# Pre-Claim Garansi Detail Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti aksi klaim langsung dengan halaman detail klaim yang menyimpan catatan klaim, sparepart pengganti (harga Rp 0, stok berkurang, HPP tercatat), dan sparepart rusak ke tabel baru tanpa mengurangi stok.

**Architecture:** Halaman baru `claim_garansi/detail/{idservice}` membaca service yang masih claimable. POST `claim_garansi` menyimpan semuanya dalam satu transaksi: baris klaim, catatan di `riwayat_claim_garansi`, baris `service_sparepart` untuk pengganti, dan `barang_rusak_claim` untuk parts rusak. Stok pengganti dihitung oleh view `stok_barang` dari `service_sparepart.jumlah_tambahan_garansi` milik service yang sudah diklaim. Tidak ada workbench dan tidak ada pembayaran.

**Tech Stack:** CodeIgniter 4, MySQL, Bootstrap/DataTables, PHPUnit.

**Spec:** Percakapan user 2026-10-23: halaman klaim baru, catatan klaim wajib, pengganti gratis namun stok turun dan HPP tercatat, barang rusak masuk tabel baru tanpa stok, setelah simpan kembali ke daftar Garansi Service.

## Global Constraints

- Sparepart pengganti selalu `harga_penjualan_garansi = 0`, `sub_total_garansi = 0`, `diskon_penjualan_garansi = 0`; HPP diambil dari `hpp_barang.hpp`.
- Sparepart rusak **tidak** boleh masuk ke perhitungan stok mana pun.
- Catatan klaim wajib tidak kosong.
- Klaim hanya sekali per service, dibungkus transaksi + `FOR UPDATE` pada baris `service`.
- Semua akses halaman dan simpan dibatasi unit (HO: jabatan 0, 1, 42).
- Setelah simpan sukses redirect ke `garansi_service`; tidak ada redirect ke `service_by_garansi`.

## Review Focus

- Service yang sudah diklaim atau kadaluarsa tidak boleh bisa dibuka/disimpan ulang.
- Qty pengganti melebihi stok atau sparepart beda unit harus gagal tanpa menyimpan klaim setengah jadi.
- Service normal yang sudah punya baris `service_sparepart` tidak boleh kehilangan qty/harga penjualan normalnya.
- Barang rusak klaim tidak boleh menggeser `stok_akhir`.
- Double-submit POST tidak boleh membuat dua riwayat atau dua baris barang rusak.

---

### Task 1: Schema, model, dan daftar barang rusak klaim

**Files:**
- Create: `app/Database/Migrations/2026-10-23-000300_AddCatatanToRiwayatClaimGaransi.php`
- Create: `app/Database/Migrations/2026-10-23-000400_CreateBarangRusakClaim.php`
- Create: `app/Database/Migrations/2026-10-23-000500_StokBarangKurangiSparepartGaransi.php`
- Create: `app/Models/ModelBarangRusakClaim.php`
- Modify: `app/Models/ModelRiwayatKlaimGaransi.php`
- Modify: `app/Controllers/BarangRusak.php`
- Modify: `app/Views/stok/barang_rusak.php`
- Test: `tests/StokBarangKurangiSparepartGaransiTest.php`

**Interfaces:**
- Produces: tabel `barang_rusak_claim` dengan kolom `idbarang_rusak_claim, service_idservice, barang_idbarang, jumlah, keterangan, unit_idunit, input_by, created_at`.
- Produces: kolom `riwayat_claim_garansi.catatan` (TEXT NULL).
- Produces: `ModelBarangRusakClaim::getAll(): array<object>`.
- Produces: view `stok_barang` dengan kolom tambahan `total_pemakaian_garansi`; `stok_akhir` dikurangi kolom itu.

- [ ] **Step 1: Tulis test view stok yang gagal**

