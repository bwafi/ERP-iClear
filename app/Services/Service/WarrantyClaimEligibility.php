<?php

namespace App\Services\Service;

use DateTimeImmutable;
use DateTimeZone;

final class WarrantyClaimEligibility
{
    public static function allows(object $service, DateTimeImmutable $today): bool
    {
        if ((int) ($service->status_service ?? 0) !== 4) {
            return false;
        }

        $snapshot = isset($service->garansi_opsi_snapshot) && $service->garansi_opsi_snapshot !== ''
            ? (string) $service->garansi_opsi_snapshot
            : null;
        if ($snapshot !== null && ! WarrantyTerm::isValid($snapshot)) {
            return false;
        }
        if ($snapshot === 'non_garansi') {
            return false;
        }

        $timezone = new DateTimeZone('Asia/Jakarta');
        $start = WarrantyTerm::startDate($service);
        if ($start === null) {
            return false;
        }
        $claimDay = $today->setTimezone($timezone)->setTime(0, 0);
        $startDay = DateTimeImmutable::createFromFormat('!Y-m-d', $start, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if (! $startDay || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $claimDay < $startDay) {
            return false;
        }
        if ($snapshot === 'selamanya') {
            return true;
        }

        $end = WarrantyTerm::expiryDate($service);
        if ($end === null) {
            $legacyDays = (int) ($service->garansi_hari ?? 0);
            if ($snapshot !== null || $legacyDays <= 0) {
                return false;
            }
            $end = (new DateTimeImmutable($start, $timezone))
                ->modify('+' . $legacyDays . ' days')->format('Y-m-d');
        }

        $claimDate = DateTimeImmutable::createFromFormat('!Y-m-d', $end, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if (! $claimDate || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return false;
        }

        return $claimDay <= $claimDate;
    }
}
