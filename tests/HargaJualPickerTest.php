<?php

namespace Tests;

use App\Models\ModelBarang;
use App\Models\ModelStokBarang;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Picker POS/Pembelian memakai harga jual efektif unit, bukan master global.
 */
final class HargaJualPickerTest extends CIUnitTestCase
{
    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    private string $prefixAsli = '';

    private const TABEL = ['barang', 'kategori', 'stok_barang', 'harga_jual_unit'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q = fn (string $sql) => $this->db->query($sql);

        $q('CREATE TABLE barang (
            idbarang INT PRIMARY KEY,
            kode_barang VARCHAR(50),
            nama_barang VARCHAR(100),
            harga DECIMAL(15,2) DEFAULT 0,
            harga_beli DECIMAL(15,2) DEFAULT 0,
            imei VARCHAR(50) NULL,
            jenis_hp VARCHAR(50) NULL,
            internal VARCHAR(10) NULL,
            warna VARCHAR(50) NULL,
            status_barang VARCHAR(20) NULL,
            status VARCHAR(20) NULL,
            status_ppn VARCHAR(5) NULL,
            input INT NULL,
            jenis_barang VARCHAR(10) DEFAULT \'produk\',
            idkategori INT NULL,
            deleted VARCHAR(2) DEFAULT \'0\')');
        $q('CREATE TABLE kategori (id INT PRIMARY KEY, nama_kategori VARCHAR(100) NULL, `delete` VARCHAR(2) DEFAULT \'0\')');
        $q('CREATE TABLE stok_barang (
            idbarang INT,
            id_unit INT,
            kode_barang VARCHAR(50),
            nama_barang VARCHAR(100),
            harga DECIMAL(15,2) DEFAULT 0,
            harga_beli DECIMAL(15,2) DEFAULT 0,
            imei VARCHAR(50) NULL,
            warna VARCHAR(50) NULL,
            stok_akhir DECIMAL(15,2) DEFAULT 0,
            stok_minimum DECIMAL(15,2) DEFAULT 0,
            aktif_unit INT DEFAULT 1)');
        $q('CREATE TABLE harga_jual_unit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            barang_idbarang INT,
            unit_idunit INT,
            harga_jual DECIMAL(15,2))');

        $q('INSERT INTO kategori VALUES (1, "Umum", "0")');
        $q('INSERT INTO barang (idbarang, kode_barang, nama_barang, harga, jenis_barang, idkategori, status_ppn) VALUES
            (1, "sprt001", "LCD Sparepart", 150000, "produk", 1, "1"),
            (2, "JASA-01", "PUP IPHONE", 250000, "jasa", 1, "1"),
            (3, "sprt002", "Baterai", 150000, "produk", 1, "1")');
        $q('INSERT INTO stok_barang (idbarang, id_unit, kode_barang, nama_barang, harga, stok_akhir, aktif_unit) VALUES
            (1, 1, "sprt001", "LCD Sparepart", 150000, 5, 1),
            (3, 1, "sprt002", "Baterai", 150000, 5, 1)');
        $q('INSERT INTO harga_jual_unit (barang_idbarang, unit_idunit, harga_jual) VALUES
            (1, 1, 175000),
            (2, 1, 300000)');

        session()->set(['ID_UNIT' => 1]);
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->dataCache = [];
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    public function testPosProdukMemakaiHargaUnit(): void
    {
        $baris = (new ModelStokBarang())->getAllBarang2();
        $peta = [];
        foreach ($baris as $row) {
            $peta[(int) $row->idbarang] = $row;
        }

        $this->assertSame(175000.0, (float) $peta[1]->harga_jual);
        $this->assertSame(150000.0, (float) $peta[1]->harga, 'harga master tetap');
    }

    public function testPosJasaMemakaiHargaUnit(): void
    {
        $peta = [];
        foreach ((new ModelStokBarang())->getJasa() as $row) {
            $peta[(int) $row->idbarang] = $row;
        }

        $this->assertSame(300000.0, (float) $peta[2]->harga_jual);
    }

    public function testPembelianPickerMemakaiHargaUnit(): void
    {
        $peta = [];
        foreach ((new ModelBarang())->getAllBarang() as $row) {
            $peta[(int) $row->idbarang] = $row;
        }

        $this->assertSame(175000.0, (float) $peta[1]->harga_jual);
    }

    public function testFallbackTanpaOverride(): void
    {
        $peta = [];
        foreach ((new ModelStokBarang())->getAllBarang2() as $row) {
            $peta[(int) $row->idbarang] = $row;
        }

        $this->assertSame(150000.0, (float) $peta[3]->harga_jual);
    }
}
