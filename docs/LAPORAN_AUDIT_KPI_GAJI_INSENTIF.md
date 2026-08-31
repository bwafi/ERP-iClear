# 📋 LAPORAN AUDIT MENYELURUH - SISTEM ERP
## Modul KPI, Gaji, Insentif, dan Organisasi

**Tanggal Audit:** 27 Agustus 2026  
**Cakupan:** KPI, Target, Bobot, Omzet, Insentif, Gaji, Jabatan, Unit, Cabang  
**Status:** READ-ONLY AUDIT (Tidak ada perubahan kode)

---

## A. EXECUTIVE SUMMARY

### Kondisi Sistem Saat Ini

Sistem ERP ini memiliki **3 modul utama** yang menghitung KPI dan gaji karyawan:

| Modul | Route | Context | Fungsi |
|-------|-------|---------|--------|
| **Penilaian Kinerja** | `/penilaian_kinerja` | `penilaian_kinerja` | Melihat detail KPI & skor karyawan |
| **Gaji** | `/gaji` | `gaji` | Dashboard gaji karyawan (login sendiri) |
| **Slip Gaji** | `/penilaian/slip_gaji/{id}` | `slip_gaji` | Cetak slip gaji (print format) |

### Temuan Kritis

1. **Tidak ada Single Source of Truth** untuk target, bobot, dan threshold insentif
2. **Perbedaan rumus antar context** (`gaji` vs `penilaian_kinerja` vs `slip_gaji`)
3. **4 bug CRITICAL** yang dapat menyebabkan crash atau perhitungan salah
4. **Hardcoded values tersebar di 2+ file** tanpa dokumentasi
5. **Relasi database tidak konsisten** (join via nama string, bukan ID)

### Risiko Utama

| Risiko | Severity | Dampak |
|--------|----------|--------|
| Gaji/insentif salah hitung | **CRITICAL** | Kerugian finansial, sengketa karyawan |
| Halaman crash (division by zero) | **CRITICAL** | Tidak bisa akses modul gaji |
| Data KPI tidak konsisten antar modul | **HIGH** | Keputusan manajemen salah |
| Perubahan struktur organisasi menyebabkan bug | **HIGH** | Akses ditolak, gaji salah |

---

## B. ARCHITECTURE MAP

### Struktur File Utama

```
Feature                    Route                        Controller                    Model
──────────────────────────────────────────────────────────────────────────────────────────────
Penilaian KPI (Level 1)    /penilaian_kpi              PenilaianKPI::index()         ModelPenilaianKPI
Key Performance (Level 2)  /key_performance            KeyPerformance::index()       ModelPenilaianKPI
Penilaian Kinerja          /penilaian_kinerja          PenilaianKPI::penilaian_kinerja()  ModelPenilaian
Slip Gaji                  /penilaian/slip_gaji/{id}   PenilaianKPI::slip_gaji()     ModelPenilaianKPI
Gaji                       /gaji                       PenilaianKPI::gaji()          ModelPenilaianKPI
Summary KPI                /SummaryPerformance/summary_kpi  SummaryKPI::summary_kpi()  ModelSummaryKPI
Summary Grading            /SummaryPerformance/summary_grading SummaryKPI::summary_grading() ModelSummaryKPI
```

### Database Tables Utama

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│     akun        │────>│    jabatan      │     │     unit        │
│  (karyawan)     │     │  (positions)    │     │   (branches)    │
└────────┬────────┘     └─────────────────┘     └─────────────────┘
         │
         │
         ▼
┌─────────────────┐     ┌─────────────────┐
│  penilaian_kpi  │────>│  template_kpi   │
│ (hasil KPI)     │     │  (master KPI)   │
└────────┬────────┘     └─────────────────┘
         │
         ▼
┌─────────────────┐     ┌─────────────────┐
│    penilaian    │────>│template_penilaian│
│ (aspek nilai)   │     │ (master aspek)  │
└─────────────────┘     └─────────────────┘

┌─────────────────┐     ┌─────────────────┐
│    penjualan    │────>│ detail_penjualan│
│  (transaksi)    │     │    (item)       │
└─────────────────┘     └─────────────────┘

