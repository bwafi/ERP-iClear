# Harga Jual per Unit — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Harga jual dapat diatur per produk+unit; POS dan Service memakai harga unit itu untuk transaksi baru, dengan fallback ke `barang.harga`, tanpa mengubah snapshot transaksi lama.

**Architecture:** Tambah tabel `harga_jual_unit` (mirip `hpp_unit`, satu baris per produk+unit; tanpa baris = ikut `barang.harga`) dan model `ModelHargaJualUnit`. Semua picker penjualan (POS, Service, Pembelian) me-resolve `COALESCE(hju.harga_jual, barang.harga)` untuk unit transaksi. Kartu Stok dan Pembelian menjadi pintu edit harga unit; harga master tetap diubah dari master Produk/Tarif Jasa. Snapshot `detail_penjualan.harga_penjualan` dan `service_sparepart.harga_penjualan` tidak disentuh. `hpp_unit` tidak diubah sama sekali.

**Tech Stack:** CodeIgniter 4.4.8, PHP 8.x, MySQLi, PHPUnit (`phpunit.xml`, database `erp_test`, prefix `db_`), Bootstrap 5 + jQuery/DataTables.

**Spec:** Tidak ada dokumen desain terpisah; requirement berasal dari percakapan 2026-11-10 dan PRD Modul 02/03/04 yang akan diperbarui di Task 6.

## Global Constraints

- Harga jual per unit hanya untuk transaksi **baru**. Baris `detail_penjualan` dan `service_sparepart` lama tidak di-backfill dan tidak ditulis ulang.
- `barang.harga` tetap harga master/default global. Tidak ada baris `harga_jual_unit` = ikut master.
- Harga jual dan HPP independen. Jangan menulis harga jual ke `hpp_unit`, dan jangan mengubah `ModelHppUnit`, `syncFromPembelian`, atau fallback HPP.
- Role edit harga jual tetap `Kartu_Stok::bolehKelolaProdukUnit()`: 0, 1, 34, 35, 41, 47. Role lain read-only di Pembelian dan 403 di endpoint.
- Nilai harga harus `> 0` dan `<= 9999999999999`; simpan `DECIMAL(15,2)`.
- Harga sama dengan master **menghapus baris override** (kembali mengikuti master), bukan menyimpan override yang kebetulan sama.
- Endpoint Kartu Stok memakai filter `['auth', 'csrf']`; validasi `id_unit` dan pasangan produk+unit (`ModelKartuStok::existsPair`).
- Jangan mengubah HPP, stok, jurnal, hutang-piutang, schema selain tabel baru, atau harga baris historis.
- UI edit harga per unit untuk **produk** ada di Kartu Stok dan Pembelian; untuk **jasa** ada di modal Ubah pada halaman Tarif Jasa (berlaku untuk unit akun login).
- CRM (`CrmLeadBridge::priceCatalog`) dan picker mutasi tidak diubah pada iterasi ini.
- Jangan `git commit` sampai user menginstruksikannya. Setiap task berakhir dengan test terarah, `php -l` pada file PHP yang diubah, dan `git -c core.whitespace=cr-at-eol diff --check`.
- Test hanya ke database test `erp_test` lewat `phpunit.xml`; jangan arahkan ke database nyata.

## Review Focus

- **HPP tidak tersentuh:** membuat/menghapus baris `harga_jual_unit` tidak boleh mengubah hasil `ModelHppUnit::resolve()`. Test di Task 1.
- **Baris lama tidak berubah:** setelah override/master diubah, harga yang sudah tersimpan di `detail_penjualan`/`service_sparepart` harus tetap. Test di Task 2.
- **Harga sama dengan master:** harus menghapus override agar perubahan master berikutnya ikut terpakai. Test di Task 1, 4, 5.
- **Unit lain tidak ikut berubah:** override unit 1 tidak boleh mengubah unit 2 atau `barang.harga`. Test di Task 1, 3, 4, 5.
- **Produk tanpa baris `harga_jual_unit`:** fallback ke master tanpa error, termasuk produk yang belum punya baris stok. Test di Task 1 dan 3.
- **Pembelian gagal di tengah:** harga invalid atau update override gagal harus membatalkan seluruh pembelian. Test invalid di Task 5.
- **Jasa:** picker jasa memakai override unit; halaman Tarif Jasa menulis override untuk unit akun login tanpa mengubah `barang.harga`. Test di Task 2 dan 6.

