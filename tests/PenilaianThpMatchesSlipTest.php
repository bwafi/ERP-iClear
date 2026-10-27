<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regresi: Take Home Pay (Estimasi) di /penilaian_kinerja harus sama dengan
 * PENERIMAAN BERSIH (A-B) di slip gaji untuk pegawai & periode yang sama.
 *
 * Estimasi sebelumnya hanya memakai komponen gaji + penempatan + insentif:
 * lembur tidak ditambahkan, bon & kasbon tidak dikurangkan.
 *
 * @internal
 */
final class PenilaianThpMatchesSlipTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const PEGAWAI = 900;
    private const UNIT = 91;

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
        $q = fn (string $sql) => $this->db->query($sql);

        foreach ([
            'unit', 'akun', 'jabatan', 'menu', 'spv_units',
            'stok_barang', 'penjualan', 'detail_penjualan', 'service', 'pelanggan',
            'kas_keluar', 'kategori_kas', 'hutang_piutang',
            'incentive_members', 'salary_components', 'salary_structures',
            'kpi_components', 'kpi_weights', 'kpi_targets', 'kpi_evaluations',
            'kpi_attendance_detail', 'kpi_attendance_off',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE unit (
                idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL, LOGO TEXT NULL,
                JALAN_UNIT TEXT NULL, KABUPATEN_UNIT TEXT NULL, jenis TEXT NULL,
                is_trial INT NULL, is_operational INT NULL)');
        $q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN TEXT NULL, NOID TEXT NULL, ROLES TEXT NULL,
                ALAMAT TEXT NULL, TERIMA_GAJI INT NULL, STATUS_PEGAWAI INT NULL,
                deleted TEXT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE spv_units (spv_id INT NULL, unit_id INT NULL)');
        $q('CREATE TABLE stok_barang (
                idbarang INT PRIMARY KEY, id_unit INT NULL, stok_akhir REAL NULL,
                stok_minimum REAL NULL, nama_barang TEXT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL)');
        $q('CREATE TABLE menu (
                idmenu INTEGER PRIMARY KEY AUTO_INCREMENT, urutan INT NULL,
                nama_menu TEXT NULL, roles TEXT NULL, url TEXT NULL,
                show_menu INT NULL, sub INT NULL, parent INT NULL,
                utama INT NULL, categories INT NULL, icon TEXT NULL,
                manualbook TEXT NULL)');

        $q('CREATE TABLE penjualan (
                idpenjualan INTEGER PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL,
                unit_idunit INT NULL)');
        $q('CREATE TABLE detail_penjualan (
                iddetail_penjualan INTEGER PRIMARY KEY AUTO_INCREMENT,
                penjualan_idpenjualan INT NULL, sub_total REAL NULL,
                hpp_penjualan REAL NULL)');
        $q('CREATE TABLE service (
                idservice INT PRIMARY KEY AUTO_INCREMENT, unit_idunit INT NULL,
                service_by INT NULL, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, created_at TEXT NULL,
                tanggal_selesai TEXT NULL, tanggal_bisa_diambil TEXT NULL,
                refund_garansi_jumlah REAL NULL,
                refund_garansi_tanggal TEXT NULL)');

        $q('CREATE TABLE kategori_kas (idkategori_kas INTEGER PRIMARY KEY, kategori TEXT NULL)');
        $q('CREATE TABLE kas_keluar (
                idkas_keluar INTEGER PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL,
                kategori_idkategori INT NULL, deskripsi TEXT NULL, jumlah REAL NULL,
                penerima TEXT NULL, idunit INT NULL)');
        $q('CREATE TABLE hutang_piutang (
                id INTEGER PRIMARY KEY AUTO_INCREMENT, deleted INT NULL,
                scope TEXT NULL, sumber_tipe TEXT NULL, pihak_tipe TEXT NULL,
                pihak_id INT NULL, status TEXT NULL, sisa REAL NULL,
                unit_id INT NULL)');

        // Mesin gaji: tanpa KPI/evaluasi → skor 0, gaji murni dari struktur.
        $q('CREATE TABLE incentive_members (
                id INTEGER PRIMARY KEY AUTO_INCREMENT, employee_id INT NULL,
                incentive_group_id INT NULL, unit_id INT NULL,
                effective_from TEXT NULL, effective_to TEXT NULL,
                is_active INT NULL)');
        $q('CREATE TABLE salary_components (
                id INT PRIMARY KEY, code TEXT NULL, name TEXT NULL,
                type TEXT NULL, is_active INT NULL)');
        $q('CREATE TABLE salary_structures (
                id INTEGER PRIMARY KEY AUTO_INCREMENT, position_id INT NULL,
                salary_component_id INT NULL, unit_id INT NULL, context TEXT NULL,
                base_value REAL NULL, calculation_type TEXT NULL, multiplier REAL NULL,
                effective_from TEXT NULL, effective_to TEXT NULL)');
        $q('CREATE TABLE kpi_components (
                id INT PRIMARY KEY, code TEXT NULL, name TEXT NULL,
                type TEXT NULL, is_active INT NULL)');
        $q('CREATE TABLE kpi_weights (
                id INTEGER PRIMARY KEY AUTO_INCREMENT, kpi_component_id INT NULL,
                position_id INT NULL, weight REAL NULL, weight_group TEXT NULL,
                effective_from TEXT NULL, effective_to TEXT NULL)');
        $q('CREATE TABLE kpi_targets (
                id INTEGER PRIMARY KEY AUTO_INCREMENT, kpi_component_id INT NULL,
                unit_id INT NULL, position_id INT NULL, context TEXT NULL,
                target_value REAL NULL, effective_from TEXT NULL, effective_to TEXT NULL)');
        $q('CREATE TABLE kpi_evaluations (
                id INTEGER PRIMARY KEY AUTO_INCREMENT, employee_id INT NULL,
                kpi_component_id INT NULL, evaluator_id INT NULL,
                evaluation_date TEXT NULL, raw_score REAL NULL,
                period_year INT NULL, period_month INT NULL)');
        $q('CREATE TABLE kpi_attendance_detail (id INTEGER PRIMARY KEY AUTO_INCREMENT, evaluation_id INT NULL)');
        $q('CREATE TABLE kpi_attendance_off (
                id INTEGER PRIMARY KEY AUTO_INCREMENT, employee_id INT NULL,
                evaluation_date TEXT NULL, period_month INT NULL,
                period_year INT NULL, evaluator_id INT NULL)');
    }

    private function sebarData(): void
    {
        $this->db->table('unit')->insert([
            'idunit' => self::UNIT, 'NAMA_UNIT' => 'ICLEAR Unit Test',
            'LOGO' => 'logo.png', 'JALAN_UNIT' => 'Jl. Test',
            'KABUPATEN_UNIT' => 'Banyuwangi', 'jenis' => 'pribadi',
            'is_trial' => 0, 'is_operational' => 1,
        ]);
        $this->db->table('jabatan')->insert([
            'ID_JABATAN' => 41, 'NAMA_JABATAN' => 'Kepala Toko',
            'ROLES_JABATAN' => '[]',
        ]);
        $this->db->table('akun')->insert([
            'ID_AKUN' => self::PEGAWAI, 'ID_UNIT' => self::UNIT,
            'ID_JABATAN' => 41, 'NAMA_AKUN' => 'Pegawai Test',
            'NOID' => 'NIP-900', 'ROLES' => '[]', 'ALAMAT' => 'Probolinggo',
            'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null,
        ]);
        $this->db->table('menu')->insert([
            'idmenu' => 1, 'urutan' => 1, 'nama_menu' => 'Dashboard',
            'roles' => '[]', 'url' => 'dashboard', 'show_menu' => 1,
        ]);

        $this->db->table('salary_components')->insertBatch([
            ['id' => 1, 'code' => 'GAJI_POKOK', 'name' => 'Gaji Pokok', 'type' => 'base', 'is_active' => 1],
        ]);
        $this->db->table('salary_structures')->insert([
            'position_id' => 41, 'salary_component_id' => 1, 'unit_id' => null,
            'base_value' => 1000000, 'calculation_type' => 'fixed',
            'effective_from' => '2026-01-01', 'effective_to' => null,
        ]);

        // Lembur +100rb, bon -40rb, kasbon -25rb pada Oktober 2026.
        $this->db->table('kas_keluar')->insertBatch([
            ['tanggal' => '2026-10-10', 'kategori_idkategori' => 10, 'deskripsi' => 'LEMBUR', 'jumlah' => 100000, 'penerima' => (string) self::PEGAWAI, 'idunit' => self::UNIT],
            ['tanggal' => '2026-10-11', 'kategori_idkategori' => 10, 'deskripsi' => 'BON', 'jumlah' => 40000, 'penerima' => (string) self::PEGAWAI, 'idunit' => self::UNIT],
        ]);
        $this->db->table('hutang_piutang')->insert([
            'deleted' => 0, 'scope' => 'active', 'sumber_tipe' => 'kasbon',
            'pihak_tipe' => 'pegawai', 'pihak_id' => self::PEGAWAI,
            'status' => 'belum', 'sisa' => 25000, 'unit_id' => self::UNIT,
        ]);
    }

    private function sesi(): void
    {
        $this->withSession([
            'logged_in'    => true,
            'ID_AKUN'      => self::PEGAWAI,
            'ID_UNIT'      => self::UNIT,
            'ID_JABATAN'   => 41,
            'NAMA'         => 'Tester',
            'NAMA_JABATAN' => 'Kepala Toko',
            'EMAIL'        => 'tester@example.com',
        ]);
    }

    private function angka(string $html, string $pola, string $label): int
    {
        $this->assertMatchesRegularExpression($pola, $html, $label . ' tidak ditemukan');
        preg_match($pola, $html, $m);

        return (int) str_replace('.', '', $m[1]);
    }

    public function testTakeHomePayEstimasiSamaDenganPenerimaanBersihSlip(): void
    {
        $this->sesi();

        $html = (string) $this->get(
            'penilaian_kinerja?bulan=10&tahun=2026&karyawan=' . self::PEGAWAI,
        )->getBody();
        $estimasi = $this->angka(
            $html,
            '/Take Home Pay \(Estimasi\)<\/span>\s*<h1[^>]*>\s*Rp\s*([\d.]+)/s',
            'Take Home Pay (Estimasi)',
        );

        $slip = (string) $this->get(
            'penilaian/slip_gaji/' . self::PEGAWAI . '?bulan=10&tahun=2026',
        )->getBody();
        $bersih = $this->angka(
            $slip,
            '/PENERIMAAN BERSIH \(A-B\).*?Rp([\d.]+)/s',
            'PENERIMAAN BERSIH (A-B)',
        );

        // 1.000.000 pokok + 350.000 penempatan + 100.000 lembur
        // - 40.000 bon - 25.000 kasbon.
        $this->assertSame(1385000, $bersih, 'patokan slip berubah');
        $this->assertSame($bersih, $estimasi, 'estimasi THP harus sama dengan slip');

        // Rincian potongan: satu angka gabungan, detail bon & kasbon tetap terlihat.
        $this->assertSame(100000, $this->angka(
            $html,
            '/Lembur<\/span>\s*<h6[^>]*>\s*\+\s*Rp\s*([\d.]+)/s',
            'Rincian Lembur',
        ));
        $this->assertSame(65000, $this->angka(
            $html,
            '/Potongan Pegawai<\/span>\s*<h6[^>]*>\s*[−-]\s*Rp\s*([\d.]+)/su',
            'Potongan Pegawai',
        ));
        $this->assertMatchesRegularExpression(
            '/Bon Rp 40\.000 · Kasbon Rp 25\.000/',
            $html,
            'rincian bon & kasbon harus tetap terlihat',
        );
    }
}
