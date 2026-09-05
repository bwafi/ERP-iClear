<?php

namespace App\Services\Kpi;

use Config\Database;
use App\Models\ModelAuth;

/**
 * ProduktivitasTeamCalculator — average of KPI scores of active
 * Admin (35) & Teknisi (36) employees in the same unit/toko.
 *
 * Nur menghitung employee AKTIF pada unit yang sama. Rata-rata skor KPI
 * (skor_total) diambil dari hasil perhitungan KpiCalculationService yang
 * sudah ada (tidak menduplikasi rumus KPI Teknisi/Admin).
 */
class ProduktivitasTeamCalculator implements KpiCalculatorInterface
{
    public function calculate($employeeId, $unitId, $month, $year)
    {
        $employees = $this->getActiveTeamMembers((int)$unitId);

        if (empty($employees)) {
            return 0.0;
        }

        $calculationService = new KpiCalculationService();

        $total = 0.0;
        $count = 0;

        foreach ($employees as $empId) {
            if ((int)$empId === (int)$employeeId) {
                continue; // jangan sertakan Kepala Toko itu sendiri
            }
            $result = $calculationService->calculateForSalary(
                (int)$empId,
                (string)$month,
                (string)$year,
                'penilaian_kinerja'
            );
            $total += (float) ($result['skor_total'] ?? 0);
            $count++;
        }

        if ($count <= 0) {
            return 0.0;
        }

        return round($total / $count, 4);
    }

    /**
     * Semua Admin(35) & Teknisi(36) aktif pada unit yang sama.
     */
    protected function getActiveTeamMembers(int $unitId): array
    {
        $db = Database::connect();
        $rows = $db->table('akun')
            ->select('ID_AKUN')
            ->whereIn('ID_JABATAN', [35, 36])
            ->where('ID_UNIT', $unitId)
            ->where('STATUS_PEGAWAI', 1)
            ->get()
            ->getResultArray();

        return array_map(fn($r) => (int)$r['ID_AKUN'], $rows);
    }
}
