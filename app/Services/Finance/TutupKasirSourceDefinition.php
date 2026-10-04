<?php

namespace App\Services\Finance;

use Config\Database;

/**
 * Definisi transaksi Kas & Bank — SOURCE OF TRUTH: TutupKasir::index()
 *
 * ================================================================
 * service ini adalah SATU-SATUNYA tempat Finance menghitung
 * Cash Masuk / Transfer Masuk / Kas Keluar. Finance membaca tabel
 * sumber asli (penjualan, service, kas_keluar, kas_masuk) dengan
 * definisi yang PERSIS sama dengan TutupKasir.php.
 *
 * Finance TIDAK memanggil TutupKasir dan TIDAK membaca hasil TutupKasir.
 * Keduanya membaca sumber yang sama dengan definisi yang sama.
 * Finance tidak perlu Tutup Kasir sudah dilakukan.
 * ================================================================
 *
 * ------------------------------------------------------------------
 * HASIL AUDIT TutupKasir.php (index(), baris 20-148)
 * ------------------------------------------------------------------
 * $today = date('Y-m-d')            <-- TIDAK dari request
 * $unit  = session()->get('ID_UNIT')
 *
 * KAS AWAL CASH        (barium 29-36)
 *   table kas_masuk, kolom jumlah
 *   WHERE DATE(tanggal) = D
 *     AND deskripsi = 'kas awal'
 *     AND idbank IS NULL
 *     AND idunit = U
 *   ->get()->getRow()->total ?? 0      *** getRow(), BUKAN SUM ***
 *
 * KAS AWAL TRANSFER    (baris 39-46)
 *   sama seperti di atas, tetapi idbank IS NOT NULL
 *   ->get()->getRow()->total ?? 0      *** getRow(), BUKAN SUM ***
 *
 * TRANSFER PENJUALAN   (baris 49-55)
 *   table penjualan, kolom bayar_bank
 *   WHERE DATE(tanggal) = D
 *     AND unit_idunit = U
 *     AND kode_invoice NOT LIKE 'srv%'
 *   SUM(bayar_bank)
 *
 * TRANSFER SERVICE     (baris 58-64)
 *   table service, kolom harus_dibayar - bayar_tunai
 *   WHERE DATE(tanggal_selesai) = D
 *     AND status_service = 4
 *     AND unit_idunit = U
 *   SUM(COALESCE(harus_dibayar,0) - COALESCE(bayar_tunai,0))
 *
 * $transfer = $tfpenjualan + $tfservice       (baris 66)
 *   kas awal TIDAK ikut masuk $transfer.
 *
 * CASH PENJUALAN       (baris 69-75)
 *   table penjualan, kolom bayar_tunai, filter SAMA seperti
 *   TRANSFER PENJUALAN (termasuk kode_invoice NOT LIKE 'srv%')
 *
 * CASH SERVICE         (baris 78-84)
 *   table service, kolom bayar_tunai
 *   WHERE DATE(tanggal_selesai) = D
 *     AND status_service = 4
 *     AND unit_idunit = U
 *
 * $cash = $cashpenjualan + $cashservice       (baris 86)
 * $total = $transfer + $cash                  (baris 87)
 *   kas awal TIDAK ikut masuk $total.
 *
 * PENGELUARAN CASH     (baris 90-96)
 *   table kas_keluar, kolom jumlah
 *   WHERE DATE(tanggal) = D
 *     AND idbank IS NULL
 *     AND idunit = U
 *   SUM(jumlah)
 *
 * PENGELUARAN TRANSFER (baris 99-105)
 *   table kas_keluar, kolom jumlah
 *   WHERE DATE(tanggal) = D
 *     AND idbank IS NOT NULL
 *     AND idunit = U
 *   SUM(jumlah)
 *
 * ------------------------------------------------------------------
 * YANG SENGAJA TIDAK "DIPERBAIKI" DI SINI
 * ------------------------------------------------------------------
 * 1. `getRow()` pada kas awal bukan `SUM`. Kalau ada lebih dari satu baris
 *    `kas_masuk` ber-deskripsi 'kas awal' pada tanggal yang sama, TutupKasir
 *    hanya menghitung SATU baris (baris pertama). Service ini mereplikanya
 *    apa adanya. Memperbaikinya akan membuat Finance ≠ TutupKasir.
 * 2. Transfer service memakai `harus_dibayar - bayar_tunai`, bukan
 *    `bayar_bank`. Secara accounting `bayar_bank` lebih masuk akal, tapi
 *    kalau diganti, angka Finance tidak lagi sama dengan TutupKasir.
 * 3. TutupKasir tidak memfilter deskripsi pada `kas_keluar`, jadi baris
 *    apa pun di kas_keluar ikut terhitung. Finance mengikuti.
 * 4. TutupKasir tidak memakai kolom `deleted` di mana pun.
 *
 * ------------------------------------------------------------------
 * PERBEDAAN SATU-SATUNYA YANG MEMBOLAK TERHADAP TUTUP KASIR
 * ------------------------------------------------------------------
 * TutupKasir selalu memakai `date('Y-m-d')`. Method di sini menerima
 * tanggal sebagai parameter agar Finance bisa menghitung hari historis.
 * Kebenaran parameter ini diuji terpisah: hasil untuk D sama dengan
 * hasil TutupKasir saat `date('Y-m-d') == D`.
 *
 * @see \App\Controllers\TutupKasir::index() — referensi definisi.
 */
