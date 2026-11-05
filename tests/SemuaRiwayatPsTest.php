<?php

namespace Tests;

use App\Controllers\RiwayatPresensi;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Riwayat absensi admin menggabungkan sesi PAGI/SORE shift PS menjadi satu baris.
 *
 * @internal
 */
final class SemuaRiwayatPsTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['form', 'url']);
    }

    private function baris(array $ubah = []): object
    {
        return (object) ($ubah + [
            'idpresensi'         => 1,
            'akun_idakun'        => 10,
            'sesi'               => 'FULL',
            'waktu_masuk'        => '2026-11-02 08:50:00',
            'waktu_pulang'       => '2026-11-02 17:00:00',
            'created_at'         => '2026-11-02 08:50:00',
            'jam_jadwal_masuk'   => '08:45:00',
            'jam_jadwal_pulang'  => '17:00:00',
            'jam_toleransi'      => '08:59:00',
            'status_kehadiran'   => 0,
            'status_absensi'     => 1,
            'NAMA_AKUN'          => 'Budi',
            'NAMA_UNIT'          => 'Unit Satu',
            'ID_UNIT'            => 1,
            'ID_JABATAN'         => 36,
            'jmmasuk'            => null,
            'toleransi'          => null,
            'ip'                 => '127.0.0.1',
            'lat'                => '-8.1',
            'long'               => '113.7',
            'LATITUDE'           => '-8.1',
            'LONGTITUDE'         => '113.7',
            'jarak'              => 5,
            'sumber'             => 'mobile',
            'foto'               => '202611/masuk.jpg',
            'foto_pulang'        => '202611/pulang.jpg',
        ]);
    }

    private function pagi(): object
    {
        return $this->baris([
            'idpresensi'        => 1,
            'sesi'              => 'PAGI',
            'waktu_pulang'      => '2026-11-02 13:00:00',
            'jam_jadwal_pulang' => '13:00:00',
            'status_kehadiran'  => 0,
            'foto_pulang'       => '',
        ]);
    }

    private function sore(): object
    {
        return $this->baris([
            'idpresensi'        => 2,
            'sesi'              => 'SORE',
            'waktu_masuk'       => '2026-11-02 16:55:00',
            'waktu_pulang'      => '2026-11-02 21:00:00',
            'jam_jadwal_masuk'  => '16:55:00',
            'jam_jadwal_pulang' => '21:00:00',
            'jam_toleransi'     => '17:09:00',
            'status_kehadiran'  => 2,
        ]);
    }

    public function testPagiDanSoreDigabungJadiSatuBaris(): void
    {
        $hasil = RiwayatPresensi::gabungSesiPs([$this->pagi(), $this->sore()]);

        $this->assertCount(1, $hasil);
        $row = $hasil[0];
        $this->assertTrue($row->ps);
        $this->assertSame('PS', $row->sesi);
        $this->assertSame(1, (int) $row->ps_pagi->idpresensi);
        $this->assertSame(2, (int) $row->ps_sore->idpresensi);
        $this->assertSame('2026-11-02 08:50:00', $row->waktu_masuk);
        $this->assertSame('2026-11-02 21:00:00', $row->waktu_pulang);
        $this->assertSame(2, (int) $row->status_kehadiran, 'Kehadiran gabungan memakai yang terburuk.');
        $this->assertSame(1, (int) $row->status_absensi);
    }

    public function testShiftLainTidakDigabung(): void
    {
        $full = $this->baris(['idpresensi' => 3]);
        $hasil = RiwayatPresensi::gabungSesiPs([$full]);

        $this->assertCount(1, $hasil);
        $this->assertEmpty($hasil[0]->ps ?? null);
        $this->assertSame('FULL', $hasil[0]->sesi);
    }

    public function testPagiSajaTetapSatuBarisPs(): void
    {
        $hasil = RiwayatPresensi::gabungSesiPs([$this->pagi()]);

        $this->assertCount(1, $hasil);
        $this->assertTrue($hasil[0]->ps);
        $this->assertNotNull($hasil[0]->ps_pagi);
        $this->assertNull($hasil[0]->ps_sore);
    }

    public function testViewPsHanyaSatuBarisDanPunyaDetailDuaSesi(): void
    {
        $presensi = RiwayatPresensi::gabungSesiPs([$this->pagi(), $this->sore()]);

        $html = (string) view('riwayat/semua_presensi', [
            'presensi'      => $presensi,
            'seragam'       => [],
            'bolehSeragam'  => [],
        ]);

        $this->assertSame(1, substr_count($html, 'class="ra-action js-ra-detail'));
        $this->assertStringContainsString('Pagi / Sore', $html);
        $this->assertStringContainsString('08:50', $html);
        $this->assertStringContainsString('16:55', $html);

        $this->assertSame(1, preg_match('/data-ps="([^"]+)"/', $html, $cocok));
        $detail = json_decode(html_entity_decode($cocok[1], ENT_QUOTES), true);
        $this->assertCount(2, $detail);
        $this->assertSame(['Pagi', 'Sore'], array_column($detail, 'label'));
        $this->assertSame('202611/masuk.jpg', $detail[0]['foto']);
        $this->assertSame('202611/masuk.jpg', $detail[1]['foto']);
    }

    public function testViewNonPsTidakMembawaDataPs(): void
    {
        $html = (string) view('riwayat/semua_presensi', [
            'presensi'      => [$this->baris()],
            'seragam'       => [],
            'bolehSeragam'  => [],
        ]);

        $this->assertSame(1, substr_count($html, 'class="ra-action js-ra-detail'));
        $this->assertStringNotContainsString('data-ps=', $html);
    }
}
