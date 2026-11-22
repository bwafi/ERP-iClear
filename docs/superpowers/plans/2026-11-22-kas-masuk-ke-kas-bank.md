# Kas Masuk Menambah Saldo Kas & Bank — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Baris `kas_masuk` non-`kas awal` benar-benar menambah saldo Kas & Bank, Tutup Kasir, Rekon Harian, dan Detail Omset Harian; form Kas Masuk dibatasi agar input baru selalu inflow-only.

**Architecture:** `TutupKasirSourceDefinition` menjadi satu-satunya tempat definisi nominal Kas Masuk manual (tunai vs transfer, pengecualian `kas awal`). `KasBankSourceMovement`, `RekonDailyCalculator`, dan `DailyCashFlowService` memanggil helper itu; tidak ada penulisan ke `transaksi_kas_bank`, `jurnal`, atau schema baru. `kas_masuk.jenis` adalah posisi COA, bukan arah kas; form memaksa `kredit` dan menyaring COA.

**Tech Stack:** PHP 7.4-compatible, CodeIgniter 4.4.8, MySQLi/MariaDB, jQuery/Bootstrap 5, PHPUnit 9.

**Spec:** `docs/superpowers/specs/2026-11-22-kas-masuk-ke-kas-bank-design.md` (revisi 3, disetujui).

