<?php

namespace App\Services\Finance;

use App\Libraries\ModeKasBank;
use App\Models\ModelAkunKasBank;
use Config\Database;

/**
 * Movement Kas & Bank per rekening, dihitung dari TABEL SUMBER.
 *
 * Menggantikan pembacaan `transaksi_kas_bank` untuk keperluan Finance.
 * Definisi nominalnya tetap memakai TutupKasirSourceDefinition (source of truth
 * TutupKasir.php) — kelas ini hanya menambahkan DIMENSI REKENING yang tidak
 * ada di definisi TutupKasir.
 *
 * ------------------------------------------------------------------
 * MASALAH DIMENSI YANG INI SELESAIKAN
 * ------------------------------------------------------------------
 * TutupKasir menghitung per UNIT (cash / transfer). Finance menyimpan saldo
 * per REKENING (`akun_kas_bank`). Dua tabel sumber tidak punya FK bank yang
 * bisa dipakai:
 *
 *   penjualan.bank_idbank  -> berisi KODE PEMBAYARAN ("PJL20260929"),
 *                             bukan idbank. Verified terhadap data.
 *   service.bayar_bank     -> berisi KODE PEMBAYARAN ("ksr2026092446872"),
 *                             bukan idbank. Verified terhadap data.
 *
 * Yang MEMILIKI idbank asli: `kas_keluar.idbank` dan `kas_masuk.idbank`.
 *
 * Keputusan: transfer masuk (penjualan.bayar_bank + residual service)
 * dialokasikan ke REKENING DEFAULT UNIT — rekening bank aktif milik unit
 * itu. Lihat rekeningDefaultUnit(). Angka ini adalah ASUMSI yang disepakati,
 * bukan fakta sumber, dan sengaja dikembalikan terpisah lewat
 * movementTeralokasi() supaya bisa diaudit.
 *
 * Yang TIDAK diasumsikan: transfer KELUAR selalu bisa dipertahankan karena
 * `kas_keluar.idbank` memang idbank asli.
 */
class KasBankSourceMovement
{
    protected $db;
    protected ModelAkunKasBank $akunModel;

    /** Cache resolver per unit, supaya tidak query berulang. */
    private array $cacheDefault = [];

    public function __construct($db = null, ?ModelAkunKasBank $akun = null)
    {
        $this->db        = $db ?? Database::connect();
        $this->akunModel = $akun ?? new ModelAkunKasBank();
    }

    // =================================================================
    // RESOLVER REKENING
    // =================================================================

    /** @return object|null baris akun_kas_bank */
    public function akun(int $akunId)
    {
        return $this->akunModel->find($akunId);
    }

    /**
     * Rekening bank default untuk transfer masuk unit.
     *
     * Rantai resolusi (berurutan, pertama yang ketemu menang):
     *   1. BANK aktif, milik unit itu sendiri, tidak shared.
     *   2. BANK aktif shared yang unit-nya punya baris alokasi di
     *      `alokasi_saldo_kas_bank` (mis. rekening 12 untuk unit 1 & 2).
     *   3. null -> TIDAK dialokasikan; nominalnya dilaporkan terpisah lewat
     *      transferMasukTakTeralokasi().
     *
     * Rekening `is_finance_ho = 1` SENGAJA TIDAK dipakai. `entitledUnitIds()`
     * mengembalikan array kosong untuk rekening itu, jadi saldo yang
     * diletakkan di sana tidak akan pernah muncul di posisi unit mana pun
     * dan invariant `saldo_fisik == LEGACY + SUM(posisiUnit)` pasti pecah.
     * Rekening HO bukan tempat movement unit.
     *
     * @return int|null idakun_kas_bank
     */
    public function rekeningDefaultUnit(int $unitId): ?int
    {
        if ($unitId <= 0) {
            return null;
        }

        if (array_key_exists($unitId, $this->cacheDefault)) {
            return $this->cacheDefault[$unitId];
        }

        $row = $this->db->table('akun_kas_bank')
            ->select('idakun_kas_bank')
            ->where('tipe', TutupKasirSourceDefinition::TIPE_BANK)
            ->where('status', 'aktif')
            ->where('is_finance_ho', 0)
            ->where('unit_id', $unitId)
            ->where('is_shared', 0)
            ->orderBy('idakun_kas_bank', 'ASC')
            ->get()
            ->getRow();

        if ($row === null) {
            $row = $this->db->table('akun_kas_bank a')
                ->select('a.idakun_kas_bank')
                ->join('alokasi_saldo_kas_bank al', 'al.akun_kas_bank_id = a.idakun_kas_bank')
                ->where('a.tipe', TutupKasirSourceDefinition::TIPE_BANK)
                ->where('a.status', 'aktif')
                ->where('a.is_finance_ho', 0)
                ->where('a.is_shared', 1)
                ->where('al.unit_id', $unitId)
                ->groupBy('a.idakun_kas_bank')
                ->orderBy('a.idakun_kas_bank', 'ASC')
                ->get()
                ->getRow();
        }

        return $this->cacheDefault[$unitId] = $row === null
            ? null
            : (int) $row->idakun_kas_bank;
    }

