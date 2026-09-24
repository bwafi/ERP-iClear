<?php

namespace App\Services\Konten;

/**
 * Multimedia KPI — struktur OWNER (jabatan 44).
 *
 *   | KPI                | Bobot | Formula                                       |
 *   | Ketepatan Deadline | 25%   | on_time / total_assigned × 100                |
 *   | Kualitas Output    | 25%   | qc_pass / total_assigned × 100                |
 *   | Kesesuaian Brief   | 20%   | sesuai_brief / berbrief_dinilai × 100          |
 *   | Produktivitas      | 15%   | completed / TARGET_PRODUKTIVITAS × 100         |
 *   | Support Campaign   | 10%   | campaign_selesai / campaign_target × 100       |
 *   | Improvement        | 5%    | approved / TARGET_IMPROVEMENT × 100            |
 *
 * SEMUA achievement dihitung PER EMPLOYEE (attribution via content_people /
 * improvements.employee_id), bukan total divisi. Cap 100 (pedoman §XX:
 * KPI Akhir tidak boleh melebihi 100%; achievement tidak boleh > 100).
 *
 * "assigned" = content yang menautkan employee melalui content_people
 * (role CREATIVE; TALENT ikut dihitung bila employee ybs jabatan 44).
 */
class MultimediaKpiService
{
    public const CODES = [
        'KETEPATAN_DEADLINE',
        'KUALITAS_OUTPUT',
        'KESESUAIAN_BRIEF',
        'PRODUKTIVITAS',
        'SUPPORT_CAMPAIGN',
        'IMPROVEMENT',
    ];

    public const TARGET_PRODUKTIVITAS = 30;   // konten selesai / employee / bulan
    public const TARGET_IMPROVEMENT   = 1;    // improvement disetujui / employee / bulan

    /**
     * Achievement satuan utk engine KPI (per employee).
     *
     * @return float|null null bila tidak ada data (dilewatkan total, bukan 0).
     */
    public function achievement(string $code, int $employeeId, int $unitId, int $month, int $year): ?float
    {
        switch ($code) {
            case 'KETEPATAN_DEADLINE':
                return $this->deadlineAchievement($employeeId, $month, $year);

            case 'KUALITAS_OUTPUT':
                return $this->kualitasAchievement($employeeId, $month, $year);

            case 'KESESUAIAN_BRIEF':
                return $this->briefAchievement($employeeId, $month, $year);

            case 'PRODUKTIVITAS':
                return $this->produktivitasAchievement($employeeId, $month, $year);

            case 'SUPPORT_CAMPAIGN':
                return $this->campaignAchievement($employeeId, $month, $year, $unitId);

            case 'IMPROVEMENT':
                return $this->improvementAchievement($employeeId, $month, $year);

            default:
                return null;
        }
    }

