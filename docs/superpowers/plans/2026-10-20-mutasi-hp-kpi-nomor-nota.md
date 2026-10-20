# Mutasi H/P: Tagihan, KPI, dan Nomor Nota Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Menagih mutasi berdasarkan jumlah diterima, memasukkan utang antar unit ke KPI `FIN_HUTANG_PIUTANG`, dan membuat nomor nota mutasi unik.

**Architecture:** Tiga perubahan terpisah. `ModeKasBank::nilaiDetailMutasi()` memakai `jumlah_terima`. Calculator baru `MutasiTimelinessCalculator` membaca `hutang_piutang` sisi hutang `sumber_tipe='mutasi_unit'`, lalu `FinanceKpiCalculationService` menggabungkannya dengan skor Hutang dan Piutang lewat helper murni. Migration baru merenumber duplikat lama secara deterministik lalu memasang unique index.

**Tech Stack:** PHP 8, CodeIgniter 4, PHPUnit 9, SQLite untuk test, MySQL/MariaDB untuk `erp_local`.

**Spec:** `docs/audit/mutasi-hutang-piutang-kpi-2026-10-19.md` dan keputusan pengguna 2026-10-20.

## Global Constraints

- Jangan commit `.impeccable/*` atau `docs/template-invoice.pdf`.
- Jangan mengubah data sebelum backup; migration renumber hanya dijalankan setelah backup `mutasi`.
- KPI hanya menghitung `scope='active'`, `deleted=0`, `jenis='hutang'`, `sumber_tipe='mutasi_unit'`; sisi piutang tidak boleh dihitung dua kali.
- Nomor nota baru tetap memakai format `MTS<unit><Ymd><idmutasi>`; renumber hanya untuk data lama.
- Test database memakai SQLite in-memory dengan prefix `db_`; migration harus portabel MySQL dan SQLite.
- Semua test dijalankan satu file per perintah karena PHPUnit proyek ini hanya memproses argumen file pertama.

---

### Task 1: Tagih `jumlah_terima`

**Files:**
- Modify: `app/Libraries/ModeKasBank.php:659-668`
- Test: `tests/KasBankTest.php:537-551`

**Interfaces:**
- Consumes: `nilaiDetailMutasi(array $detail): int`
- Produces: nilai = `jumlah_terima × harga` (`harga_mutasi`, fallback `hpp_barang`)

- [x] **Step 1: Update test yang ada dan tambah test baru**

Ubah dua test lama agar menyertakan `jumlah_terima` yang sama dengan `jumlah_kirim`:

```php
public function testNilaiDetailMutasiGunakanHargaMutasi(): void
{
    $this->assertSame(2000, $this->kasbank->nilaiDetailMutasi([
        'jumlah_kirim' => 2, 'jumlah_terima' => 2, 'harga_mutasi' => 1000, 'hpp_barang' => 999,
    ]));
}

public function testNilaiDetailMutasiFallbackKeHpp(): void
{
    $this->assertSame(1500, $this->kasbank->nilaiDetailMutasi([
        'jumlah_kirim' => 3, 'jumlah_terima' => 3, 'harga_mutasi' => 0, 'hpp_barang' => 500,
    ]));
}
```

Tambah dua test:

```php
public function testNilaiDetailMutasiMemakaiJumlahTerima(): void
{
    $this->assertSame(3000, $this->kasbank->nilaiDetailMutasi([
        'jumlah_kirim' => 5, 'jumlah_terima' => 3, 'harga_mutasi' => 1000,
    ]));
}

public function testNilaiDetailMutasiTanpaJumlahTerimaBernilaiNol(): void
{
    $this->assertSame(0, $this->kasbank->nilaiDetailMutasi([
        'jumlah_kirim' => 5, 'harga_mutasi' => 1000,
    ]));
}
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit tests/KasBankTest.php --filter 'NilaiDetailMutasi' --colors=never`

Expected: FAIL pada `testNilaiDetailMutasiMemakaiJumlahTerima` karena masih 5000.

- [x] **Step 3: Implementasi**

Di `ModeKasBank::nilaiDetailMutasi()`:

```php
$satuan = (int)($detail['jumlah_terima'] ?? 0);
```

Perbarui docblock menjadi `harga_mutasi × jumlah_terima`.

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `vendor/bin/phpunit tests/KasBankTest.php --filter 'NilaiDetailMutasi' --colors=never`

Expected: PASS.

- [x] **Step 5: Commit**

```bash
git add app/Libraries/ModeKasBank.php tests/KasBankTest.php
git commit -m "fix(mutasi): tagih hutang piutang berdasarkan jumlah terima"
```

