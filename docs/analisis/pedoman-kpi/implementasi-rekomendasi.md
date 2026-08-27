# REKOMENDASI IMPLEMENTASI — MENYESUAIKAN CODE DENGAN PEDOMAN

---

## 1. Prioritas Perubahan

### P0 — KRITIS (Harus segera)

| # | Perubahan | Alasan | File Terdampak |
|---|-----------|--------|----------------|
| 1 | **Buang jabatan CS (42), Pengiklan (43), PIC (46) dari KPI** | Tidak ada di pedoman resmi | `PenilaianKPI.php`, `KeyPerformance.php`, `template_kpi.php` |
| 2 | **Buat table `master_kompensasi`** | Pedoman Phase 4 Section IV: "Jangan menanam angka gaji langsung di source code" | New table + migration |
| 3 | **Buat table `master_kpi`** | Setiap jabatan harus punya komponen KPI + bobot dari DB | New table + migration |
| 4 | **Fix bobot KPI Kepala Toko** | Omzet 30% (bukan 70%) | `PenilaianKPI.php:1079-1085` |
| 5 | **Fix bobot KPI SPV** | Omzet 50% (bukan 70%) | `PenilaianKPI.php:1088-1096` |
| 6 | **Fix bobot KPI IT** | 0% omzet (bukan 30%) | `PenilaianKPI.php:1152-1159` |
| 7 | **Fix bobot KPI Multimedia** | 0% omzet (bukan 30%) | `PenilaianKPI.php:1141-1149` |
| 8 | **Fix bobot KPI Admin** | 0% omzet, 8 komponen non-omzet | `PenilaianKPI.php:1062-1068` |
| 9 | **Fix bobot KPI Teknisi** | 0% omzet, 10 komponen non-omzet | `PenilaianKPI.php:1071-1076` |
| 10 | **Fix Maks. Tunjangan Kinerja** | Sesuai tabel pedoman Section XVIII | `PenilaianKPI.php:1186-1200` |

### P1 — PENTING

| # | Perubahan | Alasan |
|---|-----------|--------|
| 11 | **Rumus Achievement proporsional** | `(Realisasi - BatasMinimal) / (Target - BatasMinimal) × 100%` |
| 12 | **Cap KPI Akhir ≤ 100%** | Pedoman Section VIII |
| 13 | **Ubah kategori** dari Platinum/Gold/Silver/Bronze → Sangat Baik/Baik/Cukup/Kurang/Kritis | Sesuai pedoman Section XX |
| 14 | **Target HO = Rp 250jt**, per cabang: 65/45/65/75 | Sesuai pedoman Section V |
| 15 | **Tambah batas minimal** = Target − Rp 15jt per cabang | Sesuai pedoman Section VI |
| 16 | **Tambah Target Team Cabang per jabatan** | Kepala Toko pakai Target Team, bukan Target HO |

### P2 — IMPROVEMENT

| # | Perubahan | Alasan |
|---|-----------|--------|
| 17 | **Buang hardcoded `$pembagiInsentif*`** | Buat di Master Kompensasi |
| 18 | **Refactor TutupKasir.php** | Hapus duplikasi, pakai `hitungKPIGaji()` tunggal |
| 19 | **Buat halaman manajemen KPI** | CRUD Master KPI per jabatan |
| 20 | **Buat halaman manajemen kompensasi** | CRUD Master Kompensasi per jabatan |

---

## 2. Master KPI — Struktur yang Direkomendasikan

```sql
CREATE TABLE master_kpi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jabatan_idjabatan INT NOT NULL,
    nama_indikator VARCHAR(100) NOT NULL,
    bobot DECIMAL(5,2) NOT NULL,  -- persentase
    sumber_data VARCHAR(100),       -- 'penjualan', 'presensi', 'penilaian', 'tugas'
    rumus TEXT,                      -- deskripsi rumus
    satuan VARCHAR(20),
    is_omzet BOOLEAN DEFAULT FALSE,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (jabatan_idjabatan) REFERENCES jabatan(ID_JABATAN),
    CHECK (bobot >= 0 AND bobot <= 100)
);
```

### Data Awal — Kepala Toko (ID 41)

