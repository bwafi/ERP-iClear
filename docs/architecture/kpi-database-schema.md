# KPI/Incentive Database Schema Design

**Date:** 2026-08-28  
**Status:** Comprehensive Analysis & Design  
**Scope:** Configurable KPI, incentive rules, and salary components system

---

## Executive Summary

Current system hardcodes KPI definitions, incentive calculations, and salary structures in `TutupKasir::slip_gaji()` controller. This analysis documents:

1. **Existing schema**: 15 tables related to KPI/payroll/penilaian
2. **Data integrity issues**: Missing FKs, inconsistent data types, denormalized views
3. **Hardcoded values**: Salary amounts, incentive percentages, KPI weights per position
4. **New schema**: 6 new master tables to enable configurability
5. **Migration strategy**: Seed data from controllers, maintain backward compatibility

---

## Part 1: Existing Database Analysis

### 1.1 Core Master Data Tables

#### **akun** (Employees)
```
PK: ID_AKUN (int)
FK: ID_JABATAN → jabatan.ID_JABATAN
FK: ID_UNIT → unit.idunit
```

**Current State:**
- Stores employee profile (name, email, KTP, phone, gender)
- Contains `tunjangan_penempatan` (implicit: used in salary calc but not visible in schema)
- No salary structure reference
- No KPI assignment tracking

**Issues:**
- Missing explicit FK constraints
- No salary_component assignments
- No effective_date for position changes
- `JENIS_PEGAWAI` hardcoded as enum (needs flexibility)

**Usage in KPI:**
- PK reference in `penilaian_kpi.pegawai_idpegawai`
- PK reference in `penilaian_detail.pegawai_idpegawai`
- Used in `TutupKasir::slip_gaji()` for salary calculation

---

#### **jabatan** (Positions)
```
PK: ID_JABATAN (int)
```

**Current State:**
- Minimal: Only ID, name, roles JSON
- No salary scale
- No KPI template assignment

**Hardcoded in TutupKasir (lines 1009, 1027, 1073, 1116, 1353-1366):**

Position ID → KPI Weight Mapping:
```php
35  (Admin)          → tunjangan_kinerja: 250k/850k (unit 1 vs others)
36  (Teknisi)        → tunjangan_kinerja: 250k
40  (SPV)            → tunjangan_kinerja: 1.25M, incentive: 0.5% omset
41  (Kepala Toko)    → tunjangan_kinerja: 850k, incentive: 0.75% omset / 4
42  (Customer Service) → tunjangan_kinerja: 250k
43  (Pengiklan)      → tunjangan_kinerja: 1M, incentive: 1% omset
44  (Multimedia)     → tunjangan_kinerja: 250k
45  (IT)             → tunjangan_kinerja: 250k
```

**Issues:**
- Position-specific salary hardcoded in controller
- No database reference for KPI templates
- KPI weight changes require code deployment

---

#### **unit** (Branches)
```
PK: idunit (int)
```

**Current State:**
- 14 fields: name, address, coordinates, radius, logo
- No KPI targets per unit
- Omset targets hardcoded in controller

**Hardcoded in TutupKasir (lines 983-988):**
```php
$batas_awal[$unit]      // Omset threshold 1
$batas_kedua[$unit]     // Omset threshold 2
$batas_ketiga[$unit]    // Omset threshold 3
$batas_keempat[$unit]   // Omset threshold 4
$target_omset[$unit]    // Target omset for incentive
```

**Issues:**
- 5 omset thresholds per unit undefined in DB
- Target omset undefined
- No period tracking (monthly/quarterly)

---

### 1.2 KPI Template & Configuration Tables

#### **template_kpi** (KPI Master Definitions)
```
PK: idtemplate_kpi (int)
FK: jabatan_idjabatan → jabatan.ID_JABATAN (implicit, no constraint)
```

**Fields:**
```
template_kpi (varchar 255)  - KPI name (e.g., "Omset Toko")
bobot (int)                 - Weight percentage
formula (varchar 255)       - Calculation formula (underutilized)
jabatan_idjabatan (int)     - Position ID
target (double)             - Target value
status (int)                - Active/inactive (0/1)
level (enum '1','2')        - Level 1 (KPI) vs Level 2 (Grading)
created_on, update_on       - Timestamps
```

