<?php

namespace App\Services\Marketing;

use App\Models\ModelMarketingLead;
use App\Models\ModelMarketingRekapHarian;
use App\Models\ModelMarketingRekapHarianDetail;

/**
 * Rekap Marketing Harian Manual — SOURCE OF TRUTH KPI Marketing.
 *
 * CS mengisi angka harian per cabang (platform, non iklan, iklan, prospek,
 * datang). Dari sini KPI Marketing dihitung (lead, iklan, prospek, datang,
 * rate). Kommo TIDAK ikut masuk angka ini (Kommo hanya untuk CRM).
 */
class MarketingRekapService
{
    /**
     * Platform yang dijumlahkan sebagai "Total Lead WA/DM" (case-insensitive,
     * normalisasi spasi/tanda). Bukan tabel master — cukup senarai ini.
     */
    public const PLATFORM_WA_DM = ['WhatsApp', 'Instagram'];

    private $headerModel;
    private $detailModel;
    private $leadModel;

    public function __construct()
    {
        $this->headerModel = new ModelMarketingRekapHarian();
        $this->detailModel = new ModelMarketingRekapHarianDetail();
        $this->leadModel   = new ModelMarketingLead();
    }

    // ── Simpan ────────────────────────────────────────────────────

    /**
     * Simpan/update rekap harian (upsert per unit+tanggal, replace detail).
     *
     * @param list<array{platform:string, non_iklan:int, iklan:int, prospek:int, datang:int}> $details
     * @throws \InvalidArgumentException bila data tidak valid.
     */
    public function save(int $unitId, string $tanggal, array $details, int $leadTotalIklanDashboard, ?int $userId = null): array
    {
        if ($unitId <= 0) {
            throw new \InvalidArgumentException('Cabang wajib dipilih.');
        }
        $tanggal = $this->validTanggal($tanggal);

        $clean = [];
        foreach ($details as $d) {
            if (!is_array($d)) {
                continue;
            }
            $platform = trim((string)($d['platform'] ?? ''));
            if ($platform === '') {
                continue; // baris platform kosong diabaikan
            }
            $nonIklan = max(0, (int)($d['non_iklan'] ?? 0));
            $iklan    = max(0, (int)($d['iklan'] ?? 0));
            $total    = $nonIklan + $iklan;
            $prospek  = max(0, (int)($d['prospek'] ?? 0));
            $datang   = max(0, (int)($d['datang'] ?? 0));
            $rate     = $total > 0 ? round($datang / $total * 100, 2) : 0.0;

            $clean[] = [
                'platform'  => mb_substr($platform, 0, 50),
                'non_iklan' => $nonIklan,
                'iklan'     => $iklan,
                'total'     => $total,
                'prospek'   => $prospek,
                'datang'    => $datang,
                'rate'      => $rate,
            ];
        }

        // Total Lead WA/DM = total platform pada PLATFORM_WA_DM.
        $totalWaDm = 0;
        foreach ($clean as $d) {
            if (in_array($this->normalizePlatform($d['platform']), $this->normalizedWaDmSet(), true)) {
                $totalWaDm += $d['total'];
            }
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            $header = $this->headerModel->getByUnitDate($unitId, $tanggal);
            if ($header) {
                $this->headerModel->update((int)$header->id, [
                    'total_lead_wa_dm'           => $totalWaDm,
                    'lead_total_iklan_dashboard' => max(0, $leadTotalIklanDashboard),
                ]);
                $rekapId = (int)$header->id;
            } else {
                $rekapId = (int)$this->headerModel->insert([
                    'tanggal'                    => $tanggal,
                    'unit_id'                    => $unitId,
                    'total_lead_wa_dm'           => $totalWaDm,
                    'lead_total_iklan_dashboard' => max(0, $leadTotalIklanDashboard),
                    'created_by'                 => $userId,
                ]);
                if (!$rekapId) {
                    throw new \RuntimeException('Gagal menyimpan rekap harian.');
                }
            }

            // Replace detail (simple & aman untuk struktur fleksibel).
            $this->detailModel->where('rekap_id', $rekapId)->delete();
            foreach ($clean as $d) {
                $this->detailModel->insert(['rekap_id' => $rekapId] + $d);
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        return [
            'rekap_id'        => $rekapId,
            'total_lead_wa_dm'=> $totalWaDm,
            'lead_total_iklan_dashboard' => max(0, $leadTotalIklanDashboard),
            'platforms'       => array_column($clean, 'platform'),
        ];
    }

    // ── Baca ──────────────────────────────────────────────────────

    /** Header + detail untuk (unit, tanggal). null bila belum ada rekap. */
    public function getByDate(int $unitId, string $tanggal): ?array
    {
        $tanggal = $this->validTanggal($tanggal);
        $header  = $this->headerModel->getByUnitDate($unitId, $tanggal);
        if (!$header) {
            return null;
        }
        return [
            'header'  => $header,
            'details' => $this->detailModel->getByRekapId((int)$header->id),
        ];
    }

    /** Total lead dari rekap (sum detail.total) dalam satu bulan. */
    public function monthlyLeadTotal(int $month, int $year): int
    {
        $m = sprintf('%04d-%02d', $year, $month);
        return (int)$this->detailModel
            ->join('marketing_rekap_harian', 'marketing_rekap_harian.id = marketing_rekap_harian_detail.rekap_id', 'inner')
            ->where("DATE_FORMAT(marketing_rekap_harian.tanggal, '%Y-%m') = '{$m}'", null, false)
            ->selectSum('total', 'sum')
            ->first()->sum ?? 0;
    }

    /** Lead iklan (sum detail.iklan) dalam satu bulan. */
    public function monthlyPaidTotal(int $month, int $year): int
    {
        $m = sprintf('%04d-%02d', $year, $month);
        return (int)$this->detailModel
            ->join('marketing_rekap_harian', 'marketing_rekap_harian.id = marketing_rekap_harian_detail.rekap_id', 'inner')
            ->where("DATE_FORMAT(marketing_rekap_harian.tanggal, '%Y-%m') = '{$m}'", null, false)
            ->selectSum('iklan', 'sum')
            ->first()->sum ?? 0;
    }

    /**
     * Ringkasan bulanan rekap semua cabang (format kartu dashboard):
     * non_iklan, iklan, total, prospek, datang, rate, total_lead_wa_dm,
     * lead_total_iklan_dashboard, + rincian per cabang.
     */
    public function monthlySummary(int $month, int $year): array
    {
        $m = sprintf('%04d-%02d', $year, $month);

        $agg = $this->detailModel
            ->join('marketing_rekap_harian', 'marketing_rekap_harian.id = marketing_rekap_harian_detail.rekap_id', 'inner')
            ->where("DATE_FORMAT(marketing_rekap_harian.tanggal, '%Y-%m') = '{$m}'", null, false)
            ->selectSum('non_iklan', 'non_iklan')
            ->selectSum('iklan', 'iklan')
            ->selectSum('total', 'total')
            ->selectSum('prospek', 'prospek')
            ->selectSum('datang', 'datang')
            ->first();

        // Total Lead WA/DM dihitung dari detail (WhatsApp + Instagram), bukan
        // kolom header — menjamin konsistensi walau ada data lama.
        $waDm = (int)$this->detailModel
            ->join('marketing_rekap_harian', 'marketing_rekap_harian.id = marketing_rekap_harian_detail.rekap_id', 'inner')
            ->where("DATE_FORMAT(marketing_rekap_harian.tanggal, '%Y-%m') = '{$m}'", null, false)
            ->where("UPPER(TRIM(marketing_rekap_harian_detail.platform)) IN ('WHATSAPP','INSTAGRAM')", null, false)
            ->selectSum('total', 'wa_dm')
            ->first()->wa_dm ?? 0;

        // Lead Total Iklan (Dashboard) adalah angka terpisah yang dikelola
        // di section khusus — dibaca dari header (bukan diturunkan).
        $headerAgg = $this->headerModel
            ->where("DATE_FORMAT(tanggal, '%Y-%m') = '{$m}'", null, false)
            ->selectSum('lead_total_iklan_dashboard', 'iklan_dash')
            ->first();

        $total  = (int)($agg->total ?? 0);
        $datang = (int)($agg->datang ?? 0);

        // Omzet & customer dari rekap? TIDAK — KPI closing tetap dari CRM
        // (marketing_lead). Rekap hanya angka harian/kuantitas.

        return [
            'non_iklan' => (int)($agg->non_iklan ?? 0),
            'iklan'     => (int)($agg->iklan ?? 0),
            'total'     => $total,
            'prospek'   => (int)($agg->prospek ?? 0),
            'datang'    => $datang,
            'rate'      => $total > 0 ? round($datang / $total * 100, 2) : 0.0,
            'total_lead_wa_dm'           => (int)$waDm,
            'lead_total_iklan_dashboard' => (int)($headerAgg->iklan_dash ?? 0),
            'total_days' => (int)$this->headerModel
                ->where("DATE_FORMAT(tanggal, '%Y-%m') = '{$m}'", null, false)
                ->countAllResults(),
        ];
    }

    /**
     * Ringkasan bulanan per cabang + rincian per platform.
     * Digunakan dashboard agar breakdown antar cabang jelas.
     *
     * @return list<array{unit_id:int, unit_name:string, platform:array<string,array>, total:int, iklan:int, non_iklan:int, prospek:int, datang:int, rate:float, total_lead_wa_dm:int}>
     */
    public function monthlySummaryByUnit(int $month, int $year): array
    {
        $m = sprintf('%04d-%02d', $year, $month);

        $rows = $this->detailModel
            ->join('marketing_rekap_harian', 'marketing_rekap_harian.id = marketing_rekap_harian_detail.rekap_id', 'inner')
            ->where("DATE_FORMAT(marketing_rekap_harian.tanggal, '%Y-%m') = '{$m}'", null, false)
            ->select('marketing_rekap_harian.unit_id AS unit_id')
            ->select('marketing_rekap_harian_detail.platform AS platform')
            ->selectSum('non_iklan', 'non_iklan')
            ->selectSum('iklan', 'iklan')
            ->selectSum('total', 'total')
            ->selectSum('prospek', 'prospek')
            ->selectSum('datang', 'datang')
            ->groupBy(['marketing_rekap_harian.unit_id', 'marketing_rekap_harian_detail.platform'])
            ->get()
            ->getResultArray();

        $units = $this->headerModel
            ->join('unit', 'unit.idunit = marketing_rekap_harian.unit_id', 'inner')
            ->where("DATE_FORMAT(marketing_rekap_harian.tanggal, '%Y-%m') = '{$m}'", null, false)
            ->select('marketing_rekap_harian.unit_id AS unit_id')
            ->select('unit.NAMA_UNIT AS unit_name')
            ->distinct()
            ->get()
            ->getResultArray();

        $byUnit = [];
        foreach ($units as $u) {
            $byUnit[(int)$u['unit_id']] = [
                'unit_id'   => (int)$u['unit_id'],
                'unit_name' => (string)$u['unit_name'],
                'platform'  => [],
                'total'     => 0,
                'non_iklan' => 0,
                'iklan'     => 0,
                'prospek'   => 0,
                'datang'    => 0,
                'rate'      => 0.0,
                'total_lead_wa_dm' => 0,
            ];
        }

        foreach ($rows as $r) {
            $uid = (int)$r['unit_id'];
            if (!isset($byUnit[$uid])) {
                continue;
            }
            $byUnit[$uid]['platform'][(string)$r['platform']] = [
                'non_iklan' => (int)$r['non_iklan'],
                'iklan'     => (int)$r['iklan'],
                'total'     => (int)$r['total'],
                'prospek'   => (int)$r['prospek'],
                'datang'    => (int)$r['datang'],
            ];
            if ($this->isWaDmPlatform((string)$r['platform'])) {
                $byUnit[$uid]['total_lead_wa_dm'] += (int)$r['total'];
            }
            $byUnit[$uid]['non_iklan'] += (int)$r['non_iklan'];
            $byUnit[$uid]['iklan']     += (int)$r['iklan'];
            $byUnit[$uid]['total']     += (int)$r['total'];
            $byUnit[$uid]['prospek']   += (int)$r['prospek'];
            $byUnit[$uid]['datang']    += (int)$r['datang'];
        }
        foreach ($byUnit as &$u) {
            $u['rate'] = $u['total'] > 0 ? round($u['datang'] / $u['total'] * 100, 2) : 0.0;
        }

        usort($byUnit, fn($a, $b) => $a['unit_id'] <=> $b['unit_id']);
        return array_values($byUnit);
    }

    /**
     * Daftar rekap harian dalam satu bulan (untuk halaman rekap), urut
     * tanggal terbaru. Per rekap: agregat header + rincian per platform.
     *
     * @return list<array{tanggal:string, unit_id:int, unit_name:string, wa_dm:int, iklan_dash:int, platforms:list<array>, total:int, non_iklan:int, iklan:int, prospek:int, datang:int, rate:float}>
     */
    public function listByMonth(int $month, int $year): array
    {
        $m = sprintf('%04d-%02d', $year, $month);

        $rows = $this->detailModel
            ->join('marketing_rekap_harian', 'marketing_rekap_harian.id = marketing_rekap_harian_detail.rekap_id', 'inner')
            ->join('unit', 'unit.idunit = marketing_rekap_harian.unit_id', 'inner')
            ->where("DATE_FORMAT(marketing_rekap_harian.tanggal, '%Y-%m') = '{$m}'", null, false)
            ->select('marketing_rekap_harian.tanggal AS tanggal')
            ->select('marketing_rekap_harian.unit_id AS unit_id')
            ->select('unit.NAMA_UNIT AS unit_name')
            ->select('marketing_rekap_harian.lead_total_iklan_dashboard AS iklan_dash')
            ->select('marketing_rekap_harian_detail.platform AS platform')
            ->selectSum('non_iklan', 'non_iklan')
            ->selectSum('iklan', 'iklan')
            ->selectSum('total', 'total')
            ->selectSum('prospek', 'prospek')
            ->selectSum('datang', 'datang')
            ->groupBy([
                'marketing_rekap_harian.tanggal',
                'marketing_rekap_harian.unit_id',
                'unit.NAMA_UNIT',
                'marketing_rekap_harian.lead_total_iklan_dashboard',
                'marketing_rekap_harian_detail.platform',
            ])
            ->orderBy('marketing_rekap_harian.tanggal', 'DESC')
            ->orderBy('marketing_rekap_harian.unit_id', 'ASC')
            ->get()
            ->getResultArray();

        $groups = [];
        foreach ($rows as $r) {
            $key = $r['tanggal'] . '|' . (int)$r['unit_id'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'tanggal'   => (string)$r['tanggal'],
                    'unit_id'   => (int)$r['unit_id'],
                    'unit_name' => (string)$r['unit_name'],
                    'wa_dm'     => 0,
                    'iklan_dash' => (int)$r['iklan_dash'],
                    'platforms' => [],
                    'non_iklan' => 0,
                    'iklan'     => 0,
                    'total'     => 0,
                    'prospek'   => 0,
                    'datang'    => 0,
                    'rate'      => 0.0,
                ];
            }
            $groups[$key]['platforms'][] = [
                'platform'  => (string)$r['platform'],
                'non_iklan' => (int)$r['non_iklan'],
                'iklan'     => (int)$r['iklan'],
                'total'     => (int)$r['total'],
                'prospek'   => (int)$r['prospek'],
                'datang'    => (int)$r['datang'],
                'rate'      => (int)$r['total'] > 0 ? round((int)$r['datang'] / (int)$r['total'] * 100, 2) : 0.0,
            ];
            if ($this->isWaDmPlatform((string)$r['platform'])) {
                $groups[$key]['wa_dm'] += (int)$r['total'];
            }
            $groups[$key]['non_iklan'] += (int)$r['non_iklan'];
            $groups[$key]['iklan']     += (int)$r['iklan'];
            $groups[$key]['total']     += (int)$r['total'];
            $groups[$key]['prospek']   += (int)$r['prospek'];
            $groups[$key]['datang']    += (int)$r['datang'];
        }
        foreach ($groups as &$g) {
            $g['rate'] = $g['total'] > 0 ? round($g['datang'] / $g['total'] * 100, 2) : 0.0;
        }

        return array_values($groups);
    }

    // ── Helper ────────────────────────────────────────────────────

    private function validTanggal(string $tanggal): string
    {
        $t = date('Y-m-d', strtotime($tanggal));
        if ($t === '1970-01-01' || $t === '') {
            throw new \InvalidArgumentException('Format tanggal tidak valid.');
        }
        return $t;
    }

    /** Normalisasi nama platform untuk pembandingan (WA ADMIN == wa-admin). */
    private function normalizePlatform(string $name): string
    {
        return strtoupper(preg_replace('/[^a-z0-9]+/i', '', $name));
    }

    private function isWaDmPlatform(string $name): bool
    {
        return in_array($this->normalizePlatform($name), $this->normalizedWaDmSet(), true);
    }

    private function normalizedWaDmSet(): array
    {
        return array_map(fn($p) => $this->normalizePlatform($p), self::PLATFORM_WA_DM);
    }
}