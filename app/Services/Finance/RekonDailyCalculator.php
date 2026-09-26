<?php

namespace App\Services\Finance;

use App\Models\ModelFinanceRekonDaily;
use Config\Database;

/**
 * Rekonsiliasi Harian Finance — ERP query + KPI scoring.
 *
 * 3 kelompok transaksi harian (query DICERMINAI dari TutupKasir::index()):
 *  1. Cash Masuk     = penjualan.bayar_tunai + service.bayar_tunai
 *  2. Transfer Masuk = penjualan.bayar_bank + service.(harus_dibayar - bayar_tunai)
 *  3. Kas Keluar     = kas_keluar.jumlah (cash + transfer, dijumlahkan utuh)
 *
 * PENTING: query penjualan memakai notLike('kode_invoice','srv','after') —
 * sama seperti TutupKasir — agar invoice bertanda "srv" tidak terhitung dua kali
 * (sudah masuk lewat tabel service).
 *
 * Status hasil (HASIL, bukan approval):
 *  belum | belum_lengkap | lengkap_cocok | lengkap_selisih
 * Status proses approval (TERPISAH):
 *  draft | submitted | verified | need_revision
 *
 * KPI Score = (hari lengkap+VERIFIED / hari kerja) × 100.
 * - Sen–Sab (hari libur BELUM diperhitungkan).
 * - Selisih TIDAK mengurangi skor; disimpan sebagai temuan untuk KPI Akurasi.
 */
