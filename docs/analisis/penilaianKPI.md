# KPI SYSTEM ANALYSIS — ERP CV. ICLEAR DIGITAL SOLUSI

## 1. Executive Summary

Sistem KPI pada project ini memiliki **3 lapisan penilaian** yang berbeda dan terpisah:

| Layer | Nama | Level | Cara Kerja |
|-------|------|-------|------------|
| Layer 1 | **Checklist Pekerjaan** | Manual | Manager input skor 1-5 per aspek secara manual |
| Layer 2 | **Performance Grading** | Semi-otomatis | Realisasi diisi manual → score dihitung JS `(realisasi/target) × bobot` |
| Layer 3 | **Key Performance Indicator** | Semi-otomatis | Identik Layer 2, tapi level=2 |
| Layer 4 | **Penilaian Kinerja + Gaji** | **100% Otomatis** | Hitung dari DB langsung → omset, customer, absensi, HPP → skor → gaji |

**Temuan kritis:** Layer 2 & 3 hanya scorer manual yang disimpan. Layer 4 (hitungKPIGaji) adalah engine sebenarnya yang menghitung gaji berdasarkan data transaksi di database.

---

## 2. Daftar KPI

### A. KPI PENILAIAN KINERJA (hitungKPIGaji) — Engine Utama

#### Admin (Jabatan 35)

| KPI | Bobot | Data Sumber | Rumus | Satuan |
|-----|-------|-------------|-------|--------|
| Omset Toko | 70% | `detail_penjualan` → `SUM(sub_total - hpp_penjualan)` WHERE unit=X | Tiered: 0/33/66/100 berdasarkan batas omset | Poin 0-100 |
| Tutup Kasir | 10% | `tutup_kasir` → `COUNT(status)` WHERE unit=X | `(count/30) × 100` (context kinerja) atau `(count/30) × 20` (context gaji) | Poin 0-100 |
| Stok Opname | 10% | `stok_opname_draft` → `COUNT(DISTINCT DATE(tanggal))` WHERE unit=X | `(count/4) × 100` | Poin 0-100 |
| Absensi | 10% | Hardcode = 90 (context gaji) atau AVG(skor kehadiran) × 20 (kinerja) | **Hardcoded** | Poin |

#### Teknisi (Jabatan 36)

| KPI | Bobot | Rumus |
|-----|-------|-------|
| Omset Toko | 70% | Sama seperti Admin |
| Omset Teknisi | 15% | SAMA DENGAN Omset Toko (bermasalah) |
| Customer Masuk | 15% | `min((customer_aktual/customer_target) × 100, 100)` |

#### Kepala Toko (Jabatan 41)

| KPI | Bobot | Rumus |
|-----|-------|-------|
| Omset Toko | 70% | Tiered, insentif = 3% × omset / 4 jika ≥ target |
| Total Customer | 10% | `min((customer/unit_target) × 100, 100)` |
| Tutup Kasir | 10% | Sama seperti Admin |
| Opname | 10% | Sama seperti Admin |

#### SPV (Jabatan 40)

| KPI | Bobot (gaji) | Bobot (kinerja) | Rumus |
|-----|-------------|-----------------|-------|
| Omset Cabang | 70% | 10% | Berdasarkan jumlah cabang aman (omset ≥ batas ke-4) |
| Customer | 10% | 70% | Jumlah cabang aman (customer ≥ atas_customer) |
| Operasional | 10% | 10% | Sama dengan nilai omset (copy) |
| Divisi | 10% | 10% | `AVG(skor penilaian) × 20` |

#### Customer Service (Jabatan 42)

| KPI | Bobot (gaji) | Bobot (kinerja) |
|-----|-------------|-----------------|
| Omset | 70% | 60% |
| Closing | 10% | 10% |
| Upselling | 10% | 10% |
| Follow Up | 10% | 10% |
| Testimoni | - | 10% |

#### Pengiklan (Jabatan 43)

| KPI | Bobot (gaji) | Bobot (kinerja) |
|-----|-------------|-----------------|
| Budgeting | 15% | 15% |
| ROAS | 15% | 15% |
| Omset | 70% | 10% |
| Customer | - | 60% |

