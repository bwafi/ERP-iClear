<?php

namespace App\Services\Kpi\Calculators;

use Config\Database;

/**
 * ClosingRateCalculator — Closing Rate otomatis dari data marketing.
 *
 *   pembilang = prospek lead manual (kommo_lead_id IS NULL) berstatus CLOSING
 *               dalam periode (berdasarkan tanggal_won).
 *   penyebut  = jumlah prospek dari REKAP HARIAN SEMUA cabang (pembanding).
 * Rumus: CLOSING ÷ prospek(rekap) × 100, di-cap 100.
 */
class ClosingRateCalculator
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function calculate(int $employeeId, int $unitId, int $month, int $year): float
    {
        $periode = sprintf('%04d-%02d', $year, $month);

        $closing = (int)$this->db->table('marketing_lead')
            ->where('status', 'CLOSING')
            ->where('kommo_lead_id', null)
            ->where("DATE_FORMAT(tanggal_won, '%Y-%m') = '{$periode}'")
            ->countAllResults();

        $rekap = $this->db->query(
            "SELECT COALESCE(SUM(d.prospek), 0) AS t
             FROM marketing_rekap_harian h
             JOIN marketing_rekap_harian_detail d ON d.rekap_id = h.id
             WHERE DATE_FORMAT(h.tanggal, '%Y-%m') = ?",
            [$periode]
        )->getRow();
        $prospek = (float)($rekap->t ?? 0);

        if ($prospek <= 0) {
            return 0.0;
        }

        return min($closing / $prospek * 100.0, 100.0);
    }
}