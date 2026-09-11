# Refactor KPI Absensi: Auto-Scoring dari Jam Masuk

## 📋 Overview

Refactor sistem KPI Absensi dari **input manual nilai 1-5** menjadi **input jam masuk aktual + auto-scoring** berdasarkan keterlambatan.

**Status:** ✅ Core implementation complete (Migration + Service + Calculator + Tests)
**Backward-Compatible:** ✅ Data lama (nilai manual) tetap valid
**Non-Destructive:** ✅ Histori tidak rusak

---

## 🏗️ Arsitektur

### Tabel Baru: `kpi_attendance_detail`

```sql
CREATE TABLE kpi_attendance_detail (
  id INT PRIMARY KEY AUTO_INCREMENT,
  evaluation_id INT NOT NULL,              -- FK ke kpi_evaluations
  shift ENUM('PAGI','SIANG','PS'),
  session ENUM('PAGI','SORE','FULL'),
  attendance_type ENUM('NORMAL','IZIN_TELAT'),
  scheduled_time TIME NOT NULL,            -- 08:45, 12:45, 17:00
  actual_time TIME NOT NULL,               -- jam masuk aktual
  late_minutes INT DEFAULT 0,              -- keterlambatan (menit)
  auto_score DECIMAL(3,1) DEFAULT 5.0,     -- nilai otomatis 0-5
  created_at DATETIME,
  updated_at DATETIME
);
```

**Relasi:**
- 1:1 untuk shift PAGI/SIANG
- 1:2 untuk shift PS (pagi + sore)

### Service Layer

**1. `AttendanceScoreCalculator`** (Pure Function)
- Input: shift, session, attendance_type, scheduled_time, actual_time
- Output: late_minutes, auto_score
- Tested: ✅ 35/35 unit tests PASS

**2. `AttendanceInputService`** (Integration)
- `saveAttendance()`: jam masuk → auto-score → insert/update ke DB
- `getDailyScore()`: backward-compatible read (prioritas auto, fallback manual)
- Tested: ✅ Integration tests ready

---

## 📐 Aturan Scoring

### Jam Mulai Shift

| Shift | Session | Jam Mulai |
|-------|---------|-----------|
| PAGI  | FULL    | 08:45     |
| SIANG | FULL    | 12:45     |
| PS    | PAGI    | 08:45     |
| PS    | SORE    | 17:00     |

### Normal (Tidak Izin)

| Keterlambatan | Nilai |
|---------------|-------|
| ≤ 0 menit     | 5     |
| 1-3 menit     | 4     |
| 4-6 menit     | 3     |
| 7-10 menit    | 2     |
| 11-14 menit   | 1     |
| ≥ 15 menit    | 0     |

### Izin Telat

| Durasi Izin   | Nilai |
|---------------|-------|
| 1-5 menit     | 5     |
| 6-15 menit    | 4     |
| 16-30 menit   | 3     |
| 31-40 menit   | 1     |
| > 40 menit    | 0     |

### PS (Pagi + Sore)

- **2 absensi terpisah**: pagi (08:45) & sore (17:00)
- Masing-masing dinilai sesuai aturan Normal/Izin Telat
- **Total keterlambatan harian = telat pagi + telat sore**

**Contoh:**
- Pagi: 08:50 (telat 5 menit) → score 3
- Sore: 17:05 (telat 5 menit) → score 3
- Total keterlambatan = 10 menit
- Nilai harian (rata-rata) = 3

---

## 🔄 Backward Compatibility

### Data Lama (Manual)
```
kpi_evaluations:
  employee_id: 10
  evaluation_date: 2026-09-01
  raw_score: 4.0   ← nilai manual lama
  
kpi_attendance_detail: (kosong)
```
**Dibaca sebagai:** score = 4.0 (manual)

