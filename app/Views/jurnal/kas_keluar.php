<?php
/**
 * Kas Keluar — two-deck console.
 *
 * Deck 1 (composer): formulir input kas keluar, terlipat jadi satu baris dan
 * terbuka di tempat — ledger di bawahnya tidak pernah hilang. Deck 2 (ledger):
 * bilah perintah berisi pencarian, filter, dan tabel.
 *
 * Aturan yang dijaga: nama field, route, logika bisnis, dan gating jabatan
 * tidak berubah. Yang berubah hanya susunan, hierarki, dan cara mencari.
 */
$canAct = (int) ($akun->ID_JABATAN ?? 1) === 1;
$akunRole = (int) ($akun->ID_JABATAN ?? 0);
$akunUnit = (int) ($akun->ID_UNIT ?? 0);
$canPickUnit = in_array($akunRole, [0, 1, 2, 34], true);
?>
<style>
    .kk-scope {
        --kk-ink: var(--bs-emphasis-color);
        --kk-ink-2: color-mix(in srgb, var(--bs-emphasis-color) 74%, var(--bs-body-bg));
        --kk-ink-3: color-mix(in srgb, var(--bs-emphasis-color) 56%, var(--bs-body-bg));
        --kk-line: var(--bs-border-color);
        --kk-raise: var(--bs-tertiary-bg);
        /* Permukaan deck harus warna kartu aplikasi, sama seperti halaman lain.
           `--bs-card-bg` hanya ada di dalam selektor `.card` (styles.css:5477),
           sehingga di luar kartu token itu kosong: dek jadi transparan dan
           kanvas halaman (`#f0f5f9` terang / `#15263a` gelap) terlihat langsung
           lewat isi dek — itu sebabnya halaman ini terlihat berbeda dari yang lain.
           `.card` sendiri mengecat `var(--bs-body-bg)`, jadi itulah nilainya. */
        --kk-surface: var(--bs-body-bg);
        /* Radius + bayangan kartu aplikasi (styles.css `.card`), supaya dek
           terbaca sebagai kartu yang sama, bukan bidang lepas di kanvas. */
        --kk-r: 1.125rem;
        --kk-shadow: 0 2px 6px rgba(37, 83, 185, .1);
        --kk-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
    }

    .kk-scope .kk-mono {
        font-family: var(--kk-mono);
        font-variant-numeric: tabular-nums;
        letter-spacing: -.01em;
    }

    .kk-scope .kk-num {
        font-variant-numeric: tabular-nums;
        font-feature-settings: "tnum" 1;
    }

    /* ── Masthead ─────────────────────────────────────────────── */
    /* Kartu judul tanpa bayangan, mengikuti kartu header halaman lain
       (`card shadow-none`): judul dan cakupan periode di atas kanvasAbu. */
    .kk-masthead {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .75rem 1.25rem;
        padding: 1rem 1.25rem;
        margin-bottom: 1rem;
        background: var(--kk-surface);
        border-radius: var(--kk-r);
    }

    .kk-masthead h1 {
        font-size: 1.5rem;
        font-weight: 600;
        line-height: 1.2;
        margin: .125rem 0 0;
        color: var(--kk-ink);
        text-wrap: balance;
    }

    .kk-masthead .breadcrumb {
        font-size: .8125rem;
        margin: 0;
        padding: 0;
        background: none;
    }

    .kk-scope-readout {
        display: flex;
        flex-wrap: wrap;
        gap: .375rem .5rem;
        font-size: .8125rem;
        color: var(--kk-ink-2);
    }

    .kk-scope-readout b {
        color: var(--kk-ink);
        font-weight: 600;
    }

    /* ── Deck kerangka ────────────────────────────────────────── */
    .kk-deck {
        background: var(--kk-surface);
        border-radius: var(--kk-r);
        box-shadow: var(--kk-shadow);
    }

    .kk-deck+.kk-deck {
        margin-top: 1rem;
    }

    /* ── Composer deck ────────────────────────────────────────── */
    .kk-composer-bar {
        display: flex;
        align-items: center;
        gap: .875rem;
        width: 100%;
        min-height: 60px;
        padding: .75rem 1rem;
        background: none;
        border: 0;
        border-radius: var(--kk-r);
        color: inherit;
        text-align: left;
        transition: background-color .18s ease;
    }

    .kk-composer-bar:hover {
        background: var(--kk-raise);
    }

    .kk-composer-bar:focus-visible {
        outline: 2px solid var(--bs-primary);
        outline-offset: -2px;
    }

    .kk-tile {
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: var(--bs-primary-bg-subtle);
        color: var(--bs-primary);
    }

    .kk-composer-label {
        flex: 1 1 auto;
        min-width: 0;
    }

    .kk-composer-label strong {
        display: block;
        font-size: .9375rem;
        font-weight: 600;
        color: var(--kk-ink);
    }

    .kk-composer-sub {
        display: block;
        font-size: .8125rem;
        color: var(--kk-ink-3);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .kk-composer-live {
        flex: 0 0 auto;
        text-align: right;
        font-size: .8125rem;
        color: var(--kk-ink-3);
    }

    .kk-composer-live b {
        display: block;
        font-size: 1rem;
        font-weight: 600;
        color: var(--kk-ink);
    }

    .kk-composer-caret {
        transition: transform .22s cubic-bezier(.2, .8, .3, 1);
    }

    .kk-composer[data-open="true"] .kk-composer-caret {
        transform: rotate(180deg);
    }

    /* Lipat / buka. Badan deck yang tertutup harus benar-benar keluar dari
       flow: kalau hanya dipipihkan, tabel rincian akun di dalamnya tetap lifeless
       dan melebarkan dokumen, bukan menggulir di dalam deck. */
    .kk-composer-body {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        /* Tabel rincian akun 7 kolom melebihi lebar ponsel. `.table-responsive`
           menggulirkan tabelnya sendiri, tapi tanpa containment overflow-nya
           tetap merambat ke atas sampai seluruh halaman bisa digeser sideways
           begitu composer dibuka. */
        contain: paint;
    }

    .kk-composer[data-open="false"] .kk-composer-body {
        display: none;
    }

    /* Satu momen gerak saat deck terbuka, bukan animasi bertubi. */
    .kk-composer[data-open="true"] .kk-composer-inner {
        animation: kk-deck-in .22s cubic-bezier(.2, .8, .3, 1) both;
    }

    @keyframes kk-deck-in {
        from {
            opacity: 0;
            transform: translateY(-.375rem);
        }

        to {
            opacity: 1;
            transform: none;
        }
    }

    .kk-composer-body>div {
        min-width: 0;
    }

    .kk-composer-inner {
        padding: 0 1rem 1rem;
        border-top: 1px solid var(--kk-line);
    }

    .kk-composer-inner>* {
        margin-top: 1rem;
    }

    .kk-field-label {
        display: block;
        font-size: .75rem;
        font-weight: 600;
        letter-spacing: .02em;
        text-transform: uppercase;
        color: var(--kk-ink-3);
        margin-bottom: .375rem;
    }

    /* Jarak ke atas bukan kosmetik: baris input di atasnya (`.row g-3`) berakhir
       tepat di sisi subhead, dan kolom paling lebar di baris itu adalah input
       Deskripsi — jadi tombol "Tambah Baris" duduk persis di bawahnya. Tanpa
       margin ini keduanya menempel, apalagi setelah tombol dibuat lebih tinggi. */
    .kk-subhead {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-top: 1.25rem;
    }

    .kk-subhead .kk-field-label {
        margin: 0;
    }

    .kk-pos-table {
        --bs-table-bg: transparent;
    }

    .kk-pos-table th {
        font-size: .6875rem;
        font-weight: 600;
        letter-spacing: .03em;
        text-transform: uppercase;
        border-bottom: 1px solid var(--kk-line);
        padding: .5rem .5rem;
        white-space: nowrap;
    }

    .kk-pos-table td {
        padding: .375rem .5rem;
        vertical-align: middle;
        border-bottom: 1px solid var(--kk-line);
    }

    .kk-pos-table .form-control,
    .kk-pos-table .form-select {
        font-size: .8125rem;
    }

    /* Panah select2 No. Akun / No. Rekening jatuh di luar kotaknya.
       Template bersama mengunci `.select2-selection__arrow` ke `top: 24px`
       (styles.css:25898) lalu menaikkan tingginya jadi 40px (styles.css:25956)
       — sama dengan tinggi kotak selection. Panah 40px yang mulai di 24px
       berakhir 24px di bawah kotak, dan `b` di dalamnya dipusatkan di 50%
       tinggi panah, sehingga ujung caret jatuh ±2px di bawah garis bawah
       input. `right: 20px` juga menumpukkannya tepat di tempat tombol
       bersihkan (`margin-right: 20px`), jadi caret dan "×" berebut ruang.
       Di sini geometri panah dikembalikan kekotak yang benar, tombol
       bersihkan digeser ke kiri oblast panah, dan teks diberi ruang cukup.
       Prefix `body` menyamai spesifisitas aturan template, dan blok <style>
       halaman ini muncul belakangan di badan dokumen. `height` sengaja tidak
       ditimpa: `.select2-selection--single` tidak punya `position: relative`
       (hanya `.select2-container`), jadi containing block panah berukuran
       `auto` dan `height: 100%` akan kolaps jadi nol. Tinggi 40px dari
       styles.css:25956 justru yang benar. */
    body .kk-scope .select2-container--default .select2-selection--single .select2-selection__arrow {
        top: 0;
        right: 0;
        width: 26px;
    }

    body .kk-scope .select2-container--default .select2-selection--single .select2-selection__clear {
        margin-right: 28px;
    }

    body .kk-scope .select2-container--default .select2-selection--single .select2-selection__rendered {
        padding-right: 30px;
    }

    .kk-total-line {
        display: flex;
        align-items: baseline;
        justify-content: flex-end;
        gap: .75rem;
        padding-top: .875rem;
    }

    .kk-total-line span {
        font-size: .8125rem;
        color: var(--kk-ink-3);
    }

    .kk-total-line b {
        font-size: 1.375rem;
        font-weight: 600;
        color: var(--kk-ink);
    }

    .kk-note {
        display: flex;
        gap: .5rem;
        align-items: flex-start;
        font-size: .8125rem;
        color: var(--kk-ink-3);
        padding: .625rem .75rem;
        background: var(--kk-raise);
        border-radius: 8px;
    }

    /* ── Ledger deck: bilah perintah ──────────────────────────── */
    .kk-cmdbar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: .75rem;
        padding: 1rem;
        border-bottom: 1px solid var(--kk-line);
    }

    /* `position: relative` itu wajib: ikon cari dan tombol bersihkan keduanya
       absolut. Tanpa kerangka di sini keduanya mengukur diri terhadap
       `#main-wrapper` (position: relative) dan melayang di sisi kiri halaman,
       bukan di dalam kolomnya. */
    .kk-search {
        position: relative;
        flex: 1 1 320px;
        max-width: 420px;
    }

    .kk-search .form-control {
        padding-left: 2.25rem;
        height: 38px;
    }

    .kk-search .kk-search-icon {
        position: absolute;
        left: .75rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--kk-ink-3);
        pointer-events: none;
    }

    .kk-search .kk-clear {
        position: absolute;
        right: .375rem;
        top: 50%;
        transform: translateY(-50%);
        display: none;
        border: 0;
        background: none;
        color: var(--kk-ink-3);
        padding: .25rem;
        border-radius: 6px;
        line-height: 0;
    }

    .kk-search[data-filled="true"] .kk-clear {
        display: block;
    }

    /* Tombol bersihkan mengisi 26px di tepi kanan; tanpa ruang yang disisakan,
       teks yang panjang menimpanya. */
    .kk-search[data-filled="true"] .form-control {
        padding-right: 2.25rem;
    }

    .kk-search .kk-clear:hover {
        color: var(--kk-ink);
        background: var(--kk-raise);
    }

    .kk-filter {
        flex: 0 0 auto;
    }

    .kk-filter .form-control,
    .kk-filter .form-select {
        height: 38px;
        font-size: .8125rem;
    }

    .kk-cmdbar-actions {
        display: flex;
        gap: .5rem;
        margin-left: auto;
    }

    .kk-cmdbar-actions .btn {
        height: 38px;
        display: inline-flex;
        align-items: center;
        gap: .375rem;
    }

    /* ── Chip filter aktif ────────────────────────────────────── */
    .kk-chips {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .375rem;
        padding: 0 1rem;
        padding-top: .875rem;
    }

    .kk-chips:empty {
        display: none;
    }

    .kk-chip {
        display: inline-flex;
        align-items: center;
        gap: .375rem;
        font-size: .75rem;
        color: var(--kk-ink-2);
        background: var(--kk-raise);
        border: 1px solid var(--kk-line);
        border-radius: 999px;
        padding: .1875rem .5rem .1875rem .625rem;
    }

    .kk-chip b {
        font-weight: 600;
        color: var(--kk-ink);
    }

    .kk-chip button {
        display: grid;
        place-items: center;
        border: 0;
        background: none;
        padding: 0;
        line-height: 0;
        color: var(--kk-ink-3);
        border-radius: 50%;
    }

    .kk-chip button:hover {
        color: var(--bs-danger);
    }

    /* ── Jawaban pencarian ID ─────────────────────────────────── */
    .kk-idhit {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .5rem .75rem;
        margin: .875rem 1rem 0;
        padding: .625rem .875rem;
        border-radius: 10px;
        font-size: .8125rem;
        border: 1px solid transparent;
    }

    .kk-idhit[hidden] {
        display: none;
    }

    .kk-idhit.is-found {
        background: var(--bs-primary-bg-subtle);
        border-color: color-mix(in srgb, var(--bs-primary) 25%, transparent);
    }

    .kk-idhit.is-out {
        background: var(--bs-warning-bg-subtle);
        border-color: color-mix(in srgb, var(--bs-warning) 35%, transparent);
    }

    .kk-idhit.is-miss {
        background: var(--kk-raise);
        border-color: var(--kk-line);
    }

    .kk-idhit .kk-idhit-id {
        font-family: var(--kk-mono);
        font-weight: 600;
        color: var(--kk-ink);
    }

    .kk-idhit .kk-idhit-what {
        color: var(--kk-ink-2);
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .kk-idhit .btn {
        margin-left: auto;
    }

    /* ── Tabel ────────────────────────────────────────────────── */
    .kk-tablewrap {
        padding: 1rem 1rem .5rem;
        overflow-x: auto;
        /* Tabel lebih lebar dari wadahnya dan harus tetap menggulir di dalam
           deck, bukan mendorong dokumen ini ikut bergeser sideways. */
        contain: paint;
    }

    .kk-table {
        --bs-table-bg: transparent;
        margin: 0;
        border-color: var(--kk-line);
    }

    .kk-scope .kk-table thead th {
        color: var(--kk-ink-3);
        font-size: .6875rem;
        font-weight: 600;
        letter-spacing: .03em;
        text-transform: uppercase;
        border-bottom: 1px solid var(--kk-line);
        padding: 0 .75rem .5rem;
        white-space: nowrap;
        vertical-align: bottom;
    }

    .kk-scope .kk-table tbody td {
        color: var(--kk-ink);
        padding: .625rem .75rem;
        vertical-align: middle;
        border-bottom: 1px solid var(--kk-line);
        font-size: .875rem;
    }

    .kk-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .kk-table tbody tr:hover td {
        background: var(--kk-raise);
    }

    .kk-table tbody tr.is-target td {
        background: var(--bs-primary-bg-subtle);
    }

    .kk-line1 {
        display: block;
        color: var(--kk-ink);
    }

    .kk-line2 {
        display: block;
        font-size: .75rem;
        color: var(--kk-ink-3);
        margin-top: .125rem;
    }

    .kk-c-id {
        width: 84px;
    }

    .kk-c-date {
        width: 116px;
    }

    .kk-c-kat {
        width: 150px;
    }

    .kk-c-akun {
        width: 150px;
    }

    .kk-c-penerima {
        width: 190px;
    }

    .kk-c-jumlah {
        width: 150px;
        text-align: right;
    }

    .kk-c-jenis {
        width: 104px;
    }

    .kk-c-aksi {
        width: 88px;
        text-align: right;
    }

    .kk-table td.kk-c-jumlah .kk-amount {
        font-size: .9375rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .kk-table td.kk-c-id .kk-line1 {
        font-family: var(--kk-mono);
        font-variant-numeric: tabular-nums;
        color: var(--kk-ink-2);
    }

    .kk-clip {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .kk-tag {
        display: inline-block;
        font-size: .6875rem;
        font-weight: 600;
        letter-spacing: .02em;
        padding: .1875rem .5rem;
        border-radius: 6px;
        background: var(--kk-raise);
        color: var(--kk-ink-2);
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Debet/Kredit: debet = chip berisi, kredit = chip garis. Bentuk dan warna
       membedakan, supaya tetap terbaca tanpa mengandalkan warna. */
    .kk-pos-debet {
        background: var(--bs-primary-bg-subtle);
        color: var(--kk-ink);
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--bs-primary) 32%, transparent);
    }

    .kk-pos-kredit {
        background: transparent;
        color: var(--kk-ink);
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--bs-warning) 55%, transparent);
    }

    .kk-pos-kosong {
        background: transparent;
        color: var(--kk-ink-3);
        box-shadow: none;
        padding-left: 0;
    }

    .kk-rowbtn {
        display: inline-grid;
        place-items: center;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        border: 1px solid var(--kk-line);
        background: none;
        color: var(--kk-ink-2);
        line-height: 0;
        transition: background-color .15s ease, color .15s ease, border-color .15s ease;
    }

    .kk-rowbtn:hover {
        color: var(--kk-ink);
        background: var(--kk-raise);
    }

    .kk-rowbtn.is-danger:hover {
        color: var(--bs-danger);
        border-color: color-mix(in srgb, var(--bs-danger) 45%, transparent);
        background: var(--bs-danger-bg-subtle);
    }

    .kk-scope .kk-table tfoot td {
        padding: .75rem;
        font-size: .8125rem;
        color: var(--kk-ink-3);
        border-top: 1px solid var(--kk-line);
    }

    .kk-scope .kk-table tfoot .kk-foot-total {
        font-size: 1rem;
        font-weight: 600;
        color: var(--kk-ink);
        font-variant-numeric: tabular-nums;
        text-align: right;
        white-space: nowrap;
    }

    .kk-scope .kk-table tfoot .kk-foot-count {
        font-variant-numeric: tabular-nums;
    }

    /* Tema aplikasi memaksa warna sel tabel di dark mode dengan `!important`
       (styles.css: `[data-bs-theme="dark"] .table > :not(caption) > * > *`).
       Tanpa aturan yang setara, semua sel menyatu jadi #7c8fac: judul kolom,
       nilai nominal, dan baris sub ikut kehilangan hierarki — bahkan baris sub
       jadi lebih terang daripada teks utamanya. Aturan ini menjawab dengan
       prioritas sama dan spesifisitas lebih tinggi, jadi hanya tabel kk yang
       terpengaruh; tabel lain di aplikasi tetap apa adanya. */
    [data-bs-theme="dark"] .kk-scope .kk-table tbody td {
        color: var(--kk-ink) !important;
    }

    [data-bs-theme="dark"] .kk-scope .kk-table thead th,
    [data-bs-theme="dark"] .kk-scope .kk-table tfoot td,
    [data-bs-theme="dark"] .kk-scope .kk-table tfoot .kk-foot-total {
        color: var(--kk-ink-3) !important;
    }

    /* Total kaki tabel adalah angka terbesar di ledger — tetap harus menonjol
       di dark mode, tidak ikut meredup bersama sel lain. */
    [data-bs-theme="dark"] .kk-scope .kk-table tfoot .kk-foot-total {
        color: var(--kk-ink) !important;
    }

    /* ── Empty state ──────────────────────────────────────────── */
    .kk-empty {
        display: none;
        padding: 2.5rem 1.5rem 3rem;
        text-align: center;
    }

    .kk-empty.is-shown {
        display: block;
    }

    .kk-empty .kk-tile {
        width: 44px;
        height: 44px;
        margin: 0 auto .875rem;
    }

    .kk-empty h3 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--kk-ink);
        margin: 0 0 .375rem;
    }

    .kk-empty p {
        font-size: .8125rem;
        color: var(--kk-ink-3);
        margin: 0 auto;
        max-width: 44ch;
    }

    .kk-empty .btn {
        margin-top: 1rem;
    }

    /* ── Pager DataTables, disatukan ke dalam deck ────────────── */
    .kk-pager {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .75rem 1rem;
        padding: .75rem 1rem 1rem;
        border-top: 1px solid var(--kk-line);
        font-size: .8125rem;
        color: var(--kk-ink-3);
    }

    .kk-pager .dataTables_info {
        color: var(--kk-ink-3);
        font-variant-numeric: tabular-nums;
    }

    .kk-pager .pagination {
        margin: 0;
        gap: .25rem;
    }

    .kk-pager .pagination .page-link {
        display: grid;
        place-items: center;
        min-width: 32px;
        height: 32px;
        padding: 0 .5rem;
        border-radius: 8px;
        border: 1px solid var(--kk-line);
        color: var(--kk-ink-2);
        background: none;
        font-size: .8125rem;
    }

    .kk-pager .pagination .page-link:hover {
        color: var(--kk-ink);
        background: var(--kk-raise);
    }

    .kk-pager .pagination .active .page-link {
        background: var(--bs-primary-bg-subtle);
        border-color: color-mix(in srgb, var(--bs-primary) 35%, transparent);
        color: var(--kk-ink);
        font-weight: 600;
    }

    .kk-pager .pagination .disabled .page-link {
        color: var(--kk-ink-3);
        opacity: .5;
    }

    .kk-pager .form-select {
        width: auto;
        height: 32px;
        font-size: .8125rem;
        padding: 0 1.75rem 0 .625rem;
        border-radius: 8px;
    }

    /* ── Dialog konfirmasi hapus ─────────────────────────────── */
    .kk-del-body {
        display: grid;
        gap: .25rem;
        font-size: .875rem;
        color: var(--kk-ink-2);
    }

    .kk-del-body b {
        color: var(--kk-ink);
        font-weight: 600;
    }

    @media (max-width: 991.98px) {
        .kk-search {
            max-width: none;
            flex-basis: 100%;
        }

        .kk-cmdbar-actions {
            margin-left: 0;
        }
    }

    /* Di layar sempit satu baris 12 kolom tidak mungkin jadi tabel:
       setiap baris berubah menjadi kartu, label diambil dari data-label. */
    @media (max-width: 767.98px) {
        .kk-table thead {
            display: none;
        }

        .kk-table,
        .kk-table tbody,
        .kk-table tr,
        .kk-table td {
            display: block;
            width: auto;
        }

        .kk-table tbody tr {
            border: 1px solid var(--kk-line);
            border-radius: 10px;
            padding: .5rem .25rem;
            margin-bottom: .625rem;
        }

        .kk-table tbody td {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 1rem;
            padding: .25rem .75rem;
            border: 0;
            text-align: right;
        }

        .kk-table tbody td::before {
            content: attr(data-label);
            flex: 0 0 auto;
            font-size: .6875rem;
            font-weight: 600;
            letter-spacing: .03em;
            text-transform: uppercase;
            color: var(--kk-ink-3);
            text-align: left;
        }

        .kk-table td.kk-c-desc,
        .kk-table td.kk-c-penerima,
        .kk-table td.kk-c-akun {
            flex-direction: column;
            align-items: flex-end;
        }

        .kk-table td.kk-c-desc::before,
        .kk-table td.kk-c-penerima::before,
        .kk-table td.kk-c-akun::before {
            align-self: flex-start;
        }

        .kk-table td.kk-c-jumlah {
            border-top: 1px solid var(--kk-line);
            margin-top: .25rem;
            padding-top: .5rem;
        }

        .kk-table td.kk-c-aksi {
            justify-content: flex-end;
        }

        .kk-table td.kk-c-aksi::before {
            display: none;
        }

        .kk-table td.kk-c-id .kk-line1 {
            color: var(--kk-ink);
        }

        .kk-c-id,
        .kk-c-date,
        .kk-c-kat,
        .kk-c-akun,
        .kk-c-penerima,
        .kk-c-jumlah,
        .kk-c-jenis,
        .kk-c-aksi {
            width: auto;
        }

        .kk-table tfoot tr,
        .kk-table tfoot td {
            display: block;
            width: auto;
        }

        .kk-table tfoot td {
            display: flex;
            justify-content: space-between;
            border: 0;
        }
    }

    @media (prefers-reduced-motion: reduce) {

        .kk-composer[data-open="true"] .kk-composer-inner,
        .kk-composer-caret,
        .kk-rowbtn {
            transition: none;
            animation: none;
        }
    }
