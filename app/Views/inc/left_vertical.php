<?php
if (session()->get('logged_in') !== true) {
    header("Location:" . base_url('login'));
    exit;
}

use App\Models\ModelUnit;
use App\Models\Core;

$id_unit = session()->get('ID_UNIT');
$ModelUnit = new ModelUnit();
$unitLogo  = $id_unit ? $ModelUnit->getById($id_unit) : null;
$logoFile  = ($unitLogo && $unitLogo->LOGO) ? $unitLogo->LOGO : 'logo_iclear.png';
$namaUnit  = session()->get('NAMA_UNIT') ?: '';

$MCore = new Core();
$menu_utama = $MCore->get_menu_show();
$role = $MCore->get_role();
$currentPath = trim(uri_string(), '/');
?>

<?= view('inc/_sidebar_theme') ?>

<aside class="left-sidebar with-vertical shadow-sm">
    <!-- ---------------------------------- -->
    <!-- Brand Logo Section -->
    <!-- ---------------------------------- -->
    <div class="brand-logo">
        <a href="<?= base_url() ?>" class="sb-brand-logo d-flex align-items-center text-decoration-none" aria-label="Beranda">
            <img src="<?= base_url('template/assets/images/' . $logoFile) ?>" alt="Logo Unit"
                class="dark-logo img-fluid" />
            <img src="<?= base_url('template/assets/images/' . $logoFile) ?>" alt="Logo Unit"
                class="light-logo img-fluid" />
        </a>
        <div class="sb-brand-text">
            <span class="sb-brand-name" title="<?= esc($namaUnit) ?>"><?= esc($namaUnit ?: 'iClear ERP') ?></span>
        </div>
        <a href="javascript:void(0)" class="sidebartoggler d-xl-none text-decoration-none fs-5"
            aria-label="Tutup menu">
            <i class="ti ti-x"></i>
        </a>
    </div>

    <!-- ---------------------------------- -->
    <!-- Navigation Menu Section -->
    <!-- ---------------------------------- -->
    <!-- Scroll native: SimpleBar membuat dua container scroll (wrapper + content)
         sehingga sidebar bisa discroll melewati menu terakhir. -->
    <div class="scroll-sidebar">
        <nav class="sidebar-nav" aria-label="Menu utama">
            <ul id="sidebarnav" class="mb-0">
                <?php foreach ($menu_utama as $mymenu) : ?>
                    <?php if (in_array($mymenu['id'], $role)) : ?>
                        <?php
                        // Parent yang punya url tetap bisa diklik (mis. "Kas & Bank" -> /kas_bank),
                        // cuma Expand/Collapse lewat script sidebarmenu.js yang tidak preventDefault.
                        $parentHref  = !empty($mymenu['url']) ? base_url($mymenu['url']) : '#';
                        $is_active   = !empty($mymenu['url']) && (trim($mymenu['url'], '/') === $currentPath);
                        $parentIcon  = ($mymenu['icon'] != null) ? $mymenu['icon'] : '<iconify-icon icon="solar:menu-dots-line-duotone"></iconify-icon>';
                        ?>

                        <!-- Menu Tanpa Sub-menu -->
                        <?php if (sizeof($mymenu['menu']) <= 0) : ?>
                            <?php if ($mymenu['utama'] == 0) : ?>
                                <li class="nav-small-cap">
                                    <span class="nav-small-cap-icon"></span>
                                    <span class="hide-menu"><?= $mymenu['nama'] ?></span>
                                </li>
                            <?php else: ?>
                                <li class="sidebar-item">
                                    <a class="sidebar-link <?= $is_active ? 'active' : '' ?>"
                                        href="<?= base_url() . $mymenu['url'] ?>" <?= $is_active ? 'aria-current="page"' : '' ?>>
                                        <span class="aside-icon"><?= $parentIcon ?></span>
                                        <span class="hide-menu" title="<?= esc($mymenu['nama']) ?>"><?= $mymenu['nama'] ?></span>
                                        <?php if ($mymenu['id'] == 101) : ?>
                                            <span class="sb-badge" title="Modul lama, dialihkan ke Penilaian Absensi">Deprecated</span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <!-- Menu Dengan Sub-menu -->
                        <?php else : ?>
                            <?php
                            // Buka otomatis grup yang memuat halaman aktif, tanpa menunggu JS.
                            $openFirstLevel = false;
                            foreach ($mymenu['menu'] as $menu) {
                                if (!in_array($menu['id'], $role)) {
                                    continue;
                                }
                                if (!empty($menu['url']) && (trim($menu['url'], '/') === $currentPath)) {
                                    $openFirstLevel = true;
                                    break;
                                }
                                foreach ($menu['sub'] as $sub_menu) {
                                    if (in_array($sub_menu['id'], $role) && !empty($sub_menu['url']) && (trim($sub_menu['url'], '/') === $currentPath)) {
                                        $openFirstLevel = true;
                                        break 2;
                                    }
                                }
                            }
                            ?>
                            <?php if ($mymenu['utama'] == 0) : ?>
                                <li class="nav-small-cap">
                                    <span class="nav-small-cap-icon"></span>
                                    <span class="hide-menu"><?= $mymenu['nama'] ?></span>
                                </li>
                            <?php endif; ?>

                            <li class="sidebar-item">
                                <a class="sidebar-link has-arrow <?= $is_active ? 'active' : '' ?>"
                                    href="<?= $parentHref ?>" aria-expanded="<?= $openFirstLevel ? 'true' : 'false' ?>">
                                    <span class="aside-icon"><?= $parentIcon ?></span>
                                    <span class="hide-menu" title="<?= esc($mymenu['nama']) ?>"><?= $mymenu['nama'] ?></span>
                                </a>
                                <ul aria-expanded="<?= $openFirstLevel ? 'true' : 'false' ?>"
                                    class="collapse first-level list-unstyled <?= $openFirstLevel ? 'in' : '' ?>">
                                    <?php foreach ($mymenu['menu'] as $menu) : ?>
                                        <?php if (in_array($menu['id'], $role)) : ?>
                                            <?php if (sizeof($menu['sub']) <= 0) : ?>
                                                <?php $menuActive = !empty($menu['url']) && (trim($menu['url'], '/') === $currentPath); ?>
                                                <li class="sidebar-item">
                                                    <a href="<?= base_url() . $menu['url'] ?>"
                                                        class="sidebar-link <?= $menuActive ? 'active' : '' ?>"
                                                        <?= $menuActive ? 'aria-current="page"' : '' ?>>
                                                        <span class="hide-menu" title="<?= esc($menu['nama']) ?>"><?= $menu['nama'] ?></span>
                                                    </a>
                                                </li>
                                            <?php else : ?>
                                                <?php
                                                $menuActive = !empty($menu['url']) && (trim($menu['url'], '/') === $currentPath);
                                                $openTwoLevel = false;
                                                foreach ($menu['sub'] as $sub_menu) {
                                                    if (in_array($sub_menu['id'], $role) && !empty($sub_menu['url']) && (trim($sub_menu['url'], '/') === $currentPath)) {
                                                        $openTwoLevel = true;
                                                        break;
                                                    }
                                                }
                                                ?>
                                                <li class="sidebar-item <?= ($menuActive || $openTwoLevel) ? 'selected' : '' ?>">
                                                    <a class="sidebar-link has-arrow <?= $menuActive ? 'active' : '' ?>"
                                                        href="#" aria-expanded="<?= $openTwoLevel ? 'true' : 'false' ?>">
                                                        <span class="hide-menu" title="<?= esc($menu['nama']) ?>"><?= $menu['nama'] ?></span>
                                                    </a>
                                                    <ul aria-expanded="<?= $openTwoLevel ? 'true' : 'false' ?>"
                                                        class="collapse two-level list-unstyled <?= $openTwoLevel ? 'in' : '' ?>">
                                                        <?php foreach ($menu['sub'] as $sub_menu) : ?>
                                                            <?php if (in_array($sub_menu['id'], $role)) : ?>
                                                                <?php $subActive = !empty($sub_menu['url']) && (trim($sub_menu['url'], '/') === $currentPath); ?>
                                                                <li class="sidebar-item">
                                                                    <a href="<?= base_url() . $sub_menu['url'] ?>"
                                                                        class="sidebar-link <?= $subActive ? 'active' : '' ?>"
                                                                        <?= $subActive ? 'aria-current="page"' : '' ?>>
                                                                        <span class="hide-menu" title="<?= esc($sub_menu['nama']) ?>"><?= $sub_menu['nama'] ?></span>
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
    <div class="sidebar-footer">
        <div class="sb-user">
            <img src="<?= base_url('template/assets/images/profile/user-1.jpg') ?>" width="34" height="34"
                class="sb-avatar" alt="Foto <?= esc(session('NAMA')) ?>" />
            <div class="sb-user-meta">
                <p class="sb-user-name"><?= esc(session('NAMA')) ?></p>
                <p class="sb-user-role"><?= esc(session('NAMA_JABATAN')) ?></p>
            </div>
            <a href="<?= base_url('Logout') ?>" class="sb-logout" data-bs-toggle="tooltip"
                data-bs-placement="top" title="Keluar / Logout" aria-label="Keluar / Logout">
                <iconify-icon icon="solar:logout-line-duotone" class="fs-5"></iconify-icon>
            </a>
        </div>
    </div>
</aside>