---

### Task 1: Tabel `harga_jual_unit` dan model resolver

**Files:**
- Create: `app/Database/Migrations/2026-11-10-000100_CreateHargaJualUnit.php`
- Create: `app/Models/ModelHargaJualUnit.php`
- Test: `tests/HargaJualUnitTest.php` (baru)

**Interfaces:**
- Produces:
  - `ModelHargaJualUnit::getFor(int $idbarang, int $idunit)` — baris override atau `null`.
  - `ModelHargaJualUnit::hargaJualUnit(int $idbarang, int $idunit): ?float` — nilai override atau `null`.
  - `ModelHargaJualUnit::resolve(int $idbarang, int $idunit): float` — override, fallback `barang.harga`, fallback `0.0`.
  - `ModelHargaJualUnit::setHarga(int $idbarang, int $idunit, float $harga, ?int $userId = null): bool` — upsert, nilai harus `> 0`.
  - `ModelHargaJualUnit::clear(int $idbarang, int $idunit): bool` — hapus baris override (kembali ke master).
- Memakai pola `ModelHppUnit::resolve()`, `setManual()`, dan prefix-safe raw SQL (`prefixTable`).

- [ ] **Step 1: Tulis test gagal**

Buat `tests/HargaJualUnitTest.php`. Buat/drop tabel `db_barang`, `db_harga_jual_unit`, `db_hpp_unit`, `db_hpp_barang` di `setUp/tearDown`. Seed `barang` idbarang 1 harga 150000 harga_beli 100000, dan `hpp_barang` hpp 90000.

```php
public function testTanpaOverrideMemakaiHargaMaster(): void
{
    $model = new ModelHargaJualUnit();
    $this->assertNull($model->hargaJualUnit(1, 1));
    $this->assertSame(150000.0, $model->resolve(1, 1));
}

public function testOverrideHanyaBerlakuUntukUnitItu(): void
{
    (new ModelHargaJualUnit())->setHarga(1, 1, 175000.0, 43);
    $model = new ModelHargaJualUnit();
    $this->assertSame(175000.0, $model->resolve(1, 1));
    $this->assertSame(150000.0, $model->resolve(1, 2));
    $this->assertSame(150000.0, (float) $this->db->table('barang')->where('idbarang', 1)->get()->getRow()->harga);
}

public function testClearMenghapusBarisOverride(): void
{
    $model = new ModelHargaJualUnit();
    $model->setHarga(1, 1, 175000.0, 43);
    $model->clear(1, 1);

    $this->assertNull($model->getFor(1, 1));
    $this->assertSame(150000.0, $model->resolve(1, 1));
}

public function testHargaJualTidakMengubahHpp(): void
{
    (new ModelHargaJualUnit())->setHarga(1, 1, 175000.0, 43);
    $this->assertSame(90000.0, (new ModelHppUnit())->resolve(1, 1), 'HPP tetap dari hpp_barang');
}
```

Tambahkan test migrasi: jalankan `CreateHargaJualUnit::up()`, assert `tableExists('harga_jual_unit')`, unique key menolak duplikat produk+unit, lalu `down()` dan assert tabel hilang.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/HargaJualUnitTest.php`
Expected: FAIL karena tabel/model belum ada.

- [ ] **Step 3: Implementasi migration dan model**

Migration `CreateHargaJualUnit` (pola `CreateHppUnit`):
- Reset `$this->db->dataCache['table_names'] = []` sebelum dan sesudah DDL.
- `id`, `barang_idbarang`, `unit_idunit`, `harga_jual DECIMAL(15,2)` (tidak null), `updated_by INT NULL`, `updated_at DATETIME NULL`.
- `addUniqueKey(['barang_idbarang', 'unit_idunit'], 'uk_harga_jual_unit_barang_unit')`.
- `down()` drop table. **Tidak ada backfill.**

Model:
- `protected $table = 'harga_jual_unit'`, `$allowedFields = ['barang_idbarang', 'unit_idunit', 'harga_jual', 'updated_by', 'updated_at']`.
- `getFor()` dan `hargaJualUnit()` query by pair.
- `resolve()` raw SQL:
  `SELECT COALESCE(hju.harga_jual, b.harga, 0) AS harga FROM barang b LEFT JOIN harga_jual_unit hju ON hju.barang_idbarang = b.idbarang AND hju.unit_idunit = ? WHERE b.idbarang = ?`
  memakai `prefixTable`.
- `setHarga()` menolak `$harga <= 0`, lalu `upsert` baris.
- `clear()` `delete` baris by pair (tidak menyentuh `barang`).

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/HargaJualUnitTest.php`
Expected: PASS.

