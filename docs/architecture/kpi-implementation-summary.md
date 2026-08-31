# KPI/Incentive Database Schema Implementation Summary

**Project:** ERP System KPI Refactoring  
**Date:** 2026-08-28  
**Status:** Ready for Implementation  

---

## Deliverables Created

### 1. Database Schema Design Document
**Location:** `docs/architecture/kpi-database-schema.md`

Comprehensive 400+ line document covering:
- Part 1: Analysis of 15 existing tables (akun, jabatan, unit, penilaian, etc.)
- Part 2: Identified 25+ hardcoded values in TutupKasir controller
- Part 3: New schema design with 6 new tables
- Part 4: Migration strategy (4 phases)
- Part 5: Implementation roadmap (18 days estimated)
- Part 6: Benefits/trade-offs analysis
- Part 7: Performance indexes
- Part 8: Data dictionary
- Part 9: Future enhancements

### 2. Database Migration File
**Location:** `app/Database/Migrations/2026-08-28-000000_CreateKPIConfigurationSchema.php`

Creates 7 new tables:
- `kpi_components` - Master KPI definitions (24 seed records)
- `kpi_weights` - Position-specific KPI weights (35+ seed records)
- `kpi_targets` - Unit/position targets with period tracking
- `kpi_evaluations` - Manual KPI score records
- `incentive_rules` - Configurable incentive calculation rules
- `salary_components` - Salary/payroll component catalog (7 seed records)
- `salary_structures` - Position-specific salary configuration (18+ seed records)

### 3. Database Seeder
**Location:** `app/Database/Seeds/KPIConfigurationSeeder.php`

Extracted from hardcoded values in `TutupKasir::slip_gaji()`:
- 24 KPI components (Omset, Customer, Kehadiran, etc.)
- 35+ KPI weight assignments across 8 positions
- Unit-specific omset targets
- 7 salary components (Gaji Pokok, Tunjangan Kinerja, etc.)
- 18+ position-specific salary structures
- 3 incentive rules (Kepala Toko, SPV, Pengiklan)

### 4. Model Classes (6 new models)

| Model | Purpose | Key Methods |
|-------|---------|-------------|
| `ModelKpiComponent` | KPI master data | `getActiveComponents()`, `getByCategory()`, `getByType()` |
| `ModelKpiWeight` | Position KPI weights | `getByPosition()`, `validateWeights()`, `getTotalWeightByPosition()` |
| `ModelKpiTarget` | KPI targets | `getTargetByKpiAndUnit()`, `getMonthlyTargets()` |
| `ModelKpiEvaluation` | Manual KPI scores | `getByEmployeeAndPeriod()`, `upsertEvaluation()` |
| `ModelIncentiveRule` | Incentive rules | `calculateIncentive()`, `getByPosition()` |
| `ModelSalaryStructure` | Salary config | `calculateComponentValue()`, `getTotalSalary()` |

---

## Key Findings from Analysis

### Existing Database Issues

| Issue | Location | Impact |
|-------|----------|--------|
| FK type mismatch | `penilaian.pegawai_idpegawai` (VARCHAR) vs `akun.ID_AKUN` (INT) | Query failures, data inconsistency |
| Missing FK constraints | All penilaian tables | Orphan records possible |
| Date stored as VARCHAR | `penilaian_kpi.tanggal_penilaian_kpi` | Date comparisons fail |
| Circular FK | `penilaian_kpi.penilaian_idpenilaian` | Design confusion |
| Hardcoded strings | `penilaian.aspek` = 'kehadiran', 'kebersihan', etc. | No controlled vocabulary |

### Hardcoded Values Identified in TutupKasir.php

**Salary Components (line 1369-1377):**
```php
gaji_pokok = 1,500,000 (fixed)
tunjangan_absen = skor_total2 / 100 * 250,000
tunjangan_kinerja = skor_total / 100 * (250k to 1.25M by position)
```

**Position-Specific Tunjangan Kinerja (lines 1353-1366):**
```
Admin (35):       250k or 850k (unit 1)
Teknisi (36):     250k
SPV (40):         1.25M
Kepala Toko (41): 850k
CS (42):          250k
Pengiklan (43):   1M
Multimedia (44):  250k
IT (45):          250k
```

**KPI Weights by Position (lines 1184-1338):**
```
Admin:           Omset 70%, Tutup Kasir 10%, Opname 10%, Absensi 10%
Teknisi:         Omset 70%, Omset Teknisi 15%, Customer 15%
Kepala Toko:     Omset 70%, Customer 10%, Tutup Kasir 10%, Opname 10%
SPV:             Omset Cabang 70%, Customer 10%, Operasional 10%, Divisi 10%
CS:              Omset 70%, Closing 10%, Upselling 10%, FollowUp 10%
Pengiklan:       Budgeting 15%, ROAS 15%, Omset 70%
Multimedia:      Omset 30%, Feed 15%, Video 20%, Feed Min 15%, Story 10%, Testimoni 10%
IT:              Omset 30%, Bug Minor 10%, Operasional 25%, Ecommerce 15%, Fitur 20%
```

