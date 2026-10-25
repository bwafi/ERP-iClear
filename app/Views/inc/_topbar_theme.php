<?php

/**
 * Tema topbar horizontal.
 *
 * Semua selector berada di bawah `header.topbar` supaya tidak bocor ke
 * komponen lain. Token `--tb-*` mengikuti sistem desain halaman lain
 * (surface kartu, hairline, satu aksen biru), sehingga topbar menyatu
 * dengan sidebar dan page head yang sudah diredesain.
 */
?>

<style>
    header.topbar {
        --tb-surface: var(--bs-card-bg, #fff);
        --tb-subtle: #f6f8fc;
        --tb-rule: var(--bs-border-color, #e5eaf1);
        --tb-ink: #24303f;
        --tb-muted: #5b6672;
        --tb-accent: #2f5fe0;
        --tb-accent-soft: rgba(47, 95, 224, .1);
        --tb-hover: rgba(47, 95, 224, .05);
        --tb-radius: 16px;
        --tb-radius-sm: 10px;
        --tb-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 18px 42px -36px rgba(16, 24, 40, .35);
        padding: 0 12px;
        margin-bottom: 20px;
        border: 1px solid var(--tb-rule);
        border-radius: var(--tb-radius);
        background: var(--tb-surface);
        box-shadow: var(--tb-shadow);
        color: var(--tb-ink);
    }

    html[data-bs-theme="dark"] header.topbar {
        --tb-surface: var(--bs-card-bg, #2a3547);
        --tb-subtle: rgba(255, 255, 255, .035);
        --tb-rule: var(--bs-border-color, rgba(255, 255, 255, .12));
        --tb-ink: var(--bs-body-color, #cfd8e3);
        --tb-muted: #8fa0b8;
        --tb-accent: #8ab4ff;
        --tb-accent-soft: rgba(138, 180, 255, .14);
        --tb-hover: rgba(255, 255, 255, .05);
        --tb-shadow: none;
    }

    header.topbar .navbar {
        min-height: 64px;
    }

    header.topbar ::selection {
        background: var(--tb-accent-soft);
        color: var(--tb-ink);
    }

    header.topbar input {
        caret-color: var(--tb-accent);
    }

    /* ---------- Tombol ikon ---------- */
    header.topbar .navbar .navbar-nav .nav-item .nav-link {
        display: flex;
        align-items: center;
        height: 38px;
        margin: 0 1px;
        padding: 0;
        border-radius: var(--tb-radius-sm);
        color: var(--tb-muted);
        line-height: 1;
        transition: background-color .16s ease, color .16s ease;
    }

    header.topbar .nav-icon-hover {
        width: 38px;
        justify-content: center;
    }

    header.topbar .nav-icon-hover::before {
        display: none;
    }

    header.topbar .nav-icon-hover:hover,
    header.topbar .nav-icon-hover:focus-visible {
        background: var(--tb-hover);
        color: var(--tb-ink) !important;
    }

    header.topbar .navbar .nav-link > .nav-icon-hover-bg {
        position: relative;
        width: 100%;
        height: 100%;
        border-radius: inherit;
        background: none !important;
    }

    header.topbar .navbar .nav-link iconify-icon {
        color: var(--tb-muted) !important;
        transition: color .16s ease;
    }

    header.topbar .navbar .nav-link:hover iconify-icon,
    header.topbar .navbar .nav-link:focus-visible iconify-icon {
        color: var(--tb-ink) !important;
    }

    header.topbar .navbar .nav-link:focus-visible {
        outline: 2px solid var(--tb-accent);
        outline-offset: 1px;
    }

    /* ---------- Pencarian menu ---------- */
    header.topbar #topbar-search-form {
        margin-right: 12px !important;
    }

    header.topbar #topbar-search {
        height: 38px;
        padding: 0 40px 0 36px;
        border: 1px solid var(--tb-rule);
        border-radius: var(--tb-radius-sm);
        background: var(--tb-subtle);
        color: var(--tb-ink) !important;
        font-size: .8125rem;
        box-shadow: none;
    }

    header.topbar #topbar-search::placeholder {
        color: var(--tb-muted);
        opacity: 1;
    }

    header.topbar #topbar-search:focus {
        border-color: var(--tb-accent);
        background: var(--tb-surface);
        color: var(--tb-ink);
        box-shadow: 0 0 0 3px var(--tb-accent-soft);
    }

    header.topbar #topbar-search-form form > iconify-icon {
        left: 12px !important;
        margin-left: 0 !important;
        color: var(--tb-muted) !important;
        font-size: 16px;
        pointer-events: none;
    }

    header.topbar .tb-kbd {
        position: absolute;
        top: 50%;
        right: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 5px;
        border: 1px solid var(--tb-rule);
        border-radius: 6px;
        background: var(--tb-surface);
        color: var(--tb-muted);
        font-size: .625rem;
        font-weight: 600;
        line-height: 1;
        pointer-events: none;
        transform: translateY(-50%);
    }

    header.topbar .topbar-search-results {
        margin-top: 8px;
        border: 1px solid var(--tb-rule);
        border-radius: 12px;
        background: var(--tb-surface);
        box-shadow: 0 1px 2px rgba(16, 24, 40, .06), 0 18px 40px -20px rgba(16, 24, 40, .35);
    }

    header.topbar .topbar-search-results .dropdown-item {
        color: var(--tb-ink) !important;
        font-size: .8125rem;
    }

    header.topbar .topbar-search-results .dropdown-item:hover {
        background: var(--tb-hover);
    }

    header.topbar .topbar-search-results .text-muted {
        color: var(--tb-muted) !important;
    }

    /* ---------- Dropdown ---------- */
    header.topbar .dropdown-menu {
        margin-top: 8px;
        padding: 6px 0;
        border: 1px solid var(--tb-rule);
        border-radius: 14px;
        background: var(--tb-surface);
        box-shadow: 0 1px 2px rgba(16, 24, 40, .06), 0 24px 48px -28px rgba(16, 24, 40, .45);
        color: var(--tb-ink);
    }

    html[data-bs-theme="dark"] header.topbar .dropdown-menu {
        box-shadow: 0 24px 48px -28px rgba(0, 0, 0, .8);
    }

    header.topbar .navbar .navbar-nav .dropdown-menu.content-dd {
        width: min(385px, calc(100vw - 24px));
    }

    header.topbar .dropdown-menu .dropdown-item {
        white-space: normal;
    }

    header.topbar .dropdown-menu .dropdown-item:hover,
    header.topbar .dropdown-menu .dropdown-item:focus {
        background: var(--tb-hover);
    }

    header.topbar .dropdown-menu .text-muted {
        color: var(--tb-muted) !important;
    }

    header.topbar .dropdown-menu .text-primary {
        color: var(--tb-accent) !important;
    }

    header.topbar .dropdown-menu .text-info {
        color: #0b6b8a !important;
    }

    header.topbar .dropdown-menu .text-success {
        color: #0f7a46 !important;
    }

    header.topbar .dropdown-menu .text-warning {
        color: #8a5300 !important;
    }

    header.topbar .dropdown-menu .text-danger {
        color: #b02a37 !important;
    }

    header.topbar .dropdown-menu .badge.bg-info {
        background: color-mix(in srgb, var(--tb-accent) 12%, var(--tb-surface)) !important;
        color: var(--tb-accent) !important;
    }

    html[data-bs-theme="dark"] header.topbar .dropdown-menu .text-info {
        color: #7dd3f0 !important;
    }

    html[data-bs-theme="dark"] header.topbar .dropdown-menu .text-success {
        color: #6fdd9d !important;
    }

    html[data-bs-theme="dark"] header.topbar .dropdown-menu .text-warning {
        color: #ffcf6e !important;
    }

    html[data-bs-theme="dark"] header.topbar .dropdown-menu .text-danger {
        color: #ff9aa2 !important;
    }

    /* ---------- Profil ---------- */
    header.topbar .navbar .navbar-nav .nav-item .nav-link.tb-profile {
        height: auto;
        gap: 10px;
        margin-left: 6px;
        padding: 4px 8px 4px 4px;
    }

    header.topbar .navbar .navbar-nav .nav-item .nav-link.tb-profile:hover {
        background: var(--tb-hover);
    }

    header.topbar .tb-profile .user-profile {
        margin-right: 0 !important;
    }

    header.topbar .tb-profile .user-profile img {
        width: 34px;
        height: 34px;
        border: 1px solid var(--tb-rule);
        object-fit: cover;
    }

    header.topbar .tb-profile .profile-name {
        margin: 0 0 1px;
        color: var(--tb-ink);
        font-size: .8125rem;
        font-weight: 600;
        line-height: 1.25;
    }

    header.topbar .tb-profile .profile-subtext {
        margin: 0;
        color: var(--tb-muted);
        font-size: .6875rem;
        line-height: 1.25;
    }

    /* ---------- Pemisah & badge ---------- */
    header.topbar .tb-divider {
        width: 1px;
        height: 22px;
        margin: 0 8px;
        background: var(--tb-rule);
    }

    header.topbar .notif-count {
        top: -1px;
        right: -1px;
        min-width: 16px;
        height: 16px;
        padding: 0 4px;
        border-radius: 999px;
        background: #c2353f;
        color: #fff;
        font-size: .625rem;
        font-weight: 700;
        line-height: 16px;
        box-shadow: 0 0 0 2px var(--tb-surface);
    }

    @media (max-width: 767.98px) {
        header.topbar {
            margin-bottom: 14px;
            padding: 0 8px;
            border-radius: 14px;
        }

        header.topbar .navbar {
            min-height: 56px;
        }

        header.topbar .navbar .navbar-nav .nav-item .nav-link.nav-icon-hover {
            width: 36px;
            height: 36px;
        }

        header.topbar .tb-divider {
            margin: 0 4px;
        }

        header.topbar .navbar .navbar-nav .nav-item .nav-link.tb-profile {
            padding-right: 4px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        header.topbar *,
        header.topbar *::before,
        header.topbar *::after {
            transition-duration: 0s !important;
            animation-duration: 0s !important;
        }
    }
</style>