**Issue:** [#234](https://github.com/bwafi/ERP-iClear/issues/234)

## Global Constraints

- **Inflow-only:** setiap baris `kas_masuk` non-`kas awal` menambah `jumlah`. `jenis` tidak menentukan arah kas.
- Pengecualian `kas awal` wajib NULL-safe: `(deskripsi IS NULL OR LOWER(TRIM(deskripsi)) <> 'kas awal')`.
- `idbank IS NULL` = tunai (KAS unit `idunit`); `idbank` terisi = transfer ke rekening BANK dengan `bank_idbank` yang sama.
- Jangan mengubah `harianRange()`, `CashFlowCalculator`, `ModeKasBank::postingKasMasuk()`, atau alur Setor/Tarik.
- Jangan mengubah schema database, data historis, atau jurnal lama.
- Jangan memakai `str_starts_with()`; `composer.json` masih mendukung PHP 7.4. Gunakan `strpos($jenis, 'beban') === 0`.
- Semua nominal integer rupiah.
- **Jangan `git commit` sampai user mengizinkannya secara eksplisit.** Setiap "Checkpoint" hanya menjalankan test/pemeriksaan.
- Setelah test, kembalikan `build/logs` bila berubah: `git checkout -- build/logs`.

## Test Execution Constraint

Environment saat ini **belum bisa menjalankan PHPUnit**:

- `phpunit.xml` tidak ada; hanya `phpunit.xml.dist`, `phpunit.hard.xml`, `phpunit.erp_finance.xml`, `phpunit.tk.xml`.
- PHP 8.5.12 tidak memuat ekstensi `intl`, `sqlite3`, dan `mysqli`; semua konfigurasi PHPUnit memakai driver `MySQLi`.

Konsekuensinya:

- Setiap langkah test di bawah tetap ditulis sebagai test yang harus ada.
- Jalankan `vendor/bin/phpunit -c phpunit.xml.dist tests/...` hanya bila environment sudah mendukung. Bila gagal karena ekstensi/config, catat error aktual dan lanjut dengan `php -l`.
- Jangan mengklaim test lulus tanpa output nyata.

## Review Focus

- Kas Masuk tunai dan transfer menambah saldo rekening yang tepat, bukan rekening lain.
- Baris `kas awal` tidak menambah saldo, Tutup Kasir, Rekon, atau drill-down.
- `TutupKasirClosing::hitung()` memakai Kas Masuk dan carry-forward `akhir_cash`/`akhir_transfer`.
- `erpValuesRange()` identik dengan `erpValues()` setelah Kas Masuk ditambahkan.
- `DailyCashFlowService::assemble()` tetap benar saat dipanggil langsung dengan row mentah, termasuk menyaring `kas awal`.
- Form tidak lagi mengirim `posisi_drk`; server menolak COA beban/HPP/akumulasi penyusutan/kas-bank tanpa menulis `kas_masuk` atau `jurnal`.
- Baris legacy dan data dummy tidak diubah otomatis.

---

### Task 1: Helper definisi Kas Masuk manual

**Files:**
- Modify: `app/Services/Finance/TutupKasirSourceDefinition.php`
- Test: `tests/unit/TutupKasirKasMasukManualTest.php` (baru)

**Interfaces:**
- Produces:
  - `kasMasukManualCash(int $unitId, string $tanggal): int`
  - `kasMasukManualTransfer(int $unitId, string $tanggal): int`
  - `kasMasukManualCashRange(int $unitId, string $dari, ?string $sampai = null): int`
  - `kasMasukManualTransferRange(int $unitId, string $dari, ?string $sampai = null): int`
  - `kasMasukManualTransferBankRange(string $bankId, ?int $unitId, string $dari, ?string $sampai = null): int`
  - `kasMasukManualHarianRange(int $unitId, string $dari, string $sampai): array{cash:array<string,int>,transfer:array<string,int>}`
- Consumes: `Config\Database`, `App\Libraries\ReadCache` lewat `memo()` yang sudah ada.

- [ ] **Step 1: Write the failing unit test**

Buat `tests/unit/TutupKasirKasMasukManualTest.php`:

```php
<?php

namespace Tests\unit;

use App\Services\Finance\TutupKasirSourceDefinition;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Kas Masuk manual (bukan 'kas awal') adalah sumber movement.
 *
 * Kontrak:
 *  - idbank NULL  -> tunai
 *  - idbank terisi -> transfer
 *  - deskripsi 'kas awal' (case-insensitive, trim) tidak dihitung
 *  - jenis TIDAK menentukan arah
 *
 * @internal
 */
final class TutupKasirKasMasukManualTest extends CIUnitTestCase
{
    private const UNIT = 1;
    private const D1 = '2026-12-01';
    private const D2 = '2026-12-02';

    private const TABEL = ['kas_masuk'];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    private string $prefixAsli = '';
    private TutupKasirSourceDefinition $src;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
        }

        $this->db->query('CREATE TABLE kas_masuk (
            idkas_masuk INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NULL,
            deskripsi TEXT NULL,
            jumlah BIGINT DEFAULT 0,
            idunit INT NULL,
            idbank VARCHAR(20) NULL,
            jenis VARCHAR(15) NULL)');

        $this->src = new TutupKasirSourceDefinition($this->db);
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
        }
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function masuk(string $tanggal, int $jumlah, ?string $bank, string $deskripsi = 'Setoran', int $unit = self::UNIT): void
    {
        $this->db->table('kas_masuk')->insert([
            'tanggal' => $tanggal,
            'deskripsi' => $deskripsi,
            'jumlah' => $jumlah,
            'idunit' => $unit,
            'idbank' => $bank,
            'jenis' => 'debet',
        ]);
    }

    public function testTunaiDanTransferDipisah(): void
    {
        $this->masuk(self::D1, 50000, null, 'Modal tunai');
        $this->masuk(self::D1, 70000, 'BNI-001', 'Transfer masuk');

        $this->assertSame(50000, $this->src->kasMasukManualCash(self::UNIT, self::D1));
        $this->assertSame(70000, $this->src->kasMasukManualTransfer(self::UNIT, self::D1));
    }

    public function testKasAwalTidakDihitung(): void
    {
        $this->masuk(self::D1, 900000, null, 'kas awal');
        $this->masuk(self::D1, 100000, null, ' KAS AWAL ');
        $this->masuk(self::D1, 25000, null, 'Setoran');

        $this->assertSame(25000, $this->src->kasMasukManualCash(self::UNIT, self::D1));
        $this->assertSame(0, $this->src->kasMasukManualTransfer(self::UNIT, self::D1));
    }

    public function testJenisTidakMenentukanArah(): void
    {
        $this->db->table('kas_masuk')->insert([
            'tanggal' => self::D1, 'deskripsi' => 'Kredit tetap masuk',
            'jumlah' => 40000, 'idunit' => self::UNIT, 'idbank' => null,
            'jenis' => 'kredit',
        ]);

        $this->assertSame(40000, $this->src->kasMasukManualCash(self::UNIT, self::D1));
    }

    public function testUnitLainDanRentang(): void
    {
        $this->masuk(self::D1, 10000, null, 'Unit 1');
        $this->masuk(self::D2, 20000, null, 'Unit 1');
        $this->masuk(self::D1, 99000, null, 'Unit 2', 2);

        $this->assertSame(30000, $this->src->kasMasukManualCashRange(self::UNIT, self::D1, self::D2));
        $this->assertSame(10000, $this->src->kasMasukManualCashRange(self::UNIT, self::D1, self::D1));
        $this->assertSame(0, $this->src->kasMasukManualCashRange(3, self::D1, self::D2));
    }

    public function testBankRangeMemfilterRekening(): void
    {
        $this->masuk(self::D1, 10000, 'BNI-001');
        $this->masuk(self::D1, 20000, 'BCA-001');

        $this->assertSame(10000, $this->src->kasMasukManualTransferBankRange('BNI-001', self::UNIT, self::D1));
        $this->assertSame(30000, $this->src->kasMasukManualTransferBankRange('BNI-001', null, self::D1));
        $this->assertSame(0, $this->src->kasMasukManualTransferBankRange('', self::UNIT, self::D1));
    }

    public function testHarianRangeMengelompokkanPerTanggal(): void
    {
        $this->masuk(self::D1, 10000, null);
        $this->masuk(self::D1, 20000, 'BNI-001');
        $this->masuk(self::D2, 30000, null);

        $hasil = $this->src->kasMasukManualHarianRange(self::UNIT, self::D1, self::D2);

        $this->assertSame(10000, $hasil['cash'][self::D1]);
        $this->assertSame(20000, $hasil['transfer'][self::D1]);
        $this->assertSame(30000, $hasil['cash'][self::D2]);
        $this->assertArrayNotHasKey(self::D2, $hasil['transfer']);
    }
}
```

- [ ] **Step 2: Run test (expected: FAIL, method belum ada)**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/TutupKasirKasMasukManualTest.php
```

Bila environment belum mendukung, catat error aktual (mis. `Class "Locale" not found` atau `mysqli` tidak ada) lalu lanjut.

- [ ] **Step 3: Implement helper**

Di `app/Services/Finance/TutupKasirSourceDefinition.php`, tambahkan blok baru setelah bagian Kas Keluar (sekitar baris 641) atau sebelum `AGREGAT`:

```php
    // =================================================================
    // KAS MASUK MANUAL — baris non-'kas awal' adalah inflow
    // =================================================================
    //
    // `kas_masuk.jenis` adalah posisi COA untuk jurnal, BUKAN arah kas.
    // Halaman Kas Masuk adalah kontrak inflow: setiap baris non-'kas awal'
    // menambah saldo. Pengecualian memakai deskripsi, bukan jenis.

    private const KAS_AWAL_SQL = "(deskripsi IS NULL OR LOWER(TRIM(deskripsi)) <> 'kas awal')";

    /** Kas masuk manual TUNAI (idbank IS NULL) pada satu tanggal. */
    public function kasMasukManualCash(int $unitId, string $tanggal): int
    {
        return $this->kasMasukManualCashRange($unitId, $tanggal, $tanggal);
    }

    /** Kas masuk manual TRANSFER (idbank terisi) pada satu tanggal. */
    public function kasMasukManualTransfer(int $unitId, string $tanggal): int
    {
        return $this->kasMasukManualTransferRange($unitId, $tanggal, $tanggal);
    }

    /** Kas masuk manual TUNAI dalam rentang [$dari, $sampai]. */
    public function kasMasukManualCashRange(int $unitId, string $dari, ?string $sampai = null): int
    {
        return $this->memo("kasMasukManualCashRange|$unitId|$dari|" . ($sampai ?? ''), function () use ($unitId, $dari, $sampai) {
            $b = $this->db->table('kas_masuk')
                ->selectSum('jumlah', 'total')
                ->where('idunit', $unitId)
                ->where('DATE(tanggal) >=', $dari)
                ->where(self::KAS_AWAL_SQL, null, false)
                ->where('idbank', null);

            if ($sampai !== null) {
                $b->where('DATE(tanggal) <=', $sampai);
            }

            return (int) ($b->get()->getRow()->total ?? 0);
        });
    }

    /** Kas masuk manual TRANSFER seluruh rekening unit dalam rentang. */
    public function kasMasukManualTransferRange(int $unitId, string $dari, ?string $sampai = null): int
    {
        return $this->memo("kasMasukManualTransferRange|$unitId|$dari|" . ($sampai ?? ''), function () use ($unitId, $dari, $sampai) {
            $b = $this->db->table('kas_masuk')
                ->selectSum('jumlah', 'total')
                ->where('idunit', $unitId)
                ->where('DATE(tanggal) >=', $dari)
                ->where(self::KAS_AWAL_SQL, null, false)
                ->where('idbank IS NOT NULL', null, false);

            if ($sampai !== null) {
                $b->where('DATE(tanggal) <=', $sampai);
            }

            return (int) ($b->get()->getRow()->total ?? 0);
        });
    }

    /**
     * Kas masuk manual TRANSFER untuk satu rekening.
     *
     * $unitId null = saldo fisik (semua unit); $unitId terisi = posisi unit.
     */
    public function kasMasukManualTransferBankRange(
        string $bankId,
        ?int $unitId,
        string $dari,
        ?string $sampai = null
    ): int {
        $bankId = trim($bankId);
        if ($bankId === '') {
            return 0;
        }

        return $this->memo("kasMasukManualTransferBankRange|$bankId|" . ($unitId ?? 'all') . "|$dari|" . ($sampai ?? ''), function () use ($bankId, $unitId, $dari, $sampai) {
            $b = $this->db->table('kas_masuk')
                ->selectSum('jumlah', 'total')
                ->where('idbank', $bankId)
                ->where('DATE(tanggal) >=', $dari)
                ->where(self::KAS_AWAL_SQL, null, false);

            if ($unitId !== null) {
                $b->where('idunit', $unitId);
            }
            if ($sampai !== null) {
                $b->where('DATE(tanggal) <=', $sampai);
            }

            return (int) ($b->get()->getRow()->total ?? 0);
        });
    }

    /**
     * Kas masuk manual per tanggal, dipisah tunai/transfer.
     *
     * @return array{cash:array<string,int>,transfer:array<string,int>}
     */
    public function kasMasukManualHarianRange(int $unitId, string $dari, string $sampai): array
    {
        $out = ['cash' => [], 'transfer' => []];

        foreach (['cash' => true, 'transfer' => false] as $key => $tunai) {
            $b = $this->db->table('kas_masuk')
                ->select('DATE(tanggal) AS d', false)
                ->selectSum('jumlah', 'total')
                ->where('idunit', $unitId)
                ->where('DATE(tanggal) >=', $dari)
                ->where('DATE(tanggal) <=', $sampai)
                ->where(self::KAS_AWAL_SQL, null, false);

            if ($tunai) {
                $b->where('idbank', null);
            } else {
                $b->where('idbank IS NOT NULL', null, false);
            }

            foreach ($b->groupBy('d')->get()->getResultArray() as $r) {
                $out[$key][(string) $r['d']] = (int) $r['total'];
            }
        }

        return $out;
    }
```

Catatan: `KAS_AWAL_SQL` memakai `deskripsi` tanpa qualifier karena query hanya membaca satu tabel. Jangan menambahkan `kas_masuk.` di sini.

- [ ] **Step 4: Run test (expected: PASS)**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/TutupKasirKasMasukManualTest.php
```

- [ ] **Step 5: Lint**

```bash
php -l app/Services/Finance/TutupKasirSourceDefinition.php
php -l tests/unit/TutupKasirKasMasukManualTest.php
```

- [ ] **Checkpoint:** helper berdiri sendiri dan lulus test. Jangan commit.

---

### Task 2: Komposisi ke `kasMasukDp`, `transferMasukDp`, `ringkasan`, `ringkasanRange`

**Files:**
- Modify: `app/Services/Finance/TutupKasirSourceDefinition.php`
- Test: `tests/unit/TutupKasirKasMasukManualTest.php` (tambah method)

**Interfaces:**
- Consumes: helper Task 1.
- Produces: `kasMasukDp()` dan `transferMasukDp()` sekarang memuat Kas Masuk manual; `ringkasan()`/`ringkasanRange()` konsisten.

- [ ] **Step 1: Add failing assertions**

Tambahkan ke `tests/unit/TutupKasirKasMasukManualTest.php`:

```php
    public function testKasMasukDpMemuatKasMasukManual(): void
    {
        $this->masuk(self::D1, 50000, null, 'Modal tunai');
        $this->masuk(self::D1, 70000, 'BNI-001', 'Transfer masuk');

        $this->assertSame(50000, $this->src->kasMasukDp(self::UNIT, self::D1));
        $this->assertSame(70000, $this->src->transferMasukDp(self::UNIT, self::D1));
    }

    public function testRingkasanRangeMemuatKasMasukManual(): void
    {
        $this->masuk(self::D1, 50000, null, 'Modal tunai');
        $this->masuk(self::D1, 70000, 'BNI-001', 'Transfer masuk');

        $r = $this->src->ringkasanRange(self::UNIT, self::D1, self::D1);

        $this->assertSame(50000, $r['kas_masuk_tunai']);
        $this->assertSame(70000, $r['kas_masuk_transfer']);
        $this->assertSame(50000, $r['cash']);
        $this->assertSame(70000, $r['transfer']);
        $this->assertSame(120000, $r['total_pendapatan']);
    }
```

Catatan: test ini memakai tabel `penjualan`, `service`, `service_dp` lewat `kasMasukDp`/`ringkasanRange`. Tambahkan schema minimal di `setUp()` Task 1:

```php
        $this->db->query('CREATE TABLE penjualan (
            idpenjualan INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            unit_idunit INT NULL, kode_invoice VARCHAR(64) NULL,
            bayar_tunai BIGINT DEFAULT 0, bayar_bank BIGINT DEFAULT 0,
            tanggal DATE NULL)');
        $this->db->query('CREATE TABLE service (
            idservice INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            unit_idunit INT NULL, status_service INT DEFAULT 1,
            bayar_tunai BIGINT DEFAULT 0, harus_dibayar BIGINT DEFAULT 0,
            tanggal_selesai DATE NULL)');
        $this->db->query('CREATE TABLE service_dp (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            service_idservice INT NOT NULL, jumlah BIGINT DEFAULT 0,
            status VARCHAR(15) NOT NULL DEFAULT \'aktif\',
            metode VARCHAR(15) NULL, tanggal DATE NULL)');
        $this->db->query('CREATE TABLE kas_keluar (
            idkas_keluar INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            idunit INT NULL, idbank VARCHAR(20) NULL,
            jumlah BIGINT DEFAULT 0, tanggal DATE NULL)');
```

Dan tambahkan ke `TABEL`:

```php
    private const TABEL = ['kas_masuk', 'penjualan', 'service', 'service_dp', 'kas_keluar'];
```

- [ ] **Step 2: Run test (expected: FAIL)**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/TutupKasirKasMasukManualTest.php
```

- [ ] **Step 3: Implement composition**

Ubah `kasMasukDp()`:

```php
    public function kasMasukDp(int $unitId, string $tanggal): int
    {
        return $this->cashMasuk($unitId, $tanggal)
            + $this->dpMasukTunai($unitId, $tanggal)
            + $this->kasMasukManualCash($unitId, $tanggal);
    }
```

Ubah `transferMasukDp()`:

```php
    public function transferMasukDp(int $unitId, string $tanggal): int
    {
        return $this->transferMasuk($unitId, $tanggal)
            + $this->dpMasukTransfer($unitId, $tanggal)
            - $this->dpServiceSelesai($unitId, $tanggal)
            + $this->kasMasukManualTransfer($unitId, $tanggal);
    }
```

Ubah `ringkasan()` agar `cash`/`transfer` memuat manual dan menambahkan komponen transparan:

```php
    public function ringkasan(int $unitId, string $tanggal): array
    {
        $manualTunai    = $this->kasMasukManualCash($unitId, $tanggal);
        $manualTransfer = $this->kasMasukManualTransfer($unitId, $tanggal);
        $cash     = $this->cashMasuk($unitId, $tanggal) + $manualTunai;
        $transfer = $this->transferMasuk($unitId, $tanggal) + $manualTransfer;

        return [
            'kas_awalcash'         => $this->kasAwalCash($unitId, $tanggal),
            'kas_awaltf'           => $this->kasAwalTransfer($unitId, $tanggal),

            'cashpenjualan'        => $this->cashPenjualan($unitId, $tanggal),
            'cashservice'          => $this->cashService($unitId, $tanggal),
            'tfpenjualan'          => $this->transferPenjualan($unitId, $tanggal),
            'tfservice'            => $this->transferService($unitId, $tanggal),
            'kas_masuk_tunai'      => $manualTunai,
            'kas_masuk_transfer'   => $manualTransfer,

            'cash'                 => $cash,
            'transfer'             => $transfer,
            'total_pendapatan'     => $transfer + $cash,
            'pengeluarancash'      => $this->kasKeluarCash($unitId, $tanggal),
            'pengeluarantf'        => $this->kasKeluarTransfer($unitId, $tanggal),
        ];
    }
```

Ubah `ringkasanRange()`:

```php
        $manualTunai      = $this->kasMasukManualCashRange($unitId, $dari, $sampai);
        $manualTransfer   = $this->kasMasukManualTransferRange($unitId, $dari, $sampai);
        $cash             = $cashPenjualan + $cashService + $dpTunai + $manualTunai;
        $transfer         = $tfPenjualan + $tfService + $dpTransfer - $dpServiceSelesai + $manualTransfer;
```

dan tambahkan key di return:

```php
            'kas_masuk_tunai'    => $manualTunai,
            'kas_masuk_transfer' => $manualTransfer,
```

- [ ] **Step 4: Run test (expected: PASS)**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/TutupKasirKasMasukManualTest.php
```

- [ ] **Step 5: Regression Tutup Kasir DP**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/TutupKasirDpTest.php
```

- [ ] **Checkpoint:** angka Tutup Kasir dan Rekon memakai definisi yang sama. Jangan commit.

---

### Task 3: Saldo Kas & Bank memakai Kas Masuk

**Files:**
- Modify: `app/Services/Finance/KasBankSourceMovement.php`
- Test: `tests/KasBankTest.php` (tambah method)

**Interfaces:**
- Consumes: `TutupKasirSourceDefinition::kasMasukManualCashRange()` dan `kasMasukManualTransferBankRange()`.
- Produces: `movementKas()` dan `movementBank()` memuat Kas Masuk; `rincianMovement()` KAS otomatis ikut karena memakai `cashMasuk()`.

- [ ] **Step 1: Add failing tests**

Tambahkan ke `tests/KasBankTest.php` sebelum bagian ACCOUNT SCOPE:

```php
    public function testKasMasukTunaiMenambahSaldoKas(): void
    {
        $tanggal = \App\Services\Finance\FinanceScopeService::kasBankPeriodeMulaiDate();

        $this->db->query("INSERT INTO db_kas_masuk (idkas_masuk, tanggal, deskripsi, jumlah, idunit, idbank)
            VALUES (801, '{$tanggal}', 'Modal tunai', 25000, 1, NULL)");

        $model = new ModelTransaksiKasBank();
        $this->assertSame(25000, $model->getSaldoFisikAkun(1));
    }

    public function testKasMasukTransferMenambahSaldoBank(): void
    {
        $tanggal = \App\Services\Finance\FinanceScopeService::bankPeriodeMulaiDate();

        $this->db->query("INSERT INTO db_kas_masuk (idkas_masuk, tanggal, deskripsi, jumlah, idunit, idbank)
            VALUES (802, '{$tanggal}', 'Transfer masuk', 40000, 1, 'BNI-001')");

        $model = new ModelTransaksiKasBank();
        $this->assertSame(540000, $model->getSaldoFisikAkun(2));
    }

    public function testKasMasukKasAwalTidakMenambahSaldo(): void
    {
        $tanggal = \App\Services\Finance\FinanceScopeService::kasBankPeriodeMulaiDate();

        $this->db->query("INSERT INTO db_kas_masuk (idkas_masuk, tanggal, deskripsi, jumlah, idunit, idbank)
            VALUES (803, '{$tanggal}', 'kas awal', 50000, 1, NULL)");

        $model = new ModelTransaksiKasBank();
        $this->assertSame(0, $model->getSaldoFisikAkun(1));
    }

    public function testKasMasukTransferTidakBocorKeRekeningLain(): void
    {
        $tanggal = \App\Services\Finance\FinanceScopeService::bankPeriodeMulaiDate();

        $this->db->query("INSERT INTO db_kas_masuk (idkas_masuk, tanggal, deskripsi, jumlah, idunit, idbank)
            VALUES (804, '{$tanggal}', 'Transfer IRA', 30000, 1, 'IRA-001')");

        $model = new ModelTransaksiKasBank();
        $this->assertSame(30000, $model->getSaldoFisikAkun(9));
        $this->assertSame(500000, $model->getSaldoFisikAkun(2));
    }
```

Catatan: akun 2 punya opening 500.000; akun 9 tidak punya opening. Akun 1 KAS tidak punya opening di fixture. Bila fixture berubah, sesuaikan angka.

`getSaldoFisikAkun()` untuk KAS membaca tabel `opening_kas`. `KasBankTest::buatSkema()` belum membuatnya. Tambahkan schema minimal berikut di `buatSkema()` (pakai `IF NOT EXISTS` supaya tidak bentrok bila migrasi test sudah membuatnya):

```php
        $q('CREATE TABLE IF NOT EXISTS db_opening_kas (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, unit_id INT NULL, tanggal TEXT NULL,
                opening BIGINT NULL, real_cash BIGINT NULL, selisih BIGINT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'BELUM_VERIFIKASI\',
                keterangan TEXT NULL, input_by INT NULL, verifikasi_by INT NULL,
                verifikasi_at TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
```

Tanpa baris opening, `opening()` mengembalikan 0, sehingga assertion `25000` dan `0` valid.

- [ ] **Step 2: Run test (expected: FAIL)**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/KasBankTest.php --filter KasMasuk
```

- [ ] **Step 3: Implement movement**

Di `cashMasuk()` (`:555-580`), tambahkan komponen manual:

```php
        return (int) ($p->get()->getRow()->s ?? 0)
            + (int) ($s->get()->getRow()->s ?? 0)
            + (new TutupKasirSourceDefinition($this->db))->dpMasukTunaiRange($unitId, $dari, $sampai)
            + (new TutupKasirSourceDefinition($this->db))->kasMasukManualCashRange($unitId, $dari, $sampai);
```

Di `movementBank()` (`:488-540`), setelah blok DP transfer dan sebelum `return`:

```php
        // Kas Masuk manual ber-rekening. Sama seperti kas_keluar:
        //  - $unitId null  -> seluruh unit (saldo fisik rekening)
        //  - $unitId diisi -> hanya unit yang berhak atas rekening ini
        $srcDef = new TutupKasirSourceDefinition($this->db);

        if ($unitId === null) {
            $masuk += $srcDef->kasMasukManualTransferBankRange((string) $bankId, null, $dari, $sampai);
        } elseif ($this->unitBerhakAtasRekening($akun, $unitId)) {
            $masuk += $srcDef->kasMasukManualTransferBankRange((string) $bankId, $unitId, $dari, $sampai);
        }
```

Jangan mengubah `movementInternalUnion()`; baris `kas_masuk` tidak punya `transfer_ref`.

- [ ] **Step 4: Run test (expected: PASS)**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/KasBankTest.php --filter KasMasuk
```

- [ ] **Step 5: Regression saldo dan alur KAS**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/KasBankTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/KasBankAlurSampaiTanggalTest.php
```

- [ ] **Checkpoint:** saldo fisik dan `rincianMovement()` KAS konsisten. Jangan commit.

---

### Task 4: Rekon Harian memakai Kas Masuk

**Files:**
- Modify: `app/Services/Finance/RekonDailyCalculator.php`
- Test: `tests/unit/RekonKasMasukManualTest.php` (baru)

**Interfaces:**
- Consumes: `TutupKasirSourceDefinition::kasMasukManualHarianRange()`.
- Produces: `erpValuesRange()` memuat Kas Masuk dan tetap identik dengan `erpValues()`.

- [ ] **Step 1: Write failing test**

Buat `tests/unit/RekonKasMasukManualTest.php`:

```php
<?php

namespace Tests\unit;

use App\Services\Finance\RekonDailyCalculator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class RekonKasMasukManualTest extends CIUnitTestCase
{
    private const UNIT = 1;
    private const D1 = '2026-12-01';
    private const D2 = '2026-12-02';

    private const TABEL = ['penjualan', 'service', 'service_dp', 'kas_keluar', 'kas_masuk'];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;
    private string $prefixAsli = '';
    private RekonDailyCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
        }

        $this->db->query('CREATE TABLE penjualan (
            idpenjualan INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            unit_idunit INT NULL, kode_invoice VARCHAR(64) NULL,
            bayar_tunai BIGINT DEFAULT 0, bayar_bank BIGINT DEFAULT 0,
            tanggal DATE NULL)');
        $this->db->query('CREATE TABLE service (
            idservice INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            unit_idunit INT NULL, status_service INT DEFAULT 1,
            bayar_tunai BIGINT DEFAULT 0, harus_dibayar BIGINT DEFAULT 0,
            tanggal_selesai DATE NULL)');
        $this->db->query('CREATE TABLE service_dp (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            service_idservice INT NOT NULL, jumlah BIGINT DEFAULT 0,
            status VARCHAR(15) NOT NULL DEFAULT \'aktif\',
            metode VARCHAR(15) NULL, tanggal DATE NULL)');
        $this->db->query('CREATE TABLE kas_keluar (
            idkas_keluar INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            idunit INT NULL, idbank VARCHAR(20) NULL,
            jumlah BIGINT DEFAULT 0, tanggal DATE NULL)');
        $this->db->query('CREATE TABLE kas_masuk (
            idkas_masuk INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NULL, deskripsi TEXT NULL, jumlah BIGINT DEFAULT 0,
            idunit INT NULL, idbank VARCHAR(20) NULL, jenis VARCHAR(15) NULL)');

        $this->db->table('kas_masuk')->insert([
            ['tanggal' => self::D1, 'deskripsi' => 'Modal tunai', 'jumlah' => 10000, 'idunit' => self::UNIT, 'idbank' => null, 'jenis' => 'debet'],
            ['tanggal' => self::D1, 'deskripsi' => 'Transfer masuk', 'jumlah' => 20000, 'idunit' => self::UNIT, 'idbank' => 'BNI-001', 'jenis' => 'debet'],
            ['tanggal' => self::D2, 'deskripsi' => 'kas awal', 'jumlah' => 999000, 'idunit' => self::UNIT, 'idbank' => null, 'jenis' => 'debet'],
        ]);

        $this->calc = new RekonDailyCalculator();
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
        }
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    public function testErpValuesDanRangeMemuatKasMasukManual(): void
    {
        $d1 = $this->calc->erpValues(self::UNIT, self::D1);
        $this->assertSame(10000, $d1['cash_masuk']);
        $this->assertSame(20000, $d1['transfer_masuk']);

        $range = $this->calc->erpValuesRange(self::UNIT, self::D1, self::D2);

        foreach ([self::D1, self::D2] as $d) {
            $this->assertSame(
                $this->calc->erpValues(self::UNIT, $d),
                $range[$d] ?? ['cash_masuk' => 0, 'transfer_masuk' => 0, 'kas_keluar' => 0],
                "Range harus identik dengan per-hari pada {$d}."
            );
        }

        $this->assertSame(10000, $range[self::D1]['cash_masuk']);
        $this->assertSame(20000, $range[self::D1]['transfer_masuk']);
    }

    public function testKasAwalTidakMasukRange(): void
    {
        $range = $this->calc->erpValuesRange(self::UNIT, self::D2, self::D2);
        $this->assertArrayNotHasKey(self::D2, $range, 'Baris kas awal tidak boleh membuat hari berisi kas masuk.');
    }
}
```

- [ ] **Step 2: Run test (expected: FAIL)**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/RekonKasMasukManualTest.php
```

- [ ] **Step 3: Implement range**

Di `erpValuesRange()`, setelah blok DP dan sebelum `return $out;`:

```php
        // Kas Masuk manual: inflow-only, dipisah tunai/transfer.
        $manual = (new TutupKasirSourceDefinition($this->db))
            ->kasMasukManualHarianRange($unitId, $startDate, $endDate);

        foreach (['cash' => 'cash_masuk', 'transfer' => 'transfer_masuk'] as $key => $kolom) {
            foreach ($manual[$key] as $d => $nilai) {
                if (! isset($out[$d])) {
                    $out[$d] = ['cash_masuk' => 0, 'transfer_masuk' => 0, 'kas_keluar' => 0];
                }
                $out[$d][$kolom] += $nilai;
            }
        }
```

`erpValues()` tidak perlu query baru karena sudah memakai `kasMasukDp()`/`transferMasukDp()`.

- [ ] **Step 4: Run test (expected: PASS)**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/RekonKasMasukManualTest.php
```

- [ ] **Step 5: Regression Rekon**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/RekonAntarUnitTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/RekonDpTest.php
```

- [ ] **Checkpoint:** Rekon per hari dan range identik. Jangan commit.

---

### Task 5: Detail Omset Harian memakai Kas Masuk

**Files:**
- Modify: `app/Services/Finance/DailyCashFlowService.php`
- Test: `tests/unit/DailyCashFlowServiceTest.php` (tambah method)

**Interfaces:**
- Produces: `fetchKasMasuk(int $unitId, string $tanggal): array`; `assemble()` mengonsumsi `$sources['kas_masuk']`.
- Consumes: tidak ada service baru.

- [ ] **Step 1: Add failing tests**

Tambahkan ke `tests/unit/DailyCashFlowServiceTest.php`:

```php
    public function testKasAwalTidakMasukSubtotalSaatAssembleLangsung(): void
    {
        $result = $this->service()->assemble([
            'kas_masuk' => [
                (object) ['deskripsi' => 'kas awal', 'jumlah' => 900000, 'idbank' => null, 'created_on' => '2026-10-01 07:00:00'],
                (object) ['deskripsi' => 'Setoran', 'jumlah' => 50000, 'idbank' => null, 'created_on' => '2026-10-01 08:00:00'],
            ],
        ]);

        $this->assertSame(50000, $result['subtotal']['masuk_tunai']);
        $this->assertSame('Setoran', $result['masuk']['tunai'][0]['keterangan']);
        $this->assertSame('Kas Masuk', $result['masuk']['tunai'][0]['sumber']);
    }

    public function testKasMasukTanpaDeskripsiDiberiLabelDefault(): void
    {
        $result = $this->service()->assemble([
            'kas_masuk' => [
                (object) ['deskripsi' => null, 'jumlah' => 15000, 'idbank' => '1', 'created_on' => null, 'updated_on' => '2026-10-01 09:00:00'],
            ],
        ]);

        $this->assertSame(15000, $result['subtotal']['masuk_transfer']);
        $this->assertSame('Kas masuk', $result['masuk']['transfer'][0]['keterangan']);
        $this->assertSame('09:00', $result['masuk']['transfer'][0]['waktu']);
    }
```

- [ ] **Step 2: Run test (expected: FAIL)**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/DailyCashFlowServiceTest.php
```

- [ ] **Step 3: Implement fetch and assemble**

Di `getForDate()`, tambahkan:

```php
            'kas_masuk' => $this->fetchKasMasuk($unitId, $tanggal),
```

Tambahkan method sebelum `fetchKasKeluar()`:

```php
    /**
     * Kas Masuk manual non-'kas awal' untuk tanggal+unit ini.
     *
     * `idbank` NULL = tunai, terisi = transfer. `jenis` tidak dipakai:
     * halaman Kas Masuk adalah kontrak inflow.
     */
    protected function fetchKasMasuk(int $unitId, string $tanggal): array
    {
        return $this->db->table('kas_masuk')
            ->select('kas_masuk.deskripsi, kas_masuk.jumlah, kas_masuk.penerima, kas_masuk.idbank, kas_masuk.created_on, kas_masuk.updated_on')
            ->where('kas_masuk.idunit', $unitId)
            ->where('DATE(kas_masuk.tanggal)', $tanggal)
            ->where('(kas_masuk.deskripsi IS NULL OR LOWER(TRIM(kas_masuk.deskripsi)) <> \'kas awal\')', null, false)
            ->orderBy('kas_masuk.tanggal', 'ASC')
            ->get()
            ->getResult();
    }
```

Di `assemble()`, tambahkan loop setelah loop `penjualan`/`service`/`dp_masuk` dan sebelum `kas_keluar`:

```php
        // Kas Masuk manual: inflow-only, tunai vs transfer dari idbank.
        // Filter 'kas awal' diulang di sini supaya assemble() tetap benar
        // saat dipanggil langsung dengan row mentah.
        foreach ($sources['kas_masuk'] ?? [] as $row) {
            $jumlah = (int) ($row->jumlah ?? 0);
            if ($jumlah === 0) {
                continue;
            }

            $deskripsi = trim((string) ($row->deskripsi ?? ''));
            if (strtolower($deskripsi) === 'kas awal') {
                continue;
            }

            $metode = $this->isTunai($row) ? 'tunai' : 'transfer';
            $masuk[$metode][] = $this->row(
                $this->waktu($row->created_on ?? null, $row->updated_on ?? null),
                'Kas Masuk',
                $deskripsi !== '' ? $deskripsi : 'Kas masuk',
                $jumlah
            );
        }
```

Perbarui docblock class `:26-34` dan docblock `fetchKasKeluar()` `:322-329`: `kas_masuk` sekarang dibaca, kecuali `kas awal`.

- [ ] **Step 4: Run test (expected: PASS)**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/DailyCashFlowServiceTest.php
```

- [ ] **Checkpoint:** drill-down menampilkan Kas Masuk tanpa dobel. Jangan commit.

---

### Task 6: Filter COA Kas Masuk di model

**Files:**
- Modify: `app/Models/ModelNoAkun.php`
- Test: `tests/unit/ModelNoAkunKasMasukTest.php` (baru)

**Interfaces:**
- Produces:
  - `getAkunMasuk(): array`
  - `bolehUntukKasMasuk(object $akun): bool`

- [ ] **Step 1: Write failing test**

Buat `tests/unit/ModelNoAkunKasMasukTest.php`:

```php
<?php

namespace Tests\unit;

use App\Models\ModelNoAkun;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ModelNoAkunKasMasukTest extends CIUnitTestCase
{
    private const TABEL = ['no_akun'];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
        }
        $this->db->query('CREATE TABLE no_akun (
            no_akun VARCHAR(20) NOT NULL PRIMARY KEY,
            nama_akun TEXT NULL,
            jenis_akun TEXT NULL)');

        $rows = [
            ['1000000000', 'Aset', 'Aset'],
            ['1010101000', 'Kas Besar', 'Kas & Bank'],
            ['1020000000', 'Piutang Usaha', 'Piutang'],
            ['2000000000', 'Hutang Usaha', 'Liabilitas'],
            ['3000000000', 'Modal', 'Ekuitas'],
            ['4000000000', 'Pendapatan', 'Pendapatan'],
            ['4100000000', 'Pendapatan & Beban Pajak Penghasilan', 'Pendapatan & Beban Pajak Penghasilan'],
            ['5000000000', 'HPP', 'Harga Pokok Penjualan'],
            ['6000000000', 'Beban Operasional', 'Beban Operasional'],
            ['6010000000', 'Beban Gaji', 'Beban Gaji'],
            ['1200000000', 'Akumulasi Penyusutan Aset', 'Akumulasi Penyusutan Aset'],
            ['1300000000', 'Tanpa Jenis', null],
            ['1400000000', 'Persediaan', 'Persediaan'],
        ];
        foreach ($rows as $r) {
            $this->db->table('no_akun')->insert([
                'no_akun' => $r[0], 'nama_akun' => $r[1], 'jenis_akun' => $r[2],
            ]);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $t) {
            $this->db->query("DROP TABLE IF EXISTS {$t}");
        }
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    public function testGetAkunMasukMenyaringAkunTerlarang(): void
    {
        $akun = (new ModelNoAkun())->getAkunMasuk();
        $nomor = array_map(static fn ($r) => (string) $r->no_akun, $akun);
        sort($nomor);

        $this->assertSame([
            '1000000000', '1020000000', '1400000000', '2000000000',
            '3000000000', '4000000000', '4100000000',
        ], $nomor);
    }

    public function testBolehUntukKasMasukKonsistenDenganQuery(): void
    {
        $model = new ModelNoAkun();

        $this->assertTrue($model->bolehUntukKasMasuk((object) ['jenis_akun' => 'Pendapatan']));
        $this->assertTrue($model->bolehUntukKasMasuk((object) ['jenis_akun' => 'Pendapatan & Beban Pajak Penghasilan']));
        $this->assertTrue($model->bolehUntukKasMasuk((object) ['jenis_akun' => 'Piutang']));

        $this->assertFalse($model->bolehUntukKasMasuk((object) ['jenis_akun' => null]));
        $this->assertFalse($model->bolehUntukKasMasuk((object) ['jenis_akun' => '']));
        $this->assertFalse($model->bolehUntukKasMasuk((object) ['jenis_akun' => 'Kas & Bank']));
        $this->assertFalse($model->bolehUntukKasMasuk((object) ['jenis_akun' => 'Beban Operasional']));
        $this->assertFalse($model->bolehUntukKasMasuk((object) ['jenis_akun' => ' beban gaji ']));
        $this->assertFalse($model->bolehUntukKasMasuk((object) ['jenis_akun' => 'Harga Pokok Penjualan']));
        $this->assertFalse($model->bolehUntukKasMasuk((object) ['jenis_akun' => 'Akumulasi Penyusutan Aset']));
    }
}
```

- [ ] **Step 2: Run test (expected: FAIL)**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/ModelNoAkunKasMasukTest.php
```

- [ ] **Step 3: Implement model methods**

Tambahkan ke `app/Models/ModelNoAkun.php`:

```php
    /**
     * Daftar akun yang boleh dipakai di form Kas Masuk.
     *
     * BLACKLIST, bukan whitelist: kategori `jenis_akun` baru tetap boleh
     * sampai ada alasan untuk menolaknya. Yang ditolak:
     *  - jenis_akun NULL/kosong (tidak bisa diklasifikasikan);
     *  - Kas & Bank (sisi kas/bank implisit);
     *  - Beban% (akun pengeluaran);
     *  - Harga Pokok Penjualan;
     *  - Akumulasi Penyusutan Aset.
     */
    public function getAkunMasuk(): array
    {
        return $this->where('jenis_akun IS NOT NULL', null, false)
            ->where("LOWER(TRIM(jenis_akun)) NOT IN ('kas & bank', 'harga pokok penjualan', 'akumulasi penyusutan aset')", null, false)
            ->where("LOWER(TRIM(jenis_akun)) NOT LIKE 'beban%'", null, false)
            ->findAll();
    }

    /** Predikat yang sama dengan getAkunMasuk(), untuk validasi server. */
    public function bolehUntukKasMasuk($akun): bool
    {
        if (! is_object($akun) || ! isset($akun->jenis_akun)) {
            return false;
        }

        $jenis = strtolower(trim((string) $akun->jenis_akun));
        if ($jenis === '') {
            return false;
        }
        if (in_array($jenis, ['kas & bank', 'harga pokok penjualan', 'akumulasi penyusutan aset'], true)) {
            return false;
        }
        if (strpos($jenis, 'beban') === 0) {
            return false;
        }

        return true;
    }
```

Jangan mengubah `getAkun()` atau `getByNoAkun()`.

- [ ] **Step 4: Run test (expected: PASS)**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/ModelNoAkunKasMasukTest.php
```

- [ ] **Checkpoint:** model menyaring akun tanpa mengubah halaman lain. Jangan commit.

---

### Task 7: Controller dan view Kas Masuk

**Files:**
- Modify: `app/Controllers/Kas_Masuk.php`
- Modify: `app/Views/jurnal/kas_masuk.php`
- Test: `tests/KasBankControllerTest.php` (tambah method)

**Interfaces:**
- Consumes: `ModelNoAkun::getAkunMasuk()` dan `bolehUntukKasMasuk()`.
- Produces: `jenis` selalu `kredit`; request dengan COA terlarang ditolak sebelum menulis.

- [ ] **Step 1: Add failing feature tests**

Tambahkan ke `tests/KasBankControllerTest.php` dekat test kas masuk yang ada:

```php
    public function testKasMasukMenolakCoaBebanTanpaMenulis(): void
    {
        $this->sesi();
        $this->seedNoAkun('5-2000', 'Beban Operasional', 'Beban Operasional');

        $r = $this->post('insert_kas_masuk', [
            'tanggal'     => $this->tglMulai,
            'deskripsi'   => 'Pengeluaran lewat kas masuk',
            'unit_idunit' => '1',
            'akun'        => [[
                'no_akun'             => '5-2000',
                'jumlah'              => '50000',
                'penerima'            => 'BNI-001',
                'no_rekening'         => 'BNI-001',
                'kategori_idkategori' => '1',
            ]],
        ]);

        $r->assertStatus(302);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) AS c FROM db_kas_masuk')->getRow()->c);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) AS c FROM db_jurnal')->getRow()->c);
        $this->assertStringContainsString('tidak boleh dipakai', $this->flashGagal());
    }

    public function testKasMasukMenyimpanJenisKreditTanpaPosisiDrk(): void
    {
        $this->sesi();
        $this->seedNoAkun();

        $r = $this->post('insert_kas_masuk', [
            'tanggal'     => $this->tglMulai,
            'deskripsi'   => 'Pendapatan lain',
            'unit_idunit' => '1',
            'akun'        => [[
                'no_akun'             => '5-1010',
                'jumlah'              => '50000',
                'penerima'            => 'BNI-001',
                'no_rekening'         => 'BNI-001',
                'kategori_idkategori' => '1',
            ]],
        ]);

        $r->assertStatus(302);
        $row = $this->db->query('SELECT * FROM db_kas_masuk')->getRow();
        $this->assertSame('kredit', $row->jenis);

        $jurnal = $this->db->query('SELECT * FROM db_jurnal')->getRow();
        $this->assertSame(0, (int) $jurnal->debet);
        $this->assertSame(50000, (int) $jurnal->kredit);
    }

    public function testKasMasukMenolakBatchCampuran(): void
    {
        $this->sesi();
        $this->seedNoAkun();
        $this->seedNoAkun('5-2000', 'Beban Operasional', 'Beban Operasional');

        $r = $this->post('insert_kas_masuk', [
            'tanggal'     => $this->tglMulai,
            'deskripsi'   => 'Campuran',
            'unit_idunit' => '1',
            'akun'        => [
                ['no_akun' => '5-1010', 'jumlah' => '10000', 'penerima' => 'BNI-001', 'no_rekening' => 'BNI-001', 'kategori_idkategori' => '1'],
                ['no_akun' => '5-2000', 'jumlah' => '20000', 'penerima' => 'BNI-001', 'no_rekening' => 'BNI-001', 'kategori_idkategori' => '1'],
            ],
        ]);

        $r->assertStatus(302);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) AS c FROM db_kas_masuk')->getRow()->c);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) AS c FROM db_jurnal')->getRow()->c);
    }

    public function testViewKasMasukTidakLagiMemilikiPosisiDrk(): void
    {
        $html = file_get_contents(APPPATH . 'Views/jurnal/kas_masuk.php');

        $this->assertStringNotContainsString('posisi_drk', $html);
        $this->assertStringNotContainsString('edit_posisi_drk', $html);
    }
