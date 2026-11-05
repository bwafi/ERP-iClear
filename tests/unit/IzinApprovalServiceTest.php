<?php

namespace Tests\unit;

use App\Services\Presensi\IzinApprovalService;
use CodeIgniter\Test\CIUnitTestCase;

class IzinApprovalServiceTest extends CIUnitTestCase
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
        foreach (['presensi_roster', 'izin', 'jenis_perizinan', 'kpi_attendance_off'] as $tabel) {
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

        foreach (['presensi_roster', 'izin', 'jenis_perizinan', 'kpi_attendance_off'] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE jenis_perizinan (
                idjenis_perizinan INT PRIMARY KEY AUTO_INCREMENT, jenis_izin VARCHAR(225) NULL)');
        $q('CREATE TABLE izin (
                idizin INT PRIMARY KEY AUTO_INCREMENT,
                akun_idakun INT NULL, alasan TEXT NULL, status INT NULL DEFAULT 0,
                tanggal_mulai DATE NULL, uuid_atasan TEXT NULL, pegawai_uuid VARCHAR(100) NULL,
                tanggal_akhir DATE NULL, file TEXT NULL, balasan TEXT NULL,
                jenis_perizinan_idjenis_perizinan INT NOT NULL)');
        $q('CREATE TABLE presensi_roster (
                id INT PRIMARY KEY AUTO_INCREMENT, akun_idakun INT NOT NULL, tanggal DATE NOT NULL,
                shift VARCHAR(10) NOT NULL, unit_idunit INT NULL, keterangan TEXT NULL, created_by INT NULL,
                updated_by INT NULL, created_at DATETIME NULL, updated_at DATETIME NULL)');
        $q('CREATE TABLE kpi_attendance_off (
                id INT PRIMARY KEY AUTO_INCREMENT, employee_id INT NOT NULL, evaluation_date DATE NOT NULL,
                evaluator_id INT NULL, period_month INT NULL, period_year INT NULL)');

        $q("INSERT INTO jenis_perizinan (idjenis_perizinan, jenis_izin)
            VALUES (1, 'IZIN'), (2, 'SAKIT'), (3, 'CUTI')");
    }

    private function izin(int $jenis, string $mulai, string $akhir, int $akun = 10): int
    {
        $this->db->table('izin')->insert([
            'akun_idakun'                       => $akun,
            'jenis_perizinan_idjenis_perizinan' => $jenis,
            'tanggal_mulai'                     => $mulai,
            'tanggal_akhir'                     => $akhir,
            'alasan'                            => 'Keperluan',
            'status'                            => 0,
        ]);

        return (int) $this->db->insertID();
    }

    private function roster(int $akun, string $tanggal, string $shift, string $ket = '', ?int $unit = null): void
    {
        $this->db->table('presensi_roster')->insert([
            'akun_idakun' => $akun,
            'tanggal'     => $tanggal,
            'shift'       => $shift,
            'unit_idunit' => $unit,
            'keterangan'  => $ket,
        ]);
    }

    private function baris(int $akun, string $tanggal): ?object
    {
        return $this->db->table('presensi_roster')
            ->where('akun_idakun', $akun)
            ->where('tanggal', $tanggal)
            ->get()
            ->getRow();
    }

    public function testSetujuiMenulisRentangSebagaiIzinDanMenimpaShiftLama(): void
    {
        $id = $this->izin(3, '2026-11-15', '2026-11-20');
        $this->roster(10, '2026-11-15', 'PS', 'Shift lama');
        $this->roster(10, '2026-11-21', 'PS', 'Tetap');

        $bulan = (new IzinApprovalService())->setujui($id, 99);

        $this->assertSame(['2026-11'], $bulan);
        $this->assertSame(7, $this->db->table('presensi_roster')->countAllResults());

        foreach (['2026-11-15', '2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20'] as $tanggal) {
            $row = $this->baris(10, $tanggal);
            $this->assertNotNull($row, $tanggal);
            $this->assertSame('CUTI', $row->shift);
            $this->assertNull($row->keterangan);
            $this->assertSame(99, (int) ($row->updated_by ?: $row->created_by));
        }

        $this->assertSame('PS', $this->baris(10, '2026-11-21')->shift);
        $this->assertSame('Tetap', $this->baris(10, '2026-11-21')->keterangan);
    }

    public function testSetujuiTidakMenimpaUnitKerjaRoster(): void
    {
        $id = $this->izin(3, '2026-11-15', '2026-11-16');
        $this->roster(10, '2026-11-15', 'PAGI', '', 2);
        $this->roster(10, '2026-11-16', 'PAGI', '', 2);

        (new IzinApprovalService())->setujui($id, 99);

        foreach (['2026-11-15', '2026-11-16'] as $tanggal) {
            $row = $this->baris(10, $tanggal);
            $this->assertSame('CUTI', $row->shift);
            $this->assertSame(2, (int) $row->unit_idunit);
        }
    }

    public function testBatalkanMenghapusShiftJenisPengajuanSaja(): void
    {
        $id = $this->izin(2, '2026-11-15', '2026-11-20');
        (new IzinApprovalService())->setujui($id, 99);

        $this->roster(10, '2026-11-21', 'PS', 'Tetap');
        $this->roster(10, '2026-11-22', 'IZIN', 'Izin lain');
        $this->roster(10, '2026-11-25', 'IZIN', 'Izin keluarga');

        $bulan = (new IzinApprovalService())->batalkan($id);

        $this->assertSame(['2026-11'], $bulan);
        foreach (['2026-11-15', '2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20'] as $tanggal) {
            $this->assertNull($this->baris(10, $tanggal), $tanggal);
        }

        $this->assertSame('PS', $this->baris(10, '2026-11-21')->shift);
        $this->assertNotNull($this->baris(10, '2026-11-22'));
        $this->assertNotNull($this->baris(10, '2026-11-25'));
    }

    public function testRentangLintasBulanMengembalikanSemuaBulan(): void
    {
        $id = $this->izin(1, '2026-11-29', '2026-12-02');

        $bulan = (new IzinApprovalService())->setujui($id, 1);

        $this->assertSame(['2026-11', '2026-12'], $bulan);
        $this->assertSame(4, $this->db->table('presensi_roster')->countAllResults());
        $this->assertSame('IZIN', $this->baris(10, '2026-12-02')->shift);
        $this->assertNull($this->baris(10, '2026-12-02')->keterangan);
    }

    public function testBatalkanTidakMenghapusShiftLain(): void
    {
        $id = $this->izin(1, '2026-11-15', '2026-11-20');
        $this->roster(10, '2026-11-15', 'SAKIT', 'Sakit manual');

        (new IzinApprovalService())->batalkan($id);

        $this->assertNotNull($this->baris(10, '2026-11-15'));
        $this->assertSame('SAKIT', $this->baris(10, '2026-11-15')->shift);
    }

    public function testBatalkanMenghapusMarkerOffDalamRentangSaja(): void
    {
        $id = $this->izin(2, '2026-11-15', '2026-11-20');
        foreach (['2026-11-16', '2026-11-25'] as $tanggal) {
            $this->db->table('kpi_attendance_off')->insert([
                'employee_id'     => 10,
                'evaluation_date' => $tanggal,
                'evaluator_id'    => 49,
                'period_month'    => 11,
                'period_year'     => 2026,
            ]);
        }

        (new IzinApprovalService())->batalkan($id);

        $this->assertSame(0, $this->db->table('kpi_attendance_off')->where('evaluation_date', '2026-11-16')->countAllResults());
        $this->assertSame(1, $this->db->table('kpi_attendance_off')->where('evaluation_date', '2026-11-25')->countAllResults());
    }

    public function testTolakPengajuanBaruTidakMenghapusMarkerOffSah(): void
    {
        $id = $this->izin(2, '2026-11-15', '2026-11-20');
        $this->db->table('kpi_attendance_off')->insert([
            'employee_id'     => 10,
            'evaluation_date' => '2026-11-16',
            'evaluator_id'    => 49,
            'period_month'    => 11,
            'period_year'     => 2026,
        ]);

        (new IzinApprovalService())->batalkan($id, false);

        $this->assertSame(1, $this->db->table('kpi_attendance_off')->where('evaluation_date', '2026-11-16')->countAllResults());
    }
}
