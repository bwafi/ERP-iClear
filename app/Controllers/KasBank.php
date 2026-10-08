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
use CodeIgniter\HTTP\ResponseInterface;
use App\Services\Finance\FinanceScopeService;
use App\Services\Finance\KasBankScopeService;
use App\Services\Finance\KasBankCutoffService;
use App\Services\Finance\KasBankSetorTarikService;
use App\Services\Finance\KasBankSourceMovement;
use App\Services\Finance\TutupKasirSourceDefinition;

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
    protected $SetorTarikLib;
    /** @var \CodeIgniter\Database\Connection */
    protected $db;
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
        $this->SetorTarikLib = new KasBankSetorTarikService();
        $this->db = \Config\Database::connect();
    }

    private function canInput(): bool
    {
        return $this->scopeService->canInput();
    }

    /**
     * Siapa yang boleh MENCATAT transaksi kas &amp; bank. Selain lintas unit
     * (finance/root/direktur/manager), admin cabang juga boleh mengisi
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
     * Apakah pengguna lintas unit (bisa memilih unit lain). Finance/Root/
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
     *   - rekening Finance/HO (IRA) TAMPIL hanya untuk ROOT / Finance;
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
     * Rekening yang boleh jadi SUMBER di form PINDAH SALDO.
     *
     * `akunSumberUntuk()` sudah menegakkan `canUseAsSource()`, jadi filter
     * tambahan di sini HANYA mempersempit ke BANK (Pindah Saldo bukan
     * Setor/Tarik — lihat `saveTransfer()`). Urutan itu penting: menyaring
     * `tipe` dulu lalu policy, atau policy dilewati, membuat rekening
     * Finance/HO yang tidak diizinkan sebagai sumber tetap muncul di
     * dropdown lalu ditolak server saat submit.
     */
    private function akunSumberPindahSaldo(?int $unit): array
    {
        return array_values(array_filter(
            $this->akunSumberUntuk($unit),
            static fn ($a) => (string) ($a->tipe ?? '') === 'BANK'
        ));
    }

    /**
     * Rekening yang boleh jadi TUJUAN di form PINDAH SALDO.
     *
     * Sengaja memakai `akunTujuanUntuk()` (policy `canUseAsDestination()`),
     * BUKAN policy source. "Boleh transfer KE IRA" tidak berarti boleh
     * memakai IRA sebagai sumber — lihat `KasBankScopeService`. Kalau kedua
     * sisi memakai helper yang sama, user KASIR kehilangan akses ke IRA
     * sebagai tujuan sah.
     */
    private function akunTujuanPindahSaldo(?int $unit): array
    {
        return array_values(array_filter(
            $this->akunTujuanUntuk($unit),
            static fn ($a) => (string) ($a->tipe ?? '') === 'BANK'
        ));
    }

    /**
     * Rekening yang boleh jadi TUJUAN (akun tujuan / akun penerima).
     *
     * Menyaring dengan canUseAsDestination(). Rekening Finance/HO (IRA)
     * TIDAK lagi ikut untuk semua unit — hanya unit yang benar-benar punya
     * rekening operasional sendiri, DAN user itu berwenang atas unit tsb.
     * Itu yang membuat "Unit 1 -> IRA" sah, sementara Unit 5 (rekening
     * banknya belum diverifikasi) dan Head Office tidak otomatis mendapat
     * kas Direksi hanya karena `is_finance_ho` = 1.
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

        // -----------------------------------------------------------------
        // RINGKASAN ARUS: source table, bukan ledger.
        //
        // `transaksi_kas_bank` DULU jadi sumber angka pemasukan/pengeluaran
        // di kartu ringkasan. Itu salah untuk dua alasan:
        //   1. isinya baraikan mirror penjualan/service/kas_keluar, sehingga
        //      ringkasan bisa menyimpang dari TutupKasir dan dari drill-down
        //      harian yang sekarang baca source table; dan
        //   2. tidak ada dimensi unit yang konsisten dengan account scope.
        // Arus operasional kini dihitung dari TutupKasirSourceDefinition —
        // definisi yang sama dengan tutup kasir, core Finance movement,
        // RekonDailyCalculator, dan CashFlowCalculator.
        //
        // Transfer internal TETAP dari ledger, karena memang tidak ada di
        // source table: hanya baris `transfer_ref IS NOT NULL` yang dihitung
        // (marker KasBankSetorTarikService). Mirror legacy tidak punya marker
        // itu sehingga tidak masuk dan tidak dobel.
        // -----------------------------------------------------------------
        $cutoff = FinanceScopeService::kasBankPeriodeMulaiDate();
        $dari   = $tanggalAwal !== '' ? max($tanggalAwal, $cutoff) : $cutoff;
        $sampai = $tanggalAkhir !== '' ? $tanggalAkhir : date('Y-m-d');

        // Unit yang boleh dihitung: unit terpilih, atau unit turunan dari
        // rekening yang terlihat (KAS -> unit-nya, BANK -> unit yang punya
        // alokasi). Setiap unit dihitung SATU kali walau rekeningnya banyak.
        $unitIds = [];
        if (! $konsolidasi && $unitTerpilih > 0) {
            $unitIds = [$unitTerpilih];
        } else {
            $movementSrc = new KasBankSourceMovement();
            foreach ($akun as $a) {
                $aid = (int)$a->idakun_kas_bank;
                if (($a->tipe ?? '') === 'KAS') {
                    if (! empty($a->unit_id)) {
                        $unitIds[(int)$a->unit_id] = true;
                    }
                } else {
                    foreach ($movementSrc->unitDialokasikanKe($aid) as $uid) {
                        $unitIds[(int)$uid] = true;
                    }
                }
            }
            $unitIds = array_keys($unitIds);
        }

        $ringkasan = [];
        if (! empty($unitIds)) {
            $srcDef = new TutupKasirSourceDefinition();
            foreach ($unitIds as $uid) {
                $h = $srcDef->ringkasanRange((int)$uid, $dari, $sampai);
                $ringkasan[ModeKasBank::JENIS_PEMASUKAN] =
                    ($ringkasan[ModeKasBank::JENIS_PEMASUKAN] ?? 0)
                    + (int)$h['cash'] + (int)$h['transfer'];
                $ringkasan[ModeKasBank::JENIS_PENGELUARAN] =
                    ($ringkasan[ModeKasBank::JENIS_PENGELUARAN] ?? 0)
                    + (int)$h['pengeluarancash'] + (int)$h['pengeluarantf'];
            }
        }

        // Transfer internal: NET terhadap rekening yang terlihat
        // (MASUK positif, KELUAR negatif). Kalau	source dan tujuan keduanya
        // terlihat, angkanya saling meniadakan — bukan terhitung dua kali
        // sebagai "+2x".
        $builder = db_connect()->table('transaksi_kas_bank')
            ->select("COALESCE(SUM(CASE WHEN transaksi_kas_bank.arah = 'MASUK' THEN transaksi_kas_bank.jumlah ELSE -transaksi_kas_bank.jumlah END), 0) AS total", false)
            ->where('transaksi_kas_bank.transfer_ref IS NOT NULL', null, false)
            ->where('transaksi_kas_bank.tanggal >=', $dari);

        if ($sampai !== '') {
            $builder->where('transaksi_kas_bank.tanggal <=', $sampai);
        }

        // Ringkasan WAJIB dibatasi rekening dalam scope, kalau tidak
        // transaksi rekening yang tidak terlihat pun ikut terhitung.
        if (empty($akunIds)) {
            $builder->where('1 = 0');
        } else {
            $builder->whereIn('transaksi_kas_bank.akun_kas_bank_id', $akunIds);
        }

        if (! $konsolidasi) {
            $builder->where('transaksi_kas_bank.unit_id', $unitTerpilih);
        }

        $netInternal = (int)($builder->get()->getRow()->total ?? 0);
        if ($netInternal !== 0) {
            $ringkasan[ModeKasBank::JENIS_TRANSFER] = $netInternal;
        }

        $netCashFlow = ((int)($ringkasan[ModeKasBank::JENIS_PEMASUKAN] ?? 0))
            - ((int)($ringkasan[ModeKasBank::JENIS_PENGELUARAN] ?? 0));

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
            'diagnostik_konfigurasi' => $this->diagnostikKonfigurasi(),
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
     * Diagnosa konfigurasi akun kas/bank yang MENCEGAH transaksi masuk ledger.
     *
     * Ini murni baca-saja dan tidak mengubah angka laporan. Gunanya supaya
     * kegagalan posting yang sebelumnya hanya muncul sebagai angka yang
     * tidak cocok bisa langsung terlihat oleh admin/developer: setiap butir
     * di sini punya penyebab dan cara memperbaikinya.
     *
     * Dipasang di halaman Kas & Bank (bukan dashboard laba rugi) karena itu
     * halaman konfigurasi rekening.
     *
     * @return array<int, array{level:string, judul:string, detail:array<int,string>, aksi:string}>
     */
    private function diagnostikKonfigurasi(): array
    {
        $db = db_connect();
        $out = [];

        // 1. Unit tanpa akun KAS -> transaksi tunai unit itu tidak bisa diposting.
        $unitTanpaKas = $db->query(
            'SELECT u.idunit, u.NAMA_UNIT FROM unit u
              WHERE u.idunit > 0
                AND NOT EXISTS (
                    SELECT 1 FROM akun_kas_bank a
                     WHERE a.unit_id = u.idunit AND a.tipe = \'KAS\'
                       AND a.status = \'aktif\' AND a.is_finance_ho = 0
                )
              ORDER BY u.idunit'
        )->getResult();

        if ($unitTanpaKas !== []) {
            $nama = array_map(static fn ($u) => $u->NAMA_UNIT . ' (#' . $u->idunit . ')', $unitTanpaKas);
            $out[] = [
                'level'  => 'danger',
                'judul'  => count($nama) . ' unit belum punya akun KAS aktif',
                'detail' => $nama,
                'aksi'   => 'Jalankan `php spark migrate` untuk membuat akun "Kas <UNIT>" (COA 1010101000) per unit. `kasbank:backfill` hanya mem-posting ulang transaksi, tidak membuat akun baru.',
            ];
        }

        // 2. Rekening bank di master yang belum dipetakan ke akun fisik.
        $bankTanpaAkun = $db->query(
            'SELECT b.idbank, b.nama_bank, b.norek, b.atas_nama FROM bank b
              WHERE b.jenis_bank = \'bank\'
                AND NOT EXISTS (
                    SELECT 1 FROM akun_kas_bank a WHERE a.bank_idbank = b.idbank
                )
              ORDER BY b.idbank'
        )->getResult();

        if ($bankTanpaAkun !== []) {
            $detail = [];
            foreach ($bankTanpaAkun as $b) {
                $detail[] = trim('idbank ' . $b->idbank . ' — ' . (string) $b->nama_bank . ' ' . (string) $b->norek
                    . ' (' . (string) $b->atas_nama . ')');
            }
            $out[] = [
                'level'  => 'danger',
                'judul'  => count($detail) . ' rekening bank belum punya akun fisik',
                'detail' => $detail,
                'aksi'   => 'Tambahkan lewat ' . base_url('kas_bank/akun') . ' atau `php spark migrate`.',
            ];
        }

        // 3. Rekening shared tanpa alokasi unit -> tidak ada unit yang berhak,
        //    jadi resolveAkun() selalu gagal padahal rekeningnya aktif.
        $sharedTanpaAlokasi = $db->query(
            'SELECT a.idakun_kas_bank, a.nama_akun FROM akun_kas_bank a
              WHERE a.is_shared = 1 AND a.is_finance_ho = 0 AND a.status = \'aktif\'
                AND NOT EXISTS (
                    SELECT 1 FROM alokasi_saldo_kas_bank al WHERE al.akun_kas_bank_id = a.idakun_kas_bank
                )
              ORDER BY a.idakun_kas_bank'
        )->getResult();

        if ($sharedTanpaAlokasi !== []) {
            $out[] = [
                'level'  => 'danger',
                'judul'  => count($sharedTanpaAlokasi) . ' rekening shared belum punya unit yang berhak',
                'detail' => array_map(static fn ($a) => $a->nama_akun . ' (#' . $a->idakun_kas_bank . ')', $sharedTanpaAlokasi),
                'aksi'   => 'Tentukan Hak Unit di ' . base_url('kas_bank/akun') . '. Tanpa itu rekening tidak bisa jadi sumber maupun tujuan.',
            ];
        }

        // 4. Bentuk rekening yang tidak konsisten: bukan milik unit tapi juga
        //    bukan shared. Ownershinya kosong, jadi tidak ada unit yang punya hak.
        //    Hanya akun AKTIF: rekening nonaktif memang tidak bisa dipakai, dan
        //    itu status yang sudah disengaja, bukan konfigurasi rusak.
        $tanpaPemilik = $db->query(
            'SELECT a.idakun_kas_bank, a.nama_akun FROM akun_kas_bank a
              WHERE a.unit_id IS NULL AND a.is_shared = 0 AND a.status = \'aktif\'
              ORDER BY a.idakun_kas_bank'
        )->getResult();

        if ($tanpaPemilik !== []) {
            $out[] = [
                'level'  => 'danger',
                'judul'  => count($tanpaPemilik) . ' rekening tidak punya pemilik unit',
                'detail' => array_map(static fn ($a) => $a->nama_akun . ' (#' . $a->idakun_kas_bank . ')', $tanpaPemilik),
                'aksi'   => 'Rekening ini tidak punya unit pemilik dan tidak ditandai shared, sehingga tidak bisa dipakai transaksi mana pun. Perbaiki di ' . base_url('kas_bank/akun') . '.',
            ];
        }

        // 5. Baseline statement pada tanggal CUT-OFF belum diinput.
        //
        // Ini bukan error konfigurasi, tapi kondisi yang WAJIB diselesaikan
        // Finance sebelum cut-off dipakai: saldo riil akhir tanggal cut-off
        // adalah opening balance, jadi tanpa baris statement di tanggal itu
        // tidak ada angka yang bisa dipakai. Level-nya 'warning' (bukan
        // 'danger') karena guard di KasBankSetorTarikService sudah menolak
        // Setor/Penarikan selama baseline belum tersedia — jadi tidak ada
        // transaksi yang bisa salah memakai angka nol.
        $baseline = (new \App\Services\Finance\KasBankCutoffService())->diagnostikBaseline();

        $baselineBelum = array_values(array_filter($baseline, static fn ($b) => ! $b['baseline_terverifikasi']));

        if ($baselineBelum !== []) {
            $detail     = [];
            $adaKasus   = false;
            $adaBank    = false;

            foreach ($baselineBelum as $b) {
                $kondisi = $b['baseline_ada']
                    ? sprintf('sudah ada baris %s tapi BELUM VERIFIKASI', $b['tanggal_baseline'])
                    : sprintf(
                        'belum ada baris pada %s; angka yang terbaca sekarang berasal dari baris lama tanggal %s, bukan baseline',
                        FinanceScopeService::kasBankCutoffDate(),
                        $b['tertagih'] ?? '(tidak ada baris)'
                    );

                // Rekening KAS dan BANK punya jalur baseline yang BERBEDA,
                // jadi ikutannya juga harus beda. Kalau tidak, operator laci
                // kas disuruh mengisi Statement — padahal statement hanya
                // untuk rekening bank.
                if (($b['tipe'] ?? '') === 'KAS') {
                    $adaKasus = true;

                    // Untuk KAS tidak ada jalur verifikasi: baris opening pada
                    // tanggal cut-off sudah langsung menjadi baseline. Karena
                    // `baseline_terverifikasi` TIDAK bisa beda dari
                    // `baseline_ada`, satu-satunya kondisi yang mungkin sampai
                    // ke sini adalah "belum ada opening".
                    $detail[] = sprintf(
                        '%s (#%d) — %s',
                        $b['nama_akun'],
                        $b['akun_id'],
                        sprintf('belum ada opening KAS pada %s', FinanceScopeService::kasBankCutoffDate())
                    );

                    continue;
                }

                $adaBank = true;
                $detail[] = sprintf('%s (#%d) — %s', $b['nama_akun'], $b['akun_id'], $kondisi);
            }

            // Aksi dipisah per jenis baseline supaya tidak menyuruh operator
            // melakukan hal yang memang tidak bisa dilakukan di form itu.
            $aksi = [];
            if ($adaBank) {
                $aksi[] = 'Rekening bank: input SALDO RIIL akhir ' . FinanceScopeService::kasBankCutoffDate()
                    . ' di ' . base_url('kas_bank/akun') . ' (tab Saldo awal), lalu set status "Terverifikasi".'
                    . ' Saldo riil itu sudah memabsorpsi transaksi 1–' . FinanceScopeService::kasBankCutoffDate()
                    . ', jadi jangan input hasil SUM transaksi legacy.';
            }
            if ($adaKasus) {
                $aksi[] = 'Laci kas: tetapkan OPENING KAS (uang fisik laci) di ' . base_url('kas_bank/akun') . '#opening-kas'
                    . ' untuk tanggal ' . FinanceScopeService::kasBankCutoffDate()
                    . '. Laci kas tidak punya statement bank.';
            }

            $out[] = [
                'level'  => 'warning',
                'judul'  => count($baselineBelum) . ' rekening belum punya opening balance untuk ' . FinanceScopeService::kasBankCutoffDate(),
                'detail' => $detail,
                'aksi'   => implode(' ', $aksi),
            ];
        }

        return $out;
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

// Opening KAS per rekening laci pada tanggal cut-off. Disajikan lewat
        // KasOpeningService supaya angka yang tampil di form, angka yang dipakai
        // service, dan angka yang dipakai cutoff service semuanya berasal dari
        // satu sumber yang sama.
        //
        // Dibaca semua baris opening pada cut-off (bukan cuma yang belum):
        // halaman harus menunjukkan laci mana yang sudah ada opening-nya dan
        // mana yang belum, supaya operator tidak bertanya-tanya kenapa saldo
        // laci tertentu tidak muncul.
        $openingKasSvc = new \App\Services\Finance\KasOpeningService();
        $openingKas    = $openingKasSvc->seluruhOpeningPerAkun();
        $openingKasById = [];
        foreach ($openingKas as $baris) {
            $openingKasById[(int) $baris->akun_kas_bank_id] = $baris;
        }
        $openingKasBelum = 0;
        foreach ($akun as $a) {
            if ((string) $a->tipe === 'KAS'
                && ! isset($openingKasById[(int) $a->idakun_kas_bank])) {
                $openingKasBelum++;
            }
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
            'diagnostik_konfigurasi' => $this->diagnostikKonfigurasi(),
            'opening_kas'           => $openingKas,
            'opening_kas_by_akun'   => $openingKasById,
            'opening_kas_belum'    => $openingKasBelum,
            'opening_kas_cutoff'    => FinanceScopeService::kasBankCutoffDate(),
            'bisa_input'            => $this->canInput(),
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

        if ($this->AkunScope->isFinanceHo($akun)) {
            return $this->gagal('Rekening Finance/HO "' . $akun->nama_akun
                . '" tidak memakai alokasi unit. Alokasi hanya untuk rekening Shared Antar Unit.');
        }

        if (! $this->AkunScope->userBolehUnit($unitId)) {
            return $this->gagal('Unit alokasi berada di luar cakupan Anda.');
        }

        $cutoff = new \App\Services\Finance\KasBankCutoffService();

        // Verifikasi statement BUKAN syarat alokasi. Proses verifikasi berdiri
        // sendiri dan hanya dipakai alur yang memang butuh angka terkonfirmasi
        // (setor/tarik, transfer, bayar hutang/piutang).
        //
        // Satu-satunya batas alokasi ada DI DALAM transaksi: `cekOpeningAllocation()`
        // membandingkan total alokasi dengan saldo statement cut-off — angka yang
        // sudah Finance input lewat "Catat saldo awal" — bukan dengan status
        // verifikasinya. Rekening yang belum diinput saldo awalnya tetap ditolak
        // karena totalnya melebihi saldo statement 0, bukan karena belum diverifikasi.

        $data = [
            'akun_kas_bank_id' => $akunId,
            'unit_id'          => $unitId,
            'nominal'          => $nominal,
            'keterangan'       => $ket,
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        $db = \Config\Database::connect();

        try {
            $db->transStart();

            // Lock baris statement milik rekening ini. Baris itu adalah anchor
            // yang UNIQUE per (akun, tanggal), jadi mengunciNYA membuat semua
            // penulisan alokasi untuk rekening tersebut saling menunggu.
            // Tanpa lock, dua user bisa membaca total alokasi yang sama lalu
            // dua-duanya lolos guard, lalu total alokasi melebihi statement.
            $cutoff->lockRekening($akunId);

            // Baca baris alokasi di dalam transaksi, SETELAH lock statement
            // diambil. Ini menutup celah TOCTOU: nilai $sebelum yang dipakai
            // menghitung $tambahan dijamin sama dengan yang akan di-update.
            $existing = $db->table('alokasi_saldo_kas_bank')
                ->select('id, nominal')
                ->where('akun_kas_bank_id', $akunId)
                ->where('unit_id', $unitId)
                ->get()
                ->getRow();

            $sebelum  = $existing ? (int) $existing->nominal : 0;
            $tambahan = $nominal - $sebelum;

            // Guard DI DALAM transaksi. Versi lama menghitung $tambahan dan
            // cek guard sebelum transStart(), jadi ada celah di mana total
            // alokasi berubah setelah dicek tapi sebelum disimpan.
            $guard = $cutoff->cekOpeningAllocation($akunId, $tambahan);
            if (! $guard['ok']) {
                $db->transRollback();

                return $this->gagal($guard['alasan']);
            }

            if ($existing) {
                $db->table('alokasi_saldo_kas_bank')->update($data, ['id' => $existing->id]);
            } else {
                $data['input_by']   = (int) session()->get('ID_AKUN');
                $data['created_at'] = date('Y-m-d H:i:s');
                $db->table('alokasi_saldo_kas_bank')->insert($data);
            }

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();

            return $this->gagal('Gagal menyimpan alokasi saldo: ' . $e->getMessage());
        }

        if ($db->transStatus() === false) {
            return $this->gagal('Gagal menyimpan alokasi saldo (transaksi gagal).');
        }

        session()->setFlashdata('sukses', 'Alokasi saldo awal unit berhasil disimpan');
        return redirect()->to(base_url('kas_bank/akun'));
    }

    // =====================================================================
    // OPENING KAS
    //
    // Baseline laci kas yang ditetapkan Finance pada tanggal cut-off, lalu
    // dicocokkan dengan real cash hasil hitung laci saat Tutup Kasir.
    //
    // Berdiri sendiri dari statement bank: rekening tipe KAS tidak punya
    // statement, dan `tutup_kasir.akhir_cash` TIDAK pernah jadi sumber
    // opening — hanya bahan pembanding saat verifikasi.
    // =====================================================================

    /**
     * Simpan/ubah baseline opening KAS.
     *
     * Satu rekening hanya boleh punya satu baris opening pada satu tanggal
     * (dijamin UNIQUE di database), jadi "simpan" di sini selalu berarti
     * upsert. Mengubah angka opening mengganti baseline periode ini; saldo
     * setelah cut-off otomatis dihitung dari opening baru + seluruh mutasi.
     * Tidak ada verifikasi.
     */
    public function saveOpeningKas()
    {
        if (! $this->canInput()) {
            return $this->gagal('Anda tidak berhak menetapkan opening KAS.');
        }

        $akunId   = (int) $this->request->getPost('akun_kas_bank_id');
        $opening  = (int) preg_replace('/[^0-9]/', '', (string) $this->request->getPost('opening'));
        $keterangan = trim((string) $this->request->getPost('keterangan'));
        $tanggal  = FinanceScopeService::kasBankCutoffDate();

        $svc = new \App\Services\Finance\KasOpeningService();

        $hasil = $svc->inputOpening(
            $akunId,
            $opening,
            $keterangan === '' ? null : $keterangan,
            (int) (session()->get('ID_AKUN') ?? 0),
            $tanggal
        );

        if (! $hasil['ok']) {
            return $this->gagal($hasil['alasan']);
        }

        $akun = $this->AkunModel->find($akunId);

        session()->setFlashdata(
            'sukses',
            'Opening KAS ' . (string) ($akun->nama_akun ?? $akunId) . ' tanggal ' . $tanggal
            . ' disimpan. Saldo laci mulai 1 hari setelah cut-off memakai baseline ini.'
        );

        return redirect()->to(base_url('kas_bank/akun') . '#opening-kas');
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

        $cutoff  = new \App\Services\Finance\KasBankCutoffService();
        $tanggal = \App\Services\Finance\FinanceScopeService::tanggalStr($tanggal);

        if ($akunId <= 0) {
            return $this->gagal('Akun wajib dipilih');
        }

        $akun = $this->AkunModel->find($akunId);
        if (!$akun || $akun->status !== 'aktif') {
            return $this->gagal('Akun tidak ditemukan / tidak aktif');
        }
        if (! $this->akunBolehDiKelola($akun)) {
            return $this->gagal('Rekening ini tidak terkait dengan unit dalam cakupan Anda.');
        }

        // Statement hanya boleh diisi/diubah pada tanggal cut-off, dan hanya
        // oleh user yang boleh input. Setelah migration 2026-10-03-000400 satu
        // rekening bisa punya BANYAK baris statement (satu per tanggal), jadi
        // `getByAkun()` tanpa tanggal sudah tidak unambiguously benar: dia
        // bisa saja update statement dari periode lain. Migrasi ini membuat
        // edit selalu menyasar baris (akun, tanggal cut-off) yang benar.
        if ($tanggal !== $cutoff->tanggalCutoff()) {
            return $this->gagal(
                'Tanggal statement harus ' . $cutoff->tanggalCutoff()
                . ' (tanggal cut-off). Tanggal yang dipilih: ' . $tanggal . '.'
            );
        }

        // Verifikasi statement BUKAN syarat input saldo awal, termasuk untuk
        // nilai 0. Kolom `status` tetap yang menjelaskan mana placeholder dan
        // mana fakta — 0 yang belum diverifikasi disimpan apa adanya dan masih
        // dilaporkan "BELUM VERIFIKASI" oleh diagnostik baseline. Menolak 0 di
        // sini justru memaksa Finance menandai verifikasi hanya demi bisa
        // menyimpan saldo, padahal verifikasi adalah proses terpisah.
        $verifikasi = (string) $this->request->getPost('status') === KasBankCutoffService::STATEMENT_SUDAH;

        $existing = $this->SaldoAwalModel->getByAkunTanggal($akunId, $tanggal);
        $data = [
            'akun_kas_bank_id' => $akunId,
            'tanggal'          => $tanggal,
            'saldo'            => $saldo,
            'keterangan'       => $ket,
            'status'           => $verifikasi
                ? KasBankCutoffService::STATEMENT_SUDAH
                : KasBankCutoffService::STATEMENT_BELUM,
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        try {
            if ($existing) {
                $this->SaldoAwalModel->update($existing->id, $data);
                session()->setFlashdata('sukses', 'Statement ' . $tanggal . ' berhasil diperbarui');
            } else {
                $data['input_by']   = (int) session()->get('ID_AKUN');
                $data['created_at'] = date('Y-m-d H:i:s');
                $this->SaldoAwalModel->insert($data);
                session()->setFlashdata('sukses', 'Statement ' . $tanggal . ' berhasil disimpan');
            }
        } catch (\Throwable $e) {
            return $this->gagal('Gagal menyimpan statement: ' . $e->getMessage());
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

        $akunSumberPindah = $this->akunSumberPindahSaldo($unitTerpilih);
        $akunTujuanPindah = $this->akunTujuanPindahSaldo($unitTerpilih);

        // Lookup label di tabel riwayat memakai GABUNGAN kedua daftar, bukan
        // salah satunya. Kalau hanya `akun_sumber`, rekening yang hanya sah
        // sebagai tujuan (mis. IRA untuk ROOT) tidak punya nama di tabel dan
        // kolomnya render "-". Union ini tetap BANK-only, tidak memasukkan KAS.
        $akunLabelPindah = $akunSumberPindah;
        foreach ($akunTujuanPindah as $a) {
            $akunLabelPindah[(int) $a->idakun_kas_bank] = $a;
        }

        $data = array_merge($this->pageData(), [
            'unit_terpilih' => $unitTerpilih,
            // SUMBER dan TUJUAN memakai policy BERBEDA, bukan satu helper.
            // Rekening Finance/HO (IRA) sah jadi tujuan dari unit yang punya
            // rekening operasional sendiri, tapi hanya ROOT / Finance yang
            // boleh menarik dananya — lihat KasBankScopeService.
            'akun_sumber'   => $akunSumberPindah,
            'akun_tujuan'   => $akunTujuanPindah,
            // KHUSUS label tabel riwayat. Jangan dipakai untuk dropdown, dan
            // jangan diisi KAS hanya demi kelengkapan label: baris KAS tidak
            // pernah ada di tab ini karena Pindah Saldo wajib BANK -> BANK.
            'akun_kas_bank' => array_values($akunLabelPindah),
            'can_transaksi' => $this->bisaTransaksi(),
            'transaksi'     => $this->transaksiTerlihat(
                ModeKasBank::JENIS_TRANSFER,
                $unitTerpilih,
                // Hanya Pindah Saldo. Setor/Tarik punya sumber_tipe sendiri
                // (SETOR_TUNAI / PENARIKAN_TUNAI) dan tampil di tab "Setor /
                // Tarik Tunai" — bukan di sini.
                KasBankSetorTarikService::SUMBER_TIPE_PINDAH_SALDO
            ),
            'submit_token'  => $this->buatSubmitToken(),
            'body'          => 'kas_bank/transfer',
        ]);

        return view('template', $data);
    }

    /**
     * Daftar transaksi kas/bank yang boleh dilihat: DIBATASI rekening dalam
     * scope. Tanpa ini user akan melihat mutasi rekening yang account
     * scope-nya di luar haknya.
     *
     * `$sumberTipe` (opsional) adalah PENYARING SUBTYPE. Itu wajib diisi
     * untuk `TRANSFER_INTERNAL`, karena `jenis` itu dipakai bersama oleh
     * Setor/Tarik, Pindah Saldo, dan (sebelum fase ini) keduanya tercampur di
     * tab yang sama. Tanpa penyaring, tab "Pindah Saldo" menampilkan Setor
     * dan Tarik seolah-olah itu Pindah Saldo — dan link reversal-nya ikut
     * memakai subtype yang salah.
     *
     * Null = jangan filter `sumber_tipe` (untuk jenis yang punya satu
     * subtype saja, mis. `PEMBAYARAN_ANTAR_UNIT`).
     */
    private function transaksiTerlihat(string $jenis, ?int $unitTerpilih, ?string $sumberTipe = null)
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

        $builder = $this->TransaksiModel
            ->where('jenis', $jenis)
            ->groupStart()
                ->whereIn('akun_kas_bank_id', $akunIds)
                ->orGroupStart()
                    ->whereIn('akun_tujuan_id', $akunIds)
                ->groupEnd()
            ->groupEnd();

        if ($sumberTipe !== null && $sumberTipe !== '') {
            $builder->where('sumber_tipe', $sumberTipe);
        }

        return $builder
            ->orderBy('idtransaksi', 'DESC')
            ->limit(200)
            ->findAll();
    }

    /**
     * PINDAH SALDO — BANK -> BANK.
     *
     * ============ BATASAN PERAN — BACA DULU ============
     * Method ini HANYA untuk pindah saldo antar rekening BANK milik sendiri
     * (mis. BCA -> Mandiri). KAS <-> BANK TIDAK boleh lewat sini: uang tunai
     * masuk/kelap laci adalah fitur Setor/Tarik, dan harus lewat
     * `saveSetorTunai()` -> `KasBankSetorTarikService` supaya guard baseline
     * terverifikasi, cek saldo, `cekTarikUnit()`, dan idempotensi ikut jalan.
     *
     * Kenapa pemisahan ini wajib, bukan sekadar neatly: sebelum fase ini
     * method ini menerima KAS <-> BANK dan menulis `jenis =
     * TRANSFER_INTERNAL` yang sama persis dengan Setor/Tarik, tanpa satu pun
     * guard tersebut. Efeknya pada rekening shared: Unit 2 bisa menarik
     * `KAS(Unit 2)` dari rekening CV yang saldonya milik Unit 1, karena
     * `cekTarikUnit()` tidak pernah dipanggil. Itu persis skenario yang
     * harus dilarang aturan "Tarik milik 1 unit, tidak boleh lintas unit".
     *
     * Yang TIDAK berubah: nilai `jenis` tetap `TRANSFER_INTERNAL` (tidak ada
     * migrasi enum), dan kedua kaki tetap memakai `transfer_ref` yang sama.
     * Subtype-nya dibedakan lewat `sumber_tipe = PINDAH_SALDO`.
     *
     * @see \App\Services\Finance\KasBankSetorTarikService::SUMBER_TIPE_PINDAH_SALDO
     */
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
        //
        // Catatan: POST ini BUKAN otoritas. Ia hanya kandidat yang langsung
        // diuji `userBolehUnit()` + `canUseAsSource/Destination()` di bawah,
        // jadi user tidak bisa memakai rekening milik Unit lain lewat forged POST.
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

        // ---- BATASAN PERAN: PINDAH SALDO itu BANK -> BANK ----
        //
        // Sumber dari KAS atau tujuan ke KAS berarti uang masuk/keluar laci,
        // dan itu fitur Setor/Tarik. Kalau dibiarkan lewat sini, guard
        // baseline + cek saldo + cekTarikUnit() pada
        // KasBankSetorTarikService semuanya dilewati.
        if ((string) $asal->tipe !== 'BANK') {
            return $this->gagal('Pindah Saldo hanya untuk antar rekening BANK. '
                . 'KAS ke BANK adalah SETOR — pakai menu "Setor / Tarik Tunai" '
                . 'supaya baseline terverifikasi dan saldo laci ikut diperiksa.');
        }
        if ((string) $tujuan->tipe !== 'BANK') {
            return $this->gagal('Pindah Saldo hanya untuk antar rekening BANK. '
                . 'BANK ke KAS adalah TARIK — pakai menu "Setor / Tarik Tunai" '
                . 'supaya posisi unit dan saldo laci ikut diperiksa.');
        }

        // ---- TANGGAL: hanya dalam periode operasional ----
        // Hanya batas BAWAH, konsisten dengan KasBankSetorTarikService dan
        // dengan saveTransfer() di file ini. Catatan lengkap soal kenapa tidak
        // ada batas atas "tanggal <= hari ini" ada di saveTransfer().
        $tanggal = FinanceScopeService::tanggalStr($tanggal);
        $mulai   = FinanceScopeService::kasBankPeriodeMulaiDate();


        if ($tanggal < $mulai) {
            return $this->gagal('Tanggal ' . $tanggal . ' berada sebelum periode operasional baru (' . $mulai . '). '
                . 'Mutasi sebelum cut-off tidak boleh masuk ledger periode baru.');
        }

        // Karena kedua kaki sudah dipastikan BANK, unit leg = unit transaksi.
        $unitKeluar = $unitId;
        $unitMasuk  = $unitId;
        $role       = (int) session('ID_JABATAN');

        // GUARD BERARAH — "boleh transfer KE IRA" tidak berarti boleh memakai
        // IRA sebagai SUMBER.
        //   FINANCE_HO/IRA: sebagai tujuan = unit yang punya rekening
        //     operasional sendiri (Unit 1 -> IRA dan Unit 2 -> IRA sah;
        //     Unit 5 & Head Office tidak, sampai rekeningnya terverifikasi);
        //     sebagai sumber = HANYA ROOT / Finance.
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

        // ---- GUARD SALDO & BASELINE (yang sebelumnya tidak ada) ----
        //
        // Tanpa dua blok ini, method ini bisa jadi jalur bypass Setor/Tarik:
        // pada rekening shared, `canUseAsSource()` hanya membuktikan unit
        // punya AKSES, bukan bahwa saldonya cukup miliknya.
        $cutoff = new \App\Services\Finance\KasBankCutoffService();
        $policy = new \App\Services\Finance\EntitlementPolicyService();

        // Rekening yang memang wajib punya statement: saldo yang dipindah
        // harus berdasar angka yang sudah diverifikasi Finance, bukan
        // placeholder 0. Daftar rekeningnya datang dari config, bukan tebakan.
        foreach ([[$asal, 'asal'], [$tujuan, 'tujuan']] as [$akun, $namaKaki]) {
            if ($policy->wajibStatementVerifikasi((int) $akun->idakun_kas_bank)
                && ! $cutoff->statementVerified((int) $akun->idakun_kas_bank, $tanggal)
            ) {
                return $this->gagal('Rekening ' . $namaKaki . ' "' . $akun->nama_akun . '" belum punya '
                    . 'statement yang diverifikasi Finance pada ' . $cutoff->tanggalCutoff() . '. '
                    . 'Isi dan verifikasi statement di menu "Rekening & Saldo Awal" dulu.');
            }
        }

        // Cek POSISI UNIT, bukan saldo fisik rekening. Di rekening shared,
        // saldo fisik bisa cukup sementara posisi unit ini nol — dan itu
        // harus ditolak, persis seperti Tarik.
        $cek = $cutoff->cekTarikUnit($asalId, $unitKeluar, $jumlah);
        if (! $cek['ok']) {
            return $this->gagal($cek['alasan']);
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

            // `sumber_tipe` wajib diisi supaya baris ini bisa dibedakan dari
            // Setor/Tarik (yang memakai SETOR_TUNAI / PENARIKAN_TUNAI) walau
            // `jenis`-nya sama-sama TRANSFER_INTERNAL. Tanpa ini, tab Pindah
            // Saldo ikut menampilkan Setor/Tarik — persis duplikasi yang
            // dipisah di fase ini.
            $legKeluar = $this->TransaksiModel->insert([
                'tanggal'          => $tanggal,
                'unit_id'          => $unitKeluar,
                'akun_kas_bank_id' => $asalId,
                'jenis'            => ModeKasBank::JENIS_TRANSFER,
                'arah'             => ModeKasBank::ARAH_KELUAR,
                'jumlah'           => $jumlah,
                'akun_tujuan_id'   => $tujuanId,
                'transfer_ref'     => $transferRef,
                'submission_key'   => $token,
                'sumber_tipe'      => KasBankSetorTarikService::SUMBER_TIPE_PINDAH_SALDO,
                'keterangan'       => $ket,
                'bukti'            => $bukti,
                'input_by'         => (int)session()->get('ID_AKUN'),
                'created_at'       => date('Y-m-d H:i:s'),
            ]);

            // Insert kedua kaki hanya kalau kaki pertama benar-benar masuk.
            // Kalau kaki pertama ditolak, transaksi sudah pasti di-rollback;
            // mencoba insert kedua hanya menambah lock yang tidak perlu.
            $legMasuk = $legKeluar !== false
                ? $this->TransaksiModel->insert([
                    'tanggal'          => $tanggal,
                    'unit_id'          => $unitMasuk,
                    'akun_kas_bank_id' => $tujuanId,
                    'jenis'            => ModeKasBank::JENIS_TRANSFER,
                    'arah'             => ModeKasBank::ARAH_MASUK,
                    'jumlah'           => $jumlah,
                    'transfer_ref'     => $transferRef,
                    // submission_key UNIQUE: kaki kedua harus NULL.
                    'submission_key'   => null,
                    'sumber_tipe'      => KasBankSetorTarikService::SUMBER_TIPE_PINDAH_SALDO,
                    'keterangan'       => $ket,
                    'input_by'         => (int)session()->get('ID_AKUN'),
                    'created_at'       => date('Y-m-d H:i:s'),
                ])
                : false;

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'KasBank: gagal menyimpan transfer: ' . $e->getMessage());
            return $this->gagal('Gagal menyimpan transfer. Silakan coba lagi.');
        }

        // transComplete() sudah me-rollback kalau ada query yang gagal, tapi
        // dia tidak melempar apa pun, jadi statusnya wajib diperiksa.
        if ($db->transStatus() === false || $legKeluar === false || $legMasuk === false) {
            return $this->gagal('Gagal menyimpan transfer');
        }

        session()->setFlashdata('sukses', 'Pindah saldo berhasil (tidak memengaruhi Net Cash Flow)');
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

        // Hanya Pindah Saldo yang boleh dibatalkan dari tab ini. Setor/Tarik
        // ber-jenis sama (TRANSFER_INTERNAL) tapi pembatalannya milik tab
        // "Setor / Tarik Tunai" — kalau lolos ke sini, guard baseline dan
        // cekTarikUnit() yang biasanya melindungi posisi unit jadi dilewati.
        if ((string) ($row->sumber_tipe ?? '') !== KasBankSetorTarikService::SUMBER_TIPE_PINDAH_SALDO) {
            return $this->gagal('Transaksi ini bukan Pindah Saldo. '
                . 'Setor/Tarik dibatalkan dari menu "Setor / Tarik Tunai".');
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

    // =====================================================================
    // SETOR TUNAI & PENARIKAN TUNAI
    //
    // Aturan yang dipegang controller di bagian ini:
    //
    //   1. Controller TIDAK menghitung saldo, entitlement, legacy, atau posisi
    //      unit. Semua angka berasal dari KasBankCutoffService.
    //   2. Controller TIDAK mengarang keputusan. Guard yang menolak transaksi
    //      tetap milik KasBankSetorTarikService; UI hanya menjelaskan alasan
    //      bisnisnya sebelum user menekan simpan.
    //   3. Idempotensi memakai submission_key yang STABIL antar reload, bukan
    //      token acak per render. Lihat operationKey() untuk alasannya.
    // =====================================================================

    /**
     * Slot session untuk menyimpan operation key per jenis transaksi + unit.
     */
    private const SLOT_SETOR     = 'setor';
    private const SLOT_PENARIKAN = 'penarikan';

    /**
     * Halaman Setor Tunai (GET) + preview (POST).
     */
    public function setorTunai()
    {
        if (! $this->bisaTransaksi()) {
            return $this->gagal('Anda tidak berhak melakukan setor tunai.');
        }

        $unitTerpilih = $this->unitTerpilih();
        $operationKey = $this->operationKey(self::SLOT_SETOR, $unitTerpilih);

        $data = array_merge($this->pageData(), [
            'unit_terpilih'  => $unitTerpilih,
            'akun_kas'        => $this->akunKasUntukUnit($unitTerpilih),
            'akun_bank'       => $this->akunTujuanUntuk($unitTerpilih),
            'can_transaksi'   => true,
            'submit_token'    => $this->buatSubmitToken(),
            'operation_key'   => $operationKey,
            'transaksi'       => $this->transaksiSetorTarik($unitTerpilih),
            'cutoff_info'     => $this->infoCutoff(),
            'preview'         => null,
            'body'            => 'kas_bank/setor_tunai',
        ]);

        // POST berarti user menekan "Tampilkan Pratinjau": form diisi ulang
        // dengan input yang sama PLUS angka pratinjau. Belum ada yang ditulis.
        if ($this->request->getMethod() === 'post') {
            $input = $this->inputSetorTarik();

            $data['input']   = $input;
            $data['preview'] = $this->pratinjauSetor($input, $unitTerpilih);
        } else {
            $data['input'] = $this->inputSetorTarikLama();
        }

        return view('template', $data);
    }

    /**
     * Simpan Setor Tunai. Satu-satunya tempat yang memanggil service untuk
     * SETOR; tidak ada perhitungan saldo di sini.
     */
    public function saveSetorTunai()
    {
        if (! $this->bisaTransaksi()) {
            return $this->gagal('Anda tidak berhak melakukan setor tunai.');
        }

        $unitTerpilih = $this->unitTerpilih();
        $input        = $this->inputSetorTarik();

        // Unit transaksi di-stamp dari form, TAPI hanya setelah dicek masih
        // di dalam cakupan user. Tanpa cek ini user non-lintas bisa
        // men-stamp leg ke unit lain.
        $unitId = $this->unitTransaksiDariForm($input, $unitTerpilih);
        if ($unitId === null) {
            return $this->gagal('Unit transaksi berada di luar cakupan Anda.');
        }

        // Rekening hasil POST harus benar-benar rekening yang tampil di
        // dropdown. Tanpa cek ini, POST yang dimanipulasi bisa memakai
        // rekening unit lain atau rekening yang bukan KAS/BANK.
        $masalahRekening = $this->masalahScopeRekening($input, $unitId, 'setor');
        if ($masalahRekening !== null) {
            return $this->gagal($masalahRekening);
        }

        // Anti double-submit: token dibuat saat form dirender dan dikonsumsi
        // di sini. Klik kedua atas tombol Simpan akan ditolak.
        if (! $this->klaimSubmitToken((string) ($input['submit_token'] ?? ''))) {
            return $this->gagal('Form sudah dikirim atau tidak valid. Muat ulang halaman untuk mencoba lagi.');
        }

        $hasil   = $this->SetorTarikLib->setorTunai(
            $unitId,
            (int) $input['akun_kas_id'],
            (int) $input['akun_bank_id'],
            (int) $input['nominal'],
            (string) $input['tanggal'],
            $this->operationKey(self::SLOT_SETOR, $unitId),
            (string) $input['keterangan'],
            (int) session()->get('ID_AKUN')
        );

        return $this->selesaiSetorTarik($hasil, self::SLOT_SETOR, $unitId, 'setor', $input);
    }

    /**
     * Halaman Penarikan Tunai (GET) + preview (POST).
     */
    public function penarikanTunai()
    {
        if (! $this->bisaTransaksi()) {
            return $this->gagal('Anda tidak berhak melakukan penarikan tunai.');
        }

        $unitTerpilih = $this->unitTerpilih();
        $operationKey = $this->operationKey(self::SLOT_PENARIKAN, $unitTerpilih);

        $data = array_merge($this->pageData(), [
            'unit_terpilih'  => $unitTerpilih,
            'akun_bank'      => $this->akunSumberUntuk($unitTerpilih),
            'akun_kas'       => $this->akunKasUntukUnit($unitTerpilih),
            'can_transaksi'  => true,
            'submit_token'   => $this->buatSubmitToken(),
            'operation_key'  => $operationKey,
            'transaksi'      => $this->transaksiSetorTarik($unitTerpilih),
            'cutoff_info'    => $this->infoCutoff(),
            'preview'        => null,
            'body'           => 'kas_bank/penarikan_tunai',
        ]);

        if ($this->request->getMethod() === 'post') {
            $input = $this->inputSetorTarik();

            $data['input']   = $input;
            $data['preview'] = $this->pratinjauPenarikan($input, $unitTerpilih);
        } else {
            $data['input'] = $this->inputSetorTarikLama();
        }

        return view('template', $data);
    }

    /**
     * Simpan Penarikan Tunai.
     */
    public function savePenarikanTunai()
    {
        if (! $this->bisaTransaksi()) {
            return $this->gagal('Anda tidak berhak melakukan penarikan tunai.');
        }

        $unitTerpilih = $this->unitTerpilih();
        $input        = $this->inputSetorTarik();

        $unitId = $this->unitTransaksiDariForm($input, $unitTerpilih);
        if ($unitId === null) {
            return $this->gagal('Unit transaksi berada di luar cakupan Anda.');
        }

        $masalahRekening = $this->masalahScopeRekening($input, $unitId, 'penarikan');
        if ($masalahRekening !== null) {
            return $this->gagal($masalahRekening);
        }

        if (! $this->klaimSubmitToken((string) ($input['submit_token'] ?? ''))) {
            return $this->gagal('Form sudah dikirim atau tidak valid. Muat ulang halaman untuk mencoba lagi.');
        }

        $hasil   = $this->SetorTarikLib->tarikTunai(
            $unitId,
            (int) $input['akun_kas_id'],
            (int) $input['akun_bank_id'],
            (int) $input['nominal'],
            (string) $input['tanggal'],
            $this->operationKey(self::SLOT_PENARIKAN, $unitId),
            (string) $input['keterangan'],
            (int) session()->get('ID_AKUN')
        );

        return $this->selesaiSetorTarik($hasil, self::SLOT_PENARIKAN, $unitId, 'penarikan', $input);
    }

    // ---------------------------------------------------------------------
    // Helper: input & unit
    // ---------------------------------------------------------------------

    /**
     * Normalisasi input POST. Tidak ada validasi bisnis di sini — semua
     * aturan saldo/entitlement milik service. Yang dinormalisasi hanya bentuk
     * (angka jadi integer, tanggal jadi Y-m-d).
     *
     * @return array<string, mixed>
     */
    private function inputSetorTarik(): array
    {
        $tanggal = (string) $this->request->getPost('tanggal');

        return [
            'unit_id'       => (int) $this->request->getPost('unit_id'),
            'akun_kas_id'   => (int) $this->request->getPost('akun_kas_id'),
            'akun_bank_id'  => (int) $this->request->getPost('akun_bank_id'),
            'nominal'       => (int) preg_replace('/[^0-9]/', '', (string) $this->request->getPost('nominal')),
            'tanggal'       => FinanceScopeService::tanggalStr($tanggal !== '' ? $tanggal : date('Y-m-d')),
            'keterangan'    => trim((string) $this->request->getPost('keterangan')),
            'submit_token'  => (string) $this->request->getPost('submit_token'),
            'operation_key' => trim((string) $this->request->getPost('operation_key')),
        ];
    }

    /**
     * Input yang harus dipulihkan setelah submit gagal, supaya user tidak
     * perlu mengetik ulang.
     *
     * @return array<string, mixed>
     */
    private function inputSetorTarikLama(): array
    {
        $old = session()->getFlashdata('kb_setor_tarik_input');

        if (! is_array($old)) {
            return [
                'unit_id'      => 0,
                'akun_kas_id'  => 0,
                'akun_bank_id' => 0,
                'nominal'      => 0,
                'tanggal'      => date('Y-m-d'),
                'keterangan'   => '',
            ];
        }

        return $old;
    }

    /**
     * Unit transaksi yang sah: dari form, jatuh ke unit terpilih/sesi, tapi
     * SELALU diverifikasi terhadap cakupan user.
     *
     * null = di luar cakupan.
     */
    private function unitTransaksiDariForm(array $input, ?int $unitTerpilih): ?int
    {
        $unitId = (int) ($input['unit_id'] ?? 0);

        if ($unitId <= 0) {
            $unitId = (int) $unitTerpilih;
        }
        if ($unitId <= 0) {
            $unitId = (int) session()->get('ID_UNIT');
        }

        return $this->AkunScope->userBolehUnit($unitId) ? $unitId : null;
    }

    /**
     * Cek bahwa rekening hasil POST benar-benar rekening yang tampil di form.
     *
     * Ini bukan aturan saldo. Yang diperiksa hanya scope: kalau angka
     * rekening diubah di browser, request harus ditolak di sini, bukan
     * diteruskan ke service.
     *
     * @return string|null pesan penolakan, atau null bila aman
     */
    private function masalahScopeRekening(array $input, ?int $unitId, string $arah): ?string
    {
        if ($unitId === null) {
            return 'Unit transaksi berada di luar cakupan Anda.';
        }

        $kas  = $this->AkunModel->find((int) ($input['akun_kas_id'] ?? 0));
        $bank = $this->AkunModel->find((int) ($input['akun_bank_id'] ?? 0));

        if ($kas === null || (string) $kas->tipe !== 'KAS' || (string) $kas->status !== 'aktif') {
            return 'Akun kas tidak valid atau sudah tidak aktif.';
        }
        if ($bank === null || (string) $bank->tipe !== 'BANK' || (string) $bank->status !== 'aktif') {
            return 'Rekening bank tidak valid atau sudah tidak aktif.';
        }

        // KAS punya satu unit pemilik yang pasti, jadi harus unit pemohon.
        if ((int) ($kas->unit_id ?? 0) !== $unitId) {
            return 'Akun kas bukan milik unit yang dipilih.';
        }

        // Rekening bank harus lolos filter scope + entitlement yang sama dengan
        // dropdown: canUseAsDestination() untuk setor, canUseAsSource() untuk
        // penarikan.
        $daftar = $arah === 'penarikan'
            ? $this->akunSumberUntuk($unitId)
            : $this->akunTujuanUntuk($unitId);

        foreach ($daftar as $a) {
            if ((int) $a->idakun_kas_bank === (int) $bank->idakun_kas_bank) {
                return null;
            }
        }

        return 'Rekening bank di luar cakupan Anda. Pilih ulang dari daftar.';
    }

    /**
     * Akun KAS milik unit — hanya rekening laci kas, bukan rekening bank.
     *
     * Sengaja memakai KAS milik unit saja, bukan "semua akun unit", supaya
     * form setor/penarikan tidak bisa salah pilih rekening bank sebagai laci
     * kas.
     */
    private function akunKasUntukUnit(?int $unit): array
    {
        $userUnits = $this->unitIdsUser();

        return array_values(array_filter(
            $this->akunListUntuk($unit, true, false),
            static function ($a) use ($unit, $userUnits) {
                if ((string) $a->tipe !== 'KAS' || (string) $a->status !== 'aktif') {
                    return false;
                }

                // KAS selalu punya satu unit pemilik yang pasti.
                $pemilik = (int) ($a->unit_id ?? 0);
                if ($pemilik <= 0) {
                    return false;
                }

                // Konsolidasi (null): tampilkan KAS semua unit dalam cakupan.
                if ($unit !== null && $pemilik !== $unit) {
                    return false;
                }

                return in_array($pemilik, $userUnits, true);
            }
        ));
    }

    // ---------------------------------------------------------------------
    // Helper: idempotensi
    // ---------------------------------------------------------------------

    /**
     * Operation key yang STABIL untuk satu (jenis transaksi, unit).
     *
     * Kenapa tidak pakai submit_token sebagai submission_key: token itu dibuat
     * baru setiap kali form dirender. Kalau user menekan refresh setelah
     * request timeout, halaman render ulang -> token baru -> key baru ->
     * transaksi DUPLIKAT.persis masalah yang harus dicegah.
     *
     * Solusinya: key disimpan di session per (jenis, unit) dan hanya diganti
     * setelah ada hasil terminal. Dengan begitu:
     *   - refresh / re-submit dengan halaman yang sama -> key sama -> no-op;
     *   - transaksi yang gagal (tidak ada yang ditulis) -> key tetap, retry aman;
     *   - transaksi yang berhasil -> key di-rotasi agar transaksi berikutnya
     *     dengan nominal sama bukan dianggap duplikat.
     *
     * @see putarOperationKey()
     */
    private function operationKey(string $slot, ?int $unit): string
    {
        $sessionKey = 'kb_op_' . $slot . '_' . ($unit ?? 0);
        $existing   = session()->get($sessionKey);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $key = strtoupper($slot === self::SLOT_SETOR ? 'STR' : 'PNK')
            . '-' . date('ymd') . '-' . bin2hex(random_bytes(8));

        session()->set($sessionKey, $key);

        return $key;
    }

    /**
     * Ganti operation key setelah transaksi selesai, supaya transaksi berikutnya
     * tidak tertahan oleh key lama.
     */
    private function putarOperationKey(string $slot, ?int $unit): void
    {
        session()->remove('kb_op_' . $slot . '_' . ($unit ?? 0));
    }

    // ---------------------------------------------------------------------
    // Helper: pratinjau (read-only, semua angka dari service)
    // ---------------------------------------------------------------------

    /**
     * Pratinjau SETOR TUNAI.
     *
     * Tidak menulis apa pun. Semua angka (saldo kas, saldo bank, posisi unit)
     * dibaca dari KasBankCutoffService supaya angka yang tampil di layar sama
     * persis dengan angka yang nanti dipakai service saat menyimpan.
     *
     * @param  array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function pratinjauSetor(array $input, ?int $unitTerpilih): array
    {
        $svc  = new KasBankCutoffService();
        $unit = $this->unitTransaksiDariForm($input, $unitTerpilih);

        $kas  = $this->AkunModel->find((int) $input['akun_kas_id']);
        $bank = $this->AkunModel->find((int) $input['akun_bank_id']);
        $nominal = (int) $input['nominal'];

        $kasDipakai  = ($kas !== null && (string) $kas->tipe === 'KAS') ? $kas : null;
        $bankDipakai = ($bank !== null && (string) $bank->tipe === 'BANK') ? $bank : null;

        $unitNama = $unit !== null ? $this->namaUnit($unit) : null;

        // Saldo laci kas. Kalau cut-off unit belum ada, saldo TIDAK boleh
        // ditampilkan sebagai 0 — itu akan membuat user mengira lacinya
        // memang kosong.
        $kasCutoffAda = $this->kasCutoffUnit($unit);

        // Baseline laci kas adalah OPENING KAS, bukan statement bank: opening
        // (uang fisik laci) yang Finance input pada tanggal cut-off langsung
        // sah sebagai acuan. Laci kas tidak punya statement dan tidak ada
        // verifikasi.
        //
        // Gate ini harus PERSIS sama dengan yang dipakai
        // KasBankSetorTarikService, kalau tidak request yang sama akan dapat
        // dua jawaban berbeda tergantung lewat mana dia datang.
        $kasOpeningAda = $kasDipakai !== null && $svc->openingTersedia((int) $kasDipakai->idakun_kas_bank);
        $kasSaldo      = ($kasDipakai !== null && $unit !== null) ? $svc->saldoFisik((int) $kasDipakai->idakun_kas_bank) : null;
        $bankTerverifikasi = $bankDipakai !== null && $svc->statementVerified((int) $bankDipakai->idakun_kas_bank);
        $bankSaldo     = $bankDipakai !== null ? $svc->saldoFisik((int) $bankDipakai->idakun_kas_bank) : null;
        $posisiUnit    = ($bankDipakai !== null && $unit !== null)
            ? $svc->posisiUnit((int) $bankDipakai->idakun_kas_bank, $unit)
            : null;

        $blokir  = [];
        $bisaSubmit = true;

        if ($unit === null) {
            $blokir[] = 'Unit transaksi berada di luar cakupan Anda.';
            $bisaSubmit = false;
        }
        if ($kasDipakai === null) {
            $blokir[] = 'Pilih akun kas sumber.';
            $bisaSubmit = false;
        }
        if ($bankDipakai === null) {
            $blokir[] = 'Pilih rekening bank tujuan.';
            $bisaSubmit = false;
        }
        if ($nominal <= 0) {
            $blokir[] = 'Nominal harus lebih dari 0.';
            $bisaSubmit = false;
        }
        if ($kasDipakai !== null && $unit !== null && (int) ($kasDipakai->unit_id ?? 0) !== $unit) {
            $blokir[] = 'Akun kas bukan milik unit yang dipilih.';
            $bisaSubmit = false;
        }

        // Sumber angka saldo laci adalah OPENING KAS (baseline riil fisik laci
        // yang Finance input), sama seperti yang dipakai service. Kalau baris
        // opening-nya belum ada, angkanya belum boleh jadi acuan setor -- dan
        // tidak boleh ditampilkan sebagai 0.
        //
        // `tutup_kasir.akhir_cash` (kasCutoffUnit) hanya cross-check tingkat
        // unit, ditampilkan di banner, BUKAN syarat simpan. Kalau controller
        // memblokir berdasarkan itu sementara service mengizinkan, keduanya
        // akan berbeda jawaban untuk request yang sama.
        if ($kasDipakai !== null && ! $kasOpeningAda) {
            $blokir[] = 'Laci kas unit ini belum punya opening KAS pada tanggal cut-off, jadi saldo laci belum tersedia sebagai acuan setor. Finance harus menetapkan opening (uang fisik laci) dulu.';
            $bisaSubmit = false;
        } elseif ($kasSaldo !== null && $kasSaldo < $nominal && $nominal > 0) {
            $blokir[] = 'Saldo kas unit tidak cukup untuk disetor (tersedia '
                . KasBankCutoffService::rupiah($kasSaldo) . ', diminta ' . KasBankCutoffService::rupiah($nominal) . ').';
            $bisaSubmit = false;
        }

        // Setor ke rekening bank TIDAK butuh statement terverifikasi — uang
        // masuk boleh dicatat sebelum koran bank datang. Yang ditampilkan
        // hanya statusnya, supaya user tahu saldo rekening belum terverifikasi.
        $tanggal = (string) $input['tanggal'];
        if ($tanggal !== '' && $tanggal < FinanceScopeService::kasBankPeriodeMulaiDate()) {
            $blokir[] = 'Tanggal transaksi sebelum periode operasional baru ('
                . FinanceScopeService::kasBankPeriodeMulaiDate() . ').';
            $bisaSubmit = false;
        }

        return [
            'arah'               => 'setor',
            'bisa_submit'        => $bisaSubmit,
            'blokir'             => $blokir,
            'unit_id'            => $unit,
            'unit_nama'          => $unitNama,
            'nominal'            => $nominal,
            'kas'                => [
                'akun_id'      => $kasDipakai !== null ? (int) $kasDipakai->idakun_kas_bank : null,
                'nama'         => $kasDipakai->nama_akun ?? null,
                'opening_ada'  => $kasOpeningAda,
                'saldo'        => $kasOpeningAda ? $kasSaldo : null,
                'cutoff_ada'   => $kasCutoffAda['ada'],
            ],
            'bank'               => [
                'akun_id'     => $bankDipakai !== null ? (int) $bankDipakai->idakun_kas_bank : null,
                'nama'        => $bankDipakai->nama_akun ?? null,
                'norek'       => $bankDipakai->bank_idbank ?? null,
                'is_shared'   => $bankDipakai !== null && (int) ($bankDipakai->is_shared ?? 0) === 1,
                'terverifikasi' => $bankTerverifikasi,
                'saldo'       => $bankTerverifikasi ? $bankSaldo : null,
            ],
            'posisi_unit_sebelum' => $posisiUnit,
            'posisi_unit_setelah' => $posisiUnit !== null ? $posisiUnit + $nominal : null,
            'posisi_unit_bertambah' => true,
        ];
    }

    /**
     * Pratinjau PENARIKAN TUNAI.
     *
     * @param  array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function pratinjauPenarikan(array $input, ?int $unitTerpilih): array
    {
        $svc  = new KasBankCutoffService();
        $unit = $this->unitTransaksiDariForm($input, $unitTerpilih);

        $kas  = $this->AkunModel->find((int) $input['akun_kas_id']);
        $bank = $this->AkunModel->find((int) $input['akun_bank_id']);
        $nominal = (int) $input['nominal'];

        $kasDipakai  = ($kas !== null && (string) $kas->tipe === 'KAS') ? $kas : null;
        $bankDipakai = ($bank !== null && (string) $bank->tipe === 'BANK') ? $bank : null;

        $bankTerverifikasi = $bankDipakai !== null && $svc->statementVerified((int) $bankDipakai->idakun_kas_bank);
        $bankSaldo     = $bankDipakai !== null ? $svc->saldoFisik((int) $bankDipakai->idakun_kas_bank) : null;
        $entitlement   = ($bankDipakai !== null && $unit !== null)
            ? $svc->posisiUnit((int) $bankDipakai->idakun_kas_bank, $unit)
            : null;

        $kasCutoffAda = $this->kasCutoffUnit($unit);
        // Sama seperti setor: gate laci kas adalah opening KAS yang tersedia,
        // bukan statement bank. Laci kas memang tidak punya statement.
        $kasOpeningAdaPenarikan = $kasDipakai !== null
            && $svc->openingTersedia((int) $kasDipakai->idakun_kas_bank);

        $blokir  = [];
        $bisaSubmit = true;

        if ($unit === null) {
            $blokir[] = 'Unit transaksi berada di luar cakupan Anda.';
            $bisaSubmit = false;
        }
        if ($bankDipakai === null) {
            $blokir[] = 'Pilih rekening bank sumber.';
            $bisaSubmit = false;
        }
        if ($kasDipakai === null) {
            $blokir[] = 'Pilih akun kas tujuan.';
            $bisaSubmit = false;
        }
        if ($nominal <= 0) {
            $blokir[] = 'Nominal harus lebih dari 0.';
            $bisaSubmit = false;
        }
        if ($kasDipakai !== null && $unit !== null && (int) ($kasDipakai->unit_id ?? 0) !== $unit) {
            $blokir[] = 'Akun kas bukan milik unit yang dipilih.';
            $bisaSubmit = false;
        }

        // Penarikan BERHUJUNG pada guard entitlement. Alasan ditanyakan ke
        // service yang sama dengan saat menyimpan, supaya tidak mungkin
        // berbeda: kalau service bilang tidak boleh, form ini juga menonaktifkan tombol.
        if ($bankDipakai !== null && $unit !== null && $nominal > 0) {
            $cek = $svc->cekTarikUnit((int) $bankDipakai->idakun_kas_bank, $unit, $nominal);
            if (! $cek['ok']) {
                $blokir[] = $cek['alasan'];
                $bisaSubmit = false;
            }
        }

        // Rekening non-shared milik unit lain tidak punya hak sama sekali.
        if ($bankDipakai !== null && $unit !== null
            && (int) ($bankDipakai->is_shared ?? 0) !== 1
            && (int) ($bankDipakai->unit_id ?? 0) !== $unit
        ) {
            $blokir[] = 'Rekening bank ini bukan milik unit yang dipilih.';
            $bisaSubmit = false;
        }

        // Sama seperti setor: yang menentukan adalah keberadaan opening KAS pada
        // tanggal cut-off. Laci tujuan jadi acuan penarikan, jadi baseline-nya
        // harus sudah ada. `tutup_kasir` tetap hanya cross-check.
        if ($kasDipakai !== null && ! $kasOpeningAdaPenarikan) {
            $blokir[] = 'Laci kas tujuan belum punya opening KAS pada tanggal cut-off, jadi saldo laci belum tersedia sebagai acuan penarikan. Finance harus menetapkan opening dulu.';
            $bisaSubmit = false;
        }

        $tanggal = (string) $input['tanggal'];
        if ($tanggal !== '' && $tanggal < FinanceScopeService::kasBankPeriodeMulaiDate()) {
            $blokir[] = 'Tanggal transaksi sebelum periode operasional baru ('
                . FinanceScopeService::kasBankPeriodeMulaiDate() . ').';
            $bisaSubmit = false;
        }

        return [
            'arah'               => 'penarikan',
            'bisa_submit'        => $bisaSubmit,
            'blokir'             => $blokir,
            'unit_id'            => $unit,
            'unit_nama'          => $unit !== null ? $this->namaUnit($unit) : null,
            'nominal'            => $nominal,
            'kas'                => [
                'akun_id'      => $kasDipakai !== null ? (int) $kasDipakai->idakun_kas_bank : null,
                'nama'         => $kasDipakai->nama_akun ?? null,
                'opening_ada'  => $kasOpeningAdaPenarikan,
                'saldo'        => $kasOpeningAdaPenarikan
                    ? (($kasDipakai !== null && $unit !== null) ? $svc->saldoFisik((int) $kasDipakai->idakun_kas_bank) : null)
                    : null,
                'cutoff_ada'   => $kasCutoffAda['ada'],
            ],
            'bank'               => [
                'akun_id'      => $bankDipakai !== null ? (int) $bankDipakai->idakun_kas_bank : null,
                'nama'         => $bankDipakai->nama_akun ?? null,
                'norek'        => $bankDipakai->bank_idbank ?? null,
                'is_shared'    => $bankDipakai !== null && (int) ($bankDipakai->is_shared ?? 0) === 1,
                'terverifikasi' => $bankTerverifikasi,
                'saldo'        => $bankTerverifikasi ? $bankSaldo : null,
            ],
            'entitlement_sebelum' => $entitlement,
            'entitlement_setelah' => $entitlement !== null ? $entitlement - $nominal : null,
            'entitlement_berkurang' => true,
        ];
    }

    // ---------------------------------------------------------------------
    // Helper: konteks cut-off
    // ---------------------------------------------------------------------

    /**
     * Status cut-off kas untuk sebuah unit.
     *
     * Bedakan "tidak punya akun" dari "punya akun tapi closing belum ada".
     * Keduanya sama-sama memblokir, tapi untuk HO yang belum pernah tutup
     * kasir pesannya harus menyebut cut-off, bukan akun.
     *
     * @return array{ada:bool, saldo:?int, alasan:string}
     */
    private function kasCutoffUnit(?int $unit): array
    {
        if ($unit === null || $unit <= 0) {
            return ['ada' => false, 'saldo' => null, 'alasan' => 'Unit belum ditentukan.'];
        }

        foreach (KasBankCutoffService::querySaldoKasCutoff() as $row) {
            if ((int) $row['unit_id'] === $unit) {
                return [
                    'ada'    => true,
                    'saldo'  => (int) $row['saldo'],
                    'alasan' => '',
                ];
            }
        }

        return [
            'ada'    => false,
            'saldo'  => null,
            'alasan' => 'Belum ada closing kas pada tanggal '
                . FinanceScopeService::kasBankCutoffDate() . ' untuk unit ini.',
        ];
    }

    /**
     * Ringkasan cut-off untuk banner di halaman form.
     *
     * @return array<string, mixed>
     */
    private function infoCutoff(): array
    {
        $cutoff     = FinanceScopeService::kasBankCutoffDate();
        $mulai      = FinanceScopeService::kasBankPeriodeMulaiDate();
        $tanpaUnit  = KasBankCutoffService::unitTanpaClosingCutoff();
        $namaTanpa  = [];

        foreach ($tanpaUnit as $idUnit) {
            $namaTanpa[] = $this->namaUnit((int) $idUnit);
        }

        return [
            'tanggal_cutoff'      => $cutoff,
            'tanggal_mulai'       => $mulai,
            'unit_tanpa_closing'  => $namaTanpa,
        ];
    }

    private function namaUnit(int $unitId): string
    {
        static $cache = [];

        if (! isset($cache[$unitId])) {
            $row = $this->UnitModel->find($unitId);
            $cache[$unitId] = $row !== null ? (string) $row->NAMA_UNIT : ('Unit #' . $unitId);
        }

        return $cache[$unitId];
    }

    // ---------------------------------------------------------------------
    // Helper: daftar transaksi & hasil simpan
    // ---------------------------------------------------------------------

    /**
     * Riwayat Setor / Penarikan Tunai, dikelompokkan per operation.
     *
     * Kedua leg diikat lewat `transfer_ref`. Satu baris tabel = satu
     * operation, dengan kedua sisipan ditunjukkan sebagai "+nama rekening"
     * dan "-nama rekening" supaya user bisa menelusuri keduanya tanpa
     * membuka detail.
     *
     * @return array<int, array<string, mixed>>
     */
    private function transaksiSetorTarik(?int $unitTerpilih): array
    {
        $akunIds = $this->AkunScope->akunIdsTerlihat(
            $this->unitIdsUser(),
            $unitTerpilih,
            true,
            false
        );

        if ($akunIds === []) {
            return [];
        }

        $baris = $this->db->table('transaksi_kas_bank t')
            ->select('t.*, a.nama_akun, a.tipe, a.is_shared, a.bank_idbank, u.NAMA_UNIT')
            ->join('akun_kas_bank a', 'a.idakun_kas_bank = t.akun_kas_bank_id', 'left')
            ->join('unit u', 'u.idunit = t.unit_id', 'left')
            ->whereIn('t.akun_kas_bank_id', $akunIds)
            ->whereIn('t.sumber_tipe', [
                KasBankSetorTarikService::SUMBER_TIPE_SETOR,
                KasBankSetorTarikService::SUMBER_TIPE_TARIK,
            ])
            ->orderBy('t.tanggal', 'DESC')
            ->orderBy('t.idtransaksi', 'DESC')
            ->get()
            ->getResultArray();

        // Kelompokkan per transfer_ref: satu operation = satu baris.
        $groups = [];
        foreach ($baris as $r) {
            $ref = (string) ($r['transfer_ref'] ?? '');
            if ($ref === '') {
                continue;
            }
            if (! isset($groups[$ref])) {
                $groups[$ref] = [
                    'transfer_ref' => $ref,
                    'tanggal'      => (string) $r['tanggal'],
                    'jenis'        => (string) $r['sumber_tipe'],
                    'jumlah'       => (int) $r['jumlah'],
                    'unit_nama'    => (string) ($r['NAMA_UNIT'] ?? ''),
                    'keterangan'   => (string) ($r['keterangan'] ?? ''),
                    'legs'         => [],
                ];
            }
            $groups[$ref]['legs'][] = [
                'arah'      => (string) $r['arah'],
                'nama'      => (string) ($r['nama_akun'] ?? '-'),
                'tipe'      => (string) ($r['tipe'] ?? ''),
                'is_shared' => (int) ($r['is_shared'] ?? 0) === 1,
                'norek'     => (string) ($r['bank_idbank'] ?? ''),
            ];
        }

        return array_values($groups);
    }

    /**
     * Memetakan hasil service ke flash + redirect.
     *
     * PENTING: pesan yang ditampilkan ke user SELALU berasal dari field
     * `alasan` milik service, yang sudah bahasa bisnis. Detail teknis hanya
     * masuk log server. Tidak ada exception yang diteruskan ke layar.
     *
     * @param array{ok:bool, status:string, alasan:string, transfer_ref:string} $hasil
     */
    private function selesaiSetorTarik(array $hasil, string $slot, ?int $unit, string $jenis, array $input): ResponseInterface
    {
        $label = $jenis === self::SLOT_SETOR ? 'Setor Tunai' : 'Penarikan Tunai';
        $url   = base_url('kas_bank/' . ($jenis === self::SLOT_SETOR ? 'setor-tunai' : 'penarikan-tunai'));

        if ($hasil['ok'] && $hasil['status'] === ModeKasBank::STATUS_INSERTED) {
            // Sukses: key di-rotasi supaya transaksi berikutnya dengan nominal
            // sama tidak dianggap duplikat.
            $this->putarOperationKey($slot, $unit);
            session()->setFlashdata(
                'sukses',
                $label . ' berhasil disimpan. Referensi ' . $hasil['transfer_ref'] . '.'
            );

            return redirect()->to($url);
        }

        if ($hasil['ok'] && $hasil['status'] === ModeKasBank::STATUS_SKIPPED) {
            // Sudah pernah diproses dengan key yang sama: tidak ada duplikasi.
            $this->putarOperationKey($slot, $unit);
            session()->setFlashdata(
                'sukses',
                $label . ' ini sudah pernah diproses (referensi ' . $hasil['transfer_ref'] . '). '
                . 'Transaksi tidak digandakan.'
            );

            return redirect()->to($url);
        }

        // Gagal: key TIDAK di-rotasi supaya user bisa memperbaiki lalu retry
        // tanpa membuat operasi kedua.
        // Input asli dikembalikan ke form supaya user tidak perlu mengetik
        // ulang setelah failed. Token yang gagal DIMALAKAN dengan sengaja:
        // form yang dirender ulang selalu mendapat token baru, jadi nilai
        // kosong di sini tidak bisa dipakai mengirim ulang request lama.
        session()->setFlashdata('kb_setor_tarik_input', [
            'unit_id'      => (int) ($input['unit_id'] ?? 0),
            'akun_kas_id'  => (int) ($input['akun_kas_id'] ?? 0),
            'akun_bank_id' => (int) ($input['akun_bank_id'] ?? 0),
            'nominal'      => (int) ($input['nominal'] ?? 0),
            'tanggal'      => (string) ($input['tanggal'] ?? date('Y-m-d')),
            'keterangan'   => (string) ($input['keterangan'] ?? ''),
        ]);
        session()->setFlashdata('gagal', $this->pesanGagalSetorTarik($hasil['alasan'], $jenis));

        return redirect()->to($url);
    }

    /**
     * Pastikan pesan yang sampai ke user selalu bisa ditindaklanjuti.
     *
     * Service sudah mengembalikan bahasa bisnis, tapi tetap ada jaring
     * pengaman: apa pun yang terasa seperti dump teknis dibuang dan diganti
     * kalimat generik, sementara aslinya ditulis ke log.
     */
    private function pesanGagalSetorTarik(string $alasan, string $jenis): string
    {
        $label = $jenis === self::SLOT_SETOR ? 'setor tunai' : 'penarikan tunai';

        $bocor = ['SQLSTATE', 'QueryException', 'Undefined property', 'Undefined index',
            'mysqli_', 'Call to undefined', 'stack trace', '#0 ', 'PDOException'];

        foreach ($bocor as $tanda) {
            if (stripos($alasan, $tanda) !== false) {
                log_message('error', 'Pesan ' . $label . ' keluar sebagai teks teknis: ' . $alasan);

                return 'Terjadi kendala teknis saat menyimpan ' . $label
                    . '. Transaksi tidak tersimpan dan tidak ada saldo yang berubah. '
                    . 'Coba lagi; bila tetap gagal, hubungi administrator.';
            }
        }

        return $alasan !== '' ? $alasan : 'Gagal menyimpan ' . $label . '.';
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
                    . 'Admin Root atau Finance. Jabatan Anda tidak berwenang menarik dana '
                    . 'dari rekening HO — Anda tetap boleh mentransfer DANA KE rekening ini.';
            }

            // Sebagai TUJUAN, syaratnya bukan "unit ada di cakupan user" tapi
            // "unit punya rekening operasional sendiri". Bedakan keduanya,
            // karena sebabnya berbeda dan user perlu tahu yang mana.
            if (! $this->AkunScope->userBolehUnit($unitId)) {
                return 'Rekening Finance/HO "' . $nama . '" tidak dapat dipakai: unit ' . $unitId
                    . ' berada di luar cakupan Anda.';
            }

            return 'Rekening Finance/HO "' . $nama . '" tidak dapat dipakai untuk unit ' . $unitId
                . ': unit ini belum punya rekening bank operasional sendiri. '
                . 'Hubungi Finance untuk memverifikasi rekening unit tersebut — '
                . 'rekening HO bukan pengganti hak akses.';
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

        // ---- TANGGAL: hanya dalam periode operasional ----
        // Hanya batas BAWAH, sengaja tidak ada batas atas "tanggal <= hari
        // ini". Alasannya konvensi, bukan kelalaian: KasBankSetorTarikService
        // (implementasi referensi Setor/Tarik) juga hanya menolak tanggal
        // sebelum periode mulai. Menambah batas atas di sini hanya di sini
        // akan membuat Pindah Saldo jadi satu-satunya mutasi yang terkunci,
        // dan karena `kasBankPeriodeMulaiDate()` = cutoff + 1 hari, seluruh fitur
        // mati selama periode baru belum dibuka.
        $tanggal = FinanceScopeService::tanggalStr($tanggal);
        $mulai   = FinanceScopeService::kasBankPeriodeMulaiDate();

        if ($tanggal < $mulai) {
            return $this->gagal('Tanggal ' . $tanggal . ' berada sebelum periode operasional baru (' . $mulai . '). '
                . 'Mutasi sebelum cut-off tidak boleh masuk ledger periode baru.');
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
// Finance/HO sebagai pengirim -> hanya ROOT / Finance.
        //   Finance/HO sebagai penerima   -> unit yang punya rekening
        //   operasional sendiri (bukan semua unit).
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

        // ---- GUARD SALDO & BASELINE ----
        // `canUseAsSource()` hanya membuktikan unit punya AKSES ke rekening,
        // bukan bahwa saldonya cukup miliknya. Di rekening shared keduanya
        // berbeda, jadi tanpa cek ini unit bisa mengirim>dari posisi unitnya.
        //
        // Catatan skenario: kalau rekening pengirim == rekening penerima, tidak
        // ada gerakan kas/bank sama sekali (atribusi internal), jadi cek saldo
        // per unit TIDAK relevan di sana dan hanya rekening pengirim yang dipakai.
        $rekeningDipakai = $akunKirimId === $akunTerimaId
            ? [$akunKirim]
            : [$akunKirim, $akunTerima];

        $cutoff = new KasBankCutoffService();
        $policy = new \App\Services\Finance\EntitlementPolicyService();

        foreach ($rekeningDipakai as $akun) {
            if ($policy->wajibStatementVerifikasi((int) $akun->idakun_kas_bank)
                && ! $cutoff->statementVerified((int) $akun->idakun_kas_bank, $tanggal)
            ) {
                return $this->gagal('Rekening "' . $akun->nama_akun . '" belum punya statement yang '
                    . 'diverifikasi Finance pada ' . $cutoff->tanggalCutoff() . '. '
                    . 'Isi dan verifikasi statement di menu "Rekening & Saldo Awal" dulu.');
            }
        }

        if ($akunKirimId !== $akunTerimaId) {
            $cek = $cutoff->cekTarikUnit($akunKirimId, (int) $hp->unit_id, $jumlah);
            if (! $cek['ok']) {
                return $this->gagal($cek['alasan']);
            }
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