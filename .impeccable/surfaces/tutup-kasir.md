---
version: 1
slug: "tutup-kasir"
primary_target: "tutup_kasir"
related_targets: []
---

# Surface Brief — Tutup Kasir

## Scope & mode

- Scope: `/tutup_kasir` (`TutupKasir::index`; `app/Views/jurnal/tutup_kasir.php` and its scoped theme partial).
- Visitor mode: Operate — kasir/supervisor menghitung uang laci dan menutup kas harian; Finance memakai hasilnya untuk rekonsiliasi.
- Job: memahami ringkasan kas di desktop, atau langsung memasukkan hasil hitung fisik di mobile, tanpa melihat saldo akhir atau status kecocokan.
- Constraints: pertahankan formula dan sumber angka server, routes, field form, guard saldo awal, batas waktu, pencegahan closing ganda, cetak laporan, tema Bootstrap/iClear light/dark, dan copy Bahasa Indonesia. Jangan tampilkan angka saldo akhir sistem, nominal/arah selisih, atau status cocok/ada perbedaan; halaman hanya meminta hasil hitung uang fisik. DP tunai/transfer sudah termasuk di pendapatan metode masing-masing.

## Direction contract

THESIS: Closing dibaca sebagai lembar ringkasan per bagian—saldo awal, arus kas, lalu hasil—agar kasir memahami ke mana uang bergerak; menolak kartu pendapatan berulang yang menyajikan DP seolah pendapatan tambahan.

OWN-WORLD: Pertahankan Bootstrap 5 Modernize dan token tema yang sudah dipakai. Gunakan hirarki angka tabular yang ringkas, warna semantik kas/transfer/pengeluaran, panel berbingkai, Iconify Solar, serta status dan focus state yang jelas; tidak menambah palette, font, atau library.

STORY: Kasir mengenali unit dan tanggal, membaca saldo awal KAS serta Transfer secara terpisah, menelusuri penerimaan dan pengeluaran per metode (dengan DP sebagai rincian yang sudah termasuk), menghitung uang laci sendiri, lalu memasukkan hasil hitung tanpa disodori saldo akhir atau status kecocokan.

FIRST VIEWPORT: Header unit/tanggal diikuti ringkasan saldo awal per metode. Bagian Arus Kas Hari Ini menjadi fokus utama dan menyusun pendapatan serta pengeluaran dalam kelompok KAS dan Transfer; baris DP yang tersedia tampil sebagai catatan inklusi di bawah pendapatan terkait, bukan kartu/tambahan total. Perpindahan kas-bank tampil satu kali sebagai mutasi neto KAS, dengan ringkasan setor/tarik sebagai catatan di baris yang sama. Bagian hitung fisik hanya meminta jumlah hasil penghitungan uang laci—tanpa saldo akhir, nominal/arah selisih, atau status kecocokan. Penjelasan metode hitung dapat dibuka terpisah; mutasi internal tidak dijumlahkan sebagai pendapatan/pengeluaran.

FORM: Struktur yang dipilih pengguna — Ringkasan per bagian, peringkat 4 dari 7, seed key ef88a454. Urutan: saldo awal → arus kas hari ini → input hasil hitung uang fisik; DP diagregasi sebagai catatan inklusi dan daftar transaksi DP individual tidak ditampilkan.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
