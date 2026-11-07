<?php

namespace Tests;

use App\Database\Migrations\CreateHppUnit;
use App\Models\ModelHppUnit;
use App\Models\ModelKartuStok;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * HPP per unit: backfill dari stok awal terakhir, override manual admin hanya
 * untuk satu unit, dan fallback ke harga beli master saat hpp_unit belum ada.
 *
 * Catatan prefix: migration dan model memakai raw SQL dengan asumsi produksi
 * DBPrefix = '', jadi test menyetel prefix koneksi ke '' lalu mengembalikannya.
 */
class HppPerUnitTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->buatSkema();
        $this->sebarData();
    }

    protected function tearDown(): void
    {
        $this->hapusTabelAtauView('stok_barang');

        foreach (['hpp_unit', 'hpp_barang', 'stok_awal', 'barang', 'kategori'] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function hapusTabelAtauView(string $nama): void
    {
        $row = $this->db->query(
            "SELECT TABLE_TYPE FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [$nama]
        )->getRow();

        if ($row) {
            $this->db->query(($row->TABLE_TYPE === 'VIEW' ? 'DROP VIEW ' : 'DROP TABLE ') . $nama);
        }
    }

    private function buatSkema(): void
    {
        $this->hapusTabelAtauView('stok_barang');

        foreach (['hpp_unit', 'hpp_barang', 'stok_awal', 'barang', 'kategori'] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->query('CREATE TABLE barang (
            idbarang INT PRIMARY KEY,
            kode_barang VARCHAR(50),
            harga_beli DECIMAL(15,2),
            idkategori INT,
            deleted VARCHAR(5) DEFAULT \'0\')');
        $this->db->query('CREATE TABLE kategori (id INT PRIMARY KEY, `delete` VARCHAR(5) DEFAULT \'0\')');
        $this->db->query('CREATE TABLE stok_awal (
            idstok_awal INT AUTO_INCREMENT PRIMARY KEY,
            barang_idbarang INT,
            unit_idunit INT,
            harga_beli DECIMAL(15,2),
            tanggal DATE)');
        // Ringkasan hanya butuh kolom ini; di produksi stok_barang adalah view.
        $this->db->query('CREATE TABLE stok_barang (
            idbarang INT, id_unit INT, kode_barang VARCHAR(50), stok_akhir DECIMAL(15,2))');
        $this->db->query('CREATE TABLE hpp_barang (idbarang INT PRIMARY KEY, hpp DECIMAL(15,2))');
    }

    private function sebarData(): void
    {
        $this->db->query("INSERT INTO barang VALUES
            (1, 'A-1', 100, 1, '0'),
            (2, 'B-1', 100, 1, '0'),
            (3, 'C-1', 80, 1, '0')");
        $this->db->query("INSERT INTO kategori VALUES (1, '0')");

        // Unit 1 punya dua harga; yang terakhir (150) yang dipakai.
        // Unit 2 baris terakhir 0, harus jatuh ke harga positif sebelumnya (120).
        $this->db->query('INSERT INTO stok_awal (barang_idbarang, unit_idunit, harga_beli, tanggal) VALUES
            (1, 1, 100, "2026-01-01"),
            (1, 1, 150, "2026-02-01"),
            (1, 2, 120, "2026-01-01"),
            (1, 2, 0, "2026-02-01")');

        // Produk 2 punya HPP global (`hpp_barang`) tetapi tidak punya stok_awal.
        // Produk 3 tidak punya keduanya, jadi memakai harga beli master.
        $this->db->query("INSERT INTO hpp_barang VALUES (1, 700), (2, 500)");
        $this->db->query("INSERT INTO stok_barang VALUES
            (1, 1, 'A-1', 10),
            (1, 2, 'A-1', 5),
            (2, 2, 'B-1', 5)");
    }

    private function migrasi(): CreateHppUnit
    {
        require_once APPPATH . 'Database/Migrations/2026-11-07-000100_CreateHppUnit.php';

        return new CreateHppUnit(Database::forge('tests'));
    }

    public function testBackfillMengambilHargaTerakhirYangPositifPerUnit(): void
    {
        $this->migrasi()->up();

        $model = new ModelHppUnit();
        $this->assertSame(150.0, (float) $model->getFor(1, 1)->hpp);
        $this->assertSame(120.0, (float) $model->getFor(1, 2)->hpp, 'harga 0 tidak boleh menimpa harga valid');
        $this->assertSame('stok_awal', $model->getFor(1, 2)->sumber);
    }

    public function testBackfillIdempoten(): void
    {
        $migrasi = $this->migrasi();
        $migrasi->up();
        $migrasi->up();

        $this->assertSame(2, $this->db->table('hpp_unit')->countAllResults());
    }

    public function testAdminMengubahHppHanyaUntukSatuUnit(): void
    {
        $this->migrasi()->up();
        $model = new ModelHppUnit();

        $this->assertTrue($model->setManual(1, 1, 200.0, 99));

        $this->assertSame(200.0, (float) $model->getFor(1, 1)->hpp);
        $this->assertSame('manual', $model->getFor(1, 1)->sumber);
        $this->assertSame(120.0, (float) $model->getFor(1, 2)->hpp, 'unit lain tidak boleh berubah');
    }

    public function testSyncStokAwalTidakMenimpaOverrideManual(): void
    {
        $this->migrasi()->up();
        $model = new ModelHppUnit();
        $model->setManual(1, 1, 200.0, 99);

        $model->syncFromStokAwal(1, 1, 300.0);
        $this->assertSame(200.0, (float) $model->getFor(1, 1)->hpp, 'override manual harus dipertahankan');

        $model->syncFromStokAwal(1, 2, 130.0);
        $this->assertSame(130.0, (float) $model->getFor(1, 2)->hpp);
        $this->assertSame('stok_awal', $model->getFor(1, 2)->sumber);
    }

    public function testResolverMemakaiHppUnitLaluFallbackGlobalDanMaster(): void
    {
        $this->migrasi()->up();
        $model = new ModelHppUnit();

        // Produk 1 punya hpp_unit per unit, jadi menang atas hpp_barang (700).
        $this->assertSame(150.0, $model->resolve(1, 1));
        $this->assertSame(120.0, $model->resolve(1, 2));
        // Produk 2 hanya punya hpp_barang.
        $this->assertSame(500.0, $model->resolve(2, 2));
        // Produk 3 tidak punya hpp_unit maupun hpp_barang.
        $this->assertSame(80.0, $model->resolve(3, 2));

        $this->assertSame(
            [1 => 150.0, 2 => 500.0, 3 => 80.0],
            $model->resolveMany([1, 2, 3], 1)
        );
    }

    public function testRingkasanNilaiStokMemakaiHppPerUnitDanFallbackGlobal(): void
    {
        $this->migrasi()->up();
        (new ModelHppUnit())->setManual(1, 1, 200.0, 99);

        $summary = (new ModelKartuStok())->getSummaryKartuStok();

        // Unit 1 produk 1: 10 x 200 (manual). Unit 2 produk 1: 5 x 120 (stok awal).
        // Produk 2 tanpa hpp_unit: 5 x 500 (fallback hpp_barang).
        $this->assertSame(5100.0, (float) $summary->nilai_stok);
    }
}
