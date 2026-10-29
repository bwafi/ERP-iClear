# KPI Engine Architecture

**Tanggal:** 28 Agustus 2026 (diperbarui 2026-10-29)
**Branch:** `refactor/kpi-incentive-engine`
**Status:** Dokumen historis + catatan implementasi aktual. Service layer sudah dibangun; **`KpiCalculationService` sudah dipakai controller/payroll aktif**. Bagian “legacy” di bawah dipertahankan sebagai rekaman refactor.

> **Koreksi audit 2026-10-26:** pernyataan lama “belum wiring ke Controller” dan “`KpiCalculationService` belum dipakai periode berjalan” **tidak lagi benar**. Pemanggilan aktif ditemukan di `PenilaianKPI`, `PayrollGenerator`, `HoSalaryDistributionService`, `UnitSalaryCalculationService`, dan `ManagerKpiService`. `LegacyKpiCalculationService` tidak dipanggil controller aktif. Lihat `docs/prd/audit/PRD_AUDIT_REPORT.md` X-09/KPI-05.

---

## 1. Tujuan

Memindahkan business logic perhitungan KPI/insentif/gaji dari Controller ke Service layer,
**tanpa mengubah hasil perhitungan** sistem lama.

Engine KPI periode berjalan adalah `KpiCalculationService`. Konfigurasi komponen/bobot/target
dibaca dari `kpi_components`, `kpi_weights`, dan `kpi_targets`; skor absensi dari
`kpi_evaluations`. Tabel lama (`template_kpi`, `template_penilaian`, `penilaian`,
`penilaian_detail`, `penilaian_kpi`) tetap ada untuk data historis dan input manual yang
belum dimigrasi.

---

## 2. Arsitektur Service

```
app/
├── Controllers/                  ← sudah memakai KpiCalculationService untuk periode berjalan
│   ├── PenilaianKPI.php
│   ├── TutupKasir.php
│   └── KeyPerformance.php
│
├── Services/
│   ├── Kpi/
│   │   ├── LegacyKpiCalculationService.php   ← HISTORIS: replikasi hitungKPIGaji(), tidak dipanggil controller aktif
│   │   ├── KpiScoreService.php               ← helper generic (score, weighted score)
│   │   ├── KpiCalculationService.php         ← ENGINE AKTIF periode berjalan
│   │   ├── KpiEvaluationService.php          ← manual KPI
│   │   ├── OmsetTokoCalculator.php           ← calculator (omzet = gross profit; lihat catatan)
│   │   ├── CustomerCalculator.php
│   │   ├── OmsetCabangCalculator.php
│   │   └── Calculators/
│   │       └── MetricCalculator.php          ← data penilaian/tutup kasir/opname
│   │
│   ├── Incentive/
│   │   └── IncentiveCalculationService.php   ← group-based, divisor dinamis
│   │
│   └── Payroll/
│       └── SalaryCalculationService.php      ← komponen gaji (tahap berikutnya)
│
├── Models/
│   ├── ModelIncentiveGroup.php
│   ├── ModelIncentiveMember.php
│   ├── ModelIncentiveRule.php
│   └── ModelKpi{Component,Weight,Target,Evaluation}.php
│
└── Scripts/
    ├── kpi_regression.php      ← OLD vs NEW comparison harness
    ├── kpi_migrate_seed.php    ← migration + seeding standalone
    └── incentive_test.php      ← verifikasi business rule insentif
```

---

## 3. Alur Perhitungan (Legacy, Dijaga Identik)

`LegacyKpiCalculationService::calculate($idAkun, $bulan, $tahun, $context)`
mereplikasi `PenilaianKPI::hitungKPIGaji()` secara **literal**:

```
akun (ID_AKUN)
   ↓
jabatan + unit + tunjangan_penempatan
   ↓
target_unit[unit]          (gaji vs non-gaji)
batas_awal/kedua/ketiga/keempat & target_omset[unit]
   ↓
omzet per unit 1-4         (SUM sub_total - hpp)
customer per unit 1-4      (gaji: COUNT idpenjualan; non: COUNT kode_invoice)
hpp per unit 1-4           (date('m') HARDCODED — dipertahankan sama dgn lama)
   ↓
tutup kasir, opname, divisi, kebersihan, seragam, kepatuhan
   ↓
sum aspek per pegawai: closing, upselling, followup/follow up, budgeting,
roas, feed, video, story, testimoni, bug minor, operasional, ecommerce,
kehadiran, kebersihan, seragam, kepatuhan sop
   ↓
nilai absen (gaji: hardcode 90; non-gaji: avg kehadiran × 20)
   ↓
nilai omset per jabatan (41/40/43/default) + insentif legacy
   ↓
detail_kpi + detail_absen per jabatan
   ↓
skor_total, skor_total2
   ↓
tunjangan_kinerja + tunjangan_absen + penempatan + insentif
   ↓
gaji = gaji_pokok + tunjangan + insentif
```

