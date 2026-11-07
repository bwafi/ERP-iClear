# Audit Domain Produk — ERP iClear

- **Tanggal:** 2026-11-07
- **Sifat:** Read-only. Tidak ada perubahan kode.
- **Metode:** Baca kode, route, migrasi, dan test; verifikasi read-only ke `erp_local` (root, tanpa password, `DBPrefix = ''`).
- **Cakupan:** master `barang`, generator kode, stok awal, kartu stok, mutasi, opname, stok minimum, stok HP, bundle, retur (customer/suplier/rusak), dan HPP.

## Apa yang repo ini lakukan

CI4 4.4.8 / MySQLi, ERP toko multi-unit. Domain produk mencakup: master `barang`, generator kode, stok awal, kartu stok (view), mutasi, opname, stok minimum, stok HP, bundle, retur (customer/suplier/rusak), dan HPP.

**Beban yang diasumsikan:** beberapa kasir paralel per unit, 1.781 produk (1.532 aktif), 4.642 baris `detail_penjualan`, 1.885 baris `stok_barang`, dan laporan keuangan harian yang membaca HPP. Pada beban ini, race condition kode barang dan selisih HPP langsung terasa.

**Bukti DB yang dipakai:**

- `stok_barang` dan `stok_hp`: `IS_UPDATABLE = 'NO'`.
- `barang.kode_barang`: tidak ada unique index; sudah ada duplikat `KH01`.
- `penjualan.kode_invoice`: tidak unik; 27 nomor duplikat.
- 5 baris stok negatif (mis. `sprt198` unit 3, `sprt1262` unit 1).
- 9.812 baris opname final, 623 dengan `selisih ≠ 0`.
- 13 baris `detail_penjualan` qty > 1: 5 menyimpan HPP per-unit, 6 menyimpan HPP total.
- `sql_mode` strict; `stok_opname_audit.aksi` ENUM tidak punya `batal_draft`.
- `stok_hp` kosong karena `barang.nama_barang_id = 0` untuk semua produk.

---

## Must fix

### M1. Pembuatan & import produk mati total — `ArgumentCountError`

- **File:** `app/Controllers/Produk.php:71,138,276`; `app/Controllers/Phone.php:81,287`; `app/Models/ModelBarang.php:210-219`
- **Masalah:** Pemanggil memberi 1 argumen, signature butuh 2. Setiap `insert_produk`, `update_produk`, `import_produk`, dan pembuatan Phone akan fatal sebelum menulis apa pun.
- **Fix terkecil:** Samakan signature/pemanggil (tambahkan argumen kedua yang memang dipakai, atau buat parameter opsional) dan tambah test satu kasus insert.
- **Dampak:** Fitur inti master produk tidak bisa dipakai. Ini blocker, bukan bug kosmetik.

### M2. Generator kode produk salah bentuk + rawan duplikat

- **File:** `app/Models/ModelBarang.php:210-219`; `app/Controllers/Produk.php:74,141,278`; `app/Controllers/Phone.php:84,289`
- **Masalah:** Query mengembalikan alias `max_kode`, tetapi pemanggil membaca `->kode_barang`, jadi kode baru selalu kosong/aneh. Tidak ada unique index di `barang.kode_barang`, pola `MAX + 1` juga race-prone. Duplikat `KH01` sudah ada di DB.
- **Fix terkecil:** Kembalikan kolom dengan nama yang dibaca pemanggil, lalu tambah unique index lewat migrasi (setelah bersihkan duplikat). Idealnya generator memakai `SELECT ... FOR UPDATE` atau retry saat duplicate key.
- **Dampak:** Kode produk ganda membuat kartu stok, retur, dan laporan berbasis `kode_barang` salah sambung.

### M3. Update stok minimum menulis ke view read-only

- **File:** `app/Controllers/StokMinimum.php`; `app/Models/ModelStokBarang.php`
- **Masalah:** `stok_barang` adalah view non-updatable, jadi update selalu gagal. `stok_minimum` sebenarnya ada di `barang` dan bersifat global, bukan per unit — UI menyesatkan.
- **Fix terkecil:** Update langsung ke `barang.stok_minimum`; kalau butuh per unit, buat tabel/kolom baru, jangan lewat view.
- **Dampak:** Notifikasi stok minimum tidak pernah bisa diatur dari UI; user mengira tersimpan padahal gagal.

### M4. Penjualan tidak memvalidasi stok di server

- **File:** `app/Controllers/Penjualan.php` (`insert_penjualan`)
- **Masalah:** qty, harga, dan `id_produk` dipercaya dari POST. Tidak ada cek stok, tidak ada pengurangan atomik. Stok negatif sudah ada di data nyata.
- **Fix terkecil:** Validasi produk milik unit, cek stok dalam transaksi yang sama dengan insert, tolak qty ≤ 0. Cukup satu guard terpusat di service insert.
- **Dampak:** Oversell, stok minus, dan laporan persediaan tidak dapat dipercaya. Ini juga membuka manipulasi harga dari client.

