<?php

namespace App\Services\Kpi;

use App\Models\ModelAuth;

/**
 * EvaluatorAuthorizationService — PENENTUAN PENGECEKAN EVALUATOR
 *
 * Otorisasi berbasis (evaluator JABATAN -> target JABATAN -> KOMPONEN).
 * Sumber tunggal: COMPONENT_RULES. Core untuk absensi harian & Kualitas Pelayanan.
 *
 * ATURAN CABANG:
 *   - Admin Cabang (35): KEHADIRAN utk Teknisi(36), Kepala Toko(41), dirinya(35),
 *                        dan CS(42) — khusus Admin/Kasir yang berada di Unit 1.
 *   - Kepala Toko (41): non-Kehadiran utk Admin Cabang(35), Teknisi(36),
 *                       serta CS(42) — khusus Kepala Toko yang berada di Unit 1.
 *   - Kepala Toko (41) di UNIT 1 (ICLEAR Probolinggo) merangkap Admin/Kasir,
 *     jadi SELAIN non-Kehadiran ia juga boleh mengisi KEHADIRAN utk Admin/Kasir(35),
 *     Teknisi(36), CS(42), dan dirinya sendiri(41). Kepala Toko unit lain TIDAK
 *     mendapat tambahan akses ini (lihat UNIT_EXTRA_COMPONENT_RULES).
 *   - SPV (40)        : non-Kehadiran utk Kepala Toko(41).
 *
 * ATURAN PUSAT (HQ):
 *   - SPV(40), Kepala Divisi(43), IT(45), Finance(0):
 *       KEHADIRAN dinilai Finance, selain kehadiran dinilai Manager(34).
 *   - Multimedia(44): KEHADIRAN dinilai Finance, selain kehadiran dinilai Kadiv(43).
 *   - CS(42): KEHADIRAN dinilai Admin/Kasir Unit 1, selain kehadiran dinilai Kadiv(43).
 *   - Manager(34): KEHADIRAN dinilai Finance; selain kehadiran TIDAK diinput manual,
 *       melainkan dihitung otomatis = rata-rata skor absen per komponen dari
 *       SPV(40), Kadiv(43), IT(45), dan Finance(0)
 *       (lihat AttendanceAggregationService::managerTeamAverage).
 *
 * Role tanpa aturan spesifik: TIDAK bisa menilai siapa pun (diri sendiri pun tidak) —
 * hanya melihat data sendiri secara read-only.
 * Role Admin root(1) & Direktur(2): akses penuh (tidak dibatasi).
 */
class EvaluatorAuthorizationService
{
    /** Komponen absensi selain Kehadiran (manual). */
    private const NON_HADIR = [
        'KEBERSIHAN',
        'SERAGAM',
        'KEPATUHAN_SOP',
        'KUALITAS_PELAYANAN',
    ];

    const HADIR = ['KEHADIRAN'];

    /** Jabatan pusat: boleh dinilai lintas unit (tidak terikat unit evaluator). */
    private const HQ_TARGET_JABATANS = [
        0,  // Finance
        34, // Manager
        40, // SPV (berada di cabang, tapi dinilai pusat lintas unit)
        42, // Customer Service
        43, // Kepala Divisi
        44, // Multimedia
        45, // Team IT
    ];

    /** Supervisor/Manajemen pusat yang listing-nya lintas unit. */
    private const LINTAS_UNIT_EVALUATOR = [
        0,  // Finance
        34, // Manager
        43, // Kepala Divisi
        45, // Team IT
    ];

    /**
     * Tambahan komponen yang HANYA berlaku bila evaluator berada di unit tertentu.
     * Struktur: unit_id => evaluatorJabatan => targetJabatan => [kode komponen].
     *
     * Kepala Toko (41) di Unit 1 (ICLEAR Probolinggo) merangkap Admin/Kasir,
     * sehingga ia mengisi KEHADIRAN (tidak hanya komponen non-Kehadiran) untuk
     * Admin/Kasir, Teknisi, CS, dan dirinya sendiri. Unit lain tidak tersentuh.
     */
    private const UNIT_EXTRA_COMPONENT_RULES = [
        1 => [
            41 => [
                35 => self::HADIR, // Admin/Kasir unit 1
                36 => self::HADIR, // Teknisi unit 1
                41 => self::HADIR, // dirinya sendiri
                42 => self::HADIR, // Customer Service
            ],
        ],
    ];

