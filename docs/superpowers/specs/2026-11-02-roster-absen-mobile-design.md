# Roster Absensi & Absen Mobile — Design (Fase 1)

Tanggal: 2026-11-02
Status: disetujui untuk direncanakan
Scope: roster bulanan + halaman `/absen` mobile. **KPI dan payroll tidak disentuh.**

Dokumen ini menggantikan draft `2026-10-30-roster-absen-design.md` untuk fase 1.
Draft lama tetap disimpan di stash dan tidak dipakai.

## 1. Tujuan

1. Mengganti master `jadwal_masuk` yang tidak terpakai dengan **roster bulanan per pegawai**.
2. Menyediakan **halaman `/absen` ringan** yang hanya menampilkan shift hari itu, dengan GPS + foto di setiap punch.
3. Menyimpan bukti absen nyata (`presensi`) sebagai sumber data untuk fase KPI berikutnya.

## 2. Keputusan yang sudah disepakati

- Roster adalah **baris per tanggal** (`presensi_roster`), bukan pola mingguan tersimpan.
  UI menerapkan pola 7 hari ke tanggal yang **belum punya baris**; baris yang sudah ada tidak ditimpa.
- Shift seragam semua cabang:
  - `PAGI` 08:45–17:00
  - `SIANG` 12:45–21:00
  - `PS` 08:45–13:00 lalu **16:55**–21:00
  - `OFF` libur, `IZIN` izin (wajib keterangan)
- PS = 4 punch: masuk, pulang break, masuk sore, pulang. Pagi/Siang = 2 punch.
- Jam mulai shift diambil dari `AttendanceScoreCalculator::SCHEDULED_TIMES` (satu sumber,
  tidak menambah konstanta baru). PS sore memakai `16:55` agar skor KPI yang sudah ada tidak berubah.
- Roster untuk semua pegawai aktif.
- Pengelola roster:
  - role `0` (Finance), `1` (Admin Root), `34` (Manager): semua unit.
  - role `35`, `47` (Admin/Kasir): hanya unit sendiri.
  - koreksi tanggal lampau: hanya role `1`.
- `/absen` hanya untuk roster sendiri; tidak ada tracking lokasi terus-menerus.
- Foto wajib, dikompres di browser (canvas, sisi panjang 1280 px, JPEG 0.8).
  Server membatasi 2 MB dan memvalidasi MIME.
- Foto baru disimpan di `writable/uploads/presensi/YYYYMM/` dan disajikan lewat route ber-`auth`.
  Foto legacy di `public/foto_presensi` tidak dipindahkan.
- Tanggal go-live disimpan di `Config\Presensi::$goLiveDate` (default `2026-11-01`).
  Sebelum tanggal itu `/absen` hanya menampilkan pesan, tidak menulis `presensi`.
- Menu `Jadwal Masuk` (id 118) diberikan ke role 0, 1, 34, 35, 47 lewat migrasi.

## 3. Non-goals

- Tidak menulis `kpi_evaluations`, `kpi_attendance_detail`, atau `kpi_attendance_off`.
- Tidak mengubah payroll, tunjangan, atau formula KPI.
- Tidak mengubah `/absensi`, `/riwayat_presensi`, `/approval_presensi`, dan absen manual legacy.
- Tidak membuat aplikasi/database terpisah, offline queue, atau tracking lokasi.
- Tidak mengubah `jadwal_masuk` lama; halaman roster memakai tabel baru.
- Tidak menangani izin parsial per sesi PS.

## 4. Model data

### 4.1 Tabel baru `presensi_roster`

| kolom | tipe | catatan |
|---|---|---|
| `id` | INT UNSIGNED PK AI | |
| `akun_idakun` | INT NOT NULL | pemilik jadwal |
| `tanggal` | DATE NOT NULL | |
| `shift` | VARCHAR(10) NOT NULL | `PAGI`, `SIANG`, `PS`, `OFF`, `IZIN` |
| `keterangan` | TEXT NULL | alasan izin/catatan |
| `created_by` / `updated_by` | INT NULL | akun penginput |
| `created_at` / `updated_at` | DATETIME NULL | |