**Current Usage (TutupKasir):**
- Level 1: KPI definitions per position
  - Admin (ID 35): Omset 70%, Tutup Kasir 10%, Stok Opname 10%, Absensi 10%
  - Teknisi (ID 36): Omset 70%, Omset Teknisi 15%, Customer 15%
  - SPV (ID 40): Omset Cabang 70%, Customer 10%, Operasional 10%, Divisi 10%
  - Kepala Toko (ID 41): Omset 70%, Customer 10%, Tutup Kasir 10%, Opname 10%
  - CS (ID 42): Omset 70%, Closing 10%, Upselling 10%, FollowUp 10%
  - Pengiklan (ID 43): Budgeting 15%, ROAS 15%, Omset 70%
  - Multimedia (ID 44): Omset 30%, Feed PL 15%, Video 20%, Feed Min 15%, Story 10%, Testimoni 10%
  - IT (ID 45): Omset 30%, Bug Minor 10%, Operasional 25%, Ecommerce 15%, Fitur 20%

- Level 2: Behavioral grading (per position)
  - Kehadiran 40%, Kebersihan 20%, Seragam 20%, Kepatuhan SOP 20%

**Issues:**
- Weights hardcoded in controller (switch-case on jabatan ID)
- No effective_from/effective_to for version control
- Formula column unused
- Target per position, not per unit/position combination

---

#### **template_penilaian** (Evaluation Template)
```
PK: idtemplate_penilaian (int)
FK: jabatan_idjabatan (implicit, no constraint)
FK: idtemplate_kpi (implicit, no constraint)
```

**Fields:**
```
aspek_penilaian (varchar)        - Aspect name (e.g., "Kehadiran")
keterangan_penilaian (varchar)   - Description
jabatan_idjabatan (int)          - Position
idtemplate_kpi (int)             - KPI template ref
target (int)                     - Target score/count
bobot (int)                      - Weight %
```

**Current Usage:**
- Defines evaluation criteria per position
- Linked to penilaian_detail for actual evaluations

**Issues:**
- No datetime tracking for periods
- Not normalized (could collapse template_kpi + template_penilaian)
- No active/inactive flag

---

### 1.3 Evaluation/Assessment Tables

#### **penilaian** (Manual Evaluations - Behavioral/Checklist)
```
PK: idpenilaian (int)
FK: pegawai_idpegawai (varchar 255) → akun.ID_AKUN (type mismatch!)
FK: input_by (varchar 255) → akun.ID_AKUN (type mismatch!)
```

**Fields:**
```
aspek (varchar)          - Evaluation aspect
keterangan (varchar)     - Description
skor (decimal)           - Score value
pegawai_idpegawai (varchar) - Employee ID
input_by (varchar)       - Evaluator ID
tanggal_penilaian (date) - Evaluation date
created_on, updated_on   - Timestamps
```

**Current Usage:**
- Stores behavioral evaluations (kehadiran, kebersihan, seragam, kepatuhan sop)
- Used in `ModelSummaryKPI::getDetailChecklist()` for monthly aggregation

**Issues:**
- FK type mismatch: pegawai_idpegawai is VARCHAR, but akun.ID_AKUN is INT
- aspek is string (hardcoded: "kehadiran", "kebersihan", "seragam", "kepatuhan sop")
- No template_penilaian reference
- No period tracking (inferred from tanggal_penilaian)

---

#### **penilaian_kpi** (KPI Evaluation Records)
```
PK: idpenilaian_kpi (int)
FK: pegawai_idpegawai (varchar) → akun.ID_AKUN (type mismatch!)
FK: unit_idunit (int) → unit.idunit
FK: template_kpi_idtemplate_kpi (int) → template_kpi.idtemplate_kpi
FK: penilaian_idpenilaian (int) → penilaian.idpenilaian (should not exist, circular?)
```

**Fields:**
```
kpi_utama (varchar)              - KPI name (redundant?)
bobot (int)                      - Weight %
target (double)                  - Target value
realisasi (double)               - Actual achievement
score (double)                   - Calculated score (0-100)
level (enum '1','2')             - KPI level or Grading
pegawai_idpegawai (varchar)      - Employee ID
unit_idunit (int)                - Branch unit
template_kpi_idtemplate_kpi (int)- KPI definition ref
tanggal_penilaian_kpi (varchar)  - Date (stored as string!)
penilaian_idpenilaian (int)      - Link to penilaian? (unclear use)
created_on, updated_on           - Timestamps
```

**Current Usage:**
- Stores calculated KPI scores for employees
- level=1: KPI evaluation, level=2: Grading evaluation
- Used in salary calculation (TutupKasir::slip_gaji)

**Issues:**
- Multiple FK issues and type mismatches
- kpi_utama redundant (should use template_kpi.template_kpi)
- tanggal_penilaian_kpi is VARCHAR (should be DATE/DATETIME)
- Ambiguous penilaian_idpenilaian relationship
- No active/inactive period tracking

---

