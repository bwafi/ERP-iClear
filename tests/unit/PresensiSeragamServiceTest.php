<?php

namespace Tests\unit;

use App\Services\Kpi\PresensiSeragamService;
use CodeIgniter\Test\CIUnitTestCase;

class PresensiSeragamServiceTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->buatSkema();
    }

    protected function tearDown(): void
    {
        foreach (['kpi_evaluations', 'kpi_components', 'spv_units', 'presensi', 'akun'] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }
        $this->db->dataCache['table_names'] = [];
        $this->db->dataCache['field_names'] = [];
        $this->db->setPrefix($this->prefixAsli);

        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = function (string $sql): void {
            $this->db->query($sql);
        };

        foreach (['kpi_evaluations', 'kpi_components', 'spv_units', 'presensi', 'akun'] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE presensi (
                idpresensi INT PRIMARY KEY AUTO_INCREMENT, akun_idakun INT NOT NULL,
                waktu_masuk DATETIME NULL)');

        $q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN TEXT NULL, STATUS_PEGAWAI INT NULL)');
        $q('CREATE TABLE spv_units (
                id INT PRIMARY KEY AUTO_INCREMENT, spv_id INT NOT NULL, unit_id INT NOT NULL,
                UNIQUE KEY uniq_spv_unit (spv_id, unit_id))');
        $q('CREATE TABLE kpi_components (
                id INT PRIMARY KEY AUTO_INCREMENT, code VARCHAR(50) NOT NULL, name VARCHAR(255) NULL,
                is_active TINYINT NULL)');
        $q('CREATE TABLE kpi_evaluations (
                id INT PRIMARY KEY AUTO_INCREMENT, employee_id INT NOT NULL,
                kpi_component_id INT NOT NULL, evaluator_id INT NOT NULL, evaluation_date DATE NULL,
                raw_score DECIMAL(5,2) NOT NULL, max_score DECIMAL(5,2) NOT NULL DEFAULT 5,
                normalized_score DECIMAL(5,2) NOT NULL, weighted_score DECIMAL(5,2) NOT NULL DEFAULT 0,
                notes TEXT NULL, period_year INT NOT NULL, period_month INT NOT NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL,
                UNIQUE KEY uq_emp_kpi_eval_date (employee_id, kpi_component_id, evaluator_id, evaluation_date))');

        $q("INSERT INTO kpi_components (id, code, name, is_active) VALUES (11, 'SERAGAM', 'Seragam', 1)");

        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, STATUS_PEGAWAI) VALUES
            (1, 50, 1,  'Admin Root', 1),
            (2, 50, 0,  'Finance', 1),
            (3, 50, 34, 'Manager', 1),
            (4, 2,  40, 'SPV', 1),
            (5, 2,  35, 'Admin Cabang', 1)");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, STATUS_PEGAWAI) VALUES
            (10, 2, 36, 'Teknisi', 1),
            (11, 50, 43, 'Kepala Divisi', 1),
            (12, 2, 34, 'Manager Cabang', 1)");

        $q("INSERT INTO spv_units (spv_id, unit_id) VALUES (4, 2)");

        $q("INSERT INTO presensi (akun_idakun, waktu_masuk) VALUES
            (10, '2026-11-02 08:50:00'),
            (11, '2026-11-02 08:50:00'),
            (12, '2026-11-02 08:50:00')");
    }

    private function evaluasi(): array
    {
        return $this->db->table('kpi_evaluations')->get()->getResultArray();
    }

    private function sisipManual(int $employeeId, string $date, float $score): void
    {
        $this->db->table('kpi_evaluations')->insert([
            'employee_id'      => $employeeId,
            'kpi_component_id' => 11,
            'evaluator_id'     => 2,
            'evaluation_date'  => $date,
            'raw_score'        => $score,
            'max_score'        => 5,
            'normalized_score' => $score / 5 * 100,
            'weighted_score'   => 0,
            'notes'            => 'input manual lama',
            'period_year'      => 2026,
            'period_month'     => 11,
        ]);
    }

    public function testBobotSeragamDuaIdCardDuaSepatuSatu(): void
    {
        $svc = new PresensiSeragamService(1);

        $this->assertSame(2.0, $svc->simpan(10, '2026-11-02', true, false, false)['score']);
        $this->assertSame(2.0, $svc->simpan(10, '2026-11-02', false, false, true)['score']);
        $this->assertSame(1.0, $svc->simpan(10, '2026-11-02', false, true, false)['score']);
    }

    public function testSeragamDanSepatuTanpaIdCardBernilaiTiga(): void
    {
        $hasil = (new PresensiSeragamService(1))->simpan(10, '2026-11-02', true, true, false);

        $this->assertSame(3.0, $hasil['score']);
        $rows = $this->evaluasi();
        $this->assertCount(1, $rows);
        $this->assertSame(3.0, (float) $rows[0]['raw_score']);
        $this->assertSame(60.0, (float) $rows[0]['normalized_score']);
        $this->assertStringStartsWith('SERAGAM_ABSEN;S=1;P=1;I=0', (string) $rows[0]['notes']);
    }

    public function testKetigaCentangBernilaiLimaDanTidakAdaBernilaiNol(): void
    {
        $svc = new PresensiSeragamService(1);
        $this->assertSame(5.0, $svc->simpan(10, '2026-11-02', true, true, true)['score']);
        $this->assertSame(0.0, $svc->simpan(11, '2026-11-02', false, false, false)['score']);
    }

    public function testAdminRootDanFinanceBolehSemuaUnit(): void
    {
        (new PresensiSeragamService(1))->simpan(10, '2026-11-02', true, true, true);
        (new PresensiSeragamService(2))->simpan(11, '2026-11-02', true, false, false);

        $this->assertCount(2, $this->evaluasi());
    }

    public function testManagerHanyaBolehUnitHo(): void
    {
        $svc = new PresensiSeragamService(3);

        $this->assertTrue($svc->bolehMenilai(50, 43));
        $this->assertFalse($svc->bolehMenilai(2, 36));

        $svc->simpan(11, '2026-11-02', true, true, true);
        $this->assertCount(1, $this->evaluasi());
    }

    public function testSpvHanyaBolehUnitBinaan(): void
    {
        $svc = new PresensiSeragamService(4);

        $this->assertTrue($svc->bolehMenilai(2, 36));
        $this->assertFalse($svc->bolehMenilai(50, 43));
    }

    public function testRoleLainDitolak(): void
    {
        $this->expectException(\RuntimeException::class);

        (new PresensiSeragamService(5))->simpan(10, '2026-11-02', true, true, true);
    }

    public function testPegawaiManagerTidakDinilaiLewatChecklist(): void
    {
        $this->expectException(\RuntimeException::class);

        (new PresensiSeragamService(1))->simpan(12, '2026-11-02', true, true, true);
    }

    public function testSimpanUlangMenggantiBarisBukanMenambah(): void
    {
        $svc = new PresensiSeragamService(1);
        $svc->simpan(10, '2026-11-02', true, true, false);

        (new PresensiSeragamService(2))->simpan(10, '2026-11-02', true, true, true);

        $rows = $this->evaluasi();
        $this->assertCount(1, $rows);
        $this->assertSame(5.0, (float) $rows[0]['raw_score']);
        $this->assertSame(2, (int) $rows[0]['evaluator_id']);
    }

    public function testTanggalTanpaPresensiDitolak(): void
    {
        $this->expectException(\RuntimeException::class);

        (new PresensiSeragamService(1))->simpan(10, '2026-11-03', true, true, true);
    }

    public function testNilaiManualLamaTidakTertimpaTanpaKonfirmasi(): void
    {
        $this->sisipManual(10, '2026-11-02', 3.0);

        try {
            (new PresensiSeragamService(1))->simpan(10, '2026-11-02', true, true, true);
            $this->fail('Nilai manual lama seharusnya menolak penimpaan.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Nilai ulang dari foto', $e->getMessage());
        }

        $rows = $this->evaluasi();
        $this->assertCount(1, $rows);
        $this->assertSame(3.0, (float) $rows[0]['raw_score']);
        $this->assertSame('input manual lama', $rows[0]['notes']);
    }

    public function testNilaiManualLamaBolehDitimpaDenganKonfirmasi(): void
    {
        $this->sisipManual(10, '2026-11-02', 3.0);

        $hasil = (new PresensiSeragamService(1))->simpan(10, '2026-11-02', true, true, true, true);

        $this->assertSame(5.0, $hasil['score']);
        $rows = $this->evaluasi();
        $this->assertCount(1, $rows);
        $this->assertSame(5.0, (float) $rows[0]['raw_score']);
        $this->assertStringStartsWith('SERAGAM_ABSEN', (string) $rows[0]['notes']);
    }

    public function testPetaNilaiMenandaiNilaiManualLama(): void
    {
        $this->sisipManual(10, '2026-11-02', 3.0);

        $peta = (new PresensiSeragamService(1))->petaNilai([
            (object) ['akun_idakun' => 10, 'tanggal' => '2026-11-02'],
        ]);

        $this->assertTrue($peta['10|2026-11-02']['manual']);
        $this->assertSame(3.0, $peta['10|2026-11-02']['score']);
        $this->assertFalse($peta['10|2026-11-02']['seragam']);
    }

    public function testPetaNilaiMenguraiCentang(): void
    {
        (new PresensiSeragamService(1))->simpan(10, '2026-11-02', true, false, true);

        $peta = (new PresensiSeragamService(1))->petaNilai([
            (object) ['akun_idakun' => 10, 'tanggal' => '2026-11-02'],
        ]);

        $this->assertSame(4.0, $peta['10|2026-11-02']['score']);
        $this->assertTrue($peta['10|2026-11-02']['seragam']);
        $this->assertFalse($peta['10|2026-11-02']['sepatu']);
        $this->assertTrue($peta['10|2026-11-02']['idcard']);
    }
}
