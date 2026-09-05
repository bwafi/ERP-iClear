<?php

namespace App\Controllers;

use App\Models\ModelAsset;
use App\Models\ModelPhone;
use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelRiwayatAsset;
use DateTime;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Models\ModelKategoriAsset;



class Asset extends BaseController

{

    protected $PhoneModel;
    protected $AuthModel;
    protected $AssetModel;
    protected $RiwayatAssetModel;
    protected $KategoriAssetModel;

    public function __construct()
    {
        $this->PhoneModel = new ModelPhone();
        $this->AuthModel = new ModelAuth();
        $this->AssetModel = new ModelAsset();
        $this->RiwayatAssetModel = new ModelRiwayatAsset();
        $this->KategoriAssetModel = new ModelKategoriAsset();
    }

    public function index()
    {
        $db = \Config\Database::connect();
        $data = array(
            'body' => 'datamaster/asset',
            'asset' => $this->AssetModel->getAsset(),
            'asset_lama' => $this->AssetModel->getAssetLama(),
            'kategori_asset' => $this->KategoriAssetModel->getKategoriAsset(),
            'unit' => $db->table('unit')->get()->getResult(),
        );
        return view('template', $data);
    }

    public function pulihkan_asset($id)
    {
        $data = array(
            'deleted' => 0,
        );
        $this->AssetModel->update($id, $data);
        session()->setFlashdata('sukses', 'Data Asset dan penyusutan berhasil disimpan.');
        return redirect()->to(base_url('asset'));
    }

    public function insert_asset()
    {
        date_default_timezone_set('Asia/Jakarta');
        $asset_code = $this->request->getPost('asset_code');
        $asset = $this->request->getPost('asset');
        $tanggal_perolehan = $this->request->getPost('tanggal_perolehan');
        $nilai_perolehan = str_replace('.', '', $this->request->getPost('nilai_perolehan'));
        $penyusutan_bulanan = str_replace('.', '', $this->request->getPost('penyusutan_bulanan'));
        $nilai_sekarang = str_replace('.', '', $this->request->getPost('nilai_sekarang'));
        $kondisi = $this->request->getPost('kondisi');
        $keterangan = $this->request->getPost('keterangan');
        $jangka_waktu = $this->request->getPost('jangka_waktu');
        $kategori_asset = $this->request->getPost('kategori_asset');



        $cek = $this->AssetModel->where('asset_code', $asset_code)->first();

        if ($cek) {
            session()->setFlashdata('gagal', 'Kode Asset Sudah Digunakan, Silahkan Pulihkan Di Asset Terhapus dan Edit');
            return redirect()->back()->withInput();
        }


        $data = [
            'unit' => $this->request->getPost('unit') ?: (session()->get('ID_UNIT') ?: 1),
            'asset_code' => $asset_code,
            'asset' => $asset,
            'tanggal_perolehan' => $tanggal_perolehan,
            'nilai_perolehan' => $nilai_perolehan,
            'jangka_waktu' => $jangka_waktu,
            'kategori_asset' => $kategori_asset,
            'penyusutan_bulanan' => $penyusutan_bulanan,
            // 'nilai_sekarang' => $nilai_sekarang,
            'kondisi' => $kondisi,
            'keterangan' => $keterangan,
            'deleted' => 0
        ];

        $this->AssetModel->insert_Asset($data);
        $idAsset = $this->AssetModel->getInsertID();

        if ($idAsset) {

            $start = new DateTime($tanggal_perolehan);
            $now = new DateTime();
            $interval = $start->diff($now);
            $totalBulan = ($interval->y * 12) + $interval->m;

            $nilai_riwayat = $nilai_perolehan;



            for ($i = 1; $i < $totalBulan; $i++) {
                // Penyusutan selalu pada tanggal 1 setiap bulan
                $tanggal_penyusutan = (clone $start)->modify("+{$i} months");
                $tanggal_penyusutan->setDate(
                    $tanggal_penyusutan->format('Y'),
                    $tanggal_penyusutan->format('m'),
                    1
                );
                $tanggal_penyusutan_str = $tanggal_penyusutan->format('Y-m-d');

                $nilai_riwayat -= $penyusutan_bulanan;

                $riwayat = [
                    'asset_idasset' => $idAsset,
                    'penyusutan' => $penyusutan_bulanan,
                    'nilai_riwayat' => max(0, $nilai_riwayat),
                    'tanggal_penyusutan' => $tanggal_penyusutan_str
                ];

                $this->RiwayatAssetModel->insert($riwayat);
            }


            $this->AssetModel->update($idAsset, [
                'nilai_sekarang' => max(0, $nilai_riwayat)
            ]);



            session()->setFlashdata('sukses', 'Data Asset dan penyusutan berhasil disimpan.');
        } else {
            session()->setFlashdata('gagal', 'Data Asset gagal disimpan.');
        }

        return redirect()->to(base_url('/asset'));
    }


