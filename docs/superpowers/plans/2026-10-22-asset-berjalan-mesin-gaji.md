# /asset_berjalan — Pakai Mesin Gaji Payroll

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Total Gaji di `/asset_berjalan` dihitung oleh mesin yang sama dengan payroll, bukan blok legacy ±870 baris.

**Architecture:** Tambah satu method publik di `PayrollGenerator` (`hitungGajiUnit()`) yang memakai `KpiCalculationService::calculateForSalary()` + `SalaryCalculationService::calculateSalary()` + `daftarLembur()` tanpa menulis apa pun ke database. Controller mengganti seluruh blok hitung gaji dengan satu panggilan method itu, lalu memetakan hasilnya ke `$totalGajiUnit` dan `$gajiDetail`.

**Tech Stack:** PHP 7.4 (`php74`), CodeIgniter 4, PHPUnit 9.6, MySQL `erp_test`.

**Spec:** `docs/audit/asset-berjalan-2026-10-22.md` § Must fix poin 2, dan GitHub issue #18.

## Global Constraints

- Jalankan semua perintah PHP dengan `php74`, bukan `php` (default 8.5 tidak kompatibel).
- Jangan panggil `PayrollGenerator::generate()` dari halaman laporan — method itu menulis baris payroll.
- Lembur hanya boleh dihitung **sekali**: masuk ke Total Gaji, tidak juga ke Pengeluaran (query pengeluaran tetap mengecualikan kategori 10).
- Query karyawan wajib memakai `PayrollGenerator::daftarKaryawan()` (filter `STATUS_PEGAWAI = 1` dan `deleted` kosong) dan melewati `TERIMA_GAJI = 0`.
- Test memakai koneksi `tests` (`erp_test`) dengan DBPrefix `''`, mengikuti pola `tests/OmsetBulananUnitFilterTest.php`.
- Jangan menyentuh `erp_local` dari test. Script verifikasi manual boleh **membaca** `erp_local`.

## Review Focus

