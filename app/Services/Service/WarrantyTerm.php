<?php

namespace App\Services\Service;

use DateTimeImmutable;
use DateTimeZone;

final class WarrantyTerm
{
    public const UNSET = 'belum_diatur';
    public const OPTIONS = [
        'non_garansi' => 'Non Garansi',
        'hari_7' => 'Garansi 7 Hari',
        'bulan_1' => 'Garansi 1 Bulan',
        'bulan_6' => 'Garansi 6 Bulan',
        'tahun_1' => 'Garansi 1 Tahun',
        'tahun_2' => 'Garansi 2 Tahun',
        'selamanya' => 'Garansi Selamanya',
    ];

    public static function isValid(?string $code): bool
    {
        return $code !== null && isset(self::OPTIONS[$code]);
    }

    public static function label(?string $code, $legacyDays = null): string
    {
        if ($code === self::UNSET) {
            return 'Belum Diatur';
        }
        if (self::isValid($code)) {
            return self::OPTIONS[$code];
        }
        if ($code !== null && $code !== '') {
            return 'Nilai lama: ' . $code . ' (belum dikonversi)';
        }
        if ($legacyDays !== null && $legacyDays !== '') {
            return 'Nilai lama: ' . (int) $legacyDays . ' hari (belum dikonversi)';
        }
        return 'Belum Diatur';
    }

    public static function endDate(string $startDate, string $code): ?string
    {
        $start = self::parseDate($startDate);
        if (! $start || ! self::isValid($code)) {
            return null;
        }

        if ($code === 'non_garansi' || $code === 'selamanya') {
            return null;
        }
        if ($code === 'hari_7') {
            return $start->modify('+7 days')->format('Y-m-d');
        }

        $months = [
            'bulan_1' => 1,
            'bulan_6' => 6,
            'tahun_1' => 12,
            'tahun_2' => 24,
        ][$code] ?? null;
        if ($months === null) {
            return null;
        }

        $day = (int) $start->format('d');
        $targetMonth = $start->modify('first day of this month')->modify('+' . $months . ' months');
        $targetDay = min($day, (int) $targetMonth->format('t'));
        return $targetMonth->setDate(
            (int) $targetMonth->format('Y'),
            (int) $targetMonth->format('m'),
            $targetDay
        )->format('Y-m-d');
    }

    public static function startDate(object $service): ?string
    {
        $candidate = (string) ($service->garansi_mulai ?? '');
        if ($candidate === '') {
            $candidate = (string) ($service->tanggal_selesai ?? '');
        }
        $date = self::parseDate($candidate);
        return $date ? $date->format('Y-m-d') : null;
    }

    public static function expiryDate(object $service): ?string
    {
        $stored = (string) ($service->garansi_akhir ?? '');
        if ($stored !== '') {
            $date = self::parseDate($stored);
            return $date ? $date->format('Y-m-d') : null;
        }

        $start = self::startDate($service);
        if (! $start) {
            return null;
        }

        $code = isset($service->garansi_opsi_snapshot) ? (string) $service->garansi_opsi_snapshot : '';
        if (self::isValid($code)) {
            return self::endDate($start, $code);
        }

        $legacyDays = (int) ($service->garansi_hari ?? 0);
        if ($legacyDays <= 0) {
            return null;
        }
        $legacyStart = self::parseDate($start);
        return $legacyStart ? $legacyStart->modify('+' . $legacyDays . ' days')->format('Y-m-d') : null;
    }

    public static function compatibilityDays(?string $code): int
    {
        return [
            'non_garansi' => 0,
            'hari_7' => 7,
            'bulan_1' => 30,
            'bulan_6' => 180,
            'tahun_1' => 365,
            'tahun_2' => 730,
            'selamanya' => 0,
        ][$code ?? ''] ?? 0;
    }

    public static function describe(object $service, ?DateTimeImmutable $today = null): array
    {
        $code = isset($service->garansi_opsi_snapshot) && $service->garansi_opsi_snapshot !== ''
            ? (string) $service->garansi_opsi_snapshot
            : null;
        $legacyDays = $code === null && property_exists($service, 'garansi_hari')
            ? (int) $service->garansi_hari
            : null;
        $start = self::startDate($service);
        $end = self::expiryDate($service);
        $label = self::label($code, $legacyDays);
        $status = 'Belum Diatur';

        if ($code === self::UNSET) {
            $status = 'Belum Diatur';
        } elseif ($code === 'non_garansi') {
            $status = 'Non Garansi';
        } elseif ($code === null && $legacyDays === 0) {
            $status = 'Data lama 0 hari (klaim tidak memenuhi syarat)';
        } elseif ($code === 'selamanya') {
            if ((int) ($service->status_service ?? 0) !== 4) {
                $status = 'Belum Diambil';
            } elseif (! $start) {
                $status = 'Belum Dimulai';
            } else {
                $today = ($today ?? new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta')))
                    ->setTimezone(new DateTimeZone('Asia/Jakarta'))->setTime(0, 0);
                $startDate = self::parseDate($start);
                $status = $startDate && $today >= $startDate ? 'Aktif - Selamanya' : 'Belum Dimulai';
            }
        } elseif ($code !== null && ! self::isValid($code)) {
            $status = 'Nilai lama, perlu ditinjau';
        } elseif ($code === null && $legacyDays !== null && $legacyDays > 0) {
            $status = 'Garansi legacy ' . $legacyDays . ' hari';
        } elseif ($code !== null || ($legacyDays !== null && $legacyDays > 0)) {
            if ((int) ($service->status_service ?? 0) !== 4) {
                $status = 'Belum Diambil';
            } elseif (! $start || ! $end) {
                $status = 'Tanggal garansi belum tersedia';
            } else {
                $today = ($today ?? new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta')))
                    ->setTimezone(new DateTimeZone('Asia/Jakarta'))->setTime(0, 0);
                $endDate = self::parseDate($end);
                $status = $endDate && $today <= $endDate ? 'Aktif' : 'Kedaluwarsa';
            }
        }

        return [
            'label' => $label,
            'start_date' => $start,
            'end_date' => $end,
            'status' => $status,
        ];
    }

    private static function parseDate(string $value): ?DateTimeImmutable
    {
        if (! preg_match('/^[12]\d{3}-\d{2}-\d{2}/', $value) || (int) substr($value, 0, 4) < 1971) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10), new DateTimeZone('Asia/Jakarta'));
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }
        return $date;
    }
}
