# KPI Team IT (Jabatan 45) — Operasional

Dokumen ini menjelaskan seluruh rangkaian KPI Team IT: dari backup database
harian, pengecekan uptime terjadwal, sinkronisasi snapshot bulanan, sampai
pembacaan angka di halaman penilaian. Semua komponen dihitung dari bukti yang
tersimpan (file histori), bukan angka yang diketik.

Untuk detail perintah backup, lihat juga `docs/operations/backup-database.md`.

## 1. Komponen dan bobot

Berlaku sejak **2026-11-01**. Bobot lama (Omset 30, Bug Minor 10, Operasional 25,
Ecommerce 15, Fitur 20) ditutup per 2026-10-31 agar skor periode lalu tidak
berubah.

| Komponen | Bobot | Rumus | Sumber bukti |
|---|---|---|---|
| Development (`IT_DEVELOPMENT`) | 25 | `min(commit feat ÷ 15 × 100, 100)` | GitHub, 3 repo |
| Penyelesaian Bug (`IT_BUG_FIX`) | 25 | `min(commit fix ÷ 30 × 100, 100)` | GitHub, 3 repo |
| Backup (`IT_BACKUP`) | 25 | `hari backup sukses ÷ hari diperiksa × 100`; uji restore gagal → maks 50 | `backups/history.jsonl`, `backups/restore.jsonl` |
| System Uptime (`IT_UPTIME`) | 15 | `hari uptime OK ÷ hari diperiksa × 100` | `backups/uptime.jsonl` |
| Dokumentasi (`IT_DOCS`) | 10 | `min(commit docs ÷ 5 × 100, 100)` | GitHub, 3 repo |

Target 15/30/5 adalah default service. Kalau baris `kpi_targets` untuk
`position_id = 45`, `unit_id = NULL`, `context = default` sudah ada, nilai di
tabel itu yang dipakai.

## 2. Konfigurasi `.env`

```env
# GitHub (commit feat/fix/docs)
GITHUB_TOKEN = github_pat_xxxxxxxx
GITHUB_REPOS = 'bwafi/ERP-iClear:main, bwafi/CrmIclear:main, bwafi/iClear:main'
GITHUB_AUTHORS =            # opsional: email/login, kosong = semua author

# Cek uptime terjadwal
KPI_UPTIME_URL = https://erp.icleargroup.id/login
KPI_UPTIME_MARKER =         # opsional, teks wajib ada di halaman
KPI_UPTIME_TIMEOUT = 10
KPI_TIMEZONE = Asia/Jakarta

# Backup (lihat docs/operations/backup-database.md)
BACKUP_KEEP_DAYS = 14
BACKUP_TIMEZONE = Asia/Jakarta
```

Tanggal untuk KPI, backup, dan uptime memakai `KPI_TIMEZONE`/`BACKUP_TIMEZONE`
(WIB), bukan timezone aplikasi yang UTC. Tanpa ini, cek pukul 22:00 WIB akan
tercatat sebagai tanggal UTC berikutnya dan KPI bergeser satu hari.

## 3. Perintah

Semua perintah dijalankan dari root aplikasi:

```sh
cd /home/icleargr/SubDomain/erp.icleargroup.id/ERP
```

> **Penting:** CodeIgniter CLI **tidak menerima** bentuk `--opsi=nilai`.
> Tulis dengan spasi: `--month 2026-11`, bukan `--month=2026-11`. Bentuk
> dengan `=` diabaikan diam-diam (perintah memakai nilai default).

### 3.1 `backup:db` — backup database harian

```sh
php spark backup:db
php spark backup:db --keep-days 30
php spark backup:db --dir /home/USER/backups
```

Menulis `backups/namadb_YYYY-MM-DD_HHMMSS.sql.gz` dan menambah satu baris ke
`backups/history.jsonl`. Rincian opsi dan alur verifikasi dump ada di
`docs/operations/backup-database.md`.

### 3.2 `it:uptime-check` — satu cek uptime

```sh
php spark it:uptime-check
php spark it:uptime-check --url https://erp.icleargroup.id/login
php spark it:uptime-check --marker login --timeout 15
```

Satu jalan = satu HTTP GET + satu baris ledger. Yang dianggap sukses: HTTP 200
setelah redirect dalam batas waktu (`KPI_UPTIME_TIMEOUT`, default 10 detik).
Kalau `KPI_UPTIME_MARKER` diisi, halaman juga harus memuat teks itu
(perbandingan tanpa peduli huruf besar-kecil).

