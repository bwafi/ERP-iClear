<?php

namespace Tests;

use App\Services\Payroll\HoSalaryDistributionService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Cache lintas request untuk distribusi gaji HO (issue #24).
 *
 * Distribusi dihitung dari KPI tiap karyawan HO: 1.352 query / ±1 detik untuk
 * 7 karyawan. Hasilnya disimpan di file cache 5 menit supaya buka halaman
 * berikutnya tidak menghitung ulang.
 *
 * Fixture sengaja tanpa karyawan HO (tanpa tabel KPI): yang diuji di sini
 * cache-nya, bukan mesin KPI.
 */
class HoSalaryDistributionCacheTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';
    private string $bulan = '2026-09';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->buatSkema();
        cache()->delete(HoSalaryDistributionService::cacheKey($this->bulan));
    }

    protected function tearDown(): void
    {
        cache()->delete(HoSalaryDistributionService::cacheKey($this->bulan));
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        foreach (['unit', 'akun'] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->query('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL, jenis TEXT NULL, is_trial INT NULL, is_operational INT NULL)');
        $this->db->query('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL, NAMA_AKUN TEXT NULL, ALAMAT TEXT NULL, TERIMA_GAJI INT NULL, STATUS_PEGAWAI INT NULL, deleted TEXT NULL)');

        $this->db->query("INSERT INTO unit (idunit, NAMA_UNIT, jenis, is_trial, is_operational) VALUES
            (1, 'ICLEAR Probolinggo', 'pribadi', 0, 1),
            (3, 'ICLEAR Banyuwangi', 'franchise', 0, 1),
            (50, 'Head Office', 'Kantor', 0, 0)");
    }

    /** Hasil perhitungan disimpan supaya request berikutnya tidak menghitung ulang. */
    public function testHasilDisimpanDiCache(): void
    {
        $hasil = (new HoSalaryDistributionService())->calculateDistribution($this->bulan);

        $this->assertSame(2, $hasil['divisor'], '1 unit pribadi + 1 unit franchise');
        $this->assertSame(0.0, $hasil['total_dibebankan'], 'tanpa karyawan HO, beban 0 dan bukan perkiraan');
        $this->assertSame(
            $hasil,
            cache()->get(HoSalaryDistributionService::cacheKey($this->bulan)),
            'hasil harus tersimpan di cache'
        );
    }

    /** Panggilan kedua memakai isi cache, bukan menghitung ulang. */
    public function testPanggilanKeduaMemakaiCache(): void
    {
        (new HoSalaryDistributionService())->calculateDistribution($this->bulan);

        // Kalau panggilan kedua masih menghitung ulang, penanda ini tertimpa.
        cache()->save(
            HoSalaryDistributionService::cacheKey($this->bulan),
            ['divisor' => 99, 'penanda' => 'dari cache'],
            300
        );

        $kedua = (new HoSalaryDistributionService())->calculateDistribution($this->bulan);

        $this->assertSame(99, $kedua['divisor']);
        $this->assertSame('dari cache', $kedua['penanda'] ?? null);
    }

    /** Setelah payroll/KPI berubah, cache dihapus dan angka dihitung ulang. */
    public function testInvalidateCacheMenghitungUlang(): void
    {
        (new HoSalaryDistributionService())->calculateDistribution($this->bulan);
        cache()->save(HoSalaryDistributionService::cacheKey($this->bulan), ['divisor' => 99], 300);

        HoSalaryDistributionService::invalidateCache($this->bulan);

        $this->assertNull(cache()->get(HoSalaryDistributionService::cacheKey($this->bulan)));
        $this->assertSame(2, (new HoSalaryDistributionService())->calculateDistribution($this->bulan)['divisor']);
    }
}
