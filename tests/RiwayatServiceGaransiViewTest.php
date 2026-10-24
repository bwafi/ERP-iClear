<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Halaman riwayat service garansi: tanggal claim dipakai untuk sorting
 * dan tombol riwayat klaim tersedia per baris.
 */
final class RiwayatServiceGaransiViewTest extends CIUnitTestCase
{
    public function testTanggalClaimMemilikiDataOrderDanTombolKlaim(): void
    {
        $html = view('riwayat/service_garansi', [
            'service' => [(object) [
                'idservice' => 99,
                'no_service' => 'SRV-99',
                'created_at' => '2026-10-20 09:00:00',
                'tanggal_claim_garansi' => '2026-10-24 10:02:12',
                'nama_pelanggan' => 'Pelanggan Tes',
                'no_hp' => '0811',
                'alamat' => 'Alamat',
                'status_service' => 2,
            ]],
        ]);

        $this->assertStringContainsString('data-order="2026-10-24 10:02:12"', $html);
        $this->assertStringContainsString('data-idservice="99"', $html);
        $this->assertStringContainsString('id="modalRiwayatKlaim"', $html);

        $hariIni = new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta'));
        $this->assertStringContainsString(
            'value="' . $hariIni->modify('first day of this month')->format('Y-m-d') . '"',
            $html
        );
        $this->assertStringContainsString(
            'value="' . $hariIni->modify('last day of this month')->format('Y-m-d') . '"',
            $html
        );
    }
}
