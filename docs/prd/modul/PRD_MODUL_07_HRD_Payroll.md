# PRD Modul 07 — HRD, Absensi & Payroll

| | |
|---|---|
| Kode Modul | HR-07 |
| Sumber analisis | baca parsial `app/Controllers/Payroll.php`, `Routes.php`, service `Payroll/*`, `Incentive/*` |
| Status verifikasi | ✅ fakta / 🔶 inferensi / ⚠️ ketidakpastian |

---

## 1. Tujuan
Mengelola kehadiran/absensi (masuk-pulang berbasis lokasi & manual), persetujuan presensi, jadwal masuk, tugas & template tugas, serta penggajian (generate register, lock/unlock, bayar) yang mengkaitkan skor KPI dan potongan kasbon.

## 2. Aktor
- Pegawai (absen masuk/pulang, tugas).
- HRD/admin (jadwal masuk, template tugas, approval presensi).
- Finance/manager (generate & bayar payroll).

## 3. Preconditions
- Login; akun pegawai aktif; unit.
- Untuk payroll: master gaji & KPI bulan berjalan tersedia; data absensi.

## 4. Alur Utama
### 4.1 Absensi
- `Absensi::kirim_lokasi_masuk/kirim_lokasi_pulang`; `RiwayatPresensi::submit_absen_manual/kirim_lokasi_pulang_manual`.
- `RiwayatPresensi::index/semua_riwayat/export_semua_presensi/approval_presensi/submit_approval_presensi`.
### 4.2 Jadwal Masuk
`JadwalMasuk::index/insert_jadwal/update_jadwal/delete_jadwal` — daftar jadwal & jabatan.
### 4.3 Tugas & Template
- `Tugas::index/index2/insert/update/delete/clear_all/updateStatus`; `Riwayat_Tugas::index`; `TemplateTugas::index/insert/update`.
### 4.4 Payroll
1. `Payroll::index` — daftar baris `finance_payroll` (register aktif) **dan** `kas_keluar` legacy + `PayrollRunSummary::build`; **tanpa filter unit** (semua unit tampil).
2. `generateRegister` — menghitung dari master gaji + skor KPI bulan itu via `PayrollGenerator`; menulis **`finance_payroll`** dengan status `excluded`/siap.
3. `updateRegister` — ubah baris register aktif; tolak bila sudah `dibayar`; **tidak memeriksa lock file**.
4. `lockPayroll/unlockPayroll` — file lock `writable/locks/payroll.json`; **tanpa guard role**.
5. `insert/update/delete` — CRUD legacy baris `kas_keluar` payroll; update/delete ditolak bila terkunci; **tanpa guard role/scope** dan `idunit` dari POST.
6. `bayar` — set baris `finance_payroll` `dibayar`; potong kasbon (settle idempotent); hitung `total_bersih`; tulis `potongan_kasbon`. **Tidak menolak baris yang sudah `dibayar`; panggilan kedua menimpa `potongan_kasbon` menjadi 0.**

## 5. Business Rules (Bukti)
1. Generate register berasal dari master gaji + **skor KPI bulan itu** (blok `KpiCalculationService`). — `Payroll::generateRegister`
2. Register aktif disimpan di **`finance_payroll`** (`PayrollGenerator`); CRUD legacy `insert/update/delete` masih menulis **`kas_keluar`**. `Payroll::index` menampilkan keduanya. — `Payroll::generateRegister/index/insert/update/delete` (PR-05)
3. Update/delete **legacy** ditolak bila baris terkunci (tidak ada cek status `dibayar`); **`updateRegister` menolak baris `dibayar` tetapi tidak memeriksa lock**. — `Payroll::update/delete/updateRegister` (PR-04)
4. Lock/unlock memakai file `writable/locks/payroll.json` (bukan kolom DB), tanpa guard role. — `Payroll::lockPayroll/unlockPayroll` (PR-08)
5. Bayar → settle kasbon pegawai (idempotent per payroll); `total_bersih = total - potongan_kasbon`. **Pembayaran ulang tidak ditolak dan menimpa `potongan_kasbon` menjadi 0.** — `Payroll::bayar`; `HutangPiutangService::settleKasbonFromPayroll` (PR-03)
6. `Payroll::index` menampilkan payroll seluruh unit; `FinanceScopeService` hanya dipakai untuk `can_input` di view, bukan filter query. — `Payroll.php:85-220` (PR-01)
7. `Payroll::insert/update/delete` tidak memakai `canInput`/scope dan mengambil `idunit` dari POST. — `Payroll.php` (PR-02)
8. `PenilaianKPI::slip_gaji($idakun)` tidak membatasi siapa yang meminta; `gaji()` menampilkan semua karyawan `TERIMA_GAJI=1`; route hanya `auth`. — `PenilaianKPI.php` (PR-06)
9. `PenilaianKPI::attendance_save()` memakai `target_id` dari POST tanpa `EvaluatorAuthorizationService`/scope; `attendance_input` hanya memfilter daftar. — `PenilaianKPI.php` (PR-07)
10. [🔶] Incentive grup/member/rule dikelola terpisah (`ModelIncentive*` + `IncentiveCalculationService`); integrasi ke payroll belum diverifikasi di dokumen ini.
11. [⚠️] Alur detail absensi (validasi jarak/radius lokasi, status kehadiran, approval) belum diverifikasi penuh di dokumen ini (belum baca penuh `Absensi.php` & `RiwayatPresensi.php`).

## 6. Acceptance Criteria
- [ ] Pegawai dapat absen masuk/pulang (normal & manual); riwayat tercatat.
- [ ] Approval presensi mengubah status kehadiran.
- [ ] Generate register menghitung dari master gaji & KPI; baris berstatus.
- [ ] Baris yang sudah dibayar/terkunci tidak dapat diubah/dihapus.
- [ ] Pembayaran memotong kasbon dan mencatat total bersih.

## 7. Dependensi
- `ModelPresensi/ModelIzin/ModelJenisPerizinan/ModelJadwalMasuk/ModelTugas/ModelTugasTemplate/ModelPayroll/ModelFinancePayroll/ModelIncentive*`.
- Service `Payroll/*`, `IncentiveCalculationService`, `HutangPiutangService` (kasbon), `KpiCalculationService`.
- Modul: KPI, Keuangan (kas_keluar), Hutang Piutang (kasbon).

## 8. Status & Tandai
- ✅ Terverifikasi: lock file, generate register berbasis KPI (`finance_payroll`), bayar + potongan kasbon, tolak edit/delete legacy saat terkunci.
- ⚠️ Gap kritis:
  1. `Payroll::index` membuka data gaji seluruh unit. — PR-01
  2. CRUD legacy payroll tanpa role/scope; `idunit` dari POST. — PR-02
  3. `bayar` ulang menimpa `potongan_kasbon` menjadi 0. — PR-03
  4. `updateRegister` mengabaikan lock file. — PR-04
  5. `slip_gaji`/`gaji` dapat diakses semua user login; `attendance_save` tanpa otorisasi evaluator. — PR-06/PR-07
  6. `lockPayroll`/`unlockPayroll` tanpa guard role. — PR-08
- ⚠️ Belum diverifikasi penuh: detail absensi & approval, struktur komponen gaji, distribusi HO, incentive.
- Test: `PayrollGeneratorUnitTest`, `PayrollRunSummaryTest`, `HoSalaryDistributionCacheTest`, `DetailAbsenPegawaiTanpaWeightTest`, `ManagerKpiTest`. Tidak ada test untuk scope view, bayar ulang, lock `updateRegister`, dan otorisasi slip/attendance.
