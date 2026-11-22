# Kas Masuk Server-side Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign `/kas_masuk` menjadi halaman ledger-first dengan DataTables server-side, filter tanggal/unit/kategori, ringkasan, aksi Edit/Hapus yang hidup, dan scope unit baca.

**Architecture:** `ModelKasMasuk` mendapat query builder server-side (count, sum, rows) yang dipakai satu endpoint JSON `kas_masuk/datatables`. `Kas_Masuk` memakai `FinanceScopeService` untuk membatasi unit. View dipecah menjadi partial tema `.km-*` + view tipis yang merender tabel kosong dan modal; DataTables mengisi badan tabel lewat AJAX. Update/delete menyinkronkan jurnal referensi `kas_masuk` dalam transaksi yang sama.

**Tech Stack:** PHP 7.4-compatible, CodeIgniter 4.4.8, MySQLi/MariaDB, jQuery, Bootstrap 5, DataTables 1.13, PHPUnit 9.

**Spec:** `docs/superpowers/specs/2026-11-22-kas-masuk-redesign-design.md`

**Issue:** [#235](https://github.com/bwafi/ERP-iClear/issues/235)

## Global Constraints

- Jangan mengubah schema database.
- Jangan mengubah route POST, nama field, validasi COA (`bolehUntukKasMasuk`), validasi rekening, posting ledger, atau aturan inflow-only dari #234.
- Jangan menambah guard authz baru pada update/delete. `can_act` hanya menyembunyikan tombol; server tetap `auth` seperti sebelumnya.
- Jangan `git commit` sampai user mengizinkannya secara eksplisit. Setiap "Checkpoint" hanya test/pemeriksaan.
- Jangan menjalankan test ke database produksi. Konfigurasi aktif: `phpunit.xml` bila ada, jika tidak `phpunit.xml.dist`.
- Jangan memakai `str_starts_with()`; proyek masih mendukung PHP 7.4.
- Semua nominal integer rupiah; format tampilan `Rp 1.234.567` di klien.
- `idbank` adalah VARCHAR; jangan cast ke int.
- Pekerjaan #234 yang belum di-commit di working tree harus dipertahankan.

## File Structure

| File | Tanggung jawab |
|---|---|
| `app/Models/ModelKasMasuk.php` | Query daftar: filter, hitung, jumlah, urutan, paginasi. |
| `app/Controllers/Kas_Masuk.php` | `index()` menyiapkan filter/opsi; `datatable()` mengembalikan JSON; update/delete menjaga jurnal. |
| `app/Config/Routes.php` | Route `kas_masuk/datatables` + auth pada GET index. |
| `app/Views/jurnal/_kas_masuk_theme.php` | Seluruh CSS scoped `.km-page` / `.km-modal`. |
| `app/Views/jurnal/kas_masuk.php` | Markup halaman, modal, template baris, JS DataTables. |
| `tests/unit/ModelKasMasukDataTableTest.php` | Unit test query/filter/sum/order. |
| `tests/KasMasukServerSideTest.php` | Feature test endpoint, scope, view. |
| `tests/KasBankControllerTest.php` | Regresi update/delete + sinkronisasi jurnal. |

## Review Focus

- Role terbatas tidak boleh membaca unit lain lewat `recordsTotal`, `data`, atau `unit_id` palsu.
- Ringkasan dan kaki tabel harus sama dengan seluruh hasil filter, bukan halaman aktif.
- Update nominal harus mengubah jurnal referensi, dan delete harus menghapus jurnal referensi; jurnal modul lain tidak boleh tersentuh.
- Pencarian `%`/`_` harus diperlakukan literal, bukan wildcard.
- Tabel harus tetap terbaca di ponsel (kartu berlabel) tanpa scroll horizontal.

---

### Task 1: Query server-side di `ModelKasMasuk`

**Files:**
- Modify: `app/Models/ModelKasMasuk.php`
- Test: `tests/unit/ModelKasMasukDataTableTest.php` (baru)

**Interfaces:**
- Produces:
  - `countAllKasMasuk(): int`
  - `countKasMasukFiltered(string $search = '', ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, ?int $kategoriId = null): int`
  - `sumKasMasukFiltered(string $search = '', ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, ?int $kategoriId = null): array` → `['total'=>float,'tunai'=>float,'transfer'=>float,'n'=>int]`
  - `getKasMasukDataTable(int $length, int $start, string $search = '', ?string $orderCol = null, string $orderDir = 'DESC', ?string $startDate = null, ?string $endDate = null, ?int $unitId = null, ?int $kategoriId = null): array`
- `getKasMasuk()` dan `getKasMasukFiltered()` tidak berubah.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/unit/ModelKasMasukDataTableTest.php`:

```php
<?php

namespace Tests\unit;

use App\Models\ModelKasMasuk;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ModelKasMasukDataTableTest extends CIUnitTestCase
{
    private const TABEL = ['kas_masuk', 'kategori_kas', 'bank', 'unit', 'no_akun'];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
        }

        $this->db->query('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL)');
        $this->db->query('CREATE TABLE kategori_kas (idkategori_kas INT PRIMARY KEY, kategori TEXT NULL)');
        $this->db->query('CREATE TABLE no_akun (no_akun TEXT NULL, nama_akun TEXT NULL)');
        $this->db->query('CREATE TABLE bank (idbank VARCHAR(20) PRIMARY KEY, nama_bank TEXT NULL, norek TEXT NULL)');
        $this->db->query('CREATE TABLE kas_masuk (
            idkas_masuk INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NULL, kategori_idkategori INT NULL, no_akun TEXT NULL,
            deskripsi TEXT NULL, jumlah BIGINT DEFAULT 0, jenis VARCHAR(15) NULL,
            penerima TEXT NULL, idbank VARCHAR(20) NULL, idunit INT NULL,
            created_on TEXT NULL, updated_on TEXT NULL)');

        $this->db->table('unit')->insertBatch([
            ['idunit' => 1, 'NAMA_UNIT' => 'Unit A'],
            ['idunit' => 2, 'NAMA_UNIT' => 'Unit B'],
        ]);
        $this->db->table('kategori_kas')->insertBatch([
            ['idkategori_kas' => 1, 'kategori' => 'Pendapatan'],
            ['idkategori_kas' => 2, 'kategori' => 'Modal'],
        ]);
        $this->db->table('no_akun')->insertBatch([
            ['no_akun' => '4000000000', 'nama_akun' => 'Pendapatan Lain'],
        ]);
        $this->db->table('bank')->insertBatch([
            ['idbank' => 'BNI-001', 'nama_bank' => 'BNI', 'norek' => '5551'],
        ]);
        $this->db->table('kas_masuk')->insertBatch([
            ['tanggal' => '2026-11-01', 'kategori_idkategori' => 1, 'no_akun' => '4000000000', 'deskripsi' => 'Setoran tunai', 'jumlah' => 100000, 'jenis' => 'kredit', 'penerima' => 'Kasir', 'idbank' => null, 'idunit' => 1],
            ['tanggal' => '2026-11-02', 'kategori_idkategori' => 1, 'no_akun' => '4000000000', 'deskripsi' => 'Transfer BNI', 'jumlah' => 250000, 'jenis' => 'kredit', 'penerima' => 'PT Contoh', 'idbank' => 'BNI-001', 'idunit' => 1],
            ['tanggal' => '2026-11-03', 'kategori_idkategori' => 2, 'no_akun' => '4000000000', 'deskripsi' => 'Modal unit B', 'jumlah' => 400000, 'jenis' => 'kredit', 'penerima' => 'Pemilik', 'idbank' => null, 'idunit' => 2],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
        }
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function model(): ModelKasMasuk
    {
        return new ModelKasMasuk();
    }

    public function testFilterTanggalUnitDanKategori(): void
    {
        $m = $this->model();

        $this->assertSame(3, $m->countAllKasMasuk());
        $this->assertSame(2, $m->countKasMasukFiltered('', '2026-11-01', '2026-11-02', 1, null));
        $this->assertSame(1, $m->countKasMasukFiltered('', null, null, 2, null));
        $this->assertSame(1, $m->countKasMasukFiltered('', null, null, null, 2));
    }

    public function testPencarianMencakupTeksDanId(): void
    {
        $m = $this->model();

        $this->assertSame(1, $m->countKasMasukFiltered('Transfer BNI'));
        $this->assertSame(2, $m->countKasMasukFiltered('Unit A'));
        $this->assertSame(1, $m->countKasMasukFiltered('BNI-001'));
        $this->assertSame(1, $m->countKasMasukFiltered('4000000000'));
        $this->assertSame(0, $m->countKasMasukFiltered('%'), 'Wildcard LIKE harus literal.');
        $this->assertSame(1, $m->countKasMasukFiltered('2'), 'Angka juga cocok ke idkas_masuk.');
    }

    public function testSumTunaiTransferDanTotal(): void
    {
        $m = $this->model();
        $sum = $m->sumKasMasukFiltered();

        $this->assertSame(750000.0, $sum['total']);
        $this->assertSame(500000.0, $sum['tunai']);
        $this->assertSame(250000.0, $sum['transfer']);
        $this->assertSame(3, $sum['n']);

        $filtered = $m->sumKasMasukFiltered('', null, null, 1);
        $this->assertSame(350000.0, $filtered['total']);
    }

    public function testUrutanDanPaginasi(): void
    {
        $m = $this->model();

        $desc = $m->getKasMasukDataTable(2, 0, '', 'kas_masuk.jumlah', 'DESC');
        $this->assertSame([400000.0, 250000.0], array_map(static fn ($r) => (float) $r->jumlah, $desc));

        $page2 = $m->getKasMasukDataTable(2, 2, '', 'kas_masuk.jumlah', 'DESC');
        $this->assertSame([100000.0], array_map(static fn ($r) => (float) $r->jumlah, $page2));

        $liar = $m->getKasMasukDataTable(3, 0, '', 'kas_masuk.jumlah; DROP TABLE kas_masuk', 'DESC');
        $this->assertSame(3, count($liar), 'Kolom urutan liar jatuh ke default dan tidak merusak query.');
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

```sh
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/ModelKasMasukDataTableTest.php
```

Expected: FAIL karena method belum ada.

- [ ] **Step 3: Implementasikan query**

Tambahkan ke `ModelKasMasuk` (setelah `getKasMasukFiltered`):

- `private function baseKasMasukQuery()`:
  ```php
  return $this->db->table('kas_masuk')
      ->select('kas_masuk.*, kategori_kas.kategori, bank.nama_bank, bank.norek, unit.NAMA_UNIT, no_akun.nama_akun')
      ->join('kategori_kas', 'kategori_kas.idkategori_kas = kas_masuk.kategori_idkategori', 'left')
      ->join('bank', 'bank.idbank = kas_masuk.idbank', 'left')
      ->join('unit', 'unit.idunit = kas_masuk.idunit', 'left')
      ->join('no_akun', 'no_akun.no_akun = kas_masuk.no_akun', 'left');
  ```
- `private function applyKasMasukFilter($builder, string $search, ?string $startDate, ?string $endDate, ?int $unitId, ?int $kategoriId)`:
  - bila `$search !== ''`, `groupStart()` + `like()` untuk `kas_masuk.deskripsi`, `kas_masuk.no_akun`, `no_akun.nama_akun`, `kategori_kas.kategori`, `bank.nama_bank`, `bank.norek`, `unit.NAMA_UNIT`, `kas_masuk.penerima`; bila `ctype_digit($search)`, tambah `orWhere('kas_masuk.idkas_masuk', (int) $search)`; `groupEnd()`;
  - tanggal: `>=` dan `<=` pada `kas_masuk.tanggal`;
  - `unitId > 0` → `where('kas_masuk.idunit', $unitId)`;
  - `kategoriId > 0` → `where('kas_masuk.kategori_idkategori', $kategoriId)`.
- `countAllKasMasuk()`: `COUNT(*)` dari `kas_masuk` tanpa join.
- `countKasMasukFiltered(...)`: base + filter + `countAllResults()`.
- `sumKasMasukFiltered(...)`: base + filter, `select('COALESCE(SUM(kas_masuk.jumlah),0) AS total', false)`, `select("COALESCE(SUM(CASE WHEN kas_masuk.idbank IS NULL OR kas_masuk.idbank = '' THEN kas_masuk.jumlah ELSE 0 END),0) AS tunai", false)`, `select("COALESCE(SUM(CASE WHEN kas_masuk.idbank IS NOT NULL AND kas_masuk.idbank <> '' THEN kas_masuk.jumlah ELSE 0 END),0) AS transfer", false)`, `select('COUNT(*) AS n', false)`; cast ke float/int.
- `getKasMasukDataTable(...)`:
  - whitelist: `kas_masuk.tanggal`, `unit.NAMA_UNIT`, `kas_masuk.no_akun`, `kategori_kas.kategori`, `kas_masuk.deskripsi`, `bank.nama_bank`, `kas_masuk.penerima`, `bank.norek`, `kas_masuk.jumlah`, `kas_masuk.jenis`;
  - default `kas_masuk.tanggal`;
  - arah hanya `ASC`/`DESC`;
  - `orderBy($orderCol, $orderDir)->orderBy('kas_masuk.idkas_masuk', 'DESC')`;
  - `limit(max(0, $length), max(0, $start))`;
  - `get()->getResult()`.

Catatan: `like()` CI4 meng-escape wildcard user secara default; jangan memakai `%` manual.

- [ ] **Step 4: Jalankan test, pastikan lulus**

```sh
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/ModelKasMasukDataTableTest.php
php -l app/Models/ModelKasMasuk.php
```

- [ ] **Checkpoint:** query server-side berdiri sendiri. Jangan commit.

---

### Task 2: Endpoint datatable, scope unit, dan data `index()`

**Files:**
- Modify: `app/Controllers/Kas_Masuk.php`
- Modify: `app/Config/Routes.php`
- Test: `tests/KasMasukServerSideTest.php` (baru)

**Interfaces:**
- Consumes: method Task 1.
- Produces: `Kas_Masuk::datatable()` dengan payload §5.3 desain; `index()` mengirim `unit_filter`, `can_filter_unit`, `show_all_units`, `default_filter_unit`, `can_act`, `bank_per_unit`.

- [ ] **Step 1: Tulis feature test yang gagal**

Buat `tests/KasMasukServerSideTest.php` dengan skema mandiri (pola `tests/KasKeluarUnitFilterTest.php`): tabel `unit`, `akun`, `spv_units`, `kategori_kas`, `no_akun`, `bank`, `kas_masuk`, `akun_kas_bank`, `alokasi_saldo_kas_bank`, `jurnal`, `transaksi_kas_bank`, `stok_barang`, `service`, `pelanggan`, `jabatan`, `menu`.

Seed:

```php
$q("INSERT INTO unit (idunit, NAMA_UNIT) VALUES (1,'Unit A'),(2,'Unit B'),(3,'Unit C')");
$q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN) VALUES
    (1,1,1,'Admin Root'), (200,2,35,'Kasir B'), (201,2,40,'SPV')");
$q("INSERT INTO spv_units (spv_id, unit_id) VALUES (201,1),(201,2)");
$q("INSERT INTO kategori_kas (idkategori_kas, kategori) VALUES (1,'Pendapatan'),(2,'Modal')");
$q("INSERT INTO kas_masuk (tanggal, kategori_idkategori, no_akun, deskripsi, jumlah, idunit, jenis, penerima, idbank) VALUES
    ('2026-11-01',1,'4000000000','tunai A',1000,1,'kredit','Kasir',NULL),
    ('2026-11-02',1,'4000000000','bank B',2000,2,'kredit','PT', 'BNI-001'),
    ('2026-11-03',2,'4000000000','tunai B',3000,2,'kredit','Kasir',NULL),
    ('2026-11-04',1,'4000000000','tunai C',4000,3,'kredit','Kasir',NULL)");
```

Test:

```php
public function testDatatableMengembalikanPayloadDanRingkasan(): void
{
    $this->sesi(1, 1, 1);
    $json = $this->datatable('draw=7&start=0&length=25');

    $this->assertSame(7, $json['draw']);
    $this->assertSame(4, $json['recordsTotal']);
    $this->assertSame(4, $json['recordsFiltered']);
    $this->assertSame(10000.0, (float) $json['sumTotal']);
    $this->assertSame(8000.0, (float) $json['sumTunai']);
    $this->assertSame(2000.0, (float) $json['sumTransfer']);
    $this->assertSame(4, $json['countFiltered']);
    $this->assertArrayHasKey('nama_akun', $json['data'][0]);
}

public function testFilterTanggalUnitKategoriDiServer(): void
{
    $this->sesi(1, 1, 1);

    $this->assertSame(1, $this->datatable('draw=1&start=0&length=25&kategori_id=2')['recordsFiltered']);
    $this->assertSame(2, $this->datatable('draw=1&start=0&length=25&unit_id=2')['recordsFiltered']);
    $this->assertSame(2, $this->datatable('draw=1&start=0&length=25&tanggal_awal=2026-11-02&tanggal_akhir=2026-11-03')['recordsFiltered']);
}

public function testKasirTidakBisaMembacaUnitLain(): void
{
    $this->sesi(200, 2, 35);
    $json = $this->datatable('draw=1&start=0&length=25&unit_id=1');

    $this->assertSame(2, $json['recordsFiltered']);
    $this->assertSame(2, $json['recordsTotal']);
    $this->assertSame(5000.0, (float) $json['sumTotal']);
    foreach ($json['data'] as $row) {
        $this->assertSame(2, (int) $row['idunit']);
    }
}

public function testSpvHanyaUnitBinaan(): void
{
    $this->sesi(201, 2, 40);

    $binaan = $this->datatable('draw=1&start=0&length=25&unit_id=2');
    $this->assertSame(2, $binaan['recordsFiltered']);

    $luar = $this->datatable('draw=1&start=0&length=25&unit_id=3');
    $this->assertSame(1, $luar['recordsFiltered'], 'Unit di luar spv_units jatuh ke unit binaan pertama (1).');
}

public function testPencarianWildcardLiteral(): void
{
    $this->sesi(1, 1, 1);
    $this->assertSame(0, $this->datatable('draw=1&start=0&length=25&search%5Bvalue%5D=%25')['recordsFiltered']);
}

public function testIndexMengirimFilterUnitDanAksi(): void
{
    $this->sesi(200, 2, 35);
    $html = (string) $this->get('kas_masuk')->getBody();

    $this->assertStringContainsString('id="kmUnitFilter"', $html);
    $this->assertStringContainsString('disabled', $this->selectUnitFilter());
    $this->assertStringNotContainsString('Unit A', $this->selectUnitFilter());
    $this->assertStringContainsString('kas_masuk/datatables', $html);
}

public function testIndexRoleLintasBisaSemuaUnit(): void
{
    $this->sesi(1, 1, 1);
    $html = (string) $this->get('kas_masuk')->getBody();

    $this->assertStringContainsString('Semua unit', $html);
    $this->assertStringContainsString('Unit A', $html);
    $this->assertStringContainsString('Unit B', $html);
}
```

Tambahkan helper `datatable(string $query): array` dan `selectUnitFilter(): string` seperti `KasKeluarUnitFilterTest` (regex `id="kmUnitFilter"`).

- [ ] **Step 2: Jalankan, pastikan gagal**

```sh
vendor/bin/phpunit -c phpunit.xml.dist tests/KasMasukServerSideTest.php
```

Expected: 404/500 karena route dan endpoint belum ada.

- [ ] **Step 3: Implementasikan controller dan route**

`app/Config/Routes.php`:

```php
$routes->get('/kas_masuk', 'Kas_Masuk::index', ['filter' => 'auth']);
$routes->get('kas_masuk/datatables', 'Kas_Masuk::datatable', ['filter' => 'auth']);
```

`Kas_Masuk`:

- tambah `use App\Services\Finance\FinanceScopeService;` dan properti `$FinanceScope`;
- helper `bebasPilihUnit()` → `in_array((int) session('ID_JABATAN'), FinanceScopeService::inputRoles(), true)`;
- helper `unitFilterList()` → semua unit bila lintas, selain itu `resolveAllowedUnits()`;
- helper `unitFilterTerpilih($requested)` → validasi terhadap `allowedUnitIds()`, fallback unit pertama / `session('ID_UNIT')`;
- `index()`:
  - hapus `'kas_masuk' => $this->KasMasukModel->getKasMasuk()`;
  - bangun `$bankPerUnit` seperti `Kas_Keluar::index()` (termasuk rekening Finance/HO untuk role yang berhak);
  - kirim `bank_per_unit`, `unit_filter`, `can_filter_unit`, `show_all_units`, `default_filter_unit`, `can_act`.
- `datatable()`:
  - baca `draw`, `start`, `length`, `search[value]`, `order[0][column]`, `order[0][dir]`, `tanggal_awal`, `tanggal_akhir`, `unit_id`, `kategori_id`;
  - `length < 1` → 25; `start < 0` → 0;
  - `unitId = unitFilterTerpilih($request->getGet('unit_id'))`;
  - `kategoriId = (int) ...; <= 0 ? null : $kategoriId`;
  - `recordsTotal` = `bebasPilihUnit() ? countAllKasMasuk() : countKasMasukFiltered('', null, null, $unitId, null)`;
  - `recordsFiltered`, `sum`, `rows` memakai argumen yang sama;
  - peta kolom: 0 `kas_masuk.tanggal`, 1 `unit.NAMA_UNIT`, 2 `kas_masuk.no_akun`, 3 `kategori_kas.kategori`, 4 `kas_masuk.deskripsi`, 5 `bank.nama_bank`, 6 `kas_masuk.penerima`, 7 `bank.norek`, 8 `kas_masuk.jumlah`, 9 `null`;
  - bentuk `data[]` sesuai §5.3 desain; `idbank` `(string)`, `jumlah` `(float)`, `aksi` `null`;
  - `return $this->response->setJSON([...]);`.

- [ ] **Step 4: Jalankan test, pastikan lulus**

```sh
vendor/bin/phpunit -c phpunit.xml.dist tests/KasMasukServerSideTest.php
php -l app/Controllers/Kas_Masuk.php
```

- [ ] **Checkpoint:** endpoint dan scope bekerja. Jangan commit.

---

### Task 3: Sinkronisasi jurnal pada update/delete

**Files:**
- Modify: `app/Controllers/Kas_Masuk.php`
- Test: `tests/KasBankControllerTest.php` (tambah method)

**Interfaces:**
- Consumes: `ModelJurnal::hapusByReferensi()`.
- Produces: update menulis `tanggal`, `debet = 0`, `kredit = jumlah`, `keterangan = deskripsi` pada jurnal `tabel_referensi = 'kas_masuk'`; delete menghapus jurnal referensi.

- [ ] **Step 1: Tambah test yang gagal**

Tambahkan ke `tests/KasBankControllerTest.php`:

```php
public function testUpdateKasMasukMenyinkronkanJurnal(): void
{
    $this->sesi();
    $this->db->query("INSERT INTO db_kas_masuk (idkas_masuk, tanggal, deskripsi, jumlah, jenis, penerima, idbank, idunit, no_akun)
        VALUES (801, '$this->tglMulai', 'lama', 50000, 'kredit', 'PT Contoh', 'BNI-001', 1, '5-1010')");
    $this->db->query("INSERT INTO db_jurnal (tanggal, no_akun, nama_akun, debet, kredit, keterangan, id_referensi, tabel_referensi, id_unit, id_akun)
        VALUES ('$this->tglMulai', '5-1010', 'Pendapatan', 0, 50000, 'lama', 801, 'kas_masuk', 1, 43)");
    $this->db->query("INSERT INTO db_jurnal (tanggal, no_akun, nama_akun, debet, kredit, keterangan, id_referensi, tabel_referensi, id_unit, id_akun)
        VALUES ('$this->tglMulai', '9-9999', 'Lain', 0, 7000, 'jangan sentuh', 801, 'kas_keluar', 1, 43)");

    $r = $this->post('update_kas_masuk', [
        'idkas_masuk' => '801',
        'tanggal' => $this->tglBerikut,
        'deskripsi' => 'baru',
        'kategori_idkategori' => '1',
        'jumlah' => '75000',
        'penerima' => 'BNI-001',
        'idunit' => '1',
    ]);
    $r->assertStatus(302);

    $jurnal = $this->db->query("SELECT * FROM db_jurnal WHERE tabel_referensi='kas_masuk' AND id_referensi=801")->getRow();
    $this->assertSame(0.0, (float) $jurnal->debet);
    $this->assertSame(75000.0, (float) $jurnal->kredit);
    $this->assertSame('baru', $jurnal->keterangan);
    $this->assertSame($this->tglBerikut, $jurnal->tanggal);

    $lain = $this->db->query("SELECT * FROM db_jurnal WHERE tabel_referensi='kas_keluar'")->getRow();
    $this->assertSame(7000.0, (float) $lain->kredit, 'Jurnal modul lain tidak boleh berubah.');
}

public function testDeleteKasMasukMenghapusJurnalReferensi(): void
{
    $this->sesi();
    $this->db->query("INSERT INTO db_kas_masuk (idkas_masuk, tanggal, deskripsi, jumlah, jenis, penerima, idbank, idunit, no_akun)
        VALUES (802, '$this->tglMulai', 'hapus', 30000, 'kredit', 'PT Contoh', 'BNI-001', 1, '5-1010')");
    $this->db->query("INSERT INTO db_jurnal (tanggal, no_akun, nama_akun, debet, kredit, keterangan, id_referensi, tabel_referensi, id_unit, id_akun)
        VALUES ('$this->tglMulai', '5-1010', 'Pendapatan', 0, 30000, 'hapus', 802, 'kas_masuk', 1, 43)");
    $this->db->query("INSERT INTO db_jurnal (tanggal, no_akun, nama_akun, debet, kredit, keterangan, id_referensi, tabel_referensi, id_unit, id_akun)
        VALUES ('$this->tglMulai', '9-9999', 'Lain', 0, 5000, 'tetap', 802, 'kas_keluar', 1, 43)");

    $r = $this->post('delete_kas_masuk', ['idkas_masuk' => '802']);
    $r->assertStatus(302);

    $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) AS c FROM db_kas_masuk WHERE idkas_masuk=802")->getRow()->c);
    $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) AS c FROM db_jurnal WHERE tabel_referensi='kas_masuk' AND id_referensi=802")->getRow()->c);
    $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) AS c FROM db_jurnal WHERE tabel_referensi='kas_keluar'")->getRow()->c);
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

```sh
vendor/bin/phpunit -c phpunit.xml.dist tests/KasBankControllerTest.php --filter "MenyinkronkanJurnal|MenghapusJurnalReferensi"
```

Expected: FAIL karena jurnal belum disinkronkan/dihapus.

- [ ] **Step 3: Implementasikan**

Di `update_kas_masuk()`, setelah normalisasi jumlah:

```php
$jumlah = (int) preg_replace('/[^0-9]/', '', (string) $jumlah);
```

Di dalam transaksi, setelah `$this->KasMasukModel->update($id, $data);`:

```php
$this->JurnalModel->builder()
    ->where('tabel_referensi', 'kas_masuk')
    ->where('id_referensi', (string) $id)
    ->update([
        'tanggal'    => $tanggal,
        'debet'      => 0,
        'kredit'     => $jumlah,
        'keterangan' => $deskripsi,
    ]);
```

Di `delete_kas_masuk()`, di dalam transaksi:

```php
$this->JurnalModel->hapusByReferensi('kas_masuk', (int) $id);
```

Bungkus delete dengan `try/catch` + `transRollback` + flash gagal, seperti update.

- [ ] **Step 4: Jalankan test dan regresi #234**

```sh
vendor/bin/phpunit -c phpunit.xml.dist tests/KasBankControllerTest.php
php -l app/Controllers/Kas_Masuk.php
```

- [ ] **Checkpoint:** update/delete menjaga jurnal. Jangan commit.

---

### Task 4: Tema `.km-*` dan view baru

**Files:**
- Create: `app/Views/jurnal/_kas_masuk_theme.php`
- Modify: `app/Views/jurnal/kas_masuk.php`
- Test: `tests/KasMasukServerSideTest.php` (tambah assertion statis)

**Interfaces:**
- Consumes: data `index()` Task 2; endpoint Task 2; aksi Task 3.
- Produces: markup dengan id `kmTable`, `kmSearch`, `kmStart`, `kmEnd`, `kmUnitFilter`, `kmKategoriFilter`, `kmSummary*`, `kmPager`, `kmEmpty`, `kmInputModal`, `kmEditModal`, `kmDeleteModal`, `kmAkunModal`.

- [ ] **Step 1: Tambah test statis yang gagal**

Tambahkan ke `tests/KasMasukServerSideTest.php`:

```php
public function testViewMemakaiDataTablesServerSideDanFieldLama(): void
{
    $this->sesi(1, 1, 1);
    $html = (string) $this->get('kas_masuk')->getBody();

    $this->assertStringContainsString('serverSide: true', $html);
    $this->assertStringContainsString('kas_masuk/datatables', $html);
    $this->assertStringContainsString('id="kmTable"', $html);
    $this->assertStringContainsString('name="akun[0][no_akun]"', $html, 'Template baris memakai kontrak field lama.');
    $this->assertStringContainsString('name="akun[0][kategori_idkategori]"', $html);
    $this->assertStringContainsString('name="akun[0][jenis_transaksi]"', $html);
    $this->assertStringContainsString('name="akun[0][no_rekening]"', $html);
    $this->assertStringContainsString('name="akun[0][penerima]"', $html);
    $this->assertStringContainsString('name="akun[0][jumlah]"', $html);
    $this->assertStringContainsString('name="idkas_masuk"', $html);
    $this->assertStringNotContainsString('posisi_drk', $html);
}

public function testViewPunyaEmptyStateDanKartuResponsif(): void
{
    $this->sesi(1, 1, 1);
    $html = (string) $this->get('kas_masuk')->getBody();

    $this->assertStringContainsString('id="kmEmpty"', $html);
    $this->assertStringContainsString('data-label', $html);
    $this->assertStringContainsString('_kas_masuk_theme', $html);
    $this->assertStringContainsString('prefers-reduced-motion', $html);
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

```sh
vendor/bin/phpunit -c phpunit.xml.dist tests/KasMasukServerSideTest.php --filter View
```

- [ ] **Step 3: Buat partial tema**

Buat `app/Views/jurnal/_kas_masuk_theme.php` berisi `<style>` scoped `.km-page` / `.km-modal`:

- token: `--km-ink`, `--km-ink-2`, `--km-ink-3`, `--km-line`, `--km-surface` (`var(--bs-body-bg)`), `--km-raise`, `--km-r: 1.125rem`, `--km-shadow`;
- `.km-masthead` putih ber-hairline, judul `1.5rem/600`, breadcrumb kecil;
- `.km-cmdbar` grid responsif: search lebar, tanggal, unit, kategori, reset, export;
- `.km-sum` empat sel berhairline, angka `tabular-nums`, label uppercase kecil;
- `.km-table` header uppercase, baris hover, nominal rata kanan, aksi ghost;
- `.km-empty`, `.km-pager`, `.km-chip`;
- `.km-modal .modal-dialog { max-width: 880px; }`, footer sticky;
- `@media (max-width: 767.98px)`: `.km-table thead` disembunyikan secara visual, `tr` jadi blok kartu, `td::before { content: attr(data-label) }`, filter satu kolom, modal full-width;
- `@media (prefers-reduced-motion: reduce)` mematikan transisi.

- [ ] **Step 4: Tulis ulang view**

`app/Views/jurnal/kas_masuk.php`:

- `require`/`include` partial tema (pola `$this->include('jurnal/_kas_masuk_theme')`);
- masthead + tombol `Input Kas Masuk`;
- form export dengan hidden `tanggal_awal`, `tanggal_akhir`, `nama_unit`;
- deck: search, tanggal, unit (disabled bila `!can_filter_unit`), kategori, reset, export;
- strip ringkasan `kmSumTotal`, `kmSumCount`, `kmSumTunai`, `kmSumTransfer`;
- `<table id="kmTable">` dengan kolom: Tanggal, Unit, Akun, Kategori, Deskripsi, Rekening, Penerima, Jumlah, Aksi (9 kolom); `<tbody>` kosong;
- empty state `kmEmpty`;
- pager `kmPager` + info `kmInfo`;
- modal input: field kontrak, `<template id="kmRowTemplate">` berisi input/select dengan placeholder `__i__` yang diganti index saat ditambahkan;
- modal Cari Buku `#table_kasmasuk` (daftar `$no_akun`, pencarian sederhana);
- modal edit dan hapus (field lama);
- `<script>`:
  - `window.KM_BANKS = <?= json_encode($bank_per_unit, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;`
  - `window.KM_KATEGORI = ...;`
  - DataTables `serverSide: true`, `ajax.url = base_url('kas_masuk/datatables')`, `data` menambahkan filter aktif;
  - `columns` dengan `render` untuk tanggal, uang, rekening, aksi; semua nilai dinamis di-escape sebelum masuk HTML;
  - `drawCallback` mengisi ringkasan dari `json`, pager, info, empty state, dan `data-label`;
  - tombol Edit/Hapus hanya dirender bila `can_act` (dikirim sebagai `const KM_CAN_ACT = <?= $can_act ? 'true' : 'false' ?>;`);
  - input jumlah diformat `Rp` saat mengetik dan dikembalikan ke digit saat submit;
  - ganti unit mengganti opsi rekening dari `KM_BANKS[unit]`;
  - `jenis_transaksi = bank` mengaktifkan rekening, selain itu menonaktifkan dan mengosongkan;
  - tombol Tambah Akun membuka modal Cari Buku; Pilih menambahkan baris dan menutup modal;
  - submit form memastikan minimal satu baris dan semua jumlah > 0 (validasi klien; server tetap sumber kebenaran);
  - sebelum form export dikirim, isi hidden `tanggal_awal`/`tanggal_akhir` dari filter dan `nama_unit` dari teks opsi unit terpilih (kosong bila "Semua unit").

- [ ] **Step 5: Jalankan test view dan lint**

```sh
vendor/bin/phpunit -c phpunit.xml.dist tests/KasMasukServerSideTest.php
php -l app/Views/jurnal/kas_masuk.php
php -l app/Views/jurnal/_kas_masuk_theme.php
```

- [ ] **Checkpoint:** halaman baru tampil dan test statis lulus. Jangan commit.

---

### Task 5: Dokumentasi dan verifikasi akhir

**Files:**
- Modify: `docs/prd/modul/PRD_MODUL_05_Keuangan_KasBank_Aset.md`
- Modify: `DESIGN.md`
- Modify: `docs/superpowers/specs/2026-11-22-kas-masuk-redesign-design.md` (status)

- [ ] **Step 1: Perbarui PRD**

- §4.2: daftar kas masuk server-side, filter/scope baca, ringkasan, aksi Edit/Hapus, dan sinkronisasi jurnal.
- Temuan #13 (`GET /kas_masuk` tanpa auth): tandai selesai dengan bukti route.
- Keterbatasan update/delete jurnal: perbarui karena sekarang disinkronkan untuk referensi `kas_masuk`.
- Jangan menaikkan status verifikasi tanpa test yang benar-benar dijalankan.

- [ ] **Step 2: Perbarui `DESIGN.md`**

Tambahkan bagian `### Kas Masuk (`.km-*`)` setelah `Kas Keluar`, berisi scope file, layout, tabel, modal, responsive, dan rules (satu aksen, tanpa gradien, muted ≥4.5:1, motion dimatikan).

- [ ] **Step 3: Jalankan seluruh verifikasi**

```sh
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/ModelKasMasukDataTableTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/KasMasukServerSideTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/KasBankControllerTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/ModelNoAkunKasMasukTest.php
php -l app/Controllers/Kas_Masuk.php
php -l app/Models/ModelKasMasuk.php
php -l app/Views/jurnal/kas_masuk.php
php -l app/Views/jurnal/_kas_masuk_theme.php
git diff --check
```

- [ ] **Step 4: Periksa diff**

```sh
git diff --stat
git diff -- app/Controllers/Kas_Masuk.php app/Models/ModelKasMasuk.php app/Config/Routes.php
```

Pastikan tidak ada perubahan di luar scope dan pekerjaan #234 tetap utuh.

- [ ] **Step 5: Verifikasi manual**

Buka `/kas_masuk` dengan role Root dan role unit terbatas:
- cari, urutkan, dan paginasi;
- filter tanggal/unit/kategori dan reset;
- ringkasan berubah mengikuti filter;
- Edit mengubah nominal dan jurnal; Hapus menghapus baris + jurnal;
- input multi-baris tunai dan bank;
- lebar < 768px: tabel menjadi kartu, modal nyaman.

- [ ] **Checkpoint:** laporkan hasil test aktual dan status manual. Jangan commit sampai user mengizinkan.
