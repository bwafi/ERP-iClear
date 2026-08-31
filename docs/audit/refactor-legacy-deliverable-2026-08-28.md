# Deliverable Report — Refactor Existing KPI/Incentive Engine

**Tanggal:** 28 Agustus 2026
**Branch:** `refactor/kpi-incentive-engine`
**Fokus:** Refactor arsitektur KPI existing → service layer, hasil identik dengan sistem lama.

---

## 1. Legacy KPI Mapping

### Existing Logic → Service Method

| Existing Code | Existing Formula | Source | New Service Method |
|---|---|---|---|
| `PenilaianKPI::hitungKPIGaji()` omzet | `SUM(sub_total - hpp)` per unit, bulan/tahun param | `detail_penjualan` + `penjualan` | `LegacyKpiCalculationService::calculate()` (inline) |
| omzet tiered jab 41 | `0/33/66/100` + interpolasi | `batas_*`, `target_omset` | `calculate()` inline (literal) |
| omzet cabang_aman jab 40/43 | gaji `33/66/100`, non `25/50/75/100` | `batas_keempat`, `target_omset` | `calculate()` inline |
| omzet default | `0/33/66/100` | `batas_2..4`, `target` | `calculate()` inline |
| customer gaji | `min(actual/target×100, 100)` | `penjualan` COUNT(idpenjualan) | `calculate()` inline |
| customer non-gaji | cabang_aman vs atas_customer | `penjualan` COUNT(kode_invoice) | `calculate()` inline |
| closing/upselling/followup | `min(actual/target×100, 100)` | `penilaian` SUM(skor) | `MetricCalculator::sumAspekScore()` |
| roas/budgeting | gaji `×100`, non `×20` | `penilaian` SUM(skor) | `calculate()` inline |
| HPP global per unit | `total/4` grade 100/75/50/0 | `detail_penjualan` SUM(hpp) | `calculate()` inline |
| tutup kasir | gaji `count/30×20`, non `min(count/30×100,100)` | `tutup_kasir` COUNT | `MetricCalculator::countTutupKasir()` |
| opname | `count/4×100` | `stok_opname_draft` | `MetricCalculator::countStokOpname()` |
| divisi/kebersihan/seragam/kepatuhan | `avg×20` | `penilaian` AVG | `MetricCalculator::avgAspekScoreGlobal()` |
| absen | gaji 90 hardcode, non `avg kehadiran×20` | `penilaian` AVG aspek kehadiran | `calculate()` inline |
| skor total | `Σ(nilai×bobot)/100` | detail_kpi | `KpiScoreService::totalWeightedScore()` |
| weighted score | `score/100 × bobot` | per item | `KpiScoreService::weightedScore()` |
| detail_kpi + detail_absen | switch jabatan | — | `calculate()` switch (literal) |
| insentif legacy | per jabatan + pembagi context | — | inline (dipertahankan utk regression) |
| gaji | `pokok + kinerja + absen + penempatan + insentif` | — | inline (dipertahankan) |

---

## 2. LegacyKpiCalculationService Structure

```
calculate($idAkun, $bulan, $tahun, $context)
  ├─ karyawan (akun)
  ├─ jabatan, unit, tunjangan_penempatan
  ├─ target_unit (gaji vs non-gaji)
  ├─ batas & target omset (gaji vs non-gaji)
  ├─ omset per unit 1-4 + customer per unit 1-4
  ├─ hpp per unit (date() hardcoded — literal)
  ├─ tutup kasir, opname, divisi, kebersihan, seragam, kepatuhan
  ├─ sum aspek per pegawai (closing..kepatuhan sop)
  ├─ nilai absen
  ├─ nilai omset per jabatan (41/40/43/default) + insentif legacy
  ├─ detail_kpi + detail_absen switch jabatan
  ├─ skor_total, skor_total2
  ├─ tunjangan_kinerja (per jabatan)
  └─ gaji total
```

Output array identik dengan `hitungKPIGaji()`: `karyawan, akun, jabatan, unit,
aktual_omset_unit, detail_kpi, detail_absen, skor_total, skor_total2,
tunjangan_kinerja, tunjangan_absen, insentif, gaji_pokok, gaji`.

---

## 3. Incentive Refactor

### Sebelum (hardcoded di controller)

```php
$pembagiInsentifKepalaToko = ($context === 'gaji') ? 4 : 3;
$pembagiInsentifPengiklan  = ($context === 'gaji') ? 1 : 4;
... (3/100) * $aktual_omset / $pembagiInsentifKepalaToko
```

### Sesudah (group-based, divisor dinamis)

```php
IncentiveCalculationService::calculateGroupIncentive($code, $unitId, $omsetToko, $achievement, $date)

pool        = rate% × omsetToko          // rate dari incentive_rules (3% / 1%)
memberCount = countActiveMembers(group, unit, date)   // dari incentive_members
individual  = pool / memberCount          // TANPA hardcode /3 /4
```

