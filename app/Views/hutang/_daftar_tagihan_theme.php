<?php

/**
 * Tema halaman Daftar Tagihan Hutang (hutang supplier).
 *
 * Scoped di `.dt-page` supaya tidak bocor ke modul lain. Bahasa visual:
 * lembar statement datar, garis rambut, angka tabular — mengikuti aksen
 * HUTANG (merah) dari DESIGN.md, tanpa kartu bertumpuk dan tanpa shadow.
 */
?>

<style>
    /* Token dipakai halaman dan modal; modal berada di luar `.dt-page`
       karena Bootstrap memindahkannya ke root dokumen saat dibuka. */
    .dt-page,
    .dt-pay-modal {
        --dt-surface: var(--bs-card-bg, var(--bs-body-bg, #fff));
        --dt-subtle: var(--bs-tertiary-bg, #f6f8fb);
        --dt-rule: var(--bs-border-color, #e6ecf1);
        --dt-ink: var(--bs-body-color, #1f2a37);
        --dt-muted: #5b6672;
        --dt-accent-ink: #a3212a;
        --dt-accent-solid: #b3261e;
        --dt-accent-soft: rgba(179, 38, 30, .07);
        --dt-danger-ink: #b02a37;
        --dt-danger-soft: rgba(176, 42, 55, .10);
        --dt-warn-ink: #8a5300;
        --dt-warn-soft: rgba(192, 118, 0, .13);
        --dt-ok-ink: #0f6b3e;
        --dt-ok-soft: rgba(15, 122, 70, .10);
        --dt-radius: 14px;
        --dt-radius-sm: 9px;
    }

    .dt-page {
        display: flex;
        flex-direction: column;
        gap: 18px;
        margin-bottom: 24px;
        color: var(--dt-ink);
    }

    html[data-bs-theme="dark"] .dt-page,
    html[data-bs-theme="dark"] .dt-pay-modal {
        --dt-surface: var(--bs-card-bg, #1f2937);
        --dt-subtle: rgba(255, 255, 255, .04);
        --dt-rule: rgba(255, 255, 255, .12);
        --dt-ink: var(--bs-body-color, #e5edf6);
        --dt-muted: #93a3b8;
        --dt-accent-ink: #ff9aa2;
        --dt-accent-solid: #a3212a;
        --dt-accent-soft: rgba(255, 154, 162, .12);
        --dt-danger-ink: #ff9aa2;
        --dt-danger-soft: rgba(255, 154, 162, .12);
        --dt-warn-ink: #ffcf6e;
        --dt-warn-soft: rgba(255, 207, 110, .12);
        --dt-ok-ink: #6fdd9d;
        --dt-ok-soft: rgba(111, 221, 157, .12);
    }

    .dt-page *,
    .dt-page *::before,
    .dt-page *::after,
    .dt-pay-modal *,
    .dt-pay-modal *::before,
    .dt-pay-modal *::after {
        box-sizing: border-box;
    }

    .dt-page h1,
    .dt-page h2,
    .dt-page h3,
    .dt-page p {
        margin: 0;
    }

    /* `hidden` harus menang atas display: block milik layout mobile. */
    .dt-page [hidden] {
        display: none !important;
    }

    /* ---------- Header ---------- */
    /* Kartu putih ber-hairline: judul harus terbaca sebagai permukaan,
       bukan teks yang mengambang di kanvas (DESIGN.md). */
    .dt-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        padding: 18px 20px;
        border: 1px solid var(--dt-rule);
        border-radius: var(--dt-radius);
        background: var(--dt-surface);
    }

    .dt-crumb {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 10px;
        font-size: .74rem;
        color: var(--dt-muted);
    }

    .dt-crumb a {
        color: inherit;
        text-decoration: none;
    }

    .dt-crumb a:hover {
        color: var(--dt-accent-ink);
    }

    .dt-crumb span[aria-hidden="true"] {
        opacity: .55;
    }

    .dt-head h1 {
        font-size: 1.5rem;
        font-weight: 650;
        letter-spacing: -.025em;
        color: var(--dt-ink);
    }

    .dt-head__desc {
        margin-top: 6px;
        max-width: 66ch;
        font-size: .85rem;
        line-height: 1.55;
        color: var(--dt-muted);
    }

    .dt-head__actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* ---------- Buttons & controls ---------- */
    .dt-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 36px;
        padding: 8px 13px;
        border: 1px solid transparent;
        border-radius: var(--dt-radius-sm);
        font-family: inherit;
        font-size: .78rem;
        font-weight: 650;
        line-height: 1.2;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }

    .dt-btn iconify-icon {
        flex: none;
        font-size: 1.05em;
    }

    .dt-btn--ghost {
        color: var(--dt-ink);
        background: transparent;
        border-color: var(--dt-rule);
    }

    .dt-btn--ghost:hover,
    .dt-btn--ghost:focus-visible {
        color: var(--dt-accent-ink);
        background: var(--dt-accent-soft);
        border-color: var(--dt-accent-ink);
    }

    .dt-btn--solid {
        color: #fff;
        background: var(--dt-accent-solid);
        border-color: var(--dt-accent-solid);
    }

    .dt-btn--solid:hover,
    .dt-btn--solid:focus-visible {
        color: #fff;
        background: #94201a;
        border-color: #94201a;
    }

    .dt-btn--pay {
        min-height: 30px;
        padding: 6px 11px;
        font-size: .74rem;
    }

    .dt-btn:focus-visible,
    .dt-field:focus-visible,
    .dt-search:focus-within {
        outline: 2px solid var(--dt-accent-ink);
        outline-offset: 2px;
    }

    .dt-btn:disabled {
        opacity: .5;
        cursor: not-allowed;
    }

    /* ---------- Summary strip ---------- */
    .dt-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        border: 1px solid var(--dt-rule);
        border-radius: var(--dt-radius);
        background: var(--dt-surface);
        overflow: hidden;
    }

    .dt-summary__cell {
        padding: 15px 18px;
        min-width: 0;
    }

    .dt-summary__cell + .dt-summary__cell {
        border-left: 1px solid var(--dt-rule);
    }

    .dt-summary__label {
        display: block;
        font-size: .68rem;
        font-weight: 650;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--dt-muted);
    }

    .dt-summary__value {
        display: block;
        margin-top: 4px;
        font-size: 1.15rem;
        font-weight: 650;
        letter-spacing: -.015em;
        font-variant-numeric: tabular-nums;
        color: var(--dt-ink);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .dt-summary__value--lead {
        font-size: 1.45rem;
    }

    .dt-summary__value.is-danger {
        color: var(--dt-danger-ink);
    }

    .dt-summary__value.is-warn {
        color: var(--dt-warn-ink);
    }

    /* ---------- Sheet ---------- */
    .dt-sheet {
        border: 1px solid var(--dt-rule);
        border-radius: var(--dt-radius);
        background: var(--dt-surface);
        overflow: hidden;
    }

    .dt-toolbar {
        display: flex;
        align-items: flex-end;
        gap: 12px;
        flex-wrap: wrap;
        padding: 12px 16px;
        border-bottom: 1px solid var(--dt-rule);
    }

    .dt-search {
        position: relative;
        flex: 1 1 240px;
        min-width: 190px;
        max-width: 340px;
    }

    .dt-search iconify-icon {
        position: absolute;
        top: 50%;
        left: 11px;
        transform: translateY(-50%);
        font-size: 1rem;
        color: var(--dt-muted);
        pointer-events: none;
    }

    .dt-search input {
        width: 100%;
        height: 36px;
        padding: 0 12px 0 34px;
        border: 1px solid var(--dt-rule);
        border-radius: var(--dt-radius-sm);
        background: var(--dt-surface);
        color: var(--dt-ink);
        font-family: inherit;
        font-size: .82rem;
    }

    .dt-search input::placeholder {
        color: var(--dt-muted);
    }

    .dt-search input:focus {
        outline: none;
        border-color: var(--dt-accent-ink);
    }

    .dt-filters {
        display: flex;
        align-items: flex-end;
        gap: 10px;
        flex: 1 1 auto;
        flex-wrap: wrap;
        margin: 0;
    }

    .dt-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }

    .dt-field > span {
        font-size: .66rem;
        font-weight: 650;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--dt-muted);
    }

    .dt-field input,
    .dt-field select {
        height: 36px;
        padding: 0 10px;
        border: 1px solid var(--dt-rule);
        border-radius: var(--dt-radius-sm);
        background: var(--dt-surface);
        color: var(--dt-ink);
        font-family: inherit;
        font-size: .8rem;
        max-width: 100%;
    }

    .dt-field select {
        padding-right: 26px;
    }

    .dt-field input:focus,
    .dt-field select:focus {
        outline: none;
        border-color: var(--dt-accent-ink);
        box-shadow: 0 0 0 3px var(--dt-accent-soft);
    }

    .dt-field--date input {
        width: 138px;
    }

    .dt-toolbar__actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: auto;
    }

    /* ---------- Table ---------- */
    .dt-table-wrap {
        overflow-x: auto;
    }

    .dt-table {
        width: 100%;
        border-collapse: collapse;
        font-size: .84rem;
    }

    .dt-table caption {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
        white-space: nowrap;
    }

    .dt-table th {
        padding: 10px 16px;
        font-size: .66rem;
        font-weight: 650;
        letter-spacing: .06em;
        text-transform: uppercase;
        text-align: left;
        color: var(--dt-muted);
        background: var(--dt-surface);
        white-space: nowrap;
    }

    .dt-table th.is-num,
    .dt-table td.is-num {
        text-align: right;
    }

    .dt-table td {
        padding: 12px 16px;
        border-top: 1px solid var(--dt-rule);
        vertical-align: middle;
    }

    .dt-table tbody tr {
        transition: background-color .12s ease;
    }

    .dt-table tbody tr:hover {
        background: var(--dt-subtle);
    }

    .dt-doc {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }

    .dt-doc__code {
        font-weight: 650;
        font-size: .84rem;
        color: var(--dt-ink);
        word-break: break-word;
    }

    .dt-doc__meta,
    .dt-cell__meta {
        font-size: .72rem;
        color: var(--dt-muted);
    }

    .dt-money {
        display: flex;
        flex-direction: column;
        gap: 2px;
        align-items: flex-end;
    }

    .dt-money__value {
        font-weight: 650;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .dt-money__meta {
        font-size: .72rem;
        color: var(--dt-muted);
        font-variant-numeric: tabular-nums;
    }

    .dt-due {
        display: flex;
        flex-direction: column;
        gap: 2px;
        white-space: nowrap;
    }

    .dt-due__date {
        font-variant-numeric: tabular-nums;
    }

    .dt-due__note {
        font-size: .72rem;
        color: var(--dt-muted);
    }

    .dt-due.is-danger .dt-due__date,
    .dt-due.is-danger .dt-due__note {
        color: var(--dt-danger-ink);
    }

    .dt-due.is-warn .dt-due__note {
        color: var(--dt-warn-ink);
    }

    .dt-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 650;
        white-space: nowrap;
    }

    .dt-status::before {
        content: "";
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    .dt-status.is-danger {
        color: var(--dt-danger-ink);
        background: var(--dt-danger-soft);
    }

    .dt-status.is-warn {
        color: var(--dt-warn-ink);
        background: var(--dt-warn-soft);
    }

    .dt-status.is-ok {
        color: var(--dt-ok-ink);
        background: var(--dt-ok-soft);
    }

    .dt-table__foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        padding: 10px 16px;
        border-top: 1px solid var(--dt-rule);
        font-size: .74rem;
        color: var(--dt-muted);
    }

    .dt-table__foot strong {
        color: var(--dt-ink);
        font-variant-numeric: tabular-nums;
    }

    /* ---------- Empty state ---------- */
    .dt-empty {
        padding: 52px 24px;
        text-align: center;
    }

    .dt-empty iconify-icon {
        font-size: 2.2rem;
        color: var(--dt-muted);
    }

    .dt-empty h2 {
        margin-top: 10px;
        font-size: 1rem;
        font-weight: 650;
        color: var(--dt-ink);
    }

    .dt-empty p {
        margin-top: 4px;
        font-size: .82rem;
        color: var(--dt-muted);
    }

    /* ---------- Modal bayar ---------- */
    .dt-pay-modal .modal-content {
        border: 1px solid var(--dt-rule);
        border-radius: var(--dt-radius);
        background: var(--dt-surface);
        color: var(--dt-ink);
    }

    .dt-pay-modal .modal-header,
    .dt-pay-modal .modal-footer {
        border-color: var(--dt-rule);
        padding: 14px 18px;
    }

    .dt-pay-modal .modal-body {
        padding: 18px;
    }

    .dt-pay-modal .modal-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 650;
        letter-spacing: -.01em;
        color: var(--dt-ink);
    }

    .dt-pay-modal .btn-close {
        opacity: .6;
    }

    .dt-modal-summary {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 14px;
        border: 1px solid var(--dt-rule);
        border-radius: var(--dt-radius-sm);
        background: var(--dt-subtle);
        margin-bottom: 16px;
    }

    .dt-modal-summary span {
        font-size: .72rem;
        color: var(--dt-muted);
    }

    .dt-modal-summary strong {
        font-size: 1.2rem;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
        color: var(--dt-ink);
    }

    .dt-modal-meta {
        font-size: .74rem;
        color: var(--dt-muted);
        margin-bottom: 14px;
    }

    .dt-methods {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4px;
        padding: 4px;
        margin-bottom: 14px;
        border: 1px solid var(--dt-rule);
        border-radius: var(--dt-radius-sm);
        background: var(--dt-subtle);
    }

    .dt-method {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 34px;
        padding: 6px 10px;
        border: 1px solid transparent;
        border-radius: calc(var(--dt-radius-sm) - 2px);
        background: transparent;
        color: var(--dt-muted);
        font-family: inherit;
        font-size: .8rem;
        font-weight: 650;
        cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }

    .dt-method iconify-icon {
        flex: none;
        font-size: 1.1em;
    }

    .dt-method:hover:not(:disabled) {
        color: var(--dt-ink);
    }

    .dt-method.is-active {
        color: var(--dt-accent-ink);
        background: var(--dt-surface);
        border-color: var(--dt-rule);
    }

    .dt-method:focus-visible {
        outline: 2px solid var(--dt-accent-ink);
        outline-offset: 2px;
    }

    .dt-method:disabled {
        opacity: .45;
        cursor: not-allowed;
    }

    .dt-modal-label {
        display: block;
        margin-bottom: 4px;
        font-size: .68rem;
        font-weight: 650;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--dt-muted);
    }

    .dt-modal-input {
        width: 100%;
        height: 38px;
        padding: 0 11px;
        border: 1px solid var(--dt-rule);
        border-radius: var(--dt-radius-sm);
        background: var(--dt-surface);
        color: var(--dt-ink);
        font-family: inherit;
        font-size: .88rem;
        font-variant-numeric: tabular-nums;
    }

    .dt-modal-input:focus {
        outline: none;
        border-color: var(--dt-accent-ink);
        box-shadow: 0 0 0 3px var(--dt-accent-soft);
    }

    .dt-modal-input.is-invalid {
        border-color: var(--dt-danger-ink);
    }

    .dt-modal-hint {
        margin-top: 4px;
        font-size: .7rem;
        color: var(--dt-muted);
    }

    .dt-modal-calc {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 16px;
        padding-top: 14px;
        border-top: 1px solid var(--dt-rule);
    }

    .dt-modal-calc__row span {
        display: block;
        font-size: .68rem;
        font-weight: 650;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--dt-muted);
    }

    .dt-modal-calc__row strong {
        font-size: .95rem;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
    }

    .dt-modal-error {
        margin-top: 10px;
        font-size: .75rem;
        color: var(--dt-danger-ink);
    }

    .dt-modal-error:empty {
        display: none;
    }

    .dt-link-btn {
        padding: 0;
        border: 0;
        background: none;
        color: var(--dt-accent-ink);
        font-family: inherit;
        font-size: .74rem;
        font-weight: 650;
        text-decoration: underline;
        text-underline-offset: 3px;
        cursor: pointer;
    }

    /* ---------- Responsive ---------- */
    @media (max-width: 991.98px) {
        .dt-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .dt-summary__cell:nth-child(3) {
            border-left: 0;
        }

        .dt-summary__cell:nth-child(n + 3) {
            border-top: 1px solid var(--dt-rule);
        }

        .dt-toolbar__actions {
            margin-left: 0;
        }
    }

    @media (max-width: 767.98px) {
        .dt-head {
            align-items: flex-start;
            padding: 16px;
        }

        .dt-head__actions {
            width: 100%;
        }

        .dt-head__actions .dt-btn {
            flex: 1 1 auto;
        }

        .dt-summary__cell {
            padding: 13px 14px;
        }

        .dt-summary__value--lead {
            font-size: 1.05rem;
        }

        .dt-field--date input {
            width: 100%;
        }

        .dt-toolbar {
            gap: 10px;
        }

        .dt-search {
            flex-basis: 100%;
            max-width: none;
        }

        .dt-filters {
            width: 100%;
        }

        .dt-field {
            flex: 1 1 132px;
        }

        .dt-toolbar__actions {
            width: 100%;
        }

        .dt-toolbar__actions .dt-btn {
            flex: 1 1 auto;
        }

        /* Tabel menjadi daftar bertumpuk */
        .dt-table thead {
            display: none;
        }

        .dt-table,
        .dt-table tbody,
        .dt-table tr,
        .dt-table td {
            display: block;
            width: 100%;
        }

        .dt-table tr {
            padding: 12px 14px;
            border-top: 1px solid var(--dt-rule);
        }

        .dt-table tbody tr:first-child {
            border-top: 0;
        }

        .dt-table tbody tr:hover {
            background: transparent;
        }

        .dt-table td {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 4px 0;
            border: 0;
            text-align: right;
        }

        .dt-table td::before {
            content: attr(data-label);
            font-size: .66rem;
            font-weight: 650;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--dt-muted);
            text-align: left;
        }

        .dt-table td[data-label="Aksi"] {
            padding-top: 10px;
        }

        .dt-table td[data-label="Aksi"]::before {
            display: none;
        }

        .dt-table td[data-label="Aksi"] .dt-btn {
            width: 100%;
        }

        .dt-money {
            align-items: flex-end;
        }

        .dt-modal-calc {
            grid-template-columns: 1fr;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .dt-page *,
        .dt-page *::before,
        .dt-page *::after {
            transition-duration: .01ms !important;
        }
    }
</style>
