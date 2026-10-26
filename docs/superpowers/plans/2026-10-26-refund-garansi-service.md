# Refund Garansi Service Implementation Plan

> **Revisi 2026-10-26:** Semantik refund diganti dari pembayaran klaim
> (`bayar_garansi`) menjadi uang service yang sudah dibayar
> (`dp_bayar + bayar`). Syarat berubah dari "sudah diklaim" menjadi "garansi
> masih aktif"; klaim tidak menghalangi. Langkah task di bawah adalah riwayat
> implementasi awal — ikuti spec revisi untuk perilaku yang berlaku.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tambah refund tunai/bank atas uang service yang sudah dibayar di `/garansi_service`, dengan keputusan kondisi sparepart per baris dan pengurangan KPI omset pada bulan refund.

**Architecture:** Satu service domain (`GaransiRefundService`) menangani transaksi atomik: kunci service, validasi, `kas_keluar` + jurnal seimbang, update `service_sparepart`/`barang_rusak_claim`, lalu metadata refund di `service`. Dampak KPI dibaca dari `service.refund_garansi_tanggal` lewat `ModelRefundGaransiLoss`, bukan dari query terpisah di tiap laporan.

**Tech Stack:** CodeIgniter 4.4.8, MySQLi, PHPUnit, Bootstrap 5, jQuery/DataTables.

**Spec:** `docs/superpowers/specs/2026-10-26-refund-garansi-service-design.md`

## Global Constraints

- Jangan jalankan `git commit`; AGENTS.md melarang commit sebelum instruksi eksplisit user.
- Semua nominal rupiah disimpan sebagai integer.
- POST refund wajib memakai filter `['auth', 'csrf']` dan `csrf_field()` di form.
- Scope unit divalidasi di server; `unit_id` dari request tidak pernah dipakai.
- Refund wajib satu transaksi DB atomik; kegagalan jurnal membatalkan seluruh batch.
- `/omset_bulanan` (arus kas) tidak diubah. `kas_keluar` refund sudah mengurangi net kas.
- Laba Rugi dan Dashboard tidak diubah pada iterasi ini.
- Test hanya memakai database dari `phpunit.xml` (`erp_test`), bukan `erp_local` atau produksi.
- Ikuti pola `ServiceDpService::refund()` untuk kas keluar, jurnal, dan conditional update.

## Review Focus

1. Refund ganda/request bersamaan: hanya satu `kas_keluar`, request kedua ditolak.
2. Refund bank tanpa referensi atau dengan rekening unit lain: ditolak sebelum ada kas keluar.
3. Baris sparepart tanpa pilihan kondisi atau sudah diretur supplier: seluruh refund ditolak tanpa perubahan stok parsial.
4. Barang rusak yang kerugiannya sudah tercatat: HPP lama tidak ditimpa; barang baik dengan kerugian tercatat ditolak.
5. Refund di bulan berbeda: KPI turun di bulan refund, bukan bulan service; stok kembali tepat satu kali.

---

### Task 1: Skema, template jurnal, dan view stok

**Files:**
- Create: `app/Database/Migrations/2026-10-26-000100_AddGaransiServiceRefund.php`
- Test: `tests/RefundGaransiMigrationTest.php`

**Interfaces:**
- Produces kolom `service`: `refund_garansi_idkas_keluar INT NULL`, `refund_garansi_tanggal DATE NULL`, `refund_garansi_metode VARCHAR(10) NULL`, `refund_garansi_idbank VARCHAR(20) NULL`, `refund_garansi_referensi VARCHAR(100) NULL`, `refund_garansi_alasan VARCHAR(255) NULL`, `refund_garansi_jumlah INT NOT NULL DEFAULT 0`, `refund_garansi_by INT NULL`, `refund_garansi_at DATETIME NULL`.
- Produces kolom `service_sparepart.jumlah_refund_garansi INT NOT NULL DEFAULT 0`.
- Produces template jurnal `refund_garansi_tunai` dan `refund_garansi_bank`.
- View `stok_barang` mengurangi `jumlah_tambahan_garansi - jumlah_refund_garansi`.

- [ ] **Step 1: Tulis test migrasi yang gagal**

Buat `tests/RefundGaransiMigrationTest.php`. Salin pola setup/teardown `tests/StokBarangKurangiSparepartGaransiTest.php` (tabel `barang`, `unit`, `mutasi`, `detail_mutasi`, `detail_pembelian`, `stok_awal`, `detail_penjualan`, `retur_pelanggan`, `retur_suplier`, `barang_rusak`, `barang_rusak_claim`, `stok_opname`, `service`, `service_sparepart`), lalu tambah tabel `template_jurnal` dan `no_akun`.

