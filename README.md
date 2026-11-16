# ERP iClear

ERP operasional untuk toko/cabang: penjualan (POS), pembelian & persediaan,
service & garansi, keuangan (kas-bank, jurnal, aset), hutang-piutang, HR/payroll,
KPI, marketing/konten, serta integrasi Kommo/CRM.

Dibangun dengan **CodeIgniter 4.4.8** (PHP), **Bootstrap 5**, jQuery/DataTables,
dan **MariaDB/MySQL**.

---

## Kebutuhan (Requirements)

### Jalur utama: Docker (disarankan)

| Kebutuhan | Versi / catatan |
|---|---|
| Docker Engine | aktif, user punya akses `docker.sock` (group `docker`) |
| Docker Compose | plugin v2 (`docker compose`, bukan `docker-compose`) |
| Port host | **8080** (aplikasi) dan **8081** (Adminer) harus bebas |

Stack menjalankan PHP **7.4.33**, MariaDB **10.6**, dan Adminer di dalam
container, jadi PHP di host tidak dipakai untuk proyek ini.

### Alternatif: PHP di host

| Kebutuhan | Versi / catatan |
|---|---|
| PHP | **7.4.x** (paling aman, sama dengan container) |
| Ekstensi PHP | `intl`, `mbstring`, `mysqli`, `pdo_mysql`, `gd`, `zip`, `bcmath`, `exif`, `curl`, `json` |
| Composer | 2.x |
| Database | MariaDB 10.6 atau MySQL 8 |

> [!IMPORTANT]
> **PHP 8.4 dan 8.5 tidak bisa dipakai.** CodeIgniter 4.4.8 gagal start dengan
> `Declaration of CodeIgniter\I18n\Time::createFromTimestamp(...) must be compatible
> with DateTimeImmutable::createFromTimestamp(...)`. Selain itu, tanpa ekstensi
> `intl` seluruh test berhenti di `Class "Locale" not found`.
>
> PHP 8.0/8.1 secara teori masih diterima `composer.json` (`^7.4 || ^8.0`), tetapi
> **belum diverifikasi** di repo ini. Gunakan container bila ragu.

---

## Menjalankan aplikasi dengan Docker

Panduan ini dimulai dari clone baru sampai aplikasi bisa dibuka di browser.
Stack berisi 3 container: `erp-app` (PHP 7.4), `erp-db` (MariaDB 10.6), dan
`erp-adminer` (GUI database). Source code di-mount dari folder proyek, jadi
perubahan file langsung terlihat tanpa rebuild.

### 1. Pastikan Docker siap

```bash
docker --version
docker compose version   # harus plugin v2: "Docker Compose version v2.x"
docker ps                # tidak boleh "permission denied"
```

Kalau belum ada:

- **Arch Linux**
  ```bash
  sudo pacman -S docker docker-compose
  sudo systemctl enable --now docker
  sudo usermod -aG docker "$USER"   # logout/login agar group aktif
  ```
- **Ubuntu/Debian** — ikuti panduan resmi Docker Engine + plugin Compose,
  lalu tambahkan user ke group `docker` dan login ulang.

Port **8080** (aplikasi) dan **8081** (Adminer) harus bebas. Kalau ada container
lama bernama `erp-app`, `erp-db`, atau `erp-adminer` yang masih jalan, hentikan
dulu — `start.sh` sengaja menolak start agar tidak berebut port/volume.

### 2. Clone dan siapkan `.env`

```bash
git clone <url-repo> ERP-iClear
cd ERP-iClear
cp .env.example .env
```

`.env` berisi konfigurasi lokal dan **tidak ikut Git**. Nilai default sudah cocok
dengan `compose.yaml`: host database `erp-db`, database `erp_local`, user/password
`erp`/`erp`, dan `app.baseURL = http://localhost:8080/`.
Kalau `.env` lupa dibuat, `start.sh` akan menyalinnya otomatis dari `.env.example`.

### 3. Build image dan install dependency PHP

Folder `vendor/` tidak di-commit dan tidak ikut image, jadi clone baru harus
menginstalnya sekali. Jalankan **sebelum** stack dinyalakan supaya container
aplikasi tidak restart terus karena autoload belum ada:

```bash
docker volume create erp-db-data          # idempotent; dibutuhkan service database
docker compose build erp-app
docker compose run --rm --no-deps erp-app composer install
```

### 4. Jalankan stack

```bash
./docker/scripts/start.sh
```

Script ini membuat `.env` bila belum ada (langkah 2 bisa dilewati), membuat
volume `erp-db-data` bila belum ada, lalu menjalankan
`docker compose up -d --build`. Alternatif manual:

```bash
docker volume create erp-db-data
docker compose up -d --build
```

Cek status: `docker compose ps` (semua service harus `running`/`healthy`).

### 5. Siapkan database

Database baru masih kosong. Pilih salah satu:

```bash
# a) buat schema dari migrasi
docker compose exec erp-app php spark migrate

# b) restore dump yang sudah ada (paling praktis untuk data lengkap)
gunzip -c backups/erp_local_XXXX.sql.gz | docker compose exec -T erp-db \
  mariadb -uerp -perp erp_local
```

