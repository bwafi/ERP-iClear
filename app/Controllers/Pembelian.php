<?php

namespace App\Controllers;

use App\Models\ModelBarang;

use App\Models\ModelBarangUnit;
use App\Models\ModelKategori;
use App\Models\ModelNamaHandphone;
use App\Models\ModelPembelian;
use App\Models\ModelStokAwal;
use App\Models\ModelDetailPembelian;
use App\Models\ModelSubKategori;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Config\Database;
use App\Models\ModelSuplier;
use App\Models\ModelAuth;
use App\Models\ModelPelanggan;
use App\Models\ModelHppBarang;
use App\Models\ModelHppUnit;
use App\Models\ModelHargaJualUnit;
use App\Models\ModelJurnal;
use App\Models\ModelBank;
use App\Models\ModelPembayaranBank;
use App\Models\ModelUnit;
use App\Models\ModelKasKeluar;
use App\Services\Finance\BankRekeningValidator;
use App\Services\Pembelian\PembelianCalculator;
use App\Services\Pembelian\PembelianPaymentSplit;

class Pembelian extends BaseController

{

    protected $BarangModel;
    protected $BarangUnitModel;
    protected $KategoriModel;
    protected $SuplierModel;
    protected $PembelianModel;
    protected $StokAwalModel;
    protected $DetailPembelianModel;
    protected $AuthModel;
    protected $PelangganModel;
    protected $HppBarangModel;
    protected $HppUnitModel;
    protected $HargaJualUnitModel;
    protected $JurnalModel;
    protected $BankModel;
    protected $PembayaranBankModel;
    protected $NamaHandphoneModel;
    protected $SubKategoriModel;
    protected $UnitModel;
    protected $KasKeluarModel;

    /**
     * ID_JABATAN yang boleh memilih unit saat mencatat pembelian.
     *
     * 0 = Finance, 1 = Admin root, 34 = Manager. Tim toko (kasir, kepala toko,
     * frontliner, teknisi) SELALU memakai unit akunnya dan tidak boleh
     * menggantinya lewat request.
     */
    private const UNIT_PILIH_ROLES = [0, 1, 34];

    /** Kategori kas keluar untuk pembelian Lunas (kategori_kas: HPP). */
    private const KATEGORI_KAS_HPP = 7;

    /** Akun debit pembelian barang (no_akun: Persediaan Barang Dagang). */
    private const AKUN_PERSEDIAAN = '1030100000';

    public function __construct()
    {
        $this->BarangModel = new ModelBarang();
        $this->BarangUnitModel = new ModelBarangUnit();
        $this->KategoriModel = new ModelKategori();
        $this->SuplierModel = new ModelSuplier();
        $this->PembelianModel = new ModelPembelian();
        $this->StokAwalModel = new ModelStokAwal();
        $this->DetailPembelianModel = new ModelDetailPembelian();
        $this->AuthModel = new ModelAuth();
        $this->PelangganModel = new ModelPelanggan();
        $this->HppBarangModel = new ModelHppBarang();
        $this->HppUnitModel = new ModelHppUnit();
        $this->HargaJualUnitModel = new ModelHargaJualUnit();
        $this->JurnalModel = new ModelJurnal();
        $this->BankModel = new ModelBank();
        $this->PembayaranBankModel = new ModelPembayaranBank();
        $this->NamaHandphoneModel = new ModelNamaHandphone();
        $this->SubKategoriModel = new ModelSubKategori();
        $this->UnitModel = new ModelUnit();
        $this->KasKeluarModel = new ModelKasKeluar();
    }

    /**
     * Apakah role ini boleh mengganti unit pada form pembelian.
     */
    public static function bolehPilihUnitUntukRole(int $role): bool
    {
        return in_array($role, self::UNIT_PILIH_ROLES, true);
    }

    /**
     * Unit final untuk input /pembelian.
     *
     * Murni (tanpa database) supaya aturannya bisa diuji langsung. Request
     * dari role terbatas TIDAK dipercaya: nilainya diabaikan dan unit akun
     * yang dipakai. Role yang boleh memilih tetap dibatasi ke unit yang ada.
     *
     * @param int             $role         ID_JABATAN akun login
     * @param int             $accountUnit  ID_UNIT akun login
     * @param mixed           $requested    nilai unit dari GET/POST (tidak dipercaya)
     * @param array<int,int>  $validUnitIds daftar idunit yang boleh dipilih
     */
    public static function resolveUnitInput(int $role, int $accountUnit, $requested, array $validUnitIds): int
    {
        $akunUnit = (int) $accountUnit;

        if (! self::bolehPilihUnitUntukRole($role)) {
            return $akunUnit;
        }

        $req = (int) $requested;
        $valid = array_map('intval', $validUnitIds);

        if ($req > 0 && in_array($req, $valid, true)) {
            return $req;
        }

        return $akunUnit;
    }

