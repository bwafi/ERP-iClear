<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Filter unit di /approval_presensi: select hanya dirender untuk role yang
 * berhak, dan setiap baris membawa data-unit untuk penyaringan.
 *
 * @internal
 */
final class ApprovalIzinUnitFilterTest extends CIUnitTestCase
{
    private function render(array $data): string
    {
        return view('admin/approval_izin', $data + [
            'pengajuan'        => [],
            'unit_filter'      => [],
            'show_unit_filter' => false,
        ]);
    }

    private function baris(int $unit = 4): object
    {
        return (object) [
            'idizin'          => 1,
            'akun_idakun'     => 10,
            'ID_UNIT'         => $unit,
            'NAMA_AKUN'       => 'Pegawai Uji',
            'NAMA_UNIT'       => 'Cabang Uji',
            'tanggal_mulai'   => '2026-11-06',
            'tanggal_akhir'   => '2026-11-07',
            'jenis_izin'      => 'Sakit',
            'alasan'          => 'Demam',
            'status'          => 0,
            'balasan'         => null,
            'file'            => null,
        ];
    }

    public function testFilterUnitDirenderDenganDaftarUnit(): void
    {
        $html = $this->render([
            'unit_filter'      => [(object) ['idunit' => 1, 'NAMA_UNIT' => 'Cabang Satu']],
            'show_unit_filter' => true,
        ]);

        $this->assertStringContainsString('id="ap-unit"', $html);
        $this->assertStringContainsString('Semua Unit', $html);
        $this->assertStringContainsString('Cabang Satu', $html);
    }

    public function testFilterUnitDisembunyikanTanpaHak(): void
    {
        $html = $this->render(['show_unit_filter' => false]);

        $this->assertStringNotContainsString('id="ap-unit"', $html);
    }

    public function testBarisMembawaDataUnit(): void
    {
        $html = $this->render(['pengajuan' => [$this->baris(4)]]);

        $this->assertStringContainsString('data-unit="4"', $html);
    }
}
