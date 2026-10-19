<?php

namespace App\Services\Finance;

final class ServiceCashRecognition
{
    public static function dateExpression(string $serviceAlias = 'service'): string
    {
        return "COALESCE({$serviceAlias}.tanggal_selesai, (SELECT MIN(j.tanggal) FROM jurnal j "
            . "WHERE j.tabel_referensi = 'service' AND j.id_referensi = {$serviceAlias}.idservice))";
    }

    public static function recognizedExpression(string $serviceAlias = 'service'): string
    {
        return "({$serviceAlias}.status_service = 4 OR ({$serviceAlias}.status_service = 2 "
            . "AND EXISTS (SELECT 1 FROM jurnal j WHERE j.tabel_referensi = 'service' "
            . "AND j.id_referensi = {$serviceAlias}.idservice)))";
    }
}
