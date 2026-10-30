# KPI Business Rules

**Tanggal:** 28 Agustus 2026
**Diperbarui:** 29 Oktober 2026 — KPI Kepala Divisi (43) disinkronkan dengan implementasi aktif.
**Status:** Status per rule ditandai (CONFIRMED / DARI SISTEM LAMA / UNKNOWN)

---

## 1. CONFIRMED (diklarifikasi owner)

| Rule | Nilai | Divisi | Sumber |
|---|---|---|---|
| Insentif Kepala Toko | **1% × omset toko PER ANGGOTA** (tidak dibagi jumlah anggota) | `division_method = 'per_member'` di `incentive_rules`; anggota dari `incentive_members` (group+unit+periode) | Owner |
| Insentif Digital Division | pool = 1% × omset toko | dinamis dari `incentive_members` | Owner |
| Basic compute (DIGITAL_DIVISION) | individual = pool / member_count | — | Owner |
| Insentif HO (Oktober 2026+) | pool = pool cabang + pool global. Pool cabang = rate × Σ **gross profit** (`SUM(sub_total - hpp_penjualan)`) cabang yang capai target `OMSET_TOKO`, rate **1%** bila < 3 cabang capai dan **1,5%** bila ≥ 3 (satu rate untuk semua cabang yang capai, bukan 1% + 1,5%). Pool global = **1,5% × omzet global** bila Σ omzet global ≥ Σ target `OMSET_TOKO` unit non-trial (target toko, bukan target HO). individual = pool / jumlah karyawan HO aktif `TERIMA_GAJI=1` | unit 50 | Owner |
| Insentif Probolinggo (Oktober 2026+) | pool = 3% × gross profit unit 1 bila target `OMSET_TOKO` capai; individual = pool / jumlah staff toko aktif `TERIMA_GAJI=1` (jabatan 35/36/41/42) | unit 1 | Owner |

Aturan Oktober 2026 menggantikan seluruh insentif lama untuk unit 50 (termasuk SPV & Digital Division) dan insentif Kepala Toko unit 1. Periode sebelum Oktober 2026 tetap memakai `incentive_members`. Unit cabang lain tidak berubah.

- **Basis insentif HO = gross profit** (`SUM(sub_total - hpp_penjualan)` − refund garansi), sama dengan sumber "Rincian Omset Cabang" di `/penilaian_kinerja`. Sumber: `OmsetTokoCalculator`. Target cabang tetap `OMSET_TOKO` (position_id NULL) dan tidak diubah.
- **Unit trial (`unit.is_trial = 1`) tidak ikut insentif HO**: pool cabang dan pool global sama-sama memakai daftar `omsetBranchUnits()`. — `KpiCalculationService::hoPoolIncentive`; `tests/unit/IncentiveHoOmzetTest.php`

Saat ini:
- KEPALA_TOKO → Kepala Toko + Teknisi + Admin; **setiap anggota dapat 1% × omset unit**
- DIGITAL_DIVISION → 4 member (Kepala Divisi/43 + IT + Multimedia + CS); 1% pool dibagi rata

> Syarat `minimum_achievement` (harus capai target omset) tetap berlaku untuk KEPALA_TOKO.

---

## 1a. KPI Aktif — Kepala Divisi / Digital Marketing (43)

Berlaku untuk jabatan **43 "Kepala Divisi"** (unit HO 50). Engine: `KpiCalculationService` → `DigitalMarketingKpiService`. Bobot dibaca dari `kpi_weights` posisi 43, group `kpi`; total 100.

