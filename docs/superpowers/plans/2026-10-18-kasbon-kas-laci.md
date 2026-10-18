# Kasbon Mengurangi Kas Laci — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pencairan kasbon pegawai membuat satu baris `kas_keluar` (tunai atau transfer) sehingga kasbon otomatis muncul di Kas & Bank, arus kas harian, dan Tutup Kasir — tanpa dobel jurnal.

**Architecture:** Satu baris `kas_keluar` disisipkan di dalam transaksi `HutangPiutangService::createPosition()` (hanya untuk `sumber_tipe = kasbon`), lalu id-nya disimpan di `hutang_piutang.kas_keluar_id`. Tiga modul kas sudah membaca `kas_keluar` apa adanya. Edit/hapus baris kas disinkronkan satu arah ke kasbon lewat `Kas_Keluar` controller.

**Tech Stack:** PHP (CodeIgniter 4), MySQL (`erp_local`), PHPUnit 9 untuk test suite, `app/Scripts/*.php` untuk smoke test modul Hutang Piutang.

**Spec:** `docs/superpowers/specs/2026-10-18-kasbon-kas-laci-design.md`

## Global Constraints

- Baris `kas_keluar` yang dibuat otomatis **TIDAK BOLEH** memposting jurnal (jangan panggil `JurnalModel`). Jurnal kasbon tetap `hp_kasbon_input` (tunai) / `hp_kasbon_input_bank` (transfer) — satu pasang saja.
- `kas_keluar.idbank IS NULL` = tunai (laci KAS); terisi = transfer (BANK). Kolomnya VARCHAR — **jangan** di-cast ke int.
- Kategori kas baru bernama tepat `Kasbon`, `jenis_kas = 'kas_keluar'`. **Jangan** pakai kategori 10 (SDM) — "Ka**sbon**" akan nyangkut filter `LIKE '%bon%'` di slip gaji.
- Transfer divalidasi `BankRekeningValidator::validateUntukUnit($idbank, $unitId, null, false)`; gagal → kasbon ditolak, tidak ada baris tersimpan.
- Semua penulisan kasbon + kas terjadi di dalam transaksi DB yang sudah ada (`createPosition`, `sync`, `hapus`).
- Kasbon **tidak** punya pelunasan manual (potong gaji, potong penuh). Tidak ada `kas_masuk`. Tidak ada cron/penjadwal baru.
- Akses edit/hapus baris kas tidak diubah (tetap admin root).
- Kasbon lama **tidak** di-backfill.
- Nilai tetap: `no_akun = '1020201000'` (Piutang Pegawai), `jenis = 'debet'`, `penerima = ID_AKUN` pegawai.

## Review Focus

- Kasbon `total` 0 / negatif → harus ditolak sebelum baris apa pun dibuat (tidak ada `kas_keluar`, tidak ada jurnal).
- Transfer ke rekening yang bukan hak unit → ditolak, kasbon tidak tersimpan separuh.
- Edit `jumlah` baris kas sampai di bawah `total_dibayar` → ditolak, angka tidak berubah.
- Hapus baris kas untuk kasbon yang sudah lunas/berpotongan gaji → ditolak, jurnal tetap utuh.
- Kategori `Kasbon` belum ada (DB belum migrate) → kasbon gagal dengan pesan jelas, bukan tersimpan tanpa kas.

---

### Task 1: Migrasi — kolom link, kategori "Kasbon", template jurnal bank

**Files:**
- Create: `app/Database/Migrations/2026-10-18-000100_KasbonKasLaci.php`

**Interfaces:**
- Consumes: —
- Produces: kolom `hutang_piutang.kas_keluar_id` (INT NULL), `hutang_piutang.metode` (ENUM 'tunai','transfer' NULL), `hutang_piutang.bank_idbank` (VARCHAR 20 NULL); baris `kategori_kas` (`kategori='Kasbon'`, `jenis_kas='kas_keluar'`); template jurnal `hp_kasbon_input_bank`.

- [x] **Step 1: Tulis migrasi**

Ikuti pola idempotent `app/Database/Migrations/2026-10-14-000200_AddServiceDpRefund.php` (guard `fieldExists`, seed hanya bila belum ada, `down()` menolak hapus bila sudah ada data).

