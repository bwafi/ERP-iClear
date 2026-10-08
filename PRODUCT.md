# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

PHP (CodeIgniter 4), Bootstrap 5, Iconify Solar Icons, Vanilla JS

## Users

Kasir dan Supervisor Store yang bertugas menutup transaksi dan kas harian toko di akhir jam operasional toko, serta tim Keuangan yang memantau rekapitulasi kas.

## Product Purpose

Sistem ERP operasional toko/cabang untuk pencatatan transaksi, kas & bank, stok, dan rekonsiliasi buku harian. Halaman Tutup Kasir memastikan pencocokan saldo kas sistem dengan uang fisik di laci kasir secara akurat dan transparan.

## Positioning

ERP operasional kasir dengan validasi ketat saldo awal, rekonsiliasi server-side otomatis tanpa manipulasi client, dan pencatatan audit selisih kas fisik.

## Operating Context

Dipakai di kasir/admin cabang saat closing toko (window operasional 20:45 - 23:00 WIB). Kasir menghitung uang fisik dalam laci lalu menginputkannya ke sistem untuk melihat status selisih kas sebelum mengunci buku kasir.

## Capabilities and Constraints

- Server-side calculation untuk seluruh angka saldo sistem (`TutupKasirClosing::hitung`), browser hanya mengirim `cash_laci`.
- Guard validasi saldo awal kas & transfer sebelum proses closing diizinkan.
- Proteksi one-time closing harian per unit toko.
- Live calculation selisih kas fisik vs sistem di browser.
- Window penutupan berbasis WIB (Asia/Jakarta) dengan status penanda waktu normal vs terlambat.
- Tombol cetak struk/laporan bukti tutup kasir.

## Product Principles

- Kejelasan visual operasional (Operate Mode): Arsitektur informasi hierarkis dan mudah dipindai cepat oleh kasir lelah di akhir shift.
- Kontras status dan penonjolan aksi utama: Input uang fisik dan angka selisih harus langsung menjadi pusat perhatian.
- Feedback status sistem yang eksplisit: Error/peringatan saldo awal dan jam operasional tampil proporsional tanpa merusak alur pandang.