- Karyawan tanpa `salary_structures`: total = tunjangan penempatan + insentif + lembur, bukan 0 dan bukan error.
- Karyawan tanpa `ID_JABATAN`/`ID_UNIT`: dilewati dengan log, halaman tidak 500.
- `TERIMA_GAJI = 0`, `STATUS_PEGAWAI = 0`, `deleted` terisi: tidak masuk Total Gaji.
- Lembur bulan terpilih: `tanggal >= YYYY-MM-01` dan `<= akhir bulan`, hanya kategori 10, hanya `penerima` berupa angka.
- Bulan lampau: lembur dan KPI memakai bulan itu, bukan bulan berjalan (sudah dikunci #17).

---

### Task 1: `PayrollGenerator::hitungGajiUnit()`

**Files:**
- Modify: `app/Services/Payroll/PayrollGenerator.php` (tambah method publik setelah `generate()`)
- Test: `tests/PayrollGeneratorUnitTest.php`

**Interfaces:**
- Consumes: `PayrollGenerator::daftarKaryawan(array $unitIds)`, `::daftarLembur(string $from, string $to)`, `::hitungGaji(int $id, array $karyawan, int $bln, string $tahun, float $lembur)` (semua sudah ada).
- Produces:

```php
/**
 * @return array<int, array{
 *   id_akun:int, nama:string, jabatan_id:int,
 *   gaji_pokok:float, tunjangan_kinerja:float, tunjangan_absen:float,
 *   tunjangan_penempatan:float, insentif:float, lembur:float, total:float
 * }>
 */
public function hitungGajiUnit(string $bulan, int $unitId): array
```

- [x] **Step 1: Tulis test yang gagal**

Buat `tests/PayrollGeneratorUnitTest.php`. Fixture membuat tabel unprefixed di `erp_test`, dengan DDL mengikuti `erp_local` (kolom di bawah sudah lengkap untuk service yang dipakai).

```php
<?php

namespace Tests;

use App\Services\Payroll\PayrollGenerator;
use CodeIgniter\Test\CIUnitTestCase;

class PayrollGeneratorUnitTest extends CIUnitTestCase
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

        foreach ([
            'unit', 'akun', 'jabatan', 'kas_keluar',
            'salary_components', 'salary_structures',
            'incentive_members', 'incentive_groups', 'incentive_rules',
            'kpi_weights', 'kpi_components', 'kpi_targets', 'kpi_evaluations',
            'kpi_attendance_detail', 'kpi_attendance_off',
            'penjualan', 'detail_penjualan',
        ] as $t) {
            $q('DROP TABLE IF EXISTS ' . $t);
        }

        // KpiCalculationService::branchUnits() membaca semua unit; OmsetTokoCalculator
        // menjumlah omset per unit, jadi kedua tabel ini wajib ada (boleh kosong).
        $q('CREATE TABLE penjualan (
                idpenjualan INT PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL,
                unit_idunit INT NULL)');
        $q('CREATE TABLE detail_penjualan (
                iddetail_penjualan INT PRIMARY KEY AUTO_INCREMENT,
                penjualan_idpenjualan INT NULL, sub_total REAL NULL,
                hpp_penjualan REAL NULL)');

        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL, KABUPATEN_UNIT TEXT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL)');
        $q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN TEXT NULL, ALAMAT TEXT NULL, TERIMA_GAJI INT NULL,
                STATUS_PEGAWAI INT NULL, deleted TEXT NULL)');
        $q('CREATE TABLE kas_keluar (
                idkas_keluar INT PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL,
                kategori_idkategori INT NULL, deskripsi TEXT NULL, jumlah REAL NULL,
                penerima TEXT NULL, idunit INT NULL)');

        $q('CREATE TABLE salary_components (
                id INT PRIMARY KEY, code TEXT NULL, name TEXT NULL,
                type TEXT NULL, is_active INT NULL)');
        $q('CREATE TABLE salary_structures (
                id INT PRIMARY KEY AUTO_INCREMENT, position_id INT NULL,
                salary_component_id INT NULL, unit_id INT NULL, context TEXT NULL,
                base_value REAL NULL,
                calculation_type TEXT NULL, multiplier REAL NULL,
                effective_from TEXT NULL, effective_to TEXT NULL)');

        $q('CREATE TABLE incentive_groups (id INT PRIMARY KEY, is_active INT NULL)');
        $q('CREATE TABLE incentive_members (
                id INT PRIMARY KEY AUTO_INCREMENT, incentive_group_id INT NULL,
                employee_id INT NULL, is_active INT NULL)');
        $q('CREATE TABLE incentive_rules (
                id INT PRIMARY KEY AUTO_INCREMENT, incentive_group_id INT NULL,
                kpi_component_id INT NULL, base_value REAL NULL,
                effective_from TEXT NULL, effective_to TEXT NULL)');

        $q('CREATE TABLE kpi_components (
                id INT PRIMARY KEY, code TEXT NULL, name TEXT NULL,
                type TEXT NULL, is_active INT NULL)');
        $q('CREATE TABLE kpi_weights (
                id INT PRIMARY KEY AUTO_INCREMENT, kpi_component_id INT NULL,
                position_id INT NULL, weight REAL NULL, weight_group TEXT NULL,
                effective_from TEXT NULL, effective_to TEXT NULL)');
        $q('CREATE TABLE kpi_targets (
                id INT PRIMARY KEY AUTO_INCREMENT, kpi_component_id INT NULL,
                unit_id INT NULL, position_id INT NULL, context TEXT NULL,
                target_value REAL NULL, effective_from TEXT NULL, effective_to TEXT NULL)');
        $q('CREATE TABLE kpi_evaluations (
                id INT PRIMARY KEY AUTO_INCREMENT, employee_id INT NULL,
                kpi_component_id INT NULL, evaluator_id INT NULL,
                evaluation_date TEXT NULL, raw_score REAL NULL,
                period_year INT NULL, period_month INT NULL)');
        $q('CREATE TABLE kpi_attendance_detail (id INT PRIMARY KEY AUTO_INCREMENT, evaluation_id INT NULL)');
        $q('CREATE TABLE kpi_attendance_off (
                id INT PRIMARY KEY AUTO_INCREMENT, employee_id INT NULL,
                evaluation_date TEXT NULL, period_month INT NULL,
                period_year INT NULL, evaluator_id INT NULL)');
    }

    private function sebarData(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        $q("INSERT INTO unit (idunit, NAMA_UNIT, KABUPATEN_UNIT) VALUES (3, 'ICLEAR Banyuwangi', 'Banyuwangi')");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN) VALUES (41, 'Kepala Toko')");
        // 501 aktif & menerima gaji; 502 nonaktif; 503 tidak menerima gaji.
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ALAMAT, TERIMA_GAJI, STATUS_PEGAWAI, deleted) VALUES
            (501, 3, 41, 'Karyawan Aktif', 'Jalan Test', 1, 1, NULL),
            (502, 3, 41, 'Karyawan Resign', 'Jalan Test', 1, 0, NULL),
            (503, 3, 41, 'Tidak Digaji', 'Jalan Test', 0, 1, NULL)");

        // Struktur gaji sengaja beda dari hardcode legacy (1.500.000).
        $q("INSERT INTO salary_components (id, code, name, type, is_active) VALUES
            (1, 'GAJI_POKOK', 'Gaji Pokok', 'base', 1),
            (2, 'TUNJANGAN_KINERJA', 'Tunjangan Kinerja', 'allowance', 1),
            (3, 'TUNJANGAN_ABSEN', 'Tunjangan Absen', 'allowance', 1)");
        $q("INSERT INTO salary_structures (position_id, salary_component_id, unit_id, base_value, calculation_type, multiplier, effective_from, effective_to) VALUES
            (41, 1, NULL, 2000000, 'fixed', NULL, '2026-01-01', NULL),
            (41, 2, NULL, 850000, 'percent_of_kpi', NULL, '2026-01-01', NULL),
            (41, 3, NULL, 250000, 'percent_of_kpi', NULL, '2026-01-01', NULL)");

        // Lembur kategori 10; penerima = ID_AKUN (angka).
        $q("INSERT INTO kas_keluar (tanggal, kategori_idkategori, deskripsi, jumlah, penerima, idunit) VALUES
            ('2026-09-10', 10, 'Lembur closing', 100000, '501', 3)");
    }

    public function testHitungGajiUnitMemakaiMesinPayrollDanLembur(): void
    {
        $hasil = (new PayrollGenerator())->hitungGajiUnit('2026-09', 3);

        $this->assertCount(1, $hasil, 'hanya karyawan aktif & menerima gaji yang dihitung');
        $this->assertSame(501, $hasil[0]['id_akun']);
        $this->assertSame(2000000.0, $hasil[0]['gaji_pokok'], 'pakai salary_structures, bukan hardcode 1.500.000');
        $this->assertSame(0.0, $hasil[0]['tunjangan_kinerja']);
        $this->assertSame(0.0, $hasil[0]['tunjangan_absen']);
        $this->assertSame(350000.0, $hasil[0]['tunjangan_penempatan'], 'alamat beda kabupaten');
        $this->assertSame(100000.0, $hasil[0]['lembur']);
        $this->assertSame(2450000.0, $hasil[0]['total'], '2.000.000 + 350.000 + 100.000');
    }

    /**
     * Tanpa salary_structures, total tetap masuk akal: penempatan + insentif +
     * lembur (bukan 0, bukan error).
     */
    public function testKaryawanTanpaStrukturGajiTetapDihitung(): void
    {
        $this->db->query("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN) VALUES (99, 'Tanpa Struktur')");
        $this->db->query("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ALAMAT, TERIMA_GAJI, STATUS_PEGAWAI, deleted)
            VALUES (504, 3, 99, 'Tanpa Struktur', 'Jalan Test', 1, 1, NULL)");

        $hasil = (new PayrollGenerator())->hitungGajiUnit('2026-09', 3);
        $baris = null;
        foreach ($hasil as $row) {
            if ($row['id_akun'] === 504) {
                $baris = $row;
            }
        }

        $this->assertNotNull($baris, 'karyawan tanpa struktur tidak boleh hilang');
        $this->assertSame(0.0, $baris['gaji_pokok']);
        $this->assertSame(350000.0, $baris['total'], 'hanya tunjangan penempatan');
    }

    /** Jabatan kosong bikin hitungGaji() melempar; unit ini harus melewatinya, bukan 500. */
    public function testKaryawanTanpaJabatanDilewati(): void
    {
        $this->db->query("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ALAMAT, TERIMA_GAJI, STATUS_PEGAWAI, deleted)
            VALUES (505, 3, NULL, 'Tanpa Jabatan', 'Jalan Test', 1, 1, NULL)");

        $hasil = (new PayrollGenerator())->hitungGajiUnit('2026-09', 3);

        $ids = array_column($hasil, 'id_akun');
        $this->assertNotContains(505, $ids, 'akun tanpa jabatan dilewati');
        $this->assertContains(501, $ids, 'karyawan lain tetap dihitung');
    }
}
```

- [x] **Step 2: Jalankan, pastikan gagal**

Run: `php74 vendor/bin/phpunit --filter PayrollGeneratorUnitTest`
Expected: FAIL/error karena `hitungGajiUnit()` belum ada.

- [x] **Step 3: Implementasikan `hitungGajiUnit()`**

Tambahkan setelah `generate()` di `PayrollGenerator.php`:

```php
/**
 * Gaji per karyawan satu unit untuk satu bulan, TANPA menulis payroll.
 * Dipakai laporan /asset_berjalan supaya angkanya sama dengan payroll.
 *
 * @return array<int, array{
 *   id_akun:int, nama:string, jabatan_id:int,
 *   gaji_pokok:float, tunjangan_kinerja:float, tunjangan_absen:float,
 *   tunjangan_penempatan:float, insentif:float, lembur:float, total:float
 * }>
 */
public function hitungGajiUnit(string $bulan, int $unitId): array
{
    if (! preg_match('/^\d{4}-\d{2}$/', $bulan)) {
        throw new \InvalidArgumentException('Format bulan harus YYYY-MM.');
    }

    $tahun = (int) substr($bulan, 0, 4);
    $bln   = (int) substr($bulan, 5, 2);
    $from  = $bulan . '-01';
    $to    = date('Y-m-t', strtotime($from));
    $lembur = $this->daftarLembur($from, $to);

    $hasil = [];

    foreach ($this->daftarKaryawan([$unitId]) as $karyawan) {
        if ((int) ($karyawan['TERIMA_GAJI'] ?? 1) === 0) {
            continue;
        }

        $id = (int) $karyawan['ID_AKUN'];

        try {
            $gaji = $this->hitungGaji($id, $karyawan, $bln, (string) $tahun, $lembur[$id] ?? 0.0);
        } catch (\Throwable $e) {
            log_message('error', 'PayrollGenerator: hitung gaji #' . $id . ' gagal: ' . $e->getMessage());
            continue;
        }

        // amount per component_code; kode yang tidak ada dianggap 0.
        $komponen = array_column($gaji['components'], 'amount', 'component_code');

        $hasil[] = [
            'id_akun'              => $id,
            'nama'                 => (string) $karyawan['NAMA_AKUN'],
            'jabatan_id'           => (int) ($karyawan['ID_JABATAN'] ?? 0),
            'gaji_pokok'           => (float) ($komponen['GAJI_POKOK'] ?? 0),
            'tunjangan_kinerja'    => (float) ($komponen[self::KOMPONEN_KINERJA] ?? 0),
            'tunjangan_absen'      => (float) ($komponen[self::KOMPONEN_ABSEN] ?? 0),
            'tunjangan_penempatan' => (float) $gaji['placement_allowance'],
            'insentif'             => (float) $gaji['incentive'],
            'lembur'               => (float) $gaji['lembur'],
            'total'                => (float) $gaji['total_gaji'],
        ];
    }

    return $hasil;
}
```

- [x] **Step 4: Jalankan, pastikan lulus**

Run: `php74 vendor/bin/phpunit --filter PayrollGeneratorUnitTest`
Expected: OK (1 test).

- [x] **Step 5: Commit**

```bash
git add app/Services/Payroll/PayrollGenerator.php tests/PayrollGeneratorUnitTest.php
git commit -m "feat(payroll): hitungGajiUnit() untuk laporan tanpa menulis payroll"
```

---

### Task 2: Controller memakai `hitungGajiUnit()`

**Files:**
- Modify: `app/Controllers/TutupKasir.php:802-1672` (blok `$totalGajiUnit` … akhir `foreach ($karyawanUnit)`)
- Test: `tests/AssetBerjalanFilterTest.php` (tambah fixture gaji + 1 test)

**Interfaces:**
- Consumes: `PayrollGenerator::hitungGajiUnit(string $bulan, int $unitId): array` dari Task 1.
- Produces: `$totalGajiUnit` (float) dan `$gajiDetail` (array dengan key `nama`, `jabatan`, `gaji_pokok`, `tunjangan_kinerja`, `tunjangan_absen`, `tunjangan_penempatan`, `insentif`, `total`) — bentuk yang sudah dibaca `app/Views/dashboard/asset_berjalan.php:334-387`.

- [x] **Step 1: Tulis test yang gagal**

Di `tests/AssetBerjalanFilterTest.php`:

1. Tambahkan tabel `salary_components`, `salary_structures`, `incentive_groups`, `incentive_rules`, `kpi_components`, `kpi_weights`, `kpi_targets`, `kpi_evaluations`, `kpi_attendance_detail`, `kpi_attendance_off` ke daftar DROP dan DDL — salin persis dari Task 1.
2. Ganti seed akun menjadi:

```php
$q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ALAMAT, TERIMA_GAJI, STATUS_PEGAWAI, deleted) VALUES
    (44, 50, 0, 'Ira Kurniawati', 'Jalan Test', 0, 1, NULL),
    (100, 3, 41, 'Kepala Toko', 'Jalan Test', 1, 1, NULL),
    (101, 3, 41, 'Sudah Resign', 'Jalan Test', 1, 0, NULL)");
```

3. Tambahkan seed struktur gaji (`GAJI_POKOK` fixed `2000000`) dan lembur `100000` seperti Task 1, plus jabatan 41.
4. Tambahkan test:

```php
public function testTotalGajiMemakaiMesinPayroll(): void
{
    $this->sesi(44, 50, 0);
    $html = $this->halaman('?unit=3&bulan=9&tahun=2026');

    // 2.000.000 pokok + 350.000 penempatan + 100.000 lembur.
    // Blok legacy selalu memakai pokok 1.500.000, jadi angka ini membedakan mesin.
    $this->assertSame('2.450.000', $this->kartu($html, 'Total Gaji'));
}
```

- [x] **Step 2: Jalankan, pastikan gagal**

Run: `php74 vendor/bin/phpunit --filter AssetBerjalanFilterTest`
Expected: FAIL — `Total Gaji` masih dari blok legacy (bukan `2.450.000`).

- [x] **Step 3: Ganti blok legacy**

Di `TutupKasir::assetberjalan()`, hapus dari `$totalGajiUnit = 0;` sampai penutup `foreach ($karyawanUnit as $pegawai) { ... }` sebelum `$pengeluaran = ...`. Ganti dengan:

```php
        $totalGajiUnit = 0;
        $gajiDetail = [];

        // Nama jabatan untuk kolom "Jabatan" di rincian.
        $namaJabatan = array_column(
            $this->db->table('jabatan')->select('ID_JABATAN, NAMA_JABATAN')->get()->getResultArray(),
            'NAMA_JABATAN',
            'ID_JABATAN'
        );

        // Satu mesin dengan payroll (KPI + salary_structures + lembur),
        // supaya Total Gaji di laporan = yang benar-benar dibayar.
        $gajiUnit = (new \App\Services\Payroll\PayrollGenerator())
            ->hitungGajiUnit($bulanStr, (int) $unit);

        foreach ($gajiUnit as $row) {
            $totalGajiUnit += $row['total'];

            $gajiDetail[] = [
                'nama'                 => $row['nama'],
                'jabatan'              => $namaJabatan[$row['jabatan_id']] ?? ('#' . $row['jabatan_id']),
                'gaji_pokok'           => $row['gaji_pokok'],
                'tunjangan_kinerja'    => $row['tunjangan_kinerja'],
                'tunjangan_absen'      => $row['tunjangan_absen'],
                'tunjangan_penempatan' => $row['tunjangan_penempatan'],
                'insentif'             => $row['insentif'],
                'total'                => $row['total'],
            ];
        }
```

Hapus seluruh rentang itu; semua variabel antara (`$karyawanUnit`, `$akun`, `$target*`, `$batas*`, `$aktual_*`, `$nilai_*`, `$detail_kpi`, `$skor_total*`, `$insentif`, `$gaji`) ikut terhapus bersama rentangnya. Jangan hapus `$bulanStr`, `$unit`, `$namaJabatan`, `$totalGajiUnit`, `$gajiDetail`.

- [x] **Step 4: Jalankan test terkait**

Run: `php74 vendor/bin/phpunit --filter "AssetBerjalan|PayrollGeneratorUnit"`
Expected: OK — semua test hijau.

- [x] **Step 5: Commit**

```bash
git add app/Controllers/TutupKasir.php tests/AssetBerjalanFilterTest.php
git commit -m "fix(asset-berjalan): Total Gaji pakai satu mesin dengan payroll

Menghapus blok hitung gaji legacy (±870 baris) dan memakai
PayrollGenerator::hitungGajiUnit(). Ini sekaligus:
- memasukkan lembur (kategori 10) ke Total Gaji (#19)
- memakai KPI dari kpi_evaluations, jadi Follow Up/Upselling CS terisi (#20)
- membatasi skor KPI di 100 (#21)
- mengecualikan akun nonaktif/terhapus (#22)

Refs #18 #19 #20 #21 #22"
```

---

### Task 3: Verifikasi terhadap data payroll nyata

**Files:** tidak ada perubahan kode. Script sekali pakai di `/tmp/opencode/`.

**Interfaces:**
- Consumes: `PayrollGenerator::hitungGajiUnit()`, tabel `finance_payroll`.

- [x] **Step 1: Bandingkan dengan payroll Oktober 2026 unit 5**

Buat `/tmp/opencode/cek_paritas.php` yang mem-bootstrap CI4 (contoh: `app/Scripts/assetberjalan_debug.php` baris 1-14), lalu:

```php
$unit = 5;
$bulan = '2026-10';
$rows = (new \App\Services\Payroll\PayrollGenerator())->hitungGajiUnit($bulan, $unit);
$total = array_sum(array_column($rows, 'total'));
$from = $bulan . '-01';
$to = date('Y-m-t', strtotime($from));
$payroll = (new \App\Models\ModelFinancePayroll())
    ->where('unit_id', $unit)->where('due_date >=', $from)->where('due_date <=', $to)
    ->selectSum('total')->first();
echo "hitungGajiUnit: $total\n";
echo "finance_payroll: " . ($payroll->total ?? 0) . "\n";
```

Run: `php74 /tmp/opencode/cek_paritas.php`
Expected: dua angka mendekati sama (selisih hanya bila ada karyawan yang belum punya baris payroll). Catat angkanya di komentar issue #18.

- [x] **Step 2: Cek halaman terhadap data nyata**

Run (read-only): bandingkan `$totalGajiUnit` untuk unit 5 Oktober 2026 dengan hasil Step 1. Iterasi bila jauh berbeda; jangan mengubah data.

- [x] **Step 3: Perbarui issue & board**

- Komentar di #18 berisi angka sebelum/sesudah + perintah test.
- Tutup #19, #20, #21, #22 dengan komentar bahwa perbaikannya ikut di commit #18 (atau tetap buka bila ada bagian yang belum terpenuhi, mis. cache HO di #24).
- Pindahkan #18 ke **Testing**.

---

## Catatan di luar rencana

- `app/Services/Payroll/UnitSalaryCalculationService.php` (502 baris) tidak dihapus di sini: masih dipakai `app/Scripts/terima_gaji_verify.php` dan `assetberjalan_debug.php`. Penghapusannya masuk #12.
- N+1 yang tersisa setelah blok gaji hilang: panel HO + query pengeluaran. Optimasi lintas-request ada di #24.

---

## Hasil eksekusi (2026-10-22)

- `46f1907` feat(payroll): `hitungGajiUnit()` + `tests/PayrollGeneratorUnitTest.php` (3 test, 13 assertion).
- `a2bd78b` fix(asset-berjalan): controller memakai `hitungGajiUnit()`, blok legacy 872 baris dihapus.
- `php74 vendor/bin/phpunit --filter "AssetBerjalan|PayrollGeneratorUnit|OmsetBulanan"` → 25 test, 105 assertion, OK.
- Paritas `erp_local` (read-only, `generate()` dalam transaksi lalu rollback, Nov 2026): unit 3 & 5, laporan = payroll, 100%.
- Full suite: 19 error / 181 failure — sama persis dengan baseline sebelum perubahan (tidak ada regresi).
- Board: #18–#22 → Testing. #17 → Done.

Dua hal yang berbeda dari rencana:
1. Fixture butuh kolom `salary_structures.context` (service membacanya) — ditambahkan di test dan plan.
2. Angka payroll Oktober 2026 yang tersimpan bukan pembanding yang sah: baris dibuat 5 Okt 02:21, sebelum lembur 5/7/12/14 Okt dan sebelum data penempatan berubah. Paritas dibuktikan lewat `generate()` di bulan tanpa baris payroll.
- `39cc9f5` fix(asset-berjalan): kolom Lembur di modal rincian (temuan review).
- Full filtered suite: 15 test, 78 assertion, OK.
