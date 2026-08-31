<?php

namespace App\Services\Incentive;

use App\Models\ModelIncentiveGroup;
use App\Models\ModelIncentiveMember;
use App\Models\ModelIncentiveRule;

/**
 * IncentiveCalculationService
 *
 * Menghitung insentif berbasis GROUP (bukan jabatan hardcoded).
 * Business rule (CONFIRMED):
 *   pool = omset_toko × rate%
 *   individual = pool / countActiveMembers(group, unit, date)
 *
 * Angka pembagi (divisor) TIDAK di-hardcode di service.
 * Jumlah anggota dihitung dari tabel incentive_members (per unit & periode).
 */
class IncentiveCalculationService
{
    protected $groupModel;
    protected $memberModel;
    protected $ruleModel;

    public function __construct()
    {
        $this->groupModel = new ModelIncentiveGroup();
        $this->memberModel = new ModelIncentiveMember();
        $this->ruleModel = new ModelIncentiveRule();
    }

    /**
     * Hitung insentif group pada suatu unit & periode.
     *
     * @param string $groupCode   kode group, mis. 'KEPALA_TOKO' / 'DIGITAL_DIVISION'
     * @param int    $unitId      cabang tempat omset dihitung
     * @param float  $omsetToko   omset basis (gross profit unit tsb)
     * @param float  $achievement persentase pencapaian (default 100)
     * @param string|null $date   periode yang dihitung (default: sekarang)
     */
    public function calculateGroupIncentive(
        string $groupCode,
        int $unitId,
        float $omsetToko,
        float $achievement = 100.0,
        ?string $date = null
    ): array {
        $date = $date ?? date('Y-m-d');

        $group = $this->groupModel->getByCode($groupCode);
        if (!$group || !(int) $group->is_active) {
            return $this->fail('Incentive group not found or inactive: ' . $groupCode);
        }

        $rule = $this->ruleModel->getRuleByGroup((int) $group->id, $date);
        if (!$rule) {
            return $this->fail('No active incentive rule for group ' . $groupCode);
        }

        if ($achievement < (float) $rule->minimum_achievement) {
            return $this->fail(sprintf(
                'Achievement %.2f%% below minimum %.2f%%',
                $achievement,
                (float) $rule->minimum_achievement
            ));
        }

        // pool = omset × rate%
        $poolAmount = ((float) $rule->base_value / 100) * $omsetToko;

        // divisor = JML ANGGOTA AKTIF group pada unit & periode (dinamis)
        $memberCount = $this->memberModel->countActiveMembers((int) $group->id, $unitId, $date);

        if ($memberCount <= 0) {
            return $this->fail('No active members for group ' . $groupCode . ' on unit ' . $unitId, [
                'group_code'   => $groupCode,
                'unit_id'      => $unitId,
                'pool_amount'  => round($poolAmount, 2),
                'member_count' => 0,
            ]);
        }

        $individual = $poolAmount / $memberCount;

        return [
            'success'          => true,
            'group_code'       => $groupCode,
            'group_name'       => $group->name,
            'unit_id'          => $unitId,
            'omset_toko'       => $omsetToko,
            'rate_percent'     => (float) $rule->base_value,
            'achievement'      => $achievement,
            'pool_amount'      => round($poolAmount, 2),
            'member_count'     => $memberCount,
            'incentive_amount' => round($individual, 2),
            'date'             => $date,
        ];
    }

    protected function fail(string $reason, array $extra = []): array
    {
        return array_merge([
            'success'          => false,
            'incentive_amount' => 0,
            'reason'           => $reason,
        ], $extra);
    }
}

