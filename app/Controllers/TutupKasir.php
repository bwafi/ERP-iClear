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

        // ambil unit login
        $unit = (int) session()->get('ID_UNIT');

        // ==============================================================
        // SATU SUMBER ANGKA
        // ==============================================================
        //
        // Seluruh angka halaman ini dihitung `TutupKasirClosing::hitung()` —
        // service yang SAMA dengan yang dipanggil `simpan()` saat menyimpan.
        // Jadi yang tampil di layar pasti sama dengan yang masuk ke
        // `tutup_kasir`. Tidak ada lagi query perhitungan kedua di controller
        // yang bisa menyimpang dari angka closing.
        //
        // `saldo_awal_kas` / `saldo_awal_tf` berasal dari
        // `TutupKasirSaldoAwal` (carry-forward closing -> baseline ->
        // alokasi rekening bersama -> belum ditetapkan).
        //
        // @see \App\Services\Finance\TutupKasirClosing::hitung()
        // @see \App\Services\Finance\TutupKasirSaldoAwal
        $h = (new \App\Services\Finance\TutupKasirClosing($this->db))
            ->hitung($unit, $today);

        // tutup kasir terakhir per unit (dipakai tombol Print)
        $tutupkasir = $this->db->table('tutup_kasir')
            ->where('unit', $unit)
            ->orderBy('idtutupkasir', 'DESC')
            ->get()
            ->getRow();

        // Closing hari ini sudah ada atau belum. Tidak dipakai untuk
        // menghentikan render: view punya `$sudah_ditutup` + tombol disabled.
        $sudahDitutup = $this->db->table('tutup_kasir')
            ->where('DATE(tanggal)', $today)
            ->where('unit', $unit)
            ->countAllResults() > 0;

        return view('template', array_merge($h['angka'], [
            'tanggal'             => $today,
            'unit'                => $unit,
            'tutupkasir'          => $tutupkasir,
            'saldo_awal_kas'      => $h['saldo_awal_kas'],
            'saldo_awal_tf'       => $h['saldo_awal_tf'],
            'tutup_bisa_disimpan' => (bool) $h['siap'],
            'tutup_alasan'        => (string) $h['alasan'],
            'sudah_ditutup'       => $sudahDitutup,
            'setor'               => (int) $h['setor'],
            'tarik'               => (int) $h['tarik'],
            'transfer_internal'   => $h['transfer_internal'],
            'transfer_masuk'      => (int) $h['transfer_masuk'],
            'transfer_keluar'     => (int) $h['transfer_keluar'],
            'kas_masuk'           => (int) $h['kas_masuk'],
            'kas_keluar'          => (int) $h['kas_keluar'],

            // Nama-nama yang dipakai view `jurnal/tutup_kasir` (berbeda dari
            // nama kolom `tutup_kasir`). Nilainya tetap angka yang sama dari
            // `hitung()`, hanya diganti nama supaya view tidak perlu diubah.
            'cash'                => (int) $h['kas_masuk'],
            'transfer'            => (int) $h['transfer_masuk'],
            'pengeluarancash'     => (int) $h['kas_keluar'],
            'pengeluarantf'       => (int) $h['transfer_keluar'],
            'total_pendapatan'    => (int) $h['kas_masuk'] + (int) $h['transfer_masuk'],

            'wibOffsetMenit'      => 420,
            'body'                => 'jurnal/tutup_kasir',
        ]));
    }

    public function kasirbulanan()
    {
        $tanggal = $this->request->getGet('tanggal')
            ?? date('Y-m-d');

        $unit = $this->request->getGet('unit');

        $list_unit = $this->db->table('unit')->get()->getResultArray();

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
                'list_unit'  => $list_unit,
                'selected_unit' => $unit,
                'body' => 'jurnal/kasir_bulanan'

            ]);
        }

        return view('template', [

            'tutupkasir' => $tutupkasir,

            'tanggal' => $tanggal,

            'list_unit' => $list_unit,

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
        $id_akun    = session()->get('ID_AKUN');

        // Mapping khusus untuk direktur/investor (jabatan 2) yang punya multi unit
        $allowedUnitsByAkun = [
            46 => [3, 5], // Wahid Alfarizki: Banyuwangi, Genteng
            70 => [4],    // Guruh Dwi Prasetyo: Pandaan
        ];

        // Ambil list unit (kecualikan Head Office)
        if (in_array($id_jabatan, [1, 40])) {
            $list_unit = $this->db->table('unit')
                ->where('idunit !=', 50)
                ->notLike('NAMA_UNIT', 'Head Office')
                ->get()
                ->getResultArray();
        } elseif ($id_jabatan == 2 && isset($allowedUnitsByAkun[$id_akun])) {
            $allowed = $allowedUnitsByAkun[$id_akun];
            $list_unit = $this->db->table('unit')
                ->whereIn('idunit', $allowed)
                ->where('idunit !=', 50)
                ->notLike('NAMA_UNIT', 'Head Office')
                ->get()
                ->getResultArray();
        } else {
            $list_unit = $this->db->table('unit')
                ->where('idunit !=', 50)
                ->notLike('NAMA_UNIT', 'Head Office')
                ->get()
                ->getResultArray();
        }

        // Penentuan unit
        if (in_array($id_jabatan, [1, 40])) {
            $unit = $this->request->getGet('unit');
            if (!$unit) {
                $unit = session()->get('ID_UNIT');
            }
        } elseif ($id_jabatan == 2 && isset($allowedUnitsByAkun[$id_akun])) {
            $unit = $this->request->getGet('unit');
            if (!$unit || !in_array((int)$unit, $allowedUnitsByAkun[$id_akun], true)) {
                $unit = $allowedUnitsByAkun[$id_akun][0];
            }
        } else {
            $unit = session()->get('ID_UNIT');
        }

        $availableUnitIds = array_map(function($u) { return (int)$u['idunit']; }, $list_unit);
        if (!in_array((int)$unit, $availableUnitIds, true) && !empty($availableUnitIds)) {
            $unit = $availableUnitIds[0];
        }

        // FILTER BULAN/TAHUN
        $bulan = $this->request->getGet('bulan');
        $tahun = $this->request->getGet('tahun');

        if (!$bulan || !$tahun) {
            $bulan = date('m');
            $tahun = date('Y');
        }

        // Hitung bulan/tahun sebelumnya
        $bulanSebelum = (int)$bulan - 1;
        $tahunSebelum = (int)$tahun;
        if ($bulanSebelum < 1) {
            $bulanSebelum = 12;
            $tahunSebelum = (int)$tahun - 1;
        }
        $periodeLabel = date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun));
        $isBulanBerjalan = ((int)$bulan == (int)date('m') && (int)$tahun == (int)date('Y'));


        // ==========================
        // TOTAL OMSET BULAN INI
        // ==========================
        $omset_bulan = $this->db->table('detail_penjualan')
            ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            ->where('MONTH(penjualan.tanggal)', $bulan)
            ->where('YEAR(penjualan.tanggal)', $tahun)
            ->where('penjualan.unit_idunit', $unit)
            ->get()
            ->getRow()
            ->total ?? 0;

        // HITUNG PERTUMBUHAN OMSET VS BULAN SEBELUMNYA
        $omset_bulan_sebelum = $this->db->table('detail_penjualan')
            ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            ->where('MONTH(penjualan.tanggal)', $bulanSebelum)
            ->where('YEAR(penjualan.tanggal)', $tahunSebelum)
            ->where('penjualan.unit_idunit', $unit)
            ->get()
            ->getRow()
            ->total ?? 0;

        if ($omset_bulan_sebelum > 0) {
            $pertumbuhan_omset = (($omset_bulan - $omset_bulan_sebelum) / $omset_bulan_sebelum) * 100;
        } else {
            $pertumbuhan_omset = 0;
        }
        $omset_bulan_lalu = $omset_bulan_sebelum;
        $periodeLaluLabel = date('F Y', mktime(0, 0, 0, $bulanSebelum, 1, $tahunSebelum));
        $selisih_omset = $omset_bulan - $omset_bulan_sebelum;


        // ==========================
        // TOTAL PELANGGAN
        // ==========================
        $countService = $this->db->table('service')
            ->select('COUNT(idservice) AS total')
            ->where('MONTH(tanggal_selesai)', $bulan)
            ->where('YEAR(tanggal_selesai)', $tahun)
            ->where('unit_idunit', $unit)
            ->get()
            ->getRow()
            ->total ?? 0;

        $countSales = $this->db->table('penjualan')
            ->select('COUNT(DISTINCT idpenjualan) AS total')
            ->where('MONTH(tanggal)', $bulan)
            ->where('YEAR(tanggal)', $tahun)
            ->where('unit_idunit', $unit)
            ->like('kode_invoice', 'SLL', 'after')
            ->get()
            ->getRow()
            ->total ?? 0;

        $pelanggan_bulan = $countService + $countSales;

        // ==========================
        // TOP KECAMATAN / DOMISILI PELANGGAN
        // ==========================
        $kecamatanQuery = $this->db->query("
            SELECT 
                UPPER(TRIM(p.kecamatan)) AS raw_kecamatan,
                COUNT(DISTINCT sub.unik_id) AS total_transaksi,
                COUNT(DISTINCT p.id_pelanggan) AS total_pelanggan
            FROM (
                SELECT s.pelanggan_id_pelanggan AS id_pelanggan, CONCAT('SVC-', s.idservice) AS unik_id
                FROM service s
                WHERE s.unit_idunit = ?
                  AND MONTH(s.tanggal_selesai) = ?
                  AND YEAR(s.tanggal_selesai) = ?
                  AND s.pelanggan_id_pelanggan IS NOT NULL
                  AND s.pelanggan_id_pelanggan != 0

                UNION ALL

                SELECT pj.id_pelanggan, CONCAT('SLS-', pj.idpenjualan) AS unik_id
                FROM penjualan pj
                WHERE pj.unit_idunit = ?
                  AND MONTH(pj.tanggal) = ?
                  AND YEAR(pj.tanggal) = ?
                  AND pj.kode_invoice LIKE 'SLL%'
                  AND pj.id_pelanggan IS NOT NULL
                  AND pj.id_pelanggan > 0
            ) AS sub
            JOIN pelanggan p ON p.id_pelanggan = sub.id_pelanggan
            WHERE p.deleted = '0'
              AND p.kecamatan IS NOT NULL 
              AND TRIM(p.kecamatan) != ''
            GROUP BY UPPER(TRIM(p.kecamatan))
            ORDER BY total_pelanggan DESC, total_transaksi DESC
        ", [$unit, $bulan, $tahun, $unit, $bulan, $tahun]);

        $listKecamatanRaw = $kecamatanQuery->getResultArray();

        $totalKecamatanTransaksi = 0;
        $totalKecamatanPelanggan = 0;
        foreach ($listKecamatanRaw as $row) {
            $totalKecamatanTransaksi += (int)$row['total_transaksi'];
            $totalKecamatanPelanggan += (int)$row['total_pelanggan'];
        }

        $list_kecamatan = [];
        foreach ($listKecamatanRaw as $row) {
            $pct = $totalKecamatanPelanggan > 0 ? round(((int)$row['total_pelanggan'] / $totalKecamatanPelanggan) * 100, 1) : 0;
            $list_kecamatan[] = (object)[
                'nama_kecamatan'  => ucwords(strtolower($row['raw_kecamatan'])),
                'total_transaksi' => (int)$row['total_transaksi'],
                'total_pelanggan' => (int)$row['total_pelanggan'],
                'persentase'      => $pct,
            ];
        }

        $top_kecamatan = !empty($list_kecamatan) ? $list_kecamatan[0] : (object)[
            'nama_kecamatan'  => 'Belum ada data',
            'total_transaksi' => 0,
            'total_pelanggan' => 0,
            'persentase'      => 0,
        ];

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
            ->where('MONTH(penjualan.tanggal)', $bulan)
            ->where('YEAR(penjualan.tanggal)', $tahun)
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
            ->where('MONTH(tanggal_selesai)', $bulan)
            ->where('YEAR(tanggal_selesai)', $tahun)
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
            ->where('MONTH(p.tanggal)', $bulan)
            ->where('YEAR(p.tanggal)', $tahun)
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
            ->where('MONTH(penjualan.tanggal)', $bulan)
            ->where('YEAR(penjualan.tanggal)', $tahun)
            ->where('penjualan.unit_idunit', $unit)
            ->get()
            ->getRow()
            ->total ?? 0;

        $value = $this->db->table('detail_penjualan')
            ->select('SUM(detail_penjualan.sub_total) AS total')
            ->join(
                'penjualan',
                'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan'
            )
            ->where('MONTH(penjualan.tanggal)', $bulan)
            ->where('YEAR(penjualan.tanggal)', $tahun)
            ->where('penjualan.unit_idunit', $unit)
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
            ->where('MONTH(penjualan.tanggal)', $bulan)
            ->where('YEAR(penjualan.tanggal)', $tahun)
            ->where('penjualan.unit_idunit', $unit)
            ->groupBy('DATE(penjualan.tanggal)')
            ->get()
            ->getResult();

        $dataHarian = [];

        foreach ($results as $row) {
            $dataHarian[$row->tgl] = $row->total;
        }

        // Cari hari dengan omset tertinggi
        $hariTerbaik = null;
        $omsetTerbaik = 0;
        if (!empty($dataHarian)) {
            arsort($dataHarian);
            $hariTerbaikKey = array_key_first($dataHarian);
            $hariTerbaik = $hariTerbaikKey;
            $omsetTerbaik = $dataHarian[$hariTerbaikKey] ?? 0;
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
            'bulan'          => $bulan,
            'tahun'          => $tahun,
            'periodeLabel'   => $periodeLabel,
            'isBulanBerjalan' => $isBulanBerjalan,
            'bulanSebelum'   => $bulanSebelum,
            'tahunSebelum'   => $tahunSebelum,
            'hpp'           => $hpp,
            'value'         => $value,
            'listHari'          => $listHari,
            'bestseller'        => $bestseller,
            'bestsellerproduct' => $bestsellerproduct,
            'omset_bulan'       => $omset_bulan,
            'pertumbuhan_omset' => $pertumbuhan_omset,
            'omset_bulan_lalu'  => $omset_bulan_lalu,
            'periodeLaluLabel'  => $periodeLaluLabel,
            'selisih_omset'     => $selisih_omset,
            'pelanggan_bulan'   => $pelanggan_bulan,
            'sparepart_keluar'  => $sparepart_keluar,
            'omset_hari_ini'    => $omset_hari_ini,
            'hariTerbaik'       => $hariTerbaik,
            'omsetTerbaik'      => $omsetTerbaik,
            'top_kecamatan'     => $top_kecamatan,
            'list_kecamatan'    => $list_kecamatan,
            'total_kecamatan_pelanggan' => $totalKecamatanPelanggan,
            'total_kecamatan_transaksi' => $totalKecamatanTransaksi,
            'body'              => 'jurnal/omset_bulanan'
        ]);
    }

    public function assetberjalan()
    {
        
        $db = \Config\Database::connect();


        $id_jabatan = session()->get('ID_JABATAN');
        $id_akun    = session()->get('ID_AKUN');

        // Mapping khusus untuk direktur/investor (jabatan 2) yang punya multi unit
        $allowedUnitsByAkun = [
            46 => [3, 5], // Wahid Alfarizki: Banyuwangi, Genteng
            70 => [4],    // Guruh Dwi Prasetyo: Pandaan
        ];

        // Ambil list unit (kecualikan Head Office)
        if (in_array($id_jabatan, [1, 40])) {
            $list_unit = $this->db->table('unit')
                ->where('idunit !=', 50)
                ->notLike('NAMA_UNIT', 'Head Office')
                ->get()
                ->getResultArray();
        } elseif ($id_jabatan == 2 && isset($allowedUnitsByAkun[$id_akun])) {
            $allowed = $allowedUnitsByAkun[$id_akun];
            $list_unit = $this->db->table('unit')
                ->whereIn('idunit', $allowed)
                ->where('idunit !=', 50)
                ->notLike('NAMA_UNIT', 'Head Office')
                ->get()
                ->getResultArray();
        } else {
            $list_unit = $this->db->table('unit')
                ->where('idunit !=', 50)
                ->notLike('NAMA_UNIT', 'Head Office')
                ->get()
                ->getResultArray();
        }

        // Penentuan unit
        if (in_array($id_jabatan, [1, 40])) {
            $unit = $this->request->getGet('unit');
            if (!$unit) {
                $unit = session()->get('ID_UNIT');
            }
        } elseif ($id_jabatan == 2 && isset($allowedUnitsByAkun[$id_akun])) {
            $unit = $this->request->getGet('unit');
            if (!$unit || !in_array((int)$unit, $allowedUnitsByAkun[$id_akun], true)) {
                $unit = $allowedUnitsByAkun[$id_akun][0];
            }
        } else {
            $unit = session()->get('ID_UNIT');
        }

        $availableUnitIds = array_map(function($u) { return (int)$u['idunit']; }, $list_unit);
        if (!in_array((int)$unit, $availableUnitIds, true) && !empty($availableUnitIds)) {
            $unit = $availableUnitIds[0];
        }

        // FILTER BULAN/TAHUN
        $bulan = $this->request->getGet('bulan');
        $tahun = $this->request->getGet('tahun');

        if (!$bulan || !$tahun) {
            $bulan = date('m');
            $tahun = date('Y');
        }

        $bulanSebelum = (int)$bulan - 1;
        $tahunSebelum = (int)$tahun;
        if ($bulanSebelum < 1) {
            $bulanSebelum = 12;
            $tahunSebelum = (int)$tahun - 1;
        }
        $periodeLabel = date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun));
        $isBulanBerjalan = ((int)$bulan == (int)date('m') && (int)$tahun == (int)date('Y'));


        // FILTER BULAN/TAHUN
        $bulan = $this->request->getGet('bulan');
        $tahun = $this->request->getGet('tahun');

        if (!$bulan || !$tahun) {
            $bulan = date('m');
            $tahun = date('Y');
        }

        // Hitung bulan/tahun sebelumnya
        $bulanSebelum = (int)$bulan - 1;
        $tahunSebelum = (int)$tahun;
        if ($bulanSebelum < 1) {
            $bulanSebelum = 12;
            $tahunSebelum = (int)$tahun - 1;
        }
        $periodeLabel = date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun));
        $isBulanBerjalan = ((int)$bulan == (int)date('m') && (int)$tahun == (int)date('Y'));


        // ==========================
        // TOTAL OMSET BULAN INI
        // ==========================
        $omset_bulan = $this->db->table('detail_penjualan')
            ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            ->where('MONTH(penjualan.tanggal)', $bulan)
            ->where('YEAR(penjualan.tanggal)', $tahun)
            ->where('penjualan.unit_idunit', $unit)
            ->get()
            ->getRow()
            ->total ?? 0;

        // HITUNG PERTUMBUHAN OMSET VS BULAN SEBELUMNYA
        $omset_bulan_sebelum = $this->db->table('detail_penjualan')
            ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            ->where('MONTH(penjualan.tanggal)', $bulanSebelum)
            ->where('YEAR(penjualan.tanggal)', $tahunSebelum)
            ->where('penjualan.unit_idunit', $unit)
            ->get()
            ->getRow()
            ->total ?? 0;

        if ($omset_bulan_sebelum > 0) {
            $pertumbuhan_omset = (($omset_bulan - $omset_bulan_sebelum) / $omset_bulan_sebelum) * 100;
        } else {
            $pertumbuhan_omset = 0;
        }
        $omset_bulan_lalu = $omset_bulan_sebelum;
        $periodeLaluLabel = date('F Y', mktime(0, 0, 0, $bulanSebelum, 1, $tahunSebelum));
        $selisih_omset = $omset_bulan - $omset_bulan_sebelum;


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

        // Samakan dengan StokOpnameCalculator: yang dihitung hanya periode FINAL
        // yang seluruh barang berstoknya terisi. Versi lama menghitung DISTINCT
        // tanggal pada stok_opname_draft, jadi draft kosong pun ikut dihitung
        // dan angkanya beda dari KPI resmi.
        $aktual_opname         = $this->db->table('stok_opname_periode')
                                    ->select('COUNT(*) AS total')
                                    ->where('unit_idunit', $unit)
                                    ->where('status', 'FINAL')
                                    ->where('terisi_barang = total_barang', null, false)
                                    ->where('total_barang >', 0)
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
            'bulan'          => $bulan,
            'tahun'          => $tahun,
            'periodeLabel'   => $periodeLabel,
            'isBulanBerjalan' => $isBulanBerjalan,
            'bulanSebelum'   => $bulanSebelum,
            'tahunSebelum'   => $tahunSebelum,
            'pengeluaran'     => $pengeluaran,
            'totalGajiUnit'  => $totalGajiUnit,
            'omset_bulan'       => $omset_bulan,
            'pertumbuhan_omset' => $pertumbuhan_omset,
            'omset_bulan_lalu'  => $omset_bulan_lalu,
            'periodeLaluLabel'  => $periodeLaluLabel,
            'selisih_omset'     => $selisih_omset,
            'body'              => 'dashboard/asset_berjalan'
        ]);
    }
    
    public function tutup()
    {
        $unit  = (int) session()->get('ID_UNIT');
        $today = date('Y-m-d');

        // ==============================================================
        // SERVER YANG MENGHITUNG, BUKAN FORM
        // ==============================================================
        //
        // Tidak ada lagi `getPost('awal_cash')` / `getPost('akhir_cash')`.
        // Satu-satunya angka dari request adalah `cash_laci` (hasil hitung
        // uang fisik oleh kasir), dan itu pun divalidasi ketat oleh
        // `TutupKasirClosing::simpan()`.
        //
        // `simpan()` juga yang menangani: kunci anti-duplikat (GET_LOCK),
        // transaksi, hitung ulang seluruh angka dari DB, idempoten, dan
        // penolakan kalau sumber saldo awal belum sah.
        //
        // Tidak ada lagi insert manual ke `tutup_kasir`, dan tidak ada lagi
        // penulisan baris `kas_masuk` berdeskripsi 'kas awal' — saldo awal
        // hari berikutnya sudah dibawa oleh carry-forward
        // `tutup_kasir.akhir_cash`.
        //
        // @see \App\Services\Finance\TutupKasirClosing::simpan()
        $hasil = (new \App\Services\Finance\TutupKasirClosing($this->db))
            ->simpan(
                $unit,
                $today,
                $this->request->getPost('cash_laci'),
                (int) session('ID_AKUN')
            );

        if (! $hasil['ok']) {
            return redirect()->to('/tutup_kasir')
                ->with('gagal', (string) $hasil['alasan']);
        }

        // Idempoten: posting ganda / tombol diklik dua kali. Closing hari ini
        // sudah ada, jadi tidak disimpan lagi — tetap dianggap berhasil.
        if ($hasil['kode'] === 'sudah_ada') {
            return redirect()->to('/tutup_kasir')
                ->with('sukses', (string) $hasil['alasan']);
        }

        return redirect()->to('/tutup_kasir')
            ->with('sukses', 'Tutup kasir berhasil');
    }

    public function arusKasHarian()
    {
        $unit = $this->request->getGet('unit');
        $tanggal = $this->request->getGet('tanggal');

        if (!$unit || !$tanggal) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Parameter tidak lengkap',
            ]);
        }

        $service = new \App\Services\Finance\DailyCashFlowService();

        try {
            $data = $service->getForDate((int)$unit, $tanggal);
        } catch (\Throwable $e) {
            log_message('error', 'DailyCashFlowService::getForDate gagal: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Gagal memuat data arus kas.',
            ]);
        }

        // Kontrak front-end (/omset_bulanan): payload harus `success: true`
        // dengan data di `payload.data`. Versi yang mengembalikan baris mentah
        // tanpa wrapper membuat pitanya selalu jatuh ke "Gagal memuat data
        // arus kas".
        return $this->response->setJSON([
            'success' => true,
            'data'    => $data,
        ]);
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