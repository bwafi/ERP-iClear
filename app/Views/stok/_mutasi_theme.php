<?php

/**
 * Design system halaman Mutasi Stok (/mutasi_stok).
 *
 * Semua selector berada di bawah `.mutasi-stok` supaya tidak bocor ke modul
 * lain. Warna diturunkan dari token tema aplikasi; aksen tunggal (biru
 * primary) hanya dipakai untuk aksi utama, fokus, dan state terpilih.
 *
 * Kontras teks kecil dijaga >= 4.5:1 di terang maupun gelap:
 *   --ms-ink    #626d7b di putih (5.0:1), #cfd8e3 di gelap
 *   --ms-muted  #5b6672 di putih (5.85:1), #8fa0b8 di gelap
 * Tombol primary memakai #0077c4 (putih 4.73:1), bukan primary template
 * #0085db yang hanya 3.9:1.
 */
?>

<style>
    /* ===== Token ===== */
    .mutasi-stok {
        --ms-surface: var(--bs-card-bg, #fff);
        --ms-subtle: #f4f7fb;
        --ms-rule: var(--bs-border-color, #e3e9f0);
        --ms-rule-strong: #d5dde7;
        --ms-hover: rgba(0, 119, 196, .045);
        --ms-ink: #626d7b;
        --ms-strong: #1c2733;
        --ms-muted: #5b6672;
        --ms-accent: #0077c4;
        --ms-accent-ink: #0067a8;
        --ms-accent-solid: #0077c4;
        --ms-accent-hover: #0069ad;
        --ms-accent-soft: rgba(0, 119, 196, .08);
        --ms-danger-ink: #b02a37;
        --ms-danger-soft: rgba(176, 42, 55, .10);
        --ms-warn-ink: #8a5300;
        --ms-warn-soft: rgba(192, 118, 0, .14);
        --ms-ok-ink: #0f6b3e;
        --ms-ok-soft: rgba(15, 122, 70, .10);
        --ms-radius: 16px;
        --ms-radius-sm: 10px;
        --ms-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 22px 48px -40px rgba(16, 24, 40, .4);
        max-width: 1280px;
        margin-inline: auto;
        color: var(--ms-ink);
        font-size: .875rem;
    }

    html[data-bs-theme="dark"] .mutasi-stok {
        --ms-surface: var(--bs-card-bg, #2a3547);
        --ms-subtle: rgba(255, 255, 255, .04);
        --ms-rule: var(--bs-border-color, rgba(255, 255, 255, .12));
        --ms-rule-strong: rgba(255, 255, 255, .18);
        --ms-hover: rgba(255, 255, 255, .04);
        --ms-ink: var(--bs-body-color, #cfd8e3);
        --ms-strong: #f1f5f9;
        --ms-muted: #8fa0b8;
        --ms-accent: #8ab4ff;
        --ms-accent-ink: #8ab4ff;
        --ms-accent-solid: #2f6fe4;
        --ms-accent-hover: #2563eb;
        --ms-accent-soft: rgba(47, 111, 228, .16);
        --ms-danger-ink: #ff9aa2;
        --ms-danger-soft: rgba(255, 154, 162, .12);
        --ms-warn-ink: #ffcf6e;
        --ms-warn-soft: rgba(255, 207, 110, .12);
        --ms-ok-ink: #6fdd9d;
        --ms-ok-soft: rgba(111, 221, 157, .12);
        --ms-shadow: none;
    }

    .mutasi-stok,
    .mutasi-stok .modal-content {
        color: var(--ms-ink);
    }

    .mutasi-stok .text-muted {
        color: var(--ms-muted) !important;
    }

    .mutasi-stok ::selection {
        background: var(--ms-accent-soft);
        color: var(--ms-strong);
    }

    .mutasi-stok input,
    .mutasi-stok select {
        caret-color: var(--ms-accent);
    }

    /* ===== Kepala halaman ===== */
    .ms-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 24px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .ms-head h1 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--ms-strong);
    }

    .ms-head p {
        max-width: 62ch;
        margin: 4px 0 0;
        color: var(--ms-muted);
        font-size: .875rem;
        line-height: 1.5;
    }

    .ms-crumb {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
        font-size: .75rem;
        color: var(--ms-muted);
    }

    .ms-crumb a {
        color: var(--ms-muted);
        text-decoration: none;
    }

    .ms-crumb a:hover {
        color: var(--ms-accent-ink);
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .ms-crumb iconify-icon {
        font-size: .7rem;
    }

    .ms-context {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border: 1px solid var(--ms-rule);
        border-radius: 12px;
        background: var(--ms-surface);
        box-shadow: var(--ms-shadow);
    }

    .ms-context iconify-icon {
        font-size: 1.25rem;
        color: var(--ms-accent-ink);
    }

    .ms-context small {
        display: block;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--ms-muted);
    }

    .ms-context strong {
        display: block;
        font-size: .875rem;
        font-weight: 650;
        color: var(--ms-strong);
    }

    /* ===== Panel ===== */
    .ms-panel {
        background: var(--ms-surface);
        border: 1px solid var(--ms-rule);
        border-radius: var(--ms-radius);
        box-shadow: var(--ms-shadow);
        overflow: hidden;
    }

    .ms-panel + .ms-panel {
        margin-top: 16px;
    }

    .ms-panel__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 20px;
        border-bottom: 1px solid var(--ms-rule);
    }

    .ms-panel__title {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        min-width: 0;
    }

    .ms-icon {
        display: grid;
        place-items: center;
        flex: none;
        width: 38px;
        height: 38px;
        border-radius: 11px;
        background: var(--ms-accent-soft);
        color: var(--ms-accent-ink);
        font-size: 1.25rem;
    }

    .ms-panel h2 {
        margin: 0;
        font-size: 1.0625rem;
        font-weight: 650;
        letter-spacing: -.01em;
        color: var(--ms-strong);
    }

    .ms-panel__title p {
        margin: 2px 0 0;
        font-size: .8125rem;
        line-height: 1.45;
        color: var(--ms-muted);
    }

    /* ===== Kontrol ===== */
    .ms-label {
        display: block;
        margin-bottom: 6px;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .045em;
        text-transform: uppercase;
        color: var(--ms-muted);
    }

    .mutasi-stok .ms-control {
        min-height: 42px;
        padding: .55rem .75rem;
        border: 1px solid var(--ms-rule-strong);
        border-radius: var(--ms-radius-sm);
        background: var(--ms-surface);
        color: var(--ms-strong);
        font-size: .875rem;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .mutasi-stok .ms-control:hover {
        border-color: var(--ms-muted);
    }

    .mutasi-stok .ms-control:focus {
        border-color: var(--ms-accent);
        box-shadow: 0 0 0 3px var(--ms-accent-soft);
        outline: 0;
    }

    .mutasi-stok .ms-control[readonly] {
        background: var(--ms-subtle);
        color: var(--ms-muted);
    }

    .ms-help {
        display: block;
        margin-top: 5px;
        font-size: .72rem;
        line-height: 1.35;
        color: var(--ms-muted);
    }

    .ms-help strong {
        color: var(--ms-ink);
        font-variant-numeric: tabular-nums;
    }

    /* ===== Rute ===== */
    .ms-route {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 44px minmax(0, 1fr);
        align-items: end;
        gap: 14px;
        padding: 20px;
    }

    .ms-route__arrow {
        display: grid;
        place-items: center;
        width: 40px;
        height: 40px;
        margin-bottom: 1px;
        border: 1px solid var(--ms-rule);
        border-radius: 50%;
        background: var(--ms-subtle);
        color: var(--ms-accent-ink);
        font-size: 1.1rem;
    }

    .ms-dates {
        display: grid;
        grid-template-columns: minmax(0, 360px);
        gap: 14px;
        padding: 18px 20px 20px;
        border-top: 1px solid var(--ms-rule);
    }

    .ms-lock-note {
        grid-column: 1 / -1;
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 0;
        font-size: .75rem;
        color: var(--ms-muted);
    }

    .ms-lock-note iconify-icon {
        color: var(--ms-warn-ink);
        font-size: .95rem;
    }

    /* Catatan unit terkunci di bawah rute, supaya kedua select tetap sejajar. */
    .ms-route__note {
        padding: 0 20px 20px;
    }

    /* ===== Tombol ===== */
    .ms-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 40px;
        padding: 9px 14px;
        border: 1px solid transparent;
        border-radius: var(--ms-radius-sm);
        font-size: .8125rem;
        font-weight: 650;
        line-height: 1.2;
        text-decoration: none;
        white-space: nowrap;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease, transform .15s ease;
    }

    .ms-btn iconify-icon {
        flex: none;
        font-size: 1.05em;
    }

    .ms-btn--primary {
        background: var(--ms-accent-solid);
        border-color: var(--ms-accent-solid);
        color: #fff;
    }

    .ms-btn--primary:hover,
    .ms-btn--primary:focus {
        background: var(--ms-accent-hover);
        border-color: var(--ms-accent-hover);
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 8px 18px -10px var(--ms-accent-solid);
    }

    .ms-btn--ghost {
        background: transparent;
        border-color: var(--ms-rule-strong);
        color: var(--ms-strong);
    }

    .ms-btn--ghost:hover,
    .ms-btn--ghost:focus {
        background: var(--ms-accent-soft);
        border-color: var(--ms-accent);
        color: var(--ms-accent-ink);
    }

    .ms-btn--lg {
        min-height: 44px;
        padding: 11px 20px;
        font-size: .875rem;
    }

    .ms-btn:focus-visible,
    .ms-icon-btn:focus-visible,
    .ms-crumb a:focus-visible {
        outline: 2px solid var(--ms-accent);
        outline-offset: 2px;
    }

    .ms-btn:disabled,
    .ms-btn[disabled] {
        opacity: .5;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .ms-icon-btn {
        display: inline-grid;
        place-items: center;
        width: 34px;
        height: 34px;
        border: 1px solid transparent;
        border-radius: 8px;
        background: transparent;
        color: var(--ms-muted);
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }

    .ms-icon-btn:hover {
        background: var(--ms-danger-soft);
        border-color: var(--ms-danger-soft);
        color: var(--ms-danger-ink);
    }

    /* ===== Empty state ===== */
    .ms-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin: 20px;
        padding: 36px 24px;
        border: 1px dashed var(--ms-rule-strong);
        border-radius: 12px;
        background: var(--ms-subtle);
        text-align: center;
    }

    .ms-empty__icon {
        display: grid;
        place-items: center;
        width: 52px;
        height: 52px;
        margin-bottom: 12px;
        border: 1px solid var(--ms-rule);
        border-radius: 50%;
        background: var(--ms-surface);
        color: var(--ms-accent-ink);
        font-size: 1.5rem;
    }

    .ms-empty h3 {
        margin: 0;
        font-size: 1rem;
        font-weight: 650;
        color: var(--ms-strong);
    }

    .ms-empty p {
        max-width: 46ch;
        margin: 4px 0 16px;
        font-size: .8125rem;
        line-height: 1.5;
        color: var(--ms-muted);
    }

    /* ===== Tabel ===== */
    .ms-table-wrap {
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--ms-rule-strong) transparent;
    }

    .ms-table-wrap::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    .ms-table-wrap::-webkit-scrollbar-thumb {
        border: 2px solid transparent;
        border-radius: 999px;
        background: var(--ms-rule-strong);
        background-clip: content-box;
    }

    .ms-table {
        width: 100%;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
        font-size: .8125rem;
    }

    .ms-table th {
        position: sticky;
        top: 0;
        z-index: 1;
        padding: 10px 14px;
        border-bottom: 1px solid var(--ms-rule);
        background: var(--ms-subtle);
        color: var(--ms-muted);
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .ms-table td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--ms-rule);
        color: var(--ms-ink);
        vertical-align: middle;
    }

    .ms-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .ms-table tbody tr:hover td {
        background: var(--ms-hover);
    }

    .ms-table .ms-money {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .ms-table .ms-control {
        min-height: 36px;
        padding: .38rem .55rem;
        font-size: .8125rem;
    }

    .ms-table .is-invalid {
        border-color: var(--ms-danger-ink);
        box-shadow: 0 0 0 3px var(--ms-danger-soft);
    }

    @media (min-width: 768px) {
        /* Catatan stok di bawah input tidak boleh menggeser teks kolom lain. */
        .ms-table--items td {
            vertical-align: top;
        }

        .ms-table--items td.ms-money {
            line-height: 36px;
        }
    }

    .ms-item-name {
        display: block;
        font-weight: 650;
        color: var(--ms-strong);
    }

    .ms-item-meta {
        display: block;
        margin-top: 2px;
        font-size: .72rem;
        line-height: 1.4;
        color: var(--ms-muted);
    }

    .ms-item-meta strong {
        color: var(--ms-ink);
        font-variant-numeric: tabular-nums;
    }

    .ms-item-meta.is-empty-stock,
    .ms-item-meta.is-empty-stock strong {
        color: var(--ms-danger-ink);
    }

    .ms-stock {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 42px;
        padding: 3px 8px;
        border: 1px solid var(--ms-rule);
        border-radius: 999px;
        background: var(--ms-subtle);
        color: var(--ms-ink);
        font-size: .72rem;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .ms-stock.is-empty {
        border-color: transparent;
        background: var(--ms-danger-soft);
        color: var(--ms-danger-ink);
    }

    .ms-stock.is-low {
        border-color: transparent;
        background: var(--ms-warn-soft);
        color: var(--ms-warn-ink);
    }

    .ms-tag {
        display: inline-block;
        padding: 2px 8px;
        border: 1px solid var(--ms-rule);
        border-radius: 6px;
        background: var(--ms-subtle);
        color: var(--ms-muted);
        font-size: .72rem;
        white-space: nowrap;
    }

    .ms-prod-name {
        display: block;
        font-weight: 650;
        color: var(--ms-strong);
    }

    .ms-prod-meta {
        display: block;
        margin-top: 2px;
        font-size: .72rem;
        color: var(--ms-muted);
    }

    /* ===== Ringkasan ===== */
    .ms-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        padding: 16px 20px;
        border-top: 1px solid var(--ms-rule);
        background: var(--ms-subtle);
    }

    .ms-summary__count {
        font-size: .8125rem;
        color: var(--ms-muted);
    }

    .ms-summary__count strong {
        color: var(--ms-strong);
        font-variant-numeric: tabular-nums;
    }

    /* ===== Modal picker ===== */
    .mutasi-stok .modal-content.ms-modal {
        border: 0;
        border-radius: 18px;
        background: var(--ms-surface);
        color: var(--ms-ink);
        overflow: hidden;
    }

    .ms-modal .modal-header {
        align-items: flex-start;
        padding: 20px 22px 14px;
        border-bottom: 0;
    }

    .ms-modal .modal-header h2 {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 700;
        letter-spacing: -.01em;
        color: var(--ms-strong);
    }

    .ms-modal .modal-header p {
        margin: 3px 0 0;
        font-size: .8125rem;
        color: var(--ms-muted);
    }

    .mutasi-stok .ms-modal .btn-close {
        margin-top: 2px;
    }

    .ms-modal__toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 0 22px 14px;
    }

    .ms-search {
        position: relative;
        flex: 1;
        max-width: 420px;
    }

    .ms-search iconify-icon {
        position: absolute;
        top: 50%;
        left: 12px;
        transform: translateY(-50%);
        color: var(--ms-muted);
        font-size: 1rem;
        pointer-events: none;
    }

    .ms-search input {
        width: 100%;
        min-height: 42px;
        padding: 8px 12px 8px 38px;
        border: 1px solid var(--ms-rule-strong);
        border-radius: var(--ms-radius-sm);
        background: var(--ms-subtle);
        color: var(--ms-strong);
        font-size: .8125rem;
        transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }

    .ms-search input:focus {
        border-color: var(--ms-accent);
        background: var(--ms-surface);
        box-shadow: 0 0 0 3px var(--ms-accent-soft);
        outline: 0;
    }

    .ms-search input::placeholder {
        color: var(--ms-muted);
    }

    .ms-pick-count {
        font-size: .75rem;
        color: var(--ms-muted);
        white-space: nowrap;
    }

    .ms-pick-count strong {
        color: var(--ms-strong);
        font-variant-numeric: tabular-nums;
    }

    .ms-modal .modal-body {
        padding: 0;
    }

    .ms-modal .modal-footer {
        gap: 10px;
        padding: 14px 22px;
        border-top: 1px solid var(--ms-rule);
    }

    .ms-modal__foot-note {
        margin-right: auto;
        font-size: .75rem;
        color: var(--ms-muted);
    }

    .ms-table--picker tbody tr {
        cursor: pointer;
    }

    .ms-table--picker tbody tr.is-selected td {
        background: var(--ms-accent-soft);
    }

    .ms-table--picker .ms-check {
        width: 46px;
    }

    .mutasi-stok .form-check-input {
        width: 1.05em;
        height: 1.05em;
        margin: 0;
        border-color: var(--ms-rule-strong);
        cursor: pointer;
    }

    .mutasi-stok .form-check-input:checked,
    .mutasi-stok .form-check-input:indeterminate {
        background-color: var(--ms-accent-solid);
        border-color: var(--ms-accent-solid);
    }

    .mutasi-stok .form-check-input:focus {
        border-color: var(--ms-accent);
        box-shadow: 0 0 0 3px var(--ms-accent-soft);
    }

    .ms-dt-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        padding: 12px 22px 16px;
        font-size: .75rem;
        color: var(--ms-muted);
    }

    .ms-dt-foot .dataTables_info {
        padding: 0;
        color: var(--ms-muted);
    }

    .ms-dt-foot .dataTables_paginate {
        display: flex;
        align-items: center;
        gap: 3px;
    }

    .ms-dt-foot .paginate_button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        height: 28px;
        padding: 0 6px;
        border: 1px solid var(--ms-rule);
        border-radius: 7px;
        background: transparent;
        color: var(--ms-accent-ink) !important;
        font-size: .75rem;
        text-decoration: none;
        cursor: pointer;
    }

    .ms-dt-foot .paginate_button:hover {
        border-color: var(--ms-accent);
        background: var(--ms-accent-soft);
    }

    .ms-dt-foot .paginate_button.current {
        border-color: var(--ms-accent-solid);
        background: var(--ms-accent-solid);
        color: #fff !important;
    }

    .ms-dt-foot .paginate_button.disabled {
        opacity: .5;
        color: var(--ms-muted) !important;
        cursor: default;
    }

    .ms-dt-foot .paginate_button.disabled:hover {
        border-color: var(--ms-rule);
        background: transparent;
    }

    .ms-dt-foot .ellipsis {
        padding: 0 3px;
        color: var(--ms-muted);
    }

    .ms-dt-foot .pagination {
        margin: 0;
    }

    /* Satu animasi saja: baris baru menandakan produk berhasil ditambahkan. */
    @keyframes ms-row-enter {
        from { background: var(--ms-accent-soft); }
        to { background: transparent; }
    }

    .ms-table tbody tr.is-new td {
        animation: ms-row-enter .6s ease-out;
    }

    /* ===== Responsif ===== */
    @media (max-width: 991.98px) {
        .ms-route {
            grid-template-columns: minmax(0, 1fr) 40px minmax(0, 1fr);
        }
    }

    @media (max-width: 767.98px) {
        .ms-head {
            align-items: flex-start;
        }

        .ms-route {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .ms-route__arrow {
            justify-self: center;
            margin: 2px 0;
            transform: rotate(90deg);
        }

        .ms-dates {
            grid-template-columns: 1fr;
        }

        .ms-panel__head {
            flex-direction: column;
        }

        .ms-panel__head .ms-btn {
            width: 100%;
        }

        .ms-table--items,
        .ms-table--items tbody,
        .ms-table--items tr,
        .ms-table--items td {
            display: block;
            width: 100%;
        }

        .ms-table--items thead {
            display: none;
        }

        .ms-table--items tr {
            padding: 14px 16px;
            border-bottom: 1px solid var(--ms-rule);
        }

        .ms-table--items tbody tr:last-child {
            border-bottom: 0;
        }

        .ms-table--items td {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 4px 0;
            border: 0;
        }

        .ms-table--items td::before {
            content: attr(data-label);
            flex: none;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .045em;
            text-transform: uppercase;
            color: var(--ms-muted);
        }

        .ms-table--items td[data-label="Barang"] {
            display: block;
            padding-bottom: 10px;
        }

        .ms-table--items td[data-label="Barang"]::before,
        .ms-table--items td[data-label="Aksi"]::before {
            display: none;
        }

        .ms-table--items td[data-label="Aksi"] {
            justify-content: flex-end;
            padding-top: 8px;
        }

        .ms-table--items td .ms-control {
            width: min(62%, 220px);
        }

        /* Stok tetap terlihat di kolom Barang; catatan ini hanya untuk desktop. */
        .ms-table--items td[data-label="Jumlah kirim"] .ms-help {
            display: none;
        }

        .ms-summary {
            flex-direction: column;
            align-items: stretch;
        }

        .ms-summary .ms-btn {
            width: 100%;
        }
    }

    @media (max-width: 575.98px) {
        .ms-head h1 {
            font-size: 1.3rem;
        }

        .ms-context {
            width: 100%;
        }

        .ms-table--picker th:nth-child(5),
        .ms-table--picker td:nth-child(5),
        .ms-table--picker th:nth-child(6),
        .ms-table--picker td:nth-child(6) {
            display: none;
        }

        .ms-modal__toolbar {
            flex-direction: column;
            align-items: stretch;
        }

        .ms-search {
            max-width: none;
        }

        .ms-pick-count {
            align-self: flex-end;
        }

        .ms-modal .modal-footer {
            flex-wrap: wrap;
        }

        .ms-modal__foot-note {
            flex-basis: 100%;
            margin: 0 0 4px;
        }

        .ms-modal .modal-footer .ms-btn {
            flex: 1;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .mutasi-stok *,
        .mutasi-stok *::before,
        .mutasi-stok *::after {
            animation: none !important;
            transition: none !important;
        }
    }

    @media (forced-colors: active) {
        .ms-panel,
        .ms-control,
        .ms-empty,
        .ms-table th,
        .ms-table td,
        .ms-btn {
            border-color: CanvasText;
        }

        .ms-stock,
        .ms-tag {
            border: 1px solid CanvasText;
        }
    }
</style>
