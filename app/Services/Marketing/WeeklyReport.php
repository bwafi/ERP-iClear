<?php

namespace App\Services\Marketing;

/**
 * Aturan laporan mingguan Digital Marketing.
 *
 * Bulan selalu dibagi 4: 1–7, 8–14, 15–21, 22–akhir bulan. Batas kirim
 * 2 hari kalender setelah minggu berakhir. Skor = bagian minggu yang
 * dikirim sebelum batas, dari minggu yang batasnya SUDAH lewat.
 */
class WeeklyReport
{
    /** Zona bisnis; `appTimezone` framework UTC, jadi tanggal dihitung di sini. */
    public const ZONA = 'Asia/Jakarta';

    public static function today(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(self::ZONA)))->format('Y-m-d');
    }

    public static function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(self::ZONA)))->format('Y-m-d H:i:s');
    }

    public static function range(int $month, int $year, int $minggu): array
    {
        $mulai = [1 => 1, 2 => 8, 3 => 15, 4 => 22][$minggu] ?? 1;
        $akhir = [1 => 7, 2 => 14, 3 => 21, 4 => (int)date('t', mktime(0, 0, 0, $month, 1, $year))][$minggu] ?? 7;

        return [
            'mulai'   => sprintf('%04d-%02d-%02d', $year, $month, $mulai),
            'selesai' => sprintf('%04d-%02d-%02d', $year, $month, $akhir),
        ];
    }

    /** Batas kirim: akhir minggu + 2 hari kalender (batas = akhir hari). */
    public static function deadline(int $month, int $year, int $minggu): string
    {
        return date('Y-m-d', strtotime(self::range($month, $year, $minggu)['selesai'] . ' +2 days'));
    }

    public static function sudahBerakhir(int $month, int $year, int $minggu, string $today): bool
    {
        return $today > self::range($month, $year, $minggu)['selesai'];
    }

    /**
     * @param array<int,string> $terkirim minggu => submitted_at (Y-m-d H:i:s)
     */
    public static function score(int $month, int $year, array $terkirim, string $today): ?float
    {
        $total = 0;
        $tepat = 0;

        for ($minggu = 1; $minggu <= 4; $minggu++) {
            $deadline = self::deadline($month, $year, $minggu);
            if ($today <= $deadline) {
                continue; // batas belum lewat → belum dinilai
            }

            $total++;
            $at = $terkirim[$minggu] ?? null;
            if ($at !== null && $at !== '' && substr((string)$at, 0, 10) <= $deadline) {
                $tepat++;
            }
        }

        return $total === 0 ? null : round($tepat / $total * 100, 4);
    }
}
