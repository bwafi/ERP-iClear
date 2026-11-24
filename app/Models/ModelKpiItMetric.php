<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Snapshot bulanan data mentah KPI Team IT (jabatan 45).
 *
 * Satu baris per periode (YYYY-MM). Ditulis oleh `spark it:kpi-sync` dan
 * dibaca oleh ItKpiService saat menghitung skor.
 */
class ModelKpiItMetric extends Model
{
    protected $table = 'kpi_it_metrics';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'period',
        'feat_commits',
        'fix_commits',
        'docs_commits',
        'backup_ok_days',
        'backup_checked_days',
        'uptime_ok_days',
        'uptime_checked_days',
        'restore_ok',
        'detail',
        'synced_at',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}
