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
1. `Payroll::index` — daftar baris `kas_keluar` yang bersifat payroll + `PayrollRunSummary::build`.
2. `generateRegister` — menghitung dari master gaji + skor KPI bulan itu; membuat baris berstatus `excluded`/siap.
3. `updateRegister` — ubah; tolak bila sudah `dibayar`.
4. `lockPayroll/unlockPayroll` — file lock `writable/locks/payroll.json`.
5. `insert/update/delete` — CRUD baris kas_keluar payroll; delete/update ditolak bila terkunci.
6. `bayar` — set status `dibayar`; potong kasbon (settle idempotent); hitung `total_bersih`; tulis `potongan_kasbon`.

## 5. Business Rules (Bukti)
1. Generate register berasal dari master gaji + **skor KPI bulan itu** (blok `KpiCalculationService`). — `Payroll::generateRegister`
2. Baris payroll disimpan sebagai `kas_keluar` dengan status (`excluded`/`dibayar`). — `Payroll::generateRegister/updateRegister/bayar`
3. Update/delete ditolak bila status `dibayar` atau baris terkunci. — `Payroll::update/delete`
4. Lock/unlock memakai file `writable/locks/payroll.json` (bukan kolom DB). — `Payroll::lockPayroll/unlockPayroll`
5. Bayar → settle kasbon pegawai (idempotent per payroll); `total_bersih = total - potongan_kasbon`. — `Payroll::bayar` + `HutangPiutangService::settleKasbonFromPayroll`
6. [🔶] Incentive grup/member/rule dikelola terpisah (`ModelIncentive*` + `IncentiveCalculationService`); integrasi ke payroll belum diverifikasi di dokumen ini.
7. [⚠️] Alur detail absensi (validasi jarak/radius lokasi, status kehadiran, approval) belum diverifikasi penuh di dokumen ini (belum baca penuh `Absensi.php` & `RiwayatPresensi.php`).

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
- ✅ Terverifikasi: lock file, generate register berbasis KPI, bayar + potongan kasbon, tolak edit/delete saat terkunci/dibayar.
- ⚠️ Belum diverifikasi penuh: detail absensi & approval, struktur komponen gaji, distribusi HO, incentive.
- Test: `PayrollGeneratorUnitTest`, `PayrollRunSummaryTest`, `HoSalaryDistributionCacheTest`, `DetailAbsenPegawaiTanpaWeightTest`, `ManagerKpiTest`.