┌─────────────────┐
│   kas_keluar    │
│ (bon/lembur)    │
└─────────────────┘
```

---

## C. KPI CALCULATION - ANALISIS RUMUS

### Method Inti: `hitungKPIGaji()`

**Lokasi:** `PenilaianKPI.php:569-1252`

**Input:**
- `$idAkun` - ID karyawan
- `$bulan` - format 'mm'
- `$tahun` - format 'yyyy'
- `$context` - 'penilaian_kinerja' | 'slip_gaji' | 'gaji'

**Output:**
```php
[
    'karyawan' => [...],
    'jabatan' => int,
    'unit' => int,
    'aktual_omset_unit' => [1 => ..., 2 => ..., 3 => ..., 4 => ...],
    'detail_kpi' => [...],
    'detail_absen' => [...],
    'skor_total' => float,
    'skor_total2' => float,
    'tunjangan_kinerja' => int,
    'tunjangan_absen' => int,
    'insentif' => int,
    'gaji_pokok' => 1500000,
    'gaji' => int
]
```

### Rumus Perhitungan Utama

#### 1. OMSET

**Query:**
```sql
SELECT SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total
FROM detail_penjualan
JOIN penjualan ON penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan
WHERE MONTH(penjualan.tanggal) = {bulan}
  AND YEAR(penjualan.tanggal) = {tahun}
  AND penjualan.unit_idunit = {idUnit}
