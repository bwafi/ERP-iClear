# Audit `/asset_berjalan` — 22 Oktober 2026

**Scope:** route → `TutupKasir::assetberjalan` → `app/Views/dashboard/asset_berjalan.php`, plus service payroll/KPI yang dipakai.
**Cara cek:** baca controller/view/service, cocokkan dengan data `erp_local`, jalankan `AssetBerjalanCardTest` (9 test, lulus).
**Asumsi beban:** puluhan staf internal, 3–6 karyawan per unit, halaman dibuka beberapa kali sehari. Angka uang harus sama dengan payroll dan halaman selesai dalam ±1 detik.

## What this repo does

ERP PHP (CodeIgniter 4) untuk bisnis jasa/service: penjualan, kas, KPI, dan payroll. Halaman `/asset_berjalan` adalah laporan bulanan per cabang: 6 angka uang (omset, pengeluaran, total gaji, tanggungan asset, hak cabang, tagihan center) plus panel pembagian gaji Head Office.

---

## Must fix

### 1. Filter bulan hanya mengubah Omset
`app/Controllers/TutupKasir.php:949-1263`, `:1674-1683`

- **Apa ini:** Halaman punya pilihan bulan/tahun. Omset memakai bulan pilihan.
- **Masalah:** Pengeluaran dan seluruh input gaji masih memakai `date('m')`/`date('Y')`, yaitu bulan hari ini. Buka `/asset_berjalan?unit=3&bulan=9&tahun=2026`: pengeluaran September sebenarnya Rp 3.496.682, yang tampil Rp 2.207.580 (Oktober). Laba jadi lebih besar Rp 1.289.102 → Hak Cabang lebih Rp 773.461 dan Tagihan Center lebih Rp 515.641. Gaji September juga masih memakai KPI Oktober.
- **Perbaikan:** Ganti semua `date('m')`/`date('Y')` di blok itu dan di query pengeluaran dengan `$bulan`/`$tahun`, lalu tambah satu test untuk bulan lampau.
- **Kalau dibiarkan:** Laporan review bulanan untuk bulan selain bulan ini salah, dan pembagian hasil cabang ikut salah.

### 2. Total Gaji memakai mesin gaji kedua, tidak sama dengan payroll
`app/Controllers/TutupKasir.php:802-1672` vs `app/Services/Payroll/PayrollGenerator.php:235-279`

- **Apa ini:** Payroll dan `/gaji` memakai `KpiCalculationService::calculateForSalary()` + `SalaryCalculationService`. Halaman ini menghitung ulang sendiri (±870 baris) dengan rumus lama dan tabel `penilaian`.
- **Masalah:** Untuk Oktober 2026 unit 5, halaman menampilkan Total Gaji Rp 5.132.083; payroll Rp 4.619.800 (belum lembur). Selisih Rp 512.283 membuat Hak Cabang 60% salah. Penyebabnya beda sumber skor: `penilaian` vs `kpi_evaluations`.
- **Perbaikan:** Panggil pasangan service yang sama seperti `PayrollGenerator::hitungGaji()`, lalu jumlahkan `total_gaji`. Ini juga menghapus rumus hardcode 1,5 juta/tunjangan.
- **Kalau dibiarkan:** Dua halaman menampilkan gaji berbeda untuk orang dan bulan yang sama; pembagian laba memakai angka yang tidak pernah dibayar.

### 3. Lembur tidak masuk ke biaya mana pun
`app/Controllers/TutupKasir.php:1674-1683`

- **Apa ini:** Pengeluaran hanya mengambil kategori kas 1,2,3,4,5,11,18. Lembur ada di kategori 10 (SDM).
- **Masalah:** Total Gaji halaman juga tidak menambah lembur. Oktober 2026 ada Rp 140.000 lembur per unit; September unit 4 Rp 350.000. Laba jadi terlalu besar, dan untuk unit 4 Tanggungan 20% + Hak Cabang 60% ikut terlalu besar (Rp 280.000 untuk kasus September).
- **Perbaikan:** Tambahkan lembur per pegawai ke Total Gaji memakai query yang sama dengan `PayrollGenerator::daftarLembur()`, atau masukkan baris lembur ke Pengeluaran — pilih satu, jangan dua-duanya.
- **Kalau dibiarkan:** Laba dan pembagian hasil selalu lebih besar dari biaya nyata.

### 4. KPI CS: Follow Up dan Upselling selalu 0
`app/Controllers/TutupKasir.php:1092-1110`

- **Apa ini:** Untuk jabatan 42, dua komponen KPI (10% + 10%) diambil dari `penilaian`.
- **Masalah:** Form menyimpan `follow up` (pakai spasi), halaman mencari `followup` (tanpa spasi) → 0 baris. `upselling` tidak ada di tabel `penilaian` sama sekali; sumbernya `kpi_evaluations`. Contoh: Indah (unit 1) punya nilai Follow Up dan Upselling ±75 di `kpi_evaluations`, tetapi halaman memberinya 0 → tunjangan kinerja kurang ±Rp 37.650/bulan.
- **Perbaikan:** Pakai mesin gaji final (poin 2), atau betulkan nama aspek dan sumber datanya.
- **Kalau dibiarkan:** Gaji CS selalu di bawah seharusnya.

