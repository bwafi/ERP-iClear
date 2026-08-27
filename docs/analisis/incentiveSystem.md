# INCENTIVE SYSTEM ANALYSIS

## 1. Current Organization Structure

### Struktur LAMA (sebelum perubahan)

```
CEO / Direksi
├── Kepala Divisi Digital Marketing
│   ├── IT (Jabatan 45)
│   └── Multimedia (Jabatan 44)
└── Kepala Divisi Customer Service
    └── Customer Service (Jabatan 42)
```

### Struktur BARU (sesudah perubahan)

```
CEO / Direksi
├── Kepala Divisi Digital Marketing
│   ├── IT (Jabatan 45)
│   ├── Multimedia (Jabatan 44)
│   └── Customer Service (Jabatan 42) ← pindah ke bawah DM
└── Customer Service ← BUKAN lagi Kepala Divisi
```

### Catatan Penting

- **TIDAK ADA tabel `divisi` atau `kelompok_insentif` di database.**
- Struktur organisasi hanya direpresentasikan melalui tabel `jabatan` (field: `ID_JABATAN`, `NAMA_JABATAN`, `ROLES_JABATAN`).
- Tabel `akun` punya field `ID_JABATAN` dan `ID_UNIT`, tetapi **TIDAK ADA field untuk divisi, departemen, atau kelompok insentif.**

---

## 2. Previous Incentive Structure

Berdasarkan deskripsi user:

```
Total Insentif Pool (100%)
├── Kepala Divisi Digital Marketing : 25%
├── IT                              : 25%
├── Multimedia                      : 25%
└── Customer Service                : 25%
```

**Pembagian: 100% / 4 orang = 25% per orang.**

---

## 3. New Organization Structure

```
Kepala Divisi Digital Marketing
├── IT
└── Multimedia
```

Customer Service sekarang独立, bukan Kepala Divisi, tetapi tetap harus mendapat bagian insentif dari kelompok yang sama.

**Target pembagian:**
```
Total Insentif Pool (100%)
├── Kepala Divisi Digital Marketing : 25%
├── IT                              : 25%
├── Multimedia                      : 25%
└── Customer Service                : 25%  ← TETAP MASIH DAPAT
```

---

## 4. Current Incentive Logic (Source Code)

### 4.1 Engine Insentif: `hitungKPIGaji()` di `PenilaianKPI.php:569-1221`

**Cara kerja: Insentif dihitung PER ORANG, berdasarkan ID_JABATAN.**

**TIDAK ADA konsep "pool" di code.** Setiap karyawan dihitung insentifnya sendiri-sendiri.

#### Pembagi Insentif

```php
// PenilaianKPI.php:843-845
$pembagiInsentifKepalaToko = ($context === 'gaji') ? 4 : 3;
$pembagiInsentifPengiklan  = ($context === 'gaji') ? 1 : 4;
```

| Context | Kepala Toko (41) | Pengiklan (43) | Lainnya |
|---------|-----------------|----------------|---------|
| `gaji` | /4 | /1 (full) | /4 |
| `penilaian_kinerja` | /3 | /4 | /3 |
| `slip_gaji` | /3 | /4 | /3 |

#### Formula Per Jabatan

| Jabatan | ID | Formula Insentif | Syarat |
|---------|----|-----------------|--------|
| Kepala Toko | 41 | `(3/100) × omset_unit / pembagi` | omset ≥ target_omset[unit] |
| SPV | 40 | `(5/1000) × omset_per_cabang` (per cabang aman) | omset_cabang ≥ target_omset[cabang] |
| Pengiklan | 43 | `(1/100) × omset_per_cabang / pembagi` (per cabang aman) | omset_cabang ≥ target_omset[cabang] |
| Admin | 35 | `(3/100) × omset_unit / 4` | omset ≥ target_omset[unit] |
| Teknisi | 36 | `(3/100) × omset_unit / 4` | omset ≥ target_omset[unit] |
| **Customer Service** | **42** | `(3/100) × omset_unit / 4` | omset ≥ target_omset[unit] |
| Multimedia | 44 | `(3/100) × omset_unit / 4` | omset ≥ target_omset[unit] |
| IT | 45 | `(3/100) × omset_unit / 4` | omset ≥ target_omset[unit] |
| PIC | 46 | `(3/100) × omset_unit / 4` | omset ≥ target_omset[unit] |

