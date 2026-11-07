<?php

namespace Tests;

use App\Controllers\Kartu_Stok;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guard role koreksi HPP Kartu Stok: hanya Finance (0), Admin root (1),
 * Manager (34), ADMIN/KASIR (35 & 47), dan Kepala Toko (41).
 */
class KartuStokHppRoleTest extends CIUnitTestCase
{
    public function testRoleYangBolehUbahHpp(): void
    {
        foreach ([0, 1, 34, 35, 41, 47] as $role) {
            $this->assertTrue(Kartu_Stok::bolehUbahHpp($role), 'role ' . $role);
        }
    }

    public function testRoleYangTidakBolehUbahHpp(): void
    {
        foreach ([2, 36, 37, 38, 39, 40, 42, 43, 44, 45, 46, 48] as $role) {
            $this->assertFalse(Kartu_Stok::bolehUbahHpp($role), 'role ' . $role);
        }
    }
}
