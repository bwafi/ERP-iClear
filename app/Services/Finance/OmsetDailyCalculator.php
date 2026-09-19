<?php

namespace App\Services\Finance;

use App\Models\ModelDetailPenjualan;

/**
 * Omzet harian ERP = SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan)
 * per unit per hari — mengikuti definisi OmsetCabangCalculator yang dipakai
 * modul Omset Cabang/Hari Ini dan insentif (bukan penjualan.total_penjualan).
 */
class OmsetDailyCalculator implements FinanceCalculatorInterface
{
    protected $detailPenjualan;

    public function __construct()
    {
        $this->detailPenjualan = new ModelDetailPenjualan();
    }

    /**
     * Omzet ERP satu hari untuk satu unit.
     */
    public function calculateDaily(int $unitId, string $date): float
    {
        $result = $this->detailPenjualan
            ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total_omset')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            ->where('penjualan.unit_idunit', $unitId)
            ->where('DATE(penjualan.tanggal)', $date)
            ->first();

        return (float) ($result->total_omset ?? 0);
    }

    /**
     * Omzet ERP per tanggal dalam rentang, dikembalikan sebagai map [tanggal => nilai].
     *
     * @return array<string, float>
     */
    public function calculateDailyByRange(int $unitId, string $startDate, string $endDate): array
    {
        $rows = $this->detailPenjualan
            ->select('DATE(penjualan.tanggal) AS tanggal, SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total_omset')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            ->where('penjualan.unit_idunit', $unitId)
            ->where('DATE(penjualan.tanggal) >=', $startDate)
            ->where('DATE(penjualan.tanggal) <=', $endDate)
            ->groupBy('DATE(penjualan.tanggal)')
            ->orderBy('DATE(penjualan.tanggal)', 'ASC')
            ->findAll();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->tanggal] = (float) ($row->total_omset ?? 0);
        }

        return $map;
    }

    /**
     * Total omzet ERP seluruh rentang (untuk KPI akurasi).
     */
    public function calculateRange(int $unitId, string $startDate, string $endDate): float
    {
        return array_sum($this->calculateDailyByRange($unitId, $startDate, $endDate));
    }

    /**
     * Ditambahkan agar cocok dengan FinanceCalculatorInterface (skor bulanan mentah).
     * Nilai di sini = total omzet ERP bulan tersebut.
     */
    public function calculate(int $unitId, int $month, int $year): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $total = $this->calculateRange($unitId, $startDate, $endDate);

        return [
            'score' => null, // Omzet bukan skor; digunakan sebagai nilai rujukan.
            'status' => 'ok',
            'detail' => [
                'omzet_erp' => $total,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ];
    }
}