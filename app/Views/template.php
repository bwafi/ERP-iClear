<!DOCTYPE html>
<html lang="en" dir="ltr" data-bs-theme="light" data-color-theme="Blue_Theme" data-layout="vertical"
    data-boxed-layout="full" data-card="shadow">

<head>
    <!-- Required meta tags -->
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <!-- Favicon icon-->
    <link rel="shortcut icon" type="image/png"
        href="<?php echo base_url('template/') ?><?= env('app.logo', 'assets/images/logo.png') ?>" />

    <script>
    (function() {
        var key = 'app-theme',
            saved = null;
        try { saved = localStorage.getItem(key); } catch (e) {}
        var theme = (saved === 'dark' || saved === 'light') ? saved :
            (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        window.__appTheme = theme;
        document.documentElement.setAttribute('data-bs-theme', theme);
    })();
    </script>

    <!-- Core Css -->
    <link rel="stylesheet" href="<?php echo base_url('template/') ?>assets/css/styles.css" />
    <link rel="stylesheet" href="<?php echo base_url('template/assets/libs/select2/dist/css/select2.min.css') ?>">



    <title><?= env('app.name', 'App ERP') ?></title>

    <!-- jvectormap  -->
    <link rel="stylesheet" href="<?php echo base_url('template/') ?>assets/libs/jvectormap/jquery-jvectormap.css">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- DataTables CSS -->

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">





</head>

<body>
    <!-- Preloader -->
    <div class="preloader">
        <img src="<?php echo base_url('template/') ?>assets/images/logos/loader.svg" alt="loader"
            class="lds-ripple img-fluid" />
    </div>
    <div id="main-wrapper">
        <!-- Sidebar Start -->
        <?php echo view('inc/left_vertical'); ?>
        <!--  Sidebar End -->
        <div class="page-wrapper">

            <?php echo view('inc/left_horizontal'); ?>

            <div class="body-wrapper">
                <div class="container-fluid mw-100">
                    <!--  Header Start -->
                    <!-- Header Start -->
                    <header class="topbar sticky-top">
                        <div class="with-vertical">
                            <?php
                            $id_unit = session()->get('ID_UNIT');

                            use App\Models\ModelUnit;

                            $ModelUnit = new ModelUnit();
                            $unitLogo = $ModelUnit->getById($id_unit);

                            $show_service = isset($akun_service) && $akun_service->apakah_service === 'service_oke';
                            $notif_stok = isset($stokMinimum) ? count($stokMinimum) : 0;
                            $notif_proses = $show_service && isset($proses_service) ? count($proses_service) : 0;
                            $notif_siap = $show_service && isset($bisa_diambil) ? count($bisa_diambil) : 0;
                            $notif_expired = $show_service && isset($expired_service) ? count($expired_service) : 0;
                            $notif_total = $notif_stok + $notif_proses + $notif_siap + $notif_expired;
                            $notif_badge = $notif_total > 99 ? '99+' : $notif_total;
                            ?>
                            <nav class="navbar navbar-expand-lg p-0">
                                <!-- Sidebar Toggler -->
                                <ul class="navbar-nav">
                                    <li class="nav-item">
                                        <a class="nav-link sidebartoggler nav-icon-hover" id="headerCollapse" href="javascript:void(0)">
                                            <div class="nav-icon-hover-bg rounded-circle">
                                                <iconify-icon icon="solar:list-bold-duotone" class="fs-7 text-dark"></iconify-icon>
                                            </div>
                                        </a>
                                    </li>
                                </ul>

                                <!-- Mobile Logo -->
                                <div class="d-block d-lg-none">
                                    <img src="<?= base_url('template/assets/images/' . $unitLogo->LOGO) ?>" class="dark-logo" alt="Logo-Dark" style="width: 30px; height: auto;" />
                                    <img src="<?= base_url('template/assets/images/' . $unitLogo->LOGO) ?>" class="light-logo" alt="Logo-light" style="width: 30px; height: auto;" />
                                </div>

                                <!-- Topbar Search Bar -->
                                <div class="d-none d-md-block position-relative me-3 w-100" style="max-width: 300px;" id="topbar-search-form">
                                    <form role="search" autocomplete="off" onsubmit="return false;">
                                        <input type="text" id="topbar-search" class="form-control rounded-3 py-2 ps-5 text-dark" placeholder="Cari menu... (Tekan '/')">
                                        <iconify-icon icon="solar:magnifer-linear" class="text-muted position-absolute top-50 start-0 translate-middle-y ms-3"></iconify-icon>
                                    </form>
                                    <div class="topbar-search-results d-none position-absolute w-100 bg-white shadow-sm rounded-2 mt-1 border overflow-hidden" id="topbar-search-results" style="z-index: 1050; max-height: 350px; overflow-y: auto;"></div>
                                </div>

                                <!-- Right Navbar Items -->
                                <ul class="navbar-nav flex-row ms-auto align-items-center justify-content-center">

                                    <!-- Dark / Light Toggle -->
                                    <li class="nav-item d-flex align-items-center">
                                        <a class="nav-link nav-icon-hover moon dark-layout" href="javascript:void(0)">
                                            <iconify-icon icon="solar:moon-line-duotone" class="moon fs-7"></iconify-icon>
                                        </a>
                                        <a class="nav-link nav-icon-hover sun light-layout" href="javascript:void(0)">
                                            <iconify-icon icon="solar:sun-2-line-duotone" class="sun fs-7"></iconify-icon>
                                        </a>
                                    </li>

                                    <!-- Notifications Dropdown -->
                                    <li class="nav-item dropdown">
                                        <a class="nav-link position-relative nav-icon-hover" href="javascript:void(0)" id="dropNotif" data-bs-toggle="dropdown" aria-expanded="false">
                                            <div class="nav-icon-hover-bg rounded-circle">
                                                <iconify-icon icon="solar:bell-bing-line-duotone" class="fs-7 text-dark"></iconify-icon>
                                                <?php if ($notif_total > 0): ?>
                                                    <span class="notif-count"><?= $notif_badge ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </a>

                                        <div class="dropdown-menu content-dd dropdown-menu-end dropdown-menu-animate-up" aria-labelledby="dropNotif">
                                            <div class="d-flex align-items-center justify-content-between py-3 px-7">
                                                <h3 class="mb-0 fs-5">Notifikasi</h3>
                                                <span class="badge bg-info ms-3"><?= $notif_badge ?> baru</span>
                                            </div>

                                            <div class="message-body p-0" data-simplebar>
                                                <?php if ($notif_total > 0): ?>

                                                    <!-- Stok Minimum -->
                                                    <?php if ($notif_stok > 0): ?>
                                                        <div class="px-7 pt-3 pb-1 d-flex align-items-center justify-content-between border-top">
                                                            <h5 class="mb-0 fs-4 fw-semibold">Stok Minimum</h5>
                                                            <span class="badge bg-danger-subtle text-danger"><?= $notif_stok ?></span>
                                                        </div>
                                                        <?php foreach (array_slice($stokMinimum, 0, 3) as $item): ?>
                                                            <a href="<?= base_url('stok_minimum') ?>" class="dropdown-item px-7 d-flex align-items-center py-6">
                                                                <span class="flex-shrink-0 nav-icon-hover-bg rounded-circle p-2 d-flex align-items-center justify-content-center">
                                                                    <iconify-icon icon="solar:box-minimalistic-linear" class="fs-6 text-danger"></iconify-icon>
                                                                </span>
                                                                <div class="w-100 d-inline-block v-middle ps-3">
                                                                    <h5 class="mb-0 fs-3 fw-normal"><?= esc($item->nama_barang) ?></h5>
                                                                    <span class="fs-2 text-nowrap d-block fw-normal mt-1 text-muted">Unit: <?= esc($item->nama_unit) ?></span>
                                                                    <span class="fs-2 text-nowrap d-block fw-normal mt-1 text-danger">Sisa <?= esc($item->stok_akhir) ?> (Min: <?= esc($item->stok_minimum) ?>)</span>
                                                                </div>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>

                                                    <!-- Proses Service -->
                                                    <?php if ($show_service && $notif_proses > 0): ?>
                                                        <div class="px-7 pt-3 pb-1 d-flex align-items-center justify-content-between border-top">
                                                            <h5 class="mb-0 fs-4 fw-semibold">Proses Service</h5>
                                                            <span class="badge bg-info-subtle text-info"><?= $notif_proses ?></span>
                                                        </div>
                                                        <?php foreach (array_slice($proses_service, 0, 3) as $item): ?>
                                                            <a href="<?= base_url('proses_service') ?>" class="dropdown-item px-7 d-flex align-items-center py-6">
                                                                <span class="flex-shrink-0 nav-icon-hover-bg rounded-circle p-2 d-flex align-items-center justify-content-center">
                                                                    <iconify-icon icon="solar:clipboard-add-linear" class="fs-6 text-primary"></iconify-icon>
                                                                </span>
                                                                <div class="w-100 d-inline-block v-middle ps-3">
                                                                    <h5 class="mb-0 fs-3 fw-normal"><?= esc($item->nama_pelanggan ?? '-') ?></h5>
                                                                    <span class="fs-2 d-block fw-normal mt-1 text-muted">No. Service: <?= esc($item->no_service ?? '-') ?></span>
                                                                    <span class="fs-2 d-block fw-normal mt-1 text-primary">Status: <?= esc($item->status ?? 'Proses') ?></span>
                                                                </div>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>

                                                    <!-- Siap Diambil -->
                                                    <?php if ($show_service && $notif_siap > 0): ?>
                                                        <div class="px-7 pt-3 pb-1 d-flex align-items-center justify-content-between border-top">
                                                            <h5 class="mb-0 fs-4 fw-semibold">Siap Diambil</h5>
                                                            <span class="badge bg-success-subtle text-success"><?= $notif_siap ?></span>
                                                        </div>
                                                        <?php foreach (array_slice($bisa_diambil, 0, 3) as $item): ?>
                                                            <a href="<?= base_url('bisa_diambil') ?>" class="dropdown-item px-7 d-flex align-items-center py-6">
                                                                <span class="flex-shrink-0 nav-icon-hover-bg rounded-circle p-2 d-flex align-items-center justify-content-center">
                                                                    <iconify-icon icon="solar:clipboard-check-linear" class="fs-6 text-success"></iconify-icon>
                                                                </span>
                                                                <div class="w-100 d-inline-block v-middle ps-3">
                                                                    <h5 class="mb-0 fs-3 fw-normal"><?= esc($item->nama_pelanggan ?? '-') ?></h5>
                                                                    <span class="fs-2 d-block fw-normal mt-1 text-muted">No. Service: <?= esc($item->no_service ?? '-') ?></span>
                                                                    <span class="fs-2 d-block fw-normal mt-1 text-success">Siap Diambil</span>
                                                                </div>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>

                                                    <!-- Expired Service -->
                                                    <?php if ($show_service && $notif_expired > 0): ?>
                                                        <div class="px-7 pt-3 pb-1 d-flex align-items-center justify-content-between border-top">
                                                            <h5 class="mb-0 fs-4 fw-semibold">Expired Service</h5>
                                                            <span class="badge bg-warning-subtle text-warning"><?= $notif_expired ?></span>
                                                        </div>
                                                        <?php foreach (array_slice($expired_service, 0, 3) as $item): ?>
                                                            <a href="<?= base_url('expired_service') ?>" class="dropdown-item px-7 d-flex align-items-center py-6">
                                                                <span class="flex-shrink-0 nav-icon-hover-bg rounded-circle p-2 d-flex align-items-center justify-content-center">
                                                                    <iconify-icon icon="solar:clipboard-remove-linear" class="fs-6 text-warning"></iconify-icon>
                                                                </span>
                                                                <div class="w-100 d-inline-block v-middle ps-3">
                                                                    <h5 class="mb-0 fs-3 fw-normal"><?= esc($item->nama_pelanggan ?? '-') ?></h5>
                                                                    <span class="fs-2 d-block fw-normal mt-1 text-muted">No. Service: <?= esc($item->no_service ?? '-') ?></span>
                                                                    <span class="fs-2 d-block fw-normal mt-1 text-danger">Status: Expired</span>
                                                                </div>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>

                                                <?php else : ?>
                                                    <div class="px-7 py-6 text-muted text-center">Tidak ada notifikasi</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </li>

                                    <!-- Manualbook -->
                                    <li class="nav-item">
                                        <a class="nav-link position-relative nav-icon-hover" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#Modal-Manualbook">
                                            <div class="nav-icon-hover-bg rounded-circle">
                                                <iconify-icon icon="solar:book-2-line-duotone" class="fs-7 text-dark"></iconify-icon>
                                            </div>
                                        </a>
                                    </li>

                                    <!-- Profile Dropdown -->
                                    <li class="nav-item dropdown">
                                        <a class="nav-link position-relative ms-6" href="javascript:void(0)" id="drop1" data-bs-toggle="dropdown" aria-expanded="false">
                                            <div class="d-flex align-items-center flex-shrink-0">
                                                <div class="user-profile me-sm-3 me-2">
                                                    <img src="<?= base_url('template/') ?>assets/images/profile/user-1.jpg" width="45" class="rounded-circle" alt="">
                                                </div>
                                                <span class="d-sm-none d-block">
                                                    <iconify-icon icon="solar:alt-arrow-down-line-duotone"></iconify-icon>
                                                </span>
                                                <div class="d-none d-sm-block">
                                                    <h6 class="fw-bold fs-4 mb-1 profile-name"><?= session('NAMA') ?></h6>
                                                    <p class="fs-3 lh-base mb-0 profile-subtext"><?= session('NAMA_JABATAN') ?></p>
                                                </div>
                                            </div>
                                        </a>

                                        <div class="dropdown-menu content-dd dropdown-menu-end dropdown-menu-animate-up" aria-labelledby="drop1" data-bs-auto-close="outside">
                                            <div class="profile-dropdown position-relative" data-simplebar>
                                                <div class="d-flex align-items-center justify-content-between pt-3 px-7">
                                                    <h3 class="mb-0 fs-5">User Profile</h3>
                                                </div>

                                                <div class="d-flex align-items-center mx-7 py-9 border-bottom">
                                                    <img src="<?= base_url('template/') ?>assets/images/profile/user-1.jpg" alt="user" width="90" class="rounded-circle" />
                                                    <div class="ms-4">
                                                        <h4 class="mb-0 fs-5 fw-normal"><?= session('NAMA') ?></h4>
                                                        <span class="text-muted"><?= session('NAMA_JABATAN') ?></span>
                                                        <p class="text-muted mb-0 mt-1 d-flex align-items-center">
                                                            <iconify-icon icon="solar:mailbox-line-duotone" class="fs-4 me-1"></iconify-icon>
                                                            <?= session('EMAIL') ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="message-body">
                                                    <!-- Ganti Password Toggle -->
                                                    <div class="px-7 pt-4">
                                                        <button type="button" class="dropdown-item px-0 d-flex align-items-center border-0 bg-transparent" onclick="document.getElementById('password-form').classList.toggle('d-none')">
                                                            <span class="btn px-3 py-2 bg-info-subtle rounded-1 text-info shadow-none">
                                                                <iconify-icon icon="solar:wallet-2-line-duotone" class="fs-7"></iconify-icon>
                                                            </span>
                                                            <div class="w-75 d-inline-block v-middle ps-3 ms-1 text-start">
                                                                <h5 class="mb-0 mt-1 fs-4 fw-normal">Ganti Password</h5>
                                                                <span class="fs-3 text-nowrap d-block fw-normal mt-1 text-muted">Account Settings</span>
                                                            </div>
                                                        </button>

                                                        <!-- Hidden Password Form -->
                                                        <div id="password-form" class="d-none mt-3">
                                                            <form method="post" action="<?= base_url('auth/changePassword') ?>">
                                                                <?= csrf_field() ?>
                                                                <div class="mb-2">
                                                                    <input type="password" name="new_password" class="form-control form-control-sm" placeholder="Password Baru" required>
                                                                </div>
                                                                <div class="mb-2">
                                                                    <input type="password" name="confirm_password" class="form-control form-control-sm" placeholder="Konfirmasi Password" required>
                                                                </div>
                                                                <button type="submit" class="btn btn-sm btn-primary w-100">Simpan</button>
                                                            </form>
                                                        </div>
                                                    </div>

                                                    <!-- Logout -->
                                                    <div class="py-6 px-7 mb-1">
                                                        <a href="<?= base_url('Logout') ?>" class="btn btn-primary w-100">Log Out</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </header>
                    <!-- Header End -->

                    <!-- Scripts Component -->
                    <script>
                        // Prevent dropdown from closing on internal interactions
                        document.querySelectorAll('.dropdown-menu input, .dropdown-menu form, .dropdown-menu button, .dropdown-menu label').forEach(el => {
                            el.addEventListener('click', function(e) {
                                e.stopPropagation();
                            });
                        });

                        // Menu Search Functionality
                        (function() {
                            const input = document.getElementById('topbar-search');
                            if (!input) return;
                            const box = document.getElementById('topbar-search-results');
                            let items = [];

                            document.querySelectorAll('#sidebarnav a[href]').forEach(function(a) {
                                const href = a.getAttribute('href');
                                if (!href || href === '#' || href === 'javascript:void(0)') return;
                                const label = (a.textContent || '').trim().replace(/\s+/g, ' ');
                                if (!label) return;
                                items.push({
                                    label: label,
                                    href: href
                                });
                            });

                            function buildRow(item) {
                                const a = document.createElement('a');
                                a.className = 'dropdown-item px-6 d-flex align-items-center py-2 text-dark';
                                a.href = item.href;
                                const arrow = document.createElement('span');
                                arrow.className = 'me-2 text-muted';
                                arrow.textContent = '\u203A';
                                const span = document.createElement('span');
                                span.textContent = item.label;
                                a.appendChild(arrow);
                                a.appendChild(span);
                                return a;
                            }

                            function render(q) {
                                q = q.trim().toLowerCase();
                                box.innerHTML = '';
                                if (!q) {
                                    box.classList.add('d-none');
                                    return;
                                }
                                const hits = items.filter(function(i) {
                                    return i.label.toLowerCase().includes(q);
                                }).slice(0, 12);

                                if (!hits.length) {
                                    const div = document.createElement('div');
                                    div.className = 'px-6 py-3 text-muted text-center';
                                    div.textContent = 'Tidak ada menu ditemukan';
                                    box.appendChild(div);
                                } else {
                                    hits.forEach(function(i) {
                                        box.appendChild(buildRow(i));
                                    });
                                }
                                box.classList.remove('d-none');
                            }

                            input.addEventListener('input', function() {
                                render(input.value);
                            });
                            input.addEventListener('keydown', function(e) {
                                if (e.key === 'Escape') {
                                    box.classList.add('d-none');
                                    input.blur();
                                }
                            });

                            document.addEventListener('click', function(e) {
                                if (!e.target.closest('#topbar-search-form')) {
                                    box.classList.add('d-none');
                                }
                            });

                            document.addEventListener('keydown', function(e) {
                                const tag = (document.activeElement || {}).tagName;
                                if (e.key === '/' && tag !== 'INPUT' && tag !== 'TEXTAREA') {
                                    e.preventDefault();
                                    input.focus();
                                }
                            });
                        })();
                    </script>


                    <?= view($body); ?>

                </div>
            </div>
            <script>
                function handleColorTheme(e) {
                    $("html").attr("data-color-theme", e);
                    $(e).prop("checked", !0);
                }
            </script>
            <button
                class="btn btn-primary p-3 rounded-circle d-flex align-items-center justify-content-center customizer-btn"
                type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasExample"
                aria-controls="offcanvasExample">
                <i class="icon ti ti-settings fs-7"></i>
            </button>

            <div class="offcanvas customizer offcanvas-end" tabindex="-1" id="offcanvasExample"
                aria-labelledby="offcanvasExampleLabel">
                <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
                    <h4 class="offcanvas-title fw-semibold" id="offcanvasExampleLabel">
                        Settings
                    </h4>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body h-n80" data-simplebar>
                    <h6 class="fw-semibold fs-4 mb-2">Theme</h6>

                    <div class="d-flex flex-row gap-3 customizer-box" role="group">
                        <input type="radio" class="btn-check light-layout" name="theme-layout" id="light-layout"
                            autocomplete="off" />
                        <label class="btn p-9 btn-outline-primary" for="light-layout"><i
                                class="icon ti ti-brightness-up fs-7 me-2"></i>Light</label>

                        <input type="radio" class="btn-check dark-layout" name="theme-layout" id="dark-layout"
                            autocomplete="off" />
                        <label class="btn p-9 btn-outline-primary" for="dark-layout"><i
                                class="icon ti ti-moon fs-7 me-2"></i>Dark</label>
                    </div>

                    <h6 class="mt-5 fw-semibold fs-4 mb-2">Theme Direction</h6>
                    <div class="d-flex flex-row gap-3 customizer-box" role="group">
                        <input type="radio" class="btn-check" name="direction-l" id="ltr-layout" autocomplete="off" />
                        <label class="btn p-9 btn-outline-primary" for="ltr-layout"><i
                                class="icon ti ti-text-direction-ltr fs-7 me-2"></i>LTR</label>

                        <input type="radio" class="btn-check" name="direction-l" id="rtl-layout" autocomplete="off" />
                        <label class="btn p-9 btn-outline-primary" for="rtl-layout"><i
                                class="icon ti ti-text-direction-rtl fs-7 me-2"></i>RTL</label>
                    </div>

                    <h6 class="mt-5 fw-semibold fs-4 mb-2">Theme Colors</h6>

                    <div class="d-flex flex-row flex-wrap gap-3 customizer-box color-pallete" role="group">
                        <input type="radio" class="btn-check" name="color-theme-layout" id="Blue_Theme"
                            autocomplete="off" />
                        <label class="btn p-9 btn-outline-primary d-flex align-items-center justify-content-center"
                            onclick="handleColorTheme('Blue_Theme')" for="Blue_Theme" data-bs-toggle="tooltip"
                            data-bs-placement="top" data-bs-title="BLUE_THEME">
                            <div
                                class="color-box rounded-circle d-flex align-items-center justify-content-center skin-1">
                                <i class="ti ti-check text-white d-flex icon fs-5"></i>
                            </div>
                        </label>

                        <input type="radio" class="btn-check" name="color-theme-layout" id="Aqua_Theme"
                            autocomplete="off" />
                        <label class="btn p-9 btn-outline-primary d-flex align-items-center justify-content-center"
                            onclick="handleColorTheme('Aqua_Theme')" for="Aqua_Theme" data-bs-toggle="tooltip"
                            data-bs-placement="top" data-bs-title="AQUA_THEME">
                            <div
                                class="color-box rounded-circle d-flex align-items-center justify-content-center skin-2">
                                <i class="ti ti-check text-white d-flex icon fs-5"></i>
                            </div>
                        </label>

                        <input type="radio" class="btn-check" name="color-theme-layout" id="Purple_Theme"
                            autocomplete="off" />
                        <label class="btn p-9 btn-outline-primary d-flex align-items-center justify-content-center"
                            onclick="handleColorTheme('Purple_Theme')" for="Purple_Theme" data-bs-toggle="tooltip"
                            data-bs-placement="top" data-bs-title="PURPLE_THEME">
                            <div
                                class="color-box rounded-circle d-flex align-items-center justify-content-center skin-3">
                                <i class="ti ti-check text-white d-flex icon fs-5"></i>
                            </div>
                        </label>

                        <input type="radio" class="btn-check" name="color-theme-layout" id="green-theme-layout"
                            autocomplete="off" />
                        <label class="btn p-9 btn-outline-primary d-flex align-items-center justify-content-center"
                            onclick="handleColorTheme('Green_Theme')" for="green-theme-layout" data-bs-toggle="tooltip"
                            data-bs-placement="top" data-bs-title="GREEN_THEME">
                            <div
                                class="color-box rounded-circle d-flex align-items-center justify-content-center skin-4">
                                <i class="ti ti-check text-white d-flex icon fs-5"></i>
                            </div>
                        </label>

                        <input type="radio" class="btn-check" name="color-theme-layout" id="cyan-theme-layout"
                            autocomplete="off" />
                        <label class="btn p-9 btn-outline-primary d-flex align-items-center justify-content-center"
                            onclick="handleColorTheme('Cyan_Theme')" for="cyan-theme-layout" data-bs-toggle="tooltip"
                            data-bs-placement="top" data-bs-title="CYAN_THEME">
                            <div
                                class="color-box rounded-circle d-flex align-items-center justify-content-center skin-5">
                                <i class="ti ti-check text-white d-flex icon fs-5"></i>
                            </div>
                        </label>

                        <input type="radio" class="btn-check" name="color-theme-layout" id="orange-theme-layout"
                            autocomplete="off" />
                        <label class="btn p-9 btn-outline-primary d-flex align-items-center justify-content-center"
                            onclick="handleColorTheme('Orange_Theme')" for="orange-theme-layout"
                            data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="ORANGE_THEME">
                            <div
                                class="color-box rounded-circle d-flex align-items-center justify-content-center skin-6">
                                <i class="ti ti-check text-white d-flex icon fs-5"></i>
                            </div>
                        </label>
                    </div>

                    <h6 class="mt-5 fw-semibold fs-4 mb-2">Layout Type</h6>
                    <div class="d-flex flex-row gap-3 customizer-box" role="group">
                        <div>
                            <input type="radio" class="btn-check" name="page-layout" id="vertical-layout"
                                autocomplete="off" />
                            <label class="btn p-9 btn-outline-primary" for="vertical-layout"><i
                                    class="icon ti ti-layout-sidebar-right fs-7 me-2"></i>Vertical</label>
                        </div>
                        <div>
                            <input type="radio" class="btn-check" name="page-layout" id="horizontal-layout"
                                autocomplete="off" />
                            <label class="btn p-9 btn-outline-primary" for="horizontal-layout"><i
                                    class="icon ti ti-layout-navbar fs-7 me-2"></i>Horizontal</label>
                        </div>
                    </div>

                    <h6 class="mt-5 fw-semibold fs-4 mb-2">Container Option</h6>

                    <div class="d-flex flex-row gap-3 customizer-box" role="group">
                        <input type="radio" class="btn-check" name="layout" id="boxed-layout" autocomplete="off" />
                        <label class="btn p-9 btn-outline-primary" for="boxed-layout"><i
                                class="icon ti ti-layout-distribute-vertical fs-7 me-2"></i>Boxed</label>

                        <input type="radio" class="btn-check" name="layout" id="full-layout" autocomplete="off" />
                        <label class="btn p-9 btn-outline-primary" for="full-layout"><i
                                class="icon ti ti-layout-distribute-horizontal fs-7 me-2"></i>Full</label>
                    </div>

                    <h6 class="fw-semibold fs-4 mb-2 mt-5">Sidebar Type</h6>
                    <div class="d-flex flex-row gap-3 customizer-box" role="group">
                        <a href="javascript:void(0)" class="fullsidebar">
                            <input type="radio" class="btn-check" name="sidebar-type" id="full-sidebar"
                                autocomplete="off" />
                            <label class="btn p-9 btn-outline-primary" for="full-sidebar"><i
                                    class="icon ti ti-layout-sidebar-right fs-7 me-2"></i>Full</label>
                        </a>
                        <div>
                            <input type="radio" class="btn-check " name="sidebar-type" id="mini-sidebar"
                                autocomplete="off" />
                            <label class="btn p-9 btn-outline-primary" for="mini-sidebar"><i
                                    class="icon ti ti-layout-sidebar fs-7 me-2"></i>Collapse</label>
                        </div>
                    </div>

                    <h6 class="mt-5 fw-semibold fs-4 mb-2">Card With</h6>

                    <div class="d-flex flex-row gap-3 customizer-box" role="group">
                        <input type="radio" class="btn-check" name="card-layout" id="card-with-border"
                            autocomplete="off" />
                        <label class="btn p-9 btn-outline-primary" for="card-with-border"><i
                                class="icon ti ti-border-outer fs-7 me-2"></i>Border</label>

                        <input type="radio" class="btn-check" name="card-layout" id="card-without-border"
                            autocomplete="off" />
                        <label class="btn p-9 btn-outline-primary" for="card-without-border"><i
                                class="icon ti ti-border-none fs-7 me-2"></i>Shadow</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="dark-transparent sidebartoggler"></div>
    </div>

    <div class="modal fade" id="Modal-Manualbook" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-labelledby="scroll-long-outer-modal" aria-hidden="true" style="display: none;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center">
                    <h4 class="modal-title" id="myLargeModalLabel">
                        Manual Book
                    </h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <?php
                    $uri = \Config\Services::request()->getUri()->getPath();
                    // remove index.php
                    $uri = str_replace('/index.php/', '', $uri);
                    $manualbook = db_connect()->table('menu')->where(array("url" => $uri))->get()->getFirstRow();
                    if (!empty($manualbook) && $manualbook->manualbook != null): ?>
                        <h4><?= $manualbook->nama_menu ?></h4>
                        <embed type="application/pdf" src="<?= base_url() . "/manualbook/" . $manualbook->manualbook ?>"
                            width="100%" height="800"></embed>
                    <?php else: ?>
                        <h4>Manual Book Tidak Tersedia</h4>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger  waves-effect text-start"
                        data-bs-dismiss="modal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>




    <style>
        .notif-count {
            position: absolute;
            top: 13px;
            right: -1px;
            background-color: #f00;
            color: #fff;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 5px;
            border-radius: 50%;
            line-height: 1;
            min-width: 16px;
            text-align: center;
        }
    </style>




    <script src="<?php echo base_url('template/') ?>assets/js/vendor.min.js"></script>
    <!-- Import Js Files -->
    <script src="<?php echo base_url('template/') ?>assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo base_url('template/') ?>assets/libs/simplebar/dist/simplebar.min.js"></script>
    <script src="<?php echo base_url('template/') ?>assets/js/theme/app.dark.init.js"></script>
    <script src="<?php echo base_url('template/') ?>assets/js/theme/theme.js"></script>
    <script src="<?php echo base_url('template/') ?>assets/js/theme/app.min.js"></script>
    <script src="<?php echo base_url('template/') ?>assets/js/theme/sidebarmenu.js"></script>
    <script src="<?php echo base_url('template/') ?>assets/js/theme/feather.min.js"></script>
    <script>
    (function() {
        var key = 'app-theme';
        var theme = window.__appTheme || 'light';

        function apply(t) {
            theme = t;
            document.documentElement.setAttribute('data-bs-theme', t);
            document.querySelectorAll('.moon').forEach(function(el) {
                el.style.display = t === 'dark' ? 'none' : 'flex';
            });
            document.querySelectorAll('.sun').forEach(function(el) {
                el.style.display = t === 'dark' ? 'flex' : 'none';
            });
            document.querySelectorAll('.dark-logo').forEach(function(el) {
                el.style.display = t === 'dark' ? 'none' : 'flex';
            });
            document.querySelectorAll('.light-logo').forEach(function(el) {
                el.style.display = t === 'dark' ? 'flex' : 'none';
            });
            try { localStorage.setItem(key, t); } catch (e) {}
        }
        apply(theme);

        document.querySelectorAll('.dark-layout').forEach(function(el) {
            el.addEventListener('click', function() { apply('dark'); });
        });
        document.querySelectorAll('.light-layout').forEach(function(el) {
            el.addEventListener('click', function() { apply('light'); });
        });

        var mq = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)');
        if (mq && mq.addEventListener) {
            mq.addEventListener('change', function(e) {
                var saved = null;
                try { saved = localStorage.getItem(key); } catch (err) {}
                if (saved !== 'dark' && saved !== 'light') {
                    apply(e.matches ? 'dark' : 'light');
                }
            });
        }
    })();
    </script>

    <!-- solar icons -->
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
    <script src="<?php echo base_url('template/') ?>assets/libs/jvectormap/jquery-jvectormap.min.js"></script>
    <script src="<?php echo base_url('template/') ?>assets/libs/apexcharts/dist/apexcharts.min.js"></script>
    <script src="<?php echo base_url('template/') ?>assets/js/extra-libs/jvectormap/jquery-jvectormap-us-aea-en.js">
    </script>
    <script src="<?php echo base_url('template/') ?>assets/js/dashboards/dashboard.js"></script>


    <!-- js alert -->
    <?php if (session()->getFlashdata('sukses')) : ?>
        <script>
            $(document).ready(function() {
                toastr.success(
                    "<?= session()->getFlashdata('sukses'); ?>",
                    "Berhasil!", {
                        showMethod: "slideDown",
                        hideMethod: "slideUp",
                        progressBar: true,
                        timeOut: 2000
                    }
                );
            });
        </script>
    <?php endif; ?>
    <!-- js alert Ends -->
    <?php if (session()->getFlashdata('gagal')) : ?>
        <script>
            $(document).ready(function() {
                toastr.warning(
                    <?= json_encode(session()->getFlashdata('gagal')) ?>,
                    "Gagal!", {
                        showMethod: "slideDown",
                        hideMethod: "slideUp",
                        progressBar: true,
                        timeOut: 2000
                    }
                );
            });
        </script>
    <?php endif ?>




