# GAP ANALISIS — PEDOMAN RESMI vs KODE SOURCE CODE

> **Tujuan:** Membandingkan bobot KPI, target, dan struktur gaji antara pedoman resmi (PDF) dengan implementasi di source code.

---

## 1. GAP JABATAN

### Jabatan di Pedoman (9 jabatan)

| # | Jabatan | Kategori |
|---|---------|----------|
| 1 | Manager | HO |
| 2 | Financing | HO |
| 3 | Kepala Digital Marketing | HO |
| 4 | IT | HO |
| 5 | Multimedia | HO |
| 6 | SPV | HO |
| 7 | Kepala Toko | Team Cabang |
| 8 | Admin | Team Cabang |
| 9 | Teknisi | Team Cabang |

### Jabatan di Code (10+ jabatan)

| # | Jabatan | ID_JABATAN | Ada di Pedoman? |
|---|---------|-----------|----------------|
| 1 | Manager | 1 (admin utama) | ⚠️ Tidak diproses KPI |
| 2 | Direktur/CEO | 2 (superadmin) | ❌ Tidak ada KPI |
| 3 | Admin Toko | 35 | ✅ |
| 4 | Teknisi | 36 | ✅ |
| 5 | SPV | 40 | ✅ |
| 6 | Kepala Toko | 41 | ✅ |
| 7 | Customer Service | 42 | ❌ TIDAK ADA DI PEDOMAN |
| 8 | Pengiklan | 43 | ❌ TIDAK ADA DI PEDOMAN |
| 9 | Multimedia | 44 | ✅ |
| 10 | IT | 45 | ✅ |
| 11 | PIC | 46 | ❌ TIDAK ADA DI PEDOMAN |

**Selisih: 3 jabatan di code tidak ada di pedoman (CS, Pengiklan, PIC).**
**2 jabatan di pedoman tidak ada KPI di code (Manager, Financing).**

---

## 2. GAP BOBOT KPI PER JABATAN

### Kepala Toko (ID 41)

| Pedoman | | Code | |
|---------|---|------|---|
| Komponen | Bobot | Komponen | Bobot |
| Omzet Cabang | 30% | Omset Toko | **70%** |
| Customer & Transaksi | 15% | Total Customer | 10% |
| Conversion | 10% | Tutup Kasir | 10% |
| Operational Compliance | 15% | Stok Opname | 10% |
| Team Productivity | 10% | | |
| Customer Satisfaction | 10% | | |
| Stock & Asset Control | 5% | | |
| Reporting | 5% | | |
| **TOTAL** | **100%** | **TOTAL** | **100%** |

**GAP:** Omzet 30% vs 70%. Komponen Operational, Conversion, Team Productivity, Customer Satisfaction **hilang di code.**

---

### SPV (ID 40)

| Pedoman | | Code (gaji) | |
|---------|---|-------------|---|
| Komponen | Bobot | Komponen | Bobot |
| Pencapaian Omzet Area | 50% | Omset Cabang | **70%** |
| Pencapaian Cabang | 15% | Customer | 10% |
| Produktivitas Cabang | 10% | Operasional | 10% |
| SOP & Operational Control | 10% | Divisi | 10% |
| Coaching Kepala Toko | 5% | | |
| Customer & Quality | 5% | | |
| Reporting | 3% | | |
| Improvement | 2% | | |
| **TOTAL** | **100%** | **TOTAL** | **100%** |

**GAP:** Omzet 50% vs 70%. Coaching, Reporting, Improvement **hilang.**

---

### IT (ID 45)

| Pedoman | | Code | |
|---------|---|------|---|
| Komponen | Bobot | Komponen | Bobot |
| System Uptime | 20% | Omset | **30%** |
| Penyelesaian Bug | 20% | Bug Minor | 10% |
| Response Time | 15% | Operasional | **25%** |
| Security & Backup | 15% | Ecommerce | 15% |
| Development | 15% | Fitur | 20% |
| User Support | 10% | | |
| Dokumentasi | 5% | | |
| **TOTAL** | **100%** | **TOTAL** | **100%** |

**GAP TOTAL:** Pedoman IT **0% omzet** — code IT **30% omzet**.
Pedoman: Uptime, Response Time, Security, User Support, Dokumentasi **hilang.**
Code: Omset, Operasional, Ecommerce **tidak ada di pedoman.**

---

### Multimedia (ID 44)

| Pedoman | | Code | |
|---------|---|------|---|
| Komponen | Bobot | Komponen | Bobot |
| Ketepatan Deadline | 25% | Omset Cabang | **30%** |
| Kualitas Output | 25% | Feed PL | 15% |
| Kesesuaian Brief | 20% | Video | 20% |
| Produktivitas | 15% | Feed Mingguan | 15% |
| Support Campaign | 10% | Story | 10% |
| Improvement | 5% | Testimoni | 10% |
| **TOTAL** | **100%** | **TOTAL** | **100%** |

**GAP TOTAL:** Pedoman Multimedia **0% omzet** — code Multimedia **30% omzet.**
Pedoman: Deadline, Kualitas Output, Brief, Produktivitas **diganti** dengan Feed, Video, Story, Testimoni.

---

### Admin (ID 35)

| Pedoman | | Code | |
|---------|---|------|---|
| Komponen | Bobot | Komponen | Bobot |
| Akurasi transaksi | — | Omset Toko | **70%** |
| Kelengkapan administrasi | — | Tutup Kasir | 10% |
| Akurasi invoice | — | Stok Opname | 10% |
| Kelengkapan data customer | — | Absensi | 10% |
| Pelayanan customer | — | | |
| Follow-up | — | | |
| Disiplin | — | | |
| Tingkat kesalahan administrasi | — | | |
| **TOTAL** | **100%** (non-omzet) | **TOTAL** | **100%** (70% omzet!) |

