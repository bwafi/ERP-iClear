# Service Layer Review — Refactor KPI/Incentive (Tahap Lanjut)

**Tanggal:** 28 Agustus 2026
**Branch:** `refactor/kpi-incentive-engine`
**Status:** SERVICE LAYER REVIEW SELESAI. Controller BELUM di-wiring.

---

## A. Legacy Service Review

### `LegacyKpiCalculationService`

| Aspek | Status |
|---|---|
| Klasifikasi | **LEGACY COMPATIBILITY / REGRESSION** (bukan design final) |
| Tujuan | Mereplikasi `hitungKPIGaji()` secara literal → hasil OLD==NEW |
| Header comment | ✅ Ditambah blok `LEGACY COMPATIBILITY SERVICE` tegla |
| Bug yang dipertahankan (sengaja) | HPP `date('m')`, pembagi KT context-gaji `/4`, exact-match threshold |
| Alasan | Regression baseline harus identik; perbaikan dilakukan di final services |

### `UnitSalaryCalculationService` (asset berjalan)

| Aspek | Status |
|---|---|
| Klasifikasi | **LEGACY COMPATIBILITY / REGRESSION** |
| Tujuan | Mereplikasi `TutupKasir::assetberjalan()` secara literal |
| Perbedaan dari hitungKPIGaji | Customer query `COUNT(DISTINCT penjualan_idpenjualan)` JOIN barang; `total_feed` overwrite by feed_mingguan; insentif `/4` hardcoded; jabatan 46 (PIC) tidak punya case; absen hardcode 90 |
| Detail_absen | ✅ Diisi per case jabatan (35,36,41,40,42,43,44,45), kosong utk jab 1/46 — persis OLD |
| Status | Regression assetberjalan PASS (12/12) |

---

## B. Final Service Review

### `KpiCalculationService` (FINAL)

| Requirement | Implementasi |
|---|---|
| Baca config dari DB | ✅ `kpi_components`, `kpi_weights`, `kpi_targets` via model |
| Tidak ada switch jabatan utk bobot | ✅ Bobot dari `kpi_weights.weight_group='kpi'` |
| Tidak ada hardcoded target | ✅ Target dari `kpi_targets` (target_value + batas_*) per context |
| Tidak ada hardcoded pembagi | ✅ Dividend via calculator strategy |
| Periode eksplisit | ✅ `calculateForEmployee(id, unit, month, year, context, date)` |
| Rumus di service/calculator | ✅ `KpiScoreService` + calculators |
| Manual KPI | ⚠️ Di-skip (achievement=0) sampai `KpiEvaluationService` terintegrasi |

### `IncentiveCalculationService` (FINAL)

| Requirement | Implementasi |
|---|---|
| Group-based | ✅ `calculateGroupIncentive(code, unit, omset, achievement, date)` |
| Divisor dinamis | ✅ `countActiveMembers(group, unit, date)` — tidak ada `/3` `/4` |
| Rate dari DB | ✅ `incentive_rules.base_value` |
| Manual override | ✅ `toggle` config per employee via `incentive_members.is_active` |
| Duplikasi rate | ⛔ Tidak ada — hanya di `incentive_rules` |

### `SalaryCalculationService` (Payroll) — terpisah dari KPI & Incentive

### `KpiEvaluationService` — manual KPI (raw_score → normalized → weighted)

---

## C. assetberjalan() Mapping

### `TutupKasir::assetberjalan()` → `UnitSalaryCalculationService`

| Existing | Sumber | Formula | New method |
|---|---|---|---|
| omset_bulan | detail_penjualan+penjualan, unit, month | SUM(sub_total-hpp) | `calculateForUnit()` |
| per-employee loop | akun WHERE ID_UNIT | gaji each employee | `calculateForUnit()` |
| penempatan | akun.ALAMAT vs unit | 350000 jika bukan kota unit | inline |
| target/batas | array hardcoded | context-gaji | inline (literal) |
| customer | detail_penjualan JOIN barang | COUNT(DISTINCT penjualan) | inline (literal) |
| absen nilai | — | hardcode 90 | inline |
| detail_kpi/absen | switch jabatan | per case | switch (literal) |
| tunjangan | jabatan-based | 850k/1.25jt/1jt/250k | inline |
| gaji | pokok+all | 1.5jt + tunjangan + insentif | `gaji` |
| pengeluaran | kas_keluar kat [1,2,3,4,5,11,18] | SUM(jumlah) | inline |

