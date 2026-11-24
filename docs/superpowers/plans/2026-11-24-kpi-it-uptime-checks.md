# KPI IT — Uptime Cek Terjadwal: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ganti sumber uptime KPI Team IT dari UptimeRobot menjadi cek terjadwal 3x sehari yang menulis bukti ke `backups/uptime.jsonl`, dan lengkapi dokumentasi operasional KPI IT.

**Architecture:** Satu command CLI `it:uptime-check` melakukan satu HTTP GET ke halaman login lalu menambahkan satu baris JSON ke ledger file (bukan tabel DB, supaya kegagalan MySQL tetap tercatat). `it:kpi-sync` membaca ledger itu, mengubahnya menjadi `uptime_ok_days`/`uptime_checked_days` di snapshot `kpi_it_metrics`. Halaman penilaian tetap hanya membaca snapshot.

**Tech Stack:** CodeIgniter 4.4.8, PHP 7.4 (container `erp-php74`), PHPUnit 9, MariaDB test `erp_test`, cron cPanel di production.

**Spec:** `docs/superpowers/specs/2026-11-24-kpi-it-uptime-checks-design.md`

## Global Constraints

- Slot cek: **02:00, 08:00, 22:00 WIB** (`Asia/Jakarta`), maksimal 4.
- URL default: `https://erp.icleargroup.id/login` (bukan `api.iclear.id/api/health.php`).
- Sukses = HTTP **200** setelah redirect, timeout **10 detik**; `KPI_UPTIME_MARKER` opsional.
- Hari OK = minimal satu cek sukses; semua gagal = gagal; hari tanpa cek **setelah** cek pertama = gagal; hari **sebelum** cek pertama = tidak dihitung.
- Ledger append-only `backups/uptime.jsonl`, satu baris per cek, field: `at`, `ok`, `http`, `ms`, `url`, `error`.
- Tanpa migrasi database baru. Tanpa panggilan jaringan saat render halaman.
- Tidak ada `git commit` sebelum diminta; pesan commit bahasa Inggris Conventional Commits + `(refs #<issue>)`.
- Test hanya boleh menyentuh database `erp_test` (`phpunit.xml`).

## Review Focus

- Server mati sepanjang hari (cron tidak jalan sama sekali) setelah monitoring aktif → hari **gagal**, bukan dilewati.
- Ledger dihapus/berputar → monitoring dianggap mulai lagi; hari sebelum baris pertama di file baru tidak dihitung.
- Cek pukul 22:00 WIB harus tercatat pada tanggal bisnis yang sama, bukan tanggal UTC berikutnya.
- Baris ledger rusak/terpotong (JSON tidak valid) tidak boleh membuat sync error.
- `KPI_UPTIME_MARKER` peka spasi/huruf besar-kecil di HTML → perbandingan case-insensitive dan trim.

---

### Task 1: Perintah `it:uptime-check` dan ledger

**Files:**
- Create: `app/Services/Kpi/ItUptimeCheckService.php`
- Create: `app/Commands/ItUptimeCheck.php`
- Test: `tests/unit/ItUptimeCheckTest.php`

**Interfaces:**
- Consumes: tidak ada.
- Produces:
  - `ItUptimeCheckService::__construct(array $config = [])` dengan kunci `url`, `marker`, `timeout`, `ledger_dir`, `http`.
  - `ItUptimeCheckService::run(?DateTimeImmutable $now = null): array{ok:bool,http:int,ms:int,error:?string,at:string}`
  - `ItUptimeCheckService::probe(): array{ok:bool,http:int,ms:int,error:?string}`
  - `ItUptimeCheckService::record(array $result, ?DateTimeImmutable $now = null): string` (mengembalikan path ledger)
  - `ItUptimeCheckService::isSuccess(int $status, string $body, string $marker): bool` (static)
  - Callable `http` (untuk test): `fn(string $url, int $timeout): array{status:int,body:string,ms:int,error:?string}`.

