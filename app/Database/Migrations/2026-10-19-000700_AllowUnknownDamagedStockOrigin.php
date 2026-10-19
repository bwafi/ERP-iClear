<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AllowUnknownDamagedStockOrigin extends Migration
{
    public function up()
    {
        $this->changeNullability(true);
    }

    public function down()
    {
        if ($this->db->table('barang_rusak')->where('idpembelian', null)
            ->orWhere('no_nota_sup', null)->countAllResults() > 0) {
            throw new \RuntimeException('Barang rusak dengan asal belum diketahui masih ada; rollback ditolak.');
        }
        $this->changeNullability(false);
    }

    private function changeNullability(bool $nullable): void
    {
        foreach (['idpembelian', 'no_nota_sup'] as $column) {
            $definition = $this->db->query(
                'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() '
                . 'AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$this->db->prefixTable('barang_rusak'), $column]
            )->getRow();
            $type = (string) ($definition->COLUMN_TYPE ?? '');
            if (! preg_match('/^(?:tinyint|smallint|mediumint|int|bigint|varchar|char)\([0-9]+\)(?: unsigned)?$/D', $type)) {
                throw new \RuntimeException('Tipe kolom asal barang rusak tidak dikenali; migrasi dibatalkan.');
            }
            $this->db->query('ALTER TABLE ' . $this->db->prefixTable('barang_rusak')
                . ' MODIFY ' . $column . ' ' . $type . ($nullable ? ' NULL' : ' NOT NULL'));
        }
    }
}