Test yang harus ada:

- `testMigrasiMenambahKolomRefundDanMengurangiStokSesuaiQtyRefund`
  - Sebelum migrasi: `service` hanya punya `idservice`, `tanggal_claim_garansi`; `service_sparepart` tanpa `jumlah_refund_garansi`.
  - Seed stok awal 10, service 1 sudah klaim, service 2 belum klaim, `service_sparepart` `(1, 1, 1, 2)` dan `(2, 1, 1, 3)`.
  - Jalankan `up()`; assert `stok_akhir = 8`.
  - `UPDATE service_sparepart SET jumlah_refund_garansi = 1 WHERE idservice_sparepart = 1`; assert `9`.
  - Set `jumlah_refund_garansi = 2`; assert `10`.
  - Assert `fieldExists('refund_garansi_at', 'service')` dan `fieldExists('jumlah_refund_garansi', 'service_sparepart')`.
- `testMigrasiMenyiapkanTemplateJurnalRefund`
  - Assert 4 baris `template_jurnal` untuk dua kode template.
  - `refund_garansi_tunai`: debet `2010302000`, kredit `1010101000`.
  - `refund_garansi_bank`: debet `2010302000`, kredit `1010102000`.

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/RefundGaransiMigrationTest.php`
Expected: FAIL karena class migrasi belum ada.

- [ ] **Step 3: Implementasi migrasi**

Buat migration dengan method:

```php
public function up()
public function down()
private function tambahKolom(string $table, string $column, string $definition): void
private function hapusKolom(string $table, string $column): void
private function tambahTemplate(string $kode, string $noAkun, string $namaAkun, string $debetKredit): void
private function viewSql(bool $denganRefund): string
```

- `tambahKolom` dan `tambahTemplate` idempotent lewat `information_schema.COLUMNS` dan `countAllResults()`.
- Union `service_sparepart` pada view memakai `SUM(service_sparepart.jumlah_tambahan_garansi - service_sparepart.jumlah_refund_garansi)`.
- `down()` mengembalikan view versi tanpa refund (salin SQL `2026-10-23-000500_StokBarangKurangiSparepartGaransi.php`), menghapus template, lalu menghapus kolom baru.

- [ ] **Step 4: Jalankan test sampai lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/RefundGaransiMigrationTest.php`
Expected: PASS.

- [ ] **Step 5: Cek sintaks**

Run: `php -l app/Database/Migrations/2026-10-26-000100_AddGaransiServiceRefund.php && php -l tests/RefundGaransiMigrationTest.php`
Expected: `No syntax errors detected`.

---

### Task 2: Refund service — tunai dan sparepart baik

**Files:**
- Create: `app/Services/Service/GaransiRefundService.php`
- Test: `tests/unit/GaransiRefundServiceTest.php`

**Interfaces:**
- Consumes: tabel/kolom Task 1.
- Produces: `GaransiRefundService::__construct(?BaseConnection $db = null)`.
- Produces: `GaransiRefundService::refund(int $idservice, string $metode, ?string $idbank, ?string $referensi, string $alasan, array $kondisi, int $inputBy, ?int $unitDiizinkan = null): array{idkas_keluar:int,jumlah:int,metode:string}`.
- Produces: `GaransiRefundService::refundSiap(): bool`.
- `$kondisi` adalah map `idservice_sparepart => 'baik'|'rusak'`.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/unit/GaransiRefundServiceTest.php`, namespace `Tests\unit`. Pakai `Tests\Support\Entitlement\EntitlementFixture` untuk tabel bank, lalu buat tabel `db_pelanggan`, `db_service`, `db_service_sparepart`, `db_barang_rusak_claim`, `db_retur_klaim_item`, `db_kategori_kas`, `db_kas_keluar`, `db_jurnal`, `db_template_jurnal`.

Seed minimal:
- service 1: unit 1, `status_service = 4`, `tanggal_selesai` bulan ini, `tanggal_claim_garansi` terisi, `bayar_garansi = 500000`, `service_by = 7`, pelanggan 1.
- service_sparepart 1: `jumlah_tambahan_garansi = 2`, `hpp_penjualan_garansi = 4000`, `status_retur = 'bisa'`, `hpp_omset_garansi = 0`.
- kategori kas `REFUND` id 17, `no_akun 2010302000`, template `refund_garansi_tunai` + `refund_garansi_bank`.
- `$_SESSION['ID_JABATAN'] = Fase1::ROLE_KASIR` dan `$_SESSION['ID_AKUN'] = 43`.

Test:

- `testRefundTunaiMengembalikanSparepartBaikDanMencatatKasJurnal`
  - `refund(1, 'tunai', null, null, 'Pelanggan membatalkan', [1 => 'baik'], 43, 1)`.
  - Hasil: `jumlah = 500000`, `idkas_keluar > 0`, `metode = 'tunai'`.
  - `kas_keluar`: 1 baris, `jumlah = 500000`, `jenis = 'debet'`, `no_akun = '2010302000'`, `kategori_idkategori = 17`, `idbank = null`, `idunit = 1`.
  - `jurnal` dengan `tabel_referensi = 'kas_keluar'` dan `id_referensi = idkas_keluar`: tepat 2 baris; debet `2010302000` = 500000, kredit `1010101000` = 500000.
  - `service`: `refund_garansi_jumlah = 500000`, `refund_garansi_metode = 'tunai'`, `refund_garansi_tanggal = date('Y-m-d')`, `refund_garansi_at` tidak null, `refund_garansi_by = 43`.
  - `service_sparepart`: `jumlah_refund_garansi = 2`, `status_retur = 'bisa'`, `hpp_omset_garansi = 0`.
  - `barang_rusak_claim` kosong.

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/GaransiRefundServiceTest.php`
Expected: FAIL karena class `GaransiRefundService` belum ada.

