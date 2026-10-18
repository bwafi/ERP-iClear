<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Master Campaign Digital Marketing.
 *
 * Status: draft (Draft) / active (Aktif) / done (Selesai) — mengikuti pola
 * content_campaigns. KPI Reporting = % campaign selesai (done) dari total
 * campaign pada periode (tanpa tautan laporan).
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

    /** Daftar campaign utk pilihan di form Performa Ads. */
    public function options(int $month, int $year): array
    {
        return $this->where('period_month', $month)
            ->where('period_year', $year)
            ->orderBy('nama', 'ASC')
            ->findAll();
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