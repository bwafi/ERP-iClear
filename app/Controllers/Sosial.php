<?php

namespace App\Controllers;

use App\Models\ModelUnit;
use App\Models\ModelSocialMediaAccount;
use App\Models\ModelSocialMediaPost;
use App\Models\ModelSocialMediaSnapshot;
use App\Models\ModelSocialMediaTarget;
use App\Services\SocialMedia\SocialMediaKpiService;
use App\Services\SocialMedia\SocialMediaScopeService;

/**
 * Modul Social Media KPI.
 *
 * Authorization memakai session existing (ID_JABATAN / ID_UNIT).
 *   - View KPI   : role 0,1,2,34,43
 *   - Kelola akun & target: role 0,1,2,34,43
 *
 * Sumber metric KPI: social_media_posts + social_media_metric_snapshots.
 */
class Sosial extends BaseController
{
    protected $UnitModel;
    protected $AccountModel;
    protected $PostModel;
    protected $SnapshotModel;
    protected $TargetModel;

    public function __construct()
    {
        $this->UnitModel     = new ModelUnit();
        $this->AccountModel  = new ModelSocialMediaAccount();
        $this->PostModel     = new ModelSocialMediaPost();
        $this->SnapshotModel = new ModelSocialMediaSnapshot();
        $this->TargetModel   = new ModelSocialMediaTarget();
    }

    private function role(): int
    {
        return (int)session('ID_JABATAN');
    }

    private function unit(): int
    {
        return (int)session('ID_UNIT');
    }

    private function assertView()
    {
        if (!SocialMediaScopeService::canView($this->role())) {
            return redirect()->to(base_url())->with('error', 'Anda tidak berhak mengakses modul Social Media.');
        }
        return null;
    }

    private function assertManage()
    {
        if (!SocialMediaScopeService::canManage($this->role())) {
            return redirect()->to(base_url())->with('error', 'Anda tidak berhak melakukan operasi ini.');
        }
        return null;
    }

    // ── KPI Dashboard ──────────────────────────────────────────────

    public function kpi()
    {
        if ($r = $this->assertView()) {
            return $r;
        }

        $bulan    = (int)($this->request->getGet('bulan') ?? date('n'));
        $tahun    = (int)($this->request->getGet('tahun') ?? date('Y'));
        $unitId   = (int)($this->request->getGet('unit_id') ?? 0);
        $platform = (string)($this->request->getGet('platform') ?? '');

        $units = $this->UnitModel->getUnit();

        $unitIds = $unitId > 0 ? [$unitId] : null;

        $kpi = new SocialMediaKpiService($this->SnapshotModel, $this->TargetModel, $this->PostModel);
        $summary = $kpi->summary($bulan, $tahun, $unitIds, $platform ?: null);
        $totals  = $kpi->totals($bulan, $tahun, $unitIds, $platform ?: null);

        return view('template', [
            'body'     => 'sosial/kpi',
            'units'    => $units,
            'summary'  => $summary,
            'totals'   => $totals,
            'bulan'    => $bulan,
            'tahun'    => $tahun,
            'unit_id'  => $unitId,
            'platform' => $platform,
            'platforms' => SocialMediaKpiService::PLATFORMS,
            'metrics'  => SocialMediaKpiService::METRICS,
            'layout'   => [],
        ]);
    }

    // ── Account Management ─────────────────────────────────────────

    public function account()
    {
        if ($r = $this->assertView()) {
            return $r;
        }

        return view('template', [
            'body'    => 'sosial/account',
            'units'   => $this->UnitModel->getUnit(),
            'accounts' => $this->AccountModel->withUnit(),
            'platforms' => ['facebook', 'tiktok'],
            'canManage' => SocialMediaScopeService::canManage($this->role()),
        ]);
    }

