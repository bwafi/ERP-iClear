# TASK 1B — DAILY ATTENDANCE STORAGE — IMPLEMENTATION REPORT

**Repository:** `/home/syro/Projects/ERP`
**Branch:** `refactor/kpi-incentive-engine`
**Tanggal:** 29 Agustus 2026
**Scope:** Daily raw attendance storage di `kpi_evaluations` — hanya schema + model + service.
**Status:** ✅ SELESAI & TERVERIFIKASI

---

## RINGKASAN EKSEKUTIF

Task 1B menerapkan penyimpanan evaluasi harian (daily attendance) untuk 4 komponen absensi:

- Kehadiran
- Kebersihan
- Seragam
- Kepatuhan SOP

Fokus **hanya** pada kemampuan **menyimpan daily raw score (1–5)** di `kpi_evaluations`.
TIDAK menyentuh:
- agregasi bulanan (SUM/(26×5)×100) — task berikutnya
- legacy attendance (`penilaian` table)
- formula salary / denominator 26
- `LegacyKpiCalculationService`, `hitungKPIGaji()`, `SalaryCalculationService`, `IncentiveCalculationService`

---

## A. ACTUAL SCHEMA (BEFORE → AFTER)

### A.1 Sebelum (state yang salah)

Kolom `evaluation_date` ada tapi `DEFAULT NULL`, dan seeder masih memakai `DATE NULL`.
(tabel 0 rows — aman untuk dikoreksi)

### A.2 Sesudah (verified)

```sql
SHOW CREATE TABLE kpi_evaluations;
```

```sql
CREATE TABLE `kpi_evaluations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT NOT NULL,
  `kpi_component_id` INT UNSIGNED NOT NULL,
  `evaluator_id` INT NOT NULL,
  `evaluation_date` DATE NOT NULL,               -- ✅ diubah NULL → NOT NULL
  `raw_score` DECIMAL(5,2) NOT NULL,
  `max_score` DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  `normalized_score` DECIMAL(5,2) NOT NULL,
  `weighted_score` DECIMAL(5,2) NOT NULL,
  `notes` TEXT NULL,
  `period_year` INT NOT NULL,
  `period_month` INT NOT NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_emp_kpi_date` (`employee_id`,`kpi_component_id`,`evaluation_date`),  -- ✅ daily unique
  KEY `idx_period` (`period_year`,`period_month`),
  KEY `idx_evaluator` (`evaluator_id`),
  KEY `idx_evaluation_date` (`evaluation_date`),
  CONSTRAINT `fk_ke_component` FOREIGN KEY (`kpi_component_id`) REFERENCES `kpi_components` (`id`)
) ENGINE=InnoDB;
```

**Verifikasi:**
- ✅ `evaluation_date DATE NOT NULL`
- ✅ `uq_emp_kpi_date` = `(employee_id, kpi_component_id, evaluation_date)`
- ✅ `uq_emp_kpi_period` (old monthly) **tidak ada**
- ✅ `idx_evaluation_date` ada
- ✅ `period_year`/`period_month` tetap (grouping/query)

---

## B. CURRENT DAILY EVALUATION CAPABILITY

| Pertanyaan | Jawaban |
|---|---|
| Bisa simpan 2 evaluasi emp+component berbeda tanggal? | ✅ **YA** — unique per `(emp, kpi, evaluation_date)` |
| Bisa update 1 evaluasi emp+component+date yang sama? | ✅ **YA** — upsert by date |
| Stored daily? | ✅ `evaluation_date` + `raw_score` disimpan per baris |

---

## C. FILES CHANGED

| File | Perubahan |
|---|---|
| `app/Services/Kpi/KpiEvaluationService.php` | `recordEvaluation()` meng-require & derive period dari `evaluation_date`; validasi date |
| `app/Models/ModelKpiEvaluation.php` | `upsertEvaluation()` hanya pakai `(emp, kpi, evaluation_date)`; hapus branch monthly fallback |
| `app/Scripts/kpi_migrate_seed.php` | seeder CREATE TABLE disinkronkan: `evaluation_date DATE NOT NULL` |
| database `kpi_evaluations` | `ALTER` diterapkan: `evaluation_date DATE NOT NULL` |

Status syntax: semua `php -l` PASS.

**TIDAK diubah (legacy protected):**
- `LegacyKpiCalculationService.php`
- `PenilaianKPI::hitungKPIGaji()`
- `SalaryCalculationService.php`
- `IncentiveCalculationService.php`
- tabel `penilaian` (legacy attendance)

---

## D. EXACT ModelKpiEvaluation CHANGES

**`upsertEvaluation()` — sebelum (issue):**
```php
if (!empty($data['evaluation_date'])) { ... by date ... }
else { ... by period_year/period_month ... }   // ❌ competing date source
```

**Sesudah (fixed):**
```php
public function upsertEvaluation($data)
{
    $existing = $this->where('employee_id', $data['employee_id'])
                     ->where('kpi_component_id', $data['kpi_component_id'])
                     ->where('evaluation_date', $data['evaluation_date'] ?? null)
                     ->first();
    if ($existing) { $data['id'] = $existing->id; return $this->save($data); }
    return $this->insert($data);
}
```