#### Multimedia (Jabatan 44)

| KPI | Bobot |
|-----|-------|
| Omset Cabang | 30% |
| Feed PL | 15% |
| Video | 20% |
| Feed Mingguan | 15% |
| Story | 10% |
| Testimoni | 10% |

#### IT (Jabatan 45)

| KPI | Bobot |
|-----|-------|
| Omset | 30% |
| Bug Minor | 10% |
| Operasional | 25% |
| Ecommerce | 15% |
| Fitur | 20% |

#### PIC (Jabatan 46) — hanya context non-gaji

| KPI | Bobot |
|-----|-------|
| Budget Per Toko | 20% |
| Budget Global | 30% |
| Omset Cabang | 50% |

### B. DETAIL ABSEN (semua jabatan kecuali SPV pakai rata-rata divisi)

| Aspek | Bobot | Rumus |
|-------|-------|-------|
| Kehadiran | 40% | `SUM(skor kehadiran per pegawai) / 26 × 20` |
| Kebersihan | 20% | `SUM(skor kebersihan per pegawai) / 26 × 20` |
| Seragam | 20% | `SUM(skor seragam per pegawai) / 26 × 20` |
| Kepatuhan SOP | 20% | `SUM(skor kepatuhan per pegawai) / 26 × 20` |

### C. LAYER 2 & 3 — Penilaian KPI / Key Performance

Dihitung via JavaScript di frontend:
```
score = (realisasi / target) × bobot
Total = Σ semua score
Rank: ≥90 Platinum, ≥80 Gold, ≥70 Silver, <70 Bronze
```

### D. LAYER 1 — Checklist Pekerjaan

Skor 1-5 per aspek, diinput manual oleh manager.

---

## 3. Sumber Data Setiap KPI

| KPI | Table/Query | Field |
|-----|------------|-------|
| Omset | `detail_penjualan` JOIN `penjualan` | `SUM(sub_total - hpp_penjualan)` |
| Customer | `penjualan` | `COUNT(idpenjualan)` atau `COUNT(kode_invoice)` |
| Tutup Kasir | `tutup_kasir` | `COUNT(status)` |
| Stok Opname | `stok_opname_draft` | `COUNT(DISTINCT DATE(tanggal))` |
| HPP | `detail_penjualan` JOIN `barang` | `SUM(hpp_penjualan)` |
| Absensi | `penilaian` table (aspek='kehadiran') | `AVG(skor)` atau `SUM(skor)` |
| Closing/Upselling/FollowUp | `penilaian` table | `SUM(skor)` WHERE aspek=X |
| Budgeting/ROAS/Video/Feed/etc | `penilaian` table | `SUM(skor)` |
| Grooming | `presensi` | `COUNT(idpresensi)` WHERE status_absensi=1 |

---

## 4. Rumus Perhitungan Detail

### Rumus Omset (bukan revenue, tapi gross profit)

```
Omset = SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan)
```

**Ini gross margin, bukan omset penjualan.** Label "Omset" sebenarnya adalah **laba kotor per item**.

### Rumus HPP Global

```
HPP% = (Total HPP semua unit / Total Omset semua unit) × 100

Nilai HPP:
≤35% → 100
≤40% → 75
≤45% → 50
>45% → 0
```

### Rumus Customer (context non-gaji)

```
Hitung jumlah cabang yang customer-nya ≥ atas_customer
1 cabang aman = 25, 2 = 50, 3 = 75, 4 = 100
```

### Rumus Total Skor Kinerja

```
skor_total = Σ (nilai_kpi × bobot_kpi / 100)
```

### Rumus Tunjangan

```
Tunjangan Kinerja = skor_total / 100 × max_tunjangan_per_jabatan
Tunjangan Absen   = skor_absen / 100 × 250.000
Gaji Pokok        = 1.500.000 (FIX semua jabatan)
Insentif           = 3% × omset / 4 (jika omset ≥ target)
Tunjangan Penempatan = 350.000 (jika alamat ≠ kota penempatan)
```

---

## 5. Simulasi Perhitungan

### SIMULASI — Admin (Jabatan 35) Unit Probolinggo

