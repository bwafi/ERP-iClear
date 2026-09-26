<?php

namespace App\Controllers;

use App\Services\Finance\FinanceKpiCalculationService;
use App\Services\Finance\FinanceScopeService;
use App\Services\Finance\OmsetDailyCalculator;
use App\Services\Finance\RekonDailyCalculator;
use App\Models\ModelFinanceOmsetDaily;
use App\Models\ModelFinanceKpiRecord;
use App\Models\ModelFinanceRekonDaily;
use App\Models\ModelUnit;
use Config\Finance;

class DashboardFinance extends BaseController
{
    /**
     * Sentinel hasil parse nominal aktual yang tidak valid (salah ketik).
     * Dibedakan dari null yang berarti "belum diisi".
     */
    public const AKTUAL_INVALID = 'invalid';

    protected $kpiService;
    protected $scopeService;
    protected $omsetDaily;
    protected $omsetCalculator;
    protected $rekonCalc;
    protected $rekonModel;
    protected $modelUnit;
    protected $config;

    public function __construct()
    {
        $this->kpiService = new FinanceKpiCalculationService();
        $this->scopeService = new FinanceScopeService();
        $this->omsetDaily = new ModelFinanceOmsetDaily();
        $this->omsetCalculator = new OmsetDailyCalculator();
        $this->rekonCalc = new RekonDailyCalculator();
        $this->rekonModel = new ModelFinanceRekonDaily();
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
     * Simpan penilaian manual (Kesehatan Uang, Compliance, Improvement).
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
     * Halaman Rekonsiliasi Harian.
     */
    public function rekonsiliasi()
    {
        $info = $this->scopeService->scopeInfo();
        if (!$info['isLintas']) {
            return redirect()->back()->with('gagal', 'Anda tidak berhak mengakses Rekonsiliasi.');
        }

        return view('template', $this->rekonsiliasiData($info));
    }

    /**
     * Menyusun seluruh data view Rekonsiliasi Harian.
     *
     * Dipisah dari rekonsiliasi() agar dapat diuji langsung (smoke test)
     * tanpa harus melewati routing/session HTTP.
     */
    protected function rekonsiliasiData(array $info): array
    {
        $units = $this->scopeService->resolveAllowedUnits();
        $unitId = $this->scopeService->resolveSelectedUnitId($this->request->getGet('unit_id'));
        $unitName = $unitId ? ($this->modelUnit->find($unitId)->NAMA_UNIT ?? '') : '';

        $month = $this->request->getGet('month') ?: date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        [$year, $mon] = array_map('intval', explode('-', $month));

        $statusProses = (string) ($this->request->getGet('status') ?? '');
        $allowedProses = [
            ModelFinanceRekonDaily::STATUS_DRAFT,
            ModelFinanceRekonDaily::STATUS_SUBMITTED,
            ModelFinanceRekonDaily::STATUS_VERIFIED,
            ModelFinanceRekonDaily::STATUS_NEED_REVISION,
        ];
        if (!in_array($statusProses, $allowedProses, true)) {
            $statusProses = '';
        }

        $list = [];
        $rekonScore = null;
        $akunNames = [];
        if ($unitId) {
            $list = $this->rekonCalc->monthlyList($unitId, $mon, $year, $statusProses ?: null);
            $rekonScore = $this->rekonCalc->calculate($unitId, $mon, $year);

            $ids = [];
            foreach ($list as $item) {
                if (! $item['row']) {
                    continue;
                }
                $ids[] = $item['row']->input_by;
                $ids[] = $item['row']->submitted_by;
                $ids[] = $item['row']->verified_by;
            }
            $akunNames = $this->rekonCalc->resolveAkunNames($ids);
        }

        return [
            'title'         => 'Rekonsiliasi Harian',
            'body'          => 'dashboard/finance_rekonsiliasi',
            'units'         => $units,
            'unit_id'       => $unitId,
            'unit_name'     => $unitName ?: '—',
            'month'         => $month,
            'list'          => $list,
            'rekon_score'   => $rekonScore,
            'can_input'     => $info['isLintas'],
            'can_approve'   => $this->canApproveRekon(),
            'my_id'         => (int) $info['myId'],
            'status_proses' => $statusProses,
            'akun_names'    => $akunNames,
        ];
    }

    /**
     * Form rekonsiliasi untuk satu tanggal.
     */
    public function rekonForm()
    {
        $info = $this->scopeService->scopeInfo();
        if (!$info['isLintas']) {
            return redirect()->back()->with('gagal', 'Anda tidak berhak mengakses Rekonsiliasi.');
        }

        $data = $this->rekonFormData($info);

        return view('template', array_merge($data, [
            'title' => 'Rekonsiliasi — ' . $data['tanggal'],
            'body'  => 'dashboard/finance_rekon_form',
        ]));
    }

    /**
     * Menyusun seluruh data view form rekonsiliasi harian.
     * Dipisah agar dapat diuji langsung (smoke test).
     */
    protected function rekonFormData(array $info): array
    {
        $units = $this->scopeService->resolveAllowedUnits();
        $unitId = $this->scopeService->resolveSelectedUnitId(
            $this->request->getGet('unit_id') ?? $this->request->getPost('unit_id')
        );
        $unitName = $unitId ? ($this->modelUnit->find($unitId)->NAMA_UNIT ?? '') : '';

        $tanggal = $this->request->getGet('tanggal') ?: date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $tanggal = date('Y-m-d');
        }
        if ($tanggal > date('Y-m-d')) {
            $tanggal = date('Y-m-d');
        }

        $erp = ['cash_masuk' => 0, 'transfer_masuk' => 0, 'kas_keluar' => 0];
        $existing = null;
        if ($unitId) {
            $erp = $this->rekonCalc->erpValues($unitId, $tanggal);
            $existing = $this->rekonModel->getByUnitAndDate($unitId, $tanggal);
        }

        $statusProses = RekonDailyCalculator::statusProses($existing);
        $locked = RekonDailyCalculator::isLocked($existing);

        return [
            'units'         => $units,
            'unit_id'       => $unitId,
            'unit_name'     => $unitName ?: '—',
            'tanggal'       => $tanggal,
            'erp'           => $erp,
            'existing'      => $existing,
            'can_input'     => $info['isLintas'],
            'status_proses' => $statusProses,
            'locked'        => $locked,
            'my_id'         => (int) $info['myId'],
            'is_submitter'  => $existing && (int) $existing->submitted_by === (int) $info['myId'],
            'can_approve'   => $this->canApproveRekon($existing),
            'can_submit'    => $info['isLintas']
                && ! $locked
                && RekonDailyCalculator::siapSubmit($existing)
                && (! $existing || RekonDailyCalculator::statusProses($existing) !== ModelFinanceRekonDaily::STATUS_SUBMITTED),
        ];
    }