### 4.2 Logic Flow Insentif

```
hitungKPIGaji($idAkun, $bulan, $tahun, $context)
    │
    ├── Ambil data karyawan (jabatan, unit)
    │
    ├── Hitung omset aktual per unit (detail_penjualan)
    │
    ├── Hitung customer per unit (penjualan)
    │
    ├── Hitung HPP, tutup kasir, opname, dll
    │
    ├── HITUNG INSENTIF BERDASARKAN JABATAN:
    │   │
    │   ├── if jabatan == 41 (Kepala Toko)
    │   │   └── insentif = 3% × omset / 4  ← HANYA omset unit sendiri
    │   │
    │   ├── if jabatan == 40 (SPV)
    │   │   └── insentif += 0.5% × omset  ← SEMUA 4 cabang
    │   │
    │   ├── if jabatan == 43 (Pengiklan)
    │   │   └── insentif += 1% × omset / 4  ← SEMUA 4 cabang
    │   │
    │   └── else (Admin, Teknisi, CS, Multimedia, IT, PIC)
    │       └── insentif = 3% × omset / 4  ← HANYA omset unit sendiri
    │
    └── Total Gaji = Gaji Pokok + Tunjangan Kinerja + Tunjangan Absen
                     + Tunjangan Penempatan + INSENTIF
```

### 4.3 Kode Spesifik Insentif CS

```php
// PenilaianKPI.php:966-981 — FALLTHROUGH untuk CS dan lainnya
} else {
    if ($aktual_omset < $batas2) {
        $nilai_omset = 0;
    } elseif ($aktual_omset >= $batas2 && $aktual_omset < $batas3) {
        $nilai_omset = 33;
    } elseif ($aktual_omset >= $batas3 && $aktual_omset < $batas4) {
        $nilai_omset = 66;
    } elseif ($aktual_omset >= $batas4 && $aktual_omset < $targetOmset) {
        $nilai_omset = 100;
    } elseif ($aktual_omset >= $targetOmset) {
        $nilai_omset = 100;
        $insentif = (3 / 100) * $aktual_omset / $pembagiInsentifKepalaToko;
        //  ^^^ CS mendapat insentif DI SINI
    } else {
        $nilai_omset = (($aktual_omset - $batas1) / ($batas4 - $batas1)) * 100;
    }
}
```

**CS saat ini mendapat insentif dari branch `else` (bukan dari `if jabatan == 41/40/43`).**

---

## 5. Root Cause

### Mengapa Insentif Saat Ini BERBEDA dari Pool 25% × 4?

**Temuan kritis: Sistem saat ini TIDAK menggunakan mekanisme pool.**

Setiap karyawan mendapat insentif **secara terpisah dan independen**, bukan dari satu pool yang dibagi:

| Orang | Sistem Saat Ini | Pool Ideal |
|-------|-----------------|------------|
| Kepala Toko (41) | 3% × omset_unit / 4 | 25% dari total pool |
| SPV (40) | 0.5% × omset SEMUA cabang | (tidak ada di pool) |
| Pengiklan (43) | 1% × omset SEMUA cabang / 4 | (tidak ada di pool) |
| CS (42) | 3% × omset_unit / 4 | 25% dari total pool |
| IT (45) | 3% × omset_unit / 4 | 25% dari total pool |
| Multimedia (44) | 3% × omset_unit / 4 | 25% dari total pool |

**Masalah utama:** Angka "4" pada `/ 4` dalam `(3/100) × omset / 4` adalah **divisor per-orang**, bukan **pembagi pool**. Tiap orang dihitung secara terpisah dengan formula yang sama.

