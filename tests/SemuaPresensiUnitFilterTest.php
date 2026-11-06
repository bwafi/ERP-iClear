<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Filter unit di /semua_riwayat_presensi: select hanya dirender untuk role
 * yang berhak, dan setiap baris membawa data-unit untuk penyaringan.
 *
 * @internal
 */
final class SemuaPresensiUnitFilterTest extends CIUnitTestCase
{
    private function render(array $data): string
    {
        return view('riwayat/semua_presensi', $data + [
            'presensi'      => [],
            'seragam'       => [],
            'bolehSeragam'  => [],
            'unit_filter'   => [],
            'show_unit_filter' => false,
        ]);
    }

    private function baris(int $unit = 4): object
    {
        return (object) [
            'akun_idakun'        => 10,
            'ID_UNIT'            => $unit,
            'ID_JABATAN'         => 36,
            'NAMA_AKUN'          => 'Pegawai Uji',
            'NAMA_UNIT'          => 'Cabang Uji',
            'waktu_masuk'        => '2026-11-06 08:00:00',
            'waktu_pulang'       => '2026-11-06 17:00:00',
            'created_at'         => '2026-11-06 08:00:00',
            'jam_jadwal_masuk'   => '08:00:00',
            'jmmasuk'            => '08:00:00',
            'jam_toleransi'      => '08:14:00',
            'toleransi'          => '08:14:00',
            'jam_jadwal_pulang'  => '17:00:00',
            'status_kehadiran'   => 0,
            'status_absensi'     => 0,
            'jarak'              => 10,
            'ip'                 => '127.0.0.1',
            'lat'                => '-7.1',
            'long'               => '112.1',
            'LATITUDE'           => '-7.1',
            'LONGTITUDE'         => '112.1',
            'idpresensi'         => 1,
            'sumber'             => 'mobile',
            'foto'               => '202611/a.jpg',
            'foto_pulang'        => null,
            'sesi'               => null,
        ];
    }

    public function testFilterUnitDirenderDenganDaftarUnit(): void
    {
        $html = $this->render([
            'unit_filter'      => [(object) ['idunit' => 1, 'NAMA_UNIT' => 'Cabang Satu']],
            'show_unit_filter' => true,
        ]);

        $this->assertStringContainsString('name="unit_idunit"', $html);
        $this->assertStringContainsString('Semua Unit', $html);
        $this->assertStringContainsString('Cabang Satu', $html);
    }

    public function testFilterUnitDisembunyikanTanpaHak(): void
    {
        $html = $this->render(['show_unit_filter' => false]);

        $this->assertStringNotContainsString('name="unit_idunit"', $html);
    }

    public function testBarisMembawaDataUnit(): void
    {
        $html = $this->render(['presensi' => [$this->baris(4)]]);

        $this->assertStringContainsString('data-unit="4"', $html);
    }
}
