<?php

namespace App\Database\Migrations;

use App\Services\Finance\EntitlementPolicyService;
use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * FASE 1B / M1 — KEntitlement rekening CV dibatasi ke Unit 1 dan Unit 2.
 *
 * KENAPA
 * ------
 * Rekening CV (idbank=2, akaun 16) adalah satu rekening fisik yang dipakai
 * DUA unit: Unit 1 Probolinggo dan Unit 2 Jember. Tapi alokasi_saldo_kas_bank
 * saat ini memuat EMPAT unit: 1, 2, 4, dan 50.
 *
 * Unit 4 (Pandaan) dan Unit 50 (Head Office) BUKAN pengguna resmi. Keduanya
 * hanya muncul karena histori transaksi, dan histori transaksi TIDAK pernah
 * jadi sumber kebenaran kepemilikan sebuah rekening.
 *
 * Yang SENGAJA TIDAK disentuh:
 *   - transaksi historis Unit 4 & Unit 50 di rekening ini. Barisnya tetap
 *     ada dan tidak diubah pagno=> dibuang. Asalnya dilaporkan sebagai
 *     audit finding (lihat app/Commands/KasBankAuditRekening.php).
 *   - saldo_awal_kas_bank. Saldo real hanya boleh diisi Finance dengan
 *     status VERIFIED; migration ini tidak mengarang saldo dan tidak membuat
 *     opening ledger transaction.
 *   - kolom nominal pada alokasi yang sudah ada. Nominal = 0 berarti unit
 *     punya HAK memakai rekening tapi belum ada saldo teralokasikan — bukan
 *     saldo fisik rekening 0, dan bukan berarti tanpa hak.
 *
 * IDEMPOTEN & FAIL-LOUD
 * ---------------------
 *   - Dijalankan dua kali -> hasil identik, operasi kedua kosong.
 *   - Berhenti dengan exception (bukan log diam-diam) bila:
 *       * akun 16 tidak ada atau menunjuk idbank lain dari 2;
 *       * bentuk master tidak bisa dibetulkan;
 *       * setelah semua langkah, entitlementnya TIDAK persis {1,2}.
 *   - Transaksi DML dibungkus transaction supaya perubahan parsial tidak
 *     tertinggal bila assertion gagal di tengah jalan.
 */
class KoreksiEntitlementCv extends Migration
{

    /** Pengaman: akun ini harus masih menunjuk rekening bank CV. */
    private const IDBANK = '2';
    private const NAMA = 'Rekening CV';

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
    private const UNIT_RESMI = [1, 2];

    public function up()
    {
        if (! $this->db->tableExists('akun_kas_bank') || ! $this->db->tableExists('alokasi_saldo_kas_bank')) {
            // Struktur belum ada — biarkan migration struktur yang mendahului.
            log_message('info', '[Migration KoreksiEntitlementCv] tabel belum ada — dilewati.');

            return;
        }

        $this->pastikanAkunSesuai();

        // CI4 tidak membungkus migration dalam transaction, jadi diyponsini.
        // Semua operasi di bawah murni DML (tidak ada DDL), jadi aman di-rollback.
        $this->db->transBegin();

        try {
            $policy  = new EntitlementPolicyService();
            $sebelum = $policy->unitEntitledDb($this->akun());
            $dihapus = $this->buangEntitlementLuar();
            $ditambah = $this->pasangEntitlementResmi();

            $this->benahiBentukMaster();

            $this->assertTepat($policy);
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        $this->db->transCommit();

        log_message('info', sprintf(
            '[Migration KoreksiEntitlementCv] akun %d: entitlement %s -> %s (hapus %d, tambah %d).',
            $this->akun(),
            $sebelum === [] ? '(kosong)' : implode(',', $sebelum),
            implode(',', self::UNIT_RESMI),
            count($dihapus),
            count($ditambah)
        ));
    }

    /**
     * `down()` TIDAK memulihkan alokasi lama.
     *
     * Alokasi lama (Unit 4, Unit 50) berasal dari histori transaksi, bukan
     * dari keputusan bisnis. Mengembalikannya akan mengembalikan akses yang
     * memang tidak pernah disetujui. Bila `migrate:rollback` memang
     * diperlukan, kembalikan lewat keputusan bisnis — bukan lewat tebakan.
     */
    public function down()
    {
        // Tidak ada perubahan yang aman untuk dibatalkan.
    }

    // -----------------------------------------------------------------
    // Langkah-langkah
    // -----------------------------------------------------------------

    /** Stop keras bila akun hilang atau menunjuk rekening lain. */
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
                'Migration dihentikan: akun %d kini menunjuk idbank=%s, bukan %s (rekening CV). '
                . 'Master rekening berubah — TIDAK diperbaiki otomatis. Tentukan manualrekening mana '
                . 'yang formal untuk Unit 1 & Unit 2, lalu jalankan ulang.',
                $this->akun(),
                var_export($row['bank_idbank'] ?? null, true),
                self::IDBANK
            ));
        }
    }

    /**
     * Hapus alokasi unit yang tidak resmi.
     *
     * @return list<int> unit yang dihapus
     */
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
     * Pasang alokasi untuk unit resmi yang belum punya.
     *
     * `nominal` TIDAK pernah di-update untuk baris yang sudah ada — kalau
     * suatu saat Finance mengalokasikan saldobenar, migration ini tidak boleh
     * menimpanya dengan 0.
     *
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
                'keterangan'       => 'Hak pakai rekening CV (idbank=2) sesuai mapping resmi Fase 1. '
                    . 'Nominal 0 = belum ada saldo yang dialokasikan, BUKAN tanpa hak dan BUKAN saldo fisik 0.',
                'created_at'       => date('Y-m-d H:i:s'),
            ]);

            $ditambah[] = $unitId;
        }

        return $ditambah;
    }

    /**
     * CV = rekening lintas unit: unit_id NULL, is_shared 1.
     *
     * `unit_id` dibiarkan NULL karena kolom itu berarti "unit pemilik awal".
     * Untuk rekening shared, yang menentukan akses adalah alokasi per unit,
     * bukan satu unit_id.
     */
    private function benahiBentukMaster(): void
    {
        $this->db->table('akun_kas_bank')
            ->where('idakun_kas_bank', $this->akun())
            ->update([
                'unit_id'    => null,
                'is_shared'  => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /** Assertion akhir. Gagal = exception, bukan log. */
    private function assertTepat(EntitlementPolicyService $policy): void
    {
        $entitled = $policy->unitEntitledDb($this->akun());
        $resmi    = array_map('intval', self::UNIT_RESMI);

        sort($entitled);

        if ($entitled !== $resmi) {
            throw new RuntimeException(sprintf(
                'Migration GAGAL: entitlement rekening CV harus persis [%s], tapi datanya [%s]. '
                . 'Tidak ada perubahan lain yang dilakukan tanpa keputusan bisnis.',
                implode(',', $resmi),
                $entitled === [] ? '(kosong)' : implode(',', $entitled)
            ));
        }

        if (! $policy->selarasDenganPolicy($this->akun())) {
            throw new RuntimeException(
                'Migration GAGAL: data rekening CV belum selaras dengan policy di Config\Finance. '
                . 'Detail: ' . implode('; ', $policy->ringkasanDrift($this->akun()))
            );
        }
    }
}