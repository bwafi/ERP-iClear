<?php

/**
 * Surface tambahan untuk /mutasi_stok/masuk.
 *
 * Token, panel, tombol, dan modal dasar datang dari _mutasi_theme.php.
 * File ini hanya menambah komponen yang khas halaman penerimaan: filter,
 * tabel antrean, panel rincian, dan isi modal terima/batal.
 */
?>

<style>
    .mutasi-masuk {
        --mm-danger-solid: #b02a37;
        --mm-danger-hover: #92232d;
    }

    html[data-bs-theme="dark"] .mutasi-masuk {
        --mm-danger-solid: #c93645;
        --mm-danger-hover: #b02a37;
    }

    /* ===== Pesan ===== */
    .mm-flash {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 14px;
        padding: 11px 14px;
        border-radius: 10px;
        font-size: .8125rem;
        line-height: 1.45;
    }

    .mm-flash iconify-icon {
        flex: none;
        margin-top: 1px;
        font-size: 1rem;
    }

    .mm-flash--ok {
        background: var(--ms-ok-soft);
        color: var(--ms-ok-ink);
    }

    .mm-flash--danger {
        background: var(--ms-danger-soft);
        color: var(--ms-danger-ink);
    }

    /* ===== Filter ===== */
    .mm-filter form {
        padding: 18px 20px;
    }

    .mm-filter__top {
        display: flex;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 14px;
    }

    .mm-field {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .mm-field--search {
        flex: 1 1 240px;
        max-width: 380px;
    }

    .mm-field--unit {
        flex: 0 1 220px;
    }

    .mm-field--dates {
        flex: 1 1 300px;
    }

    .mm-field--actions {
        display: flex;
        flex: 0 0 auto;
        flex-direction: row;
        margin-left: auto;
        gap: 8px;
    }

    .mm-field--actions .ms-btn {
        min-height: 42px;
    }

    .mm-input-icon {
        position: relative;
    }

    .mm-input-icon iconify-icon {
        position: absolute;
        top: 50%;
        left: 12px;
        transform: translateY(-50%);
        color: var(--ms-muted);
        font-size: 1rem;
        pointer-events: none;
    }

    .mm-input-icon .ms-control {
        padding-left: 38px;
    }

    .mm-daterange {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .mm-daterange .ms-control {
        flex: 1 1 0;
        min-width: 0;
    }

    .mm-daterange__sep {
        color: var(--ms-muted);
        font-size: .75rem;
    }

    .mm-filter__bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 16px;
        padding-top: 14px;
        border-top: 1px solid var(--ms-rule);
    }

    .mm-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .mm-chip-input {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip-path: inset(50%);
        white-space: nowrap;
    }

    .mm-chip {
        display: inline-flex;
        align-items: center;
        min-height: 32px;
        padding: 6px 12px;
        border: 1px solid var(--ms-rule-strong);
        border-radius: 999px;
        color: var(--ms-muted);
        font-size: .75rem;
        font-weight: 650;
        line-height: 1;
        cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }

    .mm-chip:hover {
        border-color: var(--ms-accent);
        background: var(--ms-accent-soft);
        color: var(--ms-accent-ink);
    }

    .mm-chip-input:checked + .mm-chip {
        border-color: var(--ms-accent-solid);
        background: var(--ms-accent-solid);
        color: #fff;
    }

    .mm-chip-input:focus-visible + .mm-chip {
        outline: 2px solid var(--ms-accent);
        outline-offset: 2px;
    }

    .mm-filter__hint {
        margin: 0;
        font-size: .72rem;
        color: var(--ms-muted);
    }

    /* ===== Hasil ===== */
    .mm-count {
        display: inline-flex;
        align-items: baseline;
        gap: 5px;
        padding: 5px 10px;
        border: 1px solid var(--ms-rule);
        border-radius: 999px;
        background: var(--ms-subtle);
        color: var(--ms-muted);
        font-size: .72rem;
        white-space: nowrap;
    }

    .mm-count strong {
        color: var(--ms-strong);
        font-size: .875rem;
        font-variant-numeric: tabular-nums;
    }

    .mm-note {
        display: block;
        color: var(--ms-strong);
        font-weight: 650;
        font-variant-numeric: tabular-nums;
    }

    .mm-note-id {
        display: block;
        margin-top: 1px;
        color: var(--ms-muted);
        font-size: .7rem;
    }

    .mm-date {
        color: var(--ms-ink);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .mm-route {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        min-width: 0;
    }

    .mm-route__from {
        color: var(--ms-strong);
        font-weight: 650;
    }

    .mm-route__arrow {
        flex: none;
        color: var(--ms-muted);
        font-size: .85rem;
    }

    .mm-route__to {
        color: var(--ms-ink);
    }

    .mm-value {
        color: var(--ms-strong);
        font-weight: 650;
        white-space: nowrap;
    }

    .mm-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 700;
        line-height: 1.4;
        white-space: nowrap;
    }

    .mm-status::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    .mm-status--pending {
        background: var(--ms-warn-soft);
        color: var(--ms-warn-ink);
    }

    .mm-status--done {
        background: var(--ms-ok-soft);
        color: var(--ms-ok-ink);
    }

    .mm-status-date {
        display: block;
        margin-top: 3px;
        color: var(--ms-muted);
        font-size: .7rem;
        white-space: nowrap;
    }

    .mm-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
        flex-wrap: wrap;
    }

    .mm-table .ms-btn {
        min-height: 32px;
        padding: 6px 10px;
        border-radius: 8px;
        font-size: .75rem;
    }

    .mm-caret {
        font-size: .85rem;
        transition: transform .18s ease;
    }

    .mm-detail-toggle[aria-expanded="true"] .mm-caret {
        transform: rotate(180deg);
    }

    .mutasi-masuk .ms-btn--danger {
        background: var(--mm-danger-solid);
        border-color: var(--mm-danger-solid);
        color: #fff;
    }

    .mutasi-masuk .ms-btn--danger:hover,
    .mutasi-masuk .ms-btn--danger:focus {
        background: var(--mm-danger-hover);
        border-color: var(--mm-danger-hover);
        color: #fff;
    }

    .mutasi-masuk .ms-btn--danger-ghost {
        border-color: var(--ms-rule-strong);
        background: transparent;
        color: var(--ms-danger-ink);
    }

    .mutasi-masuk .ms-btn--danger-ghost:hover,
    .mutasi-masuk .ms-btn--danger-ghost:focus {
        border-color: var(--ms-danger-ink);
        background: var(--ms-danger-soft);
        color: var(--ms-danger-ink);
    }

    /* Tabel daftar tidak perlu header lengket: halaman ini ikut scroll dokumen. */
    .mm-table th {
        position: static;
    }

    .mm-table td {
        padding-top: 11px;
        padding-bottom: 11px;
    }

    .mm-table .mm-detail-row:hover td {
        background: transparent;
    }

    .mm-detail-row > td {
        padding: 0 16px 14px;
        background: var(--ms-surface);
    }

    .mm-detail {
        padding: 14px 16px;
        border: 1px solid var(--ms-rule);
        border-radius: 12px;
        background: var(--ms-subtle);
    }

    .mm-detail__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
        color: var(--ms-muted);
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .mm-detail__scroll {
        overflow-x: auto;
        scrollbar-width: thin;
    }

    .mm-lines {
        width: 100%;
        min-width: 480px;
        border-collapse: collapse;
        font-size: .78rem;
    }

    .mm-lines th {
        padding: 6px 8px;
        border-bottom: 1px solid var(--ms-rule);
        color: var(--ms-muted);
        font-size: .66rem;
        letter-spacing: .05em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .mm-lines td {
        padding: 7px 8px;
        border-bottom: 1px solid var(--ms-rule);
        color: var(--ms-ink);
    }

    .mm-lines tr:last-child td {
        border-bottom: 0;
    }

    .mm-lines .mm-money {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .mm-lines-empty {
        padding: 16px 8px !important;
        color: var(--ms-muted) !important;
        text-align: center;
    }

    .mm-detail__total {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 8px;
        color: var(--ms-muted);
        font-size: .78rem;
    }

    .mm-detail__total strong {
        color: var(--ms-strong);
        font-variant-numeric: tabular-nums;
    }

    /* ===== Footer daftar ===== */
    .mm-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        padding: 14px 20px;
        border-top: 1px solid var(--ms-rule);
        background: var(--ms-subtle);
    }

    .mm-foot__text {
        color: var(--ms-muted);
        font-size: .75rem;
    }

    .mm-foot__text strong {
        color: var(--ms-strong);
        font-variant-numeric: tabular-nums;
    }

    .mm-pagination {
        display: flex;
        flex-wrap: wrap;
        gap: 3px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .mm-pagination .page-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        height: 30px;
        padding: 0 7px;
        border: 1px solid var(--ms-rule);
        border-radius: 8px;
        background: transparent;
        color: var(--ms-accent-ink);
        font-size: .75rem;
        text-decoration: none;
    }

    .mm-pagination .page-link:hover {
        border-color: var(--ms-accent);
        background: var(--ms-accent-soft);
        color: var(--ms-accent-ink);
    }

    .mm-pagination .page-item.active .page-link {
        border-color: var(--ms-accent-solid);
        background: var(--ms-accent-solid);
        color: #fff;
    }

    .mm-pagination .page-item.disabled .page-link {
        opacity: .5;
        color: var(--ms-muted);
        pointer-events: none;
    }

    .mm-pagination iconify-icon {
        font-size: 1rem;
    }

    /* ===== Isi modal ===== */
    .ms-modal .mm-modal-body {
        padding: 0 22px 22px;
    }

    .mm-receipt {
        display: grid;
        grid-template-columns: 1fr 1.2fr;
        margin-bottom: 16px;
        border: 1px solid var(--ms-rule);
        border-radius: 12px;
        background: var(--ms-subtle);
        overflow: hidden;
    }

    .mm-receipt__cell {
        min-width: 0;
        padding: 14px 16px;
    }

    .mm-receipt__cell + .mm-receipt__cell {
        border-left: 1px solid var(--ms-rule);
    }

    .mm-receipt--stack {
        grid-template-columns: 1fr;
    }

    .mm-receipt--stack .mm-receipt__cell + .mm-receipt__cell {
        border-top: 1px solid var(--ms-rule);
        border-left: 0;
    }

    .mm-kicker {
        display: block;
        margin-bottom: 4px;
        color: var(--ms-muted);
        font-size: .66rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .mm-receipt__cell > strong {
        display: block;
        color: var(--ms-strong);
        font-size: .875rem;
    }

    .mm-receipt__meta {
        display: block;
        margin-top: 4px;
        color: var(--ms-muted);
        font-size: .72rem;
    }

    .mm-receipt__route {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .mm-receipt__route > span {
        min-width: 0;
    }

    .mm-receipt__route small {
        display: block;
        color: var(--ms-muted);
        font-size: .66rem;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .mm-receipt__route strong {
        display: block;
        color: var(--ms-strong);
        font-size: .8125rem;
    }

    .mm-receipt__route iconify-icon {
        flex: none;
        color: var(--ms-muted);
    }

    .mm-lines-wrap {
        border: 1px solid var(--ms-rule);
        border-radius: 12px;
        overflow: auto;
        scrollbar-width: thin;
    }

    /* Modal body scrollcontainer yang scrollable; biarkan header ikut menggulir. */
    .mm-lines-wrap .ms-table th {
        position: static;
    }

    .mm-lines-wrap .mm-lines-table {
        min-width: 520px;
    }

    .mm-total-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 12px;
        padding: 14px 16px;
        border-radius: 12px;
        background: var(--ms-subtle);
    }

    .mm-total-row span {
        color: var(--ms-muted);
        font-size: .8125rem;
    }

    .mm-total-row strong {
        color: var(--ms-strong);
        font-size: 1.05rem;
        font-variant-numeric: tabular-nums;
    }

    .mm-total-row--danger strong {
        color: var(--ms-danger-ink);
    }

    .mm-callout {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-top: 14px;
        padding: 12px 14px;
        border-radius: 12px;
        font-size: .78rem;
        line-height: 1.5;
    }

    .mm-callout iconify-icon {
        flex: none;
        margin-top: 1px;
        font-size: 1rem;
    }

    .mm-callout p {
        margin: 0;
    }

    .mm-callout strong {
        color: var(--ms-strong);
    }

    .mm-callout--info {
        background: var(--ms-accent-soft);
        color: var(--ms-ink);
    }

    .mm-callout--info iconify-icon {
        color: var(--ms-accent-ink);
    }

    .mm-callout--danger {
        background: var(--ms-danger-soft);
        color: var(--ms-ink);
    }

    .mm-callout--danger iconify-icon {
        color: var(--ms-danger-ink);
    }

    .mm-reason {
        margin-top: 14px;
    }

    .mm-reason .ms-control {
        min-height: 96px;
        resize: vertical;
    }

    /* ===== Responsif ===== */
    @media (max-width: 991.98px) {
        .mm-field--search,
        .mm-field--dates {
            flex: 1 1 100%;
            max-width: none;
        }

        .mm-field--unit {
            flex: 1 1 220px;
        }

        .mm-field--actions {
            flex: 1 1 auto;
        }
    }

    @media (max-width: 767.98px) {
        .mm-filter form {
            padding: 16px;
        }

        .mm-field--search,
        .mm-field--unit,
        .mm-field--dates,
        .mm-field--actions {
            flex: 1 1 100%;
        }

        .mm-field--actions .ms-btn {
            flex: 1;
        }

        .mm-filter__bottom {
            align-items: flex-start;
            flex-direction: column;
        }

        .mm-foot {
            align-items: flex-start;
            flex-direction: column;
        }

        .mm-table,
        .mm-table > tbody,
        .mm-table > tbody > tr,
        .mm-table > tbody > tr > td {
            display: block;
            width: 100%;
        }

        .mm-table > thead {
            display: none;
        }

        .mm-table > tbody > tr.mm-main {
            padding: 14px 16px;
            border-bottom: 1px solid var(--ms-rule);
        }

        .mm-table > tbody > tr.mm-main:hover > td {
            background: transparent;
        }

        .mm-table > tbody > tr.mm-main > td {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 4px 0;
            border: 0;
        }

        .mm-table > tbody > tr.mm-main > td::before {
            content: attr(data-label);
            flex: none;
            color: var(--ms-muted);
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .045em;
            text-transform: uppercase;
        }

        .mm-table > tbody > tr.mm-main > td[data-label="Nota"] {
            display: block;
            padding-bottom: 8px;
        }

        .mm-table > tbody > tr.mm-main > td[data-label="Nota"]::before,
        .mm-table > tbody > tr.mm-main > td[data-label="Aksi"]::before {
            display: none;
        }

        .mm-table > tbody > tr.mm-main > td[data-label="Rute"] {
            display: block;
            padding: 6px 0;
        }

        .mm-table > tbody > tr.mm-main > td[data-label="Rute"]::before {
            display: block;
            margin-bottom: 3px;
        }

        .mm-table > tbody > tr.mm-main > td[data-label="Aksi"] {
            display: block;
            padding-top: 10px;
        }

        .mm-table .mm-actions {
            justify-content: stretch;
        }

        .mm-table .mm-actions .ms-btn {
            flex: 1 1 0;
        }

        .mm-table > tbody > tr.mm-detail-row.show {
            display: block;
        }

        .mm-table > tbody > tr.mm-detail-row > td {
            display: block;
            padding: 0 16px 14px;
            border: 0;
        }

        .mm-detail {
            border-radius: 10px;
        }
    }

    @media (max-width: 575.98px) {
        .mm-count {
            align-self: flex-start;
        }

        .mm-chip {
            min-height: 34px;
        }

        .mm-filter__hint {
            display: none;
        }

        .mm-receipt {
            grid-template-columns: 1fr;
        }

        .mm-receipt__cell + .mm-receipt__cell {
            border-top: 1px solid var(--ms-rule);
            border-left: 0;
        }

        .mm-lines {
            min-width: 0;
            table-layout: fixed;
        }

        .mm-lines th:nth-child(3),
        .mm-lines td:nth-child(3) {
            display: none;
        }

        .mm-lines th {
            white-space: normal;
        }

        .mm-lines th:nth-child(1),
        .mm-lines td:nth-child(1) {
            width: 56%;
        }

        .mm-lines th:nth-child(2),
        .mm-lines td:nth-child(2) {
            width: 14%;
        }

        .mm-lines th:nth-child(4),
        .mm-lines td:nth-child(4) {
            width: 30%;
        }

        .ms-modal .mm-modal-body {
            padding: 0 16px 18px;
        }

        .mm-total-row {
            padding: 12px 14px;
        }
    }

    @media (forced-colors: active) {
        .mm-chip,
        .mm-receipt,
        .mm-lines-wrap,
        .mm-total-row,
        .mm-callout,
        .mm-foot {
            border-color: CanvasText;
        }
    }
</style>
