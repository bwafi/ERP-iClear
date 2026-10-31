# Kembalikan Service ke Proses — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admin Root dapat mengembalikan service berstatus "sudah diambil" ke Proses Service, menghapus pembayaran yang salah dan jurnal pengambilan, lalu mengisi ulang pembayaran sebelum menandai diambil lagi.

**Architecture:** Satu endpoint POST baru di `Riwayat_Service` menjalankan satu transaksi database: kunci baris service, hapus jurnal `service`, hapus baris `pembayaran_bank` (`service_baru`), reset kolom pembayaran, dan ubah status ke 2. Tombol muncul di halaman Pengambilan Service hanya untuk Admin Root; DP tidak disentuh.

**Tech Stack:** CodeIgniter 4.4.8, PHP 8.x, MySQLi, PHPUnit (database test `erp_test`, prefix `db_`), Bootstrap 5 + jQuery/DataTables.

**Spec:** `docs/superpowers/specs/2026-10-30-kembalikan-service-ke-proses-design.md`

## Global Constraints

- Admin Root adalah `(int) session('ID_JABATAN') === 1`.
- Status service: 1/2 = proses, 3 = bisa diambil, 4 = sudah diambil.
- Endpoint baru wajib filter `['auth', 'csrf']`; token dikirim lewat `csrf_field()`.
- Jangan mengubah `dp_bayar`, ledger `service_dp`, jurnal DP, `total_service`, `harus_dibayar`, sparepart, stok, invoice, atau data pelanggan.
- Hapus hanya jurnal `tabel_referensi='service'` dan bank `tabel_referensi='service_baru'` untuk `id_referensi=idservice`.
- Bila `tutup_kasir` ada untuk unit + tanggal `service.tanggal_selesai`, wajib `konfirmasi_tutup_kasir=1`; angka tutup kasir tersimpan tidak diubah.
- Tolak service dengan `refund_garansi_at` terisi atau `GaransiClaimService::isClaimed()` true.
- **Jangan `git commit`**: user belum menginstruksikan commit. Akhiri setiap task dengan `git diff --check` dan `php -l` pada file PHP yang diubah.
- Test hanya ke database test `erp_test` lewat `phpunit.xml`; jangan arahkan ke database nyata.

## Review Focus

- **Double submit / dua request bersamaan**: request kedua harus ditolak karena status sudah 2, bukan menghapus pembayaran baru.
- **Tutup kasir pada tanggal lain**: tidak boleh memaksa konfirmasi; hanya tanggal `service.tanggal_selesai` yang relevan.
- **Sentinel klaim garansi**: `0000-00-00` dan `1970-01-01` harus dianggap "tidak diklaim" dan tidak memblokir revert.
- **Service dengan DP**: DP tetap utuh dan tidak dihitung dua kali saat pengambilan ulang.
- **Service status 4 legacy tanpa jurnal pengambilan**: revert tidak boleh gagal; pengambilan ulang memposting jurnal pertama kali dengan nominal benar.

---

### Task 1: Endpoint revert + transaksi happy path

**Files:**
- Modify: `app/Config/Routes.php` (setelah route `sudah_diambil/ajax` sekitar baris 173)
- Modify: `app/Controllers/Riwayat_Service.php` (tambah method setelah `service_sudah_diambil_ajax`)
- Test: `tests/unit/ServiceKembalikanKeProsesTest.php` (baru)

**Interfaces:**
- Consumes: `ModelJurnal::hapusByReferensi(string $tabelReferensi, $idReferensi): bool`, `ModelService::updateService($id, array $data): bool`, `GaransiClaimService::isClaimed(object $service): bool` (sudah di-import di controller).
- Produces: `Riwayat_Service::kembalikanKeProses(): RedirectResponse`; route `POST service/kembalikan_ke_proses`.

- [ ] **Step 1: Tulis test gagal untuk happy path + submit kedua**

