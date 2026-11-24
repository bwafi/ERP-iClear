# KPI IT — Uptime lewat Cek Terjadwal (Design)

**Tanggal:** 2026-11-24
**Status:** disetujui
**Terkait:** issue #241, PRD Modul 08, `docs/architecture/kpi-business-rules.md`

## Intent

Komponen **System Uptime** (bobot 15%, jabatan 45 Team IT) tidak lagi
memakai UptimeRobot. Uptime diukur dari pengecekan terjadwal yang dijalankan
server sendiri, 3 kali sehari, dengan bukti tersimpan per pengecekan.

Alasan:

- Tidak mau bergantung pada layanan pihak ketiga untuk angka KPI.
- Polling tiap 5 menit dianggap berlebihan dan berisiko mengganggu
  operasional; 3 kali sehari sudah cukup untuk menilai "hari ini aman atau
  tidak".
- Angka harus tetap punya bukti, bukan persentase yang diketik manual.

## Goals

1. Perintah `php spark it:uptime-check` melakukan satu pengecekan HTTP ke
   halaman login ERP dan menulis satu baris bukti ke `backups/uptime.jsonl`.
2. Cron menjalankannya pukul **02:00, 08:00, dan 22:00 WIB** (3 slot, maksimal
   4 bila nanti ditambah).
3. Skor harian: **minimal satu cek sukses pada hari itu = hari OK**. Semua cek
   gagal = hari gagal.
4. Hari tanpa catatan cek **setelah** pengecekan pernah berjalan dihitung
   **gagal** (server mati total tidak boleh lolos).
5. Hari **sebelum** cek pertama tidak dihitung sama sekali (N/A), supaya bulan
   berjalan tidak dihukum karena monitoring belum dipasang.
6. UptimeRobot dihapus dari kode, konfigurasi, test, dan dokumentasi.
7. Dokumentasi operasional baru `docs/operations/kpi-it.md` yang mencakup
   seluruh rangkaian KPI IT: backup, sinkronisasi, cek uptime, uji restore,
   cron, rumus, dan troubleshooting.

## Non-goals

- Bukan sistem monitoring/alerting umum: tidak ada email atau notifikasi saat
  gagal. Kegagalan hanya memengaruhi skor.
- Tidak mengukur durasi gangguan di antara dua cek. Gangguan 6 jam yang terjadi
  setelah cek 08:00 dan pulih sebelum 22:00 tetap dianggap hari OK.
- Tidak ada form input uptime di UI. Bila nanti dibutuhkan, sumber datanya
  sudah siap tanpa perubahan skema.

## Constraints

- Cron berjalan di cPanel production dengan `/usr/local/bin/php`; path aplikasi
  `/home/icleargr/SubDomain/erp.icleargroup.id/ERP`.
- Zona waktu bisnis `Asia/Jakarta`; zona waktu aplikasi UTC. Tanggal hari
  dihitung memakai zona bisnis.
- Data mentah pengecekan disimpan sebagai file append-only di folder backup
  (`BACKUP_DIR`, default `ROOTPATH/backups`), bukan tabel baru: bila MySQL mati,
  kegagalan tetap tercatat sehingga hari tidak lolos sebagai "tidak termonitor".
- Snapshot bulanan tetap di `kpi_it_metrics`; halaman penilaian tidak melakukan
  panggilan jaringan.
- Tidak ada migrasi database baru.

## Approaches

| Opsi | Isi | Kenapa dipilih / ditolak |
|---|---|---|
| A. UptimeRobot 5 menit | Layanan eksternal, API `custom_uptime_ranges` per hari | Ditolak: bergantung pihak ketiga, frekuensi 5 menit dianggap mengganggu |
| B. Input manual angka | Persen bulanan atau daftar tanggal down yang diketik | Ditolak: tidak ada bukti, dan pemilik KPI tidak mau mengetik tiap bulan |
| C. Cek terjadwal 3x/hari + ledger file | Cron server sendiri, bukti per cek, skor harian | **Dipilih** |

## Keputusan

1. **Slot cek:** 02:00, 08:00, 22:00 WIB. Satu baris cron:
   `0 2,8,22 * * *`. Menambah slot keempat cukup mengubah daftar jam.
2. **Target cek:** `KPI_UPTIME_URL`, default
   `https://erp.icleargroup.id/login`. Hanya halaman login; endpoint
   `api.iclear.id/api/health.php` tidak dipakai.
3. **Definisi sukses:** respons HTTP 200 setelah mengikuti redirect, dalam
   batas waktu 10 detik. Bila `KPI_UPTIME_MARKER` diisi, isi halaman juga harus
   memuat teks tersebut (default kosong = cukup status).
4. **Bukti:** satu baris JSON per cek di `backups/uptime.jsonl`:
   `at`, `ok`, `http`, `ms`, `url`, `error`.
5. **Perhitungan skor** (`uptimeSummary()`):
   - `start` = tanggal (zona bisnis) dari baris paling awal di ledger.
   - `checked_days` = hari selesai pada bulan itu yang >= `start`.
     Bulan berjalan memakai hari selesai sampai kemarin.
   - `ok_days` = hari dengan minimal satu baris `ok=true`.
   - Ledger tidak ada / kosong = `null` (N/A), bukan 0.
6. **Batas yang diterima:** granularitas 3 titik per hari; gangguan singkat di
   antara slot tidak terlihat.

## Yang diserahkan ke implementer

- Cara memanggil HTTP (CI `CURLRequest` atau `curl`) dan injeksi callable untuk
  test.
- Susunan test dan fixture ledger.
- Redaksi dokumentasi operasional, mengikuti gaya
  `docs/operations/backup-database.md`.