### Data Baru (Auto)
```
kpi_evaluations:
  id: 123
  employee_id: 10
  evaluation_date: 2026-09-10
  raw_score: 3.0   ← disinkronkan dari auto_score
  
kpi_attendance_detail:
  evaluation_id: 123
  shift: PAGI
  actual_time: 08:50
  late_minutes: 5
  auto_score: 3.0   ← dihitung otomatis
```
**Dibaca sebagai:** score = 3.0 (auto)

### Service Read Logic
```php
$daily = $svc->getDailyScore($employeeId, $date);
// Prioritas: auto_score dari detail, fallback raw_score manual
// Return: ['score' => float, 'is_auto' => bool, 'late_minutes' => int]
```

---

## ✅ Verifikasi

### Unit Test: `AttendanceScoreCalculator`
```bash
php74 app/Scripts/attendance_score_test.php
```
**Result:** 35/35 PASS
- ✅ Scheduled time per shift
- ✅ Normal scoring (0-15+ menit)
- ✅ Izin telat scoring (1-40+ menit)
- ✅ PS dual session
- ✅ Edge cases (tepat waktu, lebih awal, >15 menit)

### Integration Test: `AttendanceInputService`
```bash
php74 app/Scripts/attendance_input_test.php
```
**Requirement:** Database dengan employee existing

**Coverage:**
- ✅ Save attendance (jam masuk → auto-score → DB)
- ✅ Update existing
- ✅ PS dual session (pagi + sore)
- ✅ Izin telat
- ✅ Backward-compatible read (manual vs auto)

---

## 🚀 Next Steps (UI Implementation)

### 1. Controller: `PenilaianKPI::attendance_input()`
```php
// GET: tampilkan form input jam masuk
// POST: terima jam masuk → AttendanceInputService::saveAttendance()
```

### 2. View: `penilaian_kpi/attendance_input.php`

**Form Fields:**
- Tanggal (date picker)
- Karyawan (select/search)
- Shift (radio: PAGI/SIANG/PS)
- Sesi (radio: PAGI/SORE — tampil jika PS)
- Jenis Absensi (radio: NORMAL/IZIN_TELAT)
- Jam Masuk (time picker HH:MM)

**Display (realtime):**
- Jam Mulai Shift (auto dari shift+sesi)
- Keterlambatan (menit) — live calculate
- Nilai Otomatis (0-5) — live calculate

**Submit:** Simpan → auto-insert ke `kpi_evaluations` + `kpi_attendance_detail`

### 3. View: Grid Harian (Existing)

**Tampilan untuk data auto:**
```
Tanggal: 10/09/2026
Jam Masuk: 08:50
Telat: 5 menit
Nilai: 3 (otomatis)
```

**Tampilan untuk data manual lama:**
```
Tanggal: 01/09/2026
Nilai: 4 (manual)
```

### 4. Integrasi dengan `AttendanceAggregationService`

**Existing service sudah membaca dari `kpi_evaluations.raw_score`.**

**Perubahan minimal:** Tidak ada — nilai otomatis sudah disinkronkan ke `raw_score` saat save.

**Perhitungan bulanan tetap sama:**
```
nilai_bulanan = SUM(raw_score_harian) / (hari_efektif × 5) × 100
```

---

## 📊 Database Migration

```bash
php74 spark migrate
```

**Output:**
```
Running: CreateKpiAttendanceDetail
Migrations complete.
```

**Tabel created:** ✅ `kpi_attendance_detail`

---

## 🔒 Preservasi Logic Existing

### ✅ Hari Efektif
- Logic `AttendanceAggregationService::getEffectiveDayCount()` **tidak berubah**
- Hari efektif = hari kalender - jumlah hari OFF (raw_score=0)
- Tetap dipakai untuk perhitungan nilai bulanan

### ✅ OFF Day
- Input OFF tetap via existing flow (raw_score = 0, tanpa detail)
- Service `getDailyScore()` mengembalikan score = 0 untuk OFF

