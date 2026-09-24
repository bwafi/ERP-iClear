<?php

namespace App\Controllers;

use App\Models\ModelContent;
use App\Models\ModelContentBrief;
use App\Models\ModelContentCampaign;
use App\Models\ModelContentChecklist;
use App\Models\ModelContentPerson;
use App\Models\ModelContentQc;
use App\Models\ModelContentType;
use App\Models\ModelBrandChecklistItem;
use App\Models\ModelContentUnit;
use App\Models\ModelPlatform;
use App\Models\ModelPublication;
use App\Models\ModelChannel;
use App\Models\ModelChannelMetric;
use App\Models\ModelChannelPerformance;
use App\Models\ModelImprovement;
use App\Models\ModelUnit;
use App\Services\Konten\ContentKpiService;
use App\Services\Konten\ContentScopeService;
use App\Services\Konten\ContentWorkflowService;
use App\Services\Konten\MultimediaKpiService;

/**
 * Dashboard Content Management — KPI Multimedia/Creative.
 *
 * Authorization menggunakan session existing (ID_AKUN / ID_JABATAN / ID_UNIT).
 * Role = apa yang boleh dilakukan, Scope = data mana yang boleh dilihat.
 */
class Konten extends BaseController
{
    protected $ContentModel;
    protected $ContentBriefModel;
    protected $ContentCampaignModel;
    protected $ContentTypeModel;
    protected $BrandChecklistItemModel;
    protected $ContentChecklistModel;
    protected $ContentPersonModel;
    protected $ContentQcModel;
    protected $ContentUnitModel;
    protected $PlatformModel;
    protected $PublicationModel;
    protected $ChannelModel;
    protected $ChannelMetricModel;
    protected $ChannelPerformanceModel;
    protected $ImprovementModel;
    protected $UnitModel;

    protected $KpiService;
    protected $WorkflowService;
    protected $ScopeService;

    public function __construct()
    {
        $this->ContentModel             = new ModelContent();
        $this->ContentBriefModel        = new ModelContentBrief();
        $this->ContentCampaignModel     = new ModelContentCampaign();
        $this->ContentTypeModel         = new ModelContentType();
        $this->BrandChecklistItemModel  = new ModelBrandChecklistItem();
        $this->ContentChecklistModel    = new ModelContentChecklist();
        $this->ContentPersonModel       = new ModelContentPerson();
        $this->ContentQcModel           = new ModelContentQc();
        $this->ContentUnitModel         = new ModelContentUnit();
        $this->PlatformModel            = new ModelPlatform();
$this->PublicationModel          = new ModelPublication();
        $this->ChannelModel              = new ModelChannel();
        $this->ChannelMetricModel        = new ModelChannelMetric();
        $this->ChannelPerformanceModel   = new ModelChannelPerformance();
        $this->ImprovementModel         = new ModelImprovement();
        $this->UnitModel                 = new ModelUnit();

        $this->KpiService      = new ContentKpiService();
        $this->WorkflowService = new ContentWorkflowService();
        $this->ScopeService    = new ContentScopeService();
    }

    // ── Helpers role & scope ───────────────────────────────────────

    private function currentRole(): int
    {
        return (int)session('ID_JABATAN');
    }

    private function currentUnit(): int
    {
        return (int)session('ID_UNIT');
    }

    private function currentAkun(): int
    {
        return (int)session('ID_AKUN');
    }

    private function assertRead()
    {
        if (!ContentScopeService::canView($this->currentRole())) {
            return redirect()->to(base_url())->with('error', 'Anda tidak berhak mengakses modul Konten.');
        }
        return null;
    }

    private function assertWrite()
    {
        if (!ContentScopeService::canWrite($this->currentRole())) {
            return redirect()->to(base_url())->with('error', 'Anda tidak berhak melakukan operasi ini.');
        }
        return null;
    }

    private function assertQc()
    {
        if (!ContentScopeService::canQc($this->currentRole())) {
            return redirect()->to(base_url())->with('error', 'Anda tidak berhak melakukan QC / checklist.');
        }
        return null;
    }

    private function assertManage()
    {
        if (!ContentScopeService::canManageKpi($this->currentRole())) {
            return redirect()->to(base_url())->with('error', 'Anda tidak berhak mengelola campaign / improvement.');
        }
        return null;
    }

    private function scopeSql(): ?string
    {
        return $this->ScopeService->scopeSql($this->currentRole(), $this->currentUnit(), $this->currentAkun());
    }

