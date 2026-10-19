<?php

use App\Services\Service\WarrantyServiceAccess;
use PHPUnit\Framework\TestCase;

final class WarrantyServiceAccessTest extends TestCase
{
    public function testAllowsMenuGrantedThroughJabatan(): void
    {
        $account = (object) [
            'ROLES' => '[]',
            'ROLES_JABATAN' => '[76,72]',
        ];

        $this->assertTrue(WarrantyServiceAccess::allows($account, 76));
    }

    public function testAllowsExplicitUserMenuGrant(): void
    {
        $account = (object) [
            'ROLES' => '["76"]',
            'ROLES_JABATAN' => '[]',
        ];

        $this->assertTrue(WarrantyServiceAccess::allows($account, 76));
    }

    public function testRejectsMissingMenuGrantOrInvalidAccount(): void
    {
        $account = (object) [
            'ROLES' => '[72]',
            'ROLES_JABATAN' => '[]',
        ];

        $this->assertFalse(WarrantyServiceAccess::allows($account, 76));
        $this->assertFalse(WarrantyServiceAccess::allows(null, 76));
        $this->assertFalse(WarrantyServiceAccess::allows($account, 0));
    }
}
