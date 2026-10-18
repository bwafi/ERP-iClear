# Desain — KPI Tunjangan Kinerja Manager (jabatan 34)

Tanggal: 2026-10-18
Status: menunggu persetujuan

## Latar & alasan

Tunjangan kinerja Manager (Huda, `ID_AKUN 41`, `ID_JABATAN 34`, unit 50 HO)
punya plafon **Rp 2.250.000** (`salary_structures`, `percent_of_kpi`), tetapi
**KPI-nya belum terpasang**: `kpi_weights` posisi 34 kosong → `skor_total = 0`
→ tunjangan kinerja selalu **Rp 0**. Halaman Penilaian Kinerja Manager kosong
("Belum ada data KPI").

Pedoman resmi (`docs/analisis/pedoman-kpi/pedoman-resmi.md` §IX) mendefinisikan
7 komponen. Dokumen ini menetapkan sumber tiap komponen agar KPI Manager dapat
dihitung.

## Tujuan

- Manager (34) punya 7 komponen KPI berbobot total 100 dengan `skor_total`
  yang mengalir ke tunjangan kinerja (tanpa ubah `salary_structures`).
- Sebisa mungkin **memakai ulang** komponen/rumus yang sudah ada.

## Non-tujuan

- Tidak mengubah bobot/komponen jabatan lain.
- Tidak mengubah tunjangan absen Manager (`skor_total2` sudah jalan).
- Tidak membangun metrik HR baru (turnover, pelatihan) — tidak ada datanya.

## Kendala

- **PHP 7.4** (versi produksi). Tidak memakai sintaks PHP 8: named arguments,
  union types, `str_contains`/`str_starts_with`, nullsafe `?->`, `match`, dst.

## Anti-tujuan (gagal walau teknis jalan)

- Komponen baru sampai membuat KPI jabatan lain berubah nilainya.
- Angka Manager tidak bisa ditelusuri sumbernya.

## Komponen & sumber (keputusan pemilik pada 2026-10-18)

| # | Komponen | Bobot | Kode komponen | Sumber / rumus |
|---|----------|-------|----------------|----------------|
| 1 | Omzet Perusahaan | 50 | `OMZET_GLOBAL` (pakai ulang, id 55) | Rumus **sama persis dengan Kepala Divisi (43)**: `DigitalMarketingKpiService::componentData('OMZET_GLOBAL')`. actual = Σ `OmsetTokoCalculator` unit 1–5; target toko = Σ target `OMSET_TOKO` per unit, target HO = Σ target `OMSET_CABANG` (posisi 40) per unit; skor: ≤toko→0, ≥HO→100, di antaranya linear. |
| 2 | Profit & Kesehatan Keuangan | 15 | `FIN_KESEHATAN_UANG` (pakai ulang, id 60) | Nilai komponen ini apa adanya dari `FinanceKpiService::componentData('FIN_KESEHATAN_UANG')` (skor 0–100, rata-rata antar cabang). |
| 3 | Pencapaian Cabang | 10 | `TARGET_CABANG` (pakai ulang, id 31) | Opsi **A**: jumlah cabang yang omsetnya ≥ target HO ÷ total cabang × 100. Logika sama `SupervisorKpiService::targetCabang()`, tetapi cakupan = **semua unit cabang** (1–5, HO/50 tidak ikut), bukan area 1 SPV. |
| 4 | Efektivitas Organisasi | 10 | `EFEKTIVITAS_ORGANISASI` (baru, automatic) | Rata-rata `total_score` KPI **seluruh** pegawai aktif `ID_JABATAN = 40` (SPV). Anggota tanpa nilai dihitung **0** (pembagi = semua anggota aktif, bukan hanya yang terisi). Saat ini 2 SPV. |
| 5 | SDM & Leadership | 5 | `SDM_LEADERSHIP` (baru, automatic) | Rata-rata `total_score` KPI pegawai aktif `ID_JABATAN ∈ {40 (SPV), 43 (Kadiv), 45 (IT), 0 (Finance)}`, dihitung **per jabatan dulu** (masing-masing = rata-rata seluruh anggota jabatan itu, anggota tanpa nilai = 0), lalu **dirata-ratakan antar 4 jabatan** (tiap jabatan berbobot sama). Konsisten dengan `AttendanceAggregationService::managerTeamAverage`. |
| 6 | Improvement Perusahaan | 5 | `IMPROVEMENT_PERUSAHAAN` (baru, manual) | Manual dulu. |
| 7 | Reporting & Governance | 5 | `REPORTING_GOVERNANCE` (baru, manual) | Manual dulu. |

