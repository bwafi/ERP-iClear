<?php
namespace App\Models;

use CodeIgniter\Model;

class ModelIncentiveRule extends Model
{
    protected $table = 'incentive_rules';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'incentive_group_id',
        'kpi_component_id',
        'incentive_name',
        'calculation_type',
        'base_value',
        'minimum_achievement',
        'division_method',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected $useTimestamps = true;

    /**
     * Cari rule aktif untuk sebuah incentive group.
     */
    public function getRuleByGroup(int $groupId, ?string $date = null): ?object
    {
        $date = $date ?? date('Y-m-d');

        return $this->where('incentive_group_id', $groupId)
            ->where('effective_from <=', $date)
            ->groupStart()
                ->where('effective_to >=', $date)
                ->orWhere('effective_to IS NULL')
            ->groupEnd()
            ->first();
    }
}