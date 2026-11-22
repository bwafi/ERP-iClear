<style>
/* ==========================================================================
   Kas Masuk — tema scoped `.km-*`
   Satu aksen (primary), tanpa gradien. Semua warna abu memakai token agar
   kontras teks muted tetap >= 4.5:1 di atas permukaan terang.
   ========================================================================== */
.km-page {
    --km-ink: #0f172a;
    --km-ink-2: #475569;
    --km-ink-3: #64748b;
    --km-line: #e2e8f0;
    --km-surface: var(--bs-body-bg, #fff);
    --km-raise: #f8fafc;
    --km-r: 1.125rem;
    --km-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 12px 32px -24px rgba(15, 23, 42, .35);
    color: var(--km-ink);
}

.km-page .km-masthead {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1rem;
    background: var(--km-surface);
    border: 1px solid var(--km-line);
    border-radius: var(--km-r);
    box-shadow: var(--km-shadow);
}

.km-page .km-masthead h1 {
    margin: .15rem 0 0;
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: -.01em;
}

.km-page .km-masthead .breadcrumb {
    margin: 0;
    font-size: .8125rem;
}

.km-page .km-masthead .breadcrumb-item.active {
    color: var(--km-ink-3);
}

.km-page .km-scope-readout {
    font-size: .75rem;
    color: var(--km-ink-3);
    text-align: right;
    min-height: 1rem;
}

.km-page .km-deck {
    background: var(--km-surface);
    border: 1px solid var(--km-line);
    border-radius: var(--km-r);
    box-shadow: var(--km-shadow);
    overflow: hidden;
}

/* ── Command bar ─────────────────────────────────────────────────────── */
.km-page .km-cmdbar {
    display: grid;
    grid-template-columns: minmax(220px, 2fr) repeat(4, minmax(130px, 1fr)) auto;
    gap: .75rem;
    align-items: end;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--km-line);
}

.km-page .km-field-label {
    display: block;
    margin-bottom: .25rem;
    font-size: .6875rem;
    font-weight: 600;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--km-ink-3);
}

.km-page .km-search {
    position: relative;
}

.km-page .km-search .form-control {
    padding-left: 2.25rem;
    padding-right: 2.25rem;
}

.km-page .km-search-icon {
    position: absolute;
    left: .75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--km-ink-3);
    pointer-events: none;
}

.km-page .km-search .km-clear {
    position: absolute;
    right: .5rem;
    top: 50%;
    transform: translateY(-50%);
    display: none;
    padding: .125rem;
    border: 0;
    background: transparent;
    color: var(--km-ink-3);
    line-height: 1;
}

.km-page .km-search[data-filled="true"] .km-clear {
    display: inline-flex;
}

.km-page .km-cmdbar-actions {
    display: flex;
    gap: .5rem;
    align-items: center;
    justify-content: flex-end;
}

.km-page .km-cmdbar-actions .btn {
    display: inline-flex;
    align-items: center;
    gap: .375rem;
    white-space: nowrap;
}

/* ── Ringkasan ───────────────────────────────────────────────────────── */
.km-page .km-sum {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    border-bottom: 1px solid var(--km-line);
}

.km-page .km-sum-cell {
    padding: .875rem 1.25rem;
}

.km-page .km-sum-cell + .km-sum-cell {
    border-left: 1px solid var(--km-line);
}

.km-page .km-sum-cell span {
    display: block;
    font-size: .6875rem;
    font-weight: 600;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--km-ink-3);
}

.km-page .km-sum-cell b {
    display: block;
    margin-top: .125rem;
    font-size: 1.125rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.km-page .km-sum-cell small {
    display: block;
    font-size: .75rem;
    color: var(--km-ink-3);
}

/* ── Tabel ───────────────────────────────────────────────────────────── */
.km-page .km-tablewrap {
    overflow-x: auto;
}

.km-page .km-table {
    width: 100%;
    margin: 0;
    border-collapse: separate;
    border-spacing: 0;
}

.km-page .km-table thead th {
    padding: .75rem 1rem;
    border-bottom: 1px solid var(--km-line);
    background: var(--km-raise);
    font-size: .6875rem;
    font-weight: 600;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--km-ink-3);
    white-space: nowrap;
}

.km-page .km-table tbody td {
    padding: .75rem 1rem;
    border-bottom: 1px solid var(--km-line);
    vertical-align: middle;
    font-size: .875rem;
}

.km-page .km-table tbody tr:hover td {
    background: var(--km-raise);
}

.km-page .km-table tbody tr:last-child td {
    border-bottom: 0;
}