Buat `tests/unit/ServiceKembalikanKeProsesTest.php` dengan pola `tests/ServicePengambilanDpTest.php`. Setup tabel minimal: `db_service` (tambahkan `bayar`, `bayar_bank`, `refund_garansi_at`, `tanggal_claim_garansi`, `garansi_hari`), `db_pembayaran_bank`, `db_jurnal`, `db_template_jurnal`, `db_service_dp`, `db_service_sparepart`, `db_service_kerusakan`, `db_pelanggan`, `db_unit`, `db_stok_barang`, `db_akun`, `db_jabatan`, `db_tutup_kasir`, `db_bank`. Seed akun 43 / jabatan 1 / unit 1.

```php
public function testAdminRootMengembalikanServiceKeProses(): void
{
    $this->sesi();
    $id = $this->seedService([
        'status_service' => 4,
        'tanggal_selesai' => '2026-10-30 10:00:00',
        'total_service' => 420000,
        'harus_dibayar' => 420000,
        'dp_bayar' => 0,
        'bayar' => 150000,
        'bayar_tunai' => 0,
        'bayar_bank' => 'ksr-lama',
    ]);
    $this->seedBankPaid($id, 150000);
    $this->seedJurnalService($id, 150000);

    $r = $this->post('service/kembalikan_ke_proses', [
        'idservice' => $id,
        'alasan' => 'Salah metode dan nominal',
        'csrf_test_name' => service('security')->getHash(),
    ]);

    $r->assertRedirectTo(base_url('detail/riwayat_service/' . $id . '?tab=pembayaran'));

    $service = $this->koneksiFresh()->table('service')->where('idservice', $id)->get()->getRow();
    $this->assertSame(2, (int) $service->status_service);
    $this->assertNull($service->tanggal_selesai);
    $this->assertNull($service->tanggal_bisa_diambil);
    $this->assertSame(0, (int) $service->bayar);
    $this->assertSame(0, (int) $service->bayar_tunai);
    $this->assertNull($service->bayar_bank);
    $this->assertSame(420000, (int) $service->total_service, 'Tagihan tidak berubah.');
    $this->assertSame(0, (int) $this->koneksiFresh()->table('pembayaran_bank')
        ->where('tabel_referensi', 'service_baru')->where('id_referensi', $id)->countAllResults());
    $this->assertSame([], $this->jurnalService($id));
}

public function testSubmitKeduaDitolakKarenaStatusSudahProses(): void
{
    $this->sesi();
    $id = $this->seedService(['status_service' => 4, 'tanggal_selesai' => '2026-10-30 10:00:00']);
    $payload = [
        'idservice' => $id,
        'alasan' => 'Koreksi',
        'csrf_test_name' => service('security')->getHash(),
    ];

    $this->post('service/kembalikan_ke_proses', $payload)->assertStatus(302);
    $this->post('service/kembalikan_ke_proses', $payload)->assertStatus(302);

    $this->assertSame(2, (int) $this->koneksiFresh()->table('service')
        ->where('idservice', $id)->get()->getRow()->status_service);
}
```

Gunakan helper `seedService()`, `seedBankPaid()`, `seedJurnalService()`, `jurnalService()`, `sesi()`, dan `koneksiFresh()` yang mengikuti `tests/ServicePengambilanDpTest.php`. Helper baru:

```php
private function seedJurnalService(int $id, int $jumlah): void
{
    $this->db->table('jurnal')->insertBatch([
        ['tanggal' => '2026-10-30', 'no_akun' => '1010101000', 'nama_akun' => 'Kas Besar',
         'debet' => $jumlah, 'kredit' => 0, 'id_referensi' => (string) $id, 'tabel_referensi' => 'service'],
        ['tanggal' => '2026-10-30', 'no_akun' => '4010201000', 'nama_akun' => 'Pendapatan Jasa',
         'debet' => 0, 'kredit' => $jumlah, 'id_referensi' => (string) $id, 'tabel_referensi' => 'service'],
    ]);
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/ServiceKembalikanKeProsesTest.php`
Expected: FAIL karena route `service/kembalikan_ke_proses` belum ada.

