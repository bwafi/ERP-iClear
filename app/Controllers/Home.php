<?php

namespace App\Controllers;

use App\Models\Core;
use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelKartuStok;
use App\Models\ModelPenjualan;
use App\Models\ModelPelanggan;
use App\Models\ModelService;
use App\Models\ModelPembayaranHutang;
use App\Models\ModelKasKeluar;

class Home extends BaseController

{

    protected $AuthModel;
    protected $KartuStokModel;
    protected $PenjualanModel;
    protected $PelangganModel;
    protected $ServiceModel;
    protected $PembayaranHutangModel;
    protected $KasKeluarModel;

    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->KartuStokModel = new ModelKartuStok();
        $this->PenjualanModel = new ModelPenjualan();
        $this->PelangganModel = new ModelPelanggan();
        $this->ServiceModel = new ModelService();
        $this->PembayaranHutangModel = new ModelPembayaranHutang();
        $this->KasKeluarModel = new ModelKasKeluar();
    }
    
public function index()
{
    $unit_id = $this->request->getGet('unit_idunit');

    $akun = $this->AuthModel->getById(session('ID_AKUN'));
    $unitModel = new \App\Models\ModelUnit();

    // === Filter bulan (default: 6 bulan terakhir) ===
    $startMonth = $this->request->getGet('start_month');
    $endMonth   = $this->request->getGet('end_month');

    if (!$startMonth) {
        $startMonth = date('Y-m', strtotime('-5 months'));
    }
    if (!$endMonth) {
        $endMonth = date('Y-m');
    }

    $months = [];
    $pendapatan_chart = [];
    $pendapatan_service_chart = [];

    $start    = new \DateTime($startMonth . '-01');
    $end      = new \DateTime($endMonth . '-01');
    $interval = new \DateInterval('P1M');
    $period   = new \DatePeriod($start, $interval, $end->modify('+1 month'));

    foreach ($period as $dt) {
        $month = $dt->format('Y-m');
        $months[] = $dt->format('M Y');

        $total_pos = $this->PenjualanModel
            ->where('DATE_FORMAT(tanggal, "%Y-%m")', $month)
            ->where($unit_id ? ['unit_idunit' => $unit_id] : [])
            ->selectSum('total_penjualan')
            ->first()->total_penjualan ?? 0;

        $total_service = $this->ServiceModel
            ->where('DATE_FORMAT(tanggal_selesai, "%Y-%m")', $month)
            ->where(['status_service' => 4])
            ->where($unit_id ? ['unit_idunit' => $unit_id] : [])
            ->selectSum('harus_dibayar')
            ->get()
            ->getRow()
            ->harus_dibayar ?? 0;

        $pendapatan_chart[] = (float) $total_pos;
        $pendapatan_service_chart[] = (float) $total_service;
    }

    // === Barang Terlaris Chart ===
    $barangTerlaris = $this->KartuStokModel->getKartuStokTerlaris();
    $barangLabels = array_map(fn($row) => $row->nama_barang, $barangTerlaris);
    $barangData   = array_map(fn($row) => (float) $row->total_penjualan, $barangTerlaris);

    // === Hutang Chart ===
    $hutangData = $this->PembayaranHutangModel->getChartHutang($unit_id);
    $hutangLabels = [];
    $hutangBayar  = [];
    $hutangSisa   = [];

    foreach ($hutangData as $row) {
        $hutangLabels[] = date("M Y", strtotime($row->bulan . "-01"));
        $hutangBayar[]  = (float) $row->total_bayar;
        $hutangSisa[]   = (float) $row->total_sisa;
    }

    // === Kas Keluar Chart ===
    $kasKeluarData = $this->KasKeluarModel->getKasKeluarFiltered(); // Add filters if needed
    $kasKategori = [];

    foreach ($kasKeluarData as $row) {
        $kategori = $row->kategori;
        $nominal = (float) ($row->jumlah ?? 0); // adjust 'jumlah' to match your table field
        if (!isset($kasKategori[$kategori])) {
            $kasKategori[$kategori] = 0;
        }
        $kasKategori[$kategori] += $nominal;
    }

    // Sort & limit to top 10
    arsort($kasKategori);
    $kasKategori = array_slice($kasKategori, 0, 10, true);

    $kas_labels = array_keys($kasKategori);
    $kas_values = array_values($kasKategori);

    // === Data untuk View ===
    $data = array(
        'akun' => $akun,
        'unit_id' => $unit_id,
        'units' => $unitModel->findAll(),
        'stok' => $this->KartuStokModel->getKartuStokWithKategori(),
        'penjualan' => $this->PenjualanModel->getPendapatan($unit_id),
        'pendapatan_service' => $this->ServiceModel->getTotalPendapatanService($unit_id),
        'pelanggan' => $this->PelangganModel->countPelanggan(),
        'pelanggan_service' => count($this->PelangganModel->getPelangganWithService()),
        'pelanggan_baru' => count($this->PelangganModel->getPelangganBaruBulanIni()),
        'months' => $months,
        'pendapatan_chart' => $pendapatan_chart,
        'pendapatan_service_chart' => $pendapatan_service_chart,
        'start_month' => $startMonth,
        'end_month'   => $endMonth,
        'barang_labels' => $barangLabels,
        'barang_data'   => $barangData,
        'hutang_labels' => $hutangLabels,
        'hutang_bayar'  => $hutangBayar,
        'hutang_sisa'   => $hutangSisa,
        'kas_labels' => $kas_labels,
        'kas_values' => $kas_values,
        'title' => 'Home',
        'body' => 'welcome_message'
    );

    return view('template', $data);
}

}