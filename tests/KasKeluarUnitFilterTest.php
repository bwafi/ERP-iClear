<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regresi: filter unit /kas_keluar bukan sekadar UI.
 *
 * Role terbatas hanya boleh melihat unitnya sendiri; SPV hanya unit binaan
 * (spv_units). Request `unit_id` di luar scope harus jatuh ke unit yang
 * diizinkan, bukan membuka unit lain.
 */
class KasKeluarUnitFilterTest extends CIUnitTestCase
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
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        foreach ([
            'kas_keluar', 'kategori_kas', 'no_akun', 'bank', 'unit', 'akun',
            'spv_units', 'akun_kas_bank', 'alokasi_saldo_kas_bank',
            'transaksi_kas_bank',
            'stok_barang', 'service', 'pelanggan', 'jabatan', 'menu',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE unit (idunit INTEGER PRIMARY KEY, NAMA_UNIT TEXT NULL, LOGO TEXT NULL)');
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL, NAMA_AKUN TEXT NULL, ROLES TEXT NULL)');
        $q('CREATE TABLE spv_units (spv_id INT NULL, unit_id INT NULL)');
        $q('CREATE TABLE kategori_kas (idkategori_kas INTEGER PRIMARY KEY, kategori TEXT NULL)');
        $q('CREATE TABLE no_akun (no_akun TEXT NULL, nama_akun TEXT NULL)');
        $q('CREATE TABLE bank (idbank VARCHAR(20) PRIMARY KEY, nama_bank TEXT NULL, atas_nama TEXT NULL, norek TEXT NULL)');
        $q('CREATE TABLE kas_keluar (
                idkas_keluar INTEGER PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL,
                kategori_idkategori INT NULL, deskripsi TEXT NULL, jumlah REAL NULL,
                penerima TEXT NULL, idunit INT NULL, jenis TEXT NULL, idbank TEXT NULL,
                no_akun TEXT NULL, created_on TEXT NULL, updated_on TEXT NULL)');
        // Dibutuhkan index() untuk role Finance/Admin Root (rekening HO).
        $q('CREATE TABLE akun_kas_bank (
                idakun_kas_bank INT PRIMARY KEY, unit_id INT NULL, tipe TEXT NULL,
                status TEXT NULL, is_shared INT NULL, is_finance_ho INT NULL,
                bank_idbank TEXT NULL, no_akun_coa TEXT NULL, nama_akun TEXT NULL)');
        $q('CREATE TABLE alokasi_saldo_kas_bank (akun_kas_bank_id INT NULL, unit_id INT NULL)');
        // Sumber baris read-only "Antar Unit" di daftar kas keluar.
        $q('CREATE TABLE transaksi_kas_bank (
                idtransaksi INTEGER PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL,
                unit_id INT NULL, akun_kas_bank_id INT NULL, jenis TEXT NULL,
                arah TEXT NULL, jumlah REAL NULL, akun_tujuan_id INT NULL,
                transfer_ref TEXT NULL, submission_key TEXT NULL, sumber_tipe TEXT NULL,
                sumber_id INT NULL, keterangan TEXT NULL, bukti TEXT NULL,
                input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        // BaseController selalu memuat notifikasi stok & service.
        $q('CREATE TABLE stok_barang (
                idbarang INT PRIMARY KEY, id_unit INT NULL, stok_akhir REAL NULL,
                stok_minimum REAL NULL, nama_barang TEXT NULL)');
        $q('CREATE TABLE service (
                idservice INT PRIMARY KEY, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL,
                tanggal_selesai TEXT NULL, tanggal_bisa_diambil TEXT NULL,
                tipe_hp TEXT NULL, created_at TEXT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE menu (
                idmenu INTEGER PRIMARY KEY AUTO_INCREMENT, urutan INT NULL,
                nama_menu TEXT NULL, roles TEXT NULL, url TEXT NULL,
                show_menu INT NULL, sub INT NULL, parent INT NULL,
                utama INT NULL, categories INT NULL, icon TEXT NULL,
                manualbook TEXT NULL)');
    }

    private function sebarData(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        $q("INSERT INTO unit (idunit, NAMA_UNIT) VALUES (1,'Unit A'),(2,'Unit B'),(3,'Unit C')");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES
            (1,'Admin Root','[]'),(35,'Kasir','[]'),(40,'SPV','[]')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN) VALUES
            (1, 1, 1, 'Admin Root'), (200, 2, 35, 'Kasir B'), (201, 2, 40, 'SPV')");
        $q("INSERT INTO spv_units (spv_id, unit_id) VALUES (201,1),(201,2)");
        $q("INSERT INTO kategori_kas (idkategori_kas, kategori) VALUES
            (1,'Operasional'),(6,'Lain-lain'),(18,'Lainnya(Termasuk 6%)')");
        $q("INSERT INTO no_akun (no_akun, nama_akun) VALUES ('1010101','Kas')");
        $q("INSERT INTO kas_keluar (tanggal, kategori_idkategori, deskripsi, jumlah, idunit, jenis)
            VALUES
            ('2026-10-01',1,'unit A',1000,1,'debet'),
            ('2026-10-02',1,'unit B 1',2000,2,'debet'),
            ('2026-10-03',1,'unit B 2',3000,2,'debet'),
            ('2026-10-04',1,'unit C',4000,3,'debet')");
    }

    private function sesi(int $akun, int $unit, int $jabatan): void
    {
        $this->withSession([
            'logged_in'    => true,
            'ID_AKUN'      => $akun,
            'ID_UNIT'      => $unit,
            'ID_JABATAN'   => $jabatan,
            'NAMA'         => 'Tester',
            'NAMA_JABATAN' => 'Tester',
            'EMAIL'        => 'tester@example.com',
        ]);
    }

    private function datatable(string $query): array
    {
        $this->db->setPrefix('');
        $res = $this->get('kas_keluar/datatables?' . $query);

        $body = (string) $res->response()->getBody();
        $json = json_decode($body, true);
        $this->assertIsArray($json, 'Respons bukan JSON: ' . substr($body, 0, 800));

        return $json;
    }

    /** @return string isi <select id="kkUnitFilter"> */
    private function selectUnitFilter(): string
    {
        $this->db->setPrefix('');
        $html = (string) $this->get('kas_keluar')->getBody();
        $this->assertMatchesRegularExpression('#<select[^>]*id="kkUnitFilter".*?</select>#s', $html);

        preg_match('#<select[^>]*id="kkUnitFilter".*?</select>#s', $html, $m);

        return $m[0];
    }

    /**
     * Form input harus menunjukkan kategori mana yang masuk batas 6% dan mana
     * yang di luar. Daftarnya sama dengan Config\Finance::$kategoriBiayaTerkontrol
     * yang dipakai /asset_berjalan.
     */
    public function testKategoriMenandaiBatasEnamPersen(): void
    {
        $this->sesi(1, 1, 1);

        $this->db->setPrefix('');
        $html = (string) $this->get('kas_keluar')->getBody();

        $this->assertMatchesRegularExpression('#<select[^>]*id="kkEditKategori".*?</select>#s', $html);
        preg_match('#<select[^>]*id="kkEditKategori".*?</select>#s', $html, $m);

        $this->assertStringContainsString('Operasional · 6%', $m[0]);
        $this->assertStringContainsString('Lainnya(Termasuk 6%) · 6%', $m[0]);
        $this->assertStringContainsString('>Lain-lain</option>', $m[0], 'Kategori di luar 6% tampil polos tanpa penanda');
        $this->assertStringNotContainsString('di luar 6%', $m[0]);
        $this->assertStringContainsString('.kk-kat-enam', $html, 'Hanya badge 6% yang punya warna sendiri');
        $this->assertStringContainsString('templateResult: formatKategori', $html);

        $this->assertStringContainsString('id="kkHanya6"', $html, 'Checkbox filter 6% harus tersedia');
        $this->assertStringContainsString('"enam":true', $html, 'Daftar kategori JS harus membawa penanda 6%');
    }

    /** Daftar memakai modal input dan filter kategori, bukan dua deck. */
    public function testDaftarMemakaiModalDanFilterKategori(): void
    {
        $this->sesi(1, 1, 1);

        $this->db->setPrefix('');
        $html = (string) $this->get('kas_keluar')->getBody();

        $this->assertStringContainsString('id="kkSheet"', $html);
        $this->assertMatchesRegularExpression('#class="modal fade kk-modal"#', $html);
        $this->assertStringContainsString('modal-dialog-scrollable', $html);
        $this->assertStringNotContainsString('class="offcanvas offcanvas-end kk-sheet"', $html, 'Input tidak lagi di sheet samping');
        $this->assertStringContainsString('name="kkKatMode"', $html);
        $this->assertStringContainsString('id="kkSumEnam"', $html);
        $this->assertStringContainsString('id="kkOpenSheet"', $html);
        $this->assertStringNotContainsString('id="kkComposer"', $html, 'Composer lama harus sudah hilang');
    }

    /**
     * Filter 6% harus bekerja di server, bukan hanya menyembunyikan baris di
     * klien. Ringkasan tetap membandingkan kedua sisi saat salah satu difilter.
     */
    public function testFilterKategoriEnamDanLuar(): void
    {
        $this->sesi(1, 1, 1);
        $this->db->setPrefix('');
        $this->db->query(
            "INSERT INTO kas_keluar (tanggal, kategori_idkategori, deskripsi, jumlah, idunit, jenis)
             VALUES ('2026-10-05',6,'di luar 6%',500,1,'debet')"
        );

        $enam = $this->datatable('draw=1&start=0&length=25&kategori_mode=enam');
        $this->assertSame(4, $enam['recordsFiltered'], 'Hanya kategori 1,2,3,4,5,18 yang masuk 6%');
        $this->assertSame(10000.0, (float) $enam['sumTotal']);
        $this->assertSame(10000.0, (float) $enam['sumEnam']);
        $this->assertSame(500.0, (float) $enam['sumLuar']);
        $this->assertSame(4, $enam['countEnam']);
        $this->assertSame(1, $enam['countLuar']);
        foreach ($enam['data'] as $row) {
            $this->assertTrue($row['enam']);
        }

        $luar = $this->datatable('draw=1&start=0&length=25&kategori_mode=luar');
        $this->assertSame(1, $luar['recordsFiltered']);
        $this->assertSame(500.0, (float) $luar['sumTotal']);
        $this->assertSame('Lain-lain', $luar['data'][0]['kategori']);
        $this->assertFalse($luar['data'][0]['enam']);

        $semua = $this->datatable('draw=1&start=0&length=25&kategori_mode=');
        $this->assertSame(5, $semua['recordsFiltered']);
        $this->assertSame(10500.0, (float) $semua['sumTotal']);
    }

    /**
     * Pelunasan antar unit tidak disalin ke kas_keluar (posting keluar jadi
     * dobel), jadi daftar kas keluar membaca leg KELUAR-nya dari
     * transaksi_kas_bank sebagai baris read-only.
     *
     * Yang TIDAK boleh ikut: leg MASUK, dan leg atribusi (rekening fisik sama,
     * uang tidak keluar).
     */
    public function testPelunasanAntarUnitTampilReadOnlyDiKasKeluar(): void
    {
        $this->sesi(200, 2, 35);
        $this->db->setPrefix('');

        $this->db->query("INSERT INTO bank (idbank, nama_bank, atas_nama, norek)
            VALUES ('BCA-2','BCA','PT CV','0393778773')");
        $this->db->query("INSERT INTO akun_kas_bank
            (idakun_kas_bank, unit_id, tipe, status, is_shared, is_finance_ho, bank_idbank, nama_akun)
            VALUES (61, 2, 'BANK', 'aktif', 0, 0, 'BCA-2', 'BCA Unit B')");
        $this->db->query("INSERT INTO transaksi_kas_bank
            (tanggal, unit_id, akun_kas_bank_id, jenis, arah, jumlah, akun_tujuan_id,
             transfer_ref, sumber_tipe, keterangan)
            VALUES
            ('2026-10-06', 2, 61, 'PEMBAYARAN_ANTAR_UNIT', 'KELUAR', 1500, 12, 'BUT-1', NULL, ''),
            ('2026-10-06', 1, 12, 'PEMBAYARAN_ANTAR_UNIT', 'MASUK', 1500, 61, 'BUT-1', 'antar_unit', 'Pantulan masuk'),
            ('2026-10-06', 2, 61, 'PEMBAYARAN_ANTAR_UNIT', 'KELUAR', 900, 61, 'BUT-2', 'antar_unit_atribusi', 'Mutasi hak')");

        $json = $this->datatable('draw=1&start=0&length=25');

        $this->assertSame(3, $json['recordsFiltered'], '2 kas keluar unit 2 + 1 leg KELUAR antar unit');
        $this->assertSame(6500.0, (float) $json['sumTotal'], 'Leg KELUAR ikut total, leg MASUK/atribusi tidak');

        $readonly = array_values(array_filter($json['data'], static fn ($r) => $r['readonly'] === true));
        $this->assertCount(1, $readonly);
        $this->assertSame('BUT-1', $readonly[0]['ref']);
        $this->assertSame('Pelunasan antar unit Unit B → Unit A', $readonly[0]['deskripsi'],
            'Arah pelunasan harus menyebut unit asal -> unit tujuan');
        $this->assertSame(1500.0, (float) $readonly[0]['jumlah']);
        $this->assertSame('BCA-2', $readonly[0]['idbank']);
        $this->assertSame('BCA', $readonly[0]['nama_bank']);
        $this->assertSame(2, (int) $readonly[0]['idunit']);
        $this->assertSame(0, (int) $readonly[0]['id'], 'Leg antar unit tidak membawa idkas_keluar');

        // Pencarian ID angka tidak boleh menyambar idtransaksi (#1 = kas
        // keluar unit lain, leg antar unit juga #1).
        $cari = $this->datatable('draw=1&start=0&length=25&search%5Bvalue%5D=1&search_mode=auto');
        $this->assertNull($cari['idHit']);
        $this->assertSame(0, $cari['recordsFiltered']);

        // Pencarian teks tetap mencakup keterangan leg antar unit...
        $teks = $this->datatable('draw=1&start=0&length=25&search%5Bvalue%5D=Pelunasan');
        $this->assertSame(1, $teks['recordsFiltered']);
        $this->assertSame(1500.0, (float) $teks['sumTotal']);

        // ...dan wildcard LIKE dari input user di-escape, bukan dieksekusi.
        $wildcard = $this->datatable('draw=1&start=0&length=25&search%5Bvalue%5D=%25');
        $this->assertSame(0, $wildcard['recordsFiltered']);
    }

    /** Baris antar unit tidak menawarkan tombol edit/hapus. */
    public function testBarisAntarUnitTanpaTombolEditHapus(): void
    {
        $this->sesi(200, 2, 35);
        $this->db->setPrefix('');

        $html = (string) $this->get('kas_keluar')->getBody();

        $this->assertStringContainsString('Read-only: pelunasan antar unit dikelola di Kas & Bank', $html);
        $this->assertStringContainsString('if (row.readonly)', $html, 'Sel aksi harus bercabang untuk baris read-only');
    }

    /** Simpan yang ditolak harus menyimpan isian untuk dibuka lagi. */
    public function testSimpanTanpaAkunMengembalikanIsian(): void
    {
        $this->sesi(1, 1, 1);
        $this->db->setPrefix('');

        $res = $this->post('insert_kas_keluar', [
            'tanggal' => '2026-10-09',
            'deskripsi' => 'Pembelian ATK',
            'unit_idunit' => 1,
        ]);
        $this->assertSame(302, $res->response()->getStatusCode());

        $old = session()->getFlashdata('kk_old');
        $this->assertIsArray($old);
        $this->assertSame('2026-10-09', $old['tanggal']);
        $this->assertSame('Pembelian ATK', $old['deskripsi']);
        $this->assertSame(1, $old['unit_idunit']);
        $this->assertSame('Minimal satu baris akun wajib diisi.', session()->getFlashdata('gagal'));
    }

    public function testKasirTidakBisaMemfilterUnitLain(): void

    {
        $this->sesi(200, 2, 35);
        $json = $this->datatable('draw=1&start=0&length=25&unit_id=1');

        $this->assertSame(2, $json['recordsFiltered'], 'Kasir unit 2 tidak boleh melihat baris unit 1');
        $this->assertSame(2, $json['recordsTotal']);
        $this->assertSame(5000.0, (float) $json['sumTotal']);
        foreach ($json['data'] as $row) {
            $this->assertSame(2, (int) $row['idunit']);
        }
    }

    public function testRoleLintasTetapBisaMemfilter(): void
    {
        $this->sesi(1, 1, 1);

        $terfilter = $this->datatable('draw=1&start=0&length=25&unit_id=1');
        $this->assertSame(1, $terfilter['recordsFiltered']);
        $this->assertSame(4, $terfilter['recordsTotal'], 'recordsTotal role lintas tetap seluruh perusahaan');

        $semua = $this->datatable('draw=1&start=0&length=25');
        $this->assertSame(4, $semua['recordsFiltered']);
    }

    public function testSpvHanyaBisaMemfilterUnitBinaan(): void
    {
        $this->sesi(201, 2, 40);

        $binaan = $this->datatable('draw=1&start=0&length=25&unit_id=2');
        $this->assertSame(2, $binaan['recordsFiltered']);

        // Unit 3 tidak ada di spv_units -> jatuh ke unit binaan pertama (1).
        $luar = $this->datatable('draw=1&start=0&length=25&unit_id=3');
        $this->assertSame(1, $luar['recordsFiltered']);
        $this->assertSame(1, (int) $luar['data'][0]['idunit']);
    }

    public function testIdUnitLainDianggapTidakAda(): void
    {
        $this->sesi(200, 2, 35);
        $json = $this->datatable('draw=1&start=0&length=25&search%5Bvalue%5D=1&search_mode=auto');

        $this->assertNull($json['idHit'], 'Tebakan ID unit lain tidak boleh membocorkan detail');
        $this->assertSame(0, $json['recordsFiltered']);
    }

    public function testIndexKasirTerkunciKeUnitSendiri(): void
    {
        $this->sesi(200, 2, 35);
        $select = $this->selectUnitFilter();

        $this->assertStringContainsString('disabled', $select);
        $this->assertStringContainsString('Unit B', $select);
        $this->assertStringNotContainsString('Semua Unit', $select);
        $this->assertStringNotContainsString('Unit A', $select);
        $this->assertStringNotContainsString('Unit C', $select);
    }

    public function testIndexSpvHanyaUnitBinaan(): void
    {
        $this->sesi(201, 2, 40);
        $select = $this->selectUnitFilter();

        $this->assertStringNotContainsString('disabled', $select);
        $this->assertStringContainsString('Unit A', $select);
        $this->assertStringContainsString('Unit B', $select);
        $this->assertStringNotContainsString('Unit C', $select);
        $this->assertStringNotContainsString('Semua Unit', $select);
    }

    public function testIndexRoleLintasBisaSemuaUnit(): void
    {
        $this->sesi(1, 1, 1);
        $select = $this->selectUnitFilter();

        $this->assertStringNotContainsString('disabled', $select);
        $this->assertStringContainsString('Semua Unit', $select);
        $this->assertStringContainsString('Unit A', $select);
        $this->assertStringContainsString('Unit B', $select);
        $this->assertStringContainsString('Unit C', $select);
    }
}
