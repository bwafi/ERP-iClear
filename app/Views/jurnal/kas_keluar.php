<?php
/**
 * Kas Keluar — ledger-first.
 *
 * Daftar transaksi mengisi halaman; input dan edit dibuka di modal supaya
 * daftar tidak pernah terdorong atau ditinggalkan. Setiap
 * baris rincian akun adalah satu transaksi kas keluar — bentuk yang memang
 * disimpan backend — jadi ditampilkan sebagai kartu, bukan tabel.
 *
 * Aturan yang dijaga: nama field, route, logika bisnis, dan gating jabatan
 * tidak berubah. Yang berubah hanya susunan, hierarki, dan cara mencari.
 */
$canAct = (int) ($akun->ID_JABATAN ?? 1) === 1;
$akunRole = (int) ($akun->ID_JABATAN ?? 0);
$akunUnit = (int) ($akun->ID_UNIT ?? 0);
$canPickUnit = in_array($akunRole, [0, 1, 2, 34], true);
$unitFilter = $unit_filter ?? [];
$canFilterUnit = $can_filter_unit ?? false;
$showAllUnits = $show_all_units ?? false;
$defaultFilterUnit = (int) ($default_filter_unit ?? 0);
// Kategori 6% berasal dari Config\Finance (sumber yang sama dengan
// /asset_berjalan). Label opsi sudah di-escape di sini supaya aman saat
// disusun ulang lewat innerHTML di JS.
$kategoriTerkontrol = array_map('intval', $kategori_terkontrol ?? []);
$kategoriOpsi = array_map(static function ($kat) use ($kategoriTerkontrol): array {
    $id = (int) $kat->idkategori_kas;
    return [
        'id'   => $id,
        'nama' => esc($kat->kategori),
        'enam' => in_array($id, $kategoriTerkontrol, true),
    ];
}, $kategori_kas);
// Isian yang gagal disimpan dikirim balik oleh controller supaya modal bisa
// dibuka lagi dengan nilai yang sama — bukan halaman kosong.
$oldInput = $old_input ?? null;
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

    .kk-masthead-side {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .75rem 1.25rem;
    }

    /* ── Modal input/edit ─────────────────────────────────────── */
    /* Modal, bukan sheet samping: formulirnya pendek, dan daftar di belakang
       tidak ikut bergeser maupun kehilangan posisi gulir. */
    .kk-modal .modal-dialog {
        max-width: 640px;
    }

    .kk-modal .modal-content {
        background: var(--kk-surface);
        border: 0;
        border-radius: var(--kk-r);
    }

    .kk-modal .modal-header {
        align-items: flex-start;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--kk-line);
    }

    .kk-modal .modal-title {
        font-size: 1.0625rem;
        font-weight: 600;
        line-height: 1.25;
        margin: 0;
    }

    .kk-modal-sub {
        display: block;
        font-size: .8125rem;
        font-weight: 400;
        color: var(--kk-ink-3);
        margin-top: .1875rem;
    }

    .kk-modal .modal-body {
        padding: 1.25rem;
    }

    .kk-modal form>*+* {
        margin-top: 1rem;
    }

    /* Tombol simpan selalu terlihat walau isian transaksi memanjang. */
    .kk-modal-foot {
        position: sticky;
        bottom: 0;
        margin: 1.25rem -1.25rem -1.25rem;
        padding: 1rem 1.25rem;
        background: var(--kk-surface);
        border-top: 1px solid var(--kk-line);
    }

    .kk-draft-split {
        display: block;
        font-size: .75rem;
        color: var(--kk-ink-3);
        margin-top: .125rem;
    }

    .kk-draft-split b {
        font-weight: 600;
        color: color-mix(in srgb, var(--bs-success) 60%, var(--kk-ink));
    }

    /* Satu kartu = satu transaksi kas keluar. Kartu vertikal, bukan tabel
       delapan kolom yang harus digeser ke samping di ponsel. */
    .kk-tx {
        padding: .875rem;
        border: 1px solid var(--kk-line);
        border-radius: 12px;
        background: var(--kk-raise);
    }

    .kk-tx+.kk-tx {
        margin-top: .75rem;
    }

    .kk-tx-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        margin-bottom: .75rem;
    }

    .kk-tx-head span {
        font-size: .6875rem;
        font-weight: 600;
        letter-spacing: .03em;
        text-transform: uppercase;
        color: var(--kk-ink-3);
    }

    .kk-tx-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .75rem;
    }

    .kk-tx-grid .is-full {
        grid-column: 1 / -1;
    }

    .kk-tx .form-control,
    .kk-tx .form-select {
        font-size: .875rem;
    }

    @media (max-width: 575.98px) {
        .kk-tx-grid {
            grid-template-columns: minmax(0, 1fr);
        }
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

    /* Pemisah antara data transaksi dan rincian akun di dalam modal. */
    .kk-subhead {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-top: 1.25rem;
    }

    .kk-subhead .kk-field-label {
        margin: 0;
    }

    /* Hanya badge "6%" yang berwarna, bukan seluruh nama kategori. Warna
       dicampur ke warna teks supaya tetap terbaca di tema terang dan gelap. */
    .kk-kat-enam {
        color: color-mix(in srgb, var(--bs-success) 60%, var(--kk-ink));
        font-weight: 600;
    }

    .kk-tag-enam {
        background: var(--bs-success-bg-subtle);
        color: color-mix(in srgb, var(--bs-success) 60%, var(--kk-ink));
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--bs-success) 35%, transparent);
    }

    .kk-kat-cell {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .25rem;
        max-width: 100%;
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

    /* Tombol radio filter setinggi input di sebelahnya, supaya label-labelnya
       tetap sebaris. */
    .kk-filter .btn-group .btn {
        height: 38px;
        display: inline-flex;
        align-items: center;
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

    /* ── Ringkasan 6% ─────────────────────────────────────────── */
    /* Rekonsiliasi cepat: berapa yang masuk hitungan Biaya Terkontrol dan
       berapa yang tidak, untuk filter yang sedang aktif. */
    .kk-sum {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        border-bottom: 1px solid var(--kk-line);
    }

    .kk-sum-cell {
        min-width: 0;
        padding: .875rem 1rem;
    }

    .kk-sum-cell+.kk-sum-cell {
        border-left: 1px solid var(--kk-line);
    }

    .kk-sum-cell span {
        display: block;
        font-size: .6875rem;
        font-weight: 600;
        letter-spacing: .03em;
        text-transform: uppercase;
        color: var(--kk-ink-3);
    }

    .kk-sum-cell b {
        display: block;
        margin-top: .125rem;
        font-size: 1rem;
        font-weight: 600;
        color: var(--kk-ink);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .kk-sum-cell small {
        display: block;
        margin-top: .125rem;
        font-size: .75rem;
        color: var(--kk-ink-3);
    }

    .kk-sum-cell.is-enam b {
        color: color-mix(in srgb, var(--bs-success) 60%, var(--kk-ink));
    }

    .kk-dot {
        display: inline-block;
        width: 7px;
        height: 7px;
        margin-right: .375rem;
        border-radius: 50%;
        background: var(--bs-success);
        vertical-align: middle;
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

    .kk-c-tgl {
        width: 142px;
    }

    .kk-c-kat {
        width: 168px;
    }

    /* Keterangan menyerap sisa lebar; batas minimum menjaga kolom lain tidak
       terdorong saat deskripsi panjang. */
    .kk-c-ket {
        width: auto;
        min-width: 220px;
    }

    .kk-c-akun {
        width: 150px;
    }

    .kk-c-jumlah {
        width: 150px;
        text-align: right;
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

    .kk-clip {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Deskripsi boleh dua baris lalu dipangkas, supaya teksnya tetap terbaca
       tanpa harus hover. `white-space: nowrap` sengaja tidak dipakai di sini:
       teks yang tidak membungkus membuat lebar minimum sel sama dengan panjang
       teks, dan itu yang membuat kolom mengabaikan batas lebarnya.
       `overflow-wrap` menjaga referensi panjang tanpa spasi tidak meluber. */
    .kk-desc {
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        overflow: hidden;
        overflow-wrap: anywhere;
        cursor: help;
    }

    /* Tooltip deskripsi. Dipasang ke <body> dengan position: fixed karena
       `.kk-tablewrap` memakai `contain: paint` — tooltip yang diletakkan di
       dalam wrap akan terpotong oleh konteks tersebut. */
    .kk-tip {
        position: fixed;
        top: 0;
        left: 0;
        z-index: 1080;
        max-width: min(28rem, calc(100vw - 2rem));
        padding: .5rem .625rem;
        border-radius: 8px;
        background: var(--bs-dark, #1c2430);
        color: #fff;
        font-size: .8125rem;
        line-height: 1.45;
        overflow-wrap: anywhere;
        box-shadow: 0 .5rem 1.25rem rgba(0, 0, 0, .3);
        pointer-events: none;
        opacity: 0;
        transition: opacity .12s ease;
    }

    .kk-tip.is-tampil {
        opacity: 1;
    }

    .kk-desc:focus-visible {
        outline: 2px solid var(--bs-primary);
        outline-offset: 2px;
        border-radius: 3px;
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

        .kk-table td.kk-c-ket,
        .kk-table td.kk-c-akun {
            flex-direction: column;
            align-items: flex-end;
        }

        .kk-table td.kk-c-ket::before,
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

        .kk-c-tgl,
        .kk-c-kat,
        .kk-c-ket,
        .kk-c-akun,
        .kk-c-jumlah,
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

        /* Tiga nominal Rupiah tidak muat sejajar di ponsel; total jadi baris
           sendiri, 6% dan di luar 6% berbagi satu baris. */
        .kk-sum {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .kk-sum-cell:first-child {
            grid-column: 1 / -1;
            border-bottom: 1px solid var(--kk-line);
        }

        .kk-sum-cell:nth-child(2) {
            border-left: 0;
        }
    }

    @media (prefers-reduced-motion: reduce) {

        .kk-rowbtn,
        .kk-tip {
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

    <?php if (session("gagal")): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= esc(session("gagal")) ?>
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
        <div class="kk-masthead-side">
            <div class="kk-scope-readout" id="kkScope"></div>
            <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" id="kkOpenSheet">
                <iconify-icon icon="solar:add-circle-linear" width="18" height="18" aria-hidden="true"></iconify-icon>
                <span>Input Kas Keluar</span>
            </button>
        </div>
    </header>

    <!-- Modal input/edit: daftar tetap di belakang, posisi gulir tidak hilang
         saat satu baris dikoreksi. -->
    <div class="modal fade kk-modal" id="kkSheet" tabindex="-1" aria-labelledby="kkSheetTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="kkSheetTitle">
                        <span id="kkSheetTitleText">Input Kas Keluar</span>
                        <span class="kk-modal-sub" id="kkSheetSub">Satu transaksi, satu baris rincian akun</span>
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">

                <!-- Form input: route insert_kas_keluar, banyak baris akun -->
                <form action="<?= base_url("insert_kas_keluar") ?>" method="post" id="kkFormInsert" novalidate>
                    <div class="kk-tx-grid">
                        <div>
                            <label class="kk-field-label" for="kkTanggal">Tanggal</label>
                            <input type="date" class="form-control" name="tanggal" id="kkTanggal" required>
                        </div>
                        <div>
                            <label class="kk-field-label" for="kkUnit">Unit</label>
                            <select class="form-select" name="unit_idunit" id="kkUnit" required>
                                <option value="">Pilih Unit</option>
                                <?php foreach ($unit as $u): ?>
                                    <option value="<?= (int) $u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text" id="kkUnitHint" hidden>Unit mengikuti akun yang login</div>
                        </div>
                        <div class="is-full">
                            <label class="kk-field-label" for="kkDeskripsi">Deskripsi</label>
                            <input type="text" class="form-control" name="deskripsi" id="kkDeskripsi"
                                placeholder="contoh: Pembelian ATK" maxlength="255">
                        </div>
                    </div>

                    <div class="kk-subhead">
                        <span class="kk-field-label" id="kkPosLabel">Transaksi</span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="kkHanya6">
                                <label class="form-check-label small" for="kkHanya6"
                                    title="Tampilkan hanya kategori yang masuk hitungan Biaya Terkontrol (maks 6% omset)">Hanya kategori 6%</label>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 px-3 py-2" id="kkAddPosisi">
                                <iconify-icon icon="solar:add-circle-linear" width="16" height="16" aria-hidden="true"></iconify-icon>
                                <span>Tambah transaksi</span>
                            </button>
                        </div>
                    </div>

                    <div id="kkPosisiBody" aria-labelledby="kkPosLabel"></div>

                    <p class="kk-note">
                        <iconify-icon icon="solar:info-circle-linear" width="16" height="16" class="flex-shrink-0 mt-1" aria-hidden="true"></iconify-icon>
                        <span>Kategori bertanda <b class="kk-kat-enam">6%</b> masuk hitungan <b>Biaya Terkontrol (maks 6% omset)</b> di /asset_berjalan. Batas ini peringatan, transaksi tetap tersimpan.</span>
                    </p>

                    <div class="kk-modal-foot">
                        <div class="kk-total-line">
                            <span>Total draf</span>
                            <b class="kk-num" id="kkDraftTotal">Rp 0</b>
                        </div>
                        <span class="kk-draft-split" id="kkDraftSplit">6% Rp 0 · Lain Rp 0</span>
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-outline-secondary" id="kkInsertBatal">Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan Kas Keluar</button>
                        </div>
                    </div>
                </form>

                <!-- Form edit: route update_kas_keluar, satu baris (bentuk yang
                     benar-benar diterima route ini) -->
                <form action="<?= base_url("update_kas_keluar") ?>" method="post" id="kkFormEdit" hidden>
                    <input type="hidden" name="idkas_keluar" id="kkEditId">
                    <input type="hidden" name="idunit" id="kkEditUnit">
                    <div class="kk-tx-grid">
                        <div>
                            <label class="kk-field-label" for="kkEditTanggal">Tanggal</label>
                            <input type="date" class="form-control" name="tanggal" id="kkEditTanggal" required>
                        </div>
                        <div>
                            <label class="kk-field-label" for="kkEditPosisi">Debet/Kredit</label>
                            <select class="form-select" name="posisi_drk" id="kkEditPosisi">
                                <option value="debet">Debet</option>
                                <option value="kredit">Kredit</option>
                            </select>
                        </div>
                        <div class="is-full">
                            <label class="kk-field-label" for="kkEditKategori">Kategori</label>
                            <select class="form-select" name="kategori_idkategori" id="kkEditKategori" required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($kategoriOpsi as $kat): ?>
                                    <option value="<?= (int) $kat['id'] ?>"><?= $kat['nama'] ?><?= $kat['enam'] ? ' · 6%' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="is-full">
                            <label class="kk-field-label" for="kkEditPenerima">Penerima / Rekening</label>
                            <select class="form-select" name="penerima" id="kkEditPenerima">
                                <option value="">— Tidak ada rekening —</option>
                            </select>
                        </div>
                        <div class="is-full">
                            <label class="kk-field-label" for="kkEditDeskripsi">Deskripsi</label>
                            <input type="text" class="form-control" name="deskripsi" id="kkEditDeskripsi" maxlength="255" required>
                        </div>
                        <div class="is-full">
                            <label class="kk-field-label" for="kkEditJumlah">Jumlah</label>
                            <input type="text" class="form-control text-end kk-num" name="jumlah" id="kkEditJumlah"
                                inputmode="numeric" required>
                        </div>
                    </div>

                    <p class="kk-note">
                        <iconify-icon icon="solar:info-circle-linear" width="16" height="16" class="flex-shrink-0 mt-1" aria-hidden="true"></iconify-icon>
                        <span>Mode edit mengubah tanggal, kategori, deskripsi, jumlah, penerima, dan debet/kredit. Unit dan No. Akun tetap seperti saat transaksi dibuat.</span>
                    </p>

                    <div class="kk-modal-foot">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary" id="kkEditBatal">Batal</button>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </div>
                </form>

                </div>
            </div>
        </div>
    </div>

    <!-- ── Ledger ──────────────────────────────────────────────── -->
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
                <select class="form-select" id="kkUnitFilter" <?= $canFilterUnit ? '' : 'disabled' ?>>
                    <?php if ($showAllUnits): ?>
                        <option value="" <?= $defaultFilterUnit === 0 ? 'selected' : '' ?>>Semua Unit</option>
                    <?php endif; ?>
                    <?php foreach ($unitFilter as $u): ?>
                        <option value="<?= (int) $u->idunit ?>" <?= (int) $u->idunit === $defaultFilterUnit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="kk-filter">
                <span class="kk-field-label" id="kkKatModeLabel">Kategori</span>
                <div class="btn-group btn-group-sm" role="group" aria-labelledby="kkKatModeLabel">
                    <input type="radio" class="btn-check" name="kkKatMode" id="kkKatAll" value="" checked>
                    <label class="btn btn-outline-secondary" for="kkKatAll">Semua</label>
                    <input type="radio" class="btn-check" name="kkKatMode" id="kkKatEnam" value="enam">
                    <label class="btn btn-outline-secondary" for="kkKatEnam" title="Hanya kategori yang masuk Biaya Terkontrol 6%">6%</label>
                    <input type="radio" class="btn-check" name="kkKatMode" id="kkKatLuar" value="luar">
                    <label class="btn btn-outline-secondary" for="kkKatLuar">Di luar 6%</label>
                </div>
            </div>

            <div class="kk-cmdbar-actions">
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

        <div class="kk-sum">
            <div class="kk-sum-cell">
                <span>Total terfilter</span>
                <b class="kk-num" id="kkSumTotal">Rp 0</b>
            </div>
            <div class="kk-sum-cell is-enam">
                <span><i class="kk-dot" aria-hidden="true"></i>Masuk 6%</span>
                <b class="kk-num" id="kkSumEnam">Rp 0</b>
                <small id="kkSumEnamN">0 transaksi</small>
            </div>
            <div class="kk-sum-cell">
                <span>Di luar 6%</span>
                <b class="kk-num" id="kkSumLuar">Rp 0</b>
                <small id="kkSumLuarN">0 transaksi</small>
            </div>
        </div>

        <div class="kk-chips" id="kkChips"></div>

        <div class="kk-idhit" id="kkIdHit" hidden></div>

        <div class="kk-tablewrap" id="kkTableWrap">
            <table class="table kk-table" id="table_kas_keluar" style="width: 100%">
                <thead>
                    <tr>
                        <th scope="col" class="kk-c-tgl">Tanggal</th>
                        <th scope="col" class="kk-c-kat">Kategori</th>
                        <th scope="col" class="kk-c-ket">Keterangan</th>
                        <th scope="col" class="kk-c-akun">No. Akun</th>
                        <th scope="col" class="kk-c-jumlah">Jumlah</th>
                        <?php if ($canAct): ?>
                            <th scope="col" class="kk-c-aksi"><span class="visually-hidden">Aksi</span></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr>
                        <?php // Jumlah kolom ledger harus cocok dengan kaki tabel:
                        // label menutup Tanggal..No. Akun, nominal di bawah Jumlah. ?>
                        <td colspan="4" class="text-end" id="kkFootLabel">Total</td>
                        <td class="kk-foot-total" id="kkFootTotal">Rp 0</td>
                        <?php if ($canAct): ?>
                            <td></td>
                        <?php endif; ?>
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
            <div class="d-flex justify-content-center gap-2 mt-3">
                <button type="button" class="btn btn-primary mt-0" id="kkEmptyInput">Input Kas Keluar</button>
                <button type="button" class="btn btn-outline-secondary mt-0" id="kkEmptyReset">Bersihkan filter</button>
            </div>
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
        const CAN_FILTER_UNIT = <?= $canFilterUnit ? 'true' : 'false' ?>;
        const SHOW_ALL_UNITS = <?= $showAllUnits ? 'true' : 'false' ?>;
        const DEFAULT_FILTER_UNIT = <?= $defaultFilterUnit ?>;
        const LOGIN_UNIT = <?= $akunUnit ?>;

        // ── Option lists, dipakai untuk baris rincian akun ──────────
        const AKUN_OPTIONS = `<option value="">-- Pilih No. Akun --</option><?php foreach ($no_akun as $a): ?><option value="<?= esc($a->no_akun) ?>"><?= esc($a->no_akun) ?> &mdash; <?= esc($a->nama_akun) ?></option><?php endforeach; ?>`;
        const KATEGORI = <?= json_encode($kategoriOpsi, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        // Isian yang ditolak server, dikirim balik lewat flashdata. Null kalau
        // halaman dibuka normal.
        const OLD_INPUT = <?= json_encode($oldInput, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        // Penanda "6%" dan filter checkbox memakai daftar yang sama dengan
        // Config\Finance, jadi form input tidak bisa berbeda dari kartu
        // Biaya Terkontrol di /asset_berjalan.
        function kategoriOptions(hanya6) {
            let html = '<option value="">-- Pilih Kategori --</option>';
            KATEGORI.forEach(function (k) {
                if (hanya6 && !k.enam) return;
                html += '<option value="' + k.id + '">' + k.nama + (k.enam ? ' · 6%' : '') + '</option>';
            });
            return html;
        }

        // Select2 dipakai supaya hanya badge "6%" yang berwarna: <option>
        // native tidak bisa mewarnai sebagian teks.
        function formatKategori(k) {
            if (!k.id || k.loading) return k.text;
            const akhiran = ' · 6%';
            if (k.text.slice(-akhiran.length) !== akhiran) return k.text;
            const wrap = $('<span>').text(k.text.slice(0, -akhiran.length) + ' ');
            wrap.append($('<span>', { class: 'kk-kat-enam', text: '6%' }));
            return wrap;
        }

        function initKategoriSelect($sel) {
            $sel.select2({
                dropdownParent: byId('#kkSheet'),
                width: '100%',
                dropdownAutoWidth: true,
                templateResult: formatKategori,
                templateSelection: formatKategori
            });
        }
        const BANK_BY_UNIT = <?= json_encode(array_map(static function ($list) {
            return array_map(static function ($b) {
                return [
                    'idbank' => (string) $b->idbank,
                    'label'  => esc($b->nama_bank . ' · ' . $b->atas_nama . ' · ' . $b->norek),
                ];
            }, $list);
        }, $bank_per_unit), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        // Opsi rekening mengikuti unit yang sedang dipilih. Rekening unit lain
        // tidak ditawarkan karena pasti ditolak validator saat disimpan.
        function bankOptions(unitId, selected, placeholder) {
            const list = BANK_BY_UNIT[String(unitId)] || [];
            const sel = String(selected || '');
            let html = '<option value="">' + (placeholder || '-- Pilih No. Rekening --') + '</option>';
            list.forEach(function (b) {
                const val = String(b.idbank);
                html += '<option value="' + val + '"' + (val === sel ? ' selected' : '') + '>' + b.label + '</option>';
            });
            return html;
        }

        function unitTerpilih() {
            return byId('#kkUnit').value;
        }

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
        // Enam kolom: ID, Unit, dan Penerima tidak lagi berdiri sendiri —
        // ID+unit menempel pada tanggal, penerima+rekening pada keterangan.
        const COLS = [
            { key: 'tanggal', label: 'Tanggal', cls: 'kk-c-tgl', orderable: true },
            { key: 'kategori', label: 'Kategori', cls: 'kk-c-kat', orderable: true },
            { key: 'deskripsi', label: 'Keterangan', cls: 'kk-c-ket', orderable: true },
            { key: 'no_akun', label: 'No. Akun', cls: 'kk-c-akun', orderable: true },
            { key: 'jumlah', label: 'Jumlah', cls: 'kk-c-jumlah', orderable: true }
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
        // Role tanpa hak filter terkunci ke unitnya sendiri; SPV terkunci ke
        // unit binaan pertama (server juga memaksa ini).
        unit.disabled = !CAN_FILTER_UNIT;
        if (!SHOW_ALL_UNITS) unit.value = String(DEFAULT_FILTER_UNIT);
        const chips = document.getElementById('kkChips');
        const idHit = document.getElementById('kkIdHit');
        const empty = document.getElementById('kkEmpty');
        const tableWrap = document.getElementById('kkTableWrap');

        function isIdQuery() {
            const q = search.value.trim();
            return q !== '' && !forceTextSearch && /^\d+$/.test(q);
        }

        // Filter kategori untuk daftar: '', 'enam', atau 'luar'.
        function katMode() {
            const el = document.querySelector('input[name="kkKatMode"]:checked');
            return el ? el.value : '';
        }

        // ── Modal input/edit ───────────────────────────────────────
        const sheetEl = byId('#kkSheet');
        const sheetTitle = byId('#kkSheetTitleText');
        const sheetSub = byId('#kkSheetSub');
        const formInsert = document.getElementById('kkFormInsert');
        const formEdit = document.getElementById('kkFormEdit');
        const posBody = document.getElementById('kkPosisiBody');
        const draftTotal = document.getElementById('kkDraftTotal');
        const draftSplit = document.getElementById('kkDraftSplit');

        let posisiIndex = 0;
        let composerMode = 'insert';
        let sheetInstance = null;

        function openSheet() {
            if (!sheetInstance) sheetInstance = bootstrap.Modal.getOrCreateInstance(sheetEl);
            sheetInstance.show();
        }

        function closeSheet() {
            if (sheetInstance) sheetInstance.hide();
        }

        // Nilai select diisi lewat Select2 bila sudah aktif, supaya tampilan
        // dan nilai form tidak berbeda.
        function setVal(sel, value) {
            const $sel = $(sel);
            const v = value === undefined || value === null ? '' : String(value);
            if ($sel.data('select2')) $sel.val(v).trigger('change');
            else sel.value = v;
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

        function renumberTx() {
            posBody.querySelectorAll('.kk-tx').forEach(function (card, i) {
                const no = card.querySelector('.kk-tx-no');
                if (no) no.textContent = String(i + 1);
            });
        }

        // Satu kartu = satu record kas_keluar (bentuk yang disimpan backend).
        // `data` hanya dipakai saat mengembalikan isian yang gagal disimpan.
        function addPosisi(data) {
            const card = document.createElement('div');
            const i = posisiIndex++;
            card.className = 'kk-tx';
            card.innerHTML =
                '<div class="kk-tx-head">' +
                '<span>Transaksi <b class="kk-tx-no"></b></span>' +
                '<button type="button" class="kk-rowbtn is-danger kk-pos-hapus" title="Hapus transaksi" aria-label="Hapus transaksi">' +
                '<iconify-icon icon="solar:trash-bin-minimalistic-linear" width="16" height="16"></iconify-icon></button>' +
                '</div>' +
                '<div class="kk-tx-grid">' +
                '<div class="is-full"><label class="kk-field-label" for="kkPosNo' + i + '">No. Akun</label>' +
                '<select class="form-select kk-pos-no" id="kkPosNo' + i + '" name="akun[' + i + '][no_akun]" required>' + AKUN_OPTIONS + '</select></div>' +
                '<div class="is-full"><label class="kk-field-label" for="kkPosKat' + i + '">Kategori</label>' +
                '<select class="form-select kk-pos-kat" id="kkPosKat' + i + '" name="akun[' + i + '][kategori_idkategori]" required>' + kategoriOptions(byId('#kkHanya6').checked) + '</select></div>' +
                '<div><label class="kk-field-label" for="kkPosSumber' + i + '">Sumber dana</label>' +
                '<select class="form-select kk-pos-sumber" id="kkPosSumber' + i + '" name="akun[' + i + '][jenis_transaksi]">' +
                '<option value="cash" selected>Kas</option><option value="bank">Bank</option></select></div>' +
                '<div><label class="kk-field-label" for="kkPosRek' + i + '">No. Rekening</label>' +
                '<select class="form-select kk-pos-rek" id="kkPosRek' + i + '" name="akun[' + i + '][no_rekening]" disabled>' + bankOptions(unitTerpilih(), '') + '</select></div>' +
                '<div><label class="kk-field-label" for="kkPosPenerima' + i + '">Penerima</label>' +
                '<input type="text" class="form-control kk-pos-penerima" id="kkPosPenerima' + i + '" name="akun[' + i + '][penerima]" placeholder="Nama penerima" maxlength="255"></div>' +
                '<div><label class="kk-field-label" for="kkPosPosisi' + i + '">Debet/Kredit</label>' +
                '<select class="form-select kk-pos-posisi" id="kkPosPosisi' + i + '" name="akun[' + i + '][posisi_drk]">' +
                '<option value="debet" selected>Debet</option><option value="kredit">Kredit</option></select></div>' +
                '<div class="is-full"><label class="kk-field-label" for="kkPosJumlah' + i + '">Jumlah</label>' +
                '<input type="text" class="form-control text-end kk-num kk-pos-jumlah" id="kkPosJumlah' + i + '" name="akun[' + i + '][jumlah]" inputmode="numeric" placeholder="0" required></div>' +
                '</div>';

            posBody.appendChild(card);

            const sumber = card.querySelector('.kk-pos-sumber');
            const rek = card.querySelector('.kk-pos-rek');
            sumber.addEventListener('change', function () {
                const isBank = this.value === 'bank';
                rek.disabled = !isBank;
                if (!isBank) setVal(rek, '');
            });

            $(card).find('.kk-pos-no').select2({
                dropdownParent: sheetEl,
                width: '100%',
                dropdownAutoWidth: true,
                placeholder: 'Cari akun (mis. Piutang)',
                allowClear: true
            });
            $(card).find('.kk-pos-rek').select2({
                dropdownParent: sheetEl,
                width: '100%',
                dropdownAutoWidth: true,
                placeholder: 'Pilih rekening',
                allowClear: true
            });
            initKategoriSelect($(card).find('.kk-pos-kat'));

            rupiahInput(card.querySelector('.kk-pos-jumlah'));
            card.querySelector('.kk-pos-hapus').addEventListener('click', function () {
                card.remove();
                renumberTx();
                updateDraftTotal();
            });

            if (data) {
                setVal(card.querySelector('.kk-pos-no'), data.no_akun);
                setVal(card.querySelector('.kk-pos-kat'), data.kategori_idkategori);
                setVal(card.querySelector('.kk-pos-penerima'), data.penerima);
                setVal(card.querySelector('.kk-pos-posisi'), data.posisi_drk || 'debet');
                const jml = digitsOnly(data.jumlah);
                card.querySelector('.kk-pos-jumlah').value = jml ? Number(jml).toLocaleString('id-ID') : '';

                const sumberVal = data.jenis_transaksi === 'bank' ? 'bank' : 'cash';
                sumber.value = sumberVal;
                rek.disabled = sumberVal !== 'bank';
                if (sumberVal === 'bank') {
                    rek.innerHTML = bankOptions(unitTerpilih(), data.no_rekening);
                    $(rek).val(String(data.no_rekening || '')).trigger('change');
                }
            }

            renumberTx();
            updateDraftTotal();
        }

        function updateDraftTotal() {
            let total = 0;
            let enam = 0;
            let n = 0;
            posBody.querySelectorAll('.kk-tx').forEach(function (card) {
                const v = parseInt(digitsOnly(card.querySelector('.kk-pos-jumlah').value) || '0', 10);
                if (v <= 0) return;
                total += v;
                n++;
                const kat = card.querySelector('.kk-pos-kat').value;
                if (KATEGORI.some(function (k) {
                    return String(k.id) === kat && k.enam;
                })) {
                    enam += v;
                }
            });

            draftTotal.textContent = rupiah(total);
            draftSplit.textContent = '6% ' + rupiah(enam) + ' · Lain ' + rupiah(total - enam);
            sheetSub.textContent = n === 0
                ? 'Satu transaksi, satu baris rincian akun'
                : n + ' transaksi · total ' + rupiah(total);
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
            delete formInsert.dataset.terkirim;
            delete formEdit.dataset.terkirim;
            bukaKunciSubmit(formInsert);
            bukaKunciSubmit(formEdit);
            formInsert.reset();
            applyUnitDefault();
            formEdit.reset();
            if ($('#kkEditKategori').data('select2')) {
                $('#kkEditKategori').val('').trigger('change');
            }
            posBody.innerHTML = '';
            // Default ke hari ini, seperti alur input sebelumnya. Tanggal disusun
            // dari jam lokal supaya tidak pernah bergeser sehari karena zona waktu.
            const now = new Date();
            byId('#kkTanggal').value = now.getFullYear() + '-' +
                String(now.getMonth() + 1).padStart(2, '0') + '-' +
                String(now.getDate()).padStart(2, '0');
            sheetTitle.textContent = 'Input Kas Keluar';
            formInsert.hidden = false;
            formEdit.hidden = true;
            addPosisi();
            updateDraftTotal();
        }

        function openEdit(row) {
            composerMode = 'edit';
            sheetTitle.textContent = 'Edit Kas Keluar #' + row.id;
            sheetSub.textContent = tanggalPendek(row.tanggal) + ' · ' + (row.deskripsi || 'tanpa deskripsi');
            formInsert.hidden = true;
            formEdit.hidden = false;

            byId('#kkEditId').value = row.id;
            byId('#kkEditTanggal').value = row.tanggal;
            byId('#kkEditDeskripsi').value = row.deskripsi;
            byId('#kkEditJumlah').value = digitsOnly(String(Math.round(Number(row.jumlah) || 0)));
            byId('#kkEditUnit').value = row.idunit || '';
            byId('#kkEditPenerima').innerHTML = bankOptions(
                row.idunit,
                row.idbank,
                '— Tidak ada rekening —'
            );
            byId('#kkEditPenerima').value = row.idbank || '';
            byId('#kkEditPosisi').value = (row.jenis || 'debet').toLowerCase();
            openSheet();

            // Select2 baru dipasang saat form benar-benar dipakai, supaya
            // lebarnya tidak terkunci nol.
            if (!byId('#kkEditKategori').dataset.s2) {
                initKategoriSelect($('#kkEditKategori'));
                byId('#kkEditKategori').dataset.s2 = '1';
            }
            $('#kkEditKategori').val(row.kategori_id || '').trigger('change');
        }

        byId('#kkOpenSheet').addEventListener('click', function () {
            if (composerMode === 'edit') resetComposer();
            openSheet();
        });

        byId('#kkEmptyInput').addEventListener('click', function () {
            openSheet();
        });

        byId('#kkInsertBatal').addEventListener('click', function () {
            resetComposer();
            closeSheet();
        });

        byId('#kkAddPosisi').addEventListener('click', function () { addPosisi(); });

        // Filter kategori 6%: saat dicentang, opsi di setiap baris dibangun
        // ulang tanpa kategori lain. Nilai yang sudah dipilih tetap
        // dipertahankan bila masih termasuk daftar.
        byId('#kkHanya6').addEventListener('change', function () {
            const hanya6 = this.checked;
            posBody.querySelectorAll('.kk-pos-kat').forEach(function (sel) {
                const $sel = $(sel);
                const nilai = sel.value;
                if ($sel.data('select2')) $sel.select2('destroy');
                sel.innerHTML = kategoriOptions(hanya6);
                if (nilai && KATEGORI.some(function (k) {
                    return String(k.id) === nilai && (!hanya6 || k.enam);
                })) {
                    sel.value = nilai;
                }
                initKategoriSelect($sel);
            });
        });

        // Rekening yang ditawarkan harus ikut unit yang dipilih. Untuk role
        // yang boleh memilih unit, daftar di baris yang sudah ada dibangun ulang.
        byId('#kkUnit').addEventListener('change', function () {
            const unitId = this.value;
            posBody.querySelectorAll('.kk-pos-rek').forEach(function (sel) {
                sel.innerHTML = bankOptions(unitId, sel.value);
                $(sel).trigger('change');
            });
        });

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
            closeSheet();
        });
        byId('#kkTanggal').addEventListener('change', updateDraftTotal);

        // ── Tooltip deskripsi ──────────────────────────────────────
        // Isi penuhnya deskripsi yang terpotong. `title` bawaan browser
        // sebenarnya sudah ada, tapi muncul juga di deskripsi yang utuh,
        // datang terlambat (~1 detik), dan tampilannya tidak serasi.
        // Tooltip ini hanya muncul kalau teksnya benar-benar terpotong.
        const kkTable = byId('#table_kas_keluar');
        const kkTip = document.createElement('div');
        kkTip.className = 'kk-tip';
        kkTip.setAttribute('role', 'tooltip');
        kkTip.hidden = true;
        document.body.appendChild(kkTip);

        function kkDescTerpotong(span) {
            return span.scrollHeight > span.clientHeight + 1 ||
                span.scrollWidth > span.clientWidth + 1;
        }

        function kkTipTampil(span) {
            // Teks penuhnya tetap ada di DOM; yang dipangkas cuma tampilannya.
            const teks = (span.textContent || '').trim();
            if (!teks || teks === '—' || !kkDescTerpotong(span)) {
                kkTipSembunyi();
                return;
            }
            kkTip.textContent = teks;
            kkTip.hidden = false;
            const r = span.getBoundingClientRect();
            // Ditempatkan dulu di pojok supaya tinggi sebenarnya diketahui
            // sebelum memutuskan mau di atas atau di bawah sel.
            kkTip.style.left = '0px';
            kkTip.style.top = '0px';
            const t = kkTip.getBoundingClientRect();
            const gap = 8;
            let top = r.bottom + gap;
            if (top + t.height > window.innerHeight - gap) {
                top = r.top - t.height - gap;
            }
            let left = r.left;
            if (left + t.width > window.innerWidth - gap) {
                left = window.innerWidth - t.width - gap;
            }
            kkTip.style.top = Math.max(gap, top) + 'px';
            kkTip.style.left = Math.max(gap, left) + 'px';
            kkTip.classList.add('is-tampil');
        }

        function kkTipSembunyi() {
            kkTip.classList.remove('is-tampil');
            kkTip.hidden = true;
        }

        // Delegasi ke tabel, bukan listener per sel, supaya tetap hidup
        // setelah DataTables menggambar ulang isi tbody.
        kkTable.addEventListener('mouseover', function (e) {
            const s = e.target.closest('.kk-desc');
            if (s) kkTipTampil(s); else kkTipSembunyi();
        });
        kkTable.addEventListener('mouseleave', kkTipSembunyi);
        // Fokus agar teks penuhnya tetap bisa dibaca pakai keyboard.
        kkTable.addEventListener('focusin', function (e) {
            const s = e.target.closest('.kk-desc');
            if (s) kkTipTampil(s); else kkTipSembunyi();
        });
        kkTable.addEventListener('focusout', kkTipSembunyi);
        window.addEventListener('scroll', kkTipSembunyi, true);
        window.addEventListener('resize', kkTipSembunyi);

        // Hanya deskripsi terpotong yang jadi titik fokus, supaya tidak
        // menambah banyak tab stop di tabel yang panjang. Dicek ulang tiap
        // render karena lebar kolom mengikuti viewport.
        function kkSyncDescFokus() {
            kkTable.querySelectorAll('.kk-desc').forEach(function (s) {
                if (kkDescTerpotong(s)) {
                    s.setAttribute('tabindex', '0');
                } else {
                    s.removeAttribute('tabindex');
                }
            });
        }

        // Double-submit guard: satu klik = satu POST. Tanpa ini klik ganda
        // pada tombol simpan mengirim dua request dan membuat baris dobel.
        // shortcut: hanya di sisi klien; pasang idempotency key di server
        // kalau duplikat masih muncul dari retry jaringan.
        function kunciSubmit(form, label) {
            const btn = form.querySelector('button[type="submit"]');
            if (!btn) return;
            btn.dataset.label = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + label;
        }

        function bukaKunciSubmit(form) {
            const btn = form.querySelector('button[type="submit"]');
            if (!btn) return;
            btn.disabled = false;
            if (btn.dataset.label) btn.innerHTML = btn.dataset.label;
        }

        // Bersihkan format ribuan dan pastikan unit terkunci tetap terkirim.
        formInsert.addEventListener('submit', function (e) {
            if (formInsert.dataset.terkirim === '1') {
                e.preventDefault();
                return;
            }

            const u = byId('#kkUnit');
            if (u.disabled) u.disabled = false;

            // Satu transaksi harus punya minimal satu baris akun. Baris akun
            // bisa saja semuanya dihapus, dan tanpa penjaga ini form tetap terkirim.
            const rows = posBody.querySelectorAll('.kk-tx').length;
            if (rows === 0) {
                e.preventDefault();
                addPosisi();
                posBody.querySelector('.kk-pos-no').focus();
                return;
            }

            document.querySelectorAll('#kkPosisiBody .kk-pos-jumlah').forEach(function (input) {
                input.value = digitsOnly(input.value) || '0';
            });

            formInsert.dataset.terkirim = '1';
            kunciSubmit(formInsert, 'Menyimpan...');
        });
        formEdit.addEventListener('submit', function (e) {
            if (formEdit.dataset.terkirim === '1') {
                e.preventDefault();
                return;
            }
            byId('#kkEditJumlah').value = digitsOnly(byId('#kkEditJumlah').value) || '0';
            formEdit.dataset.terkirim = '1';
            kunciSubmit(formEdit, 'Menyimpan...');
        });

        resetComposer();

        // Isian yang ditolak server dibuka lagi lengkap, bukan halaman kosong.
        if (OLD_INPUT && OLD_INPUT.akun && OLD_INPUT.akun.length) {
            byId('#kkTanggal').value = OLD_INPUT.tanggal || byId('#kkTanggal').value;
            byId('#kkDeskripsi').value = OLD_INPUT.deskripsi || '';
            if (OLD_INPUT.unit_idunit) {
                const u = byId('#kkUnit');
                u.value = String(OLD_INPUT.unit_idunit);
                $(u).trigger('change');
            }
            posBody.innerHTML = '';
            posisiIndex = 0;
            OLD_INPUT.akun.forEach(function (a) {
                addPosisi(a);
            });
            updateDraftTotal();
            openSheet();
        }

        // ── Kolom tabel: pembentuk sel ────────────────────────────
        function cellTanggal(row) {
            const frag = document.createDocumentFragment();
            frag.appendChild(el('span', 'kk-line1 kk-num', tanggalPendek(row.tanggal)));
            // Baris antar unit tidak punya idkas_keluar; kode transfernya yang
            // jadi rujukan supaya tidak tertukar dengan id kas keluar lain.
            const kode = row.readonly ? (row.ref || 'antar-unit') : ('#' + row.id);
            frag.appendChild(el('span', 'kk-line2 kk-mono',
                kode + (SHOW_ALL_UNITS && row.unit ? ' · ' + row.unit : '')));
            return frag;
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
            if (row.readonly) {
                const tag = el('span', 'kk-tag', 'Antar Unit');
                tag.title = 'Pelunasan hutang antar unit — dicatat di Kas & Bank, tidak bisa diedit dari sini';
                return tag;
            }
            if (!row.kategori) return el('span', 'kk-line2', '—');
            const wrap = el('span', 'kk-kat-cell');
            const tag = el('span', 'kk-tag', row.kategori);
            tag.title = row.kategori;
            wrap.appendChild(tag);
            if (row.enam) {
                const badge = el('span', 'kk-tag kk-tag-enam', '6%');
                badge.title = 'Masuk hitungan Biaya Terkontrol (maks 6% omset)';
                wrap.appendChild(badge);
            }
            return wrap;
        }

        function cellKeterangan(row) {
            const frag = document.createDocumentFragment();
            // Tanpa `title`: tooltip kk-tip menggantikannya, dan `title` akan
            // tampil dua kali kalau keduanya menyala.
            frag.appendChild(el('span', 'kk-desc', row.deskripsi || '—'));
            const sub = [];
            if (row.penerima && row.penerima !== '—') sub.push(row.penerima);
            if (row.nama_bank) sub.push(row.nama_bank + ' · ' + row.norek);
            if (sub.length) {
                frag.appendChild(el('span', 'kk-line2 kk-clip', sub.join(' · ')));
            }
            return frag;
        }

        function cellAkun(row) {
            return cellTwo(row.no_akun || '—', row.nama_akun, true);
        }

        function cellJumlah(row) {
            const frag = document.createDocumentFragment();
            frag.appendChild(el('span', 'kk-amount', rupiah(row.jumlah)));
            const v = String(row.jenis || '').toLowerCase();
            if (v === 'debet' || v === 'kredit') {
                const tag = el('span', 'kk-tag mt-1 ' + (v === 'debet' ? 'kk-pos-debet' : 'kk-pos-kredit'),
                    v === 'debet' ? 'Debet' : 'Kredit');
                frag.appendChild(tag);
            }
            return frag;
        }

        function cellAksi(row) {
            const wrap = el('div', 'd-flex justify-content-end gap-1');

            // Baris antar unit berasal dari ledger Kas & Bank. Edit/hapus di
            // sini akan menyasar idkas_keluar yang berbeda, jadi hanya diberi
            // penanda dan tidak ada tombol.
            if (row.readonly) {
                const lock = el('span', 'kk-rowbtn', '');
                lock.title = 'Read-only: pelunasan antar unit dikelola di Kas & Bank';
                lock.setAttribute('aria-label', lock.title);
                lock.innerHTML = '<iconify-icon icon="solar:lock-keyhole-minimalistic-linear" width="16" height="16"></iconify-icon>';
                wrap.appendChild(lock);
                return wrap;
            }

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
            tanggal: cellTanggal,
            kategori: cellKategori,
            deskripsi: cellKeterangan,
            no_akun: cellAkun,
            jumlah: cellJumlah,
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
                    d.kategori_mode = katMode();
                }
            },
            order: [[0, 'desc']],
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

                const isFiltered = (json.recordsFiltered || 0) !== (json.recordsTotal || 0);
                byId('#kkFootLabel').textContent = isFiltered
                    ? 'Total hasil filter'
                    : 'Total';

                renderSummary(json);

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
            // Unit sendiri / unit binaan pertama bukan "filter aktif"; hanya
            // pilihan yang berbeda dari default yang perlu chip.
            if (unit.value && CAN_FILTER_UNIT && unit.value !== String(DEFAULT_FILTER_UNIT)) {
                const opt = unit.options[unit.selectedIndex].text;
                chips.appendChild(chip('Unit', opt, function () {
                    unit.value = SHOW_ALL_UNITS ? '' : String(DEFAULT_FILTER_UNIT);
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

        // Ringkasan 6%: angka Total mengikuti filter kategori yang aktif (sama
        // dengan kaki tabel), sedangkan Masuk 6% / Di luar 6% tetap mengukur
        // cakupan filter tanggal/unit/pencarian — supaya pembandingnya tidak
        // ikut jadi nol saat salah satu sisi difilter.
        function renderSummary(json) {
            const enam = Number(json.sumEnam) || 0;
            const luar = Number(json.sumLuar) || 0;
            byId('#kkSumTotal').textContent = rupiah(Number(json.sumTotal) || 0);
            byId('#kkSumEnam').textContent = rupiah(enam);
            byId('#kkSumLuar').textContent = rupiah(luar);
            byId('#kkSumEnamN').textContent = (Number(json.countEnam) || 0) + ' transaksi';
            byId('#kkSumLuarN').textContent = (Number(json.countLuar) || 0) + ' transaksi';
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
                    unit.value = SHOW_ALL_UNITS ? '' : String(DEFAULT_FILTER_UNIT);
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
        document.querySelectorAll('input[name="kkKatMode"]').forEach(function (radio) {
            radio.addEventListener('change', function () { dt.page(0).draw(false); });
        });

        function resetAll() {
            search.value = '';
            searchWrap.dataset.filled = 'false';
            start.value = '';
            end.value = '';
            unit.value = SHOW_ALL_UNITS ? '' : String(DEFAULT_FILTER_UNIT);
            byId('#kkKatAll').checked = true;
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

        // Titik fokus deskripsi mengikuti isi sel terbaru. Dicek ulang tiap
        // render dan tiap perubahan lebar viewport, karena lebar kolom ikut
        // bergeser sehingga sel yang tadinya muat bisa jadi terpotong.
        dt.on('draw.dt', kkSyncDescFokus);
        window.addEventListener('resize', kkSyncDescFokus);
    });
</script>
