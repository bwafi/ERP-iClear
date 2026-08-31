# KPI & Salary Audit Verification

**Tanggal:** 27 Agustus 2026  
**Tipe:** Verification Audit (Tahap 2)  
**Status:** READ-ONLY — Tidak ada perubahan kode/database

---

## 1. Verification Summary

| Bug | Status Lama | Status Verifikasi | Perubahan |
|-----|-------------|------------------|-----------|
| BUG-001 | CRITICAL | **NOT A BUG** (FIXED) | `?: 1` guard sudah ada di line 689 |
| BUG-002 | CRITICAL | **CONFIRMED** (KeyPerformance.php only) | PenilaianKPI.php sudah fixed, KeyPerformance.php belum |
| BUG-003 | CRITICAL | **CONFIRMED** | date('m')/date('Y') hardcoded line 681-682 |
| BUG-004 | CRITICAL | **NOT A BUG** (FIXED) | `if ($omset == 0) continue` sudah ada line 705-706 |
| BUG-005 | HIGH | **PARTIALLY CONFIRMED** | Code map benar, DB string inconsistency belum fixed |
| BUG-006 | HIGH | **CONFIRMED** | $total_feed dipakai untuk 2 KPI |
| BUG-007 | HIGH | **CONFIRMED** | Exact match `==` pada float |
| BUG-008 | HIGH | **CONFIRMED** | Pembagi gaji=4, harusnya 3 |
| BUG-009 | HIGH | **CONFIRMED** (intentional) | Target beda per context |
| BUG-010 | HIGH | **CONFIRMED** | Join via string name |

**Kesimpulan:** Dari 10 bug, 2 adalah FALSE POSITIVE (sudah fixed), 1 partial, 7 confirmed.

---

## 2. BUG Verification

### BUG-001: Division by Zero HPP

**Status:** NOT A BUG (FIXED)

**Evidence:**
```
File: app/Controllers/PenilaianKPI.php
Function: hitungKPIGaji()
Line: 689-690
```

```php
689: $totalomset = ($aktual_omset_unit[1] + $aktual_omset_unit[2] + $aktual_omset_unit[3] + $aktual_omset_unit[4]) ?: 1;
690: $persentasetotal = ($total_hpp / $totalomset) * 100;
```

**Actual Behavior:** `?: 1` memastikan denominator minimal 1, division by zero tidak mungkin terjadi.

**Expected Behavior:** Sama.

**Impact:** Tidak ada — kode sudah aman.

**Reasoning:** Guard clause sudah benar, bug lama sudah diperbaiki.

---

### BUG-002: Duplicate Key `level`

**Status:** CONFIRMED (KeyPerformance.php only)

**Evidence:**
```
File: app/Controllers/KeyPerformance.php
Function: insert_penilaian()
Lines: 153, 158
```

```php
153: 'level' => $levelList[$i] ?? null,
...
158: 'level' => '2',
```

Dan di `update_penilaian()`:
```php
213: 'level' => $levelList[$i] ?? null,
...
218: 'level' => '2',
```

**Actual Behavior:** PHP menggunakan key terakhir → `'level'` selalu `'2'`, menimpa nilai dari form.

**Expected Behavior:** Level dari form seharusnya dipakai.

**Impact:** Level KPI selalu 2, prefill data salah.

**Note:** `PenilaianKPI.php:199` dan `:250` SUDAH DIPERBAIKI (single `level` key). Hanya `KeyPerformance.php` yang masih bermasalah.

---

### BUG-003: HPP menggunakan `date()` hardcoded

**Status:** CONFIRMED

**Evidence:**
```
File: app/Controllers/PenilaianKPI.php
Function: hitungKPIGaji()
Lines: 681-682
```

```php
681: ->where('MONTH(penjualan.tanggal)', date('m'))
682: ->where('YEAR(penjualan.tanggal)', date('Y'))
```

**Actual Behavior:** HPP selalu dihitung dari bulan berjalan, bukan bulan parameter `$bulan`/`$tahun`.

**Expected Behavior:** Gunakan `$bulan` dan `$tahun` seperti pada line 644-645 (omset query).

**Impact:** Skor HPP salah untuk periode selain bulan berjalan.

