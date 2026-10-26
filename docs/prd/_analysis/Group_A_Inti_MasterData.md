# Analisis Kelompok A — Inti & Master Data

Analisis bersifat **hanya-baca** (read-only). Tidak ada source code, DB, maupun config yang diubah. File ini ditulis dari pembacaan kode di repo `/home/syro/Projects/ERP`.

---

## 1. Ruang Lingkup

**Controller (app/Controllers):**
| Controller | Peran |
|---|---|
| `Home` | Dashboard utama (beranda). |
| `Auth` | Login, logout, ganti password, pilih gedung/unit, getRoles (JSON), dark mode. |
| `Bank` | Menampilkan data bank (read-only di grup ini). |
| `Pegawai` | CRUD pegawai + CRUD jabatan + reset password. |
| `Unit` | CRUD unit (gedung/toko). |
| `Produk` | CRUD produk non-HP + export/import Excel + sub-kategori dropdown. |
| `Kategori` | CRUD kategori + sub-kategori. |
| `Phone` | CRUD handphone (barang kategori 1) + export/import + insert nama handphone ajax. |
| `NamaHandphone` | CRUD master nama handphone + export/import. |
| `Pelanggan` | CRUD pelanggan + pencarian + region + export/import + riwayat transaksi. |
| `Supplier` | CRUD supplier. |
| `Kerusakan` | CRUD "kerusakan" (tersimpan di tabel `fungsi`). |
| `NoAkun` | CRUD nomor akun (master akuntansi). |
| `Bundle` | CRUD bundle (paket produk). |

**Model yang dipakai:** `ModelAuth`, `ModelBank`, `ModelUnit`, `ModelBarang`, `ModelKategori`, `ModelSubKategori`, `ModelPhone`, `ModelNamaHandphone`, `ModelPelanggan`, `ModelSuplier`, `ModelKerusakan`, `ModelNoAkun`, `ModelBundle`, `ModelDetailBundle`, `ModelRegion`, `ModelPenjualan`, `Core`.

**Filter:** `AuthFilter` (didaftarkan sebagai alias `auth` di `app/Config/Filters.php`).

**Routes yang relevan:** `app/Config/Routes.php` baris 1–134 dan 391–394 dan 603–609 serta 668–674 (lihat Bagian 2).

---

## 2. Endpoint / Routes

