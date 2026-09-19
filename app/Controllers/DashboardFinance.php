<?php

namespace App\Controllers;

use App\Services\Finance\FinanceKpiCalculationService;
use App\Services\Finance\FinanceScopeService;
use App\Services\Finance\OmsetDailyCalculator;
use App\Models\ModelFinanceOmsetDaily;
use App\Models\ModelFinanceKpiRecord;
use App\Models\ModelUnit;
use Config\Finance;

class DashboardFinance extends BaseController
{
    protected $kpiService;
    protected $scopeService;
    protected $omsetDaily;
    protected $omsetCalculator;
    protected $modelUnit;
    protected $config;

    public function __construct()
    {
        $this->kpiService = new FinanceKpiCalculationService();
        $this->scopeService = new FinanceScopeService();
        $this->omsetDaily = new ModelFinanceOmsetDaily();
        $this->omsetCalculator = new OmsetDailyCalculator();
        $this->modelUnit = new ModelUnit();
        $this->config = new Finance();
    }

    public function index()
    {
        $info = $this->scopeService->scopeInfo();

        // Halaman hanya boleh diakses Admin Center/Root/Direktur/Manager.
        if (!$info['isLintas']) {
            return redirect()->back()->with('gagal', 'Anda tidak berhak mengakses Dashboard Finance.');
        }

        $units = $this->scopeService->resolveAllowedUnits();

        $month = $this->request->getGet('month') ?: date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        [$year, $mon] = array_map('intval', explode('-', $month));

        $unitId = $this->scopeService->resolveSelectedUnitId($this->request->getGet('unit_id'));
        $unitName = $unitId ? ($this->modelUnit->find($unitId)->NAMA_UNIT ?? '') : '';

        // Pengguna tanpa unit diizinkan => tampilkan kosong.
        if (!$unitId) {
            return view('template', [
                'title' => 'Dashboard Finance',
                'body' => 'dashboard/dashboard_finance',
                'units' => $units,
                'unit_id' => null,
                'unit_name' => '—',
                'month' => $month,
                'can_input' => $info['isLintas'],
                'rows' => [],
                'total_score' => 0,
                'counted' => 0,
                'weights' => $this->config->kpiWeights,
                'labels' => $this->config->kpiLabels,
            ]);
        }

        $scorecard = $this->kpiService->scorecard($unitId, $mon, $year);

        $data = [
            'title' => 'Dashboard Finance',
            'body' => 'dashboard/dashboard_finance',
            'month' => $month,
            'period' => $scorecard['period'],
            'unit_id' => $unitId,
            'unit_name' => $unitName,
            'units' => $units,
            'rows' => $scorecard['rows'],
            'total_score' => $scorecard['total_score'],
            'counted' => $scorecard['counted'],
            'weights' => $scorecard['weights'],
            'labels' => $scorecard['labels'],
            'akurasi_detail' => $scorecard['akurasi_detail'],
            'cashflow_monthly' => $scorecard['cashflow_monthly'],
            'cashflow_daily' => $scorecard['cashflow_daily'],
            'hutang_detail' => $scorecard['hutang_detail'],
            'piutang_detail' => $scorecard['piutang_detail'],
            'payroll_detail' => $scorecard['payroll_detail'],
            'manual_records' => $scorecard['manual_records'],
            'manual_options' => $scorecard['manual_options'],
            'can_input' => $info['isLintas'],
        ];

        return view('template', $data);
    }

    /**
     * Simpan omzet harian dari Sheet (rupiah) untuk unit+periode.
     */
    public function entryOmzetSheet()
    {
        if (!$this->scopeService->canInput()) {
            return redirect()->back()->with('gagal', 'Anda tidak berhak mengisi omzet.');
        }

        $post = $this->request->getPost();
        $tanggal = (string) ($post['tanggal'] ?? '');
        $unitId = (int) ($post['unit_id'] ?? 0);
        $sheet = $this->parseRupiah($post['omzet_sheet'] ?? '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return redirect()->back()->with('gagal', 'Tanggal tidak valid.');
        }

        $allowed = $this->scopeService->resolveSelectedUnitId((string) $unitId);
        $allowedIds = array_map('intval', array_column(
            array_map('get_object_vars', $this->scopeService->resolveAllowedUnits()),
            'idunit'
        ));
        if (!in_array($unitId, $allowedIds, true)) {
            return redirect()->back()->with('gagal', 'Unit tidak diperbolehkan.');
        }
        unset($allowed);

        $erp = $this->omsetCalculator->calculateDaily($unitId, $tanggal);
        $selisih = $sheet - $erp;
        $tolerance = (int) $this->config->omzetTolerance;
        $isMatch = abs($selisih) <= $tolerance ? 1 : 0;

        $this->omsetDaily->upsert([
            'unit_id' => $unitId,
            'tanggal' => $tanggal,
            'omzet_erp' => (int) round($erp),
            'omzet_sheet' => (int) round($sheet),
            'selisih' => (int) round($selisih),
            'is_match' => $isMatch,
            'input_by' => (int) session('ID_AKUN'),
        ]);

        return redirect()->back()->with('sukses', 'Omzet Sheet ' . $tanggal . ' disimpan (' . ($isMatch ? 'sesuai' : 'selisih Rp ' . number_format($selisih, 0, ',', '.') . ')') . ').');
    }

