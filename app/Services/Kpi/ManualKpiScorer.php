<?php

namespace App\Services\Kpi;

/**
 * ManualKpiScorer — achievement untuk manual KPI (non-attendance).
 *
 * Actual value dibaca dari source data yang sama dengan sistem lama:
 *   - penilaian        (SUM/AVG skor per aspek)
 *   - tutup_kasir      (COUNT status)
 *   - stok_opname_draft (COUNT DISTINCT tanggal)
 *
 * Target dibaca dari kpi_targets (source of truth NEW flow).
 *
 * CATATAN GAP (dokumentasi, bukan silent fallback):
 *   - 'atas_customer' TIDAK tersedia di kpi_targets → fallback target_value
 *     ctx default untuk CUSTOMER_COUNT pada cabang-aman scheme.
 *   - denominator 30 (tutup kasir) dan 4 (opname/bug) adalah konstanta
 *     formula legacy yang belum dimigrasikan ke database.
 */
class ManualKpiScorer
{
    protected $metrics;
    protected $targetModel;

    public function __construct()
    {
        $this->metrics = new Calculators\MetricCalculator();
        $this->targetModel = new \App\Models\ModelKpiTarget();
    }

    /**
     * @param string $code          component code (kpi_components.code)
     * @param array  $ctx keys: employee_id, unit, month, year, context, date, omset_branch_nilai
     * @return float achievement 0..100 (atau raw untuk uncapped metrics)
     */
    public function achievement(string $code, array $ctx): float
    {
        $month  = (int)$ctx['month'];
        $year   = (int)$ctx['year'];
        $emp    = (int)$ctx['employee_id'];
        $unit   = (int)$ctx['unit'];
        $context = $ctx['context'];
        $date   = $ctx['date'];

        switch ($code) {
            case 'CLOSING_RATE':
                return $this->cappedRatio(
                    $this->metrics->sumAspekScore($emp, 'closing', $month, $year),
                    $this->targetValue(4, $unit, $context, $date)
                );
            case 'UPSELLING':
                return $this->cappedRatio(
                    $this->metrics->sumAspekScore($emp, 'upselling', $month, $year),
                    $this->targetValue(5, $unit, $context, $date)
                );
            case 'FOLLOWUP':
                $aspek = ($context === 'penilaian_kinerja' || $context === 'slip_gaji') ? 'follow up' : 'followup';
                return $this->cappedRatio(
                    $this->metrics->sumAspekScore($emp, $aspek, $month, $year),
                    $this->targetValue(6, $unit, $context, $date)
                );
            case 'ROAS':
                // legacy: gaji = SUM*100, non-gaji = SUM*20 (uncapped)
                $sum = $this->metrics->sumAspekScore($emp, 'roas', $month, $year);
                return $sum * ($context === 'gaji' ? 100 : 20);
            case 'BUDGETING':
                $sum = $this->metrics->sumAspekScore($emp, 'budgeting', $month, $year);
                return $sum * ($context === 'gaji' ? 100 : 20);
            case 'TUTUP_KASIR':
                $cnt = $this->metrics->countTutupKasir($unit, $month, $year);
                if ($context === 'gaji') {
                    return $cnt / 30 * 20;
                }
                return min($cnt / 30 * 100, 100);
            case 'STOK_OPNAME':
                return $this->metrics->countStokOpname($unit, $month, $year) / 4 * 100;
            case 'FEED_MINGGUAN':
                return min($this->feedTotal($emp, $month, $year), 100);
            case 'FEED_PL':
                return min($this->feedTotal($emp, $month, $year), 100);
            case 'VIDEO':
                return $this->metrics->sumAspekScore($emp, 'video', $month, $year);
            case 'STORY':
                return $this->metrics->sumAspekScore($emp, 'story', $month, $year);
            case 'TESTIMONI':
                return $this->metrics->sumAspekScore($emp, 'testimoni', $month, $year);
            case 'BUG_MINOR':
                return $this->metrics->sumAspekScore($emp, 'bug minor', $month, $year) / 4 * 20;
            case 'BUG_OPERASIONAL':
                return $this->metrics->sumAspekScore($emp, 'operasional', $month, $year) / 4 * 20;
            case 'ECOMMERCE':
                return $this->metrics->sumAspekScore($emp, 'ecommerce', $month, $year) / 4 * 20;
            case 'FITUR':
                return $this->metrics->sumAspekScore($emp, 'operasional', $month, $year) / 4 * 20;
            case 'OPERASIONAL':
                // nilai operasional = hasil cabang-aman scheme omset (lihat KpiCalculationService)
                return isset($ctx['omset_branch_nilai']) ? (float)$ctx['omset_branch_nilai'] : 0.0;
            case 'DIVISI':
                return $this->metrics->avgScoreDivisional($month, $year) * 20;
            case 'KUALITAS_PELAYANAN':
                return $this->manualEvaluationScore($emp, $ctx, 'KUALITAS_PELAYANAN');
            case 'KONTROL_ASET':
                return $this->manualEvaluationScore($emp, $ctx, 'KONTROL_ASET');
            default:
                return 0.0;
        }
    }

    /**
     * Baca skor manual (0-100) dari kpi_evaluations utk periode yg sama.
     * Nilai yang disimpan evaluator (normalized_score) = persentase KPI.
     * Jika lebih dari satu evaluator (kasus rata-rata), gunakan AVG.
     */
    protected function manualEvaluationScore(int $emp, array $ctx, string $code): float
    {
        $componentModel = new \App\Models\ModelKpiComponent();
        $component = $componentModel->where('code', $code)->first();
        if (!$component) {
            return 0.0;
        }

        $evaluationModel = new \App\Models\ModelKpiEvaluation();
        $row = $evaluationModel
            ->select('AVG(normalized_score) as avg_norm')
            ->where('employee_id', $emp)
            ->where('kpi_component_id', (int)$component->id)
            ->where('period_year', (int)$ctx['year'])
            ->where('period_month', (int)$ctx['month'])
            ->first();

        if (!$row || $row->avg_norm === null) {
            return 0.0;
        }

        return (float) $row->avg_norm;
    }

    protected function feedTotal(int $emp, int $month, int $year): float
    {
        $mingguan = $this->metrics->sumAspekScore($emp, 'feed mingguan', $month, $year);
        return $mingguan ?: $this->metrics->sumAspekScore($emp, 'feed pl', $month, $year);
    }

    protected function cappedRatio(float $actual, float $target): float
    {
        if ($target <= 0) {
            return 0.0;
        }
        return min($actual / $target * 100, 100);
    }

    protected function targetValue(int $componentId, int $unit, string $context, string $date): float
    {
        $target = $this->targetModel->getTargetByKpiAndUnit($componentId, $unit, $context, $date);
        if (!$target) {
            $target = $this->targetModel->getTargetByKpiAndUnit($componentId, $unit, 'default', $date);
        }
        return $target ? (float)$target->target_value : 0.0;
    }
}