### Mapping

| Existing Code | Existing Formula | New Service | Source |
|---|---|---|---|
| `hitungKPIGaji()` | omzet = SUM(sub_total - hpp) | `LegacyKpiCalculationService::calculate()` | `detail_penjualan` + `penjualan` |
| `hitungKPIGaji()` | tiered omset 41/40/43/default | `calculate()` inline (dipertahankan) | `batas_*` + `target_omset` |
| `sumAspek()` | SUM(skor) per aspek per pegawai | `MetricCalculator::sumAspekScore()` | `penilaian` |
| AVG per aspek global | AVG(skor) | `MetricCalculator::avgAspekScoreGlobal()` | `penilaian` |
| tutup kasir | COUNT(status) | `MetricCalculator::countTutupKasir()` | `tutup_kasir` |
| opname | COUNT(periode FINAL terisi penuh) | `MetricCalculator::countStokOpname()` | `stok_opname_periode` |
| skor_total | Σ(nilai × bobot)/100 | `KpiScoreService::totalWeightedScore()` | detail_kpi |
| weighted score | score/100 × bobot | `KpiScoreService::weightedScore()` | per item |

---

## 4. Incentive Refactor

### Business Rule (CONFIRMED)

```
KEPALA_TOKO      : individual = 1% × omset toko (PER ANGGOTA, tidak dibagi jumlah anggota)
                   pool = individual × countActiveMembers(group, unit, date)
DIGITAL_DIVISION : pool = 1% × omset toko ; individual = pool / countActiveMembers(group, unit, date)
```

**Divisor TIDAK di-hardcode.** Dihitung dari `incentive_members`:
- group
- unit/cabang
- effective period
- is_active

**Metode pembagian** ditentukan `incentive_rules.division_method`:
- `per_member` (KEPALA_TOKO) → tiap anggota dapat rate% × omset
- `member_count` (DIGITAL_DIVISION) → pool rate% × omset dibagi rata anggota

### Struktur

```
incentive_groups        (KEPALA_TOKO, DIGITAL_DIVISION)
    ↓
incentive_members       (employee_id + unit_id + effective_from/to + is_active)
    ↓
incentive_rules         (rate per group + division_method: KEPALA_TOKO 1% per_member, DIGITAL_DIVISION 1% member_count)
    ↓
IncentiveCalculationService::calculateGroupIncentive(code, unit, omset, achievement, date)
```

### Manager Hiatus / Duplikasi Rate Dicegah

`kpi_components` TIDAK punya kolom rate.
`incentive_groups` TIDAK punya kolom rate.
Rate hanya di `incentive_rules.base_value`. Satu sumber.

---

## 5. Regression Test

`app/Scripts/kpi_regression.php`

```
OLD  = PenilaianKPI::hitungKPIGaji() via Reflection
NEW  = LegacyKpiCalculationService::calculate()
```

Dibandingkan untuk 19 karyawan × 3 context (gaji, penilaian_kinerja, slip_gaji):
- detail_kpi (nama, bobot, nilai)
- detail_absen (nama, bobot, nilai)
- skor_total, skor_total2
- tunjangan_kinerja, tunjangan_absen, insentif, gaji_pokok, gaji

**Hasil: 57 PASS / 0 FAIL / ALL_IDENTICAL** (toleransi 0.01).

---

## 6. Status Integrasi (diperbarui 2026-10-26)

**ACTIVE — `KpiCalculationService` dipakai.**

- Pemanggilan aktif: `PenilaianKPI`, `PayrollGenerator`, `HoSalaryDistributionService`, `UnitSalaryCalculationService`, `ManagerKpiService`.
- `LegacyKpiCalculationService` hanya menjadi pembanding historis/regression, bukan jalur produksi.
- `TutupKasir::assetberjalan()` masih menyimpan formula/query dashboard terpisah; insentif-nya memakai `KpiCalculationService::computeIncentive()`.
- Manual KPI, evaluasi multi-periode, dan salary component tetap memiliki jalur/service masing-masing; lihat PRD Modul 08.
- Catatan definisi: komponen KPI bernama “omzet” pada `OmsetTokoCalculator`/`OmsetCabangCalculator` dihitung dari gross profit (`SUM(sub_total - hpp_penjualan)`). Konfirmasi bisnis diperlukan bila label “omzet” harus berarti omzet penjualan (KPI-06).

---

## 7. Migrasi Konfigurasi (Tahap Lanjut)

- `kpi_components`, `kpi_weights`, `kpi_targets`, `kpi_evaluations` sudah dibuat, di-seed, dan
  dipakai `KpiCalculationService` sebagai konfigurasi aktif periode berjalan.
- Bobot per jabatan sudah divalidasi = 100 (grup kpi & absen).