Buat `tests/StokBarangKurangiSparepartGaransiTest.php` mengikuti pola `tests/StokBarangHanyaMutasiDiterimaTest.php`: set prefix `''`, buat tabel unprefixed `barang, unit, stok_awal, detail_pembelian, detail_penjualan, detail_mutasi, mutasi, retur_pelanggan, retur_suplier, barang_rusak, stok_opname, service, service_sparepart`. Seed stok awal 10 untuk barang 1 unit 1. Seed service 1 dengan `tanggal_claim_garansi='2026-10-20'` dan `service_sparepart` qty 2; service 2 belum diklaim dengan qty 3. Assert stok = 8. Tambah baris `barang_rusak` qty 1, assert stok = 7. Ubah qty claim jadi 5, assert stok = 4.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php vendor/bin/phpunit tests/StokBarangKurangiSparepartGaransiTest.php`
Expected: FAIL karena class migration belum ada / `total_pemakaian_garansi` belum ada.

- [ ] **Step 3: Buat tiga migration**

`AddCatatanToRiwayatClaimGaransi`: cek `SHOW COLUMNS ... LIKE 'catatan'`, lalu `ALTER TABLE ... ADD COLUMN catatan TEXT NULL AFTER tanggal_claim`.
`CreateBarangRusakClaim`: `CREATE TABLE IF NOT EXISTS` dengan kolom di Interfaces, primary key, index `service_idservice`, index `barang_idbarang`.
`StokBarangKurangiSparepartGaransi`: `CREATE OR REPLACE VIEW stok_barang` persis definisi migration `2026-10-19-000400`, tambah `0 AS total_pemakaian_garansi` di semua cabang union, tambah cabang:
```sql
SELECT service_sparepart.barang_idbarang AS idbarang,
       service_sparepart.unit_idunit AS idunit,
       0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian,
       0 AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk,
       0 AS total_retur_pelanggan, 0 AS total_retur_suplier,
       0 AS total_kerusakan,
       SUM(service_sparepart.jumlah_tambahan_garansi) AS total_pemakaian_garansi
FROM service_sparepart
JOIN service ON service.idservice = service_sparepart.service_idservice
WHERE service.tanggal_claim_garansi IS NOT NULL
  AND service_sparepart.jumlah_tambahan_garansi > 0
