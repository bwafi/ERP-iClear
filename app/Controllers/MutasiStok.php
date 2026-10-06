<?php

namespace App\Controllers;

use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelKartuStok;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\ModelBarang;
use App\Models\ModelKategori;
use App\Models\ModelPembelian;
use App\Models\ModelStokAwal;
use App\Models\ModelDetailPembelian;
use App\Models\ModelSuplier;
use App\Models\ModelMutasiStok;
use App\Models\ModelUnit;
use App\Models\ModelHppBarang;
use App\Models\ModelDetailMutasi;
use App\Models\ModelStokBarang;
use App\Models\ModelHutangPiutang;
use Mpdf\Mpdf;
use DateTime;
use App\Libraries\ModeKasBank;

class MutasiStok extends BaseController

{

    protected $AuthModel;
    protected $KartuStokModel;
    protected $BarangModel;
    protected $KategoriModel;
    protected $SuplierModel;
    protected $PembelianModel;
    protected $StokAwalModel;
    protected $DetailPembelianModel;
    protected $MutasiStokModel;
    protected $UnitModel;
    protected $HppBarangModel;
    protected $DetailMutasiModel;
    protected $StokBarangModel;
    protected $KasBankLib;


    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->KartuStokModel = new ModelKartuStok();
        $this->BarangModel = new ModelBarang();
        $this->KategoriModel = new ModelKategori();
        $this->SuplierModel = new ModelSuplier();
        $this->PembelianModel = new ModelPembelian();
        $this->StokAwalModel = new ModelStokAwal();
        $this->DetailPembelianModel = new ModelDetailPembelian();
        $this->MutasiStokModel = new ModelMutasiStok();
        $this->UnitModel = new ModelUnit();
        $this->HppBarangModel = new ModelHppBarang();
        $this->DetailMutasiModel = new ModelDetailMutasi();
        $this->StokBarangModel = new ModelStokBarang();
        $this->KasBankLib = new ModeKasBank();
    }

    public function index()
    {
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));
        $data =  array(
            'akun' => $akun,
            'stok' => $this->KartuStokModel->getKartuStokWithKategori(),
            'produk' => $this->BarangModel->getAllBarang(),
            'kategori' => $this->KategoriModel->getKategori(),
            'suplier' => $this->SuplierModel->getSuplier(),
            'unit' => $this->UnitModel->getUnit(),
            'body'  => 'stok/mutasi_stok'
        );
        return view('template', $data);
    }

    /**
     * Daftar mutasi yang ditujukan ke unit pengguna (admin cabang penerima),
     * untuk konfirmasi penerimaan. Admin lintas (root/direktur/manager/admin
     * center) melihat semua mutasi.
     */
    /**
     * Halaman konfirmasi terima mutasi.
     *
     * unbounded `limit(200)` lama di sini disembunyikan: yang terlihat 200
     * nota pertama saja, dan sisanya mustahil dicari karena tidak ada filter.
     * Sekarang jadi query berpaginasi, dan angka totalnya diambil dari
     * COUNT yang syaratnya sama persis dengan query datanya.
     */
    public function masuk()
    {
        $unit = (int) session('ID_UNIT');
        $isLintas = in_array((int) session('ID_JABATAN'), [0, 1, 2, 34], true);

        $filter = $this->filterMasuk();
        $perPage = 25;
        $page = max(1, (int) $this->request->getGet('page'));
        $total = $this->MutasiStokModel->countMutasiMasuk($isLintas, $unit, $filter);
        $totalPages = max(1, (int) ceil($total / $perPage));

        //.User bisa tiba di halaman 5 lalu memfilter sampai tersisa 1 halaman.
        // Tanpa clamp di sini tabelnya kosong dan tidak ada cara kembali
        // kecuali mengetik URL manual.
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $daftar = $this->MutasiStokModel->getMutasiMasuk($perPage, $page, $isLintas, $unit, $filter);

        // Peta unit diambil sekali. Sebelumnya setiap baris memanggil
        // getById() dua kali; dengan 25 baris per halaman itu 50 query sia-sia
        // hanya untuk nama unit.
        $semuaUnit = $this->UnitModel->getUnit();
        $unitMap = [];
        foreach ($semuaUnit as $u) {
            $unitMap[(int) $u->idunit] = $u->NAMA_UNIT;
        }

        $items = [];
        foreach ($daftar as $m) {
            $detail = $this->DetailMutasiModel->getFullDetailMutasiByMutasiId((int) $m->idmutasi);
            $totalNilai = 0;
            foreach ($detail as $d) {
                $d->nilai = $this->KasBankLib->nilaiDetailMutasi((array) $d);
                $totalNilai += (int) $d->nilai;
            }
            $items[] = (object) [
                'idmutasi'          => (int) $m->idmutasi,
                'no_nota_mutasi'    => $m->no_nota_mutasi,
                'tanggal_kirim'     => date('Y-m-d', strtotime($m->tanggal_kirim)),
                'tanggal_terima'    => $m->tanggal_terima ? date('Y-m-d', strtotime($m->tanggal_terima)) : null,
                'status'            => (string) $m->status,
                'kirim_idunit'      => (int) $m->kirim_idunit,
                'terima_idunit'     => (int) $m->terima_idunit,
                'nama_unit_kirim'   => $unitMap[(int) $m->kirim_idunit] ?? "Unit {$m->kirim_idunit}",
                'nama_unit_terima'  => $unitMap[(int) $m->terima_idunit] ?? "Unit {$m->terima_idunit}",
                'total'             => $totalNilai,
                'detail'            => $detail,
            ];
        }

        $data = [
            'akun'        => $this->AuthModel->getById(session('ID_AKUN')),
            'unit'        => $semuaUnit,
            'items'       => $items,
            'filter'      => $filter,
            'isLintas'    => $isLintas,
            'currentPage' => $page,
            'perPage'     => $perPage,
            'total'       => $total,
            'totalPages'  => $totalPages,
            'body'        => 'stok/mutasi_masuk',
        ];
        return view('template', $data);
    }

    /**
     * Baca filter dari query string, semua sudah divalidasi.
     *
     * Nilai yang tidak dikenal dibuang, bukan diteruskan ke query.
     * Status dibatasi ke 0/1 dan tanggal harus tanggal yang benar-benar ada,
     * supaya `?status=abc` atau `?dari=xyz` tidak jadi SQL aneh — dan supaya
     * URL yang tidak valid tidak bisa membuat footer menghitung halaman
     * yang berbeda dari tabelnya.
     */
    private function filterMasuk(): array
    {
        $status = trim((string) ($this->request->getGet('status') ?? ''));

        return [
            'search' => trim((string) ($this->request->getGet('search') ?? '')),
            'status' => in_array($status, ['0', '1'], true) ? $status : '',
            'dari'   => $this->tanggalMasuk($this->request->getGet('dari')),
            'sampai'=> $this->tanggalMasuk($this->request->getGet('sampai')),
            'unit'   => (int) ($this->request->getGet('unit') ?: 0),
        ];
    }

    private function tanggalMasuk($v): string
    {
        $v = trim((string) $v);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            return '';
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $v : '';
    }

    /**
     * Kembalikan user ke daftar dengan filter & halaman yang sama.
     *
     * Dipakai setelah terima/batal, supaya menerima satu nota tidak melempar
     * user balik ke daftar tanpa filter dan harus mencari lagi dari awal —
     * justru hal yang paling menyebalkan begitu ada paginasi.
     *
     * Query string diambil dari field `asal`, bukan HTTP_REFERER: referer
     * hilang begitu user refresh atau membuka tab baru, dan user yang sudah
     * menyaring tiga kriteria tidak boleh kehilangan tempatnya. Kuncinya
     * tetap dibatasi daftar putih dan divalidasi ulang, jadi `asal` tidak
     * bisa jadi jalur menyuntik parameter ke URL lain.
     */
    private function kembaliKeDaftarMasuk(): string
    {
        $base = base_url('mutasi_stok/masuk');
        $asal = (string) ($this->request->getPost('asal') ?? '');
        if ($asal === '' || strpos($asal, '?') === false) {
            return $base;
        }

        parse_str((string) parse_url($asal, PHP_URL_QUERY), $q);
        $params = array_filter([
            'search'  => trim((string) ($q['search'] ?? '')),
            'status'  => in_array((string) ($q['status'] ?? ''), ['0', '1'], true) ? (string) $q['status'] : '',
            'dari'    => $this->tanggalMasuk($q['dari'] ?? ''),
            'sampai' => $this->tanggalMasuk($q['sampai'] ?? ''),
            'unit'    => (int) ($q['unit'] ?? 0) ?: '',
            'page'    => max(1, (int) ($q['page'] ?? 1)),
        ], static fn ($v) => $v !== '' && $v !== 0);

        return $params ? $base . '?' . http_build_query($params) : $base;
    }

    /**
     * Konfirmasi penerimaan mutasi oleh admin unit penerima. Saat itu
     * HUTANG/PIUTANG antar unit dibuat, jatuh tempo = tanggal terima + 3 hari.
     */
    public function terima($idmutasi)
    {
        $idmutasi = (int) $idmutasi;
        $mutasi = $this->MutasiStokModel->getById($idmutasi);
        if (!$mutasi) {
            session()->setFlashdata('gagal', 'Mutasi tidak ditemukan.');
            return redirect()->back();
        }

        $unit = (int) session('ID_UNIT');
        $isLintas = in_array((int) session('ID_JABATAN'), [0, 1, 2, 34], true);
        if (!$isLintas && (int) $mutasi->terima_idunit !== $unit) {
            session()->setFlashdata('gagal', 'Anda hanya bisa menerima mutasi yang ditujukan ke unit Anda.');
            return redirect()->back();
        }

        if ((string) $mutasi->status === '1') {
            session()->setFlashdata('gagal', 'Mutasi sudah diterima sebelumnya.');
            return redirect()->back();
        }

        $db = \Config\Database::connect();
        try {
            $db->transStart();

            $now = date('Y-m-d H:i:s');
            $this->MutasiStokModel->update($idmutasi, [
                'status'        => '1',
                'tanggal_terima'=> $now,
                'input_by'      => (int) session('ID_AKUN'),
                'updated_on'    => $now,
            ]);

            $r = $this->KasBankLib->buatHutangPiutangDariMutasi($idmutasi, (int) session('ID_AKUN'));

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'KasBank: gagal konfirmasi terima mutasi #' . $idmutasi . ': ' . $e->getMessage());
            session()->setFlashdata('gagal', 'Gagal menyimpan penerimaan mutasi. Silakan coba lagi.');
            return redirect()->back();
        }

        if ($db->transStatus() === false) {
            session()->setFlashdata('gagal', 'Gagal menyimpan penerimaan mutasi.');
            return redirect()->back();
        }

        session()->setFlashdata('sukses', 'Mutasi diterima. Hutang/Piutang antar unit dibuat otomatis (jatuh tempo +3 hari).');
        // Kembali ke filter & halaman asal, bukan daftar polos: kalau user
        // sedang menyaring "belum diterima" di halaman 3, nota yang baru saja
        // ia terima akan hilang dari pandangan. Itu membingungkan — orang
        // bisa mengira aksinya tidak bekerja.
        return redirect()->to($this->kembaliKeDaftarMasuk());
    }

    /**
     * Batalkan penerimaan mutasi (keputusan 2026-09-27).
     *
     * Memperbaiki salah klik pada tombol Terima. Tombol Terima melakukan DUA
     * hal sekaligus, jadi membalikannya tidak bisa hanya menyentuh `status`:
     *   1. status mutasi -> '1', tanggal_terima diisi
     *   2. ModeKasBank::buatHutangPiutangDariMutasi() membuat sepasang
     *      Hutang/Piutang antar unit (pengirim berpiutang, penerima berhutang)
     *
     * Kalau hanya status yang dikembalikan, sepasang dokumen itu tetap
     * menggantung di buku besar dan mutasinya bisa di-terima lagi — membuat
     * dokumen kembar. Jadi keduanya dibatalkan dalam satu transaksi.
     *
     * Dokumen H/P tidak dihapus fisik, hanya di-soft delete (deleted = 1):
     * pembatalan dokumen keuangan harus meninggalkan jejak, dan tabelnya
     * sudah menyediakan kolom itu. Alasan pembatalan ikut disimpan di
     * `keterangan` supaya barang yang dihapus bisa dibaca sendiri.
     *
     * BARRIER: kalau ada pembayaran yang sudah menempel pada dokumen H/P
     * itu, pembatalan DITOLAK. Mematikan dokumen yang sudah bergerak di kas
     * sama dengan menghapus jejak uang, dan itu bukan urusan pembatalan.
     *
     * Otorisasi: hanya jabatan 1 (Admin Root). Berbeda dari menerima, yang
     * boleh siapa pun yang punya akses unit tujuan: membatalkan dokumen
     * keuangan adalah tindakan yang lebih besar daripada membuatnya.
     */
    public function batalTerima($idmutasi)
    {
        $idmutasi = (int) $idmutasi;
        $redirect = redirect()->to($this->kembaliKeDaftarMasuk());

        if (! $this->bolehBatalTerima()) {
            $redirect->with('gagal', 'Hanya Admin Root yang bisa membatalkan penerimaan mutasi.');
            return $redirect;
        }

        $mutasi = $this->MutasiStokModel->getById($idmutasi);
        if (! $mutasi) {
            $redirect->with('gagal', 'Mutasi tidak ditemukan.');
            return $redirect;
        }

        if ((string) $mutasi->status !== '1') {
            $redirect->with('gagal', 'Mutasi ini belum diterima, jadi tidak ada yang perlu dibatalkan.');
            return $redirect;
        }

        $alasan = trim((string) ($this->request->getPost('alasan_batal') ?? ''));
        if ($alasan === '') {
            $redirect->with('gagal', 'Alasan pembatalan wajib diisi.');
            return $redirect;
        }

        $hpModel = new ModelHutangPiutang();
        $dokumen = $hpModel->where('sumber_tipe', 'mutasi_unit')
            ->where('sumber_id', $idmutasi)
            ->where('deleted', 0)
            ->findAll();

        foreach ($dokumen as $d) {
            $sudahBayar = (int) $d->total_dibayar;
            $sisa       = (int) $d->sisa;
            $total      = (int) $d->total;
            if ($sudahBayar > 0 || $sisa !== $total) {
                $redirect->with(
                    'gagal',
                    'Pembatalan ditolak: dokumen H/P ' . $d->kode . ' sudah punya pembayaran '
                    . 'sebesar Rp ' . number_format($sudahBayar, 0, ',', '.')
                    . '. Batalkan pembayarannya dulu dari menu Hutang/Piutang.'
                );
                return $redirect;
            }
        }

        $db = \Config\Database::connect();
        try {
            $db->transStart();

            $now = date('Y-m-d H:i:s');
            $catatan = 'Dibatalkan oleh #' . (int) session('ID_AKUN') . ' pada ' . $now . '. Alasan: ' . $alasan;

            foreach ($dokumen as $d) {
                $hpModel->update((int) $d->id, [
                    'deleted'    => 1,
                    'keterangan' => $catatan,
                    'updated_at' => $now,
                ]);
            }

            // input_by sengaja TIDAK disentuh: isinya sudah ditimpa terima()
            // dan tidak bisa dipulihkan ke pembuat aslinya. Pembatalan
            // merekam orangnya sendiri lewat batal_oleh.
            $this->MutasiStokModel->update($idmutasi, [
                'status'        => '0',
                'tanggal_terima'=> null,
                'batal_oleh'    => (int) session('ID_AKUN'),
                'batal_at'      => $now,
                'batal_alasan'  => $alasan,
                'updated_on'    => $now,
            ]);

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'MutasiStok: gagal batal terima #' . $idmutasi . ': ' . $e->getMessage());
            $redirect->with('gagal', 'Gagal membatalkan penerimaan mutasi. Silakan coba lagi.');
            return $redirect;
        }

        if ($db->transStatus() === false) {
            $redirect->with('gagal', 'Gagal membatalkan penerimaan mutasi.');
            return $redirect;
        }

        $redirect->with(
            'sukses',
            'Penerimaan mutasi dibatalkan. Dokumen Hutang/Piutang antar unit ikut dibatalkan.'
            . (count($dokumen) ? ' (' . count($dokumen) . ' dokumen)' : '')
        );
        return $redirect;
    }

    /**
     * Otorisasi pembatalan: jabatan 1 (Admin Root) saja.
     *
     * Sengaja lebih ketat dari terima(). Menerima menambah satu dokumen
     * keuangan; membatalkan menghapus dokumen itu. Kalau keduanya terbuka
     * untuk orang yang sama, siapa pun yang salah klik bisa menghapus
     * jejaknya sendiri tanpa ada yang lain tahu.
     */
    private function bolehBatalTerima(): bool
    {
        return in_array((int) session('ID_JABATAN'), [1], true);
    }

    public function insert()
    {

        $produkData = $this->request->getPost('produk');
        $kirim_idunit = $this->request->getPost('id_unit1_text');
        $terima_idunit = $this->request->getPost('id_unit2_text');

        $dataunitkirim = $this->UnitModel->getById($kirim_idunit);
        $dataunitterima = $this->UnitModel->getById($terima_idunit);
        $kirim_namaunit = $dataunitkirim->NAMA_UNIT;
        $terima_namaunit = $dataunitterima->NAMA_UNIT;


        if ($kirim_idunit == $terima_idunit) {
            session()->setFlashdata('gagal', 'Tidak dapat memilih unit yang sama');
            return redirect()->back();
        }


        date_default_timezone_set('Asia/Jakarta');

        $tanggal_kirim = $this->request->getPost('tanggal_kirim');
        $tanggal_terima = $this->request->getPost('tanggal_terima');

        $waktu_sekarang = date('H:i:s');

        $tanggal_kirim_datetime = $tanggal_kirim . ' ' . $waktu_sekarang;
        $tanggal_terima_datetime = $tanggal_terima . ' ' . $waktu_sekarang;

        $namainputer = $this->AuthModel->getById(session('ID_AKUN'));
        $namanya = $namainputer->NAMA_AKUN;




        $tanggal_kirim_ymd = date('ymd', strtotime($tanggal_kirim));


        // nomutasi
        $lastMutasi = $this->MutasiStokModel
            ->where('kirim_idunit', $kirim_idunit)
            ->like('DATE(tanggal_kirim)', $tanggal_kirim_ymd)
            ->orderBy('no_nota_mutasi', 'DESC')
            ->first();

        if ($lastMutasi) {
            $lastKode = substr($lastMutasi['no_nota_mutasi'], -3);
            $newKode = str_pad((int)$lastKode + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newKode = '001';
        }


        $no_nota_mutasi =  'MTS' . $kirim_idunit . $tanggal_kirim_ymd . $newKode;
        // nomutasi

        $data = array(
            'no_nota_mutasi' => $no_nota_mutasi,
            'tanggal_kirim' => $tanggal_kirim_datetime,
            'tanggal_terima' => $tanggal_terima_datetime,
            'kirim_idunit' => $kirim_idunit,
            'terima_idunit' => $terima_idunit,
            'status' => '0',
            'input_by' => session('ID_AKUN'),
            'created_on' => date('Y-m-d H:i:s'),
            'updated_on' => ''
        );

        foreach ($produkData as $produk) {
            $produkid = $produk['id'];
            $namaproduk = $produk['nama'];
            $datastokawal = $this->StokAwalModel->getByIdBarang($produkid);

            if (!$datastokawal || $datastokawal->satuan_terkecil == null) {
                session()->setFlashdata('gagal', 'Barang dengan ID ' . $namaproduk . ' belum memiliki data satuan di stok awal.');
                return redirect()->back();
            }
        }

        $result = $this->MutasiStokModel->insert_MutasiStok($data);
        $idMutasi = $this->MutasiStokModel->insertID();

        foreach ($produkData as $produk) {
            $datastokawal = $this->StokAwalModel->getByIdBarang($produk['id']);
            $databarang = $this->BarangModel->getById($produk['id']);
            $idbarang = $produk['id'];

            $satuan = $datastokawal->satuan_terkecil;

            $produkjumlahKirim = $produk['jumlah_kirim'];
            $produkjumlahTerima = $produk['jumlah_terima'];
            $produkharga_beli = $produk['harga_beli'] ?? 0;
            $produkharga_jual = $produk['harga_jual'] ?? 0;
            $produkharga_mutasi = $produk['harga_mutasi'] ?? 0;

            $datahpp = $this->HppBarangModel->getById($idbarang);
            $hpp = $datahpp->hpp ?? 0;



            $data2 = array(
                'tanggal_kirim' => $tanggal_kirim,
                'tanggal_terima' => $tanggal_terima,
                'jumlah_kirim' => $produkjumlahKirim,
                'jumlah_terima' => $produkjumlahTerima,
                'satuan' => $satuan,
                'hpp_barang' => $hpp,
                'barang_idbarang' => $idbarang,
                'kirim_idunit' => $kirim_idunit,
                'terima_idunit' => $terima_idunit,
                'mutasi_idmutasi' => $idMutasi,
                'harga_mutasi' => $produkharga_mutasi,
                'harga_beli' => $produkharga_beli,
                'harga_jual' => $produkharga_jual

            );
            $result2 = $this->DetailMutasiModel->insert_DetailMutasiStok($data2);
        }

        if ($result & $result2) {

            $notamutasi = array(
                'tanggal' => $tanggal_kirim,
                'pengirim' => $kirim_namaunit,
                'penerima' => $terima_namaunit,
                'data_produk' => $produkData,
                'namainputer' => $namanya,
                'kode_mutasi' => $no_nota_mutasi
            );

            $html = view('cetak/mutasi_stok', $notamutasi);

            $mpdf = new \Mpdf\Mpdf([
                'curlAllowUnsafeSslRequests' => true,
                'curlUserAgent' => 'Mozilla/5.0',
            ]);

            ob_end_clean();
            $mpdf->WriteHTML($html);
            $mpdf->Output('nota_mutasi.pdf', 'I');
            exit;



            session()->setFlashdata('sukses', 'Data Berhasil Di Simpan');
            return redirect()->to(base_url('/mutasi_stok'));
        }
    }

    public function cetak_notamutasi($idmutasi)
    {

        $datadetailmutasi = $this->DetailMutasiModel->getFullDetailMutasiByMutasiId($idmutasi);
        $datalengkapmutasi = $this->MutasiStokModel->getById($idmutasi);
        $tanggal_kirim = $datalengkapmutasi->tanggal_kirim;
        $no_nota_mutasi = $datalengkapmutasi->no_nota_mutasi;


        $dataunitkirim = $this->UnitModel->getById($datalengkapmutasi->kirim_idunit);
        $dataunitterima = $this->UnitModel->getById($datalengkapmutasi->terima_idunit);
        $kirim_namaunit = $dataunitkirim->NAMA_UNIT;
        $terima_namaunit = $dataunitterima->NAMA_UNIT;


        $namainputer = $this->AuthModel->getById($datalengkapmutasi->input_by);
        $namanya = $namainputer->NAMA_AKUN;

        $produkData = [];

        foreach ($datadetailmutasi as $detail) {
            $produkData[] = [
                'nama'   => $detail->nama_barang,
                'jumlah_kirim'  => $detail->jumlah_kirim,
                'jumlah_terima' => $detail->jumlah_terima,
                'harga_beli'    => $detail->harga_beli,
                'harga_mutasi'    => $detail->harga_mutasi ?? 0,
                'satuan'        => $detail->satuan ?? 'pcs',
                'harga_jual' => $detail->harga_jual
            ];
        }

        $notamutasi = array(
            'tanggal' => $tanggal_kirim,
            'pengirim' => $kirim_namaunit,
            'penerima' => $terima_namaunit,
            'data_produk' => $produkData,
            'namainputer' => $namanya,
            'kode_mutasi' => $no_nota_mutasi
        );

        $html = view('cetak/mutasi_stok', $notamutasi);

        $mpdf = new \Mpdf\Mpdf([
            'curlAllowUnsafeSslRequests' => true,
            'curlUserAgent' => 'Mozilla/5.0',
        ]);

        ob_end_clean();
        $mpdf->WriteHTML($html);
        $mpdf->Output('nota_mutasi.pdf', 'I');
        exit;
    }
}