### Kenapa CS Bisa Kehilangan Insentif?

**Jawaban: Saat ini, CS TIDAK kehilangan insentif hanya karena role berubah.**

Insentif CS dihitung dari branch `else` pada `PenilaianKPI.php:966`. Branch ini dijalankan untuk SEMUA jabatan yang BUKAN 41, 40, atau 43. Jadi:

- CS dengan jabatan 42 → masuk branch `else` → dapat insentif ✅
- CS dengan jabatan apapun selain 41/40/43 → masuk branch `else` → dapat insentif ✅

**NAMUN,** ada dampak lain dari perubahan role:

1. **KPI Weight berubah** — CS (jabatan 42) punya KPI khusus: Omset 60-70%, Closing 10%, Upselling 10%, Follow Up 10%, Testimoni 10%. Jika jabatan berubah, KPI weight juga berubah.

2. **Tunjangan Kinerja berbeda** — CS mendapat 250.000 max (default), sedangkan jika menjadi Multimedia juga 250.000. Jika menjadi IT, juga 250.000. Tapi jika menjadi SPV, naik ke 1.250.000.

3. **TutupKasir.php** memiliki logika insentif yang sedikit BERBEDA (duplicated, tidak refactor). Ada risiko inkonsistensi.

---

## 6. Why Customer Service Loses Incentive (Analisis)

### Hipotesis: Apakah CS kehilangan insentif saat role berubah?

**Berdasarkan source code saat ini:**

**TIDAK, CS tidak kehilangan insentif hanya karena role berubah dari "Kepala Divisi" ke "Customer Service".**

Alasannya:

1. Insentif CS dihitung di branch `else` (line 966), bukan di `if jabatan == 41/40/43`.
2. Branch `else` mencakup SEMUA jabatan selain 41, 40, 43.
3. Tidak ada filter yang hanya mengizinkan "Kepala Divisi" untuk dapat insentif.

### Kemungkinan Penyebab Jika CS KEHILANGAN Insentif

Jika dalam praktiknya CS kehilangan insentif setelah perubahan role, kemungkinan penyebabnya:

| # | Kemungkinan | Evidence |
|---|------------|----------|
| 1 | **Data master `jabatan` berubah** — ID_JABATAN CS diubah dari 42 ke ID baru, dan ID baru tidak dikenal di switch | `PenilaianKPI.php` menggunakan hardcode `jabatan == 42` |
| 2 | **CS pindah ke unit yang omset-nya tidak mencapai target** | Insentif hanya didapat jika `omset >= targetOmset` |
| 3 | **Perubahan di `TutupKasir.php`** — code duplikat yang sedikit berbeda | `TutupKasir.php:1094-1123` |
| 4 | **Ada logic di luar `hitungKPIGaji`** yang memfilter penerima insentif berdasarkan struktur organisasi | TIDAK DITEMUKAN di source code |
| 5 | **Perubahan manual di database** — admin mengubah `ID_JABATAN` di tabel `akun` | Tidak terlihat dari code |

### Catatan Penting: Kode "4" pada `/ 4`

Angka 4 pada `/ 4` DIINTERPRETASIKAN oleh user sebagai:
> "4 orang berbagi insentif → 100%/4 = 25% per orang"

Tetapi dalam code, angka 4 adalah **divisor tetap**, bukan jumlah anggota pool yang dinamis. Artinya:

- Jika ada 3 orang → angka 4 tetap dipakai (bukan /3)
- Jika ada 5 orang → angka 4 tetap dipakai (bukan /5)
- Jika CS keluar → angka 4 tetap dipakai (bukan /3)

**Ini berarti "4" mungkin memang adalah jumlah orang yang berbagi, TETAPI hardcoded, bukan dihitung dari database.**

---

## 7. Required Business Rule

### Rule Bisnis Baru

```
Kelompok Insentif Digital Marketing:
├── Kepala Divisi Digital Marketing  → 25%
├── IT                               → 25%
├── Multimedia                       → 25%
└── Customer Service                 → 25%  ← TETAP VALID meskipun role berubah
```

