<?php

namespace App\Controllers;

use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelBarang;
use App\Models\ModelBarangUnit;
use App\Models\ModelHppUnit;
use App\Models\ModelKartuStok;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Models\ModelUnit;

class Kartu_Stok extends BaseController

{

    protected $AuthModel;
    protected $KartuStokModel;
    protected $BarangModel;
    protected $HppUnitModel;
    protected $BarangUnitModel;
    protected $UnitModel;

    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->KartuStokModel = new ModelKartuStok();
        $this->BarangModel = new ModelBarang();
        $this->HppUnitModel = new ModelHppUnit();
        $this->BarangUnitModel = new ModelBarangUnit();
        $this->UnitModel = new ModelUnit();
    }

    public function index()
    {
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));
        $data =  array(
            'akun' => $akun,
            'summary' => $this->KartuStokModel->getSummaryKartuStok(),
            'body'  => 'stok/kartu_stok',
            'unit' => $this->UnitModel->getUnit(),
            'can_manage_produk_unit' => self::bolehKelolaProdukUnit((int) session('ID_JABATAN')),
        );
        return view('template', $data);
    }

    /**
     * Role yang boleh mengelola HPP dan status produk per unit: Finance (0),
     * Admin root (1), Manager (34), ADMIN/KASIR (35 & 47), dan Kepala Toko (41).
     */
    public static function bolehKelolaProdukUnit(int $idJabatan): bool
    {
        return in_array($idJabatan, [0, 1, 34, 35, 41, 47], true);
    }

    /**
     * Koreksi HPP satu produk pada satu unit.
     *
     * Unit diambil dari baris Kartu Stok yang dipilih, bukan dari master, dan
     * hanya baris itu yang berubah. Nilai disimpan sebagai sumber `manual`
     * supaya sync stok awal berikutnya tidak menimpanya.
     */
    public function updateHpp()
    {
        if (!self::bolehKelolaProdukUnit((int) session('ID_JABATAN'))) {
            return $this->response->setStatusCode(403)->setJSON([
                'status'  => 'error',
                'message' => 'Role Anda tidak dapat mengubah HPP.',
            ]);
        }

        $idbarang = (int) $this->request->getPost('idbarang');
        $idunit   = (int) $this->request->getPost('id_unit');
        $hpp      = (float) $this->request->getPost('hpp');

        if ($idbarang <= 0 || $idunit <= 0 || $hpp <= 0 || $hpp > 9999999999999) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'HPP tidak valid.',
            ]);
        }

        if (!$this->KartuStokModel->existsPair($idbarang, $idunit)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Produk/unit tidak ditemukan.',
            ]);
        }

        $this->HppUnitModel->setManual($idbarang, $idunit, $hpp, (int) session('ID_AKUN'));

        return $this->response->setJSON([
            'status' => 'success',
            'hpp'    => $hpp,
            'csrf'   => csrf_hash(),
        ]);
    }

    /**
     * Ubah harga jual master. Harga jual disimpan di `barang.harga` sehingga
     * berlaku global untuk semua unit dan transaksi berikutnya; transaksi lama
     * tetap memakai snapshot harga di detailnya.
     */
    public function updateHargaJual()
    {
        if (!self::bolehKelolaProdukUnit((int) session('ID_JABATAN'))) {
            return $this->response->setStatusCode(403)->setJSON([
                'status'  => 'error',
                'message' => 'Role Anda tidak dapat mengubah harga jual.',
            ]);
        }

        $idbarang = (int) $this->request->getPost('idbarang');
        $harga    = (int) $this->request->getPost('harga');

        if ($idbarang <= 0 || $harga <= 0 || $harga > 9999999999999) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Harga jual tidak valid.',
            ]);
        }

        if ($this->BarangModel->find($idbarang) === null) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Produk tidak ditemukan.',
            ]);
        }

        $this->BarangModel->update($idbarang, ['harga' => $harga]);

        return $this->response->setJSON([
            'status' => 'success',
            'harga'  => $harga,
            'csrf'   => csrf_hash(),
        ]);
    }

    /**
     * Aktif/nonaktif produk pada satu unit.
     *
     * Nonaktif ditolak bila unit masih punya stok (termasuk stok negatif),
     * karena produk yang tersembunyi dari stok opname tidak akan pernah
     * dikoreksi. Aktivasi selalu boleh.
     */
    public function updateStatus()
    {
        if (!self::bolehKelolaProdukUnit((int) session('ID_JABATAN'))) {
            return $this->response->setStatusCode(403)->setJSON([
                'status'  => 'error',
                'message' => 'Role Anda tidak dapat mengubah status produk unit.',
            ]);
        }

        $idbarang = (int) $this->request->getPost('idbarang');
        $idunit   = (int) $this->request->getPost('id_unit');
        $aktif    = (int) $this->request->getPost('aktif') === 1;

        if ($idbarang <= 0 || $idunit <= 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Produk/unit tidak valid.',
            ]);
        }

        $row = $this->KartuStokModel->getPair($idbarang, $idunit);
        if ($row === null) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Produk/unit tidak ditemukan.',
            ]);
        }

        $stok = (float) ($row->stok_akhir ?? 0);
        if (!$aktif && $stok != 0.0) {
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Produk masih punya stok ' . rtrim(rtrim(number_format($stok, 2, ',', '.'), '0'), ',')
                    . ' di unit ini.',
            ]);
        }

        $this->BarangUnitModel->setAktif($idbarang, $idunit, $aktif, (int) session('ID_AKUN'));

        return $this->response->setJSON([
            'status' => 'success',
            'aktif'  => $aktif ? 1 : 0,
            'csrf'   => csrf_hash(),
        ]);
    }

    /**
     * Server-side processing DataTables Kartu Stok.
     */
    public function dt()
    {
        $draw    = (int)$this->request->getGet('draw');
        $start   = (int)$this->request->getGet('start');
        $length  = (int)$this->request->getGet('length');

        $searchParam = $this->request->getGet('search');
        $search = '';
        if (is_array($searchParam)) {
            $search = trim((string)($searchParam['value'] ?? ''));
        } else {
            $search = trim((string)$searchParam);
        }

        $orderCol = $this->request->getGet('order') ? $this->request->getGet('order')[0]['column'] : 0;
        $orderDir = $this->request->getGet('order') ? $this->request->getGet('order')[0]['dir'] : 'asc';

        $columnMap = [
            0 => 'stok_barang.kode_barang',
            1 => 'stok_barang.nama_barang',
            2 => 'stok_barang.nama_unit',
            3 => 'kategori.nama_kategori',
            4 => 'barang.status_ppn',
            5 => 'stok_barang.stok_akhir',
        ];
        $orderCol = $columnMap[$orderCol] ?? 'stok_barang.kode_barang';
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';

        $unit = trim((string)$this->request->getGet('unit'));
        $ppn  = trim((string)$this->request->getGet('status_ppn'));

        $totalRecords = $this->KartuStokModel->countKartuStokAll();
        $filteredRecords = $this->KartuStokModel->countKartuStokDT($search, $unit, $ppn);
        $rows = $this->KartuStokModel->getKartuStokDT($length, $start, $search, $orderCol, $orderDir, $unit, $ppn);

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'idbarang'            => (int)$row->idbarang,
                'id_unit'             => (int)$row->id_unit,
                'aktif'               => (int)($row->aktif_unit ?? 1),
                'kode_barang'         => $row->kode_barang,
                'nama_barang'         => $row->nama_barang,
                'imei'                => $row->imei,
                'nama_unit'           => $row->nama_unit,
                'nama_kategori'       => $row->nama_kategori ?? '-',
                'nama_supplier'       => $row->nama_supplier ?? '-',
                'jenis_hp'            => $row->jenis_hp,
                'warna'               => $row->warna,
                'harga'               => (float)$row->harga,
                'harga_beli'          => (float)$row->harga_beli,
                'status_ppn'          => (int)$row->status_ppn,
                'stok_awal'           => (float)$row->stok_awal,
                'stok_akhir'          => (float)$row->stok_akhir,
                'total_pembelian'     => (float)$row->total_pembelian,
                'total_penjualan'     => (float)$row->total_penjualan,
                'total_retur_pelanggan' => (float)$row->total_retur_pelanggan,
                'total_retur_supplier'  => (float)$row->total_retur_supplier,
                'total_mutasi_masuk'  => (float)$row->total_mutasi_masuk,
                'total_mutasi_keluar' => (float)$row->total_mutasi_keluar,
            ];
        }

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $data,
        ]);
    }

    public function export()
    {
        // $tanggalAwal = $this->request->getPost('tanggal_awal');
        // $tanggalAkhir = $this->request->getPost('tanggal_akhir');
        $unit = $this->request->getPost('unit');
        $statusPpn = $this->request->getPost('status_ppn');

        // if (empty($tanggalAwal)) {
        //     $tanggalAwal = $this->KartuStokModel->getMinTanggalStok();
        // }

        // if (empty($tanggalAkhir)) {
        //     $tanggalAkhir = date('Y-m-d');
        // }

        $stok = $this->KartuStokModel->exportfilter( $unit, $statusPpn);
        // $stok = $this->KartuStokModel->exportfilter($tanggalAwal, $tanggalAkhir, $unit, $statusPpn);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header titles
        $headers = [
            'Kode Barang',
            'Nama Barang',
            'Imei',
            'Status PPN',
            'Unit',
            'Total Pembelian',
            'Total Penjualan',
            'Total Retur Pelanggan',
            'Total Retur Supplier',
            'Stok Akhir'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Header styling
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);
        $sheet->getStyle('A1:L1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:L1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');

        // Data rows
        $row = 2;
        foreach ($stok as $item) {
            $sheet->setCellValue('A' . $row, $item->kode_barang);
            $sheet->setCellValue('B' . $row, $item->nama_barang);

            // Jika imei kosong → tampilkan "Tidak ada IMEI"
            $imeiText = !empty($item->imei) ? $item->imei : 'Tidak ada IMEI';
            $sheet->setCellValue('C' . $row, $imeiText);

            $sheet->setCellValue('D' . $row, ((int)$item->status_ppn === 1) ? 'PPN' : 'NON PPN');
            $sheet->setCellValue('E' . $row, $item->nama_unit);
            
            
            $sheet->setCellValue('H' . $row, $item->total_pembelian);
            $sheet->setCellValue('I' . $row, $item->total_penjualan);
            $sheet->setCellValue('J' . $row, $item->total_retur_pelanggan);
            $sheet->setCellValue('K' . $row, $item->total_retur_supplier);
            $sheet->setCellValue('L' . $row, $item->stok_akhir);
            $row++;
        }

        // Auto width
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Borders
        $sheet->getStyle('A1:L' . ($row - 1))
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Freeze header
        $sheet->freezePane('A2');

        // File name
        $unitLabel = $unit ?: 'all_unit';
        $ppnLabel = $statusPpn ?: 'all_ppn';
        // $tglAwalFormatted = date('Ymd', strtotime($tanggalAwal));
        // $tglAkhirFormatted = date('Ymd', strtotime($tanggalAkhir));
        $filename = 'kartu_stok_' . $unitLabel . '_' . $ppnLabel . '.xlsx';
        // $filename = 'kartu_stok_' . $unitLabel . '_' . $ppnLabel . '_' . $tglAwalFormatted . '-' . $tglAkhirFormatted . '.xlsx';

        // Output
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}