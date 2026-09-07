<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelAuditAsetPeriode extends Model
{
    protected $table = 'audit_aset_periode';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'unit',
        'bulan',
        'tahun',
        'status',
        'tanggal_audit',
        'auditor_id',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_FINAL = 'FINAL';

    /**
     * Ambil periode audit satu unit-untuk satu bulan (ada/membuat draft baru).
     */
    public function getOrCreatePeriode(int $unit, int $bulan, int $tahun, int $auditorId, ?string $tanggalAudit): object
    {
        $periode = $this->where('unit', $unit)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->first();

        if ($periode) {
            return $periode;
        }

        $this->insert([
            'unit'          => $unit,
            'bulan'         => $bulan,
            'tahun'         => $tahun,
            'status'        => self::STATUS_DRAFT,
            'tanggal_audit' => $tanggalAudit,
            'auditor_id'    => $auditorId,
        ]);

        return $this->find($this->getInsertID());
    }

    /**
     * Periode final utk unit-bulan tsb, atau null.
     */
    public function findFinalPeriode(int $unit, int $bulan, int $tahun): ?object
    {
        return $this->where('unit', $unit)
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->where('status', self::STATUS_FINAL)
            ->first() ?: null;
    }
}