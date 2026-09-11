<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelMarketingLead extends Model
{
    protected $table = 'marketing_lead';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = [
        'tanggal', 'nama', 'no_hp', 'source_id', 'ads_organic', 'price', 'cs', 'cabang', 'status',
        'customer_id', 'tanggal_won', 'created_by',
        'kommo_lead_id', 'kommo_account_id', 'kommo_pipeline_id',
        'kommo_status_id', 'kommo_updated_at', 'kommo_deleted_at',
        // Detail Prospek (baris manual, kommo_lead_id IS NULL).
        'platform', 'unit_id', 'no_telp_wa', 'keterangan', 'tanggal_booking', 'omset', 'service_id', 'catatan', 'nomor',
    ];

    public const STATUS_PROSPEK = 'PROSPEK';
    public const STATUS_BOOKING = 'BOOKING';
    public const STATUS_DATANG  = 'DATANG';
    public const STATUS_CLOSED  = 'CLOSED';
    public const STATUS_BATAL   = 'BATAL';

    /** Status Detail Prospek operasional (CS / marketing harian). */
    public const PROSPEK_STATUSES = [
        self::STATUS_PROSPEK,
        self::STATUS_BOOKING,
        self::STATUS_DATANG,
        self::STATUS_CLOSED,
        self::STATUS_BATAL,
    ];

    // ── Detail Prospek (baris manual, tanpa data sinkronisasi Kommo) ──

    /** Daftar detail prospek manual per bulan, urut tanggal terbaru. */
    public function findDetailProspek(int $month, int $year, ?string $status = null, ?string $platform = null, ?int $unitId = null, ?int $limit = null, ?int $offset = 0): array
    {
        $builder = $this->detailProspekWhere($month, $year, $status, $platform, $unitId)
            ->orderBy('tanggal', 'DESC')
            ->orderBy('id', 'DESC');

        if ($limit !== null && $limit > 0) {
            return $builder->findAll($limit, max(0, $offset));
        }
        return $builder->findAll();
    }

    public function countDetailProspek(int $month, int $year, ?string $status = null, ?string $platform = null, ?int $unitId = null): int
    {
        return $this->detailProspekWhere($month, $year, $status, $platform, $unitId)->countAllResults();
    }

    /** Nomor urut berikutnya untuk tanggal tertentu (kolom "No" pada sheet). */
    public function nextNomorForDate(string $tanggal): int
    {
        $row = $this->select('MAX(nomor) as last')
            ->where('tanggal', $tanggal)
            ->where('kommo_lead_id', null)
            ->first();
        return (int)($row->last ?? 0) + 1;
    }

    /** Gabungan kriteria baris manual detail prospek (bukan lead Kommo). */
    private function detailProspekWhere(int $month, int $year, ?string $status = null, ?string $platform = null, ?int $unitId = null)
    {
        $where = "DATE_FORMAT(tanggal, '%Y-%m') = '" . sprintf('%04d-%02d', $year, $month) . "'";
        $where .= ' AND kommo_lead_id IS NULL';
        if ($status !== null && in_array($status, self::PROSPEK_STATUSES, true)) {
            $where .= " AND status = '" . $status . "'";
        }
        if ($platform !== null && $platform !== '') {
            $platforms = array_map(
                fn($p) => (string)$p,
                explode(',', $platform)
            );
            $quoted = implode(',', array_map(fn($p) => "'" . $this->db->escapeString($p) . "'", $platforms));
            $where .= " AND platform IN ({$quoted})";
        }
        if ($unitId !== null && $unitId > 0) {
            $where .= ' AND unit_id = ' . (int)$unitId;
        }
        return $this->where($where, null, false);
    }

    /** Find baris manual berdasarkan id (untuk edit/hapus/status). */
    public function findManualById(int $id): ?object
    {
        return $this->where('id', $id)->where('kommo_lead_id', null)->first();
    }

    /** Cari berdasarkan kommo_lead_id (primary external identifier). */
    public function findByKommoId(int $kommoLeadId): ?object
    {
        return $this->where('kommo_lead_id', $kommoLeadId)->first();
    }
}