```

Ubah `seedNoAkun()` agar menerima jenis akun:

```php
    private function seedNoAkun(string $no = '5-1010', string $nama = 'Pendapatan Operasional', string $jenis = 'Pendapatan'): void
    {
        $this->db->query(
            "INSERT INTO db_no_akun (no_akun, nama_akun, jenis_akun) VALUES ('{$no}', '{$nama}', '{$jenis}')"
        );
    }
```

- [ ] **Step 2: Run tests (expected: FAIL)**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/KasBankControllerTest.php --filter KasMasuk
```

- [ ] **Step 3: Implement controller**

`index()`:

```php
            'no_akun' =>  $this->NoAkunModel->getAkunMasuk(),
```

`insert_kas_masuk()`, setelah `$akunData = $this->request->getPost('akun');` dan sebelum `$db->transStart();`:

```php
        if (! is_array($akunData) || $akunData === []) {
            session()->setFlashdata('gagal', 'Minimal satu baris akun harus diisi.');
            return redirect()->to(base_url('/kas_masuk'));
        }

        // Validasi SEMUA baris sebelum transaksi: satu baris terlarang
        // membatalkan seluruh batch tanpa menulis apa pun.
        foreach ($akunData as $baris) {
            $noAkun = trim((string) ($baris['no_akun'] ?? ''));
            $akun   = $noAkun === '' ? null : $this->NoAkunModel->getByNoAkun($noAkun);

            if ($akun === null) {
                session()->setFlashdata('gagal', 'Akun "' . $noAkun . '" tidak terdaftar.');
                return redirect()->to(base_url('/kas_masuk'));
            }
            if (! $this->NoAkunModel->bolehUntukKasMasuk($akun)) {
                session()->setFlashdata(
                    'gagal',
                    'Akun "' . $noAkun . '" (' . ($akun->jenis_akun ?? 'tanpa jenis') . ') tidak boleh dipakai untuk Kas Masuk.'
                );
                return redirect()->to(base_url('/kas_masuk'));
            }
        }
```

