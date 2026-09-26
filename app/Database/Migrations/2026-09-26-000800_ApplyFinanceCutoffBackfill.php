<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Finance Cut-off — backfill data (idempotent, aman dijalankan berulang).
 *
 * 1) Menentukan tanggal cut-off dari Config\Finance::cutoffDate.
 * 2) Non-kasbon dengan tanggal transaksi < cut-off → scope=LEGACY_CLOSED
 *    (kolom scope='legacy', cutoff meta diisi). Data tidak dihapus,
 *    status asli dipertahankan, TIDAK ada pembayaran fiktif.
 * 3) Kasbon pre-cut-off yang masih bersisa → scope='opening' (outstanding
 *    yang dibawa ke engine baru, tetap bisa dipotong payroll).
 * 4) Kasbon pre-cut-off yang sudah lunas (sisa <= 0) → scope='legacy'
 *    (tidak dijadikan opening balance, tidak diduplikasi).
 * 5) Kas & Bank: `saldo_awal_kas_bank` diinterpretasikan sebagai saldo riil
 *    as-of cut-off (tanggal dicap = cut-off). Akun aktif yang belum punya
 *    baris saldo awal dibuat dengan saldo 0 + penanda UNVERIFIED — nilai riil
 *    harus diisi Finance via form Kas & Bank (angka TIDAK dikarang di sini).
 *    Transaksi sebelum cut-off tidak ikut dihitung ulang (lihat
 *    ModelTransaksiKasBank::getSaldoAkun).
 */
class ApplyFinanceCutoffBackfill extends Migration
{
    public function up()
    {
        $cutoff = (string) (new \Config\Finance())->cutoffDate;
        if ($cutoff === '') {
            return;
        }

        $cutoffAt = $cutoff . ' 23:59:59';

        // 2. Non-kasbon sebelum cut-off → legacy.
        $this->db->query('
            UPDATE `hutang_piutang`
            SET scope = "legacy",
                cutoff_closed_at   = COALESCE(cutoff_closed_at, :cutoff_at:),
                cutoff_closed_by   = 0,
                cutoff_reason      = "LEGACY_CUTOFF"
            WHERE deleted = 0
              AND scope   = "active"
              AND sumber_tipe <> "kasbon"
              AND tanggal <  :cutoff:
        ', ['cutoff' => $cutoff, 'cutoff_at' => $cutoffAt]);

        // 4. Kasbon pre-cut-off yang sudah lunas → legacy (bukan opening).
        $this->db->query('
            UPDATE `hutang_piutang`
            SET scope = "legacy",
                cutoff_closed_at   = COALESCE(cutoff_closed_at, :cutoff_at:),
                cutoff_closed_by   = 0,
                cutoff_reason      = "LEGACY_CUTOFF"
            WHERE deleted = 0
              AND scope   = "active"
              AND sumber_tipe = "kasbon"
              AND sisa <= 0
              AND tanggal <  :cutoff:
        ', ['cutoff' => $cutoff, 'cutoff_at' => $cutoffAt]);

        // 3. Kasbon pre-cut-off masih outstanding → opening balance.
        $this->db->query('
            UPDATE `hutang_piutang`
            SET scope = "opening"
            WHERE deleted = 0
              AND scope   = "active"
              AND sumber_tipe = "kasbon"
              AND sisa > 0
              AND tanggal <  :cutoff:
        ', ['cutoff' => $cutoff]);

        // 5a. Cap tanggal saldo awal = cut-off (opening balance as-of cut-off).
        $this->db->table('saldo_awal_kas_bank')->update(['tanggal' => $cutoff]);

        // 5b. Siapkan baris saldo awal untuk akun aktif yang belum punya
        //     (saldo 0 + penanda; nilai riil diisi Finance via form).
        $akunRows = $this->db->table('akun_kas_bank')
            ->where('status', 'aktif')
            ->get()->getResult();
        foreach ($akunRows as $akun) {
            $ada = (int) $this->db->table('saldo_awal_kas_bank')
                ->where('akun_kas_bank_id', $akun->idakun_kas_bank)
                ->countAllResults();
            if ($ada === 0) {
                $this->db->table('saldo_awal_kas_bank')->insert([
                    'akun_kas_bank_id' => $akun->idakun_kas_bank,
                    'tanggal'          => $cutoff,
                    'saldo'            => 0,
                    'keterangan'       => 'OPENING-BELUM-VERIFIKASI',
                ]);
            }
        }
    }

    public function down()
    {
        // Rollback terbatas: kembalikan scope ke active untuk baris yang
        // ditandai LEGACY_CUTOFF oleh migrasi ini (metadata kedaluwarsa).
        $this->db->query('
            UPDATE `hutang_piutang`
            SET scope = "active",
                cutoff_closed_at = NULL,
                cutoff_closed_by = NULL,
                cutoff_reason    = NULL
            WHERE cutoff_reason = "LEGACY_CUTOFF"
        ');
    }
}