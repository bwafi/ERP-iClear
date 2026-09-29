<?php
/**
 * Design system bersama Kas & Bank.
 *
 * Satu-satunya sumber ukuran font, warna, dan komponen untuk 4 halaman:
 * dashboard, akun, transfer, antar_unit.
 *
 * Skala tipografi sengaja disamakan dengan utilitas `fs-*` yang dipakai
 * seluruh aplikasi (nilai rem identik dengan styles.css template):
 *
 *   --kb-fs-page   1.25rem / 20px  judul halaman        (di _nav.php)
 *   --kb-fs-metric 1.125rem / 18px  angka metrik        = fs-5
 *   --kb-fs-title  1rem     / 16px  judul kartu + header tabel = fs-4
 *   --kb-fs-body   0.875rem / 14px  isi tabel, input     = fs-3
 *   --kb-fs-meta   0.75rem  / 12px  label, badge, keterangan = fs-2
 *   --kb-fs-micro  0.625rem / 10px  ikon, micro-teks    = fs-1
 *
 * Catatan: skala `fs-*` di template ini terbalik dari Bootstrap 5.3 standar
 * (fs-1 = terkecil, bukan terbesar). Jangan memakai fs-6/fs-7/fs-8 untuk
 * teks kecil — nilainya jauh lebih besar dari body.
 */
?>

