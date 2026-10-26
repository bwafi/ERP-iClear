# PRD Modul 08 — KPI & Penilaian

| | |
|---|---|
| Kode Modul | KPI-08 |
| Sumber analisis | baca parsial `app/Controllers/PenilaianKPI.php`, `Routes.php`, service `Kpi/*` |
| Status verifikasi | ✅ fakta / 🔶 inferensi / ⚠️ ketidakpastian |

---

## 1. Tujuan
Mengelola template KPI, penilaian kinerja, evaluasi KPI otomatis dari data operasional, grading, target KPI, aset KPI (master & kontrol), customer satisfaction, dan input kehadiran KPI. Skor akhir dihitung oleh engine `KpiCalculationService` memakai `kpi_components/kpi_weights/kpi_targets`.

## 2. Aktor
- Finance/ROOT/Direktur/Manager (role 0,1,2,34): akses luas; edit aset master; target KPI (khusus root).
- SPV (role 40): kontrol aset, scope unit binaan.
- Kepala Toko/SPV: input customer satisfaction (Google review).
- Multimedia/Divisi: menyuplai achievement (konten/marketing/sosial) yang dihitung engine.

## 3. Preconditions
- Login; role sesuai.
- Data operasional sumber (omzet, stok, service, absensi, dll.).

## 4. Alur Utama
### 4.1 Penilaian KPI
- `PenilaianKPI::index` (daftar), `kpi_index` (scope unit by role), `kpi_detail`, `insert/update/delete_penilaian`, `export_penilaian`, `export_penilaian_detail`, `index_riwayat`.
### 4.2 Input nilai manual
- `save_kualitas`, `save_manual_daily`, `save_customer_satisfaction`, `save_manager_manual`, `save_absen` / `save_absen`, `attendance_input/attendance_save`.
### 4.3 Aset KPI
- `aset_master_index/insert/update/toggle/delete`; delete diizinkan role `[0,1,2]` (pesan “Hanya Finance” menyesatkan), manager aset `[0,1,2,34]`.
- `kontrol_aset_index/save/finalize/reopen` (SPV).
### 4.4 Target KPI
- `TargetKpi::index/simpan/hapus` — guard `ADA_YANG_BOLEH = [1,34]` (bukan “Admin Root saja”).
### 4.5 Template & Summary & Legacy
- `TemplateKPI::index/index2/insert/update/delete`, `TemplatePenilaian::insert/update/delete`, `Penilaian::insert/update/delete/get_template_by_jabatan/spv_kpi_index/save_spv_kpi`, `SummaryKPI::summary_kpi/summary_grading/summary_detail`, `KeyPerformance::index/insert/update/delete_penilaian`.