- [ ] **Step 3: Implementasi jalur tunai + baik**

Implementasi mengikuti urutan `ServiceDpService::refund()`:

1. Validasi input dasar (`idservice > 0`, metode `tunai|bank`, alasan wajib maks 255, referensi maks 100).
2. `refundSiap()`; jika false lempar `RuntimeException`.
3. `transBegin()`, lock `service` + `pelanggan` dengan `FOR UPDATE`.
4. Tolak jika service tidak ada, belum diklaim, `bayar_garansi <= 0`, sudah direfund, atau unit di luar `$unitDiizinkan`.
5. Ambil `service_sparepart` dengan `jumlah_tambahan_garansi > 0` `FOR UPDATE`.
6. Untuk task ini, lempar `RuntimeException('Metode bank belum didukung.')` bila `metode === 'bank'`, dan `RuntimeException('Kondisi rusak belum didukung.')` bila ada kondisi `rusak`.
7. Setiap baris wajib punya kondisi; `jumlah_refund_garansi = jumlah_tambahan_garansi` untuk `baik`.
8. Insert `kas_keluar` (pola DP refund) lalu `ModelJurnal::insertJurnal()` dengan template `refund_garansi_tunai`.
9. Cek `ringkasByReferensi()`: 2 baris, seimbang, debet = kredit = jumlah.
10. Update `service` dengan semua field refund; jika `affectedRows() !== 1` lempar error.
11. `transCommit()` dan kembalikan array hasil.

- [ ] **Step 4: Jalankan test sampai lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/GaransiRefundServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Cek sintaks**

Run: `php -l app/Services/Service/GaransiRefundService.php`
Expected: `No syntax errors detected`.

---

### Task 3: Refund service — metode bank

**Files:**
- Modify: `app/Services/Service/GaransiRefundService.php`
- Modify: `tests/unit/GaransiRefundServiceTest.php`

**Interfaces:**
- Memakai `BankRekeningValidator::validateUntukUnit($idbank, $unitId, $role, false)`.
- Template bank `refund_garansi_bank`; kredit `1010102000`.

- [ ] **Step 1: Tulis test bank yang gagal**

Tambahkan:

- `testRefundBankMemakaiRekeningUnitDanReferensi`
  - `refund(1, 'bank', Fase1::IDBANK_CV, 'TRX-123', 'Refund transfer', [1 => 'baik'], 43, 1)`.
  - `kas_keluar.idbank = Fase1::IDBANK_CV`.
  - Jurnal kredit `1010102000` = 500000.
  - `service.refund_garansi_idbank = Fase1::IDBANK_CV`, `refund_garansi_referensi = 'TRX-123'`.
- `testRefundBankTanpaReferensiDitolak`
  - `refund(..., 'bank', Fase1::IDBANK_CV, '', ...)` melempar `RuntimeException` yang mengandung `Referensi`.
