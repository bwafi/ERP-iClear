<?php

namespace App\Services\Kpi;

use Config\Database;

/**
 * Scope karyawan halaman Penilaian Harian (/penilaian/absen) untuk SPV.
 *
 * SPV (ID_JABATAN 40) membina beberapa cabang lewat tabel `spv_units`.
 * Halaman ini menampilkan seluruh pegawai aktif di cabang binaan plus dirinya
 * sendiri. Pegawai yang tidak boleh dinilai tetap bisa dilihat (read-only);
 * otorisasi tulis tetap memakai EvaluatorAuthorizationService di controller.
 */
final class AbsenEmployeeScopeService
{
    public const SPV_ROLE = 40;

    /** Role yang boleh melihat seluruh pegawai lintas unit. */
    public const VIEW_ALL_ROLES = [0, 34];

    /** @var \CodeIgniter\Database\BaseConnection */
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * ID cabang binaan SPV. Fallback ke unit sesi bila mapping tidak ada,
     * supaya SPV tanpa mapping tidak jatuh ke "semua unit".
     *
     * @return int[]
     */
    public function spvUnitIds(int $spvId, int $unitSesi): array
    {
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
     * Pegawai aktif di unit binaan SPV plus dirinya sendiri.
     *
     * @return array<int, array<string, mixed>>
     */
    public function daftarKaryawanSpv(int $spvId, int $unitSesi): array
    {
        $unitIds = $this->spvUnitIds($spvId, $unitSesi);

        $builder = $this->activeEmployeeBuilder();

        if ($unitIds === []) {
            $builder->where('a.ID_AKUN', $spvId);
        } else {
            $builder->groupStart()
                ->whereIn('a.ID_UNIT', $unitIds)
                ->orWhere('a.ID_AKUN', $spvId)
                ->groupEnd();
        }

        return $builder
            ->orderBy('a.ID_UNIT', 'ASC')
            ->orderBy('a.NAMA_AKUN', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Apakah role ini boleh melihat seluruh pegawai lintas unit?
     */
    public static function isViewAllRole(int $role): bool
    {
        return in_array($role, self::VIEW_ALL_ROLES, true);
    }

    /**
     * Seluruh pegawai aktif lintas unit (Finance/Manager), termasuk dirinya.
     *
     * @return array<int, array<string, mixed>>
     */
    public function daftarKaryawanSemua(): array
    {
        return $this->activeEmployeeBuilder()
            ->orderBy('a.ID_UNIT', 'ASC')
            ->orderBy('a.NAMA_AKUN', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Builder dasar pegawai aktif yang belum difilter scope.
     *
     * @return \CodeIgniter\Database\BaseBuilder
     */
    private function activeEmployeeBuilder()
    {
        return $this->db->table('akun a')
            ->select('a.ID_AKUN, a.ID_JABATAN, a.ID_UNIT, a.NAMA_AKUN, j.NAMA_JABATAN, u.NAMA_UNIT')
            ->join('jabatan j', 'j.ID_JABATAN = a.ID_JABATAN', 'left')
            ->join('unit u', 'u.idunit = a.ID_UNIT', 'left')
            ->where('a.STATUS_PEGAWAI', 1)
            ->groupStart()
            ->where('a.deleted', null)
            ->orWhere('a.deleted', 0)
            ->groupEnd();
    }

    /**
     * Boleh melihat data pegawai ini di halaman Penilaian Harian?
     *
     * Hanya untuk SPV: dirinya sendiri atau pegawai di unit binaan. Ini scope
     * LIHAT, bukan izin tulis; izin tulis tetap dicek per komponen.
     */
    public static function canView(int $viewerRole, int $viewerId, int $employeeId, int $employeeUnit, array $allowedUnitIds): bool
    {
        if ($viewerRole !== self::SPV_ROLE) {
            return false;
        }

        if ($employeeId === $viewerId) {
            return true;
        }

        return in_array($employeeUnit, array_map('intval', $allowedUnitIds), true);
    }
}