**Reasoning:** Copy-paste error dari query omset di atasnya yang sudah benar.

---

### BUG-004: Division by Zero HPP per Unit

**Status:** NOT A BUG (FIXED)

**Evidence:**
```
File: app/Controllers/PenilaianKPI.php
Function: hitungKPIGaji()
Lines: 704-708
```

```php
704: $omset = $aktual_omset_unit[$idUnit] ?? 0;
705: if ($omset == 0) {
706:     continue;
707: }
708: $persentase = ($hpp / $omset) * 100;
```

**Actual Behavior:** Unit tanpa penjualan di-skip via `continue`.

**Expected Behavior:** Sama.

**Impact:** Tidak ada — aman.

---

### BUG-005: `follow up` vs `followup`

**Status:** PARTIALLY CONFIRMED

**Evidence:**
```
File: app/Controllers/PenilaianKPI.php
Function: hitungKPIGaji()
Line: 785
```

```php
785: $followupAspek = ($context === 'penilaian_kinerja' || $context === 'slip_gaji') ? 'follow up' : 'followup';
```

**Actual Behavior:** Code memetakan aspek name per context dengan benar. Query menggunakan aspek name yang tepat.

**Expected Behavior:** DB seharusnya konsisten (semua 'followup' atau semua 'follow up').

**Impact:** Nilai followup tidak = 0 karena code sudah handle mapping.

**Reasoning:** Underlying DB inconsistency masih ada, tapi code sudah work-around. Bukan bug kritis.

---

### BUG-006: Feed Mingguan Overwrite Feed PL

**Status:** CONFIRMED

**Evidence:**
```
File: app/Controllers/PenilaianKPI.php
Function: hitungKPIGaji()
Lines: 804, 1035-1037
```

```php
804: $total_feed = $sumAspek('feed mingguan') ?: $sumAspek('feed pl');
...
1035: $nilai_feed_pl = min($total_feed, 100);
1037: $nilai_feed_mingguan = min($total_feed, 100);
```

**Actual Behavior:** Kedua KPI (Feed PL dan Feed Mingguan) menggunakan variabel `$total_feed` yang sama.

**Expected Behavior:** Feed PL harusnya pakai `sumAspek('feed pl')`, Feed Mingguan pakai `sumAspek('feed mingguan')`.

**Impact:** Nilai Feed PL selalu sama dengan Feed Mingguan.

---

### BUG-007: Exact Match Omset Conditions

**Status:** CONFIRMED

**Evidence:**
```
File: app/Controllers/PenilaianKPI.php
Function: hitungKPIGaji()
Lines: 851-854
```

```php
851: } elseif ($aktual_omset == $batas2) {
852:     $nilai_omset = 33;
853: } elseif ($aktual_omset == $batas3) {
854:     $nilai_omset = 66;
```

**Actual Behavior:** Exact `==` pada nilai float omset.

**Expected Behavior:** Seharusnya `>=` atau range check.

**Impact:** Jika omset tepat di batas, dapat nilai exact. Jika off-by-fraction, masuk ke else (interpolasi).

**Reasoning:** Boundary condition fragile tapi tidak crash.

---

### BUG-008: Pembagi Insentif Kepala Toko

**Status:** CONFIRMED

**Business Rule:**
```
1 toko = 3 orang (1 Kepala Toko + 1 Teknisi + 1 Admin)
Rumus: Insentif Kepala Toko = 1% × Omzet Toko ÷ 3
```

**Context yang diverifikasi:**

| Context | Denominator Code | Expected | Status |
|---------|-----------------|----------|--------|
| `/gaji` | **4** (line 845) | 3 | ❌ SALAH |
| `/penilaian_kinerja` | 3 (line 845) | 3 | ✅ BENAR |
| `/slip_gaji` | 3 (line 845) | 3 | ✅ BENAR |

**Evidence:**
```
File: app/Controllers/PenilaianKPI.php
Line: 845
```

```php
845: $pembagiInsentifKepalaToko = ($context === 'gaji') ? 4 : 3;
```

**Actual Behavior:**
- Context `gaji`: pembagi = 4 → insentif 25% lebih kecil dari seharusnya
- Context lain: pembagi = 3 → benar

