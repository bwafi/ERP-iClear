# Roster Absensi & Absen Mobile (Fase 1) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyediakan roster absensi bulanan dan halaman `/absen` mobile berbasis GPS+foto, tanpa menyentuh KPI/payroll.

**Architecture:** Roster disimpan per tanggal di `presensi_roster`. Punch mobile menulis ke `presensi` yang sudah ada (`sesi`, `sumber` baru). Business rules (scope unit, urutan sesi PS, radius, foto) ditempatkan di service kecil yang bisa diuji tanpa HTTP; controller hanya menerjemahkan request/response. Halaman roster memakai layout `template`; halaman `/absen` sengaja ringan dan tidak mewarisi `BaseController`.

**Tech Stack:** CodeIgniter 4.4.8, PHP 7.4-compatible, MySQLi, PHPUnit 9, Bootstrap 5 (roster), vanilla JS + canvas (mobile).

**Spec:** `docs/superpowers/specs/2026-11-02-roster-absen-mobile-design.md`

## Global Constraints

- PHP harus kompatibel 7.4: tidak ada `readonly`, `enum`, constructor promotion, named arguments, atau `match`.
- Test hanya ke database test (`erp_test`, prefix `db_` dari `phpunit.xml`). Jangan pernah mengarahkan test ke `erp_local`/produksi.
- Role diambil dari `session('ID_JABATAN')`. Jangan memakai `session('ROLES_JABATAN')` — key itu tidak ada.
- Filter `auth` hanya memeriksa `logged_in`; setiap aturan role/unit harus dicek di server.
- Jangan mengubah KPI, payroll, `kpi_evaluations`, `kpi_attendance_detail`, atau `kpi_attendance_off`.
- Jangan mengubah perilaku `/absensi`, `/riwayat_presensi`, `/approval_presensi`, dan absen manual legacy.
- Jam mulai shift hanya dari `AttendanceScoreCalculator::SCHEDULED_TIMES` (`PAGI 08:45`, `SIANG 12:45`, `PS 08:45/16:55`).
- Foto: JPEG/PNG, maksimal 2 MB, disimpan di `writable/uploads/presensi/YYYYMM/`, disajikan lewat route ber-`auth`.
- Timezone `Asia/Jakarta`; tanggal memakai format `Y-m-d`, bulan `Y-m`.
- **Commit policy:** jangan `git commit` kecuali user memerintahkan eksplisit (AGENTS.md). Setiap task berhenti setelah test lulus; tidak ada langkah commit di plan ini.

## Review Focus

1. **Punch ganda / double tap:** dua request untuk `(akun, tanggal, sesi)` yang sama tidak boleh menghasilkan dua baris. Diuji di Task 7.
2. **PS SORE tanpa PAGI:** sesi sore harus ditolak bila baris sesi pagi belum ada. Diuji di Task 7.
3. **Di luar radius / unit tanpa koordinat:** punch ditolak dengan pesan jelas dan tidak menulis baris. Diuji di Task 7 dan Task 9.
4. **Foto tidak valid / kebesaran / gagal dipindah:** tidak ada baris `presensi` dan tidak ada file yatim. Diuji di Task 6 dan Task 7.
5. **Guard unit/tanggal lampau ditembus via POST:** role 35/47 tidak bisa menulis unit lain atau tanggal lampau walau field form diubah. Diuji di Task 5.

---

### Task 1: Config + schema roster/presensi

**Files:**
- Create: `app/Config/Presensi.php`
- Create: `app/Database/Migrations/2026-11-02-000100_CreatePresensiRoster.php`
- Create: `app/Database/Migrations/2026-11-02-000200_AddSesiSumberToPresensi.php`
- Test: `tests/PresensiSchemaMigrationTest.php`

**Interfaces:**
- Produces: `Config\Presensi` dengan properti `string $goLiveDate`, `string $zonaWaktu`, `string $fotoDir`, `int $maxFotoBytes`, `int $toleransiMenit`, `array $shiftPulang`, `array $rosterSemuaUnit`, `array $rosterUnitSendiri`, `int $koreksiLampau`.
- Produces: tabel `presensi_roster` dan kolom `presensi.sesi`, `presensi.sumber`, index `idx_presensi_akun_masuk`.

- [ ] **Step 1: Tulis test migrasi yang gagal**

Buat `tests/PresensiSchemaMigrationTest.php`:

```php
<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class PresensiSchemaMigrationTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->resetSkema();
        $this->migrasi();
    }

    protected function tearDown(): void
    {
        $this->db->query('DROP TABLE IF EXISTS presensi_roster');
        $this->db->query('DROP TABLE IF EXISTS presensi');
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function resetSkema(): void
    {
        $this->db->query('DROP TABLE IF EXISTS presensi_roster');
        $this->db->query('DROP TABLE IF EXISTS presensi');
        $this->db->query('CREATE TABLE presensi (
            idpresensi INT PRIMARY KEY AUTO_INCREMENT,
            akun_idakun INT NOT NULL,
            unit_idunit INT NULL,
            waktu_masuk DATETIME NULL
        )');
    }

    private function migrasi(): void
    {
        $dir = APPPATH . 'Database/Migrations/';
        require_once $dir . '2026-11-02-000100_CreatePresensiRoster.php';
        require_once $dir . '2026-11-02-000200_AddSesiSumberToPresensi.php';
        (new \App\Database\Migrations\CreatePresensiRoster())->up();
        (new \App\Database\Migrations\AddSesiSumberToPresensi())->up();
    }

    public function testRosterDanKolomPresensiTerbentuk(): void
    {
        $this->assertTrue($this->db->tableExists('presensi_roster'));
        $this->assertTrue($this->db->fieldExists('sesi', 'presensi'));
        $this->assertTrue($this->db->fieldExists('sumber', 'presensi'));
        $this->assertArrayHasKey('idx_presensi_akun_masuk', $this->db->getIndexData('presensi'));
    }

    public function testDijalankanDuaKaliTetapIdempoten(): void
    {
        $this->db->table('presensi_roster')->insert([
            'akun_idakun' => 10,
            'tanggal'     => '2026-11-02',
            'shift'       => 'PAGI',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->migrasi();
        $this->migrasi();

        $this->assertSame(1, (int) $this->db->table('presensi_roster')->countAllResults());
        $this->assertTrue($this->db->fieldExists('sesi', 'presensi'));
    }

    public function testTanpaTabelPresensiTidakMembuatTabelBaru(): void
    {
        $this->db->query('DROP TABLE presensi');
        $this->migrasi();

        $this->assertFalse($this->db->tableExists('presensi'));
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/PresensiSchemaMigrationTest.php`
Expected: FAIL karena file migration/class belum ada.

- [ ] **Step 3: Buat `Config\Presensi`**

```php
<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Presensi extends BaseConfig
{
    public string $goLiveDate = '2026-11-01';
    public string $zonaWaktu = 'Asia/Jakarta';
    public string $fotoDir = WRITEPATH . 'uploads/presensi';
    public int $maxFotoBytes = 2097152;
    public int $toleransiMenit = 14;

    /** Jam pulang per shift; sesi PS punya dua nilai. */
    public array $shiftPulang = [
        'PAGI'  => '17:00',
        'SIANG' => '21:00',
        'PS'    => ['PAGI' => '13:00', 'SORE' => '21:00'],
    ];

    public array $rosterSemuaUnit = [0, 1, 34];
    public array $rosterUnitSendiri = [35, 47];
    public int $koreksiLampau = 1;
}
```

- [ ] **Step 4: Buat migrasi `CreatePresensiRoster`**

Gunakan `$this->db->tableExists('presensi_roster')` untuk guard, lalu `forge` dengan field: `id` INT UNSIGNED AI, `akun_idakun` INT, `tanggal` DATE, `shift` VARCHAR(10), `keterangan` TEXT NULL, `created_by`/`updated_by` INT NULL, `created_at`/`updated_at` DATETIME NULL. Tambah primary key `id`, unique key `['akun_idakun', 'tanggal']`, key `tanggal`. `down()` drop table dengan `true`.

- [ ] **Step 5: Buat migrasi `AddSesiSumberToPresensi`**

`up()`: jika `! $this->db->tableExists('presensi')` return. Tambah `sesi` VARCHAR(10) NULL dan `sumber` VARCHAR(20) NULL hanya jika `! fieldExists`. Tambah index `idx_presensi_akun_masuk` hanya jika belum ada (`$this->db->getIndexData('presensi')`), lewat `$this->forge->addKey(['akun_idakun', 'waktu_masuk'], false, false, 'idx_presensi_akun_masuk')` lalu `$this->forge->processIndexes('presensi')`. `down()`: drop index lalu drop kolom jika ada.

