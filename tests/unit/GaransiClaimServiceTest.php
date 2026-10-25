<?php

namespace Tests\unit;

use App\Services\Service\GaransiClaimService;
use CodeIgniter\Test\CIUnitTestCase;
use DateTimeImmutable;
use DateTimeZone;

/**
 * @internal
 */
final class GaransiClaimServiceTest extends CIUnitTestCase
{
    private function service(array $override = []): object
    {
        return (object) array_merge([
            'status_service' => 4,
            'garansi_hari' => 30,
            'tanggal_selesai' => '2026-01-01 10:00:00',
            'tanggal_claim_garansi' => null,
        ], $override);
    }

    private function today(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, new DateTimeZone('Asia/Jakarta'));
    }

    public function testGaransiAktifSampaiHariTerakhir(): void
    {
        $service = $this->service();

        $this->assertTrue(GaransiClaimService::isClaimable($service, $this->today('2026-01-31')));
        $this->assertFalse(GaransiClaimService::isClaimable($service, $this->today('2026-02-01')));
    }

    public function testServiceBelumSelesaiTidakBisaDiklaim(): void
    {
        $this->assertFalse(GaransiClaimService::isClaimable(
            $this->service(['status_service' => 2]),
            $this->today('2026-01-10')
        ));
    }

    public function testServiceTanpaGaransiAtauTanggalSelesaiTidakBisaDiklaim(): void
    {
        $this->assertFalse(GaransiClaimService::isClaimable(
            $this->service(['garansi_hari' => 0]),
            $this->today('2026-01-10')
        ));
        $this->assertFalse(GaransiClaimService::isClaimable(
            $this->service(['tanggal_selesai' => null]),
            $this->today('2026-01-10')
        ));
    }

    public function testKlaimHanyaSekali(): void
    {
        $service = $this->service(['tanggal_claim_garansi' => '2026-01-05 08:00:00']);

        $this->assertTrue(GaransiClaimService::isClaimed($service));
        $this->assertFalse(GaransiClaimService::isClaimable($service, $this->today('2026-01-06')));
    }

    public function testStatusTextMembedakanAktifKadaluarsaDanSudahDiklaim(): void
    {
        $this->assertSame(
            '30 hari (aktif sampai 31-01-2026)',
            GaransiClaimService::statusText($this->service(), $this->today('2026-01-10'))
        );
        $this->assertSame(
            'Kadaluarsa pada 31-01-2026',
            GaransiClaimService::statusText($this->service(), $this->today('2026-02-01'))
        );
        $this->assertSame(
            'Diklaim pada 05-01-2026',
            GaransiClaimService::statusText(
                $this->service(['tanggal_claim_garansi' => '2026-01-05 08:00:00']),
                $this->today('2026-01-06')
            )
        );
    }

    public function testTotalKlaimDihitungDariHargaQtyDiskonDanBiaya(): void
    {
        $total = GaransiClaimService::totals([
            ['harga' => 100000, 'qty' => 2, 'diskon' => 5000],
            ['harga' => 50000, 'qty' => 1, 'diskon' => 0],
        ], 10000);

        $this->assertSame(245000, $total['total'] - $total['biaya']);
        $this->assertSame(5000, $total['diskon']);
        $this->assertSame(10000, $total['biaya']);
        $this->assertSame(255000, $total['total']);
        $this->assertSame(255000, $total['due']);
    }

    public function testDiskonTidakBolehMelebihiNilaiBaris(): void
    {
        $total = GaransiClaimService::totals([
            ['harga' => 1000, 'qty' => 1, 'diskon' => 5000],
        ]);

        $this->assertSame(1000, $total['diskon']);
        $this->assertSame(0, $total['due']);
    }
}
