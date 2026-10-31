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
2. `generateRegister` — menghitung dari master gaji + skor KPI bulan itu via `PayrollGenerator`; menulis **`finance_payroll`** dengan status `excluded`/siap. Akun `TERIMA_GAJI=0` dilewati; draft `rencana` lamanya dihapus saat overwrite.
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
8. `PenilaianKPI::slip_gaji($idakun)` tidak membatasi siapa yang meminta; `gaji()` menampilkan semua karyawan `TERIMA_GAJI=1`; route hanya `auth`. **Pengecualian sejak 2026-10-29:** SPV (40) dialihkan dari `/gaji` dan hanya boleh membuka slip ID sendiri; gaji sendiri tetap terlihat di `/penilaian_kinerja` saat membuka KPI sendiri. Karyawan lain tetap tanpa guard. — `PenilaianKPI.php` (PR-06); `tests/PenilaianThpMatchesSlipTest.php`
9. `PenilaianKPI::attendance_save()` memakai `target_id` dari POST tanpa `EvaluatorAuthorizationService`/scope; `attendance_input` hanya memfilter daftar. — `PenilaianKPI.php` (PR-07)
10. [✅] Insentif Oktober 2026+: unit 50 memakai pool HO = pool cabang + pool global. Pool cabang = rate × Σ gross profit (`SUM(sub_total - hpp_penjualan)`) cabang yang capai target `OMSET_TOKO` (rate 1% bila < 3 cabang capai, 1,5% bila ≥ 3 — satu rate untuk semua cabang yang capai); pool global = 1,5% × omzet global bila Σ omzet ≥ Σ target `OMSET_TOKO` unit non-trial capai (target toko, bukan target HO). Unit trial tidak ikut pool cabang maupun global. Dibagi karyawan HO aktif `TERIMA_GAJI=1`. Unit 1 memakai pool Probolinggo (3% × gross profit bila target capai, dibagi staff toko aktif jabatan 35/36/41/42 `TERIMA_GAJI=1`). Keduanya melewati `incentive_members`; periode sebelum Oktober 2026 tetap memakai aturan lama. — `KpiCalculationService::computeIncentive/hoPoolIncentive`; `OmsetTokoCalculator`; `tests/unit/IncentiveHoOmzetTest.php`, `tests/unit/IncentiveHoProbolinggoTest.php`
11. [⚠️] Alur detail absensi (validasi jarak/radius lokasi, status kehadiran, approval) belum diverifikasi penuh di dokumen ini (belum baca penuh `Absensi.php` & `RiwayatPresensi.php`).
12. [✅] Distribusi gaji HO di `/asset_berjalan`: pool non-SPV dibagi global (pribadi = 1 bagian, franchise = 1/unit, trial = 0); gaji SPV (jabatan 40) hanya dibebankan ke unit wilayahnya (`spv_units`) yang non-trial; tunjangan penempatan HO dibebankan penuh ke unit pribadi pertama (Probolinggo), bukan ke cabang. Beban HO per unit = aktual **menggantikan** perkiraan untuk karyawan yang sudah dibayar; karyawan lain tetap perkiraan (tidak dijumlah ganda), dan cache periode dihapus setiap payroll ditandai dibayar. Rincian per unit tidak dibatasi role tambahan; visibilitas panel tetap mengikuti role lintas cabang. Detail aturan: PRD-05 butir 16. — `HoSalaryDistributionService::alokasiKeUnit`, `Payroll::bayar`; `tests/HoSalaryDistributionSpvTest.php`
13. [✅] Take Home Pay (Estimasi) di `/penilaian_kinerja` memakai input yang sama dengan PENERIMAAN BERSIH (A-B) slip gaji: komponen gaji + penempatan + insentif + lembur − bon − kasbon. Kartu THP hanya menampilkan angka bersih; rincian komponen, lembur, dan potongan pegawai (bon + kasbon) ada di bagian atas halaman. Slip cetak tetap memisahkan Bon dan Kasbon. — `PenilaianKPI::salaryExtras`; `tests/PenilaianThpMatchesSlipTest.php`
14. [✅] Pengecualian rate insentif per karyawan per bulan disimpan di `incentive_overrides` (`employee_id`, `unit_id`, `period` YYYY-MM, `rate` persen, `reason`). `computeIncentive()` memakai rate ini menggantikan rate grup untuk periode tersebut; `rate` 0 berarti tidak mendapat insentif, dan syarat `minimum_achievement` tetap berlaku. Baris hanya berlaku pada bulan yang dicatat, sehingga bulan berikutnya otomatis kembali ke rate normal. Berlaku untuk grup non-SPV. Oktober 2026 unit 5: Dimas 67 = 1,5%, Nanda 71 = 0% (masa training). — migrasi `CreateIncentiveOverrides`; `tests/unit/IncentiveOverrideTest.php`
15. [✅] Penyesuaian gaji manual per karyawan per bulan disimpan di `payroll_adjustment` (`employee_id`, `unit_id`, `period` YYYY-MM, `jenis` = `potongan`/`bonus`, `jumlah`, `keterangan`). `potongan` dipakai untuk SP/denda, `bonus` untuk bonus kinerja. Nominal bebas dan ditentukan manager/owner; diinput lewat koreksi register `/payroll` oleh role `canInput()` (Finance, Admin Root, Manager). Satu baris per karyawan per periode per jenis, jadi potongan dan bonus bisa ada bersamaan; nominal 0 menghapus baris jenis tersebut. Efek: THP `/penilaian_kinerja` = komponen + lembur + bonus − bon − kasbon − potongan; slip menampilkan baris "Bonus" di penerimaan dan "Potongan" di pengurangan beserta `keterangan` masing-masing; saat payroll dibayar `total_bersih = max(0, total − potongan_kasbon − potongan + bonus)` dan nominalnya disimpan di `finance_payroll.potongan_lain`/`bonus_lain`. Bonus yang dicatat di menu Bonus/Lembur (`kas_keluar`) tetap terbaca sebagai potongan karena filter `%bon%`, jadi bonus kinerja memakai jalur ini. — migrasi `CreatePayrollAdjustment`; `Payroll::updateRegister/bayar`, `PenilaianKPI::salaryExtras`; `tests/PayrollAdjustmentTest.php`, `tests/PenilaianThpMatchesSlipTest.php`

