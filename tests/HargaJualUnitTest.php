<?php

namespace Tests;

use App\Database\Migrations\CreateHargaJualUnit;
use App\Models\ModelHargaJualUnit;
use App\Models\ModelHppUnit;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Harga jual per produk+unit: override hanya untuk unit yang dipilih,
 * fallback ke `barang.harga`, dan tidak menyentuh HPP.
 */
final class HargaJualUnitTest extends CIUnitTestCase
{
    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->buatSkema();
    }

    protected function tearDown(): void
    {
        $this->migrasi()->down();

        foreach (['hpp_unit', 'hpp_barang', 'barang'] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $this->migrasi()->down();

        foreach (['hpp_unit', 'hpp_barang', 'barang'] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->query('CREATE TABLE barang (
            idbarang INT PRIMARY KEY,
            kode_barang VARCHAR(50),
            harga DECIMAL(15,2) NULL DEFAULT 0,
            harga_beli DECIMAL(15,2) NULL DEFAULT 0)');
        $this->db->query('CREATE TABLE hpp_barang (idbarang INT PRIMARY KEY, hpp DECIMAL(15,2))');
        $this->db->query('CREATE TABLE hpp_unit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            barang_idbarang INT,
            unit_idunit INT,
            hpp DECIMAL(15,2) DEFAULT 0,
            sumber VARCHAR(20) DEFAULT \'manual\',
            updated_by INT NULL,
            updated_at DATETIME NULL)');

        $this->db->query("INSERT INTO barang VALUES (1, 'A-1', 150000, 100000)");
        $this->db->query('INSERT INTO hpp_barang VALUES (1, 90000)');

        $this->migrasi()->up();
    }

    private function migrasi(): CreateHargaJualUnit
    {
        $path = APPPATH . 'Database/Migrations/2026-11-10-000100_CreateHargaJualUnit.php';
        require_once $path;

        return new CreateHargaJualUnit(Database::forge('tests'));
    }

    public function testTanpaOverrideMemakaiHargaMaster(): void
    {
        $model = new ModelHargaJualUnit();

        $this->assertNull($model->hargaJualUnit(1, 1));
        $this->assertSame(150000.0, $model->resolve(1, 1));
    }

    public function testOverrideHanyaBerlakuUntukUnitItu(): void
    {
        (new ModelHargaJualUnit())->setHarga(1, 1, 175000.0, 43);

        $model = new ModelHargaJualUnit();
        $this->assertSame(175000.0, $model->resolve(1, 1));
        $this->assertSame(150000.0, $model->resolve(1, 2));
        $this->assertSame(150000.0, (float) $this->db->table('barang')->where('idbarang', 1)->get()->getRow()->harga);
    }

    public function testClearMenghapusBarisOverride(): void
    {
        $model = new ModelHargaJualUnit();
        $model->setHarga(1, 1, 175000.0, 43);
        $model->clear(1, 1);

        $this->assertNull($model->getFor(1, 1));
        $this->assertSame(150000.0, $model->resolve(1, 1));
    }

    public function testHargaJualTidakMengubahHpp(): void
    {
        (new ModelHargaJualUnit())->setHarga(1, 1, 175000.0, 43);

        $this->assertSame(90000.0, (new ModelHppUnit())->resolve(1, 1), 'HPP tetap dari hpp_barang');
    }

    public function testMigrationMembuatTabelDanUnikPerProdukUnit(): void
    {
        $this->assertTrue($this->db->tableExists('harga_jual_unit'));

        $this->db->query('INSERT INTO harga_jual_unit (barang_idbarang, unit_idunit, harga_jual) VALUES (1, 1, 175000)');

        $this->expectException(\Throwable::class);
        $this->db->query('INSERT INTO harga_jual_unit (barang_idbarang, unit_idunit, harga_jual) VALUES (1, 1, 180000)');
    }
}