```php
class KasbonKasLaci extends Migration
{
    private const TEMPLATE_BANK = [
        ['1020201000', 'Piutang Pegawai', 'debet'],
        ['1010102000', 'Kas di Bank (Cash in Bank)', 'kredit'],
    ];

    public function up()
    {
        // 1) kolom link + metode
        foreach ([
            'kas_keluar_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'metode'        => ['type' => 'ENUM', 'constraint' => ['tunai', 'transfer'], 'null' => true],
            'bank_idbank'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
        ] as $name => $def) {
            if (! $this->db->fieldExists($name, 'hutang_piutang')) {
                $this->forge->addColumn('hutang_piutang', [$name => $def]);
            }
        }

        // 2) kategori kas "Kasbon"
        $ada = $this->db->table('kategori_kas')
            ->where('kategori', 'Kasbon')->where('jenis_kas', 'kas_keluar')->countAllResults();
        if ($ada === 0) {
            $this->db->table('kategori_kas')->insert([
                'kategori' => 'Kasbon', 'kode_template_jurnal' => null, 'jenis_kas' => 'kas_keluar',
            ]);
        }

        // 3) template jurnal transfer -> kredit Kas di Bank
        foreach (self::TEMPLATE_BANK as $row) {
            $exists = $this->db->table('template_jurnal')
                ->where('kode_template', 'hp_kasbon_input_bank')
                ->where('no_akun', $row[0])->where('debet_kredit', $row[2])
                ->where('array_value', 0)->countAllResults();
            if ($exists === 0) {
                $this->db->table('template_jurnal')->insert([
                    'kode_template' => 'hp_kasbon_input_bank', 'no_akun' => $row[0],
                    'nama_akun' => $row[1], 'debet_kredit' => $row[2],
                    'array_value' => 0, 'keterangan' => 'Hutang Piutang',
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('hutang_piutang')
            && $this->db->fieldExists('kas_keluar_id', 'hutang_piutang')
            && $this->db->table('hutang_piutang')->where('kas_keluar_id IS NOT NULL', null, false)->countAllResults() > 0) {
            throw new \RuntimeException('Kasbon sudah tertaut ke kas; migrasi tidak bisa di-rollback.');
        }
        // drop 3 kolom, delete kategori "Kasbon", delete kode_template hp_kasbon_input_bank
    }
}
```

- [x] **Step 2: Jalankan migrasi**

Run: `php74 spark migrate`
Expected: `KasbonKasLaci` sukses, tanpa error.

- [x] **Step 3: Verifikasi di DB**

Run:
```bash
mysql -uroot erp_local -e "SHOW COLUMNS FROM hutang_piutang LIKE 'kas_keluar_id'; \
SELECT idkategori_kas,kategori FROM kategori_kas WHERE kategori='Kasbon'; \
SELECT no_akun,debet_kredit FROM template_jurnal WHERE kode_template='hp_kasbon_input_bank';"
```
Expected: kolom ada; 1 baris `Kasbon`; 2 baris template (1020201000 debet, 1010102000 kredit).

- [x] **Step 4: Idempotency check**

Run: `php74 spark migrate:rollback` lalu `php74 spark migrate` ulang (selagi belum ada kasbon tertaut).
Expected: tidak ada error; hasil Step 3 sama.

- [x] **Step 5: Commit**

```bash
git add app/Database/Migrations/2026-10-18-000100_KasbonKasLaci.php
git commit -m "feat(kasbon): migrasi link kas_keluar, kategori Kasbon, template jurnal bank"
```

---

### Task 2: Input kasbon membuat baris `kas_keluar` (tunai & transfer)

**Files:**
- Modify: `app/Services/Finance/HutangPiutangService.php` (konstanta + 3 helper baru; ganti blok jurnal kasbon di `createPosition()` sekitar L337)
- Modify: `app/Controllers/HutangPiutang.php` (`store()` ~L144-156)
- Modify: `app/Views/hutangpiutang/_input_form.php` (field metode/bank di dalam `#hp-input-form`, JS `sync()` ~L162)
- Modify: `app/Views/hutangpiutang/form.php` (L15-24) dan `app/Views/hutangpiutang/index.php` (L187-197) — teruskan `'bank' => $bank ?? []`
- Modify: `app/Views/hutangpiutang/index.php` (L150) dan `detail.php` (L97) — sembunyikan tombol Bayar untuk kasbon

