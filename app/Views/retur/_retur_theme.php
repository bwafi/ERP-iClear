<?php

/**
 * Tema halaman Retur Barang Rusak (/retur_barang_rusak).
 *
 * Semua selector berada di bawah `.rt-page` / `.rt-modal` agar tidak bocor ke
 * modul lain. Warna diturunkan dari token tema aplikasi; satu aksen (biru)
 * hanya untuk aksi utama, fokus, dan state terpilih. Tidak ada gradien maupun
 * border kiri berwarna.
 *
 * Kontras teks kecil dijaga >= 4.5:1 di terang maupun gelap:
 *   --rt-muted  #5b6672 di putih (5.9:1), #8fa0b8 di gelap
 *   --rt-ink    #24303f di putih, var(--bs-body-color) di gelap
 * Tombol primary memakai #2f5fe0 (putih 5.2:1), bukan primary template yang
 * lebih terang.
 */
?>

<style>
    /* ===== Token ===== */
    .rt-page {
        --rt-surface: var(--bs-card-bg, #fff);
        --rt-subtle: #f6f8fc;
        --rt-rule: var(--bs-border-color, #e5eaf1);
        --rt-rule-strong: #d7dfea;
        --rt-hover: rgba(47, 95, 224, .045);
        --rt-ink: #24303f;
        --rt-strong: #142033;
        --rt-muted: #5b6672;
        --rt-accent: #2f5fe0;
        --rt-accent-solid: #2f5fe0;
        --rt-accent-hover: #244fc4;
        --rt-accent-ink: #244fc4;
        --rt-accent-soft: rgba(47, 95, 224, .08);
        --rt-success: #0f7a46;
        --rt-success-soft: #e8f7ef;
        --rt-warn: #8a5300;
        --rt-warn-soft: #fff4d6;
        --rt-info: #0b6b8a;
        --rt-info-soft: #e6f6fb;
        --rt-danger: #b02a37;
        --rt-danger-soft: #fdecee;
        --rt-radius: 16px;
        --rt-radius-sm: 10px;
        --rt-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 18px 42px -36px rgba(16, 24, 40, .35);
        color: var(--rt-ink);
        font-size: .875rem;
    }

    html[data-bs-theme="dark"] .rt-page {
        --rt-surface: var(--bs-card-bg, #2a3547);
        --rt-subtle: rgba(255, 255, 255, .035);
        --rt-rule: var(--bs-border-color, rgba(255, 255, 255, .12));
        --rt-rule-strong: rgba(255, 255, 255, .18);
        --rt-hover: rgba(255, 255, 255, .04);
        --rt-ink: var(--bs-body-color, #cfd8e3);
        --rt-strong: #f1f5f9;
        --rt-muted: #8fa0b8;
        --rt-accent: #8ab4ff;
        --rt-accent-solid: #2f6fe4;
        --rt-accent-hover: #2a63cc;
        --rt-accent-ink: #a9c6ff;
        --rt-accent-soft: rgba(138, 180, 255, .12);
        --rt-success: #6fdd9d;
        --rt-success-soft: rgba(111, 221, 157, .12);
        --rt-warn: #ffcf6e;
        --rt-warn-soft: rgba(255, 207, 110, .12);
        --rt-info: #7dd3f0;
        --rt-info-soft: rgba(125, 211, 240, .12);
        --rt-danger: #ff9aa2;
        --rt-danger-soft: rgba(255, 154, 162, .12);
        --rt-shadow: none;
    }

    .rt-page,
    .rt-modal {
        color: var(--rt-ink);
    }

    .rt-page .text-muted,
    .rt-modal .text-muted {
        color: var(--rt-muted) !important;
    }

    .rt-page ::selection,
    .rt-modal ::selection {
        background: var(--rt-accent-soft);
        color: var(--rt-strong);
    }

    .rt-page input,
    .rt-page select,
    .rt-modal input,
    .rt-modal select {
        caret-color: var(--rt-accent);
    }

    /* ===== Kepala halaman ===== */
    .rt-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 16px;
        padding: 24px;
        border: 1px solid var(--rt-rule);
        border-radius: var(--rt-radius);
        background: var(--rt-surface);
        box-shadow: var(--rt-shadow);
    }

    .rt-crumb {
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 0 0 8px;
        padding: 0;
        list-style: none;
        color: var(--rt-muted);
        font-size: .75rem;
    }

    .rt-crumb a {
        color: var(--rt-muted);
        text-decoration: none;
    }

    .rt-crumb a:hover {
        color: var(--rt-accent-ink);
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .rt-title {
        margin: 0;
        color: var(--rt-strong);
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -.02em;
        line-height: 1.2;
    }

    .rt-head p {
        max-width: 68ch;
        margin: 6px 0 0;
        color: var(--rt-muted);
        font-size: .875rem;
        line-height: 1.55;
    }

    .rt-head__meta {
        display: flex;
        flex-wrap: wrap;
        flex: 0 0 auto;
        justify-content: flex-end;
        gap: 8px;
    }

    .rt-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border: 1px solid var(--rt-rule);
        border-radius: 999px;
        background: var(--rt-subtle);
        color: var(--rt-muted);
        font-size: .75rem;
        font-weight: 500;
        line-height: 1.2;
        white-space: nowrap;
    }

    .rt-chip iconify-icon {
        color: var(--rt-muted);
    }

    .rt-chip strong {
        color: var(--rt-strong);
        font-weight: 650;
        font-variant-numeric: tabular-nums;
    }

    .rt-chip--accent {
        border-color: transparent;
        background: var(--rt-accent-soft);
        color: var(--rt-accent-ink);
    }

    .rt-chip--accent iconify-icon,
    .rt-chip--accent strong {
        color: var(--rt-accent-ink);
    }

    /* ===== Filter ===== */
    .rt-filter {
        margin-bottom: 16px;
        padding: 16px 20px;
        border: 1px solid var(--rt-rule);
        border-radius: var(--rt-radius);
        background: var(--rt-surface);
        box-shadow: var(--rt-shadow);
    }

    .rt-filter__form {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 14px;
    }

    .rt-field {
        display: flex;
        flex: 0 1 210px;
        flex-direction: column;
        min-width: 0;
    }

    .rt-field--grow {
        flex: 1 1 260px;
    }

    .rt-field label {
        margin-bottom: 6px;
        color: var(--rt-muted);
        font-size: .75rem;
        font-weight: 600;
    }

    .rt-input {
        width: 100%;
        min-height: 40px;
        padding: 8px 11px;
        border: 1px solid var(--rt-rule-strong);
        border-radius: var(--rt-radius-sm);
        background: var(--rt-surface);
        color: var(--rt-ink);
        font-size: .8125rem;
        line-height: 1.4;
    }

    .rt-input::placeholder {
        color: var(--rt-muted);
        opacity: 1;
    }

    .rt-input:focus {
        outline: none;
        border-color: var(--rt-accent);
        box-shadow: 0 0 0 3px var(--rt-accent-soft);
    }

    .rt-input:disabled {
        cursor: not-allowed;
        border-color: var(--rt-rule);
        background: var(--rt-subtle);
        color: var(--rt-muted);
        opacity: 1;
    }

    .rt-input--sm {
        min-height: 34px;
        padding: 6px 9px;
        font-size: .8125rem;
    }

    .rt-filter__actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: auto;
    }

    /* ===== Tombol ===== */
    .rt-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 40px;
        padding: 8px 14px;
        border: 1px solid transparent;
        border-radius: var(--rt-radius-sm);
        font-size: .8125rem;
        font-weight: 600;
        line-height: 1.2;
        text-decoration: none;
        cursor: pointer;
        transition: background-color .16s ease, border-color .16s ease, color .16s ease;
    }

    .rt-btn:focus-visible {
        outline: 2px solid var(--rt-accent);
        outline-offset: 2px;
    }

    .rt-btn:disabled {
        cursor: not-allowed;
        opacity: .55;
    }

    .rt-btn--primary {
        background: var(--rt-accent-solid);
        color: #fff;
    }

    .rt-btn--primary:hover:not(:disabled),
    .rt-btn--primary:focus-visible {
        background: var(--rt-accent-hover);
        color: #fff;
    }

    .rt-btn--ghost {
        border-color: var(--rt-rule-strong);
        background: transparent;
        color: var(--rt-ink);
    }

    .rt-btn--ghost:hover,
    .rt-btn--ghost:focus-visible {
        background: var(--rt-surface);
        color: var(--rt-strong);
    }

    .rt-btn--danger-ghost {
        border-color: var(--rt-rule-strong);
        background: transparent;
        color: var(--rt-danger);
    }

    .rt-btn--danger-ghost:hover,
    .rt-btn--danger-ghost:focus-visible {
        border-color: var(--rt-danger);
        background: var(--rt-danger-soft);
        color: var(--rt-danger);
    }

    .rt-mini {
        min-height: 28px;
        padding: 3px 8px;
        border: 1px solid var(--rt-rule-strong);
        border-radius: 7px;
        background: transparent;
        color: var(--rt-muted);
        font-size: .75rem;
        font-weight: 600;
        line-height: 1.2;
        cursor: pointer;
        transition: border-color .16s ease, background-color .16s ease, color .16s ease;
    }

    .rt-mini:hover,
    .rt-mini:focus-visible {
        border-color: var(--rt-accent);
        background: var(--rt-accent-soft);
        color: var(--rt-accent-ink);
    }

    .rt-mini:focus-visible {
        outline: 2px solid var(--rt-accent);
        outline-offset: 1px;
    }

    .rt-btn--danger-ghost.rt-mini {
        border-color: var(--rt-rule-strong);
        background: transparent;
        color: var(--rt-danger);
    }

    .rt-btn--danger-ghost.rt-mini:hover,
    .rt-btn--danger-ghost.rt-mini:focus-visible {
        border-color: var(--rt-danger);
        background: var(--rt-danger-soft);
        color: var(--rt-danger);
    }

    /* ===== Flash & notice ===== */
    .rt-flash,
    .rt-notice {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 16px;
        padding: 12px 16px;
        border: 1px solid var(--rt-rule);
        border-radius: var(--rt-radius-sm);
        font-size: .8125rem;
        line-height: 1.5;
    }

    .rt-flash iconify-icon,
    .rt-notice iconify-icon {
        flex: 0 0 auto;
        margin-top: 1px;
    }

    .rt-flash--success {
        border-color: transparent;
        background: var(--rt-success-soft);
        color: var(--rt-success);
    }

    .rt-flash--danger {
        border-color: transparent;
        background: var(--rt-danger-soft);
        color: var(--rt-danger);
    }

    .rt-notice {
        margin: 0 24px 16px;
        background: var(--rt-warn-soft);
        color: var(--rt-warn);
    }

    /* ===== Panel ===== */
    .rt-panel {
        position: relative;
        margin-bottom: 16px;
        overflow: hidden;
        border: 1px solid var(--rt-rule);
        border-radius: var(--rt-radius);
        background: var(--rt-surface);
        box-shadow: var(--rt-shadow);
    }

    .rt-panel__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        padding: 20px 24px 16px;
    }

    .rt-panel__head h2 {
        margin: 0;
        color: var(--rt-strong);
        font-size: 1rem;
        font-weight: 650;
        letter-spacing: -.01em;
    }

    .rt-panel__head p {
        max-width: 78ch;
        margin: 4px 0 0;
        color: var(--rt-muted);
        font-size: .8125rem;
        line-height: 1.5;
    }

    .rt-panel__count {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex: 0 0 auto;
        padding: 5px 11px;
        border: 1px solid var(--rt-rule);
        border-radius: 999px;
        background: var(--rt-subtle);
        color: var(--rt-muted);
        font-size: .75rem;
        white-space: nowrap;
    }

    .rt-panel__count strong {
        color: var(--rt-strong);
        font-weight: 650;
        font-variant-numeric: tabular-nums;
    }

    /* ===== Band dokumen retur ===== */
    .rt-docband {
        display: grid;
        grid-template-columns: minmax(200px, 300px) minmax(160px, 200px) minmax(220px, 1fr);
        gap: 14px;
        padding: 16px 24px;
        border-block: 1px solid var(--rt-rule);
        background: var(--rt-subtle);
    }

    /* ===== Tabel ===== */
    .rt-table-scroll {
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--rt-rule-strong) transparent;
    }

    .rt-table-scroll::-webkit-scrollbar {
        height: 8px;
    }

    .rt-table-scroll::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: var(--rt-rule-strong);
    }

    .rt-table {
        width: 100%;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .rt-table th {
        padding: 10px 14px;
        border-bottom: 1px solid var(--rt-rule);
        background: var(--rt-subtle);
        color: var(--rt-muted);
        font-size: .6875rem;
        font-weight: 650;
        letter-spacing: .06em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .rt-table th:first-child,
    .rt-table td:first-child {
        padding-left: 24px;
    }

    .rt-table th:last-child,
    .rt-table td:last-child {
        padding-right: 24px;
    }

    .rt-table td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--rt-rule);
        color: var(--rt-ink);
        vertical-align: middle;
    }

    .rt-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .rt-table tbody tr:hover td {
        background: var(--rt-hover);
    }

    .rt-table .rt-num {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .rt-col-check {
        width: 40px;
    }

    .rt-pick {
        cursor: pointer;
    }

    .rt-row.is-selected td {
        background: var(--rt-accent-soft);
    }

    .rt-cell {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 3px;
        min-width: 0;
    }

    .rt-code {
        color: var(--rt-strong);
        font-size: .8125rem;
        font-weight: 650;
        letter-spacing: .01em;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .rt-strong {
        color: var(--rt-strong);
        font-size: .8125rem;
        font-weight: 600;
        line-height: 1.4;
    }

    .rt-sub {
        color: var(--rt-muted);
        font-size: .75rem;
        line-height: 1.4;
    }

    .rt-money {
        color: var(--rt-strong);
        font-weight: 600;
        white-space: nowrap;
    }

    .rt-unit {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        max-width: 22ch;
        margin-top: 2px;
        padding: 2px 7px;
        border: 1px solid var(--rt-rule);
        border-radius: 999px;
        background: var(--rt-subtle);
        color: var(--rt-muted);
        font-size: .6875rem;
        line-height: 1.4;
        white-space: nowrap;
    }

    .rt-unit iconify-icon {
        flex: 0 0 auto;
        color: var(--rt-muted);
    }

    .rt-unit span {
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .rt-check {
        width: 18px;
        height: 18px;
        margin: 0;
        accent-color: var(--rt-accent-solid);
        cursor: pointer;
    }

    .rt-check:disabled {
        cursor: not-allowed;
        opacity: .45;
    }

    .rt-check:focus-visible {
        outline: 2px solid var(--rt-accent);
        outline-offset: 2px;
    }

    /* ===== Status retur ===== */
    .rt-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: .6875rem;
        font-weight: 600;
        line-height: 1.5;
        white-space: nowrap;
    }

    .rt-badge--success {
        background: var(--rt-success-soft);
        color: var(--rt-success);
    }

    .rt-badge--muted {
        background: var(--rt-subtle);
        color: var(--rt-muted);
    }

    .rt-badge--warn {
        background: var(--rt-warn-soft);
        color: var(--rt-warn);
    }

    .rt-statusform {
        display: flex;
        align-items: center;
        gap: 6px;
        width: 100%;
        margin-top: 6px;
    }

    .rt-statusform .rt-input {
        flex: 1 1 auto;
        min-width: 0;
    }

    /* ===== Pengganti ===== */
    .rt-replacement {
        display: flex;
        flex-direction: column;
        gap: 6px;
        width: 100%;
        min-width: 150px;
    }

    .rt-replacement.is-off {
        opacity: 1;
    }

    .rt-col-hpp {
        min-width: 110px;
    }

    .rt-col-status {
        min-width: 220px;
    }

    .rt-col-optional {
        min-width: 150px;
    }

    /* ===== Action bar ===== */
    .rt-actionbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        padding: 14px 24px;
        border-top: 1px solid var(--rt-rule);
        background: var(--rt-subtle);
    }

    .rt-summary {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px 16px;
        color: var(--rt-muted);
        font-size: .8125rem;
    }

    .rt-summary strong {
        color: var(--rt-strong);
        font-weight: 650;
        font-variant-numeric: tabular-nums;
    }

    .rt-summary__value {
        padding-left: 16px;
        border-left: 1px solid var(--rt-rule-strong);
    }

    /* ===== Riwayat ===== */
    .rt-actions {
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
    }

    .rt-actions form {
        display: inline-flex;
        margin: 0;
    }

    /* ===== Empty state ===== */
    .rt-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 52px 24px;
        text-align: center;
    }

    .rt-empty__icon {
        display: grid;
        width: 48px;
        height: 48px;
        place-items: center;
        border-radius: 14px;
        background: var(--rt-accent-soft);
        color: var(--rt-accent-ink);
    }

    .rt-empty h3 {
        margin: 4px 0 0;
        color: var(--rt-strong);
        font-size: .9375rem;
        font-weight: 650;
    }

    .rt-empty p {
        max-width: 44ch;
        margin: 0;
        color: var(--rt-muted);
        font-size: .8125rem;
        line-height: 1.55;
    }

    .rt-empty .rt-btn {
        margin-top: 8px;
    }

    /* ===== Modal ===== */
    .rt-modal {
        --rt-surface: var(--bs-card-bg, #fff);
        --rt-subtle: #f6f8fc;
        --rt-rule: var(--bs-border-color, #e5eaf1);
        --rt-rule-strong: #d7dfea;
        --rt-ink: #24303f;
        --rt-strong: #142033;
        --rt-muted: #5b6672;
        --rt-accent-ink: #244fc4;
        --rt-accent-soft: rgba(47, 95, 224, .08);
        --rt-success: #0f7a46;
        --rt-success-soft: #e8f7ef;
        --rt-warn: #8a5300;
        --rt-warn-soft: #fff4d6;
        --rt-danger: #b02a37;
        --rt-danger-soft: #fdecee;
        --rt-radius: 16px;
        --rt-radius-sm: 10px;
        font-size: .875rem;
    }

    html[data-bs-theme="dark"] .rt-modal {
        --rt-surface: var(--bs-card-bg, #2a3547);
        --rt-subtle: rgba(255, 255, 255, .035);
        --rt-rule: var(--bs-border-color, rgba(255, 255, 255, .12));
        --rt-rule-strong: rgba(255, 255, 255, .18);
        --rt-ink: var(--bs-body-color, #cfd8e3);
        --rt-strong: #f1f5f9;
        --rt-muted: #8fa0b8;
        --rt-accent-ink: #a9c6ff;
        --rt-accent-soft: rgba(138, 180, 255, .12);
        --rt-success: #6fdd9d;
        --rt-success-soft: rgba(111, 221, 157, .12);
        --rt-warn: #ffcf6e;
        --rt-warn-soft: rgba(255, 207, 110, .12);
        --rt-danger: #ff9aa2;
        --rt-danger-soft: rgba(255, 154, 162, .12);
    }

    .rt-modal .modal-content {
        border: 1px solid var(--rt-rule);
        border-radius: var(--rt-radius);
        background: var(--rt-surface);
        color: var(--rt-ink);
    }

    .rt-modal .modal-header {
        align-items: flex-start;
        gap: 12px;
        border-bottom: 1px solid var(--rt-rule);
        background: var(--rt-subtle);
    }

    .rt-modal__title {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .rt-modal__icon {
        display: grid;
        flex: 0 0 40px;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 12px;
        background: var(--rt-accent-soft);
        color: var(--rt-accent-ink);
    }

    .rt-modal .modal-title {
        color: var(--rt-strong);
        font-size: 1rem;
        font-weight: 650;
    }

    .rt-modal__title p {
        margin: 2px 0 0;
        color: var(--rt-muted);
        font-size: .75rem;
    }

    .rt-modal .modal-body {
        padding: 20px 24px;
    }

    .rt-modal__meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px 20px;
        margin: 0 0 18px;
        padding: 14px 16px;
        border: 1px solid var(--rt-rule);
        border-radius: 10px;
        background: var(--rt-subtle);
    }

    .rt-modal__meta dt {
        color: var(--rt-muted);
        font-size: .6875rem;
        font-weight: 650;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .rt-modal__meta dd {
        margin: 3px 0 0;
        color: var(--rt-strong);
        font-size: .8125rem;
        font-weight: 600;
    }

    .rt-modal .rt-table th:first-child,
    .rt-modal .rt-table td:first-child {
        padding-left: 14px;
    }

    .rt-modal .rt-table th:last-child,
    .rt-modal .rt-table td:last-child {
        padding-right: 14px;
    }

    .rt-modal .modal-footer {
        border-top: 1px solid var(--rt-rule);
    }

    .rt-scroll-hint {
        display: none;
        margin: 0 0 8px;
        color: var(--rt-muted);
        font-size: .75rem;
    }

    /* ===== Responsif ===== */
    @media (max-width: 1100px) {
        .rt-head {
            flex-direction: column;
        }

        .rt-head__meta {
            justify-content: flex-start;
        }

        .rt-docband {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
    }

    @media (max-width: 991.98px) {
        .rt-table--klaim thead,
        .rt-table--riwayat thead {
            display: none;
        }

        .rt-table--klaim,
        .rt-table--riwayat,
        .rt-table--klaim tbody,
        .rt-table--riwayat tbody,
        .rt-table--klaim tr,
        .rt-table--riwayat tr,
        .rt-table--klaim td,
        .rt-table--riwayat td {
            display: block;
            width: 100%;
        }

        .rt-table--klaim tr,
        .rt-table--riwayat tr {
            padding: 14px 18px;
            border-bottom: 1px solid var(--rt-rule);
        }

        .rt-page .rt-table--klaim tbody tr:last-child,
        .rt-page .rt-table--riwayat tbody tr:last-child {
            border-bottom: 0;
        }

        .rt-page .rt-table--klaim td,
        .rt-page .rt-table--riwayat td {
            display: grid;
            grid-template-columns: 7.25rem minmax(0, 1fr);
            align-items: start;
            gap: 12px;
            padding: 6px 0;
            border: 0;
            text-align: left;
        }

        .rt-page .rt-table--klaim td::before,
        .rt-page .rt-table--riwayat td::before {
            content: attr(data-label);
            color: var(--rt-muted);
            font-size: .72rem;
            font-weight: 600;
            line-height: 1.4;
        }

        .rt-page .rt-table--klaim td.rt-pick {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .rt-page .rt-table td.rt-num {
            text-align: left;
        }

        .rt-page .rt-table td:first-child,
        .rt-page .rt-table td:last-child {
            padding-left: 0;
            padding-right: 0;
        }

        .rt-page .rt-table tbody tr:hover td {
            background: transparent;
        }

        .rt-page .rt-table--klaim tr.is-selected {
            background: var(--rt-accent-soft);
        }

        .rt-page .rt-table--klaim tr.is-selected td {
            background: transparent;
        }

        .rt-cell {
            align-items: flex-start;
        }

        .rt-actions {
            justify-content: flex-start;
        }

        .rt-statusform {
            max-width: 260px;
        }
    }

    @media (max-width: 575.98px) {
        .rt-input,
        .rt-input--sm {
            font-size: 1rem;
        }

        .rt-scroll-hint {
            display: block;
        }
        .rt-head,
        .rt-filter {
            padding: 18px;
        }

        .rt-panel__head,
        .rt-docband,
        .rt-actionbar,
        .rt-notice {
            padding-inline: 18px;
        }

        .rt-notice {
            margin-inline: 0;
            border-inline: 0;
            border-radius: 0;
        }

        .rt-field,
        .rt-field--grow {
            flex: 1 1 100%;
        }

        .rt-filter__actions {
            width: 100%;
            margin-left: 0;
        }

        .rt-filter__actions .rt-btn {
            flex: 1;
        }

        .rt-panel__head {
            flex-direction: column;
            gap: 10px;
        }

        .rt-summary__value {
            padding-left: 0;
            border-left: 0;
        }

        .rt-actionbar .rt-btn {
            width: 100%;
        }

        .rt-page .rt-table--klaim td,
        .rt-page .rt-table--riwayat td {
            grid-template-columns: 6rem minmax(0, 1fr);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .rt-btn,
        .rt-mini {
            transition: none;
        }
    }
</style>
