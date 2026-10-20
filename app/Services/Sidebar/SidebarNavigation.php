<?php

namespace App\Services\Sidebar;

final class SidebarNavigation
{
    private const AREAS = [
        'Service' => [
            'icon' => 'solar:smartphone-rotate-angle-bold-duotone',
            'routes' => [
                'dashboard/service' => ['Laporan', 'Dashboard Service'],
                'service' => ['Transaksi', 'Input Service'],
                'proses_service' => ['Transaksi', 'Proses Service'],
                'garansi_service' => ['Transaksi', 'Garansi Service'],
                'dp_service' => ['Transaksi', 'Pembayaran Awal Service'],
                'bisa_diambil' => ['Transaksi', 'Service Siap Diambil'],
                'sudah_diambil' => ['Laporan', 'Service Sudah Diambil'],
                'expired_service' => ['Laporan', 'Service Kedaluwarsa'],
                'expired_proses' => ['Transaksi', 'Proses Kedaluwarsa'],
                'list/service/dibatalkan' => ['Laporan', 'Service Dibatalkan'],
                'notif_service' => ['Laporan', 'Notifikasi Service'],
                'sparepart_keluar' => ['Transaksi', 'Pemakaian Sparepart Service'],
                'riwayat_service' => ['Laporan', 'Riwayat Service'],
                'riwayat_service_garansi' => ['Laporan', 'Riwayat Garansi'],
                'kerusakan' => ['Master/Pengaturan', 'Master Kerusakan'],
                'fee_service' => ['Laporan', 'Fee Service'],
                'laba_service' => ['Laporan', 'Laba Service'],
            ],
        ],
        'Produk & Harga' => [
            'icon' => 'solar:box-bold-duotone',
            'routes' => [
                'produk' => ['Master/Pengaturan', 'Master Barang/Jasa'],
                'produk/kartu-harga' => ['Master/Pengaturan', 'Kartu Harga'],
                'kategori' => ['Master/Pengaturan', 'Kategori'],
                'produk/review-tipe' => ['Master/Pengaturan', 'Review Tipe Item'],
            ],
        ],
        'Pembelian' => [
            'icon' => 'solar:cart-large-4-bold-duotone',
            'routes' => [
                'supplier' => ['Master/Pengaturan', 'Master Supplier'],
                'pembelian' => ['Transaksi', 'Pembelian'],
                'riwayat_pembelian' => ['Laporan', 'Riwayat Pembelian'],
                'retur_suplier' => ['Transaksi', 'Retur Supplier'],
                'kredit_retur_suplier' => ['Transaksi', 'Kredit Retur Supplier'],
                'riwayat_retur_pembelian' => ['Laporan', 'Riwayat Retur Supplier'],
            ],
        ],
        'Persediaan' => [
            'icon' => 'solar:database-bold-duotone',
            'routes' => [
                'kartu_stok' => ['Transaksi', 'Kartu Stok'],
                'stok_opname' => ['Transaksi', 'Stok Opname'],
                'riwayat_stok_opname' => ['Laporan', 'Riwayat Stok Opname'],
                'mutasi_stok' => ['Transaksi', 'Transfer Stok'],
                'riwayat_mutasi' => ['Laporan', 'Riwayat Transfer Stok'],
                'barang_rusak' => ['Transaksi', 'Barang Rusak'],
                'stok_minimum' => ['Master/Pengaturan', 'Batas Minimum Stok'],
                'stok' => ['Transaksi', 'Kategori Saldo Awal'],
                'stok_awal' => ['Laporan', 'Daftar Saldo Awal'],
                'input_stokawal' => ['Transaksi', 'Input Saldo Awal'],
                'stok_hp' => ['Laporan', 'Stok iPhone'],
                'input_barang_rusak' => ['Transaksi', 'Input Barang Rusak'],
            ],
        ],
        'Penjualan' => [
            'icon' => 'solar:wallet-money-bold-duotone',
            'routes' => [
                'pelanggan' => ['Master/Pengaturan', 'Master Pelanggan'],
                'penjualan' => ['Transaksi', 'Penjualan'],
                'riwayat_penjualan' => ['Laporan', 'Riwayat Penjualan'],
                'retur_customer' => ['Transaksi', 'Retur Customer'],
                'riwayat_retur_penjualan' => ['Laporan', 'Riwayat Retur Customer'],
            ],
        ],
        'Keuangan' => [
            'icon' => 'solar:chart-square-bold-duotone',
            'routes' => [
                'dashboard/finance' => ['Laporan', 'KPI Keuangan'],
                'dashboard/keuangan' => ['Laporan', 'Ringkasan Keuangan'],
                'datamaster_akun' => ['Master Keuangan', 'Akun/COA'],
                'akun_jurnal_garansi' => ['Master Keuangan', 'Pemetaan Akun Garansi'],
                'kas_bank/akun' => ['Master Keuangan', 'Master Kas & Bank'],
                'kategori_kas' => ['Master Keuangan', 'Kategori Kas'],
                'kas_bank' => ['Kas & Bank', 'Kas & Bank'],
                'kas_bank/transfer' => ['Kas & Bank', 'Transfer Internal'],
                'kas_bank/antar-unit' => ['Kas & Bank', 'Pembayaran Antar Cabang'],
                'kas_bank/antar_unit' => ['Kas & Bank', 'Pembayaran Antar Cabang'],
                'kas_masuk' => ['Kas & Bank', 'Kas Masuk'],
                'kas_keluar' => ['Kas & Bank', 'Kas Keluar'],
                'kasir_bulanan' => ['Kas & Bank', 'Riwayat Tutup Kasir'],
                'tutup_kasir' => ['Kas & Bank', 'Tutup Kasir'],
                'hutangpiutang/dashboard' => ['Utang/Piutang', 'Ringkasan Utang/Piutang'],
                'hutangpiutang/hutang' => ['Utang/Piutang', 'Utang Supplier'],
                'hutangpiutang/piutang' => ['Utang/Piutang', 'Piutang'],
                'daftar_tagihan' => ['Utang/Piutang', 'Tagihan Supplier'],
                'piutang' => ['Utang/Piutang', 'Daftar Piutang'],
                'laporan_jurnal' => ['Laporan', 'Jurnal'],
                'sisi_keuangan' => ['Laporan', 'Neraca'],
                'laporankeuangan/laba_rugi' => ['Laporan', 'Laba Rugi'],
                'omset_bulanan/arus_kas' => ['Laporan', 'Arus Kas'],
                'finance/rekonsiliasi' => ['Laporan', 'Rekonsiliasi Keuangan'],
            ],
        ],
        'Administrasi' => [
            'icon' => 'solar:settings-bold-duotone',
            'routes' => [
                'pegawai' => ['Master/Pengaturan', 'Pengguna'],
                'pegawai/jabatan' => ['Master/Pengaturan', 'Jabatan & Hak Akses'],
                'unit' => ['Master/Pengaturan', 'Cabang'],
            ],
        ],
    ];