**Interfaces:**
- Consumes: kolom + kategori dari Task 1.
- Produces (di `HutangPiutangService`):
  - `public const KATEGORI_KASBON = 'Kasbon';`
  - `public const TEMPLATE_KASBON_INPUT_TUNAI = 'hp_kasbon_input';`
  - `public const TEMPLATE_KASBON_INPUT_BANK = 'hp_kasbon_input_bank';`
  - `public const NO_AKUN_PIUTANG_PEGAWAI = '1020201000';`
  - `private function resolveKategoriKasbonId(): int` — throw `\RuntimeException('Kategori kas "Kasbon" belum tersedia.')` bila tidak ada.
  - `private function templateKasbonInput(string $metode): string`
  - `private function insertKasKeluarKasbon(int $unitId, string $tanggal, int $jumlah, string $metode, ?string $idbank, string $deskripsi, int $penerimaAkun): int`

- [x] **Step 1: Tambah konstanta & helper di service**

`insertKasKeluarKasbon()`: bila `$metode === 'transfer'`, panggil `(new BankRekeningValidator())->validateUntukUnit($idbank, $unitId, null, false)`; `!ok` → `throw new \RuntimeException($cek['alasan'])`. Lalu:

```php
$this->db->table('kas_keluar')->insert([
    'tanggal'             => $tanggal,
    'kategori_idkategori' => $this->resolveKategoriKasbonId(),
    'deskripsi'           => $deskripsi,
    'jumlah'              => $jumlah,
    'penerima'            => (string) $penerimaAkun,
    'idunit'              => $unitId,
    'jenis'               => 'debet',
    'idbank'              => $metode === 'transfer' ? $cek['idbank'] : null,
    'no_akun'             => self::NO_AKUN_PIUTANG_PEGAWAI,
    'created_on'          => date('Y-m-d H:i:s'),
    'updated_on'          => date('Y-m-d H:i:s'),
]);
return (int) $this->db->insertID();
```

`use App\Services\Finance\BankRekeningValidator;` ada di namespace yang sama — tidak perlu import.

- [x] **Step 2: Ganti blok jurnal kasbon di `createPosition()`**

Ganti baris `if ($sumberTipe === self::SUMBER_KASBON) { $this->postJurnal(... 'hp_kasbon_input' ...); }` dengan:

```php
if ($sumberTipe === self::SUMBER_KASBON) {
    $metode  = (($in['metode'] ?? 'tunai') === 'transfer') ? 'transfer' : 'tunai';
    $idbank  = $metode === 'transfer' ? trim((string) ($in['bank_idbank'] ?? '')) : null;
    $kasId   = $this->insertKasKeluarKasbon(
        $unitId, $tanggal, $total, $metode, $idbank,
        'Kasbon ' . $kode . ' - ' . $namaPihak, $pihakId
    );
    $this->hp->update((int) $id, [
        'kas_keluar_id' => $kasId,
        'metode'        => $metode,
        'bank_idbank'   => $idbank,
    ]);
    $this->postJurnal($tanggal, $this->templateKasbonInput($metode), $total,
        'Kasbon Pegawai: ' . $kode, (int) $id, 'hutang_piutang', $unitId, $inputBy);
}
```

Jangan panggil `postingKasKeluar` — baris operasional dibaca langsung dari `kas_keluar`.

- [x] **Step 3: Teruskan input dari controller `store()`**

Tambah dua baris ke array `createPosition([...])`:

```php
'metode'      => (string) $this->request->getPost('metode'),
'bank_idbank' => $this->request->getPost('bank_idbank'),
```

- [x] **Step 4: Tambah field metode + bank di `_input_form.php`**

Tambah satu kolom (setelah blok `wrap-jatuh-tempo`, sebelum Total) yang tampil hanya untuk kasbon:

```php
<div class="col-12 d-none" id="wrap-metode">
    <label class="form-label">Metode Pencairan</label>
    <select name="metode" id="metode" class="form-select">
        <option value="tunai">Tunai (laci kas)</option>
        <option value="transfer">Transfer bank</option>
    </select>
    <select name="bank_idbank" id="bank_idbank" class="form-select mt-2 d-none">
        <option value="">-- Pilih Rekening --</option>
        <?php foreach (($bank ?? []) as $b) : ?>
            <option value="<?= esc($b->idbank) ?>"><?= esc($b->nama_bank . ' - ' . $b->norek) ?></option>
        <?php endforeach; ?>
    </select>
</div>
```

Di JS `sync()` (bila `kasbon`), tambahkan: `wrap-metode` muncul, `#bank_idbank` muncul + `required` saat metode = transfer. Tambahkan listener `metode` → toggle rekening + required.