class RekonDailyCalculator implements FinanceCalculatorInterface
{
    protected $db;
    protected $model;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->model = new ModelFinanceRekonDaily();
    }

    /**
     * Hitung nilai ERP untuk satu unit pada satu tanggal.
     *
     * @return array{cash_masuk: int, transfer_masuk: int, kas_keluar: int}
     */
    public function erpValues(int $unitId, string $tanggal): array
    {
        return [
            'cash_masuk'     => $this->erpCashMasuk($unitId, $tanggal),
            'transfer_masuk' => $this->erpTransferMasuk($unitId, $tanggal),
            'kas_keluar'     => $this->erpKasKeluar($unitId, $tanggal),
        ];
    }

    /**
     * Cash Masuk = penjualan.bayar_tunai + service.bayar_tunai.
     * Setara TutupKasir::index() "PENJUALAN CASH" + "SERVICE CASH".
     */
    protected function erpCashMasuk(int $unitId, string $tanggal): int
    {
        $cashPenjualan = $this->db->table('penjualan')
            ->selectSum('bayar_tunai', 'total')
            ->where('DATE(tanggal)', $tanggal)
            ->where('unit_idunit', $unitId)
            ->notLike('kode_invoice', 'srv', 'after')
            ->get()
            ->getRow()->total ?? 0;

        $cashService = $this->db->table('service')
            ->selectSum('bayar_tunai', 'total')
            ->where('DATE(tanggal_selesai)', $tanggal)
            ->where('status_service', 4)
            ->where('unit_idunit', $unitId)
            ->get()
            ->getRow()->total ?? 0;

        return (int) $cashPenjualan + (int) $cashService;
    }

    /**
     * Transfer Masuk = penjualan.bayar_bank + service.(harus_dibayar - bayar_tunai).
     * Setara TutupKasir::index() "PENJUALAN TRANSFER" + "SERVICE TRANSFER".
     */
    protected function erpTransferMasuk(int $unitId, string $tanggal): int
    {
        $tfPenjualan = $this->db->table('penjualan')
            ->selectSum('bayar_bank', 'total')
            ->where('DATE(tanggal)', $tanggal)
            ->where('unit_idunit', $unitId)
            ->notLike('kode_invoice', 'srv', 'after')
            ->get()
            ->getRow()->total ?? 0;

        $tfService = $this->db->table('service')
            ->select('SUM(COALESCE(harus_dibayar,0) - COALESCE(bayar_tunai,0)) AS total')
            ->where('DATE(tanggal_selesai)', $tanggal)
            ->where('status_service', 4)
            ->where('unit_idunit', $unitId)
            ->get()
            ->getRow()->total ?? 0;

        return (int) $tfPenjualan + (int) $tfService;
    }

    /**
     * Kas Keluar = SUM(kas_keluar.jumlah) per unit per tanggal.
     * TutupKasir::index() memecahnya cash (idbank null) vs transfer (idbank
     * != null); karena keduanya tidak disaring lagi di modul ini, hasil
     * penjumlahan keduanya identik dengan penjumlahan utuh.
     */
    protected function erpKasKeluar(int $unitId, string $tanggal): int
    {
        $row = $this->db->table('kas_keluar')
            ->selectSum('jumlah', 'total')
            ->where('DATE(tanggal)', $tanggal)
            ->where('idunit', $unitId)
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    /** Status hasil per komponen (dipakai form & list). */
    public const KOMPONEN_BELUM_DIPERIKSA = 'belum_diperiksa';
    public const KOMPONEN_COCOK = 'cocok';
    public const KOMPONEN_SELISIH = 'selisih';

    /**
     * Status HASIL rekonsiliasi satu hari (bukan status approval).
     *
     * murni dari data, tanpa input manual:
     *   belum            : tidak ada record sama sekali
     *   belum_lengkap    : ada record, tapi minimal satu actual_* NULL
     *   lengkap_cocok    : ketiga actual_* terisi & semua selisih 0
     *   lengkap_selisih  : ketiga actual_* terisi & ada selisih != 0
     */
    public static function statusHarian(?object $row): string
    {
        if (! $row) {
            return 'belum';
        }

        if (! self::isLengkap($row)) {
            return 'belum_lengkap';
        }

        return self::punyaSelisih($row) ? 'lengkap_selisih' : 'lengkap_cocok';
    }

    /**
     * Status hasil satu komponen (Cash Masuk / Transfer Masuk / Kas Keluar).
     *
     *   actual_* NULL           -> belum_diperiksa
     *   actual_* terisi, selisih 0  -> cocok
     *   actual_* terisi, selisih != 0 -> selisih
     *
     * $suffix = 'cash_masuk' | 'transfer_masuk' | 'kas_keluar'
     */
    public static function statusKomponen(?object $row, string $suffix): string
    {
        if (! $row) {
            return self::KOMPONEN_BELUM_DIPERIKSA;
        }

        $actual = $row->{'actual_' . $suffix} ?? null;

        if ($actual === null || $actual === '') {
            return self::KOMPONEN_BELUM_DIPERIKSA;
        }

        $selisih = $row->{'selisih_' . $suffix} ?? null;

        // Selisih dihitung server-side; bila null (mis. data legacy) belum dianggap cocok.
        if ($selisih === null) {
            return self::KOMPONEN_BELUM_DIPERIKSA;
        }

        return (int) $selisih === 0 ? self::KOMPONEN_COCOK : self::KOMPONEN_SELISIH;
    }

    /**
     * Label teks status komponen.
     */
    public static function labelKomponen(string $status): string
    {
        $map = [
            self::KOMPONEN_BELUM_DIPERIKSA => 'Belum diperiksa',
            self::KOMPONEN_COCOK            => 'Cocok',
            self::KOMPONEN_SELISIH          => 'Selisih',
        ];

        return $map[$status] ?? 'Belum diperiksa';
    }

    /**
     * Badge CSS class status komponen.
     */
    public static function badgeKomponen(string $status): string
    {
        $map = [
            self::KOMPONEN_BELUM_DIPERIKSA => 'bg-secondary',
            self::KOMPONEN_COCOK            => 'bg-success',
            self::KOMPONEN_SELISIH          => 'bg-info',
        ];

        return $map[$status] ?? 'bg-secondary';
    }

    /**
     * Apakah ketiga actual_* sudah terisi? Ini definisi "lengkap" yang dipakai
     * numerator KPI, terpisah dari approval.
     *
     * PENTING: angka 0 dianggap SAH (user benar-benar mencocokkan 0), yang
     * menentukan "belum" adalah NULL.
     */
    public static function isLengkap(?object $row): bool
    {
        if (! $row) {
            return false;
        }

        return self::sudahDiisi($row, 'cash_masuk')
            && self::sudahDiisi($row, 'transfer_masuk')
            && self::sudahDiisi($row, 'kas_keluar');
    }

    /**
     * Apakah actual_<suffix> sudah diisi (bukan NULL)?
     */
    private static function sudahDiisi(?object $row, string $suffix): bool
    {
        $actual = $row->{'actual_' . $suffix} ?? null;

        return $actual !== null && $actual !== '';
    }

    /**
     * Apakah ada minimal satu selisih != 0?
     * Tidak memengaruhi skor, hanya penanda hasil.
     */
    public static function punyaSelisih(?object $row): bool
    {
        if (! $row) {
            return false;
        }

        return ((int) ($row->selisih_cash_masuk ?? 0) !== 0)
            || ((int) ($row->selisih_transfer_masuk ?? 0) !== 0)
            || ((int) ($row->selisih_kas_keluar ?? 0) !== 0);
    }

    /**
     * Syarat "siap submit": ketiga actual_* sudah terisi.
     * Selisih boleh ada — Manager tetap boleh Verify meski LENGKAP_SELISIH.
     */
    public static function siapSubmit(?object $row): bool
    {
        return self::isLengkap($row);
    }

    /**
     * Label tampilan status.
     */
    public static function labelStatus(string $status): string
    {
        $map = [
            'belum'           => 'Belum Rekonsiliasi',
            'belum_lengkap'   => 'Belum lengkap',
            'lengkap_cocok'   => 'Lengkap - Cocok',
            'lengkap_selisih' => 'Lengkap - Selisih',
        ];
        return $map[$status] ?? ucfirst($status);
    }

    /**
     * Badge CSS class per status.
     */
    public static function badgeStatus(string $status): string
    {
        $map = [
            'belum'           => 'bg-secondary',
            'belum_lengkap'   => 'bg-warning text-dark',
            'lengkap_cocok'   => 'bg-success',
            'lengkap_selisih' => 'bg-info',
        ];
        return $map[$status] ?? 'bg-secondary';
    }

    // =====================================================================
    // Status PROSES approval (terpisah dari status hasil di atas)
    // =====================================================================

    /**
     * Normalisasi status_proses dari database (default 'draft').
     */
    public static function statusProses(?object $row): string
    {
        $status = strtolower(trim((string) ($row->status_proses ?? '')));

        return $status !== '' ? $status : ModelFinanceRekonDaily::STATUS_DRAFT;
    }

    public static function labelProses(string $status): string
    {
        $map = [
            'draft'         => 'Draft',
            'submitted'     => 'Submitted',
            'verified'      => 'Verified',
            'need_revision' => 'Perlu Revisi',
        ];
        return $map[$status] ?? ucfirst($status);
    }

    public static function badgeProses(string $status): string
    {
        $map = [
            'draft'         => 'bg-secondary',
            'submitted'     => 'bg-info text-dark',
            'verified'      => 'bg-success',
            'need_revision' => 'bg-danger',
        ];
        return $map[$status] ?? 'bg-secondary';
    }

    /**
     * Data sudah terkunci (tidak bisa diubah Admin) setelah VERIFIED.
     */
    public static function isLocked(?object $row): bool
    {
        return self::statusProses($row) === ModelFinanceRekonDaily::STATUS_VERIFIED;
    }

    /**
     * Nama-nama akun untuk list (input_by / submitted_by / verified_by).
     *
     * @param array<int,int> $ids
     * @return array<int,string>
     */
    public function resolveAkunNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === []) {
            return [];
        }

        $rows = $this->db->table('akun')
            ->select('ID_AKUN, NAMA_AKUN')
            ->whereIn('ID_AKUN', $ids)
            ->get()
            ->getResult();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->ID_AKUN] = (string) $row->NAMA_AKUN;
        }

        return $out;
    }

    /**
     * Daftar rekonsiliasi harian untuk satu unit dalam satu bulan.
     * Mengembalikan array per hari dalam bulan, termasuk hari tanpa data.
     *
     * @param string|null $filterProses filter status_proses (null = semua)
     */
    public function monthlyList(int $unitId, int $month, int $year, ?string $filterProses = null): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
        $today = date('Y-m-d');

        $rows = $this->model->getByUnitAndRange($unitId, $startDate, $endDate);
        $byDate = [];
        foreach ($rows as $r) {
            $byDate[$r->tanggal] = $r;
        }

        $list = [];
        $d = $startDate;
        while ($d <= $endDate && $d <= $today) {
            $row = $byDate[$d] ?? null;
            $proses = self::statusProses($row);

            if ($filterProses !== null && $filterProses !== '' && $proses !== $filterProses) {
                $d = date('Y-m-d', strtotime($d . ' +1 day'));
                continue;
            }

            $list[] = [
                'tanggal'  => $d,
                'row'      => $row,
                'status'   => self::statusHarian($row),
                'proses'   => $row ? $proses : null,
                'lengkap'  => self::isLengkap($row),
                'selisih'  => self::punyaSelisih($row),
            ];
            $d = date('Y-m-d', strtotime($d . ' +1 day'));
        }

        return $list;
    }

    /**
     * KPI Score Rekonsiliasi = (hari lengkap+VERIFIED / hari kerja) × 100.
     *
     * Numerator: ketiga actual_* terisi (IS NOT NULL) DAN status_proses =
     *            'verified'. 'submitted'/'need_revision'/'draft' TIDAK dihitung.
     * Selisih TIDAK memengaruhi skor: LENGKAP_COCOK dan LENGKAP_SELISIH
     *            sama-sama dihitung sebagai hari selesai selama terverifikasi.
     * Denominator: Senin–Sabtu dalam periode, dipotong di hari ini.
     *              HARI LIBUR BELUM diperhitungkan.
     *
     * Mengembalikan score = null bila tabel/kolom belum tersedia atau tidak
     * ada hari kerja, sehingga pemanggil (FinanceKpiCalculationService) dapat
     * jatuh ke skor manual.
     */
    public function calculate(int $unitId, int $month, int $year): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
        $today = date('Y-m-d');

        if ($endDate > $today) {
            $endDate = $today;
        }

        $hariKerja = $this->countHariKerja($startDate, $endDate);
        $hariLengkap = 0;
        $hariLengkapVerified = 0;

        if ($hariKerja > 0) {
            try {
                $hariLengkap = $this->model->countLengkapInRange($unitId, $startDate, $endDate);
                $hariLengkapVerified = $this->model->countLengkapVerifiedInRange($unitId, $startDate, $endDate);
            } catch (\Throwable $e) {
                // Tabel/kolom belum siap -> kembalikan null agar caller fallback manual.
                return [
                    'score'  => null,
                    'status' => 'error',
                    'detail' => [
                        'start_date'           => $startDate,
                        'end_date'             => $endDate,
                        'hari_kerja'           => $hariKerja,
                        'hari_lengkap'         => 0,
                        'hari_lengkap_verified' => 0,
                        'error'                => $e->getMessage(),
                    ],
                ];
            }
        }

        $detail = [
            'start_date'           => $startDate,
            'end_date'             => $endDate,
            'hari_kerja'           => $hariKerja,
            'hari_lengkap'         => $hariLengkap,
            'hari_lengkap_verified' => $hariLengkapVerified,
        ];

        if ($hariKerja <= 0) {
            return [
                'score'  => null,
                'status' => 'data_kosong',
                'detail' => $detail,
            ];
        }

        $score = min(($hariLengkapVerified / $hariKerja) * 100, 100);

        return [
            'score'  => round($score, 2),
            'status' => 'ok',
            'detail' => $detail,
        ];
    }

    /**
     * Hitung hari kerja (Senin-Sabtu) dalam rentang.
     */
    protected function countHariKerja(string $startDate, string $endDate): int
    {
        $count = 0;
        $d = $startDate;
        while ($d <= $endDate) {
            $dow = (int) date('w', strtotime($d));
            if ($dow >= 1 && $dow <= 6) {
                $count++;
            }
            $d = date('Y-m-d', strtotime($d . ' +1 day'));
        }
        return $count;
    }
}
