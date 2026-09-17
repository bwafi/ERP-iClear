<?php

namespace App\Services\SocialMedia;

/**
 * Scope & permission modul Social Media KPI — memakai session existing
 * (ID_JABATAN / ID_UNIT) tanpa sistem permission baru.
 *
 *   - View KPI  : Admin Center (0), Root (1), Direktur (2), Manager (34),
 *                 Kepala Divisi Digital Marketing (43)
 *   - Kelola akun & target: 0, 1, 2, 34, 43
 *
 * Scope data: role pusat/manajemen melihat semua unit; selain itu scope dibatasi
 * unit akun (untuk role tambahan di masa depan).
 */
class SocialMediaScopeService
{
    public const ROLES_MANAGE = [0, 1, 2, 34, 43];
    public const ROLES_VIEW   = [0, 1, 2, 34, 43];

    public static function canView(int $role): bool
    {
        return in_array($role, self::ROLES_VIEW, true);
    }

    public static function canManage(int $role): bool
    {
        return in_array($role, self::ROLES_MANAGE, true);
    }

    /**
     * Unit yang boleh dilihat akun. null = semua unit.
     */
    public static function allowedUnitIds(int $role, int $unit): ?array
    {
        if (in_array($role, self::ROLES_VIEW, true)) {
            return null;
        }
        return [$unit];
    }
}