```sql
INSERT INTO master_kpi (jabatan_idjabatan, nama_indikator, bobot, sumber_data, is_omzet)
VALUES
(41, 'Omzet Cabang', 30, 'penjualan', TRUE),
(41, 'Customer & Transaksi', 15, 'penjualan', FALSE),
(41, 'Conversion', 10, 'penjualan', FALSE),
(41, 'Operational Compliance', 15, 'tugas', FALSE),
(41, 'Team Productivity', 10, 'presensi', FALSE),
(41, 'Customer Satisfaction', 10, 'penilaian', FALSE),
(41, 'Stock & Asset Control', 5, 'stok_opname_draft', FALSE),
(41, 'Reporting', 5, 'tugas', FALSE);
```

### Data Awal — IT (ID 45)

```sql
INSERT INTO master_kpi (jabatan_idjabatan, nama_indikator, bobot, sumber_data, is_omzet)
VALUES
(45, 'System Uptime', 20, 'monitoring', FALSE),
(45, 'Penyelesaian Bug', 20, 'tugas', FALSE),
(45, 'Response Time', 15, 'tugas', FALSE),
(45, 'Security & Backup', 15, 'monitoring', FALSE),
(45, 'Development', 15, 'tugas', FALSE),
(45, 'User Support', 10, 'tugas', FALSE),
(45, 'Dokumentasi', 5, 'tugas', FALSE);
```

### Data Awal — Multimedia (ID 44)

```sql
INSERT INTO master_kpi (jabatan_idjabatan, nama_indikator, bobot, sumber_data, is_omzet)
VALUES
(44, 'Ketepatan Deadline', 25, 'tugas', FALSE),
(44, 'Kualitas Output', 25, 'penilaian', FALSE),
(44, 'Kesesuaian Brief', 20, 'penilaian', FALSE),
(44, 'Produktivitas', 15, 'tugas', FALSE),
(44, 'Support Campaign', 10, 'tugas', FALSE),
(44, 'Improvement', 5, 'tugas', FALSE);
```

### Data Awal — Admin (ID 35)

```sql
INSERT INTO master_kpi (jabatan_idjabatan, nama_indikator, bobot, sumber_data, is_omzet)
VALUES
(35, 'Akurasi Transaksi', 15, 'penjualan', FALSE),
(35, 'Kelengkapan Administrasi', 15, 'tugas', FALSE),
(35, 'Akurasi Invoice', 10, 'penjualan', FALSE),
(35, 'Kelengkapan Data Customer', 10, 'pelanggan', FALSE),
(35, 'Pelayanan Customer', 10, 'penilaian', FALSE),
(35, 'Follow-up', 10, 'tugas', FALSE),
(35, 'Disiplin', 15, 'presensi', FALSE),
(35, 'Tingkat Kesalahan Administrasi', 5, 'tugas', FALSE);
```

### Data Awal — Teknisi (ID 36)

```sql
INSERT INTO master_kpi (jabatan_idjabatan, nama_indikator, bobot, sumber_data, is_omzet)
VALUES
(36, 'Produktivitas Service', 15, 'service', FALSE),
(36, 'Jumlah Pekerjaan', 15, 'service', FALSE),
(36, 'Kecepatan Service', 10, 'service', FALSE),
(36, 'Kualitas Hasil Service', 10, 'penilaian', FALSE),
(36, 'Kepatuhan SOP', 10, 'penilaian', FALSE),
(36, 'Error Rate', 10, 'service', FALSE),
(36, 'Rework', 5, 'service', FALSE),
(36, 'Komplain Customer', 5, 'komplain', FALSE),
(36, 'Garansi', 5, 'service', FALSE),
(36, 'Disiplin Kerja', 5, 'presensi', FALSE);
```

---

## 3. Master Kompensasi — Struktur yang Direkomendasikan

```sql
CREATE TABLE master_kompensasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jabatan_idjabatan INT NOT NULL,
    gaji_pokok DECIMAL(12,2) NOT NULL,
    tunjangan_absensi DECIMAL(12,2) NOT NULL,
    maks_tunjangan_kinerja DECIMAL(12,2) NOT NULL,
    tunjangan_penempatan DECIMAL(12,2) DEFAULT 350000,
    efektif_mulai DATE NOT NULL,
    efektif_sampai DATE NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (jabatan_idjabatan) REFERENCES jabatan(ID_JABATAN)
);
```

### Data Awal

