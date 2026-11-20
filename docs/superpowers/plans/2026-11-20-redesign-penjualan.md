# Redesign Penjualan + Split Payment — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign `/penjualan` into a calm one-sheet cashier page with working Tunai / Transfer / Tunai + Transfer split payment, live totals, scoped bank accounts, and inline validation.

**Architecture:** Add a small pure-PHP `PenjualanPaymentSplit` service that normalizes cash + bank rows and validates them against the unit's allowed bank accounts. The controller consumes that service and derives `Lunas/Belum Lunas` from server totals instead of the hidden `hutang` field. The view becomes a `.pj-*` themed single sheet that mirrors `/pembelian`, with the same POST field names, IDs, modals, and JS hooks preserved.

**Tech Stack:** CodeIgniter 4.4.8, PHP 7.4+/8.x, Bootstrap 5, jQuery/Select2, Iconify Solar, vanilla JS, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-20-redesign-penjualan-design.md`

## Global Constraints

- No schema changes, no new dependency, no new palette/font.
- Keep route `POST insert_penjualan` and all existing field names/IDs:
  `tanggal_masuk`, `sales_by`, `metode_bayar`, `bayar`,
  `bank[i][id]`, `bank[i][jumlah]`,
  `produk[id][id|kode|nama|kategori|kondisi_barang|harga|jumlah|diskon|ppn]`,
  `total-diskon`, `total-ppn`, `total-harga`, `hutang`, `kembalian`,
  `selectedidpelanggan`, `pelanggan`, `action`.
- Keep `.produk-checkbox` data attributes, `data-harga` as whole rupiah, and
  `formatToRupiah()` stripping non-digits.
- Keep both save actions and their current server behavior (both save + print
  thermal). Do not change the print/redirect flow.
- Underpayment stays allowed and records `Belum Lunas`; no automatic piutang.
- Bank options and posted bank IDs must be limited to
  `ModelBank::getBankDalamScopeUnit(session('ID_UNIT'))`.
- One accent, hairline rules, `tabular-nums` money, visible focus, light/dark,
  and `prefers-reduced-motion` respected.

## Review Focus

- A cashier enters cash, switches to Transfer, and submits: the hidden cash must
  not be counted. Test: `metode_bayar=transfer` zeroes cash.
- A bank row has an amount but no account/changed account: the sale must be
  rejected, not silently lose money. Tests: missing ID, out-of-scope ID.
- A zero-amount bank row left behind by the UI must be ignored. Test: zero rows.
- The page must not crash when the cart is empty or totals are zero. Test:
  controller/service guards and view empty state.
- The redesigned picker must keep `data-harga="55000"` and
  `formatToRupiah`'s non-digit contract. Existing
  `tests/PenjualanPickerMoneyTest.php` is the regression gate.

---

### Task 1: Payment split service + unit test

**Files:**
- Create: `app/Services/Sales/PenjualanPaymentSplit.php`
- Test: `tests/unit/PenjualanPaymentSplitTest.php`

**Interfaces:**
- Produces:
  `PenjualanPaymentSplit::normalize(array $post, array $allowedBankIds): array`
  returning:
  `['cash' => float, 'banks' => list<array{id:string, jumlah:float}>, 'total' => float, 'errors' => string[]]`.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/PenjualanPaymentSplitTest.php` with cases:

