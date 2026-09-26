<?php

namespace App\Services\Finance;

use App\Models\ModelFinanceKpiRecord;
use App\Models\ModelFinanceOmsetDaily;
use App\Models\ModelFinancePayroll;
use Config\Finance;

/**
 * Orkestrator perhitungan & snapshot KPI Finance untuk satu unit+periode.
 *
 * - Auto  : akurasi (omzet ERP vs sheet), cash_flow, hutang_piutang, payroll
 * - Manual: kesehatan_uang, compliance, improvement
 * - Auto + fallback manual: rekonsiliasi
 *
 * Transisi aman rekonsiliasi: 'rekonsiliasi' tetap terdaftar di
 * Finance::$manualKpiCodes, tetapi kpiRow() mencoba calculator auto lebih
 * dahulu. Score auto dipakai bila tersedia; bila calculator error atau tidak
 * dapat dihitung (score null), skor manual dari finance_kpi_records dipakai
 * sehingga deploy calculator tidak pernah membuat KPIexisting jadi 0/null.
 */
class FinanceKpiCalculationService
{
    protected $kpiRecord;
    protected $omsetDaily;
    protected $cashFlow;
    protected $omset;
    protected $hutang;
    protected $piutang;
    protected $payroll;
    protected $rekon;
    protected $payrollModel;
    protected $config;

    public function __construct()
    {
        $this->kpiRecord = new ModelFinanceKpiRecord();
        $this->omsetDaily = new ModelFinanceOmsetDaily();
        $this->cashFlow = new CashFlowCalculator();
        $this->omset = new OmsetDailyCalculator();
        $this->hutang = new HutangTimelinessCalculator();
        $this->piutang = new PiutangTimelinessCalculator();
        $this->payroll = new PayrollTimelinessCalculator();
        $this->rekon = new RekonDailyCalculator();
        $this->payrollModel = new ModelFinancePayroll();
        $this->config = new Finance();
    }

    /**
     * Scorecard lengkap KPI Finance untuk sebuah unit pada periode.
     *
     * @return array{
     *   period: string, unit_id: int, rows: array,
     *   total_score: float, counted: int, weights: array, labels: array,
     *   akurasi_detail: array, cashflow_monthly: array, cashflow_daily: array,
     *   hutang_detail: array, piutang_detail: array, payroll_detail: array,
     *   manual_records: array, manual_options: array
     * }
     */
    public function scorecard(int $unitId, int $month, int $year): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        // data harian tandem dalam bulan berjalan (untuk akurasi)
        $akurasiDetail = $this->akurasiDetail($unitId, $startDate, $endDate);

        $rows = [];
        foreach (array_keys($this->config->kpiWeights) as $code) {
            $rows[$code] = $this->kpiRow($unitId, $month, $year, $startDate, $endDate, $code, $akurasiDetail);
        }

        $totalScore = 0.0;
        $counted = 0;
        foreach ($rows as $row) {
            if ($row['score'] !== null) {
                $totalScore += (float) $row['contribution'];
                $counted++;
            }
        }

        // snapshot ke tabel (auto) agar riwayat tetap membeku
        $this->snapshot($unitId, $month, $year, $rows);