```sql
INSERT INTO master_kompensasi (jabatan_idjabatan, gaji_pokok, tunjangan_absensi, maks_tunjangan_kinerja, efektif_mulai)
VALUES
(1,  1500000, 250000, 2250000, '2026-01-01'),  -- Manager
(3,  1500000, 250000, 1500000, '2026-01-01'),  -- Kepala Digital Marketing
(4,  1500000, 250000, 750000,  '2026-01-01'),  -- IT
(5,  1500000, 250000, 750000,  '2026-01-01'),  -- Multimedia
(6,  1500000, 250000, 1500000, '2026-01-01'),  -- SPV
(7,  1500000, 250000, 1000000, '2026-01-01'),  -- Kepala Toko
(35, 1500000, 250000, 750000,  '2026-01-01'),  -- Admin
(36, 1500000, 250000, 500000,  '2026-01-01'),  -- Teknisi
```

---

## 4. Rumus Achievement Proporsional

### Pedoman (Section VII)

```
Achievement = (Realisasi − Batas Minimal) ÷ (Target − Batas Minimal) × 100%
```

Contoh:
```
Target = Rp 65 jt
Batas Minimal = Rp 50 jt
Realisasi = Rp 57.5 jt

Achievement = (57.5 - 50) / (65 - 50) × 100% = 50%
```

### Code Saat Ini (Hardcoded)

```php
if ($aktual_omset < $batas2) $nilai_omset = 0;
elseif ($aktual_omset >= $batas2 && $aktual_omset < $batas3) $nilai_omset = 33;
elseif ($aktual_omset >= $batas3 && $aktual_omset < $batas4) $nilai_omset = 66;
```

### Perubahan yang Diperlukan

```php
// SEBELUM (hardcoded):
$nilai_omset = 33; // hanya untuk tepat $batas2

// SESUDAH (proporsional):
$batas_minimal = $batas_awal[$unit]; // Target - 15jt
$target = $target_omset[$unit];
if ($aktual_omset <= $batas_minimal) {
    $nilai_omset = 0;
} elseif ($aktual_omset >= $target) {
    $nilai_omset = 100;
} else {
    $nilai_omset = (($aktual_omset - $batas_minimal) / ($target - $batas_minimal)) * 100;
}
$nilai_omset = min($nilai_omset, 100); // Cap di 100
```

---

## 5. Perubahan Kategori KPI

### SEBELUM (di view)

```php
if ($total >= 90) rank = "Platinum";
else if ($total >= 80) rank = "Gold";
else if ($total >= 70) rank = "Silver";
else rank = "Bronze";
```

### SESUDAH (sesuai pedoman Section XX)

```php
if ($kpiAkhir >= 100) $kategori = "Sangat Baik";
elseif ($kpiAkhir >= 90) $kategori = "Baik";
elseif ($kpiAkhir >= 80) $kategori = "Cukup";
elseif ($kpiAkhir >= 65) $kategori = "Kurang";
else $kategori = "Kritis";
```

---

## 6. Target yang Benar

### SEBELUM (hardcoded di code)

```php
// context 'gaji':
$batas_awal    = [1 => 30000000, 2 => 18000000, 3 => 40000000, 4 => 18000000];
$batas_kedua   = [1 => 35000000, 2 => 22000000, 3 => 45000000, 4 => 22000000];
$batas_ketiga  = [1 => 40000000, 2 => 26000000, 3 => 50000000, 4 => 26000000];
$batas_keempat = [1 => 45000000, 2 => 30000000, 3 => 55000000, 4 => 30000000];
$target_omset  = [1 => 50000000, 2 => 35000000, 3 => 60000000, 4 => 35000000];
```

### SESUDAH (sesuai pedoman)

```php
// Target HO (untuk Manager, Financing, Kepala DM, SPV):
$target_ho = [1 => 65000000, 2 => 45000000, 3 => 65000000, 4 => 75000000];

// Target Team Cabang (untuk Kepala Toko, Admin, Teknisi):
$target_team = [1 => 50000000, 2 => 35000000, 3 => 55000000, 4 => 60000000];

// Batas minimal = Target - 15jt
$batas_minimal_ho   = [1 => 50000000, 2 => 30000000, 3 => 50000000, 4 => 60000000];
$batas_minimal_team = [1 => 35000000, 2 => 20000000, 3 => 40000000, 4 => 45000000];
```

---

## 7. Maks. Tunjangan Kinerja yang Benar

### SEBELUM (hardcoded di code)

```php
if ($jabatan == 41) $tunjangan_kinerja = $skor_total / 100 * 850000;
elseif ($jabatan == 40) $tunjangan_kinerja = $skor_total / 100 * 1250000;
elseif ($jabatan == 43) $tunjangan_kinerja = $skor_total / 100 * 1000000;
elseif ($jabatan == 35) {
    $tunjangan_kinerja = ($unit == 1) ? $skor_total / 100 * 850000 : $skor_total / 100 * 250000;
} else $tunjangan_kinerja = $skor_total / 100 * 250000;
```

