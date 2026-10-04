<?php

namespace App\Database\Migrations;

use App\Services\Finance\EntitlementPolicyService;
use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * FASE 1B / M2 — Rekening SABRINA hanya untuk Unit 4 (Pandaan).
 *
 * KENAPA
 * ------
 * Rekening SABRINA RATU SALSABILLA (idbank=1, akaun 15, norek 0391796181)
 * adalah rekening milik Unit 4 Pandaan. Yang storing saat iniclaiming berbeda:
 * is_shared = 1 dengan alokasi untuk Unit 1, Unit 3, dan Unit 4.
 *
 * Unit 1, Unit 2, Unit 3, Unit 5, dan Unit 50 muncul di rekening ini hanya
 * karena histori transaksi pada form kas_masuk legacy, bukan karena keputusan
 * bisnis. Sejarah transaksi tidak pernah menetapkan pemilik rekening.
 *
 * Yang SENGAJA TIDAK disentuh:
 *   - ratusan baris kas_masuk/kas_keluar unit lain di rekening ini. Barisnya
 *     tetap ada; tidak diubah, tidak dipindah, tidak dihapus. Dilaporkan
 *     sebagai audit finding.
 *   - saldo_awal_kas_bank. Saldo real hanya dari Finance dengan status
 *     VERIFIED.
 *   - kolom nominal alokasi Unit 4 yang sudah ada.
 *
 * BENTUK MASTER
 * -------------
 * Setelah migration ini akun 15 berubah dari rekening lintas-unit menjadi
 * rekening milik satu unit: unit_id = 4 dan is_shared = 0. Ini kebalikan dari
 * M1 (CV) yang justru lintas-unit — karena kedua rekening memang beda sifat.
 *
 * IDEMPOTEN & FAIL-LOUD
 * ---------------------
 *   - Dijalankan dua kali -> hasil identik.
 *   - Berhenti dengan exception bila akun 15 hilang / menunjuk idbank selain 1,
 *     atau entitlement akhirnya bukan persis {4}.
 *   - DML dibungkus transaction; perubahan parsial tidak tertinggal.
 */
class KoreksiEntitlementSabrina extends Migration
{

    /** Pengaman: akun ini harus masih menunjuk rekening bank SABRINA. */
    private const IDBANK = '1';
    private const NAMA = 'Rekening SABRINA';

    /**
     * Nomor rekening, di-resolve dari bank_idbank -- bukan dari konstanta.
     *
     * Kenapa tidak hardcode: idakun_kas_bank berasal dari AUTO_INCREMENT, dan
     * migration 2026-09-21-000200 membuat rekening bank tanpa id eksplisit.
     * Nomor urutnya berbeda antar-linse data: dump lama punya CV=16/SABRINA=15,
     * dump produksi lain bisa CV=12/SABRINA=11. Hardcode pecah begitu dump
     * diganti. bank_idbank stabil = FK ke master `bank`, nilainya sudah
     * disahkan Finance, jadi itu yang jadi kunci.
     */
    private ?int $akunCache = null;

    private function akun(): int
    {
        if ($this->akunCache !== null) {
            return $this->akunCache;
        }

        $rows = $this->db->table('akun_kas_bank')
            ->select('idakun_kas_bank, bank_idbank')
            ->where('tipe', 'BANK')
            ->where('bank_idbank', self::IDBANK)
            ->orderBy('idakun_kas_bank', 'ASC')
            ->get()
            ->getResultArray();

        if ($rows === []) {
            throw new RuntimeException(sprintf(
                'Migration dihentikan: tidak ada akun_kas_bank dengan bank_idbank=%s (%s). '
                . 'Rekening ini tidak bisa dibetulkan dan migration ini tidak menebak akun mana yang dimaksud. '
                . 'Pastikan master `bank` punya baris idbank=%s dan rekeningnya sudah dibuat migration sebelumnya.',
                self::IDBANK,
                self::NAMA,
                self::IDBANK
            ));
        }

        if (count($rows) > 1) {
            throw new RuntimeException(sprintf(
                'Migration dihentikan: ada %d rekening kas-bank dengan bank_idbank=%s (%s): %s. '
                . 'Peta rekening jadi ambigu dan migration ini tidak memilih salah satu.',
                count($rows),
                self::IDBANK,
                self::NAMA,
                implode(', ', array_column($rows, 'idakun_kas_bank'))
            ));
        }

        return $this->akunCache = (int) $rows[0]['idakun_kas_bank'];
    }


    /** User resmi yang boleh memakai rekening ini. */
    private const UNIT_RESMI = [4];

