<?php
/**
 * Style halaman Approval Izin (/approval_presensi).
 *
 * Mengikuti bahasa visual Riwayat Absen (riwayat/semua_presensi.php): token
 * warna dari template, hero + papan ringkasan + kartu filter + kartu tabel.
 * Semua aturan di-scope ke .ap-page supaya tidak bocor ke halaman lain.
 */
?>

<style>
    .ap-page {
        --ap-surface: var(--bs-card-bg, #fff);
        --ap-subtle: var(--bs-tertiary-bg, #f6f8fb);
        --ap-rule: var(--bs-border-color, #e6ecf1);
        --ap-muted: #5b6672;
        --ap-ink: #3d4a58;
        --ap-accent-ink: #0b5f8a;
        --ap-accent-solid: #0b5f8a;
        --ap-accent-soft: rgba(11, 95, 138, .08);
        --ap-ok-ink: #0f6b3e;
        --ap-ok-solid: #0f6b3e;
        --ap-ok-soft: rgba(15, 122, 70, .10);
        --ap-warn-ink: #8a5300;
        --ap-warn-soft: rgba(192, 118, 0, .13);
        --ap-bad-ink: #b02a37;
        --ap-bad-soft: rgba(176, 42, 55, .10);
        --ap-radius: 16px;
        --ap-radius-sm: 10px;
        --ap-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 18px 40px -32px rgba(16, 24, 40, .35);
        display: flex;
        flex-direction: column;
        gap: 16px;
        color: var(--ap-ink);
    }

    html[data-bs-theme="dark"] .ap-page {
        --ap-surface: var(--bs-card-bg, #1f2937);
        --ap-subtle: rgba(255, 255, 255, .04);
        --ap-muted: #8fa0b8;
        --ap-ink: var(--bs-body-color, #cfd8e3);
        --ap-accent-ink: #7cc4f0;
        --ap-accent-soft: rgba(124, 196, 240, .12);
        --ap-ok-ink: #6fdd9d;
        --ap-ok-soft: rgba(111, 221, 157, .12);
        --ap-warn-ink: #ffcf6e;
        --ap-warn-soft: rgba(255, 207, 110, .12);
        --ap-bad-ink: #ff9aa2;
        --ap-bad-soft: rgba(255, 154, 162, .12);
        --ap-shadow: none;
    }

    .ap-page h1,
    .ap-page h2 {
        color: var(--ap-ink);
    }

    /* ===== Hero ===== */
    .ap-page .ap-hero {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        padding: 4px 2px 0;
    }

    .ap-page .ap-crumb {
        margin-bottom: 10px;
        color: var(--ap-muted);
        font-size: .72rem;
    }

    .ap-page .ap-crumb ol {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .ap-page .ap-crumb li {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .ap-page .ap-crumb li + li::before {
        content: "/";
        color: var(--ap-rule);
    }

    .ap-page .ap-crumb a {
        color: var(--ap-muted);
        text-decoration: none;
    }

    .ap-page .ap-crumb a:hover {
        color: var(--ap-accent-ink);
    }

    .ap-page .ap-hero h1 {
        margin: 0 0 6px;
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1.1;
        letter-spacing: -.035em;
    }

    .ap-page .ap-hero p {
        max-width: 62ch;
        margin: 0;
        color: var(--ap-muted);
        font-size: .86rem;
        line-height: 1.55;
        text-wrap: pretty;
    }

    .ap-page .ap-hero__aside {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    /* ===== Tombol & kontrol ===== */
    .ap-page .ap-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 38px;
        padding: 8px 13px;
        border: 1px solid transparent;
        border-radius: var(--ap-radius-sm);
        font-family: inherit;
        font-size: .78rem;
        font-weight: 650;
        line-height: 1.2;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease;
    }

    .ap-page .ap-btn--primary {
        color: #fff;
        background: var(--ap-accent-solid);
        border-color: var(--ap-accent-solid);
    }

    .ap-page .ap-btn--primary:hover,
    .ap-page .ap-btn--primary:focus {
        color: #fff;
        background: #09506f;
        border-color: #09506f;
    }

    .ap-page .ap-btn--ghost {
        color: var(--ap-ink);
        background: transparent;
        border-color: var(--ap-rule);
    }

    .ap-page .ap-btn--ghost:hover,
    .ap-page .ap-btn--ghost:focus {
        color: var(--ap-accent-ink);
        border-color: var(--ap-accent-ink);
        background: var(--ap-accent-soft);
    }

    .ap-page .ap-btn--ok {
        color: #fff;
        background: var(--ap-ok-solid);
        border-color: var(--ap-ok-solid);
    }

    .ap-page .ap-btn--ok:hover,
    .ap-page .ap-btn--ok:focus {
        color: #fff;
        background: #0b5531;
        border-color: #0b5531;
    }

    .ap-page .ap-btn--bad {
        color: var(--ap-bad-ink);
        background: transparent;
        border-color: var(--ap-rule);
    }

    .ap-page .ap-btn--bad:hover,
    .ap-page .ap-btn--bad:focus {
        color: var(--ap-bad-ink);
        background: var(--ap-bad-soft);
        border-color: var(--ap-bad-ink);
    }

    .ap-page .ap-control {
        width: 100%;
        height: 38px;
        padding: 7px 10px;
        color: var(--ap-ink);
        background: var(--ap-surface);
        border: 1px solid var(--ap-rule);
        border-radius: var(--ap-radius-sm);
        font-family: inherit;
        font-size: .8rem;
    }

    html[data-bs-theme="dark"] .ap-page .ap-control {
        color-scheme: dark;
    }

    .ap-page .ap-control:focus {
        outline: none;
        border-color: var(--ap-accent-ink);
        box-shadow: 0 0 0 3px var(--ap-accent-soft);
    }

    .ap-page a:focus-visible,
    .ap-page button:focus-visible,
    .ap-page summary:focus-visible {
        outline: 2px solid var(--ap-accent-ink);
        outline-offset: 2px;
    }

    /* ===== Ringkasan ===== */
    .ap-page .ap-board {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }

    .ap-page .ap-board__cell {
        min-width: 0;
        padding: 14px 16px;
        background: var(--ap-surface);
        border: 1px solid var(--ap-rule);
        border-radius: var(--ap-radius-sm);
    }

    .ap-page .ap-board__label {
        margin-bottom: 6px;
        color: var(--ap-muted);
        font-size: .66rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .ap-page .ap-board__value {
        color: var(--ap-ink);
        font-size: 1.5rem;
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        letter-spacing: -.03em;
        line-height: 1.05;
    }

    .ap-page .ap-board__value.is-ok {
        color: var(--ap-ok-ink);
    }

    .ap-page .ap-board__value.is-warn {
        color: var(--ap-warn-ink);
    }

    .ap-page .ap-board__value.is-bad {
        color: var(--ap-bad-ink);
    }

    /* ===== Kartu & filter ===== */
    .ap-page .ap-card {
        background: var(--ap-surface);
        border: 1px solid var(--ap-rule);
        border-radius: var(--ap-radius);
        box-shadow: var(--ap-shadow);
    }

    .ap-page .ap-filter {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 12px 16px;
        padding: 14px 18px;
    }

    .ap-page .ap-filter__fields {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 12px;
    }

    .ap-page .ap-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
    }

    .ap-page .ap-field label {
        color: var(--ap-muted);
        font-size: .66rem;
        font-weight: 700;
        letter-spacing: .055em;
        text-transform: uppercase;
    }

    .ap-page .ap-filter__hint {
        display: flex;
        flex: 1 1 240px;
        gap: 8px;
        align-items: center;
        justify-content: flex-end;
        margin: 0;
        color: var(--ap-muted);
        font-size: .73rem;
        line-height: 1.45;
    }

    .ap-page .ap-filter__hint iconify-icon {
        flex: none;
        font-size: 16px;
    }

    /* ===== Tabel ===== */
    .ap-page .ap-table-card {
        overflow: hidden;
    }

    .ap-page .ap-table-card__head {
        padding: 16px 18px 12px;
    }

    .ap-page .ap-table-card__head h2 {
        margin: 0;
        font-size: .95rem;
        font-weight: 700;
        letter-spacing: -.015em;
    }

    .ap-page .ap-table-card__head p {
        margin: 4px 0 0;
        color: var(--ap-muted);
        font-size: .73rem;
        line-height: 1.45;
    }

    .ap-page .ap-table-wrap {
        overflow-x: auto;
        scrollbar-width: thin;
    }

    .ap-page .ap-table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
        color: var(--ap-ink);
        font-size: .8rem;
    }

    .ap-page .ap-table > thead > tr > th {
        padding: 10px 14px;
        color: var(--ap-muted);
        background: var(--ap-subtle);
        border-top: 1px solid var(--ap-rule);
        border-bottom: 1px solid var(--ap-rule);
        font-size: .65rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .ap-page .ap-table > tbody > tr > td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--ap-rule);
        vertical-align: middle;
    }

    .ap-page .ap-table > tbody > tr:last-child > td {
        border-bottom: 0;
    }

    .ap-page .ap-table > tbody > tr:hover > td {
        background: var(--ap-subtle);
    }

    .ap-page .ap-emp {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .ap-page .ap-emp__name {
        color: var(--ap-ink);
        font-weight: 650;
    }

    .ap-page .ap-emp__unit {
        color: var(--ap-muted);
        font-size: .69rem;
    }

    .ap-page .ap-time {
        font-variant-numeric: tabular-nums;
        font-weight: 600;
        white-space: nowrap;
    }

    .ap-page .ap-time__note {
        display: block;
        margin-top: 2px;
        color: var(--ap-muted);
        font-size: .68rem;
        font-weight: 500;
        white-space: nowrap;
    }

    .ap-page .ap-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: .69rem;
        font-weight: 650;
        line-height: 1.25;
        white-space: nowrap;
    }

    .ap-page .ap-tag::before {
        content: "";
        flex: none;
        width: 6px;
        height: 6px;
        background: currentColor;
        border-radius: 50%;
        opacity: .9;
    }

    .ap-page .ap-tag--ok {
        color: var(--ap-ok-ink);
        background: var(--ap-ok-soft);
    }

    .ap-page .ap-tag--warn {
        color: var(--ap-warn-ink);
        background: var(--ap-warn-soft);
    }

    .ap-page .ap-tag--bad {
        color: var(--ap-bad-ink);
        background: var(--ap-bad-soft);
    }

    .ap-page .ap-tag--accent {
        color: var(--ap-accent-ink);
        background: var(--ap-accent-soft);
    }

    .ap-page .ap-tag--muted {
        color: var(--ap-muted);
        background: var(--ap-subtle);
    }

    .ap-page .ap-reason {
        display: -webkit-box;
        overflow: hidden;
        max-width: 40ch;
        color: var(--ap-ink);
        line-height: 1.45;
        overflow-wrap: anywhere;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .ap-page .ap-link {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-top: 4px;
        color: var(--ap-accent-ink);
        font-size: .72rem;
        font-weight: 650;
        text-decoration: none;
    }

    .ap-page .ap-link:hover {
        text-decoration: underline;
    }

    /* ===== Keputusan ===== */
    .ap-page .ap-form {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        min-width: 0;
    }

    .ap-page .ap-input {
        flex: 1 1 140px;
        max-width: 240px;
        min-width: 0;
        height: 34px;
        padding: 6px 9px;
        color: var(--ap-ink);
        background: var(--ap-surface);
        border: 1px solid var(--ap-rule);
        border-radius: 8px;
        font-family: inherit;
        font-size: .76rem;
    }

    .ap-page .ap-input::placeholder {
        color: var(--ap-muted);
        opacity: .85;
    }

    .ap-page .ap-input:focus {
        outline: none;
        border-color: var(--ap-accent-ink);
        box-shadow: 0 0 0 3px var(--ap-accent-soft);
    }

    .ap-page .ap-form__actions {
        display: flex;
        gap: 6px;
    }

    .ap-page .ap-form .ap-btn {
        min-height: 34px;
        padding: 6px 11px;
        font-size: .74rem;
    }

    .ap-page .ap-reply {
        margin: 0 0 6px;
        color: var(--ap-muted);
        font-size: .75rem;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    .ap-page .ap-reply__label {
        display: block;
        margin-bottom: 1px;
        font-size: .62rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .ap-page .ap-more {
        font-size: .72rem;
    }

    .ap-page .ap-more > summary {
        color: var(--ap-accent-ink);
        font-weight: 650;
        cursor: pointer;
    }

    .ap-page .ap-more > summary:hover {
        text-decoration: underline;
    }

    .ap-page .ap-more[open] > summary {
        margin-bottom: 6px;
    }

    /* ===== Kontrol DataTables ===== */
    .ap-page .ap-dt-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 18px;
        border-top: 1px solid var(--ap-rule);
    }

    .ap-page .dataTables_length label {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        color: var(--ap-muted);
        font-size: .73rem;
        font-weight: 600;
    }

    .ap-page .dataTables_length select {
        height: 32px;
        padding: 4px 8px;
        color: var(--ap-ink);
        background: var(--ap-surface);
        border: 1px solid var(--ap-rule);
        border-radius: 8px;
        font-family: inherit;
        font-size: .75rem;
    }

    .ap-page .dataTables_filter label {
        margin: 0;
    }

    /* Selector harus lebih spesifik dari aturan dataTables.bootstrap5.css. */
    .ap-page .ap-dt-toolbar .dataTables_filter input {
        width: 240px;
        height: 34px;
        padding: 6px 10px;
        color: var(--ap-ink);
        background: var(--ap-surface);
        border: 1px solid var(--ap-rule);
        border-radius: 8px;
        font-family: inherit;
        font-size: .77rem;
    }

    .ap-page .dataTables_filter input::placeholder {
        color: var(--ap-muted);
        opacity: .85;
    }

    .ap-page .dataTables_filter input:focus {
        outline: none;
        border-color: var(--ap-accent-ink);
        box-shadow: 0 0 0 3px var(--ap-accent-soft);
    }

    .ap-page .ap-dt-foot {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 18px;
        border-top: 1px solid var(--ap-rule);
    }

    .ap-page .dataTables_info {
        padding: 0;
        color: var(--ap-muted);
        font-size: .73rem;
    }

    .ap-page .dataTables_paginate {
        padding: 0;
    }

    .ap-page .dataTables_paginate .pagination {
        gap: 4px;
        margin: 0;
    }

    .ap-page .dataTables_paginate .page-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        padding: 0 8px;
        color: var(--ap-ink);
        background: transparent;
        border: 1px solid var(--ap-rule);
        border-radius: 8px;
        font-size: .73rem;
        font-weight: 600;
    }

    .ap-page .dataTables_paginate .page-link:hover {
        color: var(--ap-accent-ink);
        background: var(--ap-accent-soft);
        border-color: var(--ap-accent-ink);
    }

    .ap-page .dataTables_paginate .active .page-link {
        color: #fff;
        background: var(--ap-accent-solid);
        border-color: var(--ap-accent-solid);
    }

    .ap-page .dataTables_paginate .disabled .page-link {
        opacity: .45;
        pointer-events: none;
    }

    /* Spesifisitas harus mengalahkan aturan sel tabel di atas. */
    .ap-page .ap-table > tbody > tr > td.dataTables_empty {
        padding: 28px 14px;
        color: var(--ap-muted);
        text-align: center;
    }

    /* ===== Responsif ===== */
    @media (max-width: 991.98px) {
        .ap-page .ap-filter__hint {
            justify-content: flex-start;
        }
    }

    @media (max-width: 575.98px) {
        .ap-page .ap-hero h1 {
            font-size: 1.35rem;
        }

        .ap-page .ap-hero__aside,
        .ap-page .ap-hero__aside .ap-btn {
            width: 100%;
        }

        .ap-page .ap-board {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .ap-page .ap-field {
            flex: 1 1 100%;
        }

        .ap-page .ap-filter__fields {
            width: 100%;
        }

        .ap-page .ap-filter__fields .ap-btn {
            width: 100%;
        }

        .ap-page .ap-dt-toolbar,
        .ap-page .ap-dt-foot {
            flex-direction: column;
            align-items: stretch;
        }

        .ap-page .ap-dt-toolbar .dataTables_filter input {
            width: 100%;
        }

        .ap-page .ap-dt-search,
        .ap-page .ap-dt-search .dataTables_filter,
        .ap-page .ap-dt-search .dataTables_filter label {
            display: block;
            width: 100%;
        }
    }

    /* Tabel menjadi kartu per baris di ponsel. */
    @media (max-width: 767.98px) {
        .ap-page .ap-table-wrap {
            overflow: visible;
        }

        .ap-page .ap-table > thead {
            display: none;
        }

        .ap-page .ap-table,
        .ap-page .ap-table > tbody,
        .ap-page .ap-table > tbody > tr,
        .ap-page .ap-table > tbody > tr > td {
            display: block;
            width: 100%;
        }

        .ap-page .ap-table > tbody > tr {
            padding: 12px 16px;
            border-top: 1px solid var(--ap-rule);
        }

        .ap-page .ap-table > tbody > tr:first-child {
            border-top: 0;
        }

        .ap-page .ap-table > tbody > tr > td {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            padding: 5px 0;
            border: 0;
            text-align: right;
        }

        .ap-page .ap-table > tbody > tr > td::before {
            content: attr(data-label);
            flex: none;
            color: var(--ap-muted);
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-align: left;
            text-transform: uppercase;
        }

        .ap-page .ap-table > tbody > tr > td:first-child {
            padding-top: 0;
            text-align: left;
        }

        .ap-page .ap-table > tbody > tr > td:first-child::before,
        .ap-page .ap-table > tbody > tr > td:last-child::before {
            display: none;
        }

        .ap-page .ap-table > tbody > tr > td:last-child {
            display: block;
            padding-top: 10px;
            text-align: left;
        }

        .ap-page .ap-reason {
            max-width: none;
        }

        /* Sel berisi teks panjang dibaca kiri-ke-kanan, bukan label:nilai. */
        .ap-page .ap-table > tbody > tr > td[data-label="Alasan"] {
            display: block;
            text-align: left;
        }

        .ap-page .ap-table > tbody > tr > td[data-label="Alasan"]::before {
            display: block;
            margin-bottom: 3px;
        }

        .ap-page .ap-input {
            max-width: none;
            height: 40px;
        }

        .ap-page .ap-form .ap-btn {
            min-height: 40px;
        }

        .ap-page .ap-form {
            min-width: 0;
        }

        .ap-page .ap-table > tbody > tr > td.dataTables_empty {
            padding: 28px 0;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .ap-page *,
        .ap-page *::before,
        .ap-page *::after {
            transition-duration: .01ms !important;
            animation-duration: .01ms !important;
        }
    }

    /* Tombol kustomisasi template menutupi kolom keputusan di kanan bawah. */
    .customizer-btn {
        display: none !important;
    }
</style>
