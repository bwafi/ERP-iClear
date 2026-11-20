# Split Payment Pembelian Lunas — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pembelian Lunas bisa dibayar dengan kombinasi tunai + transfer (termasuk beberapa rekening), dengan server menolak total yang kurang atau lebih.

**Architecture:** Service `PembelianPaymentSplit` menormalkan komponen pembayaran tanpa menyentuh database. `Pembelian::insert` memvalidasi total, menulis satu baris `pembayaran_bank` per rekening, satu baris `kas_keluar` per komponen, dan satu jurnal pembayaran per komponen. Tidak ada migrasi schema.

**Tech Stack:** PHP 7.4, CodeIgniter 4.4.8, MySQLi/MariaDB, jQuery, PHPUnit 9.

**Spec:** `docs/superpowers/specs/2026-11-20-pembelian-split-payment-design.md`

## Global Constraints

- Lunas wajib pas: total komponen harus sama dengan `total_transaksi`; kurang **dan** lebih ditolak.
- Tempo tidak menerima pembayaran di muka; tidak menulis `kas_keluar`.
- Nominal uang integer rupiah; gunakan `PembelianCalculator::parseRupiah()`.
- `metode_bayar` yang sah untuk Lunas: `tunai`, `transfer`, `tunai_transfer`.
- Satu rekening hanya boleh muncul sekali dalam satu transaksi.
- `pembelian.bank_idbank` = id rekening bila tepat satu bank, `NULL` bila nol/lebih dari satu.
- Route `insert_pembelian` tetap `auth` + `csrf`; form tetap membawa token.
- Jangan mengubah Penjualan, Tempo, stok, HPP, PPN, atau harga jual.
- Jangan menulis ulang data historis.
- **Jangan `git commit` sampai user mengizinkannya secara eksplisit.** Setiap langkah "Checkpoint" hanya menjalankan test.
- Test memakai database terpisah `erp_pembelian_test` di container `erp-app`.
- Setelah test, kembalikan `build/logs` bila berubah: `git checkout -- build/logs`.

## Review Focus

- Split lebih bayar (mis. tunai 100.000 + bank 200.000 untuk total 240.000) harus ditolak tanpa menyisakan header/detail/kas/jurnal.
- Dua baris bank dengan rekening yang sama harus ditolak, bukan menghasilkan dua baris `pembayaran_bank` untuk rekening yang sama.
- `metode_bayar = tunai` dengan baris bank nyelip: bank diabaikan, tunai tetap harus sama dengan total.
- `metode_bayar = tunai_transfer` dengan tunai saja (tanpa baris bank) harus diterima bila tunai sama dengan total.
- Tempo dengan `bayar`/`bank` terkirim harus mengabaikan semuanya: `sisa = total`, tanpa `kas_keluar`.

---

### Task 1: Service normalisasi split

**Files:**
- Create: `app/Services/Pembelian/PembelianPaymentSplit.php`
- Test: `tests/unit/PembelianPaymentSplitTest.php`

**Interfaces:**
- Consumes: `App\Services\Pembelian\PembelianCalculator::parseRupiah($value): ?int`
- Produces:
  - `App\Services\Pembelian\PembelianPaymentSplit::normalize(array $post): array`
  - Return: `array{cash:int, banks:list<array{id:string, jumlah:int}>, total:int, errors:list<string>}`
  - `$post` berisi `metode_bayar`, `bayar`, `bank` (array baris `['id' => string, 'jumlah' => string]`).

- [ ] **Step 1: Write the failing unit test**

Buat `tests/unit/PembelianPaymentSplitTest.php` dengan test berikut (nama dan assertion persis):

