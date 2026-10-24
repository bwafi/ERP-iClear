<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Migration retur barang rusak harus idempoten dan tidak mengubah klaim lama.
 */
final class ReturBarangRusakMigrationTest extends CIUnitTestCase
{
    private const TABEL = [
        'db_retur_klaim_item',
        'db_retur_klaim',
        'db_service_sparepart',
        'db_menu',
        'db_jabatan',
    ];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect('tests', false);
        foreach (self::TABEL as $tabel) {
            $this->db->query("DROP TABLE IF EXISTS {$tabel}");
        }

        $this->db->query('CREATE TABLE db_service_sparepart (
            idservice_sparepart INT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT NULL,
            barang_idbarang INT NULL,
            jumlah_tambahan_garansi INT NULL DEFAULT 0,
            hpp_penjualan_garansi INT NULL DEFAULT 0,
            PRIMARY KEY (idservice_sparepart)
        ) ENGINE=InnoDB');

        $this->db->table('service_sparepart')->insert([
            'idservice_sparepart' => 1,
            'service_idservice' => 10,
            'barang_idbarang' => 20,
            'jumlah_tambahan_garansi' => 2,
            'hpp_penjualan_garansi' => 4000,
        ]);

        $this->db->query('CREATE TABLE db_menu (
            idmenu INT NOT NULL,
            urutan INT NOT NULL,
            nama_menu TEXT NULL,
            roles TEXT NULL,
            url TEXT NULL,
            show_menu INT NULL DEFAULT 1,
            sub INT NULL,
            parent INT NULL,
            utama INT NULL,
            categories INT NULL,
            icon TEXT NULL,
            manualbook TEXT NULL,
            PRIMARY KEY (idmenu)
        ) ENGINE=InnoDB');

        $this->db->query('CREATE TABLE db_jabatan (
            ID_JABATAN INT NOT NULL,
            ROLES_JABATAN TEXT NULL,
            PRIMARY KEY (ID_JABATAN)
        ) ENGINE=InnoDB');

        $this->db->table('menu')->insert([
            'idmenu' => 23,
            'urutan' => 23,
            'nama_menu' => 'Retur Supplier',
            'roles' => 'retur_suplier',
            'url' => 'retur_suplier',
            'show_menu' => 1,
            'sub' => 0,
            'parent' => 0,
            'utama' => 1,
            'categories' => 1,
            'icon' => '',
            'manualbook' => '',
        ]);

        $this->db->table('jabatan')->insert([
            'ID_JABATAN' => 1,
            'ROLES_JABATAN' => json_encode([23]),
        ]);
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $tabel) {
            $this->db->query("DROP TABLE IF EXISTS {$tabel}");
        }

        parent::tearDown();
    }

    public function testMigrationIdempotenDanTidakMengubahKlaimLama(): void
    {
        $class = $this->migration();
        (new $class())->up();
        (new $class())->up();

        $this->assertTrue($this->db->fieldExists('status_retur', 'service_sparepart'));
        $this->assertTrue($this->db->fieldExists('hpp_omset_garansi', 'service_sparepart'));
        $this->assertTrue($this->db->fieldExists('tanggal_hpp_klaim', 'service_sparepart'));
        $this->assertTrue($this->db->tableExists('retur_klaim'));
        $this->assertTrue($this->db->tableExists('retur_klaim_item'));

        $row = $this->db->table('service_sparepart')->where('idservice_sparepart', 1)->get()->getRow();
        $this->assertSame('bisa', $row->status_retur);
        $this->assertSame(0, (int) $row->hpp_omset_garansi);
        $this->assertNull($row->tanggal_hpp_klaim);
        $this->assertSame(4000, (int) $row->hpp_penjualan_garansi);

        $menu = $this->db->table('menu')->where('url', 'retur_barang_rusak')->get()->getResultArray();
        $this->assertCount(1, $menu);

        $jabatan = $this->db->table('jabatan')->where('ID_JABATAN', 1)->get()->getRow();
        $roles = json_decode($jabatan->ROLES_JABATAN, true);
        $this->assertContains(10160, array_map('intval', $roles));
    }

    public function testDownMenghapusSkemaDanMenu(): void
    {
        $class = $this->migration();
        (new $class())->up();
        (new $class())->down();

        $this->db->resetDataCache();

        $this->assertFalse($this->db->tableExists('retur_klaim'));
        $this->assertFalse($this->db->tableExists('retur_klaim_item'));
        $this->assertFalse($this->db->fieldExists('status_retur', 'service_sparepart'));
        $this->assertSame(0, $this->db->table('menu')->where('url', 'retur_barang_rusak')->countAllResults());
    }

    private function migration(): string
    {
        $class = 'App\\Database\\Migrations\\ReturBarangRusakKlaim';
        if (! class_exists($class, false)) {
            require_once APPPATH . 'Database/Migrations/2026-10-24-000200_ReturBarangRusakKlaim.php';
        }

        return $class;
    }
}
