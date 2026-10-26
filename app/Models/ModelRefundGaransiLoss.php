<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Sumber pengurang omset dari refund pembayaran klaim garansi.
 *
 * Periode selalu `refund_garansi_tanggal`, bukan tanggal klaim/service.
 */
class ModelRefundGaransiLoss extends Model
{
    protected $table = 'service';
    protected $primaryKey = 'idservice';
    protected $returnType = 'object';

    public function getTotal(string $start, string $end, ?int $unitId = null, ?int $teknisiId = null): int
    {
        if (! $this->kolomAda()) {
            return 0;
        }

        $row = $this->baseQuery($unitId, $teknisiId)
            ->selectSum('refund_garansi_jumlah', 'total')
            ->where('refund_garansi_tanggal >=', $start)
            ->where('refund_garansi_tanggal <=', $end)
            ->get()
            ->getRow();

        return (int) ($row->total ?? 0);
    }

    /**
     * @return array<string,int>
     */
    public function getByDateRange(string $start, string $end, ?int $unitId = null): array
    {
        if (! $this->kolomAda()) {
            return [];
        }

        $rows = $this->baseQuery($unitId)
            ->select('refund_garansi_tanggal AS tanggal, SUM(refund_garansi_jumlah) AS total')
            ->where('refund_garansi_tanggal >=', $start)
            ->where('refund_garansi_tanggal <=', $end)
            ->groupBy('refund_garansi_tanggal')
            ->orderBy('refund_garansi_tanggal', 'ASC')
            ->get()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->tanggal] = (int) $row->total;
        }

        return $map;
    }

    private function kolomAda(): bool
    {
        // fieldExists() memakai cache metadata koneksi, jadi tidak akurat saat
        // skema berubah di proses yang sama (mis. test).
        $row = $this->db->query(
            'SELECT COUNT(*) AS jumlah FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->db->prefixTable('service'), 'refund_garansi_tanggal']
        )->getRow();

        return (int) ($row->jumlah ?? 0) > 0;
    }

    private function baseQuery(?int $unitId = null, ?int $teknisiId = null)
    {
        $builder = $this->db->table('service')
            ->where('refund_garansi_jumlah >', 0);

        if ($unitId !== null && (int) $unitId !== 0) {
            $builder->where('unit_idunit', (int) $unitId);
        }
        if ($teknisiId !== null && (int) $teknisiId !== 0) {
            $builder->where('service_by', (int) $teknisiId);
        }

        return $builder;
    }
}