- ✅ `evaluation_date` ada di `$allowedFields`
- ✅ daily uniqueness
- ✅ tidak ada lagi branch monthly upsert
- ✅ `getByEmployeeAndPeriod()` + `getByEmployee()` tetap (query/groupping kompatibel)

---

## E. EXACT KpiEvaluationService CHANGES

**`recordEvaluation()`:**
- Validasi: `evaluation_date` **wajib** + valid (`strtotime`)
- `period_year` / `period_month` **DERIVED** dari `evaluation_date`
- `evaluation_date` disimpan
- `raw_score` disimpan as-is (authoritative)
- `normalized_score = raw_score / max_score × 100` (per-event, tetap NOT NULL utk kompat)
- `weighted_score` tetap dihitung (per-event)

**Perilaku yang diminta — diverifikasi:**
| Condition | Result |
|---|---|
| same emp + same comp + same date | UPDATE (1 row) |
| same emp + same comp + different date | INSERT baru (2 rows) |
| caller period_year/month vs evaluation_date | `evaluation_date` authoritative |

---

## F. DAILY TEST RESULTS (CASE 1–5)

Run: `php74 app/Scripts/daily_eval_test.php`

```
[PASS] CASE1 insert sukses
[PASS] CASE1 1 row
[PASS] CASE2 update sukses
[PASS] CASE2 tetap 1 row
[PASS] CASE2 raw=4
[PASS] CASE3 2 row (2 tanggal)
[PASS] CASE4 kehadiran 1 row di d1
[PASS] CASE4 kebersihan 1 row di d1 (terpisah)
[PASS] CASE5 employee 48 tetap 1 row
[PASS] CASE5 employee 61 1 row terpisah
[PASS] evaluation_date benar
[PASS] period_year=2026
[PASS] period_month=8
[PASS] tanggal invalid ditolak
[PASS] cleanup selesai (0 row tersisa)

RESULT: PASS=15 FAIL=0
```

Test menggunakan employee nyata (48 Radit, 61 Anggun) & component nyata (KEHADIRAN id=9, KEBERSIHAN id=10). Data test dibersihkan penuh (0 row tersisa).

---

## G. KPI REGRESSION

```
RESULT: PASS=57 FAIL=0
STATUS: ALL_IDENTICAL
```

## H. INCENTIVE REGRESSION

```
RESULT: PASS=11 FAIL=0
STATUS: ALL_PASS
```

## I. SALARY REGRESSION

```
RESULT: PASS=63 FAIL=0
STATUS: ALL_IDENTICAL
```

## J. ASSET REGRESSION (php74)

```
RESULT: PASS=12 FAIL=0
STATUS: ALL_IDENTICAL
```

**Total regression baseline: 143/143 PASS** — semua intact.

---

## K. LEGACY FILES — CONFIRMED UNTOUCHED

| File | Status |
|---|---|
| `app/Services/Kpi/LegacyKpiCalculationService.php` | ✅ tidak berubah (md5 fixed) |
| `app/Services/Payroll/SalaryCalculationService.php` | ✅ tidak berubah |
| `app/Services/Incentive/IncentiveCalculationService.php` | ✅ tidak berubah |
| `app/Controllers/PenilaianKPI::hitungKPIGaji()` | ✅ tidak berubah di task ini |
| tabel `penilaian` (legacy attendance) | ✅ tidak disentuh |

---

## L. REMAINING WORK (untuk task berikutnya)

1. **Agregasi bulanan absensi** — `SUM(daily raw)/(26×5)×100` cap 100 → skor absensi bulanan.
2. **Integrasi KpiCalculationService** — ambil attendance monthly score dari `kpi_evaluations`
   (saat ini manual KPI branch masih placeholder `achievement=0`).
3. **Mapping evaluator** — business rule (Kepala Toko→Teknisi/Admin; SPV→KT; KD→IT/MM/CS) belum di-encode.
4. **Migration historic** (opsional) — opsional; `penilaian` legacy belum dimigrasi.
5. **UI input daily** (opsional) — form evaluasi harian.

---

## VERIFIKASI AKHIR

| Item | Status |
|---|---|
| Schema `evaluation_date NOT NULL` + unique daily | ✅ |
| Model upsert by date | ✅ |
| Service derive period from date, date authoritative | ✅ |
| Focused tests CASE 1–5 | ✅ PASS (15/15) |
| KPI regression | ✅ 57/57 |
| Incentive regression | ✅ 11/11 |
| Salary regression | ✅ 63/63 |
| Asset regression | ✅ 12/12 |
| Legacy files untouched | ✅ |

**Kesimpulan:** Task 1B selesai — daily attendance now dapat disimpan per tanggal di `kpi_evaluations`,
tanpa menyentuh legacy & tanpa merusak regression baseline.
**STOP di sini — tidak lanjut ke agregasi bulanan.**

---

*Rekomendasi langkah berikut (di luar task 1B): lihat Task berikutnya untuk agregasi bulanan absensi.*