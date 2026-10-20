<?php

namespace App\Services\Finance;

final class ReportCalculationScope
{
    private static ?array $values = null;

    public static function run(callable $calculate)
    {
        $previous = self::$values;
        self::$values = [];
        try {
            return $calculate();
        } finally {
            self::$values = $previous;
        }
    }

    public static function remember(string $key, callable $calculate)
    {
        if (self::$values === null) {
            return $calculate();
        }
        if (!array_key_exists($key, self::$values)) {
            self::$values[$key] = $calculate();
        }
        return self::$values[$key];
    }
}