| Method | Path | Controller::Method | Filter | Catatan |
|---|---|---|---|---|
| GET | `/` | `Home::index` | auth | Dashboard |
| GET | `/login` | `Auth::login` | — | |
| POST | `/proses_login` | `Auth::proses_login` | — | |
| GET | `/Logout` | `Auth::proses_logout` | — | |
| POST | `auth/changePassword` | `Auth::changePassword` | — | |
| GET | `/bank` | `Bank::index` | auth | read-only |
| GET | `pegawai` | `Pegawai::index` | auth | |
| POST | `pegawai/search` | `Pegawai::search` | auth | |
| POST | `pegawai/insert` | `Pegawai::insert` | auth | |
| POST | `pegawai/update` | `Pegawai::update` | auth | |
| POST | `pegawai/delete` | `Pegawai::delete` | auth | |
| GET | `pegawai/jabatan` | `Pegawai::jabatan` | auth | |
| POST | `pegawai/insert_jabatan` | `Pegawai::insert_jabatan` | auth | |
| POST | `pegawai/update_jabatan` | `Pegawai::update_jabatan` | auth | |
| POST | `pegawai/delete_jabatan` | `Pegawai::delete_jabatan` | auth | ⚠️ method tidak ada di controller |
| POST | `pegawai/reset` | `Pegawai::reset_password` | auth | |
| GET | `unit` | `Unit::index` | auth | |
| POST | `insert_unit` | `Unit::insert_unit` | auth | |
| POST | `update_unit` | `Unit::update_unit` | auth | |
| POST | `delete_unit` | `Unit::delete_unit` | auth | |
| GET | `produk` | `Produk::index` | auth | |
| GET | `produk/export/produk` | `Produk::export_produk` | auth | |
| POST | `produk/import/produk` | `Produk::import_produk` | auth | |
| POST | `produk/insert_produk` | `Produk::insert_produk` | auth | |
| POST | `produk/update_produk` | `Produk::update_produk` | auth | |
| POST | `produk/delete_produk` | `Produk::delete_produk` | auth | |
| GET | `produk/get_sub_kategori/(:any)` | `Produk::get_sub_kategori/$1` | auth | |
| GET | `/supplier` | `Supplier::index` | auth | |
| POST | `insert_suplier` | `Supplier::insert_suplier` | auth | |
| POST | `update_suplier` | `Supplier::update_suplier` | auth | |
| POST | `delete_suplier` | `Supplier::delete_suplier` | auth | |
| GET | `kategori` | `Kategori::index` | auth | |
| POST | `kategori/insert_sub_kategori` | `Kategori::insert_sub_kategori` | auth | |
| POST | `kategori/update_sub_kategori` | `Kategori::update_sub_kategori` | auth | |
| POST | `kategori/delete_sub_kategori` | `Kategori::delete_sub_kategori` | auth | |
| GET | `/phone` | `Phone::index` | auth | |
| POST | `insert_phone` | `Phone::insert_phone` | auth | |
| POST | `update_phone` | `Phone::update_phone` | auth | |
| POST | `delete_phone` | `Phone::delete_phone` | auth | |
| GET | `export/phone` | `Phone::export_phone` | auth | |
| POST | `import/phone` | `Phone::import_phone` | auth | |
| GET | `import_phone` | `Phone::menuimport_phone` | auth | |
| POST | `insert-phone-ajax` | `Phone::insertnamahandphone` | auth | |
| GET | `/pelanggan` | `Pelanggan::index` | auth | |
| POST | `insert_pelanggan` | `Pelanggan::insert_pelanggan` | auth | |
| POST | `update_pelanggan` | `Pelanggan::update_pelanggan` | auth | |
| POST | `delete_pelanggan` | `Pelanggan::delete_pelanggan` | auth | |
| GET | `export/pelanggan` | `Pelanggan::export_pelanggan` | auth | |
| POST | `import/pelanggan` | `Pelanggan::import_pelanggan` | auth | |
| POST | `simpan/pelanggan` | `Pelanggan::simpanPelanggan` | auth | AJAX |
| GET | `riwayat_transaksi_pelanggan/(:num)` | `Pelanggan::riwayat_transaksi_pelanggan/$1` | auth | |
| GET | `pelanggan/search` | `Pelanggan::searchPelanggan` | auth | |
| GET | `region/kabupaten/(:any)` | `Pelanggan::getKabupaten/$1` | — | ⚠️ tanpa auth |
| GET | `region/kecamatan/(:any)` | `Pelanggan::getKecamatan/$1` | — | ⚠️ tanpa auth |
| GET | `kerusakan` | `Kerusakan::index` | auth | |
| POST | `insert_kerusakan` | `Kerusakan::insert_kerusakan` | auth | |
| POST | `update_kerusakan` | `Kerusakan::update_kerusakan` | auth | |
| POST | `delete_kerusakan` | `Kerusakan::delete_kerusakan` | auth | |
| GET | `datamaster_akun` | `NoAkun::index` | auth | |
| POST | `insert_noakun` | `NoAkun::insert` | auth | |
| POST | `update_noakun` | `NoAkun::update` | auth | |
| GET | `bundle` | `Bundle::index` | auth | |
| GET | `input_bundle` | `Bundle::input` | auth | |
| POST | `insert_bundle` | `Bundle::insert` | auth | |
| GET | `edit_bundle/(:num)` | `Bundle::edit/$1` | auth | |
| POST | `update_bundle` | `Bundle::update` | auth | |
| POST | `delete_bundle` | `Bundle::delete` | auth | |

**Metode yang benar-benar memproses (bukan sekadar render view):**
- `Auth::proses_login`, `Auth::setGedung`, `Auth::getRoles`, `Auth::changePassword`, `Auth::proses_logout`, `Auth::proses_darkmode`
- `Pegawai::search/insert/update/delete/insert_jabatan/update_jabatan/reset_password`
- `Unit::insert_unit/update_unit/delete_unit`
- `Produk::insert_produk/update_produk/delete_produk/export_produk/import_produk/get_sub_kategori`
- `Kategori::insert_kategori/update_kategori/delete_kategori/insert_sub_kategori/update_sub_kategori/delete_sub_kategori`
- `Phone::insert_phone/update_phone/delete_phone/export_phone/import_phone/insertnamahandphone`
- `NamaHandphone::insertNamaHandphone/udpateNamaHandphone/deleteNamaHandphone/export_handphone/import_handphone`
- `Pelanggan::getKabupaten/getKecamatan/searchPelanggan/insert_pelanggan/update_pelanggan/delete_pelanggan/export_pelanggan/import_pelanggan/simpanPelanggan/riwayat_transaksi_pelanggan`
- `Supplier::insert_suplier/update_suplier/delete_suplier`
- `Kerusakan::insert_kerusakan/update_kerusakan/delete_kerusakan`
- `NoAkun::insert/update`
- `Bundle::insert/update/delete`

