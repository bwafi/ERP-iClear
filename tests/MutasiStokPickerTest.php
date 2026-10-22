<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regresi picker produk /mutasi_stok yang sekarang server-side.
 *
 * Halaman index tidak boleh lagi mengirim seluruh katalog ke browser. Data
 * picker diambil dari /mutasi_stok/produk, hanya produk dengan stok > 0 di
 * unit pengirim, dan role cabang tidak boleh membaca unit lain lewat ?unit=.
 *
 * Sama seperti OmsetBulananUnitFilterTest, tes memakai prefix '' karena query
 * picker memakai nama tabel mentah (stok_barang, stok_awal, suplier).
 */
class MutasiStokPickerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->buatSkema();
        $this->sebarData();
    }

    protected function tearDown(): void
    {
        // AuthFilter menutup koneksi setelah request; tutup juga referensi tes
        // supaya full suite tidak kehabisan koneksi.
        $this->db->close();
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        foreach ([
            'unit', 'akun', 'jabatan', 'menu',
            'stok_barang', 'stok_awal', 'suplier', 'barang', 'kategori',
            'service', 'pelanggan',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE unit (idunit INTEGER PRIMARY KEY, NAMA_UNIT TEXT NULL, LOGO TEXT NULL)');
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL, NAMA_AKUN TEXT NULL, ROLES TEXT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE menu (
                idmenu INTEGER PRIMARY KEY AUTO_INCREMENT, urutan INT NULL,
                nama_menu TEXT NULL, roles TEXT NULL, url TEXT NULL,
                show_menu INT NULL, sub INT NULL, parent INT NULL,
                utama INT NULL, categories INT NULL, icon TEXT NULL, manualbook TEXT NULL)');

        // Di produksi stok_barang adalah VIEW; di tes cukup tabel dengan kolom
        // yang dipakai picker + notifikasi BaseController.
        $q('CREATE TABLE stok_barang (
                idbarang INT NOT NULL, id_unit INT NOT NULL,
                kode_barang TEXT NULL, nama_barang TEXT NULL,
                harga INT NULL, harga_beli INT NULL, idkategori INT NULL,
                imei TEXT NULL, deleted TEXT NULL,
                stok_akhir REAL NULL, stok_minimum REAL NULL,
                total_penjualan REAL NULL, nama_unit TEXT NULL,
                PRIMARY KEY (idbarang, id_unit))');
        $q('CREATE TABLE kategori (id INT PRIMARY KEY, nama_kategori TEXT NULL, `delete` TEXT NULL)');
        $q('CREATE TABLE stok_awal (
                idstok_awal INT PRIMARY KEY, unit_idunit INT NULL,
                barang_idbarang INT NULL, suplier_id_suplier VARCHAR(45) NULL)');
        $q('CREATE TABLE suplier (id_suplier VARCHAR(45) PRIMARY KEY, nama_suplier TEXT NULL, unit_idunit INT NULL, deleted TEXT NULL)');

        // BaseController::initController membaca tabel ini.
        $q('CREATE TABLE service (
                idservice INT PRIMARY KEY, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL,
                tanggal_selesai TEXT NULL, tanggal_bisa_diambil TEXT NULL,
                tipe_hp TEXT NULL, created_at TEXT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL, kabupaten TEXT NULL, kecamatan TEXT NULL, deleted TEXT NULL)');

        // Hanya dipakai kode lama (getAllBarang); setelah perbaikan tidak lagi.
        $q('CREATE TABLE barang (
                idbarang INT PRIMARY KEY, kode_barang TEXT NULL, nama_barang TEXT NULL,
                harga INT NULL, harga_beli INT NULL, idkategori INT NULL,
                imei TEXT NULL, deleted TEXT NULL, status_ppn INT NULL)');
    }

    private function sebarData(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        $q("INSERT INTO unit (idunit, NAMA_UNIT, LOGO) VALUES
            (1, 'ICLEAR Probolinggo', 'logo.png'),
            (2, 'ICLEAR Jember', 'logo.png'),
            (50, 'Head Office', 'logo.png')");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES
            (1, 'Admin root', '[]'), (35, 'ADMIN / KASIR', '[]')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN) VALUES
            (1, 1, 1, 'Admin Root'), (43, 2, 35, 'Kasir Jember')");
        $q("INSERT INTO kategori (id, nama_kategori, `delete`) VALUES
            (1, 'Sparepart', '0')");
        $q("INSERT INTO suplier (id_suplier, nama_suplier, deleted) VALUES
            ('S1', 'Suplier A', '0'), ('S2', 'Suplier B', '0')");

        $barang = [];
        $stok1 = [];
        $stok2 = [];
        for ($i = 1; $i <= 15; $i++) {
            $kode = sprintf('P%02d', $i);
            $nama = sprintf('Produk %02d', $i);
            $harga = 100000 + ($i * 10000);
            $hargaBeli = 1000 * $i;
            $barang[] = "($i, '$kode', '$nama', $harga, $hargaBeli, 1, NULL, '0', 0)";

            if ($i <= 12) {
                $akhir = 10;
            } elseif ($i <= 14) {
                $akhir = 0;
            } else {
                $akhir = null; // tidak punya baris stok di unit 1
            }
            if ($akhir !== null) {
                $stok1[] = "($i, 1, '$kode', '$nama', $harga, $hargaBeli, 1, NULL, '0', $akhir, 0, 0)";
            }

            if ($i >= 13) {
                $stok2[] = "($i, 2, '$kode', '$nama', $harga, $hargaBeli, 1, NULL, '0', 5, 0, 0)";
            }
        }

        $q('INSERT INTO barang (idbarang, kode_barang, nama_barang, harga, harga_beli, idkategori, imei, deleted, status_ppn) VALUES ' . implode(',', $barang));
        $q('INSERT INTO stok_barang (idbarang, id_unit, kode_barang, nama_barang, harga, harga_beli, idkategori, imei, deleted, stok_akhir, stok_minimum, total_penjualan) VALUES ' . implode(',', $stok1));
        $q('INSERT INTO stok_barang (idbarang, id_unit, kode_barang, nama_barang, harga, harga_beli, idkategori, imei, deleted, stok_akhir, stok_minimum, total_penjualan) VALUES ' . implode(',', $stok2));

        // Produk 1 punya dua stok awal; yang terbaru (S2) yang dipakai.
        $q("INSERT INTO stok_awal (idstok_awal, unit_idunit, barang_idbarang, suplier_id_suplier) VALUES
            (1, 1, 1, 'S1'), (2, 1, 1, 'S2'), (3, 1, 2, 'S2'), (4, 2, 13, 'S1')");
    }

    private function sesi(int $akun, int $unit, int $jabatan): void
    {
        $this->withSession([
            'logged_in'    => true,
            'ID_AKUN'      => $akun,
            'ID_UNIT'      => $unit,
            'ID_JABATAN'   => $jabatan,
            'NAMA'         => 'Tester',
            'NAMA_JABATAN' => $jabatan === 1 ? 'Admin root' : 'ADMIN / KASIR',
            'EMAIL'        => 'tester@example.com',
        ]);
    }

    private function picker(array $params): array
    {
        $this->db->setPrefix('');
        $r = $this->get('mutasi_stok/produk?' . http_build_query($params));
        $r->assertStatus(200);

        return json_decode($r->response()->getBody(), true) ?? [];
    }

    private function idDariBaris(string $html): int
    {
        $this->assertMatchesRegularExpression('/data-id="(\d+)"/', $html);
        preg_match('/data-id="(\d+)"/', $html, $m);

        return (int) $m[1];
    }

    private function idDariResponse(array $json): array
    {
        return array_map(fn(array $row) => $this->idDariBaris($row[0]), $json['data']);
    }

    public function testPickerServerSide(): void
    {
        $this->cekStokDanPagination();
        $this->cekScopeCabang();
        $this->cekScopeAdmin();
        $this->cekSearchSuplier();
        $this->cekSortHarga();
        $this->cekIndexTanpaProduk();
    }

    private function cekStokDanPagination(): void
    {
        $this->sesi(1, 1, 1);

        $json = $this->picker([
            'draw' => 1, 'start' => 0, 'length' => 10, 'unit' => 1,
            'order' => [0 => ['column' => 1, 'dir' => 'asc']],
            'search' => ['value' => ''],
        ]);

        $this->assertSame(12, $json['recordsTotal'], 'Hanya produk stok > 0 di unit 1 yang dihitung');
        $this->assertSame(12, $json['recordsFiltered']);
        $this->assertCount(10, $json['data'], 'Picker harus membatasi 10 baris per request');
        $this->assertSame(range(1, 10), $this->idDariResponse($json));

        $ids = $this->idDariResponse($json);
        $this->assertNotContains(13, $ids, 'Produk stok 0 tidak boleh ikut');
        $this->assertNotContains(14, $ids, 'Produk stok 0 tidak boleh ikut');
        $this->assertNotContains(15, $ids, 'Produk tanpa baris stok tidak boleh ikut');
    }

    private function cekScopeCabang(): void
    {
        // Kasir unit 2 meminta unit=1; harus tetap dapat data unit 2.
        $this->sesi(43, 2, 35);

        $json = $this->picker([
            'draw' => 1, 'start' => 0, 'length' => 10, 'unit' => 1,
            'order' => [0 => ['column' => 1, 'dir' => 'asc']],
            'search' => ['value' => ''],
        ]);

        $this->assertSame(3, $json['recordsTotal']);
        $this->assertSame([13, 14, 15], $this->idDariResponse($json));
    }

    private function cekScopeAdmin(): void
    {
        $this->sesi(1, 1, 1);

        $json = $this->picker([
            'draw' => 1, 'start' => 0, 'length' => 10, 'unit' => 2,
            'order' => [0 => ['column' => 1, 'dir' => 'asc']],
            'search' => ['value' => ''],
        ]);

        $this->assertSame(3, $json['recordsTotal']);
        $this->assertSame([13, 14, 15], $this->idDariResponse($json));
    }

    private function cekSearchSuplier(): void
    {
        $this->sesi(1, 1, 1);

        $json = $this->picker([
            'draw' => 1, 'start' => 0, 'length' => 10, 'unit' => 1,
            'order' => [0 => ['column' => 1, 'dir' => 'asc']],
            'search' => ['value' => 'Suplier B'],
        ]);

        $this->assertSame(2, $json['recordsFiltered']);
        $this->assertSame([1, 2], $this->idDariResponse($json));
    }

    private function cekSortHarga(): void
    {
        $this->sesi(1, 1, 1);

        $json = $this->picker([
            'draw' => 1, 'start' => 0, 'length' => 10, 'unit' => 1,
            'order' => [0 => ['column' => 4, 'dir' => 'desc']],
            'search' => ['value' => ''],
        ]);

        $this->assertSame(12, $this->idDariBaris($json['data'][0][0]));
    }

    private function cekIndexTanpaProduk(): void
    {
        $this->sesi(1, 1, 1);
        $this->db->setPrefix('');

        $html = (string) $this->get('mutasi_stok')->getBody();

        $start = strpos($html, 'id="produk-modal-table"');
        $this->assertNotFalse($start, 'Tabel picker harus tetap ada');
        $end = strpos($html, '</table>', $start);
        $segment = substr($html, $start, $end - $start);

        $this->assertStringNotContainsString(
            'produk-checkbox',
            $segment,
            'Index tidak boleh merender katalog produk; picker harus server-side'
        );
    }
}
