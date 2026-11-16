<?php

namespace Tests;

use App\Models\ModelKartuStok;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Filter server-side Kartu Stok: kategori dan kondisi stok akhir.
 * Filter harus konsisten antara daftar DataTables, hitungan baris, dan export.
 */
final class KartuStokFilterTest extends CIUnitTestCase
{
    private const TABEL = [
        'barang', 'stok_barang', 'kategori', 'harga_jual_unit',
        'hpp_unit', 'hpp_barang', 'detail_pembelian', 'pembelian',
        'suplier', 'stok_awal',
    ];

    protected $db;
    protected $shared;

    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect('tests', false);
        $this->shared = Database::connect();
        $this->prefixAsli = (string) $this->shared->DBPrefix;
        $this->db->setPrefix('');
        $this->shared->setPrefix('');
        $this->buatTabel();

        $this->db->table('kategori')->insertBatch([
            ['id' => 1, 'nama_kategori' => 'Sparepart', 'delete' => 0],
            ['id' => 2, 'nama_kategori' => 'Aksesoris', 'delete' => 0],
        ]);
        $this->db->table('barang')->insertBatch([
            [
                'idbarang' => 1, 'kode_barang' => 'P01', 'nama_barang' => 'LCD',
                'harga' => 150000, 'harga_beli' => 100000, 'status_ppn' => 1,
                'idkategori' => 1, 'deleted' => '0',
            ],
            [
                'idbarang' => 2, 'kode_barang' => 'A01', 'nama_barang' => 'Casing',
                'harga' => 50000, 'harga_beli' => 30000, 'status_ppn' => 0,
                'idkategori' => 2, 'deleted' => '0',
            ],
        ]);
        $this->db->table('stok_barang')->insertBatch([
            [
                'idbarang' => 1, 'id_unit' => 1, 'kode_barang' => 'P01',
                'nama_barang' => 'LCD', 'nama_unit' => 'Unit 1',
                'stok_akhir' => -5, 'aktif_unit' => 1,
            ],
            [
                'idbarang' => 2, 'id_unit' => 1, 'kode_barang' => 'A01',
                'nama_barang' => 'Casing', 'nama_unit' => 'Unit 1',
                'stok_akhir' => 0, 'aktif_unit' => 1,
            ],
            [
                'idbarang' => 2, 'id_unit' => 2, 'kode_barang' => 'A01',
                'nama_barang' => 'Casing', 'nama_unit' => 'Unit 2',
                'stok_akhir' => 10, 'aktif_unit' => 1,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->setPrefix($this->prefixAsli);
        $this->shared->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatTabel(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        foreach (self::TABEL as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE barang (idbarang INT PRIMARY KEY AUTO_INCREMENT, kode_barang VARCHAR(50) NULL,
                nama_barang VARCHAR(100) NULL, imei VARCHAR(50) NULL, harga INT NULL, harga_beli INT NULL,
                jenis_hp VARCHAR(50) NULL, warna VARCHAR(50) NULL, status_ppn INT NULL, idkategori INT NULL,
                deleted VARCHAR(2) NULL)');
        $q('CREATE TABLE stok_barang (idbarang INT NULL, id_unit INT NULL, kode_barang VARCHAR(50) NULL,
                nama_barang VARCHAR(100) NULL, imei VARCHAR(50) NULL, nama_unit VARCHAR(100) NULL,
                harga INT NULL, stok_awal INT NULL DEFAULT 0, stok_akhir INT NULL DEFAULT 0,
                total_pembelian INT NULL DEFAULT 0, total_penjualan INT NULL DEFAULT 0,
                total_retur_pelanggan INT NULL DEFAULT 0, total_retur_supplier INT NULL DEFAULT 0,
                total_mutasi_masuk INT NULL DEFAULT 0, total_mutasi_keluar INT NULL DEFAULT 0,
                stok_minimum INT NULL DEFAULT 0, aktif_unit INT NULL DEFAULT 1)');
        $q('CREATE TABLE kategori (id INT PRIMARY KEY, nama_kategori VARCHAR(100) NULL, `delete` INT NULL DEFAULT 0)');
        $q('CREATE TABLE harga_jual_unit (id INT PRIMARY KEY AUTO_INCREMENT,
                barang_idbarang INT NOT NULL, unit_idunit INT NOT NULL, harga_jual DECIMAL(15,2) NOT NULL,
                updated_by INT NULL, updated_at DATETIME NULL,
                UNIQUE KEY (barang_idbarang, unit_idunit))');
        $q('CREATE TABLE hpp_unit (barang_idbarang INT NULL, unit_idunit INT NULL, hpp DECIMAL(15,2) NULL,
                sumber VARCHAR(20) NULL, updated_by INT NULL, updated_at DATETIME NULL)');
        $q('CREATE TABLE hpp_barang (idbarang INT NULL, hpp DECIMAL(15,2) NULL)');
        $q('CREATE TABLE detail_pembelian (barang_idbarang INT NULL, unit_idunit INT NULL,
                tanggal DATE NULL, pembelian_idpembelian INT NULL)');
        $q('CREATE TABLE pembelian (idpembelian INT PRIMARY KEY, suplier_id_suplier INT NULL)');
        $q('CREATE TABLE suplier (id_suplier INT PRIMARY KEY, nama_suplier VARCHAR(100) NULL)');
        $q('CREATE TABLE stok_awal (idstok_awal INT PRIMARY KEY AUTO_INCREMENT,
                barang_idbarang INT NULL, unit_idunit INT NULL, suplier_id_suplier INT NULL, tanggal DATE NULL)');
    }

    /**
     * @return string[]
     */
    private function kodeBarang(array $rows): array
    {
        $kode = array_map(static fn ($row) => (string) $row->kode_barang, $rows);
        sort($kode);

        return $kode;
    }

    public function testFilterKategoriAksesorisHanyaBarangKategoriItu(): void
    {
        $model = new ModelKartuStok();

        $rows = $model->getKartuStokDT(50, 0, '', 'stok_barang.kode_barang', 'ASC', '', '', '2');
        $this->assertSame(['A01', 'A01'], $this->kodeBarang($rows));
        $this->assertSame(2, (int) $model->countKartuStokDT('', '', '', '2'));

        $rows = $model->getKartuStokDT(50, 0, '', 'stok_barang.kode_barang', 'ASC', '', '', '1');
        $this->assertSame(['P01'], $this->kodeBarang($rows));
    }

    public function testFilterStokMinusHanyaBarisNegatif(): void
    {
        $model = new ModelKartuStok();

        $rows = $model->getKartuStokDT(50, 0, '', 'stok_barang.kode_barang', 'ASC', '', '', '', 'minus');

        $this->assertSame(['P01'], $this->kodeBarang($rows));
        $this->assertSame(-5.0, (float) $rows[0]->stok_akhir);
        $this->assertSame(1, (int) $model->countKartuStokDT('', '', '', '', 'minus'));
    }

    public function testFilterStokNonPositifMencakupMinusDanNol(): void
    {
        $model = new ModelKartuStok();

        $rows = $model->getKartuStokDT(50, 0, '', 'stok_barang.kode_barang', 'ASC', '', '', '', 'non_positif');

        $this->assertSame(['A01', 'P01'], $this->kodeBarang($rows));
        $this->assertSame(2, (int) $model->countKartuStokDT('', '', '', '', 'non_positif'));
    }

    public function testFilterStokNolDanPositif(): void
    {
        $model = new ModelKartuStok();

        $this->assertSame(
            ['A01'],
            $this->kodeBarang($model->getKartuStokDT(50, 0, '', 'stok_barang.kode_barang', 'ASC', '', '', '', 'nol'))
        );
        $this->assertSame(
            ['A01'],
            $this->kodeBarang($model->getKartuStokDT(50, 0, '', 'stok_barang.kode_barang', 'ASC', '', '', '', 'positif'))
        );
    }

    public function testFilterStokTidakDikenalDiabaikan(): void
    {
        $model = new ModelKartuStok();

        $rows = $model->getKartuStokDT(50, 0, '', 'stok_barang.kode_barang', 'ASC', '', '', '', 'bukan-filter');

        $this->assertCount(3, $rows);
    }

    public function testFilterKategoriDanStokDapatDigabung(): void
    {
        $model = new ModelKartuStok();

        $rows = $model->getKartuStokDT(50, 0, '', 'stok_barang.kode_barang', 'ASC', '', '', '2', 'nol');

        $this->assertSame(['A01'], $this->kodeBarang($rows));
        $this->assertSame(1, (int) $rows[0]->id_unit);
        $this->assertSame(1, (int) $model->countKartuStokDT('', '', '', '2', 'nol'));
    }

    public function testExportMemakaiFilterKategoriDanStok(): void
    {
        $model = new ModelKartuStok();

        $this->assertSame(['P01'], $this->kodeBarang($model->exportfilter(null, null, '1', 'minus')));
        $this->assertCount(1, $model->exportfilter(null, null, '2', 'non_positif'));
    }
}