---

## 3. Alur Pengguna Utama & Alternatif

### Login (Auth)
1. User membuka `/login` (`Auth::login` merender `auth/login`).
2. Submit POST `/proses_login`. `Auth::proses_login` mengambil `username` & `password`.
3. `ModelAuth::cekUsername` mencari di tabel `akun` dengan **NOID ATAU KTP ATAU EMAIL** (`orWhere`).
4. Jika ditemukan dan `password_verify` cocok:
   - Ambil data jabatan (`get_nama_jabatan`).
   - Bangun sesi: `ID_AKUN, NAMA, NOID, KTP, EMAIL, ID_JABATAN, NAMA_JABATAN`.
   - Parse `ID_UNIT` (JSON). Jika array, ambil elemen pertama.
   - Set sesi unit: `ID_UNIT, NAMA_UNIT, logged_in=true, is_select_gedung=false`.
   - Redirect ke `/`.
5. Alternatif (multi-gedung): jika user harus memilih gedung, panggil `Auth::setGedung` (POST) — hanya menyimpan `ID_GEDUNG/NAMA_GEDUNG` dan tetap `logged_in=true`.
6. Jika password salah → flashdata `pesan_password`, redirect `/login`. Jika username tak ditemukan → flashdata `pesan_username`.
7. Logout: `proses_logout` menghancurkan sesi, redirect `/login`.

### Pegawai
- `index`: render daftar pegawai + unit + jabatan + roles + daftar akun (`getAkunPegawai`).
- `search`: filter pegawai per unit (POST), balas JSON.
- `insert`: upload foto KTP/KK ke `public/foto_ktp/` dan `public/foto_kk/`, lalu `ModelAuth::insert_akun`. Password default = hash dari NOID (cost 10). `TERIMA_GAJI` default 1 kecuali form mengirim `'0'`.
- `update`: perbarui akun; jika `jenis_pegawai == 100` set `STATUS_PEGAWAI=0` (⚠️ pakai `$THIS` — lihat Gap).
- `delete`: **soft delete** — set `STATUS_PEGAWAI='0'` dan `JENIS_PEGAWAI=100`.
- `reset_password`: set password ke `urbankeren` (BCRYPT).
- `jabatan`: render daftar jabatan + roles per jabatan (`getRolesJabatan`).
- `insert_jabatan/update_jabatan`: simpan `ROLES_JABATAN` sebagai JSON.

### Produk
- `index`: daftar produk (`getBarang` → exclude kategori 1/HP), kategori (kecuali id 7), sub-kategori, unit.
- `insert_produk`: sanitasi harga (buang `,` `.`), cari kategori by nama (`ModelKategori::getByName`), generate `kode_barang = kodeKategori + nomor_urut(2 digit)` dari `getLastBarangByKategori`. Insert ke `barang` (`status=1, deleted=0, nama_barang_id=0`).
- `update_produk`: jika kategori berubah, generate ulang kode (nomor urut di kategori baru).
- `delete_produk`: soft delete `deleted='1'`.
- `export_produk`: tulis file `.xlsx` via PhpSpreadsheet (header download).
- `import_produk`: baca Excel baris demi baris, insert langsung (SQL mentah, `addslashes`), skip bila kategori tidak ditemukan.
- `get_sub_kategori/(nama)`: JSON daftar sub-kategori milik kategori.