---

## D. assetberjalan() Regression Result

| Unit | Component | OLD | NEW | Diff | Status |
|---|---|---|---|---|---|
| 1 | omset_bulan | 30,496,420 | 30,496,420 | 0.00 | PASS |
| 1 | pengeluaran | 2,355,700 | 2,355,700 | 0.00 | PASS |
| 1 | totalGajiUnit | 23,842,491 | 23,842,490.76 | 0.24 | PASS |
| 2 | omset_bulan | 30,539,280 | 30,539,280 | 0.00 | PASS |
| 2 | totalGajiUnit | 6,666,224 | 6,666,223.58 | 0.42 | PASS |
| 3 | totalGajiUnit | 9,604,057 | 9,604,056.55 | 0.45 | PASS |
| 4 | totalGajiUnit | 8,552,601 | 8,552,600.72 | 0.28 | PASS |

**RESULT: PASS=12 FAIL=0 (toleransi < 100 rupiah, floating-point)**

---

## E. Incentive Group Data Review

### Membership (100% dari `akun` — TIDAK ADA data fiktif)

| Group | Unit | Members (employee nyata) | Jumlah |
|---|---|---|---|
| KEPALA_TOKO | 1 | Ikmal(36), Ira(35), Inka(35) | 3 |
| KEPALA_TOKO | 2 | Faizin(36), Fian(41), iza(35) | 3 |
| KEPALA_TOKO | 3 | Adjie(36), Anggun(35), Arya(41) | 3 |
| KEPALA_TOKO | 4 | Radit A(36), Bima(41), Ryan(36) | 3 |
| DIGITAL_DIVISION | 1 | fathoni(43), Syahroni(43), Fahri(44), Indah(42) | 4 |

### Observable facts
- Unit 1 TIDAK punya employee jab 41 (Kepala Toko) → KEPALA_TOKO unit1 = 3 orang (Teknisi+2 Admin)
- IT (45) tidak punya active employee → DIGITAL_DIVISION saat ini 4 (2×43 + 44 + 42)
- Semua `effective_from='2024-01-01'`, `effective_to=NULL`, `is_active=1`

### Validasi seeder
- Seeder memetakan jabatan-id → group (SEED-TIME config, sekali jalan)
- Setelah seed, membership dikelola via `incentive_members` (bukan hardcode baru)

### Test dynamic membership
- Test 3→4 member menggunakan `INSERT` nyata ke DB (bukan mock) → PASS (750,000)
- Unit isolation test → PASS

---

## F. Threshold/Target Migration Review

### `kpi_targets` — REVISI (5 parameter dipertahankan)

| Kolom | Arti |
|---|---|
| `target_value` | target final (100%) |
| `batas_awal` | minimum (skor 0) |
| `batas_kedua` | tier 33 (gaji) / tier 2 |
| `batas_ketiga` | tier 66 |
| `batas_keempat` | tier 100- (masuk skor 100 dgn insentif di bawah target) |
| `context` | `gaji` | `penilaian_kinerja` | `slip_gaji` | `default` |

### Seed values (dari source code existing, tidak mengubah nilai)

| Unit | Context | target | batas_awal | batas_keempat |
|---|---|---|---|---|
| 1 | gaji | 50jt | 30jt | 45jt |
| 1 | penilaian | 55jt | 35jt | 50jt |
| 2 | gaji | 35jt | 18jt | 30jt |
| 3 | gaji | 60jt | 40jt | 55jt |
| 4 | gaji | 35jt | 18jt | 30jt |
| 4 | penilaian | 55jt | 35jt | 50jt |

**Biz rule UNKNOWN tetap: `/gaji` 50 vs `/penilaian` 55 — nilai dipertahankan, bukan diputuskan.**

---

## G. Period Handling Review

