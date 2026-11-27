# Service Intake Stale Session Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hentikan penyuntingan senyap tiket service lama dari halaman intake dan pastikan nota hanya tercetak untuk tiket yang sah.

**Architecture:** Satu guard kecil (`ServiceIntakeGuard`) memutuskan tiket mana yang boleh disunting dari `/service` (status 1/2 dan unit dalam cakupan). Controller memakai guard itu untuk mereset sesi basi, menolak penulisan, dan mencegah cetak. View menampilkan banner status dan tombol **Service Baru**.

**Tech Stack:** CodeIgniter 4.4.8, PHP 8.x, MySQLi (`erp_test` untuk test), PHPUnit, Bootstrap 5 + jQuery.

**Spec:** `docs/superpowers/specs/2026-11-27-service-intake-stale-session-design.md`

## Global Constraints

- Jangan commit sebelum user menginstruksikan (AGENTS.md D). Setiap langkah "Commit" hanya dijalankan bila user sudah menyetujui commit.
- Jangan mengubah skema database produksi, `Service::insert_pembayaran`, endpoint `update_service/save*`, atau filter `/proses_service`.
- Test hanya memakai group `tests` (`erp_test`, prefix `db_`); jangan arahkan ke `erp_local`/produksi.
- Jalankan `php -l` pada setiap file PHP yang diubah.
- Semua pesan UI berbahasa Indonesia; identifier kode berbahasa Inggris.
- Setelah implementasi, perbarui `docs/prd/modul/PRD_MODUL_04_Service_Garansi.md`.
- Format commit (bila diminta): `fix(service): ... (refs #251)`.

## Review Focus

- Tiket status `3` (bisa diambil): intake harus **reset**, bukan lanjut menyunting; penyuntingan tetap lewat halaman detail.
- Role HO (`0/1/34`) dan SPV (`40`): boleh menyentuh tiket lintas unit sesuai cakupan yang sudah ada; jangan blokir.
- Tab lama tanpa `session('idservice')` yang mengirim `idservice_k`: harus ditolak, bukan mencetak nota.
- Halaman detail tiket selesai: penyuntingan yang sekarang berjalan tidak boleh ikut terblokir.
- `selectedidpelanggan` kosong/`0` dari intake: bukan perubahan pelanggan, jangan ditolak.
- `idservice_k` datang sebagai string: bandingkan setelah cast `(int)`.

---

### Task 1: Guard status dan cakupan unit

**Files:**
- Create: `app/Services/Service/ServiceIntakeGuard.php`
- Test: `tests/unit/ServiceIntakeGuardTest.php`

**Interfaces:**
- Produces:
  - `ServiceIntakeGuard::__construct(?array $context = null, ?ServiceUnitScopeService $scope = null)` — `$context` berisi `['role' => int, 'unit' => int]`; bila null, dibaca dari session.
  - `isEditableStatus($status): bool`
  - `checkTicket($service, array $allowedUnitIds): array{ok: bool, reason: string}` — `reason` = `''|'missing'|'closed'|'unit'`.
  - `allowedUnitIds(): array` — array kosong berarti semua unit (HO); `[-1]` berarti tidak ada unit sah.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\unit;

use App\Services\Service\ServiceIntakeGuard;
use CodeIgniter\Test\CIUnitTestCase;

final class ServiceIntakeGuardTest extends CIUnitTestCase
{
    public function testHanyaStatusSatuDanDuaYangBolehDisuntingDariIntake(): void
    {
        $guard = new ServiceIntakeGuard(['role' => 5, 'unit' => 1]);

        $this->assertTrue($guard->isEditableStatus(1));
        $this->assertTrue($guard->isEditableStatus(2));
        $this->assertTrue($guard->isEditableStatus('2'));
        $this->assertFalse($guard->isEditableStatus(3));
        $this->assertFalse($guard->isEditableStatus(4));
        $this->assertFalse($guard->isEditableStatus(90));
        $this->assertFalse($guard->isEditableStatus(91));
        $this->assertFalse($guard->isEditableStatus(null));
    }