#### **penilaian_detail** (Checklist Item Scores)
```
PK: iddetail_penilaian (int)
FK: template_penilaian_idtemplate_penilaian (int) → template_penilaian.idtemplate_penilaian
FK: pegawai_idpegawai (int) → akun.ID_AKUN (type mismatch with penilaian!)
FK: penilaian_idpenilaian (int) → penilaian.idpenilaian
```

**Fields:**
```
template_penilaian_idtemplate_penilaian (int)
pegawai_idpegawai (int)     - Employee ID
bobot (double)              - Weight
target (int)                - Target
skor (int)                  - Score
tanggal_penilaian (datetime)
penilaian_idpenilaian (int) - Link to parent penilaian
created_on, updated_on
```

**Issues:**
- Type mismatch with penilaian.pegawai_idpegawai (varchar vs int)
- Redundant with penilaian table
- Could be denormalized into penilaian_detail only

---

### 1.4 Sales & Revenue Tables (KPI Data Source)

#### **penjualan** (Sales Invoices)
```
PK: idpenjualan (int)
FK: unit_idunit (int) → unit.idunit
FK: input_by (int) → akun.ID_AKUN
FK: sales_by (int) → akun.ID_AKUN
```

**Fields:** invoice code, dates, amounts, customer ID, unit, sales person

**Usage in KPI:**
- Source for omset (revenue) KPI calculation
- Filtered by unit, sales_by (sales person), and date range

**Issues:**
- No direct KPI component link
- sales_by implies individual sales targets (not currently used in KPI)

---

#### **detail_penjualan** (Sales Line Items)
```
PK: iddetail_penjualan (int)
FK: penjualan_idpenjualan (int) → penjualan.idpenjualan
FK: barang_idbarang (int) → barang.idbarang
FK: unit_idunit (int) → unit.idunit
```

**Fields:** quantity, price, subtotal, HPP, discount, bundle indicator

**Usage in KPI:**
- Omset rolled up to penjualan.total_penjualan
- Category-based segmentation (kategori 1 = handphones, 2 = accessories)

---

#### **kas_keluar** (Expenses/Bon/Lembur)
```
PK: idkas_keluar (int)
FK: kategori_idkategori (int) → kategori_kas.idkategori_kas
FK: idunit (int) → unit.idunit
```

**Fields:** date, description, amount, recipient, category, bank, accounting code

**Current Hardcoded Categories (TutupKasir:1388):**
```php
[1,2,3,4,5,11,18]  - Operating expenses (non-salary)
Bon/Lembur: kategori 10 + like('deskripsi', 'bon') OR like('deskripsi', 'lembur')
```

**Issues:**
- Categories hardcoded in queries
- Bon/Lembur identified by string pattern, not category
- No employee reference (penerima is free text)

---

### 1.5 Summary/Denormalized Views

#### **summary_grading_kpi** (View/Table)
```
Fields: ID_AKUN, ID_JABATAN, ID_UNIT, NAMA_UNIT, NAMA_JABATAN, NAMA_AKUN,
        level (1 or 2), tanggal (YYYY-MM), realisasi, target, score
```

**Purpose:** Pre-aggregated KPI monthly summary for reporting

**Issues:**
- Denormalized, requires refresh on penilaian_kpi changes
- No metadata on calculation timestamp

---

#### **summary_checklist** (View/Table)
```
Fields: ID_AKUN, ID_JABATAN, ID_UNIT, NAMA_AKUN, tanggal (YYYY-MM),
        bobot, target, skor
```

**Purpose:** Aggregated behavioral evaluation summary

---

## Part 2: Identified Issues & Design Problems

### 2.1 Data Integrity Issues
| Issue | Impact | Severity |
|-------|--------|----------|
| FK type mismatches (varchar vs int) | Query joins fail, data inconsistency | HIGH |
| Missing FK constraints in DB | Orphan records, no referential integrity | HIGH |
| tanggal_penilaian_kpi stored as VARCHAR | Date comparisons fail | MEDIUM |
| Circular FK (penilaian_kpi → penilaian) | Design confusion, unused relation | MEDIUM |
| Aspek/KPI names hardcoded as strings | No controlled vocabulary | MEDIUM |

### 2.2 Hardcoded Configuration (Cannot Change Without Code Deploy)

**Salary Components:**
```php
gaji_pokok          = 1,500,000         // Line 1369
tunjangan_absen     = skor_total2 / 100 * 250,000
tunjangan_kinerja   = skor_total / 100 * (250k-1.25M by position)
akun->tunjangan_penempatan = ???        // Undefined in schema
insentif            = (3-5)% omset / 4 (by position & omset tier)
```

