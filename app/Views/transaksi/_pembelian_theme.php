<?php

/**
 * Design system halaman Pembelian (/pembelian).
 *
 * Semua selector berada di bawah `.pembelian-page` dan `.pb-modal` supaya
 * tidak bocor ke modul lain. Warna diturunkan dari token tema aplikasi; satu
 * aksen (biru primary) hanya untuk fokus dan aksi utama. Tanpa bayangan dan
 * tanpa gradien: pemisah memakai hairline, kedalaman datang dari ruang dan
 * tipografi.
 */
?>

<style>
    /* ===== Token ===== */
    .pembelian-page,
    .pb-modal {
        --pb-surface: var(--bs-card-bg, #fff);
        --pb-subtle: #f7f9fb;
        --pb-rule: var(--bs-border-color, #e6ecf2);
        --pb-rule-strong: #d3dce5;
        --pb-hover: rgba(0, 119, 196, .045);
        --pb-ink: #495564;
        --pb-strong: #101828;
        --pb-muted: #5b6672;
        --pb-accent: #0077c4;
        --pb-accent-ink: #0067a8;
        --pb-accent-soft: rgba(0, 119, 196, .08);
        --pb-accent-contrast: #fff;
        --pb-danger: #b02a37;
        --pb-danger-soft: rgba(176, 42, 55, .08);
        --pb-ok: #0f7a46;
        --pb-ok-soft: rgba(15, 122, 70, .08);
        --pb-radius: 16px;
        --pb-radius-sm: 10px;
    }

    html[data-bs-theme="dark"] .pembelian-page,
    html[data-bs-theme="dark"] .pb-modal {
        --pb-surface: var(--bs-card-bg, #2a3547);
        --pb-subtle: rgba(255, 255, 255, .035);
        --pb-rule: var(--bs-border-color, rgba(255, 255, 255, .12));
        --pb-rule-strong: rgba(255, 255, 255, .2);
        --pb-hover: rgba(255, 255, 255, .04);
        --pb-ink: var(--bs-body-color, #cfd8e3);
        --pb-strong: #f1f5f9;
        --pb-muted: #8fa0b8;
        --pb-accent: #8ab4ff;
        --pb-accent-ink: #8ab4ff;
        --pb-accent-soft: rgba(138, 180, 255, .14);
        --pb-accent-contrast: #0b1622;
        --pb-danger: #ff9aa2;
        --pb-danger-soft: rgba(255, 154, 162, .1);
        --pb-ok: #6fdd9d;
        --pb-ok-soft: rgba(111, 221, 157, .1);
    }

    .pembelian-page {
        width: 100%;
        color: var(--pb-ink);
        font-size: .875rem;
    }

    .pembelian-page ::selection {
        background: var(--pb-accent-soft);
        color: var(--pb-strong);
    }

    .pembelian-page :focus-visible,
    .pb-modal :focus-visible {
        outline: 2px solid var(--pb-accent);
        outline-offset: 2px;
    }

    .pembelian-page .pb-num {
        font-variant-numeric: tabular-nums;
    }

    /* ===== Kepala halaman ===== */
    .pb-head {
        padding: 22px 24px;
        border: 1px solid var(--pb-rule);
        border-radius: var(--pb-radius);
        background: var(--pb-surface);
        animation: pb-settle .24s cubic-bezier(.2, .7, .3, 1) both;
    }

    @keyframes pb-settle {
        from { opacity: 0; transform: translateY(4px); }
        to   { opacity: 1; transform: none; }
    }

    .pb-crumb {
        margin: 0 0 10px;
        padding: 0;
        list-style: none;
        display: flex;
        gap: 6px;
        font-size: .75rem;
        color: var(--pb-muted);
    }

    .pb-crumb a {
        color: inherit;
        text-decoration: none;
    }

    .pb-crumb a:hover {
        color: var(--pb-strong);
    }

    .pb-crumb li + li::before {
        content: "/";
        margin-right: 6px;
        color: var(--pb-rule-strong);
    }

    .pb-head__row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }

    .pb-head h1 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
        letter-spacing: -.03em;
        color: var(--pb-strong);
    }

    .pb-sub {
        max-width: 68ch;
        margin: 6px 0 0;
        font-size: .8125rem;
        line-height: 1.6;
        color: var(--pb-muted);
    }

    .pb-head__unit {
        flex: none;
        padding: 5px 10px;
        border: 1px solid var(--pb-rule);
        border-radius: 999px;
        font-size: .75rem;
        font-weight: 600;
        color: var(--pb-muted);
        letter-spacing: .01em;
        white-space: nowrap;
    }

    /* ===== Lembar ===== */
    .pb-sheet {
        margin-top: 16px;
        border: 1px solid var(--pb-rule);
        border-radius: var(--pb-radius);
        background: var(--pb-surface);
    }

    .pb-section {
        padding: 22px 24px 24px;
    }

    .pb-section + .pb-section {
        border-top: 1px solid var(--pb-rule);
    }

    .pb-section__head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .pb-section__head h2 {
        margin: 0;
        font-size: .95rem;
        font-weight: 650;
        letter-spacing: -.015em;
        color: var(--pb-strong);
    }

    .pb-section__hint {
        margin: 3px 0 0;
        font-size: .75rem;
        line-height: 1.5;
        color: var(--pb-muted);
    }

    /* ===== Grid & field ===== */
    .pb-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px 16px;
    }

    .pb-grid--3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .pb-grid--2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .pb-field {
        min-width: 0;
    }

    .pb-field--span2 {
        grid-column: span 2;
    }

    .pb-field label,
    .pb-field > .pb-label {
        display: block;
        margin-bottom: 6px;
        font-size: .75rem;
        font-weight: 600;
        color: var(--pb-muted);
        letter-spacing: .01em;
    }

    .pb-field > .form-control,
    .pb-field > .form-select,
    .pb-modal .form-control,
    .pb-modal .form-select {
        height: 40px;
        padding: 0 12px;
        border: 1px solid var(--pb-rule);
        border-radius: 9px;
        background: var(--pb-surface);
        color: var(--pb-strong);
        font-size: .8125rem;
    }

    .pb-field > textarea.form-control,
    .pb-modal textarea.form-control {
        height: auto;
        min-height: 84px;
        padding: 10px 12px;
        line-height: 1.5;
    }

    .pb-field > .form-control::placeholder,
    .pb-modal .form-control::placeholder {
        color: var(--pb-muted);
        opacity: .8;
    }

    .pb-field > .form-control:focus,
    .pb-field > .form-select:focus,
    .pb-modal .form-control:focus,
    .pb-modal .form-select:focus {
        border-color: var(--pb-accent);
        box-shadow: 0 0 0 3px var(--pb-accent-soft);
    }

    .pb-field > .form-control[readonly],
    .pb-field > .form-select:disabled,
    .pb-modal .form-control[readonly] {
        background: var(--pb-subtle);
        color: var(--pb-ink);
        opacity: 1;
    }

    .pb-field--inline {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* ===== Dropzone nota ===== */
    .pb-file {
        position: relative;
        display: flex;
        align-items: center;
        gap: 12px;
        min-height: 72px;
        padding: 14px;
        border: 1px dashed var(--pb-rule-strong);
        border-radius: 12px;
        background: var(--pb-subtle);
        cursor: pointer;
        transition: border-color .15s ease, background .15s ease;
    }

    .pb-file:hover,
    .pb-file:focus-within {
        border-color: var(--pb-accent);
        background: var(--pb-accent-soft);
    }

    .pb-file__input {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }

    .pb-file__icon {
        display: grid;
        place-items: center;
        width: 38px;
        height: 38px;
        flex: none;
        border: 1px solid var(--pb-rule);
        border-radius: 10px;
        background: var(--pb-surface);
        color: var(--pb-accent);
    }

    .pb-file__title {
        margin: 0;
        font-size: .8125rem;
        font-weight: 600;
        color: var(--pb-strong);
    }

    .pb-file__hint {
        margin: 2px 0 0;
        font-size: .75rem;
        color: var(--pb-muted);
    }

    .pb-file__preview {
        display: none;
        max-width: 100%;
        max-height: 160px;
        margin-top: 10px;
        border-radius: 10px;
        object-fit: contain;
    }

    /* ===== Tombol ===== */
    .pb-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        height: 40px;
        padding: 0 16px;
        border: 1px solid transparent;
        border-radius: 9px;
        background: transparent;
        color: var(--pb-ink);
        font-size: .8125rem;
        font-weight: 600;
        line-height: 1;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        transition: background .15s ease, border-color .15s ease, color .15s ease;
    }

    .pb-btn--primary {
        background: var(--pb-accent);
        color: var(--pb-accent-contrast);
    }

    .pb-btn--primary:hover {
        background: var(--pb-accent-ink);
        color: var(--pb-accent-contrast);
    }

    .pb-btn--outline {
        border-color: var(--pb-rule-strong);
        background: var(--pb-surface);
        color: var(--pb-strong);
    }

    .pb-btn--outline:hover {
        border-color: var(--pb-accent);
        color: var(--pb-accent-ink);
        background: var(--pb-accent-soft);
    }

    .pb-btn--danger {
        border-color: transparent;
        background: var(--pb-danger-soft);
        color: var(--pb-danger);
    }

    .pb-btn--danger:hover {
        border-color: var(--pb-danger);
    }

    .pb-btn--icon {
        width: 34px;
        height: 34px;
        padding: 0;
        border-radius: 8px;
    }

    .pb-btn:disabled {
        opacity: .5;
        cursor: not-allowed;
    }

    .pb-toolbar {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* ===== Tabel produk ===== */
    .pb-table-wrap {
        border: 1px solid var(--pb-rule);
        border-radius: 12px;
        overflow-x: auto;
    }

    .pb-table {
        width: 100%;
        min-width: 1120px;
        margin: 0;
        border-collapse: collapse;
        font-size: .8125rem;
        color: var(--pb-ink);
    }

    .pb-table th {
        padding: 9px 10px;
        border-bottom: 1px solid var(--pb-rule-strong);
        background: var(--pb-subtle);
        color: var(--pb-muted);
        font-size: .6875rem;
        font-weight: 650;
        letter-spacing: .04em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .pb-table td {
        padding: 8px 10px;
        border-bottom: 1px solid var(--pb-rule);
        vertical-align: middle;
        white-space: nowrap;
    }

    .pb-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .pb-table tbody tr:hover td {
        background: var(--pb-hover);
    }

    .pb-table .form-control {
        height: 34px;
        padding: 0 8px;
        border-color: var(--pb-rule);
        border-radius: 8px;
        background: var(--pb-surface);
        color: var(--pb-strong);
        font-size: .8125rem;
    }

    .pb-table .form-control:focus {
        border-color: var(--pb-accent);
        box-shadow: 0 0 0 3px var(--pb-accent-soft);
    }

    .pb-table input[type="number"] {
        width: 76px;
    }

    .pb-table .form-check-input {
        width: 16px;
        height: 16px;
        margin: 0;
        accent-color: var(--pb-accent);
    }

    .pb-money {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .pb-empty td {
        padding: 28px 16px;
        color: var(--pb-muted);
        text-align: center;
    }

    .pb-empty__title {
        display: block;
        margin-bottom: 3px;
        color: var(--pb-strong);
        font-weight: 600;
    }

    /* ===== Pembayaran ===== */
    .pb-pay {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px 16px;
    }

    .pb-bank-row {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) auto;
        gap: 8px;
        align-items: center;
        margin-bottom: 8px;
    }

    .pb-pay__note {
        grid-column: 1 / -1;
        margin: 0;
        padding-top: 2px;
        font-size: .75rem;
        line-height: 1.55;
        color: var(--pb-muted);
    }

    /* ===== Dock pembayaran ===== */
    .pb-dock {
        position: sticky;
        bottom: 14px;
        z-index: 20;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        margin: 8px 24px 24px;
        padding: 14px 16px;
        border: 1px solid var(--pb-rule-strong);
        border-radius: 14px;
        background: var(--pb-surface);
    }

    .pb-dock__figures {
        display: flex;
        align-items: flex-end;
        gap: 22px;
        flex-wrap: wrap;
        min-width: 0;
    }

    .pb-figure {
        display: flex;
        flex-direction: column;
        gap: 3px;
        min-width: 0;
    }

    .pb-figure > span:first-child {
        color: var(--pb-muted);
        font-size: .6875rem;
        font-weight: 650;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .pb-figure input,
    .pb-figure__value {
        display: block;
        width: 8.5rem;
        height: auto;
        padding: 0;
        border: 0;
        background: transparent;
        color: var(--pb-strong);
        font-size: .95rem;
        font-weight: 600;
        line-height: 1.3;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .pb-figure input:focus {
        outline: 2px solid var(--pb-accent);
        outline-offset: 2px;
        border-radius: 2px;
    }

    .pb-figure--total input {
        width: 10rem;
        font-size: 1.15rem;
        font-weight: 650;
        letter-spacing: -.02em;
    }

    .pb-figure--debt input {
        color: var(--pb-danger);
    }

    .pb-dock__save {
        height: 44px;
        padding: 0 22px;
        flex: none;
    }

    /* ===== Modal ===== */
    .pb-modal .modal-content {
        border: 1px solid var(--pb-rule);
        border-radius: var(--pb-radius);
        background: var(--pb-surface);
        color: var(--pb-ink);
        overflow: hidden;
    }

    .pb-modal .modal-header,
    .pb-modal .modal-footer {
        padding: 15px 20px;
        border-color: var(--pb-rule);
    }

    .pb-modal .modal-body {
        padding: 20px;
    }

    .pb-modal .modal-title {
        color: var(--pb-strong);
        font-size: .95rem;
        font-weight: 650;
        letter-spacing: -.015em;
    }

    .pb-modal .btn-close {
        opacity: .6;
    }

    .pb-modal .btn-close:hover {
        opacity: 1;
    }

    .pb-modal .form-label {
        margin-bottom: 6px;
        color: var(--pb-muted);
        font-size: .75rem;
        font-weight: 600;
    }

    .pb-modal .input-group-text {
        border-color: var(--pb-rule);
        background: var(--pb-subtle);
        color: var(--pb-muted);
        font-size: .8125rem;
    }

    .pb-modal .form-text,
    .pb-modal .text-muted {
        color: var(--pb-muted) !important;
    }

    .pb-modal .table {
        color: var(--pb-ink);
        font-size: .8125rem;
    }

    .pb-modal .table > :not(caption) > * > * {
        border-color: var(--pb-rule);
    }

    .pb-modal .table thead th {
        background: var(--pb-subtle);
        color: var(--pb-muted);
        font-size: .6875rem;
        font-weight: 650;
        letter-spacing: .04em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .pb-modal .table tbody tr:hover > * {
        background: var(--pb-hover);
    }

    /* Picker produk — daftar kartu, bukan tabel */
    .pb-picker .modal-content {
        border-color: var(--pb-rule-strong);
        border-radius: 18px;
        background: var(--pb-surface);
    }

    .pb-picker .modal-header {
        align-items: flex-start;
        padding: 18px 22px 15px;
        border-bottom: 1px solid var(--pb-rule);
    }

    .pb-picker__head {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .pb-picker__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: none;
        width: 38px;
        height: 38px;
        border-radius: 11px;
        background: var(--pb-accent-soft);
        color: var(--pb-accent-ink);
    }

    .pb-picker .modal-title {
        margin: 0;
        color: var(--pb-strong);
        font-size: 1.08rem;
        font-weight: 650;
        letter-spacing: -.02em;
        line-height: 1.25;
    }

    .pb-picker__hint {
        margin-top: 2px;
        color: var(--pb-muted);
        font-size: .76rem;
        line-height: 1.4;
    }

    .pb-picker .modal-body {
        padding: 15px 22px 8px;
    }

    .pb-picker__toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }

    .pb-search {
        position: relative;
        flex: 1 1 auto;
        min-width: 0;
        margin: 0;
    }

    .pb-search iconify-icon {
        position: absolute;
        top: 50%;
        left: 13px;
        color: var(--pb-muted);
        transform: translateY(-50%);
        pointer-events: none;
    }

    .pb-search input {
        width: 100%;
        height: 42px;
        padding: 0 13px 0 39px;
        border: 1px solid var(--pb-rule);
        border-radius: 10px;
        background: var(--pb-surface);
        color: var(--pb-strong);
        font-size: .84rem;
    }

    .pb-search input::placeholder {
        color: var(--pb-muted);
        opacity: .85;
    }

    .pb-search input:focus {
        border-color: var(--pb-accent);
        outline: none;
        box-shadow: 0 0 0 3px var(--pb-accent-soft);
    }

    .pb-picker__unit {
        flex: none;
        align-items: center;
        gap: 6px;
        height: 30px;
        padding: 0 10px;
        border: 1px solid var(--pb-rule);
        border-radius: 999px;
        background: var(--pb-subtle);
        color: var(--pb-muted);
        font-size: .7rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .pb-produk-list {
        display: flex;
        flex-direction: column;
        gap: 7px;
        min-height: 240px;
        max-height: min(54vh, 430px);
        overflow-y: auto;
        padding: 2px;
        scrollbar-width: thin;
        scrollbar-color: var(--pb-rule-strong) transparent;
    }

    .pb-produk-list::-webkit-scrollbar {
        width: 8px;
    }

    .pb-produk-list::-webkit-scrollbar-thumb {
        border: 2px solid transparent;
        border-radius: 999px;
        background: var(--pb-rule-strong);
        background-clip: content-box;
    }

    .pb-produk-card {
        position: relative;
        margin: 0;
        cursor: pointer;
    }

    .pb-produk-card[hidden] {
        display: none;
    }

    .pb-produk-card__input {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }

    .pb-produk-card__box {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 10px 13px;
        border: 1px solid var(--pb-rule);
        border-radius: 12px;
        background: var(--pb-surface);
        transition: border-color .15s ease, background-color .15s ease;
    }

    .pb-produk-card:hover .pb-produk-card__box {
        border-color: var(--pb-rule-strong);
        background: var(--pb-hover);
    }

    .pb-produk-card__input:focus-visible + .pb-produk-card__box {
        outline: 2px solid var(--pb-accent);
        outline-offset: 2px;
    }

    .pb-produk-card__input:checked + .pb-produk-card__box {
        border-color: var(--pb-accent);
        background: var(--pb-accent-soft);
    }

    .pb-produk-card__tick {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: none;
        width: 22px;
        height: 22px;
        border: 1.5px solid var(--pb-rule-strong);
        border-radius: 50%;
        color: transparent;
        transition: border-color .15s ease, background-color .15s ease, color .15s ease;
    }

    .pb-produk-card__input:checked + .pb-produk-card__box .pb-produk-card__tick {
        border-color: var(--pb-accent);
        background: var(--pb-accent);
        color: var(--pb-accent-contrast);
    }

    .pb-produk-card__main {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        gap: 3px;
        min-width: 0;
    }

    .pb-produk-card__name {
        color: var(--pb-strong);
        font-size: .85rem;
        font-weight: 600;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .pb-produk-card__meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 2px 10px;
        color: var(--pb-muted);
        font-size: .72rem;
    }

    .pb-produk-card__code {
        color: var(--pb-ink);
        font-weight: 650;
        font-variant-numeric: tabular-nums;
    }

    .pb-produk-card__meta-item {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        min-width: 0;
    }

    .pb-produk-card__meta-item iconify-icon {
        flex: none;
        color: var(--pb-muted);
    }

    .pb-produk-card__side {
        display: flex;
        flex: none;
        flex-direction: column;
        align-items: flex-end;
        gap: 2px;
        text-align: right;
    }

    .pb-produk-card__price {
        color: var(--pb-strong);
        font-size: .86rem;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .pb-produk-card__sub {
        color: var(--pb-muted);
        font-size: .68rem;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .pb-picker__state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 240px;
        padding: 24px 16px;
        text-align: center;
        color: var(--pb-muted);
    }

    .pb-picker__state[hidden] {
        display: none;
    }

    .pb-picker__state p {
        margin: 0;
        font-size: .8rem;
        line-height: 1.5;
    }

    .pb-picker__foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 12px 22px 14px;
        border-top: 1px solid var(--pb-rule);
        background: var(--pb-surface);
    }

    .pb-picker__status {
        display: flex;
        flex-direction: column;
        gap: 1px;
        min-width: 0;
        font-variant-numeric: tabular-nums;
    }

    #pb-produk-summary {
        color: var(--pb-ink);
        font-size: .78rem;
        font-weight: 600;
    }

    #pb-produk-total-hpp {
        color: var(--pb-muted);
        font-size: .72rem;
    }

    .pb-picker__actions {
        display: flex;
        flex: none;
        gap: 8px;
    }

    .pb-picker__actions .pb-btn:disabled {
        opacity: .5;
        cursor: not-allowed;
    }

    /* Select2 mengikuti kontrol form */
    .pembelian-page .select2-container--default .select2-selection--single,
    .pb-modal .select2-container--default .select2-selection--single {
        height: 40px;
        border: 1px solid var(--pb-rule);
        border-radius: 9px;
        background: var(--pb-surface);
    }

    .pembelian-page .select2-container--default .select2-selection--single .select2-selection__rendered,
    .pb-modal .select2-container--default .select2-selection--single .select2-selection__rendered {
        padding: 0 32px 0 12px;
        color: var(--pb-strong);
        font-size: .8125rem;
        line-height: 38px;
    }

    .pembelian-page .select2-container--default .select2-selection--single .select2-selection__arrow,
    .pb-modal .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 38px;
    }

    .pembelian-page .select2-container--default.select2-container--focus .select2-selection--single,
    .pb-modal .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: var(--pb-accent);
        box-shadow: 0 0 0 3px var(--pb-accent-soft);
    }

    /* ===== Responsive ===== */
    @media (max-width: 1199.98px) {
        .pb-dock__figures {
            gap: 16px;
        }
    }

    @media (max-width: 991.98px) {
        .pb-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pb-grid--3 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pb-dock {
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .pb-dock__figures {
            gap: 18px;
        }

        .pb-dock__save {
            margin-left: auto;
        }
    }

    @media (max-width: 767.98px) {
        .pb-head {
            padding: 18px 16px;
        }

        .pb-head h1 {
            font-size: 1.35rem;
        }

        .pb-section {
            padding: 18px 16px;
        }

        .pb-dock {
            bottom: 8px;
            margin: 8px 16px 16px;
            padding: 12px 14px;
        }

        .pb-dock__figures {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            width: 100%;
            gap: 12px;
        }

        .pb-figure input,
        .pb-figure__value {
            width: 100%;
            font-size: .875rem;
        }

        .pb-figure--total input {
            width: 100%;
            font-size: 1rem;
        }

        .pb-dock__save {
            width: 100%;
            margin-left: 0;
        }

        .pb-pay {
            grid-template-columns: minmax(0, 1fr);
        }

        .pb-bank-row {
            grid-template-columns: minmax(0, 1fr) 88px auto;
        }
    }

    @media (max-width: 575.98px) {
        .pb-grid,
        .pb-grid--3 {
            grid-template-columns: minmax(0, 1fr);
        }

        .pb-field--span2 {
            grid-column: span 1;
        }

        .pb-dock__figures {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pb-toolbar {
            width: 100%;
        }

        .pb-toolbar .pb-btn {
            flex: 1;
        }

        .pb-bank-row {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .pb-bank-row .bank-select,
        .pb-bank-row .select2-container {
            grid-column: 1 / -1;
        }

        .pb-picker .modal-header {
            padding: 15px 16px 13px;
        }

        .pb-picker .modal-body {
            padding: 13px 14px 6px;
        }

        .pb-picker__icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
        }

        .pb-picker .modal-title {
            font-size: 1rem;
        }

        .pb-picker__hint {
            font-size: .72rem;
        }

        .pb-produk-list {
            max-height: min(56vh, 400px);
        }

        .pb-produk-card__box {
            flex-wrap: wrap;
            padding: 10px 12px;
        }

        .pb-produk-card__name {
            font-size: .82rem;
        }

        .pb-produk-card__side {
            flex-basis: 100%;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            padding-left: calc(22px + 11px);
        }

        .pb-produk-card__price {
            font-size: .82rem;
        }

        .pb-picker__foot {
            flex-wrap: wrap;
            padding: 11px 16px 13px;
        }

        .pb-picker__actions {
            margin-left: auto;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .pb-head {
            animation: none;
        }

        .pb-btn,
        .pb-file {
            transition: none;
        }
    }
</style>
