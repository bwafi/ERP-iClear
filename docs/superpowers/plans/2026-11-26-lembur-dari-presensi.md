# Lembur dari Presensi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hitung lembur otomatis dari roster `shift = 'FULL'` yang tanggalnya sudah lewat untuk Teknisi (36) dan Kepala Toko (41), Rp35.000/hari, lalu pakai angka yang sama di payroll dan `/penilaian_kinerja` tanpa snapshot.

> **Revisi 2026-11-26:** rencana awal memakai `presensi.sesi = 'FULL'`. Ternyata sesi itu hanya mode punch (semua shift non-PS menulis FULL), sehingga hari kerja biasa ikut terhitung lembur. Aturan final: sumber `presensi_roster.shift = 'FULL'`, hanya `tanggal < hari ini`. Langkah task di bawah tetap menjadi catatan historis; test dan implementasi sudah memakai aturan final.

**Architecture:** Satu service `AttendanceOvertimeService` menjadi sumber tunggal. `PayrollGenerator` memakainya untuk periode `>= 2026-11-01` (sebelumnya tetap `kas_keluar`), dan `PenilaianKPI::salaryExtras()` memakai service yang sama. Tidak ada perubahan schema.

**Tech Stack:** CodeIgniter 4.4.8, PHP 7.4 (Docker `erp-php74:local`), PHPUnit dengan database `erp_test` MySQLi.

**Spec:** `docs/superpowers/specs/2026-11-26-lembur-dari-presensi-design.md`

## Global Constraints

- Jangan `git commit` sebelum user menginstruksikan secara eksplisit (AGENTS.md D).
- Jangan mengubah schema database, payroll `dibayar`, jurnal, atau data historis.
- Test hanya boleh memakai database `erp_test` lewat `phpunit.xml`; jangan arahkan ke `erp_local`.
- Kode harus kompatibel PHP 7.4: tanpa `match`, union type, named argument, atau properti readonly.
- Penerima lembur hanya jabatan `36` dan `41`; unit harus `is_operational = 1` dan `ID_UNIT != 50`.
- Satu tanggal dihitung sekali; `waktu_pulang` boleh kosong; `sesi` harus persis `'FULL'`.
- Jangan menambah kolom/tabel baru. Rincian hari ditulis di `finance_payroll.notes`.
- Jangan mengubah perilaku periode sebelum `2026-11-01` (tetap `kas_keluar`).
- Jika commit diminta, format Conventional Commits: `feat(payroll): ... (refs #246)`.

## Review Focus

- `presensi.sesi` bernilai `NULL`, `'full'`, atau ada spasi → hanya `'FULL'` persis yang dihitung; test ada di Task 1.
- Punch di batas bulan (`2026-10-31 23:xx`, `2026-11-30 23:xx`, `2026-12-01 00:xx`) → masuk bulan berdasarkan `waktu_masuk` dan batas `< awal bulan berikutnya`; test ada di Task 1.
- `kas_keluar.penerima` berisi nama, bukan angka → legacy dan peringatan manual harus mengabaikannya, bukan menghitung 0 diam-diam; test ada di Task 1.
- Pegawai punya lebih dari satu baris payroll dalam sebulan → lembur tidak boleh bertambah dua kali; test ada di Task 2.
- Pegawai layak gaji tetapi tanpa presensi FULL → gaji normal, lembur 0; test ada di Task 2.

---

### Task 1: Service `AttendanceOvertimeService`

**Files:**
- Create: `app/Services/Payroll/AttendanceOvertimeService.php`
- Create: `tests/AttendanceOvertimeServiceTest.php`

**Interfaces:**
- Produces:
  - `AttendanceOvertimeService::TARIF_HARIAN` = `35000`
  - `AttendanceOvertimeService::JABATAN_PENERIMA` = `[36, 41]`
  - `AttendanceOvertimeService::MULAI_SUMBER_PRESENSI` = `'2026-11-01'`
  - `__construct(?BaseConnection $db = null)`
  - `sumberPeriode(string $bulan): string` → `'presensi'` atau `'kas_keluar'`; `InvalidArgumentException` bila format bukan `YYYY-MM`
  - `ringkasanPeriode(string $bulan): array<int, array{hari:int, tanggal:list<string>, nominal:int}>` → `[]` untuk periode legacy
  - `untukPegawai(int $employeeId, string $bulan): array{hari:int, tanggal:list<string>, nominal:int}`
  - `laporan(string $bulan, ?int $pegawaiId = null): list<array{pegawai_id:int, nama:string, jabatan_id:int, hari:int, tanggal:list<string>, nominal:int}>`
  - `adaLemburManual(string $bulan): bool`

- [ ] **Step 1: Write the failing test**

