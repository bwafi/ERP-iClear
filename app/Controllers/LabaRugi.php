<?php

namespace App\Controllers;

use App\Models\ModelAuth;
use App\Models\ModelUnit;
use App\Models\ModelJurnal;
use App\Models\ModelPenjualan;
use App\Models\ModelService;
use App\Models\ModelKasMasuk;
use App\Models\ModelKasKeluar;
use App\Models\ModelHppKlaimLoss;

class LabaRugi extends BaseController
{
    protected $AuthModel;
    protected $UnitModel;
    protected $JurnalModel;
    protected $PenjualanModel;
    protected $ServiceModel;
    protected $KasMasukModel;
    protected $KasKeluarModel;

    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->UnitModel = new ModelUnit();
        $this->JurnalModel = new ModelJurnal();
        $this->PenjualanModel = new ModelPenjualan();
        $this->ServiceModel = new ModelService();
        $this->KasMasukModel = new ModelKasMasuk();
        $this->KasKeluarModel = new ModelKasKeluar();
    }

    public function index()
    {
        $akun = $this->AuthModel->getById(session('ID_AKUN'));
        $unit = $this->UnitModel->getUnit();

        $tanggal_awal = $this->request->getGet('tanggal_awal') ?? date('Y-m-01');
        $tanggal_akhir = $this->request->getGet('tanggal_akhir') ?? date('Y-m-t');
        $id_unit = $this->request->getGet('id_unit');
        
        // Handle multiple units - convert to array
        if ($id_unit && !is_array($id_unit)) {
            $id_unit = [$id_unit];
        }
        
        // If single unit selected, use first one for backward compatibility
        $id_unit_single = (is_array($id_unit) && count($id_unit) == 1) ? $id_unit[0] : (is_array($id_unit) ? null : $id_unit);
        if ($id_unit_single == "") {
            $id_unit_single = null;
        }
        $jenis_laporan = $this->request->getGet('jenis_laporan') ?: 'jurnal';

        // Data untuk laporan berdasarkan jurnal
        $data_jurnal = [];
        if ($jenis_laporan == 'jurnal') {
            $data_jurnal = $this->JurnalModel->getLabaRugiFromJurnal($tanggal_awal, $tanggal_akhir, $id_unit_single, 9);
        }

        // Data untuk laporan berdasarkan transaksi
        $data_transaksi = [];
        if ($jenis_laporan == 'transaksi') {
            $data_transaksi = $this->getLabaRugiFromTransaksi($tanggal_awal, $tanggal_akhir, $id_unit_single);
        }

        $data = [
            'akun' => $akun,
            'unit' => $unit,
            'tanggal_awal' => $tanggal_awal,
            'tanggal_akhir' => $tanggal_akhir,
            'id_unit' => $id_unit, // Keep as array
            'jenis_laporan' => $jenis_laporan,
            'data_jurnal' => $data_jurnal,
            'data_transaksi' => $data_transaksi,
            'body' => 'laporan/laba_rugi'
        ];
        // die(json_encode($data));
        return view('template', $data);
    }

    /**
     * Mendapatkan data laba rugi dari transaksi (POS, Service, Kas Masuk, Kas Keluar)
     */
    private function getLabaRugiFromTransaksi($tanggal_awal = null, $tanggal_akhir = null, $id_unit = null)
    {
        $db = \Config\Database::connect();

        $applyTanggalFilter = function ($builder, string $column) use ($tanggal_awal, $tanggal_akhir) {
            if ($tanggal_awal && $tanggal_akhir) {
                $builder->where("DATE({$column}) >=", $tanggal_awal)
                    ->where("DATE({$column}) <=", $tanggal_akhir);
            } elseif ($tanggal_awal) {
                $builder->where("DATE({$column}) >=", $tanggal_awal);
            } elseif ($tanggal_akhir) {
                $builder->where("DATE({$column}) <=", $tanggal_akhir);
            }
        };

        // Pendapatan: Penjualan
        $builder_penjualan = $db->table('penjualan');
        $builder_penjualan->selectSum('total_penjualan', 'total');
        $applyTanggalFilter($builder_penjualan, 'penjualan.tanggal');
        if ($id_unit) {
            $builder_penjualan->where('penjualan.unit_idunit', $id_unit);
        }
        $penjualan = $builder_penjualan->get()->getRow();
        $total_penjualan = $penjualan->total ?? 0;

        $penjualan_detail_builder = $db->table('penjualan')
            ->select('penjualan.kode_invoice, penjualan.tanggal, penjualan.total_penjualan, unit.NAMA_UNIT as nama_unit, pelanggan.nama as nama_pelanggan')
            ->join('unit', 'unit.idunit = penjualan.unit_idunit', 'left')
            ->join('pelanggan', 'pelanggan.id_pelanggan = penjualan.id_pelanggan', 'left');
        $applyTanggalFilter($penjualan_detail_builder, 'penjualan.tanggal');
        if ($id_unit) {
            $penjualan_detail_builder->where('penjualan.unit_idunit', $id_unit);
        }
        $penjualan_detail = $penjualan_detail_builder
            ->orderBy('penjualan.tanggal', 'DESC')
            ->get()->getResult();

        // Pendapatan: Service
        $builder_service = $db->table('service');
        $builder_service->selectSum('harus_dibayar', 'total')
            ->where('status_service', 4);
        $applyTanggalFilter($builder_service, 'service.created_at');
        if ($id_unit) {
            $builder_service->where('service.unit_idunit', $id_unit);
        }
        $service = $builder_service->get()->getRow();
        $total_service = $service->total ?? 0;

        $service_detail_builder = $db->table('service')
            ->select('service.no_service, service.created_at, service.harus_dibayar, unit.NAMA_UNIT as nama_unit, pelanggan.nama as nama_pelanggan')
            ->join('unit', 'unit.idunit = service.unit_idunit', 'left')
            ->join('pelanggan', 'pelanggan.id_pelanggan = service.pelanggan_id_pelanggan', 'left')
            ->where('service.status_service', 4);
        $applyTanggalFilter($service_detail_builder, 'service.created_at');
        if ($id_unit) {
            $service_detail_builder->where('service.unit_idunit', $id_unit);
        }
        $service_detail = $service_detail_builder
            ->orderBy('service.created_at', 'DESC')
            ->get()->getResult();

        // Pendapatan: Kas Masuk
        $builder_kas_masuk = $db->table('kas_masuk');
        $builder_kas_masuk->selectSum('jumlah', 'total');
        $applyTanggalFilter($builder_kas_masuk, 'kas_masuk.tanggal');
        if ($id_unit) {
            $builder_kas_masuk->where('kas_masuk.idunit', $id_unit);
        }
        $kas_masuk = $builder_kas_masuk->get()->getRow();
        $total_kas_masuk = $kas_masuk->total ?? 0;

        $kas_masuk_detail_builder = $db->table('kas_masuk')
            ->select('kas_masuk.tanggal, kategori_kas.kategori, kas_masuk.deskripsi, kas_masuk.jumlah, unit.NAMA_UNIT as nama_unit')
            ->join('kategori_kas', 'kategori_kas.idkategori_kas = kas_masuk.kategori_idkategori', 'left')
            ->join('unit', 'unit.idunit = kas_masuk.idunit', 'left');
        $applyTanggalFilter($kas_masuk_detail_builder, 'kas_masuk.tanggal');
        if ($id_unit) {
            $kas_masuk_detail_builder->where('kas_masuk.idunit', $id_unit);
        }
        $kas_masuk_detail = $kas_masuk_detail_builder
            ->orderBy('kas_masuk.tanggal', 'DESC')
            ->get()->getResult();

        // Biaya: Kas Keluar
        $builder_kas_keluar = $db->table('kas_keluar');
        $builder_kas_keluar->selectSum('jumlah', 'total');
        $applyTanggalFilter($builder_kas_keluar, 'kas_keluar.tanggal');
        if ($id_unit) {
            $builder_kas_keluar->where('kas_keluar.idunit', $id_unit);
        }
        $kas_keluar = $builder_kas_keluar->get()->getRow();
        $total_kas_keluar = $kas_keluar->total ?? 0;

        $kas_keluar_detail_builder = $db->table('kas_keluar')
            ->select('kas_keluar.tanggal, kategori_kas.kategori, kas_keluar.deskripsi, kas_keluar.jumlah, unit.NAMA_UNIT as nama_unit')
            ->join('kategori_kas', 'kategori_kas.idkategori_kas = kas_keluar.kategori_idkategori', 'left')
            ->join('unit', 'unit.idunit = kas_keluar.idunit', 'left');
        $applyTanggalFilter($kas_keluar_detail_builder, 'kas_keluar.tanggal');
        if ($id_unit) {
            $kas_keluar_detail_builder->where('kas_keluar.idunit', $id_unit);
        }
        $kas_keluar_detail = $kas_keluar_detail_builder
            ->orderBy('kas_keluar.tanggal', 'DESC')
            ->get()->getResult();

        // Biaya: HPP Penjualan
        $builder_hpp = $db->table('detail_penjualan');
        $builder_hpp->select('SUM(detail_penjualan.hpp_penjualan * detail_penjualan.jumlah) as total', false)
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan');
        $applyTanggalFilter($builder_hpp, 'penjualan.tanggal');
        if ($id_unit) {
            $builder_hpp->where('penjualan.unit_idunit', $id_unit);
        }
        $hpp = $builder_hpp->get()->getRow();
        $total_hpp = $hpp->total ?? 0;

        $hpp_detail_builder = $db->table('detail_penjualan')
            ->select('penjualan.kode_invoice, penjualan.tanggal, barang.nama_barang, detail_penjualan.jumlah, detail_penjualan.hpp_penjualan, (detail_penjualan.hpp_penjualan * detail_penjualan.jumlah) as total_hpp, unit.NAMA_UNIT as nama_unit')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            ->join('barang', 'barang.idbarang = detail_penjualan.barang_idbarang', 'left')
            ->join('unit', 'unit.idunit = detail_penjualan.unit_idunit', 'left');
        $applyTanggalFilter($hpp_detail_builder, 'penjualan.tanggal');
        if ($id_unit) {
            $hpp_detail_builder->where('penjualan.unit_idunit', $id_unit);
        }
        $hpp_detail = $hpp_detail_builder
            ->orderBy('penjualan.tanggal', 'DESC')
            ->get()->getResult();

        // Biaya: HPP Service
        $builder_hpp_service = $db->table('service_sparepart');
        $builder_hpp_service->select('SUM(service_sparepart.hpp_penjualan * service_sparepart.jumlah) as total', false)
            ->join('service', 'service.idservice = service_sparepart.service_idservice')
            ->where('service.status_service', 4);
        $applyTanggalFilter($builder_hpp_service, 'service.created_at');
        if ($id_unit) {
            $builder_hpp_service->where('service.unit_idunit', $id_unit);
        }
        $hpp_service = $builder_hpp_service->get()->getRow();
        $total_hpp_service = $hpp_service->total ?? 0;

        $hpp_service_detail_builder = $db->table('service_sparepart')
            ->select('service.no_service, service.created_at, barang.nama_barang, service_sparepart.jumlah, service_sparepart.hpp_penjualan, (service_sparepart.hpp_penjualan * service_sparepart.jumlah) as total_hpp, unit.NAMA_UNIT as nama_unit')
            ->join('service', 'service.idservice = service_sparepart.service_idservice')
            ->join('barang', 'barang.idbarang = service_sparepart.barang_idbarang', 'left')
            ->join('unit', 'unit.idunit = service_sparepart.unit_idunit', 'left')
            ->where('service.status_service', 4);
        $applyTanggalFilter($hpp_service_detail_builder, 'service.created_at');
        if ($id_unit) {
            $hpp_service_detail_builder->where('service.unit_idunit', $id_unit);
        }
        $hpp_service_detail = $hpp_service_detail_builder
            ->orderBy('service.created_at', 'DESC')
            ->get()->getResult();

        // Biaya: HPP klaim garansi, periode mengikuti tanggal_hpp_klaim.
        $hppKlaimModel = new ModelHppKlaimLoss();
        $lossStart = $tanggal_awal ?: '1970-01-01';
        $lossEnd = $tanggal_akhir ?: date('Y-m-d');
        $total_hpp_klaim = $hppKlaimModel->getTotalLoss($lossStart, $lossEnd, $id_unit);
        $hpp_klaim_detail = $hppKlaimModel->getLoss($lossStart, $lossEnd, $id_unit);

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
            'detail' => [
                'pendapatan' => [
                    'penjualan' => $penjualan_detail,
                    'service' => $service_detail,
                    'kas_masuk' => $kas_masuk_detail,
                ],
                'biaya' => [
                    'kas_keluar' => $kas_keluar_detail,
                    'hpp_penjualan' => $hpp_detail,
                    'hpp_service' => $hpp_service_detail,
                    'hpp_klaim' => $hpp_klaim_detail,
                ],
            ],
            'laba_rugi' => $laba_rugi
        ];
    }

    public function laporan_unit()
    {
        $akun = $this->AuthModel->getById(session('ID_AKUN'));
        $unit = $this->UnitModel->getUnit();

        $tanggal_awal = $this->request->getGet('tanggal_awal') ?? date('Y-m-01');
        $tanggal_akhir = $this->request->getGet('tanggal_akhir') ?? date('Y-m-t');
        $id_unit = $this->request->getGet('id_unit') ?: null;
        $jenis_laporan = $this->request->getGet('jenis_laporan') ?: 'jurnal'; // 'jurnal' atau 'transaksi'

        // Data untuk laporan berdasarkan jurnal
        $data_jurnal = [];
        if ($jenis_laporan == 'jurnal') {
            $data_jurnal = $this->JurnalModel->getLabaRugiFromJurnal($tanggal_awal, $tanggal_akhir, $id_unit);
        }

        // Data untuk laporan berdasarkan transaksi
        $data_transaksi = [];
        if ($jenis_laporan == 'transaksi') {
            $data_transaksi = $this->getLabaRugiFromTransaksi($tanggal_awal, $tanggal_akhir, $id_unit);
        }

        $data = [
            'akun' => $akun,
            'unit' => $unit,
            'tanggal_awal' => $tanggal_awal,
            'tanggal_akhir' => $tanggal_akhir,
            'id_unit' => $id_unit,
            'jenis_laporan' => $jenis_laporan,
            'data_jurnal' => $data_jurnal,
            'data_transaksi' => $data_transaksi,
            'body' => 'laporan/laba_rugi_unit'
        ];

        return view('template', $data);
    }

    public function cetak_standar()
    {
        $tanggal_awal = $this->request->getGet('tanggal_awal') ?: null;
        $tanggal_akhir = $this->request->getGet('tanggal_akhir') ?: null;
        $id_unit = $this->request->getGet('id_unit') ?: null;
        $show_saldo_0 = $this->request->getGet('show_saldo_0') ?: 1;

        $id_unit = !empty($id_units) ? $id_units[0] : null;
        $data_laba_rugi = $this->JurnalModel->getLabaRugiFromJurnal($tanggal_awal, $tanggal_akhir, $id_unit, $show_saldo_0);

        $nama_unit = "Semua Cabang";
        if ($id_unit) {
            $unit = $this->UnitModel->find($id_unit);
            $nama_unit = $unit ? $unit->NAMA_UNIT : "Semua Cabang";
        }

        $data = [
            'tanggal_awal' => $tanggal_awal,
            'tanggal_akhir' => $tanggal_akhir,
            'nama_unit' => $nama_unit,
            'data_laba_rugi' => $data_laba_rugi,
        ];
        return view('laporan/cetak_laba_rugi_standar', $data);
    }

    /**
     * Cetak laporan perbandingan antar unit
     */
    public function cetak_perbandingan()
    {
        $tanggal_awal = $this->request->getGet('tanggal_awal') ?: null;
        $tanggal_akhir = $this->request->getGet('tanggal_akhir') ?: null;
        $id_unit_param = $this->request->getGet('id_unit') ?: null;

        if (!$id_unit_param) {
            return redirect()->to(base_url('LaporanKeuangan/laba_rugi'))->with('error', 'Pilih minimal 2 unit untuk perbandingan');
        }

        // Handle multiple units from comma-separated string
        $id_units = explode(',', $id_unit_param);
        $id_units = array_filter(array_map('trim', $id_units));

        if (count($id_units) < 2) {
            return redirect()->to(base_url('LaporanKeuangan/laba_rugi'))->with('error', 'Pilih minimal 2 unit untuk perbandingan');
        }

        // Get data for each unit
        $data_per_unit = [];
        $units_info = [];
        
        foreach ($id_units as $id_unit) {
            $unit = $this->UnitModel->find($id_unit);
            if ($unit) {
                $units_info[$id_unit] = $unit->NAMA_UNIT;
                $data_per_unit[$id_unit] = $this->JurnalModel->getLabaRugiFromJurnal($tanggal_awal, $tanggal_akhir, $id_unit);
            }
        }

        // Calculate totals
        $total_pendapatan_all = 0;
        $total_biaya_all = 0;
        foreach ($data_per_unit as $data) {
            $total_pendapatan_all += $data['total_pendapatan'];
            $total_biaya_all += $data['total_biaya'];
        }
        $total_laba_rugi_all = $total_pendapatan_all - $total_biaya_all;

        $data = [
            'tanggal_awal' => $tanggal_awal,
            'tanggal_akhir' => $tanggal_akhir,
            'units_info' => $units_info,
            'data_per_unit' => $data_per_unit,
            'total_pendapatan_all' => $total_pendapatan_all,
            'total_biaya_all' => $total_biaya_all,
            'total_laba_rugi_all' => $total_laba_rugi_all,
        ];

        return view('laporan/cetak_laba_rugi_perbandingan', $data);
    }

    /**
     * Form laporan perbandingan periode
     */
    public function laporan_perbandingan_periode()
    {
        $akun = $this->AuthModel->getById(session('ID_AKUN'));
        $unit = $this->UnitModel->getUnit();

        $tanggal_awal_1 = $this->request->getGet('tanggal_awal_1') ?: date('Y-m-01', strtotime('-1 month'));
        $tanggal_akhir_1 = $this->request->getGet('tanggal_akhir_1') ?: date('Y-m-t', strtotime('-1 month'));
        $tanggal_awal_2 = $this->request->getGet('tanggal_awal_2') ?: date('Y-m-01');
        $tanggal_akhir_2 = $this->request->getGet('tanggal_akhir_2') ?: date('Y-m-t');
        $id_unit_param = $this->request->getGet('id_unit') ?: null;

        // Handle multiple units from comma-separated string
        $id_units = [];
        if ($id_unit_param) {
            $id_units = explode(',', $id_unit_param);
            $id_units = array_filter(array_map('trim', $id_units));
        }

        $data = [
            'akun' => $akun,
            'unit' => $unit,
            'tanggal_awal_1' => $tanggal_awal_1,
            'tanggal_akhir_1' => $tanggal_akhir_1,
            'tanggal_awal_2' => $tanggal_awal_2,
            'tanggal_akhir_2' => $tanggal_akhir_2,
            'id_unit' => $id_units,
            'body' => 'laporan/laba_rugi_perbandingan_periode'
        ];

        return view('template', $data);
    }

    /**
     * Cetak laporan perbandingan periode
     */
    public function cetak_perbandingan_periode()
    {
        $tanggal_awal_1 = $this->request->getGet('tanggal_awal_1') ?: null;
        $tanggal_akhir_1 = $this->request->getGet('tanggal_akhir_1') ?: null;
        $tanggal_awal_2 = $this->request->getGet('tanggal_awal_2') ?: null;
        $tanggal_akhir_2 = $this->request->getGet('tanggal_akhir_2') ?: null;
        $id_unit_param = $this->request->getGet('id_unit') ?: null;

        if (!$tanggal_awal_1 || !$tanggal_akhir_1 || !$tanggal_awal_2 || !$tanggal_akhir_2) {
            return redirect()->to(base_url('LaporanKeuangan/laba_rugi'))->with('error', 'Semua tanggal periode harus diisi');
        }

        // Handle unit selection
        $id_units = [];
        if ($id_unit_param) {
            $id_units = explode(',', $id_unit_param);
            $id_units = array_filter(array_map('trim', $id_units));
        }

        // Get data for periode 1
        $data_periode_1 = [];
        if (!empty($id_units)) {
            // Multiple units - aggregate
            $data_periode_1 = $this->getLabaRugiMultipleUnits($tanggal_awal_1, $tanggal_akhir_1, $id_units);
        } else {
            // All units
            $data_periode_1 = $this->getLabaRugiMultipleUnits($tanggal_awal_1, $tanggal_akhir_1, []);
        }

        // Get data for periode 2
        $data_periode_2 = [];
        if (!empty($id_units)) {
            // Multiple units - aggregate
            $data_periode_2 = $this->getLabaRugiMultipleUnits($tanggal_awal_2, $tanggal_akhir_2, $id_units);
        } else {
            // All units
            $data_periode_2 = $this->getLabaRugiMultipleUnits($tanggal_awal_2, $tanggal_akhir_2, []);
        }

        // Get unit names if specific units selected
        $nama_unit = "Semua Cabang";
        if (!empty($id_units)) {
            $unit_names = [];
            foreach ($id_units as $id_unit) {
                $unit = $this->UnitModel->find($id_unit);
                if ($unit) {
                    $unit_names[] = $unit->NAMA_UNIT;
                }
            }
            $nama_unit = implode(', ', $unit_names);
        }

        $data = [
            'tanggal_awal_1' => $tanggal_awal_1,
            'tanggal_akhir_1' => $tanggal_akhir_1,
            'tanggal_awal_2' => $tanggal_awal_2,
            'tanggal_akhir_2' => $tanggal_akhir_2,
            'nama_unit' => $nama_unit,
            'data_periode_1' => $data_periode_1,
            'data_periode_2' => $data_periode_2,
        ];

        return view('laporan/cetak_laba_rugi_perbandingan_periode', $data);
    }

    /**
     * Get laba rugi for multiple units (aggregated)
     */
    private function getLabaRugiMultipleUnits($tanggal_awal = null, $tanggal_akhir = null, $id_units = [])
    {
        $db = \Config\Database::connect();
        
        $applyFilters = function ($builder) use ($tanggal_awal, $tanggal_akhir, $id_units) {
            if (!empty($id_units)) {
                $builder->whereIn('j.id_unit', $id_units);
            }
            if (!empty($tanggal_awal) && !empty($tanggal_akhir)) {
                $builder->where('DATE(j.tanggal) >=', $tanggal_awal)
                    ->where('DATE(j.tanggal) <=', $tanggal_akhir);
            } elseif (!empty($tanggal_awal)) {
                $builder->where('DATE(j.tanggal) >=', $tanggal_awal);
            } elseif (!empty($tanggal_akhir)) {
                $builder->where('DATE(j.tanggal) <=', $tanggal_akhir);
            }
        };

        // Pendapatan
        $builder_pendapatan = $db->table('no_akun na');
        $builder_pendapatan->select("
            na.no_akun,
            na.nama_akun,
            COALESCE(SUM(j.kredit), 0) - COALESCE(SUM(j.debet), 0) as saldo
        ");
        $builder_pendapatan->join('jurnal j', 'na.no_akun = j.no_akun', 'left');
        $builder_pendapatan->like('na.no_akun', '4', 'after');
        $builder_pendapatan->where('CHAR_LENGTH(na.no_akun)', 10);
        $builder_pendapatan->where('RIGHT(na.no_akun, 7) !=', '0000000');
        
        if (!empty($id_units)) {
            $builder_pendapatan->whereIn('j.id_unit', $id_units);
        }
        
        if ($tanggal_awal !== null && $tanggal_akhir !== null) {
            $builder_pendapatan->where('DATE(j.tanggal) >=', $tanggal_awal)
                ->where('DATE(j.tanggal) <=', $tanggal_akhir);
        }
        
        $builder_pendapatan->groupBy('na.no_akun, na.nama_akun');
        $builder_pendapatan->having('saldo !=', 0);
        $builder_pendapatan->orderBy('na.no_akun', 'asc');
        $pendapatan = $builder_pendapatan->get()->getResult();
        

        // Biaya - PISAHKAN menjadi 2 kategori
        // Beban Pokok Penjualan (prefix 5)
        $builder_beban_pokok = $db->table('no_akun na');
        $builder_beban_pokok->select("
            na.no_akun,
            na.nama_akun,
            COALESCE(SUM(j.debet), 0) - COALESCE(SUM(j.kredit), 0) as saldo
        ");
        $builder_beban_pokok->join('jurnal j', 'na.no_akun = j.no_akun', 'left');
        $builder_beban_pokok->like('na.no_akun', '5', 'after');
        $builder_beban_pokok->where('CHAR_LENGTH(na.no_akun)', 10);
        $builder_beban_pokok->where('RIGHT(na.no_akun, 7) !=', '0000000');

        if (!empty($id_units)) {
            $builder_beban_pokok->whereIn('j.id_unit', $id_units);
        }

        if ($tanggal_awal !== null && $tanggal_akhir !== null) {
            $builder_beban_pokok->where('DATE(j.tanggal) >=', $tanggal_awal)
                ->where('DATE(j.tanggal) <=', $tanggal_akhir);
        }

        $builder_beban_pokok->groupBy('na.no_akun, na.nama_akun');
        $builder_beban_pokok->having('saldo !=', 0);
        $builder_beban_pokok->orderBy('na.no_akun', 'asc');
        $beban_pokok_penjualan = $builder_beban_pokok->get()->getResult();

        // Beban Operasional (prefix 6 dan 7)
        $builder_beban_operasional = $db->table('no_akun na');
        $builder_beban_operasional->select("
            na.no_akun,
            na.nama_akun,
            COALESCE(SUM(j.debet), 0) - COALESCE(SUM(j.kredit), 0) as saldo
        ");
        $builder_beban_operasional->join('jurnal j', 'na.no_akun = j.no_akun', 'left');
        $builder_beban_operasional->groupStart()
            ->like('na.no_akun', '6', 'after')
            ->orLike('na.no_akun', '7', 'after')
            ->groupEnd();
        $builder_beban_operasional->where('CHAR_LENGTH(na.no_akun)', 10);
        $builder_beban_operasional->where('RIGHT(na.no_akun, 7) !=', '0000000');
        
        if (!empty($id_units)) {
            $builder_beban_operasional->whereIn('j.id_unit', $id_units);
        }
        
        if ($tanggal_awal !== null && $tanggal_akhir !== null) {
            $builder_beban_operasional->where('DATE(j.tanggal) >=', $tanggal_awal)
                ->where('DATE(j.tanggal) <=', $tanggal_akhir);
        }
        
        $builder_beban_operasional->groupBy('na.no_akun, na.nama_akun');
        $builder_beban_operasional->having('saldo !=', 0);
        $builder_beban_operasional->orderBy('na.no_akun', 'asc');
        $beban_operasional = $builder_beban_operasional->get()->getResult();

        // 3. Pendapatan Non Operasional (prefix 701)
        $builder_pendapatan_non_operasional = $db->table('no_akun na');
        $builder_pendapatan_non_operasional->select("
            na.no_akun,
            na.nama_akun,
            COALESCE(SUM(j.kredit), 0) - COALESCE(SUM(j.debet), 0) as saldo
        ");
        $builder_pendapatan_non_operasional->join('jurnal j', 'na.no_akun = j.no_akun', 'left');
        $builder_pendapatan_non_operasional->like('na.no_akun', '701', 'after'); // prefix 701 = PENDAPATAN NON OPERASIONAL
        $builder_pendapatan_non_operasional->where('CHAR_LENGTH(na.no_akun)', 10);
        $builder_pendapatan_non_operasional->where('RIGHT(na.no_akun, 7) !=', '0000000'); // bukan parent

        if (!empty($id_units)) {
            $builder_pendapatan_non_operasional->whereIn('j.id_unit', $id_units);
        }
        
        if ($tanggal_awal !== null && $tanggal_akhir !== null) {
            $builder_pendapatan_non_operasional->where('DATE(j.tanggal) >=', $tanggal_awal)
                ->where('DATE(j.tanggal) <=', $tanggal_akhir);
        }
        
        $builder_pendapatan_non_operasional->groupBy('na.no_akun, na.nama_akun');
        $builder_pendapatan_non_operasional->having('saldo !=', 0);
        $builder_pendapatan_non_operasional->orderBy('na.no_akun', 'asc');
        $pendapatan_non_operasional = $builder_pendapatan_non_operasional->get()->getResult();

        // 4. Beban Non Operasional (prefix 702)
        $builder_beban_non_operasional = $db->table('no_akun na');
        $builder_beban_non_operasional->select("
            na.no_akun,
            na.nama_akun,
            COALESCE(SUM(j.debet), 0) - COALESCE(SUM(j.kredit), 0) as saldo
        ");
        $builder_beban_non_operasional->join('jurnal j', 'na.no_akun = j.no_akun', 'left');
        $builder_beban_non_operasional->like('na.no_akun', '702', 'after'); // prefix 702 = BEBAN NON OPERASIONAL
        $builder_beban_non_operasional->where('CHAR_LENGTH(na.no_akun)', 10);
        $builder_beban_non_operasional->where('RIGHT(na.no_akun, 7) !=', '0000000'); // bukan parent

        if (!empty($id_units)) {
            $builder_beban_non_operasional->whereIn('j.id_unit', $id_units);
        }
        
        if ($tanggal_awal !== null && $tanggal_akhir !== null) {
            $builder_beban_non_operasional->where('DATE(j.tanggal) >=', $tanggal_awal)
                ->where('DATE(j.tanggal) <=', $tanggal_akhir);
        }
        
        $builder_beban_non_operasional->groupBy('na.no_akun, na.nama_akun');
        $builder_beban_non_operasional->having('saldo !=', 0);
        $builder_beban_non_operasional->orderBy('na.no_akun', 'asc');
        $beban_non_operasional = $builder_beban_non_operasional->get()->getResult();

        // Detail biaya - juga perlu dipisah
        // Detail beban pokok penjualan (kode 5)
        $builder_detail_beban_pokok = $db->table('jurnal j')
            ->select('j.tanggal, j.no_akun, j.nama_akun, j.keterangan, j.debet, j.kredit, j.id_referensi, j.tabel_referensi, unit.NAMA_UNIT AS nama_unit')
            ->join('unit', 'unit.idunit = j.id_unit', 'left')
            ->like('j.no_akun', '5', 'after')
            ->orderBy('j.tanggal', 'DESC');
        $applyFilters($builder_detail_beban_pokok);
        $detail_beban_pokok = $builder_detail_beban_pokok->get()->getResult();

        // Detail beban operasional (kode 6 dan 7)
        $builder_detail_beban_operasional = $db->table('jurnal j')
            ->select('j.tanggal, j.no_akun, j.nama_akun, j.keterangan, j.debet, j.kredit, j.id_referensi, j.tabel_referensi, unit.NAMA_UNIT AS nama_unit')
            ->join('unit', 'unit.idunit = j.id_unit', 'left')
            ->groupStart()
            ->like('j.no_akun', '6', 'after')
            ->orLike('j.no_akun', '7', 'after')
            ->groupEnd()
            ->orderBy('j.tanggal', 'DESC');
        $applyFilters($builder_detail_beban_operasional);
        $detail_beban_operasional = $builder_detail_beban_operasional->get()->getResult();

        // Detail pendapatan non operasional (kode 701)
        $builder_detail_pendapatan_non_operasional = $db->table('jurnal j')
            ->select('j.tanggal, j.no_akun, j.nama_akun, j.keterangan, j.debet, j.kredit, j.id_referensi, j.tabel_referensi, unit.NAMA_UNIT AS nama_unit')
            ->join('unit', 'unit.idunit = j.id_unit', 'left')
            ->like('j.no_akun', '701', 'after')
            ->orderBy('j.tanggal', 'DESC');
        $applyFilters($builder_detail_pendapatan_non_operasional);
        $detail_pendapatan_non_operasional = $builder_detail_pendapatan_non_operasional->get()->getResult();

        // Detail beban non operasional (kode 702)
        $builder_detail_beban_non_operasional = $db->table('jurnal j')
            ->select('j.tanggal, j.no_akun, j.nama_akun, j.keterangan, j.debet, j.kredit, j.id_referensi, j.tabel_referensi, unit.NAMA_UNIT AS nama_unit')
            ->join('unit', 'unit.idunit = j.id_unit', 'left')
            ->like('j.no_akun', '702', 'after')
            ->orderBy('j.tanggal', 'DESC');
        $applyFilters($builder_detail_beban_non_operasional);
        $detail_beban_non_operasional = $builder_detail_beban_non_operasional->get()->getResult();

        // Hitung total
        $total_pendapatan = array_sum(array_map(function($item) {
            return $item->saldo ?? 0;
        }, $pendapatan));

        $total_beban_pokok_penjualan = array_sum(array_map(function($item) {
            return $item->saldo ?? 0;
        }, $beban_pokok_penjualan));

        $total_beban_operasional = array_sum(array_map(function($item) {
            return $item->saldo ?? 0;
        }, $beban_operasional));

        $total_pendapatan_non_operasional = array_sum(array_map(function($item) {
            return $item->saldo ?? 0;
        }, $pendapatan_non_operasional));

        $total_beban_non_operasional = array_sum(array_map(function($item) {
            return $item->saldo ?? 0;
        }, $beban_non_operasional));

        $total_biaya = $total_beban_pokok_penjualan + $total_beban_operasional;
        $laba_rugi = $total_pendapatan - $total_biaya + $total_pendapatan_non_operasional - $total_beban_non_operasional;

        return [
            'pendapatan' => $pendapatan,
            'total_pendapatan' => $total_pendapatan,
            'beban_pokok_penjualan' => $beban_pokok_penjualan,
            'total_beban_pokok_penjualan' => $total_beban_pokok_penjualan,
            'beban_operasional' => $beban_operasional,
            'total_beban_operasional' => $total_beban_operasional,
            'pendapatan_non_operasional' => $pendapatan_non_operasional,
            'total_pendapatan_non_operasional' => $total_pendapatan_non_operasional,
            'beban_non_operasional' => $beban_non_operasional,
            'total_beban_non_operasional' => $total_beban_non_operasional,
            'biaya' => array_merge($beban_pokok_penjualan, $beban_operasional), // untuk backward compatibility
            'total_biaya' => $total_biaya,
            'laba_rugi' => $laba_rugi,
            'detail' => [
                // 'pendapatan' => $detail_pendapatan,
                'beban_pokok_penjualan' => $detail_beban_pokok,
                'beban_operasional' => $detail_beban_operasional,
                'pendapatan_non_operasional' => $detail_pendapatan_non_operasional,
                'beban_non_operasional' => $detail_beban_non_operasional,
                'biaya' => array_merge($detail_beban_pokok, $detail_beban_operasional), // untuk backward compatibility
            ],
        ];
    }
}


