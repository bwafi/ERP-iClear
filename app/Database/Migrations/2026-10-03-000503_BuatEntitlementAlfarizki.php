<?php

namespace App\Database\Migrations;

use App\Services\Finance\EntitlementPolicyService;
use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * FASE 1B / M3 — Pasang entitlement Unit 3 pada rekening ALFARIZKI.
 *
 * KENAPA
 * ------
 * Rekening ALFARIZKI (idbank=5, akaun 3, norek 1802016667) adalah rekening
 * milik Unit 3 Banyuwangi. Bentuk master-nya sudah benar (unit_id = 3,
 * is_shared = 0), TAPI alokasi_saldo_kas_bank-nya kosong — tidak ada satu pun
 * baris yang menyatakan Unit 3 berhak memakai rekening ini.
 *
 * Akibatnya scope berbasis entitlement tidak pernah bisa menampilkan
 * rekening ini ke Unit 3, padahal itu satu-satunya unit yang berhak.
 *
 * Yang SENGAJA TIDAK disentuh
 * --------------------------
 *   - transaksi historis Unit 5 Genteng di rekening ini. Unit 5 memakai
 *     rekening ini karena rekening resminya sendiri (norek 1802016123 atas
 *     nama Iclear Genteng) BELUM ADA di master — nomor rekeningnya belum
 *     diverifikasi Finance, jadi tidak boleh dibuat pada fase ini.
 *     12 baris itu (8 kas_keluar + 4 transaksi_kas_bank) tetap utuh dan
 *     dilaporkan sebagai audit finding, bukan dihapus atau dipindah.
 *   - saldo_awal_kas_bank. Saldo real hanya dari Finance dengan status
 *     VERIFIED; migration ini tidak mengarang angka dan tidak membuat
 *     opening ledger transaction.
 *
 * NOMINAL
 * -------
 * Alokasi baru dipasang dengan nominal 0. Itu berarti "Unit 3 punya HAK
 * memakai rekening ini", bukan "Unit 3 tidak punya saldo". Nominal 0 juga
 * TIDAK berarti saldo fisik rekening ini 0 — saldo fisik hanya boleh dibaca
 * dari statement VERIFIED.
 *
 * IDEMPOTEN & FAIL-LOUD
 * ---------------------
 *   - Dijalankan dua kali -> hasil identik.
 *   - Berhenti dengan exception bila akun 3 hilang / menunjuk idbank selain 5,
 *     atau bila sudah ada alokasi untuk unit LAIN selain 3 (konflik master
 *     tidak diselesaikan diam-diam).
 *   - DML dibungkus transaction.
 */
class BuatEntitlementAlfarizki extends Migration
{

    /** Pengaman: akun ini harus masih menunjuk rekening bank ALFARIZKI. */
    private const IDBANK = '5';
    private const NAMA = 'Rekening ALFARIZKI';

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
    private const UNIT_RESMI = [3];

    public function up()
    {
        if (! $this->db->tableExists('akun_kas_bank') || ! $this->db->tableExists('alokasi_saldo_kas_bank')) {
            log_message('info', '[Migration BuatEntitlementAlfarizki] tabel belum ada — dilewati.');

            return;
        }

        $this->pastikanAkunSesuai();

        $this->db->transBegin();

        try {
            $policy = new EntitlementPolicyService();
            $sebelum = $policy->unitEntitledDb($this->akun());

            $this->tolakKonflikUnitLain();
            $ditambah = $this->pasangEntitlementResmi();

            $this->benahiBentukMaster();

            $this->assertTepat($policy);
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        $this->db->transCommit();

        log_message('info', sprintf(
            '[Migration BuatEntitlementAlfarizki] akun %d: entitlement %s -> [%s] (tambah %d).',
            $this->akun(),
            $sebelum === [] ? '(kosong)' : implode(',', $sebelum),
            implode(',', self::UNIT_RESMI),
            count($ditambah)
        ));
    }

    public function down()
    {
        // Menghapus entitlement Unit 3 akan menutup rekening milik Unit 3 dari
        // satu-satunya unit yang berhak. Tidak ada rollback yang aman; bila
        // dibatalkan, kembalikan lewat keputusan bisnis.
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
                'Migration dihentikan: akun_kas_bank ' . $this->akun() . ' (bank_idbank=' . self::IDBANK . ') tidak ditemukan. '
                . 'Entitlement tidak bisa dipasang dan migration ini tidak menebak akun mana yang dimaksud.'
            );
        }

        if ((string) ($row['bank_idbank'] ?? '') !== self::IDBANK) {
            throw new RuntimeException(sprintf(
                'Migration dihentikan: akun %d kini menunjuk idbank=%s, bukan %s (rekening ALFARIZKI). '
                . 'Master rekening berubah — TIDAK diperbaiki otomatis.',
                $this->akun(),
                var_export($row['bank_idbank'] ?? null, true),
                self::IDBANK
            ));
        }
    }

    /**
     * Migration ini HANYA menambah, tidak pernah mengoreksi entitlement yang
     * sudah ada. Kalau ternyata ada alokasi untuk unit lain, itu konflik yang
     * harus diputuskan manusia — bukan dihapus sepele oleh migration.
     */
    private function tolakKonflikUnitLain(): void
    {
        $resmi = array_map('intval', self::UNIT_RESMI);
        $ada   = array_map('intval', array_column($this->unitAdaDb(), 'unit_id'));
        $asing = array_values(array_diff($ada, $resmi));

        if ($asing === []) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Migration dihentikan: akun %d punya alokasi untuk unit [%s], padahal mapping resmi hanya [%s]. '
            . 'Konflik master TIDAK diselesaikan otomatis — hapus atau pertahankan alokasi itu sebagai '
            . 'keputusan bisnis, lalu jalankan ulang migration.',
            $this->akun(),
            implode(',', $asing),
            implode(',', $resmi)
        ));
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

    /** @return list<int> unit yang ditambahkan */
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
                'keterangan'       => 'Hak pakai rekening ALFARIZKI (idbank=5) oleh Unit 3 Banyuwangi, '
                    . 'sesuai mapping resmi Fase 1. Nominal 0 = belum ada saldo teralokasikan, '
                    . 'BUKAN tanpa hak dan BUKAN saldo fisik rekening 0.',
                'created_at'       => date('Y-m-d H:i:s'),
            ]);

            $ditambah[] = $unitId;
        }

        return $ditambah;
    }

    /** Rekening milik satu unit: unit_id = 3, is_shared = 0. */
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
                'Migration GAGAL: entitlement rekening ALFARIZKI harus persis [%s], tapi datanya [%s]. '
                . 'Unit 5 Genteng yang pernah memakainya TIDAK ditulis di sini — rekening resminya belum '
                . 'diverifikasi Finance, dan transaksi historisnya tidak boleh dipindahkan.',
                implode(',', $resmi),
                $entitled === [] ? '(kosong)' : implode(',', $entitled)
            ));
        }

        if (! $policy->selarasDenganPolicy($this->akun())) {
            throw new RuntimeException(
                'Migration GAGAL: data rekening ALFARIZKI belum selaras dengan policy di Config\Finance. '
                . 'Detail: ' . implode('; ', $policy->ringkasanDrift($this->akun()))
            );
        }
    }
}