**Expected Behavior:** Semua context pakai pembagi 3

**Impact:** Insentif Kepala Toko di `/gaji` lebih kecil 25% dari seharusnya

**Additional Finding:** Line 859 menggunakan `(3/100)` bukan `(1/100)`:
```php
859: $insentif = (3 / 100) * $aktual_omset / $pembagiInsentifKepalaToko;
```
Business rule menyebut 1%, tapi code pakai 3%. Perlu konfirmasi business rule.

**Status:** NOT FIXED

---

### BUG-009: Target Omset Berbeda Antar Context

**Status:** CONFIRMED (Intentional)

**Evidence:**
```
File: app/Controllers/PenilaianKPI.php
Function: hitungKPIGaji()
Lines: 612-616 (context gaji) vs 625-629 (context lain)
```

```php
// gaji context
616: $target_omset = [1 => 50000000, 2 => 35000000, 3 => 60000000, 4 => 35000000];

// penilaian_kinerja / slip_gaji context  
629: $target_omset = [1 => 55000000, 2 => 35000000, 3 => 60000000, 4 => 55000000];
```

**Actual Behavior:** Unit 1 dan 4 punya target berbeda antar context.

**Expected Behavior:** Perlu keputusan bisnis — apakah memang boleh beda?

**Impact:** Lebih mudah capai target di context `gaji`.

**Note:** Code comment line 598-601 menjelaskan ini intentional.

---

### BUG-010: Relasi KPI Menggunakan Nama String

**Status:** CONFIRMED

**Evidence:**
```
File: app/Models/ModelPenilaianKPI.php
Function: getAllKPI()
Line: 93
```

```php
93: ->join('template_kpi', 'template_kpi.template_kpi = penilaian_kpi.kpi_utama', 'left')
```

**Actual Behavior:** Join via string `template_kpi.template_kpi = penilaian_kpi.kpi_utama`.

**Expected Behavior:** Seharusnya via FK `template_kpi_idtemplate_kpi`.

**Impact:** Jika nama template berubah, relasi putus.

**Note:** Tabel `penilaian_kpi` punya kolom `template_kpi_idtemplate_kpi` tapi tidak dipakai di join ini.

---

## 3. Context Verification

### Perbandingan 3 Context

| Komponen | gaji | penilaian_kinerja | slip_gaji | Seharusnya Sama? |
|----------|------|-------------------|-----------|------------------|
| Target Omset | 50M/35M/60M/35M | 55M/35M/60M/55M | 55M/35M/60M/55M | ❌ Unit 1 & 4 beda |
| Target Customer | Tanpa atas/bawah | Dengan atas/bawah | Dengan atas/bawah | ❌ Formula beda |
| Bobot SPV | Omset 70%/Cust 10% | Omset 10%/Cust 70% | Omset 10%/Cust 70% | ❌ Terbalik |
| Bobot CS | 70/10/10/10 (no Testimoni) | 60/10/10/10/10 | 60/10/10/10/10 | ❌ Testimoni hanya kinerja |
| Bobot Pengiklan | 15/15/70 (no Cust) | 15/15/10/60 | 15/15/10/60 | ❌ Customer hanya kinerja |
| Nilai Absen | Hardcode 90 | AVG×20 | AVG×20 | ❌ Hardcode vs actual |
| Aspek Followup | 'followup' | 'follow up' | 'follow up' | ⚠️ Mapping beda |
| Pembagi KT | 4 | 3 | 3 | ❌ Harus 3 semua |
| Pembagi Pengiklan | 1 (full) | 4 | 4 | ⚠️ By design |
| HPP Period | date() hardcoded | date() hardcoded | date() hardcoded | ❌ Semua salah |
| Insentif | Beda pembagi | Beda pembagi | Beda pembagi | ❌ Inconsistent |
| Tutup Kasir | count/30×20 | min(count/30×100) | min(count/30×100) | ❌ Max beda (20 vs 100) |

**Kesimpulan:** Hanya `slip_gaji` dan `penilaian_kinerja` yang konsisten. `gaji` context berbeda signifikan.

---

## 4. KPI Formula Verification

### Kepala Toko (Jabatan 41)