<style>
    /* ===== 1. Token ===== */
    body {
        --kb-fs-metric: 1.125rem;
        --kb-fs-title: 1rem;
        --kb-fs-body: 0.875rem;
        --kb-fs-meta: 0.75rem;
        --kb-fs-micro: 0.625rem;
        --kb-border: var(--bs-border-color, #dee2e6);
        --kb-surface: var(--bs-body-bg, #fff);
        --kb-soft: var(--bs-tertiary-bg, #f8f9fa);
        --kb-text: var(--bs-body-color, #212529);
        --kb-muted: var(--bs-secondary-color, #6c757d);
    }

    /* Wrapper opsional (dipakai akun.php) */
    .kb {
        font-size: var(--kb-fs-body);
        line-height: 1.5;
        color: var(--kb-text);
    }

    /* Samakan komponen bawaan Bootstrap di dalam area modul */
    .kb .form-control,
    .kb .form-select,
    .kb .form-check-label,
    .kb .btn,
    .kb .table,
    .kb .nav-link {
        font-size: var(--kb-fs-body);
    }

    .kb .badge {
        font-size: var(--kb-fs-meta);
    }

    /* Angka rata kolom */
    .kb-num,
    .kb-mono,
    .kb-amount {
        font-variant-numeric: tabular-nums;
        font-feature-settings: "tnum";
        letter-spacing: -0.01em;
    }

    .kb-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }

    .kb-ico {
        font-size: var(--kb-fs-body);
        vertical-align: -1px;
    }

    .kb-ico-lg {
        font-size: 1.25rem;
        opacity: 0.45;
    }

    /* ===== 2. Kartu ===== */
    .kb-card {
        background: var(--kb-surface);
        border: 1px solid var(--kb-border);
        border-radius: 0.5rem;
        overflow: hidden;
    }

    .kb-card-body {
        padding: 1rem;
    }

    /* kb-card-head (akun.php) dan kb-card-header (transfer/antar_unit) */
    .kb-card-head,
    .kb-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--kb-border);
        background: var(--kb-soft);
    }

    /* kb-title (akun.php) dan kb-card-title (transfer/antar_unit) */
    .kb-title,
    .kb-card-title,
    .kb-pane-title {
        font-size: var(--kb-fs-title);
        font-weight: 600;
        color: var(--kb-text);
        line-height: 1.3;
    }

    .kb-card-sub,
    .kb-hint {
        font-size: var(--kb-fs-meta);
        color: var(--kb-muted);
    }

    /* ===== 3. Metrik ===== */
    .kb-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .kb-stat {
        background: var(--kb-surface);
        border: 1px solid var(--kb-border);
        border-radius: 0.5rem;
        padding: 0.75rem 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .kb-stat-label {
        font-size: var(--kb-fs-meta);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--kb-muted);
    }

    .kb-stat-value {
        font-size: var(--kb-fs-metric);
        font-weight: 700;
        color: var(--kb-text);
    }

    .kb-stat-hint {
        font-size: var(--kb-fs-micro);
        color: var(--kb-muted);
    }

    .kb-stat.is-warn {
        border-color: var(--bs-danger-border-subtle, #f1aeb5);
        background: var(--bs-danger-bg-subtle, #f8d7da);
    }

    .kb-stat.is-warn .kb-stat-value {
        color: var(--bs-danger-text-emphasis, #842029);
    }

    /* ===== 4. Filter ===== */
    .kb-filter {
        display: flex;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 0.5rem 0.75rem;
        margin-bottom: 1rem;
        padding: 0.75rem 1rem;
        background: var(--kb-soft);
        border: 1px solid var(--kb-border);
        border-radius: 0.5rem;
    }

    .kb-filter .kb-field {
        margin-bottom: 0;
    }

    .kb-filter-select {
        width: auto;
        min-width: 220px;
    }

    /* ===== 5. Layout ===== */
    .kb-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 1rem;
        align-items: start;
    }

    .kb-main {
        min-width: 0;
    }

    @media (min-width: 992px) {
        .kb-layout {
            grid-template-columns: 340px minmax(0, 1fr);
        }

        .kb-side {
            position: sticky;
            top: 12px;
        }
    }

    /* ===== 6. Banner konteks ===== */
    .kb-banner {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
        background: var(--bs-tertiary-bg, #f8f9fa);
        border: 1px solid var(--kb-border);
        border-radius: 0.5rem;
    }

    .kb-banner-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        flex-shrink: 0;
        border-radius: 0.5rem;
        font-size: 1.125rem;
    }

    .kb-banner-content {
        font-size: var(--kb-fs-body);
        line-height: 1.5;
        min-width: 0;
    }

    /* ===== 7. Tab langkah ===== */
    .kb-tabs {
        display: flex;
        border-bottom: 1px solid var(--kb-border);
        background: var(--kb-soft);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .kb-tabs .nav-item {
        flex: 1;
    }

    .kb-tab {
        width: 100%;
        border: 0;
        border-bottom: 2px solid transparent;
        background: transparent;
        padding: 0.625rem 0.25rem;
        font-size: var(--kb-fs-body);
        font-weight: 500;
        color: var(--kb-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        white-space: nowrap;
    }

    .kb-tab:hover {
        color: var(--kb-text);
    }

    .kb-tab.active {
        background: var(--kb-surface);
        color: var(--kb-text);
        border-bottom-color: var(--bs-primary, #0d6efd);
    }

    .kb-tab:focus-visible {
        outline: 2px solid var(--bs-primary, #0d6efd);
        outline-offset: -2px;
    }

    .kb-step {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.125rem;
        height: 1.125rem;
        border-radius: 50%;
        font-size: var(--kb-fs-micro);
        font-weight: 600;
        background: var(--bs-secondary-bg, #e9ecef);
        color: var(--kb-muted);
    }

    .kb-tab.active .kb-step {
        background: var(--bs-primary, #0d6efd);
        color: #fff;
    }

    /* Badge bernomor di header kartu form */
    .kb-step-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        flex-shrink: 0;
        border-radius: 0.5rem;
        font-size: 1rem;
        color: #fff;
    }

    .kb-pane-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }

    .kb-link-danger {
        border: 0;
        background: none;
        padding: 0;
        font-size: var(--kb-fs-meta);
        color: var(--bs-danger, #dc3545);
        white-space: nowrap;
    }

    .kb-link-danger:hover {
        text-decoration: underline;
    }

    /* ===== 8. Form ===== */
    .kb-field {
        margin-bottom: 1rem;
    }

    .kb-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }

    .kb-label {
        display: block;
        font-size: var(--kb-fs-meta);
        font-weight: 500;
        color: var(--kb-muted);
        margin-bottom: 0.25rem;
    }

    .kb-req {
        color: var(--bs-danger, #dc3545);
    }

    .kb-input {
        font-size: var(--kb-fs-body);
        border-radius: 0.375rem;
        box-shadow: none;
    }

    .kb-input.form-select {
        padding-right: 1.5rem;
    }

    .kb-select {
        font-size: var(--kb-fs-body);
    }

    .kb-search {
        max-width: 220px;
    }

    .kb-switch {
        font-size: var(--kb-fs-body);
        min-height: 0;
    }

    .kb-btn {
        width: 100%;
        font-size: var(--kb-fs-body);
        font-weight: 600;
        border-radius: 0.375rem;
    }

    /* ===== 9. Tabel ===== */
    .kb-table {
        --bs-table-bg: transparent;
        margin: 0;
    }

    .kb-table thead th {
        font-size: var(--kb-fs-title);
        font-weight: 600;
        color: var(--kb-text);
        background: var(--kb-soft);
        padding: 0.5rem 0.75rem;
        border-bottom: 1px solid var(--kb-border);
        white-space: nowrap;
    }

    .kb-table tbody td {
        font-size: var(--kb-fs-body);
        padding: 0.625rem 0.75rem;
        border-bottom: 1px solid var(--kb-border);
        vertical-align: middle;
    }

    .kb-table tbody tr.kb-row-main:hover>td {
        background: var(--kb-soft);
    }

    .kb-row-alloc>td {
        background: var(--kb-soft);
        padding: 0.5rem 0.75rem !important;
    }

    .kb-name {
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.375rem;
        flex-wrap: wrap;
    }

    .kb-sub {
        color: var(--kb-muted);
    }

    .kb-sub-strong {
        font-weight: 500;
    }

    .kb-meta {
        font-size: var(--kb-fs-meta);
        color: var(--kb-muted);
    }

    .kb-amount {
        font-weight: 600;
    }

    .kb-empty {
        text-align: center;
        padding: 2rem 0.75rem !important;
        color: var(--kb-muted);
    }

    /* ===== 10. Badge, chip, tombol kecil ===== */
    .kb-badge {
        display: inline-block;
        padding: 0.125rem 0.5rem;
        border-radius: 0.25rem;
        font-size: var(--kb-fs-meta);
        font-weight: 500;
        line-height: 1.5;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .kb-badge-muted {
        background: var(--bs-secondary-bg, #e9ecef);
        color: var(--kb-muted);
    }

    .kb-badge-blue {
        background: var(--bs-primary-bg-subtle, #cfe2ff);
        color: var(--bs-primary-text-emphasis, #052c65);
    }

    .kb-badge-amber {
        background: var(--bs-warning-bg-subtle, #fff3cd);
        color: var(--bs-warning-text-emphasis, #664d03);
    }

    .kb-badge-green {
        background: var(--bs-success-bg-subtle, #d1e7dd);
        color: var(--bs-success-text-emphasis, #0a3622);
    }

    .kb-badge-red {
        background: var(--bs-danger-bg-subtle, #f8d7da);
        color: var(--bs-danger-text-emphasis, #58151c);
    }

    /* Pill konteks di navigasi (_nav.php) */
    .kb-pill {
        display: inline-flex;
        align-items: center;
        padding: 0.125rem 0.5rem;
        border-radius: 999px;
        font-size: var(--kb-fs-meta);
        font-weight: 600;
        line-height: 1.3;
    }

    .kb-pill-primary {
        background: rgba(var(--bs-primary-rgb), 0.12);
        color: var(--bs-primary);
    }

    .kb-pill-info {
        background: rgba(var(--bs-info-rgb), 0.12);
        color: var(--bs-info);
    }

    .kb-pill-danger {
        background: rgba(var(--bs-danger-rgb), 0.12);
        color: var(--bs-danger);
    }

    .kb-alloc {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.375rem;
    }

    .kb-chip {
        display: inline-flex;
        gap: 0.375rem;
        align-items: baseline;
        padding: 0.125rem 0.5rem;
        border: 1px solid var(--kb-border);
        border-radius: 999px;
        background: var(--kb-surface);
        font-size: var(--kb-fs-meta);
    }

    .kb-chip b {
        font-weight: 600;
    }

    .kb-icon-btn {
        width: 1.75rem;
        height: 1.75rem;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--kb-border);
        border-radius: 0.375rem;
        background: var(--kb-surface);
        color: var(--kb-muted);
    }

    .kb-icon-btn:hover {
        color: var(--bs-primary, #0d6efd);
        border-color: var(--bs-primary, #0d6efd);
    }

    .kb-icon-btn:focus-visible {
        outline: 2px solid var(--bs-primary, #0d6efd);
        outline-offset: 1px;
    }

    .btn-xs {
        padding: 0.25rem 0.5rem;
        font-size: var(--kb-fs-meta);
        font-weight: 500;
        line-height: 1.4;
        border-radius: 0.375rem;
    }

    .fw-medium {
        font-weight: 500 !important;
    }

    /* ===== 11. Responsif ===== */
    @media (max-width: 575.98px) {
        .kb-row {
            grid-template-columns: 1fr;
        }

        .kb-search,
        .kb-filter-select {
            max-width: 100%;
            min-width: 0;
        }
    }
</style>
