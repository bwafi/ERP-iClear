<?php

namespace App\Controllers;

use App\Models\ModelAkunKasBank;
use App\Models\ModelAlokasiSaldoKasBank;
use App\Models\ModelAuth;
use App\Models\ModelBank;
use App\Models\ModelDetailMutasi;
use App\Models\ModelHutangPiutang;
use App\Models\ModelNoAkun;
use App\Models\ModelPembayaranHutangPiutang;
use App\Models\ModelSaldoAwalKasBank;
use App\Models\ModelTransaksiKasBank;
use App\Models\ModelUnit;
use App\Libraries\ModeKasBank;
use App\Services\Finance\FinanceScopeService;
use App\Services\Finance\KasBankScopeService;

/**
 * Kas & Bank + Pembayaran Antar Unit.
 *
 * Authorization reuse FinanceScopeService (Config\Finance::financeInputRoles):
 * - role input (0,1,2,34 = lintas unit) boleh memilih unit & mengisi;
 * - lainnya terikat ke unit sendiri (baca saja).
 */
class KasBank extends BaseController
{
    protected $AkunModel;
    protected $AlokasiModel;
    protected $TransaksiModel;
    protected $SaldoAwalModel;
    protected $HPModel;
    protected $PembayaranModel;
    protected $DetailMutasiModel;
    protected $BankModel;
    protected $NoAkunModel;
    protected $AuthModel;
    protected $UnitModel;
    protected $scopeService;
    protected $AkunScope;
    protected $KasBankLib;

    public function __construct()
    {
        $this->AkunModel = new ModelAkunKasBank();
        $this->AlokasiModel = new ModelAlokasiSaldoKasBank();
        $this->TransaksiModel = new ModelTransaksiKasBank();
        $this->SaldoAwalModel = new ModelSaldoAwalKasBank();
        $this->HPModel = new ModelHutangPiutang();
        $this->PembayaranModel = new ModelPembayaranHutangPiutang();
        $this->DetailMutasiModel = new ModelDetailMutasi();
        $this->BankModel = new ModelBank();
        $this->NoAkunModel = new ModelNoAkun();
        $this->AuthModel = new ModelAuth();
        $this->UnitModel = new ModelUnit();
        $this->scopeService = new FinanceScopeService();
        $this->AkunScope = new KasBankScopeService($this->scopeService);
        $this->KasBankLib = new ModeKasBank();
    }

    private function canInput(): bool
    {
        return $this->scopeService->canInput();
    }

    /**
     * Siapa yang boleh MENCATAT transaksi kas &amp; bank. Selain lintas unit
     * (root/direktur/manager/admin center), admin cabang juga boleh mengisi
     * Transfer Internal dan Pembayaran Antar Unit — tapi hanya memakai
     * rekening unitnya sendiri (dibatasi guard akun Terbatas).
     */
    private function bisaTransaksi(): bool
    {
        return in_array((int) session('ID_JABATAN'), [0, 1, 2, 34, 35, 40, 41, 47], true);
    }

    /**
     * Unit hasil pilihan user, dibatasi USER SCOPE.
     *
     * null = KONSOLIDASI (user punya akses >1 unit dan tidak memilih). null
     * TIDAK berarti "semua rekening" — pemanggil WAJIB memfilter daftar
     * rekening dengan ACCOUNT SCOPE lewat akunListUntuk()/akunAktifUntuk().
     */
    private function unitTerpilih(): ?int
    {
        $unit = $this->request->getGet('unit_id') ?? $this->request->getPost('unit_id');
        return $this->scopeService->resolveSelectedUnitIdAtauKosolidasi($unit !== null ? (string) $unit : null);
    }

    /**
     * Apakah pengguna lintas unit (bisa memilih unit lain). Admin Center/Root/
     * Direktur/Manager = lintas; admin cabang/SPV = terikat unit sendiri.
     *
     * CATATAN: ini soal HAK INPUT, bukan soal rekening mana yang terlihat.
     * Visibility rekening ditentukan ACCOUNT SCOPE, bukan peran ini.
     */
    private function lintas(): bool
    {
        return $this->scopeService->scopeInfo()['isLintas'];
    }

    /**
     * @return int[] user scope
     */
    private function unitIdsUser(): array
    {
        return $this->AkunScope->userUnitIds();
    }

    /**
     * Rekening yang boleh dipakai user = irisan USER SCOPE & ACCOUNT SCOPE.
     *
     * Konsolidasi (null) = seluruh rekening yang account scope-nya beririsan
     * dengan user scope. Rekening non-shared unit lain & rekening shared yang
     * hanya dialokasikan ke unit lain TIDAK ikut.
     *
     * @param bool $aktifOnly              form transaksi: hanya akun aktif
     * @param bool $termasukBelumAlokasi  halaman master: sertakan rekening
     *                                    shared yang belum punya alokasi
     *                                    (supaya alokasi bisa dikonfigurasi)
     */
    private function akunListUntuk(?int $unit, bool $aktifOnly = false, bool $termasukBelumAlokasi = false): array
    {
        return $this->AkunScope->akunTerlihat(
            $this->unitIdsUser(),
            $unit,
            $aktifOnly,
            $termasukBelumAlokasi
        );
    }

    /**
     * Rekening AKTIF untuk form transaksi — selalu hasil irisan dua scope.
     */
    private function akunAktifUntuk(?int $unit): array
    {
        return $this->akunListUntuk($unit, true, false);
    }

    /**
     * Rekening yang boleh jadi SUMBER (akun asal / akun pengirim).
     *
     * Menyaring daftar aktif dengan canUseAsSource() sehingga:
     *   - rekening Finance/HO (IRA) TAMPIL hanya untuk ROOT / ADMIN CENTER;
     *   - rekening unit/shared mengikuti irisan user scope & account scope.
     */
    private function akunSumberUntuk(?int $unit): array
    {
        $role = (int) session('ID_JABATAN');

        return array_values(array_filter(
            $this->akunAktifUntuk($unit),
            fn ($a) => $this->AkunScope->canUseAsSource($a, $unit, $role)
        ));
    }

    /**
     * Rekening yang boleh jadi TUJUAN (akun tujuan / akun penerima).
     *
     * Menyaring dengan canUseAsDestination(). Rekening Finance/HO (IRA)
     * ikut untuk semua unit yang boleh bertransaksi — inilah yang membuat
     * "Unit 1 -> IRA" dan "Unit 2 -> IRA" sama-sama sah.
     */
    private function akunTujuanUntuk(?int $unit): array
    {
        $role = (int) session('ID_JABATAN');

        return array_values(array_filter(
            $this->akunAktifUntuk($unit),
            fn ($a) => $this->AkunScope->canUseAsDestination($a, $unit, $role)
        ));
    }