```php
use App\Services\Sales\PenjualanPaymentSplit;

public function testSplitTunaiTransferMenjumlahkanTunaiDanBanyakBank(): void
{
    $hasil = (new PenjualanPaymentSplit())->normalize([
        'metode_bayar' => 'tunai_transfer',
        'bayar' => 'Rp 150.000',
        'bank' => [
            ['id' => '1', 'jumlah' => 'Rp 250.000'],
            ['id' => '2', 'jumlah' => 'Rp 100.000'],
        ],
    ], ['1', '2']);

    $this->assertSame(400000.0, $hasil['total']);
    $this->assertSame(150000.0, $hasil['cash']);
    $this->assertCount(2, $hasil['banks']);
    $this->assertSame([], $hasil['errors']);
}

public function testMetodeTunaiMengabaikanBarisBank(): void
{
    $hasil = (new PenjualanPaymentSplit())->normalize([
        'metode_bayar' => 'tunai',
        'bayar' => 'Rp 100.000',
        'bank' => [['id' => '1', 'jumlah' => 'Rp 50.000']],
    ], ['1']);

    $this->assertSame(100000.0, $hasil['total']);
    $this->assertSame([], $hasil['banks']);
}

public function testMetodeTransferMengabaikanTunai(): void
{
    $hasil = (new PenjualanPaymentSplit())->normalize([
        'metode_bayar' => 'transfer',
        'bayar' => 'Rp 100.000',
        'bank' => [['id' => '1', 'jumlah' => 'Rp 250.000']],
    ], ['1']);

    $this->assertSame(250000.0, $hasil['total']);
    $this->assertSame(0.0, $hasil['cash']);
}

public function testBarisBankNolDiabaikan(): void
{
    $hasil = (new PenjualanPaymentSplit())->normalize([
        'metode_bayar' => 'transfer',
        'bank' => [['id' => '', 'jumlah' => 'Rp 0']],
    ], ['1']);

    $this->assertSame([], $hasil['banks']);
    $this->assertSame([], $hasil['errors']);
}

public function testNominalBankTanpaRekeningDitolak(): void
{
    $hasil = (new PenjualanPaymentSplit())->normalize([
        'metode_bayar' => 'transfer',
        'bank' => [['id' => '', 'jumlah' => 'Rp 50.000']],
    ], ['1']);

    $this->assertNotSame([], $hasil['errors']);
}

public function testRekeningDiLuarScopeDitolak(): void
{
    $hasil = (new PenjualanPaymentSplit())->normalize([
        'metode_bayar' => 'transfer',
        'bank' => [['id' => '99', 'jumlah' => 'Rp 50.000']],
    ], ['1']);

    $this->assertNotSame([], $hasil['errors']);
}

public function testNominalNegatifDitolak(): void
{
    $hasil = (new PenjualanPaymentSplit())->normalize([
        'metode_bayar' => 'tunai',
        'bayar' => '-5000',
    ], []);

    $this->assertNotSame([], $hasil['errors']);
}

public function testMetodeKosongMemakaiPerilakuLama(): void
{
    $hasil = (new PenjualanPaymentSplit())->normalize([
        'bayar' => 'Rp 10.000',
        'bank' => [['id' => '1', 'jumlah' => 'Rp 20.000']],
    ], ['1']);

    $this->assertSame(30000.0, $hasil['total']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/unit/PenjualanPaymentSplitTest.php`
Expected: FAIL — class `App\Services\Sales\PenjualanPaymentSplit` not found.

- [ ] **Step 3: Implement the service**

`app/Services/Sales/PenjualanPaymentSplit.php`:

- `normalize()` reads `metode_bayar` lowercased.
- `parseMoney($raw): float` strips `Rp`, dots, spaces; if the raw string
  contains `-`, add error `Nominal pembayaran tidak boleh negatif.` and return 0.
- Sensitive rules:
  - `tunai`: `banks = []`, `cash` from `bayar`.
  - `transfer`: `cash = 0`, use bank rows.
  - `tunai_transfer`: both.
  - anything else: both (legacy).
  - Per bank row: `jumlah <= 0` → skip; `id === ''` → error
    `Rekening bank wajib dipilih untuk nominal transfer.`; if
    `$allowedBankIds !== []` and ID not in it (compare as strings) → error
    `Rekening bank tidak termasuk rekening unit ini.`