- [ ] **Step 3: Tambah route**

Di `app/Config/Routes.php`, setelah route `sudah_diambil/ajax`:

```php
$routes->post('service/kembalikan_ke_proses', 'Riwayat_Service::kembalikanKeProses', ['filter' => ['auth', 'csrf']]);
```

- [ ] **Step 4: Implementasikan method `kembalikanKeProses()`**

Di `app/Controllers/Riwayat_Service.php`, setelah `service_sudah_diambil_ajax()`:

```php
public function kembalikanKeProses()
{
    if ((int) session('ID_JABATAN') !== 1) {
        return redirect()->back()->with('gagal', 'Akses ditolak. Hanya Admin Root yang dapat mengembalikan service ke proses.');
    }

    $idservice = (int) $this->request->getPost('idservice');
    $alasan = trim((string) $this->request->getPost('alasan'));

    if ($idservice <= 0 || $alasan === '' || mb_strlen($alasan) > 120) {
        return redirect()->back()->with('gagal', 'Data koreksi tidak valid.');
    }

    $db = Database::connect();
    $db->transBegin();

    try {
        $sql = $db->table('service')->where('idservice', $idservice)->getCompiledSelect();
        if (in_array(strtolower((string) $db->DBDriver), ['mysqli', 'postgre'], true)) {
            $sql .= ' FOR UPDATE';
        }
        $service = $db->query($sql)->getRow();

        if ($service === null) {
            throw new \RuntimeException('Data service tidak ditemukan.');
        }
        if ((int) $service->status_service !== 4) {
            throw new \RuntimeException('Service ini bukan status sudah diambil.');
        }

        if (! $this->JurnalModel->hapusByReferensi('service', $idservice)) {
            throw new \RuntimeException('Gagal menghapus jurnal pengambilan.');
        }
        if (! $db->table('pembayaran_bank')
            ->where('tabel_referensi', 'service_baru')
            ->where('id_referensi', $idservice)
            ->delete()) {
            throw new \RuntimeException('Gagal menghapus pembayaran bank lama.');
        }
        if (! $this->ServiceModel->updateService($idservice, [
            'status_service' => 2,
            'tanggal_selesai' => null,
            'tanggal_bisa_diambil' => null,
            'bayar' => 0,
            'bayar_tunai' => 0,
            'bayar_bank' => null,
        ])) {
            throw new \RuntimeException('Gagal memperbarui status service.');
        }

        if (! $db->transStatus()) {
            throw new \RuntimeException('Transaksi gagal diselesaikan.');
        }
        $db->transCommit();

        log_message('info', 'Kembalikan service ke proses: ' . json_encode([
            'idservice' => $idservice,
            'no_service' => $service->no_service ?? null,
            'alasan' => $alasan,
            'oleh' => (int) session('ID_AKUN'),
            'sebelum' => [
                'status_service' => (int) $service->status_service,
                'tanggal_selesai' => $service->tanggal_selesai,
                'bayar' => (int) $service->bayar,
                'bayar_tunai' => (int) $service->bayar_tunai,
                'dp_bayar' => (int) $service->dp_bayar,
            ],
        ]));

        return redirect()->to(base_url('detail/riwayat_service/' . $idservice . '?tab=pembayaran'))
            ->with('sukses', 'Service dikembalikan ke Proses Service. Silakan input ulang pembayaran.');
    } catch (\Throwable $e) {
        $db->transRollback();
        log_message('error', 'kembalikanKeProses gagal service ' . $idservice . ': ' . $e->getMessage());
        return redirect()->back()->with('gagal', $e->getMessage());
    }
}
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/ServiceKembalikanKeProsesTest.php`
Expected: PASS untuk dua test.

