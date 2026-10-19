<?php

namespace App\Services\Finance;

final class JournalReportFilters
{
    private const FILTER_KEYS = [
        'startDate',
        'endDate',
        'filterUnit',
        'no_akun',
        'tabel_referensi',
        'id_referensi',
    ];

    public static function fromArray(array $input): array
    {
        $filters = [];
        foreach (self::FILTER_KEYS as $key) {
            $filters[$key] = $input[$key] ?? null;
        }

        return $filters;
    }

    public static function forSource(array $filters, $unitId, string $table, $referenceId): array
    {
        $sourceFilters = [
            'startDate' => $filters['startDate'] ?? null,
            'endDate' => $filters['endDate'] ?? null,
            'filterUnit' => $unitId !== null && $unitId !== ''
                ? $unitId
                : ($filters['filterUnit'] ?? null),
            'tabel_referensi' => trim($table),
            'id_referensi' => (string) $referenceId,
        ];

        return array_filter(
            $sourceFilters,
            static fn ($value): bool => $value !== null && $value !== ''
        );
    }
}
