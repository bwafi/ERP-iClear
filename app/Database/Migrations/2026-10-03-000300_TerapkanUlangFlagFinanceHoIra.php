<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Terapkan kembali keputusan "rekening IRA = Finance/HO" ke data produksi.
 *
 * KENAPA MIGRATION INI ADA
 * -----------------------
 * 2026-09-30-001100_MarkIraAsFinanceHoAccount sudah tercatat di tabel
 * `migrations` (batch 16), jadi `up()`-nya tidak akan dieksekusi lagi,
 * sedangkan DB produksi masih menunjukkan is_finance_ho = 0 untuk
 * akun 1. Artinya keputusan bisnis yang sudah disetujui itu TIDAK berlaku
 * di data — dan lima test verifikasi wajib di tests/KasBankTest.php
 * (testAkun1TerpantauFinanceHo, testAkun1TidakButuhAlokasi,
 * testAkun1TujuanBolehUntukSemuaUnit, testAkun1SumberHanyaRole0Dan1)
 * gagal karena itu.
 *
 * Ini BUKAN keputusan baru. Migration ini mengulang keputusan yang sama,
 * dengan penjaga yang sama persis seperti 001100, plus dua pengaman tambahan:
 *
 *   1. SPESIFIK: UPDATE dikunci ke idakun_kas_bank = IRA_ID dan
 *      bank_idbank = IRA_BANK_ID. Tidak ada akun lain yang bisa ikut
 *      tersentuh, termasuk akun unit.
 *   2. BENTUK HARUS COCOK: akun hanya ditandai bila masih
 *      unit_id IS NULL + is_shared = 1, yaitu persis bentuk rekening
 *      shared antar-unit. Bila suatu saat akun ini dipakai ulang jadi
 *      rekening milik unit, migration ini berhenti dan TIDAK menandainya
 *      diam-diam — supaya akun unit tidak ikut masuk scope Finance/HO.
 *
 * Akun 4 ("Bank BCA 3251427508 (FARA DINDA AYUWANDA)") tetap di luar
 * jangkauan migration ini, sama seperti pada 001100.
 *
 * Yang SENGAJA TIDAK dilakukan:
 *   - tidak menambah alokasi_saldo_kas_bank (rekening HO tidak butuh);
 *   - tidak mengubah unit_id / is_shared / status / nama akun;
 *   - tidak mengubah mapping bank, cutoff, atau saldo awal;
 *   - tidak menyentuh baris transaksi apa pun.
 */
class TerapkanUlangFlagFinanceHoIra extends Migration
{
    /** Rekening yang disepakati menjadi Finance/HO. */
    private const IRA_ID = 1;

    /** Pengaman tambahan: pastikan akun tsb masih rekening bank IRA. */
    private const IRA_BANK_ID = '3';

    public function up()
    {
        if (! $this->db->tableExists('akun_kas_bank') || ! $this->db->fieldExists('is_finance_ho', 'akun_kas_bank')) {
            return;
        }

        $row = $this->db->query(
            'SELECT idakun_kas_bank, nama_akun, unit_id, bank_idbank, is_shared, is_finance_ho
               FROM akun_kas_bank
              WHERE idakun_kas_bank = ' . self::IRA_ID
        )->getRow();

        if ($row === null) {
            log_message('warning', '[Migration TerapkanUlangFlagFinanceHoIra] akun ' . self::IRA_ID . ' tidak ada — dilewati.');

            return;
        }

        // Sudah benar -> tidak ada yang perlu diubah.
        if ((int) $row->is_finance_ho === 1) {
            return;
        }

        // Rekening sudah berpindah ke rekening bank lain -> jangan sentuh.
        if ((string) $row->bank_idbank !== self::IRA_BANK_ID) {
            log_message('warning', sprintf(
                '[Migration TerapkanUlangFlagFinanceHoIra] akun %d kini menunjuk idbank=%s (bukan %s) — dilewati.',
                self::IRA_ID,
                (string) $row->bank_idbank,
                self::IRA_BANK_ID
            ));

            return;
        }

        // Bentuknya harus persis rekening HO: bukan milik unit + shared.
        if ($row->unit_id !== null || (int) $row->is_shared !== 1) {
            log_message('warning', sprintf(
                '[Migration TerapkanUlangFlagFinanceHoIra] akun %d bentuknya sudah berubah '
                    . '(unit_id=%s, is_shared=%d) — TIDAK ditandai sebagai Finance/HO.',
                self::IRA_ID,
                $row->unit_id === null ? 'NULL' : (string) $row->unit_id,
                (int) $row->is_shared
            ));

            return;
        }

        $this->db->query(
            'UPDATE akun_kas_bank SET is_finance_ho = 1
              WHERE idakun_kas_bank = ' . self::IRA_ID
                . ' AND is_finance_ho = 0 AND unit_id IS NULL AND is_shared = 1'
        );

        log_message('info', sprintf(
            '[Migration TerapkanUlangFlagFinanceHoIra] akun %d "%s" ditandai Finance/HO.',
            self::IRA_ID,
            (string) $row->nama_akun
        ));
    }

    /**
     * Hanya membalik flag yang migration ini pasang, dengan penjaga yang SAMA
     * dengan `up()`: akun harus masih menunjuk rekening bank IRA dan masih
     * berbentuk shared. Kalau shape-nya sudah berubah, `down()` berhenti
     * supaya tidak ikut membuka akses HO pada akun yang bentuknya sudah lain.
     */
    public function down()
    {
        if (! $this->db->tableExists('akun_kas_bank') || ! $this->db->fieldExists('is_finance_ho', 'akun_kas_bank')) {
            return;
        }

        $row = $this->db->query(
            'SELECT idakun_kas_bank, bank_idbank, unit_id, is_shared, is_finance_ho
               FROM akun_kas_bank
              WHERE idakun_kas_bank = ' . self::IRA_ID
        )->getRow();

        if ($row === null
            || (int) $row->is_finance_ho !== 1
            || (string) $row->bank_idbank !== self::IRA_BANK_ID
            || $row->unit_id !== null
            || (int) $row->is_shared !== 1
        ) {
            return;
        }

        $this->db->query(
            'UPDATE akun_kas_bank SET is_finance_ho = 0
              WHERE idakun_kas_bank = ' . self::IRA_ID
                . ' AND is_finance_ho = 1 AND unit_id IS NULL AND is_shared = 1'
        );
    }
}
