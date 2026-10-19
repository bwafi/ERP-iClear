<?php

use App\Services\Service\WarrantyServiceAccess;
use PHPUnit\Framework\TestCase;

final class WarrantyServiceAccessTest extends TestCase
{
    public function testAllowsMenuGrantedThroughJabatan(): void
    {
        $account = (object) [
            'ROLES' => '[]',
            'ROLES_JABATAN' => '[10151,72]',
        ];

        $this->assertTrue(WarrantyServiceAccess::allows($account, 10151));
    }

    public function testAllowsExplicitUserMenuGrant(): void
    {
        $account = (object) [
            'ROLES' => '["10151"]',
            'ROLES_JABATAN' => '[]',
        ];

        $this->assertTrue(WarrantyServiceAccess::allows($account, 10151));
    }

    public function testRejectsMissingMenuGrantOrInvalidAccount(): void
    {
        $account = (object) [
            'ROLES' => '[72]',
            'ROLES_JABATAN' => '[]',
        ];

        $this->assertFalse(WarrantyServiceAccess::allows($account, 10151));
        $this->assertFalse(WarrantyServiceAccess::allows(null, 10151));
        $this->assertFalse(WarrantyServiceAccess::allows($account, 0));
    }
}
