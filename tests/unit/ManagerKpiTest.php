<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\Kpi\ManagerKpiService;

/**
 * Unit test agregasi KPI Manager (jabatan 34) — murni (tanpa DB).
 *
 * Aturan angka (desain 2026-10-18):
 *   - Anggota tanpa nilai = 0 (pembagi = semua anggota aktif).
 *   - SDM & Leadership dirata-rata PER JABATAN dulu, baru antar jabatan.
 *   - Tiap skor kontributor di-cap 100 sebelum dirata-rata.
 */
final class ManagerKpiTest extends CIUnitTestCase
{
    public function testCodesCoverSevenComponents()
    {
        $this->assertCount(7, ManagerKpiService::CODES);
    }

    public function testAverageOverMembersCountsMissingAsZero()
    {
        // 2 SPV aktif, hanya 1 punya nilai 80 → 80/2 = 40.
        $this->assertSame(40.0, ManagerKpiService::averageOverMembers([80.0], 2));
    }

    public function testAverageOverMembersCapsEachScoreAt100()
    {
        $this->assertSame(100.0, ManagerKpiService::averageOverMembers([150.0], 1));
    }

    public function testAverageOverMembersNullWhenNoMembers()
    {
        $this->assertNull(ManagerKpiService::averageOverMembers([], 0));
    }

    public function testAverageAcrossRolesAveragesPerRoleFirst()
    {
        // B2: (80+60+0+0)/4 = 35.
        $this->assertSame(35.0, ManagerKpiService::averageAcrossRoles([80.0, 60.0, 0.0, 0.0]));
    }

    public function testAverageAcrossRolesSkipsRoleWithoutMembers()
    {
        $this->assertSame(60.0, ManagerKpiService::averageAcrossRoles([80.0, null, 40.0]));
    }

    public function testSaveManualRejectsOutOfRange()
    {
        // Review Focus #4: nilai di luar 0-100 ditolak (short-circuit, tanpa DB).
        $svc  = new ManagerKpiService();
        $low  = $svc->saveManual(41, 'REPORTING_GOVERNANCE', 46, 10, 2026, -1.0);
        $high = $svc->saveManual(41, 'REPORTING_GOVERNANCE', 46, 10, 2026, 150.0);

        $this->assertFalse($low['success']);
        $this->assertFalse($high['success']);
    }
}