    /**
     * Boleh dikelola/diedit di halaman master? Rekening non-shared hanya bila
     * unit pemiliknya dalam user scope. Rekening shared boleh bila ada alokasi
     * ke unit dalam scope, ATAU belum punya alokasi sama sekali (kalau tidak,
     * tidak akan pernah bisa dikonfigurasi dari UI).
     */
    private function akunBolehDiKelola($akun): bool
    {
        // Rekening Finance/HO tidak dimiliki unit mana pun dan tidak punya
        // baris alokasi, sehingga cek unit di bawah ini selalu lolos. Supaya
        // itu tidak jadi celah (admin cabang mengedit rekening HO), pengelolaan
        // HO ikut memakai daftar role yang sudah dikonfigurasi.
        if ($this->AkunScope->isFinanceHo($akun)) {
            return KasBankScopeService::roleBolehFinanceHoSource((int) session('ID_JABATAN'));
        }

        if ((int) ($akun->is_shared ?? 0) !== 1) {
            return $this->AkunScope->userBolehUnit((int) ($akun->unit_id ?? 0));
        }

        $entitled = $this->AkunScope->entitledUnitIds((int) $akun->idakun_kas_bank);
        if (empty($entitled)) {
            return true;
        }

        return count(array_intersect($entitled, $this->unitIdsUser())) > 0;
    }

    private function pageData(): array
    {
        return [
            'akun'           => $this->AuthModel->getById(session('ID_AKUN')),
            'unit'           => $this->scopeService->resolveAllowedUnits(),
            'bisa_pilih_unit' => $this->canInput(),
        ];
    }

    private function gagal(string $pesan)
    {
        session()->setFlashdata('gagal', $pesan);
        return redirect()->back();
    }

    /**
     * Token anti double-submit: dibuat saat halaman form dirender, disimpan di
     * session, dipakai & dihapus saat form benar-benar dikirim. Submit kedua
     * (double-click) mendapat token yang sudah terpakai -> ditolak.
     */
    private function buatSubmitToken(): string
    {
        $token = bin2hex(random_bytes(16));
        session()->set('kb_submit_' . $token, time());
        return $token;
    }

