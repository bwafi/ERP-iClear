<?php

/**
 * Tema halaman Sparepart Keluar (/sparepart_keluar).
 *
 * Semua selector berada di bawah `.sk-page` / `.sk-modal` agar tidak bocor ke
 * modul lain. Warna diturunkan dari token tema aplikasi; satu aksen (biru)
 * hanya untuk aksi utama, fokus, dan state terpilih.
 *
 * Kontras teks kecil dijaga >= 4.5:1 di terang maupun gelap:
 *   --sk-muted  #5b6672 di putih (6.55:1), #8fa0b8 di gelap
 *   --sk-ink    #24303f di putih, var(--bs-body-color) di gelap
 * Tombol primary memakai #2f5fe0 (putih 5.2:1), bukan primary template yang
 * lebih terang.
 */
?>

<style>
    /* ===== Token ===== */
    /* Modal dirender di #modalContainer, di luar .sk-page, jadi token diduplikasi di sini. */
    .sk-page,
    .sk-modal {
        --sk-surface: var(--bs-card-bg, #fff);
        --sk-subtle: #f6f8fc;
        --sk-rule: var(--bs-border-color, #e5eaf1);
        --sk-rule-strong: #d7dfea;
        --sk-hover: rgba(47, 95, 224, .045);
        --sk-ink: #24303f;
        --sk-strong: #142033;
        --sk-muted: #5b6672;
        --sk-accent: #2f5fe0;
        --sk-accent-solid: #2f5fe0;
        --sk-accent-hover: #244fc4;
        --sk-accent-ink: #244fc4;
        --sk-accent-soft: rgba(47, 95, 224, .08);
        --sk-success: #0f7a46;
        --sk-success-soft: #e8f7ef;
        --sk-warn: #8a5300;
        --sk-warn-soft: #fff4d6;
        --sk-info: #0b6b8a;
        --sk-info-soft: #e6f6fb;
        --sk-danger: #b02a37;
        --sk-danger-soft: #fdecee;
        --sk-radius: 16px;
        --sk-radius-sm: 10px;
        --sk-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 18px 42px -36px rgba(16, 24, 40, .35);
        color: var(--sk-ink);
        font-size: .875rem;
    }

    html[data-bs-theme="dark"] .sk-page,
    html[data-bs-theme="dark"] .sk-modal {
        --sk-surface: var(--bs-card-bg, #2a3547);
        --sk-subtle: rgba(255, 255, 255, .035);
        --sk-rule: var(--bs-border-color, rgba(255, 255, 255, .12));
        --sk-rule-strong: rgba(255, 255, 255, .18);
        --sk-hover: rgba(255, 255, 255, .04);
        --sk-ink: var(--bs-body-color, #cfd8e3);
        --sk-strong: #f1f5f9;
        --sk-muted: #8fa0b8;
        --sk-accent: #8ab4ff;
        --sk-accent-solid: #2f6fe4;
        --sk-accent-hover: #3b7bf0;
        --sk-accent-ink: #a9c6ff;
        --sk-accent-soft: rgba(138, 180, 255, .12);
        --sk-success: #6fdd9d;
        --sk-success-soft: rgba(111, 221, 157, .12);
        --sk-warn: #ffcf6e;
        --sk-warn-soft: rgba(255, 207, 110, .12);
        --sk-info: #7dd3f0;
        --sk-info-soft: rgba(125, 211, 240, .12);
        --sk-danger: #ff9aa2;
        --sk-danger-soft: rgba(255, 154, 162, .12);
        --sk-shadow: none;
    }

    .sk-page,
    .sk-modal {
        color: var(--sk-ink);
    }

    .sk-page .text-muted,
    .sk-modal .text-muted {
        color: var(--sk-muted) !important;
    }

    .sk-page ::selection,
    .sk-modal ::selection {
        background: var(--sk-accent-soft);
        color: var(--sk-strong);
    }

    .sk-page input,
    .sk-page select,
    .sk-modal input,
    .sk-modal select {
        caret-color: var(--sk-accent);
    }

    /* ===== Kepala halaman ===== */
    .sk-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 16px;
        padding: 24px;
        border: 1px solid var(--sk-rule);
        border-radius: var(--sk-radius);
        background: var(--sk-surface);
        box-shadow: var(--sk-shadow);
    }

    .sk-crumb {
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 0 0 8px;
        padding: 0;
        list-style: none;
        font-size: .75rem;
        color: var(--sk-muted);
    }

    .sk-crumb a {
        color: var(--sk-muted);
        text-decoration: none;
    }

    .sk-crumb a:hover {
        color: var(--sk-accent-ink);
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .sk-title {
        margin: 0;
        color: var(--sk-strong);
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -.02em;
        line-height: 1.2;
    }

    .sk-head p {
        max-width: 65ch;
        margin: 6px 0 0;
        color: var(--sk-muted);
        font-size: .875rem;
        line-height: 1.55;
    }

    .sk-head__meta {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
        flex: 0 0 auto;
    }

    .sk-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border: 1px solid var(--sk-rule);
        border-radius: 999px;
        background: var(--sk-subtle);
        color: var(--sk-muted);
        font-size: .75rem;
        font-weight: 500;
        line-height: 1.2;
        white-space: nowrap;
    }

    .sk-chip iconify-icon {
        color: var(--sk-accent-ink);
    }

    .sk-chip strong {
        color: var(--sk-strong);
        font-weight: 650;
        font-variant-numeric: tabular-nums;
    }

    .sk-chip--accent {
        border-color: transparent;
        background: var(--sk-accent-soft);
        color: var(--sk-accent-ink);
    }

    .sk-chip--accent iconify-icon,
    .sk-chip--accent strong {
        color: var(--sk-accent-ink);
    }

    /* ===== Panel data ===== */
    .sk-panel {
        position: relative;
        overflow: hidden;
        border: 1px solid var(--sk-rule);
        border-radius: var(--sk-radius);
        background: var(--sk-surface);
        box-shadow: var(--sk-shadow);
    }

    .sk-panel__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        padding: 20px 24px 16px;
    }

    .sk-panel__head h2 {
        margin: 0;
        color: var(--sk-strong);
        font-size: 1rem;
        font-weight: 650;
        letter-spacing: -.01em;
    }

    .sk-panel__head p {
        margin: 4px 0 0;
        color: var(--sk-muted);
        font-size: .8125rem;
        line-height: 1.5;
    }

    .sk-search {
        position: relative;
        flex: 0 1 320px;
    }

    .sk-search iconify-icon {
        position: absolute;
        top: 50%;
        left: 12px;
        color: var(--sk-muted);
        transform: translateY(-50%);
        pointer-events: none;
    }

    .sk-search input {
        width: 100%;
        min-height: 40px;
        padding: 9px 12px 9px 36px;
        border: 1px solid var(--sk-rule-strong);
        border-radius: var(--sk-radius-sm);
        background: var(--sk-subtle);
        color: var(--sk-ink);
        font-size: .8125rem;
    }

    .sk-search input::placeholder {
        color: var(--sk-muted);
    }

    .sk-search input:focus {
        outline: none;
        border-color: var(--sk-accent);
        background: var(--sk-surface);
        box-shadow: 0 0 0 3px var(--sk-accent-soft);
    }

    /* ===== Filter ===== */
    .sk-filter {
        display: grid;
        grid-template-columns: minmax(200px, 280px) minmax(170px, 220px) 120px auto;
        justify-content: start;
        align-items: end;
        gap: 14px;
        padding: 16px 24px;
        border-block: 1px solid var(--sk-rule);
        background: var(--sk-subtle);
    }

    .sk-field label,
    .sk-field__label {
        display: block;
        margin-bottom: 6px;
        color: var(--sk-muted);
        font-size: .75rem;
        font-weight: 600;
    }

    .sk-input {
        width: 100%;
        min-height: 40px;
        padding: 8px 11px;
        border: 1px solid var(--sk-rule-strong);
        border-radius: var(--sk-radius-sm);
        background: var(--sk-surface);
        color: var(--sk-ink);
        font-size: .8125rem;
    }

    .sk-input:focus {
        outline: none;
        border-color: var(--sk-accent);
        box-shadow: 0 0 0 3px var(--sk-accent-soft);
    }

    .sk-input:disabled {
        cursor: not-allowed;
        opacity: .7;
    }

    .sk-filter__actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
    }

    /* ===== Tombol ===== */
    .sk-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 40px;
        padding: 8px 14px;
        border: 1px solid transparent;
        border-radius: var(--sk-radius-sm);
        font-size: .8125rem;
        font-weight: 600;
        line-height: 1.2;
        text-decoration: none;
        cursor: pointer;
        transition: background-color .16s ease, border-color .16s ease, color .16s ease;
    }

    .sk-btn:focus-visible {
        outline: 2px solid var(--sk-accent);
        outline-offset: 2px;
    }

    .sk-btn--primary {
        background: var(--sk-accent-solid);
        color: #fff;
    }

    .sk-btn--primary:hover,
    .sk-btn--primary:focus {
        background: var(--sk-accent-hover);
        color: #fff;
    }

    .sk-btn--ghost {
        border-color: var(--sk-rule-strong);
        background: transparent;
        color: var(--sk-ink);
    }

    .sk-btn--ghost:hover,
    .sk-btn--ghost:focus {
        background: var(--sk-surface);
        color: var(--sk-strong);
    }

    .sk-btn--danger {
        background: var(--sk-danger);
        color: #fff;
    }

    .sk-btn--danger:hover,
    .sk-btn--danger:focus {
        background: #92212c;
        color: #fff;
    }

    .sk-btn--icon {
        min-width: 34px;
        min-height: 34px;
        padding: 6px;
        border-color: var(--sk-rule-strong);
        background: transparent;
        color: var(--sk-muted);
    }

    .sk-btn--icon:hover,
    .sk-btn--icon:focus {
        border-color: var(--sk-accent);
        background: var(--sk-accent-soft);
        color: var(--sk-accent-ink);
    }

    .sk-btn--icon.sk-btn--edit:hover,
    .sk-btn--icon.sk-btn--edit:focus {
        border-color: var(--sk-warn);
        background: var(--sk-warn-soft);
        color: var(--sk-warn);
    }

    .sk-btn--icon.sk-btn--delete:hover,
    .sk-btn--icon.sk-btn--delete:focus {
        border-color: var(--sk-danger);
        background: var(--sk-danger-soft);
        color: var(--sk-danger);
    }

    /* ===== Tabel ===== */
    .sk-table-scroll {
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--sk-rule-strong) transparent;
    }

    .sk-table-scroll::-webkit-scrollbar {
        height: 8px;
    }

    .sk-table-scroll::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: var(--sk-rule-strong);
    }

    .sk-table {
        width: 100%;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .sk-table th {
        padding: 10px 16px;
        border-bottom: 1px solid var(--sk-rule);
        background: var(--sk-subtle);
        color: var(--sk-muted);
        font-size: .6875rem;
        font-weight: 650;
        letter-spacing: .06em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .sk-table th:first-child {
        padding-left: 24px;
    }

    .sk-table th:last-child {
        padding-right: 24px;
        text-align: right;
    }

    .sk-table td {
        padding: 14px 16px;
        border-bottom: 1px solid var(--sk-rule);
        color: var(--sk-ink);
        vertical-align: middle;
    }

    .sk-table td:first-child {
        padding-left: 24px;
    }

    .sk-table td:last-child {
        padding-right: 24px;
        text-align: right;
    }

    .sk-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .sk-table tbody tr:hover td {
        background: var(--sk-hover);
    }

    .sk-table .sk-num {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sk-doc {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
        min-width: 0;
    }

    .sk-code {
        display: block;
        color: var(--sk-strong);
        font-size: .8125rem;
        font-weight: 650;
        letter-spacing: .01em;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sk-strong {
        display: block;
        color: var(--sk-strong);
        font-size: .8125rem;
        font-weight: 600;
        line-height: 1.4;
    }

    .sk-sub {
        display: block;
        margin-top: 3px;
        color: var(--sk-muted);
        font-size: .75rem;
        line-height: 1.4;
    }

    .sk-date {
        display: block;
        color: var(--sk-strong);
        font-size: .8125rem;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sk-money {
        color: var(--sk-strong);
        font-weight: 600;
        white-space: nowrap;
    }

    .sk-tag {
        display: inline-flex;
        align-items: center;
        padding: 2px 7px;
        border-radius: 999px;
        font-size: .6875rem;
        font-weight: 600;
        line-height: 1.4;
        white-space: nowrap;
    }

    .sk-tag--sale {
        background: var(--sk-info-soft);
        color: var(--sk-info);
    }

    .sk-tag--claim {
        background: var(--sk-warn-soft);
        color: var(--sk-warn);
    }

    .sk-unit {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        max-width: 22ch;
        padding: 4px 8px;
        border: 1px solid var(--sk-rule);
        border-radius: 999px;
        background: var(--sk-subtle);
        color: var(--sk-muted);
        font-size: .75rem;
        line-height: 1.2;
        white-space: nowrap;
    }

    .sk-unit iconify-icon {
        flex: 0 0 auto;
        color: var(--sk-accent-ink);
    }

    .sk-unit span {
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sk-actions {
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
    }

    /* ===== Footer & state ===== */
    .sk-dt-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        padding: 14px 24px;
        border-top: 1px solid var(--sk-rule);
        color: var(--sk-muted);
        font-size: .75rem;
    }

    .sk-dt-foot .dataTables_info {
        padding: 0;
        color: var(--sk-muted);
    }

    .sk-dt-foot .dataTables_paginate {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .sk-dt-foot .dataTables_paginate > span {
        display: inline-flex;
        gap: 4px;
    }

    .sk-dt-foot .paginate_button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 30px;
        padding: 5px 9px;
        border: 1px solid var(--sk-rule);
        border-radius: 8px;
        background: var(--sk-surface);
        color: var(--sk-ink);
        font-size: .75rem;
        text-decoration: none;
        cursor: pointer;
    }

    .sk-dt-foot .paginate_button:hover {
        border-color: var(--sk-accent);
        background: var(--sk-accent-soft);
        color: var(--sk-accent-ink);
    }

    .sk-dt-foot .paginate_button.current {
        border-color: var(--sk-accent-solid);
        background: var(--sk-accent-solid);
        color: #fff;
    }

    .sk-dt-foot .paginate_button.disabled {
        color: var(--sk-muted);
        cursor: not-allowed;
        opacity: .55;
    }

    .sk-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 16px;
        color: var(--sk-muted);
        font-size: .8125rem;
    }

    .sk-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 52px 24px;
        text-align: center;
    }

    .sk-empty__icon {
        display: grid;
        width: 48px;
        height: 48px;
        place-items: center;
        border-radius: 14px;
        background: var(--sk-accent-soft);
        color: var(--sk-accent-ink);
    }

    .sk-empty h3 {
        margin: 4px 0 0;
        color: var(--sk-strong);
        font-size: .9375rem;
        font-weight: 650;
    }

    .sk-empty p {
        max-width: 42ch;
        margin: 0;
        color: var(--sk-muted);
        font-size: .8125rem;
        line-height: 1.55;
    }

    .sk-page .dataTables_processing {
        position: absolute;
        inset: 0;
        z-index: 2;
        width: auto;
        height: auto;
        margin: 0;
        padding: 0;
        border: 0;
        background: rgba(255, 255, 255, .72);
        color: var(--sk-muted);
        text-align: left;
    }

    html[data-bs-theme="dark"] .sk-page .dataTables_processing {
        background: rgba(31, 41, 55, .72);
    }

    .sk-page .dataTables_processing > .sk-loading {
        position: absolute;
        top: 50%;
        left: 50%;
        padding: 10px 16px;
        border: 1px solid var(--sk-rule);
        border-radius: var(--sk-radius-sm);
        background: var(--sk-surface);
        box-shadow: var(--sk-shadow);
        transform: translate(-50%, -50%);
    }

    /* ===== Modal ===== */
    .sk-modal .modal-content {
        border: 1px solid var(--sk-rule);
        border-radius: var(--sk-radius);
        background: var(--sk-surface);
        color: var(--sk-ink);
    }

    .sk-modal .modal-header {
        align-items: flex-start;
        gap: 12px;
        border-bottom: 1px solid var(--sk-rule);
        background: var(--sk-subtle);
    }

    .sk-modal__title {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .sk-modal__icon {
        display: grid;
        flex: 0 0 40px;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 12px;
        background: var(--sk-accent-soft);
        color: var(--sk-accent-ink);
    }

    .sk-modal .modal-title {
        color: var(--sk-strong);
        font-size: 1rem;
        font-weight: 650;
    }

    .sk-modal__title p {
        margin: 2px 0 0;
        color: var(--sk-muted);
        font-size: .75rem;
    }

    .sk-modal .modal-body {
        padding: 20px 24px;
    }

    .sk-modal .modal-footer {
        gap: 8px;
        border-top: 1px solid var(--sk-rule);
    }

    .sk-modal--danger .modal-header {
        border-bottom-color: transparent;
        background: var(--sk-danger-soft);
    }

    .sk-modal--danger .sk-modal__icon {
        background: var(--sk-danger-soft);
        color: var(--sk-danger);
    }

    .sk-detail {
        display: grid;
        gap: 0;
        margin: 0;
    }

    .sk-detail__row {
        display: grid;
        grid-template-columns: 160px minmax(0, 1fr);
        gap: 16px;
        padding: 10px 0;
        border-bottom: 1px solid var(--sk-rule);
    }

    .sk-detail__row:last-child {
        border-bottom: 0;
    }

    .sk-detail dt {
        color: var(--sk-muted);
        font-size: .75rem;
        font-weight: 600;
    }

    .sk-detail dd {
        margin: 0;
        color: var(--sk-strong);
        font-size: .8125rem;
        font-weight: 500;
        overflow-wrap: anywhere;
    }

    .sk-form .form-label {
        margin-bottom: 6px;
        color: var(--sk-muted);
        font-size: .75rem;
        font-weight: 600;
    }

    .sk-form .form-control {
        min-height: 40px;
        border-color: var(--sk-rule-strong);
        border-radius: var(--sk-radius-sm);
        background: var(--sk-surface);
        color: var(--sk-ink);
        font-size: .8125rem;
    }

    .sk-form .form-control:focus {
        border-color: var(--sk-accent);
        box-shadow: 0 0 0 3px var(--sk-accent-soft);
    }

    .sk-form .form-control[readonly] {
        background: var(--sk-subtle);
        color: var(--sk-muted);
    }

    .sk-modal .alert {
        margin-bottom: 0;
        border-radius: var(--sk-radius-sm);
    }

    /* ===== Responsif ===== */
    @media (max-width: 991.98px) {
        .sk-head {
            flex-direction: column;
        }

        .sk-head__meta {
            justify-content: flex-start;
        }

        .sk-filter {
            grid-template-columns: 1fr 1fr;
        }

        .sk-filter__actions {
            grid-column: 1 / -1;
            justify-content: flex-start;
        }
    }

    @media (max-width: 767.98px) {
        .sk-panel__head {
            flex-direction: column;
        }

        .sk-search {
            width: 100%;
            flex-basis: auto;
        }

        .sk-table-scroll {
            overflow: visible;
        }

        .sk-table,
        .sk-table tbody,
        .sk-table tr,
        .sk-table td {
            display: block;
            width: 100%;
        }

        .sk-table thead {
            display: none;
        }

        .sk-table tr {
            padding: 14px 18px;
            border-bottom: 1px solid var(--sk-rule);
        }

        .sk-table tbody tr:last-child {
            border-bottom: 0;
        }

        .sk-table td,
        .sk-table td:first-child,
        .sk-table td:last-child,
        .sk-table .sk-num {
            display: grid;
            grid-template-columns: 42% minmax(0, 1fr);
            align-items: start;
            column-gap: 16px;
            row-gap: 2px;
            padding: 5px 0;
            border: 0;
            text-align: left;
        }

        .sk-table td::before {
            grid-column: 1;
            grid-row: 1 / span 4;
            color: var(--sk-muted);
            font-size: .6875rem;
            font-weight: 650;
            letter-spacing: .05em;
            text-transform: uppercase;
            content: attr(data-label);
        }

        .sk-table td > * {
            grid-column: 2;
        }

        .sk-table td[data-label="Aksi"] {
            display: block;
            padding-top: 12px;
        }

        .sk-table td[data-label="Aksi"]::before {
            display: none;
        }

        .sk-table td[colspan] {
            display: block;
            padding: 0;
        }

        .sk-table td[colspan]::before {
            display: none;
        }

        .sk-actions {
            width: 100%;
            justify-content: stretch;
        }

        .sk-actions .sk-btn {
            flex: 1;
        }

        .sk-detail__row {
            grid-template-columns: 1fr;
            gap: 2px;
        }
    }

    @media (max-width: 575.98px) {
        .sk-head {
            padding: 20px;
        }

        .sk-filter {
            grid-template-columns: 1fr;
        }

        .sk-filter__actions {
            grid-column: auto;
        }

        .sk-filter__actions .sk-btn {
            width: 100%;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .sk-page *,
        .sk-modal * {
            transition: none !important;
        }
    }
</style>