**Position-Specific KPI Weights (Lines 1184-1338):**
- 8 positions × (5-8 KPI components) = 56 hardcoded weight assignments
- Switch-case on jabatan ID (35, 36, 40, 41, 42, 43, 44, 45)

**Omset Thresholds (Unit-Specific):**
```php
$batas_awal[$unit]      // 4 thresholds per unit (12 units × 4 = 48 values)
$batas_kedua[$unit]
$batas_ketiga[$unit]
$batas_keempat[$unit]
$target_omset[$unit]
```

**Incentive Rules:**
```php
if (jabatan == 41)
    if (omset >= target) incentif = (3 / 100) * omset / 4
if (jabatan == 40)
    if (omset >= target) incentif += (5 / 1000) * omset
if (jabatan == 43)
    if (omset >= target) incentif += (1 / 100) * omset
```

### 2.3 Missing Configuration Flexibility
- No seasonal/monthly KPI target changes
- No A/B testing different weight configurations
- No audit trail for KPI changes
- No role-based access to KPI configuration
- No bulk KPI import/export

---

## Part 3: Proposed New Schema

### 3.1 New Tables for Configurable KPI System

#### **kpi_components** (Master KPI Catalog)
Master definitions of all possible KPI metrics.

```sql
CREATE TABLE kpi_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,          -- e.g., 'OMSET', 'CUSTOMER', 'CLOSING'
    name VARCHAR(255) NOT NULL,                -- e.g., 'Omset Toko'
    description TEXT,
    type ENUM('automatic', 'manual') NOT NULL, -- Data source
    category VARCHAR(100),                      -- e.g., 'sales', 'behavior', 'operational'
    unit_of_measure VARCHAR(50),               -- e.g., 'IDR', 'count', 'percent'
    calculation_method VARCHAR(255),            -- e.g., 'SUM(penjualan.total)', 'COUNT(tugas)'
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_code (code),
    INDEX idx_type (type),
    INDEX idx_category (category)
);

-- Seed data examples:
INSERT INTO kpi_components VALUES
(1, 'OMSET_TOKO', 'Omset Toko', 'Revenue from store sales', 'automatic', 'sales', 'IDR', 'SUM(detail_penjualan.sub_total)', 1, NOW(), NOW()),
(2, 'CUSTOMER_COUNT', 'Total Pelanggan', 'Count of unique customers', 'automatic', 'sales', 'count', 'COUNT(DISTINCT penjualan.id_pelanggan)', 1, NOW(), NOW()),
(3, 'KEHADIRAN', 'Kehadiran', 'Attendance score', 'manual', 'behavior', 'percent', NULL, 1, NOW(), NOW()),
(4, 'KEBERSIHAN', 'Kebersihan', 'Cleanliness score', 'manual', 'behavior', 'percent', NULL, 1, NOW(), NOW()),
...
```

**Rationale:**
- Centralized KPI definition
- Enables dynamic KPI discovery in UI
- Supports both automatic (calc from sales) and manual (input by manager) KPIs
- Versioning ready for future enhancements

---

#### **kpi_weights** (Configurable Weights per Position)
Defines which KPIs apply to which position and their weight percentage.

```sql
CREATE TABLE kpi_weights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kpi_component_id INT NOT NULL,
    position_id INT NOT NULL,                  -- jabatan.ID_JABATAN
    weight DECIMAL(5,2) NOT NULL,              -- 0.00 to 100.00
    effective_from DATE NOT NULL,
    effective_to DATE,
    created_by INT,                            -- akun.ID_AKUN (who set this)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    FOREIGN KEY (kpi_component_id) REFERENCES kpi_components(id) ON DELETE RESTRICT,
    FOREIGN KEY (position_id) REFERENCES jabatan(ID_JABATAN) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES akun(ID_AKUN) ON DELETE SET NULL,
    
    UNIQUE KEY uq_kpi_pos_period (kpi_component_id, position_id, effective_from),
    INDEX idx_position_period (position_id, effective_from, effective_to),
    INDEX idx_active (effective_to)
);

-- Seed from TutupKasir lines 1184-1338:
-- Admin (ID 35):
INSERT INTO kpi_weights (kpi_component_id, position_id, weight, effective_from, effective_to)
SELECT 1, 35, 70, '2024-01-01', NULL UNION  -- Omset Toko 70%
SELECT 5, 35, 10, '2024-01-01', NULL UNION  -- Tutup Kasir 10%
SELECT 6, 35, 10, '2024-01-01', NULL UNION  -- Stok Opname 10%
SELECT 7, 35, 10, '2024-01-01', NULL;       -- Absensi 10%
```

