<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * /penilaian/absen: pegawai tanpa komponen yang boleh diisi tetap menampilkan
 * grid harian (read-only) plus peringatan, bukan menyembunyikan data.
 *
 * @internal
 */
final class PenilaianAbsenReadOnlyViewTest extends CIUnitTestCase
{
    private function render(array $allowed, bool $readOnlyView = true): string
    {
        return (string) view('penilaian/penilaian_absen', [
            'list_karyawan' => [],
            'selected_karyawan' => 401,
            'target' => (object) ['ID_AKUN' => 401, 'NAMA_AKUN' => 'Budi'],
            'targetUnitName' => 'Unit 1',
            'allowedComponentCodes' => $allowed,
            'readOnlyView' => $readOnlyView,
            'kpi' => [],
            'detail_absen' => [],
            'skor_total2' => 0,
            'attendanceComponents' => [
                (object) ['id' => 1, 'code' => 'KEHADIRAN', 'name' => 'Kehadiran'],
                (object) ['id' => 2, 'code' => 'KEBERSIHAN', 'name' => 'Kebersihan'],
                (object) ['id' => 3, 'code' => 'SERAGAM', 'name' => 'Seragam'],
                (object) ['id' => 4, 'code' => 'KEPATUHAN_SOP', 'name' => 'Kepatuhan SOP'],
            ],
            'existing' => [],
            'offDays' => [],
            'hariEfektif' => 20,
            'hariLibur' => 0,
            'jumlahHari' => 30,
            'attendanceDetails' => [],
            'bulan' => 11,
            'tahun' => 2026,
        ]);
    }

    public function testTanpaKomponenMenampilkanPeringatanDanGridTanpaForm(): void
    {
        $html = $this->render([]);

        $this->assertStringContainsString('Mode baca saja', $html);
        $this->assertStringContainsString('<section class="pa-ledger"', $html);
        $this->assertStringContainsString('Kebersihan', $html);
        $this->assertStringNotContainsString('/penilaian/absen/save', $html);
    }

    public function testDenganKomponenTetapMenampilkanForm(): void
    {
        $html = $this->render(['KEBERSIHAN']);

        $this->assertStringContainsString('/penilaian/absen/save', $html);
        $this->assertStringNotContainsString('Mode baca saja', $html);
    }

    public function testRoleNonSpvTanpaKomponenTetapMemakaiEmptyStateLama(): void
    {
        $html = $this->render([], false);

        $this->assertStringContainsString('Akses terbatas', $html);
        $this->assertStringNotContainsString('Mode baca saja', $html);
        $this->assertStringNotContainsString('<section class="pa-ledger"', $html);
    }
}
