<?php

namespace Tests;

use App\Services\DirekturUnitScopeService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Unit test pemetaan area Direktur (investor) — murni, tanpa DB.
 *
 * Direktur hanya memegang sebagian cabang; pemetaan ini dipakai bersama oleh
 * /omset_bulanan (TutupKasir) dan halaman penilaian kinerja (PenilaianKPI).
 */
class DirekturUnitScopeTest extends CIUnitTestCase
{
    public function testDirekturTerpetakanMengembalikanDaftarUnit(): void
    {
        // Wahid Alfarizki: Banyuwangi (3) + Genteng (5).
        $this->assertSame([3, 5], DirekturUnitScopeService::unitsFor(46));

        // Guruh Dwi Prasetyo: Pandaan (4).
        $this->assertSame([4], DirekturUnitScopeService::unitsFor(70));
    }

    public function testDirekturTanpaPemetaanMengembalikanNull(): void
    {
        $this->assertNull(DirekturUnitScopeService::unitsFor(99));
    }

    public function testFilterBranchesMembuangUnitDiLuarArea(): void
    {
        $cabang = [
            ['unit' => 1, 'nama' => 'ICLEAR Probolinggo', 'omset' => 10],
            ['unit' => 3, 'nama' => 'ICLEAR Banyuwangi', 'omset' => 20],
            ['unit' => 4, 'nama' => 'ICLEAR Pandaan', 'omset' => 30],
            ['unit' => 5, 'nama' => 'ICLEAR Genteng', 'omset' => 40],
        ];

        $hasil = DirekturUnitScopeService::filterBranches($cabang, DirekturUnitScopeService::unitsFor(46));

        $this->assertSame([3, 5], array_column($hasil, 'unit'), 'Hanya Banyuwangi & Genteng yang tersisa');
    }

    public function testFilterBranchesTanpaBatasMengembalikanSemua(): void
    {
        $cabang = [
            ['unit' => 1, 'omset' => 10],
            ['unit' => 3, 'omset' => 20],
        ];

        $this->assertSame($cabang, DirekturUnitScopeService::filterBranches($cabang, null));
    }
}
