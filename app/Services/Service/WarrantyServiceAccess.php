<?php

namespace App\Services\Service;

final class WarrantyServiceAccess
{
    public static function allows(?object $account, int $menuId): bool
    {
        if (! $account || $menuId <= 0) {
            return false;
        }

        foreach (['ROLES', 'ROLES_JABATAN'] as $field) {
            $roles = json_decode((string) ($account->{$field} ?? ''), true);
            if (! is_array($roles)) {
                continue;
            }

            foreach ($roles as $role) {
                if ((int) $role === $menuId) {
                    return true;
                }
            }
        }

        return false;
    }
}
