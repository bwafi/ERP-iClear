<?php

namespace App\Services\Finance;

use App\Models\ModelFinanceRekonDaily;
use Config\Database;

/**
 * Rekonsiliasi Harian Finance — ERP query + KPI scoring.
 *
 * 3 kelompok transaksi harian:
 *  1. Cash Masuk   = penjualan.bayar_tunai + service.bayar_tunai
 *  2. Transfer Masuk = penjualan.bayar_bank + service.(harus_dibayar - bayar_tunai)
 *  3. Kas Keluar   = kas_keluar.jumlah (cash + transfer)
 *
 * KPI Score = (hari lengkap / hari kerja) × 100.
 * Selisih TIDAK mengurangi skor; disimpan sebagai temuan untuk KPI Akurasi.
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
     * Pattern dari TutupKasir::index() line 68-83.
     */
    protected function erpCashMasuk(int $unitId, string $tanggal): int
    {
        $cashPenjualan = $this->db->table('penjualan')
            ->selectSum('bayar_tunai', 'total')
            ->where('DATE(tanggal)', $tanggal)
            ->where('unit_idunit', $unitId)
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
     * Pattern dari TutupKasir::index() line 47-65.
     */
    protected function erpTransferMasuk(int $unitId, string $tanggal): int
    {
        $tfPenjualan = $this->db->table('penjualan')
            ->selectSum('bayar_bank', 'total')
            ->where('DATE(tanggal)', $tanggal)
            ->where('unit_idunit', $unitId)
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
     * Termasuk cash dan transfer (semua jenis pengeluaran).
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

    /**
     * Status harian dari satu baris rekon.
     *
     * @return string belum|belum_lengkap|lengkap_cocok|lengkap_selisih
     */
    public static function statusHarian(?object $row): string
    {
        if (!$row) {
            return 'belum';
        }

        $allChecked = (int) $row->checked_cash_masuk
            && (int) $row->checked_transfer_masuk
            && (int) $row->checked_kas_keluar;

        if (!$allChecked) {
            return 'belum_lengkap';
        }

        $adaSelisih = ((int) ($row->selisih_cash_masuk ?? 0) !== 0)
            || ((int) ($row->selisih_transfer_masuk ?? 0) !== 0)
            || ((int) ($row->selisih_kas_keluar ?? 0) !== 0);

        return $adaSelisih ? 'lengkap_selisih' : 'lengkap_cocok';
    }

    /**
     * Label tampilan status.
     */
    public static function labelStatus(string $status): string
    {
        $map = [
            'belum'           => 'Belum Rekonsiliasi',
            'belum_lengkap'   => 'Belum Lengkap',
            'lengkap_cocok'   => 'Lengkap & Cocok',
            'lengkap_selisih' => 'Lengkap, Ada Selisih',
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

    /**
     * Daftar rekonsiliasi harian untuk satu unit dalam satu bulan.
     * Mengembalikan array per hari dalam bulan, termasuk hari tanpa data.
     */
    public function monthlyList(int $unitId, int $month, int $year): array
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
            $list[] = [
                'tanggal' => $d,
                'row'     => $row,
                'status'  => self::statusHarian($row),
            ];
            $d = date('Y-m-d', strtotime($d . ' +1 day'));
        }

        return $list;
    }

    /**
     * KPI Score Rekonsiliasi = (hari lengkap / hari kerja) × 100.
     *
     * Implements FinanceCalculatorInterface::calculate().
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
        $hariLengkap = $this->model->countLengkapInRange($unitId, $startDate, $endDate);

        if ($hariKerja <= 0) {
            return [
                'score'  => null,
                'status' => 'data_kosong',
                'detail' => [
                    'start_date'    => $startDate,
                    'end_date'      => $endDate,
                    'hari_kerja'    => 0,
                    'hari_lengkap'  => 0,
                ],
            ];
        }

        $score = min(($hariLengkap / $hariKerja) * 100, 100);

        return [
            'score'  => round($score, 2),
            'status' => 'ok',
            'detail' => [
                'start_date'    => $startDate,
                'end_date'      => $endDate,
                'hari_kerja'    => $hariKerja,
                'hari_lengkap'  => $hariLengkap,
            ],
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
