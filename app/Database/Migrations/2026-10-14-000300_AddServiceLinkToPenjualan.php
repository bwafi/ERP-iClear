<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Link service-created sparepart invoices back to their service ticket. */
class AddServiceLinkToPenjualan extends Migration
{
    private const INDEX_SERVICE = 'idx_penjualan_service_idservice';

    public function up()
    {
        if (! $this->db->fieldExists('service_idservice', 'penjualan')) {
            $this->forge->addColumn('penjualan', [
                'service_idservice' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                    'default'    => null,
                ],
            ]);
        }

        $table = $this->db->prefixTable('penjualan');
        if (! $this->hasServiceIndex($table)) {
            $this->db->query('CREATE INDEX ' . self::INDEX_SERVICE . ' ON ' . $table . ' (service_idservice)');
        }
    }

    public function down()
    {
        $table = $this->db->prefixTable('penjualan');
        if ($this->hasServiceIndex($table)) {
            $this->db->query('DROP INDEX ' . self::INDEX_SERVICE . ' ON ' . $table);
        }

        if ($this->db->fieldExists('service_idservice', 'penjualan')) {
            $this->forge->dropColumn('penjualan', 'service_idservice');
        }
    }

    private function hasServiceIndex(string $table): bool
    {
        foreach ($this->db->query('SHOW INDEX FROM ' . $table)->getResultArray() as $index) {
            if (($index['Key_name'] ?? '') === self::INDEX_SERVICE) {
                return true;
            }
        }

        return false;
    }
}
