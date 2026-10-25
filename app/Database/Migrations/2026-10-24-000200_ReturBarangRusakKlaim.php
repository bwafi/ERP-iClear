<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Retur sparepart rusak hasil klaim garansi.
 *
 * Loss omset disimpan di service_sparepart supaya laporan bisa memakai
 * tanggal_hpp_klaim, bukan tanggal service.
 */
class ReturBarangRusakKlaim extends Migration
{
    private const MENU_ID = 10160;

    private array $menuDef = [
        'idmenu'     => self::MENU_ID,
        'urutan'     => 26,
        'nama_menu'  => 'Retur Barang Rusak',
        'roles'      => 'retur_barang_rusak',
        'url'        => 'retur_barang_rusak',
        'show_menu'  => 1,
        'sub'        => 0,
        'parent'     => 0,
        'utama'      => 1,
        'categories' => 1,
        'icon'       => '<iconify-icon icon="solar:card-send-bold" width="24" height="24"></iconify-icon>',
        'manualbook' => 'Retur Barang Rusak.pdf',
    ];

    public function up()
    {
        $this->tambahKolom('service_sparepart', 'status_retur', "VARCHAR(20) NULL DEFAULT NULL AFTER hpp_penjualan_garansi");
        $this->tambahKolom('service_sparepart', 'hpp_omset_garansi', "INT NOT NULL DEFAULT 0 AFTER status_retur");
        $this->tambahKolom('service_sparepart', 'tanggal_hpp_klaim', "DATE NULL DEFAULT NULL AFTER hpp_omset_garansi");

        $sparepart = $this->db->prefixTable('service_sparepart');
        $this->db->query("
            UPDATE {$sparepart}
            SET status_retur = 'bisa',
                hpp_omset_garansi = 0,
                tanggal_hpp_klaim = NULL
            WHERE jumlah_tambahan_garansi > 0
              AND (status_retur IS NULL OR status_retur = '')
        ");

        $header = $this->db->prefixTable('retur_klaim');
        $this->db->query("CREATE TABLE IF NOT EXISTS {$header} (
            idretur_klaim INT(11) NOT NULL AUTO_INCREMENT,
            no_retur VARCHAR(45) NOT NULL,
            tanggal DATE NOT NULL,
            suplier_id_suplier INT(11) NOT NULL,
            unit_idunit INT(11) NOT NULL,
            input_by INT(11) NULL,
            catatan TEXT NULL,
            created_at DATETIME NULL,
            PRIMARY KEY (idretur_klaim),
            UNIQUE KEY uq_retur_klaim_no (no_retur),
            KEY idx_retur_klaim_tanggal (tanggal),
            KEY idx_retur_klaim_supplier (suplier_id_suplier),
            KEY idx_retur_klaim_unit (unit_idunit)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $item = $this->db->prefixTable('retur_klaim_item');
        $this->db->query("CREATE TABLE IF NOT EXISTS {$item} (
            idretur_klaim_item INT(11) NOT NULL AUTO_INCREMENT,
            retur_klaim_idretur_klaim INT(11) NOT NULL,
            service_sparepart_idservice_sparepart INT(11) NOT NULL,
            barang_rusak_claim_idbarang_rusak_claim INT(11) NULL,
            barang_idbarang INT(11) NOT NULL,
            jumlah DECIMAL(15,2) NOT NULL,
            hpp_manual INT(11) NOT NULL,
            hasil VARCHAR(30) NOT NULL,
            barang_pengganti_idbarang INT(11) NULL,
            jumlah_pengganti DECIMAL(15,2) NULL,
            nilai INT(11) NOT NULL DEFAULT 0,
            keterangan TEXT NULL,
            created_at DATETIME NULL,
            PRIMARY KEY (idretur_klaim_item),
            KEY idx_retur_klaim_item_header (retur_klaim_idretur_klaim),
            UNIQUE KEY uq_retur_klaim_item_sparepart (service_sparepart_idservice_sparepart)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->tambahMenu();
    }

    public function down()
    {
        $this->hapusMenu();

        $this->db->query('DROP TABLE IF EXISTS ' . $this->db->prefixTable('retur_klaim_item'));
        $this->db->query('DROP TABLE IF EXISTS ' . $this->db->prefixTable('retur_klaim'));

        $this->hapusKolom('service_sparepart', 'tanggal_hpp_klaim');
        $this->hapusKolom('service_sparepart', 'hpp_omset_garansi');
        $this->hapusKolom('service_sparepart', 'status_retur');
    }

    private function tambahKolom(string $table, string $column, string $definition): void
    {
        $prefixed = $this->db->prefixTable($table);
        $row = $this->db->query(
            'SELECT COUNT(*) AS n FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$prefixed, $column]
        )->getRow();

        if ((int) ($row->n ?? 0) === 0) {
            $this->db->query("ALTER TABLE {$prefixed} ADD COLUMN {$column} {$definition}");
        }
    }

    private function hapusKolom(string $table, string $column): void
    {
        $prefixed = $this->db->prefixTable($table);
        $row = $this->db->query(
            'SELECT COUNT(*) AS n FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$prefixed, $column]
        )->getRow();

        if ((int) ($row->n ?? 0) > 0) {
            $this->db->query("ALTER TABLE {$prefixed} DROP COLUMN {$column}");
        }
    }

    private function tambahMenu(): void
    {
        if (! $this->db->tableExists('menu')) {
            return;
        }

        $exists = $this->db->table('menu')->where('idmenu', self::MENU_ID)->countAllResults();
        if ($exists === 0) {
            $this->db->table('menu')->insert($this->menuDef);
        }

        if (! $this->db->tableExists('jabatan')) {
            return;
        }

        $targets = [];
        foreach ($this->db->table('jabatan')->get()->getResult() as $row) {
            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (is_array($roles) && in_array(23, array_map('intval', $roles), true)) {
                $targets[] = (int) $row->ID_JABATAN;
            }
        }

        if ($targets === []) {
            $targets = [1];
        }

        foreach ($targets as $jabatanId) {
            $row = $this->db->table('jabatan')->where('ID_JABATAN', $jabatanId)->get()->getRow();
            if (! $row) {
                continue;
            }

            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (! is_array($roles)) {
                $roles = [];
            }

            $merged = array_values(array_unique(array_merge($roles, [self::MENU_ID])));
            if ($merged !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jabatanId)
                    ->update(['ROLES_JABATAN' => json_encode($merged)]);
            }
        }
    }

    private function hapusMenu(): void
    {
        if (! $this->db->tableExists('menu')) {
            return;
        }

        $this->db->table('menu')->where('idmenu', self::MENU_ID)->delete();

        if (! $this->db->tableExists('jabatan')) {
            return;
        }

        foreach ($this->db->table('jabatan')->get()->getResult() as $row) {
            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (! is_array($roles)) {
                continue;
            }

            $filtered = array_values(array_filter(
                $roles,
                static fn ($id) => (int) $id !== self::MENU_ID
            ));

            if ($filtered !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $row->ID_JABATAN)
                    ->update(['ROLES_JABATAN' => json_encode($filtered)]);
            }
        }
    }
}
