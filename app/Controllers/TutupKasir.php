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

    /** Kategori biaya terkontrol (maks 6% omset). Sumber: Config\Finance. */
    private function kategoriTerkontrol(): array
    {
        return config(\Config\Finance::class)->kategoriBiayaTerkontrol;
    }

    /** Kategori biaya lain yang juga mengurangi laba bersih. Sumber: Config\Finance. */
    private function kategoriBiayaLain(): array
    {
        return config(\Config\Finance::class)->kategoriBiayaLain;
    }

    /**
     * Batasi query kas_keluar ke biaya lain yang mengurangi laba: Lain-lain,
     * Iklan Ads, dan SDM tanpa baris lembur (lembur sudah di Total Gaji).
     *
     * @param \CodeIgniter\Database\BaseBuilder $builder
     */
    private function saringBiayaLain($builder): void
    {
        $builder->whereIn('kas_keluar.kategori_idkategori', $this->kategoriBiayaLain())
            ->groupStart()
                ->where('kas_keluar.kategori_idkategori !=', 10)
                ->orGroupStart()
                    ->where('kas_keluar.kategori_idkategori', 10)
                    ->notLike('kas_keluar.deskripsi', 'lembur')
                ->groupEnd()
            ->groupEnd();
    }

    /**
     * Resolusi daftar unit + unit aktif untuk laporan lintas cabang
     * (/omset_bulanan & /asset_berjalan).
     *
     * Aturan:
     *   - Finance (0), Admin root (1), Manager (34), SPV (40): boleh memilih
     *     seluruh unit cabang (Head Office dikecualikan) lewat ?unit=.
     *   - Direktur (2): boleh memilih unit seperti Admin root, TETAPI dibatasi
     *     daftar unit per akun (DirekturUnitScopeService::UNIT_MAP). Direktur
     *     yang belum dipetakan diperlakukan "lintas unit" (semua cabang).
     *   - Jabatan lain (mis. Kasir/Kepala Toko): terkunci ke ID_UNIT sesi.
     *
     * Daftar unit pada dasarnya tidak menampilkan Head Office (idunit 50).
     *
     * @param int[] $roleTambahan Role yang boleh memilih unit lintas cabang
     *                            khusus untuk halaman pemanggil.
     *
     * @return array{list_unit: array, unit: int, id_jabatan: int, boleh_pilih_unit: bool}
     */
    private function resolveUnitScope(array $roleTambahan = [], bool $batasiSpv = false, bool $kunciDirektur = false): array
    {
        $id_jabatan = (int) session()->get('ID_JABATAN');
        $id_akun    = (int) session()->get('ID_AKUN');

        // Direktur/investor (jabatan 2) yang hanya memegang sebagian unit.
        // Pemetaan dipusatkan di DirekturUnitScopeService agar konsisten dengan
        // halaman penilaian kinerja.
        $direkturUnits = \App\Services\DirekturUnitScopeService::unitsFor($id_akun);

        $unitQuery = function () {
            return $this->db->table('unit')
                ->where('idunit !=', 50)
                ->notLike('NAMA_UNIT', 'Head Office');
        };

        $bolehPilihUnit = in_array($id_jabatan, array_merge([0, 1, 2, 34, 40], $roleTambahan), true);

        if ($batasiSpv && $id_jabatan === 40) {
            // SPV hanya membuka unit wilayahnya (spv_units); unit lain jatuh
            // ke wilayah pertama. Fallback service: unit sendiri.
            $allowedIds = (new \App\Services\Finance\FinanceScopeService())->allowedUnitIds();
            $list_unit  = $allowedIds === []
                ? []
                : $unitQuery()->whereIn('idunit', $allowedIds)->orderBy('idunit', 'ASC')->get()->getResultArray();
            $availableIds = array_map(static fn ($u) => (int) $u['idunit'], $list_unit);
            $requested    = (int) ($this->request->getGet('unit') ?: 0);
            $unit         = in_array($requested, $availableIds, true)
                ? $requested
                : (int) ($availableIds[0] ?? session()->get('ID_UNIT'));
        } elseif ($bolehPilihUnit && $id_jabatan === 2 && ($direkturUnits !== null || $kunciDirektur)) {
            // Direktur terbatas: daftar unit + validasi ?unit= terhadap daftarnya.
            // Halaman yang meminta $kunciDirektur tidak pernah terbuka ke semua
            // unit; direktur tanpa peta jatuh ke unit akunnya sendiri.
            $allowedIds = $kunciDirektur
                ? \App\Services\DirekturUnitScopeService::assetBerjalanUnits($direkturUnits, (int) session()->get('ID_UNIT'))
                : $direkturUnits;
            $list_unit  = $unitQuery()->whereIn('idunit', $allowedIds)->orderBy('idunit', 'ASC')->get()->getResultArray();

            $unit = (int) ($this->request->getGet('unit') ?: 0);
            if (! in_array($unit, $allowedIds, true)) {
                $unit = (int) ($allowedIds[0] ?? session()->get('ID_UNIT'));
            }
        } elseif ($bolehPilihUnit) {
            // Lintas unit: boleh memilih unit mana pun (Head Office dikecualikan).
            $list_unit = $unitQuery()->orderBy('idunit', 'ASC')->get()->getResultArray();

            $unit = (int) ($this->request->getGet('unit') ?: session()->get('ID_UNIT'));
        } else {
            // Terkunci ke unit sesi (pemilih unit tidak ditampilkan view).
            $list_unit = $unitQuery()->orderBy('idunit', 'ASC')->get()->getResultArray();
            $unit      = (int) session()->get('ID_UNIT');
        }

        // Jaga-jaga: unit di luar daftar yang diizinkan -> unit pertama.
        $availableUnitIds = array_map(static fn ($u) => (int) $u['idunit'], $list_unit);
        if ($availableUnitIds !== [] && ! in_array($unit, $availableUnitIds, true)) {
            $unit = $availableUnitIds[0];
        }

        return [
            'list_unit'        => $list_unit,
            'unit'             => $unit,
            'id_jabatan'       => $id_jabatan,
            'boleh_pilih_unit' => $bolehPilihUnit,
        ];
    }

    /**
     * HPP Klaim service per tanggal_hpp_klaim, untuk mengurangi omset.
     *
     * @return array<string, int>
     */
    private function hppKlaimHarian(string $start, string $end, int $unit): array
    {
        $harian = [];
        foreach ((new \App\Models\ModelHppKlaimLoss())->getLoss($start, $end, $unit) as $row) {
            $tanggal = (string) $row->tanggal_hpp_klaim;
            $harian[$tanggal] = ($harian[$tanggal] ?? 0) + (int) $row->total;
        }

        return $harian;
    }

    /**
     * Refund garansi per tanggal refund, untuk mengurangi omset periode itu.
     *
     * @return array<string, int>
     */
    private function refundHarian(string $start, string $end, int $unit): array
    {
        return (new \App\Models\ModelRefundGaransiLoss())->getByDateRange($start, $end, $unit);
    }

    public function index()
    {
        $today = date('Y-m-d');
        $isAdminRoot = $this->isAdminRoot();
        $unitList = [];

        if ($isAdminRoot) {
            $unitList = $this->db->table('unit')
                ->select('idunit, NAMA_UNIT')
                ->orderBy('NAMA_UNIT', 'ASC')
                ->get()
                ->getResultArray();

            $unitFallback = (int) session('ID_UNIT');
            if (! $this->isKnownClosingUnit($unitFallback)) {
                $unitFallback = (int) ($unitList[0]['idunit'] ?? 0);
            }

            $requestedUnit = $this->cleanClosingUnit($this->request->getGet('unit'));
            $unit = $this->isKnownClosingUnit($requestedUnit) ? $requestedUnit : $unitFallback;

            $requestedDate = $this->cleanClosingDate($this->request->getGet('tanggal'));
            $tanggal = $requestedDate !== null && $requestedDate <= $today
                ? $requestedDate
                : $today;
        } else {
            // Pilihan query string sengaja diabaikan untuk seluruh role lain.
            $unit = (int) session()->get('ID_UNIT');
            $tanggal = $today;
        }

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
            ->hitung($unit, $tanggal);

        // Rincian DP yang diterima pada tanggal pilihan. Nilainya sudah termasuk di
        // pendapatan cash/transfer; bagian ini hanya menampilkan rinciannya.
        $dpService = (new \App\Services\Finance\TutupKasirSourceDefinition($this->db))
            ->dpMasukRincian($unit, $tanggal);

        // Utamakan closing tanggal+unit pilihan. Jika belum ada, tetap sediakan
        // akses ke struk closing terakhir unit ini seperti perilaku sebelumnya.
        $tutupkasir = $this->db->table('tutup_kasir')
            ->where('unit', $unit)
            ->where('DATE(tanggal)', $tanggal)
            ->orderBy('idtutupkasir', 'DESC')
            ->get()
            ->getRow();

        if ($tutupkasir === null) {
            $tutupkasir = $this->db->table('tutup_kasir')
                ->where('unit', $unit)
                ->orderBy('tanggal', 'DESC')
                ->orderBy('idtutupkasir', 'DESC')
                ->get()
                ->getRow();
        }

        // Closing untuk pasangan tanggal+unit pilihan sudah ada atau belum. Tidak dipakai untuk
        // menghentikan render: view punya `$sudah_ditutup` + tombol disabled.
        $sudahDitutup = $this->db->table('tutup_kasir')
            ->where('DATE(tanggal)', $tanggal)
            ->where('unit', $unit)
            ->countAllResults() > 0;

        $unitRow = $this->db->table('unit')
            ->select('NAMA_UNIT')
            ->where('idunit', $unit)
            ->get()
            ->getRowArray();

        return view('template', array_merge($h['angka'], [
            'tanggal'             => $tanggal,
            'hari_ini'            => $today,
            'unit'                => $unit,
            'nama_unit'           => (string) ($unitRow['NAMA_UNIT'] ?? ('Unit ' . $unit)),
            'unit_list'           => $unitList,
            'is_admin_root'       => $isAdminRoot,
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
            'dp_service'          => $dpService,
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

        // Setor/Tarik (transaksi bank: KAS <-> BANK, mis. "TUKAR CASH") TIDAK
        // disimpan di kolom `tutup_kasir` — dihitung ulang dari
        // `transaksi_kas_bank`, sama persis seperti halaman harian. Tanpa ini,
        // rekap bulanan tidak menampilkan perpindahan kas ke bank, padahal
        // `akhir_cash` sudah ikut berkurang karenanya.
        $transferKas = (new \App\Services\Finance\TutupKasirTransferInternal($this->db))
            ->hariIni((int) $tutupkasir->unit, $tanggal);

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

            // transaksi bank (setor / tarik KAS <-> BANK)
            'setor' => (int) ($transferKas['setor'] ?? 0),
            'tarik' => (int) ($transferKas['tarik'] ?? 0),
            'transfer_internal' => $transferKas,

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
        // Kepala Divisi (43) boleh memilih unit lintas cabang khusus di sini.
        $scope = $this->resolveUnitScope([43]);
        $list_unit      = $scope['list_unit'];
        $unit           = $scope['unit'];
        $id_jabatan     = $scope['id_jabatan'];
        $bolehPilihUnit = $scope['boleh_pilih_unit'];

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

        // Omset halaman ini = penjualan - HPP Klaim service pada tanggal klaim
        // - refund garansi pada tanggal refund.
        $awalBulan = sprintf('%04d-%02d-01', (int) $tahun, (int) $bulan);
        $hppKlaimBulan = $this->hppKlaimHarian($awalBulan, date('Y-m-t', strtotime($awalBulan)), (int) $unit);
        $omset_bulan -= array_sum($hppKlaimBulan);
        $refundBulan = $this->refundHarian($awalBulan, date('Y-m-t', strtotime($awalBulan)), (int) $unit);
        $omset_bulan -= array_sum($refundBulan);

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

        $awalBulanSebelum = sprintf('%04d-%02d-01', (int) $tahunSebelum, (int) $bulanSebelum);
        $hppKlaimBulanSebelum = $this->hppKlaimHarian($awalBulanSebelum, date('Y-m-t', strtotime($awalBulanSebelum)), (int) $unit);
        $omset_bulan_sebelum -= array_sum($hppKlaimBulanSebelum);
        $omset_bulan_sebelum -= array_sum(
            $this->refundHarian($awalBulanSebelum, date('Y-m-t', strtotime($awalBulanSebelum)), (int) $unit)
        );

        if ($omset_bulan_sebelum > 0) {
            $pertumbuhan_omset = (($omset_bulan - $omset_bulan_sebelum) / $omset_bulan_sebelum) * 100;
        } else {
            $pertumbuhan_omset = 0;
        }
        $omset_bulan_lalu = $omset_bulan_sebelum;
        $periodeLaluLabel = date('F Y', mktime(0, 0, 0, $bulanSebelum, 1, $tahunSebelum));
        $selisih_omset = $omset_bulan - $omset_bulan_sebelum;


        // ==========================
        // TOTAL PELANGGAN MASUK
        // ==========================
        // Disamakan dengan KPI CustomerCalculator: pelanggan "masuk" dihitung
        // saat service DIBUAT (created_at, semua status — bukan tunggu selesai)
        // + semua penjualan yang punya id_pelanggan (bukan hanya invoice SLL).
        $countService = $this->db->table('service')
            ->select('COUNT(idservice) AS total')
            ->where('MONTH(created_at)', $bulan)
            ->where('YEAR(created_at)', $tahun)
            ->where('unit_idunit', $unit)
            ->get()
            ->getRow()
            ->total ?? 0;

        $countSales = $this->db->table('penjualan')
            ->select('COUNT(DISTINCT idpenjualan) AS total')
            ->where('MONTH(tanggal)', $bulan)
            ->where('YEAR(tanggal)', $tahun)
            ->where('unit_idunit', $unit)
            ->where('id_pelanggan IS NOT NULL AND id_pelanggan <> 0', null, false)
            ->get()
            ->getRow()
            ->total ?? 0;

        $pelanggan_bulan = $countService + $countSales;

        // ==========================
        // TOP KECAMATAN / DOMISILI PELANGGAN
        // ==========================
        // Basis sama dengan KPI CustomerCalculator: service dihitung saat
        // DIBUAT (created_at, semua status) + semua penjualan ber-id_pelanggan.
        $kecamatanQuery = $this->db->query("
            SELECT 
                UPPER(TRIM(p.kabupaten)) AS raw_kabupaten,
                UPPER(TRIM(p.kecamatan)) AS raw_kecamatan,
                COUNT(DISTINCT sub.unik_id) AS total_transaksi,
                COUNT(DISTINCT p.id_pelanggan) AS total_pelanggan
            FROM (
                SELECT s.pelanggan_id_pelanggan AS id_pelanggan, CONCAT('SVC-', s.idservice) AS unik_id
                FROM service s
                WHERE s.unit_idunit = ?
                  AND MONTH(s.created_at) = ?
                  AND YEAR(s.created_at) = ?
                  AND s.pelanggan_id_pelanggan IS NOT NULL
                  AND s.pelanggan_id_pelanggan != 0

                UNION ALL

                SELECT pj.id_pelanggan, CONCAT('SLS-', pj.idpenjualan) AS unik_id
                FROM penjualan pj
                WHERE pj.unit_idunit = ?
                  AND MONTH(pj.tanggal) = ?
                  AND YEAR(pj.tanggal) = ?
                  AND pj.id_pelanggan IS NOT NULL
                  AND pj.id_pelanggan > 0
            ) AS sub
            JOIN pelanggan p ON p.id_pelanggan = sub.id_pelanggan
            WHERE p.deleted = '0'
              AND p.kecamatan IS NOT NULL 
              AND TRIM(p.kecamatan) != ''
            GROUP BY UPPER(TRIM(p.kabupaten)), UPPER(TRIM(p.kecamatan))
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
        $kabupatenTerdata = [];
        foreach ($listKecamatanRaw as $row) {
            $pct = $totalKecamatanPelanggan > 0 ? round(((int)$row['total_pelanggan'] / $totalKecamatanPelanggan) * 100, 1) : 0;
            $kabupaten = trim((string)($row['raw_kabupaten'] ?? ''));
            if ($kabupaten !== '') {
                $kabupatenTerdata[$kabupaten] = true;
            }
            $list_kecamatan[] = (object)[
                'nama_kabupaten'  => $kabupaten !== '' ? ucwords(strtolower($kabupaten)) : '-',
                'nama_kecamatan'  => ucwords(strtolower($row['raw_kecamatan'])),
                'total_transaksi' => (int)$row['total_transaksi'],
                'total_pelanggan' => (int)$row['total_pelanggan'],
                'persentase'      => $pct,
            ];
        }

        $total_kabupaten = count($kabupatenTerdata);

        $top_kecamatan = !empty($list_kecamatan) ? $list_kecamatan[0] : (object)[
            'nama_kabupaten'  => '-',
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

        $omset_hari_ini -= (new \App\Models\ModelHppKlaimLoss())
            ->getTotalLoss(date('Y-m-d'), date('Y-m-d'), (int) $unit);

        $omset_hari_ini -= (new \App\Models\ModelRefundGaransiLoss())
            ->getTotal(date('Y-m-d'), date('Y-m-d'), (int) $unit);

        // DP tetap terpisah dari omset penjualan, tetapi ditampilkan sebagai
        // informasi tambahan di kartu Omset Hari Ini untuk unit yang dipilih.
        $dpHariIni = (new \App\Services\Finance\TutupKasirSourceDefinition($this->db))
            ->dpMasukRincian((int) $unit, date('Y-m-d'));
            
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

        foreach ($hppKlaimBulan as $tanggal => $total) {
            $dataHarian[$tanggal] = ($dataHarian[$tanggal] ?? 0) - $total;
        }

        foreach ($refundBulan as $tanggal => $total) {
            $dataHarian[$tanggal] = ($dataHarian[$tanggal] ?? 0) - $total;
        }

        // ==========================
        // DATA HARIAN BULAN PEMBANDING (12 bulan ke belakang)
        // ==========================
        // Dipakai grafik untuk membandingkan omset harian periode terpilih
        // dengan beberapa bulan sekaligus. Default di view: bulan sebelumnya.
        $awalPeriodeTerpilih = sprintf('%04d-%02d-01', (int) $tahun, (int) $bulan);
        $awalBulanTerlama = date('Y-m-01', mktime(0, 0, 0, (int) $bulan - 12, 1, (int) $tahun));

        $resultsPembanding = $this->db->table('detail_penjualan')
            ->select("
                DATE(penjualan.tanggal) AS tgl,
                SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total
            ")
            ->join(
                'penjualan',
                'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan'
            )
            ->where('penjualan.tanggal >=', $awalBulanTerlama)
            ->where('penjualan.tanggal <', $awalPeriodeTerpilih)
            ->where('penjualan.unit_idunit', $unit)
            ->groupBy('DATE(penjualan.tanggal)')
            ->get()
            ->getResult();

        $dataHarianPembanding = [];

        foreach ($resultsPembanding as $row) {
            $dataHarianPembanding[$row->tgl] = $row->total;
        }

        $hppKlaimPembanding = $this->hppKlaimHarian(
            $awalBulanTerlama,
            date('Y-m-d', strtotime($awalPeriodeTerpilih . ' -1 day')),
            (int) $unit
        );
        foreach ($hppKlaimPembanding as $tanggal => $total) {
            $dataHarianPembanding[$tanggal] = ($dataHarianPembanding[$tanggal] ?? 0) - $total;
        }

        $refundPembanding = $this->refundHarian(
            $awalBulanTerlama,
            date('Y-m-d', strtotime($awalPeriodeTerpilih . ' -1 day')),
            (int) $unit
        );
        foreach ($refundPembanding as $tanggal => $total) {
            $dataHarianPembanding[$tanggal] = ($dataHarianPembanding[$tanggal] ?? 0) - $total;
        }

        $listBulanPembanding = [];

        for ($n = 1; $n <= 12; $n++) {
            $bulanPembanding = (int) $bulan - $n;
            $tahunPembanding = (int) $tahun;
            while ($bulanPembanding < 1) {
                $bulanPembanding += 12;
                $tahunPembanding--;
            }

            $jumlahHariPembanding = (int) date('t', mktime(0, 0, 0, $bulanPembanding, 1, $tahunPembanding));

            $hariPembanding = [];
            for ($d = 1; $d <= $jumlahHariPembanding; $d++) {
                $tglPembanding = sprintf('%04d-%02d-%02d', $tahunPembanding, $bulanPembanding, $d);
                $hariPembanding[$d] = (float) ($dataHarianPembanding[$tglPembanding] ?? 0);
            }

            $listBulanPembanding[] = [
                'value' => sprintf('%04d-%02d', $tahunPembanding, $bulanPembanding),
                'label' => date('F Y', mktime(0, 0, 0, $bulanPembanding, 1, $tahunPembanding)),
                'days'  => $jumlahHariPembanding,
                'hari'  => $hariPembanding,
                'total' => array_sum($hariPembanding),
            ];
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


        $jumlahHari = (int) date('t', mktime(0, 0, 0, (int) $bulan, 1, (int) $tahun));
        $listHari = [];

        for ($i = 1; $i <= $jumlahHari; $i++) {

            $tgl = sprintf('%04d-%02d-%02d', (int) $tahun, (int) $bulan, $i);

            $listHari[] = [
                'tanggal' => $tgl,
                'total'   => $dataHarian[$tgl] ?? 0,
            ];
        }

        return view('template', [
            'list_unit'      => $list_unit,
            'selected_unit'  => $unit,
            'id_jabatan'     => $id_jabatan,
            'boleh_pilih_unit' => $bolehPilihUnit,
            'bulan'          => $bulan,
            'tahun'          => $tahun,
            'periodeLabel'   => $periodeLabel,
            'isBulanBerjalan' => $isBulanBerjalan,
            'bulanSebelum'   => $bulanSebelum,
            'tahunSebelum'   => $tahunSebelum,
            'hpp'           => $hpp,
            'value'         => $value,
            'listHari'          => $listHari,
            'listBulanPembanding' => $listBulanPembanding,
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
            'dp_hari_ini'       => (int) ($dpHariIni['total'] ?? 0),
            'dp_jumlah_hari_ini' => (int) ($dpHariIni['jumlah'] ?? 0),
            'hariTerbaik'       => $hariTerbaik,
            'omsetTerbaik'      => $omsetTerbaik,
            'top_kecamatan'     => $top_kecamatan,
            'list_kecamatan'    => $list_kecamatan,
            'total_kecamatan_pelanggan' => $totalKecamatanPelanggan,
            'total_kecamatan_transaksi' => $totalKecamatanTransaksi,
            'total_kabupaten'   => $total_kabupaten,
            'body'              => 'jurnal/omset_bulanan'
        ]);
    }

    public function assetberjalan()
    {
        // Direktur (jabatan 2) hanya membuka unit yang dipetakan / unit akunnya.
        $scope = $this->resolveUnitScope([], true, true);
        $list_unit      = $scope['list_unit'];
        $unit           = $scope['unit'];
        $id_jabatan     = $scope['id_jabatan'];
        $bolehPilihUnit = $scope['boleh_pilih_unit'];

        // FILTER BULAN/TAHUN
        $bulan = $this->request->getGet('bulan');
        $tahun = $this->request->getGet('tahun');

        if (!$bulan || !$tahun) {
            $bulan = date('m');
            $tahun = date('Y');
        }

        $bulanStr = sprintf('%04d-%02d', (int)$tahun, (int)$bulan);

        // Hitung bulan/tahun sebelumnya
        $bulanSebelum = (int)$bulan - 1;
        $tahunSebelum = (int)$tahun;
        if ($bulanSebelum < 1) {
            $bulanSebelum = 12;
            $tahunSebelum = (int)$tahun - 1;
        }
        $periodeLabel = date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun));
        $isBulanBerjalan = ((int)$bulan == (int)date('m') && (int)$tahun == (int)date('Y'));

        // ============================
        // HO SALARY DISTRIBUTION
        // ============================
        // Selalu dihitung karena laba bersih mengurangi bagian gaji HO unit ini.
        // Panel agregatnya tetap hanya untuk role lintas cabang (issue #23): yang
        // dikirim ke mereka `ho_distribution`, role lain hanya `ho_unit_share`
        // supaya laba bersih unit yang sama tidak berbeda antar role.
        $hoDistribution = (new \App\Services\Payroll\HoSalaryDistributionService())
            ->calculateDistribution($bulanStr, (string)$tahun);

        $hoUnitShare = null;
        foreach ($hoDistribution['per_unit'] ?? [] as $u) {
            if ((int) $u['idunit'] === $unit) {
                $hoUnitShare = $u;
                break;
            }
        }
        if (!self::bolehLihatPanelHo((int) $id_jabatan)) {
            $hoDistribution = null;
        }

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
        $gajiDetail = [];

        // Nama jabatan untuk kolom "Jabatan" di rincian.
        $namaJabatan = array_column(
            $this->db->table('jabatan')->select('ID_JABATAN, NAMA_JABATAN')->get()->getResultArray(),
            'NAMA_JABATAN',
            'ID_JABATAN'
        );

        // Mesin yang sama dengan payroll (KPI + salary_structures + lembur),
        // bukan rumus legacy. Ini perkiraan; baris payroll yang sudah dibayar
        // menggantikannya di bawah supaya laba memakai uang yang benar-benar keluar.
        $gajiUnit = (new \App\Services\Payroll\PayrollGenerator())
            ->hitungGajiUnit($bulanStr, (int) $unit);

        foreach ($gajiUnit as $row) {
            $gajiDetail[] = [
                'id_akun'              => $row['id_akun'],
                'nama'                 => $row['nama'],
                'jabatan'              => $namaJabatan[$row['jabatan_id']] ?? ('#' . $row['jabatan_id']),
                'gaji_pokok'           => $row['gaji_pokok'],
                'tunjangan_kinerja'    => $row['tunjangan_kinerja'],
                'tunjangan_absen'      => $row['tunjangan_absen'],
                'tunjangan_penempatan' => $row['tunjangan_penempatan'],
                'insentif'             => $row['insentif'],
                'lembur'               => $row['lembur'],
                'total'                => $row['total'],
            ];
        }

        // Payroll berstatus dibayar = angka final: total bersih per pegawai
        // (sudah termasuk potongan kasbon/lain dan bonus). Bisa lebih dari satu
        // baris per pegawai, jadi nominalnya dijumlah. Sisa yang belum dibayar
        // tetap memakai perkiraan mesin.
        $payrollDibayar = [];
        $rowsDibayar = $this->db->table('finance_payroll fp')
            ->select('fp.pegawai_id, fp.total, fp.total_bersih, a.NAMA_AKUN, a.ID_JABATAN')
            ->join('akun a', 'a.ID_AKUN = fp.pegawai_id', 'left')
            ->where('fp.unit_id', $unit)
            ->where('fp.status', 'dibayar')
            ->where('fp.due_date >=', $bulanStr . '-01')
            ->where('fp.due_date <=', date('Y-m-t', strtotime($bulanStr . '-01')))
            ->get()
            ->getResult();

        foreach ($rowsDibayar as $p) {
            $id = (int) $p->pegawai_id;
            // Baris lama bisa punya total_bersih 0 karena kolomnya belum diisi
            // saat dibayar; pakai total bruto supaya gaji tidak hilang.
            $bersih = (float) $p->total_bersih > 0 ? (float) $p->total_bersih : (float) $p->total;

            $payrollDibayar[$id] ??= [
                'nama'    => (string) ($p->NAMA_AKUN ?? ('#' . $id)),
                'jabatan' => $namaJabatan[(int) ($p->ID_JABATAN ?? 0)] ?? ('#' . (int) ($p->ID_JABATAN ?? 0)),
                'total'   => 0.0,
                'bersih'  => 0.0,
            ];
            $payrollDibayar[$id]['total']  += (float) $p->total;
            $payrollDibayar[$id]['bersih'] += $bersih;
        }

        $sisaPerkiraan = 0.0;
        foreach ($gajiDetail as $i => $d) {
            $id = (int) $d['id_akun'];
            if (!isset($payrollDibayar[$id])) {
                $sisaPerkiraan += (float) $d['total'];
                continue;
            }

            $sisa = max(0, (float) $d['total'] - $payrollDibayar[$id]['total']);
            $sisaPerkiraan += $sisa;
            $gajiDetail[$i]['total']   = $payrollDibayar[$id]['bersih'] + $sisa;
            $gajiDetail[$i]['dibayar'] = true;
            unset($payrollDibayar[$id]);
        }

        // Pegawai yang sudah tidak aktif tetap dibayar; jangan hilangkan dari laba.
        foreach ($payrollDibayar as $p) {
            $gajiDetail[] = [
                'id_akun'              => 0,
                'nama'                 => $p['nama'],
                'jabatan'              => $p['jabatan'],
                'gaji_pokok'           => 0,
                'tunjangan_kinerja'    => 0,
                'tunjangan_absen'      => 0,
                'tunjangan_penempatan' => 0,
                'insentif'             => 0,
                'lembur'               => 0,
                'total'                => $p['bersih'],
                'dibayar'              => true,
            ];
        }

        $totalGajiUnit = array_sum(array_column($gajiDetail, 'total'));
        $gajiSumber = 'perkiraan';
        if ($rowsDibayar !== []) {
            $gajiSumber = $sisaPerkiraan > 0.005 ? 'sebagian' : 'payroll';
        }

        $pengeluaran = $this->db->table('kas_keluar')
            ->selectSum('kas_keluar.jumlah', 'total')
            ->join('kategori_kas', 'kategori_kas.idkategori_kas = kas_keluar.kategori_idkategori')
            ->where('MONTH(kas_keluar.tanggal)', $bulan)
            ->where('YEAR(kas_keluar.tanggal)', $tahun)
            ->where('kas_keluar.idunit', $unit)
            ->whereIn('kas_keluar.kategori_idkategori', $this->kategoriTerkontrol())
            ->get()
            ->getRow()
            ->total ?? 0;

        // Seluruh kas keluar bulan itu di luar biaya terkontrol, untuk kartu "Pengeluaran di Luar 6%".
        $pengeluaranFull = $this->db->table('kas_keluar')
            ->selectSum('kas_keluar.jumlah', 'total')
            ->where('MONTH(kas_keluar.tanggal)', $bulan)
            ->where('YEAR(kas_keluar.tanggal)', $tahun)
            ->where('kas_keluar.idunit', $unit)
            ->whereNotIn('kas_keluar.kategori_idkategori', $this->kategoriTerkontrol())
            ->get()
            ->getRow()
            ->total ?? 0;

        // Biaya di luar 6% yang tetap mengurangi laba bersih: Lain-lain, Iklan
        // Ads, dan SDM tanpa lembur (lembur sudah dihitung di Total Gaji).
        $queryBiayaLain = $this->db->table('kas_keluar')
            ->selectSum('kas_keluar.jumlah', 'total')
            ->where('MONTH(kas_keluar.tanggal)', $bulan)
            ->where('YEAR(kas_keluar.tanggal)', $tahun)
            ->where('kas_keluar.idunit', $unit);
        $this->saringBiayaLain($queryBiayaLain);
        $biayaLain = $queryBiayaLain->get()->getRow()->total ?? 0;

        // Jumlah hari pada bulan terpilih, dipakai view untuk "Total Tanggungan Asset".
        $hariDalamBulan = (int) date('t', mktime(0, 0, 0, $bulan, 1, $tahun));

        // ---------------------------------------------------------------
        // Tanggungan Asset franchise: satu baris per unit+bulan, dipotong
        // dari saldo `unit.tanggungan`. Bulan berjalan diperbarui otomatis;
        // bulan lain lewat tombol Simpan.
        // ---------------------------------------------------------------
        // Beban HO = aktual menggantikan perkiraan untuk karyawan yang sudah
        // dibayar (sama seperti Total Gaji). Selama belum ada yang dibayar,
        // angkanya sama dengan perkiraan bagian unit.
        $hoDibebankan = (float) ($hoUnitShare['total_dibebankan'] ?? 0);
        $hoDipotongPayroll = (float) ($hoUnitShare['total_dipotong'] ?? 0);
        $hoPotongan = $hoDibebankan > 0
            ? $hoDibebankan
            : ($hoDipotongPayroll > 0
                ? $hoDipotongPayroll
                : (float) ($hoUnitShare['total_perkiraan'] ?? 0));
        $profitNett = $omset_bulan - $pengeluaran - $biayaLain - $totalGajiUnit - $hoPotongan;

        $unitRow = $this->db->table('unit')
            ->where('idunit', $unit)
            ->get()->getRow();
        $isFranchise = strtolower((string) ($unitRow->jenis ?? '')) === 'franchise';
        $tanggunganMentah = $isFranchise ? max(0.0, (float) $profitNett * 0.20) : 0.0;

        $tanggunganService     = new \App\Services\Finance\TanggunganAssetService();
        $tanggunganTersimpan   = null;
        $bisaSimpanTanggungan  = false;
        $sisaTanggungan        = (float) ($unitRow->tanggungan ?? 0);

        if ($isFranchise) {
            $mintaSimpan = $this->request->is('post')
                && (int) $this->request->getPost('simpan_tanggungan') === 1;

            // Bulan berjalan: satu baris yang ikut diperbarui sampai bulan
            // berganti, supaya tidak terkunci di angka awal bulan.
            if ($isBulanBerjalan && $sisaTanggungan > 0) {
                $tanggunganService->simpan($unit, $bulanStr, $tanggunganMentah, true);
            }

            $tanggunganTersimpan = $tanggunganService->nominalTersimpan($unit, $bulanStr);
            $sisaTanggungan      = $tanggunganService->sisa($unit);

            // Bulan lain: hanya lewat tombol, dan tidak boleh menyisip ke
            // masa depan. Redirect juga saat baris sudah ada supaya refresh
            // tidak mengirim ulang form.
            if ($mintaSimpan && $bulanStr <= date('Y-m')) {
                if ($tanggunganTersimpan === null && $sisaTanggungan > 0) {
                    $tanggunganService->simpan($unit, $bulanStr, $tanggunganMentah);
                }

                return redirect()->to(
                    base_url('asset_berjalan?unit=' . $unit . '&bulan=' . (int) $bulan . '&tahun=' . (int) $tahun)
                );
            }

            $bisaSimpanTanggungan = $tanggunganTersimpan === null
                && $bulanStr <= date('Y-m')
                && $sisaTanggungan > 0;
        }

        // Franchise memakai angka tersimpan (atau perkiraan yang dibatasi
        // sisa); unit pribadi tetap memakai rumus lama di view.
        $tanggunganAsset = $isFranchise
            ? (float) ($tanggunganTersimpan ?? min($tanggunganMentah, max(0.0, $sisaTanggungan)))
            : null;

        // Tagihan Center = 40% sisa laba setelah tanggungan asset. Dihitung di
        // sini supaya invoice memakai angka yang sama dengan kartu halaman.
        $tagihanCenter = in_array((int) $unit, [3, 4, 5], true)
            ? 0.40 * ($profitNett - (float) ($tanggunganAsset ?? 0))
            : 0.0;
        $bolehCetakInvoice = in_array((int) $unit, [3, 4, 5], true)
            && self::bolehLihatRincianGaji((int) $id_jabatan);

        $data = [
            'list_unit'      => $list_unit,
            'selected_unit'  => $unit,
            'id_jabatan'     => $id_jabatan,
            'boleh_pilih_unit' => $bolehPilihUnit,
            'boleh_cetak_invoice' => $bolehCetakInvoice,
            'bulan'          => $bulan,
            'tahun'          => $tahun,
            'periodeLabel'   => $periodeLabel,
            'isBulanBerjalan' => $isBulanBerjalan,
            'hariDalamBulan' => $hariDalamBulan,
            'bulanSebelum'   => $bulanSebelum,
            'tahunSebelum'   => $tahunSebelum,
            'pengeluaran'     => $pengeluaran,
            'pengeluaran_full' => $pengeluaranFull,
            'biaya_lain'      => $biayaLain,
            'totalGajiUnit'  => $totalGajiUnit,
            'gaji_sumber'    => $gajiSumber,
            // Rincian per pegawai hanya untuk yang berhak; null = tidak dikirim
            // sama sekali, jadi tidak ada datanya di HTML.
            'gaji_detail'    => self::bolehLihatRincianGaji((int) session('ID_JABATAN')) ? $gajiDetail : null,
            'omset_bulan'       => $omset_bulan,
            'pertumbuhan_omset' => $pertumbuhan_omset,
            'omset_bulan_lalu'  => $omset_bulan_lalu,
            'periodeLaluLabel'  => $periodeLaluLabel,
            'selisih_omset'     => $selisih_omset,
            // HO Salary Distribution
            'ho_distribution' => $hoDistribution,
            'ho_unit_share'   => $hoUnitShare,
            // Satu sumber angka laba & tanggungan untuk view.
            'profit_nett'     => $profitNett,
            'tanggungan_asset' => $tanggunganAsset,
            'tagihan_center'  => $tagihanCenter,
            'tanggungan_tersimpan' => $tanggunganTersimpan !== null,
            'bisa_simpan_tanggungan' => $bisaSimpanTanggungan,
            'sisa_tanggungan' => $isFranchise ? $sisaTanggungan : null,
            'body'              => 'dashboard/asset_berjalan',
        ];

        // Cetak invoice internal: dokumen saja, tidak mencatat tagihan baru.
        if ($this->request->getGet('invoice') === '1') {
            if (! $bolehCetakInvoice) {
                return $this->response->setStatusCode(403)->setBody('Tidak berhak mencetak invoice unit ini.');
            }

            helper('invoice_internal');

            $alamatUnit = implode(', ', array_filter([
                trim((string) ($unitRow->JALAN_UNIT ?? '')),
                trim((string) ($unitRow->KELURAHAN_UNIT ?? '')),
                trim((string) ($unitRow->KECAMATAN_UNIT ?? '')),
                trim((string) ($unitRow->KABUPATEN_UNIT ?? '')),
                trim((string) ($unitRow->PROVINSI_UNIT ?? '')),
            ]));

            // Rincian gaji cabang untuk lampiran invoice: komponen per
            // karyawan. Baris payroll yang sudah dibayar memakai total aktual
            // (termasuk bonus/potongan), jadi selisihnya masuk "Penyesuaian
            // payroll" — sama seperti rincian gaji HO.
            $gajiCabang = [];
            if ($gajiDetail !== []) {
                $totalBulat = (int) round($totalGajiUnit);
                $selisihTotal = $totalBulat - array_sum(array_map(
                    static fn (array $g): int => (int) round((float) ($g['total'] ?? 0)),
                    $gajiDetail
                ));
                if ($selisihTotal !== 0) {
                    $akhir = count($gajiDetail) - 1;
                    $gajiDetail[$akhir]['total'] = (float) ($gajiDetail[$akhir]['total'] ?? 0) + $selisihTotal;
                }
            }

            foreach ($gajiDetail as $gaji) {
                $gajiCabang[] = [
                    'nama'     => (string) ($gaji['nama'] ?? '-'),
                    'nominal'  => (int) round((float) ($gaji['total'] ?? 0)),
                    'komponen' => invoice_gaji_komponen($gaji),
                ];
            }

            $kodeUnit  = trim((string) ($unitRow->kode_unit ?? ''));
            $config    = config('InvoiceInternal');
            $signers   = invoice_internal_signers();
            $invoiceNo = 'INT/' . date('Ym', strtotime($bulanStr . '-01')) . '/'
                . ($kodeUnit !== '' ? $kodeUnit : (string) $unit);

            $dataInvoice = [
                'nama_usaha'        => $config->namaUsaha,
                'alamat_usaha'      => $config->alamat,
                'telp_usaha'        => $config->telepon,
                'unit_nama'         => (string) ($unitRow->NAMA_UNIT ?? ('Unit ' . $unit)),
                'unit_alamat'       => $alamatUnit,
                'periode'           => $bulanStr,
                'invoice_no'        => $invoiceNo,
                'invoice_date'      => date('Y-m-d', min(strtotime($bulanStr . '-01 +1 month'), time())),
                'royalti'           => $tagihanCenter,
                'gaji_cabang_total' => $totalGajiUnit,
                'gaji_cabang'       => $gajiCabang,
                'ho_total'          => $hoPotongan,
                // Unit trial tidak menanggung HO (bagiannya 0), jadi helper
                // tidak menghasilkan rincian karyawan HO.
                'ho_employees'      => invoice_ho_employees($hoDistribution ?? [], (int) $unit, (float) $hoPotongan),
                'unit_trial'        => (int) ($unitRow->is_trial ?? 0) === 1,
                'finance_nama'      => $signers['finance'],
                'manager_nama'      => $signers['manager'],
                'bank'              => [
                    'nama'      => $config->bank,
                    'norek'     => $config->norek,
                    'atas_nama' => $config->atasNama,
                ],
                'catatan' => $config->catatan,
            ];

            $namaFile = 'invoice-internal-' . $bulanStr . '-unit-' . (int) $unit . '.pdf';

            return $this->response
                ->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Disposition', 'inline; filename="' . $namaFile . '"')
                ->setBody(invoice_internal_pdf($dataInvoice));
        }

        return view('template', $data);
    }

    /**
     * Detail pengeluaran untuk kartu Biaya Terkontrol / Biaya Kebutuhan /
     * Biaya Iklan, SDM & Lain-lain. Dimuat hanya saat tombol Detail diklik.
     */
    public function detailPengeluaran()
    {
        $scope = $this->resolveUnitScope([], true, true);

        // resolveUnitScope() sudah mem-fallback unit di luar izin; kalau hasilnya
        // beda dengan yang diminta, berarti user tidak berhak atas unit itu.
        $unit = (int) ($this->request->getGet('unit') ?: $scope['unit']);
        if ($unit !== (int) $scope['unit']) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Unit tidak diizinkan']);
        }

        $kelompok = (string) $this->request->getGet('kelompok');
        if (! in_array($kelompok, ['terkontrol', 'kebutuhan', 'lain'], true)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Kelompok tidak dikenal']);
        }

        $bulan = (int) $this->request->getGet('bulan');
        $tahun = (int) $this->request->getGet('tahun');
        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > 2100) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Periode tidak valid']);
        }

        // DataTables server-side: hanya satu halaman baris yang dikirim, bukan
        // seluruh pengeluaran. Ringkasan (total & per kategori) tetap dihitung
        // dari seluruh hasil filter, bukan hanya halaman yang tampil.
        $draw   = (int) ($this->request->getGet('draw') ?? 0);
        $start  = max(0, (int) ($this->request->getGet('start') ?? 0));
        $length = (int) ($this->request->getGet('length') ?? 10);
        $length = ($length > 0 && $length <= 100) ? $length : 10;

        $search = $this->request->getGet('search');
        $search = is_array($search) ? trim((string) ($search['value'] ?? '')) : '';

        // Filter kategori (single pilih). 0 / kosong = tanpa filter.
        $kategoriFilter = (int) ($this->request->getGet('kategori') ?? 0);
        $kategoriTerkontrol = $this->kategoriTerkontrol();

        $order   = $this->request->getGet('order');
        $order   = is_array($order) ? ($order[0] ?? []) : [];
        $sortMap = [
            0 => 'kas_keluar.tanggal',
            1 => 'kategori_kas.kategori',
            3 => 'kas_keluar.penerima',
            4 => 'kas_keluar.jumlah',
        ];
        $sortCol = $sortMap[(int) ($order['column'] ?? 0)] ?? 'kas_keluar.tanggal';
        $dir     = strtoupper((string) ($order['dir'] ?? 'asc')) === 'DESC' ? 'DESC' : 'ASC';

        // Syarat yang sama dipakai count, sum, group, dan halaman baris.
        // $denganKategori=false untuk chip kategori: chip harus tetap lengkap
        // walau tabel sedang difilter, supaya user bisa ganti kategori langsung.
        // $denganCari=false untuk recordsTotal: itu total kelompok, bukan hasil cari.
        $saring = function ($builder, bool $denganKategori = true, bool $denganCari = true) use ($bulan, $tahun, $unit, $kelompok, $search, $kategoriFilter, $kategoriTerkontrol): void {
            $builder->where('MONTH(kas_keluar.tanggal)', $bulan)
                ->where('YEAR(kas_keluar.tanggal)', $tahun)
                ->where('kas_keluar.idunit', $unit);

            if ($kelompok === 'terkontrol') {
                $builder->whereIn('kas_keluar.kategori_idkategori', $kategoriTerkontrol);
            } elseif ($kelompok === 'lain') {
                $this->saringBiayaLain($builder);
            } else {
                $builder->whereNotIn('kas_keluar.kategori_idkategori', $kategoriTerkontrol);
            }

            if ($denganKategori && $kategoriFilter > 0) {
                $builder->where('kas_keluar.kategori_idkategori', $kategoriFilter);
            }

            if ($denganCari && $search !== '') {
                $builder->groupStart()
                    ->like('kas_keluar.deskripsi', $search)
                    ->orLike('kas_keluar.penerima', $search)
                    ->orLike('kategori_kas.kategori', $search)
                    ->groupEnd();
            }
        };
        $dasar = fn () => $this->db->table('kas_keluar')
            ->join('kategori_kas', 'kategori_kas.idkategori_kas = kas_keluar.kategori_idkategori', 'left');

        $tanpaCari = $dasar();
        $saring($tanpaCari, false, false);
        $totalSemua = (int) $tanpaCari->countAllResults();

        $denganCari = $dasar()->select('COUNT(*) AS c, COALESCE(SUM(kas_keluar.jumlah), 0) AS t');
        $saring($denganCari);
        $agregat = $denganCari->get()->getRow();

        $halaman = $dasar()
            ->select('kas_keluar.idkas_keluar, kas_keluar.tanggal, kas_keluar.kategori_idkategori, kategori_kas.kategori AS nama_kategori, kas_keluar.deskripsi, kas_keluar.penerima, kas_keluar.jumlah');
        $saring($halaman);
        $rows = $halaman->orderBy($sortCol, $dir)
            ->orderBy('kas_keluar.idkas_keluar', 'ASC')
            ->limit($length, $start)
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['jumlah'] = (float) $row['jumlah'];
        }
        unset($row);

        $grup = $dasar()
            ->select('kategori_kas.idkategori_kas AS id, COALESCE(kategori_kas.kategori, "Tanpa kategori") AS nama, SUM(kas_keluar.jumlah) AS jumlah')
            ->groupBy('kategori_kas.idkategori_kas, kategori_kas.kategori');
        $saring($grup, false);
        $kategori = [];
        foreach ($grup->get()->getResultArray() as $g) {
            $kategori[] = [
                'id'     => (int) ($g['id'] ?? 0),
                'nama'   => (string) $g['nama'],
                'jumlah' => (float) $g['jumlah'],
            ];
        }
        usort($kategori, static fn ($a, $b) => $b['jumlah'] <=> $a['jumlah']);

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $totalSemua,
            'recordsFiltered' => (int) $agregat->c,
            'data'            => $rows,
            'total'           => (float) $agregat->t,
            'kategori'        => $kategori,
        ]);
    }

    public function tutup()
    {
        $today = date('Y-m-d');
        $isAdminRoot = $this->isAdminRoot();

        if ($isAdminRoot) {
            $tanggal = $this->cleanClosingDate($this->request->getPost('tanggal'));
            $postedUnit = $this->cleanClosingUnit($this->request->getPost('unit'));
            $fallbackUnit = (int) session('ID_UNIT');
            if (! $this->isKnownClosingUnit($fallbackUnit)) {
                $fallbackUnit = $this->isKnownClosingUnit($postedUnit) ? $postedUnit : 0;
            }
            $fallbackDate = $tanggal !== null && $tanggal <= $today ? $tanggal : $today;

            if ($tanggal === null || $tanggal > $today) {
                return redirect()->to($this->closingPageUrl($fallbackUnit, $fallbackDate, true))
                    ->with('gagal', 'Tanggal closing tidak valid. Pilih tanggal hari ini atau sebelumnya.');
            }

            if (! $this->isKnownClosingUnit($postedUnit)) {
                return redirect()->to($this->closingPageUrl($fallbackUnit, $tanggal, true))
                    ->with('gagal', 'Unit tidak valid. Silakan pilih unit dari daftar.');
            }

            $unit = $postedUnit;
        } else {
            // Unit dan tanggal dari POST tidak pernah dipercaya untuk role lain.
            $unit = (int) session()->get('ID_UNIT');
            $tanggal = $today;
        }

        $redirectUrl = $this->closingPageUrl($unit, $tanggal, $isAdminRoot);
        if ($unit <= 0) {
            return redirect()->to($redirectUrl)
                ->with('gagal', 'Unit tidak valid. Silakan pilih unit yang tersedia.');
        }

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
                $tanggal,
                $this->request->getPost('cash_laci'),
                (int) session('ID_AKUN'),
                $isAdminRoot
            );

        if (! $hasil['ok']) {
            return redirect()->to($redirectUrl)
                ->with('gagal', (string) $hasil['alasan']);
        }

        // Idempoten: posting ganda / tombol diklik dua kali. Closing hari ini
        // sudah ada, jadi tidak disimpan lagi — tetap dianggap berhasil.
        if ($hasil['kode'] === 'sudah_ada') {
            return redirect()->to($redirectUrl)
                ->with('sukses', (string) $hasil['alasan']);
        }

        return redirect()->to($redirectUrl)
            ->with('sukses', 'Tutup kasir berhasil');
    }

    private function isAdminRoot(): bool
    {
        return (int) session('ID_JABATAN') === 1;
    }

    /** Rincian gaji hanya untuk Admin root (1), Finance (0), dan Manager (34). */
    public static function bolehLihatRincianGaji(int $idJabatan): bool
    {
        return in_array($idJabatan, [0, 1, 34], true);
    }

    /**
     * Panel "Pembagian Gaji Head Office" (agregat gaji HO) hanya untuk role
     * lintas cabang: Finance (0), Admin root (1), Direktur (2), Manager (34),
     * dan SPV (40). Staf cabang hanya boleh melihat gaji unitnya sendiri.
     */
    public static function bolehLihatPanelHo(int $idJabatan): bool
    {
        return in_array($idJabatan, [0, 1, 2, 34, 40], true);
    }

    private function cleanClosingDate($value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));
        return checkdate($month, $day, $year) ? $value : null;
    }

    private function cleanClosingUnit($value): int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : 0;
        }

        if (! is_string($value) || preg_match('/^[1-9]\d*$/', $value) !== 1) {
            return 0;
        }

        return (int) $value;
    }

    private function isKnownClosingUnit(int $unit): bool
    {
        return $unit > 0 && $this->db->table('unit')
            ->where('idunit', $unit)
            ->countAllResults() > 0;
    }

    private function closingPageUrl(int $unit, string $tanggal, bool $isAdminRoot): string
    {
        $url = base_url('tutup_kasir');
        if (! $isAdminRoot) {
            return $url;
        }

        return $url . '?' . http_build_query(['unit' => $unit, 'tanggal' => $tanggal]);
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
        $isAdminRoot = $this->isAdminRoot();
        $id = (int) $id;
        $tutupKasir = $isAdminRoot
            ? $this->db->table('tutup_kasir')->where('idtutupkasir', $id)->get()->getRow()
            : $this->TutupKasir->getById($id, (int) session('ID_UNIT'));

        if ($tutupKasir === null) {
            return redirect()->to(base_url('tutup_kasir'))
                ->with('gagal', 'Data tutup kasir tidak ditemukan atau Anda tidak memiliki akses.');
        }

        $unit = (int) $tutupKasir->unit;
        $tanggal = date('Y-m-d', strtotime((string) $tutupKasir->tanggal));

        $totalservice = $this->db->table('service')
            ->selectSum('bayar_tunai', 'total')
            ->where('DATE(tanggal_selesai)', $tanggal)
            ->where('status_service', 4)
            ->where('unit_idunit', $unit)
            ->get()
            ->getRow()->total ?? 0;
        
        $qtyservice = $this->db->table('service')
            ->selectCount('bayar_tunai', 'total')
            ->where('DATE(tanggal_selesai)', $tanggal)
            ->where('status_service', 4)
            ->where('unit_idunit', $unit)
            ->get()
            ->getRow()->total ?? 0;
        
        // PENGELUARAN Cash
        $opcash = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 'total')
            ->where('idunit', $unit)
            ->where('DATE(tanggal)', $tanggal)
            ->where('idbank IS NULL', null, false)
            ->like('deskripsi', 'operasional', 'after')
            ->get()
            ->getRow()->total ?? 0;
        
        $optf = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 'total')
            ->where('idunit', $unit)
            ->where('DATE(tanggal)', $tanggal)
            ->where('idbank IS NOT NULL', null, false)
            ->like('deskripsi', 'operasional', 'after')
            ->get()
            ->getRow()->total ?? 0;

        $pscash = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 'total')
            ->where('idunit', $unit)
            ->where('DATE(tanggal)', $tanggal)
            ->where('idbank IS NULL', null, false)
            ->like('deskripsi', 'sparepart', 'after')
            ->get()
            ->getRow()->total ?? 0;
        
        $pstf = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 'total')
            ->where('idunit', $unit)
            ->where('DATE(tanggal)', $tanggal)
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
            ->like('penjualan.tanggal', $tanggal, 'after')
            ->get()
            ->getRow()
            ->total ?? 0;

        $hp_qty = $this->db->table('detail_penjualan')
            ->selectCount('detail_penjualan.sub_total', 'total')
            ->join('barang', 'barang.idbarang = detail_penjualan.barang_idbarang', 'left')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan', 'left')
            ->where('barang.idkategori', 1)
            ->where('penjualan.unit_idunit', $unit)
            ->like('penjualan.tanggal', $tanggal, 'after')
            ->get()
            ->getRow()
            ->total ?? 0;

        $acc_total = $this->db->table('detail_penjualan')
            ->selectSum('detail_penjualan.sub_total', 'total')
            ->join('barang', 'barang.idbarang = detail_penjualan.barang_idbarang', 'left')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan', 'left')
            ->where('barang.idkategori', 2)
            ->where('penjualan.unit_idunit', $unit)
            ->like('penjualan.tanggal', $tanggal, 'after')
            ->get()
            ->getRow()
            ->total ?? 0;
            
        $acc_qty = $this->db->table('detail_penjualan')
            ->selectCount('detail_penjualan.sub_total', 'total')
            ->join('barang', 'barang.idbarang = detail_penjualan.barang_idbarang', 'left')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan', 'left')
            ->where('barang.idkategori', 2)
            ->where('penjualan.unit_idunit', $unit)
            ->like('penjualan.tanggal', $tanggal, 'after')
            ->get()
            ->getRow()
            ->total ?? 0;

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

        $filename = 'laporan-tutup-kasir-' . $tanggal . '-unit-' . $unit . '.pdf';

        $mpdf->Output($filename, 'I');
        exit;
    }
}