- [ ] **Step 5: Lint dan diff check**

Run: `php -l app/Database/Migrations/2026-11-10-000100_CreateHargaJualUnit.php && php -l app/Models/ModelHargaJualUnit.php && php -l tests/HargaJualUnitTest.php && git -c core.whitespace=cr-at-eol diff --check`
Expected: tidak ada error.

---

### Task 2: Service picker memakai harga jual per unit

**Files:**
- Modify: `app/Controllers/Service.php` (`search_sparepart_ajax`, cabang `jasa` dan `sparepart`)
- Modify: `tests/ServiceSparepartJasaTest.php`
- Modify: `tests/ServiceSparepartSearchDedupTest.php`

**Interfaces:**
- Consumes: tabel `harga_jual_unit` dari Task 1.
- Produces: respons JSON picker tetap memakai field `harga`, tetapi nilainya `COALESCE(hju.harga_jual, b.harga)` untuk unit sesi. Tidak ada perubahan kontrak JS.

- [ ] **Step 1: Tambah fixture `harga_jual_unit` dan test gagal**

Di kedua test, tambahkan tabel `harga_jual_unit` (prefix sesuai file) dengan kolom `id`, `barang_idbarang`, `unit_idunit`, `harga_jual`, `updated_by`, `updated_at`. Seed override:

```php
// unit 1, barang 1 (sparepart)
INSERT INTO harga_jual_unit (barang_idbarang, unit_idunit, harga_jual) VALUES (1, 1, 175000);
// unit 1, barang 2 (jasa)
INSERT INTO harga_jual_unit (barang_idbarang, unit_idunit, harga_jual) VALUES (2, 1, 300000);
```

Test baru di `ServiceSparepartJasaTest`:
- `testSparepartPickerMemakaiHargaJualUnit`: POST `service/search_sparepart` dengan `jenis=sparepart`, cari kode sparepart, assert item `harga` = 175000.
- `testJasaPickerMemakaiHargaJualUnit`: POST dengan `jenis=jasa`, assert item jasa `harga` = 300000.
- `testJasaPickerFallbackHargaMaster`: untuk barang jasa tanpa override, assert `harga` = `barang.harga`.
- `testSnapshotServiceLamaTidakBerubahSaatOverrideBerubah`: seed baris `service_sparepart` dengan `harga_penjualan=150000` (langsung lewat query), ubah override unit menjadi 200000, assert baris lama tetap 150000.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/ServiceSparepartJasaTest.php`
Expected: FAIL karena join belum dipakai.

- [ ] **Step 3: Implementasi query**

- Cabang `jasa`: `LEFT JOIN harga_jual_unit hju ON hju.barang_idbarang = b.idbarang AND hju.unit_idunit = ?` (parameter `$unitId`), ganti `b.harga` di daftar select dengan `COALESCE(hju.harga_jual, b.harga) AS harga` (jangan select `b.harga` juga sebagai `harga`).
- Cabang sparepart: `LEFT JOIN harga_jual_unit hju ON hju.barang_idbarang = sb.idbarang AND hju.unit_idunit = sb.id_unit`, tambahkan select `COALESCE(hju.harga_jual, b.harga) AS harga_jual`, lalu di loop hasil set `$row->harga = (float) $row->harga_jual`.
- Pertahankan `hpp` dari `lampirkanHppPicker()`.

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/ServiceSparepartJasaTest.php`
Run: `vendor/bin/phpunit -c phpunit.xml tests/ServiceSparepartSearchDedupTest.php`
Expected: PASS keduanya.

