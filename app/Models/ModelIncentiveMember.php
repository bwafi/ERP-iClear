<?php
namespace App\Models;

use CodeIgniter\Model;

class ModelIncentiveMember extends Model
{
    protected $table = 'incentive_members';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'incentive_group_id', 'employee_id', 'unit_id',
        'effective_from', 'effective_to', 'is_active',
    ];
    protected $useTimestamps = true;

    /**
     * Hitung jumlah anggota aktif dalam suatu group, unit, dan periode tertentu.
     */
    public function countActiveMembers(int $groupId, int $unitId, ?string $date = null): int
    {
        $date = $date ?? date('Y-m-d');

        return $this->where('incentive_group_id', $groupId)
            ->where('unit_id', $unitId)
            ->where('is_active', 1)
            ->where('effective_from <=', $date)
            ->groupStart()
                ->where('effective_to >=', $date)
                ->orWhere('effective_to IS NULL')
            ->groupEnd()
            ->countAllResults();
    }
}