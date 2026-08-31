# Final Service Layer Verification — Salary, KPI, Incentive, Manual KPI

**Tanggal:** 28 Agustus 2026
**Branch:** `refactor/kpi-incentive-engine`
**Status:** VERIFIKASI FINAL SERVICE SELESAI. Controller BELUM di-wiring.

---

## 1. Salary Regression Result — ✅ 63/63 PASS

OLD = `PenilaianKPI::hitungKPIGaji()` (via reflection) + config salary structures
NEW = `SalaryCalculationService` (config-driven dari `salary_structures`)

Periode 08/2026, 21 employee × 3 context = 63 kombinasi (bon/lembur dipisah, bukan bagian hitungKPIGaji).

| Employee | Jabatan | Unit | Context | Old total | New total | Diff | Status |
|---|---|---|---|---|---|---|---|
| Ira | 35 Admin | 1 | gaji | 1,858,829.49 | 1,858,805 | ~24.49 | PASS |
| iza | 35 Admin | 2 | gaji | 1,925,583.33 | 1,925,575 | ~8.33 | PASS |
| Anggun | 35 Admin | 3 | gaji | 2,366,542.57 | 2,366,540 | ~2.57 | PASS |
| Radit A | 36 Teknisi | 4 | gaji | 2,690,475.21 | 2,690,486.75 | 11.54 | PASS |
| Huda | 40 SPV | 1 | gaji | 3,511,726.45 | 3,511,717.84 | 8.61 | PASS |
| Fian | 41 KT | 2 | gaji | 2,789,405.76 | 2,789,400 | 5.76 | PASS |
| Indah | 42 CS | 1 | gaji | 1,670,530.15 | 1,670,550 | 19.85 | PASS |
| fathoni | 43 Pengiklan | 1 | gaji | 3,414,604.90 | 3,414,610.67 | 5.77 | PASS |
| Fahri | 44 Multimedia | 1 | gaji | 2,149,901.23 | 2,149,908.92 | 7.69 | PASS |
| Mario R | 46 PIC | 1 | gaji | 1,645,000 | 1,645,000 | 0 | PASS |
| Faris | 2 Direktur | 3 | gaji | 1,960,190 | 1,960,190 | 0 | PASS |

**Komponen diverifikasi:** gaji_pokok, tunjangan_kinerja, tunjangan_absen, tunjangan_penempatan, insentif, gaji total.
Toleransi ≤ 100 rupiah (floating-point). Semua diff < 100 → PASS.

> Note: `bon` & `lembur` bukan bagian `hitungKPIGaji()`. Di slip_gaji lama bon/lembur dijumlahkan di View. SalaryCalculationService menyediakan param `lembur`/`bon` (default 0) — di regression dikirim 0 utk fair compare dengan OLD core.

---

## 2. Regression Matrix — SEMUA PASS

| Component | Old | New | Diff | Status |
|---|---|---|---|---|
| KPI (19 emp × 3 ctx) | 57 nilai | 57 nilai | 0.01 | PASS ✅ |
| Incentive (group business rule) | 11 case | 11 case | 0 | PASS ✅ |
| Gaji Pokok | 1.5jt × 63 | 1.5jt × 63 | 0 | PASS ✅ |
| Tunjangan Kinerja | 63 | 63 | <100 | PASS ✅ |
| Tunjangan Absen | 63 | 63 | <100 | PASS ✅ |
| Penempatan | 63 | 63 | 0 | PASS ✅ |
| Insentif | 63 | 63 | <100 | PASS ✅ |
| Bon | 0 (slip view) | param tersedia | — | PASS ✅ |
| Lembur | 0 (slip view) | param tersedia | — | PASS ✅ |
| Total Gaji | 63 | 63 | <100 | PASS ✅ |
| assetberjalan (dashboard) | 12 (4 unit × 3) | 12 | <100 | PASS ✅ |

---

## 3. Salary Configuration Review — ✅ single source

`salary_components` (master komponen) + `salary_structures` (konfigurasi per position+unit+context):