### Handphone (Phone & NamaHandphone)
- `Phone::index`: daftar HP status aktif (`getPhoneActive`), master nama handphone, sub-kategori parent=1, unit.
- `Phone::insert_phone`: resolve nama barang dari `nama_handphone`, cek **duplikasi IMEI** (model `where('imei',$imei)->first()`). Generate `kode_barang='HP'+nomor(2 digit)` via `getLastBarangByKategori(1)`. Insert ke `barang` dengan `idkategori=1, status='0', status_barang` dari kondisi.
- `Phone::update_phone`: cek IMEI duplikat **kecuali idbarang sendiri**. Update `barang`.
- `Phone::delete_phone`: soft delete `deleted='1'`.
- `Phone::export_phone` / `import_phone`: Excel (import pakai SQL mentah, mapping status_barang "bekas"→1, PPN→1).
- `Phone::insertnamahandphone` (AJAX): validasi wajib `nama,size,type`; cek kombinasi duplikat; insert `nama_handphone`; balas JSON.
- `NamaHandphone::index/insertNamaHandphone/udpateNamaHandphone/deleteNamaHandphone`: CRUD; **delete memakai hard delete** `Model->delete($id)`.
- `export_handphone/import_handphone`: Excel (import skip baris kosong).

### Pelanggan
- `index`: paginasi (25/halaman), pencarian (nama/hp/alamat/nik), data provinsi (`ModelRegion::getProvinces`).
- `getKabupaten/getKecamatan`: cascading region (tanpa auth).
- `searchPelanggan`: JSON `{id,text}` untuk select2 (limit 20).
- `insert_pelanggan/update_pelanggan/delete_pelanggan`: simpan; delete soft (`deleted='1'`); redirect balik ke halaman+search+page.
- `simpanPelanggan` (AJAX): validasi nama & hp wajib; **cek duplikat NIK dan No HP** (aktif saja); insert; balas JSON.
- `export_pelanggan/import_pelanggan`: Excel.
- `riwayat_transaksi_pelanggan/:id`: render transaksi (`ModelPenjualan::getByIdPelanggan`).

### Supplier / Kerusakan / NoAkun / Bundle
- Supplier: CRUD sederhana, soft delete (`deleted`), insert mengikat `unit_idunit = session('ID_UNIT')`.
- Kerusakan: CRUD ke tabel **`fungsi`** (kolom `nama_fungsi`); soft delete `deleted='1'`.
- NoAkun: CRUD `no_akun`; insert cek duplikasi `no_akun`.
- Bundle: `insert` buat `bundle` + detail per barang (`detail_bundle`). `update` sinkronisasi detail (update yg ada, insert baru, hapus yg dihilangkan). `delete` hapus bundle + detail (deleteByBundle).

---

## 4. Business Rules

### (a) Fakta Terverifikasi dari Kode