**Rationale:**
- Decouples KPI definitions from business logic
- Supports historical auditing (effective_from/to)
- Allows concurrent A/B testing
- Weights always sum to 100 per position (enforced in application)

---

#### **kpi_targets** (Configurable Targets)
Defines target values for KPI components by unit/position/period.

```sql
CREATE TABLE kpi_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kpi_component_id INT NOT NULL,
    unit_id INT,                               -- NULL = all units, else specific unit
    position_id INT,                           -- NULL = all positions, else specific
    target_value DECIMAL(15,2) NOT NULL,
    period_type ENUM('monthly', 'quarterly', 'annual') DEFAULT 'monthly',
    period_month INT,                          -- 1-12 for seasonal, NULL = all months
    effective_from DATE NOT NULL,
    effective_to DATE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    FOREIGN KEY (kpi_component_id) REFERENCES kpi_components(id) ON DELETE RESTRICT,
    FOREIGN KEY (unit_id) REFERENCES unit(idunit) ON DELETE CASCADE,
    FOREIGN KEY (position_id) REFERENCES jabatan(ID_JABATAN) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES akun(ID_AKUN) ON DELETE SET NULL,
    
    INDEX idx_kpi_unit_period (kpi_component_id, unit_id, effective_from),
    INDEX idx_active (effective_to)
);

-- Seed from TutupKasir lines 983-988:
-- Unit omset targets and thresholds
INSERT INTO kpi_targets VALUES
(NULL, 1, 1, NULL, 10000000, 'monthly', NULL, '2024-01-01', NULL, NULL, NOW(), NOW()),  -- Unit 1 omset target
(NULL, 1, 2, NULL, 8000000, 'monthly', NULL, '2024-01-01', NULL, NULL, NOW(), NOW()),   -- Unit 2 omset target
...
```

**Rationale:**
- Replaces hardcoded $batas_awal, $batas_kedua arrays
- Supports per-unit, per-position, per-month variations
- Enables seasonal adjustments (e.g., higher Q4 targets)
- Historical tracking for audit trails

---

#### **kpi_evaluations** (Manual KPI Input)
Records for manual (non-calculated) KPI evaluations.

```sql
CREATE TABLE kpi_evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    kpi_component_id INT NOT NULL,
    evaluator_id INT NOT NULL,
    score DECIMAL(5,2) NOT NULL,              -- 1-100 or specific range
    notes TEXT,
    period_year INT NOT NULL,
    period_month INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    FOREIGN KEY (employee_id) REFERENCES akun(ID_AKUN) ON DELETE RESTRICT,
    FOREIGN KEY (kpi_component_id) REFERENCES kpi_components(id) ON DELETE RESTRICT,
    FOREIGN KEY (evaluator_id) REFERENCES akun(ID_AKUN) ON DELETE RESTRICT,
    
    UNIQUE KEY uq_emp_kpi_period (employee_id, kpi_component_id, period_year, period_month),
    INDEX idx_period (period_year, period_month),
    INDEX idx_evaluator (evaluator_id)
);

-- Replaces/consolidates: penilaian, penilaian_detail (manual aspects only)
```

**Rationale:**
- Standardized structure for manual KPI scores
- Clear auditing (who evaluated, when)
- Period-based grouping for monthly KPI cycles
- Replaces string-based aspek names with kpi_component_id references

---

#### **incentive_rules** (Configurable Incentive Calculation)
Business rules for bonus/incentive payments based on KPI achievement.

```sql
CREATE TABLE incentive_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position_id INT NOT NULL,
    kpi_component_id INT NOT NULL,             -- e.g., OMSET for omset-based incentive
    incentive_name VARCHAR(255),               -- e.g., 'Omset Incentive'
    calculation_type ENUM('percentage', 'tier', 'flat') DEFAULT 'percentage',
    base_value DECIMAL(15,2),                  -- e.g., 3% or 250,000 (flat)
    minimum_achievement DECIMAL(5,2) DEFAULT 100,  -- e.g., 100% = must hit target
    division_method VARCHAR(100),              -- e.g., 'divide_by_4', 'per_unit'
    effective_from DATE NOT NULL,
    effective_to DATE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    FOREIGN KEY (position_id) REFERENCES jabatan(ID_JABATAN) ON DELETE RESTRICT,
    FOREIGN KEY (kpi_component_id) REFERENCES kpi_components(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES akun(ID_AKUN) ON DELETE SET NULL,
    
    INDEX idx_position_kpi (position_id, kpi_component_id),
    INDEX idx_active (effective_to)
);

-- Seed from TutupKasir lines 1009, 1027, 1073, 1116:
INSERT INTO incentive_rules VALUES
-- Kepala Toko (41): 3% omset / 4 when omset >= target
(NULL, 41, 1, 'Kepala Toko Omset Bonus', 'percentage', 3.00, 100, 'divide_by_4', '2024-01-01', NULL, NULL, NOW(), NOW()),
-- SPV (40): 0.5% omset when omset >= target per unit
(NULL, 40, 1, 'SPV Omset Bonus', 'percentage', 0.50, 100, 'per_unit', '2024-01-01', NULL, NULL, NOW(), NOW()),
-- Pengiklan (43): 1% omset when omset >= target
(NULL, 43, 1, 'Pengiklan Omset Bonus', 'percentage', 1.00, 100, 'per_unit', '2024-01-01', NULL, NULL, NOW(), NOW()),
...
```

