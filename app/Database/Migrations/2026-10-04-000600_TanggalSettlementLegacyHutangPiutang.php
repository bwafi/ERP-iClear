<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pelunasan Legacy Hutang — kolom audit trail settlement sebelum cut-off.
 *
 * KONTEKS. Finance cut-off = 2026-10-05, Finance efektif = 2026-10-06.
 * Banyak hutang lama — terutama `mutasi_unit` (hutang antar-unit) — secara
 * faktual SUDAH lunas, tapi record ERP masih `belum_lunas`. Module ini
 * tidak menambah tabel: audit trail memakai kolom yang sudah ada dan masih
 * kosong (`cutoff_closed_at`, `cutoff_closed_by`, `cutoff_reason`).
 *
 * Yang ditambahkan HANYA dua kolom:
 *
 *  1. `tanggal_settlement_legacy` — tanggal settlement faktual. Tidak ada
 *     kolom tanggal settlement sebelumnya; `updated_at` tidak bisa dipakai
 *     karena hanya menyatakan kapan baris diubah, bukan kapan hutang
 *     diselesaikan.
 *
 *  2. `sisa_legacy_sebelum` — sisa outstanding SEBELUM ditandai lunas.
 *     Wajib ada karena settlement ini obliged `sisa = 0` supaya hutang
 *     berhenti terhitung sebagai outstanding Finance; tanpa menyimpan nilai
 *     lama, aksi "Batalkan Pelunasan Legacy" tidak bisa mengembalikan
 *     jumlah yang tepat (Aturan 7).
 *
 * `total_dibayar` SENGAJA tidak diubah. Settlement legacy bukan pembayaran:
 * tidak ada uang yang berpindah, jadi secara nominal "sudah dibayar" akan
 * berbohong. Yang ditandai adalah posisinya sudah tertutup.
 *
 * Tidak ada kolom `status_sebelum`: status sebelum settlement bisa
 * diturunkan persis dari `total_dibayar` memakai aturan yang sama dengan
 * HutangPiutangService::statusFromSisa().
 *
 * Additive & idempotent (aman rerun). Tidak menyentuh data.
 */
class TanggalSettlementLegacyHutangPiutang extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('tanggal_settlement_legacy', 'hutang_piutang')) {
            $this->forge->addColumn('hutang_piutang', [
                'tanggal_settlement_legacy' => [
                    'type'       => 'DATE',
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'cutoff_reason',
                    'comment'    => 'Tanggal settlement faktual (legacy). NULL = belum pernah ditandai lunas legacy.',
                ],
            ]);
        }

        if (!$this->db->fieldExists('sisa_legacy_sebelum', 'hutang_piutang')) {
            $this->forge->addColumn('hutang_piutang', [
                'sisa_legacy_sebelum' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'tanggal_settlement_legacy',
                    'comment'    => 'Sisa outstanding sebelum ditandai lunas legacy; dipakai untuk pembatalan.',
                ],
            ]);
        }
    }

    public function down()
    {
        // Additive-only: tidak ada data yang ditulis migration ini, jadi
        // drop kolom aman dan tidak menghilangkan histori settlement.
        foreach (['sisa_legacy_sebelum', 'tanggal_settlement_legacy'] as $col) {
            if ($this->db->fieldExists($col, 'hutang_piutang')) {
                $this->forge->dropColumn('hutang_piutang', $col, true);
            }
        }
    }
}