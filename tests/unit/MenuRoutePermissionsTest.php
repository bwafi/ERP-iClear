<?php

namespace Tests\unit;

use App\Security\MenuRoutePermissions;
use PHPUnit\Framework\TestCase;

final class MenuRoutePermissionsTest extends TestCase
{
    public function testMapsPageAjaxDetailExportAndSaveToExistingMenuPermission(): void
    {
        self::assertSame('kartu_stok', MenuRoutePermissions::resolve('GET', '/kartu_stok/detail/18/2'));
        self::assertSame('kartu_stok', MenuRoutePermissions::resolve('POST', '/export/kartu_stock'));
        self::assertSame('garansi_service', MenuRoutePermissions::resolve('POST', '/claim_garansi'));
        self::assertSame('retur_customer', MenuRoutePermissions::resolve('GET', '/retur_customer/invoice/42'));
        self::assertSame('input_barang_rusak', MenuRoutePermissions::resolve('GET', '/input_barang_rusak'));
        self::assertSame('input_barang_rusak', MenuRoutePermissions::resolve('POST', '/insert_barang_rusak'));
        self::assertSame('kasir_bulanan', MenuRoutePermissions::resolve('GET', '/cetak-tutup-kasir/9'));
        self::assertSame('sisi_keuangan', MenuRoutePermissions::resolve('GET', '/cetak/posisi_keuangan'));
        self::assertSame('rekonsiliasi', MenuRoutePermissions::resolve('POST', '/finance/rekon/approve'));
        self::assertSame('LaporanKeuangan', MenuRoutePermissions::resolve('GET', '/LaporanKeuangan/laba_rugi'));
        self::assertSame('laba_service', MenuRoutePermissions::resolve('POST', '/laba_service/export'));
        self::assertSame('pembelian', MenuRoutePermissions::resolve('POST', '/insert_produk'));
        self::assertSame('kategori', MenuRoutePermissions::resolve('POST', '/kategori/insert_kategori'));
        self::assertSame('kategori', MenuRoutePermissions::resolve('POST', '/kategori/update_kategori'));
        self::assertSame('kategori_kas', MenuRoutePermissions::resolve('POST', '/insert_kategori'));
    }

    public function testRoleFixturesOnlyAllowExactMenuPermissionAlreadyAssigned(): void
    {
        $menuRows = [
            ['idmenu' => 72, 'roles' => 'proses_service'],
            ['idmenu' => 94, 'roles' => 'kartu_stok'],
        ];

        $administratorFixture = ['1', '72', '94'];
        $serviceStaffFixture = ['72'];
        $serviceOnlyMenuRows = [$menuRows[0]];

        self::assertTrue(MenuRoutePermissions::isGranted('proses_service', $administratorFixture, $menuRows));
        self::assertTrue(MenuRoutePermissions::isGranted('proses_service', $serviceStaffFixture, $serviceOnlyMenuRows));
        self::assertFalse(MenuRoutePermissions::isGranted('kartu_stok', $serviceStaffFixture, $menuRows));
        self::assertFalse(MenuRoutePermissions::isGranted('kartu_stok', ['1'], $menuRows));
        self::assertFalse(MenuRoutePermissions::isGranted('unknown', $administratorFixture, $menuRows));
    }

    public function testUnknownRoutesAndMethodsDoNotInheritAnUnrelatedPermission(): void
    {
        self::assertNull(MenuRoutePermissions::resolve('GET', '/buku_besar'));
        self::assertNull(MenuRoutePermissions::resolve('DELETE', '/kartu_stok'));
        self::assertNull(MenuRoutePermissions::resolve('GET', '/produk_terlaris'));
    }

    public function testPermissionFilterRunsForEveryRouteSoUnfilteredAliasesAreCovered(): void
    {
        $filters = new \Config\Filters();

        self::assertSame(\App\Filters\MenuPermissionFilter::class, $filters->aliases['menupermission']);
        self::assertSame(['*'], $filters->filters['menupermission']['before']);
        self::assertSame('laba_service', MenuRoutePermissions::resolve('GET', '/laba_service'));
    }
}