> [!NOTE]
> Migrasi hanya membuat tabel. **Belum ada akun login** di database kosong, jadi
> untuk bisa masuk gunakan restore dump, atau minta tim developer membuatkan akun
> admin. Sebelum restore, backup database lama dulu — lihat bagian restore di
> [`docker/README.md`](docker/README.md).

### 6. Buka aplikasi

- Aplikasi: <http://localhost:8080> (otomatis diarahkan ke halaman login)
- Adminer: <http://localhost:8081> → server `erp-db`, user `erp`, password `erp`,
  database `erp_local`

Login memakai akun yang ada di database (hasil restore). Tidak ada akun default.

### 7. Perintah harian

| Perintah | Fungsi |
|---|---|
| `docker compose ps` | cek status container |
| `./docker/scripts/logs.sh erp-app` | ikuti log aplikasi |
| `./docker/scripts/shell.sh` | masuk shell container PHP |
| `./docker/scripts/stop.sh` | hentikan stack (data tetap aman) |
| `./docker/scripts/backup-db.sh` | dump database ke `backups/*.sql.gz` |

Menjalankan ulang setelah reboot: `docker compose start` atau
`./docker/scripts/start.sh`.

> [!WARNING]
> Jangan memakai `docker compose down -v` — opsi `-v` menghapus volume
> `erp-db-data` beserta seluruh isi database. Untuk berhenti, pakai
> `./docker/scripts/stop.sh`.

### Troubleshooting singkat

| Gejala | Solusi |
|---|---|
| `permission denied ... docker.sock` | logout/login setelah `usermod -aG docker`, atau sementara `newgrp docker` |
| `docker: unknown command: docker compose` | plugin Compose belum ada: `sudo pacman -S docker-compose` (Arch) |
| `port is already allocated` | hentikan container lama: `docker stop erp-adminer erp-app erp-db` |
| Halaman 500 setelah start | cek `docker compose logs erp-app` dan `writable/logs/` |
| Error koneksi database | pastikan `.env` memakai host `erp-db` (bukan `localhost`) dan `docker compose ps` menunjukkan `erp-db` healthy |

Backup, restore, migrasi dari container lama, dan troubleshooting lengkap ada di
**[`docker/README.md`](docker/README.md)**.

---

## Menjalankan test

Semua perintah dijalankan **di dalam container** (PHP host tidak dipakai):

```bash
# satu file
docker compose exec erp-app vendor/bin/phpunit -c phpunit.xml.dist tests/PenjualanPickerMoneyTest.php

# seluruh suite
docker compose exec erp-app vendor/bin/phpunit -c phpunit.xml.dist
```

Hal yang perlu diketahui:

- `phpunit.xml` **tidak** di-commit (lihat `.gitignore`). Kalau tidak ada, gunakan
  `-c phpunit.xml.dist`.
- Konfigurasi default memakai SQLite `:memory:`. Test yang butuh MySQL (misalnya
  yang membaca `information_schema`) akan gagal dan memerlukan database test
  terpisah dengan nama berakhiran `_test`.
- **Jangan** mengarahkan test ke `erp_local` atau database produksi.
  `app/Config/Database.php` akan menolak (fail-closed) bila group `tests` menunjuk
  database yang sama dengan `default`.
- Aturan isolasi database test selengkapnya: [`tests/README.md`](tests/README.md).

---

## Struktur proyek

```
app/
  Controllers/          # controller per modul (extends App\Controllers\BaseController)
  Models/               # model berprefix Model*, mis. ModelPenjualan
  Services/             # business logic domain: Finance/, Kpi/, Payroll/
  Views/                # halaman (layout: Views/template.php, partial: Views/inc, Views/partials)
  Database/Migrations/  # migrasi schema
  Config/               # route, database, filter, dll.
public/                 # document root (index.php ada di sini)
tests/                  # PHPUnit
docs/                   # PRD & analisis
docker/                 # stack development lokal (Dockerfile, script, README)
```

Beberapa aturan kerja yang mengikat (arsitektur, keamanan, alur perubahan,
konvensi test) ada di **[`AGENTS.md`](AGENTS.md)** — baca sebelum mengubah kode.

---

## Dokumentasi

| Dokumen | Isi |
|---|---|
| [`docs/prd/README.md`](docs/prd/README.md) | indeks PRD |
| [`docs/prd/PRD_MASTER.md`](docs/prd/PRD_MASTER.md) | aturan lintas modul |
| `docs/prd/modul/PRD_MODUL_01..10_*.md` | PRD per modul |
| [`docs/architecture/kpi-business-rules.md`](docs/architecture/kpi-business-rules.md) | formula KPI |
| [`docker/README.md`](docker/README.md) | operasional stack development |
| [`PRODUCT.md`](PRODUCT.md) | konteks produk alur Tutup Kasir |
| [`DESIGN.md`](DESIGN.md) | visual system Kas & Bank |

PRD adalah dokumentasi, bukan bukti implementasi. Bukti aktual ada di kode,
route, schema, dan test.
