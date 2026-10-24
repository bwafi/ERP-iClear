<?php

namespace App\Controllers;

use App\Models\ModelJurnal;
use App\Models\ModelUnit;
use App\Models\ModelHppKlaimLoss;
use Config\Database;

class DashboardLabaRugi extends BaseController
{
    protected $JurnalModel;
    protected $UnitModel;
    protected $db;

    public function __construct()
    {
        $this->JurnalModel = new ModelJurnal();
        $this->UnitModel = new ModelUnit();
        $this->db = Database::connect();
    }

    public function index()
    {
        // Get filter parameters - bulan dalam format YYYY-MM
        $startMonth = $this->request->getGet('start_month') ?: date('Y-m', strtotime('-2 months'));
        $endMonth = $this->request->getGet('end_month') ?: date('Y-m');
        $unitId = $this->request->getGet('unit_id');
        $jenisLaporan = $this->request->getGet('jenis_laporan') ?: 'all'; // 'jurnal', 'transaksi', atau 'all'

        // Convert bulan ke tanggal awal dan akhir
        $startDate = $startMonth . '-01';
        $endDate = date('Y-m-t', strtotime($endMonth . '-01')); // tanggal terakhir dari bulan

        // Get units for dropdown
        $units = $this->UnitModel->findAll();

        // Data untuk laporan berdasarkan jurnal
        $labaRugiJurnal = [];
        if ($jenisLaporan == 'jurnal' || $jenisLaporan == 'all') {
            $labaRugiJurnal = $this->JurnalModel->getLabaRugiFromJurnal($startDate, $endDate, $unitId);
        }

        // Data untuk laporan berdasarkan transaksi
        $labaRugiTransaksi = [];
        if ($jenisLaporan == 'transaksi' || $jenisLaporan == 'all') {
            $labaRugiTransaksi = $this->getLabaRugiFromTransaksi($startDate, $endDate, $unitId);
        }

        // Data per bulan untuk chart
        $chartData = $this->getChartDataPerBulan($startMonth, $endMonth, $unitId);

        $data = [
            'title' => 'Dashboard Laba Rugi',
            'body' => 'dashboard/dashboard_laba_rugi',
            'start_month' => $startMonth,
            'end_month' => $endMonth,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'unit_id' => $unitId,
            'jenis_laporan' => $jenisLaporan,
            'units' => $units,
            'laba_rugi_jurnal' => $labaRugiJurnal,
            'laba_rugi_transaksi' => $labaRugiTransaksi,
            'chart_data' => $chartData,
        ];

        return view('template', $data);
    }

