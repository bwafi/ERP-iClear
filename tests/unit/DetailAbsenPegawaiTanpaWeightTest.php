<?php

namespace Tests\unit;

use App\Services\Kpi\AttendanceAggregationService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regresi: "Detail Penilaian Absen" untuk pegawai tanpa mapping bobot absen.
 *
 * Bug sebelum perbaikan: KpiCalculationService::calculateForSalary() menyusun
 * detail_absen dari tabel kpi_weights (weight_group='absen'). Pegawai dengan
 * ID_JABATAN = 0 (Finance — Ira Kurniawati) tidak punya baris kpi_weights sama
 * sekali, sehingga rincian absen kosong walau skor absen (skor_total2) tetap
 * terhitung dari kpi_evaluations.
 *
 * Perbaikan: detail_absen dibangun dari komponen yang SUDAH dihitung
 * AttendanceAggregationService (bobot tunggal 40/20/20/20) via
 * detailRows(), bukan lagi dari kpi_weights.
 *
 * @internal
 */
final class DetailAbsenPegawaiTanpaWeightTest extends CIUnitTestCase
{
    private const PERIODE_BULAN = '09';
    private const PERIODE_TAHUN = '2026';

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
        $q = function (string $sql): void {
            $this->db->query($sql);
        };

        foreach ([
            'akun', 'kpi_components', 'kpi_evaluations',
            'kpi_attendance_detail', 'kpi_attendance_off', 'kpi_weights',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY,
                ID_JABATAN INT NULL,
                ID_UNIT INT NULL,
                STATUS_PEGAWAI INT NULL,
                NAMA_AKUN VARCHAR(150) NULL,
                ALAMAT TEXT NULL)');

        $q('CREATE TABLE kpi_components (
                id INT PRIMARY KEY,
                code VARCHAR(50) NULL,
                name VARCHAR(150) NULL,
                type VARCHAR(30) NULL,
                is_active INT NULL)');

        $q('CREATE TABLE kpi_evaluations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                employee_id INT NULL,
                kpi_component_id INT NULL,
                evaluator_id INT NULL,
                evaluation_date DATE NULL,
                raw_score DECIMAL(5,2) NULL,
                period_year INT NULL,
                period_month INT NULL)');

        $q('CREATE TABLE kpi_attendance_detail (
                id INT PRIMARY KEY AUTO_INCREMENT,
                evaluation_id INT NULL)');

        $q('CREATE TABLE kpi_attendance_off (
                id INT PRIMARY KEY AUTO_INCREMENT,
                employee_id INT NULL,
                evaluation_date DATE NULL,
                period_month INT NULL,
                period_year INT NULL,
                evaluator_id INT NULL)');

        $q('CREATE TABLE kpi_weights (
                id INT PRIMARY KEY AUTO_INCREMENT,
                kpi_component_id INT NULL,
                position_id INT NULL,
                weight DECIMAL(5,2) NULL,
                weight_group VARCHAR(20) NULL,
                effective_from DATE NULL,
                effective_to DATE NULL)');
    }

