<?php

namespace Tests\unit;

use App\Services\Kpi\PresensiAttendanceSyncService;
use CodeIgniter\Test\CIUnitTestCase;

class PresensiAttendanceSyncServiceTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';
    private string $goLiveAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->goLiveAsli = (string) config('Presensi')->goLiveDate;
        config('Presensi')->goLiveDate = '2026-11-01';
        $this->buatSkema();
    }

    protected function tearDown(): void
    {
        config('Presensi')->goLiveDate = $this->goLiveAsli;

        foreach ([
            'presensi', 'presensi_roster', 'spv_units', 'kpi_evaluations',
            'kpi_attendance_detail', 'kpi_attendance_off', 'kpi_components', 'akun',
        ] as $tabel) {
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

        foreach ([
            'presensi', 'presensi_roster', 'spv_units', 'kpi_evaluations',
            'kpi_attendance_detail', 'kpi_attendance_off', 'kpi_components', 'akun',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN TEXT NULL, STATUS_PEGAWAI INT NULL)');
        $q('CREATE TABLE spv_units (
                id INT PRIMARY KEY AUTO_INCREMENT, spv_id INT NOT NULL, unit_id INT NOT NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL,
                UNIQUE KEY uniq_spv_unit (spv_id, unit_id))');
        $q('CREATE TABLE presensi_roster (
                id INT PRIMARY KEY AUTO_INCREMENT, akun_idakun INT NOT NULL,
                tanggal DATE NOT NULL, shift VARCHAR(10) NOT NULL, keterangan TEXT NULL,
                created_by INT NULL, updated_by INT NULL, created_at DATETIME NULL, updated_at DATETIME NULL,
                UNIQUE KEY uniq_akun_tanggal (akun_idakun, tanggal))');
        $q('CREATE TABLE presensi (
                idpresensi INT PRIMARY KEY AUTO_INCREMENT, waktu_masuk DATETIME NULL,
                waktu_pulang DATETIME NULL, jam_jadwal_masuk TIME NULL, jam_jadwal_pulang TIME NULL,
                jam_toleransi TIME NULL, status_absensi INT NULL, lat TEXT NULL, `long` TEXT NULL,
                ip TEXT NULL, foto TEXT NULL, foto_pulang VARCHAR(255) NULL, idjadwal_masuk INT NULL,
                akun_idakun INT NOT NULL, unit_idunit INT NULL, created_at DATETIME NULL,
                updated_at DATETIME NULL, jarak INT NULL, status_kehadiran INT NULL,
                keterangan TEXT NULL, sesi VARCHAR(10) NULL, sumber VARCHAR(20) NULL)');
        $q('CREATE TABLE kpi_components (
                id INT PRIMARY KEY AUTO_INCREMENT, code VARCHAR(50) NOT NULL, name VARCHAR(255) NULL,
                description TEXT NULL, type VARCHAR(20) NULL, category VARCHAR(20) NULL,
                unit_of_measure VARCHAR(20) NULL, calculation_strategy VARCHAR(50) NULL,
                is_active TINYINT NULL, created_at DATETIME NULL, updated_at DATETIME NULL)');
        $q('CREATE TABLE kpi_evaluations (
                id INT PRIMARY KEY AUTO_INCREMENT, employee_id INT NOT NULL,
                kpi_component_id INT NOT NULL, evaluator_id INT NOT NULL, evaluation_date DATE NULL,
                raw_score DECIMAL(5,2) NOT NULL, max_score DECIMAL(5,2) NOT NULL DEFAULT 5,
                normalized_score DECIMAL(5,2) NOT NULL, weighted_score DECIMAL(5,2) NOT NULL DEFAULT 0,
                notes TEXT NULL, period_year INT NOT NULL, period_month INT NOT NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL,
                UNIQUE KEY uq_emp_kpi_eval_date (employee_id, kpi_component_id, evaluator_id, evaluation_date))');
        $q('CREATE TABLE kpi_attendance_detail (
                id INT PRIMARY KEY AUTO_INCREMENT, evaluation_id INT NOT NULL,
                shift VARCHAR(10) NOT NULL, session VARCHAR(10) NOT NULL,
                attendance_type VARCHAR(20) NOT NULL, scheduled_time VARCHAR(5) NOT NULL,
                actual_time VARCHAR(5) NOT NULL, late_minutes INT NOT NULL, auto_score DECIMAL(5,2) NOT NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL)');
        $q('CREATE TABLE kpi_attendance_off (
                id INT PRIMARY KEY AUTO_INCREMENT, employee_id INT NOT NULL, evaluation_date DATE NOT NULL,
                evaluator_id INT NOT NULL, period_month INT NOT NULL, period_year INT NOT NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL,
                UNIQUE KEY uq_emp_date_eval (employee_id, evaluation_date, evaluator_id))');

        $q("INSERT INTO kpi_components (id, code, name, is_active)
            VALUES (1, 'KEHADIRAN', 'Kehadiran', 1)");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, STATUS_PEGAWAI)
            VALUES (500, 50, 34, 'Manager HO', 1)");
    }

    private function karyawan(int $id, int $unit, int $jabatan = 36): void
    {
        $this->db->table('akun')->insert([
            'ID_AKUN'        => $id,
            'ID_UNIT'        => $unit,
            'ID_JABATAN'     => $jabatan,
            'NAMA_AKUN'      => 'Pegawai ' . $id,
            'STATUS_PEGAWAI' => 1,
        ]);
    }

    private function spv(int $spvId, int $unit): void
    {
        if ($this->db->table('akun')->where('ID_AKUN', $spvId)->countAllResults() === 0) {
            $this->db->table('akun')->insert([
                'ID_AKUN'        => $spvId,
                'ID_UNIT'        => 50,
                'ID_JABATAN'     => 40,
                'NAMA_AKUN'      => 'SPV ' . $spvId,
                'STATUS_PEGAWAI' => 1,
            ]);
        }

        $this->db->table('spv_units')->insert([
            'spv_id'  => $spvId,
            'unit_id' => $unit,
        ]);
    }

    private function roster(int $akunId, string $tanggal, string $shift): void
    {
        $this->db->table('presensi_roster')->insert([
            'akun_idakun' => $akunId,
            'tanggal'     => $tanggal,
            'shift'       => $shift,
        ]);
    }

    private function punch(int $akunId, string $waktu, string $sesi, string $jadwal): void
    {
        $this->db->table('presensi')->insert([
            'akun_idakun'      => $akunId,
            'unit_idunit'      => 1,
            'waktu_masuk'      => $waktu,
            'sesi'             => $sesi,
            'jam_jadwal_masuk' => $jadwal,
            'sumber'           => 'mobile',
        ]);
    }

    private function komponenKehadiran(): int
    {
        return (int) $this->db->table('kpi_components')
            ->where('code', 'KEHADIRAN')
            ->get()->getRow()->id;
    }

    public function testEvaluatorCabangMemakaiSpvUnit(): void
    {
        $this->karyawan(10, 2);
        $this->spv(49, 2);

        $svc = new PresensiAttendanceSyncService();

        $this->assertSame(49, $svc->evaluatorFor(10));
    }

    public function testEvaluatorHoMemakaiManager(): void
    {
        $this->karyawan(11, 50);

        $svc = new PresensiAttendanceSyncService();

        $this->assertSame(500, $svc->evaluatorFor(11));
    }

    public function testCabangTanpaMappingSpvFallbackKeManager(): void
    {
        $this->karyawan(12, 6);

        $svc = new PresensiAttendanceSyncService();

        $this->assertSame(500, $svc->evaluatorFor(12));
    }

    public function testTanggalSebelumGoLiveDilewati(): void
    {
        $this->karyawan(10, 2);
        $this->spv(49, 2);

        $hasil = (new PresensiAttendanceSyncService())->syncDay(10, '2026-10-31');

        $this->assertSame('skipped', $hasil['status']);
        $this->assertSame('before_go_live', $hasil['reason']);
    }

    public function testPunchMembuatKehadiranDenganEvaluatorSpv(): void
    {
        $this->karyawan(10, 2);
        $this->spv(49, 2);
        $this->roster(10, '2026-11-02', 'PAGI');
        $this->punch(10, '2026-11-02 08:50:00', 'FULL', '08:45:00');

        (new PresensiAttendanceSyncService())->syncDay(10, '2026-11-02');

        $eval = $this->db->table('kpi_evaluations')->get()->getRow();
        $this->assertNotNull($eval);
        $this->assertSame(49, (int) $eval->evaluator_id);
        $this->assertSame(3.0, (float) $eval->raw_score);
        $this->assertSame(1, (int) $this->db->table('kpi_attendance_detail')->countAllResults());
    }

    public function testShiftPsMenghasilkanSatuEvaluasiDuaSesiDanIdempoten(): void
    {
        $this->karyawan(10, 2);
        $this->spv(49, 2);
        $this->roster(10, '2026-11-02', 'PS');
        $this->punch(10, '2026-11-02 08:50:00', 'PAGI', '08:45:00');
        $this->punch(10, '2026-11-02 17:00:00', 'SORE', '16:55:00');

        $svc = new PresensiAttendanceSyncService();
        $svc->syncDay(10, '2026-11-02');
        $svc->syncDay(10, '2026-11-02');

        $eval = $this->db->table('kpi_evaluations')->get()->getRow();
        $this->assertNotNull($eval);
        $this->assertSame(2.0, (float) $eval->raw_score);
        $this->assertSame(1, (int) $this->db->table('kpi_evaluations')->countAllResults());
        $this->assertSame(2, (int) $this->db->table('kpi_attendance_detail')->countAllResults());
    }

    public function testRosterOffTidakDihitungDanMembuatMarkerOff(): void
    {
        $this->karyawan(10, 2);
        $this->spv(49, 2);
        $this->roster(10, '2026-11-02', 'OFF');

        (new PresensiAttendanceSyncService())->syncDay(10, '2026-11-02');

        $this->assertSame(0, (int) $this->db->table('kpi_evaluations')->countAllResults());
        $off = $this->db->table('kpi_attendance_off')->get()->getRow();
        $this->assertNotNull($off);
        $this->assertSame(49, (int) $off->evaluator_id);
    }

    public function testRosterIzinTidakDihitungWalauAdaPunch(): void
    {
        $this->karyawan(10, 2);
        $this->spv(49, 2);
        $this->roster(10, '2026-11-02', 'IZIN');
        $this->punch(10, '2026-11-02 08:50:00', 'FULL', '08:45:00');

        (new PresensiAttendanceSyncService())->syncDay(10, '2026-11-02');

        $this->assertSame(0, (int) $this->db->table('kpi_evaluations')->countAllResults());
        $off = $this->db->table('kpi_attendance_off')->get()->getRow();
        $this->assertNotNull($off);
        $this->assertSame(49, (int) $off->evaluator_id);
    }

    public function testRosterSakitDanCutiJugaTidakDihitung(): void
    {
        $this->karyawan(10, 2);
        $this->spv(49, 2);
        $this->roster(10, '2026-11-03', 'SAKIT');
        $this->roster(10, '2026-11-04', 'CUTI');

        $sync = new PresensiAttendanceSyncService();
        $sync->syncDay(10, '2026-11-03');
        $sync->syncDay(10, '2026-11-04');

        $this->assertSame(0, (int) $this->db->table('kpi_evaluations')->countAllResults());
        $this->assertSame(2, (int) $this->db->table('kpi_attendance_off')->countAllResults());
    }

    public function testBarisManualTidakDitimpaSinkronisasi(): void
    {
        $this->karyawan(10, 2);
        $this->spv(49, 2);
        $this->roster(10, '2026-11-02', 'PAGI');
        $this->punch(10, '2026-11-02 08:50:00', 'FULL', '08:45:00');

        $this->db->table('kpi_evaluations')->insert([
            'employee_id'      => 10,
            'kpi_component_id' => $this->komponenKehadiran(),
            'evaluator_id'     => 35,
            'evaluation_date'  => '2026-11-02',
            'raw_score'        => 5,
            'max_score'        => 5,
            'normalized_score' => 100,
            'weighted_score'   => 0,
            'notes'            => 'Input manual',
            'period_year'      => 2026,
            'period_month'     => 11,
        ]);

        (new PresensiAttendanceSyncService())->syncDay(10, '2026-11-02');

        $this->assertSame(1, (int) $this->db->table('kpi_evaluations')->countAllResults());
        $eval = $this->db->table('kpi_evaluations')->get()->getRow();
        $this->assertSame(5.0, (float) $eval->raw_score);
        $this->assertSame(35, (int) $eval->evaluator_id);
    }

    public function testKoreksiManualMenghapusBarisOtomatis(): void
    {
        $this->karyawan(10, 2);
        $this->spv(49, 2);
        $this->roster(10, '2026-11-02', 'PAGI');
        $this->punch(10, '2026-11-02 08:50:00', 'FULL', '08:45:00');
        $this->db->table('kpi_attendance_off')->insert([
            'employee_id'     => 10,
            'evaluation_date' => '2026-11-02',
            'evaluator_id'    => 49,
            'period_month'    => 11,
            'period_year'     => 2026,
        ]);

        $svc = new PresensiAttendanceSyncService();
        $svc->syncDay(10, '2026-11-02');
        $svc->clearAutoForManual(10, '2026-11-02', 35, false);

        $this->assertSame(0, (int) $this->db->table('kpi_evaluations')->countAllResults());
        $this->assertSame(0, (int) $this->db->table('kpi_attendance_off')->countAllResults());
    }

    public function testKoreksiManualOlehEvaluatorSamaMenjadiManual(): void
    {
        $this->karyawan(10, 2);
        $this->spv(49, 2);
        $this->roster(10, '2026-11-02', 'PAGI');
        $this->punch(10, '2026-11-02 08:50:00', 'FULL', '08:45:00');

        $svc = new PresensiAttendanceSyncService();
        $svc->syncDay(10, '2026-11-02');
        $svc->clearAutoForManual(10, '2026-11-02', 49, false);

        $eval = $this->db->table('kpi_evaluations')->get()->getRow();
        $this->assertNotNull($eval);
        $this->assertStringStartsWith('MANUAL_ABSEN', (string) $eval->notes);
    }

    public function testSyncMonthMemprosesHadirDanOff(): void
    {
        $this->karyawan(10, 2);
        $this->spv(49, 2);
        $this->roster(10, '2026-11-02', 'PAGI');
        $this->punch(10, '2026-11-02 08:50:00', 'FULL', '08:45:00');
        $this->roster(10, '2026-11-03', 'OFF');

        $hasil = (new PresensiAttendanceSyncService())->syncMonth(10, '2026-11');

        $this->assertSame(1, $hasil['synced']);
        $this->assertSame(1, $hasil['off']);
        $this->assertSame(1, (int) $this->db->table('kpi_evaluations')->countAllResults());
        $this->assertSame(1, (int) $this->db->table('kpi_attendance_off')->countAllResults());
    }
}
