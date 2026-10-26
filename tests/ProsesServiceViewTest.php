<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Halaman proses service memakai tema .ps-*, tetap server-side, dan filter
 * unit mengikuti role.
 */
final class ProsesServiceViewTest extends CIUnitTestCase
{
    public function testMemakaiTemaPsDanDataTablesServerSide(): void
    {
        $html = view('riwayat/proses_service', [
            'unit' => [],
            'is_ho' => true,
            'user_unit' => 1,
        ]);

        $this->assertStringContainsString('class="ps-page"', $html);
        $this->assertStringContainsString('max-width: none;', $html);
        $this->assertStringContainsString('id="psTable"', $html);
        $this->assertStringContainsString('serverSide: true', $html);
        $this->assertStringContainsString('proses_service/ajax', $html);
        $this->assertStringContainsString('id="exportForm"', $html);
        $this->assertStringContainsString('riwayat_service/export', $html);
        $this->assertStringContainsString('service/bisa_diambil', $html);
        $this->assertStringContainsString('service/sudah_diambil', $html);
        $this->assertStringContainsString('service/dibatalkan', $html);
        $this->assertStringContainsString('service/toggle_prioritas', $html);
        $this->assertStringContainsString('update_status_proses', $html);
        $this->assertStringContainsString('solar:star-linear', $html);
        $this->assertStringContainsString('Cetak nota', $html);
        $this->assertStringContainsString('Cetak struk thermal', $html);
        $this->assertStringContainsString('>Nota', $html);
        $this->assertStringContainsString('>Struk', $html);
        $this->assertStringContainsString("data-label', 'Prioritas'", $html);
        $this->assertStringContainsString("data-label', 'Aksi'", $html);
        $this->assertStringContainsString('td.dataTables_empty .ps-empty', $html);
        $this->assertStringContainsString('Belum Dicek', $html);
        $this->assertStringContainsString('Menunggu Sparepart', $html);
    }

    public function testRoleLintasUnitMendapatFilterUnit(): void
    {
        $html = view('riwayat/proses_service', [
            'unit' => [
                (object) ['idunit' => 1, 'NAMA_UNIT' => 'Unit Tes'],
                (object) ['idunit' => 2, 'NAMA_UNIT' => 'Unit Dua'],
            ],
            'is_ho' => true,
            'user_unit' => 1,
        ]);

        $this->assertStringContainsString('<select id="unitFilter"', $html);
        $this->assertStringContainsString('<option value="1">Unit Tes</option>', $html);
        $this->assertStringContainsString('<option value="2">Unit Dua</option>', $html);
        $this->assertStringContainsString('Semua unit', $html);
    }

    public function testRoleUnitTerkunciTidakMendapatSelectUnit(): void
    {
        $html = view('riwayat/proses_service', [
            'unit' => [],
            'is_ho' => false,
            'user_unit' => 1,
        ]);

        $this->assertStringNotContainsString('<select id="unitFilter"', $html);
        $this->assertStringContainsString('class="ps-locked"', $html);
    }
}