        return [
            'period' => sprintf('%04d-%02d', $year, $month),
            'unit_id' => $unitId,
            'rows' => $rows,
            'total_score' => round(min($totalScore, 100), 2),
            'counted' => $counted,
            'weights' => $this->config->kpiWeights,
            'labels' => $this->config->kpiLabels,
            'akurasi_detail' => $akurasiDetail,
            'cashflow_monthly' => $this->cashFlow->calculate($unitId, $month, $year),
            'cashflow_daily' => $this->cashFlow->daily($unitId, $month, $year),
            'hutang_detail' => $this->hutang->calculate($unitId, $month, $year),
            'piutang_detail' => $this->piutang->calculate($unitId, $month, $year),
            'payroll_detail' => $this->payroll->calculate($unitId, $month, $year),
            'rekon_detail' => $this->rekon->calculate($unitId, $month, $year),
            'manual_records' => $this->kpiRecord->getByUnitAndPeriod($unitId, $year, $month),
            'manual_options' => $this->config->manualKpiCodes,
            'approve_roles' => $this->config->financeApproveRoles,
        ];
    }

    /**
     * Satu baris KPI lengkap (nilai, skor, kontribusi, status + snapshot).
     */
    protected function kpiRow(int $unitId, int $month, int $year, string $startDate, string $endDate, string $code, array $akurasiDetail): array
    {
        $weight = (float) $this->config->kpiWeights[$code];

        // Auto-first: coba calculator, jatuh ke manual bila tidak bisa dihitung.
        if ($code === 'rekonsiliasi') {
            return $this->rekonRow($unitId, $month, $year, $weight);
        }

        $manual = in_array($code, $this->config->manualKpiCodes, true);

        if ($manual) {
            $record = $this->kpiRecord->findOneByUnitCodePeriod($unitId, $code, $year, $month);
            if ($record) {
                return $this->buildRow($code, $weight, $record->mode, (float) $record->score, $record->notes, 'terisi');
            }

            return $this->buildRow($code, $weight, 'manual', null, null, 'belum_dinilai');
        }

        switch ($code) {
            case 'akurasi':
                $score = $this->akurasiScore($akurasiDetail);
                return $this->buildRow($code, $weight, 'auto', $score, null, $score === null ? 'data_kosong' : 'ok');

            case 'cash_flow':
                $cf = $this->cashFlow->calculate($unitId, $month, $year);
                return $this->buildRow($code, $weight, 'auto', $cf['score'], null, $cf['status']);

            case 'hutang_piutang':
                $hutang = $this->hutang->calculate($unitId, $month, $year);
                $piutang = $this->piutang->calculate($unitId, $month, $year);
                $h = $hutang['score'];
                $p = $piutang['score'];

                if ($h !== null && $p !== null) {
                    $scoreRow = round(($h + $p) / 2, 2);
                    $statusRow = 'ok';
                    $notesRow = 'Skor gabungan interim (pembobotan Hutang:Piutang = pending).';
                } elseif ($h !== null) {
                    $scoreRow = $h;
                    $statusRow = $piutang['status'] === 'gap_no_payment_date' ? 'gap_piutang' : 'ok';
                    $notesRow = 'Skor sementara dari Hutang. Piutang belum dapat dinilai karena pembayaran_piutang tidak memiliki tanggal bayar (GAP).';
                } else {
                    $scoreRow = null;
                    $statusRow = 'data_kosong';
                    $notesRow = 'Tidak ada pembelian jatuh tempo pada periode ini.';
                }

                return $this->buildRow($code, $weight, 'auto', $scoreRow, $notesRow, $statusRow);

            case 'payroll':
                $payroll = $this->payroll->calculate($unitId, $month, $year);
                return $this->buildRow($code, $weight, 'auto', $payroll['score'], null, $payroll['status']);

            case 'rekonsiliasi':
                // ditangani sebelum switch oleh rekonRow()
                return $this->rekonRow($unitId, $month, $year, $weight);

            default:
                throw new \RuntimeException("KPI code tidak dikenal: {$code}");
        }
    }

    /**
     * Baris KPI Rekonsiliasi dengan transisi aman auto -> manual.
     *
     * 1. Coba calculator auto. Bila score !== null (berhasil dihitung) pakai
     *    score auto, mode 'auto' supaya ikut di-snapshot.
     * 2. Bila calculator error / data kosong / kolom belum ada, baca skor
     *    manual dari finance_kpi_records (mode 'manual' diutamakan).
     * 3. Bila tidak ada manual sama sekali -> belum_dinilai.
     */
    protected function rekonRow(int $unitId, int $month, int $year, float $weight): array
    {
        $autoScore = null;
        $autoStatus = 'error';

        try {
            $rekon = $this->rekon->calculate($unitId, $month, $year);
            $autoScore = $rekon['score'];
            $autoStatus = (string) $rekon['status'];
        } catch (\Throwable $e) {
            $autoScore = null;
            $autoStatus = 'error';
        }

        if ($autoScore !== null) {
            return $this->buildRow('rekonsiliasi', $weight, 'auto', (float) $autoScore, null, $autoStatus);
        }

        $record = $this->kpiRecord->findOneByUnitCodePeriod($unitId, 'rekonsiliasi', $year, $month);
        if ($record && $record->score !== null) {
            $notes = 'Fallback manual: calculator auto tidak dapat menghitung '
                . '(status: ' . $autoStatus . ').';

            return $this->buildRow(
                'rekonsiliasi',
                $weight,
                (string) $record->mode,
                (float) $record->score,
                $notes,
                'fallback_manual'
            );
        }

        return $this->buildRow(
            'rekonsiliasi',
            $weight,
            'manual',
            null,
            null,
            'belum_dinilai'
        );
    }

    private function buildRow(string $code, float $weight, string $mode, ?float $score, ?string $notes, string $status): array
    {
        $contribution = $score === null ? null : round($score * $weight / 100, 2);

        return [
            'code' => $code,
            'mode' => $mode,
            'weight' => $weight,
            'score' => $score,
            'notes' => $notes,
            'contribution' => $contribution,
            'status' => $status,
        ];
    }

    /**
     * Detail harian akurasi: pasangan omzet ERP vs sheet + selisih.
     */
    protected function akurasiDetail(int $unitId, string $startDate, string $endDate): array
    {
        $records = $this->omsetDaily->getByUnitAndRange($unitId, $startDate, $endDate);
        $erpByDate = $this->omset->calculateDailyByRange($unitId, $startDate, $endDate);

        $detail = [];
        foreach ($records as $record) {
            $erp = (float) ($record->omzet_erp ?? 0);
            $detail[] = [
                'tanggal' => $record->tanggal,
                'omzet_erp' => $erp,
                'omzet_sheet' => (float) ($record->omzet_sheet ?? 0),
                'selisih' => (float) ($record->selisih ?? 0),
                'is_match' => (bool) $record->is_match,
            ];
            unset($erpByDate[$record->tanggal]);
        }

        foreach ($erpByDate as $tanggal => $erp) {
            $detail[] = [
                'tanggal' => $tanggal,
                'omzet_erp' => $erp,
                'omzet_sheet' => null,
                'selisih' => null,
                'is_match' => null,
            ];
        }

        usort($detail, fn ($a, $b) => strcmp($a['tanggal'], $b['tanggal']));

        return $detail;
    }

    /**
     * Nilai akurasi = jumlah hari cocok / jumlah hari dengan input sheet.
     * Hari tanpa input sheet ikut dibagikan (dianggap belum cocok) — hanya
     * dihitung dari data sheet yang sudah ada.
     */
    protected function akurasiScore(array $detail): ?float
    {
        $cocok = 0;
        $total = 0;
        foreach ($detail as $row) {
            if ($row['omzet_sheet'] === null) {
                continue;
            }
            $total++;
            if ($row['is_match']) {
                $cocok++;
            }
        }

        if ($total === 0) {
            return null;
        }

        return round(($cocok / $total) * 100, 2);
    }

    /**
     * Simpan/update penilaian manual (KPI yang dikelola Finance).
     */
    public function saveManual(int $unitId, string $kpiCode, int $year, int $month, float $score, float $weight, float $contribution, string $notes = ''): bool
    {
        if (!in_array($kpiCode, $this->config->manualKpiCodes, true)) {
            throw new \InvalidArgumentException("KPI manual tidak dikenal: {$kpiCode}");
        }

        return $this->kpiRecord->upsert([
            'unit_id' => $unitId,
            'period_year' => $year,
            'period_month' => $month,
            'kpi_code' => $kpiCode,
            'mode' => 'manual',
            'score' => $score,
            'contribution' => $contribution,
            'weight' => $weight,
            'notes' => $notes,
            'evaluator_id' => (int) session('ID_AKUN'),
            'evaluated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Simpan satu catatan pembayaran gaji untuk sebuah unit.
     * paid_date diisi → status 'dibayar', kosong → 'rencana'.
     */
    public function savePayroll(int $unitId, string $dueDate, float $total, ?string $paidDate = null, string $notes = '', ?int $pegawaiId = null): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            throw new \InvalidArgumentException('due_date tidak valid.');
        }

        $status = 'rencana';
        if ($paidDate !== null) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidDate)) {
                throw new \InvalidArgumentException('paid_date tidak valid.');
            }
            $status = 'dibayar';
        }

        return $this->payrollModel->insert([
            'unit_id' => $unitId,
            'pegawai_id' => $pegawaiId,
            'due_date' => $dueDate,
            'paid_date' => $paidDate,
            'status' => $status,
            'total' => (int) round($total),
            'notes' => $notes,
            'created_by' => (int) session('ID_AKUN'),
        ]);
    }

    /**
     * Rekam nilai auto (akurasi & cash_flow) ke finance_kpi_records
     * agar histori periode membeku. Manual & placeholder dilewati.
     */
    protected function snapshot(int $unitId, int $month, int $year, array $rows): void
    {
        foreach ($rows as $code => $row) {
            if ($row['mode'] !== 'auto' || $row['score'] === null) {
                continue;
            }

            $this->kpiRecord->upsert([
                'unit_id' => $unitId,
                'period_year' => $year,
                'period_month' => $month,
                'kpi_code' => $code,
                'mode' => 'auto',
                'score' => $row['score'],
                'contribution' => $row['contribution'],
                'weight' => $row['weight'],
                'detail_json' => json_encode($row),
                'evaluator_id' => session()->get('ID_AKUN'),
                'evaluated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}