**GAP TOTAL:** Pedoman Admin **0% omzet** — code Admin **70% omzet.**
Pedoman: 8 komponen non-omzet **hilang total.**

---

### Teknisi (ID 36)

| Pedoman | | Code | |
|---------|---|------|---|
| Komponen | Bobot | Komponen | Bobot |
| Produktivitas service | — | Omset Toko | **70%** |
| Jumlah pekerjaan | — | Omset Teknisi | 15% |
| Kecepatan service | — | Customer Masuk | 15% |
| Kualitas hasil service | — | | |
| Kepatuhan SOP | — | | |
| Error rate | — | | |
| Rework | — | | |
| Komplain customer | — | | |
| Garansi | — | | |
| Disiplin kerja | — | | |
| **TOTAL** | **100%** (non-omzet) | **TOTAL** | **100%** (85% omzet!) |

**GAP TOTAL:** Pedoman Teknisi **0% omzet** — code Teknisi **85% omzet.**

---

## 3. GAP TUNJANGAN KINERJA (Maksimal)

| Jabatan | Pedoman | Code | Selisih |
|---------|---------|------|---------|
| Manager | Rp 2.250.000 | Tidak ada di code | — |
| Financing | Rp 1.250.000 | Tidak ada di code | — |
| Kepala Digital Marketing | Rp 1.500.000 | — | — |
| IT | Rp 750.000 | Rp 250.000 | **-Rp 500.000** |
| Multimedia | Rp 750.000 | Rp 250.000 | **-Rp 500.000** |
| SPV | Rp 1.500.000 | Rp 1.250.000 | **-Rp 250.000** |
| Kepala Toko | Rp 1.000.000 | Rp 850.000 | **-Rp 150.000** |
| Admin | Rp 750.000 | Rp 250.000–850.000 | **Inkonsisten** |
| Teknisi | Rp 500.000 | Rp 250.000 | **-Rp 250.000** |
| Customer Service | Tidak ada | Rp 250.000 | — |
| Pengiklan | Tidak ada | Rp 1.000.000 | — |
| PIC | Tidak ada | Tidak ada | — |

**Semua tunjangan kinerja di code LEBIH RENDAH dari pedoman, kecuali Admin unit 1.**

---

## 4. GAP TARGET

| Target | Pedoman | Code | Konsisten? |
|--------|---------|------|-----------|
| Target HO Total | Rp 250.000.000 | Rp 200.000.000 (context gaji) | ❌ |
| Target HO per cabang | 65/45/65/75 jt | 35/18/40/35 jt (context gaji) | ❌ |
| Target Team Total | Rp 200.000.000 | Rp 200.000.000 | ✅ |
| Target Team per cabang | 50/35/55/60 jt | 50/35/55/60 jt | ✅ |
| Batas Minimal | Target − Rp 15jt | Tidak ada rumus ini | ❌ |

**GAP:** Target HO di code BUKAN Rp 250 jt. Target per cabang beda total.
Rumus Achievement `(Realisasi - BatasMinimal) / (Target - BatasMinimal) × 100%` **TIDAK ADA di code.**

---

## 5. GAP RUMUS ACHIEVEMENT

### Pedoman (Section VII)
```
Achievement = (Realisasi − Batas Minimal) ÷ (Target − Batas Minimal) × 100%
```

### Code (PenilaianKPI.php)
```php
// Kepala Toko — tiered hardcoded:
if ($aktual_omset <= $batas1) $nilai_omset = 0;
elseif ($aktual_omset == $batas2) $nilai_omset = 33;
elseif ($aktual_omset == $batas3) $nilai_omset = 66;
elseif ($aktual_omset >= $batas4 && $aktual_omset < $targetOmset) $nilai_omset = 100;
```

**GAP:** Code pakai tiered hardcoded (0/33/66/100), pedoman pakai rumus proporsional.
Code: `== $batas2` → hanya tepat pada angka tertentu.
Pedoman: proporsional di seluruh range.

---

## 6. RINGKASAN GAP

| Aspek | Status | Keterangan |
|-------|--------|-----------|
| Jumlah jabatan | ❌ | Code punya 3 jabatan ekstra (CS, Pengiklan, PIC) |
| Bobot KPI Kepala Toko | ❌ | 30% vs 70% omzet |
| Bobot KPI SPV | ❌ | 50% vs 70% omzet |
| Bobot KPI IT | ❌ | 0% vs 30% omzet |
| Bobot KPI Multimedia | ❌ | 0% vs 30% omzet |
| Bobot KPI Admin | ❌ | 0% omzet vs 70% omzet |
| Bobot KPI Teknisi | ❌ | 0% omzet vs 85% omzet |
| Tunjangan Kinerja | ❌ | Semua lebih rendah di code |
| Target HO | ❌ | Rp 250jt vs Rp 200jt |
| Rumus Achievement | ❌ | Proporsional vs Hardcoded |
| KPI Akhir ≤ 100% | ❌ | Code tidak cap |
| Kategori KPI | ❌ | Code pakai Platinum/Gold/Silver/Bronze |
| | | Pedoman pakai Sangat Baik/Baik/Cukup/Kurang/Kritis |
| Master Kompensasi | ❌ | Hardcode di code, harusnya DB |
| Master KPI | ❌ | Hardcode di code, harusnya DB |
| Gaji Pokok | ✅ | Rp 1.500.000 semua |
| Tunjangan Absensi | ✅ | Rp 250.000 semua |
