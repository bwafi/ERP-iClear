<?php

namespace App\Services\Kpi;

use Config\Database;
use App\Models\ModelCustomerSatisfaction;

/**
 * Customer Satisfaction KPI.
 *
 * Konsep: persentase customer (transaksi penjualan ber-invoice SLL + service)
 * yang memberikan review Google Maps vs total customer.
 *
 * - input harian per unit oleh Kepala Toko (jumlah_review).
 * - total_customer TIDAK disimpan — dihitung dari transaksi per (tanggal, unit).
 * - agregasi SPV = SUM(review) / SUM(total_customer) x 100 (bukan rata2 harian).
 */
class CustomerSatisfactionService
{
    protected $db;
    protected $model;

    public function __construct()
    {
        $this->db    = Database::connect();
        $this->model = new ModelCustomerSatisfaction();
    }

    public function model(): ModelCustomerSatisfaction
    {
        return $this->model;
    }

    /**
     * Unit-area SPV dari spv_units (fallback unit sendiri). Dipakai ulang,
     * tanpa mapping SPV baru.
     */
    public function scopeUnits(int $supervisorId, int $fallbackUnit): array
    {
        $rows = $this->db->table('spv_units')
            ->where('spv_id', $supervisorId)
            ->get()
            ->getResultArray();

        $units = !empty($rows)
            ? array_map('intval', array_column($rows, 'unit_id'))
            : [(int)$fallbackUnit];

        return array_values(array_unique($units));
    }

    /**
     * Total customer unik pada (tanggal, unit): transaksi penjualan
     * ber-kode_invoice 'SLL%' + transaksi service.
     */
    public function totalCustomers(string $tanggal, int $idUnit): int
    {
        $row = $this->db->query(
            "SELECT COUNT(DISTINCT pid) AS total FROM (
                SELECT id_pelanggan AS pid
                FROM penjualan
                WHERE unit_idunit = ?
                  AND DATE(tanggal) = ?
                  AND id_pelanggan > 0
                  AND kode_invoice LIKE 'SLL%'
                UNION
                SELECT pelanggan_id_pelanggan AS pid
                FROM service
                WHERE unit_idunit = ?
                  AND DATE(created_at) = ?
                  AND pelanggan_id_pelanggan > 0
            ) x",
            [(int)$idUnit, $tanggal, (int)$idUnit, $tanggal]
        )->getRow();

