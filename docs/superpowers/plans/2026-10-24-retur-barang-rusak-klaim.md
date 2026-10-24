# Retur Barang Rusak Klaim Garansi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan alur retur sparepart rusak hasil klaim garansi ke supplier, dengan HPP klaim hanya mengurangi omset pada bulan loss dan menu baru yang tidak mengubah `retur_suplier`.

**Architecture:** Status retur disimpan per baris `service_sparepart`. Loss omset disimpan di `hpp_omset_garansi` + `tanggal_hpp_klaim`, lalu dibaca oleh satu model `ModelHppKlaimLoss` untuk semua laporan. Menu retur baru memakai header `retur_klaim` + item `retur_klaim_item`, supplier manual, dan HPP manual.

**Tech Stack:** PHP 8, CodeIgniter 4, MySQL, PHPUnit 9, Bootstrap/DataTables.

**Spec:** `docs/superpowers/specs/2026-10-24-retur-barang-rusak-klaim-design.md`

**Issue:** https://github.com/bwafi/ERP-iClear/issues/38

## Global Constraints

- `hpp_penjualan_garansi` selalu HPP asli; tidak ada pilihan HPP 0.
- Pengurangan omset memakai `tanggal_hpp_klaim`: `tidak_bisa` = tanggal klaim, `ditolak` = tanggal retur, `diterima`/`bisa`/batal = NULL.
- `SRV420261001011` tetap HPP Rp 247.680 dan omset Rp 994.000.
- Menu `retur_suplier` dan stok tidak diubah.
- Satu retur = satu supplier = satu unit; satu transaksi boleh berisi banyak item.
- Unit scope memakai `GaransiClaimService::canSeeAllUnits((int) session('ID_JABATAN'))`.
- Laporan pendapatan bruto (`DashboardService`, `Home`) dan laporan berbasis jurnal tidak diubah.
- Test DB: `erp_test`, prefix `db_`; jalankan `php vendor/bin/phpunit <file>`.

## Review Focus

- Service September ditolak supplier Oktober: loss hanya di Oktober, September tidak berubah.
- Retur batal mengembalikan status `bisa`, HPP omset 0, dan `tanggal_hpp_klaim` NULL.
- Baris klaim yang sudah punya item retur tidak bisa dihapus lewat edit sparepart lain.
- Satu retur banyak item lintas unit harus ditolak.
- Ubah status `tidak_bisa` -> `bisa` menghapus loss; sebaliknya memunculkan loss di bulan klaim.

---

### Task 1: Schema, tabel retur, dan menu

**Files:**
- Create: `app/Database/Migrations/2026-10-24-000200_ReturBarangRusakKlaim.php`
- Test: `tests/ReturBarangRusakMigrationTest.php`

**Interfaces:**
- Produces columns `service_sparepart.status_retur`, `hpp_omset_garansi`, `tanggal_hpp_klaim`.
- Produces tables `retur_klaim`, `retur_klaim_item`.
- Produces menu `Retur Barang Rusak` dengan url `retur_barang_rusak`.

- [ ] **Step 1: Write the migration test**

Test membuat koneksi `tests`, memanggil migration `up()` dua kali, lalu assert:
- kolom `status_retur`, `hpp_omset_garansi`, `tanggal_hpp_klaim` ada di `db_service_sparepart`;
- tabel `db_retur_klaim` dan `db_retur_klaim_item` ada;
- baris klaim lama mendapat `status_retur='bisa'`, `hpp_omset_garansi=0`, `tanggal_hpp_klaim=NULL`;
- menu `retur_barang_rusak` ada tepat satu.

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/ReturBarangRusakMigrationTest.php`
Expected: FAIL karena migration belum ada.

- [ ] **Step 3: Implement migration**

Gunakan `CREATE TABLE IF NOT EXISTS`, cek kolom lewat `information_schema`, dan guard backfill. Menu id diambil dari `insertID()`, lalu merge ke `jabatan.ROLES_JABATAN` yang sudah punya menu `23` (`Retur Supplier`); fallback `ID_JABATAN = 1`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/ReturBarangRusakMigrationTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Database/Migrations/2026-10-24-000200_ReturBarangRusakKlaim.php tests/ReturBarangRusakMigrationTest.php
git commit -m "feat(retur): tambah skema retur barang rusak klaim"
```