- [ ] **Step 5: Lint dan diff check**

Run: `php -l app/Controllers/Service.php && php -l tests/ServiceSparepartJasaTest.php && php -l tests/ServiceSparepartSearchDedupTest.php && git -c core.whitespace=cr-at-eol diff --check`

---

### Task 3: Picker POS dan Pembelian memakai harga jual per unit

**Files:**
- Modify: `app/Models/ModelStokBarang.php` (`getAllBarang2`, `getJasa`)
- Modify: `app/Models/ModelBarang.php` (`getAllBarang`)
- Modify: `app/Views/transaksi/penjualan.php`
- Modify: `app/Views/transaksi/pembelian.php`
- Test: `tests/HargaJualPickerTest.php` (baru)
- Modify: `tests/PembelianPageTest.php`
- Modify fixture `harga_jual_unit`: `tests/BarangNonaktifUnitTest.php`, `tests/JasaBukanProdukTest.php`, `tests/MutasiStokPickerTest.php`, `tests/StokAwalReaktivasiTest.php`

**Interfaces:**
- Consumes: `harga_jual_unit`.
- Produces: objek produk/jasa dari model memiliki properti `harga_jual` berisi harga efektif unit. Properti `harga` tetap master.

- [ ] **Step 1: Tulis test gagal**

Buat `tests/HargaJualPickerTest.php`:
- Seed tabel minimal: `barang` (`idbarang`, `kode_barang`, `nama_barang`, `harga`, `harga_beli`, `deleted`, `jenis_barang`, `idkategori`, `status_ppn`), `kategori` (`id`, `nama_kategori`, `delete`), `harga_jual_unit`, `stok_barang` (`idbarang`, `id_unit`, `kode_barang`, `nama_barang`, `harga`, `harga_beli`, `stok_akhir`, `aktif_unit`), `unit`.
- Data: produk 1 harga 150000; jasa 2 harga 250000; `harga_jual_unit` barang 1 unit 1 = 175000, barang 2 unit 1 = 300000; `stok_barang` unit 1 stok 5.
- `testPosProdukMemakaiHargaUnit`: `ModelStokBarang::getAllBarang2()` untuk unit 1 → `harga_jual` 175000, `harga` 150000.
- `testPosJasaMemakaiHargaUnit`: `getJasa()` → `harga_jual` 300000.
- `testPembelianPickerMemakaiHargaUnit`: `ModelBarang::getAllBarang()` → `harga_jual` 175000.
- `testFallbackTanpaOverride`: barang tanpa baris `harga_jual_unit` → `harga_jual` = master.

Di `PembelianPageTest`, tambahkan `'harga_jual' => 175000` pada fixture produk dan assert halaman memuat `data-harga="175000"` (bukan 150000), serta memuat teks `Harga jual yang diubah berlaku untuk unit ini.`

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/HargaJualPickerTest.php`
Run: `vendor/bin/phpunit -c phpunit.xml tests/PembelianPageTest.php`
Expected: FAIL karena properti `harga_jual` belum ada/bernilai master.

- [ ] **Step 3: Implementasi model dan view**

- `ModelStokBarang::getAllBarang2`: `LEFT JOIN harga_jual_unit hju ON hju.barang_idbarang = barang.idbarang AND hju.unit_idunit = (int) $id_unit`, select `COALESCE(hju.harga_jual, barang.harga) AS harga_jual`; pertahankan `barang.harga`.
- `ModelStokBarang::getJasa`: `LEFT JOIN harga_jual_unit hju ON hju.barang_idbarang = barang.idbarang AND hju.unit_idunit = (int) session('ID_UNIT')`, select `COALESCE(hju.harga_jual, barang.harga) AS harga_jual`; pertahankan `harga`.
- `ModelBarang::getAllBarang`: join `harga_jual_unit hju` pada `$id_unit`, select `COALESCE(hju.harga_jual, barang.harga) AS harga_jual`; pertahankan `barang.*`.
- `penjualan.php`: `data-harga` dan kolom harga picker produk/jasa memakai `$p->harga_jual` / `$j->harga_jual`; bundle tetap `harga_jual` bundle.
- `pembelian.php`: card `data-harga` dan teks `HPP · jual Rp` memakai `$p->harga_jual`; ubah hint menjadi "Harga jual yang diubah berlaku untuk unit ini."

- [ ] **Step 4: Tambah fixture `harga_jual_unit`**

Tambahkan `CREATE TABLE ... harga_jual_unit` (dan drop di `tearDown`) pada setiap test yang memanggil query di atas: `BarangNonaktifUnitTest`, `JasaBukanProdukTest`, `MutasiStokPickerTest`, `StokAwalReaktivasiTest`. Tanpa ini test lama gagal karena join baru.

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/HargaJualPickerTest.php`
Run: `vendor/bin/phpunit -c phpunit.xml tests/PembelianPageTest.php`
Run: `vendor/bin/phpunit -c phpunit.xml tests/BarangNonaktifUnitTest.php`
Run: `vendor/bin/phpunit -c phpunit.xml tests/JasaBukanProdukTest.php`
Run: `vendor/bin/phpunit -c phpunit.xml tests/MutasiStokPickerTest.php`
Run: `vendor/bin/phpunit -c phpunit.xml tests/StokAwalReaktivasiTest.php`
Expected: PASS semua.

