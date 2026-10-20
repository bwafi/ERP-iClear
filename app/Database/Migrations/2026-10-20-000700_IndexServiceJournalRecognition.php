<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class IndexServiceJournalRecognition extends Migration
{
    public function up()
    {
        $table = $this->db->prefixTable('jurnal');
        $indexes = $this->db->query('SHOW INDEX FROM ' . $this->db->protectIdentifiers($table))->getResultArray();
        $columns = [];
        foreach ($indexes as $index) {
            $columns[$index['Key_name']][(int) $index['Seq_in_index']] = $index['Column_name'];
        }
        foreach ($columns as $name => $indexed) {
            ksort($indexed);
            if (array_slice(array_values($indexed), 0, 3) === ['tabel_referensi', 'id_referensi', 'tanggal']) {
                return;
            }
            if ($name === 'idx_jurnal_source_date') {
                throw new \RuntimeException('Indeks idx_jurnal_source_date sudah ada dengan kolom berbeda.');
            }
        }
        $this->db->query('ALTER TABLE ' . $this->db->protectIdentifiers($table)
            . ' ADD INDEX idx_jurnal_source_date (tabel_referensi, id_referensi, tanggal)');
    }

    public function down()
    {
        throw new \RuntimeException('Rollback aplikasi tidak memerlukan penghapusan indeks jurnal.');
    }
}