- [ ] **Step 6: Jalankan test sampai lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/PresensiSchemaMigrationTest.php`
Expected: PASS (3 tests).

---

### Task 2: Scope service (role/unit/foto)

**Files:**
- Create: `app/Services/Presensi/PresensiScopeService.php`
- Test: `tests/unit/PresensiScopeServiceTest.php`

**Interfaces:**
- Produces:
  - `bolehKelolaUnit(int $jabatan, int $unitSesi, int $unitTarget): bool`
  - `bolehKoreksiLampau(int $jabatan): bool`
  - `bolehLihatFoto(int $jabatan, int $unitSesi, int $akunId, int $akunPemilik, int $unitPemilik): bool`

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\unit;

use App\Services\Presensi\PresensiScopeService;
use CodeIgniter\Test\CIUnitTestCase;

class PresensiScopeServiceTest extends CIUnitTestCase
{
    private PresensiScopeService $scope;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scope = new PresensiScopeService();
    }

    public function testRoleLintasBolehSemuaUnit(): void
    {
        foreach ([0, 1, 34] as $jabatan) {
            $this->assertTrue($this->scope->bolehKelolaUnit($jabatan, 1, 2));
        }
    }

    public function testAdminKasirHanyaUnitSendiri(): void
    {
        foreach ([35, 47] as $jabatan) {
            $this->assertTrue($this->scope->bolehKelolaUnit($jabatan, 2, 2));
            $this->assertFalse($this->scope->bolehKelolaUnit($jabatan, 2, 1));
        }
    }

    public function testRoleLainTidakBolehKelola(): void
    {
        $this->assertFalse($this->scope->bolehKelolaUnit(36, 2, 2));
    }

    public function testHanyaAdminRootBolehKoreksiLampau(): void
    {
        $this->assertTrue($this->scope->bolehKoreksiLampau(1));
        $this->assertFalse($this->scope->bolehKoreksiLampau(0));
        $this->assertFalse($this->scope->bolehKoreksiLampau(35));
    }

    public function testFotoPemilikDanScope(): void
    {
        $this->assertTrue($this->scope->bolehLihatFoto(36, 1, 10, 10, 1));
        $this->assertTrue($this->scope->bolehLihatFoto(34, 1, 99, 10, 5));
        $this->assertTrue($this->scope->bolehLihatFoto(35, 2, 99, 10, 2));
        $this->assertFalse($this->scope->bolehLihatFoto(35, 2, 99, 10, 1));
        $this->assertFalse($this->scope->bolehLihatFoto(36, 2, 99, 10, 2));
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/PresensiScopeServiceTest.php`
Expected: FAIL class not found.

- [ ] **Step 3: Implementasikan service**

Baca `config('Presensi')` di dalam method (jangan di constructor) supaya test bisa mengganti config. Aturan:
- `bolehKelolaUnit`: `in_array($jabatan, $config->rosterSemuaUnit, true)` → true; `in_array($jabatan, $config->rosterUnitSendiri, true) && $unitTarget === $unitSesi` → true; selain itu false.
- `bolehKoreksiLampau`: `$jabatan === $config->koreksiLampau`.
- `bolehLihatFoto`: pemilik → true; role lintas → true; role unit sendiri dan `$unitPemilik === $unitSesi` → true; selain itu false.

- [ ] **Step 4: Jalankan test sampai lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/PresensiScopeServiceTest.php`
Expected: PASS (5 tests).

---

### Task 3: Model roster + RosterService

**Files:**
- Create: `app/Models/ModelPresensiRoster.php`
- Create: `app/Services/Presensi/RosterService.php`
- Test: `tests/unit/RosterServiceTest.php`

**Interfaces:**
- Produces `ModelPresensiRoster`:
  - `getByAkunTanggal(int $akunId, string $tanggal): ?object`
  - `getRentang(int $akunId, string $dari, string $sampai): array` — keyed by `tanggal`
  - `ada(int $akunId, string $tanggal): bool`
  - `simpan(int $akunId, string $tanggal, string $shift, ?string $keterangan, int $oleh): bool`
- Produces `RosterService`:
  - `terapkanBulan(int $akunId, string $bulan, array $pola, int $oleh): int` — jumlah baris baru
  - `simpanTanggal(int $akunId, string $tanggal, string $shift, ?string $keterangan, int $oleh): bool`

- [ ] **Step 1: Tulis test yang gagal**

Test membuat tabel `presensi_roster` manual di `setUp` (kolom sesuai Task 1), lalu:

```php
public function testTerapkanHanyaMengisiTanggalKosong(): void
{
    $this->db->table('presensi_roster')->insert([
        'akun_idakun' => 10, 'tanggal' => '2026-11-02', 'shift' => 'OFF',
        'created_by' => 1, 'created_at' => date('Y-m-d H:i:s'),
    ]);
    $pola = [1 => 'PAGI', 2 => 'SIANG', 3 => 'PS', 4 => 'PAGI', 5 => 'SIANG', 6 => 'OFF', 7 => 'OFF'];

    $jumlah = (new RosterService())->terapkanBulan(10, '2026-11', $pola, 1);

    $this->assertSame(29, $jumlah); // November 30 hari, 1 sudah ada
    $this->assertSame('OFF', $this->db->table('presensi_roster')
        ->where('akun_idakun', 10)->where('tanggal', '2026-11-02')->get()->getRow()->shift);
    // 3 Nov 2026 = Selasa -> pola[2] = SIANG
    $this->assertSame('SIANG', $this->db->table('presensi_roster')
        ->where('akun_idakun', 10)->where('tanggal', '2026-11-03')->get()->getRow()->shift);
}

public function testSimpanTanggalUpsert(): void
{
    $service = new RosterService();
    $service->simpanTanggal(10, '2026-11-05', 'PS', null, 1);
    $service->simpanTanggal(10, '2026-11-05', 'IZIN', 'sakit', 1);

    $row = $this->db->table('presensi_roster')
        ->where('akun_idakun', 10)->where('tanggal', '2026-11-05')->get()->getRow();
    $this->assertSame('IZIN', $row->shift);
    $this->assertSame('sakit', $row->keterangan);
    $this->assertSame(1, (int) $this->db->table('presensi_roster')->countAllResults());
}

public function testIzinWajibKeterangan(): void
{
    $this->expectException(\InvalidArgumentException::class);
    (new RosterService())->simpanTanggal(10, '2026-11-05', 'IZIN', '  ', 1);
}

