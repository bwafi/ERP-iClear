<?php

/**
 * Tema halaman Riwayat Presensi pegawai (/riwayat_presensi).
 *
 * Semua selector berada di bawah `.rp-page` dan modalnya memakai `.rp-modal`
 * agar tidak bocor ke modul lain. Satu aksen (biru riwayat, sama dengan
 * halaman Riwayat Absen) hanya untuk aksi utama, fokus, dan status aktif.
 *
 * Kontras teks kecil dijaga >= 4.5:1 di terang maupun gelap:
 *   --rp-muted  #5b6672 di putih (5.85:1), #8fa0b8 di gelap
 *   --rp-ink    #3d4a58 di putih, var(--bs-body-color) di gelap
 */
?>

<style>
    /* ===== Token ===== */
    .rp-page,
    .rp-modal {
        --rp-surface: var(--bs-card-bg, #fff);
        --rp-subtle: var(--bs-tertiary-bg, #f6f8fb);
        --rp-rule: var(--bs-border-color, #e6ecf1);
        --rp-muted: #5b6672;
        --rp-ink: #3d4a58;
        --rp-accent-ink: #0b5f8a;
        --rp-accent-solid: #0b5f8a;
        --rp-accent-hover: #09506f;
        --rp-accent-soft: rgba(11, 95, 138, .08);
        --rp-ok-ink: #0f6b3e;
        --rp-ok-soft: rgba(15, 122, 70, .10);
        --rp-warn-ink: #8a5300;
        --rp-warn-soft: rgba(192, 118, 0, .13);
        --rp-bad-ink: #b02a37;
        --rp-bad-soft: rgba(176, 42, 55, .10);
        --rp-control-line: #7d8a99;
        --rp-radius: 16px;
        --rp-radius-sm: 10px;
        --rp-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 18px 40px -32px rgba(16, 24, 40, .35);
    }

    html[data-bs-theme="dark"] .rp-page,
    html[data-bs-theme="dark"] .rp-modal {
        --rp-surface: var(--bs-card-bg, #1f2937);
        --rp-subtle: rgba(255, 255, 255, .04);
        --rp-muted: #8fa0b8;
        --rp-ink: var(--bs-body-color, #cfd8e3);
        --rp-accent-ink: #7cc4f0;
        --rp-accent-soft: rgba(124, 196, 240, .12);
        --rp-ok-ink: #6fdd9d;
        --rp-ok-soft: rgba(111, 221, 157, .12);
        --rp-warn-ink: #ffcf6e;
        --rp-warn-soft: rgba(255, 207, 110, .12);
        --rp-bad-ink: #ff9aa2;
        --rp-bad-soft: rgba(255, 154, 162, .12);
        --rp-control-line: #8fa0b8;
        --rp-shadow: none;
    }

    .rp-page {
        display: flex;
        flex-direction: column;
        gap: 16px;
        color: var(--rp-ink);
    }

    .rp-page h1,
    .rp-page h2,
    .rp-page h5,
    .rp-modal h5,
    .rp-modal h6 {
        color: var(--rp-ink);
    }

    /* ===== Page head ===== */
    .rp-page .rp-hero {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        padding: 16px 20px;
        background: var(--rp-surface);
        border: 1px solid var(--rp-rule);
        border-radius: var(--rp-radius);
        box-shadow: var(--rp-shadow);
    }

    .rp-page .rp-crumb {
        margin-bottom: 10px;
        color: var(--rp-muted);
        font-size: .72rem;
    }

    .rp-page .rp-crumb ol {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .rp-page .rp-crumb li {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .rp-page .rp-crumb li + li::before {
        content: "/";
        color: var(--rp-rule);
    }

    .rp-page .rp-crumb a {
        color: var(--rp-muted);
        text-decoration: none;
    }

    .rp-page .rp-crumb a:hover {
        color: var(--rp-accent-ink);
    }

    .rp-page .rp-hero h1 {
        margin: 0 0 6px;
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1.1;
        letter-spacing: -.035em;
    }

    .rp-page .rp-hero p {
        max-width: 62ch;
        margin: 0;
        color: var(--rp-muted);
        font-size: .86rem;
        line-height: 1.55;
        text-wrap: pretty;
    }

    .rp-page .rp-hero__aside {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .rp-page .rp-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 10px;
        color: var(--rp-muted);
        background: var(--rp-subtle);
        border: 1px solid var(--rp-rule);
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 650;
        white-space: nowrap;
    }

    .rp-page .rp-chip strong {
        color: var(--rp-ink);
        font-variant-numeric: tabular-nums;
        font-weight: 700;
    }

    /* ===== Tombol & kontrol ===== */
    .rp-page .rp-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        min-height: 38px;
        padding: 8px 13px;
        border: 1px solid transparent;
        border-radius: var(--rp-radius-sm);
        font-family: inherit;
        font-size: .78rem;
        font-weight: 650;
        line-height: 1.2;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease;
    }

    .rp-page .rp-btn--ghost {
        color: var(--rp-ink);
        background: transparent;
        border-color: var(--rp-control-line);
    }

    .rp-page .rp-btn--ghost:hover,
    .rp-page .rp-btn--ghost:focus {
        color: var(--rp-accent-ink);
        background: var(--rp-accent-soft);
        border-color: var(--rp-accent-ink);
    }

    .rp-page .rp-control {
        width: 100%;
        height: 38px;
        padding: 7px 10px;
        color: var(--rp-ink);
        background: var(--rp-surface);
        border: 1px solid var(--rp-rule);
        border-radius: var(--rp-radius-sm);
        font-family: inherit;
        font-size: .8rem;
    }

    html[data-bs-theme="dark"] .rp-page .rp-control {
        color-scheme: dark;
    }

    .rp-page .rp-control:focus {
        outline: none;
        border-color: var(--rp-accent-ink);
        box-shadow: 0 0 0 3px var(--rp-accent-soft);
    }

    .rp-page a:focus-visible,
    .rp-page button:focus-visible,
    .rp-modal a:focus-visible,
    .rp-modal button:focus-visible {
        outline: 2px solid var(--rp-accent-ink);
        outline-offset: 2px;
    }

    /* ===== Papan ringkasan ===== */
    .rp-page .rp-board {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
    }

    .rp-page .rp-board__cell {
        min-width: 0;
        padding: 14px 16px;
        background: var(--rp-surface);
        border: 1px solid var(--rp-rule);
        border-radius: var(--rp-radius-sm);
    }

    .rp-page .rp-board__label {
        margin-bottom: 6px;
        color: var(--rp-muted);
        font-size: .66rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .rp-page .rp-board__value {
        color: var(--rp-ink);
        font-size: 1.5rem;
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        letter-spacing: -.03em;
        line-height: 1.05;
    }

    .rp-page .rp-board__value.is-ok {
        color: var(--rp-ok-ink);
    }

    .rp-page .rp-board__value.is-warn {
        color: var(--rp-warn-ink);
    }

    .rp-page .rp-board__value.is-bad {
        color: var(--rp-bad-ink);
    }

    /* ===== Kartu & filter ===== */
    .rp-page .rp-card {
        background: var(--rp-surface);
        border: 1px solid var(--rp-rule);
        border-radius: var(--rp-radius);
        box-shadow: var(--rp-shadow);
    }

    .rp-page .rp-filter {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 12px 16px;
        padding: 14px 18px;
    }

    .rp-page .rp-filter__fields {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 12px;
    }

    .rp-page .rp-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
    }

    .rp-page .rp-field label {
        color: var(--rp-muted);
        font-size: .66rem;
        font-weight: 700;
        letter-spacing: .055em;
        text-transform: uppercase;
    }

    .rp-page .rp-filter__hint {
        display: flex;
        flex: 1 1 240px;
        gap: 8px;
        align-items: center;
        justify-content: flex-end;
        margin: 0;
        color: var(--rp-muted);
        font-size: .73rem;
        line-height: 1.45;
    }

    .rp-page .rp-filter__hint iconify-icon {
        flex: none;
        font-size: 16px;
    }

    /* ===== Tabel ===== */
    .rp-page .rp-table-card {
        overflow: hidden;
    }

    .rp-page .rp-table-card__head {
        padding: 16px 18px 12px;
    }

    .rp-page .rp-table-card__head h2 {
        margin: 0;
        font-size: .95rem;
        font-weight: 700;
        letter-spacing: -.015em;
    }

    .rp-page .rp-table-card__head p {
        margin: 4px 0 0;
        color: var(--rp-muted);
        font-size: .73rem;
        line-height: 1.45;
    }

    .rp-page .rp-table-wrap {
        overflow-x: auto;
        scrollbar-width: thin;
    }

    .rp-page .rp-table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
        color: var(--rp-ink);
        font-size: .8rem;
    }

    .rp-page .rp-table > thead > tr > th {
        padding: 10px 14px;
        color: var(--rp-muted);
        background: var(--rp-subtle);
        border-top: 1px solid var(--rp-rule);
        border-bottom: 1px solid var(--rp-rule);
        font-size: .65rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .rp-page .rp-table > tbody > tr > td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--rp-rule);
        vertical-align: middle;
    }

    .rp-page .rp-table > tbody > tr:last-child > td {
        border-bottom: 0;
    }

    .rp-page .rp-table > tbody > tr:hover > td {
        background: var(--rp-subtle);
    }

    .rp-page .rp-day {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .rp-page .rp-day__date {
        color: var(--rp-ink);
        font-variant-numeric: tabular-nums;
        font-weight: 650;
        white-space: nowrap;
    }

    .rp-page .rp-day__note {
        color: var(--rp-muted);
        font-size: .69rem;
    }

    .rp-page .rp-time {
        font-variant-numeric: tabular-nums;
        font-weight: 600;
        white-space: nowrap;
    }

    .rp-page .rp-time.is-empty,
    .rp-page .rp-muted {
        color: var(--rp-muted);
        font-weight: 500;
    }

    .rp-page .rp-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: .69rem;
        font-weight: 650;
        line-height: 1.25;
        white-space: nowrap;
    }

    .rp-page .rp-tag::before {
        content: "";
        flex: none;
        width: 6px;
        height: 6px;
        background: currentColor;
        border-radius: 50%;
        opacity: .9;
    }

    .rp-page .rp-tag--ok {
        color: var(--rp-ok-ink);
        background: var(--rp-ok-soft);
    }

    .rp-page .rp-tag--warn {
        color: var(--rp-warn-ink);
        background: var(--rp-warn-soft);
    }

    .rp-page .rp-tag--bad {
        color: var(--rp-bad-ink);
        background: var(--rp-bad-soft);
    }

    .rp-page .rp-tag--accent {
        color: var(--rp-accent-ink);
        background: var(--rp-accent-soft);
    }

    .rp-page .rp-tag--muted {
        color: var(--rp-muted);
        background: var(--rp-subtle);
    }

    .rp-page .rp-action {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 10px;
        color: var(--rp-accent-ink);
        background: transparent;
        border: 1px solid var(--rp-rule);
        border-radius: 8px;
        font-family: inherit;
        font-size: .72rem;
        font-weight: 650;
        line-height: 1.2;
        cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }

    .rp-page .rp-action:hover,
    .rp-page .rp-action:focus {
        color: var(--rp-accent-ink);
        background: var(--rp-accent-soft);
        border-color: var(--rp-accent-ink);
    }

    /* ===== Kontrol DataTables ===== */
    .rp-page .rp-dt-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 18px;
        border-top: 1px solid var(--rp-rule);
    }

    .rp-page .rp-dt-toolbar .dataTables_length label {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        color: var(--rp-muted);
        font-size: .73rem;
        font-weight: 600;
    }

    .rp-page .rp-dt-toolbar .dataTables_length select {
        height: 32px;
        padding: 4px 8px;
        color: var(--rp-ink);
        background: var(--rp-surface);
        border: 1px solid var(--rp-rule);
        border-radius: 8px;
        font-family: inherit;
        font-size: .75rem;
    }

    .rp-page .rp-dt-toolbar .dataTables_filter label {
        margin: 0;
    }

    .rp-page .rp-dt-toolbar .dataTables_filter input {
        width: 230px;
        height: 34px;
        padding: 6px 10px;
        color: var(--rp-ink);
        background: var(--rp-surface);
        border: 1px solid var(--rp-rule);
        border-radius: 8px;
        font-family: inherit;
        font-size: .77rem;
    }

    .rp-page .rp-dt-toolbar .dataTables_filter input::placeholder {
        color: var(--rp-muted);
        opacity: .85;
    }

    .rp-page .rp-dt-toolbar .dataTables_filter input:focus {
        outline: none;
        border-color: var(--rp-accent-ink);
        box-shadow: 0 0 0 3px var(--rp-accent-soft);
    }

    .rp-page .rp-dt-foot {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 18px;
        border-top: 1px solid var(--rp-rule);
    }

    .rp-page .dataTables_info {
        padding: 0;
        color: var(--rp-muted);
        font-size: .73rem;
    }

    /* DataTables inti merender <a class="paginate_button">, bukan markup
       Bootstrap 5 (<ul class="pagination"><a class="page-link">), karena
       integrasi dataTables.bootstrap5.min.js tidak dimuat. Gaya di bawah
       mengikuti kotak 32px halaman /kas_keluar. */
    .rp-page .dataTables_paginate {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px;
        padding: 0;
    }

    .rp-page .dataTables_paginate > span {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px;
    }

    .rp-page .dataTables_paginate .paginate_button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        padding: 0 8px;
        color: var(--rp-ink);
        background: var(--rp-surface);
        border: 1px solid var(--rp-rule);
        border-radius: 8px;
        font-size: .8125rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        line-height: 1;
        text-decoration: none;
        cursor: pointer;
        user-select: none;
    }

    .rp-page .dataTables_paginate .paginate_button.previous,
    .rp-page .dataTables_paginate .paginate_button.next {
        font-size: 1rem;
    }

    .rp-page .dataTables_paginate .paginate_button:hover {
        color: var(--rp-accent-ink);
        background: var(--rp-accent-soft);
        border-color: var(--rp-accent-ink);
    }

    .rp-page .dataTables_paginate .paginate_button.current {
        color: #fff;
        background: var(--rp-accent-solid);
        border-color: var(--rp-accent-solid);
        cursor: default;
    }

    .rp-page .dataTables_paginate .paginate_button.disabled {
        color: var(--rp-muted);
        background: transparent;
        border-color: var(--rp-rule);
        opacity: .45;
        pointer-events: none;
    }

    .rp-page .dataTables_paginate .ellipsis {
        padding: 0 2px;
        color: var(--rp-muted);
        font-size: .8125rem;
        line-height: 32px;
    }

    .rp-page .dataTables_empty {
        padding: 28px 14px;
        color: var(--rp-muted);
        text-align: center;
    }

    /* ===== Modal detail ===== */
    .rp-modal .modal-content {
        color: var(--rp-ink);
        background: var(--rp-surface);
        border: 1px solid var(--rp-rule);
        border-radius: 18px;
    }

    .rp-modal .modal-header {
        align-items: flex-start;
        gap: 14px;
        padding: 16px 18px;
        border-bottom: 1px solid var(--rp-rule);
    }

    .rp-modal .modal-body {
        padding: 18px;
    }

    .rp-modal .modal-footer {
        gap: 8px;
        padding: 12px 18px;
        border-top: 1px solid var(--rp-rule);
    }

    .rp-modal .rp-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        gap: 12px;
    }

    .rp-modal .rp-identity {
        display: flex;
        flex: 1 1 260px;
        gap: 12px;
        align-items: center;
        min-width: 0;
    }

    .rp-modal .rp-avatar {
        display: inline-flex;
        flex: none;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        color: var(--rp-accent-ink);
        background: var(--rp-accent-soft);
        border-radius: 12px;
    }

    .rp-modal .rp-identity h5 {
        margin: 0;
        font-size: .98rem;
        font-weight: 700;
        letter-spacing: -.015em;
    }

    .rp-modal .rp-identity p {
        margin: 3px 0 0;
        color: var(--rp-muted);
        font-size: .74rem;
    }

    .rp-modal .rp-detail__tags {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .rp-modal .rp-detail {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .rp-modal .rp-panel {
        min-width: 0;
        padding: 14px;
        background: var(--rp-surface);
        border: 1px solid var(--rp-rule);
        border-radius: 12px;
    }

    .rp-modal .rp-panel__title {
        margin: 0 0 10px;
        color: var(--rp-muted);
        font-size: .66rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .rp-modal .rp-meta {
        display: flex;
        flex-direction: column;
        margin: 0;
    }

    .rp-modal .rp-meta__item {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 12px;
        padding: 8px 0;
        border-top: 1px solid var(--rp-rule);
    }

    .rp-modal .rp-meta__item:first-child {
        border-top: 0;
    }

    .rp-modal .rp-meta dt {
        margin: 0;
        color: var(--rp-muted);
        font-size: .73rem;
        font-weight: 600;
    }

    .rp-modal .rp-meta dd {
        margin: 0;
        color: var(--rp-ink);
        font-size: .8rem;
        font-variant-numeric: tabular-nums;
        font-weight: 650;
        text-align: right;
        overflow-wrap: anywhere;
    }

    .rp-modal .rp-media {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .rp-modal .rp-map {
        height: 320px;
        overflow: hidden;
        background: var(--rp-subtle);
        border: 1px solid var(--rp-rule);
        border-radius: 12px;
    }

    .rp-modal .rp-map__empty {
        margin: 0;
        padding: 16px;
        color: var(--rp-muted);
        font-size: .78rem;
        line-height: 1.5;
    }

    .rp-modal .rp-photos {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .rp-modal .rp-photo {
        margin: 0;
        overflow: hidden;
        background: var(--rp-subtle);
        border: 1px solid var(--rp-rule);
        border-radius: 12px;
    }

    .rp-modal .rp-photo figcaption {
        padding: 8px 10px;
        color: var(--rp-muted);
        font-size: .66rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .rp-modal .rp-photo__img {
        display: block;
        width: 100%;
        height: 150px;
        object-fit: cover;
        cursor: zoom-in;
        background: var(--rp-subtle);
    }

    .rp-modal .rp-photo__img:hover {
        opacity: .9;
    }

    .rp-modal .rp-photo__empty {
        padding: 24px 10px;
        color: var(--rp-muted);
        font-size: .76rem;
        text-align: center;
    }

    /* ===== Penampil foto ===== */
    .rp-viewer .modal-body {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 240px;
        padding: 0;
        background: #0b0f14;
        border-radius: 17px 17px 0 0;
    }

    .rp-viewer .rp-zoom {
        overflow: hidden;
        width: 100%;
        text-align: center;
    }

    .rp-viewer .rp-zoom img {
        max-width: 100%;
        max-height: 68vh;
        transition: transform .2s ease;
    }

    .rp-viewer .modal-footer {
        justify-content: center;
        background: var(--rp-surface);
    }

    .rp-viewer .rp-viewer__btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 10px;
        color: var(--rp-ink);
        background: transparent;
        border: 1px solid var(--rp-rule);
        border-radius: 8px;
        font-family: inherit;
        font-size: .82rem;
        font-weight: 650;
        cursor: pointer;
    }

    .rp-viewer .rp-viewer__btn:hover {
        color: var(--rp-accent-ink);
        background: var(--rp-accent-soft);
        border-color: var(--rp-accent-ink);
    }

    .rp-map__label {
        padding: 3px 7px;
        color: #0d1117;
        background: #fff;
        border: 1px solid #8b949e;
        border-radius: 5px;
        font-size: 12px;
        font-weight: 700;
    }

    /* ===== Responsif ===== */
    @media (max-width: 991.98px) {
        .rp-page .rp-board {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .rp-page .rp-filter__hint {
            justify-content: flex-start;
        }

        .rp-modal .rp-detail {
            grid-template-columns: minmax(0, 1fr);
        }

        .rp-modal .rp-media {
            order: -1;
        }
    }

    @media (max-width: 575.98px) {
        .rp-page .rp-hero h1 {
            font-size: 1.35rem;
        }

        .rp-page .rp-hero__aside {
            width: 100%;
        }

        .rp-page .rp-board {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .rp-page .rp-field {
            flex: 1 1 100%;
        }

        .rp-page .rp-filter__fields {
            width: 100%;
        }

        .rp-page .rp-filter__fields .rp-btn {
            width: 100%;
        }

        .rp-page .rp-dt-toolbar,
        .rp-page .rp-dt-foot {
            flex-direction: column;
            align-items: stretch;
        }

        .rp-page .rp-dt-toolbar .dataTables_filter input {
            width: 100%;
        }

        .rp-page .dataTables_filter {
            width: 100%;
        }

        .rp-page .rp-dt-toolbar .dataTables_filter label {
            display: block;
            width: 100%;
        }

        .rp-modal .rp-photos {
            grid-template-columns: minmax(0, 1fr);
        }

        .rp-modal .rp-map {
            height: 240px;
        }

        .rp-modal .modal-body {
            padding: 14px;
        }
    }

    /* Delapan kolom dirapatkan sedikit agar tabel utuh di layar 992-1199px. */
    @media (min-width: 992px) and (max-width: 1199.98px) {
        .rp-page .rp-table > thead > tr > th,
        .rp-page .rp-table > tbody > tr > td {
            padding-right: 10px;
            padding-left: 10px;
        }
    }

    /* Tabel menjadi kartu per baris; delapan kolom butuh ~925px. */
    @media (max-width: 991.98px) {
        .rp-page .rp-table-wrap {
            overflow: visible;
        }

        .rp-page .rp-table > thead {
            display: none;
        }

        .rp-page .rp-table,
        .rp-page .rp-table > tbody,
        .rp-page .rp-table > tbody > tr,
        .rp-page .rp-table > tbody > tr > td {
            display: block;
            width: 100%;
        }

        .rp-page .rp-table > tbody > tr {
            padding: 12px 16px;
            border-top: 1px solid var(--rp-rule);
        }

        .rp-page .rp-table > tbody > tr:first-child {
            border-top: 0;
        }

        .rp-page .rp-table > tbody > tr > td {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            padding: 5px 0;
            border: 0;
            text-align: right;
        }

        .rp-page .rp-table > tbody > tr > td::before {
            content: attr(data-label);
            flex: none;
            color: var(--rp-muted);
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-align: left;
            text-transform: uppercase;
        }

        .rp-page .rp-table > tbody > tr > td:first-child {
            padding-top: 0;
            text-align: left;
        }

        .rp-page .rp-table > tbody > tr > td:last-child {
            justify-content: flex-end;
            padding-top: 10px;
        }

        .rp-page .rp-table > tbody > tr > td:last-child::before {
            display: none;
        }

        .rp-page .rp-day {
            align-items: flex-end;
        }
    }

    /* Tombol kustomisasi template menutupi kolom aksi di kanan bawah. */
    .customizer-btn {
        display: none !important;
    }

    @media (prefers-reduced-motion: reduce) {
        .rp-page *,
        .rp-page *::before,
        .rp-page *::after,
        .rp-modal *,
        .rp-modal *::before,
        .rp-modal *::after {
            transition-duration: .01ms !important;
            animation-duration: .01ms !important;
        }
    }
</style>