### Persyaratan Desain

1. **Role ≠ Insentif** — Perubahan role tidak otomatis menghapus hak insentif
2. **Pembagian dinamis** — Jumlah anggota kelompok bisa berubah tanpa ubah code
3. **Konfigurasi di database** — Bukan hardcoded di PHP
4. **Audit trail** — Siapa yang dapat insentif, berapa persen, kapan berubah
5. **Skala** — Mendukung kelompok insentif lain jika ada di masa depan

---

## 8. Recommended System Design

### 8.1 Arsitektur Database Baru

```
┌─────────────────────┐
│   akun (pegawai)    │
│   ───────────────── │
│   ID_AKUN (PK)      │
│   NAMA_AKUN         │
│   ID_JABATAN (FK)   │──── jabatan
│   ID_UNIT (FK)      │──── unit
└────────┬────────────┘
         │
         │ 1:N (satu pegawai bisa di beberapa kelompok)
         ▼
┌──────────────────────────┐
│  employee_incentive_group │  ← TABLE BARU
│  ──────────────────────── │
│  id (PK)                  │
│  id_akun (FK)             │
│  id_incentive_group (FK)  │
│  share_pct (persentase)   │
│  status (active/inactive) │
│  start_date               │
│  end_date (nullable)      │
│  created_at               │
│  updated_at               │
└────────┬─────────────────┘
         │ N:1
         ▼
┌──────────────────────────┐
│   incentive_group         │  ← TABLE BARU
│   ──────────────────────  │
│   id (PK)                 │
│   nama_group              │
│   description             │
│   calculation_method      │  ← 'equal_split' | 'fixed_pct' | 'custom'
│   base_percentage         │  ← persentase dasar dari total omset
│   status                  │
│   created_at              │
│   updated_at              │
└──────────────────────────┘
```

### 8.2 Data Awal

```sql
-- Kelompok Insentif Digital Marketing
INSERT INTO incentive_group (nama_group, description, calculation_method, base_percentage)
VALUES ('Digital Marketing', 'Kelompok insentif divisi Digital Marketing', 'equal_split', 3);

-- Anggota kelompok (4 orang, masing-masing 25%)
INSERT INTO employee_incentive_group (id_akun, id_incentive_group, share_pct, status)
VALUES
  (id_kepala_dm, 1, 25.00, 'active'),   -- Kepala Divisi DM
  (id_it,        1, 25.00, 'active'),   -- IT
  (id_mm,        1, 25.00, 'active'),   -- Multimedia
  (id_cs,        1, 25.00, 'active');   -- Customer Service ← TETAP MASIH 25%
```

### 8.3 Logic Hitung Insentif Baru

```
hitungKPIGaji($idAkun, $bulan, $tahun, $context)
    │
    ├── Ambil data karyawan (jabatan, unit)
    │
    ├── Hitung omset aktual per unit
    │
    ├── CEK: Apakah karyawan ada di employee_incentive_group?
    │   │
    │   ├── YA → Hitung total insentif kelompok
    │   │         ├── total_insentif = 3% × total_omset_kelompok
    │   │         └── insentif_orang = total_insentif × (share_pct / 100)
    │   │
    │   └── TIDAK → hitung insentif individual (formula lama)
    │
    └── Total Gaji = Gaji Pokok + Tunjangan Kinerja + Tunjangan Absen
                     + Tunjangan Penempatan + INSENTIF
```

### 8.4 Mengapa Desain Ini Lebih Baik

| Aspek | Desain Lama | Desain Baru |
|-------|-------------|-------------|
| Penerima insentif | Hardcoded di `if ($jabatan == 41/40/43/else)` | Dinamis dari database |
| Jumlah anggota | Hardcoded `/4` | Dihitung dari COUNT anggota aktif |
| Persentase per orang | Selalu 25% (100%/4) | Bervariasi (25%, 20%, 30%, dll) |
| Perubahan role | Role berubah → insentif ikut berubah | Role berubah → insentif TIDAK berubah |
| Penambahan anggota | Ubah code PHP | INSERT ke database |
| Pengurangan anggota | Ubah code PHP | UPDATE status di database |
| Audit trail | Tidak ada | Ada (start_date, end_date, status) |
| Multi-kelompok | Tidak bisa | Bisa (CS di kelompok DM + kelompok lain) |

