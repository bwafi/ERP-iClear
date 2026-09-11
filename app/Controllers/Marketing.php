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

    // ── Detail Prospek Marketing ──────────────────────────────────

    private function db()
    {
        return \Config\Database::connect();
    }

    /** Map idservice → no_service (untuk menampilkan tautan service di tabel). */
    private function serviceMap(): array
    {
        $rows = $this->db()->query(
            "SELECT idservice, no_service FROM service WHERE status_service = 4"
        )->getResultArray();
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['idservice']] = (string)$r['no_service'];
        }
        return $map;
    }

    /** Omset service selesai = sum(service_sparepart.sub_total). */
    /**
     * Omset service selesai = sum(service_sparepart.sub_total).
     * HPP TIDAK dikurangkan (laba terpisah: laba = sub_total - hpp).
     */
    private function serviceOmset(int $serviceId): float
    {
        $row = $this->db()->query(
            "SELECT COALESCE(SUM(sp.sub_total), 0) AS t
             FROM service_sparepart sp
             WHERE sp.service_idservice = ?",
            [$serviceId]
        )->getRow();
        return round((float)$row->t, 0);
    }

    /** Cari service via AJAX. Param `s=1` hanya service SELESAI (utk CLOSED). */
    public function search_service()
    {
        if ($r = $this->readOrRedirect()) {
            return $r;
        }

        $q          = trim((string)$this->request->getGet('q'));
        $unitId     = (int)$this->request->getGet('unit_id');
        $sdate      = trim((string)$this->request->getGet('sdate'));
        $selesaiOnly = (int)$this->request->getGet('s') === 1;

        // CLOSED → hanya service selesai; selain itu semua kecuali dibatalkan.
        $where = $selesaiOnly ? 's.status_service = 4' : 's.status_service != 5';
        $param = [];
        if ($q !== '') {
            $where .= " AND (CAST(s.idservice AS CHAR) = ? OR s.no_service LIKE ? OR p.nama LIKE ?)";
            $like  = '%' . $q . '%';
            array_push($param, $q, $like, $like);
        }
        // CLOSED (closing) → cari per TANGGAL, semua unit (cabang tampil di list).
        if ($selesaiOnly && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sdate)) {
            $where .= ' AND DATE(s.tanggal_selesai) = ?';
            $param[] = $sdate;
        }
        // Filter unit hanya untuk non-CLOSED / saat unit dikirim & valid.
        if ($unitId > 0 && !$selesaiOnly && in_array($unitId, array_map(fn($u) => (int)$u->idunit, $this->units()), true)) {
            $where .= ' AND s.unit_idunit = ?';
            $param[] = $unitId;
        }

        $rows = $this->db()->query(
            "SELECT s.idservice, s.no_service, s.unit_idunit,
                    s.pelanggan_id_pelanggan, s.keluhan, s.keterangan,
                    DATE_FORMAT(COALESCE(s.tanggal_selesai, s.created_at), '%Y-%m-%d') AS service_date,
                    CASE WHEN s.status_service = 4 THEN 'SELESAI' ELSE 'PROSES' END AS status_label,
                    COALESCE(u.NAMA_UNIT, '') AS unit_name,
                    COALESCE(p.nama, '') AS nama_pelanggan,
                    COALESCE(p.no_hp, '') AS no_hp_pelanggan,
                    COALESCE(SUM(sp.sub_total), 0) AS sub_total,
                    COALESCE(SUM(sp.hpp_penjualan * sp.jumlah), 0) AS total_hpp
             FROM service s
             LEFT JOIN service_sparepart sp ON sp.service_idservice = s.idservice
             LEFT JOIN pelanggan p ON p.id_pelanggan = s.pelanggan_id_pelanggan
             LEFT JOIN unit u ON u.idunit = s.unit_idunit
             WHERE {$where}
             GROUP BY s.idservice, s.no_service, s.unit_idunit, s.pelanggan_id_pelanggan,
                      s.keluhan, s.keterangan, s.tanggal_selesai, s.created_at,
                      p.nama, p.no_hp, u.NAMA_UNIT
             ORDER BY s.tanggal_selesai DESC, s.created_at DESC
             LIMIT 30",
            $param
        )->getResultArray();

        $result = array_map(function ($r) {
            $omset = max(0, (int)$r['sub_total']);
            return [
                'id'           => (int)$r['idservice'],
                'text'         => trim($r['no_service'] . ' · ' . $r['nama_pelanggan']),
                'status_label' => $r['status_label'],
                'service_date' => $r['service_date'],
                'omset'        => $omset,
                'nama'         => $r['nama_pelanggan'],
                'no_hp'        => $r['no_hp_pelanggan'],
                'keterangan'   => trim(trim((string)$r['keluhan']) . ' ' . trim((string)$r['keterangan'])),
                'unit_id'      => (int)$r['unit_idunit'],
                'unit_name'    => $r['unit_name'],
            ];
        }, $rows);

        return $this->response->setContentType('application/json')->setJSON([
            'results' => $result,
            'count'   => count($result),
        ]);
    }

    /** Data service lengkap + pelanggan untuk pengisian otomatis prospek. */
    private function serviceDetail(int $serviceId): ?object
    {
        return $this->db()->query(
            "SELECT s.idservice, s.no_service, s.keluhan, s.keterangan,
                    s.unit_idunit, s.status_service, s.pelanggan_id_pelanggan,
                    s.tanggal_selesai,
                    COALESCE(p.nama, '') AS nama_pelanggan,
                    COALESCE(p.no_hp, '') AS no_hp_pelanggan
             FROM service s
             LEFT JOIN pelanggan p ON p.id_pelanggan = s.pelanggan_id_pelanggan
             WHERE s.idservice = ?",
            [$serviceId]
        )->getRow();
    }

    /**
     * Field CLOSED yang SELALU diambil dari service (source of truth).
     * Omset dihitung server-side: SUM(service_sparepart.sub_total).
     * Tidak mempercayai kiriman frontend utk nama/noHP/keterangan/omset.
     */
    private function closedFieldsFromService(object $svc, int $serviceId): array
    {
        $keterangan = trim(trim((string)$svc->keluhan) . ' ' . trim((string)$svc->keterangan));
        $omset      = $this->serviceOmset($serviceId);
        $serviceDate = !empty($svc->tanggal_selesai)
            ? date('Y-m-d', strtotime($svc->tanggal_selesai))
            : null;

        return [
            'service_id'  => $serviceId,
            'nama'        => mb_substr(trim($svc->nama_pelanggan), 0, 150),
            'no_telp_wa'  => trim($svc->no_hp_pelanggan) !== '' ? mb_substr(trim($svc->no_hp_pelanggan), 0, 30) : null,
            'keterangan'  => $keterangan !== '' ? mb_substr($keterangan, 0, 255) : null,
            'unit_id'     => (int)$svc->unit_idunit,
            'omset'       => $omset > 0 ? $omset : null,
            'tanggal'     => $serviceDate,
        ];
    }

    public function leads()
    {
        if ($r = $this->readOrRedirect()) {
            return $r;
        }

        $bulan = (int)($this->request->getGet('bulan') ?? date('n'));
        $tahun = (int)($this->request->getGet('tahun') ?? date('Y'));
        $status = strtoupper(trim((string)$this->request->getGet('status') ?: ''));
        $platform = trim((string)$this->request->getGet('platform') ?: '');
        $unitId = (int)$this->request->getGet('unit_id');
        if (!$this->validPeriod($bulan, $tahun)) {
            $bulan = (int)date('n');
            $tahun = (int)date('Y');
        }
        if ($status !== '' && !in_array($status, \App\Models\ModelMarketingLead::PROSPEK_STATUSES, true)) {
            $status = '';
        }
        if ($unitId > 0 && !in_array($unitId, array_map(fn($u) => (int)$u->idunit, $this->units()), true)) {
            $unitId = 0;
        }

        // Pagination daftar detail prospek manual (baris non-Kommo).
        $perPage = 25;
        $page    = max(1, (int)$this->request->getGet('page'));
        $statusFilter   = $status !== '' ? $status : null;
        $platformFilter = $platform !== '' ? $platform : null;
        $unitFilter     = $unitId > 0 ? $unitId : null;
        $total   = $this->LeaderModel->countDetailProspek($bulan, $tahun, $statusFilter, $platformFilter, $unitFilter);
        $totalPages = (int)ceil($total / $perPage);
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
        }
        $rows = $this->LeaderModel->findDetailProspek($bulan, $tahun, $statusFilter, $platformFilter, $unitFilter, $perPage, ($page - 1) * $perPage);

        $unitList = $this->units();
        $unitMap  = [];
        foreach ($unitList as $u) {
            $unitMap[(int)$u->idunit] = $u->NAMA_UNIT;
        }

        return view('template', [
            'body'      => 'marketing/leads',
            'akun'      => (new \App\Models\ModelAuth())->getById(session('ID_AKUN')),
            'bulan'     => $bulan,
            'tahun'     => $tahun,
            'status'    => $status,
            'platform'  => $platform,
            'statuses'  => \App\Models\ModelMarketingLead::PROSPEK_STATUSES,
            'platforms' => (new ModelMarketingPlatform())->active(),
            'units'     => $unitList,
            'unitId'    => $unitId,
            'unitMap'   => $unitMap,
            'serviceMap'=> $this->serviceMap(),
            'rows'      => $rows,
            'currentPage' => $page,
            'perPage'     => $perPage,
            'total'       => $total,
            'totalPages'  => $totalPages,
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

        $id             = (int)($this->request->getPost('id') ?? 0);
        $tanggal        = trim((string)$this->request->getPost('tanggal'));
        $nama           = trim((string)$this->request->getPost('nama'));
        $platform       = trim((string)$this->request->getPost('platform'));
        $unitId         = (int)$this->request->getPost('unit_id');
        $noTelp         = trim((string)$this->request->getPost('no_telp_wa'));
        $keterangan     = trim((string)$this->request->getPost('keterangan'));
        $status         = strtoupper(trim((string)$this->request->getPost('status') ?: \App\Models\ModelMarketingLead::STATUS_PROSPEK));
        $tanggalBooking = trim((string)$this->request->getPost('tanggal_booking'));
        $serviceId      = (int)$this->request->getPost('service_id');
        $catatan        = trim((string)$this->request->getPost('catatan'));

        if ($tanggal === '') {
            return redirect()->back()->with('error', 'Tanggal wajib diisi.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return redirect()->back()->with('error', 'Format tanggal tidak valid.');
        }
        if ($platform === '') {
            return redirect()->back()->with('error', 'Platform wajib dipilih.');
        }
        if (!in_array($status, \App\Models\ModelMarketingLead::PROSPEK_STATUSES, true)) {
            $status = \App\Models\ModelMarketingLead::STATUS_PROSPEK;
        }
        if ($tanggalBooking !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalBooking)) {
            $tanggalBooking = '';
        }

        // ── Ambil / validasi service ─────────────────────────────────
        $svc = null;
        if ($serviceId > 0) {
            $svc = $this->serviceDetail($serviceId);
            if (!$svc) {
                return redirect()->back()->with('error', 'Service tidak ditemukan.');
            }
            // CLOSED → wajib service SELESAI (omset otomatis).
            if ($status === \App\Models\ModelMarketingLead::STATUS_CLOSED && (int)$svc->status_service !== 4) {
                return redirect()->back()->with('error', 'Status CLOSED wajib memilih service yang sudah SELESAI.');
            }
            // Selain CLOSED → service boleh dipilih asal tidak dibatalkan.
            if ($status !== \App\Models\ModelMarketingLead::STATUS_CLOSED && (int)$svc->status_service === 5) {
                return redirect()->back()->with('error', 'Service yang dibatalkan tidak dapat dipilih.');
            }
        }

        // Baris manual (detail prospek) yang boleh diedit / menjadi basis existing.
        $existing = null;
        if ($id > 0) {
            $existing = $this->LeaderModel->findManualById($id);
            if (!$existing) {
                return redirect()->back()->with('error', 'Prospek tidak ditemukan.');
            }
        }

        $omset = 0.0;
        if ($status === \App\Models\ModelMarketingLead::STATUS_CLOSED) {
            // CLOSED → service adalah source of truth. Semua field diambil
            // ulang DARI SERVICE, termasuk nama/no HP/keterangan/unit/tanggal.
            // Data manual (mis. nama lama dari DATANG) TIDAK dipertahankan.
            if (!$svc) {
                return redirect()->back()->with('error', 'Status CLOSED wajib memilih service yang sudah SELESAI.');
            }
            $closed = $this->closedFieldsFromService($svc, $serviceId);
            $tanggal        = $closed['tanggal'] ?: $tanggal;
            $nama           = $closed['nama'];
            $noTelp         = (string)$closed['no_telp_wa'];
            $keterangan     = (string)$closed['keterangan'];
            $unitId         = $closed['unit_id'];
            $omset          = (float)$closed['omset'];
            // Tanggal booking tidak relevan untuk CLOSED.
            $tanggalBooking = '';
        } else {
            // Non-CLOSED → data prospek MEMAKAI input manual yang ada.
            // Service hanya tautan opsional, bukan sumber data.
            if ($nama === '') {
                $nama = $existing ? trim((string)$existing->nama) : ($svc ? trim($svc->nama_pelanggan) : '');
            }
            if ($nama === '') {
                return redirect()->back()->with('error', 'Nama/Akun wajib diisi bila belum ada service.');
            }
            if ($noTelp === '') {
                $noTelp = $existing ? trim((string)$existing->no_telp_wa) : ($svc ? trim($svc->no_hp_pelanggan) : '');
            }
            if ($keterangan === '') {
                $keterangan = $existing
                    ? trim((string)$existing->keterangan)
                    : ($svc ? trim(trim((string)$svc->keluhan) . ' ' . trim((string)$svc->keterangan)) : '');
            }
            if ($unitId <= 0) {
                $unitId = $existing ? (int)$existing->unit_id : ($svc ? (int)$svc->unit_idunit : 0);
            }
        }

        // Unit harus valid (dari pilihan manual / existing / service).
        $unitList   = $this->units();
        $validUnits = array_map(fn($u) => (int)$u->idunit, $unitList);
        if (!in_array($unitId, $validUnits, true)) {
            return redirect()->back()->with('error', 'Unit/cabang tidak valid.');
        }

        $data = [
            'tanggal'         => $tanggal,
            'nama'            => mb_substr($nama, 0, 150),
            'platform'        => mb_substr($platform, 0, 50),
            'unit_id'         => $unitId,
            'no_telp_wa'      => $noTelp !== '' ? mb_substr($noTelp, 0, 30) : null,
            'keterangan'      => $keterangan !== '' ? mb_substr($keterangan, 0, 255) : null,
            'status'          => $status,
            'tanggal_booking' => $tanggalBooking !== '' ? $tanggalBooking : null,
            'omset'           => $omset > 0 ? $omset : null,
            'service_id'      => $serviceId > 0 ? $serviceId : null,
            'catatan'         => $catatan !== '' ? $catatan : null,
            // tanggal_won = tanggal tercatat saat CLOSED → sumber KPI customer/omzet
            // (baris Kommo tetap dikeluarkan dari KPI lewat kommo_lead_id IS NULL).
            'tanggal_won'     => $status === \App\Models\ModelMarketingLead::STATUS_CLOSED ? $tanggal : null,
            'created_by'      => $this->currentAkun(),
        ];

        if ($id > 0) {
            $this->LeaderModel->update($id, $data);
        } else {
            $data['nomor'] = $this->LeaderModel->nextNomorForDate($tanggal);
            $this->LeaderModel->insert($data);
        }

        return redirect()->back()->with('success', 'Detail prospek tersimpan.');
    }

    public function leads_status()
    {
        if ($r = $this->writeOrRedirect()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('marketing/leads'));
        }

        $id     = (int)$this->request->getPost('id');
        $status = strtoupper(trim((string)$this->request->getPost('status')));
        $serviceId = (int)$this->request->getPost('service_id');
        $lead   = $this->LeaderModel->findManualById($id);
        if (!$lead) {
            return redirect()->back()->with('error', 'Prospek tidak ditemukan.');
        }
        if (!in_array($status, \App\Models\ModelMarketingLead::PROSPEK_STATUSES, true)) {
            return redirect()->back()->with('error', 'Status tidak valid.');
        }
        // CLOSED wajib memilih service yang SUDAH SELESAI (omset otomatis).
        if ($status === \App\Models\ModelMarketingLead::STATUS_CLOSED) {
            $svc = $serviceId > 0 ? $this->serviceDetail($serviceId) : null;
            if (!$svc || (int)$svc->status_service !== 4) {
                return redirect()->back()->with('error', 'Status CLOSED wajib memilih service yang sudah SELESAI.');
            }
        } else {
            $svc = null;
        }

        $update = [
            'status'      => $status,
            'tanggal_won' => $status === \App\Models\ModelMarketingLead::STATUS_CLOSED
                ? (trim((string)$lead->tanggal_won) !== '' ? $lead->tanggal_won : date('Y-m-d'))
                : null,
        ];

        // Keluar dari CLOSED → omset tidak lagi berlaku (KPI hanya hitung CLOSED).
        if ($status !== \App\Models\ModelMarketingLead::STATUS_CLOSED) {
            $update['omset'] = null;
        }

        // Menjadi CLOSED → service menjadi source of truth: seluruh field
        // data prospek (nama/noHP/keterangan/unit/tanggal/omset) disinkronkan
        // dari service. Data manual status lama (mis. nama dari DATANG) TIDAK
        // dipertahankan. Tanggal booking dikosongkan (tidak relevan saat CLOSED).
        if ($status === \App\Models\ModelMarketingLead::STATUS_CLOSED) {
            $closed = $this->closedFieldsFromService($svc, $serviceId);
            if ($closed['tanggal'] !== null) {
                $update['tanggal'] = $closed['tanggal'];
                $update['tanggal_won'] = $closed['tanggal'];
            }
            if ($closed['nama'] !== '')          { $update['nama'] = $closed['nama']; }
            if ($closed['no_telp_wa'] !== null)  { $update['no_telp_wa'] = $closed['no_telp_wa']; }
            if ($closed['keterangan'] !== null)  { $update['keterangan'] = $closed['keterangan']; }
            if ($closed['unit_id'] > 0)          { $update['unit_id'] = $closed['unit_id']; }
            $update['omset']        = $closed['omset'];
            $update['service_id']   = $serviceId;
            $update['tanggal_booking'] = null;
        }

        $this->LeaderModel->update($id, $update);

        return redirect()->back()->with('success', 'Status prospek diperbarui.');
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
        if (!$this->LeaderModel->findManualById($id)) {
            return redirect()->back()->with('error', 'Prospek tidak ditemukan.');
        }
        $this->LeaderModel->delete($id);
        return redirect()->back()->with('success', 'Detail prospek dihapus.');
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