- `testRefundBankRekeningUnitLainDitolak`
  - `refund(1, 'bank', Fase1::IDBANK_SABRINA, 'TRX-9', ..., 43, 1)` melempar error; `kas_keluar` tetap 0.

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/GaransiRefundServiceTest.php --filter Bank`
Expected: FAIL karena bank belum didukung.

- [ ] **Step 3: Implementasi jalur bank**

- Referensi wajib bila `metode === 'bank'`.
- Validasi `idbank` dengan `BankRekeningValidator`, role dari `session('ID_JABATAN')`, `$bolehKosong = false`.
- Gagal validasi: lempar `RuntimeException` berisi alasan validator.
- Pilih template `refund_garansi_bank`; isi `idbank` pada `kas_keluar` dan metadata `service`.
- `jenis` kas keluar tetap `debet`.

- [ ] **Step 4: Jalankan test sampai lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/GaransiRefundServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Cek sintaks**

Run: `php -l app/Services/Service/GaransiRefundService.php`
Expected: `No syntax errors detected`.

---

### Task 4: Refund service — sparepart rusak, guard bisnis, dan kunci sekali refund

**Files:**
- Modify: `app/Services/Service/GaransiRefundService.php`
- Modify: `tests/unit/GaransiRefundServiceTest.php`

**Interfaces:**
- Memakai `retur_klaim_item` dan `service_sparepart.status_retur`.
- Menulis `barang_rusak_claim` untuk kondisi `rusak`.
- `service.refund_garansi_at` menjadi kunci idempotensi.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan:

- `testRefundRusakMencatatBarangRusakDanHppDiBulanRefund`
  - Kondisi `[1 => 'rusak']`.
  - Seed `service_sparepart`: `jumlah_refund_garansi = 0`, `status_retur = 'bisa'`, `hpp_omset_garansi = 0`, `tanggal_hpp_klaim = null`.
  - Assert `status_retur = 'tidak_bisa'`, `hpp_omset_garansi = 4000`, `tanggal_hpp_klaim = date('Y-m-d')`.
  - `barang_rusak_claim` 1 baris: `barang_idbarang = 10`, `jumlah = 2`, `keterangan` berawalan `Refund:`, `unit_idunit = 1`.
- `testRefundRusakTidakMenimpaHppLama`
  - Seed `status_retur = 'tidak_bisa'`, `hpp_omset_garansi = 4000`, `tanggal_hpp_klaim = '2026-10-20'`.
  - Refund rusak; nilai HPP dan tanggal lama tidak berubah.
- `testRefundDitolakBilaBarisSudahReturSupplier`
  - Insert `retur_klaim_item` untuk sparepart 1; refund melempar error; `kas_keluar = 0`.
- `testBarangBaikDitolakBilaKerugianSudahTercatat`
  - Seed `status_retur = 'tidak_bisa'`, `hpp_omset_garansi = 4000`; kondisi `baik` ditolak.
- `testRefundKeduaDitolak`
  - Refund pertama sukses; refund kedua melempar error; `kas_keluar` tetap 1.
- `testKondisiSparepartWajibLengkap`
  - `$kondisi = []` ditolak; `kas_keluar = 0`.
- `testServiceBelumDiklaimDitolak`
  - `tanggal_claim_garansi = null`; ditolak.
- `testServiceTanpaPembayaranDitolak`
  - `bayar_garansi = 0`; ditolak.
- `testUnitDiLuarWewenangDitolak`
  - `unitDiizinkan = 2` untuk service unit 1; ditolak.

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/GaransiRefundServiceTest.php --filter 'Rusak|Ditolak|Wajib|Kedua|Belum|Tanpa|Unit'`
Expected: FAIL pada kasus rusak dan guard.

- [ ] **Step 3: Implementasi guard dan jalur rusak**

- Kumpulkan ID baris retur supplier:
  - `status_retur = 'diterima'`, atau
  - ada baris `retur_klaim_item` dengan `service_sparepart_idservice_sparepart` = ID baris.
  - Jika ada, lempar `RuntimeException('Sparepart klaim sudah diretur ke supplier.')`.
- `baik`: tolak jika `hpp_omset_garansi > 0` atau `status_retur IN ('tidak_bisa','ditolak')`.
- `rusak`:
  - `jumlah_refund_garansi = 0`.
  - Jika `status_retur` belum `tidak_bisa`/`ditolak`: set `tidak_bisa`.
  - Jika `hpp_omset_garansi = 0`: isi `hpp_omset_garansi = hpp_penjualan_garansi` dan `tanggal_hpp_klaim = tanggal refund`.
  - Jika kerugian sudah tercatat: biarkan HPP dan tanggal lama.
  - Insert `barang_rusak_claim` dengan `keterangan = 'Refund: ' . $alasan`, `input_by = $inputBy`, `created_at = waktu`.
