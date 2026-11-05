<?php

namespace App\Controllers;

use App\Models\Core;
use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelKartuStok;
use App\Models\ModelPenjualan;
use App\Models\ModelPelanggan;
use App\Models\ModelService;
use App\Models\ModelPresensi;
use App\Models\ModelJadwalMasuk;
use App\Models\ModelUnit;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RiwayatPresensi extends BaseController

{

    protected $AuthModel;
    protected $KartuStokModel;
    protected $PenjualanModel;
    protected $PelangganModel;
    protected $ServiceModel;
    protected $PresensiModel;
    protected $JadwalMasukModel;
    protected $UnitModel;

    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->KartuStokModel = new ModelKartuStok();
        $this->PenjualanModel = new ModelPenjualan();
        $this->PelangganModel = new ModelPelanggan();
        $this->ServiceModel = new ModelService();
        $this->PresensiModel = new ModelPresensi();
        $this->JadwalMasukModel = new ModelJadwalMasuk();
        $this->UnitModel = new ModelUnit();
    }


    public function index()
    {
        $data = array(
            'presensi' => $this->PresensiModel->getByIdAkun(session('ID_AKUN')),
            'body' => 'riwayat/presensi'
        );
        return view('template', $data);
    }

    public function semua_riwayat()
    {
        $presensi = $this->PresensiModel->getAll();
        $seragamSvc = new \App\Services\Kpi\PresensiSeragamService();

        $baris = [];
        $bolehSeragam = [];
        foreach ($presensi as $row) {
            $tanggal = substr((string) ($row->waktu_masuk ?: $row->created_at), 0, 10);
            $baris[] = ['akun_idakun' => (int) $row->akun_idakun, 'tanggal' => $tanggal];
            $bolehSeragam[(int) $row->akun_idakun . '|' . $tanggal] = $seragamSvc->bolehMenilai(
                (int) ($row->ID_UNIT ?? 0),
                (int) ($row->ID_JABATAN ?? 0)
            );
        }

        $data = array(
            'presensi' => $presensi,
            'seragam' => $seragamSvc->petaNilai($baris),
            'bolehSeragam' => $bolehSeragam,
            'body' => 'riwayat/semua_presensi'
        );
        return view('template', $data);
    }

    /**
     * Simpan nilai SERAGAM (2/1/2) dari centang foto absen.
     */
    public function seragam()
    {
        try {
            $svc = new \App\Services\Kpi\PresensiSeragamService();
            $hasil = $svc->simpan(
                (int) $this->request->getPost('employee_id'),
                (string) $this->request->getPost('tanggal'),
                $this->request->getPost('seragam') === '1',
                $this->request->getPost('sepatu') === '1',
                $this->request->getPost('idcard') === '1',
                $this->request->getPost('timpa_manual') === '1'
            );

            return redirect()->to(base_url('semua_riwayat_presensi'))
                ->with('sukses', 'Nilai SERAGAM ' . $hasil['score'] . '/5 tersimpan.');
        } catch (\Throwable $e) {
            return redirect()->to(base_url('semua_riwayat_presensi'))
                ->with('gagal', $e->getMessage());
        }
    }

    public function export_semua_presensi()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();


        $tanggal_awal  = $this->request->getPost('tanggal_awal');
        $tanggal_akhir = $this->request->getPost('tanggal_akhir');



        $datapresensi = $this->PresensiModel->filterexport($tanggal_awal, $tanggal_akhir);


        $headers = [
            'A1' => 'Nama Akun',
            'B1' => 'Unit',
            'C1' => 'Tanggal',
            'D1' => 'Jam Masuk',
            'E1' => 'Jam Pulang',
            'F1' => 'Nama Jadwal',
            'G1' => 'Status Kehadiran',
            'H1' => 'Jarak (m)',
            'I1' => 'IP',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }


        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getStyle('A1:I1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');

        $row = 2;
        foreach ($datapresensi as $item) {
            $sheet->setCellValue('A' . $row, $item->NAMA_AKUN);
            $sheet->setCellValue('B' . $row, $item->NAMA_UNIT ?? '-');
            $sheet->setCellValue('C' . $row, date('d-m-Y', strtotime($item->created_at)));
            $sheet->setCellValue('D' . $row, date('H:i:s', strtotime($item->waktu_masuk ?? '')));
            $sheet->setCellValue('E' . $row, $item->waktu_pulang ? date('H:i:s', strtotime($item->waktu_pulang)) : '-');
            $sheet->setCellValue('F' . $row, $item->nama_jadwal ?? '-');
            $sheet->setCellValue(
                'G' . $row,
                $item->status_kehadiran == 0
                    ? 'Tepat Waktu'
                    : ($item->status_kehadiran == 1
                        ? 'Toleransi'
                        : ($item->status_kehadiran == 2
                            ? 'Telat'
                            : '-'))

            );
            $sheet->setCellValue('H' . $row, $item->jarak ?? '0');
            $sheet->setCellValue('I' . $row, $item->ip);
            $row++;
        }

        // Auto Width
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }


        $sheet->getStyle('A1:I' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->freezePane('A2');
        $sheet->getStyle('H2:H' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0');


        $filename = 'Data_Presensi_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }






    //approval_presensi
    public function approval_presensi()
    {
        $data = array(
            'presensi' => $this->PresensiModel->getAll(),
            'jadwalmasuk' => $this->JadwalMasukModel->getAll(),
            'namapegawai' => $this->AuthModel->getAkunPegawai(),
            'dataunit' => $this->UnitModel->getById(session('ID_UNIT')),
            'body' => 'admin/approval_presensi'

        );
        return view('template', $data);
    }

    public function submit_approval_presensi()
    {
        $idpresensi = $this->request->getPost('idpresensi');

        $data = array(
            'status_absensi' => 1,
            'keterangan' => $this->request->getPost('keterangan')
        );

        try {
            $this->PresensiModel->update($idpresensi, $data);

            // Jika request AJAX, return JSON response
            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'status' => 'success',
                    'message' => 'Data berhasil diupdate'
                ]);
            }

            // Jika bukan AJAX, redirect seperti biasa
            session()->setFlashdata('sukses', 'Data berhasil diupdate');
            return redirect()->to(base_url('approval_presensi'));
        } catch (\Exception $e) {
            // Jika request AJAX, return error JSON
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(500)->setJSON([
                    'status' => 'error',
                    'message' => 'Gagal mengupdate data: ' . $e->getMessage()
                ]);
            }

            // Jika bukan AJAX, redirect dengan error
            session()->setFlashdata('gagal', 'Gagal mengupdate data');
            return redirect()->to(base_url('approval_presensi'));
        }
    }


    public function submit_absen_manual()
    {


        date_default_timezone_set('Asia/Jakarta');
        $idakun =  $this->request->getPost('id_akun');
        $dataakun =  $this->AuthModel->getById($idakun);
        $idunit = $dataakun->ID_UNIT;
        $dataunit = $this->UnitModel->getById($idunit);
        $lat = $dataunit->LATITUDE;
        $long = $dataunit->LONGTITUDE;
        $idjadwalmasuk = $this->request->getPost('idjadwal_masuk');
        $jam_jadwal_masuk = $this->request->getPost('jam_masuk');
        $jam_jadwal_pulang = $this->request->getPost('jam_pulang');
        $jam_toleransi = $this->request->getPost('jam_toleransi');
        $foto_kehadiran = $this->request->getFile('foto_kehadiran');
        $jampresensi = $this->request->getPost('jam_prensensi');
        $datetime = new \DateTime($jampresensi);
        $formattedJam = $datetime->format('Y-m-d H:i:s');


        $waktuMasuk = date('H:i:s');
        $tsMasuk = strtotime($waktuMasuk);
        $tsJadwalMasuk = strtotime($jam_jadwal_masuk);
        $tsToleransi = strtotime($jam_toleransi);

        $selisihDetik = $tsMasuk - $tsJadwalMasuk;

        if ($selisihDetik <= 59) {
            $statusKehadiran = 0;
        } elseif ($tsMasuk < $tsToleransi) {
            $statusKehadiran = 1;
        } else {
            $statusKehadiran = 2;
        }


        $dataunit = $this->UnitModel->getById(session('ID_UNIT'));
        $radius = $dataunit->RADIUS;
        $latUnit = $dataunit->LATITUDE;
        $longUnit = $dataunit->LONGTITUDE;


        $tanggalHariIni = date('Y-m-d');


        //  Cek jarak dari titik unit
        // $jarakKm = $this->hitungJarakKm($lat, $long, $latUnit, $longUnit);
        // $radiusKm = floatval($radius) / 1000;
        // $jarakmeter = $jarakKm * 1000;



        // if ($jarakmeter > floatval($radius)) {
        //     session()->setFlashdata('gagal', 'Lokasi Anda terlalu jauh dari titik absen. Maksimal ' . $radius . ' meter.');
        //     return redirect()->to(base_url('absensi'));
        // }

        // Cek apakah sudah absen hari ini
        $presensiHariIni = $this->PresensiModel
            ->where('akun_idakun', $idakun)
            ->where('idjadwal_masuk', $idjadwalmasuk)
            ->where('DATE(waktu_masuk)', $tanggalHariIni)
            ->first();

        if ($presensiHariIni) {
            session()->setFlashdata('gagal', 'Anda sudah melakukan absen masuk untuk jadwal ini hari ini.');
            return redirect()->to(base_url('absensi'));
        }



        //  Upload foto
        $namaFoto = null;
        if ($foto_kehadiran && $foto_kehadiran->isValid() && !$foto_kehadiran->hasMoved()) {
            $ext = $foto_kehadiran->getClientExtension();
            $timestamp = date('Ymd_His');
            $namaFoto = 'absen_' . $idakun . '_' . $timestamp . '.' . $ext;

            $foto_kehadiran->move(ROOTPATH . 'public/foto_presensi', $namaFoto);
        }


        $data = [
            'waktu_masuk' => $formattedJam,
            'jam_jadwal_masuk' => $jam_jadwal_masuk,
            'jam_jadwal_pulang' => $jam_jadwal_pulang,
            'jam_toleransi' => $jam_toleransi,
            'status_absensi' => 1,
            'lat' => $lat,
            'long' => $long,
            'ip' => $this->request->getIPAddress(),
            'foto' => $namaFoto,
            'idjadwal_masuk' => $idjadwalmasuk,
            'akun_idakun' => $idakun,
            'unit_idunit' => $idunit,
            'created_at' => date('Y-m-d H:i:s'),
            'jarak' => 10,
            'status_kehadiran' => $statusKehadiran,
            'keterangan' => $this->request->getPost('keterangan')
        ];

        $this->PresensiModel->insertPresensi($data);
        session()->setFlashdata('sukses', 'Absen masuk berhasil!');
        return redirect()->to(base_url('approval_presensi'));
    }


    public function kirim_lokasi_pulang_manual()
    {
        date_default_timezone_set('Asia/Jakarta');
        $idpresensi = $this->request->getPost('idpresensi');

        $jampresensi = $this->request->getPost('jam_prensensi');
        $datetime = new \DateTime($jampresensi);
        $formattedJam = $datetime->format('Y-m-d H:i:s');

        $namaFoto = null;
        $foto_kehadiran = $this->request->getFile('foto_kehadiran');
        if ($foto_kehadiran && $foto_kehadiran->isValid() && !$foto_kehadiran->hasMoved()) {
            $ext = $foto_kehadiran->getClientExtension();
            $timestamp = date('Ymd_His');
            $namaFoto = 'absen_' . $idpresensi . '_' . $timestamp . '.' . $ext;

            $foto_kehadiran->move(ROOTPATH . 'public/foto_presensi', $namaFoto);
        }

        $data = array(
            'foto_pulang' => $namaFoto,
            'waktu_pulang' => $formattedJam,
        );
        $this->PresensiModel->update($idpresensi, $data);
        session()->setFlashdata('sukses', 'Absen pulang berhasil!');
        return redirect()->to(base_url('approval_presensi'));
    }


    private function hitungJarakKm($lat1, $lon1, $lat2, $lon2)
    {
        $R = 6371; // Radius bumi dalam kilometer
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c;
    }
}