GROUP BY service_sparepart.barang_idbarang, service_sparepart.unit_idunit
```
Kurangi `combined_stocks.total_pemakaian_garansi` dari `stok_akhir` dan ekspos `SUM(combined_stocks.total_pemakaian_garansi) AS total_pemakaian_garansi`. `down()` memakai SQL yang sama tanpa kolom/cabang claim.

- [ ] **Step 4: Buat model dan update model riwayat**

`ModelBarangRusakClaim` dengan `$table='barang_rusak_claim'`, `$primaryKey='idbarang_rusak_claim'`, `$allowedFields` sesuai kolom, dan `getAll()` join `barang`, `service`, `pelanggan`, `unit`, `akun` (left join), order `idbarang_rusak_claim DESC`.
Tambahkan `'catatan'` ke `$allowedFields` `ModelRiwayatKlaimGaransi`.

- [ ] **Step 5: Tampilkan daftar barang rusak klaim di halaman Barang Rusak**

`BarangRusak::__construct` tambah `ModelBarangRusakClaim`; `index()` kirim `barang_rusak_claim`. Di `stok/barang_rusak.php` tambah card/table kedua setelah tabel lama dengan kolom: No Service, Tanggal, Kode, Nama Barang, Qty, Keterangan, Unit, Input By, Pelanggan. Jangan memakai id `zero_config`.

- [ ] **Step 6: Jalankan test view dan test lama**

Run: `php vendor/bin/phpunit tests/StokBarangKurangiSparepartGaransiTest.php tests/StokBarangHanyaMutasiDiterimaTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Database/Migrations app/Models/ModelBarangRusakClaim.php app/Models/ModelRiwayatKlaimGaransi.php app/Controllers/BarangRusak.php app/Views/stok/barang_rusak.php tests/StokBarangKurangiSparepartGaransiTest.php
git commit -m "feat: add claim damaged parts and warranty stock deduction"
```

---

### Task 2: Halaman detail klaim

**Files:**
- Modify: `app/Config/Routes.php`
- Modify: `app/Controllers/StatusGaransi.php`
- Modify: `app/Views/riwayat/garansi_service.php`
- Create: `app/Views/transaksi/table_garansi/claim_detail.php`

**Interfaces:**
- Consumes: `GaransiClaimService::isClaimable()`, `GaransiClaimService::warrantyEnd()`, `ModelStokBarang::getSparepart()`, `ModelBarang::getBarangSparepart()`, `ModelServiceKerusakan::getSerModelServiceKerusakanByServiceId()`.
- Produces: `GET claim_garansi/detail/(:num)` → `StatusGaransi::claim_detail($idservice)`.
- Produces: view variables `service`, `oldkerusakan`, `fungsiMap`, `sparepart`, `sparepart_rusak`, `warranty_end`, `status_garansi`.

- [ ] **Step 1: Tambah route GET**

Sebelum route POST `claim_garansi`, tambah:
```php
$routes->get('claim_garansi/detail/(:num)', 'StatusGaransi::claim_detail/$1', ['filter' => 'auth']);
```

- [ ] **Step 2: Tambah helper guard dan method `claim_detail`**

Di `StatusGaransi` tambah `private function claimableServiceForUser(int $idservice): ?object` yang mengambil `ModelService->getByIdWithPelanggan($idservice)`, menolak unit beda untuk non-HO, dan menolak `!GaransiClaimService::isClaimable($service)`.
`claim_detail($idservice)`:
- jika guard null → redirect `garansi_service` + flash gagal;
- ambil kerusakan lama dan map `idfungsi => nama_fungsi` dari `ModelKerusakan->getKerusakan()`;
- ambil sparepart stok unit dengan `stok_akhir > 0`, dan master sparepart `getBarangSparepart()` yang `deleted=0`;
- hitung `warranty_end` dan `status_garansi`;
- render `template` dengan `body = transaksi/table_garansi/claim_detail`.

- [ ] **Step 3: Ganti tombol daftar menjadi link**

Di `app/Views/riwayat/garansi_service.php`, tombol `Claim Garansi` yang aktif menjadi `<a href="<?= base_url('claim_garansi/detail') ?>/${row.idservice}" ...>`. Hapus modal `ClaimGaransiModal` dan handler `.btn-claim-garansi`; tombol disabled tetap.

- [ ] **Step 4: Buat view `claim_detail.php`**

Tampilkan card data service/pelanggan/device (read-only), daftar kerusakan lama, textarea `catatan` required, dua tabel input, dan tombol `Simpan Klaim`. Form `action="<?= base_url('claim_garansi') ?>" method="post"` + `csrf_field()`, hidden `idservice`.
Tabel pengganti: modal berisi `$sparepart` dengan checkbox `data-id`, `data-nama`, `data-stok`; baris tabel berisi hidden `produk[INDEX][id]`, input `produk[INDEX][qty]` min 1 max stok, tombol hapus. Tampilkan stok.
Tabel rusak: modal berisi `$sparepart_rusak` dengan checkbox `data-id`, `data-nama`; baris tabel berisi hidden `barang_rusak[INDEX][id]`, input qty, textarea `barang_rusak[INDEX][keterangan]`, tombol hapus.
Gunakan DataTable untuk kedua modal. Index row dimulai 0 dan naik; cegah duplikat id per tabel. Qty pengganti tidak boleh melebihi stok (JS).

- [ ] **Step 5: Cek halaman bisa dirender**

Run: `php -l app/Controllers/StatusGaransi.php && php -l app/Views/transaksi/table_garansi/claim_detail.php`
Expected: No syntax errors.

- [ ] **Step 6: Commit**

```bash
git add app/Config/Routes.php app/Controllers/StatusGaransi.php app/Views/riwayat/garansi_service.php app/Views/transaksi/table_garansi/claim_detail.php
git commit -m "feat: add warranty claim detail page"
```

---

### Task 3: Simpan klaim, catatan, pengganti, dan barang rusak

**Files:**
- Modify: `app/Controllers/StatusGaransi.php`
- Modify: `app/Models/ModelServiceSparepart.php` (hanya jika perlu method existing; utamakan method yang sudah ada)
- Test: `tests/GaransiClaimSaveTest.php`

**Interfaces:**
- Consumes: `claimableServiceForUser()`, `GaransiClaimService::isClaimable()`, `ModelBarangRusakClaim`, `ModelHppBarang`, `ModelStokBarang`, `ModelStokAwal`.
- Produces: `POST claim_garansi` menyimpan klaim; redirect `garansi_service` sukses atau `claim_garansi/detail/{id}` gagal.

- [ ] **Step 1: Tulis feature test yang gagal**

Buat `tests/GaransiClaimSaveTest.php` dengan `FeatureTestTrait`, prefix `db_`, tabel minimal: `db_service`, `db_riwayat_claim_garansi`, `db_service_sparepart`, `db_barang_rusak_claim`, `db_service_kerusakan`, `db_pelanggan`, `db_unit`, `db_stok_barang`, `db_akun`, `db_jabatan`, `db_barang`, `db_hpp_barang`, `db_stok_awal`, `db_proses_service`. Seed session `logged_in, ID_AKUN=43, ID_UNIT=1, ID_JABATAN=1`. Seed service status 4, `garansi_hari=30`, `tanggal_selesai='2026-10-01'`, unit 1; barang 10 stok 5 HPP 4000; barang 11 master rusak.
Test 1: POST dengan token CSRF baru, `catatan='LCD rusak karena pecah'`, `produk=[['id'=>10,'qty'=>2]]`, `barang_rusak=[['id'=>11,'qty'=>1,'keterangan'=>'LCD pecah']]`. Assert redirect, service `status_service=2`, `tanggal_claim_garansi` terisi, satu riwayat dengan catatan, `service_sparepart` qty 2 harga 0 HPP 4000, `barang_rusak_claim` satu baris qty 1.
Test 2: POST kedua ke service yang sama; assert tetap satu riwayat dan satu baris rusak.
Test 3: service lain, `catatan=''`; assert tidak ada riwayat dan status tetap 4.
Test 4: service lain, qty 99 dari stok 5; assert tidak ada klaim dan tidak ada baris `service_sparepart`.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php vendor/bin/phpunit tests/GaransiClaimSaveTest.php`
Expected: FAIL karena `claim_garansi` masih aksi lama / tabel belum lengkap.

