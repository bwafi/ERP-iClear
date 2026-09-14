<?php
namespace App\Models;

use CodeIgniter\Model;

class ModelContentPerformanceTarget extends Model
{
    protected $table = 'content_performance_targets';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = ['content_id', 'metric_id', 'target'];

    /**
     * Ganti seluruh target metric sebuah konten secara atomik.
     * @param int   $contentId
     * @param array $rows  list [metric_id => target, ...] atau [['metric_id'=>..,'target'=>..], ...]
     */
    public function replaceForContent(int $contentId, array $rows)
    {
        $normalized = [];
        foreach ($rows as $key => $val) {
            if (is_array($val)) {
                $metricId = (int)($val['metric_id'] ?? 0);
                $target   = $val['target'];
            } else {
                $metricId = (int)$key;
                $target   = $val;
            }
            if ($metricId <= 0) {
                continue;
            }
            $normalized[$metricId] = $target;
        }

        $this->where('content_id', $contentId)->delete();

        foreach ($normalized as $metricId => $target) {
            $t = ($target !== null && $target !== '') ? (float)$target : null;
            $this->insert([
                'content_id' => $contentId,
                'metric_id'  => $metricId,
                'target'     => $t,
            ]);
        }
        return true;
    }

    /**
     * Ambil target per metric untuk sebuah konten, sudah join nama metric.
     * @return object[] [{id, metric_id, name, code, target}]
     */
    public function forContent(int $contentId): array
    {
        return $this->select('t.id, t.metric_id, t.target, m.name AS metric_name, m.code AS metric_code')
            ->from($this->table . ' t', true)
            ->join('performance_metrics m', 'm.id = t.metric_id', 'left')
            ->where('t.content_id', $contentId)
            ->orderBy('m.name', 'ASC')
            ->get()
            ->getResult();
    }
}