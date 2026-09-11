<?php

namespace App\Controllers;

use App\Models\ModelMarketingLead;
use App\Models\ModelMarketingAdsCost;
use App\Models\ModelMarketingSource;
use App\Models\ModelPelanggan;
use App\Models\ModelChannel;
use App\Models\ModelUnit;
use App\Models\ModelMarketingPlatform;
use App\Services\Marketing\MarketingKpiService;
use App\Services\Marketing\MarketingRekapService;

/**
 * KPI Digital Marketing / Kepala Divisi (jabatan 43).
 *
 * Fitur operasional: input Lead Marketing, konversi Won → Customer,
 * biaya iklan per periode, dan dashboard KPI (Lead/Customer/Conversion/CPL/
 * Omzet Marketing/ROAS/Pertumbuhan Channel) yang seluruhnya dihitung otomatis
 * dari data operasional.
 */
class Marketing extends BaseController
{
    protected $LeaderModel;
    protected $AdsModel;
    protected $SourceModel;
    protected $CustomerModel;
    protected $ChannelModel;
    protected $Service;

    public function __construct()
    {
        $this->LeaderModel   = new ModelMarketingLead();
        $this->AdsModel      = new ModelMarketingAdsCost();
        $this->SourceModel   = new ModelMarketingSource();
        $this->CustomerModel = new ModelPelanggan();
        $this->ChannelModel  = new ModelChannel();
        $this->Service       = new MarketingKpiService();
    }

    private function currentRole(): int
    {
        return (int)session('ID_JABATAN');
    }

    private function currentAkun(): int
    {
        return (int)session('ID_AKUN');
    }

    private function canView(): bool
    {
        return in_array($this->currentRole(), [0, 1, 2, 34, 43, 44], true);
    }

    private function canWrite(): bool
    {
        return in_array($this->currentRole(), [0, 1, 2, 43, 44], true);
    }

    private function readOrRedirect()
    {
        if (!$this->canView()) {
            return redirect()->to(base_url())->with('error', 'Anda tidak berhak mengakses fitur Marketing.');
        }
        return null;
    }

    private function writeOrRedirect()
    {
        if (!$this->canWrite()) {
            return redirect()->to(base_url('marketing'))->with('error', 'Anda tidak berhak melakukan operasi ini.');
        }
        return null;
    }

    private function validPeriod(int $bulan, int $tahun): bool
    {
        return $bulan >= 1 && $bulan <= 12 && $tahun >= 2000 && $tahun <= 2100;
    }

    // ── Dashboard KPI ─────────────────────────────────────────────

    public function index()
    {
        if ($r = $this->readOrRedirect()) {
            return $r;
        }

        $bulan = (int)($this->request->getGet('bulan') ?? date('n'));
        $tahun = (int)($this->request->getGet('tahun') ?? date('Y'));
        if (!$this->validPeriod($bulan, $tahun)) {
            $bulan = (int)date('n');
            $tahun = (int)date('Y');
        }

        $summary = $this->Service->monthlySummary($bulan, $tahun);

        return view('template', [
            'body'    => 'marketing/dashboard',
            'akun'    => (new \App\Models\ModelAuth())->getById(session('ID_AKUN')),
            'bulan'   => $bulan,
            'tahun'   => $tahun,
            'summary' => $summary,
            'rekap'   => (new MarketingRekapService())->monthlySummary($bulan, $tahun),
            'rekapByUnit' => (new MarketingRekapService())->monthlySummaryByUnit($bulan, $tahun),
            'leadsByStatus' => $this->Service->leadsByStatus($bulan, $tahun),
            'trend'         => $this->Service->trendSeries($bulan, $tahun, 6),
            'canWrite' => $this->canWrite(),
        ]);
    }

    // ── Rekap Marketing Harian (source of truth KPI) ──────────────

    /** Cabang aktif (HO / kantor pusat dikecualikan). */
    private function units()
    {
        return (new ModelUnit())
            ->where('jenis !=', 'Kantor')
            ->orderBy('NAMA_UNIT', 'ASC')
            ->findAll();
    }