- [ ] **Step 6: Lint dan diff check**

Run: `php -l app/Models/ModelStokBarang.php && php -l app/Models/ModelBarang.php && php -l app/Views/transaksi/penjualan.php && php -l app/Views/transaksi/pembelian.php && php -l tests/HargaJualPickerTest.php && git -c core.whitespace=cr-at-eol diff --check`

---

### Task 4: Kartu Stok mengedit harga jual per unit

**Files:**
- Modify: `app/Models/ModelKartuStok.php` (`kartuStokBuilder`)
- Modify: `app/Controllers/Kartu_Stok.php` (`updateHargaJual`, `dt`)
- Modify: `app/Views/stok/kartu_stok.php`
- Modify: `tests/KartuStokHargaJualTest.php`

**Interfaces:**
- Consumes: `ModelHargaJualUnit::getFor/hargaJualUnit/resolve/set/clear` dari Task 1; `ModelKartuStok::existsPair`.
- Produces: `POST kartu_stok/harga-jual` menerima `idbarang`, `id_unit`, `harga`; `harga == barang.harga` menghapus override. Respons `{status, harga (efektif), harga_jual_unit (null|float), csrf}`.

- [ ] **Step 1: Tulis test gagal**

Ubah `tests/KartuStokHargaJualTest.php`:
- Setup `harga_jual_unit` + `stok_barang` dengan pasangan barang 1/unit 1 dan barang 1/unit 2. Untuk test daftar Kartu Stok, tambahkan tabel minimal `hpp_unit`, `hpp_barang`, `kategori`, `detail_pembelian`, `pembelian`, `suplier` (kolom mengikuti query `kartuStokBuilder`).
- `testDaftarKartuStokMemakaiHargaUnit`: seed override 175000; `ModelKartuStok::getKartuStokDT(10, 0)` → `harga=175000`, `harga_jual_unit=175000`, `harga_master=150000`.
- `testDaftarKartuStokTanpaOverride`: tanpa baris override → `harga=150000`, `harga_jual_unit=null`.
- `testRolePengelolaMenyimpanHargaUnit`: POST 175000 → baris `harga_jual_unit` 175000, `barang.harga` tetap 150000, respons `harga` 175000.
- `testHargaSamaDenganMasterMenghapusOverride`: seed override 175000, POST 150000 → baris override hilang, respons `harga` 150000.
- `testUnitLainTidakBerubah`: seed override unit 1, POST unit 2 200000 → unit 1 tetap, unit 2 200000.
- `testRoleLainDitolak`: jabatan 99 → 403, tidak ada baris berubah.
- `testHargaTidakValidDitolak`: `0` dan `-1` → 422.
- `testPasanganProdukUnitTidakAda`: `id_unit=99` → 404.
- `testProdukTidakDitemukan`: idbarang 999 → 404.
- `testCsrfWajib`: tanpa token → 403 (pola test CSRF yang ada).

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/KartuStokHargaJualTest.php`
Expected: FAIL karena `id_unit` diabaikan dan masih menulis `barang.harga`.

- [ ] **Step 3: Implementasi query dan endpoint**

- `kartuStokBuilder`: `LEFT JOIN harga_jual_unit hju ON hju.barang_idbarang = stok_barang.idbarang AND hju.unit_idunit = stok_barang.id_unit`; select `COALESCE(hju.harga_jual, stok_barang.harga) AS harga`, `hju.harga_jual AS harga_jual_unit`, `stok_barang.harga AS harga_master`.
- `Kartu_Stok::dt`: tambahkan `harga_jual_unit` (`null` bila kosong) dan `harga_master`.
- `updateHargaJual`: guard role; validasi `idbarang`, `id_unit`, `harga`; `existsPair` 404; ambil `barang.harga`; bila `harga == master` → `clear(...)`, selain itu `setHarga(...)`; respons harga efektif (`resolve`) + override + CSRF.
- View: modal menambah hidden `id_unit`, teks "Harga master: Rp …", hint "Isi sama dengan harga master untuk mengikuti master." Tombol `Ubah` mengirim `data-idunit`, `data-harga`, `data-harga-master`.

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/KartuStokHargaJualTest.php`
Expected: PASS.

