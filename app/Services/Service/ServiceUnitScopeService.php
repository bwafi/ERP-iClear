<?php

namespace App\Services\Service;

use App\Models\ModelUnit;
use Config\Database;

/**
 * Cakupan unit halaman service untuk SPV.
 *
 * SPV (ID_JABATAN 40) membina beberapa cabang lewat tabel `spv_units`.
 * ServiceUnitScopeService memusatkan pembacaan mapping itu supaya daftar,
 * filter, dan validasi server memakai daftar cabang yang sama.
 */
final class ServiceUnitScopeService
{
    public const SPV_ROLE = 40;

    /** @var \CodeIgniter\Database\BaseConnection */
    private $db;

    /** @var ModelUnit */
    private $unitModel;

    public function __construct($db = null, ?ModelUnit $unitModel = null)
    {
        $this->db = $db ?? Database::connect();
        $this->unitModel = $unitModel ?? new ModelUnit();
    }

    public function isSpv(): bool
    {
        return (int) session('ID_JABATAN') === self::SPV_ROLE;
    }

    /**
     * ID cabang binaan SPV. Fallback ke unit sesi bila mapping tidak ada,
     * supaya SPV tanpa mapping tidak jatuh ke "semua unit".
     *
     * @return int[]
     */
    public function spvUnitIds(): array
    {
        $spvId = (int) session('ID_AKUN');
        $unitSesi = (int) session('ID_UNIT');

        if ($spvId <= 0 || ! $this->db->tableExists('spv_units')) {
            return $unitSesi > 0 ? [$unitSesi] : [];
        }

        $rows = $this->db->table('spv_units')
            ->select('unit_id')
            ->where('spv_id', $spvId)
            ->get()
            ->getResultArray();

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['unit_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        if ($ids !== []) {
            return array_values($ids);
        }

        return $unitSesi > 0 ? [$unitSesi] : [];
    }

    /**
     * Unit operasional untuk dropdown filter SPV, urut nama unit.
     *
     * @return object[]
     */
    public function spvUnits(): array
    {
        $ids = $this->spvUnitIds();
        if ($ids === []) {
            return [];
        }

        $units = $this->unitModel->whereIn('idunit', $ids)->findAll();

        $hasil = [];
        foreach ($units as $unit) {
            if ((int) ($unit->is_operational ?? 1) === 1) {
                $hasil[] = $unit;
            }
        }

        usort($hasil, static function ($a, $b): int {
            return strcmp((string) ($a->NAMA_UNIT ?? ''), (string) ($b->NAMA_UNIT ?? ''));
        });

        return $hasil;
    }

    /**
     * Filter unit efektif untuk request SPV: satu cabang bila valid, atau
     * seluruh cabang binaan. Sentinel -1 dipakai bila tidak ada unit sah
     * supaya query mengembalikan nol baris, bukan semua baris.
     *
     * @param mixed $requested
     * @return int[]
     */
    public function resolveSpvFilter($requested): array
    {
        $allowed = $this->spvUnitIds();
        if ($allowed === []) {
            return [-1];
        }

        $req = is_numeric($requested) ? (int) $requested : 0;
        if ($req > 0 && in_array($req, $allowed, true)) {
            return [$req];
        }

        return $allowed;
    }
}
