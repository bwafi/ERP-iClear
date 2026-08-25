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

    /**
     * =========================================================================
     * HELPER GAJI/KPI — HASIL REFACTOR
     * =========================================================================
     * Sebelumnya, logic ini (target per unit, batas omset, query omset/customer/
     * HPP/absen, perhitungan nilai & skor per jabatan) di-copy-paste hampir
     * identik di 3 fungsi: penilaian_kinerja(), slip_gaji(), dan gaji().
     * Sekarang disatukan di sini. Perbedaan kecil antar 3 fungsi asal (misal
     * target 'atas_customer', batas unit ke-4, cara hitung $nilai_absen, dan
     * pembagi insentif) dipertahankan lewat parameter $context supaya perilaku
     * tiap halaman TIDAK berubah dari sebelumnya.
     *
     * @param int|string $idAkun  ID akun yang dihitung gajinya
     * @param string     $bulan   Bulan (2 digit, mis. '08')
     * @param string     $tahun   Tahun (4 digit, mis. '2026')
     * @param string     $context 'penilaian_kinerja' | 'slip_gaji' | 'gaji'
     * @return array
     */
    private function hitungKPIGaji($idAkun, $bulan, $tahun, string $context = 'penilaian_kinerja'): array
    {
        $karyawan = $this->db->table('akun')
            ->where('ID_AKUN', $idAkun)
            ->get()
            ->getRowArray();

        $jabatan = $karyawan['ID_JABATAN'] ?? null;
        $unit    = $karyawan['ID_UNIT'] ?? null;

        $query = $this->db->query("
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
            WHERE ID_AKUN = ?
        ", [$idAkun]);

        $akun = $query->getRow();
        $akun->tunjangan_penempatan = ($akun->penempatan == 0) ? 350000 : 0;

        // ---------------------------------------------------------------
        // TARGET PER UNIT
        // Catatan: nilai 'atas_customer' & 'bawah_customer' hanya dipakai
        // di context 'penilaian_kinerja' & 'slip_gaji'. Context 'gaji'
        // pakai skema target lama (tanpa atas/bawah), sesuai kode asal.
        // ---------------------------------------------------------------
        if ($context === 'gaji') {
            $target_unit = [
                1 => ['customer' => 130, 'closing' => 111, 'upselling' => 14, 'followup' => 100, 'roas' => 5],
                2 => ['customer' => 118, 'closing' => 96,  'upselling' => 14, 'followup' => 80,  'roas' => 4],
                3 => ['customer' => 210, 'closing' => 188, 'upselling' => 27, 'followup' => 60,  'roas' => 3],
                4 => ['customer' => 1,   'closing' => 1,   'upselling' => 1,  'followup' => 1,   'roas' => 1],
            ];

            $batas_awal    = [1 => 30000000, 2 => 18000000, 3 => 40000000, 4 => 18000000];
            $batas_kedua   = [1 => 35000000, 2 => 22000000, 3 => 45000000, 4 => 22000000];
            $batas_ketiga  = [1 => 40000000, 2 => 26000000, 3 => 50000000, 4 => 26000000];
            $batas_keempat = [1 => 45000000, 2 => 30000000, 3 => 55000000, 4 => 30000000];
            $target_omset  = [1 => 50000000, 2 => 35000000, 3 => 60000000, 4 => 35000000];
        } else {
            $target_unit = [
                1 => ['customer' => 130, 'atas_customer' => 220, 'bawah_customer' => 150, 'closing' => 111, 'upselling' => 14, 'followup' => 100, 'roas' => 5],
                2 => ['customer' => 118, 'atas_customer' => 180, 'bawah_customer' => 150, 'closing' => 96,  'upselling' => 14, 'followup' => 80,  'roas' => 4],
                3 => ['customer' => 210, 'atas_customer' => 350, 'bawah_customer' => 250, 'closing' => 188, 'upselling' => 27, 'followup' => 60,  'roas' => 3],
                4 => ['customer' => 118, 'atas_customer' => 250, 'bawah_customer' => 200, 'closing' => 96,  'upselling' => 14, 'followup' => 80,  'roas' => 5],
            ];

            $batas_awal    = [1 => 35000000, 2 => 18000000, 3 => 40000000, 4 => 35000000];
            $batas_kedua   = [1 => 40000000, 2 => 22000000, 3 => 45000000, 4 => 40000000];
            $batas_ketiga  = [1 => 45000000, 2 => 26000000, 3 => 50000000, 4 => 45000000];
            $batas_keempat = [1 => 50000000, 2 => 30000000, 3 => 55000000, 4 => 50000000];
            $target_omset  = [1 => 55000000, 2 => 35000000, 3 => 60000000, 4 => 55000000];
        }

        $target = $target_unit[$unit] ?? $target_unit[1];

        // ---------------------------------------------------------------
        // OMSET & CUSTOMER PER CABANG (1-4)
        // ---------------------------------------------------------------
        $aktual_omset_unit = [];
        $aktual_customer_unit = [];

        foreach ([1, 2, 3, 4] as $idUnit) {
            $aktual_omset_unit[$idUnit] = $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', $bulan)
                ->where('YEAR(penjualan.tanggal)', $tahun)
                ->where('penjualan.unit_idunit =', $idUnit)
                ->get()
                ->getRow()
                ->total ?? 0;

            if ($context === 'gaji') {
                $aktual_customer_unit[$idUnit] = $this->db->table('penjualan')
                    ->select('COUNT(idpenjualan) AS total')
                    ->where('MONTH(tanggal)', $bulan)
                    ->where('YEAR(tanggal)', $tahun)
                    ->where('unit_idunit =', $idUnit)
                    ->get()
                    ->getRow()
                    ->total ?? 0;
            } else {
                $aktual_customer_unit[$idUnit] = $this->db->table('penjualan')
                    ->select('COUNT(kode_invoice) AS total')
                    ->where('MONTH(tanggal)', $bulan)
                    ->where('YEAR(tanggal)', $tahun)
                    ->where('unit_idunit', $idUnit)
                    ->get()
                    ->getRow()
                    ->total ?? 0;
            }
        }

        $aktual_omset = $aktual_omset_unit[$unit] ?? 0;
        $total_customer = $aktual_customer_unit[$unit] ?? 0;

        // HPP per cabang (dipakai untuk nilai_hpp / nilai_hpp_global di context non-'gaji')
        $aktual_hpp = [];
        foreach ([1, 2, 3, 4] as $idUnit) {
            $aktual_hpp[$idUnit] = $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', date('m'))
                ->where('YEAR(penjualan.tanggal)', date('Y'))
                ->where('penjualan.unit_idunit =', $idUnit)
                ->get()
                ->getRow()
                ->total ?? 0;
        }
        $total_hpp = $aktual_hpp[1] + $aktual_hpp[2] + $aktual_hpp[3] + $aktual_hpp[4];
        $totalomset = ($aktual_omset_unit[1] + $aktual_omset_unit[2] + $aktual_omset_unit[3] + $aktual_omset_unit[4]) ?: 1;
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

        $nilai_hpp = 0;
        foreach ($aktual_hpp as $idUnit => $hpp) {
            $omset = $aktual_omset_unit[$idUnit] ?? 0;
            if ($omset == 0) {
                continue;
            }
            $persentase = ($hpp / $omset) * 100;
            if ($persentase <= 35) {
                $nilai_hpp += 25;
            } elseif ($persentase <= 40) {
                $nilai_hpp += 18.75;
            } elseif ($persentase <= 45) {
                $nilai_hpp += 12.5;
            }
        }

        // ---------------------------------------------------------------
        // METRIK LAIN (tutup kasir, opname, penilaian per-aspek)
        // ---------------------------------------------------------------
        $total_tutup_kasir = $this->db->table('tutup_kasir')
            ->select('COUNT(status) AS total')
            ->where('MONTH(tanggal)', $bulan)
            ->where('YEAR(tanggal)', $tahun)
            ->where('unit', $unit)
            ->get()
            ->getRow()
            ->total ?? 0;

        $aktual_opname = $this->db->table('stok_opname_draft')
            ->select('COUNT(DISTINCT DATE(tanggal)) AS total')
            ->where('unit_idunit', $unit)
            ->where('MONTH(tanggal)', $bulan)
            ->where('YEAR(tanggal)', $tahun)
            ->get()
            ->getRow()
            ->total ?? 0;

        // Rata-rata (divisi/kebersihan/seragam/kepatuhan) — global, bukan per pegawai
        $total_divisi = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->get()->getRow()->total ?? 0;

        $ttl_kebersihan = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek =', 'kebersihan')
            ->get()->getRow()->total ?? 0;

        $ttl_seragam = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek =', 'seragam')
            ->get()->getRow()->total ?? 0;

        $ttl_kepatuhan = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek =', 'kepatuhan sop')
            ->get()->getRow()->total ?? 0;

        // Skor per-pegawai per aspek
        $aspekSumList = [
            'closing', 'upselling', 'followup', 'budgeting', 'roas',
            'feed pl', 'video', 'feed mingguan', 'story', 'testimoni',
            'bug minor', 'operasional', 'ecommerce',
        ];

        // NB: 'followup' vs 'follow up' berbeda di context penilaian_kinerja/slip_gaji
        $followupAspek = ($context === 'penilaian_kinerja' || $context === 'slip_gaji') ? 'follow up' : 'followup';

        $sumAspek = function (string $aspek) use ($bulan, $tahun, $idAkun) {
            $r = $this->db->table('penilaian')
                ->select('SUM(skor) AS total')
                ->where('MONTH(tanggal_penilaian)', $bulan)
                ->where('YEAR(tanggal_penilaian)', $tahun)
                ->where('pegawai_idpegawai', $idAkun)
                ->where('aspek =', $aspek)
                ->get()
                ->getRow();
            return $r->total ?? 0;
        };

        $total_closing      = $sumAspek('closing');
        $total_upselling    = $sumAspek('upselling');
        $total_followup     = $sumAspek($followupAspek);
        $total_budgeting    = $sumAspek('budgeting');
        $total_roas         = $sumAspek('roas');
        $total_feed         = $sumAspek('feed mingguan') ?: $sumAspek('feed pl');
        $total_video        = $sumAspek('video');
        $total_story        = $sumAspek('story');
        $total_testimoni    = $sumAspek('testimoni');
        $total_bug_minor    = $sumAspek('bug minor');
        $total_bug_operasional = $sumAspek('operasional');
        $total_ecommerce    = $sumAspek('ecommerce');
        $total_fitur        = $sumAspek('operasional');

        $totalKehadiran  = $sumAspek('kehadiran');
        $totalKebersihan = $sumAspek('kebersihan');
        $totalSeragam    = $sumAspek('seragam');
        $totalSop        = $sumAspek('kepatuhan sop');

        // Absen: context 'gaji' pakai hardcode 90 (perilaku asli dipertahankan)
        if ($context === 'gaji') {
            $nilai_absen = 90;
        } else {
            $total_absen = $this->db->table('penilaian')
                ->select('Avg(skor) AS total')
                ->where('MONTH(tanggal_penilaian)', $bulan)
                ->where('YEAR(tanggal_penilaian)', $tahun)
                ->where('aspek =', 'kehadiran')
                ->get()->getRow()->total ?? 0;
            $nilai_absen = $total_absen * 20;
        }

        // ---------------------------------------------------------------
        // NILAI OMSET (berbeda per jabatan & context)
        // ---------------------------------------------------------------
        $batas1 = $batas_awal[$unit];
        $batas2 = $batas_kedua[$unit];
        $batas3 = $batas_ketiga[$unit];
        $batas4 = $batas_keempat[$unit];
        $targetOmset = $target_omset[$unit];

        $aktual_operasional = 0;
        $insentif = 0;
        $nilai_omset = 0;

        // Pembagi insentif kepala toko (41) & operasional cabang berbeda antar context
        $pembagiInsentifKepalaToko = ($context === 'gaji') ? 4 : 3;
        $pembagiInsentifPengiklan  = ($context === 'gaji') ? 1 : 4; // asal: gaji pakai *omset penuh, lainnya /4

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
                $insentif = (3 / 100) * $aktual_omset / $pembagiInsentifKepalaToko;
            } else {
                $nilai_omset = (($aktual_omset - $batas1) / ($batas4 - $batas1)) * 100;
            }
        } elseif ($jabatan == 40) {
            $cabang_aman = 0;
            foreach ($aktual_omset_unit as $idUnit => $omset) {
                if ($omset >= $batas_keempat[$idUnit]) {
                    $cabang_aman++;
                }
                if ($omset >= $target_omset[$idUnit]) {
                    $insentif += (5 / 1000) * $omset;
                }
            }

            if ($context === 'gaji') {
                switch ($cabang_aman) {
                    case 1: $nilai_omset = 33; $aktual_operasional = 33; break;
                    case 2: $nilai_omset = 66; $aktual_operasional = 66; break;
                    case 3: $nilai_omset = 100; $aktual_operasional = 100; break;
                    default: $nilai_omset = 0; $aktual_operasional = 0; break;
                }
            } else {
                switch ($cabang_aman) {
                    case 1: $nilai_omset = 25; $aktual_operasional = 25; break;
                    case 2: $nilai_omset = 50; $aktual_operasional = 50; break;
                    case 3: $nilai_omset = 75; $aktual_operasional = 75; break;
                    case 4: $nilai_omset = 100; $aktual_operasional = 100; break;
                    default: $nilai_omset = 0; $aktual_operasional = 0; break;
                }
            }
        } elseif ($jabatan == 43) {
            $cabang_aman = 0;
            foreach ($aktual_omset_unit as $idUnit => $omset) {
                if ($omset >= $batas_keempat[$idUnit]) {
                    $cabang_aman++;
                }
                if ($omset >= $target_omset[$idUnit]) {
                    $insentif += (1 / 100) * $omset / $pembagiInsentifPengiklan;
                }
            }

            if ($context === 'gaji') {
                switch ($cabang_aman) {
                    case 1: $nilai_omset = 33; break;
                    case 2: $nilai_omset = 66; break;
                    case 3: $nilai_omset = 100; break;
                    default: $nilai_omset = 0; break;
                }
            } else {
                switch ($cabang_aman) {
                    case 1: $nilai_omset = 25; $aktual_operasional = 25; break;
                    case 2: $nilai_omset = 50; $aktual_operasional = 50; break;
                    case 3: $nilai_omset = 75; $aktual_operasional = 75; break;
                    case 4: $nilai_omset = 100; $aktual_operasional = 100; break;
                    default: $nilai_omset = 0; $aktual_operasional = 0; break;
                }
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
                $insentif = (3 / 100) * $aktual_omset / $pembagiInsentifKepalaToko;
            } else {
                $nilai_omset = (($aktual_omset - $batas1) / ($batas4 - $batas1)) * 100;
            }
        }

        // ---------------------------------------------------------------
        // NILAI CUSTOMER
        // context 'gaji' pakai target['customer'] langsung (persentase capped 100).
        // context lain pakai skema 'cabang aman' terhadap atas_customer.
        // ---------------------------------------------------------------
        if ($context === 'gaji') {
            $nilai_customer = min(($total_customer / $target['customer']) * 100, 100);
        } else {
            $customer_aman = 0;
            foreach ($aktual_customer_unit as $idUnit => $customer) {
                if ($customer >= $target['atas_customer']) {
                    $customer_aman++;
                }
            }
            switch ($customer_aman) {
                case 1: $nilai_customer = 25; break;
                case 2: $nilai_customer = 50; break;
                case 3: $nilai_customer = 75; break;
                case 4: $nilai_customer = 100; break;
                default: $nilai_customer = 0; break;
            }
        }

        $nilai_closing   = min(($total_closing / $target['closing']) * 100, 100);
        $nilai_upselling = min(($total_upselling / $target['upselling']) * 100, 100);
        $nilai_followup  = min(($total_followup / $target['followup']) * 100, 100);
        $nilai_roas      = $total_roas * ($context === 'gaji' ? 100 : 20);
        $nilai_budgeting = $total_budgeting * ($context === 'gaji' ? 100 : 20);

        $nilai_tutup_kasir = ($context === 'gaji')
            ? ($total_tutup_kasir / 30 * 20)
            : min(($total_tutup_kasir / 30) * 100, 100);

        $nilai_opname = $aktual_opname / 4 * 100;

        $nilai_operasional = $aktual_operasional;
        $nilai_divisi = $total_divisi * 20;
        $rata_kebersihan = $ttl_kebersihan * 20;
        $rata_seragam = $ttl_seragam * 20;
        $rata_kepatuhan = $ttl_kepatuhan * 20;

        $nilai_feed_pl = $total_feed;
        $nilai_video = $total_video;
        $nilai_feed_mingguan = $total_feed;
        $nilai_story = $total_story;
        $nilai_testimoni = $total_testimoni;

        $nilai_bug_minor = $total_bug_minor / 4 * 20;
        $nilai_bug_operasional = $total_bug_operasional / 4 * 20;
        $nilai_ecommerce = $total_ecommerce / 4 * 20;
        $nilai_fitur = $total_fitur / 4 * 20;

        $nilai_kehadiran = $totalKehadiran / 26 * 20;
        $nilai_kebersihan = $totalKebersihan / 26 * 20;
        $nilai_seragam = $totalSeragam / 26 * 20;
        $nilai_sop = $totalSop / 26 * 20;

        // ---------------------------------------------------------------
        // DETAIL KPI + ABSEN PER JABATAN
        // ---------------------------------------------------------------
        $detail_kpi = [];
        $detail_absen = [
            ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
            ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
            ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
            ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
        ];

        switch ($jabatan) {
            case 35: // ADMIN
                $detail_kpi = [
                    ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ['nama' => 'Tutup Kasir', 'bobot' => 10, 'nilai' => $nilai_tutup_kasir],
                    ['nama' => 'Stok Opname', 'bobot' => 10, 'nilai' => $nilai_opname],
                    ['nama' => 'Absensi', 'bobot' => 10, 'nilai' => $nilai_absen],
                ];
                break;

            case 36: // TEKNISI
                $detail_kpi = [
                    ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ['nama' => 'Omset Teknisi', 'bobot' => 15, 'nilai' => $nilai_omset],
                    ['nama' => 'Customer Masuk', 'bobot' => 15, 'nilai' => $nilai_customer],
                ];
                break;

            case 41: // KEPALA TOKO
                $detail_kpi = [
                    ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ['nama' => 'Total Customer', 'bobot' => 10, 'nilai' => $nilai_customer],
                    ['nama' => 'Tutup Kasir', 'bobot' => 10, 'nilai' => $nilai_tutup_kasir],
                    ['nama' => 'Opname', 'bobot' => 10, 'nilai' => $nilai_opname],
                ];
                break;

            case 40: // SPV
                $bobotOmsetSpv = ($context === 'gaji') ? 70 : 10;
                $bobotCustomerSpv = ($context === 'gaji') ? 10 : 70;
                $detail_kpi = [
                    ['nama' => 'Omset Cabang', 'bobot' => $bobotOmsetSpv, 'nilai' => $nilai_omset],
                    ['nama' => 'Customer', 'bobot' => $bobotCustomerSpv, 'nilai' => $nilai_customer],
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

            case 42: // CUSTOMER SERVICE
                if ($context === 'gaji') {
                    $detail_kpi = [
                        ['nama' => 'Omset', 'bobot' => 70, 'nilai' => $nilai_omset],
                        ['nama' => 'Closing', 'bobot' => 10, 'nilai' => $nilai_closing],
                        ['nama' => 'Upselling', 'bobot' => 10, 'nilai' => $nilai_upselling],
                        ['nama' => 'Follow Up', 'bobot' => 10, 'nilai' => $nilai_followup],
                    ];
                } else {
                    $detail_kpi = [
                        ['nama' => 'Omset', 'bobot' => 60, 'nilai' => $nilai_omset],
                        ['nama' => 'Closing', 'bobot' => 10, 'nilai' => $nilai_closing],
                        ['nama' => 'Upselling', 'bobot' => 10, 'nilai' => $nilai_upselling],
                        ['nama' => 'Follow Up', 'bobot' => 10, 'nilai' => $nilai_followup],
                        ['nama' => 'Testimoni', 'bobot' => 10, 'nilai' => $nilai_testimoni],
                    ];
                }
                break;

            case 43: // PENGIKLAN
                if ($context === 'gaji') {
                    $detail_kpi = [
                        ['nama' => 'Budgeting', 'bobot' => 15, 'nilai' => $nilai_budgeting],
                        ['nama' => 'ROAS', 'bobot' => 15, 'nilai' => $nilai_roas],
                        ['nama' => 'Omset', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ];
                } else {
                    $detail_kpi = [
                        ['nama' => 'Budgeting', 'bobot' => 15, 'nilai' => $nilai_budgeting],
                        ['nama' => 'ROAS', 'bobot' => 15, 'nilai' => $nilai_roas],
                        ['nama' => 'Omset', 'bobot' => 10, 'nilai' => $nilai_omset],
                        ['nama' => 'Customer', 'bobot' => 60, 'nilai' => $nilai_customer],
                    ];
                }
                break;

            case 44: // MULTIMEDIA
                $detail_kpi = [
                    ['nama' => 'Omset Cabang', 'bobot' => 30, 'nilai' => $nilai_omset],
                    ['nama' => 'Feed PL', 'bobot' => 15, 'nilai' => $nilai_feed_pl],
                    ['nama' => 'Video', 'bobot' => 20, 'nilai' => $nilai_video],
                    ['nama' => 'Feed Mingguan', 'bobot' => 15, 'nilai' => $nilai_feed_mingguan],
                    ['nama' => 'Story', 'bobot' => 10, 'nilai' => $nilai_story],
                    ['nama' => 'Testimoni', 'bobot' => 10, 'nilai' => $nilai_testimoni],
                ];
                break;

            case 45: // IT
                $detail_kpi = [
                    ['nama' => 'Omset', 'bobot' => 30, 'nilai' => $nilai_omset],
                    ['nama' => 'Bug Minor', 'bobot' => 10, 'nilai' => $nilai_bug_minor],
                    ['nama' => 'Operasional', 'bobot' => 25, 'nilai' => $nilai_bug_operasional],
                    ['nama' => 'Ecommerce', 'bobot' => 15, 'nilai' => $nilai_ecommerce],
                    ['nama' => 'Fitur', 'bobot' => 20, 'nilai' => $nilai_fitur],
                ];
                break;

            case 46: // PIC (tidak ada di context 'gaji')
                $detail_kpi = [
                    ['nama' => 'Budget Per Toko', 'bobot' => 20, 'nilai' => $nilai_hpp],
                    ['nama' => 'Budget Global', 'bobot' => 30, 'nilai' => $nilai_hpp_global],
                    ['nama' => 'Omset Cabang', 'bobot' => 50, 'nilai' => $nilai_omset],
                ];
                break;
        }

        // ---------------------------------------------------------------
        // TOTAL SKOR & GAJI
        // ---------------------------------------------------------------
        $skor_total = 0;
        foreach ($detail_kpi as $kpi) {
            $skor_total += ($kpi['nilai'] * $kpi['bobot']) / 100;
        }

        $skor_total2 = 0;
        foreach ($detail_absen as $absen) {
            $skor_total2 += ($absen['nilai'] * $absen['bobot']) / 100;
        }

        $tunjangan_absen = $skor_total2 / 100 * 250000;

        if ($jabatan == 41) {
            $tunjangan_kinerja = $skor_total / 100 * 850000;
        } elseif ($jabatan == 46 && $context !== 'gaji') {
            $tunjangan_kinerja = $skor_total / 100 * 850000;
        } elseif ($jabatan == 40) {
            $tunjangan_kinerja = $skor_total / 100 * 1250000;
        } elseif ($jabatan == 43) {
            $tunjangan_kinerja = $skor_total / 100 * 1000000;
        } elseif ($jabatan == 35) {
            $tunjangan_kinerja = ($unit == 1)
                ? $skor_total / 100 * 850000
                : $skor_total / 100 * 250000;
        } else {
            $tunjangan_kinerja = $skor_total / 100 * 250000;
        }

        $gaji_pokok = 1500000;
        $gaji = $gaji_pokok + $tunjangan_kinerja + $tunjangan_absen + $akun->tunjangan_penempatan + $insentif;

        return [
            'karyawan'             => $karyawan,
            'akun'                 => $akun,
            'jabatan'              => $jabatan,
            'unit'                 => $unit,
            'aktual_omset_unit'    => $aktual_omset_unit,
            'detail_kpi'           => $detail_kpi,
            'detail_absen'         => $detail_absen,
            'skor_total'           => round($skor_total, 2),
            'skor_total2'          => round($skor_total2, 2),
            'tunjangan_kinerja'    => $tunjangan_kinerja,
            'tunjangan_absen'      => $tunjangan_absen,
            'insentif'             => $insentif,
            'gaji_pokok'           => $gaji_pokok,
            'gaji'                 => $gaji,
        ];
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

        $hasil = $this->hitungKPIGaji($selected_karyawan, $bulan, $tahun, 'penilaian_kinerja');

        return view('template', [
            'list_karyawan'        => $list_karyawan,
            'selected_karyawan'    => $selected_karyawan,
            'karyawan'             => $hasil['karyawan'],
            'detail_kpi'           => $hasil['detail_kpi'],
            'detail_absen'         => $hasil['detail_absen'],
            'aktual_omset_unit'    => $hasil['aktual_omset_unit'],
            'skor_total'           => $hasil['skor_total'],
            'tunjangan_kinerja'    => $hasil['tunjangan_kinerja'],
            'tunjangan_absen'      => $hasil['tunjangan_absen'],
            'insentif'             => $hasil['insentif'],
            'tunjangan_penempatan' => $hasil['akun'],
            'gaji_pokok'           => $hasil['gaji_pokok'],
            'gaji'                 => $hasil['gaji'],
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

        $hasil = $this->hitungKPIGaji($idakun, $bulan, $tahun, 'slip_gaji');

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
            'pegawai'              => $hasil['karyawan'],
            'jabatan'              => $namajabatan,
            'unit'                 => $jalanunit,
            'gaji_pokok'           => $hasil['gaji_pokok'],
            'tunjangan_kinerja'    => $hasil['tunjangan_kinerja'],
            'tunjangan_absen'      => $hasil['tunjangan_absen'],
            'insentif'             => $hasil['insentif'],
            'tunjangan_penempatan' => $hasil['akun']->tunjangan_penempatan,
            'gaji'                 => $hasil['gaji'],
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

        $hasil = $this->hitungKPIGaji($selected_karyawan, $bulan, $tahun, 'gaji');

        return view('template', [
            'list_karyawan'        => $list_karyawan,
            'selected_karyawan'    => $selected_karyawan,
            'karyawan'             => $hasil['karyawan'],
            'detail_kpi'           => $hasil['detail_kpi'],
            'detail_absen'         => $hasil['detail_absen'],
            'aktual_omset_unit'    => $hasil['aktual_omset_unit'],
            'skor_total'           => $hasil['skor_total'],
            'tunjangan_kinerja'    => $hasil['tunjangan_kinerja'],
            'tunjangan_absen'      => $hasil['tunjangan_absen'],
            'insentif'             => $hasil['insentif'],
            'tunjangan_penempatan' => $hasil['akun'],
            'gaji_pokok'           => $hasil['gaji_pokok'],
            'gaji'                 => $hasil['gaji'],
            'bulan'                => $bulan,
            'tahun'                => $tahun,
            'body'                 => 'penilaian/gaji'
        ]);
    }
}