- [ ] **Step 5: Lint dan diff check**

Run: `php -l app/Models/ModelKartuStok.php && php -l app/Controllers/Kartu_Stok.php && php -l app/Views/stok/kartu_stok.php && php -l tests/KartuStokHargaJualTest.php && git -c core.whitespace=cr-at-eol diff --check`

---

### Task 5: Pembelian menyimpan harga jual unit pembelian

**Files:**
- Modify: `app/Controllers/Pembelian.php` (`__construct`, `insert`)
- Modify: `tests/PembelianHppUnitTest.php`

**Interfaces:**
- Consumes: `ModelHargaJualUnit::resolve/setHarga/clear`.
- Produces: pembelian tidak lagi menulis `barang.harga`; harga jual yang berbeda dari master disimpan ke `harga_jual_unit` untuk unit pembelian (unit akun login).

- [ ] **Step 1: Tulis test gagal**

Di `PembelianHppUnitTest`:
- Tambah tabel `harga_jual_unit` (`id`, `barang_idbarang`, `unit_idunit`, `harga_jual`, `updated_by`, `updated_at`) dan pastikan seed barang harga 150000.
- Ganti `testHargaJualDariPembelianMenimpaMaster` menjadi:
  - `testHargaJualPembelianDisimpanPerUnit`: POST harga jual 175000 → `harga_jual_unit` unit 1 = 175000, `barang.harga` tetap 150000.
  - `testHargaJualPembelianTidakMengubahUnitLain`: seed unit 2; setelah POST unit 1, unit 2 tidak punya baris override.
  - `testHargaJualSamaDenganMasterMenghapusOverride`: seed override 175000; POST 150000 → baris override hilang.
  - `testHargaJualDiabaikanUntukRoleNonPengelola`: jabatan 99, POST 175000 → `barang.harga` 150000, `harga_jual_unit` tidak berubah, pembelian tetap tersimpan.
  - `testPembelianBatalBilaHargaJualTidakValid`: POST 0 → header/detail batal, `barang.harga` dan `harga_jual_unit` tetap.
- Pertahankan test HPP/frontliner yang sudah ada.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/PembelianHppUnitTest.php`
Expected: FAIL karena controller masih menulis `barang.harga` dan test belum punya tabel `harga_jual_unit` di setup.

- [ ] **Step 3: Implementasi controller**

- Tambah `use App\Models\ModelHargaJualUnit;`, properti `$HargaJualUnitModel`, dan instansiasi di `__construct`.
- Di `insert`, setelah `syncFromPembelian` berhasil:
  - Bila `$bolehUbahHargaJual` dan `(float) $produkharga !== (float) $databarang->harga`: `setHarga((int) $produk['id'], (int) $useridunit, (float) $produkharga, ...)`.
  - Bila `(float) $produkharga === (float) $databarang->harga`: `clear((int) $produk['id'], (int) $useridunit)`.
  - Bila gagal → `transRollback()` + flash "Harga jual gagal disimpan." + redirect.
- Hapus blok `$this->BarangModel->update($produk['id'], ['harga' => $produkharga])`.

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/PembelianHppUnitTest.php`
Expected: PASS (semua test lama + baru).

