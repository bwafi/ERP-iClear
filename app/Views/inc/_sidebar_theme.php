<?php

/**
 * Design system sidebar vertikal.
 *
 * Semua selector berada di bawah `.left-sidebar.with-vertical`, jadi sidebar
 * horizontal (left_horizontal.php) dan halaman lain tidak ikut berubah.
 * Warna memakai token template (--bs-*), bukan hex baru, supaya ikut tema
 * terang/gelap dan pergantian color-theme.
 *
 * Hook yang dipertahankan: #sidebarnav, .sidebar-item, .sidebar-link,
 * .has-arrow, .collapse/.in, .hide-menu, .aside-icon, .nav-small-cap.
 * sidebarmenu.js dan pencarian menu di template.php bergantung pada ini.
 */
?>

<style>
    .left-sidebar.with-vertical {
        --sb-ink: color-mix(in srgb, var(--bs-body-color) 70%, var(--bs-emphasis-color));
        --sb-muted: color-mix(in srgb, var(--bs-body-color) 85%, var(--bs-emphasis-color));
        --sb-icon: color-mix(in srgb, var(--bs-body-color) 68%, var(--bs-primary));
        --sb-accent: color-mix(in srgb, var(--bs-primary) 62%, var(--bs-emphasis-color));
        --sb-line: var(--bs-border-color);
        --sb-hover: color-mix(in srgb, var(--bs-primary) 6%, transparent);
        --sb-active: color-mix(in srgb, var(--bs-primary) 10%, transparent);
        display: flex;
        flex-direction: column;
        border: 1px solid var(--sb-line);
    }

    /* ---------- Brand ---------- */
    .left-sidebar.with-vertical .brand-logo {
        position: relative;
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 0;
        padding: 14px 16px;
        border-bottom: 1px solid var(--sb-line);
    }

    .left-sidebar.with-vertical .brand-logo .sb-brand-logo {
        flex: 0 0 auto;
    }

    .left-sidebar.with-vertical .brand-logo img {
        max-height: 38px;
        width: auto;
    }

    .left-sidebar.with-vertical .sb-brand-text {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 1px;
    }

    .left-sidebar.with-vertical .sb-brand-name {
        font-size: .8125rem;
        font-weight: 650;
        line-height: 1.25;
        color: var(--sb-ink);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .left-sidebar.with-vertical .brand-logo .sidebartoggler {
        position: absolute;
        top: 50%;
        right: 8px;
        transform: translateY(-50%);
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        color: var(--sb-muted);
    }

    .left-sidebar.with-vertical .brand-logo .sidebartoggler:hover {
        background: var(--sb-hover);
        color: var(--sb-ink);
    }

    /* ---------- Scroll area ---------- */
    .left-sidebar.with-vertical .scroll-sidebar {
        flex: 1 1 auto;
        min-height: 0;
        height: auto;
        padding: 8px 10px 14px;
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-width: thin;
        scrollbar-color: color-mix(in srgb, var(--sb-muted) 45%, transparent) transparent;
    }

    .left-sidebar.with-vertical .scroll-sidebar::-webkit-scrollbar {
        width: 5px;
    }

    .left-sidebar.with-vertical .scroll-sidebar::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: color-mix(in srgb, var(--sb-muted) 45%, transparent);
    }

    /* ---------- Group label ---------- */
    .left-sidebar.with-vertical #sidebarnav > .nav-small-cap {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 16px 2px 5px;
        padding: 0 6px;
        font-size: .625rem;
        font-weight: 700;
        line-height: 1.2;
        letter-spacing: .09em;
        text-transform: uppercase;
        color: var(--sb-muted);
    }

    .left-sidebar.with-vertical #sidebarnav > .nav-small-cap:first-child {
        margin-top: 2px;
    }

    .left-sidebar.with-vertical #sidebarnav > .nav-small-cap::after {
        content: "";
        flex: 1 1 auto;
        height: 1px;
        background: var(--sb-line);
        opacity: .8;
    }

    .left-sidebar.with-vertical #sidebarnav .nav-small-cap-icon {
        display: none;
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: var(--sb-muted);
        opacity: .55;
    }

    /* ---------- Top-level item ---------- */
    .left-sidebar.with-vertical #sidebarnav > .sidebar-item > .sidebar-link {
        position: relative;
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 40px;
        margin: 0;
        padding: 5px 10px 5px 5px;
        border-radius: 10px;
        color: var(--sb-ink);
        font-size: .875rem;
        font-weight: 500;
        line-height: 1.35;
        white-space: nowrap;
        transition: background-color .16s ease, color .16s ease;
    }

    .left-sidebar.with-vertical #sidebarnav .aside-icon {
        flex: 0 0 30px;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background: none !important;
        color: var(--sb-icon);
        transition: color .16s ease;
    }

    .left-sidebar.with-vertical #sidebarnav .aside-icon iconify-icon {
        width: 19px;
        height: 19px;
        font-size: 19px;
    }

    .left-sidebar.with-vertical #sidebarnav .hide-menu {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .left-sidebar.with-vertical #sidebarnav > .sidebar-item > .sidebar-link:hover,
    .left-sidebar.with-vertical #sidebarnav .first-level .sidebar-link:hover {
        background: var(--sb-hover);
        color: var(--sb-ink);
    }

    .left-sidebar.with-vertical #sidebarnav > .sidebar-item > .sidebar-link:hover .aside-icon {
        color: var(--sb-accent);
    }

    .left-sidebar.with-vertical #sidebarnav > .sidebar-item > .sidebar-link.active {
        background: var(--sb-active);
        color: var(--sb-accent);
        font-weight: 600;
    }

    .left-sidebar.with-vertical #sidebarnav > .sidebar-item > .sidebar-link.active .aside-icon {
        color: var(--sb-accent);
    }

    .left-sidebar.with-vertical #sidebarnav .sidebar-link:focus-visible {
        outline: 2px solid var(--bs-primary);
        outline-offset: 1px;
    }

    /* ---------- Chevron ---------- */
    .left-sidebar.with-vertical #sidebarnav .sidebar-link.has-arrow::after {
        content: "";
        position: absolute;
        top: 50%;
        right: 13px;
        width: 5px;
        height: 5px;
        margin: 0;
        border-style: solid;
        border-width: 0 1.5px 1.5px 0;
        border-color: currentColor;
        transform: translateY(-60%) rotate(-45deg);
        transform-origin: center;
        opacity: .5;
        transition: transform .18s ease, opacity .18s ease;
    }

    .left-sidebar.with-vertical #sidebarnav .sidebar-link.has-arrow.active::after,
    .left-sidebar.with-vertical #sidebarnav .sidebar-item:has(> ul.in) > .sidebar-link.has-arrow::after {
        transform: translateY(-25%) rotate(45deg);
        opacity: .9;
    }

    .left-sidebar.with-vertical #sidebarnav .sidebar-link.has-arrow {
        padding-right: 28px;
    }

    /* ---------- Sub-menu ---------- */
    .left-sidebar.with-vertical #sidebarnav .first-level {
        margin: 2px 0 4px 15px;
        padding: 2px 0 2px 11px;
        border-left: 1px solid var(--sb-line);
    }

    .left-sidebar.with-vertical #sidebarnav .first-level .sidebar-item > .sidebar-link {
        display: flex;
        align-items: center;
        min-height: 32px;
        margin: 1px 0;
        padding: 5px 10px;
        border-radius: 8px;
        color: var(--sb-muted);
        font-size: .8125rem;
        font-weight: 500;
        line-height: 1.3;
        white-space: nowrap;
        opacity: 1;
        transition: background-color .16s ease, color .16s ease;
    }

    .left-sidebar.with-vertical #sidebarnav .first-level .sidebar-item > .sidebar-link::before {
        content: "";
        flex: 0 0 4px;
        width: 4px;
        height: 4px;
        margin-right: 9px;
        border-radius: 50%;
        background: var(--sb-accent);
        opacity: 0;
        transition: opacity .16s ease;
    }

    .left-sidebar.with-vertical #sidebarnav .first-level .sidebar-item > .sidebar-link:hover::before {
        opacity: .4;
    }

    .left-sidebar.with-vertical #sidebarnav .first-level .sidebar-item > .sidebar-link.active {
        color: var(--sb-accent);
        font-weight: 600;
    }

    .left-sidebar.with-vertical #sidebarnav .first-level .sidebar-item > .sidebar-link.active::before {
        opacity: 1;
    }

    .left-sidebar.with-vertical #sidebarnav .first-level .sidebar-item:last-child {
        margin-bottom: 0;
    }

    .left-sidebar.with-vertical #sidebarnav .two-level {
        margin: 2px 0 2px 10px;
        padding-left: 10px;
        border-left: 1px solid var(--sb-line);
    }

    /* ---------- Deprecated chip ---------- */
    .left-sidebar.with-vertical .sb-badge {
        margin-left: auto;
        padding: 2px 6px;
        border-radius: 999px;
        background: var(--bs-warning-bg-subtle);
        color: var(--bs-warning-text-emphasis, var(--bs-warning));
        font-size: .5625rem;
        font-weight: 700;
        line-height: 1.4;
        letter-spacing: .04em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    /* ---------- Footer ---------- */
    .left-sidebar.with-vertical .sidebar-footer {
        flex: 0 0 auto;
        margin: 0;
        padding: 10px 12px 12px;
        border-top: 1px solid var(--sb-line);
    }

    .left-sidebar.with-vertical .sb-user {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 5px;
        border-radius: 10px;
        transition: background-color .16s ease;
    }

    .left-sidebar.with-vertical .sb-user:hover {
        background: var(--sb-hover);
    }

    .left-sidebar.with-vertical .sb-avatar {
        flex: 0 0 34px;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        object-fit: cover;
    }

    .left-sidebar.with-vertical .sb-user-meta {
        flex: 1 1 auto;
        min-width: 0;
    }

    .left-sidebar.with-vertical .sb-user-name,
    .left-sidebar.with-vertical .sb-user-role {
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .left-sidebar.with-vertical .sb-user-name {
        font-size: .8125rem;
        font-weight: 600;
        line-height: 1.3;
        color: var(--sb-ink);
    }

    .left-sidebar.with-vertical .sb-user-role {
        font-size: .6875rem;
        line-height: 1.3;
        color: var(--sb-muted);
    }

    .left-sidebar.with-vertical .sb-logout {
        flex: 0 0 30px;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        color: var(--sb-muted);
        transition: background-color .16s ease, color .16s ease;
    }

    .left-sidebar.with-vertical .sb-logout:hover {
        background: var(--bs-danger-bg-subtle);
        color: var(--bs-danger-text-emphasis, var(--bs-danger));
    }

    .left-sidebar.with-vertical .sb-logout:focus-visible {
        outline: 2px solid var(--bs-danger);
        outline-offset: 1px;
    }

    /* ---------- Collapsed rail ---------- */
    @media (min-width: 1300px) {
        [data-layout="vertical"] body[data-sidebartype="mini-sidebar"] .left-sidebar.with-vertical:not(:hover) .sb-brand-text,
        [data-layout="vertical"] body[data-sidebartype="mini-sidebar"] .left-sidebar.with-vertical:not(:hover) .sb-user-meta,
        [data-layout="vertical"] body[data-sidebartype="mini-sidebar"] .left-sidebar.with-vertical:not(:hover) .sb-logout {
            display: none;
        }

        [data-layout="vertical"] body[data-sidebartype="mini-sidebar"] .left-sidebar.with-vertical .scroll-sidebar {
            height: auto;
            padding: 10px 8px 12px;
        }

        [data-layout="vertical"] body[data-sidebartype="mini-sidebar"] .left-sidebar.with-vertical:not(:hover) .brand-logo {
            justify-content: center;
            padding: 14px 0;
        }

        [data-layout="vertical"] body[data-sidebartype="mini-sidebar"] .left-sidebar.with-vertical:not(:hover) #sidebarnav > .sidebar-item > .sidebar-link {
            justify-content: center;
            padding: 5px;
        }

        [data-layout="vertical"] body[data-sidebartype="mini-sidebar"] .left-sidebar.with-vertical:not(:hover) #sidebarnav > .nav-small-cap {
            justify-content: center;
            margin: 12px 0 4px;
        }

        [data-layout="vertical"] body[data-sidebartype="mini-sidebar"] .left-sidebar.with-vertical:not(:hover) #sidebarnav > .nav-small-cap .nav-small-cap-icon {
            display: inline-block;
        }

        [data-layout="vertical"] body[data-sidebartype="mini-sidebar"] .left-sidebar.with-vertical:not(:hover) #sidebarnav > .nav-small-cap::after {
            display: none;
        }

        [data-layout="vertical"] body[data-sidebartype="mini-sidebar"] .left-sidebar.with-vertical:not(:hover) .sb-user {
            justify-content: center;
            padding: 5px 0;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .left-sidebar.with-vertical *,
        .left-sidebar.with-vertical *::before,
        .left-sidebar.with-vertical *::after {
            transition-duration: 0s !important;
            animation-duration: 0s !important;
        }
    }
</style>