    /**
     * Simpan rekonsiliasi harian (DRAFT).
     *
     * Validasi server-side adalah source of truth: nilai wajib numerik bulat
     * dan tidak boleh negatif. Setelah VERIFIED, data terkunci.
     */
    public function rekonSave()
    {
        if (!$this->scopeService->canInput()) {
            return redirect()->back()->with('gagal', 'Anda tidak berhak mengisi rekonsiliasi.');
        }

        $post = $this->request->getPost();
        $unitId = (int) ($post['unit_id'] ?? 0);
        $tanggal = (string) ($post['tanggal'] ?? '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return redirect()->back()->with('gagal', 'Tanggal tidak valid.');
        }
        if ($tanggal > date('Y-m-d')) {
            return redirect()->back()->with('gagal', 'Tidak bisa rekonsiliasi tanggal masa depan.');
        }

        if (! $this->unitDiizinkan($unitId)) {
            return redirect()->back()->with('gagal', 'Unit tidak diperbolehkan.');
        }

        $existing = $this->rekonModel->getByUnitAndDate($unitId, $tanggal);
        if (RekonDailyCalculator::isLocked($existing)) {
            return redirect()->back()->with('gagal', 'Rekonsiliasi sudah VERIFIED dan terkunci.');
        }

        $erp = $this->rekonCalc->erpValues($unitId, $tanggal);

        // Nominal aktual: parse ketat (tolak non-numerik & negatif).
        // Field KOSONG disimpan sebagai NULL = "belum diisi" (bukan 0), karena
        // status hasil sekarang murni turunan dari keberadaan nilai actual.
        // Kolom checked_* tidak lagi ada; tidak ada input manual COCOK/SELISIH.
        $actualCash = $this->parseNominalRekon($post['actual_cash_masuk'] ?? null);
        $actualTransfer = $this->parseNominalRekon($post['actual_transfer_masuk'] ?? null);
        $actualKeluar = $this->parseNominalRekon($post['actual_kas_keluar'] ?? null);

        if ($actualCash === self::AKTUAL_INVALID || $actualTransfer === self::AKTUAL_INVALID || $actualKeluar === self::AKTUAL_INVALID) {
            return redirect()->back()->with('gagal', 'Nilai aktual harus angka dan tidak boleh negatif.');
        }

        // Selisih = actual - erp, SELALU dihitung server-side.
        // Nilai erp/selisih dari POST browser tidak pernah dipercaya.
        $erpCash = (int) $erp['cash_masuk'];
        $erpTransfer = (int) $erp['transfer_masuk'];
        $erpKeluar = (int) $erp['kas_keluar'];

        $this->rekonModel->upsert([
            'unit_id'                => $unitId,
            'tanggal'                => $tanggal,
            // ERP readonly: selalu dari source transaksi, bukan dari POST.
            'erp_cash_masuk'         => $erpCash,
            'actual_cash_masuk'      => $actualCash,
            'selisih_cash_masuk'     => $this->hitungSelisih($actualCash, $erpCash),
            'erp_transfer_masuk'     => $erpTransfer,
            'actual_transfer_masuk'  => $actualTransfer,
            'selisih_transfer_masuk' => $this->hitungSelisih($actualTransfer, $erpTransfer),
            'erp_kas_keluar'         => $erpKeluar,
            'actual_kas_keluar'      => $actualKeluar,
            'selisih_kas_keluar'     => $this->hitungSelisih($actualKeluar, $erpKeluar),
            'catatan'                => trim((string) ($post['catatan'] ?? '')),
            'status_proses'          => ModelFinanceRekonDaily::STATUS_DRAFT,
            'submitted_by'           => null,
            'submitted_at'           => null,
            'verified_by'            => null,
            'verified_at'            => null,
            'catatan_revisi'         => null,
            'input_by'               => (int) session('ID_AKUN'),
        ]);

        return redirect()->to(base_url('finance/rekonsiliasi?unit_id=' . $unitId . '&month=' . substr($tanggal, 0, 7)))
            ->with('sukses', 'Rekonsiliasi ' . $tanggal . ' tersimpan sebagai draft.');
    }