| # | Aturan | Bukti |
|---|---|---|
| A1 | Login menerima NOID / KTP / EMAIL sebagai username | `app/Models/ModelAuth.php::cekUsername()` (orWhere NOID/KTP/EMAIL) |
| A2 | Password diverifikasi dengan `password_verify` (hash) | `app/Controllers/Auth.php::proses_login()` baris 60 |
| A3 | Sesu login berisi ID_AKUN, NAMA, NOID, KTP, EMAIL, ID_JABATAN, NAMA_JABATAN + ID_UNIT/NAMA_UNIT + `logged_in=true` | `Auth.php::proses_login()` baris 75–101 |
| A4 | ID_UNIT di-simpan sebagai JSON; saat login diambil elemen pertama jika array | `Auth.php::proses_login()` baris 86–92 |
| A5 | Password pegawai baru = hash dari NOID (cost 10) | `Pegawai.php::insert()` baris 138 |
| A6 | Password reset pegawai = literal `urbankeren` | `Pegawai.php::reset_password()` baris 232 |
| A7 | Hapus pegawai = soft delete (`STATUS_PEGAWAI='0'`, `JENIS_PEGAWAI=100`) | `Pegawai.php::delete()` baris 217–222 |
| A8 | Kode produk = `kode_kategori + nomor_urut 2 digit`; nomor urut dari `MAX(SUBSTRING(kode_barang))` per kategori | `Produk.php::insert_produk()` baris 71–82; `ModelBarang.php::getLastBarangByKategori()` |
| A9 | Harga dibersihkan dari `,` dan `.` sebelum disimpan | `Produk.php` baris 56–57 |
| A10 | Hapus produk = soft delete `deleted='1'` | `Produk.php::delete_produk()` baris 317–319 |
| A11 | Produk list mengecualikan kategori id=1 (HP) dan kategori/barang yang dihapus | `ModelBarang.php::getBarang()` baris 16–22 |
| A12 | HP (kategori=1) generate kode `'HP'+nomor 2 digit` | `Phone.php::insert_phone()` baris 81–92 |
| A13 | IMEI harus unik saat insert maupun update (kecuali id sendiri) | `Phone.php::insert_phone()` baris 75–79; `update_phone()` baris 140–148 |
| A14 | HP baru disimpan dengan `status='0'` (menunggu, tidak langsung aktif), `status_barang` dari kondisi | `Phone.php::insert_phone()` baris 103–110 |
| A15 | Hapus HP = soft delete `deleted='1'` | `Phone.php::delete_phone()` baris 178–180 |
| A16 | Nama handphone: kombinasi `nama+size+type` wajib unik (AJAX) | `Phone.php::insertnamahandphone()` baris 328–364 |
| A17 | Hapus nama handphone = **hard delete** | `NamaHandphone.php::deleteNamaHandphone()` baris 93 |
| A18 | Pelanggan: list hanya `deleted='0'`; paginasi 25 + search | `ModelPelanggan.php::getPelanggan()` |
| A19 | Pelanggan (AJAX `simpanPelanggan`) menolak duplikat NIK dan No HP (aktif) | `Pelanggan.php::simpanPelanggan()` baris 295–321 |
| A20 | Pelanggan: hapus = soft delete `deleted='1'` | `Pelanggan.php::delete_pelanggan()` |
| A21 | Supplier baru diikat ke `unit_idunit = session('ID_UNIT')` | `Supplier.php::insert_suplier()` baris 45 |
| A22 | Supplier dihapus = soft delete `deleted='1'` | `Supplier.php::delete_suplier()` |
| A23 | Kerusakan tersimpan di tabel `fungsi` (kolom `nama_fungsi`); dihapus = soft delete `deleted='1'` | `ModelKerusakan.php` (table `fungsi`); `Kerusakan.php::delete_kerusakan()` |
| A24 | NoAkun: `no_akun` wajib unik saat insert | `NoAkun.php::insert()` baris 44–49 |
| A25 | Bundle: `harga_jual` = `harga_total`; detail menyimpan `barang_idbarang, jumlah, harga` | `Bundle.php::insert()` baris 68–87 |
| A26 | Update bundle menyinkronkan detail: update yang ada, insert baru, hapus yang tidak ada | `Bundle.php::update()` baris 124–160 |
| A27 | Hapus bundle menghapus bundle + detail | `Bundle.php::delete()` + `ModelDetailBundle::deleteByBundle()` |
| A28 | Semua perubahan master data hanya menulis ke DB; tidak ada transaksi SQL eksplisit | pemeriksaan semua controller/model |
| A29 | `BaseController::initController` menjalankan query notifikasi (stok min, service) pada setiap request | `BaseController.php` baris 70–110 |

### (b) Inferensi (dari pola, bukan verifikasi data)

| # | Aturan | Dasar |
|---|---|---|
| B1 | Form kategori di view masih menampilkan form insert/update/delete kategori, tetapi route-nya dikomentari → CRUD kategori **rusak** | `Routes.php` baris 89–91 (comment) vs `app/Views/datamaster/kategori.php` baris 152/184/214 |
| B2 | `Bank` di grup ini read-only (tidak ada route create/update/delete); modal pada view hanyalah sisa kopi dari kategori | `Bank.php` hanya `index`; `bank.php` form action `update_kategori` dll. |
| B3 | Import Excel memakai SQL mentah (`$db->query`) dengan `addslashes` — rentan terhadap escaping tidak konsisten; kolom diharapkan urut sesuai template | `Produk.php::import_produk`, `Phone.php::import_phone`, `NamaHandphone.php::import_handphone`, `Pelanggan.php::import_pelanggan` |
| B4 | Soft-delete dipakai hampir di mana-mana (produk, HP, pelanggan, supplier, kategori, kerusakan, unit? tidak) kecuali nama handphone (hard) | pola `deleted='1'` |
| B5 | Kode barang unik per kategori tetapi **tidak ada constraint unique** di model; regenerasi hanya dari MAX+1 → risiko duplikat bila ada penghapusan fisik | `ModelBarang::getLastBarangByKategori` |

