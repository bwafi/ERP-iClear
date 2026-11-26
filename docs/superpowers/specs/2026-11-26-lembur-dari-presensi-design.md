# Lembur dari Presensi untuk Teknisi & Kepala Toko — Desain (Opsi B)

**Tanggal:** 2026-11-26
**Status:** disetujui 2026-11-26
**Terkait:** `docs/prd/modul/PRD_MODUL_07_HRD_Payroll.md` (butir 13, 15, 17),
`docs/prd/modul/PRD_MODUL_08_KPI_Penilaian.md` (butir 16),
`docs/architecture/unit-scope.md`, `AGENTS.md` bagian C dan D.

## Intent

Lembur berhenti dibaca dari `kas_keluar` dan dihitung otomatis dari presensi.
Penerima dibatasi hanya **Teknisi (jabatan 36)** dan **Kepala Toko (jabatan 41)**
di unit operasional. Jabatan lain — termasuk HO, admin, SPV, Kepala Teknisi, dan
Teknisi Mesin — tidak pernah menerima lembur walaupun absennya penuh. Nominal
**Rp35.000 per hari** dan langsung menjadi komponen payroll tanpa review manual.

## Keputusan yang sudah disepakati

| No | Keputusan | Catatan |
|---|---|---|
| 1 | Sumber lembur = `presensi_roster` | Bukan lagi `kas_keluar` untuk periode presensi |
| 2 | Hari lembur = roster dengan `shift = 'FULL'` dan tanggal sudah lewat | Bukan `presensi.sesi`; tanggal berjalan/belum lewat tidak dihitung |
| 3 | Penerima hanya jabatan **36** dan **41** | Daftar putih, bukan daftar hitam |
| 4 | Nominal tetap **Rp35.000/hari** | Satu tanggal dihitung sekali |
| 5 | Otomatis masuk payroll (Opsi B) | Tidak ada langkah approve manual |
| 6 | **Tidak ada snapshot lembur di `finance_payroll`** | Total lembur dihitung ulang dari roster dan ditampilkan di `/penilaian_kinerja` |
| 7 | Cut-off `2026-11-01` | Periode sebelum itu tetap memakai `kas_keluar` |
| 8 | `/penilaian_kinerja` memakai service yang sama dengan payroll | Angka lembur yang tampil = angka yang dihitung payroll |

## Revisi 2026-11-26: sumber lembur adalah roster FULL

Asumsi awal “absen full = `presensi.sesi = 'FULL'`” **salah**. `sesi` hanya
menandai mode punch: semua shift non-PS (PAGI, SIANG, FULL) menulis
`sesi = 'FULL'`. Akibatnya 12 hari kerja biasa Ikmal terhitung sebagai 12 hari
lembur.

Aturan final yang disetujui user:

- Lembur diambil dari **`presensi_roster.shift = 'FULL'`**, bukan `presensi.sesi`.
- Hanya **tanggal yang sudah lewat** (`tanggal < hari ini`) yang dihitung;
  tanggal berjalan/belum lewat tidak dihitung.
- Satu tanggal dihitung sekali walau ada baris roster ganda.
- Efek: Ikmal (akun 62) November 2026 = 3 hari (3, 17, 24 Nov) = Rp105.000.

Konsekuensi yang dicatat: aturan ini berbasis jadwal, jadi FULL yang sudah lewat
tetap dihitung walau tidak ada punch (contoh Ikmal 3 Nov tidak punya presensi).
Kalau nanti ingin mensyaratkan punch hadir, itu perubahan aturan terpisah.

## Non-goals

- Tidak mengubah nominal, rumus, atau bobot KPI. Lembur tetap komponen gaji,
  bukan komponen skor KPI.
- Tidak mengubah tarif atau alur `bon`, kasbon, komisi, insentif, dan tunjangan.
- Tidak mengubah payroll yang sudah `dibayar`, jurnal, atau data historis.
- Tidak mengaktifkan/mengubah alur DP service.
- Tidak menambah penerima lembur di luar jabatan 36 dan 41, termasuk Teknisi
  Mesin (49), Kepala Teknisi (38), SPV (40), dan HO.
