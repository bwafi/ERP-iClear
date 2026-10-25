<?php

/**
 * Tema halaman Riwayat Service Garansi (/riwayat_service_garansi).
 *
 * Semua selector berada di bawah `.rsg-page` / `.rsg-modal` agar tidak bocor
 * ke modul lain. Warna diturunkan dari token tema aplikasi; aksen tunggal
 * (biru) hanya untuk aksi utama, fokus, dan state terpilih.
 *
 * Kontras teks kecil dijaga >= 4.5:1 di terang maupun gelap:
 *   --rsg-muted  #5b6672 di putih (5.85:1), #8fa0b8 di gelap
 *   --rsg-ink    #24303f di putih, var(--bs-body-color) di gelap
 * Tombol primary memakai #2f5fe0 (putih 5.2:1), bukan primary template
 * yang lebih terang.
 */
?>

<style>
    /* ===== Token ===== */
    .rsg-page {
        --rsg-surface: var(--bs-card-bg, #fff);
        --rsg-subtle: #f6f8fc;
        --rsg-rule: var(--bs-border-color, #e5eaf1);
        --rsg-rule-strong: #d7dfea;
        --rsg-hover: rgba(47, 95, 224, .045);
        --rsg-ink: #24303f;
        --rsg-strong: #142033;
        --rsg-muted: #5b6672;
        --rsg-accent: #2f5fe0;
        --rsg-accent-solid: #2f5fe0;
        --rsg-accent-hover: #244fc4;
        --rsg-accent-ink: #244fc4;
        --rsg-accent-soft: rgba(47, 95, 224, .08);
        --rsg-success: #0f7a46;
        --rsg-success-soft: #e8f7ef;
        --rsg-warn: #8a5300;
        --rsg-warn-soft: #fff4d6;
        --rsg-info: #0b6b8a;
        --rsg-info-soft: #e6f6fb;
        --rsg-danger: #b02a37;
        --rsg-danger-soft: #fdecee;
        --rsg-radius: 16px;
        --rsg-radius-sm: 10px;
        --rsg-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 18px 42px -36px rgba(16, 24, 40, .35);
        max-width: 1280px;
        margin-inline: auto;
        color: var(--rsg-ink);
        font-size: .875rem;
    }

    html[data-bs-theme="dark"] .rsg-page {
        --rsg-surface: var(--bs-card-bg, #2a3547);
        --rsg-subtle: rgba(255, 255, 255, .035);
        --rsg-rule: var(--bs-border-color, rgba(255, 255, 255, .12));
        --rsg-rule-strong: rgba(255, 255, 255, .18);
        --rsg-hover: rgba(255, 255, 255, .04);
        --rsg-ink: var(--bs-body-color, #cfd8e3);
        --rsg-strong: #f1f5f9;
        --rsg-muted: #8fa0b8;
        --rsg-accent: #8ab4ff;
        --rsg-accent-solid: #2f6fe4;
        --rsg-accent-hover: #3b7bf0;
        --rsg-accent-ink: #a9c6ff;
        --rsg-accent-soft: rgba(138, 180, 255, .12);
        --rsg-success: #6fdd9d;
        --rsg-success-soft: rgba(111, 221, 157, .12);
        --rsg-warn: #ffcf6e;
        --rsg-warn-soft: rgba(255, 207, 110, .12);
        --rsg-info: #7dd3f0;
        --rsg-info-soft: rgba(125, 211, 240, .12);
        --rsg-danger: #ff9aa2;
        --rsg-danger-soft: rgba(255, 154, 162, .12);
        --rsg-shadow: none;
    }

    .rsg-page,
    .rsg-modal {
        color: var(--rsg-ink);
    }

    .rsg-page .text-muted,
    .rsg-modal .text-muted {
        color: var(--rsg-muted) !important;
    }

    .rsg-page ::selection,
    .rsg-modal ::selection {
        background: var(--rsg-accent-soft);
        color: var(--rsg-strong);
    }

    .rsg-page input,
    .rsg-page select,
    .rsg-modal input,
    .rsg-modal select {
        caret-color: var(--rsg-accent);
    }

    /* ===== Kepala halaman ===== */
    .rsg-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 16px;
        padding: 24px;
        border: 1px solid var(--rsg-rule);
        border-radius: var(--rsg-radius);
        background: var(--rsg-surface);
        box-shadow: var(--rsg-shadow);
    }

    .rsg-crumb {
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 0 0 8px;
        padding: 0;
        list-style: none;
        font-size: .75rem;
        color: var(--rsg-muted);
    }

    .rsg-crumb a {
        color: var(--rsg-muted);
        text-decoration: none;
    }

    .rsg-crumb a:hover {
        color: var(--rsg-accent-ink);
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .rsg-title {
        margin: 0;
        color: var(--rsg-strong);
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -.02em;
        line-height: 1.2;
    }

    .rsg-head p {
        max-width: 65ch;
        margin: 6px 0 0;
        color: var(--rsg-muted);
        font-size: .875rem;
        line-height: 1.55;
    }

    .rsg-head__meta {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
        flex: 0 0 auto;
    }

    .rsg-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border: 1px solid var(--rsg-rule);
        border-radius: 999px;
        background: var(--rsg-subtle);
        color: var(--rsg-muted);
        font-size: .75rem;
        font-weight: 500;
        line-height: 1.2;
        white-space: nowrap;
    }

    .rsg-chip iconify-icon {
        color: var(--rsg-accent-ink);
    }

    .rsg-chip strong {
        color: var(--rsg-strong);
        font-weight: 650;
        font-variant-numeric: tabular-nums;
    }

    .rsg-chip--accent {
        border-color: transparent;
        background: var(--rsg-accent-soft);
        color: var(--rsg-accent-ink);
    }

    .rsg-chip--accent iconify-icon,
    .rsg-chip--accent strong {
        color: var(--rsg-accent-ink);
    }

    /* ===== Panel data ===== */
    .rsg-panel {
        overflow: hidden;
        border: 1px solid var(--rsg-rule);
        border-radius: var(--rsg-radius);
        background: var(--rsg-surface);
        box-shadow: var(--rsg-shadow);
    }

    .rsg-panel__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        padding: 20px 24px 16px;
    }

    .rsg-panel__head h2 {
        margin: 0;
        color: var(--rsg-strong);
        font-size: 1rem;
        font-weight: 650;
        letter-spacing: -.01em;
    }

    .rsg-panel__head p {
        margin: 4px 0 0;
        color: var(--rsg-muted);
        font-size: .8125rem;
        line-height: 1.5;
    }

    .rsg-search {
        position: relative;
        flex: 0 1 320px;
    }

    .rsg-search iconify-icon {
        position: absolute;
        top: 50%;
        left: 12px;
        color: var(--rsg-muted);
        transform: translateY(-50%);
        pointer-events: none;
    }

    .rsg-search input {
        width: 100%;
        min-height: 40px;
        padding: 9px 12px 9px 36px;
        border: 1px solid var(--rsg-rule-strong);
        border-radius: var(--rsg-radius-sm);
        background: var(--rsg-subtle);
        color: var(--rsg-ink);
        font-size: .8125rem;
    }

    .rsg-search input::placeholder {
        color: var(--rsg-muted);
    }

    .rsg-search input:focus {
        outline: none;
        border-color: var(--rsg-accent);
        background: var(--rsg-surface);
        box-shadow: 0 0 0 3px var(--rsg-accent-soft);
    }

    .rsg-search input:disabled {
        cursor: not-allowed;
        opacity: .65;
    }

    /* ===== Filter ===== */
    .rsg-filter {
        display: grid;
        grid-template-columns: minmax(180px, 1fr) minmax(280px, 1.4fr) auto;
        align-items: end;
        gap: 14px;
        padding: 16px 24px;
        border-block: 1px solid var(--rsg-rule);
        background: var(--rsg-subtle);
    }

    .rsg-field label,
    .rsg-field__label {
        display: block;
        margin-bottom: 6px;
        color: var(--rsg-muted);
        font-size: .75rem;
        font-weight: 600;
    }

    .rsg-input {
        width: 100%;
        min-height: 40px;
        padding: 8px 11px;
        border: 1px solid var(--rsg-rule-strong);
        border-radius: var(--rsg-radius-sm);
        background: var(--rsg-surface);
        color: var(--rsg-ink);
        font-size: .8125rem;
    }

    .rsg-input:focus {
        outline: none;
        border-color: var(--rsg-accent);
        box-shadow: 0 0 0 3px var(--rsg-accent-soft);
    }

    .rsg-range {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .rsg-range span {
        color: var(--rsg-muted);
        font-size: .75rem;
        white-space: nowrap;
    }

    .rsg-range .rsg-input {
        flex: 1;
        min-width: 0;
    }

    .rsg-locked {
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 40px;
        padding: 8px 11px;
        border: 1px dashed var(--rsg-rule-strong);
        border-radius: var(--rsg-radius-sm);
        background: var(--rsg-surface);
        color: var(--rsg-muted);
        font-size: .8125rem;
    }

    .rsg-locked iconify-icon {
        color: var(--rsg-accent-ink);
        flex: 0 0 auto;
    }

    .rsg-filter__actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* ===== Tombol ===== */
    .rsg-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 40px;
        padding: 8px 14px;
        border: 1px solid transparent;
        border-radius: var(--rsg-radius-sm);
        font-size: .8125rem;
        font-weight: 600;
        line-height: 1.2;
        text-decoration: none;
        cursor: pointer;
        transition: background-color .16s ease, border-color .16s ease, color .16s ease;
    }

    .rsg-btn:focus-visible {
        outline: 2px solid var(--rsg-accent);
        outline-offset: 2px;
    }

    .rsg-btn:disabled,
    .rsg-btn[disabled] {
        opacity: .55;
        cursor: not-allowed;
    }

    .rsg-btn--primary {
        background: var(--rsg-accent-solid);
        color: #fff;
    }

    .rsg-btn--primary:hover,
    .rsg-btn--primary:focus {
        background: var(--rsg-accent-hover);
        color: #fff;
    }

    .rsg-btn--ghost {
        border-color: var(--rsg-rule-strong);
        background: transparent;
        color: var(--rsg-ink);
    }

    .rsg-btn--ghost:hover,
    .rsg-btn--ghost:focus {
        background: var(--rsg-surface);
        color: var(--rsg-strong);
    }

    .rsg-btn--export {
        border-color: var(--rsg-rule-strong);
        background: var(--rsg-surface);
        color: var(--rsg-ink);
    }

    .rsg-btn--export:hover,
    .rsg-btn--export:focus {
        border-color: var(--rsg-accent);
        background: var(--rsg-accent-soft);
        color: var(--rsg-accent-ink);
    }

    /* ===== Tabel ===== */
    .rsg-table-scroll {
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--rsg-rule-strong) transparent;
    }

    .rsg-table-scroll::-webkit-scrollbar {
        height: 8px;
    }

    .rsg-table-scroll::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: var(--rsg-rule-strong);
    }

    .rsg-table {
        width: 100%;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .rsg-table th {
        padding: 10px 16px;
        border-bottom: 1px solid var(--rsg-rule);
        background: var(--rsg-subtle);
        color: var(--rsg-muted);
        font-size: .6875rem;
        font-weight: 650;
        letter-spacing: .06em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .rsg-table th:first-child {
        padding-left: 24px;
    }

    .rsg-table th:last-child {
        padding-right: 24px;
        text-align: right;
    }

    .rsg-table td {
        padding: 14px 16px;
        border-bottom: 1px solid var(--rsg-rule);
        color: var(--rsg-ink);
        vertical-align: middle;
    }

    .rsg-table td:first-child {
        padding-left: 24px;
    }

    .rsg-table td:last-child {
        padding-right: 24px;
        text-align: right;
    }

    .rsg-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .rsg-table tbody tr:hover td {
        background: var(--rsg-hover);
    }

    .rsg-code {
        display: block;
        color: var(--rsg-strong);
        font-size: .8125rem;
        font-weight: 650;
        letter-spacing: .01em;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .rsg-sub {
        display: block;
        margin-top: 3px;
        color: var(--rsg-muted);
        font-size: .75rem;
        line-height: 1.4;
    }

    .rsg-address {
        max-width: 30ch;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .rsg-person {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        min-width: 0;
    }

    .rsg-avatar {
        display: grid;
        flex: 0 0 34px;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 50%;
        background: var(--rsg-accent-soft);
        color: var(--rsg-accent-ink);
        font-size: .8125rem;
        font-weight: 700;
    }

    .rsg-person__body {
        min-width: 0;
    }

    .rsg-person__name {
        display: block;
        overflow: hidden;
        color: var(--rsg-strong);
        font-size: .8125rem;
        font-weight: 600;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .rsg-date {
        display: block;
        color: var(--rsg-strong);
        font-size: .8125rem;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .rsg-unit {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        max-width: 22ch;
        padding: 4px 8px;
        border: 1px solid var(--rsg-rule);
        border-radius: 999px;
        background: var(--rsg-subtle);
        color: var(--rsg-muted);
        font-size: .75rem;
        line-height: 1.2;
        white-space: nowrap;
    }

    .rsg-unit iconify-icon {
        flex: 0 0 auto;
        color: var(--rsg-accent-ink);
    }

    .rsg-unit span {
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .rsg-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: .75rem;
        font-weight: 600;
        line-height: 1.2;
        white-space: nowrap;
    }

    .rsg-status::before {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        content: "";
    }

    .rsg-status--wait {
        background: var(--rsg-info-soft);
        color: var(--rsg-info);
    }

    .rsg-status--progress {
        background: var(--rsg-warn-soft);
        color: var(--rsg-warn);
    }

    .rsg-status--ready {
        background: var(--rsg-accent-soft);
        color: var(--rsg-accent-ink);
    }

    .rsg-status--done {
        background: var(--rsg-success-soft);
        color: var(--rsg-success);
    }

    .rsg-status--cancel {
        background: var(--rsg-danger-soft);
        color: var(--rsg-danger);
    }

    .rsg-actions {
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
    }

    .rsg-btn--claim {
        min-height: 34px;
        padding: 6px 10px;
        background: var(--rsg-accent-soft);
        color: var(--rsg-accent-ink);
        font-size: .75rem;
    }

    .rsg-btn--claim:hover,
    .rsg-btn--claim:focus {
        background: var(--rsg-accent-solid);
        color: #fff;
    }

    .rsg-btn--claim:disabled,
    .rsg-btn--claim[disabled],
    .rsg-btn--claim:disabled:hover,
    .rsg-btn--claim[disabled]:hover,
    .rsg-btn--claim:disabled:focus,
    .rsg-btn--claim[disabled]:focus {
        background: var(--rsg-accent-soft);
        color: var(--rsg-muted);
    }

    .rsg-btn--print {
        min-height: 34px;
        padding: 6px 9px;
        border-color: var(--rsg-rule-strong);
        background: transparent;
        color: var(--rsg-muted);
        font-size: .75rem;
    }

    .rsg-btn--print:hover,
    .rsg-btn--print:focus {
        border-color: var(--rsg-accent);
        background: var(--rsg-accent-soft);
        color: var(--rsg-accent-ink);
    }

    /* ===== Footer DataTables ===== */
    .rsg-dt-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        padding: 14px 24px;
        border-top: 1px solid var(--rsg-rule);
        color: var(--rsg-muted);
        font-size: .75rem;
    }

    .rsg-dt-foot .dataTables_info {
        padding: 0;
        color: var(--rsg-muted);
    }

    .rsg-dt-foot .dataTables_paginate {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .rsg-dt-foot .dataTables_paginate > span {
        display: inline-flex;
        gap: 4px;
    }

    .rsg-dt-foot .paginate_button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 30px;
        padding: 5px 9px;
        border: 1px solid var(--rsg-rule);
        border-radius: 8px;
        background: var(--rsg-surface);
        color: var(--rsg-ink);
        font-size: .75rem;
        text-decoration: none;
        cursor: pointer;
    }

    .rsg-dt-foot .paginate_button:hover {
        border-color: var(--rsg-accent);
        background: var(--rsg-accent-soft);
        color: var(--rsg-accent-ink);
    }

    .rsg-dt-foot .paginate_button.current {
        border-color: var(--rsg-accent-solid);
        background: var(--rsg-accent-solid);
        color: #fff;
    }

    .rsg-dt-foot .paginate_button.disabled {
        color: var(--rsg-muted);
        cursor: not-allowed;
        opacity: .55;
    }

    /* ===== Empty state ===== */
    .rsg-empty-row td {
        padding: 0 !important;
        border-bottom: 0 !important;
        text-align: center !important;
    }

    .rsg-empty-row:hover td {
        background: transparent !important;
    }

    .rsg-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 52px 24px;
        text-align: center;
    }

    .rsg-empty__icon {
        display: grid;
        width: 48px;
        height: 48px;
        place-items: center;
        border-radius: 14px;
        background: var(--rsg-accent-soft);
        color: var(--rsg-accent-ink);
    }

    .rsg-empty h3 {
        margin: 4px 0 0;
        color: var(--rsg-strong);
        font-size: .9375rem;
        font-weight: 650;
    }

    .rsg-empty p {
        max-width: 42ch;
        margin: 0;
        color: var(--rsg-muted);
        font-size: .8125rem;
        line-height: 1.55;
    }

    /* ===== Modal ===== */
    .rsg-modal .modal-content {
        border: 1px solid var(--rsg-rule);
        border-radius: var(--rsg-radius);
        background: var(--rsg-surface);
        color: var(--rsg-ink);
    }

    .rsg-modal .modal-header {
        align-items: flex-start;
        gap: 12px;
        border-bottom: 1px solid var(--rsg-rule);
        background: var(--rsg-subtle);
    }

    .rsg-modal__title {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .rsg-modal__icon {
        display: grid;
        flex: 0 0 40px;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 12px;
        background: var(--rsg-accent-soft);
        color: var(--rsg-accent-ink);
    }

    .rsg-modal .modal-title {
        color: var(--rsg-strong);
        font-size: 1rem;
        font-weight: 650;
    }

    .rsg-modal__title p {
        margin: 2px 0 0;
        color: var(--rsg-muted);
        font-size: .75rem;
    }

    .rsg-modal .modal-body {
        padding: 20px 24px;
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--rsg-rule-strong) transparent;
    }

    .rsg-modal .modal-body h6 {
        margin: 0 0 8px;
        color: var(--rsg-strong);
        font-size: .75rem;
        font-weight: 650;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .rsg-modal .table {
        margin-bottom: 0;
        color: var(--rsg-ink);
    }

    .rsg-modal .table th {
        padding: 8px 10px;
        border-color: var(--rsg-rule);
        background: var(--rsg-subtle);
        color: var(--rsg-muted);
        font-size: .6875rem;
        font-weight: 650;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .rsg-modal .table td {
        padding: 8px 10px;
        border-color: var(--rsg-rule);
        color: var(--rsg-ink);
        font-size: .8125rem;
        vertical-align: top;
    }

    .rsg-modal .modal-footer {
        border-top: 1px solid var(--rsg-rule);
    }

    .rsg-modal__loading {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 32px 0;
        color: var(--rsg-muted);
        font-size: .8125rem;
    }

    .rsg-modal .alert {
        margin-bottom: 0;
    }

    /* ===== Responsif ===== */
    @media (max-width: 991.98px) {
        .rsg-head {
            flex-direction: column;
        }

        .rsg-head__meta {
            justify-content: flex-start;
        }

        .rsg-filter {
            grid-template-columns: 1fr 1fr;
        }

        .rsg-filter__actions {
            grid-column: 1 / -1;
            justify-content: flex-start;
        }
    }

    @media (max-width: 767.98px) {
        .rsg-panel__head {
            flex-direction: column;
        }

        .rsg-search {
            width: 100%;
            flex-basis: auto;
        }

        .rsg-table-scroll {
            overflow: visible;
        }

        .rsg-table,
        .rsg-table tbody,
        .rsg-table tr,
        .rsg-table td {
            display: block;
            width: 100%;
        }

        .rsg-table thead {
            display: none;
        }

        .rsg-table tr {
            padding: 14px 18px;
            border-bottom: 1px solid var(--rsg-rule);
        }

        .rsg-table tbody tr:last-child {
            border-bottom: 0;
        }

        .rsg-table td,
        .rsg-table td:first-child,
        .rsg-table td:last-child {
            display: grid;
            grid-template-columns: 42% minmax(0, 1fr);
            align-items: start;
            column-gap: 16px;
            row-gap: 2px;
            padding: 5px 0;
            border: 0;
            text-align: left;
        }

        .rsg-table td::before {
            grid-column: 1;
            grid-row: 1 / span 4;
            color: var(--rsg-muted);
            font-size: .6875rem;
            font-weight: 650;
            letter-spacing: .05em;
            text-transform: uppercase;
            content: attr(data-label);
        }

        .rsg-table td > * {
            grid-column: 2;
        }

        .rsg-table td[data-label="Aksi"] {
            display: block;
            padding-top: 12px;
        }

        .rsg-table td[data-label="Aksi"]::before {
            display: none;
        }

        .rsg-actions {
            width: 100%;
            justify-content: stretch;
        }

        .rsg-actions .rsg-btn {
            flex: 1;
        }

        .rsg-person {
            width: 100%;
        }

        .rsg-address {
            max-width: none;
            white-space: normal;
        }

        .rsg-empty-row td {
            display: block !important;
        }

        .rsg-empty-row td::before {
            display: none;
        }
    }

    @media (max-width: 575.98px) {
        .rsg-head {
            padding: 20px;
        }

        .rsg-filter {
            grid-template-columns: 1fr;
        }

        .rsg-range {
            flex-direction: column;
            align-items: stretch;
        }

        .rsg-range span {
            text-align: center;
        }

        .rsg-filter__actions {
            grid-column: auto;
        }

        .rsg-filter__actions .rsg-btn {
            flex: 1;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .rsg-page *,
        .rsg-modal * {
            transition: none !important;
        }
    }
</style>
