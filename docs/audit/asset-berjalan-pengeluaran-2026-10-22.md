# Audit "Pengeluaran Bulan Ini" — `/asset_berjalan`

Tanggal: 22 Oktober 2026
Cakupan: satu kartu **Pengeluaran Bulan Ini** di `/asset_berjalan`.
Data: `erp_local` (read-only), periode Agustus–Oktober 2026. Tidak ada kode yang diubah.

> **Update 22 Okt 2026 (issue #28):** kategori A sudah diganti dari `[1,2,3,4,5,11,18]` menjadi `[1,2,3,4,5,18]` (11 Administrasi dan 6 Lain-lain keluar; 18 Lainnya 6% tetap masuk), peringatan batas 6% sudah ditambahkan. Kartu-kartunya sekarang "Biaya Terkontrol (Maks 6%)" dan "Pengeluaran di Luar 6%" (seluruh kas keluar minus kategori A). Temuan 2 (kategori SDM campur) dan 3 (refund) belum ditangani.

## Yang diaudit

- Kartu: `app/Views/dashboard/asset_berjalan.php:181-196`
  (judul "Pengeluaran Bulan Ini", subjudul "Biaya operasional unit periode ini").
- Angka: `TutupKasir::assetberjalan()`, `app/Controllers/TutupKasir.php:838-847`
  → `SUM(kas_keluar.jumlah)` untuk unit + bulan terpilih, hanya kategori
  `[1 OPERASIONAL, 2 ATK, 3 MAINTENANCE, 4 TRANSPORT, 5 KONSUMSI,
  11 ADMINISTRASI, 18 Lainnya(Termasuk 6%)]`.
- Filter periode sudah benar (pakai `$bulan`/`$tahun`, bukan tanggal hari ini)
  dan sudah ada test-nya di `tests/AssetBerjalanFilterTest.php:211-230`.

Kategori yang **tidak** dihitung: 6 LAIN-LAIN, 7 HPP, 8 MARKETING,
9 IKLAN ADS, 10 SDM, 12 CICILAN, 13 SEWA, 15 BEBAN CABANG, 16 TEMPO,
17 REFUND, 19 Kasbon.

## Must fix

### 1. Kartu mengklaim "biaya operasional", isinya hanya 4 dari 15 kategori

Contoh **Pandaan Oktober 2026**:

| Pos | Jumlah | Masuk kartu? |
|---|---:|---|
| OPERASIONAL + KONSUMSI + TRANSPORT + ATK | 2.308.900 | ya |
| IKLAN ADS | 3.983.911 | tidak |
| SDM non-gaji (komisi 1.509.750 + seragam 99.978) | 1.609.728 | tidak |
| ROYALITY CABANG (kategori 6) | 20.715.623 | tidak |
| Refund penjualan (bukan refund DP) | 1.300.000 | tidak |
| **Biaya operasional nyata (tanpa gaji)** | **29.918.162** | **2.308.900** |

Kartu menampilkan **Rp 2,3 juta** untuk bulan dengan biaya operasional
**Rp 29,9 juta** — selisih 13 kali. Total kas keluar bulan itu Rp 77.936.567.

Contoh lain:

- **Banyuwangi September 2026:** kartu 3.496.682; iklan 3.693.999 + komisi
  902.500 tidak dihitung → biaya operasional nyata (tanpa gaji) 8.093.181.
- **Genteng Oktober 2026:** kartu 1.118.505; iklan 2.744.218 + komisi 280.000
  tidak dihitung → 4.142.723.

Yang **benar** dikeluarkan: HPP (biaya barang sudah lewat margin
`sub_total - hpp_penjualan`), TEMPO (pelunasan utang lama), CICILAN (pokok
pinjaman), Kasbon (piutang karyawan), dan refund DP (pengembalian deposit,
bukan biaya). Masalahnya bukan pengecualian itu, tetapi iklan, komisi, royalti,
dan refund penjualan ikut hilang tanpa alasan yang tercatat.

- **Perbaikan:** definisikan kategori mana yang masuk laba-rugi (flag di
  `kategori_kas`), minimal IKLAN ADS dan komisi/seragam; putuskan royalti dan
  refund penjualan.
- **Kalau dibiarkan:** kartu ini menjadi pembenaran angka laba yang terlalu
  besar di audit Hak Cabang; pembaca mengira biaya cabang cuma 2 juta.

### 2. Kategori SDM mencampur gaji, lembur, komisi, bagi hasil, dan kasbon

Satu kategori tidak bisa "masuk" atau "tidak masuk" sekaligus:

| Isi kategori 10 (SDM) | Perlakuan yang benar | Sudah dihitung di mana? |
|---|---|---|
| Gaji | biaya | kartu Total Gaji |
| Lembur | biaya | kartu Total Gaji (`PayrollGenerator::daftarLembur`) |
| Komisi, seragam | biaya | **tidak di mana pun** |
| SHARING PROFIT (Pandaan Sept 31.324.744) | pembagian laba | **tidak boleh jadi biaya** |
| Kasbon | piutang | bukan biaya |

Karena seluruh kategori 10 dikecualikan, komisi dan seragam hilang dari
laporan: Pandaan Oktober 1.609.728; Banyuwangi Oktober 883.500; Genteng
Oktober 280.000; Banyuwangi September 902.500. Kalau kategori 10 dimasukkan
apa adanya, gaji dan bagi hasil jadi dobel.

- **Perbaikan:** beri penanda per baris (gaji/lembur/komisi/bagi hasil/kasbon),
  atau pisahkan kategori. Jangan menebak dari `deskripsi`.
- **Kalau dibiarkan:** pilih masukkan → dobel; pilih keluarkan → biaya nyata
  hilang. Tidak ada pilihan yang benar dengan struktur data sekarang.

### 3. Refund tidak mengurangi apa pun

Kategori 17 (REFUND) tidak masuk pengeluaran dan tidak ada baris penjualan
negatif di `detail_penjualan` (0 baris). Jadi refund benar-benar tidak terlihat
di laporan.

- Pandaan Oktober 2026: refund Rp 1.600.000 (1.300.000 refund mesin +
  300.000 refund DP). Yang 1.300.000 mengurangi laba; laba 40.742.140 →
  39.442.140, Hak Cabang turun Rp 780.000.
- Refund berulang tiap bulan: Pandaan Juli 1.500.000, Agustus 1.200.000,
  September 950.000; Banyuwangi Oktober 50.000; Probolinggo Oktober 500.000.
- Sebagian refund adalah pengembalian DP (bukan penjualan), jadi jangan
  masukkan semua. Perlu ditautkan ke invoice/DP asalnya.
- **Perbaikan:** refund penjualan mengurangi omset; refund DP tidak.
- **Kalau dibiarkan:** laba (dan pembagian 60/40) selalu lebih tinggi dari
  kenyataan setiap kali ada refund.

## Should fix

### 4. Judul "Bulan Ini" tidak mengikuti bulan yang dipilih

Buka `?unit=3&bulan=9&tahun=2026`: kartu Omset punya subjudul
"Periode September 2026", kartu Pengeluaran tetap "Bulan Ini" tanpa keterangan
periode. Orang bisa membandingkan angka September dengan judul yang
tersirat Oktober.

- **Perbaikan:** pakai `$periodeLabelId` seperti kartu Omset, atau ganti judul
  jadi "Pengeluaran".
- **Kalau dibiarkan:** salah baca periode saat presentasi.

### 5. Tidak ada rincian; tidak bisa dicocokkan dengan buku kas

Kartu Gaji punya tombol rincian; kartu Pengeluaran hanya satu angka. Kasus
Pandaan Oktober: 2.308.900 di halaman vs 77.936.567 di daftar kas keluar.
Finance tidak punya cara melihat 2,3 juta itu terdiri dari apa.

- **Perbaikan:** tombol rincian per kategori (query yang sama dengan kartu).
- **Kalau dibiarkan:** setiap tutup buku jadi investigasi manual.

### 6. Test hanya mengunci satu kategori

`tests/AssetBerjalanFilterTest.php` memasang kategori 1 dan 10, lalu memastikan
kategori 10 tidak masuk. Menambah/menghapus kategori 6, 8, 9, 13, 15, 17 tidak
akan menggagalkan test mana pun.

- **Perbaikan:** satu test dengan satu baris per kategori, assert total kartu.
- **Kalau dibiarkan:** perubahan daftar kategori lolos tanpa terdeteksi.

### 7. `JOIN kategori_kas` tidak dipakai dan bisa membuang baris

`TutupKasir.php:840` melakukan inner join ke `kategori_kas`, padahal tidak ada
kolom kategori yang dipakai di `SELECT`/`WHERE`. Baris dengan
`kategori_idkategori` yang tidak ada di tabel kategori akan hilang diam-diam.
Saat ini hanya ada satu baris yatim (jumlah 0), jadi belum berdampak.

- **Perbaikan:** hapus join, atau ganti `LEFT JOIN` + sertakan kategori tak
  dikenal.
- **Kalau dibiarkan:** suatu hari ada pengeluaran yang tidak pernah muncul.

## Nice to have

### 8. Filter `MONTH()`/`YEAR()` tidak memakai index

`TutupKasir.php:842-843`. Dengan ±200 baris per cabang per bulan, belum terasa.
Baru perlu diubah kalau data bertahun-tahun dan halaman mulai lambat.

## Verdict

Kartu ini bukan "biaya operasional"; ia adalah "belanja kategori 1-5, 11, 18".
Angkanya benar sebagai penjumlahan kategori tersebut, tetapi labelnya menyesatkan
dan angkanya 6-13 kali lebih kecil dari biaya operasional nyata pada bulan yang
diperiksa. Jangan dipakai untuk menilai kesehatan cabang sebelum poin 1-3
diputuskan.

## Lean

Perbaikan minimum: satu flag `masuk_laba_rugi` di `kategori_kas`, pisahkan
komisi/seragam dari SDM (atau tandai barisnya), tautkan refund ke invoice,
lalu ganti subjudul kartu. Tidak perlu service atau tabel baru.

## Tidak diperiksa

Bulan sebelum Juli 2026, apakah royalti memang biaya cabang menurut kontrak,
perlakuan refund DP di modul DP, dan tampilan di browser.