Di dalam loop, ganti baris `$jenis = $data['posisi_drk']; // debet/kredit` menjadi:

```php
                // COA yang dipilih adalah akun lawan; sisi kas/bank didebit
                // implisit, jadi baris jurnal selalu kredit.
                $jenis = 'kredit';
```

`update_kas_masuk()`: hapus `$posisi_drk = $this->request->getPost('posisi_drk');` dan ganti `'jenis' => $posisi_drk,` menjadi `'jenis' => 'kredit',`.

- [ ] **Step 4: Implement view**

Di `app/Views/jurnal/kas_masuk.php`:

1. Hapus `<th style="width: fit-content;">Jenis</th>` pada tabel baris akun (sekitar `:168`).
2. Hapus blok `<td>` berisi `<select ... name="akun[${akunIndex}][posisi_drk]">` (sekitar `:396-402`).
3. Hapus blok modal edit:

```php
                    <div class="mb-3">
                        <label for="edit_posisi_drk" class="form-label">Posisi</label>
                        <select class="form-control" name="posisi_drk" id="edit_posisi_drk">
                            <option value="">-- Pilih --</option>
                            <option value="debet">Debet</option>
                            <option value="kredit">Kredit</option>
                        </select>
                    </div>
```

4. Hapus baris JS `document.getElementById('edit_posisi_drk').value = btn.dataset.jenis;`.
5. Jangan hapus kolom "Jenis" di tabel daftar (`:84`, `:101`); itu untuk melihat nilai historis.

