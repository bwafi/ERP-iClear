<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Master Campaign Digital Marketing.
 *
 * Baris dibuat otomatis dari Meta (`meta:sync-campaigns`). Satu campaign Meta
 * = satu baris (`meta_campaign_id` UNIQUE). Status mengikuti Meta: ACTIVE →
 * active, selain itu draft. Unit ditebak dari nama (`unit_sumber='auto'`) dan
 * boleh dikoreksi (`unit_sumber='manual'`).
 *
 * KPI Reporting sejak 2026-11 = laporan mingguan tepat waktu (bukan status
 * campaign lagi).
 */
class ModelMarketingCampaign extends Model
{
    protected $table = 'marketing_campaigns';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = [
        'nama',
        'deskripsi',
        'tanggal_mulai',
        'tanggal_selesai',
        'period_month',
        'period_year',
        'status',
        'pic',
        'meta_campaign_id',
        'unit_id',
        'unit_sumber',
        'created_by',
    ];

    public const STATUS_DRAFT  = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DONE   = 'done';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT  => 'Draft',
        self::STATUS_ACTIVE => 'Aktif',
        self::STATUS_DONE   => 'Selesai',
    ];

    /**
     * Daftar campaign untuk pemilih di Performa Ads & Laporan.
     *
     * Satu campaign Meta hanya punya satu baris (`meta_campaign_id` UNIQUE),
     * jadi saringan periode saja akan menyembunyikannya di bulan lain.
     * Sertakan: periode itu, yang sedang aktif, dan yang punya baris iklan
     * di periode itu.
     */
    public function options(int $month, int $year): array
    {
        return $this->db->query(
            "SELECT * FROM marketing_campaigns
             WHERE (period_month = ? AND period_year = ?)
                OR status = ?
                OR id IN (SELECT DISTINCT campaign_id FROM marketing_ads_performance
                          WHERE period_month = ? AND period_year = ? AND campaign_id IS NOT NULL)
             ORDER BY nama ASC",
            [$month, $year, self::STATUS_ACTIVE, $month, $year]
        )->getResult();
    }

    /**
     * Peta campaign Meta → campaign ERP, untuk tarikan otomatis.
     *
     * @return array<string, array{campaign_id: int, unit_id: int|null}>
     */
    public function metaMap(): array
    {
        $map = [];
        foreach ($this->where('meta_campaign_id IS NOT NULL', null, false)->findAll() as $row) {
            $metaId = trim((string)$row->meta_campaign_id);
            if ($metaId === '') {
                continue;
            }
            $map[$metaId] = [
                'campaign_id' => (int)$row->id,
                'unit_id'     => $row->unit_id !== null && (int)$row->unit_id > 0 ? (int)$row->unit_id : null,
            ];
        }

        return $map;
    }
}