---

## 9. Database Impact

### Table Baru

| Table | Purpose |
|-------|---------|
| `incentive_group` | Daftar kelompok insentif |
| `employee_incentive_group` | Anggota kelompok + persentase |

### Table yang TIDAK berubah

| Table | Alasan |
|-------|--------|
| `jabatan` | Role/jabatan tetap ada, tetapi tidak menentukan insentif |
| `akun` | Data karyawan tetap, tidak perlu tambah field |
| `penilaian_kpi` | KPI scoring tetap sama |
| `penilaian` | Checklist tetap sama |
| `presensi` | Absensi tetap sama |

### Migrasi Data

```sql
-- 1. Buat tabel baru
CREATE TABLE incentive_group (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_group VARCHAR(100) NOT NULL,
    description TEXT,
    calculation_method ENUM('equal_split','fixed_pct','custom') DEFAULT 'equal_split',
    base_percentage DECIMAL(5,2) DEFAULT 3.00,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE employee_incentive_group (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_akun INT NOT NULL,
    id_incentive_group INT NOT NULL,
    share_pct DECIMAL(5,2) NOT NULL DEFAULT 25.00,
    status ENUM('active','inactive') DEFAULT 'active',
    start_date DATE NOT NULL,
    end_date DATE NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_akun) REFERENCES akun(ID_AKUN),
    FOREIGN KEY (id_incentive_group) REFERENCES incentive_group(id)
);

-- 2. Insert kelompok Digital Marketing
INSERT INTO incentive_group (nama_group, description, calculation_method, base_percentage)
VALUES ('Digital Marketing', 'Kelompok insentif divisi Digital Marketing', 'equal_split', 3);

-- 3. Insert anggota (4 orang × 25%)
-- Ganti ID_AKUN dengan ID aktual di database
INSERT INTO employee_incentive_group (id_akun, id_incentive_group, share_pct, status, start_date)
VALUES
  (/* ID Kepala DM */, 1, 25.00, 'active', CURDATE()),
  (/* ID IT */,        1, 25.00, 'active', CURDATE()),
  (/* ID MM */,        1, 25.00, 'active', CURDATE()),
  (/* ID CS */,        1, 25.00, 'active', CURDATE());
```

---

## 10. Code Impact

### File yang Perlu Diubah

| File | Perubahan |
|------|-----------|
| `PenilaianKPI.php` | Modifikasi `hitungKPIGaji()` — tambah logic insentif berbasis kelompok |
| `TutupKasir.php` | Sama — modifikasi `assetberjalan()` (jika masih dipakai) |
| Views (gaji, penilaian_kinerja, slip_gaji) | Tampilkan detail insentif: nama kelompok, persentase, total pool |

### File yang TIDAK berubah

| File | Alasan |
|------|--------|
| `KeyPerformance.php` | Layer 2 KPI tidak ada hubungan dengan insentif |
| `Penilaian.php` | Checklist tidak terpengaruh |
| `SummaryKPI.php` | Summary view tidak terpengaruh |
| `DashboardHRD.php` | Dashboard HRD tidak menampilkan insentif |
| `ModelPresensi.php` | Absensi tidak terpengaruh |
| `ModelPenilaianKPI.php` | Data storage tidak berubah |

### Contoh Perubahan Logic (Pseudocode)