- [ ] **Step 5: Run tests (expected: PASS)**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/KasBankControllerTest.php --filter KasMasuk
```

- [ ] **Step 6: Lint**

```bash
php -l app/Controllers/Kas_Masuk.php
php -l app/Views/jurnal/kas_masuk.php
```

- [ ] **Checkpoint:** form dan server konsisten. Jangan commit.

---

### Task 8: Schema test yang belum punya `kas_masuk`

**Files:**
- Modify: `tests/unit/TutupKasirDpTest.php`
- Modify: `tests/KasBankAlurSampaiTanggalTest.php`
- Modify: `tests/unit/RekonAntarUnitTest.php`
- Modify: `tests/unit/RekonDpTest.php`
- Modify: `tests/KasBankControllerTest.php` (`db_no_akun`)

**Interfaces:**
- Tidak ada method baru; hanya schema/seed.

- [ ] **Step 1: Add `kas_masuk` schema**

`tests/unit/TutupKasirDpTest.php` — tambahkan `'kas_masuk'` ke `TABEL` dan buat tabel:

```php
        $this->db->query('CREATE TABLE kas_masuk (
            idkas_masuk INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NULL, deskripsi TEXT NULL, jumlah BIGINT DEFAULT 0,
            idunit INT NULL, idbank VARCHAR(20) NULL, jenis VARCHAR(15) NULL)');
