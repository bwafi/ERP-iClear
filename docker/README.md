# Docker Development — ERP iClear

Stack development lokal yang reproducible: PHP 7.4, MariaDB 10.6, Adminer, dan
source code dari folder proyek (bind mount). Semua konfigurasi ada di Git,
kecuali `.env` (kredensial lokal) dan isi database (`backups/`).

## Isi stack

| Service | Image / Build | Port host | Keterangan |
|---|---|---|---|
| `erp-app` | build `docker/php74/Dockerfile` | **8080** | PHP 7.4.33 + `gd`, `intl`, `mysqli`, `pdo_mysql`, `zip`, `bcmath`, `exif`, Composer 2.11 |
| `erp-db` | `mariadb:10.6` | – (internal) | Hostname jaringan: **`erp-db`**, DB `erp_local` |
| `erp-adminer` | `adminer:latest` | **8081** | GUI database |

- Source code: `./` (root proyek) di-mount ke `/app`
- Database: volume external **`erp-db-data`** (dipakai ulang, bukan dibuat baru)
- Container: `erp-iclear-erp-app-1`, `erp-iclear-erp-db-1`, `erp-iclear-erp-adminer-1`

## Prasyarat

1. Docker aktif dan user punya akses socket (group `docker`).
   Cek: `docker ps`
2. Plugin Compose:
   ```bash
   sudo pacman -S docker-compose
   docker compose version
   ```
3. Folder proyek sudah ter-clone (paket PHP lain tidak perlu di host — host tetap
   boleh PHP 8.5, semua eksekusi lewat container).

## Setup pertama kali

```bash
cp .env.example .env          # konfigurasi lokal (tidak ikut Git)
./docker/scripts/start.sh     # build image + jalankan stack
```

Lalu buka:

- Aplikasi: <http://localhost:8080>
- Adminer: <http://localhost:8081> → server `erp-db`, user `erp`, db `erp_local`

`start.sh` otomatis membuat `.env` dari `.env.example` bila belum ada, dan
membuat volume `erp-db-data` bila belum ada. Database yang baru dibuat masih
kosong — isi lewat migrasi dan/atau restore dump:

```bash
docker compose exec erp-app php spark migrate
# atau: gunzip -c backups/xxx.sql.gz | docker compose exec -T erp-db \
#         mariadb -uerp -perp erp_local
```

## Perintah harian

| Perintah | Fungsi |
|---|---|
| `./docker/scripts/start.sh` | build (bila perlu) + nyalakan stack |
| `./docker/scripts/stop.sh` | hentikan container (data aman) |
| `./docker/scripts/logs.sh [service]` | ikuti log (`erp-app`, `erp-db`, …) |
| `./docker/scripts/shell.sh [service]` | masuk shell container (default `erp-app`) |
| `./docker/scripts/backup-db.sh` | dump database ke `backups/*.sql.gz` |
| `./docker/scripts/backup-files.sh` | arsipkan folder upload/template ke `backups/*.tgz` |

Perintah container langsung:

```bash
docker compose ps
docker compose exec erp-app php spark migrate
docker compose exec erp-app composer install
docker compose exec erp-db mariadb -uerp -perp erp_local
```

## Migrasi dari container lama (erp-app / erp-db / erp-adminer)

Container lama **tidak dihapus** oleh prosedur ini, jadi bisa dipakai untuk rollback.

```bash
# 0) WAJIB: backup database + file upload sebelum menyentuh apa pun
./docker/scripts/backup-db.sh
./docker/scripts/backup-files.sh

# 1) Hentikan stack lama (container tetap ada, hanya berhenti)
docker stop erp-adminer erp-app erp-db

# 2) Matikan auto-restart container lama agar tidak berebut volume/port saat boot
docker update --restart=no erp-app erp-db erp-adminer

# 3) Build + jalankan stack compose
docker compose build erp-app
docker compose up -d

# 4) Validasi
docker compose ps
curl -sI http://localhost:8080/login | head -1
docker compose exec erp-db mariadb -uerp -perp -e \
  "SELECT COUNT(*) AS barang FROM erp_local.barang;"
```

### Rollback (kembali ke container lama)

```bash
docker compose stop
docker start erp-db erp-app erp-adminer
```

## Backup & restore

### Database

```bash
./docker/scripts/backup-db.sh
# hasil: backups/erp_local_YYYY-MM-DD_HHMMSS.sql.gz
```

Restore (hati-hati, menimpa database):

```bash
docker compose stop erp-app

docker compose exec -T erp-db mariadb -uroot -proot -e "
  DROP DATABASE IF EXISTS erp_local;
  CREATE DATABASE erp_local CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
  GRANT ALL PRIVILEGES ON erp_local.* TO 'erp'@'%';
  FLUSH PRIVILEGES;"

gunzip -c backups/erp_local_XXXX.sql.gz | \
  docker compose exec -T erp-db mariadb --max-allowed-packet=512M -uroot -proot erp_local

docker compose start erp-app
```

### Upload / file pengguna

Upload **tidak** disimpan di volume Docker; semuanya ada di folder proyek
(`public/foto_*`, `public/uploads`, `writable/uploads`). Artinya:

- otomatis hilang kalau folder proyek dihapus → tetap backup berkala
- tidak ikut `git` (lihat `.gitignore`)

```bash
./docker/scripts/backup-files.sh
# hasil: backups/uploads_YYYY-MM-DD_HHMMSS.tgz
```

Script otomatis melewati folder yang belum ada (mis. `public/uploads`), jadi aman
dijalankan kapan saja.

## Catatan penting

- **Jangan** `docker compose down -v` — opsi `-v` menghapus volume `erp-db-data`.
- **Jangan** reset database produksi. Compose ini hanya untuk lokal.
- `.env` berisi kredensial lokal dan **tidak** di-commit; `.env.example` yang di-commit.
- Volume `erp-db-data` bersifat `external` — compose tidak akan membuat/menghapusnya.
  `start.sh` sudah membuatnya otomatis bila belum ada; kalau ingin manual:
  `docker volume create erp-db-data`.
- Perubahan kode PHP/view/CSS **tidak** perlu rebuild (bind mount). Rebuild hanya
  kalau `docker/php74/Dockerfile` berubah.
- Host boleh tetap PHP 8.5, tapi jangan menjalankan `php`/`composer` host untuk proyek ini.

## Troubleshooting

| Gejala | Penyebab / solusi |
|---|---|
| `permission denied ... docker.sock` | Sesi login belum memuat group `docker`. Logout/login, atau sementara: `newgrp docker` |
| `docker: unknown command: docker compose` | Install: `sudo pacman -S docker-compose` |
| `port is already allocated` (8080/8081) | Container lama masih jalan: `docker stop erp-adminer erp-app erp-db`, atau cek `ss -ltnp \| grep -E '8080\|8081'` |
| Build gagal `404` dari `deb.debian.org` | Base Debian bullseye sudah EOL; Dockerfile sudah diarahkan ke `archive.debian.org` |
| File log/upload jadi milik `root` | Pastikan service memakai `user: "${UID:-1000}:${GID:-1000}"` dan start dari shell user biasa |
| Aplikasi 500 setelah restore | Cek `writable/logs/`, lalu `docker compose logs erp-app` |