class TutupKasirSourceDefinition
{
    /** Tipe akun kas/bank. */
    public const TIPE_KAS = 'KAS';
    public const TIPE_BANK = 'BANK';

    /** Deskripsi baris carry-over yang ditulis TutupKasir::tutup(). */
    public const DESKRIPSI_KAS_AWAL = 'kas awal';

    protected $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    // =================================================================
    // KAS AWAL — opening, BUKAN transaksi
    // =================================================================

    /**
     * Replika TutupKasir baris 29-36. getRow() = baris PERTAMA.
     */
    public function kasAwalCash(int $unitId, string $tanggal): int
    {
        $row = $this->db->table('kas_masuk')
            ->select('jumlah as total')
            ->where('DATE(tanggal)', $tanggal)
            ->where('deskripsi', self::DESKRIPSI_KAS_AWAL)
            ->where('idbank', null)
            ->where('idunit', $unitId)
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    /**
     * Replika TutupKasir baris 39-46. getRow() = baris PERTAMA.
     */
    public function kasAwalTransfer(int $unitId, string $tanggal): int
    {
        $row = $this->db->table('kas_masuk')
            ->select('jumlah as total')
            ->where('DATE(tanggal)', $tanggal)
            ->where('deskripsi', self::DESKRIPSI_KAS_AWAL)
            ->where('idbank !=', null)
            ->where('idunit', $unitId)
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    // =================================================================
    // PENJUALAN — TIDAK TERPISAH cash/transfer di level query
    // =================================================================

    /** Filter penjualan yang dipakai TutupKasir (baris 49-55 dan 69-75). */
    private function penjualan(int $unitId, string $tanggal)
    {
        return $this->db->table('penjualan')
            ->where('DATE(tanggal)', $tanggal)
            ->where('unit_idunit', $unitId)
            ->notLike('kode_invoice', 'srv', 'after');
    }

    /** Replika TutupKasir $tfpenjualan (baris 49-55): SUM(bayar_bank). */
    public function transferPenjualan(int $unitId, string $tanggal): int
    {
        $row = $this->penjualan($unitId, $tanggal)
            ->selectSum('bayar_bank', 'total')
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    /** Replika TutupKasir $cashpenjualan (baris 69-75): SUM(bayar_tunai). */
    public function cashPenjualan(int $unitId, string $tanggal): int
    {
        $row = $this->penjualan($unitId, $tanggal)
            ->selectSum('bayar_tunai', 'total')
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    // =================================================================
    // SERVICE — filter tanggal_selesai + status_service = 4
    // =================================================================

    /** Filter service yang dipakai TutupKasir (baris 58-64 dan 78-84). */
    private function service(int $unitId, string $tanggal)
    {
        return $this->db->table('service')
            ->where('DATE(tanggal_selesai)', $tanggal)
            ->where('status_service', 4)
            ->where('unit_idunit', $unitId);
    }

    /**
     * Replika TutupKasir $tfservice (baris 58-64):
     * SUM(COALESCE(harus_dibayar,0) - COALESCE(bayar_tunai,0)).
     *
     * Sengaja TIDAK memakai `bayar_bank`.
     */
    public function transferService(int $unitId, string $tanggal): int
    {
        $row = $this->service($unitId, $tanggal)
            ->select('SUM(COALESCE(harus_dibayar,0) - COALESCE(bayar_tunai,0)) AS total')
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    /** Replika TutupKasir $cashservice (baris 78-84): SUM(bayar_tunai). */
    public function cashService(int $unitId, string $tanggal): int
    {
        $row = $this->service($unitId, $tanggal)
            ->selectSum('bayar_tunai', 'total')
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    // =================================================================
    // KAS KELUAR — dipisah cash (idbank NULL) vs transfer (idbank NOT NULL)
    // =================================================================

    /** Replika TutupKasir $pengeluarancash (baris 90-96). */
    public function kasKeluarCash(int $unitId, string $tanggal): int
    {
        $row = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 'total')
            ->where('DATE(tanggal)', $tanggal)
            ->where('idbank', null)
            ->where('idunit', $unitId)
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    /** Replika TutupKasir $pengeluarantf (baris 99-105). */
    public function kasKeluarTransfer(int $unitId, string $tanggal): int
    {
        $row = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 'total')
            ->where('DATE(tanggal)', $tanggal)
            ->where('idbank !=', null)
            ->where('idunit', $unitId)
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    // =================================================================
    // AGREGAT
    // =================================================================

    /** $transfer = $tfpenjualan + $tfservice (TutupKasir baris 66). */
    public function transferMasuk(int $unitId, string $tanggal): int
    {
        return $this->transferPenjualan($unitId, $tanggal)
            + $this->transferService($unitId, $tanggal);
    }

    /** $cash = $cashpenjualan + $cashservice (TutupKasir baris 86). */
    public function cashMasuk(int $unitId, string $tanggal): int
    {
        return $this->cashPenjualan($unitId, $tanggal)
            + $this->cashService($unitId, $tanggal);
    }

    // =================================================================
    // AGREGAT RANGE per komponen — definisi per hari, dijumlahkan
    // =================================================================

    /**
     * SUM(bayar_tunai) penjualan dalam rentang, filter TutupKasir.
     *
     * Setara menjumlahkan `cashPenjualan()` untuk tiap tanggal di rentang.
     * Dipakai calculator KPI agar definisinya tidak ditulis ulang di
     * tempat lain — harus tetap identik dengan yang di atas.
     */
    public function cashPenjualanRange(int $unitId, string $dari, string $sampai): int
    {
        $row = $this->db->table('penjualan')
            ->selectSum('bayar_tunai', 'total')
            ->where('DATE(tanggal) >=', $dari)
            ->where('DATE(tanggal) <=', $sampai)
            ->where('unit_idunit', $unitId)
            ->notLike('kode_invoice', 'srv', 'after')
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    /** SUM(bayar_bank) penjualan dalam rentang, filter TutupKasir. */
    public function transferPenjualanRange(int $unitId, string $dari, string $sampai): int
    {
        $row = $this->db->table('penjualan')
            ->selectSum('bayar_bank', 'total')
            ->where('DATE(tanggal) >=', $dari)
            ->where('DATE(tanggal) <=', $sampai)
            ->where('unit_idunit', $unitId)
            ->notLike('kode_invoice', 'srv', 'after')
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    /** SUM(bayar_tunai) service selesai dalam rentang. */
    public function cashServiceRange(int $unitId, string $dari, string $sampai): int
    {
        $row = $this->db->table('service')
            ->selectSum('bayar_tunai', 'total')
            ->where('DATE(tanggal_selesai) >=', $dari)
            ->where('DATE(tanggal_selesai) <=', $sampai)
            ->where('status_service', 4)
            ->where('unit_idunit', $unitId)
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    /**
     * SUM(COALESCE(harus_dibayar,0) - COALESCE(bayar_tunai,0)) service
     * selesai dalam rentang.
     *
     * Sengaja TIDAK `max(0, ...)`: TutupKasir menjumlahkan residual mentah,
     * jadi nilai negatif harus ikut terhitung agar totalnya sama.
     */
    public function transferServiceRange(int $unitId, string $dari, string $sampai): int
    {
        $row = $this->db->table('service')
            ->select('COALESCE(SUM(COALESCE(harus_dibayar,0) - COALESCE(bayar_tunai,0)),0) AS total')
            ->where('DATE(tanggal_selesai) >=', $dari)
            ->where('DATE(tanggal_selesai) <=', $sampai)
            ->where('status_service', 4)
            ->where('unit_idunit', $unitId)
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    /** SUM(jumlah) kas_keluar idbank NULL dalam rentang. */
    public function kasKeluarCashRange(int $unitId, string $dari, string $sampai): int
    {
        $row = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 'total')
            ->where('DATE(tanggal) >=', $dari)
            ->where('DATE(tanggal) <=', $sampai)
            ->where('idunit', $unitId)
            ->where('idbank', null)
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    /** SUM(jumlah) kas_keluar idbank NOT NULL dalam rentang. */
    public function kasKeluarTransferRange(int $unitId, string $dari, string $sampai): int
    {
        $row = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 'total')
            ->where('DATE(tanggal) >=', $dari)
            ->where('DATE(tanggal) <=', $sampai)
            ->where('idunit', $unitId)
            ->where('idbank !=', null)
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    /**
     * Ringkasan satu hari untuk satu unit.
     *
     * Field `pendapatan_*` dan `pengeluaran_*` diberi nama yang sama dengan
     * kolom `tutup_kasir` supaya bisa dibandingkan langsung dengan hasil
     * yang TutupKasir catat.
     *
     * @return array<string,int>
     */
    public function ringkasan(int $unitId, string $tanggal): array
    {
        $cash    = $this->cashMasuk($unitId, $tanggal);
        $transfer = $this->transferMasuk($unitId, $tanggal);

        return [
            'kas_awalcash'         => $this->kasAwalCash($unitId, $tanggal),
            'kas_awaltf'           => $this->kasAwalTransfer($unitId, $tanggal),

            // Komponen, supaya selisih dengan TutupKasir bisa ditelusuri.
            'cashpenjualan'        => $this->cashPenjualan($unitId, $tanggal),
            'cashservice'          => $this->cashService($unitId, $tanggal),
            'tfpenjualan'          => $this->transferPenjualan($unitId, $tanggal),
            'tfservice'            => $this->transferService($unitId, $tanggal),

            // Total — sama dengan yang dihitung TutupKasir.
            'cash'                 => $cash,
            'transfer'             => $transfer,
            'total_pendapatan'     => $transfer + $cash,
            'pengeluarancash'      => $this->kasKeluarCash($unitId, $tanggal),
            'pengeluarantf'        => $this->kasKeluarTransfer($unitId, $tanggal),
        ];
    }

    /** $total = $transfer + $cash (TutupKasir baris 87). */
    public function totalPendapatan(int $unitId, string $tanggal): int
    {
        return $this->transferMasuk($unitId, $tanggal)
            + $this->cashMasuk($unitId, $tanggal);
    }

    // =================================================================
    // AGREGAT RANGE — untuk KPI bulanan (tanpa mengubah definisi per hari)
    // =================================================================

    /**
     * Jumlah rentang tanggal [dari, sampai] INK-LUSIF per hari.
     *
     * Definisi per hari TIDAK diubah — hanya dijumlahkan. Karena TutupKasir
     * memakai `DATE(kolom) = D`, rentang ditulis sebagai
     * `DATE(kolom) BETWEEN dari AND sampai` yang secara hasil setara dengan
     * menjumlahkan `= D` untuk tiap D di rentang.
     *
     * @return array<string,int>
     */
    public function ringkasanRange(int $unitId, string $dari, string $sampai): array
    {
        $p = function (string $kolom) use ($unitId, $dari, $sampai) {
            return $this->db->table('penjualan')
                ->where('DATE(tanggal) >=', $dari)
                ->where('DATE(tanggal) <=', $sampai)
                ->where('unit_idunit', $unitId)
                ->notLike('kode_invoice', 'srv', 'after');
        };

        $s = function () use ($unitId, $dari, $sampai) {
            return $this->db->table('service')
                ->where('DATE(tanggal_selesai) >=', $dari)
                ->where('DATE(tanggal_selesai) <=', $sampai)
                ->where('status_service', 4)
                ->where('unit_idunit', $unitId);
        };

        $sum = static function ($builder): int {
            $row = $builder->get()->getRow();

            return (int) ($row->t ?? 0);
        };

        $cashPenjualan = $sum($p('tanggal')->selectSum('bayar_tunai', 't'));
        $cashService   = $sum($s()->selectSum('bayar_tunai', 't'));
        $tfPenjualan   = $sum($p('tanggal')->selectSum('bayar_bank', 't'));
        $tfService     = $sum($s()->select('SUM(COALESCE(harus_dibayar,0) - COALESCE(bayar_tunai,0)) AS t'));

        $cash     = $cashPenjualan + $cashService;
        $transfer = $tfPenjualan + $tfService;

        $kk = function (string $bank) use ($unitId, $dari, $sampai) {
            $b = $this->db->table('kas_keluar')
                ->where('DATE(tanggal) >=', $dari)
                ->where('DATE(tanggal) <=', $sampai)
                ->where('idunit', $unitId);

            return $bank === 'null'
                ? $b->where('idbank', null)
                : $b->where('idbank !=', null);
        };

        $keluarCash = $sum($kk('null')->selectSum('jumlah', 't'));
        $keluarTf   = $sum($kk('notnull')->selectSum('jumlah', 't'));

        return [
            'cashpenjualan'    => $cashPenjualan,
            'cashservice'      => $cashService,
            'tfpenjualan'      => $tfPenjualan,
            'tfservice'        => $tfService,
            'cash'             => $cash,
            'transfer'         => $transfer,
            'total_pendapatan' => $transfer + $cash,
            'pengeluarancash'  => $keluarCash,
            'pengeluarantf'    => $keluarTf,
            'pengeluaran'      => $keluarCash + $keluarTf,
        ];
    }

    /**
     * Penjumlahan hari-per-hari untuk rentang, di group berdasarkan tanggal.
     *
     * Dipakai grafik/tabel detail. Mengembalikan:
     *   cash      => ['YYYY-MM-DD' => int]
     *   transfer  => ['YYYY-MM-DD' => int]
     *   keluar    => ['YYYY-MM-DD' => int]
     *
     * @return array{cash: array<string,int>, transfer: array<string,int>, keluar: array<string,int>}
     */
    public function harianRange(int $unitId, string $dari, string $sampai): array
    {
        $rowsPenjualan = $this->db->table('penjualan')
            ->select('DATE(tanggal) AS d', false)
            ->select('COALESCE(SUM(bayar_tunai),0) AS cash', false)
            ->select('COALESCE(SUM(bayar_bank),0) AS transfer', false)
            ->where('DATE(tanggal) >=', $dari)
            ->where('DATE(tanggal) <=', $sampai)
            ->where('unit_idunit', $unitId)
            ->notLike('kode_invoice', 'srv', 'after')
            ->groupBy('DATE(tanggal)')
            ->get()
            ->getResultArray();

        $rowsService = $this->db->table('service')
            ->select('DATE(tanggal_selesai) AS d', false)
            ->select('COALESCE(SUM(bayar_tunai),0) AS cash', false)
            ->select('COALESCE(SUM(COALESCE(harus_dibayar,0) - COALESCE(bayar_tunai,0)),0) AS transfer', false)
            ->where('DATE(tanggal_selesai) >=', $dari)
            ->where('DATE(tanggal_selesai) <=', $sampai)
            ->where('status_service', 4)
            ->where('unit_idunit', $unitId)
            ->groupBy('DATE(tanggal_selesai)')
            ->get()
            ->getResultArray();

        $rowsKeluar = $this->db->table('kas_keluar')
            ->select('DATE(tanggal) AS d, COALESCE(SUM(jumlah),0) AS keluar')
            ->where('DATE(tanggal) >=', $dari)
            ->where('DATE(tanggal) <=', $sampai)
            ->where('idunit', $unitId)
            ->groupBy('DATE(tanggal)')
            ->get()
            ->getResultArray();

        $out = ['cash' => [], 'transfer' => [], 'keluar' => []];

        foreach ($rowsPenjualan as $r) {
            $out['cash'][$r['d']]     = (int) $r['cash'];
            $out['transfer'][$r['d']] = (int) $r['transfer'];
        }
        foreach ($rowsService as $r) {
            $out['cash'][$r['d']]     = ($out['cash'][$r['d']] ?? 0) + (int) $r['cash'];
            $out['transfer'][$r['d']] = ($out['transfer'][$r['d']] ?? 0) + (int) $r['transfer'];
        }
        foreach ($rowsKeluar as $r) {
            $out['keluar'][$r['d']] = (int) $r['keluar'];
        }

        return $out;
    }
}