    /**
     * Scope fragment: content yang "dimiliki" employee (content_people).
     * role TALENT ikut bila employee jabatan 44 (orang bisa tampil + mengerjakan).
     */
    private function assignedExpr(int $employeeId): string
    {
        return "EXISTS(SELECT 1 FROM content_people cp
                WHERE cp.content_id = c.id AND cp.akun_id = {$employeeId})";
    }

    /**
     * @return array{0: string, 1: array} [WHERE (bisa AND), param pakai '?' ]
     */
    private function assignedWhere(int $employeeId, int $month, int $year): array
    {
        $expr  = $this->assignedExpr($employeeId);
        $where = "( DATE_FORMAT(c.deadline, '%Y-%m') = ? AND {$expr} )";
        return [$where, [sprintf('%04d-%02d', $year, $month)]];
    }

    private function deadlineAchievement(int $employeeId, int $month, int $year): ?float
    {
        [$where, $params] = $this->assignedWhere($employeeId, $month, $year);

        $db = \Config\Database::connect();
        $total = (int)$db->query(
            "SELECT COUNT(*) jml FROM contents c WHERE {$where}",
            $params
        )->getRow()->jml;
        if ($total <= 0) {
            return null;
        }

        $onTime = (int)$db->query(
            "SELECT COUNT(*) jml FROM contents c
             WHERE {$where} AND c.status IN ('PUBLISHED', 'COMPLETED')
               AND COALESCE(c.completed_at, c.published_at) <= CONCAT(c.deadline, ' 23:59:59')",
            $params
        )->getRow()->jml;

        return round(min(100.0, $onTime / $total * 100), 2);
    }

    private function kualitasAchievement(int $employeeId, int $month, int $year): ?float
    {
        [$where, $params] = $this->assignedWhere($employeeId, $month, $year);

        $db = \Config\Database::connect();
        $total = (int)$db->query(
            "SELECT COUNT(*) jml FROM contents c WHERE {$where}",
            $params
        )->getRow()->jml;
        if ($total <= 0) {
            return null;
        }

        // Lolos QC: content yang pernah tercatat QC PASS (distinct).
        $qcPass = 0;
        $rows = $db->query(
            "SELECT c.id FROM contents c
             WHERE {$where}
               AND EXISTS(SELECT 1 FROM content_qc q WHERE q.content_id = c.id AND q.status = 'PASS')",
            $params
        )->getResult();
        $qcPass = count($rows);

        return round(min(100.0, $qcPass / $total * 100), 2);
    }

    /**
     * Kesesuaian Brief: konten yang DINILAI kesesuaiannya (ada brief + verdict
     * sesuai_brief di content_qc). Denominator = konten ber-brief yang dinilai;
     * numerator = yang diberi verdict "sesuai" (sesuai_brief = 1).
     */
    private function briefAchievement(int $employeeId, int $month, int $year): ?float
    {
        [$where, $params] = $this->assignedWhere($employeeId, $month, $year);

        $db = \Config\Database::connect();
        $dinilai = 0;
        $sesuai = 0;
        $rows = $db->query(
            "SELECT
                COUNT(DISTINCT c.id) AS dinilai,
                COUNT(DISTINCT CASE WHEN q.sesuai_brief = 1 THEN c.id END) AS sesuai
             FROM contents c
             JOIN content_qc q ON q.content_id = c.id AND q.sesuai_brief IS NOT NULL
             WHERE {$where}",
            $params
        )->getRow();

        $dinilai = (int)$rows->dinilai;
        $sesuai  = (int)$rows->sesuai;
        if ($dinilai <= 0) {
            return null;
        }

        return round(min(100.0, $sesuai / $dinilai * 100), 2);
    }

    private function produktivitasAchievement(int $employeeId, int $month, int $year): ?float
    {
        [$where, $params] = $this->assignedWhere($employeeId, $month, $year);

        $db = \Config\Database::connect();
        $total = (int)$db->query(
            "SELECT COUNT(*) jml FROM contents c WHERE {$where}",
            $params
        )->getRow()->jml;
        if ($total <= 0) {
            return null;
        }

        $completed = (int)$db->query(
            "SELECT COUNT(*) jml FROM contents c
             WHERE {$where} AND c.status = 'COMPLETED'",
            $params
        )->getRow()->jml;

        return round(min(100.0, $completed / self::TARGET_PRODUKTIVITAS * 100), 2);
    }

    /**
     * Support Campaign: konten campaign yang dikerjakan employee & selesai
     * tepat waktu (publikasi ≤ target_deadline campaign) dibanding target
     * jumlah konten campaign periode tsb (campaign.target_jumlah_konten).
     * Tanpa target campaign→ null (tidak menghukum, menunggu data).
     */
    private function campaignAchievement(int $employeeId, int $month, int $year, int $unitId): ?float
    {
        $db = \Config\Database::connect();
        $campaigns = $db->table('content_campaigns')
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->where('status', 'active')
            ->get()
            ->getResult();
        if (empty($campaigns)) {
            return null;
        }

        $campaignIds = array_map(fn($r) => (int)$r->id, $campaigns);
        $in = implode(',', $campaignIds);

        $selesai = 0;
        $target = 0;
        foreach ($campaigns as $c) {
            $target += (int)($c->target_jumlah_konten ?? 0);
        }

        $rows = $db->query(
            "SELECT c.id, c.status, c.published_at, c.completed_at, c.campaign_id
             FROM contents c
             WHERE c.campaign_id IN ({$in})
               AND {$this->assignedExpr($employeeId)}"
        )->getResult();

        foreach ($rows as $r) {
            if (!in_array($r->status, ['PUBLISHED', 'COMPLETED'], true)) {
                continue;
            }
            $tglSelesai = $r->completed_at ?: $r->published_at;
            $deadlineCmp = null;
            foreach ($campaigns as $c) {
                if ($c->id === $r->campaign_id) {
                    $deadlineCmp = $c->target_deadline;
                    break;
                }
            }
            if ($deadlineCmp && $tglSelesai && $tglSelesai <= date('Y-m-d 23:59:59', strtotime($deadlineCmp))) {
                $selesai++;
            } elseif (!$deadlineCmp) {
                $selesai++;
            }
        }

        if ($target <= 0) {
            return null;
        }

        return round(min(100.0, $selesai / $target * 100), 2);
    }

    /**
     * Improvement: improvement employee yang di-setuju (approved/implemented)
     * pada bulan berjalan dibanding target bulanan.
     */
    private function improvementAchievement(int $employeeId, int $month, int $year): ?float
    {
        $db = \Config\Database::connect();
        $total = (int)$db->table('improvements')
            ->where('employee_id', $employeeId)
            ->where('submission_month', $month)
            ->where('submission_year', $year)
            ->countAllResults();
        if ($total <= 0) {
            return null;
        }

        $approved = (int)$db->table('improvements')
            ->where('employee_id', $employeeId)
            ->where('submission_month', $month)
            ->where('submission_year', $year)
            ->whereIn('status', ['approved', 'implemented'])
            ->countAllResults();

        return round(min(100.0, $approved / self::TARGET_IMPROVEMENT * 100), 2);
    }

    /**
     * Ringkasan divisi utk dashboard (/konten/dashboard) — 6 KPI OWNER.
     *
     * Mengagregasi seluruh pegawai aktif jabatan 44: realisasi = jumlah
     * pembilang/penyebut digabung seluruh employee; achievement = persentase
     * agregat (cap 100). Bobot mengikuti kpi_weights posisi 44 (25/25/20/15/10/5).
     *
     * @return array{items:array, weighted_total:float}
     */
    public function monthlySummary(int $month, int $year): array
    {
        $db = \Config\Database::connect();

        $employees = $db->table('akun')
            ->select('ID_AKUN')
            ->where('ID_JABATAN', 44)
            ->where('STATUS_PEGAWAI', 1)
            ->get()
            ->getResult();
        $empIds = array_map(static fn($r) => (int)$r->ID_AKUN, $employees);

        // Aggregates mentah per komponen (jumlah seluruh employee).
        $num = $den = [];
        foreach (self::CODES as $code) {
            $num[$code] = 0;
            $den[$code] = 0;
        }

        foreach ($empIds as $empId) {
            $sum = $this->rawAggregates($empId, $month, $year);
            foreach (self::CODES as $code) {
                $num[$code] += $sum[$code]['num'];
                $den[$code] += $sum[$code]['den'];
            }
        }

        // Bobot posisi 44 dari DB.
        $weights = [];
        $rows = $db->query(
            "SELECT c.code, w.weight
             FROM kpi_weights w
             JOIN kpi_components c ON c.id = w.kpi_component_id
             WHERE w.position_id = 44 AND w.weight_group = 'kpi'"
        )->getResult();
        foreach ($rows as $r) {
            $weights[(string)$r->code] = (float)$r->weight;
        }

        $nameMap = [
            'KETEPATAN_DEADLINE' => 'Ketepatan Deadline',
            'KUALITAS_OUTPUT'    => 'Kualitas Output',
            'KESESUAIAN_BRIEF'   => 'Kesesuaian Brief',
            'PRODUKTIVITAS'      => 'Produktivitas',
            'SUPPORT_CAMPAIGN'   => 'Support Campaign',
            'IMPROVEMENT'        => 'Improvement',
        ];
        $targetMap = [
            'KETEPATAN_DEADLINE' => '100% tepat waktu',
            'KUALITAS_OUTPUT'    => '100% lolos QC',
            'KESESUAIAN_BRIEF'   => '100% sesuai brief',
            'PRODUKTIVITAS'      => self::TARGET_PRODUKTIVITAS . ' konten/org/bulan',
            'SUPPORT_CAMPAIGN'   => '100% konten campaign selesai',
            'IMPROVEMENT'        => self::TARGET_IMPROVEMENT . ' improvement/org/bulan',
        ];
        $realisasiMap = [
            'KETEPATAN_DEADLINE' => static fn(int $n, int $d) => "{$n}/{$d} tepat waktu",
            'KUALITAS_OUTPUT'    => static fn(int $n, int $d) => "{$n}/{$d} lolos QC",
            'KESESUAIAN_BRIEF'   => static fn(int $n, int $d) => "{$n}/{$d} sesuai brief",
            'PRODUKTIVITAS'      => static fn(int $n, int $d) => "{$n}/{$d} konten selesai",
            'SUPPORT_CAMPAIGN'   => static fn(int $n, int $d) => "{$n}/{$d} konten campaign",
            'IMPROVEMENT'        => static fn(int $n, int $d) => "{$n}/{$d} improvement disetujui",
        ];

        $items = [];
        $weightedTotal = 0.0;
        foreach (self::CODES as $code) {
            $bobot = $weights[$code] ?? 0.0;
            $n = $num[$code];
            $d = $den[$code];
            $achievement = $d > 0 ? round(min(100.0, $n / $d * 100), 2) : null;

            $items[] = [
                'code'        => $code,
                'name'        => $nameMap[$code],
                'bobot'       => $bobot,
                'target'      => $targetMap[$code],
                'realisasi'   => $d > 0 ? $realisasiMap[$code]($n, $d) : 'Belum ada data',
                'achievement' => $achievement,
                'weighted'    => $achievement !== null ? round($achievement * $bobot / 100, 2) : null,
            ];
            if ($achievement !== null) {
                $weightedTotal += $achievement * $bobot / 100;
            }
        }

        return ['items' => $items, 'weighted_total' => round($weightedTotal, 2), 'employee_count' => count($empIds)];
    }

    /**
     * Agregat mentah (num/den) SATU employee utk 6 komponen — divisi dashboard.
     */
    private function rawAggregates(int $employeeId, int $month, int $year): array
    {
        $db = \Config\Database::connect();
        [$where, $params] = $this->assignedWhere($employeeId, $month, $year);

        $total = (int)$db->query("SELECT COUNT(*) jml FROM contents c WHERE {$where}", $params)->getRow()->jml;

        $onTime = 0;
        $qcPass = 0;
        $dinilai = 0;
        $sesuai = 0;
        $completed = 0;
        $campaignSelesai = 0;
        $campaignTarget = 0;

        if ($total > 0) {
            $onTime = (int)$db->query(
                "SELECT COUNT(*) jml FROM contents c WHERE {$where}
                 AND c.status IN ('PUBLISHED','COMPLETED')
                 AND COALESCE(c.completed_at, c.published_at) <= CONCAT(c.deadline, ' 23:59:59')",
                $params
            )->getRow()->jml;

            $qcPass = (int)$db->query(
                "SELECT COUNT(DISTINCT c.id) jml FROM contents c
                 WHERE {$where}
                 AND EXISTS(SELECT 1 FROM content_qc q WHERE q.content_id = c.id AND q.status = 'PASS')",
                $params
            )->getRow()->jml;

            $briefRows = $db->query(
                "SELECT
                    COUNT(DISTINCT c.id) AS dinilai,
                    COUNT(DISTINCT CASE WHEN q.sesuai_brief = 1 THEN c.id END) AS sesuai
                 FROM contents c
                 JOIN content_qc q ON q.content_id = c.id AND q.sesuai_brief IS NOT NULL
                 WHERE {$where}",
                $params
            )->getRow();
            $dinilai = (int)($briefRows->dinilai ?? 0);
            $sesuai  = (int)($briefRows->sesuai ?? 0);

            $completed = (int)$db->query(
                "SELECT COUNT(*) jml FROM contents c WHERE {$where} AND c.status = 'COMPLETED'",
                $params
            )->getRow()->jml;
        }

        // SUPPORT_CAMPAIGN: campaign aktif periode tsb.
        $campaigns = $db->table('content_campaigns')
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->where('status', 'active')
            ->get()
            ->getResult();
        if ($campaigns) {
            $in = implode(',', array_map(static fn($c) => (int)$c->id, $campaigns));
            $deadlineMap = [];
            foreach ($campaigns as $c) {
                $campaignTarget += (int)($c->target_jumlah_konten ?? 0);
                $deadlineMap[(int)$c->id] = $c->target_deadline;
            }
            $rows = $db->query(
                "SELECT c.id, c.status, c.completed_at, c.published_at, c.campaign_id
                 FROM contents c
                 WHERE c.campaign_id IN ({$in}) AND {$this->assignedExpr($employeeId)}",
                []
            )->getResult();
            foreach ($rows as $r) {
                if (!in_array($r->status, ['PUBLISHED', 'COMPLETED'], true)) {
                    continue;
                }
                $deadlineCmp = $deadlineMap[(int)$r->campaign_id] ?? null;
                $doneAt = $r->completed_at ?: $r->published_at;
                if (!$deadlineCmp || ($doneAt && $doneAt <= date('Y-m-d 23:59:59', strtotime($deadlineCmp)))) {
                    $campaignSelesai++;
                }
            }
        }

        // IMPROVEMENT bulan ini.
        $improvementRows = $db->table('improvements')
            ->where('employee_id', $employeeId)
            ->where('submission_month', $month)
            ->where('submission_year', $year)
            ->get()
            ->getResult();
        $improvementDen = count($improvementRows);
        $improvementNum = 0;
        foreach ($improvementRows as $r) {
            if (in_array($r->status, ['approved', 'implemented'], true)) {
                $improvementNum++;
            }
        }

        return [
            'KETEPATAN_DEADLINE' => ['num' => $onTime,              'den' => $total],
            'KUALITAS_OUTPUT'    => ['num' => $qcPass,             'den' => $total],
            'KESESUAIAN_BRIEF'   => ['num' => $sesuai,             'den' => $dinilai],
            'PRODUKTIVITAS'      => ['num' => $completed,          'den' => max(self::TARGET_PRODUKTIVITAS, 1)],
            'SUPPORT_CAMPAIGN'   => ['num' => $campaignSelesai,    'den' => $campaignTarget],
            'IMPROVEMENT'        => ['num' => $improvementNum,     'den' => max($improvementDen, 1)],
        ];
    }
}