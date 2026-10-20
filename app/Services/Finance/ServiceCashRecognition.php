<?php

namespace App\Services\Finance;

final class ServiceCashRecognition
{
    public static function dateExpression(string $serviceAlias = 'service'): string
    {
        return "COALESCE({$serviceAlias}.tanggal_selesai, (SELECT recognized.first_date FROM "
            . self::journalSummary() . " recognized WHERE recognized.service_id = {$serviceAlias}.idservice))";
    }

    public static function recognizedExpression(string $serviceAlias = 'service'): string
    {
        return "({$serviceAlias}.status_service = 4 OR ({$serviceAlias}.status_service = 2 "
            . "AND EXISTS (SELECT 1 FROM " . self::journalSummary()
            . " recognized WHERE recognized.service_id = {$serviceAlias}.idservice)))";
    }

    private static function journalSummary(): string
    {
        return "(SELECT j.id_referensi + 0 AS service_id, MIN(j.tanggal) AS first_date "
            . "FROM jurnal j WHERE j.tabel_referensi = 'service' GROUP BY j.id_referensi + 0)";
    }
}
