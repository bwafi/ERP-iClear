<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWarrantyClaimIntake extends Migration
{
    public function up()
    {
        foreach ([
            'original_service_sparepart_id' => ['type' => 'INT', 'null' => true],
            'original_barang_id' => ['type' => 'INT', 'null' => true],
            'original_purchase_detail_id' => ['type' => 'INT', 'null' => true],
            'original_quantity' => ['type' => 'INT', 'null' => true],
            'claim_reason' => ['type' => 'TEXT', 'null' => true],
        ] as $field => $definition) {
            if (! $this->hasColumn($field)) {
                $this->forge->addColumn('riwayat_claim_garansi', [$field => $definition]);
            }
        }
    }

    public function down()
    {
        if ($this->hasColumn('original_service_sparepart_id')
            && $this->db->table('riwayat_claim_garansi')
                ->where('original_service_sparepart_id IS NOT NULL', null, false)->countAllResults() > 0) {
            throw new \RuntimeException('Rollback schema ditolak: rincian klaim sudah digunakan. Rollback aplikasi tanpa menghapus histori.');
        }
        foreach (['claim_reason', 'original_quantity', 'original_purchase_detail_id', 'original_barang_id', 'original_service_sparepart_id'] as $field) {
            if ($this->hasColumn($field)) {
                $this->forge->dropColumn('riwayat_claim_garansi', $field);
            }
        }
    }

    private function hasColumn(string $column): bool
    {
        return $this->db->query('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->db->prefixTable('riwayat_claim_garansi'), $column])->getNumRows() > 0;
    }
}
