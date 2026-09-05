<?php

namespace App\Controllers;

use App\Models\ModelPhone;
use App\Models\ModelPelanggan;
use App\Models\ModelPenilaianKPI;
use App\Models\ModelAuth;
use App\Models\ModelTemplatePenilaian;
use App\Models\ModelTemplateKpi;
use App\Models\ModelPenilaian;
use App\Models\ModelPenjualan;
use App\Models\ModelPresensi;
use App\Models\ModelDetailPenjualan;
use App\Models\ModelPenilaianDetail;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PenilaianKPI extends BaseController
{
    protected $AuthModel;
    protected $TemplatePenilaianModel;
    protected $PenilaianKPIModel;
    protected $TemplateKpiModel;
    protected $PenilaianModel;
    protected $PenjualanModel;
    protected $PresensiModel;
    protected $DetailPenjualanModel;
    protected $PenilaianDetailModel;
    protected $db;

    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->TemplatePenilaianModel = new ModelTemplatePenilaian();
        $this->PenilaianKPIModel = new ModelPenilaianKPI();
        $this->TemplateKpiModel = new ModelTemplateKpi();
        $this->PenilaianModel = new ModelPenilaian();
        $this->PenjualanModel = new ModelPenjualan();
        $this->PresensiModel = new ModelPresensi();
        $this->DetailPenjualanModel = new ModelDetailPenjualan();
        $this->PenilaianDetailModel = new ModelPenilaianDetail();
        $this->db = \Config\Database::connect();
    }

    public function index()
    {
        $pegawai_id = $this->request->getGet('pegawai_idpegawai');
        $templatekpi = [];
        $skorMap = [];
        $isUpdate = false;

        $levelList = [];
        $templateIdList = [];
        $unitId = null;

        $penilaianKPIList = [];
        $kpiExistingMap = [];
        $idpenilaianList = [];
        $prefillList = [];

        if ($pegawai_id) {
            $pegawai = $this->AuthModel->getById($pegawai_id);

            if ($pegawai && isset($pegawai->ID_JABATAN)) {
                $unitId = $pegawai->unit_idunit ?? null;

                $templatekpi = $this->TemplateKpiModel->getByJabatan($pegawai->ID_JABATAN);

                foreach ($templatekpi as $i => $tpl) {
                    $levelList[$i] = $tpl->level;
                    $templateIdList[$i] = $tpl->idtemplate_kpi;
                }

                $tanggalPenilaian = $this->request->getGet('tanggal_penilaian_kpi') ?? date('Y-m-d');
                $bulan = date('m', strtotime($tanggalPenilaian));
                $tahun = date('Y', strtotime($tanggalPenilaian));
                $startDate = date('Y-m-01', strtotime("$tahun-$bulan-01"));
                $endDate = date('Y-m-t', strtotime("$tahun-$bulan-01"));

                $penilaianKPIList = $this->PenilaianKPIModel
                    ->where('pegawai_idpegawai', $pegawai_id)
                    ->where('tanggal_penilaian_kpi >=', $startDate)
                    ->where('tanggal_penilaian_kpi <=', $endDate)
                    ->findAll();

                foreach ($penilaianKPIList as $p) {
                    $kpiExistingMap[$p->template_kpi_idtemplate_kpi] = $p;
                    $idpenilaianList[] = $p->idpenilaian_kpi;

                    $skorMap[$p->template_kpi_idtemplate_kpi]['realisasi'] = $p->realisasi ?? '';
                    $skorMap[$p->template_kpi_idtemplate_kpi]['score'] = $p->score ?? '';

                    $unitId = $p->unit_idunit ?? $unitId;
                }

                foreach ($templatekpi as $tpl) {
                    $templateId = $tpl->idtemplate_kpi;
                    $existing = $kpiExistingMap[$templateId] ?? null;

                    $prefillList[$templateId] = [
                        'idpenilaian_kpi' => $existing->idpenilaian_kpi ?? '',
                        'realisasi'       => $existing->realisasi ?? '',
                        'level'           => $existing->level ?? $tpl->level,
                        'score'           => $existing->score ?? '',
                    ];
                }

                $isUpdate = !empty($templatekpi);
                foreach ($templatekpi as $tpl) {
                    if (!isset($kpiExistingMap[$tpl->idtemplate_kpi])) {
                        $isUpdate = false;
                        break;
                    }
                }

                $bulanIni = date('Y-m-01');
                $bulanAkhir = date('Y-m-t');

                $omsetResult = $this->PenjualanModel
                    ->selectSum('total_penjualan')
                    ->where('sales_by', $pegawai_id)
                    ->where('tanggal >=', $bulanIni)
                    ->where('tanggal <=', $bulanAkhir)
                    ->first();
                $skorMap['Penjualan(Omzet)']['realisasi'] = $omsetResult->total_penjualan ?? 0;

                $onTimeResult = $this->PresensiModel
                    ->countOnTimeAbsensiPerBulan($pegawai_id, $startDate, $endDate);
                $skorMap['Kedisiplinan']['realisasi'] = $onTimeResult->total_ontime ?? 0;

                $absensiResult = $this->PresensiModel
                    ->countAbsensiPerBulan($pegawai_id, $startDate, $endDate);
                $skorMap['Kehadiran']['realisasi'] = $absensiResult->total_absensi ?? 0;

                $groomingResult = $this->PresensiModel
                    ->countGrooming($pegawai_id, $startDate, $endDate);
                $skorMap['Grooming']['realisasi'] = $groomingResult->total_grooming ?? 0;

                $categoryResult = $this->DetailPenjualanModel
                    ->countByCategory(2, $startDate, $endDate);
                $skorMap['Up-selling dan Cross-selling']['realisasi'] = $categoryResult->total_category ?? 0;

                $jumlahByTemplate = $this->PenilaianKPIModel->getJumlahByTemplateKPI($pegawai_id);
                foreach ($jumlahByTemplate as $row) {
                    $skorMap[$row->template_kpi]['realisasi'] = $row->jumlah;
                }
            }
        }

        $data = [
            'penilaiankpi' => $penilaianKPIList,
            'akun' => $this->AuthModel->getdataakun(),
            'pegawai_idpegawai' => $pegawai_id,
            'unit_idunit' => $unitId,
            'templatekpi' => $templatekpi,
            'skorMap' => $skorMap,
            'isUpdate' => $isUpdate,
            'levelList' => $levelList,
            'templateIdList' => $templateIdList,
            'prefillList' => $prefillList,
            'idpenilaianList' => $idpenilaianList,
            'kpiExistingMap' => $kpiExistingMap,
            'body' => 'penilaian/penilaian_kpi',
        ];

        return view('template', $data);
    }

    /**
     * Halaman Penilaian KPI (modern, config-driven).
     *
     * Menentukan scope pegawai berdasarkan role yang login, lalu
     * menampilkan daftar pegawai yang berada dalam scope tsb.
     */
    public function kpi_index()
    {
        $me      = $this->AuthModel->getById((int)session()->get('ID_AKUN'));
        $myRole  = (int)($me->ID_JABATAN ?? 0);
        $myUnit  = (int)($me->ID_UNIT ?? 0);
        $myId    = (int)($me->ID_AKUN ?? 0);

        $bulan = (int)($this->request->getGet('bulan') ?: date('m'));
        $tahun = (int)($this->request->getGet('tahun') ?: date('Y'));

        // Scope unit (role yang bisa lintas unit => null = semua unit)
        $scopeUnits = $this->getScopeUnits($myRole, $myUnit, $myId);
        $allowedUnits = $this->getAllowedUnits($myRole, $myUnit);

        // Daftar pegawai dalam scope
        $builder = $this->db->table('akun a')
            ->select('a.ID_AKUN, a.ID_JABATAN, a.ID_UNIT, a.NAMA_AKUN, j.NAMA_JABATAN, u.NAMA_UNIT')
            ->join('jabatan j', 'j.ID_JABATAN = a.ID_JABATAN', 'left')
            ->join('unit u', 'u.idunit = a.ID_UNIT', 'left')
            ->where('a.STATUS_PEGAWAI', 1)
            ->groupStart()
            ->where('a.deleted', null)
            ->orWhere('a.deleted', 0)
            ->groupEnd();

        // Filter target berdasarkan matriks evaluator, plus izinkan target HQ lintas unit.
        $allowedTargets = \App\Services\Kpi\EvaluatorAuthorizationService::allowedTargetJabatans($myRole);

        if (in_array($myRole, [1, 2], true)) {
            // Admin root / Direktur: semua pegawai, semua unit.
        } elseif (!empty($allowedTargets)) {
            $builder->whereIn('a.ID_JABATAN', $allowedTargets);

            if ($scopeUnits !== null) {
                $hqTargets = array_values(array_filter(
                    $allowedTargets,
                    fn($j) => \App\Services\Kpi\EvaluatorAuthorizationService::isHqTargetJabatan((int)$j)
                ));

                if (!empty($hqTargets)) {
                    $builder->groupStart()
                        ->whereIn('a.ID_UNIT', $scopeUnits)
                        ->orWhereIn('a.ID_JABATAN', $hqTargets)
                        ->groupEnd();
                } else {
                    $builder->whereIn('a.ID_UNIT', $scopeUnits);
                }
            }
        } else {
            // Pegawai/team tanpa target evaluasi: hanya dirinya sendiri.
            $builder->where('a.ID_AKUN', (int)session()->get('ID_AKUN'));
        }

        $list_karyawan = $builder->orderBy('a.ID_UNIT', 'ASC')->orderBy('a.ID_JABATAN', 'ASC')->get()->getResultArray();

        $units = $this->db->table('unit')->orderBy('idunit', 'ASC')->get()->getResultArray();

        return view('template', [
            'list_karyawan' => $list_karyawan,
            'units'         => $units,
            'bulan'         => $bulan,
            'tahun'         => $tahun,
            'myRole'        => $myRole,
            'myUnit'        => $myUnit,
            'body'          => 'penilaian/kpi_index',
        ]);
    }

    /**
     * Detail KPI pegawai (server-side authorization).
     * Memastikan pegawai berada dalam scope evaluator yang login.
     */
    public function kpi_detail(int $id)
    {
        $me     = $this->AuthModel->getById((int)session()->get('ID_AKUN'));
        $myRole = (int)($me->ID_JABATAN ?? 0);
        $myUnit = (int)($me->ID_UNIT ?? 0);

        $target = $this->AuthModel->getById((int)$id);
        if (!$target || (int)$target->STATUS_PEGAWAI !== 1) {
            return redirect()->to('/penilaian/kpi')->with('error', 'Pegawai tidak ditemukan.');
        }

        // Authorization: apakah pegawai dalam scope evaluator
        if (!$this->isInScope($me, $target)) {
            return redirect()->to('/penilaian/kpi')->with('error', 'Anda tidak berhak mengakses KPI pegawai tersebut.');
        }

        $bulan = (int)($this->request->getGet('bulan') ?: date('m'));
        $tahun = (int)($this->request->getGet('tahun') ?: date('Y'));

        $kpiService = new \App\Services\Kpi\KpiCalculationService();
        $kpi = $kpiService->calculateForSalary((int)$target->ID_AKUN, (string)$bulan, (string)$tahun, 'penilaian_kinerja');

        $jabatanRow = $this->db->table('jabatan')
            ->where('ID_JABATAN', (int)$target->ID_JABATAN)->get()->getRow();
        $namaJabatan = $jabatanRow ? $jabatanRow->NAMA_JABATAN : 'Pegawai';

        // Kualitas Pelayanan (manual 1-5) — prefill raw score existing
        $kualitasRaw = $this->getKualitasRaw((int)$target->ID_AKUN, $bulan, $tahun);

        // Tandai komponen manual vs otomatis berdasarkan kpi_components.
        // KONTROL_ASET kini dihitung OTOMATIS dari data aset (bukan input manual).
        $manualCodes = (new \App\Models\ModelKpiComponent())
            ->where('type', 'manual')->findAll();
        $manualNameSet = [];
        foreach ($manualCodes as $c) {
            if ($c->code === 'KONTROL_ASET') {
                continue;
            }
            $manualNameSet[$c->name] = true;
        }

        $canEvaluate = \App\Services\Kpi\EvaluatorAuthorizationService::canEvaluateComponent(
            (int)$me->ID_AKUN,
            (int)$target->ID_AKUN,
            'KUALITAS_PELAYANAN'
        );

        return view('template', [
            'target'        => $target,
            'namaJabatan'   => $namaJabatan,
            'jabatan'       => $kpi['jabatan'],
            'kpi'           => $kpi,
            'manualNameSet' => $manualNameSet,
            'kualitasRaw'   => $kualitasRaw,
            'canEvaluate'   => $canEvaluate,
            'bulan'         => $bulan,
            'tahun'         => $tahun,
            'body'          => 'penilaian/kpi_detail',
        ]);
    }

    /**
     * Simpan skor Kualitas Pelayanan (1-5) via KpiEvaluationService.
     * Server-side authorization: hanya evaluator berwenang (SPV -> KT, unit sama).
     */
    public function save_kualitas()
    {
        $evaluatorId = (int)session()->get('ID_AKUN');
        $employeeId  = (int)$this->request->getPost('employee_id');
        $score       = (int)$this->request->getPost('skor_kualitas');
        $bulan       = (int)($this->request->getPost('bulan') ?: date('m'));
        $tahun       = (int)($this->request->getPost('tahun') ?: date('Y'));

        if ($score < 1 || $score > 5) {
            return redirect()->to('/penilaian/kpi/detail/' . $employeeId . '?bulan=' . $bulan . '&tahun=' . $tahun)
                ->with('error', 'Skor Kualitas Pelayanan harus antara 1 s/d 5.');
        }

        // Validasi evaluator + target + KOMPONEN (Kualitas Pelayanan) secara server-side
        if (!\App\Services\Kpi\EvaluatorAuthorizationService::canEvaluateComponent(
            $evaluatorId,
            $employeeId,
            'KUALITAS_PELAYANAN'
        )) {
            return redirect()->to('/penilaian/kpi/detail/' . $employeeId)
                ->with('error', 'Anda tidak berwenang menilai KPI pegawai ini.');
        }

        $component = (new \App\Models\ModelKpiComponent())->where('code', 'KUALITAS_PELAYANAN')->first();
        if (!$component) {
            return redirect()->to('/penilaian/kpi/detail/' . $employeeId)
                ->with('error', 'Komponen KPI Kualitas Pelayanan tidak ditemukan.');
        }

        $svc = new \App\Services\Kpi\KpiEvaluationService();
        $result = $svc->recordEvaluation([
            'employee_id'      => $employeeId,
            'kpi_component_id' => (int)$component->id,
            'evaluator_id'     => $evaluatorId,
            'evaluation_date'  => sprintf('%04d-%02d-15', $tahun, $bulan),
            'raw_score'        => $score,
            'max_score'        => 5,
            'notes'            => 'Kualitas Pelayanan (Skor: ' . $score . '/5)',
        ]);

        if (!$result['success']) {
            return redirect()->to('/penilaian/kpi/detail/' . $employeeId . '?bulan=' . $bulan . '&tahun=' . $tahun)
                ->with('error', 'Gagal menyimpan: ' . implode(', ', $result['errors']));
        }

        return redirect()->to('/penilaian/kpi/detail/' . $employeeId . '?bulan=' . $bulan . '&tahun=' . $tahun)
            ->with('success', 'Skor Kualitas Pelayanan berhasil disimpan.');
    }

    /**
     * Scope unit: null = semua unit (lintas unit). Array = hanya unit tsb.
     */
    /**
     * Scope unit: null = semua unit (lintas unit). Array = hanya unit tsb.
     * Untuk SPV: baca dari spv_units mapping, fallback ke ID_UNIT jika tidak ada mapping.
     */
    private function getScopeUnits(int $myRole, int $myUnit, int $myId = null): ?array
    {
        // Role dengan akses lintas unit
        if (in_array($myRole, [0, 1, 2, 34], true)) {
            return null;
        }
        
        // SPV: baca dari spv_units mapping
        if ($myRole === 40 && $myId) {
            $mappings = $this->db->table('spv_units')
                ->where('spv_id', $myId)
                ->get()
                ->getResultArray();
            
            if (!empty($mappings)) {
                return array_column($mappings, 'unit_id');
            }
            
            // Fallback ke ID_UNIT untuk backward compatibility
            return [$myUnit];
        }
        
        return [$myUnit];
    }

    /**
     * Daftar unit yang boleh dilihat untuk filter UI.
     */
    private function getAllowedUnits(int $myRole, int $myUnit): array
    {
        if (in_array($myRole, [0, 1, 2, 34], true)) {
            $rows = $this->db->table('unit')->orderBy('idunit', 'ASC')->get()->getResultArray();
            return array_column($rows, 'idunit');
        }
        return [$myUnit];
    }

    /**
     * Cek apakah $employee berada dalam scope $evaluator.
     */
    private function isInScope($evaluator, $employee): bool
    {
        $myRole = (int)($evaluator->ID_JABATAN ?? 0);
        $myUnit = (int)($evaluator->ID_UNIT ?? 0);
        $myId   = (int)($evaluator->ID_AKUN ?? 0);

        // Admin root & Direktur: akses penuh
        if (in_array($myRole, [1, 2], true)) {
            return true;
        }

        $allowedTargets = \App\Services\Kpi\EvaluatorAuthorizationService::allowedTargetJabatans($myRole);

        if (!empty($allowedTargets) && in_array((int)$employee->ID_JABATAN, $allowedTargets, true)) {
            // Jabatan pusat (HQ) boleh dinilai lintas unit; selain itu wajib dalam scope unit.
            if (!\App\Services\Kpi\EvaluatorAuthorizationService::isHqTargetJabatan((int)$employee->ID_JABATAN)) {
                $scopeUnits = $this->getScopeUnits($myRole, $myUnit, $myId);
                $targetUnit = (int)($employee->ID_UNIT ?? 0);
                if ($scopeUnits !== null && !in_array($targetUnit, array_map('intval', $scopeUnits), true)) {
                    return false;
                }
            }
            return true;
        }

        // Role lain (pegawai/team) hanya melihat dirinya sendiri
        return (int)$employee->ID_AKUN === $myId;
    }

    /**
     * Ambil raw score Kualitas Pelayanan (skala 1-5) untuk periode.
     */
    private function getKualitasRaw(int $employeeId, int $bulan, int $tahun): ?int
    {
        $component = (new \App\Models\ModelKpiComponent())->where('code', 'KUALITAS_PELAYANAN')->first();
        if (!$component) {
            return null;
        }
        // Setiap evaluator melihat nilai yang dia isi sendiri (bukan rata-rata).
        $row = (new \App\Models\ModelKpiEvaluation())
            ->where('employee_id', $employeeId)
            ->where('kpi_component_id', (int)$component->id)
            ->where('evaluator_id', (int)session()->get('ID_AKUN'))
            ->where('period_year', $tahun)
            ->where('period_month', $bulan)
            ->orderBy('evaluation_date', 'DESC')
            ->first();
        return $row ? (int)$row->raw_score : null;
    }

    public function insert_penilaian()
    {
        $kpiList        = $this->request->getPost('kpi_utama');
        $bobotList      = $this->request->getPost('bobot');
        $targetList     = $this->request->getPost('target');
        $realisasiList  = $this->request->getPost('realisasi');
        $scoreList      = $this->request->getPost('score');
        $levelList      = $this->request->getPost('level');
        $unitIdList     = $this->request->getPost('unit_idunit');
        $templateIdList = $this->request->getPost('template_kpi_idtemplate_kpi');

        $pegawai_id = $this->request->getPost('pegawai_idpegawai');
        $tanggal    = $this->request->getPost('tanggal_penilaian_kpi');

        if ($kpiList && is_array($kpiList)) {
            foreach ($kpiList as $i => $kpi) {
                $this->PenilaianKPIModel->insert([
                    'kpi_utama'                    => $kpi,
                    'bobot'                        => $bobotList[$i] ?? null,
                    'target'                       => $targetList[$i] ?? null,
                    'realisasi'                    => $realisasiList[$i] ?? 0,
                    'score'                        => $scoreList[$i] ?? 0,
                    // FIX: sebelumnya key 'level' ditulis dua kali di array ini,
                    // sehingga nilai dari form selalu ketimpa jadi string '1'.
                    // Sekarang nilai dari form (levelList) yang dipakai, dengan
                    // fallback ke '1' hanya kalau memang kosong.
                    'level'                        => $levelList[$i] ?? '1',
                    'unit_idunit'                  => $unitIdList[$i] ?? null,
                    'template_kpi_idtemplate_kpi'  => $templateIdList[$i] ?? null,
                    'pegawai_idpegawai'            => $pegawai_id,
                    'tanggal_penilaian_kpi'        => $tanggal,
                    'created_on'                   => date('Y-m-d H:i:s'),
                ]);
            }
        }

        session()->setFlashdata('sukses', 'Data Berhasil Ditambahkan');
        return redirect()->to(base_url('penilaian_kpi'));
    }

    public function update_penilaian()
    {
        $ids           = $this->request->getPost('idpenilaian_kpi');
        $kpiList       = $this->request->getPost('kpi_utama');
        $bobotList     = $this->request->getPost('bobot');
        $targetList    = $this->request->getPost('target');
        $realisasiList = $this->request->getPost('realisasi');
        $scoreList     = $this->request->getPost('score');
        $levelList     = $this->request->getPost('level');
        $pegawai_id    = $this->request->getPost('pegawai_idpegawai');
        $tanggal       = $this->request->getPost('tanggal_penilaian_kpi');

        if (!$pegawai_id || !$tanggal || !is_array($kpiList)) {
            session()->setFlashdata('error', 'Data tidak lengkap untuk update.');
            return redirect()->to(base_url('penilaian_kpi'));
        }

        $bulan = date('m', strtotime($tanggal));
        $tahun = date('Y', strtotime($tanggal));
        $startDate = date('Y-m-01', strtotime("$tahun-$bulan-01"));
        $endDate   = date('Y-m-t', strtotime("$tahun-$bulan-01"));

        $this->PenilaianKPIModel
            ->where('pegawai_idpegawai', $pegawai_id)
            ->where('tanggal_penilaian_kpi >=', $startDate)
            ->where('tanggal_penilaian_kpi <=', $endDate)
            ->delete();

        $batchData = [];
        foreach ($kpiList as $i => $kpi) {
            $batchData[] = [
                'kpi_utama'             => $kpi,
                'bobot'                 => $bobotList[$i] ?? null,
                'target'                => $targetList[$i] ?? null,
                'realisasi'             => $realisasiList[$i] ?? null,
                'score'                 => $scoreList[$i] ?? null,
                // FIX: sama seperti insert_penilaian(), pakai nilai dari form.
                'level'                 => $levelList[$i] ?? '1',
                'pegawai_idpegawai'     => $pegawai_id,
                'tanggal_penilaian_kpi' => $tanggal,
                'created_on'            => date('Y-m-d H:i:s'),
            ];
        }

        try {
            $this->PenilaianKPIModel->insertBatch($batchData);
            session()->setFlashdata('sukses', 'Data Berhasil Diperbarui.');
        } catch (\Throwable $e) {
            session()->setFlashdata('error', 'Terjadi Kesalahan Saat Menyimpan Data: ' . $e->getMessage());
        }

        return redirect()->to(base_url('penilaian_kpi'));
    }

    public function delete_penilaian()
    {
        $id = $this->request->getPost('idpenilaian_kpi');
        $this->PenilaianKPIModel->delete($id);
        session()->setFlashdata('sukses', 'Data Berhasil Dihapus');
        return redirect()->to(base_url('penilaian'));
    }

    public function index_riwayat()
    {
        $riwayat = $this->PenilaianKPIModel
            ->select('penilaian_kpi.*, penilaian_kpi.penilaian_idpenilaian, akun.NAMA_AKUN as pegawai_nama, jabatan.NAMA_JABATAN as jabatan_nama, unit.NAMA_UNIT as unit_nama')
            ->join('akun', 'akun.ID_AKUN = penilaian_kpi.pegawai_idpegawai', 'left')
            ->join('jabatan', 'jabatan.ID_JABATAN = akun.ID_JABATAN', 'left')
            ->join('unit', 'unit.idunit = akun.ID_UNIT', 'left')
            ->orderBy('penilaian_kpi.created_on', 'ASC')
            ->findAll();

        foreach ($riwayat as $row) {
            $detail = $this->PenilaianKPIModel
                ->select('kpi_utama, bobot, target, realisasi, score')
                ->where('idpenilaian_kpi', $row->idpenilaian_kpi)
                ->orderBy('kpi_utama', 'ASC')
                ->findAll();

            $totalScore = 0;
            if (!empty($detail)) {
                foreach ($detail as $d) {
                    $totalScore += (float) $d->score;
                }
            }

            $aspek_detail = $this->PenilaianDetailModel
                ->select('template_penilaian.aspek_penilaian, penilaian_detail.skor')
                ->join('template_penilaian', 'template_penilaian.idtemplate_penilaian = penilaian_detail.template_penilaian_idtemplate_penilaian', 'left')
                ->where('penilaian_detail.penilaian_idpenilaian', $row->penilaian_idpenilaian)
                ->findAll();

            $row->detail = $detail ?: [];
            $row->total_score = $totalScore;
            $row->aspek_detail = $aspek_detail ?: [];
        }

        $grouped_riwayat = [];
        foreach ($riwayat as $row) {
            $key = $row->pegawai_nama . '|' . $row->created_on . '|' . $row->jabatan_nama . '|' . $row->unit_nama;
            $grouped_riwayat[$key][] = $row;
        }

        $data = [
            'title' => 'Riwayat Penilaian KPI',
            'riwayat' => $riwayat,
            'grouped_riwayat' => $grouped_riwayat,
            'body' => 'penilaian/riwayat_penilaian_KPI',
        ];

        return view('template', $data);
    }

    public function export_penilaian_detail()
    {
        $tanggal_awal = $this->request->getPost('tanggal_awal');
        $tanggal_akhir = $this->request->getPost('tanggal_akhir');

        $query = $this->PenilaianKPIModel
            ->select('penilaian_kpi.idpenilaian_kpi,
                  penilaian_kpi.tanggal_penilaian_kpi,
                  penilaian_kpi.kpi_utama,
                  penilaian_kpi.bobot,
                  penilaian_kpi.target,
                  penilaian_kpi.realisasi,
                  penilaian_kpi.score,
                  penilaian_kpi.penilaian_idpenilaian,
                  akun.NAMA_AKUN as pegawai_nama,
                  jabatan.NAMA_JABATAN as jabatan_nama,
                  unit.NAMA_UNIT as unit_nama')
            ->join('akun', 'akun.ID_AKUN = penilaian_kpi.pegawai_idpegawai', 'left')
            ->join('jabatan', 'jabatan.ID_JABATAN = akun.ID_JABATAN', 'left')
            ->join('unit', 'unit.idunit = akun.ID_UNIT', 'left')
            ->orderBy('penilaian_kpi.tanggal_penilaian_kpi', 'DESC');

        if (!empty($tanggal_awal)) {
            $query->where('penilaian_kpi.tanggal_penilaian_kpi >=', $tanggal_awal);
        }
        if (!empty($tanggal_akhir)) {
            $query->where('penilaian_kpi.tanggal_penilaian_kpi <=', $tanggal_akhir);
        }

        $rows = $query->findAll();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if (empty($rows)) {
            $sheet->setCellValue('A1', 'Tidak ada data untuk rentang tanggal yang dipilih.');
            $filename = 'Penilaian_KPI_' . date('Ymd_His') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"$filename\"");
            header('Cache-Control: max-age=0');
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        }

        $grouped = [];
        foreach ($rows as $r) {
            $pegawai = $r->pegawai_nama ?? '-';
            $tanggal = $r->tanggal_penilaian_kpi ?? '-';
            $jabatan = $r->jabatan_nama ?? '-';
            $unit = $r->unit_nama ?? '-';

            $key = $pegawai . '|' . $tanggal . '|' . $jabatan . '|' . $unit;
            $grouped[$key][] = $r;
        }

        $boldStyle = ['font' => ['bold' => true]];
        $headerFill = [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'DCE6F1']
        ];

        $pegawaiFill = [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'BDD7EE']
        ];

        $rowNum = 1;

        foreach ($grouped as $key => $items) {
            list($pegawai, $tanggal, $jabatan, $unit) = explode('|', $key);

            $sheet->setCellValue('A' . $rowNum, 'Pegawai');
            $sheet->setCellValue('B' . $rowNum, $pegawai);
            $sheet->getStyle("A{$rowNum}:B{$rowNum}")->applyFromArray($boldStyle);
            $sheet->getStyle("A{$rowNum}:B{$rowNum}")->getFill()->applyFromArray($pegawaiFill);
            $rowNum++;

            $sheet->setCellValue('A' . $rowNum, 'Jabatan');
            $sheet->setCellValue('B' . $rowNum, $jabatan);
            $sheet->getStyle('A' . $rowNum)->applyFromArray($boldStyle);
            $sheet->getStyle("A{$rowNum}:B{$rowNum}")->getFill()->applyFromArray($pegawaiFill);
            $rowNum++;

            $sheet->setCellValue('A' . $rowNum, 'Unit');
            $sheet->setCellValue('B' . $rowNum, $unit);
            $sheet->getStyle('A' . $rowNum)->applyFromArray($boldStyle);
            $sheet->getStyle("A{$rowNum}:B{$rowNum}")->getFill()->applyFromArray($pegawaiFill);
            $rowNum++;

            $sheet->setCellValue('A' . $rowNum, 'Tanggal Penilaian');
            $sheet->setCellValue('B' . $rowNum, ($tanggal && $tanggal !== '-') ? date('d-m-Y', strtotime($tanggal)) : '-');
            $sheet->getStyle('A' . $rowNum)->applyFromArray($boldStyle);
            $sheet->getStyle("A{$rowNum}:B{$rowNum}")->getFill()->applyFromArray($pegawaiFill);
            $rowNum++;

            $rowNum++;

            $sheet->setCellValue('A' . $rowNum, 'KPI Utama');
            $sheet->setCellValue('B' . $rowNum, 'Bobot');
            $sheet->setCellValue('C' . $rowNum, 'Target');
            $sheet->setCellValue('D' . $rowNum, 'Realisasi');
            $sheet->setCellValue('E' . $rowNum, 'Score');
            $sheet->getStyle("A{$rowNum}:E{$rowNum}")->applyFromArray($boldStyle);
            $sheet->getStyle("A{$rowNum}:E{$rowNum}")->getFill()->applyFromArray($headerFill);
            $rowNum++;

            $totalScore = 0.0;

            foreach ($items as $item) {
                $sheet->setCellValue('A' . $rowNum, $item->kpi_utama ?? '-');
                $sheet->setCellValue('B' . $rowNum, $item->bobot ?? '-');
                $sheet->setCellValue('C' . $rowNum, $item->target ?? '-');
                $sheet->setCellValue('D' . $rowNum, $item->realisasi ?? '-');
                $sheet->setCellValue('E' . $rowNum, $item->score ?? 0);
                $totalScore += (float) ($item->score ?? 0);
                $rowNum++;

                if ($item->kpi_utama === 'Checklist Pekerjaan' && !empty($item->penilaian_idpenilaian)) {
                    $aspekList = $this->PenilaianDetailModel
                        ->select('template_penilaian.aspek_penilaian, penilaian_detail.skor')
                        ->join('template_penilaian', 'template_penilaian.idtemplate_penilaian = penilaian_detail.template_penilaian_idtemplate_penilaian', 'left')
                        ->where('penilaian_detail.penilaian_idpenilaian', $item->penilaian_idpenilaian)
                        ->findAll();

                    if (!empty($aspekList)) {
                        $sheet->setCellValue('B' . $rowNum, 'Aspek Penilaian');
                        $sheet->setCellValue('C' . $rowNum, 'Skor');
                        $sheet->getStyle("B{$rowNum}:C{$rowNum}")->applyFromArray($boldStyle);
                        $rowNum++;

                        foreach ($aspekList as $aspek) {
                            $sheet->setCellValue('B' . $rowNum, $aspek->aspek_penilaian);
                            $sheet->setCellValue('C' . $rowNum, $aspek->skor);
                            $rowNum++;
                        }
                    }
                }
            }

            $sheet->setCellValue('D' . $rowNum, 'Total Score');
            $sheet->setCellValue('E' . $rowNum, $totalScore);
            $sheet->getStyle("D{$rowNum}:E{$rowNum}")->applyFromArray($boldStyle);
            $rowNum += 2;
        }

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $lastRow = $rowNum - 1;
        $sheet->getStyle("A1:E{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $filename = 'Riwayat_Penilaian_KPI_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function export_penilaian()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $tanggal_awal = $this->request->getPost('tanggal_awal');
        $tanggal_akhir = $this->request->getPost('tanggal_akhir');

        $data = $this->PenilaianKPIModel
            ->where('tanggal_penilaian_kpi >=', $tanggal_awal)
            ->where('tanggal_penilaian_kpi <=', $tanggal_akhir)
            ->findAll();

        $headers = [
            'A1' => 'KPI Utama',
            'B1' => 'Bobot',
            'C1' => 'Target',
            'D1' => 'Realisasi',
            'E1' => 'Score',
            'F1' => 'Tanggal Penilaian',
            'G1' => 'Dibuat Pada',
            'H1' => 'Diupdate Pada',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');

        $row = 2;
        foreach ($data as $item) {
            $sheet->setCellValue('A' . $row, $item->kpi_utama);
            $sheet->setCellValue('B' . $row, $item->bobot);
            $sheet->setCellValue('C' . $row, $item->target);
            $sheet->setCellValue('D' . $row, $item->realisasi);
            $sheet->setCellValue('E' . $row, $item->score);
            $sheet->setCellValue('F' . $row, date('d-m-Y', strtotime($item->tanggal_penilaian_kpi)));
            $sheet->setCellValue('G' . $row, $item->created_on ? date('d-m-Y H:i:s', strtotime($item->created_on)) : '-');
            $sheet->setCellValue('H' . $row, $item->updated_on ? date('d-m-Y H:i:s', strtotime($item->updated_on)) : '-');
            $row++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->getStyle('A1:H' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->freezePane('A2');

        $filename = 'Penilaian_KPI_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }



    public function penilaian_kinerja()
    {
        $bulan = $this->request->getGet('bulan') ?? date('m');
        $tahun = $this->request->getGet('tahun') ?? date('Y');

        $idJabatan = session()->get('ID_JABATAN');
        $idUnit = session()->get('ID_UNIT');

        if ($idJabatan == 2) {
            $list_karyawan = $this->db->table('akun')
                ->where('STATUS_PEGAWAI', 1)
                ->where('ID_JABATAN !=', 1)
                ->where('ID_JABATAN !=', 2)
                ->where('ID_UNIT=', $idUnit)
                ->get()
                ->getResultArray();
        } else {
            $list_karyawan = $this->db->table('akun')
                ->where('STATUS_PEGAWAI', 1)
                ->where('ID_JABATAN !=', 1)
                ->where('ID_JABATAN !=', 2)
                ->get()
                ->getResultArray();
        }

        $selected_karyawan = $this->request->getGet('karyawan');
        if (!$selected_karyawan && !empty($list_karyawan)) {
            $selected_karyawan = $list_karyawan[0]['ID_AKUN'];
        }

        // =========================================================
        // KPI SCORES (context penilaian_kinerja) — KpiCalculationService
        // =========================================================
        $kpiService = new \App\Services\Kpi\KpiCalculationService();
        $kpi = $kpiService->calculateForSalary((int) $selected_karyawan, (string) $bulan, (string) $tahun, 'penilaian_kinerja');

        // =========================================================
        // SALARY COMPOSITION — SalaryCalculationService (context penilaian_kinerja)
        // =========================================================
        $periodDate = "$tahun-$bulan-15";
        $salaryService = new \App\Services\Payroll\SalaryCalculationService();
        $result = $salaryService->calculateSalary(
            (int) $selected_karyawan,
            $kpi['jabatan'],
            $kpi['unit'],
            'penilaian_kinerja',
            [
                'TUNJANGAN_KINERJA' => $kpi['skor_total'],
                'TUNJANGAN_ABSEN'   => $kpi['skor_total2'],
            ],
            $kpi['akun']->tunjangan_penempatan,
            $kpi['insentif'],
            0.0,
            0.0,
            $periodDate
        );

        $compByCode = [];
        foreach (($result['components'] ?? []) as $comp) {
            $compByCode[$comp['component_code']] = (float) $comp['amount'];
        }

        return view('template', [
            'list_karyawan'        => $list_karyawan,
            'selected_karyawan'    => $selected_karyawan,
            'karyawan'             => $kpi['karyawan'],
            'detail_kpi'           => $kpi['detail_kpi'],
            'detail_absen'         => $kpi['detail_absen'],
            'aktual_omset_unit'    => $kpi['aktual_omset_unit'],
            'skor_total'           => $kpi['skor_total'],
            'tunjangan_kinerja'    => $compByCode['TUNJANGAN_KINERJA'] ?? 0,
            'tunjangan_absen'      => $compByCode['TUNJANGAN_ABSEN'] ?? 0,
            'insentif'             => $result['incentive'],
            'tunjangan_penempatan' => $kpi['akun'],
            'gaji_pokok'           => $compByCode['GAJI_POKOK'] ?? $kpi['gaji_pokok'],
            'gaji'                 => $result['total_gaji'],
            'bulan'                => $bulan,
            'tahun'                => $tahun,
            'body'                 => 'penilaian/penilaian_kinerja'
        ]);
    }

    public function penilaian_absen()
    {
        $bulan = (int)($this->request->getGet('bulan') ?: date('m'));
        $tahun = (int)($this->request->getGet('tahun') ?: date('Y'));

        $me      = $this->AuthModel->getById((int)session()->get('ID_AKUN'));
        $myRole  = (int)($me->ID_JABATAN ?? 0);
        $myUnit  = (int)($me->ID_UNIT ?? 0);
        $myId    = (int)($me->ID_AKUN ?? 0);

        $scopeUnits = $this->getScopeUnits($myRole, $myUnit, $myId);

        $builder = $this->db->table('akun a')
            ->select('a.ID_AKUN, a.ID_JABATAN, a.ID_UNIT, a.NAMA_AKUN, j.NAMA_JABATAN, u.NAMA_UNIT')
            ->join('jabatan j', 'j.ID_JABATAN = a.ID_JABATAN', 'left')
            ->join('unit u', 'u.idunit = a.ID_UNIT', 'left')
            ->where('a.STATUS_PEGAWAI', 1)
            ->groupStart()
            ->where('a.deleted', null)
            ->orWhere('a.deleted', 0)
            ->groupEnd();

        // Filter target berdasarkan matriks evaluator, plus izinkan target HQ lintas unit.
        $allowedTargets = \App\Services\Kpi\EvaluatorAuthorizationService::allowedTargetJabatans($myRole);

        if (in_array($myRole, [1, 2], true)) {
            // Admin root / Direktur: semua pegawai, semua unit.
        } elseif (!empty($allowedTargets)) {
            $builder->whereIn('a.ID_JABATAN', $allowedTargets);

            if ($scopeUnits !== null) {
                $hqTargets = array_values(array_filter(
                    $allowedTargets,
                    fn($j) => \App\Services\Kpi\EvaluatorAuthorizationService::isHqTargetJabatan((int)$j)
                ));

                if (!empty($hqTargets)) {
                    $builder->groupStart()
                        ->whereIn('a.ID_UNIT', $scopeUnits)
                        ->orWhereIn('a.ID_JABATAN', $hqTargets)
                        ->groupEnd();
                } else {
                    $builder->whereIn('a.ID_UNIT', $scopeUnits);
                }
            }
        } else {
            // Pegawai/team tanpa target evaluasi: hanya dirinya sendiri.
            $builder->where('a.ID_AKUN', (int)session()->get('ID_AKUN'));
        }

        $list_karyawan = $builder->orderBy('a.ID_UNIT', 'ASC')->get()->getResultArray();

        $selected_karyawan = (int)($this->request->getGet('karyawan') ?: 0);
        if (!$selected_karyawan && !empty($list_karyawan)) {
            $selected_karyawan = (int)$list_karyawan[0]['ID_AKUN'];
        }

        $target = null;
        $targetUnitName = null;
        if ($selected_karyawan) {
            $target = $this->AuthModel->getById($selected_karyawan);
            // Otorisasi: pegawai harus dalam scope evaluator
            if ($target && !$this->isInScope($me, $target)) {
                $target = null;
            }
            
            // Ambil nama unit untuk ditampilkan
            if ($target) {
                $unitRow = $this->db->table('unit')
                    ->select('NAMA_UNIT')
                    ->where('idunit', (int)$target->ID_UNIT)
                    ->get()
                    ->getRow();
                $targetUnitName = $unitRow ? $unitRow->NAMA_UNIT : 'Unit ' . $target->ID_UNIT;
            }
        }

        $kpi = null;
        $existing = [];
        $attendanceComponents = [];
        $allowedComponentCodes = [];
        if ($target) {
            $kpiService = new \App\Services\Kpi\KpiCalculationService();
            $kpi = $kpiService->calculateForSalary((int)$target->ID_AKUN, (string)$bulan, (string)$tahun, 'penilaian_kinerja');

            // Komponen absen (urut sesuai detail_absen)
            $attendanceCodes = ['KEHADIRAN', 'KEBERSIHAN', 'SERAGAM', 'KEPATUHAN_SOP'];
            $attendanceComponents = (new \App\Models\ModelKpiComponent())
                ->whereIn('code', $attendanceCodes)->findAll();

            // Komponen yang boleh dinilai evaluator utk target ini (server-side)
            foreach ($attendanceComponents as $comp) {
                if (\App\Services\Kpi\EvaluatorAuthorizationService::canEvaluateComponent(
                    (int)$me->ID_AKUN,
                    (int)$target->ID_AKUN,
                    (string)$comp->code
                )) {
                    $allowedComponentCodes[] = (string)$comp->code;
                }
            }

            // Ambil skor harian existing utk periode (rata-rata antar evaluator per hari)
            $evals = (new \App\Models\ModelKpiEvaluation())
                ->where('employee_id', (int)$target->ID_AKUN)
                ->where('period_year', $tahun)
                ->where('period_month', $bulan)
                ->findAll();

            foreach ($evals as $e) {
                $day = (int)date('j', strtotime($e->evaluation_date));
                $cid = (int)$e->kpi_component_id;
                if (!isset($existing[$cid][$day])) {
                    $existing[$cid][$day] = ['sum' => 0.0, 'count' => 0];
                }
                $existing[$cid][$day]['sum'] += (float)$e->raw_score;
                $existing[$cid][$day]['count']++;
            }

            foreach ($existing as $cid => &$days) {
                foreach ($days as $d => &$v) {
                    $v = round($v['sum'] / $v['count'], 2);
                }
                unset($d, $v);
            }
            unset($cid, $days);
        }

        return view('template', [
            'list_karyawan'         => $list_karyawan,
            'selected_karyawan'     => $selected_karyawan,
            'target'                => $target,
            'targetUnitName'        => $targetUnitName,
            'allowedComponentCodes' => $allowedComponentCodes,
            'kpi'                   => $kpi,
            'detail_absen'          => $kpi['detail_absen'] ?? [],
            'skor_total2'           => $kpi['skor_total2'] ?? 0,
            'attendanceComponents'  => $attendanceComponents,
            'existing'              => $existing,
            'bulan'                 => $bulan,
            'tahun'                 => $tahun,
            'body'                  => 'penilaian/penilaian_absen'
        ]);
    }

    public function save_absen()
    {
        $me      = $this->AuthModel->getById((int)session()->get('ID_AKUN'));
        $myRole  = (int)($me->ID_JABATAN ?? 0);

        $employeeId = (int)$this->request->getPost('employee_id');
        $bulan      = (int)($this->request->getPost('bulan') ?: date('m'));
        $tahun      = (int)($this->request->getPost('tahun') ?: date('Y'));

        $target = $this->AuthModel->getById($employeeId);
        if (!$target || !$this->isInScope($me, $target)) {
            return redirect()->to('/penilaian/absen')
                ->with('error', 'Pegawai tidak ditemukan / tidak dalam lingkup Anda.');
        }

        $attendanceCodes = ['KEHADIRAN', 'KEBERSIHAN', 'SERAGAM', 'KEPATUHAN_SOP'];
        $components = (new \App\Models\ModelKpiComponent())
            ->whereIn('code', $attendanceCodes)->findAll();
        $codeToComponent = [];
        foreach ($components as $c) {
            $codeToComponent[$c->code] = $c;
        }

        // Form mengirim: tanggal + 4 aspek sekaligus (skor_kehadiran, skor_kebersihan, dll)
        $tanggal = $this->request->getPost('tanggal');
        $confirm = (int)$this->request->getPost('confirm'); // 1 = user sudah konfirmasi

        if (empty($tanggal)) {
            return redirect()->to('/penilaian/absen?karyawan=' . $employeeId . '&bulan=' . $bulan . '&tahun=' . $tahun)
                ->with('error', 'Tanggal wajib diisi.');
        }

        // Validasi tanggal berada dalam bulan/tahun yang sedang dipilih
        if ((int)date('m', strtotime($tanggal)) !== $bulan || (int)date('Y', strtotime($tanggal)) !== $tahun) {
            return redirect()->to('/penilaian/absen?karyawan=' . $employeeId . '&bulan=' . $bulan . '&tahun=' . $tahun)
                ->with('error', 'Tanggal harus berada dalam bulan/tahun yang dipilih.');
        }

        // Collect input values — HANYA komponen yang diotorisasi untuk evaluator+target ini.
        $savedAnyAllowed = false;
        $inputValues = [];
        foreach ($codeToComponent as $code => $comp) {
            if (!\App\Services\Kpi\EvaluatorAuthorizationService::canEvaluateComponent(
                (int)$me->ID_AKUN,
                $employeeId,
                (string)$code
            )) {
                continue; // lewati komponen yang tidak berwenang (server-side).
            }

            $savedAnyAllowed = true;
            $fieldName = 'skor_' . strtolower($code);
            $skor = (int)$this->request->getPost($fieldName);
            if ($skor >= 1 && $skor <= 5) {
                $inputValues[$code] = ['skor' => $skor, 'comp' => $comp];
            }
        }

        if (!$savedAnyAllowed) {
            return redirect()->to('/penilaian/absen')
                ->with('error', 'Anda tidak berwenang menilai komponen absensi pegawai ini.');
        }

        if (empty($inputValues)) {
            return redirect()->to('/penilaian/absen?karyawan=' . $employeeId . '&bulan=' . $bulan . '&tahun=' . $tahun)
                ->with('error', 'Tidak ada skor yang valid untuk disimpan (harus 1-5).');
        }

        // Cek apakah tanggal ini sudah memiliki data (untuk konfirmasi)
        if ($confirm !== 1) {
            // Extract component IDs from inputValues
            $componentIds = array_map(function($item) {
                return (int)$item['comp']->id;
            }, $inputValues);

            $existingData = $this->db->table('kpi_evaluations')
                ->select('kpi_component_id, raw_score')
                ->where('employee_id', $employeeId)
                ->where('evaluator_id', (int)$me->ID_AKUN)
                ->where('evaluation_date', $tanggal)
                ->whereIn('kpi_component_id', $componentIds)
                ->get()
                ->getResultArray();

            if (!empty($existingData)) {
                // Ada data existing → butuh konfirmasi
                $existingByComponentId = [];
                foreach ($existingData as $row) {
                    $existingByComponentId[(int)$row['kpi_component_id']] = (float)$row['raw_score'];
                }

                // Build comparison data
                $comparisons = [];
                foreach ($inputValues as $code => $data) {
                    $compId = (int)$data['comp']->id;
                    if (isset($existingByComponentId[$compId])) {
                        $oldScore = (int)$existingByComponentId[$compId];
                        $newScore = $data['skor'];
                        if ($oldScore !== $newScore) {
                            $comparisons[] = [
                                'name' => $data['comp']->name,
                                'old' => $oldScore,
                                'new' => $newScore,
                            ];
                        }
                    }
                }

                if (!empty($comparisons)) {
                    // Set session data untuk modal konfirmasi
                    session()->setFlashdata('require_confirmation', [
                        'tanggal' => $tanggal,
                        'comparisons' => $comparisons,
                        'post_data' => $this->request->getPost(),
                    ]);
                    return redirect()->to('/penilaian/absen?karyawan=' . $employeeId . '&bulan=' . $bulan . '&tahun=' . $tahun);
                }
            }
        }

        // Proceed to save (either no existing data, or user confirmed)
        $svc = new \App\Services\Kpi\KpiEvaluationService();
        $saved = 0;

        foreach ($inputValues as $code => $data) {
            $result = $svc->recordEvaluation([
                'employee_id'      => $employeeId,
                'kpi_component_id' => (int)$data['comp']->id,
                'evaluator_id'     => (int)$me->ID_AKUN,
                'evaluation_date'  => $tanggal,
                'raw_score'        => $data['skor'],
                'max_score'        => 5,
                'notes'            => 'Absensi: ' . $data['comp']->name . ' (Skor: ' . $data['skor'] . '/5)',
            ]);

            if ($result['success']) {
                $saved++;
            }
        }

        return redirect()->to('/penilaian/absen?karyawan=' . $employeeId . '&bulan=' . $bulan . '&tahun=' . $tahun)
            ->with('success', 'Skor absensi ' . date('d M Y', strtotime($tanggal)) . ' tersimpan (' . $saved . ' komponen).');
    }

    public function slip_gaji($idakun)
    {
        $awal_bulan = date('01 m');
        $akhir_bulan = date('t m Y');

        $bulan = $this->request->getGet('bulan');
        $tahun = $this->request->getGet('tahun');

        $namajabatan = $this->db->table('jabatan')
            ->select('jabatan.*')
            ->join('akun', 'akun.ID_JABATAN = jabatan.ID_JABATAN')
            ->where('akun.ID_AKUN', $idakun)
            ->get()
            ->getRowArray();

        $jalanunit = $this->db->table('unit')
            ->select('unit.*')
            ->join('akun', 'akun.ID_UNIT = unit.idunit')
            ->where('akun.ID_AKUN', $idakun)
            ->get()
            ->getRowArray();

        // =========================================================
        // KPI SCORES (context slip_gaji) — KpiCalculationService
        // =========================================================
        $kpiService = new \App\Services\Kpi\KpiCalculationService();
        $kpi = $kpiService->calculateForSalary((int) $idakun, (string) $bulan, (string) $tahun, 'slip_gaji');

        // =========================================================
        // BON & LEMBUR — source existing (kas_keluar, kategori 10)
        // =========================================================
        $bon = $this->db->table('kas_keluar kk')
            ->selectSum('kk.jumlah', 'total_bon')
            ->where('kk.kategori_idkategori', 10)
            ->where('kk.penerima', $idakun)
            ->like('kk.deskripsi', 'bon')
            ->get()
            ->getRow()
            ->total_bon ?? 0;

        $lembur = $this->db->table('kas_keluar kk')
            ->selectSum('kk.jumlah', 'total_bon')
            ->where('kk.kategori_idkategori', 10)
            ->where('kk.penerima', $idakun)
            ->like('kk.deskripsi', 'lembur')
            ->get()
            ->getRow()
            ->total_bon ?? 0;

        // =========================================================
        // SALARY COMPOSITION — SalaryCalculationService (context slip_gaji)
        // =========================================================
        $periodDate = (($tahun ?: date('Y')) . '-' . ($bulan ?: date('m')) . '-15');

        $salaryService = new \App\Services\Payroll\SalaryCalculationService();
        $result = $salaryService->calculateSalary(
            (int) $idakun,
            $kpi['jabatan'],
            $kpi['unit'],
            'slip_gaji',
            [
                'TUNJANGAN_KINERJA' => $kpi['skor_total'],
                'TUNJANGAN_ABSEN'   => $kpi['skor_total2'],
            ],
            $kpi['akun']->tunjangan_penempatan,
            $kpi['insentif'],
            (float) $lembur,
            (float) $bon,
            $periodDate
        );

        $compByCode = [];
        foreach (($result['components'] ?? []) as $comp) {
            $compByCode[$comp['component_code']] = (float) $comp['amount'];
        }

        // View slip_gaji menghitung TOTAL(A)=gaji+lembur dan BERSIH=gaji+lembur-bon.
        // Maka gaji yang dikirim = total_gaji - lembur + bon (basis tanpa bon/lembur).
        $gaji = (float) $result['total_gaji'] - (float) $result['lembur'] + (float) $result['bon'];

        return view('cetak/slip_gaji', [
            'pegawai'              => $kpi['karyawan'],
            'jabatan'              => $namajabatan,
            'unit'                 => $jalanunit,
            'gaji_pokok'           => $compByCode['GAJI_POKOK'] ?? $result['subtotal_before_incentive'] ?? 0,
            'tunjangan_kinerja'    => $compByCode['TUNJANGAN_KINERJA'] ?? 0,
            'tunjangan_absen'      => $compByCode['TUNJANGAN_ABSEN'] ?? 0,
            'insentif'             => $result['incentive'],
            'tunjangan_penempatan' => $result['placement_allowance'],
            'gaji'                 => $gaji,
            'bon'                  => $bon,
            'lembur'               => $lembur,
            'bulan'                => $bulan,
            'tahun'                => $tahun,
            'awal_bulan'           => $awal_bulan,
            'akhir_bulan'          => $akhir_bulan,
        ]);
    }

    public function gaji()
    {
        $bulan = $this->request->getGet('bulan') ?? date('m');
        $tahun = $this->request->getGet('tahun') ?? date('Y');

        $list_karyawan = $this->db->table('akun')
            ->where('STATUS_PEGAWAI', 1)
            ->where('ID_JABATAN !=', 1)
            ->get()
            ->getResultArray();

        $selected_karyawan = session()->get('ID_AKUN');
        if (!$selected_karyawan && !empty($list_karyawan)) {
            $selected_karyawan = $list_karyawan[0]['ID_AKUN'];
        }

        // =========================================================
        // KPI SCORES (context gaji) — KpiCalculationService
        // =========================================================
        $kpiService = new \App\Services\Kpi\KpiCalculationService();
        $kpi = $kpiService->calculateForSalary((int) $selected_karyawan, (string) $bulan, (string) $tahun, 'gaji');

        // =========================================================
        // SALARY COMPOSITION — SalaryCalculationService (context gaji)
        // =========================================================
        $periodDate = "$tahun-$bulan-15";
        $salaryService = new \App\Services\Payroll\SalaryCalculationService();
        $result = $salaryService->calculateSalary(
            (int) $selected_karyawan,
            $kpi['jabatan'],
            $kpi['unit'],
            'gaji',
            [
                'TUNJANGAN_KINERJA' => $kpi['skor_total'],
                'TUNJANGAN_ABSEN'   => $kpi['skor_total2'],
            ],
            $kpi['akun']->tunjangan_penempatan,
            $kpi['insentif'],
            0.0,
            0.0,
            $periodDate
        );

        $compByCode = [];
        foreach (($result['components'] ?? []) as $comp) {
            $compByCode[$comp['component_code']] = (float) $comp['amount'];
        }

        return view('template', [
            'list_karyawan'        => $list_karyawan,
            'selected_karyawan'    => $selected_karyawan,
            'karyawan'             => $kpi['karyawan'],
            'detail_kpi'           => $kpi['detail_kpi'],
            'detail_absen'         => $kpi['detail_absen'],
            'aktual_omset_unit'    => $kpi['aktual_omset_unit'],
            'skor_total'           => $kpi['skor_total'],
            'tunjangan_kinerja'    => $compByCode['TUNJANGAN_KINERJA'] ?? 0,
            'tunjangan_absen'      => $compByCode['TUNJANGAN_ABSEN'] ?? 0,
            'insentif'             => $result['incentive'],
            'tunjangan_penempatan' => $kpi['akun'],
            'gaji_pokok'           => $compByCode['GAJI_POKOK'] ?? $kpi['gaji_pokok'],
            'gaji'                 => $result['total_gaji'],
            'bulan'                => $bulan,
            'tahun'                => $tahun,
            'body'                 => 'penilaian/gaji'
        ]);
    }
}