- [ ] **Step 5: Lint dan diff check**

Run: `php -l app/Controllers/Pembelian.php && php -l tests/PembelianHppUnitTest.php && git -c core.whitespace=cr-at-eol diff --check`

---

### Task 6: Tarif Jasa mengatur harga jual per unit

**Files:**
- Modify: `app/Controllers/TarifJasa.php` (`index`, `ubah`)
- Modify: `app/Views/jasa/tarif.php`
- Modify: `tests/TarifJasaPageTest.php`

**Interfaces:**
- Consumes: `ModelHargaJualUnit::resolve/setHarga/clear` dari Task 1; role guard `Kartu_Stok::bolehKelolaProdukUnit`.
- Produces: `POST tarif_jasa/ubah` menerima field opsional `harga_unit`; kosong atau sama dengan `barang.harga` menghapus override unit akun login, nilai lain menyimpan override unit itu. `barang.harga` tidak berubah kecuali dari field `harga` yang sudah ada.

- [ ] **Step 1: Tulis test gagal**

Di `tests/TarifJasaPageTest.php`:
- Tambah tabel `harga_jual_unit` (`id`, `barang_idbarang`, `unit_idunit`, `harga_jual`, `updated_by`, `updated_at`) dan seed unit akun login = 1.
- `testHalamanMenampilkanHargaUnitUntukRolePengelola`: GET `tarif_jasa` → memuat field `harga_unit` dan harga efektif unit.
- `testUbahMenyimpanOverrideUnit`: POST `tarif_jasa/ubah` dengan `harga_unit=175000` → baris `harga_jual_unit` unit 1 = 175000, `barang.harga` tetap nilai master.
- `testUbahDenganHargaMasterMenghapusOverride`: seed override 175000, POST `harga_unit` = master → baris override hilang.
- `testUbahRoleNonPengelolaTidakMenulisOverride`: jabatan 99, POST `harga_unit=175000` → tidak ada baris override, perubahan master tetap tersimpan.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/TarifJasaPageTest.php`
Expected: FAIL karena field dan penulisan override belum ada.

- [ ] **Step 3: Implementasi controller dan view**

- `index`: tambahkan `'can_manage_harga_jual' => Kartu_Stok::bolehKelolaProdukUnit((int) session('ID_JABATAN'))`; join `harga_jual_unit hju ON hju.barang_idbarang = barang.idbarang AND hju.unit_idunit = <session unit>` dan select `COALESCE(hju.harga_jual, barang.harga) AS harga_jual_unit`.
- `ubah`: setelah update master/fee, bila role pengelola dan `harga_unit` dikirim:
  - kosong atau `(int) $hargaUnit === (int) $harga` → `clear($id, session unit)`;
  - selain itu validasi `> 0` dan `<= 9999999999999`, lalu `setHarga(...)`; bila gagal, flash gagal dan redirect.
  - role non-pengelola: abaikan `harga_unit`.
- View: sel Harga Jual menampilkan `$j->harga_jual_unit` (efektif unit) dan penanda kecil bila berbeda dari master. Modal Ubah menampilkan field `harga_unit` hanya bila `can_manage_harga_jual`, dengan hint "Berlaku untuk unit akun Anda. Kosongkan atau samakan dengan harga master untuk mengikuti master."; tombol Ubah mengirim `data-harga-unit` untuk mengisi field.

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/TarifJasaPageTest.php`
Expected: PASS.

- [ ] **Step 5: Lint dan diff check**

Run: `php -l app/Controllers/TarifJasa.php && php -l app/Views/jasa/tarif.php && php -l tests/TarifJasaPageTest.php && git -c core.whitespace=cr-at-eol diff --check`

---

### Task 7: Perbarui PRD