```php
<?php

namespace Tests\Unit;

use App\Services\Pembelian\PembelianPaymentSplit;
use CodeIgniter\Test\CIUnitTestCase;

final class PembelianPaymentSplitTest extends CIUnitTestCase
{
    private PembelianPaymentSplit $split;

    protected function setUp(): void
    {
        parent::setUp();
        $this->split = new PembelianPaymentSplit();
    }

    public function testTunaiPenuhTanpaBank(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'tunai',
            'bayar' => 'Rp 240.000',
        ]);

        $this->assertSame([], $hasil['errors']);
        $this->assertSame(240000, $hasil['cash']);
        $this->assertSame([], $hasil['banks']);
        $this->assertSame(240000, $hasil['total']);
    }

    public function testTransferSatuRekening(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'transfer',
            'bayar' => 'Rp 999.000',
            'bank' => [
                ['id' => '2', 'jumlah' => 'Rp 240.000'],
            ],
        ]);

        $this->assertSame([], $hasil['errors']);
        $this->assertSame(0, $hasil['cash'], 'tunai diabaikan saat transfer');
        $this->assertSame([['id' => '2', 'jumlah' => 240000]], $hasil['banks']);
        $this->assertSame(240000, $hasil['total']);
    }

    public function testTunaiDanDuaBank(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'tunai_transfer',
            'bayar' => 'Rp 50.000',
            'bank' => [
                ['id' => '2', 'jumlah' => 'Rp 100.000'],
                ['id' => '3', 'jumlah' => 'Rp 90.000'],
            ],
        ]);

        $this->assertSame([], $hasil['errors']);
        $this->assertSame(50000, $hasil['cash']);
        $this->assertSame(240000, $hasil['total']);
        $this->assertCount(2, $hasil['banks']);
    }

    public function testMetodeKosongDitolak(): void
    {
        $hasil = $this->split->normalize(['bayar' => 'Rp 240.000']);

        $this->assertNotSame([], $hasil['errors']);
        $this->assertStringContainsString('Metode pembayaran', $hasil['errors'][0]);
    }

    public function testNominalNegatifDitolak(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'tunai_transfer',
            'bayar' => 'Rp -1.000',
            'bank' => [
                ['id' => '2', 'jumlah' => 'Rp 240.000'],
            ],
        ]);

        $this->assertNotSame([], $hasil['errors']);
        $this->assertStringContainsString('tunai', $hasil['errors'][0]);
    }

    public function testBarisBankNolDilewatiDanTanpaRekeningDitolak(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'transfer',
            'bank' => [
                ['id' => '2', 'jumlah' => 'Rp 0'],
                ['id' => '', 'jumlah' => 'Rp 100.000'],
            ],
        ]);

        $this->assertStringContainsString('Rekening bank wajib dipilih', $hasil['errors'][0]);
    }

    public function testRekeningGandaDitolak(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'transfer',
            'bank' => [
                ['id' => '2', 'jumlah' => 'Rp 100.000'],
                ['id' => '2', 'jumlah' => 'Rp 140.000'],
            ],
        ]);

        $this->assertStringContainsString('lebih dari sekali', $hasil['errors'][0]);
    }

    public function testTanpaKomponenDitolak(): void
    {
        $hasil = $this->split->normalize([
            'metode_bayar' => 'tunai_transfer',
            'bayar' => 'Rp 0',
            'bank' => [],
        ]);

        $this->assertStringContainsString('minimal satu', $hasil['errors'][0]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker exec -w /app \
  -e database.tests.hostname=erp-db \
  -e database.tests.database=erp_pembelian_test \
  -e database.tests.username=erp \
  -e database.tests.password=erp \
  -e database.tests.DBDriver=MySQLi \
  -e database.tests.DBPrefix= \
  erp-app vendor/bin/phpunit -c phpunit.xml.dist tests/unit/PembelianPaymentSplitTest.php --no-coverage
```

Expected: FAIL — class `PembelianPaymentSplit` belum ada.

- [ ] **Step 3: Implement the service**

Buat `app/Services/Pembelian/PembelianPaymentSplit.php`:

```php
<?php

namespace App\Services\Pembelian;

/**
 * Normalisasi pembayaran pembelian Lunas: tunai, transfer, atau keduanya.
 * Service ini tidak menyentuh database; validasi rekening tetap di controller
 * lewat BankRekeningValidator.
 */
final class PembelianPaymentSplit
{
    private const METODE = ['tunai', 'transfer', 'tunai_transfer'];

    public function normalize(array $post): array
    {
        $metode = strtolower(trim((string) ($post['metode_bayar'] ?? '')));
        $errors = [];

        if (! in_array($metode, self::METODE, true)) {
            return $this->gagal('Metode pembayaran Lunas wajib dipilih: Tunai, Transfer, atau Tunai + Transfer.');
        }

        $pakaiTunai = $metode !== 'transfer';
        $pakaiBank = $metode !== 'tunai';

        $cash = 0;
        if ($pakaiTunai) {
            $cash = PembelianCalculator::parseRupiah($post['bayar'] ?? '0');
            if ($cash === null) {
                $errors[] = 'Nominal pembayaran tunai tidak valid.';
                $cash = 0;
            }
        }

        $banks = [];
        $terpakai = [];
        if ($pakaiBank) {
            $rows = $post['bank'] ?? [];
            if (! is_array($rows)) {
                $rows = [];
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $jumlah = PembelianCalculator::parseRupiah($row['jumlah'] ?? '0');
                if ($jumlah === null) {
                    $errors[] = 'Nominal pembayaran bank tidak valid.';
                    continue;
                }
                if ($jumlah <= 0) {
                    continue;
                }

                $id = trim((string) ($row['id'] ?? ''));
                if ($id === '') {
                    $errors[] = 'Rekening bank wajib dipilih untuk nominal transfer.';
                    continue;
                }
                if (in_array($id, $terpakai, true)) {
                    $errors[] = 'Rekening bank tidak boleh dipilih lebih dari sekali.';
                    continue;
                }

                $terpakai[] = $id;
                $banks[] = ['id' => $id, 'jumlah' => $jumlah];
            }
        }

        $total = $cash;
        foreach ($banks as $bank) {
            $total += $bank['jumlah'];
        }

        if ($total <= 0 && $errors === []) {
            $errors[] = 'Pembayaran Lunas wajib memiliki minimal satu komponen tunai atau transfer.';
        }

        return [
            'cash' => $cash,
            'banks' => $banks,
            'total' => $total,
            'errors' => $errors,
        ];
    }

    private function gagal(string $pesan): array
    {
        return ['cash' => 0, 'banks' => [], 'total' => 0, 'errors' => [$pesan]];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run command yang sama seperti Step 2. Expected: PASS (8 tests).

- [ ] **Step 5: Checkpoint**

`php -l app/Services/Pembelian/PembelianPaymentSplit.php`. Jangan commit.

---

### Task 2: Controller menulis split payment

**Files:**
- Modify: `app/Controllers/Pembelian.php` (blok pembayaran, header, `pembayaran_bank`, `kas_keluar`, jurnal)
- Test: `tests/PembelianHppUnitTest.php`

**Interfaces:**
- Consumes: `PembelianPaymentSplit::normalize(array): array` dari Task 1; `BankRekeningValidator::validateUntukUnit(?string, int, ?int): array{ok:bool, alasan:string, idbank:?string}`.
- Produces: `Pembelian::insert()` menulis `pembelian`, `detail_pembelian`, `pembayaran_bank`, `kas_keluar`, dan `jurnal` sesuai split.

- [ ] **Step 1: Tambah fixture bank kedua dan helper `$bayar`**

Di `tests/PembelianHppUnitTest.php`:

1. Pada `sebarData()`, setelah bank `'2'`, tambahkan bank `'3'` dan alokasinya ke unit 1 dan 2:

```php
$q("INSERT INTO bank (idbank, jenis_bank, nama_bank, atas_nama, norek)
    VALUES ('3', 'bank', 'Mandiri', 'ICLEAR DIGITAL SOLUSI CV', '1234567890')");
$q("INSERT INTO akun_kas_bank (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank,
        status, is_shared, is_finance_ho)
    VALUES (3, NULL, 'BANK', 'Mandiri CV', '3', 'aktif', 1, 0)");
$q("INSERT INTO alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal)
    VALUES (3, 1, 0), (3, 2, 0)");
```

2. Ubah signature `postPembelian` menjadi berakhir dengan `array $override = [], ?string $bayar = null`, dan ganti baris `'bayar' => 'Rp ' . number_format($total, ...)` menjadi:

```php
'bayar' => $bayar ?? 'Rp ' . number_format($total, 0, ',', '.'),
```

- [ ] **Step 2: Write failing feature tests**

Tambahkan test berikut di `tests/PembelianHppUnitTest.php`:

```php
public function testSplitTunaiDanSatuBank(): void
{
    $this->sesi();

    $this->postPembelian(
        120000.0, 2, 0.0, 0.0, '', '150000', null, null,
        'lunas', 'tunai_transfer',
        [['id' => '2', 'jumlah' => '140000']],
        false, [], 'Rp 100.000'
    )->assertRedirect();

    $p = $this->db->table('pembelian')->get()->getRow();
    $this->assertSame(100000, (int) $p->bayar_tunai);
    $this->assertSame(140000, (int) $p->bayar_bank);
    $this->assertSame(240000, (int) $p->total_bayar);
    $this->assertSame(0, (int) $p->sisa);
    $this->assertSame('Lunas', $p->status);
    $this->assertSame('2', (string) $p->bank_idbank);

    $this->assertSame(1, $this->db->table('pembayaran_bank')->countAllResults());
    $this->assertSame(140000, (int) $this->db->table('pembayaran_bank')->get()->getRow()->jumlah);

    $kas = $this->db->table('kas_keluar')->orderBy('idkas_keluar', 'ASC')->get()->getResult();
    $this->assertCount(2, $kas);
    $this->assertNull($kas[0]->idbank);
    $this->assertSame(100000, (int) $kas[0]->jumlah);
    $this->assertSame('2', (string) $kas[1]->idbank);
    $this->assertSame(140000, (int) $kas[1]->jumlah);

    $jurnal = $this->db->table('jurnal')
        ->where(['tabel_referensi' => 'pembayaran_bank'])->get()->getResult();
    $debet = 0; $kredit = 0;
    foreach ($jurnal as $row) { $debet += (int) $row->debet; $kredit += (int) $row->kredit; }
    $this->assertSame(240000, $debet);
    $this->assertSame(240000, $kredit);
}

public function testSplitTunaiDanDuaBankMengosongkanBankIdbank(): void
{
    $this->sesi();

    $this->postPembelian(
        120000.0, 2, 0.0, 0.0, '', '150000', null, null,
        'lunas', 'tunai_transfer',
        [
            ['id' => '2', 'jumlah' => '100000'],
            ['id' => '3', 'jumlah' => '90000'],
        ],
        false, [], 'Rp 50.000'
    )->assertRedirect();

    $p = $this->db->table('pembelian')->get()->getRow();
    $this->assertSame(50000, (int) $p->bayar_tunai);
    $this->assertSame(190000, (int) $p->bayar_bank);
    $this->assertNull($p->bank_idbank, 'multi-bank tidak punya satu id rekening');
    $this->assertSame(2, $this->db->table('pembayaran_bank')->countAllResults());
    $this->assertSame(3, $this->db->table('kas_keluar')->countAllResults());
}

public function testSplitKurangDitolakTanpaSisaData(): void
{
    $this->sesi();

    $this->postPembelian(
        120000.0, 2, 0.0, 0.0, '', '150000', null, null,
        'lunas', 'tunai_transfer',
        [['id' => '2', 'jumlah' => '100000']],
        false, [], 'Rp 50.000'
    )->assertRedirect();

    $this->assertSame(0, $this->db->table('pembelian')->countAllResults());
    $this->assertSame(0, $this->db->table('detail_pembelian')->countAllResults());
    $this->assertSame(0, $this->db->table('pembayaran_bank')->countAllResults());
    $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults());
    $this->assertSame(0, $this->db->table('jurnal')->countAllResults());
    $this->assertStringContainsString('kurang', (string) session()->getFlashdata('gagal'));
}

public function testSplitLebihDitolakTanpaSisaData(): void
{
    $this->sesi();

    $this->postPembelian(
        120000.0, 2, 0.0, 0.0, '', '150000', null, null,
        'lunas', 'tunai_transfer',
        [['id' => '2', 'jumlah' => '200000']],
        false, [], 'Rp 100.000'
    )->assertRedirect();

    $this->assertSame(0, $this->db->table('pembelian')->countAllResults());
    $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults());
    $this->assertStringContainsString('melebihi', (string) session()->getFlashdata('gagal'));
}