    /**
     * Mendapatkan data laba rugi dari transaksi (POS, Service, Kas Masuk, Kas Keluar)
     */
    private function getLabaRugiFromTransaksi($tanggal_awal = null, $tanggal_akhir = null, $id_unit = null)
    {
        // 1. Pendapatan dari Penjualan (POS)
        $builder_penjualan = $this->db->table('penjualan');
        $builder_penjualan->selectSum('total_penjualan', 'total');
        if ($tanggal_awal && $tanggal_akhir) {
            $builder_penjualan->where('DATE(tanggal) >=', $tanggal_awal)
                ->where('DATE(tanggal) <=', $tanggal_akhir);
        }
        if ($id_unit) {
            $builder_penjualan->where('unit_idunit', $id_unit);
        }
        $penjualan = $builder_penjualan->get()->getRow();
        $total_penjualan = $penjualan->total ?? 0;

        // 2. Pendapatan dari Service
        $builder_service = $this->db->table('service');
        $builder_service->selectSum('harus_dibayar', 'total');
        $builder_service->where('status_service', 4); // hanya service yang sudah selesai
        if ($tanggal_awal && $tanggal_akhir) {
            $builder_service->where('DATE(created_at) >=', $tanggal_awal)
                ->where('DATE(created_at) <=', $tanggal_akhir);
        }
        if ($id_unit) {
            $builder_service->where('unit_idunit', $id_unit);
        }
        $service = $builder_service->get()->getRow();
        $total_service = $service->total ?? 0;

        // 3. Pendapatan dari Kas Masuk
        $builder_kas_masuk = $this->db->table('kas_masuk');
        $builder_kas_masuk->selectSum('jumlah', 'total');
        if ($tanggal_awal && $tanggal_akhir) {
            $builder_kas_masuk->where('tanggal >=', $tanggal_awal)
                ->where('tanggal <=', $tanggal_akhir);
        }
        if ($id_unit) {
            $builder_kas_masuk->where('idunit', $id_unit);
        }
        $kas_masuk = $builder_kas_masuk->get()->getRow();
        $total_kas_masuk = $kas_masuk->total ?? 0;

        // 4. Biaya dari Kas Keluar
        $builder_kas_keluar = $this->db->table('kas_keluar');
        $builder_kas_keluar->selectSum('jumlah', 'total');
        if ($tanggal_awal && $tanggal_akhir) {
            $builder_kas_keluar->where('tanggal >=', $tanggal_awal)
                ->where('tanggal <=', $tanggal_akhir);
        }
        if ($id_unit) {
            $builder_kas_keluar->where('idunit', $id_unit);
        }
        $kas_keluar = $builder_kas_keluar->get()->getRow();
        $total_kas_keluar = $kas_keluar->total ?? 0;

        // 5. HPP Penjualan (dari detail penjualan) - HPP dikalikan dengan jumlah
        $builder_hpp = $this->db->table('detail_penjualan');
        $builder_hpp->select('SUM(hpp_penjualan * jumlah) as total', false);
        $builder_hpp->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan');
        
        if ($tanggal_awal && $tanggal_akhir) {
            $builder_hpp->where('DATE(penjualan.tanggal) >=', $tanggal_awal)
                ->where('DATE(penjualan.tanggal) <=', $tanggal_akhir);
        }
        
        if ($id_unit) {
            $builder_hpp->where('penjualan.unit_idunit', $id_unit);
        }
        
        $hpp = $builder_hpp->get()->getRow();
        $total_hpp = $hpp->total ?? 0;

        // 6. HPP Service (dari service_sparepart) - HPP dikalikan dengan jumlah
        $builder_hpp_service = $this->db->table('service_sparepart');
        $builder_hpp_service->select('SUM(hpp_penjualan * jumlah) as total', false);
        $builder_hpp_service->join('service', 'service.idservice = service_sparepart.service_idservice');
        $builder_hpp_service->where('service.status_service', 4); // hanya service yang sudah selesai
        
        if ($tanggal_awal && $tanggal_akhir) {
            $builder_hpp_service->where('DATE(service.created_at) >=', $tanggal_awal)
                ->where('DATE(service.created_at) <=', $tanggal_akhir);
        }
        
        if ($id_unit) {
            $builder_hpp_service->where('service.unit_idunit', $id_unit);
        }
        
        $hpp_service = $builder_hpp_service->get()->getRow();
        $total_hpp_service = $hpp_service->total ?? 0;

        // 7. HPP Klaim garansi, periode mengikuti tanggal_hpp_klaim.
        $lossStart = $tanggal_awal ?: '1970-01-01';
        $lossEnd = $tanggal_akhir ?: date('Y-m-d');
        $total_hpp_klaim = (new ModelHppKlaimLoss())->getTotalLoss($lossStart, $lossEnd, $id_unit);

        // Hitung total pendapatan dan biaya
        $total_pendapatan = $total_penjualan + $total_service + $total_kas_masuk;
        $total_biaya = $total_kas_keluar + $total_hpp + $total_hpp_service + $total_hpp_klaim;
        $laba_rugi = $total_pendapatan - $total_biaya;

        return [
            'pendapatan' => [
                'penjualan' => $total_penjualan,
                'service' => $total_service,
                'kas_masuk' => $total_kas_masuk,
                'total' => $total_pendapatan
            ],
            'biaya' => [
                'kas_keluar' => $total_kas_keluar,
                'hpp_penjualan' => $total_hpp,
                'hpp_service' => $total_hpp_service,
                'hpp_klaim' => $total_hpp_klaim,
                'total' => $total_biaya
            ],
            'laba_rugi' => $laba_rugi
        ];
    }

    /**
     * Mendapatkan data chart per bulan untuk visualisasi
     */
    private function getChartDataPerBulan($startMonth, $endMonth, $unitId = null)
    {
        $months = [];
        $current = strtotime($startMonth . '-01');
        $end = strtotime($endMonth . '-01');

        while ($current <= $end) {
            $monthStr = date('Y-m', $current);
            $monthLabel = date('M Y', $current);
            
            $startDate = $monthStr . '-01';
            $endDate = date('Y-m-t', $current);

            // Laba rugi jurnal per bulan
            $labaRugiJurnal = $this->JurnalModel->getLabaRugiFromJurnal($startDate, $endDate, $unitId);
            
            // Laba rugi transaksi per bulan
            $labaRugiTransaksi = $this->getLabaRugiFromTransaksi($startDate, $endDate, $unitId);

            $months[] = [
                'label' => $monthLabel,
                'month' => $monthStr,
                'jurnal' => [
                    'pendapatan' => $labaRugiJurnal['total_pendapatan'] ?? 0,
                    'biaya' => $labaRugiJurnal['total_biaya'] ?? 0,
                    'laba_rugi' => $labaRugiJurnal['laba_rugi'] ?? 0,
                ],
                'transaksi' => [
                    'pendapatan' => $labaRugiTransaksi['pendapatan']['total'] ?? 0,
                    'biaya' => $labaRugiTransaksi['biaya']['total'] ?? 0,
                    'laba_rugi' => $labaRugiTransaksi['laba_rugi'] ?? 0,
                ]
            ];

            $current = strtotime('+1 month', $current);
        }

        return $months;
    }
}

