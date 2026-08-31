<?php

namespace App\Services\Kpi\Calculators;

use Config\Database;

/**
 * MetricCalculator — Extract metrics from existing database tables (penilaian, tutup_kasir, stok_opname_draft)
 * Replicates EXACT formulas from hitungKPIGaji and assetberjalan.
 */
class MetricCalculator
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Sum scores for a specific aspect and employee.
     * Replicates: SUM(skor) FROM penilaian WHERE aspek = ? AND month/year AND pegawai_idpegawai
     */
    public function sumAspekScore(int $idAkun, string $aspek, int $bulan, int $tahun): float
    {
        $r = $this->db->table('penilaian')
            ->select('SUM(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('pegawai_idpegawai', $idAkun)
            ->where('aspek', $aspek)
            ->get()
            ->getRow();
        return (float) ($r->total ?? 0.0);
    }

    /**
     * Average scores for a specific aspect (global).
     * Replicates: AVG(skor) FROM penilaian WHERE aspek = ? AND month/year
     */
    public function avgAspekScoreGlobal(string $aspek, int $bulan, int $tahun): float
    {
        $r = $this->db->table('penilaian')
            ->select('AVG(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek', $aspek)
            ->get()
            ->getRow();
        return (float) ($r->total ?? 0.0);
    }

    /**
     * Average scores for all aspects (divisional).
     * Replicates: AVG(skor) FROM penilaian WHERE month/year
     */
    public function avgScoreDivisional(int $bulan, int $tahun): float
    {
        $r = $this->db->table('penilaian')
            ->select('AVG(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->get()
            ->getRow();
        return (float) ($r->total ?? 0.0);
    }

    /**
     * Count Tutup Kasir status.
     * Replicates: COUNT(status) FROM tutup_kasir WHERE unit = ? AND month/year
     */
    public function countTutupKasir(int $unitId, int $bulan, int $tahun): int
    {
        $r = $this->db->table('tutup_kasir')
            ->select('COUNT(status) AS total')
            ->where('MONTH(tanggal)', $bulan)
            ->where('YEAR(tanggal)', $tahun)
            ->where('unit', $unitId)
            ->get()
            ->getRow();
        return (int) ($r->total ?? 0);
    }

    /**
     * Count unique days of Stok Opname.
     * Replicates: COUNT(DISTINCT DATE(tanggal)) FROM stok_opname_draft WHERE unit_idunit = ? AND month/year
     */
    public function countStokOpname(int $unitId, int $bulan, int $tahun): int
    {
        $r = $this->db->table('stok_opname_draft')
            ->select('COUNT(DISTINCT DATE(tanggal)) AS total')
            ->where('unit_idunit', $unitId)
            ->where('MONTH(tanggal)', $bulan)
            ->where('YEAR(tanggal)', $tahun)
            ->get()
            ->getRow();
        return (int) ($r->total ?? 0);
    }
}
