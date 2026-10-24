<?php

namespace App\Models;

use CodeIgniter\Model;

/** Satu baris = satu minggu yang sudah dilaporkan. Sekali kirim, tidak diubah. */
class ModelMarketingLaporanMingguan extends Model
{
    protected $table = 'marketing_laporan_mingguan';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'period_year',
        'period_month',
        'minggu',
        'submitted_by',
        'submitted_at',
    ];
}