    public static function organize(array $categories, array $roles): array
    {
        $roleIds = array_fill_keys(array_map('strval', $roles), true);
        $areas = [];
        $remaining = [];
        $seenRoutes = [];

        foreach ($categories as $category) {
            $remainingCategory = $category;
            $remainingMenus = [];
            $categoryHasMenus = !empty($category['menu']);
            foreach ($category['menu'] ?? [] as $menu) {
                $menuUrl = (string) ($menu['url'] ?? '');
                $menuRoute = self::normalizeRoute($menuUrl);
                $menuMoved = self::moveLink($menu, $menuRoute, $roleIds, $areas, $seenRoutes);
                $remainingSubmenus = [];

                foreach ($menu['sub'] ?? [] as $submenu) {
                    $route = self::normalizeRoute((string) ($submenu['url'] ?? ''));
                    if (!self::moveLink($submenu, $route, $roleIds, $areas, $seenRoutes)) {
                        $remainingSubmenus[] = $submenu;
                    }
                }

                if (!$menuMoved) {
                    $menu['sub'] = $remainingSubmenus;
                } elseif ($remainingSubmenus !== []) {
                    $menu['url'] = '#';
                    $menu['sub'] = $remainingSubmenus;
                }
                if ((!$menuMoved && $menuUrl !== '') || $remainingSubmenus !== []) {
                    $remainingMenus[] = $menu;
                }
            }

            $remainingCategory['menu'] = $remainingMenus;
            if (!$categoryHasMenus && !empty($category['url'])) {
                $categoryRoute = self::normalizeRoute((string) $category['url']);
                if (self::moveLink($category, $categoryRoute, $roleIds, $areas, $seenRoutes)) {
                    continue;
                }
                $remainingCategory['menu'] = [];
                $remaining[] = $remainingCategory;
            } elseif ($remainingMenus !== []) {
                $remaining[] = $remainingCategory;
            }
        }

        $grouped = [];
        foreach (self::AREAS as $areaName => $definition) {
            if (empty($areas[$areaName])) {
                continue;
            }

            $sections = [];
            foreach ($areas[$areaName] as $sectionName => $links) {
                if ($links === []) {
                    continue;
                }
                $sections[] = [
                    'id' => $links[0]['id'],
                    'nama' => $sectionName,
                    'icon' => null,
                    'url' => '#',
                    'role' => null,
                    'utama' => 1,
                    'sub' => array_values($links),
                ];
            }

            if ($sections === []) {
                continue;
            }

            $grouped[] = [
                'id' => $sections[0]['id'],
                'nama' => $areaName,
                'icon' => '<iconify-icon icon="' . $definition['icon'] . '" class="fs-6"></iconify-icon>',
                'url' => '',
                'role' => null,
                'utama' => 1,
                'menu' => $sections,
            ];
        }

        return array_merge($grouped, $remaining);
    }

