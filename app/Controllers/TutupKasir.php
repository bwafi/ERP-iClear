<?php

namespace App\Controllers;

use App\Models\ModelTutupKasir;
use CodeIgniter\Controller;

class TutupKasir extends BaseController
{
    protected $db;
    protected $TutupKasir;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
        $this->TutupKasir = new ModelTutupKasir();
    }

    public function index()
    {
        $today = date('Y-m-d');
        $besok = date('Y-m-d', strtotime('+1 day'));

        // ambil unit login
        $unit = session()->get('ID_UNIT');

        // KAS AWAL CASH
        $kasawalcash = $this->db->table('kas_masuk')
            ->select('jumlah as total')
            ->where('DATE(tanggal)', $today)
            ->where('deskripsi', 'kas awal')
            ->where('idbank', null)
            ->where('idunit', $unit)
            ->get()
            ->getRow()->total ?? 0;

        // KAS AWAL TRANSFER
        $kasawaltf = $this->db->table('kas_masuk')
            ->select('jumlah as total')
            ->where('DATE(tanggal)', $today)
            ->where('deskripsi', 'kas awal')
            ->where('idbank !=', null)
            ->where('idunit', $unit)
            ->get()
            ->getRow()->total ?? 0;

        // PENJUALAN TRANSFER
        $tfpenjualan = $this->db->table('penjualan')
            ->selectSum('bayar_bank', 'total')
            ->where('DATE(tanggal)', $today)
            ->where('unit_idunit', $unit)
            ->notLike('kode_invoice', 'srv', 'after')
            ->get()
            ->getRow()->total ?? 0;

        // SERVICE TRANSFER
        $tfservice = $this->db->table('service')
            ->select('SUM(COALESCE(harus_dibayar,0) - COALESCE(bayar_tunai,0)) AS total')
            ->where('DATE(tanggal_selesai)', $today)
            ->where('status_service', 4)
            ->where('unit_idunit', $unit)
            ->get()
            ->getRow()->total ?? 0;

        $transfer = $tfpenjualan + $tfservice;

        // PENJUALAN CASH
        $cashpenjualan = $this->db->table('penjualan')
            ->selectSum('bayar_tunai', 'total')
            ->where('DATE(tanggal)', $today)
            ->where('unit_idunit', $unit)
            ->notLike('kode_invoice', 'srv', 'after')
            ->get()
            ->getRow()->total ?? 0;

        // SERVICE CASH
        $cashservice = $this->db->table('service')
            ->selectSum('bayar_tunai', 'total')
            ->where('DATE(tanggal_selesai)', $today)
            ->where('status_service', 4)
            ->where('unit_idunit', $unit)
            ->get()
            ->getRow()->total ?? 0;

        $cash = $cashpenjualan + $cashservice;
        $total = $transfer + $cash;

        // PENGELUARAN CASH
        $pengeluarancash = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 'total')
            ->where('DATE(tanggal)', $today)
            ->where('idbank', null)
            ->where('idunit', $unit)
            ->get()
            ->getRow()->total ?? 0;

        // PENGELUARAN TF
        $pengeluarantf = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 'total')
            ->where('DATE(tanggal)', $today)
            ->where('idbank !=', null)
            ->where('idunit', $unit)
            ->get()
            ->getRow()->total ?? 0;

        // tutup kasir terakhir per unit
        $tutupkasir = $this->db->table('tutup_kasir')
            ->where('unit', $unit)
            ->orderBy('idtutupkasir', 'DESC')
            ->get()
            ->getRow();

        // cek sudah tutup kasir hari ini atau belum per unit
        $cek = $this->db->table('tutup_kasir')
            ->where('DATE(tanggal)', $today)
            ->where('unit', $unit)
            ->countAllResults();

        if ($cek > 0) {
            return view('template', [
                'today'            => $today,
                'besok'            => $besok,
                'tutupkasir'       => $tutupkasir,
                'kas_awaltf'       => $kasawaltf,
                'kas_awalcash'     => $kasawalcash,
                'total_pendapatan' => $total,
                'transfer'         => $transfer,
                'cash'             => $cash,
                'pengeluarantf'    => $pengeluarantf,
                'pengeluarancash'  => $pengeluarancash,
                'error'            => 'Tutup kasir unit ini hari ini sudah dilakukan',
                'body'             => 'jurnal/tutup_kasir'
            ]);
        }

        return view('template', [
            'tutupkasir'       => $tutupkasir,
            'kas_awaltf'       => $kasawaltf,
            'kas_awalcash'     => $kasawalcash,
            'total_pendapatan' => $total,
            'transfer'         => $transfer,
            'cash'             => $cash,
            'pengeluarantf'    => $pengeluarantf,
            'pengeluarancash'  => $pengeluarancash,
            'body'             => 'jurnal/tutup_kasir'
        ]);
    }

    public function kasirbulanan()
    {
        $tanggal = $this->request->getGet('tanggal')
            ?? date('Y-m-d');

        $unit = $this->request->getGet('unit');

        $builder = $this->db->table('tutup_kasir tk')

            ->select('
                tk.*,
                akun.NAMA_AKUN
            ')

            ->join(
                'akun',
                'akun.ID_AKUN = tk.akun_ID_AKUN',
                'left'
            )

            ->where('DATE(tk.tanggal)', $tanggal);

        // filter unit jika dipilih
        if (!empty($unit)) {
            $builder->where('tk.unit', $unit);
        }

        $tutupkasir = $builder
            ->get()
            ->getRow();

        // bila data kosong
        if (!$tutupkasir) {

            return view('template', [

                'tutupkasir' => null,
                'tanggal'    => $tanggal,
                'selected_unit' => $unit,
                'body' => 'jurnal/kasir_bulanan'

            ]);
        }

        return view('template', [

            'tutupkasir' => $tutupkasir,

            'tanggal' => $tanggal,

            'selected_unit' => $unit,

            // saldo awal
            'kas_awalcash' => $tutupkasir->awal_cash ?? 0,
            'kas_awaltf'   => $tutupkasir->awal_transfer ?? 0,

            // pendapatan
            'cash'         => $tutupkasir->pendapatan_cash ?? 0,
            'transfer'     => $tutupkasir->pendapatan_transfer ?? 0,

            // pengeluaran
            'pengeluarancash' => $tutupkasir->pengeluaran_cash ?? 0,
            'pengeluarantf'   => $tutupkasir->pengeluaran_transfer ?? 0,

            // saldo akhir
            'kas_akhircash' => $tutupkasir->akhir_cash ?? 0,
            'kas_akhirtf'   => $tutupkasir->akhir_transfer ?? 0,

            // total pendapatan
            'total_pendapatan' =>
                ($tutupkasir->pendapatan_cash ?? 0)
                +
                ($tutupkasir->pendapatan_transfer ?? 0),

            'body' => 'jurnal/kasir_bulanan'

        ]);
    }

    public function omsetbulanan()
    {
        $id_jabatan = session()->get('ID_JABATAN');

        // Ambil list unit
        $list_unit = $this->db->table('unit')
            ->get()
            ->getResultArray();

        // Hanya jabatan 1 dan 40 boleh memilih unit
        if (in_array($id_jabatan, [1, 40])) {

            $unit = $this->request->getGet('unit');

            if (!$unit) {
                $unit = session()->get('ID_UNIT');
            }

        } else {

            // Selain jabatan 1 & 40 wajib unit sendiri
            $unit = session()->get('ID_UNIT');
        }

        // ==========================
        // FILTER UNIT
        // ==========================
        $unit = $this->request->getGet('unit');

        if (!$unit) {
            $unit = session()->get('ID_UNIT');
        }

        // LIST UNIT
        $list_unit = $this->db->table('unit')
            ->get()
            ->getResultArray();

        // ==========================
        // TOTAL OMSET BULAN INI
        // ==========================
        $omset_bulan = $this->db->table('detail_penjualan')
            ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            ->where('MONTH(penjualan.tanggal)', date('m'))
            ->where('YEAR(penjualan.tanggal)', date('Y'))
            ->where('penjualan.unit_idunit', $unit)
            ->get()
            ->getRow()
            ->total ?? 0;

        // ==========================
        // TOTAL PELANGGAN
        // ==========================
        $countService = $this->db->table('service')
            ->select('COUNT(idservice) AS total')
            ->where('MONTH(tanggal_selesai)', date('m'))
            ->where('YEAR(tanggal_selesai)', date('Y'))
            ->where('unit_idunit', $unit)
            ->get()
            ->getRow()
            ->total ?? 0;

        $countSales = $this->db->table('penjualan')
            ->select('COUNT(DISTINCT idpenjualan) AS total')
            ->where('MONTH(tanggal)', date('m'))
            ->where('YEAR(tanggal)', date('Y'))
            ->where('unit_idunit', $unit)
            ->like('kode_invoice', 'SLL', 'after')
            ->get()
            ->getRow()
            ->total ?? 0;

        $pelanggan_bulan = $countService + $countSales;

        // ==========================
        // SPAREPART BEST SELLER
        // ==========================
        $bestseller = $this->db->table('stok_barang')
            ->select('stok_barang.nama_barang, stok_barang.total_penjualan')
            ->join(
                'detail_penjualan',
                'detail_penjualan.barang_idbarang = stok_barang.idbarang'
            )
            ->join(
                'penjualan',
                'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan'
            )
            ->like('stok_barang.kode_barang', 'SPRT', 'after')
            ->where('MONTH(penjualan.tanggal)', date('m'))
            ->where('YEAR(penjualan.tanggal)', date('Y'))
            ->where('penjualan.unit_idunit', $unit)
            ->orderBy('stok_barang.total_penjualan', 'DESC')
            ->limit(1)
            ->get()
            ->getRow() ?? (object)[
                'nama_barang' => 'Belum ada',
                'total_penjualan' => 0
            ];

        // ==========================
        // PRODUCT SERVICE FAST MOVING
        // ==========================
        $bestsellerproduct = $this->db->table('service')
            ->select("
                LOWER(
                    CONCAT(
                        SUBSTRING_INDEX(tipe_hp,' ',1),
                        ' ',
                        REGEXP_SUBSTR(tipe_hp,'[0-9]+')
                    )
                ) AS keyword_hp,
                COUNT(*) AS total
            ")
            ->where('MONTH(tanggal_selesai)', date('m'))
            ->where('YEAR(tanggal_selesai)', date('Y'))
            ->where('unit_idunit', $unit)
            ->groupBy('keyword_hp')
            ->orderBy('total', 'DESC')
            ->limit(1)
            ->get()
            ->getRow() ?? (object)[
                'keyword_hp' => 'Belum ada',
                'total' => 0
            ];

        // ==========================
        // SPAREPART KELUAR
        // ==========================
        $sparepart_keluar = $this->db->table('detail_penjualan dp')
            ->selectCount('b.nama_barang', 'total')
            ->join('barang b', 'b.idbarang = dp.barang_idbarang')
            ->join('penjualan p', 'p.idpenjualan = dp.penjualan_idpenjualan')
            ->where('MONTH(p.tanggal)', date('m'))
            ->where('YEAR(p.tanggal)', date('Y'))
            ->where('p.unit_idunit', $unit)
            ->where('b.idkategori', 3)
            ->notLike('b.nama_barang', 'mesin')
            ->notLike('b.nama_barang', 'PUP')
            ->notLike('b.nama_barang', 'RESTORE')
            ->notLike('b.nama_barang', 'FLASH')
            ->notLike('b.nama_barang', 'CLEANING')
            ->notLike('b.nama_barang', 'JASA')
            ->where('dp.hpp_penjualan >', 0)
            ->get()
            ->getRow()
            ->total ?? 0;

        // ==========================
        // OMSET HARI INI
        // ==========================
        $omset_hari_ini = $this->db->table('detail_penjualan')
            ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
            ->join(
                'penjualan',
                'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan'
            )
            ->where('DATE(penjualan.tanggal)', date('Y-m-d'))
            ->where('penjualan.unit_idunit', $unit)
            ->get()
            ->getRow()
            ->total ?? 0;
            
        $hpp = $this->db->table('detail_penjualan')
            ->select('SUM(hpp_penjualan) AS total')
            ->join(
                'penjualan',
                'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan'
            )
            ->join(
                'barang',
                'barang.idbarang = detail_penjualan.barang_idbarang'
            )
            ->where('MONTH(penjualan.tanggal)', date('m'))
            ->where('YEAR(penjualan.tanggal)', date('Y'))
            ->where('penjualan.unit_idunit', $unit)
            ->where('barang.idkategori =', 3)
            ->get()
            ->getRow()
            ->total ?? 0;

        $hpp_global = $this->db->table('detail_penjualan')
            ->select('SUM(hpp_penjualan) AS total')
            ->join(
                'penjualan',
                'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan'
            )
            ->join(
                'barang',
                'barang.idbarang = detail_penjualan.barang_idbarang'
            )
            ->where('MONTH(penjualan.tanggal)', date('m'))
            ->where('YEAR(penjualan.tanggal)', date('Y'))
            ->where('barang.idkategori =', 3)
            ->get()
            ->getRow()
            ->total ?? 0;

        // ==========================
        // DATA GRAFIK
        // ==========================
        $results = $this->db->table('detail_penjualan')
            ->select("
                DATE(penjualan.tanggal) AS tgl,
                SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total
            ")
            ->join(
                'penjualan',
                'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan'
            )
            ->where('MONTH(penjualan.tanggal)', date('m'))
            ->where('YEAR(penjualan.tanggal)', date('Y'))
            ->where('penjualan.unit_idunit', $unit)
            ->groupBy('DATE(penjualan.tanggal)')
            ->get()
            ->getResult();

        $dataHarian = [];

        foreach ($results as $row) {
            $dataHarian[$row->tgl] = $row->total;
        }

        $jumlahHari = date('t');
        $listHari = [];

        for ($i = 1; $i <= $jumlahHari; $i++) {

            $tgl = date('Y-m-') . str_pad($i, 2, '0', STR_PAD_LEFT);

            $listHari[] = [
                'tanggal' => $tgl,
                'total' => $dataHarian[$tgl] ?? 0
            ];
        }

        return view('template', [
            'list_unit'      => $list_unit,
            'selected_unit'  => $unit,
            'id_jabatan'     => $id_jabatan,
            'hpp'           => $hpp,
            'hpp_global'    => $hpp_global,
            'listHari'          => $listHari,
            'bestseller'        => $bestseller,
            'bestsellerproduct' => $bestsellerproduct,
            'omset_bulan'       => $omset_bulan,
            'pelanggan_bulan'   => $pelanggan_bulan,
            'sparepart_keluar'  => $sparepart_keluar,
            'omset_hari_ini'    => $omset_hari_ini,
            'body'              => 'jurnal/omset_bulanan'
        ]);
    }

    public function assetberjalan()
    {
        
        $db = \Config\Database::connect();


        $id_jabatan = session()->get('ID_JABATAN');

        // Ambil list unit
        $list_unit = $this->db->table('unit')
            ->get()
            ->getResultArray();

        // Hanya jabatan 1 dan 40 boleh memilih unit
        if (in_array($id_jabatan, [1, 40])) {

            $unit = $this->request->getGet('unit');

            if (!$unit) {
                $unit = session()->get('ID_UNIT');
            }

        } else {

            // Selain jabatan 1 & 40 wajib unit sendiri
            $unit = session()->get('ID_UNIT');
        }

        // ==========================
        // FILTER UNIT
        // ==========================
        $unit = $this->request->getGet('unit');

        if (!$unit) {
            $unit = session()->get('ID_UNIT');
        }

        // LIST UNIT
        $list_unit = $this->db->table('unit')
            ->get()
            ->getResultArray();

        // ==========================
        // TOTAL OMSET BULAN INI
        // ==========================
        $omset_bulan = $this->db->table('detail_penjualan')
            ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            ->where('MONTH(penjualan.tanggal)', date('m'))
            ->where('YEAR(penjualan.tanggal)', date('Y'))
            ->where('penjualan.unit_idunit', $unit)
            ->get()
            ->getRow()
            ->total ?? 0;

        $totalGajiUnit = 0;

        // ambil semua karyawan dalam unit tertentu
        $karyawanUnit = $this->db->table('akun')
            ->where('ID_UNIT', $unit) // misal unit 1
            ->get()
            ->getResultArray();

        foreach ($karyawanUnit as $pegawai) {

            $selected_karyawan = $pegawai['ID_AKUN'];

            // LIST KARYAWAN

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
                'customer'  => 118,
                'closing'   => 96,
                'upselling' => 14,
                'followup'  => 80,
                'roas'      => 4,
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

        $aktual_customer = [];
        foreach ([1, 2, 3, 4] as $idUnit) {
            $countService = $this->db->table('service')
                ->select('COUNT(idservice) AS total')
                ->where('MONTH(tanggal_selesai)', date('m'))
                ->where('YEAR(tanggal_selesai)', date('Y'))
                ->where('unit_idunit', $idUnit)
                ->get()
                ->getRow()
                ->total ?? 0;

            $countSales = $this->db->table('penjualan')
                ->select('COUNT(DISTINCT idpenjualan) AS total')
                ->where('MONTH(tanggal)', date('m'))
                ->where('YEAR(tanggal)', date('Y'))
                ->where('unit_idunit', $idUnit)
                ->like('kode_invoice', 'SLL', 'after')
                ->get()
                ->getRow()
                ->total ?? 0;

            $aktual_customer[$idUnit] = $countService + $countSales;
        }
        $aktual_customer = $aktual_customer[$unit] ?? 0;

        $aktual_tutup_kasir    = $this->db->table('tutup_kasir')
                                    ->select('COUNT(status) AS total')
                                    ->where('MONTH(tanggal)', date('m'))
                                    ->where('YEAR(tanggal)', date('Y'))
                                    ->where('unit', $unit)
                                    ->get()
                                    ->getRow();
        $total_tutup_kasir = $aktual_tutup_kasir->total ?? 0;

        $aktual_opname         = $this->db->table('stok_opname_draft')
                                    ->select('COUNT(DISTINCT DATE(tanggal)) AS total')
                                    ->where('unit_idunit', $unit)
                                    ->where('MONTH(tanggal)', date('m'), false)
                                    ->where('YEAR(tanggal)', date('Y'), false)
                                    ->get()
                                    ->getRow()
                                    ->total;

        $aktual_absen          = 90;

        $aktual_divisi         = $this->db->table('penilaian')
                                    ->select('Avg(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
                                    ->get()
                                    ->getRow();
        $total_divisi = $aktual_divisi->total ?? 0;

        $ak_kebersihan         = $this->db->table('penilaian')
                                    ->select('Avg(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
                                    ->where('aspek =', 'kebersihan')
                                    ->get()
                                    ->getRow();
        $ttl_kebersihan = $ak_kebersihan->total ?? 0;

        $ak_seragam         = $this->db->table('penilaian')
                                    ->select('Avg(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
                                    ->where('aspek =', 'seragam')
                                    ->get()
                                    ->getRow();
        $ttl_seragam = $ak_seragam->total ?? 0;

        $ak_kepatuhan          = $this->db->table('penilaian')
                                    ->select('Avg(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
                                    ->where('aspek =', 'kepatuhan sop')
                                    ->get()
                                    ->getRow();
        $ttl_kepatuhan  = $ak_kepatuhan ->total ?? 0;
        
        $aktual_closing        = $this->db->table('penilaian')
                                    ->select('SUM(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
                                    ->where('pegawai_idpegawai', $selected_karyawan)
                                    ->where('aspek =', 'closing')
                                    ->get()
                                    ->getRow();
        $total_closing = $aktual_closing->total ?? 0;

        $aktual_upselling      = $this->db->table('penilaian')
                                    ->select('SUM(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
                                    ->where('pegawai_idpegawai', $selected_karyawan)
                                    ->where('aspek =', 'upselling')
                                    ->get()
                                    ->getRow();
        $total_upselling = $aktual_upselling->total ?? 0;

        $aktual_followup       = $this->db->table('penilaian')
                                    ->select('SUM(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
                                    ->where('pegawai_idpegawai', $selected_karyawan)
                                    ->where('aspek =', 'followup')
                                    ->get()
                                    ->getRow();
        $total_followup = $aktual_followup->total ?? 0;
        
        $aktual_budgeting      = $this->db->table('penilaian')
                                    ->select('SUM(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
                                    ->where('pegawai_idpegawai', $selected_karyawan)
                                    ->where('aspek =', 'budgeting')
                                    ->get()
                                    ->getRow();
        $total_budgeting = $aktual_budgeting->total ?? 0;

        $aktual_roas           =$this->db->table('penilaian')
                                    ->select('SUM(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
                                    ->where('pegawai_idpegawai', $selected_karyawan)
                                    ->where('aspek =', 'roas')
                                    ->get()
                                    ->getRow();
        $total_roas = $aktual_roas->total ?? 0;

        $aktual_feed_pl        = $this->db->table('penilaian')
                                    ->select('SUM(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
                                    ->where('pegawai_idpegawai', $selected_karyawan)
                                    ->where('aspek =', 'feed pl')
                                    ->get()
                                    ->getRow();
        $total_feed = $aktual_feed_pl->total ?? 0;

        $aktual_video          = $this->db->table('penilaian')
                                    ->select('SUM(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
                                    ->where('pegawai_idpegawai', $selected_karyawan)
                                    ->where('aspek =', 'video')
                                    ->get()
                                    ->getRow();
        $total_video = $aktual_video->total ?? 0;

        $aktual_feed_mingguan  = $this->db->table('penilaian')
                                    ->select('SUM(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
                                    ->where('pegawai_idpegawai', $selected_karyawan)
                                    ->where('aspek =', 'feed mingguan')
                                    ->get()
                                    ->getRow();
        $total_feed = $aktual_feed_mingguan->total ?? 0;

        $aktual_story          = $this->db->table('penilaian')
                                    ->select('SUM(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
                                    ->where('pegawai_idpegawai', $selected_karyawan)
                                    ->where('aspek =', 'story')
                                    ->get()
                                    ->getRow();
        $total_story = $aktual_story->total ?? 0;

        $aktual_testimoni      = $this->db->table('penilaian')
                                    ->select('SUM(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
                                    ->where('pegawai_idpegawai', $selected_karyawan)
                                    ->where('aspek =', 'testimoni')
                                    ->get()
                                    ->getRow();
        $total_testimoni = $aktual_testimoni->total ?? 0;

        $aktual_bug_minor      = $this->db->table('penilaian')
                                    ->select('SUM(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
                                    ->where('pegawai_idpegawai', $selected_karyawan)
                                    ->where('aspek =', 'bug minor')
                                    ->get()
                                    ->getRow();
        $total_bug_minor = $aktual_bug_minor->total ?? 0;

        $aktual_bug_operasional= $this->db->table('penilaian')
                                    ->select('SUM(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
                                    ->where('pegawai_idpegawai', $selected_karyawan)
                                    ->where('aspek =', 'operasional')
                                    ->get()
                                    ->getRow();
        $total_bug_operasional = $aktual_bug_operasional->total ?? 0;

        $aktual_ecommerce      = $this->db->table('penilaian')
                                    ->select('SUM(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
                                    ->where('pegawai_idpegawai', $selected_karyawan)
                                    ->where('aspek =', 'ecommerce')
                                    ->get()
                                    ->getRow();
        $total_ecommerce = $aktual_ecommerce->total ?? 0;

        $aktual_fitur          = $this->db->table('penilaian')
                                    ->select('SUM(skor) AS total')
                                    ->where('MONTH(tanggal_penilaian)', date('m'))
                                    ->where('YEAR(tanggal_penilaian)', date('Y'))
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

        if ($jabatan == 41){
            if ($aktual_omset <= $batas1) {
                $nilai_omset = 0;

            } elseif ($aktual_omset == $batas2) {
                $nilai_omset = 33;

            } elseif ($aktual_omset == $batas3 ) {
                $nilai_omset = 66;

            } elseif ($aktual_omset >= $batas4 && $aktual_omset < $targetOmset) {
                $nilai_omset = 100;

            } elseif ($aktual_omset >= $targetOmset) {
                $nilai_omset = 100;
                $insentif = (3 / 100) * $aktual_omset / 4;
            } else{
                $nilai_omset = (($aktual_omset - $batas1) / ($batas4 - $batas1)) * 100;
            }
        } elseif($jabatan == 40 ){

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
        } elseif($jabatan == 43){

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

                
            } else{
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

        $nilai_tutup_kasir  = $total_tutup_kasir/30 * 20;
        $nilai_opname       = $aktual_opname/4 * 100;
        $nilai_absen        = $aktual_absen;
    
        $nilai_operasional  = $aktual_operasional;
        $nilai_divisi       = $total_divisi *20;

        $rata_kebersihan    = $ttl_kebersihan *20;
        $rata_seragam    = $ttl_seragam *20;
        $rata_kepatuhan    = $ttl_kepatuhan *20;

        $nilai_budgeting    = $total_budgeting * 100;

        $nilai_feed_pl      = $total_feed;
        $nilai_video        = $total_video;
        $nilai_feed_mingguan = $total_feed;
        $nilai_story        = $total_story;
        $nilai_testimoni    = $total_testimoni;

        $nilai_bug_minor    = $total_bug_minor/4 * 20;
        $nilai_bug_operasional = $total_bug_operasional/4 * 20;
        $nilai_ecommerce    = $total_ecommerce/4 * 20;
        $nilai_fitur        = $total_fitur/4 * 20;

        $nilai_kehadiran = $totalKehadiran/26 * 20;
        $nilai_kebersihan = $totalKebersihan/26 * 20;
        $nilai_seragam = $totalSeragam/26 * 20;
        $nilai_sop = $totalSop/26 * 20;

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
        }

        //total nilai

        foreach ($detail_kpi as $kpi) {
            $skor_total += ($kpi['nilai'] * $kpi['bobot']) / 100;
        }

        foreach ($detail_absen as $absen) {
            $skor_total2 += ($absen['nilai'] * $absen['bobot']) / 100;
        }
        
        $tunjangan_absen = $skor_total2 /100 * 250000;
        
        if($jabatan == 41){                
            $tunjangan_kinerja = $skor_total /100 * 850000;
        } elseif($jabatan == 40){
            $tunjangan_kinerja = $skor_total /100 * 1250000;
        } elseif($jabatan == 43){
            $tunjangan_kinerja = $skor_total /100 * 1000000;
        } elseif($jabatan == 35){
            if ($unit == 1) {
                $tunjangan_kinerja = $skor_total /100 * 850000;
            } else{
                $tunjangan_kinerja = $skor_total /100 * 250000;
            }
        }else{
            $tunjangan_kinerja = $skor_total /100 * 250000;
        }
        

        $gaji_pokok= 1500000;

        $gaji = $gaji_pokok + $tunjangan_kinerja + $tunjangan_absen + $akun->tunjangan_penempatan + $insentif;
        
            $gaji = $gaji_pokok
                    + $tunjangan_kinerja
                    + $tunjangan_absen
                    + $akun->tunjangan_penempatan
                    + $insentif;
        
            $totalGajiUnit += $gaji;
        }
        
        $pengeluaran = $this->db->table('kas_keluar')
            ->selectSum('kas_keluar.jumlah', 'total')
            ->join('kategori_kas', 'kategori_kas.idkategori_kas = kas_keluar.kategori_idkategori')
            ->where('MONTH(kas_keluar.tanggal)', date('m'))
            ->where('YEAR(kas_keluar.tanggal)', date('Y'))
            ->where('kas_keluar.idunit', $unit)
            ->whereIn('kas_keluar.kategori_idkategori', [1,2,3,4,5,11,18])
            ->get()
            ->getRow()
            ->total ?? 0;

        return view('template', [
            'list_unit'      => $list_unit,
            'selected_unit'  => $unit,
            'id_jabatan'     => $id_jabatan,
            'pengeluaran'     => $pengeluaran,
            'totalGajiUnit'  => $totalGajiUnit,
            
            'omset_bulan'       => $omset_bulan,
            'body'              => 'dashboard/asset_berjalan'
        ]);
    }
    
    public function tutup()
    {
        $unit = session()->get('ID_UNIT');
        
        $today = date('Y-m-d');

        $besok = date('Y-m-d', strtotime('+1 day'));

        $akhir_cash = $this->request->getPost('akhir_cash');
        $akhir_transfer = $this->request->getPost('akhir_transfer');

        // default
        $cash_laci = $akhir_cash;
        $transfer_tambahan = 0;

        // jika cash lebih dari 1 juta
        if ($akhir_cash > 1000000) {
            $cash_laci = 1000000;
            $this->db->table('kas_masuk')->insert([

                'tanggal'          => $besok,
                'jumlah'           => $cash_laci,
                'deskripsi'        => 'kas awal',
                'idunit'             => $unit,
                'created_on'       => date('Y-m-d H:i:s'),
                'updated_on'       => date('Y-m-d H:i:s'),
            ]);

            $transfer_tambahan = $akhir_cash - 1000000;
            $transfer_final= $akhir_transfer + $transfer_tambahan;
        }else {
            $this->db->table('kas_masuk')->insert([

                'tanggal'          => $besok,
                'jumlah'           => $cash_laci,
                'deskripsi'        => 'kas awal',
                'idunit'             => $unit,
                'created_on'       => date('Y-m-d H:i:s'),
                'updated_on'       => date('Y-m-d H:i:s'),
            ]);
        }

        $this->db->table('tutup_kasir')->insert([

            'tanggal'              => $today,

            'awal_cash'            => $this->request->getPost('awal_cash'),
            'awal_transfer'        => $this->request->getPost('awal_transfer'),

            'akhir_cash'           => $this->request->getPost('akhir_cash'),
            'akhir_transfer'       => $this->request->getPost('akhir_transfer'),

            'pendapatan_cash'      => $this->request->getPost('pendapatan_cash'),
            'pendapatan_transfer'  => $this->request->getPost('pendapatan_transfer'),

            'pengeluaran_cash'     => $this->request->getPost('pengeluaran_cash'),
            'pengeluaran_transfer' => $this->request->getPost('pengeluaran_transfer'),

            'cash_laci'            => $this->request->getPost('cash_laci'),

            'status'               => 'selesai',

            'akun_ID_AKUN' => session('ID_AKUN'),
            'unit'         => session('ID_UNIT'),

            'created_at'           => date('Y-m-d H:i:s'),
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);

        if ($transfer_tambahan > 0) {
            $this->db->table('kas_masuk')->insert([

                'tanggal'          => $besok,
                'jumlah'           => $transfer_final,
                'deskripsi'        => 'kas awal',
                'idbank'          => 1,
                'idunit'             => $unit,
                'created_on'       => date('Y-m-d H:i:s'),
                'updated_on'       => date('Y-m-d H:i:s'),
            ]);
        } else {
            $this->db->table('kas_masuk')->insert([

                'tanggal'          => $besok,
                'jumlah'           => $akhir_transfer,
                'deskripsi'        => 'kas awal',
                'idbank'          => 1,
                'idunit'             => $unit,
                'created_on'       => date('Y-m-d H:i:s'),
                'updated_on'       => date('Y-m-d H:i:s'),
            ]);
        }

        return redirect()->to('/tutup_kasir')
            ->with('success', 'Tutup kasir berhasil');
    }

    public function cetak_tutup_kasir($id)
    {
        $unit = session()->get('ID_UNIT');

        $today = date('Y-m-d');

        $totalservice = $this->db->table('service')
            ->selectSum('bayar_tunai', 'total')
            ->where('DATE(tanggal_selesai)', $today)
            ->where('status_service', 4)
            ->where('unit_idunit', $unit)
            ->get()
            ->getRow()->total ?? 0;
        
        $qtyservice = $this->db->table('service')
            ->selectCount('bayar_tunai', 'total')
            ->where('DATE(tanggal_selesai)', $today)
            ->where('status_service', 4)
            ->where('unit_idunit', $unit)
            ->get()
            ->getRow()->total ?? 0;
        
        // PENGELUARAN Cash
        $opcash = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 'total')
            ->where('idunit', $unit)
            ->where('DATE(tanggal)', $today)
            ->where('idbank IS NULL', null, false)
            ->like('deskripsi', 'operasional', 'after')
            ->get()
            ->getRow()->total ?? 0;
        
        $optf = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 'total')
            ->where('idunit', $unit)
            ->where('DATE(tanggal)', $today)
            ->where('idbank IS NOT NULL', null, false)
            ->like('deskripsi', 'operasional', 'after')
            ->get()
            ->getRow()->total ?? 0;

        $pscash = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 'total')
            ->where('idunit', $unit)
            ->where('DATE(tanggal)', $today)
            ->where('idbank IS NULL', null, false)
            ->like('deskripsi', 'sparepart', 'after')
            ->get()
            ->getRow()->total ?? 0;
        
        $pstf = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 'total')
            ->where('idunit', $unit)
            ->where('DATE(tanggal)', $today)
            ->where('idbank IS NOT NULL', null, false)
            ->like('deskripsi', 'sparepart', 'after')
            ->get()
            ->getRow()->total ?? 0;
        
        $hp_total = $this->db->table('detail_penjualan')
            ->selectSum('detail_penjualan.sub_total', 'total')
            ->join('barang', 'barang.idbarang = detail_penjualan.barang_idbarang', 'left')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan', 'left')
            ->where('barang.idkategori', 1)
            ->where('penjualan.unit_idunit', $unit)
            ->like('penjualan.tanggal', $today, 'after')
            ->get()
            ->getRow()
            ->total ?? 0;

        $hp_qty = $this->db->table('detail_penjualan')
            ->selectCount('detail_penjualan.sub_total', 'total')
            ->join('barang', 'barang.idbarang = detail_penjualan.barang_idbarang', 'left')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan', 'left')
            ->where('barang.idkategori', 1)
            ->where('penjualan.unit_idunit', $unit)
            ->like('penjualan.tanggal', $today, 'after')
            ->get()
            ->getRow()
            ->total ?? 0;

        $acc_total = $this->db->table('detail_penjualan')
            ->selectSum('detail_penjualan.sub_total', 'total')
            ->join('barang', 'barang.idbarang = detail_penjualan.barang_idbarang', 'left')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan', 'left')
            ->where('barang.idkategori', 2)
            ->where('penjualan.unit_idunit', $unit)
            ->like('penjualan.tanggal', $today, 'after')
            ->get()
            ->getRow()
            ->total ?? 0;
            
        $acc_qty = $this->db->table('detail_penjualan')
            ->selectCount('detail_penjualan.sub_total', 'total')
            ->join('barang', 'barang.idbarang = detail_penjualan.barang_idbarang', 'left')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan', 'left')
            ->where('barang.idkategori', 2)
            ->where('penjualan.unit_idunit', $unit)
            ->like('penjualan.tanggal', $today, 'after')
            ->get()
            ->getRow()
            ->total ?? 0;

        $tutupKasir = $this->TutupKasir->getById($id, $unit);

        $data = [
            'ps_tf' => $pstf,
            'ps_cash' => $pscash,
            'op_tf' => $optf,
            'op_cash' => $opcash,
            'qty_service' => $qtyservice,
            'service_total' => $totalservice,
            'qty_hp' => $hp_qty,
            'hp_total' => $hp_total,
            'qty_acc' => $acc_qty,
            'acc_total' => $acc_total,
            'tutup'    => $tutupKasir,
            // 'dataunit' => $this->Unit->getById(session('ID_UNIT'))
        ];

        $html = view('cetak/cetak_tutupkasir', $data);

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left'   => 10,
            'margin_right'  => 10,
            'margin_top'    => 10,
            'margin_bottom' => 10,
        ]);

        error_reporting(0);

        if (ob_get_length()) {
            ob_end_clean();
        }

        $mpdf->curlAllowUnsafeSslRequests = true;

        $this->response->setHeader('Content-Type', 'application/pdf');
        $this->response->setHeader('Content-Transfer-Encoding', 'binary');
        $this->response->setHeader('Accept-Ranges', 'bytes');

        $mpdf->WriteHTML($html);

        $filename = 'laporan-tutup-kasir-' . date('Y-m-d') . '.pdf';

        $mpdf->Output($filename, 'I');
        exit;
    }
}