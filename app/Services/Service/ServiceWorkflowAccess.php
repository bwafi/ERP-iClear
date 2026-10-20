<?php

namespace App\Services\Service;

use Config\Database;
use CodeIgniter\Database\BaseConnection;

final class ServiceWorkflowAccess
{
    public const TECHNICIAN_MENU = 10153;
    public const CASHIER_MENU = 10154;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function can(int $accountId, int $jobId, int $menuId): bool
    {
        if ($accountId <= 0 || ! in_array($menuId, [self::TECHNICIAN_MENU, self::CASHIER_MENU], true)) {
            return false;
        }

        $account = $this->db->table('akun')->select('ROLES, ID_JABATAN')->where('ID_AKUN', $accountId)->get()->getRow();
        if (! $account) {
            return false;
        }

        $grants = $this->decode($account->ROLES ?? null);
        $effectiveJobId = (int) ($account->ID_JABATAN ?? $jobId);
        if ($effectiveJobId > 0) {
            $job = $this->db->table('jabatan')->select('ROLES_JABATAN')->where('ID_JABATAN', $effectiveJobId)->get()->getRow();
            $grants = array_merge($grants, $this->decode($job->ROLES_JABATAN ?? null));
        }

        return in_array($menuId, array_map('intval', $grants), true);
    }

    public function canTechnician(): bool
    {
        return $this->can((int) session('ID_AKUN'), (int) session('ID_JABATAN'), self::TECHNICIAN_MENU);
    }

    public function canCashier(): bool
    {
        return $this->can((int) session('ID_AKUN'), (int) session('ID_JABATAN'), self::CASHIER_MENU);
    }

    private function decode(?string $encoded): array
    {
        $decoded = json_decode((string) $encoded, true);
        return is_array($decoded) ? $decoded : [];
    }
}