    /**
     * Jatuh tempo wajib diisi manual oleh penginput; form tidak lagi memakai
     * nilai default hari ini. Nilai harus `YYYY-MM-DD` yang benar-benar ada.
     */
    public static function tanggalJatuhTempoValid(string $value): bool
    {
        return self::tanggalValid($value);
    }

    /**
     * Tanggal `YYYY-MM-DD` yang benar-benar ada di kalender.
     */
    public static function tanggalValid(string $value): bool
    {
        if (trim($value) === '') {
            return false;
        }

        $parsed = date_parse_from_format('Y-m-d', $value);

        return ($parsed['error_count'] ?? 1) === 0
            && ($parsed['warning_count'] ?? 1) === 0
            && checkdate(
                (int) ($parsed['month'] ?? 0),
                (int) ($parsed['day'] ?? 0),
                (int) ($parsed['year'] ?? 0)
            );
    }

    /**
     * @return int[] idunit operasional (unit.is_operational = 1)
     */
    private function daftarIdUnitOperasional(): array
    {
        return array_map(
            static fn ($u) => (int) $u->idunit,
            $this->UnitModel->getUnitOperasional()
        );
    }

    /**
     * Nama pihak pada nota pembelian: supplier kalau sumbernya supplier,
     * kalau tidak nama pelanggan yang diketik/dipilih.
     */
    private function namaPihakPembelian($idSuplier, $namaPelanggan, $namaBaru): string
    {
        $idSuplier = trim((string) $idSuplier);
        if ($idSuplier !== '') {
            $suplier = $this->SuplierModel->getById($idSuplier);
            if ($suplier && trim((string) ($suplier->nama_suplier ?? '')) !== '') {
                return (string) $suplier->nama_suplier;
            }
        }

        $namaPelanggan = trim((string) $namaPelanggan);
        if ($namaPelanggan !== '') {
            return $namaPelanggan;
        }

        $namaBaru = trim((string) $namaBaru);

        return $namaBaru !== '' ? $namaBaru : '-';
    }

    /**
     * Kategori Kas Keluar untuk pembelian Lunas. Dicari lewat nama supaya
     * tidak bergantung pada auto increment; id 7 adalah fallback data lokal.
     */
    private function idKategoriKasHpp($db): int
    {
        if ($db->tableExists('kategori_kas')) {
            $row = $db->table('kategori_kas')->where('kategori', 'HPP')->get()->getRow();
            if ($row && (int) ($row->idkategori_kas ?? 0) > 0) {
                return (int) $row->idkategori_kas;
            }
        }

        return self::KATEGORI_KAS_HPP;
    }

    /**
     * Unit final untuk halaman /pembelian.
     *
     * Role lintas unit hanya boleh memakai unit operasional: kalau unit akun
     * (atau unit dari request) bukan unit operasional — mis. akun Finance yang
     * bernaung di Head Office — dipakai unit operasional pertama. Tim toko tidak
     * berubah: selalu unit akunnya.
     *
     * @param array<int,int> $operationalUnitIds
     */
    public static function resolveUnitPembelian(int $role, int $accountUnit, $requested, array $operationalUnitIds): int
    {
        $unit = self::resolveUnitInput($role, $accountUnit, $requested, $operationalUnitIds);

        if (! self::bolehPilihUnitUntukRole($role) || $operationalUnitIds === []) {
            return $unit;
        }

        $valid = array_map('intval', $operationalUnitIds);

        return in_array($unit, $valid, true) ? $unit : (int) $valid[0];
    }