### SESUDAH (dari Master Kompensasi)

```php
$komponen = $this->db->table('master_kompensasi')
    ->where('jabatan_idjabatan', $jabatan)
    ->where('status', 'active')
    ->where('efektif_mulai <=', date('Y-m-d'))
    ->orderBy('efektif_mulai', 'DESC')
    ->get()
    ->getRow();

$tunjangan_kinerja = ($skor_total / 100) * $komponen->maks_tunjangan_kinerja;
```

---

## 8. Implementation Plan

### Phase A — Database (2 hari)

| Langkah | Aksi | File |
|---------|------|------|
| A1 | Buat migration `master_kompensasi` | New migration |
| A2 | Buat migration `master_kpi` | New migration |
| A3 | Buat migration `incentive_group` + `employee_incentive_group` | New migration |
| A4 | Seed data Master Kompensasi (9 jabatan) | Seed |
| A5 | Seed data Master KPI (per jabatan) | Seed |
| A6 | Seed data Incentive Group | Seed |

### Phase B — Core Engine (5 hari)

| Langkah | Aksi | File |
|---------|------|------|
| B1 | Modifikasi `hitungKPIGaji()` — baca dari `master_kompensasi` | `PenilaianKPI.php` |
| B2 | Modifikasi `hitungKPIGaji()` — baca bobot dari `master_kpi` | `PenilaianKPI.php` |
| B3 | Implementasi rumus Achievement proporsional | `PenilaianKPI.php` |
| B4 | Cap KPI Akhir ≤ 100% | `PenilaianKPI.php` |
| B5 | Modifikasi insentif — baca dari `employee_incentive_group` | `PenilaianKPI.php` |
| B6 | Hapus cs/pengiklan/pic dari switch-case | `PenilaianKPI.php` |

### Phase C — Views (3 hari)

| Langkah | Aksi | File |
|---------|------|------|
| C1 | Update badge kategori | `penilaian_kinerja.php`, `gaji.php` |
| C2 | Update rank kategori | `penilaian_kpi.php`, `key_performance.php` |
| C3 | Update slip gaji | `slip_gaji.php` |

### Phase D — Management UI (5 hari)

| Langkah | Aksi |
|---------|------|
| D1 | CRUD Master KPI per jabatan |
| D2 | CRUD Master Kompensasi |
| D3 | CRUD Kelompok Insentif |
| D4 | CRUD Anggota Kelompok Insentif |

### Phase E — Testing & Deploy (3 hari)

| Langkah | Aksi |
|---------|------|
| E1 | Test semua jabatan — validasi bobot = 100% |
| E2 | Test tunjangan kinerja — validasi sesuai pedoman |
| E3 | Test Achievement proporsional |
| E4 | Test insentif kelompok |
| E5 | Deploy ke production |

**Estimasi total: 20 hari kerja**

---

## 9. Risk Assessment

| Risk | Probabilitas | Dampak | Mitigasi |
|------|-------------|--------|----------|
| Bobot KPI salah setelah diubah | Medium | High | Validasi total bobot = 100% di DB |
| Tunjangan kinerja berbeda dari sebelumnya | High | High | Run parallel 1 bulan, bandingkan hasil |
| User tidak terbiasa dengan UI baru | Medium | Medium | Training + dokumentasi |
| Data lama tidak migrate | Low | High | Script migrasi + backup |
| Performance query bertambah | Low | Low | Index optimization |

---

## 10. Kesimpulan

### Yang harus berubah:

1. **Bobot KPI** — semua jabatan harus sesuai pedoman resmi
2. **Target** — harus pakai Target HO Rp 250jt dan Target Team Cabang Rp 200jt
3. **Rumus Achievement** — harus proporsional, bukan hardcoded
4. **Tunjangan Kinerja** — harus dari Master Kompensasi di database
5. **Jabatan** — buang CS, Pengiklan, PIC dari KPI (atau minta Management update pedoman)
6. **Kategori** — Platinum/Gold/Silver/Bronze → Sangat Baik/Baik/Cukup/Kurang/Kritis
7. **Insentif** — pindah ke tabel `employee_incentive_group`

### Yang sudah benar:

1. Gaji Pokok Rp 1.500.000 ✅
2. Tunjangan Absensi Rp 250.000 ✅
3. Total bobot = 100% per jabatan ✅
4. Rumus `Tunjangan Kinerja = KPI Akhir × Maks Tunjangan` ✅
