<?php

namespace Tests;

use App\Controllers\Penjualan;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Scope dropdown Staff pada halaman /penjualan.
 *
 * Tim toko hanya boleh memilih staff unitnya sendiri. Finance (0), Admin root
 * (1), dan Manager (34) adalah pengecualian: mereka boleh melihat staff semua
 * unit. Aturan ini juga divalidasi ulang di server supaya `sales_by` dari
 * request tidak bisa menunjuk staff unit lain.
 */
final class PenjualanStaffScopeTest extends CIUnitTestCase
{
    public function testRoleLintasUnitMelihatSemuaStaff(): void
    {
        foreach ([0, 1, 34] as $role) {
            $this->assertTrue(
                Penjualan::bolehPilihStaffLintasUnit($role),
                'role ' . $role . ' seharusnya melihat staff semua unit'
            );
            $this->assertNull(
                Penjualan::resolveStaffUnit($role, 3),
                'role ' . $role . ' tidak boleh dibatasi ke unit sesi'
            );
        }
    }

    public function testTimTokoHanyaMelihatStaffUnitnya(): void
    {
        // Kasir, frontliner, SPV, kepala toko, teknisi.
        foreach ([2, 35, 37, 40, 41, 47, 50] as $role) {
            $this->assertFalse(
                Penjualan::bolehPilihStaffLintasUnit($role),
                'role ' . $role . ' tidak boleh melihat staff lintas unit'
            );
            $this->assertSame(
                3,
                Penjualan::resolveStaffUnit($role, 3),
                'role ' . $role . ' harus dibatasi ke unit sesi'
            );
        }
    }

    public function testUnitKosongTetapMembatasi(): void
    {
        // Unit sesi 0 tidak boleh berubah menjadi "semua unit".
        $this->assertSame(0, Penjualan::resolveStaffUnit(35, 0));
        $this->assertNull(Penjualan::resolveStaffUnit(1, 0));
    }

    public function testControllerMemakaiScopeStaffDanValidasiServer(): void
    {
        $source = file_get_contents(APPPATH . 'Controllers/Penjualan.php');

        $this->assertStringContainsString('resolveStaffUnit', $source);
        $this->assertStringContainsString('getdataakun($staffUnit)', $source);
        $this->assertStringContainsString(
            'Staff yang dipilih tidak tersedia untuk unit ini.',
            $source
        );
    }
}