- [ ] **Step 6: Cek sintaks dan diff**

Run: `php -l app/Controllers/Riwayat_Service.php && git diff --check`
Expected: `No syntax errors detected` dan tidak ada output `git diff --check`.

---

### Task 2: Guard role, status, garansi/refund, dan tutup kasir

**Files:**
- Modify: `app/Controllers/Riwayat_Service.php` (method `kembalikanKeProses`, setelah validasi status)
- Test: `tests/unit/ServiceKembalikanKeProsesTest.php`

**Interfaces:**
- Consumes: `GaransiClaimService::isClaimed(object $service): bool`, tabel `tutup_kasir` (`unit`, `tanggal`).
- Produces: penolakan dengan flash `gagal`; tidak ada perubahan data saat ditolak.

- [ ] **Step 1: Tambah test guard**

```php
public function testRoleSelainAdminRootDitolak(): void
{
    $this->withSession(['logged_in' => true, 'ID_AKUN' => 43, 'ID_UNIT' => 1, 'ID_JABATAN' => 41]);
    $id = $this->seedService(['status_service' => 4, 'tanggal_selesai' => '2026-10-30 10:00:00']);

    $this->post('service/kembalikan_ke_proses', [
        'idservice' => $id, 'alasan' => 'Koreksi',
        'csrf_test_name' => service('security')->getHash(),
    ])->assertStatus(302);

    $this->assertSame(4, (int) $this->koneksiFresh()->table('service')
        ->where('idservice', $id)->get()->getRow()->status_service);
}

public function testServiceBukanStatusEmpatDitolak(): void
{
    $this->sesi();
    $id = $this->seedService(['status_service' => 3]);

    $this->post('service/kembalikan_ke_proses', [
        'idservice' => $id, 'alasan' => 'Koreksi',
        'csrf_test_name' => service('security')->getHash(),
    ])->assertStatus(302);

    $this->assertSame(3, (int) $this->koneksiFresh()->table('service')
        ->where('idservice', $id)->get()->getRow()->status_service);
}

public function testKlaimGaransiAtauRefundDitolak(): void
{
    $this->sesi();
    $id = $this->seedService([
        'status_service' => 4,
        'tanggal_selesai' => '2026-10-30 10:00:00',
        'tanggal_claim_garansi' => '2026-10-30',
    ]);

    $this->post('service/kembalikan_ke_proses', [
        'idservice' => $id, 'alasan' => 'Koreksi',
        'csrf_test_name' => service('security')->getHash(),
    ])->assertStatus(302);

    $this->assertSame(4, (int) $this->koneksiFresh()->table('service')
        ->where('idservice', $id)->get()->getRow()->status_service);
}

public function testSentinelKlaimDianggapTidakDiklaim(): void
{
    $this->sesi();
    $id = $this->seedService([
        'status_service' => 4,
        'tanggal_selesai' => '2026-10-30 10:00:00',
        'tanggal_claim_garansi' => '0000-00-00',
    ]);

    $this->post('service/kembalikan_ke_proses', [
        'idservice' => $id, 'alasan' => 'Koreksi',
        'csrf_test_name' => service('security')->getHash(),
    ])->assertStatus(302);

    $this->assertSame(2, (int) $this->koneksiFresh()->table('service')
        ->where('idservice', $id)->get()->getRow()->status_service);
}

public function testTutupKasirTanggalSamaButuhKonfirmasi(): void
{
    $this->sesi();
    $id = $this->seedService([
        'status_service' => 4,
        'unit_idunit' => 1,
        'tanggal_selesai' => '2026-10-30 10:00:00',
    ]);
    $this->koneksiFresh()->table('tutup_kasir')->insert(['unit' => 1, 'tanggal' => '2026-10-30']);

    $this->post('service/kembalikan_ke_proses', [
        'idservice' => $id, 'alasan' => 'Koreksi',
        'csrf_test_name' => service('security')->getHash(),
    ])->assertStatus(302);

    $this->assertSame(4, (int) $this->koneksiFresh()->table('service')
        ->where('idservice', $id)->get()->getRow()->status_service);

    $this->post('service/kembalikan_ke_proses', [
        'idservice' => $id, 'alasan' => 'Koreksi',
        'konfirmasi_tutup_kasir' => '1',
        'csrf_test_name' => service('security')->getHash(),
    ])->assertStatus(302);

    $this->assertSame(2, (int) $this->koneksiFresh()->table('service')
        ->where('idservice', $id)->get()->getRow()->status_service);
}

public function testTutupKasirTanggalLainTidakButuhKonfirmasi(): void
{
    $this->sesi();
    $id = $this->seedService([
        'status_service' => 4,
        'unit_idunit' => 1,
        'tanggal_selesai' => '2026-10-30 10:00:00',
    ]);
    $this->koneksiFresh()->table('tutup_kasir')->insert(['unit' => 1, 'tanggal' => '2026-10-29']);

    $this->post('service/kembalikan_ke_proses', [
        'idservice' => $id, 'alasan' => 'Koreksi',
        'csrf_test_name' => service('security')->getHash(),
    ])->assertStatus(302);

    $this->assertSame(2, (int) $this->koneksiFresh()->table('service')
        ->where('idservice', $id)->get()->getRow()->status_service);
}
```

