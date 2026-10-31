<?php

namespace Tests;

use App\Controllers\PenilaianKPI;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Urutan komponen absen di /penilaian/absen: Kehadiran paling atas.
 *
 * @internal
 */
final class PenilaianAbsenOrderTest extends CIUnitTestCase
{
    private const KODE = ['KEHADIRAN', 'KEBERSIHAN', 'SERAGAM', 'KEPATUHAN_SOP'];

    public function testKehadiranSelaluPalingAtas(): void
    {
        $acak = array_map(
            static fn ($kode) => (object) ['code' => $kode, 'name' => $kode],
            ['SERAGAM', 'KEPATUHAN_SOP', 'KEHADIRAN', 'KEBERSIHAN']
        );

        $hasil = PenilaianKPI::urutkanKomponenAbsen($acak, self::KODE);

        $this->assertSame(
            ['KEHADIRAN', 'KEBERSIHAN', 'SERAGAM', 'KEPATUHAN_SOP'],
            array_column($hasil, 'code')
        );
    }
}