    private function allowedUnitIds(): array
    {
        $units = $this->ScopeService->allowedUnits($this->currentRole(), $this->currentUnit(), $this->currentAkun());

        if ($units === null) {
            $rows = $this->UnitModel->findAll();
            $units = array_map('intval', array_column((array)$rows, 'idunit'));
        }

        return $units;
    }

    // ── Halaman ────────────────────────────────────────────────────

    public function dashboard()
    {
        if ($r = $this->assertRead()) {
            return $r;
        }

        $bulan = (int)($this->request->getGet('bulan') ?? date('n'));
        $tahun = (int)($this->request->getGet('tahun') ?? date('Y'));
        if ($bulan < 1 || $bulan > 12) {
            $bulan = (int)date('n');
        }

        $scopeSql = $this->ScopeService->kpiScopeSql($this->currentRole(), $this->currentUnit(), $this->currentAkun());

        $stats = $this->KpiService->monthlyStats($bulan, $tahun, $scopeSql);
        $kpi   = $this->KpiService->monthlyKpi($bulan, $tahun, $scopeSql);
        $kpiOwner = (new MultimediaKpiService())->monthlySummary($bulan, $tahun);

        return view('template', [
            'body'   => 'konten/dashboard',
            'akun'   => (new \App\Models\ModelAuth())->getById(session('ID_AKUN')),
            'bulan'  => $bulan,
            'tahun'  => $tahun,
            'stats'  => $stats,
            'kpi'    => $kpi,
            'kpiOwner' => $kpiOwner,
            'scopeLabel' => $this->scopeLabel(),
        ]);
    }

    public function index()
    {
        if ($r = $this->assertRead()) {
            return $r;
        }

        $units = $this->UnitModel->orderBy('idunit', 'ASC')->findAll();
        $allowedUnits = $this->allowedUnitIds();

        return view('template', [
            'body'             => 'konten/index',
            'akun'             => (new \App\Models\ModelAuth())->getById(session('ID_AKUN')),
            'contentTypes'     => $this->ContentTypeModel->optionsActive(),
            'platforms'        => $this->PlatformModel->optionsActive(),
            'units'            => $units,
            'allowedUnits'     => $allowedUnits,
            'allPeoples'       => $this->ContentModel->activePeople(),
            'multimediaPeoples'=> $this->ContentModel->multimediaPeople(),
            'statuses'         => ModelContent::STATUSES,
            'canWrite'         => ContentScopeService::canWrite($this->currentRole()),
        ]);
    }