    /**
     * Matriks komponen yang boleh dinilai: evaluatorJabatan => targetJabatan => [kode komponen].
     *
     * CATATAN: skor Manager "selain kehadiran" TIDAK diinput manual oleh siapa pun —
     * dihitung otomatis sebagai rata-rata skor absen per komponen dari SPV, Kepala
     * Divisi, IT, dan Finance (lihat AttendanceAggregationService::managerTeamAverage).
     * Karena itu Manager (34) TIDAK menjadi target input SPV/Kadiv/IT; Finance
     * tetap menginput KEHADIRAN Manager.
     */
    private const COMPONENT_RULES = [
        // Admin Cabang / Kasir (35): hanya Kehadiran.
        35 => [
            35 => self::HADIR,      // dirinya sendiri
            36 => self::HADIR,      // Teknisi
            41 => self::HADIR,      // Kepala Toko
            42 => self::HADIR,      // CS (khusus Admin/Kasir Unit 1)
        ],
        // Kepala Toko (41): non-Kehadiran utk Admin, Teknisi, dan CS (Unit 1).
        41 => [
            35 => self::NON_HADIR,
            36 => self::NON_HADIR,
            42 => self::NON_HADIR,
        ],
        // SPV (40): non-Kehadiran utk Kepala Toko.
        40 => [
            41 => self::NON_HADIR,
        ],
        // Finance (0): Kehadiran utk target pusat; KEHADIRAN utk Manager.
        // (Selain kehadiran Manager = rata-rata otomatis, bukan input manual.)
        0 => [
            0  => self::HADIR,                          // Finance (self)
            34 => self::HADIR,                          // Manager (hanya Kehadiran)
            40 => self::HADIR,                          // SPV
            43 => self::HADIR,                          // Kepala Divisi
            44 => self::HADIR,                          // Multimedia
            45 => self::HADIR,                          // Team IT
        ],
        // Manager (34): non-Kehadiran utk Finance, SPV, Kadiv, IT.
        34 => [
            0  => self::NON_HADIR,
            40 => ['KEBERSIHAN', 'SERAGAM', 'KEPATUHAN_SOP', 'KUALITAS_PELAYANAN', 'CUSTOMER_SATISFACTION'],
            43 => self::NON_HADIR,
            45 => self::NON_HADIR,
        ],
        // Kepala Divisi (43): non-Kehadiran utk CS, Multimedia.
        43 => [
            42 => ['KEBERSIHAN', 'SERAGAM', 'KEPATUHAN_SOP', 'KUALITAS_PELAYANAN', 'UPSELLING', 'FOLLOWUP'],
            44 => self::NON_HADIR,
        ],
    ];

    /**
     * Apakah jabatan target termasuk kategori pusat (boleh lintas unit)?
     */
    public static function isHqTargetJabatan(int $jabatan): bool
    {
        return in_array($jabatan, self::HQ_TARGET_JABATANS, true);
    }

    /**
     * Apakah evaluator jabatan punya akses lintas unit (HQ)?
     */
    public static function isLintasUnitEvaluator(int $jabatan): bool
    {
        return in_array($jabatan, self::LINTAS_UNIT_EVALUATOR, true);
    }

    /**
     * Daftar jabatan target yang boleh dievaluasi oleh sebuah jabatan.
     *
     * @param int|null $evaluatorUnit unit evaluator; bila diisi, sertakan jabatan
     *                               target tambahan dari UNIT_EXTRA_COMPONENT_RULES.
     *
     * @return int[]
     */
    public static function allowedTargetJabatans(int $evaluatorJabatan, ?int $evaluatorUnit = null): array
    {
        $targets = array_keys(self::COMPONENT_RULES[$evaluatorJabatan] ?? []);

        if ($evaluatorUnit !== null) {
            foreach (array_keys(self::UNIT_EXTRA_COMPONENT_RULES[$evaluatorUnit][$evaluatorJabatan] ?? []) as $target) {
                $targets[] = $target;
            }
        }

        return array_values(array_unique(array_map('intval', $targets)));
    }

    /**
     * Komponen yang boleh diisi evaluator (jabatan+unit) untuk jabatan target tertentu.
     * Gabungan COMPONENT_RULES dengan tambahan khusus unit.
     *
     * @return string[]
     */
    public static function allowedComponents(int $evaluatorJabatan, int $evaluatorUnit, int $targetJabatan): array
    {
        $base  = self::COMPONENT_RULES[$evaluatorJabatan][$targetJabatan] ?? [];
        $extra = self::UNIT_EXTRA_COMPONENT_RULES[$evaluatorUnit][$evaluatorJabatan][$targetJabatan] ?? [];

        return array_values(array_unique(array_merge($base, $extra)));
    }