    /**
     * Simpan penilaian manual (Kesehatan Uang, Rekonsiliasi, Compliance, Improvement).
     */
    public function entryManual()
    {
        if (!$this->scopeService->canInput()) {
            return redirect()->back()->with('gagal', 'Anda tidak berhak mengisi KPI.');
        }

        $post = $this->request->getPost();
        $unitId = (int) ($post['unit_id'] ?? 0);
        $kpiCode = (string) ($post['kpi_code'] ?? '');
        $score = (float) ($post['score'] ?? -1);
        $notes = (string) ($post['notes'] ?? '');

        if (!in_array($kpiCode, $this->config->manualKpiCodes, true)) {
            return redirect()->back()->with('gagal', 'KPI manual tidak dikenal.');
        }
        if ($score < 0 || $score > 100) {
            return redirect()->back()->with('gagal', 'Skor harus 0-100.');
        }

        $allowedIds = array_map('intval', array_column(
            array_map('get_object_vars', $this->scopeService->resolveAllowedUnits()),
            'idunit'
        ));
        if (!in_array($unitId, $allowedIds, true)) {
            return redirect()->back()->with('gagal', 'Unit tidak diperbolehkan.');
        }

        $month = (string) ($post['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        [$year, $mon] = array_map('intval', explode('-', $month));

        $weight = (float) $this->config->kpiWeights[$kpiCode];
        $contribution = round($score * $weight / 100, 2);

        $this->kpiService->saveManual($unitId, $kpiCode, $year, $mon, $score, $weight, $contribution, $notes);

        return redirect()->back()->with('sukses', 'KPI ' . $this->config->kpiLabels[$kpiCode] . ' tersimpan.');
    }

    /**
     * Simpan catatan pembayaran gaji untuk unit terpilih.
     * Periode evaluasi ditentukan dari due_date (bulan jatuh tempo).
     */
    public function entryPayroll()
    {
        if (!$this->scopeService->canInput()) {
            return redirect()->back()->with('gagal', 'Anda tidak berhak mengisi payroll.');
        }

        $post = $this->request->getPost();
        $unitId = (int) ($post['unit_id'] ?? 0);
        $dueDate = (string) ($post['due_date'] ?? '');
        $paidDate = (string) ($post['paid_date'] ?? '');
        $total = $this->parseRupiah($post['total'] ?? '');
        $notes = (string) ($post['notes'] ?? '');
        $pegawaiId = (int) ($post['pegawai_id'] ?? 0) ?: null;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            return redirect()->back()->with('gagal', 'Tanggal jatuh tempo tidak valid.');
        }
        if ($paidDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidDate)) {
            return redirect()->back()->with('gagal', 'Tanggal bayar tidak valid.');
        }

        $allowedIds = array_map('intval', array_column(
            array_map('get_object_vars', $this->scopeService->resolveAllowedUnits()),
            'idunit'
        ));
        if (!in_array($unitId, $allowedIds, true)) {
            return redirect()->back()->with('gagal', 'Unit tidak diperbolehkan.');
        }

        $this->kpiService->savePayroll(
            $unitId,
            $dueDate,
            $total,
            $paidDate !== '' ? $paidDate : null,
            $notes,
            $pegawaiId
        );

        return redirect()->back()->with('sukses', 'Catatan payroll tersimpan (jatuh tempo ' . $dueDate . ').');
    }

    /**
     * Bersihkan input rupiah (strip titik/koma/pemisah) menjadi bilangan bulat.
     */
    private function parseRupiah($value): float
    {
        return (float) preg_replace('/[^0-9]/', '', (string) $value);
    }
}