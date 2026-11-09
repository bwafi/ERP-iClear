<?php

namespace Tests;

use App\Services\Marketing\MarketingRekapService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regresi filter cabang pada ringkasan & daftar rekap marketing.
 *
 * Service memakai qualifier tabel mentah (`marketing_rekap_harian.*`), jadi
 * test ini menyetel prefix koneksi ke '' seperti test unit lain yang memakai
 * query mentah.
 */
class MarketingRekapUnitFilterTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string)$this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->buatSkema();
        $this->sebarData();
    }

    protected function tearDown(): void
    {
        foreach (['marketing_rekap_harian_detail', 'marketing_rekap_harian', 'unit'] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        foreach (['marketing_rekap_harian_detail', 'marketing_rekap_harian', 'unit'] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }
        $this->db->query('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT VARCHAR(100) NULL)');
        $this->db->query('CREATE TABLE marketing_rekap_harian (
            id INT PRIMARY KEY AUTO_INCREMENT,
            tanggal DATE NOT NULL,
            unit_id INT NOT NULL,
            total_lead_wa_dm INT NOT NULL DEFAULT 0,
            lead_total_iklan_dashboard INT NOT NULL DEFAULT 0,
            created_by INT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
        $this->db->query('CREATE TABLE marketing_rekap_harian_detail (
            id INT PRIMARY KEY AUTO_INCREMENT,
            rekap_id INT NOT NULL,
            platform VARCHAR(50) NOT NULL,
            non_iklan INT NOT NULL DEFAULT 0,
            iklan INT NOT NULL DEFAULT 0,
            total INT NOT NULL DEFAULT 0,
            prospek INT NOT NULL DEFAULT 0,
            datang INT NOT NULL DEFAULT 0,
            rate DECIMAL(5,2) NOT NULL DEFAULT 0
        )');
    }

    private function sebarData(): void
    {
        $this->db->query("INSERT INTO unit (idunit, NAMA_UNIT) VALUES
            (1, 'ICLEAR Jember'), (2, 'ICLEAR Probolinggo')");
        $this->db->query("INSERT INTO marketing_rekap_harian (id, tanggal, unit_id, total_lead_wa_dm) VALUES
            (1, '2026-09-01', 1, 8), (2, '2026-09-02', 2, 10)");
        $this->db->query("INSERT INTO marketing_rekap_harian_detail
            (rekap_id, platform, non_iklan, iklan, total, prospek, datang, rate) VALUES
            (1, 'WhatsApp', 5, 2, 7, 3, 2, 28.57),
            (1, 'Instagram', 1, 0, 1, 0, 1, 100.00),
            (2, 'WhatsApp', 10, 0, 10, 4, 5, 50.00)");
    }

    public function testRingkasanSemuaCabang(): void
    {
        $summary = (new MarketingRekapService())->monthlySummary(9, 2026);

        $this->assertSame(18, $summary['total']);
        $this->assertSame(18, $summary['total_lead_wa_dm']);
        $this->assertSame(2, $summary['total_days']);
    }

    public function testRingkasanDifilterSatuCabang(): void
    {
        $summary = (new MarketingRekapService())->monthlySummary(9, 2026, 1);

        $this->assertSame(6, $summary['non_iklan']);
        $this->assertSame(2, $summary['iklan']);
        $this->assertSame(8, $summary['total']);
        $this->assertSame(3, $summary['prospek']);
        $this->assertSame(3, $summary['datang']);
        $this->assertSame(8, $summary['total_lead_wa_dm']);
        $this->assertSame(1, $summary['total_days']);
        $this->assertSame(37.5, $summary['rate']);
    }

    public function testDaftarDifilterSatuCabang(): void
    {
        $rows = (new MarketingRekapService())->listByMonth(9, 2026, 1);

        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows[0]['unit_id']);
        $this->assertSame('ICLEAR Jember', $rows[0]['unit_name']);
        $this->assertSame(8, $rows[0]['total']);
        $this->assertCount(2, $rows[0]['platforms']);
    }

    public function testDaftarSemuaCabang(): void
    {
        $rows = (new MarketingRekapService())->listByMonth(9, 2026);

        $this->assertCount(2, $rows);
        $this->assertSame(18, array_sum(array_column($rows, 'total')));
    }

    public function testCabangTidakDikenalMenghasilkanKosong(): void
    {
        $summary = (new MarketingRekapService())->monthlySummary(9, 2026, 999);

        $this->assertSame(0, $summary['total']);
        $this->assertSame(0, $summary['total_days']);
        $this->assertSame([], (new MarketingRekapService())->listByMonth(9, 2026, 999));
    }

    public function testViewMerenderRingkasanDanPembanding(): void
    {
        $summary = [
            'non_iklan' => 6, 'iklan' => 2, 'total' => 8, 'prospek' => 3,
            'datang' => 3, 'rate' => 37.5, 'total_lead_wa_dm' => 8,
            'lead_total_iklan_dashboard' => 0, 'total_days' => 1,
        ];

        $html = view('marketing/rekap', [
            'tanggal' => '2026-09-01',
            'unitId' => 1,
            'units' => [(object)['idunit' => 1, 'NAMA_UNIT' => 'ICLEAR Jember']],
            'rekap' => ['header' => null, 'details' => []],
            'summary' => $summary,
            'filterUnitId' => 1,
            'filterUnitName' => 'ICLEAR Jember',
            'cmpA' => '2026-09',
            'cmpB' => '2026-08',
            'cmpSummaryA' => $summary,
            'cmpSummaryB' => array_merge($summary, ['total' => 5, 'rate' => 25.0]),
            'platforms' => [(object)['name' => 'WhatsApp']],
            'bulanR' => 9,
            'tahunR' => 2026,
            'rekaps' => [],
            'canWrite' => false,
        ]);

        $this->assertStringContainsString('Ringkasan September 2026', $html);
        $this->assertStringContainsString('Bandingkan Bulan', $html);
        $this->assertStringContainsString('ICLEAR Jember', $html);
    }
}
