<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regresi: /kas_masuk memakai DataTables server-side dan scope unit baca.
 *
 * Role terbatas tidak boleh membaca unit lain lewat `data`, `recordsTotal`,
 * maupun `unit_id` palsu. Ringkasan harus mengikuti seluruh hasil filter.
 */
class KasMasukServerSideTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    /** @var \CodeIgniter\Database\BaseConnection */
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

        $q('SET FOREIGN_KEY_CHECKS = 0');
        foreach ([
            'kas_masuk', 'kategori_kas', 'no_akun', 'bank', 'unit', 'akun',
            'spv_units', 'akun_kas_bank', 'alokasi_saldo_kas_bank',
            'transaksi_kas_bank', 'jurnal', 'stok_barang', 'service',
            'pelanggan', 'jabatan', 'menu',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }
        $q('SET FOREIGN_KEY_CHECKS = 1');

        $q('CREATE TABLE unit (idunit INTEGER PRIMARY KEY, NAMA_UNIT TEXT NULL, LOGO TEXT NULL, is_operational INT NULL)');
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL, NAMA_AKUN TEXT NULL, ROLES TEXT NULL)');
        $q('CREATE TABLE spv_units (spv_id INT NULL, unit_id INT NULL)');
        $q('CREATE TABLE kategori_kas (idkategori_kas INTEGER PRIMARY KEY, kategori TEXT NULL)');
        $q('CREATE TABLE no_akun (no_akun TEXT NULL, nama_akun TEXT NULL, jenis_akun TEXT NULL)');
        $q('CREATE TABLE bank (idbank VARCHAR(20) PRIMARY KEY, nama_bank TEXT NULL, atas_nama TEXT NULL, norek TEXT NULL)');
        $q('CREATE TABLE kas_masuk (
                idkas_masuk INTEGER PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL,
                kategori_idkategori INT NULL, no_akun TEXT NULL, deskripsi TEXT NULL,
                jumlah REAL NULL, jenis TEXT NULL, penerima TEXT NULL, idbank TEXT NULL,
                idunit INT NULL, created_on TEXT NULL, updated_on TEXT NULL)');
        $q('CREATE TABLE akun_kas_bank (
                idakun_kas_bank INT PRIMARY KEY, unit_id INT NULL, tipe TEXT NULL,
                status TEXT NULL, is_shared INT NULL, is_finance_ho INT NULL,
                bank_idbank TEXT NULL, no_akun_coa TEXT NULL, nama_akun TEXT NULL)');
        $q('CREATE TABLE alokasi_saldo_kas_bank (akun_kas_bank_id INT NULL, unit_id INT NULL)');
        $q('CREATE TABLE transaksi_kas_bank (
                idtransaksi INTEGER PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL,
                unit_id INT NULL, akun_kas_bank_id INT NULL, jenis TEXT NULL,
                arah TEXT NULL, jumlah REAL NULL, akun_tujuan_id INT NULL,
                transfer_ref TEXT NULL, submission_key TEXT NULL, sumber_tipe TEXT NULL,
                sumber_id INT NULL, keterangan TEXT NULL, bukti TEXT NULL,
                input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $q('CREATE TABLE jurnal (
                idjurnal INTEGER PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL,
                no_akun TEXT NULL, nama_akun TEXT NULL, debet REAL NULL,
                kredit REAL NULL, keterangan TEXT NULL, id_referensi TEXT NULL,
                tabel_referensi TEXT NULL, id_unit INT NULL, id_akun INT NULL)');
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

        $q("INSERT INTO unit (idunit, NAMA_UNIT, is_operational) VALUES
            (1,'Unit A',1),(2,'Unit B',1),(3,'Unit C',1)");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES
            (1,'Admin Root','[]'),(35,'Kasir','[]'),(40,'SPV','[]')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN) VALUES
            (1,1,1,'Admin Root'), (200,2,35,'Kasir B'), (201,2,40,'SPV')");
        $q("INSERT INTO spv_units (spv_id, unit_id) VALUES (201,1),(201,2)");
        $q("INSERT INTO kategori_kas (idkategori_kas, kategori) VALUES (1,'Pendapatan'),(2,'Modal')");
        $q("INSERT INTO no_akun (no_akun, nama_akun, jenis_akun) VALUES
            ('4000000000','Pendapatan Lain','Pendapatan')");
        $q("INSERT INTO bank (idbank, nama_bank, atas_nama, norek) VALUES
            ('BNI-001','BNI','PT Contoh','5551')");
        $q("INSERT INTO kas_masuk (tanggal, kategori_idkategori, no_akun, deskripsi, jumlah, idunit, jenis, penerima, idbank) VALUES
            ('2026-11-01',1,'4000000000','tunai A',1000,1,'kredit','Kasir',NULL),
            ('2026-11-02',1,'4000000000','bank B',2000,2,'kredit','PT', 'BNI-001'),
            ('2026-11-03',2,'4000000000','tunai B',3000,2,'kredit','Kasir',NULL),
            ('2026-11-04',1,'4000000000','tunai C',4000,3,'kredit','Kasir',NULL)");
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
        $res = $this->get('kas_masuk/datatables?' . $query);

        $body = (string) $res->response()->getBody();
        $json = json_decode($body, true);
        $this->assertIsArray($json, 'Respons bukan JSON: ' . substr($body, 0, 800));

        return $json;
    }

    /**
     * Body HTML mentah dari response.
     *
     * `TestResponse` tidak punya `getBody()` sendiri; `__call` meneruskannya ke
     * `DOMParser::getBody()` yang mengembalikan hasil serialisasi ulang DOM.
     * Serialisasi itu meng-encode non-ASCII dan memakan `<=` / `</option>` di
     * dalam <script>, sehingga assertion string harus memakai body asli.
     */
    private function halaman(): string
    {
        $this->db->setPrefix('');

        return (string) $this->get('kas_masuk')->response()->getBody();
    }

    /** @return string isi <select id="kmUnitFilter"> */
    private function selectUnitFilter(): string
    {
        $html = $this->halaman();
        $this->assertMatchesRegularExpression('#<select[^>]*id="kmUnitFilter".*?</select>#s', $html);

        preg_match('#<select[^>]*id="kmUnitFilter".*?</select>#s', $html, $m);

        return $m[0];
    }

    public function testDatatableMengembalikanPayloadDanRingkasan(): void
    {
        $this->sesi(1, 1, 1);
        $json = $this->datatable('draw=7&start=0&length=25');

        $this->assertSame(7, $json['draw']);
        $this->assertSame(4, $json['recordsTotal']);
        $this->assertSame(4, $json['recordsFiltered']);
        $this->assertSame(10000.0, (float) $json['sumTotal']);
        $this->assertSame(8000.0, (float) $json['sumTunai']);
        $this->assertSame(2000.0, (float) $json['sumTransfer']);
        $this->assertSame(4, $json['countFiltered']);
        $this->assertArrayHasKey('nama_akun', $json['data'][0]);
    }

    public function testDatatableMengecualikanKasAwal(): void
    {
        $this->db->table('kas_masuk')->insert([
            'tanggal' => '2026-11-05', 'kategori_idkategori' => 1, 'no_akun' => '4000000000',
            'deskripsi' => 'kas awal', 'jumlah' => 5000000, 'idunit' => 1,
            'jenis' => null, 'penerima' => null, 'idbank' => null,
        ]);

        $this->sesi(1, 1, 1);
        $json = $this->datatable('draw=1&start=0&length=25');

        $this->assertSame(4, $json['recordsTotal'], 'Snapshot saldo awal tidak dihitung sebagai baris scope.');
        $this->assertSame(4, $json['recordsFiltered']);
        $this->assertSame(10000.0, (float) $json['sumTotal'], 'Saldo awal tidak masuk ringkasan.');
        $this->assertSame(4, $json['countFiltered']);

        foreach ($json['data'] as $row) {
            $this->assertNotSame('kas awal', strtolower(trim((string) $row['deskripsi'])));
        }
    }

    public function testModalInputMemakaiKartuAkunDanKontrakFieldLama(): void
    {
        $this->sesi(1, 1, 1);
        $html = $this->halaman();

        $this->assertStringContainsString('id="kmAkunBody"', $html);
        $this->assertStringContainsString('id="kmAddAkun"', $html);
        $this->assertStringContainsString('id="kmDraftTotal"', $html);
        $this->assertStringContainsString('name="akun[', $html);

        $this->assertStringNotContainsString('id="kmAkunTable"', $html, 'Tabel 8 kolom sudah diganti kartu.');
        $this->assertStringNotContainsString('id="kmAkunModal"', $html, 'Modal Cari Buku terpisah tidak dipakai lagi.');
        $this->assertStringNotContainsString('id="table_kasmasuk"', $html);

        foreach (['no_akun', 'jenis_akun', 'kategori_idkategori', 'jenis_transaksi', 'no_rekening', 'penerima', 'jumlah'] as $field) {
            $this->assertStringContainsString('[' . $field . ']', $html, 'Kontrak POST ' . $field . ' hilang.');
        }

        $this->assertStringContainsString('4000000000', $html, 'Daftar akun sah harus tersedia di modal.');
    }

    public function testFilterTanggalUnitKategoriDiServer(): void
    {
        $this->sesi(1, 1, 1);

        $this->assertSame(1, $this->datatable('draw=1&start=0&length=25&kategori_id=2')['recordsFiltered']);
        $this->assertSame(2, $this->datatable('draw=1&start=0&length=25&unit_id=2')['recordsFiltered']);
        $this->assertSame(2, $this->datatable('draw=1&start=0&length=25&tanggal_awal=2026-11-02&tanggal_akhir=2026-11-03')['recordsFiltered']);
    }

    public function testKasirTidakBisaMembacaUnitLain(): void
    {
        $this->sesi(200, 2, 35);
        $json = $this->datatable('draw=1&start=0&length=25&unit_id=1');

        $this->assertSame(2, $json['recordsFiltered']);
        $this->assertSame(2, $json['recordsTotal']);
        $this->assertSame(5000.0, (float) $json['sumTotal']);
        foreach ($json['data'] as $row) {
            $this->assertSame(2, (int) $row['idunit']);
        }
    }

    public function testSpvHanyaUnitBinaan(): void
    {
        $this->sesi(201, 2, 40);

        $binaan = $this->datatable('draw=1&start=0&length=25&unit_id=2');
        $this->assertSame(2, $binaan['recordsFiltered']);

        $luar = $this->datatable('draw=1&start=0&length=25&unit_id=3');
        $this->assertSame(1, $luar['recordsFiltered'], 'Unit di luar spv_units jatuh ke unit binaan pertama (1).');
    }

    public function testPencarianWildcardLiteral(): void
    {
        $this->sesi(1, 1, 1);
        $this->assertSame(0, $this->datatable('draw=1&start=0&length=25&search%5Bvalue%5D=%25')['recordsFiltered']);
    }

    public function testIndexMengirimFilterUnitDanAksi(): void
    {
        $this->sesi(200, 2, 35);
        $html = $this->halaman();

        $this->assertStringContainsString('id="kmUnitFilter"', $html);
        $this->assertStringContainsString('disabled', $this->selectUnitFilter());
        $this->assertStringNotContainsString('Unit A', $this->selectUnitFilter());
        $this->assertStringContainsString('kas_masuk/datatables', $html);
    }

    public function testIndexRoleLintasBisaSemuaUnit(): void
    {
        $this->sesi(1, 1, 1);
        $html = $this->halaman();

        $this->assertStringContainsString('Semua unit', $html);
        $this->assertStringContainsString('Unit A', $html);
        $this->assertStringContainsString('Unit B', $html);
    }

    public function testViewMemakaiDataTablesServerSideDanFieldLama(): void
    {
        $this->sesi(1, 1, 1);
        $html = $this->halaman();

        $this->assertStringContainsString('serverSide: true', $html);
        $this->assertStringContainsString('kas_masuk/datatables', $html);
        $this->assertStringContainsString('id="kmTable"', $html);

        // Kartu akun dibuat di klien, jadi kontrak field tetap sama tetapi
        // indeksnya dinamis (`akun[i][...]`), bukan literal `akun[0]`.
        foreach (['no_akun', 'jenis_akun', 'kategori_idkategori', 'jenis_transaksi', 'no_rekening', 'penerima', 'jumlah'] as $field) {
            $this->assertStringContainsString(
                'name="akun[\' + i + \'][' . $field . ']"',
                $html,
                'Kontrak POST ' . $field . ' hilang.'
            );
        }

        $this->assertStringContainsString('name="idkas_masuk"', $html);
        $this->assertStringNotContainsString('posisi_drk', $html);
    }

    public function testViewPunyaEmptyStateDanKartuResponsif(): void
    {
        $this->sesi(1, 1, 1);
        $html = $this->halaman();

        $this->assertStringContainsString('id="kmEmpty"', $html);
        $this->assertStringContainsString('data-label', $html);
        $this->assertStringContainsString('_kas_masuk_theme', $html);
        $this->assertStringContainsString('prefers-reduced-motion', $html);
    }
}