```
Input:
  - Omset Unit: SUM(sub_total - hpp) WHERE unit=X, MONTH=tanggal, YEAR=tahun
  - Customer: COUNT(idpenjualan) WHERE unit=X
  - Tutup Kasir: COUNT(status) WHERE unit=X
  - Opname: COUNT(DISTINCT DATE) WHERE unit=X

Target:
  - Omset: target_omset[unit] (50M/35M/60M/35M untuk gaji)
  - Customer: target['customer'] (130/118/210/118)

Achievement:
  - Omset: Tiered 0/33/66/100 berdasarkan batas
  - Customer: min((actual/target)*100, 100)

Bobot:
  - Omset Toko: 70%
  - Total Customer: 10%
  - Tutup Kasir: 10%
  - Opname: 10%

Score:
  skor_total = Σ(nilai × bobot / 100)

Tunjangan:
  tunjangan_kinerja = skor_total/100 × 850.000

Insentif:
  if omset >= target: insentif = (3/100) × omset / pembagi

Gaji:
  gaji = 1.500.000 + tunjangan_kinerja + tunjangan_absen + tunjangan_penempatan + insentif
```

**Verifikasi:** Formula sesuai source code line 1062-1218.

---

### SPV (Jabatan 40)

```
Bobot (gaji):
  - Omset Cabang: 70%
  - Customer: 10%
  - Operasional: 10%
  - Divisi: 10%

Bobot (kinerja):
  - Omset Cabang: 10%
  - Customer: 70%
  - Operasional: 10%
  - Divisi: 10%

Insentif:
  for each cabang: if omset >= batas_keempat: cabang_aman++
                   if omset >= target: insentif += (5/1000) × omset

Tunjangan: skor_total/100 × 1.250.000
```

**Verifikasi:** Bobot terbalik antara context. Inti logic benar.

---

### Customer Service (Jabatan 42)

```
Bobot (gaji):
  - Omset: 70%
  - Closing: 10%
  - Upselling: 10%
  - Follow Up: 10%

Bobot (kinerja):
  - Omset: 60%
  - Closing: 10%
  - Upselling: 10%
  - Follow Up: 10%
  - Testimoni: 10%

Insentif: dari branch else, (3/100) × omset / 4
```

**Verifikasi:** Testimoni hanya di context kinerja. Insentif dari fallthrough.

---

### Pengiklan (Jabatan 43)

```
Bobot (gaji):
  - Budgeting: 15%
  - ROAS: 15%
  - Omset: 70%

Bobot (kinerja):
  - Budgeting: 15%
  - ROAS: 15%
  - Omset: 10%
  - Customer: 60%

Insentif: for each cabang: if omset >= target: insentif += (1/100) × omset / pembagi
  - gaji: pembagi = 1 (full)
  - kinerja: pembagi = 4
```

**Verifikasi:** Customer KPI hanya di kinerja. Insentif pembagi beda per context — BY DESIGN.

---

### Admin (Jabatan 35)

```
Bobot:
  - Omset Toko: 70%
  - Tutup Kasir: 10%
  - Stok Opname: 10%
  - Absensi: 10%

Tunjangan: skor_total/100 × (850K if unit=1 else 250K)
```

**Verifikasi:** Sesuai.

---

### Teknisi (Jabatan 36)

```
Bobot:
  - Omset Toko: 70%
  - Omset Teknisi: 15%  ← SAMA DENGAN Omset Toko
  - Customer Masuk: 15%

Insentif: (3/100) × omset / 4
```

**Verifikasi:** "Omset Teknisi" menggunakan nilai yang SAMA dengan "Omset Toko" — tidak ada query terpisah.

---

### Multimedia (Jabatan 44)

```
Bobot:
  - Omset Cabang: 30%
  - Feed PL: 15%
  - Video: 20%
  - Feed Mingguan: 15%
  - Story: 10%
  - Testimoni: 10%

Insentif: for each cabang: if omset >= target: insentif += (1/100) × omset / 4
```

**Verifikasi:** Feed PL dan Feed Mingguan pakai $total_feed yang sama (BUG-006).

---

### IT (Jabatan 45)

```
Bobot:
  - Omset: 30%
  - Bug Minor: 10%
  - Operasional: 25%
  - Ecommerce: 15%
  - Fitur: 20%  ← SAMA DENGAN Operasional

Insentif: (3/100) × omset / 4
```