    /**
     * Draft/Need Revision -> SUBMITTED.
     * Syarat: ketiga kelompok sudah diperiksa. Selisih boleh ada.
     */
    public function rekonSubmit()
    {
        if (! $this->scopeService->canInput()) {
            return redirect()->back()->with('gagal', 'Anda tidak berhak submit rekonsiliasi.');
        }

        $post = $this->request->getPost();
        $unitId = (int) ($post['unit_id'] ?? 0);
        $tanggal = (string) ($post['tanggal'] ?? '');

        if (! $this->unitDiizinkan($unitId) || ! $this->tanggalValid($tanggal)) {
            return redirect()->back()->with('gagal', 'Unit atau tanggal tidak valid.');
        }

        $row = $this->rekonModel->getByUnitAndDate($unitId, $tanggal);
        if (! $row) {
            return redirect()->back()->with('gagal', 'Data rekonsiliasi belum ada.');
        }
        if (RekonDailyCalculator::isLocked($row)) {
            return redirect()->back()->with('gagal', 'Rekonsiliasi sudah VERIFIED.');
        }
        if (! RekonDailyCalculator::siapSubmit($row)) {
            return redirect()->back()->with('gagal', 'Ketiga kelompok harus diperiksa sebelum submit.');
        }

        $this->rekonModel->updateRow((int) $row->id, [
            'status_proses'  => ModelFinanceRekonDaily::STATUS_SUBMITTED,
            'submitted_by'   => (int) session('ID_AKUN'),
            'submitted_at'   => date('Y-m-d H:i:s'),
            'catatan_revisi' => null,
        ]);

        return redirect()->to(base_url('finance/rekon/form?unit_id=' . $unitId . '&tanggal=' . $tanggal))
            ->with('sukses', 'Rekonsiliasi ' . $tanggal . ' dikirim untuk diverifikasi.');
    }

