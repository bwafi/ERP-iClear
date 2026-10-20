<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UseTransactionalStockSources extends Migration
{
    private const TABLES = ['detail_mutasi', 'detail_pembelian', 'stok_awal'];

    public function up()
    {
        foreach (self::TABLES as $table) {
            $name = $this->db->prefixTable($table);
            $metadata = $this->db->query('SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$name])->getRow();
            if (! $metadata || $metadata->ENGINE === 'InnoDB') {
                continue;
            }
            if ($metadata->ENGINE !== 'MyISAM') {
                throw new \RuntimeException('Engine sumber stok tidak dikenali; migrasi dihentikan untuk pemeriksaan.');
            }
            $before = $this->digest($table);
            $this->db->query('ALTER TABLE ' . $this->db->protectIdentifiers($name) . ' ENGINE=InnoDB');
            if ($this->digest($table) !== $before) {
                throw new \RuntimeException('Isi sumber stok berubah selama konversi engine; periksa backup sebelum melanjutkan.');
            }
        }
    }

    public function down()
    {
        throw new \RuntimeException('Engine stok tidak dikembalikan ke nontransaksional. Rollback aplikasi tanpa menghapus data/histori.');
    }

    private function digest(string $table): string
    {
        $rows = $this->db->table($table)->get()->getResultArray();
        $encoded = array_map(static fn ($row) => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $rows);
        sort($encoded, SORT_STRING);
        return hash('sha256', implode("\n", $encoded));
    }
}