**Verifikasi:** "Fitur" menggunakan aspek 'operasional' yang sama dengan "Operasional" (BUG dari laporan sebelumnya).

---

### PIC (Jabatan 46) — hanya context non-gaji

```
Bobot:
  - Budget Per Toko: 20%
  - Budget Global: 30%
  - Omset Cabang: 50%

Tunjangan: skor_total/100 × 850.000
```

**Verifikasi:** Tidak diproses di context `gaji`.

---

## 5. Incentive Verification

### Summary Insentif per Jabatan

| Jabatan | Formula | Syarat | Pembagi (gaji) | Pembagi (kinerja) |
|---------|---------|--------|----------------|-------------------|
| 41 Kepala Toko | 3% × omset | omset ≥ target | **4** ❌ | 3 ✅ |
| 40 SPV | 0.5% × omset per cabang | per cabang aman | full | full |
| 43 Pengiklan | 1% × omset per cabang | per cabang aman | 1 (full) | 4 |
| 35/36/42/44/45/46 | 3% × omset | omset ≥ target | /4 | /3 |

**Business Rule (dari user):**
- Kepala Toko: 1% × omset ÷ 3 (1 toko = 3 orang)
- Pengiklan: 1% × omset ÷ 4 (Pengiklan, Multimedia, IT, CS)

**Gap:** Code pakai 3% untuk Kepala Toko, business rule sebut 1%. Perlu konfirmasi.

---

## 6. Salary Verification

### Trace Alur Karyawan (Contoh: Kepala Toko Unit 1)

```
akun (ID_AKUN=1, ID_JABATAN=41, ID_UNIT=1)
  ↓
jabatan (41 = Kepala Toko)
  ↓
unit (1 = Probolinggo)
  ↓
hitungKPIGaji(1, bulan, tahun, context)
  ├── Omset Unit 1: SUM(sub_total - hpp) WHERE unit=1
  ├── Customer Unit 1: COUNT(idpenjualan) WHERE unit=1
  ├── HPP: (hardcoded date)
  ├── Nilai Omset: Tiered threshold
  ├── Nilai Customer: min((actual/target)*100, 100)
  ├── Detail KPI: [Omset 70%, Customer 10%, Tutup Kasir 10%, Opname 10%]
  ├── Skor Total: Σ(nilai × bobot / 100)
  ├── Tunjangan Kinerja: skor/100 × 850.000
  ├── Tunjangan Absen: skor_absen/100 × 250.000
  ├── Tunjangan Penempatan: 350.000 (if alamat ≠ Probolinggo)
  ├── Insentif: (3/100) × omset / pembagi
  └── Gaji: 1.5M + tunjangan_kinerja + tunjangan_absen + tunjangan_penempatan + insentif
```

### Perbedaan /gaji vs /slip_gaji

| Komponen | /gaji | /slip_gaji | Sama? |
|----------|-------|-----------|-------|
| Gaji pokok | 1.5M | 1.5M | ✅ |
| Tunjangan kinerja | Dari hitungKPIGaji('gaji') | Dari hitungKPIGaji('slip_gaji') | ❌ Bobot/batas beda |
| Tunjangan absen | Dari hitungKPIGaji('gaji') | Dari hitungKPIGaji('slip_gaji') | ❌ Absen hardcode 90 vs actual |
| Insentif | Dari hitungKPIGaji('gaji') | Dari hitungKPIGaji('slip_gaji') | ❌ Pembagi beda |
| Bon | Tidak ada | SUM(kas_keluar LIKE '%bon%') | ❌ |
| Lembur | Tidak ada | SUM(kas_keluar LIKE '%lembur%') | ❌ |

**Kesimpulan:** /gaji dan /slip_gaji menghasilkan nominal BERBEDA karena context beda.

---

## 7. Database Verification

### Tabel penilaian_kpi