### M5. Retur menulis stok lintas unit tanpa transaksi

- **File:** `app/Controllers/Retur_Customer.php` (`insert`); `app/Controllers/Retur_Suplier.php` (`insert`)
- **Masalah:** `unit_idunit` dan id produk diambil dari POST, tidak ada transaksi, tidak ada guard total retur kumulatif. `$result`/`$iddetail_penjualan` bisa undefined saat tidak ada item dipilih. Retur bisa menambah stok di unit sembarang.
- **Fix terkecil:** Ambil unit dari sesi/scope server, bungkus dalam transaksi, validasi qty ≤ sisa yang bisa diretur, inisialisasi variabel sebelum loop.
- **Dampak:** Stok bisa digelembungkan di unit lain dan retur melebihi penjualan. Risiko keuangan dan audit.

### M6. Stok opname tidak pernah mengoreksi stok, dan batal draft selalu gagal

- **File:** `app/Services/StokOpnameService.php`; migrasi view `stok_barang`; `app/Controllers/StokOpname.php`
- **Masalah:** View memakai `0 AS stok_opname`, jadi 623 baris final dengan `selisih ≠ 0` tidak berpengaruh ke stok. `batalDraft` menulis ENUM `batal_draft` yang tidak ada di schema; dengan `sql_mode` strict, transaksi selalu rollback.
- **Fix terkecil:** Terapkan penyesuaian opname lewat mutasi stok resmi (bukan view), dan tambahkan `batal_draft` ke ENUM via migrasi. Tambah regression test untuk finalisasi dan pembatalan.
- **Dampak:** Opname hanya jadi catatan; stok fisik dan sistem terus menyimpang. Pembatalan draft tidak bisa dipakai.

### M7. Semantik HPP terbelah dua → laporan pasti salah untuk qty > 1

- **File:** `app/Controllers/LabaRugi.php` (`hpp_penjualan * jumlah`); `app/Services/Finance/OmsetDailyCalculator.php` (`sub_total - hpp_penjualan`)
- **Masalah:** Satu laporan menganggap `hpp_penjualan` per unit, yang lain menganggap total. Data live berisi dua-duanya (5 per-unit, 6 total pada qty > 1).
- **Fix terkecil:** Tetapkan satu kontrak (mis. selalu simpan HPP per unit di `detail_penjualan`), perbaiki penulisnya, lalu samakan semua pembaca. Jangan ubah data historis tanpa otorisasi; pakai backfill/reversal.
- **Dampak:** Laba kotor dan omzet harian berbeda untuk transaksi yang sama. Ini angka yang dipakai keputusan bisnis.

### M8. Endpoint tulis produk tanpa autentikasi & tanpa cek role

- **File:** `app/Config/Routes.php:140` (`/insert_produk` → `Pembelian::insert_produk`); mayoritas route tulis produk/stok
- **Masalah:** `AuthFilter` hanya cek `logged_in`, dan route ini bahkan tanpa filter `auth`. Tidak ada cek role/unit; sebagian besar tidak memakai CSRF.
- **Fix terkecil:** Tambahkan `['auth','csrf']` pada route tulis dan guard role yang konsisten dengan modul lain.
- **Dampak:** Siapa pun yang bisa menjangkau URL dapat menulis produk/stok. Ini celah keamanan, bukan hanya UX.

---

## Should fix

### S1. Bundle menyimpan `sub_total` penuh di setiap komponen

- **File:** `app/Controllers/Bundle.php`; model terkait `detail_bundle`
- **Masalah:** Setiap baris komponen mendapat `sub_total` penuh sehingga total bundle berlipat. Saat ini `detail_bundle` kosong (0 bundle), jadi masih laten.
- **Fix:** Simpan harga komponen per baris atau simpan total hanya di header. Tambah test hitung total bundle.
- **Dampak:** Begitu bundle dipakai, HPP/omzet membengkak tanpa terlihat.

### S2. Halaman master produk tanpa pagination

- **File:** `app/Controllers/Produk.php`, view terkait
- **Masalah:** 1.781 produk dimuat sekaligus.
- **Fix:** Pakai DataTables server-side seperti halaman lain.
- **Dampak:** Halaman lambat dan berat di memori; makin buruk seiring pertumbuhan data.

### S3. Kartu Stok join rapuh + subquery berkorelasi per baris

- **File:** `app/Models/ModelKartuStok.php`
- **Masalah:** Join memakai `kode_barang` (yang tidak unik), plus dua subquery supplier berkorelasi per baris.
- **Fix:** Join lewat `id_barang`, dan ganti subquery dengan join agregat sekali jalan.
- **Dampak:** Baris ganda/salah dan query lambat pada riwayat panjang.

### S4. Import produk tidak transaksional & validasi file lemah