### Task 2: Status retur di form klaim

**Files:**
- Modify: `app/Controllers/StatusGaransi.php:326,763-850`
- Modify: `app/Views/transaksi/table_garansi/claim_detail.php:175-370`
- Modify: `app/Models/ModelServiceSparepart.php:12-28`
- Modify: `tests/GaransiClaimSaveTest.php:65-140,283-380,560-620`

**Interfaces:**
- `simpanSparepartKlaim($db, object $service, array $produkData, \DateTimeInterface $claimTime): void`
- Produces `service_sparepart.status_retur` (`bisa`/`tidak_bisa`), `hpp_omset_garansi`, `tanggal_hpp_klaim`.

- [ ] **Step 1: Update the test table and claim tests**

Tambah kolom baru ke DDL `db_service_sparepart`. Ganti payload `hpp_mode` menjadi `status_retur`. Assert:
- `bisa`: `hpp_penjualan_garansi=4000`, `hpp_omset_garansi=0`, `tanggal_hpp_klaim=NULL`.
- `tidak_bisa`: `hpp_penjualan_garansi=4000`, `hpp_omset_garansi=4000`, `tanggal_hpp_klaim=tanggal klaim`.
- Sparepart Keluar tetap menampilkan HPP 4000 untuk kedua status.

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/GaransiClaimSaveTest.php`
Expected: FAIL karena kolom dan payload status belum didukung.

- [ ] **Step 3: Implement claim save and view**

Di `claim_garansi()` panggil `simpanSparepartKlaim($db, $service, $produkData, $wibTime)`. Di method itu, baca `status_retur`, selalu set HPP asli, lalu set HPP omset dan tanggal. Di view, ganti select HPP dengan select status dan tampilkan HPP asli sebagai teks. Saat membersihkan baris garansi yang tidak dipilih, reset juga tiga kolom baru.

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/GaransiClaimSaveTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Controllers/StatusGaransi.php app/Views/transaksi/table_garansi/claim_detail.php app/Models/ModelServiceSparepart.php tests/GaransiClaimSaveTest.php
git commit -m "feat(garansi): pilih status retur saat klaim"
```

### Task 3: Model loss dan laporan omset/laba

**Files:**
- Create: `app/Models/ModelHppKlaimLoss.php`
- Modify: `app/Controllers/Fee_Service.php`, `app/Controllers/RiwayatLabaService.php`
- Modify: `app/Views/riwayat/fee_service.php`, `app/Views/riwayat/laba_service.php`
- Modify: `app/Controllers/LabaRugi.php:215-270`, `app/Controllers/DashboardKeuangan.php:334-370`, `app/Controllers/DashboardLabaRugi.php:146-182`
- Modify: `app/Views/laporan/laba_rugi.php:425-440`, `app/Views/dashboard/dashboard_keuangan.php:270-280`, `app/Views/dashboard/dashboard_laba_rugi.php:215-222`
- Modify: `app/Services/Kpi/OmsetTeknisiCalculator.php:22-36`
- Test: `tests/unit/ModelHppKlaimLossTest.php`

**Interfaces:**
- `ModelHppKlaimLoss::getLoss($start, $end, $unitId = null, $teknisiId = null): array`
- `ModelHppKlaimLoss::getTotalLoss($start, $end, $unitId = null, $teknisiId = null): int`
- Row keys: `tanggal_hpp_klaim`, `no_service`, `nama_teknisi`, `nama_barang`, `hpp`, `qty`, `total`, `NAMA_UNIT`.

- [ ] **Step 1: Write the loss model test**