</body>

<script>
    document.documentElement.setAttribute("data-boxed-layout", "full");
    document.getElementById("full-layout").checked = true;

    // Save and restore sidebar scroll position with SimpleBar support
    (function() {
        const sidebarWrapper = document.querySelector('.scroll-sidebar');

        if (sidebarWrapper) {
            // Wait for SimpleBar to initialize
            setTimeout(function() {
                const simplebarContent = sidebarWrapper.querySelector('.simplebar-content-wrapper');
                const scrollElement = simplebarContent || sidebarWrapper;

                // Restore scroll position on page load
                const savedScroll = localStorage.getItem('sidebarScrollPosition');
                if (savedScroll) {
                    scrollElement.scrollTop = parseInt(savedScroll, 10);
                }

                // Save scroll position on scroll
                let scrollTimeout;
                scrollElement.addEventListener('scroll', function() {
                    clearTimeout(scrollTimeout);
                    scrollTimeout = setTimeout(function() {
                        localStorage.setItem('sidebarScrollPosition', scrollElement.scrollTop);
                    }, 100);
                });

                // Save scroll position before page unload
                window.addEventListener('beforeunload', function() {
                    localStorage.setItem('sidebarScrollPosition', scrollElement.scrollTop);
                });
            }, 500);
        }
    })();
</script>
<script src="<?php echo base_url('template/assets/libs/datatables.net/js/jquery.dataTables.min.js') ?>"></script>
<script src="<?php echo base_url('template/assets/js/datatable/datatable-basic.init.js') ?>"></script>
<link rel="stylesheet"
    href="<?php echo base_url('template/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css') ?>" />
<script src="<?php echo base_url('template/assets/js/plugins/toastr-init.js') ?>"></script>
<script src="<?php echo base_url('template/assets/libs/select2/dist/js/select2.full.min.js') ?>"></script>
<script src="<?php echo base_url('template/assets/libs/select2/dist/js/select2.min.js') ?>"></script>
<script src="<?php echo base_url('template/assets/js/forms/select2.init.js') ?>"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js"></script>

</html>