```
Primary Key: idpenilaian_kpi
Foreign Keys: 
  - pegawai_idpegawai → akun.ID_AKUN
  - unit_idunit → unit.idunit
  - template_kpi_idtemplate_kpi → template_kpi.idtemplate_kpi (TIDAK dipakai di join!)
  - penilaian_idpenilaian → penilaian.idpenilaian

Relevant columns:
  - kpi_utama (VARCHAR) — nama KPI, dipakai untuk join string
  - bobot (INT)
  - target (DOUBLE)
  - realisasi (DOUBLE)
  - score (DOUBLE)
  - level (ENUM 1/2)
```

**Issue:** `template_kpi_idtemplate_kpi` ada tapi tidak dipakai di `getAllKPI()` join.

### Tabel template_kpi

```
Primary Key: idtemplate_kpi
Foreign Keys:
  - jabatan_idjabatan → jabatan.ID_JABATAN

Relevant columns:
  - template_kpi (VARCHAR) — nama template
  - bobot (INT)
  - target (DOUBLE)
  - formula (VARCHAR)
  - level (ENUM 1/2)
```

### Relasi template_kpi → penilaian_kpi

**QUERY 1 (ModelPenilaianKPI.php:93):**
```php
->join('template_kpi', 'template_kpi.template_kpi = penilaian_kpi.kpi_utama', 'left')
```
**Status:** JOIN VIA STRING NAME ❌

**QUERY 2 (ModelPenilaianKPI.php:91-92):**
```php
->join('akun', 'akun.ID_AKUN = penilaian_kpi.pegawai_idpegawai', 'left')
->join('template_kpi', 'template_kpi.template_kpi = penilaian_kpi.kpi_utama', 'left')
```
**Status:** SAMA, JOIN VIA STRING ❌

**QUERY 3 (ModelTemplateKpi.php — getByJabatan):**
```php
->where('jabatan_idjabatan', $jabatan_id)
->where('level', 1)
```
**Status:** Pakai FK `jabatan_idjabatan` ✅

**Kesimpulan:** Relasi template→penilaian menggunakan STRING NAME di beberapa query, FK di query lain.

---

## 8. Business Rule Verification

| Business Rule | Source Code | Database | Status | Evidence |
|---------------|-----------|----------|--------|----------|
| 1 toko = 3 orang | N/A | akun: 3 orang per unit | MATCH | DB count |
| Insentif KT = 1% × omset ÷ 3 | 3% × omset ÷ 4 (gaji) | N/A | MISMATCH | Line 845, 859 |
| Insentif Pengiklan = 1% × omset ÷ 4 | 1% × omset ÷ 1 (gaji) | N/A | PARTIAL | Line 846 |
| Bobot total = 100% | ✅ All jabatan | N/A | MATCH | Switch case |
| Gaji pokok = 1.5M | ✅ Hardcoded | N/A | MATCH | Line 1233 |
| HPP < 35% → 100 | ✅ Code | N/A | MATCH | Line 692-700 |
| Absensi context gaji = 90 | ✅ Hardcode | N/A | MATCH (but wrong) | Line 820 |

**Documentation source:** `docs/analisis/penilaianKPI.md`, `docs/analisis/incentiveSystem.md`

---

## 9. New Findings

### NEW-001: Insentif Kepala Toko = 3% Bukan 1%

**Status:** UNKNOWN — perlu konfirmasi business rule

**Evidence:**
```php
// Line 859
$insentif = (3 / 100) * $aktual_omset / $pembagiInsentifKepalaToko;
```

User menyebut "1% dari omzet", tapi code pakai `3/100` (3%).

**Possible explanations:**
1. Business rule salah dijelaskan user
2. Code salah implementasi
3. Ada perubahan dari 1% ke 3% (tanpa dokumentasi)

---

### NEW-002: Context `gaji` Tidak Menampilkan Bon/Lembur

**Status:** CONFIRMED (by design)

**Evidence:**
- `/gaji` view: tidak ada query bon/lembur
- `/slip_gaji` view: query bon & lembur dari kas_keluar

**Impact:** Karyawan lihat gaji di `/gaji` tanpa potongan bon.

---

### NEW-003: Tutup Kasir Context `gaji` Max 20 Poin

**Status:** CONFIRMED

**Evidence:**
```php
// Line 1023-1025
$nilai_tutup_kasir = ($context === 'gaji')
    ? ($total_tutup_kasir / 30 * 20)
    : min(($total_tutup_kasir / 30) * 100, 100);
```

