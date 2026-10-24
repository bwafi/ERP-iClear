<?php

namespace Tests;

use App\Services\Service\GaransiClaimService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Halaman riwayat service garansi: tanggal claim dipakai untuk sorting,
 * tombol riwayat klaim tersedia per baris, dan filter unit mengikuti role.
 */
final class RiwayatServiceGaransiViewTest extends CIUnitTestCase
{
    private function baris(array $override = []): object
    {
        return (object) array_merge([
            'idservice' => 99,
            'no_service' => 'SRV-99',
            'created_at' => '2026-10-20 09:00:00',
            'tanggal_claim_garansi' => '2026-10-24 10:02:12',
            'nama_pelanggan' => 'Pelanggan Tes',
            'no_hp' => '0811',
            'alamat' => 'Alamat',
            'status_service' => 2,
            'nama_unit' => 'Unit Tes',
        ], $override);
    }

    public function testTanggalClaimMemilikiDataOrderDanTombolKlaim(): void
    {
        $html = view('riwayat/service_garansi', [
            'service' => [$this->baris()],
            'unit' => [],
            'can_see_all_units' => false,
            'selected_unit' => 1,
            'selected_unit_name' => 'Unit Tes',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        $this->assertStringContainsString('data-order="2026-10-24 10:02:12"', $html);
        $this->assertStringContainsString('data-idservice="99"', $html);
        $this->assertStringContainsString('id="modalRiwayatKlaim"', $html);
        $this->assertStringContainsString('id="rsgTable"', $html);
        $this->assertStringContainsString('Riwayat Klaim', $html);
        $this->assertStringContainsString('cetak/invoice_service/99', $html);
        $this->assertStringContainsString('Dalam Pengerjaan', $html);

        $this->assertStringContainsString('name="tanggal_awal"', $html);
        $this->assertStringContainsString('value="2026-10-01"', $html);
        $this->assertStringContainsString('name="tanggal_akhir"', $html);
        $this->assertStringContainsString('value="2026-10-31"', $html);

        $this->assertStringNotContainsString('id="rsgUnit"', $html);
        $this->assertStringContainsString('aria-label="Unit terkunci: Unit Tes"', $html);
        $this->assertStringNotContainsString('data-label="Unit"', $html);
    }

    public function testRoleLintasUnitMelihatFilterDanKolomUnit(): void
    {
        $html = view('riwayat/service_garansi', [
            'service' => [$this->baris()],
            'unit' => [
                (object) ['idunit' => 1, 'NAMA_UNIT' => 'Unit Tes'],
                (object) ['idunit' => 2, 'NAMA_UNIT' => 'Unit Dua'],
            ],
            'can_see_all_units' => true,
            'selected_unit' => 0,
            'selected_unit_name' => 'Semua unit',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        $this->assertStringContainsString('id="rsgUnit"', $html);
        $this->assertStringContainsString('<option value="1"', $html);
        $this->assertStringContainsString('<option value="2"', $html);
        $this->assertStringContainsString('<th scope="col">Unit</th>', $html);
        $this->assertStringContainsString('data-label="Unit"', $html);
        $this->assertStringContainsString('Unit Tes', $html);
    }

    public function testUnitTerpilihMenyembunyikanKolomUnit(): void
    {
        $html = view('riwayat/service_garansi', [
            'service' => [$this->baris()],
            'unit' => [
                (object) ['idunit' => 1, 'NAMA_UNIT' => 'Unit Tes'],
                (object) ['idunit' => 2, 'NAMA_UNIT' => 'Unit Dua'],
            ],
            'can_see_all_units' => true,
            'selected_unit' => 2,
            'selected_unit_name' => 'Unit Dua',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        $this->assertStringContainsString('id="rsgUnit"', $html);
        $this->assertStringContainsString('<option value="2" selected>', $html);
        $this->assertStringNotContainsString('<th scope="col">Unit</th>', $html);
        $this->assertStringNotContainsString('data-label="Unit"', $html);
    }

    public function testTanpaDataMenampilkanEmptyState(): void
    {
        $html = view('riwayat/service_garansi', [
            'service' => [],
            'unit' => [],
            'can_see_all_units' => false,
            'selected_unit' => 1,
            'selected_unit_name' => 'Unit Tes',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        $this->assertStringContainsString('Belum ada klaim garansi', $html);
        $this->assertStringContainsString('is-empty', $html);
        $this->assertStringContainsString('placeholder="Tidak ada klaim pada periode ini"', $html);
        $this->assertStringContainsString('aria-label="Cari klaim garansi" autocomplete="off" disabled', $html);
        $this->assertStringNotContainsString('data-idservice', $html);
    }

    public function testHanyaRoleTertentuYangBolehMelihatSemuaUnit(): void
    {
        foreach ([0, 1, 34, 40] as $role) {
            $this->assertTrue(GaransiClaimService::canSeeAllUnits($role), 'Role ' . $role);
        }

        foreach ([2, 42, 999] as $role) {
            $this->assertFalse(GaransiClaimService::canSeeAllUnits($role), 'Role ' . $role);
        }
    }
}
