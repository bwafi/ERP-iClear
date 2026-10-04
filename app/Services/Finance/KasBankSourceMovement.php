<?php

namespace App\Services\Finance;

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
     * Net movement satu rekening sejak $dari (default: periode aktif).
     *
     * Masuk - keluar, dalam rupiah, sudah INCLUDING transaksi 1-5 Okt bila
     * $dari diberikan lebih awal.
     *
     * @param int|null $unitId null = seluruh unit yang condemnasi ke rekening ini
     */
    public function netMovement(int $akunId, ?int $unitId = null, ?string $dari = null): int
    {
        $akun = $this->akun($akunId);

        if ($akun === null) {
            return 0;
        }

        $dari = $dari ?? FinanceScopeService::periodeMulaiDate();
        $tipe = strtoupper((string) $akun->tipe);

        // Lihat movementTransferInternal(): ini satu-satunya bagian angka yang
        // masih membaca transaksi_kas_bank, dan hanya untuk transfer internal.
        $internal = $this->movementTransferInternal($akunId, $unitId, $dari);

        if ($tipe === TutupKasirSourceDefinition::TIPE_KAS) {
            return $this->movementKas($akun, $unitId, $dari) + $internal;
        }

        return $this->movementBank($akun, $unitId, $dari) + $internal;
    }

    /**
     * Transfer internal KAS <-> BANK (Setor / Tarik).
     *
     * KENAPA BACA LEDGER: Setor/Tarik adalah perpindahan saldo antara dua
     * rekening milik kita sendiri. Itu BUKAN pengeluaran dan BUKAN pendapatan,
     * jadi tidak ada baris di penjualan / service / kas_keluar yang bisa
     * merepresentasikannya — dan tidak boleh dibuatkan, karena akan mengotori
     * `pengeluaran` TutupKasir dan merusak parity.
     *
     * `KasBankSetorTarikService` hanya menulis ke `transaksi_kas_bank`
     * (0 referensi ke kas_keluar/kas_masuk — sudah diverifikasi). Jadi ledger
     * di sini bukan "sumber kebenaran ganda", melainkan SATU-SATUNYA tempat
     * transfer internal terekam.
     *
     * FILTER: hanya `transfer_ref IS NOT NULL`. Baris `sumber_tipe =
     * kas_keluar` / `kas_masuk` yang ada di ledger adalah CERMINAN tabel
     * source dan sudah dihitung lewat movementKas()/movementBank() — menghitung
     * dua kali akan jadi double count. Legacy dump tanpa transfer_ref karena
     * itu juga otomatis tertinggal.
     *
     * Sisi KAS tidak perlu penyesuaian: `arah` sudah benar — setor = KAS
     * KELUAR, tarik = KAS MASUK.
     */
    private function movementTransferInternal(int $akunId, ?int $unitId, string $dari): int
    {
        $db = $this->db;
        $b  = $db->table('transaksi_kas_bank')
            ->select('COALESCE(SUM(CASE WHEN arah = \'MASUK\' THEN jumlah ELSE 0 END), 0) as masuk')
            ->select('COALESCE(SUM(CASE WHEN arah = \'KELUAR\' THEN jumlah ELSE 0 END), 0) as keluar', false)
            ->where('akun_kas_bank_id', $akunId)
            ->where('transfer_ref IS NOT NULL', null, false)
            ->where('tanggal >=', $dari);

        if ($unitId !== null) {
            $b->where('unit_id', $unitId);
        }

        $row = $b->get()->getRow();

        return (int) ($row->masuk ?? 0) - (int) ($row->keluar ?? 0);
    }

    /**
     * KAS: cash masuk dari penjualan + service (filter TutupKasir), cash
     * keluar dari kas_keluar idbank IS NULL. Tidak ada asumsi apa pun —
     * `idbank IS NULL` persis sama dengan definisi TutupKasir.
     */
    private function movementKas(object $akun, ?int $unitId, string $dari): int
    {
        // KAS account always milik satu unit.
        $unit = (int) ($akun->unit_id ?? 0);

        if ($unitId !== null && $unitId !== $unit) {
            return 0;
        }

        if ($unit <= 0) {
            return 0;
        }

        $masuk = $this->cashMasuk($unit, $dari);
        $keluar = (int) ($this->db->table('kas_keluar')
            ->selectSum('jumlah', 's')
            ->where('DATE(tanggal) >=', $dari)
            ->where('idunit', $unit)
            ->where('idbank', null)
            ->get()
            ->getRow()->s ?? 0);

        return $masuk - $keluar;
    }

    /**
     * BANK: transfer keluar dari kas_keluar.idbank (idbank asli, jadi pasti),
     * transfer masuk dari penjualan + service yang DEFAULT-nya rekening ini.
     */
    private function movementBank(object $akun, ?int $unitId, string $dari): int
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
            $masuk += $this->transferMasuk($u, $dari);
        }

        return $masuk - $keluar;
    }

    // =================================================================
    // NOMINAL — definisi identik TutupKasir, tanpa dimensi rekening
    // =================================================================

    /** SUM(bayar_tunai) penjualan + service, filter TutupKasir, sejak $dari. */
    private function cashMasuk(int $unitId, string $dari): int
    {
        $p = (int) ($this->db->table('penjualan')
            ->selectSum('bayar_tunai', 's')
            ->where('DATE(tanggal) >=', $dari)
            ->where('unit_idunit', $unitId)
            ->notLike('kode_invoice', 'srv', 'after')
            ->get()
            ->getRow()->s ?? 0);

        $s = (int) ($this->db->table('service')
            ->selectSum('bayar_tunai', 's')
            ->where('DATE(tanggal_selesai) >=', $dari)
            ->where('status_service', 4)
            ->where('unit_idunit', $unitId)
            ->get()
            ->getRow()->s ?? 0);

        return $p + $s;
    }

    /** SUM(bayar_bank) penjualan + residual service, sejak $dari. */
    private function transferMasuk(int $unitId, string $dari): int
    {
        $p = (int) ($this->db->table('penjualan')
            ->selectSum('bayar_bank', 's')
            ->where('DATE(tanggal) >=', $dari)
            ->where('unit_idunit', $unitId)
            ->notLike('kode_invoice', 'srv', 'after')
            ->get()
            ->getRow()->s ?? 0);

        $s = (int) ($this->db->table('service')
            ->select('COALESCE(SUM(COALESCE(harus_dibayar,0) - COALESCE(bayar_tunai,0)),0) AS s')
            ->where('DATE(tanggal_selesai) >=', $dari)
            ->where('status_service', 4)
            ->where('unit_idunit', $unitId)
            ->get()
            ->getRow()->s ?? 0);

        return $p + $s;
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
        $dari = $dari ?? FinanceScopeService::periodeMulaiDate();
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