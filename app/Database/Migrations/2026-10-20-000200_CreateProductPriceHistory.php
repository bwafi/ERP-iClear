<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class CreateProductPriceHistory extends Migration
{
    private const MENU_ID = 10152;
    private const PRODUCT_MENU_ID = 6;
    private const ROUTE = 'produk/kartu-harga';

    public function up()
    {
        $productMenu = $this->db->table('menu')->where('idmenu', self::PRODUCT_MENU_ID)->get()->getRow();
        if (! $productMenu) {
            throw new RuntimeException('Menu Master Barang/Jasa tidak ditemukan; Kartu Harga tidak didaftarkan.');
        }
        $byId = $this->db->table('menu')->where('idmenu', self::MENU_ID)->get()->getRow();
        $byRoute = $this->db->table('menu')->where('url', self::ROUTE)->get()->getRow();
        if ($byId && ((string) $byId->url !== self::ROUTE || (string) $byId->nama_menu !== 'Kartu Harga')) {
            throw new RuntimeException('ID menu Kartu Harga telah digunakan oleh fitur lain.');
        }
        if ($byRoute && (int) $byRoute->idmenu !== self::MENU_ID) {
            throw new RuntimeException('Rute Kartu Harga telah digunakan oleh fitur lain.');
        }

        if (! $this->columnExists('barang', 'garansi_hari_default')) {
            $this->forge->addColumn('barang', [
                'garansi_hari_default' => [
                    'type' => 'INT',
                    'constraint' => 5,
                    'unsigned' => true,
                    'null' => true,
                    'default' => null,
                ],
            ]);
        }

        if (! $this->db->tableExists('barang_harga_history')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'barang_idbarang' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'harga_sebelum' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'harga_sesudah' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'garansi_sebelum' => ['type' => 'INT', 'constraint' => 5, 'unsigned' => true, 'null' => true],
                'garansi_sesudah' => ['type' => 'INT', 'constraint' => 5, 'unsigned' => true, 'null' => true],
                'petugas_idakun' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'berlaku_mulai' => ['type' => 'DATETIME'],
                'created_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['barang_idbarang', 'created_at']);
            $this->forge->createTable('barang_harga_history', true);
        }

        if (! $byId) {
            $this->db->table('menu')->insert([
                'idmenu' => self::MENU_ID,
                'urutan' => 1010,
                'nama_menu' => 'Kartu Harga',
                'roles' => self::ROUTE,
                'url' => self::ROUTE,
                'show_menu' => 1,
                'sub' => 0,
                'parent' => self::PRODUCT_MENU_ID,
                'utama' => 0,
                'categories' => 0,
                'icon' => '',
                'manualbook' => '',
            ]);
        }

        foreach ($this->db->table('jabatan')->select('ID_JABATAN, ROLES_JABATAN')->get()->getResult() as $jabatan) {
            $roles = json_decode((string) $jabatan->ROLES_JABATAN, true);
            if (! is_array($roles) || ! in_array(self::PRODUCT_MENU_ID, array_map('intval', $roles), true)) {
                continue;
            }
            $roles = array_values(array_unique(array_merge(array_map('intval', $roles), [self::MENU_ID])));
            $this->db->table('jabatan')->where('ID_JABATAN', $jabatan->ID_JABATAN)
                ->update(['ROLES_JABATAN' => json_encode($roles)]);
        }
    }

    public function down()
    {
        $historyHasRows = $this->db->tableExists('barang_harga_history')
            && $this->db->table('barang_harga_history')->countAllResults() > 0;
        $hasConfiguredWarranty = $this->columnExists('barang', 'garansi_hari_default')
            && $this->db->table('barang')->where('garansi_hari_default IS NOT NULL', null, false)->countAllResults() > 0;
        if ($historyHasRows || $hasConfiguredWarranty) {
            throw new RuntimeException('Rollback ditolak: histori harga atau pengaturan garansi sudah digunakan.');
        }

        foreach ($this->db->table('jabatan')->select('ID_JABATAN, ROLES_JABATAN')->get()->getResult() as $jabatan) {
            $roles = json_decode((string) $jabatan->ROLES_JABATAN, true);
            if (! is_array($roles)) {
                continue;
            }
            $roles = array_values(array_filter($roles, static fn ($id): bool => (int) $id !== self::MENU_ID));
            $this->db->table('jabatan')->where('ID_JABATAN', $jabatan->ID_JABATAN)
                ->update(['ROLES_JABATAN' => json_encode($roles)]);
        }
        $this->db->table('menu')->where('idmenu', self::MENU_ID)->where('url', self::ROUTE)->delete();
        $this->forge->dropTable('barang_harga_history', true);
        if ($this->columnExists('barang', 'garansi_hari_default')) {
            $this->forge->dropColumn('barang', 'garansi_hari_default');
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