    public function update_asset()
    {
        $idnya = $this->request->getPost('id_asset');
        $asset_code = $this->request->getPost('asset_code');
        $asset = $this->request->getPost('asset');
        $tanggal_perolehan = $this->request->getPost('tanggal_perolehan');
        $nilai_perolehan = str_replace('.', '', $this->request->getPost('nilai_perolehan'));
        $penyusutan_bulanan = str_replace('.', '', $this->request->getPost('penyusutan_bulanan'));
        $nilai_sekarang = str_replace('.', '', $this->request->getPost('nilai_sekarang'));
        $kondisi = $this->request->getPost('kondisi');
        $keterangan = $this->request->getPost('keterangan');
        $jangka_waktu = $this->request->getPost('jangka_waktu');




        $data = [
            'unit' => $this->request->getPost('unit') ?: null,
            'jangka_waktu' => $jangka_waktu,
            'penyusutan_bulanan' => $penyusutan_bulanan,
            'kondisi' => $kondisi,
            'keterangan' => $keterangan,
        ];

        $result = $this->AssetModel->update($idnya, $data);

        if ($result) {
            session()->setFlashdata('sukses', 'Data Asset berhasil disimpan.');
        } else {
            session()->setFlashdata('gagal', 'Data Asset gagal disimpan.');
        }

        return redirect()->to(base_url('/asset'));
    }

    public function delete_asset()
    {
        $idnya = $this->request->getPost('id_asset');
        $data = array(
            'deleted' => 1
        );
        $this->AssetModel->update($idnya, $data);
        session()->setFlashdata('sukses', 'Data Asset berhasil dihapus.');
        return redirect()->to(base_url('/asset'));
    }

    function cleanRupiah($value)
    {

        $value = str_replace(['Rp', '.',], '', $value);
        return (int) $value;
    }

    public function export_asset()
    {

        $penyusutan = $this->AssetModel->getAsset();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $headers = ['Asset Code', 'Nama Asset', 'Tanggal Perolehan', 'Nilai Perolehan', 'Penyusutan Bulanan', 'Jangka Waktu Penyusutan', 'Nilai Sekarang'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Styling Header
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');

        // Data
        $row = 2;
        foreach ($penyusutan as $susutan) {
            $sheet->setCellValue('A' . $row, $susutan->asset_code);
            $sheet->setCellValue('B' . $row, $susutan->asset);
            $sheet->setCellValue('C' . $row, date('d-m-Y', strtotime($susutan->tanggal_perolehan)));
            $sheet->setCellValue('D' . $row, $susutan->nilai_perolehan);
            $sheet->setCellValue('E' . $row, $susutan->penyusutan_bulanan);
            $sheet->setCellValue('F' . $row, date('d-m-Y', strtotime($susutan->jangka_waktu)));
            $sheet->setCellValue('G' . $row, $susutan->nilai_sekarang);
            $row++;
        }


        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }


        $sheet->getStyle('A1:G' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);


        $sheet->freezePane('A2');


        $filename = 'data_asset_' . date('Ymd_His') . '.xlsx';

        // Set header
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }


    //penyusutan bulanan
    public function prosesPenyusutan()
    {
        date_default_timezone_set('Asia/Jakarta');
        $assets = $this->AssetModel
            ->where('deleted', 0)
            ->findAll();

        $today = date('Y-m-01');

        foreach ($assets as $asset) {

            if (date('Y-m-d', strtotime($asset->jangka_waktu)) < $today) {
                continue;
            }

            // Cek  penyusutan untuk bulan ini
            $sudahAda = $this->RiwayatAssetModel
                ->where('asset_idasset', $asset->idasset)
                ->where('tanggal_penyusutan >=', $today)
                ->first();

            if ($sudahAda) {
                continue; // skip jika sudah disusutkan 
            }

            // Hitung nilai baru
            $penyusutan = (int)$asset->penyusutan_bulanan;
            $nilaiBaru = max(0, (int)$asset->nilai_sekarang - $penyusutan);


            $this->RiwayatAssetModel->insert([
                'asset_idasset' => $asset->idasset,
                'penyusutan' => $penyusutan,
                'nilai_riwayat' => $nilaiBaru,
                'tanggal_penyusutan' => $today
            ]);


            $this->AssetModel->update($asset->idasset, [
                'nilai_sekarang' => $nilaiBaru
            ]);
        }

        return "Penyusutan selesai dijalankan.";
    }
}