- Kondisi wajib lengkap: jumlah baris kondisi harus sama dengan jumlah baris sparepart; nilai hanya `baik|rusak`.
- Kunci refund: update `service` dengan syarat `refund_garansi_at IS NULL` dan `refund_garansi_idkas_keluar IS NULL`; `affectedRows() !== 1` → lempar error.
- Rollback seluruh transaksi pada setiap `Throwable`.

- [ ] **Step 4: Jalankan seluruh test service**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/GaransiRefundServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Cek sintaks**

Run: `php -l app/Services/Service/GaransiRefundService.php`
Expected: `No syntax errors detected`.

---

### Task 5: Endpoint, route, modal, dan tombol refund

**Files:**
- Modify: `app/Config/Routes.php`
- Modify: `app/Controllers/StatusGaransi.php`
- Modify: `app/Models/ModelService.php`
- Modify: `app/Views/riwayat/garansi_service.php`
- Create: `app/Views/transaksi/table_garansi/refund_form.php`
- Create: `app/Views/riwayat/_refund_modal.php`
- Modify: `tests/unit/ModelServiceGaransiListingTest.php`
- Modify: `tests/GaransiServiceViewTest.php`

**Interfaces:**
- GET `garansi_service/refund/detail/(:num)` → `StatusGaransi::refund_detail/$1`, filter `auth`.
- POST `garansi_service/refund` → `StatusGaransi::refund`, filter `['auth', 'csrf']`.
- JSON detail: `{"html": "<form ...>"}`.
- Form fields: `idservice`, `metode`, `idbank`, `referensi`, `alasan`, `kondisi[<idservice_sparepart>]`.
- Produces: `ModelService::hasRefundedGaransi($idservice): bool` (dipakai Task 6).

- [ ] **Step 1: Tulis test view/listing yang gagal**

Di `tests/GaransiServiceViewTest.php` tambahkan assertion:

```php
$this->assertStringContainsString('id="modalRefundGaransi"', $html);
$this->assertStringContainsString('btn-refund', $html);
$this->assertStringContainsString('garansi_service/refund', $html);
```

Di `tests/unit/ModelServiceGaransiListingTest.php`:

- Tambah kolom `bayar_garansi INT DEFAULT 0`, `refund_garansi_at DATETIME NULL`, `refund_garansi_jumlah INT DEFAULT 0` pada `CREATE TABLE service`.
- Tambah `testListingMengembalikanStatusRefund`:
  - Set `bayar_garansi = 500000`, `refund_garansi_at = null` untuk SRV-001.
  - Assert hasil memuat `bayar_garansi = 500000` dan `refund_garansi_at = null`.

Di `tests/GaransiServiceViewTest.php` tambahkan juga (class perlu `use CodeIgniter\Test\FeatureTestTrait;`):

```php
public function testRouteRefundTerdaftar(): void
{
    // Tanpa sesi, filter auth mengalihkan. 404 berarti route belum terdaftar.
    $this->post('garansi_service/refund')->assertRedirect();
    $this->get('garansi_service/refund/detail/1')->assertRedirect();
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/GaransiServiceViewTest.php tests/unit/ModelServiceGaransiListingTest.php`
Expected: FAIL karena modal/kolom belum ada.

- [ ] **Step 3: Tambah route dan data listing**

- Tambah dua route di dekat route `garansi_service` (`app/Config/Routes.php:210-214`).
- Di `ModelService::getGaransiServiceServerSide()` tambah `service.bayar_garansi`, `service.refund_garansi_at`, `service.refund_garansi_jumlah` ke SELECT.
- Di `StatusGaransi::garansi_service_ajax()` setelah `$row->claimed`:
  - `$row->refunded = !empty($row->refund_garansi_at);`
  - `$row->can_refund = $row->claimed && (int) ($row->bayar_garansi ?? 0) > 0 && ! $row->refunded;`
- Tambah `ModelService::hasRefundedGaransi($idservice): bool`: `fieldExists('refund_garansi_at', 'service')` lalu `refund_garansi_at IS NOT NULL`.

- [ ] **Step 4: Tambah controller dan view**

- `StatusGaransi::refund_detail(int $idservice)`:
  - `$service = $this->claimedServiceForUser($idservice)`; null → JSON `html` alert info.
  - `$sparepart = filter(getSerModelServiceSparepartByServiceId(), jumlah_tambahan_garansi > 0)`.
  - `$bank = $this->BankModel->getBankDalamScopeUnit((int) $service->unit_idunit)`.
  - `$refunded = $this->ServiceModel->hasRefundedGaransi($idservice)`.
  - Return `setJSON(['html' => view('transaksi/table_garansi/refund_form', [...])])`.
