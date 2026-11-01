<?php
/**
 * Tema halaman Rekening & Saldo Awal.
 *
 * Halaman ini memakai dunia "Lembar Posisi Kas" yang sama dengan dashboard:
 * satu lembar datar, garis rambut, label mikro kapital, angka tabular. Bagian
 * yang khas di sini hanya ringkasan rekening, baris "Catat & atur" yang bisa
 * dibuka, dan tabel buku rekening. Tabel memakai .kbx-table supaya perilaku
 * mobile-nya (baris jadi blok berlabel) tidak ditulis dua kali.
 */
?>
<style>
    /* ===== Toolbar ===== */
    .kba-filter {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 0.75rem 1rem;
    }

    .kba-field--search { width: 16rem; }
    .kba-field--search .kbx-control { width: 100%; }

    .kba-toolbar .kbx-actions { margin-left: auto; }

    /* ===== Ringkasan ===== */
    .kba-summary-body {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
    }

    .kba-summary-main { padding: 1.25rem 1.5rem 1.375rem; }

    .kba-total {
        margin-top: 0.25rem;
        font-size: 2rem;
        font-weight: 600;
        line-height: 1.1;
        letter-spacing: -0.03em;
    }

    .kba-total-note {
        margin-top: 0.25rem;
        font-size: 0.75rem;
        color: var(--kbx-muted);
    }

    .kba-facts {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        margin: 0;
        border-left: 1px solid var(--kbx-line);
    }

    .kba-facts > div {
        padding: 0.75rem 1.25rem;
        border-top: 1px solid var(--kbx-line);
    }

    .kba-facts > div:nth-child(-n + 2) { border-top: 0; }
    .kba-facts > div:nth-child(even) { border-left: 1px solid var(--kbx-line); }

    .kba-facts dt {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: var(--kbx-muted);
    }

    .kba-facts dd {
        margin: 0.125rem 0 0;
        font-size: 1rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.01em;
    }

    .kba-summary-foot {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 0.25rem 1.25rem;
        padding: 0.625rem 1.5rem;
        border-top: 1px solid var(--kbx-line);
        background: var(--kbx-soft);
        font-size: 0.75rem;
        color: var(--kbx-muted);
    }

    .kba-summary-foot b {
        font-weight: 600;
        color: var(--kbx-ink);
    }

    .kba-summary-foot .is-over {
        font-weight: 600;
        color: var(--bs-danger-text-emphasis, #58151c);
    }

    [data-bs-theme="dark"] .kba-summary-foot .is-over { color: #fb977d; }

    /* ===== Catat & atur ===== */
    .kba-record {
        margin-top: 1.25rem;
        background: var(--kbx-sheet);
        border: 1px solid var(--kbx-line);
        border-radius: var(--kbx-radius);
        overflow: hidden;
    }

    .kba-section-head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.5rem 1.5rem;
        padding: 1rem 1.5rem 0.75rem;
    }

    .kba-section-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        letter-spacing: -0.01em;
    }

    .kba-section-sub {
        margin: 0.125rem 0 0;
        max-width: 72ch;
        font-size: 0.75rem;
        color: var(--kbx-muted);
    }

    .kba-step { border-top: 1px solid var(--kbx-line); }

    .kba-step > summary {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.875rem 1.5rem;
        cursor: pointer;
        list-style: none;
    }

    .kba-step > summary::-webkit-details-marker { display: none; }
    .kba-step > summary:hover { background: var(--kbx-soft); }

    .kba-step > summary:focus-visible {
        outline: 2px solid var(--bs-primary, #0d6efd);
        outline-offset: -2px;
    }

    .kba-step-no {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 1.5rem;
        height: 1.5rem;
        border: 1px solid var(--kbx-line-strong);
        border-radius: 2px;
        font-size: 0.75rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        color: var(--kbx-muted);
    }

    .kba-step-copy {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        gap: 0.125rem;
        min-width: 0;
    }

    .kba-step-title {
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--kbx-ink);
    }

    .kba-step-hint {
        font-size: 0.75rem;
        color: var(--kbx-muted);
    }

    .kba-step-state {
        flex: 0 0 auto;
        font-size: 0.75rem;
        font-variant-numeric: tabular-nums;
        color: var(--kbx-muted);
        white-space: nowrap;
    }

    .kba-chevron {
        flex: 0 0 auto;
        font-size: 1rem;
        color: var(--kbx-muted);
        transition: transform 0.18s ease;
    }

    .kba-step[open] .kba-chevron { transform: rotate(180deg); }

    /* Indentasi isi mengikuti judul langkah, bukan nomornya, supaya form
       terbaca sebagai isi baris yang sedang dibuka. */
    .kba-step-body { padding: 0.25rem 1.5rem 1.25rem 3.75rem; }

    .kba-step-note {
        margin: 0 0 0.75rem;
        max-width: 68ch;
        font-size: 0.75rem;
        line-height: 1.5;
        color: var(--kbx-muted);
    }

    /* ===== Form ===== */
    /* Form tetap pada measure baca, bukan melebar selebar lembar. */
    .kba-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem 1rem;
        max-width: 48rem;
    }

    .kba-field {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        min-width: 0;
    }

    .kba-field--full { grid-column: 1 / -1; }
    .kba-field .kbx-control { width: 100%; }

    .kba-req { color: var(--bs-danger, #dc3545); }

    .kba-form-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.75rem 1rem;
        max-width: 48rem;
        margin-top: 1rem;
    }

    .kba-link-danger {
        padding: 0;
        border: 0;
        background: none;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--bs-danger-text-emphasis, #842029);
        text-decoration: underline;
    }

    [data-bs-theme="dark"] .kba-link-danger { color: #fb977d; }

    .kba-switch { margin: 0; }
    .kba-switch .form-check-label { font-size: 0.8125rem; }

    .kba-hint {
        margin: 0.125rem 0 0;
        font-size: 0.6875rem;
        line-height: 1.5;
        color: var(--kbx-muted);
    }

    /* ===== Opening KAS ===== */
    .kba-open-table {
        width: 100%;
        margin: 0 0 0.75rem;
        border-collapse: collapse;
    }

    .kba-open-table th {
        padding: 0.5rem 0.75rem 0.5rem 0;
        border-bottom: 1px solid var(--kbx-line);
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        text-align: left;
        color: var(--kbx-muted);
    }

    .kba-open-table td {
        padding: 0.5rem 0.75rem 0.5rem 0;
        border-bottom: 1px solid var(--kbx-line);
        font-size: 0.8125rem;
        vertical-align: top;
    }

    .kba-open-table th.num,
    .kba-open-table td.num { text-align: right; }

    .kba-open-table td.num {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .kba-open-table tr:last-child td { border-bottom: 0; }

    /* ===== Buku rekening ===== */
    .kba-col-action { width: 1%; }

    .kba-cell-action {
        text-align: right;
        white-space: nowrap;
    }

    .kba-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.875rem;
        height: 1.875rem;
        padding: 0;
        border: 1px solid var(--kbx-line);
        border-radius: 2px;
        background: transparent;
        color: var(--kbx-muted);
    }

    .kba-icon-btn:hover {
        color: var(--kbx-ink);
        border-color: var(--kbx-line-strong);
        background: var(--kbx-soft);
    }

    .kba-icon-btn:focus-visible {
        outline: 2px solid var(--bs-primary, #0d6efd);
        outline-offset: 2px;
    }

    .kba-icon-btn iconify-icon { font-size: 0.875rem; }

    .kba-tag {
        display: inline-block;
        margin-left: 0.375rem;
        padding: 0 0.3rem;
        border: 1px solid var(--kbx-line);
        border-radius: 2px;
        font-size: 0.625rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--kbx-muted);
        vertical-align: 0.0625rem;
        white-space: nowrap;
    }

    .kba-tag.is-ho {
        color: var(--kbx-ink);
        border-color: var(--kbx-line-strong);
    }

    .kba-status {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        margin-left: 0.5rem;
        font-size: 0.6875rem;
        font-weight: 600;
        color: var(--bs-danger-text-emphasis, #842029);
        white-space: nowrap;
    }

    .kba-status::before {
        content: "";
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: currentColor;
    }

    [data-bs-theme="dark"] .kba-status { color: #fb977d; }

    .kba-row-alloc > td {
        padding-top: 0;
        padding-bottom: 0.625rem;
    }

    .kba-alloc {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.375rem 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px solid var(--kbx-line);
    }

    .kba-alloc-label {
        font-size: 0.625rem;
        font-weight: 600;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: var(--kbx-muted);
    }

    .kba-chip {
        display: inline-flex;
        align-items: baseline;
        gap: 0.375rem;
        padding: 0.0625rem 0.375rem;
        border: 1px solid var(--kbx-line);
        border-radius: 2px;
        font-size: 0.75rem;
        color: var(--kbx-muted);
    }

    .kba-chip b {
        font-weight: 600;
        color: var(--kbx-ink);
        font-variant-numeric: tabular-nums;
    }

    /* ===== Responsif ===== */
    @media (max-width: 991.98px) {
        .kba-summary-body { grid-template-columns: minmax(0, 1fr); }

        .kba-facts {
            border-top: 1px solid var(--kbx-line);
            border-left: 0;
        }

        .kba-facts > div { border-top: 0; }
        .kba-facts > div:nth-child(n + 3) { border-top: 1px solid var(--kbx-line); }
    }

    @media (max-width: 767.98px) {
        .kba-toolbar { align-items: stretch; flex-direction: column; }
        .kba-filter { width: 100%; }
        .kba-field--search { width: 100%; }
        .kba-toolbar .kbx-actions { margin-left: 0; }

        .kba-summary-main,
        .kba-section-head,
        .kba-summary-foot { padding-left: 1rem; padding-right: 1rem; }

        .kba-step > summary { padding-left: 1rem; padding-right: 1rem; }
        .kba-step-body { padding: 0.25rem 1rem 1rem; }
        .kba-step-state { display: none; }

        .kba-form-grid { grid-template-columns: minmax(0, 1fr); }
        .kba-total { font-size: 1.75rem; }

        .kba-table td.kba-cell-action {
            display: flex;
            justify-content: flex-end;
            padding-top: 0.5rem;
        }

        .kba-table td.kba-cell-action::before { display: none; }
        .kba-table tfoot .kba-cell-action { display: none; }
        .kba-row-alloc > td:first-child { margin-bottom: 0; }

        /* Opening laci ikut pola blok berlabel yang dipakai buku rekening. */
        .kba-open-table thead { display: none; }
        .kba-open-table,
        .kba-open-table tbody,
        .kba-open-table tr,
        .kba-open-table td { display: block; }

        .kba-open-table tr {
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--kbx-line);
        }

        .kba-open-table tr:last-child { border-bottom: 0; }

        .kba-open-table td {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.125rem 0;
            border: 0;
            text-align: right;
        }

        .kba-open-table td::before {
            content: attr(data-label);
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: var(--kbx-muted);
        }

        .kba-open-table td:first-child {
            display: block;
            margin-bottom: 0.25rem;
            text-align: left;
        }

        .kba-open-table td:first-child::before { display: none; }

        /* Strip hak unit menempel pada baris rekeningnya, bukan baris sendiri. */
        .kba-table tr.kba-row-alloc {
            padding: 0 1rem 0.75rem;
            border-bottom: 1px solid var(--kbx-line);
        }

        .kba-table tr.kba-row-alloc > td { padding: 0; border: 0; }
        .kba-alloc { padding-top: 0.5rem; }
    }

    @media (prefers-reduced-motion: reduce) {
        .kba-chevron { transition: none; }
    }
</style>
