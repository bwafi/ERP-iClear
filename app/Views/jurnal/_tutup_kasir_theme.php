<?php

/**
 * Design system halaman Tutup Kasir / Rekap Kasir.
 *
 * Scoped di bawah `.tutup-kasir` supaya tidak bocor ke halaman lain.
 * Semua warna dari token template, ikut mode gelap/terang.
 *
 * Calm & trustworthy · clean & efficient.
 * Operate-first: angka jadi satu-satunya bintang, warna dipakai semantik, bukan hiasan.
 */
?>
<style>
    /* ===== 1. Token ===== */
    .tutup-kasir {
        --tk-surface: var(--bs-card-bg, var(--bs-body-bg, #fff));
        --tk-canvas: var(--bs-body-bg, #f2f4f7);
        --tk-rule: var(--bs-border-color, #d7dde4);
        --tk-rule-strong: var(--bs-border-color, #b9c2ce);
        --tk-ink: #1f2937;
        --tk-muted: #5a6672;
        --tk-kas: #2553b9;
        --tk-transfer: #047857;
        --tk-expense: #c1121f;
        --tk-success: #057a4d;
        --tk-warning: #b45309;
        --tk-info: #0369a1;
        --tk-amber-bg: #fff7e6;
        --tk-amber-fg: #8a4b00;
        --tk-danger-bg: #fef3f2;
        --tk-danger-fg: #b91c1c;
        --tk-primary-bg: #eef2ff;
        --tk-primary-fg: #2553b9;
        --tk-radius: 0.5rem;
        --tk-elev-1: 0 1px 2px rgba(0, 0, 0, 0.04);
        --tk-elev-2: 0 1px 3px rgba(0, 0, 0, 0.08), 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    /* ===== 2. Canvas & layout ===== */
    .tutup-kasir.tk-page {
        background: var(--tk-canvas);
        min-height: 100vh;
    }
    .tutup-kasir .tk-header {
        background: var(--tk-surface);
        border-bottom: 1px solid var(--tk-rule);
    }
    .tutup-kasir .tk-section {
        background: var(--tk-surface);
        border-radius: var(--tk-radius);
        box-shadow: var(--tk-elev-1);
        border: 1px solid var(--tk-rule);
        overflow: hidden;
    }
    .tutup-kasir .tk-section--flat {
        background: transparent;
        box-shadow: none;
        border: none;
    }
    .tutup-kasir .tk-section-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--tk-rule);
        color: var(--tk-ink);
        font-weight: 600;
        font-size: 0.9375rem;
    }
    .tutup-kasir .tk-section-body {
        padding: 1.25rem;
    }
    .tutup-kasir .tk-card {
        background: var(--tk-surface);
        border-radius: var(--tk-radius);
        box-shadow: var(--tk-elev-1);
        border: 1px solid var(--tk-rule);
    }
    .tutup-kasir .tk-card--subtle {
        background: var(--bs-secondary-bg, #f2f4f7);
        border-color: var(--tk-rule);
    }

    /* ===== 3. Hero ===== */
    .tutup-kasir .tk-hero {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    @media (min-width: 992px) {
        .tutup-kasir .tk-hero {
            grid-template-columns: 1.05fr 0.95fr;
            align-items: start;
        }
    }
    .tutup-kasir .tk-hero__balance {
        padding: 1.25rem;
    }
    .tutup-kasir .tk-hero__input {
        padding: 1.25rem;
        background: var(--bs-secondary-bg, #f2f4f7);
        border-radius: var(--tk-radius);
        border: 1px solid var(--tk-rule);
    }
    .tutup-kasir .tk-balance__label {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--tk-muted);
        font-size: 0.8125rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin-bottom: 0.375rem;
    }
    .tutup-kasir .tk-balance__amount {
        color: var(--tk-ink);
        font-size: 1.625rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        line-height: 1.15;
        margin-bottom: 0.375rem;
    }
    .tutup-kasir .tk-balance__note {
        color: var(--tk-muted);
        font-size: 0.8125rem;
        line-height: 1.45;
        margin-top: 0.375rem;
    }
    .tutup-kasir .tk-balance__breakdown {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        color: var(--tk-muted);
        font-size: 0.875rem;
        margin-top: 0.5rem;
    }
    .tutup-kasir .tk-balance__breakdown strong {
        color: var(--tk-ink);
        font-weight: 500;
    }
    .tutup-kasir .tk-balance__separator {
        color: var(--tk-muted);
        opacity: 0.5;
        margin-left: 0.25rem;
    }
    .tutup-kasir .tk-balance--primary .tk-balance__amount {
        color: var(--tk-primary-fg);
    }

    /* ===== 4. Input ===== */
    .tutup-kasir .tk-input__label {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--tk-ink);
        margin-bottom: 0.5rem;
        display: inline-block;
    }
    .tutup-kasir .tk-input-group {
        display: flex;
        gap: 0;
    }
    .tutup-kasir .tk-input-group .input-group-text {
        background: var(--tk-surface);
        border: 1px solid var(--tk-rule);
        border-right: none;
        border-radius: var(--tk-radius) 0 0 var(--tk-radius);
        font-weight: 600;
        color: var(--tk-muted);
        padding: 0.625rem 0.875rem;
    }
    .tutup-kasir .tk-input-group .form-control {
        border: 1px solid var(--tk-rule);
        border-left: none;
        border-radius: 0 var(--tk-radius) var(--tk-radius) 0;
        font-size: 1.125rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        padding: 0.625rem 0.875rem;
        color: var(--tk-ink);
    }
    .tutup-kasir .tk-input-group .form-control:focus {
        border-color: var(--tk-kas);
        box-shadow: 0 0 0 4px rgba(37, 83, 185, 0.1);
        outline: none;
    }
    .tutup-kasir .tk-input__hint {
        font-size: 0.8125rem;
        color: var(--tk-muted);
        margin-top: 0.5rem;
        line-height: 1.5;
    }
    .tutup-kasir .tk-input__hint strong {
        color: var(--tk-ink);
    }

    /* ===== 5. Summary items ===== */
    .tutup-kasir .tk-summary {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    @media (min-width: 768px) {
        .tutup-kasir .tk-summary {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (min-width: 992px) {
        .tutup-kasir .tk-summary {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    .tutup-kasir .tk-summary__item {
        background: var(--bs-secondary-bg, #f2f4f7);
        border: 1px solid var(--tk-rule);
        border-radius: var(--tk-radius);
        padding: 1rem;
    }
    .tutup-kasir .tk-summary__item--income-total {
        background: var(--tk-primary-bg);
        border-color: var(--tk-primary-fg);
    }
    .tutup-kasir .tk-summary__item--expense-total {
        background: var(--tk-danger-bg);
        border-color: var(--tk-danger-fg);
    }
    .tutup-kasir .tk-summary__title {
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--tk-muted);
        line-height: 1.4;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .tutup-kasir .tk-summary__value {
        color: var(--tk-ink);
        font-size: 1.3125rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        line-height: 1.2;
        margin-top: 0.25rem;
    }
    .tutup-kasir .tk-summary__item--income .tk-summary__value { color: var(--tk-success); }
    .tutup-kasir .tk-summary__item--income-total .tk-summary__value { color: var(--tk-primary-fg); }
    .tutup-kasir .tk-summary__item--expense .tk-summary__value { color: var(--tk-expense); }
    .tutup-kasir .tk-summary__item--expense-total .tk-summary__value { color: var(--tk-danger-fg); }
    .tutup-kasir .tk-summary__sub {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-top: 0.75rem;
        border-top: 1px solid color-mix(in srgb, var(--tk-rule) 55%, transparent);
        padding-top: 0.625rem;
        font-size: 0.8125rem;
    }
    .tutup-kasir .tk-summary__sub strong {
        color: var(--tk-ink);
        font-weight: 500;
    }

    /* ===== 6. Collapsible details ===== */
    .tutup-kasir .tk-details {
        margin-top: 1.5rem;
    }
    .tutup-kasir .tk-details summary {
        cursor: pointer;
        list-style: none;
        display: flex;
        align-items: center;
        gap: 0.625rem;
        font-weight: 600;
        color: var(--tk-ink);
        padding: 0.875rem 1.125rem;
        background: var(--bs-secondary-bg, #f2f4f7);
        border: 1px solid var(--tk-rule);
        border-radius: var(--tk-radius);
        user-select: none;
        transition: background 0.15s ease, border-color 0.15s ease;
        font-size: 0.9375rem;
    }
    .tutup-kasir .tk-details summary:hover {
        background: var(--tk-surface);
        border-color: var(--tk-rule-strong);
    }
    .tutup-kasir .tk-details summary:focus-visible {
        outline: 2px solid var(--tk-kas);
        outline-offset: 2px;
    }
    .tutup-kasir .tk-details summary::-webkit-details-marker {
        display: none;
    }
    .tutup-kasir .tk-details summary::after {
        content: '';
        width: 0.625rem;
        height: 0.625rem;
        margin-left: auto;
        border-right: 1.5px solid var(--tk-muted);
        border-bottom: 1.5px solid var(--tk-muted);
        transform: rotate(45deg);
        transition: transform 0.15s ease, border-color 0.15s ease;
    }
    .tutup-kasir .tk-details[open] summary::after {
        transform: rotate(225deg);
    }
    .tutup-kasir .tk-details__content {
        padding: 0.75rem 1.125rem;
        border: 1px solid var(--tk-rule);
        border-top: none;
        border-radius: 0 0 var(--tk-radius) var(--tk-radius);
        background: var(--bs-secondary-bg, #f2f4f7);
    }
    .tutup-kasir .tk-details__row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        padding: 0.5rem 0;
        gap: 1rem;
    }
    .tutup-kasir .tk-details__row:last-child {
        border-bottom: none;
    }
    .tutup-kasir .tk-details__label {
        color: var(--tk-muted);
        font-size: 0.875rem;
        font-weight: 500;
        flex: 1;
    }
    .tutup-kasir .tk-details__value {
        color: var(--tk-ink);
        font-size: 0.9375rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .tutup-kasir .tk-details__value--positive { color: var(--tk-success); }
    .tutup-kasir .tk-details__value--negative { color: var(--tk-expense); }
    .tutup-kasir .tk-details__value--warning { color: var(--tk-warning); }

    /* ===== 7. Alerts ===== */
    .tutup-kasir .tk-alert {
        border-radius: var(--tk-radius);
        padding: 0.875rem 1.125rem;
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
    }
    .tutup-kasir .tk-alert iconify-icon {
        flex-shrink: 0;
        width: 1.125rem;
        height: 1.125rem;
        margin-top: 0.125rem;
    }
    .tutup-kasir .tk-alert--warning {
        background: var(--tk-amber-bg);
        color: var(--tk-amber-fg);
        border: 1px solid color-mix(in srgb, #f59e0b 22%, var(--tk-rule));
    }
    .tutup-kasir .tk-alert--warning iconify-icon { color: var(--tk-warning); }
    .tutup-kasir .tk-alert--danger {
        background: var(--tk-danger-bg);
        color: var(--tk-danger-fg);
        border: 1px solid color-mix(in srgb, #dc3545 22%, var(--tk-rule));
    }
    .tutup-kasir .tk-alert--danger iconify-icon { color: var(--tk-expense); }
    .tutup-kasir .tk-alert__title {
        font-weight: 600;
        margin-bottom: 0.125rem;
    }
    .tutup-kasir .tk-alert__desc {
        font-size: 0.875rem;
        margin: 0;
        line-height: 1.45;
    }
    .tutup-kasir .tk-alert__desc strong {
        color: var(--tk-ink);
    }
    .tutup-kasir .tk-alert__link {
        font-weight: 600;
        text-decoration: underline;
    }

    /* ===== 8. Late warning ===== */
    .tutup-kasir .tk-late {
        background: var(--tk-amber-bg);
        border: 1px solid color-mix(in srgb, #f59e0b 22%, var(--tk-rule));
        border-radius: var(--tk-radius);
        padding: 0.875rem 1.125rem;
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
    }
    .tutup-kasir .tk-late iconify-icon {
        color: var(--tk-warning);
        flex-shrink: 0;
        width: 1.125rem;
        height: 1.125rem;
        margin-top: 0.125rem;
    }
    .tutup-kasir .tk-late__title {
        font-weight: 600;
        color: var(--tk-amber-fg);
        margin-bottom: 0.125rem;
    }
    .tutup-kasir .tk-late__desc {
        font-size: 0.875rem;
        color: var(--tk-amber-fg);
        line-height: 1.45;
        margin: 0;
    }
    .tutup-kasir .tk-late__desc strong {
        color: var(--tk-ink);
    }

    /* ===== 9. Badge (status chips) ===== */
    .tutup-kasir .tk-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .tutup-kasir .tk-badge--success {
        background: var(--bs-success-bg-subtle, #dffff3);
        color: var(--bs-success, #057a4d);
    }
    .tutup-kasir .tk-badge--info {
        background: var(--bs-info-bg-subtle, #e1f5fa);
        color: var(--bs-info, #0369a1);
    }

    /* ===== 10. Sticky footer ===== */
    .tutup-kasir .tk-footer {
        position: sticky;
        bottom: 0;
        z-index: 100;
        background: var(--tk-surface);
        border-top: 1px solid var(--tk-rule);
        padding: 1rem 1.5rem;
        margin: 1.5rem -1.5rem -1.5rem;
        border-radius: 0 0 var(--tk-radius) var(--tk-radius);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.06);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }
    .tutup-kasir .tk-footer .tk-footer__meta {
        font-size: 0.875rem;
        color: var(--tk-muted);
    }
    .tutup-kasir .tk-footer .tk-footer__meta strong {
        color: var(--tk-ink);
    }
    .tutup-kasir .tk-btn {
        padding: 0.625rem 1.375rem;
        font-weight: 600;
        border-radius: var(--tk-radius);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        font-size: 0.9375rem;
        border: none;
        cursor: pointer;
        transition: transform 0.1s ease, box-shadow 0.15s ease;
    }
    .tutup-kasir .tk-btn--primary {
        background: var(--tk-kas);
        color: #fff;
        box-shadow: 0 1px 2px rgba(37, 83, 185, 0.25);
    }
    .tutup-kasir .tk-btn--primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(37, 83, 185, 0.35);
    }
    .tutup-kasir .tk-btn--primary:active {
        transform: translateY(0);
    }
    .tutup-kasir .tk-btn--danger {
        background: var(--tk-expense);
        color: #fff;
        box-shadow: 0 1px 2px rgba(193, 18, 31, 0.25);
    }
    .tutup-kasir .tk-btn--danger:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(193, 18, 31, 0.35);
    }
    .tutup-kasir .tk-btn--danger:active {
        transform: translateY(0);
    }
    .tutup-kasir .tk-btn--secondary {
        background: var(--bs-secondary-bg, #f2f4f7);
        color: var(--tk-ink);
        border: 1px solid var(--tk-rule);
    }
    .tutup-kasir .tk-btn--secondary:hover {
        background: #e8ebf0;
    }
    .tutup-kasir .tk-btn--disabled {
        opacity: 0.55;
        cursor: not-allowed;
        transform: none !important;
        box-shadow: none !important;
    }
    .tutup-kasir .tk-btn iconify-icon {
        width: 1.125rem;
        height: 1.125rem;
    }

    /* ===== 11. Utilities ===== */
    .tutup-kasir .tk-tabular {
        font-variant-numeric: tabular-nums;
    }
    .tutup-kasir .tk-hairline {
        height: 1px;
        background: var(--tk-rule);
        margin: 0.75rem 0;
        border: none;
    }
    .tutup-kasir .tk-mt-sm { margin-top: 0.5rem; }
    .tutup-kasir .tk-mb-sm { margin-bottom: 0.5rem; }
    .tutup-kasir .tk-mb-md { margin-bottom: 0.75rem; }
    .tutup-kasir .tk-text-right { text-align: right; }

    /* ===== Closing statement redesign ===== */
    .tutup-kasir {
        --tk-ink: var(--bs-body-color, #253040);
        --tk-muted: var(--bs-secondary-color, #596675);
        --tk-surface: var(--bs-card-bg, var(--bs-body-bg, #fff));
        --tk-canvas: var(--bs-body-bg, #f2f4f7);
        --tk-rule: var(--bs-border-color, #d7dde4);
        --tk-kas: var(--bs-primary, #2553b9);
        --tk-success: var(--bs-success, #198754);
        --tk-expense: #c1121f;
        --tk-transfer: var(--bs-info, #0d6efd);
    }

    .tutup-kasir .tk-context {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 0.375rem 1.25rem;
        font-size: 0.8125rem;
    }
    .tutup-kasir .tk-context strong { margin-left: 0.25rem; font-weight: 600; }
    .tutup-kasir .tk-print-label { display: grid; gap: 0.1rem; line-height: 1.1; text-align: left; }
    .tutup-kasir .tk-print-label small { font-size: 0.68rem; font-weight: 500; }
    .tutup-kasir .tk-admin-filter {
        display: grid;
        grid-template-columns: minmax(13rem, 1.2fr) minmax(10rem, 0.8fr) minmax(12rem, 0.9fr) auto;
        align-items: end;
        gap: 0.75rem;
        padding: 0.9rem 1rem;
        margin-bottom: 1rem;
        color: var(--tk-ink);
        background: var(--tk-surface);
        border: 1px solid var(--tk-rule);
        border-radius: var(--tk-radius);
        box-shadow: var(--tk-elev-1);
    }
    .tutup-kasir .tk-admin-filter__intro { display: grid; gap: 0.2rem; padding-bottom: 0.15rem; }
    .tutup-kasir .tk-admin-filter__intro strong { font-size: 0.9rem; font-weight: 650; }
    .tutup-kasir .tk-admin-filter__intro span { color: var(--tk-muted); font-size: 0.78rem; line-height: 1.4; }
    .tutup-kasir .tk-admin-filter .form-label { margin-bottom: 0.35rem; color: var(--tk-muted); font-size: 0.76rem; font-weight: 600; }
    .tutup-kasir .tk-admin-filter .form-control,
    .tutup-kasir .tk-admin-filter .form-select { min-height: 2.55rem; border-color: var(--tk-rule); color: var(--tk-ink); }
    .tutup-kasir .tk-admin-filter .form-control:focus,
    .tutup-kasir .tk-admin-filter .form-select:focus { border-color: var(--tk-kas); box-shadow: 0 0 0 3px color-mix(in srgb, var(--tk-kas) 16%, transparent); }
    .tutup-kasir .tk-title-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-top: 1.25rem;
    }
    .tutup-kasir .tk-page-title {
        color: var(--tk-ink);
        font-size: 1.5rem;
        font-weight: 650;
        letter-spacing: -0.025em;
        margin: 0 0 0.25rem;
    }
    .tutup-kasir .tk-page-subtitle { color: var(--tk-muted); font-size: 0.9rem; }
    .tutup-kasir .tk-state {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.4rem 0.7rem;
        border: 1px solid var(--tk-rule);
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .tutup-kasir .tk-state--success { color: var(--bs-success-text-emphasis, var(--tk-success)); background: var(--bs-success-bg-subtle, #dffff3); }
    .tutup-kasir .tk-state--info { color: var(--bs-primary-text-emphasis, var(--tk-kas)); background: var(--bs-primary-bg-subtle, #eef2ff); }
    .tutup-kasir .tk-state__dot { width: 0.45rem; height: 0.45rem; border-radius: 50%; background: currentColor; }

    .tutup-kasir .tk-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.125rem 1.25rem;
        border-bottom: 1px solid var(--tk-rule);
    }
    .tutup-kasir .tk-section-header h2,
    .tutup-kasir .tk-reconcile h2 {
        color: var(--tk-ink);
        font-size: 1.05rem;
        font-weight: 650;
        letter-spacing: -0.01em;
    }
    .tutup-kasir .tk-section-header p { color: var(--tk-muted); font-size: 0.8125rem; line-height: 1.45; }
    .tutup-kasir .tk-ledger-wrap { overflow-x: auto; }
    .tutup-kasir .tk-ledger {
        width: 100%;
        min-width: 660px;
        border-collapse: collapse;
        color: var(--tk-ink);
        font-size: 0.9rem;
    }
    .tutup-kasir .tk-ledger th,
    .tutup-kasir .tk-ledger td {
        padding: 0.9rem 1.25rem;
        border-bottom: 1px solid var(--tk-rule);
        vertical-align: middle;
    }
    .tutup-kasir .tk-ledger thead th {
        padding-top: 0.7rem;
        padding-bottom: 0.7rem;
        color: var(--tk-muted);
        background: var(--bs-secondary-bg, #f6f7f9);
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.045em;
        text-transform: uppercase;
    }
    .tutup-kasir .tk-ledger thead th:first-child { width: 40%; }
    .tutup-kasir .tk-ledger tbody th,
    .tutup-kasir .tk-ledger tfoot th { text-align: left; font-weight: 600; }
    .tutup-kasir .tk-ledger tbody th > span,
    .tutup-kasir .tk-ledger tfoot th > span { display: block; }
    .tutup-kasir .tk-ledger tbody th small,
    .tutup-kasir .tk-ledger tfoot th small {
        display: block;
        margin-top: 0.2rem;
        color: var(--tk-muted);
        font-size: 0.77rem;
        font-weight: 400;
        line-height: 1.4;
    }
    .tutup-kasir .tk-ledger__mobile-total { display: none !important; }
    .tutup-kasir .tk-method { display: inline-flex; align-items: center; gap: 0.45rem; }
    .tutup-kasir .tk-method__dot { width: 0.5rem; height: 0.5rem; border-radius: 50%; }
    .tutup-kasir .tk-method__dot--cash { background: var(--tk-kas); }
    .tutup-kasir .tk-method__dot--transfer { background: var(--tk-transfer); }
    .tutup-kasir .tk-ledger__opening { background: color-mix(in srgb, var(--bs-primary-bg-subtle, #eef2ff) 42%, var(--tk-surface)); }
    .tutup-kasir .tk-ledger__opening td { font-weight: 600; }
    .tutup-kasir .tk-ledger__income td strong { display: block; color: var(--tk-success); font-weight: 650; }
    .tutup-kasir .tk-ledger__expense td { color: var(--tk-expense); font-weight: 600; }
    .tutup-kasir .tk-ledger__note {
        display: block;
        margin-top: 0.25rem;
        color: var(--tk-muted);
        font-size: 0.74rem;
        font-weight: 400;
        line-height: 1.4;
        white-space: normal;
    }
    .tutup-kasir .tk-ledger__internal td:first-of-type { color: var(--tk-transfer); font-weight: 600; }
    .tutup-kasir .tk-ledger__muted { color: var(--tk-muted); font-size: 0.8rem; font-weight: 400; }
    .tutup-kasir .tk-ledger__missing { color: var(--bs-warning-text-emphasis, #8a4b00); font-weight: 600; }
    .tutup-kasir .tk-ledger tfoot th,
    .tutup-kasir .tk-ledger tfoot td {
        padding-top: 1rem;
        padding-bottom: 1rem;
        background: var(--bs-secondary-bg, #f6f7f9);
        border-bottom: 0;
        font-weight: 700;
    }
    .tutup-kasir .tk-ledger tfoot td { color: var(--tk-kas); font-size: 1rem; white-space: nowrap; }
    .tutup-kasir .tk-ledger em { color: inherit; font-style: normal; }

    .tutup-kasir .tk-reconcile {
        padding: 1.15rem 1.25rem 1.25rem;
        background: var(--tk-surface);
        border: 1px solid var(--tk-rule);
        border-radius: var(--tk-radius);
        box-shadow: var(--tk-elev-1);
    }
    .tutup-kasir .tk-reconcile__intro { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
    .tutup-kasir .tk-reconcile__icon {
        display: grid;
        place-items: center;
        width: 2.5rem;
        height: 2.5rem;
        flex: 0 0 auto;
        color: var(--tk-kas);
        background: var(--bs-primary-bg-subtle, #eef2ff);
        border-radius: 0.625rem;
    }
    .tutup-kasir .tk-reconcile__intro p { color: var(--tk-muted); font-size: 0.82rem; line-height: 1.45; }
    .tutup-kasir .tk-reconcile__body {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        align-items: stretch;
        border: 1px solid var(--tk-rule);
        border-radius: 0.625rem;
        overflow: hidden;
    }
    .tutup-kasir .tk-reconcile__input { padding: 0.9rem 1rem; }
    .tutup-kasir .tk-input__label,
    .tutup-kasir .tk-reconcile__label { display: block; margin-bottom: 0.45rem; color: var(--tk-muted); font-size: 0.76rem; font-weight: 600; }
    .tutup-kasir .tk-input-group .input-group-text { color: var(--tk-muted); background: var(--bs-secondary-bg, #f6f7f9); }
    .tutup-kasir .tk-input-group .form-control { min-width: 0; background: var(--tk-surface); }
    .tutup-kasir .tk-input__hint { display: block; margin-top: 0.4rem; color: var(--tk-muted); font-size: 0.73rem; }
    .tutup-kasir .tk-details { margin-top: 0; }
    .tutup-kasir .tk-details summary { border-radius: var(--tk-radius); }
    .tutup-kasir .tk-details[open] summary { border-radius: var(--tk-radius) var(--tk-radius) 0 0; }
    .tutup-kasir .tk-details__content { color: var(--tk-ink); line-height: 1.5; }
    .tutup-kasir .tk-formula { color: var(--tk-muted); font-size: 0.82rem; }
    .tutup-kasir .tk-details__row { border-bottom: 1px solid var(--tk-rule); }
    .tutup-kasir .tk-details__row > span { color: var(--tk-muted); font-size: 0.84rem; }
    .tutup-kasir .tk-details__row > strong { font-size: 0.87rem; }
    .tutup-kasir .tk-footer { gap: 1rem; }
    .tutup-kasir .tk-footer__meta { margin-right: auto; color: var(--tk-muted); font-size: 0.82rem; }
    .tutup-kasir .tk-footer .tk-btn:focus-visible { outline: 2px solid var(--tk-kas); outline-offset: 3px; }
    .tutup-kasir .tk-alert--warning { background: var(--bs-warning-bg-subtle, #fff7e6); color: var(--bs-warning-text-emphasis, #8a4b00); }
    .tutup-kasir .tk-alert--danger { background: var(--bs-danger-bg-subtle, #fef3f2); color: var(--bs-danger-text-emphasis, #b91c1c); }

    @media (max-width: 767.98px) {
        #formTutupKasir { display: flex; flex-direction: column; }
        .tutup-kasir .tk-admin-filter { grid-template-columns: minmax(0, 1fr); gap: 0.7rem; }
        .tutup-kasir .tk-admin-filter .tk-btn { width: 100%; }
        #formTutupKasir > .tk-alert { order: 0; }
        #formTutupKasir > .tk-reconcile { order: 1; }
        #formTutupKasir > .tk-flow-section { order: 2; }
        #formTutupKasir > .tk-details { order: 3; }
        #formTutupKasir > .tk-late { order: 4; }
        #formTutupKasir > .tk-footer { order: 5; }
        .tutup-kasir .tk-header .container-fluid { padding-bottom: 1rem !important; }
        .tutup-kasir .tk-header nav[aria-label="breadcrumb"] > .breadcrumb { display: none; }
        .tutup-kasir .tk-context { justify-content: flex-start; width: 100%; margin-top: 0.25rem; }
        .tutup-kasir .tk-title-row { align-items: flex-start; flex-direction: column; gap: 0.65rem; margin-top: 1rem; }
        .tutup-kasir .tk-page-title { font-size: 1.3rem; }
        .tutup-kasir .tk-section-header { align-items: flex-start; flex-direction: column; padding: 0.85rem 1rem; }
        .tutup-kasir .tk-ledger-wrap { overflow: visible; }
        .tutup-kasir .tk-ledger { display: block; min-width: 0; font-size: 0.82rem; }
        .tutup-kasir .tk-ledger thead { display: none; }
        .tutup-kasir .tk-ledger tbody,
        .tutup-kasir .tk-ledger tfoot { display: block; }
        .tutup-kasir .tk-ledger tr {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            border-bottom: 1px solid var(--tk-rule);
        }
        .tutup-kasir .tk-ledger tbody th,
        .tutup-kasir .tk-ledger tfoot th {
            grid-column: 1 / -1;
            padding: 0.65rem 1rem 0.2rem;
            border: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }
        .tutup-kasir .tk-ledger tbody th .tk-ledger__description,
        .tutup-kasir .tk-ledger tfoot th .tk-ledger__description { display: none !important; }
        .tutup-kasir .tk-ledger tbody th .tk-ledger__mobile-total,
        .tutup-kasir .tk-ledger tfoot th .tk-ledger__mobile-total {
            display: block !important;
            flex: 0 0 auto;
            margin: 0;
            color: var(--tk-ink);
            font-size: 0.72rem;
            font-weight: 600;
            text-align: right;
            white-space: nowrap;
        }
        .tutup-kasir .tk-ledger tbody td,
        .tutup-kasir .tk-ledger tfoot td {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            min-width: 0;
            padding: 0.3rem 1rem 0.75rem;
            border: 0;
            text-align: left !important;
            white-space: normal;
            overflow-wrap: anywhere;
        }
        .tutup-kasir .tk-ledger tbody td::before,
        .tutup-kasir .tk-ledger tfoot td::before {
            content: attr(data-label);
            display: block;
            margin-bottom: 0.22rem;
            color: var(--tk-muted);
            font-size: 0.66rem;
            font-weight: 700;
            letter-spacing: 0.035em;
            text-transform: uppercase;
        }
        .tutup-kasir .tk-ledger td[data-label="Total"] {
            display: none !important;
        }
        .tutup-kasir .tk-ledger tfoot tr { border: 0; border-radius: 0 0 var(--tk-radius) var(--tk-radius); }
        .tutup-kasir .tk-ledger tfoot th,
        .tutup-kasir .tk-ledger tfoot td { background: var(--bs-secondary-bg, #f6f7f9); }
        .tutup-kasir .tk-ledger tfoot td { color: var(--tk-kas); font-size: 0.92rem; }
        .tutup-kasir .tk-ledger__note { max-width: 100%; }
        .tutup-kasir .tk-reconcile { padding: 1rem; }
        .tutup-kasir .tk-reconcile__body { grid-template-columns: 1fr; }
        .tutup-kasir .tk-footer { margin-left: -1rem; margin-right: -1rem; padding: 0.75rem 1rem; }
        .tutup-kasir .tk-footer__meta { display: none; }
        .tutup-kasir .tk-footer .tk-btn { flex: 1; padding-left: 0.75rem; padding-right: 0.75rem; }
    }
    @media (max-width: 380px) {
        .tutup-kasir .tk-footer { flex-wrap: wrap; }
        .tutup-kasir .tk-footer .tk-btn { min-width: 100%; }
    }

    @media (prefers-reduced-motion: reduce) {
        .tutup-kasir .tk-details summary::after,
        .tutup-kasir .tk-btn { transition: none !important; }
        .tutup-kasir .tk-footer,
        .tutup-kasir .tk-section,
        .tutup-kasir .tk-card { transition: none; }
    }

    /* ===== 12. Reduced motion ===== */
    @media (prefers-reduced-motion: reduce) {
        .tutup-kasir .tk-details summary::after,
        .tutup-kasir .tk-btn {
            transition: none !important;
        }
        .tutup-kasir .tk-footer,
        .tutup-kasir .tk-section,
        .tutup-kasir .tk-card {
            transition: none;
        }
    }

    /* ===== 13. Focus visible ===== */
    .tutup-kasir .tk-input-group .form-control:focus-visible,
    .tutup-kasir .tk-btn:focus-visible,
    .tutup-kasir .tk-details summary:focus-visible {
        outline: 2px solid var(--tk-kas);
        outline-offset: 2px;
    }
    .tutup-kasir .tk-input-group .form-control:focus:not(:focus-visible),
    .tutup-kasir .tk-btn:focus:not(:focus-visible),
    .tutup-kasir .tk-details summary:focus:not(:focus-visible) {
        outline: none;
    }

    /* ===== 14. Scrollbar & selection ===== */
    .tutup-kasir ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    .tutup-kasir ::-webkit-scrollbar-track {
        background: transparent;
    }
    .tutup-kasir ::-webkit-scrollbar-thumb {
        background: color-mix(in srgb, var(--tk-muted) 30%, transparent);
        border-radius: 4px;
    }
    .tutup-kasir ::-webkit-scrollbar-thumb:hover {
        background: color-mix(in srgb, var(--tk-muted) 45%, transparent);
    }
    .tutup-kasir ::selection {
        background: color-mix(in srgb, var(--tk-kas) 18%, transparent);
        color: var(--tk-ink);
    }
    .tutup-kasir .tk-input-group .form-control { caret-color: var(--tk-kas); }
    .tutup-kasir .tk-details summary:focus-visible,
    .tutup-kasir .tk-btn:focus-visible,
    .tutup-kasir .tk-input-group .form-control:focus-visible {
        outline-color: var(--tk-kas);
        outline-offset: 2px;
    }
</style>