- [ ] **Step 2: Jalankan test, pastikan test guard gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/ServiceKembalikanKeProsesTest.php`
Expected: test role/status/klaim/tutup kasir FAIL (belum ada guard), happy path tetap PASS.

- [ ] **Step 3: Tambah guard setelah validasi status**

```php
if (! empty($service->refund_garansi_at)) {
    throw new \RuntimeException('Service sudah direfund; tidak dapat dikembalikan ke proses.');
}
if (GaransiClaimService::isClaimed($service)) {
    throw new \RuntimeException('Service sudah diklaim garansi; tidak dapat dikembalikan ke proses.');
}

$tanggalSelesai = substr((string) ($service->tanggal_selesai ?? ''), 0, 10);
$adaTutupKasir = false;
if ($tanggalSelesai !== '') {
    $adaTutupKasir = $db->table('tutup_kasir')
        ->where('unit', (int) $service->unit_idunit)
        ->where('DATE(tanggal)', $tanggalSelesai)
        ->countAllResults() > 0;
}
if ($adaTutupKasir && $this->request->getPost('konfirmasi_tutup_kasir') !== '1') {
    throw new \RuntimeException('Tutup kasir tanggal ini sudah tersimpan. Ulangi dengan konfirmasi.');
}
```

- [ ] **Step 4: Jalankan semua test file**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/ServiceKembalikanKeProsesTest.php`
Expected: semua test PASS.

- [ ] **Step 5: Cek sintaks dan diff**

Run: `php -l app/Controllers/Riwayat_Service.php && git diff --check`
Expected: `No syntax errors detected`, tidak ada output `git diff --check`.

---

### Task 3: Integrasi pengambilan ulang, DP, dan legacy tanpa jurnal

**Files:**
- Test: `tests/unit/ServiceKembalikanKeProsesTest.php`

**Interfaces:**
- Consumes: route `insert/service/savePembayaran`, `service/bisa_diambil`, `service/sudah_diambil`; template jurnal `pembayaran_service_tunai` dan `pembayaran_service_bank` (seed seperti `tests/ServicePengambilanDpTest.php`).
- Produces: bukti jurnal baru terbentuk sekali dengan nominal benar; tidak ada perubahan kode produksi kecuali test menemukan bug.

- [ ] **Step 1: Tambah test pengambilan ulang tanpa DP**

