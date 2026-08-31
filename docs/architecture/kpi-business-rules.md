# KPI Business Rules

**Tanggal:** 28 Agustus 2026
**Status:** Status per rule ditandai (CONFIRMED / DARI SISTEM LAMA / UNKNOWN)

---

## 1. CONFIRMED (diklarifikasi owner)

| Rule | Nilai | Divisi | Sumber |
|---|---|---|---|
| Insentif Kepala Toko | pool = 3% × omset toko | dinamis dari `incentive_members` (group+unit+periode) | Owner |
| Insentif Digital Division | pool = 1% × omset toko | dinamis dari `incentive_members` | Owner |
| Basic compute | individual = pool / member_count | — | Owner |

Saat ini:
- KEPALA_TOKO → 3 member/unit (Kepala Toko + Teknisi + Admin)
- DIGITAL_DIVISION → 4 member (Pengiklan + IT + Multimedia + CS)

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
| 35 Admin | Omset 70, Tutup Kasir 10, Stok Opname 10, Absensi 10 | 40/20/20/20 |
| 36 Teknisi | Omset 70, Omset Teknisi 15 (=omset), Customer 15 | 40/20/20/20 |
| 41 Kepala Toko | Omset 70, Customer 10, Tutup Kasir 10, Opname 10 | 40/20/20/20 |
| 40 SPV | gaji: omset 70/cust 10; non: omset 10/cust 70; + Operasional 10, Divisi 10 | SPV pakai RATA (divisi) |
| 42 CS | gaji: omset 70; non: omset 60 + Testimoni 10; + Closing/Upselling/FollowUp 10/10/10 | 40/20/20/20 |
| 43 Pengiklan | gaji: Budg 15/ROAS 15/Omset 70; non: Budg 15/ROAS 15/Omset 10/Customer 60 | 40/20/20/20 |
| 44 Multimedia | Omset 30, Feed PL 15, Video 20, Feed Mingguan 15, Story 10, Testimoni 10 | 40/20/20/20 |
| 45 IT | Omset 30, Bug Minor 10, Operasional 25, Ecommerce 15, Fitur 20 | 40/20/20/20 |
| 46 PIC (non-gaji) | Budget Toko 20, Budget Global 30, Omset Cabang 50 | 40/20/20/20 |

### Tunjangan

| Jabatan | tunjangan_kinerja max | absen | penempatan |
|---|---|---|---|
| 41 KT | 850k | 250k max | 350k jika bukan kota unit |
| 40 SPV | 1.25jt | 250k | 350k |
| 43 Pengiklan | 1jt | 250k | 350k |
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