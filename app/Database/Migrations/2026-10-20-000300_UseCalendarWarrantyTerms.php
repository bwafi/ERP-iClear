<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class UseCalendarWarrantyTerms extends Migration
{
    public function up()
    {
        $this->addNullableVarchar('barang', 'garansi_opsi_default', 32);
        $this->addNullableVarchar('barang_harga_history', 'garansi_opsi_sebelum', 32);
        $this->addNullableVarchar('barang_harga_history', 'garansi_opsi_sesudah', 32);
        $this->addNullableVarchar('service', 'garansi_opsi_snapshot', 32);
        $this->addNullableDate('service', 'garansi_mulai');
        $this->addNullableDate('service', 'garansi_akhir');
    }

    public function down()
    {
        foreach ([
            ['barang', 'garansi_opsi_default'],
            ['barang_harga_history', 'garansi_opsi_sebelum'],
            ['barang_harga_history', 'garansi_opsi_sesudah'],
            ['service', 'garansi_opsi_snapshot'],
            ['service', 'garansi_mulai'],
            ['service', 'garansi_akhir'],
        ] as [$table, $field]) {
            if (! $this->columnExists($table, $field)) {
                continue;
            }
            $used = $this->db->table($table)->where($field . ' IS NOT NULL', null, false)->countAllResults();
            if ($used > 0) {
                throw new RuntimeException('Rollback ditolak: snapshot garansi kalender sudah digunakan.');
            }
        }

        foreach ([
            ['service', 'garansi_akhir'],
            ['service', 'garansi_mulai'],
            ['service', 'garansi_opsi_snapshot'],
            ['barang_harga_history', 'garansi_opsi_sesudah'],
            ['barang_harga_history', 'garansi_opsi_sebelum'],
            ['barang', 'garansi_opsi_default'],
        ] as [$table, $field]) {
            if ($this->columnExists($table, $field)) {
                $this->forge->dropColumn($table, $field);
            }
        }
    }

    private function addNullableVarchar(string $table, string $field, int $length): void
    {
        if ($this->db->tableExists($this->db->getPrefix() . $table, false) && ! $this->columnExists($table, $field)) {
            $this->forge->addColumn($table, [$field => [
                'type' => 'VARCHAR', 'constraint' => $length, 'null' => true, 'default' => null,
            ]]);
        }
    }

    private function addNullableDate(string $table, string $field): void
    {
        if ($this->db->tableExists($this->db->getPrefix() . $table, false) && ! $this->columnExists($table, $field)) {
            $this->forge->addColumn($table, [$field => [
                'type' => 'DATE', 'null' => true, 'default' => null,
            ]]);
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $tableName = $this->db->prefixTable($table);
        return $this->db->query(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$tableName, $column]
        )->getNumRows() > 0;
    }
}