Total bobot = 100.

Catatan kesepakatan penting: #4 dan #5 **menumpang skor KPI orang lain**. Bila
SPV/Kadiv/IT/Finance belum dinilai pada bulan itu, komponen bernilai 0 (bukan
N/A) — disadari dan diterima.

## Rancangan teknis

### Data (migrasi idempoten, pola repo)
- Tambah 4 baris `kpi_components`:
  - `EFEKTIVITAS_ORGANISASI` — name "Efektivitas Organisasi", type `automatic`.
  - `SDM_LEADERSHIP` — name "SDM & Leadership", type `automatic`.
  - `IMPROVEMENT_PERUSAHAAN` — name "Improvement Perusahaan", type `manual`.
  - `REPORTING_GOVERNANCE` — name "Reporting & Governance", type `manual`.
- Tambah 7 baris `kpi_weights`, `position_id = 34`, `weight_group = 'kpi'`,
  `effective_from = '2024-01-01'`, `effective_to = NULL`, bobot 50/15/10/10/5/5/5
  (komponen pakai-ulang + 4 komponen baru).

### Engine
- `KpiCalculationService::calculateForEmployee()`: tambah cabang dispatch
  `positionId === 34` untuk ketujuh komponen Manager (pola sama seperti cabang
  jabatan 43/44/0). Komponen pakai-ulang dipetakan ke service masing-masing.
- Service baru `App\Services\Kpi\ManagerKpiService` untuk #4 dan #5 (rata-rata
  `total_score` KPI kelompok jabatan), dan #3 versi manager (semua unit).
  #1 dan #2 memanggil `DigitalMarketingKpiService` / `FinanceKpiService` yang ada.

### Input manual (#6, #7)
- Kedua komponen `type = manual` akan muncul di grid manual existing
  (`ManualGridService`) untuk target Manager, karena `EvaluatorAuthorizationService`
  memberi akses penuh ke Admin root (1) & Direktur (2). Tidak perlu aturan
  otorisasi baru; input oleh Direktur/Admin root.

### Tampilan
- `penilaian_kinerja.php` membaca `detail_kpi` generik → 7 baris tampil otomatis.

## Yang diserahkan ke builder

- Kode/nama persis migrasi & lokasi file service (ikuti konvensi repo).
- Cara membatasi cakupan #3 ke "semua unit cabang" (source daftar unit dari
  master `unit` kecuali HO, seperti `KpiCalculationService::branchUnits()`).
- Detail pemetaan nilai #1/#2 (ambil `achievement` dari service existing).
- Bentuk input manual #6/#7 (grid harian existing vs nilai bulanan) — pilih yang
  paling tidak menambah kode baru.

## Keputusan yang ditetapkan pemilik (2026-10-18, hasil builder-check)

- **A2 — anggota tanpa nilai dihitung 0.** Saat merata-rata kelompok (#4, #5),
  pembaginya = jumlah **semua** anggota aktif kelompok itu, bukan hanya yang
  terisi. Anggota yang belum punya nilai menarik komponen turun. "Punya nilai"
  = `total_score > 0`.
- **B2 — rata-rata per jabatan dulu, baru antar jabatan.** Khusus #5 (dan
  menyiratkan #4 = rata-rata jabatan 40). Mengikuti pola
  `AttendanceAggregationService::managerTeamAverage`.
- **C1 — input manual #6/#7 oleh Admin Root (1) & Direktur (2).** Keduanya sudah
  punya akses penuh di `EvaluatorAuthorizationService`; **tidak ada** aturan
  otorisasi baru. Akun live: Admin Root (id 1, "Danny"), Direktur (Wahid, Guruh).

## Panggilan yang saya ambil (builder boleh sesuaikan)

- Setiap skor kontributor **di-cap 100 sebelum dirata-rata** (konsisten dengan
  `SupervisorKpiService::kinerjaKepalaToko` yang `min(val,100)` per pegawai).
- Hasil #4/#5 **di-cache** dalam `ManagerKpiService`, karena menghitung KPI tiap
  SPV ikut menghitung setiap Kepala Toko di areanya (kaskade) dan ini jalan tiap
  render Penilaian Kinerja / slip gaji Manager.

## Sisa pertanyaan ke pemilik

- (Tidak ada.) Bila muncul saat implementasi, dibawa kembali sebelum lanjut.