**INPUT DATA:**
- Unit = 1 (Probolinggo)
- Omset bulan = Rp 48.000.000
- Tutup kasir = 25 kali
- Stok opname = 3 kali
- Absensi score = 90 (hardcode)

**PERHITUNGAN:**
```
Batas omset (context gaji):
batas_awal = 30.000.000
batas_kedua = 35.000.000
batas_ketiga = 40.000.000
batas_keempat = 45.000.000
target_omset = 50.000.000

Omset 48.000.000 ≥ batas_keempat (45jt) DAN < target (50jt)
→ nilai_omset = 100
→ insentif = 0

Tutup kasir = 25/30 × 20 = 16.67
Stok opname = 3/4 × 100 = 75
Absensi = 90 (hardcode)

detail_kpi:
- Omset Toko: bobot=70, nilai=100 → 100×70/100 = 70
- Tutup Kasir: bobot=10, nilai=16.67 → 16.67×10/100 = 1.67
- Stok Opname: bobot=10, nilai=75 → 75×10/100 = 7.5
- Absensi: bobot=10, nilai=90 → 90×10/100 = 9

skor_total = 70 + 1.67 + 7.5 + 9 = 88.17

Tunjangan Kinerja (Admin, Unit 1) = 88.17/100 × 850.000 = Rp 749.445
Tunjangan Absen = 90/100 × 250.000 = Rp 225.000
Gaji Pokok = Rp 1.500.000

TOTAL = 1.500.000 + 749.445 + 225.000 + 0 + 0 = Rp 2.474.445
```

---

## 6. Interpretasi Hasil

| Skor Kinerja | Badge (View) | Arti |
|-------------|-------------|------|
| ≥ 90 | Green (success) | Sangat Baik |
| ≥ 75 | Yellow (warning) | Cukup |
| < 75 | Red (danger) | Perlu Perhatian |

**Rank (Layer 2 & 3):**
| Total Score | Rank |
|------------|------|
| ≥ 90 | Platinum |
| ≥ 80 | Gold |
| ≥ 70 | Silver |
| < 70 | Bronze |

---

## 7. Analisis Bobot

### Bobot per Jabatan — Apakah Total = 100%?

| Jabatan | Total Bobot | Valid? |
|---------|-------------|--------|
| Admin (35) | 70+10+10+10 = **100%** | ✅ |
| Teknisi (36) | 70+15+15 = **100%** | ✅ |
| Kepala Toko (41) | 70+10+10+10 = **100%** | ✅ |
| SPV (gaji) | 70+10+10+10 = **100%** | ✅ |
| SPV (kinerja) | 10+70+10+10 = **100%** | ✅ |
| CS (gaji) | 70+10+10+10 = **100%** | ✅ |
| CS (kinerja) | 60+10+10+10+10 = **100%** | ✅ |
| Pengiklan (gaji) | 15+15+70 = **100%** | ✅ |
| Pengiklan (kinerja) | 15+15+10+60 = **100%** | ✅ |
| Multimedia | 30+15+20+15+10+10 = **100%** | ✅ |
| IT | 30+10+25+15+20 = **100%** | ✅ |
| PIC | 20+30+50 = **100%** | ✅ |

### Detail Absen — Total Bobot

| Aspek | Bobot | Total |
|-------|-------|-------|
| Kehadiran | 40% | |
| Kebersihan | 20% | |
| Seragam | 20% | |
| Kepatuhan SOP | 20% | |
| **TOTAL** | **100%** | ✅ |

**Semua bobot = 100%.** Valid.

---

## 8. Dashboard vs Database Validation

### Penilaian Kinerja (hitungKPIGaji)

| Komponen | Sumber | Konsisten? |
|----------|--------|-----------|
| Omset Global | `SUM(sub_total - hpp_penjualan)` per unit | ✅ Konsisten |
| Customer | `COUNT(idpenjualan)` atau `COUNT(kode_invoice)` | ⚠️ **ADA INKONSISTENSI** |
| Tutup Kasir | `COUNT(status)` | ✅ |
| Stok Opname | `COUNT(DISTINCT DATE(tanggal))` | ✅ |