- Tidak menghapus baris `kas_keluar` LEMBUR lama.
- **Tidak mengubah schema `finance_payroll`.** Tidak ada kolom snapshot lembur
  baru; total lembur dihitung ulang saat dibutuhkan.
- Tidak memperbaiki masalah baris payroll ganda per pegawai/bulan (isu terpisah
  yang sudah tercatat di audit).

## Kondisi aktual (terverifikasi)

- ✅ `presensi` menyimpan `waktu_masuk`, `waktu_pulang`, `sesi`, `akun_idakun`,
  `unit_idunit`, `status_kehadiran`. Nilai `sesi` yang ada di data:
  `FULL` (255), `PAGI` (79), `SORE` (72), `NULL` (14).
- ✅ Shift tidak disimpan di `presensi`; shift ada di `presensi_roster.shift`
  (`PAGI/SIANG/FULL/PS/OFF/IZIN/SAKIT/CUTI`). Karena itu aturan desain memakai
  `presensi.sesi`, bukan roster.
- ✅ Data presensi baru terisi mulai **November 2026** (407 baris, 24 pegawai;
  361 sudah absen pulang, 46 belum). Tidak ada presensi Oktober 2026.
- ✅ November 2026, dengan aturan `sesi = 'FULL'`:
  - Teknisi (36): 7 pegawai, 55 hari FULL lengkap, 13 hari FULL tanpa pulang.
  - Kepala Toko (41): 5 pegawai, 44 hari FULL lengkap, 7 hari FULL tanpa pulang.
  - Teknisi Mesin (49): 1 pegawai, 7 hari FULL lengkap (di luar cakupan).
- ✅ `akun` menyimpan `ID_JABATAN`, `ID_UNIT`, `STATUS_PEGAWAI`, `TERIMA_GAJI`,
  `deleted`. Filter karyawan aktif yang dipakai `PayrollGenerator::daftarKaryawan()`
  adalah `STATUS_PEGAWAI = 1` dan `deleted IS NULL OR deleted = ''`.
- ✅ `unit.is_operational` ada dan Head Office (`idunit = 50`) ber-flag `0`.
- ✅ `PayrollGenerator::daftarLembur()` menjumlah `kas_keluar` kategori 10 dengan
  `deskripsi LIKE '%lembur%'`, dikelompokkan per `penerima` numerik.
- ✅ `PenilaianKPI::salaryExtras()` juga membaca lembur dari `kas_keluar`
  kategori 10. Kalau hanya payroll yang diubah, slip/THP akan berbeda dari
  register payroll.
- ✅ `finance_payroll` **belum punya kolom lembur**. Kolom yang ada: `total`,
  `notes`, `potongan_kasbon`, `potongan_lain`, `bonus_lain`, `total_bersih`.
- ✅ `Payroll::bayar()` hanya mengubah status/`paid_date`/potongan; tidak membuat
  baris `kas_keluar` dan tidak posting jurnal.
- ✅ Praktik lembur manual: 118 baris Rp35.000, tersebar di Teknisi (65 baris,
  6 orang), Kepala Toko (47 baris, 4 orang), SPV (6 baris), dan 1 baris anomali
  Rp3.821.500 yang `penerima`-nya bukan ID akun valid.
- ✅ PRD 07 butir 13, 15, dan 17 masih menyebut lembur dari `kas_keluar`; PRD
  perlu diperbarui saat implementasi.

## Aturan bisnis final

### 1. Kelayakan pegawai

Pegawai dianggap layak menerima lembur pada suatu periode jika **semua** benar:

- `akun.ID_JABATAN IN (36, 41)`
- `akun.STATUS_PEGAWAI = 1`
- `akun.deleted IS NULL OR akun.deleted = ''`
- `akun.TERIMA_GAJI = 1`
- unit pegawai `unit.is_operational = 1`
- `akun.ID_UNIT <> 50` (guard tambahan untuk Head Office)

### 2. Hari yang dihitung

Satu hari dihitung lembur jika ada baris `presensi_roster` dengan:

- `presensi_roster.shift = 'FULL'`
- `presensi_roster.tanggal` berada di dalam periode payroll
- `presensi_roster.tanggal < hari ini` (hanya tanggal yang sudah lewat)