```

`tests/unit/RekonAntarUnitTest.php` dan `tests/unit/RekonDpTest.php` — sama, dengan prefix `''`.

`tests/KasBankAlurSampaiTanggalTest.php` — tambahkan `db_kas_masuk`:

```php
        $this->q('CREATE TABLE db_kas_masuk (
                idkas_masuk INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, deskripsi TEXT NULL, jumlah REAL NULL,
                idunit INT NULL, idbank TEXT NULL, jenis TEXT NULL)');
```

`tests/KasBankControllerTest.php` — `db_no_akun` sekarang memakai `CREATE TABLE IF NOT EXISTS`, jadi tabel lama tanpa `jenis_akun` akan bertahan. Tambahkan `'db_no_akun'` ke daftar DROP di `buatSkema()` (dekat `'db_jurnal'`), lalu ubah definisinya:

```php
            'db_jurnal',
            'db_no_akun',
```

```php
        $q('CREATE TABLE IF NOT EXISTS db_no_akun (
                no_akun TEXT NULL, nama_akun TEXT NULL, jenis_akun TEXT NULL)');
```

Tanpa DROP itu, test bisa gagal dengan `Unknown column 'jenis_akun'` pada run berikutnya.

- [ ] **Step 2: Run the four regression tests**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/TutupKasirDpTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/KasBankAlurSampaiTanggalTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/RekonAntarUnitTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/RekonDpTest.php
```

