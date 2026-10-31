<?php

namespace Tests;

use App\Services\Payroll\SalaryCalculationService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Gaji pegawai Probation (JENIS_PEGAWAI = 2) hanya gaji pokok prorata:
 * gaji pokok / 30 x hari hadir. Hari hadir diambil dari tanggal unik
 * evaluasi KEHADIRAN pada bulan payroll.
 *
 * @internal
 */
final class PayrollProbationSalaryTest extends CIUnitTestCase
{
    private const PROBATION = 9001;
    private const TETAP = 9002;
    private const JABATAN = 42;
    private const UNIT = 1;

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
            'akun', 'kpi_components', 'kpi_evaluations',
            'salary_components', 'salary_structures',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN VARCHAR(150) NULL, JENIS_PEGAWAI INT NULL,
                STATUS_PEGAWAI INT NULL, deleted INT NULL)');
        $q('CREATE TABLE kpi_components (
                id INT PRIMARY KEY, code VARCHAR(50) NULL, name VARCHAR(100) NULL)');
        $q('CREATE TABLE kpi_evaluations (
                id INT PRIMARY KEY AUTO_INCREMENT, employee_id INT NULL,
                kpi_component_id INT NULL, evaluation_date DATE NULL,
                period_year INT NULL, period_month INT NULL)');
        $q('CREATE TABLE salary_components (
                id INT PRIMARY KEY, code VARCHAR(50) NULL, name VARCHAR(100) NULL,
                type VARCHAR(50) NULL)');
        $q('CREATE TABLE salary_structures (
                id INT PRIMARY KEY AUTO_INCREMENT, position_id INT NULL,
                salary_component_id INT NULL, unit_id INT NULL,
                context VARCHAR(20) NULL DEFAULT \'default\',
                base_value DECIMAL(15,2) NULL, calculation_type VARCHAR(30) NULL,
                multiplier DECIMAL(5,2) NULL, effective_from DATE NULL,
                effective_to DATE NULL)');
    }

    private function sebarData(): void
    {
        $this->db->table('akun')->insertBatch([
            [
                'ID_AKUN' => self::PROBATION, 'ID_UNIT' => self::UNIT,
                'ID_JABATAN' => self::JABATAN, 'NAMA_AKUN' => 'Ervina Test',
                'JENIS_PEGAWAI' => 2, 'STATUS_PEGAWAI' => 1, 'deleted' => null,
            ],
            [
                'ID_AKUN' => self::TETAP, 'ID_UNIT' => self::UNIT,
                'ID_JABATAN' => self::JABATAN, 'NAMA_AKUN' => 'Pegawai Tetap',
                'JENIS_PEGAWAI' => 0, 'STATUS_PEGAWAI' => 1, 'deleted' => null,
            ],
        ]);

        $this->db->table('kpi_components')->insert([
            'id' => 9, 'code' => 'KEHADIRAN', 'name' => 'Kehadiran',
        ]);

        // 8 tanggal hadir; satu tanggal punya dua sesi (baris ganda).
        $tanggal = ['01', '02', '03', '03', '04', '05', '06', '07', '08'];
        foreach ($tanggal as $i => $tgl) {
            $this->db->table('kpi_evaluations')->insert([
                'employee_id'      => self::PROBATION,
                'kpi_component_id' => 9,
                'evaluation_date'  => '2026-10-' . $tgl,
                'period_year'      => 2026,
                'period_month'     => 10,
            ]);
        }

        $this->db->table('salary_components')->insertBatch([
            ['id' => 1, 'code' => 'GAJI_POKOK', 'name' => 'Gaji Pokok', 'type' => 'pokok'],
            ['id' => 2, 'code' => 'TUNJANGAN_KINERJA', 'name' => 'Tunjangan Kinerja', 'type' => 'tunjangan'],
            ['id' => 3, 'code' => 'TUNJANGAN_ABSEN', 'name' => 'Tunjangan Absensi', 'type' => 'tunjangan'],
        ]);
        $this->db->table('salary_structures')->insertBatch([
            [
                'position_id' => self::JABATAN, 'salary_component_id' => 1, 'unit_id' => null,
                'context' => 'default', 'base_value' => 1500000, 'calculation_type' => 'fixed',
                'multiplier' => null, 'effective_from' => '2024-01-01', 'effective_to' => null,
            ],
            [
                'position_id' => self::JABATAN, 'salary_component_id' => 2, 'unit_id' => null,
                'context' => 'default', 'base_value' => 250000, 'calculation_type' => 'percent_of_kpi',
                'multiplier' => 1, 'effective_from' => '2024-01-01', 'effective_to' => null,
            ],
            [
                'position_id' => self::JABATAN, 'salary_component_id' => 3, 'unit_id' => null,
                'context' => 'default', 'base_value' => 250000, 'calculation_type' => 'percent_of_kpi',
                'multiplier' => 1, 'effective_from' => '2024-01-01', 'effective_to' => null,
            ],
        ]);
    }

    private function hitung(int $pegawai): array
    {
        return (new SalaryCalculationService())->calculateSalary(
            $pegawai,
            self::JABATAN,
            self::UNIT,
            'gaji',
            ['TUNJANGAN_KINERJA' => 100, 'TUNJANGAN_ABSEN' => 100],
            350000,
            500000,
            100000,
            0.0,
            '2026-10-15'
        );
    }

    public function testProbationHanyaGajiPokokProrataHariHadir(): void
    {
        $result = $this->hitung(self::PROBATION);

        // 1.500.000 / 30 x 8 hari (tanggal 03 dihitung sekali).
        $this->assertSame(8, $result['hari_hadir']);
        $this->assertTrue($result['probation']);
        $this->assertSame(400000.0, (float) $result['total_gaji']);
        $this->assertSame(400000.0, (float) $result['subtotal_before_incentive']);
        $this->assertSame(0.0, (float) $result['placement_allowance']);
        $this->assertSame(0.0, (float) $result['incentive']);
        $this->assertSame(0.0, (float) $result['lembur']);

        $this->assertCount(1, $result['components']);
        $this->assertSame('GAJI_POKOK', $result['components'][0]['component_code']);
        $this->assertSame(400000.0, (float) $result['components'][0]['amount']);
    }

    public function testProbationTanpaAbsenTidakDapatGaji(): void
    {
        $this->db->table('kpi_evaluations')->where('employee_id', self::PROBATION)->delete();

        $result = $this->hitung(self::PROBATION);

        $this->assertSame(0, $result['hari_hadir']);
        $this->assertSame(0.0, (float) $result['total_gaji']);
    }

    public function testHariHadirDibatasiTigaPuluh(): void
    {
        $this->db->table('kpi_evaluations')->where('employee_id', self::PROBATION)->delete();
        for ($i = 1; $i <= 31; $i++) {
            $this->db->table('kpi_evaluations')->insert([
                'employee_id'      => self::PROBATION,
                'kpi_component_id' => 9,
                'evaluation_date'  => sprintf('2026-10-%02d', $i),
                'period_year'      => 2026,
                'period_month'     => 10,
            ]);
        }

        $result = $this->hitung(self::PROBATION);

        $this->assertSame(30, $result['hari_hadir']);
        $this->assertSame(1500000.0, (float) $result['total_gaji']);
    }

    public function testCatatanProbationMenjelaskanRumus(): void
    {
        $generator = new \App\Services\Payroll\PayrollGenerator();
        $method    = new \ReflectionMethod($generator, 'susunCatatan');

        $catatan = $method->invoke($generator, [
            'probation' => true,
            'hari_hadir' => 8,
            'gaji_pokok_bulanan' => 1500000,
            'subtotal_before_incentive' => 400000,
            'components' => [],
        ], 0.0);

        $this->assertSame(
            'Probation: Gaji Pokok Rp 1.500.000 ÷ 30 × 8 hari hadir = Rp 400.000. Kasbon dipotong saat dibayar.',
            $catatan
        );
    }

    public function testPegawaiTetapTetapMemakaiKomponenLengkap(): void
    {
        $result = $this->hitung(self::TETAP);

        $this->assertFalse($result['probation'] ?? false);
        $this->assertCount(3, $result['components']);
        $this->assertSame(
            1500000.0 + 250000.0 + 250000.0 + 350000.0 + 500000.0 + 100000.0,
            (float) $result['total_gaji']
        );
    }
}
