# Plan — Sinkronisasi Unit Sesi (#252)

**Spec:** `docs/superpowers/specs/2026-11-27-unit-session-sync-design.md`

Aturan kerja: TDD (test merah dulu), test memakai database test `erp_test` lewat
`phpunit.xml`, tidak ada commit sampai user meminta.

## Task 1 — Service sinkronisasi + unit test

**Buat:** `app/Services/Auth/SessionUnitSyncService.php`

- `sync(string $method, bool $isAjax): ?array`
- Hanya `GET` dan `! $isAjax`.
- Baca `akun.ID_UNIT` berdasarkan `session('ID_AKUN')`; dukung nilai JSON array
  seperti login (ambil elemen pertama).
- Bila berbeda: set `ID_UNIT` + `NAMA_UNIT`, hapus `idservice`, catat log,
  kembalikan `['old','new','old_name','new_name','ticket_reset']`.
- Null-safe: akun/ID_UNIT NULL, ID_AKUN kosong, atau DB error → `null`.
- Nama unit fallback `'Unit <id>'`.

**Test:** `tests/unit/SessionUnitSyncServiceTest.php`

- GET menyinkronkan + reset `idservice` + nama unit.
- POST tidak menyinkronkan.
- AJAX GET tidak menyinkronkan.
- Unit sama → tidak berubah, `idservice` tetap.
- Akun tidak ditemukan / ID_AKUN kosong → tidak berubah.
- Nama unit fallback.
- Unit 0 disinkronkan.
- Tabel `akun` tidak ada → `null`, tanpa exception.

**Verifikasi:**
`docker compose exec -T erp-app vendor/bin/phpunit -c phpunit.xml tests/unit/SessionUnitSyncServiceTest.php` → PASS.

## Task 2 — Integrasi AuthFilter + feature test

**Ubah:** `app/Filters/AuthFilter.php`

- Setelah cek `logged_in`, panggil service; bila ada perubahan, set flashdata
  `sukses` berisi pesan unit lama → baru dan info reset tiket.

**Test:** tambah ke `tests/ServiceIntakeSessionTest.php`

- `GET /service` saat `db_akun.ID_UNIT` diubah → `session('ID_UNIT')`/`NAMA_UNIT`
  ikut, `idservice` terhapus, flashdata `sukses` berisi unit baru, halaman tidak
  lagi menampilkan tombol `service/clear_session`.
- POST `service/saveKerusakan` saat unit akun berubah → `session('ID_UNIT')` tetap.
- GET AJAX (`X-Requested-With: XMLHttpRequest`) → `session('ID_UNIT')` tetap.

**Verifikasi:**
`docker compose exec -T erp-app vendor/bin/phpunit -c phpunit.xml tests/ServiceIntakeSessionTest.php` → PASS.

## Task 3 — Dokumentasi + verifikasi penuh

**Dokumentasi:**

- `docs/architecture/unit-scope.md` — section sinkronisasi unit sesi.
- `docs/prd/PRD_MASTER.md` — catatan bahwa `ID_UNIT` sesi disinkronkan pada GET
  non-AJAX.

**Verifikasi:**

- `php -l` semua file PHP yang diubah.
- `vendor/bin/phpunit -c phpunit.xml tests/unit/SessionUnitSyncServiceTest.php`
- `vendor/bin/phpunit -c phpunit.xml tests/ServiceIntakeSessionTest.php`
- `vendor/bin/phpunit -c phpunit.xml` (seluruh suite) — catat hasil aktual.
- `git diff --check`.

## Hasil verifikasi (27 November 2026)

- `tests/unit/SessionUnitSyncServiceTest.php` → OK (10 tests, 27 assertions).
- `tests/ServiceIntakeSessionTest.php` → OK (21 tests, 71 assertions), termasuk
  GET sinkron + reset tiket + flash, POST tidak sinkron, AJAX GET tidak sinkron.
- `php -l` bersih untuk `SessionUnitSyncService`, `AuthFilter`, dan kedua file test.
- `git diff --check` bersih.

Seluruh suite tidak dapat diselesaikan di environment ini: OOM 128 MB dan banyak
error/failure lama dari polusi database test (tabel sisa dengan FK). Failure yang
diperiksa (`AssetBerjalanFilterTest`, `KasKeluarUnitFilterTest`, `RosterPageTest`,
`TutupKasirClosingHardeningTest`, `DpServiceUnitFilterTest`, `ServiceCancelDpTest`)
identik saat `AuthFilter` dikembalikan ke versi tanpa sinkronisasi, jadi bukan
regresi dari perubahan ini. File feature yang lulus: `TarifJasaPageTest`,
`AuthRedirectAfterLoginTest`, `PenjualanPageTest`, `PembelianPageTest`,
`KasMasukServerSideTest`, `StokAwalReaktivasiTest`.
