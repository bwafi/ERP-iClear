# KPI Tunjangan Kinerja Manager (jabatan 34) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memasang 7 komponen KPI berbobot 100 untuk Manager (jabatan 34) agar `skor_total` terisi dan tunjangan kinerjanya mengalir (bukan Rp 0 lagi).

**Architecture:** Mengikuti engine KPI config-driven yang ada. Komponen & bobot ditulis ke `kpi_components`/`kpi_weights` (migrasi). Perhitungan dipusatkan di `ManagerKpiService` baru (pola `SupervisorKpiService`/`DigitalMarketingKpiService`), lalu disambungkan sebagai satu cabang dispatch di `KpiCalculationService::calculateForEmployee()` untuk `positionId === 34`. #1/#2 memakai ulang service Divisi/Finance; #3/#4/#5 dihitung dari data yang ada; #6/#7 input manual bulanan 0–100 (pola `CUSTOMER_SATISFACTION`).

**Tech Stack:** PHP 7.4, CodeIgniter 4, MySQL, PHPUnit 9.

**Spec:** `docs/superpowers/specs/2026-10-18-kpi-manager-design.md`

## Global Constraints

- **PHP 7.4** (produksi). Dilarang: `str_contains`/`str_starts_with`, union types, `match`, named args, constructor promotion, nullsafe `?->`.
- Bobot Manager: `OMZET_GLOBAL` 50, `FIN_KESEHATAN_UANG` 15, `TARGET_CABANG` 10, `EFEKTIVITAS_ORGANISASI` 10, `SDM_LEADERSHIP` 5, `IMPROVEMENT_PERUSAHAAN` 5, `REPORTING_GOVERNANCE` 5 (total 100).
- Jabatan: Manager 34, SPV 40, Kadiv 43, IT 45, Finance 0. Target HO cabang = `OMSET_CABANG` `position_id = 40`.
- Angka: anggota tanpa nilai = **0** (pembagi = semua anggota aktif); `SDM_LEADERSHIP` di-**per-jabatan dulu** baru antar-jabatan; tiap skor **di-cap 100** sebelum dirata-rata; hasil #4/#5 di-cache.
- Komponen manual tanpa data = `null` (dikecualikan dari total), bukan 0.
- Jangan menyentuh `salary_structures` atau bobot/komponen jabatan lain.
- Test: `vendor/bin/phpunit`. Tes murni tidak butuh DB.

## Review Focus

1. Bawahan ber-skor >100 (mis. `STOK_OPNAME`) — harus di-cap 100 per kontributor, tidak menggelembungkan #4/#5. → tes di Task 2.
2. Cabang tanpa target `OMSET_CABANG` (mis. Unit 5) — #3 harus `null`, bukan pembagian nol / menghitung cabang kosong. → Task 3.
3. Tidak ada SPV sama sekali → #4 `null` (dikecualikan), bukan error. → Task 2/3.
4. Nilai manual di luar 0–100 → ditolak dengan pesan, data lama tidak rusak. → Task 3.
5. Bobot posisi 34 lama/duplikat → setelah migrasi tepat 7 baris, `weight_valid` true (tidak dobel). → Task 4.

---

### Task 1: Migrasi komponen & bobot posisi 34

**Files:**
- Create: `app/Database/Migrations/2026-10-18-001000_AddKpiJabatan34Manager.php`
- Reference pola: `app/Database/Migrations/2026-09-25-000200_UpdateKpiJabatan43DigitalMarketing.php`

**Interfaces:**
- Consumes: tabel `kpi_components`, `kpi_weights`; kode pakai ulang `OMZET_GLOBAL`, `FIN_KESEHATAN_UANG`, `TARGET_CABANG` (sudah ada).
- Produces: 4 komponen baru (`EFEKTIVITAS_ORGANISASI` automatic, `SDM_LEADERSHIP` automatic, `IMPROVEMENT_PERUSAHAAN` manual, `REPORTING_GOVERNANCE` manual) + 7 baris `kpi_weights` `position_id = 34`, `weight_group = 'kpi'`.

- [ ] **Step 1: Tulis migrasi** (idempoten; tiru pola migrasi 43).

Konstanta (nilai persis):

