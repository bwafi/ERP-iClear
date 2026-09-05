<?php

namespace App\Controllers;

use App\Models\ModelPhone;
use App\Models\ModelPelanggan;
use IntlBreakIterator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelTemplatePenilaian;
use App\Models\ModelPenilaian;
use App\Models\ModelTemplateKpi;
use App\Models\ModelPenilaianDetail;

class Penilaian extends BaseController

{

    protected $PhoneModel;
    protected $PelangganModel;
    protected $AuthModel;
    protected $TemplatePenilaianModel;
    protected $PenilaianModel;
    protected $PenilaianDetailModel;
    protected $TemplateKpiModel;

    public function __construct()
    {
        $this->PhoneModel = new ModelPhone();
        $this->PelangganModel = new ModelPelanggan();
        $this->AuthModel = new ModelAuth();
        $this->TemplatePenilaianModel = new ModelTemplatePenilaian();
        $this->PenilaianModel = new ModelPenilaian();
        $this->PenilaianDetailModel = new ModelPenilaianDetail();
        $this->TemplateKpiModel = new ModelTemplateKpi();
    }

public function index()
{
    // DEPRECATED: modul checklist pekerjaan lama. Tidak dihapus, tapi akses
    // dialihkan ke /penilaian/absen yang baru.
    return redirect()->to('/penilaian/absen');

    $jumlahData = $this->PenilaianModel->getJumlahByTemplatePenilaian();
    $jumlahMap = [];
    foreach ($jumlahData as $row) {
        $jumlahMap[$row->idtemplate_penilaian] = $row->jumlah;
    }

    $data = array(
        'template'   => $this->TemplatePenilaianModel->getTemplatePenilaian(),
        'penilaian'  => $this->PenilaianModel->where('MONTH(tanggal_penilaian)', date('m'))->where('YEAR(tanggal_penilaian)', date('Y'))->getPenilaian(),
        'jumlahMap'  => $jumlahMap,
        'akun'       => $this->AuthModel->getdataakun(),
        'body'       => 'penilaian/penilaian',
    );

    return view('template', $data);
}

/**
 * SPV mengisi Kualitas Pelayanan & Kontrol Aset Kepala Toko
 * pada unit milik SPV (hanya unit sendiri).
 *
 * Data disimpan ke kpi_evaluations via KpiEvaluationService
 * (normalized_score = persentase 0-100).
 */
public function spv_kpi_index()
{
    $session = session();
    $myUnit  = (int)$session->get('ID_UNIT');
    $myRole  = (int)$session->get('ID_JABATAN');

    $bulan = $this->request->getGet('bulan') ?: date('m');
    $tahun = $this->request->getGet('tahun') ?: date('Y');

    // Kepala Toko (41) pada unit SPV
    $kepalaTokos = $this->AuthModel
        ->where('ID_JABATAN', 41)
        ->where('ID_UNIT', $myUnit)
        ->where('STATUS_PEGAWAI', 1)
        ->findAll();

    // Ambil nilai evaluasi yang sudah ada utk periode tsb (raw_score 1-5)
    $evaluationModel = new \App\Models\ModelKpiEvaluation();
    $kualitas = [];
    foreach ($kepalaTokos as $kt) {
        $kualitas[$kt->ID_AKUN] = $this->getRawScore($evaluationModel, $kt->ID_AKUN, 'KUALITAS_PELAYANAN', $bulan, $tahun);
    }

    return view('template', [
        'myRole'       => $myRole,
        'myUnit'       => $myUnit,
        'bulan'        => $bulan,
        'tahun'        => $tahun,
        'kepalaTokos'  => $kepalaTokos,
        'kualitas'     => $kualitas,
        'body'         => 'penilaian/spv_kpi',
    ]);
}

protected function getRawScore($model, int $employeeId, string $code, string $month, string $year): ?int
{
    $componentModel = new \App\Models\ModelKpiComponent();
    $component = $componentModel->where('code', $code)->first();
    if (!$component) {
        return null;
    }
    $row = $model->where('employee_id', $employeeId)
        ->where('kpi_component_id', (int)$component->id)
        ->where('period_year', (int)$year)
        ->where('period_month', (int)$month)
        ->orderBy('evaluation_date', 'DESC')
        ->first();
    return $row ? (int)$row->raw_score : null;
}

public function save_spv_kpi()
{
    $session = session();
    $evaluatorId = (int)$session->get('ID_AKUN');
    $myUnit      = (int)$session->get('ID_UNIT');

    $employeeId = (int)$this->request->getPost('employee_id');
    $kualitas   = (int)$this->request->getPost('kualitas_pelayanan'); // 1 - 5
    $bulan      = $this->request->getPost('bulan') ?: date('m');
    $tahun      = $this->request->getPost('tahun') ?: date('Y');
    $evaluationDate = sprintf('%04d-%02d-15', (int)$tahun, (int)$bulan);

    // Hanya boleh menilai Kepala Toko di UNIT SENDIRI
    $employee = (new ModelAuth())->where('ID_AKUN', $employeeId)->first();
    if (!$employee || (int)$employee->ID_UNIT !== $myUnit) {
        return redirect()->to('/penilaian/spv_kpi')->with('error', 'Anda tidak berhak menilai di unit lain.');
    }

    // Hanya evaluator yang berhak (SPV -> Kepala Toko) yang boleh mengisi.
    if (!\App\Services\Kpi\EvaluatorAuthorizationService::canEvaluate($evaluatorId, $employeeId)) {
        return redirect()->to('/penilaian/spv_kpi')->with('error', 'Anda tidak berwenang menilai KPI ini.');
    }

    $evaluationService = new \App\Services\Kpi\KpiEvaluationService();
    $componentModel = new \App\Models\ModelKpiComponent();
    $errors = [];

    if ($kualitas >= 1 && $kualitas <= 5) {
        $kualitasComponent = $componentModel->where('code', 'KUALITAS_PELAYANAN')->first();
        if ($kualitasComponent) {
            $result = $evaluationService->recordEvaluation([
                'employee_id'      => $employeeId,
                'kpi_component_id' => (int)$kualitasComponent->id,
                'evaluator_id'     => $evaluatorId,
                'evaluation_date'  => $evaluationDate,
                'raw_score'        => $kualitas,
                'max_score'        => 5,
                'notes'            => 'Kualitas Pelayanan (Skor: ' . $kualitas . '/5)',
            ]);
            if (!$result['success']) {
                $errors[] = 'Kualitas Pelayanan: ' . implode(', ', $result['errors']);
            }
        }
    }

    if (!empty($errors)) {
        return redirect()->to('/penilaian/spv_kpi?bulan=' . $bulan . '&tahun=' . $tahun)
            ->with('error', implode(' | ', $errors));
    }

    return redirect()->to('/penilaian/spv_kpi?bulan=' . $bulan . '&tahun=' . $tahun)
        ->with('success', 'Penilaian Kualitas Pelayanan Kepala Toko berhasil disimpan.');
}

public function get_template_by_jabatan($idjabatan)
{
    $template = $this->TemplatePenilaianModel
        ->select('
            template_penilaian.idtemplate_penilaian,
            template_penilaian.aspek_penilaian,
            template_penilaian.keterangan_penilaian,
            template_penilaian.jabatan_idjabatan,
            template_penilaian.idtemplate_kpi,
            template_penilaian.target,
            template_penilaian.bobot,
            template_kpi.status,
            template_kpi.template_kpi AS aspek_kpi
        ')
        ->join('template_kpi', 'template_kpi.idtemplate_kpi = template_penilaian.idtemplate_kpi')
        ->where('template_penilaian.jabatan_idjabatan', $idjabatan)
        ->findAll();

    return $this->response->setJSON($template);
}


    // public function get_template_by_jabatan($idjabatan)
    // {
    //     $template = $this->TemplatePenilaianModel
    //         ->select('
    //     template_penilaian.idtemplate_penilaian,
    //     template_penilaian.aspek_penilaian,
    //     template_penilaian.keterangan_penilaian,
    //     template_penilaian.jabatan_idjabatan,
    //     template_penilaian.idtemplate_kpi,
    //     template_kpi.status,
    //     template_kpi.template_kpi AS aspek_kpi,
    //     template_kpi.target
    // ')
    //         ->join('template_kpi', 'template_kpi.idtemplate_kpi = template_penilaian.idtemplate_kpi')
    //         ->where('template_penilaian.jabatan_idjabatan', $idjabatan)
    //         ->findAll();
    //     return $this->response->setJSON($template);
    // }

public function insert_penilaian()
{
    $akun = session('ID_AKUN');
    $pegawai_idpegawai = $this->request->getPost('pegawai_idpegawai');
    $tanggal_penilaian = $this->request->getPost('tanggal_penilaian');
    
    $skor3 = $this->request->getPost('skor3');

    $aspek3 = $this->request->getPost('aspek3');

    $idTempKpi2 = $this->request->getPost('idtempkpi2');
    $skor2      = $this->request->getPost('skor2');

    // Ambil nama KPI dari template
    $kpi2 = $this->TemplateKpiModel->getById($idTempKpi2[0] ?? null);
    $aspek2 = $kpi2 ? $kpi2->template_kpi : 'Aspek KPI';

    // Hitung total skor
    $totalSkorAkhir2 = 0;
    if (is_array($skor2)) {
        foreach ($skor2 as $val) {
            $totalSkorAkhir2 += isset($val) ? (float) $val : 0;
        }
    }

    // Tentukan keterangan
    if ($skor3 == 1) {
        $keterangan2 = 'Skor Kinerja sangat kurang';
    } elseif ($skor3 == 2) {
        $keterangan2 = 'Skor Kinerja kurang';
    } elseif ($skor3 == 3) {
        $keterangan2 = 'Skor Kinerja cukup';
    } elseif ($skor3 == 4) {
        $keterangan2 = 'Skor Kinerja baik';
    } else {
        $keterangan2 = 'Skor Kinerja sangat baik';
    }

    // Simpan ke tabel penilaian (utama)
    $data2 = [
        'aspek'             => $aspek3,
        'keterangan'        => $keterangan2,
        'skor'              => $skor3,
        'pegawai_idpegawai' => $pegawai_idpegawai,
        'input_by'          => $akun,
        'tanggal_penilaian' => $tanggal_penilaian
    ];
    $idPenilaian = $this->PenilaianModel->insertPenilaian($data2); // <-- dapatkan ID insert

    // Simpan ke tabel penilaian_detail
$template_ids2 = $this->request->getPost('template_ids2');
$bobot2        = $this->request->getPost('bobot2');
$target2       = $this->request->getPost('target2');

$detailData = [];

if (is_array($template_ids2) && is_array($skor2)) {
    foreach ($template_ids2 as $index => $templateId) {
        $detailData[] = [
            'template_penilaian_idtemplate_penilaian' => $templateId,
            'bobot'              => $bobot2[$index] ?? 0,
            'target'             => $target2[$index] ?? 0,
            'skor'               => $skor2[$index] ?? 0,
            'pegawai_idpegawai'  => $pegawai_idpegawai,
            'tanggal_penilaian'  => $tanggal_penilaian,
            'penilaian_idpenilaian' => $idPenilaian, // <-- link ke penilaian utama
            'created_on'         => date('Y-m-d H:i:s')
        ];
    }
}

if (!empty($detailData)) {
    $this->PenilaianDetailModel->insertBatch($detailData);
}


    session()->setFlashData('sukses', 'Data Berhasil Ditambahkan');
    return redirect()->to(base_url('penilaian'));
}

    // public function insert_penilaian()
    // {

    //     $pegawai_idpegawai = $this->request->getPost('pegawai_idpegawai');
    //     $tanggal_penilaian = $this->request->getPost('tanggal_penilaian');
    //     $idTempKpi1  = $this->request->getPost('idtempkpi1[1]');
    //     $kpi1 = $this->TemplateKpiModel->getById($idTempKpi1);
    //     $aspek1 = $kpi1->template_kpi;
    //     $skor1       = $this->request->getPost('skor1');
    //     $totalSkor1 = array_sum($skor1);
    //     $keterangan1 = '';

    //     if ($totalSkor1 >= 1 && $totalSkor1 <= 10) {
    //         $keterangan1 = 'Skor Kinerja buruk';
    //     } elseif ($totalSkor1 >= 11 && $totalSkor1 <= 20) {
    //         $keterangan1 = 'Skor Kinerja cukup baik';
    //     } elseif ($totalSkor1 >= 21 && $totalSkor1 <= 25) {
    //         $keterangan1 = 'Skor Kinerja baik';
    //     }




    //     $idTempKpi2  = $this->request->getPost('idtempkpi2[1]');
    //     $templateIds2 = $this->request->getPost('template_ids2');
    //     $skor2       = $this->request->getPost('skor2');
    //     $kpi2 = $this->TemplateKpiModel->getById($idTempKpi2);
    //     $aspek2 = $kpi2->template_kpi;

    //     $totalSkorAkhir2 = 0;
    //     $semuaSkorAkhir2 = [];

    //     foreach ($templateIds2 as $i => $id) {
    //         $datatemplatepenilaian = $this->TemplatePenilaianModel->getById($id);

    //         if (!$datatemplatepenilaian) {
    //             continue;
    //         }

    //         $target = (float) $datatemplatepenilaian->target;
    //         $realisasi = isset($skor2[$i]) ? (float) $skor2[$i] : 0;

    //         if ($target > 0) {
    //             $skorAkhir2 = $realisasi / $target;


    //             if ($skorAkhir2 > 1) {
    //                 $skorAkhir2 = 1;
    //             }
    //         } else {
    //             $skorAkhir2 = 0;
    //         }

    //         $semuaSkorAkhir2[] = $skorAkhir2;
    //     }

    //     $totalSkorAkhir2 = array_sum($semuaSkorAkhir2);

    //     $keterangan2 = '';

    //     if ($totalSkorAkhir2 >= 0 && $totalSkorAkhir2 <= 1.5) {
    //         $keterangan2 = 'Skor Kinerja buruk';
    //     } elseif ($totalSkorAkhir2 >= 1.6 && $totalSkorAkhir2 <= 4.0) {
    //         $keterangan2 = 'Skor Kinerja cukup baik';
    //     } elseif ($totalSkorAkhir2 >= 4.0 && $totalSkorAkhir2 <= 5.0) {
    //         $keterangan2 = 'Skor Kinerja baik';
    //     }


    //     $data = array(
    //         'aspek' => $aspek1,
    //         'keterangan' => $keterangan1,
    //         'skor' => $totalSkor1,
    //         'pegawai_idpegawai' => $pegawai_idpegawai,
    //         'tanggal_penilaian' => $tanggal_penilaian

    //     );


    //     $data2 = array(

    //         'aspek' => $aspek2,
    //         'keterangan' => $keterangan2,
    //         'skor' => $totalSkorAkhir2,
    //         'pegawai_idpegawai' => $pegawai_idpegawai,
    //         'tanggal_penilaian' => $tanggal_penilaian
    //     );


    //     $this->PenilaianModel->insertPenilaian($data);
    //     $this->PenilaianModel->insertPenilaian($data2);
    //     session()->setFlashData('sukses', 'Data Berhasil Ditambahkan');
    //     return redirect()->to(base_url('penilaian'));
    // }


    // public function update_penilaian()
    // {

    //     $aspek = $this->request->getPost('aspek');
    //     $keterangan = $this->request->getPost('keterangan');
    //     $skor = $this->request->getPost('skor');
    //     $pegawai_idpegawai = $this->request->getPost('pegawai_idpegawai');
    //     $tanggal_penilaian = $this->request->getPost('tanggal_penilaian');
    //     $idpenilaian = $this->request->getPost('idpenilaian');

    //     $data = array(
    //         'aspek' => $aspek,
    //         'keterangan' => $keterangan,
    //         'skor' => $skor,
    //         'pegawai_idpegawai' => $pegawai_idpegawai,
    //         'tanggal_penilaian' => $tanggal_penilaian
    //     );
    //     $this->PenilaianModel->update($idpenilaian, $data);
    //     session()->setFlashData('sukses', 'Data Berhasil Diupdate');
    //     return redirect()->to(base_url('penilaian'));
    // }

    public function delete_penilaian()
    {
        $idpenilaian = $this->request->getPost('idpenilaian');

        $db = \Config\Database::connect();
        $db->table('penilaian')
            ->where('idpenilaian', $idpenilaian)
            ->delete();
        session()->setFlashData('sukses', 'Data Berhasil Dihapus');
        return redirect()->to(base_url('penilaian'));
    }

    public function export_penilaian()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $tanggal_awal  = $this->request->getPost('tanggal_awal');
        $tanggal_akhir = $this->request->getPost('tanggal_akhir');

        $penilaianModel = new ModelPenilaian();
        $datapenilaian = $penilaianModel->getPenilaianByTanggal($tanggal_awal, $tanggal_akhir);

        // Set Header Excel
        $headers = [
            'A1' => 'Nama Akun',
            'B1' => 'Aspek',
            'C1' => 'Keterangan',
            'D1' => 'Skor',
            'E1' => 'Tanggal Penilaian',
            'F1' => 'Dibuat Pada',
            'G1' => 'Diupdate Pada',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');

        $row = 2;
        foreach ($datapenilaian as $item) {
            $sheet->setCellValue('A' . $row, $item->NAMA_AKUN ?? '-');
            $sheet->setCellValue('B' . $row, $item->aspek);
            $sheet->setCellValue('C' . $row, $item->keterangan);
            $sheet->setCellValue('D' . $row, $item->skor);
            $sheet->setCellValue('E' . $row, date('d-m-Y', strtotime($item->tanggal_penilaian)));
            $sheet->setCellValue('F' . $row, date('d-m-Y H:i:s', strtotime($item->created_on ?? '-')));
            $sheet->setCellValue('G' . $row, date('d-m-Y H:i:s', strtotime($item->updated_on ?? '-')));
            $row++;
        }

        // Auto width
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Border
        $sheet->getStyle('A1:G' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->freezePane('A2');

        $filename = 'Data_Penilaian_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}