public function testRekeningGandaDitolak(): void
{
    $this->sesi();

    $this->postPembelian(
        120000.0, 2, 0.0, 0.0, '', '150000', null, null,
        'lunas', 'transfer',
        [
            ['id' => '2', 'jumlah' => '100000'],
            ['id' => '2', 'jumlah' => '140000'],
        ],
        false, [], 'Rp 0'
    )->assertRedirect();

    $this->assertSame(0, $this->db->table('pembelian')->countAllResults());
    $this->assertStringContainsString('lebih dari sekali', (string) session()->getFlashdata('gagal'));
}

public function testTunaiDenganBarisBankNyelipMengabaikanBank(): void
{
    $this->sesi();

    $this->postPembelian(
        120000.0, 2, 0.0, 0.0, '', '150000', null, null,
        'lunas', 'tunai',
        [['id' => '2', 'jumlah' => '240000']],
        false, [], 'Rp 240.000'
    )->assertRedirect();

    $p = $this->db->table('pembelian')->get()->getRow();
    $this->assertSame(240000, (int) $p->bayar_tunai);
    $this->assertSame(0, (int) $p->bayar_bank);
    $this->assertSame(0, $this->db->table('pembayaran_bank')->countAllResults());
    $this->assertSame(1, $this->db->table('kas_keluar')->countAllResults());
}

