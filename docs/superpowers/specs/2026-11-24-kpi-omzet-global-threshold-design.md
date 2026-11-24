# KPI Omzet Global Bertingkat Target Toko → Target HO — Design

**Tanggal:** 2026-11-24
**Status:** disetujui, siap implementasi
**Issue:** [#239](https://github.com/bwafi/ERP-iClear/issues/239)

## 1. Intent

Di `/penilaian_kinerja`, komponen **Omzet Global Perusahaan** (bobot 50) milik
Manager (jabatan 34) dan Kepala Divisi Digital Marketing (jabatan 43) memakai
skor proporsional `omzet / Σ target HO × 100`. Akibatnya perusahaan tetap
mendapat skor tinggi walau belum mencapai target toko sama sekali.

Yang diminta: skor **0 selama omzet global belum mencapai Σ target toko**, lalu
naik linear dari 0 di Σ target toko sampai 100 di Σ target HO. User memilih
varian ini (bukan 100 langsung di target toko) pada 2026-11-24.

## 2. Kondisi saat ini

- `DigitalMarketingKpiService::scoreOmzetGlobal(float $actual, float $target)`
  menghitung `min(actual / target × 100, 100)` (commit `fb7aa5d`, 27 Okt 2026).
- `DigitalMarketingKpiService::omzetTarget()` = Σ `target_value` komponen
  `OMSET_CABANG` `position_id = 40` untuk `omsetBranchUnits()` (non-HO,
  non-trial).
- `ManagerKpiService::componentData('OMZET_GLOBAL')` mendelegasikan seluruhnya
  ke service yang sama, jadi satu perubahan mencakup Manager dan Kadiv.
- PRD Modul 08 butir 13 mencatat threshold toko/HO sengaja dihapus. Butir itu
  harus diperbarui.
- Varian threshold ini sudah pernah ada di service yang sama sebelum `fb7aa5d`;
  perubahan ini mengembalikannya dengan definisi target yang berlaku sekarang.

Data `kpi_targets` November 2026: Σ target toko ≈ Rp300–310 jt
(`OMSET_TOKO`, `position_id NULL`), Σ target HO = Rp338 jt (`OMSET_CABANG`
posisi 40). Omzet Rp270 jt: skor lama 79,9; skor baru 0.

## 3. Goals

- Skor `OMZET_GLOBAL` = 0 bila omzet global ≤ Σ target toko.
- Skor naik linear di antara Σ target toko dan Σ target HO.
- Skor = 100 bila omzet global ≥ Σ target HO.
- Manager (34) dan Kadiv (43) memakai rumus identik.
- Tetap mengecualikan unit trial dan HO, sama seperti aturan omzet lintas
  cabang yang berlaku (PRD Modul 08 butir 17).

## 4. Non-goals

- Tidak mengubah komponen omzet lain (`OMSET_TOKO`, `OMSET_CABANG`,
  `OMSET_TEKNISI`, `OMZET_WILAYAH`) maupun insentif HO.
- Tidak mengubah bobot, target, atau konfigurasi `kpi_targets`.
- Tidak menambah cut-off periode. Rumus berlaku untuk semua periode yang
  dihitung ulang oleh engine.
- Tidak mengubah schema database.

## 5. Pendekatan yang dipertimbangkan

1. **Proporsional ke target HO (status quo).** Tidak memenuhi permintaan;
   skor > 0 walau target toko belum tercapai.
2. **Threshold per cabang lalu dirata-rata.** Menilai tiap cabang 0/100 lalu
   dirata-rata. Ditolak: komponen ini bernama Omzet Global Perusahaan dan
   selama ini agregat perusahaan, bukan rata-rata capaian cabang (itu peran
   `TARGET_CABANG`).
3. **Threshold agregat toko → HO (dipilih).** Σ omzet perusahaan dibandingkan
   dengan Σ target toko dan Σ target HO. Konsisten dengan definisi insentif HO
   (pool global memakai Σ target `OMSET_TOKO`) dan dengan rumus yang pernah
   dipakai komponen ini.

## 6. Desain

### 6.1 Service

`app/Services/Marketing/DigitalMarketingKpiService.php`

- Tambah `omzetTargets(string $context, ?string $date): array{toko:float,ho:float}`:
  - `toko` = Σ `target_value` `OMSET_TOKO` (`position_id NULL`) per
    `salesUnits()`;
  - `ho` = Σ `target_value` `OMSET_CABANG` (`position_id 40`) per
    `salesUnits()`;
  - target kosong dilewati (tidak menambah 0).
- `omzetTarget()` tetap ada dan mengembalikan `omzetTargets()['ho']` agar
  pemanggil/test lama tidak rusak.
- `scoreOmzetGlobal(float $actual, float $targetToko, float $targetHo)`:
  - `targetHo <= 0` → `null` (target belum dikonfigurasi);
  - `actual >= targetHo` → `100.0`;
  - `actual <= targetToko` → `0.0`;
  - `targetHo - targetToko <= 0` → `100.0` (konfigurasi anomali: batas atas
    sudah terlampaui);
  - selain itu `round((actual - targetToko) / (targetHo - targetToko) * 100, 4)`.
- `componentData('OMZET_GLOBAL')`:
  - `achievement` memakai rumus baru;
  - `target` tetap Σ target HO (dipakai kolom Target);
  - tambah `target_toko` untuk keterangan tampilan;
  - `shortfall` tetap `max(target HO - actual, 0)`.

### 6.2 Tampilan `/penilaian_kinerja`

`app/Services/Kpi/KpiCalculationService.php` meneruskan `target_toko` pada item
KPI (jalur Manager dan Kadiv). `app/Views/penilaian/penilaian_kinerja.php`
menampilkan baris kecil "min. Rp …" di bawah Target HO khusus komponen yang
punya `target_toko`, supaya skor 0 tidak terbaca sebagai error. Tidak ada
perubahan kolom/tabel.

### 6.3 Dokumentasi

- `docs/prd/modul/PRD_MODUL_08_KPI_Penilaian.md` butir 13 diperbarui menjadi
  rumus threshold toko → HO.
- `docs/prd/modul/PRD_MODUL_08_KPI_Penilaian.md` acceptance criteria ditambah
  satu poin.
- `docs/superpowers/specs/2026-10-18-kpi-manager-design.md` baris komponen
  Omzet Perusahaan sudah menyebut threshold; tidak perlu diubah.

## 7. Edge case

| Kasus | Perilaku |
|---|---|
| Σ target HO ≤ 0 / tidak ada | `null` (dikecualikan dari total, seperti sekarang) |
| Σ target toko = 0, HO > 0 | Skor proporsional terhadap HO (threshold toko tidak berlaku) |
| Σ target toko ≥ Σ target HO | 100 bila omzet ≥ target HO; 0 bila di bawahnya |
| Omzet negatif | 0 |
| Unit trial | Tidak masuk omzet maupun target (fungsi `salesUnits()`) |
| Cabang tanpa target | Tidak menambah target; omzetnya tetap masuk Σ omzet |

## 8. Testing

- `tests/unit/DigitalMarketingKpiTest.php`: ganti tiga test proporsional dengan
  boundary threshold — di bawah/di titik toko = 0, titik tengah = 50, di
  atas/di titik HO = 100, target HO invalid = null, target toko ≥ HO.
- `tests/unit/KpiOmsetTrialUnitTest.php`: pastikan Σ target toko dan Σ target
  HO sama-sama membuang unit trial.
- Jalankan `vendor/bin/phpunit -c phpunit.xml tests/unit/DigitalMarketingKpiTest.php`
  dan `tests/unit/KpiOmsetTrialUnitTest.php`.
- `php -l` pada file PHP yang diubah dan `git diff --check`.

## 9. Risiko

- Skor Manager/Kadiv turun tajam pada bulan yang omzetnya di bawah target toko
  (contoh Nov: 79,9 → 0 pada omzet Rp270 jt). Ini konsekuensi yang diminta.
- Payroll yang di-generate ulang untuk periode lama ikut memakai skor baru.
  Tidak ada snapshot skor otomatis; user sudah diberi tahu dan memilih tanpa
  cut-off.
- Data target unit 4 (Pandaan) punya dua baris `OMSET_TOKO` aktif pada
  November (Rp70 jt dan Rp80 jt). `getTargetByKpiAndUnit()` mengambil baris
  pertama tanpa urutan `effective_from`; ini masalah konfigurasi lama dan
  tidak diubah dalam scope ini. Σ target toko memakai baris yang sama dengan
  yang dipakai insentif HO.