### (c) Ketidakpastian

- **C1** Kode `kode_barang` pada `ModelBarang::getLastBarangByKategori($idkategori, $kode_kategori)` membutuhkan **2 argumen**, tetapi dipanggil `Produk.php` dengan **1 argumen** (`getLastBarangByKategori($idkategori)`). Akibat: `$kode_kategori` kosong → `strlen`=0 → `SUBSTRING(kode_barang,1)` mengambil semua karakter. Generasi nomor urut mungkin tidak benar per kategori. (Ketidakpastian: apakah ada overload/argumen default; dari kode yang dibaca tidak ada.)
- **C2** `Pegawai::update()` memakai `$THIS->AuthModel->update(...)` (huruf besar). Variabel PHP case-sensitive → `$THIS` undefined. Ketika `jenis_pegawai == 100`, baris ini berpotensi error/fatal. Tidak terverifikasi runtime.
- **C3** `ModelKerusakan` tidak memiliki method `update`; `Kerusakan::update_kerusakan` memakai inheritance `Model::update` (CI4) dengan field `nama_fungsi` dan `delete`. Field `delete` **tidak ada** di `allowedFields` (hanya `deleted`) → kemungkinan field `delete` diabaikan saat insert/update.
- **C4** `insert_produk` dan `update_produk` tidak memiliki validasi server-side untuk input kosong/angka; bergantung pada form.
- **C5** Otorisasi berbasis peran (role/menu) tidak diterapkan pada filter; hanya `logged_in` (lihat Bagian 5).

---

## 5. Validasi & Permission / Role

- **AuthFilter** (`app/Filters/AuthFilter.php::before`): hanya memeriksa `session()->get('logged_in') !== true` → redirect `/login`. Logika cek role/menu yang lebih ketat **dikomentari** di file yang sama (baris 25–52) dan tidak aktif.
- **Config/Filters.php**: alias `auth` = `AuthFilter`; tidak ada global `csrf`; filter dipasang **per-route/group**.
- **Role berbasis menu** dipakai untuk **menampilkan sidebar** saja (bukan penegakan):
  - `Core::get_role()` — gabungkan role user (`akun.ROLES`) + role jabatan (`jabatan.ROLES_JABATAN`) + default 1, lalu ambil menu.
  - `Core::get_menu_show()` — susun menu sidebar sesuai `show_menu=1`.
  - `Auth::getRoles()` — endpoint JSON untuk menu (hanya mengecek `ID_AKUN` & `ROLES`).
- **Kesimpulan**: auth hanya menegakkan "sudah login". **Tidak ada cek akses berbasis menu/role pada level route/controller.** Semua user login dapat memanggil semua route master data.
- Validasi data pada umumnya sangat minim; validasi eksplisit hanya ada di: duplikat IMEI (Phone), duplikat NIK/HP (simpanPelanggan), duplikat `no_akun` (NoAkun), kombinasi `nama+size+type` (NamaHandphone AJAX), dan validasi wajib nama/no_hp pada `simpanPelanggan`.

---

## 6. Perubahan Database / Transaksi / Efek Samping

- **Transaksi**: Tidak ada penggunaan `db->transStart()/transComplete()` di seluruh grup ini. Semua operasi (mis. insert bundle + detail) **tidak atomik** — gagal separuh bisa meninggalkan data parsial.
- **Soft delete** vs **hard delete**:
  - Soft (`deleted='1'` atau `STATUS_PEGAWAI='0'`): produk, HP, pelanggan, supplier, kategori, sub-kategori, kerusakan, pegawai.
  - Hard: nama handphone (`delete()`).
- **Efek samping file**: upload foto KTP/KK ditulis ke `public/foto_ktp/` dan `public/foto_kk/` (`Pegawai::insert/update`). Tidak ada penghapusan file lama saat update.
- **Efek samping kode**: `Produk::insert_produk`/`update_produk` dan `Phone` mengubah `kode_barang` berbasis MAX+1 per kategori.
- **BaseController** mengeksekusi beberapa query (stok minimum, service proses/bisa diambil/expired, info akun) di **setiap** request ke controller apa pun dari grup ini.
- Migrasi yang relevan dengan master data: `AddTerimaGajiToAkun`, `AddIsOperationalToUnit`, `AddIsTrialToUnit` (menambah kolom pada `akun`/`unit`).