    public static function normalizeRoute(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path)) {
            return '';
        }

        $route = strtolower(trim($path, '/'));
        $route = preg_replace('#^(?:index\.php/)+#', '', $route) ?? $route;
        return trim($route, '/');
    }

    public static function isActive(string $url, string $currentPath): bool
    {
        $route = self::normalizeRoute($url);
        $current = self::normalizeRoute($currentPath);
        if ($route === '' || $route === '#') {
            return false;
        }

        return $route === $current || strpos($current, $route . '/') === 0;
    }

    private static function moveLink(array $link, string $route, array $roleIds, array &$areas, array &$seenRoutes): bool
    {
        if (strpos($route, '(:') !== false) {
            return true;
        }

        $classification = self::classificationFor($route);
        if ($classification === null) {
            return false;
        }

        $id = (string) ($link['id'] ?? '');
        if ($id === '' || !isset($roleIds[$id])) {
            return true;
        }

        if (isset($seenRoutes[$route])) {
            return true;
        }
        $seenRoutes[$route] = true;

        [$areaName, $sectionName, $label] = $classification;
        $link['nama'] = $label ?? ($link['nama'] ?? $route);
        $link['sub'] = [];
        $areas[$areaName][$sectionName][] = $link;
        return true;
    }

    private static function classificationFor(string $route): ?array
    {
        if (isset(self::AREAS['Keuangan']['routes'][$route])) {
            [$section, $label] = self::AREAS['Keuangan']['routes'][$route];
            return ['Keuangan', $section, $label];
        }

        foreach (self::AREAS as $areaName => $definition) {
            if ($areaName === 'Keuangan') {
                continue;
            }
            if (isset($definition['routes'][$route])) {
                [$section, $label] = $definition['routes'][$route];
                return [$areaName, $section, $label];
            }
        }

        foreach ([
            ['service/', 'Service', 'Transaksi', null],
            ['service_by_garansi/', 'Service', 'Transaksi', null],
            ['riwayat_service/', 'Service', 'Laporan', null],
            ['mutasi_stok/', 'Persediaan', 'Transaksi', null],
            ['stok_opname/', 'Persediaan', 'Transaksi', null],
            ['kartu_stok/', 'Persediaan', 'Transaksi', null],
            ['retur_customer/', 'Penjualan', 'Transaksi', null],
            ['retur_suplier/', 'Pembelian', 'Transaksi', null],
            ['kas_bank/', 'Keuangan', 'Kas & Bank', null],
            ['hutangpiutang/', 'Keuangan', 'Utang/Piutang', null],
            ['laporankeuangan/', 'Keuangan', 'Laporan', null],
            ['pegawai/', 'Administrasi', 'Master/Pengaturan', null],
        ] as [$prefix, $areaName, $sectionName, $label]) {
            if (strpos($route, $prefix) === 0) {
                return [$areaName, $sectionName, $label];
            }
        }

        return null;
    }
}