```php
public function testPengambilanUlangMembentukJurnalBaruDariPembayaranBenar(): void
{
    $this->sesi();
    $id = $this->seedService([
        'status_service' => 4,
        'tanggal_selesai' => '2026-10-30 10:00:00',
        'total_service' => 420000,
        'harus_dibayar' => 420000,
        'bayar' => 150000,
        'bayar_tunai' => 0,
    ]);
    $this->seedBankPaid($id, 150000);
    $this->seedJurnalService($id, 150000);

    $this->post('service/kembalikan_ke_proses', [
        'idservice' => $id, 'alasan' => 'Salah metode dan nominal',
        'csrf_test_name' => service('security')->getHash(),
    ]);

    $this->post('insert/service/savePembayaran', [
        'idservice_p' => $id,
        'total_harga_pembayaran' => '420000',
        'metode_bayar' => 'tunai',
        'bayar_pembayaran' => '420000',
    ]);

    $this->post('service/bisa_diambil', ['idservice' => $id]);
    $this->post('service/sudah_diambil', ['idservice' => $id])->assertStatus(302);

    $jurnal = $this->jurnalService($id);
    $this->assertSame(1, count(array_filter($jurnal, static fn ($r) => (string) $r['no_akun'] === '4010201000')), 'Pendapatan diakui satu kali.');
    $this->assertSame(420000, $this->jumlahDenganAkun($jurnal, '4010201000', 'kredit'));
}
```

- [ ] **Step 2: Tambah test DP tetap dan tidak dobel**

```php
public function testDpTetapDanTidakDobelSaatPengambilanUlang(): void
{
    $this->sesi();
    $id = $this->seedService([
        'status_service' => 4,
        'tanggal_selesai' => '2026-10-30 10:00:00',
        'total_service' => 420000,
        'harus_dibayar' => 420000,
        'dp_bayar' => 100000,
        'bayar' => 50000,
        'bayar_tunai' => 50000,
    ]);
    $this->seedDp($id, 100000);
    $this->seedJurnalService($id, 150000);

    $this->post('service/kembalikan_ke_proses', [
        'idservice' => $id, 'alasan' => 'Salah input',
        'csrf_test_name' => service('security')->getHash(),
    ]);

    $service = $this->koneksiFresh()->table('service')->where('idservice', $id)->get()->getRow();
    $this->assertSame(100000, (int) $service->dp_bayar, 'DP tidak berubah.');

    $this->post('insert/service/savePembayaran', [
        'idservice_p' => $id,
        'total_harga_pembayaran' => '420000',
        'metode_bayar' => 'tunai',
        'bayar_pembayaran' => '320000',
    ]);
    $this->post('service/bisa_diambil', ['idservice' => $id]);
    $this->post('service/sudah_diambil', ['idservice' => $id]);

    $jurnal = $this->jurnalService($id);
    $this->assertSame(100000, $this->jumlahDenganAkun($jurnal, '2010302000', 'debet'), 'PDM dilepas sekali.');
    $this->assertSame(420000, $this->jumlahDenganAkun($jurnal, '4010201000', 'kredit'));
}
```

- [ ] **Step 3: Tambah test legacy tanpa jurnal**

```php
public function testLegacyTanpaJurnalTidakGagal(): void
{
    $this->sesi();
    $id = $this->seedService([
        'status_service' => 4,
        'tanggal_selesai' => '2026-10-30 10:00:00',
        'total_service' => 100000,
        'harus_dibayar' => 100000,
        'bayar' => 100000,
        'bayar_tunai' => 100000,
    ]);

    $this->post('service/kembalikan_ke_proses', [
        'idservice' => $id, 'alasan' => 'Legacy',
        'csrf_test_name' => service('security')->getHash(),
    ])->assertStatus(302);

    $this->assertSame(2, (int) $this->koneksiFresh()->table('service')
        ->where('idservice', $id)->get()->getRow()->status_service);
}
```