```php
private const NEW_COMPONENTS = [
    ['code' => 'EFEKTIVITAS_ORGANISASI', 'name' => 'Efektivitas Organisasi', 'type' => 'automatic'],
    ['code' => 'SDM_LEADERSHIP',         'name' => 'SDM & Leadership',       'type' => 'automatic'],
    ['code' => 'IMPROVEMENT_PERUSAHAAN', 'name' => 'Improvement Perusahaan', 'type' => 'manual'],
    ['code' => 'REPORTING_GOVERNANCE',   'name' => 'Reporting & Governance', 'type' => 'manual'],
];

private const WEIGHTS_34 = [
    'OMZET_GLOBAL'           => 50,
    'FIN_KESEHATAN_UANG'     => 15,
    'TARGET_CABANG'          => 10,
    'EFEKTIVITAS_ORGANISASI' => 10,
    'SDM_LEADERSHIP'         => 5,
    'IMPROVEMENT_PERUSAHAAN' => 5,
    'REPORTING_GOVERNANCE'   => 5,
];
```

`up()`: upsert `NEW_COMPONENTS` (`calculation_strategy = ''`, `is_active = 1`); kumpulkan id 7 kode (baru + pakai ulang); **hapus** `kpi_weights` `position_id = 34` `weight_group = 'kpi'`; `insertBatch` 7 baris (`effective_from = '2024-01-01'`, `effective_to = null`, `created_by = null`).
`down()`: hapus `kpi_weights` posisi 34 (group kpi) untuk 7 komponen itu.

- [ ] **Step 2: Jalankan migrasi**

Run: `php spark migrate`
Expected: `Running: 2026-10-18-001000_AddKpiJabatan34Manager` tanpa error.

- [ ] **Step 3: Verifikasi data**

Run:
```bash
mysql -uroot erp_local -t -e "SELECT kw.weight, c.code FROM kpi_weights kw JOIN kpi_components c ON c.id=kw.kpi_component_id WHERE kw.position_id=34 AND kw.weight_group='kpi' ORDER BY kw.weight DESC;"
```
Expected: 7 baris; total bobot 100; tidak ada duplikat.

- [ ] **Step 4: Commit**

```bash
git add app/Database/Migrations/2026-10-18-001000_AddKpiJabatan34Manager.php
git commit -m "feat(kpi): pasang 7 komponen KPI Manager (jabatan 34)"
```

---

### Task 2: Agregasi murni `ManagerKpiService` (TDD)

**Files:**
- Create: `app/Services/Kpi/ManagerKpiService.php`
- Test: `tests/unit/ManagerKpiTest.php` (murni, tanpa DB; tiru `tests/unit/DigitalMarketingKpiTest.php`)

**Interfaces:**
- Consumes: —
- Produces:
  - `ManagerKpiService::CODES` — `string[]` (7 kode).
  - `ManagerKpiService::EFEKTIVITAS_JABATANS` — `int[]` = `[40]`.
  - `ManagerKpiService::SDM_JABATANS` — `int[]` = `[40, 43, 45, 0]`.
  - `static averageOverMembers(array $scores, int $memberCount): ?float`
  - `static averageAcrossRoles(array $roleAverages): ?float`

- [ ] **Step 1: Tulis tes yang GAGAL**

```php
final class ManagerKpiTest extends CIUnitTestCase
{
    public function testCodesCoverSevenComponents()
    {
        $this->assertCount(7, ManagerKpiService::CODES);
    }

    public function testAverageOverMembersCountsMissingAsZero()
    {
        // 2 SPV aktif, hanya 1 punya nilai 80 → 80/2 = 40.
        $this->assertSame(40.0, ManagerKpiService::averageOverMembers([80.0], 2));
    }

    public function testAverageOverMembersCapsEachScoreAt100()
    {
        // Review Focus #1: skor bawahan >100 tidak menggelembungkan.
        $this->assertSame(100.0, ManagerKpiService::averageOverMembers([150.0], 1));
    }

    public function testAverageOverMembersNullWhenNoMembers()
    {
        // Review Focus #3.
        $this->assertNull(ManagerKpiService::averageOverMembers([], 0));
    }

    public function testAverageAcrossRolesAveragesPerRoleFirst()
    {
        // B2: (80+60+0+0)/4 = 35.
        $this->assertSame(35.0, ManagerKpiService::averageAcrossRoles([80.0, 60.0, 0.0, 0.0]));
    }

    public function testAverageAcrossRolesSkipsRoleWithoutMembers()
    {
        $this->assertSame(60.0, ManagerKpiService::averageAcrossRoles([80.0, null, 40.0]));
    }
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**

Run: `vendor/bin/phpunit tests/unit/ManagerKpiTest.php`
Expected: FAIL — class `ManagerKpiService` tidak ditemukan.

- [ ] **Step 3: Implementasi bagian murni**

Buat `app/Services/Kpi/ManagerKpiService.php` dengan konstanta di atas dan dua metode statis:
`averageOverMembers()` → `null` bila `memberCount <= 0`; jumlahkan `min((float)$s, 100.0)` untuk setiap skor non-null; bagi `memberCount`; `round(..., 4)`.
`averageAcrossRoles()` → rata-rata nilai non-null; `null` bila tidak ada.

- [ ] **Step 4: Jalankan, pastikan LULUS**

Run: `vendor/bin/phpunit tests/unit/ManagerKpiTest.php`
Expected: PASS (6 test).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Kpi/ManagerKpiService.php tests/unit/ManagerKpiTest.php
git commit -m "feat(kpi): agregasi murni KPI Manager (Efektivitas & SDM)"
```

