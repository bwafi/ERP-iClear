<?php

namespace App\Services\Service;

use DateTimeImmutable;
use DateTimeZone;

final class WarrantyClaimEligibility
{
    public static function allows(object $service, DateTimeImmutable $today): bool
    {
        if ((int) ($service->status_service ?? 0) !== 4 || (int) ($service->garansi_hari ?? 0) <= 0) {
            return false;
        }

        $completedAt = (string) ($service->tanggal_selesai ?? '');
        if (! preg_match('/^[12]\d{3}-\d{2}-\d{2}/', $completedAt)
            || (int) substr($completedAt, 0, 4) < 1971) {
            return false;
        }

        $timezone = new DateTimeZone('Asia/Jakarta');
        $completedDate = DateTimeImmutable::createFromFormat('!Y-m-d', substr($completedAt, 0, 10), $timezone);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if ($completedDate === false
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
            return false;
        }

        $expiresOn = $completedDate->modify('+' . (int) $service->garansi_hari . ' days');

        return $today->setTimezone($timezone)->setTime(0, 0) <= $expiresOn;
    }
}