- [ ] **Step 4: Jalankan semua test**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/ServiceKembalikanKeProsesTest.php`
Expected: semua PASS. Jika pengambilan ulang gagal, perbaiki penyebabnya di method revert (bukan mengubah `update_sudah_diambil`).

- [ ] **Step 5: Cek diff**

Run: `git diff --check`
Expected: tidak ada output.

---

### Task 4: Tombol UI di halaman Pengambilan Service

**Files:**
- Modify: `app/Controllers/Riwayat_Service.php` (`service_sudah_diambil`, sekitar baris 1423)
- Modify: `app/Views/riwayat/sudah_diambil.php` (script DataTables, kolom Action)
- Test: `tests/SudahDiambilViewTest.php` (baru)

**Interfaces:**
- Consumes: `service_sudah_diambil` mengirim variabel `is_admin_root` (bool); route `service/kembalikan_ke_proses`.
- Produces: tombol form per baris untuk Admin Root; `const IS_ADMIN_ROOT` dan `const CSRF_FIELD` di view.

- [ ] **Step 1: Tulis test render**

```php
<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

final class SudahDiambilViewTest extends CIUnitTestCase
{
    public function testAdminRootMelihatTombolKembalikan(): void
    {
        $html = view('riwayat/sudah_diambil', ['is_admin_root' => true]);

        $this->assertStringContainsString('const IS_ADMIN_ROOT = true;', $html);
        $this->assertStringContainsString('service/kembalikan_ke_proses', $html);
        $this->assertStringContainsString('Kembalikan ke Proses', $html);
        $this->assertStringContainsString('name="alasan"', $html);
    }