---

### Task 2: KPI `FIN_HUTANG_PIUTANG` mencakup mutasi antar unit

**Files:**
- Create: `app/Services/Finance/MutasiTimelinessCalculator.php`
- Modify: `app/Services/Finance/FinanceKpiCalculationService.php:43-48, 70-85, 150-180`
- Modify: `app/Services/Finance/FinanceKpiService.php` (helper statis baru)
- Test: `tests/MutasiTimelinessCalculatorTest.php`
- Test: `tests/unit/FinanceKpiTest.php`

**Interfaces:**
- Produces: `MutasiTimelinessCalculator::calculate(int $unitId, int $month, int $year): array` dengan bentuk `score`, `status`, `detail`.
- Produces: `FinanceKpiService::gabungSkorHutangPiutang(?float $hutang, ?float $piutang, ?float $mutasi): array{score:?float,status:string,notes:string}`.

- [x] **Step 1: Tulis test integrasi calculator**

Buat `tests/MutasiTimelinessCalculatorTest.php`:

```php
<?php

namespace Tests;

use App\Services\Finance\MutasiTimelinessCalculator;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class MutasiTimelinessCalculatorTest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->db->query('DROP TABLE IF EXISTS db_hutang_piutang');
        $this->db->query('DROP TABLE IF EXISTS db_pembayaran_hutang_piutang');
        $this->db->query('CREATE TABLE db_hutang_piutang (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kode TEXT NULL, jenis TEXT NULL, sumber_tipe TEXT NULL,
            tanggal TEXT NULL, jatuh_tempo TEXT NULL,
            total REAL NULL, total_dibayar REAL NULL, sisa REAL NULL,
            status TEXT NULL, unit_id INT NULL, deleted INT DEFAULT 0,
            scope TEXT DEFAULT \'active\'
        )');
        $this->db->query('CREATE TABLE db_pembayaran_hutang_piutang (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            hutang_piutang_id INT NULL, tanggal_bayar TEXT NULL, jumlah_bayar REAL NULL
        )');
    }

    protected function tearDown(): void
    {
        $this->db->query('DROP TABLE IF EXISTS db_hutang_piutang');
        $this->db->query('DROP TABLE IF EXISTS db_pembayaran_hutang_piutang');
        parent::tearDown();
    }

    public function testHitungKetepatanMutasiAntarUnit(): void
    {
        $rows = [
            [1, 'H-1', 'hutang', 'mutasi_unit', '2026-10-06', '2026-10-10', 0, 'lunas', 1, 0, 'active'],
            [2, 'H-2', 'hutang', 'mutasi_unit', '2026-10-06', '2026-10-10', 100, 'belum_lunas', 1, 0, 'active'],
            [3, 'H-3', 'hutang', 'mutasi_unit', '2026-10-06', '2026-10-31', 100, 'belum_lunas', 1, 0, 'active'],
            [4, 'H-4', 'hutang', 'mutasi_unit', '2026-10-06', '2026-10-10', 100, 'belum_lunas', 2, 0, 'active'],
            [5, 'P-1', 'piutang', 'mutasi_unit', '2026-10-06', '2026-10-10', 100, 'belum_lunas', 1, 0, 'active'],
            [6, 'H-5', 'hutang', 'mutasi_unit', '2026-10-06', '2026-10-10', 100, 'belum_lunas', 1, 0, 'legacy'],
            [7, 'H-6', 'hutang', 'mutasi_unit', '2026-10-05', '2026-10-10', 100, 'belum_lunas', 1, 0, 'active'],
            [8, 'H-7', 'hutang', 'pembelian', '2026-10-06', '2026-10-10', 100, 'belum_lunas', 1, 0, 'active'],
            [9, 'H-8', 'hutang', 'mutasi_unit', '2026-10-06', '2026-10-10', 100, 'belum_lunas', 1, 1, 'active'],
            [10, 'H-9', 'hutang', 'mutasi_unit', '2026-10-06', '2026-10-10', 0, 'lunas', 1, 0, 'active'],
        ];
        foreach ($rows as $r) {
            $this->db->table('hutang_piutang')->insert([
                'id' => $r[0], 'kode' => $r[1], 'jenis' => $r[2], 'sumber_tipe' => $r[3],
                'tanggal' => $r[4], 'jatuh_tempo' => $r[5], 'sisa' => $r[6],
                'status' => $r[7], 'unit_id' => $r[8], 'deleted' => $r[9], 'scope' => $r[10],
            ]);
        }
        $this->db->table('pembayaran_hutang_piutang')->insert([
            'hutang_piutang_id' => 1, 'tanggal_bayar' => '2026-10-09', 'jumlah_bayar' => 100,
        ]);

        $result = (new MutasiTimelinessCalculator())->calculate(1, 10, 2026);

        $this->assertSame(50.0, $result['score']);
        $this->assertSame('ok', $result['status']);
        $this->assertSame(1, $result['detail']['tepat']);
        $this->assertSame(1, $result['detail']['terlambat']);
        $this->assertSame(1, $result['detail']['open']);
        $this->assertSame(1, $result['detail']['tanpa_bukti']);
        $this->assertSame(2, $result['detail']['dinilai']);
    }

    public function testTanpaMutasiJatuhTempoMenghasilkanNull(): void
    {
        $result = (new MutasiTimelinessCalculator())->calculate(1, 10, 2026);

        $this->assertNull($result['score']);
        $this->assertSame('data_kosong', $result['status']);
        $this->assertSame(0, $result['detail']['dinilai']);
    }
}
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit tests/MutasiTimelinessCalculatorTest.php --colors=never`