Tanggal yang sama dihitung **satu kali** walau ada lebih dari satu baris FULL.
Shift `PAGI`, `SIANG`, `PS`, dan `OFF` tidak dihitung. Punch `presensi` tidak
menjadi syarat; sumbernya jadwal roster.

### 3. Nominal

`lembur = jumlah_hari × 35.000`, dibulatkan ke rupiah penuh. Tarif disimpan
sebagai konstanta service (bukan input bebas), supaya konsisten dengan praktik
Rp35.000/hari yang sudah berjalan.

### 4. Periode dan cut-off

- Periode `>= 2026-11-01`: sumber roster shift FULL.
- Periode `< 2026-11-01`: sumber `kas_keluar` lama (fallback historis), supaya
  draft payroll lama yang belum dibayar tidak kehilangan angka.
- Baris payroll berstatus `dibayar` tidak pernah dihitung ulang.

## Model data

**Tidak ada perubahan schema.** Desain ini sengaja tidak menambah kolom lembur di
`finance_payroll`.

- `finance_payroll` tetap menyimpan `total` (termasuk lembur) dan `notes`.
- `notes` diisi rincian `Lembur N hari × Rp 35.000 = Rp X` saat payroll
  digenerate. Ini menjadi jejak audit tekstual untuk angka yang dipakai payroll,
  tanpa perlu kolom baru.
- Total lembur yang ditampilkan di `/penilaian_kinerja` dihitung ulang dari
  roster lewat service yang sama.

Konsekuensinya: kalau roster dikoreksi setelah payroll dibayar, angka lembur
yang tampil di halaman penilaian bisa berbeda dari yang sudah dibayar. Selisih itu
tidak diubah otomatis; `notes` payroll adalah catatan nominal yang benar-benar
dipakai saat generate.

## Service baru

`App\Services\Payroll\AttendanceOvertimeService`

Konstanta:

- `TARIF_HARIAN = 35000`
- `JABATAN_PENERIMA = [36, 41]`
- `MULAI_SUMBER_PRESENSI = '2026-11-01'`

API:

| Method | Keluaran |
|---|---|
| `ringkasanPeriode(string $bulan): array` | `[employeeId => ['hari' => int, 'tanggal' => string[], 'nominal' => int]]` |
| `untukPegawai(int $employeeId, string $bulan): array` | satu entri seperti di atas (`hari` 0 bila tidak layak) |
| `totalPeriode(string $bulan): array` | `[employeeId => int nominal]` |
| `sumberPeriode(string $bulan): string` | `'presensi'` atau `'kas_keluar'` sesuai cut-off |

Kueri inti (satu tempat, bukan tersebar di controller):

```sql
SELECT r.akun_idakun AS pegawai_id, r.tanggal
FROM presensi_roster r
JOIN akun a ON a.ID_AKUN = r.akun_idakun
JOIN unit u ON u.idunit = a.ID_UNIT
WHERE r.shift = 'FULL'
  AND r.tanggal >= ? AND r.tanggal <= ?
  AND r.tanggal < ?
  AND a.ID_JABATAN IN (36, 41)
  AND a.STATUS_PEGAWAI = 1
  AND (a.deleted IS NULL OR a.deleted = '')
  AND a.TERIMA_GAJI = 1
  AND u.is_operational = 1
  AND a.ID_UNIT <> 50
GROUP BY r.akun_idakun, r.tanggal
```

Batas periode memakai `>= awal bulan` dan `<= akhir bulan`, ditambah
`tanggal < hari ini` supaya jadwal yang belum berjalan tidak dibayar.

## Integrasi payroll

`PayrollGenerator`:

1. `daftarLembur()` lama dipertahankan sebagai `daftarLemburKasKeluar()` untuk
   periode sebelum cut-off.
2. Method baru `lemburPeriode(string $bulan)` memilih sumber lewat
   `AttendanceOvertimeService::sumberPeriode()`.
3. Nominal lembur langsung dipakai saat menghitung `total`; **tidak** disimpan ke
   kolom baru. Yang tersimpan hanya `total` dan `notes` seperti sekarang.
