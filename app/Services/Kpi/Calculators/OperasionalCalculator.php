<?php

namespace App\Services\Kpi\Calculators;

use App\Services\Kpi\OmsetTokoCalculator;

/**
 * OPERASIONAL Calculator for SPV (position_id = 40)
 * 
 * Business Rule:
 * - Count units (cabang) that meet batas_keempat threshold
 * - Map count to score: 1→25, 2→50, 3→75, 4→100, 0→0
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
     * @param string $context
     * @param string $date
     * @return float Normalized score (0-100)
     */
    public function calculate(
        int $employeeId,
        int $positionId,
        int $unitId,
        string $month,
        string $year,
        string $context = 'gaji',
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

        // Count cabang_aman (units meeting batas_keempat threshold)
        $cabangAman = 0;
        
        for ($unit = 1; $unit <= 4; $unit++) {
            // Get actual omzet for this unit
            $actualOmzet = $this->omsetCalc->calculate(0, $unit, $month, $year);
            
            // Get batas_keempat threshold for this unit
            $target = $this->targetModel
                ->where('kpi_component_id', $component->id)
                ->where('unit_id', $unit)
                ->where('context', $context)
                ->where('effective_from <=', $date)
                ->groupStart()
                    ->where('effective_to >=', $date)
                    ->orWhere('effective_to IS NULL')
                ->groupEnd()
                ->first();
            
            if (!$target || !$target->batas_keempat) {
                continue;
            }
            
            // Check if unit meets threshold
            if ($actualOmzet >= (float)$target->batas_keempat) {
                $cabangAman++;
            }
        }

        // Map cabang_aman count to score (legacy business rule)
        $score = $this->mapCabangAmanToScore($cabangAman, $context);
        
        return (float)$score;
    }

    /**
     * Map cabang_aman count to OPERASIONAL score
     * 
     * @param int $cabangAman
     * @param string $context
     * @return int
     */
    protected function mapCabangAmanToScore(int $cabangAman, string $context): int
    {
        // For gaji context (3-tier mapping)
        if ($context === 'gaji') {
            switch ($cabangAman) {
                case 1:
                    return 33;
                case 2:
                    return 66;
                case 3:
                    return 100;
                default:
                    return 0;
            }
        }
        
        // For penilaian_kinerja and slip_gaji contexts (4-tier mapping)
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
        string $context = 'gaji',
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
                ->where('context', $context)
                ->where('effective_from <=', $date)
                ->groupStart()
                    ->where('effective_to >=', $date)
                    ->orWhere('effective_to IS NULL')
                ->groupEnd()
                ->first();
            
            $threshold = $target ? (float)$target->batas_keempat : 0;
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
        $breakdown['final_score'] = $this->mapCabangAmanToScore($cabangAman, $context);
        
        return $breakdown;
    }
}
