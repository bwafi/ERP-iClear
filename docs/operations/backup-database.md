# Backup Database Harian

Backup database ERP dijalankan oleh perintah `backup:db`, lalu dijadwalkan
lewat cron cPanel di production. Tujuannya bukan hanya menghasilkan file,
tetapi menghasilkan bukti yang bisa dipakai untuk komponen KPI **Backup**:
satu baris histori per hari, berisi status, ukuran, jumlah tabel, dan error
bila gagal.

## Perintah

```sh
php spark backup:db
php spark backup:db --keep-days 30
php spark backup:db --dir /home/USER/backups
php spark backup:db --bin mariadb-dump
```

| Opsi | Default | Keterangan |
|---|---|---|
| `--keep-days` | `14` (atau env `BACKUP_KEEP_DAYS`) | Backup lebih tua dihapus; backup terbaru selalu disisakan. |
| `--dir` | `ROOTPATH/backups` (atau env `BACKUP_DIR`) | Folder tujuan. |
| `--bin` | deteksi `mysqldump` lalu `mariadb-dump` | Binary dump. |
| `--timezone` | `Asia/Jakarta` (atau env `BACKUP_TIMEZONE`) | Zona waktu untuk nama file dan histori. |

Zona waktu default sengaja WIB, bukan UTC milik aplikasi. Backup pukul 02:15
WIB harus tercatat sebagai tanggal toko, bukan tanggal kemarin UTC; kalau
tidak, perhitungan KPI Backup harian bisa salah hari.

Yang dilakukan perintah ini:

1. Membaca group database `default` dari `.env`.
2. Menjalankan `mysqldump`/`mariadb-dump` dengan `--single-transaction --quick
   --routines --triggers --events --no-tablespaces`.
3. Menulis ke `namadb_YYYY-MM-DD_HHMMSS.sql.gz.part`, memverifikasi gzip dan
   minimal satu `CREATE TABLE`, baru me-rename menjadi `.sql.gz`.
4. Menghapus backup yang melewati retensi (kecuali yang terbaru).
5. Menambah satu baris ke `backups/history.jsonl`.

Password tidak pernah dikirim lewat argumen proses. Perintah membuat file
`--defaults-extra-file` sementara dengan permission `0600`, lalu menghapusnya.

## Cron di cPanel

1. Buka **cPanel → Cron Jobs → Add New Cron Job**.
2. Isi jadwal, misalnya tiap hari pukul 02:15 server time:
   ```
   15 2 * * *
   ```
3. Isi command (sesuaikan path dan binary PHP dengan hosting Anda):
   ```sh
   cd /home/USER/domains/erp.icleargroup.id && /usr/local/bin/php spark backup:db --keep-days 14 >> writable/logs/backup-cron.log 2>&1
   ```
4. Simpan, lalu jalankan sekali secara manual dari **Terminal** cPanel untuk
   memastikan tidak ada error:
   ```sh
   cd /home/USER/domains/erp.icleargroup.id
   /usr/local/bin/php spark backup:db
   ```

Cara menemukan path yang benar: buka Terminal cPanel, masuk ke folder aplikasi
(tempat `spark` berada), lalu jalankan `pwd`. Binary PHP biasanya
`/usr/local/bin/php`, tetapi bisa berbeda; cek dengan `which php`.

## Keamanan folder backup

File backup berisi seluruh data ERP. Pastikan folder `backups/` **tidak bisa
diakses lewat URL**:

```sh
curl -I https://erp.icleargroup.id/backups/
```

Hasil yang benar adalah `403` atau `404`. Idealnya folder backup berada di
luar document root (`public_html`/`public`). Kalau hosting memaksa folder itu
di dalam web root, tambahkan `.htaccess`:

```apache
Require all denied
```

Jangan letakkan token, password, atau file `.env` di folder yang sama.

## Verifikasi

Setelah cron berjalan:

```sh
ls -lh backups/*.sql.gz | tail -3
tail -3 backups/history.jsonl
```

Baris histori sukses terlihat seperti:

```json
{"at":"2026-11-25T02:15:03+07:00","status":"ok","database":"erp_local","file":"erp_local_2026-11-25_021503.sql.gz","bytes":1649694,"tables":152,"keep_days":14,"deleted":0,"error":null}
```

Jika gagal, `status` bernilai `failed`, `error` berisi pesan dari
`mysqldump`, dan tidak ada file `.sql.gz` yang ditinggalkan.

## Uji restore (wajib bulanan)

Backup yang tidak pernah diuji restore belum terbukti berguna.

```sh
# Di database test, bukan production.
gunzip -c backups/erp_local_2026-11-25_021503.sql.gz | mysql -u USER -p erp_restore_test
```

Setelah import, cek jumlah tabel dan satu query sederhana (mis.
`SELECT COUNT(*) FROM penjualan`). Catat tanggal uji restore di catatan
bulanan; ini dipakai sebagai syarat komponen KPI Backup.

## Penggunaan untuk KPI Backup

Komponen **Backup** dinilai dari `backups/history.jsonl`:

- hari dengan `status = ok` dan `tables > 0` dihitung sukses;
- hari tanpa entri dianggap gagal (cron tidak jalan);
- skor bulanan = `hari sukses / jumlah hari × 100`;
- uji restore bulanan yang gagal membatasi skor maksimal 50.

## Catatan development (Docker)

Container aplikasi dev tidak memasang `mysql-client`, jadi `spark backup:db`
tidak bisa menemukan binary dump di dalam container. Untuk backup lokal
gunakan script yang sudah ada:

```sh
./docker/scripts/backup-db.sh
```

Script itu memakai `mariadb-dump` dari container database.

## Troubleshooting

- **`Binary dump tidak ditemukan`** — pasang `mysql-client`/`mariadb-client`
  di server, atau jalankan dengan `--bin /path/ke/mysqldump`.
- **`Unknown table 'COLUMN_STATISTICS'`** — client MySQL 8 dipakai ke server
  MariaDB. Jalankan dengan `--bin mariadb-dump`.
- **`Access denied for PROCESS privilege`** — flag `--no-tablespaces` sudah
  dipakai; kalau masih muncul, gunakan user database yang punya akses dump.
- **File `.part` tertinggal** — artinya proses terhenti. File itu dibersihkan
  otomatis setelah 1 hari; cek `writable/logs/backup-cron.log`.
- **`proc_open` dinonaktifkan** — sebagian shared hosting mematikan fungsi ini.
  Perintah akan gagal dengan pesan "Tidak bisa menjalankan binary dump". Minta
  provider mengaktifkannya, atau gunakan backup native cPanel sementara.
