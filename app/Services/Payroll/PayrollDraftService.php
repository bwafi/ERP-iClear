<?php

namespace App\Services\Payroll;

use CodeIgniter\Database\BaseConnection;
use Config\Database as DatabaseConfig;

/**
 * Pencarian dan pembatalan draft register gaji (`finance_payroll`).
 *
 * Hanya baris berstatus `rencana` yang boleh dibatalkan. Baris `dibayar`
 * adalah riwayat dan tidak pernah disentuh.
 */
class PayrollDraftService
{
    /** @var BaseConnection */
    protected $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? DatabaseConfig::connect();
    }

    /**
     * Cari draft payroll berstatus `rencana`.
     *
     * @param array $filter pegawai_id, bulan (YYYY-MM), unit_ids, hanya_nonaktif
     *
     * @return array<int, array<string, mixed>>
     */
    public function cari(array $filter = []): array
    {
        $builder = $this->db->table('finance_payroll fp')
            ->select('fp.id, fp.pegawai_id, fp.unit_id, fp.due_date, fp.total, fp.sumber, a.NAMA_AKUN, a.STATUS_PEGAWAI, a.JENIS_PEGAWAI, u.NAMA_UNIT')
            ->join('akun a', 'a.ID_AKUN = fp.pegawai_id', 'inner')
            ->join('unit u', 'u.idunit = fp.unit_id', 'left')
            ->where('fp.status', 'rencana');

        if (! empty($filter['pegawai_id'])) {
            $builder->where('fp.pegawai_id', (int) $filter['pegawai_id']);
        }

        $bulan = (string) ($filter['bulan'] ?? '');
        if ($bulan !== '') {
            if (! preg_match('/^\d{4}-\d{2}$/', $bulan)) {
                throw new \InvalidArgumentException('Format bulan harus YYYY-MM.');
            }

            $from = $bulan . '-01';
            $builder->where('fp.due_date >=', $from)
                ->where('fp.due_date <=', date('Y-m-t', strtotime($from)));
        }

        if (! empty($filter['hanya_nonaktif'])) {
            // Sama dengan definisi aktif di PayrollGenerator::daftarKaryawan().
            $builder->where(
                "NOT (a.STATUS_PEGAWAI = 1 AND (a.deleted IS NULL OR a.deleted = ''))",
                null,
                false
            );
        }

        if (! empty($filter['unit_ids'])) {
            $builder->whereIn('fp.unit_id', array_values(array_map('intval', (array) $filter['unit_ids'])));
        }

        return $builder
            ->orderBy('fp.due_date', 'ASC')
            ->orderBy('a.NAMA_AKUN', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Batalkan satu draft. Hanya status `rencana` yang boleh dihapus.
     *
     * @throws \RuntimeException bila baris tidak ada, sudah dibayar, atau gagal dihapus.
     */
    public function batalkan(int $id): void
    {
        $row = $this->db->table('finance_payroll')->where('id', $id)->get()->getRow();

        if ($row === null) {
            throw new \RuntimeException('Draft payroll tidak ditemukan.');
        }

        if (($row->status ?? '') !== 'rencana') {
            throw new \RuntimeException('Hanya draft berstatus rencana yang bisa dibatalkan.');
        }

        if (! $this->db->table('finance_payroll')->where('id', $id)->delete()) {
            throw new \RuntimeException('Draft payroll gagal dibatalkan.');
        }
    }
}
