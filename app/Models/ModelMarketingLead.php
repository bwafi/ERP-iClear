<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelMarketingLead extends Model
{
    protected $table = 'marketing_lead';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = ['tanggal', 'nama', 'no_hp', 'source_id', 'ads_organic', 'cs', 'status', 'customer_id', 'tanggal_won', 'created_by'];

    public function findByPeriod(int $month, int $year, ?string $status = null): array
    {
        $where = "DATE_FORMAT(tanggal, '%Y-%m') = '" . sprintf('%04d-%02d', $year, $month) . "'";
        if ($status !== null) {
            $where .= " AND status = '" . $status . "'";
        }
        return $this->where($where, null, false)->orderBy('tanggal', 'DESC')->orderBy('id', 'DESC')->findAll();
    }
}