    public function up()
    {
        if (! $this->db->tableExists('akun_kas_bank') || ! $this->db->tableExists('alokasi_saldo_kas_bank')) {
            log_message('info', '[Migration KoreksiEntitlementSabrina] tabel belum ada — dilewati.');

            return;
        }

        $this->pastikanAkunSesuai();

        $this->db->transBegin();

        try {
            $policy   = new EntitlementPolicyService();
            $sebelum  = $policy->unitEntitledDb($this->akun());
            $dihapus  = $this->buangEntitlementLuar();
            $ditambah = $this->pasangEntitlementResmi();

            $this->benahiBentukMaster();

            $this->assertTepat($policy);
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        $this->db->transCommit();

        log_message('info', sprintf(
            '[Migration KoreksiEntitlementSabrina] akun %d: entitlement %s -> [%s] (hapus %d, tambah %d); '
            . 'is_shared 1->0, unit_id NULL->%d.',
            $this->akun(),
            $sebelum === [] ? '(kosong)' : implode(',', $sebelum),
            implode(',', self::UNIT_RESMI),
            count($dihapus),
            count($ditambah),
            self::UNIT_RESMI[0]
        ));
    }

    /**
     * `down()` tidak memulihkan Unit 1 / Unit 3.
     *
     * Akses lama itu berasal dari histori transaksi, bukan keputusan bisnis.
     * Mengembalikannya berarti membuka rekening milik Unit 4 ke unit lain
     * tanpa persetujuan siapa pun.
     */
    public function down()
    {
        // Tidak ada perubahan yang aman untuk dibatalkan.
    }

    // -----------------------------------------------------------------
    // Langkah-langkah
    // -----------------------------------------------------------------

    private function pastikanAkunSesuai(): void
    {
        $row = $this->db->table('akun_kas_bank')
            ->select('idakun_kas_bank, nama_akun, bank_idbank')
            ->where('idakun_kas_bank', $this->akun())
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw new RuntimeException(
                'Migration dihentikan: akun kas-bank untuk ' . self::NAMA . ' (bank_idbank=' . self::IDBANK . ') tidak ditemukan. '
                . 'Entitlement tidak bisa dibetulkan dan migration ini tidak menebak akun mana yang dimaksud.'
            );
        }

        if ((string) ($row['bank_idbank'] ?? '') !== self::IDBANK) {
            throw new RuntimeException(sprintf(
                'Migration dihentikan: akun %d kini menunjuk idbank=%s, bukan %s (rekening SABRINA). '
                . 'Master rekening berubah — TIDAK diperbaiki otomatis.',
                $this->akun(),
                var_export($row['bank_idbank'] ?? null, true),
                self::IDBANK
            ));
        }
    }

    /** @return list<int> unit yang dihapus */
    private function buangEntitlementLuar(): array
    {
        $resmi = array_map('intval', self::UNIT_RESMI);
        $ada   = array_map('intval', array_column($this->unitAdaDb(), 'unit_id'));

        $buang = array_values(array_diff($ada, $resmi));

        foreach ($buang as $unitId) {
            $this->db->table('alokasi_saldo_kas_bank')
                ->where('akun_kas_bank_id', $this->akun())
                ->where('unit_id', $unitId)
                ->delete();
        }

        return $buang;
    }

    /** @return list<array{unit_id:int|string}> */
    private function unitAdaDb(): array
    {
        return $this->db->table('alokasi_saldo_kas_bank')
            ->select('unit_id')
            ->where('akun_kas_bank_id', $this->akun())
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<int> unit yang ditambahkan
     */
    private function pasangEntitlementResmi(): array
    {
        $ditambah = [];

        foreach (self::UNIT_RESMI as $unitId) {
            $sudah = $this->db->table('alokasi_saldo_kas_bank')
                ->where('akun_kas_bank_id', $this->akun())
                ->where('unit_id', $unitId)
                ->countAllResults() > 0;

            if ($sudah) {
                continue;
            }

            $this->db->table('alokasi_saldo_kas_bank')->insert([
                'akun_kas_bank_id' => $this->akun(),
                'unit_id'          => $unitId,
                'nominal'          => 0,
                'keterangan'       => 'Hak pakai rekening SABRINA (idbank=1) oleh Unit 4 Pandaan, '
                    . 'sesuai mapping resmi Fase 1. Nominal 0 = belum ada saldo teralokasikan.',
                'created_at'       => date('Y-m-d H:i:s'),
            ]);

            $ditambah[] = $unitId;
        }

        return $ditambah;
    }

    /** Rekening milik satu unit: unit_id = 4, is_shared = 0. */
    private function benahiBentukMaster(): void
    {
        $this->db->table('akun_kas_bank')
            ->where('idakun_kas_bank', $this->akun())
            ->update([
                'unit_id'    => self::UNIT_RESMI[0],
                'is_shared'  => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    private function assertTepat(EntitlementPolicyService $policy): void
    {
        $entitled = $policy->unitEntitledDb($this->akun());
        $resmi    = array_map('intval', self::UNIT_RESMI);

        sort($entitled);

        if ($entitled !== $resmi) {
            throw new RuntimeException(sprintf(
                'Migration GAGAL: entitlement rekening SABRINA harus persis [%s], tapi datanya [%s]. '
                . 'Tidak ada perubahan lain yang dilakukan tanpa keputusan bisnis.',
                implode(',', $resmi),
                $entitled === [] ? '(kosong)' : implode(',', $entitled)
            ));
        }

        if (! $policy->selarasDenganPolicy($this->akun())) {
            throw new RuntimeException(
                'Migration GAGAL: data rekening SABRINA belum selaras dengan policy di Config\Finance. '
                . 'Detail: ' . implode('; ', $policy->ringkasanDrift($this->akun()))
            );
        }
    }
}