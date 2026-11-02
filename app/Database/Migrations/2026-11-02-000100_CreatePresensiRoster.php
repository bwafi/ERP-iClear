<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Roster absensi per pegawai per tanggal.
 *
 * Menggantikan master `jadwal_masuk` untuk operasional. Baris di sini adalah
 * jadwal satu hari; pola 7 hari hanya dipakai UI untuk mengisi tanggal kosong.
 */
class CreatePresensiRoster extends Migration
{
    public function up()
    {
        // Forge/DDL tidak membersihkan cache skema; reset supaya up() idempoten
        // walau dipanggil dua kali dalam satu proses (test/migrator).
        $this->db->dataCache['table_names'] = [];

        if ($this->db->tableExists('presensi_roster')) {
            return;
        }

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'akun_idakun' => ['type' => 'INT', 'constraint' => 11],
            'tanggal'     => ['type' => 'DATE'],
            'shift'       => ['type' => 'VARCHAR', 'constraint' => 10],
            'keterangan'  => ['type' => 'TEXT', 'null' => true],
            'created_by'  => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'updated_by'  => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['akun_idakun', 'tanggal']);
        $this->forge->addKey('tanggal');
        $this->forge->createTable('presensi_roster', true);
        $this->db->dataCache['table_names'] = [];
    }

    public function down()
    {
        $this->forge->dropTable('presensi_roster', true);
        $this->db->dataCache['table_names'] = [];
    }
}