</style>

<div class="kk-scope">

    <?php if (session("sukses")): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= esc(session("sukses")) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>

    <!-- ── Masthead ─────────────────────────────────────────────── -->
    <header class="kk-masthead">
        <div>
            <nav aria-label="Remah roti">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a class="text-secondary text-decoration-none" href="<?= base_url("/") ?>">Jurnal</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Kas Keluar</li>
                </ol>
            </nav>
            <h1>Kas Keluar</h1>
        </div>
        <div class="kk-scope-readout" id="kkScope"></div>
    </header>

    <!-- ── Deck 1 · Composer ────────────────────────────────────── -->
    <section class="kk-deck kk-composer" id="kkComposer" data-open="false" aria-labelledby="kkComposerTitle">
        <button type="button" class="kk-composer-bar" id="kkComposerToggle" aria-expanded="false" aria-controls="kkComposerBody">
            <span class="kk-tile" aria-hidden="true">
                <iconify-icon icon="solar:wallet-money-line-duotone" width="20" height="20"></iconify-icon>
            </span>
            <span class="kk-composer-label">
                <strong id="kkComposerTitle">Input Kas Keluar</strong>
                <span class="kk-composer-sub" id="kkComposerSub">Belum ada draf — isi tanggal, unit, lalu tambah baris akun</span>
            </span>
            <span class="kk-composer-live" id="kkComposerLive" hidden>
                <b class="kk-num" id="kkComposerLiveTotal">Rp 0</b>
                <span id="kkComposerLiveMeta"></span>
            </span>
            <span class="btn btn-primary" id="kkComposerCta">Input</span>
            <iconify-icon class="kk-composer-caret text-secondary" icon="solar:alt-arrow-down-linear" width="18" height="18" aria-hidden="true"></iconify-icon>
        </button>

        <div class="kk-composer-body" id="kkComposerBody">
            <div>
                <div class="kk-composer-inner">

                    <!-- Form input: route insert_kas_keluar, banyak baris akun -->
                    <form action="<?= base_url("insert_kas_keluar") ?>" method="post" id="kkFormInsert" novalidate>
                        <div class="row g-3">
                            <div class="col-12 col-md-3">
                                <label class="kk-field-label" for="kkTanggal">Tanggal</label>
                                <input type="date" class="form-control" name="tanggal" id="kkTanggal" required>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="kk-field-label" for="kkUnit">Unit</label>
                                <select class="form-select" name="unit_idunit" id="kkUnit" required>
                                    <option value="">Pilih Unit</option>
                                    <?php foreach ($unit as $u): ?>
                                        <option value="<?= (int) $u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text" id="kkUnitHint" hidden>Unit mengikuti akun yang login</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="kk-field-label" for="kkDeskripsi">Deskripsi</label>
                                <input type="text" class="form-control" name="deskripsi" id="kkDeskripsi"
                                    placeholder="contoh: Pembelian ATK" maxlength="255">
                            </div>
                        </div>

                        <div class="kk-subhead">
                            <span class="kk-field-label" id="kkPosLabel">Rincian Akun</span>
                            <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 px-3 py-2" id="kkAddPosisi">
                                <iconify-icon icon="solar:add-circle-linear" width="16" height="16" aria-hidden="true"></iconify-icon>
                                <span>Tambah Baris</span>
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table kk-pos-table" aria-labelledby="kkPosLabel">
                                <thead>
                                    <tr>
                                        <th scope="col">No. Akun</th>
                                        <th scope="col">Kategori</th>
                                        <th scope="col">Sumber Dana</th>
                                        <th scope="col">No. Rekening</th>
                                        <th scope="col">Penerima</th>
                                        <th scope="col">Debet/Kredit</th>
                                        <th scope="col" class="text-end">Jumlah</th>
                                        <th scope="col"><span class="visually-hidden">Hapus</span></th>
                                    </tr>
                                </thead>
                                <tbody id="kkPosisiBody"></tbody>
                            </table>
                        </div>

                        <div class="kk-total-line">
                            <span>Total draf</span>
                            <b class="kk-num" id="kkDraftTotal">Rp 0</b>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-kk-fold>Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan Kas Keluar</button>
                        </div>
                    </form>

                    <!-- Form edit: route update_kas_keluar, satu baris (bentuk yang
                         benar-benar diterima route ini) -->
                    <form action="<?= base_url("update_kas_keluar") ?>" method="post" id="kkFormEdit" hidden>
                        <input type="hidden" name="idkas_keluar" id="kkEditId">
                        <div class="row g-3">
                            <div class="col-12 col-md-3">
                                <label class="kk-field-label" for="kkEditTanggal">Tanggal</label>
                                <input type="date" class="form-control" name="tanggal" id="kkEditTanggal" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="kk-field-label" for="kkEditKategori">Kategori</label>
                                <select class="form-select" name="kategori_idkategori" id="kkEditKategori" required>
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($kategori_kas as $kat): ?>
                                        <option value="<?= (int) $kat->idkategori_kas ?>"><?= esc($kat->kategori) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-5">
                                <label class="kk-field-label" for="kkEditPenerima">Penerima / Rekening</label>
                                <select class="form-select" name="penerima" id="kkEditPenerima">
                                    <option value="">— Tidak ada rekening —</option>
                                    <?php foreach ($bank as $b): ?>
                                        <option value="<?= (int) $b->idbank ?>"><?= esc($b->nama_bank . " · " . $b->atas_nama . " · " . $b->norek) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-8">
                                <label class="kk-field-label" for="kkEditDeskripsi">Deskripsi</label>
                                <input type="text" class="form-control" name="deskripsi" id="kkEditDeskripsi" maxlength="255" required>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="kk-field-label" for="kkEditPosisi">Debet/Kredit</label>
                                <select class="form-select" name="posisi_drk" id="kkEditPosisi">
                                    <option value="debet">Debet</option>
                                    <option value="kredit">Kredit</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="kk-field-label" for="kkEditJumlah">Jumlah</label>
                                <input type="text" class="form-control text-end kk-num" name="jumlah" id="kkEditJumlah"
                                    inputmode="numeric" required>
                            </div>
                        </div>

                        <p class="kk-note">
                            <iconify-icon icon="solar:info-circle-linear" width="16" height="16" class="flex-shrink-0 mt-1" aria-hidden="true"></iconify-icon>
                            <span>Mode edit mengubah tanggal, kategori, deskripsi, jumlah, penerima, dan debet/kredit. Unit dan No. Akun tetap seperti saat transaksi dibuat.</span>
                        </p>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary" id="kkEditBatal">Batal</button>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </section>

    <!-- ── Deck 2 · Ledger ──────────────────────────────────────── -->
    <section class="kk-deck" aria-labelledby="kkLedgerTitle">
        <h2 class="visually-hidden" id="kkLedgerTitle">Daftar kas keluar</h2>

        <div class="kk-cmdbar">
            <div class="kk-search" id="kkSearchWrap" data-filled="false">
                <label class="visually-hidden" for="kkSearch">Cari kas keluar</label>
                <!-- Solar menamai ikon ini `magnifier`; `magnifer` (tanpa "i")
                     hanya alias di API Iconify dan bisa sewaktu-waktu hilang. -->
                <iconify-icon class="kk-search-icon" icon="solar:magnifier-linear" width="18" height="18" aria-hidden="true"></iconify-icon>
                <input type="search" class="form-control" id="kkSearch"
                    placeholder="Cari ID, deskripsi, kategori, penerima, No. Akun…"
                    autocomplete="off" spellcheck="false">
                <button type="button" class="kk-clear" id="kkSearchClear" aria-label="Bersihkan pencarian">
                    <iconify-icon icon="solar:close-circle-linear" width="18" height="18"></iconify-icon>
                </button>
            </div>

            <div class="kk-filter">
                <label class="kk-field-label" for="kkStart">Dari</label>
                <input type="date" class="form-control" id="kkStart">
            </div>
            <div class="kk-filter">
                <label class="kk-field-label" for="kkEnd">Sampai</label>
                <input type="date" class="form-control" id="kkEnd">
            </div>
            <div class="kk-filter">
                <label class="kk-field-label" for="kkUnitFilter">Unit</label>
                <select class="form-select" id="kkUnitFilter">
                    <option value="">Semua Unit</option>
                    <?php foreach ($unit as $u): ?>
                        <option value="<?= (int) $u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="kk-cmdbar-actions">
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="kkKolomBtn">
                        <iconify-icon icon="solar:eye-linear" width="18" height="18" aria-hidden="true"></iconify-icon>
                        <span>Kolom</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-2" aria-labelledby="kkKolomBtn" style="min-width: 210px;"></div>
                </div>
                <form action="<?= base_url("export_kas_keluar") ?>" method="post" id="kkExportForm">
                    <input type="hidden" name="tanggal_awal" id="kkExpStart">
                    <input type="hidden" name="tanggal_akhir" id="kkExpEnd">
                    <input type="hidden" name="unit_id" id="kkExpUnit">
                    <button type="submit" class="btn btn-outline-secondary" title="Export memakai filter tanggal dan unit yang aktif, bukan pencarian teks">
                        <iconify-icon icon="solar:export-linear" width="18" height="18" aria-hidden="true"></iconify-icon>
                        <span>Export</span>
                    </button>
                </form>
                <button type="button" class="btn btn-secondary" id="kkResetFilter" hidden>Reset</button>
            </div>
        </div>

        <div class="kk-chips" id="kkChips"></div>

        <div class="kk-idhit" id="kkIdHit" hidden></div>

        <div class="kk-tablewrap" id="kkTableWrap">
            <table class="table kk-table" id="table_kas_keluar" style="width: 100%">
                <thead>
                    <tr>
                        <th scope="col" class="kk-c-id">ID</th>
                        <th scope="col" class="kk-c-date">Tanggal</th>
                        <th scope="col" class="kk-c-unit" data-col="unit">Unit</th>
                        <th scope="col" class="kk-c-kat">Kategori</th>
                        <th scope="col" class="kk-c-akun" data-col="no_akun">No. Akun</th>
                        <th scope="col" class="kk-c-desc">Deskripsi</th>
                        <th scope="col" class="kk-c-penerima">Penerima</th>
                        <th scope="col" class="kk-c-jumlah">Jumlah</th>
                        <th scope="col" class="kk-c-jenis">Debet/Kredit</th>
                        <?php if ($canAct): ?>
                            <th scope="col" class="kk-c-aksi"><span class="visually-hidden">Aksi</span></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr>
                        <?php // Baris kaki harus penjumlah persis sama dengan jumlah kolom
                        // ledger: label menutup ID..Penerima, nominal duduk di bawah
                        // Jumlah, lalu hitungan grabs Debet/Kredit + Aksi. ?>
                        <td colspan="7" class="text-end" id="kkFootLabel">Total</td>
                        <td class="kk-foot-total" id="kkFootTotal">Rp 0</td>
                        <td colspan="<?= $canAct ? 2 : 1 ?>" class="kk-foot-count text-secondary" id="kkFootCount"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="kk-empty" id="kkEmpty">
            <span class="kk-tile" aria-hidden="true">
                <iconify-icon icon="solar:inbox-line-linear" width="22" height="22"></iconify-icon>
            </span>
            <h3 id="kkEmptyTitle">Belum ada kas keluar yang cocok</h3>
            <p id="kkEmptyText">Coba longgarkan filter tanggal, atau pakai ID untuk melompat ke satu transaksi tertentu.</p>
            <button type="button" class="btn btn-outline-secondary" id="kkEmptyReset">Bersihkan semua filter</button>
        </div>

        <div class="kk-pager">
            <label class="visually-hidden" for="kkLength">Baris per halaman</label>
            <select class="form-select form-select-sm" id="kkLength">
                <option value="10">10 per halaman</option>
                <option value="25" selected>25 per halaman</option>
                <option value="50">50 per halaman</option>
                <option value="100">100 per halaman</option>
                <option value="-1">Semua</option>
            </select>
            <div class="dataTables_info" id="kkInfo" role="status" aria-live="polite"></div>
            <nav aria-label="Navigasi halaman" class="ms-auto">
                <ul class="pagination pagination-sm" id="kkPager"></ul>
            </nav>
        </div>
    </section>

    <!-- Konfirmasi hapus: menyebut transaksi yang akan dihapus, bukan
         "apakah Anda yakin" tanpa identitas. -->
    <div class="modal fade" id="kkDeleteModal" tabindex="-1" aria-labelledby="kkDeleteTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <form action="<?= base_url("delete_kas_keluar") ?>" method="post" id="kkDeleteForm">
                    <div class="modal-header">
                        <h2 class="modal-title fs-6" id="kkDeleteTitle">Hapus Kas Keluar</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="idkas_keluar" id="kkDeleteId">
                        <div class="kk-del-body" id="kkDeleteBody"></div>
                        <p class="mb-0 mt-3 small text-secondary">Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
        const byId = (id) => document.getElementById(id.slice(1));
    document.addEventListener('DOMContentLoaded', function () {

        const CAN_ACT = <?= $canAct ? 'true' : 'false' ?>;
        const CAN_PICK_UNIT = <?= $canPickUnit ? 'true' : 'false' ?>;
        const LOGIN_UNIT = <?= $akunUnit ?>;

        // ── Option lists, dipakai untuk baris rincian akun ──────────
        const AKUN_OPTIONS = `<option value="">-- Pilih No. Akun --</option><?php foreach ($no_akun as $a): ?><option value="<?= esc($a->no_akun) ?>"><?= esc($a->no_akun) ?> &mdash; <?= esc($a->nama_akun) ?></option><?php endforeach; ?>`;
        const KAT_OPTIONS = `<option value="">-- Pilih Kategori --</option><?php foreach ($kategori_kas as $kat): ?><option value="<?= (int) $kat->idkategori_kas ?>"><?= esc($kat->kategori) ?></option><?php endforeach; ?>`;
        const BANK_OPTIONS = `<option value="">-- Pilih No. Rekening --</option><?php foreach ($bank as $b): ?><option value="<?= (int) $b->idbank ?>"><?= esc($b->nama_bank . ' · ' . $b->atas_nama . ' · ' . $b->norek) ?></option><?php endforeach; ?>`;

        // ── Format angka & tanggal ─────────────────────────────────
        const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        function rupiah(value) {
            const n = Number(value) || 0;
            return 'Rp ' + Math.round(n).toLocaleString('id-ID');
        }

        function digitsOnly(value) {
            return String(value).replace(/[^0-9]/g, '');
        }

        // Tanggal datang sebagai YYYY-MM-DD. Diurai manual, bukan lewat new Date(),
        // supaya zona waktu browser tidak pernah menggeser tanggal satu hari.
        function tanggalPendek(iso) {
            if (!iso) return '—';
            const p = String(iso).slice(0, 10).split('-');
            if (p.length !== 3) return String(iso);
            const d = parseInt(p[2], 10);
            const m = parseInt(p[1], 10);
            const y = p[0];
            if (!d || !m) return String(iso);
            return String(d).padStart(2, '0') + ' ' + (MONTHS[m - 1] || p[1]) + ' ' + y;
        }

        function el(tag, cls, text) {
            const n = document.createElement(tag);
            if (cls) n.className = cls;
            if (text !== undefined && text !== null) n.textContent = text;
            return n;
        }

        // ── Kolom ledger ───────────────────────────────────────────
        const COLS = [
            { key: 'id', label: 'ID', cls: 'kk-c-id', orderable: true },
            { key: 'tanggal', label: 'Tanggal', cls: 'kk-c-date', orderable: true },
            { key: 'unit', label: 'Unit', cls: 'kk-c-unit', orderable: true, toggle: true },
            { key: 'kategori', label: 'Kategori', cls: 'kk-c-kat', orderable: true },
            { key: 'no_akun', label: 'No. Akun', cls: 'kk-c-akun', orderable: true, toggle: true },
            { key: 'deskripsi', label: 'Deskripsi', cls: 'kk-c-desc', orderable: true },
            { key: 'penerima', label: 'Penerima', cls: 'kk-c-penerima', orderable: true },
            { key: 'jumlah', label: 'Jumlah', cls: 'kk-c-jumlah', orderable: true },
            { key: 'jenis', label: 'Debet/Kredit', cls: 'kk-c-jenis', orderable: true }
        ];
        if (CAN_ACT) {
            COLS.push({ key: 'aksi', label: '', cls: 'kk-c-aksi', orderable: false, searchable: false });
        }

        // ── State pencarian & filter ───────────────────────────────
        let forceTextSearch = false;
        let targetId = null;
        let lastPayload = null;

        const search = document.getElementById('kkSearch');
        const searchWrap = document.getElementById('kkSearchWrap');
        const start = document.getElementById('kkStart');
        const end = document.getElementById('kkEnd');
        const unit = document.getElementById('kkUnitFilter');
        const chips = document.getElementById('kkChips');
        const idHit = document.getElementById('kkIdHit');
        const empty = document.getElementById('kkEmpty');
        const tableWrap = document.getElementById('kkTableWrap');

        function isIdQuery() {
            const q = search.value.trim();
            return q !== '' && !forceTextSearch && /^\d+$/.test(q);
        }

        // ── Composer ───────────────────────────────────────────────
        const composer = byId('#kkComposer');
        const toggle = document.getElementById('kkComposerToggle');
        const title = document.getElementById('kkComposerTitle');
        const sub = document.getElementById('kkComposerSub');
        const live = document.getElementById('kkComposerLive');
        const liveTotal = document.getElementById('kkComposerLiveTotal');
        const liveMeta = document.getElementById('kkComposerLiveMeta');
        const cta = document.getElementById('kkComposerCta');
        const formInsert = document.getElementById('kkFormInsert');
        const formEdit = document.getElementById('kkFormEdit');
        const posBody = document.getElementById('kkPosisiBody');
        const draftTotal = document.getElementById('kkDraftTotal');

        let posisiIndex = 0;
        let composerMode = 'insert';

        function setComposerOpen(open) {
            composer.dataset.open = open ? 'true' : 'false';
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                const first = composer.querySelector('input:not([type=hidden]), select, textarea');
                if (first) setTimeout(() => first.focus(), 180);
            }
        }

        function rupiahInput(input) {
            input.addEventListener('input', function () {
                const d = digitsOnly(input.value);
                input.value = d ? Number(d).toLocaleString('id-ID') : '';
                if (input.id === 'kkEditJumlah') return;
                updateDraftTotal();
            });
            // Saat blur angka dikembalikan ke format ribuan supaya tetap enak
            // dibaca; pembulatan ke angka murni baru terjadi saat submit.
            input.addEventListener('blur', function () {
                const d = digitsOnly(input.value);
                input.value = d ? Number(d).toLocaleString('id-ID') : '';
            });
        }

        function addPosisi() {
            const row = document.createElement('tr');
            const i = posisiIndex++;
            row.innerHTML =
                '<td><select class="form-select kk-pos-no" name="akun[' + i + '][no_akun]" required>' + AKUN_OPTIONS + '</select></td>' +
                '<td><select class="form-select kk-pos-kat" name="akun[' + i + '][kategori_idkategori]" required>' + KAT_OPTIONS + '</select></td>' +
                '<td><select class="form-select kk-pos-sumber" name="akun[' + i + '][jenis_transaksi]">' +
                '<option value="cash" selected>Kas</option><option value="bank">Bank</option></select></td>' +
                '<td><select class="form-select kk-pos-rek" name="akun[' + i + '][no_rekening]" disabled>' + BANK_OPTIONS + '</select></td>' +
                '<td><input type="text" class="form-control kk-pos-penerima" name="akun[' + i + '][penerima]" placeholder="Nama penerima" maxlength="255"></td>' +
                '<td><select class="form-select kk-pos-posisi" name="akun[' + i + '][posisi_drk]">' +
                '<option value="debet" selected>Debet</option><option value="kredit">Kredit</option></select></td>' +
                '<td><input type="text" class="form-control text-end kk-num kk-pos-jumlah" name="akun[' + i + '][jumlah]" inputmode="numeric" placeholder="0" required></td>' +
                '<td class="text-end"><button type="button" class="kk-rowbtn is-danger kk-pos-hapus" title="Hapus baris" aria-label="Hapus baris akun">' +
                '<iconify-icon icon="solar:trash-bin-minimalistic-linear" width="16" height="16"></iconify-icon></button></td>';

            posBody.appendChild(row);

            const $sumber = row.querySelector('.kk-pos-sumber');
            const $rek = row.querySelector('.kk-pos-rek');
            $sumber.addEventListener('change', function () {
                const isBank = this.value === 'bank';
                $rek.disabled = !isBank;
                if (!isBank) {
                    $(rek).val('');
                }
            });

            $(row).find('.kk-pos-no').select2({
                dropdownParent: byId('#kkComposer'),
                width: '100%',
                dropdownAutoWidth: true,
                placeholder: 'Cari akun (mis. Piutang)',
                allowClear: true
            });
            $(row).find('.kk-pos-rek').select2({
                dropdownParent: byId('#kkComposer'),
                width: '100%',
                dropdownAutoWidth: true,
                placeholder: 'Pilih rekening',
                allowClear: true
            });

            rupiahInput(row.querySelector('.kk-pos-jumlah'));
            row.querySelector('.kk-pos-hapus').addEventListener('click', function () {
                row.remove();
                updateDraftTotal();
            });

            updateDraftTotal();
        }

        function updateDraftTotal() {
            let total = 0;
            let n = 0;
            document.querySelectorAll('#kkPosisiBody .kk-pos-jumlah').forEach(function (input) {
                const v = parseInt(digitsOnly(input.value) || '0', 10);
                if (v > 0) {
                    total += v;
                    n++;
                }
            });
            draftTotal.textContent = rupiah(total);
            liveTotal.textContent = rupiah(total);
            liveMeta.textContent = n + ' baris' + (n === 1 ? '' : ' akun');

            const tgl = byId('#kkTanggal').value;
            sub.textContent = (n === 0)
                ? 'Belum ada draf — isi tanggal, unit, lalu tambah baris akun'
                : (tgl ? tanggalPendek(tgl) : 'Tanggal belum diisi') + ' · ' + n + ' baris akun';
            live.hidden = n === 0;
        }

        // Unit terkunci mengikuti akun login, seperti sebelumnya. Harus dipanggil
        // ulang setiap kali form di-reset: `form.reset()` mengembalikan select ke
        // option default ("Pilih Unit"), sehingga unit yang terkunci ikut hilang.
        function applyUnitDefault() {
            const u = byId('#kkUnit');
            if (LOGIN_UNIT > 0) {
                u.value = LOGIN_UNIT;
            }
            const locked = !CAN_PICK_UNIT && LOGIN_UNIT > 0;
            u.disabled = locked;
            byId('#kkUnitHint').hidden = !locked;
        }

        function resetComposer() {
            composerMode = 'insert';
            posisiIndex = 0;
            formInsert.reset();
            applyUnitDefault();
            formEdit.reset();
            posBody.innerHTML = '';
            // Default ke hari ini, seperti alur input sebelumnya. Tanggal disusun
            // dari jam lokal supaya tidak pernah bergeser sehari karena zona waktu.
            const now = new Date();
            byId('#kkTanggal').value = now.getFullYear() + '-' +
                String(now.getMonth() + 1).padStart(2, '0') + '-' +
                String(now.getDate()).padStart(2, '0');
            title.textContent = 'Input Kas Keluar';
            cta.textContent = 'Input';
            formInsert.hidden = false;
            formEdit.hidden = true;
            addPosisi();
            updateDraftTotal();
        }

        function openEdit(row) {
            composerMode = 'edit';
            title.textContent = 'Edit Kas Keluar #' + row.id;
            cta.textContent = 'Edit';
            formInsert.hidden = true;
            formEdit.hidden = false;
            live.hidden = true;
            sub.textContent = tanggalPendek(row.tanggal) + ' · ' + (row.deskripsi || 'tanpa deskripsi');

            byId('#kkEditId').value = row.id;
            byId('#kkEditTanggal').value = row.tanggal;
            byId('#kkEditKategori').value = row.kategori_id || '';
            byId('#kkEditDeskripsi').value = row.deskripsi;
            byId('#kkEditJumlah').value = digitsOnly(String(Math.round(Number(row.jumlah) || 0)));
            byId('#kkEditPenerima').value = row.idbank || '';
            byId('#kkEditPosisi').value = (row.jenis || 'debet').toLowerCase();
            setComposerOpen(true);
        }

        toggle.addEventListener('click', function () {
            if (composerMode === 'edit') {
                setComposerOpen(false);
                return;
            }
            const opening = composer.dataset.open !== 'true';
            setComposerOpen(opening);
            if (opening && posBody.children.length === 0) {
                addPosisi();
            }
        });

        document.querySelectorAll('[data-kk-fold]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setComposerOpen(false);
            });
        });

        byId('#kkAddPosisi').addEventListener('click', addPosisi);

        // Aksi baris didelegasikan ke <tbody> supaya tetap hidup setelah
        // tabel digambar ulang oleh DataTables.
        byId('#table_kas_keluar').addEventListener('click', function (e) {
            const btn = e.target.closest('.kk-edit, .kk-del');
            if (!btn) return;
            const id = btn.dataset.id;
            const row = dt.rows().data().toArray().filter(function (r) {
                return String(r.id) === String(id);
            })[0];
            if (!row) return;
            if (btn.classList.contains('kk-edit')) {
                openEdit(row);
            } else {
                askDelete(row);
            }
        });

        byId('#kkEditBatal').addEventListener('click', function () {
            resetComposer();
            setComposerOpen(false);
        });
        byId('#kkTanggal').addEventListener('change', updateDraftTotal);

        // Bersihkan format ribuan dan pastikan unit terkunci tetap terkirim.
        formInsert.addEventListener('submit', function (e) {
            const u = byId('#kkUnit');
            if (u.disabled) u.disabled = false;

            // Satu transaksi harus punya minimal satu baris akun. Baris akun
            // bisa saja semuanya dihapus, dan tanpa penjaga ini form tetap terkirim.
            const rows = posBody.querySelectorAll('tr').length;
            if (rows === 0) {
                e.preventDefault();
                posBody.closest('.kk-pos-table').closest('div').scrollIntoView({ block: 'center' });
                addPosisi();
                byId('#kkPosisiBody').querySelector('.kk-pos-no').focus();
                return;
            }

            document.querySelectorAll('#kkPosisiBody .kk-pos-jumlah').forEach(function (input) {
                input.value = digitsOnly(input.value) || '0';
            });
        });
        formEdit.addEventListener('submit', function () {
            byId('#kkEditJumlah').value = digitsOnly(byId('#kkEditJumlah').value) || '0';
        });

        resetComposer();

        // ── Kolom tabel: pembentuk sel ────────────────────────────
        function cellId(row) {
            return el('span', 'kk-line1', '#' + row.id);
        }

        function cellTwo(main, sub, subMono) {
            const wrap = document.createDocumentFragment();
            const line1 = el('span', 'kk-clip', main);
            wrap.appendChild(line1);
            if (sub) {
                const line2 = el('span', 'kk-line2' + (subMono ? ' kk-mono' : ''), sub);
                wrap.appendChild(line2);
            }
            return wrap;
        }

        function cellKategori(row) {
            if (!row.kategori) return el('span', 'kk-line2', '—');
            const tag = el('span', 'kk-tag', row.kategori);
            tag.title = row.kategori;
            return tag;
        }

        function cellJumlah(row) {
            return el('span', 'kk-amount', rupiah(row.jumlah));
        }

        function cellJenis(row) {
            const v = String(row.jenis || '').toLowerCase();
            if (v === 'debet' || v === 'kredit') {
                const tag = el('span', 'kk-tag ' + (v === 'debet' ? 'kk-pos-debet' : 'kk-pos-kredit'), v === 'debet' ? 'Debet' : 'Kredit');
                return tag;
            }
            const tag = el('span', 'kk-tag kk-pos-kosong', '—');
            tag.title = 'Baris ini tidak diisi pada transaksi ini';
            return tag;
        }

        function cellAksi(row) {
            const wrap = el('div', 'd-flex justify-content-end gap-1');

            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'kk-rowbtn kk-edit';
            edit.title = 'Edit transaksi #' + row.id;
            edit.setAttribute('aria-label', 'Edit transaksi #' + row.id);
            edit.dataset.id = String(row.id);
            // Solar tidak punya ikon `pencil-*`; pena tulisnya `pen-2`.
            edit.innerHTML = '<iconify-icon icon="solar:pen-2-linear" width="16" height="16"></iconify-icon>';

            const del = document.createElement('button');
            del.type = 'button';
            del.className = 'kk-rowbtn is-danger kk-del';
            del.title = 'Hapus transaksi #' + row.id;
            del.setAttribute('aria-label', 'Hapus transaksi #' + row.id);
            del.dataset.id = String(row.id);
            del.innerHTML = '<iconify-icon icon="solar:trash-bin-minimalistic-linear" width="16" height="16"></iconify-icon>';

            wrap.appendChild(edit);
            wrap.appendChild(del);
            return wrap;
        }

        function askDelete(row) {
            byId('#kkDeleteId').value = row.id;
            const body = byId('#kkDeleteBody');
            body.innerHTML = '';
            body.appendChild(el('span', '', 'Hapus kas keluar'));
            body.appendChild(el('b', 'kk-mono', '#' + row.id));
            body.appendChild(el('span', '', tanggalPendek(row.tanggal) + (row.unit ? ' · ' + row.unit : '')));
            body.appendChild(el('b', 'kk-num', rupiah(row.jumlah)));
            if (row.deskripsi) {
                body.appendChild(el('span', 'kk-clip', row.deskripsi));
            }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('kkDeleteModal')).show();
        }

        // DataTables menulis hasil render ke innerHTML, jadi sel harus berupa
        // markup, bukan node. Konsekuensnya listener tidak bisa menempel di
        // dalam sel: aksi baris memakai delegasi dari <tbody>.
        function markup(node) {
            if (!node) return '';
            if (node.nodeType === 11) {
                const box = document.createElement('div');
                box.appendChild(node);
                return box.innerHTML;
            }
            return node.outerHTML;
        }

        const RENDER = {
            id: cellId,
            tanggal: (row) => el('span', 'kk-line1 kk-num', tanggalPendek(row.tanggal)),
            unit: (row) => el('span', 'kk-clip', row.unit || '—'),
            kategori: cellKategori,
            no_akun: (row) => cellTwo(row.no_akun || '—', row.nama_akun, true),
            deskripsi: (row) => {
                const s = el('span', 'kk-clip', row.deskripsi || '—');
                s.title = row.deskripsi || '';
                return s;
            },
            penerima: (row) => cellTwo(row.penerima || '—', row.nama_bank ? (row.nama_bank + ' · ' + row.norek) : '', true),
            jumlah: cellJumlah,
            jenis: cellJenis,
            aksi: cellAksi
        };

        const dt = jQuery(byId('#table_kas_keluar')).DataTable({
            processing: true,
            serverSide: true,
            deferRender: true,
            ajax: {
                url: '<?= base_url("kas_keluar/datatables") ?>',
                type: 'GET',
                data: function (d) {
                    d.tanggal_awal = start.value;
                    d.tanggal_akhir = end.value;
                    d.unit_id = unit.value;
                    d.search_mode = forceTextSearch ? 'text' : 'auto';
                }
            },
            order: [[1, 'desc']],
            columns: COLS.map(function (c) {
                return {
                    // Kolom Aksi tidak punya field di payload, jadi `data` kosong;
                    // renderer tetap menerima objek baris penuh lewat argumen `row`.
                    data: c.key,
                    className: c.cls,
                    orderable: c.orderable,
                    searchable: false,
                    render: function (data, type, row) {
                        if (type !== 'display') return data;
                        return markup(RENDER[c.key](row || {}));
                    }
                };
            }),
            pageLength: 25,
            lengthChange: false,
            // Pager dan pemilih panjang dibangun sendiri di dalam deck ini,
            // jadi DataTables hanya menghasilkan badan tabel.
            dom: 'rt',
            language: {
                emptyTable: '',
                zeroRecords: ''
            },
            drawCallback: function (settings) {
                const json = settings.json || {};
                const rows = json.data || [];
                lastPayload = json;

                const api = this.api();
                COLS.forEach(function (c, i) {
                    const cell = api.column(i).header();
                    if (cell) cell.dataset.label = c.label || 'Aksi';
                    api.column(i).nodes().toArray().forEach(function (td) {
                        td.dataset.label = c.label || 'Aksi';
                    });
                });

                // Total di kaki tabel: dari SELURUH baris terfilter, bukan
                // penjumlahan halaman ini saja.
                byId('#kkFootTotal').textContent = rupiah(json.sumTotal || 0);
                byId('#kkFootCount').textContent = (json.recordsFiltered || 0) + ' transaksi';

                const isFiltered = (json.recordsFiltered || 0) !== (json.recordsTotal || 0);
                byId('#kkFootLabel').textContent = isFiltered
                    ? 'Total hasil filter'
                    : 'Total';

                // Pager dirakit sendiri supaya seqaras dengan deck ini.
                renderPager(api, json);
                renderIdHit(json);
                renderChips();
                renderScope(json);

                const kosong = rows.length === 0;
                tableWrap.hidden = kosong;
                empty.classList.toggle('is-shown', kosong);
                byId('#kkPager').parentElement.hidden = kosong;
                byId('#kkInfo').textContent = kosong ? '' : infoText(api, json);

                markTarget();
            }
        });

        function infoText(api, json) {
            const pi = api.page.info();
            return 'Menampilkan ' + (pi.start + 1) + '–' + pi.end + ' dari ' +
                (json.recordsFiltered || 0) + ' transaksi';
        }

        function renderPager(api, json) {
            const ul = document.getElementById('kkPager');
            ul.innerHTML = '';
            const pages = api.page.info().pages;
            if (pages <= 1) return;

            const total = api.page.info().recordsTotal;
            const current = api.page.info().page + 1;

            function item(label, page, opts) {
                const o = opts || {};
                const li = document.createElement('li');
                li.className = 'page-item' + (o.active ? ' active' : '') + (o.disabled ? ' disabled' : '');
                const a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.setAttribute('aria-label', o.label || ('Halaman ' + (page + 1)));
                if (o.disabled) {
                    a.setAttribute('aria-disabled', 'true');
                    a.tabIndex = -1;
                } else {
                    a.addEventListener('click', function (e) {
                        e.preventDefault();
                        api.page(page).draw(false);
                    });
                }
                a.innerHTML = label;
                li.appendChild(a);
                return li;
            }

            ul.appendChild(item('<iconify-icon icon="solar:alt-arrow-left-linear" width="14" height="14"></iconify-icon>',
                current - 2, { disabled: current === 1, label: 'Halaman sebelumnya' }));

            const win = 2;
            let from = Math.max(0, current - 1 - win);
            let to = Math.min(pages, from + win * 2 + 1);
            from = Math.max(0, to - win * 2 - 1);

            if (from > 0) {
                ul.appendChild(item('1', 0));
                if (from > 1) ul.appendChild(item('…', -1, { disabled: true }));
            }
            for (let p = from; p < to; p++) {
                ul.appendChild(item(String(p + 1), p, { active: p === current - 1 }));
            }
            if (to < pages) {
                if (to < pages - 1) ul.appendChild(item('…', -1, { disabled: true }));
                ul.appendChild(item(String(pages), pages - 1));
            }

            ul.appendChild(item('<iconify-icon icon="solar:alt-arrow-right-linear" width="14" height="14"></iconify-icon>',
                current, { disabled: current === pages, label: 'Halaman berikutnya' }));
        }

        // ── Chip filter aktif ──────────────────────────────────────
        function chip(label, value, onRemove) {
            const c = el('span', 'kk-chip');
            c.appendChild(el('span', '', label));
            c.appendChild(el('b', '', value));
            const x = document.createElement('button');
            x.type = 'button';
            x.setAttribute('aria-label', 'Hapus filter ' + label);
            x.innerHTML = '<iconify-icon icon="solar:close-circle-linear" width="14" height="14"></iconify-icon>';
            x.addEventListener('click', onRemove);
            c.appendChild(x);
            return c;
        }

        function renderChips() {
            chips.innerHTML = '';
            const q = search.value.trim();
            if (q) {
                chips.appendChild(chip(
                    isIdQuery() ? 'ID' : 'Cari',
                    q,
                    function () {
                        search.value = '';
                        forceTextSearch = false;
                        searchWrap.dataset.filled = 'false';
                        dt.search('').page(0).draw(false);
                    }
                ));
            }
            if (start.value) {
                chips.appendChild(chip('Dari', tanggalPendek(start.value), function () {
                    start.value = '';
                    dt.draw(false);
                }));
            }
            if (end.value) {
                chips.appendChild(chip('Sampai', tanggalPendek(end.value), function () {
                    end.value = '';
                    dt.draw(false);
                }));
            }
            if (unit.value) {
                const opt = unit.options[unit.selectedIndex].text;
                chips.appendChild(chip('Unit', opt, function () {
                    unit.value = '';
                    dt.draw(false);
                }));
            }
            document.getElementById('kkResetFilter').hidden = (chips.children.length === 0);
        }

        // Scope readout dibangun dari simpul DOM, bukan innerHTML: nama unit
        // berasal dari server dan tidak boleh menjadi markup.
        function renderScope(json) {
            const host = byId('#kkScope');
            host.innerHTML = '';
            const bits = [];
            bits.push([el('b', '', String(json.recordsFiltered || 0)), document.createTextNode(' transaksi')]);
            bits.push([document.createTextNode(rupiah(json.sumTotal || 0))]);
            if (start.value || end.value) {
                const a = start.value ? tanggalPendek(start.value) : 'awal';
                const b = end.value ? tanggalPendek(end.value) : 'hari ini';
                bits.push([document.createTextNode(a + ' – ' + b)]);
            } else {
                bits.push([document.createTextNode('Semua periode')]);
            }
            bits.push([document.createTextNode(unit.value ? unit.options[unit.selectedIndex].text : 'Semua unit')]);

            bits.forEach(function (nodes, i) {
                if (i > 0) {
                    const dot = el('span', '', '·');
                    dot.setAttribute('aria-hidden', 'true');
                    host.appendChild(dot);
                }
                nodes.forEach(function (n) { host.appendChild(n); });
            });
        }

        // ── Jawaban pencarian ID ───────────────────────────────────
        function renderIdHit(json) {
            idHit.hidden = true;
            idHit.className = 'kk-idhit';
            idHit.innerHTML = '';

            if (!json.isIdQuery) return;

            const id = search.value.trim();

            if (!json.idHit) {
                idHit.classList.add('is-miss');
                idHit.appendChild(icon('solar:question-circle-linear'));
                idHit.appendChild(el('span', 'kk-idhit-id', 'ID #' + id));
                idHit.appendChild(el('span', 'kk-idhit-what', 'tidak ada transaksi dengan ID tersebut'));
                const btn = el('button', 'btn btn-sm btn-outline-secondary', 'Cari teks yang mengandung "' + id + '"');
                btn.addEventListener('click', function () {
                    forceTextSearch = true;
                    dt.search(id).page(0).draw(false);
                });
                idHit.appendChild(btn);
                idHit.hidden = false;
                targetId = null;
                return;
            }

            const hit = json.idHit;
            targetId = hit.id;

            if (!hit.inScope) {
                idHit.classList.add('is-out');
                idHit.appendChild(icon('solar:filter-linear'));
                idHit.appendChild(el('span', 'kk-idhit-id', 'ID #' + hit.id));
                idHit.appendChild(el('span', 'kk-idhit-what',
                    'ada, tapi di luar filter tanggal/unit yang aktif'));
                const btn = el('button', 'btn btn-sm btn-outline-secondary', 'Bersihkan filter');
                btn.addEventListener('click', function () {
                    start.value = '';
                    end.value = '';
                    unit.value = '';
                    dt.page(0).draw(false);
                });
                idHit.appendChild(btn);
                idHit.hidden = false;
                return;
            }

            idHit.classList.add('is-found');
            idHit.appendChild(icon('solar:check-circle-linear'));
            idHit.appendChild(el('span', 'kk-idhit-id', 'ID #' + hit.id));
            idHit.appendChild(el('span', 'kk-idhit-what',
                tanggalPendek(hit.tanggal) + (hit.unit ? ' · ' + hit.unit : '') +
                (hit.deskripsi ? ' · ' + hit.deskripsi : '')));
            idHit.appendChild(el('span', 'kk-num fw-semibold', rupiah(hit.jumlah)));
            idHit.hidden = false;
        }

        function icon(name) {
            const i = document.createElement('iconify-icon');
            i.setAttribute('icon', name);
            i.setAttribute('width', '18');
            i.setAttribute('height', '18');
            i.setAttribute('aria-hidden', 'true');
            i.classList.add('flex-shrink-0');
            return i;
        }

        // Baris yang dituju jawaban ID disorot, supaya "ditemukan" terasa terjadi
        // di tabel, bukan hanya di chip.
        function markTarget() {
            if (!targetId) return;
            dt.rows().every(function () {
                const data = this.data();
                $(this.node()).toggleClass('is-target', String(data.id) === String(targetId));
            });
        }

        // ── Kolom toggle ───────────────────────────────────────────
        (function buildColumnToggle() {
            const menu = document.getElementById('kkKolomBtn').nextElementSibling;
            if (!menu) return;
            let stored = {};
            try {
                stored = JSON.parse(localStorage.getItem('kkColumns') || '{}');
            } catch (e) { /* storage tidak tersedia */ }

            COLS.forEach(function (c, i) {
                if (!c.toggle) return;
                const id = 'kkCol' + i;
                const wrap = document.createElement('div');
                wrap.className = 'form-check';
                const input = document.createElement('input');
                input.className = 'form-check-input';
                input.type = 'checkbox';
                input.id = id;
                input.checked = stored[c.key] === undefined ? true : !!stored[c.key];
                const label = document.createElement('label');
                label.className = 'form-check-label';
                label.setAttribute('for', id);
                label.textContent = c.label;
                input.addEventListener('change', function () {
                    dt.column(i).visible(this.checked);
                    const map = {};
                    COLS.forEach(function (cc, ii) {
                        if (cc.toggle) map[cc.key] = dt.column(ii).visible();
                    });
                    try {
                        localStorage.setItem('kkColumns', JSON.stringify(map));
                    } catch (e) { /* storage tidak tersedia */ }
                });
                wrap.appendChild(input);
                wrap.appendChild(label);
                menu.appendChild(wrap);
                if (!input.checked) dt.column(i).visible(false);
            });
        })();

        // ── Pencarian: satu input, dua mode ────────────────────────
        let searchTimer = null;
        search.addEventListener('input', function () {
            const q = search.value.trim();
            searchWrap.dataset.filled = q ? 'true' : 'false';
            // Mode kembali mengikuti isi kolom: digit = ID, selain itu teks.
            forceTextSearch = false;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                dt.search(q).page(0).draw(false);
            }, 260);
        });

        search.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && search.value !== '') {
                e.stopPropagation();
                search.value = '';
                searchWrap.dataset.filled = 'false';
                forceTextSearch = false;
                dt.search('').page(0).draw(false);
            }
        });

        byId('#kkSearchClear').addEventListener('click', function () {
            search.value = '';
            searchWrap.dataset.filled = 'false';
            forceTextSearch = false;
            dt.search('').page(0).draw(false);
            search.focus();
        });

        start.addEventListener('change', function () { dt.page(0).draw(false); });
        end.addEventListener('change', function () { dt.page(0).draw(false); });
        unit.addEventListener('change', function () { dt.page(0).draw(false); });

        function resetAll() {
            search.value = '';
            searchWrap.dataset.filled = 'false';
            start.value = '';
            end.value = '';
            unit.value = '';
            forceTextSearch = false;
            targetId = null;
            dt.search('').page(0).draw(false);
        }

        byId('#kkResetFilter').addEventListener('click', resetAll);
        byId('#kkEmptyReset').addEventListener('click', resetAll);

        byId('#kkLength').addEventListener('change', function () {
            dt.page.len(this.value === '-1' ? -1 : parseInt(this.value, 10)).draw(false);
        });

        byId('#kkExportForm').addEventListener('submit', function () {
            byId('#kkExpStart').value = start.value;
            byId('#kkExpEnd').value = end.value;
            byId('#kkExpUnit').value = unit.value;
        });

        // Export mengikuti perubahan panjang halaman.
        dt.on('length.dt', function () {
            byId('#kkLength').value = String(dt.page.len());
        });
    });
</script>