### ✅ Komponen Lain (KEBERSIHAN, SERAGAM, KEPATUHAN_SOP)
- **Tidak terpengaruh** — masih input manual via grid existing
- Refactor ini **hanya untuk KEHADIRAN**

---

## 📝 File Changes

### New Files
- ✅ `Migrations/2026-09-10-140000_CreateKpiAttendanceDetail.php`
- ✅ `Models/ModelKpiAttendanceDetail.php`
- ✅ `Services/Kpi/AttendanceScoreCalculator.php`
- ✅ `Services/Kpi/AttendanceInputService.php`
- ✅ `Scripts/attendance_score_test.php`
- ✅ `Scripts/attendance_input_test.php`
- ✅ `REFACTOR_KPI_ABSENSI.md` (this file)

### Modified Files
- ⏳ `Controllers/PenilaianKPI.php` (TODO: add attendance_input routes)
- ⏳ `Views/penilaian_kpi/attendance_input.php` (TODO: create UI)
- ⏳ `Views/penilaian_kpi/kpi_detail.php` (TODO: tampilkan jam masuk + telat untuk data auto)

---

## 🎯 Production Deployment Checklist

- [x] Migration created & tested
- [x] Service layer implemented
- [x] Unit tests written (35/35 PASS)
- [x] Integration tests written
- [ ] Integration tests run with real employee data
- [ ] UI controller implemented
- [ ] UI view created
- [ ] Manual testing with Admin/Evaluator role
- [ ] Verify existing grid display compatibility
- [ ] Verify monthly aggregation calculation
- [ ] Data migration plan for existing manual scores (optional)
- [ ] User training documentation

---

## 💡 Usage Example

### Input Absensi (PHP)
```php
use App\Services\Kpi\AttendanceInputService;

$svc = new AttendanceInputService();

// Contoh: Karyawan telat 5 menit shift pagi
$result = $svc->saveAttendance(
    employeeId: 10,
    date: '2026-09-10',
    shift: 'PAGI',
    session: 'FULL',
    attendanceType: 'NORMAL',
    actualTime: '08:50',
    evaluatorId: 1
);

// Result:
// [
//   'evaluation_id' => 123,
//   'auto_score' => 3.0,
//   'late_minutes' => 5
// ]
```

### PS Dual Session
```php
// Pagi
$svc->saveAttendance(10, '2026-09-10', 'PS', 'PAGI', 'NORMAL', '08:50', 1);
// → score 3 (telat 5 menit)

// Sore
$svc->saveAttendance(10, '2026-09-10', 'PS', 'SORE', 'NORMAL', '17:05', 1);
// → score 3 (telat 5 menit)

// Get daily
$daily = $svc->getDailyScore(10, '2026-09-10');
// → score 3 (rata-rata), late_minutes 10 (total)
```

### Izin Telat
```php
$svc->saveAttendance(10, '2026-09-10', 'PAGI', 'FULL', 'IZIN_TELAT', '09:00', 1);
// → Izin 15 menit → score 4
```

---

## 🔍 Troubleshooting

### Q: Data lama hilang setelah migration?
**A:** Tidak. Migration hanya CREATE tabel baru, tidak ALTER/DROP existing.

### Q: Bagaimana jika ada data manual yang ingin dikonversi ke auto?
**A:** Buat script migrasi terpisah (optional). Data manual tetap valid dan terbaca.

### Q: Apakah perhitungan gaji terpengaruh?
**A:** Tidak. `raw_score` di `kpi_evaluations` tetap dipakai oleh `AttendanceAggregationService` & `LegacyKpiCalculationService`.

### Q: Bagaimana input OFF?
**A:** OFF tetap via existing flow (raw_score=0 tanpa detail), atau bisa via UI baru dengan checkbox "OFF".

---

## 📞 Support

**Developer:** AI Assistant (Kiro)
**Date:** 2026-09-10
**Version:** 1.0.0

---

**END OF DOCUMENTATION**