Cek **gagal tidak membuat perintah error**: exit code tetap `0` dan alasannya
tertulis di log, supaya cron tidak mengirim peringatan tiga kali sehari ketika
situs memang sedang down. Exit `1` hanya untuk kesalahan konfigurasi (URL kosong,
timeout bukan angka).

### 3.3 `it:kpi-sync` — snapshot bulanan

```sh
php spark it:kpi-sync                        # bulan berjalan
php spark it:kpi-sync --month 2026-11
php spark it:kpi-sync --month 2026-11 --restore ok
php spark it:kpi-sync --month 2026-11 --restore failed
```

Mengumpulkan commit GitHub, hari backup, dan hari uptime, lalu menulis satu
baris snapshot ke tabel `kpi_it_metrics` (upsert per periode, aman dijalankan
berulang). Halaman penilaian hanya membaca tabel ini, jadi tidak ada panggilan
jaringan saat halaman dibuka dan angka bulan lalu tidak berubah.

Opsi `--restore` mencatat hasil uji restore bulanan ke `backups/restore.jsonl`
sebelum sinkronisasi. Uji restore yang **gagal** membatasi skor Backup maksimal
50.

## 4. Cron di production

Buka **cPanel → Cron Jobs**. Path aplikasi production:
`/home/icleargr/SubDomain/erp.icleargroup.id/ERP`, binary PHP
`/usr/local/bin/php`.

Server production memakai jam **WIB** — dicek 24 November 2026 di Terminal
cPanel: `date` menghasilkan `Tue Nov 24 13:34:28 WIB 2026` dan
`date +"%Z %z"` menghasilkan `WIB +0700`. Jadi cron production memakai jam WIB
langsung dan tiga baris berikut sudah sesuai:

```cron
# 1. Backup database — 02:15 WIB
15 2 * * * cd /home/icleargr/SubDomain/erp.icleargroup.id/ERP && /usr/local/bin/php spark backup:db --keep-days 14 >> writable/logs/backup-cron.log 2>&1

# 2. Cek uptime — 02:00, 08:00, 22:00 WIB
0 2,8,22 * * * cd /home/icleargr/SubDomain/erp.icleargroup.id/ERP && /usr/local/bin/php spark it:uptime-check >> writable/logs/uptime-cron.log 2>&1

# 3. Snapshot KPI — 02:45 WIB, setelah backup dan cek terakhir hari itu
45 2 * * * cd /home/icleargr/SubDomain/erp.icleargroup.id/ERP && /usr/local/bin/php spark it:kpi-sync >> writable/logs/kpi-cron.log 2>&1
```

Cek dulu dengan `date` sebelum menempel, karena cron mengikuti jam server:

| `date +"%Z %z"` | Jam cron yang dipakai |
|---|---|
| `WIB +0700` | `15 2`, `0 2,8,22`, `45 2` (production sekarang) |
| `UTC +0000` | `15 19`, `0 19,1,15`, `45 19` (hari sebelumnya di UTC) |

Kalau server memakai offset lain (mis. `WITA +0800`), geser jamnya sebesar
selisih terhadap WIB.

Untuk memastikan cron benar-benar memakai jam yang sama dengan `date`, pasang
cron percobaan satu menit, tunggu dua menit, lalu hapus:

```cron
* * * * * date >> /home/icleargr/cron-clock.txt
```

```sh
cat /home/icleargr/cron-clock.txt
```

Cek setelah dipasang:

```sh
tail -n 5 writable/logs/uptime-cron.log
tail -n 5 writable/logs/kpi-cron.log
tail -n 5 backups/uptime.jsonl
```

## 5. File histori

Semua bukti berupa file JSONL (satu baris JSON per kejadian) di folder
`BACKUP_DIR` (default `ROOTPATH/backups`).

### `history.jsonl` — backup

```json
{"at":"2026-11-24T02:15:03+07:00","status":"ok","database":"icleargr_erp","file":"icleargr_erp_2026-11-24_021503.sql.gz","bytes":52428800,"tables":187,"keep_days":14,"deleted":1,"error":null}
```

Hari backup dianggap **sukses** bila ada minimal satu baris dengan
`status = ok` dan `tables > 0` pada tanggal itu.

### `restore.jsonl` — uji restore bulanan

```json
{"at":"2026-11-24T10:00:00+07:00","status":"ok"}
```

Diisi lewat `it:kpi-sync --restore ok|failed`. Baris terakhir dalam periode
yang menentukan. Tidak ada baris = belum diuji (tidak memotong skor).