### `LegacyKpiCalculationService`
- Menerima `$bulan`/`$tahun` param dari caller
- ⚠️ HPP tetap pakai `date('m')` — **sengaja** (legacy replication, BUG-003 di-fix di final)

### `UnitSalaryCalculationService` (assetberjalan)
- Menerima `$bulan`/`$tahun` param (default current month)
- Asli `assetberjalan()` TIDAK menerima periode (hanya current month) — service menambah opsi tanpa mengubah baseline

### `KpiCalculationService` (FINAL)
- ✅ `calculateForEmployee(..., $month, $year, ..., ?$date)` — SEMUA periode eksplisit, TIDAK pakai `date()` saat caller beri periode

---

## H. Hardcode Remaining

### LEGACY COMPATIBILITY (sengaja, utk regression)
| Nilai | Lokasi | Alasan |
|---|---|---|
| target/batas arrays | LegacyKpiCalculationService | harus sama dgn lama |
| HPP date('m') | Legacy service | BUG-003 dipertahankan utk baseline |
| pembagi `/4` context gaji | Legacy service | BUG-008 literal |
| absen 90 | Legacy + asset service | literal |
| asset: insentif /4 | UnitSalaryCalculationService | literal |
| gaji_pokok 1.5jt | semua legacy | literal |

### CONFIGURATION (sudah dipindah ke DB)
| Item | Tabel |
|---|---|
| Anggota group | `incentive_members` |
| Rate insentif | `incentive_rules` |
| Target omzet & batas | `kpi_targets` |
| Bobot KPI & absen | `kpi_weights` |
| Komponen gaji | `salary_components` / `salary_structures` |

### CALCULATION LOGIC (benar di service)
- tiered score, normalize, weighted, cap 100 per KPI

### POTENTIAL BUG (belum diputuskan biz)
| Item | Status |
|---|---|
| Target `/gaji` 50 vs `/penilaian` 55 | UNKNOWN |
| Insentif KT 3% vs 1% (code 3%, rule 1%) | UNKNOWN |
| Manual KPI aggregation (avg/sum/latest) | UNKNOWN |

---

## I. Controller Integration Readiness

```text
KPI regression:        57/57 PASS (ALL_IDENTICAL)
Incentive test:        11/11 PASS
assetberjalan regression: 12/12 PASS
Salary regression:     integrasi SalaryCalculationService (belum ter-regression penuh)
Discrepancy tak terjelaskan: tidak ada (asset KPI/salary, KPI, incentive identik)
```

**STATUS: NOT_READY**

Alasan:
1. `SalaryCalculationService` (payroll) belum ter-regression terhadap slip_gaji.
2. `KpiEvaluationService` (manual KPI) belum terhubung ke alur final KpiCalculationService.
3. Warn UNKNOWN business rules (`/gaji` vs `/penilaian` target, insentif KT 1% vs 3%) belum dikonfirmasi.
4. Kontronler tetap TIDAK diubah (sesuai instruksi).

---

## Files Changed (this stage)

```
app/Services/Kpi/LegacyKpiCalculationService.php      (edit: komentar LEGACY terb)
app/Services/Kpi/KpiScoreService.php                  (edit: audit doc + cap param)
app/Services/Kpi/KpiCalculationService.php            (edit: config-driven, periode eksplisit)
app/Services/Payroll/UnitSalaryCalculationService.php (NEW)
app/Scripts/assetberjalan_regression.php              (NEW)
app/Scripts/assetberjalan_old_capture.php             (NEW)
app/Scripts/assetberjalan_debug.php                   (NEW)
app/Database/Migrations/2026-08-28-000000_*.php       (edit: kpi_targets + context + batas_*)
app/Database/Seeds/KPIConfigurationSeeder.php         (edit: target threshold + context)
app/Database/Seeds/IncentiveGroupSeeder.php           (edit: komentar non-fiktif)
app/Models/ModelKpiTarget.php                         (edit: context + batas_*)
```

## Regression Commands (PHP 7.4 untuk session-based)

```bash
php app/Scripts/kpi_regression.php 08 2026 0.01
php app/Scripts/incentive_test.php
php74 app/Scripts/assetberjalan_regression.php 08 2026
```