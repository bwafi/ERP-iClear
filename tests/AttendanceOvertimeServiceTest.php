<?php

namespace Tests;

use App\Services\Payroll\AttendanceOvertimeService;
use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;

/**
 * Lembur = roster shift FULL yang tanggalnya sudah lewat.
 *
 * `presensi.sesi = 'FULL'` bukan penanda lembur; semua shift non-PS memakai
 * sesi itu. Karena itu test ini memakai `presensi_roster`.
 *
 * @internal
 */
final class AttendanceOvertimeServiceTest extends CIUnitTestCase
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
        $this->sebarData();
    }

    protected function tearDown(): void
    {
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function svc(?string $hariIni = '2026-11-26'): AttendanceOvertimeService
    {
        return new AttendanceOvertimeService($this->db, $hariIni);
    }

    private function buatSkema(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        foreach (['presensi_roster', 'presensi', 'kas_keluar', 'akun', 'unit'] as $t) {
            $q('DROP TABLE IF EXISTS ' . $t);
        }

        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL, is_operational INT NULL)');
        $q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN TEXT NULL, TERIMA_GAJI INT NULL,
                STATUS_PEGAWAI INT NULL, deleted TEXT NULL)');
        $q('CREATE TABLE presensi_roster (
                id INT PRIMARY KEY AUTO_INCREMENT, akun_idakun INT NULL,
                tanggal DATE NULL, shift VARCHAR(10) NULL, unit_idunit INT NULL)');
        $q('CREATE TABLE kas_keluar (
                idkas_keluar INT PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL,
                kategori_idkategori INT NULL, deskripsi TEXT NULL,
                jumlah REAL NULL, penerima TEXT NULL, idunit INT NULL)');
    }

    private function sebarData(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        $q("INSERT INTO unit (idunit, NAMA_UNIT, is_operational) VALUES
            (1, 'ICLEAR Cabang', 1), (50, 'Head Office', 0)");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, TERIMA_GAJI, STATUS_PEGAWAI, deleted) VALUES
            (1, 1, 36, 'Teknisi Satu', 1, 1, NULL),
            (2, 1, 41, 'Kepala Toko', 1, 1, NULL),
            (3, 1, 40, 'SPV', 1, 1, NULL),
            (4, 50, 36, 'Teknisi HO', 1, 1, NULL),
            (5, 1, 36, 'Teknisi Resign', 1, 0, NULL),
            (6, 1, 36, 'Teknisi Tanpa Gaji', 0, 1, NULL),
            (7, 1, 49, 'Teknisi Mesin', 1, 1, NULL),
            (8, 1, 36, 'Teknisi Dihapus', 1, 1, '1')");

        // Roster November 2026. 3 & 4 FULL (4 dobel untuk uji dedupe), 5 PAGI,
        // 6 OFF, 30 FULL tapi belum lewat. Tanggal di luar November diabaikan.
        $q("INSERT INTO presensi_roster (akun_idakun, tanggal, shift, unit_idunit) VALUES
            (1, '2026-11-03', 'FULL', 1),
            (1, '2026-11-04', 'FULL', 1),
            (1, '2026-11-04', 'FULL', 1),
            (1, '2026-11-05', 'PAGI', 1),
            (1, '2026-11-06', 'OFF', 1),
            (1, '2026-11-30', 'FULL', 1),
            (1, '2026-10-31', 'FULL', 1),
            (1, '2026-12-01', 'FULL', 1),
            (2, '2026-11-07', 'FULL', 1),
            (3, '2026-11-08', 'FULL', 1),
            (4, '2026-11-09', 'FULL', 1),
            (5, '2026-11-10', 'FULL', 1),
            (6, '2026-11-11', 'FULL', 1),
            (7, '2026-11-12', 'FULL', 1),
            (8, '2026-11-13', 'FULL', 1)");

        $q("INSERT INTO kas_keluar (tanggal, kategori_idkategori, deskripsi, jumlah, penerima, idunit) VALUES
            ('2026-11-11', 10, 'LEMBUR', 35000, '1', 1),
            ('2026-11-12', 10, 'LEMBUR', 35000, 'Budi', 1),
            ('2026-10-12', 10, 'LEMBUR', 35000, '2', 1)");
    }

    public function testRingkasanHanyaRosterFullDanPegawaiLayak(): void
    {
        $hasil = $this->svc()->ringkasanPeriode('2026-11');

        $this->assertSame([1, 2], array_keys($hasil));
        $this->assertSame(2, $hasil[1]['hari'], 'dua tanggal FULL unik; baris ganda tidak menambah hari');
        $this->assertSame(70000, $hasil[1]['nominal']);
        $this->assertSame(['2026-11-03', '2026-11-04'], $hasil[1]['tanggal']);
        $this->assertSame(1, $hasil[2]['hari']);
        $this->assertSame(35000, $hasil[2]['nominal']);
    }

    public function testShiftSelainFullTidakDihitung(): void
    {
        $tanggal = $this->svc()->untukPegawai(1, '2026-11')['tanggal'];

        $this->assertNotContains('2026-11-05', $tanggal, 'PAGI bukan FULL');
        $this->assertNotContains('2026-11-06', $tanggal, 'OFF bukan FULL');
    }

    public function testTanggalBelumLewatTidakDihitung(): void
    {
        $tanggal = $this->svc('2026-11-26')->untukPegawai(1, '2026-11')['tanggal'];

        $this->assertNotContains('2026-11-30', $tanggal, 'tanggal setelah hari ini tidak boleh dihitung');
    }

    public function testTanggalSudahLewatSemuaDihitungSaatBulanSelesai(): void
    {
        $ringkasan = $this->svc('2026-12-05')->untukPegawai(1, '2026-11');

        $this->assertSame(3, $ringkasan['hari'], '3, 4, dan 30 November semuanya sudah lewat');
        $this->assertSame(105000, $ringkasan['nominal']);
    }

    public function testBatasBulanMemakaiTanggalRoster(): void
    {
        $tanggal = $this->svc('2027-01-05')->untukPegawai(1, '2026-11')['tanggal'];

        $this->assertNotContains('2026-10-31', $tanggal);
        $this->assertNotContains('2026-12-01', $tanggal);
    }

    public function testPegawaiTidakLayakTidakMuncul(): void
    {
        $hasil = $this->svc()->ringkasanPeriode('2026-11');

        foreach ([3, 4, 5, 6, 7, 8] as $id) {
            $this->assertArrayNotHasKey($id, $hasil, 'akun ' . $id . ' tidak boleh dapat lembur');
        }
    }

    public function testSumberPeriodeMenghormatiCutoff(): void
    {
        $svc = $this->svc();

        $this->assertSame('kas_keluar', $svc->sumberPeriode('2026-10'));
        $this->assertSame('presensi', $svc->sumberPeriode('2026-11'));
    }

    public function testFormatBulanTidakValidDitolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->svc()->ringkasanPeriode('2026-1');
    }

    public function testAdaLemburManualHanyaPenerimaAngkaYangLayak(): void
    {
        $svc = $this->svc();

        $this->assertTrue($svc->adaLemburManual('2026-11'), 'penerima "1" adalah pegawai layak');
        $this->assertFalse($svc->adaLemburManual('2026-10'), 'periode legacy tidak memicu peringatan');

        $this->db->table('kas_keluar')->where('penerima', '1')->delete();
        $this->assertFalse($svc->adaLemburManual('2026-11'), 'penerima nama harus diabaikan');
    }

    public function testLaporanMemuatNamaDanTanggal(): void
    {
        $laporan = $this->svc()->laporan('2026-11');

        $this->assertCount(2, $laporan);
        $this->assertSame('Teknisi Satu', $laporan[0]['nama']);
        $this->assertSame(36, $laporan[0]['jabatan_id']);
        $this->assertSame(['2026-11-03', '2026-11-04'], $laporan[0]['tanggal']);
    }
}
