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
- `aset_master_index/insert/update/toggle/delete`; delete hanya Finance.
- `kontrol_aset_index/save/finalize/reopen` (SPV).
### 4.4 Target KPI
- `TargetKpi::index/simpan/hapus` (khusus Admin Root — guard di Controller).
### 4.5 Template & Summary & Legacy
- `TemplateKPI::index/index2/insert/update/delete`, `TemplatePenilaian::insert/update/delete`, `Penilaian::insert/update/delete/get_template_by_jabatan/spv_kpi_index/save_spv_kpi`, `SummaryKPI::summary_kpi/summary_grading/summary_detail`, `KeyPerformance::index/insert/update/delete_penilaian`.

## 5. Business Rules (Bukti)
1. Engine KPI terpusat: `KpiCalculationService` (dan `LegacyKpiCalculationService`, `ManagerKpiService`, `SupervisorKpiService`, `ManualKpiScorer`, `AttendanceScoreCalculator`, `ManualGridService`, dst.). — `app/Services/Kpi/*`
2. Scope unit penilaian bergantung role: role lintas unit → semua unit; SPV → unit binaan; lain → unit sendiri. — `PenilaianKPI::kpi_index`
3. Hapus aset master hanya role Finance (0/1). — `PenilaianKPI::aset_master_delete`
4. Control aset (SPV): save/finalize/reopen dengan guard unit scope. — `PenilaianKPI::kontrol_aset_*`
5. Target KPI khusus Admin Root. — `TargetKpi` (comment route)
6. Input manual dibatasi range 0–100 (`ManualKpiScorer`). — test `ManualKpiScorerFeedTest`
7. [⚠️] Perhitungan skor/achievement per komponen & bobot dijamin dari DB (`kpi_weights/kpi_components/kpi_targets`); detail formula per komponen tidak semuanya diverifikasi di dokumen ini (rujuk `docs/architecture/kpi-engine.md`, `KPI_SERVICE_DOCUMENTATION.md`, `docs/analisis/penilaianKPI.md`).
8. [⚠️] Konfigurasi target (mis. OMSET_TOKO/CABANG, Has/TARGET) bervariasi per jabatan & unit; banyak migration menyesuaikan (mis. `UpdateTargetOmsetUnit1`, `FixKepalaTokoOmsetWeight`, `MirrorJemberTargetsToGenteng`).

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
- ✅ Terverifikasi: engine terpusat, scope role, aset master delete finance-only, kontrol aset SPV, target root, batas 0–100.
- ⚠️ Belum diverifikasi penuh: formula per-komponen (bobot/achievement) dan seluruh method `PenilaianKPI` (2463 baris). 
- Test: `CustomerSatisfactionTest`, `ManualKpiScorerFeedTest`, `CustomerCountKepalaTokoUnit1Test`, `ManagerKpiTest`, `FinanceKpiTest`, `DigitalMarketingKpiTest`.
- Dokumen pendukung: `docs/architecture/kpi-engine.md`, `docs/analisis/penilaianKPI.md`.
