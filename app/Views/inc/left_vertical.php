<?php
if (session()->get('logged_in') !== true) {
    header("Location:" . base_url('login'));
    exit;
}

use App\Models\ModelUnit;
use App\Models\Core;
use App\Services\Sidebar\SidebarNavigation;

$id_unit = session()->get('ID_UNIT');
$ModelUnit = new ModelUnit();
$unitLogo  = $id_unit ? $ModelUnit->getById($id_unit) : null;
$logoFile  = ($unitLogo && $unitLogo->LOGO) ? $unitLogo->LOGO : 'logo_iclear.png';
$namaUnit  = session()->get('NAMA_UNIT') ?: '';

$MCore = new Core();
$role = $MCore->get_role();
$menu_utama = SidebarNavigation::organize($MCore->get_menu_show(), $role);
$currentUri = service('uri');
$currentPath = $currentUri->getPath();
$activeRoute = '';
$activeRouteLength = 0;
$findActiveRoute = static function (array $nodes) use (&$findActiveRoute, &$activeRoute, &$activeRouteLength, $role, $currentPath): void {
    foreach ($nodes as $node) {
        if (in_array($node['id'] ?? null, $role)) {
            $route = SidebarNavigation::normalizeRoute((string) ($node['url'] ?? ''));
            if ($route !== '' && $route !== '#' && strlen($route) > $activeRouteLength && SidebarNavigation::isActive($route, $currentPath)) {
                $activeRoute = $route;
                $activeRouteLength = strlen($route);
            }
        }
        $findActiveRoute(array_merge($node['menu'] ?? [], $node['sub'] ?? []));
    }
};
$findActiveRoute($menu_utama);
$nodeIsActive = static function (array $node) use (&$nodeIsActive, $activeRoute): bool {
    if ($activeRoute !== '' && SidebarNavigation::normalizeRoute((string) ($node['url'] ?? '')) === $activeRoute) {
        return true;
    }
    foreach (array_merge($node['menu'] ?? [], $node['sub'] ?? []) as $child) {
        if ($nodeIsActive($child)) {
            return true;
        }
    }
    return false;
};
?>