16. [✅] Susun Otomatis (overwrite) menghapus baris `finance_payroll` berstatus `rencana` milik akun `TERIMA_GAJI=0` pada periode yang disusun, supaya draft lama dari sebelum flag tidak tertinggal sebagai baris `auto`. Baris `dibayar` tidak dihapus. Jumlah draft yang dihapus dilaporkan di pesan hasil. — `PayrollGenerator::generate`; `tests/PayrollGeneratorUnitTest.php::testGenerateMenghapusDraftAkunTidakDigaji`

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
  5. `slip_gaji`/`gaji` dapat diakses semua user login **kecuali SPV (40)**: `/gaji` dialihkan, slip karyawan lain ditolak, slip sendiri tetap boleh. `attendance_save` tanpa otorisasi evaluator. — PR-06/PR-07
  6. `lockPayroll`/`unlockPayroll` tanpa guard role. — PR-08
- ⚠️ Belum diverifikasi penuh: detail absensi & approval, struktur komponen gaji, rule insentif unit cabang selain HO/Probolinggo.
- Test: `PayrollGeneratorUnitTest`, `PayrollRunSummaryTest`, `HoSalaryDistributionCacheTest`, `HoSalaryDistributionSpvTest`, `DetailAbsenPegawaiTanpaWeightTest`, `ManagerKpiTest`, `unit/IncentiveHoProbolinggoTest`, `PenilaianThpMatchesSlipTest`. Tidak ada test untuk scope view, bayar ulang, lock `updateRegister`, dan otorisasi slip/attendance.
