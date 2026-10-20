<?php

/**
 * Design system daftar Hutang/Piutang.
 *
 * Scoped under `.hp-list` supaya tidak bocor ke modul lain. Warna memakai
 * token tema aplikasi; aksen halaman hanya membedakan hutang (merah) dan
 * piutang (hijau). Kontras teks kecil dijaga >= 4.5:1 di terang dan gelap.
 */
?>

<style>
    .hp-list {
        --hpl-surface: var(--bs-card-bg, var(--bs-body-bg, #fff));
        --hpl-subtle: var(--bs-tertiary-bg, #f6f8fb);
        --hpl-rule: var(--bs-border-color, #e6ecf1);
        --hpl-muted: #5b6672;
        --hpl-ink: #626d7b;
        --hpl-accent-ink: #a3212a;
        --hpl-accent-solid: #b3261e;
        --hpl-accent-soft: rgba(179, 38, 30, .07);
        --hpl-danger-ink: #b02a37;
        --hpl-danger-soft: rgba(176, 42, 55, .10);
        --hpl-warn-ink: #8a5300;
        --hpl-warn-soft: rgba(192, 118, 0, .12);
        --hpl-ok-ink: #0f6b3e;
        --hpl-ok-soft: rgba(15, 122, 70, .10);
        --hpl-radius: 16px;
        --hpl-radius-sm: 10px;
        --hpl-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 18px 40px -32px rgba(16, 24, 40, .35);
        display: flex;
        flex-direction: column;
        gap: 16px;
        color: var(--hpl-ink);
    }

    .hp-list--piutang {
        --hpl-accent-ink: #0f6b3e;
        --hpl-accent-solid: #0f7a46;
        --hpl-accent-soft: rgba(15, 122, 70, .08);
    }

    html[data-bs-theme="dark"] .hp-list {
        --hpl-surface: var(--bs-card-bg, #1f2937);
        --hpl-subtle: rgba(255, 255, 255, .04);
        --hpl-muted: #8fa0b8;
        --hpl-ink: var(--bs-body-color, #cfd8e3);
        --hpl-accent-ink: #ff9aa2;
        --hpl-accent-solid: #a3212a;
        --hpl-accent-soft: rgba(255, 154, 162, .12);
        --hpl-danger-ink: #ff9aa2;
        --hpl-danger-soft: rgba(255, 154, 162, .12);
        --hpl-warn-ink: #ffcf6e;
        --hpl-warn-soft: rgba(255, 207, 110, .12);
        --hpl-ok-ink: #6fdd9d;
        --hpl-ok-soft: rgba(111, 221, 157, .12);
        --hpl-shadow: none;
    }

    html[data-bs-theme="dark"] .hp-list--piutang {
        --hpl-accent-ink: #7ee2a8;
        --hpl-accent-solid: #0f6b3e;
        --hpl-accent-soft: rgba(126, 226, 168, .12);
    }

    .hp-list h1,
    .hp-list h2,
    .hp-list h3 {
        color: var(--hpl-ink);
    }

    .hp-list .hp-btn,
    .hp-list .hp-chip,
    .hp-list .hp-control {
        font-family: inherit;
    }

    .hp-list .hp-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 36px;
        padding: 8px 12px;
        border: 1px solid transparent;
        border-radius: var(--hpl-radius-sm);
        font-size: .76rem;
        font-weight: 650;
        line-height: 1.2;
        text-decoration: none;
        white-space: nowrap;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease, transform .15s ease;
    }

    .hp-list .hp-btn iconify-icon {
        flex: none;
        font-size: 1.05em;
    }

    .hp-list .hp-btn--primary {
        color: #fff;
        background: var(--hpl-accent-solid);
        border-color: var(--hpl-accent-solid);
    }

    .hp-list .hp-btn--primary:hover,
    .hp-list .hp-btn--primary:focus {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 6px 14px -9px var(--hpl-accent-solid);
    }

    .hp-list .hp-btn--ghost {
        color: var(--hpl-ink);
        background: transparent;
        border-color: var(--hpl-rule);
    }

    .hp-list .hp-btn--ghost:hover,
    .hp-list .hp-btn--ghost:focus {
        color: var(--hpl-accent-ink);
        background: var(--hpl-accent-soft);
        border-color: var(--hpl-accent-ink);
    }

    .hp-list .hp-btn--icon {
        width: 32px;
        min-height: 32px;
        padding: 0;
    }

    .hp-list .hp-btn--sm {
        min-height: 30px;
        padding: 6px 9px;
        font-size: .72rem;
    }

    .hp-list .hp-btn:focus-visible,
    .hp-list .hp-chip-input:focus-visible + .hp-chip,
    .hp-list .hp-control:focus-visible,
    .hp-list .hp-action-note:focus-visible {
        outline: 2px solid var(--hpl-accent-ink);
        outline-offset: 2px;
    }

    /* ===== Hero ===== */
    .hp-list .hp-hero {
        position: relative;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        padding: 24px 26px;
        overflow: hidden;
        background: var(--hpl-surface);
        border: 1px solid var(--hpl-rule);
        border-radius: var(--hpl-radius);
        box-shadow: var(--hpl-shadow);
    }

    .hp-list .hp-hero::after {
        content: "";
        position: absolute;
        top: -120px;
        right: -90px;
        width: 280px;
        height: 280px;
        pointer-events: none;
        background: var(--hpl-accent-soft);
        border-radius: 50%;
    }

    .hp-list .hp-hero > * {
        position: relative;
        z-index: 1;
    }

    .hp-list .hp-crumb {
        margin-bottom: 12px;
        font-size: .72rem;
        color: var(--hpl-muted);
    }

    .hp-list .hp-crumb ol {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .hp-list .hp-crumb li {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .hp-list .hp-crumb li + li::before {
        content: "/";
        color: var(--hpl-rule);
    }

    .hp-list .hp-crumb a {
        color: var(--hpl-muted);
        text-decoration: none;
    }

    .hp-list .hp-crumb a:hover {
        color: var(--hpl-accent-ink);
    }

    .hp-list .hp-hero__title-row {
        display: flex;
        align-items: flex-start;
        gap: 14px;
    }

    .hp-list .hp-hero__mark {
        display: grid;
        flex: none;
        width: 48px;
        height: 48px;
        place-items: center;
        color: var(--hpl-accent-ink);
        background: var(--hpl-accent-soft);
        border-radius: 14px;
        font-size: 24px;
    }

    .hp-list .hp-hero h1 {
        margin: 1px 0 5px;
        font-size: 1.55rem;
        font-weight: 700;
        line-height: 1.15;
        letter-spacing: -.035em;
    }

    .hp-list .hp-hero p {
        max-width: 58ch;
        margin: 0;
        color: var(--hpl-muted);
        font-size: .86rem;
        line-height: 1.55;
        text-wrap: pretty;
    }

    .hp-list .hp-hero__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        margin-left: auto;
    }

    /* ===== Ringkasan ===== */
    .hp-list .hp-board {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1px;
        overflow: hidden;
        background: var(--hpl-rule);
        border: 1px solid var(--hpl-rule);
        border-radius: var(--hpl-radius);
        box-shadow: var(--hpl-shadow);
    }

    .hp-list .hp-board__cell {
        min-width: 0;
        padding: 18px 20px;
        background: var(--hpl-surface);
    }

    .hp-list .hp-board__label {
        margin-bottom: 7px;
        color: var(--hpl-muted);
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .hp-list .hp-board__value {
        color: var(--hpl-ink);
        font-size: 1.35rem;
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        letter-spacing: -.03em;
        line-height: 1.1;
    }

    .hp-list .hp-board__value small {
        color: var(--hpl-muted);
        font-size: .7rem;
        font-weight: 600;
        letter-spacing: 0;
    }

    .hp-list .hp-board__value.is-accent {
        color: var(--hpl-accent-ink);
    }

    .hp-list .hp-board__value.is-danger {
        color: var(--hpl-danger-ink);
    }

    .hp-list .hp-board__note {
        margin-top: 6px;
        color: var(--hpl-muted);
        font-size: .71rem;
        line-height: 1.4;
    }

    /* ===== Filter ===== */
    .hp-list .hp-panel {
        background: var(--hpl-surface);
        border: 1px solid var(--hpl-rule);
        border-radius: var(--hpl-radius);
        box-shadow: var(--hpl-shadow);
    }

    .hp-list .hp-filter {
        padding: 16px 18px;
    }

    .hp-list .hp-filter__top {
        display: grid;
        grid-template-columns: minmax(240px, 1.5fr) minmax(150px, .7fr) minmax(150px, .7fr) auto;
        gap: 12px;
        align-items: end;
    }

    .hp-list .hp-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
    }

    .hp-list .hp-field > label,
    .hp-list .hp-mini > span {
        color: var(--hpl-muted);
        font-size: .67rem;
        font-weight: 700;
        letter-spacing: .055em;
        text-transform: uppercase;
    }

    .hp-list .hp-field--action {
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }

    .hp-list .hp-control {
        width: 100%;
        height: 38px;
        padding: 7px 10px;
        color: var(--hpl-ink);
        background: var(--hpl-surface);
        border: 1px solid var(--hpl-rule);
        border-radius: var(--hpl-radius-sm);
        font-size: .8rem;
    }

    .hp-list .hp-control::placeholder {
        color: var(--hpl-muted);
        opacity: .85;
    }

    .hp-list .hp-control:focus {
        color: var(--hpl-ink);
        background: var(--hpl-surface);
        border-color: var(--hpl-accent-ink);
        box-shadow: 0 0 0 3px var(--hpl-accent-soft);
    }

    .hp-list .hp-control--sm {
        height: 32px;
        padding: 4px 26px 4px 9px;
        font-size: .74rem;
    }

    .hp-list .hp-input-icon {
        position: relative;
    }

    .hp-list .hp-input-icon iconify-icon {
        position: absolute;
        top: 50%;
        left: 12px;
        z-index: 1;
        color: var(--hpl-muted);
        font-size: 15px;
        pointer-events: none;
        transform: translateY(-50%);
    }

    .hp-list .hp-input-icon .hp-control {
        padding-left: 35px;
    }

    .hp-list .hp-filter__bottom {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        align-items: center;
        justify-content: space-between;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid var(--hpl-rule);
    }

    .hp-list .hp-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .hp-list .hp-chip-input {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip-path: inset(50%);
        white-space: nowrap;
    }

    .hp-list .hp-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 11px;
        color: var(--hpl-muted);
        background: transparent;
        border: 1px solid var(--hpl-rule);
        border-radius: 999px;
        cursor: pointer;
        font-size: .74rem;
        font-weight: 650;
        line-height: 1;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }

    .hp-list .hp-chip:hover {
        color: var(--hpl-accent-ink);
        border-color: var(--hpl-accent-ink);
    }

    .hp-list .hp-chip-input:checked + .hp-chip {
        color: #fff;
        background: var(--hpl-accent-solid);
        border-color: var(--hpl-accent-solid);
    }

    .hp-list .hp-inline {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }

    .hp-list .hp-mini {
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 0;
    }

    .hp-list .hp-mini > span {
        letter-spacing: 0;
        text-transform: none;
    }

    .hp-list .hp-note {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        margin-top: 12px;
        padding: 10px 12px;
        color: var(--hpl-muted);
        background: var(--hpl-subtle);
        border-radius: var(--hpl-radius-sm);
        font-size: .76rem;
        line-height: 1.5;
    }

    .hp-list .hp-note iconify-icon {
        flex: none;
        margin-top: 2px;
        color: var(--hpl-accent-ink);
        font-size: 16px;
    }

    .hp-list .hp-note a {
        color: var(--hpl-accent-ink);
        font-weight: 650;
        text-decoration: none;
    }

    .hp-list .hp-note a:hover {
        text-decoration: underline;
    }

    /* ===== Tabel ===== */
    .hp-list .hp-table-card {
        overflow: hidden;
    }

    .hp-list .hp-table-card__head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        padding: 18px 20px 14px;
    }

    .hp-list .hp-table-card__head h2 {
        margin: 0;
        font-size: .95rem;
        font-weight: 700;
        letter-spacing: -.015em;
    }

    .hp-list .hp-table-card__head p {
        margin: 4px 0 0;
        color: var(--hpl-muted);
        font-size: .73rem;
        line-height: 1.45;
    }

    .hp-list .hp-table-card__sort {
        display: inline-flex;
        flex: none;
        gap: 5px;
        align-items: center;
        color: var(--hpl-muted);
        font-size: .71rem;
        font-weight: 600;
    }

    .hp-list .hp-table-wrap {
        overflow-x: auto;
        scrollbar-width: thin;
    }

    .hp-list .hp-table {
        width: 100%;
        min-width: 860px;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
        color: var(--hpl-ink);
        font-size: .8rem;
    }

    .hp-list .hp-table th {
        padding: 10px 16px;
        color: var(--hpl-muted);
        background: var(--hpl-subtle);
        border-top: 1px solid var(--hpl-rule);
        border-bottom: 1px solid var(--hpl-rule);
        font-size: .66rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .hp-list .hp-table td {
        padding: 14px 16px;
        vertical-align: middle;
        border-bottom: 1px solid var(--hpl-rule);
    }

    .hp-list .hp-table th:first-child,
    .hp-list .hp-table td:first-child {
        padding-left: 20px;
    }

    .hp-list .hp-table th:last-child,
    .hp-list .hp-table td:last-child {
        padding-right: 20px;
    }

    .hp-list .hp-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .hp-list .hp-table tbody tr:hover td {
        background: var(--hpl-subtle);
    }

    .hp-list .hp-table th:nth-child(1) { width: 20%; }
    .hp-list .hp-table th:nth-child(2) { width: 24%; }
    .hp-list .hp-table th:nth-child(3) { width: 15%; }
    .hp-list .hp-table th:nth-child(4) { width: 19%; }
    .hp-list .hp-table th:nth-child(5) { width: 11%; }

    .hp-list .hp-cell-actions {
        min-width: 176px;
    }

    .hp-list .hp-doc {
        display: flex;
        flex-direction: column;
        gap: 3px;
        min-width: 0;
    }

    .hp-list .hp-doc__code {
        color: var(--hpl-ink);
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: -.01em;
        overflow-wrap: anywhere;
    }

    .hp-list .hp-doc__meta {
        color: var(--hpl-muted);
        font-size: .69rem;
        line-height: 1.4;
    }

    .hp-list .hp-party {
        display: flex;
        flex-direction: column;
        gap: 5px;
        min-width: 0;
    }

    .hp-list .hp-party__name {
        color: var(--hpl-ink);
        font-size: .82rem;
        font-weight: 650;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .hp-list .hp-party__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        align-items: center;
    }

    .hp-list .hp-tag {
        display: inline-flex;
        align-items: center;
        padding: 3px 7px;
        color: var(--hpl-muted);
        background: var(--hpl-subtle);
        border: 1px solid var(--hpl-rule);
        border-radius: 6px;
        font-size: .66rem;
        font-weight: 600;
        line-height: 1.2;
        white-space: nowrap;
    }

    .hp-list .hp-tag--warn {
        color: var(--hpl-warn-ink);
        background: var(--hpl-warn-soft);
        border-color: transparent;
    }

    .hp-list .hp-due {
        display: flex;
        flex-direction: column;
        gap: 2px;
        font-variant-numeric: tabular-nums;
    }

    .hp-list .hp-due__date {
        color: var(--hpl-ink);
        font-size: .78rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .hp-list .hp-due small {
        color: var(--hpl-muted);
        font-size: .68rem;
        font-weight: 600;
        line-height: 1.35;
    }

    .hp-list .hp-due.is-overdue small {
        color: var(--hpl-danger-ink);
    }

    .hp-list .hp-due.is-today small,
    .hp-list .hp-due.is-soon small {
        color: var(--hpl-warn-ink);
    }

    .hp-list .hp-money {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .hp-list .hp-money__sisa {
        display: block;
        color: var(--hpl-ink);
        font-size: .87rem;
        font-weight: 700;
        letter-spacing: -.01em;
    }

    .hp-list .hp-money__sisa.is-zero {
        color: var(--hpl-muted);
        font-weight: 600;
    }

    .hp-list .hp-money__meta {
        display: block;
        margin-top: 2px;
        color: var(--hpl-muted);
        font-size: .67rem;
        line-height: 1.35;
    }

    .hp-list .hp-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: .68rem;
        font-weight: 700;
        line-height: 1.3;
        white-space: nowrap;
    }

    .hp-list .hp-status::before {
        content: "";
        width: 6px;
        height: 6px;
        background: currentColor;
        border-radius: 50%;
    }

    .hp-list .hp-status.is-neutral {
        color: var(--hpl-muted);
        background: var(--hpl-subtle);
    }

    .hp-list .hp-status.is-warning {
        color: var(--hpl-warn-ink);
        background: var(--hpl-warn-soft);
    }

    .hp-list .hp-status.is-success {
        color: var(--hpl-ok-ink);
        background: var(--hpl-ok-soft);
    }

    .hp-list .hp-actions {
        display: flex;
        flex-direction: column;
        gap: 6px;
        align-items: flex-end;
    }

    .hp-list .hp-actions__main {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
        justify-content: flex-end;
    }

    .hp-list .hp-action-note {
        display: inline-flex;
        gap: 4px;
        align-items: center;
        color: var(--hpl-warn-ink);
        font-size: .69rem;
        font-weight: 650;
        text-decoration: none;
    }

    .hp-list .hp-action-note:hover {
        text-decoration: underline;
    }

    /* ===== Empty state ===== */
    .hp-list .hp-empty {
        padding: 52px 24px 58px;
        text-align: center;
        border-top: 1px solid var(--hpl-rule);
    }

    .hp-list .hp-empty__icon {
        display: grid;
        width: 52px;
        height: 52px;
        margin: 0 auto 14px;
        place-items: center;
        color: var(--hpl-accent-ink);
        background: var(--hpl-accent-soft);
        border-radius: 16px;
        font-size: 26px;
    }

    .hp-list .hp-empty h3 {
        margin: 0 0 4px;
        font-size: .95rem;
        font-weight: 700;
    }

    .hp-list .hp-empty p {
        max-width: 42ch;
        margin: 0 auto 16px;
        color: var(--hpl-muted);
        font-size: .8rem;
        line-height: 1.55;
    }

    /* ===== Responsif ===== */
    @media (max-width: 1199.98px) {
        .hp-list .hp-filter__top {
            grid-template-columns: minmax(220px, 1.4fr) repeat(2, minmax(140px, .7fr));
        }

        .hp-list .hp-field--action {
            grid-column: 1 / -1;
            justify-content: flex-end;
        }
    }

    @media (max-width: 991.98px) {
        .hp-list .hp-hero {
            flex-direction: column;
        }

        .hp-list .hp-hero__actions {
            margin-left: 0;
        }

        .hp-list .hp-board {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .hp-list .hp-filter__top {
            grid-template-columns: 1fr 1fr;
        }

        .hp-list .hp-field--search {
            grid-column: 1 / -1;
        }

        .hp-list .hp-field--action {
            justify-content: flex-start;
        }
    }

    @media (max-width: 767.98px) {
        .hp-list {
            gap: 12px;
        }

        .hp-list .hp-hero {
            padding: 20px;
        }

        .hp-list .hp-hero__title-row {
            gap: 12px;
        }

        .hp-list .hp-hero__mark {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            font-size: 21px;
        }

        .hp-list .hp-hero h1 {
            font-size: 1.35rem;
        }

        .hp-list .hp-hero__actions {
            width: 100%;
        }

        .hp-list .hp-hero__actions .hp-btn {
            flex: 1 1 auto;
        }

        .hp-list .hp-filter {
            padding: 14px;
        }

        .hp-list .hp-filter__top {
            grid-template-columns: 1fr;
        }

        .hp-list .hp-field--search,
        .hp-list .hp-field--action {
            grid-column: auto;
        }

        .hp-list .hp-filter__bottom {
            align-items: stretch;
        }

        .hp-list .hp-inline {
            width: 100%;
        }

        .hp-list .hp-mini {
            flex: 1 1 140px;
            justify-content: space-between;
        }

        .hp-list .hp-table {
            min-width: 0;
        }

        .hp-list .hp-table thead {
            display: none;
        }

        .hp-list .hp-table,
        .hp-list .hp-table tbody,
        .hp-list .hp-table tr,
        .hp-list .hp-table td {
            display: block;
            width: 100%;
        }

        .hp-list .hp-table tbody tr {
            padding: 16px 16px 14px;
            border-bottom: 1px solid var(--hpl-rule);
        }

        .hp-list .hp-table tbody tr:last-child {
            border-bottom: 0;
        }

        .hp-list .hp-table tbody td {
            display: grid;
            grid-template-columns: 88px minmax(0, 1fr);
            gap: 8px;
            align-items: start;
            padding: 5px 0;
            border: 0;
        }

        .hp-list .hp-table tbody td::before {
            content: attr(data-label);
            padding-top: 3px;
            color: var(--hpl-muted);
            font-size: .64rem;
            font-weight: 700;
            letter-spacing: .055em;
            text-transform: uppercase;
        }

        .hp-list .hp-table tbody td.hp-cell-doc,
        .hp-list .hp-table tbody td.hp-cell-actions {
            grid-template-columns: 1fr;
        }

        .hp-list .hp-table tbody td.hp-cell-doc::before,
        .hp-list .hp-table tbody td.hp-cell-actions::before {
            display: none;
        }

        .hp-list .hp-table tbody td.hp-cell-doc {
            padding-bottom: 9px;
        }

        .hp-list .hp-table tbody td.hp-cell-actions {
            padding-top: 10px;
        }

        .hp-list .hp-cell-doc .hp-doc__code {
            font-size: .95rem;
        }

        .hp-list .hp-money {
            text-align: left;
        }

        .hp-list .hp-actions {
            align-items: flex-start;
        }

        .hp-list .hp-actions__main {
            justify-content: flex-start;
        }

        .hp-list .hp-table-card__head {
            align-items: flex-start;
            padding: 16px 16px 12px;
        }

        .hp-list .hp-table-card__sort {
            display: none;
        }

        .hp-list .hp-empty {
            padding: 40px 20px 44px;
        }
    }

    @media (max-width: 575.98px) {
        .hp-list .hp-board__cell {
            padding: 14px 16px;
        }

        .hp-list .hp-board__value {
            font-size: 1.08rem;
        }

        .hp-list .hp-board__note {
            font-size: .67rem;
        }

        .hp-list .hp-crumb {
            font-size: .68rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .hp-list *,
        .hp-list *::before,
        .hp-list *::after {
            transition-duration: .01ms !important;
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
        }
    }

    @media (forced-colors: active) {
        .hp-list .hp-hero,
        .hp-list .hp-panel,
        .hp-list .hp-board,
        .hp-list .hp-board__cell,
        .hp-list .hp-table td,
        .hp-list .hp-table th {
            border-color: CanvasText;
        }

        .hp-list .hp-btn,
        .hp-list .hp-chip,
        .hp-list .hp-control {
            border: 1px solid ButtonText;
        }
    }
</style>
