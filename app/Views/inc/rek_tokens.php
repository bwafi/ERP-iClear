<?php
/**
 * Token & primitif visual bersama modul Rekonsiliasi Harian.
 *
 * Dipakai oleh finance_rekonsiliasi.php dan partial inputnya. Semula juga
 * dipakai halaman form harian (dashboard/finance_rekon_form), yang dihapus
 * 2026-09-27. Tokennya tetap di partial supaya halaman mana pun yang memakai
 * blok ini membaca sebagai satu produk, bukan menyalin palet ke tiap view
 * yang bisa diam-diam melenceng.
 *
 * Aturan: hanya token dan primitif yang dipakai kedua halaman. Komponen
 * khas satu halaman tetap tinggal di view-nya.
 */
?>
<style>
    .rk-scope {
        --rk-ink: var(--bs-emphasis-color);
        --rk-ink-2: color-mix(in srgb, var(--bs-emphasis-color) 74%, var(--bs-body-bg));
        --rk-ink-3: color-mix(in srgb, var(--bs-emphasis-color) 56%, var(--bs-body-bg));
        --rk-line: var(--bs-border-color);
        --rk-raise: var(--bs-tertiary-bg);
        /* Blue_Theme menyelesaikan --bs-danger/--bs-success jadi tint pucat
           (kira-kira #fb977d / #4bd08b). Warna itu aman untuk BADGE, tapi
           hanya 2,3:1 dan 2,1:1 kalau dipakai sebagai TEKS di atas kartu
           putih. Jadi teks semantik dicampur ke arah tinta tema: gelap di
           mode terang, terang di mode gelap. Badge bawaan tetap dipakai
           apa adanya supaya kosakata status tidak berubah. */
        --rk-bad-ink: color-mix(in srgb, var(--bs-danger) 58%, var(--bs-emphasis-color));
        --rk-good-ink: color-mix(in srgb, var(--bs-success) 58%, var(--bs-emphasis-color));
        /* --bs-warning (#f8c076) luminansinya 0,59 — jauh lebih terang dari
           danger/success, jadi campurnya harus lebih jauh ke arah tinta
           (50%, bukan 58%) supaya chip "perlu perhatian" tetap 4,5:1 di
           atas latarnya yang nyaris putih. */
        --rk-warn-ink: color-mix(in srgb, var(--bs-warning) 50%, var(--bs-emphasis-color));
        /* Latar chip: warna semantis hanya sebagai jejak, mayoritas
           digabung ke latar halaman supaya tint pucat Blue_Theme tidak
           pernah jadi dasar teks. */
        --rk-tint-ok: color-mix(in srgb, var(--bs-success) 16%, var(--bs-body-bg));
        --rk-tint-bad: color-mix(in srgb, var(--bs-danger) 16%, var(--bs-body-bg));
        --rk-tint-warn: color-mix(in srgb, var(--bs-warning) 18%, var(--bs-body-bg));
        --rk-tint-info: color-mix(in srgb, var(--bs-info) 15%, var(--bs-body-bg));
    }

    .rk-scope .rk-num {
        font-variant-numeric: tabular-nums;
        font-feature-settings: "tnum" 1;
    }

    /* Masthead */
    .rk-masthead h1 {
        font-size: 1.5rem;
        font-weight: 600;
        line-height: 1.2;
        margin: 0;
        color: var(--rk-ink);
    }

    .rk-masthead .breadcrumb {
        font-size: .8125rem;
        margin: 0;
        padding: 0;
        background: none;
    }

    /* Tautan breadcrumb bawaan inline setinggi 16px, di bawah minimum
       target 24px AA. Padding ini membawanya ke 32px tanpa mengubah
       tampilan teksnya. */
    .rk-masthead .breadcrumb-item a {
        display: inline-block;
        padding: .5rem 0;
    }

    /* Dua hal yang membuat breadcrumb tidak sejajar, diperbaiki berpasangan.

       1) Item AKTIF tidak punya <a>, jadi kotaknya tidak ikut mendapat
          padding tautan di atas dan teksnya duduk 8px lebih tinggi dari
          label sebelahnya. Padding vertikalnya disamakan. Sengaja memakai
          padding-block, bukan padding: Bootstrap memberi padding-left pada
          .breadcrumb-item + .breadcrumb-item untuk jarak dari divider, dan
          shorthand padding akan menghapusnya.
       2) Divider bawaan Bootstrap (" / ") di-float kiri. Float tidak
          berlaku di dalam flex, jadi saat li dibuat flex, divider ikut
          di-center bersama teksnya sebagai satu baris — bukan melayang
          sendiri di atas. */
    .rk-masthead .breadcrumb-item {
        display: inline-flex;
        align-items: center;
    }

    .rk-masthead .breadcrumb-item.active {
        padding-block: .5rem;
    }

    .rk-scope-readout {
        display: flex;
        flex-wrap: wrap;
        gap: .25rem 1.25rem;
        font-size: .8125rem;
        color: var(--rk-ink-2);
    }

    .rk-scope-readout b {
        color: var(--rk-ink);
        font-weight: 600;
    }

    /* Deck = kartu isi. Jaraknya milik isi deck, bukan card-body bawaan
       Modernize, jadi padding di atasnya dibuang. */
    .rk-deck .card-body {
        padding: 0;
    }

    /* Pita status */
    /* Pemberitahuan dalam deck. Bukan .alert Bootstrap: blok itu
       menyediakan warna latar yang tidak punya rasio teks di atasnya
       sendiri. Yang dipakai di sini baris hailin + ikon, tanpa kartu. */
    .rk-scope .rk-note-line {
        display: flex;
        align-items: flex-start;
        gap: .5rem;
        padding: .75rem 1.25rem;
        font-size: .8125rem;
        line-height: 1.5;
        color: var(--rk-ink-2);
        background: var(--rk-tint-warn);
        border-bottom: 1px solid var(--rk-line);
    }

    .rk-scope .rk-note-line.is-ok {
        background: var(--rk-tint-ok);
    }

    .rk-scope .rk-note-line i {
        flex: 0 0 auto;
        margin-top: .0625rem;
        font-size: 1rem;
        line-height: 1.35;
        color: var(--rk-warn-ink);
    }

    .rk-scope .rk-note-line.is-ok i {
        color: var(--rk-good-ink);
    }

    .rk-scope .rk-note-line b {
        color: var(--rk-ink);
        font-weight: 600;
    }

    /* Chip status. Memakai kata dari RekonDailyCalculator apa adanya,
       tetapi warnanya digambar sendiri: kelas bg-* bawaan Blue_Theme
       hanya 2,1-2,4:1 sebagai teks di atas kartu putih. */
    .rk-scope .rk-chip {
        display: inline-block;
        padding: .1875rem .5rem;
        font-size: .75rem;
        font-weight: 600;
        line-height: 1.35;
        border-radius: .375rem;
        background: var(--rk-raise);
        color: var(--rk-ink-2);
    }

    .rk-scope .rk-chip.is-ok {
        background: var(--rk-tint-ok);
        color: var(--rk-good-ink);
    }

    .rk-scope .rk-chip.is-bad {
        background: var(--rk-tint-bad);
        color: var(--rk-bad-ink);
    }

    .rk-scope .rk-chip.is-warn {
        background: var(--rk-tint-warn);
        color: var(--rk-warn-ink);
    }

    .rk-scope .rk-chip.is-info {
        background: var(--rk-tint-info);
        color: var(--rk-ink);
    }
</style>
