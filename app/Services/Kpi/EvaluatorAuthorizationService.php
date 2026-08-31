<?php

namespace App\Services\Kpi;

use App\Models\ModelAuth;

/**
 * EvaluatorAuthorizationService — PENENTUAN PENGECEKAN EVALUATOR
 *
 * Aturan (fixed, tidak dokumen di DB, bisa diedit sebutan config):
 *   1. Kepala Toko (jabatan 41)       boleh evaluasi: Admin (35) + Teknisi (36)
 *   2. SPV   (jabatan 40)           boleh evaluasi: Kepala Toko (41)
 *   3. Kepala Divisi (jabatan 43)    boleh evaluasi: Customer Service (42) + Multimedia (44) + Team IT (45)
 *
 * Semua jabatan lain tidak memiliki hak evaluasi khusus (default: only self).
 */
class EvaluatorAuthorizationService
{
    private const MAPPING = [
        41 => [35, 36], // Kepala Toko
        40 => [41],     // SPV
        43 => [42, 44, 45], // Kepala Divisi
    ];

    /**
     * Cek apah evaluator jabatan boleh mencatat evaluasi untuk employee jabatan.
     *
     * @param int $evaluatorId   ID akun evaluator
     * @param int $employeeId    ID akun employee yang diedit
     * @return bool
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

        $allowed = self::MAPPING[$evaluatorJabatan] ?? [];

        // Jika evaluator tidak punya aturan khusus, hanya boleh diri sendiri.
        if (empty($allowed)) {
            return $evaluatorJabatan === $employeeJabatan;
        }

        // Cek apakah jabatan karyawan ada di daftar terdaftar.
        return in_array($employeeJabatan, $allowed, true);
    }
}

