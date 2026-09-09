<?php

namespace App\Controllers;

use App\Models\ModelContent;
use App\Models\ModelContentChecklist;
use App\Models\ModelContentPerson;
use App\Models\ModelContentQc;
use App\Models\ModelContentType;
use App\Models\ModelBrandChecklistItem;
use App\Models\ModelContentUnit;
use App\Models\ModelPerformanceMetric;
use App\Models\ModelPlatform;
use App\Models\ModelPublication;
use App\Models\ModelPublicationPerformance;
use App\Models\ModelUnit;
use App\Services\Konten\ContentKpiService;
use App\Services\Konten\ContentScopeService;
use App\Services\Konten\ContentWorkflowService;

/**
 * Dashboard Content Management — KPI Multimedia/Creative.
 *
 * Authorization menggunakan session existing (ID_AKUN / ID_JABATAN / ID_UNIT).
 * Role = apa yang boleh dilakukan, Scope = data mana yang boleh dilihat.
 */
class Konten extends BaseController
{
    protected $ContentModel;
    protected $ContentTypeModel;
    protected $BrandChecklistItemModel;
    protected $ContentChecklistModel;
    protected $ContentPersonModel;
    protected $ContentQcModel;
    protected $ContentUnitModel;
    protected $PerformanceMetricModel;
    protected $PlatformModel;
    protected $PublicationModel;
    protected $PublicationPerformanceModel;
    protected $UnitModel;

    protected $KpiService;
    protected $WorkflowService;
    protected $ScopeService;

    public function __construct()
    {
        $this->ContentModel             = new ModelContent();
        $this->ContentTypeModel         = new ModelContentType();
        $this->BrandChecklistItemModel  = new ModelBrandChecklistItem();
        $this->ContentChecklistModel    = new ModelContentChecklist();
        $this->ContentPersonModel       = new ModelContentPerson();
        $this->ContentQcModel           = new ModelContentQc();
        $this->ContentUnitModel         = new ModelContentUnit();
        $this->PerformanceMetricModel   = new ModelPerformanceMetric();
        $this->PlatformModel            = new ModelPlatform();
        $this->PublicationModel         = new ModelPublication();
        $this->PublicationPerformanceModel = new ModelPublicationPerformance();
        $this->UnitModel                = new ModelUnit();

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

        return view('template', [
            'body'   => 'konten/dashboard',
            'akun'   => (new \App\Models\ModelAuth())->getById(session('ID_AKUN')),
            'bulan'  => $bulan,
            'tahun'  => $tahun,
            'stats'  => $stats,
            'kpi'    => $kpi,
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
            'metrics'     => $this->PerformanceMetricModel->optionsActive(),
            'units'       => $this->UnitModel->orderBy('idunit', 'ASC')->findAll(),
            'allowedUnits'=> $this->allowedUnitIds(),
            'peoples'     => $this->ContentModel->activePeople(),
            'multimediaPeoples' => $this->ContentModel->multimediaPeople(),
            'selectedUnits' => $selectedUnits,
            'talentIds'   => $talentIds,
            'creativeIds' => $creativeIds,
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
        $metricId = $this->request->getPost('performance_metric_id') ? (int)$this->request->getPost('performance_metric_id') : null;
        $perfTargetPost = $this->request->getPost('performance_target');
        $perfTarget = ($perfTargetPost !== null && $perfTargetPost !== '') ? (float)$perfTargetPost : null;

        $jenis = strtoupper(trim((string)$this->request->getPost('jenis_konten')));
        $jenis = in_array($jenis, ['REGULAR', 'ADS'], true) ? $jenis : 'REGULAR';

        $data = [
            'judul'               => $judul,
            'deskripsi'           => $this->request->getPost('deskripsi') ?: null,
            'content_type_id'     => $contentTypeId ?: null,
            'jenis_konten'        => $jenis,
            'target_scope'        => $targetScope,
            'deadline'            => $deadline,
            'performance_metric_id' => $metricId ?: null,
            'performance_target'  => $perfTarget,
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
        foreach ($publications as $pub) {
            $pub->performances = $this->PublicationModel->performances((int)$pub->id);
        }

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
            'metrics'       => $this->PerformanceMetricModel->optionsActive(),
            'units'         => $this->UnitModel->orderBy('idunit', 'ASC')->findAll(),
            'allowedUnits'  => $this->allowedUnitIds(),
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

        $content = $this->ContentModel->find($id);
        if (!$content) {
            return redirect()->back()->with('error', 'Konten tidak ditemukan.');
        }

        $res = $this->WorkflowService->qc($content, $result, $note, $this->currentAkun());

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

    public function save_performance()
    {
        if ($r = $this->assertWrite()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('konten'));
        }

        $publicationId = (int)$this->request->getPost('publication_id');
        $metricId      = (int)$this->request->getPost('metric_id');
        $month         = (int)$this->request->getPost('period_month');
        $year          = (int)$this->request->getPost('period_year');
        $target        = (float)($this->request->getPost('target') ?: 0);
        $actual        = (float)($this->request->getPost('actual') ?: 0);
        $perfId        = (int)($this->request->getPost('id') ?? 0);

        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            return redirect()->back()->with('error', 'Periode performa tidak valid.');
        }

        $pub = $this->PublicationModel->find($publicationId);
        if (!$pub) {
            return redirect()->back()->with('error', 'Publikasi tidak ditemukan.');
        }

        $achievement = $target > 0 ? round($actual / $target * 100, 2) : null;

        $data = [
            'publication_id' => $publicationId,
            'metric_id'      => $metricId,
            'period_month'   => $month,
            'period_year'    => $year,
            'target'         => $target,
            'actual'         => $actual,
            'achievement'    => $achievement,
        ];

        if ($perfId > 0 && $this->PublicationPerformanceModel->find($perfId)) {
            // Mode edit: ubah baris yang diklik (field form terisi-ulang dari data row tsb).
            $this->PublicationPerformanceModel->update($perfId, $data);
        } else {
            $existing = $this->PublicationPerformanceModel->getByUnique($publicationId, $metricId, $month, $year);
            if ($existing) {
                $this->PublicationPerformanceModel->update($existing->id, $data);
            } else {
                $this->PublicationPerformanceModel->insert($data);
            }
        }

        return redirect()->back()->with('success', 'Performa publikasi tersimpan.');
    }

    public function hapus_performance()
    {
        if ($r = $this->assertWrite()) {
            return $r;
        }
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('konten'));
        }

        $perfId = (int)$this->request->getPost('id');
        $perf = $this->PublicationPerformanceModel->find($perfId);
        if (!$perf) {
            return redirect()->back()->with('error', 'Performa tidak ditemukan.');
        }

        $this->PublicationPerformanceModel->delete($perfId);
        return redirect()->back()->with('success', 'Performa publikasi dihapus.');
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