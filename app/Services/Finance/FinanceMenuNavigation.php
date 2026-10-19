<?php

namespace App\Services\Finance;

final class FinanceMenuNavigation
{
    private const FINANCE_ROUTES = [
        'dashboard/finance',
        'dashboard/keuangan',
        'kas_bank',
        'kas_masuk',
        'kas_keluar',
        'tutup_kasir',
        'kasir_bulanan',
        'hutangpiutang/dashboard',
        'hutangpiutang/hutang',
        'hutangpiutang/piutang',
        'hutangpiutang/riwayat',
        'laporan_jurnal',
        'sisi_keuangan',
        'laporankeuangan/laba_rugi',
        'omset_bulanan/arus_kas',
        'kartu_stok',
        'finance/rekonsiliasi',
    ];

    public static function group(array $categories, array $roles): array
    {
        $originalCategories = $categories;
        $existingGroupIndex = null;
        foreach ($categories as $index => $category) {
            if (strtolower(trim((string) ($category['nama'] ?? ''))) === 'keuangan') {
                $existingGroupIndex = $index;
                break;
            }
        }

        $financeItems = [];
        foreach ($categories as $categoryIndex => &$category) {
            if ($categoryIndex === $existingGroupIndex) {
                continue;
            }

            $categoryHadFinanceLinks = false;
            foreach ($category['menu'] ?? [] as $menuIndex => &$menu) {
                if (self::isFinanceRoute($menu['url'] ?? '')) {
                    $financeItems[] = $menu;
                    $categoryHadFinanceLinks = true;
                    unset($category['menu'][$menuIndex]);
                    continue;
                }

                foreach ($menu['sub'] ?? [] as $subIndex => $sub) {
                    if (!self::isFinanceRoute($sub['url'] ?? '')) {
                        continue;
                    }
                    $financeItems[] = [
                        'id' => $sub['id'],
                        'nama' => $sub['nama'],
                        'icon' => $sub['icon'] ?? null,
                        'url' => $sub['url'],
                        'role' => $sub['role'] ?? null,
                        'utama' => 1,
                        'sub' => [],
                    ];
                    $categoryHadFinanceLinks = true;
                    unset($menu['sub'][$subIndex]);
                }

                $menu['sub'] = array_values($menu['sub'] ?? []);
                if ($menu['sub'] === [] && trim((string) ($menu['url'] ?? '')) === '#') {
                    unset($category['menu'][$menuIndex]);
                }
            }
            unset($menu);
            $category['menu'] = array_values($category['menu'] ?? []);
            if ($categoryHadFinanceLinks && $category['menu'] === []) {
                unset($categories[$categoryIndex]);
            }
        }
        unset($category);

        if ($financeItems === []) {
            return array_values($categories);
        }

        $groupId = self::firstAuthorizedId($financeItems, $roles);
        if ($groupId === null) {
            return array_values($originalCategories);
        }

        usort($financeItems, static function (array $left, array $right): int {
            return self::routePriority($left['url'] ?? '') <=> self::routePriority($right['url'] ?? '');
        });

        if ($existingGroupIndex !== null) {
            $categories[$existingGroupIndex]['menu'] = array_values(array_merge(
                $categories[$existingGroupIndex]['menu'] ?? [],
                $financeItems
            ));
            return array_values($categories);
        }

        $categories[] = [
            'id' => $groupId,
            'nama' => 'Keuangan',
            'icon' => '<iconify-icon icon="solar:wallet-money-bold-duotone" class="fs-6"></iconify-icon>',
            'url' => '',
            'role' => null,
            'utama' => 1,
            'menu' => $financeItems,
        ];

        return array_values($categories);
    }

    private static function firstAuthorizedId(array $items, array $roles)
    {
        $roleIds = array_map('strval', $roles);
        foreach ($items as $item) {
            if (in_array((string) ($item['id'] ?? ''), $roleIds, true)) {
                return $item['id'];
            }
            foreach ($item['sub'] ?? [] as $sub) {
                if (in_array((string) ($sub['id'] ?? ''), $roleIds, true)) {
                    return $item['id'] ?? null;
                }
            }
        }
        return null;
    }

    private static function isFinanceRoute(string $url): bool
    {
        $route = strtolower(trim(parse_url($url, PHP_URL_PATH) ?: '', '/'));
        if (strpos($route, 'index.php/') === 0) {
            $route = substr($route, strlen('index.php/'));
        }

        return in_array($route, self::FINANCE_ROUTES, true)
            || strpos($route, 'kas_bank/') === 0
            || strpos($route, 'hutangpiutang/') === 0
            || strpos($route, 'laporankeuangan/') === 0;
    }

    private static function routePriority(string $url): int
    {
        $route = strtolower(trim(parse_url($url, PHP_URL_PATH) ?: '', '/'));
        $priorities = [
            'dashboard/finance' => 10,
            'dashboard/keuangan' => 10,
            'kas_bank' => 20,
            'hutangpiutang/dashboard' => 30,
            'kas_masuk' => 40,
            'kas_keluar' => 41,
            'laporan_jurnal' => 50,
            'sisi_keuangan' => 60,
            'laporankeuangan/laba_rugi' => 70,
            'omset_bulanan/arus_kas' => 80,
            'kartu_stok' => 90,
            'finance/rekonsiliasi' => 95,
        ];

        return $priorities[$route] ?? 99;
    }
}
