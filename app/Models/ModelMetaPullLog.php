<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Catatan tiap tarikan Meta Ads, termasuk nilai rate limit saat itu.
 *
 * Dipakai halaman Performa Ads untuk menampilkan sisa kuota tanpa perlu
 * membuka App Dashboard.
 */
class ModelMetaPullLog extends Model
{
    protected $table         = 'meta_pull_log';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $allowedFields = [
        'tanggal_mulai',
        'tanggal_sampai',
        'jumlah_kampanye',
        'jumlah_baris',
        'baru',
        'diperbarui',
        'dilewati',
        'call_count',
        'total_time',
        'total_cputime',
        'app_id_util_pct',
        'acc_id_util_pct',
        'estimated_time_to_regain_access',
        'tier',
        'status',
        'pesan',
    ];

    /** Tarikan terakhir, untuk panel info limit. */
    public function terakhir()
    {
        return $this->orderBy('id', 'DESC')->first();
    }
}
