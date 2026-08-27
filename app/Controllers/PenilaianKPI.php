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

    // public function index()
    // {
    //     $pegawai_id = $this->request->getGet('pegawai_idpegawai');
    //     $templatekpi = [];
    //     $skorMap = [];

    //     if ($pegawai_id) {
    //         $pegawai = $this->AuthModel->getById($pegawai_id);

    //         if ($pegawai && isset($pegawai->ID_JABATAN)) {
    //             $templatekpi = $this->TemplateKpiModel->getByJabatan($pegawai->ID_JABATAN);

    //             // Get penilaian
    //             $penilaianList = $this->PenilaianModel
    //                 ->where('pegawai_idpegawai', $pegawai_id)
    //                 ->findAll();

    //             foreach ($penilaianList as $p) {
    //                 $skorMap[$p->aspek]['realisasi'] = $p->skor;
    //             }

    //             $bulanIni = date('Y-m-01');
    //             $bulanAkhir = date('Y-m-t');

    //             //Omset

    //             $omsetResult = $this->PenjualanModel
    //                 ->selectSum('total_penjualan')
    //                 ->where('sales_by', $pegawai_id)
    //                 ->where('tanggal >=', $bulanIni)
    //                 ->where('tanggal <=', $bulanAkhir)
    //                 ->first();

    //             $omsetValue = $omsetResult->total_penjualan ?? 0;

    //             $skorMap['Penjualan(Omzet)']['realisasi'] = $omsetValue;

    //             $tanggalPenilaian = $this->request->getGet('tanggal_penilaian_kpi') ?? date('Y-m-d');

    //             $bulan = date('m', strtotime($tanggalPenilaian));
    //             $tahun = date('Y', strtotime($tanggalPenilaian));
    //             $startDate = date('Y-m-01', strtotime("$tahun-$bulan-01"));
    //             $endDate = date('Y-m-t', strtotime("$tahun-$bulan-01"));

    //             $onTimeResult = $this->PresensiModel
    //                 ->countOnTimeAbsensiPerBulan($pegawai_id, $startDate, $endDate);
    //             $onTimeCount = $onTimeResult->total_ontime ?? 0;
    //             $skorMap['Kedisiplinan']['realisasi'] = $onTimeCount;

    //             $absensiResult = $this->PresensiModel
    //                 ->countAbsensiPerBulan($pegawai_id, $startDate, $endDate);
    //             $jumlahAbsensi = $absensiResult->total_absensi ?? 0;
    //             $skorMap['Kehadiran']['realisasi'] = $jumlahAbsensi;

    //             $groomingResult = $this->PresensiModel
    //                 ->countGrooming($pegawai_id, $startDate, $endDate);
    //             $jumlahGrooming = $groomingResult->total_grooming ?? 0;
    //             $skorMap['Grooming']['realisasi'] = $jumlahGrooming;

    //             $categoryResult = $this->DetailPenjualanModel
    //                 ->countByCategory(2, $startDate, $endDate);
    //             $totalCategory = $categoryResult->total_category ?? 0;
    //             $skorMap['Up-selling dan Cross-selling']['realisasi'] = $totalCategory;

    //             //media
    //             $jumlahByTemplate = $this->PenilaianKPIModel->getJumlahByTemplateKPI($pegawai_id);

    //             foreach ($jumlahByTemplate as $row) {
    //                 $skorMap[$row->template_kpi]['realisasi'] = $row->jumlah;
    //             }


    //         }
    //     }

    //     $data = [
    //         'penilaiankpi' => [],
    //         'akun' => $this->AuthModel->getdataakun(),
    //         'pegawai_idpegawai' => $pegawai_id,
    //         'templatekpi' => $templatekpi,
    //         'skorMap' => $skorMap,
    //         'body' => 'penilaian/penilaian_kpi',
    //     ];

    //     return view('template', $data);
    //     // return $this->response->setJSON($data);
    // }

    public function index()
    {
        $pegawai_id = $this->request->getGet('pegawai_idpegawai');
        $templatekpi = [];
        $skorMap = [];
        $isUpdate = false;

        // Initialize arrays
        $levelList = [];
        $templateIdList = [];
        $unitId = null;

        $penilaianKPIList = [];
        $kpiExistingMap = [];    // map template_id => penilaian object
        $idpenilaianList = [];   // list of existing penilaian ids
        $prefillList = [];       // per-template prefill data

        if ($pegawai_id) {
            $pegawai = $this->AuthModel->getById($pegawai_id);

            if ($pegawai && isset($pegawai->ID_JABATAN)) {
                $unitId = $pegawai->unit_idunit ?? null; // unit comes from pegawai

                // 1️⃣ Get KPI template for this jabatan
                $templatekpi = $this->TemplateKpiModel->getByJabatan($pegawai->ID_JABATAN);

                // 2️⃣ Populate defaults from template
                foreach ($templatekpi as $i => $tpl) {
                    $levelList[$i] = $tpl->level;
                    $templateIdList[$i] = $tpl->idtemplate_kpi;
                }

                $tanggalPenilaian = $this->request->getGet('tanggal_penilaian_kpi') ?? date('Y-m-d');
                $bulan = date('m', strtotime($tanggalPenilaian));
                $tahun = date('Y', strtotime($tanggalPenilaian));
                $startDate = date('Y-m-01', strtotime("$tahun-$bulan-01"));
                $endDate = date('Y-m-t', strtotime("$tahun-$bulan-01"));

                // 3️⃣ Fetch existing penilaian_kpi rows for this pegawai & month
                $penilaianKPIList = $this->PenilaianKPIModel
                    ->where('pegawai_idpegawai', $pegawai_id)
                    ->where('tanggal_penilaian_kpi >=', $startDate)
                    ->where('tanggal_penilaian_kpi <=', $endDate)
                    ->findAll();

                // Build map by template_kpi_id for reliable lookup (template_id => penilaian object)
                foreach ($penilaianKPIList as $p) {
                    $kpiExistingMap[$p->template_kpi_idtemplate_kpi] = $p;
                    $idpenilaianList[] = $p->idpenilaian_kpi;

                    // fill skorMap keyed by template or kpi_utama as needed
                    $skorMap[$p->template_kpi_idtemplate_kpi]['realisasi'] = $p->realisasi ?? '';
                    $skorMap[$p->template_kpi_idtemplate_kpi]['score'] = $p->score ?? '';

                    // ensure unit override if present
                    $unitId = $p->unit_idunit ?? $unitId;
                }

                // Prefill data for each template in consistent order
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

                // ✅ Update button only if ALL displayed templates already have penilaian this month
                // i.e. every template id must exist as a key in $kpiExistingMap
                $isUpdate = !empty($templatekpi);
                foreach ($templatekpi as $tpl) {
                    if (!isset($kpiExistingMap[$tpl->idtemplate_kpi])) {
                        $isUpdate = false;
                        break;
                    }
                }

                // 4️⃣ Other metrics like Omset, Presensi, Grooming...
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

                // Additional KPI template-based data
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
        // return json_encode($data);
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
                    'level'                        => $levelList[$i] ?? null,
                    'unit_idunit'                  => $unitIdList[$i] ?? null,
                    'template_kpi_idtemplate_kpi'  => $templateIdList[$i] ?? null,
                    'pegawai_idpegawai'            => $pegawai_id,
                    'tanggal_penilaian_kpi'        => $tanggal,
                    'level'                        => '1',
                    'created_on'                   => date('Y-m-d H:i:s'),
                ]);
            }
        }

        // 3️⃣ Flash message & redirect
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

        // Parse year and month from the given date
        $bulan = date('m', strtotime($tanggal));
        $tahun = date('Y', strtotime($tanggal));
        $startDate = date('Y-m-01', strtotime("$tahun-$bulan-01"));
        $endDate   = date('Y-m-t', strtotime("$tahun-$bulan-01"));

        // Step 1: Delete all existing records for this pegawai and month
        $this->PenilaianKPIModel
            ->where('pegawai_idpegawai', $pegawai_id)
            ->where('tanggal_penilaian_kpi >=', $startDate)
            ->where('tanggal_penilaian_kpi <=', $endDate)
            ->delete();

        // Step 2: Insert the new batch records
        $batchData = [];
        foreach ($kpiList as $i => $kpi) {
            $batchData[] = [
                'kpi_utama'             => $kpi,
                'bobot'                 => $bobotList[$i] ?? null,
                'target'                => $targetList[$i] ?? null,
                'realisasi'             => $realisasiList[$i] ?? null,
                'score'                 => $scoreList[$i] ?? null,
                'level'                 => $levelList[$i] ?? null,
                'pegawai_idpegawai'     => $pegawai_id,
                'tanggal_penilaian_kpi' => $tanggal,
                'level'                 => '1',
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


    // public function insert_penilaian()
    // {
    //     // 1️⃣ Get POST data
    //     $kpiList          = $this->request->getPost('kpi_utama');
    //     $bobotList        = $this->request->getPost('bobot');
    //     $targetList       = $this->request->getPost('target');
    //     $realisasiList    = $this->request->getPost('realisasi');
    //     $scoreList        = $this->request->getPost('score');
    //     $levelList        = $this->request->getPost('level');
    //     $unitIdList       = $this->request->getPost('unit_idunit');
    //     $templateIdList   = $this->request->getPost('template_kpi_idtemplate_kpi');

    //     $pegawai_id = $this->request->getPost('pegawai_idpegawai');
    //     $tanggal    = $this->request->getPost('tanggal_penilaian_kpi');

    //     // 2️⃣ Fetch or create parent "Checklist Pekerjaan"
    //     $parent = $this->PenilaianModel
    //         ->where('pegawai_idpegawai', $pegawai_id)
    //         ->where('tanggal_penilaian', $tanggal)
    //         ->where('aspek', 'Checklist Pekerjaan')
    //         ->first();

    //     if (!$parent) {
    //         $parent_id = $this->PenilaianModel->insert([
    //             'pegawai_idpegawai' => $pegawai_id,
    //             'tanggal_penilaian' => $tanggal,
    //             'aspek'             => 'Checklist Pekerjaan',
    //             'keterangan'        => 'Penilaian KPI',
    //             'created_on'        => date('Y-m-d H:i:s'),
    //         ]);
    //     } else {
    //         $parent_id = $parent->idpenilaian;
    //     }

    //     // 3️⃣ Insert KPI rows linked to parent
    //     if ($kpiList && is_array($kpiList)) {
    //         foreach ($kpiList as $i => $kpi) {
    //             $this->PenilaianKPIModel->insertKPI(
    //                 $kpi,
    //                 $bobotList[$i] ?? null,
    //                 $targetList[$i] ?? null,
    //                 $realisasiList[$i] ?? 0,
    //                 $scoreList[$i] ?? 0,
    //                 $levelList[$i] ?? null,
    //                 $unitIdList[$i] ?? null,
    //                 $templateIdList[$i] ?? null,
    //                 $pegawai_id,
    //                 $tanggal,
    //                 $parent_id
    //             );
    //         }
    //     }

    //         session()->setFlashdata('sukses', 'Data Berhasil Ditambahkan');
    //         return redirect()->to(base_url('penilaian_kpi'));
    //     }


    // public function insert_penilaian()
    // {
    //     $kpiList       = $this->request->getPost('kpi_utama');
    //     $bobotList     = $this->request->getPost('bobot');
    //     $targetList    = $this->request->getPost('target');
    //     $realisasiList = $this->request->getPost('realisasi');
    //     $scoreList     = $this->request->getPost('score');

    //     $pegawai_id = $this->request->getPost('pegawai_idpegawai');
    //     $tanggal    = $this->request->getPost('tanggal_penilaian_kpi');

    //     // 1️⃣ Fetch or create parent "Checklist Pekerjaan"
    //     $parent = $this->PenilaianModel
    //         ->where('pegawai_idpegawai', $pegawai_id)
    //         ->where('tanggal_penilaian', $tanggal)
    //         ->where('aspek', 'Checklist Pekerjaan')
    //         ->first();

    //     if (!$parent) {
    //         $parent_id = $this->PenilaianModel->insert([
    //             'pegawai_idpegawai' => $pegawai_id,
    //             'tanggal_penilaian' => $tanggal,
    //             'aspek'            => 'Checklist Pekerjaan',
    //             'keterangan'       => 'Penilaian KPI',
    //             'created_on'       => date('Y-m-d H:i:s'),
    //         ]);
    //     } else {
    //         $parent_id = $parent->idpenilaian;
    //     }

    //     // 2️⃣ Insert KPI rows linked to parent
    //     if ($kpiList && is_array($kpiList)) {
    //        foreach ($kpiList as $i => $kpi) {
    //     $this->PenilaianKPIModel->insertKPI(
    //     $kpi,
    //     $bobotList[$i] ?? null,
    //     $targetList[$i] ?? null,
    //     $realisasiList[$i] ?? 0,
    //     $scoreList[$i] ?? 0,
    //     $pegawai_id,
    //     $tanggal,
    //     $parent_id
    // );

    // }

    //     }

    //     session()->setFlashdata('sukses', 'Data Berhasil Ditambahkan');
    //     return redirect()->to(base_url('penilaian_kpi'));
    // }


    //     public function insert_penilaian()
    // {
    //     $kpiList = $this->request->getPost('kpi_utama');
    //     $bobotList = $this->request->getPost('bobot');
    //     $targetList = $this->request->getPost('target');
    //     $realisasiList = $this->request->getPost('realisasi');
    //     $scoreList = $this->request->getPost('score');

    //     $pegawai_id = $this->request->getPost('pegawai_idpegawai');
    //     $tanggal = $this->request->getPost('tanggal_penilaian_kpi');

    //     if ($kpiList && is_array($kpiList)) {
    //         foreach ($kpiList as $i => $kpi) {
    //             $this->PenilaianKPIModel->insertKPI(
    //                 $kpi,
    //                 $bobotList[$i] ?? null,
    //                 $targetList[$i] ?? null,
    //                 [$realisasiList[$i] ?? 0],
    //                 [$scoreList[$i] ?? 0],
    //                 $pegawai_id,
    //                 $tanggal
    //             );
    //         }
    //     }

    //     session()->setFlashdata('sukses', 'Data Berhasil Ditambahkan');
    //     return redirect()->to(base_url('penilaian_kpi'));
    // }





    public function delete_penilaian()
    {
        $id = $this->request->getPost('idpenilaian_kpi');
        $this->PenilaianKPIModel->delete($id);
        session()->setFlashdata('sukses', 'Data Berhasil Dihapus');
        return redirect()->to(base_url('penilaian'));
    }


    public function index_riwayat()
    {
        // Ambil semua penilaian_kpi
        $riwayat = $this->PenilaianKPIModel
            ->select('penilaian_kpi.*, penilaian_kpi.penilaian_idpenilaian, akun.NAMA_AKUN as pegawai_nama, jabatan.NAMA_JABATAN as jabatan_nama, unit.NAMA_UNIT as unit_nama')
            ->join('akun', 'akun.ID_AKUN = penilaian_kpi.pegawai_idpegawai', 'left')
            ->join('jabatan', 'jabatan.ID_JABATAN = akun.ID_JABATAN', 'left')
            ->join('unit', 'unit.idunit = akun.ID_UNIT', 'left')
            ->orderBy('penilaian_kpi.created_on', 'ASC')
            ->findAll();


        foreach ($riwayat as $row) {
            // Ambil detail KPI sesuai penilaian_kpi
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

            // Ambil aspek_detail dari penilaian_detail
            // ✅ Gunakan penilaian_idpenilaian yang ada di penilaian_kpi
            $aspek_detail = $this->PenilaianDetailModel
                ->select('template_penilaian.aspek_penilaian, penilaian_detail.skor')
                ->join('template_penilaian', 'template_penilaian.idtemplate_penilaian = penilaian_detail.template_penilaian_idtemplate_penilaian', 'left')
                ->where('penilaian_detail.penilaian_idpenilaian', $row->penilaian_idpenilaian)
                ->findAll();

            // Assign ke objek row
            $row->detail = $detail ?: [];
            $row->total_score = $totalScore;
            $row->aspek_detail = $aspek_detail ?: [];
        }

        // Group per pegawai + created_on + jabatan + unit
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
        // return $this->response->setJSON($grouped_riwayat);

    }

    public function export_penilaian_detail()
    {
        $tanggal_awal = $this->request->getPost('tanggal_awal');
        $tanggal_akhir = $this->request->getPost('tanggal_akhir');

        // Build query safely (only add date filters if provided)
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

        // Group by pegawai + tanggal + jabatan + unit
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

        // 💠 Light Blue for Pegawai Section
        $pegawaiFill = [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'BDD7EE'] // Light blue
        ];

        $rowNum = 1;

        foreach ($grouped as $key => $items) {
            list($pegawai, $tanggal, $jabatan, $unit) = explode('|', $key);

            // 💠 Pegawai Section (with background color)
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

            // Detail header
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

                // ✅ Show aspek_detail only if KPI = Checklist Pekerjaan
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

            // Total row
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
        $db = \Config\Database::connect();

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
            // LIST KARYAWAN
            $list_karyawan = $this->db->table('akun')
                ->where('STATUS_PEGAWAI', 1)
                ->where('ID_JABATAN !=', 1)
                ->where('ID_JABATAN !=', 2)
                ->get()
                ->getResultArray();
        }

        // KARYAWAN TERPILIH
        $selected_karyawan = $this->request->getGet('karyawan');

        if (!$selected_karyawan && !empty($list_karyawan)) {
            $selected_karyawan = $list_karyawan[0]['ID_AKUN'];
        }

        $karyawan = $this->db->table('akun')
            ->where('ID_AKUN', $selected_karyawan)
            ->get()
            ->getRowArray();

        $jabatan = $karyawan['ID_JABATAN'];
        $unit    = $karyawan['ID_UNIT'];

        $query = $db->query("
                                SELECT 
                                    NAMA_AKUN,
                                    ALAMAT,
                                    ID_UNIT,
                                    CASE
                                        WHEN ALAMAT = 'Probolinggo' AND ID_UNIT = 1 THEN 1
                                        WHEN ALAMAT = 'Jember' AND ID_UNIT = 2 THEN 1
                                        WHEN ALAMAT = 'Banyuwangi' AND ID_UNIT = 3 THEN 1
                                        ELSE 0
                                    END AS penempatan
                                FROM akun
                                WHERE ID_AKUN=$selected_karyawan
                            ");

        $akun = $query->getRow();

        if ($akun->penempatan == 0) {
            $akun->tunjangan_penempatan = 350000;
        } else {
            $akun->tunjangan_penempatan = 0;
        }

        //traget setiap cabang

        $target_unit = [

            1 => [
                'customer'  => 130,
                'atas_customer'  => 220,
                'bawah_customer'  => 150,
                'closing'   => 111,
                'upselling' => 14,
                'followup'  => 100,
                'roas'      => 5,
            ],

            2 => [
                'customer'  => 118,
                'atas_customer'  => 180,
                'bawah_customer'  => 150,
                'closing'   => 96,
                'upselling' => 14,
                'followup'  => 80,
                'roas'      => 4,
            ],

            3 => [
                'customer'  => 210,
                'atas_customer'  => 350,
                'bawah_customer'  => 250,
                'closing'   => 188,
                'upselling' => 27,
                'followup'  => 60,
                'roas'      => 3,
            ],

            4 => [
                'customer'  => 118,
                'atas_customer'  => 250,
                'bawah_customer'  => 200,
                'closing'   => 96,
                'upselling' => 14,
                'followup'  => 80,
                'roas'      => 5,
            ]
        ];

        $target = $target_unit[$unit] ?? $target_unit[1];

        $batas_awal = [
            1 => 35000000, // Probolinggo
            2 => 18000000, // Jember
            3 => 40000000, // Banyuwangi
            4 => 35000000, // Pandaan
        ];

        $batas_kedua = [
            1 => 40000000, // Probolinggo
            2 => 22000000, // Jember
            3 => 45000000, // Banyuwangi
            4 => 40000000, // Pandaan
        ];

        $batas_ketiga = [
            1 => 45000000, // Probolinggo
            2 => 26000000, // Jember
            3 => 50000000, // Banyuwangi
            4 => 45000000, // Pandaan
        ];

        $batas_keempat = [
            1 => 50000000, // Probolinggo
            2 => 30000000, // Jember
            3 => 55000000, // Banyuwangi
            4 => 50000000, // Pandaan
        ];

        $target_omset = [
            1 => 55000000, // Probolinggo
            2 => 35000000, // Jember
            3 => 60000000, // Banyuwangi
            4 => 55000000, // Pandaan
        ];

        //nilai dari db

        $aktual_omset_unit = [

            1 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', $bulan)
                ->where('YEAR(penjualan.tanggal)', $tahun)
                ->where('penjualan.unit_idunit =', 1)
                ->get()
                ->getRow()
                ->total ?? 0, // Cabang 1
            2 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', $bulan)
                ->where('YEAR(penjualan.tanggal)', $tahun)
                ->where('penjualan.unit_idunit =', 2)
                ->get()
                ->getRow()
                ->total ?? 0,  // Cabang 2
            3 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', $bulan)
                ->where('YEAR(penjualan.tanggal)', $tahun)
                ->where('penjualan.unit_idunit =', 3)
                ->get()
                ->getRow()
                ->total ?? 0,  // Cabang 3
            4 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', $bulan)
                ->where('YEAR(penjualan.tanggal)', $tahun)
                ->where('penjualan.unit_idunit =', 4)
                ->get()
                ->getRow()
                ->total ?? 0,

        ];
        $aktual_omset = $aktual_omset_unit[$unit] ?? 0;

        $aktual_customer       = [

            1 => $this->db->table('penjualan')
                ->select('COUNT(kode_invoice) AS total')
                ->where('MONTH(tanggal)', $bulan)
                ->where('YEAR(tanggal)', $tahun)
                ->where('unit_idunit', 1)
                ->get()
                ->getRow()
                ->total ?? 0, // Cabang 1
            2 => $this->db->table('penjualan')
                ->select('COUNT(kode_invoice) AS total')
                ->where('MONTH(tanggal)', $bulan)
                ->where('YEAR(tanggal)', $tahun)
                ->where('unit_idunit', 2)
                ->get()
                ->getRow()
                ->total ?? 0,  // Cabang 2
            3 => $this->db->table('penjualan')
                ->select('COUNT(kode_invoice) AS total')
                ->where('MONTH(tanggal)', $bulan)
                ->where('YEAR(tanggal)', $tahun)
                ->where('unit_idunit', 3)
                ->get()
                ->getRow()
                ->total ?? 0,
            4 => $this->db->table('penjualan')
                ->select('COUNT(kode_invoice) AS total')
                ->where('MONTH(tanggal)', $bulan)
                ->where('YEAR(tanggal)', $tahun)
                ->where('unit_idunit', 4)
                ->get()
                ->getRow()
                ->total ?? 0,

        ];
        $total_customer = $aktual_customer[$unit] ?? 0;

        $aktual_hpp       = [

            // 1 => 4000000, // Cabang 1
            // 2 => 4000000,
            // 3 => 4000000,
            // 4 => 3000000,
            // 2 => $this->db->table('kas_keluar')
            //                         ->select('SUM(jumlah) AS total')
            //                         ->where('MONTH(tanggal)', $bulan)
            //                         ->where('YEAR(tanggal)', $tahun)
            //                         ->where('idunit', 2)
            //                         ->where('kategori_idkategori', 7)
            //                         ->get()
            //                         ->getRow()
            //                         ->total ?? 0,  // Cabang 2
            // 3 => $this->db->table('kas_keluar')
            //                         ->select('SUM(jumlah) AS total')
            //                         ->where('MONTH(tanggal)', $bulan)
            //                         ->where('YEAR(tanggal)', $tahun)
            //                         ->where('idunit', 3)
            //                         ->where('kategori_idkategori', 7)
            //                         ->get()
            //                         ->getRow()
            //                         ->total ?? 0,
            // 4 => $this->db->table('kas_keluar')
            //                         ->select('SUM(jumlah) AS total')
            //                         ->where('MONTH(tanggal)', $bulan)
            //                         ->where('YEAR(tanggal)', $tahun)
            //                         ->where('idunit', 4)
            //                         ->where('kategori_idkategori', 7)
            //                         ->get()
            //                         ->getRow()
            //                         ->total ?? 0,
            1 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', date('m'))
                ->where('YEAR(penjualan.tanggal)', date('Y'))
                ->where('penjualan.unit_idunit =', 1)
                ->get()
                ->getRow()
                ->total ?? 0,  // Cabang 3
            2 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', date('m'))
                ->where('YEAR(penjualan.tanggal)', date('Y'))
                ->where('penjualan.unit_idunit =', 2)
                ->get()
                ->getRow()
                ->total ?? 0,  // Cabang 3
            3 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', date('m'))
                ->where('YEAR(penjualan.tanggal)', date('Y'))
                ->where('penjualan.unit_idunit =', 3)
                ->get()
                ->getRow()
                ->total ?? 0,  // Cabang 3
            4 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', date('m'))
                ->where('YEAR(penjualan.tanggal)', date('Y'))
                ->where('penjualan.unit_idunit =', 4)
                ->get()
                ->getRow()
                ->total ?? 0,  // Cabang 3

        ];
        $total_hpp = $aktual_hpp[$unit] ?? 0;



        $aktual_tutup_kasir    = $this->db->table('tutup_kasir')
            ->select('COUNT(status) AS total')
            ->where('MONTH(tanggal)', $bulan)
            ->where('YEAR(tanggal)', $tahun)
            ->where('unit', $unit)
            ->get()
            ->getRow();
        $total_tutup_kasir = $aktual_tutup_kasir->total ?? 0;

        $aktual_opname         = $this->db->table('stok_opname_draft')
            ->select('COUNT(DISTINCT DATE(tanggal)) AS total')
            ->where('unit_idunit', $unit)
            ->where('MONTH(tanggal)', $bulan)
            ->where('YEAR(tanggal)', $tahun)
            ->get()
            ->getRow()
            ->total ?? 0;

        $aktual_absen         = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek =', 'kehadiran')
            ->get()
            ->getRow();
        $total_absen = $aktual_absen->total ?? 0;

        $aktual_divisi         = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->get()
            ->getRow();
        $total_divisi = $aktual_divisi->total ?? 0;

        $ak_kebersihan         = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek =', 'kebersihan')
            ->get()
            ->getRow();
        $ttl_kebersihan = $ak_kebersihan->total ?? 0;

        $ak_seragam         = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek =', 'seragam')
            ->get()
            ->getRow();
        $ttl_seragam = $ak_seragam->total ?? 0;

        $ak_kepatuhan          = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek =', 'kepatuhan sop')
            ->get()
            ->getRow();
        $ttl_kepatuhan  = $ak_kepatuhan->total ?? 0;

        $aktual_closing        = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'closing')
            ->get()
            ->getRow();
        $total_closing = $aktual_closing->total ?? 0;

        $aktual_upselling      = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'upselling')
            ->get()
            ->getRow();
        $total_upselling = $aktual_upselling->total ?? 0;

        $aktual_followup       = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'follow up')
            ->get()
            ->getRow();
        $total_followup = $aktual_followup->total ?? 0;

        $aktual_budgeting      = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'budgeting')
            ->get()
            ->getRow();
        $total_budgeting = $aktual_budgeting->total ?? 0;

        $aktual_roas           = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'roas')
            ->get()
            ->getRow();
        $total_roas = $aktual_roas->total ?? 0;

        $aktual_feed_pl        = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'feed pl')
            ->get()
            ->getRow();
        $total_feed = $aktual_feed_pl->total ?? 0;

        $aktual_video          = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'video')
            ->get()
            ->getRow();
        $total_video = $aktual_video->total ?? 0;

        $aktual_feed_mingguan  = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'feed mingguan')
            ->get()
            ->getRow();
        $total_feed = $aktual_feed_mingguan->total ?? 0;

        $aktual_story          = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'story')
            ->get()
            ->getRow();
        $total_story = $aktual_story->total ?? 0;

        $aktual_testimoni      = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'testimoni')
            ->get()
            ->getRow();
        $total_testimoni = $aktual_testimoni->total ?? 0;

        $aktual_bug_minor      = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'bug minor')
            ->get()
            ->getRow();
        $total_bug_minor = $aktual_bug_minor->total ?? 0;

        $aktual_bug_operasional = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'operasional')
            ->get()
            ->getRow();
        $total_bug_operasional = $aktual_bug_operasional->total ?? 0;

        $aktual_ecommerce      = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'ecommerce')
            ->get()
            ->getRow();
        $total_ecommerce = $aktual_ecommerce->total ?? 0;

        $aktual_fitur          = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'operasional')
            ->get()
            ->getRow();
        $total_fitur = $aktual_fitur->total ?? 0;

        // $aktual_kehadiran = 150;
        $aktual_kehadiran = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'kehadiran')
            ->get()
            ->getRow();

        $totalKehadiran = $aktual_kehadiran->total ?? 0;

        $aktual_kebersihan = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'kebersihan')
            ->get()
            ->getRow();
        $totalKebersihan = $aktual_kebersihan->total ?? 0;

        $aktual_seragam = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'seragam')
            ->get()
            ->getRow();

        $totalSeragam = $aktual_seragam->total ?? 0;

        $aktual_sop = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'kepatuhan sop')
            ->get()
            ->getRow();
        $totalSop = $aktual_sop->total ?? 0;

        //persentas nilai
        $batas1 = $batas_awal[$unit];
        $batas2 = $batas_kedua[$unit];
        $batas3 = $batas_ketiga[$unit];
        $batas4 = $batas_keempat[$unit];

        $targetOmset = $target_omset[$unit];

        $aktual_operasional = 0;

        $insentif = 0;

        if ($jabatan == 41) {
            if ($aktual_omset <= $batas1) {
                $nilai_omset = 0;
            } elseif ($aktual_omset == $batas2) {
                $nilai_omset = 33;
            } elseif ($aktual_omset == $batas3) {
                $nilai_omset = 66;
            } elseif ($aktual_omset >= $batas4 && $aktual_omset < $targetOmset) {
                $nilai_omset = 100;
            } elseif ($aktual_omset >= $targetOmset) {
                $nilai_omset = 100;
                $insentif = (3 / 100) * $aktual_omset / 3;
            } else {
                $nilai_omset = (($aktual_omset - $batas1) / ($batas4 - $batas1)) * 100;
            }
        } elseif ($jabatan == 40) {

            $cabang_aman = 0;

            foreach ($aktual_omset_unit as $idUnit => $omset) {

                $batasCabang = $batas_keempat[$idUnit];

                if ($omset >= $batasCabang) {
                    $cabang_aman++;
                }

                // insentif jika target cabang tercapai
                if ($omset >= $target_omset[$idUnit]) {
                    $insentif += (5 / 1000) * $omset;
                }
            }

            switch ($cabang_aman) {
                case 1:
                    $nilai_omset = 25;

                    $aktual_operasional    = 25;

                    break;

                case 2:
                    $nilai_omset = 50;

                    $aktual_operasional    = 50;

                    break;

                case 3:
                    $nilai_omset = 75;

                    $aktual_operasional    = 75;

                    break;

                case 4:
                    $nilai_omset = 100;

                    $aktual_operasional    = 100;

                    break;

                default:
                    $nilai_omset = 0;

                    $aktual_operasional    = 0;
                    break;
            }
        } elseif ($jabatan == 43) {

            $cabang_aman = 0;

            foreach ($aktual_omset_unit as $idUnit => $omset) {

                $batasCabang = $batas_keempat[$idUnit];

                if ($omset >= $batasCabang) {
                    $cabang_aman++;
                }

                // insentif jika target cabang tercapai
                if ($omset >= $target_omset[$idUnit]) {
                    $insentif += (1 / 100) * $omset / 4;
                }
            }

            switch ($cabang_aman) {
                case 1:
                    $nilai_omset = 25;

                    $aktual_operasional    = 25;

                    break;

                case 2:
                    $nilai_omset = 50;

                    $aktual_operasional    = 50;

                    break;

                case 3:
                    $nilai_omset = 75;

                    $aktual_operasional    = 75;

                    break;

                case 4:
                    $nilai_omset = 100;

                    $aktual_operasional    = 100;

                    break;

                default:
                    $nilai_omset = 0;

                    $aktual_operasional    = 0;
                    break;
            }
        } else {

            if ($aktual_omset < $batas2) {
                $nilai_omset = 0;
            } elseif ($aktual_omset >= $batas2 && $aktual_omset < $batas3) {
                $nilai_omset = 33;
            } elseif ($aktual_omset >= $batas3 && $aktual_omset < $batas4) {
                $nilai_omset = 66;
            } elseif ($aktual_omset >= $batas4 && $aktual_omset < $targetOmset) {
                $nilai_omset = 100;
            } elseif ($aktual_omset >= $targetOmset) {
                $nilai_omset = 100;
                $insentif = (3 / 100) * $aktual_omset / 3;
            } else {
                $nilai_omset = (($aktual_omset - $batas1) / ($batas4 - $batas1)) * 100;
            }
        }

        $customer_aman = 0;

        foreach ($aktual_customer as $idUnit => $customer) {

            $batasCabang = $target['atas_customer'];

            if ($customer >= $batasCabang) {
                $customer_aman++;
            }
        }
        switch ($customer_aman) {
            case 1:
                $nilai_customer = 25;
                break;

            case 2:
                $nilai_customer = 50;
                break;

            case 3:
                $nilai_customer = 75;
                break;

            case 4:
                $nilai_customer = 100;
                break;

            default:
                $nilai_customer = 0;
                break;
        }

        $nilai_closing = min(
            ($total_closing / $target['closing']) * 100,
            100
        );

        $nilai_upselling = min(
            ($total_upselling / $target['upselling']) * 100,
            100
        );

        $nilai_followup = min(
            ($total_followup / $target['followup']) * 100,
            100
        );

        $nilai_roas = $total_roas * 20;

        $nilai_hpp = 0;

        foreach ($aktual_hpp as $idUnit => $hpp) {

            $omset = $aktual_omset_unit[$idUnit] ?? 0;

            if ($omset == 0) {
                continue;
            }

            // Persentase HPP
            $persentase = ($hpp / $omset) * 100;

            // Maksimal kontribusi setiap cabang = 25 poin
            if ($persentase <= 35) {
                $nilai_hpp += 25;
            } elseif ($persentase <= 40) {
                $nilai_hpp += 18.75;
            } elseif ($persentase <= 45) {
                $nilai_hpp += 12.5;
            } else {
                $nilai_hpp += 0;
            }
        }

        $total_hpp = $aktual_hpp[1] + $aktual_hpp[2] + $aktual_hpp[3] + $aktual_hpp[4];
        $totalomset = $aktual_omset_unit[1] + $aktual_omset_unit[2] + $aktual_omset_unit[3] + $aktual_omset_unit[4] ?? 1;

        // Persentase HPP
        $persentasetotal = ($total_hpp / $totalomset) * 100;


        if ($persentasetotal <= 35) {
            $nilai_hpp_global = 100;
        } elseif ($persentasetotal <= 40) {
            $nilai_hpp_global = 75;
        } elseif ($persentasetotal <= 45) {
            $nilai_hpp_global = 50;
        } else {
            $nilai_hpp_global = 0;
        }

        $nilai_tutup_kasir  = min(
            ($total_tutup_kasir / 30) * 100,
            100
        );
        $nilai_opname       = $aktual_opname / 4 * 100;
        $nilai_absen        = $total_absen * 20;

        $nilai_operasional  = $aktual_operasional;
        $nilai_divisi       = $total_divisi * 20;

        $rata_kebersihan    = $ttl_kebersihan * 20;
        $rata_seragam    = $ttl_seragam * 20;
        $rata_kepatuhan    = $ttl_kepatuhan * 20;

        $nilai_budgeting    = $total_budgeting * 20;

        $nilai_feed_pl      = $total_feed;
        $nilai_video        = $total_video;
        $nilai_feed_mingguan = $total_feed;
        $nilai_story        = $total_story;
        $nilai_testimoni    = $total_testimoni;

        $nilai_bug_minor    = $total_bug_minor / 4 * 20;
        $nilai_bug_operasional = $total_bug_operasional / 4 * 20;
        $nilai_ecommerce    = $total_ecommerce / 4 * 20;
        $nilai_fitur        = $total_fitur / 4 * 20;

        $nilai_kehadiran = $totalKehadiran / 26 * 20;
        $nilai_kebersihan = $totalKebersihan / 26 * 20;
        $nilai_seragam = $totalSeragam / 26 * 20;
        $nilai_sop = $totalSop / 26 * 20;

        //gaji sesuai jabatan

        $skor_total = 0;
        $skor_total2 = 0;
        $detail_kpi = [];
        $detail_absen = [];

        switch ($jabatan) {

            // ADMIN
            case 35:

                $detail_kpi = [
                    ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ['nama' => 'Tutup Kasir', 'bobot' => 10, 'nilai' => $nilai_tutup_kasir],
                    ['nama' => 'Stok Opname', 'bobot' => 10, 'nilai' => $nilai_opname],
                    ['nama' => 'Absensi', 'bobot' => 10, 'nilai' => $nilai_absen],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // TEKNISI
            case 36:

                $detail_kpi = [
                    ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ['nama' => 'Omset Teknisi', 'bobot' => 15, 'nilai' => $nilai_omset],
                    ['nama' => 'Customer Masuk', 'bobot' => 15, 'nilai' => $nilai_customer],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // KEPALA TOKO
            case 41:

                $detail_kpi = [
                    ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ['nama' => 'Total Customer', 'bobot' => 10, 'nilai' => $nilai_customer],
                    ['nama' => 'Tutup Kasir', 'bobot' => 10, 'nilai' => $nilai_tutup_kasir],
                    ['nama' => 'Opname', 'bobot' => 10, 'nilai' => $nilai_opname],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // SPV
            case 40:

                $detail_kpi = [
                    ['nama' => 'Omset Cabang', 'bobot' => 10, 'nilai' => $nilai_omset],
                    ['nama' => 'Customer', 'bobot' => 70, 'nilai' => $nilai_customer],
                    ['nama' => 'Operasional', 'bobot' => 10, 'nilai' => $nilai_operasional],
                    ['nama' => 'Divisi', 'bobot' => 10, 'nilai' => $nilai_divisi],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $rata_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $rata_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $rata_kepatuhan],
                ];

                break;

            // CUSTOMER SERVICE
            case 42:

                $detail_kpi = [
                    ['nama' => 'Omset', 'bobot' => 60, 'nilai' => $nilai_omset],
                    ['nama' => 'Closing', 'bobot' => 10, 'nilai' => $nilai_closing],
                    ['nama' => 'Upselling', 'bobot' => 10, 'nilai' => $nilai_upselling],
                    ['nama' => 'Follow Up', 'bobot' => 10, 'nilai' => $nilai_followup],
                    ['nama' => 'Testimoni', 'bobot' => 10, 'nilai' => $nilai_testimoni],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // PENGIKLAN
            case 43:

                $detail_kpi = [
                    ['nama' => 'Budgeting', 'bobot' => 15, 'nilai' => $nilai_budgeting],
                    ['nama' => 'ROAS', 'bobot' => 15, 'nilai' => $nilai_roas],
                    ['nama' => 'Omset', 'bobot' => 10, 'nilai' => $nilai_omset],
                    ['nama' => 'Customer', 'bobot' => 60, 'nilai' => $nilai_customer],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // MULTIMEDIA
            case 44:

                $detail_kpi = [
                    ['nama' => 'Omset Cabang', 'bobot' => 30, 'nilai' => $nilai_omset],
                    ['nama' => 'Feed PL', 'bobot' => 15, 'nilai' => $nilai_feed_pl],
                    ['nama' => 'Video', 'bobot' => 20, 'nilai' => $nilai_video],
                    ['nama' => 'Feed Mingguan', 'bobot' => 15, 'nilai' => $nilai_feed_mingguan],
                    ['nama' => 'Story', 'bobot' => 10, 'nilai' => $nilai_story],
                    ['nama' => 'Testimoni', 'bobot' => 10, 'nilai' => $nilai_testimoni],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // IT
            case 45:

                $detail_kpi = [
                    ['nama' => 'Omset', 'bobot' => 30, 'nilai' => $nilai_omset],
                    ['nama' => 'Bug Minor', 'bobot' => 10, 'nilai' => $nilai_bug_minor],
                    ['nama' => 'Operasional', 'bobot' => 25, 'nilai' => $nilai_bug_operasional],
                    ['nama' => 'Ecommerce', 'bobot' => 15, 'nilai' => $nilai_ecommerce],
                    ['nama' => 'Fitur', 'bobot' => 20, 'nilai' => $nilai_fitur],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            //PIC
            case 46:

                $detail_kpi = [
                    ['nama' => 'Budget Per Toko', 'bobot' => 20, 'nilai' => $nilai_hpp],
                    ['nama' => 'Budget Global', 'bobot' => 30, 'nilai' => $nilai_hpp_global],
                    ['nama' => 'Omset Cabang', 'bobot' => 50, 'nilai' => $nilai_omset],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;
        }

        //total nilai

        foreach ($detail_kpi as $kpi) {
            $skor_total += ($kpi['nilai'] * $kpi['bobot']) / 100;
        }

        foreach ($detail_absen as $absen) {
            $skor_total2 += ($absen['nilai'] * $absen['bobot']) / 100;
        }

        $tunjangan_absen = $skor_total2 / 100 * 250000;

        if ($jabatan == 41) {
            $tunjangan_kinerja = $skor_total / 100 * 850000;
        } elseif ($jabatan == 46) {
            $tunjangan_kinerja = $skor_total / 100 * 850000;
        } elseif ($jabatan == 40) {
            $tunjangan_kinerja = $skor_total / 100 * 1250000;
        } elseif ($jabatan == 43) {
            $tunjangan_kinerja = $skor_total / 100 * 1000000;
        } elseif ($jabatan == 35) {
            if ($unit == 1) {
                $tunjangan_kinerja = $skor_total / 100 * 850000;
            } else {
                $tunjangan_kinerja = $skor_total / 100 * 250000;
            }
        } else {
            $tunjangan_kinerja = $skor_total / 100 * 250000;
        }


        $gaji_pokok = 1500000;

        $gaji = $gaji_pokok + $tunjangan_kinerja + $tunjangan_absen + $akun->tunjangan_penempatan + $insentif;

        return view('template', [
            'list_karyawan'     => $list_karyawan,
            'selected_karyawan' => $selected_karyawan,
            'karyawan'          => $karyawan,
            'detail_kpi'        => $detail_kpi,
            'detail_absen'      => $detail_absen,
            'aktual_omset_unit' => $aktual_omset_unit,
            'skor_total'        => round($skor_total, 2),
            'tunjangan_kinerja' => $tunjangan_kinerja,
            'tunjangan_absen'   => $tunjangan_absen,
            'insentif'          => $insentif,
            'tunjangan_penempatan' => $akun,
            'gaji_pokok'        => $gaji_pokok,
            'gaji'              => $gaji,
            'bulan'             => $bulan,
            'tahun'             => $tahun,
            'body'              => 'penilaian/penilaian_kinerja'
        ]);
    }

    public function slip_gaji($idakun)
    {
        $awal_bulan = date('01 m');
        $akhir_bulan = date('t m Y');

        $bulan = $this->request->getGet('bulan');
        $tahun = $this->request->getGet('tahun');

        $db = \Config\Database::connect();

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

        $idUnit = session()->get('ID_UNIT');

        $karyawan = $this->db->table('akun')
            ->where('ID_AKUN', $idakun)
            ->get()
            ->getRowArray();

        $jabatan = $karyawan['ID_JABATAN'];
        $unit    = $karyawan['ID_UNIT'];

        $query = $db->query("
                                SELECT 
                                    NAMA_AKUN,
                                    ALAMAT,
                                    ID_UNIT,
                                    CASE
                                        WHEN ALAMAT = 'Probolinggo' AND ID_UNIT = 1 THEN 1
                                        WHEN ALAMAT = 'Jember' AND ID_UNIT = 2 THEN 1
                                        WHEN ALAMAT = 'Banyuwangi' AND ID_UNIT = 3 THEN 1
                                        ELSE 0
                                    END AS penempatan
                                FROM akun
                                WHERE ID_AKUN=$idakun
                            ");

        $akun = $query->getRow();

        if ($akun->penempatan == 0) {
            $akun->tunjangan_penempatan = 350000;
        } else {
            $akun->tunjangan_penempatan = 0;
        }

        //traget setiap cabang

        $target_unit = [

            1 => [
                'customer'  => 130,
                'atas_customer'  => 220,
                'bawah_customer'  => 150,
                'closing'   => 111,
                'upselling' => 14,
                'followup'  => 100,
                'roas'      => 5,
            ],

            2 => [
                'customer'  => 118,
                'atas_customer'  => 180,
                'bawah_customer'  => 150,
                'closing'   => 96,
                'upselling' => 14,
                'followup'  => 80,
                'roas'      => 4,
            ],

            3 => [
                'customer'  => 210,
                'atas_customer'  => 350,
                'bawah_customer'  => 250,
                'closing'   => 188,
                'upselling' => 27,
                'followup'  => 60,
                'roas'      => 3,
            ],

            4 => [
                'customer'  => 118,
                'atas_customer'  => 250,
                'bawah_customer'  => 200,
                'closing'   => 96,
                'upselling' => 14,
                'followup'  => 80,
                'roas'      => 5,
            ]
        ];

        $target = $target_unit[$unit] ?? $target_unit[1];

        $batas_awal = [
            1 => 35000000, // Probolinggo
            2 => 18000000, // Jember
            3 => 40000000, // Banyuwangi
            4 => 35000000, // Pandaan
        ];

        $batas_kedua = [
            1 => 40000000, // Probolinggo
            2 => 22000000, // Jember
            3 => 45000000, // Banyuwangi
            4 => 40000000, // Pandaan
        ];

        $batas_ketiga = [
            1 => 45000000, // Probolinggo
            2 => 26000000, // Jember
            3 => 50000000, // Banyuwangi
            4 => 45000000, // Pandaan
        ];

        $batas_keempat = [
            1 => 50000000, // Probolinggo
            2 => 30000000, // Jember
            3 => 55000000, // Banyuwangi
            4 => 50000000, // Pandaan
        ];

        $target_omset = [
            1 => 55000000, // Probolinggo
            2 => 35000000, // Jember
            3 => 60000000, // Banyuwangi
            4 => 55000000, // Pandaan
        ];

        //nilai dari db

        $aktual_omset_unit = [

            1 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', $bulan)
                ->where('YEAR(penjualan.tanggal)', $tahun)
                ->where('penjualan.unit_idunit =', 1)
                ->get()
                ->getRow()
                ->total ?? 0, // Cabang 1
            2 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', $bulan)
                ->where('YEAR(penjualan.tanggal)', $tahun)
                ->where('penjualan.unit_idunit =', 2)
                ->get()
                ->getRow()
                ->total ?? 0,  // Cabang 2
            3 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', $bulan)
                ->where('YEAR(penjualan.tanggal)', $tahun)
                ->where('penjualan.unit_idunit =', 3)
                ->get()
                ->getRow()
                ->total ?? 0,  // Cabang 3
            4 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', $bulan)
                ->where('YEAR(penjualan.tanggal)', $tahun)
                ->where('penjualan.unit_idunit =', 4)
                ->get()
                ->getRow()
                ->total ?? 0,
            // 2 => $this->db->table('detail_penjualan')
            //         ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
            //         ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            //         ->where('MONTH(penjualan.tanggal)', $bulan)
            //         ->where('YEAR(penjualan.tanggal)', $tahun)
            //         ->where('penjualan.unit_idunit =', 2)
            //         ->get()
            //         ->getRow()
            //         ->total ?? 0,  // Cabang 2
            // 3 => $this->db->table('detail_penjualan')
            //         ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
            //         ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            //         ->where('MONTH(penjualan.tanggal)', $bulan)
            //         ->where('YEAR(penjualan.tanggal)', $tahun)
            //         ->where('penjualan.unit_idunit =', 3)
            //         ->get()
            //         ->getRow()
            //         ->total ?? 0,  // Cabang 3
            // 4 => $this->db->table('detail_penjualan')
            //         ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
            //         ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            //         ->where('MONTH(penjualan.tanggal)', $bulan)
            //         ->where('YEAR(penjualan.tanggal)', $tahun)
            //         ->where('penjualan.unit_idunit =', 4)
            //         ->get()
            //         ->getRow()
            //         ->total ?? 0,                

        ];
        $aktual_omset = $aktual_omset_unit[$unit] ?? 0;

        $aktual_customer       = [

            1 => $this->db->table('penjualan')
                ->select('COUNT(kode_invoice) AS total')
                ->where('MONTH(tanggal)', $bulan)
                ->where('YEAR(tanggal)', $tahun)
                ->where('unit_idunit', 1)
                ->get()
                ->getRow()
                ->total ?? 0, // Cabang 1
            2 => $this->db->table('penjualan')
                ->select('COUNT(kode_invoice) AS total')
                ->where('MONTH(tanggal)', $bulan)
                ->where('YEAR(tanggal)', $tahun)
                ->where('unit_idunit', 2)
                ->get()
                ->getRow()
                ->total ?? 0,  // Cabang 2
            3 => $this->db->table('penjualan')
                ->select('COUNT(kode_invoice) AS total')
                ->where('MONTH(tanggal)', $bulan)
                ->where('YEAR(tanggal)', $tahun)
                ->where('unit_idunit', 3)
                ->get()
                ->getRow()
                ->total ?? 0,
            4 => $this->db->table('penjualan')
                ->select('COUNT(kode_invoice) AS total')
                ->where('MONTH(tanggal)', $bulan)
                ->where('YEAR(tanggal)', $tahun)
                ->where('unit_idunit', 4)
                ->get()
                ->getRow()
                ->total ?? 0,
            // 2 => $this->db->table('penjualan')
            //         ->select('COUNT(kode_invoice) AS total')
            //         ->where('MONTH(tanggal)', $bulan)
            //         ->where('YEAR(tanggal)', $tahun)
            //         ->where('unit_idunit', 2)
            //         ->get()
            //         ->getRow()
            //         ->total ?? 0,  // Cabang 2
            // 3 => $this->db->table('penjualan')
            //         ->select('COUNT(kode_invoice) AS total')
            //         ->where('MONTH(tanggal)', $bulan)
            //         ->where('YEAR(tanggal)', $tahun)
            //         ->where('unit_idunit', 3)
            //         ->get()
            //         ->getRow()
            //         ->total ?? 0,
            // 4 => $this->db->table('penjualan')
            //         ->select('COUNT(kode_invoice) AS total')
            //         ->where('MONTH(tanggal)', $bulan)
            //         ->where('YEAR(tanggal)', $tahun)
            //         ->where('unit_idunit', 4)
            //         ->get()
            //         ->getRow()
            //         ->total ?? 0,
            // 3 => $this->db->table('detail_penjualan')
            //         ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
            //         ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            //         ->where('MONTH(penjualan.tanggal)', date('m'))
            //         ->where('YEAR(penjualan.tanggal)', date('Y'))
            //         ->where('penjualan.unit_idunit =', 3)
            //         ->get()
            //         ->getRow()
            //         ->total ?? 0,  // Cabang 3

        ];
        $total_customer = $aktual_customer[$unit] ?? 0;

        $aktual_hpp       = [

            1 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', date('m'))
                ->where('YEAR(penjualan.tanggal)', date('Y'))
                ->where('penjualan.unit_idunit =', 1)
                ->get()
                ->getRow()
                ->total ?? 0,  // Cabang 3
            2 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', date('m'))
                ->where('YEAR(penjualan.tanggal)', date('Y'))
                ->where('penjualan.unit_idunit =', 2)
                ->get()
                ->getRow()
                ->total ?? 0,  // Cabang 3
            3 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', date('m'))
                ->where('YEAR(penjualan.tanggal)', date('Y'))
                ->where('penjualan.unit_idunit =', 3)
                ->get()
                ->getRow()
                ->total ?? 0,  // Cabang 3
            4 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', date('m'))
                ->where('YEAR(penjualan.tanggal)', date('Y'))
                ->where('penjualan.unit_idunit =', 4)
                ->get()
                ->getRow()
                ->total ?? 0,  // Cabang 3
            // 2 => $this->db->table('kas_keluar')
            //                         ->select('SUM(jumlah) AS total')
            //                         ->where('MONTH(tanggal)', $bulan)
            //                         ->where('YEAR(tanggal)', $tahun)
            //                         ->where('idunit', 2)
            //                         ->where('kategori_idkategori', 7)
            //                         ->get()
            //                         ->getRow()
            //                         ->total ?? 0,  // Cabang 2
            // 3 => $this->db->table('kas_keluar')
            //                         ->select('SUM(jumlah) AS total')
            //                         ->where('MONTH(tanggal)', $bulan)
            //                         ->where('YEAR(tanggal)', $tahun)
            //                         ->where('idunit', 3)
            //                         ->where('kategori_idkategori', 7)
            //                         ->get()
            //                         ->getRow()
            //                         ->total ?? 0,
            // 4 => $this->db->table('kas_keluar')
            //                         ->select('SUM(jumlah) AS total')
            //                         ->where('MONTH(tanggal)', $bulan)
            //                         ->where('YEAR(tanggal)', $tahun)
            //                         ->where('idunit', 4)
            //                         ->where('kategori_idkategori', 7)
            //                         ->get()
            //                         ->getRow()
            //                         ->total ?? 0,
            // 3 => $this->db->table('detail_penjualan')
            //         ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
            //         ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            //         ->where('MONTH(penjualan.tanggal)', date('m'))
            //         ->where('YEAR(penjualan.tanggal)', date('Y'))
            //         ->where('penjualan.unit_idunit =', 3)
            //         ->get()
            //         ->getRow()
            //         ->total ?? 0,  // Cabang 3

        ];
        $total_hpp = $aktual_hpp[$unit] ?? 0;



        $aktual_tutup_kasir    = $this->db->table('tutup_kasir')
            ->select('COUNT(status) AS total')
            ->where('MONTH(tanggal)', $bulan)
            ->where('YEAR(tanggal)', $tahun)
            ->where('unit', $unit)
            ->get()
            ->getRow();
        $total_tutup_kasir = $aktual_tutup_kasir->total ?? 0;

        $aktual_opname         = $this->db->table('stok_opname_draft')
            ->select('COUNT(DISTINCT DATE(tanggal)) AS total')
            ->where('unit_idunit', $unit)
            ->where('MONTH(tanggal)', $bulan)
            ->where('YEAR(tanggal)', $tahun)
            ->get()
            ->getRow()
            ->total ?? 0;

        $aktual_absen         = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek =', 'kehadiran')
            ->get()
            ->getRow();
        $total_absen = $aktual_absen->total ?? 0;

        $aktual_divisi         = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->get()
            ->getRow();
        $total_divisi = $aktual_divisi->total ?? 0;

        $ak_kebersihan         = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek =', 'kebersihan')
            ->get()
            ->getRow();
        $ttl_kebersihan = $ak_kebersihan->total ?? 0;

        $ak_seragam         = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek =', 'seragam')
            ->get()
            ->getRow();
        $ttl_seragam = $ak_seragam->total ?? 0;

        $ak_kepatuhan          = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek =', 'kepatuhan sop')
            ->get()
            ->getRow();
        $ttl_kepatuhan  = $ak_kepatuhan->total ?? 0;

        $aktual_closing        = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'closing')
            ->get()
            ->getRow();
        $total_closing = $aktual_closing->total ?? 0;

        $aktual_upselling      = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'upselling')
            ->get()
            ->getRow();
        $total_upselling = $aktual_upselling->total ?? 0;

        $aktual_followup       = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'followup')
            ->get()
            ->getRow();
        $total_followup = $aktual_followup->total ?? 0;

        $aktual_budgeting      = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'budgeting')
            ->get()
            ->getRow();
        $total_budgeting = $aktual_budgeting->total ?? 0;

        $aktual_roas           = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'roas')
            ->get()
            ->getRow();
        $total_roas = $aktual_roas->total ?? 0;

        $aktual_feed_pl        = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'feed pl')
            ->get()
            ->getRow();
        $total_feed = $aktual_feed_pl->total ?? 0;

        $aktual_video          = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'video')
            ->get()
            ->getRow();
        $total_video = $aktual_video->total ?? 0;

        $aktual_feed_mingguan  = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'feed mingguan')
            ->get()
            ->getRow();
        $total_feed = $aktual_feed_mingguan->total ?? 0;

        $aktual_story          = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'story')
            ->get()
            ->getRow();
        $total_story = $aktual_story->total ?? 0;

        $aktual_testimoni      = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'testimoni')
            ->get()
            ->getRow();
        $total_testimoni = $aktual_testimoni->total ?? 0;

        $aktual_bug_minor      = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'bug minor')
            ->get()
            ->getRow();
        $total_bug_minor = $aktual_bug_minor->total ?? 0;

        $aktual_bug_operasional = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'operasional')
            ->get()
            ->getRow();
        $total_bug_operasional = $aktual_bug_operasional->total ?? 0;

        $aktual_ecommerce      = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'ecommerce')
            ->get()
            ->getRow();
        $total_ecommerce = $aktual_ecommerce->total ?? 0;

        $aktual_fitur          = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'operasional')
            ->get()
            ->getRow();
        $total_fitur = $aktual_fitur->total ?? 0;

        // $aktual_kehadiran = 150;
        $aktual_kehadiran = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'kehadiran')
            ->get()
            ->getRow();

        $totalKehadiran = $aktual_kehadiran->total ?? 0;

        $aktual_kebersihan = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'kebersihan')
            ->get()
            ->getRow();
        $totalKebersihan = $aktual_kebersihan->total ?? 0;

        $aktual_seragam = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'seragam')
            ->get()
            ->getRow();

        $totalSeragam = $aktual_seragam->total ?? 0;

        $aktual_sop = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idakun)
            ->where('aspek =', 'kepatuhan sop')
            ->get()
            ->getRow();
        $totalSop = $aktual_sop->total ?? 0;

        //persentas nilai
        $batas1 = $batas_awal[$unit];
        $batas2 = $batas_kedua[$unit];
        $batas3 = $batas_ketiga[$unit];
        $batas4 = $batas_keempat[$unit];

        $targetOmset = $target_omset[$unit];

        $aktual_operasional = 0;

        $insentif = 0;

        if ($jabatan == 41) {
            if ($aktual_omset <= $batas1) {
                $nilai_omset = 0;
            } elseif ($aktual_omset == $batas2) {
                $nilai_omset = 33;
            } elseif ($aktual_omset == $batas3) {
                $nilai_omset = 66;
            } elseif ($aktual_omset >= $batas4 && $aktual_omset < $targetOmset) {
                $nilai_omset = 100;
            } elseif ($aktual_omset >= $targetOmset) {
                $nilai_omset = 100;
                $insentif = (3 / 100) * $aktual_omset / 3;
            } else {
                $nilai_omset = (($aktual_omset - $batas1) / ($batas4 - $batas1)) * 100;
            }
        } elseif ($jabatan == 40) {

            $cabang_aman = 0;

            foreach ($aktual_omset_unit as $idUnit => $omset) {

                $batasCabang = $batas_keempat[$idUnit];

                if ($omset >= $batasCabang) {
                    $cabang_aman++;
                }

                // insentif jika target cabang tercapai
                if ($omset >= $target_omset[$idUnit]) {
                    $insentif += (5 / 1000) * $omset;
                }
            }

            switch ($cabang_aman) {
                case 1:
                    $nilai_omset = 25;

                    $aktual_operasional    = 25;

                    break;

                case 2:
                    $nilai_omset = 50;

                    $aktual_operasional    = 50;

                    break;

                case 3:
                    $nilai_omset = 75;

                    $aktual_operasional    = 75;

                    break;

                case 4:
                    $nilai_omset = 100;

                    $aktual_operasional    = 100;

                    break;

                default:
                    $nilai_omset = 0;

                    $aktual_operasional    = 0;
                    break;
            }
        } elseif ($jabatan == 43) {

            $cabang_aman = 0;

            foreach ($aktual_omset_unit as $idUnit => $omset) {

                $batasCabang = $batas_keempat[$idUnit];

                if ($omset >= $batasCabang) {
                    $cabang_aman++;
                }

                // insentif jika target cabang tercapai
                if ($omset >= $target_omset[$idUnit]) {
                    $insentif += (1 / 100) * $omset / 4;
                }
            }

            switch ($cabang_aman) {
                case 1:
                    $nilai_omset = 25;

                    $aktual_operasional    = 25;

                    break;

                case 2:
                    $nilai_omset = 50;

                    $aktual_operasional    = 50;

                    break;

                case 3:
                    $nilai_omset = 75;

                    $aktual_operasional    = 75;

                    break;

                case 4:
                    $nilai_omset = 100;

                    $aktual_operasional    = 100;

                    break;

                default:
                    $nilai_omset = 0;

                    $aktual_operasional    = 0;
                    break;
            }
        } else {

            if ($aktual_omset < $batas2) {
                $nilai_omset = 0;
            } elseif ($aktual_omset >= $batas2 && $aktual_omset < $batas3) {
                $nilai_omset = 33;
            } elseif ($aktual_omset >= $batas3 && $aktual_omset < $batas4) {
                $nilai_omset = 66;
            } elseif ($aktual_omset >= $batas4 && $aktual_omset < $targetOmset) {
                $nilai_omset = 100;
            } elseif ($aktual_omset >= $targetOmset) {
                $nilai_omset = 100;
                $insentif = (3 / 100) * $aktual_omset / 3;
            } else {
                $nilai_omset = (($aktual_omset - $batas1) / ($batas4 - $batas1)) * 100;
            }
        }

        $customer_aman = 0;

        foreach ($aktual_customer as $idUnit => $customer) {

            $batasCabang = $target['atas_customer'];

            if ($customer >= $batasCabang) {
                $customer_aman++;
            }
        }
        switch ($customer_aman) {
            case 1:
                $nilai_customer = 25;
                break;

            case 2:
                $nilai_customer = 50;
                break;

            case 3:
                $nilai_customer = 75;
                break;

            case 4:
                $nilai_customer = 100;
                break;

            default:
                $nilai_customer = 0;
                break;
        }

        $nilai_closing = min(
            ($total_closing / $target['closing']) * 100,
            100
        );

        $nilai_upselling = min(
            ($total_upselling / $target['upselling']) * 100,
            100
        );

        $nilai_followup = min(
            ($total_followup / $target['followup']) * 100,
            100
        );

        $nilai_roas = $total_roas * 20;

        $nilai_hpp = 0;

        foreach ($aktual_hpp as $idUnit => $hpp) {

            $omset = $aktual_omset_unit[$idUnit] ?? 0;

            if ($omset == 0) {
                continue;
            }

            // Persentase HPP
            $persentase = ($hpp / $omset) * 100;

            // Maksimal kontribusi setiap cabang = 25 poin
            if ($persentase <= 35) {
                $nilai_hpp += 25;
            } elseif ($persentase <= 40) {
                $nilai_hpp += 18.75;
            } elseif ($persentase <= 45) {
                $nilai_hpp += 12.5;
            } else {
                $nilai_hpp += 0;
            }
        }

        $total_hpp = $aktual_hpp[1] + $aktual_hpp[2] + $aktual_hpp[3] + $aktual_hpp[4];
        $totalomset = $aktual_omset_unit[1] + $aktual_omset_unit[2] + $aktual_omset_unit[3] + $aktual_omset_unit[4] ?? 1;

        // Persentase HPP
        $persentasetotal = ($total_hpp / $totalomset) * 100;


        if ($persentasetotal <= 35) {
            $nilai_hpp_global = 100;
        } elseif ($persentasetotal <= 40) {
            $nilai_hpp_global = 75;
        } elseif ($persentasetotal <= 45) {
            $nilai_hpp_global = 50;
        } else {
            $nilai_hpp_global = 0;
        }

        $nilai_tutup_kasir  = min(
            ($total_tutup_kasir / 30) * 100,
            100
        );
        $nilai_opname       = $aktual_opname / 4 * 100;
        $nilai_absen        = $total_absen * 20;

        $nilai_operasional  = $aktual_operasional;
        $nilai_divisi       = $total_divisi * 20;

        $rata_kebersihan    = $ttl_kebersihan * 20;
        $rata_seragam    = $ttl_seragam * 20;
        $rata_kepatuhan    = $ttl_kepatuhan * 20;

        $nilai_budgeting    = $total_budgeting * 20;

        $nilai_feed_pl      = $total_feed;
        $nilai_video        = $total_video;
        $nilai_feed_mingguan = $total_feed;
        $nilai_story        = $total_story;
        $nilai_testimoni    = $total_testimoni;

        $nilai_bug_minor    = $total_bug_minor / 4 * 20;
        $nilai_bug_operasional = $total_bug_operasional / 4 * 20;
        $nilai_ecommerce    = $total_ecommerce / 4 * 20;
        $nilai_fitur        = $total_fitur / 4 * 20;

        $nilai_kehadiran = $totalKehadiran / 26 * 20;
        $nilai_kebersihan = $totalKebersihan / 26 * 20;
        $nilai_seragam = $totalSeragam / 26 * 20;
        $nilai_sop = $totalSop / 26 * 20;

        //gaji sesuai jabatan

        $skor_total = 0;
        $skor_total2 = 0;
        $detail_kpi = [];
        $detail_absen = [];

        switch ($jabatan) {

            // ADMIN
            case 35:

                $detail_kpi = [
                    ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ['nama' => 'Tutup Kasir', 'bobot' => 10, 'nilai' => $nilai_tutup_kasir],
                    ['nama' => 'Stok Opname', 'bobot' => 10, 'nilai' => $nilai_opname],
                    ['nama' => 'Absensi', 'bobot' => 10, 'nilai' => $nilai_absen],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // TEKNISI
            case 36:

                $detail_kpi = [
                    ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ['nama' => 'Omset Teknisi', 'bobot' => 15, 'nilai' => $nilai_omset],
                    ['nama' => 'Customer Masuk', 'bobot' => 15, 'nilai' => $nilai_customer],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // KEPALA TOKO
            case 41:

                $detail_kpi = [
                    ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ['nama' => 'Total Customer', 'bobot' => 10, 'nilai' => $nilai_customer],
                    ['nama' => 'Tutup Kasir', 'bobot' => 10, 'nilai' => $nilai_tutup_kasir],
                    ['nama' => 'Opname', 'bobot' => 10, 'nilai' => $nilai_opname],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // SPV
            case 40:

                $detail_kpi = [
                    ['nama' => 'Omset Cabang', 'bobot' => 10, 'nilai' => $nilai_omset],
                    ['nama' => 'Customer', 'bobot' => 70, 'nilai' => $nilai_customer],
                    ['nama' => 'Operasional', 'bobot' => 10, 'nilai' => $nilai_operasional],
                    ['nama' => 'Divisi', 'bobot' => 10, 'nilai' => $nilai_divisi],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $rata_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $rata_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $rata_kepatuhan],
                ];

                break;

            // CUSTOMER SERVICE
            case 42:

                $detail_kpi = [
                    ['nama' => 'Omset', 'bobot' => 60, 'nilai' => $nilai_omset],
                    ['nama' => 'Closing', 'bobot' => 10, 'nilai' => $nilai_closing],
                    ['nama' => 'Upselling', 'bobot' => 10, 'nilai' => $nilai_upselling],
                    ['nama' => 'Follow Up', 'bobot' => 10, 'nilai' => $nilai_followup],
                    ['nama' => 'Testimoni', 'bobot' => 10, 'nilai' => $nilai_testimoni],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // PENGIKLAN
            case 43:

                $detail_kpi = [
                    ['nama' => 'Budgeting', 'bobot' => 15, 'nilai' => $nilai_budgeting],
                    ['nama' => 'ROAS', 'bobot' => 15, 'nilai' => $nilai_roas],
                    ['nama' => 'Omset', 'bobot' => 10, 'nilai' => $nilai_omset],
                    ['nama' => 'Customer', 'bobot' => 60, 'nilai' => $nilai_customer],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // MULTIMEDIA
            case 44:

                $detail_kpi = [
                    ['nama' => 'Omset Cabang', 'bobot' => 30, 'nilai' => $nilai_omset],
                    ['nama' => 'Feed PL', 'bobot' => 15, 'nilai' => $nilai_feed_pl],
                    ['nama' => 'Video', 'bobot' => 20, 'nilai' => $nilai_video],
                    ['nama' => 'Feed Mingguan', 'bobot' => 15, 'nilai' => $nilai_feed_mingguan],
                    ['nama' => 'Story', 'bobot' => 10, 'nilai' => $nilai_story],
                    ['nama' => 'Testimoni', 'bobot' => 10, 'nilai' => $nilai_testimoni],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // IT
            case 45:

                $detail_kpi = [
                    ['nama' => 'Omset', 'bobot' => 30, 'nilai' => $nilai_omset],
                    ['nama' => 'Bug Minor', 'bobot' => 10, 'nilai' => $nilai_bug_minor],
                    ['nama' => 'Operasional', 'bobot' => 25, 'nilai' => $nilai_bug_operasional],
                    ['nama' => 'Ecommerce', 'bobot' => 15, 'nilai' => $nilai_ecommerce],
                    ['nama' => 'Fitur', 'bobot' => 20, 'nilai' => $nilai_fitur],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            //PIC
            case 46:

                $detail_kpi = [
                    ['nama' => 'Budget Per Toko', 'bobot' => 20, 'nilai' => $nilai_hpp],
                    ['nama' => 'Budget Global', 'bobot' => 30, 'nilai' => $nilai_hpp_global],
                    ['nama' => 'Omset Cabang', 'bobot' => 50, 'nilai' => $nilai_omset],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;
        }

        //total nilai

        foreach ($detail_kpi as $kpi) {
            $skor_total += ($kpi['nilai'] * $kpi['bobot']) / 100;
        }

        foreach ($detail_absen as $absen) {
            $skor_total2 += ($absen['nilai'] * $absen['bobot']) / 100;
        }

        $tunjangan_absen = $skor_total2 / 100 * 250000;

        if ($jabatan == 41) {
            $tunjangan_kinerja = $skor_total / 100 * 850000;
        } elseif ($jabatan == 46) {
            $tunjangan_kinerja = $skor_total / 100 * 850000;
        } elseif ($jabatan == 40) {
            $tunjangan_kinerja = $skor_total / 100 * 1250000;
        } elseif ($jabatan == 43) {
            $tunjangan_kinerja = $skor_total / 100 * 1000000;
        } elseif ($jabatan == 35) {
            if ($unit == 1) {
                $tunjangan_kinerja = $skor_total / 100 * 850000;
            } else {
                $tunjangan_kinerja = $skor_total / 100 * 250000;
            }
        } else {
            $tunjangan_kinerja = $skor_total / 100 * 250000;
        }


        $gaji_pokok = 1500000;

        $gaji = $gaji_pokok + $tunjangan_kinerja + $tunjangan_absen + $akun->tunjangan_penempatan + $insentif;

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

        return view('cetak/slip_gaji', [
            'pegawai' => $karyawan,
            'jabatan' => $namajabatan,
            'unit' => $jalanunit,
            'gaji_pokok' => $gaji_pokok,
            'tunjangan_kinerja' => $tunjangan_kinerja,
            'tunjangan_absen' => $tunjangan_absen,
            'insentif' => $insentif,
            'tunjangan_penempatan' => $akun->tunjangan_penempatan,
            'gaji' => $gaji,
            'bon' => $bon,
            'lembur' => $lembur,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'awal_bulan' => $awal_bulan,
            'akhir_bulan' => $akhir_bulan,
        ]);
    }

    public function gaji()
    {
        $bulan = $this->request->getGet('bulan') ?? date('m');
        $tahun = $this->request->getGet('tahun') ?? date('Y');
        $db = \Config\Database::connect();

        // LIST KARYAWAN
        $list_karyawan = $this->db->table('akun')
            ->where('STATUS_PEGAWAI', 1)
            ->where('ID_JABATAN !=', 1)
            ->get()
            ->getResultArray();

        // KARYAWAN TERPILIH
        $selected_karyawan = session()->get('ID_AKUN');

        if (!$selected_karyawan && !empty($list_karyawan)) {
            $selected_karyawan = $list_karyawan[0]['ID_AKUN'];
        }

        $karyawan = $this->db->table('akun')
            ->where('ID_AKUN', $selected_karyawan)
            ->get()
            ->getRowArray();

        $jabatan = $karyawan['ID_JABATAN'];
        $unit    = $karyawan['ID_UNIT'];

        $query = $db->query("
                                SELECT 
                                    NAMA_AKUN,
                                    ALAMAT,
                                    ID_UNIT,
                                    CASE
                                        WHEN ALAMAT = 'Probolinggo' AND ID_UNIT = 1 THEN 1
                                        WHEN ALAMAT = 'Jember' AND ID_UNIT = 2 THEN 1
                                        WHEN ALAMAT = 'Banyuwangi' AND ID_UNIT = 3 THEN 1
                                        ELSE 0
                                    END AS penempatan
                                FROM akun
                                WHERE ID_AKUN=$selected_karyawan
                            ");

        $akun = $query->getRow();

        if ($akun->penempatan == 0) {
            $akun->tunjangan_penempatan = 350000;
        } else {
            $akun->tunjangan_penempatan = 0;
        }

        //traget setiap cabang

        $target_unit = [

            1 => [
                'customer'  => 130,
                'closing'   => 111,
                'upselling' => 14,
                'followup'  => 100,
                'roas'      => 5,
            ],

            2 => [
                'customer'  => 118,
                'closing'   => 96,
                'upselling' => 14,
                'followup'  => 80,
                'roas'      => 4,
            ],

            3 => [
                'customer'  => 210,
                'closing'   => 188,
                'upselling' => 27,
                'followup'  => 60,
                'roas'      => 3,
            ],

            4 => [
                'customer'  => 1,
                'closing'   => 1,
                'upselling' => 1,
                'followup'  => 1,
                'roas'      => 1,
            ]
        ];

        $target = $target_unit[$unit] ?? $target_unit[1];

        $batas_awal = [
            1 => 30000000, // Probolinggo
            2 => 18000000, // Jember
            3 => 40000000, // Banyuwangi
            4 => 18000000, // Pandaan
        ];

        $batas_kedua = [
            1 => 35000000, // Probolinggo
            2 => 22000000, // Jember
            3 => 45000000, // Banyuwangi
            4 => 22000000, // Pandaan
        ];

        $batas_ketiga = [
            1 => 40000000, // Probolinggo
            2 => 26000000, // Jember
            3 => 50000000, // Banyuwangi
            4 => 26000000, // Pandaan
        ];

        $batas_keempat = [
            1 => 45000000, // Probolinggo
            2 => 30000000, // Jember
            3 => 55000000, // Banyuwangi
            4 => 30000000, // Pandaan
        ];

        $target_omset = [
            1 => 50000000, // Probolinggo
            2 => 35000000, // Jember
            3 => 60000000, // Banyuwangi
            4 => 35000000, // Pandaan
        ];

        //nilai dari db

        $aktual_omset_unit = [

            1 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', date('m'))
                ->where('YEAR(penjualan.tanggal)', date('Y'))
                ->where('penjualan.unit_idunit =', 1)
                ->get()
                ->getRow()
                ->total ?? 0,
            2 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', date('m'))
                ->where('YEAR(penjualan.tanggal)', date('Y'))
                ->where('penjualan.unit_idunit =', 2)
                ->get()
                ->getRow()
                ->total ?? 0,
            3 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', date('m'))
                ->where('YEAR(penjualan.tanggal)', date('Y'))
                ->where('penjualan.unit_idunit =', 3)
                ->get()
                ->getRow()
                ->total ?? 0,  // Cabang 3
            4 => $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', date('m'))
                ->where('YEAR(penjualan.tanggal)', date('Y'))
                ->where('penjualan.unit_idunit =', 4)
                ->get()
                ->getRow()
                ->total ?? 0,

        ];
        $aktual_omset = $aktual_omset_unit[$unit] ?? 0;

        $aktual_customer       = [

            1 => $this->db->table('penjualan')
                ->select('COUNT(idpenjualan) AS total')
                ->where('MONTH(tanggal)', date('m'))
                ->where('YEAR(tanggal)', date('Y'))
                ->where('unit_idunit =', 1)
                ->get()
                ->getRow()
                ->total ?? 0,
            2 => $this->db->table('penjualan')
                ->select('COUNT(idpenjualan) AS total')
                ->where('MONTH(tanggal)', date('m'))
                ->where('YEAR(tanggal)', date('Y'))
                ->where('unit_idunit =', 2)
                ->get()
                ->getRow()
                ->total ?? 0,
            3 => $this->db->table('penjualan')
                ->select('COUNT(idpenjualan) AS total')
                ->where('MONTH(tanggal)', date('m'))
                ->where('YEAR(tanggal)', date('Y'))
                ->where('unit_idunit =', 3)
                ->get()
                ->getRow()
                ->total ?? 0,  // Cabang 3
            4 => $this->db->table('penjualan')
                ->select('COUNT(idpenjualan) AS total')
                ->where('MONTH(tanggal)', date('m'))
                ->where('YEAR(tanggal)', date('Y'))
                ->where('unit_idunit =', 4)
                ->get()
                ->getRow()
                ->total ?? 0,

        ];
        $aktual_customer = $aktual_customer[$unit] ?? 0;

        $aktual_tutup_kasir    = $this->db->table('tutup_kasir')
            ->select('COUNT(status) AS total')
            ->where('MONTH(tanggal)', $bulan)
            ->where('YEAR(tanggal)', $tahun)
            ->where('unit', $unit)
            ->get()
            ->getRow();
        $total_tutup_kasir = $aktual_tutup_kasir->total ?? 0;

        $aktual_opname         = $this->db->table('stok_opname_draft')
            ->select('COUNT(DISTINCT DATE(tanggal)) AS total')
            ->where('unit_idunit', $unit)
            ->where('MONTH(tanggal)', $bulan)
            ->where('YEAR(tanggal)', $tahun)
            ->get()
            ->getRow()
            ->total ?? 0;

        $aktual_absen          = 90;

        $aktual_divisi         = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->get()
            ->getRow();
        $total_divisi = $aktual_divisi->total ?? 0;

        $ak_kebersihan         = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek =', 'kebersihan')
            ->get()
            ->getRow();
        $ttl_kebersihan = $ak_kebersihan->total ?? 0;

        $ak_seragam         = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek =', 'seragam')
            ->get()
            ->getRow();
        $ttl_seragam = $ak_seragam->total ?? 0;

        $ak_kepatuhan          = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek =', 'kepatuhan sop')
            ->get()
            ->getRow();
        $ttl_kepatuhan  = $ak_kepatuhan->total ?? 0;

        $aktual_closing        = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'closing')
            ->get()
            ->getRow();
        $total_closing = $aktual_closing->total ?? 0;

        $aktual_upselling      = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'upselling')
            ->get()
            ->getRow();
        $total_upselling = $aktual_upselling->total ?? 0;

        $aktual_followup       = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'followup')
            ->get()
            ->getRow();
        $total_followup = $aktual_followup->total ?? 0;

        $aktual_budgeting      = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'budgeting')
            ->get()
            ->getRow();
        $total_budgeting = $aktual_budgeting->total ?? 0;

        $aktual_roas           = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'roas')
            ->get()
            ->getRow();
        $total_roas = $aktual_roas->total ?? 0;

        $aktual_feed_pl        = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'feed pl')
            ->get()
            ->getRow();
        $total_feed = $aktual_feed_pl->total ?? 0;

        $aktual_video          = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'video')
            ->get()
            ->getRow();
        $total_video = $aktual_video->total ?? 0;

        $aktual_feed_mingguan  = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'feed mingguan')
            ->get()
            ->getRow();
        $total_feed = $aktual_feed_mingguan->total ?? 0;

        $aktual_story          = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'story')
            ->get()
            ->getRow();
        $total_story = $aktual_story->total ?? 0;

        $aktual_testimoni      = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'testimoni')
            ->get()
            ->getRow();
        $total_testimoni = $aktual_testimoni->total ?? 0;

        $aktual_bug_minor      = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'bug minor')
            ->get()
            ->getRow();
        $total_bug_minor = $aktual_bug_minor->total ?? 0;

        $aktual_bug_operasional = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'operasional')
            ->get()
            ->getRow();
        $total_bug_operasional = $aktual_bug_operasional->total ?? 0;

        $aktual_ecommerce      = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'ecommerce')
            ->get()
            ->getRow();
        $total_ecommerce = $aktual_ecommerce->total ?? 0;

        $aktual_fitur          = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'operasional')
            ->get()
            ->getRow();
        $total_fitur = $aktual_fitur->total ?? 0;

        // $aktual_kehadiran = 150;
        $aktual_kehadiran = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', date('m'))
            ->where('YEAR(tanggal_penilaian)', date('Y'))
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'kehadiran')
            ->get()
            ->getRow();

        $totalKehadiran = $aktual_kehadiran->total ?? 0;

        $aktual_kebersihan = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', date('m'))
            ->where('YEAR(tanggal_penilaian)', date('Y'))
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'kebersihan')
            ->get()
            ->getRow();
        $totalKebersihan = $aktual_kebersihan->total ?? 0;

        $aktual_seragam = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', date('m'))
            ->where('YEAR(tanggal_penilaian)', date('Y'))
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'seragam')
            ->get()
            ->getRow();

        $totalSeragam = $aktual_seragam->total ?? 0;

        $aktual_sop = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', date('m'))
            ->where('YEAR(tanggal_penilaian)', date('Y'))
            ->where('pegawai_idpegawai', $selected_karyawan)
            ->where('aspek =', 'kepatuhan sop')
            ->get()
            ->getRow();
        $totalSop = $aktual_sop->total ?? 0;

        //persentas nilai
        $batas1 = $batas_awal[$unit];
        $batas2 = $batas_kedua[$unit];
        $batas3 = $batas_ketiga[$unit];
        $batas4 = $batas_keempat[$unit];

        $targetOmset = $target_omset[$unit];

        $aktual_operasional = 0;

        $insentif = 0;

        if ($jabatan == 41) {
            if ($aktual_omset <= $batas1) {
                $nilai_omset = 0;
            } elseif ($aktual_omset == $batas2) {
                $nilai_omset = 33;
            } elseif ($aktual_omset == $batas3) {
                $nilai_omset = 66;
            } elseif ($aktual_omset >= $batas4 && $aktual_omset < $targetOmset) {
                $nilai_omset = 100;
            } elseif ($aktual_omset >= $targetOmset) {
                $nilai_omset = 100;
                $insentif = (3 / 100) * $aktual_omset / 4;
            } else {
                $nilai_omset = (($aktual_omset - $batas1) / ($batas4 - $batas1)) * 100;
            }
        } elseif ($jabatan == 40) {

            $cabang_aman = 0;

            foreach ($aktual_omset_unit as $idUnit => $omset) {

                $batasCabang = $batas_keempat[$idUnit];

                if ($omset >= $batasCabang) {
                    $cabang_aman++;
                }

                // insentif jika target cabang tercapai
                if ($omset >= $target_omset[$idUnit]) {
                    $insentif += (5 / 1000) * $omset;
                }
            }

            switch ($cabang_aman) {
                case 1:
                    $nilai_omset = 33;

                    $aktual_operasional    = 33;

                    break;

                case 2:
                    $nilai_omset = 66;

                    $aktual_operasional    = 66;

                    break;

                case 3:
                    $nilai_omset = 100;

                    $aktual_operasional    = 100;

                    break;

                default:
                    $nilai_omset = 0;

                    $aktual_operasional    = 0;
                    break;
            }
        } elseif ($jabatan == 43) {

            $cabang_aman = 0;

            foreach ($aktual_omset_unit as $idUnit => $omset) {

                $batasCabang = $batas_keempat[$idUnit];

                if ($omset >= $batasCabang) {
                    $cabang_aman++;
                }

                // insentif jika target cabang tercapai
                if ($omset >= $target_omset[$idUnit]) {
                    $insentif += (1 / 100) * $omset;
                }
            }

            switch ($cabang_aman) {
                case 1:
                    $nilai_omset = 33;
                    break;

                case 2:
                    $nilai_omset = 66;
                    break;

                case 3:
                    $nilai_omset = 100;
                    break;

                default:
                    $nilai_omset = 0;
                    break;
            }
        } else {

            if ($aktual_omset < $batas2) {
                $nilai_omset = 0;
            } elseif ($aktual_omset >= $batas2 && $aktual_omset < $batas3) {
                $nilai_omset = 33;
            } elseif ($aktual_omset >= $batas3 && $aktual_omset < $batas4) {
                $nilai_omset = 66;
            } elseif ($aktual_omset >= $batas4 && $aktual_omset < $targetOmset) {
                $nilai_omset = 100;
            } elseif ($aktual_omset >= $targetOmset) {
                $nilai_omset = 100;
                $insentif = (3 / 100) * $aktual_omset / 4;
            } else {
                $nilai_omset = (($aktual_omset - $batas1) / ($batas4 - $batas1)) * 100;
            }
        }

        $nilai_customer = min(
            ($aktual_customer / $target['customer']) * 100,
            100
        );

        $nilai_closing = min(
            ($total_closing / $target['closing']) * 100,
            100
        );

        $nilai_upselling = min(
            ($total_upselling / $target['upselling']) * 100,
            100
        );

        $nilai_followup = min(
            ($total_followup / $target['followup']) * 100,
            100
        );

        $nilai_roas = $total_roas * 100;

        $nilai_tutup_kasir  = $total_tutup_kasir / 30 * 20;
        $nilai_opname       = $aktual_opname / 4 * 100;
        $nilai_absen        = $aktual_absen;

        $nilai_operasional  = $aktual_operasional;
        $nilai_divisi       = $total_divisi * 20;

        $rata_kebersihan    = $ttl_kebersihan * 20;
        $rata_seragam    = $ttl_seragam * 20;
        $rata_kepatuhan    = $ttl_kepatuhan * 20;

        $nilai_budgeting    = $total_budgeting * 100;

        $nilai_feed_pl      = $total_feed;
        $nilai_video        = $total_video;
        $nilai_feed_mingguan = $total_feed;
        $nilai_story        = $total_story;
        $nilai_testimoni    = $total_testimoni;

        $nilai_bug_minor    = $total_bug_minor / 4 * 20;
        $nilai_bug_operasional = $total_bug_operasional / 4 * 20;
        $nilai_ecommerce    = $total_ecommerce / 4 * 20;
        $nilai_fitur        = $total_fitur / 4 * 20;

        $nilai_kehadiran = $totalKehadiran / 26 * 20;
        $nilai_kebersihan = $totalKebersihan / 26 * 20;
        $nilai_seragam = $totalSeragam / 26 * 20;
        $nilai_sop = $totalSop / 26 * 20;

        //gaji sesuai jabatan

        $skor_total = 0;
        $skor_total2 = 0;
        $detail_kpi = [];
        $detail_absen = [];

        switch ($jabatan) {

            // ADMIN
            case 35:

                $detail_kpi = [
                    ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ['nama' => 'Tutup Kasir', 'bobot' => 10, 'nilai' => $nilai_tutup_kasir],
                    ['nama' => 'Stok Opname', 'bobot' => 10, 'nilai' => $nilai_opname],
                    ['nama' => 'Absensi', 'bobot' => 10, 'nilai' => $nilai_absen],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // TEKNISI
            case 36:

                $detail_kpi = [
                    ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ['nama' => 'Omset Teknisi', 'bobot' => 15, 'nilai' => $nilai_omset],
                    ['nama' => 'Customer Masuk', 'bobot' => 15, 'nilai' => $nilai_customer],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // KEPALA TOKO
            case 41:

                $detail_kpi = [
                    ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ['nama' => 'Total Customer', 'bobot' => 10, 'nilai' => $nilai_customer],
                    ['nama' => 'Tutup Kasir', 'bobot' => 10, 'nilai' => $nilai_tutup_kasir],
                    ['nama' => 'Opname', 'bobot' => 10, 'nilai' => $nilai_opname],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // SPV
            case 40:

                $detail_kpi = [
                    ['nama' => 'Omset Cabang', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ['nama' => 'Customer', 'bobot' => 10, 'nilai' => $nilai_customer],
                    ['nama' => 'Operasional', 'bobot' => 10, 'nilai' => $nilai_operasional],
                    ['nama' => 'Divisi', 'bobot' => 10, 'nilai' => $nilai_divisi],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $rata_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $rata_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $rata_kepatuhan],
                ];

                break;

            // CUSTOMER SERVICE
            case 42:

                $detail_kpi = [
                    ['nama' => 'Omset', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ['nama' => 'Closing', 'bobot' => 10, 'nilai' => $nilai_closing],
                    ['nama' => 'Upselling', 'bobot' => 10, 'nilai' => $nilai_upselling],
                    ['nama' => 'Follow Up', 'bobot' => 10, 'nilai' => $nilai_followup],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // PENGIKLAN
            case 43:

                $detail_kpi = [
                    ['nama' => 'Budgeting', 'bobot' => 15, 'nilai' => $nilai_budgeting],
                    ['nama' => 'ROAS', 'bobot' => 15, 'nilai' => $nilai_roas],
                    ['nama' => 'Omset', 'bobot' => 70, 'nilai' => $nilai_omset],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // MULTIMEDIA
            case 44:

                $detail_kpi = [
                    ['nama' => 'Omset Cabang', 'bobot' => 30, 'nilai' => $nilai_omset],
                    ['nama' => 'Feed PL', 'bobot' => 15, 'nilai' => $nilai_feed_pl],
                    ['nama' => 'Video', 'bobot' => 20, 'nilai' => $nilai_video],
                    ['nama' => 'Feed Mingguan', 'bobot' => 15, 'nilai' => $nilai_feed_mingguan],
                    ['nama' => 'Story', 'bobot' => 10, 'nilai' => $nilai_story],
                    ['nama' => 'Testimoni', 'bobot' => 10, 'nilai' => $nilai_testimoni],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            // IT
            case 45:

                $detail_kpi = [
                    ['nama' => 'Omset', 'bobot' => 30, 'nilai' => $nilai_omset],
                    ['nama' => 'Bug Minor', 'bobot' => 10, 'nilai' => $nilai_bug_minor],
                    ['nama' => 'Operasional', 'bobot' => 25, 'nilai' => $nilai_bug_operasional],
                    ['nama' => 'Ecommerce', 'bobot' => 15, 'nilai' => $nilai_ecommerce],
                    ['nama' => 'Fitur', 'bobot' => 20, 'nilai' => $nilai_fitur],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;

            case 45:

                $detail_kpi = [
                    ['nama' => 'Omset', 'bobot' => 30, 'nilai' => $nilai_omset],
                    ['nama' => 'Bug Minor', 'bobot' => 10, 'nilai' => $nilai_bug_minor],
                    ['nama' => 'Operasional', 'bobot' => 25, 'nilai' => $nilai_bug_operasional],
                    ['nama' => 'Ecommerce', 'bobot' => 15, 'nilai' => $nilai_ecommerce],
                    ['nama' => 'Fitur', 'bobot' => 20, 'nilai' => $nilai_fitur],
                ];

                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                ];

                break;
        }

        //total nilai

        foreach ($detail_kpi as $kpi) {
            $skor_total += ($kpi['nilai'] * $kpi['bobot']) / 100;
        }

        foreach ($detail_absen as $absen) {
            $skor_total2 += ($absen['nilai'] * $absen['bobot']) / 100;
        }

        $tunjangan_absen = $skor_total2 / 100 * 250000;

        if ($jabatan == 41) {
            $tunjangan_kinerja = $skor_total / 100 * 850000;
        } elseif ($jabatan == 40) {
            $tunjangan_kinerja = $skor_total / 100 * 1250000;
        } elseif ($jabatan == 43) {
            $tunjangan_kinerja = $skor_total / 100 * 1000000;
        } elseif ($jabatan == 35) {
            if ($unit == 1) {
                $tunjangan_kinerja = $skor_total / 100 * 850000;
            } else {
                $tunjangan_kinerja = $skor_total / 100 * 250000;
            }
        } else {
            $tunjangan_kinerja = $skor_total / 100 * 250000;
        }


        $gaji_pokok = 1500000;

        $gaji = $gaji_pokok + $tunjangan_kinerja + $tunjangan_absen + $akun->tunjangan_penempatan + $insentif;

        return view('template', [
            'list_karyawan'     => $list_karyawan,
            'selected_karyawan' => $selected_karyawan,
            'karyawan'          => $karyawan,
            'detail_kpi'        => $detail_kpi,
            'detail_absen'      => $detail_absen,
            'aktual_omset_unit' => $aktual_omset_unit,
            'skor_total'        => round($skor_total, 2),
            'tunjangan_kinerja' => $tunjangan_kinerja,
            'tunjangan_absen'   => $tunjangan_absen,
            'insentif'          => $insentif,
            'tunjangan_penempatan' => $akun,
            'gaji_pokok'        => $gaji_pokok,
            'gaji'              => $gaji,
            'bulan'             => $bulan,
            'tahun'             => $tahun,
            'body'              => 'penilaian/gaji'
        ]);
    }
}