- [x] **Step 5: Kirim opsi bank ke partial**

`form()` controller: tambah `'bank' => $this->service->getBankOptions(),`. Di `form.php` dan `index.php`, tambah `'bank' => $bank ?? [],` pada argumen `view('hutangpiutang/_input_form', [...])`.

- [x] **Step 6: Sembunyikan tombol "Bayar" untuk kasbon**

`index.php:150` → `<?php if ($authoritative && $canInput && $row['status'] !== 'lunas' && $row['sumber_tipe'] !== 'kasbon') : ?>`
`detail.php:97` → `<?php if ($can_input && $authoritative && $row->status !== 'lunas' && $row->sumber_tipe !== 'kasbon') : ?>`

- [x] **Step 7: Verifikasi manual cepat**

Run: muat `/hutangpiutang/form` (masih di dev). Pilih kategori "Kasbon Pegawai" → field Metode muncul; pilih Transfer → dropdown rekening muncul & wajib.
Expected: kategori lain tidak menampilkan field metode.

- [x] **Step 8: Commit**

```bash
git add app/Services/Finance/HutangPiutangService.php app/Controllers/HutangPiutang.php \
        app/Views/hutangpiutang/_input_form.php app/Views/hutangpiutang/form.php \
        app/Views/hutangpiutang/index.php app/Views/hutangpiutang/detail.php
git commit -m "feat(kasbon): pencairan kasbon mengurangi kas laci (tunai/transfer)"
```

---

### Task 3: Sinkron kas → kasbon saat baris `kas_keluar` diedit/dihapus

**Files:**
- Modify: `app/Services/Finance/HutangPiutangService.php` (tambah 3 method publik)
- Modify: `app/Controllers/Kas_Keluar.php` (`update_kas_keluar()` ~L368, `delete_kas_keluar()` ~L457)

**Interfaces:**
- Consumes: `hutang_piutang.kas_keluar_id`, `templateKasbonInput()`, `statusFromSisa()` dari Task 2.
- Produces (di `HutangPiutangService`):
  - `public function guardKasbonKasKeluar(int $kasKeluarId, int $jumlahBaru): array` → `['ok'=>bool,'message'=>string]`
  - `public function syncKasbonDariKasKeluar(int $kasKeluarId, array $data): array` → `['ok'=>bool,'message'=>string]`; `$data` = `['tanggal'=>string,'jumlah'=>int,'idbank'=>?string]`
  - `public function hapusKasbonDariKasKeluar(int $kasKeluarId): array` → `['ok'=>bool,'message'=>string]`

- [x] **Step 1: Tulis `guardKasbonKasKeluar()`**

Cari `hutang_piutang` dengan `kas_keluar_id = $kasKeluarId` dan `deleted = 0`. Tidak ada → `ok`. Bila `status === STATUS_LUNAS` → `ok=false`, pesan "Kasbon sudah lunas dipotong gaji; baris kas tidak boleh diubah." Bila `$jumlahBaru < total_dibayar` → `ok=false`, pesan "Nominal kas lebih kecil dari yang sudah dibayar."

- [x] **Step 2: Tulis `syncKasbonDariKasKeluar()`**

Tidak ada kasbon tertaut → `ok`. Bila ada: `status = statusFromSisa($total, $total - $total_dibayar, $total_dibayar)`; di dalam `transBegin()`: update `hutang_piutang` (`total`, `sisa`, `status`, `tanggal`, `metode`, `bank_idbank`), hapus jurnal lama lalu post ulang:

```php
$this->db->table('jurnal')->where('tabel_referensi', 'hutang_piutang')
    ->where('id_referensi', (int) $row->id)->delete();
$this->postJurnal($data['tanggal'] ?? $row->tanggal, $this->templateKasbonInput($metode),
    $total, 'Kasbon Pegawai: ' . $row->kode, (int) $row->id, 'hutang_piutang',
    (int) $row->unit_id, (int) session('ID_AKUN'));
```

`metode = (trim((string) ($data['idbank'] ?? '')) !== '') ? 'transfer' : 'tunai'`.

- [x] **Step 3: Tulis `hapusKasbonDariKasKeluar()`**