    public function account_simpan()
    {
        if ($r = $this->assertManage()) {
            return $r;
        }

        $unitId    = (int)$this->request->getPost('unit_id');
        $platform  = trim((string)$this->request->getPost('platform'));
        $profileUrl = trim((string)$this->request->getPost('profile_url'));

        if ($unitId <= 0 || $platform === '' || $profileUrl === '' || !in_array($platform, SocialMediaKpiService::PLATFORMS, true)) {
            return redirect()->back()->with('error', 'Unit, platform, dan Profile URL wajib diisi.');
        }

        $data = [
            'unit_id'             => $unitId,
            'platform'            => $platform,
            'account_name'        => trim((string)$this->request->getPost('account_name')),
            'username'            => trim((string)$this->request->getPost('username')) ?: null,
            'external_account_id' => trim((string)$this->request->getPost('external_account_id')) ?: null,
            'profile_url'         => $profileUrl,
            'provider'            => trim((string)$this->request->getPost('provider')) ?: 'bright_data',
            'is_active'           => (int)(bool)$this->request->getPost('is_active'),
        ];

        $existing = $this->AccountModel->findByIdentity($unitId, $platform, $profileUrl);

        if ($existing) {
            $this->AccountModel->update($existing->id, $data);
            session()->setFlashdata('sukses', 'Akun social media diperbarui.');
        } else {
            $this->AccountModel->insert($data);
            session()->setFlashdata('sukses', 'Akun social media ditambahkan.');
        }

        return redirect()->to(base_url('sosial/account'));
    }

    public function account_toggle()
    {
        if ($r = $this->assertManage()) {
            return $r;
        }

        $id = (int)$this->request->getPost('id');
        $account = $this->AccountModel->find($id);
        if (!$account) {
            return $this->response->setStatusCode(404)->setJSON(['message' => 'Akun tidak ditemukan.']);
        }

        $this->AccountModel->update($id, ['is_active' => $account->is_active ? 0 : 1]);

        return $this->response->setJSON(['is_active' => $account->is_active ? 0 : 1]);
    }

    public function account_hapus()
    {
        if ($r = $this->assertManage()) {
            return $r;
        }

        $id = (int)$this->request->getPost('id');
        $this->AccountModel->delete($id);

        return redirect()->back()->with('sukses', 'Akun social media dihapus.');
    }

    // ── Target Management ─────────────────────────────────────────

    public function target()
    {
        if ($r = $this->assertManage()) {
            return $r;
        }

        $bulan    = (int)($this->request->getGet('bulan') ?? date('n'));
        $tahun    = (int)($this->request->getGet('tahun') ?? date('Y'));
        $unitId   = (int)($this->request->getGet('unit_id') ?? 0);
        $platform = (string)($this->request->getGet('platform') ?? '');

        $periodMonth = sprintf('%04d-%02d', $tahun, $bulan);
        $targets = $this->TargetModel->targetsFor($periodMonth, $unitId ?: null, $platform ?: null);

        return view('template', [
            'body'     => 'sosial/target',
            'units'    => $this->UnitModel->getUnit(),
            'targets'  => $targets,
            'bulan'    => $bulan,
            'tahun'    => $tahun,
            'unit_id'  => $unitId,
            'platform' => $platform,
            'platforms' => SocialMediaKpiService::PLATFORMS,
            'metrics'  => SocialMediaKpiService::METRICS,
        ]);
    }

    public function target_simpan()
    {
        if ($r = $this->assertManage()) {
            return $r;
        }

        $bulan       = (int)$this->request->getPost('bulan');
        $tahun       = (int)$this->request->getPost('tahun');
        $unitId      = (int)$this->request->getPost('unit_id');
        $platform    = trim((string)$this->request->getPost('platform'));
        $metric      = trim((string)$this->request->getPost('metric'));
        $targetValue = (float)$this->request->getPost('target_value');

        if ($bulan < 1 || $bulan > 12 || !in_array($platform, SocialMediaKpiService::PLATFORMS, true)
            || !in_array($metric, SocialMediaKpiService::METRICS, true) || $targetValue < 0) {
            return redirect()->back()->with('error', 'Data target tidak valid.');
        }

        $this->TargetModel->upsertTarget(
            sprintf('%04d-%02d', $tahun, $bulan),
            $unitId,
            $platform,
            $metric,
            $targetValue
        );

        return redirect()->to(base_url('sosial/target?bulan=' . $bulan . '&tahun=' . $tahun . '&unit_id=' . $unitId))
            ->with('sukses', 'Target social media disimpan.');
    }

    public function target_hapus()
    {
        if ($r = $this->assertManage()) {
            return $r;
        }

        $id = (int)$this->request->getPost('id');
        $this->TargetModel->delete($id);

        return redirect()->back()->with('sukses', 'Target social media dihapus.');
    }
}