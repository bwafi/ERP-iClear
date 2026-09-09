<?php

namespace App\Services\Konten;

/**
 * Data operasional & ringkasan KPI Multimedia/Creative (dashboard).
 *
 * MENGULANGI BUKAN engine KPI: service ini hanya menyediakan data operasional
 * (statistik + achievement dari data konten per bulan) yang siap dikonsumsi
 * engine KPI existing. Bobot & target konten mengikuti spesifikasi KPI:
 *
 *   | KPI                 | Target        | Bobot |
 *   | Jumlah Konten       | 30/bulan      | 20%   |
 *   | Deadline            | ≥95%          | 20%   |
 *   | Kualitas Konten     | ≥90%          | 25%   |
 *   | Konsistensi Brand   | ≥95%          | 15%   |
 *   | Performa Konten     | sesuai target | 20%   |
 *
 * Satu content tetap dihitung SATU KALI pada setiap metrik (COUNT DISTINCT / agregasi per content).
 */
class ContentKpiService
{
    public const TARGET_KONTEN_PER_BULAN = 30;

    public const KPI_DEFS = [
        ['key' => 'JUMLAH_KONTEN', 'name' => 'Jumlah Konten',   'bobot' => 20, 'target' => '30/bulan'],
        ['key' => 'DEADLINE',      'name' => 'Deadline',        'bobot' => 20, 'target' => '≥95%'],
        ['key' => 'KUALITAS',      'name' => 'Kualitas Konten', 'bobot' => 25, 'target' => '≥90%'],
        ['key' => 'BRAND',         'name' => 'Konsistensi Brand', 'bobot' => 15, 'target' => '≥95%'],
        ['key' => 'PERFORMA',      'name' => 'Performa Konten', 'bobot' => 20, 'target' => 'sesuai target'],
    ];

    /**
     * Kode komponen KPI di engine existing (kpi_components) ↔ key ringkasan KPI konten.
     */
    public const COMPONENT_CODES = [
        'KONTEN_JUMLAH',
        'KONTEN_DEADLINE',
        'KONTEN_KUALITAS',
        'KONTEN_BRAND',
        'KONTEN_PERFORMA',
    ];

    public const COMPONENT_CODE_MAP = [
        'KONTEN_JUMLAH'   => 'JUMLAH_KONTEN',
        'KONTEN_DEADLINE' => 'DEADLINE',
        'KONTEN_KUALITAS' => 'KUALITAS',
        'KONTEN_BRAND'    => 'BRAND',
        'KONTEN_PERFORMA' => 'PERFORMA',
    ];

    private $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    /**
     * Score achievement per kode komponen (dipakai engine KPI existing via
     * KpiCalculationService untuk jabatan Multimedia).
     */
    public function scoreByCode(string $code, int $month, int $year, ?string $scopeSql = null): ?float
    {
        $key = self::COMPONENT_CODE_MAP[$code] ?? null;
        if ($key === null) {
            return null;
        }

        $kpi = $this->monthlyKpi($month, $year, $scopeSql);
        foreach ($kpi['items'] as $it) {
            if ($it['key'] === $key) {
                return $it['achievement'];
            }
        }

        return null;
    }

    /**
     * Statistik pipeline bulanan.
     */
    public function monthlyStats(int $month, int $year, ?string $scopeSql = null): array
    {
        $sql = [];
        $params = [];
        $sql[] = "DATE_FORMAT(c.deadline, '%Y-%m') = ?";
        $params[] = sprintf('%04d-%02d', $year, $month);
        if (!empty($scopeSql)) {
            $sql[] = $scopeSql;
        }
        $where = '( ' . implode(' AND ', $sql) . ' )';

        $out = ['total' => 0, 'draft' => 0, 'production' => 0, 'qc' => 0, 'approved' => 0, 'published' => 0, 'completed' => 0, 'revision' => 0, 'overdue' => 0];

        $rows = $this->db->query(
            "SELECT c.status, COUNT(*) AS jml
             FROM contents c
             WHERE {$where}
             GROUP BY c.status",
            $params
        )->getResult();

        foreach ($rows as $r) {
            $status = strtolower($r->status);
            if (isset($out[$status])) {
                $out[$status] = (int)$r->jml;
            }
            $out['total'] += (int)$r->jml;
        }

        $out['overdue'] = (int)$this->db->query(
            "SELECT COUNT(*) AS jml
             FROM contents c
             WHERE {$where}
               AND c.deadline < CURDATE()
               AND c.status NOT IN ('PUBLISHED', 'COMPLETED')",
            $params
        )->getRow()->jml;

        return $out;
    }