- `StatusGaransi::refund()`:
  - Ambil `idservice`, `metode`, `idbank`, `referensi`, `alasan`, `kondisi`.
  - `$unitDiizinkan = $this->canSeeAllUnits() ? null : (int) session('ID_UNIT')`.
  - Panggil `(new GaransiRefundService())->refund(...)`.
  - Sukses/gagal → redirect `garansi_service` dengan flash.
- `refund_form.php`: ringkasan `no_service`, pelanggan, `bayar_garansi`; radio metode; select rekening; referensi; alasan; tabel sparepart dengan radio kondisi wajib.
- `_refund_modal.php`: modal `id="modalRefundGaransi"` + script delegated `.btn-refund` untuk fetch detail, dan toggle field bank.
- Di kolom aksi `garansi_service.php`, tambah tombol:
  - `refunded` → tombol disabled `Sudah Refund`.
  - `can_refund` → tombol `.btn-refund` dengan `data-idservice`.
  - lainnya → tidak ada tombol.

- [ ] **Step 5: Jalankan test sampai lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/GaransiServiceViewTest.php tests/unit/ModelServiceGaransiListingTest.php`
Expected: PASS.

- [ ] **Step 6: Cek sintaks**

Run: `php -l app/Controllers/StatusGaransi.php && php -l app/Models/ModelService.php && php -l app/Views/transaksi/table_garansi/refund_form.php && php -l app/Views/riwayat/_refund_modal.php`
Expected: `No syntax errors detected`.

---

### Task 6: Kunci service setelah refund

**Files:**
- Modify: `app/Models/ModelService.php`
- Modify: `app/Controllers/StatusGaransi.php`
- Modify: `app/Controllers/ReturBarangRusak.php`
- Modify: `tests/ReturBarangRusakTest.php`
- Modify: `tests/unit/ModelServiceGaransiListingTest.php`

**Interfaces:**
- Consumes: `ModelService::hasRefundedGaransi($idservice): bool` dari Task 5.
- Guard memakai `service.refund_garansi_at`.

- [ ] **Step 1: Tulis test guard yang gagal**

Di `tests/ReturBarangRusakTest.php`:

- Tambah `refund_garansi_at DATETIME NULL`, `refund_garansi_jumlah INT DEFAULT 0` ke `db_service`; `jumlah_refund_garansi INT DEFAULT 0` ke `db_service_sparepart`.
- `testSimpanReturDitolakSetelahRefund`: set `refund_garansi_at`; post retur; assert `retur_klaim` kosong dan `status_retur` tetap `bisa`.
- `testUbahStatusDitolakSetelahRefund`: set refund; post ubah status; assert status tidak berubah.
- `testBatalReturDitolakSetelahRefund`: seed retur header+item; set refund; post batal; assert header+item masih ada.
- `testEditSparepartGaransiDitolakSetelahRefund`: set refund; post `update_service_garansi/saveSparepart`; assert `jumlah_tambahan_garansi` tidak berubah.
- `testTambahPembayaranGaransiDitolakSetelahRefund`: set refund; post `update_service_garansi/savePembayaran`; assert tidak ada perubahan `bayar_garansi`.

Di `tests/unit/ModelServiceGaransiListingTest.php` tambahkan:

- `testHardDeleteDitolakSetelahRefund`: set `refund_garansi_at`; `hardDeleteService(1)` mengembalikan `false` dan service masih ada.

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/ReturBarangRusakTest.php --filter 'Refund'`
Expected: FAIL karena guard belum ada.

- [ ] **Step 3: Implementasi guard**

- `ModelService::hardDeleteService`: tolak jika `hasRefundedGaransi()` true (log warning + return false).
- `StatusGaransi::insert_sparepart_garansi` dan `insert_pembayaran_garansi`: setelah `claimedServiceForUser()` valid, tolak jika `hasRefundedGaransi()`.
- Di `insert_sparepart_garansi`, tolak juga baris `service_sparepart` dengan `jumlah_refund_garansi > 0` supaya qty baris yang sudah direfund tidak pernah ditimpa.
- `ReturBarangRusak::simpan`: tambah `s.refund_garansi_at` ke SELECT dan tolak bila terisi.
- `ReturBarangRusak::lockSparepart`: tambah `s.refund_garansi_at`; `ubahStatus` menolak bila terisi.
- `ReturBarangRusak::batal`: sebelum loop item, query `retur_klaim_item` join `service_sparepart` join `service`; jika ada `refund_garansi_at`, tolak.

