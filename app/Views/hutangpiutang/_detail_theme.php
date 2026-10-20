<?php

/**
 * Tema halaman detail Hutang/Piutang.
 *
 * Token, tombol, tag, status, dan panel dasar dipakai dari `_list_theme.php`.
 * File ini hanya menambah komposisi khusus detail: ringkasan saldo, rincian
 * dokumen, riwayat pembayaran, dan jejak legacy.
 */
?>

<style>
    .hp-detail .hp-hero__main,
    .hp-detail .hp-detail__heading {
        min-width: 0;
    }

    .hp-detail .hp-detail__subline {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 0;
        align-items: center;
        max-width: none;
        margin-top: 2px;
        font-size: .78rem;
    }

    .hp-detail .hp-detail__subline > span + span::before {
        content: "·";
        margin: 0 6px;
        color: var(--hpl-muted);
        opacity: .55;
    }

    .hp-detail .hp-detail__code {
        padding: 2px 6px;
        color: var(--hpl-ink);
        background: var(--hpl-subtle);
        border: 1px solid var(--hpl-rule);
        border-radius: 5px;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .01em;
    }

    .hp-detail .hp-detail__badges {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
        margin-top: 10px;
    }

    .hp-detail .hp-detail__actions {
        justify-content: flex-end;
    }

    /* ===== Ringkasan saldo ===== */
    .hp-detail .hp-detail__balance {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(230px, .65fr);
        overflow: hidden;
        background: var(--hpl-surface);
        border: 1px solid var(--hpl-rule);
        border-radius: var(--hpl-radius);
        box-shadow: var(--hpl-shadow);
    }

    .hp-detail .hp-detail__balance-main {
        min-width: 0;
        padding: 22px 24px;
    }

    .hp-detail .hp-detail__balance-side {
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 10px 22px;
        background: var(--hpl-subtle);
        border-left: 1px solid var(--hpl-rule);
    }

    .hp-detail .hp-detail__eyebrow {
        margin-bottom: 6px;
        color: var(--hpl-muted);
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .hp-detail .hp-detail__balance-value {
        color: var(--hpl-accent-ink);
        font-size: 2.15rem;
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        letter-spacing: -.045em;
        line-height: 1.05;
    }

    .hp-detail .hp-detail__balance-value.is-clear {
        color: var(--hpl-muted);
    }

    .hp-detail .hp-detail__balance-sub {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 6px;
        color: var(--hpl-muted);
        font-size: .76rem;
    }

    .hp-detail .hp-detail__progress {
        max-width: 460px;
        margin-top: 16px;
    }

    .hp-detail .hp-detail__progress-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 7px;
        color: var(--hpl-muted);
        font-size: .72rem;
        font-variant-numeric: tabular-nums;
    }

    .hp-detail .hp-detail__progress-head strong {
        color: var(--hpl-ink);
    }

    .hp-detail .hp-detail__bar {
        height: 6px;
        overflow: hidden;
        background: var(--hpl-rule);
        border-radius: 999px;
    }

    .hp-detail .hp-detail__bar span {
        display: block;
        height: 100%;
        background: var(--hpl-accent-solid);
        border-radius: inherit;
        transition: width .35s ease;
    }

    .hp-detail .hp-detail__settlement {
        display: inline-flex;
        gap: 8px;
        align-items: center;
        margin-top: 16px;
        padding: 9px 11px;
        color: var(--hpl-warn-ink);
        background: var(--hpl-warn-soft);
        border-radius: var(--hpl-radius-sm);
        font-size: .76rem;
        line-height: 1.4;
    }

    .hp-detail .hp-detail__settlement iconify-icon {
        flex: none;
        font-size: 16px;
    }

    .hp-detail .hp-detail__side-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 10px 0;
    }

    .hp-detail .hp-detail__side-row + .hp-detail__side-row {
        border-top: 1px solid var(--hpl-rule);
    }

    .hp-detail .hp-detail__side-label {
        color: var(--hpl-muted);
        font-size: .66rem;
        font-weight: 700;
        letter-spacing: .055em;
        text-transform: uppercase;
    }

    .hp-detail .hp-detail__side-row .hp-due {
        text-align: right;
    }

    .hp-detail .hp-detail__side-row .hp-tag {
        text-align: right;
        white-space: normal;
    }

    .hp-detail .hp-detail__side-value {
        display: flex;
        flex-direction: column;
        gap: 2px;
        align-items: flex-end;
        text-align: right;
    }

    .hp-detail .hp-detail__side-value strong {
        color: var(--hpl-ink);
        font-size: .78rem;
        font-variant-numeric: tabular-nums;
    }

    .hp-detail .hp-detail__side-value small {
        color: var(--hpl-muted);
        font-size: .7rem;
    }

    .hp-detail .hp-detail__side-value-empty {
        color: var(--hpl-muted);
        font-size: .76rem;
    }

    /* ===== Catatan ===== */
    .hp-detail .hp-note {
        margin-top: 0;
    }

    .hp-detail .hp-note--warn {
        color: var(--hpl-warn-ink);
        background: var(--hpl-warn-soft);
    }

    .hp-detail .hp-note--warn iconify-icon {
        color: var(--hpl-warn-ink);
    }

    /* ===== Grid dokumen + riwayat ===== */
    .hp-detail .hp-detail__grid {
        display: grid;
        grid-template-columns: minmax(0, 1.08fr) minmax(0, .92fr);
        gap: 16px;
        align-items: start;
    }

    .hp-detail .hp-detail__stack {
        display: flex;
        flex-direction: column;
        gap: 16px;
        min-width: 0;
    }

    .hp-detail .hp-detail__panel {
        padding: 22px 24px;
    }

    .hp-detail .hp-detail__panel-head {
        display: flex;
        align-items: flex-start;
        gap: 11px;
        margin-bottom: 18px;
    }

    .hp-detail .hp-detail__panel-icon {
        display: grid;
        flex: none;
        width: 34px;
        height: 34px;
        place-items: center;
        color: var(--hpl-accent-ink);
        background: var(--hpl-accent-soft);
        border-radius: 10px;
        font-size: 17px;
    }

    .hp-detail .hp-detail__panel-head h2 {
        margin: 1px 0 3px;
        font-size: .98rem;
        font-weight: 700;
        letter-spacing: -.015em;
    }

    .hp-detail .hp-detail__panel-head p {
        margin: 0;
        color: var(--hpl-muted);
        font-size: .74rem;
        line-height: 1.45;
    }

    .hp-detail .hp-detail__panel-head > .hp-tag {
        margin-left: auto;
    }

    /* ===== Rincian ===== */
    .hp-detail .hp-detail__facts {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0 26px;
        margin: 0;
    }

    .hp-detail .hp-detail__fact {
        min-width: 0;
        padding: 10px 0;
        border-bottom: 1px solid var(--hpl-rule);
    }

    .hp-detail .hp-detail__fact--wide {
        grid-column: 1 / -1;
    }

    .hp-detail .hp-detail__fact dt {
        margin-bottom: 4px;
        color: var(--hpl-muted);
        font-size: .64rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .hp-detail .hp-detail__fact dd {
        margin: 0;
        color: var(--hpl-ink);
        font-size: .82rem;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .hp-detail .hp-detail__fact-note {
        color: var(--hpl-muted);
        font-size: .72rem;
    }

    .hp-detail .hp-detail__prose {
        white-space: pre-line;
    }

    .hp-detail .hp-detail__pair {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 18px;
        align-items: baseline;
    }

    .hp-detail .hp-detail__pair-item {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 5px;
        align-items: baseline;
        min-width: 0;
    }

    .hp-detail .hp-detail__pair-label {
        color: var(--hpl-muted);
        font-size: .62rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .hp-detail .hp-detail__pair a {
        color: var(--hpl-accent-ink);
        font-weight: 650;
        text-decoration: none;
    }

    .hp-detail .hp-detail__pair a:hover {
        text-decoration: underline;
    }

    .hp-detail .hp-detail__context {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        margin-top: 16px;
        padding: 12px 14px;
        color: var(--hpl-muted);
        background: var(--hpl-subtle);
        border-radius: var(--hpl-radius-sm);
        font-size: .76rem;
        line-height: 1.55;
    }

    .hp-detail .hp-detail__context iconify-icon {
        flex: none;
        margin-top: 1px;
        color: var(--hpl-accent-ink);
        font-size: 17px;
    }

    .hp-detail .hp-detail__notes .hp-detail__facts {
        grid-template-columns: 1fr;
    }

    .hp-detail .hp-detail__notes .hp-detail__prose {
        max-width: 74ch;
    }

    .hp-detail .hp-detail__notes .hp-detail__panel-head + .hp-detail__context {
        margin-top: 0;
    }

    /* ===== Riwayat ===== */
    .hp-detail .hp-detail__empty {
        padding: 34px 18px 38px;
        text-align: center;
    }

    .hp-detail .hp-detail__empty-icon {
        display: grid;
        width: 46px;
        height: 46px;
        margin: 0 auto 12px;
        place-items: center;
        color: var(--hpl-muted);
        background: var(--hpl-subtle);
        border-radius: 14px;
        font-size: 22px;
    }

    .hp-detail .hp-detail__empty h3 {
        margin: 0 0 4px;
        font-size: .88rem;
        font-weight: 700;
    }

    .hp-detail .hp-detail__empty p {
        max-width: 44ch;
        margin: 0 auto;
        color: var(--hpl-muted);
        font-size: .76rem;
        line-height: 1.55;
    }

    .hp-detail .hp-detail__timeline {
        position: relative;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .hp-detail .hp-detail__timeline-item {
        position: relative;
        display: grid;
        grid-template-columns: 14px minmax(0, 1fr);
        gap: 10px;
        padding-bottom: 18px;
    }

    .hp-detail .hp-detail__timeline-item:last-child {
        padding-bottom: 0;
    }

    .hp-detail .hp-detail__timeline-item::before {
        content: "";
        position: absolute;
        top: 9px;
        bottom: -3px;
        left: 5px;
        width: 1px;
        background: var(--hpl-rule);
    }

    .hp-detail .hp-detail__timeline-item:last-child::before {
        display: none;
    }

    .hp-detail .hp-detail__timeline-dot {
        position: relative;
        z-index: 1;
        width: 11px;
        height: 11px;
        margin-top: 4px;
        background: var(--hpl-surface);
        border: 2px solid var(--hpl-accent-solid);
        border-radius: 50%;
    }

    .hp-detail .hp-detail__timeline-body {
        min-width: 0;
    }

    .hp-detail .hp-detail__timeline-head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: 6px 12px;
    }

    .hp-detail .hp-detail__timeline-head strong {
        color: var(--hpl-ink);
        font-size: .92rem;
        font-variant-numeric: tabular-nums;
        letter-spacing: -.01em;
    }

    .hp-detail .hp-detail__timeline-head > span {
        color: var(--hpl-muted);
        font-size: .72rem;
    }

    .hp-detail .hp-detail__timeline-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
        margin-top: 5px;
        color: var(--hpl-muted);
        font-size: .72rem;
    }

    .hp-detail .hp-detail__timeline-note {
        margin: 7px 0 0;
        color: var(--hpl-muted);
        font-size: .74rem;
        line-height: 1.5;
    }

    /* ===== Jejak legacy ===== */
    .hp-detail .hp-detail__audit .hp-detail__panel-icon {
        color: var(--hpl-warn-ink);
        background: var(--hpl-warn-soft);
    }

    .hp-detail .hp-detail__audit.is-neutral .hp-detail__panel-icon {
        color: var(--hpl-muted);
        background: var(--hpl-subtle);
    }

    .hp-detail .hp-detail__audit-note {
        margin: 16px 0 0;
        padding-top: 14px;
        color: var(--hpl-muted);
        border-top: 1px solid var(--hpl-rule);
        font-size: .74rem;
        line-height: 1.55;
    }

    .hp-detail .hp-btn--warn {
        color: var(--hpl-warn-ink);
        background: transparent;
        border-color: var(--hpl-warn-ink);
    }

    .hp-detail .hp-btn--warn:hover,
    .hp-detail .hp-btn--warn:focus {
        color: var(--hpl-warn-ink);
        background: var(--hpl-warn-soft);
        border-color: var(--hpl-warn-ink);
    }

    /* ===== Modal ===== */
    .hp-detail .modal-content {
        overflow: hidden;
        border: 1px solid var(--hpl-rule);
        border-radius: var(--hpl-radius);
        box-shadow: 0 24px 70px -30px rgba(16, 24, 40, .5);
    }

    .hp-detail .modal-header,
    .hp-detail .modal-footer {
        border-color: var(--hpl-rule);
    }

    /* ===== Responsif ===== */
    @media (max-width: 991.98px) {
        .hp-detail .hp-detail__balance {
            grid-template-columns: 1fr;
        }

        .hp-detail .hp-detail__balance-side {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0 18px;
            padding: 10px 22px;
            border-top: 1px solid var(--hpl-rule);
            border-left: 0;
        }

        .hp-detail .hp-detail__side-row {
            flex-direction: column;
            align-items: flex-start;
            justify-content: flex-start;
            gap: 5px;
            padding: 8px 0;
        }

        .hp-detail .hp-detail__side-row + .hp-detail__side-row {
            padding-left: 18px;
            border-top: 0;
            border-left: 1px solid var(--hpl-rule);
        }

        .hp-detail .hp-detail__side-row .hp-due {
            text-align: left;
        }

        .hp-detail .hp-detail__side-row .hp-tag {
            text-align: left;
        }

        .hp-detail .hp-detail__side-value {
            align-items: flex-start;
            text-align: left;
        }

        .hp-detail .hp-detail__grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .hp-detail .hp-detail__balance-main {
            padding: 18px;
        }

        .hp-detail .hp-detail__balance-value {
            font-size: 1.8rem;
        }

        .hp-detail .hp-detail__balance-side {
            grid-template-columns: 1fr;
            padding: 4px 18px;
        }

        .hp-detail .hp-detail__side-row {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }

        .hp-detail .hp-detail__side-row + .hp-detail__side-row {
            padding-left: 0;
            border-top: 1px solid var(--hpl-rule);
            border-left: 0;
        }

        .hp-detail .hp-detail__side-row .hp-due,
        .hp-detail .hp-detail__side-row .hp-tag {
            text-align: right;
        }

        .hp-detail .hp-detail__side-value {
            align-items: flex-end;
            text-align: right;
        }

        .hp-detail .hp-detail__panel {
            padding: 18px;
        }

        .hp-detail .hp-detail__facts {
            grid-template-columns: 1fr;
            gap: 0;
        }

        .hp-detail .hp-detail__fact--wide {
            grid-column: auto;
        }

        .hp-detail .hp-detail__panel-head {
            margin-bottom: 14px;
        }

        .hp-detail .hp-detail__actions .hp-btn {
            flex: 1 1 auto;
        }

        .hp-detail .hp-detail__timeline-head {
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
        }
    }

    @media (max-width: 575.98px) {
        .hp-detail .hp-detail__balance-value {
            font-size: 1.6rem;
        }

        .hp-detail .hp-detail__subline {
            font-size: .74rem;
        }

        .hp-detail .hp-detail__badges {
            gap: 5px;
        }

        .hp-detail .hp-detail__panel-head {
            gap: 9px;
        }

        .hp-detail .hp-detail__panel-icon {
            width: 30px;
            height: 30px;
            border-radius: 9px;
            font-size: 15px;
        }
    }

    @media (forced-colors: active) {
        .hp-detail .hp-detail__balance,
        .hp-detail .hp-detail__balance-main,
        .hp-detail .hp-detail__balance-side,
        .hp-detail .hp-detail__fact {
            border-color: CanvasText;
        }
    }
</style>