Unique `(akun_idakun, tanggal)`, index `(tanggal)`.

### 4.2 Perubahan `presensi`

- `sesi` VARCHAR(10) NULL — `FULL` (Pagi/Siang), `PAGI`/`SORE` (PS). Baris legacy NULL = `FULL`.
- `sumber` VARCHAR(20) NULL — `mobile` atau `manual`.
- Index `idx_presensi_akun_masuk (akun_idakun, waktu_masuk)`.

Punch baru mengisi `jam_jadwal_masuk` dari kalkulator, `jam_jadwal_pulang` dari config,
`jam_toleransi` = jam mulai + 14 menit, `idjadwal_masuk` NULL, `status_absensi` 0.
Satu hari PS = 2 baris `presensi`.

## 5. Alur

### 5.1 Roster (`/jadwal_masuk`)

- Filter bulan + pegawai sesuai scope.
- Form pola 7 hari (Senin–Minggu: PAGI/SIANG/PS/OFF) → `POST /jadwal_masuk/terapkan`.
  Hanya mengisi tanggal kosong di bulan itu.
- Tabel tanggal: ubah shift + keterangan → `POST /jadwal_masuk/simpan`.
  Tanggal lampau read-only kecuali role 1.
- Semua guard di server; unit target dibaca dari akun, bukan dari POST unit.

### 5.2 Absen mobile (`/absen`)

- Baca roster hari ini:
  - tanpa roster → "Belum ada jadwal. Hubungi admin cabang."
  - `OFF`/`IZIN` → status, tanpa form.
  - `PAGI`/`SIANG` → satu kartu `FULL`.
  - `PS` → dua kartu (`PAGI`, `SORE`).
- Setiap punch mengirim `latitude`, `longitude`, `foto`, `sesi` (+ `idpresensi` untuk pulang).
- Server memvalidasi: go-live, roster, sesi, urutan PS (SORE butuh PAGI), duplikat,
  radius unit, ukuran/MIME foto.
- `status_kehadiran`: 0 = tepat (≤59 detik), 1 = toleransi (≤14 menit), 2 = telat.
  Nilai ini hanya untuk tampilan/approval; tidak dipakai KPI pada fase ini.
- Foto disajikan lewat `GET /absen/foto/{id}` dan `/absen/foto/{id}/pulang`.

## 6. Hak akses

| Aksi | Role |
|---|---|
| Lihat/kelola roster semua unit | 0, 1, 34 |
| Lihat/kelola roster unit sendiri | 35, 47 |
| Koreksi roster tanggal lampau | 1 |
| `/absen` | semua pegawai login (roster sendiri) |
| Lihat foto | pemilik; 0, 1, 34; 35/47 unit yang sama |

Semua guard di server. `auth` filter hanya memeriksa `logged_in`.

## 7. Yang tidak berubah

`presensi` tetap tidak dibaca KPI/payroll. Data lama, alur manual, approval, dan
laporan presensi tetap seperti sekarang. Fase berikutnya (di luar plan ini) yang
menyambungkan `presensi` ke KPI.

## 8. Risiko & pertanyaan terbuka

- `Config\Presensi::$goLiveDate` default `2026-11-01` — perlu konfirmasi tanggal final.
- Unit 4 (Pandaan), 5 (Genteng), dan 50 (HO) belum punya koordinat/radius.
  `/absen` akan menolak dengan pesan jelas sampai diisi.
- Draft lama memakai PS sore 17:00; fase ini memakai 16:55 mengikuti kalkulator KPI.
- Role 2 (Direktur) tidak termasuk pengelola roster; konfirmasi bila perlu.
- Route absensi tetap tanpa CSRF mengikuti pola yang ada; global CSRF memang mati.
- Approval presensi lama belum membatasi unit; di luar scope fase ini.