- [ ] **Step 4: Jalankan test sampai lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/ReturBarangRusakTest.php tests/unit/ModelServiceGaransiListingTest.php`
Expected: PASS.

- [ ] **Step 5: Cek sintaks**

Run: `php -l app/Models/ModelService.php && php -l app/Controllers/StatusGaransi.php && php -l app/Controllers/ReturBarangRusak.php`
Expected: `No syntax errors detected`.

---

### Task 7: Pengurang KPI omset pada bulan refund

**Files:**
- Create: `app/Models/ModelRefundGaransiLoss.php`
- Modify: `app/Services/Kpi/OmsetTokoCalculator.php`
- Modify: `app/Services/Kpi/OmsetCabangCalculator.php`
- Modify: `app/Services/Kpi/OmsetTeknisiCalculator.php`
- Modify: `app/Services/Finance/OmsetDailyCalculator.php`
- Modify: `app/Controllers/TutupKasir.php`
- Test: `tests/unit/ModelRefundGaransiLossTest.php`
- Test: `tests/unit/GaransiRefundKpiTest.php`

**Interfaces:**
- `ModelRefundGaransiLoss::getTotal(string $start, string $end, ?int $unitId = null, ?int $teknisiId = null): int`
- `ModelRefundGaransiLoss::getByDateRange(string $start, string $end, ?int $unitId = null): array<string,int>`

- [ ] **Step 1: Tulis test model yang gagal**

Buat `tests/unit/ModelRefundGaransiLossTest.php` dengan tabel `service` berisi `refund_garansi_tanggal`, `refund_garansi_jumlah`, `unit_idunit`, `service_by`.

Seed:

- 2026-10-05, unit 1, teknisi 7, 100000
- 2026-10-20, unit 1, teknisi 8, 50000
- 2026-11-01, unit 1, teknisi 7, 70000
- 2026-10-10, unit 2, teknisi 7, 25000

Assert:

- `getTotal('2026-10-01', '2026-10-31') === 175000`
- `getTotal('2026-10-01', '2026-10-31', 1) === 150000`
- `getTotal('2026-10-01', '2026-10-31', 1, 7) === 100000`
- `getByDateRange('2026-10-01', '2026-10-31', 1) === ['2026-10-05' => 100000, '2026-10-20' => 50000]`
- Kolom belum ada → `getTotal` mengembalikan `0` dan `getByDateRange` mengembalikan `[]`.

- [ ] **Step 2: Jalankan test model untuk memastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/ModelRefundGaransiLossTest.php`
Expected: FAIL karena model belum ada.

- [ ] **Step 3: Implementasi model**

- Tabel `service`; filter `refund_garansi_tanggal BETWEEN ? AND ?`, `refund_garansi_jumlah > 0`.
- Filter unit `unit_idunit`; filter teknisi `service_by`.
- `getByDateRange` group by `refund_garansi_tanggal`, hasil map tanggal → total.
- Guard `fieldExists('refund_garansi_tanggal', 'service')` mengembalikan nilai netral.

- [ ] **Step 4: Jalankan test model sampai lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/ModelRefundGaransiLossTest.php`
Expected: PASS.

- [ ] **Step 5: Tulis test integrasi KPI yang gagal**

Buat `tests/unit/GaransiRefundKpiTest.php`. Gunakan unit ID berbeda per test method agar static memo tidak bocor.

Skenario utama:
- `penjualan` unit 1 tanggal `2026-10-03`; `detail_penjualan` `sub_total = 1000000`, `hpp_penjualan = 200000`.
- `service` id 10: unit 1, `status_service = 4`, `service_by = 7`, `tanggal_selesai = '2026-10-04'`, `harus_dibayar = 500000`.
- Sebelum refund: `OmsetTokoCalculator` = `OmsetCabangCalculator` = 800000; `OmsetTeknisiCalculator` = 500000.
- Set `refund_garansi_jumlah = 100000`, `refund_garansi_tanggal = '2026-10-25'` pada service 10.
- Assert Toko/Cabang = 700000; Teknisi = 400000.
- Tambah refund 50000 pada `2026-11-02`; assert nilai Oktober tidak berubah.
- Tambah `service_sparepart` untuk service 10 dengan `status_retur = 'tidak_bisa'`, `hpp_omset_garansi = 10000`, `tanggal_hpp_klaim = '2026-10-25'`, `jumlah_tambahan_garansi = 1`; assert Teknisi = 390000, Toko/Cabang tetap 700000.
- `OmsetDailyCalculator::calculateDaily(1, '2026-10-25')` = `-100000` (tidak ada penjualan hari itu).
- `calculateDailyByRange(1, '2026-10-01', '2026-10-31')` memuat `2026-10-25 => -100000` dan `2026-10-03 => 800000`.

- [ ] **Step 6: Jalankan test integrasi untuk memastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/GaransiRefundKpiTest.php`
Expected: FAIL karena calculator belum mengurangi refund.