**Rationale:**
- Replaces hardcoded if-statements for incentive calculation
- Supports multiple incentive tiers (future)
- Audit trail for incentive policy changes
- Easy to activate/deactivate via effective_from/to

---

#### **salary_components** (Configurable Salary Structure)
Master list of all salary/payroll components.

```sql
CREATE TABLE salary_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,          -- e.g., 'GAJI_POKOK', 'TUNJANGAN_KINERJA'
    name VARCHAR(255) NOT NULL,
    type ENUM('base', 'allowance', 'deduction', 'incentive') NOT NULL,
    description TEXT,
    default_value DECIMAL(15,2),               -- Fallback value
    is_active BOOLEAN DEFAULT 1,
    is_configurable BOOLEAN DEFAULT 1,         -- Can be overridden per employee/position
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    INDEX idx_type (type),
    INDEX idx_code (code)
);

-- Seed from TutupKasir:
INSERT INTO salary_components VALUES
(1, 'GAJI_POKOK', 'Gaji Pokok', 'base', 'Base salary', 1500000, 1, 1, NOW(), NOW()),
(2, 'TUNJANGAN_KINERJA', 'Tunjangan Kinerja', 'allowance', 'Performance allowance', 250000, 1, 1, NOW(), NOW()),
(3, 'TUNJANGAN_ABSEN', 'Tunjangan Absensi', 'allowance', 'Attendance allowance', 250000, 1, 1, NOW(), NOW()),
(4, 'TUNJANGAN_PENEMPATAN', 'Tunjangan Penempatan', 'allowance', 'Placement allowance', 0, 1, 1, NOW(), NOW()),
(5, 'INSENTIF', 'Insentif', 'incentive', 'Performance incentive', 0, 1, 0, NOW(), NOW()),
(6, 'BON', 'Bon/Advance', 'deduction', 'Employee advance/bon', 0, 1, 1, NOW(), NOW()),
(7, 'LEMBUR', 'Overtime', 'incentive', 'Overtime payment', 0, 1, 0, NOW(), NOW()),
...
```

**Rationale:**
- Defines all possible payroll components
- Replaces hardcoded salary structure (line 1369-1377)
- Enables flexible payroll configuration per company
- Supports audit trails for component changes

---

#### **salary_structures** (Employee-Specific Salary Configuration)
Links salary components to positions with specific multipliers/values.

```sql
CREATE TABLE salary_structures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position_id INT NOT NULL,
    salary_component_id INT NOT NULL,
    base_value DECIMAL(15,2),                  -- Fixed amount or multiplier base
    calculation_type ENUM('fixed', 'percent_of_base', 'percent_of_kpi') DEFAULT 'fixed',
    multiplier DECIMAL(5,2),                   -- For percent calculations
    effective_from DATE NOT NULL,
    effective_to DATE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    FOREIGN KEY (position_id) REFERENCES jabatan(ID_JABATAN) ON DELETE RESTRICT,
    FOREIGN KEY (salary_component_id) REFERENCES salary_components(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES akun(ID_AKUN) ON DELETE SET NULL,
    
    UNIQUE KEY uq_pos_comp_period (position_id, salary_component_id, effective_from),
    INDEX idx_position_period (position_id, effective_from)
);

-- Seed from TutupKasir lines 1353-1366:
INSERT INTO salary_structures VALUES
-- Admin (35): tunjangan_kinerja = 250k/850k by unit
(NULL, 35, 2, 250000, 'percent_of_kpi', 1.00, '2024-01-01', NULL, NULL, NOW(), NOW()), -- Unit 1: 850k
(NULL, 35, 2, 250000, 'percent_of_kpi', 1.00, '2024-01-01', NULL, NULL, NOW(), NOW()), -- Others: 250k
-- Kepala Toko (41): tunjangan_kinerja = 850k
(NULL, 41, 2, 850000, 'percent_of_kpi', 1.00, '2024-01-01', NULL, NULL, NOW(), NOW()),
-- SPV (40): tunjangan_kinerja = 1.25M
(NULL, 40, 2, 1250000, 'percent_of_kpi', 1.00, '2024-01-01', NULL, NULL, NOW(), NOW()),
...
```

