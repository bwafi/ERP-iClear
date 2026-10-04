<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Audit trail pembatalan Pelunasan Legacy.
 *
 * Kenapa kolom terpisah, bukan reuse kolom yang sudah ada:
 *
 * `cutoff_reason` + `cutoff_closed_at` + `cutoff_closed_by` adalah penanda
 * AKTIF. Selama isinya 'legacy', baris dianggap sudah lunas legacy
 * (lihat HutangPiutangService::settlementLegacyAktif()). Kalau pembatalan
 * mengosongkan ketiganya, bukti settlement ikut hilang.
 *
 * Sebaliknya, kalau ketiganya dibiarkan terisi, baris tetap terbaca lunas
 * padahal sudah dikembalikan jadi outstanding.
 *
 * Solusinya: penanda aktif dikosongkan (baris jadi outstanding lagi),
 * sementara jejak settlement tetap disimpan di kolom dedicated, dan
 * pembatalannya dicatat terpisah. Jadi kedua event tetap bisa dibaca.
 */
class AuditPembatalanLegacyHutangPiutang extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('legacy_dibatalkan_at', 'hutang_piutang')) {
            $this->forge->addColumn('hutang_piutang', [
                'legacy_dibatalkan_at' => [
                    'type'    => 'DATETIME',
                    'null'    => true,
                    'default' => null,
                    'after'   => 'sisa_legacy_sebelum',
                    'comment' => 'Waktu pembatalan pelunasan legacy. NULL = belum pernah dibatalkan.',
                ],
            ]);
        }

        if (!$this->db->fieldExists('legacy_dibatalkan_by', 'hutang_piutang')) {
            $this->forge->addColumn('hutang_piutang', [
                'legacy_dibatalkan_by' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'legacy_dibatalkan_at',
                    'comment'    => 'ID user yang membatalkan pelunasan legacy.',
                ],
            ]);
        }

        if (!$this->db->fieldExists('legacy_alasan_pembatalan', 'hutang_piutang')) {
            $this->forge->addColumn('hutang_piutang', [
                'legacy_alasan_pembatalan' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'legacy_dibatalkan_by',
                    'comment'    => 'Alasan pembatalan pelunasan legacy.',
                ],
            ]);
        }
    }

    public function down()
    {
        // Kolom audit: rollback TIDAK aman membutanya karena history settlement
        // dan pembatalannya hilang permanen. Sengaja dibiarkan kosong supaya
        // tidak ada yang menghapus bukti.
        foreach (['legacy_alasan_pembatalan', 'legacy_dibatalkan_by', 'legacy_dibatalkan_at'] as $col) {
            if ($this->db->fieldExists($col, 'hutang_piutang')) {
                $this->forge->dropColumn('hutang_piutang', $col, true);
            }
        }
    }
}