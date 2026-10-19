<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AllowUnknownDamagedStockOrigin extends Migration
{
    public function up()
    {
        $this->allowUnknownOrigin();
    }

    public function down()
    {
        throw new \RuntimeException('Nullability lama tidak dapat dipastikan; pulihkan schema TEST dari snapshot pra-deploy.');
    }

    private function allowUnknownOrigin(): void
    {
        foreach (['idpembelian', 'no_nota_sup'] as $column) {
            $definition = $this->db->query(
                'SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() '
                . 'AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$this->db->prefixTable('barang_rusak'), $column]
            )->getRow();
            $type = (string) ($definition->COLUMN_TYPE ?? '');
            if (! preg_match('/^(?:tinyint|smallint|mediumint|int|bigint|varchar|char)\([0-9]+\)(?: unsigned)?$/D', $type)) {
                throw new \RuntimeException('Tipe kolom asal barang rusak tidak dikenali; migrasi dibatalkan.');
            }
            if (($definition->IS_NULLABLE ?? '') === 'YES') {
                continue;
            }
            $this->db->query('ALTER TABLE ' . $this->db->prefixTable('barang_rusak')
                . ' MODIFY ' . $column . ' ' . $type . ' NULL');
        }
    }
}