---

## Should fix

### 5. Skor KPI bisa melebihi 100%, tidak di-cap
`app/Controllers/TutupKasir.php:1429-1456`, `:1623-1629`

- **Apa ini:** `nilai_opname = jumlah/4*100` dan `nilai_tutup_kasir = jumlah/30*20`; total skor tidak dibatasi.
- **Masalah:** Unit 3 September punya 5 periode opname FINAL → nilainya 125, bukan 100. Agustus punya 31 tutup kasir → 20,67, bukan 20. Tunjangan kinerja jadi melebihi batas (contoh Kepala Toko unit 3: +Rp 21.250 dari opname saja). Mesin payroll final membatasi di 100.
- **Perbaikan:** `$skor_total = min($skor_total, 100);` sebelum menghitung tunjangan, atau cap tiap komponen.
- **Kalau dibiarkan:** Tunjangan bisa melebihi nilai maksimum yang dirancang.

### 6. Akun nonaktif dan terhapus tetap dihitung
`app/Controllers/TutupKasir.php:815-819`

- **Apa ini:** Query karyawan hanya memfilter `ID_UNIT` dan `TERIMA_GAJI`.
- **Masalah:** Payroll memfilter `STATUS_PEGAWAI = 1` dan `deleted` kosong; halaman tidak. Karyawan yang resign tetap menambah Total Gaji sampai `TERIMA_GAJI` diubah manual, sehingga Hak Cabang mengecil. Saat ini belum ada data seperti itu, tapi akan terjadi.
- **Perbaikan:** Samakan query dengan `PayrollGenerator::daftarKaryawan()`.
- **Kalau dibiarkan:** Total Gaji halaman dan payroll berbeda setiap ada karyawan keluar.

### 7. Total gaji HO dan unit terlihat oleh semua user yang login
`app/Config/Routes.php:658`, `app/Views/dashboard/asset_berjalan.php:286-331`

- **Apa ini:** Route hanya memakai filter `auth`; menu "Asset & Pembagian" juga tanpa batas role. Panel HO menampilkan total gaji semua karyawan HO.
- **Masalah:** Karyawan jabatan 36 bisa membuka URL langsung dan melihat total gaji HO (Oktober: Rp 14.782.975) serta total gaji unitnya. Rincian per orang sudah dibatasi ke role 0/1/34, jadi ini tidak konsisten.
- **Perbaikan:** Kirim `ho_distribution` hanya untuk role 0,1,2,34,40, atau batasi seluruh halaman. Konfirmasi dulu apakah angka agregat memang boleh dilihat staf cabang.
- **Kalau dibiarkan:** Informasi gaji bocor ke seluruh karyawan.

### 8. N+1: angka yang sama di-query ulang per karyawan; halaman ±1 detik
`app/Controllers/TutupKasir.php:944-1263`

- **Apa ini:** Di dalam loop karyawan ada 5 query omset semua unit, 10 query customer, tutup kasir, opname, 4 rata-rata global, ±17 sum aspek, plus 2 query akun yang menduplikasi baris yang sudah ada.
- **Masalah:** Terukur: satu blok gaji unit 1 = 148 query / 487 ms untuk 4 karyawan. Panel HO = 653 ms untuk 7 karyawan. Total ±1,2 detik per buka halaman.
- **Perbaikan:** Angkat query tingkat unit (omset, customer, tutup kasir, opname, rata-rata) ke luar loop; ambil `NAMA_AKUN`, `ALAMAT`, `ID_JABATAN` dari baris `akun` yang sudah diambil; cache hasil distribusi HO lintas request (bukan hanya `static` per request).
- **Kalau dibiarkan:** Halaman makin lambat setiap tambah karyawan/unit.

### 9. Tidak ada test untuk angka controller
`tests/AssetBerjalanCardTest.php:26-54`

- **Apa ini:** Sembilan test yang ada hanya merender view dengan angka buatan dan menguji 3 kartu.
- **Masalah:** Tidak ada test untuk filter bulan, pengeluaran, gaji, atau kesamaan dengan payroll. `app/Scripts/assetberjalan_regression.php` membandingkan controller dengan salinan service, bukan dengan payroll, dan tidak jalan di PHPUnit/CI.
- **Perbaikan:** Satu test controller/service dengan bulan tetap yang membandingkan Total Gaji, Pengeluaran, dan Hak Cabang dengan angka payroll.
- **Kalau dibiarkan:** Bug poin 1–4 tidak akan tertangkap saat ada perubahan berikutnya.

### 10. Dropdown tahun reset saat melihat lebih dari 3 tahun ke belakang
`app/Views/dashboard/asset_berjalan.php:136`

