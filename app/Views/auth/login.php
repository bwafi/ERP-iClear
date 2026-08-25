<!DOCTYPE html>
<html lang="id" dir="ltr" data-bs-theme="light" data-color-theme="Blue_Theme" data-layout="vertical">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="shortcut icon" type="image/png" href="<?php echo base_url('template/') ?><?= env('app.logo', 'assets/images/logo.png') ?>" />
    <link rel="stylesheet" href="<?php echo base_url('template/') ?>assets/css/styles.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>Masuk - <?= env('app.name', 'Aplikasi ERP') ?></title>
    <style>
        body {
            background-color: #F1F5F9;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .login-split-card {
            width: 100%;
            max-width: 1020px;
            height: 640px;
            background: #FFFFFF;
            border-radius: 26px;
            box-shadow: 0 25px 50px rgba(15, 23, 42, 0.12), 0 10px 20px rgba(15, 23, 42, 0.05);
            display: flex;
            overflow: hidden;
            position: relative;
        }
        .login-form-side {
            flex: 1;
            padding: 55px 54px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #FFFFFF;
            z-index: 2;
        }
        .login-brand {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 38px;
        }
        .login-brand img {
            max-width: 150px;
            max-height: 55px;
            object-fit: contain;
        }
        .login-title {
            color: #0F172A;
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            margin-bottom: 4px;
        }
        .login-subtitle {
            color: #64748B;
            font-size: 0.9rem;
            margin-bottom: 30px;
        }
        .login-subtitle a {
            color: #2563EB;
            font-weight: 600;
            text-decoration: none;
        }
        .login-subtitle a:hover {
            color: #1D4ED8;
        }
        .login-label {
            color: #475569;
            font-size: 0.82rem;
            font-weight: 500;
            margin-bottom: 7px;
        }
        .form-floating-custom {
            position: relative;
        }
        .form-floating-custom .form-control {
            width: 100%;
            height: 48px;
            background-color: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 11px;
            padding: 0 16px;
            font-size: 0.9rem;
            color: #0F172A;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }
        .form-floating-custom .form-control::placeholder {
            color: #94A3B8;
        }
        .form-floating-custom .form-control:focus {
            background-color: #FFFFFF;
            border-color: #2563EB;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
            outline: none;
        }
        .password-input {
            padding-right: 48px !important;
        }
        .password-toggle {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 0;
            background: transparent;
            color: #94A3B8;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        .password-toggle:hover {
            color: #2563EB;
        }
        .password-toggle:focus {
            outline: none;
            box-shadow: none;
        }
        .password-toggle i {
            font-size: 17px;
            line-height: 1;
        }
        .forgot-password {
            color: #64748B;
            font-size: 0.78rem;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .forgot-password:hover {
            color: #2563EB;
        }
        .remember-wrapper {
            display: flex;
            align-items: center;
            margin-top: 18px;
            margin-bottom: 26px;
        }
        .remember-wrapper .form-check {
            display: flex;
            align-items: center;
            gap: 7px;
            margin: 0;
            padding-left: 0;
        }
        .remember-wrapper .form-check-input {
            width: 16px;
            height: 16px;
            margin: 0;
            border: 1px solid #94A3B8;
            border-radius: 3px;
            cursor: pointer;
        }
        .remember-wrapper .form-check-input:checked {
            background-color: #2563EB;
            border-color: #2563EB;
        }
        .remember-wrapper .form-check-label {
            color: #64748B;
            font-size: 0.82rem;
            cursor: pointer;
        }
        .btn-custom-primary {
            width: 100%;
            height: 48px;
            background-color: #2563EB;
            border: 1px solid #2563EB;
            border-radius: 11px;
            color: #FFFFFF;
            font-size: 0.9rem;
            font-weight: 600;
            transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }
        .btn-custom-primary:hover {
            background-color: #1D4ED8;
            border-color: #1D4ED8;
            color: #FFFFFF;
            transform: translateY(-1px);
            box-shadow: 0 7px 15px rgba(37, 99, 235, 0.20);
        }
        .btn-custom-primary:active {
            transform: translateY(0);
        }
        .login-error {
            background-color: #FEF2F2;
            border: 1px solid #FECACA;
            border-radius: 9px;
            color: #DC2626;
            font-size: 0.8rem;
            padding: 9px 12px;
            margin-bottom: 18px;
        }
        .field-error {
            color: #DC2626;
            font-size: 0.75rem;
            margin-top: 5px;
        }
        .login-banner-side {
            flex: 1;
            position: relative;
            background: url('<?php echo base_url("template/") ?>assets/images/login-img.webp') no-repeat center center;
            background-size: cover;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 36px 40px;
            color: #FFFFFF;
        }
        .login-banner-side::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.25) 0%, rgba(15, 23, 42, 0.48) 45%, rgba(15, 23, 42, 0.90) 100%);
            z-index: 1;
        }
        .banner-content, .banner-top, .banner-footer {
            position: relative;
            z-index: 2;
        }
        .support-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 12px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 9px;
            color: #FFFFFF;
            font-size: 0.75rem;
            text-decoration: none;
            backdrop-filter: blur(8px);
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }
        .support-badge:hover {
            background: rgba(255, 255, 255, 0.22);
            border-color: rgba(255, 255, 255, 0.28);
            color: #FFFFFF;
        }
        .support-badge i {
            font-size: 13px;
        }
        .erp-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 13px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.20);
            border-radius: 999px;
            color: #E2E8F0;
            font-size: 0.72rem;
            font-weight: 600;
            backdrop-filter: blur(8px);
            margin-bottom: 15px;
        }
        .status-dot {
            width: 7px;
            height: 7px;
            flex: 0 0 7px;
            border-radius: 50%;
            background-color: #22C55E;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.14), 0 0 9px rgba(34, 197, 94, 0.40);
        }
        .banner-content {
            max-width: 430px;
            margin-top: auto;
            margin-bottom: auto;
        }
        .banner-content h2 {
            color: #FFFFFF;
            font-size: 2rem;
            line-height: 1.18;
            font-weight: 700;
            letter-spacing: -0.025em;
            margin-bottom: 14px;
        }
        .banner-content p {
            color: rgba(255, 255, 255, 0.68);
            font-size: 0.84rem;
            line-height: 1.55;
            margin-bottom: 0;
        }
        .banner-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }
        .banner-footer-brand {
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.75rem;
            font-weight: 500;
        }
        .banner-indicators {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .banner-indicator {
            display: block;
            width: 6px;
            height: 4px;
            background: rgba(255, 255, 255, 0.35);
            border-radius: 3px;
        }
        .banner-indicator.active {
            width: 19px;
            background: #FFFFFF;
        }
        @media (max-width: 991px) {
            body {
                overflow: auto;
                padding: 20px;
            }
            .login-split-card {
                max-width: 480px;
                height: auto;
                min-height: auto;
                border-radius: 22px;
            }
            .login-banner-side {
                display: none;
            }
            .login-form-side {
                padding: 45px 35px;
            }
            .login-brand {
                margin-bottom: 30px;
            }
        }
        @media (max-width: 480px) {
            body {
                padding: 12px;
            }
            .login-form-side {
                padding: 35px 25px;
            }
            .login-title {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="preloader">
        <img src="<?php echo base_url('template/') ?>assets/images/logos/loader.svg" alt="Memuat..." class="lds-ripple img-fluid" />
    </div>

    <div class="login-split-card">
        <div class="login-form-side">
            <div class="login-brand">
                <img src="<?php echo base_url('template/') ?><?= env('app.logo', 'assets/images/logo.png') ?>" alt="Logo <?= env('app.name', 'ERP') ?>" onerror="this.style.display='none'" />
            </div>

            <h3 class="login-title">Masuk</h3>
            <p class="login-subtitle">Kelola sistem ERP Anda secara efisien.</p>

            <form action="<?= base_url('/proses_login') ?>" method="post">
                <?php if (session()->getFlashdata('pesan_error')) { ?>
                    <div class="login-error"><?= session()->getFlashdata('pesan_error') ?></div>
                <?php } ?>

                <div class="mb-3">
                    <label for="InputUsername" class="login-label">Email atau Nama Pengguna</label>
                    <div class="form-floating-custom">
                        <input type="text" name="username" class="form-control" id="InputUsername" placeholder="contoh@gmail.com" value="<?= old('username') ?>" autocomplete="username" />
                    </div>
                    <?php if (session()->getFlashdata('pesan_username')) { ?>
                        <div class="field-error"><?= session()->getFlashdata('pesan_username') ?></div>
                    <?php } ?>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label for="exampleInputPassword1" class="login-label mb-0">Kata Sandi</label>
                        <a href="https://wa.me/6285959978483?text=Halo%2C%20saya%20lupa%20password%20untuk%20akun%20sistem%20ERP%20iCLEAR.%20Mohon%20bantuannya." class="forgot-password" target="_blank" rel="noopener noreferrer">Lupa Kata Sandi?</a>
                    </div>

                    <div class="form-floating-custom">
                        <input type="password" name="password" class="form-control password-input" id="exampleInputPassword1" placeholder="••••••••" autocomplete="current-password" />
                        <button type="button" id="togglePassword" class="password-toggle" aria-label="Tampilkan kata sandi" title="Tampilkan kata sandi">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>

                    <?php if (session()->getFlashdata('pesan_password')) { ?>
                        <div class="field-error"><?= session()->getFlashdata('pesan_password') ?></div>
                    <?php } ?>
                </div>

                <div class="remember-wrapper">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" name="remember" id="rememberMe" />
                        <label class="form-check-label" for="rememberMe">Ingat perangkat ini</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-custom-primary">Masuk</button>
            </form>
        </div>

        <div class="login-banner-side d-none d-lg-flex">
            <div class="banner-top">
                <a href="https://wa.me/6285959978483?text=Halo%2C%20saya%20membutuhkan%20bantuan%20terkait%20sistem%20ERP%20iCLEAR." class="support-badge" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-life-preserver"></i> Bantuan / Support
                </a>
            </div>

            <div class="banner-content">
                <div class="erp-status">
                    <span class="status-dot"></span> Sistem ERP Aktif
                </div>
                <h2>Kelola operasional bisnis dalam satu sistem.</h2>
                <p>Kelola operasional, inventaris, keuangan, dan data perusahaan secara terintegrasi dan profesional.</p>
            </div>

            <div class="banner-footer">
                <span class="banner-footer-brand"><?= env('app.name', 'Aplikasi ERP') ?></span>
                <div class="banner-indicators">
                    <span class="banner-indicator active"></span>
                    <span class="banner-indicator"></span>
                    <span class="banner-indicator"></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#exampleInputPassword1');
        const toggleIcon = document.querySelector('#toggleIcon');

        if (togglePassword && password && toggleIcon) {
            togglePassword.addEventListener('click', function () {
                const isPassword = password.getAttribute('type') === 'password';
                if (isPassword) {
                    password.setAttribute('type', 'text');
                    toggleIcon.classList.remove('bi-eye');
                    toggleIcon.classList.add('bi-eye-slash');
                    togglePassword.setAttribute('aria-label', 'Sembunyikan kata sandi');
                    togglePassword.setAttribute('title', 'Sembunyikan kata sandi');
                } else {
                    password.setAttribute('type', 'password');
                    toggleIcon.classList.remove('bi-eye-slash');
                    toggleIcon.classList.add('bi-eye');
                    togglePassword.setAttribute('aria-label', 'Tampilkan kata sandi');
                    togglePassword.setAttribute('title', 'Tampilkan kata sandi');
                }
            });
        }
    </script>

    <script src="<?php echo base_url('template/') ?>assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo base_url('template/') ?>assets/libs/simplebar/dist/simplebar.min.js"></script>
    <script src="<?php echo base_url('template/') ?>assets/js/theme/app.dark.init.js"></script>
    <script src="<?php echo base_url('template/') ?>assets/js/theme/theme.js"></script>
    <script src="<?php echo base_url('template/') ?>assets/js/theme/app.min.js"></script>
    <script src="<?php echo base_url('template/') ?>assets/js/theme/sidebarmenu.js"></script>
    <script src="<?php echo base_url('template/') ?>assets/js/theme/feather.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
</body>
</html>