## 5. Business Rules (Bukti)
1. Engine KPI **aktif** adalah `KpiCalculationService`, dipanggil dari `PenilaianKPI`, `PayrollGenerator`, `HoSalaryDistributionService`, `UnitSalaryCalculationService`, dan `ManagerKpiService`. `LegacyKpiCalculationService` tidak dipanggil controller aktif. Service pendukung: `ManagerKpiService`, `SupervisorKpiService`, `ManualKpiScorer`, `AttendanceScoreCalculator`, `ManualGridService`. — `app/Services/Kpi/*` (KPI-05)
2. Scope unit penilaian bergantung role: role lintas unit → semua unit; SPV → unit binaan; lain → unit sendiri. — `PenilaianKPI::kpi_index`
3. Hapus aset master diizinkan role `[0,1,2]`; manager aset `[0,1,2,34]` — **bukan hanya Finance 0/1**. — `PenilaianKPI::aset_master_delete/isAssetMasterManager` (KPI-02)
4. Control aset (SPV): save/finalize/reopen dengan guard unit scope. — `PenilaianKPI::kontrol_aset_*`
5. Target KPI diizinkan role `[1,34]`, bukan “Admin Root saja”. — `TargetKpi::ADA_YANG_BOLEH` (KPI-01)
6. Range input manual **tidak seragam**: `save_kualitas` menolak di luar 1–5; `ManualKpiScorer` memakai 0–100 untuk komponen lain; `ManualGridService` menangani grid harian. — `PenilaianKPI::save_kualitas`; `ManualKpiScorer` (KPI-03)
7. [⚠️] Perhitungan skor/achievement per komponen & bobot dijamin dari DB (`kpi_weights/kpi_components/kpi_targets`); detail formula per komponen tidak semuanya diverifikasi di dokumen ini (rujuk `docs/architecture/kpi-engine.md`, `KPI_SERVICE_DOCUMENTATION.md`, `docs/analisis/penilaianKPI.md`).
8. [⚠️] Konfigurasi target (mis. OMSET_TOKO/CABANG, Has/TARGET) bervariasi per jabatan & unit; banyak migration menyesuaikan (mis. `UpdateTargetOmsetUnit1`, `FixKepalaTokoOmsetWeight`, `MirrorJemberTargetsToGenteng`).
9. `save_manual_daily` dan `save_kualitas` memeriksa `isInScope`/guard evaluator; `save_manager_manual` dan `save_customer_satisfaction` mendelegasikan otorisasi ke service (`EvaluatorAuthorizationService::canEvaluateComponent`) dan memvalidasi nilai 0–100 di service. — `PenilaianKPI.php`; `ManagerKpiService::saveManual`; `SupervisorKpiService::saveCustomerSatisfaction`
10. `attendance_save` **tidak** memakai `EvaluatorAuthorizationService`/scope; `target_id` dari POST dan `AttendanceInputService` tidak memeriksa hak evaluator. — `PenilaianKPI.php:2432`; `AttendanceInputService` (PR-07/KPI-04)
11. [🔶] Istilah “omzet” pada sebagian komponen KPI (`OMZET_TOKO`/`OMZET_WILAYAH`/`OMZET_GLOBAL`) dihitung dari **gross profit** (`SUM(sub_total - hpp_penjualan)`), bukan omzet penjualan. Perlu konfirmasi definisi bisnis. — `OmsetTokoCalculator`; `ManagerKpiService` (KPI-06)
12. Refund klaim garansi mengurangi KPI Omset Toko, Omset Cabang, dan Omset Teknisi sebesar `service.refund_garansi_jumlah` pada bulan `service.refund_garansi_tanggal` (bukan bulan klaim). HPP sparepart rusak tetap mengurangi Omset Teknisi lewat `hpp_omset_garansi`/`tanggal_hpp_klaim`. — `ModelRefundGaransiLoss`; `OmsetTokoCalculator`, `OmsetCabangCalculator`, `OmsetTeknisiCalculator`, `OmsetDailyCalculator`; test `GaransiRefundKpiTest`.

## 6. Acceptance Criteria
- [ ] Template KPI & penilaian dapat dikelola.
- [ ] Evaluasi KPI otomatis dipicu dari data operasional & menghasilkan skor/grading.
- [ ] Input manual terbatas 0–100 dan tersimpan.
- [ ] Edit/hapus aset master dibatasi role; kontrol aset SPV dengan finalize/reopen.
- [ ] Target KPI hanya dikelola root.
- [ ] Customer satisfaction & input kehadiran tersimpan.

## 7. Dependensi
- `ModelTemplateKpi/ModelTemplatePenilaian/ModelPenilaian/ModelPenilaianDetail/ModelPenilaianKPI/ModelSummaryKPI/ModelKpiTarget/ModelKpiComponent/ModelKpiWeight/ModelKpiEvaluation/ModelAsetKpi/ModelKpiAttendanceDetail/ModelKpiAttendanceOff/ModelCustomerSatisfaction`.
- Service `Kpi/*`; data sumber dari modul lain (omzet, stok, service, absensi, marketing, konten, sosial, finance).
- Modul: seluruh modul operasional sebagai sumber achievement; Keuangan/Hutang Piutang (timeliness); Aset.

## 8. Status & Tandai
- ✅ Terverifikasi: `KpiCalculationService` aktif, scope role `kpi_index`, kontrol aset SPV, manager/supervisor calculator.
- ⚠️ Koreksi:
  1. Target KPI bukan root-only; role `[1,34]`. — KPI-01
  2. Hapus aset master `[0,1,2]`, bukan Finance-only. — KPI-02
  3. Range input manual tidak seragam 0–100; `save_kualitas` 1–5. — KPI-03
  4. `attendance_save` tanpa otorisasi evaluator. — KPI-04/PR-07
  5. “Omzet” sebagian KPI = gross profit; perlu konfirmasi. — KPI-06
- ⚠️ Belum diverifikasi penuh: formula per-komponen (bobot/achievement) dan seluruh method `PenilaianKPI` (2463 baris).
- Test: `CustomerSatisfactionTest`, `ManualKpiScorerFeedTest`, `CustomerCountKepalaTokoUnit1Test`, `ManagerKpiTest`, `FinanceKpiTest`, `DigitalMarketingKpiTest`. Tidak ada test untuk role TargetKpi/aset, range manual, otorisasi attendance, dan definisi omzet.
- Dokumen pendukung: `docs/architecture/kpi-engine.md`, `docs/analisis/penilaianKPI.md`.