**Inkonsistensi Customer:** Pada context gaji menggunakan `COUNT(idpenjualan)`, sedangkan context lain menggunakan `COUNT(kode_invoice)`. Jika ada kode_invoice yang NULL atau duplikat, hasilnya akan berbeda.

### Riwayat KPI vs Data Tersimpan

Riwayat menampilkan data langsung dari `penilaian_kpi` table. Total score dihitung dari field `score` yang sudah disimpan. **Konsisten** dengan data yang di-save.

### Summary KPI

Menggunakan view `summary_grading_kpi` yang **DEFINISI VIEW-NYA TIDAK ADA DI REPOSITORY**. Tidak dapat diverifikasi apakah view menghasilkan angka yang benar.

---

## 9. Edge Case Analysis

### CRITICAL BUGS DITEMUKAN

#### BUG 1: HPP Menggunakan BULAN SAAT INI, Bukan Bulan yang Di-filter

**Lokasi:** `PenilaianKPI.php:676-686`

```php
$aktual_hpp[$idUnit] = $this->db->table('detail_penjualan')
    ->where('MONTH(penjualan.tanggal)', date('m'))  // ← SELALU bulan ini
    ->where('YEAR(penjualan.tanggal)', date('Y'))    // ← SELALU tahun ini
```

**Dampak:** Jika user memilih bulan Maret tapi saat ini bulan Agustus, HPP dihitung dari Agustus bukan Maret. **Skor HPP akan salah untuk semua periode selain bulan berjalan.**

#### BUG 2: Tutup Kasir Pada Context Gaji: `count/30 × 20` vs `count/30 × 100`

```php
// context 'gaji':
nilai_tutup_kasir = ($total_tutup_kasir / 30 * 20);  // MAX = 20

// context lain:
nilai_tutup_kasir = min(($total_tutup_kasir / 30) * 100, 100);  // MAX = 100
```

**Dampak:** Pada context gaji, tutup kasir hanya bernilai maks 20 dari 100. Dikali bobot 10% → kontribusi ke total skor hanya 2 poin. Konsisten dengan aslinya (seperti code comment), tapi **sangat rendah**.

#### BUG 3: Teknisi — Omset Toko dan Omset Teknisi PAKAI NILAI SAMA

```php
case 36: // TEKNISI
    $detail_kpi = [
        ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
        ['nama' => 'Omset Teknisi', 'bobot' => 15, 'nilai' => $nilai_omset], // ← SAMA!
```

**Dampak:** KPI "Omset Teknisi" menggunakan omset TOKO, bukan omset individu teknisi. Tidak ada query terpisah untuk omset per teknisi.

#### BUG 4: Kehadiran/Kebersihan/Seragam/SOP Dibagi 26 dengan Pengali 20

```php
$nilai_kehadiran = $totalKehadiran / 26 * 20;  // MAX = SUM(skor per pegawai)/26*20
```

`$totalKehadiran` adalah `SUM(skor)` dari tabel `penilaian` untuk satu pegawai. Jika skor max = 5 dan ada 1 entry per hari, max = 5 × 26 = 130 → 130/26×20 = 100. Tetapi jika ada lebih dari 1 entry per hari (duplikasi), bisa > 100. **Tidak ada cap ke 100.**

#### BUG 5: `total_fitur` dan `total_bug_operasional` PAKAI ASPEK SAMA

```php
$total_bug_operasional = $sumAspek('operasional');
...
$total_fitur = $sumAspek('operasional');  // ← SAMA dengan bug_operasional!
```

**Dampak:** KPI "Fitur" dan "Bug Minor" untuk IT semuanya pakai data yang sama. KPI "Fitur" tidak punya data terpisah.

#### BUG 6: Context Gaji Absensi Hardcode 90

```php
if ($context === 'gaji') {
    $nilai_absen = 90;
}
```

**Dampak:** Halaman gaji SELALU menampilkan absensi 90, tidak peduli data aktual. Absensi dihitung dari tabel `penilaian` (input manual), bukan dari data `presensi`.

#### BUG 7: Context Gaji vs Kinerja — Target Customer Berbeda

