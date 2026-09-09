<?php

namespace App\Services\Konten;

/**
 * Scope & permission modul Content Management (KPI Multimedia/Creative).
 *
 * Memakai session existing (ID_JABATAN / ID_UNIT / ID_AKUN) — tanpa membuat
 * sistem permission baru. Sesuai beberapa modul existing (PenilaianKPI):
 *   - Role 0,1,2,34 (Admin Center/Root, Direktur, Manager) → akses penuh
 *   - Role 43 (Kepala Divisi)              → monitoring read-only, scope seluruh divisi
 *   - Role 44 (Multimedia/Creative)        → operasional, scope SELURUH divisi (KPI jabatan
 *     multimedia bersifat perusahaan, jadi filter unit memuat semua unit).
 *   - Role 48 (Talent)                     → view-only (orang yang tampil).
 */
class ContentScopeService
{
    public const ROLES_VIEW = [0, 1, 2, 34, 43, 44, 48];
    public const ROLES_WRITE = [0, 1, 2, 34, 44];
    public const ROLES_QC = [0, 1, 2, 34, 43, 44]; // QC & checklist

    public const ROLE_KADIV = 43;
    public const ROLE_MULTIMEDIA = 44;
    public const ROLE_TALENT = 48;

    private $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public static function canView(int $role): bool
    {
        return in_array($role, self::ROLES_VIEW, true);
    }

    public static function canWrite(int $role): bool
    {
        return in_array($role, self::ROLES_WRITE, true);
    }

    public static function canQc(int $role): bool
    {
        return in_array($role, self::ROLES_QC, true);
    }

    /**
     * Scope untuk Dashboard Digital Marketing.
     *
     * KPI konten adalah KPI jabatan Multimedia, jadi role operasional
     * multimedia (44) juga melihat ringkasan KPI seluruh divisi. Operasional
     * CRUD tetap memakai scope unit sendiri (scopeSql).
     */
    public function kpiScopeSql(int $role, int $unit, int $akunId): ?string
    {
        if (in_array($role, [0, 1, 2, 34, self::ROLE_KADIV, self::ROLE_MULTIMEDIA], true)) {
            return null;
        }

        return $this->scopeSql($role, $unit, $akunId);
    }

    /**
     * Unit yang boleh dilihat (untuk filter UI).
     * null = semua unit.
     */
    public function allowedUnits(int $role, int $unit, int $akunId): ?array
    {
        if (in_array($role, [0, 1, 2, 34, self::ROLE_KADIV, self::ROLE_MULTIMEDIA], true)) {
            return null;
        }

        if ($role === 40) {
            return $this->spvUnits($akunId, $unit);
        }

        return [$unit];
    }

    /**
     * Fragment WHERE mentah untuk tabel "contents c" berdasar scope user.
     * null = tanpa filter (lihat semua).
     */
    public function scopeSql(int $role, int $unit, int $akunId): ?string
    {
        if (in_array($role, [0, 1, 2, 34, self::ROLE_KADIV, self::ROLE_MULTIMEDIA], true)) {
            return null;
        }

        if ($role === 40) {
            $units = $this->spvUnits($akunId, $unit);
            if ($units === null) {
                return null;
            }
            return $this->unitsScopeFragment($units);
        }

        // Role lainnya: unit sendiri ATAU content yang melibatkan dia.
        return "(
            c.target_scope = 'ALL'
            OR EXISTS(SELECT 1 FROM content_units cu WHERE cu.content_id = c.id AND cu.unit_id = {$unit})
            OR EXISTS(SELECT 1 FROM publications pp WHERE pp.content_id = c.id AND pp.unit_id = {$unit})
            OR EXISTS(SELECT 1 FROM content_people cp WHERE cp.content_id = c.id AND cp.akun_id = {$akunId})
        )";
    }

    private function unitsScopeFragment(array $units): string
    {
        if (empty($units)) {
            return '1 = 0';
        }
        $in = implode(',', array_map('intval', $units));

        return "(
            c.target_scope = 'ALL'
            OR EXISTS(SELECT 1 FROM content_units cu WHERE cu.content_id = c.id AND cu.unit_id IN ({$in}))
            OR EXISTS(SELECT 1 FROM publications pp WHERE pp.content_id = c.id AND pp.unit_id IN ({$in}))
        )";
    }

    private function spvUnits(int $akunId, int $unit): ?array
    {
        $rows = $this->db->table('spv_units')
            ->select('unit_id')
            ->where('spv_id', $akunId)
            ->get()
            ->getResult();

        if (!empty($rows)) {
            return array_map('intval', array_column($rows, 'unit_id'));
        }

        return [$unit];
    }
}