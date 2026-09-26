<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelFinanceRekonDaily extends Model
{
    /** Status proses approval (terpisah dari status hasil rekonsiliasi). */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_NEED_REVISION = 'need_revision';

    protected $table = 'finance_rekon_daily';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'unit_id',
        'tanggal',
        'erp_cash_masuk',
        'actual_cash_masuk',
        'selisih_cash_masuk',
        'erp_transfer_masuk',
        'actual_transfer_masuk',
        'selisih_transfer_masuk',
        'erp_kas_keluar',
        'actual_kas_keluar',
        'selisih_kas_keluar',
        'catatan',
        'catatan_revisi',
        'status_proses',
        'submitted_by',
        'submitted_at',
        'verified_by',
        'verified_at',
        'input_by',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Kolom yang SELALU dibaca sebagai int.
     *
     * Driver MySQL PDO mengembalikan BIGINT sebagai string, sehingga
     * number_format() / perbandingan === akan salah tanpa cast eksplisit.
     * (CI4 tidak punya $casts bawaan, jadi cast dilakukan di sini.)
     */
    protected $intFields = [
        'unit_id',
        'erp_cash_masuk',
        'actual_cash_masuk',
        'selisih_cash_masuk',
        'erp_transfer_masuk',
        'actual_transfer_masuk',
        'selisih_transfer_masuk',
        'erp_kas_keluar',
        'actual_kas_keluar',
        'selisih_kas_keluar',
        'input_by',
        'submitted_by',
        'verified_by',
    ];

    /**
     * Ubah kolom integer sebuah row menjadi int. Aman untuk row null.
     */
    public function castRow(?object $row): ?object
    {
        if (! $row) {
            return null;
        }

        foreach ($this->intFields as $field) {
            if (isset($row->{$field})) {
                $row->{$field} = $row->{$field} === null ? null : (int) $row->{$field};
            }
        }

        return $row;
    }

    public function getByUnitAndDate(int $unitId, string $tanggal)
    {
        return $this->castRow($this->where('unit_id', $unitId)
            ->where('tanggal', $tanggal)
            ->first());
    }

    public function getByUnitAndRange(int $unitId, string $startDate, string $endDate): array
    {
        $rows = $this->where('unit_id', $unitId)
            ->where('tanggal >=', $startDate)
            ->where('tanggal <=', $endDate)
            ->orderBy('tanggal', 'ASC')
            ->findAll();

        foreach ($rows as $i => $row) {
            $rows[$i] = $this->castRow($row);
        }

        return $rows;
    }

    /**
     * Update satu baris by primary key (dipakai transisi status approval).
     *
     * Nama 'updateRow' sengaja dipakai, bukan 'update', karena method
     * CodeIgniter\Model::update() sudah ada dengan signature berbeda.
     */
    public function updateRow(int $id, array $data): bool
    {
        if ($id <= 0) {
            return false;
        }

        return (bool) $this->update($id, $data);
    }

    public function upsert(array $data): bool
    {
        $existing = $this->where('unit_id', $data['unit_id'])
            ->where('tanggal', $data['tanggal'])
            ->first();

        if ($existing) {
            $data['id'] = $existing->id;
            return $this->save($data);
        }

        return (bool) $this->insert($data);
    }

    /**
     * Syarat "lengkap": ketiga actual_* sudah diisi (IS NOT NULL).
     *
     * Tidak ada lagi kolom checked_*: kelengkapan murni dari keberadaan nilai
     * actual, sehingga angka 0 tetap dianggap sah (bukan "belum diisi").
     */
    public function countLengkapInRange(int $unitId, string $startDate, string $endDate): int
    {
        return $this->applyLengkap($this->where('unit_id', $unitId)
            ->where('tanggal >=', $startDate)
            ->where('tanggal <=', $endDate))
            ->countAllResults();
    }

    /**
     * Numerator KPI: ketiga actual_* terisi DAN approval = 'verified'.
     *
     * 'submitted' / 'need_revision' / 'draft' belum dihitung. Selisih TIDAK
     * berpengaruh: LENGKAP_COCOK maupun LENGKAP_SELISIH sama-sama dihitung
     * sebagai hari selesai selama sudah diverifikasi.
     */
    public function countLengkapVerifiedInRange(int $unitId, string $startDate, string $endDate): int
    {
        return $this->applyLengkap($this->where('unit_id', $unitId)
            ->where('tanggal >=', $startDate)
            ->where('tanggal <=', $endDate))
            ->where('status_proses', self::STATUS_VERIFIED)
            ->countAllResults();
    }

    /**
     * Terapkan syarat "ketiga actual_* terisi" ke sebuah query builder.
     */
    private function applyLengkap($builder)
    {
        return $builder
            ->where('actual_cash_masuk IS NOT NULL', null, false)
            ->where('actual_transfer_masuk IS NOT NULL', null, false)
            ->where('actual_kas_keluar IS NOT NULL', null, false);
    }
}
