<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Master Campaign Digital Marketing.
 *
 * Status: draft (Draft) / active (Aktif) / done (Selesai) — mengikuti pola
 * content_campaigns. Reporting = campaign selesai yang memiliki report_url
 * (bukan input angka manual score).
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
        'report_url',
        'pic',
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

    /** Campaign selesai (status done) pada periode tertentu. */
    public function doneInPeriod(int $month, int $year): array
    {
        return $this->where('status', self::STATUS_DONE)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->orderBy('nama', 'ASC')
            ->findAll();
    }
}