4. `susunCatatan()` menampilkan `Lembur N hari × Rp 35.000 = Rp X` untuk periode
   presensi, dan tetap `Lembur Rp X` untuk periode lama.
5. Baris payroll `dibayar` tetap dilewati; draft `rencana` boleh dihitung ulang
   saat `overwrite` aktif, sehingga total ikut menyesuaikan presensi terbaru.
6. `hitungGajiUnit()` (dipakai Tutup Kasir) memakai sumber lembur yang sama,
   sehingga perkiraan laba memakai angka presensi, bukan `kas_keluar` manual.

## Integrasi penilaian dan slip

`PenilaianKPI::salaryExtras()` mengubah **hanya bagian lembur**:

1. Periode `>= 2026-11-01`: hitung langsung lewat
   `AttendanceOvertimeService::untukPegawai()`. Tidak ada pembacaan snapshot.
2. Periode lebih lama: pakai `kas_keluar` seperti sekarang.
3. `bon`, kasbon, potongan, dan bonus tetap dari sumber masing-masing.

Halaman `/penilaian_kinerja` (dan slip) akan menampilkan total lembur yang
dihitung dari roster, memakai service yang sama dengan payroll. Kalau roster
dikoreksi, angka yang tampil ikut berubah pada kunjungan berikutnya.

## Guardrail lembur manual

- Sejak cut-off, baris `kas_keluar` kategori 10 berisi `LEMBUR` untuk pegawai
  jabatan 36/41 **tidak lagi masuk payroll**.
- Halaman `/payroll` dan `/penilaian_kinerja` menampilkan peringatan bila pada
  periode presensi masih ada baris LEMBUR manual untuk pegawai yang eligible:
  “Lembur manual terdeteksi dan tidak dihitung lagi di payroll.”
- Baris lama tidak dihapus atau diubah otomatis. Kalau nominalnya sudah
  dibayarkan sebagai kas, itu tetap tercatat sebagai pengeluaran; Finance perlu
  memutuskan koreksinya secara manual agar tidak terjadi pembayaran ganda.

## Command audit

`php spark presensi:lembur --bulan=2026-11 [--pegawai=67] [--csv]`

- Mode default **dry-run**: hanya menampilkan per pegawai `hari`, daftar tanggal,
  dan nominal; tidak menulis apa pun.
- Opsi `--csv` untuk memudahkan Finance memeriksa sebelum payroll di-generate.
- Mengikuti pola `app/Commands/PresensiSyncKpi.php` (grup `presensi`).

Command ini bukan langkah approval; payroll tetap otomatis. Fungsinya untuk
audit dan pembuktian angka.

## Edge case

| Kasus | Perilaku |
|---|---|
| Dua baris roster FULL di tanggal sama | Dihitung 1 hari |
| Roster FULL tanpa punch | Tetap dihitung (sumber = jadwal) |
| Shift PAGI / SIANG / PS / OFF | Tidak dihitung |
| Tanggal FULL hari ini atau setelahnya | Tidak dihitung |
| Pegawai jabatan 36/41 di unit 50 | Ditolak oleh `is_operational` dan guard `ID_UNIT <> 50` |
| Jabatan 38, 40, 49 | Tidak dihitung |
| Pegawai `STATUS_PEGAWAI != 1` / `deleted` / `TERIMA_GAJI = 0` | Tidak dihitung |
| Roster dikoreksi setelah payroll dibayar | `total` payroll tidak berubah; `/penilaian_kinerja` menghitung ulang sehingga bisa berbeda. Rincian nominal yang dibayar tetap ada di `notes` payroll |
| Roster dikoreksi saat payroll masih `rencana` | Ikut berubah saat generate/overwrite |
| Periode sebelum November 2026 | Fallback `kas_keluar` |
| Payroll bulan berjalan digenerate sebelum hari terakhir | Tanggal setelah hari generate belum dihitung; generate ulang setelah tanggal itu lewat |

## Risiko dan hal yang perlu dikonfirmasi

