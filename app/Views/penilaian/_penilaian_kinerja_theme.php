<?php

/**
 * Design system halaman Penilaian Kinerja (/penilaian_kinerja).
 *
 * Satu-satunya sumber warna, ukuran, dan komponen untuk halaman ini.
 * Semua warna diambil dari token template (resolved CSS var), jadi ikut
 * mode gelap/terang dan pergantian color-theme aplikasi tanpa duplikasi hex.
 *
 * Skala font mengikuti utilitas `fs-*` template (nilai rem identik):
 *
 *   0.75rem   label, header tabel, chip, keterangan
 *   0.8125rem isi tabel, teks bantuan
 *   0.875rem  isi tabel utama
 *   1rem      judul kartu
 *   1.25rem   judul halaman
 *
 * Satu aturan elevasi saja: bayangan `.card` bawaan template
 * (0px 2px 6px rgba(37,83,185,.1)) — panel di sini memakai `.card` biasa
 * supaya tidak terlihat sebagai sistem visual terpisah.
 */
?>

<style>
    /* ===== 1. Token ===== */
    .penilaian-kinerja {
        --pk-surface: var(--bs-card-bg, var(--bs-body-bg, #fff));
        --pk-rule: var(--bs-border-color, #e6ecf1);
        /* Netral sekunder pekat: lolos AA di kedua tema */
        --pk-muted: #5b6672;
        /* Tinta isi permukaan: var(--bs-body-color) template terlalu pudar di
           terang, dan harus tetap >=4.5:1 di atas latar filter (#e7ecf0).
           Harus juga lebih pekat daripada --pk-muted agar hierarki benar. */
        --pk-ink: #55606d;
        /* Tinta isi khusus latar filter, yang lebih pekat dari kartu */
        --pk-ink-strong: #4d5866;
        --pk-hair: rgba(var(--bs-body-color-rgb, 33, 37, 41), .09);
        --pk-radius: .75rem;
        /* Warna status baca. Token --bs-*-text-emphasis pada mode terang masih
           membawa fungsi Sass mentah (shade-color(...)) sehingga tidak sah
           sebagai warna dan akan menginherit tinta; nilai terang ditetapkan
           eksplisit di sini agar status tetap berwarna dan lolos AA. */
        --pk-ok: #146c43;
        --pk-warn: #7a5c04;
        --pk-bad: #b02a37;
    }

    html[data-bs-theme="dark"] .penilaian-kinerja {
        --pk-muted: #8fa0b8;
        --pk-ink: var(--bs-body-color, #cfd8e3);
        --pk-ink-strong: #cfd8e3;
        --pk-ok: var(--bs-success-text-emphasis, #93e3b9);
        --pk-warn: var(--bs-warning-text-emphasis, #fbd9ad);
        --pk-bad: var(--bs-danger-text-emphasis, #fdc1b1);
    }

    .penilaian-kinerja .text-muted {
        color: var(--pk-muted) !important;
    }

    .penilaian-kinerja,
    .penilaian-kinerja .card,
    .penilaian-kinerja .form-control,
    .penilaian-kinerja .form-select {
        color: var(--pk-ink);
    }

    /* Tombol primer template #0085db + teks putih hanya 3.9:1; versi
       sedikit lebih pekat ini 4.73:1, masih satu keluarga warna. */
    .penilaian-kinerja .btn-primary {
        background-color: #0077c4;
        border-color: #0077c4;
        color: #fff;
    }

    .penilaian-kinerja .btn-primary:hover,
    .penilaian-kinerja .btn-primary:focus,
    .penilaian-kinerja .btn-primary:active {
        background-color: #006fb6;
        border-color: #006fb6;
        color: #fff;
    }

    /* Tombol outline netral */
    .penilaian-kinerja .btn-outline-secondary {
        color: var(--pk-ink);
        border-color: var(--pk-rule);
    }

    .penilaian-kinerja .btn-outline-secondary:hover,
    .penilaian-kinerja .btn-outline-secondary:focus {
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .06);
        color: var(--pk-ink);
        border-color: var(--pk-rule);
    }

    /* Breadcrumb: teks primer template terlalu pudar di alas terang */
    .penilaian-kinerja .breadcrumb-item,
    .penilaian-kinerja .breadcrumb-item.active,
    .penilaian-kinerja .breadcrumb-item > a {
        color: var(--pk-muted);
    }

    /* ===== 2. Alas halaman: seleksi, fokus ===== */
    .penilaian-kinerja ::selection {
        background: rgba(var(--bs-primary-rgb, 0, 133, 219), .22);
    }

    .penilaian-kinerja :focus-visible {
        outline: 2px solid var(--bs-primary, #0085db);
        outline-offset: 2px;
    }

    /* ===== 3. Header halaman ===== */
    .penilaian-kinerja .pk-head .card-body {
        padding: 1.1rem 1.25rem;
    }

    .penilaian-kinerja .pk-head h4 {
        font-size: 1.25rem;
        letter-spacing: -.015em;
    }

    .penilaian-kinerja .pk-head p {
        font-size: .8125rem;
        max-width: 70ch;
    }

    .penilaian-kinerja .pk-head p strong {
        color: var(--pk-ink);
        font-weight: 650;
    }

    /* ===== 4. Baris filter ===== */
    .penilaian-kinerja .pk-filter .card-body {
        padding: 1rem 1.25rem 1.1rem;
    }

    .penilaian-kinerja .pk-filter .form-label {
        font-size: .75rem;
        font-weight: 600;
        color: var(--pk-muted);
        margin-bottom: .3rem;
    }

    /* Latar filter lebih pekat dari kartu: pakai tinta kuat agar AA */
    .penilaian-kinerja .pk-filter .form-select {
        color: var(--pk-ink-strong);
    }

    /* Select2 (Pilih Karyawan) disamakan dengan form-select */
    .penilaian-kinerja #f_karyawan + .select2-container {
        width: 100% !important;
    }

    .penilaian-kinerja #f_karyawan + .select2-container .select2-selection--single {
        background-color: var(--bs-tertiary-bg, #f8f9fa);
        border: 0;
        border-radius: var(--bs-border-radius);
        color: var(--pk-ink-strong);
        min-height: 2.4rem;
        display: flex;
        align-items: center;
        padding-inline: .85rem;
    }

    .penilaian-kinerja #f_karyawan + .select2-container .select2-selection--single .select2-selection__rendered {
        line-height: 1.2;
        padding-left: 0;
        color: var(--pk-ink-strong);
    }

    .penilaian-kinerja #f_karyawan + .select2-container .select2-selection--single .select2-selection__arrow {
        top: 50%;
        transform: translateY(-50%);
    }

    .penilaian-kinerja #f_karyawan + .select2-container .select2-selection--single .select2-selection__clear {
        position: relative;
        top: 0;
    }

    /* ===== 5. Kartu laporan (hero) =====
       Satu kartu utuh, dua paruh dipisah garis rambut:
       kiri = skor kinerja, kanan = estimasi take home pay. */
    .penilaian-kinerja .pk-report .card-body {
        padding: 0;
    }

    .penilaian-kinerja .pk-report__grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .penilaian-kinerja .pk-half {
        padding: 1.25rem 1.4rem 1.35rem;
        min-width: 0;
    }

    .penilaian-kinerja .pk-half + .pk-half {
        border-left: 1px solid var(--pk-hair);
    }

    .penilaian-kinerja .pk-half__label {
        font-size: .75rem;
        font-weight: 600;
        letter-spacing: .02em;
        text-transform: uppercase;
        color: var(--pk-muted);
    }

    .penilaian-kinerja .pk-score {
        display: flex;
        align-items: baseline;
        gap: .4rem;
        margin: .35rem 0 .5rem;
    }

    .penilaian-kinerja .pk-score__num {
        font-size: clamp(2.4rem, 3.4vw, 3rem);
        font-weight: 700;
        line-height: 1;
        letter-spacing: -.03em;
        font-variant-numeric: tabular-nums;
        color: var(--pk-ink);
    }

    .penilaian-kinerja .pk-score__den {
        font-size: 1rem;
        font-weight: 500;
        color: var(--pk-muted);
    }

    .penilaian-kinerja .pk-meter {
        height: 6px;
        border-radius: 999px;
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .1);
        overflow: hidden;
        margin: .15rem 0 .6rem;
    }

    .penilaian-kinerja .pk-meter__bar {
        height: 100%;
        width: 100%;
        transform-origin: left center;
        transform: scaleX(var(--pk-prog, 0));
        border-radius: 999px;
    }

    .penilaian-kinerja .pk-meter__bar.is-success { background: var(--bs-success, #13deb9); }
    .penilaian-kinerja .pk-meter__bar.is-warning { background: var(--bs-warning, #ffae1f); }
    .penilaian-kinerja .pk-meter__bar.is-danger  { background: var(--bs-danger, #fa896b); }

    .penilaian-kinerja .pk-pay__num {
        font-size: clamp(1.7rem, 2.5vw, 2.15rem);
        font-weight: 700;
        line-height: 1.05;
        letter-spacing: -.025em;
        font-variant-numeric: tabular-nums;
        color: var(--pk-ink);
        margin: .35rem 0 .15rem;
        word-break: break-word;
    }

    .penilaian-kinerja .pk-note {
        font-size: .75rem;
        color: var(--pk-muted);
        line-height: 1.4;
        display: flex;
        align-items: flex-start;
        gap: .35rem;
    }

    /* ===== 6. Chip band mutu & status ===== */
    .penilaian-kinerja .pk-band {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        font-size: .8125rem;
        font-weight: 650;
        padding: .3rem .7rem;
        border-radius: 999px;
        border: 1px solid transparent;
        line-height: 1.2;
    }

    .penilaian-kinerja .pk-band::before {
        content: "";
        width: .5rem;
        height: .5rem;
        border-radius: 50%;
        background: currentColor;
        flex: 0 0 auto;
    }

    .penilaian-kinerja .pk-band.is-success {
        color: var(--pk-ok);
        background: var(--bs-success-bg-subtle, #d1e7dd);
    }

    .penilaian-kinerja .pk-band.is-warning {
        color: var(--pk-warn);
        background: var(--bs-warning-bg-subtle, #fff3cd);
    }

    .penilaian-kinerja .pk-band.is-danger {
        color: var(--pk-bad);
        background: var(--bs-danger-bg-subtle, #f8d7da);
    }

    .penilaian-kinerja .pk-band.is-neutral {
        color: var(--pk-muted);
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .07);
    }

    .penilaian-kinerja .pk-chip {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        font-size: .75rem;
        font-weight: 600;
        padding: .2rem .55rem;
        border-radius: .4rem;
        border: 1px solid var(--pk-rule);
        color: var(--pk-muted);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .penilaian-kinerja .pk-chip--ho {
        color: var(--bs-primary);
        border-color: rgba(var(--bs-primary-rgb, 0, 133, 219), .35);
    }

    /* ===== 7. Kepala kartu (judul + catatan) ===== */
    .penilaian-kinerja .pk-card > .card-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .5rem 1rem;
        padding: .9rem 1.25rem;
        background: transparent;
        border-bottom: 1px solid var(--pk-hair);
    }

    .penilaian-kinerja .pk-card > .card-header h5,
    .penilaian-kinerja .pk-card > .card-header h6 {
        font-size: 1rem;
        font-weight: 650;
        letter-spacing: -.01em;
        margin: 0;
        display: inline-flex;
        align-items: center;
        gap: .45rem;
    }

    .penilaian-kinerja .pk-card > .card-header h5 iconify-icon,
    .penilaian-kinerja .pk-card > .card-header h6 iconify-icon {
        color: var(--bs-primary);
        flex: 0 0 auto;
    }

    /* ===== 8. Strip omset cabang ===== */
    .penilaian-kinerja .pk-omset .card-body {
        padding: 0;
    }

    .penilaian-kinerja .pk-omset__grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .penilaian-kinerja .pk-omset__cell {
        padding: .8rem 1.1rem .85rem;
        border-left: 1px solid var(--pk-hair);
        min-width: 0;
    }

    /* Hapus garis kiri pada kolom pertama setiap baris (bukan hanya sel pertama) */
    .penilaian-kinerja .pk-omset__cell:nth-child(4n + 1) {
        border-left: 0;
    }

    /* Pisahkan baris kedua dan seterusnya */
    .penilaian-kinerja .pk-omset__cell:nth-child(n + 5) {
        border-top: 1px solid var(--pk-hair);
    }

    .penilaian-kinerja .pk-omset__label {
        display: block;
        font-size: .75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: var(--pk-muted);
        margin-bottom: .2rem;
    }

    .penilaian-kinerja .pk-omset__value {
        font-size: .9375rem;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
        color: var(--pk-ink);
    }

    .penilaian-kinerja .pk-omset__cell.is-total {
        background: rgba(var(--bs-primary-rgb, 0, 133, 219), .05);
    }

    .penilaian-kinerja .pk-omset__cell.is-total .pk-omset__value {
        color: var(--bs-primary);
    }

    /* ===== 9. Ledger (tabel KPI & absen) ===== */
    .penilaian-kinerja .pk-ledger > .card-body {
        padding: 0;
    }

    .penilaian-kinerja .pk-scroll {
        max-height: 70vh;
        overflow: auto;
        scrollbar-width: thin;
        scrollbar-color: rgba(var(--bs-body-color-rgb, 33, 37, 41), .3) transparent;
    }

    .penilaian-kinerja .pk-scroll::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }

    .penilaian-kinerja .pk-scroll::-webkit-scrollbar-track {
        background: transparent;
    }

    .penilaian-kinerja .pk-scroll::-webkit-scrollbar-thumb {
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .28);
        border-radius: 999px;
        border: 3px solid transparent;
        background-clip: content-box;
    }

    .penilaian-kinerja .pk-scroll::-webkit-scrollbar-thumb:hover {
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .45);
        background-clip: content-box;
    }

    .penilaian-kinerja .pk-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: .875rem;
        margin-bottom: 0;
    }

    .penilaian-kinerja .pk-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--pk-surface);
        color: var(--pk-muted);
        font-size: .75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .02em;
        padding: .6rem .75rem;
        border-bottom: 1px solid var(--pk-rule);
        white-space: nowrap;
    }

    .penilaian-kinerja .pk-table tbody td {
        padding: .7rem .75rem;
        border-bottom: 1px solid var(--pk-hair);
        vertical-align: middle;
    }

    .penilaian-kinerja .pk-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .penilaian-kinerja .pk-table tbody tr:hover > td {
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .035);
    }

    .penilaian-kinerja .pk-table .pk-idx {
        font-variant-numeric: tabular-nums;
        color: var(--pk-muted);
        font-size: .75rem;
        text-align: right;
        width: 3rem;
    }

    .penilaian-kinerja .pk-table .pk-kriteria {
        font-weight: 650;
        color: var(--pk-ink);
        display: block;
        margin-bottom: .15rem;
    }

    .penilaian-kinerja .pk-num {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .penilaian-kinerja .pk-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.35rem;
        padding: .3rem .6rem;
        border-radius: .4rem;
        font-weight: 650;
        font-size: .8125rem;
        font-variant-numeric: tabular-nums;
    }

    .penilaian-kinerja .pk-badge.is-success { background: var(--bs-success, #13deb9); color: #04322a; }
    .penilaian-kinerja .pk-badge.is-warning { background: var(--bs-warning, #ffae1f); color: #1f2430; }
    .penilaian-kinerja .pk-badge.is-danger  { background: var(--bs-danger, #fa896b); color: #3d1109; }
    .penilaian-kinerja .pk-badge.is-neutral { background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .08); color: var(--pk-muted); }

    /* Sub-ledger rincian per cabang di dalam baris kriteria */
    .penilaian-kinerja .pk-branch {
        margin-top: .6rem;
        border: 1px solid var(--pk-hair);
        border-radius: .55rem;
        overflow: hidden;
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .02);
    }

    .penilaian-kinerja .pk-branch__row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .3rem 1rem;
        padding: .4rem .65rem;
        font-size: .8125rem;
        font-variant-numeric: tabular-nums;
    }

    .penilaian-kinerja .pk-branch__row + .pk-branch__row {
        border-top: 1px solid var(--pk-hair);
    }

    .penilaian-kinerja .pk-branch__name {
        font-weight: 600;
        color: var(--pk-ink);
    }

    .penilaian-kinerja .pk-branch__flow {
        color: var(--pk-muted);
    }

    /* Status / kekurangan: warna ikut token status halaman */
    .penilaian-kinerja .pk-pos { color: var(--pk-ok); font-weight: 600; }
    .penilaian-kinerja .pk-neg { color: var(--pk-bad); font-weight: 600; }

    /* ===== 10. Ledger komposisi penghasilan (payslip) ===== */
    .penilaian-kinerja .pk-pay .card-body {
        padding: .35rem 1.25rem .65rem;
    }

    .penilaian-kinerja .pk-pay__row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 1rem;
        padding: .7rem 0;
        border-bottom: 1px solid var(--pk-hair);
    }

    .penilaian-kinerja .pk-pay__row:last-child {
        border-bottom: 0;
    }

    .penilaian-kinerja .pk-pay__label {
        font-size: .875rem;
        font-weight: 600;
        color: var(--pk-ink);
    }

    .penilaian-kinerja .pk-pay__sub {
        display: block;
        font-size: .75rem;
        font-weight: 400;
        color: var(--pk-muted);
        margin-top: .1rem;
    }

    .penilaian-kinerja .pk-pay__amount {
        font-size: .9375rem;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
        color: var(--pk-ink);
        white-space: nowrap;
    }

    .penilaian-kinerja .pk-pay__row.is-total {
        margin-top: .35rem;
        padding-top: 1rem;
        border-top: 2px solid var(--pk-rule);
        border-bottom: 0;
    }

    .penilaian-kinerja .pk-pay__row.is-total .pk-pay__label {
        font-size: .9375rem;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .penilaian-kinerja .pk-pay__row.is-total .pk-pay__amount {
        font-size: clamp(1.35rem, 2vw, 1.6rem);
        font-weight: 700;
        letter-spacing: -.02em;
        color: var(--bs-primary);
    }

    /* ===== 11. Keadaan kosong ===== */
    .penilaian-kinerja .pk-empty {
        text-align: center;
        padding: 2.25rem 1.25rem 2rem;
        max-width: 52ch;
        margin: 0 auto;
        color: var(--pk-muted);
    }

    .penilaian-kinerja .pk-empty__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        background: rgba(var(--bs-body-color-rgb, 33, 37, 41), .06);
        color: var(--pk-muted);
        margin-bottom: .85rem;
    }

    .penilaian-kinerja .pk-empty__icon iconify-icon {
        font-size: 1.5rem;
    }

    .penilaian-kinerja .pk-empty__title {
        font-size: .9375rem;
        font-weight: 650;
        color: var(--pk-ink);
        margin-bottom: .35rem;
    }

    .penilaian-kinerja .pk-empty p {
        font-size: .8125rem;
        margin: 0;
        line-height: 1.55;
    }

    /* ===== 12. Responsif ===== */
    @media (max-width: 991.98px) {
        .penilaian-kinerja .pk-report__grid {
            grid-template-columns: 1fr;
        }

        .penilaian-kinerja .pk-half + .pk-half {
            border-left: 0;
            border-top: 1px solid var(--pk-hair);
        }

        .penilaian-kinerja .pk-omset__grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .penilaian-kinerja .pk-omset__cell:nth-child(odd) {
            border-left: 0;
        }

        .penilaian-kinerja .pk-omset__cell:nth-child(n + 3) {
            border-top: 1px solid var(--pk-hair);
        }
    }

    @media (max-width: 575.98px) {
        .penilaian-kinerja .pk-half {
            padding: 1.05rem 1rem 1.15rem;
        }

        .penilaian-kinerja .pk-omset__grid {
            grid-template-columns: 1fr;
        }

        .penilaian-kinerja .pk-omset__cell {
            border-left: 0;
        }

        .penilaian-kinerja .pk-omset__cell + .pk-omset__cell {
            border-top: 1px solid var(--pk-hair);
        }

        .penilaian-kinerja .btn,
        .penilaian-kinerja .form-control,
        .penilaian-kinerja .form-select {
            min-height: 40px;
        }
    }

    /* ===== 13. Gerak, bila pengguna minta kurangi animasi ===== */
    @media (prefers-reduced-motion: reduce) {
        .penilaian-kinerja .pk-meter__bar {
            transition: none;
        }
    }
</style>
