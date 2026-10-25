# Audit Hak Cabang & Tagihan Center — `/asset_berjalan`

Tanggal: 22 Oktober 2026
Cakupan: kartu **Hak Cabang** dan **Tagihan Center** di `/asset_berjalan` (bukan seluruh halaman).
Data: `erp_local` (read-only), periode Juli–Oktober 2026. Tidak ada kode yang diubah.

> **Update 22 Okt 2026 (issue #28):** daftar kategori pengeluaran sudah diganti menjadi `[1,2,3,4,5,18]` (11 Administrasi dan 6 Lain-lain keluar; 18 Lainnya 6% tetap masuk). Dampak ke Hak Cabang/Tagihan Center belum diputuskan; temuan 2–3 di bawah masih terbuka.

## Yang diaudit

Halaman `/asset_berjalan` adalah laporan bulanan per cabang: omset, pengeluaran,
total gaji, tanggungan asset, hak cabang, tagihan center, plus panel gaji HO.

- Rumus ada di view: `app/Views/dashboard/asset_berjalan.php:33-47`
  - `$profitNett = $omset_bulan - $pengeluaran - $totalGajiUnit`
  - Hak Cabang = `0.60 * $profitNett`, Tagihan Center = `0.40 * $profitNett`
  - Hanya untuk unit id `[3,4,5]`; unit 4 juga `20%` tanggungan; unit `[1,2]` tanggungan `hari × 355.000`
- Input dari controller `TutupKasir::assetberjalan()`:
  - omset/margin: baris 775-783 (`SUM(sub_total - hpp_penjualan)`)
  - pengeluaran: baris 838-847 (kategori `[1,2,3,4,5,11,18]`)
  - gaji: baris 815-836 (`PayrollGenerator::hitungGajiUnit`)
  - jumlah hari: baris 850

Aritmatika 60/40 benar (hak + tagihan = 100% laba, tidak ada selisih pembulatan).
Masalahnya ada di angka laba yang menjadi dasar.

## Must fix

### 1. Setengah biaya nyata tidak masuk "Pengeluaran", jadi laba (dan Hak Cabang) inflated

`app/Controllers/TutupKasir.php:838-847` hanya menjumlah kategori
`[1,2,3,4,5,11,18]`.

- **Kasus nyata — Pandaan Oktober 2026:** yang dihitung Rp 2.308.900. Yang tidak
  dihitung: ROYALITY CABANG Rp 20.715.623 (kategori 6), IKLAN ADS Rp 3.983.911,
  dan SDM non-gaji (komisi/lembur/seragam) Rp 1.749.728.
  Laba tampil **40.742.140**; kalau tiga biaya itu masuk, laba **14.292.878**.
  Hak Cabang turun dari **24.445.284 → 8.575.727** (turun 65%),
  Tagihan Center **16.296.856 → 5.717.151**.
- **Kasus kedua — Banyuwangi September 2026:** iklan Rp 3.693.999 + komisi/lembur
  Rp 1.007.500 tidak dihitung. Hak Cabang tampil 40.813.605, seharusnya
  sekitar 37.992.706.
- Kategori 6 `LAIN-LAIN` dikecualikan, tetapi 18 `Lainnya(Termasuk 6%)`
  dihitung. Jenis belanja yang sama (doorprize, tisu, royaliti) diperlakukan
  berbeda tergantung kasir memilih kategori mana.
- **Perbaikan:** satu flag di `kategori_kas` (mis. `masuk_laba_rugi`) atau daftar
  tertulis beserta alasannya; minimal iklan dan komisi/lembur masuk.
- **Kalau dibiarkan:** cabang dibayar 60% dari laba yang tidak pernah ada;
  center menagih 40% dari angka yang sama.

Catatan: CICILAN/TEMPO/Kasbon (pokok pinjaman/pelunasan utang) dan SDM penuh
(berisi gaji + `SHARING PROFIT` yang sudah dihitung di tempat lain) sengaja
tidak dimasukkan karena akan dobel.

### 2. HPP kosong dianggap nol, jadi margin terlalu besar

`app/Controllers/TutupKasir.php:775-783` menghitung margin
`sub_total - hpp_penjualan`.

- September 2026: **81 dari 970** baris penjualan HPP-nya NULL/0; unit 3
  menyumbang **Rp 6.330.000** dari margin 80.091.348 (7,9%) → Hak Cabang
  kelebihan sekitar **Rp 3.798.000**.
- Oktober 2026: **73 dari 716** baris; unit 4 Rp 4.230.000 dari 49.357.400
  (8,6%) → kelebihan sekitar **Rp 2.538.000**.
- Sebagian wajar (JASA UPDATE IOS memang tanpa HPP), tetapi ada barang jelas:
  "LCD IPHONE 11 NO HPP COPOTAN", "KAMERA BDEPAN IPHONE XR",
  "FLEX POWER IPHONE 11".
- **Perbaikan:** isi HPP barang (proses), plus peringatan di halaman kalau ada
  baris barang dengan HPP 0. Jangan diam-diam dianggap untung 100%.
- **Kalau dibiarkan:** split bulanan selalu lebih besar dari laba sebenarnya,
  dan besarnya berubah-ubah tergantung admin input HPP.

### 3. Laba negatif ditampilkan sebagai angka negatif

Tidak ada penanganan rugi di view.

- Genteng Agustus 2026 (belum ada penjualan): laba −4.500.000 → Hak Cabang
  **−2.700.000**, Tagihan Center **−1.800.000**.
- Genteng September 2026: laba −4.029.380 → **−2.417.628 / −1.611.752**.
- Kartu tertulis "Kewajiban penyetoran 40% ke Head Office" dengan angka minus.
  Tidak jelas apakah utang berkurang, atau cabang justru menagih.
- **Perbaikan:** putuskan: clamp 0, carry-forward kerugian, atau tampilkan
  sebagai baris "Rugi" terpisah.
- **Kalau dibiarkan:** angka minus dibaca sebagai kredit/piutang oleh orang
  berbeda-beda.

## Should fix

### 4. Aturan unit hardcoded per id, bukan `unit.jenis`

View baris 33-47: `== 4`, `in_array(..., [1,2])`, `in_array(..., [3,4,5])`.
Padahal DB punya `unit.jenis` (`pribadi`/`franchise`) dan `is_trial`.
Unit franchise baru (id 6) otomatis dapat Hak Cabang 0 / Tagihan 0 tanpa error;
unit 5 yang `is_trial=1` tetap di-split 60/40.

- **Perbaikan:** ambil dari `jenis` + satu kolom khusus untuk deal 20% Pandaan.
- **Kalau dibiarkan:** cabang baru tidak pernah dapat haknya dan tidak ada yang
  sadar.

### 5. Hak Cabang bukan catatan pembayaran, cuma hitungan ulang

Halaman menghitung gaji via `PayrollGenerator::hitungGajiUnit`
(`TutupKasir.php:815-836`), bukan baris payroll yang sudah dibukukan.

- **Kasus nyata:** Pandaan Agustus 2026 di halaman = **29.643.101**;
  satu-satunya pembayaran "SHARING PROFIT" yang tercatat adalah **31.324.744**
  (2 September, kategori SDM). Selisih Rp 1.681.643 (5,7%).
- Kalau KPI diedit setelah payroll dibayar, Hak/Tagihan bulan lalu ikut berubah.
- **Perbaikan:** snapshot split saat tutup payroll, atau baca angka yang
  dibukukan.
- **Kalau dibiarkan:** laporan dan uang yang ditransfer tidak pernah cocok, dan
  tidak ada jejak versi mana yang benar.

### 6. Unit 4: total tiga kartu = 120% laba

Pandaan Oktober 2026: tanggungan 8.148.428 + hak 24.445.284 + tagihan
16.296.856 = **48.890.568** vs laba **40.742.140**.

Audit sebelumnya (`docs/audit/asset-berjalan-2026-10-22.md:154`) sudah menandai
ini sebagai perlu keputusan produk; masih terbuka.

- **Perbaikan:** putuskan 20% itu diambil dari bagian cabang (tampilkan hak net
  40%) atau memang tambahan.
- **Kalau dibiarkan:** total kewajiban unit 4 melebihi labanya sendiri.

### 7. Test tidak menutup kasus yang berbahaya

`tests/AssetBerjalanCardTest.php` (10 test) hanya menguji laba positif untuk
unit 2/3/4/5. Tidak ada test untuk: laba negatif, unit id baru/`jenis` baru,
dan kategori biaya yang dikecualikan.

- **Perbaikan:** tiga test itu.
- **Kalau dibiarkan:** temuan di atas tidak akan tertangkap saat refactor
  berikutnya.

## Nice to have

### 8. "Omset" di kartu = margin, bukan omzet

Banyuwangi September 2026: kartu "Omset" Rp 80.091.348, penjualan kotor
Rp 131.346.000. Catatan formula menulis "Omset − Pengeluaran − Total Gaji",
jadi pembaca wajar mengira itu omzet. Di fungsi yang sama
(`TutupKasir::omsetbulanan`, baris 575) ada variabel lain yang memang
`SUM(sub_total)`. Bukan bug kalau istilah "omset" di bisnis ini memang berarti
margin — tetapi kartu sebaiknya diberi label "Laba Kotor".

Rumus di view sudah tercatat di audit sebelumnya poin 15, tidak diulang di sini.

## Verdict

Hak Cabang dan Tagihan Center konsisten secara aritmatika dengan input halaman,
tetapi belum bisa dipercaya sebagai angka bayar. Pada satu bulan nyata (Pandaan
Oktober 2026) Hak Cabang bisa turun 65% hanya dengan memasukkan biaya yang
jelas-jelas biaya. Jangan pakai untuk transfer sebelum poin 1–3 diputuskan.

## Lean

Tidak perlu abstraksi baru. Perbaikan minimum: satu flag kategori di DB, isi
HPP barang, satu aturan rugi, tiga test. Formula 60/40 di view tidak perlu
disentuh kalau inputnya dibenahi.

## Tidak diperiksa

Bulan sebelum Juli 2026, kebenaran `barang.harga_beli`, apakah selisih 1,68 juta
di poin 5 karena jeda pembayaran, aturan bisnis unit 1/2 (tidak ada split), dan
perlakuan pajak.
