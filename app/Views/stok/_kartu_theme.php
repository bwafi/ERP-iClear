<?php

/**
 * Design system halaman Kartu Stok (/kartu_stok).
 *
 * Semua selector berada di bawah `.kartu-stok` (dan modal HPP) supaya tidak
 * bocor ke modul lain. Warna diturunkan dari token tema aplikasi; satu aksen
 * (biru primary) hanya untuk fokus dan aksi. Nilai kontras teks kecil:
 *   --ks-ink   #626d7b di putih (5.0:1)
 *   --ks-muted #5b6672 di putih (5.85:1)
 *
 * Skala font mengikuti utilitas `fs-*` template (terbalik dari Bootstrap):
 *   .75rem label, .8125rem isi tabel, 1.5rem judul halaman.
 */
?>

<style>
    /* ===== Token ===== */
    .kartu-stok {
        --ks-surface: var(--bs-card-bg, #fff);
        --ks-subtle: #f6f8fa;
        --ks-rule: var(--bs-border-color, #e6ecf1);
        --ks-rule-strong: #d7e0e8;
        --ks-hover: rgba(0, 119, 196, .045);
        --ks-ink: #626d7b;
        --ks-strong: #1c2733;
        --ks-muted: #5b6672;
        --ks-accent: #0077c4;
        --ks-accent-ink: #0067a8;
        --ks-accent-soft: rgba(0, 119, 196, .08);
        --ks-ok-dot: #17a05e;
        --ks-ok-ink: #0f6b3e;
        --ks-danger-ink: #b02a37;
        --ks-warn-soft: rgba(192, 118, 0, .14);
        --ks-warn-ink: #8a5300;
        --ks-radius: 14px;
        --ks-radius-sm: 9px;
        --ks-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 18px 40px -36px rgba(16, 24, 40, .35);
        width: 100%;
        color: var(--ks-ink);
        font-size: .875rem;
    }

    html[data-bs-theme="dark"] .kartu-stok {
        --ks-surface: var(--bs-card-bg, #2a3547);
        --ks-subtle: rgba(255, 255, 255, .035);
        --ks-rule: var(--bs-border-color, rgba(255, 255, 255, .12));
        --ks-rule-strong: rgba(255, 255, 255, .18);
        --ks-hover: rgba(255, 255, 255, .04);
        --ks-ink: var(--bs-body-color, #cfd8e3);
        --ks-strong: #f1f5f9;
        --ks-muted: #8fa0b8;
        --ks-accent: #8ab4ff;
        --ks-accent-ink: #8ab4ff;
        --ks-accent-soft: rgba(47, 111, 228, .16);
        --ks-ok-dot: #3ecf8e;
        --ks-ok-ink: #6fdd9d;
        --ks-danger-ink: #ff9aa2;
        --ks-warn-soft: rgba(255, 207, 110, .12);
        --ks-warn-ink: #ffcf6e;
        --ks-shadow: none;
    }

    .kartu-stok ::selection {
        background: var(--ks-accent-soft);
        color: var(--ks-strong);
    }

    .kartu-stok :focus-visible {
        outline: 2px solid var(--ks-accent);
        outline-offset: 2px;
    }

    .kartu-stok .text-muted {
        color: var(--ks-muted) !important;
    }

    /* ===== Kepala halaman ===== */
    .ks-head {
        margin-bottom: 12px;
        padding: 18px 20px;
        border: 1px solid var(--ks-rule);
        border-radius: var(--ks-radius);
        background: var(--ks-surface);
        box-shadow: var(--ks-shadow);
    }

    .ks-head h1 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--ks-strong);
    }

    .ks-head p {
        max-width: 66ch;
        margin: 4px 0 0;
        color: var(--ks-muted);
        font-size: .875rem;
        line-height: 1.5;
    }

    .ks-crumb {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
        font-size: .75rem;
        color: var(--ks-muted);
    }

    .ks-crumb a {
        color: var(--ks-muted);
        text-decoration: none;
    }

    .ks-crumb a:hover {
        color: var(--ks-accent-ink);
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    /* ===== Strip ringkasan ===== */
    .ks-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1px;
        margin-bottom: 4px;
        border: 1px solid var(--ks-rule);
        border-radius: var(--ks-radius);
        background: var(--ks-rule);
        overflow: hidden;
    }

    .ks-stat {
        padding: 15px 20px 16px;
        background: var(--ks-surface);
    }

    .ks-stat__label {
        display: block;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--ks-muted);
    }

    .ks-stat__value {
        display: block;
        margin-top: 4px;
        font-size: 1.25rem;
        font-weight: 650;
        letter-spacing: -.015em;
        line-height: 1.25;
        color: var(--ks-strong);
        font-variant-numeric: tabular-nums;
    }

    .ks-stat__note {
        display: block;
        margin-top: 1px;
        font-size: .72rem;
        color: var(--ks-muted);
    }

    /* ===== Toolbar ===== */
    .ks-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 12px;
        margin: 20px 0 12px;
    }

    .ks-filters {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 10px;
    }

    .ks-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .ks-field > span {
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--ks-muted);
    }

    .kartu-stok .ks-select {
        height: 38px;
        min-width: 160px;
        padding-block: 0;
        border-color: var(--ks-rule-strong);
        border-radius: var(--ks-radius-sm);
        background-color: var(--ks-surface);
        color: var(--ks-ink);
        font-size: .8125rem;
    }

    .kartu-stok .ks-select:focus {
        border-color: var(--ks-accent);
        box-shadow: 0 0 0 3px var(--ks-accent-soft);
    }

    .ks-tools {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .ks-search {
        position: relative;
        display: flex;
        align-items: center;
    }

    .ks-search iconify-icon {
        position: absolute;
        left: 11px;
        font-size: .95rem;
        color: var(--ks-muted);
        pointer-events: none;
    }

    .ks-search input {
        width: 240px;
        height: 38px;
        padding: 0 12px 0 34px;
        border: 1px solid var(--ks-rule-strong);
        border-radius: var(--ks-radius-sm);
        background: var(--ks-surface);
        color: var(--ks-ink);
        font-size: .8125rem;
    }

    .ks-search input::placeholder {
        color: var(--ks-muted);
        opacity: 1;
    }

    .ks-search input:focus {
        border-color: var(--ks-accent);
        box-shadow: 0 0 0 3px var(--ks-accent-soft);
        outline: 0;
    }

    .ks-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        height: 38px;
        padding: 0 14px;
        border: 1px solid var(--ks-rule-strong);
        border-radius: var(--ks-radius-sm);
        background: transparent;
        color: var(--ks-strong);
        font-size: .8125rem;
        font-weight: 650;
        white-space: nowrap;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }

    .ks-btn iconify-icon {
        font-size: 1.05em;
    }

    .ks-btn:hover {
        border-color: var(--ks-accent);
        background: var(--ks-accent-soft);
        color: var(--ks-accent-ink);
    }

    /* ===== Lembar tabel ===== */
    .ks-sheet {
        border: 1px solid var(--ks-rule);
        border-radius: var(--ks-radius);
        background: var(--ks-surface);
        box-shadow: var(--ks-shadow);
        overflow: hidden;
    }

    .ks-table-wrap {
        position: relative;
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--ks-rule-strong) transparent;
    }

    .ks-table-wrap::-webkit-scrollbar {
        height: 8px;
    }

    .ks-table-wrap::-webkit-scrollbar-thumb {
        border: 2px solid transparent;
        border-radius: 999px;
        background: var(--ks-rule-strong);
        background-clip: content-box;
    }

    .ks-table {
        width: 100%;
        min-width: 760px;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
        font-size: .8125rem;
    }

    .ks-table th {
        position: sticky;
        top: 0;
        z-index: 1;
        padding: 10px 14px;
        border-bottom: 1px solid var(--ks-rule);
        background: var(--ks-surface);
        color: var(--ks-muted);
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        text-align: left;
        white-space: nowrap;
    }

    .ks-table td {
        padding: 9px 14px;
        border-bottom: 1px solid var(--ks-rule);
        color: var(--ks-ink);
        vertical-align: middle;
    }

    .ks-table tbody tr:last-child td {
        border-bottom: 0;
    }

    /* Baris detail memakai panel penuh, bukan sel ber-padding. */
    .ks-table tr.child > td {
        padding: 0;
        border-bottom: 0;
    }

    .ks-table tbody tr:hover > td {
        background: var(--ks-hover);
    }

    .ks-table .ks-num {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .ks-table .ks-center {
        text-align: center;
    }

    .ks-code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: .75rem;
        letter-spacing: .01em;
        color: var(--ks-muted);
        white-space: nowrap;
    }

    .ks-name {
        display: block;
        font-weight: 600;
        color: var(--ks-strong);
        line-height: 1.35;
    }

    .ks-sub {
        display: block;
        margin-top: 1px;
        font-size: .72rem;
        color: var(--ks-muted);
    }

    .ks-sub--mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }

    .ks-stock {
        font-weight: 650;
        color: var(--ks-strong);
    }

    .ks-stock.is-zero {
        font-weight: 500;
        color: var(--ks-muted);
    }

    .ks-stock.is-neg {
        color: var(--ks-danger-ink);
    }

    /* Status PPN: titik + teks, bukan badge. */
    .ks-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: .75rem;
        color: var(--ks-ink);
        white-space: nowrap;
    }

    .ks-tag::before {
        content: "";
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--ks-rule-strong);
    }

    .ks-tag--on::before {
        background: var(--ks-ok-dot);
    }

    .ks-tag--off {
        color: var(--ks-muted);
    }

    /* Sel aksi: detail + status */
    .ks-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
    }

    .ks-detail-btn {
        display: inline-grid;
        place-items: center;
        width: 26px;
        height: 26px;
        border: 1px solid transparent;
        border-radius: 8px;
        background: transparent;
        color: var(--ks-muted);
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }

    .ks-detail-btn iconify-icon {
        font-size: .95rem;
        transition: transform .18s ease;
    }

    .ks-detail-btn:hover {
        border-color: var(--ks-rule);
        background: var(--ks-subtle);
        color: var(--ks-accent-ink);
    }

    tr.shown .ks-detail-btn {
        border-color: var(--ks-rule);
        background: var(--ks-subtle);
        color: var(--ks-accent-ink);
    }

    tr.shown .ks-detail-btn iconify-icon {
        transform: rotate(180deg);
    }

    /* Switch status aktif per unit */
    .ks-switch {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 3px 2px;
        border: 0;
        background: transparent;
        color: var(--ks-muted);
        font-size: .75rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .ks-switch:hover {
        color: var(--ks-strong);
    }

    .ks-switch__track {
        position: relative;
        flex: none;
        width: 30px;
        height: 18px;
        border-radius: 999px;
        background: var(--ks-rule-strong);
        transition: background-color .16s ease;
    }

    .ks-switch__thumb {
        position: absolute;
        top: 2px;
        left: 2px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .28);
        transition: left .16s ease;
    }

    .ks-switch[data-aktif="1"] .ks-switch__track {
        background: var(--ks-ok-dot);
    }

    .ks-switch[data-aktif="1"] .ks-switch__thumb {
        left: 14px;
    }

    /* ===== Detail baris ===== */
    .ks-detail {
        padding: 16px 18px 18px;
        border-bottom: 1px solid var(--ks-rule);
        background: var(--ks-subtle);
    }

    .ks-detail__grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        align-items: start;
        gap: 18px 32px;
    }

    .ks-detail__group h2 {
        margin: 0 0 6px;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--ks-muted);
    }

    .ks-detail__list {
        margin: 0;
    }

    .ks-detail__row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 16px;
        padding: 5px 0;
        border-bottom: 1px dashed var(--ks-rule);
    }

    .ks-detail__row:last-child {
        border-bottom: 0;
    }

    .ks-detail__row dt {
        font-size: .75rem;
        font-weight: 500;
        color: var(--ks-muted);
    }

    .ks-detail__row dd {
        margin: 0;
        font-size: .8125rem;
        color: var(--ks-ink);
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .ks-detail__row dd.is-strong {
        font-weight: 650;
        color: var(--ks-strong);
    }

    .ks-detail__row dd.is-pos {
        color: var(--ks-ok-ink);
    }

    .ks-detail__row dd.is-neg {
        color: var(--ks-danger-ink);
    }

    .ks-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: .75rem;
    }

    .ks-none {
        color: var(--ks-muted);
    }

    .ks-edit-hpp {
        margin-left: 8px;
        padding: 2px 8px;
        border: 1px solid var(--ks-rule-strong);
        border-radius: 7px;
        background: var(--ks-surface);
        color: var(--ks-accent-ink);
        font-size: .72rem;
        font-weight: 650;
        font-style: normal;
    }

    .ks-edit-hpp:hover {
        border-color: var(--ks-accent);
        background: var(--ks-accent-soft);
    }

    /* ===== Footer DataTables ===== */
    .ks-dt-foot {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px 16px;
        padding: 11px 16px;
        border-top: 1px solid var(--ks-rule);
        font-size: .75rem;
        color: var(--ks-muted);
    }

    .ks-dt-foot .dataTables_length {
        order: 1;
    }

    .ks-dt-foot .dataTables_length select {
        height: 30px;
        margin: 0 4px;
        padding: 0 26px 0 8px;
        border: 1px solid var(--ks-rule-strong);
        border-radius: 7px;
        background-color: var(--ks-surface);
        color: var(--ks-ink);
        font-size: .75rem;
    }

    .ks-dt-foot .dataTables_info {
        order: 2;
        font-variant-numeric: tabular-nums;
    }

    .ks-dt-foot .dataTables_paginate {
        order: 3;
        display: flex;
        align-items: center;
        gap: 2px;
        margin-left: auto;
    }

    .ks-dt-foot .paginate_button {
        display: inline-grid;
        place-items: center;
        min-width: 30px;
        height: 30px;
        padding: 0 8px;
        border-radius: 7px;
        color: var(--ks-ink);
        cursor: pointer;
    }

    .ks-dt-foot .paginate_button:hover {
        background: var(--ks-subtle);
        color: var(--ks-strong);
    }

    .ks-dt-foot .paginate_button.current {
        background: var(--ks-accent-soft);
        color: var(--ks-accent-ink);
        font-weight: 650;
    }

    .ks-dt-foot .paginate_button.disabled,
    .ks-dt-foot .paginate_button.disabled:hover {
        background: transparent;
        color: var(--ks-rule-strong);
        cursor: default;
    }

    .ks-dt-foot .ellipsis {
        padding: 0 4px;
        color: var(--ks-muted);
    }

    /* Status kosong & loading */
    .ks-table td.dataTables_empty {
        padding: 46px 16px;
        text-align: center;
        color: var(--ks-muted);
    }

    .kartu-stok .dataTables_processing {
        position: absolute;
        left: 50%;
        z-index: 5;
        margin-top: 12px;
        padding: 8px 14px;
        border: 1px solid var(--ks-rule);
        border-radius: 9px;
        background: var(--ks-surface);
        box-shadow: var(--ks-shadow);
        color: var(--ks-muted);
        font-size: .75rem;
        transform: translateX(-50%);
    }

    /* ===== Modal HPP ===== */
    .kartu-stok .modal-content {
        border-color: var(--ks-rule);
        border-radius: var(--ks-radius);
        background: var(--ks-surface);
        color: var(--ks-ink);
    }

    .kartu-stok .modal-header,
    .kartu-stok .modal-footer {
        border-color: var(--ks-rule);
    }

    .kartu-stok .modal-title {
        font-size: 1rem;
        font-weight: 650;
        color: var(--ks-strong);
    }

    .kartu-stok .form-label {
        font-size: .75rem;
        font-weight: 600;
        color: var(--ks-muted);
    }

    .kartu-stok .form-control {
        border-color: var(--ks-rule-strong);
        border-radius: var(--ks-radius-sm);
        background: var(--ks-surface);
        color: var(--ks-ink);
    }

    .kartu-stok .form-control:focus {
        border-color: var(--ks-accent);
        box-shadow: 0 0 0 3px var(--ks-accent-soft);
    }

    .kartu-stok .input-group-text {
        border-color: var(--ks-rule-strong);
        background: var(--ks-subtle);
        color: var(--ks-muted);
        font-size: .8125rem;
    }

    .kartu-stok .form-text {
        color: var(--ks-muted);
        font-size: .72rem;
    }

    .kartu-stok .btn-primary {
        border-color: var(--ks-accent);
        background-color: var(--ks-accent);
    }

    .kartu-stok .btn-primary:hover,
    .kartu-stok .btn-primary:focus {
        border-color: var(--ks-accent-ink);
        background-color: var(--ks-accent-ink);
    }

    .kartu-stok .btn-light {
        border-color: var(--ks-rule-strong);
        background: transparent;
        color: var(--ks-ink);
    }

    /* ===== Modal peringatan stok ===== */
    .kartu-stok .ks-warn {
        display: flex;
        gap: 12px;
        padding: 14px 16px;
        border-radius: var(--ks-radius-sm);
        background: var(--ks-warn-soft);
    }

    .kartu-stok .ks-warn iconify-icon {
        flex: none;
        margin-top: 1px;
        font-size: 1.15rem;
        color: var(--ks-warn-ink);
    }

    .ks-warn__target {
        margin: 0;
        font-size: .8125rem;
        font-weight: 650;
        color: var(--ks-strong);
    }

    .ks-warn__text {
        margin: 4px 0 0;
        font-size: .8125rem;
        line-height: 1.55;
        color: var(--ks-ink);
    }

    .ks-warn__figure {
        font-weight: 700;
        color: var(--ks-warn-ink);
        font-variant-numeric: tabular-nums;
    }

    .kartu-stok .modal-footer .btn-primary {
        color: #fff;
    }

    html[data-bs-theme="dark"] .kartu-stok .modal-footer .btn-primary {
        color: var(--ks-surface);
    }

    /* ===== Responsif ===== */
    @media (max-width: 1199.98px) {
        .ks-detail__grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .ks-head {
            padding: 16px;
        }

        .ks-stats {
            grid-template-columns: minmax(0, 1fr);
        }

        .ks-toolbar {
            align-items: stretch;
        }

        .ks-filters {
            display: flex;
            width: 100%;
        }

        .ks-field,
        .kartu-stok .ks-select {
            width: 100%;
            min-width: 0;
        }

        .ks-tools {
            width: 100%;
            flex-wrap: nowrap;
        }

        .ks-search {
            flex: 1;
        }

        .ks-search input {
            width: 100%;
        }

        .ks-btn {
            flex: none;
        }

        .ks-detail__grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