| Komponen | Bobot | Rumus ringkas | Sumber data |
|---|---:|---|---|
| `OMZET_GLOBAL` | 50 | `min(actual / Σ target OMSET_CABANG posisi 40 unit non-trial × 100, 100)`; target ≤ 0 → null | Penjualan unit non-trial (gross profit) |
| `LEADS_QUALITY` | 15 | rata-rata `min(leads / 3.000 × 100, 100)` dan `min(qualified / leads × 100, 100)`; leads 0 → 0 | `marketing_ads_performance` |
| `CONVERSION` | 10 | `min((closing CS / lead CS × 100) / 30% × 100, 100)`; lead 0 → null | Rekap harian CS + prospek closing |
| `CPL` | 10 | rata-rata `min(250.000 / CPL Datang × 100, 100)` dan `min(250.000 / CPL Closing × 100, 100)`; penyebut 0 → null | Budget ads, datang & closing CS |
| `CAMPAIGN_PERFORMANCE` | 5 | `ads dengan campaign valid / total ads × 100`; total ads 0 → null | Ads + campaign |
| `REPORTING` | 5 | `campaign done / total campaign × 100`; total campaign 0 → null | `marketing_campaigns` |
| `BUDGETING` | 5 | belanja iklan ≤ **Rp15.000.000/bulan** → 100; **di atas batas → 0**; tanpa data ads → null | `marketing_ads_performance` (spend + PPN) |

- **Target efektif saat ini:** `LEADS_QUALITY` 3.000 leads/bulan, `CONVERSION` 30%, `CPL` ≤ Rp250.000, `BUDGETING` ≤ Rp15.000.000. `CAMPAIGN_PERFORMANCE` dan `REPORTING` dinilai terhadap 100%.
- **Berlaku sejak 2026-10-01.** `BUDGETING` menggantikan `IMPROVEMENT` (5) yang bobotnya ditutup per 2026-09-30; periode sebelum Oktober 2026 tetap memakai `IMPROVEMENT` agar skor historis tidak berubah. Multimedia (44) tetap memakai `IMPROVEMENT`.
- Target komponen dibaca dari `kpi_targets` dengan `position_id = 43`; service memakai fallback hardcode hanya bila baris target tidak ada.
- **Komponen absensi** (group `absen`, bukan 7 KPI): Kehadiran 80, Kebersihan 5, Seragam 10, Kepatuhan SOP 5. Kehadiran dinilai Finance; Kebersihan/Seragam/SOP dinilai Manager. Skor ini masuk `skor_total2` → tunjangan absen maksimum Rp250.000.
- **Tunjangan kinerja** maksimum jabatan 43 = **Rp1.500.000** (`salary_structures`, `percent_of_kpi`). Insentif terpisah dari KPI; untuk unit 50 sejak Oktober 2026 mengikuti aturan **Insentif HO** di §1.
- "Omzet" pada `OMZET_GLOBAL` dihitung dari **gross profit** (`SUM(sub_total - hpp_penjualan)`), bukan omzet penjualan. Definisi ini masih perlu konfirmasi bisnis (KPI-06).
- **Unit trial (`unit.is_trial = 1`) tidak ikut komponen omzet lintas cabang**: `OMZET_GLOBAL` (Kadiv 43 & Manager 34), `TARGET_CABANG` Manager, serta `OMZET_WILAYAH`/`TARGET_CABANG` SPV. Komponen non-omzet dan KPI karyawan unit trial sendiri tetap dihitung. — `KpiCalculationService::omsetBranchUnits/trialUnitIds`; test `KpiOmsetTrialUnitTest`

---

## 2. DARI SISTEM LAMA (diambil dari controller, BELUM dikonfirmasi owner)

Dipertahankan persis di `LegacyKpiCalculationService` agar hasil identik.

### Target per Unit

| Unit | customer | atas_customer* | closing | upselling | followup | roas |
|---|---|---|---|---|---|---|
| 1 | 130 | 220 | 111 | 14 | 100 | 5 |
| 2 | 118 | 180 | 96 | 14 | 80 | 4 |
| 3 | 210 | 350 | 188 | 27 | 60 | 3 |
| 4 | 118 | 250 | 96 | 14 | 80 | 5 |

*atas_customer hanya dipakai pada context non-gaji.
Context `gaji` memakai `customer` polos.