public function testShiftTidakDikenalDitolak(): void
{
    $this->expectException(\InvalidArgumentException::class);
    (new RosterService())->terapkanBulan(10, '2026-11', [1 => 'LIBUR'], 1);
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/RosterServiceTest.php`
Expected: FAIL class not found.

- [ ] **Step 3: Implementasikan model**

`ModelPresensiRoster` dengan `$table = 'presensi_roster'`, `$primaryKey = 'id'`, `$returnType = 'object'`, `$allowedFields` sesuai Task 1. `getRentang` mengembalikan `[$row->tanggal => $row]`. `simpan` mengisi `created_by`/`updated_by` dan timestamp; update memakai primary key baris yang ada.

- [ ] **Step 4: Implementasikan `RosterService`**

- `terapkanBulan`: validasi `$bulan` cocok `/^\d{4}-\d{2}$/`; validasi setiap `$pola[1..7]` ada dan nilainya di `['PAGI','SIANG','PS','OFF']`; loop `1..date('t', strtotime($bulan . '-01'))`; hitung `date('N', strtotime($tanggal))`; jika `! $model->ada()` → `$model->simpan(..., $pola[$hari], null, $oleh)`. Bungkus loop dengan `db_connect()->transStart()` / `transComplete()` agar tidak ada insert setengah jalan. Kembalikan jumlah insert.
- `simpanTanggal`: validasi shift di `['PAGI','SIANG','PS','OFF','IZIN']`; jika `IZIN` dan `trim((string)$keterangan) === ''` lempar `InvalidArgumentException('Keterangan izin wajib diisi.')`; panggil `$model->simpan`.

- [ ] **Step 5: Jalankan test sampai lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/RosterServiceTest.php`
Expected: PASS (4 tests).

---

### Task 4: Halaman roster (GET `/jadwal_masuk`)

**Files:**
- Modify: `app/Controllers/JadwalMasuk.php` — ganti seluruh isi
- Modify: `app/Views/absensi/jadwal_masuk.php` — ganti seluruh isi
- Test: `tests/RosterPageTest.php`

**Interfaces:**
- Consumes: `ModelAuth::getAkun(?int $unitId)`, `ModelUnit::getUnit()`, `ModelPresensiRoster::getRentang()`, `PresensiScopeService`.
- Produces: view `absensi/jadwal_masuk` dengan variabel `$bulan`, `$unit`, `$pegawai`, `$akunId`, `$roster`, `$bolehSemuaUnit`, `$bolehKoreksiLampau`.

- [ ] **Step 1: Tulis test halaman yang gagal**

Buat schema minimal di `setUp`, mengikuti pola `tests/KasKeluarUnitFilterTest.php` (prefix dikosongkan, drop table, create table):
`unit(idunit, NAMA_UNIT, LOGO, LATITUDE, LONGTITUDE, RADIUS)`, `akun(ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, STATUS_PEGAWAI, ROLES)`, `jabatan(ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN)`, `presensi_roster`.

`template.php` juga query `menu` (ambil `manualbook` berdasarkan `url`) dan BaseController memuat notifikasi `stok_barang` + `service`/`pelanggan`, jadi salin persis skema `menu`, `stok_barang`, `service`, dan `pelanggan` dari `tests/KasKeluarUnitFilterTest.php` (termasuk kolom `menu.manualbook`). Tanpa `manualbook`, render view gagal.

Seed: unit 1 "Unit Satu", unit 2 "Unit Dua"; akun 100 unit 1 "Pegawai Satu", akun 200 unit 2 "Pegawai Dua"; jabatan 1/34/35.

Helper sesi (wajib `logged_in => true`, kalau tidak `AuthFilter` redirect ke login):

```php
private function sesi(int $akun, int $unit, int $jabatan): void
{
    $this->withSession([
        'logged_in'  => true,
        'ID_AKUN'    => $akun,
        'ID_UNIT'    => $unit,
        'ID_JABATAN' => $jabatan,
        'NAMA'       => 'Tester',
    ]);
}
```

```php
public function testAdminKasirHanyaMelihatPegawaiUnitSendiri(): void
{
    $this->sesi(200, 2, 35);
    $result = $this->get('jadwal_masuk?bulan=2026-11&unit=1');

    $result->assertStatus(200);
    $result->assertSee('Pegawai Dua');
    $result->assertDontSee('Pegawai Satu');
    $result->assertSee('pola[1]');
    $result->assertSee('Buka Absen Mobile');
}

public function testRoleLintasBisaMemilihUnit(): void
{
    $this->sesi(1, 1, 34);
    $result = $this->get('jadwal_masuk?bulan=2026-11&unit=1');

    $result->assertSee('Pegawai Satu');
    $result->assertSee('name="unit"');
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/RosterPageTest.php`
Expected: FAIL karena controller/view lama tidak memuat form roster.

- [ ] **Step 3: Tulis ulang `JadwalMasuk::index`**

```php
public function index()
{
    $jabatan    = (int) session('ID_JABATAN');
    $unitSesi   = (int) session('ID_UNIT');
    $scope      = new \App\Services\Presensi\PresensiScopeService();
    $bolehSemua = in_array($jabatan, config('Presensi')->rosterSemuaUnit, true);

    $bulan = (string) ($this->request->getGet('bulan') ?: date('Y-m'));
    if (! preg_match('/^\d{4}-\d{2}$/', $bulan)) {
        $bulan = date('Y-m');
    }

    $unitFilter = $bolehSemua
        ? (int) ($this->request->getGet('unit') ?: 0)
        : $unitSesi;
    if ($unitFilter === 0) {
        $pegawai = $this->AuthModel->getAkun();
    } else {
        $pegawai = $this->AuthModel->getAkun($unitFilter);
    }

    $akunId = (int) ($this->request->getGet('akun') ?: 0);
    $valid  = array_map(static fn ($row) => (int) $row->ID_AKUN, $pegawai);
    if ($akunId === 0 || ! in_array($akunId, $valid, true)) {
        $akunId = $valid[0] ?? 0;
    }

    $roster = $akunId === 0 ? [] : (new \App\Models\ModelPresensiRoster())
        ->getRentang($akunId, $bulan . '-01', date('Y-m-t', strtotime($bulan . '-01')));

    return view('template', [
        'body'              => 'absensi/jadwal_masuk',
        'bulan'             => $bulan,
        'unit'              => $this->UnitModel->getUnit(),
        'pegawai'           => $pegawai,
        'akunId'            => $akunId,
        'roster'            => $roster,
        'bolehSemuaUnit'    => $bolehSemua,
        'bolehKoreksiLampau'=> $scope->bolehKoreksiLampau($jabatan),
    ]);
}
```

Biarkan `insert_jadwal`, `update_jadwal`, `delete_jadwal` lama apa adanya pada Task 4 (route-nya masih ada sampai Task 5). Task 5 yang menggantinya dengan `terapkan()` dan `simpan()` sekaligus mengganti route.

- [ ] **Step 4: Tulis view roster**

View memakai layout `template` dan berisi:
1. Judul "Roster Absensi" + tautan `base_url('absen')` teks "Buka Absen Mobile".
2. Form GET: `bulan` (`type="month"`), select `unit` hanya jika `$bolehSemuaUnit`, select `akun` berisi `$pegawai` (`ID_AKUN`, `NAMA_AKUN`, `NAMA_UNIT`). Tombol "Tampilkan".
3. Jika `$pegawai` kosong: pesan "Belum ada pegawai aktif."
4. Jika ada: form POST `jadwal_masuk/terapkan` dengan hidden `akun_idakun`, `bulan`, dan 7 select `pola[1]`..`pola[7]` (Senin–Minggu) berisi `PAGI/SIANG/PS/OFF`; tombol "Terapkan ke bulan".
5. Form POST `jadwal_masuk/simpan` dengan hidden `akun_idakun`, `bulan`; tabel tanggal 1..akhir bulan:
   - kolom hari (`date('l', ...)`), tanggal.
   - Jika tanggal lampau dan `! $bolehKoreksiLampau`: tampilkan shift sebagai teks.
   - Selain itu: select `name="shift[Y-m-d]"` berisi `PAGI/SIANG/PS/OFF/IZIN` dengan nilai dari `$roster[$tanggal]->shift ?? ''`.
   - Input `name="keterangan[Y-m-d]"` dengan nilai `$roster[$tanggal]->keterangan ?? ''`.
   - Tombol "Simpan Roster" di bawah tabel.

- [ ] **Step 5: Jalankan test sampai lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/RosterPageTest.php`
Expected: PASS (2 tests).

---

### Task 5: Simpan roster + guard server-side

**Files:**
- Modify: `app/Controllers/JadwalMasuk.php` — tambah `terapkan()`, `simpan()`
- Modify: `app/Config/Routes.php` — ganti blok `jadwal masuk`
- Test: `tests/RosterSimpanTest.php`

**Interfaces:**
- Consumes: `RosterService`, `PresensiScopeService`, `ModelAuth::getById()`.
- Produces routes: `POST jadwal_masuk/terapkan`, `POST jadwal_masuk/simpan` (filter `auth`).

- [ ] **Step 1: Tulis test yang gagal**

Gunakan schema dan seed yang sama dengan Task 4.

```php
public function testAdminKasirTidakBisaTerapkanUntukUnitLain(): void
{
    $this->sesi(200, 2, 35);
    $pola = [1 => 'PAGI', 2 => 'PAGI', 3 => 'PAGI', 4 => 'PAGI', 5 => 'PAGI', 6 => 'OFF', 7 => 'OFF'];

    $this->post('jadwal_masuk/terapkan', [
        'akun_idakun' => 100, // unit 1
        'bulan'       => '2026-11',
        'pola'        => $pola,
    ])->assertRedirect();

    $this->assertSame(0, (int) $this->db->table('presensi_roster')->countAllResults());
    $this->assertStringContainsString('unit', (string) session('gagal'));
}

public function testAdminKasirTidakBisaUbahTanggalLampau(): void
{
    $this->sesi(200, 2, 35);
    $this->post('jadwal_masuk/simpan', [
        'akun_idakun' => 200,
        'bulan'       => '2020-01',
        'shift'       => ['2020-01-01' => 'PAGI'],
        'keterangan'  => ['2020-01-01' => ''],
    ])->assertRedirect();

    $this->assertSame(0, (int) $this->db->table('presensi_roster')->countAllResults());
}

public function testAdminRootBolehUbahTanggalLampau(): void
{
    $this->sesi(1, 1, 1);
    $this->post('jadwal_masuk/simpan', [
        'akun_idakun' => 100,
        'bulan'       => '2020-01',
        'shift'       => ['2020-01-01' => 'PS'],
        'keterangan'  => ['2020-01-01' => ''],
    ])->assertRedirect();

    $row = $this->db->table('presensi_roster')->where('akun_idakun', 100)->get()->getRow();
    $this->assertSame('PS', $row->shift);
}

public function testIzinTanpaKeteranganDitolak(): void
{
    $this->sesi(1, 1, 1);
    $this->post('jadwal_masuk/simpan', [
        'akun_idakun' => 100,
        'bulan'       => '2099-01',
        'shift'       => ['2099-01-01' => 'IZIN'],
        'keterangan'  => ['2099-01-01' => ''],
    ])->assertRedirect();

    $this->assertSame(0, (int) $this->db->table('presensi_roster')->countAllResults());
    $this->assertStringContainsString('Keterangan', (string) session('gagal'));
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/RosterSimpanTest.php`
Expected: FAIL karena route/method belum ada.

- [ ] **Step 3: Implementasikan `terapkan()`**

```php
public function terapkan()
{
    $akun = $this->AuthModel->getById((int) $this->request->getPost('akun_idakun'));
    if (! $akun) {
        return redirect()->back()->with('gagal', 'Pegawai tidak ditemukan.');
    }

    $jabatan  = (int) session('ID_JABATAN');
    $unitSesi = (int) session('ID_UNIT');
    $unitAkun = (int) $akun->ID_UNIT;
    $scope    = new \App\Services\Presensi\PresensiScopeService();

    if (! $scope->bolehKelolaUnit($jabatan, $unitSesi, $unitAkun)) {
        return redirect()->back()->with('gagal', 'Anda tidak berhak mengelola roster unit ini.');
    }

    $bulan = (string) $this->request->getPost('bulan');
    if (! $scope->bolehKoreksiLampau($jabatan) && $bulan < date('Y-m')) {
        return redirect()->back()->with('gagal', 'Roster bulan lampau hanya bisa diubah Admin Root.');
    }

    try {
        $jumlah = (new \App\Services\Presensi\RosterService())->terapkanBulan(
            (int) $akun->ID_AKUN,
            $bulan,
            (array) $this->request->getPost('pola'),
            (int) session('ID_AKUN')
        );
    } catch (\InvalidArgumentException $e) {
        return redirect()->back()->with('gagal', $e->getMessage());
    }

    return redirect()->back()->with('sukses', $jumlah . ' tanggal roster ditambahkan.');
}
```

- [ ] **Step 4: Implementasikan `simpan()`**

```php
public function simpan()
{
    $akun = $this->AuthModel->getById((int) $this->request->getPost('akun_idakun'));
    if (! $akun) {
        return redirect()->back()->with('gagal', 'Pegawai tidak ditemukan.');
    }

    $jabatan  = (int) session('ID_JABATAN');
    $unitSesi = (int) session('ID_UNIT');
    $scope    = new \App\Services\Presensi\PresensiScopeService();

    if (! $scope->bolehKelolaUnit($jabatan, $unitSesi, (int) $akun->ID_UNIT)) {
        return redirect()->back()->with('gagal', 'Anda tidak berhak mengelola roster unit ini.');
    }

    $shift      = (array) $this->request->getPost('shift');
    $keterangan = (array) $this->request->getPost('keterangan');
    $service    = new \App\Services\Presensi\RosterService();
    $hariIni    = date('Y-m-d');

    // Validasi semua baris dulu supaya tidak ada simpan setengah jalan.
    foreach ($shift as $tanggal => $nilai) {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $tanggal)) {
            return redirect()->back()->with('gagal', 'Tanggal roster tidak valid.');
        }
        if ($tanggal < $hariIni && ! $scope->bolehKoreksiLampau($jabatan)) {
            return redirect()->back()->with('gagal', 'Roster tanggal lampau hanya bisa diubah Admin Root.');
        }
        if (! in_array((string) $nilai, ['PAGI', 'SIANG', 'PS', 'OFF', 'IZIN'], true)) {
            return redirect()->back()->with('gagal', 'Shift roster tidak valid.');
        }
        if ((string) $nilai === 'IZIN' && trim((string) ($keterangan[$tanggal] ?? '')) === '') {
            return redirect()->back()->with('gagal', 'Keterangan izin wajib diisi.');
        }
    }

    $db = db_connect();
    $db->transStart();
    foreach ($shift as $tanggal => $nilai) {
        $service->simpanTanggal(
            (int) $akun->ID_AKUN,
            $tanggal,
            (string) $nilai,
            (string) ($keterangan[$tanggal] ?? ''),
            (int) session('ID_AKUN')
        );
    }
    $db->transComplete();

    if (! $db->transStatus()) {
        return redirect()->back()->with('gagal', 'Roster gagal disimpan.');
    }

    return redirect()->back()->with('sukses', 'Roster berhasil disimpan.');
}
```

- [ ] **Step 5: Ganti route jadwal**

Di `app/Config/Routes.php`, ganti baris 365–369 dengan:

```php
//roster absensi
$routes->get('jadwal_masuk', 'JadwalMasuk::index', ['filter' => 'auth']);
$routes->post('jadwal_masuk/terapkan', 'JadwalMasuk::terapkan', ['filter' => 'auth']);
$routes->post('jadwal_masuk/simpan', 'JadwalMasuk::simpan', ['filter' => 'auth']);
```

- [ ] **Step 6: Jalankan test sampai lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/RosterSimpanTest.php`
Expected: PASS (4 tests).