    private function sebarData(): void
    {
        // Komponen absen (id mengikuti seed produksi).
        $this->db->table('kpi_components')->insertBatch([
            ['id' => 9,  'code' => 'KEHADIRAN',     'name' => 'Kehadiran',     'type' => 'manual', 'is_active' => 1],
            ['id' => 10, 'code' => 'KEBERSIHAN',    'name' => 'Kebersihan',    'type' => 'manual', 'is_active' => 1],
            ['id' => 11, 'code' => 'SERAGAM',       'name' => 'Seragam',       'type' => 'manual', 'is_active' => 1],
            ['id' => 12, 'code' => 'KEPATUHAN_SOP', 'name' => 'Kepatuhan SOP', 'type' => 'manual', 'is_active' => 1],
        ]);

        // Ira = Finance (jabatan 0, unit 50) — TANPA baris kpi_weights.
        // Anggun = jabatan 35 (unit 3) — punya bobot absen 40/20/20/20.
        $this->db->table('akun')->insertBatch([
            ['ID_AKUN' => 44, 'ID_JABATAN' => 0,  'ID_UNIT' => 50, 'STATUS_PEGAWAI' => 1, 'NAMA_AKUN' => 'Ira Kurniawati', 'ALAMAT' => 'Probolinggo'],
            ['ID_AKUN' => 61, 'ID_JABATAN' => 35, 'ID_UNIT' => 3,  'STATUS_PEGAWAI' => 1, 'NAMA_AKUN' => 'Anggun', 'ALAMAT' => 'Jember'],
        ]);

        $this->db->table('kpi_weights')->insertBatch([
            ['kpi_component_id' => 9,  'position_id' => 35, 'weight' => 40, 'weight_group' => 'absen', 'effective_from' => '2024-01-01', 'effective_to' => null],
            ['kpi_component_id' => 10, 'position_id' => 35, 'weight' => 20, 'weight_group' => 'absen', 'effective_from' => '2024-01-01', 'effective_to' => null],
            ['kpi_component_id' => 11, 'position_id' => 35, 'weight' => 20, 'weight_group' => 'absen', 'effective_from' => '2024-01-01', 'effective_to' => null],
            ['kpi_component_id' => 12, 'position_id' => 35, 'weight' => 20, 'weight_group' => 'absen', 'effective_from' => '2024-01-01', 'effective_to' => null],
        ]);

        // Hari kerja efektif: 1..10 (10 hari); sisanya OFF (11..30).
        foreach ([44, 61] as $employeeId) {
            $offs = [];
            for ($day = 11; $day <= 30; $day++) {
                $offs[] = [
                    'employee_id'    => $employeeId,
                    'evaluation_date' => sprintf('%s-%s-%02d', self::PERIODE_TAHUN, self::PERIODE_BULAN, $day),
                    'period_month'   => (int) self::PERIODE_BULAN,
                    'period_year'    => (int) self::PERIODE_TAHUN,
                    'evaluator_id'   => 1,
                ];
            }
            $this->db->table('kpi_attendance_off')->insertBatch($offs);
        }

        // Ira: semua komponen sempurna (raw 5) → normalized 100 tiap komponen.
        $this->sebarEvaluasi(44, ['KEHADIRAN' => 5, 'KEBERSIHAN' => 5, 'SERAGAM' => 5, 'KEPATUHAN_SOP' => 5]);

        // Anggun: Kehadiran raw 4 (→ 80), sisanya raw 5 (→ 100).
        $this->sebarEvaluasi(61, ['KEHADIRAN' => 4, 'KEBERSIHAN' => 5, 'SERAGAM' => 5, 'KEPATUHAN_SOP' => 5]);
    }

    private function sebarEvaluasi(int $employeeId, array $rawByCode): void
    {
        $componentIds = [
            'KEHADIRAN' => 9, 'KEBERSIHAN' => 10, 'SERAGAM' => 11, 'KEPATUHAN_SOP' => 12,
        ];

        $rows = [];
        foreach ($rawByCode as $code => $raw) {
            for ($day = 1; $day <= 10; $day++) {
                $rows[] = [
                    'employee_id'      => $employeeId,
                    'kpi_component_id' => $componentIds[$code],
                    'evaluator_id'     => 1,
                    'evaluation_date'  => sprintf('%s-%s-%02d', self::PERIODE_TAHUN, self::PERIODE_BULAN, $day),
                    'raw_score'        => $raw,
                    'period_year'      => (int) self::PERIODE_TAHUN,
                    'period_month'     => (int) self::PERIODE_BULAN,
                ];
            }
        }

        $this->db->table('kpi_evaluations')->insertBatch($rows);
    }

    private function hitung(int $employeeId, int $unitId): array
    {
        return (new AttendanceAggregationService())
            ->calculateMonthlyAttendance($employeeId, $unitId, self::PERIODE_BULAN, self::PERIODE_TAHUN, 'penilaian_kinerja');
    }

    // ── Ira (Finance / jabatan 0, tanpa kpi_weights) ────────────────

