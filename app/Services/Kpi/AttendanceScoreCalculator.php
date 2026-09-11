<?php

namespace App\Services\Kpi;

/**
 * Pure function untuk menghitung nilai absensi otomatis berdasarkan:
 * - Shift (PAGI, SIANG, PS)
 * - Sesi (PAGI, SORE, FULL)
 * - Jenis absensi (NORMAL, IZIN_TELAT)
 * - Jam masuk aktual vs jam mulai shift
 *
 * Implementasi aturan scoring sesuai dokumen requirement.
 */
class AttendanceScoreCalculator
{
    /**
     * Jam mulai shift per jenis.
     */
    public const SCHEDULED_TIMES = [
        'PAGI'  => '08:45',
        'SIANG' => '12:45',
        'PS'    => [
            'PAGI' => '08:45',
            'SORE' => '17:00',
        ],
    ];

    /**
     * Aturan scoring normal (tidak izin).
     * Menit keterlambatan → nilai (0-5).
     */
    private const NORMAL_SCORING = [
        ['max' =>  0, 'score' => 5],
        ['max' =>  3, 'score' => 4],
        ['max' =>  6, 'score' => 3],
        ['max' => 10, 'score' => 2],
        ['max' => 14, 'score' => 1],
        ['max' => PHP_INT_MAX, 'score' => 0],
    ];

    /**
     * Aturan scoring izin telat.
     * Durasi izin (menit) → nilai (0-5).
     */
    private const IZIN_TELAT_SCORING = [
        ['max' =>  5, 'score' => 5],
        ['max' => 15, 'score' => 4],
        ['max' => 30, 'score' => 3],
        ['max' => 40, 'score' => 1],
        ['max' => PHP_INT_MAX, 'score' => 0],
    ];

    /**
     * Hitung keterlambatan & nilai otomatis.
     *
     * @param string $shift PAGI|SIANG|PS
     * @param string $session PAGI|SORE|FULL
     * @param string $attendanceType NORMAL|IZIN_TELAT
     * @param string $scheduledTime Format HH:MM (08:45, 12:45, 17:00)
     * @param string $actualTime Format HH:MM
     * @return array{late_minutes:int, auto_score:float, scheduled_time:string}
     */
    public static function calculate(
        string $shift,
        string $session,
        string $attendanceType,
        string $scheduledTime,
        string $actualTime
    ): array {
        $lateMinutes = self::minutesLate($scheduledTime, $actualTime);
        $score = 0.0;

        if ($attendanceType === 'IZIN_TELAT') {
            $score = self::applyScoring(self::IZIN_TELAT_SCORING, $lateMinutes);
        } else {
            $score = self::applyScoring(self::NORMAL_SCORING, $lateMinutes);
        }

        return [
            'late_minutes'   => $lateMinutes,
            'auto_score'     => $score,
            'scheduled_time' => $scheduledTime,
        ];
    }

    /**
     * Ambil jam mulai shift berdasarkan shift & sesi.
     *
     * @param string $shift PAGI|SIANG|PS
     * @param string $session PAGI|SORE|FULL
     * @return string Jam schedule HH:MM
     */
    public static function getScheduledTime(string $shift, string $session): string
    {
        if ($shift === 'PS' && isset(self::SCHEDULED_TIMES['PS'][$session])) {
            return self::SCHEDULED_TIMES['PS'][$session];
        }

        if (isset(self::SCHEDULED_TIMES[$shift])) {
            return self::SCHEDULED_TIMES[$shift];
        }

        return '08:45'; // fallback
    }

    /**
     * Hitung keterlambatan dalam menit.
     * Negatif = masuk lebih awal.
     */
    private static function minutesLate(string $scheduled, string $actual): int
    {
        $scheduledSec = strtotime($scheduled) ?: 0;
        $actualSec    = strtotime($actual)    ?: 0;

        if ($scheduledSec === 0 || $actualSec === 0) {
            return 0;
        }

        $diff = $actualSec - $scheduledSec;
        $minutes = (int)ceil($diff / 60);

        return max(0, $minutes);
    }

    /**
     * Terapkan scoring array [max=>, score=>] untuk menit tertentu.
     */
    private static function applyScoring(array $rules, int $minutes): float
    {
        foreach ($rules as $rule) {
            if ($minutes <= $rule['max']) {
                return (float)$rule['score'];
            }
        }

        return 0.0;
    }

    /**
     * Skor dari total keterlambatan (menit) berdasarkan jenis absensi.
     * Dipakai untuk nilai harian PS: total telat = telat pagi + telat sore.
     *
     * @param int $lateMinutes Total keterlambatan dalam menit.
     * @param string $attendanceType NORMAL|IZIN_TELAT
     * @return float
     */
    public static function scoreForMinutes(int $lateMinutes, string $attendanceType): float
    {
        $rules = ($attendanceType === 'IZIN_TELAT')
            ? self::IZIN_TELAT_SCORING
            : self::NORMAL_SCORING;

        return self::applyScoring($rules, $lateMinutes);
    }
}