- Return `total = cash + sum(banks)`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/unit/PenjualanPaymentSplitTest.php`
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Sales/PenjualanPaymentSplit.php tests/unit/PenjualanPaymentSplitTest.php
git commit -m "feat(penjualan): validasi split pembayaran tunai dan bank (refs #222)"
```

---

### Task 2: Controller integration + bank scope

**Files:**
- Modify: `app/Controllers/Penjualan.php`

**Interfaces:**
- Consumes: `PenjualanPaymentSplit::normalize()` from Task 1.
- Produces: `Penjualan::index()` passes only unit-scoped `bank`;
  `insert_penjualan` rejects invalid split, uses normalized cash/banks, and
  derives `keterangan` server-side.

- [ ] **Step 1: Write the failing integration assertion (source test)**

There is no safe DB fixture for the full sale, so pin the integration points in
`tests/PenjualanPageTest.php` later (Task 3) and in this task verify by
`php -l` plus a focused assertion that the controller uses the service:

```php
// tests/PenjualanPaymentSplitTest.php, tambahan
public function testControllerMemakaiServiceDanScopeBankUnit(): void
{
    $source = file_get_contents(APPPATH . 'Controllers/Penjualan.php');
    $this->assertStringContainsString('PenjualanPaymentSplit', $source);
    $this->assertStringContainsString('getBankDalamScopeUnit', $source);
    $this->assertStringNotContainsString("getPost('hutang')", $source);
}
```

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/unit/PenjualanPaymentSplitTest.php`
Expected: FAIL on the new assertions.

- [ ] **Step 2: Update `index()`**

Change:

```php
'bank' => $this->BankModel->getBank(),
```

to:

```php
'bank' => $this->BankModel->getBankDalamScopeUnit((int) session('ID_UNIT')),
```

Do not change any other index data.

- [ ] **Step 3: Update `insert_penjualan()`**

- Add `use App\Services\Sales\PenjualanPaymentSplit;`.
- Move `$db->transStart()` after input validation.
- After `$produkData = $this->request->getPost('produk');`:
  - If not a non-empty array → `session()->setFlashdata('gagal', 'Pilih minimal satu produk sebelum menyimpan penjualan.'); return redirect()->to(base_url('/penjualan'));`
  - `$total_penjualan = $this->sanitizeCurrency($this->request->getPost('total-harga'));`
  - If `$total_penjualan <= 0` → flash gagal and redirect.
- Build `$allowedBankIds` from
  `$this->BankModel->getBankDalamScopeUnit((int) session('ID_UNIT'))`
  using `(string) $b->idbank`.
- `$split = (new PenjualanPaymentSplit())->normalize($this->request->getPost(), $allowedBankIds);`
- If `$split['errors'] !== []` → flash the first error + redirect. Do not write.
- Replace the old `getPost('bayar')` and raw `getPost('bank')` normalization with
  `$bayar_tunai = $split['cash']; $bankData = $split['banks'];`.
- In the bank insertion loop use `(float) ($b['jumlah'] ?? 0)`.
- Initialize `$jurnal = [];` before the bank loop.
- Compute:
  `$total_bayar = $bayar_tunai + $totalBayarBank;`
  `$hutang = max(0, $total_penjualan - $total_bayar);`
  `$keterangan = $hutang == 0 ? 'Lunas' : 'Belum Lunas';`
  Remove the `getPost('hutang')` read.
- Keep invoice, stock, journals, print flow, and both `action` branches
  unchanged.

- [ ] **Step 4: Run checks**

Run:
```bash
php -l app/Controllers/Penjualan.php
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/PenjualanPaymentSplitTest.php
```
Expected: `No syntax errors detected`; all tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Controllers/Penjualan.php tests/unit/PenjualanPaymentSplitTest.php
git commit -m "feat(penjualan): pakai split pembayaran dan scope rekening unit (refs #222)"
```

---

### Task 3: Theme file + redesigned page shell

**Files:**
- Create: `app/Views/transaksi/_penjualan_theme.php`
- Modify: `app/Views/transaksi/penjualan.php`
- Test: `tests/PenjualanPageTest.php`

