<?php

namespace App\Controllers;

use App\Models\ModelKpiTarget;
use App\Models\ModelKpiComponent;
use App\Models\ModelUnit;
use App\Models\ModelAuth;

/**
 * Editor Target KPI (tabel kpi_targets).
 *
 * HANYA Admin root (jabatan 1) dan Manager (jabatan 34) yang boleh mengakses,
 * karena perubahan target langsung berdampak ke perhitungan KPI
 * gaji/penilaian. Guard dilakukan di controller ini (bukan hanya di menu),
 * sehingga akses langsung via URL tetap tercegah untuk jabatan lain.
 */
class TargetKpi extends BaseController
{
    protected $TargetModel;
    protected $ComponentModel;
    protected $UnitModel;
    protected $AuthModel;

    public const CONTEXTS = ['default'];
    public const PERIOD_TYPES = ['monthly', 'quarterly', 'annual'];

    private const ADA_YANG_BOLEH = [1, 34];

    public function __construct()
    {
        $this->TargetModel    = new ModelKpiTarget();
        $this->ComponentModel = new ModelKpiComponent();
        $this->UnitModel      = new ModelUnit();
        $this->AuthModel      = new ModelAuth();
    }

    private function isRoot(): bool
    {
        return in_array((int)session('ID_JABATAN'), self::ADA_YANG_BOLEH, true);
    }

    private function deny()
    {
        return redirect()->to(base_url())
            ->with('error', 'Halaman ini khusus Admin Root / Manager.');
    }

    public function index()
    {
        if (!$this->isRoot()) {
            return $this->deny();
        }

        $filter = [
            'kpi_component_id' => (int)($this->request->getGet('kpi_component_id') ?? 0),
            'unit_id'          => (int)($this->request->getGet('unit_id') ?? 0),
            'position_id'      => (int)($this->request->getGet('position_id') ?? 0),
            'q'                => trim((string)$this->request->getGet('q')),
        ];

        $targets = $this->TargetModel->getEditorTargets($filter);

        return view('template', [
            'body'       => 'admin/target_kpi',
            'targets'    => $targets,
            'components' => $this->ComponentModel->orderBy('code', 'ASC')->findAll(),
            'units'      => $this->UnitModel->getUnit(),
            'positions'  => $this->AuthModel->getJabatan(),
            'contexts'   => self::CONTEXTS,
            'periodTypes' => self::PERIOD_TYPES,
            'filter'     => $filter,
        ]);
    }

    public function simpan()
    {
        if (!$this->isRoot()) {
            return $this->deny();
        }

        $id             = (int)$this->request->getPost('id');
        $kpiComponentId = (int)$this->request->getPost('kpi_component_id');
        $unitId         = (int)$this->request->getPost('unit_id');
        $positionId     = (int)$this->request->getPost('position_id');
        $periodType     = trim((string)$this->request->getPost('period_type'));
        $periodMonth    = (int)$this->request->getPost('period_month');
        $effectiveFrom  = trim((string)$this->request->getPost('effective_from'));
        $effectiveTo    = trim((string)$this->request->getPost('effective_to'));

        if ($kpiComponentId <= 0
            || !in_array($periodType ?: 'monthly', self::PERIOD_TYPES, true)
            || $effectiveFrom === '') {
            return redirect()->back()->with('error', 'Data target tidak valid.');
        }

        $data = [
            'kpi_component_id' => $kpiComponentId,
            'unit_id'          => $unitId > 0 ? $unitId : null,
            'position_id'      => $positionId > 0 ? $positionId : null,
            'context'          => 'default',
            'target_value'     => (float)$this->request->getPost('target_value'),
            'period_type'      => $periodType ?: 'monthly',
            'period_month'     => ($periodMonth >= 1 && $periodMonth <= 12) ? $periodMonth : null,
            'effective_from'   => $effectiveFrom,
            'effective_to'     => $effectiveTo ?: null,
        ];

        if ($id > 0 && $this->TargetModel->find($id)) {
            $this->TargetModel->update($id, $data);
            $sukses = 'Target KPI diperbarui.';
        } else {
            $data['created_by'] = (int)session('ID_AKUN');
            $this->TargetModel->insert($data);
            $sukses = 'Target KPI ditambahkan.';
        }

        return redirect()->back()->with('sukses', $sukses);
    }

    public function hapus()
    {
        if (!$this->isRoot()) {
            return $this->deny();
        }

        $id = (int)$this->request->getPost('id');
        if ($id > 0) {
            $this->TargetModel->delete($id);
        }

        return redirect()->back()->with('sukses', 'Target KPI dihapus.');
    }
}