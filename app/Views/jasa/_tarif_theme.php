<?php

/**
 * Design system halaman Tarif Jasa (/tarif_jasa).
 *
 * Semua selector berada di bawah `.tarif-jasa` dan `.tj-modal` supaya tidak
 * bocor ke modul lain. Warna diturunkan dari token tema aplikasi; satu aksen
 * (biru primary) hanya untuk fokus dan aksi utama. Tanpa bayangan dan tanpa
 * gradien: pemisah memakai hairline, kedalaman datang dari ruang dan tipografi.
 *
 * Kontras teks kecil:
 *   --tj-muted #5b6672 di putih (5.85:1)
 *   --tj-ink   #495564 di putih (7.3:1)
 */
?>

<style>
    /* ===== Token ===== */
    .tarif-jasa,
    .tj-modal {
        --tj-surface: var(--bs-card-bg, #fff);
        --tj-subtle: #f7f9fb;
        --tj-rule: var(--bs-border-color, #e6ecf2);
        --tj-rule-strong: #d3dce5;
        --tj-hover: rgba(0, 119, 196, .045);
        --tj-ink: #495564;
        --tj-strong: #101828;
        --tj-muted: #5b6672;
        --tj-accent: #0077c4;
        --tj-accent-ink: #0067a8;
        --tj-accent-soft: rgba(0, 119, 196, .08);
        --tj-danger: #b02a37;
        --tj-danger-soft: rgba(176, 42, 55, .08);
        --tj-ok: #0f7a46;
        --tj-ok-soft: rgba(15, 122, 70, .08);
        --tj-radius: 16px;
        --tj-radius-sm: 10px;
    }

    html[data-bs-theme="dark"] .tarif-jasa,
    html[data-bs-theme="dark"] .tj-modal {
        --tj-surface: var(--bs-card-bg, #2a3547);
        --tj-subtle: rgba(255, 255, 255, .035);
        --tj-rule: var(--bs-border-color, rgba(255, 255, 255, .12));
        --tj-rule-strong: rgba(255, 255, 255, .2);
        --tj-hover: rgba(255, 255, 255, .04);
        --tj-ink: var(--bs-body-color, #cfd8e3);
        --tj-strong: #f1f5f9;
        --tj-muted: #8fa0b8;
        --tj-accent: #8ab4ff;
        --tj-accent-ink: #8ab4ff;
        --tj-accent-soft: rgba(138, 180, 255, .14);
        --tj-danger: #ff9aa2;
        --tj-danger-soft: rgba(255, 154, 162, .1);
        --tj-ok: #6fdd9d;
        --tj-ok-soft: rgba(111, 221, 157, .1);
    }

    .tarif-jasa {
        width: 100%;
        color: var(--tj-ink);
        font-size: .875rem;
    }

    .tarif-jasa ::selection {
        background: var(--tj-accent-soft);
        color: var(--tj-strong);
    }

    .tarif-jasa :focus-visible,
    .tj-modal :focus-visible {
        outline: 2px solid var(--tj-accent);
        outline-offset: 2px;
    }

    .tarif-jasa .tj-num {
        font-variant-numeric: tabular-nums;
    }

    /* ===== Kepala halaman ===== */
    .tj-head {
        padding: 22px 24px 0;
        border: 1px solid var(--tj-rule);
        border-radius: var(--tj-radius);
        background: var(--tj-surface);
        animation: tj-settle .24s cubic-bezier(.2, .7, .3, 1) both;
    }

    @keyframes tj-settle {
        from { opacity: 0; transform: translateY(4px); }
        to   { opacity: 1; transform: none; }
    }

    .tj-crumb {
        margin: 0 0 10px;
        padding: 0;
        list-style: none;
        display: flex;
        gap: 6px;
        font-size: .75rem;
        color: var(--tj-muted);
    }

    .tj-crumb a {
        color: inherit;
        text-decoration: none;
    }

    .tj-crumb a:hover {
        color: var(--tj-strong);
    }

    .tj-crumb li + li::before {
        content: "/";
        margin-right: 6px;
        color: var(--tj-rule-strong);
    }

    .tj-head__row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }

    .tj-head h1 {
        margin: 0;
        font-size: 1.6rem;
        font-weight: 600;
        letter-spacing: -.03em;
        color: var(--tj-strong);
        text-wrap: balance;
    }

    .tj-sub {
        max-width: 68ch;
        margin: 6px 0 0;
        font-size: .8125rem;
        line-height: 1.6;
        color: var(--tj-muted);
    }

    /* ===== Strip angka ===== */
    .tj-figures {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin: 20px 0 0;
        border-top: 1px solid var(--tj-rule);
    }

    .tj-fig {
        padding: 14px 18px 16px 0;
    }

    .tj-fig + .tj-fig {
        padding-left: 18px;
        border-left: 1px solid var(--tj-rule);
    }

    .tj-fig dt {
        margin: 0;
        font-size: .6875rem;
        font-weight: 600;
        letter-spacing: .07em;
        text-transform: uppercase;
        color: var(--tj-muted);
    }

    .tj-fig dd {
        margin: 6px 0 0;
        font-size: 1.0625rem;
        font-weight: 600;
        letter-spacing: -.01em;
        color: var(--tj-strong);
        font-variant-numeric: tabular-nums;
    }

    /* ===== Tombol ===== */
    .tj-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 40px;
        padding: 0 14px;
        border: 1px solid transparent;
        border-radius: var(--tj-radius-sm);
        font-size: .8125rem;
        font-weight: 600;
        line-height: 1;
        text-decoration: none;
        cursor: pointer;
        transition: background-color .15s ease, color .15s ease, border-color .15s ease;
    }

    .tj-btn--primary {
        background: var(--tj-accent);
        color: #fff;
    }

    .tj-btn--primary:hover,
    .tj-btn--primary:focus-visible {
        background: var(--tj-accent-ink);
        color: #fff;
    }

    html[data-bs-theme="dark"] .tj-btn--primary {
        color: #0b1220;
    }

    html[data-bs-theme="dark"] .tj-btn--primary:hover,
    html[data-bs-theme="dark"] .tj-btn--primary:focus-visible {
        background: #a9c8ff;
        color: #0b1220;
    }

    .tj-btn--ghost {
        background: transparent;
        border-color: var(--tj-rule);
        color: var(--tj-ink);
    }

    .tj-btn--ghost:hover,
    .tj-btn--ghost:focus-visible {
        border-color: var(--tj-rule-strong);
        background: var(--tj-subtle);
        color: var(--tj-strong);
    }

    .tj-btn svg {
        width: 16px;
        height: 16px;
        flex: none;
    }

    /* ===== Flash ===== */
    .tj-flash {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 14px 0 0;
        padding: 10px 14px;
        border: 1px solid var(--tj-rule);
        border-radius: var(--tj-radius-sm);
        font-size: .8125rem;
    }

    .tj-flash--ok {
        background: var(--tj-ok-soft);
        border-color: transparent;
        color: var(--tj-ok);
    }

    .tj-flash--fail {
        background: var(--tj-danger-soft);
        border-color: transparent;
        color: var(--tj-danger);
    }

    /* ===== Lembar tarif ===== */
    .tj-sheet {
        margin-top: 16px;
        border: 1px solid var(--tj-rule);
        border-radius: var(--tj-radius);
        background: var(--tj-surface);
        overflow: hidden;
    }

    .tj-toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 20px;
        border-bottom: 1px solid var(--tj-rule);
    }

    .tj-search {
        position: relative;
        flex: 1 1 auto;
        max-width: 320px;
    }

    .tj-search svg {
        position: absolute;
        top: 50%;
        left: 12px;
        width: 15px;
        height: 15px;
        transform: translateY(-50%);
        color: var(--tj-muted);
        pointer-events: none;
    }

    .tj-search input {
        width: 100%;
        height: 38px;
        padding: 0 12px 0 34px;
        border: 1px solid var(--tj-rule);
        border-radius: var(--tj-radius-sm);
        background: transparent;
        color: var(--tj-strong);
        font-size: .8125rem;
        transition: border-color .15s ease, background-color .15s ease;
    }

    .tj-search input::placeholder {
        color: var(--tj-muted);
        opacity: 1;
    }

    .tj-search input:hover {
        border-color: var(--tj-rule-strong);
    }

    .tj-sort {
        height: 38px;
        padding: 0 30px 0 12px;
        border: 1px solid var(--tj-rule);
        border-radius: var(--tj-radius-sm);
        background: transparent;
        color: var(--tj-ink);
        font-size: .8125rem;
        cursor: pointer;
    }

    .tj-sort:hover {
        border-color: var(--tj-rule-strong);
    }

    .tj-count {
        margin-left: auto;
        font-size: .75rem;
        color: var(--tj-muted);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .tj-table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
        color: var(--tj-ink);
    }

    .tj-table thead th {
        padding: 10px 20px;
        border-bottom: 1px solid var(--tj-rule);
        background: transparent;
        font-size: .6875rem;
        font-weight: 600;
        letter-spacing: .07em;
        text-transform: uppercase;
        color: var(--tj-muted);
        white-space: nowrap;
    }

    .tj-table tbody td {
        padding: 0 20px;
        height: 54px;
        border-bottom: 1px solid var(--tj-rule);
        vertical-align: middle;
    }

    .tj-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .tj-table tbody tr {
        transition: background-color .15s ease;
    }

    .tj-table tbody tr:hover {
        background: var(--tj-hover);
    }

    .tj-table .tj-col-price,
    .tj-table th.tj-col-price {
        text-align: right;
    }

    .tj-table .tj-col-action,
    .tj-table th.tj-col-action {
        text-align: right;
        width: 1%;
    }

    .tj-code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: .75rem;
        letter-spacing: .01em;
        color: var(--tj-muted);
        white-space: nowrap;
    }

    .tj-name {
        font-weight: 550;
        color: var(--tj-strong);
    }

    .tj-price {
        font-size: .875rem;
        font-weight: 600;
        color: var(--tj-strong);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .tj-fee {
        font-size: .8125rem;
        font-weight: 500;
        color: var(--tj-muted);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .tj-edit {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 32px;
        padding: 0 10px;
        border: 1px solid transparent;
        border-radius: 8px;
        background: transparent;
        color: var(--tj-accent-ink);
        font-size: .75rem;
        font-weight: 600;
        transition: background-color .15s ease, border-color .15s ease;
    }

    .tj-edit:hover,
    .tj-edit:focus-visible {
        background: var(--tj-accent-soft);
        border-color: transparent;
    }

    .tj-edit svg {
        width: 14px;
        height: 14px;
    }

    /* ===== Footer DataTables ===== */
    .tj-dt-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 20px;
        border-top: 1px solid var(--tj-rule);
    }

    .tj-dt-foot .dataTables_info {
        padding: 0;
        font-size: .75rem;
        color: var(--tj-muted);
        font-variant-numeric: tabular-nums;
    }

    .tj-dt-foot .dataTables_paginate {
        padding: 0;
        display: flex;
        gap: 4px;
    }

    .tj-dt-foot .paginate_button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        height: 30px;
        padding: 0 8px;
        border: 1px solid var(--tj-rule);
        border-radius: 8px;
        color: var(--tj-ink) !important;
        font-size: .75rem;
        text-decoration: none;
        cursor: pointer;
    }

    .tj-dt-foot .paginate_button:hover {
        border-color: var(--tj-rule-strong);
        background: var(--tj-subtle);
        color: var(--tj-strong) !important;
    }

    .tj-dt-foot .paginate_button.current,
    .tj-dt-foot .paginate_button.current:hover {
        border-color: transparent;
        background: var(--tj-accent-soft);
        color: var(--tj-accent-ink) !important;
        font-weight: 600;
    }

    .tj-dt-foot .paginate_button.disabled,
    .tj-dt-foot .paginate_button.disabled:hover {
        opacity: .45;
        background: transparent;
        border-color: var(--tj-rule);
        cursor: default;
    }

    /* ===== Kosong ===== */
    .tj-empty {
        padding: 56px 20px 60px;
        text-align: center;
    }

    .tj-empty svg {
        width: 28px;
        height: 28px;
        color: var(--tj-rule-strong);
    }

    .tj-empty p {
        margin: 12px 0 0;
        font-size: .8125rem;
        color: var(--tj-muted);
    }

    /* ===== Modal ===== */
    .tj-modal .modal-content {
        border: 1px solid var(--tj-rule);
        border-radius: var(--tj-radius);
        background: var(--tj-surface);
        color: var(--tj-ink);
        box-shadow: none;
    }

    .tj-modal .modal-header {
        padding: 20px 22px 0;
        border: 0;
    }

    .tj-modal .modal-title {
        font-size: 1.0625rem;
        font-weight: 600;
        letter-spacing: -.02em;
        color: var(--tj-strong);
    }

    .tj-modal .modal-body {
        padding: 18px 22px 4px;
    }

    .tj-modal .modal-footer {
        padding: 12px 22px 20px;
        border: 0;
        gap: 8px;
    }

    .tj-field {
        margin-bottom: 16px;
    }

    .tj-field label {
        display: block;
        margin-bottom: 6px;
        font-size: .75rem;
        font-weight: 600;
        color: var(--tj-ink);
    }

    .tj-field input {
        width: 100%;
        height: 42px;
        padding: 0 12px;
        border: 1px solid var(--tj-rule);
        border-radius: var(--tj-radius-sm);
        background: transparent;
        color: var(--tj-strong);
        font-size: .875rem;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .tj-field input:hover {
        border-color: var(--tj-rule-strong);
    }

    .tj-field input:focus {
        border-color: var(--tj-accent);
        box-shadow: 0 0 0 3px var(--tj-accent-soft);
        outline: none;
    }

    .tj-pair {
        display: flex;
        gap: 8px;
    }

    .tj-pair input:first-child {
        flex: 2 1 0;
        min-width: 0;
    }

    .tj-pair input:last-child {
        flex: 1 1 0;
        min-width: 0;
    }

    .tj-hint {
        margin: 6px 0 0;
        font-size: .75rem;
        line-height: 1.5;
        color: var(--tj-muted);
    }

    .tj-preview {
        margin: 6px 0 0;
        font-size: .8125rem;
        font-weight: 600;
        color: var(--tj-accent-ink);
        font-variant-numeric: tabular-nums;
    }

    .tj-preview:empty {
        display: none;
    }

    /* ===== Responsif ===== */
    @media (max-width: 991.98px) {
        .tj-toolbar {
            flex-wrap: wrap;
        }

        .tj-search {
            max-width: none;
            flex: 1 1 100%;
        }

        .tj-count {
            margin-left: 0;
        }
    }

    @media (max-width: 767.98px) {
        .tj-head {
            padding: 18px 18px 0;
        }

        .tj-head__row {
            flex-direction: column;
            gap: 12px;
        }

        .tj-head h1 {
            font-size: 1.375rem;
        }

        .tj-head__row .tj-btn {
            width: 100%;
            justify-content: center;
        }

        .tj-figures {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .tj-fig {
            padding: 12px 12px 14px 0;
        }

        .tj-fig:nth-child(3) {
            padding-left: 0;
            border-left: 0;
            border-top: 1px solid var(--tj-rule);
        }

        .tj-fig:nth-child(4) {
            border-top: 1px solid var(--tj-rule);
        }

        .tj-toolbar {
            padding: 12px 14px;
        }

        .tj-table thead {
            position: absolute;
            width: 1px;
            height: 1px;
            margin: -1px;
            padding: 0;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .tj-table tbody tr {
            display: block;
            padding: 12px 14px;
            border-bottom: 1px solid var(--tj-rule);
        }

        .tj-table tbody tr:last-child {
            border-bottom: 0;
        }

        .tj-table tbody td {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 16px;
            height: auto;
            padding: 5px 0;
            border: 0;
        }

        .tj-table tbody td::before {
            content: attr(data-label);
            font-size: .6875rem;
            font-weight: 600;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: var(--tj-muted);
        }

        .tj-table .tj-col-action {
            width: auto;
            margin-top: 6px;
            padding-top: 8px;
            border-top: 1px solid var(--tj-rule);
        }

        .tj-table .tj-col-action::before {
            content: none;
        }

        .tj-edit {
            width: 100%;
            justify-content: center;
        }

        .tj-dt-foot {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
            padding: 12px 14px;
        }

        .tj-dt-foot .dataTables_info {
            text-align: center;
        }

        .tj-dt-foot .dataTables_paginate {
            justify-content: center;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .tj-head {
            animation: none;
        }

        .tarif-jasa *,
        .tj-modal * {
            transition-duration: .01ms !important;
        }
    }
</style>