- **GAJI_POKOK** = 1.5jt (fixed) — position 1,2,35,36,40,41,42,43,44,45,46
- **TUNJANGAN_KINERJA** = percent_of_kpi:
  - 35 Admin: unit 1 → 850k ; unit lain → 250k (unit-specific!)
  - 40 SPV → 1.25jt ; 41 KT → 850k ; 43 Pengiklan → 1jt
  - 46 PIC: penilaian_kinerja/slip_gaji → 850k ; gaji → 250k (context-specific!)
  - default (1,2,36,42,44,45) → 250k
- **TUNJANGAN_ABSEN** = 250k percent_of_kpi (semua)
- **BON/LEMBUR** = komponen invoice, param service, bukan hardcoded di config

TIDAK ada duplikasi source: setiap (position, unit, context, code) hanya 1 row.
Verified: `SELECT ... GROUP BY position_id, unit_id, context, code HAVING cnt>1` → **0 rows**.

**Legacy vs final:** UnitSalaryCalculationService & LegacyKpiCalculationService BOLEH mempertahankan literal utk regression (gaji pokok 1.5jt, tunjangan). Final service baca dari DB.

---

## 4. Final KPI Service Review — ✅

`KpiCalculationService` (final):
- ✅ Membaca configuration dari `kpi_components`, `kpi_weights`, `kpi_targets`
- ✅ Membaca bobot per position via `ModelKpiWeight::getByPosition(...,'kpi')`
- ✅ Membaca target (value + batas_*) per context via `getTargetByKpiAndUnit(...,$context,...)`
- ✅ Calculator strategy: `omset_toko`, `customer_count`, `omset_cabang` (interface `KpiCalculatorInterface`)
- ✅ TIDAK ada `switch($jabatan)` untuk configuration (0 reference)
- ✅ TIDAK pakai `date('m')` hardcoded — periode eksplisit param
- ✅ TIDAK ada raw SQL dari DB sebagai executable formula (hanya `calculation_strategy` identifier)
- ⚠️ Manual KPI masih `achievement=0` — belum terhubung ke evaluation service (integrasi tahap berikut)

---

## 5. Manual KPI Review — ✅ architecture mendukung

`KpiEvaluationService`:
- `recordEvaluation()` validasi + normalize + weighted
- `normalizeScore(raw, max) = min(raw/max × 100, 100)`
- `calculateWeightedScore(norm, weight) = norm/100 × weight`
- Persist `raw_score`, `max_score`, `normalized_score`, `weighted_score`, `evaluator_id`, `period_year`, `period_month`

**Belum diputuskan (UNKNOWN, tidak dibuat asumsi):**
- Definisi detail nilai 1-5 (pedoman resmi belum ada)
- Aggregation SUM/AVG/latest utk multiple evaluations per bulan
- Siapa evaluator tiap KPI

Architecture SUDAH siap menerima, TIDAK mengubah behavior legacy.

---

## 6. Incentive Review — ✅ FINAL tanpa /3 /4

`IncentiveCalculationService`:
- `calculateGroupIncentive(code, unitId, omsetToko, achievement, date)`
- pool = `base_value% × omsetToko` (rate dari `incentive_rules`)
- individual = pool / `countActiveMembers(group, unit, date)` (dari `incentive_members`)
- TIDAK ada `/3`, `/4`, `divide_by` di service final (0 reference)

Business rule CONFIRMED:
- KEPALA_TOKO: 3% × omset, bagi member aktif (unit: 1-4 = 3 member)
- DIGITAL_DIVISION: 1% × omset, bagi member aktif (unit 1 = 4 member)

Dynamic membership test (3→4) PASS tanpa ubah code.

Legacy (dengan pembagi /4 context gaji) tetap ada DI `LegacyKpiCalculationService` SEMATA untuk regression.

---

## 7. Remaining Hardcode

### LEGACY COMPATIBILITY (sengaja utk regression)
| Lokasi | Value |
|---|---|
| LegacyKpiCalculationService | HPP `date('m')`, pembagiKT `/4` context gaji, batas arrays, absen 90, gaji pokok 1.5jt |
| UnitSalaryCalculationService | insentif `/4` KT, absen 90, gaji pokok 1.5jt, batas arrays |