### Struktur Data

```
incentive_groups          code, name, is_active, effective_from/to
incentive_members         group_id, employee_id, unit_id, effective_from/to, is_active
incentive_rules           group_id, kpi_component_id, base_value (rate), minimum_achievement
```

### Seeding (dari struktur organisasi existing)

- KEPALA_TOKO: unit 1-4 = 3 member (KT + Teknisi + Admin)
- DIGITAL_DIVISION: unit 1 = 4 member (Pengiklan + IT + Multimedia + CS)

> Catatan: DIGITAL_DIVISION hanya ter-seed di unit 1 karena hanya ada karyawan
> role digital di unit 1. Bila employee pindah unit, seeder perlu update.

---

## 4. Hardcode Removed (dari service baru)

| Hardcode | Lokasi lama | Status |
|---|---|---|
| Pembagi insentif context-based (`? 4 : 3`, `? 1 : 4`) | PenilaianKPI.php:845-846 | **Removed** di IncentiveCalculationService → countActiveMembers |
| Switch jabatan insentif | controller switch | **Dipindah** ke evaluate group (belum di-wiring) |
| Bobot KPI per jabatan | controller switch | **Dipindah** ke kpi_weights tabel (migrasi config) |
| Target & batas per unit | controller array | **Dipindah** ke kpi_targets tabel (migrasi config) |

---

## 5. Hardcode Remaining

| Hardcode | Klas | Alasan |
|---|---|---|
| `gaji_pokok = 1500000` di LegacyKpiCalculationService | LEGACY COMPAT | Harus sama dgn output lama; migrasi ke salary_components tahap lanjut |
| `tunjangan_kinerja` & `tunjangan_absen` & `penempatan` di Legacy service | LEGACY COMPAT | Sama dgn sistem lama; nanti dari salary_structures |
| target/batas arrays di dalam `LegacyKpiCalculationService` | LEGACY COMPAT | Replikasi literal; nanti dari kpi_targets |
| `date('m')` HPP (BUG-003) di Legacy service | LEGACY COMPAT | **Dibuat literal supaya regression lulus.** Fix terpisah setelah baseline |
| pembagi `/4` context gaji (BUG-008) di Legacy service | LEGACY COMPAT | Harus identik dgn lama utk regression; fix di service insentif baru |
| ID jabatan (35,36,40,41,42,43,44,45,46) di Legacy switch | LEGACY COMPAT | Struktur bisnis baseline |
| `avg × 20`, `/26 × 20`, `/4 × 20` dll. | CALCULATION LOGIC | Merupakan rumus (biarkan di service) |
| `assetberjalan()` TutupKasir (dashboard) | LEGACY COMPAT | Belum direplikasi — pekerjaan lanjut |

---

## 6. Regression Test Result

**Metode:** `app/Scripts/kpi_regression.php`
**OLD:** `PenilaianKPI::hitungKPIGaji()` via Reflection
**NEW:** `LegacyKpiCalculationService::calculate()`
**Data:** 19 karyawan aktif × 3 context = 57 kombinasi
**Periode:** 2026-08 | **Toleransi:** 0.01

```
RESULT: PASS=57 FAIL=0
STATUS: ALL_IDENTICAL
```

### Sampel

| Employee | Context | Old KPI | New KPI | Diff | Old Gaji | New Gaji | Status |
|---|---|---|---|---|---|---|---|
| Fian (KT, unit2) | gaji | 88.30 | 88.30 | 0 | 2,789,405.76 | 2,789,405.76 | PASS |
| Huda (SPV, unit1) | kinerja | 41.82 | 41.82 | 0 | 2,553,813.49 | 2,553,813.49 | PASS |
| fathoni (Pengiklan) | gaji | 70.00 | 70.00 | 0 | 3,414,604.90 | 3,414,604.90 | PASS |
| Indah (CS, unit1) | slip | 17.34 | 17.34 | 0 | 1,701,030.15 | 1,701,030.15 | PASS |
| Anggun (Admin, unit3) | gaji | 88.23 | 88.23 | 0 | 2,366,542.57 | 2,366,542.57 | PASS |

### Incentive (business rule confirmed)

**Metode:** `app/Scripts/incentive_test.php`

```
TEST 1 KT omset 100jt × 3% / 3 member        → pool 3.000.000, individual 1.000.000  PASS
TEST 2 DD omset 100jt × 1% / 4 member        → pool 1.000.000, individual 250.000    PASS
TEST 3 dynamic: KT +1 member (3→4)           → individual otomatis 750.000 (tanpa ubah code) PASS
TEST 4 unit isolation (unit2 = 3 member)      → individual 1.000.000                   PASS
TEST 5 achievement 80% < min 100%            → insentif 0                             PASS
TEST 6 unit tanpa member                     → insentif 0 (tanpa div-by-zero)          PASS
RESULT: PASS=11 FAIL=0
```

