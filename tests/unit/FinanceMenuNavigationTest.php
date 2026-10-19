<?php

namespace Tests\unit;

use App\Services\Finance\FinanceMenuNavigation;
use PHPUnit\Framework\TestCase;

final class FinanceMenuNavigationTest extends TestCase
{
    public function testGroupsExistingFinanceLinksWithoutCreatingPermissions(): void
    {
        $categories = [
            [
                'id' => 100,
                'nama' => 'Transaksi',
                'icon' => '',
                'url' => '',
                'role' => null,
                'utama' => 0,
                'menu' => [
                    ['id' => 101, 'nama' => 'Dashboard Finance', 'icon' => '', 'url' => 'dashboard/finance', 'role' => null, 'utama' => 1, 'sub' => []],
                    ['id' => 102, 'nama' => 'Penjualan', 'icon' => '', 'url' => 'penjualan', 'role' => null, 'utama' => 1, 'sub' => []],
                ],
            ],
            [
                'id' => 200,
                'nama' => 'Master',
                'icon' => '',
                'url' => '',
                'role' => null,
                'utama' => 0,
                'menu' => [
                    ['id' => 201, 'nama' => 'Neraca', 'icon' => '', 'url' => 'sisi_keuangan', 'role' => null, 'utama' => 1, 'sub' => []],
                ],
            ],
        ];

        $grouped = FinanceMenuNavigation::group($categories, [101, 201]);

        self::assertCount(2, $grouped);
        self::assertSame('Penjualan', $grouped[0]['menu'][0]['nama']);
        self::assertSame('Keuangan', $grouped[1]['nama']);
        self::assertSame(101, $grouped[1]['id']);
        self::assertSame(['dashboard/finance', 'sisi_keuangan'], array_column($grouped[1]['menu'], 'url'));
        self::assertSame([101, 201], array_column($grouped[1]['menu'], 'id'));
    }

    public function testDoesNotAddVirtualGroupWhenNoExistingFinancePermissionIsPresent(): void
    {
        $categories = [[
            'id' => 100,
            'nama' => 'Transaksi',
            'icon' => '',
            'url' => '',
            'role' => null,
            'utama' => 0,
            'menu' => [
                ['id' => 101, 'nama' => 'Dashboard Finance', 'icon' => '', 'url' => 'dashboard/finance', 'role' => null, 'utama' => 1, 'sub' => []],
            ],
        ]];

        $grouped = FinanceMenuNavigation::group($categories, []);

        self::assertCount(1, $grouped);
        self::assertSame($categories[0]['menu'], $grouped[0]['menu']);
    }
}
