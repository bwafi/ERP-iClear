<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sesi dan sumber pada `presensi`.
 *
 * `sesi` membedakan baris PS pagi/sore (FULL untuk shift biasa); `sumber`
 * menandai absen mobile vs input manual/legacy.
 */
class AddSesiSumberToPresensi extends Migration
{
    public function up()
    {
        // Forge/DDL tidak membersihkan cache skema; reset supaya up() idempoten
        // walau dipanggil dua kali dalam satu proses (test/migrator).
        $this->db->dataCache['table_names'] = [];
        $this->db->dataCache['field_names'] = [];

        if (! $this->db->tableExists('presensi')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('sesi', 'presensi')) {
            $fields['sesi'] = ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true];
        }
        if (! $this->db->fieldExists('sumber', 'presensi')) {
            $fields['sumber'] = ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true];
        }
        if ($fields !== []) {
            $this->forge->addColumn('presensi', $fields);
            $this->db->dataCache['field_names'] = [];
        }

        $indexes = $this->db->getIndexData('presensi');
        if (! isset($indexes['idx_presensi_akun_masuk'])) {
            $this->forge->addKey(['akun_idakun', 'waktu_masuk'], false, false, 'idx_presensi_akun_masuk');
            $this->forge->processIndexes('presensi');
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('presensi')) {
            return;
        }

        $indexes = $this->db->getIndexData('presensi');
        if (isset($indexes['idx_presensi_akun_masuk'])) {
            $this->forge->dropKey('presensi', 'idx_presensi_akun_masuk', false);
        }

        foreach (['sesi', 'sumber'] as $field) {
            if ($this->db->fieldExists($field, 'presensi')) {
                $this->forge->dropColumn('presensi', $field);
            }
        }

        $this->db->dataCache['field_names'] = [];
    }
}