Expected: FAIL `Class "App\Services\Finance\MutasiTimelinessCalculator" not found`.

- [x] **Step 3: Implementasi calculator**

Buat `MutasiTimelinessCalculator implements FinanceCalculatorInterface`.

Query:
- `hutang_piutang` filter `unit_id = $unitId`, `jenis = 'hutang'`, `sumber_tipe = 'mutasi_unit'`, `scope = 'active'`, `deleted = 0`, `tanggal >= FinanceScopeService::periodeMulaiDate()`, `jatuh_tempo BETWEEN startDate AND endDate`.
- Peta pembayaran terakhir dari `pembayaran_hutang_piutang` per `hutang_piutang_id` (`MAX(tanggal_bayar)`).
- Klasifikasi sama dengan `HutangTimelinessCalculator`: `tepat`, `terlambat`, `open`, `tanpa_bukti`.
- `score = tepat / (tepat + terlambat) * 100`, `null` bila `dinilai = 0`.
- `status = 'ok'` bila `dinilai > 0`, selain itu `'data_kosong'`.
- Detail memuat `start_date`, `end_date`, `tepat`, `terlambat`, `open`, `tanpa_bukti`, `dinilai`, `items`.

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `vendor/bin/phpunit tests/MutasiTimelinessCalculatorTest.php --colors=never`

Expected: PASS 2 test.

- [x] **Step 5: Tulis test helper gabungan**

Tambahkan ke `tests/unit/FinanceKpiTest.php`:

```php
public function testGabungSkorHutangPiutangMemakaiSemuaSumber(): void
{
    $r = FinanceKpiService::gabungSkorHutangPiutang(100.0, 80.0, 60.0);
    $this->assertSame(80.0, $r['score']);
    $this->assertSame('ok', $r['status']);
}

public function testGabungSkorTetapJalanTanpaPiutang(): void
{
    $r = FinanceKpiService::gabungSkorHutangPiutang(100.0, null, 60.0);
    $this->assertSame(80.0, $r['score']);
    $this->assertSame('gap_piutang', $r['status']);
    $this->assertStringContainsString('GAP', $r['notes']);
}

public function testGabungSkorHanyaMutasi(): void
{
    $r = FinanceKpiService::gabungSkorHutangPiutang(null, null, 70.0);
    $this->assertSame(70.0, $r['score']);
    $this->assertSame('ok', $r['status']);
}

public function testGabungSkorTanpaData(): void
{
    $r = FinanceKpiService::gabungSkorHutangPiutang(null, null, null);
    $this->assertNull($r['score']);
    $this->assertSame('data_kosong', $r['status']);
}
```

- [x] **Step 6: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit tests/unit/FinanceKpiTest.php --filter 'GabungSkor' --colors=never`

Expected: FAIL karena method belum ada.

- [x] **Step 7: Implementasi helper dan wiring**

Di `FinanceKpiService` tambahkan helper statis yang:
- mengabaikan skor `null`,
- merata-ratakan skor yang ada,
- `status='gap_piutang'` bila `$piutang === null` dan ada skor lain,
- `status='data_kosong'` bila semua `null`,
- notes menyebut GAP piutang atau gabungan tiga sumber.

Di `FinanceKpiCalculationService`:
- tambah properti `protected $mutasi;` dan `$this->mutasi = new MutasiTimelinessCalculator();`
- tambah `'mutasi_detail' => $this->mutasi->calculate($unitId, $month, $year)` pada return `scorecard()`
- di `case 'hutang_piutang'`, hitung `$mutasi`, panggil `FinanceKpiService::gabungSkorHutangPiutang($h, $p, $mutasi['score'])`, lalu `buildRow()` dengan score/status/notes hasil helper.

- [x] **Step 8: Jalankan semua test KPI, pastikan lulus**

Run: `vendor/bin/phpunit tests/MutasiTimelinessCalculatorTest.php --colors=never`

Run: `vendor/bin/phpunit tests/unit/FinanceKpiTest.php --colors=never`

Expected: PASS.

- [x] **Step 9: Commit**

```bash
git add app/Services/Finance/MutasiTimelinessCalculator.php \
        app/Services/Finance/FinanceKpiCalculationService.php \
        app/Services/Finance/FinanceKpiService.php \
        tests/MutasiTimelinessCalculatorTest.php \
        tests/unit/FinanceKpiTest.php