- [ ] **Step 3: Tulis ulang `claim_garansi()`**

Validasi `idservice`, `catatan` wajib, array `produk` dan `barang_rusak`. Buka transaksi, `SELECT * FROM service WHERE idservice = ? FOR UPDATE`, guard null/unit/claimed/riwayat/claimable. Panggil helper penyimpanan, update service (`status_service=2`, `tanggal_bisa_diambil=NULL`, `tanggal_selesai=NULL`, `status_proses=1`, `tanggal_claim_garansi=WIB`, total garansi=0), hapus `proses_service`, insert `riwayat_claim_garansi` dengan `catatan`. Commit; redirect `garansi_service`. Catch → rollback + redirect detail dengan flash gagal.

- [ ] **Step 4: Implement helper `simpanSparepartKlaim`**

Loop `produk`; abaikan item invalid; dedupe id; qty > 0. Ambil HPP dan baris `stok_barang` unit; tolak jika tidak ada atau `stok_akhir < qty`. Untuk existing `service_sparepart` (service+barang) update hanya kolom garansi; untuk baru insert dengan kolom normal 0 (`jumlah`, `harga_penjualan`, `sub_total`, `diskon_penjualan`) dan kolom garansi `harga=0, sub_total=0, diskon=0, qty=qty, hpp=hpp, satuan_jual` dari `stok_awal`. Setelah loop, untuk baris existing yang tidak dipilih: hapus jika baris itu murni garansi (`jumlah=0 && harga_penjualan=0`), selain itu nolkan kolom garansi. Ini mencegah baris normal ikut terhapus.

- [ ] **Step 5: Implement helper `simpanBarangRusakKlaim`**

Loop `barang_rusak`; abaikan invalid; qty > 0. Pastikan `barang` ada dan tidak deleted (tidak cek stok). Insert ke `barang_rusak_claim` dengan `service_idservice`, `barang_idbarang`, `jumlah`, `keterangan` (trim), `unit_idunit`, `input_by=session('ID_AKUN')`, `created_at=WIB`.

- [ ] **Step 6: Jalankan test**

Run: `php vendor/bin/phpunit tests/GaransiClaimSaveTest.php tests/unit/GaransiClaimServiceTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Controllers/StatusGaransi.php tests/GaransiClaimSaveTest.php
git commit -m "feat: save warranty claim with note, replacement, and damaged parts"
```

---

### Task 4: Catatan di riwayat dan verifikasi akhir

**Files:**
- Modify: `app/Controllers/StatusGaransi.php`
- Test: tidak ada test baru; jalankan seluruh test terkait.

**Interfaces:**
- Consumes: `riwayat_claim_garansi.catatan`.

- [ ] **Step 1: Tampilkan catatan di modal riwayat**

Di `getRiwayatKlaim`, tambah kolom `Catatan` dan render `esc($row->catatan ?? '-')`.

- [ ] **Step 2: Jalankan test terkait**

Run:
```bash
php vendor/bin/phpunit tests/GaransiClaimSaveTest.php tests/StokBarangKurangiSparepartGaransiTest.php tests/StokBarangHanyaMutasiDiterimaTest.php tests/unit/GaransiClaimServiceTest.php
```
Expected: semua PASS.

- [ ] **Step 3: Cek syntax file yang diubah**

Run: `php -l app/Controllers/StatusGaransi.php && php -l app/Controllers/BarangRusak.php && php -l app/Models/ModelBarangRusakClaim.php && php -l app/Models/ModelRiwayatKlaimGaransi.php`
Expected: No syntax errors.

- [ ] **Step 4: Commit**

```bash
git add app/Controllers/StatusGaransi.php
git commit -m "feat: show claim note in warranty claim history"
```

## Out of Scope

- Pembayaran klaim dan jurnal Kas-Bank tidak ditambahkan karena halaman klaim baru tidak memiliki pembayaran (semua pengganti Rp 0).
- Workbench `service_by_garansi` tidak dipakai oleh alur baru.