**Interfaces:**
- Produces: `.penjualan-page`, `.pj-head`, `.pj-sheet`, `.pj-section`,
  `.pj-pay-methods`, `.pj-dock`, `.pj-num`; all selector names below are
  contracts for Task 4.
- Keeps every ID/name listed in Global Constraints.

- [ ] **Step 1: Write the failing view test**

Create `tests/PenjualanPageTest.php` with a `render()` helper modeled on
`tests/PenjualanPickerMoneyTest.php` (same fixture objects), then assert:

```php
$this->assertStringContainsString('class="penjualan-page"', $html);
$this->assertStringContainsString('pj-head', $html);
$this->assertStringContainsString('id="form_penjualan"', $html);
$this->assertSame(3, substr_count($html, 'class="pj-section'));
$this->assertStringContainsString('name="metode_bayar" value="tunai"', $html);
$this->assertStringContainsString('name="metode_bayar" value="transfer"', $html);
$this->assertStringContainsString('name="metode_bayar" value="tunai_transfer"', $html);
$this->assertStringContainsString('id="bayar"', $html);
$this->assertStringContainsString('name="bayar"', $html);
$this->assertStringContainsString('id="bank-payment-container"', $html);
$this->assertStringContainsString('id="tambah-bank"', $html);
$this->assertStringContainsString('id="total-diskon"', $html);
$this->assertStringContainsString('id="total-ppn"', $html);
$this->assertStringContainsString('id="total-harga"', $html);
$this->assertStringContainsString('id="hutang"', $html);
$this->assertStringContainsString('id="kembalian"', $html);
$this->assertStringContainsString('name="action" value="simpan"', $html);
$this->assertStringContainsString('name="action" value="simpan_thermal"', $html);
$this->assertStringNotContainsString('bayar_bank', $html);
$this->assertStringContainsString('id="pelangganModal"', $html);
$this->assertStringContainsString('id="modalTambahPelanggan"', $html);
$this->assertStringContainsString('id="pelanggan-select"', $html);
$this->assertStringContainsString('id="pilih-produk-modal"', $html);
$this->assertStringContainsString('id="selected-produk-table"', $html);
$this->assertStringContainsString('aria-live="polite"', $html);
$this->assertStringContainsString('class="pj-dock"', $html);
```

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/PenjualanPageTest.php`
Expected: FAIL — old markup lacks `.pj-*` shell.

- [ ] **Step 2: Create `_penjualan_theme.php`**

Scoped `<style>` with CSS variables `--pj-*` for light and
`html[data-bs-theme="dark"]`, following `_pembelian_theme.php`:
surface/subtle/rule/rule-strong/hover/ink/strong/muted/accent/accent-ink/
accent-soft/accent-contrast/danger/danger-soft/ok/ok-soft/radius/radius-sm.

Components to style:
- `.pj-head`, `.pj-crumb`, `.pj-head__row`, `.pj-head h1`, `.pj-sub`,
  `.pj-unit` (read-only unit chip);
- `.pj-sheet`, `.pj-section`, `.pj-section__head`, `.pj-section__title`,
  `.pj-section__hint`, `.pj-field`, `.pj-grid`;
- `.pj-customer` (readonly input + button), `.pj-customer__meta`;
- `.pj-toolbar`, `.pj-count`, `.pj-btn` (primary/outline/ghost/danger),
  `.pj-btn--icon`;
- `.pj-table` (ledger), `.pj-table th`, `.pj-table td`, `.pj-empty`,
  `.pj-money-input`, `.pj-subtotal`, `.pj-row-del`, `data-label` cards under
  `767.98px`;
- `.pj-pay-methods`, `.pj-method`, `.pj-method__box`, `.pj-method__label`,
  `.pj-method__hint`, selected state via `:has(input:checked)` with a
  `input:checked + .pj-method__box` fallback;
- `.pj-zone`, `.pj-bank-row`, `.pj-bank-row__remove`, `.pj-quick`,
  `.pj-pay-status` (+ `.is-ok`, `.is-due`, `.is-over`);
- `.pj-dock`, `.pj-dock__figures`, `.pj-figure`, `.pj-figure--total`,
  `.pj-figure--debt`, `.pj-dock__actions`, `.pj-save-hint`;
- `.pj-modal`, `.pj-picker`, `.pj-picker__head`, `.pj-picker__tabs`,
  `.pj-picker__search`, `.pj-pick-list`, `.pj-pick-row`, `.pj-pick-row__meta`,
  `.pj-stock`;
- responsive breakpoints `1199.98`, `991.98`, `767.98`, `575.98`;
- `@media (prefers-reduced-motion: reduce)` and `pj-settle` keyframes.

Rules: no gradients, no shadows, no colored left borders, one accent
`#0077c4` / `#8ab4ff`, money `tabular-nums`, focus-visible 2px accent.

