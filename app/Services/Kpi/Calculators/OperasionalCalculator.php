<?php

namespace App\Services\Kpi\Calculators;

use App\Services\Kpi\OmsetTokoCalculator;

/**
 * OPERASIONAL Calculator for SPV (position_id = 40)
 * 
 * Business Rule (konteks tunggal):
 * - Count units (cabang) that reach their OMSET target (target_value)
 * - Map count to score: 1→25, 2→50, 3→75, 4→100, 0→0 (4-tier penilaian_kinerja)
 * - Only applies to SPV position
 * 
 * Source: LegacyKpiCalculationService.php:340-366
 */
class OperasionalCalculator
{
    protected $targetModel;
    protected $omsetCalc;

    public function __construct()
    {
        $this->targetModel = new \App\Models\ModelKpiTarget();
        $this->omsetCalc = new OmsetTokoCalculator();
    }

    /**
     * Calculate OPERASIONAL score for SPV based on cabang-aman logic
     * 
     * @param int $employeeId
     * @param int $positionId
     * @param int $unitId
     * @param string $month
     * @param string $year
     * @param string $date
     * @return float Normalized score (0-100)
     */
    public function calculate(
        int $employeeId,
        int $positionId,
        int $unitId,
        string $month,
        string $year,
        ?string $date = null
    ): float {
        // OPERASIONAL cabang-aman logic only applies to SPV (position_id = 40)
        if ($positionId !== 40) {
            return 0.0;
        }

        $date = $date ?? sprintf('%04d-%02d-15', (int)$year, (int)$month);
        
        // Get OPERASIONAL component ID
        $db = \Config\Database::connect();
        $component = $db->table('kpi_components')->where('code', 'OPERASIONAL')->get()->getRow();
        if (!$component) {
            return 0.0;
        }

        // Count cabang_aman (units reaching OMSET target_value)
        $cabangAman = 0;
        
        for ($unit = 1; $unit <= 4; $unit++) {
            // Get actual omzet for this unit
            $actualOmzet = $this->omsetCalc->calculate(0, $unit, $month, $year);
            
            // Get OMSET target (target_value) for this unit
            $target = $this->targetModel
                ->where('kpi_component_id', $component->id)
                ->where('unit_id', $unit)
                ->where('context', 'default')
                ->where('effective_from <=', $date)
                ->groupStart()
                    ->where('effective_to >=', $date)
                    ->orWhere('effective_to IS NULL')
                ->groupEnd()
                ->first();
            
            if (!$target || $target->target_value === null) {
                continue;
            }
            
            // Check if unit meets target
            if ($actualOmzet >= (float)$target->target_value) {
                $cabangAman++;
            }
        }

        // Map cabang_aman count to score (legacy business rule)
        $score = $this->mapCabangAmanToScore($cabangAman);
        
        return (float)$score;
    }

    /**
     * Map cabang_aman count to OPERASIONAL score (4-tier penilaian_kinerja)
     * 
     * @param int $cabangAman
     * @return int
     */
    protected function mapCabangAmanToScore(int $cabangAman): int
    {
        // Konteks tunggal: mapping 4-tier (1→25, 2→50, 3→75, 4→100, 0→0).
        switch ($cabangAman) {
            case 1:
                return 25;
            case 2:
                return 50;
            case 3:
                return 75;
            case 4:
                return 100;
            default:
                return 0;
        }
    }

    /**
     * Get detailed breakdown for debugging
     */
    public function getBreakdown(
        int $employeeId,
        int $positionId,
        int $unitId,
        string $month,
        string $year,
        ?string $date = null
    ): array {
        if ($positionId !== 40) {
            return ['error' => 'OPERASIONAL cabang-aman only applies to SPV (position_id=40)'];
        }

        $date = $date ?? sprintf('%04d-%02d-15', (int)$year, (int)$month);
        
        $db = \Config\Database::connect();
        $component = $db->table('kpi_components')->where('code', 'OPERASIONAL')->get()->getRow();
        
        $breakdown = [];
        $cabangAman = 0;
        
        for ($unit = 1; $unit <= 4; $unit++) {
            $actualOmzet = $this->omsetCalc->calculate(0, $unit, $month, $year);
            
            $target = $this->targetModel
                ->where('kpi_component_id', $component->id)
                ->where('unit_id', $unit)
                ->where('context', 'default')
                ->where('effective_from <=', $date)
                ->groupStart()
                    ->where('effective_to >=', $date)
                    ->orWhere('effective_to IS NULL')
                ->groupEnd()
                ->first();
            
            $threshold = ($target && $target->target_value !== null) ? (float)$target->target_value : 0;
            $meets = ($actualOmzet >= $threshold);
            
            if ($meets) {
                $cabangAman++;
            }
            
            $breakdown["unit_$unit"] = [
                'omzet' => $actualOmzet,
                'threshold' => $threshold,
                'meets' => $meets,
            ];
        }
        
        $breakdown['cabang_aman'] = $cabangAman;
        $breakdown['final_score'] = $this->mapCabangAmanToScore($cabangAman);
        
        return $breakdown;
    }
}