- [ ] **Step 1: Write the failing tests**

```php
// tests/unit/ItUptimeCheckTest.php
public function testCekSuksesMenulisSatuBarisLedger(): void
public function testCekGagalTetapMencatatBaris(): void
public function testMarkerTidakCocokDianggapGagal(): void
public function testErrorJaringanDicatatTanpaMelemparException(): void
public function testJamDuaPuluhDuaWibTetapTanggalBisnisYangSama(): void
public function testLedgerRusakTidakMembuatRunGagal(): void
```

Assertions inti: baris pertama ledger `ok === true` dan `http === 200`; untuk status 503 `ok === false`; `isSuccess(200, '<h1>Login</h1>', 'login') === true` dan `isSuccess(200, '<h1>Masuk</h1>', 'login') === false`; waktu `2026-11-24 22:00 WIB` menghasilkan `at` berawalan `2026-11-24T22:00`.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec erp-app vendor/bin/phpunit -c phpunit.xml tests/unit/ItUptimeCheckTest.php`
Expected: FAIL — `Class "App\Services\Kpi\ItUptimeCheckService" not found`

- [ ] **Step 3: Implement the service**

`probe()` memakai callable `http` bila ada, selain itu `\Config\Services::curlrequest()` dengan `timeout` 10 dan `http_errors` false, mengukur durasi dengan `microtime(true)`. `isSuccess()` = `status === 200 && (marker trim === '' || stripos(body, marker) !== false)`. `run()` memanggil `probe()` lalu `record()`. `record()` membuat folder `ledger_dir` bila belum ada, menambahkan baris `json_encode([...]) . "\n"` dengan `FILE_APPEND | LOCK_EX`, dan tidak melempar exception bila penulisan gagal (kembalikan path saja).

- [ ] **Step 4: Implement the command**

`app/Commands/ItUptimeCheck.php`, `protected $name = 'it:uptime-check'`, opsi `--url`, `--marker`, `--timeout`, `--backup-dir`, `--ledger-dir`. Sumber nilai: opsi CLI → `env('KPI_UPTIME_URL'/'KPI_UPTIME_MARKER'/'KPI_UPTIME_TIMEOUT'/'BACKUP_DIR')` → default. Keluaran sukses: `Cek uptime OK (HTTP 200, 412 ms) -> backups/uptime.jsonl`; gagal: tulis alasan dan `return 0` (kegagalan cek bukan kegagalan perintah, karena cron tidak perlu kirim error tiap kali situs down). `return 1` hanya untuk konfigurasi tidak valid (URL kosong/timeout bukan angka).

- [ ] **Step 5: Run the tests to verify they pass**

Run: `docker compose exec erp-app vendor/bin/phpunit -c phpunit.xml tests/unit/ItUptimeCheckTest.php`
Expected: PASS, semua test hijau.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Kpi/ItUptimeCheckService.php app/Commands/ItUptimeCheck.php tests/unit/ItUptimeCheckTest.php
git commit -m "feat(kpi): add scheduled uptime check with ledger (refs #<issue>)"
```

---

### Task 2: `it:kpi-sync` membaca ledger uptime

**Files:**
- Modify: `app/Services/Kpi/ItKpiSyncService.php` (hapus blok UptimeRobot, tambah `uptimeSummary()`)
- Modify: `app/Commands/ItKpiSync.php` (hapus kunci uptime UptimeRobot, ganti catatan CLI)
- Test: `tests/unit/ItKpiTest.php` (ganti 3 test uptime lama)

**Interfaces:**
- Consumes: format ledger dari Task 1.
- Produces: `ItKpiSyncService::uptimeSummary(string $ledgerFile, array $days, string $timezone): array{ok_days:int,checked_days:int,failed_days:list<string>,start:?string}`.

- [ ] **Step 1: Write the failing tests**