```php
// SEBELUM (hardcoded):
if ($jabatan == 41) {
    $insentif = (3 / 100) * $aktual_omset / 4;
} elseif ($jabatan == 40) {
    foreach ($aktual_omset_unit as $omset) {
        if ($omset >= $target) $insentif += (5/1000) * $omset;
    }
} elseif ($jabatan == 43) {
    foreach ($aktual_omset_unit as $omset) {
        if ($omset >= $target) $insentif += (1/100) * $omset / 4;
    }
} else {
    $insentif = (3 / 100) * $aktual_omset / 4;
}

// SESUDAH (dinamis dari database):
$insentifGroups = $this->db->table('employee_incentive_group')
    ->join('incentive_group', 'incentive_group.id = employee_incentive_group.id_incentive_group')
    ->where('id_akun', $idAkun)
    ->where('status', 'active')
    ->get()
    ->getResult();

$insentif = 0;
if (!empty($insentifGroups)) {
    foreach ($insentifGroups as $group) {
        $totalOmsetKelompok = $this->hitungTotalOmsetKelompok($group->id_incentive_group);
        $insentif += ($group->base_percentage / 100) * $totalOmsetKelompok * ($group->share_pct / 100);
    }
} else {
    // Fallback ke formula lama untuk jabatan yang tidak di kelompok manapun
    $insentif = (3 / 100) * $aktual_omset / 4;
}
```

---

## 11. KPI / Payroll Impact

### KPI TIDAK Terpengaruh

| Aspek | Dampak |
|-------|--------|
| KPI Weight per jabatan | Tidak berubah — CS tetap punya Omset 60%, Closing 10%, dst |
| KPI Score calculation | Tidak berubah — formula `(realisasi/target) × bobot` sama |
| Tunjangan Kinerja | Tidak berubah — berdasarkan skor KPI × max tunjangan |
| Tunjangan Absen | Tidak berubah — berdasarkan skor kehadiran |
| Ranking (Platinum/Gold/Silver/Bronze) | Tidak berubah |

### Payroll TERPENGARUH

| Aspek | Dampak |
|-------|--------|
| **Insentif** | **BERUBAH** — dari formula hardcoded ke database-driven |
| Gaji Pokok | Tidak berubah — fix Rp 1.500.000 |
| Tunjangan Penempatan | Tidak berubah — berdasarkan alamat vs unit |

### Dampak pada Slip Gaji

Slip gaji (`slip_gaji.php`) perlu menambahkan:
- Nama kelompok insentif
- Persentase share
- Total pool insentif

---

## 12. Simulation Before vs After

### Skenario

```
Total Omset Unit: Rp 60.000.000 (mencapai target)
Jumlah anggota kelompok: 4 orang

Anggota:
1. Kepala Divisi DM  — share 25%
2. IT                — share 25%
3. Multimedia        — share 25%
4. Customer Service  — share 25%
```

### SEBELUM (Formula Hardcoded)

```
Tiap orang dihitung TERPISAH:
- Kepala DM (jabatan 41): insentif = 3% × Rp 60jt / 4 = Rp 450.000
- IT (jabatan 45):        insentif = 3% × Rp 60jt / 4 = Rp 450.000
- MM (jabatan 44):        insentif = 3% × Rp 60jt / 4 = Rp 450.000
- CS (jabatan 42):        insentif = 3% × Rp 60jt / 4 = Rp 450.000

Total insentif keluar: Rp 1.800.000 (dari omset Rp 60jt)
Efektif: 3% × Rp 60jt = Rp 1.800.000 (dibagi 4 = Rp 450.000/orang)
```

### SESUDAH (Database-Driven Pool)

```
Total pool = 3% × Rp 60jt = Rp 1.800.000
Tiap orang: Rp 1.800.000 × 25% = Rp 450.000

- Kepala DM:  Rp 450.000
- IT:         Rp 450.000
- MM:         Rp 450.000
- CS:         Rp 450.000

Total: Rp 1.800.000 ✅ SAMA
```

### Skenario: CS Pindah Role (Tanpa Pool)