    /**
     * Cek apakah evaluator boleh menilai target (tanpa cek komponen).
     */
    public static function canEvaluate(int $evaluatorId, int $employeeId): bool
    {
        $evaluator = (new ModelAuth())->where('ID_AKUN', $evaluatorId)->first();
        $employee  = (new ModelAuth())->where('ID_AKUN', $employeeId)->first();

        if (!$evaluator || !$employee) {
            return false;
        }

        $evaluatorJabatan = (int)($evaluator->ID_JABATAN ?? 0);
        $employeeJabatan  = (int)($employee->ID_JABATAN ?? 0);
        $evaluatorUnit    = (int)($evaluator->ID_UNIT ?? 0);

        if (in_array($evaluatorJabatan, [1, 2], true)) {
            return true;
        }

        $allowed = self::allowedTargetJabatans($evaluatorJabatan, $evaluatorUnit);

        if (!in_array($employeeJabatan, $allowed, true)) {
            return false;
        }

        // CS (42) KEHADIRAN dinilai Admin/Kasir (35) di UNIT 1;
        // CS (42) non-Kehadiran dinilai Kepala Toko (41) yang berada di UNIT 1.
        if ($employeeJabatan === 42) {
            if (($evaluatorJabatan === 35 || $evaluatorJabatan === 41) && $evaluatorUnit !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cek apakah jabatan evaluator boleh menilai jabatan karyawan.
     */
    public static function canEvaluateJabatan(int $evaluatorJabatan, int $employeeJabatan): bool
    {
        $allowed = self::allowedTargetJabatans($evaluatorJabatan);

        return in_array($employeeJabatan, $allowed, true);
    }

    /**
     * Cek apakah evaluator boleh menilai EMPLOYEE untuk SUATU KOMPONEN KPI.
     * Otorisasi SERVER-SIDE paling spesifik: evaluator + target + komponen + scope unit.
     */
    public static function canEvaluateComponent(int $evaluatorId, int $employeeId, string $componentCode): bool
    {
        $evaluator = (new ModelAuth())->where('ID_AKUN', $evaluatorId)->first();
        $employee  = (new ModelAuth())->where('ID_AKUN', $employeeId)->first();

        if (!$evaluator || !$employee) {
            return false;
        }

        $evaluatorJabatan = (int)($evaluator->ID_JABATAN ?? 0);
        $employeeJabatan  = (int)($employee->ID_JABATAN ?? 0);
        $evaluatorUnit    = (int)($evaluator->ID_UNIT ?? 0);

        // Admin root & Direktur: akses penuh.
        if (in_array($evaluatorJabatan, [1, 2], true)) {
            return true;
        }

        // Komponen yang diizinkan = matriks dasar + tambahan khusus unit evaluator.
        $allowedComponents = self::allowedComponents($evaluatorJabatan, $evaluatorUnit, $employeeJabatan);

        if (empty($allowedComponents)) {
            return false;
        }

        // Kasus khusus: CS (42) KEHADIRAN dinilai Admin/Kasir (35) di UNIT 1;
        // CS (42) non-Kehadiran dinilai Kepala Toko (41) yang berada di UNIT 1.
        if ($evaluatorJabatan === 35 && $employeeJabatan === 42 && $evaluatorUnit !== 1) {
            return false;
        }
        if ($evaluatorJabatan === 41 && $employeeJabatan === 42 && $evaluatorUnit !== 1) {
            return false;
        }

        // Scope unit. Jabatan pusat boleh dinilai lintas unit.
        if (!self::isHqTargetJabatan($employeeJabatan)) {
            if ($evaluatorJabatan === 40) {
                $db = \Config\Database::connect();
                $mappings = $db->table('spv_units')
                    ->where('spv_id', $evaluatorId)
                    ->get()
                    ->getResultArray();

                $allowedUnits = !empty($mappings)
                    ? array_map('intval', array_column($mappings, 'unit_id'))
                    : [$evaluatorUnit];

                if (!in_array((int)($employee->ID_UNIT ?? 0), $allowedUnits, true)) {
                    return false;
                }
            } elseif ((int)($employee->ID_UNIT ?? 0) !== $evaluatorUnit) {
                return false;
            }
        }

        return in_array($componentCode, $allowedComponents, true);
    }
}