<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Kontrak tampilan redesign /riwayat_pembelian: tema .rb-*, hero dengan chip
 * konteks, istilah "No. Nota", penanda sisa hutang dan PPN, serta kontrol
 * pencarian dan export yang tetap ada.
 */
final class RiwayatPembelianRedesignTest extends CIUnitTestCase
{
    private function render(bool $semua = true): string
    {
        return view('riwayat/pembelian', [
            'boleh_semua_unit' => $semua,
            'unit' => [(object) ['idunit' => 2, 'NAMA_UNIT' => 'Unit Dua']],
        ]);
    }

    public function testMemakaiTemaRbSendiri(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('--rb-accent-solid: #0b5f8a;', $html);
        $this->assertStringContainsString('class="rb-page"', $html);
        $this->assertStringContainsString('rb-hero', $html);
        $this->assertStringContainsString('rb-toolbar', $html);
        $this->assertStringContainsString('rb-table', $html);
    }

    public function testChipKonteksTersedia(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('id="rbCountValue"', $html);
        $this->assertStringContainsString('id="rbUnitChip"', $html);
        $this->assertStringContainsString('id="rbRangeChip"', $html);
    }

    public function testHeaderTabelMemakaiIstilahNota(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('>No. Nota<', $html);
        $this->assertStringContainsString('<caption class="visually-hidden">', $html);
        $this->assertStringContainsString('scope="col"', $html);
    }

    public function testSisaHutangDanPpnDirender(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('rb-sub--warn', $html);
        $this->assertStringContainsString('Sisa ', $html);
        $this->assertStringContainsString('PPN', $html);
    }

    public function testExportDanPencarianTetapAda(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('riwayat_pembelian/export', $html);
        $this->assertStringContainsString('id="rbSearch"', $html);
        $this->assertStringContainsString('aria-label="Cari nota pembelian"', $html);
    }

    public function testRingkasanModalTidakIkutTergulung(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('class="rb-summary"', $html);
        $this->assertMatchesRegularExpression('/\.rb-summary\s*\{[^}]*position:\s*sticky/s', $html);
        $this->assertMatchesRegularExpression('/\.rb-summary\s*\{[^}]*bottom:\s*0/s', $html);
    }
}