```
SEBELUM POOL:
- CS jabatan 42 → 3% × Rp 60jt / 4 = Rp 450.000

SESUDAH POOL:
- CS pindah ke jabatan baru (misal ID 47)
- Di branch else → 3% × Rp 60jt / 4 = Rp 450.000 ← MASIH SAMA

Karena branch 'else' mencakup semua jabatan selain 41/40/43,
CS tetap dapat insentif meskipun jabatan berubah.
```

### Skenario: CS Tambah ke Kelompok Lain

```
CS di 2 kelompok:
1. Digital Marketing (25%)
2. Customer Service Incentive (30%)

Total insentif CS = (3% × DM_omset × 25%) + (2% × CS_omset × 30%)
```

---

## 13. Implementation Plan

### Phase 1: Database Migration (Tanpa Ubah Logic)

| Langkah | Aksi | Risk |
|---------|------|------|
| 1 | Buat tabel `incentive_group` | Low — tabel baru |
| 2 | Buat tabel `employee_incentive_group` | Low — tabel baru |
| 3 | Insert data awal (kelompok DM + 4 anggota) | Low — data seeding |
| 4 | **VALIDASI**: Pastikan 4 orang terdaftar dengan share 25% masing-masing | Critical |

### Phase 2: Logic Engine

| Langkah | Aksi | Risk |
|---------|------|------|
| 5 | Modifikasi `hitungKPIGaji()` — tambah query ke `employee_incentive_group` | High — mengubah logic gaji |
| 6 | Tambah fallback ke formula lama untuk yang tidak di kelompok | Medium |
| 7 | **TEST**: Hitung gaji 4 orang → pastikan hasilnya SAMA dengan formula lama | Critical |
| 8 | **TEST**: Hitung gaji orang NON-kelompok → pastikan tidak berubah | Critical |

### Phase 3: UI & Reporting

| Langkah | Aksi | Risk |
|---------|------|------|
| 9 | Tambah halaman manajemen `incentive_group` | Low |
| 10 | Tambah halaman manajemen anggota kelompok | Low |
| 11 | Update slip gaji → tampilkan detail insentif | Low |
| 12 | Update halaman gaji/penilaian_kinerja → tampilkan kelompok | Low |

### Phase 4: Migration & Validation

| Langkah | Aksi | Risk |
|---------|------|------|
| 13 | Jalankan di staging environment | Medium |
| 14 | Validasi semua karyawan → pastikan tidak ada yang hilang insentifnya | Critical |
| 15 | Deploy ke production | High |

---

## 14. Risk / Side Effects

### Risiko Tinggi

| # | Risiko | Dampak | Mitigasi |
|---|--------|--------|----------|
| 1 | **Query ke employee_incentive_group gagal** | Insentif = 0 untuk semua | Tambah fallback ke formula lama |
| 2 | **Data tidak lengkap** — anggota tidak terdaftar | Karyawan tidak dapat insentif | Validasi data sebelum deploy |
| 3 | **Share percentage tidak = 100%** | Total pool terlalu besar/kecil | Validasi di database (CHECK constraint) |
| 4 | **2 tabel baru menambah complexity** | Maintenance lebih rumit | Dokumentasi yang baik |

### Risiko Sedang

| # | Risiko | Dampak | Mitigasi |
|---|--------|--------|----------|
| 5 | **TutupKasir.php** (code duplikat) tidak ikut diubah | Insetif berbeda di 2 halaman | Refactor atau tandai sebagai deprecated |
| 6 | **SPV (40) dan Pengiklan (43)** punya formula berbeda | Perlu keputusan: tetap formula lama atau pindah ke pool | Diskusi dengan stakeholder |
| 7 | **Karyawan baru** tidak otomatis masuk pool | Perlu input manual | Buat UI yang user-friendly |

### Risiko Rendah

| # | Risiko | Dampak | Mitigasi |
|---|--------|--------|----------|
| 8 | Performa query bertambah | Negligible untuk data kecil | Index pada foreign key |
| 9 | User tidak familiar dengan UI baru | Training diperlukan | Buat UI intuitif |

---

## 15. Final Recommendation

### Kesimpulan Utama