### Batas & Target Omset (per unit)

#### Context gaji
| | U1 | U2 | U3 | U4 |
|---|---|---|---|---|
| batas_awal | 30jt | 18jt | 40jt | 18jt |
| batas_kedua | 35jt | 22jt | 45jt | 22jt |
| batas_ketiga | 40jt | 26jt | 50jt | 26jt |
| batas_keempat | 45jt | 30jt | 55jt | 30jt |
| target_omset | 50jt | 35jt | 60jt | 35jt |

#### Context penilaian_kinerja / slip_gaji
| | U1 | U2 | U3 | U4 |
|---|---|---|---|---|
| batas_awal | 35jt | 18jt | 40jt | 35jt |
| batas_kedua | 40jt | 22jt | 45jt | 40jt |
| batas_ketiga | 45jt | 26jt | 50jt | 45jt |
| batas_keempat | 50jt | 30jt | 55jt | 50jt |
| target_omset | 55jt | 35jt | 60jt | 55jt |

### Persentase Nilai

- omset tiered: 0 / 33 / 66 / 100 (default & KT)
- SPV/Pengiklan cabang_aman: gaji = 33/66/100, non-gaji = 25/50/75/100
- roas: `total × 100` (gaji) / `total × 20` (non-gaji)
- budgeting: `total × 100` (gaji) / `total × 20` (non-gaji)
- bug minor/operasional/ecommerce/fitur: `total/4 × 20`
- kehadiran/kebersihan/seragam/sop: `total/26 × 20`
- tutup kasir: `count/30 × 20` (gaji) / `min(count/30 × 100, 100)` (non-gaji)
- opname: `count/4 × 100`
- divisi/kebersihan/seragam/kepatuhan (SPV): `avg × 20`

### Bobot per Jabatan

| Jabatan | KPI (bobot) | Absen (bobot) |
|---|---|---|
| 35 Admin | Omset 70, Tutup Kasir 10, Stok Opname 10, Absensi 10 | 80/5/10/5 |
| 36 Teknisi | Omset 70, Omset Teknisi 15 (=omset), Customer 15. Target Omset Teknisi = target Omset Toko unit ÷ 2 sejak 2026-10; periode sebelumnya memakai `kpi_targets` | 80/5/10/5 |
| 41 Kepala Toko | Omset 70, Customer 10, Tutup Kasir 10, Opname 10 | 80/5/10/5 |
| 40 SPV | Area Supervisor: Omzet Wilayah 20, Target Cabang 15, Produktivitas Cabang 15, SOP 15, Kinerja Kepala Toko 15, Kedisiplinan Team 10, Customer Satisfaction 10 | 80/5/10/5 |
| 42 CS | gaji: omset 70; non: omset 60 + Testimoni 10; + Closing/Upselling/FollowUp 10/10/10 | 80/5/10/5 |
| 43 Kepala Divisi (legacy "Pengiklan") | **Legacy:** Budg 15/ROAS 15/Omset 70; non: Budg 15/ROAS 15/Omset 10/Customer 60 — **bukan KPI aktif**; lihat §1a | 80/5/10/5 |
| 44 Multimedia | Omset 30, Feed PL 15, Video 20, Feed Mingguan 15, Story 10, Testimoni 10 | 80/5/10/5 |
| 45 IT | Omset 30, Bug Minor 10, Operasional 25, Ecommerce 15, Fitur 20 | 80/5/10/5 |
| 46 PIC (non-gaji) | Budget Toko 20, Budget Global 30, Omset Cabang 50 | 80/5/10/5 |

### Area Supervisor (SPV) — KPI Kini (Kode 40)