---

## 7. Relasi Antar-modul

- **Pegawai → akun/jabatan/unit**: `ModelAuth::getAkunPegawai` join `jabatan` & `unit`.
- **Produk/HP → kategori & sub-kategori**: `barang.idkategori → kategori.id`; `barang.id_sub_kategori → sub_kategori.id`.
- **HP → nama_handphone**: `barang.nama_barang_id → nama_handphone.id`; kode selalu `HP...`, `idkategori=1`.
- **Pelanggan → Region**: `provinsi/kabupaten/kecamatan` diambil dari master `provinces/regencie/districts`.
- **Pelanggan → Penjualan**: `riwayat_transaksi_pelanggan` memakai `ModelPenjualan::getByIdPelanggan`.
- **Pelanggan → Service**: `ModelPelanggan::getPelangganWithService` join `service` (dipakai Home dashboard).
- **Supplier → unit**: `suplier.unit_idunit` diisi dari sesi.
- **Bundle → barang**: `bundle` + `detail_bundle` (JOIN `barang`, `stok_barang` di `ModelBundle::getBundleWithDetail`).
- **Unit/Home**: `Home::index` memakai `ModelUnit`, `ModelPenjualan`, `ModelService`, `ModelPelanggan`, `ModelPembayaranHutang`, `ModelKasKeluar`, `ModelKartuStok` (dashboard lintas modul).

---

## 8. Bukti Test

- **Tidak ada test langsung** untuk controller/model master data grup ini (Pegawai, Produk, Kategori, Supplier, Pelanggan CRUD, NoAkun, Kerusakan, NamaHandphone, Bundle).
- Test yang menyentuh `ModelBank` (bukan CRUD Bank): `tests/ServiceDpBankScopeTest.php`, `tests/BankRekeningValidatorEntitlementTest.php` (`getBankDalamScopeUnit`).
- `pelanggan` muncul sebagai **fixture** di test modul lain (mis. `ReturBarangRusakTest.php`, `GaransiClaimSaveTest.php`, `ServiceIntakeDpTest.php`).
- Tidak ada test untuk `Auth`, `AuthFilter`, `Core` di scope grup ini.

---

## 9. Gap / Konflik yang Ditemukan

1. **Route `Pegawai::delete_jabatan` → method tidak ada.** Route terdaftar (Routes.php baris 49) tapi `Pegawai.php` tidak punya method `delete_jabatan`. → 404/error saat dipanggil.
2. **CRUD Kategori putus.** View masih punya form `insert_kategori/update_kategori/delete_kategori`, namun route-nya dikomentari (Routes.php baris 89–91). Kategori hanya bisa diisi via DB, bukan UI.
3. **Mismatch argumen `getLastBarangByKategori`.** Model butuh 2 argumen, controller kirim 1 (Produk.php baris 71/138). Berpotensi kode produk salah dihitung.
4. **`$THIS` typo di `Pegawai::update()`** (baris 205) — berpotensi fatal saat `jenis_pegawai==100`.
5. **Field `delete` vs `deleted` pada Kerusakan.** Insert/update mengirim `delete`, tetapi `allowedFields` hanya memuat `deleted` → flag soft-delete tidak terset pada insert (bergantung default DB).
6. **Kurangnya otorisasi role.** `AuthFilter` hanya cek `logged_in`; cek role/menu dikomentari. Semua user login bisa akses semua route master data.
7. **Import Excel memakai SQL mentah + `addslashes`** (produk, HP, pelanggan, nama handphone) — tidak konsisten dan tidak pakai parameter binding.
8. **Tidak ada transaksi** untuk operasi multi-tabel (Bundle: bundle+detail) → risiko data parsial.
9. **Route `/region/*` dan `status_service` tanpa filter auth** — dapat diakses tanpa login.
10. **Hard delete nama handphone** vs pola soft-delete di master data lain → inkonsistensi, berisiko kehilangan data yang masih dipakai `barang.nama_barang_id`.
11. **`proses_login` tidak membatasi upaya** (tanpa rate-limit/lockout); `reset_password` memakai password default yang sama untuk semua.
12. **BaseController mengeksekusi query notifikasi di setiap request**, termasuk untuk master data yang tak membutuhkannya (overhead).
