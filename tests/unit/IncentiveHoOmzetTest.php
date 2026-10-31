<?php

namespace Tests\unit;

use App\Services\Kpi\KpiCalculationService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Insentif HO (unit 50) Oktober 2026+:
 *   - Basis omzet = gross profit (`SUM(sub_total - hpp_penjualan)`, sama dengan
 *     Rincian Omset Cabang).
 *   - Unit trial (`unit.is_trial = 1`) tidak ikut pool cabang maupun global.
 *   - Pool cabang = rate × Σ GP cabang yang capai target OMSET_TOKO
 *     (rate 1% bila < 3 cabang capai, 1,5% bila ≥ 3 cabang capai).
 *   - Pool global = 1,5% × Σ GP semua cabang non-trial bila Σ GP ≥
 *     Σ target OMSET_TOKO cabang non-trial.
 *   - Individual = (pool cabang + pool global) / jumlah karyawan HO aktif
 *     yang menerima gaji.
 *
 * Fixture terisolasi, tidak memakai data produksi.
 *
 * @internal
 */
final class IncentiveHoOmzetTest extends CIUnitTestCase
{
    private const BULAN = '10';
    private const TAHUN = '2026';
    private const TANGGAL = '2026-10-15';

    private const HO_A = 701;
    private const HO_B = 702;
    private const HO_C = 703;
    private const HO_NONAKTIF = 704;
    private const HO_TANPA_GAJI = 705;

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
        $this->sebarDasar();
    }

    protected function tearDown(): void
    {
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        foreach (['unit', 'akun', 'kpi_components', 'kpi_targets', 'penjualan', 'detail_penjualan', 'service'] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT VARCHAR(100) NULL, is_trial INT NULL)');
        $q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN VARCHAR(150) NULL, ALAMAT TEXT NULL, TERIMA_GAJI INT NULL,
                STATUS_PEGAWAI INT NULL, JENIS_PEGAWAI INT NULL, deleted INT NULL)');
        $q('CREATE TABLE kpi_components (
                id INT PRIMARY KEY, code VARCHAR(50) NULL, name VARCHAR(150) NULL)');
        $q('CREATE TABLE kpi_targets (
                id INT PRIMARY KEY AUTO_INCREMENT, kpi_component_id INT NULL,
                unit_id INT NULL, position_id INT NULL, context VARCHAR(30) NULL,
                target_value DECIMAL(15,2) NULL, effective_from DATE NULL,
                effective_to DATE NULL)');
        $q('CREATE TABLE penjualan (
                idpenjualan INT PRIMARY KEY AUTO_INCREMENT, unit_idunit INT NULL,
                tanggal DATE NULL, total_penjualan DECIMAL(15,2) NULL)');
        $q('CREATE TABLE detail_penjualan (
                iddetail_penjualan INT PRIMARY KEY AUTO_INCREMENT,
                penjualan_idpenjualan INT NULL, sub_total REAL NULL,
                hpp_penjualan REAL NULL)');
        $q('CREATE TABLE service (
                idservice INT PRIMARY KEY AUTO_INCREMENT, unit_idunit INT NULL,
                refund_garansi_jumlah REAL NULL, refund_garansi_tanggal DATE NULL)');
    }

    private function sebarDasar(): void
    {
        $this->db->table('unit')->insert(['idunit' => 50, 'NAMA_UNIT' => 'Head Office', 'is_trial' => 0]);
        $this->db->table('kpi_components')->insert([
            'id' => 1, 'code' => 'OMSET_TOKO', 'name' => 'Omset Toko',
        ]);

        $this->db->table('akun')->insertBatch([
            ['ID_AKUN' => self::HO_A, 'ID_UNIT' => 50, 'ID_JABATAN' => 41, 'NAMA_AKUN' => 'HO A', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
            ['ID_AKUN' => self::HO_B, 'ID_UNIT' => 50, 'ID_JABATAN' => 40, 'NAMA_AKUN' => 'HO B', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => 0],
            ['ID_AKUN' => self::HO_NONAKTIF, 'ID_UNIT' => 50, 'ID_JABATAN' => 41, 'NAMA_AKUN' => 'HO Nonaktif', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 0, 'deleted' => null],
            ['ID_AKUN' => self::HO_TANPA_GAJI, 'ID_UNIT' => 50, 'ID_JABATAN' => 41, 'NAMA_AKUN' => 'HO Tanpa Gaji', 'TERIMA_GAJI' => 0, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
        ]);
    }

    /**
     * Satu cabang: omzet (gross profit) bulan ini + target OMSET_TOKO.
     * `$target = null` = cabang tanpa target (tetap ikut omzet global).
     * `$hpp` membuat total penjualan berbeda dari omzet agar sumber angkanya
     * bisa diuji.
     */
    private function cabang(int $unitId, float $omzet, ?float $target, int $trial = 0, float $hpp = 0.0): void
    {
        $this->db->table('unit')->insert([
            'idunit' => $unitId, 'NAMA_UNIT' => 'Cabang ' . $unitId, 'is_trial' => $trial,
        ]);

        if ($target !== null) {
            $this->db->table('kpi_targets')->insert([
                'kpi_component_id' => 1, 'unit_id' => $unitId, 'position_id' => null,
                'context' => 'default', 'target_value' => $target,
                'effective_from' => '2024-01-01', 'effective_to' => null,
            ]);
        }

        $this->db->table('penjualan')->insert([
            'unit_idunit' => $unitId, 'tanggal' => '2026-10-05',
            'total_penjualan' => $omzet + $hpp,
        ]);
        $this->db->table('detail_penjualan')->insert([
            'penjualan_idpenjualan' => $this->db->insertID(),
            'sub_total' => $omzet + $hpp,
            'hpp_penjualan' => $hpp,
        ]);
    }

    private function insentif(int $employeeId): float
    {
        return (new KpiCalculationService())->computeIncentive(
            $employeeId,
            50,
            self::BULAN,
            self::TAHUN,
            self::TANGGAL,
        );
    }

    public function testTidakAdaCabangCapaiTargetPoolCabangNol(): void
    {
        $this->cabang(1, 40000000, 50000000);

        // Cabang & global sama-sama tidak capai (40jt < 50jt).
        $this->assertSame(0.0, $this->insentif(self::HO_A));
    }

    public function testSatuCabangCapaiTargetMemakaiSatuPersen(): void
    {
        $this->cabang(1, 50000000, 50000000);
        $this->cabang(2, 0, 50000000); // target menahan pool global

        // 1% × 50jt = 500rb; 2 penerima → 250rb.
        $this->assertSame(250000.0, $this->insentif(self::HO_A));
    }

    public function testDuaCabangCapaiTargetMemakaiSatuPersen(): void
    {
        $this->cabang(1, 50000000, 50000000);
        $this->cabang(2, 60000000, 60000000);
        $this->cabang(3, 0, 50000000);

        // 1% × 110jt = 1,1jt; 2 penerima → 550rb.
        $this->assertSame(550000.0, $this->insentif(self::HO_A));
    }

    public function testTigaCabangCapaiTargetSemuaMemakaiSatuSetengahPersen(): void
    {
        $this->cabang(1, 50000000, 50000000);
        $this->cabang(2, 60000000, 60000000);
        $this->cabang(3, 70000000, 70000000);
        $this->cabang(4, 0, 50000000);

        // 1,5% × 180jt = 2,7jt; 2 penerima → 1,35jt (bukan 1% + 1,5%).
        $this->assertSame(1350000.0, $this->insentif(self::HO_A));
    }

    public function testEmpatCabangCapaiTargetSemuaMemakaiSatuSetengahPersen(): void
    {
        $this->cabang(1, 50000000, 50000000);
        $this->cabang(2, 50000000, 50000000);
        $this->cabang(3, 50000000, 50000000);
        $this->cabang(4, 50000000, 50000000);
        $this->cabang(5, 0, 50000000);

        // 1,5% × 200jt = 3jt; 2 penerima → 1,5jt.
        $this->assertSame(1500000.0, $this->insentif(self::HO_A));
    }

    public function testCabangTidakCapaiTargetTidakMenyumbangPool(): void
    {
        $this->cabang(1, 50000000, 50000000);
        $this->cabang(2, 30000000, 50000000); // belum capai
        $this->cabang(3, 0, 50000000);

        // Hanya unit 1: 1% × 50jt = 500rb; 2 penerima → 250rb.
        $this->assertSame(250000.0, $this->insentif(self::HO_A));
    }

    public function testGlobalCapaiTargetMenambahSatuSetengahPersenOmzetGlobal(): void
    {
        $this->cabang(1, 60000000, 50000000);

        // Cabang 1% × 60jt = 600rb; global 1,5% × 60jt = 900rb;
        // total 1,5jt; 2 penerima → 750rb.
        $this->assertSame(750000.0, $this->insentif(self::HO_A));
    }

    public function testGlobalTidakCapaiTargetPoolGlobalNol(): void
    {
        $this->cabang(1, 40000000, 50000000);

        $this->assertSame(0.0, $this->insentif(self::HO_A));
    }

    public function testCabangCapaiTargetTetapiGlobalTidakHanyaPoolCabang(): void
    {
        $this->cabang(1, 60000000, 50000000);
        $this->cabang(2, 0, 50000000);

        // 1% × 60jt = 600rb; 2 penerima → 300rb.
        $this->assertSame(300000.0, $this->insentif(self::HO_A));
    }

    public function testGlobalCapaiTargetTanpaCabangCapaiTargetHanyaPoolGlobal(): void
    {
        // Unit 1 tidak capai; unit 2 tanpa target menyumbang omzet global.
        $this->cabang(1, 40000000, 50000000);
        $this->cabang(2, 100000000, null);

        // Cabang 0; global 1,5% × 140jt = 2,1jt; 2 penerima → 1,05jt.
        $this->assertSame(1050000.0, $this->insentif(self::HO_A));
    }

    public function testKeduaKomponenTercapaiPoolCabangPlusPoolGlobal(): void
    {
        $this->cabang(1, 50000000, 50000000);
        $this->cabang(2, 60000000, 60000000);
        $this->cabang(3, 70000000, 70000000);

        // Cabang 1,5% × 180jt = 2,7jt; global 1,5% × 180jt = 2,7jt;
        // total 5,4jt; 2 penerima → 2,7jt.
        $this->assertSame(2700000.0, $this->insentif(self::HO_A));
    }

    public function testPembagianMemakaiJumlahPenerimaHoYangBenar(): void
    {
        $this->db->table('akun')->insert([
            'ID_AKUN' => self::HO_C, 'ID_UNIT' => 50, 'ID_JABATAN' => 41,
            'NAMA_AKUN' => 'HO C', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1,
            'deleted' => null,
        ]);
        $this->cabang(1, 50000000, 50000000);
        $this->cabang(2, 0, 50000000);

        // Pool 500rb dibagi 3 penerima aktif (nonaktif & tanpa gaji tidak ikut).
        $this->assertEqualsWithDelta(166666.6667, $this->insentif(self::HO_A), 0.01);
        $this->assertSame(0.0, $this->insentif(self::HO_NONAKTIF));
        $this->assertSame(0.0, $this->insentif(self::HO_TANPA_GAJI));
    }

    public function testSumberOmzetAdalahGrossProfitBukanTotalPenjualan(): void
    {
        // Penjualan 100jt, HPP 40jt → omzet 60jt < target 80jt (tidak capai).
        // Bila memakai total penjualan, cabang akan dianggap capai.
        $this->cabang(1, 60000000, 80000000, 0, 40000000);

        $this->assertSame(0.0, $this->insentif(self::HO_A));
    }

    public function testCabangTrialTidakMasukPoolCabangMaupunGlobal(): void
    {
        // Satu-satunya cabang capai adalah unit trial → tidak ada pool.
        $this->cabang(5, 60000000, 50000000, 1);

        $this->assertSame(0.0, $this->insentif(self::HO_A));
    }
}
