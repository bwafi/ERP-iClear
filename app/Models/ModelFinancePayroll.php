<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelFinancePayroll extends Model
{
    protected $table = 'finance_payroll';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'unit_id',
        'pegawai_id',
        'due_date',
        'paid_date',
        'status',
        'total',
        'potongan_kasbon',
        'total_bersih',
        'notes',
        'created_by',
        'sumber',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function getByUnitAndRange(int $unitId, string $startDate, string $endDate): array
    {
        return $this->where('unit_id', $unitId)
            ->where('due_date >=', $startDate)
            ->where('due_date <=', $endDate)
            ->orderBy('due_date', 'ASC')
            ->findAll();
    }

    /**
     * Update satu baris register gaji.
     *
     * Kolom yang boleh diubah sengaja dibatasi: `unit_id` dan `pegawai_id`
     * tidak pernah disentuh supaya baris tetap menunjuk orang dan unit yang
     * sama, dan `paid_date` hanya boleh diisi lewat penandaan lunas
     * (Payroll::bayar()) yang sekaligus menghitung potongan kasbonnya.
     */
    public function updateRegister(int $id, array $data): bool
    {
        $ubah = [];

        foreach (['total', 'due_date', 'notes'] as $kolom) {
            if (array_key_exists($kolom, $data)) {
                $ubah[$kolom] = $data[$kolom];
            }
        }

        if ($ubah === []) {
            return false;
        }

        $ubah['sumber'] = 'manual';

        return (bool) $this->update($id, $ubah);
    }
}