public function testTunaiTransferDenganTunaiSajaDiterimaBilaPas(): void
{
    $this->sesi();

    $this->postPembelian(
        120000.0, 2, 0.0, 0.0, '', '150000', null, null,
        'lunas', 'tunai_transfer', [], false, [], 'Rp 240.000'
    )->assertRedirect();

    $p = $this->db->table('pembelian')->get()->getRow();
    $this->assertSame(240000, (int) $p->bayar_tunai);
    $this->assertSame(0, (int) $p->bayar_bank);
    $this->assertSame(1, $this->db->table('kas_keluar')->countAllResults());
}

public function testTempoMengabaikanNominalPembayaran(): void
{
    $this->sesi();

    $this->postPembelian(
        120000.0, 2, 0.0, 0.0, '', '150000', null, '2026-12-31',
        'tempo', 'tunai_transfer',
        [['id' => '2', 'jumlah' => '240000']],
        false, [], 'Rp 240.000'
    )->assertRedirect();

    $p = $this->db->table('pembelian')->get()->getRow();
    $this->assertSame(240000, (int) $p->sisa);
    $this->assertSame('Belum Lunas', $p->status);
    $this->assertSame(0, (int) $p->total_bayar);
    $this->assertSame(0, $this->db->table('pembayaran_bank')->countAllResults());
    $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults());
}
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
docker exec -w /app \
  -e database.tests.hostname=erp-db \
  -e database.tests.database=erp_pembelian_test \
  -e database.tests.username=erp \
  -e database.tests.password=erp \
  -e database.tests.DBDriver=MySQLi \
  -e database.tests.DBPrefix= \
  erp-app vendor/bin/phpunit -c phpunit.xml.dist tests/PembelianHppUnitTest.php --no-coverage
```

Expected: FAIL pada test split (controller masih menolak `tunai_transfer` atau tidak menulis baris ganda).

- [ ] **Step 4: Implement controller changes**

Di `app/Controllers/Pembelian.php`:

1. Tambah `use App\Services\Pembelian\PembelianPaymentSplit;`.
2. Ubah validasi metode menjadi tiga nilai:
   `in_array($metodeBayar, ['tunai', 'transfer', 'tunai_transfer'], true)`.
3. Ganti seluruh blok normalisasi pembayaran (dari `$bankData = ...` sampai sebelum komentar kategori persediaan) dengan:

```php
$bankTervalidasi = [];
$bayar_tunai = 0;
$totalBayarBank = 0;
$idbankPembayaran = null;