**Incentive Rules (lines 1009, 1027, 1073, 1116):**
```
Kepala Toko:  3% omset / 4 when target achieved
SPV:          0.5% omset per unit when target achieved
Pengiklan:    1% omset when target achieved
Others:       3% omset / 4 when target achieved
```

---

## Implementation Steps

### Step 1: Run Migration (5 minutes)
```bash
cd /home/syro/Projects/ERP
php spark migrate
```

### Step 2: Seed Initial Data (5 minutes)
```bash
php spark db:seed KPIConfigurationSeeder
```

### Step 3: Verify Data Integrity
```sql
SELECT COUNT(*) FROM kpi_components;    -- Should be 24
SELECT COUNT(*) FROM kpi_weights;       -- Should be 35+
SELECT COUNT(*) FROM salary_components; -- Should be 7
SELECT COUNT(*) FROM salary_structures; -- Should be 18+
SELECT COUNT(*) FROM incentive_rules;   -- Should be 3

SELECT position_id, SUM(weight) as total 
FROM kpi_weights 
WHERE effective_to IS NULL 
GROUP BY position_id;  -- Should all equal 100
```

### Step 4: Update TutupKasir Controller

Replace switch-case (lines 1184-1338) with:
```php
$kpiWeightModel = new \App\Models\ModelKpiWeight();
$detail_kpi = [];
$weights = $kpiWeightModel->getByPosition($jabatan);

foreach ($weights as $w) {
    $nilai_kpi = $this->calculateKpiValue($w->code, $employee_id, $unit_id, $month);
    $detail_kpi[] = [
        'nama' => $w->name,
        'bobot' => $w->weight,
        'nilai' => $nilai_kpi
    ];
}
```

Replace hardcoded salary (lines 1353-1377) with:
```php
$salaryModel = new \App\Models\ModelSalaryStructure();
$structures = $salaryModel->getByPosition($jabatan);

$tunjangan_kinerja = $salaryModel->calculateComponentValue(
    $jabatan, 
    $salaryMap['TUNJANGAN_KINERJA'], 
    $skor_total
);
```

Replace incentive calculation (lines 1009, 1027, 1073, 1116) with:
```php
$incentiveModel = new \App\Models\ModelIncentiveRule();
$incentive = $incentiveModel->calculateIncentive(
    $jabatan,
    $componentMap['OMSET_TOKO'],
    $achievement_percentage,
    $omset
);
```

### Step 5: Create Configuration UI (Optional)

Create admin interface at `/admin/kpi-weights` to:
- View/edit KPI weights per position
- Add new KPI components
- Set effective dates for changes
- Validate weights sum to 100

---

## Benefits

| Before (Hardcoded) | After (Database-Driven) |
|-------------------|------------------------|
| Code deployment required for KPI changes | Admin UI can update instantly |
| No audit trail | Full history with effective_from/to |
| No unit-specific variations | Granular unit/position targets |
| Position IDs hardcoded | Flexible position references |
| Seasonal changes require code edits | Period-based targets supported |
| No A/B testing capability | Multiple active configurations |

---

## Risk Mitigation

| Risk | Mitigation |
|------|-----------|
| Data migration errors | Seeder validates all FKs, no deletion of old tables |
| Backward compatibility | Old tables remain, gradual refactoring |
| Performance impact | Indexes added, cached lookups recommended |
| User adoption | No UI changes initially, seamless migration |

---

## Testing Checklist

- [ ] Migration runs without errors
- [ ] Seeder populates all tables
- [ ] KPI weights sum to 100 per position
- [ ] Model queries return expected results
- [ ] Salary calculation matches old values
- [ ] Incentive calculation matches old logic
- [ ] No foreign key violations
- [ ] Old penilaian tables still functional

---

## Files Modified/Created

### New Files (7):
```
docs/architecture/kpi-database-schema.md
app/Database/Migrations/2026-08-28-000000_CreateKPIConfigurationSchema.php
app/Database/Seeds/KPIConfigurationSeeder.php
app/Models/ModelKpiComponent.php
app/Models/ModelKpiWeight.php
app/Models/ModelKpiTarget.php
app/Models/ModelKpiEvaluation.php
app/Models/ModelIncentiveRule.php
app/Models/ModelSalaryStructure.php
```

### Files to Update (Future):
```
app/Controllers/TutupKasir.php         - Refactor slip_gaji() method
app/Controllers/PenilaianKPI.php       - Use new models
app/Controllers/SummaryKPI.php         - Query new tables
app/Views/penilaian/*.php              - Admin UI for config
```

---

## Next Steps

1. **Review schema design** with team/PM
2. **Run migration** on development database
3. **Verify seed data** matches business requirements
4. **Refactor TutupKasir::slip_gaji()** to use new models
5. **Create admin UI** for KPI configuration management
6. **Update tests** to cover new models
7. **Deploy to production** with backward compatibility
8. **Deprecate old tables** after validation period

---

**Questions? Contact: Development Team**  
**Document Version:** 1.0  
**Last Updated:** 2026-08-28