- ⚠️ **Angka naik signifikan.** November 2026: ± Rp3.465.000 (55 hari Teknisi +
  44 hari Kepala Toko) vs realisasi manual Oktober Rp1.575.000. Perlu konfirmasi
  bahwa kenaikan ini memang diharapkan, bukan efek samping definisi FULL.
- ⚠️ **Kas keluar lembur hilang dari ledger.** Karena lembur tidak lagi dicatat
  sebagai `kas_keluar`, pembayaran lembur hanya terlihat di total payroll.
  Finance perlu memastikan pembayaran gaji (termasuk lembur) tetap tercatat di
  Kas Bank sebagai satu transaksi. Ini konsekuensi langsung Opsi B.
- ⚠️ **Tanpa snapshot, angka bisa bergeser setelah dibayar.** Kalau presensi
  dikoreksi setelah payroll `dibayar`, halaman penilaian akan menampilkan angka
  baru sementara `total` payroll lama tidak berubah. `notes` payroll tetap
  mencatat rincian yang dibayar. Kalau ini tidak diinginkan, perlu aturan koreksi
  manual (bukan snapshot kolom).
- 🔶 **Keterlambatan tidak membatalkan lembur.** Aturan yang dipilih hanya
  melihat `sesi = 'FULL'`, jadi pegawai FULL yang telat tetap dapat Rp35.000.
  Kalau ingin telat membatalkan lembur, perlu aturan tambahan.
- 🔶 **Hari OFF yang tetap masuk FULL dihitung lembur.** Karena sumbernya
  presensi, bukan roster, pegawai yang absen FULL di hari OFF tetap dapat
  lembur. Ini justru wajar untuk lembur hari libur, tapi perlu dikonfirmasi.
- 🔶 **Pegawai dengan dua baris payroll sebulan** (isu lama) bisa membuat
  lembur muncul di lebih dari satu baris bila keduanya dibuat manual. Generator
  otomatis hanya menyentuh baris pertama; isu ini tidak diperbaiki di sini.

## Rencana implementasi (setelah desain disetujui)

1. `AttendanceOvertimeService` + unit test murni.
2. Integrasi `PayrollGenerator` (sumber, total, catatan) + regression test.
3. Integrasi `PenilaianKPI::salaryExtras()` + test konsistensi slip/THP.
4. Peringatan lembur manual di `/payroll` dan `/penilaian_kinerja`.
5. Command `presensi:lembur` (dry-run) + test.
6. Update PRD 07 butir 13, 15, 17 dan PRD 08 bila needed.
7. Jalankan `vendor/bin/phpunit -c phpunit.xml` terarah dan seluruh suite.

## Rencana test

- `AttendanceOvertimeServiceTest`
  - hanya jabatan 36/41; 38/40/49 ditolak
  - unit non-operasional dan unit 50 ditolak
  - `sesi FULL` dihitung, `PAGI/SORE/NULL` tidak
  - `waktu_pulang NULL` tetap dihitung
  - tanggal ganda dihitung sekali
  - batas awal/akhir bulan
  - `STATUS_PEGAWAI`, `deleted`, `TERIMA_GAJI` difilter
  - tarif 35.000 dan cut-off menghasilkan `sumberPeriode` yang benar
- `PayrollGeneratorLemburTest`
  - periode presensi memakai service, bukan `kas_keluar`
  - `total` memasukkan lembur
  - `notes` memuat `N hari × Rp 35.000`
  - baris `dibayar` tidak berubah
  - periode sebelum cut-off tetap memakai `kas_keluar`
- `PenilaianThpMatchesSlipTest` (perluas)
  - lembur periode presensi dihitung dari service yang sama dengan payroll
  - THP estimasi = slip untuk periode yang sama
- `PresensiLemburCommandTest`
  - dry-run tidak menulis
  - output per pegawai dan total benar

## Pertanyaan terbuka

1. Apakah pegawai FULL yang **telat** tetap mendapat lembur? (default desain: ya)
2. Apakah hari **OFF** yang tetap absen FULL dihitung lembur? (default desain: ya)
3. Apakah Finance setuju bahwa pembayaran lembur tidak lagi dibuat sebagai baris
   `kas_keluar` terpisah, melainkan menyatu di pembayaran payroll?
