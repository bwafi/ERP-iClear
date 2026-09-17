<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model Target Social Media.
 *
 * Target bersifat period-based (period_month YYYY-MM). unit_id default 0 =
 * target global seluruh unit. Dikelola: Kepala Divisi Digital Marketing (43),
 * Admin (0), Root (1), Manager (34).
 */
class ModelSocialMediaTarget extends Model
{
    protected $DBGroup = 'default';

    protected $table         = 'social_media_targets';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'period_month',
        'unit_id',
        'platform',
        'metric',
        'target_value',
    ];

    public function findTarget(string $periodMonth, int $unitId, string $platform, string $metric): ?object
    {
        // Prioritas: target khusus unit, lalu target global (unit_id = 0).
        $builder = $this->where('period_month', $periodMonth)
            ->where('platform', $platform)
            ->where('metric', $metric)
            ->groupStart()
            ->where('unit_id', $unitId)
            ->groupEnd();
        $specific = (clone $builder)->get()->getRow();

        if ($specific) {
            return $specific;
        }

        return (clone $builder)->groupStart()->where('unit_id', 0)->groupEnd()->get()->getRow();
    }

    public function upsertTarget(string $periodMonth, int $unitId, string $platform, string $metric, $targetValue): void
    {
        $existing = $this->where('period_month', $periodMonth)
            ->where('unit_id', $unitId)
            ->where('platform', $platform)
            ->where('metric', $metric)
            ->first();

        $data = [
            'period_month' => $periodMonth,
            'unit_id'      => $unitId,
            'platform'     => $platform,
            'metric'       => $metric,
            'target_value' => (float)$targetValue,
        ];

        if ($existing) {
            $this->update($existing->id, $data);
        } else {
            $this->insert($data);
        }
    }

    public function targetsFor(string $periodMonth, ?int $unitId = null, ?string $platform = null): array
    {
        $builder = $this->where('period_month', $periodMonth);
        if ($unitId !== null && $unitId > 0) {
            $builder->groupStart()->where('unit_id', $unitId)->orWhere('unit_id', 0)->groupEnd();
        }
        if ($platform !== null && $platform !== '') {
            $builder->where('platform', $platform);
        }
        return $builder->orderBy('platform', 'ASC')->orderBy('metric', 'ASC')->findAll();
    }
}