- [ ] **Step 3: Rewrite the view shell in `penjualan.php`**

Start the file with:

```php
<?= $this->include('transaksi/_penjualan_theme') ?>
```

Wrap everything in `<div class="penjualan-page">`. Header:

```php
<header class="pj-head">
  <ol class="pj-crumb">...</ol>
  <div class="pj-head__row">
    <div>
      <h1>Penjualan</h1>
      <p class="pj-sub">Pilih pelanggan, masukkan produk, lalu bagi pembayaran tunai dan transfer.</p>
    </div>
    <span class="pj-unit">Unit: <?= esc(session('NAMA_UNIT') ?: 'Aktif') ?></span>
  </div>
</header>
```

Form sections (same form element/id/action):

1. **Pihak & Dokumen** — grid:
   - `#tanggal_masuk` (date, default today, required)
   - `#sales_by` (select from `$frontliner`, required)
   - `#pelanggan-container` with readonly `#pelanggan` (`name="pelanggan"`,
     required) + button `#pelanggan-button` (`data-bs-target="#pelangganModal"`)
   - `#pj-customer-meta` empty text placeholder for address.
   - Remove the three main-form `#form_provinsi/#form_kabupaten/#form_kecamatan`
     selects.
2. **Produk** — toolbar with `#pj-pilih-produk` button opening
   `#pilih-produk-modal`, `#pj-item-count`, `#pj-clear-cart` ghost button.
   Table markup with columns Produk, Harga, Qty, Diskon, PPN, Subtotal, Aksi.
   `<tbody id="selected-produk-table">`; empty-state `<tr id="pj-empty-row">`
   with icon/text and a button that opens the picker.
   Hidden summary inputs `#total-diskon`, `#total-ppn`, `#total-harga` must
   stay inside the form (put them in the dock). If the test expects them in the
   markup, the dock satisfies it.
3. **Pembayaran** — `role="radiogroup"` with three `input[name="metode_bayar"]`
   + `.pj-method__box` labels; `#bayar`; `#bank-payment-container`;
   `#tambah-bank`; `#pj-pay-status` with `aria-live="polite"`; quick chip
   `#pj-uang-pas`.
4. **Dock** — figures (Diskon, PPN, Total, Dibayar, Kembalian, Sisa/Hutang)
   and both submit buttons; hint about both printing. Keep `#hutang` and
   `#kembalian` as readonly inputs (hutang posted, kembalian display).

Modals stay in the same file for now: `#pilih-produk-modal` (can keep the old
table markup temporarily so the page renders; Task 4 replaces the inside),
`#pelangganModal`, `#modalTambahPelanggan`, `#detailBundleModal`. Keep all IDs.

