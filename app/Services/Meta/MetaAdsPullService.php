<?php

namespace App\Services\Meta;

use App\Models\ModelMarketingAdsPerf;
use App\Models\ModelMarketingCampaign;
use App\Models\ModelMetaLabelObjektif;
use App\Models\ModelMetaPullLog;

/**
 * Tarik insight Meta → tabel Performa Ads (marketing_ads_performance).
 *
 * Aturan yang berlaku (keputusan pemilik produk):
 *  - satu campaign ERP = satu unit; unit diambil dari marketing_campaigns.unit_id;
 *  - angka Meta MENIMPA baris manual yang sudah ada ("Meta menang");
 *  - kampanye Meta yang belum dipetakan DILEWATI dan dilaporkan, tidak membuat
 *    baris setengah jadi;
 *  - baris yang tidak muncul di insight (kampanye pause) tidak pernah dihapus
 *    dan tidak pernah dibuat baris nol.
 */
class MetaAdsPullService
{
    private MetaAdsClient $client;

    public function __construct(?MetaAdsClient $client = null)
    {
        $this->client = $client ?? new MetaAdsClient();
    }

    /**
     * @return array{baru:int, diperbarui:int, dilewati:int, tanpa_peta:array<int,string>, log_id:int|null}
     */
    public function pull(string $since, string $until, bool $dryRun = false): array
    {
        $map    = (new ModelMarketingCampaign())->metaMap();
        $data   = $this->client->fetchCampaignInsights($since, $until);
        $camps  = $this->client->fetchCampaigns();
        $labels = new ModelMetaLabelObjektif();
        $perf   = new ModelMarketingAdsPerf();

        $baru = 0;
        $diperbarui = 0;
        $dilewati = 0;
        $tanpaPeta = [];

        foreach (($data['rows'] ?? []) as $row) {
            $metaId = (string)($row['campaign_id'] ?? '');

            if ($metaId === '' || !isset($map[$metaId])) {
                $tanpaPeta[$metaId] = true;
                $dilewati++;
                continue;
            }

            $campaignId = (int)$map[$metaId]['campaign_id'];
            $unitId     = $map[$metaId]['unit_id'];
            $tanggal    = (string)$row['date'];
            $bulan      = (int)date('n', strtotime($tanggal));
            $tahun      = (int)date('Y', strtotime($tanggal));
            $c          = $camps[$metaId] ?? [];

            $payload = [
                'period_month' => $bulan,
                'period_year'  => $tahun,
                'tanggal'      => $tanggal,
                'campaign_id'  => $campaignId,
                'unit_id'      => $unitId,
                'amount'       => $row['spend'],
                'daily_budget' => $c['daily_budget'] ?? null,
                'ppn'          => $this->ppnPersen(),
                'objective'    => $labels->labelFor(
                    $c['objective'] ?? null,
                    $c['optimization_goal'] ?? null,
                    $c['destination_type'] ?? null
                ),
                'reach'        => $row['reach'],
                'impression'   => $row['impressions'],
                'klik'         => $row['link_clicks'],
                'hasil'        => $row['conversations'],
                'sumber'       => 'meta',
                'ditarik_pada' => date('Y-m-d H:i:s'),
            ];

            // Kunci pencarian: (tanggal, campaign). channel_id tidak dipakai
            // karena tarikan Meta tidak menetapkan saluran.
            $existing = $perf->getByUnique($bulan, $tahun, $campaignId, $tanggal, 0, $unitId ?? 0);
            if (!$existing) {
                // Baris manual lama sering tidak punya unit_id.
                $existing = $perf->getByUnique($bulan, $tahun, $campaignId, $tanggal, 0, 0);
            }

            if ($dryRun) {
                $existing ? $diperbarui++ : $baru++;
                continue;
            }

            if ($existing) {
                $perf->update($existing->id, $payload);
                $diperbarui++;
            } else {
                $perf->insert($payload);
                $baru++;
            }
        }

        $hasil = [
            'baru'       => $baru,
            'diperbarui' => $diperbarui,
            'dilewati'   => $dilewati,
            'tanpa_peta' => array_values(array_filter(array_keys($tanpaPeta), fn($k) => $k !== '')),
            'log_id'     => null,
        ];

        if (!$dryRun) {
            $rate   = (array)($data['rate'] ?? []);
            $logId  = (new ModelMetaPullLog())->insert([
                'tanggal_mulai'  => $since,
                'tanggal_sampai' => $until,
                'jumlah_kampanye' => count($camps),
                'jumlah_baris'   => $baru + $diperbarui,
                'baru'           => $baru,
                'diperbarui'     => $diperbarui,
                'dilewati'       => $dilewati,
                'call_count'     => $rate['call_count'] ?? null,
                'total_time'     => $rate['total_time'] ?? null,
                'total_cputime'  => $rate['total_cputime'] ?? null,
                'app_id_util_pct' => $rate['app_id_util_pct'] ?? null,
                'acc_id_util_pct' => $rate['acc_id_util_pct'] ?? null,
                'estimated_time_to_regain_access' => $rate['estimated_time_to_regain_access'] ?? null,
                'tier'           => $rate['tier'] ?? null,
                'status'         => 'ok',
                'pesan'          => $tanpaPeta === [] ? null : 'Tanpa peta: ' . implode(', ', $hasil['tanpa_peta']),
            ]);
            $hasil['log_id'] = is_numeric($logId) ? (int)$logId : null;
        }

        return $hasil;
    }

    /** Tarif pajak per akun iklan, diatur di .env (default 5%). */
    private function ppnPersen(): float
    {
        $v = env('META_PPN_PERSEN');

        return ($v === null || $v === '') ? 5.0 : (float)$v;
    }
}