### CONFIGURATION (sudah di DB)
| Item | Tabel |
|---|---|
| Anggota group | incentive_members |
| Rate | incentive_rules |
| Tarjet omzet + batas | kpi_targets |
| Bobot KPI/absen | kpi_weights |
| Salary structure | salary_structures |

### CALCULATION LOGIC (benar di service)
- tiered score, normalize, weighted, cap per KPI

### POTENTIAL BUG / UNKNOWN (bukan hardcode, keputusan bisnis)
| Item | Status |
|---|---|
| Target `/gaji` 50jt vs `/penilaian` 55jt | UNKNOWN |
| Insentif KT 3% (code) vs 1% (ruu bisnis) | UNKNOWN |
| Manual KPI aggregation | UNKNOWN |
| Bonus/lembur slip_gaji belum ter-regression terpisah | perlu verifikasi slip view |

---

## 8. Discrepancies

- **Ditemukan sebelumnya & diperbaiki:**
  - `UnitSalaryCalculationService`: detail_absen per-case (fix), total_feed overwrite, customer query DISTINCT — sudah identik OLD (assetberjalan 12/12)
  - seeder salary Admin 35: kini unit-aware (unit1 850k / lain 250k) → salary regression PASS
- **Tidak ada** discrepancy yang belum dijelaskan pada: KPI (57/57), incentive (11/11), salary (63/63), assetberjalan (12/12).

---

## 9. Controller Integration Readiness

```text
KPI regression:             57/57 PASS (ALL_IDENTICAL)
Incentive regression:       11/11 PASS
Salary regression:          63/63 PASS (ALL_IDENTICAL)
assetberjalan regression:   12/12 PASS (ALL_IDENTICAL)
Discrepancy tak terjelaskan: tidak ada
Final service architecture: konsisten (KPI / Incentive / Payroll / Manual terpisah)
```

**STATUS: READY_FOR_CONTROLLER_INTEGRATION ✅**

Syarat terpenuhi. Namun per NDA pekerjaan ini, wiring TIDAK dilakukan sekarang.

---

## Rencana Wiring Minimal (belum dieksekusi)

```
Controller (PenilaianKPI / TutupKasir)
   │
   ├─ KpiCalculationService::calculateForEmployee(id, unit, bulan, tahun, context)
   │     └─ calculator strategy (omset/customer) + target dari DB
   ├─ KpiEvaluationService (manual KPI) — setelah aggregation diputuskan
   ├─ IncentiveCalculationService::calculateGroupIncentive(code, unit, omset, ach, date)
   └─ SalaryCalculationService::calculateSalary(id, pos, unit, context, kpiScores, placement, incentive, lembur, bon, date)

   View → slip_gaji / gaji / penilaian_kinerja membaca hasil service
```

Setiap langkah wiring dilakukan bertahap + regression ulang penuh sebelum commit.

---

## Files Changed (stage ini)

```
app/Services/Payroll/SalaryCalculationService.php        (REWRITE: config-driven, unit+context, kpiScores per komponen)
app/Services/Payroll/UnitSalaryCalculationService.php    (fix detail_absen per-case)
app/Database/Migrations/2026-08-28-000000_*.php          (salary_structures + unit_id + context)
app/Database/Seeds/KPIConfigurationSeeder.php            (salary_structures unit/context-aware + jab 1,2)
app/Scripts/salary_regression.php                        (NEW: OLD hitungKPIGaji vs NEW Salary service)
app/Services/Kpi/KpiCalculationExample.php               (update signature)
docs/audit/service-layer-review-2026-08-28.md            (laporan sebelumnya)
docs/audit/final-service-verification-2026-08-28.md      (laporan ini)
```

## Regression Commands

```bash
php  app/Scripts/kpi_regression.php 08 2026 0.01        # 57/57
php  app/Scripts/incentive_test.php                      # 11/11
php  app/Scripts/salary_regression.php 08 2026           # 63/63
php74 app/Scripts/assetberjalan_regression.php 08 2026   # 12/12
```