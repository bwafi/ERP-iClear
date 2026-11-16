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

## Quickstart (Docker)

```bash
git clone <url-repo> ERP-iClear
cd ERP-iClear

cp .env.example .env          # konfigurasi lokal; file ini TIDAK ikut Git
./docker/scripts/start.sh     # build image + buat volume + jalankan stack
```

Buka:

- Aplikasi: <http://localhost:8080>
- Adminer: <http://localhost:8081> → server `erp-db`, user `erp`, db `erp_local`

Database yang baru dibuat masih kosong. Isi dengan migrasi dan/atau restore dump:

```bash
docker compose exec erp-app php spark migrate

# restore dari backup (./docker/scripts/backup-db.sh)
gunzip -c backups/xxx.sql.gz | docker compose exec -T erp-db \
  mariadb -uerp -perp erp_local
```

Perintah harian (`start`, `stop`, `logs`, `shell`, `backup-db`, `backup-files`)
dan troubleshooting ada di **[`docker/README.md`](docker/README.md)**.

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