Seed `service`, `service_sparepart`, `barang`, `unit`, `akun`. Assert:
- `tidak_bisa` dengan `tanggal_hpp_klaim` September hanya muncul di September.
- `ditolak` dengan `tanggal_hpp_klaim` Oktober hanya muncul di Oktober.
- `diterima`/`bisa` tidak muncul.
- Filter unit dan teknisi bekerja.
- `getTotalLoss` = `hpp_omset_garansi * jumlah_tambahan_garansi`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/unit/ModelHppKlaimLossTest.php`
Expected: FAIL karena model belum ada.

- [ ] **Step 3: Implement `ModelHppKlaimLoss`**

Query `service_sparepart` join `service`, `akun`, `barang`, `unit`; filter `status_retur IN ('tidak_bisa','ditolak')`, `tanggal_hpp_klaim` di rentang, `jumlah_tambahan_garansi > 0`. `getTotalLoss` menjumlahkan `total` di PHP.

- [ ] **Step 4: Integrate reports**

- `Fee_Service`/`RiwayatLabaService` memuat loss bulan berjalan dan menampilkan section **HPP Klaim**; total laba/omset dikurangi total loss.
- Export Laba Service menambah sheet kedua **HPP Klaim** dengan kolom tanggal, no service, teknisi, barang, HPP, qty, total.
- `LabaRugi`, `DashboardKeuangan`, `DashboardLabaRugi` menambah `hpp_klaim` ke `biaya.total` dan baris view.
- `OmsetTeknisiCalculator` mengurangi `getTotalLoss($awal, $akhir, $unitId, $employeeId)`.

- [ ] **Step 5: Run tests**

Run: `php vendor/bin/phpunit tests/unit/ModelHppKlaimLossTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Models/ModelHppKlaimLoss.php app/Controllers/Fee_Service.php app/Controllers/RiwayatLabaService.php app/Views/riwayat/fee_service.php app/Views/riwayat/laba_service.php app/Controllers/LabaRugi.php app/Controllers/DashboardKeuangan.php app/Controllers/DashboardLabaRugi.php app/Views/laporan/laba_rugi.php app/Views/dashboard/dashboard_keuangan.php app/Views/dashboard/dashboard_laba_rugi.php app/Services/Kpi/OmsetTeknisiCalculator.php tests/unit/ModelHppKlaimLossTest.php
git commit -m "feat(laporan): kurangi omset dengan HPP klaim per bulan loss"
```

### Task 4: Menu dan alur Retur Barang Rusak

**Files:**
- Create: `app/Models/ModelReturBarangRusak.php`
- Create: `app/Controllers/ReturBarangRusak.php`
- Create: `app/Views/retur/retur_barang_rusak.php`
- Modify: `app/Config/Routes.php:310-316`
- Test: `tests/ReturBarangRusakTest.php`

**Interfaces:**
- `ReturBarangRusak::index()`, `simpan()`, `ubahStatus($id)`, `batal($id)`.
- `ModelReturBarangRusak::getKlaimBelumRetur($unitId, $awal, $akhir)`, `getRiwayat($unitId, $awal, $akhir)`, `getItems($id)`.
- Routes:
  - `GET retur_barang_rusak`
  - `POST retur_barang_rusak/simpan`
  - `POST retur_barang_rusak/ubah-status/(:num)`
  - `POST retur_barang_rusak/batal/(:num)`

- [ ] **Step 1: Write feature tests**

Seed service + claim row + supplier + unit. Test:
- simpan satu item `ditolak`: header/item tersimpan, status `ditolak`, HPP omset = HPP manual, `tanggal_hpp_klaim` = tanggal retur.
- simpan banyak item satu unit.
- hasil `diterima` mengosongkan HPP omset dan tanggal.
- item lintas unit ditolak.
- batal retur mengembalikan status `bisa` dan menghapus header/item.
- `ubahStatus` `tidak_bisa` -> `bisa` dan sebaliknya.
- baris yang sudah punya item retur tidak muncul di daftar.

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/ReturBarangRusakTest.php`
Expected: FAIL karena route/controller belum ada.

- [ ] **Step 3: Implement model and controller**