    public function index()
    {
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));
        $role = (int) ($akun->ID_JABATAN ?? session('ID_JABATAN'));
        $unitAkun = (int) ($akun->ID_UNIT ?? session('ID_UNIT'));
        $bolehPilihUnit = self::bolehPilihUnitUntukRole($role);
        // Head Office bukan unit operasional, jadi tidak ikut di pemilih unit.
        $unitList = $bolehPilihUnit ? $this->UnitModel->getUnitOperasional() : [];
        $unitId = self::resolveUnitPembelian(
            $role,
            $unitAkun,
            $this->request->getGet('unit'),
            $bolehPilihUnit ? array_map(static fn ($u) => (int) $u->idunit, $unitList) : []
        );

        // Stok, harga jual, dan HPP mengikuti unit yang dipilih, bukan unit sesi,
        // supaya role lintas unit tidak melihat angka unit lain.
        // Produk nonaktif tetap tampil: pembelian adalah pintu stok masuk, dan
        // produk yang dibeli akan diaktifkan kembali di unit ini.
        $produk = $this->BarangModel->getAllBarang($unitId, true);
        $hppUnit = $this->HppUnitModel->resolveMany(
            array_map(static fn ($p) => (int) $p->idbarang, $produk),
            $unitId
        );
        foreach ($produk as $p) {
            $p->hpp_unit = $hppUnit[(int) $p->idbarang] ?? (float) $p->harga_beli;
        }

        $unitTerpilih = $this->UnitModel->getById($unitId);
        $unitTerpilihNama = $unitTerpilih->NAMA_UNIT ?? ('Unit ' . $unitId);

        // Rekening bank mengikuti scope unit halaman, sama seperti Kas &
        // Keluar: rekening unit lain dan Finance/HO tidak boleh muncul.
        $data =  array(
            'akun' => $akun,
            'produk' => $produk,
            'kategori' => $this->KategoriModel->getKategori(),
            'suplier' => $this->SuplierModel->getSuplier(),
            'bank' => $this->BankModel->getBankDalamScopeUnit($unitId),
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'body'  => 'transaksi/pembelian',
            'nama_handphone' => $this->NamaHandphoneModel->getNamaHandphone(),
            'sub_kategori' => $this->SubKategoriModel->getSubKategori(),
            'can_manage_harga_jual' => Kartu_Stok::bolehKelolaProdukUnit((int) session('ID_JABATAN')),
            'boleh_pilih_unit' => $bolehPilihUnit,
            'unit_list' => $unitList,
            'unit_terpilih' => $unitId,
            'unit_terpilih_nama' => $unitTerpilihNama,
        );
        return view('template', $data);
    }

    function cleanRupiah($value)
    {

        $value = str_replace(['Rp', '.', ' '], '', $value);
        return (int) $value;
    }

    public function insert()
    {
        $produkData = $this->request->getPost('produk');

        date_default_timezone_set('Asia/Jakarta');

        $db = \Config\Database::connect();
        $gagal = function (string $pesan) use ($db) {
            $db->transRollback();
            session()->setFlashdata('gagal', $pesan);

            return redirect()->to(base_url('/pembelian'));
        };

        // Baris produk, diskon, PPN, dan total dihitung ulang di server.
        // Nominal dari POST hanya dipakai untuk membandingkan, bukan sumber.
        try {
            $kalkulasi = PembelianCalculator::normalisasi($produkData);
        } catch (\InvalidArgumentException $e) {
            return $gagal($e->getMessage());
        }

        $datauser = $this->AuthModel->getById(session('ID_AKUN'));
        if (! $datauser) {
            return $gagal('Akun pengguna tidak ditemukan.');
        }

        $role = (int) ($datauser->ID_JABATAN ?? session('ID_JABATAN'));
        $unitAkun = (int) ($datauser->ID_UNIT ?? session('ID_UNIT'));

        // Unit pembelian: role lintas unit memakai unit operasional dari form
        // (divalidasi ke master unit operasional), tim toko selalu memakai unit
        // akunnya walaupun POST berisi unit lain.
        $useridunit = self::resolveUnitPembelian(
            $role,
            $unitAkun,
            $this->request->getPost('unit_idunit'),
            self::bolehPilihUnitUntukRole($role) ? $this->daftarIdUnitOperasional() : []
        );
        if ($useridunit <= 0) {
            return $gagal('Unit pembelian tidak valid.');
        }

        $id_suplier_text = $this->request->getPost('id_suplier_text');
        $id_pelanggan = $this->request->getPost('id_pelanggan');
        $nota_file = $this->request->getFile('nota_file');

        // Tanggal masuk wajib tanggal sah dan tidak boleh masa depan. Hanya
        // unit 1 yang boleh backdate; unit lain terkunci ke hari ini. Aturan
        // ini sama dengan batas yang dipasang di form.
        $tanggalMasuk = trim((string) $this->request->getPost('tanggal_masuk'));
        $hariIni = date('Y-m-d');
        if (! self::tanggalValid($tanggalMasuk)) {
            return $gagal('Tanggal masuk wajib diisi dengan tanggal yang valid.');
        }
        if ($tanggalMasuk > $hariIni) {
            return $gagal('Tanggal masuk tidak boleh melebihi hari ini.');
        }
        if ((int) $useridunit !== 1 && $tanggalMasuk !== $hariIni) {
            return $gagal('Tanggal masuk unit ini harus hari ini.');
        }
        $tanggal_masuk_full = $tanggalMasuk . ' ' . date('H:i:s');

        // Jenis pembayaran hanya Tempo atau Lunas. Nilai dibaca dari POST,
        // jadi tetap divalidasi di server walaupun form sudah membatasi.
        $jenisPembayaran = strtolower(trim((string) $this->request->getPost('jenis_pembayaran')));
        $metodeBayar = strtolower(trim((string) $this->request->getPost('metode_bayar')));
        if (! in_array($jenisPembayaran, ['tempo', 'lunas'], true)) {
            return $gagal('Jenis pembayaran wajib dipilih: Tempo atau Lunas.');
        }
        if ($jenisPembayaran === 'lunas' && ! in_array($metodeBayar, ['tunai', 'transfer', 'tunai_transfer'], true)) {
            return $gagal('Metode pembayaran Lunas wajib dipilih: Tunai, Transfer, atau Tunai + Transfer.');
        }

        // Jatuh tempo hanya dipakai Tempo. Form tidak memberi nilai default
        // supaya penginput tidak tanpa sadar menyimpan tanggal hari ini dan
        // merusak aging hutang.
        $jatuhTempo = trim((string) $this->request->getPost('jatuh_tempo'));
        if ($jenisPembayaran === 'tempo' && ! self::tanggalJatuhTempoValid($jatuhTempo)) {
            return $gagal('Jatuh tempo wajib diisi dengan tanggal yang valid.');
        }
        if ($jenisPembayaran === 'lunas') {
            $jatuhTempo = $tanggalMasuk;
        }

        $total_harga = (int) $kalkulasi['total'];
        $total_diskon = (int) $kalkulasi['diskon'];
        $total_ppn = (int) $kalkulasi['ppn'];

        // Total dari POST tidak dipercaya. Bila dikirim, nilainya harus sama
        // dengan hasil hitung server supaya header, detail, jurnal, dan kas
        // tidak bisa dipisah.
        $totalServer = [
            'total-harga'  => $total_harga,
            'total-diskon' => $total_diskon,
            'total-ppn'    => $total_ppn,
        ];
        foreach ($totalServer as $field => $nilaiServer) {
            $mentah = $this->request->getPost($field);
            if ($mentah === null || trim((string) $mentah) === '') {
                continue;
            }

            $nilaiPost = PembelianCalculator::parseRupiah($mentah);
            if ($nilaiPost === null || $nilaiPost !== $nilaiServer) {
                return $gagal('Total pembelian tidak sesuai dengan rincian produk. Muat ulang halaman.');
            }
        }

        $bolehUbahHargaJual = Kartu_Stok::bolehKelolaProdukUnit((int) session('ID_JABATAN'));

        // Normalisasi pembayaran: Tempo tidak menerima uang sekarang; Lunas
        // boleh tunai, transfer, atau keduanya. Total komponen dihitung ulang
        // di server dan wajib tepat sama dengan total pembelian supaya sisa,
        // kas keluar, dan jurnal tidak bisa dipisah dari nominal yang dibayar.
        $bankTervalidasi = [];
        $bayar_tunai = 0;
        $totalBayarBank = 0;
        $idbankPembayaran = null;

        if ($jenisPembayaran === 'tempo') {
            $sisa = $total_harga;
        } else {
            $split = (new PembelianPaymentSplit())->normalize($this->request->getPost());
            if ($split['errors'] !== []) {
                return $gagal($split['errors'][0]);
            }

            if ($split['total'] !== $total_harga) {
                $selisih = $total_harga - $split['total'];

                return $gagal($selisih > 0
                    ? 'Pembayaran Lunas kurang Rp ' . number_format($selisih, 0, ',', '.') . '. Lunas wajib dibayar penuh.'
                    : 'Pembayaran Lunas melebihi total Rp ' . number_format(-$selisih, 0, ',', '.') . '.');
            }

            foreach ($split['banks'] as $baris) {
                $cekRekening = (new BankRekeningValidator())->validateUntukUnit($baris['id'], $useridunit, $role, false);
                if (! $cekRekening['ok']) {
                    return $gagal('Rekening pembayaran tidak valid: ' . $cekRekening['alasan']);
                }

                $bankTervalidasi[] = [
                    'id'     => (string) $cekRekening['idbank'],
                    'jumlah' => (int) $baris['jumlah'],
                ];
            }

            $bayar_tunai = (int) $split['cash'];
            foreach ($bankTervalidasi as $baris) {
                $totalBayarBank += $baris['jumlah'];
            }
            $sisa = 0;
            $idbankPembayaran = count($bankTervalidasi) === 1 ? $bankTervalidasi[0]['id'] : null;
        }

        // Kategori persediaan untuk jurnal. Produk divalidasi di luar
        // transaksi supaya kegagalan tidak meninggalkan header setengah jadi.
        $total_aksesoris = 0;
        $total_sparepart = 0;
        $total_hp_ppn = 0;
        $total_hp_non_ppn = 0;
        $total_hp_second = 0;
        $barangTerkait = [];

        foreach ($kalkulasi['baris'] as $baris) {
            $databarang = $this->BarangModel->getById($baris['id']);
            if (! $databarang) {
                return $gagal('Produk pembelian tidak ditemukan.');
            }
            $barangTerkait[(int) $baris['id']] = $databarang;

            if ($bolehUbahHargaJual && ($baris['harga_jual'] <= 0 || $baris['harga_jual'] > 9999999999999)) {
                return $gagal('Harga jual tidak valid.');
            }

            if ((int) $databarang->idkategori === 1 && (int) $databarang->status_barang === 1) {
                $total_hp_second += $baris['dasar_persediaan'];
            } elseif ((int) $databarang->idkategori === 1 && (int) $databarang->status_ppn === 0) {
                $total_hp_non_ppn += $baris['dasar_persediaan'];
            } elseif ((int) $databarang->idkategori === 1 && (int) $databarang->status_ppn === 1) {
                $total_hp_ppn += $baris['dasar_persediaan'];
            } elseif ((int) $databarang->idkategori === 3) {
                $total_sparepart += $baris['dasar_persediaan'];
            } else {
                $total_aksesoris += $baris['dasar_persediaan'];
            }
        }

        $total_bayar = $bayar_tunai + $totalBayarBank;
        $status = $sisa === 0 ? 'Lunas' : 'Belum Lunas';

        $kodePembayaran = $jenisPembayaran === 'lunas'
            ? $this->buatKodePembayaran($db, (int) $useridunit)
            : null;

        //invoice otomatis
        $no_nota = $this->buatNoNota((int) $useridunit, $tanggalMasuk);

        // Rencana jurnal. Setiap template harus seimbang sendiri: jurnal
        // persediaan/PPN mengkredit Utang Usaha, jurnal pembayaran mendebit
        // Utang Usaha dan mengkredit kas/bank. Dengan begitu grup per
        // referensi selalu seimbang dan Tempo menyisakan utang di 2010100000.
        $rencanaJurnal = [];
        $tambahJurnal = static function (string $template, int $nilai, string $keterangan, string $ref) use (&$rencanaJurnal) {
            if ($nilai <= 0) {
                return;
            }
            $rencanaJurnal[] = [
                'template'   => $template,
                'nilai'      => $nilai,
                'keterangan' => $keterangan,
                'ref'        => $ref,
            ];
        };

        $tambahJurnal('pembelian_ppn_lunas', $total_ppn, 'Pembelian PPN: No.Nota ' . $no_nota, 'pembelian');
        $tambahJurnal('pembelian_aksesoris', $total_aksesoris, 'Pembelian Aksesoris: No.Nota ' . $no_nota, 'pembelian');
        $tambahJurnal('pembelian_sparepart', $total_sparepart, 'Pembelian Sparepart: No.Nota ' . $no_nota, 'pembelian');
        $tambahJurnal('pembelian_lunas_hp_baru_ppn', $total_hp_ppn, 'Pembelian HP PPN: No.Nota ' . $no_nota, 'pembelian');
        $tambahJurnal('pembelian_lunas_hp_baru', $total_hp_non_ppn, 'Pembelian HP NON PPN: No.Nota ' . $no_nota, 'pembelian');
        $tambahJurnal('pembelian_lunas_hp_second', $total_hp_second, 'Pembelian HP Second : No.Nota ' . $no_nota, 'pembelian');

        if ($jenisPembayaran === 'lunas') {
            if ($bayar_tunai > 0) {
                $tambahJurnal('pembelian_lunas_tunai', $bayar_tunai, 'Pembayaran Tunai - Pembelian', 'pembayaran');
            }
            foreach ($bankTervalidasi as $baris) {
                // Template per rekening dipertahankan untuk kompatibilitas;
                // rekening baru memakai template bank generik.
                $templateBank = 'pembelian_lunas_bank_' . $baris['id'];
                if ($this->JurnalModel->templateRows($templateBank) === []) {
                    $templateBank = 'pembelian_lunas_bank';
                }
                $tambahJurnal($templateBank, (int) $baris['jumlah'], 'Pembayaran Bank - Pembelian', 'pembayaran');
            }
        }

        // Template hilang atau tidak seimbang harus menolak pembelian sebelum
        // transaksi dibuka. Tanpa ini, jurnal bisa tidak tertulis sama sekali.
        foreach ($rencanaJurnal as $rencana) {
            $validasi = $this->JurnalModel->validasiTemplate($rencana['template'], [$rencana['nilai']]);
            if (! $validasi['ok']) {
                return $gagal('Jurnal pembelian tidak dapat disimpan: ' . $validasi['alasan']);
            }
        }

        $db->transStart();

        $foto_nota_name = null;
        if ($nota_file && $nota_file->isValid() && ! $nota_file->hasMoved()) {
            $foto_nota_name = $nota_file->getRandomName();
            if (! $nota_file->move(ROOTPATH . 'public/foto_nota', $foto_nota_name)) {
                return $gagal('Foto nota gagal diunggah.');
            }
        }
        $input_by = session('ID_AKUN');

        $nama = $this->request->getPost('nama');
        $alamat = $this->request->getPost('alamat');
        $nik = $this->request->getPost('nik');
        $nomor = $this->request->getPost('nomor');

        if (empty($id_pelanggan)) {
            // Pastikan ada data yang diinput sebelum insert
            if (! empty($nama) || ! empty($alamat) || ! empty($nik) || ! empty($nomor)) {
                $datapelanggan = $this->PelangganModel->getByNomor($nomor);

                if (empty($datapelanggan)) {
                    $resultp = $this->PelangganModel->insert_Pelanggan([
                        'nama' => $nama,
                        'alamat' => $alamat,
                        'nik' => $nik,
                        'no_hp' => $nomor,
                        'deleted' => 0,
                    ]);
                    if (! $resultp) {
                        return $gagal('Pelanggan baru gagal disimpan.');
                    }
                    $id_pelanggan = $this->PelangganModel->insertID();
                } else {
                    $id_pelanggan = $datapelanggan->id_pelanggan;
                }
            }
        }

        // Frontliner selalu akun yang login; nilai POST tidak dipercaya.
        $frontliner = (int) $input_by;

        $data = [
            'suplier_id_suplier' => $id_suplier_text,
            'no_nota_supplier' => $no_nota,
            'foto_nota' => $foto_nota_name,
            'tanggal_masuk' => $tanggal_masuk_full,
            'sisa' => $sisa,
            'status' => $status,
            'total_transaksi' => $total_harga,
            'total_diskon' => $total_diskon,
            'total_ppn' => $total_ppn,
            'total_bayar' => $total_bayar,
            'frontliner' => $frontliner,
            'bayar_tunai' => $bayar_tunai,
            'bayar_bank' => $totalBayarBank,
            // Kolom ini menyimpan rekening pembayaran, bukan kode pembayaran.
            'bank_idbank' => $idbankPembayaran,
            'jatuh_tempo' => $jatuhTempo,
            'bayar' => $total_bayar,
            'unit_idunit' => $useridunit,
            'pelanggan_id_pelanggan' => $id_pelanggan ?? null,
            'input_by' => $input_by,
        ];

        $idPembelian = $this->PembelianModel->insert_Pembelian($data);
        if ($idPembelian === false || (int) $idPembelian <= 0) {
            return $gagal('Data pembelian gagal disimpan.');
        }
        $idPembelian = (int) $idPembelian;

        if ($jenisPembayaran === 'lunas') {
            foreach ($bankTervalidasi as $baris) {
                $pembayaranTersimpan = $this->PembayaranBankModel->insertPembayaranBank([
                    'kode_pembayaran' => $kodePembayaran,
                    'bank_idbank' => $baris['id'],
                    'jumlah' => $baris['jumlah'],
                    'tabel_referensi' => 'pembelian',
                    'id_referensi' => $idPembelian,
                ]);
                if (! $pembayaranTersimpan) {
                    return $gagal('Pembayaran bank gagal disimpan.');
                }
            }
        }

        foreach ($kalkulasi['baris'] as $baris) {
            $produkid = (int) $baris['id'];
            $datastokawal = $this->StokAwalModel->getByIdBarang($produkid);
            $datahpp = $this->HppBarangModel->getById($produkid);
            $hitung_hpp = $datahpp->hpp ?? 0;
            $satuan_beli = $datastokawal ? $datastokawal->satuan_terkecil : 'pcs';

            $data2 = [
                'no_batch' => $no_nota,
                'tanggal' => $tanggal_masuk_full,
                'jumlah' => $baris['jumlah'],
                'hrg_beli' => $baris['harga_beli'],
                'diskon' => $baris['diskon'],
                'ppn' => $baris['ppn'],
                'hitung_hpp' => $hitung_hpp,
                'total_harga' => $baris['total'],
                'satuan_beli' => $satuan_beli,
                'barang_idbarang' => $produkid,
                'biaya_tambahan' => $baris['biaya_tambahan'],
                'keterangan_tambahan' => $baris['keterangan'],
                'unit_idunit' => $useridunit,
                'pembelian_idpembelian' => $idPembelian,
            ];

            if (! $this->DetailPembelianModel->insert_detail($data2)) {
                return $gagal('Detail pembelian gagal disimpan.');
            }

            // Stok masuk lewat pembelian mengaktifkan kembali produk yang
            // sebelumnya nonaktif di unit ini (pola sama dengan input stok awal).
            $this->BarangUnitModel->aktifkanJikaNonaktif($produkid, (int) $useridunit, (int) $input_by);

            if (! $this->HppUnitModel->syncFromPembelian(
                $produkid,
                (int) $useridunit,
                (float) $baris['harga_beli'],
                (int) $baris['jumlah'],
                (float) $baris['diskon'],
                (float) $baris['biaya_tambahan'],
                (int) $input_by
            )) {
                return $gagal('HPP pembelian tidak valid.');
            }

            if ($bolehUbahHargaJual) {
                $databarang = $barangTerkait[$produkid];
                $hargaTersimpan = (float) $baris['harga_jual'] === (float) $databarang->harga
                    ? $this->HargaJualUnitModel->clear($produkid, (int) $useridunit)
                    : $this->HargaJualUnitModel->setHarga(
                        $produkid,
                        (int) $useridunit,
                        (float) $baris['harga_jual'],
                        (int) $input_by
                    );

                if (! $hargaTersimpan) {
                    return $gagal('Harga jual gagal disimpan.');
                }
            }
        }

        // Pembelian Lunas langsung dicatat sebagai arus kas keluar supaya
        // terlihat di menu Kas Keluar, Tutup Kasir, dan saldo Kas & Bank.
        // Baris ini sengaja TIDAK membuat jurnal kedua: jurnal pembelian di
        // bawah adalah catatan akuntansinya. no_akun berisi sisi debit
        // persediaan; lawan kas/bank-nya dibaca dari idbank.
        if ($jenisPembayaran === 'lunas' && (int) $total_bayar > 0) {
            $penerimaKasKeluar = $this->namaPihakPembelian(
                $id_suplier_text,
                $this->request->getPost('pelanggan'),
                $nama
            );

            // Satu baris per komponen pembayaran: tunai memakai idbank NULL,
            // tiap rekening bank memakai idbank-nya sendiri.
            $komponenKas = [];
            if ($bayar_tunai > 0) {
                $komponenKas[] = ['jumlah' => $bayar_tunai, 'idbank' => null];
            }
            foreach ($bankTervalidasi as $baris) {
                $komponenKas[] = ['jumlah' => $baris['jumlah'], 'idbank' => $baris['id']];
            }

            $kategoriKasHpp = $this->idKategoriKasHpp($db);
            foreach ($komponenKas as $komponen) {
                $kasKeluarTersimpan = $this->KasKeluarModel->insert_KasKeluar([
                    'tanggal' => $tanggalMasuk,
                    'kategori_idkategori' => $kategoriKasHpp,
                    'deskripsi' => 'Pembelian ' . $no_nota . ' - ' . $penerimaKasKeluar,
                    'jumlah' => (int) $komponen['jumlah'],
                    'penerima' => $penerimaKasKeluar,
                    'idunit' => $useridunit,
                    'jenis' => 'debet',
                    'idbank' => $komponen['idbank'],
                    'created_on' => date('Y-m-d H:i:s'),
                    'no_akun' => self::AKUN_PERSEDIAAN,
                ]);

                if (! $kasKeluarTersimpan) {
                    return $gagal('Kas keluar pembelian gagal disimpan.');
                }
            }
        }

        // insert jurnal pembelian
        foreach ($rencanaJurnal as $rencana) {
            $idReferensi = $rencana['ref'] === 'pembelian' ? $idPembelian : $kodePembayaran;
            $tabelReferensi = $rencana['ref'] === 'pembelian' ? 'pembelian' : 'pembayaran_bank';

            if (! $this->JurnalModel->insertJurnal(
                $tanggal_masuk_full,
                $rencana['template'],
                [$rencana['nilai']],
                $rencana['keterangan'],
                $idReferensi,
                $tabelReferensi,
                (int) $useridunit
            )) {
                return $gagal('Jurnal pembelian gagal disimpan.');
            }
        }

        // Verifikasi pasca-insert: jurnal per grup referensi harus ada dan
        // seimbang. Kegagalan di sini membatalkan seluruh pembelian.
        $grupReferensi = [['pembelian', $idPembelian]];
        if ($jenisPembayaran === 'lunas' && $kodePembayaran !== null) {
            $grupReferensi[] = ['pembayaran_bank', $kodePembayaran];
        }
        foreach ($grupReferensi as [$tabelReferensi, $idReferensi]) {
            $ringkas = $this->JurnalModel->ringkasByReferensi($tabelReferensi, $idReferensi);
            if ($ringkas['jumlah'] === 0 || ! $ringkas['seimbang']) {
                return $gagal('Jurnal pembelian tidak seimbang. Pembelian dibatalkan.');
            }
        }

        // Sinkronkan baris projection hutang supplier (best-effort, tidak
        // menggagalkan pembelian bila modul hutang piutang bermasalah).
        try {
            (new \App\Services\Finance\HutangPiutangService())->syncFromPembelian($idPembelian);
        } catch (\Throwable $e) {
            log_message('error', 'syncFromPembelian #' . $idPembelian . ' gagal: ' . $e->getMessage());
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            $db->transRollback();
            session()->setFlashdata('gagal', 'Data Gagal Di Simpan');

            return redirect()->to(base_url('/pembelian'));
        }

        $db->transCommit();
        session()->setFlashdata('sukses', 'Data Berhasil Di Simpan');

        return redirect()->to(base_url('/pembelian'));
    }

    /**
     * Nomor nota unik per unit per hari: PCR{unit}{Ymd}{seq4}.
     */
    private function buatNoNota(int $unitId, string $tanggal): string
    {
        $ymd = str_replace('-', '', $tanggal);
        $lastNota = $this->PembelianModel
            ->select('no_nota_supplier')
            ->where('DATE(tanggal_masuk)', $tanggal)
            ->where('unit_idunit', $unitId)
            ->orderBy('no_nota_supplier', 'DESC')
            ->first();

        if ($lastNota) {
            // Ambil 4 digit terakhir (urutan)
            $lastNumber = (int) substr($lastNota->no_nota_supplier, -4);
            $urutan = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $urutan = '0001';
        }

        return 'PCR' . $unitId . $ymd . $urutan;
    }

    /**
     * Kode pembayaran unik per transaksi. Dicek ke tabel pembayaran_bank
     * supaya dua pembelian pada detik yang sama tidak memakai kode yang sama.
     */
    private function buatKodePembayaran($db, int $unitId): string
    {
        $prefix = 'PBL' . date('Ymd') . $unitId;

        for ($i = 0; $i < 20; $i++) {
            $kode = $prefix . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            if ($db->table('pembayaran_bank')->where('kode_pembayaran', $kode)->countAllResults() === 0) {
                return $kode;
            }
        }

        return $prefix . random_int(10000, 99999);
    }

    public function insert_produk()
    {


        $harga      = str_replace('.', '', $this->request->getPost('harga'));
        $harga_beli      = str_replace('.', '', $this->request->getPost('harga_beli'));
        $input = $this->request->getPost('input_by');
        $stok_minimum = $this->request->getPost('stok_minimum');

        $kategori = $this->request->getPost('kategori');
        $data_kategori = $this->KategoriModel->getByName($kategori);

        $idkategori = $data_kategori->id;
        $kode_kategori = $data_kategori->idkategori;

        $lastBarang = $this->BarangModel->getLastBarangByKategori($idkategori, $kode_kategori);

        $lastNumber = $lastBarang->max_kode ?? 0;
        $newNumber = $lastNumber + 1;
        
        $formattedNumber = str_pad($newNumber, 2, '0', STR_PAD_LEFT);
        
        $kode_barang = $kode_kategori . $formattedNumber;

        $formattedNumber = str_pad($newNumber, 2, '0', STR_PAD_LEFT);

        $kode_barang = $kode_kategori . $formattedNumber;
        $status_ppn = $this->request->getPost('status_ppn');

        $nama_barang = "";
        $id_nama_barang = 0;
        if ($kategori == "Handphone") {
            $nama_barang = $this->request->getPost('nama_barang');
            // $datanamahandphone =  $this->NamaHandphoneModel->getNamaHandphoneById($id_nama_barang);
            // $nama_barang = $datanamahandphone->nama;
        } else {
            $nama_barang = $this->request->getPost('nama_barang');
        }


        $data = array(
            'kode_barang' => $kode_barang,
            'nama_barang' => $nama_barang,
            'harga' => $harga,
            'harga_beli' => $harga_beli,
            'input' => $input,
            'stok_minimum' => $stok_minimum,
            'idkategori' => $idkategori,
            'id_sub_kategori' => $this->request->getPost('subkategori'),
            'imei' => $this->request->getPost('imei'),
            'warna' => $this->request->getPost('warna'),
            'status_barang' => $this->request->getPost('kondisi'),
            'jenis_hp' => $this->request->getPost('type'),
            'internal' => $this->request->getPost('size'),
            'status' => "1",
            'status_ppn' => $status_ppn,
            'nama_barang_id' => $id_nama_barang,
            'deleted' => '0'

        );

        $result = $this->BarangModel->insert_Barang($data);
        if ($result) {
            session()->setFlashdata('sukses', 'Data Berhasil Disimpan');
            return redirect()->to(base_url('/pembelian'));
        }
    }

    function sanitizeCurrency($value)
    {

        $cleaned = str_replace(['Rp', '.', ' '], '', $value);
        return (float) $cleaned;
    }
}
