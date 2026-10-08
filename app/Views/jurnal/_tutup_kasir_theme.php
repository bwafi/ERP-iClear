<?php

/**
 * Design system halaman Tutup Kasir (/tutupkasir).
 *
 * Scoped di bawah `.tutup-kasir` supaya tidak bocor ke halaman lain.
 * Semua warna dari token template (CSS var resolved), ikut mode gelap/terang.
 */

?>
<style>
    /* ===== 1. Token ===== */
    .tutup-kasir {
        --tk-surface: var(--bs-card-bg, var(--bs-body-bg, #fff));
        --tk-canvas: var(--bs-body-bg, #f0f5f9);
        --tk-rule: var(--bs-border-color, #e6ecf1);
        /* Netral sekunder pekat: kontras >=4.5:1 di kedua mode */
        --tk-muted: #5b6672;
        --tk-ink: #626d7b;
        /* Semantik kas/bank (pakai warna template) */
        --tk-kas: var(--bs-primary, #2553b9);
        --tk-bank: var(--bs-warning, #f59e0b);
        --tk-transfer: var(--bs-info, #0dcaf0);
        --tk-expense: var(--bs-danger, #dc3545);
        --tk-success: var(--bs-success, #198754);
        /* Elevasi standar: card template */
        --tk-shadow: 0 2px 6px rgba(37, 83, 185, 0.1);
        --tk-radius: 1.125rem;
    }

    /* ===== 2. Layout helpers ===== */
    .tutup-kasir .tk-page {
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
        box-shadow: var(--tk-shadow);
        border: 1px solid var(--tk-rule);
    }
    .tutup-kasir .tk-section--flat {
        background: transparent;
        box-shadow: none;
        border: none;
    }
    .tutup-kasir .tk-card {
        background: var(--tk-surface);
        border-radius: var(--tk-radius);
        box-shadow: var(--tk-shadow);
        border: 1px solid var(--tk-rule);
    }
    .tutup-kasir .tk-card--flat {
        background: transparent;
        box-shadow: none;
        border: none;
    }
    .tutup-kasir .tk-card--subtle {
        background: var(--bs-secondary-bg, #f8f9fa);
        border-color: var(--tk-rule);
    }

    /* ===== 3. Hero input ===== */
    .tutup-kasir .tk-hero {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    @media (min-width: 992px) {
        .tutup-kasir .tk-hero {
            grid-template-columns: 1fr 1fr;
            align-items: start;
        }
    }
    .tutup-kasir .tk-hero__balance {
        padding: 1.5rem;
    }
    .tutup-kasir .tk-hero__input {
        padding: 1.5rem;
        background: var(--bs-secondary-bg, #f8f9fa);
        border-radius: var(--tk-radius);
        border: 1px solid var(--tk-rule);
    }
    .tutup-kasir .tk-balance__amount {
        font-size: clamp(2rem, 3vw, 2.5rem);
        font-weight: 700;
        color: var(--bs-body-color);
        font-variant-numeric: tabular-nums;
        line-height: 1.2;
    }
    .tutup-kasir .tk-balance__breakdown {
        color: var(--tk-muted);
        font-size: 0.875rem;
        margin-top: 0.5rem;
    }
    .tutup-kasir .tk-balance__breakdown strong {
        color: var(--tk-ink);
    }
    .tutup-kasir .tk-balance__note {
        font-size: 0.8125rem;
        color: var(--tk-muted);
        margin-top: 0.5rem;
    }

    .tutup-kasir .tk-input__label {
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }
    .tutup-kasir .tk-input__label iconify-icon {
        color: var(--tk-kas);
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
        color: var(--tk-ink);
    }
    .tutup-kasir .tk-input-group .form-control {
        border: 1px solid var(--tk-rule);
        border-left: none;
        border-radius: 0 var(--tk-radius) var(--tk-radius) 0;
        font-size: 1.25rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        padding: 0.75rem 1rem;
    }
    .tutup-kasir .tk-input-group .form-control:focus {
        box-shadow: 0 0 0 0.2rem rgba(37, 83, 185, 0.15);
        border-color: var(--tk-kas);
    }
    .tutup-kasir .tk-input__hint {
        font-size: 0.8125rem;
        color: var(--tk-muted);
        margin-top: 0.5rem;
    }

    /* ===== 4. Summary grid (board-style) ===== */
    .tutup-kasir .tk-summary {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
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
        background: var(--tk-surface);
        border: 1px solid var(--tk-rule);
        border-radius: var(--tk-radius);
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .tutup-kasir .tk-summary__item--income {
        border-top: 3px solid var(--tk-success);
    }
    .tutup-kasir .tk-summary__item--income-total {
        border-top: 3px solid var(--tk-kas);
    }
    .tutup-kasir .tk-summary__item--expense {
        border-top: 3px solid var(--tk-expense);
    }
    .tutup-kasir .tk-summary__item--expense-total {
        border-top: 3px solid var(--tk-expense);
        background: var(--bs-danger-subtle, #fef2f2);
    }
    .tutup-kasir .tk-summary__header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
    }
    .tutup-kasir .tk-summary__title {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--tk-muted);
        line-height: 1.3;
    }
    .tutup-kasir .tk-summary__icon {
        flex-shrink: 0;
        width: 2.5rem;
        height: 2.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.75rem;
        background: var(--bs-secondary-bg, #f0f5f9);
    }
    .tutup-kasir .tk-summary__item--income .tk-summary__icon { color: var(--tk-success); }
    .tutup-kasir .tk-summary__item--income-total .tk-summary__icon { color: var(--tk-kas); }
    .tutup-kasir .tk-summary__item--expense .tk-summary__icon { color: var(--tk-expense); }
    .tutup-kasir .tk-summary__item--expense-total .tk-summary__icon { color: var(--tk-expense); }
    .tutup-kasir .tk-summary__value {
        margin-top: 0.75rem;
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--bs-body-color);
        font-variant-numeric: tabular-nums;
        line-height: 1.2;
    }
    .tutup-kasir .tk-summary__item--income .tk-summary__value { color: var(--tk-success); }
    .tutup-kasir .tk-summary__item--income-total .tk-summary__value { color: var(--tk-kas); }
    .tutup-kasir .tk-summary__item--expense .tk-summary__value { color: var(--tk-expense); }
    .tutup-kasir .tk-summary__item--expense-total .tk-summary__value { color: var(--tk-expense); }

    /* ===== 5. Collapsible details ===== */
    .tutup-kasir .tk-details {
        margin-top: 1.5rem;
    }
    .tutup-kasir .tk-details summary {
        cursor: pointer;
        list-style: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        color: var(--tk-ink);
        padding: 0.75rem 1rem;
        background: var(--bs-secondary-bg, #f8f9fa);
        border: 1px solid var(--tk-rule);
        border-radius: var(--tk-radius);
    }
    .tutup-kasir .tk-details summary::-webkit-details-marker {
        display: none;
    }
    .tutup-kasir .tk-details summary::after {
        content: '';
        display: inline-block;
        width: 0.75rem;
        height: 0.75rem;
        margin-left: auto;
        background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%235b6672' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") center/0.75rem no-repeat;
        transition: transform 0.2s ease;
    }
    .tutup-kasir .tk-details[open] summary::after {
        transform: rotate(180deg);
    }
    .tutup-kasir .tk-details__content {
        padding: 1.25rem 1rem 0.5rem;
    }
    .tutup-kasir .tk-details__row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        padding: 0.5rem 0;
        border-bottom: 1px dashed var(--tk-rule);
    }
    .tutup-kasir .tk-details__row:last-child {
        border-bottom: none;
    }
    .tutup-kasir .tk-details__label {
        color: var(--tk-muted);
        font-size: 0.875rem;
    }
    .tutup-kasir .tk-details__value {
        font-weight: 600;
        color: var(--bs-body-color);
        font-variant-numeric: tabular-nums;
        margin-left: 1rem;
        white-space: nowrap;
    }

    /* ===== 6. Alerts ===== */
    .tutup-kasir .tk-alert {
        border: none;
        border-radius: var(--tk-radius);
        padding: 1rem 1.25rem;
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
    }
    .tutup-kasir .tk-alert iconify-icon {
        flex-shrink: 0;
        width: 1.25rem;
        height: 1.25rem;
        margin-top: 0.125rem;
    }
    .tutup-kasir .tk-alert--warning {
        background: var(--bs-warning-bg, #fff8e1);
        color: #856404;
    }
    .tutup-kasir .tk-alert--warning iconify-icon { color: var(--bs-warning, #f59e0b); }
    .tutup-kasir .tk-alert--danger {
        background: var(--bs-danger-bg, #fef2f2);
        color: #b91c1c;
    }
    .tutup-kasir .tk-alert--danger iconify-icon { color: var(--bs-danger, #dc3545); }
    .tutup-kasir .tk-alert__title { font-weight: 600; margin-bottom: 0.25rem; }
    .tutup-kasir .tk-alert__desc { font-size: 0.875rem; opacity: 0.9; }
    .tutup-kasir .tk-alert__link { font-weight: 600; text-decoration: underline; }

    /* ===== 7. Late warning ===== */
    .tutup-kasir .tk-late {
        background: var(--bs-warning-bg, #fff8e1);
        border: 1px solid var(--bs-warning-border, #f5e6b8);
        border-radius: var(--tk-radius);
        padding: 1rem 1.25rem;
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
    }
    .tutup-kasir .tk-late iconify-icon {
        color: var(--bs-warning, #f59e0b);
        flex-shrink: 0;
        margin-top: 0.125rem;
    }
    .tutup-kasir .tk-late__title { font-weight: 600; color: #856404; margin-bottom: 0.25rem; }
    .tutup-kasir .tk-late__desc { font-size: 0.875rem; color: #856404; opacity: 0.9; }

    /* ===== 8. Sticky footer ===== */
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
        justify-content: flex-end;
        gap: 0.75rem;
        box-shadow: 0 -4px 12px rgba(0,0,0,0.04);
    }
    .tutup-kasir .tk-btn {
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        border-radius: var(--tk-radius);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1rem;
    }
    .tutup-kasir .tk-btn--primary {
        background: var(--tk-kas);
        border-color: var(--tk-kas);
        color: #fff;
    }
    .tutup-kasir .tk-btn--primary:hover { filter: brightness(0.95); }
    .tutup-kasir .tk-btn--danger {
        background: var(--bs-danger, #dc3545);
        border-color: var(--bs-danger, #dc3545);
        color: #fff;
    }
    .tutup-kasir .tk-btn--danger:hover { filter: brightness(0.95); }
    .tutup-kasir .tk-btn--disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    .tutup-kasir .tk-btn iconify-icon {
        width: 1.25rem;
        height: 1.25rem;
    }

    /* ===== 9. Tabular nums utility ===== */
    .tutup-kasir .tk-tabular {
        font-variant-numeric: tabular-nums;
    }

    /* ===== 10. Reduced motion ===== */
    @media (prefers-reduced-motion: reduce) {
        .tutup-kasir .tk-details summary::after,
        .tutup-kasir .tk-btn {
            transition: none !important;
        }
    }

    /* ===== 11. Focus visible ===== */
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
</style>