Tidak ada kasbon tertaut → `ok`. Bila kasbon punya baris di `pembayaran_hutang_piutang` **atau** `status === STATUS_LUNAS` → `ok=false`, pesan "Kasbon sudah dibayar/dipotong gaji; baris kas tidak boleh dihapus." Selain itu, dalam transaksi: hapus jurnal `('hutang_piutang', hpId)`, lalu `$this->hp->update($hpId, ['deleted' => 1])`.

- [x] **Step 4: Sambungkan di `update_kas_keluar()`**

Sebelum `$db->transStart()` (setelah `$barisLama` diambil), sisipkan guard:

```php
$hpSvc = new \App\Services\Finance\HutangPiutangService();
$guard = $hpSvc->guardKasbonKasKeluar((int) $id, (int) preg_replace('/[^\d]/', '', (string) $jumlah));
if (! $guard['ok']) {
    session()->setFlashdata('gagal', $guard['message']);
    return redirect()->to(base_url('/kas_keluar'));
}
```

Setelah `$db->transComplete()` sukses (sebelum flash sukses), panggil:

```php
$hpSvc->syncKasbonDariKasKeluar((int) $id, ['tanggal' => $tanggal, 'jumlah' => (int) preg_replace('/[^\d]/', '', (string) $jumlah), 'idbank' => $penerima]);
```

- [x] **Step 5: Sambungkan di `delete_kas_keluar()`**

Sebelum `$db->transStart()`:

```php
$guard = (new \App\Services\Finance\HutangPiutangService())->hapusKasbonDariKasKeluar((int) $id);
if (! $guard['ok']) {
    session()->setFlashdata('gagal', $guard['message']);
    return redirect()->to(base_url('/kas_keluar'));
}
```

- [x] **Step 6: Verifikasi manual cepat (DB)**

Buat kasbon tunai uji → edit `jumlah` baris kasnya di `/kas_keluar` → `total`/`sisa` kasbon ikut berubah dan jurnal punya 1 pasang dengan nominal baru. Hapus baris kasnya → `hutang_piutang.deleted = 1` dan jurnal kasbon hilang.
Expected: tidak ada sisa jurnal yatim, tidak ada kasbon aktif tanpa baris kas.

- [x] **Step 7: Commit**

```bash
git add app/Services/Finance/HutangPiutangService.php app/Controllers/Kas_Keluar.php
git commit -m "feat(kasbon): sinkron edit/hapus baris kas ke kasbon + guard lunas"
```

---

### Task 4: Smoke test end-to-end

**Files:**
- Create: `app/Scripts/kasbon_kas_laci_smoke.php`

**Interfaces:**
- Consumes: `HutangPiutangService::createPosition()`, `guardKasbonKasKeluar()`, `syncKasbonDariKasKeluar()`, `hapusKasbonDariKasKeluar()`.
- Produces: —

Ikuti pola `app/Scripts/hutang_piutang_smoke.php` (bootstrap CI, ambil unit/pegawai dari DB, tandai data `SMOKE-KAS`, bersihkan di akhir). Pakai helper `ok($label, $cond, $detail)`.

- [x] **Step 1: Tulis skrip + assertion**

Urutan assertion (semua dengan `ok(...)`):

1. `createPosition` kasbon tunai → `success`; `kas_keluar` bertambah 1 dengan `idbank IS NULL`, `kategori = Kasbon`, `jumlah = total`; `hutang_piutang.kas_keluar_id` terisi.
2. Tidak ada jurnal `tabel_referensi='kas_keluar'` untuk id itu (`SELECT COUNT(*) ... = 0`).
3. Jurnal `hutang_piutang` untuk id itu = tepat 2 baris (template `hp_kasbon_input`).
4. Cari satu `idbank` yang lolos `validateUntukUnit` untuk unit uji; bila ada, buat kasbon **transfer** → baris `kas_keluar.idbank` = idbank itu dan jurnal memakai `hp_kasbon_input_bank` (kredit `1010102000`). Bila tidak ada rekening yang berhak, cetak `SKIP transfer`.
5. Transfer ke idbank tidak berhak (mis. `'ZZZ-999'`) → `createPosition` `success = false` dan tidak ada baris `hutang_piutang` baru.
6. `guardKasbonKasKeluar(id, jumlah - 1)` setelah ada `total_dibayar` → `ok = false`. (Set `total_dibayar` uji lewat `bayar()` lalu rollback/bersihkan, atau pakai kasbon terpisah.)
7. `syncKasbonDariKasKeluar(id, ['jumlah'=>total+50000, ...])` → kasbon `total` naik, jurnal tetap 2 baris dengan nominal baru.
8. `hapusKasbonDariKasKeluar(id)` → `hutang_piutang.deleted = 1` dan 0 baris jurnal `hutang_piutang` untuk id itu.
9. Bersihkan: hapus `kas_keluar` dan `hutang_piutang` ber-`SMOKE-KAS`.