.km-page .km-num,
.km-page .km-amount {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.km-page .km-table .is-amount {
    text-align: right;
}

.km-page .km-mono {
    font-family: var(--bs-font-monospace, ui-monospace, SFMono-Regular, Menlo, monospace);
    font-size: .8125rem;
}

.km-page .km-sub {
    display: block;
    font-size: .75rem;
    color: var(--km-ink-3);
}

.km-page .km-clip {
    display: block;
    max-width: 28ch;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.km-page .km-tag {
    display: inline-block;
    padding: .0625rem .375rem;
    border: 1px solid var(--km-line);
    border-radius: 999px;
    font-size: .6875rem;
    color: var(--km-ink-2);
    background: var(--km-raise);
}

.km-page .km-rowbtn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    padding: 0;
    border: 1px solid transparent;
    border-radius: .5rem;
    background: transparent;
    color: var(--km-ink-2);
}

.km-page .km-rowbtn:hover,
.km-page .km-rowbtn:focus-visible {
    border-color: var(--km-line);
    background: var(--km-raise);
    color: var(--km-ink);
}

.km-page .km-rowbtn.is-danger:hover,
.km-page .km-rowbtn.is-danger:focus-visible {
    color: var(--bs-danger);
    border-color: var(--bs-danger-border-subtle, #f1aeb5);
}

/* ── Kosong & pager ──────────────────────────────────────────────────── */
.km-page .km-empty {
    display: none;
    padding: 3rem 1.5rem;
    text-align: center;
}

.km-page .km-empty.is-shown {
    display: block;
}

.km-page .km-empty .km-tile {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: .75rem;
    background: var(--km-raise);
    border: 1px solid var(--km-line);
    color: var(--km-ink-3);
}

.km-page .km-empty h3 {
    margin: .75rem 0 .25rem;
    font-size: 1rem;
    font-weight: 600;
}

.km-page .km-empty p {
    margin: 0 auto;
    max-width: 42ch;
    color: var(--km-ink-3);
    font-size: .875rem;
}

.km-page .km-pager {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .75rem 1.25rem;
    border-top: 1px solid var(--km-line);
}

.km-page .km-pager .form-select {
    width: auto;
}

.km-page .km-pager .dataTables_info {
    font-size: .8125rem;
    color: var(--km-ink-3);
    padding: 0;
}

.km-page .km-pager .pagination {
    margin: 0;
}

/* ── Modal ───────────────────────────────────────────────────────────── */
/* Pola yang sama dengan modal Kas Keluar: 640px, kartu per akun, footer
   sticky berisi total draf. */
.km-modal .modal-dialog {
    max-width: var(--km-modal-w, 640px);
}

#kmDeleteModal {
    --km-modal-w: 340px;
}

.km-modal .modal-content {
    border: 1px solid var(--km-line);
    border-radius: var(--km-r);
    box-shadow: var(--km-shadow);
}

.km-modal .modal-header {
    align-items: flex-start;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--km-line);
}

.km-modal .modal-footer {
    border-color: var(--km-line);
}

.km-modal .modal-title {
    margin: 0;
    font-size: 1.0625rem;
    font-weight: 600;
    line-height: 1.25;
}

.km-modal .km-modal-sub {
    display: block;
    margin-top: .1875rem;
    font-size: .8125rem;
    font-weight: 400;
    color: var(--km-ink-3);
}

.km-modal .modal-body {
    padding: 1.25rem;
}

.km-modal form > * + * {
    margin-top: 1rem;
}

.km-modal .km-modal-foot {
    position: sticky;
    bottom: 0;
    margin: 1.25rem -1.25rem -1.25rem;
    padding: 1rem 1.25rem;
    background: var(--km-surface);
    border-top: 1px solid var(--km-line);
}

.km-modal .km-field-label {
    display: block;
    margin-bottom: .375rem;
    font-size: .75rem;
    font-weight: 600;
    letter-spacing: .02em;
    text-transform: uppercase;
    color: var(--km-ink-3);
}

.km-modal .km-tx-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .75rem;
}

.km-modal .km-tx-grid .is-full {
    grid-column: 1 / -1;
}

/* Satu kartu = satu akun. Kartu vertikal, bukan tabel delapan kolom yang
   harus digeser ke samping di ponsel. */
.km-modal .km-tx {
    padding: .875rem;
    border: 1px solid var(--km-line);
    border-radius: 12px;
    background: var(--km-raise);
}

.km-modal .km-tx + .km-tx {
    margin-top: .75rem;
}

.km-modal .km-tx-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    margin-bottom: .75rem;
}

