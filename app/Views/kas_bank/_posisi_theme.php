<?php
/**
 * Lembar Posisi Kas — tema khusus dashboard /kas_bank.
 *
 * Semua selector di-scope ke .kbx supaya akun/transfer/antar_unit yang masih
 * memakai komponen .kb-* di _theme.php tidak ikut berubah. Warna diturunkan
 * dari token tema aplikasi; satu aksen (primary) untuk KAS dan angka utama,
 * satu amber untuk BANK. Tidak ada bayangan di dalam lembar: kedalaman dibawa
 * oleh garis rambut dan aturan ganda total, bukan elevasi kartu.
 */
?>
<style>
    .kbx {
        --kbx-ink: var(--bs-emphasis-color, #111c2d);
        --kbx-muted: #5b6672;
        --kbx-line: var(--bs-border-color, #e6ecf1);
        --kbx-line-strong: #7d8a99;
        --kbx-sheet: var(--bs-body-bg, #fff);
        --kbx-soft: var(--bs-tertiary-bg, #e7ecf0);
        --kbx-bank: #b45309;
        --kbx-kas-ink: #0069a8;
        --kbx-radius: 0.375rem;
        margin: 0 0 2rem;
        color: var(--kbx-ink);
        font-size: 0.875rem;
    }

    [data-bs-theme="dark"] .kbx {
        --kbx-muted: #8fa0b8;
        --kbx-line-strong: #8fa0b8;
        --kbx-bank: #fbbf24;
        --kbx-kas-ink: #66b6e9;
    }

    .kbx .kbx-label {
        display: block;
        font-size: 0.6875rem;
        font-weight: 600;
        line-height: 1.3;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: var(--kbx-muted);
    }

    .kbx-num {
        font-variant-numeric: tabular-nums;
        font-feature-settings: "tnum";
        letter-spacing: -0.01em;
    }

    /* ===== Toolbar filter (di kanvas, bukan kartu) ===== */
    .kbx-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 0.75rem 1rem;
        padding-bottom: 1rem;
        margin-bottom: 1.25rem;
        border-bottom: 1px solid var(--kbx-line);
    }

    .kbx-field {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .kbx-control {
        height: 2.25rem;
        padding: 0.25rem 0.625rem;
        font-size: 0.875rem;
        color: var(--kbx-ink);
        background: var(--kbx-sheet);
        border: 1px solid var(--kbx-line-strong);
        border-radius: 0.25rem;
        box-shadow: none;
    }

    .kbx-control:focus {
        border-color: var(--bs-primary, #0d6efd);
        outline: 2px solid rgba(var(--bs-primary-rgb, 13, 110, 253), 0.25);
        outline-offset: 0;
        box-shadow: none;
    }

    .kbx-field--date .kbx-control { width: 9.75rem; }
    .kbx-field--unit .kbx-control,
    .kbx-field--akun .kbx-control { width: 15rem; min-width: 0; }

    .kbx-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .kbx-apply {
        height: 2.25rem;
        padding: 0 0.875rem;
        font-size: 0.875rem;
        font-weight: 600;
        border: 1px solid #0074ba;
        border-radius: 0.25rem;
        background: #0074ba;
    }

    .kbx-apply:hover,
    .kbx-apply:focus {
        background: #0069a8;
        border-color: #0069a8;
    }

    .kbx-reset {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--kbx-muted);
        text-decoration: none;
    }
    .kbx-reset:hover { color: var(--kbx-kas-ink); text-decoration: underline; }

    /* ===== Lembar posisi ===== */
    .kbx-sheet {
        background: var(--kbx-sheet);
        border: 1px solid var(--kbx-line);
        border-radius: var(--kbx-radius);
        overflow: hidden;
    }

    .kbx-meta {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 0.25rem 1.5rem;
        padding: 0.625rem 1.5rem;
        border-bottom: 1px solid var(--kbx-line);
        font-size: 0.75rem;
        color: var(--kbx-muted);
    }

    .kbx-meta b {
        font-weight: 600;
        color: var(--kbx-ink);
    }

    .kbx-hero {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
    }

    .kbx-hero-main {
        padding: 1.25rem 1.5rem 1.375rem;
    }

    .kbx-total {
        margin-top: 0.25rem;
        font-size: 2.25rem;
        font-weight: 600;
        line-height: 1.1;
        letter-spacing: -0.03em;
    }

    .kbx-total-note {
        margin-top: 0.25rem;
        font-size: 0.75rem;
        color: var(--kbx-muted);
    }

    .kbx-split {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem 1.5rem;
        margin-top: 1.125rem;
        padding-top: 0.875rem;
        border-top: 1px solid var(--kbx-line);
    }

    .kbx-split-value {
        margin-top: 0.125rem;
        font-size: 1.125rem;
        font-weight: 600;
    }

    .kbx-split-note {
        font-size: 0.6875rem;
        color: var(--kbx-muted);
    }

    /* Komposisi kas vs bank: satu batang tipis, bukan progress ring. */
    .kbx-compose {
        padding: 0 1.5rem 1.25rem;
    }

    .kbx-bar {
        display: flex;
        gap: 2px;
        height: 6px;
        background: var(--kbx-soft);
        overflow: hidden;
    }

    .kbx-bar > span {
        display: block;
        height: 100%;
        transform-origin: left center;
        animation: kbx-grow 0.45s cubic-bezier(0.2, 0.7, 0.2, 1) both;
    }

    .kbx-bar .is-kas { background: var(--bs-primary, #0d6efd); }
    .kbx-bar .is-bank { background: var(--kbx-bank); }

    @keyframes kbx-grow {
        from { transform: scaleX(0); }
        to { transform: scaleX(1); }
    }

    .kbx-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem 1rem;
        margin-top: 0.5rem;
        font-size: 0.6875rem;
        color: var(--kbx-muted);
    }

    .kbx-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
    }

    .kbx-legend-item::before {
        content: "";
        width: 6px;
        height: 6px;
        background: currentColor;
    }
    .kbx-legend-item.is-kas { color: var(--kbx-kas-ink); }
    .kbx-legend-item.is-bank { color: var(--kbx-bank); }
    .kbx-legend-item.is-kas::before { background: var(--bs-primary, #0d6efd); }
    .kbx-legend-item.is-bank::before { background: var(--kbx-bank); }

    .kbx-note {
        margin: 0.75rem 0 0;
        max-width: 68ch;
        font-size: 0.6875rem;
        line-height: 1.5;
        color: var(--kbx-muted);
    }

    /* ===== Arus kas ===== */
    .kbx-flow {
        padding: 1rem 1.5rem 1.125rem;
        border-left: 1px solid var(--kbx-line);
        background: var(--kbx-soft);
    }

    .kbx-flow-title {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.25rem;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: var(--kbx-muted);
    }

    .kbx-flow-title span:last-child {
        font-weight: 500;
        letter-spacing: 0.02em;
        text-transform: none;
    }

    .kbx-flow-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.4375rem 0;
        border-bottom: 1px solid var(--kbx-line);
        font-size: 0.8125rem;
    }

    .kbx-flow-row:last-child {
        border-bottom: 0;
        border-top: 3px double var(--kbx-line-strong);
        margin-top: 0.25rem;
        padding-top: 0.625rem;
        font-weight: 600;
    }

    .kbx-flow-label { color: var(--kbx-muted); }
    .kbx-flow-row:last-child .kbx-flow-label { color: var(--kbx-ink); }
    .kbx-flow-value { white-space: nowrap; }
    .kbx-flow-note {
        margin: 0.375rem 0 0;
        font-size: 0.6875rem;
        line-height: 1.4;
        color: var(--kbx-muted);
    }
    .kbx .is-zero { color: var(--kbx-muted); }

    /* Warna tanda arus: token gelap/terang yang terukur ≥4.5:1, bukan
       .text-success/.text-danger bawaan template yang terlalu pucat di putih. */
    .kbx-pos { color: #0f7a46; }
    .kbx-neg { color: #c2353f; }
    [data-bs-theme="dark"] .kbx-pos { color: #4bd08b; }
    [data-bs-theme="dark"] .kbx-neg { color: #fb977d; }

    /* ===== Identitas di kaki lembar ===== */
    .kbx-identity {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 0.25rem 1.25rem;
        padding: 0.625rem 1.5rem;
        border-top: 1px solid var(--kbx-line);
        background: var(--kbx-soft);
        font-size: 0.75rem;
        color: var(--kbx-muted);
    }

    .kbx-identity b {
        font-weight: 600;
        color: var(--kbx-ink);
    }

    /* ===== Alert ===== */
    .kbx-alert {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        margin-bottom: 1rem;
        padding: 0.75rem 1rem;
        border: 1px solid var(--bs-danger-border-subtle, #f1aeb5);
        border-radius: var(--kbx-radius);
        background: var(--bs-danger-bg-subtle, #f8d7da);
        color: var(--bs-danger-text-emphasis, #58151c);
        font-size: 0.8125rem;
        line-height: 1.5;
    }

    .kbx-alert iconify-icon {
        flex-shrink: 0;
        margin-top: 0.125rem;
        font-size: 1rem;
    }

    .kbx-alert a {
        color: inherit;
        font-weight: 600;
        text-decoration: underline;
    }

    /* Diagnosa konfigurasi memakai strip yang sama dengan .kbx-alert, bukan kartu. */
    .kbx .kb-banner {
        border-radius: var(--kbx-radius);
        box-shadow: none;
        font-size: 0.8125rem;
        line-height: 1.5;
    }

    .kbx .kb-banner-icon {
        width: auto;
        height: auto;
        margin-top: 0.125rem;
        border-radius: 0;
        font-size: 1rem;
    }

    /* ===== Buku rekening ===== */
    .kbx-ledger {
        margin-top: 1.25rem;
        background: var(--kbx-sheet);
        border: 1px solid var(--kbx-line);
        border-radius: var(--kbx-radius);
        overflow: hidden;
    }

    .kbx-ledger-head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.5rem 1.5rem;
        padding: 1rem 1.5rem 0.75rem;
    }

    .kbx-ledger-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        letter-spacing: -0.01em;
    }

    .kbx-ledger-sub {
        font-size: 0.75rem;
        color: var(--kbx-muted);
    }

    .kbx-ledger-legend {
        display: flex;
        gap: 1rem;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.07em;
        text-transform: uppercase;
    }

    .kbx-kind {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--kbx-muted);
        white-space: nowrap;
    }

    .kbx-kind::before {
        content: "";
        width: 6px;
        height: 6px;
        background: currentColor;
    }

    .kbx-kind.is-kas { color: var(--kbx-kas-ink); }
    .kbx-kind.is-bank { color: var(--kbx-bank); }
    .kbx-kind.is-kas::before { background: var(--bs-primary, #0d6efd); }
    .kbx-kind.is-bank::before { background: var(--kbx-bank); }

    .kbx-table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
        --bs-table-bg: transparent;
    }

    .kbx-table th {
        padding: 0.5rem 1.5rem;
        border-top: 1px solid var(--kbx-line);
        border-bottom: 1px solid var(--kbx-line);
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        text-align: left;
        color: var(--kbx-muted);
        white-space: nowrap;
    }

    .kbx-table td {
        padding: 0.6875rem 1.5rem;
        border-bottom: 1px solid var(--kbx-line);
        font-size: 0.875rem;
        vertical-align: middle;
    }

    .kbx-table th.num,
    .kbx-table td.num { text-align: right; }

    .kbx-table thead th:nth-child(2),
    .kbx-table tbody td:nth-child(2) {
        width: 1%;
        white-space: nowrap;
    }

    .kbx-table tbody tr:hover > td { background: var(--kbx-soft); }

    .kbx-acct-name {
        font-weight: 600;
        color: var(--kbx-ink);
    }

    .kbx-acct-meta {
        margin-top: 0.125rem;
        font-size: 0.75rem;
        color: var(--kbx-muted);
    }

    .kbx-share {
        width: 72px;
        height: 3px;
        margin: 0.375rem 0 0 auto;
        background: var(--kbx-soft);
        overflow: hidden;
    }

    .kbx-share > span {
        display: block;
        height: 100%;
        background: var(--bs-primary, #0d6efd);
    }

    .kbx-share.is-bank > span { background: var(--kbx-bank); }
    .kbx-share.is-over > span { background: var(--bs-danger, #dc3545); }

    .kbx-share-note {
        margin-top: 0.25rem;
        font-size: 0.6875rem;
        color: var(--kbx-muted);
    }

    .kbx-flag {
        font-weight: 600;
        color: var(--bs-danger-text-emphasis, #58151c);
    }

    [data-bs-theme="dark"] .kbx-flag { color: #fb977d; }

    /* Tombol kustomisasi template menutupi nominal kolom kanan di dasar buku rekening. */
    .customizer-btn { display: none !important; }

    .kbx-table tfoot td {
        padding-top: 0.75rem;
        padding-bottom: 0.875rem;
        border-top: 3px double var(--kbx-line-strong);
        border-bottom: 0;
        font-weight: 600;
    }

    .kbx-table tfoot .kbx-share-note { font-weight: 400; }

    .kbx-empty {
        padding: 2.5rem 1.5rem;
        text-align: center;
        color: var(--kbx-muted);
    }

    .kbx-empty iconify-icon {
        display: block;
        width: 1.5rem;
        height: 1.5rem;
        margin: 0 auto 0.375rem;
        font-size: 1.5rem;
        opacity: 0.55;
    }

    /* ===== Responsif ===== */
    @media (max-width: 991.98px) {
        .kbx-hero { grid-template-columns: minmax(0, 1fr); }
        .kbx-flow {
            border-left: 0;
            border-top: 1px solid var(--kbx-line);
        }
    }

    @media (max-width: 767.98px) {
        .kbx-toolbar { gap: 0.75rem; }
        .kbx-field--unit,
        .kbx-field--akun { flex: 1 1 100%; }
        .kbx-field--unit .kbx-control,
        .kbx-field--akun .kbx-control { min-width: 0; width: 100%; }
        .kbx-field--date { flex: 1 1 45%; }
        .kbx-field--date .kbx-control { width: 100%; }

        .kbx-meta,
        .kbx-hero-main,
        .kbx-compose,
        .kbx-identity,
        .kbx-ledger-head { padding-left: 1rem; padding-right: 1rem; }
        .kbx-flow { padding-left: 1rem; padding-right: 1rem; }

        .kbx-total { font-size: 1.875rem; }

        .kbx-table thead { display: none; }
        .kbx-table,
        .kbx-table tbody,
        .kbx-table tr,
        .kbx-table td,
        .kbx-table tfoot { display: block; }

        .kbx-table tr {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--kbx-line);
        }

        .kbx-table td {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.125rem 0;
            border: 0;
            text-align: right;
        }

        .kbx-table td::before {
            content: attr(data-label);
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: var(--kbx-muted);
        }

        .kbx-table td:first-child {
            display: block;
            margin-bottom: 0.375rem;
            text-align: left;
        }
        .kbx-table td:first-child::before { display: none; }

        .kbx-table td.kbx-cell-kind { display: inline-flex; }
        .kbx-table td.kbx-cell-kind::before { display: none; }

        .kbx-table td.kbx-cell-share {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: baseline;
            gap: 0.125rem 0.75rem;
            margin-top: 0.375rem;
            text-align: left;
        }
        .kbx-table td.kbx-cell-share::before {
            grid-column: 1;
            margin: 0;
        }
        .kbx-table td.kbx-cell-share > .kbx-num {
            grid-column: 2;
            text-align: right;
        }
        .kbx-table td.kbx-cell-share .kbx-share,
        .kbx-table td.kbx-cell-share .kbx-share-note { grid-column: 1 / -1; }
        .kbx-share { margin-left: 0; }

        .kbx-table tfoot tr {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.25rem 1rem;
            border-bottom: 0;
        }
        .kbx-table tfoot td,
        .kbx-table tfoot td.num {
            display: grid;
            gap: 0.125rem;
            padding: 0;
            border: 0;
            text-align: left;
        }
        .kbx-table tfoot td:first-child { grid-column: 1 / -1; }
    }

    @media (prefers-reduced-motion: reduce) {
        .kbx-bar > span { animation: none; }
    }
</style>