- [x] **Step 2: Jalankan**

Run: `php74 app/Scripts/kasbon_kas_laci_smoke.php`
Expected: semua `PASS`, keluar dengan kode 0.

- [x] **Step 3: Commit**

```bash
git add app/Scripts/kasbon_kas_laci_smoke.php
git commit -m "test(kasbon): smoke test kasbon mengurangi kas laci"
```

- [x] **Step 4: Checklist manual UI (bukti modul membaca baris kas)**

> **Tidak dijalankan di browser.** Diganti assertion otomatis terhadap reader nyata
> `TutupKasirSourceDefinition::kasKeluarCash()` / `kasKeluarTransfer()` di smoke test
> (naik tepat sebesar kasbon). Jalur Kas & Bank dan arus kas harian membaca tabel
> `kas_keluar` yang sama, jadi belum dibuktikan lewat UI.

1. `/kas_keluar` → baris "Kasbon KSB-... - <nama>" muncul dengan kategori Kasbon.
2. Kas & Bank unit → saldo KAS (atau BANK bila transfer) turun sebesar kasbon.
3. Arus kas harian tanggal itu → baris Kasbon muncul sebagai pengeluaran.
4. Tutup Kasir tanggal itu → `pengeluarancash` (atau transfer) naik sebesar kasbon.
5. Slip gaji pegawai → baris "Bon" tidak berubah oleh kasbon baru.

---

## Self-Review

- Spec §4.1 (insert `kas_keluar`) → Task 2. §4.2 (jurnal, template bank) → Task 1 (seed) + Task 2 (pemilihan). §4.3 (kategori Kasbon) → Task 1. §4.4 (field metode) → Task 2. §4.5 (potong gaji tak berubah) → tidak ada task (memang tidak diubah). §4.6 (sembunyikan Bayar) → Task 2 Step 6. §4.7 (link + sinkron) → Task 1 (kolom) + Task 3.
- Kasus tepi §6: kategori hilang → Task 2 helper (throw); transfer invalid → Task 2 Step 1 + smoke #5; rollback saat gagal → Task 2 (di dalam transaksi); idempotensi → smoke #1/#3; edit lunas & jumlah < dibayar → Task 3 Step 1 + smoke #6; hapus saat ada pembayaran → Task 3 Step 3.
- Tipe konsisten: `guardKasbonKasKeluar`/`syncKasbonDariKasKeluar`/`hapusKasbonDariKasKeluar` dipakai dengan nama sama di Task 3 controller dan Task 4 smoke.

---

## Hasil eksekusi (native, 2026-10-18)

Branch: `feat/kasbon-kas-laci` (dari `main`, tanpa worktree).

| Commit | Isi |
|---|---|
| `5014230` | spec + rencana |
| `c62cd51` | smoke test RED (11 gagal) |
| `6688628` | Task 1: migrasi |
| `b5db15d` | Task 2: pencairan kasbon → kas_keluar |
| `1dfaefe` | Task 3: guard + sinkron kas→kasbon |
| `f412982` | Task 4: assertion reader Tutup Kasir |
| `c0d93cc` | perbaikan hasil review: tolak total negatif & nominal 0, wajib akun KAS aktif, sinkron atomik |

Verifikasi:
- `php74 app/Scripts/kasbon_kas_laci_smoke.php` → **29 passed, 0 failed**.
- `php74 app/Scripts/hutang_piutang_smoke.php` → 15 passed, 0 failed (regresi bersih).
- PHPUnit: **220 test gagal dengan nama identik di `main` dan di branch ini** (DB test `erp_test` basi, sudah ada sebelum perubahan). Tidak ada regresi. Uji determinisme: subset relevan dijalankan 2× → identik.

Penyimpangan dari rencana (semua sudah dicatat di ledger): smoke test dikerjakan lebih dulu (TDD), `ModelHutangPiutang::$allowedFields` ditambah 3 kolom, pre-cleanup data uji, dan satu putaran perbaikan hasil review (2 Important + 2 Minor yang dinilai ulang).