Create `tests/AttendanceOvertimeServiceTest.php`. Mirrors the schema-rebuild style of `tests/PayrollGeneratorUnitTest.php`; create only `unit`, `akun`, `presensi`, `kas_keluar`.

```php
<?php

namespace Tests;

use App\Services\Payroll\AttendanceOvertimeService;
use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;

final class AttendanceOvertimeServiceTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->buatSkema();
        $this->sebarData();
    }

    protected function tearDown(): void
    {
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        foreach (['presensi', 'kas_keluar', 'akun', 'unit'] as $t) {
            $q('DROP TABLE IF EXISTS ' . $t);
        }

        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL, is_operational INT NULL)');
        $q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN TEXT NULL, TERIMA_GAJI INT NULL,
                STATUS_PEGAWAI INT NULL, deleted TEXT NULL)');
        $q('CREATE TABLE presensi (
                idpresensi INT PRIMARY KEY AUTO_INCREMENT, akun_idakun INT NULL,
                unit_idunit INT NULL, waktu_masuk TEXT NULL,
                waktu_pulang TEXT NULL, sesi TEXT NULL)');
        $q('CREATE TABLE kas_keluar (
                idkas_keluar INT PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL,
                kategori_idkategori INT NULL, deskripsi TEXT NULL,
                jumlah REAL NULL, penerima TEXT NULL, idunit INT NULL)');
    }

    private function sebarData(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        $q("INSERT INTO unit (idunit, NAMA_UNIT, is_operational) VALUES
            (1, 'ICLEAR Cabang', 1), (50, 'Head Office', 0)");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, TERIMA_GAJI, STATUS_PEGAWAI, deleted) VALUES
            (1, 1, 36, 'Teknisi Satu', 1, 1, NULL),
            (2, 1, 41, 'Kepala Toko', 1, 1, NULL),
            (3, 1, 40, 'SPV', 1, 1, NULL),
            (4, 50, 36, 'Teknisi HO', 1, 1, NULL),
            (5, 1, 36, 'Teknisi Resign', 1, 0, NULL),
            (6, 1, 36, 'Teknisi Tanpa Gaji', 0, 1, NULL),
            (7, 1, 49, 'Teknisi Mesin', 1, 1, NULL),
            (8, 1, 36, 'Teknisi Dihapus', 1, 1, '1')");

        $q("INSERT INTO presensi (akun_idakun, unit_idunit, waktu_masuk, waktu_pulang, sesi) VALUES
            (1, 1, '2026-11-03 08:50:00', NULL, 'FULL'),
            (1, 1, '2026-11-04 08:50:00', '2026-11-04 21:00:00', 'FULL'),
            (1, 1, '2026-11-04 09:10:00', '2026-11-04 21:00:00', 'FULL'),
            (1, 1, '2026-11-05 08:50:00', '2026-11-05 12:00:00', 'PAGI'),
            (1, 1, '2026-11-05 12:50:00', '2026-11-05 21:00:00', 'SORE'),
            (1, 1, '2026-11-06 08:50:00', '2026-11-06 21:00:00', NULL),
            (1, 1, '2026-10-31 23:30:00', NULL, 'FULL'),
            (1, 1, '2026-12-01 00:10:00', NULL, 'FULL'),
            (2, 1, '2026-11-07 08:50:00', NULL, 'FULL'),
            (3, 1, '2026-11-08 08:50:00', NULL, 'FULL'),
            (4, 1, '2026-11-09 08:50:00', NULL, 'FULL'),
            (7, 1, '2026-11-10 08:50:00', NULL, 'FULL')");

        $q("INSERT INTO kas_keluar (tanggal, kategori_idkategori, deskripsi, jumlah, penerima, idunit) VALUES
            ('2026-11-11', 10, 'LEMBUR', 35000, '1', 1),
            ('2026-11-12', 10, 'LEMBUR', 35000, 'Budi', 1),
            ('2026-10-12', 10, 'LEMBUR', 35000, '2', 1)");
    }

    public function testRingkasanHanyaJabatanDanUnitYangLayak(): void
    {
        $hasil = (new AttendanceOvertimeService($this->db))->ringkasanPeriode('2026-11');

        $this->assertSame([1, 2], array_keys($hasil));
        $this->assertSame(2, $hasil[1]['hari'], 'dua tanggal unik; baris ganda tidak menambah hari');
        $this->assertSame(70000, $hasil[1]['nominal']);
        $this->assertSame(1, $hasil[2]['hari']);
        $this->assertSame(35000, $hasil[2]['nominal']);
    }

    public function testSesiSelainFullTidakDihitung(): void
    {
        $tanggal = (new AttendanceOvertimeService($this->db))
            ->untukPegawai(1, '2026-11')['tanggal'];

        $this->assertNotContains('2026-11-05', $tanggal, 'PAGI/SORE bukan FULL');
        $this->assertNotContains('2026-11-06', $tanggal, 'sesi NULL bukan FULL');
    }

    public function testBatasBulanMemakaiWaktuMasuk(): void
    {
        $tanggal = (new AttendanceOvertimeService($this->db))
            ->untukPegawai(1, '2026-11')['tanggal'];

        $this->assertNotContains('2026-10-31', $tanggal);
        $this->assertNotContains('2026-12-01', $tanggal);
    }

    public function testPegawaiTidakLayakTidakMuncul(): void
    {
        $hasil = (new AttendanceOvertimeService($this->db))->ringkasanPeriode('2026-11');

        foreach ([3, 4, 5, 6, 7, 8] as $id) {
            $this->assertArrayNotHasKey($id, $hasil, 'akun ' . $id . ' tidak boleh dapat lembur');
        }
    }

    public function testSumberPeriodeMenghormatiCutoff(): void
    {
        $svc = new AttendanceOvertimeService($this->db);

        $this->assertSame('kas_keluar', $svc->sumberPeriode('2026-10'));
        $this->assertSame('presensi', $svc->sumberPeriode('2026-11'));
    }

    public function testFormatBulanTidakValidDitolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AttendanceOvertimeService($this->db))->ringkasanPeriode('2026-1');
    }

    public function testAdaLemburManualHanyaPenerimaAngkaYangLayak(): void
    {
        $svc = new AttendanceOvertimeService($this->db);

        $this->assertTrue($svc->adaLemburManual('2026-11'), 'penerima "1" adalah pegawai layak');
        $this->assertFalse($svc->adaLemburManual('2026-10'), 'periode legacy tidak memicu peringatan');

        $this->db->table('kas_keluar')->where('penerima', '1')->delete();
        $this->assertFalse($svc->adaLemburManual('2026-11'), 'penerima nama harus diabaikan');
    }

    public function testLaporanMemuatNamaDanTanggal(): void
    {
        $laporan = (new AttendanceOvertimeService($this->db))->laporan('2026-11');

        $this->assertCount(2, $laporan);
        $this->assertSame('Teknisi Satu', $laporan[0]['nama']);
        $this->assertSame(36, $laporan[0]['jabatan_id']);
        $this->assertSame(['2026-11-03', '2026-11-04'], $laporan[0]['tanggal']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run inside the app container:

```bash
docker exec erp-iclear-erp-app-1 sh -c 'cd /app && vendor/bin/phpunit -c phpunit.xml tests/AttendanceOvertimeServiceTest.php'
```

Expected: FAIL, `Class "App\Services\Payroll\AttendanceOvertimeService" not found`.

- [ ] **Step 3: Implement the service**

Create `app/Services/Payroll/AttendanceOvertimeService.php`:

- `pegawaiLayak(): array<int>` runs one query on `akun a JOIN unit u` with `ID_JABATAN IN (36,41)`, `STATUS_PEGAWAI = 1`, `(deleted IS NULL OR deleted = '')`, `TERIMA_GAJI = 1`, `u.is_operational = 1`, `a.ID_UNIT != 50`, returning `ID_AKUN`.
- `ringkasanPeriode()` returns `[]` unless `sumberPeriode($bulan) === 'presensi'`. Otherwise query `presensi` with `sesi = 'FULL'`, `waktu_masuk >= "$bulan-01 00:00:00"`, `waktu_masuk < awal bulan berikutnya`, `whereIn('akun_idakun', $pegawaiLayak)`, `select('akun_idakun AS pegawai_id, DATE(waktu_masuk) AS tanggal')`, `groupBy(['akun_idakun', 'DATE(waktu_masuk)'])`. Folder hasil per pegawai dengan `hari++` dan `nominal += self::TARIF_HARIAN`.
- `untukPegawai()` returns `ringkasanPeriode($bulan)[$employeeId] ?? ['hari'=>0,'tanggal'=>[],'nominal'=>0]`.
- `laporan()` builds rows from `ringkasanPeriode()` plus `NAMA_AKUN`/`ID_JABATAN` from `akun`; filter by `$pegawaiId` when given; sort by `jabatan_id`, then `nama`.
- `adaLemburManual()` returns `false` for legacy. For presensi era, fetch `kas_keluar` kategori 10, `tanggal` dalam bulan, `deskripsi LIKE '%lembur%'`, lalu `true` bila ada `penerima` yang `ctype_digit()` dan termasuk `pegawaiLayak()`.
- Validate month format with `preg_match('/^\d{4}-\d{2}$/', $bulan)`; throw `InvalidArgumentException('Format bulan harus YYYY-MM.')` otherwise.

- [ ] **Step 4: Run test to verify it passes**

```bash
docker exec erp-iclear-erp-app-1 sh -c 'cd /app && vendor/bin/phpunit -c phpunit.xml tests/AttendanceOvertimeServiceTest.php'
```

Expected: PASS, 8 tests.

- [ ] **Step 5: Lint and inspect diff**

```bash
docker exec erp-iclear-erp-app-1 sh -c 'cd /app && php -l app/Services/Payroll/AttendanceOvertimeService.php'
git diff --check
```

---

### Task 2: Integrasi `PayrollGenerator`

**Files:**
- Modify: `app/Services/Payroll/PayrollGenerator.php:108,293-340,401-443,500-530`
- Modify: `tests/PayrollGeneratorUnitTest.php` (schema + seed + tests)

**Interfaces:**
- Consumes: `AttendanceOvertimeService::sumberPeriode/ringkasanPeriode`.
- Produces: `protected function lemburPeriode(string $bulan, string $from, string $to): array{nominal:array<int,float>, hari:array<int,int>}`.

- [ ] **Step 1: Extend the test schema and seed**

In `tests/PayrollGeneratorUnitTest.php`:
- Add `presensi` to the drop list and create the table (same columns as Task 1).
- Add `is_operational INT NULL` to the `unit` table definition and set unit 3 → `1`.
- Add `presensi` rows for November 2026:
  - akun `501` (Kepala Toko): `2026-11-03` FULL tanpa pulang, `2026-11-04` FULL dua baris (untuk uji dedupe).
  - akun `502` (nonaktif): FULL `2026-11-05`.
  - akun `503` (tanpa gaji): FULL `2026-11-06`.

- [ ] **Step 2: Write the failing tests**

Append to `tests/PayrollGeneratorUnitTest.php`:

```php
public function testNovemberMemakaiPresensiDanMenulisRincianHari(): void
{
    $hasil = (new PayrollGenerator())->generate('2026-11', ['unit_ids' => [3], 'overwrite' => true]);

    $row = $this->db->table('finance_payroll')->where('pegawai_id', 501)->get()->getRow();
    $this->assertNotNull($row);
    $this->assertStringContainsString('2 hari × Rp 35.000 = Rp 70.000', (string) $row->notes);
    $this->assertSame(2420000.0, (float) $row->total, 'gaji 2.350.000 + lembur 70.000');
    $this->assertSame(1, $hasil['created']);
}

