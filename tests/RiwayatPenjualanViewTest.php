<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Halaman riwayat penjualan harus memakai DataTables server-side dan satu
 * modal detail yang diisi lewat AJAX, bukan modal per invoice.
 */
class RiwayatPenjualanViewTest extends CIUnitTestCase
{
    public function testMemakaiServerSideDanModalOnDemand(): void
    {
        $renderer = service('renderer');
        $renderer->setVar('unit', [
            (object) ['idunit' => 1, 'NAMA_UNIT' => 'Unit 1'],
        ]);

        $html = (string) $renderer->include('riwayat/penjualan');

        $this->assertStringContainsString('id="penjualanTable"', $html);
        $this->assertStringContainsString('serverSide: true', $html);
        $this->assertStringContainsString('riwayat_penjualan/ajax', $html);
        $this->assertStringContainsString('riwayat_penjualan/detail/', $html);
        $this->assertStringContainsString('id="detailModal"', $html);
        $this->assertStringNotContainsString('modalDetail', $html);
        $this->assertStringContainsString('Semua Unit', $html);
    }
}