- [ ] **Step 7: Implementasi integrasi**

- `OmsetTokoCalculator`: total gross profit dikurangi `getTotal($startDate, $endDate, (int) $unitId)` sebelum disimpan ke memo.
- `OmsetCabangCalculator`: sama.
- `OmsetTeknisiCalculator`: kurangi `getTotal($startDate, $endDate, $unitId, (int) $employeeId)` setelah HPP loss.
- `OmsetDailyCalculator::calculateDaily`: kurangi `getTotal($date, $date, $unitId)`.
- `OmsetDailyCalculator::calculateDailyByRange`: ambil `getByDateRange($startDate, $endDate, $unitId)`, kurangi per tanggal, dan masukkan tanggal refund yang tidak punya penjualan.
- `TutupKasir` sekitar baris 588: setelah pengurangan `ModelHppKlaimLoss`, kurangi `(new ModelRefundGaransiLoss())->getTotal(date('Y-m-d'), date('Y-m-d'), (int) $unit)`.

- [ ] **Step 8: Jalankan test sampai lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/ModelRefundGaransiLossTest.php tests/unit/GaransiRefundKpiTest.php`
Expected: PASS.

- [ ] **Step 9: Cek sintaks**

Run: `php -l app/Models/ModelRefundGaransiLoss.php && php -l app/Services/Kpi/OmsetTokoCalculator.php && php -l app/Services/Kpi/OmsetCabangCalculator.php && php -l app/Services/Kpi/OmsetTeknisiCalculator.php && php -l app/Services/Finance/OmsetDailyCalculator.php && php -l app/Controllers/TutupKasir.php`
Expected: `No syntax errors detected`.

---

### Task 8: Dokumentasi dan verifikasi akhir

**Files:**
- Modify: `docs/superpowers/specs/2026-10-26-refund-garansi-service-design.md`
- Modify: `docs/prd/modul/PRD_MODUL_04_Service_Garansi.md`
- Modify: `docs/prd/modul/PRD_MODUL_05_Keuangan_KasBank_Aset.md`
- Modify: `docs/prd/modul/PRD_MODUL_08_KPI_Penilaian.md`
- Modify: `docs/prd/PRD_MASTER.md`

- [ ] **Step 1: Ubah status desain menjadi disetujui**

Ganti `Status: draft untuk persetujuan` menjadi `Status: disetujui 2026-10-26 (diimplementasikan)`.

- [ ] **Step 2: Perbarui PRD**

- PRD 04: tambah `### 4.8 Refund Klaim Garansi` berisi nominal otomatis `bayar_garansi`, metode tunai/bank, kondisi sparepart per baris, kunci sekali refund, dan guard retur supplier.
- PRD 05: tambah catatan bahwa refund garansi dicatat sebagai `kas_keluar` kategori `REFUND` + jurnal `refund_garansi_tunai`/`refund_garansi_bank`.
- PRD 08: tambah aturan bahwa KPI Omset Toko/Cabang/Teknisi dikurangi `service.refund_garansi_jumlah` pada bulan `refund_garansi_tanggal`.
- PRD_MASTER bagian 3.5: tambah satu bullet bahwa refund garansi mengurangi KPI omset pada bulan refund dan HPP rusak tetap memakai `hpp_omset_garansi`.

Jangan menaikkan status `✅` tanpa bukti test yang sudah dijalankan.

- [ ] **Step 3: Jalankan seluruh test terkait**

Run:

```bash
vendor/bin/phpunit -c phpunit.xml tests/RefundGaransiMigrationTest.php tests/unit/GaransiRefundServiceTest.php tests/unit/ModelRefundGaransiLossTest.php tests/unit/GaransiRefundKpiTest.php tests/unit/ModelServiceGaransiListingTest.php tests/GaransiServiceViewTest.php tests/ReturBarangRusakTest.php tests/GaransiClaimSaveTest.php
```

Expected: PASS semua.

- [ ] **Step 4: Verifikasi diff**

Run:

```bash
git diff --check
git diff --stat
```

Expected: tidak ada whitespace error; hanya file yang tercantum di plan yang berubah.

- [ ] **Step 5: Laporkan hasil**

Tulis laporan akhir berisi ringkasan perubahan, file yang diubah, PRD/aturan yang diperiksa, perintah test + hasil aktual, risiko/keterbatasan, dan status dokumentasi. Jangan klaim lulus tanpa output test.
