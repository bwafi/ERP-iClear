<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * /riwayat_pembelian: DataTables server-side, scope unit, dan detail per nota.
 *
 * `no_batch` tidak unik (baris yatim bisa berbagi nomor dengan nota lain),
 * jadi identitas nota adalah pembelian.idpembelian.
 */
final class RiwayatPembelianServerSideTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    private string $prefixAsli = '';

    private const TABEL = [
        'unit', 'jabatan', 'akun', 'spv_units', 'suplier', 'barang',
        'pembelian', 'detail_pembelian', 'pelanggan', 'stok_barang', 'service',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->buatSkema();
        $this->sebarData();
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->close();
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL, LOGO TEXT NULL,
                is_operational INT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN TEXT NULL, STATUS_PEGAWAI INT NULL)');
        $q('CREATE TABLE spv_units (id INT PRIMARY KEY AUTO_INCREMENT, spv_id INT NULL, unit_id INT NULL)');
        $q('CREATE TABLE suplier (id_suplier INT PRIMARY KEY, nama_suplier TEXT NULL)');
        $q('CREATE TABLE barang (idbarang INT PRIMARY KEY, kode_barang TEXT NULL, nama_barang TEXT NULL,
                imei TEXT NULL, harga INT NULL, harga_beli INT NULL)');
        $q('CREATE TABLE pembelian (idpembelian INT PRIMARY KEY AUTO_INCREMENT,
                no_nota_supplier TEXT NULL, tanggal_masuk DATETIME NULL, unit_idunit INT NULL,
                suplier_id_suplier INT NULL, pelanggan_id_pelanggan INT NULL, sisa INT NULL,
                status TEXT NULL, total_transaksi INT NULL, total_diskon INT NULL,
                total_ppn INT NULL, total_bayar INT NULL, bayar INT NULL, bayar_tunai INT NULL,
                bayar_bank INT NULL, bank_idbank TEXT NULL, jatuh_tempo TEXT NULL,
                frontliner INT NULL, foto_nota TEXT NULL, input_by INT NULL)');
        $q('CREATE TABLE detail_pembelian (iddetail_pembelian INT PRIMARY KEY AUTO_INCREMENT,
                no_batch TEXT NULL, tanggal DATETIME NULL, jumlah INT NULL, hrg_beli INT NULL,
                diskon INT NULL, ppn INT NULL, hitung_hpp INT NULL, total_harga INT NULL,
                satuan_beli TEXT NULL, barang_idbarang INT NULL, pembelian_idpembelian INT NULL,
                unit_idunit INT NULL, biaya_tambahan INT NULL, keterangan_tambahan TEXT NULL)');

        // Dipakai BaseController (notifikasi stok minimum & service) saat
        // feature test memanggil controller.
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL)');
        $q('CREATE TABLE stok_barang (idbarang INT NULL, id_unit INT NULL,
                stok_akhir REAL NULL, stok_minimum REAL NULL)');
        $q('CREATE TABLE service (idservice INT PRIMARY KEY, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL, created_at TEXT NULL,
                tanggal_selesai TEXT NULL, tanggal_bisa_diambil TEXT NULL)');
    }

    private function sebarData(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        $q("INSERT INTO unit (idunit, NAMA_UNIT, is_operational) VALUES (1, 'Unit Satu', 1), (2, 'Unit Dua', 1)");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES
                (0, 'Finance', '[]'), (1, 'Admin root', '[]'), (41, 'Kepala Toko', '[]')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, STATUS_PEGAWAI) VALUES
                (1, 1, 1, 'Root', 1), (2, 2, 41, 'Kepala Toko Dua', 1), (3, 1, 0, 'Finance', 1)");
        $q("INSERT INTO suplier (id_suplier, nama_suplier) VALUES (1, 'Supplier Satu')");
        $q("INSERT INTO barang (idbarang, kode_barang, nama_barang, imei) VALUES
                (1, 'A-1', 'Barang A', 'IMEI-A'),
                (2, 'B-1', 'Barang B', NULL),
                (3, 'C-1', 'Barang C', NULL),
                (4, 'D-1', 'Barang D', NULL),
                (5, 'Y-1', 'Barang Yatim', NULL)");

        // Nota 101 dan 103 sengaja memakai no_batch yang sama.
        $q("INSERT INTO pembelian (idpembelian, no_nota_supplier, tanggal_masuk, unit_idunit,
                suplier_id_suplier, sisa, status, total_transaksi) VALUES
                (101, 'B-101', '2026-11-10', 1, 1, 0, 'Lunas', 300000),
                (102, 'B-102', '2026-11-20', 2, NULL, 50000, 'Belum Lunas', 50000),
                (103, 'B-101', '2026-11-15', 1, 1, 0, 'Lunas', 70000)");

        $q("INSERT INTO detail_pembelian (no_batch, tanggal, jumlah, hrg_beli, diskon, ppn,
                total_harga, satuan_beli, barang_idbarang, pembelian_idpembelian, unit_idunit) VALUES
                ('B-101', '2026-11-10 10:00:00', 2, 100000, 0, 0, 200000, 'pcs', 1, 101, 1),
                ('B-101', '2026-11-10 10:00:00', 1, 100000, 0, 0, 100000, 'pcs', 2, 101, 1),
                ('B-102', '2026-11-20 09:00:00', 1, 50000, 0, 0, 50000, 'pcs', 3, 102, 2),
                ('B-101', '2026-11-15 08:00:00', 1, 70000, 0, 0, 70000, 'pcs', 4, 103, 1),
                ('B-101', '2026-11-09 08:00:00', 1, 90000, 0, 0, 90000, 'pcs', 5, 999, 1)");
    }

    private function daftar(array $opsi = []): array
    {
        $model = new \App\Models\ModelPembelian();

        return $model->getRiwayatServerSide(
            $opsi['start'] ?? 0,
            $opsi['length'] ?? 25,
            $opsi['search'] ?? '',
            $opsi['orderColumn'] ?? 1,
            $opsi['orderDir'] ?? 'desc',
            $opsi['startDate'] ?? '',
            $opsi['endDate'] ?? '',
            $opsi['unitId'] ?? 0,
            $opsi['allowed'] ?? [1, 2]
        );
    }

    private function ids(array $hasil): array
    {
        return array_map(static fn ($row) => (int) $row->idpembelian, $hasil['data']);
    }

    public function testUrutanDefaultTanggalTerbaru(): void
    {
        $hasil = $this->daftar();

        $this->assertSame([102, 103, 101], $this->ids($hasil));
        $this->assertSame(3, $hasil['recordsFiltered']);
    }

    public function testPaginationDipotongDiServer(): void
    {
        $hasil = $this->daftar(['start' => 1, 'length' => 1]);

        $this->assertSame([103], $this->ids($hasil));
        $this->assertSame(3, $hasil['recordsFiltered']);
    }

    public function testCariBarangMengembalikanSatuNotaDenganJumlahItem(): void
    {
        $hasil = $this->daftar(['search' => 'Barang A']);

        $this->assertSame([101], $this->ids($hasil));
        $this->assertSame('Barang A', $hasil['data'][0]->nama_barang);
        $this->assertSame(2, (int) $hasil['data'][0]->jumlah_item);
    }

    public function testFilterUnit(): void
    {
        $hasil = $this->daftar(['unitId' => 2]);

        $this->assertSame([102], $this->ids($hasil));
    }

    public function testFilterTanggal(): void
    {
        $hasil = $this->daftar(['startDate' => '2026-11-14', 'endDate' => '2026-11-16']);

        $this->assertSame([103], $this->ids($hasil));
    }

    public function testScopeMenangAtasUnitDariRequest(): void
    {
        // Role unit 2 meminta unit 1: tetap hanya unit 2 yang boleh keluar.
        $hasil = $this->daftar(['unitId' => 1, 'allowed' => [2]]);

        $this->assertSame([102], $this->ids($hasil));
        $this->assertSame(1, $hasil['recordsFiltered']);
    }

    public function testScopeKosongTidakMengembalikanData(): void
    {
        $hasil = $this->daftar(['allowed' => []]);

        $this->assertSame([], $hasil['data']);
        $this->assertSame(0, $hasil['recordsFiltered']);

        $this->assertSame(0, (new \App\Models\ModelPembelian())->countRiwayat([]));
    }

    public function testCountRiwayatMengikutiScope(): void
    {
        $model = new \App\Models\ModelPembelian();

        $this->assertSame(2, $model->countRiwayat([1]));
        $this->assertSame(3, $model->countRiwayat([1, 2]));
    }

    public function testDetailHanyaMilikNotaWalauNoBatchSama(): void
    {
        $items = (new \App\Models\ModelDetailPembelian())->getByPembelianId(103);

        $this->assertCount(1, $items);
        $this->assertSame('Barang D', $items[0]->nama_barang);
    }

    public function testAjaxRoleUnitTidakBisaMelihatUnitLain(): void
    {
        $this->sesi(2, 2);

        $result = $this->post('riwayat_pembelian/ajax', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'unitFilter' => '1',
            'search' => ['value' => ''],
            'order' => [['column' => 1, 'dir' => 'desc']],
        ]);

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);

        $this->assertSame(1, $json['recordsTotal']);
        $this->assertSame([102], array_map(static fn ($row) => (int) $row['idpembelian'], $json['data']));
    }

    public function testDetailNotaUnitLainDitolak(): void
    {
        $this->sesi(2, 2);

        $this->get('riwayat_pembelian/detail/101')->assertStatus(404);
    }

    public function testDetailNotaSendiriDiizinkan(): void
    {
        $this->sesi(2, 2);

        $result = $this->get('riwayat_pembelian/detail/102');
        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame('B-102', $json['no_batch']);
        $this->assertCount(1, $json['items']);
        $this->assertSame('Barang C', $json['items'][0]['nama_barang']);
    }

    public function testRoleLintasUnitMelihatSemuaUnit(): void
    {
        $this->sesi(1, 1);

        $result = $this->post('riwayat_pembelian/ajax', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'unitFilter' => '',
            'search' => ['value' => ''],
            'order' => [['column' => 1, 'dir' => 'desc']],
        ]);

        $json = json_decode($result->getJSON(), true);

        $this->assertSame(3, $json['recordsTotal']);
        $this->assertSame([102, 103, 101], array_map(static fn ($row) => (int) $row['idpembelian'], $json['data']));
    }

    private function sesi(int $idAkun, int $idUnit): void
    {
        $this->withSession([
            'logged_in'  => true,
            'ID_AKUN'    => $idAkun,
            'ID_UNIT'    => $idUnit,
            'NAMA'       => 'Tester',
            'NAMA_UNIT'  => 'Tester',
        ]);
    }
}
