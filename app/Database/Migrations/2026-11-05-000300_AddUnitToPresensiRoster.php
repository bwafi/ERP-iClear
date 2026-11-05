<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Unit kerja per tanggal roster. NULL berarti unit pegawai sendiri.
 */
class AddUnitToPresensiRoster extends Migration
{
    public function up()
    {
        $this->db->dataCache['table_names'] = [];
        $this->db->dataCache['field_names'] = [];

        if (! $this->db->tableExists('presensi_roster')
            || $this->db->fieldExists('unit_idunit', 'presensi_roster')) {
            return;
        }

        $this->forge->addColumn('presensi_roster', [
            'unit_idunit' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
                'after'      => 'shift',
                'comment'    => 'Unit kerja; NULL = unit pegawai',
            ],
        ]);
        $this->db->dataCache['field_names'] = [];
    }

    public function down()
    {
        if (! $this->db->tableExists('presensi_roster')
            || ! $this->db->fieldExists('unit_idunit', 'presensi_roster')) {
            return;
        }

        $this->forge->dropColumn('presensi_roster', 'unit_idunit');
        $this->db->dataCache['field_names'] = [];
    }
}