if ($jenisPembayaran === 'tempo') {
    $sisa = $total_harga;
} else {
    $split = (new PembelianPaymentSplit())->normalize($this->request->getPost());
    if ($split['errors'] !== []) {
        return $gagal($split['errors'][0]);
    }

    if ($split['total'] !== $total_harga) {
        $selisih = $total_harga - $split['total'];
        return $gagal($selisih > 0
            ? 'Pembayaran Lunas kurang Rp ' . number_format($selisih, 0, ',', '.') . '. Lunas wajib dibayar penuh.'
            : 'Pembayaran Lunas melebihi total Rp ' . number_format(-$selisih, 0, ',', '.') . '.');
    }

    foreach ($split['banks'] as $baris) {
        $cek = (new BankRekeningValidator())->validateUntukUnit($baris['id'], $useridunit, $role);
        if (! $cek['ok']) {
            return $gagal('Rekening pembayaran tidak valid: ' . $cek['alasan']);
        }
        $bankTervalidasi[] = [
            'id' => (string) $cek['idbank'],
            'jumlah' => (int) $baris['jumlah'],
        ];
    }

    $bayar_tunai = (int) $split['cash'];
    foreach ($bankTervalidasi as $baris) {
        $totalBayarBank += $baris['jumlah'];
    }
    $sisa = 0;
    $idbankPembayaran = count($bankTervalidasi) === 1 ? $bankTervalidasi[0]['id'] : null;
}
```

4. Pastikan `$total_bayar = $bayar_tunai + $totalBayarBank;` dan `$status` tetap seperti sekarang.
5. Ganti blok insert `pembayaran_bank` menjadi loop:

```php
if ($jenisPembayaran === 'lunas') {
    foreach ($bankTervalidasi as $baris) {
        $tersimpan = $this->PembayaranBankModel->insertPembayaranBank([
            'kode_pembayaran' => $kodePembayaran,
            'bank_idbank' => $baris['id'],
            'jumlah' => $baris['jumlah'],
            'tabel_referensi' => 'pembelian',
            'id_referensi' => $idPembelian,
        ]);
        if (! $tersimpan) {
            return $gagal('Pembayaran bank gagal disimpan.');
        }
    }
}
```

6. Ganti blok satu baris `kas_keluar` menjadi loop komponen:

```php
if ($jenisPembayaran === 'lunas' && (int) $total_bayar > 0) {
    $penerimaKasKeluar = $this->namaPihakPembelian(
        $id_suplier_text,
        $this->request->getPost('pelanggan'),
        $nama
    );

    $komponenKas = [];
    if ($bayar_tunai > 0) {
        $komponenKas[] = ['jumlah' => $bayar_tunai, 'idbank' => null];
    }
    foreach ($bankTervalidasi as $baris) {
        $komponenKas[] = ['jumlah' => $baris['jumlah'], 'idbank' => $baris['id']];
    }

    foreach ($komponenKas as $komponen) {
        $tersimpan = $this->KasKeluarModel->insert_KasKeluar([
            'tanggal' => $tanggalMasuk,
            'kategori_idkategori' => $this->idKategoriKasHpp($db),
            'deskripsi' => 'Pembelian ' . $no_nota . ' - ' . $penerimaKasKeluar,
            'jumlah' => (int) $komponen['jumlah'],
            'penerima' => $penerimaKasKeluar,
            'idunit' => $useridunit,
            'jenis' => 'debet',
            'idbank' => $komponen['idbank'],
            'created_on' => date('Y-m-d H:i:s'),
            'no_akun' => self::AKUN_PERSEDIAAN,
        ]);
        if (! $tersimpan) {
            return $gagal('Kas keluar pembelian gagal disimpan.');
        }
    }
}
```

7. Ganti blok rencana jurnal pembayaran menjadi per komponen:

```php
if ($jenisPembayaran === 'lunas') {
    if ($bayar_tunai > 0) {
        $tambahJurnal('pembelian_lunas_tunai', $bayar_tunai, 'Pembayaran Tunai - Pembelian', 'pembayaran');
    }
    foreach ($bankTervalidasi as $baris) {
        $templateBank = 'pembelian_lunas_bank_' . $baris['id'];
        if ($this->JurnalModel->templateRows($templateBank) === []) {
            $templateBank = 'pembelian_lunas_bank';
        }
        $tambahJurnal($templateBank, $baris['jumlah'], 'Pembayaran Bank - Pembelian', 'pembayaran');
    }
}
```

8. Header `pembelian`: `'bank_idbank' => $idbankPembayaran`, `'bayar_tunai' => $bayar_tunai`, `'bayar_bank' => $totalBayarBank`, `'total_bayar' => $total_bayar`, `'sisa' => $sisa`, `'status' => $status`.

- [ ] **Step 5: Run tests to verify they pass**

Run command Step 3. Expected: PASS untuk seluruh `PembelianHppUnitTest`, termasuk test lama (tunai, transfer, tempo, jurnal, CSRF).

- [ ] **Step 6: Checkpoint**

`php -l app/Controllers/Pembelian.php`; `git diff --check`. Jangan commit.

---

### Task 3: UI split dan page tests

**Files:**
- Modify: `app/Views/transaksi/pembelian.php`
- Test: `tests/PembelianPageTest.php`

**Interfaces:**
- Consumes: nama field `metode_bayar`, `bayar`, `bank[i][id]`, `bank[i][jumlah]`.
- Produces: opsi `tunai_transfer`, input tunai/bank editable, status `#pb-pay-status`, guard submit.

