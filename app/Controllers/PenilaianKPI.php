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
