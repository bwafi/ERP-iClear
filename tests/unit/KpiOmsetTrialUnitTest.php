<?php

namespace Tests\unit;

use App\Services\Kpi\KpiCalculationService;
use App\Services\Kpi\ManagerKpiService;
use App\Services\Kpi\SupervisorKpiService;
use App\Services\Marketing\DigitalMarketingKpiService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regresi KPI omzet: unit trial (is_trial = 1) tidak ikut komponen omzet
 * lintas cabang (omzet global, target cabang, omzet wilayah SPV), tetapi
 * tetap ikut komponen non-omzet dan KPI karyawannya sendiri.
 *
 * @internal
 */
final class KpiOmsetTrialUnitTest extends CIUnitTestCase
{
    private const UNIT_PROB = 971;
    private const UNIT_JEMBER = 972;
    private const UNIT_GENTENG = 975;
    private const UNIT_HO = 950;
    private const SPV = 991;
    private const COMPONENT = 9001;

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
            'unit', 'kpi_components', 'kpi_targets', 'penjualan',
            'detail_penjualan', 'spv_units',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE unit (
                idunit INT PRIMARY KEY, NAMA_UNIT VARCHAR(100) NULL,
                is_trial INT NULL)');
        $q('CREATE TABLE kpi_components (
                id INT PRIMARY KEY, code VARCHAR(50) NULL,
                name VARCHAR(150) NULL)');
        $q('CREATE TABLE kpi_targets (
                id INT PRIMARY KEY AUTO_INCREMENT, kpi_component_id INT NULL,
                unit_id INT NULL, position_id INT NULL, context VARCHAR(30) NULL,
                target_value DECIMAL(15,2) NULL, effective_from DATE NULL,
                effective_to DATE NULL)');
        $q('CREATE TABLE penjualan (
                idpenjualan INT PRIMARY KEY AUTO_INCREMENT,
                unit_idunit INT NULL, tanggal DATE NULL)');
        $q('CREATE TABLE detail_penjualan (
                iddetail_penjualan INT PRIMARY KEY AUTO_INCREMENT,
                penjualan_idpenjualan INT NULL, sub_total REAL NULL,
                hpp_penjualan REAL NULL)');
        $q('CREATE TABLE spv_units (spv_id INT NULL, unit_id INT NULL)');
    }

    private function sebarData(): void
    {
        $this->db->table('unit')->insertBatch([
            ['idunit' => self::UNIT_PROB, 'NAMA_UNIT' => 'ICLEAR Test Probolinggo', 'is_trial' => 0],
            ['idunit' => self::UNIT_JEMBER, 'NAMA_UNIT' => 'ICLEAR Test Jember', 'is_trial' => 0],
            ['idunit' => self::UNIT_GENTENG, 'NAMA_UNIT' => 'ICLEAR Test Genteng', 'is_trial' => 1],
            ['idunit' => self::UNIT_HO, 'NAMA_UNIT' => 'Head Office', 'is_trial' => 0],
        ]);

        $this->db->table('kpi_components')->insert([
            'id' => self::COMPONENT, 'code' => 'OMSET_CABANG', 'name' => 'Omset Cabang',
        ]);
        $this->db->table('kpi_targets')->insertBatch([
            ['kpi_component_id' => self::COMPONENT, 'unit_id' => self::UNIT_PROB, 'position_id' => 40, 'context' => 'default', 'target_value' => 100000000, 'effective_from' => '2024-01-01'],
            ['kpi_component_id' => self::COMPONENT, 'unit_id' => self::UNIT_GENTENG, 'position_id' => 40, 'context' => 'default', 'target_value' => 999000000, 'effective_from' => '2024-01-01'],
        ]);

        // Kedua cabang melampaui target: trial hanya boleh diabaikan di omzet.
        $this->db->table('penjualan')->insertBatch([
            ['idpenjualan' => 1, 'unit_idunit' => self::UNIT_PROB, 'tanggal' => '2026-11-10'],
            ['idpenjualan' => 2, 'unit_idunit' => self::UNIT_GENTENG, 'tanggal' => '2026-11-10'],
        ]);
        $this->db->table('detail_penjualan')->insertBatch([
            ['penjualan_idpenjualan' => 1, 'sub_total' => 150000000, 'hpp_penjualan' => 0],
            ['penjualan_idpenjualan' => 2, 'sub_total' => 1000000001, 'hpp_penjualan' => 0],
        ]);

        $this->db->table('spv_units')->insertBatch([
            ['spv_id' => self::SPV, 'unit_id' => self::UNIT_PROB],
            ['spv_id' => self::SPV, 'unit_id' => self::UNIT_GENTENG],
        ]);
    }

    public function testOmsetBranchUnitsMembuangUnitTrialDanHo(): void
    {
        $units = array_column((new KpiCalculationService())->omsetBranchUnits(), 'unit');

        $this->assertSame([self::UNIT_PROB, self::UNIT_JEMBER], $units);
    }

    public function testTargetOmzetGlobalMembuangTargetUnitTrial(): void
    {
        $target = (new DigitalMarketingKpiService())->omzetTarget('default', '2026-11-15');

        $this->assertSame(100000000.0, $target, 'target unit trial tidak boleh masuk omzet global');
    }

    public function testPencapaianCabangManagerHanyaMenghitungCabangNonTrial(): void
    {
        $data = (new ManagerKpiService())->componentData(
            'TARGET_CABANG',
            41,
            self::UNIT_HO,
            11,
            2026,
            'default',
            '2026-11-15',
        );

        $this->assertSame(100.0, $data['achievement']);
        $this->assertSame(1, $data['unit_count'], 'unit trial tidak masuk jumlah cabang');
        $this->assertSame([self::UNIT_PROB], array_column($data['cabang'], 'unit'));
    }

    public function testOmzetWilayahSpvMembuangUnitTrial(): void
    {
        $svc = new SupervisorKpiService();

        $this->assertSame(100.0, $svc->targetCabang(
            self::SPV,
            self::UNIT_HO,
            11,
            2026,
            'default',
            '2026-11-15',
        ));

        $detail = $svc->omzetDetail(
            self::SPV,
            self::UNIT_HO,
            11,
            2026,
            'default',
            '2026-11-15',
        );

        $this->assertNotNull($detail);
        $this->assertSame([self::UNIT_PROB], array_column($detail['cabang'], 'unit'));
    }

    public function testScopeNonOmzetSpvTetapMemuatUnitTrial(): void
    {
        $units = (new SupervisorKpiService())->scopeUnits(self::SPV, self::UNIT_HO);

        $this->assertSame([self::UNIT_PROB, self::UNIT_GENTENG], $units);
    }
}