- [ ] **Step 1: Write failing page tests**

Tambahkan di `tests/PembelianPageTest.php`:

```php
public function testMetodePembayaranMendukungTunaiTransfer(): void
{
    $html = $this->render();

    $this->assertMatchesRegularExpression(
        '/<select[^>]*id="metode_bayar"[^>]*>.*?<\/select>/s',
        $html
    );
    preg_match('/<select[^>]*id="metode_bayar"[^>]*>.*?<\/select>/s', $html, $m);
    $this->assertStringContainsString('value="tunai_transfer"', $m[0]);
    $this->assertStringContainsString('Tunai + Transfer', $m[0]);
}

public function testInputTunaiDanBankTidakReadonly(): void
{
    $html = $this->render();

    preg_match('/<input[^>]*id="bayar"[^>]*>/', $html, $m);
    $this->assertNotEmpty($m);
    $this->assertStringNotContainsString('readonly', $m[0], 'nominal tunai harus bisa diketik');

    $this->assertStringNotContainsString(
        'class="form-control bank-amount" name="bank[${index}][jumlah]" value="Rp 0" readonly',
        $html,
        'nominal bank harus bisa diketik'
    );
}

public function testAdaStatusPembayaranDanGuardSubmit(): void
{
    $html = $this->render();

    $this->assertStringContainsString('id="pb-pay-status"', $html);
    $this->assertStringContainsString('Pembayaran Lunas harus tepat', $html);
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker exec -w /app \
  -e database.tests.hostname=erp-db \
  -e database.tests.database=erp_pembelian_test \
  -e database.tests.username=erp \
  -e database.tests.password=erp \
  -e database.tests.DBDriver=MySQLi \
  -e database.tests.DBPrefix= \
  erp-app vendor/bin/phpunit -c phpunit.xml.dist tests/PembelianPageTest.php --no-coverage
```

Expected: FAIL pada tiga test baru.

- [ ] **Step 3: Implement UI**

Di `app/Views/transaksi/pembelian.php`:

1. Tambah opsi di select `metode_bayar`:
   `<option value="tunai_transfer">Tunai + Transfer</option>`.
2. Hapus `readonly` dari input `#bayar`; tambah `autocomplete="off"` dan `inputmode="numeric"`.
3. Di `transfer-section`, tambah tombol `<button type="button" ... id="tambah-bank">Tambah Bank</button>` (hanya jika `$bank` tidak kosong) dan elemen status:
   `<p class="pb-pay-status" id="pb-pay-status" aria-live="polite"></p>`.
4. Di template baris bank (`tambahBarisBank`), hapus `readonly` pada `.bank-amount`, tambah tombol hapus:
   `<button type="button" class="pb-row-del hapus-bank" aria-label="Hapus rekening">×</button>`.
5. Tambah event listener:
   - `.bank-amount` input → format rupiah + `updateHutang()`.
   - `.hapus-bank` click → hapus baris + `updateHutang()`.
   - `#tambah-bank` click → `tambahBarisBank()`.