    /**
     * Unit-unit yang transfer masuk-nya dialokasikan ke rekening ini.
     *
     * @return int[]
     */
    public function unitDialokasikanKe(int $akunId): array
    {
        if ($this->akun($akunId) === null) {
            return [];
        }

        $semua = $this->db->table('unit')->select('idunit')->get()->getResultArray();
        $out   = [];

        foreach ($semua as $u) {
            $uid = (int) $u['idunit'];
            if ($this->rekeningDefaultUnit($uid) === $akunId) {
                $out[] = $uid;
            }
        }

        return $out;
    }

    // =================================================================
    // MOVEMENT PER REKENING
    // =================================================================

    /**
     * Net movement satu rekening dalam rentang [$dari, $sampai].
     *
     * Masuk - keluar, dalam rupiah.
     *
     * BATAS ATAS ($sampai) sengaja opsional: pemanggil lama memakai
     * panggilan tanpa batas atas dan maknanya tidak berubah — movement dari
     * cutoff sampai sekarang. Parameter ini hanya untuk pemanggil yang
     * membutuhkan saldo buku pada tanggal tertentu, misalnya "alur KAS
     * sampai 7 Okt", supaya transaksi 8 Okt tidak ikut terhitung. Tanpa
     * batas atas, angka untuk 7 Okt ikut naik setiap ada transaksi 8 Okt.
     *
     * @param int|null    $unitId null = seluruh unit yang punya hak atas rekening ini
     * @param string|null $sampai batas atas TANGGAL (YYYY-MM-DD), null = tanpa batas atas
     */
    public function netMovement(int $akunId, ?int $unitId = null, ?string $dari = null, ?string $sampai = null): int
    {
        $akun = $this->akun($akunId);

        if ($akun === null) {
            return 0;
        }

        $dari = $dari ?? FinanceScopeService::kasBankPeriodeMulaiDate();
        $tipe = strtoupper((string) $akun->tipe);

        // Batas atas yang lebih kecil dari batas bawah berarti tidak ada
        // transaksi sama sekali (mis. diminta tanggal sebelum cutoff).
        if ($sampai !== null && $sampai < $dari) {
            return 0;
        }

        // Lihat movementInternalUnion(): ini satu-satunya bagian angka yang
        // masih membaca transaksi_kas_bank, dan hanya untuk perpindahan uang
        // antar rekening fisik (Setor/Tarik, Pindah Saldo, dan pelunasan
        // H/P antar unit yang menyentuh rekening berbeda).
        $internal = $this->movementInternalUnion($akunId, $unitId, $dari, $sampai);

        if ($tipe === TutupKasirSourceDefinition::TIPE_KAS) {
            return $this->movementKas($akun, $unitId, $dari, $sampai) + $internal;
        }

        return $this->movementBank($akun, $unitId, $dari, $sampai) + $internal;
    }