        return (int)($row->total ?? 0);
    }

    /**
     * Semua unit aktif (utk Admin root / Direktur).
     */
    public function allUnits(): array
    {
        $rows = $this->db->table('unit')->select('idunit')->orderBy('idunit', 'ASC')->get()->getResultArray();
        return array_map('intval', array_column($rows, 'idunit'));
    }

    /**
     * Peta total customer per (tanggal, unit) untuk rentang & daftar unit.
     * Satu query (UNION + GROUP BY) supaya agregasi bulanan tidak puluhan query.
     */
    public function customerMapForPeriod(array $unitIds, string $start, string $end): array
    {
        if (empty($unitIds)) {
            return [];
        }
        $in = implode(',', array_map('intval', $unitIds));

        $rows = $this->db->query(
            "SELECT d, u, COUNT(DISTINCT pid) AS total FROM (
                SELECT DATE(tanggal) AS d, unit_idunit AS u, id_pelanggan AS pid
                FROM penjualan
                WHERE unit_idunit IN ($in)
                  AND DATE(tanggal) BETWEEN ? AND ?
                  AND id_pelanggan > 0
                  AND kode_invoice LIKE 'SLL%'
                UNION
                SELECT DATE(created_at) AS d, unit_idunit AS u, pelanggan_id_pelanggan AS pid
                FROM service
                WHERE unit_idunit IN ($in)
                  AND DATE(created_at) BETWEEN ? AND ?
                  AND pelanggan_id_pelanggan > 0
            ) x
            GROUP BY d, u",
            [$start, $end, $start, $end]
        )->getResultArray();

        $map = [];
        foreach ($rows as $r) {
            $map[$r['u']][$r['d']] = (int)$r['total'];
        }
        return $map;
    }

    /**
     * Simpan jumlah review (upsert per tanggal+unit).
     * Validasi: review tidak boleh negatif / melebihi total customer.
     *
     * @return array ['success' => bool, 'errors' => string[], 'total_customer' => int]
     */
    public function save(string $tanggal, int $idUnit, int $jumlahReview, int $createdBy): array
    {
        $totalCustomer = $this->totalCustomers($tanggal, $idUnit);

        if ($jumlahReview < 0) {
            return ['success' => false, 'total_customer' => $totalCustomer, 'errors' => ['Jumlah review tidak boleh negatif.']];
        }
        if ($jumlahReview > $totalCustomer) {
            return ['success' => false, 'total_customer' => $totalCustomer, 'errors' => ['Jumlah review tidak boleh melebihi total customer pada tanggal tersebut.']];
        }

        $this->model->upsert($tanggal, $idUnit, $jumlahReview, $createdBy);

        return ['success' => true, 'total_customer' => $totalCustomer, 'errors' => []];
    }

    /**
     * Persentase harian utk tampilan: review / total_customer x 100.
     */
    public function dailyPercentage(int $review, int $totalCustomer): float
    {
        if ($totalCustomer <= 0) {
            return 0.0;
        }
        return round($review / $totalCustomer * 100, 2);
    }

    /**
     * Agregasi bulanan area: SUM(review) / SUM(total_customer) x 100.
     * Hanya hari yang tercatat ikut dihitung. Per unit + total area.
     *
     * @return array ['units' => [id => ['review','customer','persen']], 'total' => ['review','customer','persen]]
     */
    public function monthlyAggregation(array $unitIds, string $bulan, string $tahun): array
    {
        $start = sprintf('%04d-%02d-01', (int)$tahun, (int)$bulan);
        $end   = date('Y-m-t', strtotime($start));

        $records = $this->model->findForPeriod($unitIds, $start, $end);
        $maps    = $this->customerMapForPeriod($unitIds, $start, $end);

        $units = [];
        foreach ($unitIds as $u) {
            $units[$u] = ['review' => 0, 'customer' => 0, 'persen' => 0.0];
        }

        foreach ($records as $rec) {
            $u = (int)$rec->id_unit;
            if (!isset($units[$u])) {
                continue;
            }
            $customers = $maps[$u][$rec->tanggal] ?? 0;
            $units[$u]['review']   += (int)$rec->jumlah_review;
            $units[$u]['customer'] += $customers;

            // Guard: data transaksi hilang/0 customer pd hari tercatat → review tetap masuk?
            // Per spesifikasi SUM(review)/SUM(customer); hari tanpa customer dgn review
            // >0 tidak mungkin (divalidasi saat save). Tetap dealokasikan dari total.
        }

        foreach ($units as $u => &$agg) {
            $agg['persen'] = $this->dailyPercentage($agg['review'], $agg['customer']);
        }
        unset($agg);

        $totalReview   = array_sum(array_column($units, 'review'));
        $totalCustomer = array_sum(array_column($units, 'customer'));

        return [
            'units' => $units,
            'total' => [
                'review'   => $totalReview,
                'customer' => $totalCustomer,
                'persen'   => $this->dailyPercentage($totalReview, $totalCustomer),
            ],
        ];
    }

    /**
     * Nilai KPI SPV (0-100) utk periode: SUM(review)/SUM(customer) x 100.
     * Null bila tidak ada data harian sama sekali.
     */
    public function spvSatisfaction(int $supervisorId, int $ownUnit, string $bulan, string $tahun): ?float
    {
        $units = $this->scopeUnits($supervisorId, $ownUnit);
        $start = sprintf('%04d-%02d-01', (int)$tahun, (int)$bulan);
        $end   = date('Y-m-t', strtotime($start));

        $records = $this->model->findForPeriod($units, $start, $end);
        if (empty($records)) {
            return null;
        }

        $maps = $this->customerMapForPeriod($units, $start, $end);
        $totalReview = 0;
        $totalCustomer = 0;
        foreach ($records as $rec) {
            $totalReview += (int)$rec->jumlah_review;
            $totalCustomer += (int)($maps[(int)$rec->id_unit][$rec->tanggal] ?? 0);
        }

        if ($totalCustomer <= 0) {
            return 0.0;
        }

        return round($totalReview / $totalCustomer * 100, 4);
    }
}