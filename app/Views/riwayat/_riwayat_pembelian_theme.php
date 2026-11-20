<?php

/**
 * Tema halaman Riwayat Pembelian (/riwayat_pembelian).
 *
 * Semua selector berada di bawah `.rb-page` dan modalnya memakai `.rb-modal`
 * agar tidak bocor ke modul lain. Warna diturunkan dari token tema aplikasi;
 * satu aksen biru-riwayat (#0b5f8a, gelap #7cc4f0) hanya untuk aksi utama,
 * fokus, dan state aktif.
 *
 * Kontras teks kecil dijaga >= 4.5:1 di terang maupun gelap:
 *   --rb-muted  #5b6672 di putih (5.85:1), #8fa0b8 di gelap
 *   --rb-ink    #3d4a58 di putih, var(--bs-body-color) di gelap
 *   tombol aksen #0b5f8a dengan teks putih (7.0:1)
 */
?>

<style>
    /* ===== Token ===== */
    /* Modal dirender di dalam body, di luar `.rb-page`, jadi token diduplikasi. */
    .rb-page,
    .rb-modal {
        --rb-surface: var(--bs-card-bg, #fff);
        --rb-subtle: var(--bs-tertiary-bg, #f6f8fb);
        --rb-rule: var(--bs-border-color, #e6ecf1);
        --rb-ink: #3d4a58;
        --rb-strong: #1d2939;
        --rb-muted: #5b6672;
        --rb-accent-ink: #0b5f8a;
        --rb-accent-solid: #0b5f8a;
        --rb-accent-hover: #09506f;
        --rb-accent-soft: rgba(11, 95, 138, .08);
        --rb-warn-ink: #8a5300;
        --rb-bad-ink: #b02a37;
        --rb-bad-soft: rgba(176, 42, 55, .10);
        --rb-control-line: #7d8a99;
        --rb-radius: 16px;
        --rb-radius-sm: 10px;
        --rb-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 18px 40px -32px rgba(16, 24, 40, .35);
    }

    html[data-bs-theme="dark"] .rb-page,
    html[data-bs-theme="dark"] .rb-modal {
        --rb-surface: var(--bs-card-bg, #1f2937);
        --rb-subtle: rgba(255, 255, 255, .04);
        --rb-ink: var(--bs-body-color, #cfd8e3);
        --rb-strong: #f2f6fb;
        --rb-muted: #8fa0b8;
        --rb-accent-ink: #7cc4f0;
        --rb-accent-solid: #0f6f9e;
        --rb-accent-hover: #1280b5;
        --rb-accent-soft: rgba(124, 196, 240, .12);
        --rb-warn-ink: #ffcf6e;
        --rb-bad-ink: #ff9aa2;
        --rb-bad-soft: rgba(255, 154, 162, .12);
        --rb-control-line: #8fa0b8;
        --rb-shadow: none;
    }

    /* ===== Halaman ===== */
    .rb-page {
        display: flex;
        flex-direction: column;
        gap: 16px;
        color: var(--rb-ink);
        color-scheme: light;
        font-size: .875rem;
    }

    html[data-bs-theme="dark"] .rb-page {
        color-scheme: dark;
    }

    .rb-page h1,
    .rb-page h2,
    .rb-modal h5 {
        color: var(--rb-strong);
    }

    .rb-page :focus-visible,
    .rb-modal :focus-visible {
        outline: 2px solid var(--rb-accent-solid);
        outline-offset: 2px;
    }

    /* ===== Kepala halaman ===== */
    .rb-page .rb-hero {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 20px;
        background: var(--rb-surface);
        border: 1px solid var(--rb-rule);
        border-radius: var(--rb-radius);
        box-shadow: var(--rb-shadow);
    }

    .rb-page .rb-hero > div:first-child {
        min-width: 0;
        flex: 1 1 420px;
    }

    .rb-page .rb-crumb {
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 0 0 8px;
        padding: 0;
        list-style: none;
        color: var(--rb-muted);
        font-size: .72rem;
    }

    .rb-page .rb-crumb a {
        color: inherit;
        text-decoration: none;
    }

    .rb-page .rb-crumb a:hover {
        color: var(--rb-accent-ink);
        text-decoration: underline;
    }

    .rb-page .rb-crumb [aria-current="page"] {
        color: var(--rb-ink);
        font-weight: 600;
    }

    .rb-page .rb-hero h1 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -.02em;
        line-height: 1.15;
    }

    .rb-page .rb-hero p {
        max-width: 68ch;
        margin: 6px 0 0;
        color: var(--rb-muted);
        font-size: .83rem;
        line-height: 1.55;
    }

    .rb-page .rb-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .rb-page .rb-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 28px;
        padding: 4px 10px;
        color: var(--rb-muted);
        background: var(--rb-subtle);
        border: 1px solid var(--rb-rule);
        border-radius: 999px;
        font-size: .72rem;
        line-height: 1.2;
        white-space: nowrap;
    }

    .rb-page .rb-chip iconify-icon {
        flex: none;
    }

    .rb-page .rb-chip strong {
        color: var(--rb-strong);
        font-variant-numeric: tabular-nums;
        font-weight: 700;
    }

    .rb-page .rb-chip--accent {
        color: var(--rb-accent-ink);
        background: var(--rb-accent-soft);
        border-color: transparent;
    }

    .rb-page .rb-chip--accent strong {
        color: var(--rb-accent-ink);
    }

    /* ===== Toolbar filter ===== */
    .rb-page .rb-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 10px 12px;
        padding: 14px 16px;
        background: var(--rb-surface);
        border: 1px solid var(--rb-rule);
        border-radius: var(--rb-radius);
        box-shadow: var(--rb-shadow);
    }

    .rb-page .rb-search {
        position: relative;
        display: flex;
        align-items: center;
        flex: 1 1 260px;
        min-width: 190px;
    }

    .rb-page .rb-search iconify-icon {
        position: absolute;
        left: 12px;
        color: var(--rb-muted);
        pointer-events: none;
    }

    .rb-page .rb-search input {
        width: 100%;
        height: 38px;
        padding: 0 12px 0 36px;
        color: var(--rb-ink);
        background: var(--rb-surface);
        border: 1px solid var(--rb-control-line);
        border-radius: var(--rb-radius-sm);
        font-size: .8rem;
    }

    .rb-page .rb-search input::placeholder {
        color: var(--rb-muted);
    }

    .rb-page .rb-search input:focus {
        border-color: var(--rb-accent-solid);
        box-shadow: 0 0 0 2px var(--rb-accent-soft);
        outline: none;
    }

    .rb-page .rb-field {
        display: flex;
        flex-direction: column;
        gap: 5px;
        min-width: 0;
    }

    .rb-page .rb-field > label {
        color: var(--rb-muted);
        font-size: .65rem;
        font-weight: 700;
        letter-spacing: .06em;
        line-height: 1;
        text-transform: uppercase;
    }

    .rb-page .rb-control {
        height: 38px;
        max-width: 100%;
        padding: 0 10px;
        color: var(--rb-ink);
        background: var(--rb-surface);
        border: 1px solid var(--rb-control-line);
        border-radius: var(--rb-radius-sm);
        font-size: .8rem;
        font-weight: 500;
    }

    .rb-page .rb-control:disabled {
        color: var(--rb-muted);
        background: var(--rb-subtle);
        opacity: 1;
    }

    .rb-page .rb-control:focus {
        border-color: var(--rb-accent-solid);
        box-shadow: 0 0 0 2px var(--rb-accent-soft);
        outline: none;
    }

    .rb-page .rb-range {
        display: flex;
        align-items: flex-end;
        gap: 6px;
    }

    .rb-page .rb-range .rb-control {
        width: 148px;
    }

    .rb-page .rb-range__dash {
        padding-bottom: 10px;
        color: var(--rb-muted);
        font-size: .8rem;
        line-height: 1;
    }

    .rb-page .rb-toolbar__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-left: auto;
    }

    .rb-page .rb-btn,
    .rb-modal .rb-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        height: 38px;
        padding: 0 13px;
        color: var(--rb-ink);
        background: transparent;
        border: 1px solid var(--rb-rule);
        border-radius: var(--rb-radius-sm);
        font-size: .78rem;
        font-weight: 600;
        line-height: 1;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }

    .rb-page .rb-btn:hover,
    .rb-modal .rb-btn:hover {
        color: var(--rb-accent-ink);
        background: var(--rb-accent-soft);
        border-color: var(--rb-accent-ink);
    }

    .rb-page .rb-btn--accent {
        color: #fff;
        background: var(--rb-accent-solid);
        border-color: var(--rb-accent-solid);
    }

    .rb-page .rb-btn--accent:hover {
        color: #fff;
        background: var(--rb-accent-hover);
        border-color: var(--rb-accent-hover);
    }

    .rb-page .rb-scope-note {
        display: flex;
        align-items: center;
        gap: 6px;
        width: 100%;
        margin: 0;
        color: var(--rb-muted);
        font-size: .72rem;
        line-height: 1.4;
    }

    /* ===== Panel tabel ===== */
    .rb-page .rb-card {
        position: relative;
        overflow: hidden;
        background: var(--rb-surface);
        border: 1px solid var(--rb-rule);
        border-radius: var(--rb-radius);
        box-shadow: var(--rb-shadow);
    }

    .rb-page .rb-card__head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 8px 16px;
        padding: 16px 20px 12px;
    }

    .rb-page .rb-card__head h2 {
        margin: 0;
        font-size: .95rem;
        font-weight: 700;
        letter-spacing: -.015em;
    }

    .rb-page .rb-card__head p {
        margin: 4px 0 0;
        color: var(--rb-muted);
        font-size: .73rem;
        line-height: 1.45;
    }

    .rb-page .rb-table-wrap {
        max-width: 100%;
        overflow-x: auto;
        scrollbar-width: thin;
    }

    .rb-page .rb-table,
    .rb-modal .rb-table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
        color: var(--rb-ink);
        font-size: .8rem;
    }

    /* Di layar sempit tabel digeser, bukan dijejalkan sampai teks terlipat. */
    .rb-page .rb-table {
        min-width: 940px;
    }

    .rb-page .rb-table > thead > tr > th:nth-child(5),
    .rb-page .rb-table > tbody > tr > td:nth-child(5) {
        min-width: 160px;
    }

    .rb-modal .rb-table {
        min-width: 620px;
    }

    .rb-page .rb-table > thead > tr > th,
    .rb-modal .rb-table > thead > tr > th {
        padding: 10px 14px;
        color: var(--rb-muted);
        background: var(--rb-subtle);
        border-top: 1px solid var(--rb-rule);
        border-bottom: 1px solid var(--rb-rule);
        font-size: .65rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .rb-page .rb-table > tbody > tr > td,
    .rb-modal .rb-table > tbody > tr > td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--rb-rule);
        vertical-align: middle;
    }

    .rb-page .rb-table > tbody > tr:last-child > td,
    .rb-modal .rb-table > tbody > tr:last-child > td {
        border-bottom: 0;
    }

    .rb-page .rb-table > tbody > tr:hover > td {
        background: var(--rb-accent-soft);
    }

    .rb-page .rb-table > thead > tr > th:first-child,
    .rb-page .rb-table > tbody > tr > td:first-child,
    .rb-modal .rb-table > thead > tr > th:first-child,
    .rb-modal .rb-table > tbody > tr > td:first-child {
        padding-left: 20px;
    }

    .rb-page .rb-table > thead > tr > th:last-child,
    .rb-page .rb-table > tbody > tr > td:last-child,
    .rb-modal .rb-table > thead > tr > th:last-child,
    .rb-modal .rb-table > tbody > tr > td:last-child {
        padding-right: 20px;
    }

    .rb-page .rb-table .is-num,
    .rb-modal .rb-table .is-num,
    .rb-page .rb-table th.is-num,
    .rb-modal .rb-table th.is-num {
        text-align: right;
    }

    .rb-modal .rb-table > tbody > tr > td.is-num,
    .rb-modal .rb-table > thead > tr > th.is-num {
        white-space: nowrap;
    }

    /* Penanda urut DataTables tanpa aset gambar. */
    .rb-page .rb-table > thead > tr > th[class*="sorting"] {
        position: relative;
        padding-right: 30px;
        cursor: pointer;
    }

    .rb-page .rb-table > thead > tr > th[class*="sorting"]::after {
        position: absolute;
        top: 50%;
        right: 12px;
        color: var(--rb-muted);
        font-size: .72rem;
        line-height: 1;
        content: "\2195";
        opacity: .5;
        transform: translateY(-50%);
    }

    .rb-page .rb-table > thead > tr > th.sorting_asc::after {
        color: var(--rb-accent-ink);
        content: "\2191";
        opacity: 1;
    }

    .rb-page .rb-table > thead > tr > th.sorting_desc::after {
        color: var(--rb-accent-ink);
        content: "\2193";
        opacity: 1;
    }

    .rb-page .rb-table > thead > tr > th.sorting_disabled {
        cursor: default;
    }

    .rb-page .rb-table > thead > tr > th.sorting_disabled::after {
        content: none;
    }

    .rb-page .rb-table .is-center,
    .rb-modal .rb-table .is-center {
        text-align: center;
    }

    .rb-page .rb-table .is-action {
        width: 1%;
        white-space: nowrap;
    }

    /* ===== Sel ===== */
    .rb-page .rb-note,
    .rb-modal .rb-mono {
        color: var(--rb-strong);
        font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
        font-size: .78rem;
        font-weight: 600;
        letter-spacing: .01em;
        white-space: nowrap;
    }

    .rb-page .rb-strong {
        display: block;
        color: var(--rb-ink);
        font-weight: 600;
        line-height: 1.35;
    }

    .rb-page .rb-nowrap {
        white-space: nowrap;
    }

    .rb-page .rb-sub {
        display: block;
        margin-top: 2px;
        color: var(--rb-muted);
        font-size: .72rem;
        line-height: 1.35;
    }

    .rb-modal .rb-sub {
        display: block;
        margin-top: 2px;
        color: var(--rb-muted);
        font-size: .72rem;
        line-height: 1.35;
    }

    .rb-page .rb-sub--warn {
        color: var(--rb-warn-ink);
        font-variant-numeric: tabular-nums;
        font-weight: 600;
        white-space: nowrap;
    }

    .rb-page .rb-sub-inline,
    .rb-modal .rb-sub-inline {
        margin-left: 3px;
        color: var(--rb-muted);
        font-size: .72rem;
        font-weight: 400;
    }

    .rb-page .rb-unit {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 8px;
        color: var(--rb-ink);
        background: var(--rb-subtle);
        border: 1px solid var(--rb-rule);
        border-radius: 999px;
        font-size: .72rem;
        line-height: 1.2;
        white-space: nowrap;
    }

    .rb-page .rb-money,
    .rb-modal .rb-money {
        display: block;
        color: var(--rb-strong);
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        text-align: right;
        white-space: nowrap;
    }

    .rb-page .rb-btn-detail {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        height: 32px;
        padding: 0 10px;
        color: var(--rb-ink);
        background: transparent;
        border: 1px solid var(--rb-rule);
        border-radius: 8px;
        font-size: .72rem;
        font-weight: 600;
        line-height: 1;
        cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }

    .rb-page .rb-btn-detail:hover {
        color: var(--rb-accent-ink);
        background: var(--rb-accent-soft);
        border-color: var(--rb-accent-ink);
    }

    /* ===== DataTables ===== */
    .rb-page .dataTables_wrapper {
        position: static;
    }

    .rb-page .dataTables_processing {
        position: absolute;
        top: 10px;
        right: 16px;
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 0;
        padding: 4px 10px;
        color: var(--rb-muted);
        background: var(--rb-surface);
        border: 1px solid var(--rb-rule);
        border-radius: 999px;
        font-size: .7rem;
        z-index: 3;
    }

    .rb-page .rb-dt-foot {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px 16px;
        padding: 12px 20px;
        border-top: 1px solid var(--rb-rule);
    }

    .rb-page .dataTables_info {
        padding: 0;
        color: var(--rb-muted);
        font-size: .72rem;
        line-height: 1.4;
    }

    .rb-page .dataTables_paginate {
        padding: 0;
        margin: 0 0 0 auto;
        display: flex;
        justify-content: flex-end;
    }

    .rb-page .dataTables_paginate ul.pagination {
        flex-wrap: wrap;
        gap: 4px;
        margin: 0;
    }

    .rb-page .dataTables_paginate .page-link,
    .rb-page .dataTables_paginate .paginate_button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        height: 30px;
        padding: 0 8px;
        color: var(--rb-ink);
        background: transparent;
        border: 1px solid var(--rb-rule);
        border-radius: 8px;
        font-size: .72rem;
        font-weight: 600;
        line-height: 1;
        text-decoration: none;
        cursor: pointer;
    }

    .rb-page .dataTables_paginate .page-link:hover,
    .rb-page .dataTables_paginate .paginate_button:hover {
        color: var(--rb-accent-ink);
        background: var(--rb-accent-soft);
        border-color: var(--rb-accent-ink);
    }

    .rb-page .dataTables_paginate .page-item.active .page-link,
    .rb-page .dataTables_paginate .paginate_button.current {
        color: #fff;
        background: var(--rb-accent-solid);
        border-color: var(--rb-accent-solid);
        cursor: default;
    }

    .rb-page .dataTables_paginate .page-item.disabled .page-link,
    .rb-page .dataTables_paginate .paginate_button.disabled {
        color: var(--rb-muted);
        background: var(--rb-subtle);
        border-color: var(--rb-rule);
        cursor: not-allowed;
    }

    .rb-page .dataTables_empty {
        padding: 0 !important;
        border-bottom: 0;
    }

    /* ===== State kosong & loading ===== */
    .rb-page .rb-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        padding: 32px 16px;
        text-align: center;
    }

    .rb-page .rb-empty__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        margin-bottom: 4px;
        color: var(--rb-accent-ink);
        background: var(--rb-accent-soft);
        border-radius: 14px;
    }

    .rb-page .rb-empty__title {
        color: var(--rb-ink);
        font-size: .85rem;
        font-weight: 600;
    }

    .rb-page .rb-empty__hint {
        color: var(--rb-muted);
        font-size: .74rem;
        line-height: 1.45;
    }

    .rb-loading-block {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 40px 16px;
        color: var(--rb-muted);
        font-size: .78rem;
    }

    .rb-spinner {
        display: inline-block;
        flex: none;
        width: 16px;
        height: 16px;
        border: 2px solid var(--rb-accent-soft);
        border-top-color: var(--rb-accent-solid);
        border-radius: 50%;
        animation: rb-spin .7s linear infinite;
    }

    @keyframes rb-spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* ===== Modal detail ===== */
    .rb-modal {
        color: var(--rb-ink);
        background: var(--rb-surface);
        border: 1px solid var(--rb-rule);
        border-radius: 18px;
        box-shadow: var(--rb-shadow);
    }

    .rb-modal .modal-header {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 18px 20px;
        border-bottom: 1px solid var(--rb-rule);
    }

    .rb-modal__title {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .rb-modal__icon {
        display: inline-flex;
        flex: none;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        color: var(--rb-accent-ink);
        background: var(--rb-accent-soft);
        border-radius: 12px;
    }

    .rb-modal h5 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: -.01em;
    }

    .rb-modal__batch {
        margin: 3px 0 0;
        color: var(--rb-muted);
        font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
        font-size: .75rem;
        word-break: break-all;
    }

    .rb-modal .modal-body {
        padding: 20px 20px 0;
    }

    .rb-modal .modal-footer {
        gap: 8px;
        padding: 14px 20px;
        border-top: 1px solid var(--rb-rule);
    }

    .rb-modal .rb-alert {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin: 0;
        padding: 12px 14px;
        color: var(--rb-bad-ink);
        background: var(--rb-bad-soft);
        border: 1px solid var(--rb-bad-ink);
        border-radius: var(--rb-radius-sm);
        font-size: .78rem;
        line-height: 1.5;
    }

    .rb-modal .rb-meta {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0;
        margin: 0 0 18px;
        overflow: hidden;
        border: 1px solid var(--rb-rule);
        border-radius: 12px;
    }

    .rb-modal .rb-meta__cell {
        padding: 11px 14px;
        border-right: 1px solid var(--rb-rule);
    }

    .rb-modal .rb-meta__cell:last-child {
        border-right: 0;
    }

    .rb-modal .rb-meta dt {
        margin: 0 0 3px;
        color: var(--rb-muted);
        font-size: .65rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .rb-modal .rb-meta dd {
        margin: 0;
        color: var(--rb-strong);
        font-size: .82rem;
        font-weight: 600;
        line-height: 1.35;
    }

    .rb-modal .rb-table-wrap {
        overflow-x: auto;
        border: 1px solid var(--rb-rule);
        border-radius: 12px;
    }

    .rb-modal .rb-table > thead > tr > th {
        border-top: 0;
    }

    /* Ringkasan uang menempel di kaki modal: rincian boleh tergulung, angka
       akhir tetap terbaca tanpa perlu scroll ke bawah. */
    .rb-modal .rb-summary {
        position: sticky;
        bottom: 0;
        z-index: 2;
        margin: 16px -20px 0;
        padding: 10px 20px 16px;
        background: var(--rb-surface);
        border-top: 1px solid var(--rb-rule);
        border-radius: 0;
    }

    .rb-modal .rb-totals {
        width: min(340px, 100%);
        margin: 0 0 0 auto;
        padding: 0;
        border-top: 0;
    }

    .rb-modal .rb-totals__row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 16px;
        padding: 7px 0;
        color: var(--rb-muted);
        font-size: .78rem;
    }

    .rb-modal .rb-totals__row > span:last-child {
        color: var(--rb-ink);
        font-variant-numeric: tabular-nums;
        font-weight: 600;
    }

    .rb-modal .rb-totals__row--grand {
        margin-top: 4px;
        padding-top: 10px;
        border-top: 1px solid var(--rb-rule);
        color: var(--rb-ink);
        font-size: .82rem;
        font-weight: 600;
    }

    .rb-modal .rb-totals__row--grand > span:last-child {
        color: var(--rb-strong);
        font-size: 1.05rem;
        font-weight: 700;
    }

    .rb-modal .rb-totals__row--warn > span:last-child {
        color: var(--rb-warn-ink);
    }

    /* ===== Responsif ===== */
    @media (max-width: 991.98px) {
        .rb-page .rb-hero {
            align-items: flex-start;
        }

        .rb-page .rb-range {
            flex: 1 1 240px;
        }

        .rb-page .rb-range .rb-control {
            width: 100%;
            min-width: 118px;
        }

        .rb-modal .rb-meta {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .rb-modal .rb-meta__cell:nth-child(2n) {
            border-right: 0;
        }

        .rb-modal .rb-meta__cell:nth-child(n+3) {
            border-top: 1px solid var(--rb-rule);
        }
    }

    @media (max-width: 575.98px) {
        .rb-page .rb-hero,
        .rb-page .rb-toolbar {
            padding: 14px;
        }

        .rb-page .rb-hero h1 {
            font-size: 1.3rem;
        }

        .rb-page .rb-toolbar {
            align-items: stretch;
        }

        .rb-page .rb-search,
        .rb-page .rb-field,
        .rb-page .rb-range {
            flex: 1 1 100%;
        }

        .rb-page .rb-range {
            flex-wrap: wrap;
        }

        .rb-page .rb-range__dash {
            display: none;
        }

        .rb-page .rb-toolbar__actions {
            width: 100%;
        }

        .rb-page .rb-toolbar__actions .rb-btn,
        .rb-page .rb-toolbar__actions form {
            flex: 1 1 0;
        }

        .rb-page .rb-toolbar__actions form .rb-btn {
            width: 100%;
        }

        .rb-page .rb-card__head,
        .rb-page .rb-dt-foot {
            padding: 14px;
        }

        .rb-page .rb-table > thead > tr > th,
        .rb-page .rb-table > tbody > tr > td {
            padding: 10px 12px;
        }

        .rb-page .rb-table > thead > tr > th:first-child,
        .rb-page .rb-table > tbody > tr > td:first-child {
            padding-left: 14px;
        }

        .rb-page .rb-table > thead > tr > th:last-child,
        .rb-page .rb-table > tbody > tr > td:last-child {
            padding-right: 14px;
        }

        .rb-page .dataTables_paginate {
            width: 100%;
            justify-content: flex-start;
        }

        .rb-modal .modal-body {
            padding: 14px 14px 0;
        }

        .rb-modal .rb-summary {
            margin: 14px -14px 0;
            padding: 10px 14px 14px;
        }

        .rb-modal .rb-totals {
            width: 100%;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .rb-page *,
        .rb-modal * {
            transition: none !important;
        }

        .rb-spinner {
            animation: none;
            border-top-color: var(--rb-accent-soft);
            border-left-color: var(--rb-accent-solid);
        }
    }
</style>