```php
if ($context === 'gaji') {
    $nilai_customer = min(($total_customer / $target['customer']) * 100, 100);
} else {
    // pakai skema 'cabang aman' terhadap atas_customer
}
```

**Dampak:** Angka customer pada halaman gaji dan halaman penilaian kinerja bisa sangat berbeda untuk karyawan yang sama di bulan yang sama.

#### BUG 8: Insentif Pengiklan Beda Context

```php
// gaji:
$insentif += (1 / 100) * $omset;  // 1% × omset
// kinerja (penilaian_kinerja):
$insentif += (1 / 100) * $omset / 4;  // 0.25% × omset
```

**Dampak:** Insentif pengiklan pada halaman gaji 4x lebih besar dari halaman penilaian kinerja.

#### BUG 9: Total Skor Ditampilkan Tanpa Unit yang Konsisten

```php
$skor_total = 0;
foreach ($detail_kpi as $kpi) {
    $skor_total += ($kpi['nilai'] * $kpi['bobot']) / 100;
}
```

Ini benar secara matematika. Namun `nilai_omset` untuk SPV/Pengiklan menggunakan **semua 4 unit**, bukan hanya unit karyawan. Karyawan yang berbeda unit-nya mendapat skor omset yang **sama**.

---

## 10. KPI Validity Score

| KPI | Rumus | Sumber Data | Konsistensi | Interpretasi | Edge Case | Skor |
|-----|-------|-------------|-------------|--------------|-----------|------|
| **Omset Toko** | A | A | A | B | C | **B+** |
| **Customer (gaji)** | B | B | A | B | B | **B** |
| **Customer (kinerja)** | B | B | B | B | B | **B-** |
| **Tutup Kasir** | D | B | C | B | C | **C+** |
| **Stok Opname** | A | A | A | B | A | **A-** |
| **Absensi (gaji)** | F | F | F | F | F | **F** |
| **Absensi (kinerja)** | C | C | B | B | C | **C** |
| **Closing** | B | C | A | B | B | **B-** |
| **Upselling** | B | C | A | B | B | **B-** |
| **Follow Up** | B | C | A | B | B | **B-** |
| **Budgeting** | C | C | A | C | C | **C-** |
| **ROAS** | C | C | A | C | C | **C-** |
| **HPP** | C | F | F | B | F | **D** |
| **Omset Teknisi** | F | F | F | F | F | **F** |
| **Fitur (IT)** | F | F | F | F | F | **F** |
| **Feed PL/Video/Story/Testimoni** | C | C | A | C | C | **C-** |
| **Tunjangan Kinerja** | A | B | A | B | A | **B+** |
| **Tunjangan Absen** | B | C | B | B | A | **B-** |
| **Insentif** | B | A | A | B | B | **B** |
| **Skor Total** | A | B | A | A | B | **B+** |
| **Layer 2/3 KPI** | A | B | A | B | C | **B** |
| **Checklist** | C | C | A | C | C | **C-** |

---

## 11. Masalah yang Ditemukan

### CRITICAL (Skor D = Tidak Dapat Dipercaya)

| # | Masalah | Dampak | Lokasi |
|---|---------|--------|--------|
| 1 | **HPP selalu dihitung dari bulan berjalan**, bukan bulan yang di-filter | Skor HPP salah untuk semua periode selain bulan ini | `PenilaianKPI.php:676-686` |
| 2 | **Absensi pada context gaji = hardcode 90** | Skor absensi tidak pernah benar | `PenilaianKPI.php:818-819` |
| 3 | **Omset Teknisi = Omset Toko** | KPI Omset Teknisi tidak ada nilainya | `PenilaianKPI.php:1072-1076` |
| 4 | **Fitur (IT) = Operasional (IT)** | KPI Fitur tidak ada datanya sendiri | `PenilaianKPI.php:810` |
| 5 | **Summary KPI view tidak ada di repo** | Tidak bisa verifikasi data summary | `ModelSummaryKPI.php:26` |

### MAJOR (Skor C = Perlu Perbaikan)

