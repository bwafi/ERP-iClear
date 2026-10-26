# PRD Modul 01 — Master Data (Inti)

| | |
|---|---|
| Kode Modul | MD-01 |
| Sumber analisis | `docs/prd/_analysis/Group_A_Inti_MasterData.md` |
| Status verifikasi | Fakta kode ✅ / Inferensi 🔶 / Ketidakpastian ⚠️ (tandai per aturan) |

---

## 1. Tujuan
Menyediakan data master yang dipakai lintas modul: akun pengguna & jabatan, unit/gedung, produk & handphone, kategori/sub-kategori, pelanggan, supplier, kerusakan, nomor akun, bundle, bank, nama handphone.

## 2. Aktor
- Semua pengguna login (authentikasi hanya `logged_in`; tidak ada penegakan role per-route di modul ini).
- `AuthFilter` hanya memeriksa `session()->get('logged_in') === true` (`app/Filters/AuthFilter.php::before`).

## 3. Preconditions
- User sudah login (`logged_in=true`).
- Data referensi (kategori, unit, jabatan) sudah ada/diisi.

## 4. Alur Utama
### 4.1 Login (Auth)
1. GET `/login` → `Auth::login` render form.
2. POST `/proses_login` → `Auth::proses_login`: cari akun via `ModelAuth::cekUsername` (`NOID|KTP|EMAIL`).
3. `password_verify(PASSWORD)` → set sesi (`ID_AKUN, ID_JABATAN, ID_UNIT, NAMA_UNIT, logged_in=true`).
4. Redirect `/`.
### 4.2 Pegawai & Jabatan
CRUD via `Pegawai::insert/update/delete`; jabatan via `insert/update_jabatan`; reset password via `reset_password`. Upload foto KTP/KK.
### 4.3 Unit, Produk, Handphone, Kategori
CRUD; produk adalah kode `kodeKategori+nomor(2)`; handphone (kategori 1) kode `HP+nomor(2)` + cek IMEI unik; sub-kategori cascading.
### 4.4 Pelanggan, Supplier, Kerusakan, NoAkun, Bundle
CRUD; pelanggan mendukung ekspor/impor Excel, pencarian, region; bundle menyimpan detail produk.

## 5. Business Rules (Bukti)
1. Login menerima NOID/KTP/EMAIL. — `ModelAuth::cekUsername`
2. Password diverifikasi via `password_verify`. — `Auth::proses_login`
3. Password pegawai baru = hash NOID (cost 10); reset = literal `urbankeren`. — `Pegawai::insert`, `Pegawai::reset_password`
4. Hapus pegawai = soft delete (`STATUS_PEGAWAI='0'`, `JENIS_PEGAWAI=100`). — `Pegawai::delete`
5. Kode produk = `kode_kategori + nomor_urut(2)`; resiko duplikat karena tanpa unique constraint [⚠️/🔶]. — `Produk::insert_produk`, `ModelBarang::getLastBarangByKategori`
6. HP (kategori 1): IMEI wajib unik (insert & update kecuali id sendiri); HP baru `status='0'`. — `Phone::insert_phone`, `Phone::update_phone`
7. Nama handphone: kombinasi `nama+size+type` unik; delete = hard delete (inkonsisten dgn soft-delete lain). — `Phone::insertnamahandphone`, `NamaHandphone::deleteNamaHandphone`
8. Pelanggan: list `deleted=0`; insert menolak duplikat NIK & No HP aktif; delete soft. — `ModelPelanggan::getPelanggan`, `Pelanggan::simpanPelanggan`
9. Supplier baru diikat ke `unit_idunit=session('ID_UNIT')`. — `Supplier::insert_suplier`
10. NoAkun: `no_akun` unik. — `NoAkun::insert`
11. Bundle: simpan `bundle`+`detail_bundle`; update sinkronkan detail; delete hapus bundle+detail. — `Bundle::insert/update/delete`
12. **Tidak ada transaksi DB eksplisit** untuk operasi multi-tabel (risiko data parsial). — pemeriksaan seluruh controller grup A

### Alur Alternatif
- Multi-gedung: `Auth::setGedung` menyimpan `ID_GEDUNG` dan tetap `logged_in=true`.
- Login gagal → flashdata `pesan_username`/`pesan_password`, redirect `/login`.

## 6. Acceptance Criteria
- [ ] User dapat login, ganti password, logout.
- [ ] CRUD pegawai & jabatan berfungsi; reset password mengubah password ke nilai default.
- [ ] IMEI duplikat ditolak saat insert/update HP.
- [ ] Duplikat NIK/No HP ditolak saat input pelanggan (AJAX).
- [ ] Ekspor/Impor Excel produk, HP, pelanggan, nama handphone berjalan.
- [ ] Semua operasi CRUD menghasilkan soft-delete (kecuali nama handphone = hard delete).

## 7. Dependensi
- `AuthFilter` (auth), `ModelAuth`, `Core` (menu/sidebar), tabel `akun`, `jabatan`, `unit`, `barang`, `kategori`, `sub_kategori`, `pelanggan`, `suplier`, `fungsi`, `no_akun`, `bundle`, `detail_bundle`, `nama_handphone`, `bank`, regions.

## 8. Status & Tandai
- ✅ Fakta terverifikasi: mayoritas aturan di atas.
- ⚠️ Gap penting:
  1. Route `pegawai/delete_jabatan` menunjuk method yang tidak ada → 404. — `Routes.php:49`; `Pegawai.php` (MD-04)
  2. CRUD kategori master diarahkan ke `Kategori_Kas` dengan field form yang tidak cocok (`idnya`/`nama_kategori` vs `idkategori_kas`/`kategori`), sehingga berpotensi menulis ke tabel kategori kas atau gagal. — `Routes.php:416-419`; `Views/datamaster/kategori.php`; `Kategori_Kas.php` (MD-01)
  3. `Produk::insert_produk`, `Produk::update_produk`, dan `Produk::import_produk` memanggil `getLastBarangByKategori()` dengan satu argumen, padahal model membutuhkan dua → `ArgumentCountError` di PHP 8. — `Produk.php:71,138,276`; `ModelBarang.php:210` (MD-02)
  4. Typo `$THIS` pada `Pegawai::update` saat `jenis_pegawai == 100` → fatal. — `Pegawai.php:205` (MD-03)
  5. CRUD `Pegawai`, `Unit`, dan `Produk` tidak memeriksa role; setiap user login dapat mengubah pegawai/role/unit/produk. — route grup hanya `auth` (MD-05)
  6. Import Excel memakai SQL mentah + `addslashes` ⚠️ (belum diverifikasi ulang pada audit ini).
  7. Tidak ada test otomatis untuk alur master data di atas (kecuali penggunaan `ModelBank` pada test modul lain).
