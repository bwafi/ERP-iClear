<?php

namespace Tests;

use App\Services\Kpi\ManualKpiScorer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regresi issue #27: Feed PL tidak boleh hilang saat Feed Mingguan kosong.
 *
 * Aturan mesin final (sama dengan LegacyKpiCalculationService):
 *   feed = feed mingguan ?: feed pl
 * Kedua komponen (FEED_PL & FEED_MINGGUAN) memakai nilai gabungan itu.
 */
class ManualKpiScorerFeedTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->db->query('DROP TABLE IF EXISTS penilaian');
        $this->db->query('CREATE TABLE penilaian (
            id INT PRIMARY KEY AUTO_INCREMENT,
            pegawai_idpegawai INT NULL,
            aspek TEXT NULL,
            skor REAL NULL,
            tanggal_penilaian TEXT NULL
        )');
    }

    protected function tearDown(): void
    {
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function ctx(): array
    {
        return [
            'employee_id' => 7,
            'unit'        => 1,
            'month'       => 9,
            'year'        => 2026,
            'context'     => 'default',
            'date'        => '2026-09-15',
        ];
    }

    /** Kasus di issue: Feed Mingguan kosong, Feed PL terisi → nilainya dipakai. */
    public function testFeedPlDipakaiSaatFeedMingguanKosong(): void
    {
        $this->db->table('penilaian')->insertBatch([
            ['pegawai_idpegawai' => 7, 'aspek' => 'feed pl', 'skor' => 80, 'tanggal_penilaian' => '2026-09-03'],
            ['pegawai_idpegawai' => 7, 'aspek' => 'feed pl', 'skor' => 10, 'tanggal_penilaian' => '2026-09-10'],
        ]);

        $scorer = new ManualKpiScorer();

        $this->assertEqualsWithDelta(90.0, $scorer->achievement('FEED_PL', $this->ctx()), 0.001);
        $this->assertEqualsWithDelta(90.0, $scorer->achievement('FEED_MINGGUAN', $this->ctx()), 0.001);
    }

    /** Kalau dua-duanya ada, Feed Mingguan yang dipakai (aturan final). */
    public function testFeedMingguanMenangSaatKeduanyaAda(): void
    {
        $this->db->table('penilaian')->insertBatch([
            ['pegawai_idpegawai' => 7, 'aspek' => 'feed mingguan', 'skor' => 60, 'tanggal_penilaian' => '2026-09-05'],
            ['pegawai_idpegawai' => 7, 'aspek' => 'feed pl', 'skor' => 90, 'tanggal_penilaian' => '2026-09-06'],
        ]);

        $scorer = new ManualKpiScorer();

        $this->assertEqualsWithDelta(60.0, $scorer->achievement('FEED_PL', $this->ctx()), 0.001);
        $this->assertEqualsWithDelta(60.0, $scorer->achievement('FEED_MINGGUAN', $this->ctx()), 0.001);
    }

    /** Tanpa data sama sekali tetap 0 (bukan error). */
    public function testTanpaDataNol(): void
    {
        $scorer = new ManualKpiScorer();

        $this->assertEqualsWithDelta(0.0, $scorer->achievement('FEED_PL', $this->ctx()), 0.001);
        $this->assertEqualsWithDelta(0.0, $scorer->achievement('FEED_MINGGUAN', $this->ctx()), 0.001);
    }
}