    public function rekap()
    {
        if ($r = $this->readOrRedirect()) {
            return $r;
        }

        $tanggal = trim((string)$this->request->getGet('tanggal') ?: date('Y-m-d'));
        $unitId  = (int)$this->request->getGet('unit_id');

        $bulanR = (int)($this->request->getGet('bulan') ?? (int)date('n'));
        $tahunR = (int)($this->request->getGet('tahun') ?? (int)date('Y'));
        if (!$this->validPeriod($bulanR, $tahunR)) {
            $bulanR = (int)date('n');
            $tahunR = (int)date('Y');
        }

        $data = null;
        if ($unitId > 0) {
            try {
                $data = (new MarketingRekapService())->getByDate($unitId, $tanggal);
            } catch (\InvalidArgumentException $e) {
                $tanggal = date('Y-m-d');
            }
        }
        if (!is_array($data)) {
            $data = ['header' => null, 'details' => []];
        }

        return view('template', [
            'body'    => 'marketing/rekap',
            'akun'    => (new \App\Models\ModelAuth())->getById(session('ID_AKUN')),
            'tanggal' => $tanggal,
            'unitId'  => $unitId,
            'units'   => $this->units(),
            'rekap'   => $data,
            'platforms' => (new ModelMarketingPlatform())->active(),
            'bulanR'  => $bulanR,
            'tahunR'  => $tahunR,
            'rekaps'  => (new MarketingRekapService())->listByMonth($bulanR, $tahunR),
            'canWrite' => $this->canWrite(),
        ]);
    }

    public function rekap_simpan()
    {
        if ($r = $this->writeOrRedirect()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('marketing/rekap'));
        }

        $unitId   = (int)$this->request->getPost('unit_id');
        $tanggal  = trim((string)$this->request->getPost('tanggal'));
        $leadIklanDash = (int)$this->request->getPost('lead_total_iklan_dashboard');

        $platforms = (array)$this->request->getPost('platform');
        $nonIklans = (array)$this->request->getPost('non_iklan');
        $iklans    = (array)$this->request->getPost('iklan');
        $prospeks  = (array)$this->request->getPost('prospek');
        $datangs   = (array)$this->request->getPost('datang');

        $n = max(count($platforms), count($nonIklans), count($iklans), count($prospeks), count($datangs));
        $details = [];
        for ($i = 0; $i < $n; $i++) {
            $details[] = [
                'platform'  => (string)($platforms[$i] ?? ''),
                'non_iklan' => (int)($nonIklans[$i] ?? 0),
                'iklan'     => (int)($iklans[$i] ?? 0),
                'prospek'   => (int)($prospeks[$i] ?? 0),
                'datang'    => (int)($datangs[$i] ?? 0),
            ];
        }