    public function dt()
    {
        if (!ContentScopeService::canView($this->currentRole())) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'forbidden']);
        }

        $draw    = (int)$this->request->getGet('draw');
        $start   = (int)$this->request->getGet('start');
        $length  = (int)$this->request->getGet('length');

        $searchParam = $this->request->getGet('search');
        $search = '';
        if (is_array($searchParam)) {
            $search = trim((string)($searchParam['value'] ?? ''));
        } else {
            $search = trim((string)$searchParam);
        }

        $orderCol = $this->request->getGet('order') ? $this->request->getGet('order')[0]['column'] : 4;
        $orderDir = $this->request->getGet('order') ? $this->request->getGet('order')[0]['dir'] : 'desc';

        $columnMap = [
            0 => 'c.judul',
            1 => 'content_types.name',
            2 => 'c.target_scope',
            3 => 'c.deadline',
            4 => 'c.created_at',
            5 => 'c.status',
            6 => 'c.created_at',
        ];
        $orderCol = $columnMap[$orderCol] ?? 'c.deadline';
        $orderDir = strtolower($orderDir) === 'asc' ? 'ASC' : 'DESC';

        $filters = [
            'search'      => $search,
            'periode'     => $this->request->getGet('periode') ?: null,
            'unit'        => $this->request->getGet('unit') ?: null,
            'multimedia'  => $this->request->getGet('multimedia') ?: null,
            'talent'      => $this->request->getGet('talent') ?: null,
            'status'      => $this->request->getGet('status') ?: null,
            'platform'    => $this->request->getGet('platform') ?: null,
            'content_type'=> $this->request->getGet('content_type') ?: null,
            'scope_sql'   => $this->scopeSql(),
        ];

        // recordsTotal = total konten tanpa filter (hanya scope).
        $totalRecords = $this->ContentModel->countContentsDT([
            'scope_sql' => $filters['scope_sql'],
        ]);

        $filteredRecords = $this->ContentModel->countContentsDT($filters);
        $rows = $this->ContentModel->getContentsDT($length, $start, $filters, $orderCol, $orderDir);

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id'               => (int)$row->id,
                'judul'            => $row->judul,
                'content_type_name'=> $row->content_type_name ?: '-',
                'jenis_konten'     => $row->jenis_konten ?? 'REGULAR',
                'target_scope'     => $row->target_scope,
                'deadline'         => $row->deadline,
                'status'           => $row->status,
                'talent_names'     => $row->talent_names,
                'creative_names'   => $row->creative_names,
                'created_at'       => $row->created_at,
            ];
        }

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $data,
        ]);
    }

    // ── Create / Edit ──────────────────────────────────────────────

    public function form($id = null)
    {
        if ($r = $this->assertWrite()) {
            return $r;
        }

        $content = null;
        $selectedUnits = [];
        $talentIds = [];
        $creativeIds = [];

        if ($id !== null) {
            $content = $this->ContentModel->getById((int)$id);
            if (!$content) {
                return redirect()->to(base_url('konten'))->with('error', 'Konten tidak ditemukan.');
            }
            $selectedUnits = array_map(fn($u) => $u->unit_id, $this->ContentModel->targetUnits((int)$id));
            foreach ($this->ContentModel->people((int)$id) as $p) {
                if ($p->role === 'TALENT') {
                    $talentIds[] = (int)$p->akun_id;
                } else {
                    $creativeIds[] = (int)$p->akun_id;
                }
            }
        }

        return view('template', [
            'body'        => 'konten/form',
            'akun'        => (new \App\Models\ModelAuth())->getById(session('ID_AKUN')),
            'content'     => $content,
            'contentTypes'=> $this->ContentTypeModel->optionsActive(),
            'units'       => $this->UnitModel->orderBy('idunit', 'ASC')->findAll(),
            'allowedUnits'=> $this->allowedUnitIds(),
            'peoples'     => $this->ContentModel->activePeople(),
            'multimediaPeoples' => $this->ContentModel->multimediaPeople(),
            'selectedUnits' => $selectedUnits,
            'talentIds'   => $talentIds,
            'creativeIds' => $creativeIds,
            'campaigns'   => $this->ContentCampaignModel->orderBy('id', 'DESC')->findAll(100),
            'brief'       => $id !== null ? $this->ContentBriefModel->forContent((int)$id) : null,
        ]);
    }

    public function simpan()
    {
        if ($r = $this->assertWrite()) {
            return $r;
        }

        if (!$this->request->is('post')) {
            return redirect()->to(base_url('konten'));
        }

        $id = (int)($this->request->getPost('id') ?? 0);

        $judul = trim((string)$this->request->getPost('judul'));
        $deadline = trim((string)$this->request->getPost('deadline'));
        $targetScope = strtoupper(trim((string)$this->request->getPost('target_scope') ?: 'ALL'));
        $targetScope = in_array($targetScope, ['ALL', 'SELECTED'], true) ? $targetScope : 'ALL';

        $errors = [];
        if ($judul === '') {
            $errors[] = 'Judul konten wajib diisi.';
        }
        if ($deadline === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
            $errors[] = 'Deadline wajib diisi (format YYYY-MM-DD).';
        }
        if ($targetScope === 'SELECTED' && empty($this->request->getPost('target_units'))) {
            $errors[] = 'Pilih minimal satu unit target saat Target Scope = SELECTED.';
        }

        if (!empty($errors)) {
            return redirect()->back()->with('error', implode(' ', $errors));
        }

        $contentTypeId = $this->request->getPost('content_type_id') ? (int)$this->request->getPost('content_type_id') : null;

        $jenis = strtoupper(trim((string)$this->request->getPost('jenis_konten')));
        $jenis = in_array($jenis, ['REGULAR', 'ADS'], true) ? $jenis : 'REGULAR';

        $campaignId = (int)($this->request->getPost('campaign_id') ?? 0);
        if ($campaignId > 0 && !$this->ContentCampaignModel->find($campaignId)) {
            $errors[] = 'Campaign tidak valid.';
        }
        if (!empty($errors)) {
            return redirect()->back()->with('error', implode(' ', $errors));
        }

        $data = [
            'judul'               => $judul,
            'deskripsi'           => $this->request->getPost('deskripsi') ?: null,
            'content_type_id'     => $contentTypeId ?: null,
            'jenis_konten'        => $jenis,
            'campaign_id'         => $campaignId > 0 ? $campaignId : null,
            'target_scope'        => $targetScope,
            'deadline'            => $deadline,
        ];

        if ($id > 0) {
            $existing = $this->ContentModel->find($id);
            if (!$existing) {
                return redirect()->to(base_url('konten'))->with('error', 'Konten tidak ditemukan.');
            }
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->ContentModel->update($id, $data);
            $contentId = $id;
        } else {
            $data['status'] = 'DRAFT';
            $data['created_by'] = $this->currentAkun();
            $contentId = $this->ContentModel->insert($data);
        }

        // Target unit.
        $unitIds = $targetScope === 'SELECTED'
            ? $this->request->getPost('target_units')
            : [];
        $unitIds = is_array($unitIds) ? $unitIds : [];
        $this->ContentUnitModel->replaceForContent((int)$contentId, $unitIds);

        // People (Talent & Creative).
        $talentIds = $this->request->getPost('talent_ids') ?: [];
        $creativeIds = $this->request->getPost('creative_ids') ?: [];
        $this->ContentPersonModel->replaceForContent(
            (int)$contentId,
            is_array($talentIds) ? $talentIds : [],
            is_array($creativeIds) ? $creativeIds : []
        );

        // Brand checklist otomatis tersedia untuk content baru.
        $items = array_map(fn($i) => $i->id, $this->BrandChecklistItemModel->optionsActive());
        $this->ContentChecklistModel->ensureItemsForContent((int)$contentId, $items);

        // Brief kesesuaian (KPI Kesesuaian Brief 20%).
        $this->ContentBriefModel->upsertForContent(
            (int)$contentId,
            (string)$this->request->getPost('isi_brief'),
            (string)$this->request->getPost('requirement'),
            null
        );

        return redirect()->to(base_url('konten/detail/' . $contentId))
            ->with('success', $id > 0 ? 'Konten berhasil diperbarui.' : 'Konten berhasil dibuat.');
    }

    public function hapus($id)
    {
        if ($r = $this->assertWrite()) {
            return $r;
        }

        $content = $this->ContentModel->find((int)$id);
        if (!$content) {
            return redirect()->to(base_url('konten'))->with('error', 'Konten tidak ditemukan.');
        }

        $this->ContentModel->delete((int)$id); // relasi CASCADE otomatis

        return redirect()->to(base_url('konten'))->with('success', 'Konten dihapus.');
    }

    // ── Detail ─────────────────────────────────────────────────────

    public function detail($id)
    {
        if ($r = $this->assertRead()) {
            return $r;
        }

        $content = $this->ContentModel->getById((int)$id);
        if (!$content) {
            return redirect()->to(base_url('konten'))->with('error', 'Konten tidak ditemukan.');
        }

        $publications = $this->ContentModel->publications((int)$id);

        return view('template', [
            'body'          => 'konten/detail',
            'akun'          => (new \App\Models\ModelAuth())->getById(session('ID_AKUN')),
            'content'       => $content,
            'targetUnits'   => $this->ContentModel->targetUnits((int)$id),
            'peoples'       => $this->ContentModel->people((int)$id),
            'checklist'     => $this->ContentChecklistModel->where('content_id', $id)->findAll(),
            'checklistItems'=> $this->BrandChecklistItemModel->optionsActive(),
            'qcHistory'     => $this->ContentModel->qcHistory((int)$id),
            'publications'  => $publications,
            'platforms'     => $this->PlatformModel->optionsActive(),
            'units'         => $this->UnitModel->orderBy('idunit', 'ASC')->findAll(),
            'allowedUnits'  => $this->allowedUnitIds(),
            'brief'         => $this->ContentBriefModel->forContent((int)$id),
            'campaign'      => !empty($content->campaign_id) ? $this->ContentCampaignModel->find((int)$content->campaign_id) : null,
            'campaigns'     => $this->ContentCampaignModel->orderBy('id', 'DESC')->findAll(100),
            'canWrite'      => ContentScopeService::canWrite($this->currentRole()),
            'canQc'         => ContentScopeService::canQc($this->currentRole()),
            'nextStatuses'  => ContentWorkflowService::TRANSITIONS[$content->status] ?? [],
            'overdue'       => $content->deadline < date('Y-m-d') && !in_array($content->status, ['PUBLISHED', 'COMPLETED'], true),
        ]);
    }

    // ── Workflow & QC ──────────────────────────────────────────────

    public function setStatus()
    {
        if ($r = $this->assertWrite()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('konten'));
        }

        $id = (int)$this->request->getPost('id');
        $to = (string)$this->request->getPost('status');

        $content = $this->ContentModel->find($id);
        if (!$content) {
            return redirect()->back()->with('error', 'Konten tidak ditemukan.');
        }

        $result = $this->WorkflowService->transition($content, $to, $this->currentAkun());

        return redirect()->back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function qc()
    {
        if ($r = $this->assertQc()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('konten'));
        }

        $id = (int)$this->request->getPost('id');
        $result = strtoupper(trim((string)$this->request->getPost('qc_result')));
        $note = trim((string)$this->request->getPost('qc_note')) ?: null;
        $sesuaiBriefRaw = $this->request->getPost('sesuai_brief');
        $sesuaiBrief = ($sesuaiBriefRaw === '1' || $sesuaiBriefRaw === '0') ? (int)$sesuaiBriefRaw : null;

        $content = $this->ContentModel->find($id);
        if (!$content) {
            return redirect()->back()->with('error', 'Konten tidak ditemukan.');
        }

        $res = $this->WorkflowService->qc($content, $result, $note, $this->currentAkun(), $sesuaiBrief);

        return redirect()->back()->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function checklist()
    {
        if ($r = $this->assertQc()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->back();
        }

        $contentId = (int)$this->request->getPost('content_id');

        $content = $this->ContentModel->find($contentId);
        if (!$content) {
            return redirect()->back()->with('error', 'Konten tidak ditemukan.');
        }

        // Centang brand disimpan SEKALI untuk semua item (bukan per-item).
        $checks = $this->request->getPost('checks');
        $checks = is_array($checks) ? array_map('intval', $checks) : [];

        $activeItems = array_map(fn($i) => (int)$i->id, $this->BrandChecklistItemModel->optionsActive());

        $this->ContentChecklistModel->syncForContent($contentId, $activeItems, $checks, $this->currentAkun());

        return redirect()->back()->with('success', 'Checklist brand tersimpan.');
    }

    // ── Publication & Performance ──────────────────────────────────

    public function save_publication()
    {
        if ($r = $this->assertWrite()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('konten'));
        }

        $id        = (int)($this->request->getPost('id') ?? 0);
        $contentId = (int)$this->request->getPost('content_id');
        $unitId    = (int)$this->request->getPost('unit_id');
        $platformId = (int)$this->request->getPost('platform_id');
        $status    = strtoupper(trim((string)$this->request->getPost('status') ?: 'PLANNED'));
        $link      = trim((string)$this->request->getPost('link'));
        $publishedAt = trim((string)$this->request->getPost('published_at'));
        $publishedAt = $publishedAt !== '' ? str_replace('T', ' ', $publishedAt) : '';

        if (!in_array($status, ['PLANNED', 'PUBLISHED'], true)) {
            $status = 'PLANNED';
        }

        $content = $this->ContentModel->find($contentId);
        if (!$content) {
            return redirect()->back()->with('error', 'Konten tidak ditemukan.');
        }

        $data = [
            'content_id'   => $contentId,
            'unit_id'      => $unitId,
            'platform_id'  => $platformId,
            'link'         => $link !== '' ? $link : null,
            'status'       => $status,
            'published_at' => $publishedAt !== '' ? $publishedAt : ($status === 'PUBLISHED' ? date('Y-m-d H:i:s') : null),
        ];

        if ($id > 0) {
            $this->PublicationModel->update($id, $data);
        } else {
            $data['created_by'] = $this->currentAkun();
            $this->PublicationModel->insert($data);
        }

        return redirect()->to(base_url('konten/detail/' . $contentId))
            ->with('success', 'Publikasi disimpan.');
    }

    public function hapus_publication()
    {
        if ($r = $this->assertWrite()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('konten'));
        }

        $id = (int)$this->request->getPost('id');
        $pub = $this->PublicationModel->find($id);
        if (!$pub) {
            return redirect()->back()->with('error', 'Publikasi tidak ditemukan.');
        }

        $contentId = (int)$pub->content_id;
        $this->PublicationModel->delete($id); // performa ikut terhapus (CASCADE)

        return redirect()->to(base_url('konten/detail/' . $contentId))
            ->with('success', 'Publikasi dihapus.');
    }

    // ── Pertumbuhan Channel (KPI 10%) ─────────────────────────────

    public function channel()
    {
        if ($r = $this->assertRead()) {
            return $r;
        }

        $bulan = (int)($this->request->getGet('bulan') ?? date('n'));
        $tahun = (int)($this->request->getGet('tahun') ?? date('Y'));
        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > 2100) {
            $bulan = (int)date('n');
            $tahun = (int)date('Y');
        }

        $channels = $this->ChannelModel->active();
        $metrics = [];
        foreach ($channels as $ch) {
            $metrics[(int)$ch->id] = array_map(function ($m) {
                return [
                    'id'            => (int)$m->id,
                    'name'          => (string)$m->name,
                    'is_kpi'        => (int)$m->is_kpi === 1,
                    'target_growth' => $m->target_growth !== null ? (float)$m->target_growth : null,
                ];
            }, $this->ChannelMetricModel->byChannel((int)$ch->id));
        }

        $summary = $this->KpiService->channelGrowthSummary($bulan, $tahun);

        return view('template', [
            'body'         => 'konten/channel',
            'akun'         => (new \App\Models\ModelAuth())->getById(session('ID_AKUN')),
            'bulan'        => $bulan,
            'tahun'        => $tahun,
            'channels'     => $channels,
            'metrics'      => $metrics,
            'rows'         => $summary['rows'],
            'kpiAchievement' => $summary['kpi_achievement'],
            'canWrite'     => ContentScopeService::canWrite($this->currentRole()),
            'scopeLabel'   => $this->scopeLabel(),
        ]);
    }

    public function channel_simpan()
    {
        if ($r = $this->assertWrite()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('konten/channel'));
        }

        $channelId = (int)$this->request->getPost('channel_id');
        $metricId  = (int)$this->request->getPost('metric_id');
        $bulan     = (int)$this->request->getPost('period_month');
        $tahun     = (int)$this->request->getPost('period_year');
        $actualRaw = trim((string)$this->request->getPost('actual'));
        $targetRaw = trim((string)$this->request->getPost('target_growth'));
        $note      = trim((string)$this->request->getPost('note') ?: '');

        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > 2100) {
            return redirect()->back()->with('error', 'Periode performa channel tidak valid.');
        }
        if ($channelId < 1 || !$this->ChannelModel->find($channelId)) {
            return redirect()->back()->with('error', 'Channel tidak valid.');
        }
        $metric = $this->ChannelMetricModel
            ->where('id', $metricId)
            ->where('channel_id', $channelId)
            ->first();
        if (!$metric) {
            return redirect()->back()->with('error', 'Metric tidak sesuai dengan channel yang dipilih.');
        }
        if (!is_numeric($actualRaw) || (float)$actualRaw < 0) {
            return redirect()->back()->with('error', 'Actual harus berupa angka valid.');
        }
        $target = $targetRaw !== '' ? (float)$targetRaw : null;
        if ($target !== null && $target < 0) {
            return redirect()->back()->with('error', 'Target Growth tidak boleh negatif.');
        }

        $data = [
            'channel_id'    => $channelId,
            'metric_id'     => $metricId,
            'period_month'  => $bulan,
            'period_year'   => $tahun,
            'actual'        => (float)$actualRaw,
            'target_growth' => $target,
            'note'          => $note !== '' ? $note : null,
            'created_by'    => $this->currentAkun(),
        ];

        $existing = $this->ChannelPerformanceModel->getByUnique($channelId, $metricId, $bulan, $tahun);
        if ($existing) {
            $this->ChannelPerformanceModel->update($existing->id, $data);
            $msg = 'Data performa channel periode ini sudah ada, diperbarui.';
        } else {
            $this->ChannelPerformanceModel->insert($data);
            $msg = 'Performa channel tersimpan.';
        }

        return redirect()->to(base_url('konten/channel?bulan=' . $bulan . '&tahun=' . $tahun))->with('success', $msg);
    }

    public function channel_hapus()
    {
        if ($r = $this->assertWrite()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('konten/channel'));
        }

        $id = (int)$this->request->getPost('id');
        $row = $this->ChannelPerformanceModel->find($id);
        if (!$row) {
            return redirect()->back()->with('error', 'Data performa channel tidak ditemukan.');
        }

        $this->ChannelPerformanceModel->delete($id);
        return redirect()->back()->with('success', 'Performa channel dihapus.');
    }

    // ── Campaign (Support Campaign 10%) ────────────────────────────

    public function campaigns()
    {
        if ($r = $this->assertRead()) {
            return $r;
        }

        $bulan = (int)($this->request->getGet('bulan') ?? date('n'));
        $tahun = (int)($this->request->getGet('tahun') ?? date('Y'));
        if ($bulan < 1 || $bulan > 12) {
            $bulan = (int)date('n');
        }

        $campaigns = $this->ContentCampaignModel
            ->where('period_month', $bulan)
            ->where('period_year', $tahun)
            ->orderBy('id', 'ASC')
            ->findAll();

        // Jumlah konten per campaign (sudah dinilai 1 konten = 1 satuan).
        $contentCount = [];
        foreach ($campaigns as $c) {
            $contentCount[(int)$c->id] = $this->ContentModel
                ->where('campaign_id', $c->id)
                ->countAllResults();
        }

        return view('template', [
            'body'          => 'konten/campaigns',
            'akun'          => (new \App\Models\ModelAuth())->getById(session('ID_AKUN')),
            'bulan'         => $bulan,
            'tahun'         => $tahun,
            'campaigns'     => $campaigns,
            'contentCounts' => $contentCount,
            'statuses'      => ModelContentCampaign::STATUSES,
            'canWrite'      => ContentScopeService::canManageKpi($this->currentRole()),
        ]);
    }

    public function campaign_simpan()
    {
        if ($r = $this->assertManage()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('konten/campaigns'));
        }

        $id = (int)($this->request->getPost('id') ?? 0);
        $nama = trim((string)$this->request->getPost('nama'));
        $bulan = (int)$this->request->getPost('period_month');
        $tahun = (int)$this->request->getPost('period_year');
        $status = (string)$this->request->getPost('status');
        $targetDeadline = trim((string)$this->request->getPost('target_deadline'));
        $targetJumlah = trim((string)$this->request->getPost('target_jumlah_konten'));

        if ($nama === '') {
            return redirect()->back()->with('error', 'Nama campaign wajib diisi.');
        }
        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > 2100) {
            return redirect()->back()->with('error', 'Periode campaign tidak valid.');
        }
        if (!in_array($status, ModelContentCampaign::STATUSES, true)) {
            $status = 'draft';
        }
        $targetJumlahVal = ($targetJumlah !== '' && is_numeric($targetJumlah)) ? (int)$targetJumlah : null;
        if ($targetJumlahVal !== null && $targetJumlahVal < 0) {
            return redirect()->back()->with('error', 'Target jumlah konten tidak valid.');
        }

        $data = [
            'nama'                 => $nama,
            'deskripsi'            => $this->request->getPost('deskripsi') ?: null,
            'period_month'         => $bulan,
            'period_year'          => $tahun,
            'target_jumlah_konten' => $targetJumlahVal,
            'target_deadline'      => ($targetDeadline !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDeadline)) ? $targetDeadline : null,
            'status'               => $status,
            'pic'                  => (int)($this->request->getPost('pic') ?? 0) ?: null,
        ];

        if ($id > 0) {
            if (!$this->ContentCampaignModel->find($id)) {
                return redirect()->back()->with('error', 'Campaign tidak ditemukan.');
            }
            $this->ContentCampaignModel->update($id, $data);
            $msg = 'Campaign diperbarui.';
        } else {
            $data['created_by'] = $this->currentAkun();
            $id = (int)$this->ContentCampaignModel->insert($data);
            $msg = 'Campaign dibuat.';
        }

        return redirect()->to(base_url('konten/campaigns?bulan=' . $bulan . '&tahun=' . $tahun))
            ->with('success', $msg);
    }

    public function campaign_hapus()
    {
        if ($r = $this->assertManage()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('konten/campaigns'));
        }

        $id = (int)$this->request->getPost('id');
        $row = $this->ContentCampaignModel->find($id);
        if (!$row) {
            return redirect()->back()->with('error', 'Campaign tidak ditemukan.');
        }

        // Konten milik campaign dilepas (SET NULL), campaign dihapus.
        $this->ContentModel->where('campaign_id', $id)->set('campaign_id', null)->update();
        $this->ContentCampaignModel->delete($id);

        return redirect()->back()->with('success', 'Campaign dihapus.');
    }

    // ── Improvement (5%) ───────────────────────────────────────────

    public function improvements()
    {
        if ($r = $this->assertRead()) {
            return $r;
        }

        $bulan = (int)($this->request->getGet('bulan') ?? date('n'));
        $tahun = (int)($this->request->getGet('tahun') ?? date('Y'));
        if ($bulan < 1 || $bulan > 12) {
            $bulan = (int)date('n');
        }

        $isManager = ContentScopeService::canManageKpi($this->currentRole());
        $rows = $isManager
            ? $this->ImprovementModel->orderBy('id', 'DESC')->findAll(200)
            : $this->ImprovementModel->untukEmployeePeriode($this->currentAkun(), $bulan, $tahun);

        $peoples = $this->ContentModel->activePeople();
        $peopleById = [];
        foreach ($peoples as $p) {
            $peopleById[(int)$p->ID_AKUN] = $p;
        }

        return view('template', [
            'body'          => 'konten/improvements',
            'akun'          => (new \App\Models\ModelAuth())->getById(session('ID_AKUN')),
            'bulan'         => $bulan,
            'tahun'         => $tahun,
            'rows'          => $rows,
            'peopleById'    => $peopleById,
            'multimediaPeoples' => $this->ContentModel->multimediaPeople(),
            'statuses'      => ModelImprovement::STATUSES,
            'canWrite'      => ContentScopeService::canManageKpi($this->currentRole()),
            'canApprove'    => $isManager,
        ]);
    }

    public function improvement_simpan()
    {
        if ($r = $this->assertManage()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('konten/improvements'));
        }

        $id = (int)($this->request->getPost('id') ?? 0);
        $judul = trim((string)$this->request->getPost('judul'));
        $bulan = (int)$this->request->getPost('submission_month');
        $tahun = (int)$this->request->getPost('submission_year');

        if ($judul === '') {
            return redirect()->back()->with('error', 'Judul improvement wajib diisi.');
        }
        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > 2100) {
            return redirect()->back()->with('error', 'Periode tidak valid.');
        }

        $employeeId = (int)($this->request->getPost('employee_id') ?? 0);
        if ($employeeId <= 0) {
            $employeeId = $this->currentAkun();
        }

        $data = [
            'employee_id'       => $employeeId,
            'judul'             => $judul,
            'deskripsi'         => $this->request->getPost('deskripsi') ?: null,
            'kategori'          => $this->request->getPost('kategori') ?: null,
            'submission_month'  => $bulan,
            'submission_year'   => $tahun,
            'evidence'          => $this->request->getPost('evidence') ?: null,
        ];

        if ($id > 0 && $this->ImprovementModel->find($id)) {
            unset($data['employee_id']);
            $this->ImprovementModel->update($id, $data);
            $msg = 'Improvement diperbarui.';
        } else {
            $data['status'] = 'submitted';
            $id = (int)$this->ImprovementModel->insert($data);
            $msg = 'Improvement diajukan.';
        }

        return redirect()->to(base_url('konten/improvements?bulan=' . $bulan . '&tahun=' . $tahun))
            ->with('success', $msg);
    }

    public function improvement_status()
    {
        if ($r = $this->assertManage()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('konten/improvements'));
        }

        $id = (int)$this->request->getPost('id');
        $status = (string)$this->request->getPost('status');

        $row = $this->ImprovementModel->find($id);
        if (!$row) {
            return redirect()->back()->with('error', 'Improvement tidak ditemukan.');
        }
        if (!in_array($status, ['submitted', 'approved', 'implemented', 'rejected'], true)) {
            return redirect()->back()->with('error', 'Status tidak valid.');
        }

        $now = date('Y-m-d H:i:s');
        $data = ['status' => $status, 'evaluated_by' => $this->currentAkun(), 'updated_at' => $now];
        if ($status === 'approved' || $status === 'implemented') {
            $data['approved_at'] = $data['approved_at'] ?? $now;
        }
        $this->ImprovementModel->update($id, $data);

        return redirect()->back()->with('success', "Status improvement menjadi {$status}.");
    }

    public function improvement_hapus()
    {
        if ($r = $this->assertManage()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('konten/improvements'));
        }

        $id = (int)$this->request->getPost('id');
        $row = $this->ImprovementModel->find($id);
        if (!$row) {
            return redirect()->back()->with('error', 'Improvement tidak ditemukan.');
        }
        if ((int)$row->employee_id !== $this->currentAkun() && !ContentScopeService::canManageKpi($this->currentRole())) {
            return redirect()->back()->with('error', 'Anda tidak berhak menghapus improvement ini.');
        }

        $this->ImprovementModel->delete($id);

        return redirect()->back()->with('success', 'Improvement dihapus.');
    }

    // ── Lainnya ────────────────────────────────────────────────────

    private function scopeLabel(): string
    {
        $role = $this->currentRole();

        if (in_array($role, [0, 1, 2, 34], true)) {
            return 'Semua Cabang';
        }
        if (in_array($role, [43, 44], true)) {
            return 'Divisi Multimedia (semua cabang)';
        }
        if ($role === 40) {
            return 'Area SPV (sesuai spv_units)';
        }

        return $this->scopeSql() !== null ? 'Cabang ' . session('NAMA_UNIT') : 'Semua Cabang';
    }
}