---

### Task 6: FotoPresensiService

**Files:**
- Create: `app/Services/Presensi/PresensiException.php`
- Create: `app/Services/Presensi/FotoPresensiService.php`
- Create: `tests/_support/FakeUploadedFile.php`
- Test: `tests/unit/FotoPresensiServiceTest.php`

**Interfaces:**
- Produces `PresensiException extends \RuntimeException` dengan `__construct(string $message, int $status = 422)` dan `public int $status`.
- Produces `FotoPresensiService`:
  - `simpan(\CodeIgniter\HTTP\Files\UploadedFile $file, string $jenis, int $akunId, ?string $dir = null): string` — path relatif `YYYYMM/nama.jpg`
  - `hapus(string $relatif, ?string $dir = null): void`

- [ ] **Step 1: Tulis fake upload + test yang gagal**

`FeatureTestTrait` tidak bisa memalsukan `is_uploaded_file()`, jadi kedua task foto memakai satu fake bersama di `tests/_support/`:

```php
<?php

namespace Tests\Support;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * UploadedFile palsu untuk test service: isValid()/getMimeType() bisa
 * ditentukan, dan move() menyalin file biasa (tanpa is_uploaded_file()).
 */
class FakeUploadedFile extends UploadedFile
{
    private string $mime;
    private bool $valid;
    private int $ukuran;
    public bool $dipindah = false;

    public function __construct(string $path, string $mime = 'image/jpeg', bool $valid = true, int $ukuran = 1024)
    {
        parent::__construct($path, basename($path), $mime, $ukuran, UPLOAD_ERR_OK);
        $this->mime   = $mime;
        $this->valid  = $valid;
        $this->ukuran = $ukuran;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getSize(): int
    {
        return $this->ukuran;
    }

    public function getMimeType(): string
    {
        return $this->mime;
    }

    public function move(string $targetPath, ?string $name = null, bool $overwrite = false)
    {
        $this->dipindah = true;
        $this->setPath($targetPath);

        return copy($this->getPathname(), rtrim($targetPath, '/') . '/' . $name);
    }
}
```

Lalu `tests/unit/FotoPresensiServiceTest.php` memakai `Tests\Support\FakeUploadedFile`:

- JPEG valid → return path cocok `/^\d{6}\/absen_masuk_10_.*\.jpg$/`, file ada di `$dir`, `dipindah === true`.
- MIME `application/pdf` → `PresensiException` dengan status 422.
- Size `2097153` → exception.
- `isValid() === false` → exception.
- `hapus()` menghapus file.
- Cleanup `$dir` di `tearDown` (`removeDir`).

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/FotoPresensiServiceTest.php`
Expected: FAIL class not found.

- [ ] **Step 3: Implementasikan service**

`simpan`:
1. `! $file->isValid()` → `PresensiException('Foto wajib diambil dan berhasil diunggah.')`.
2. `$file->getSize() > config('Presensi')->maxFotoBytes` → `PresensiException('Ukuran foto terlalu besar (maks 2 MB).')`.
3. `$mime = $file->getMimeType()`; map `image/jpeg => jpg`, `image/png => png`; selain itu `PresensiException('Foto harus berupa JPEG atau PNG.')`.
4. `$base = rtrim($dir ?? config('Presensi')->fotoDir, '/')`; `$sub = date('Ym')`; buat folder `$base . '/' . $sub` dengan `mkdir(..., 0775, true)` bila belum ada.
5. Nama `absen_<jenis>_<akunId>_<Ymd_His>_<8 hex>.ext`.
6. `$file->move($folder, $nama)`; bungkus `\Throwable` → `PresensiException('Foto gagal disimpan. Coba lagi.')`.
7. Return `$sub . '/' . $nama`.

`hapus` memakai base dir yang sama; validasi traversal tetap tugas controller yang menyajikan file.

- [ ] **Step 4: Jalankan test sampai lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/FotoPresensiServiceTest.php`
Expected: PASS (5 tests).

