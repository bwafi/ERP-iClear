# Redesign Halaman Riwayat Pembelian

**Tanggal:** 2026-11-20
**Status:** menunggu persetujuan
**Modul:** Pembelian & Persediaan (PRD Modul 03) — halaman riwayat
**Surface brief:** belum ada; acuan visual `.sk-*` (`riwayat/sparepart_keluar`) dan `.rp-*` (`riwayat/presensi`)

## 1. Intent

`/riwayat_pembelian` masih memakai gaya Bootstrap generik: dua kartu bersarang
(header + panel), label filter tebal 5 kolom, tabel ber-border dengan header
tebal, dan modal detail berisi empat kotak statistik. Halaman riwayat lain di
repo ini sudah pindah ke bahasa visual yang lebih tenang: kepala tipis dengan
chip konteks, satu panel, filter ringkas, tabel hairline, pagination kustom,
modal bergaya sama.

Tujuan redesign: **menyelaraskan tampilan dan rasa halaman ini dengan riwayat
lain**, tanpa mengubah data, filter, alur, atau export yang sudah benar
(server-side + scope unit dari issue #228).

## 2. Goals

1. Satu bahasa visual dengan `sparepart_keluar` dan `presensi`: kepala tipis,
   chip meta, panel tunggal, filter satu baris, tabel hairline, pagination
   kustom, modal detail senada.
2. Informasi nota lebih cepat dipindai: no. nota sebagai identitas monospace,
   tanggal dengan hari, sumber sebagai teks + nama supplier sebagai sub-baris,
   kolom uang tabular rata kanan.
3. Sisa hutang terlihat langsung di baris (data `sisa` sudah dikirim `ajax`),
   tanpa membuka modal.
4. Tetap enak dipakai di 1280px ke bawah: filter melipat, tabel bisa digeser
   horizontal, modal detail menumpuk.
5. Mode gelap dan terang sama-sama benar (token, bukan warna hardcode per
   komponen), kontras teks kecil ≥ 4.5:1.

## 3. Non-goals

- Tidak mengubah route, controller, model, query, atau schema.
- Tidak mengubah filter (tanggal awal/akhir, unit, cari, jumlah baris) maupun
  semantik export XLSX.
- Tidak menambah ringkasan agregat (total periode, jumlah nota per supplier).
- Tidak menambah kolom baru ke payload `ajax`; yang dipakai hanya field yang
  sudah ada (`sisa`, `status`, `jumlah_item`, `nama_suplier`).
- Tidak menyentuh halaman `/pembelian` (form input) atau `_pembelian_theme.php`.

## 4. Constraints

- Kontrak test `tests/RiwayatPembelianPageTest.php` harus tetap lulus tanpa
  diubah: `id="pembelianTable"`, `id="unitFilter"`, `id="detailModal"`,
  `id="detailBatch"`, `id="detailItems"`, `id="detailLoading"`,
  `id="detailError"`, class `js-detail`, string `<option value="">Semua Unit</option>`,
  `<option value="2">Unit Dua</option>`, dan teks
  `Akun Anda hanya dapat melihat unit ini.`
- Nama variabel JS `startDateInput`, `endDateInput`, `unitFilter`, `searchBox`,
  `pageLength`, `exportForm` dipertahankan (diuji di test yang sama).
- DataTables tetap server-side, `searching: false`, `lengthChange: false`,
  urutan default kolom 1 `desc`.
- Tanpa dependency baru. Ikon tetap Iconify `solar:*`. Font tetap sistem yang
  dipakai template.
- Satu aksen saja; status sebagai teks, bukan badge warna-warni.
- Tidak ada `alert()`; pesan error tetap inline di modal.

## 5. Approaches

1. **Tempel ulang kelas `.sk-*` di view ini.** Cepat, tapi tema `sk` ditulis
   untuk halaman sparepart (kolom HPP/Sub Total, tag klaim penjualan). Memakai
   namanya di sini mencampur kosakata dan membuat perubahan `sk` ikut
   menggeser halaman ini. Ditolak.
2. **Tema halaman sendiri `_riwayat_pembelian_theme.php` dengan prefix `.rb-*`,
   mengikuti pola tema yang sudah ada.** Semua selector di bawah `.rb-page` /
   `.rb-modal`, token turunan variabel tema aplikasi, satu aksen biru-riwayat
   (`#0b5f8a` terang, `#7cc4f0` gelap) yang sama dengan `.rp-*` supaya keluarga
   halaman riwayat konsisten. **Dipilih.**
3. **Restyle langsung di view tanpa file tema.** Menghemat satu file, tetapi
   aturan tabel/modal jadi 400+ baris inline, sulit dijaga kontras gelapnya, dan
   tidak mengikuti pola repo. Ditolak.

## 6. Keputusan desain

### Struktur halaman

```
.rb-hero        breadcrumb (Riwayat / Pembelian) · h1 · deskripsi
                chip: jumlah nota (live) · unit scope · rentang tanggal (live)
.rb-toolbar     cari (melebar) · tanggal awal–akhir (satu grup) · unit · baris
                aksi: Reset (ghost) · Export (ghost, ikon unduh)
              hint scope unit terkunci: "Akun Anda hanya dapat melihat unit ini."
.rb-card        kepala: "Nota pembelian" + hint "Klik Detail untuk rincian barang."
                tabel 7 kolom · footer pagination kustom
.rb-modal       detail nota (statis, di view, id dipertahankan)
```

- Judul kolom: No. Nota · Tanggal · Barang · Unit · Sumber · Total · Aksi.
  "No. Batch" tetap muncul sebagai label di modal/`no_batch` karena itu istilah
  yang dipakai controller dan export; header tabel memakai "No. Nota" agar
  terbaca operator, dengan `title`/`aria-label` "Nomor nota / batch".
- Barang: nama barang pertama + `+N barang lain` sebagai sub-baris (bukan badge
  abu-abu). Tanpa rincian → teks muted "Tanpa rincian barang".
- Sumber: "Suplier" / "Pelanggan" sebagai teks kuat; `nama_suplier` sebagai
  sub-baris muted.
- Total: angka tabular rata kanan; jika `sisa > 0`, sub-baris "Sisa Rp …" warna
  peringatan.
- Aksi: tombol ghost berikon, satu-satunya aksen solid di baris adalah fokus.

### Modal detail

- Kepala: ikon + "Detail Pembelian" + nomor nota monospace, tombol tutup.
- Strip meta 4 sel (Tanggal · Unit · Sumber · Status) dengan garis pemisah,
  bukan empat kotak bersarang.
- Tabel item: Barang (nama + kode · IMEI + satuan), Jumlah, Harga Beli, Diskon
  (+ catatan PPN bila > 0), Sub Total.
- Blok total rata kanan: Sub Total · Sisa Hutang · Total Nota (paling besar).
- Blok total menempel di kaki area gulir (`position: sticky; bottom: 0`, latar
  `--rb-surface`, garis atas hairline). Saat nota punya banyak barang, hanya
  daftar barang yang bergulir; Sub Total, Sisa Hutang, dan Total Nota tetap
  terbaca tanpa scroll ke bawah.
- Loading, error, dan kondisi kosong tetap punya state sendiri; error memakai
  kotak inline `.rb-alert`.

### Perilaku & state

- Loading tabel memakai indikator teks + spinner kecil di area tabel, bukan
  menutupi seluruh panel.
- Empty state DataTables: ikon + "Tidak ada nota pada filter ini" + saran
  melebarkan tanggal.
- Chip jumlah nota dan rentang tanggal ikut berubah saat filter berubah
  (`drawCallback` membaca `info` dan nilai input).
- Reset mengosongkan tanggal + cari (unit tetap seperti sekarang) dan
  mengembalikan chip ke "Semua tanggal".

### Responsif & aksesibilitas

- ≥1200px: toolbar satu baris. 992–1199px: cari + tanggal satu baris, unit dan
  aksi turun. <768px: semua bertumpuk, tombol aksi selebar kontainer.
- Tabel: `overflow-x: auto`, header sticky, kolom uang `text-align: right`,
  `caption` visually-hidden, `scope="col"` di semua header.
- Fokus keyboard terlihat (ring 2px aksen) pada input, select, tombol, dan
  tombol Detail; modal memakai `aria-labelledby` yang sudah ada.

## 7. Yang diserahkan ke builder

- Skala tipografi dan padding tepat mengikuti `.rp-*`/`.sk-*` (judul 1.5rem,
  label .65rem uppercase, sel .8rem, header tabel `--rb-subtle`).
- Markup pagination kustom (Prev/Next + angka) dengan class `.rb-page-btn`.
- Susunan chip di layar sempit (boleh turun ke baris kedua, tetap urut).
- Penamaan class internal `.rb-*` boleh disesuaikan selama tetap di bawah
  `.rb-page` / `.rb-modal`.

## 8. Verifikasi

- `php -l` pada file yang diubah.
- `vendor/bin/phpunit -c phpunit.xml tests/RiwayatPembelianPageTest.php` dan
  `tests/RiwayatPembelianServerSideTest.php`.
- Detektor Impeccable pada target view + tema.
- Pemeriksaan manual: terang/gelap, 1440/1024/390px, buka-tutup modal, filter
  tanggal + unit, reset, export.

## 9. Risiko

- `no_batch` tidak unik (tercatat di spec #228); tabel tetap satu baris per
  `idpembelian`, jadi tidak ada perubahan identitas baris.
- Menambah baris "Sisa Rp …" memakai `sisa` apa adanya dari DB; jika data
  legacy punya `sisa` tidak konsisten, tampilan ikut menampilkannya. Tidak ada
  penulisan ulang data.