    public function testFinanceTidakPunyaMappingBobotAbsen(): void
    {
        $jumlah = $this->db->table('kpi_weights')->where('position_id', 0)->countAllResults();

        $this->assertSame(0, $jumlah, 'Prasyarat bug: jabatan Finance (0) memang tidak punya kpi_weights.');
    }

    public function testDetailAbsenIraTerisiWalauTanpaBobot(): void
    {
        $attendance = $this->hitung(44, 50);

        $rows = AttendanceAggregationService::detailRows($attendance);

        $this->assertCount(4, $rows, 'Detail absen Ira harus terisi walau jabatan 0 tanpa kpi_weights.');
        $this->assertSame(
            ['Kehadiran', 'Kebersihan', 'Seragam', 'Kepatuhan SOP'],
            array_column($rows, 'nama')
        );
        $this->assertSame([40.0, 20.0, 20.0, 20.0], array_column($rows, 'bobot'));
        $this->assertSame([100.0, 100.0, 100.0, 100.0], array_column($rows, 'nilai'));
    }

    public function testSkorAbsenIraTetapTerhitung(): void
    {
        $attendance = $this->hitung(44, 50);

        // skor_total2 tidak bergantung pada kpi_weights — nilainya tidak berubah.
        $this->assertSame(100.0, (float) $attendance['attendance_score']);
    }

    // ── Anggun (jabatan 35, punya kpi_weights absen) ────────────────

    public function testDetailAbsenAnggunTerisiDenganBobotBenar(): void
    {
        $attendance = $this->hitung(61, 3);

        $rows = AttendanceAggregationService::detailRows($attendance);

        $this->assertCount(4, $rows);
        $this->assertSame(
            ['Kehadiran', 'Kebersihan', 'Seragam', 'Kepatuhan SOP'],
            array_column($rows, 'nama')
        );
        $this->assertSame([40.0, 20.0, 20.0, 20.0], array_column($rows, 'bobot'));
        $this->assertSame([80.0, 100.0, 100.0, 100.0], array_column($rows, 'nilai'));
    }

    public function testSkorAbsenAnggunTidakBerubah(): void
    {
        $attendance = $this->hitung(61, 3);

        // 80×0.4 + 100×0.2 + 100×0.2 + 100×0.2 = 92.
        $this->assertSame(92.0, (float) $attendance['attendance_score']);
    }

    /**
     * Regresi: untuk jabatan yang PUNYA kpi_weights absen, hasil detailRows()
     * harus identik dengan penyusunan lama (iterasi kpi_weights). Ini memastikan
     * detail absen jabatan 35–45 tidak berubah.
     */
    public function testHasilSamaDenganPenyusunanLamaBerbasisKpiWeights(): void
    {
        $attendance = $this->hitung(61, 3);

        $weights = $this->db->table('kpi_weights w')
            ->select('w.weight, c.code, c.name')
            ->join('kpi_components c', 'c.id = w.kpi_component_id')
            ->where('w.position_id', 35)
            ->where('w.weight_group', 'absen')
            ->orderBy('w.id', 'ASC')
            ->get()->getResultArray();

        $lama = [];
        foreach ($weights as $w) {
            $normalized = (float) ($attendance['components'][$w['code']]['normalized'] ?? 0.0);
            $lama[] = [
                'nama'  => $w['name'],
                'bobot' => (float) $w['weight'],
                'nilai' => round($normalized, 2),
            ];
        }

        $this->assertSame($lama, AttendanceAggregationService::detailRows($attendance));
    }

    // ── Kontrak format & kontrol skor ───────────────────────────────

    public function testBarisKonsistenDenganSkorTerbobot(): void
    {
        foreach ([[44, 50], [61, 3]] as [$employeeId, $unitId]) {
            $attendance = $this->hitung($employeeId, $unitId);

            $weighted = 0.0;
            foreach (AttendanceAggregationService::detailRows($attendance) as $row) {
                $weighted += $row['nilai'] / 100.0 * $row['bobot'];
            }

            $this->assertSame(
                (float) $attendance['attendance_score'],
                round($weighted, 4),
                "Σ(nilai×bobot/100) harus sama dengan attendance_score untuk pegawai {$employeeId}."
            );
        }
    }
}
