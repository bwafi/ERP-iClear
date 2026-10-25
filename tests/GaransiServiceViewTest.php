<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Halaman garansi service memakai tema .rsg-*, tetap server-side, dan
 * filter unit mengikuti role.
 */
final class GaransiServiceViewTest extends CIUnitTestCase
{
    public function testMemakaiTemaRsgDanDataTablesServerSide(): void
    {
        $html = view('riwayat/garansi_service', [
            'unit' => [],
            'can_see_all_units' => true,
        ]);

        $this->assertStringContainsString('class="rsg-page"', $html);
        $this->assertStringContainsString('max-width: none;', $html);
        $this->assertStringContainsString('id="gsTable"', $html);
        $this->assertStringContainsString('serverSide: true', $html);
        $this->assertStringContainsString('garansi_service/ajax', $html);
        $this->assertStringContainsString('id="modalRiwayatKlaim"', $html);
        $this->assertStringContainsString('id="exportForm"', $html);
        $this->assertStringContainsString('name="sumber" value="garansi"', $html);
        $this->assertStringContainsString('claim_garansi/detail', $html);
        $this->assertStringContainsString('riwayat-klaim', $html);
        $this->assertStringContainsString('Sudah Diklaim', $html);
        $this->assertStringContainsString('rsg-btn--claim:disabled', $html);
        $this->assertStringContainsString("data-label', 'Pelanggan'", $html);
        $this->assertStringContainsString("data-label', 'Aksi'", $html);
    }

    public function testRoleLintasUnitMendapatFilterUnit(): void
    {
        $html = view('riwayat/garansi_service', [
            'unit' => [
                (object) ['idunit' => 1, 'NAMA_UNIT' => 'Unit Tes'],
                (object) ['idunit' => 2, 'NAMA_UNIT' => 'Unit Dua'],
            ],
            'can_see_all_units' => true,
        ]);

        $this->assertStringContainsString('<select id="unitFilter"', $html);
        $this->assertStringContainsString('<option value="1">Unit Tes</option>', $html);
        $this->assertStringContainsString('<option value="2">Unit Dua</option>', $html);
        $this->assertStringNotContainsString('style="display:none">Unit</th>', $html);
    }

    public function testRoleUnitTerkunciTidakMendapatSelectUnit(): void
    {
        $html = view('riwayat/garansi_service', [
            'unit' => [],
            'can_see_all_units' => false,
        ]);

        $this->assertStringNotContainsString('<select id="unitFilter"', $html);
        $this->assertStringContainsString('class="rsg-locked"', $html);
        $this->assertStringContainsString('style="display:none">Unit</th>', $html);
    }
}
