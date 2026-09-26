<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Finance Cut-off — kolom scope data hutang/piutang.
 *
 * Prinsip (keputusan bisnis, lihat README FinanceCutoff):
 *  - `scope = legacy`  : histori sistem lama (sebelum cut-off, non-kasbon).
 *                        TIDAK dihitung sebagai saldo aktif/aging/tempo.
 *  - `scope = opening` : saldo yang sengaja dibawa masuk (kasbon pegawai lama
 *                        yang masih outstanding, saldo Kas & Bank as-of cut-off
 *                        disimpan di saldo_awal_kas_bank).
 *  - `scope = active`  : transaksi pada/setelah cut-off (engine Finance baru).
 *
 * Status asli (belum_lunas/sebagian/lunas) dipertahankan — "ditutup saat
 * release", bukan "dibayar". Tidak ada pembayaran fiktif; tidak ada hapus.
 * Additive & dijalankan dengan guard (aman bila rerun).
 */
class AddFinanceCutoffScopeToHutangPiutang extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('scope', 'hutang_piutang')) {
            $this->forge->addColumn('hutang_piutang', [
                'scope' => [
                    'type'       => 'ENUM',
                    'constraint' => "'active','opening','legacy'",
                    'null'       => false,
                    'default'    => 'active',
                    'after'      => 'status',
                ],
            ]);
        }
        if (!$this->db->fieldExists('cutoff_closed_at', 'hutang_piutang')) {
            $this->forge->addColumn('hutang_piutang', [
                'cutoff_closed_at' => [
                    'type'    => 'DATETIME',
                    'null'    => true,
                    'default' => null,
                    'after'   => 'scope',
                ],
            ]);
        }
        if (!$this->db->fieldExists('cutoff_closed_by', 'hutang_piutang')) {
            $this->forge->addColumn('hutang_piutang', [
                'cutoff_closed_by' => [
                    'type'       => 'INTEGER',
                    'constraint' => 11,
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'cutoff_closed_at',
                ],
            ]);
        }
        if (!$this->db->fieldExists('cutoff_reason', 'hutang_piutang')) {
            $this->forge->addColumn('hutang_piutang', [
                'cutoff_reason' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'cutoff_closed_by',
                ],
            ]);
        }
    }

    public function down()
    {
        foreach (['scope', 'cutoff_closed_at', 'cutoff_closed_by', 'cutoff_reason'] as $col) {
            if ($this->db->fieldExists($col, 'hutang_piutang')) {
                $this->forge->dropColumn('hutang_piutang', $col);
            }
        }
    }
}