---

## 7. Discrepancies

**Tidak ada discrepancy antara OLD dan NEW Legacy engine** (57/57 identik).

Discrepancy yang **sengaja dikenali** (bukan dari refactor):

| Komponen | OLD | NEW (jika pakai IncentiveCalculationService) |
|---|---|---|
| Insentif KT context gaji | `3% omset / 4` (bug BUG-008) | `3% omset / 3` (sesuai business rule) |
| Insentif Pengiklan context gaji | `1% omset / 1` | `/ countActiveMembers(DD,unit)` |
| HPP | `date('m')` (bug BUG-003) | fix di service baru (tahap lanjut) |

Discrepancy ini **tidak** memengaruhi regression Legacy engine karena Legacy tetap
literal. Perbaikan business rule akan diterapkan pada service insentif baru + wiring.

---

## 8. Business Rule Confirmed

| Rule | Nilai | Konfirmasi |
|---|---|---|
| Insentif Kepala Toko | 3% × omset toko, dibagi member aktif group per unit | Owner ✅ |
| Insentif Digital Division | 1% × omset toko, dibagi member aktif group per unit | Owner ✅ |
| Divisor dinamis (bukan /3 /4 hardcoded) | dari incentive_members | Owner ✅ |

---

## 9. Business Rule Unknown

| Rule | Status | Butuh |
|---|---|---|
| Target omzet `/gaji` 50M vs `/penilaian`/`slip_gaji` 55M | UNKNOWN | Konfirmasi |
| Definisi skor 1-5 manual KPI | UNKNOWN | Pedoman |
| Aggregation manual KPI (SUM/AVG/latest) | UNKNOWN | Pedoman |
| Evaluator tiap KPI manual | UNKNOWN | Role owner |
| Formula dalam dashboard `assetberjalan()` | UNKNOWN | Apakah = gaji/slip |

---

## 10. Controller Integration Readiness

**Status: NOT_READY**

Alasan:
- Legacy engine + service insentif sudah diuji (57/57 + 11/11 PASS).
- Namun `assetberjalan()` dashboard belum direplikasi.
- Wiring controller belum dimulai (sesuai instruksi — menunggu status READY).
- Perlu menyelesaikan: replicasi `assetberjalan`, sanity test per jabatan,
  dan keputusan business rule target `/gaji` vs `/penilaian`.

---

## Files Changed (branch ini)

### Baru
```
app/Services/Kpi/LegacyKpiCalculationService.php
app/Services/Kpi/KpiScoreService.php
app/Services/Kpi/Calculators/MetricCalculator.php
app/Services/Kpi/OmsetTokoCalculator.php
app/Services/Kpi/CustomerCalculator.php
app/Services/Kpi/OmsetCabangCalculator.php
app/Services/Kpi/KpiCalculationService.php
app/Services/Kpi/KpiEvaluationService.php
app/Services/Kpi/KpiCalculatorInterface.php
app/Services/Kpi/KpiCalculationExample.php
app/Services/Incentive/IncentiveCalculationService.php
app/Services/Payroll/SalaryCalculationService.php
app/Models/ModelIncentiveGroup.php
app/Models/ModelIncentiveMember.php
app/Models/ModelIncentiveRule.php
app/Models/ModelKpiComponent.php
app/Models/ModelKpiWeight.php
app/Models/ModelKpiTarget.php
app/Models/ModelKpiEvaluation.php
app/Models/ModelSalaryComponent.php
app/Models/ModelSalaryStructure.php
app/Database/Migrations/2026-08-28-000000_CreateKPIConfigurationSchema.php
app/Database/Seeds/KPIConfigurationSeeder.php
app/Database/Seeds/IncentiveGroupSeeder.php
app/Scripts/kpi_regression.php
app/Scripts/kpi_migrate_seed.php
app/Scripts/incentive_test.php
docs/architecture/kpi-engine.md
docs/architecture/kpi-business-rules.md
docs/architecture/kpi-database-schema.md
docs/architecture/kpi-implementation-summary.md
```

### Tidak Diubah (sesuai instruksi)
```
app/Controllers/PenilaianKPI.php   ✗ tidak disentuh
app/Controllers/TutupKasir.php     ✗ tidak disentuh
app/Controllers/KeyPerformance.php ✗ tidak disentuh
```

---

## Perintah untuk Reviewer

```bash
# Regression KPI (OLD vs NEW)
php app/Scripts/kpi_regression.php 08 2026 0.01

# Test incentive group
php app/Scripts/incentive_test.php

# Migration + seed (tabel konfigurasi baru)
php app/Scripts/kpi_migrate_seed.php
```