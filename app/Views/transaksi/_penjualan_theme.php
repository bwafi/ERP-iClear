<?php

/**
 * Design system halaman Penjualan (/penjualan).
 *
 * Semua selector berada di bawah `.penjualan-page` dan `.pj-modal` supaya tidak
 * bocor ke modul lain. Token warna diturunkan dari tema aplikasi; satu aksen
 * (biru) hanya untuk fokus dan aksi utama. Tanpa bayangan dan tanpa gradien:
 * pemisah memakai hairline, kedalaman datang dari ruang dan tipografi.
 */
?>

<style>
    /* ===== Token ===== */
    .penjualan-page,
    .pj-modal,
    .pj-picker-modal {
        --pj-surface: var(--bs-card-bg, #fff);
        --pj-subtle: #f7f9fb;
        --pj-rule: var(--bs-border-color, #e6ecf2);
        --pj-rule-strong: #d3dce5;
        --pj-hover: rgba(0, 119, 196, .045);
        --pj-ink: #495564;
        --pj-strong: #101828;
        --pj-muted: #5b6672;
        --pj-accent: #0077c4;
        --pj-accent-ink: #0067a8;
        --pj-accent-soft: rgba(0, 119, 196, .08);
        --pj-accent-contrast: #fff;
        --pj-danger: #b02a37;
        --pj-danger-soft: rgba(176, 42, 55, .08);
        --pj-warn: #8a5a00;
        --pj-warn-soft: rgba(138, 90, 0, .09);
        --pj-ok: #0f7a46;
        --pj-ok-soft: rgba(15, 122, 70, .08);
        --pj-radius: 16px;
        --pj-radius-sm: 10px;
    }

    html[data-bs-theme="dark"] .penjualan-page,
    html[data-bs-theme="dark"] .pj-modal,
    html[data-bs-theme="dark"] .pj-picker-modal {
        --pj-surface: var(--bs-card-bg, #2a3547);
        --pj-subtle: rgba(255, 255, 255, .035);
        --pj-rule: var(--bs-border-color, rgba(255, 255, 255, .12));
        --pj-rule-strong: rgba(255, 255, 255, .2);
        --pj-hover: rgba(255, 255, 255, .04);
        --pj-ink: var(--bs-body-color, #cfd8e3);
        --pj-strong: #f1f5f9;
        --pj-muted: #8fa0b8;
        --pj-accent: #8ab4ff;
        --pj-accent-ink: #8ab4ff;
        --pj-accent-soft: rgba(138, 180, 255, .14);
        --pj-accent-contrast: #0b1622;
        --pj-danger: #ff9aa2;
        --pj-danger-soft: rgba(255, 154, 162, .1);
        --pj-warn: #f0c36a;
        --pj-warn-soft: rgba(240, 195, 106, .12);
        --pj-ok: #6fdd9d;
        --pj-ok-soft: rgba(111, 221, 157, .1);
    }

    .penjualan-page {
        width: 100%;
        color: var(--pj-ink);
        font-size: .875rem;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .penjualan-page ::selection {
        background: var(--pj-accent-soft);
        color: var(--pj-strong);
    }

    .penjualan-page :focus-visible,
    .pj-modal :focus-visible,
    .pj-picker-modal :focus-visible:not(.service-sp-item__input) {
        outline: 2px solid var(--pj-accent);
        outline-offset: 2px;
    }

    /* ===== Kepala halaman ===== */
    .pj-head {
        background: var(--pj-surface);
        border: 1px solid var(--pj-rule);
        border-radius: var(--pj-radius);
        padding: 20px 24px;
    }

    .pj-crumb {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        list-style: none;
        margin: 0 0 12px;
        padding: 0;
        font-size: .75rem;
        color: var(--pj-muted);
    }

    .pj-crumb li + li::before {
        content: '/';
        margin-right: 8px;
        color: var(--pj-rule-strong);
    }

    .pj-crumb a {
        color: var(--pj-muted);
        text-decoration: none;
    }

    .pj-crumb a:hover {
        color: var(--pj-accent-ink);
    }

    .pj-head__row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .pj-head h1 {
        margin: 0;
        font-size: 1.375rem;
        font-weight: 600;
        letter-spacing: -.03em;
        color: var(--pj-strong);
        line-height: 1.2;
    }

    .pj-sub {
        margin: 4px 0 0;
        max-width: 64ch;
        color: var(--pj-muted);
        font-size: .82rem;
    }

    .pj-unit {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        height: 32px;
        padding: 0 12px;
        border: 1px solid var(--pj-rule);
        border-radius: 999px;
        background: var(--pj-subtle);
        color: var(--pj-ink);
        font-size: .75rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .pj-unit iconify-icon {
        color: var(--pj-muted);
    }

    /* ===== Lembar ===== */
    .pj-sheet {
        background: var(--pj-surface);
        border: 1px solid var(--pj-rule);
        border-radius: var(--pj-radius);
        overflow: clip;
        animation: pj-settle .28s ease;
    }

    .pj-section {
        padding: 22px 24px;
        border-top: 1px solid var(--pj-rule);
    }

    .pj-section:first-of-type {
        border-top: 0;
    }

    .pj-section__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .pj-section__title {
        margin: 0;
        font-size: .95rem;
        font-weight: 600;
        letter-spacing: -.01em;
        color: var(--pj-strong);
    }

    .pj-section__hint {
        margin: 3px 0 0;
        max-width: 66ch;
        color: var(--pj-muted);
        font-size: .78rem;
    }

    .pj-grid {
        display: grid;
        grid-template-columns: repeat(var(--pj-cols, 3), minmax(0, 1fr));
        gap: 16px;
    }

    .pj-grid--2 {
        --pj-cols: 2;
    }

    /* ===== Field & kontrol ===== */
    .pj-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
    }

    .pj-field > label {
        font-size: .625rem;
        font-weight: 600;
        letter-spacing: .09em;
        text-transform: uppercase;
        color: var(--pj-muted);
    }

    .pj-input,
    .pj-select,
    .pj-field .form-control,
    .pj-field .form-select {
        width: 100%;
        height: 40px;
        padding: 0 12px;
        border: 1px solid var(--pj-rule-strong);
        border-radius: var(--pj-radius-sm);
        background: var(--pj-surface);
        color: var(--pj-strong);
        font-size: .84rem;
        box-shadow: none;
        transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }

    .pj-input::placeholder {
        color: var(--pj-muted);
    }

    .pj-input:hover,
    .pj-select:hover,
    .pj-field .form-control:hover,
    .pj-field .form-select:hover {
        border-color: var(--pj-accent);
    }

    .pj-input:focus,
    .pj-select:focus,
    .pj-field .form-control:focus,
    .pj-field .form-select:focus {
        border-color: var(--pj-accent);
        box-shadow: 0 0 0 3px var(--pj-accent-soft);
        outline: 0;
        background: var(--pj-surface);
        color: var(--pj-strong);
    }

    .pj-input[readonly],
    .pj-input:disabled,
    .pj-select:disabled {
        background: var(--pj-subtle);
        color: var(--pj-ink);
        cursor: default;
    }

    .pj-input.is-invalid,
    .pj-select.is-invalid {
        border-color: var(--pj-danger);
    }

    .pj-hint {
        margin: 0;
        font-size: .72rem;
        color: var(--pj-muted);
    }

    .pj-error {
        margin: 0;
        font-size: .72rem;
        color: var(--pj-danger);
    }

    .pj-error[hidden],
    .pj-hint[hidden] {
        display: none;
    }

    /* ===== Pelanggan ===== */
    .pj-customer {
        display: flex;
        gap: 8px;
        align-items: stretch;
    }

    .pj-customer .pj-input {
        flex: 1;
        min-width: 0;
    }

    .pj-customer__meta {
        display: flex;
        align-items: center;
        gap: 6px;
        min-height: 18px;
        font-size: .72rem;
        color: var(--pj-muted);
    }

    .pj-customer__meta iconify-icon {
        flex: none;
        color: var(--pj-rule-strong);
    }

    /* ===== Tombol ===== */
    .pj-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        height: 40px;
        padding: 0 14px;
        border: 1px solid transparent;
        border-radius: var(--pj-radius-sm);
        font-size: .82rem;
        font-weight: 600;
        line-height: 1;
        white-space: nowrap;
        cursor: pointer;
        text-decoration: none;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }

    .pj-btn:disabled {
        opacity: .45;
        cursor: not-allowed;
    }

    .pj-btn--primary {
        background: var(--pj-accent);
        border-color: var(--pj-accent);
        color: var(--pj-accent-contrast);
    }

    .pj-btn--primary:hover:not(:disabled) {
        background: var(--pj-accent-ink);
        border-color: var(--pj-accent-ink);
        color: var(--pj-accent-contrast);
    }

    .pj-btn--outline {
        background: var(--pj-surface);
        border-color: var(--pj-rule-strong);
        color: var(--pj-strong);
    }

    .pj-btn--outline:hover:not(:disabled) {
        background: var(--pj-hover);
        border-color: var(--pj-accent);
        color: var(--pj-accent-ink);
    }

    .pj-btn--ghost {
        background: transparent;
        border-color: transparent;
        color: var(--pj-muted);
    }

    .pj-btn--ghost:hover:not(:disabled) {
        background: var(--pj-hover);
        color: var(--pj-strong);
    }

    .pj-btn--danger {
        background: transparent;
        border-color: transparent;
        color: var(--pj-danger);
    }

    .pj-btn--danger:hover:not(:disabled) {
        background: var(--pj-danger-soft);
        color: var(--pj-danger);
    }

    .pj-btn--sm {
        height: 32px;
        padding: 0 10px;
        font-size: .75rem;
    }

    .pj-row-del {
        display: inline-grid;
        place-items: center;
        width: 34px;
        height: 34px;
        border: 1px solid transparent;
        border-radius: 9px;
        background: transparent;
        color: var(--pj-muted);
        cursor: pointer;
        transition: background-color .15s ease, color .15s ease, border-color .15s ease;
    }

    .pj-row-del:hover {
        background: var(--pj-danger-soft);
        border-color: transparent;
        color: var(--pj-danger);
    }

    /* ===== Toolbar produk ===== */
    .pj-toolbar {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .pj-count {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 30px;
        padding: 0 10px;
        border: 1px solid var(--pj-rule);
        border-radius: 999px;
        background: var(--pj-subtle);
        color: var(--pj-muted);
        font-size: .72rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    /* ===== Tabel produk ===== */
    .pj-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .pj-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
    }

    .pj-table th {
        padding: 0 10px 10px;
        border-bottom: 1px solid var(--pj-rule);
        text-align: left;
        font-size: .625rem;
        font-weight: 600;
        letter-spacing: .09em;
        text-transform: uppercase;
        color: var(--pj-muted);
        white-space: nowrap;
    }

    .pj-table th.pj-num {
        text-align: right;
    }

    .pj-table td {
        padding: 12px 10px;
        border-bottom: 1px solid var(--pj-rule);
        vertical-align: middle;
    }

    .pj-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .pj-table tbody tr:hover td {
        background: var(--pj-hover);
    }

    .pj-table .pj-input {
        height: 36px;
        font-size: .82rem;
    }

    .pj-table .harga-input,
    .pj-table .diskon-input {
        min-width: 108px;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .pj-table .jumlah-input {
        min-width: 68px;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .pj-num {
        font-variant-numeric: tabular-nums;
    }

    .pj-row__name {
        display: block;
        font-weight: 600;
        color: var(--pj-strong);
    }

    .pj-row__meta {
        display: block;
        margin-top: 2px;
        font-size: .72rem;
        color: var(--pj-muted);
    }

    .pj-table .pj-check {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: .75rem;
        color: var(--pj-ink);
        cursor: pointer;
        white-space: nowrap;
    }

    .penjualan-page input[type="checkbox"],
    .pj-modal input[type="checkbox"] {
        accent-color: var(--pj-accent);
        width: 16px;
        height: 16px;
        cursor: pointer;
    }

    .penjualan-page input[type="checkbox"]:disabled,
    .pj-modal input[type="checkbox"]:disabled {
        cursor: not-allowed;
        opacity: .45;
    }

    .pj-subtotal {
        display: block;
        text-align: right;
        font-weight: 600;
        color: var(--pj-strong);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .pj-empty td {
        padding: 36px 16px;
        text-align: center;
        color: var(--pj-muted);
        font-size: .8rem;
    }

    .pj-table tbody tr[hidden] {
        display: none !important;
    }

    .pj-empty:hover td {
        background: transparent !important;
    }

    .pj-empty iconify-icon {
        display: block !important;
        margin: 0 auto 8px !important;
        color: var(--pj-rule-strong);
    }

    .pj-empty__title {
        display: block;
        margin-bottom: 2px;
        color: var(--pj-strong);
        font-weight: 600;
    }

    .pj-empty .pj-btn {
        margin-top: 10px;
    }

    /* ===== Metode pembayaran ===== */
    .pj-methods {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 16px;
    }

    .pj-method {
        position: relative;
        display: block;
        margin: 0;
        cursor: pointer;
    }

    .pj-method input {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }

    .pj-method__box {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        height: 100%;
        padding: 14px;
        border: 1px solid var(--pj-rule-strong);
        border-radius: 12px;
        background: var(--pj-surface);
        transition: border-color .15s ease, background-color .15s ease;
    }

    .pj-method:hover .pj-method__box {
        border-color: var(--pj-accent);
    }

    .pj-method__icon {
        display: grid;
        place-items: center;
        flex: none;
        width: 34px;
        height: 34px;
        border-radius: 9px;
        background: var(--pj-subtle);
        color: var(--pj-muted);
        transition: background-color .15s ease, color .15s ease;
    }

    .pj-method__label {
        display: block;
        font-size: .84rem;
        font-weight: 600;
        color: var(--pj-strong);
        line-height: 1.3;
    }

    .pj-method__hint {
        display: block;
        margin-top: 2px;
        font-size: .72rem;
        color: var(--pj-muted);
    }

    .pj-method input:checked + .pj-method__box {
        border-color: var(--pj-accent);
        background: var(--pj-accent-soft);
    }

    .pj-method input:checked + .pj-method__box .pj-method__icon {
        background: var(--pj-accent);
        color: var(--pj-accent-contrast);
    }

    .pj-method input:focus-visible + .pj-method__box {
        box-shadow: 0 0 0 3px var(--pj-accent-soft);
        border-color: var(--pj-accent);
    }

    /* ===== Zona pembayaran ===== */
    .pj-zone {
        padding: 16px;
        border: 1px solid var(--pj-rule);
        border-radius: 12px;
        background: var(--pj-subtle);
    }

    .pj-zone + .pj-zone {
        margin-top: 12px;
    }

    .pj-zone[hidden] {
        display: none;
    }

    .pj-zone__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 10px;
    }

    .pj-zone__title {
        margin: 0;
        font-size: .625rem;
        font-weight: 600;
        letter-spacing: .09em;
        text-transform: uppercase;
        color: var(--pj-muted);
    }

    .pj-cash {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .pj-cash .pj-input {
        max-width: 260px;
        height: 48px;
        padding: 0 14px;
        font-size: 1.15rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    .pj-quick {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .pj-quick .pj-btn {
        height: 32px;
        padding: 0 10px;
        font-size: .72rem;
    }

    .pj-bank-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .pj-bank-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 170px 34px;
        gap: 8px;
        align-items: center;
    }

    .pj-bank-row .pj-input,
    .pj-bank-row .pj-select {
        height: 38px;
    }

    .pj-bank-row .bank-amount {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .pj-bank-note {
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 10px 0 0;
        font-size: .75rem;
        color: var(--pj-warn);
        background: var(--pj-warn-soft);
        border-radius: 8px;
        padding: 8px 10px;
    }

    /* ===== Status pembayaran ===== */
    .pj-pay-status {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin-top: 14px;
        padding: 11px 13px;
        border: 1px solid var(--pj-rule);
        border-radius: var(--pj-radius-sm);
        background: var(--pj-surface);
        color: var(--pj-muted);
        font-size: .78rem;
        line-height: 1.45;
    }

    .pj-pay-status iconify-icon {
        flex: none;
        margin-top: 1px;
    }

    .pj-pay-status.is-ok {
        border-color: transparent;
        background: var(--pj-ok-soft);
        color: var(--pj-ok);
    }

    .pj-pay-status.is-over {
        border-color: transparent;
        background: var(--pj-ok-soft);
        color: var(--pj-ok);
    }

    .pj-pay-status.is-due {
        border-color: transparent;
        background: var(--pj-warn-soft);
        color: var(--pj-warn);
    }

    .pj-pay-status.is-empty {
        background: var(--pj-subtle);
    }

    /* ===== Dock ===== */
    .pj-dock {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
        padding: 20px 24px;
        border-top: 1px solid var(--pj-rule-strong);
        background: var(--pj-subtle);
    }

    .pj-dock__figures {
        display: grid;
        grid-template-columns: repeat(6, minmax(90px, 1fr));
        gap: 16px;
        flex: 1;
        min-width: 0;
    }

    .pj-figure {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
        margin: 0;
        cursor: default;
    }

    label.pj-figure {
        cursor: text;
    }

    .pj-figure > span:first-child {
        font-size: .625rem;
        font-weight: 600;
        letter-spacing: .09em;
        text-transform: uppercase;
        color: var(--pj-muted);
        white-space: nowrap;
    }

    .pj-figure input,
    .pj-figure__value {
        width: 100%;
        height: auto;
        padding: 0;
        border: 0;
        border-radius: 0;
        background: transparent;
        color: var(--pj-strong);
        font-size: .95rem;
        font-weight: 600;
        line-height: 1.35;
        font-variant-numeric: tabular-nums;
        box-shadow: none;
        white-space: nowrap;
    }

    .pj-figure input:focus {
        outline: 0;
        box-shadow: none;
        color: var(--pj-strong);
    }

    .pj-figure--total input {
        font-size: 1.3rem;
        letter-spacing: -.02em;
    }

    .pj-figure--total > span:first-child {
        color: var(--pj-strong);
    }

    .pj-figure__value.is-due,
    .pj-figure--debt input {
        color: var(--pj-danger);
    }

    .pj-figure__value.is-ok {
        color: var(--pj-ok);
    }

    .pj-dock__actions {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 6px;
        flex: none;
    }

    .pj-dock__buttons {
        display: flex;
        gap: 8px;
    }

    .pj-dock__buttons .pj-btn {
        height: 44px;
        padding: 0 18px;
    }

    .pj-save-hint {
        margin: 0;
        text-align: right;
        font-size: .68rem;
        color: var(--pj-muted);
    }

    .pj-form-error {
        margin: 0;
        padding: 10px 12px;
        border-radius: var(--pj-radius-sm);
        background: var(--pj-danger-soft);
        color: var(--pj-danger);
        font-size: .78rem;
    }

    .pj-form-error[hidden] {
        display: none;
    }

    /* ===== Modal ===== */
    .pj-modal .modal-content {
        border: 1px solid var(--pj-rule);
        border-radius: var(--pj-radius);
        background: var(--pj-surface);
        color: var(--pj-ink);
    }

    .pj-modal .modal-header,
    .pj-modal .modal-footer {
        padding: 16px 20px;
        border-color: var(--pj-rule);
    }

    .pj-modal .modal-body {
        padding: 16px 20px;
    }

    .pj-modal .modal-title {
        color: var(--pj-strong);
        font-size: 1rem;
        font-weight: 600;
    }

    .pj-modal .form-label,
    .pj-modal label {
        color: var(--pj-muted);
        font-size: .72rem;
        font-weight: 600;
    }

    .pj-modal .form-control,
    .pj-modal .form-select {
        height: 40px;
        border: 1px solid var(--pj-rule-strong);
        border-radius: var(--pj-radius-sm);
        background: var(--pj-surface);
        color: var(--pj-strong);
        font-size: .84rem;
    }

    .pj-modal .form-control:focus,
    .pj-modal .form-select:focus {
        border-color: var(--pj-accent);
        box-shadow: 0 0 0 3px var(--pj-accent-soft);
    }

    /* Select2 di dalam modal: aturan global template memakai
       `top: 24px; right: 20px; width: auto` untuk panah sehingga chevron
       jatuh di luar box 40px. Panah bawaan disembunyikan dan chevron
       digambar ulang agar selalu di tengah kanan box, termasuk saat
       dropdown terbuka. */
    .pj-modal .select2-selection--single {
        position: relative;
    }

    .pj-modal .select2-selection--single .select2-selection__arrow {
        display: none;
    }

    .pj-modal .select2-selection--single::after {
        content: '';
        position: absolute;
        top: 50%;
        right: 14px;
        width: 7px;
        height: 7px;
        border-right: 2px solid var(--bs-body-color);
        border-bottom: 2px solid var(--bs-body-color);
        transform: translateY(-65%) rotate(45deg);
        pointer-events: none;
    }

    .pj-modal .select2-container--open .select2-selection--single::after {
        transform: translateY(-20%) rotate(-135deg);
    }

    .pj-modal .select2-selection--single .select2-selection__rendered {
        padding-right: 34px;
    }

    .pj-modal-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 16px;
    }

    .pj-modal-note {
        margin: 0 0 12px;
        font-size: .75rem;
        color: var(--pj-muted);
    }

    .pj-modal-error,
    .pj-modal-success {
        margin: 12px 0 0;
        font-size: .75rem;
    }

    .pj-modal-error {
        color: var(--pj-danger);
    }

    .pj-modal-success {
        color: var(--pj-ok);
    }

    .pj-modal-error[hidden],
    .pj-modal-success[hidden] {
        display: none;
    }

    /* ===== Picker ===== */
    /* Daftar, kartu, tick, chip meta, dan pill stok memakai komponen picker
       Sparepart (partials/sparepart_picker_style) supaya tampil identik
       dengan halaman terima service. Di sini hanya kulit modal dan tab yang
       khas Penjualan. */
    .pj-picker-modal .modal-content {
        border: 1px solid var(--pj-rule);
        border-radius: var(--pj-radius);
        background: var(--pj-surface);
    }

    .pj-picker-tabs {
        display: flex;
        align-items: center;
        gap: 4px;
        margin: 0 0 10px;
        padding: 0;
        border-bottom: 1px solid var(--pj-rule);
        list-style: none;
    }

    .pj-picker-tabs .nav-link {
        padding: 8px 12px;
        border: 0;
        border-bottom: 2px solid transparent;
        border-radius: 0;
        background: transparent;
        color: var(--pj-muted);
        font-size: .82rem;
        font-weight: 600;
    }

    .pj-picker-tabs .nav-link:hover {
        color: var(--pj-strong);
        border-bottom-color: var(--pj-rule-strong);
    }

    .pj-picker-tabs .nav-link.active {
        color: var(--pj-accent-ink);
        border-bottom-color: var(--pj-accent);
        background: transparent;
    }

    .pj-picker-modal .service-sp-item.is-hidden,
    .pj-picker-modal .service-sp-state[hidden] {
        display: none;
    }

    .pj-picker-modal .service-sp-item.is-disabled {
        cursor: not-allowed;
    }

    .pj-picker-modal .service-sp-item.is-disabled .service-sp-item__box {
        opacity: .55;
    }

    .pj-picker-modal .service-sp-item.is-disabled:hover .service-sp-item__box {
        border-color: var(--bs-border-color);
        background: var(--bs-body-bg);
    }

    .pj-picker-modal .service-sp-item.is-disabled .service-sp-item__input:checked+.service-sp-item__box {
        border-color: var(--bs-border-color);
        background: var(--bs-body-bg);
    }

    .pj-picker-modal .service-sp-side .btn {
        font-size: .72rem;
        padding: .15rem .5rem;
    }

    .pj-bundle-detail {
        width: 100%;
        border-collapse: collapse;
    }

    .pj-bundle-detail th {
        padding: 0 8px 8px;
        border-bottom: 1px solid var(--pj-rule);
        text-align: left;
        font-size: .625rem;
        font-weight: 600;
        letter-spacing: .09em;
        text-transform: uppercase;
        color: var(--pj-muted);
    }

    .pj-bundle-detail td {
        padding: 9px 8px;
        border-bottom: 1px solid var(--pj-rule);
        font-size: .82rem;
        color: var(--pj-ink);
    }

    /* ===== Animasi ===== */
    @keyframes pj-settle {
        from {
            opacity: 0;
            transform: translateY(4px);
        }

        to {
            opacity: 1;
            transform: none;
        }
    }

    /* ===== Responsif ===== */
    @media (max-width: 1199.98px) {
        .pj-grid {
            --pj-cols: 2;
        }

        .pj-dock__figures {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 991.98px) {
        .pj-grid {
            --pj-cols: 1;
        }

        .pj-bank-row {
            grid-template-columns: minmax(0, 1fr) 34px;
        }

        .pj-bank-row .bank-select {
            grid-column: 1 / -1;
        }

        .pj-bank-row .bank-amount {
            grid-column: 1;
        }

        .pj-bank-row .hapus-bank {
            grid-column: 2;
        }
    }

    @media (max-width: 767.98px) {
        .pj-head,
        .pj-section {
            padding-left: 18px;
            padding-right: 18px;
        }

        .pj-dock {
            padding-left: 18px;
            padding-right: 18px;
            align-items: stretch;
        }

        .pj-dock__figures {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pj-dock__actions {
            width: 100%;
        }

        .pj-dock__buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .pj-save-hint {
            text-align: left;
        }

        .pj-table thead {
            display: none;
        }

        .pj-table,
        .pj-table tbody,
        .pj-table tr,
        .pj-table td {
            display: block;
            width: 100%;
        }

        .pj-table tbody tr {
            margin-bottom: 10px;
            padding: 10px 12px;
            border: 1px solid var(--pj-rule);
            border-radius: 12px;
        }

        .pj-table tbody tr:hover td {
            background: transparent;
        }

        .pj-table td {
            display: grid;
            grid-template-columns: 88px minmax(0, 1fr);
            gap: 10px;
            align-items: center;
            padding: 6px 0;
            border: 0;
            text-align: left;
        }

        .pj-table td::before {
            content: attr(data-label);
            color: var(--pj-muted);
            font-size: .625rem;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .pj-table td[data-label=""]::before {
            content: none;
        }

        .pj-table .pj-input,
        .pj-table .harga-input,
        .pj-table .diskon-input,
        .pj-table .jumlah-input {
            width: 100%;
            min-width: 0;
        }

        .pj-subtotal {
            text-align: left;
        }

        .pj-empty {
            border: 0;
            padding: 0;
        }

        .pj-empty td {
            display: block;
            padding: 28px 12px;
        }

        .pj-empty td::before {
            content: none;
        }

        .pj-methods {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .pj-pick-row {
            flex-wrap: wrap;
        }

        .pj-pick-row__side {
            flex-basis: 100%;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding-left: calc(22px + 12px);
        }
    }

    @media (max-width: 575.98px) {
        .pj-head {
            padding: 16px 16px;
        }

        .pj-section {
            padding: 18px 16px;
        }

        .pj-head h1 {
            font-size: 1.2rem;
        }

        .pj-customer {
            flex-direction: column;
        }

        .pj-methods {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
        }

        .pj-method__box {
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 10px 6px;
            text-align: center;
        }

        .pj-method__icon {
            width: 28px;
            height: 28px;
        }

        .pj-method__hint {
            display: none;
        }

        .pj-cash .pj-input {
            max-width: none;
        }

        .pj-dock__buttons {
            grid-template-columns: 1fr;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .pj-sheet {
            animation: none;
        }

        .penjualan-page *,
        .pj-modal * {
            transition: none !important;
            animation: none !important;
        }
    }
</style>