- **File:** `app/Controllers/Produk.php` (`import_produk`)
- **Masalah:** Tidak ada transaksi, validasi file kurang, dan `addslashes` ganda.
- **Fix:** Bungkus satu transaksi per file, validasi MIME/ukuran/kolom, hapus escape ganda (gunakan query builder).
- **Dampak:** Import gagal separuh jalan meninggalkan data parsial; risiko injeksi/encoding rusak.

### S5. Halaman Stok HP selalu kosong

- **File:** view `stok_hp`; `app/Controllers/StokHP.php`
- **Masalah:** Semua `barang.nama_barang_id = 0`, jadi view menghasilkan 0 baris. View juga masih menambahkan `stok_opname` (bertentangan dengan keputusan 2026-10-07) dan tidak mengurangi garansi.
- **Fix:** Isi relasi `nama_barang_id` atau ganti join ke `id_barang`, lalu selaraskan definisi view dengan keputusan opname/garansi.
- **Dampak:** Fitur tampil ada tapi tidak berfungsi — persis kategori "gap bukan status selesai".

### S6. Stok awal menerima qty negatif/kosong dan unit dari POST

- **File:** `app/Controllers/StokAwal.php`
- **Masalah:** Tidak ada validasi qty, `unit_id` dipercaya dari request. 23 baris `stok_awal` tanpa `satuan_terkecil`, sehingga mutasi menolaknya.
- **Fix:** Validasi qty > 0, ambil unit dari scope server, wajibkan `satuan_terkecil` saat insert.
- **Dampak:** Data awal kotor langsung memicu stok negatif dan mutasi gagal.

### S7. `getByIdBarang` mengabaikan unit

- **File:** `app/Models/ModelBarang.php`
- **Masalah:** Lookup produk tidak memfilter unit, padahal stok per unit.
- **Fix:** Tambahkan parameter/filter unit di query dan pemanggilnya.
- **Dampak:** Harga/stok unit lain bisa terbaca dan dipakai di transaksi unit yang salah.

### S8. `createPeriode` opname N+1

- **File:** `app/Services/StokOpnameService.php`
- **Masalah:** Dua query per produk saat membuat periode.
- **Fix:** Satu `INSERT ... SELECT` atau batch insert.
- **Dampak:** Pembuatan periode lambat dan berat di DB saat produk banyak.

---

## Nice to have

### N1. 159 produk aktif dengan `harga = 0` atau `harga_beli = 0`

- **File:** data `barang`
- **Fix:** Tambah validasi/peringatan saat simpan, dan query audit berkala.
- **Dampak:** Penjualan bisa terjadi dengan harga nol; HPP tidak akurat.

### N2. 189 produk aktif tanpa baris `stok_awal`

- **File:** data `stok_awal`
- **Fix:** Tampilkan status "belum ada stok awal" di master produk.
- **Dampak:** Stok awal tidak lengkap menyulitkan rekonsiliasi.

### N3. Tidak ada constraint DB untuk stok negatif

- **File:** schema stok (view + tabel sumber)
- **Fix:** Tambah `CHECK`/guard di service penulisan stok.
- **Dampak:** Bug aplikasi langsung menjadi data rusak tanpa penahan terakhir.

### N4. Tidak ada test untuk generator kode, stok minimum, guard stok penjualan, retur, dan matematika HPP

- **File:** `tests/`
- **Fix:** Tambah regression test kecil per area.
- **Dampak:** Perbaikan di atas mudah rusak lagi tanpa terdeteksi.

---

## Verdict

**Domain produk belum aman untuk produksi.** Tiga jalur inti rusak secara fungsional (buat/import produk, stok minimum, batal opname), satu jalur keamanan terbuka (`/insert_produk` tanpa auth), dan dua area data berisiko tinggi (stok penjualan tanpa guard, HPP dengan dua semantik). Stok opname yang tidak mengoreksi stok membuat seluruh alur persediaan kehilangan makna.

Urutan perbaikan yang disarankan: **M1 → M2 → M8 → M4 → M6 → M7 → M5 → M3**, lalu should-fix.

## Lean

- Perbaikan M1/M2/M3 bisa satu PR kecil: samakan signature, unique index + generator aman, update langsung ke `barang`.
- M4/M5/M6 sebaiknya satu service stok terpusat dengan transaksi + guard, menggantikan logika tersebar. Ini menghapus lebih banyak kode daripada menambah.
- M7 butuh keputusan bisnis dulu (per-unit vs total) sebelum menyentuh data historis.
- S1, S5, N1–N2 bisa ditunda tanpa memperparah risiko keamanan.

## Not checked

- Tidak menjalankan UI/browser manual; temuan UI berbasis kode dan definisi view.
- Tidak menjalankan seluruh test suite; hanya membaca test yang ada.
- Tidak memutar ulang migrasi data legacy atau menguji backfill HPP.
- Tidak menguji perilaku konkurensi secara langsung; race condition disimpulkan dari pola `MAX+1` dan absennya unique index.
- Tidak mengaudit modul di luar produk/stok yang terdampak (pajak, payroll, KPI) selain titik baca HPP.