---

### Task 7: AbsenPunchService + method model presensi

**Files:**
- Modify: `app/Models/ModelPresensi.php` — tambah `sesi`, `sumber` ke `$allowedFields`; tambah dua method
- Create: `app/Services/Presensi/AbsenPunchService.php`
- Test: `tests/unit/AbsenPunchServiceTest.php`

**Interfaces:**
- Produces `ModelPresensi`:
  - `getHariIniByAkun(int $akunId, string $tanggal): array`
  - `getByAkunTanggalSesi(int $akunId, string $tanggal, string $sesi): ?object`
- Produces `AbsenPunchService`:
  - `validasiLokasi(int $unitId, float $lat, float $long): int` — jarak meter; melempar `PresensiException` 422
  - `masuk(int $akunId, int $unitId, string $sesi, float $lat, float $long, int $jarak, UploadedFile $foto, string $waktu): int`
  - `pulang(int $akunId, int $idPresensi, float $lat, float $long, UploadedFile $foto, string $waktu): void`
  - static `statusKehadiran(string $waktu, string $jadwalMasuk, string $jamToleransi): int`
  - static `jarakMeter(float $lat1, float $lon1, float $lat2, float $lon2): float`

- [ ] **Step 1: Tulis test yang gagal**

Schema: `presensi` lengkap sesuai kolom produksi (Task 1), `presensi_roster`, `unit(idunit, NAMA_UNIT, LATITUDE, LONGTITUDE, RADIUS)`. Seed unit 1 di `-8.1, 113.7`, radius 200.

Helper (`use App\Services\Presensi\AbsenPunchService; use Tests\Support\FakeUploadedFile;`):

```php
private function roster(int $akunId, string $tanggal, string $shift): void
{
    $this->db->table('presensi_roster')->insert([
        'akun_idakun' => $akunId,
        'tanggal'     => $tanggal,
        'shift'       => $shift,
        'created_at'  => date('Y-m-d H:i:s'),
    ]);
}
```

Test minimal:
```php
public function testStatusKehadiranBands(): void
{
    $this->assertSame(0, AbsenPunchService::statusKehadiran('2026-11-02 08:45:59', '08:45', '08:59'));
    $this->assertSame(1, AbsenPunchService::statusKehadiran('2026-11-02 08:46:00', '08:45', '08:59'));
    $this->assertSame(1, AbsenPunchService::statusKehadiran('2026-11-02 08:59:00', '08:45', '08:59'));
    $this->assertSame(2, AbsenPunchService::statusKehadiran('2026-11-02 08:59:01', '08:45', '08:59'));
}

public function testLokasiDiLuarRadiusDitolak(): void
{
    $this->expectException(\App\Services\Presensi\PresensiException::class);
    (new AbsenPunchService())->validasiLokasi(1, -8.2, 113.7);
}

public function testMasukPagiMembuatSatuBaris(): void
{
    $this->roster(10, '2026-11-02', 'PAGI');
    $id = (new AbsenPunchService(null, null, new FakeFotoService()))->masuk(
        10, 1, 'FULL', -8.1, 113.7, 5, $this->foto(), '2026-11-02 08:50:00'
    );

    $row = $this->db->table('presensi')->where('idpresensi', $id)->get()->getRow();
    $this->assertSame('FULL', $row->sesi);
    $this->assertSame('mobile', $row->sumber);
    $this->assertSame('08:45:00', $row->jam_jadwal_masuk);
    $this->assertSame(1, (int) $row->status_kehadiran);
}

public function testMasukGandaDitolak(): void
{
    $this->roster(10, '2026-11-02', 'PAGI');
    $service = new AbsenPunchService(null, null, new FakeFotoService());
    $service->masuk(10, 1, 'FULL', -8.1, 113.7, 5, $this->foto(), '2026-11-02 08:50:00');

    $this->expectException(\App\Services\Presensi\PresensiException::class);
    $service->masuk(10, 1, 'FULL', -8.1, 113.7, 5, $this->foto(), '2026-11-02 08:51:00');
}

public function testSoreTanpaPagiDitolak(): void
{
    $this->roster(10, '2026-11-02', 'PS');
    $this->expectException(\App\Services\Presensi\PresensiException::class);
    (new AbsenPunchService(null, null, new FakeFotoService()))->masuk(
        10, 1, 'SORE', -8.1, 113.7, 5, $this->foto(), '2026-11-02 17:00:00'
    );
}

public function testPsEmpatPunch(): void
{
    $this->roster(10, '2026-11-02', 'PS');
    $service = new AbsenPunchService(null, null, new FakeFotoService());

    $pagi = $service->masuk(10, 1, 'PAGI', -8.1, 113.7, 5, $this->foto(), '2026-11-02 08:50:00');
    $service->pulang(10, $pagi, -8.1, 113.7, $this->foto(), '2026-11-02 13:00:00');
    $sore = $service->masuk(10, 1, 'SORE', -8.1, 113.7, 5, $this->foto(), '2026-11-02 17:00:00');
    $service->pulang(10, $sore, -8.1, 113.7, $this->foto(), '2026-11-02 21:00:00');

    $this->assertSame(2, (int) $this->db->table('presensi')->countAllResults());
    $this->assertSame(2, (int) $this->db->table('presensi')->where('waktu_pulang IS NOT NULL', null, false)->countAllResults());
}

public function testPulangOrangLainDitolak(): void
{
    $this->roster(10, '2026-11-02', 'PAGI');
    $service = new AbsenPunchService(null, null, new FakeFotoService());
    $id = $service->masuk(10, 1, 'FULL', -8.1, 113.7, 5, $this->foto(), '2026-11-02 08:50:00');

    $this->expectException(\App\Services\Presensi\PresensiException::class);
    $service->pulang(11, $id, -8.1, 113.7, $this->foto(), '2026-11-02 17:00:00');
}

public function testInsertGagalMenghapusFoto(): void
{
    $this->roster(10, '2026-11-02', 'PAGI');
    $foto = new FakeFotoService();
    $service = new AbsenPunchService(new FakePresensiGagal(), null, $foto);

    try {
        $service->masuk(10, 1, 'FULL', -8.1, 113.7, 5, $this->foto(), '2026-11-02 08:50:00');
        $this->fail('Seharusnya melempar exception.');
    } catch (\App\Services\Presensi\PresensiException $e) {
        $this->assertSame(500, $e->status);
        $this->assertTrue($foto->dihapus, 'Foto harus dihapus saat insert gagal.');
    }
}
```

`FakeFotoService` dan `FakePresensiGagal`:

```php
class FakeFotoService extends \App\Services\Presensi\FotoPresensiService
{
    public bool $dihapus = false;

    public function simpan(\CodeIgniter\HTTP\Files\UploadedFile $file, string $jenis, int $akunId, ?string $dir = null): string
    {
        return 'test/' . $jenis . '_' . $akunId . '.jpg';
    }

    public function hapus(string $relatif, ?string $dir = null): void
    {
        $this->dihapus = true;
    }
}

class FakePresensiGagal extends \App\Models\ModelPresensi
{
    public function getByAkunTanggalSesi(int $akunId, string $tanggal, string $sesi)
    {
        return null;
    }

    public function insert($data = null, bool $returnID = true)
    {
        return false;
    }
}
```

Test memakai fake yang sama dari Task 6 (`use Tests\Support\FakeUploadedFile;`). Helper:

```php
private function foto(): FakeUploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'absen');
    file_put_contents($path, 'foto');

    return new FakeUploadedFile($path);
}
```

`FakeFotoService` mengganti penyimpanan file supaya test tidak menulis ke `writable/`.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/AbsenPunchServiceTest.php`
Expected: FAIL class not found.

- [ ] **Step 3: Tambah method ke `ModelPresensi`**

```php
public function getHariIniByAkun(int $akunId, string $tanggal): array
{
    return $this->where('akun_idakun', $akunId)
        ->where('DATE(waktu_masuk)', $tanggal)
        ->findAll();
}

