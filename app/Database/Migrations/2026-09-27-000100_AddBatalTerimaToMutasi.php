<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Batal Terima Mutasi Stok — jejak pembatalan.
 *
 * Saat mutasi antar unit diterima, dua efek terjadi bersamaan (lihat
 * MutasiStok::terima()): status mutasi jadi '1', dan ModeKasBank membuat
 * sepasang Hutang/Piutang antar unit. Membatalkan penerimaan berarti
 * membatalkan KEDUA efek itu, jadi alasan pembatalan harus punya tempat
 * sendiri — bukan ditumpangkan ke kolom yang sudah dipakai.
 *
 * `input_by` sengaja TIDAK dipakai untuk ini. Kolom itu sudah tercemar:
 * MutasiStok::insert() mengisinya dengan pembuat mutasi, lalu
 * MutasiStok::terima() menimpanya dengan penerima. Setelah diterima, siapa
 * yang membuat sudah tidak bisa dibedakan dari siapa yang menerima. Bug itu
 * ada sejak awal dan sengaja tidak disentuh di sini; pembatalan memakai
 * kolom sendiri supaya jejaknya tidak tertukar dengan siapa pun.
 *
 * Additive & dijalankan dengan guard (aman bila rerun).
 */
class AddBatalTerimaToMutasi extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('batal_oleh', 'mutasi')) {
            $this->forge->addColumn('mutasi', [
                'batal_oleh' => [
                    'type'       => 'INTEGER',
                    'constraint' => 11,
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'input_by',
                ],
            ]);
        }
        if (!$this->db->fieldExists('batal_at', 'mutasi')) {
            $this->forge->addColumn('mutasi', [
                'batal_at' => [
                    'type'    => 'DATETIME',
                    'null'    => true,
                    'default' => null,
                    'after'   => 'batal_oleh',
                ],
            ]);
        }
        if (!$this->db->fieldExists('batal_alasan', 'mutasi')) {
            $this->forge->addColumn('mutasi', [
                'batal_alasan' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'batal_at',
                ],
            ]);
        }
    }

    public function down()
    {
        foreach (['batal_alasan', 'batal_at', 'batal_oleh'] as $col) {
            if ($this->db->fieldExists($col, 'mutasi')) {
                $this->forge->dropColumn('mutasi', $col);
            }
        }
    }
}
