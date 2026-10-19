<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Komentar kolom `mutasi.status` masih menyebut nilai lama (1 = kirim,
 * 2 = terima), padahal kode hanya memakai 0 = menunggu dan 1 = diterima.
 * Perbaiki komentar tanpa mengubah tipe/isi kolom.
 */
class FixMutasiStatusComment extends Migration
{
    private const KOMENTAR = '0 = menunggu, 1 = diterima';

    public function up()
    {
        if (! $this->db->fieldExists('status', 'mutasi')) {
            return;
        }

        $this->forge->modifyColumn('mutasi', [
            'status' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
                'null'       => true,
                'default'    => null,
                'comment'    => self::KOMENTAR,
            ],
        ]);
    }

    public function down()
    {
        if (! $this->db->fieldExists('status', 'mutasi')) {
            return;
        }

        $this->forge->modifyColumn('mutasi', [
            'status' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
                'null'       => true,
                'default'    => null,
                'comment'    => "0 = menunggu\n1 = kirim\n2 = terima",
            ],
        ]);
    }
}