---

### Task 3: Sumber data `ManagerKpiService` (#3, #4, #5, manual #6/#7)

**Files:**
- Modify: `app/Services/Kpi/ManagerKpiService.php`

**Interfaces:**
- Consumes: `KpiCalculationService::calculateForEmployee()` & `branchUnits()`, `ModelKpiTarget::getTargetByKpiAndUnit($compId,$unit,$context,$date,$positionId)`, `OmsetCabangCalculator::calculate($by,$unit,$month,$year)`, `DigitalMarketingKpiService::componentData()`, `FinanceKpiService::componentData()`, `EvaluatorAuthorizationService::canEvaluateComponent()`, `KpiEvaluationService::recordEvaluation()`.
- Produces (dipakai Task 4 & 5):
  - `componentData(string $code, int $employeeId, int $unitId, int $month, int $year, string $context, ?string $date = null): array` → `['achievement'=>?float,'target'=>?float,'actual'=>?float,'shortfall'=>?float,'source'=>string]`
  - `manualValue(int $employeeId, string $code, int $month, int $year): ?float`
  - `saveManual(int $employeeId, string $code, int $evaluatorId, int $month, int $year, float $value): array` → `['success'=>bool,'errors'=>string[]]`

- [ ] **Step 1: Tambah `componentData()`**

Dispatch per kode: `OMZET_GLOBAL` → delegasi `DigitalMarketingKpiService::componentData(...)`; `FIN_KESEHATAN_UANG` → delegasi `FinanceKpiService::componentData($code,$month,$year)`; `TARGET_CABANG` → `pencapaianCabang()`; `EFEKTIVITAS_ORGANISASI` → `groupKpiData(self::EFEKTIVITAS_JABATANS,...)`; `SDM_LEADERSHIP` → `groupKpiData(self::SDM_JABATANS,...)`; `IMPROVEMENT_PERUSAHAAN`/`REPORTING_GOVERNANCE` → `manualData()`; default → semua `null`.

- [ ] **Step 2: Tambah `groupKpiData(array $jabatans, int $month, int $year, string $context, ?string $date): array`**

Untuk tiap jabatan: ambil anggota aktif (query `akun`: `ID_JABATAN = ?`, `STATUS_PEGAWAI = 1`, `deleted` null/0, select `ID_AKUN, ID_JABATAN, ID_UNIT`); bila kosong → `null` (dilewati). Lainnya: hitung `total_score` tiap anggota lewat `KpiCalculationService::calculateForEmployee()`, `averageOverMembers($scores, count($members))`. Gabung dengan `averageAcrossRoles()`. **Cache** per kunci `jabatans|month|year|context|date` di properti instans. Kembalikan `target = 100.0`, `actual = achievement`, `shortfall = null|max(100-achievement,0)`.

- [ ] **Step 3: Tambah `pencapaianCabang(int $month, int $year, string $targetContext, ?string $date): array`**