    /**
     * SUBMITTED -> VERIFIED | NEED_REVISION.
     *
     * - Approver harus dalam financeApproveRoles dan unit dalam scope.
     * - Approver tidak boleh memverifikasi data yang ia kirim sendiri.
     * - NEED_REVISION wajib menyertakan catatan.
     * - VERIFIED tetap boleh walau ada selisih.
     */
    public function rekonApprove()
    {
        $post = $this->request->getPost();
        $unitId = (int) ($post['unit_id'] ?? 0);
        $tanggal = (string) ($post['tanggal'] ?? '');
        $aksi = (string) ($post['action'] ?? '');
        $catatan = trim((string) ($post['catatan_revisi'] ?? ''));
        $myId = (int) session('ID_AKUN');

        if (! $this->unitDiizinkan($unitId) || ! $this->tanggalValid($tanggal)) {
            return redirect()->back()->with('gagal', 'Unit atau tanggal tidak valid.');
        }

        $row = $this->rekonModel->getByUnitAndDate($unitId, $tanggal);
        if (! $row) {
            return redirect()->back()->with('gagal', 'Data rekonsiliasi belum ada.');
        }
        if (RekonDailyCalculator::statusProses($row) !== ModelFinanceRekonDaily::STATUS_SUBMITTED) {
            return redirect()->back()->with('gagal', 'Hanya data SUBMITTED yang bisa diproses approval.');
        }
        // Pengirim harus sudah mengisi ketiga aktual; jadi "sudah diperiksa" berarti
        // data lengkap, bukan centang manual. Menolak data SUBMITTED yang tidak
        // lengkap (mis. hasil penyuntingan langsung di DB).
        if (! RekonDailyCalculator::siapSubmit($row)) {
            return redirect()->back()->with('gagal', 'Data rekonsiliasi belum lengkap: ketiga aktual harus diisi sebelum diverifikasi.');
        }

        // Otorisasi approval: jabatan + scope + bukan pengirim sendiri.
        if (! $this->canApproveRekon($row)) {
            return redirect()->back()->with('gagal', 'Anda tidak berwenang memverifikasi data ini.');
        }

        if ($aksi === 'verify') {
            $this->rekonModel->updateRow((int) $row->id, [
                'status_proses'  => ModelFinanceRekonDaily::STATUS_VERIFIED,
                'verified_by'    => $myId,
                'verified_at'    => date('Y-m-d H:i:s'),
                'catatan_revisi' => null,
            ]);

            return redirect()->to(base_url('finance/rekon/form?unit_id=' . $unitId . '&tanggal=' . $tanggal))
                ->with('sukses', 'Rekonsiliasi ' . $tanggal . ' diverifikasi.');
        }

        if ($aksi === 'need_revision') {
            if ($catatan === '') {
                return redirect()->back()->with('gagal', 'Catatan revisi wajib diisi.');
            }

            $this->rekonModel->updateRow((int) $row->id, [
                'status_proses'  => ModelFinanceRekonDaily::STATUS_NEED_REVISION,
                'verified_by'    => null,
                'verified_at'    => null,
                'catatan_revisi' => $catatan,
            ]);

            return redirect()->to(base_url('finance/rekon/form?unit_id=' . $unitId . '&tanggal=' . $tanggal))
                ->with('sukses', 'Rekonsiliasi ' . $tanggal . ' dikembalikan untuk revisi.');
        }

        return redirect()->back()->with('gagal', 'Aksi approval tidak dikenal.');
    }