### `uptime.jsonl` — cek uptime

```json
{"at":"2026-11-24T08:00:04+07:00","ok":true,"http":200,"ms":76,"url":"https://erp.icleargroup.id/login","error":null}
{"at":"2026-11-24T22:00:03+07:00","ok":false,"http":0,"ms":5001,"url":"https://erp.icleargroup.id/login","error":"HTTP gagal: Operation timed out after 5000 milliseconds"}
```

Sengaja file, bukan tabel database: kalau MySQL mati, cek tetap tercatat gagal,
sehingga hari itu benar-benar dinilai gagal — bukan dianggap "tidak termonitor".

## 6. Aturan penilaian

### Commit (Development, Bug Fix, Dokumentasi)

- Dihitung dari 3 repo di `GITHUB_REPOS`, branch `main`, memakai *subject*
  Conventional Commits: `feat:`, `feat(scope):`, `fix(scope):`, `docs(scope):`.
  `feat(scope)!:` tetap dihitung.
- `GITHUB_AUTHORS` kosong = semua commit di repo ikut dihitung. Isi dengan email
  atau login GitHub untuk membatasi ke tim IT.
- Skor dibatasi 100.

### Backup

- Hari sukses = `status=ok` dan `tables>0` di `history.jsonl` pada tanggal itu.
- Hari tanpa baris **dihitung gagal**.
- Kalau `history.jsonl` belum ada sama sekali → komponen `N/A` (bukan 0).
- Uji restore gagal pada bulan itu → skor maksimal 50.

### Uptime

- Tiga cek sehari (02:00, 08:00, 22:00 WIB).
- **Satu cek sukses = hari OK.** Semua cek gagal = hari gagal.
- Hari tanpa cek sama sekali **setelah** pengecekan pernah berjalan = gagal
  (server mati total tidak boleh lolos).
- Hari **sebelum** baris pertama di `uptime.jsonl` tidak dihitung. Jadi bulan
  pertama pemasangan tidak dihukum karena monitoring belum ada.
- Kalau file dihapus, "mulai monitoring" ikut bergeser ke baris pertama yang
  baru. Jangan hapus file ini.
- Baris JSON rusak diabaikan, tidak membuat sinkronisasi error.

### Periode berjalan

Bulan berjalan hanya menghitung hari yang sudah selesai (sampai kemarin), supaya
cron hari ini yang belum jalan tidak dihitung gagal. Bulan lampau memakai
seluruh hari.

### N/A versus 0

`N/A` berarti sumber datanya belum ada (belum sync, ledger kosong, token kosong).
Komponen `N/A` **dikeluarkan** dari total berbobot, tidak dianggap nol. `0`
berarti sumbernya ada dan hasilnya memang nol (mis. semua cek uptime gagal).

## 7. Troubleshooting

| Gejala | Penyebab umum | Tindakan |
|---|---|---|
| Uptime `N/A` | `backups/uptime.jsonl` tidak ada | Jalankan `php spark it:uptime-check`, pastikan folder `backups` bisa ditulis user cron |
| Semua hari uptime gagal | Cron tidak jalan, atau jam cron salah zona | `crontab -l`, `date`, cek `writable/logs/uptime-cron.log` |
| Cek gagal terus padahal situs normal | URL salah, marker tidak cocok, timeout terlalu pendek | Uji manual `php spark it:uptime-check --marker "" --timeout 20` |
| Commit 0 semua | Token/repo salah, branch beda, author filter tidak cocok | `php spark it:kpi-sync`, lihat `detail.github` di baris `kpi_it_metrics` |
| Skor backup rendah | Backup tidak jalan beberapa hari, atau `tables` 0 | `tail backups/history.jsonl`, jalankan `php spark backup:db` manual |
| Perintah memakai nilai default padahal opsi diisi | Memakai bentuk `--opsi=nilai` | Ulangi dengan spasi: `--month 2026-11` |
| `php spark` fatal di laptop | PHP 8.5 tidak kompatibel dengan CodeIgniter 4.4.8 dan ekstensi `intl` tidak ada | Pakai container proyek: `docker compose exec erp-app php spark ...` |
| Snapshot lama tidak berubah | `it:kpi-sync` belum jalan lagi | Jadwalkan cron harian atau jalankan manual |

## 8. Checklist pemasangan (production)

Jalankan dari root aplikasi di server:
`cd /home/icleargr/SubDomain/erp.icleargroup.id/ERP`.