**Files:**
- Modify: `docs/prd/modul/PRD_MODUL_03_Pembelian_Persediaan.md`
- Modify: `docs/prd/modul/PRD_MODUL_02_Penjualan_Piutang.md`
- Modify: `docs/prd/modul/PRD_MODUL_04_Service_Garansi.md`

Tanpa test aplikasi.

- [ ] **Step 1: Modul 03 rule 17**

Ganti aturan harga jual global dengan: tabel `harga_jual_unit` menyimpan override harga jual per produk+unit; tanpa baris = `barang.harga`. Kartu Stok dan Pembelian menulis override unit; harga sama dengan master menghapus baris; role guard dan rollback dipertahankan; tidak ada backfill dan transaksi lama tetap snapshot. Tegaskan `hpp_unit` terpisah dan tidak terpengaruh.

- [ ] **Step 2: Modul 02 (picker POS)**

Tambahkan di rule picker/penjualan: picker produk dan jasa memakai `COALESCE(harga_jual_unit.harga_jual, barang.harga)` untuk unit transaksi; `detail_penjualan.harga_penjualan` tetap snapshot; bundle tetap memakai `bundle.harga_jual`.

- [ ] **Step 3: Modul 04 (picker service)**

Tambahkan di rule 17: picker sparepart dan jasa memakai harga jual efektif unit (`harga_jual_unit.harga_jual` fallback `barang.harga`); `service_sparepart.harga_penjualan` tetap snapshot dan tidak di-backfill.

- [ ] **Step 3b: Modul 03/04 (UI jasa)**

Catat bahwa halaman Tarif Jasa dapat menyimpan harga jual per unit lewat modal Ubah untuk unit akun login; harga master tetap di `barang.harga`, dan kosong/sama dengan master menghapus override.

- [ ] **Step 4: Acceptance/status**

Tambahkan acceptance criterion: "Harga jual per unit dapat diatur dan dipakai transaksi baru POS/Service; transaksi lama tidak berubah." Tandai `✅` hanya setelah test Task 1–6 lulus, dengan menyebut `HargaJualUnitTest`, `HargaJualPickerTest`, `ServiceSparepartJasaTest`, `KartuStokHargaJualTest`, `PembelianHppUnitTest`, `TarifJasaPageTest`.

- [ ] **Step 5: Diff check**

Run: `git -c core.whitespace=cr-at-eol diff --check`
Expected: bersih.

---

### Task 8: Verifikasi akhir

**Files:** tidak ada perubahan kode baru.

- [ ] **Step 1: Jalankan seluruh test terdampak**

```bash
for f in tests/HargaJualUnitTest.php tests/HargaJualPickerTest.php \
         tests/ServiceSparepartJasaTest.php tests/ServiceSparepartSearchDedupTest.php \
         tests/KartuStokHargaJualTest.php tests/PembelianHppUnitTest.php \
         tests/PembelianPageTest.php tests/BarangNonaktifUnitTest.php \
         tests/JasaBukanProdukTest.php tests/MutasiStokPickerTest.php \
         tests/StokAwalReaktivasiTest.php tests/TarifJasaPageTest.php; do
  echo "== $f"
  vendor/bin/phpunit -c phpunit.xml "$f" 2>&1 | tail -4
done
```

Expected: setiap file `OK`.

- [ ] **Step 2: Lint semua file PHP yang diubah**

Run `php -l` untuk: migration baru, `ModelHargaJualUnit`, `Service`, `ModelStokBarang`, `ModelBarang`, `ModelKartuStok`, `Kartu_Stok`, `Pembelian`, view `penjualan`/`pembelian`/`kartu_stok`, dan semua test yang diubah.

- [ ] **Step 3: Periksa diff**

Run: `git -c core.whitespace=cr-at-eol diff --check && git diff --stat && git status --short`
Expected: hanya file yang direncanakan; `.mcp.json` tetap tidak di-stage; tidak ada `build/logs` yang ikut berubah (kembalikan dengan `git checkout -- build/logs` bila test menulisnya).

- [ ] **Step 4: Laporkan**

Laporkan hasil test aktual, risiko, dan status PRD. **Jangan commit** sampai user memerintahkan.
