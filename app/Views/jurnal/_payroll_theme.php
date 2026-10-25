<?php
/**
 * Tema halaman Payroll — Lembar Proses Payroll.
 *
 * Semua selector di bawah `.payroll-page`. Warna diambil dari token tema
 * aplikasi supaya mode terang/gelap tetap satu dunia dengan halaman lain.
 * Tidak ada palette, font, atau library baru.
 */
?>
<style>
    .payroll-page {
        --pay-surface: var(--bs-body-bg);
        --pay-ink: var(--bs-emphasis-color);
        --pay-muted: color-mix(in srgb, var(--bs-emphasis-color) 55%, var(--bs-body-bg));
        --pay-line: var(--bs-border-color);
        --pay-raise: var(--bs-tertiary-bg);
        --pay-primary-ink: color-mix(in srgb, var(--bs-primary) 62%, var(--bs-emphasis-color));
        --pay-success-ink: color-mix(in srgb, var(--bs-success) 38%, var(--bs-emphasis-color));
        --pay-warning-ink: color-mix(in srgb, var(--bs-warning) 32%, var(--bs-emphasis-color));
        --pay-danger-ink: color-mix(in srgb, var(--bs-danger) 36%, var(--bs-emphasis-color));
        --pay-r: 1.125rem;
        --pay-shadow: 0 2px 6px rgba(37, 83, 185, .1);
        color: var(--pay-ink);
        font-variant-numeric: tabular-nums;
    }

    .payroll-page .pay-sheet,
    .payroll-page .pay-panel {
        background: var(--pay-surface);
        border-radius: var(--pay-r);
        box-shadow: var(--pay-shadow);
        overflow: hidden;
    }

    .payroll-page .pay-panel {
        margin-top: 1rem;
    }

    /* ── Masthead ───────────────────────────────────────────── */
    .payroll-page .pay-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 1.25rem 2rem;
        align-items: start;
        padding: 1.25rem 1.5rem 1rem;
    }

    .payroll-page .pay-crumb {
        display: flex;
        align-items: center;
        gap: .4rem;
        margin-bottom: .35rem;
        font-size: .72rem;
        color: var(--pay-muted);
    }

    .payroll-page .pay-crumb a {
        color: inherit;
        text-decoration: none;
    }

    .payroll-page .pay-crumb a:hover {
        color: var(--pay-primary-ink);
    }

    .payroll-page .pay-title-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: .55rem;
    }

    .payroll-page .pay-title {
        margin: 0;
        color: var(--pay-ink);
        font-size: 1.5rem;
        font-weight: 650;
        line-height: 1.2;
        letter-spacing: -.015em;
    }

    .payroll-page .pay-period-badge {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .2rem .55rem;
        border: 1px solid var(--pay-line);
        border-radius: 999px;
        color: var(--pay-muted);
        font-size: .68rem;
        font-weight: 600;
        line-height: 1.3;
        white-space: nowrap;
    }

    .payroll-page .pay-sub {
        margin: .35rem 0 0;
        max-width: 68ch;
        color: var(--pay-muted);
        font-size: .8rem;
        line-height: 1.5;
    }

    .payroll-page .pay-head-side {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: .65rem;
    }

    .payroll-page .pay-period {
        display: flex;
        align-items: center;
        gap: .4rem;
    }

    .payroll-page .pay-period label {
        margin: 0;
        color: var(--pay-muted);
        font-size: .7rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .payroll-page .pay-period .form-control {
        width: 9.4rem;
        height: 34px;
        border-color: var(--pay-line);
        background: var(--pay-surface);
        color: var(--pay-ink);
        font-size: .76rem;
    }

    .payroll-page .pay-period .form-control:focus,
    .payroll-page .pay-search input:focus {
        border-color: var(--bs-primary);
        box-shadow: 0 0 0 .18rem rgba(var(--bs-primary-rgb), .16);
    }

    .payroll-page .pay-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: .45rem;
    }

    .payroll-page .btn {
        border-radius: .55rem;
        font-size: .76rem;
        font-weight: 600;
    }

    .payroll-page .btn-sm {
        padding: .4rem .7rem;
        line-height: 1.25;
    }

    .payroll-page .btn-outline-primary {
        border-color: var(--bs-primary);
        color: var(--pay-primary-ink);
    }

    .payroll-page .btn-outline-primary:hover,
    .payroll-page .btn-outline-primary:active {
        border-color: var(--bs-primary);
        background: var(--bs-primary);
        color: #fff;
    }

    .payroll-page .pay-actions .btn {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
    }

    /* ── Papan fase ─────────────────────────────────────────── */
    .payroll-page .pay-run {
        border-top: 1px solid var(--pay-line);
    }

    .payroll-page .pay-run-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .5rem 1rem;
        padding: .8rem 1.5rem;
        color: var(--pay-muted);
        font-size: .73rem;
    }

    .payroll-page .pay-run-head strong {
        color: var(--pay-ink);
        font-weight: 650;
    }

    .payroll-page .pay-run-alert,
    .payroll-page .pay-run-ok {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        font-weight: 600;
    }

    .payroll-page .pay-run-alert {
        color: var(--pay-danger-ink);
    }

    .payroll-page .pay-run-ok {
        color: var(--pay-success-ink);
    }

    .payroll-page .pay-phases {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        border-top: 1px solid var(--pay-line);
    }

    .payroll-page .pay-phase {
        min-width: 0;
        padding: 1rem 1.25rem 1.05rem;
    }

    .payroll-page .pay-phase + .pay-phase {
        border-left: 1px solid var(--pay-line);
    }

    .payroll-page .pay-phase-label {
        display: flex;
        align-items: center;
        gap: .4rem;
        color: var(--pay-muted);
        font-size: .7rem;
        font-weight: 650;
        line-height: 1.2;
    }

    .payroll-page .pay-dot {
        width: 7px;
        height: 7px;
        flex: 0 0 7px;
        border-radius: 50%;
        background: currentColor;
    }

    .payroll-page .pay-phase--auto .pay-dot { color: var(--bs-primary); }
    .payroll-page .pay-phase--manual .pay-dot { color: var(--bs-warning); }
    .payroll-page .pay-phase--paid .pay-dot { color: var(--bs-success); }
    .payroll-page .pay-phase--bonus .pay-dot { color: var(--bs-info); }

    .payroll-page .pay-phase-value {
        margin-top: .4rem;
        color: var(--pay-ink);
        font-size: 1.65rem;
        font-weight: 650;
        line-height: 1;
        letter-spacing: -.02em;
    }

    .payroll-page .pay-phase-value small {
        margin-left: .15rem;
        color: var(--pay-muted);
        font-size: .68rem;
        font-weight: 600;
        letter-spacing: 0;
    }

    .payroll-page .pay-phase-money {
        margin-top: .35rem;
        color: var(--pay-ink);
        font-size: .8rem;
        font-weight: 650;
    }

    .payroll-page .pay-phase-note {
        margin-top: .18rem;
        color: var(--pay-muted);
        font-size: .7rem;
        line-height: 1.35;
    }

    .payroll-page .pay-run-note {
        display: flex;
        align-items: flex-start;
        gap: .65rem;
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--pay-line);
        color: var(--pay-muted);
        font-size: .78rem;
        line-height: 1.45;
    }

    .payroll-page .pay-run-note strong {
        color: var(--pay-ink);
    }

    .payroll-page .pay-run-note iconify-icon {
        margin-top: .1rem;
        color: var(--pay-primary-ink);
        font-size: 1.1rem;
    }

    /* ── Strip KPI unit ─────────────────────────────────────── */
    .payroll-page .pay-units {
        border-top: 1px solid var(--pay-line);
        padding: .7rem 1.5rem .85rem;
    }

    .payroll-page .pay-units-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: .35rem;
        color: var(--pay-muted);
        font-size: .68rem;
        font-weight: 600;
    }

    .payroll-page .pay-units-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }

    .payroll-page .pay-unit {
        min-width: 0;
        padding: .45rem 1rem;
        border-left: 1px solid var(--pay-line);
    }

    .payroll-page .pay-unit:first-child {
        border-left: 0;
        padding-left: 0;
    }

    .payroll-page .pay-unit-name {
        display: flex;
        align-items: center;
        gap: .3rem;
        overflow: hidden;
        color: var(--pay-ink);
        font-size: .75rem;
        font-weight: 600;
        line-height: 1.3;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .payroll-page .pay-unit-name iconify-icon {
        flex: 0 0 auto;
        color: var(--pay-muted);
    }

    .payroll-page .pay-unit-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: .5rem;
        margin-top: .15rem;
    }

    .payroll-page .pay-unit-score {
        color: var(--pay-ink);
        font-size: .98rem;
        font-weight: 650;
        line-height: 1.2;
    }

    .payroll-page .pay-unit-score small {
        color: var(--pay-muted);
        font-size: .62rem;
        font-weight: 600;
    }

    .payroll-page .pay-unit-total {
        overflow: hidden;
        color: var(--pay-muted);
        font-size: .68rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .payroll-page .pay-meter {
        height: 3px;
        margin-top: .3rem;
        overflow: hidden;
        border-radius: 2px;
        background: var(--pay-line);
    }

    .payroll-page .pay-meter span {
        display: block;
        width: var(--pay-score, 0%);
        height: 100%;
        border-radius: 2px;
        background: var(--bs-primary);
    }

    .payroll-page .pay-unit-status {
        margin-top: .18rem;
        color: var(--pay-muted);
        font-size: .64rem;
        line-height: 1.2;
    }

    .payroll-page .pay-units-empty {
        margin: 0;
        padding: .25rem 0 .1rem;
        color: var(--pay-muted);
        font-size: .73rem;
    }

    /* ── Panel ledger ───────────────────────────────────────── */
    .payroll-page .pay-panel-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .75rem 1.25rem;
        padding: 1.1rem 1.5rem .85rem;
    }

    .payroll-page .pay-panel-title {
        margin: 0;
        color: var(--pay-ink);
        font-size: 1rem;
        font-weight: 650;
        line-height: 1.25;
    }

    .payroll-page .pay-panel-sub {
        margin: .22rem 0 0;
        max-width: 66ch;
        color: var(--pay-muted);
        font-size: .74rem;
        line-height: 1.45;
    }

    .payroll-page .pay-panel-meta {
        margin-left: auto;
        text-align: right;
        color: var(--pay-muted);
        font-size: .7rem;
        line-height: 1.3;
    }

    .payroll-page .pay-panel-meta b {
        display: block;
        margin-top: .12rem;
        color: var(--pay-ink);
        font-size: .95rem;
        font-weight: 650;
    }

    .payroll-page .pay-panel--bonus .pay-panel-head > .btn {
        align-self: center;
        display: inline-flex;
        align-items: center;
        gap: .35rem;
    }

    .payroll-page .pay-toolbar {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: .65rem .8rem;
        padding: .6rem 1.5rem;
        border-top: 1px solid var(--pay-line);
        border-bottom: 1px solid var(--pay-line);
    }

    .payroll-page .pay-search {
        position: relative;
        flex: 0 1 300px;
        min-width: 190px;
        margin: 0;
    }

    .payroll-page .pay-search iconify-icon {
        position: absolute;
        top: 50%;
        left: .7rem;
        color: var(--pay-muted);
        font-size: .95rem;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .payroll-page .pay-search input {
        width: 100%;
        height: 34px;
        padding: .35rem .7rem .35rem 2rem;
        border: 1px solid var(--pay-line);
        border-radius: .5rem;
        background: var(--pay-surface);
        color: var(--pay-ink);
        font-size: .75rem;
    }

    .payroll-page .pay-search input::placeholder {
        color: var(--pay-muted);
        opacity: 1;
    }

    .payroll-page .pay-chips {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: .3rem;
    }

    .payroll-page .pay-chip {
        display: inline-flex;
        align-items: center;
        gap: .28rem;
        padding: .3rem .6rem;
        border: 1px solid var(--pay-line);
        border-radius: 999px;
        background: transparent;
        color: var(--pay-muted);
        font-size: .7rem;
        font-weight: 600;
        line-height: 1.2;
        transition: color .15s ease, background-color .15s ease, border-color .15s ease;
    }

    .payroll-page .pay-chip span {
        color: inherit;
        font-size: .62rem;
        opacity: .8;
    }

    .payroll-page .pay-chip:hover {
        border-color: var(--bs-primary-border-subtle);
        color: var(--pay-primary-ink);
    }

    .payroll-page .pay-chip.is-active {
        border-color: var(--bs-primary-border-subtle);
        background: var(--bs-primary-bg-subtle);
        color: var(--pay-primary-ink);
    }

    .payroll-page .pay-result {
        margin-left: auto;
        color: var(--pay-muted);
        font-size: .7rem;
        white-space: nowrap;
    }

    /* ── Tabel ledger ───────────────────────────────────────── */
    .payroll-page .pay-table-wrap {
        width: 100%;
    }

    .payroll-page .pay-table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
        color: var(--pay-ink);
    }

    .payroll-page .pay-table th {
        padding: .55rem 1rem;
        border-bottom: 1px solid var(--pay-line);
        color: var(--pay-muted);
        font-size: .65rem;
        font-weight: 650;
        letter-spacing: .05em;
        text-align: left;
        text-transform: uppercase;
    }

    .payroll-page .pay-table td {
        padding: .78rem 1rem;
        border-bottom: 1px solid var(--pay-line);
        vertical-align: middle;
    }

    .payroll-page .pay-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .payroll-page .pay-table tr[hidden] {
        display: none !important;
    }

    .payroll-page .pay-table tbody tr:hover {
        background: color-mix(in srgb, var(--pay-raise) 48%, transparent);
    }

    .payroll-page .pay-th-right,
    .payroll-page .pay-amount {
        text-align: right;
    }

    .payroll-page .pay-th-center,
    .payroll-page .pay-cell-actions {
        text-align: center;
    }

    .payroll-page .pay-person {
        display: flex;
        align-items: center;
        gap: .6rem;
        min-width: 0;
    }

    .payroll-page .pay-avatar {
        display: grid;
        width: 32px;
        height: 32px;
        flex: 0 0 32px;
        place-items: center;
        border-radius: 50%;
        background: var(--bs-primary-bg-subtle);
        color: var(--pay-primary-ink);
        font-size: .75rem;
        font-weight: 700;
    }

    .payroll-page .pay-person-copy {
        min-width: 0;
    }

    .payroll-page .pay-person-name {
        display: block;
        overflow: hidden;
        color: var(--pay-ink);
        font-size: .8rem;
        font-weight: 600;
        line-height: 1.25;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .payroll-page .pay-person-unit {
        display: block;
        margin-top: .1rem;
        overflow: hidden;
        color: var(--pay-muted);
        font-size: .68rem;
        line-height: 1.25;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .payroll-page .pay-date {
        display: block;
        color: var(--pay-ink);
        font-size: .76rem;
        font-weight: 600;
        line-height: 1.25;
    }

    .payroll-page .pay-date-sub {
        display: block;
        margin-top: .12rem;
        color: var(--pay-muted);
        font-size: .68rem;
        line-height: 1.3;
    }

    .payroll-page .pay-date-sub.is-late {
        color: var(--pay-danger-ink);
        font-weight: 600;
    }

    .payroll-page .pay-badge {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        padding: .2rem .48rem;
        border-radius: 999px;
        font-size: .65rem;
        font-weight: 650;
        line-height: 1.25;
        white-space: nowrap;
    }

    .payroll-page .pay-badge--plan {
        background: var(--bs-primary-bg-subtle);
        color: var(--pay-primary-ink);
    }

    .payroll-page .pay-badge--paid {
        background: var(--bs-success-bg-subtle);
        color: var(--pay-success-ink);
    }

    .payroll-page .pay-timing {
        display: block;
        margin-top: .22rem;
        color: var(--pay-muted);
        font-size: .65rem;
        line-height: 1.3;
    }

    .payroll-page .pay-timing.is-ok {
        color: var(--pay-success-ink);
    }

    .payroll-page .pay-timing.is-late {
        color: var(--pay-danger-ink);
        font-weight: 600;
    }

    .payroll-page .pay-amount {
        color: var(--pay-ink);
        font-size: .95rem;
        font-weight: 700;
        letter-spacing: -.01em;
        white-space: nowrap;
    }

    .payroll-page .pay-src {
        display: inline-block;
        margin-bottom: .18rem;
        padding: .08rem .32rem;
        border-radius: .28rem;
        background: var(--bs-info-bg-subtle);
        color: var(--pay-primary-ink);
        font-size: .58rem;
        font-weight: 700;
        letter-spacing: .04em;
        line-height: 1.35;
        text-transform: uppercase;
    }

    .payroll-page .pay-src--manual {
        background: var(--bs-secondary-bg);
        color: var(--pay-muted);
    }

    .payroll-page .pay-note {
        display: -webkit-box;
        max-width: 30ch;
        overflow: hidden;
        color: var(--pay-muted);
        font-size: .7rem;
        line-height: 1.35;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .payroll-page .pay-cell-note {
        line-height: 1.35;
    }

    .payroll-page .pay-muted {
        color: var(--pay-muted);
    }

    .payroll-page .pay-row-actions {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .32rem;
    }

    .payroll-page .pay-icon-btn {
        display: inline-grid;
        width: 32px;
        height: 32px;
        padding: 0;
        place-items: center;
        border: 1px solid var(--pay-line);
        border-radius: .5rem;
        background: transparent;
        color: var(--pay-muted);
        font-size: .95rem;
        line-height: 1;
        transition: color .15s ease, background-color .15s ease, border-color .15s ease;
    }

    .payroll-page .pay-icon-btn:hover {
        background: var(--pay-raise);
        color: var(--pay-ink);
    }

    .payroll-page .pay-icon-btn--edit:hover {
        border-color: var(--bs-warning-border-subtle);
        color: var(--pay-warning-ink);
    }

    .payroll-page .pay-icon-btn--pay:hover,
    .payroll-page .pay-icon-btn--unlock:hover {
        border-color: var(--bs-success-border-subtle);
        color: var(--pay-success-ink);
    }

    .payroll-page .pay-type {
        display: block;
        margin-top: .12rem;
        color: var(--pay-muted);
        font-size: .6rem;
        font-weight: 650;
        letter-spacing: .04em;
    }

    .payroll-page .pay-type.is-debet {
        color: var(--pay-danger-ink);
    }

    .payroll-page .pay-type.is-kredit {
        color: var(--pay-success-ink);
    }

    /* ── Empty state ────────────────────────────────────────── */
    .payroll-page .pay-empty {
        padding: 2.4rem 1.5rem;
        text-align: center;
    }

    .payroll-page .pay-empty > iconify-icon {
        color: var(--pay-muted);
        font-size: 2rem;
    }

    .payroll-page .pay-empty h3 {
        margin: .5rem 0 .2rem;
        color: var(--pay-ink);
        font-size: .95rem;
        font-weight: 650;
    }

    .payroll-page .pay-empty p {
        max-width: 46ch;
        margin: 0 auto .85rem;
        color: var(--pay-muted);
        font-size: .76rem;
        line-height: 1.5;
    }

    /* ── Modal ──────────────────────────────────────────────── */
    .payroll-page .modal-content {
        border-radius: 1rem;
        background: var(--pay-surface);
        color: var(--pay-ink);
    }

    .payroll-page .modal-header,
    .payroll-page .modal-footer {
        border-color: var(--pay-line);
    }

    .payroll-page .modal-title {
        color: var(--pay-ink);
        font-size: 1rem;
        font-weight: 650;
    }

    .payroll-page .modal-body {
        color: var(--pay-ink);
        font-size: .8rem;
    }

    .payroll-page .modal-footer.bg-light-subtle {
        background: transparent !important;
    }

    .payroll-page .form-label {
        color: var(--pay-ink);
        font-size: .74rem;
        font-weight: 600;
    }

    .payroll-page .form-control,
    .payroll-page .form-select {
        border-color: var(--pay-line);
        background-color: var(--pay-surface);
        color: var(--pay-ink);
        font-size: .78rem;
    }

    .payroll-page .form-control::placeholder {
        color: var(--pay-muted);
        opacity: 1;
    }

    .payroll-page .form-text {
        color: var(--pay-muted);
        font-size: .68rem;
    }

    .payroll-page .modal .alert-info {
        border: 0;
    }

    .payroll-page .modal .input-group-text {
        border-color: var(--pay-line);
        background: var(--bs-tertiary-bg);
        color: var(--pay-muted);
        font-size: .78rem;
    }

    .payroll-page .modal .form-control:focus,
    .payroll-page .modal .form-select:focus {
        border-color: var(--bs-primary);
        box-shadow: 0 0 0 .18rem rgba(var(--bs-primary-rgb), .16);
    }

    /* ── Browser surfaces ───────────────────────────────────── */
    .payroll-page ::selection {
        background: var(--bs-primary);
        color: #fff;
    }

    .payroll-page :focus-visible {
        outline: 2px solid var(--bs-primary);
        outline-offset: 2px;
    }

    /* ── Responsif ──────────────────────────────────────────── */
    @media (max-width: 1199.98px) {
        .payroll-page .pay-units-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .payroll-page .pay-unit:nth-child(3n + 1) {
            border-left: 0;
            padding-left: 0;
        }

        .payroll-page .pay-unit:nth-child(n + 4) {
            border-top: 1px solid var(--pay-line);
        }
    }

    @media (max-width: 991.98px) {
        .payroll-page .pay-head {
            grid-template-columns: 1fr;
        }

        .payroll-page .pay-head-side {
            align-items: flex-start;
        }

        .payroll-page .pay-period {
            flex-wrap: wrap;
        }

        .payroll-page .pay-actions {
            justify-content: flex-start;
        }

        .payroll-page .pay-phases {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .payroll-page .pay-phase:nth-child(3) {
            border-left: 0;
        }

        .payroll-page .pay-phase:nth-child(n+3) {
            border-top: 1px solid var(--pay-line);
        }
    }

    @media (max-width: 899.98px) {
        .payroll-page .pay-head {
            padding: 1rem 1rem .9rem;
        }

        .payroll-page .pay-title {
            font-size: 1.3rem;
        }

        .payroll-page .pay-run-head,
        .payroll-page .pay-units,
        .payroll-page .pay-panel-head,
        .payroll-page .pay-toolbar {
            padding-right: 1rem;
            padding-left: 1rem;
        }

        .payroll-page .pay-run-head {
            align-items: flex-start;
        }

        .payroll-page .pay-units-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .55rem 0;
        }

        .payroll-page .pay-unit {
            padding: .45rem .8rem;
        }

        .payroll-page .pay-unit:nth-child(odd) {
            border-left: 0;
            padding-left: 0;
        }

        .payroll-page .pay-unit:nth-child(even) {
            border-left: 1px solid var(--pay-line);
            padding-left: .8rem;
        }

        .payroll-page .pay-unit:nth-child(n+3) {
            border-top: 1px solid var(--pay-line);
        }

        .payroll-page .pay-toolbar {
            align-items: stretch;
        }

        .payroll-page .pay-search {
            flex-basis: 100%;
        }

        .payroll-page .pay-chips {
            flex-wrap: nowrap;
            max-width: 100%;
            overflow-x: auto;
            padding-bottom: .1rem;
            scrollbar-width: thin;
        }

        .payroll-page .pay-chip {
            flex: 0 0 auto;
        }

        .payroll-page .pay-result {
            margin-left: 0;
        }

        .payroll-page .pay-table-wrap {
            padding: .7rem;
        }

        .payroll-page .pay-table,
        .payroll-page .pay-table tbody,
        .payroll-page .pay-table tr,
        .payroll-page .pay-table td {
            display: block;
            width: 100%;
        }

        .payroll-page .pay-table thead {
            display: none;
        }

        .payroll-page .pay-table tr {
            margin-bottom: .6rem;
            padding: .7rem .8rem;
            border: 1px solid var(--pay-line);
            border-radius: .75rem;
        }

        .payroll-page .pay-table tr:last-child {
            margin-bottom: 0;
        }

        .payroll-page .pay-table td {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: .26rem 0;
            border: 0;
            text-align: right;
        }

        .payroll-page .pay-table td::before {
            flex: 0 0 auto;
            padding-top: .1rem;
            color: var(--pay-muted);
            content: attr(data-label);
            font-size: .62rem;
            font-weight: 650;
            letter-spacing: .04em;
            text-align: left;
            text-transform: uppercase;
        }

        .payroll-page .pay-table td.pay-cell-person {
            display: block;
            margin-bottom: .4rem;
            padding-bottom: .45rem;
            border-bottom: 1px solid var(--pay-line);
            text-align: left;
        }

        .payroll-page .pay-table td.pay-cell-person::before,
        .payroll-page .pay-table td.pay-cell-actions::before {
            display: none;
        }

        .payroll-page .pay-table td.pay-cell-note,
        .payroll-page .pay-table td.pay-cell-stack {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: flex-start;
            gap: .18rem;
            text-align: left;
        }

        .payroll-page .pay-table td.pay-cell-note::before,
        .payroll-page .pay-table td.pay-cell-stack::before {
            display: block;
            margin-bottom: .18rem;
        }

        .payroll-page .pay-table td.pay-cell-stack .pay-person-name {
            overflow: visible;
            text-overflow: clip;
            white-space: normal;
        }

        .payroll-page .pay-table td.pay-cell-actions {
            justify-content: flex-end;
            padding-top: .5rem;
        }

        .payroll-page .pay-amount {
            font-size: 1.05rem;
        }

        .payroll-page .pay-note {
            max-width: none;
            text-align: left;
        }

        .payroll-page .pay-row-actions {
            justify-content: flex-end;
        }

        .payroll-page .pay-icon-btn {
            width: 40px;
            height: 40px;
        }

        .payroll-page .pay-empty {
            padding: 2rem 1rem;
        }
    }

    @media (max-width: 575.98px) {
        .payroll-page .pay-period {
            align-items: stretch;
        }

        .payroll-page .pay-period label {
            width: 100%;
        }

        .payroll-page .pay-period .form-control {
            flex: 1 1 100%;
            width: 100%;
        }

        .payroll-page .pay-actions {
            width: 100%;
        }

        .payroll-page .pay-actions .btn {
            flex: 1 1 auto;
            justify-content: center;
        }

        .payroll-page .pay-panel-meta {
            width: 100%;
            margin-left: 0;
            text-align: left;
        }

        .payroll-page .pay-panel-meta b {
            display: inline;
            margin-left: .35rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .payroll-page *,
        .payroll-page *::before,
        .payroll-page *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: .01ms !important;
        }
    }

    /* Tombol kustomisasi template menutupi nominal/aksi di kanan bawah. */
    .customizer-btn {
        display: none !important;
    }
</style>
