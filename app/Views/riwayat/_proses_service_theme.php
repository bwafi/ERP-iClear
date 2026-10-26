<?php

/**
 * Tema halaman Proses Service (/proses_service).
 *
 * Semua selector berada di bawah `.ps-page` / `.ps-modal` agar tidak bocor ke
 * modul lain. Modal status dirender di body, jadi token juga dipasang di
 * `.ps-modal`. Warna diturunkan dari token tema aplikasi; satu aksen (biru)
 * hanya untuk aksi utama, fokus, dan state terpilih.
 *
 * Kontras teks kecil dijaga >= 4.5:1 di terang maupun gelap:
 *   --ps-muted  #5b6672 di putih (5.85:1), #8fa0b8 di gelap
 *   --ps-ink    #24303f di putih, var(--bs-body-color) di gelap
 * Tombol primary memakai #2f5fe0 (putih 5.2:1), bukan primary template
 * yang lebih terang.
 */
?>

<style>
    /* ===== Token ===== */
    .ps-page,
    .ps-modal {
        --ps-surface: var(--bs-card-bg, #fff);
        --ps-subtle: #f6f8fc;
        --ps-rule: var(--bs-border-color, #e5eaf1);
        --ps-rule-strong: #d7dfea;
        --ps-hover: rgba(47, 95, 224, .045);
        --ps-ink: #24303f;
        --ps-strong: #142033;
        --ps-muted: #5b6672;
        --ps-accent: #2f5fe0;
        --ps-accent-solid: #2f5fe0;
        --ps-accent-hover: #244fc4;
        --ps-accent-ink: #244fc4;
        --ps-accent-soft: rgba(47, 95, 224, .08);
        --ps-success: #0f7a46;
        --ps-success-soft: #e8f7ef;
        --ps-warn: #8a5300;
        --ps-warn-soft: #fff4d6;
        --ps-info: #0b6b8a;
        --ps-info-soft: #e6f6fb;
        --ps-danger: #b02a37;
        --ps-danger-soft: #fdecee;
        --ps-radius: 16px;
        --ps-radius-sm: 10px;
        --ps-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 18px 42px -36px rgba(16, 24, 40, .35);
        color: var(--ps-ink);
        font-size: .875rem;
    }

    html[data-bs-theme="dark"] .ps-page,
    html[data-bs-theme="dark"] .ps-modal {
        --ps-surface: var(--bs-card-bg, #2a3547);
        --ps-subtle: rgba(255, 255, 255, .035);
        --ps-rule: var(--bs-border-color, rgba(255, 255, 255, .12));
        --ps-rule-strong: rgba(255, 255, 255, .18);
        --ps-hover: rgba(255, 255, 255, .04);
        --ps-ink: var(--bs-body-color, #cfd8e3);
        --ps-strong: #f1f5f9;
        --ps-muted: #8fa0b8;
        --ps-accent: #8ab4ff;
        --ps-accent-solid: #2f6fe4;
        --ps-accent-hover: #3b7bf0;
        --ps-accent-ink: #a9c6ff;
        --ps-accent-soft: rgba(138, 180, 255, .12);
        --ps-success: #6fdd9d;
        --ps-success-soft: rgba(111, 221, 157, .12);
        --ps-warn: #ffcf6e;
        --ps-warn-soft: rgba(255, 207, 110, .12);
        --ps-info: #7dd3f0;
        --ps-info-soft: rgba(125, 211, 240, .12);
        --ps-danger: #ff9aa2;
        --ps-danger-soft: rgba(255, 154, 162, .12);
        --ps-shadow: none;
    }

    .ps-page {
        max-width: none;
        margin-inline: auto;
    }

    .ps-page .text-muted,
    .ps-modal .text-muted {
        color: var(--ps-muted) !important;
    }

    .ps-page ::selection,
    .ps-modal ::selection {
        background: var(--ps-accent-soft);
        color: var(--ps-strong);
    }

    .ps-page input,
    .ps-page select,
    .ps-modal input,
    .ps-modal select {
        caret-color: var(--ps-accent);
    }

    /* ===== Kepala halaman ===== */
    .ps-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 16px;
        padding: 24px;
        border: 1px solid var(--ps-rule);
        border-radius: var(--ps-radius);
        background: var(--ps-surface);
        box-shadow: var(--ps-shadow);
    }

    .ps-crumb {
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 0 0 8px;
        padding: 0;
        list-style: none;
        font-size: .75rem;
        color: var(--ps-muted);
    }

    .ps-crumb a {
        color: var(--ps-muted);
        text-decoration: none;
    }

    .ps-crumb a:hover {
        color: var(--ps-accent-ink);
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .ps-title {
        margin: 0;
        color: var(--ps-strong);
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -.02em;
        line-height: 1.2;
    }

    .ps-head p {
        max-width: 65ch;
        margin: 6px 0 0;
        color: var(--ps-muted);
        font-size: .875rem;
        line-height: 1.55;
    }

    .ps-head__meta {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
        flex: 0 0 auto;
    }

    .ps-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border: 1px solid var(--ps-rule);
        border-radius: 999px;
        background: var(--ps-subtle);
        color: var(--ps-muted);
        font-size: .75rem;
        font-weight: 500;
        line-height: 1.2;
        white-space: nowrap;
    }

    .ps-chip iconify-icon {
        color: var(--ps-accent-ink);
    }

    .ps-chip strong {
        color: var(--ps-strong);
        font-weight: 650;
        font-variant-numeric: tabular-nums;
    }

    .ps-chip--accent {
        border-color: transparent;
        background: var(--ps-accent-soft);
        color: var(--ps-accent-ink);
    }

    .ps-chip--accent iconify-icon,
    .ps-chip--accent strong {
        color: var(--ps-accent-ink);
    }

    /* ===== Panel data ===== */
    .ps-panel {
        position: relative;
        overflow: hidden;
        border: 1px solid var(--ps-rule);
        border-radius: var(--ps-radius);
        background: var(--ps-surface);
        box-shadow: var(--ps-shadow);
    }

    .ps-panel__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        padding: 20px 24px 16px;
    }

    .ps-panel__head h2 {
        margin: 0;
        color: var(--ps-strong);
        font-size: 1rem;
        font-weight: 650;
        letter-spacing: -.01em;
    }

    .ps-panel__head p {
        margin: 4px 0 0;
        color: var(--ps-muted);
        font-size: .8125rem;
        line-height: 1.5;
    }

    .ps-search {
        position: relative;
        flex: 0 1 340px;
    }

    .ps-search iconify-icon {
        position: absolute;
        top: 50%;
        left: 12px;
        color: var(--ps-muted);
        transform: translateY(-50%);
        pointer-events: none;
    }

    .ps-search input {
        width: 100%;
        min-height: 40px;
        padding: 9px 12px 9px 36px;
        border: 1px solid var(--ps-rule-strong);
        border-radius: var(--ps-radius-sm);
        background: var(--ps-subtle);
        color: var(--ps-ink);
        font-size: .8125rem;
    }

    .ps-search input::placeholder {
        color: var(--ps-muted);
    }

    .ps-search input:focus {
        outline: none;
        border-color: var(--ps-accent);
        background: var(--ps-surface);
        box-shadow: 0 0 0 3px var(--ps-accent-soft);
    }

    /* ===== Filter ===== */
    .ps-filter {
        display: grid;
        grid-template-columns: minmax(180px, 1fr) minmax(300px, 1.5fr) auto;
        align-items: end;
        gap: 14px;
        padding: 16px 24px;
        border-block: 1px solid var(--ps-rule);
        background: var(--ps-subtle);
    }

    .ps-field label,
    .ps-field__label {
        display: block;
        margin-bottom: 6px;
        color: var(--ps-muted);
        font-size: .75rem;
        font-weight: 600;
    }

    .ps-input {
        width: 100%;
        min-height: 40px;
        padding: 8px 11px;
        border: 1px solid var(--ps-rule-strong);
        border-radius: var(--ps-radius-sm);
        background: var(--ps-surface);
        color: var(--ps-ink);
        font-size: .8125rem;
    }

    .ps-input:focus {
        outline: none;
        border-color: var(--ps-accent);
        box-shadow: 0 0 0 3px var(--ps-accent-soft);
    }

    .ps-range {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ps-range span {
        color: var(--ps-muted);
        font-size: .75rem;
        white-space: nowrap;
    }

    .ps-range .ps-input {
        flex: 1;
        min-width: 0;
    }

    .ps-locked {
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 40px;
        padding: 8px 11px;
        border: 1px dashed var(--ps-rule-strong);
        border-radius: var(--ps-radius-sm);
        background: var(--ps-surface);
        color: var(--ps-muted);
        font-size: .8125rem;
    }

    .ps-locked iconify-icon {
        flex: 0 0 auto;
        color: var(--ps-accent-ink);
    }

    .ps-filter__actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
    }

    .ps-pagesize {
        flex: 0 0 88px;
        width: 88px;
    }

    .ps-filter__actions form {
        display: flex;
    }

    .ps-filter__actions form .ps-btn {
        flex: 1;
    }

    /* ===== Tombol ===== */
    .ps-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 40px;
        padding: 8px 14px;
        border: 1px solid transparent;
        border-radius: var(--ps-radius-sm);
        font-size: .8125rem;
        font-weight: 600;
        line-height: 1.2;
        text-decoration: none;
        cursor: pointer;
        transition: background-color .16s ease, border-color .16s ease, color .16s ease;
    }

    .ps-btn:focus-visible {
        outline: 2px solid var(--ps-accent);
        outline-offset: 2px;
    }

    .ps-btn:disabled,
    .ps-btn[disabled] {
        opacity: .55;
        cursor: not-allowed;
    }

    .ps-btn--primary {
        background: var(--ps-accent-solid);
        color: #fff;
    }

    .ps-btn--primary:hover,
    .ps-btn--primary:focus {
        background: var(--ps-accent-hover);
        color: #fff;
    }

    .ps-btn--ghost {
        border-color: var(--ps-rule-strong);
        background: transparent;
        color: var(--ps-ink);
    }

    .ps-btn--ghost:hover,
    .ps-btn--ghost:focus {
        background: var(--ps-surface);
        color: var(--ps-strong);
    }

    .ps-btn--export {
        border-color: var(--ps-rule-strong);
        background: var(--ps-surface);
        color: var(--ps-ink);
    }

    .ps-btn--export:hover,
    .ps-btn--export:focus {
        border-color: var(--ps-accent);
        background: var(--ps-accent-soft);
        color: var(--ps-accent-ink);
    }

    .ps-btn--sm {
        min-height: 32px;
        padding: 5px 9px;
        border-radius: 8px;
        font-size: .75rem;
    }

    .ps-btn--icon {
        width: 32px;
        padding: 0;
    }

    .ps-btn--action {
        background: var(--ps-accent-soft);
        color: var(--ps-accent-ink);
    }

    .ps-btn--action:hover,
    .ps-btn--action:focus {
        background: var(--ps-accent-solid);
        color: #fff;
    }

    .ps-btn--danger {
        border-color: transparent;
        background: var(--ps-danger-soft);
        color: var(--ps-danger);
    }

    .ps-btn--danger:hover,
    .ps-btn--danger:focus {
        background: var(--ps-danger);
        color: #fff;
    }

    .ps-btn--soft {
        border-color: var(--ps-rule-strong);
        background: transparent;
        color: var(--ps-muted);
    }

    .ps-btn--soft:hover,
    .ps-btn--soft:focus {
        border-color: var(--ps-accent);
        background: var(--ps-accent-soft);
        color: var(--ps-accent-ink);
    }

    .ps-btn.is-loading {
        pointer-events: none;
        opacity: .7;
    }

    /* ===== Tabel ===== */
    .ps-table-scroll {
        overflow-x: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--ps-rule-strong) transparent;
    }

    .ps-table-scroll::-webkit-scrollbar {
        height: 8px;
    }

    .ps-table-scroll::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: var(--ps-rule-strong);
    }

    .ps-table {
        width: 100%;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .ps-table th {
        padding: 10px 16px;
        border-bottom: 1px solid var(--ps-rule);
        background: var(--ps-subtle);
        color: var(--ps-muted);
        font-size: .6875rem;
        font-weight: 650;
        letter-spacing: .06em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .ps-table th:first-child {
        padding-left: 24px;
    }

    .ps-table th:last-child {
        padding-right: 24px;
        text-align: right;
    }

    .ps-table td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--ps-rule);
        color: var(--ps-ink);
        vertical-align: middle;
    }

    .ps-table td:first-child {
        padding-left: 24px;
    }

    .ps-table td:last-child {
        padding-right: 24px;
        text-align: right;
    }

    .ps-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .ps-table tbody tr:hover td {
        background: var(--ps-hover);
    }

    .ps-code {
        display: block;
        color: var(--ps-strong);
        font-size: .8125rem;
        font-weight: 650;
        letter-spacing: .01em;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .ps-sub {
        display: block;
        margin-top: 3px;
        color: var(--ps-muted);
        font-size: .75rem;
        line-height: 1.4;
    }

    .ps-person {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        min-width: 0;
    }

    .ps-avatar {
        display: grid;
        flex: 0 0 34px;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 50%;
        background: var(--ps-accent-soft);
        color: var(--ps-accent-ink);
        font-size: .8125rem;
        font-weight: 700;
    }

    .ps-person__body {
        min-width: 0;
    }

    .ps-person__name {
        display: block;
        overflow: hidden;
        color: var(--ps-strong);
        font-size: .8125rem;
        font-weight: 600;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ps-unit {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        max-width: 22ch;
        margin-top: 5px;
        padding: 3px 8px;
        border: 1px solid var(--ps-rule);
        border-radius: 999px;
        background: var(--ps-subtle);
        color: var(--ps-muted);
        font-size: .6875rem;
        line-height: 1.2;
        white-space: nowrap;
    }

    .ps-unit iconify-icon {
        flex: 0 0 auto;
        color: var(--ps-accent-ink);
    }

    .ps-unit span {
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ps-age {
        display: inline-flex;
        align-items: baseline;
        gap: 4px;
        color: var(--ps-strong);
        white-space: nowrap;
    }

    .ps-age__value {
        font-size: 1rem;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
    }

    .ps-age__unit {
        color: var(--ps-muted);
        font-size: .75rem;
    }

    .ps-tag {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: .75rem;
        font-weight: 600;
        line-height: 1.2;
        white-space: nowrap;
    }

    .ps-tag--new {
        background: var(--ps-subtle);
        color: var(--ps-muted);
    }

    .ps-tag--warranty {
        background: var(--ps-warn-soft);
        color: var(--ps-warn);
    }

    .ps-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 9px;
        border: 1px solid transparent;
        border-radius: 999px;
        font-size: .75rem;
        font-weight: 600;
        line-height: 1.2;
        white-space: nowrap;
        cursor: pointer;
        transition: filter .16s ease, border-color .16s ease;
    }

    .ps-status::before {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        content: "";
    }

    .ps-status:hover,
    .ps-status:focus-visible {
        filter: brightness(.97);
        outline: none;
        border-color: currentColor;
    }

    .ps-status iconify-icon {
        opacity: .7;
    }

    .ps-status--idle {
        background: var(--ps-subtle);
        color: var(--ps-muted);
    }

    .ps-status--info {
        background: var(--ps-info-soft);
        color: var(--ps-info);
    }

    .ps-status--progress {
        background: var(--ps-accent-soft);
        color: var(--ps-accent-ink);
    }

    .ps-status--success {
        background: var(--ps-success-soft);
        color: var(--ps-success);
    }

    .ps-status--danger {
        background: var(--ps-danger-soft);
        color: var(--ps-danger);
    }

    .ps-status--warn {
        background: var(--ps-warn-soft);
        color: var(--ps-warn);
    }

    .ps-star {
        display: inline-grid;
        width: 34px;
        height: 34px;
        padding: 0;
        place-items: center;
        border: 1px solid var(--ps-rule-strong);
        border-radius: 9px;
        background: transparent;
        color: var(--ps-muted);
        cursor: pointer;
        transition: color .16s ease, border-color .16s ease, background-color .16s ease;
    }

    .ps-star:hover,
    .ps-star:focus-visible {
        border-color: var(--ps-warn);
        background: var(--ps-warn-soft);
        color: var(--ps-warn);
    }

    .ps-star:focus-visible {
        outline: 2px solid var(--ps-accent);
        outline-offset: 2px;
    }

    .ps-star.is-on {
        border-color: transparent;
        background: var(--ps-warn-soft);
        color: var(--ps-warn);
    }

    .ps-actions {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 6px;
    }

    .ps-actions__row {
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
    }

    .ps-alert-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--ps-warn);
        box-shadow: 0 0 0 2px var(--ps-warn-soft);
    }

    /* ===== Footer DataTables ===== */
    .ps-dt-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        padding: 14px 24px;
        border-top: 1px solid var(--ps-rule);
        color: var(--ps-muted);
        font-size: .75rem;
    }

    .ps-dt-foot .dataTables_info {
        padding: 0;
        color: var(--ps-muted);
    }

    .ps-dt-foot .dataTables_paginate {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .ps-dt-foot .dataTables_paginate > span {
        display: inline-flex;
        gap: 4px;
    }

    .ps-dt-foot .paginate_button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 30px;
        padding: 5px 9px;
        border: 1px solid var(--ps-rule);
        border-radius: 8px;
        background: var(--ps-surface);
        color: var(--ps-ink);
        font-size: .75rem;
        text-decoration: none;
        cursor: pointer;
    }

    .ps-dt-foot .paginate_button:hover {
        border-color: var(--ps-accent);
        background: var(--ps-accent-soft);
        color: var(--ps-accent-ink);
    }

    .ps-dt-foot .paginate_button.current {
        border-color: var(--ps-accent-solid);
        background: var(--ps-accent-solid);
        color: #fff;
    }

    .ps-dt-foot .paginate_button.disabled {
        color: var(--ps-muted);
        cursor: not-allowed;
        opacity: .55;
    }

    .ps-panel .dataTables_processing {
        padding: 12px 24px;
        border-bottom: 1px solid var(--ps-rule);
        background: var(--ps-surface);
        color: var(--ps-muted);
        font-size: .8125rem;
        text-align: center;
    }

    /* ===== Empty state ===== */
    .ps-table td.dataTables_empty {
        padding: 0 !important;
        border: 0;
        text-align: center;
    }

    .ps-table td.dataTables_empty .ps-empty {
        width: 100%;
    }

    .ps-table td.dataTables_empty::before {
        display: none;
    }

    .ps-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 52px 24px;
        text-align: center;
    }

    .ps-empty__icon {
        display: grid;
        width: 48px;
        height: 48px;
        place-items: center;
        border-radius: 14px;
        background: var(--ps-accent-soft);
        color: var(--ps-accent-ink);
    }

    .ps-empty h3 {
        margin: 4px 0 0;
        color: var(--ps-strong);
        font-size: .9375rem;
        font-weight: 650;
    }

    .ps-empty p {
        max-width: 42ch;
        margin: 0;
        color: var(--ps-muted);
        font-size: .8125rem;
        line-height: 1.55;
    }

    /* ===== Modal ===== */
    .ps-modal .modal-content {
        border: 1px solid var(--ps-rule);
        border-radius: var(--ps-radius);
        background: var(--ps-surface);
        color: var(--ps-ink);
    }

    .ps-modal .modal-header {
        align-items: flex-start;
        gap: 12px;
        border-bottom: 1px solid var(--ps-rule);
        background: var(--ps-subtle);
    }

    .ps-modal__title {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .ps-modal__icon {
        display: grid;
        flex: 0 0 40px;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 12px;
        background: var(--ps-accent-soft);
        color: var(--ps-accent-ink);
    }

    .ps-modal__title--warn .ps-modal__icon {
        background: var(--ps-warn-soft);
        color: var(--ps-warn);
    }

    .ps-modal__title--danger .ps-modal__icon {
        background: var(--ps-danger-soft);
        color: var(--ps-danger);
    }

    .ps-modal .modal-title {
        color: var(--ps-strong);
        font-size: 1rem;
        font-weight: 650;
    }

    .ps-modal__title p {
        margin: 2px 0 0;
        color: var(--ps-muted);
        font-size: .75rem;
    }

    .ps-modal .modal-body {
        padding: 20px 24px;
    }

    .ps-modal__question {
        margin: 0;
        color: var(--ps-ink);
        font-size: .875rem;
        line-height: 1.6;
    }

    .ps-modal__question strong {
        color: var(--ps-strong);
        font-weight: 650;
    }

    .ps-modal .modal-footer {
        border-top: 1px solid var(--ps-rule);
    }

    .ps-modal .alert {
        margin-bottom: 12px;
        border: 1px solid var(--ps-warn-soft);
        border-radius: var(--ps-radius-sm);
        background: var(--ps-warn-soft);
        color: var(--ps-warn);
        font-size: .8125rem;
    }

    .ps-choice-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .ps-choice {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 10px 12px;
        border: 1px solid var(--ps-rule-strong);
        border-radius: var(--ps-radius-sm);
        background: var(--ps-surface);
        color: var(--ps-ink);
        font-size: .8125rem;
        cursor: pointer;
    }

    .ps-choice:hover {
        border-color: var(--ps-accent);
        background: var(--ps-accent-soft);
    }

    .ps-choice:has(input:checked) {
        border-color: var(--ps-accent-solid);
        background: var(--ps-accent-soft);
        color: var(--ps-accent-ink);
        font-weight: 600;
    }

    .ps-choice input {
        flex: 0 0 auto;
        margin: 0;
        accent-color: var(--ps-accent-solid);
    }

    .ps-choice__dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: currentColor;
    }

    .ps-modal__loading {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 32px 0;
        color: var(--ps-muted);
        font-size: .8125rem;
    }

    /* ===== Responsif ===== */
    @media (max-width: 991.98px) {
        .ps-head {
            flex-direction: column;
        }

        .ps-head__meta {
            justify-content: flex-start;
        }

        .ps-filter {
            grid-template-columns: 1fr 1fr;
        }

        .ps-filter__actions {
            grid-column: 1 / -1;
            justify-content: flex-start;
        }
    }

    @media (max-width: 1199.98px) {
        .ps-panel__head {
            flex-direction: column;
        }

        .ps-search {
            width: 100%;
            flex-basis: auto;
        }

        .ps-table-scroll {
            overflow: visible;
        }

        .ps-table,
        .ps-table tbody,
        .ps-table tr,
        .ps-table td {
            display: block;
            width: 100%;
        }

        .ps-table thead {
            display: none;
        }

        .ps-table tr {
            padding: 14px 18px;
            border-bottom: 1px solid var(--ps-rule);
        }

        .ps-table tbody tr:last-child {
            border-bottom: 0;
        }

        .ps-table td,
        .ps-table td:first-child,
        .ps-table td:last-child {
            display: grid;
            grid-template-columns: 42% minmax(0, 1fr);
            align-items: start;
            column-gap: 16px;
            row-gap: 2px;
            padding: 5px 0;
            border: 0;
            text-align: left;
        }

        .ps-table td::before {
            grid-column: 1;
            grid-row: 1 / span 4;
            color: var(--ps-muted);
            font-size: .6875rem;
            font-weight: 650;
            letter-spacing: .05em;
            text-transform: uppercase;
            content: attr(data-label);
        }

        .ps-table td > * {
            grid-column: 2;
        }

        .ps-table td[data-label="Aksi"] {
            display: block;
            padding-top: 12px;
        }

        .ps-table td[data-label="Aksi"]::before {
            display: none;
        }

        .ps-table td[data-label="Prioritas"] {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-top: 12px;
        }

        .ps-table td[data-label="Prioritas"]::before {
            grid-row: auto;
        }

        .ps-actions {
            align-items: stretch;
        }

        .ps-actions__row {
            justify-content: stretch;
        }

        .ps-actions__row .ps-btn:not(.ps-btn--icon) {
            flex: 1;
        }

        .ps-person {
            width: 100%;
        }

        .ps-tag,
        .ps-unit,
        .ps-status,
        .ps-age {
            justify-self: start;
        }

        .ps-table td.dataTables_empty {
            display: block;
        }
    }

    @media (max-width: 575.98px) {
        .ps-head {
            padding: 20px;
        }

        .ps-filter {
            grid-template-columns: 1fr;
        }

        .ps-range {
            flex-direction: column;
            align-items: stretch;
        }

        .ps-range span {
            text-align: center;
        }

        .ps-filter__actions {
            grid-column: auto;
        }

        .ps-filter__actions .ps-btn {
            flex: 1;
        }

        .ps-choice-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .ps-page *,
        .ps-modal * {
            transition: none !important;
        }
    }
</style>
