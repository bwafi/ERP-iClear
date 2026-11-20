<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Kontrak halaman /riwayat_pembelian: DataTables server-side, filter unit
 * sesuai scope, dan modal detail yang diisi lewat AJAX.
 */
final class RiwayatPembelianPageTest extends CIUnitTestCase
{
    private function render(bool $bolehSemuaUnit, array $unit): string
    {
        return view('riwayat/pembelian', [
            'boleh_semua_unit' => $bolehSemuaUnit,
            'unit' => $unit,
        ]);
    }

    private function unit(int $id, string $nama): object
    {
        return (object) ['idunit' => $id, 'NAMA_UNIT' => $nama];
    }

    public function testMemakaiDataTablesServerSide(): void
    {
        $html = $this->render(true, [$this->unit(1, 'Unit Satu')]);

        $this->assertStringContainsString('serverSide: true', $html);
        $this->assertStringContainsString('riwayat_pembelian/ajax', $html);
        $this->assertStringContainsString('riwayat_pembelian/detail/', $html);
        $this->assertStringContainsString('id="pembelianTable"', $html);
        $this->assertStringContainsString("order: [[1, 'desc']]", $html);
    }

    public function testTidakAdaFilterGlobalDataTables(): void
    {
        $html = $this->render(true, [$this->unit(1, 'Unit Satu')]);

        $this->assertStringNotContainsString(
            'dataTable.ext.search',
            $html,
            'filter global menelan baris tabel detail; filter harus di server'
        );
        $this->assertStringNotContainsString('getDetailAll', $html);
    }

    public function testRoleLintasUnitMendapatOpsiSemuaUnit(): void
    {
        $html = $this->render(true, [$this->unit(1, 'Unit Satu'), $this->unit(2, 'Unit Dua')]);

        $this->assertStringContainsString('<option value="">Semua Unit</option>', $html);
        $this->assertStringNotContainsString('Akun Anda hanya dapat melihat unit ini.', $html);
        $this->assertMatchesRegularExpression('/<select id="unitFilter"[^>]*>/', $html);
        preg_match('/<select id="unitFilter"[^>]*>/', $html, $m);
        $this->assertStringNotContainsString('disabled', $m[0]);
    }

    public function testRoleUnitTerkunciKeUnitnya(): void
    {
        $html = $this->render(false, [$this->unit(2, 'Unit Dua')]);

        $this->assertStringNotContainsString('Semua Unit', $html);
        $this->assertStringContainsString('Akun Anda hanya dapat melihat unit ini.', $html);
        preg_match('/<select id="unitFilter"[^>]*>/', $html, $m);
        $this->assertStringContainsString('disabled', $m[0]);
        $this->assertStringContainsString('<option value="2">Unit Dua</option>', $html);
    }

    public function testModalDetailMemuatRincianBarang(): void
    {
        $html = $this->render(true, [$this->unit(1, 'Unit Satu')]);

        $this->assertStringContainsString('id="detailModal"', $html);
        $this->assertStringContainsString('id="detailItems"', $html);
        $this->assertStringContainsString('id="detailBatch"', $html);
        $this->assertStringContainsString('id="detailLoading"', $html);
        $this->assertStringContainsString('id="detailError"', $html);
        $this->assertStringContainsString('js-detail', $html);
    }

    public function testFilterTanggalDanUnitDikirimKeServer(): void
    {
        $html = $this->render(true, [$this->unit(1, 'Unit Satu')]);

        $this->assertStringContainsString('d.startDate = startDateInput.value;', $html);
        $this->assertStringContainsString('d.endDate = endDateInput.value;', $html);
        $this->assertStringContainsString('d.unitFilter = unitFilter.value;', $html);
    }
}