**Rationale:**
- Removes hardcoded switch-case on jabatan ID
- Supports unit-specific variations
- Historical auditing of salary policy
- Enables configuration UI for HR team

---

### 3.2 New Database Relationships Diagram

```
kpi_components
├── kpi_weights (many: position_id, effective_from/to)
├── kpi_targets (many: unit_id, effective_from/to)
├── kpi_evaluations (many: employee_id, period)
└── incentive_rules (many: position_id)

salary_components
└── salary_structures (many: position_id, effective_from/to)

jabatan
├── kpi_weights (many: position_id)
├── incentive_rules (many: position_id)
└── salary_structures (many: position_id)

unit
└── kpi_targets (many: unit_id)

akun
├── kpi_evaluations (many: employee_id)
└── salary_structures (many: created_by)
```

---

## Part 4: Migration Strategy

### 4.1 Phase 1: Create New Tables (Backward Compatible)

**Step 1.1:** Create all 6 new tables in schema above with no deletions
```bash
Run: app/Database/Migrations/YYYY-MM-DD-000000_CreateKPITables.php
```

**Step 1.2:** Seed master data from existing code/tables
```php
// Pseudo-code seed logic:
kpi_components: KEHADIRAN, KEBERSIHAN, OMSET, CUSTOMER, CLOSING, etc. (hardcoded in TutupKasir)
kpi_weights: FROM template_kpi, pivot on jabatan_id (switch-case 1184-1338)
kpi_targets: FROM unit table + hardcoded thresholds (983-988)
salary_components: FROM TutupKasir hardcoded values (1369-1377)
salary_structures: pivot on jabatan_id (1353-1366)
```

**Step 1.3:** Verify data integrity
```sql
SELECT * FROM kpi_weights WHERE position_id NOT IN (SELECT ID_JABATAN FROM jabatan);
SELECT * FROM kpi_targets WHERE unit_id NOT IN (SELECT idunit FROM unit);
```

### 4.2 Phase 2: Update Controllers to Read from New Tables

**Refactor TutupKasir::slip_gaji() to:**
1. Query `kpi_weights` instead of switch-case
2. Query `kpi_targets` instead of $batas_* arrays
3. Query `incentive_rules` instead of if-statements
4. Query `salary_structures` instead of hardcoded multipliers

**No data loss:** Old tables (template_kpi, penilaian, etc.) remain untouched

### 4.3 Phase 3: Consolidate/Migrate Evaluation Data

**Option A (Recommended):** Keep both systems
- Old: penilaian, penilaian_detail, penilaian_kpi (for backward compatibility)
- New: kpi_evaluations (for new workflows)
- Add sync view to prevent divergence

**Option B:** Full migration
- Migrate penilaian.* → kpi_evaluations with script
- Update all queries to use kpi_evaluations
- Keep old tables as archive (renamed to `_archive`)

### 4.4 Phase 4: Update Views & Reports

Update `summary_grading_kpi`, `summary_checklist` to calculate from:
- kpi_evaluations (manual scores)
- Calculated KPI values from penjualan (automatic)

---

## Part 5: Implementation Roadmap

| Phase | Task | Owner | Duration | Risk |
|-------|------|-------|----------|------|
| 1 | Create 6 new tables | DB Admin | 1 day | Low |
| 1 | Seed master data | Data Admin | 2 days | Low |
| 2 | Refactor TutupKasir::slip_gaji | Backend Dev | 3 days | Medium |
| 2 | Create config management UI | Frontend Dev | 5 days | Medium |
| 2 | Test backward compatibility | QA | 2 days | Medium |
| 3 | Migrate evaluation history | Data Admin | 2 days | Medium |
| 3 | Create sync validator | Backend Dev | 1 day | Low |
| 4 | Update reports/dashboards | Frontend Dev | 2 days | Low |
| **Total** | | | **18 days** | **Medium** |

---

## Part 6: Benefits & Trade-offs

### Benefits
✅ HR can change KPI weights without developer  
✅ Supports A/B testing of compensation models  
✅ Audit trail for compliance/dispute resolution  
✅ Unit-specific and seasonal targets  
✅ Scales to multiple companies/rules  
✅ Data-driven decision making  

### Trade-offs
⚠️ More database tables (6 new tables)  
⚠️ More complex queries (need date range logic)  
⚠️ Requires data governance (who can modify KPI?)  
⚠️ Two-phase rollout (maintain backward compat)  

