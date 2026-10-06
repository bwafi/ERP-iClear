<?php

/**
 * Design system halaman Stok Opname (/stok_opname).
 *
 * Satu-satunya sumber warna, ukuran, dan komponen untuk halaman ini.
 * Semua nilai warna diambil dari token template (resolved CSS var), jadi
 * ikut mode gelap/terang dan ikut pergantian color-theme aplikasi tanpa
 * perlu duplikasi nilai hex.
 *
 * Skala font mengikuti utilitas `fs-*` template (nilai rem identik):
 *
 *   0.75rem / fs-2   label papan, header tabel, chip, keterangan
 *   0.875rem / fs-3  isi tabel, input, teks bantuan
 *   1rem     / fs-4  judul kartu
 *   1.125rem / fs-5  angka papan
 *
 * Catatan: skala `fs-*` template terbalik dari Bootstrap 5.3 standar
 * (fs-1 = terkecil). Jangan memakai fs-6 ke atas untuk teks kecil.
 *
 * Satu aturan elevasi saja: bayangan `.card` bawaan template
 * (0px 2px 6px rgba(37,83,185,.1)) — panel di sini sengaja memakai
 * `.card` biasa supaya tidak terlihat sebagai sistem visual terpisah.
 */
?>

<style>
    /* ===== 1. Token ===== */
    .stok-opname,
    #modalMulai,
    #modalFinalisasi,
    #modalReopen {
        --so-surface: var(--bs-card-bg, var(--bs-body-bg, #fff));
        --so-canvas: var(--bs-body-bg, #f0f5f9);
        --so-rule: var(--bs-border-color, #e6ecf1);
        /* Netral sekunder pekat: kontras 5.85:1 di terang, 6.42:1 di gelap */
        --so-muted: #5b6672;
        /* Teks isi permukaan: var(--bs-body-color) template hanya 4.38:1
           (di bawah AA 4.5), jadi di terang dipakai nilai pekat sendiri. */
        --so-ink: #626d7b;
        --so-hair: rgba(var(--bs-body-color-rgb, 33, 37, 41), .09);
        --so-radius: .75rem;
        --so-shadow: 0 2px 6px rgba(37, 83, 185, .1);
    }

    html[data-bs-theme="dark"] .stok-opname,
    html[data-bs-theme="dark"] #modalMulai,
    html[data-bs-theme="dark"] #modalFinalisasi,
    html[data-bs-theme="dark"] #modalReopen {
        --so-muted: #8fa0b8;
        --so-ink: var(--bs-body-color, #cfd8e3);
    }

    .stok-opname .text-muted,
    #modalMulai .text-muted,
    #modalFinalisasi .text-muted,
    #modalReopen .text-muted {
        color: var(--so-muted) !important;
    }

    /* Teks yang mewarisi warna dari body (td, kartu, keterangan) memakai
       tinta permukaan, bukan warna body yang lebih pudar. */
    .stok-opname,
    #modalMulai,
    #modalFinalisasi,
    #modalReopen {
        color: var(--so-ink);
    }

    .stok-opname .form-control,
    .stok-opname .form-select {
        color: var(--so-ink);
    }

    .stok-opname .card,
    .stok-opname .modal-content {
        color: var(--so-ink);
    }

    /* Tombol hijau template memakai teks putih (kontras 1.96:1); di
       permukaan ini teksnya digelapkan agar lolos AA. */
    .stok-opname .btn-success,
    #modalFinalisasi .btn-success {
        color: #0b2517;
    }

    .stok-opname .btn-success:hover,
    .stok-opname .btn-success:focus,
    #modalFinalisasi .btn-success:hover,
    #modalFinalisasi .btn-success:focus {
        color: #0b2517;
    }

    /* Tombol primer template #0085db dengan teks putih hanya 3.9:1;
       di permukaan ini dipakai varian sedikit lebih pekat (4.73:1),
       masih satu keluarga warna dengan tombol lain di aplikasi. */
    .stok-opname .btn-primary,
    #modalMulai .btn-primary {
        background-color: #0077c4;
        border-color: #0077c4;
        color: #fff;
    }

    .stok-opname .btn-primary:hover,
    .stok-opname .btn-primary:focus,
    .stok-opname .btn-primary:active,
    #modalMulai .btn-primary:hover,
    #modalMulai .btn-primary:focus,
    #modalMulai .btn-primary:active {
        background-color: #006fb6;
        border-color: #006fb6;
        color: #fff;
    }

    /* Tombol abu template (putih di atas #707a82 = 4.38:1) dinaikkan
       kontrasnya di dalam modal permukaan ini. */
    #modalMulai .modal-content .btn-secondary,
    #modalFinalisasi .modal-content .btn-secondary,
    #modalReopen .modal-content .btn-secondary {
        background-color: #5b6672;
        border-color: #5b6672;
        color: #fff;
    }

    #modalMulai .modal-content .btn-secondary:hover,
    #modalFinalisasi .modal-content .btn-secondary:hover,
    #modalReopen .modal-content .btn-secondary:hover,
    #modalMulai .modal-content .btn-secondary:focus,
    #modalFinalisasi .modal-content .btn-secondary:focus,
    #modalReopen .modal-content .btn-secondary:focus {
        background-color: #4f5966;
        border-color: #4f5966;
        color: #fff;
    }

    /* Breadcrumb & badge status: teks primer template (#0085db) di atas
       alas biru muda hanya 3.5–3.9:1. */
    .stok-opname .breadcrumb-item,
    .stok-opname .breadcrumb-item.active,
    .stok-opname .breadcrumb-item > a {
        color: var(--so-muted);
    }

    .stok-opname .badge.bg-primary-subtle.text-primary {
        color: #006cb6 !important;
    }

    html[data-bs-theme="dark"] .stok-opname .badge.bg-primary-subtle.text-primary {
        color: #3a9ce4 !important;
    }

    /* ===== 2. Alas halaman: seleksi, fokus, scrollbar ===== */
    .stok-opname ::selection {
        background: rgba(var(--bs-primary-rgb, 0, 133, 219), .22);
    }

    .stok-opname :focus-visible {
        outline: 2px solid var(--bs-primary, #0085db);
        outline-offset: 2px;
    }

    .stok-opname .so-scroll {
        max-height: 65vh;
        scrollbar-width: thin;
        scrollbar-color: rgba(var(--bs-body-color-rgb, 33, 37, 41), .3) transparent;
    }

    .stok-opname .so-scroll::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }

    .stok-opname .so-scroll::-webkit-scrollbar-track {
        background: transparent;
    }

    .stok-opname .so-scroll::-webkit-scrollbar-thumb {
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .28);
        border-radius: 999px;
        border: 3px solid transparent;
        background-clip: content-box;
    }

    .stok-opname .so-scroll::-webkit-scrollbar-thumb:hover {
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .45);
        background-clip: content-box;
    }

    /* ===== 3. Header halaman ===== */
    .stok-opname .so-head .card-body {
        padding: 1.1rem 1.25rem;
    }

    .stok-opname .so-head h4 {
        font-size: 1.25rem;
        letter-spacing: -.015em;
    }

    .stok-opname .so-head p {
        font-size: .8125rem;
        max-width: 68ch;
    }

    .stok-opname .so-scope {
        border-top: 1px solid var(--so-hair);
        padding: .75rem 1.25rem;
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .02);
        border-bottom-left-radius: var(--so-radius);
        border-bottom-right-radius: var(--so-radius);
    }

    .stok-opname .so-scope .form-label {
        font-size: .75rem;
        font-weight: 600;
        color: var(--so-muted);
        margin-bottom: .25rem;
    }

    /* ===== 4. Papan kendali (empat angka periode) =====
       Bukan empat kartu metrik yang sama besar: satu panel utuh,
       dipisah garis rambut, supaya terbaca sebagai papan instrumen. */
    .stok-opname .so-board .card-body {
        padding: 0;
    }

    .stok-opname .so-board__grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .stok-opname .so-cell {
        padding: 1.1rem 1.25rem 1rem;
        border-left: 1px solid var(--so-hair);
        display: flex;
        flex-direction: column;
        gap: .3rem;
        min-width: 0;
    }

    .stok-opname .so-cell:first-child {
        border-left: 0;
    }

    .stok-opname .so-cell__label {
        font-size: .75rem;
        font-weight: 600;
        color: var(--so-muted);
    }

    .stok-opname .so-cell__value {
        font-size: clamp(1.5rem, 1.9vw, 1.75rem);
        font-weight: 650;
        line-height: 1.1;
        letter-spacing: -.02em;
        font-variant-numeric: tabular-nums;
        color: var(--so-ink);
    }

    .stok-opname .so-cell__value .so-den {
        font-size: .9375rem;
        font-weight: 500;
        color: var(--so-muted);
        letter-spacing: 0;
    }

    .stok-opname .so-cell__value.is-pos { color: var(--bs-success); }
    .stok-opname .so-cell__value.is-neg { color: var(--bs-danger); }
    .stok-opname .so-cell__value.is-empty { color: var(--so-muted); }

    .stok-opname .so-cell__note {
        font-size: .75rem;
        color: var(--so-muted);
        line-height: 1.35;
    }

    .stok-opname .so-cell__note .bi,
    .stok-opname .so-cell__note iconify-icon {
        color: var(--bs-primary);
        vertical-align: -.12em;
    }

    .stok-opname .so-meter {
        height: 6px;
        border-radius: 999px;
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .1);
        overflow: hidden;
        margin: .15rem 0 .1rem;
    }

    .stok-opname .so-meter__bar {
        height: 100%;
        border-radius: 999px;
        background: var(--bs-primary);
    }

    @media (max-width: 991.98px) {
        .stok-opname .so-board__grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .stok-opname .so-cell:nth-child(odd) {
            border-left: 0;
        }

        .stok-opname .so-cell:nth-child(n + 3) {
            border-top: 1px solid var(--so-hair);
        }
    }

    @media (max-width: 575.98px) {
        .stok-opname .so-cell {
            padding: .9rem 1rem .85rem;
        }
    }

    /* ===== 5. Baris status periode ===== */
    .stok-opname .so-status .card-body {
        padding: 1.1rem 1.25rem;
    }

    .stok-opname .so-status__top {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .75rem 1rem;
    }

    .stok-opname .so-status__id {
        display: flex;
        align-items: center;
        gap: .75rem;
        flex-wrap: wrap;
        min-width: 0;
    }

    .stok-opname .so-status__unit h6 {
        font-size: .9375rem;
        margin: 0;
    }

    .stok-opname .so-status__unit small {
        font-size: .75rem;
        color: var(--so-muted);
        font-variant-numeric: tabular-nums;
    }

    .stok-opname .so-status__actions {
        margin-left: auto;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .5rem;
    }

    /* Chip pembekuan — momen kunci halaman ini. */
    .stok-opname .so-freeze {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .35rem .7rem;
        border-radius: 999px;
        border: 1px solid rgba(var(--bs-primary-rgb, 0, 133, 219), .3);
        background: rgba(var(--bs-primary-rgb, 0, 133, 219), .08);
        font-size: .75rem;
        color: var(--so-ink);
        line-height: 1.3;
        max-width: 100%;
    }

    .stok-opname .so-freeze iconify-icon,
    .stok-opname .so-freeze .bi {
        color: var(--bs-primary);
        flex: 0 0 auto;
    }

    .stok-opname .so-freeze strong {
        font-weight: 650;
        font-variant-numeric: tabular-nums;
    }

    .stok-opname .so-status__meter {
        margin-top: 1rem;
        padding-top: .9rem;
        border-top: 1px solid var(--so-hair);
    }

    .stok-opname .so-status__meter .so-meter {
        height: 8px;
        margin: .4rem 0 .35rem;
    }

    .stok-opname .so-status__meter .so-progress-text {
        font-size: .8125rem;
        font-variant-numeric: tabular-nums;
    }

    .stok-opname .so-status__meter .so-hint {
        font-size: .75rem;
        display: inline-flex;
        align-items: center;
        gap: .35rem;
    }

    .stok-opname .so-finalised {
        font-size: .75rem;
        color: var(--so-muted);
        font-variant-numeric: tabular-nums;
    }

    /* ===== 6. Ledger: kepala, baris perintah, tabel ===== */
    .stok-opname .so-ledger > .card-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .5rem 1rem;
        padding: .9rem 1.25rem;
        background: transparent;
        border-bottom: 1px solid var(--so-hair);
    }

    .stok-opname .so-ledger > .card-header h5 {
        font-size: 1rem;
        letter-spacing: -.01em;
    }

    .stok-opname .so-ledger > .card-body {
        padding: 1rem 1.25rem 1.1rem;
    }

    .stok-opname .so-command {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .6rem .75rem;
        margin-bottom: .85rem;
    }

    .stok-opname .so-command .so-spacer {
        margin-left: auto;
    }

    .stok-opname .so-filter {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 3px;
        padding: 3px;
        border: 1px solid var(--so-rule);
        border-radius: 999px;
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .04);
        max-width: 100%;
    }

    .stok-opname .so-filter .btn {
        font-size: .8125rem;
        font-weight: 600;
        border: 0;
        background: transparent;
        border-radius: 999px;
        padding: .3rem .75rem;
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        white-space: nowrap;
        box-shadow: none;
    }

    .stok-opname .so-filter .btn-outline-secondary {
        color: var(--so-muted);
    }

    .stok-opname .so-filter .btn-warning {
        background: var(--bs-warning, #ffc107);
        color: #1f2430;
    }

    .stok-opname .so-filter .btn-danger {
        /* Merah solid, bukan var(--bs-danger) yang di mode gelap menjadi
           salmon terang (putih di atasnya hanya 2.14:1) */
        background: #dc3545;
        color: #fff;
    }

    .stok-opname .so-filter .btn:hover:not(.btn-warning):not(.btn-danger) {
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .07);
        color: var(--so-ink);
    }

    .stok-opname .so-filter .btn:hover.btn-warning,
    .stok-opname .so-filter .btn:hover.btn-danger {
        filter: brightness(.96);
    }

    .stok-opname .so-filter .btn:focus-visible {
        outline: 2px solid var(--so-accent, #0085db);
        outline-offset: 1px;
    }

    .stok-opname .so-filter .badge {
        font-variant-numeric: tabular-nums;
        font-weight: 600;
        font-size: .6875rem;
    }

    .stok-opname .so-search {
        min-width: 15rem;
        max-width: 20rem;
    }

    .stok-opname .so-field {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
    }

    .stok-opname .so-field label {
        font-size: .75rem;
        color: var(--so-muted);
        margin: 0;
        white-space: nowrap;
    }

    .stok-opname .so-field .form-select,
    .stok-opname .so-search .form-control {
        font-size: .8125rem;
        border-radius: .5rem;
    }

    .stok-opname .so-state-chip {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        font-size: .75rem;
        font-weight: 600;
        padding: .3rem .65rem;
        border-radius: 999px;
        border: 1px solid var(--so-rule);
        color: var(--so-muted);
    }

    .stok-opname .so-state-chip.is-draft {
        color: var(--bs-warning-text-emphasis, #997404);
        background: var(--bs-warning-bg-subtle, #fff3cd);
        border-color: transparent;
    }

    .stok-opname .so-state-chip.is-final {
        color: var(--bs-success-text-emphasis, #146c43);
        background: var(--bs-success-bg-subtle, #d1e7dd);
        border-color: transparent;
    }

    .stok-opname .so-state-chip.is-idle {
        color: var(--so-muted);
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .06);
        border-color: transparent;
    }

    .stok-opname .so-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: .875rem;
        margin-bottom: 0;
    }

    .stok-opname .so-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--so-surface);
        color: var(--so-muted);
        font-size: .75rem;
        font-weight: 600;
        text-transform: uppercase;
        padding: .6rem .75rem;
        border-bottom: 1px solid var(--so-rule);
        white-space: nowrap;
    }

    .stok-opname .so-table tbody td {
        padding: .5rem .75rem;
        border-bottom: 1px solid var(--so-hair);
        vertical-align: middle;
    }

    .stok-opname .so-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .stok-opname .so-table tbody tr:hover > td {
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .035);
    }

    .stok-opname .so-table .so-num {
        font-variant-numeric: tabular-nums;
        text-align: center;
        font-weight: 650;
    }

    .stok-opname .so-table .so-idx {
        font-variant-numeric: tabular-nums;
        color: var(--so-muted);
        font-size: .75rem;
        text-align: right;
        width: 3rem;
    }

    .stok-opname .so-table .so-kode {
        font-weight: 600;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .stok-opname .so-table .so-sub {
        display: block;
        font-size: .75rem;
        color: var(--so-muted);
    }

    .stok-opname .so-table .so-delta {
        font-variant-numeric: tabular-nums;
        font-weight: 650;
        text-align: center;
        white-space: nowrap;
    }

    /* Varian selisih: warna semangat template (#fb977d / #4bd08b) terlalu
       pudar untuk teks kecil di atas kartu putih (2.14:1). */
    .stok-opname .so-table .so-delta.is-pos { color: #0f7a46; }
    .stok-opname .so-table .so-delta.is-neg { color: #c2353f; }
    .stok-opname .so-table .so-delta.is-zero { color: var(--so-muted); }

    html[data-bs-theme="dark"] .stok-opname .so-table .so-delta.is-pos { color: #4bd08b; }
    html[data-bs-theme="dark"] .stok-opname .so-table .so-delta.is-neg { color: #fb977d; }

    .stok-opname .so-table .so-flagged > td,
    .stok-opname .so-table tr.table-warning > td {
        background: var(--bs-warning-bg-subtle, #fff3cd);
    }

    .stok-opname .so-table .so-flagged:hover > td,
    .stok-opname .so-table tr.table-warning:hover > td {
        background: var(--bs-warning-bg-subtle, #fff3cd);
    }

    .stok-opname .so-state {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        font-size: .8125rem;
        color: var(--so-muted);
        white-space: nowrap;
    }

    .stok-opname .so-state::before {
        content: "";
        width: .5rem;
        height: .5rem;
        border-radius: 50%;
        background: currentColor;
        flex: 0 0 auto;
    }

    .stok-opname .so-state.is-done {
        color: var(--bs-success-text-emphasis, #146c43);
        font-weight: 600;
    }

    .stok-opname .input-real {
        max-width: 6.5rem;
        margin: 0 auto;
        text-align: center;
        font-variant-numeric: tabular-nums;
        font-weight: 600;
        border-radius: .5rem;
        display: block;
    }

    .stok-opname .input-real::-webkit-outer-spin-button,
    .stok-opname .input-real::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .stok-opname .input-real[type=number] {
        -moz-appearance: textfield;
        appearance: textfield;
    }

    .stok-opname .so-pager {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .5rem 1rem;
        margin-top: .85rem;
        padding-top: .85rem;
        border-top: 1px solid var(--so-hair);
    }

    .stok-opname .so-pager .pagination .page-link {
        font-size: .8125rem;
        border-radius: .45rem;
        margin: 0 .15rem;
        font-variant-numeric: tabular-nums;
        border: 0;
    }

    .stok-opname .so-pager .page-item.active .page-link {
        background: var(--bs-primary);
    }

    .stok-opname .so-pager small {
        color: var(--so-muted);
        font-variant-numeric: tabular-nums;
    }

    .stok-opname .so-footnote {
        font-size: .75rem;
        color: var(--so-muted);
        margin-top: .85rem;
        padding-top: .8rem;
        border-top: 1px solid var(--so-hair);
        max-width: 78ch;
        line-height: 1.5;
    }

    .stok-opname .so-footnote strong {
        color: var(--so-ink);
        font-weight: 650;
    }

    /* ===== 7. Keadaan kosong ===== */
    .stok-opname .so-empty {
        text-align: center;
        padding: 2.25rem 1.25rem 2rem;
        max-width: 58ch;
        margin: 0 auto;
    }

    .stok-opname .so-empty__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .06);
        color: var(--so-muted);
        margin-bottom: .85rem;
    }

    .stok-opname .so-empty__icon iconify-icon {
        font-size: 1.5rem;
    }

    .stok-opname .so-empty__title {
        font-size: 1rem;
        font-weight: 650;
        margin-bottom: .4rem;
        letter-spacing: -.01em;
    }

    .stok-opname .so-empty__text {
        font-size: .8125rem;
        color: var(--so-muted);
        line-height: 1.6;
        margin: 0;
    }

    .stok-opname .so-empty__text strong {
        color: var(--so-ink);
        font-weight: 650;
    }

    .stok-opname .so-empty__stamp {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        margin-top: .85rem;
        font-size: .75rem;
        color: var(--so-ink);
        background: rgba(var(--bs-primary-rgb, 0, 133, 219), .08);
        border: 1px dashed rgba(var(--bs-primary-rgb, 0, 133, 219), .35);
        border-radius: .5rem;
        padding: .4rem .7rem;
    }

    /* ===== 8. Panel bawah: audit + riwayat ===== */
    .stok-opname .so-trail .card-header,
    .stok-opname .so-history .card-header {
        padding: .85rem 1.25rem;
        background: transparent;
        border-bottom: 1px solid var(--so-hair);
    }

    .stok-opname .so-trail h6,
    .stok-opname .so-history h6 {
        font-size: .875rem;
        font-weight: 650;
        display: inline-flex;
        align-items: center;
        gap: .45rem;
    }

    .stok-opname .so-trail .card-body,
    .stok-opname .so-history .card-body {
        padding: .5rem 1.25rem .9rem;
        max-height: 22rem;
        overflow: auto;
    }

    .stok-opname .so-trail ul {
        margin: 0;
    }

    .stok-opname .so-trail li {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: .25rem 1rem;
        padding: .5rem 0;
        border-bottom: 1px solid var(--so-hair);
        font-size: .8125rem;
    }

    .stok-opname .so-trail li:last-child {
        border-bottom: 0;
    }

    .stok-opname .so-trail .so-trail__meta {
        color: var(--so-muted);
        font-size: .75rem;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .stok-opname .so-history .table {
        font-size: .8125rem;
        margin: 0;
    }

    .stok-opname .so-history thead th {
        font-size: .75rem;
        text-transform: uppercase;
        color: var(--so-muted);
        font-weight: 600;
        border-bottom: 1px solid var(--so-rule);
        white-space: nowrap;
    }

    .stok-opname .so-history tbody td {
        border-bottom: 1px solid var(--so-hair);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    /* ===== 9. Progres input (dipakai JS) ===== */
    .stok-opname .progress-so {
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .1) !important;
        border-radius: 999px !important;
        box-shadow: none;
    }

    .stok-opname .progress-so .progress-bar {
        /* Lebar dikunci, kemajuan digambar dengan transform agar animasi
           tidak memicu layout ulang (lihat baris pembaruan di JS). */
        width: 100% !important;
        transform-origin: left center;
        transform: scaleX(var(--so-prog, 0));
        background: var(--bs-primary);
        border-radius: 999px;
        transition: transform .55s cubic-bezier(.22, 1, .36, 1);
    }

    /* ===== 10. Gerak, bila pengguna minta kurangi animasi ===== */
    @media (prefers-reduced-motion: reduce) {
        .stok-opname .progress-so .progress-bar {
            transition: none;
        }
    }

    /* ===== 11. Layar sentuh: area tekan longgar ===== */
    @media (max-width: 575.98px) {
        .stok-opname .so-filter {
            width: 100%;
        }

        .stok-opname .so-filter .btn {
            padding: .6rem .85rem;
            font-size: .875rem;
        }

        .stok-opname .btn,
        .stok-opname .form-control-sm,
        .stok-opname .form-select-sm {
            min-height: 40px;
        }

        .stok-opname .so-search {
            min-width: 100%;
            max-width: 100%;
        }

        .stok-opname .so-command {
            gap: .5rem;
        }

        .stok-opname .so-command .so-spacer {
            margin-left: 0;
        }
    }
</style>