    /**
     * Ringkasan KPI Creative bulanan.
     *
     * @return array dengan keys: total, completed, on_time, qc_pass,
     *      brand(total_items, checked_items), perf(target, actual),
     *      items[], weighted_total
     */
    public function monthlyKpi(int $month, int $year, ?string $scopeSql = null): array
    {
        $sql = [];
        $params = [];
        $sql[] = "DATE_FORMAT(c.deadline, '%Y-%m') = ?";
        $params[] = sprintf('%04d-%02d', $year, $month);
        if (!empty($scopeSql)) {
            $sql[] = $scopeSql;
        }
        $where = '( ' . implode(' AND ', $sql) . ' )';

        $total = (int)$this->db->query("SELECT COUNT(*) jml FROM contents c WHERE {$where}", $params)->getRow()->jml;
        $completed = (int)$this->db->query("SELECT COUNT(*) jml FROM contents c WHERE {$where} AND c.status = 'COMPLETED'", $params)->getRow()->jml;

        // Tepat waktu: status PUBLISHED/COMPLETED dan tanggal selesai ≤ deadline.
        $onTime = (int)$this->db->query(
            "SELECT COUNT(*) jml FROM contents c
             WHERE {$where} AND c.status IN ('PUBLISHED', 'COMPLETED')
               AND COALESCE(c.completed_at, c.published_at) <= CONCAT(c.deadline, ' 23:59:59')",
            $params
        )->getRow()->jml;

        $contentIds = $this->contentIdsInScope($where, $params);

        // Lolos QC: content yang pernah tercatat QC PASS.
        $qcPass = $this->countQcPass($contentIds);

        // Brand checklist.
        $brand = $this->brandSummary($contentIds);

        // Performa publikasi: sum actual / sum target periode tsb (scope content).
        $perf = $this->performanceSummary($month, $year, $scopeSql);

        $items = [];
        foreach (self::KPI_DEFS as $def) {
            $achievement = null;
            switch ($def['key']) {
                case 'JUMLAH_KONTEN':
                    $achievement = $total > 0 ? $completed / self::TARGET_KONTEN_PER_BULAN * 100 : null;
                    break;
                case 'DEADLINE':
                    $achievement = $total > 0 ? $onTime / $total * 100 : null;
                    break;
                case 'KUALITAS':
                    $achievement = $total > 0 ? $qcPass / $total * 100 : null;
                    break;
                case 'BRAND':
                    $achievement = $brand['total_items'] > 0 ? $brand['checked_items'] / $brand['total_items'] * 100 : null;
                    break;
                case 'PERFORMA':
                    $achievement = $perf['target'] > 0 ? $perf['actual'] / $perf['target'] * 100 : null;
                    break;
            }

            // Achievement KPI maksimal 100 (tidak boleh tembus 100%).
            if ($achievement !== null) {
                $achievement = min(100.0, $achievement);
            }

            $items[] = [
                'key'         => $def['key'],
                'name'        => $def['name'],
                'bobot'       => $def['bobot'],
                'target'      => $def['target'],
                'achievement' => $achievement === null ? null : round($achievement, 2),
                'realisasi'   => $this->realisasiLabel($def['key'], $total, $completed, $onTime, $qcPass, $brand, $perf),
            ];
        }

        $weightedTotal = 0.0;
        foreach ($items as $it) {
            if ($it['achievement'] !== null) {
                $weightedTotal += $it['achievement'] * $it['bobot'] / 100;
            }
        }

        return [
            'total'          => $total,
            'completed'      => $completed,
            'on_time'        => $onTime,
            'qc_pass'        => $qcPass,
            'brand'          => $brand,
            'perf'           => $perf,
            'items'          => $items,
            'weighted_total' => round($weightedTotal, 2),
        ];
    }

    private function contentIdsInScope(string $where, array $params): array
    {
        $rows = $this->db->query("SELECT c.id FROM contents c WHERE {$where}", $params)->getResult();

        return array_map('intval', array_column($rows, 'id'));
    }

    private function countQcPass(array $contentIds): int
    {
        if (empty($contentIds)) {
            return 0;
        }
        $ids = implode(',', $contentIds);

        return (int)$this->db->query(
            "SELECT COUNT(DISTINCT content_id) jml
             FROM content_qc
             WHERE status = 'PASS' AND content_id IN ({$ids})"
        )->getRow()->jml;
    }

    private function brandSummary(array $contentIds): array
    {
        if (empty($contentIds)) {
            return ['total_items' => 0, 'checked_items' => 0];
        }
        $ids = implode(',', $contentIds);
        $row = $this->db->query(
            "SELECT COUNT(*) total_items, COALESCE(SUM(is_checked), 0) checked_items
             FROM content_checklists
             WHERE content_id IN ({$ids})"
        )->getRow();

        return ['total_items' => (int)$row->total_items, 'checked_items' => (int)$row->checked_items];
    }

    private function performanceSummary(int $month, int $year, ?string $scopeSql): array
    {
        $whereContent = "DATE_FORMAT(c.deadline, '%Y-%m') = '" . sprintf('%04d-%02d', $year, $month) . "'";
        $whereContent .= " AND c.jenis_konten = 'ADS'";
        if (!empty($scopeSql)) {
            $whereContent .= ' AND ' . $scopeSql;
        }

        $rows = $this->db->query(
            "SELECT pp.metric_id, pp.period_month, pp.period_year,
                    SUM(pp.actual) AS actual, SUM(pp.target) AS target
             FROM publication_performance pp
             JOIN publications p ON p.id = pp.publication_id
             JOIN contents c ON c.id = p.content_id
             WHERE pp.period_month = {$month} AND pp.period_year = {$year}
               AND ({$whereContent})
             GROUP BY pp.metric_id, pp.period_month, pp.period_year"
        )->getResult();

        $actual = 0.0;
        $target = 0.0;
        foreach ($rows as $r) {
            $actual += (float)$r->actual;
            $target += (float)$r->target;
        }

        return ['actual' => round($actual, 2), 'target' => round($target, 2)];
    }

    private function realisasiLabel(string $key, int $total, int $completed, int $onTime, int $qcPass, array $brand, array $perf): string
    {
        switch ($key) {
            case 'JUMLAH_KONTEN':
                return "{$completed} selesai / 30";
            case 'DEADLINE':
                return "{$onTime} tepat waktu / {$total} konten";
            case 'KUALITAS':
                return "{$qcPass} lolos QC / {$total} konten";
            case 'BRAND':
                return "{$brand['checked_items']} / {$brand['total_items']} item";
            case 'PERFORMA':
                return number_format($perf['actual'], 0, ',', '.') . ' / ' . number_format($perf['target'], 0, ',', '.');
        }

        return '';
    }
}