Ambil komponen `OMSET_CABANG`. Unit = `(new KpiCalculationService())->branchUnits()` (semua cabang). Untuk tiap unit: target HO = `getTargetByKpiAndUnit($compId, $unit, $targetContext, $date, 40)`; **lewati** bila target null/≤0 (Review Focus #2); hitung `reached++` bila `OmsetCabangCalculator::calculate(0,$unit,$month,$year) >= target`; `n++`. Hasil `achievement = $n>0 ? round($reached/$n*100,4) : null`.

- [ ] **Step 4: Tambah `manualData()`, `manualValue()`, `saveManual()`**

`manualValue()`: komponen by code; baris `ModelKpiEvaluation` terbaru (`employee_id`,`kpi_component_id`,`period_year`,`period_month`, order `evaluation_date DESC, id DESC`); `min((float)normalized_score,100)` atau `null`.
`saveManual()`: tolak di luar 0–100 (Review Focus #4) → `['success'=>false,'errors'=>[...]]`; tolak bila `canEvaluateComponent()` false; upsert anchor tanggal 15 (hapus baris `evaluator_id`+anchor lama); `KpiEvaluationService::recordEvaluation()` dengan `raw_score = $value`, `max_score = 100`. Tiru `SupervisorKpiService::saveCustomerSatisfaction()`.

- [ ] **Step 5: Cek sintaks & jalankan tes murni**

Run: `php -l app/Services/Kpi/ManagerKpiService.php && vendor/bin/phpunit tests/unit/ManagerKpiTest.php`
Expected: `No syntax errors detected`; PASS.

- [ ] **Step 6: Verifikasi live (skrip sekali-pakai, lalu hapus)**

Buat skrip sementara yang memanggil `componentData('TARGET_CABANG', 41, 50, 10, 2026, 'penilaian_kinerja')`, `EFEKTIVITAS_ORGANISASI`, `SDM_LEADERSHIP`; cetak hasilnya.
Expected: angka 0–100 atau `null`; `TARGET_CABANG` `null`/0–100 tanpa error saat ada cabang tanpa target; `SDM_LEADERSHIP` = rata-rata per-jabatan. Hapus skrip.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Kpi/ManagerKpiService.php
git commit -m "feat(kpi): sumber data KPI Manager (cabang, SPV, bawahan, manual)"
```

---

### Task 4: Sambungkan ke engine

**Files:**
- Modify: `app/Services/Kpi/KpiCalculationService.php` (properti ~61-63; init ~108-109; cabang dispatch setelah blok Finance ~159; rantai display setelah `$financeData` ~363-391)

**Interfaces:**
- Consumes: `ManagerKpiService::CODES`, `ManagerKpiService::componentData()`.
- Produces: untuk jabatan 34, `calculateForEmployee()` mengembalikan 7 item dengan `achievement` terisi.

- [ ] **Step 1: Tambah properti + aksesor**

```php
protected $managerKpiService;

public function managerService(): ManagerKpiService
{
    if ($this->managerKpiService === null) {
        $this->managerKpiService = new ManagerKpiService();
    }
    return $this->managerKpiService;
}
```

- [ ] **Step 2: Cabang dispatch jabatan 34**

Deklarasikan `$managerData = null;` di blok init; sisipkan rantai `elseif` setelah cabang Finance:

```php
} elseif ($positionId === 34 && in_array($component->code, ManagerKpiService::CODES, true)) {
    $managerData = $this->managerService()->componentData(
        $component->code, (int)$employeeId, (int)$unitId,
        (int)$month, (int)$year, $context, $date
    );
    $achievement = $managerData['achievement'];
}
```

- [ ] **Step 3: Cabang display target/realisasi**

Tambahkan pada rantai `$targetInfo` (setelah `elseif ($financeData !== null)`):
`elseif ($managerData !== null)` → `target/actual/shortfall` dari `$managerData`, `ho = true`, `cabang = null`.

- [ ] **Step 4: Verifikasi live (skrip sekali-pakai, lalu hapus)**

```php
$res = (new \App\Services\Kpi\KpiCalculationService())
    ->calculateForEmployee(41, 50, '10', '2026', 'penilaian_kinerja');
echo 'items=' . count($res['items']) . ' total=' . $res['total_score']
   . ' valid=' . var_export($res['weight_valid']['is_valid'], true) . "\n";
```
Expected: `items=7`, `valid=true`, `total` > 0 (sebelumnya 0). Review Focus #5. Hapus skrip.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Kpi/KpiCalculationService.php
git commit -m "feat(kpi): hitung KPI Manager lewat ManagerKpiService di engine"
```

---

### Task 5: Input manual #6/#7 (Direktur/Admin root)

**Files:**
- Modify: `app/Controllers/PenilaianKPI.php` (method `kpi_detail()` + method baru `save_manager_manual()` dekat `save_customer_satisfaction()` ~854)
- Modify: `app/Views/penilaian/kpi_detail.php` (blok baru dekat form CS ~149-175)
- Modify: `app/Config/Routes.php` (~525, dekat route CS)
- Test: `tests/unit/ManagerKpiTest.php` (tambah tes validasi rentang murni) — atau feature test bila ada pola; utama: verifikasi manual.

**Interfaces:**
- Consumes: `ManagerKpiService::manualValue()`, `ManagerKpiService::saveManual()`, `EvaluatorAuthorizationService::canEvaluateComponent()`.
- Produces: route `POST penilaian/kpi/save_manager_manual`; kartu input di detail KPI Manager.

- [ ] **Step 1: Siapkan data di controller**

Di `kpi_detail()`, bila `ID_JABATAN` target = 34, bangun array 2 komponen manual (kode, nama, `value = manualValue()`, `can_evaluate = canEvaluateComponent()`), kirim ke view.

- [ ] **Step 2: Tambah kartu input di view**

Loop komponen tsb; satu form per komponen: input `number` (`step=0.01 min=0 max=100 required`), hidden `employee_id`/`bulan`/`tahun`/`code`; tombol hanya bila `can_evaluate`. Tiru blok `Customer Satisfaction` di `kpi_detail.php:149-175`.

- [ ] **Step 3: Route**

```php
$routes->post('penilaian/kpi/save_manager_manual', 'PenilaianKPI::save_manager_manual', ['filter' => 'auth']);
```

- [ ] **Step 4: Controller `save_manager_manual()`**

Ambil POST; validasi pegawai aktif; panggil `(new ManagerKpiService())->saveManual($employeeId, $code, $evaluatorId, $bulan, $tahun, $nilai)`; redirect balik dengan `success`/`error` (tiru `save_customer_satisfaction()`).

- [ ] **Step 5: Verifikasi**

Run: `php -l app/Controllers/PenilaianKPI.php && php -l app/Views/penilaian/kpi_detail.php`
Expected: `No syntax errors detected`.
Manual: login Direktur/Admin root → detail KPI Manager → simpan kedua komponen → muncul di `detail_kpi` dengan nilai tersimpan; nilai di luar 0–100 ditolak.

- [ ] **Step 6: Commit**

```bash
git add app/Controllers/PenilaianKPI.php app/Views/penilaian/kpi_detail.php app/Config/Routes.php
git commit -m "feat(kpi): input manual Improvement & Reporting Manager"
```

---

### Task 6: Verifikasi akhir cabang

**Files:**
- Modify: — (hanya bila ada perbaikan)

- [ ] **Step 1: Seluruh tes**

Run: `vendor/bin/phpunit`
Expected: PASS (tanpa regresi).

- [ ] **Step 2: Tidak ada jabatan lain terdampak**

Run:
```bash
mysql -uroot erp_local -t -e "SELECT position_id, COUNT(*) c, SUM(weight) total FROM kpi_weights WHERE weight_group='kpi' GROUP BY position_id ORDER BY position_id;"
```
Expected: hanya posisi 34 yang kini 7 baris/100; posisi lain tidak berubah.

- [ ] **Step 3: Tunjangan kinerja tidak lagi Rp 0**

Hitung gaji/tunjangan Manager Oktober 2026 via `SalaryCalculationService` (atau `percent_of_kpi` × `skor_total/100`).
Expected: tunjangan kinerja **> Rp 0**.

- [ ] **Step 4: Bersihkan file sementara**

Run: `git status --short`
Expected: tidak ada skrip `_tmp`/`_verify` yang tertinggal.

- [ ] **Step 5: Commit (bila ada perubahan)**

```bash
git add -A && git commit -m "test(kpi): verifikasi KPI Manager 7 komponen"
```

---

## Di luar cakupan

- Tunjangan absen Manager (`skor_total2`) — sudah jalan (commit `a2c209f`).
- Menghapus tumpang-tindih SPV antara #4 dan #5 — disepakati diterima.
- Perubahan `salary_structures` — plafon Rp 2.250.000 sudah ada.

## Risiko

- #4/#5 menumpang KPI orang lain; bila belum dinilai → 0 (keputusan A2) → menekan `skor_total`.
- #4/#5 memicu perhitungan berantai (Manager → SPV → Kepala Toko); cache per-periode dipasang.
- Input manual #6/#7 hanya Direktur/Admin root (C1).