- [ ] **Checkpoint:** tidak ada "table not found". Jangan commit.

---

### Task 9: Audit seed `kas_masuk` lama

**Files:**
- Modify: `tests/KasBankTest.php` (bila perlu)
- Modify: `tests/KasBankControllerTest.php` (bila perlu)

**Interfaces:**
- Tidak ada.

- [ ] **Step 1: Audit**

Periksa seed berikut dan tentukan apakah assertion saldo/ringkasan terpengaruh:

- `tests/KasBankTest.php:234` (`testPostingKasMasukIdempotent`)
- `tests/KasBankTest.php:277` (`testPostingKasMasukGagalSaatAkunBelumDikonfigurasi`)
- `tests/KasBankTest.php:1141` (`Setoran bank tanpa rekening`)
- `tests/KasBankControllerTest.php:690` (`testUpdateKasMasukRefreshPostingDalamSatuTransaksi`)

Aturan:

- Bila test hanya menguji posting/ledger dan tidak menguji saldo, biarkan.
- Bila assertion saldo berubah karena baris non-`kas awal` sekarang dihitung, sesuaikan angka **atau** ubah deskripsi seed menjadi `kas awal` bila baris itu memang dimaksudkan inert.
- Jangan mengubah assertion yang sudah benar hanya agar test lulus.