    /**
     * Apakah pengguna boleh melakukan approval pada sebuah baris rekonsiliasi?
     *
     * Syarat: jabatan termasuk financeApproveRoles, unit dalam scope, dan
     * pengguna BUKAN pengirimnya sendiri (submitted_by / input_by).
     */
    private function canApproveRekon($row = null): bool
    {
        $info = $this->scopeService->scopeInfo();

        if (! in_array($info['myRole'], $this->config->financeApproveRoles, true)) {
            return false;
        }

        if ($row === null) {
            return true;
        }

        $myId = (int) $info['myId'];
        if ($myId > 0) {
            if ((int) ($row->submitted_by ?? 0) === $myId) {
                return false;
            }
            if ((int) ($row->input_by ?? 0) === $myId) {
                return false;
            }
        }

        if (! $this->unitDiizinkan((int) $row->unit_id)) {
            return false;
        }

        return true;
    }

    /**
     * Apakah unit ini berada dalam scope pengguna yang login?
     */
    private function unitDiizinkan(int $unitId): bool
    {
        if ($unitId <= 0) {
            return false;
        }

        $allowedIds = array_map('intval', array_column(
            array_map('get_object_vars', $this->scopeService->resolveAllowedUnits()),
            'idunit'
        ));

        return in_array($unitId, $allowedIds, true);
    }

    private function tanggalValid(string $tanggal): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)
            && $tanggal <= date('Y-m-d');
    }

    /**
     * Parse nominal aktual rekonsiliasi — ketat, 3 kemungkinan hasil:
     *
     *   null                  -> BELUM DIISI (field kosong di form)
     *   self::AKTUAL_INVALID  -> input TIDAK valid (salah ketik), form ditolak
     *   int >= 0              -> nilai aktual yang sah (0 termasuk sah)
     *
     * Pemisahan null vs INVALID itu penting: "belum diisi" adalah kondisi
     * bisnis yang sah (status BELUM_DIPERIKSA), sedangkan "tidak valid" adalah
     * kesalahan input yang tidak boleh tersimpan diam-diam.
     *
     * - Menerima "10000", "10.000", "10,000", "1.000.000", " 10000 ", "Rp 1.000".
     * - TOLAK nilai negatif ("-100" / "Rp -100") -> INVALID.
     * - TOLAK teks non-numerik -> INVALID.
     * - TOLAK desimal ("1.500,25" / "1000,5") -> INVALID. Pemisah ribuan hanya
     *   dianggap ribuan bila tepat 3 digit di BEKASANG pemisah terakhir;
     *   kalau tidak, itu desimal dan ditolak (bukan dibulatkan diam-diam).
     *
     * @return int|null|string
     */
    private function parseNominalRekon($value)
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        // Buang pemisah ribuan & prefix.
        $clean = preg_replace('/\s|Rp\.?/i', '', $raw);

        // Guard desimal: digit setelah pemisah TERAKHIR harus tepat 3 (p ribuan).
        // "1.000.000" -> "000" (ok, 3 digit). "1.500,25" -> "25" (desimal, tolak).
        if (preg_match_all('/[.,]/', (string) $clean, $seps, PREG_OFFSET_CAPTURE) > 0) {
            $last = end($seps[0]);
            $after = substr((string) $clean, $last[1] + 1);
            if (strlen($after) !== 3) {
                return self::AKTUAL_INVALID;
            }
        }

        $clean = str_replace(['.', ','], '', (string) $clean);

        if ($clean === '' || ! preg_match('/^-?\d+$/', $clean)) {
            return self::AKTUAL_INVALID;
        }

        $int = (int) $clean;

        return $int < 0 ? self::AKTUAL_INVALID : $int;
    }

    /**
     * Selisih = actual - erp, dihitung server-side.
     *
     * Bernilai null bila actual belum diisi, karena selisih tidak terdefinisi
     * tanpa nilai aktual. Selisih TIDAK PERNAH diambil dari POST browser.
     *
     * @param int|null $actual nilai aktual hasil parseNominalRekon()
     */
    private function hitungSelisih($actual, int $erp): ?int
    {
        if ($actual === null || $actual === self::AKTUAL_INVALID) {
            return null;
        }

        return (int) $actual - $erp;
    }

    /**
     * Bersihkan input rupiah (strip titik/koma/pemisah) menjadi bilangan bulat.
     * Dipakai modul lama (payroll); JANGAN dipakai untuk rekon — gunakan
     * parseNominalRekon() yang menolak negatif & non-numerik.
     */
    private function parseRupiah($value): float
    {
        return (float) preg_replace('/[^0-9]/', '', (string) $value);
    }
}
