<?php

namespace Tests\unit;

use App\Services\Sidebar\SidebarNavigation;
use PHPUnit\Framework\TestCase;

final class SidebarNavigationTest extends TestCase
{
    public function testDashboardRemainsFirstWithoutCreatingOrGrantingAnyLinks(): void
    {
        $tree = [
            ['id' => 9, 'nama' => 'Supplier', 'url' => 'supplier', 'menu' => []],
            ['id' => 2, 'nama' => 'DASHBOARD', 'url' => '', 'menu' => [
                ['id' => 3, 'nama' => 'Omset', 'url' => 'omset_bulanan', 'sub' => []],
            ]],
        ];
        $organized = SidebarNavigation::organize($tree, [2, 3, 9]);
        self::assertSame(['Dashboard', 'Pembelian'], array_column($organized, 'nama'));
        self::assertSame(['omset_bulanan', 'supplier'], $this->allUrls($organized));
        self::assertNotContains('supplier', $this->allUrls(SidebarNavigation::organize($tree, [2, 3])));
    }

    public function testGroupsOnlyExistingAuthorizedMenuLinksAndKeepsUnmappedOperations(): void
    {
        $tree = [
            [
                'id' => 1,
                'nama' => 'Operasional',
                'menu' => [
                    ['id' => 11, 'nama' => 'Produk', 'url' => 'produk/', 'sub' => []],
                    ['id' => 12, 'nama' => 'Pembelian', 'url' => '/pembelian', 'sub' => []],
                    ['id' => 13, 'nama' => 'Stok', 'url' => '#', 'sub' => [
                        ['id' => 14, 'nama' => 'Kartu Stok', 'url' => 'kartu_stok', 'icon' => null],
                        ['id' => 15, 'nama' => 'Riwayat Tugas', 'url' => 'riwayat_tugas', 'icon' => null],
                    ]],
                    ['id' => 16, 'nama' => 'Penjualan', 'url' => 'penjualan', 'sub' => []],
                    ['id' => 17, 'nama' => 'Detail Garansi Dinamis', 'url' => 'service_by_garansi/(:num)', 'sub' => []],
                ],
            ],
        ];

        $organized = SidebarNavigation::organize($tree, [1, 11, 12, 13, 14, 15, 17]);

        self::assertSame(['Produk & Harga', 'Persediaan', 'Pembelian', 'Operasional'], array_column($organized, 'nama'));
        self::assertSame('Master Barang/Jasa', $organized[0]['menu'][0]['sub'][0]['nama']);
        self::assertSame('Kartu Stok', $organized[1]['menu'][0]['sub'][0]['nama']);
        self::assertSame('Pembelian', $organized[2]['menu'][0]['sub'][0]['nama']);
        self::assertSame('Riwayat Tugas', $organized[3]['menu'][0]['sub'][0]['nama']);
        self::assertNotContains('Penjualan', array_column($organized, 'nama'));
        self::assertNotContains('https://example.test/pricelist', $this->allUrls($organized));
    }

    public function testDoesNotCreateLinksOrPermissionForFeaturesMissingFromTheMenuTree(): void
    {
        $organized = SidebarNavigation::organize([[
            'id' => 1,
            'nama' => 'Keuangan Lama',
            'menu' => [
                ['id' => 20, 'nama' => 'Neraca', 'url' => 'sisi_keuangan', 'sub' => []],
            ],
        ]], [1, 20]);

        $urls = $this->allUrls($organized);
        self::assertContains('sisi_keuangan', $urls);
        self::assertNotContains('pricelist', $urls);
        self::assertNotContains('buku_besar', $urls);
        self::assertSame(20, $organized[0]['id']);
    }

    public function testGroupsFlatRootRoutesAndKeepsUnmappedRootOperations(): void
    {
        $tree = [
            ['id' => 76, 'nama' => 'Garansi Service', 'url' => 'garansi_service', 'menu' => []],
            ['id' => 9, 'nama' => 'Supplier', 'url' => 'supplier', 'menu' => []],
            ['id' => 71, 'nama' => 'Proses Service', 'url' => 'proses_service', 'menu' => []],
            ['id' => 51, 'nama' => 'Stok Produk', 'url' => 'kartu_stok', 'menu' => []],
            ['id' => 210, 'nama' => 'Piutang Pegawai', 'url' => 'piutang', 'menu' => []],
            ['id' => 112, 'nama' => 'Presensi', 'url' => 'absensi', 'menu' => []],
        ];

        $organized = SidebarNavigation::organize($tree, [9, 51, 71, 76, 112, 210]);

        self::assertSame(['Service', 'Persediaan', 'Pembelian', 'Keuangan', 'Presensi'], array_column($organized, 'nama'));
        self::assertContains('garansi_service', $this->allUrls($organized));
        self::assertContains('supplier', $this->allUrls($organized));
        self::assertContains('kartu_stok', $this->allUrls($organized));
        self::assertContains('piutang', $this->allUrls($organized));
        self::assertContains('absensi', $this->allUrls($organized));
    }

    public function testKeepsOnlyAuthorizedLinksAndDoesNotDuplicateAliasesByExactRoute(): void
    {
        $tree = [[
            'id' => 1,
            'nama' => 'Lama',
            'menu' => [
                ['id' => 21, 'nama' => 'Dashboard Finance', 'url' => 'https://test.example/index.php/dashboard/finance?from=nav', 'sub' => []],
                ['id' => 22, 'nama' => 'Dashboard duplikat', 'url' => 'dashboard/finance/', 'sub' => []],
                ['id' => 23, 'nama' => 'Utang', 'url' => 'hutangpiutang/hutang', 'sub' => []],
            ],
        ]];

        $organized = SidebarNavigation::organize($tree, [1, 21, 23]);

        self::assertSame(['Keuangan'], array_column($organized, 'nama'));
        self::assertSame(['KPI Keuangan', 'Utang Supplier'], $this->labels($organized[0]));
        self::assertNotContains(22, $this->allIds($organized));
        self::assertSame('dashboard/finance', SidebarNavigation::normalizeRoute('https://example.test/index.php/dashboard/finance/?x=1'));
    }

    public function testMatchesActiveRouteAndNestedPages(): void
    {
        self::assertTrue(SidebarNavigation::isActive('mutasi_stok', '/mutasi_stok/purchase-batches/5/2'));
        self::assertTrue(SidebarNavigation::isActive('/sisi_keuangan/', 'sisi_keuangan'));
        self::assertFalse(SidebarNavigation::isActive('#', 'sisi_keuangan'));
        self::assertFalse(SidebarNavigation::isActive('produk', 'produk_terlaris'));
    }

    private function allUrls(array $nodes): array
    {
        $urls = [];
        foreach ($nodes as $node) {
            if (!empty($node['url']) && $node['url'] !== '#') {
                $urls[] = SidebarNavigation::normalizeRoute((string) $node['url']);
            }
            $urls = array_merge($urls, $this->allUrls($node['menu'] ?? []), $this->allUrls($node['sub'] ?? []));
        }
        return $urls;
    }

    private function allIds(array $nodes): array
    {
        $ids = [];
        foreach ($nodes as $node) {
            $ids[] = $node['id'];
            $ids = array_merge($ids, $this->allIds($node['menu'] ?? []), $this->allIds($node['sub'] ?? []));
        }
        return $ids;
    }

    private function labels(array $area): array
    {
        $labels = [];
        foreach ($area['menu'] as $section) {
            foreach ($section['sub'] as $link) {
                $labels[] = $link['nama'];
            }
        }
        return $labels;
    }
}