<aside class="left-sidebar with-vertical shadow-sm">
    <style>
        .left-sidebar .sidebar-link { min-width: 0; }
        .left-sidebar .hide-menu { white-space: normal; overflow-wrap: anywhere; line-height: 1.3; }
        .left-sidebar .sidebar-nav .first-level,
        .left-sidebar .sidebar-nav .two-level { padding-right: .25rem; }
        .left-sidebar .scroll-sidebar { min-height: 0; overflow-x: hidden; }
    </style>
    <!-- ---------------------------------- -->
    <!-- Brand Logo Section -->
    <!-- ---------------------------------- -->
    <div class="brand-logo d-flex flex-column justify-content-center align-items-center py-4 px-3 border-bottom border-light">
        <a href="<?= base_url() ?>" class="text-nowrap logo-img mb-2 d-flex align-items-center justify-content-center">
            <img src="<?= base_url('template/assets/images/' . $logoFile) ?>" alt="Logo Unit"
                class="dark-logo img-fluid" style="max-height: 45px; width: auto;" />
            <img src="<?= base_url('template/assets/images/' . $logoFile) ?>" alt="Logo Unit"
                class="light-logo img-fluid" style="max-height: 45px; width: auto;" />
        </a>
        <?php if (!empty($namaUnit)): ?>
            <span class="badge bg-primary-subtle text-primary px-3 py-1 rounded-pill fw-semibold small mt-1 text-truncate" style="max-width: 100%;">
                <?= esc($namaUnit) ?>
            </span>
        <?php endif; ?>
        <a href="javascript:void(0)" class="sidebartoggler ms-auto text-decoration-none fs-5 d-block d-xl-none position-absolute top-0 end-0 p-3 text-muted">
            <i class="ti ti-x"></i>
        </a>
    </div>

    <!-- ---------------------------------- -->
    <!-- Navigation Menu Section -->
    <!-- ---------------------------------- -->
    <div class="scroll-sidebar px-2 py-3" data-simplebar>
        <nav class="sidebar-nav">
            <ul id="sidebarnav" class="mb-0">
                <?php foreach ($menu_utama as $mymenu) : ?>
                    <?php if (in_array($mymenu['id'], $role)) : ?>
                        <?php
                        // Parent yang punya url tetap bisa diklik (mis. "Kas & Bank" -> /kas_bank),
                        // cuma Expand/Collapse lewat script sidebarmenu.js yang tidak preventDefault.
                        $parentHref  = !empty($mymenu['url']) ? base_url(ltrim((string) $mymenu['url'], '/')) : '#';
                        $is_active   = $nodeIsActive($mymenu);
                        ?>

                        <!-- Menu Tanpa Sub-menu -->
                        <?php if (sizeof($mymenu['menu']) <= 0) : ?>
                            <?php if ($mymenu['utama'] == 0) : ?>
                                <li class="nav-small-cap text-uppercase text-muted fw-bold fs-xs px-3 mt-4 mb-2">
                                    <iconify-icon icon="solar:menu-dots-bold-duotone" class="nav-small-cap-icon fs-5 align-middle me-1"></iconify-icon>
                                    <span class="hide-menu"><?= $mymenu['nama'] ?></span>
                                </li>
                            <?php else: ?>
                                <li class="sidebar-item mb-1">
                                    <a class="sidebar-link <?= $is_active ? 'active bg-primary text-white shadow-sm' : 'primary-hover-bg text-dark' ?> rounded-3 px-3 py-2 d-flex align-items-center text-decoration-none transition-all"
                                        href="<?= esc(base_url(ltrim((string) ($mymenu['url'] ?? ''), '/'))) ?>" aria-expanded="<?= $is_active ? 'true' : 'false' ?>" <?= $activeRoute !== '' && SidebarNavigation::normalizeRoute((string) ($mymenu['url'] ?? '')) === $activeRoute ? 'aria-current="page"' : '' ?>>
                                        <span class="aside-icon p-2 <?= $is_active ? 'bg-white bg-opacity-25 text-white' : 'bg-primary-subtle text-primary' ?> rounded-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                            <?= $mymenu['icon'] ?>
                                        </span>
                                        <span class="hide-menu ps-3 fw-medium flex-grow-1"><?= $mymenu['nama'] ?></span>
                                        <?php if ($mymenu['id'] == 101) : ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-auto small px-2 py-0.5"
                                                title="Modul lama, dialihkan ke Penilaian Absensi">Deprecated</span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <!-- Menu Dengan Sub-menu -->
                        <?php else : ?>
                            <?php if ($mymenu['utama'] == 0) : ?>
                                <li class="nav-small-cap text-uppercase text-muted fw-bold fs-xs px-3 mt-4 mb-2">
                                    <iconify-icon icon="solar:menu-dots-bold-duotone" class="nav-small-cap-icon fs-5 align-middle me-1"></iconify-icon>
                                    <span class="hide-menu"><?= $mymenu['nama'] ?></span>
                                </li>
                            <?php endif; ?>

                            <li class="sidebar-item mb-1">
                                    <a class="sidebar-link has-arrow success-hover-bg <?= $is_active ? 'active' : 'text-dark' ?> rounded-3 px-3 py-2 d-flex align-items-center text-decoration-none"
                                    href="<?= esc($parentHref) ?>" aria-expanded="<?= $is_active ? 'true' : 'false' ?>">
                                    <span class="aside-icon p-2 bg-success-subtle text-success rounded-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                        <?= ($mymenu['icon'] != null) ? $mymenu['icon'] : '<iconify-icon icon="solar:smart-speaker-minimalistic-line-duotone" class="fs-6"></iconify-icon>'; ?>
                                    </span>
                                    <span class="hide-menu ps-3 fw-medium flex-grow-1"><?= $mymenu['nama'] ?></span>
                                </a>
                                <ul aria-expanded="<?= $is_active ? 'true' : 'false' ?>" class="collapse first-level list-unstyled ps-3 pt-1 <?= $is_active ? 'show' : '' ?>">
                                    <?php foreach ($mymenu['menu'] as $menu) : ?>
                                        <?php if (in_array($menu['id'], $role)) : ?>
                                            <?php $menuActive = $nodeIsActive($menu); ?>
                                            <?php if (sizeof($menu['sub']) <= 0) : ?>
                                                <li class="sidebar-item my-1">
                                                    <a href="<?= esc(base_url(ltrim((string) $menu['url'], '/'))) ?>" class="sidebar-link <?= $menuActive ? 'active bg-primary text-white' : 'text-dark' ?> fw-medium text-decoration-none py-2 px-3 rounded-2 d-block hover-bg-light" <?= $menuActive ? 'aria-current="page"' : '' ?>>
                                                        <span class="hide-menu"><?= esc($menu['nama']) ?></span>
                                                        <?php if ((int) $menu['id'] === \App\Services\Service\ServiceWorkflowAccess::CASHIER_MENU): ?><span class="badge text-bg-danger ms-2" id="cashier-queue-count" aria-label="Jumlah service dalam antrean">0</span><?php endif ?>
                                                    </a>
                                                </li>
                                            <?php else : ?>
                                                <li class="sidebar-item my-1">
                                                    <a class="sidebar-link has-arrow <?= $menuActive ? 'active text-primary' : 'text-dark' ?> fw-medium text-decoration-none py-2 px-3 rounded-2 d-flex justify-content-between align-items-center" href="#" aria-expanded="<?= $menuActive ? 'true' : 'false' ?>">
                                                        <span class="hide-menu"><?= $menu['nama'] ?></span>
                                                    </a>
                                                    <ul aria-expanded="<?= $menuActive ? 'true' : 'false' ?>" class="collapse two-level list-unstyled ps-3 pt-1 <?= $menuActive ? 'show' : '' ?>">
                                                        <?php foreach ($menu['sub'] as $sub_menu) : ?>
                                                            <?php if (in_array($sub_menu['id'], $role)) : ?>
                                                                <?php $subActive = $nodeIsActive($sub_menu); ?>
                                                                <li class="sidebar-item my-1">
                                                                    <a href="<?= base_url() . $sub_menu['url'] ?>" class="sidebar-link <?= $subActive ? 'active bg-primary text-white' : 'text-dark' ?> fw-medium text-decoration-none py-1 px-3 rounded-2 d-block" <?= $subActive ? 'aria-current="page"' : '' ?>>
                                                                        <span class="hide-menu"><?= esc($sub_menu['nama']) ?></span>
                                                                    </a>
                                                                </li>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </li>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endif; ?>

                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>

    <!-- ---------------------------------- -->
    <!-- User Profile & Logout Footer -->
    <!-- ---------------------------------- -->
    <div class="sidebar-footer px-3 pb-3 pt-2 mt-auto border-top border-light">
        <div class="card bg-light border-0 mb-0 rounded-4 shadow-none">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                        <img src="<?= base_url('template/assets/images/profile/user-1.jpg') ?>" width="40"
                            height="40" class="img-fluid rounded-circle object-fit-cover flex-shrink-0" alt="User Profile" />
                        <div class="overflow-hidden">
                            <h6 class="mb-0 text-truncate fw-bold text-dark fs-sm"><?= esc(session('NAMA')) ?></h6>
                            <p class="mb-0 text-truncate text-muted fs-xs"><?= esc(session('NAMA_JABATAN')) ?></p>
                        </div>
                    </div>
                    <a href="<?= base_url('Logout') ?>" class="btn btn-danger-subtle text-danger p-2 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                        style="width: 36px; height: 36px;" data-bs-toggle="tooltip" data-bs-placement="top" title="Keluar / Logout">
                        <iconify-icon icon="solar:logout-line-duotone" class="fs-5"></iconify-icon>
                    </a>
                </div>
            </div>
        </div>
    </div>
</aside>
<?php if (in_array(\App\Services\Service\ServiceWorkflowAccess::CASHIER_MENU, array_map('intval', $role), true)): ?>
<script>
(function () {
    const badge = document.getElementById('cashier-queue-count');
    if (!badge) return;
    const refresh = async function () {
        try {
            const response = await fetch('<?= base_url('service/workflow/badge') ?>', {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            if (!response.ok) return;
            const data = await response.json();
            badge.textContent = data.cashier > 99 ? '99+' : String(data.cashier || 0);
            badge.classList.toggle('d-none', !data.cashier);
        } catch (_) {}
    };
    refresh();
    window.setInterval(refresh, 30000);
})();
</script>
<?php endif ?>