public function getByAkunTanggalSesi(int $akunId, string $tanggal, string $sesi)
{
    return $this->where('akun_idakun', $akunId)
        ->where('DATE(waktu_masuk)', $tanggal)
        ->where('sesi', $sesi)
        ->first();
}
```

- [ ] **Step 4: Implementasikan `AbsenPunchService`**

Constructor menerima `?ModelPresensi`, `?ModelPresensiRoster`, `?FotoPresensiService`.

`validasiLokasi`:
- `abs($lat) > 90 || abs($long) > 180` → exception "Lokasi tidak valid."
- unit tidak ada / `LATITUDE`/`LONGTITUDE` bukan numerik / `RADIUS <= 0` → exception "Titik dan radius absen unit belum diatur."
- hitung `jarakMeter`; jika `> RADIUS` → exception "Lokasi terlalu jauh dari titik absen (maks N meter)."
- return `(int) round($jarak)`.

`masuk`:
1. `$tanggal = substr($waktu, 0, 10)`.
2. roster = `$this->roster->getByAkunTanggal($akunId, $tanggal)`; null → exception "Belum ada jadwal untuk hari ini. Hubungi admin cabang."
3. `$shift = strtoupper((string) $roster->shift)`; jika bukan `PAGI/SIANG/PS` → exception "Hari ini <shift>, tidak perlu absen."
4. `$sesi = strtoupper($sesi)`; jika `PS`: harus `PAGI`/`SORE`, dan `SORE` hanya jika `getByAkunTanggalSesi(...,'PAGI')` ada; selain PS: harus `FULL`.
5. Duplikat: `getByAkunTanggalSesi($akunId,$tanggal,$sesi)` → exception "Anda sudah absen <sesi> hari ini."
6. `$jadwalMasuk = AttendanceScoreCalculator::getScheduledTime($shift, $sesi)`.
7. `$jadwalPulang = config('Presensi')->shiftPulang[$shift]`; jika array ambil `[$sesi]`.
8. `$toleransi = date('H:i:s', strtotime($jadwalMasuk) + config('Presensi')->toleransiMenit * 60)`.
9. `$fotoPath = $this->foto->simpan($foto, 'masuk', $akunId)`.
10. Insert `presensi` dengan kolom: `waktu_masuk`, `jam_jadwal_masuk`, `jam_jadwal_pulang`, `jam_toleransi`, `status_absensi => 0`, `lat`, `long`, `ip => null` (controller mengisi IP? service tidak punya request; biarkan `null`), `foto`, `idjadwal_masuk => null`, `akun_idakun`, `unit_idunit`, `created_at`, `jarak`, `status_kehadiran`, `sesi`, `sumber => 'mobile'`.
11. Jika insert gagal: `$this->foto->hapus($fotoPath)` lalu exception 500.
12. Return insert ID.

`pulang`:
1. `$row = $this->presensi->find($idPresensi)`.
2. Tidak ada atau `akun_idakun !== $akunId` → exception 403 "Data absen tidak ditemukan."
3. `waktu_pulang` tidak kosong → exception "Anda sudah absen pulang."
4. `substr($row->waktu_masuk,0,10) !== substr($waktu,0,10)` → exception "Absen pulang hanya untuk hari ini."
5. Simpan foto `pulang`; update `waktu_pulang`, `foto_pulang`; gagal → hapus foto + exception 500.

`statusKehadiran` dan `jarakMeter` sesuai rumus yang sudah dipakai `Absensi` (Haversine radius 6371000).

- [ ] **Step 5: Jalankan test sampai lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/AbsenPunchServiceTest.php`
Expected: PASS (8 tests).

---

### Task 8: Halaman `/absen` (GET)

**Files:**
- Create: `app/Controllers/AbsenMobile.php`
- Create: `app/Views/absen/mobile.php`
- Modify: `app/Config/Routes.php` — tambah route `/absen`
- Test: `tests/AbsenMobilePageTest.php`

**Interfaces:**
- Consumes: `ModelPresensiRoster::getByAkunTanggal()`, `ModelPresensi::getHariIniByAkun()`, `ModelUnit::getById()`, `Config\Presensi`.
- Produces view `absen/mobile` dengan variabel `$hariIni`, `$goLive`, `$roster`, `$sesi`, `$unit`, `$nama`.

- [ ] **Step 1: Tulis test yang gagal**

Schema: `unit`, `akun`, `jabatan`, `presensi`, `presensi_roster`. `AbsenMobile` extends `Controller`, jadi tidak perlu tabel `stok_barang`/`service`.

`setUp` wajib memundurkan go-live supaya test halaman tidak tertahan tanggal default (`2026-11-01`):

```php
protected function setUp(): void
{
    parent::setUp();
    // ... buat skema + seed unit 1 (-8.1, 113.7, radius 200) ...
    $this->goLiveAsli = config('Presensi')->goLiveDate;
    config('Presensi')->goLiveDate = '2020-01-01';
}

protected function tearDown(): void
{
    config('Presensi')->goLiveDate = $this->goLiveAsli;
    parent::tearDown();
}

private function sesi(int $akun, int $unit, int $jabatan): void
{
    $this->withSession([
        'logged_in'  => true,
        'ID_AKUN'    => $akun,
        'ID_UNIT'    => $unit,
        'ID_JABATAN' => $jabatan,
        'NAMA'       => 'Tester',
    ]);
}

private function roster(int $akunId, string $tanggal, string $shift): void
{
    $this->db->table('presensi_roster')->insert([
        'akun_idakun' => $akunId,
        'tanggal'     => $tanggal,
        'shift'       => $shift,
        'created_at'  => date('Y-m-d H:i:s'),
    ]);
}

/** Tanggal yang sama dengan yang dipakai controller (zona Presensi, bukan UTC). */
private function hariIni(): string
{
    return (new \DateTime('now', new \DateTimeZone(config('Presensi')->zonaWaktu)))->format('Y-m-d');
}
```

```php
public function testTanpaRosterMenampilkanPesan(): void
{
    $this->sesi(10, 1, 36);
    $this->get('absen')->assertSee('Belum ada jadwal');
}

public function testOffTidakMenampilkanForm(): void
{
    $this->sesi(10, 1, 36);
    $this->roster(10, $this->hariIni(), 'OFF');
    $result = $this->get('absen');
    $result->assertSee('OFF');
    $result->assertDontSee('Absen Masuk');
}

public function testPagiSatuKartuDanPsDuaKartu(): void
{
    $this->sesi(10, 1, 36);

    $this->roster(10, $this->hariIni(), 'PAGI');
    $this->get('absen')->assertSee('Pagi');

    $this->db->table('presensi_roster')
        ->where('akun_idakun', 10)->where('tanggal', $this->hariIni())
        ->update(['shift' => 'PS']);
    $result = $this->get('absen');
    $result->assertSee('PS Pagi');
    $result->assertSee('PS Sore');
}

public function testSebelumGoLiveHanyaPesan(): void
{
    $this->sesi(10, 1, 36);
    $this->roster(10, $this->hariIni(), 'PAGI');
    config('Presensi')->goLiveDate = '2099-01-01';

    $this->get('absen')->assertSee('aktif mulai');
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/AbsenMobilePageTest.php`
Expected: FAIL route `absen` belum ada.

- [ ] **Step 3: Tambah route**

```php
//absen mobile
$routes->get('absen', 'AbsenMobile::index', ['filter' => 'auth']);
```

- [ ] **Step 4: Implementasikan `index()`**