| Komponen | Sumber data | Status |
|---|---|---|
| OMZET_WILAYAH | `SUM(actual omset cabang) / SUM(target omset cabang) × 100`, target dari `kpi_targets` (`OMSET_CABANG`); unit trial tidak dihitung | config DB + calculator existing |
| TARGET_CABANG | avg `(actual/target) × 100` per cabang; target dari `kpi_targets` (`OMSET_CABANG`); unit trial tidak dihitung | config DB + calculator existing |
| PRODUKTIVITAS_CABANG | unique customer bulan berjalan / bulan sebelumnya × 100; sumber `penjualan.id_pelanggan` | config DB + calculator existing |
| SOP | avg nilai KPI "Kepatuhan SOP" Kepala Toko (sudah dihitung sistem); **bukan** checklist SOP baru | ambil nilai existing (kpi_evaluations) |
| KINERJA_KEPALA_TOKO | avg total KPI Kepala Toko (tanpa absensi/kedisiplinan); ambil dari `KpiCalculationService` | ambil nilai existing |
| KEDISIPLINAN_TEAM | avg attendance_score seluruh team cabang (Kepala Toko + Teknisi + Admin + team lainnya) | ambil nilai existing (attendance_score) |
| CUSTOMER_SATISFACTION | manual input 0–100 per bulan; disimpan di `kpi_evaluations` (saat ini); siap migrasi ke `GOOGLE_BUSINESS_PROFILE` | input manual / siap dikembangkan |

- Bobot total kpi-group = 100% (`kpi_weights.weight_group = 'kpi'`).
- Detail Absensi memakai bobot dari `AttendanceAggregationService::ATTENDANCE_COMPONENTS` = 80/5/10/5 (Kehadiran/Kebersihan/Seragam/Kepatuhan SOP), bukan dari `kpi_weights`. Nilai per-kriteria tetap dari `AttendanceAggregationService::calculateSPVAttendance` (kehadiran SPV sendiri + rata-rata KT/Kadiv), bukan memengaruhi 7 komponen KPI.
- Scope area = `spv_units (spv_id = ID_AKUN)` → daftar unit cabang, fallback unit sendiri jika tidak ada mapping.

### Tunjangan

| Jabatan | tunjangan_kinerja max | absen | penempatan |
|---|---|---|---|
| 41 KT | 850k | 250k max | 350k jika bukan kota unit |
| 40 SPV | 1.25jt | 250k | 350k |
| 43 Kepala Divisi (legacy "Pengiklan") | 1jt (legacy; aktif **1,5jt** — lihat §1a) | 250k | 350k |
| 46 PIC (non-gaji) | 850k | 250k | 350k |
| 35 Admin unit 1 | 850k | 250k | 350k |
| 35 Admin unit lain | 250k | 250k | 350k |
| default (36,42,44,45) | 250k | 250k | 350k |

### Gaji Pokok & Insentif Legacy

- gaji_pokok = 1.500.000
- insentif legacy (context gaji): KT & default = `3% omset / 4`; pengiklan = `1% omset / 1`
- insentif legacy (context non-gaji): KT & default = `3% omset / 3`; pengiklan = `1% omset / 4`
- SPV = `0.5% omset` per cabang (no divisor)
- Multimedia = `1% omset / 4` per cabang

---

## 3. UNKNOWN / BUTUH KONFIRMASI

| Rule | Status | Yang dibutuhkan |
|---|---|---|
| Target omzet `/gaji` 50M vs `/penilaian` 55M | **UNKNOWN** | Konfirmasi mana yang benar untuk pembayaran |
| Apakah `/gaji` adalah halaman yang dibayar (vs slip_gaji) | UNKNOWN | Alur proses payroll |
| Definisi skor 1-5 untuk manual KPI | UNKNOWN | Pedoman resmi |
| Aggregation manual KPI (SUM vs AVG vs latest) | UNKNOWN | Pedoman resmi |
| Threshold tiap KPI manual | UNKNOWN | Pedoman resmi |
| Siapa evaluator tiap KPI manual | UNKNOWN | Role owner |
| Dashboard `assetberjalan()` formula terpisah | UNKNOWN | Apakah dashboard = slip_gaji |