public function testLemburKasKeluarDiabaikanPadaPeriodePresensi(): void
{
    $this->db->query("INSERT INTO kas_keluar (tanggal, kategori_idkategori, deskripsi, jumlah, penerima, idunit)
        VALUES ('2026-11-10', 10, 'LEMBUR', 999000, '501', 3)");

    $baris = (new PayrollGenerator())->hitungGajiUnit('2026-11', 3)[0];

    $this->assertSame(70000.0, $baris['lembur'], 'manual 999.000 tidak boleh ikut');
}

public function testKaryawanTanpaPresensiFullTetapDigajiTanpaLembur(): void
{
    $this->db->query("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ALAMAT, TERIMA_GAJI, STATUS_PEGAWAI, deleted)
        VALUES (506, 3, 41, 'Tanpa Presensi', 'Jalan Test', 1, 1, NULL)");

    $hasil = (new PayrollGenerator())->hitungGajiUnit('2026-11', 3);
    $baris = array_values(array_filter($hasil, fn ($r) => $r['id_akun'] === 506))[0];

    $this->assertSame(0.0, $baris['lembur']);
    $this->assertGreaterThan(0.0, $baris['total']);
}

public function testBarisKeduaTidakMendapatLemburGanda(): void
{
    $this->db->query("INSERT INTO finance_payroll
        (id, unit_id, pegawai_id, due_date, status, total, notes, sumber, created_at, updated_at)
        VALUES (10, 3, 501, '2026-11-30', 'rencana', 100, 'baris pertama', 'auto', '2026-11-01', '2026-11-01'),
               (11, 3, 501, '2026-11-30', 'rencana', 200, 'baris kedua', 'auto', '2026-11-01', '2026-11-01')");

    (new PayrollGenerator())->generate('2026-11', ['unit_ids' => [3], 'overwrite' => true]);

    $kedua = $this->db->table('finance_payroll')->where('id', 11)->get()->getRow();
    $this->assertSame(200.0, (float) $kedua->total, 'baris kedua tidak boleh ikut dihitung');
}

public function testBarisDibayarNovemberTidakBerubah(): void
{
    $this->db->query("INSERT INTO finance_payroll
        (id, unit_id, pegawai_id, due_date, status, total, notes, sumber, created_at, updated_at)
        VALUES (12, 3, 501, '2026-11-30', 'dibayar', 500000, 'sudah dibayar', 'auto', '2026-11-01', '2026-11-01')");

    (new PayrollGenerator())->generate('2026-11', ['unit_ids' => [3], 'overwrite' => true]);

    $row = $this->db->table('finance_payroll')->where('id', 12)->get()->getRow();
    $this->assertSame(500000.0, (float) $row->total);
    $this->assertSame('sudah dibayar', $row->notes);
}
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
docker exec erp-iclear-erp-app-1 sh -c 'cd /app && vendor/bin/phpunit -c phpunit.xml tests/PayrollGeneratorUnitTest.php'
```

Expected: new tests FAIL (`lembur` still 0 or manual 999.000 counted).

- [ ] **Step 4: Implement the source switch**

In `PayrollGenerator`:
- Rename `daftarLembur()` → `daftarLemburKasKeluar()` without changing its body; update its docblock.
- Add:

```php
protected function lemburPeriode(string $bulan, string $from, string $to): array
{
    $service = new AttendanceOvertimeService($this->db);

    if ($service->sumberPeriode($bulan) !== 'presensi') {
        return ['nominal' => $this->daftarLemburKasKeluar($from, $to), 'hari' => []];
    }

    $ringkasan = $service->ringkasanPeriode($bulan);

    return [
        'nominal' => array_map(static fn ($r) => (float) $r['nominal'], $ringkasan),
        'hari'    => array_map(static fn ($r) => (int) $r['hari'], $ringkasan),
    ];
}
```

- In `generate()` replace `$lembur = $this->daftarLembur($from, $to);` with `$lembur = $this->lemburPeriode($bulan, $from, $to);`. Use `$lembur['nominal'][$id] ?? 0.0` and `$lembur['hari'][$id] ?? 0`.
- In `hitungGajiUnit()` do the same.
- Change `susunCatatan(array $gaji, float $lembur)` to `susunCatatan(array $gaji, float $lembur, int $lemburHari = 0)`. In the non-probation branch replace the `$lembur` line with:

```php
if ($lembur != 0.0) {
    $bagian[] = $lemburHari > 0
        ? 'Lembur ' . $lemburHari . ' hari × Rp 35.000 = ' . $this->rupiah($lembur)
        : 'Lembur ' . $this->rupiah($lembur);
}
```

Leave the probation branch unchanged.

- [ ] **Step 5: Run tests to verify they pass**

```bash
docker exec erp-iclear-erp-app-1 sh -c 'cd /app && vendor/bin/phpunit -c phpunit.xml tests/PayrollGeneratorUnitTest.php'
```

Expected: PASS, semua test lama + 5 test baru.

- [ ] **Step 6: Lint**

```bash
docker exec erp-iclear-erp-app-1 sh -c 'cd /app && php -l app/Services/Payroll/PayrollGenerator.php'
```

---

### Task 3: Papan payroll & peringatan lembur manual

**Files:**
- Modify: `app/Services/Payroll/PayrollRunSummary.php:17`
- Modify: `app/Controllers/Payroll.php:218-220` and view data
- Modify: `app/Views/jurnal/payroll.php` (setelah `<div class="payroll-page">`)
- Modify: `tests/PayrollRunSummaryTest.php`

**Interfaces:**
- Consumes: `AttendanceOvertimeService::ringkasanPeriode`, `adaLemburManual`.
- Produces: `PayrollRunSummary::build(array $payrollRows, array $bonusRows = [], ?string $today = null, array $lemburOtomatis = [])`.

- [ ] **Step 1: Write the failing test**

Append to `tests/PayrollRunSummaryTest.php`:

```php
public function testLemburOtomatisMasukBucketBonus(): void
{
    $summary = PayrollRunSummary::build([], [], '2026-11-26', ['count' => 3, 'total' => 105000]);

    $this->assertSame(3, $summary['run']['bonus']['count']);
    $this->assertSame(105000, $summary['run']['bonus']['total']);
}

public function testTanpaLemburOtomatisBucketBonusTetap(): void
{
    $summary = PayrollRunSummary::build([], [], '2026-11-26');

    $this->assertSame(0, $summary['run']['bonus']['count']);
    $this->assertSame(0, $summary['run']['bonus']['total']);
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker exec erp-iclear-erp-app-1 sh -c 'cd /app && vendor/bin/phpunit -c phpunit.xml tests/PayrollRunSummaryTest.php'
```

Expected: FAIL, argumen ke-4 belum ada / count tetap 0.

- [ ] **Step 3: Implement**

- `PayrollRunSummary::build()` tambah parameter `array $lemburOtomatis = []`; setelah loop `$bonusRows`, tambahkan `$run['bonus']['count'] += (int) ($lemburOtomatis['count'] ?? 0);` dan `$run['bonus']['total'] += (int) ($lemburOtomatis['total'] ?? 0);`.
- `Payroll::index()`: setelah `$kas_keluar` diambil dan sebelum `PayrollRunSummary::build`, hitung:

```php
$lemburOtomatis = ['count' => 0, 'total' => 0];
$lemburManual = false;
if (!$showAll) {
    $overtime = new \App\Services\Payroll\AttendanceOvertimeService($this->db);
    foreach ($overtime->ringkasanPeriode($bulan) as $ringkasan) {
        $lemburOtomatis['count']++;
        $lemburOtomatis['total'] += (int) $ringkasan['nominal'];
    }
    $lemburManual = $overtime->adaLemburManual($bulan);
}
```

- Panggil `PayrollRunSummary::build($payrollItems, $kas_keluar, null, $lemburOtomatis)`.
- Kirim `'lemburManual' => $lemburManual` di `view('template', [...])`.
- Di `app/Views/jurnal/payroll.php`, tambahkan `$lemburManual = $lemburManual ?? false;` pada blok variabel di atas (dekat `$can_input`). Lalu tepat setelah `<div class="payroll-page">`, tambahkan:

```php
<?php if (!empty($lemburManual)): ?>
<div class="alert alert-warning border-0 mb-3" role="alert">
    Lembur manual terdeteksi pada periode ini dan tidak dihitung lagi di payroll.
</div>
<?php endif; ?>
```

- [ ] **Step 4: Run test + lint**

```bash
docker exec erp-iclear-erp-app-1 sh -c 'cd /app && vendor/bin/phpunit -c phpunit.xml tests/PayrollRunSummaryTest.php'
docker exec erp-iclear-erp-app-1 sh -c 'cd /app && php -l app/Controllers/Payroll.php && php -l app/Views/jurnal/payroll.php'
```

Expected: PASS; lint tanpa error.

---

### Task 4: `PenilaianKPI` dan slip memakai sumber yang sama

**Files:**
- Modify: `app/Controllers/PenilaianKPI.php:1630-1690` (view data) dan `:2370-2450` (`salaryExtras`)
- Modify: `app/Views/penilaian/penilaian_kinerja.php:470-475`
- Modify: `tests/PenilaianThpMatchesSlipTest.php` (schema + test)

**Interfaces:**
- Consumes: `AttendanceOvertimeService::sumberPeriode/untukPegawai/adaLemburManual`.
- Produces: `private function lemburPeriode(int $employeeId, string $startMonth): float`.

- [ ] **Step 1: Add `presensi` to the test schema**

In `tests/PenilaianThpMatchesSlipTest.php`, add `presensi` to the drop list and create it with the same columns as Task 1.

- [ ] **Step 2: Write the failing test**

Append to `tests/PenilaianThpMatchesSlipTest.php`:

```php
public function testSlipNovemberMenghitungLemburDariPresensi(): void
{
    $this->sesi();

    foreach (['2026-11-03', '2026-11-04'] as $tanggal) {
        $this->db->table('presensi')->insert([
            'akun_idakun' => self::PEGAWAI, 'unit_idunit' => self::UNIT,
            'waktu_masuk' => $tanggal . ' 08:50:00',
            'waktu_pulang' => $tanggal . ' 21:00:00',
            'sesi' => 'FULL',
        ]);
    }

    $this->db->table('kas_keluar')->insert([
        'tanggal' => '2026-11-10', 'kategori_idkategori' => 10,
        'deskripsi' => 'LEMBUR', 'jumlah' => 999000,
        'penerima' => (string) self::PEGAWAI, 'idunit' => self::UNIT,
    ]);

    $slip = (string) $this->get(
        'penilaian/slip_gaji/' . self::PEGAWAI . '?bulan=11&tahun=2026',
    )->getBody();

    $this->assertSame(
        70000,
        $this->angka($slip, '/Lembur<\/td>\s*<td>\s*Rp([\d.]+)/s', 'Lembur'),
        'lembur November dari 2 hari FULL, bukan 999.000 manual',
    );
}

public function testSlipOktoberTetapMemakaiKasKeluar(): void
{
    $this->sesi();

    $slip = (string) $this->get(
        'penilaian/slip_gaji/' . self::PEGAWAI . '?bulan=10&tahun=2026',
    )->getBody();

    $this->assertSame(100000, $this->angka($slip, '/Lembur<\/td>\s*<td>\s*Rp([\d.]+)/s', 'Lembur'));
}
```

- [ ] **Step 3: Run test to verify it fails**

```bash
docker exec erp-iclear-erp-app-1 sh -c 'cd /app && vendor/bin/phpunit -c phpunit.xml tests/PenilaianThpMatchesSlipTest.php'
```

Expected: test November FAIL (Lembur 999.000), test Oktober PASS.

- [ ] **Step 4: Implement**

- Tambah `private function lemburPeriode(int $employeeId, string $startMonth): float` di `PenilaianKPI`:

```php
$bulan = substr($startMonth, 0, 7);
$service = new \App\Services\Payroll\AttendanceOvertimeService($this->db);

if ($service->sumberPeriode($bulan) === 'presensi') {
    return (float) $service->untukPegawai($employeeId, $bulan)['nominal'];
}

return (float) ($this->db->table('kas_keluar kk')
    ->selectSum('kk.jumlah', 'total')
    ->where('kk.kategori_idkategori', 10)
    ->where('kk.penerima', $employeeId)
    ->where('kk.tanggal >=', $startMonth)
    ->where('kk.tanggal <=', date('Y-m-t', strtotime($startMonth)))
    ->like('kk.deskripsi', 'lembur')
    ->get()->getRow()->total ?? 0);
```

- Di `salaryExtras()`, ganti `'lembur' => $sum('lembur')` menjadi `'lembur' => $this->lemburPeriode($employeeId, $startMonth)`.
- Di `penilaian_kinerja()`, sebelum `if ($showGaji)`, hitung `$period = $tahun . '-' . str_pad((string) $bulan, 2, '0', STR_PAD_LEFT);` dan tambahkan ke `$viewData`: `'lemburManual' => (new \App\Services\Payroll\AttendanceOvertimeService($this->db))->adaLemburManual($period)`.
- Di `app/Views/penilaian/penilaian_kinerja.php`, tepat di dalam `<?php if ($show_gaji): ?>`, tambahkan `<?php $lemburManual = $lemburManual ?? false; ?>` sebelum kartu “RINCIAN LEMBUR & POTONGAN”, lalu alert yang sama dengan Task 3 memakai `$lemburManual`.

- [ ] **Step 5: Run tests**

```bash
docker exec erp-iclear-erp-app-1 sh -c 'cd /app && vendor/bin/phpunit -c phpunit.xml tests/PenilaianThpMatchesSlipTest.php'
```

Expected: PASS (test lama tetap lulus, 2 test baru lulus). Jika ada kegagalan regex tampilan yang sudah ada sebelumnya, catat sebagai gagal terpisah dan jangan ubah test itu.

- [ ] **Step 6: Lint**

```bash
docker exec erp-iclear-erp-app-1 sh -c 'cd /app && php -l app/Controllers/PenilaianKPI.php && php -l app/Views/penilaian/penilaian_kinerja.php'
```

---

### Task 5: Command dry-run dan dokumentasi PRD

**Files:**
- Create: `app/Commands/PresensiLembur.php`
- Modify: `docs/prd/modul/PRD_MODUL_07_HRD_Payroll.md` (butir 17 dan tambah butir 25)
- Modify: `docs/superpowers/specs/2026-11-26-lembur-dari-presensi-design.md` (status → `disetujui`)

**Interfaces:**
- Consumes: `AttendanceOvertimeService::laporan/sumberPeriode`.
- Produces: CLI `presensi:lembur --bulan=YYYY-MM [--pegawai=ID] [--csv]`.

- [ ] **Step 1: Create the command**

```php
<?php

namespace App\Commands;

use App\Services\Payroll\AttendanceOvertimeService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Dry-run lembur dari presensi. Tidak menulis apa pun.
 *
 * Contoh:
 *   php spark presensi:lembur --bulan=2026-11
 *   php spark presensi:lembur --bulan=2026-11 --pegawai=67 --csv
 */
class PresensiLembur extends BaseCommand
{
    protected $group       = 'presensi';
    protected $name        = 'presensi:lembur';
    protected $description = 'Tampilkan lembur dari presensi untuk Teknisi dan Kepala Toko (dry-run).';

    public function run(array $params): int
    {
        $bulan = (string) (CLI::getOption('bulan') ?: date('Y-m'));

        if (! preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            CLI::error('Format --bulan harus YYYY-MM.');

            return 1;
        }

        $service = new AttendanceOvertimeService();

        if ($service->sumberPeriode($bulan) !== 'presensi') {
            CLI::write('Periode ' . $bulan . ' memakai kas_keluar (sebelum cut-off), bukan presensi.', 'yellow');

            return 0;
        }

        $pegawai = (int) CLI::getOption('pegawai');
        $baris   = $service->laporan($bulan, $pegawai > 0 ? $pegawai : null);

        if ((bool) CLI::getOption('csv')) {
            CLI::write('pegawai;nama;jabatan;hari;tanggal;nominal');
            foreach ($baris as $row) {
                CLI::write(implode(';', [
                    $row['pegawai_id'], $row['nama'], $row['jabatan_id'],
                    $row['hari'], implode('|', $row['tanggal']), $row['nominal'],
                ]));
            }
        } else {
            foreach ($baris as $row) {
                CLI::write(sprintf(
                    '%d %s (jabatan %d): %d hari, Rp %s — %s',
                    $row['pegawai_id'],
                    $row['nama'],
                    $row['jabatan_id'],
                    $row['hari'],
                    number_format($row['nominal'], 0, ',', '.'),
                    implode(', ', $row['tanggal'])
                ));
            }
        }

        $totalHari    = array_sum(array_column($baris, 'hari'));
        $totalNominal = array_sum(array_column($baris, 'nominal'));
        CLI::write(sprintf(
            'Total: %d hari, Rp %s. DRY-RUN: tidak ada data yang ditulis.',
            $totalHari,
            number_format($totalNominal, 0, ',', '.')
        ), 'green');

        return 0;
    }
}
```

- [ ] **Step 2: Verify command is discovered and dry-run does not write**

```bash
docker exec erp-iclear-erp-app-1 sh -c 'cd /app && php spark list | grep presensi:lembur'
docker exec erp-iclear-erp-app-1 sh -c 'cd /app && php spark presensi:lembur --bulan=2026-11 --pegawai=67'
```

Expected: command tampil di daftar; output per pegawai dan total; tidak ada perubahan baris database.

- [ ] **Step 3: Lint**

```bash
docker exec erp-iclear-erp-app-1 sh -c 'cd /app && php -l app/Commands/PresensiLembur.php'
```

- [ ] **Step 4: Update PRD 07**

- Butir 17: ganti frasa `lembur tetap dibayar sesuai kas_keluar kategori SDM` menjadi `lembur dihitung dari presensi untuk periode >= 2026-11-01 (lihat butir 25); periode sebelumnya memakai kas_keluar kategori SDM`.
- Tambah butir 25:

```markdown
25. [✅] **Lembur dari presensi (mulai November 2026).** Lembur dihitung dari `presensi` dengan `sesi = 'FULL'`; `waktu_pulang` boleh kosong, satu tanggal dihitung sekali, dan tarifnya Rp35.000/hari. Penerima hanya jabatan **36 (Teknisi)** dan **41 (Kepala Toko)** di unit `is_operational = 1` dengan `ID_UNIT != 50`, `STATUS_PEGAWAI = 1`, `deleted` kosong, dan `TERIMA_GAJI = 1`. Jabatan 38/40/49, HO, admin, dan jabatan lain tidak pernah menerima lembur. `PayrollGenerator` memakai sumber ini untuk periode >= 2026-11-01; periode sebelumnya tetap memakai `kas_keluar` kategori 10. Nominal masuk `total` payroll dan rincian `N hari × Rp 35.000` ditulis di `notes`; tidak ada kolom snapshot. `/penilaian_kinerja` dan slip memakai service yang sama. Baris payroll `dibayar` tidak dihitung ulang. Lembur manual di `kas_keluar` pada periode presensi diabaikan dan memunculkan peringatan. Command `php spark presensi:lembur --bulan=YYYY-MM` bersifat dry-run. — `AttendanceOvertimeService`, `PayrollGenerator`, `PenilaianKPI::salaryExtras`, `PresensiLembur`; `tests/AttendanceOvertimeServiceTest.php`, `tests/PayrollGeneratorUnitTest.php`, `tests/PayrollRunSummaryTest.php`, `tests/PenilaianThpMatchesSlipTest.php`
```

- [ ] **Step 5: Mark the spec approved**

Ubah baris `**Status:** menunggu persetujuan` menjadi `**Status:** disetujui 2026-11-26`. Tambahkan satu baris di bagian **Integrasi payroll**: `hitungGajiUnit()` (dipakai Tutup Kasir) memakai sumber lembur yang sama.

- [ ] **Step 6: Full verification**

```bash
docker exec erp-iclear-erp-app-1 sh -c 'cd /app && vendor/bin/phpunit -c phpunit.xml tests/AttendanceOvertimeServiceTest.php tests/PayrollGeneratorUnitTest.php tests/PayrollRunSummaryTest.php tests/PenilaianThpMatchesSlipTest.php'
git diff --check
git status --short
```

Expected: semua test terarah PASS; `git diff --check` bersih; hanya file yang direncanakan berubah.

---

## Catatan eksekusi

- Jangan menjalankan `vendor/bin/phpunit` di host: PHP host tidak punya `Locale` dan semua test gagal. Gunakan container `erp-iclear-erp-app-1`.
- Jangan menyentuh `erp_local`. Test memakai `erp_test` dari `phpunit.xml`.
- Jika `PenilaianThpMatchesSlipTest` punya satu kegagalan regex tampilan yang sudah ada sebelum perubahan ini, biarkan; laporkan sebagai kegagalan terpisah.
- Setelah semua task, laporkan hasil test aktual, file yang berubah, dan risiko (angka lembur naik, tidak ada snapshot, kas keluar lembur tidak lagi otomatis). Jangan klaim selesai tanpa output test.