```php
class AbsenMobile extends Controller
{
    protected $helpers = ['url', 'form'];

    public function index()
    {
        // Jangan ubah timezone global proses; cukup hitung waktu Jakarta.
        $zona    = new \DateTimeZone(config('Presensi')->zonaWaktu);
        $sekarang = new \DateTime('now', $zona);
        $hariIni = $sekarang->format('Y-m-d');
        $akunId  = (int) session('ID_AKUN');
        $unitId  = (int) session('ID_UNIT');
        $goLive  = config('Presensi')->goLiveDate;

        $roster = null;
        $sesi   = [];
        if ($hariIni >= $goLive) {
            $roster = (new \App\Models\ModelPresensiRoster())->getByAkunTanggal($akunId, $hariIni);

            if ($roster && in_array($roster->shift, ['PAGI', 'SIANG', 'PS'], true)) {
                $rows = (new \App\Models\ModelPresensi())->getHariIniByAkun($akunId, $hariIni);
                $bySesi = [];
                foreach ($rows as $row) {
                    $bySesi[$row->sesi ?: 'FULL'] = $row;
                }

                $daftarSesi = $roster->shift === 'PS' ? ['PAGI', 'SORE'] : ['FULL'];
                foreach ($daftarSesi as $kode) {
                    $sesi[] = [
                        'kode'     => $kode,
                        'label'    => $this->labelSesi($roster->shift, $kode),
                        'masuk'    => \App\Services\Kpi\AttendanceScoreCalculator::getScheduledTime($roster->shift, $kode),
                        'pulang'   => $this->jamPulang($roster->shift, $kode),
                        'presensi' => $bySesi[$kode] ?? null,
                    ];
                }
            }
        }

        return view('absen/mobile', [
            'hariIni' => $hariIni,
            'goLive'  => $goLive,
            'roster'  => $roster,
            'sesi'    => $sesi,
            'unit'    => (new \App\Models\ModelUnit())->getById($unitId),
            'nama'    => (string) session('NAMA'),
        ]);
    }

    private function labelSesi(string $shift, string $sesi): string
    {
        if ($shift !== 'PS') return $shift === 'PAGI' ? 'Pagi' : 'Siang';
        return $sesi === 'PAGI' ? 'PS Pagi' : 'PS Sore';
    }

    private function jamPulang(string $shift, string $sesi): string
    {
        $pulang = config('Presensi')->shiftPulang[$shift];
        return is_array($pulang) ? $pulang[$sesi] : $pulang;
    }
}
```

- [ ] **Step 5: Tulis view mobile**

Buat HTML mandiri (tanpa `template`) dengan `<meta name="viewport" content="width=device-width, initial-scale=1">` dan CSS inline sederhana. Isi:
1. Header: nama pegawai, `NAMA_UNIT`, tanggal `$hariIni`.
2. Jika `$hariIni < $goLive`: kartu "Absen mobile aktif mulai <goLive>." — tidak ada form.
3. Jika `$roster === null`: "Belum ada jadwal. Hubungi admin cabang."
4. Jika `$roster->shift === 'OFF'`: "OFF — libur."
5. Jika `$roster->shift === 'IZIN'`: "IZIN — <keterangan>."
6. Selain itu loop `$sesi`: kartu berisi label, jam `masuk–pulang`, status:
   - belum ada `presensi`: form `action="<?= base_url('absen/masuk') ?>"` method POST `enctype="multipart/form-data"` dengan hidden `sesi`, `latitude`, `longitude`, input `foto` (`accept="image/*" capture="environment"`), tombol "Absen Masuk".
   - ada `presensi` tanpa `waktu_pulang`: tampilkan jam masuk + `status_kehadiran` (0 Tepat, 1 Toleransi, 2 Telat), form `action="<?= base_url('absen/pulang') ?>"` dengan hidden `idpresensi`, `latitude`, `longitude`, input `foto`, tombol "Absen Pulang".
   - ada `waktu_pulang`: "Selesai HH:MM" + link foto masuk/pulang ke `base_url('absen/foto/'.$row->idpresensi)` dan `.../pulang`.
7. Script inline:
   - `ambilLokasi()` mengembalikan Promise `navigator.geolocation.getCurrentPosition` (enableHighAccuracy, timeout 15000).
   - `kompres(file)` memakai `createImageBitmap`, canvas max 1280 px, `canvas.toBlob(..., 'image/jpeg', 0.8)`.
   - Semua form dengan class `form-absen` di-`submit`: `preventDefault()`, ambil lokasi, kompres foto, `FormData`, `fetch(form.action, {method:'POST', body, credentials:'same-origin'})`, parse JSON, tampilkan pesan; jika `ok` reload.
   - Jika geolocation/kompres gagal, tampilkan pesan dan jangan submit.

- [ ] **Step 6: Jalankan test sampai lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/AbsenMobilePageTest.php`
Expected: PASS (4 tests).

---

### Task 9: Punch mobile + route foto

**Files:**
- Modify: `app/Controllers/AbsenMobile.php` — tambah `masuk()`, `pulang()`, `foto()`, `fotoPulang()`
- Modify: `app/Config/Routes.php` — tambah route POST + foto
- Test: `tests/AbsenMobilePunchTest.php`

**Interfaces:**
- Produces routes:
  - `POST absen/masuk`
  - `POST absen/pulang`
  - `GET absen/foto/(:num)`
  - `GET absen/foto/(:num)/pulang`
- Response JSON `{ok: bool, message: string}`.

- [ ] **Step 1: Tulis test yang gagal**

Schema dan seed: `unit` (unit 1 di `-8.1, 113.7`, radius 200), `akun`, `jabatan`, `presensi`, `presensi_roster`.

`setUp`/`tearDown` sama seperti Task 8: simpan `goLiveAsli`, set `config('Presensi')->goLiveDate = '2020-01-01'`, pulihkan di `tearDown`. Salin juga helper `sesi()`, `roster(int $akunId, string $tanggal, string $shift)`, dan `hariIni()`.

Tambahan untuk test foto: `setUp` menyimpan `fotoDirAsli` lalu set `config('Presensi')->fotoDir = WRITEPATH . 'uploads/presensi_test'`; `tearDown` menghapus folder itu rekursif dan memulihkan config. Jadi test tidak menulis ke folder produksi.

```php
public function testTanpaFotoDitolak(): void
{
    $this->sesi(10, 1, 36);
    $this->roster(10, $this->hariIni(), 'PAGI');

    $result = $this->post('absen/masuk', [
        'sesi' => 'FULL', 'latitude' => -8.1, 'longitude' => 113.7,
    ]);

    $result->assertStatus(422);
    $this->assertFalse(json_decode($result->getJSON(), true)['ok']);
    $this->assertSame(0, (int) $this->db->table('presensi')->countAllResults());
}

public function testDiLuarRadiusDitolak(): void
{
    $this->sesi(10, 1, 36);
    $this->roster(10, $this->hariIni(), 'PAGI');

    $result = $this->post('absen/masuk', [
        'sesi' => 'FULL', 'latitude' => -8.5, 'longitude' => 113.7,
    ]);

    $result->assertStatus(422);
    $this->assertStringContainsString('jauh', json_decode($result->getJSON(), true)['message']);
}

public function testPulangBarisOrangLainDitolak(): void
{
    $this->sesi(11, 1, 36);
    $this->db->table('presensi')->insert([
        'idpresensi' => 77, 'akun_idakun' => 10, 'unit_idunit' => 1,
        'waktu_masuk' => date('Y-m-d H:i:s'), 'sesi' => 'FULL',
    ]);

    $result = $this->post('absen/pulang', ['idpresensi' => 77]);
    $result->assertStatus(403);
}

public function testFotoHanyaUntukPemilikAtauScope(): void
{
    $this->siapkanFoto('202611/uji.jpg');
    $this->db->table('presensi')->insert([
        'idpresensi' => 77, 'akun_idakun' => 10, 'unit_idunit' => 1,
        'waktu_masuk' => '2026-11-02 08:50:00', 'foto' => '202611/uji.jpg',
    ]);

    $this->sesi(99, 2, 35);
    $this->get('absen/foto/77')->assertStatus(403);

    $this->sesi(10, 1, 36);
    $this->get('absen/foto/77')->assertStatus(200);
}
```

`$this->siapkanFoto()` menulis 1×1 JPEG ke `config('Presensi')->fotoDir . '/202611/uji.jpg'` dan menghapusnya di `tearDown`.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/AbsenMobilePunchTest.php`
Expected: FAIL route/method belum ada.

- [ ] **Step 3: Tambah route**

```php
$routes->post('absen/masuk', 'AbsenMobile::masuk', ['filter' => 'auth']);
$routes->post('absen/pulang', 'AbsenMobile::pulang', ['filter' => 'auth']);
$routes->get('absen/foto/(:num)', 'AbsenMobile::foto/$1', ['filter' => 'auth']);
$routes->get('absen/foto/(:num)/pulang', 'AbsenMobile::fotoPulang/$1', ['filter' => 'auth']);
```

- [ ] **Step 4: Implementasikan `masuk()` dan `pulang()`**

Keduanya:
- Tolak non-POST dengan 405.
- Hitung waktu Jakarta tanpa mengubah timezone global:
  ```php
  $sekarang = new \DateTime('now', new \DateTimeZone(config('Presensi')->zonaWaktu));
  $waktu    = $sekarang->format('Y-m-d H:i:s');
  ```