    public function testCheckTicketMenolakTiketHilangTutupDanUnitLain(): void
    {
        $guard = new ServiceIntakeGuard(['role' => 5, 'unit' => 1]);

        $this->assertSame(['ok' => false, 'reason' => 'missing'], $guard->checkTicket(null, [1]));
        $this->assertSame(
            ['ok' => false, 'reason' => 'closed'],
            $guard->checkTicket((object) ['status_service' => 4, 'unit_idunit' => 1], [1])
        );
        $this->assertSame(
            ['ok' => false, 'reason' => 'unit'],
            $guard->checkTicket((object) ['status_service' => 1, 'unit_idunit' => 2], [1])
        );
        $this->assertSame(
            ['ok' => true, 'reason' => ''],
            $guard->checkTicket((object) ['status_service' => 2, 'unit_idunit' => 1], [1])
        );
    }

    public function testCheckTicketMenerimaArrayDanSemuaUnit(): void
    {
        $guard = new ServiceIntakeGuard(['role' => 1, 'unit' => 1]);

        $this->assertSame(
            ['ok' => true, 'reason' => ''],
            $guard->checkTicket(['status_service' => 1, 'unit_idunit' => 99], [])
        );
    }

    public function testAllowedUnitIdsMengikutiRole(): void
    {
        $this->assertSame([], (new ServiceIntakeGuard(['role' => 1, 'unit' => 1]))->allowedUnitIds());
        $this->assertSame([], (new ServiceIntakeGuard(['role' => 34, 'unit' => 1]))->allowedUnitIds());
        $this->assertSame([3], (new ServiceIntakeGuard(['role' => 5, 'unit' => 3]))->allowedUnitIds());
        $this->assertSame([-1], (new ServiceIntakeGuard(['role' => 5, 'unit' => 0]))->allowedUnitIds());
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/ServiceIntakeGuardTest.php`
Expected: FAIL — class `ServiceIntakeGuard` tidak ditemukan.

- [ ] **Step 3: Implementasikan `app/Services/Service/ServiceIntakeGuard.php`**

Konstanta: `EDITABLE_STATUSES = [1, 2]`, `HO_ROLES = [0, 1, 34]`.
`allowedUnitIds()`: HO → `[]`; SPV (`ServiceUnitScopeService::SPV_ROLE`) → `$this->scope()->spvUnitIds()`; lainnya → `[$unit]` bila `$unit > 0`, selain itu `[-1]`.
Scope dibuat lazy di dalam `allowedUnitIds()` supaya test HO/unit tidak perlu koneksi DB.
`checkTicket()` menerima object atau array; cast status/unit ke `(int)`; urutan cek: kosong → `missing`, status bukan 1/2 → `closed`, unit tidak ada di daftar (bila daftar tidak kosong) → `unit`.

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/ServiceIntakeGuardTest.php`
Expected: PASS (4 test).

- [ ] **Step 5: Lint**

Run: `php -l app/Services/Service/ServiceIntakeGuard.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Commit (hanya bila user menginstruksikan)**

```bash
git add app/Services/Service/ServiceIntakeGuard.php tests/unit/ServiceIntakeGuardTest.php
git commit -m "feat(service): add intake guard for editable tickets (refs #251)"
```

---

### Task 2: Reset sesi basi di `Service::index()` + banner dan tombol Service Baru

**Files:**
- Modify: `app/Controllers/Service.php:77-102`
- Modify: `app/Views/transaksi/service.php` (sisipkan setelah blok header, sebelum `<div class="row g-3" id="service-deck">` di baris 244)
- Create: `tests/ServiceIntakeSessionTest.php`

**Interfaces:**
- Consumes: `ServiceIntakeGuard::checkTicket()`, `ServiceIntakeGuard::allowedUnitIds()` dari Task 1.
- Produces: view `transaksi/service` menerima `intake_ticket` (object `service` atau null).

- [ ] **Step 1: Buat feature test dengan tabel uji dan dua skenario GET**

Buat `tests/ServiceIntakeSessionTest.php` (namespace `Tests`, `CIUnitTestCase` + `FeatureTestTrait`). Salin pola `tests/ServiceCancelDpTest.php`: `setUp()` membuat koneksi fresh `Config\Database::connect('tests', false)`, `buatTabel()` men-drop lalu membuat tabel `db_*`, `tearDown()` men-drop semuanya.

Tabel minimum:
- `db_akun`, `db_jabatan`, `db_unit`, `db_pelanggan`, `db_stok_barang` — sama seperti `ServiceCancelDpTest`.
- `db_pelanggan` tambah kolom `provinsi`, `kabupaten`, `kecamatan` (VARCHAR NULL) dan `deleted` (INT DEFAULT 0).
- `db_service` — pakai kolom produksi: `idservice` (PK AI), `no_service`, `no_hp`, `imei`, `alamat`, `keluhan`, `keterangan`, `passcode`, `tipe_hp`, `type_passcode`, `email_icloud`, `password_icloud`, `status_service` (INT DEFAULT 1), `status_proses` (INT DEFAULT 1), `total_service`, `total_diskon`, `harus_dibayar`, `bayar`, `dp_bayar`, `garansi_hari`, `pelanggan_id_pelanggan`, `unit_idunit`, `service_by`, `input_by`, `tanggal_bisa_diambil`, `tanggal_selesai`, `created_at`, `updated_at`, `estimasi_biaya`, `tanggal_claim_garansi`, `biaya_tambahan_garansi`, `total_service_garansi`, `total_diskon_garansi`, `service_by_garansi`, `bayar_garansi`, `harus_dibayar_garansi`, `bayar_tunai`, `bayar_bank`, `bayar_tunai_garansi`, `prioritas` (INT DEFAULT 0), `refund_garansi_*` (NULL/default 0).
- `db_service_kerusakan` (`idservice_kerusakan` PK AI, `service_idservice`, `fungsi_idfungsi`, `keterangan`, `created_at`).
- `db_service_sparepart` (`idservice_sparepart` PK AI, `service_idservice`, `barang_idbarang`, `jumlah`, `unit_idunit`).
- `db_kerusakan` (`idfungsi` PK AI, `nama_fungsi`, `deleted` INT DEFAULT 0).
- `db_barang` (`idbarang` PK AI, `kode_barang`, `nama_barang`).
- `db_bank` (`idbank` PK AI, `nama_bank`, `atas_nama`, `norek`).
- `db_provinces` (`id`, `name`).

Seed: akun `43` (jabatan 1, unit 1), akun `44` (jabatan 5, unit 1), jabatan `1` dan `5`, pelanggan `10` dan `11`, satu baris `db_kerusakan`.

Helper test:

```php
private function sesi(array $extra = []): void
{
    $this->withSession(array_merge([
        'logged_in'  => true,
        'ID_AKUN'    => 43,
        'ID_UNIT'    => 1,
        'ID_JABATAN' => 1,
    ], $extra));
}

private function seedService(array $override = []): int
{
    $this->db->table('service')->insert(array_merge([
        'no_service'             => 'SRV-UJI-1',
        'status_service'         => 1,
        'pelanggan_id_pelanggan' => 10,
        'unit_idunit'            => 1,
        'service_by'             => 43,
        'input_by'               => 43,
        'created_at'             => date('Y-m-d H:i:s'),
    ], $override));

    return (int) $this->db->insertID();
}
```

Test GET:

```php
public function testSesiTiketSelesaiDiresetKeFormServiceBaru(): void
{
    $id = $this->seedService(['status_service' => 4, 'no_service' => 'SRV-LAMA']);
    $this->sesi(['idservice' => $id]);

    $html = (string) $this->get('service')->assertStatus(200)->getBody();

    $this->assertStringContainsString('insert/pelanggan_service', $html);
    $this->assertStringNotContainsString('update/pelanggan_service', $html);
    $this->assertStringNotContainsString('service/clear_session', $html);
}

public function testSesiTiketBisaDiambilDiresetKeFormServiceBaru(): void
{
    $id = $this->seedService(['status_service' => 3, 'no_service' => 'SRV-AMBIL']);
    $this->sesi(['idservice' => $id]);

    $html = (string) $this->get('service')->assertStatus(200)->getBody();

    $this->assertStringContainsString('insert/pelanggan_service', $html);
    $this->assertStringNotContainsString('service/clear_session', $html);
}

public function testSesiTiketUnitLainDiresetUntukRoleUnit(): void
{
    $id = $this->seedService(['status_service' => 1, 'unit_idunit' => 2]);
    $this->withSession([
        'logged_in'  => true,
        'ID_AKUN'    => 44,
        'ID_UNIT'    => 1,
        'ID_JABATAN' => 5,
        'idservice'  => $id,
    ]);

    $html = (string) $this->get('service')->assertStatus(200)->getBody();

    $this->assertStringContainsString('insert/pelanggan_service', $html);
    $this->assertStringNotContainsString('service/clear_session', $html);
}

public function testSesiTiketAktifMenampilkanBannerDanTombolServiceBaru(): void
{
    $id = $this->seedService(['status_service' => 2, 'no_service' => 'SRV-AKTIF']);
    $this->sesi(['idservice' => $id]);

    $html = (string) $this->get('service')->assertStatus(200)->getBody();

    $this->assertStringContainsString('update/pelanggan_service', $html);
    $this->assertStringContainsString('Sedang menyunting tiket', $html);
    $this->assertStringContainsString('SRV-AKTIF', $html);
    $this->assertStringContainsString('Service Baru', $html);
    $this->assertStringContainsString('service/clear_session', $html);
}
```

Bila halaman error karena kolom/tabel lain yang dibaca `BaseController` atau view, tambahkan kolom/tabel itu ke `buatTabel()`; jangan ubah kode aplikasi untuk membuat test lulus.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/ServiceIntakeSessionTest.php`
Expected: FAIL pada dua test — form masih `update/pelanggan_service` dan banner belum ada.

- [ ] **Step 3: Ubah `Service::index()`**

Ganti blok `$idservice = session('idservice') ?? null;` dengan:

```php
$guard = new ServiceIntakeGuard();
$idservice = session('idservice') ?? null;
$intakeTicket = null;

if (!empty($idservice)) {
    $ticket = $this->ServiceModel->find($idservice);
    $check = $guard->checkTicket($ticket, $guard->allowedUnitIds());

    if ($check['ok']) {
        $intakeTicket = $ticket;
    } else {
        session()->remove('idservice');
        $idservice = null;
        $pesan = [
            'closed' => 'Tiket sebelumnya sudah selesai atau dibatalkan. Form dimulai dari service baru.',
            'unit'   => 'Tiket sebelumnya milik unit lain. Form dimulai dari service baru.',
        ][$check['reason']] ?? '';
        if ($pesan !== '') {
            session()->setFlashdata('gagal', $pesan);
        }
    }
}
```

Tambahkan `use App\Services\Service\ServiceIntakeGuard;` dan `'intake_ticket' => $intakeTicket,` ke array `$data`.

- [ ] **Step 4: Tambah banner di `app/Views/transaksi/service.php`**

Sisipkan tepat sebelum `<div class="row g-3" id="service-deck">`:

```php
<?php if (!empty($idservice) && !empty($intake_ticket)): ?>
    <?php $statusLabel = ((int) $intake_ticket->status_service === 1) ? 'Menunggu' : 'Proses'; ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3" role="status">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>Sedang menyunting tiket <strong><?= esc($intake_ticket->no_service) ?></strong> (status: <?= esc($statusLabel) ?>).</span>
        </div>
        <a href="<?= base_url('service/clear_session') ?>" class="btn btn-sm btn-outline-primary"
           onclick="return confirm('Mulai service baru? Tiket ini tetap tersimpan dan bisa dilanjutkan dari halaman Proses Service.')">
            <i class="bi bi-plus-lg me-1"></i> Service Baru
        </a>
    </div>
<?php endif ?>
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/ServiceIntakeSessionTest.php`
Expected: PASS (4 test).

- [ ] **Step 6: Lint**

Run: `php -l app/Controllers/Service.php && php -l app/Views/transaksi/service.php`
Expected: `No syntax errors detected` untuk keduanya.

- [ ] **Step 7: Commit (hanya bila user menginstruksikan)**

```bash
git add app/Controllers/Service.php app/Views/transaksi/service.php tests/ServiceIntakeSessionTest.php
git commit -m "fix(service): reset stale intake session and expose new service action (refs #251)"
```

---

### Task 3: Guard pada `update_service_pelanggan()` dan perubahan pelanggan dari halaman detail

**Files:**
- Modify: `app/Controllers/Riwayat_Service.php:261-307`
- Test: `tests/ServiceIntakeSessionTest.php`

**Interfaces:**
- Consumes: `ServiceIntakeGuard` (Task 1), `ModelPelanggan::getById()`, `ModelService::updateService()`.

- [ ] **Step 1: Tambah test yang gagal**

```php
public function testEditPelangganDariIntakeDitolakSaatTiketSelesai(): void
{
    $id = $this->seedService(['status_service' => 4, 'tipe_hp' => 'iPhone Lama']);
    $this->sesi(['idservice' => $id]);

    $this->post('update/pelanggan_service', [
        'idservice'              => $id,
        'return_to'              => 'service',
        'selectedidpelanggan'    => 10,
        'tipe_hp'                => 'iPhone Baru',
        'imei'                   => '999',
    ])->assertStatus(302);

    $row = $this->koneksiFresh()->table('service')->where('idservice', $id)->get()->getRow();
    $this->assertSame('iPhone Lama', $row->tipe_hp);
    $this->assertStringContainsString('service baru', strtolower($this->flashGagal()));
}

public function testEditPelangganDariIntakeDitolakBilaPelangganBerbeda(): void
{
    $id = $this->seedService(['status_service' => 2, 'pelanggan_id_pelanggan' => 10]);
    $this->sesi(['idservice' => $id]);

    $this->post('update/pelanggan_service', [
        'idservice'           => $id,
        'return_to'           => 'service',
        'selectedidpelanggan' => 11,
        'tipe_hp'             => 'iPhone Baru',
    ])->assertStatus(302);

    $row = $this->koneksiFresh()->table('service')->where('idservice', $id)->get()->getRow();
    $this->assertSame(10, (int) $row->pelanggan_id_pelanggan);
    $this->assertStringContainsString('service baru', strtolower($this->flashGagal()));
}

public function testEditDariIntakeTanpaPilihanPelangganTetapDisimpan(): void
{
    $id = $this->seedService(['status_service' => 2]);
    $this->sesi(['idservice' => $id]);

    $this->post('update/pelanggan_service', [
        'idservice'           => $id,
        'return_to'           => 'service',
        'selectedidpelanggan' => '',
        'tipe_hp'             => 'iPhone Baru',
    ])->assertStatus(302);

    $row = $this->koneksiFresh()->table('service')->where('idservice', $id)->get()->getRow();
    $this->assertSame('iPhone Baru', $row->tipe_hp);
}

public function testGantiPelangganDariHalamanDetailTersimpan(): void
{
    $id = $this->seedService(['status_service' => 4, 'pelanggan_id_pelanggan' => 10]);
    $this->sesi();

    $this->post('update/pelanggan_service', [
        'idservice'           => $id,
        'selectedidpelanggan' => 11,
    ])->assertStatus(302);

    $row = $this->koneksiFresh()->table('service')->where('idservice', $id)->get()->getRow();
    $this->assertSame(11, (int) $row->pelanggan_id_pelanggan);
}
```

Tambahkan helper:

```php
private function flashGagal(): string
{
    return (string) ($_SESSION['gagal'] ?? '');
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/ServiceIntakeSessionTest.php --filter 'Pelanggan'`
Expected: FAIL — tiket selesai tetap berubah dan pelanggan detail tidak tersimpan.

- [ ] **Step 3: Implementasikan guard dan penanganan pelanggan**

Di awal `update_service_pelanggan()` setelah `$idservice`:

```php
$dariIntake = $this->request->getPost('return_to') === 'service';

if ($dariIntake) {
    $guard = new ServiceIntakeGuard();
    $ticket = $this->ServiceModel->find($idservice);
    $check = $guard->checkTicket($ticket, $guard->allowedUnitIds());

    if (! $check['ok']) {
        session()->setFlashdata('gagal', 'Tiket ini sudah selesai atau bukan milik unit Anda. Gunakan tombol Service Baru untuk memulai tiket lain.');
        return redirect()->to(base_url('service'));
    }

    $pelangganDipilih = (int) $this->request->getPost('selectedidpelanggan');
    if ($pelangganDipilih > 0 && $pelangganDipilih !== (int) $ticket->pelanggan_id_pelanggan) {
        session()->setFlashdata('gagal', 'Pelanggan tiket tidak bisa diubah dari form ini. Gunakan tombol Service Baru untuk pelanggan lain.');
        return redirect()->to(base_url('service'));
    }
}
```

Setelah `$service = $this->ServiceModel->find($idservice);`, ganti blok update domisili yang ada dengan urutan berikut:

```php
if (! $dariIntake) {
    $pelangganDipilih = (int) $this->request->getPost('selectedidpelanggan');
    if ($pelangganDipilih > 0
        && $pelangganDipilih !== (int) ($service->pelanggan_id_pelanggan ?? 0)
        && $this->PelangganModel->getById($pelangganDipilih)) {
        $this->ServiceModel->updateService($idservice, ['pelanggan_id_pelanggan' => $pelangganDipilih]);
        $service = $this->ServiceModel->find($idservice);
    }
}

$domisili = array_filter([
    'provinsi'  => $this->request->getPost('domisili_provinsi'),
    'kabupaten' => $this->request->getPost('domisili_kabupaten'),
    'kecamatan' => $this->request->getPost('domisili_kecamatan'),
], static fn ($nilai) => $nilai !== null && $nilai !== '');

if ($service && !empty($service->pelanggan_id_pelanggan) && $domisili !== []) {
    $this->PelangganModel->update($service->pelanggan_id_pelanggan, $domisili);
}
```

Alasan `array_filter`: saat pelanggan diganti dari halaman detail, field domisili bisa kosong; tanpa penjagaan ini domisili pelanggan baru akan tertimpa string kosong.

Tambahkan `use App\Services\Service\ServiceIntakeGuard;`.

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/ServiceIntakeSessionTest.php --filter 'Pelanggan'`
Expected: PASS (4 test).

- [ ] **Step 5: Lint**

Run: `php -l app/Controllers/Riwayat_Service.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Commit (hanya bila user menginstruksikan)**

```bash
git add app/Controllers/Riwayat_Service.php tests/ServiceIntakeSessionTest.php
git commit -m "fix(service): block stale intake edits and honor detail customer change (refs #251)"
```

---

### Task 4: Guard simpan kerusakan dan sparepart dari intake

**Files:**
- Modify: `app/Controllers/Service.php:305-355` (`insert_kerusakan`)
- Modify: `app/Controllers/Service.php:358-370` (`insert_sparepart`)
- Test: `tests/ServiceIntakeSessionTest.php`

- [ ] **Step 1: Tambah test yang gagal**

```php
public function testKerusakanTidakDisimpanDanTidakMencetakSaatTiketSelesai(): void
{
    $id = $this->seedService(['status_service' => 4]);
    $this->sesi(['idservice' => $id]);

    $r = $this->post('service/saveKerusakan', [
        'idservice_k' => $id,
        'fungsi'      => [1],
        'keterangan'  => [1 => 'retak'],
    ]);

    $r->assertStatus(302);
    $this->assertStringContainsString('service?tab=kerusakan', (string) $r->getHeaderLine('Location'));
    $this->assertStringNotContainsString('cetak/invoice_service', (string) $r->getHeaderLine('Location'));
    $this->assertSame(0, (int) $this->koneksiFresh()->table('service_kerusakan')->countAllResults());
}

public function testKerusakanDitolakBilaIdTidakSamaDenganSesi(): void
{
    $aktif = $this->seedService(['status_service' => 1, 'no_service' => 'SRV-AKTIF']);
    $lain  = $this->seedService(['status_service' => 1, 'no_service' => 'SRV-LAIN']);
    $this->sesi(['idservice' => $aktif]);

    $r = $this->post('service/saveKerusakan', [
        'idservice_k' => $lain,
        'fungsi'      => [1],
    ]);

    $r->assertStatus(302);
    $this->assertStringNotContainsString('cetak/invoice_service', (string) $r->getHeaderLine('Location'));
    $this->assertSame(0, (int) $this->koneksiFresh()->table('service_kerusakan')->countAllResults());
}

public function testKerusakanTiketAktifTetapTersimpanDanMencetak(): void
{
    $id = $this->seedService(['status_service' => 1]);
    $this->sesi(['idservice' => $id]);

    $r = $this->post('service/saveKerusakan', [
        'idservice_k' => $id,
        'fungsi'      => [1],
        'keterangan'  => [1 => 'retak'],
    ]);

    $r->assertStatus(302);
    $this->assertStringContainsString('cetak/invoice_service/' . $id, (string) $r->getHeaderLine('Location'));
    $this->assertSame(1, (int) $this->koneksiFresh()->table('service_kerusakan')->countAllResults());
}

public function testSparepartDitolakSaatTiketSelesai(): void
{
    $id = $this->seedService(['status_service' => 4, 'garansi_hari' => 7]);
    $this->sesi(['idservice' => $id]);

    $r = $this->post('service/saveSparepart', [
        'idservice_s' => $id,
        'garansi'     => '0',
    ]);

    $r->assertStatus(302);
    $row = $this->koneksiFresh()->table('service')->where('idservice', $id)->get()->getRow();
    $this->assertSame(7, (int) $row->garansi_hari);
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/ServiceIntakeSessionTest.php --filter 'Kerusakan|Sparepart'`
Expected: FAIL — kerusakan tiket selesai tersimpan dan halaman cetak terbuka.

- [ ] **Step 3: Implementasikan guard di `insert_kerusakan()`**

Sisipkan setelah `$idservice = $this->request->getPost('idservice_k');`:

```php
$guard = new ServiceIntakeGuard();
$ticket = $this->ServiceModel->find($idservice);
$check = $guard->checkTicket($ticket, $guard->allowedUnitIds());

if (! $check['ok'] || (int) $idservice !== (int) session('idservice')) {
    session()->setFlashdata('gagal', 'Data service tidak valid atau sudah selesai. Kerusakan tidak disimpan dan nota tidak dicetak.');
    return redirect()->to(base_url('service?tab=kerusakan'));
}
```

- [ ] **Step 4: Implementasikan guard di `insert_sparepart()`**

Sisipkan setelah blok `if ($idservice <= 0) { ... }`:

```php
$guard = new ServiceIntakeGuard();
$ticket = $this->ServiceModel->find($idservice);
$check = $guard->checkTicket($ticket, $guard->allowedUnitIds());

if (! $check['ok'] || $idservice !== (int) session('idservice')) {
    return redirect()->to('/service?tab=sparepart')->with('gagal', 'Data service tidak valid atau sudah selesai. Sparepart tidak disimpan.');
}
```

- [ ] **Step 5: Jalankan seluruh test file, pastikan lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/ServiceIntakeSessionTest.php`
Expected: PASS (semua test).

- [ ] **Step 6: Lint**

Run: `php -l app/Controllers/Service.php`
Expected: `No syntax errors detected`.

- [ ] **Step 7: Commit (hanya bila user menginstruksikan)**

```bash
git add app/Controllers/Service.php tests/ServiceIntakeSessionTest.php
git commit -m "fix(service): reject intake writes for closed or foreign tickets (refs #251)"
```

---

### Task 5: Pesan guard intake + PRD Modul 04

**Files:**
- Modify: `app/Controllers/Service.php:139-149`
- Modify: `docs/prd/modul/PRD_MODUL_04_Service_Garansi.md`
- Test: `tests/ServiceIntakeSessionTest.php`

- [ ] **Step 1: Tambah test yang gagal**

```php
public function testGuardInsertServiceMenawarkanServiceBaru(): void
{
    $id = $this->seedService(['status_service' => 2, 'no_service' => 'SRV-AKTIF']);
    $this->sesi(['idservice' => $id]);

    $this->post('insert/pelanggan_service', [
        'selectedidpelanggan' => 10,
    ])->assertStatus(302);

    $this->assertStringContainsString('Service Baru', $this->flashGagal());
    $this->assertStringContainsString('service/clear_session', $this->flashGagal());
    $this->assertSame(1, (int) $this->koneksiFresh()->table('service')->countAllResults());
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/ServiceIntakeSessionTest.php --filter 'GuardInsertService'`
Expected: FAIL — pesan masih menautkan `service/cancel`.

- [ ] **Step 3: Ubah pesan guard `insert_service()`**

Ganti pesan pada `if ($existingService && in_array($existingService->status_service, [1, 2, 3]))` menjadi:

```php
session()->setFlashdata('gagal', 'Gagal! Anda masih memiliki service aktif (No: ' . $existingService->no_service . '). Selesaikan dulu, atau klik <a href="' . base_url('service/clear_session') . '">Service Baru</a> untuk memulai tiket lain.');
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/ServiceIntakeSessionTest.php`
Expected: PASS (semua test).

- [ ] **Step 5: Perbarui PRD Modul 04**

Di `docs/prd/modul/PRD_MODUL_04_Service_Garansi.md`:
- Bagian `4.1 Intake Service`: tambahkan bahwa sesi `idservice` hanya dipertahankan bila tiket berstatus 1/2 dan unitnya dalam cakupan user; selain itu sesi direset dan form mulai dari service baru.
- Bagian `5. Business Rules`: perbarui butir 1 — satu tiket aktif per user tetap berlaku, tetapi pengguna bisa memilih **Service Baru** (hapus sesi) tanpa menghapus tiket lama.
- Tambahkan aturan: penyuntingan pelanggan dari `/service` ditolak bila pelanggan berbeda; dari halaman detail, perubahan pelanggan disimpan setelah validasi.
- Tandai aturan baru dengan `✅` hanya setelah test lulus.

- [ ] **Step 6: Verifikasi tidak ada perubahan tak terkait**

Run: `git diff --check && git status --short`
Expected: hanya file yang tercantum di plan yang berubah.

- [ ] **Step 7: Commit (hanya bila user menginstruksikan)**

```bash
git add app/Controllers/Service.php docs/prd/modul/PRD_MODUL_04_Service_Garansi.md tests/ServiceIntakeSessionTest.php
git commit -m "docs(service): document intake session and new service action (refs #251)"
```

---

## Verifikasi Akhir

- [ ] `vendor/bin/phpunit -c phpunit.xml tests/unit/ServiceIntakeGuardTest.php` → PASS.
- [ ] `vendor/bin/phpunit -c phpunit.xml tests/ServiceIntakeSessionTest.php` → PASS.
  (PHPUnit 9 hanya menerima satu path; jalankan terpisah.)
- [ ] `php -l` untuk `app/Services/Service/ServiceIntakeGuard.php`, `app/Controllers/Service.php`, `app/Controllers/Riwayat_Service.php`, `app/Views/transaksi/service.php`.
- [ ] `git diff --check`.
- [ ] Regresi manual cepat (bila DB lokal tersedia): buat tiket status 1, buka `/service`, pastikan banner + tombol Service Baru; klik Service Baru, pastikan form kosong dan tiket lama tetap ada di `/proses_service`.
- [ ] Komentari issue #251 dengan hash commit + hasil test, lalu pindahkan ke **Testing**.
