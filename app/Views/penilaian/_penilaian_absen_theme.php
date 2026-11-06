<?php

/**
 * Tema halaman Penilaian Harian (/penilaian/absen).
 *
 * Semua selector berada di bawah `.pa-page` / `.pa-modal` agar tidak bocor ke
 * modul lain. Warna diturunkan dari token tema aplikasi; satu aksen (primary)
 * hanya untuk aksi utama, fokus, dan angka skor.
 *
 * Kontras teks kecil dijaga >= 4.5:1 di terang maupun gelap:
 *   --pa-muted  #5b6672 di putih (5.85:1), #8fa0b8 di gelap
 *   --pa-ink    #1d2a38 di putih, var(--bs-body-color) di gelap
 */
?>

<style>
    /* ===== Token ===== */
    .pa-page,
    .pa-modal {
        --pa-surface: var(--bs-card-bg, #fff);
        --pa-subtle: var(--bs-secondary-bg, #f5f8fb);
        --pa-subtle-2: var(--bs-tertiary-bg, #e7ecf0);
        --pa-rule: var(--bs-border-color, #e6ecf1);
        --pa-rule-strong: #cfd8e3;
        --pa-ink: #1d2a38;
        --pa-strong: #101b27;
        --pa-muted: #5b6672;
        --pa-accent: #0074ba;
        --pa-accent-solid: #0074ba;
        --pa-accent-hover: #005f99;
        --pa-accent-ink: #0069a8;
        --pa-accent-soft: rgba(0, 133, 219, .09);
        --pa-success: #0f7a46;
        --pa-success-soft: #e7f6ee;
        --pa-warn: #8a5300;
        --pa-warn-soft: #fff3d6;
        --pa-warn-action: #8a5300;
        --pa-warn-action-ink: #fff;
        --pa-danger: #b02a37;
        --pa-danger-soft: #fdecee;
        --pa-radius: 14px;
        --pa-radius-sm: 9px;
    }

    html[data-bs-theme="dark"] .pa-page,
    html[data-bs-theme="dark"] .pa-modal {
        --pa-surface: var(--bs-card-bg, #17222f);
        --pa-subtle: rgba(255, 255, 255, .04);
        --pa-subtle-2: rgba(255, 255, 255, .08);
        --pa-rule: var(--bs-border-color, rgba(255, 255, 255, .12));
        --pa-rule-strong: rgba(255, 255, 255, .22);
        --pa-ink: var(--bs-body-color, #cfd8e3);
        --pa-strong: #f1f5f9;
        --pa-muted: #8fa0b8;
        --pa-accent: #3b9fe0;
        --pa-accent-solid: #0074ba;
        --pa-accent-hover: #005f99;
        --pa-accent-ink: #7cc4f0;
        --pa-accent-soft: rgba(124, 196, 240, .14);
        --pa-success: #6fdd9d;
        --pa-success-soft: rgba(111, 221, 157, .13);
        --pa-warn: #ffcf6e;
        --pa-warn-soft: rgba(255, 207, 110, .13);
        --pa-warn-action: #ffcf6e;
        --pa-warn-action-ink: #231703;
        --pa-danger: #ff9aa2;
        --pa-danger-soft: rgba(255, 154, 162, .13);
    }

    .pa-page,
    .pa-modal {
        color: var(--pa-ink);
    }

    .pa-page {
        max-width: none;
        margin: 0;
        font-size: .875rem;
        line-height: 1.5;
    }

    .pa-page .text-muted,
    .pa-modal .text-muted {
        color: var(--pa-muted) !important;
    }

    .pa-page ::selection,
    .pa-modal ::selection {
        background: var(--pa-accent-soft);
        color: var(--pa-strong);
    }

    .pa-page input,
    .pa-page select,
    .pa-modal input,
    .pa-modal select {
        caret-color: var(--pa-accent);
    }

    .pa-page a:focus-visible,
    .pa-page button:focus-visible,
    .pa-page select:focus-visible,
    .pa-page input:focus-visible,
    .pa-modal a:focus-visible,
    .pa-modal button:focus-visible {
        outline: 2px solid var(--pa-accent);
        outline-offset: 2px;
    }

    .pa-page .form-control,
    .pa-page .form-select {
        color: var(--pa-ink);
        background-color: var(--pa-surface);
        border-color: var(--pa-rule-strong);
    }

    .pa-page .form-control:focus,
    .pa-page .form-select:focus {
        color: var(--pa-ink);
        background-color: var(--pa-surface);
        border-color: var(--pa-accent);
        box-shadow: 0 0 0 3px var(--pa-accent-soft);
    }

    .pa-page .form-label,
    .pa-page .pa-label {
        display: block;
        margin: 0 0 5px;
        color: var(--pa-ink);
        font-size: .75rem;
        font-weight: 650;
    }

    /* ===== Kepala halaman ===== */
    .pa-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 24px;
        padding: 0 0 18px;
        border-bottom: 1px solid var(--pa-rule);
    }

    .pa-crumb {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 7px;
        margin: 0 0 10px;
        padding: 0;
        list-style: none;
        font-size: .75rem;
        color: var(--pa-muted);
    }

    .pa-crumb li {
        display: flex;
        align-items: center;
        gap: 7px;
    }

    .pa-crumb li + li::before {
        content: "/";
        color: var(--pa-rule-strong);
    }

    .pa-crumb a {
        color: var(--pa-muted);
        text-decoration: none;
    }

    .pa-crumb a:hover {
        color: var(--pa-accent-ink);
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .pa-title {
        margin: 0;
        color: var(--pa-strong);
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -.025em;
        line-height: 1.2;
    }

    .pa-sub {
        max-width: 74ch;
        margin: 6px 0 0;
        color: var(--pa-muted);
        font-size: .85rem;
        line-height: 1.55;
    }

    .pa-formula {
        display: inline-block;
        margin-top: 4px;
        padding: 2px 8px;
        border: 1px solid var(--pa-rule);
        border-radius: 6px;
        background: var(--pa-subtle);
        color: var(--pa-ink);
        font-size: .78rem;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .pa-period {
        flex: 0 0 auto;
        margin: 0;
        text-align: right;
    }

    .pa-period strong {
        display: block;
        color: var(--pa-strong);
        font-size: .9rem;
        font-weight: 650;
    }

    .pa-period span {
        display: block;
        margin-top: 2px;
        color: var(--pa-muted);
        font-size: .72rem;
    }

    /* ===== Flash ===== */
    .pa-flash {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-top: 16px;
        padding: 10px 14px;
        border: 1px solid var(--pa-rule);
        border-radius: var(--pa-radius-sm);
        background: var(--pa-surface);
        color: var(--pa-ink);
        font-size: .82rem;
    }

    .pa-flash iconify-icon {
        flex: 0 0 auto;
        margin-top: 1px;
    }

    .pa-flash > div {
        flex: 1 1 auto;
    }

    .pa-flash--ok {
        border-color: transparent;
        background: var(--pa-success-soft);
        color: var(--pa-success);
    }

    .pa-flash--err {
        border-color: transparent;
        background: var(--pa-danger-soft);
        color: var(--pa-danger);
    }

    .pa-flash .btn-close {
        flex: 0 0 auto;
        padding: 2px;
        font-size: .7rem;
    }

    /* ===== Toolbar filter ===== */
    .pa-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 12px;
        padding: 16px 0 18px;
        border-bottom: 1px solid var(--pa-rule);
    }

    .pa-toolbar .pa-field {
        flex: 0 0 auto;
        min-width: 118px;
    }

    .pa-toolbar .pa-field--grow {
        flex: 1 1 300px;
        min-width: 240px;
    }

    .pa-toolbar .form-select-sm {
        min-height: 32px;
    }

    .pa-reset {
        margin-left: auto;
    }

    /* styles.css global memaksa tinggi Select2 40px; reset agar setinggi form-select-sm. */
    .pa-page #filterKaryawan + .select2-container {
        width: 100% !important;
    }

    .pa-page #filterKaryawan + .select2-container .select2-selection--single {
        position: relative;
        display: flex;
        align-items: center;
        height: auto;
        min-height: 32px;
        padding: .25rem .75rem;
        font-size: .875rem;
        line-height: normal;
        color: var(--pa-ink);
        background-color: var(--pa-surface);
        border: 1px solid var(--pa-rule-strong);
        border-radius: var(--bs-border-radius-sm, .2rem);
    }

    .pa-page #filterKaryawan + .select2-container .select2-selection--single .select2-selection__rendered {
        padding: 0 1.5rem 0 0;
        height: auto;
        line-height: inherit;
        color: inherit;
    }

    .pa-page #filterKaryawan + .select2-container .select2-selection--single .select2-selection__arrow {
        top: 50%;
        right: .75rem;
        left: auto;
        width: 10px;
        height: 10px;
        transform: translateY(-50%);
    }

    .pa-page #filterKaryawan + .select2-container .select2-selection--single .select2-selection__arrow b {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 7px;
        height: 7px;
        margin: 0;
        border: 0;
        border-right: 1.5px solid var(--pa-muted);
        border-bottom: 1.5px solid var(--pa-muted);
        transform: translate(-50%, -50%) rotate(45deg);
    }

    .pa-page #filterKaryawan + .select2-container.select2-container--open .select2-selection__arrow b {
        transform: translate(-50%, -50%) rotate(-135deg);
    }

    .pa-page #filterKaryawan + .select2-container.select2-container--focus .select2-selection--single,
    .pa-page #filterKaryawan + .select2-container.select2-container--open .select2-selection--single {
        border-color: var(--pa-accent);
        box-shadow: 0 0 0 3px var(--pa-accent-soft);
    }

    /* ===== Tombol ===== */
    .pa-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 32px;
        padding: .35rem .9rem;
        border: 1px solid transparent;
        border-radius: 8px;
        font-size: .8rem;
        font-weight: 650;
        line-height: 1.3;
        text-decoration: none;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }

    .pa-btn iconify-icon {
        font-size: 1rem;
    }

    .pa-btn--primary {
        color: #fff;
        background: var(--pa-accent-solid);
        border-color: var(--pa-accent-solid);
    }

    .pa-btn--primary:hover {
        color: #fff;
        background: var(--pa-accent-hover);
        border-color: var(--pa-accent-hover);
    }

    .pa-btn--ghost {
        color: var(--pa-ink);
        background: transparent;
        border-color: var(--pa-rule-strong);
    }

    .pa-btn--ghost:hover {
        color: var(--pa-strong);
        background: var(--pa-subtle);
    }

    /* ===== Ringkasan bulanan ===== */
    .pa-board {
        margin-top: 18px;
        border: 1px solid var(--pa-rule);
        border-radius: var(--pa-radius);
        background: var(--pa-surface);
    }

    .pa-board__top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        padding: 18px 22px;
    }

    .pa-name {
        margin: 0;
        color: var(--pa-strong);
        font-size: 1.2rem;
        font-weight: 700;
        letter-spacing: -.015em;
        line-height: 1.25;
    }

    .pa-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin: 7px 0 0;
        color: var(--pa-muted);
        font-size: .78rem;
    }

    .pa-chip {
        display: inline-flex;
        align-items: center;
        padding: 2px 9px;
        border: 1px solid var(--pa-rule-strong);
        border-radius: 999px;
        background: var(--pa-subtle);
        color: var(--pa-ink);
        font-size: .72rem;
        font-weight: 600;
    }

    .pa-score {
        flex: 0 0 auto;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        text-align: right;
    }

    .pa-score__label {
        color: var(--pa-muted);
        font-size: .72rem;
        font-weight: 600;
    }

    .pa-score__value {
        margin-top: 1px;
        color: var(--pa-accent-ink);
        font-size: 2.15rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        letter-spacing: -.03em;
        line-height: 1.05;
    }

    .pa-score__scale {
        color: var(--pa-muted);
        font-size: .72rem;
    }

    .pa-comps {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        border-top: 1px solid var(--pa-rule);
    }

    .pa-comp {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 10px;
        padding: 11px 22px;
    }

    .pa-comp + .pa-comp {
        border-left: 1px solid var(--pa-rule);
    }

    .pa-comp__label {
        min-width: 0;
        overflow: hidden;
        color: var(--pa-muted);
        font-size: .74rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pa-comp__value {
        color: var(--pa-strong);
        font-size: .9rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    /* ===== Lembar input ===== */
    .pa-sheet {
        margin-top: 18px;
        border: 1px solid var(--pa-rule);
        border-radius: var(--pa-radius);
        background: var(--pa-surface);
    }

    .pa-sheet__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 14px 22px;
        border-bottom: 1px solid var(--pa-rule);
    }

    .pa-sheet__title {
        margin: 0;
        color: var(--pa-strong);
        font-size: 1rem;
        font-weight: 650;
        letter-spacing: -.01em;
    }

    .pa-sheet__sub {
        margin: 3px 0 0;
        color: var(--pa-muted);
        font-size: .79rem;
    }

    .pa-sheet__body {
        display: grid;
        grid-template-columns: minmax(200px, 250px) minmax(0, 1fr);
        gap: 22px;
        padding: 20px 22px;
    }

    .pa-help {
        margin: 6px 0 0;
        color: var(--pa-muted);
        font-size: .74rem;
        line-height: 1.5;
    }

    .pa-note {
        margin: 12px 0 0;
        padding: 9px 12px;
        border: 1px solid var(--pa-rule);
        border-radius: var(--pa-radius-sm);
        background: var(--pa-subtle);
        color: var(--pa-ink);
        font-size: .76rem;
        line-height: 1.5;
    }

    .pa-note a {
        color: var(--pa-accent-ink);
        font-weight: 600;
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    /* ===== Blok kehadiran ===== */
    .pa-punch {
        padding: 14px 16px;
        border: 1px solid var(--pa-rule);
        border-radius: var(--pa-radius-sm);
        background: var(--pa-subtle);
    }

    .pa-punch__head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 8px;
        margin-bottom: 12px;
    }

    .pa-punch__title {
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 0;
        color: var(--pa-strong);
        font-size: .88rem;
        font-weight: 650;
    }

    .pa-punch__badge {
        padding: 2px 8px;
        border-radius: 999px;
        background: var(--pa-accent-soft);
        color: var(--pa-accent-ink);
        font-size: .66rem;
        font-weight: 650;
        letter-spacing: .02em;
        text-transform: uppercase;
    }

    .pa-punch__grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        align-items: end;
        gap: 10px 12px;
    }

    .pa-punch__grid #atsPreviewBlockCol,
    .pa-punch__grid #atsOffNote {
        grid-column: 1 / -1;
    }

    .pa-punch .form-label {
        margin-bottom: 4px;
        color: var(--pa-muted);
        font-size: .72rem;
        font-weight: 600;
    }

    .pa-punch .form-text {
        margin-top: 9px;
        color: var(--pa-muted);
        font-size: .72rem;
        line-height: 1.5;
    }

    /* Preview yang diinjeksi JS memakai .alert/.badge Bootstrap. */
    .pa-punch .alert {
        margin: 0;
        border: 1px solid transparent;
        border-radius: var(--pa-radius-sm);
        background: var(--pa-surface);
        color: var(--pa-ink);
        font-size: .78rem;
    }

    .pa-punch .alert-info {
        background: var(--pa-accent-soft);
        color: var(--pa-ink);
    }

    .pa-punch .badge.bg-success {
        background: var(--pa-success-soft) !important;
        color: var(--pa-success) !important;
    }

    .pa-punch .badge.bg-warning {
        background: var(--pa-warn-soft) !important;
        color: var(--pa-warn) !important;
    }

    .pa-punch .badge.bg-danger {
        background: var(--pa-danger-soft) !important;
        color: var(--pa-danger) !important;
    }

    .pa-punch .badge.bg-secondary {
        background: var(--pa-subtle-2) !important;
        color: var(--pa-muted) !important;
    }

    .pa-off {
        padding: 8px 10px;
        border: 1px dashed var(--pa-rule-strong);
        border-radius: var(--pa-radius-sm);
        background: var(--pa-surface);
        font-size: .76rem;
        color: var(--pa-muted);
    }

    .pa-assess {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: 12px;
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px solid var(--pa-rule);
    }

    .pa-sheet__foot {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: 11px 22px;
        border-top: 1px solid var(--pa-rule);
        border-radius: 0 0 var(--pa-radius) var(--pa-radius);
        background: var(--pa-subtle);
        color: var(--pa-muted);
        font-size: .74rem;
        line-height: 1.5;
    }

    .pa-sheet__foot iconify-icon {
        flex: 0 0 auto;
        margin-top: 1px;
        color: var(--pa-accent-ink);
    }

    .pa-sheet__foot p {
        margin: 0;
    }

    /* ===== Ledger riwayat ===== */
    .pa-ledger {
        margin-top: 18px;
        border: 1px solid var(--pa-rule);
        border-radius: var(--pa-radius);
        background: var(--pa-surface);
        overflow: hidden;
    }

    .pa-ledger__head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px 24px;
        padding: 14px 22px;
        border-bottom: 1px solid var(--pa-rule);
    }

    .pa-ledger__title {
        margin: 0;
        color: var(--pa-strong);
        font-size: 1rem;
        font-weight: 650;
        letter-spacing: -.01em;
    }

    .pa-facts {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 18px;
    }

    .pa-fact {
        display: inline-flex;
        align-items: baseline;
        gap: 5px;
        color: var(--pa-muted);
        font-size: .74rem;
    }

    .pa-fact strong {
        color: var(--pa-strong);
        font-size: .88rem;
        font-variant-numeric: tabular-nums;
    }

    .pa-table-wrap {
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--pa-rule-strong) transparent;
    }

    .pa-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: .74rem;
    }

    .pa-table th,
    .pa-table td {
        padding: 6px 3px;
        border-bottom: 1px solid var(--pa-rule);
        text-align: center;
        vertical-align: middle;
    }

    .pa-table thead th {
        background: var(--pa-subtle);
        color: var(--pa-muted);
        font-size: .68rem;
        font-weight: 650;
    }

    .pa-table tbody tr:last-child th,
    .pa-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .pa-table tbody tr:hover td {
        background: var(--pa-subtle);
    }

    .pa-table .pa-compname {
        position: sticky;
        left: 0;
        z-index: 1;
        width: 190px;
        min-width: 190px;
        padding-left: 18px;
        background: var(--pa-surface);
        color: var(--pa-strong);
        font-weight: 600;
        text-align: left;
    }

    .pa-table thead .pa-compname {
        z-index: 2;
        background: var(--pa-subtle);
        color: var(--pa-muted);
    }

    .pa-table tbody tr:hover .pa-compname {
        background: var(--pa-subtle);
    }

    .pa-table .pa-dayhead,
    .pa-table .pa-daycell {
        width: 33px;
        min-width: 33px;
    }

    .pa-cell {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 22px;
        padding: 0 4px;
        border-radius: 5px;
        font-size: .7rem;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
        line-height: 1;
    }

    .pa-cell--off {
        background: var(--pa-subtle-2);
        color: var(--pa-muted);
        font-size: .62rem;
        letter-spacing: .01em;
    }

    .pa-cell--low {
        background: var(--pa-danger-soft);
        color: var(--pa-danger);
    }

    .pa-cell--mid {
        background: var(--pa-warn-soft);
        color: var(--pa-warn);
    }

    .pa-cell--good {
        background: var(--pa-success-soft);
        color: var(--pa-success);
    }

    .pa-cell--empty {
        color: var(--pa-rule-strong);
        font-weight: 500;
    }

    .pa-legend {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px 16px;
        padding: 11px 22px;
        border-top: 1px solid var(--pa-rule);
        color: var(--pa-muted);
        font-size: .73rem;
    }

    .pa-legend__item {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .pa-legend .pa-cell {
        height: 20px;
    }

    /* Kartu per komponen di mobile: matriks tanggal tidak perlu digeser. */
    .pa-mdays {
        display: none;
    }

    /* ===== Keadaan kosong / akses terbatas ===== */
    .pa-empty {
        margin-top: 18px;
        padding: 40px 24px;
        border: 1px solid var(--pa-rule);
        border-radius: var(--pa-radius);
        background: var(--pa-surface);
        text-align: center;
    }

    .pa-empty iconify-icon {
        display: block;
        margin: 0 auto 8px;
        color: var(--pa-muted);
        font-size: 2rem;
    }

    .pa-empty--warn iconify-icon {
        color: var(--pa-warn);
    }

    .pa-empty h2 {
        margin: 0 0 4px;
        color: var(--pa-strong);
        font-size: 1rem;
        font-weight: 650;
    }

    .pa-empty p {
        margin: 0;
        color: var(--pa-muted);
        font-size: .82rem;
    }

    /* ===== Modal ===== */
    .pa-modal .modal-content {
        border: 1px solid var(--pa-rule);
        border-radius: var(--pa-radius);
        background: var(--pa-surface);
        box-shadow: 0 18px 48px -24px rgba(16, 24, 40, .35);
    }

    .pa-modal .modal-header {
        padding: 12px 18px;
        border-bottom: 1px solid var(--pa-rule);
        background: var(--pa-warn-soft);
        color: var(--pa-warn);
    }

    .pa-modal .modal-title {
        color: var(--pa-warn);
        font-size: .95rem;
        font-weight: 650;
    }

    .pa-modal .modal-header .btn-close {
        filter: none;
        opacity: .75;
    }

    .pa-modal .modal-header .btn-close:hover {
        opacity: 1;
    }

    .pa-modal .modal-body {
        padding: 16px 18px;
    }

    .pa-modal .modal-footer {
        padding: 10px 18px;
        border-top: 1px solid var(--pa-rule);
        background: var(--pa-subtle);
        border-radius: 0 0 var(--pa-radius) var(--pa-radius);
    }

    .pa-modal .table {
        margin-bottom: 0;
        color: var(--pa-ink);
    }

    .pa-modal .table th,
    .pa-modal .table td {
        border-color: var(--pa-rule);
    }

    .pa-modal .table thead th {
        background: var(--pa-subtle);
        color: var(--pa-muted);
        font-size: .72rem;
        font-weight: 650;
    }

    .pa-modal .badge.bg-secondary {
        background: var(--pa-subtle-2) !important;
        color: var(--pa-muted) !important;
    }

    .pa-modal .badge.bg-primary {
        background: var(--pa-accent-solid) !important;
        color: #fff !important;
    }

    .pa-modal .alert-warning {
        border: 0;
        background: var(--pa-warn-soft);
        color: var(--pa-warn);
    }

    .pa-modal .btn-outline-secondary {
        color: var(--pa-ink);
        border-color: var(--pa-rule-strong);
    }

    .pa-modal .btn-outline-secondary:hover {
        color: var(--pa-strong);
        background: var(--pa-subtle);
    }

    .pa-modal .btn-warning {
        color: var(--pa-warn-action-ink);
        background: var(--pa-warn-action);
        border-color: var(--pa-warn-action);
    }

    .pa-modal .btn-warning:hover,
    .pa-modal .btn-warning:focus-visible {
        color: var(--pa-warn-action-ink);
        background: var(--pa-warn-action);
        border-color: var(--pa-warn-action);
        filter: brightness(1.06);
    }

    /* ===== Responsive ===== */
    @media (max-width: 991.98px) {
        .pa-sheet__body {
            grid-template-columns: 1fr;
        }

        .pa-toolbar .pa-field--grow {
            flex-basis: 100%;
        }
    }

    @media (max-width: 767.98px) {
        .pa-head {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }

        .pa-period {
            text-align: left;
        }

        .pa-toolbar .pa-field {
            flex: 1 1 42%;
        }

        .pa-toolbar .pa-field--grow {
            flex-basis: 100%;
        }

        .pa-reset {
            flex: 1 1 100%;
            margin-left: 0;
        }

        .pa-board__top {
            flex-direction: column;
            gap: 14px;
        }

        .pa-score {
            align-items: flex-start;
            text-align: left;
        }

        .pa-score__value {
            font-size: 1.9rem;
        }

        .pa-comps {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pa-comp {
            padding: 10px 18px;
        }

        .pa-comp:nth-child(odd) {
            border-left: 0;
        }

        .pa-comp:nth-child(n + 3) {
            border-top: 1px solid var(--pa-rule);
        }

        .pa-sheet__head {
            flex-wrap: wrap;
        }

        .pa-sheet__head .pa-btn {
            width: 100%;
        }

        .pa-punch__grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pa-table-wrap {
            display: none;
        }

        .pa-mdays {
            display: block;
            padding: 14px 18px;
        }

        .pa-mcard + .pa-mcard {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid var(--pa-rule);
        }

        .pa-mcard__title {
            margin: 0 0 8px;
            color: var(--pa-strong);
            font-size: .8rem;
            font-weight: 650;
        }

        .pa-days {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 5px;
        }

        .pa-day {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            padding: 4px 2px;
            border-radius: 6px;
            background: var(--pa-subtle);
        }

        .pa-day__n {
            color: var(--pa-muted);
            font-size: .62rem;
            font-variant-numeric: tabular-nums;
        }

        .pa-day .pa-cell {
            min-width: 22px;
            height: 20px;
        }

        .pa-legend {
            padding: 11px 18px;
        }
    }

    @media (max-width: 575.98px) {
        .pa-punch__grid {
            grid-template-columns: 1fr;
        }

        .pa-sheet__body,
        .pa-sheet__head,
        .pa-sheet__foot {
            padding-left: 16px;
            padding-right: 16px;
        }

        .pa-board__top,
        .pa-ledger__head {
            padding-left: 16px;
            padding-right: 16px;
        }

        .pa-comp {
            padding-left: 16px;
            padding-right: 16px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .pa-page *,
        .pa-modal * {
            transition: none !important;
            animation: none !important;
            scroll-behavior: auto !important;
        }
    }
</style>