- [ ] **Step 4: Run the view test**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/PenjualanPageTest.php`
Expected: PASS for Task 3 assertions; picker-specific assertions may be added in
Task 4.

- [ ] **Step 5: Commit**

```bash
git add app/Views/transaksi/_penjualan_theme.php app/Views/transaksi/penjualan.php tests/PenjualanPageTest.php
git commit -m "feat(penjualan): redesign satu lembar dan panel split payment (refs #222)"
```

---

### Task 4: Picker, customer modal, and page JavaScript

**Files:**
- Modify: `app/Views/transaksi/penjualan.php`
- Modify: `tests/PenjualanPageTest.php`

**Interfaces:**
- Consumes: `.pj-*` classes from Task 3.
- Produces: working picker/customer/payment JS; no `alert()`, no duplicate
  submit listeners, no `bayar_bank`.

- [ ] **Step 1: Extend the failing view test**

Add assertions:

```php
$this->assertStringContainsString('id="pj-picker-search"', $html);
$this->assertStringContainsString('class="produk-checkbox"', $html);
$this->assertStringContainsString('data-harga="55000"', $html);
$this->assertStringContainsString('data-harga="125000"', $html);
$this->assertStringContainsString('id="confirm-produk-btn"', $html);
$this->assertStringContainsString('id="select-all-produk"', $html);
$this->assertStringContainsString('id="detailBundleModal"', $html);
$this->assertStringContainsString('id="bundle-detail-body"', $html);
$this->assertStringContainsString('id="btnPilihPelanggan"', $html);
$this->assertStringContainsString('id="btnTambahPelanggan"', $html);
$this->assertStringContainsString('id="formTambahPelanggan"', $html);
$this->assertStringContainsString("angka.replace(/[^\\d]/g, '')", $html);
```

Run and expect FAIL until the picker/JS is in place.

- [ ] **Step 2: Replace the picker modal**

Keep `#pilih-produk-modal`, `#produk-modal-table`, `#jasa-modal-table`,
`#bundle-modal-table` containers (IDs are contracts), but render rows as
`.pj-pick-row` inside scrollable lists. For each product/jasa/bundle keep the
existing `.produk-checkbox` attributes and `data-harga` values exactly (products
and jasa use `(int) round((float) ...)`; bundles keep the raw string).

Add one search input `#pj-picker-search`; filter `.pj-pick-row` in the active
tab by `textContent`. Add a footer `#pj-pick-count` + existing
`#confirm-produk-btn` + Batal. Keep `#select-all-produk` for the Produk tab.
Keep `#detailBundleModal` / `#bundle-detail-body` and `.btn-check-bundle`.

- [ ] **Step 3: Replace the page JS**

Rewrite the script blocks into one `DOMContentLoaded` flow:

- `formatToRupiah(angka)` / `unformatRupiah(rupiah)` with the exact regex from
  `PenjualanPickerMoneyTest`.
- `addProductRow(data)`: creates `#produk-row-${id}`, hidden inputs, price/qty/
  discount/PPN/subtotal/delete. All names exactly as before. Bundle detail is
  shown from `data-detail` via the existing modal.
- `updateTotals()`: row subtotal = `(harga*qty - diskon) + ppn`, totals for
  discount/PPN/total, dock update, `#pj-item-count`, empty state visibility,
  and `updatePayment()`.
- `updatePayment()`: read checked `metode_bayar`; show/hide `.pj-zone-tunai`
  and `.pj-zone-transfer`; clear inactive zone (cash → `Rp 0`, remove bank
  rows) on method change; sum cash + `.bank-amount`; set `#hutang`,
  `#kembalian`, `#pj-pay-status` (is-ok / is-due / is-over).
- `ensureBankRow()` / `addBankRow()`: `.bank-row` with `bank[index][id]`,
  `bank[index][jumlah]`, `.bank-select`, `.bank-amount`, `.hapus-bank`; format
  amount; respect `bank` list from PHP; show empty note if no banks.
- `#pj-uang-pas` sets `#bayar` to the current total.
- `#pj-clear-cart` clears `#selected-produk-table` after an inline confirm
  state (button text "Yakin?") and updates totals.
