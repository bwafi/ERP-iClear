<?php

namespace Tests\unit;

use App\Services\Kpi\KpiCalculationService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Pengecualian rate insentif per karyawan per bulan
 * (incentive_overrides): dipakai untuk masa training, dan harus otomatis
 * kembali ke rate normal di bulan berikutnya.
 *
 * @internal
 */
final class IncentiveOverrideTest extends CIUnitTestCase
{
    private const BULAN = '10';
    private const TAHUN = '2026';

    private const TEK_A = 601;
    private const TEK_B = 602;
    private const TEK_C = 603;

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
            'unit', 'akun', 'kpi_components', 'kpi_targets', 'penjualan',
            'detail_penjualan', 'service',
            'incentive_groups', 'incentive_members', 'incentive_rules',
            'incentive_overrides',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT VARCHAR(100) NULL)');
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
                tanggal DATE NULL)');
        $q('CREATE TABLE detail_penjualan (
                iddetail_penjualan INT PRIMARY KEY AUTO_INCREMENT,
                penjualan_idpenjualan INT NULL, sub_total REAL NULL,
                hpp_penjualan REAL NULL)');
        $q('CREATE TABLE service (
                idservice INT PRIMARY KEY AUTO_INCREMENT, unit_idunit INT NULL,
                service_by INT NULL, refund_garansi_jumlah REAL NULL,
                refund_garansi_tanggal DATE NULL)');
        $q('CREATE TABLE incentive_groups (
                id INT PRIMARY KEY, code VARCHAR(50) NULL, name VARCHAR(255) NULL,
                description TEXT NULL, is_active INT NULL, effective_from DATE NULL,
                effective_to DATE NULL, created_at DATETIME NULL, updated_at DATETIME NULL)');
        $q('CREATE TABLE incentive_members (
                id INT PRIMARY KEY AUTO_INCREMENT, incentive_group_id INT NULL,
                employee_id INT NULL, unit_id INT NULL, effective_from DATE NULL,
                effective_to DATE NULL, is_active INT NULL)');
        $q('CREATE TABLE incentive_rules (
                id INT PRIMARY KEY AUTO_INCREMENT, incentive_group_id INT NULL,
                kpi_component_id INT NULL, incentive_name VARCHAR(255) NULL,
                calculation_type VARCHAR(30) NULL, base_value REAL NULL,
                division_method VARCHAR(30) NULL, minimum_achievement REAL NULL,
                effective_from DATE NULL, effective_to DATE NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL)');
        $q('CREATE TABLE incentive_overrides (
                id INT PRIMARY KEY AUTO_INCREMENT, employee_id INT NULL,
                unit_id INT NULL, period CHAR(7) NULL, rate DECIMAL(5,2) NULL,
                reason VARCHAR(255) NULL, created_by INT NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL)');
    }

    private function sebarData(): void
    {
        $this->db->table('unit')->insert(['idunit' => 5, 'NAMA_UNIT' => 'ICLEAR Genteng']);
        $this->db->table('kpi_components')->insert([
            'id' => 1, 'code' => 'OMSET_TOKO', 'name' => 'Omset Toko',
        ]);
        $this->db->table('kpi_targets')->insert([
            'kpi_component_id' => 1, 'unit_id' => 5, 'context' => 'default',
            'target_value' => 35000000, 'effective_from' => '2024-01-01',
        ]);
        $this->db->table('akun')->insertBatch([
            ['ID_AKUN' => self::TEK_A, 'ID_UNIT' => 5, 'ID_JABATAN' => 36, 'NAMA_AKUN' => 'Teknisi A', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
            ['ID_AKUN' => self::TEK_B, 'ID_UNIT' => 5, 'ID_JABATAN' => 36, 'NAMA_AKUN' => 'Teknisi B', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
            ['ID_AKUN' => self::TEK_C, 'ID_UNIT' => 5, 'ID_JABATAN' => 36, 'NAMA_AKUN' => 'Teknisi C', 'TERIMA_GAJI' => 1, 'STATUS_PEGAWAI' => 1, 'deleted' => null],
        ]);

        // Oktober 45jt, September 40jt (gross profit = sub_total - hpp).
        $this->sebarOmset('2026-10-07', 45000000);
        $this->sebarOmset('2026-09-07', 40000000);

        $this->db->table('incentive_groups')->insert([
            'id' => 1, 'code' => 'KEPALA_TOKO', 'is_active' => 1,
        ]);
        foreach ([self::TEK_A, self::TEK_B, self::TEK_C] as $id) {
            $this->db->table('incentive_members')->insert([
                'incentive_group_id' => 1, 'employee_id' => $id, 'unit_id' => 5,
                'effective_from' => '2024-01-01', 'effective_to' => null,
                'is_active' => 1,
            ]);
        }
        $this->db->table('incentive_rules')->insert([
            'incentive_group_id' => 1, 'kpi_component_id' => 1,
            'base_value' => 1, 'division_method' => 'per_member',
            'minimum_achievement' => 100, 'effective_from' => '2024-01-01',
            'effective_to' => null,
        ]);
    }

    private function sebarOmset(string $tanggal, float $subTotal): void
    {
        $this->db->table('penjualan')->insert([
            'unit_idunit' => 5, 'tanggal' => $tanggal,
        ]);
        $penjualanId = $this->db->insertID();

        $this->db->table('detail_penjualan')->insert([
            'penjualan_idpenjualan' => $penjualanId,
            'sub_total' => $subTotal,
            'hpp_penjualan' => 0,
        ]);
    }

    private function override(int $employeeId, float $rate, string $period): void
    {
        $this->db->table('incentive_overrides')->insert([
            'employee_id' => $employeeId, 'unit_id' => 5,
            'period' => $period, 'rate' => $rate, 'reason' => 'test',
        ]);
    }

    private function insentif(int $employeeId, string $bulan = self::BULAN): float
    {
        return (new KpiCalculationService())->computeIncentive(
            $employeeId,
            5,
            $bulan,
            self::TAHUN,
            sprintf('%s-%s-15', self::TAHUN, $bulan),
        );
    }

    public function testTanpaOverrideTetapSatuPersen(): void
    {
        $this->assertSame(450000.0, $this->insentif(self::TEK_A));
        $this->assertSame(450000.0, $this->insentif(self::TEK_B));
    }

    public function testOverrideMenaikkanRateDanMenolkanYangTraining(): void
    {
        $this->override(self::TEK_A, 1.50, '2026-10');
        $this->override(self::TEK_B, 0.00, '2026-10');

        $this->assertSame(675000.0, $this->insentif(self::TEK_A));
        $this->assertSame(0.0, $this->insentif(self::TEK_B));
        // Karyawan tanpa override tidak terpengaruh.
        $this->assertSame(450000.0, $this->insentif(self::TEK_C));
    }

    public function testOverrideHanyaBerlakuDiBulanYangDicatat(): void
    {
        $this->override(self::TEK_A, 1.50, '2026-10');
        $this->override(self::TEK_B, 0.00, '2026-10');

        // September: override Oktober tidak berlaku → kembali 1%.
        $this->assertSame(400000.0, $this->insentif(self::TEK_A, '09'));
        $this->assertSame(400000.0, $this->insentif(self::TEK_B, '09'));
    }
}