        try {
            (new MarketingRekapService())->save(
                $unitId,
                $tanggal,
                $details,
                $leadIklanDash,
                $this->currentAkun()
            );
            return redirect()->back()->with('success', 'Rekap marketing harian tersimpan.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[Rekap] Gagal simpan: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menyimpan rekap. Silakan coba lagi.');
        }
    }

    // ── Lead Marketing ────────────────────────────────────────────

    public function leads()
    {
        if ($r = $this->readOrRedirect()) {
            return $r;
        }

        $bulan = (int)($this->request->getGet('bulan') ?? date('n'));
        $tahun = (int)($this->request->getGet('tahun') ?? date('Y'));
        $status = strtoupper(trim((string)$this->request->getGet('status') ?: ''));
        if (!$this->validPeriod($bulan, $tahun)) {
            $bulan = (int)date('n');
            $tahun = (int)date('Y');
        }
        if (!in_array($status, ['', 'NEW', 'FOLLOW_UP', 'WON', 'LOST'], true)) {
            $status = '';
        }

        // Pagination daftar lead per bulan.
        $perPage = 25;
        $page    = max(1, (int)$this->request->getGet('page'));
        $statusFilter = $status !== '' ? $status : null;
        $total   = $this->LeaderModel->countByPeriod($bulan, $tahun, $statusFilter);
        $totalPages = (int)ceil($total / $perPage);
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
        }
        $rows = $this->LeaderModel->findByPeriod($bulan, $tahun, $statusFilter, $perPage, ($page - 1) * $perPage);
        // Isi nama source & customer terkait.
        $sourceMap  = [];
        $customerMap = [];
        foreach ($this->SourceModel->active() as $s) {
            $sourceMap[(int)$s->id] = $s;
        }
        $customerIds = array_unique(array_values(array_filter(array_map(fn($l) => (int)$l->customer_id, $rows), fn($v) => $v > 0)));
        if (!empty($customerIds)) {
            foreach ($this->CustomerModel->whereIn('id_pelanggan', $customerIds)->findAll() as $c) {
                $customerMap[(int)$c->id_pelanggan] = $c;
            }
        }

        $customers = $this->CustomerModel->orderBy('id_pelanggan', 'DESC')->findAll(300);
        $csPeoples = (new \App\Models\ModelAuth())->getCsKadiv();

        return view('template', [
            'body'      => 'marketing/leads',
            'akun'      => (new \App\Models\ModelAuth())->getById(session('ID_AKUN')),
            'bulan'     => $bulan,
            'tahun'     => $tahun,
            'status'    => $status,
            'rows'      => $rows,
            'currentPage' => $page,
            'perPage'     => $perPage,
            'total'       => $total,
            'totalPages'  => $totalPages,
            'sources'   => $this->SourceModel->active(),
            'sourceMap' => $sourceMap,
            'customerMap' => $customerMap,
            'customers' => $customers,
            'csPeoples' => $csPeoples,
            'leadsByStatus' => $this->Service->leadsByStatus($bulan, $tahun),
            'canWrite'  => $this->canWrite(),
        ]);
    }

    public function leads_simpan()
    {
        if ($r = $this->writeOrRedirect()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('marketing/leads'));
        }

        $id       = (int)($this->request->getPost('id') ?? 0);
        $tanggal  = trim((string)$this->request->getPost('tanggal'));
        $nama     = trim((string)$this->request->getPost('nama'));
        $noHp     = trim((string)$this->request->getPost('no_hp'));
        $sourceId = (int)$this->request->getPost('source_id');
        $adsOrg   = strtoupper(trim((string)$this->request->getPost('ads_organic') ?: 'ORGANIC'));
        $cs       = trim((string)$this->request->getPost('cs'));
        $status   = strtoupper(trim((string)$this->request->getPost('status') ?: 'NEW'));

        if ($tanggal === '' || $nama === '') {
            return redirect()->back()->with('error', 'Tanggal dan nama lead wajib diisi.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return redirect()->back()->with('error', 'Format tanggal tidak valid.');
        }
        if (!in_array($adsOrg, ['ADS', 'ORGANIC'], true)) {
            $adsOrg = 'ORGANIC';
        }
        if (!in_array($status, ['NEW', 'FOLLOW_UP', 'WON', 'LOST'], true)) {
            $status = 'NEW';
        }
        if ($sourceId > 0 && !$this->SourceModel->find($sourceId)) {
            $sourceId = null;
        }

        $data = [
            'tanggal'     => $tanggal,
            'nama'        => $nama,
            'no_hp'       => $noHp !== '' ? $noHp : null,
            'source_id'   => $sourceId > 0 ? $sourceId : null,
            'ads_organic' => $adsOrg,
            'cs'          => $cs !== '' ? $cs : null,
            'status'      => $status,
            'created_by'  => $this->currentAkun(),
        ];

        if ($id > 0 && $this->LeaderModel->find($id)) {
            $this->LeaderModel->update($id, $data);
        } else {
            $this->LeaderModel->insert($data);
        }

        return redirect()->back()->with('success', 'Lead marketing tersimpan.');
    }

    public function leads_status()
    {
        if ($r = $this->writeOrRedirect()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('marketing/leads'));
        }

        $id       = (int)$this->request->getPost('id');
        $status   = strtoupper(trim((string)$this->request->getPost('status')));
        $lead     = $this->LeaderModel->find($id);
        if (!$lead) {
            return redirect()->back()->with('error', 'Lead tidak ditemukan.');
        }

        if ($status === 'WON') {
            $customerId = (int)$this->request->getPost('customer_id');
            $customer   = $customerId > 0 ? $this->CustomerModel->find($customerId) : null;
            if (!$customer) {
                return redirect()->back()->with('error', 'Pilih customer hasil conversion untuk menjadikan WON.');
            }
            $tanggalWon = trim((string)$this->request->getPost('tanggal_won') ?: '');
            if ($tanggalWon === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalWon)) {
                $tanggalWon = date('Y-m-d');
            }
            $this->LeaderModel->update($id, [
                'status'      => 'WON',
                'customer_id' => $customerId,
                'tanggal_won' => $tanggalWon,
            ]);
            return redirect()->back()->with('success', 'Lead ditandai WON dan tertaut ke customer.');
        }

        if (in_array($status, ['NEW', 'FOLLOW_UP', 'LOST'], true)) {
            $this->LeaderModel->update($id, [
                'status'      => $status,
                'customer_id' => $status === 'LOST' ? null : $lead->customer_id,
                'tanggal_won' => $status === 'LOST' ? null : $lead->tanggal_won,
            ]);
            return redirect()->back()->with('success', 'Status lead diperbarui.');
        }

        return redirect()->back()->with('error', 'Status tidak valid.');
    }

    public function leads_hapus()
    {
        if ($r = $this->writeOrRedirect()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('marketing/leads'));
        }
        $id = (int)$this->request->getPost('id');
        if (!$this->LeaderModel->find($id)) {
            return redirect()->back()->with('error', 'Lead tidak ditemukan.');
        }
        $this->LeaderModel->delete($id);
        return redirect()->back()->with('success', 'Lead dihapus.');
    }

    // ── Biaya Iklan ───────────────────────────────────────────────

    public function ads()
    {
        if ($r = $this->readOrRedirect()) {
            return $r;
        }

        $bulan = (int)($this->request->getGet('bulan') ?? date('n'));
        $tahun = (int)($this->request->getGet('tahun') ?? date('Y'));
        if (!$this->validPeriod($bulan, $tahun)) {
            $bulan = (int)date('n');
            $tahun = (int)date('Y');
        }

        $rows = $this->AdsModel->findByPeriod($bulan, $tahun);

        return view('template', [
            'body'    => 'marketing/ads',
            'akun'    => (new \App\Models\ModelAuth())->getById(session('ID_AKUN')),
            'bulan'   => $bulan,
            'tahun'   => $tahun,
            'rows'    => $rows,
            'channels' => $this->ChannelModel->active(),
            'adsByChannel' => $this->Service->adsCostByChannel($bulan, $tahun),
            'canWrite' => $this->canWrite(),
        ]);
    }

    public function ads_simpan()
    {
        if ($r = $this->writeOrRedirect()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('marketing/ads'));
        }

        $id        = (int)($this->request->getPost('id') ?? 0);
        $tanggal   = trim((string)$this->request->getPost('tanggal') ?: '');
        $bulan     = (int)$this->request->getPost('period_month');
        $tahun     = (int)$this->request->getPost('period_year');
        $channelId = (int)$this->request->getPost('channel_id');
        $campaign  = trim((string)$this->request->getPost('campaign'));
        $amountRaw = trim((string)$this->request->getPost('amount'));
        $note      = trim((string)$this->request->getPost('note') ?: '');

        if (!$this->validPeriod($bulan, $tahun)) {
            return redirect()->back()->with('error', 'Periode biaya iklan tidak valid.');
        }
        // Bila tanggal diisi, bulan/tahun mengikuti tanggal.
        if ($tanggal !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
                return redirect()->back()->with('error', 'Format tanggal tidak valid.');
            }
            $bulan = (int)date('n', strtotime($tanggal));
            $tahun = (int)date('Y', strtotime($tanggal));
        } else {
            $tanggal = null;
        }
        if (!is_numeric($amountRaw) || (float)$amountRaw < 0) {
            return redirect()->back()->with('error', 'Nominal biaya iklan harus angka valid.');
        }
        $amount = (float)$amountRaw;
        if ($channelId > 0 && !$this->ChannelModel->find($channelId)) {
            $channelId = null;
        }
        if ($campaign === '') {
            $campaign = 'General';
        }

        $data = [
            'period_month' => $bulan,
            'period_year'  => $tahun,
            'tanggal'      => $tanggal,
            'channel_id'   => $channelId > 0 ? $channelId : null,
            'campaign'     => $campaign,
            'amount'       => $amount,
            'note'         => $note !== '' ? $note : null,
            'created_by'   => $this->currentAkun(),
        ];

        $existing = $this->AdsModel->getByUnique($bulan, $tahun, $channelId, $campaign);
        if ($existing) {
            $this->AdsModel->update($existing->id, $data);
        } else {
            $this->AdsModel->insert($data);
        }

        return redirect()->back()->with('success', 'Biaya iklan tersimpan.');
    }

    public function ads_hapus()
    {
        if ($r = $this->writeOrRedirect()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('marketing/ads'));
        }
        $id = (int)$this->request->getPost('id');
        if (!$this->AdsModel->find($id)) {
            return redirect()->back()->with('error', 'Biaya iklan tidak ditemukan.');
        }
        $this->AdsModel->delete($id);
        return redirect()->back()->with('success', 'Biaya iklan dihapus.');
    }
}