---

## Part 7: SQL Indexes for Performance

Critical indexes to add for query performance:

```sql
-- kpi_weights: Lookup current weights for position
ALTER TABLE kpi_weights ADD INDEX idx_effective_period 
    (position_id, effective_from, effective_to, kpi_component_id);

-- kpi_evaluations: Monthly aggregation
ALTER TABLE kpi_evaluations ADD INDEX idx_period_emp 
    (period_year, period_month, employee_id, kpi_component_id);

-- kpi_targets: Lookup current targets
ALTER TABLE kpi_targets ADD INDEX idx_lookup 
    (unit_id, kpi_component_id, effective_from, effective_to);

-- salary_structures: Fetch salary config for period
ALTER TABLE salary_structures ADD INDEX idx_lookup 
    (position_id, effective_from, effective_to, salary_component_id);

-- incentive_rules: Fast incentive lookup
ALTER TABLE incentive_rules ADD INDEX idx_lookup 
    (position_id, kpi_component_id, effective_from, effective_to);
```

---

## Part 8: Data Dictionary Quick Reference

### Existing Tables Summary

| Table | PK | Key Columns | Status | Action |
|-------|----|----|--------|--------|
| akun | ID_AKUN | ID_JABATAN, ID_UNIT | ✅ Keep | Add tunjangan_penempatan tracking |
| jabatan | ID_JABATAN | NAMA_JABATAN | ✅ Keep | No changes |
| unit | idunit | NAMA_UNIT | ✅ Keep | No changes |
| penjualan | idpenjualan | unit_idunit, tanggal | ✅ Keep | Source for auto KPIs |
| detail_penjualan | iddetail_penjualan | penjualan_idpenjualan | ✅ Keep | Source for omset calc |
| template_kpi | idtemplate_kpi | jabatan, level | ⚠️ Deprecate | Replace w/ kpi_components |
| template_penilaian | idtemplate_penilaian | jabatan | ⚠️ Deprecate | Replace w/ kpi_evaluations |
| penilaian | idpenilaian | pegawai_idpegawai | ⚠️ Keep | Migrate to kpi_evaluations (Phase 3) |
| penilaian_detail | iddetail_penilaian | pegawai_idpegawai | ⚠️ Keep | Migrate to kpi_evaluations (Phase 3) |
| penilaian_kpi | idpenilaian_kpi | pegawai_idpegawai | ⚠️ Keep | Migrate to kpi_evaluations (Phase 3) |
| kas_keluar | idkas_keluar | unit, tanggal | ✅ Keep | Source for expense KPIs |
| summary_* | N/A | Aggregated | ⚠️ Refresh | Recalculate from new tables |

---

## Part 9: Future Enhancements

1. **Tiered Incentives:** Multiple thresholds (0-70%, 71-90%, 91-110%, 111%+)
2. **Weighted KPI Scoring:** Dynamic calculation with configurable formulas
3. **Forecasting:** Project KPI achievement based on YTD performance
4. **Comparative Analysis:** Compare positions/units on same KPI basis
5. **API:** REST endpoints for mobile KPI entry
6. **Real-time Dashboard:** Live KPI tracking with notifications
7. **Scenario Planning:** Test "what-if" changes before deployment

---

## Appendix A: SQL Migration Script Template

```php
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateKPIConfigurationSchema extends Migration
{
    public function up()
    {
        // 1. kpi_components
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'auto_increment' => true],
            'code'                 => ['type' => 'VARCHAR', 'constraint' => 50, 'unique' => true],
            'name'                 => ['type' => 'VARCHAR', 'constraint' => 255],
            'description'          => ['type' => 'TEXT', 'null' => true],
            'type'                 => ['type' => 'ENUM', 'constraint' => ['automatic', 'manual']],
            'category'             => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'unit_of_measure'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'calculation_method'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active'            => ['type' => 'BOOLEAN', 'default' => 1],
            'created_at'           => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
            'updated_at'           => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('code');
        $this->forge->addKey('type');
        $this->forge->createTable('kpi_components');

        // 2-6. Create remaining tables...
        // [Full implementations follow same pattern]
    }

    public function down()
    {
        $this->forge->dropTable('kpi_components');
        $this->forge->dropTable('kpi_weights');
        $this->forge->dropTable('kpi_targets');
        $this->forge->dropTable('kpi_evaluations');
        $this->forge->dropTable('incentive_rules');
        $this->forge->dropTable('salary_components');
        $this->forge->dropTable('salary_structures');
    }
}
```

---

**Document Version:** 1.0  
**Last Updated:** 2026-08-28  
**Review Status:** Ready for Architecture Review