```

#### 2. SCORE KPI

```php
skor_total = Σ(nilai_kpi × bobot_kpi) / 100
```

#### 3. TUNJANGAN KINERJA

```php
if ($jabatan == 41) {
    $tunjangan_kinerja = $skor_total / 100 * 850000;
} elseif ($jabatan == 40) {
    $tunjangan_kinerja = $skor_total / 100 * 1250000;
} elseif ($jabatan == 43) {
    $tunjangan_kinerja = $skor_total / 100 * 1000000;
} elseif ($jabatan == 35 && $unit == 1) {
    $tunjangan_kinerja = $skor_total / 100 * 850000;
} else {
    $tunjangan_kinerja = $skor_total / 100 * 250000;
}
```

#### 4. TUNJANGAN ABSEN

```php
skor_total2 = Σ(nilai_absen × bobot_absen) / 100
tunjangan_absen = skor_total2 / 100 * 250000
```

#### 5. INSENTIF

| Jabatan | Persentase | Pembagi | Kondisi |
|---------|------------|---------|---------|
| **41 (Kepala Toko)** | 3% | 3 (non-gaji) / 4 (gaji) | Omset >= target |
| **40 (SPV)** | 0.5% | - (full) | Per cabang yang mencapai target |
| **43 (Pengiklan)** | 1% | 4 (non-gaji) / 1 (gaji) | Per cabang yang mencapai target |
| **44 (Multimedia)** | 1% | 4 | Per cabang yang mencapai target |

#### 6. GAJI TOTAL

```php
gaji_pokok = 1500000  // Hardcoded
gaji = gaji_pokok + tunjangan_kinerja + tunjangan_absen + tunjangan_penempatan + insentif
```

---

## D. TARGET MATRIX

### Target KPI per Unit

| KPI | Unit 1 (Probolinggo) | Unit 2 (Jember) | Unit 3 (Banyuwangi) | Unit 4 (Pandaan) |
|-----|----------------------|-----------------|---------------------|------------------|
| **Customer** | 130 | 118 | 210 | 118 |
| **Atas Customer** | 220 | 180 | 350 | 250 |
| **Bawah Customer** | 150 | 150 | 250 | 200 |
| **Closing** | 111 | 96 | 188 | 96 |
| **Upselling** | 14 | 14 | 27 | 14 |
| **Follow-up** | 100 | 80 | 60 | 80 |
| **ROAS** | 5 | 4 | 3 | 5 |

**Source:** `PenilaianKPI.php:604-622`

### Target Omset per Unit

| Context | Unit 1 | Unit 2 | Unit 3 | Unit 4 |
|---------|--------|--------|--------|--------|
| **gaji** | 50M | 35M | 60M | 35M |
| **penilaian_kinerja** | 55M | 35M | 60M | 55M |

**⚠️ Catatan:** Target berbeda antara context `gaji` dan `penilaian_kinerja`!

---

## E. WEIGHT MATRIX (BOBOT KPI)

### Bobot per Jabatan

| Jabatan | KPI | Bobot (gaji) | Bobot (non-gaji) | Status |
|---------|-----|--------------|------------------|--------|
| **35 (Admin)** | Omset Toko | 70% | 70% | ✅ |
| | Tutup Kasir | 10% | 10% | ✅ |
| | Stok Opname | 10% | 10% | ✅ |
| | Absensi | 10% | 10% | ✅ |
| **41 (Kepala Toko)** | Omset Toko | 70% | 70% | ✅ |
| | Total Customer | 10% | 10% | ✅ |
| | Tutup Kasir | 10% | 10% | ✅ |
| | Opname | 10% | 10% | ✅ |
| **40 (SPV)** | Omset Cabang | **70%** | **10%** | ⚠️ Terbalik |
| | Customer | **10%** | **70%** | ⚠️ Terbalik |
| | Operasional | 10% | 10% | ✅ |
| | Divisi | 10% | 10% | ✅ |
| **42 (CS)** | Omset | 70% | 60% | ⚠️ Beda |
| | Closing | 10% | 10% | ✅ |
| | Upselling | 10% | 10% | ✅ |
| | Follow Up | 10% | 10% | ✅ |
| | Testimoni | - | 10% | ⚠️ Hanya non-gaji |
| **43 (Pengiklan)** | Budgeting | 15% | 15% | ✅ |
| | ROAS | 15% | 15% | ✅ |
| | Omset | **70%** | **10%** | ⚠️ Beda |
| | Customer | - | **60%** | ⚠️ Hanya non-gaji |

**Catatan:** Semua bobot total = 100% ✅, tapi ada perbedaan signifikan antara context.

---

## F. BUGS KRITIS (CRITICAL & HIGH)

### BUG-001: Division by Zero pada Perhitungan HPP

| Aspek | Detail |
|-------|--------|
| **Lokasi** | `PenilaianKPI.php:690` |
| **Kode** | `$persentasetotal = ($total_hpp / $totalomset) * 100;` |
| **Kondisi** | Tidak ada penjualan di semua cabang ($totalomset = 0) |
| **Dampak** | Crash halaman, nilai_hpp_global = error |
| **Severity** | **CRITICAL** |
| **Fix** | `$totalomset = max($totalomset, 1);` |

### BUG-002: Duplicate Key 'level' Mengoverwrite Nilai Form

| Aspek | Detail |
|-------|--------|
| **Lokasi** | `KeyPerformance.php:153,158,213,218` |
| **Kode** | `'level' => $levelList[$i], ... 'level' => '2',` |
| **Kondisi** | Insert/update KPI selalu set level='2' |
| **Dampak** | Level dari form diabaikan, data tidak konsisten |
| **Status** | ✅ Sudah diperbaiki di `PenilaianKPI.php` |
| | ❌ BELUM di `KeyPerformance.php` |
| **Severity** | **CRITICAL** |

### BUG-003: Query HPP Menggunakan date() Hardcoded

| Aspek | Detail |
|-------|--------|
| **Lokasi** | `PenilaianKPI.php:681-682` |
| **Kode** | `->where('MONTH(tanggal)', date('m'))` |
| **Kondisi** | Menghitung KPI bulan lalu |
| **Dampak** | HPP selalu dari bulan sekarang, bukan bulan parameter |
| **Severity** | **CRITICAL** |
| **Fix** | Gunakan `$bulan` dan `$tahun` parameter |

### BUG-004: Division by Zero pada HPP per Unit

| Aspek | Detail |
|-------|--------|
| **Lokasi** | `PenilaianKPI.php:704-708` |
| **Kode** | `$persentase = ($hpp / $omset) * 100;` |
| **Kondisi** | Unit tidak memiliki penjualan ($omset = 0) |
| **Dampak** | Error pada unit tanpa transaksi |
| **Severity** | **CRITICAL** |
| **Fix** | Tambah `if ($omset <= 0) continue;` |

### BUG-005: Aspek 'follow up' vs 'followup' Inconsistency

| Aspek | Detail |
|-------|--------|
| **Lokasi** | `PenilaianKPI.php:785` |
| **Kode** | `$followupAspek = (...) ? 'follow up' : 'followup';` |
| **Kondisi** | Database pakai 'followup' tapi context gaji cari 'follow up' |
| **Dampak** | Nilai followup = 0 di context tertentu |
| **Severity** | **HIGH** |

### BUG-006: Feed Mingguan Overwrite Feed PL Value

| Aspek | Detail |
|-------|--------|
| **Lokasi** | `PenilaianKPI.php:804, 1035-1037` |
| **Kode** | Kedua KPI menggunakan variabel yang sama |
| **Dampak** | Nilai feed_pl = feed_mingguan, duplikasi |
| **Severity** | **HIGH** |

### BUG-007: Kondisi IF Omset Exact Match

| Aspek | Detail |
|-------|--------|
| **Lokasi** | `PenilaianKPI.php:851-854` |
| **Kode** | `$aktual_omset == $batas2` (exact match) |
| **Dampak** | Jika omset di antara batas, nilai tidak konsisten |
| **Severity** | **HIGH** |

### BUG-008: Pembagi Insentif Kepala Toko Salah di Context `gaji` — CONFIRMED BUG

| Aspek | Detail |
|-------|--------|
| **Lokasi** | `PenilaianKPI.php:845` |
| **Kode** | `$pembagiInsentifKepalaToko = ($context === 'gaji') ? 4 : 3;` |
| **Fakta Bisnis** | 1 toko = **3 orang** (1 Kepala Toko + 1 Teknisi + 1 Admin). Insentif Kepala Toko = `3% × omset_unit`, lalu **dibagi 3** karyawan toko |
| **Yang Salah** | Context `gaji` pakai pembagi **4** (seharusnya **3**). Context `penilaian_kinerja` & `slip_gaji` sudah benar pakai **3** |
| **Dampak** | Insentif Kepala Toko di halaman `/gaji` **lebih kecil 25%** dari seharusnya. Karyawan sama di bulan sama dapat nominal berbeda antara `/gaji` vs `/penilaian_kinerja` |
| **Severity** | **HIGH** |
| **Fix** | Ubah line 845 menjadi `$pembagiInsentifKepalaToko = 3;` (hapus ternary, gunakan 3 untuk semua context) |

**Catatan Pengiklan (line 846) — BUKAN BUG:**
```php
$pembagiInsentifPengiklan = ($context === 'gaji') ? 1 : 4;
```
Ini **sesuai aturan bisnis**: Pengiklan dapat `1% × omset` lalu **dibagi 4** (Pengiklan, Multimedia, IT, CS).
- Context `gaji`: Pengiklan dapat **full 1%** (mewakili tim DM)
- Context `penilaian_kinerja`: Pengiklan dapat **1% / 4 = 0.25%** (share tim terlihat per orang)

Jadi hanya **Kepala Toko** yang punya bug di line 845, bukan Pengiklan.

### BUG-009: Target Omset Berbeda Antar Context

| Aspek | Detail |
|-------|--------|
| **Lokasi** | `PenilaianKPI.php:612-629` |
| **Dampak** | Lebih mudah dapat insentif di context gaji |
| **Severity** | **HIGH** |

### BUG-010: Relasi KPI via Nama String

| Aspek | Detail |
|-------|--------|
| **Lokasi** | `ModelPenilaianKPI.php:93` |
| **Kode** | `template_kpi.template_kpi = penilaian_kpi.kpi_utama` |
| **Dampak** | Jika nama template berubah, data tidak terhubung |
| **Severity** | **HIGH** |

---

## G. HARDCODED CONFIGURATION

### Ringkasan Hardcoded Values

| Kategori | Jumlah | Risk |
|----------|--------|------|
| Target KPI | 28 nilai | Duplikat di 2+ file |
| Bobot KPI | 40+ nilai | Context-dependent |
| Threshold Omset | 32 nilai | Tidak di database |
| Persentase Insentif | 4 nilai | Magic numbers |
| Gaji Pokok | 1 nilai | Tidak scalable |
| Tunjangan | 5 nilai | Per jabatan, tidak terstruktur |
| Pembagi Insentif | 2 nilai | Context-dependent |
| ID Jabatan | 10 ID | Fragile |

### Target Omset (per unit)

```
Unit 1: 50M (gaji) / 55M (penilaian)
Unit 2: 35M (sama)
Unit 3: 60M (sama)
Unit 4: 35M (gaji) / 55M (penilaian)
```

### Gaji Pokok & Tunjangan

```
Gaji Pokok: 1,500,000 (hardcoded)

