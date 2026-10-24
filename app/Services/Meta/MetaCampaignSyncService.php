<?php

namespace App\Services\Meta;

use App\Models\ModelMarketingCampaign;
use App\Services\Marketing\UnitDetector;

/**
 * Tarik daftar campaign Meta ke master `marketing_campaigns`.
 *
 * Aturan:
 *  - kunci baris = `meta_campaign_id` (UNIQUE) → sync idempoten;
 *  - unit ditebak dari nama, kecuali sudah dikoreksi manual (`unit_sumber`);
 *  - `status` mengikuti status Meta (ACTIVE → active, selain itu draft);
 *  - campaign yang tidak lagi dilaporkan Meta diturunkan ke draft;
 *  - periode sebelum 2026-10 ditolak (data September sudah final/manual).
 */
class MetaCampaignSyncService
{
    private MetaAdsClient $client;

    public function __construct(?MetaAdsClient $client = null)
    {
        $this->client = $client ?? new MetaAdsClient();
    }

    /**
     * Bagian murni (tanpa DB/jaringan): tentukan baris yang ditulis.
     *
     * @param array<int,array{id:string,nama:string,spend:float,aktif:bool}> $opsi
     * @param array<string,array{id:int,unit_id:?int,unit_sumber:string,status:string}> $existing
     * @return array{upsert: array<int,array{meta_id:string,id:?int,nama:string,unit_id:int,status:string}>, demote: array<int,int>}
     */
    public function plan(array $opsi, array $existing): array
    {
        $upsert = [];
        $dilaporkan = [];

        foreach ($opsi as $o) {
            $metaId = (string)$o['id'];
            $dilaporkan[$metaId] = true;
            $lama = $existing[$metaId] ?? null;

            $manual = $lama !== null
                && ($lama['unit_sumber'] ?? 'auto') === 'manual'
                && !empty($lama['unit_id']);

            $upsert[] = [
                'meta_id' => $metaId,
                'id'      => $lama['id'] ?? null,
                'nama'    => (string)$o['nama'],
                'unit_id' => $manual ? (int)$lama['unit_id'] : UnitDetector::detect((string)$o['nama']),
                'status'  => !empty($o['aktif']) ? 'active' : 'draft',
            ];
        }

        $demote = [];
        foreach ($existing as $metaId => $row) {
            if (!isset($dilaporkan[(string)$metaId]) && ($row['status'] ?? '') === 'active') {
                $demote[] = (int)$row['id'];
            }
        }

        return ['upsert' => $upsert, 'demote' => $demote];
    }

    /**
     * @return array{dibuat:int, diperbarui:int, diturunkan:int}
     */
    public function sync(int $month, int $year): array
    {
        if ($year * 100 + $month < 202610) {
            throw new \RuntimeException('Sync campaign hanya untuk periode 2026-10 ke atas.');
        }

        $dari   = sprintf('%04d-%02d-01', $year, $month);
        $sampai = date('Y-m-t', strtotime($dari));

        $opsi = $this->client->fetchCampaignOptions($dari, $sampai);

        // fetchCampaignOptions ikut mengembalikan campaign yang ACTIVE hari ini.
        // Untuk periode lama, hanya yang benar-benar belanja di periode itu yang
        // boleh dibuatkan baris.
        if (!((int)date('n') === $month && (int)date('Y') === $year)) {
            $opsi = array_values(array_filter($opsi, fn($o) => (float)$o['spend'] > 0));
        }

        $model = new ModelMarketingCampaign();
        $existing = [];
        foreach ($model->where('meta_campaign_id IS NOT NULL', null, false)->findAll() as $row) {
            $metaId = trim((string)$row->meta_campaign_id);
            if ($metaId === '') {
                continue;
            }
            $existing[$metaId] = [
                'id'           => (int)$row->id,
                'unit_id'      => $row->unit_id !== null ? (int)$row->unit_id : null,
                'unit_sumber'  => (string)($row->unit_sumber ?? 'auto'),
                'status'       => (string)$row->status,
                'period_month' => (int)$row->period_month,
                'period_year'  => (int)$row->period_year,
            ];
        }

        $p = $this->plan($opsi, $existing);

        // Sync bulan lama tidak boleh mengubah status baris bulan lain.
        $bolehTurun = [];
        foreach ($existing as $e) {
            if ($e['period_month'] === $month && $e['period_year'] === $year) {
                $bolehTurun[$e['id']] = true;
            }
        }
        $p['demote'] = array_values(array_filter($p['demote'], fn($id) => isset($bolehTurun[$id])));

        $dibuat = 0;
        $diperbarui = 0;
        foreach ($p['upsert'] as $u) {
            if ($u['id'] !== null) {
                // unit_sumber sengaja tidak ditulis: koreksi manual dipertahankan.
                $model->update($u['id'], [
                    'nama'    => $u['nama'],
                    'status'  => $u['status'],
                    'unit_id' => $u['unit_id'],
                ]);
                $diperbarui++;
            } else {
                $model->insert([
                    'nama'             => $u['nama'],
                    'meta_campaign_id' => $u['meta_id'],
                    'period_month'     => $month,
                    'period_year'      => $year,
                    'status'           => $u['status'],
                    'unit_id'          => $u['unit_id'],
                    'unit_sumber'      => 'auto',
                ]);
                $dibuat++;
            }
        }

        $diturunkan = 0;
        foreach ($p['demote'] as $id) {
            $model->update($id, ['status' => 'draft']);
            $diturunkan++;
        }

        return ['dibuat' => $dibuat, 'diperbarui' => $diperbarui, 'diturunkan' => $diturunkan];
    }
}
