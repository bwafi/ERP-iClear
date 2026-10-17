<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Padanan objective + optimization_goal + destination_type → label kolom
 * "Objektif" di laporan Performa Ads.
 *
 * Label diisi manual karena penamaannya bebas. Bila sebuah kombinasi belum
 * dipetakan, labelFor() memakai kode mentahnya sehingga laporan tidak pernah
 * kosong dan tarikan tetap berhasil.
 */
class ModelMetaLabelObjektif extends Model
{
    protected $table         = 'meta_label_objektif';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $allowedFields = [
        'objective',
        'optimization_goal',
        'destination_type',
        'label',
    ];

    /** Label untuk satu kombinasi; jatuh ke kode mentah bila belum dipetakan. */
    public function labelFor(?string $objective, ?string $goal, ?string $dest): string
    {
        $objective = ($objective === null || $objective === '') ? 'UNDEFINED' : $objective;
        $goal      = ($goal === null || $goal === '') ? 'UNDEFINED' : $goal;
        $dest      = ($dest === null || $dest === '') ? 'UNDEFINED' : $dest;

        $row = $this->where('objective', $objective)
            ->where('optimization_goal', $goal)
            ->where('destination_type', $dest)
            ->first();

        $label = $row ? trim((string)$row->label) : '';

        return $label !== '' ? $label : "{$objective} / {$goal} / {$dest}";
    }
}