    public function testBukanAdminRootTidakMelihatTombol(): void
    {
        $html = view('riwayat/sudah_diambil', ['is_admin_root' => false]);

        $this->assertStringContainsString('const IS_ADMIN_ROOT = false;', $html);
        $this->assertStringContainsString('IS_ADMIN_ROOT ?', $html, 'Tombol tetap dibungkus kondisi runtime.');
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/SudahDiambilViewTest.php`
Expected: FAIL karena view belum punya flag/tombol.

- [ ] **Step 3: Kirim flag dari controller**

Di `service_sudah_diambil()`:

```php
'is_admin_root' => (int) session('ID_JABATAN') === 1,
```

- [ ] **Step 4: Tambah konstanta dan form di view**

Di `app/Views/riwayat/sudah_diambil.php`, setelah `let searchTimeout;`:

```js
const IS_ADMIN_ROOT = <?= $is_admin_root ? 'true' : 'false' ?>;
const CSRF_FIELD = <?= json_encode(csrf_field()) ?>;
```

Di kolom Action (render function), tambahkan sebelum `<a href=...cetak...>`:

```js
const tombolKembali = IS_ADMIN_ROOT ? `
    <form action="<?= base_url('service/kembalikan_ke_proses') ?>" method="post" class="form-kembalikan-service" style="display:inline-block;">
        ${CSRF_FIELD}
        <input type="hidden" name="idservice" value="${row.idservice}">
        <input type="hidden" name="konfirmasi_tutup_kasir" value="1">
        <input type="hidden" name="alasan" value="">
        <button type="submit" class="btn btn-sm btn-warning">Kembalikan ke Proses</button>
    </form>` : '';
return `${tombolKembali} <a href="...">...</a> ...`;
```

- [ ] **Step 5: Tambah konfirmasi dan alasan**

Tambahkan delegated handler di dalam `window.onload` (setelah handler `.btn-wa`):

```js
$(document).on('submit', '.form-kembalikan-service', function(e) {
    const alasan = window.prompt('Alasan mengembalikan service ke Proses Service:');
    if (!alasan || !alasan.trim()) {
        e.preventDefault();
        return;
    }
    $(this).find('input[name="alasan"]').val(alasan.trim().slice(0, 120));
    if (!window.confirm('Kembalikan service ini ke Proses Service? Pembayaran yang tercatat akan dihapus dan jurnal pengambilan dibatalkan. Jika Tutup Kasir tanggal ini sudah tersimpan, angka live Tutup Kasir dan omset/KPI teknisi tanggal itu ikut berubah (angka tersimpan dan struk tidak berubah).')) {
        e.preventDefault();
    }
});
```

- [ ] **Step 6: Jalankan test dan cek sintaks**

Run: `vendor/bin/phpunit -c phpunit.xml tests/SudahDiambilViewTest.php && php -l app/Views/riwayat/sudah_diambil.php && php -l app/Controllers/Riwayat_Service.php`
Expected: semua PASS dan `No syntax errors detected`.

- [ ] **Step 7: Cek diff**

Run: `git diff --check`
Expected: tidak ada output.

---

### Task 5: Perbarui PRD Modul 04

**Files:**
- Modify: `docs/prd/modul/PRD_MODUL_04_Service_Garansi.md`

**Interfaces:**
- Consumes: perilaku final dari Task 1-4.
- Produces: dokumentasi alur, guard, dan batasan.

- [ ] **Step 1: Tambah alur di bagian 4.4**

Tambahkan satu blok:

```markdown
- **Kembalikan ke Proses Service** (Admin Root, `ID_JABATAN = 1`): `Riwayat_Service::kembalikanKeProses` (POST `service/kembalikan_ke_proses`, filter `['auth','csrf']`).
  - Syarat: `status_service = 4`, tidak sedang diklaim garansi (`GaransiClaimService::isClaimed`), tidak direfund.
  - Dalam satu transaksi: hapus jurnal `tabel_referensi='service'`, hapus `pembayaran_bank` (`service_baru`), set `status_service=2`, `tanggal_selesai=NULL`, `tanggal_bisa_diambil=NULL`, `bayar=0`, `bayar_tunai=0`, `bayar_bank=NULL`.
  - DP (`dp_bayar`, ledger `service_dp`, jurnal DP) tidak berubah. `total_service`/`harus_dibayar` tidak berubah.
  - Bila Tutup Kasir unit+tanggal `tanggal_selesai` sudah ada, wajib `konfirmasi_tutup_kasir=1`. Angka tutup kasir tersimpan/struk tidak diubah, tetapi halaman live dan omset/KPI teknisi bisa berubah karena dihitung ulang dari `service`.
  - Alasan wajib (maks 120 karakter) dicatat di log aplikasi.
```

- [ ] **Step 2: Tambah baris verifikasi**

Di bagian bukti/verifikasi:

```markdown
- ✅ Kembalikan service sudah diambil ke Proses Service: Admin Root saja, jurnal pengambilan dihapus, pembayaran lama dihapus, DP tetap, guard tutup kasir. Test `tests/unit/ServiceKembalikanKeProsesTest.php`.
```

- [ ] **Step 3: Cek diff**

Run: `git diff --check`
Expected: tidak ada output.

---

## Self-Review

- **Spec coverage:** endpoint, transaksi, guard role/status/garansi/refund/tutup kasir, UI, audit log, test, dan PRD sudah tercakup di Task 1-5.
- **Step scan:** setiap step punya aksi atau hasil yang bisa dicek; tidak ada langkah "handle edge cases" tanpa detail.
- **Type consistency:** `kembalikanKeProses(): RedirectResponse`, `is_admin_root: bool`, `hapusByReferensi(string, $id): bool`, `updateService($id, array): bool` konsisten.
- **Review Focus:** double submit (Task 1), tutup kasir tanggal lain (Task 2), sentinel klaim (Task 2), DP (Task 3), legacy tanpa jurnal (Task 3).
- **Proportion:** plan lebih pendek dari kode yang dihasilkan; body hanya untuk transaksi inti dan potongan view yang tidak bisa ditebak dari signature.