**Impact:** Di context gaji, tutup kasir maksimal 20 poin (bukan 100).

---

### NEW-004: $total_fitur = $total_bug_operasional

**Status:** CONFIRMED

**Evidence:**
```php
// Line 810-811
$total_bug_operasional = $sumAspek('operasional');
$total_fitur = $sumAspek('operasional');  // SAMA!
```

**Impact:** KPI "Fitur" IT menggunakan data yang sama dengan "Operasional".

---

## 10. Severity Reassessment

| Bug | Severity Lama | Severity Baru | Alasan |
|-----|--------------|---------------|--------|
| BUG-001 | CRITICAL | INFO | Sudah fixed, tidak ada dampak |
| BUG-002 | CRITICAL | HIGH | Masih confirmed, tapi hanya KeyPerformance.php |
| BUG-003 | CRITICAL | **CRITICAL** | HPP salah untuk semua periode non-current |
| BUG-004 | CRITICAL | INFO | Sudah fixed |
| BUG-005 | HIGH | LOW | Code sudah handle mapping |
| BUG-006 | HIGH | MEDIUM | Feed PL/Mingguan duplikat, tapi tidak crash |
| BUG-007 | HIGH | LOW | Boundary fragile, tapi tidak crash |
| BUG-008 | HIGH | **HIGH** | Insentif salah 25% di context gaji |
| BUG-009 | HIGH | MEDIUM | Intentional, tapi inkonsisten |
| BUG-010 | HIGH | MEDIUM | Join string, tapi tidak crash |

**Overall Risk:** CRITICAL (karena BUG-003 HPP date hardcoded)

---

## 11. Confirmed Bugs

1. **BUG-002** (KeyPerformance.php) — Duplicate key 'level'
2. **BUG-003** (PenilaianKPI.php:681-682) — HPP date hardcoded
3. **BUG-006** (PenilaianKPI.php:804, 1035-1037) — Feed PL/Mingguan duplikat
4. **BUG-007** (PenilaianKPI.php:851-854) — Exact match omset
5. **BUG-008** (PenilaianKPI.php:845) — Pembagi Kepala Toko salah (4 vs 3)
6. **BUG-009** (PenilaianKPI.php:612-629) — Target beda per context
7. **BUG-010** (ModelPenilaianKPI.php:93) — Join string name
8. **NEW-001** — Insentif KT 3% vs 1% (UNKNOWN)
9. **NEW-004** (PenilaianKPI.php:810-811) — Fitur = Operasional

---

## 12. False Positives

1. **BUG-001** — Division by zero HPP: Sudah ada `?: 1` guard
2. **BUG-004** — Division by zero HPP per unit: Sudah ada `if ($omset == 0) continue`

---

## 13. Unknown / Need Business Confirmation

1. **NEW-001:** Apakah insentif Kepala Toko 1% atau 3%?
2. **BUG-009:** Apakah target omset boleh beda antara context gaji vs kinerja?
3. **BUG-008:** Apakah pembagi 3 untuk semua context (termasuk gaji)?
4. **Context gaji:** Apakah memang sengaja pakai rumus berbeda, atau bug?

---

## 14. Final Recommended Fix Order

### Priority 1 (CRITICAL — Fix segera)
1. **BUG-003:** Fix HPP date hardcoded → gunakan `$bulan`/`$tahun`
2. **BUG-008:** Fix pembagi Kepala Toko → ubah `($context === 'gaji') ? 4 : 3` menjadi `3`

### Priority 2 (HIGH — Fix minggu ini)
3. **BUG-002:** Fix duplicate key 'level' di KeyPerformance.php
4. **NEW-001:** Konfirmasi & fix insentif KT (1% vs 3%)

### Priority 3 (MEDIUM — Planning)
5. **BUG-006:** Pisahkan Feed PL dan Feed Mingguan
6. **BUG-009:** Standardisasi target antar context
7. **BUG-010:** Ganti join string ke FK
8. **NEW-004:** Buat aspek 'fitur' terpisah untuk IT

### Priority 4 (LOW — Nice to have)
9. **BUG-005:** Standardisasi aspek name di DB
10. **BUG-007:** Ganti exact match ke range check

---

**End of Verification Report**