| # | Masalah | Dampak |
|---|---------|--------|
| 6 | Kehadiran/absensi dihitung dari tabel `penilaian` (input manual), bukan dari `presensi` | Bukan data otomatis, tergantung input manager |
| 7 | Semua target omset/customer **hardcoded** di code, tidak di database | Sulit ubah target tanpa ubah code |
| 8 | Tutup kasir pada context gaji hanya max 20 poin | Kontribusi ke gaji sangat kecil |
| 9 | SPV & Pengiklan: nilai omset pakai **semua 4 cabang** | Karyawan berbeda unit dapat skor omset sama |
| 10 | Duplikasi logika di TutupKasir.php dan PenilaianKPI.php | Konsentrasi logika berbeda |
| 11 | `total_feed` di-overwrite oleh `feed_mingguan` | `PenilaianKPI.php:803` — feed_pl tidak dipakai |
| 12 | Numeric value tidak di-cap ke 100 | KPI bisa > 100, melebihi bobot |

### MINOR (Skor B = Minor Issue)

| # | Masalah | Dampak |
|---|---------|--------|
| 13 | Gaji pokok fix Rp 1.500.000 semua jabatan | Tidak ada differensiasi |
| 14 | Tunjangan kinerja range sangat lebar (250rb - 1.25jt) | Gap antar jabatan sangat besar |
| 15 | Penilaian form (Layer 1) hanya bisa input 1 aspek per kali | Ribet untuk input banyak |
| 16 | `switch ($jabatan)` dengan hardcode ID jabatan | fragile, tambah jabatan baru harus edit code |
| 17 | Insert/Update KPI: level dari form ditimpa (history fix di comment) | Sudah diperbaiki |

---

## 12. KPI yang Sudah Valid

| KPI | Alasan Valid |
|-----|-------------|
| **Omset Toko** (jabatan lain selain Teknisi) | Query jelas, target hardcoded jelas, tiered logic masuk akal |
| **Customer** (context gaji, non-SPV) | Formula `min((aktual/target) × 100, 100)` benar |
| **Stok Opname** | `count/4 × 100` simple dan benar |
| **Tunjangan Kinerja** | `(skor/100) × max_tunjangan` sesuai bobot per jabatan |
| **Tunjangan Absen** | `(skor_absen/100) × 250.000` |
| **Gaji Pokok** | Fix 1.500.000 |
| **Tunjangan Penempatan** | Logic alamat vs unit benar |
| **Skor Total** | `Σ(nilai × bobot/100)` — aritmatika benar |
| **Rank (Platinum/Gold/Silver/Bronze)** | Boundary value benar |
| **Bobot total = 100%** | Semua jabatan valid |

---

## 13. KPI yang Perlu Diperbaiki

| KPI | Masalah Utama | Prioritas |
|-----|--------------|-----------|
| HPP | Selalu pakai bulan berjalan | **CRITICAL** |
| Absensi (gaji) | Hardcode 90 | **CRITICAL** |
| Omset Teknisi | Sama dengan Omset Toko | **CRITICAL** |
| Fitur (IT) | Sama dengan Operasional | **CRITICAL** |
| Kehadiran | Dari tabel penilaian (manual), bukan presensi | **MAJOR** |
| Tutup Kasir (gaji) | Max 20 poin | **MAJOR** |
| Customer (kinerja) | Beda formula dengan gaji | **MAJOR** |
| Budgeting/ROAS | Tidak jelas apakah `total × 100` itu valid | **MAJOR** |
| Summary KPI | View tidak di-repo | **MAJOR** |
| Semua target | Hardcoded di code | **MAJOR** |

---

## 14. Final KPI System Score

| Komponen | Skor | Keterangan |
|----------|------|-----------|
| **Accuracy Formula** | 65 | Banyak rumus benar, tapi 4 KPI critical salah |
| **Data Reliability** | 55 | HPP salah periode, absensi hardcode, 1 KPI tidak ada datanya |
| **Calculation Logic** | 80 | Aritmatika benar, tapi ada inconsistensi context |
| **Result Interpretation** | 75 | Badge/rank benar, tapi beberapa KPI menampilkan angka misleading |
| **Consistency** | 60 | Context gaji vs kinerja beda hasil, 1 KPI overwrite lain |
| **Edge Case Handling** | 40 | Tidak ada cap 100, tidak ada validasi data kosong, tidak ada handling untuk 0/NULL |
| **Dashboard/Report** | 50 | Summary view tidak di-repo, tidak bisa verifikasi |