- [ ] **Step 2: Run affected tests**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/KasBankTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/KasBankControllerTest.php
```

- [ ] **Checkpoint:** tidak ada test yang diubah tanpa alasan yang terdokumentasi. Jangan commit.

---

### Task 10: Dokumentasi PRD

**Files:**
- Modify: `docs/prd/modul/PRD_MODUL_05_Keuangan_KasBank_Aset.md`
- Modify: `docs/prd/PRD_MASTER.md` (hanya bila ringkasan lintas modul berubah)

- [ ] **Step 1: Update PRD 05**

Bagian 4.2:

- `kas_masuk` adalah sumber movement, bukan hanya baris ledger.
- `jenis` adalah posisi COA, bukan arah kas.
- Form dibatasi: tanpa `posisi_drk`, `jenis` selalu `kredit`, COA disaring.
- `idbank IS NULL` = tunai; `idbank` terisi = transfer.
- Baris `kas awal` dikecualikan.
- Laporan mentah `LabaRugi`/`DashboardKeuangan`/`DashboardLabaRugi` masih menghitung `kas awal`; dicatat sebagai keterbatasan.

Business rules: tambahkan aturan inflow-only, pengecualian `kas awal`, cakupan laporan, dan daftar COA yang ditolak.

- [ ] **Step 2: Cek PRD Master**

Hanya ubah `docs/prd/PRD_MASTER.md` bila ringkasan lintas modul menyebut `kas_masuk` secara berbeda. Jika tidak, catat alasannya di laporan akhir.

- [ ] **Checkpoint:** dokumentasi cocok dengan kode. Jangan commit.

---

### Task 11: Verifikasi akhir

- [ ] **Step 1: Lint semua file PHP yang diubah**

```bash
php -l app/Services/Finance/TutupKasirSourceDefinition.php
php -l app/Services/Finance/KasBankSourceMovement.php
php -l app/Services/Finance/RekonDailyCalculator.php
php -l app/Services/Finance/DailyCashFlowService.php
php -l app/Models/ModelNoAkun.php
php -l app/Controllers/Kas_Masuk.php
php -l app/Views/jurnal/kas_masuk.php
php -l tests/unit/TutupKasirKasMasukManualTest.php
php -l tests/unit/RekonKasMasukManualTest.php
php -l tests/unit/ModelNoAkunKasMasukTest.php
```

- [ ] **Step 2: Jalankan test terarah**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/TutupKasirKasMasukManualTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/RekonKasMasukManualTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/ModelNoAkunKasMasukTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/DailyCashFlowServiceTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/KasBankTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/KasBankControllerTest.php
```

- [ ] **Step 3: Jalankan regresi terkait**

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/TutupKasirDpTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/KasBankAlurSampaiTanggalTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/RekonAntarUnitTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/RekonDpTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/TutupKasirSaldoAwalTest.php
```

- [ ] **Step 4: Cek diff**

```bash
git diff --check
git status --short
git checkout -- build/logs 2>/dev/null || true
```

Pastikan hanya file yang direncanakan berubah.

- [ ] **Step 5: Laporkan hasil aktual**

Laporkan perintah yang dijalankan, output nyata, dan alasan bila test tidak bisa dijalankan. Jangan mengklaim lulus tanpa bukti.

- [ ] **Checkpoint:** pekerjaan siap untuk review user. **Jangan commit** sampai user menginstruksikan.

---

## Catatan Implementasi

- `TutupKasirSourceDefinition::memo()` sudah membatalkan cache saat ada query tulis (`ReadCache`), jadi aman dipakai di helper baru.
- `KAS_AWAL_SQL` sengaja memakai `deskripsi` tanpa qualifier; query helper hanya membaca satu tabel.
- `kasMasukManualTransferBankRange()` dengan `$unitId === null` adalah saldo fisik; jangan menambahkan guard entitlement di sana.
- `movementBank()` tetap menghitung rekening Finance/HO secara fisik; `posisiUnit()` tetap mengembalikan 0 untuk unit yang tidak entitled.
- `harianRange()` tidak diubah; grafik KPI Cash Flow tetap sales-only.
- Jangan menghidupkan kembali posting `transaksi_kas_bank` untuk `kas_masuk`.
