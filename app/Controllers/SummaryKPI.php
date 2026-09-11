<?php

namespace App\Controllers;

use App\Models\ModelSuplier;
use App\Models\ModelUnit;
use App\Models\ModelAuth;
use App\Models\ModelSummaryKPI;

class SummaryKPI extends BaseController
{
    protected $AuthModel;
    protected $UnitModel;
    protected $SummaryKPIModel;

    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->UnitModel = new ModelUnit();
        $this->SummaryKPIModel = new ModelSummaryKPI();
    }

    /**
     * Informasi scope pegawai yang login (matriks EvaluatorAuthorizationService).
     *
     * @return array [me, myRole, isLintas, allowedUnitIds, allowedIds]
     *   - isLintas         : bool, role bisa lintas unit (0,1,2,34).
     *   - allowedUnitIds   : int[], unit yang boleh dilihat/difilter.
     *   - allowedIds       : int[]|null, ID pegawai yang boleh dilihat; null = semua.
     */
    private function scopeInfo(): array
    {
        $me       = $this->AuthModel->getById(session('ID_AKUN'));
        $myRole   = (int)($me->ID_JABATAN ?? 0);
        $myUnit   = (int)($me->ID_UNIT ?? 0);
        $myId     = (int)($me->ID_AKUN ?? 0);
        $isLintas = in_array($myRole, [0, 1, 2, 34], true);

        // Unit yang boleh dilihat.
        $allowedUnitIds = [$myUnit];
        if ($isLintas) {
            $db = \Config\Database::connect();
            $rows = $db->table('unit')->orderBy('idunit', 'ASC')->get()->getResultArray();
            $allowedUnitIds = array_map('intval', array_column($rows, 'idunit'));
        } elseif ($myRole === 40 && $myId) {
            $db = \Config\Database::connect();
            $mappings = $db->table('spv_units')->where('spv_id', $myId)->get()->getResultArray();
            $allowedUnitIds = !empty($mappings)
                ? array_map('intval', array_column($mappings, 'unit_id'))
                : [$myUnit];
        }

        // Pegawai yang boleh dilihat (data per pegawai); null = semua.
        $allowedIds = $isLintas ? null : [$myId];

        if (!$isLintas) {
            $targets = \App\Services\Kpi\EvaluatorAuthorizationService::allowedTargetJabatans($myRole);
            if (!empty($targets)) {
                $db = \Config\Database::connect();

                $scopeUnits = ($myRole === 40 && $myId) ? $allowedUnitIds : [$myUnit];

                $b = $db->table('akun')
                    ->select('ID_AKUN, ID_JABATAN, ID_UNIT')
                    ->where('STATUS_PEGAWAI', 1)
                    ->whereIn('ID_JABATAN', $targets);

                $hq = array_values(array_filter(
                    $targets,
                    fn($j) => \App\Services\Kpi\EvaluatorAuthorizationService::isHqTargetJabatan((int)$j)
                ));

                // CS (42) hanya boleh dilihat Admin/Kasir Unit 1.
                if ($myRole === 35 && $myUnit !== 1) {
                    $hq = array_values(array_filter($hq, fn($j) => (int)$j !== 42));
                }

                $b->groupStart()
                    ->whereIn('ID_UNIT', $scopeUnits)
                    ->orWhereIn('ID_JABATAN', $hq)
                    ->groupEnd();

                foreach ($b->get()->getResultArray() as $r) {
                    $allowedIds[] = (int)$r['ID_AKUN'];
                }
                $allowedIds = array_unique($allowedIds);
            }
        }

        return [$me, $myRole, $isLintas, $allowedUnitIds, $allowedIds];
    }

    /**
     * Filter parameter unit sesuai scope: lintas bebas, selain itu
     * wajib berasal dari allowedUnitIds (jika param kosong => null / semua yang diizinkan per-kategori).
     */
    private function allowedUnitParam(array $allowedUnitIds, $requestUnit): ?int
    {
        $req = (int)($requestUnit ?: 0);
        if ($req && in_array($req, $allowedUnitIds, true)) {
            return $req;
        }
        return count($allowedUnitIds) === 1 ? $allowedUnitIds[0] : null;
    }

    public function summary_kpi()
    {
        [$me, $myRole, $isLintas, $allowedUnitIds, $allowedIds] = $this->scopeInfo();

        // Get month filters from request, default to last 6 months
        $startMonth = $this->request->getGet('start_month');
        $endMonth = $this->request->getGet('end_month');
        $id_unit = $this->allowedUnitParam($allowedUnitIds, $this->request->getGet('id_unit'));

        if (!$startMonth) {
            $startMonth = date('Y-m', strtotime('-5 months'));
        }
        if (!$endMonth) {
            $endMonth = date('Y-m');
        }

        // Get data from view
        $rawData = $this->SummaryKPIModel->getSummaryKPI($startMonth, $endMonth, $id_unit);
        
        // Get months in range for columns
        $months = $this->SummaryKPIModel->getMonthsInRange($startMonth, $endMonth);
        
        // Pivot data: group by employee/KPI and create columns for each month
        $pivotedData = [];
        $monthLabels = [];

        // Create month labels for display
        foreach ($months as $month) {
            $monthLabels[$month] = date('M Y', strtotime($month . '-01'));
        }

// Process raw data and pivot it (dibatasi scope pegawai yang login)
        foreach ($rawData as $row) {
            // Assuming the view has columns like: pegawai_id, nama_pegawai, kpi, bulan (YYYY-MM), nilai/score
            // Adjust these field names based on your actual structure
            $key = $row->ID_AKUN ?? $row->NAMA_AKUN ?? null;
            $kpiName = 'KPI';
            $bulan = date('Y-m', strtotime($row->tanggal ??  date('Y-m')));
            $nilai = $row->score ?? 0;

            if (!$key) continue;

            // Non-lintas: hanya KPI pegawai dalam scope-nya.
            if (!$isLintas && !in_array((int)$row->ID_AKUN, (array)$allowedIds, true)) {
                continue;
            }

            $rowKey = $key . '|' . $kpiName;
            
            if (!isset($pivotedData[$rowKey])) {
                $pivotedData[$rowKey] = [
                    'pegawai_id' => $row->ID_AKUN ?? null,
                    'nama_unit' => $row->NAMA_UNIT ?? null,
                    'nama_pegawai' => $row->NAMA_AKUN ?? '',
                    'nama_jabatan' => $row->NAMA_JABATAN ?? '',
                    'kpi' => $kpiName,
                    'months' => []
                ];
            }

            $pivotedData[$rowKey]['months'][$bulan] = $nilai;
        }
        // die(json_encode($pivotedData));

        // Fill missing months with 0 or null
        foreach ($pivotedData as &$row) {
            foreach ($months as $month) {
                if (!isset($row['months'][$month])) {
                    $row['months'][$month] = null;
                }
            }
        }

        $data = [
            'title' => 'Summary KPI',
            'body' => 'SummaryKPI/summary_kpi',
            'akun' => $me,
            'unit' => $this->UnitModel->getUnit(),
            'pivotedData' => $pivotedData,
            'months' => $months,
            'monthLabels' => $monthLabels,
            'start_month' => $startMonth,
            'end_month' => $endMonth
        ];

        // die(json_encode($data['unit']));

        return view('template', $data);
    }

    public function summary_grading()
    {
        [$me, $myRole, $isLintas, $allowedUnitIds, $allowedIds] = $this->scopeInfo();

        // Get month filters from request, default to last 6 months
        $startMonth = $this->request->getGet('start_month');
        $endMonth = $this->request->getGet('end_month');
        $id_unit = $this->request->getGet('id_unit');

        if (!$startMonth) {
            $startMonth = date('Y-m', strtotime('-5 months'));
        }
        if (!$endMonth) {
            $endMonth = date('Y-m');
        }

        // Get data from view (non-lintas hanya unit yang diperbolehkan)
        if ($isLintas) {
            $rawData = $this->SummaryKPIModel->getSummaryGrading($startMonth, $endMonth, $id_unit);
        } else {
            $units = ($id_unit && in_array((int)$id_unit, $allowedUnitIds, true)) ? [(int)$id_unit] : $allowedUnitIds;
            $rawData = [];
            foreach ($units as $u) {
                $rawData = array_merge($rawData, $this->SummaryKPIModel->getSummaryGrading($startMonth, $endMonth, $u));
            }
        }

        // Get months in range for columns
        $months = $this->SummaryKPIModel->getMonthsInRange($startMonth, $endMonth);

        // Pivot data: group by employee/KPI and create columns for each month
        $pivotedData = [];
        $monthLabels = [];

        // Create month labels for display
        foreach ($months as $month) {
            $monthLabels[$month] = date('M Y', strtotime($month . '-01'));
        }

        // Process raw data and pivot it
        foreach ($rawData as $row) {
            // Assuming the view has columns like: pegawai_id, nama_pegawai, kpi, bulan (YYYY-MM), nilai/score
            // Adjust these field names based on your actual view structure
            $key = $row->ID_AKUN ?? $row->NAMA_AKUN ?? null;
            $kpiName = 'KPI';
            $bulan = date('Y-m', strtotime($row->tanggal ??  date('Y-m')));
            $nilai = $row->score ?? 0;

            if (!$key) continue;

            $rowKey = $key . '|' . $kpiName;

            if (!isset($pivotedData[$rowKey])) {
                $pivotedData[$rowKey] = [
                    'pegawai_id' => $row->ID_AKUN ?? null,
                    'nama_unit' => $row->NAMA_UNIT ?? null,
                    'nama_pegawai' => $row->NAMA_AKUN ?? '',
                    'nama_jabatan' => $row->NAMA_JABATAN ?? '',
                    'kpi' => $kpiName,
                    'months' => []
                ];
            }

            $pivotedData[$rowKey]['months'][$bulan] = $nilai;
        }
        // die(json_encode($pivotedData));

        // Fill missing months with 0 or null
        foreach ($pivotedData as &$row) {
            foreach ($months as $month) {
                if (!isset($row['months'][$month])) {
                    $row['months'][$month] = null;
                }
            }
        }

        $data = [
            'title' => 'Summary Grading',
            'body' => 'SummaryKPI/summary_grading',
            'akun' => $me,
            'unit' => $this->UnitModel->getUnit(),
            'pivotedData' => $pivotedData,
            'months' => $months,
            'monthLabels' => $monthLabels,
            'start_month' => $startMonth,
            'end_month' => $endMonth
        ];

        // die(json_encode($data['unit']));

        return view('template', $data);
    }

    public function summary_detail()
    {
        [$me, $myRole, $isLintas, $allowedUnitIds, $allowedIds] = $this->scopeInfo();

        $id_akun = (int)$this->request->getPost('id_akun');
        $month = $this->request->getPost('month');

        if (!$id_akun || (!$isLintas && !in_array($id_akun, (array)$allowedIds, true))) {
            return view('SummaryKPI/summary_detail', [
                'error' => 'Anda tidak berhak melihat detail KPI pegawai tersebut.',
            ]);
        }

        $detail_checklist = $this->SummaryKPIModel->getDetailChecklist($id_akun, $month);
        $detail_grading = $this->SummaryKPIModel->getDetailGrading($id_akun, $month);
        $detail_kpi = $this->SummaryKPIModel->getDetailKPI($id_akun, $month);
        $data = [
            'detail_checklist' => $detail_checklist,
            'detail_grading' => $detail_grading,
            'detail_kpi' => $detail_kpi,
        ];
        return view('SummaryKPI/summary_detail', $data);
    }
}
