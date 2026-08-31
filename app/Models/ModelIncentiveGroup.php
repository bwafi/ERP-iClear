<?php
namespace App\Models;

use CodeIgniter\Model;

class ModelIncentiveGroup extends Model
{
    protected $table = 'incentive_groups';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = ['code', 'name', 'description', 'is_active', 'effective_from', 'effective_to'];
    protected $useTimestamps = true;

    public function getByCode($code): ?object
    {
        return $this->where('code', $code)->first();
    }
}