- **Apa ini:** Pilihan tahun hanya tahun ini sampai 3 tahun lalu. Tombol "sebelumnya" bisa mundur lebih jauh.
- **Masalah:** Setelah 4 kali klik mundur ke 2022, dropdown tahun menampilkan 2026 (opsi pertama). Begitu bulan diganti, tahun ikut lompat ke 2026.
- **Perbaikan:** Susun daftar tahun dari `min(tahun terpilih, tahun ini)` sampai 3 tahun sebelumnya.
- **Kalau dibiarkan:** User mengira sedang melihat 2022, padahal datanya 2026.

### 11. Feed PL tertimpa Feed Mingguan
`app/Controllers/TutupKasir.php:1152-1160`, `:1442-1444`

- **Apa ini:** `$total_feed` diisi `feed pl`, lalu langsung ditimpa `feed mingguan`.
- **Masalah:** Untuk jabatan 44, komponen Feed PL (15%) memakai nilai Feed Mingguan. Kalau Feed Mingguan kosong tetapi Feed PL ada isinya, keduanya jadi 0. Belum ada data yang memicunya sekarang, tapi rumusnya salah.
- **Perbaikan:** Simpan dua variabel terpisah, atau pakai aturan mesin final (`feed mingguan ?: feed pl`).
- **Kalau dibiarkan:** Gaji Multimedia salah begitu pengisian Feed PL dan Feed Mingguan tidak sama.

---

## Nice to have

### 12. Kode mati dan data yang tidak dipakai
`app/Controllers/TutupKasir.php:765-766`, `:1695`, `:1707-1712`, `:1652-1658`, `:1276`

- **Apa ini:** `$hoUnitShares` dihitung tetapi tidak dipakai view; `periodeLabel`, `omset_bulan_lalu`, `periodeLaluLabel`, `selisih_omset` juga tidak dipakai; `$gaji` di-assign dua kali; `$insentif = 0` langsung ditimpa.
- **Perbaikan:** Hapus 5 variabel view, panggilan `getUnitShares`, dan 2 baris assign ganda. Setelah poin 2, salinan `UnitSalaryCalculationService` (503 baris) dan script regresinya juga bisa dihapus.
- **Kalau dibiarkan:** Pembaca mengira data itu tampil, dan setiap perubahan harus ikut mengurus salinan yang tidak dipakai.

### 13. `bulan`/`tahun` dari URL tidak divalidasi
`app/Controllers/TutupKasir.php:740-748`

- **Apa ini:** Nilai GET langsung dipakai.
- **Masalah:** `?bulan=13&tahun=2026` menghasilkan `$bulanStr = "2026-13"`. Service HO memakai `strtotime()` yang gagal, sehingga data payroll dicari di tanggal 1970; label bulan jadi "-" dan halaman menampilkan nol tanpa pesan.
- **Perbaikan:** Tolak bulan di luar 1–12 dan tahun di luar rentang wajar, lalu pakai bulan berjalan.
- **Kalau dibiarkan:** Halaman bisa menampilkan data kosong yang membingungkan.

### 14. Pertumbuhan omset 0% saat bulan lalu kosong
`app/Controllers/TutupKasir.php:792-796`

- **Apa ini:** Kalau omset bulan lalu 0, `pertumbuhan_omset` diset 0.
- **Masalah:** Halaman menulis "±0%" padahal tidak ada pembanding, bukan "belum ada data".
- **Perbaikan:** Kirim `null` saat pembanding 0; view sudah punya cabang `null`.
- **Kalau dibiarkan:** Manajemen membaca 0% sebagai "tidak ada perubahan".

### 15. Rumus uang ada di view
`app/Views/dashboard/asset_berjalan.php:33-47`

- **Apa ini:** Tanggungan, Hak Cabang, dan Tagihan Center dihitung di template.
- **Masalah:** Rumus uang hanya bisa diuji lewat render HTML; controller tidak tahu nilai yang tampil.
- **Perbaikan:** Hitung di controller/service, kirim 3 angka jadi; view hanya memformat.
- **Kalau dibiarkan:** Perubahan rumus mudah lolos tanpa test.

---

## Verdict

Belum sehat. Perbaiki poin 1–4 dulu; setelah itu angka halaman baru bisa dipakai untuk review dan bagi hasil. Poin 2 sekaligus menghapus sebagian besar duplikasi gaji.

**Lean:** sekitar **-1.300 baris** bisa dihapus (blok gaji legacy ±870 baris + salinan `UnitSalaryCalculationService` 503 baris), **0 dependency**.

## Not checked

- Tidak membuka halaman di browser sebagai user login.
- Tidak mengaudit sisa `TutupKasir.php` di luar `assetberjalan()`.
- Tidak menilai keputusan bisnis "unit 4 = 20% laba + 60% hak cabang + 40% tagihan center" (totalnya 120% dari laba) — perlu konfirmasi produk.