6. Ubah `aturPembayaran()`:
   - `tunai`: tunai-section tampil, transfer-section sembunyi, kosongkan bank, `#bayar` = total.
   - `transfer`: tunai-section sembunyi, transfer-section tampil, `#bayar` = 0, pastikan satu baris bank, isi bank pertama = total.
   - `tunai_transfer`: dua section tampil, pastikan satu baris bank, `#bayar` = max(total - total bank, 0).
7. Ubah `sinkronPembayaranOtomatis()`: untuk `tunai_transfer` jangan menimpa input user; hanya pastikan baris bank ada.
8. Tambah `updateStatusPembayaran()` dan panggil dari `updateHutang()`:
   - `jenis != lunas` → status kosong.
   - `hutang > 0` → `"Kurang Rp X"`.
   - `kembalian > 0` → `"Lebih Rp X"`.
   - selain itu → `"Pas"`.
9. Di submit listener, sebelum guard produk, tambah:

```js
const jenis = document.getElementById('jenis_pembayaran').value;
if (jenis === 'lunas') {
    const metode = document.getElementById('metode_bayar').value;
    const total = totalPembelian();
    let dibayar = unformatRupiah(document.getElementById('bayar').value || 'Rp 0');
    document.querySelectorAll('.bank-amount').forEach(input => {
        dibayar += unformatRupiah(input.value || 'Rp 0');
    });

    if (!metode) {
        e.preventDefault();
        alert('Pilih metode pembayaran Lunas.');
        return false;
    }
    if (dibayar !== total) {
        e.preventDefault();
        alert('Pembayaran Lunas harus tepat Rp ' + total.toLocaleString('id-ID') + '.');
        return false;
    }
}
```

- [ ] **Step 4: Run page tests to verify they pass**

Run command Step 2. Expected: PASS seluruh `PembelianPageTest`.

- [ ] **Step 5: Checkpoint**

`php -l app/Views/transaksi/pembelian.php`; `git diff --check`. Jangan commit.

---

### Task 4: Perbarui PRD

**Files:**
- Modify: `docs/prd/modul/PRD_MODUL_03_Pembelian_Persediaan.md`
- Modify: `docs/prd/modul/PRD_MODUL_05_Keuangan_KasBank_Aset.md`

**Interfaces:** tidak ada kode baru; dokumentasi mengikuti perilaku Task 1–3.

- [ ] **Step 1: Perbarui aturan Modul 03**

- Aturan 6: ganti "tepat satu rekening bank" menjadi "satu atau beberapa rekening bank; tunai dan transfer boleh digabung".
- Aturan 8: ganti "satu baris `kas_keluar`" menjadi "satu baris per komponen: tunai `idbank NULL`, tiap rekening bank `idbank` sendiri".
- Aturan 20: tulis ulang: Lunas menerima `tunai`, `transfer`, `tunai_transfer`; total komponen wajib tepat sama; kurang/lebih ditolak; `bank_idbank` hanya diisi bila tepat satu rekening.
- Acceptance criteria: tambah split tunai+transfer, tolak kurang/lebih, `kas_keluar` per komponen, jurnal per komponen.
- Verification status: tambah bukti test `PembelianHppUnitTest`, `PembelianPageTest`, `PembelianPaymentSplitTest`.

- [ ] **Step 2: Perbarui aturan Modul 05**

Pada aturan 27, jelaskan bahwa pembelian Lunas split menulis beberapa baris `kas_keluar` (satu per metode) dan beberapa jurnal pembayaran, semuanya seimbang per grup referensi.

- [ ] **Step 3: Checkpoint dokumentasi**

```bash
git diff --check
```

Expected: tidak ada output.

---

## Self-Review Checklist (sudah dijalankan)

- [x] Setiap requirement spec punya task: service (Task 1), controller (Task 2), UI (Task 3), PRD (Task 4).
- [x] Interface antar-task konsisten: `normalize()` mengembalikan `cash`, `banks`, `total`, `errors`.
- [x] Review Focus punya test di Task 2.
- [x] Tidak ada migrasi schema.
- [x] Commit ditunda sampai user mengizinkan.