- Customer modal: Select2 init, `#btnPilihPelanggan` fills `#pelanggan` and
  `#pj-customer-meta` from `data-provinsi/kabupaten/kecamatan`, closes modal.
  `#btnTambahPelanggan` opens `#modalTambahPelanggan`. Add-customer AJAX keeps
  the existing URL/fields; on success append option, fill customer, update meta,
  reopen customer modal. Replace `alert()` with inline `.pj-modal-error` text.
- Region cascade only for `#modal_provinsi/#modal_kabupaten/#modal_kecamatan`.
- Single submit handler: validate customer, at least one product, staff, numeric
  non-negative amounts, bank row with amount > 0 needs a selected bank; on
  failure focus the first bad field, show inline text, `preventDefault()`.
  Underpayment is allowed (no block); show the due status.
- Remove the old duplicate submit listeners, `bayar_bank`, and
  `document.querySelector('.table-scroll')` drag code if the table no longer
  uses it (keep it harmless if it still exists).

- [ ] **Step 4: Run tests**

Run:
```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/PenjualanPageTest.php tests/PenjualanPickerMoneyTest.php
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Views/transaksi/penjualan.php tests/PenjualanPageTest.php
git commit -m "feat(penjualan): picker kartu, validasi inline, dan split payment live (refs #222)"
```

---

### Task 5: Documentation + final verification

**Files:**
- Modify: `DESIGN.md`
- Modify: `docs/prd/modul/PRD_MODUL_02_Penjualan_Piutang.md`
- Create: `.impeccable/surfaces/penjualan.md`

- [ ] **Step 1: Update `DESIGN.md`**

Add a `### Penjualan (`.pj-*`)` section after the Pembelian section: scope,
layout, payment split, bank scope, picker, dock, responsive, color/motion rules.

- [ ] **Step 2: Update PRD Modul 02**

Add/refresh the flow and acceptance criteria:
- split payment cards + multiple bank rows + live recap;
- bank scope `getBankDalamScopeUnit`;
- server rejects invalid rows/empty cart;
- underpayment stays `Belum Lunas`, no auto piutang;
- both save actions still print thermal.

- [ ] **Step 3: Create `.impeccable/surfaces/penjualan.md`**

Front matter (`version: 1`, `slug: penjualan`, `primary_target: penjualan`,
`related_targets`) and a short surface brief matching the design: mode
Operate, one-sheet thesis, memorable dock, direction contract, unresolved
decisions.

- [ ] **Step 4: Final checks**

Run:
```bash
php -l app/Controllers/Penjualan.php
php -l app/Services/Sales/PenjualanPaymentSplit.php
php -l app/Views/transaksi/penjualan.php
php -l app/Views/transaksi/_penjualan_theme.php
vendor/bin/phpunit -c phpunit.xml.dist tests/PenjualanPickerMoneyTest.php tests/PenjualanPageTest.php tests/unit/PenjualanPaymentSplitTest.php
git diff --check
```
Expected: no syntax errors, all tests PASS, no whitespace errors.

- [ ] **Step 5: Commit**

```bash
git add DESIGN.md docs/prd/modul/PRD_MODUL_02_Penjualan_Piutang.md .impeccable/surfaces/penjualan.md
git commit -m "docs(penjualan): catat desain dan aturan split payment (refs #222)"
```

---

## Self-Review Notes

- Spec coverage: layout, split payment, bank scope, picker, customer modal,
  validation, underpayment, tests, docs all have tasks.
- Type consistency: service returns `cash`, `banks[{id,jumlah}]`, `total`,
  `errors`; controller and tests use those names.
- Review Focus tests are pinned in Task 1 (method consistency, zero rows,
  missing/out-of-scope bank, negative) and Task 4 (picker contract).
- Print behavior is intentionally unchanged; the UI hint prevents a misleading
  promise.