Tunjangan Kinerja:
  - SPV: 1,250,000
  - Pengiklan: 1,000,000
  - Kepala Toko: 850,000
  - Admin Pusat (Unit 1): 850,000
  - Default: 250,000

Tunjangan Absen: 250,000 (max)
Tunjangan Penempatan: 350,000 (jika bukan kota utama)
```

---

## H. CONTEXT COMPARISON

### Perbedaan Antar Context

| Aspek | gaji | penilaian_kinerja | slip_gaji |
|-------|------|-------------------|-----------|
| **Target Omset** | Lebih rendah | Lebih tinggi | Lebih tinggi |
| **Target Customer** | Tanpa atas/bawah | Dengan atas/bawah | Dengan atas/bawah |
| **Nilai Absen** | Hardcoded 90 | Dari rata-rata | Dari rata-rata |
| **Aspek Followup** | 'followup' | 'follow up' | 'follow up' |
| **Bobot SPV Omset** | 70% | 10% | 10% |
| **Bobot SPV Customer** | 10% | 70% | 70% |
| **KPI Testimoni (CS)** | Tidak ada | Ada | Ada |
| **KPI Customer (Pengiklan)** | Tidak ada | Ada | Ada |
| **Pembagi Insentif Kepala Toko** | ~~4~~ (**BUG! harus 3**) | 3 | 3 |
| **Pembagi Insentif Pengiklan** | 1 | 4 | 4 |

### Dampak Perbedaan

1. **Gaji yang ditampilkan di `/gaji` berbeda dengan `/slip_gaji`** untuk karyawan yang sama di bulan yang sama
2. **Penilaian kinerja tidak konsisten** antar modul
3. **Perhitungan insentif berbeda** tergantung halaman yang diakses

---

## I. RISK ASSESSMENT

### Risiko Sistem Terhadap Kesalahan Pembayaran

| Risiko | Probability | Impact | Score | Mitigation |
|--------|-------------|--------|-------|------------|
| Gaji salah hitung karena bug | HIGH | HIGH | **CRITICAL** | Fix bug #1-#4 segera |
| Insentif berbeda antar modul | HIGH | MEDIUM | **HIGH** | Standardisasi context |
| Target berubah tidak update di semua tempat | HIGH | HIGH | **CRITICAL** | Pindah ke database |
| Struktur organisasi berubah | MEDIUM | HIGH | **HIGH** | Hapus hardcoded ID |
| Division by zero crash | MEDIUM | HIGH | **HIGH** | Fix bug #1, #4 |
| Data tidak konsisten antar tabel | MEDIUM | MEDIUM | **MEDIUM** | FK constraints |

### Overall Risk Level: **CRITICAL**

---

## J. RECOMMENDED REFACTORING

### Prioritas Tinggi

1. **Buat Tabel Master KPI**
```sql
CREATE TABLE kpi_target (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT,
    kpi_name VARCHAR(100),
    target_value DECIMAL(15,2),
    year INT,
    month INT,
    created_at TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES unit(idunit)
);
```

2. **Buat Tabel Master Bobot**
```sql
CREATE TABLE kpi_bobot (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jabatan_id INT,
    kpi_name VARCHAR(100),
    bobot DECIMAL(5,2),
    context ENUM('gaji', 'penilaian_kinerja', 'slip_gaji'),
    FOREIGN KEY (jabatan_id) REFERENCES jabatan(ID_JABATAN)
);
```

3. **Buat Config File untuk Constant**
```php
// app/Config/Kpi.php
namespace App\Config;