`index()` memfilter unit/tanggal, memuat supplier aktif, baris `bisa`/`tidak_bisa` tanpa item retur, dan riwayat. `simpan()` memakai transaksi, `SELECT ... FOR UPDATE`, validasi supplier/unit/HPP/status, nomor `RTB{unit}{Ymd}{3 digit}`, lalu update baris klaim. `batal()` mengembalikan status dan menghapus item/header. `ubahStatus()` hanya untuk baris tanpa item retur.

- [ ] **Step 4: Implement view and routes**

View berisi filter, tabel baris belum retur, form retur (supplier, tanggal, checkbox item, HPP manual, hasil, barang pengganti/qty, keterangan), tombol Ubah Status, dan riwayat retur dengan detail + batal.

- [ ] **Step 5: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/ReturBarangRusakTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Models/ModelReturBarangRusak.php app/Controllers/ReturBarangRusak.php app/Views/retur/retur_barang_rusak.php app/Config/Routes.php tests/ReturBarangRusakTest.php
git commit -m "feat(retur): menu retur barang rusak klaim"
```

### Task 5: Guard jalur edit sparepart lain

**Files:**
- Modify: `app/Controllers/StatusGaransi.php:489-620`
- Modify: `app/Controllers/Service.php:355-450`
- Modify: `app/Controllers/Riwayat_Service.php:348-450`
- Test: `tests/ReturBarangRusakGuardTest.php`

**Interfaces:**
- Consumes `retur_klaim_item.service_sparepart_idservice_sparepart`.
- Produces error message `Sparepart klaim sudah diretur; batalkan retur dulu.`

- [ ] **Step 1: Write guard test**

Buat baris klaim + item retur. Panggil jalur edit sparepart yang mencoba menghapus baris itu. Assert request gagal dan baris + item retur tetap ada.

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/ReturBarangRusakGuardTest.php`
Expected: FAIL karena baris masih bisa dihapus.

- [ ] **Step 3: Implement guard**

Sebelum menghapus baris `service_sparepart`, cek `retur_klaim_item`. Kalau ada, lempar `RuntimeException` dengan pesan di atas. Baris klaim baru dari jalur mana pun wajib `status_retur='bisa'`, `hpp_omset_garansi=0`, `tanggal_hpp_klaim=NULL`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/ReturBarangRusakGuardTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Controllers/StatusGaransi.php app/Controllers/Service.php app/Controllers/Riwayat_Service.php tests/ReturBarangRusakGuardTest.php
git commit -m "fix(garansi): cegah hapus baris klaim yang sudah diretur"
```

### Task 6: Verifikasi regresi

**Files:**
- Modify: `tests/GaransiClaimSaveTest.php` if needed.
- Modify: `docs/superpowers/specs/2026-10-24-retur-barang-rusak-klaim-design.md` status.

- [ ] **Step 1: Run related tests**

Run:
```bash
php vendor/bin/phpunit tests/GaransiClaimSaveTest.php tests/ReturBarangRusakTest.php tests/ReturBarangRusakGuardTest.php tests/unit/ModelHppKlaimLossTest.php tests/ReturBarangRusakMigrationTest.php
```
Expected: PASS.

- [ ] **Step 2: Run stock/garansi regression**

Run:
```bash
php vendor/bin/phpunit tests/StokBarangKurangiSparepartGaransiTest.php tests/unit/GaransiClaimServiceTest.php tests/GaransiClaimDetailPrefillTest.php tests/RiwayatServiceGaransiViewTest.php tests/unit/ModelServiceGaransiListingTest.php
```
Expected: PASS.

- [ ] **Step 3: Verify `SRV420261001011`**

Jalankan query manual di DB dev:
- `hpp_penjualan_garansi` baris klaim = 247680;
- `status_retur='bisa'`, `hpp_omset_garansi=0`, `tanggal_hpp_klaim=NULL`;
- omset service = 994000.

- [ ] **Step 4: Commit docs and final changes**

```bash
git add docs/superpowers/specs/2026-10-24-retur-barang-rusak-klaim-design.md docs/superpowers/plans/2026-10-24-retur-barang-rusak-klaim.md
git commit -m "docs: finalisasi rancangan retur barang rusak klaim"
```