.km-modal .km-tx-head span {
    font-size: .6875rem;
    font-weight: 600;
    letter-spacing: .03em;
    text-transform: uppercase;
    color: var(--km-ink-3);
}

.km-modal .km-tx .form-control,
.km-modal .km-tx .form-select {
    font-size: .875rem;
}

.km-modal .km-subhead {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    margin-top: 1.25rem;
}

.km-modal .km-subhead .km-field-label {
    margin: 0;
}

.km-modal .km-note {
    display: flex;
    gap: .5rem;
    margin: .75rem 0 0;
    font-size: .8125rem;
    color: var(--km-ink-3);
}

.km-modal .km-total-line {
    display: flex;
    align-items: baseline;
    justify-content: flex-end;
    gap: .75rem;
    padding-top: .875rem;
}

.km-modal .km-total-line span {
    font-size: .8125rem;
    color: var(--km-ink-3);
}

.km-modal .km-total-line b {
    font-size: 1.375rem;
    font-weight: 600;
    color: var(--km-ink);
}

.km-modal .km-del-body {
    display: flex;
    flex-wrap: wrap;
    gap: .25rem .5rem;
    align-items: baseline;
    font-size: .875rem;
}

/* Select2 di dalam modal. Geometri panah mengikuti catatan yang sama dengan
   modal Kas Keluar: template mengunci panah di `top: 24px`, jadi kotak
   selection perlu dikembalikan ke posisi yang benar. */
.km-modal .select2-container--default .select2-selection--single {
    height: 38px;
    padding: .375rem .75rem;
    border-color: var(--km-line);
    border-radius: .375rem;
}

.km-modal .select2-container--default .select2-selection--single .select2-selection__rendered {
    padding: 0 30px 0 0;
    line-height: 1.5;
}

body .km-modal .select2-container--default .select2-selection--single .select2-selection__arrow {
    top: 0;
    right: 0;
    width: 26px;
}

body .km-modal .select2-container--default .select2-selection--single .select2-selection__clear {
    margin-right: 28px;
}

.km-modal .select2-container--default .select2-selection--single.is-invalid {
    border-color: var(--bs-form-invalid-border-color, #dc3545);
}

/* ── Responsif: tabel menjadi kartu berlabel ─────────────────────────── */
@media (max-width: 991.98px) {
    .km-page .km-cmdbar {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .km-page .km-search,
    .km-page .km-cmdbar-actions {
        grid-column: 1 / -1;
    }

    .km-page .km-sum {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .km-page .km-sum-cell:nth-child(3) {
        border-left: 0;
        border-top: 1px solid var(--km-line);
    }

    .km-page .km-sum-cell:nth-child(4) {
        border-top: 1px solid var(--km-line);
    }
}

@media (max-width: 767.98px) {
    .km-page .km-masthead {
        padding: 1rem;
    }

    .km-page .km-cmdbar {
        grid-template-columns: minmax(0, 1fr);
        padding: .875rem;
    }

    .km-page .km-sum {
        grid-template-columns: minmax(0, 1fr);
    }

    .km-page .km-sum-cell + .km-sum-cell {
        border-left: 0;
        border-top: 1px solid var(--km-line);
    }

    .km-page .km-tablewrap {
        overflow: visible;
        padding: .5rem;
    }

    .km-page .km-table thead {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    .km-page .km-table,
    .km-page .km-table tbody,
    .km-page .km-table tr,
    .km-page .km-table td {
        display: block;
        width: 100%;
    }

    .km-page .km-table tbody tr {
        margin-bottom: .625rem;
        padding: .25rem .75rem;
        border: 1px solid var(--km-line);
        border-radius: .75rem;
        background: var(--km-surface);
    }

    .km-page .km-table tbody td {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: .375rem 0;
        border-bottom: 0;
        text-align: right;
    }

    .km-page .km-table tbody td::before {
        content: attr(data-label);
        flex: 0 0 auto;
        font-size: .6875rem;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--km-ink-3);
        text-align: left;
    }

    .km-page .km-table .km-clip {
        max-width: 60%;
        margin-left: auto;
    }

    .km-page .km-pager {
        flex-wrap: wrap;
        padding: .75rem;
    }

    .km-modal .modal-dialog {
        max-width: none;
        margin: .5rem;
    }
}

@media (max-width: 575.98px) {
    .km-modal .km-tx-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (prefers-reduced-motion: reduce) {
    .km-page *,
    .km-modal * {
        transition: none !important;
        animation: none !important;
        scroll-behavior: auto !important;
    }
}
</style>