    // =================================================================
    // PERPINDAHAN UANG ANTAR REKENING — DUA MAKNA, DUA METHOD
    // =================================================================
    //
    // Basis pemisahan: `transfer_ref IS NOT NULL` (semua perpindahan uang
    // antar rekening fisik yang kita catat sendiri) vs
    // `jenis = TRANSFER_INTERNAL` (hanya Setor/Tarik/Pindah Saldo, yaitu
    // perpindahan yang TIDAK ikut memindahkan kepemilikan unit).
    //
    // Kenapa keduanya harus bernama dan tidak boleh jadi satu method dengan
    // parameter opsional:
    //
    //   - Finance menghitung POSISI REKENING secara utuh. Di sana arus
    //     `PEMBAYARAN_ANTAR_UNIT` juga benar-benar memindahkan uang antar
    //     rekening fisik, jadi HARUS ikut terhitung.
    //   - Tutup Kasir & Rekon menghitung SALDO LACI. Pelunasan H/P antar unit
    //     yang menyentuh laci BUKAN Setor/Tarik laci, jadi TIDAK boleh ikut.
    //
    // Dulu kedua makna ini berbagi `transferInternal(..., ?string $jenis)`
    // dengan default null, sehingga nama "transfer internal" berarti dua
    // hal berbeda tergantung pemanggilnya — dan DailyCashFlowService
    // menduplikasi literal-nya sendiri. Sekarang maknanya dipisah di method
    // terpisah; `internalLedger()` di bawah tetap query builder tunggal,
    // jadi definisi SQL hanya ada di satu tempat.

    /**
     * SEMUA perpindahan uang antar rekening fisik — basis `movementInternalUnion()`.
     *
     * Mencakup TRANSFER_INTERNAL (Setor/Tarik + Pindah Saldo) DAN
     * `PEMBAYARAN_ANTAR_UNIT` yang menyentuh rekening fisik berbeda, karena
     * keduanya benar-benar memindahkan uang antar rekening kita.
     *
     * TIDAK termasuk: baris `sumber_tipe = kas_keluar / kas_masuk` yang ada
     * di ledger sebagai CERMINAN tabel source (sudah dihitung lewat
     * movementKas()/movementBank() — menghitung dua kali = double count),
     * dan legacy dump tanpa `transfer_ref` (otomatis tertinggal).
     *
     * @return array{masuk:int, keluar:int}
     */
    public function movementInternalUnionRincian(
        int $akunId,
        ?int $unitId,
        string $dari,
        ?string $sampai = null
    ): array {
        return $this->internalLedger($akunId, $unitId, $dari, $sampai, null);
    }

    /**
     * Net `movementInternalUnionRincian()` (MASUK - KELUAR).
     */
    public function movementInternalUnion(
        int $akunId,
        ?int $unitId,
        string $dari,
        ?string $sampai = null
    ): int {
        $t = $this->movementInternalUnionRincian($akunId, $unitId, $dari, $sampai);

        return $t['masuk'] - $t['keluar'];
    }

    /**
     * HANYA Setor/Tarik (dan Pindah Saldo) — `jenis = TRANSFER_INTERNAL`.
     *
     * Ini method yang dipakai Tutup Kasir & Rekon. `PEMBAYARAN_ANTAR_UNIT`
     * SENGAJA dikecualikan: pelunasan hutang/piutang antar unit bukan
     * Setor/Tarik laci, dan kalau ikut dihitung akan mengurangi saldo laci
     * dengan angka yang bukan setoran.
     *
     * @return array{masuk:int, keluar:int}
     */
    public function movementSetorTarikRincian(
        int $akunId,
        ?int $unitId,
        string $dari,
        ?string $sampai = null
    ): array {
        return $this->internalLedger(
            $akunId,
            $unitId,
            $dari,
            $sampai,
            ModeKasBank::JENIS_TRANSFER
        );
    }