class Kpi
{
    const GAJI_POKOK = 1500000;
    const TUNJANGAN_ABSEN_MAX = 250000;
    
    const TUNJANGAN_KINERJA = [
        40 => 1250000,
        41 => 850000,
        43 => 1000000,
        'default' => 250000,
    ];
}
```

4. **Ekstrak Logic ke Service Class**
```php
// app/Services/KpiCalculatorService.php
class KpiCalculatorService
{
    public function calculateOmset($unitId, $bulan, $tahun) { ... }
    public function calculateIncentive($jabatanId, $omset, $target) { ... }
    public function calculateScore($detailKpi) { ... }
}
```

---

## K. EXISTING DOCUMENTATION

Dokumentasi existing yang sudah tersedia:
- `docs/analisis/penilaianKPI.md` - Analisis detail rumus KPI
- `docs/analisis/incentiveSystem.md` - Analisis sistem insentif
- `docs/pedoman-kpi/` - Business rule documentation (PDF)

---

## L. KESIMPULAN

### Yang BISA Dipercaya ✅

- Tunjangan Kinerja untuk jabatan tertentu (35, 41, 44, 45)
- Bobot KPI sudah benar 100% semua jabatan
- Ranking Platinum/Gold/Silver/Bronze implementasinya benar
- Insentif dan Tunjangan Penempatan logic-nya masuk akal
- Slip gaji menghitung dari data yang benar

### Yang TIDAK BISA Dipercaya ❌

1. **KPI Absensi** pada halaman gaji = SELALU 90
2. **KPI HPP** = salah untuk periode selain bulan berjalan
3. **KPI Customer** pada halaman gaji vs penilaian kinerja berbeda untuk data sama
4. **Pembagi Insentif Kepala Toko** di context `gaji` pakai **4** (seharusnya **3**, karena 1 toko = 3 orang). **CONFIRMED BUG**
5. **Target Omset** berbeda antar context

### FINAL SCORE: **60/100 - NEEDS IMPROVEMENT**

**Rekomendasi Perbaikan (Urutan Prioritas):**

#### ⚡ **URGENT (Fix dalam 1-2 hari)**
1. **Fix HPP query** → gunakan `$bulan`/`$tahun` parameter, bukan `date('m')`/`date('Y')`
2. **Fix Division by Zero** → tambah `max($totalomset, 1)` dan `if ($omset <= 0) continue`
3. **Fix Pembagi Kepala Toko** → ubah dari `($context === 'gaji') ? 4 : 3` menjadi `3` (1 toko = 3 orang)
4. **Fix Duplicate Key Level** di `KeyPerformance.php` → hapus baris yang overwrite `'level' => '2'`

#### 🔧 **HIGH (Fix dalam 1 minggu)**
5. Fix absensi gaji → jangan hardcode 90, ambil dari data aktual
6. Standardisasi context → gunakan satu rumus untuk semua halaman
7. Fix aspek 'follow up' vs 'followup' → standardisasi di database
8. Fix Feed PL/Mingguan → gunakan variabel terpisah

#### 📋 **MEDIUM (Planning untuk refactor)**
9. Pindahkan semua target/bobot ke database (tabel `kpi_target` dan `kpi_bobot`)
10. Buat config file untuk constant nilai (`app/Config/Kpi.php`)
11. Ekstrak logic ke Service class (`app/Services/KpiCalculatorService.php`)
12. Tambah audit log untuk perubahan target dan bobot

---

**Laporan audit ini adalah hasil analisis read-only terhadap source code, models, routes, dan struktur database.**

**Last Updated:** 27 Agustus 2026