1. **Backup dulu.**
   ```sh
   /usr/local/bin/php spark backup:db
   ```
   Catat nama file yang muncul di `backups/` — itu jalan pulang kalau deploy
   bermasalah.
2. **Tarik kode dan migrasi.**
   ```sh
   git pull origin main
   /usr/local/bin/php spark migrate
   /usr/local/bin/php spark cache:clear
   ```
   Tidak perlu `composer install` untuk perubahan ini (tidak ada dependency
   baru). Migrasi menambah tabel `kpi_it_metrics` dan baris komponen/bobot/target
   KPI IT dengan tanggal berlaku 2026-11-01.
3. **Isi `.env`** (bukan `.env.example`):
   `GITHUB_TOKEN`, `GITHUB_REPOS`, `GITHUB_AUTHORS`, `KPI_UPTIME_URL`,
   `KPI_UPTIME_MARKER`, `KPI_UPTIME_TIMEOUT`, `KPI_TIMEZONE`, `BACKUP_*`.
4. **Pastikan folder backup bisa ditulis user cron.**
   ```sh
   touch backups/uptime.jsonl && ls -l backups/uptime.jsonl
   ```
   Pemiliknya harus user yang menjalankan cron cPanel. Kalau bukan, ledger
   uptime tidak akan pernah tertulis dan komponennya tetap N/A.
5. **Pasang tiga baris cron** pada bagian 4 (production WIB: `15 2`, `0 2,8,22`,
   `45 2`).
6. **Jalankan manual sekali** untuk memastikan tidak ada error:
   ```sh
   /usr/local/bin/php spark backup:db
   /usr/local/bin/php spark it:uptime-check
   /usr/local/bin/php spark it:kpi-sync
   ```
7. **Catat uji restore bulanan:** `/usr/local/bin/php spark it:kpi-sync --restore ok`.
8. **Cek hasilnya:** buka `/penilaian/kpi`, pilih pegawai Team IT, pastikan lima
   komponen muncul dengan angka atau `N/A` yang masuk akal.
9. **Periksa log setelah satu siklus penuh (24 jam):**
   `tail -n 5 writable/logs/uptime-cron.log` dan `backups/uptime.jsonl`.

### Kalau deploy bermasalah

- Kode: `git log --oneline -5` untuk melihat commit aktif, lalu
  `git checkout <commit-sebelumnya>` dan `php spark cache:clear`.
- Database: pulihkan dump dari langkah 1
  (`gunzip < backups/namadb_*.sql.gz | mysql -u USER -p NAMADB`) **hanya** kalau
  memang perlu; migrasi KPI IT tidak mengubah tabel lama, hanya menambah tabel
  dan baris konfigurasi, jadi kasus normalnya cukup membiarkannya.
- Cron: hapus baris yang baru ditambahkan di cPanel; tidak ada efek samping ke
  data, hanya ledger berhenti bertambah.

## 9. Batasan yang disepakati

- Uptime dinilai dari 3 titik per hari. Gangguan singkat di antara dua cek tidak
  terlihat; gangguan panjang yang pulih sebelum cek berikutnya tetap dianggap
  hari OK.
- Pengecekan berjalan dari server yang sama dengan aplikasi. Kalau seluruh
  server mati, tidak ada baris ledger — dan hari itu tetap dihitung gagal karena
  aturan "tanpa cek setelah monitoring berjalan = gagal".
- Tidak ada notifikasi/alert. Kegagalan hanya memengaruhi skor.
- Tidak ada input uptime lewat UI; semua lewat cron dan command.

## 10. Referensi kode

| Berkas | Tanggung jawab |
|---|---|
| `app/Commands/ItUptimeCheck.php` | Command `it:uptime-check` |
| `app/Services/Kpi/ItUptimeCheckService.php` | Probe HTTP + penulisan ledger |
| `app/Commands/ItKpiSync.php` | Command `it:kpi-sync` |
| `app/Services/Kpi/ItKpiSyncService.php` | Kolektor GitHub/backup/uptime, `uptimeSummary()` |
| `app/Services/Kpi/ItKpiService.php` | Rumus skor dan target komponen IT |
| `app/Database/Migrations/2026-11-24-000100_CreateItKpiMetrics.php` | Tabel snapshot `kpi_it_metrics` |
| `tests/unit/ItKpiTest.php`, `tests/unit/ItUptimeCheckTest.php` | Test aturan skor dan cek uptime |