### **FINAL SCORE = 60/100**

**Grade: NEEDS IMPROVEMENT (60-69)**

---

## 15. Kesimpulan Bisnis

> **"Jika perusahaan menggunakan sistem KPI ini untuk menilai karyawan/performa, apakah hasilnya dapat dipercaya?"**

### JAWABAN: **BELUM CUKUP VALID — Ada catatan penting**

**Yang BISA dipercaya:**
- Tunjangan Kinerja untuk Jabatan 35 (Admin), 41 (Kepala Toko), 44 (Multimedia), 45 (IT) — komponen omset dan opname dihitung dari data transaksi
- Bobot KPI sudah benar 100% semua jabatan
- Ranking Platinum/Gold/Silver/Bronze implementasinya benar
- Insentif dan Tunjangan Penempatan logic-nya masuk akal
- Slip gaji menghitung dari data yang benar

**Yang TIDAK BISA dipercaya:**
1. **KPI Absensi** pada halaman gaji = SELALU 90, tidak peduli data aktual
2. **KPI HPP** = salah untuk periode selain bulan berjalan
3. **KPI Omset Teknisi** = tidak ada, nilainya sama dengan Omset Toko
4. **KPI Fitur (IT)** = tidak ada, nilainya sama dengan Operasional
5. **KPI Customer** pada halaman gaji vs penilaian kinerja menghasilkan angka beda untuk data sama
6. **Tutup kasir** pada context gaji bernilai sangat kecil (max 20 dari 100)

**Rekomendasi sebelum dipakai untuk penilaian:**
1. Fix HPP query → gunakan `$bulan`/`$tahun` parameter, bukan `date('m')`/`date('Y')`
2. Fix absensi gaji → jangan hardcode 90
3. Buat query terpisah untuk Omset Teknisi
4. Buat data terpisah untuk KPI Fitur IT
5. Pindahkan semua target omset/customer ke database
6. Cap semua nilai KPI ke max 100
7. Buat migration SQL untuk view `summary_grading_kpi`

---

## 16. Prioritas Perbaikan

| Prioritas | Item | Estimasi Dampak |
|-----------|------|-----------------|
| **P1** | Fix HPP query bulan | Mengubah 2 baris code, mengoreksi semua skor HPP |
| **P1** | Fix absensi gaji hardcode 90 | Mengubah 1 baris, mengoreksi seluruh tunjangan absen |
| **P1** | Buat query Omset Teknisi terpisah | Menambah ~10 baris, mengoreksi 1 KPI |
| **P2** | Buat data terpisah untuk Fitur IT | Menambah 1 query, mengoreksi 1 KPI |
| **P2** | Cap numeric values ke 100 | Menambah `min()` di ~15 lokasi |
| **P2** | Pindahkan target ke database | Membuat table baru + migration |
| **P3** | Buat view SQL `summary_grading_kpi` | Membuat 1 view di database |
| **P3** | Duplikasi logika TutupKasir.php | Refactor, tidak mengubah behavior |

---

## Catatan Metodologi

Laporan ini dihasilkan dari analisis **read-only** terhadap seluruh source code PHP (Controllers, Models, Views), route configuration, dan struktur database (inferred dari models).

**Yang TIDAK dapat diverifikasi dari source code:**
- Isi data di database `summary_grading_kpi` (view SQL tidak ada di repo)
- Data yang sudah ada di tabel `penilaian_kpi`, `penilaian`, `penilaian_detail`
- Isi data di `template_kpi` dan `template_penilaian` (data runtime)
- Apakah ada penyesuaian manual di database yang tidak terekam di code

**Asumsi:**
- Target omset/customer yang hardcoded di code merupakan target aktual yang digunakan perusahaan
- Bobot pada `template_kpi` sudah diisi dengan benar oleh admin
- Skor 1-5 pada penilaian (Layer 1) diinput dengan benar oleh manager