```php
public function testUptimeSatuCekSuksesCukupUntukHariOk(): void
public function testUptimeSemuaCekGagalMenjadiHariGagal(): void
public function testUptimeHariSebelumCekPertamaTidakDihitung(): void
public function testUptimeHariTanpaCekSetelahMulaiDihitungGagal(): void
public function testUptimeTanpaLedgerMenghasilkanNull(): void
public function testUptimeBarisRusakDiabaikan(): void
```

Assertions inti: ledger `[{ok:true},{ok:false}]` pada hari yang sama → `ok_days=1, checked_days=1`; hari tanpa baris setelah `start` → masuk `failed_days`; hari sebelum `start` tidak masuk `failed_days` dan tidak menambah `checked_days`; file tidak ada → `checked_days=0` dan `collect()` menghasilkan `uptime_ok_days === null`.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `docker compose exec erp-app vendor/bin/phpunit -c phpunit.xml tests/unit/ItKpiTest.php`
Expected: FAIL — `uptimeSummary` belum ada / test lama UptimeRobot gagal.

- [ ] **Step 3: Implement `uptimeSummary()` dan sambungkan ke `collect()`**

Baca `file($ledgerFile)`, `json_decode` per baris, lewati baris tidak valid. Kumpulkan `okByDay` (hari dengan minimal satu `ok === true`) dan `start` = tanggal bisnis paling awal. Untuk setiap `$days`: lewati bila `< start`, tambah `checked_days`, hitung `ok_days` atau masukkan `failed_days`. `collect()` memanggilnya dengan `backup_dir . '/uptime.jsonl'`; `detail['uptime']` = `['status' => is_file(...) ? 'ok' : 'no-ledger', 'file' => ..., 'start' => ..., 'failed_days' => ...]`.

- [ ] **Step 4: Hapus jalur UptimeRobot**

Hapus `UPTIME_API`, `MAX_DOWNTIME_SECONDS`, kunci `uptime_key`/`uptime_monitors`, `uptimeStats()`, `parseUptimeRatios()`, `downtimeSecondsFromRatio()`, `parseRatioSeries()`. Di `ItKpiSync.php` hapus dua kunci config itu dan ganti catatan CLI menjadi `ledger uptime kosong, uptime N/A (jalankan it:uptime-check)`.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `docker compose exec erp-app vendor/bin/phpunit -c phpunit.xml tests/unit/ItKpiTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Kpi/ItKpiSyncService.php app/Commands/ItKpiSync.php tests/unit/ItKpiTest.php
git commit -m "refactor(kpi): score uptime from scheduled check ledger (refs #<issue>)"
```

---

### Task 3: Bersihkan sisa UptimeRobot

**Files:**
- Modify: `.env.example`
- Modify: `.env` (gitignored, tidak di-commit)
- Modify: `app/Services/Kpi/ItKpiService.php` (docblock sumber uptime)
- Modify: `app/Database/Migrations/2026-11-24-000100_CreateItKpiMetrics.php` (komentar)

**Interfaces:**
- Consumes: hasil Task 2. Produces: tidak ada API baru.

- [ ] **Step 1: Hapus kunci env**

Hapus `UPTIMEROBOT_API_KEY` dan `UPTIMEROBOT_MONITOR_IDS`; tambahkan `KPI_UPTIME_URL`, `KPI_UPTIME_MARKER`, `KPI_UPTIME_TIMEOUT` dengan komentar singkat dan contoh nilai produksi.

- [ ] **Step 2: Perbarui komentar kode**

`ItKpiService` dan komentar migrasi menyebut "cek terjadwal `it:uptime-check`", bukan UptimeRobot.

- [ ] **Step 3: Verifikasi tidak ada sisa**

Run: `grep -rniE "uptimerobot|custom_uptime" app tests .env.example docs/architecture docs/prd/modul/PRD_MODUL_08_KPI_Penilaian.md`
Expected: tidak ada hasil.

- [ ] **Step 4: Commit**