    /**
     * Net `movementSetorTarikRincian()`.
     */
    public function movementSetorTarik(
        int $akunId,
        ?int $unitId,
        string $dari,
        ?string $sampai = null
    ): int {
        $t = $this->movementSetorTarikRincian($akunId, $unitId, $dari, $sampai);

        return $t['masuk'] - $t['keluar'];
    }

    /**
     * Query builder tunggal untuk seluruh pembacaan `transaksi_kas_bank`.
     *
     * Pivate dengan sengaja: pemanggil TIDAK boleh memilih `$jenis` sendiri.
     * Akses dibatasi ke dua makna yang sudah bernama di atas lewat
     * movementInternalUnionRincian() / movementSetorTarikRincian(), supaya
     * tidak ada caller yang bisa diam-diam memakai definisi ketiga.
     *
     * @param string|null $jenis null = union (tanpa filter jenis)
     */
    private function internalLedger(
        int $akunId,
        ?int $unitId,
        string $dari,
        ?string $sampai = null,
        ?string $jenis = null
    ): array {
        $db = $this->db;
        $b  = $db->table('transaksi_kas_bank')
            ->select('COALESCE(SUM(CASE WHEN arah = \'MASUK\' THEN jumlah ELSE 0 END), 0) as masuk')
            ->select('COALESCE(SUM(CASE WHEN arah = \'KELUAR\' THEN jumlah ELSE 0 END), 0) as keluar', false)
            ->where('akun_kas_bank_id', $akunId)
            ->where('tanggal >=', $dari);

        self::scopeInternalUnion($b);

        if ($jenis !== null && $jenis !== '') {
            $b->where('jenis', $jenis);
        }

        // Kolom `tanggal` di ledger bertipe DATE, bukan DATETIME, jadi `<=`
        // sudah mencakup seluruh hari tersebut tanpa perlu `23:59:59`.
        if ($sampai !== null) {
            $b->where('tanggal <=', $sampai);
        }

        if ($unitId !== null) {
            $b->where('unit_id', $unitId);
        }

        $row = $b->get()->getRow();

        return [
            'masuk'  => (int) ($row->masuk ?? 0),
            'keluar' => (int) ($row->keluar ?? 0),
        ];
    }

    /**
     * Predikat SQL "perpindahan uang antar rekening fisik yang kita catat
     * sendiri": `transfer_ref IS NOT NULL`.
     *
     * Dipisah sebagai helper supaya definisinya hanya ada di SATU tempat.
     * `DailyCashFlowService` memakainya untuk query drill-down baris-per-baris
     * (yang butuh detail rekening untuk ditampilkan, bukan hasil SUM), dan
     * `internalLedger()` memakainya untuk agregat. Menulis memakai literal
     * `transfer_ref IS NOT NULL` di kedua tempat persislah kondisi yang
     * membuat Finance dan Cash Flow diam-diam berbeda saat definisinya
     * berubah.
     *
     * @param object $builder query builder CodeIgniter
     * @return object builder yang sama
     */
    public static function scopeInternalUnion($builder)
    {
        return $builder->where('transfer_ref IS NOT NULL', null, false);
    }

    /**
     * KAS: cash masuk dari penjualan + service (filter TutupKasir), cash
     * keluar dari kas_keluar idbank IS NULL. Tidak ada asumsi apa pun —
     * `idbank IS NULL` persis sama dengan definisi TutupKasir.
     */
    private function movementKas(object $akun, ?int $unitId, string $dari, ?string $sampai = null): int
    {
        // KAS account always milik satu unit.
        $unit = (int) ($akun->unit_id ?? 0);

        if ($unitId !== null && $unitId !== $unit) {
            return 0;
        }

        if ($unit <= 0) {
            return 0;
        }

        $masuk = $this->cashMasuk($unit, $dari, $sampai);

        $qKeluar = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 's')
            ->where('DATE(tanggal) >=', $dari)
            ->where('idunit', $unit)
            ->where('idbank', null);