1. **Sistem saat ini BUKAN pool-based** — tiap karyawan dihitung insentifnya sendiri berdasarkan jabatan hardcoded.

2. **Angka `/ 4` pada `(3/100) × omset / 4` BUKAN pembagi pool** — melainkan divisor tetap yang mungkin merepresentasikan 4 orang yang berbagi, tetapi tidak dihitung dari database.

3. **CS saat ini MASIH mendapat insentif** dari branch `else` di `PenilaianKPI.php:966-981`. Perubahan role tidak otomatis menghilangkan hak CS.

4. **Namun, arsitektur saat ini rapuh** — jika ada perubahan jabatan, penambahan anggota, atau perubahan persentase, semua harus ubah code PHP.

### Rekomendasi

| Prioritas | Aksi |
|-----------|------|
| **P0** | **JANGAN ubah code dulu** — buat tabel `incentive_group` dan `employee_incentive_group` di database |
| **P0** | Validasi: CS dengan jabatan baru MASIH masuk branch `else` → MASIH dapat insentif |
| **P1** | Buat migration SQL untuk membuat 2 tabel baru + seed data |
| **P1** | Modifikasi `hitungKPIGaji()` — query ke tabel baru, fallback ke formula lama |
| **P2** | Buat CRUD untuk manajemen kelompok insentif |
| **P2** | Update slip gaji dan halaman gaji |
| **P3** | Refactor `TutupKasir.php` — hapus duplikasi, gunakan `hitungKPIGaji()` tunggal |

### Yang TIDAK BOLEH dilakukan

- **JANGAN** sekadar mengubah nama jabatan CS dari "Kepala Divisi" ke "Customer Service" tanpa memastikan insentif tetap ada
- **JANGAN** hardcoded jumlah anggota pool (misal `/ 4`) — harus dihitung dari database
- **JANGAN** menghapus branch `else` di `hitungKPIGaji()` — itu adalah fallback untuk semua jabatan selain 41/40/43

### Yang BOLEH dilakukan

- **BOLEH** mengubah jabatan CS — asalkan tetap ada di branch `else` atau di tabel `employee_incentive_group`
- **BOLEH** menambah anggota kelompok — asalkan INSERT ke database, bukan ubah code
- **BOLEH** mengubah persentase per orang — asalkan UPDATE di database

---

## Lampiran: File dan Lokasi Kode Kritis

| File | Baris | Fungsi | Keterangan |
|------|-------|--------|------------|
| `app/Controllers/PenilaianKPI.php` | 569-1221 | `hitungKPIGaji()` | Engine utama gaji + insentif |
| `app/Controllers/PenilaianKPI.php` | 843-845 | `$pembagiInsentif*` | Definisi divisor insentif |
| `app/Controllers/PenilaianKPI.php` | 847-861 | `if jabatan == 41` | Kepala Toko insentif |
| `app/Controllers/PenilaianKPI.php` | 862-915 | `if jabatan == 40` | SPV insentif |
| `app/Controllers/PenilaianKPI.php` | 916-965 | `if jabatan == 43` | Pengiklan insentif |
| `app/Controllers/PenilaianKPI.php` | 966-981 | `else` | **CS dan lainnya insentif** |
| `app/Controllers/PenilaianKPI.php` | 1203 | `$gaji = ...` | Total gaji termasuk insentif |
| `app/Controllers/TutupKasir.php` | 994-1123 | (duplikat) | Code insentif lama (duplikat) |
| `app/Views/penilaian/gaji.php` | 118-121 | tampilan insentif | Card insentif di halaman gaji |
| `app/Views/penilaian/penilaian_kinerja.php` | 231-234 | tampilan insentif | Card insentif di penilaian kinerja |
| `app/Views/cetak/slip_gaji.php` | 228-231 | cetak insentif | Baris insentif di slip gaji |
| `app/Models/ModelJabatan.php` | 1-19 | `jabatan` table | Struktur tabel jabatan |
| `app/Models/ModelUnit.php` | 1-49 | `unit` table | Struktur tabel unit |
