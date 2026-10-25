<?php

/**
 * Tema halaman Detail Klaim Garansi (/claim_garansi/detail).
 *
 * Semua selector berada di bawah `.cg-page` / `.cg-modal` agar tidak bocor ke
 * modul lain. Warna diturunkan dari token tema aplikasi; aksen biru hanya untuk
 * aksi utama, fokus, dan state terpilih. Kontras teks kecil dijaga >= 4.5:1
 * pada mode terang maupun gelap.
 */
?>

<style>
    /* ===== Token ===== */
    .cg-page,
    .cg-modal {
        --cg-surface: var(--bs-card-bg, #fff);
        --cg-subtle: #f7f9fc;
        --cg-rule: var(--bs-border-color, #e5eaf1);
        --cg-rule-strong: #d7dfea;
        --cg-hover: rgba(47, 95, 224, .04);
        --cg-ink: #24303f;
        --cg-strong: #142033;
        --cg-muted: #5b6672;
        --cg-accent: #2f5fe0;
        --cg-accent-solid: #2f5fe0;
        --cg-accent-hover: #244fc4;
        --cg-accent-ink: #244fc4;
        --cg-accent-soft: rgba(47, 95, 224, .08);
        --cg-success: #0f7a46;
        --cg-success-soft: #e8f7ef;
        --cg-success-solid: #0f7a46;
        --cg-warn: #8a5300;
        --cg-warn-soft: #fff4d6;
        --cg-danger: #b02a37;
        --cg-danger-soft: #fdecee;
        --cg-radius: 16px;
        --cg-radius-sm: 10px;
        --cg-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 18px 42px -36px rgba(16, 24, 40, .35);
    }

    html[data-bs-theme="dark"] .cg-page,
    html[data-bs-theme="dark"] .cg-modal {
        --cg-surface: var(--bs-card-bg, #2a3547);
        --cg-subtle: rgba(255, 255, 255, .035);
        --cg-rule: var(--bs-border-color, rgba(255, 255, 255, .12));
        --cg-rule-strong: rgba(255, 255, 255, .2);
        --cg-hover: rgba(255, 255, 255, .04);
        --cg-ink: var(--bs-body-color, #cfd8e3);
        --cg-strong: #f1f5f9;
        --cg-muted: #8fa0b8;
        --cg-accent: #8ab4ff;
        --cg-accent-solid: #2f6fe4;
        --cg-accent-hover: #3b7bf0;
        --cg-accent-ink: #a9c6ff;
        --cg-accent-soft: rgba(138, 180, 255, .12);
        --cg-success: #6fdd9d;
        --cg-success-soft: rgba(111, 221, 157, .12);
        --cg-success-solid: #167a4c;
        --cg-warn: #ffcf6e;
        --cg-warn-soft: rgba(255, 207, 110, .12);
        --cg-danger: #ff9aa2;
        --cg-danger-soft: rgba(255, 154, 162, .12);
        --cg-shadow: none;
    }

    .cg-page {
        max-width: 1180px;
        margin-inline: auto;
        color: var(--cg-ink);
        font-size: .875rem;
    }

    .cg-page .text-muted {
        color: var(--cg-muted) !important;
    }

    .cg-page ::selection {
        background: var(--cg-accent-soft);
    }

    /* ===== Hero ===== */
    .cg-hero {
        position: relative;
        overflow: hidden;
        padding: 1.35rem 1.5rem 1.5rem;
        border: 1px solid var(--cg-rule);
        border-radius: var(--cg-radius);
        background: var(--cg-surface);
        box-shadow: var(--cg-shadow);
        margin-bottom: 1rem;
    }

    .cg-hero::before {
        content: "";
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 3px;
        background: var(--cg-accent-solid);
    }

    .cg-hero__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.1rem;
    }

    .cg-crumb {
        display: flex;
        align-items: center;
        gap: .4rem;
        list-style: none;
        margin: 0;
        padding: 0;
        font-size: .78rem;
    }

    .cg-crumb li {
        color: var(--cg-muted);
    }

    .cg-crumb a {
        color: var(--cg-muted);
        text-decoration: none;
    }

    .cg-crumb a:hover {
        color: var(--cg-accent-ink);
    }

    .cg-back {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        min-height: 34px;
        padding: .3rem .7rem;
        border: 1px solid var(--cg-rule);
        border-radius: 999px;
        color: var(--cg-muted);
        font-size: .78rem;
        font-weight: 600;
        text-decoration: none;
        transition: color .16s ease, border-color .16s ease, background-color .16s ease;
    }

    .cg-back:hover {
        color: var(--cg-accent-ink);
        border-color: var(--cg-accent);
        background: var(--cg-accent-soft);
    }

    .cg-eyebrow {
        display: block;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .09em;
        text-transform: uppercase;
        color: var(--cg-accent-ink);
    }

    .cg-title {
        margin: .15rem 0 .7rem;
        font-size: clamp(1.45rem, 2.4vw, 1.9rem);
        line-height: 1.15;
        letter-spacing: -.025em;
        font-weight: 650;
        color: var(--cg-strong);
    }

    .cg-chips {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .4rem;
    }

    .cg-chip,
    .cg-status {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        min-height: 26px;
        padding: .2rem .6rem;
        border: 1px solid var(--cg-rule);
        border-radius: 999px;
        background: var(--cg-surface);
        color: var(--cg-muted);
        font-size: .75rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .cg-chip iconify-icon {
        color: var(--cg-muted);
    }

    .cg-chip--code {
        font-variant-numeric: tabular-nums;
        letter-spacing: .01em;
        color: var(--cg-strong);
    }

    .cg-status {
        border-color: transparent;
        background: var(--cg-success-soft);
        color: var(--cg-success);
    }

    .cg-status::before {
        content: "";
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: currentColor;
    }

    /* ===== Kartu ===== */
    .cg-card {
        padding: 1.15rem 1.35rem;
        border: 1px solid var(--cg-rule);
        border-radius: var(--cg-radius);
        background: var(--cg-surface);
        box-shadow: var(--cg-shadow);
        margin-bottom: 1rem;
    }

    .cg-card__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .9rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .cg-card__title {
        margin: 0;
        font-size: 1rem;
        font-weight: 650;
        letter-spacing: -.01em;
        color: var(--cg-strong);
    }

    .cg-card__sub {
        max-width: 64ch;
        margin: .2rem 0 0;
        color: var(--cg-muted);
        font-size: .8rem;
        line-height: 1.5;
    }

    .cg-count {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: .15rem .55rem;
        border-radius: 999px;
        background: var(--cg-accent-soft);
        color: var(--cg-accent-ink);
        font-size: .72rem;
        font-weight: 700;
        white-space: nowrap;
    }

    /* ===== Ringkasan service ===== */
    .cg-person {
        display: flex;
        align-items: center;
        gap: .85rem;
        flex-wrap: wrap;
        padding: .8rem .9rem;
        border: 1px solid var(--cg-rule);
        border-radius: var(--cg-radius-sm);
        background: var(--cg-subtle);
        margin-bottom: 1.1rem;
    }

    .cg-avatar {
        display: grid;
        place-items: center;
        flex-shrink: 0;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: var(--cg-accent-soft);
        color: var(--cg-accent-ink);
        font-size: 1rem;
        font-weight: 700;
    }

    .cg-person__name {
        display: block;
        color: var(--cg-strong);
        font-weight: 650;
        line-height: 1.3;
    }

    .cg-person__sub {
        display: flex;
        flex-wrap: wrap;
        gap: .3rem .9rem;
        margin-top: .15rem;
        color: var(--cg-muted);
        font-size: .78rem;
    }

    .cg-person__sub span {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
    }

    .cg-person__loc {
        margin-left: auto;
        color: var(--cg-muted);
        font-size: .78rem;
        font-weight: 600;
    }

    .cg-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: .9rem 1.25rem;
        margin: 0;
    }

    .cg-grid dt {
        margin-bottom: .2rem;
        color: var(--cg-muted);
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .cg-grid dd {
        margin: 0;
        color: var(--cg-strong);
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    .cg-notes {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: .7rem;
        margin-top: 1.1rem;
        padding-top: 1.1rem;
        border-top: 1px solid var(--cg-rule);
    }

    .cg-note {
        padding: .7rem .85rem;
        border-radius: var(--cg-radius-sm);
        background: var(--cg-subtle);
    }

    .cg-note span {
        display: block;
        margin-bottom: .2rem;
        color: var(--cg-muted);
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .cg-note p {
        margin: 0;
        color: var(--cg-ink);
        line-height: 1.5;
        white-space: pre-line;
    }

    /* ===== Kerusakan ===== */
    .cg-damage-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: .5rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .cg-damage-item {
        display: flex;
        align-items: flex-start;
        gap: .6rem;
        padding: .55rem .7rem;
        border: 1px solid var(--cg-rule);
        border-radius: var(--cg-radius-sm);
    }

    .cg-damage-item iconify-icon {
        flex-shrink: 0;
        margin-top: 1px;
        color: var(--cg-warn);
    }

    .cg-damage-item strong {
        display: block;
        color: var(--cg-strong);
        font-weight: 600;
        line-height: 1.35;
    }

    .cg-damage-item span {
        color: var(--cg-muted);
        font-size: .78rem;
    }

    /* ===== Langkah form ===== */
    .cg-step__main {
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        flex: 1 1 240px;
        min-width: 0;
    }

    .cg-step__num {
        display: grid;
        place-items: center;
        flex-shrink: 0;
        width: 30px;
        height: 30px;
        border-radius: 9px;
        background: var(--cg-accent-soft);
        color: var(--cg-accent-ink);
        font-size: .8rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .cg-step__body {
        flex: 1 1 auto;
        min-width: 0;
    }

    .cg-req {
        color: var(--cg-danger);
        font-size: .72rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .cg-page .form-control,
    .cg-page .form-select {
        border-color: var(--cg-rule-strong);
        border-radius: var(--cg-radius-sm);
        background-color: var(--cg-surface);
        color: var(--cg-ink);
        font-size: .85rem;
    }

    .cg-page .form-control::placeholder {
        color: var(--cg-muted);
        opacity: .8;
    }

    .cg-page .form-control:focus,
    .cg-page .form-select:focus {
        border-color: var(--cg-accent);
        box-shadow: 0 0 0 3px var(--cg-accent-soft);
    }

    .cg-page .form-check-input {
        border-color: var(--cg-rule-strong);
        cursor: pointer;
    }

    .cg-page .form-check-input:checked {
        background-color: var(--cg-accent-solid);
        border-color: var(--cg-accent-solid);
    }

    .cg-page .form-check-input:focus {
        border-color: var(--cg-accent);
        box-shadow: 0 0 0 3px var(--cg-accent-soft);
    }

    .cg-hint {
        display: flex;
        align-items: center;
        gap: .35rem;
        margin-top: .45rem;
        color: var(--cg-muted);
        font-size: .78rem;
    }

    .cg-scroll-hint {
        display: none;
        margin: .5rem 0 0;
        color: var(--cg-muted);
        font-size: .75rem;
    }

    /* ===== Tabel ===== */
    .cg-table {
        width: 100%;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .cg-table th {
        padding: .55rem .7rem;
        border-bottom: 1px solid var(--cg-rule);
        background: var(--cg-subtle);
        color: var(--cg-muted);
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        text-align: left;
        white-space: nowrap;
    }

    .cg-table th:first-child {
        border-top-left-radius: var(--cg-radius-sm);
    }

    .cg-table th:last-child {
        border-top-right-radius: var(--cg-radius-sm);
    }

    .cg-table td {
        padding: .6rem .7rem;
        border-bottom: 1px solid var(--cg-rule);
        color: var(--cg-ink);
        vertical-align: middle;
    }

    .cg-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .cg-table tbody tr:hover td {
        background: var(--cg-hover);
    }

    .cg-table tbody tr.is-selected td {
        background: var(--cg-accent-soft);
    }

    .cg-table .form-control-sm,
    .cg-table .form-select-sm {
        min-height: 32px;
        padding-block: .2rem;
    }

    .cg-qty {
        display: inline-grid;
        place-items: center;
        min-width: 30px;
        height: 26px;
        padding-inline: .4rem;
        border-radius: 7px;
        background: var(--cg-subtle);
        color: var(--cg-strong);
        font-size: .8rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .cg-empty {
        padding: 1.2rem 1rem;
        color: var(--cg-muted);
        text-align: center;
        font-size: .82rem;
    }

    /* ===== Tombol ===== */
    .cg-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .4rem;
        min-height: 40px;
        padding: .45rem .95rem;
        border: 1px solid transparent;
        border-radius: var(--cg-radius-sm);
        font-size: .84rem;
        font-weight: 600;
        line-height: 1;
        text-decoration: none;
        cursor: pointer;
        transition: background-color .16s ease, border-color .16s ease, color .16s ease, transform .16s ease;
    }

    .cg-btn:active {
        transform: translateY(1px);
    }

    .cg-btn:focus-visible {
        outline: 2px solid var(--cg-accent);
        outline-offset: 2px;
    }

    .cg-btn--primary {
        background: var(--cg-accent-solid);
        color: #fff;
    }

    .cg-btn--primary:hover,
    .cg-btn--primary:focus {
        background: var(--cg-accent-hover);
        color: #fff;
    }

    .cg-btn--save {
        background: var(--cg-success-solid);
        color: #fff;
    }

    .cg-btn--save:hover,
    .cg-btn--save:focus {
        background: #0b6238;
        color: #fff;
    }

    .cg-btn--ghost {
        border-color: var(--cg-rule-strong);
        background: transparent;
        color: var(--cg-ink);
    }

    .cg-btn--ghost:hover,
    .cg-btn--ghost:focus {
        border-color: var(--cg-accent);
        background: var(--cg-accent-soft);
        color: var(--cg-accent-ink);
    }

    /* ===== Action bar ===== */
    .cg-actionbar {
        position: sticky;
        bottom: 1rem;
        z-index: 20;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        padding: .75rem 1rem .75rem 1.15rem;
        border: 1px solid var(--cg-rule-strong);
        border-radius: var(--cg-radius);
        background: var(--cg-surface);
        box-shadow: 0 14px 34px -24px rgba(16, 24, 40, .45);
        margin-bottom: 1rem;
    }

    .cg-actionbar__note {
        display: flex;
        align-items: center;
        gap: .4rem;
        color: var(--cg-muted);
        font-size: .78rem;
    }

    .cg-actionbar__note iconify-icon {
        color: var(--cg-accent-ink);
    }

    .cg-actions {
        display: flex;
        gap: .5rem;
    }

    /* ===== Modal ===== */
    .cg-modal .modal-content {
        border: 1px solid var(--cg-rule);
        border-radius: var(--cg-radius);
        background: var(--cg-surface);
        color: var(--cg-ink);
        overflow: hidden;
    }

    .cg-modal .modal-header,
    .cg-modal .modal-footer {
        padding: .9rem 1.2rem;
        border-color: var(--cg-rule);
    }

    .cg-modal .modal-body {
        padding: 1rem 1.2rem 1.2rem;
    }

    .cg-modal__icon {
        display: grid;
        place-items: center;
        flex-shrink: 0;
        width: 38px;
        height: 38px;
        border-radius: var(--cg-radius-sm);
        background: var(--cg-accent-soft);
        color: var(--cg-accent-ink);
    }

    .cg-modal .modal-title {
        color: var(--cg-strong);
        font-size: .95rem;
        font-weight: 650;
    }

    .cg-modal .modal-title+p {
        margin: .1rem 0 0;
        color: var(--cg-muted);
        font-size: .78rem;
    }

    /* ===== Responsif ===== */
    @media (max-width: 767.98px) {
        .cg-hero {
            padding: 1.1rem 1rem 1.15rem;
        }

        .cg-card {
            padding: 1rem;
        }

        .cg-person__loc {
            margin-left: 0;
            flex-basis: 100%;
        }

        .cg-card__head > .cg-btn {
            width: 100%;
        }

        .cg-table--replacement {
            min-width: 640px;
        }

        .cg-table--damaged {
            min-width: 0;
        }

        .cg-table--damaged thead {
            display: none;
        }

        .cg-table--damaged tbody {
            display: flex;
            flex-direction: column;
            gap: .5rem;
        }

        .cg-table--damaged tbody tr {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: .35rem .6rem;
            align-items: center;
            padding: .65rem .7rem;
            border: 1px solid var(--cg-rule);
            border-radius: var(--cg-radius-sm);
        }

        .cg-table--damaged tbody tr.cg-empty-row {
            display: block;
            padding: .5rem 0;
            border: 0;
        }

        .cg-table--damaged td {
            padding: 0;
            border: 0;
        }

        .cg-table--damaged td:nth-child(1) {
            grid-area: 1 / 1;
        }

        .cg-table--damaged td:nth-child(2) {
            grid-area: 1 / 2;
            color: var(--cg-strong);
            font-weight: 600;
        }

        .cg-table--damaged td:nth-child(3) {
            grid-area: 1 / 3;
            text-align: right;
        }

        .cg-table--damaged td:nth-child(4) {
            grid-area: 2 / 1 / 3 / 4;
        }

        .cg-table--damaged tbody tr:hover td,
        .cg-table--damaged tbody tr.is-selected td {
            background: transparent;
        }

        .cg-table--damaged tbody tr.is-selected {
            border-color: var(--cg-accent);
            background: var(--cg-accent-soft);
        }

        .cg-scroll-hint {
            display: flex;
        }

        .cg-actionbar {
            position: static;
            flex-direction: column;
            align-items: stretch;
        }

        .cg-actions {
            flex-direction: column-reverse;
        }

        .cg-actions .cg-btn {
            width: 100%;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .cg-page *,
        .cg-modal * {
            transition-duration: .01ms !important;
        }
    }
</style>