    /**
     * Klaim (konsumsi) token submit. Return false bila token tidak ada / sudah
     * dipakai -> submit ganda harus ditolak.
     */
    private function klaimSubmitToken(string $token): bool
    {
        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }
        $key = 'kb_submit_' . $token;
        if (session()->get($key) === null) {
            return false;
        }
        session()->remove($key);
        return true;
    }

    /**
     * Dashboard Kas & Bank.
     *
     * TIGA ANGKA YANG HARUS TIDAK DICAMPUR:
     *   1. saldo FISIK rekening   = saldo awal + seluruh arus rekening
     *   2. HAK/ALOKASI unit       = alokasi saldo awal + arus unit tsb
     *   3. total KONSOLIDASI       = jumlah saldo FISIK atas rekening distinct
     *
     * Konsolidasi TIDAK boleh menjumlahkan hak unit per unit: rekening shared
     * Rp15jt yang dialokasikan 10jt/5jt akan terhitung 25jt kalau approach
     * lama dipakai. Konsolidasi memakai saldo fisik per rekening, satu kali.
     *
     * Rekening yang ditampilkan selalu hasil irisan USER SCOPE (unit mana yang
     * boleh diakses user) dan ACCOUNT SCOPE (unit mana yang punya hak atas
     * rekening) — lihat KasBankScopeService.
     *
     * @param int|null $unitTerpilih null = konsolidasi
     */
    public function index()
    {
        $unitTerpilih = $this->unitTerpilih();
        $tanggalAwal  = $this->request->getGet('tanggal_awal') ?: date('Y-m-01');
        $tanggalAkhir = $this->request->getGet('tanggal_akhir') ?: date('Y-m-d');
        $akunId       = (int)$this->request->getGet('akun_id');

        // Dashboard menampilkan rekening yang bisa dipakai (aktif saja) —
        // rekening nonaktif tidak bisa jadi sumber atau tujuan transaksi apa pun.
        $akun = $this->akunListUntuk($unitTerpilih, true, false);

        if ($akunId > 0) {
            $akun = array_values(array_filter(
                $akun,
                static fn ($a) => (int) $a->idakun_kas_bank === $akunId
            ));
        }

        $akunIds = array_map('intval', array_column($akun, 'idakun_kas_bank'));

        $saldoFisikPerAkun = [];
        $saldoUnitPerAkun  = [];
        $alokasiTotal      = [];
        $belumDialokasikan = [];
        $warningAlokasi    = [];
        $totalKas = 0;
        $totalBank = 0;
        $totalSemua = 0;
        $totalFisikKas = 0;
        $totalFisikBank = 0;
        $totalFisikSemua = 0;

        $konsolidasi = ($unitTerpilih === null || $unitTerpilih <= 0);

        foreach ($akun as $a) {
            $aid = (int)$a->idakun_kas_bank;
            $fisik = $this->TransaksiModel->getSaldoFisikAkun($aid);
            $saldoFisikPerAkun[$aid] = $fisik;
            $alokasiTotal[$aid] = $this->TransaksiModel->getTotalAlokasiUnit($aid);
            if ($alokasiTotal[$aid] > $fisik) {
                $warningAlokasi[] = $aid;
            }
            // Sisa saldo fisik yang belum menjadi hak unit manapun.
            $belumDialokasikan[$aid] = $fisik - $alokasiTotal[$aid];

            $totalFisikSemua += $fisik;
            if ($a->tipe === 'KAS') {
                $totalFisikKas += $fisik;
            } else {
                $totalFisikBank += $fisik;
            }

            if (! $konsolidasi) {
                // Tampilan per unit: hak unit (alokasi + arus unit).
                $unitSaldo = $this->TransaksiModel->getSaldoUnitAkun($aid, $unitTerpilih);
                $saldoUnitPerAkun[$aid] = $unitSaldo;
                $totalSemua += $unitSaldo;
                if ($a->tipe === 'KAS') {
                    $totalKas += $unitSaldo;
                } else {
                    $totalBank += $unitSaldo;
                }
            } else {
                // Konsolidasi: saldo FISIK per rekening, satu kali per rekening.
                $totalSemua += $fisik;
                if ($a->tipe === 'KAS') {
                    $totalKas += $fisik;
                } else {
                    $totalBank += $fisik;
                }
            }
        }

        $builder = db_connect()->table('transaksi_kas_bank')
            ->select('transaksi_kas_bank.jenis, SUM(transaksi_kas_bank.jumlah) as total')
            ->groupBy('transaksi_kas_bank.jenis');

        // Ringkasan arus WAJIB dibatasi rekening dalam scope, kalau tidak
        // transaksi rekening yang tidak terlihat pun ikut terhitung.
        if (empty($akunIds)) {
            $builder->where('1 = 0');
        } else {
            $builder->whereIn('transaksi_kas_bank.akun_kas_bank_id', $akunIds);
        }

        if (! $konsolidasi) {
            $builder->where('transaksi_kas_bank.unit_id', $unitTerpilih);
        }
        if ($tanggalAwal) {
            $builder->where('transaksi_kas_bank.tanggal >=', $tanggalAwal);
        }
        if ($tanggalAkhir) {
            $builder->where('transaksi_kas_bank.tanggal <=', $tanggalAkhir);
        }

        // Finance cut-off: Net Cash Flow & ringkasan pemasukan/pengeluaran yang
        // ditampilkan adalah arus kas OPERASIONAL pada/setelah cut-off. Baris
        // "kas awal" (penanda saldo dari backfill sistem lama) bukan transaksi;
        // transaksi sebelum cut-off adalah legacy dan tidak dihitung ulang.
        $cutoff = FinanceScopeService::cutoffDate();
        $builder->where('transaksi_kas_bank.tanggal >=', $cutoff);
        $builder->groupStart()
            ->where('transaksi_kas_bank.keterangan !=', 'kas awal')
            ->groupStart()
                ->where('transaksi_kas_bank.keterangan IS NOT NULL')
                ->where('transaksi_kas_bank.keterangan !=', '')
            ->groupEnd()
        ->groupEnd();

        $ringkasan = [];
        $netCashFlow = 0;
        foreach ($builder->get()->getResult() as $row) {
            $ringkasan[$row->jenis] = (int)$row->total;
            if ($row->jenis === ModeKasBank::JENIS_PEMASUKAN) {
                $netCashFlow += (int)$row->total;
            } elseif ($row->jenis === ModeKasBank::JENIS_PENGELUARAN) {
                $netCashFlow -= (int)$row->total;
            }
        }

        $data = array_merge($this->pageData(), [
            'unit_terpilih'        => $unitTerpilih,
            'konsolidasi'          => $konsolidasi,
            'akun_kas_bank'        => $akun,
            'akun_scope'           => $this->AkunScope->petaAccountScope($akun),
            'saldo_fisik_per_akun' => $saldoFisikPerAkun,
            'saldo_unit_per_akun'  => $saldoUnitPerAkun,
            'alokasi_total'        => $alokasiTotal,
            'belum_dialokasikan'   => $belumDialokasikan,
            'warning_alokasi'      => $warningAlokasi,
            'total_kas'            => $totalKas,
            'total_bank'           => $totalBank,
            'total_semua'          => $totalSemua,
            'total_fisik_kas'      => $totalFisikKas,
            'total_fisik_bank'     => $totalFisikBank,
            'total_fisik_semua'    => $totalFisikSemua,
            'ringkasan'            => $ringkasan,
            'net_cash_flow'        => $netCashFlow,
            'filter'               => [
                'tanggal_awal'  => $tanggalAwal,
                'tanggal_akhir' => $tanggalAkhir,
                'akun_id'       => $akunId,
            ],
            'body'                 => 'kas_bank/dashboard',
        ]);

        return view('template', $data);
    }

    /**
     * Master akun kas/bank + saldo awal + alokasi saldo awal per unit.
     *
     * Ini halaman KONFIGURASI, jadi menampilkan rekening shared yang belum
     * punya alokasi sama sekali — tanpa itu alokasi tidak akan pernah bisa
     * diisi dari UI. Rekening non-shared milik unit lain tetap tidak muncul.
     * Saldo yang ditampilkan adalah saldo FISIK (bukan penjumlahan hak unit).
     */
    public function akun()
    {
        $unitTerpilih = $this->unitTerpilih();

        $akun = $this->akunListUntuk($unitTerpilih, false, true);

        $fisikSaldo = [];
        $jenisRek   = [];
        foreach ($akun as $a) {
            $id = (int) $a->idakun_kas_bank;
            $fisikSaldo[$id] = $this->TransaksiModel->getSaldoFisikAkun($id);
            $jenisRek[$id]   = $this->AkunScope->accountKind($a);
        }

        $data = array_merge($this->pageData(), [
            'unit_terpilih'     => $unitTerpilih,
            'akun_kas_bank'     => $akun,
            'akun_scope'        => $this->AkunScope->petaAccountScope($akun),
            'akun_jenis'        => $jenisRek,
            'saldo_fisik_akun'  => $fisikSaldo,
            'bank'              => $this->BankModel->getBank(),
            'no_akun'           => $this->NoAkunModel->getAkun(),
            'saldo_awal'        => $this->SaldoAwalModel->findAll(),
            'alokasi'           => $this->AlokasiModel->indexByAkun(),
            'unit_list'         => $this->scopeService->resolveAllowedUnits(),
            'body'              => 'kas_bank/akun',
        ]);

        return view('template', $data);
    }

    public function saveAkun()
    {
        if (!$this->canInput()) {
            return $this->gagal('Anda tidak berhak menambah/mengubah akun kas & bank.');
        }
        $id     = (int)$this->request->getPost('idakun_kas_bank');
        $unitId = (int)$this->request->getPost('unit_id');
        $tipe   = $this->request->getPost('tipe');
        $nama   = trim($this->request->getPost('nama_akun'));
        $shared = $this->request->getPost('is_shared') === '1';

        if (!in_array($tipe, ['KAS', 'BANK'], true)) {
            return $this->gagal('Tipe akun tidak valid');
        }
        if ($nama === '') {
            return $this->gagal('Nama akun wajib diisi');
        }

        $bankId = $tipe === 'BANK' ? trim((string)$this->request->getPost('bank_idbank')) : null;
        if ($tipe === 'BANK' && empty($bankId)) {
            return $this->gagal('Akun BANK wajib memilih master bank');
        }

        // KAS adalah fisik per unit -> wajib ada unit. BANK adalah rekening
        // fisik (bisa lintas unit / shared -> unit boleh kosong).
        if ($tipe === 'KAS' && $unitId <= 0) {
            return $this->gagal('Akun KAS wajib diisi unit-nya');
        }
        if ($tipe === 'BANK' && $unitId <= 0) {
            $shared = true;
        }
        if ($tipe === 'KAS') {
            $shared = false;
        }

        // User scope: unit yang dicantumkan harus berada dalam scope user.
        // Menolak di sini mencegah user membuat rekening milik unit di luar
        // haknya (mis. admin cabang mendaftarkan rekening unit lain).
        if ($unitId > 0 && ! $this->AkunScope->userBolehUnit($unitId)) {
            return $this->gagal('Unit tersebut tidak berada dalam cakupan Anda.');
        }

        // Edit: rekening yang diubah harus dalam scope (account scope bila
        // shared, atau unit pemiliknya).
        if ($id > 0) {
            $existing = $this->AkunModel->find($id);
            if (! $existing) {
                return $this->gagal('Akun tidak ditemukan');
            }
            if ((int) ($existing->is_shared ?? 0) === 1) {
                if (! $this->akunBolehDiKelola($existing)) {
                    return $this->gagal('Rekening bersama ini tidak terkait dengan unit dalam cakupan Anda.');
                }
            } elseif (! $this->AkunScope->userBolehUnit((int) ($existing->unit_id ?? 0))) {
                return $this->gagal('Rekening ini milik unit di luar cakupan Anda.');
            }

            // Rekening Finance/HO adalah rekening arsitektur: bentuknya terkunci
            // (unit NULL + shared) supaya tetap dikenali sebagai HO. Admin boleh
            // mengubah nama/keterangan, TIDAK boleh mengubahnya menjadi rekening
            // unit atau KAS lewat form ini.
            if ($this->AkunScope->isFinanceHo($existing)) {
                if ($tipe !== 'BANK') {
                    return $this->gagal('Rekening Finance/HO tidak dapat diubah menjadi rekening KAS.');
                }
                if ($unitId > 0) {
                    return $this->gagal('Rekening Finance/HO "' . $existing->nama_akun
                        . '" bukan milik unit manapun dan tidak bisa diberi unit pemilik.');
                }
                $unitId = 0;
                $shared = true;
            }
        }

        $noAkunCoa = trim((string)$this->request->getPost('no_akun_coa'));
        $noAkunCoa = $noAkunCoa === '' ? null : $noAkunCoa;
        $status    = $this->request->getPost('status') === 'nonaktif' ? 'nonaktif' : 'aktif';

        if ($tipe === 'BANK') {
            // 1 rekening fisik = 1 akun BANK.
            $duplikat = $this->AkunModel->where('tipe', 'BANK')->where('bank_idbank', $bankId);
            if ($id > 0) {
                $duplikat->where('idakun_kas_bank !=', $id);
            }
            if ($duplikat->first()) {
                return $this->gagal('Rekening bank ini sudah terdaftar sebagai akun fisik (satu rekening = satu akun).');
            }
        } else {
            $duplikat = $this->AkunModel->where('unit_id', $unitId)->where('nama_akun', $nama);
            if ($id > 0) {
                $duplikat->where('idakun_kas_bank !=', $id);
            }
            if ($duplikat->first()) {
                return $this->gagal('Nama akun sudah dipakai di unit ini');
            }
        }

        $data = [
            'unit_id'     => $unitId > 0 ? $unitId : null,
            'tipe'        => $tipe,
            'nama_akun'   => $nama,
            'bank_idbank' => $bankId,
            'no_akun_coa' => $noAkunCoa,
            'status'      => $status,
            'is_shared'   => $shared ? 1 : 0,
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            $this->AkunModel->update($id, $data);
            session()->setFlashdata('sukses', 'Akun berhasil diperbarui');
        } else {
            $data['created_by'] = (int)session()->get('ID_AKUN');
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->AkunModel->insert($data);
            session()->setFlashdata('sukses', 'Akun berhasil ditambahkan');
        }

        return redirect()->to(base_url('kas_bank/akun'));
    }

    /**
     * Simpan alokasi saldo awal per unit pada satu rekening fisik.
     * Tidak mengubah saldo fisik; total alokasi tidak boleh melebihi
     * saldo fisik rekening.
     */
    public function saveAlokasiSaldo()
    {
        if (!$this->canInput()) {
            return $this->gagal('Anda tidak berhak mengatur alokasi saldo.');
        }
        $akunId = (int)$this->request->getPost('akun_kas_bank_id');
        $unitId = (int)$this->request->getPost('unit_id');
        $nominal = (int)preg_replace('/[^0-9]/', '', (string)$this->request->getPost('nominal'));
        $ket = trim((string)$this->request->getPost('keterangan'));

        if ($akunId <= 0 || $unitId <= 0) {
            return $this->gagal('Rekening dan unit wajib dipilih');
        }
        if ($nominal <= 0) {
            return $this->gagal('Nominal alokasi harus lebih dari 0');
        }

        $akun = $this->AkunModel->find($akunId);
        if (!$akun || $akun->tipe !== 'BANK' || $akun->status !== 'aktif') {
            return $this->gagal('Alokasi khusus untuk rekening BANK aktif');
        }
        if (! $this->akunBolehDiKelola($akun)) {
            return $this->gagal('Rekening ini tidak terkait dengan unit dalam cakupan Anda.');
        }

        // Rekening Finance/HO TIDAK memakai alokasi unit: bukan milik unit
        // mana pun dan tidak boleh dibuat "berpunya" unit. Guard di server —
        // disembunyikan dari form saja tidak cukup.
        if ($this->AkunScope->isFinanceHo($akun)) {
            return $this->gagal('Rekening Finance/HO "' . $akun->nama_akun
                . '" tidak memakai alokasi unit. Alokasi hanya untuk rekening Shared Antar Unit.');
        }

        // Unit tujuan alokasi harus dalam user scope — inilah yang menentukan
        // siapa yang berhak atas rekening shared, jadi tidak boleh berasal dari
        // unit di luar jangkauan user.
        if (! $this->AkunScope->userBolehUnit($unitId)) {
            return $this->gagal('Unit alokasi berada di luar cakupan Anda.');
        }

        $saldoFisik = $this->TransaksiModel->getSaldoFisikAkun($akunId);
        $sekarang = $this->AlokasiModel->sumByAkun($akunId);

        $existing = $this->AlokasiModel->getByAkunUnit($akunId, $unitId);
        $sebelum  = $existing ? (int)$existing->nominal : 0;

        if ($sekarang - $sebelum + $nominal > $saldoFisik) {
            return $this->gagal('Total alokasi unit melebihi saldo fisik rekening (' .
                number_format($saldoFisik) . ').');
        }

        $data = [
            'akun_kas_bank_id' => $akunId,
            'unit_id'          => $unitId,
            'nominal'          => $nominal,
            'keterangan'       => $ket,
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->AlokasiModel->update($existing->id, $data);
        } else {
            $data['input_by']   = (int)session()->get('ID_AKUN');
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->AlokasiModel->insert($data);
        }

        session()->setFlashdata('sukses', 'Alokasi saldo awal unit berhasil disimpan');
        return redirect()->to(base_url('kas_bank/akun'));
    }

    public function saveSaldoAwal()
    {
        if (!$this->canInput()) {
            return $this->gagal('Anda tidak berhak menyimpan saldo awal.');
        }
        $akunId  = (int)$this->request->getPost('akun_kas_bank_id');
        $saldo   = (int)preg_replace('/[^0-9]/', '', (string)$this->request->getPost('saldo'));
        $tanggal = $this->request->getPost('tanggal') ?: date('Y-m-d');
        $ket     = trim((string)$this->request->getPost('keterangan'));

        if ($akunId <= 0) {
            return $this->gagal('Akun wajib dipilih');
        }
        if ($saldo <= 0) {
            return $this->gagal('Saldo awal harus lebih dari 0');
        }

        $akun = $this->AkunModel->find($akunId);
        if (!$akun || $akun->status !== 'aktif') {
            return $this->gagal('Akun tidak ditemukan / tidak aktif');
        }
        if (! $this->akunBolehDiKelola($akun)) {
            return $this->gagal('Rekening ini tidak terkait dengan unit dalam cakupan Anda.');
        }

        $existing = $this->SaldoAwalModel->getByAkun($akunId);
        $data = [
            'akun_kas_bank_id' => $akunId,
            'tanggal'          => $tanggal,
            'saldo'            => $saldo,
            'keterangan'       => $ket,
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->SaldoAwalModel->update($existing->id, $data);
            session()->setFlashdata('sukses', 'Saldo awal berhasil diperbarui');
        } else {
            $data['input_by']   = (int)session()->get('ID_AKUN');
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->SaldoAwalModel->insert($data);
            session()->setFlashdata('sukses', 'Saldo awal berhasil disimpan');
        }

        return redirect()->to(base_url('kas_bank/akun'));
    }

    /**
     * Halaman transfer internal. Akun yang bisa dipilih = yang ada dalam
     * irisan USER SCOPE x ACCOUNT SCOPE.
     */
    public function transfer()
    {
        $unitTerpilih = $this->unitTerpilih();

        $data = array_merge($this->pageData(), [
            'unit_terpilih' => $unitTerpilih,
            // Daftar SUMBER dan TUJUAN sengaja dipisah: rekening Finance/HO
            // (IRA) boleh jadi tujuan dari unit mana pun, tapi hanya ROOT /
            // ADMIN CENTER yang boleh men takers docketnya.
            'akun_sumber'   => $this->akunSumberUntuk($unitTerpilih),
            'akun_tujuan'   => $this->akunTujuanUntuk($unitTerpilih),
            'akun_kas_bank' => $this->akunAktifUntuk($unitTerpilih),
            'can_transaksi' => $this->bisaTransaksi(),
            'transaksi'     => $this->transaksiTerlihat(ModeKasBank::JENIS_TRANSFER, $unitTerpilih),
            'submit_token'  => $this->buatSubmitToken(),
            'body'          => 'kas_bank/transfer',
        ]);

        return view('template', $data);
    }

    /**
     * Daftar transaksi kas/bank yang boleh dilihat: DIBATASI rekening dalam
     * scope. Tanpa ini user akan melihat mutasi rekening yang account
     * scope-nya di luar haknya.
     */
    private function transaksiTerlihat(string $jenis, ?int $unitTerpilih)
    {
        $akunIds = $this->AkunScope->akunIdsTerlihat(
            $this->unitIdsUser(),
            $unitTerpilih,
            true,
            false
        );

        if (empty($akunIds)) {
            return [];
        }

        return $this->TransaksiModel
            ->where('jenis', $jenis)
            ->groupStart()
                ->whereIn('akun_kas_bank_id', $akunIds)
                ->orGroupStart()
                    ->whereIn('akun_tujuan_id', $akunIds)
                ->groupEnd()
            ->groupEnd()
            ->orderBy('idtransaksi', 'DESC')
            ->limit(200)
            ->findAll();
    }

    public function saveTransfer()
    {
        if (!$this->bisaTransaksi()) {
            return $this->gagal('Anda tidak berhak melakukan transfer internal.');
        }
        $asalId    = (int)$this->request->getPost('akun_asal_id');
        $tujuanId  = (int)$this->request->getPost('akun_tujuan_id');
        $jumlah    = (int)preg_replace('/[^0-9]/', '', (string)$this->request->getPost('jumlah'));
        $tanggal   = $this->request->getPost('tanggal') ?: date('Y-m-d');
        $ket       = trim((string)$this->request->getPost('keterangan'));
        $token     = (string)$this->request->getPost('submit_token');

        // Unit transaksi di-stamp dari form. User non-lintas TIDAK boleh
        // memilih unit bebas: unitnya dipaksa ke unit sesinya, kalau tidak
        // form bisa meng-stamp leg ke unit yang di luar haknya.
        $unitId = (int)$this->request->getPost('unit_id');
        if ($unitId <= 0) {
            $unitId = (int)session()->get('ID_UNIT');
        }
        if ($unitId <= 0) {
            $unitId = (int)$this->unitTerpilih();
        }
        if (! $this->AkunScope->userBolehUnit($unitId)) {
            return $this->gagal('Unit transaksi berada di luar cakupan Anda.');
        }

        if (!$this->klaimSubmitToken($token)) {
            return $this->gagal('Form sudah dikirim atau tidak valid. Muat ulang halaman untuk mencoba lagi.');
        }
        if ($asalId <= 0 || $tujuanId <= 0) {
            return $this->gagal('Pilih akun asal dan tujuan');
        }
        if ($asalId === $tujuanId) {
            return $this->gagal('Asal dan tujuan tidak boleh rekening fisik yang sama. ' .
                'Jika dua unit berbagi rekening yang sama itu BUKAN transfer internal.');
        }
        if ($jumlah <= 0) {
            return $this->gagal('Jumlah harus lebih dari 0');
        }

        $asal   = $this->AkunModel->find($asalId);
        $tujuan = $this->AkunModel->find($tujuanId);
        if (!$asal || $asal->status !== 'aktif' || !$tujuan || $tujuan->status !== 'aktif') {
            return $this->gagal('Akun asal / tujuan tidak valid atau tidak aktif');
        }

        // Unit per leg: KAS terikat unit pemilik rekening; BANK (rekening
        // fisik) di-stamp unit transaksi dari form.
        $unitKeluar = $asal->tipe === 'KAS' ? (int)$asal->unit_id : $unitId;
        $unitMasuk  = $tujuan->tipe === 'KAS' ? (int)$tujuan->unit_id : $unitId;
        $role       = (int) session('ID_JABATAN');

        // GUARD BERARAH — "boleh transfer KE IRA" tidak berarti boleh memakai
        // IRA sebagai SUMBER.
        //   FINANCE_HO/IRA: sebagai tujuan = unit mana pun yang boleh
        //     bertransaksi (Unit 1 -> IRA dan Unit 2 -> IRA sama-sama sah);
        //     sebagai sumber = HANYA ROOT / ADMIN CENTER.
        //   UNIT   : hanya unit pemiliknya, dua arah.
        //   SHARED : hanya unit yang punya baris alokasi, dua arah.
        if (! $this->AkunScope->canUseAsSource($asal, $unitKeluar, $role)) {
            return $this->gagal('Akun asal tidak dapat dipakai. '
                . $this->alasanRekeningDitolak($asal, $unitKeluar, 'source', $role));
        }
        if (! $this->AkunScope->canUseAsDestination($tujuan, $unitMasuk, $role)) {
            return $this->gagal('Akun tujuan tidak dapat dipakai. '
                . $this->alasanRekeningDitolak($tujuan, $unitMasuk, 'destination', $role));
        }

        $transferRef = 'TRF-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -6));
        $bukti       = $this->uploadBukti();

        $db = \Config\Database::connect();
        try {
            $db->transStart();

            $duplikat = $this->TransaksiModel->where('transfer_ref', $transferRef)->first();
            if ($duplikat) {
                $db->transRollback();
                return $this->gagal('Kode transfer sudah terpakai, coba lagi');
            }

            $this->TransaksiModel->insert([
                'tanggal'          => $tanggal,
                'unit_id'          => $unitKeluar,
                'akun_kas_bank_id' => $asalId,
                'jenis'            => ModeKasBank::JENIS_TRANSFER,
                'arah'             => ModeKasBank::ARAH_KELUAR,
                'jumlah'           => $jumlah,
                'akun_tujuan_id'   => $tujuanId,
                'transfer_ref'     => $transferRef,
                'submission_key'   => $token,
                'keterangan'       => $ket,
                'bukti'            => $bukti,
                'input_by'         => (int)session()->get('ID_AKUN'),
                'created_at'       => date('Y-m-d H:i:s'),
            ]);

            $this->TransaksiModel->insert([
                'tanggal'          => $tanggal,
                'unit_id'          => $unitMasuk,
                'akun_kas_bank_id' => $tujuanId,
                'jenis'            => ModeKasBank::JENIS_TRANSFER,
                'arah'             => ModeKasBank::ARAH_MASUK,
                'jumlah'           => $jumlah,
                'transfer_ref'     => $transferRef,
                'keterangan'       => $ket,
                'input_by'         => (int)session()->get('ID_AKUN'),
                'created_at'       => date('Y-m-d H:i:s'),
            ]);

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'KasBank: gagal menyimpan transfer: ' . $e->getMessage());
            return $this->gagal('Gagal menyimpan transfer. Silakan coba lagi.');
        }

        if ($db->transStatus() === false) {
            return $this->gagal('Gagal menyimpan transfer');
        }

        session()->setFlashdata('sukses', 'Transfer internal berhasil (tidak memengaruhi Net Cash Flow)');
        return redirect()->to(base_url('kas_bank/transfer'));
    }

    public function reversalTransfer(int $id)
    {
        if (!$this->canInput()) {
            return $this->gagal('Anda tidak berhak membatalkan transfer.');
        }
        $row = $this->TransaksiModel->find($id);
        if (!$row || $row->jenis !== ModeKasBank::JENIS_TRANSFER) {
            return $this->gagal('Transaksi transfer tidak ditemukan');
        }

        // Reversal menghapus SELURUH pasangan transfer_ref, jadi kedua rekening
        // dan unit leg-nya harus berada dalam scope user.
        foreach ($this->TransaksiModel->getByTransferRef((string) $row->transfer_ref) as $leg) {
            if (! $this->AkunScope->userBolehUnit((int) $leg->unit_id)) {
                return $this->gagal('Transaksi transfer ini involve unit di luar cakupan Anda.');
            }
        }

        $db = \Config\Database::connect();
        $db->transStart();
        $this->TransaksiModel->where('transfer_ref', $row->transfer_ref)->delete();
        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->gagal('Gagal reversal transfer');
        }

        session()->setFlashdata('sukses', 'Transfer berhasil dibatalkan');
        return redirect()->to(base_url('kas_bank/transfer'));
    }

    /**
     * Halaman pembayaran antar unit + daftar hutang/piutang antar unit.
     */
    public function antar_unit()
    {
        $unitTerpilih = $this->unitTerpilih();

        $hp = $this->HPModel->getHutangPiutangUnit($unitTerpilih > 0 ? $unitTerpilih : null, 'hutang');
        $piutang = $this->HPModel->getHutangPiutangUnit($unitTerpilih > 0 ? $unitTerpilih : null, 'piutang');

        // Detail barang mutasi untuk H/P yang bersumber dari mutasi stok
        // (bukti H/P = list barang yang dikirim).
        $detailMutasiMap = [];
        foreach (array_merge($hp, $piutang) as $row) {
            if ($row->sumber_tipe !== 'mutasi_unit' || (int) $row->sumber_id <= 0) {
                continue;
            }
            $mid = (int) $row->sumber_id;
            if (isset($detailMutasiMap[$mid])) {
                continue;
            }
            $detail = $this->DetailMutasiModel->getFullDetailMutasiByMutasiId($mid);
            if (empty($detail)) {
                continue;
            }
            $total = 0;
            foreach ($detail as $d) {
                $d->nilai = $this->KasBankLib->nilaiDetailMutasi((array) $d);
                $total += (int) $d->nilai;
            }
            $detailMutasiMap[$mid] = [
                'no_nota' => $detail[0]->no_nota_mutasi ?? '',
                'tanggal' => $detail[0]->mutasi_tanggal_kirim ?? '',
                'total'   => $total,
                'items'   => $detail,
            ];
        }

        // Reversal atribusi: pembayaran antar unit yang MEMAKAI rekening fisik
        // yang sama (tidak membuat gerakan kas/bank).
        $atribusi = $this->PembayaranModel
            ->where('sumber', 'antar_unit')
            ->where('referensi_tipe', 'antar_unit_atribusi')
            ->orderBy('id', 'DESC')
            ->limit(100)
            ->findAll();

        $data = array_merge($this->pageData(), [
            'unit_terpilih'    => $unitTerpilih,
            'akun_kas_bank'    => $this->akunAktifUntuk($unitTerpilih),
            'can_transaksi'    => $this->bisaTransaksi(),
            'akun_pengirim'    => $this->akunSumberUntuk($unitTerpilih),
            'akun_penerima'    => $this->akunTujuanUntuk($unitTerpilih),
            'hp_hutang'        => $hp,
            'hp_piutang'       => $piutang,
            'detail_mutasi_map'=> $detailMutasiMap,
            'pembayaran'       => $this->transaksiTerlihat(ModeKasBank::JENIS_ANTAR_UNIT, $unitTerpilih),
            'histori'          => $this->PembayaranModel->findAll(),
            'histori_atribusi' => $atribusi,
            'submit_token'     => $this->buatSubmitToken(),
            'body'             => 'kas_bank/antar_unit',
        ]);

        return view('template', $data);
    }

    /**
     * Pesan error yang menyebut alasan penolakan rekening secara SPESIFIK
     * menurut jenis & arah, supaya user tahu harus memperbaiki apa.
     *
     * @param string $arah 'source' | 'destination'
     */
    private function alasanRekeningDitolak($akun, int $unitId, string $arah = 'source', ?int $role = null): string
    {
        $nama = (string) ($akun->nama_akun ?? 'rekening tersebut');
        $role = $role ?? (int) session('ID_JABATAN');
        $kind = $this->AkunScope->accountKind($akun);

        if ($kind === KasBankScopeService::KIND_FINANCE_HO) {
            if ($arah === 'source') {
                return 'Rekening Finance/HO "' . $nama . '" hanya boleh mengeluarkan dana oleh '
                    . 'Admin Root atau Admin Center. Jabatan Anda tidak berwenang menarik dana '
                    . 'dari rekening HO — Anda tetap boleh mentransfer DANA KE rekening ini.';
            }

            return 'Rekening Finance/HO "' . $nama . '" tidak dapat dipakai: unit ' . $unitId
                . ' berada di luar cakupan Anda.';
        }

        if ($arah === 'source') {
            return 'Rekening "' . $nama . '" tidak boleh menjadi SUMBER dana untuk unit ' . $unitId . '.';
        }

        if ($kind === KasBankScopeService::KIND_SHARED) {
            return 'Rekening "' . $nama . '" tidak dialokasikan ke unit ' . $unitId . '. '
                . 'Tambahkan alokasi unit tersebut di Master Akun Kas & Bank terlebih dahulu.';
        }

        return 'Rekening "' . $nama . '" hanya milik unit ' . (int) ($akun->unit_id ?? 0) . '.';
    }

    /**
     * Simpan pembayaran antar unit (satu DB transaction, atomic).
     *
     * Dua skenario rekening:
     * a) Pengirim & penerima pakai rekening FISIK berbeda -> 2 leg kas/bank.
     * b) Pengirim & penerima pakai rekening FISIK yang SAMA -> TIDAK membuat
     *    gerakan kas/bank; hanya menyelesaikan H/P + catatan pembayaran
     *    bertipe 'antar_unit_atribusi' (atribusi internal, ledger mencerminkan
     *    kenyataan: uang tidak berpindah rekening).
     */
    public function saveAntarUnit()
    {
        if (!$this->bisaTransaksi()) {
            return $this->gagal('Anda tidak berhak mencatat pembayaran antar unit.');
        }
        $tanggal     = $this->request->getPost('tanggal') ?: date('Y-m-d');
        $hpId        = (int)$this->request->getPost('hutang_piutang_id');
        $akunKirimId = (int)$this->request->getPost('akun_pengirim_id');
        $akunTerimaId = (int)$this->request->getPost('akun_penerima_id');
        $jumlah      = (int)preg_replace('/[^0-9]/', '', (string)$this->request->getPost('jumlah'));
        $ket         = trim((string)$this->request->getPost('keterangan'));
        $token       = (string)$this->request->getPost('submit_token');

        if (!$this->klaimSubmitToken($token)) {
            return $this->gagal('Form sudah dikirim atau tidak valid. Muat ulang halaman untuk mencoba lagi.');
        }
        if ($hpId <= 0 || $akunKirimId <= 0 || $akunTerimaId <= 0) {
            return $this->gagal('Data belum lengkap');
        }
        if ($jumlah <= 0) {
            return $this->gagal('Jumlah harus lebih dari 0');
        }

        $hp = $this->HPModel->find($hpId);
        if (!$hp || $hp->jenis !== 'hutang' || $hp->pihak_tipe !== 'unit' || $hp->deleted) {
            return $this->gagal('Hutang antar unit tidak ditemukan');
        }

        if ((int)$hp->sisa < $jumlah) {
            return $this->gagal('Jumlah melebihi sisa hutang');
        }

        $akunKirim  = $this->AkunModel->find($akunKirimId);
        $akunTerima = $this->AkunModel->find($akunTerimaId);
        if (!$akunKirim || $akunKirim->status !== 'aktif' || !$akunTerima || $akunTerima->status !== 'aktif') {
            return $this->gagal('Akun pengirim / penerima tidak valid');
        }

        // Pasangan piutang
        $piutang = $this->HPModel->getPasangan($hp);
        if (!$piutang) {
            return $this->gagal('Pasangan piutang tidak ditemukan');
        }

        // Rekening pengirim harus berarah-SUMBER atas unit yang punya hutang,
        // rekening penerima berarah-TUJUAN atas unit yang berpiutang.
        //   Finance/HO sebagai pengirim -> hanya ROOT / ADMIN CENTER.
        //   Finance/HO sebagai penerima   -> unit mana pun (sah).
        //   Rekening non-shared milik unit lain DITOLAK; rekening shared harus
        //   dialokasikan ke unit tsb lebih dulu.
        $role = (int) session('ID_JABATAN');

        if (!$this->AkunScope->canUseAsSource($akunKirim, (int)$hp->unit_id, $role)) {
            return $this->gagal('Akun pengirim ditolak. '
                . $this->alasanRekeningDitolak($akunKirim, (int)$hp->unit_id, 'source', $role));
        }
        if (!$this->AkunScope->canUseAsDestination($akunTerima, (int)$piutang->unit_id, $role)) {
            return $this->gagal('Akun penerima ditolak. '
                . $this->alasanRekeningDitolak($akunTerima, (int)$piutang->unit_id, 'destination', $role));
        }

        // User scope: unit yang punya hutang/piutang WAJIB dalam cakupan user.
        // Tanpa ini admin cabang bisa menyelesaikan hutang unit lain, dan leg
        // kasnya ter-stamp ke unit yang bukan haknya.
        if (!$this->AkunScope->userBolehUnit((int)$hp->unit_id)
            || !$this->AkunScope->userBolehUnit((int)$piutang->unit_id)) {
            return $this->gagal('Hutang antar unit ini involve unit di luar cakupan Anda.');
        }

        $bukti = $this->uploadBukti();

        $transferRef = 'BUT-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -6));

        $db = \Config\Database::connect();
        try {
            $db->transStart();
            $idKeluar = null;

            if ($akunKirimId === $akunTerimaId) {
                // ---------- SKENARIO (b): rekening fisik SAMA ----------
                // Tidak ada gerakan kas/bank; cukup selesaikan H/P. Tandai
                // pembayaran dengan referensi_tipe 'antar_unit_atribusi' dan
                // referensi_id = id hutang, agar bisa di-reversal tanpa
                // menyentuh saldo (yang memang tidak berubah).
                $bayarData = [
                    'tanggal_bayar'   => $tanggal,
                    'jumlah_bayar'    => $jumlah,
                    'bayar_tunai'     => $akunKirim->tipe === 'KAS' ? $jumlah : 0,
                    'bayar_bank'      => $akunKirim->tipe === 'BANK' ? $jumlah : 0,
                    'bank_idbank'     => $akunKirim->tipe === 'BANK' ? $akunKirim->bank_idbank : null,
                    'sumber'          => 'antar_unit',
                    'referensi_tipe'  => 'antar_unit_atribusi',
                    'referensi_id'    => (int)$hp->id,
                    'keterangan'      => 'Penyelesaian H/P antar unit (rekening fisik sama — tanpa gerakan kas) ' .
                        $transferRef . ($ket ? ' — ' . $ket : ''),
                    'input_by'        => (int)session()->get('ID_AKUN'),
                    'created_at'      => date('Y-m-d H:i:s'),
                ];

                $bayarData['hutang_piutang_id'] = (int)$hp->id;
                $this->PembayaranModel->insert($bayarData);
                $bayarData['hutang_piutang_id'] = (int)$piutang->id;
                $this->PembayaranModel->insert($bayarData);
            } else {
                // ---------- SKENARIO (a): rekening fisik BEDA ----------
                // Akun pengirim KAS -> bayar tunai; BANK -> bayar bank.
                $bayarTunai = $akunKirim->tipe === 'KAS' ? $jumlah : 0;
                $bayarBank  = $akunKirim->tipe === 'BANK' ? $jumlah : 0;
                $bankId     = $akunKirim->tipe === 'BANK' ? $akunKirim->bank_idbank : null;

                // Leg 1: KELUAR dari akun pengirim.
                // submission_key = token form (UNIQUE) -> anti double-submit.
                $this->TransaksiModel->insert([
                    'tanggal'          => $tanggal,
                    'unit_id'          => (int)$hp->unit_id,
                    'akun_kas_bank_id' => $akunKirimId,
                    'jenis'            => ModeKasBank::JENIS_ANTAR_UNIT,
                    'arah'             => ModeKasBank::ARAH_KELUAR,
                    'jumlah'           => $jumlah,
                    'akun_tujuan_id'   => $akunTerimaId,
                    'transfer_ref'     => $transferRef,
                    'submission_key'   => $token,
                    'keterangan'       => $ket,
                    'bukti'            => $bukti,
                    'input_by'         => (int)session()->get('ID_AKUN'),
                    'created_at'       => date('Y-m-d H:i:s'),
                ]);
                $idKeluar = (int)$this->TransaksiModel->insertID();

                // Leg 2: MASUK ke akun penerima.
                $this->TransaksiModel->insert([
                    'tanggal'          => $tanggal,
                    'unit_id'          => (int)$piutang->unit_id,
                    'akun_kas_bank_id' => $akunTerimaId,
                    'jenis'            => ModeKasBank::JENIS_ANTAR_UNIT,
                    'arah'             => ModeKasBank::ARAH_MASUK,
                    'jumlah'           => $jumlah,
                    'akun_tujuan_id'   => $akunKirimId,
                    'transfer_ref'     => $transferRef,
                    'sumber_tipe'      => 'antar_unit',
                    'sumber_id'        => $idKeluar,
                    'keterangan'       => $ket,
                    'bukti'            => $bukti,
                    'input_by'         => (int)session()->get('ID_AKUN'),
                    'created_at'       => date('Y-m-d H:i:s'),
                ]);

                // Catat pembayaran di registry existing (hutang + piutang pasangan).
                $bayarData = [
                    'tanggal_bayar'  => $tanggal,
                    'jumlah_bayar'   => $jumlah,
                    'bayar_tunai'    => $bayarTunai,
                    'bayar_bank'     => $bayarBank,
                    'bank_idbank'    => $bankId,
                    'sumber'         => 'antar_unit',
                    'referensi_tipe' => 'transaksi_kas_bank',
                    'referensi_id'   => $idKeluar,
                    'keterangan'     => 'Pembayaran antar unit ' . $transferRef . ($ket ? ' — ' . $ket : ''),
                    'input_by'       => (int)session()->get('ID_AKUN'),
                    'created_at'     => date('Y-m-d H:i:s'),
                ];

                $bayarData['hutang_piutang_id'] = (int)$hp->id;
                $this->PembayaranModel->insert($bayarData);
                $bayarData['hutang_piutang_id'] = (int)$piutang->id;
                $this->PembayaranModel->insert($bayarData);
            }

            // Kurangi sisa hutang & piutang pasangan secara atomik.
            $rH = $this->KasBankLib->terapkanPembayaranHP((int)$hp->id, $jumlah);
            $rP = $this->KasBankLib->terapkanPembayaranHP((int)$piutang->id, $jumlah);

            if ($rH['status'] !== 'ok' || $rP['status'] !== 'ok') {
                $db->transRollback();
                return $this->gagal('Gagal memperbarui sisa hutang/piutang');
            }

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'KasBank: gagal menyimpan pembayaran antar unit: ' . $e->getMessage());
            return $this->gagal('Gagal menyimpan pembayaran antar unit. Silakan coba lagi.');
        }

        if ($db->transStatus() === false) {
            return $this->gagal('Gagal menyimpan pembayaran antar unit');
        }

        session()->setFlashdata(
            'sukses',
            $akunKirimId === $akunTerimaId
                ? 'Pembayaran antar unit berhasil disimpan (rekening fisik sama — H/P diselesaikan tanpa gerakan kas)'
                : 'Pembayaran antar unit berhasil disimpan'
        );
        return redirect()->to(base_url('kas_bank/antar_unit'));
    }

    /**
     * Reversal pembayaran antar unit ATRIBUSI (rekening fisik sama, tanpa
     * gerakan kas/bank): kembalikan sisa H/P dan hapus catatan pembayaran.
     */
    public function reversalAtribusi(int $idHp)
    {
        if (!$this->canInput()) {
            return $this->gagal('Anda tidak berhak membatalkan pembayaran antar unit.');
        }

        $daftarBayar = $this->PembayaranModel
            ->where('sumber', 'antar_unit')
            ->where('referensi_tipe', 'antar_unit_atribusi')
            ->where('referensi_id', $idHp)
            ->findAll();
        if (!$daftarBayar) {
            return $this->gagal('Catatan pembayaran atribusi tidak ditemukan');
        }

        // Reversal atribusi mengembalikan sisa H/P -> unit H/P harus dalam scope.
        $hp = $this->HPModel->find($idHp);
        if (!$hp || ! $this->AkunScope->userBolehUnit((int) $hp->unit_id)) {
            return $this->gagal('Hutang antar unit ini involve unit di luar cakupan Anda.');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        foreach ($daftarBayar as $bayar) {
            $this->KasBankLib->restorePembayaranHP((int)$bayar->hutang_piutang_id, (int)$bayar->jumlah_bayar);
            $this->PembayaranModel->delete($bayar->id);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->gagal('Gagal reversal pembayaran antar unit atribusi');
        }

        session()->setFlashdata('sukses', 'Pembayaran antar unit atribusi berhasil dibatalkan (sisa H/P dikembalikan)');
        return redirect()->to(base_url('kas_bank/antar_unit'));
    }

    /**
     * Reversal pembayaran antar unit: kembalikan kas/bank + sisa H/P secara atomik.
     */
    public function reversalAntarUnit(int $id)
    {
        if (!$this->canInput()) {
            return $this->gagal('Anda tidak berhak membatalkan pembayaran antar unit.');
        }
        $row = $this->TransaksiModel->find($id);
        if (!$row || $row->jenis !== ModeKasBank::JENIS_ANTAR_UNIT || $row->arah !== ModeKasBank::ARAH_KELUAR) {
            return $this->gagal('Transaksi pembayaran antar unit tidak ditemukan');
        }

        // Reversal men-HAPUS saldo kedua rekening + mengembalikan sisa H/P
        // milik unit lawan, jadi seluruh leg harus dalam scope user.
        foreach ($this->TransaksiModel->getByTransferRef((string) $row->transfer_ref) as $leg) {
            if (! $this->AkunScope->userBolehUnit((int) $leg->unit_id)) {
                return $this->gagal('Transaksi ini involve unit di luar cakupan Anda.');
            }
        }

        $transferRef = $row->transfer_ref;
        $idKeluar    = (int)$row->idtransaksi;

        $db = \Config\Database::connect();
        $db->transStart();

        $daftarBayar = $this->PembayaranModel->getByReferensi('transaksi_kas_bank', $idKeluar);
        foreach ($daftarBayar as $bayar) {
            $this->KasBankLib->restorePembayaranHP((int)$bayar->hutang_piutang_id, (int)$bayar->jumlah_bayar);
            $this->PembayaranModel->delete($bayar->id);
        }

        $this->TransaksiModel->where('transfer_ref', $transferRef)->delete();

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->gagal('Gagal reversal pembayaran antar unit');
        }

        session()->setFlashdata('sukses', 'Pembayaran antar unit berhasil dibatalkan (kas/bank & sisa H/P dikembalikan)');
        return redirect()->to(base_url('kas_bank/antar_unit'));
    }

    /**
     * Upload bukti transfer ke public/uploads/bukti_antar_unit/.
     * Return nama file (null jika tidak ada upload).
     */
    private function uploadBukti(): ?string
    {
        $file = $this->request->getFile('bukti');
        if ($file === null) {
            return null;
        }

        if (!$file->isValid()) {
            return null;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'gif', 'webp'];
        $ext = strtolower($file->getClientExtension());
        if (!in_array($ext, $allowed, true)) {
            return null;
        }

        $dir = ROOTPATH . 'public/uploads/bukti_antar_unit';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $nama = 'BUT-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4))) . '.' . $ext;
        $file->move($dir, $nama);

        return 'uploads/bukti_antar_unit/' . $nama;
    }
}