- Cek go-live (`$sekarang->format('Y-m-d') < config('Presensi')->goLiveDate`); sebelum go-live → 422 `{ok:false, message:'Absen mobile aktif mulai ...'}`.
- Ambil `$akunId`, `$unitId`, `$lat`, `$long`, `$file = $this->request->getFile('foto')`.
- `$service = new AbsenPunchService()`.
- `try` dengan urutan: **lokasi dulu, baru foto**, supaya request di luar radius tidak perlu upload:
  1. `$jarak = $service->validasiLokasi($unitId, (float) $lat, (float) $long)`.
  2. jika `! $file` → lempar `new PresensiException('Foto wajib diambil dan berhasil diunggah.')`.
  3. `$service->masuk($akunId, $unitId, (string) $this->request->getPost('sesi'), (float) $lat, (float) $long, $jarak, $file, $waktu)` (atau `pulang` dengan `idpresensi`).
  4. return `$this->jawab(true, 'Absen masuk berhasil.')`.
- `catch (PresensiException $e)` → `$this->jawab(false, $e->getMessage(), $e->status)`.
- `catch (\Throwable $e)` → log_message('error', ...), `$this->jawab(false, 'Absen gagal disimpan.', 500)`.

Khusus `pulang()`, cek kepemilikan **sebelum** lokasi/foto: `$row = (new ModelPresensi())->find($idPresensi)`; jika tidak ada atau `akun_idakun !== $akunId` → langsung 403 `{ok:false, message:'Data absen tidak ditemukan.'}`. Ini yang membuat test `testPulangBarisOrangLainDitolak` tetap 403 walau tanpa foto. Service tetap memeriksa ulang sebagai pertahanan kedua.

`jawab`:
```php
private function jawab(bool $ok, string $message, int $status = 200)
{
    return $this->response->setStatusCode($status)->setJSON(['ok' => $ok, 'message' => $message]);
}
```

- [ ] **Step 5: Implementasikan route foto**

```php
public function foto($id) { return $this->sajikanFoto((int) $id, 'foto'); }
public function fotoPulang($id) { return $this->sajikanFoto((int) $id, 'foto_pulang'); }

private function sajikanFoto(int $id, string $kolom)
{
    $row = (new \App\Models\ModelPresensi())->find($id);
    if (! $row) return $this->response->setStatusCode(404);

    $scope = new \App\Services\Presensi\PresensiScopeService();
    if (! $scope->bolehLihatFoto(
        (int) session('ID_JABATAN'),
        (int) session('ID_UNIT'),
        (int) session('ID_AKUN'),
        (int) $row->akun_idakun,
        (int) $row->unit_idunit
    )) {
        return $this->response->setStatusCode(403);
    }

    $relatif = (string) ($row->{$kolom} ?? '');
    if ($relatif === '' || strpos($relatif, '/') === false) {
        return $this->response->setStatusCode(404); // foto legacy ada di public/, bukan route ini
    }

    $base = realpath(config('Presensi')->fotoDir);
    if ($base === false) {
        return $this->response->setStatusCode(404);
    }

    $path = realpath($base . '/' . $relatif);
    if ($path === false || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || ! is_file($path)) {
        return $this->response->setStatusCode(404);
    }

    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';

    return $this->response
        ->setHeader('Content-Type', $mime)
        ->setHeader('Content-Disposition', 'inline')
        ->setBody((string) file_get_contents($path));
}
```

- [ ] **Step 6: Jalankan test sampai lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/AbsenMobilePunchTest.php`
Expected: PASS (4 tests).

Catatan: upload sukses tidak diuji lewat HTTP karena `FeatureTestTrait` tidak bisa memalsukan `is_uploaded_file()`. Jalur sukses sudah diuji di `FotoPresensiServiceTest` dan `AbsenPunchServiceTest`.

---

### Task 10: Grant menu roster + update PRD

**Files:**
- Create: `app/Database/Migrations/2026-11-02-000300_GrantMenuJadwalMasukRoster.php`
- Test: `tests/MenuRosterGrantTest.php`
- Modify: `docs/prd/modul/PRD_MODUL_07_HRD_Payroll.md`

**Interfaces:**
- Produces: menu id `118` (`Jadwal Masuk`, URL `jadwal_masuk`) ada di `jabatan.ROLES_JABATAN` untuk jabatan 0, 1, 34, 35, 47.

- [ ] **Step 1: Tulis test yang gagal**

Ikuti pola `tests/KasKeluarUnitFilterTest.php`: `setUp` menyimpan `$this->prefixAsli`, `setPrefix('')`, lalu `DROP TABLE IF EXISTS jabatan` + `CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)`; `tearDown` drop tabel lalu pulihkan prefix.

Seed:
`0` dengan `["1","2"]`, `1` dengan `["1","118"]`, `34` dengan `[]`, `35` dengan `["2"]`, `47` dengan `["2"]`, `36` dengan `["2"]`.

```php
public function testGrantIdempotenDanTidakMenyentuhRoleLain(): void
{
    $this->migrasi();
    $this->migrasi();

    $this->assertSame(['1', '2', '118'], $this->roles(0));
    $this->assertSame(['1', '118'], $this->roles(1));
    $this->assertSame(['118'], $this->roles(34));
    $this->assertSame(['2', '118'], $this->roles(35));
    $this->assertSame(['2', '118'], $this->roles(47));
    $this->assertSame(['2'], $this->roles(36));
}
```

`roles()` decode JSON lalu `array_map('strval', $roles)` **tanpa mengubah urutan** (migrasi menambahkan 118 di akhir). `migrasi()` memakai `require_once APPPATH . 'Database/Migrations/2026-11-02-000300_GrantMenuJadwalMasukRoster.php'` (folder Migrations di-exclude dari classmap) lalu `(new \App\Database\Migrations\GrantMenuJadwalMasukRoster())->up()`.

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/MenuRosterGrantTest.php`
Expected: FAIL class not found.

- [ ] **Step 3: Implementasikan migrasi**

`up()`:
- Jika `! $this->db->tableExists('jabatan')` return.
- `$target = [0, 1, 34, 35, 47]`.
- Ambil baris `jabatan` untuk target; decode `ROLES_JABATAN` (array atau `[]`); jika `in_array(118, $roles)` atau `in_array('118', $roles)` → skip; else append `118` (int) dan update.
- Jangan mengubah urutan/nilai menu lain.
- `down()`: hapus `118` dari array target (hanya nilai 118/`'118'`), update.

- [ ] **Step 4: Jalankan test sampai lulus**

Run: `vendor/bin/phpunit -c phpunit.xml tests/MenuRosterGrantTest.php`
Expected: PASS.

- [ ] **Step 5: Update PRD Modul 07**

Tambahkan bagian "Roster Absensi & Absen Mobile" yang menyatakan:
- Roster per tanggal menggantikan master `jadwal_masuk` untuk operasional.
- Shift PAGI/SIANG/PS/OFF/IZIN dan aturan punch.
- Role pengelola 0/1/34 semua unit, 35/47 unit sendiri, koreksi lampau hanya 1.
- `presensi` adalah sumber absen; **KPI/payroll belum tersambung pada fase ini**.
- Foto disimpan privat dan disajikan lewat route ber-`auth`.
- Tandai bahwa sinkronisasi KPI otomatis adalah pekerjaan terpisah dengan status `⚠️`.

- [ ] **Step 6: Jalankan seluruh test terkait**

Run:
```sh
vendor/bin/phpunit -c phpunit.xml tests/PresensiSchemaMigrationTest.php tests/unit/PresensiScopeServiceTest.php tests/unit/RosterServiceTest.php tests/RosterPageTest.php tests/RosterSimpanTest.php tests/unit/FotoPresensiServiceTest.php tests/unit/AbsenPunchServiceTest.php tests/AbsenMobilePageTest.php tests/AbsenMobilePunchTest.php tests/MenuRosterGrantTest.php
```
Expected: semua PASS.

- [ ] **Step 7: Cek diff bersih**

Run: `git diff --check && git status --short`
Expected: tidak ada whitespace error; hanya file yang tercantum di plan yang berubah.

---

## Catatan eksekusi

- Mulai dari Task 1. Jangan lompat ke controller sebelum service dasar lulus test.
- Jika test DB `erp_test` tidak bisa diakses, hentikan dan laporkan error aslinya; jangan mengarahkan test ke `erp_local`.
- Setelah Task 5, halaman roster bisa dipakai; setelah Task 9, `/absen` bisa dipakai. Task 10 hanya akses menu dan dokumentasi.
- Sebelum uji manual, isi `unit.LATITUDE`, `unit.LONGTITUDE`, dan `unit.RADIUS` untuk unit yang dipakai.