```bash
git add .env.example app/Services/Kpi/ItKpiService.php app/Database/Migrations/2026-11-24-000100_CreateItKpiMetrics.php
git commit -m "chore(kpi): drop uptimerobot configuration (refs #<issue>)"
```

---

### Task 4: Dokumentasi operasional KPI IT

**Files:**
- Create: `docs/operations/kpi-it.md`
- Modify: `docs/operations/backup-database.md` (tautan ke dokumen KPI IT)
- Modify: `docs/prd/modul/PRD_MODUL_08_KPI_Penilaian.md`
- Modify: `docs/architecture/kpi-business-rules.md`

**Interfaces:**
- Consumes: nama command dan aturan dari Task 1–3. Produces: tidak ada kode.

- [ ] **Step 1: Tulis `docs/operations/kpi-it.md`**

Bagian wajib: (1) ringkasan komponen & bobot 25/25/25/15/10; (2) daftar command `backup:db`, `it:kpi-sync`, `it:uptime-check`, `it:kpi-sync --restore=ok|failed` beserta opsi; (3) cron production lengkap dengan path `/home/icleargr/SubDomain/erp.icleargroup.id/ERP` dan `/usr/local/bin/php`; (4) file histori `backups/history.jsonl`, `restore.jsonl`, `uptime.jsonl` dengan contoh baris; (5) rumus skor tiap komponen termasuk cap restore 50; (6) arti N/A vs 0; (7) troubleshooting (PHP 8.5 vs CI 4.4.8 di lokal, ledger kosong, hari gagal). Gaya mengikuti `backup-database.md` (bahasa Indonesia, tabel opsi, langkah bernomor).

- [ ] **Step 2: Perbarui dokumen terkait**

`backup-database.md`: satu paragraf + tautan "lihat `docs/operations/kpi-it.md`". PRD Modul 08 item KPI IT: sumber uptime = cek terjadwal 3x/hari, bukan UptimeRobot, plus acceptance criteria baru. `kpi-business-rules.md`: aturan hari OK/gagal/`start`.

- [ ] **Step 3: Verifikasi tautan dan istilah**

Run: `grep -rn "kpi-it.md" docs | wc -l` → minimal 2; `grep -rni "uptimerobot" docs` → kosong.

- [ ] **Step 4: Commit**

```bash
git add docs/operations/kpi-it.md docs/operations/backup-database.md docs/prd/modul/PRD_MODUL_08_KPI_Penilaian.md docs/architecture/kpi-business-rules.md
git commit -m "docs(kpi): document IT KPI operations end to end (refs #<issue>)"
```

---

### Task 5: Verifikasi menyeluruh dan penutupan issue

**Files:** tidak ada file baru.

- [ ] **Step 1: Lint**

Run: `docker compose exec erp-app sh -lc 'for f in app/Services/Kpi/*.php app/Commands/It*.php tests/unit/ItKpiTest.php tests/unit/ItUptimeCheckTest.php; do php -l "$f"; done'`
Expected: `No syntax errors detected` untuk semua file.

- [ ] **Step 2: Test terarah**

Run: `docker compose exec erp-app vendor/bin/phpunit -c phpunit.xml tests/unit/ItKpiTest.php tests/unit/ItUptimeCheckTest.php`
Expected: PASS, 0 failure.

- [ ] **Step 3: Regression KPI**

Run: `docker compose exec erp-app vendor/bin/phpunit -c phpunit.xml tests/unit/DigitalMarketingKpiTest.php tests/unit/KpiOmsetTrialUnitTest.php tests/unit/FinanceKpiTest.php`
Expected: PASS.

- [ ] **Step 4: Periksa diff**

Run: `git diff --check && git status --short`
Expected: tanpa whitespace error; hanya file yang direncanakan.

- [ ] **Step 5: Update issue**

Komentari issue dengan hash commit, perintah test, dan hasilnya; pindahkan status ke **Testing**.
