<?php

namespace App\Services\Service;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Aturan klaim garansi service.
 *
 * Dipakai bersama oleh daftar garansi, aksi klaim, dan workbench supaya
 * tombol yang tampil sama dengan yang diizinkan server. Tanggal dihitung
 * date-only dengan zona WIB; batas garansi inklusif pada hari terakhir.
 */
final class GaransiClaimService
{
    private const ZONA = 'Asia/Jakarta';
    private const CROSS_UNIT_ROLES = [0, 1, 34, 40];

    /**
     * Hanya Admin Root, Manager, Finance, dan SPV yang boleh melihat
     * seluruh unit. Role toko lain tetap terkunci ke unit sesi.
     */
    public static function canSeeAllUnits(int $idJabatan): bool
    {
        return in_array($idJabatan, self::CROSS_UNIT_ROLES, true);
    }

    public static function isClaimed(object $service): bool
    {
        $claim = substr((string) ($service->tanggal_claim_garansi ?? ''), 0, 10);

        return $claim !== '' && !in_array($claim, ['0000-00-00', '1970-01-01'], true);
    }

    public static function warrantyEnd(object $service): ?DateTimeImmutable
    {
        $selesai = self::parseDate($service->tanggal_selesai ?? null);
        $hari = (int) ($service->garansi_hari ?? 0);

        if ($selesai === null || $hari <= 0) {
            return null;
        }

        return $selesai->modify("+{$hari} days");
    }

    public static function isWarrantyActive(object $service, ?DateTimeImmutable $today = null): bool
    {
        if ((int) ($service->status_service ?? 0) !== 4) {
            return false;
        }

        $end = self::warrantyEnd($service);

        return $end !== null && self::today($today) <= $end;
    }

    public static function isClaimable(object $service, ?DateTimeImmutable $today = null): bool
    {
        return ! self::isClaimed($service) && self::isWarrantyActive($service, $today);
    }

    public static function statusText(object $service, ?DateTimeImmutable $today = null): string
    {
        if (self::isClaimed($service)) {
            $claim = self::parseDate($service->tanggal_claim_garansi ?? null);

            return 'Diklaim pada ' . ($claim ? $claim->format('d-m-Y') : '-');
        }

        $end = self::warrantyEnd($service);
        if ($end === null) {
            return (int) ($service->garansi_hari ?? 0) > 0 ? 'Belum selesai' : 'Tidak ada garansi';
        }

        if ((int) ($service->status_service ?? 0) !== 4) {
            return 'Belum selesai (garansi sampai ' . $end->format('d-m-Y') . ')';
        }

        if (self::today($today) > $end) {
            return 'Kadaluarsa pada ' . $end->format('d-m-Y');
        }

        return (int) $service->garansi_hari . ' hari (aktif sampai ' . $end->format('d-m-Y') . ')';
    }

    /**
     * @param array<int, array{harga?: int|string, qty?: int|string, diskon?: int|string}> $items
     *
     * @return array{total:int, diskon:int, biaya:int, due:int}
     */
    public static function totals(array $items, int $biaya = 0): array
    {
        $kotor = 0;
        $diskon = 0;

        foreach ($items as $item) {
            $qty = max(0, (int) ($item['qty'] ?? 0));
            $harga = max(0, (int) ($item['harga'] ?? 0));
            $diskonItem = max(0, (int) ($item['diskon'] ?? 0));

            $kotor += $harga * $qty;
            $diskon += min($diskonItem, $harga * $qty);
        }

        $biaya = max(0, $biaya);
        $net = max(0, $kotor - $diskon);

        return [
            'total' => $net + $biaya,
            'diskon' => $diskon,
            'biaya' => $biaya,
            'due' => $net + $biaya,
        ];
    }

    private static function parseDate($value): ?DateTimeImmutable
    {
        $value = substr((string) ($value ?? ''), 0, 10);

        if ($value === '' || in_array($value, ['0000-00-00', '1970-01-01'], true)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone(self::ZONA));

        return $date instanceof DateTimeImmutable ? $date : null;
    }

    private static function today(?DateTimeImmutable $today): DateTimeImmutable
    {
        return $today !== null
            ? $today->setTime(0, 0, 0)
            : new DateTimeImmutable('today', new DateTimeZone(self::ZONA));
    }
}