git commit -m "feat(kpi): masukkan mutasi antar unit ke KPI hutang piutang"
```

---

### Task 3: Renumber nomor nota duplikat dan unique index

**Files:**
- Create: `app/Database/Migrations/2026-10-20-000100_RenumberDanUnikkanNoNotaMutasi.php`
- Test: `tests/MutasiNoNotaUniqueTest.php`

**Interfaces:**
- Produces: nomor unik `no_nota_mutasi`; baris pertama tiap grup tetap, baris berikutnya mendapat suffix `-R2`, `-R3`, dst.
- Produces: unique index `uniq_mutasi_no_nota` pada `mutasi.no_nota_mutasi`.

- [x] **Step 1: Tulis test migration**

Buat `tests/MutasiNoNotaUniqueTest.php`:

```php
<?php

namespace Tests;

use App\Database\Migrations\RenumberDanUnikkanNoNotaMutasi;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class MutasiNoNotaUniqueTest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->db->query('DROP TABLE IF EXISTS db_mutasi');
        $this->db->query('CREATE TABLE db_mutasi (
            idmutasi INTEGER PRIMARY KEY AUTOINCREMENT,
            no_nota_mutasi TEXT NULL
        )');
        $this->db->query("INSERT INTO db_mutasi (idmutasi, no_nota_mutasi) VALUES
            (1, 'A'), (2, 'A'), (3, 'A'), (4, 'B'), (5, 'B'), (6, 'C')");
    }

    protected function tearDown(): void
    {
        $this->db->query('DROP TABLE IF EXISTS db_mutasi');
        parent::tearDown();
    }

    public function testRenumberDanPasangUniqueIndex(): void
    {
        $migration = new RenumberDanUnikkanNoNotaMutasi();
        $migration->up();
        $migration->up(); // idempotent

        $rows = $this->db->table('mutasi')->orderBy('idmutasi')->get()->getResultArray();
        $this->assertSame(['A', 'A-R2', 'A-R3', 'B', 'B-R2', 'C'], array_column($rows, 'no_nota_mutasi'));

        $indexes = $this->db->getIndexData('mutasi');
        $this->assertArrayHasKey('uniq_mutasi_no_nota', $indexes);
    }

    public function testNomorDuplikatDitolakSetelahIndex(): void
    {
        (new RenumberDanUnikkanNoNotaMutasi())->up();

        $this->expectException(\Throwable::class);
        $this->db->table('mutasi')->insert(['no_nota_mutasi' => 'A']);
    }
}
```

- [x] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit tests/MutasiNoNotaUniqueTest.php --colors=never`

Expected: FAIL class migration belum ada.

- [x] **Step 3: Implementasi migration**

Migration `up()`:
1. Ambil semua `idmutasi, no_nota_mutasi` yang tidak null/kosong, urut `idmutasi ASC`.
2. Kelompokkan per nomor.
3. Untuk grup dengan >1 baris: baris pertama tetap; baris berikutnya mendapat `-R2`, `-R3`, dst. Hindari benturan dengan nomor yang sudah ada.
4. Cek index lewat `$this->db->getIndexData($table)`; bila `uniq_mutasi_no_nota` belum ada, jalankan `CREATE UNIQUE INDEX uniq_mutasi_no_nota ON {$table} (no_nota_mutasi)`.
5. `down()` kosong dengan komentar bahwa renumber data tidak auto-revert.

- [x] **Step 4: Jalankan test, pastikan lulus**

Run: `vendor/bin/phpunit tests/MutasiNoNotaUniqueTest.php --colors=never`

Expected: PASS 2 test.

- [x] **Step 5: Backup `mutasi` sebelum migration nyata**

Buat backup di `erp_local`:

```bash
php -r '
$env=[]; foreach(file(".env") as $l){ if(preg_match("/^\\s*database\\.default\\.(hostname|database|username|password|port)\\s*=\\s*(.*)$/",trim($l),$m)) $env[$m[1]]=trim($m[2]," \\t\\r\\n\\\"\x27"); }
$db=new mysqli($env["hostname"],$env["username"],$env["password"],$env["database"],(int)$env["port"]);
$db->query("DROP TABLE IF EXISTS mutasi_backup_20261020");
$db->query("CREATE TABLE mutasi_backup_20261020 AS SELECT * FROM mutasi");
echo "backup rows: ".$db->query("SELECT COUNT(*) c FROM mutasi_backup_20261020")->fetch_assoc()["c"]."\n";
'
```

Expected: `backup rows: 62`.

- [x] **Step 6: Jalankan migration dan verifikasi**

Run: `php spark migrate`

Verifikasi:
```sql
SELECT COUNT(*) FROM mutasi;
SELECT no_nota_mutasi, COUNT(*) c FROM mutasi GROUP BY no_nota_mutasi HAVING c > 1;
SELECT COUNT(*) FROM mutasi WHERE no_nota_mutasi LIKE '%-R%';
SHOW INDEX FROM mutasi WHERE Key_name = 'uniq_mutasi_no_nota';
```

Expected: total 62; query duplikat kosong; 19 baris bersuffix; index ada.

- [x] **Step 7: Commit**

```bash
git add app/Database/Migrations/2026-10-20-000100_RenumberDanUnikkanNoNotaMutasi.php \
        tests/MutasiNoNotaUniqueTest.php
git commit -m "fix(mutasi): renumber nomor nota duplikat dan pasang unique index"
```

---

### Task 4: Perbarui audit dan verifikasi akhir

**Files:**
- Modify: `docs/audit/mutasi-hutang-piutang-kpi-2026-10-19.md`

- [x] **Step 1: Catat keputusan dan hasil**

Perbarui:
- #5: 11 grup/30 baris, 19 baris di-renumber; unique index terpasang.
- #8: ✅ memakai `jumlah_terima`; data lama semua sama sehingga tidak ada perubahan nilai.
- KPI: `FIN_HUTANG_PIUTANG` sekarang menggabungkan Hutang, Piutang, dan Mutasi Antar Unit.
- Verdict: hapus kalimat "KPI buta terhadap utang antar unit".
- Jumlah migrasi batch dan status.

- [x] **Step 2: Jalankan verifikasi terarah**

```bash
vendor/bin/phpunit tests/KasBankTest.php --filter 'NilaiDetailMutasi' --colors=never
vendor/bin/phpunit tests/MutasiTimelinessCalculatorTest.php --colors=never
vendor/bin/phpunit tests/MutasiNoNotaUniqueTest.php --colors=never
vendor/bin/phpunit tests/unit/FinanceKpiTest.php --colors=never
vendor/bin/phpunit tests/HutangPiutangDetailLinkTest.php --colors=never
```

Expected: semua PASS. Full suite tetap punya baseline failure lama; jangan klaim full suite bersih.

- [x] **Step 3: Cek sintaks dan status git**

```bash
php -l app/Libraries/ModeKasBank.php
php -l app/Services/Finance/MutasiTimelinessCalculator.php
php -l app/Services/Finance/FinanceKpiCalculationService.php
php -l app/Services/Finance/FinanceKpiService.php
php -l app/Database/Migrations/2026-10-20-000100_RenumberDanUnikkanNoNotaMutasi.php
git status --short
```

Expected: tidak ada syntax error; `.impeccable/*` dan `docs/template-invoice.pdf` tetap tidak di-stage.

- [x] **Step 4: Commit dokumentasi**

```bash
git add docs/audit/mutasi-hutang-piutang-kpi-2026-10-19.md
git commit -m "docs(audit): catat keputusan tagihan, KPI mutasi, dan nomor nota"
```

## Review Focus

- Mutasi dengan `jumlah_terima = 0` atau belum diterima tidak boleh menghasilkan tagihan; test `testNilaiDetailMutasiTanpaJumlahTerimaBernilaiNol` mengunci ini.
- Pembayaran tepat waktu dengan `tanggal_bayar` sama dengan `jatuh_tempo` harus dihitung tepat; calculator memakai `<=`.
- Baris `scope='legacy'`, `deleted=1`, sisi piutang, dan sumber selain `mutasi_unit` tidak boleh masuk KPI; test integrasi mengunci filter ini.
- Renumber harus idempotent dan tidak menabrak nomor yang sudah bersuffix; test memanggil `up()` dua kali.
- Unique index harus tetap mengizinkan nomor `NULL` di masa depan, tetapi migration hanya memasang index pada kolom yang sudah tidak punya duplikat.