        if ($sampai !== null) {
            $qKeluar->where('DATE(tanggal) <=', $sampai);
        }

        $keluar = (int) ($qKeluar->get()->getRow()->s ?? 0);

        return $masuk - $keluar;
    }

    /**
     * BANK: transfer keluar dari kas_keluar.idbank (idbank asli, jadi pasti),
     * transfer masuk dari penjualan + service yang DEFAULT-nya rekening ini.
     */
    private function movementBank(object $akun, ?int $unitId, string $dari, ?string $sampai = null): int
    {
        $bankId = $akun->bank_idbank;

        // Rekening BANK tanpa idbank tidak punya dimensi bank sama sekali.
        // Kalau diteruskan, `where('idbank', null)` akan menjadi `IS NULL` dan
        // justru menarik SELURUH kas_keluar CASH unit itu. Jadi transfer keluar
        // tidak bisa diatribusikan dan dihitung 0, bukan mencuri angka kas.
        if ($bankId === null || $bankId === '') {
            return 0;
        }

        // --- KELUAR: kas_keluar dengan idbank = bank rekening ini ---
        $qKeluar = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 's')
            ->where('DATE(tanggal) >=', $dari)
            ->where('idbank', $bankId);

        if ($sampai !== null) {
            $qKeluar->where('DATE(tanggal) <=', $sampai);
        }

        if ($unitId !== null) {
            $qKeluar->where('idunit', $unitId);
        }

        $keluar = (int) ($qKeluar->get()->getRow()->s ?? 0);

        // --- MASUK: unit-unit yang transfer-nya ke rekening ini ---
        $units = $unitId !== null
            ? [$unitId]
            : $this->unitDialokasikanKe((int) $akun->idakun_kas_bank);

        $masuk = 0;
        foreach ($units as $u) {
            if ($this->rekeningDefaultUnit($u) !== (int) $akun->idakun_kas_bank) {
                continue;
            }
            $masuk += $this->transferMasuk($u, $dari, $sampai);
        }

        return $masuk - $keluar;
    }

    // =================================================================
    // NOMINAL — definisi identik TutupKasir, tanpa dimensi rekening
    // =================================================================

    /**
     * SUM(bayar_tunai) penjualan + service, filter Tutup Kasir, rentang
     * [$dari, $sampai].
     *
     * Kolom tanggal dan filter status PERSIS sama dengan
     * TutupKasirSourceDefinition (penjualan `tanggal`, service
     * `tanggal_selesai` + `status_service = 4`) supaya angka Cash In di
     * sini tidak pernah berbeda dari Tutup Kasir.
     */
    private function cashMasuk(int $unitId, string $dari, ?string $sampai = null): int
    {
        $p = $this->db->table('penjualan')
            ->selectSum('bayar_tunai', 's')
            ->where('DATE(tanggal) >=', $dari)
            ->where('unit_idunit', $unitId)
            ->notLike('kode_invoice', 'srv', 'after');

        if ($sampai !== null) {
            $p->where('DATE(tanggal) <=', $sampai);
        }

        $s = $this->db->table('service')
            ->selectSum('bayar_tunai', 's')
            ->where('DATE(tanggal_selesai) >=', $dari)
            ->where('status_service', 4)
            ->where('unit_idunit', $unitId);

        if ($sampai !== null) {
            $s->where('DATE(tanggal_selesai) <=', $sampai);
        }

        return (int) ($p->get()->getRow()->s ?? 0) + (int) ($s->get()->getRow()->s ?? 0);
    }

    /** SUM(bayar_bank) penjualan + residual service, rentang [$dari, $sampai]. */
    private function transferMasuk(int $unitId, string $dari, ?string $sampai = null): int
    {
        $p = $this->db->table('penjualan')
            ->selectSum('bayar_bank', 's')
            ->where('DATE(tanggal) >=', $dari)
            ->where('unit_idunit', $unitId)
            ->notLike('kode_invoice', 'srv', 'after');

        if ($sampai !== null) {
            $p->where('DATE(tanggal) <=', $sampai);
        }

        $s = $this->db->table('service')
            ->select('COALESCE(SUM(COALESCE(harus_dibayar,0) - COALESCE(bayar_tunai,0)),0) AS s')
            ->where('DATE(tanggal_selesai) >=', $dari)
            ->where('status_service', 4)
            ->where('unit_idunit', $unitId);

        if ($sampai !== null) {
            $s->where('DATE(tanggal_selesai) <=', $sampai);
        }

        return (int) ($p->get()->getRow()->s ?? 0) + (int) ($s->get()->getRow()->s ?? 0);
    }

    // =================================================================
    // AUDIT: transfer masuk yang TIDAK bisa dialokasikan
    // =================================================================

    /**
     * Transfer masuk per unit yang TIDAK punya rekening default.
     *
     * Nominal ini tetap bagian dari movement unit, tapi tidak masuk saldo
     * rekening mana pun. Sengaja dikembalikan agar tidak hilang diam-diam.
     *
     * @return array<int,array{unit:int,nominal:int}>
     */
    public function transferMasukTakTeralokasi(string $dari): array
    {
        $dari = $dari ?? FinanceScopeService::kasBankPeriodeMulaiDate();
        $out  = [];

        foreach ($this->db->table('unit')->select('idunit')->get()->getResultArray() as $u) {
            $uid = (int) $u['idunit'];

            if ($this->rekeningDefaultUnit($uid) !== null) {
                continue;
            }

            $nominal = $this->transferMasuk($uid, $dari);

            if ($nominal > 0) {
                $out[] = ['unit' => $uid, 'nominal' => $nominal];
            }
        }

        return $out;
    }

    // =================================================================
    // RINGKASAN ALUR KAS SAMPAI TANGGAL TERTENTU
    // =================================================================

    /**
     * Movement satu rekening KAS dipecah per komponen, dalam rentang
     * [$dari, $sampai].
     *
     * Dipisah-pisah supaya UI bisa menampilkan alurnya (Cash In / Cash Out /
     * Setor / Tarik) alih-alih satu angka movement bersih. Nominalnya memakai
     * sumber yang sama persis dengan netMovement() — ini memecah angka, bukan
     * menghitung ulang dengan definisi lain.
     *
     * Opening TIDAK termasuk di sini. Opening adalah baseline, bukan
     * transaksi, dan tidak pernah muncul sebagai movement.
     *
     * `$jenisTransfer` — DEPRECATED, hanya untuk backward compatibility.
     *
     * Nilai yang diterima HANYA `ModeKasBank::JENIS_TRANSFER`, yang berarti
     * komponen transfer dihitung dengan definisi Setor/Tarik
     * (`movementSetorTarikRincian()`). Nilai lain — termasuk null — berarti
     * union (`movementInternalUnionRincian()`).
     *
     * Parameter ini tidak pernah dipakai untuk pertanyaan "berapa movement untuk
     * jenis/free-form apa pun". Itu sebabnya ia tidak lagi jadi parameter
     * publik yang bebas: makna transfer dipisah ke dua method bernama,
     * `movementSetorTarikRincian()` dan `movementInternalUnionRincian()`.
     * Pemanggil baru WAJIB memakai salah satu dari keduanya secara langsung.
     *
     * Diperlukan hanya untuk satu caller yang kontraknya dibekukan pada fase
     * ini: `TutupKasirTransferInternal::hariIni()` yang meneruskan
     * `ModeKasBank::JENIS_TRANSFER` sebagai argumen ke-5. Angka Tutup Kasir
     * TIDAK berubah: dispatch ke movementSetorTarikRincian() menghasilkan
     * query yang identik dengan yang sebelumnya dibangun oleh
     * `transferInternal(..., TRANSFER_INTERNAL)`.
     *
     * @return array{cash_in:int,cash_out:int,transfer_masuk:int,transfer_keluar:int,net:int}
     */
    public function rincianMovement(
        int $akunId,
        ?int $unitId = null,
        ?string $dari = null,
        ?string $sampai = null,
        ?string $jenisTransfer = null
    ): array {
        $akun = $this->akun($akunId);

        $nol = [
            'cash_in' => 0, 'cash_out' => 0,
            'transfer_masuk' => 0, 'transfer_keluar' => 0, 'net' => 0,
        ];

        if ($akun === null) {
            return $nol;
        }

        $dari = $dari ?? FinanceScopeService::kasBankPeriodeMulaiDate();

        if ($sampai !== null && $sampai < $dari) {
            return $nol;
        }

        // Cash In / Cash Out hanya KAS. Rekening BANK memakai transfer, jadi
        // komponennya sudah tercakup oleh transfer_masuk / transfer_keluar.
        $cashIn  = 0;
        $cashOut = 0;

        if (strtoupper((string) $akun->tipe) === TutupKasirSourceDefinition::TIPE_KAS) {
            $unit = (int) ($akun->unit_id ?? 0);

            if ($unit > 0 && ($unitId === null || $unitId === $unit)) {
                $cashIn  = $this->cashMasuk($unit, $dari, $sampai);
                $cashOut = $this->cashOutKas($unit, $dari, $sampai);
            }
        }

        // Dispatch ke dua makna yang sudah bernama. `$jenisTransfer` hanya
        // diwariskan untuk satu caller yang dibekukan kontraknya
        // (TutupKasirTransferInternal, yang tidak boleh diubah pada fase ini)
        // dan campernya secara eksplisit ModeKasBank::JENIS_TRANSFER.
        // Setiap pemanggil baru WAJIB memanggil movementSetorTarikRincian()
        // atau movementInternalUnionRincian() secara langsung.
        $transfer = $jenisTransfer === ModeKasBank::JENIS_TRANSFER
            ? $this->movementSetorTarikRincian($akunId, $unitId, $dari, $sampai)
            : $this->movementInternalUnionRincian($akunId, $unitId, $dari, $sampai);

        $transferMasuk      = $transfer['masuk'];
        $transferKeluar     = $transfer['keluar'];

        return [
            'cash_in'         => $cashIn,
            'cash_out'        => $cashOut,
            'transfer_masuk'  => $transferMasuk,
            'transfer_keluar' => $transferKeluar,
            'net'             => $cashIn - $cashOut + $transferMasuk - $transferKeluar,
        ];
    }

    /** SUM(jumlah) kas_keluar tunai (idbank IS NULL) pada rentang [$dari, $sampai]. */
    private function cashOutKas(int $unitId, string $dari, ?string $sampai = null): int
    {
        $b = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 's')
            ->where('DATE(tanggal) >=', $dari)
            ->where('idunit', $unitId)
            ->where('idbank', null);

        if ($sampai !== null) {
            $b->where('DATE(tanggal) <=', $sampai);
        }

        return (int) ($b->get()->getRow()->s ?? 0);
    }

    /**
     * Ringkasan alokasi default per unit, untuk ditampilkan di Finance.
     *
     * @return array<int,array{unit:int,rekening:?int,sumber:string}>
     */
    public function petaAlokasiDefault(): array
    {
        $out = [];

        foreach ($this->db->table('unit')->select('idunit')->get()->getResultArray() as $u) {
            $uid  = (int) $u['idunit'];
            $akun = $this->rekeningDefaultUnit($uid);
            $row  = $akun === null ? null : $this->akun($akun);

            $out[] = [
                'unit'     => $uid,
                'rekening' => $akun,
                'sumber'   => $akun === null
                    ? 'TIDAK TERALOKASI'
                    : ($row->is_shared ? 'bank shared' : 'bank unit'),
            ];
        }

        return $out;
    }
}