<?php

namespace App\Services\Kpi;

/**
 * OmsetTeknisiCalculator — realisasi OMSET_TEKNISI dihitung HANYA dari
 * pekerjaan service yang dikerjakan oleh teknisi bersangkutan
 * (service.service_by, status selesai = 4, diselesaikan dalam bulan berjalan).
 *
 * Omset jasa per service = service.harus_dibayar − Σ(service_sparepart.hpp_penjualan × jumlah).
 * Tidak lagi memakai omzet utuh cabang (OmsetToko).
 */
class OmsetTeknisiCalculator implements KpiCalculatorInterface
{
    public function calculate($employeeId, $unitId, $month, $year)
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = date('Y-m-t', strtotime($startDate));

        $db = \Config\Database::connect();

        $result = $db->query(
            "SELECT COALESCE(SUM(t.omset_service), 0) AS total
               FROM (
                    SELECT
                        s.idservice,
                        s.harus_dibayar - COALESCE(
                            (SELECT SUM(COALESCE(sp.hpp_penjualan, 0) * COALESCE(sp.jumlah, 0))
                               FROM service_sparepart sp
                              WHERE sp.service_idservice = s.idservice), 0) AS omset_service
                      FROM service s
                     WHERE s.service_by = ?
                       AND s.status_service = 4
                       AND DATE(s.tanggal_selesai) BETWEEN ? AND ?
               ) t",
            [$employeeId, $startDate, $endDate]
        )->getRow();

        return (float) ($result->total ?? 0);
    }
}