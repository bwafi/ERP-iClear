# Redesign UI Pembayaran Lunas (Tunai + Transfer) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengubah blok Pembayaran `/pembelian` menjadi konsol Lunas dengan tab metode, panel split tunai/bank, ringkasan alokasi live, dan dock Lunas yang disederhanakan — tanpa mengubah server, schema, route, atau perilaku Tempo.

**Architecture:** Perubahan hanya di view `app/Views/transaksi/pembelian.php` dan tema `app/Views/transaksi/_pembelian_theme.php`. Tab metode menulis ke select native `#metode_bayar` yang sudah ada, lalu memicu `aturPembayaran()`; `updateHutang()` tetap menjadi satu-satunya perhitungan uang. Page test mengunci struktur, hook, dan kontrak JS; verifikasi visual/responsif dilakukan manual di browser.

**Tech Stack:** CodeIgniter 4.4.8 view, Bootstrap 5, jQuery + Select2, Iconify `solar:*`, vanilla JS, PHPUnit 9.6.

**Spec:** `docs/superpowers/specs/2026-11-20-redesign-lunas-pembelian-design.md`

## Global Constraints

- Jangan mengubah `Pembelian::insert`, `PembelianPaymentSplit`, schema, route, CSRF, jurnal, stok, HPP, PPN, atau harga jual.
- `#jenis_pembayaran` dan `#metode_bayar` tetap `<select>` native dengan opsi lama.
- Semua `id`, `name`, hook JS, dan urutan `#pb-bayar-title` → `#metode_bayar` → `name="bayar"` dipertahankan.
- `#bayar`, `#bank-payment-container`, `#tambah-bank`, `#isi-sisa`, `#pb-pay-status`, `#hutang`, `#kembalian`, `#pb-dibayar` tetap ada.
- `#hutang` dan `#kembalian` tetap dikirim; menyembunyikannya hanya perubahan tampilan.
- Bank select tidak boleh `required` (regresi review #226: tunai saja pada `tunai_transfer` tidak boleh diblokir).
- Gunakan token `--pb-*`; tanpa shadow, gradien, border kiri berwarna, palet, font, atau dependency baru.
- Test hanya memakai database `erp_pembelian_test` lewat container `erp-app`; jangan arahkan ke database produksi.
- **Jangan commit.** User belum memberi instruksi commit. Gunakan checkpoint `git diff --check` + review diff.
- Perubahan split-payment issue #226 masih **uncommitted** di working tree. Plan ini duduk di atasnya; bila user nanti mengizinkan commit, commit #226 lebih dulu agar #227 bisa direview terpisah.
- Jangan menyentuh perubahan Penjualan milik user: `app/Views/transaksi/_penjualan_theme.php` dan `tests/PenjualanPageTest.php`.
- PRD Modul 03/05 tidak berubah karena tidak ada perubahan perilaku bisnis.

## Review Focus

1. **Tanpa rekening aktif:** tab `Transfer` dan `Tunai + Transfer` harus nonaktif dan catatan "Belum ada rekening aktif untuk unit ini." muncul; form tidak boleh diam-diam tidak bisa disubmit. — Page test Task 1.
2. **`tunai_transfer` tunai saja:** baris bank kosong tidak boleh memaksa rekening lewat `required`; tunai sebesar total harus diterima. — Page test lama + manual Task 2.
3. **Total berubah setelah split:** alokasi banyak rekening tidak boleh ditimpa otomatis; hanya transfer satu baris yang boleh mengikuti total. — Page test lama `testSinkronTransferTidakMenimpaSplitBanyakRekening` tetap lulus.
4. **Kurang/lebih bayar:** status dan dock harus menunjukkan selisih, Lunas tidak menampilkan Kembalian, dan submit diblokir. — Page test Task 2 + manual Task 2.
5. **Keyboard/screen reader:** tablist bisa dioperasikan dengan panah/Home/End, select native tersembunyi tidak menjadi tab stop ganda, dan status diumumkan. — Page test Task 2 + manual Task 2.
6. **390px:** tab, baris bank, ringkasan, dan dock tidak boleh overflow horizontal; target sentuh ≥44px. — Manual Task 3.

---

### Task 1: Struktur payment console + tab metode

**Files:**
- Modify: `app/Views/transaksi/pembelian.php:202-301` (bagian Pembayaran + dock)
- Modify: `app/Views/transaksi/pembelian.php:1202-1344` (template baris bank + `aturPembayaran`)
- Modify: `app/Views/transaksi/_pembelian_theme.php:744-849` (CSS pembayaran)
- Modify: `app/Views/transaksi/_pembelian_theme.php:1416-1511` (responsive)
- Test: `tests/PembelianPageTest.php:60-73`, `:157-171`, `:192-203`

**Interfaces:**
- Consumes: `$bank`, `$can_manage_harga_jual`, `$boleh_pilih_unit`, `$unit_list`, `$unit_terpilih`, session.
- Produces DOM: `.pb-pay-band`, `#pb-method-tabs`, `[data-metode="tunai|transfer|tunai_transfer"]`, `.pb-pay-split`, `.pb-cash-panel`, `.pb-bank-panel`, `.pb-bank-head`, `.pb-bank-list`, `#pb-alloc-total`, `#pb-alloc-dibayar`, `#pb-alloc-sisa`, `#pb-meter`, `[data-dock="lunas|tempo"]`.
- Produces JS: `function setMetode(value)`, `function syncTabMetode()`.

- [ ] **Step 1: Tulis page test yang gagal**

Ubah `testStrukturRedesignDirender` agar memakai band baru:

```php
$this->assertStringContainsString('pb-pay-band', $html);
$this->assertStringContainsString('pb-method-tabs', $html);
$this->assertStringNotContainsString('pb-pay-grid', $html);
```

Tambahkan test baru:

```php
public function testPaymentConsoleMemilikiTabDanPanel(): void
{
    $html = $this->render();

    $this->assertStringContainsString('id="pb-method-tabs"', $html);
    $this->assertStringContainsString('role="tablist"', $html);
    $this->assertStringContainsString('data-metode="tunai"', $html);
    $this->assertStringContainsString('data-metode="transfer"', $html);
    $this->assertStringContainsString('data-metode="tunai_transfer"', $html);
    $this->assertStringContainsString('class="pb-pay-split"', $html);
    $this->assertStringContainsString('id="pb-pay-split"', $html);
    $this->assertStringContainsString('id="pb-pay-empty"', $html);
    $this->assertStringContainsString('class="pb-cash-panel"', $html);
    $this->assertStringContainsString('class="pb-bank-panel"', $html);
    $this->assertStringContainsString('id="pb-alloc-total"', $html);
    $this->assertStringContainsString('id="pb-alloc-dibayar"', $html);
    $this->assertStringContainsString('id="pb-alloc-sisa"', $html);
    $this->assertStringContainsString('id="pb-meter"', $html);
}

public function testTabTransferNonaktifTanpaRekening(): void
{
    $html = $this->render(false, false, [], null, []);

    $this->assertMatchesRegularExpression(
        '/<button[^>]*data-metode="transfer"[^>]*disabled/s',
        $html
    );
    $this->assertMatchesRegularExpression(
        '/<button[^>]*data-metode="tunai_transfer"[^>]*disabled/s',
        $html
    );
    $this->assertStringContainsString('Belum ada rekening aktif untuk unit ini.', $html);
}

public function testDockMemilikiModeLunasDanTempo(): void
{
    $html = $this->render();

    $this->assertStringContainsString('data-dock="lunas"', $html);
    $this->assertStringContainsString('data-dock="tempo"', $html);
    $this->assertStringContainsString('id="hutang"', $html);
    $this->assertStringContainsString('id="kembalian"', $html);
}
```

Perbarui test bank dan tombol tambah:

```php
// testPembayaranBankDisusunVertikal
$this->assertStringContainsString('pb-bank-panel', $html);
$this->assertStringContainsString('pb-bank-list', $html);
$this->assertStringContainsString('pb-bank-head', $html);
// assertion lama bank-rekening-${index}, bank-nominal-${index},
// .pb-subfield, .pb-bank-row .pb-subfield__label tetap dipertahankan.

// testCsrfTokenAdaDanTombolTambahBankBerfungsi
$this->assertStringContainsString('Tambah rekening', $html);
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run:

```bash
docker exec -w /app -e database.tests.hostname=erp-db -e database.tests.database=erp_pembelian_test -e database.tests.username=erp -e database.tests.password=erp -e database.tests.DBDriver=MySQLi -e database.tests.DBPrefix= erp-app vendor/bin/phpunit -c phpunit.xml.dist --no-coverage tests/PembelianPageTest.php
```

Expected: FAIL pada assertion `pb-pay-band`, `#pb-method-tabs`, `data-metode`, `Tambah rekening`.

- [ ] **Step 3: Ganti markup bagian Pembayaran**

Ganti `<div class="pb-pay-grid">` dengan struktur berikut (pertahankan urutan judul → `#metode_bayar` → `name="bayar"`):

```html
<div class="pb-pay-band">
  <p class="pb-section__hint">…</p>
  <div class="pb-pay-head">
    <div class="pb-field">
      <label for="jenis_pembayaran">Jenis Pembayaran</label>
      <select id="jenis_pembayaran" name="jenis_pembayaran" class="form-select" required>…</select>
    </div>
    <div class="pb-field" id="jatuh-tempo-field">…</div>
  </div>

  <div class="pb-method-tabs" id="pb-method-tabs" role="tablist" aria-label="Metode pembayaran">
    <button type="button" class="pb-method-tab" data-metode="tunai" role="tab" aria-selected="false">…</button>
    <button type="button" class="pb-method-tab" data-metode="transfer" role="tab" aria-selected="false" <?= empty($bank) ? 'disabled' : '' ?>>…</button>
    <button type="button" class="pb-method-tab" data-metode="tunai_transfer" role="tab" aria-selected="false" <?= empty($bank) ? 'disabled' : '' ?>>…</button>
  </div>

  <select id="metode_bayar" name="metode_bayar" class="form-select pb-method-native">…opsi lama…</select>

  <div class="pb-pay-split" id="pb-pay-split">
    <div class="pb-cash-panel tunai-section">…label, #bayar, #uang-pas, #isi-sisa…</div>
    <div class="pb-bank-panel transfer-section">
      <div class="pb-bank-head">…label Transfer, hitungan rekening…</div>
      <div id="bank-payment-container" class="pb-bank-list"></div>
      <button type="button" id="tambah-bank" class="pb-btn pb-btn--outline pb-bank-add">Tambah rekening</button>
      <p class="pb-bank-note">…</p>
    </div>
  </div>

  <p class="pb-pay-empty" id="pb-pay-empty" hidden>Pilih metode untuk membuka kolom nominal.</p>

  <div class="pb-alloc">
    <div class="pb-alloc__figures">
      <span>Total <b id="pb-alloc-total">Rp 0</b></span>
      <span>Dibayar <b id="pb-alloc-dibayar">Rp 0</b></span>
      <span>Sisa <b id="pb-alloc-sisa">Rp 0</b></span>
    </div>
    <p class="pb-pay-status" id="pb-pay-status" aria-live="polite"></p>
    <span class="pb-meter"><i id="pb-meter"></i></span>
  </div>

  <p class="pb-pay-note" id="pb-pay-note">…</p>
</div>
```

Ketentuan:
- `#metode_bayar` tetap select; tambahkan kelas `pb-method-native`.
- `#jatuh-tempo-field` tetap di dalam `.pb-pay-head`.
- `#tambah-bank` hanya dirender bila `$bank` tidak kosong; `pb-bank-note` hanya bila kosong.
- `#isi-sisa` hanya dirender bila `$bank` tidak kosong (split butuh rekening).
- `#uang-pas` adalah tombol baru; `#isi-sisa` tetap ada.
- Di dock, bungkus figur dengan `data-dock="lunas"` (Dibayar/Sisa) dan `data-dock="tempo"` (Hutang); `#hutang`/`#kembalian` tetap ada.
- `#kembalian` tetap di DOM tetapi wrapper-nya selalu `hidden` (tidak diberi `data-dock`), karena Lunas menolak kelebihan dan Tempo tidak memakai kembalian.

- [ ] **Step 4: Tambahkan CSS band, tab, panel, dan responsive**

Tambahkan selector baru di sekitar CSS pembayaran:

```css
.pb-pay-band { display: grid; gap: 16px; }
.pb-pay-head { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 14px 16px; }
.pb-method-tabs { display: none; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 4px; padding: 4px; border: 1px solid var(--pb-rule); border-radius: var(--pb-radius-sm); background: var(--pb-subtle); }
.pb-js .pb-method-tabs { display: grid; }
.pb-method-tab { min-height: 44px; border: 0; border-radius: 7px; background: transparent; color: var(--pb-muted); font-size: .8rem; font-weight: 600; }
.pb-method-tab[aria-selected="true"] { background: var(--pb-surface); color: var(--pb-strong); box-shadow: none; }
.pb-method-tab:disabled { opacity: .45; cursor: not-allowed; }
.pb-pay-split { display: grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr); gap: 0; border: 1px solid var(--pb-rule); border-radius: var(--pb-radius-sm); overflow: clip; }
.pb-pay-split.is-tunai, .pb-pay-split.is-transfer { grid-template-columns: minmax(0, 1fr); }
.pb-pay-split.is-tunai .pb-bank-panel, .pb-pay-split.is-transfer .pb-bank-panel { border-left: 0; }
.pb-pay-empty { margin: 0; padding: 18px 16px; border: 1px dashed var(--pb-rule-strong); border-radius: var(--pb-radius-sm); color: var(--pb-muted); font-size: .8rem; text-align: center; }
.pb-cash-panel, .pb-bank-panel { min-width: 0; padding: 16px; }
.pb-bank-panel { border-left: 1px solid var(--pb-rule); background: var(--pb-subtle); }
.pb-bank-list { display: grid; gap: 10px; }
.pb-js .pb-method-native { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }
```

Responsive:
- `@media (max-width: 1199.98px)`: `.pb-pay-head` satu kolom; `.pb-pay-split` tetap dua kolom bila muat.
- `@media (max-width: 991.98px)`: `.pb-pay-split` satu kolom; `.pb-bank-panel` border-left 0 + border-top hairline.
- `@media (max-width: 767.98px)`: tab tiga kolom kompak, label boleh membungkus; `.pb-alloc` tiga kolom; target sentuh ≥44px, termasuk `.pb-row-del` (`width: 44px; height: 44px;`).
- `@media (max-width: 575.98px)`: `.pb-pay-head` satu kolom; hint metode disembunyikan; tombol tambah rekening full width.

Hapus aturan `.pb-pay-grid` lama dan referensinya di media query.

- [ ] **Step 5: Tambahkan sinkronisasi tab → select**

Di script pembayaran:

> **Scope fungsi:** definisikan `setMetode`, `syncTabMetode`, `updateAlokasi`,
> `updateDockMode`, dan `tampilkanErrorPembayaran` sebagai fungsi **top-level**
> di blok script pembayaran (bukan di dalam `$(document).ready`), supaya bisa
> dipanggil dari `updateHutang` dan submit guard di blok script lain. Handler
> klik/keyboard tetap boleh berada di dalam `$(document).ready`.

```js
document.documentElement.classList.add('pb-js');
const metodeNative = document.getElementById('metode_bayar');
if (metodeNative) metodeNative.setAttribute('tabindex', '-1');

function setMetode(value) {
    const select = document.getElementById('metode_bayar');
    if (!select || select.value === value) return;
    select.value = value;
    $(select).trigger('change');
}

function syncTabMetode() {
    const select = document.getElementById('metode_bayar');
    const aktif = select ? select.value : '';
    document.querySelectorAll('#pb-method-tabs [data-metode]').forEach(function (tab) {
        const selected = tab.getAttribute('data-metode') === aktif;
        tab.setAttribute('aria-selected', selected ? 'true' : 'false');
        tab.setAttribute('tabindex', selected ? '0' : '-1');
    });
}

$('#pb-method-tabs').on('click', '[data-metode]', function () {
    if (this.disabled) return;
    setMetode(this.getAttribute('data-metode'));
});
```

Panggil `syncTabMetode()` di akhir `aturPembayaran()` dan setelah `#metode_bayar` berubah. Pastikan listener `$('#metode_bayar').on('change', aturPembayaran)` tetap ada dan tidak digandakan.

Di `aturPembayaran()`, setelah `pakaiTunai`/`pakaiBank` didefinisikan, tambahkan pengaturan layout panel. Di cabang `!lunas`, set `split.hidden = true` dan `empty.hidden = true` sebelum `return`:

```js
const split = document.getElementById('pb-pay-split');
const empty = document.getElementById('pb-pay-empty');
if (split) {
    split.hidden = !(pakaiTunai || pakaiBank);
    split.classList.toggle('is-tunai', pakaiTunai && !pakaiBank);
    split.classList.toggle('is-transfer', pakaiBank && !pakaiTunai);
    split.classList.toggle('is-split', pakaiTunai && pakaiBank);
}
if (empty) empty.hidden = !lunas || pakaiTunai || pakaiBank;
```

- Tanpa metode: split disembunyikan, `#pb-pay-empty` tampil.
- Tunai/Transfer saja: split satu kolom, panel yang tidak dipakai disembunyikan.
- Tunai + Transfer: split dua kolom.
- Tempo: split dan empty state disembunyikan.

- [ ] **Step 6: Jalankan page test sampai lulus**

Run perintah Step 2. Expected: `OK` untuk `tests/PembelianPageTest.php`.

- [ ] **Step 7: Cek sintaks dan checkpoint**

```bash
php -l app/Views/transaksi/pembelian.php
php -l app/Views/transaksi/_pembelian_theme.php
git diff --check
```

Expected: tidak ada syntax error; `git diff --check` tanpa output. Jangan commit.

---

### Task 2: Ringkasan alokasi, mode dock, aksi cepat, validasi inline

**Files:**
- Modify: `app/Views/transaksi/pembelian.php:628-658` (`sinkronPembayaranOtomatis`)
- Modify: `app/Views/transaksi/pembelian.php:945-1015` (`updateHutang`, `updateStatusPembayaran`)
- Modify: `app/Views/transaksi/pembelian.php:1360-1400` (submit guard)
- Modify: `app/Views/transaksi/_pembelian_theme.php:793-929` (status, meter, dock)
- Test: `tests/PembelianPageTest.php`

**Interfaces:**
- Consumes: DOM/JS dari Task 1.
- Produces JS: `function updateAlokasi()`, `function updateDockMode()`, `function tampilkanErrorPembayaran(pesan, fokusEl)`.
- Produces DOM behavior: `#uang-pas`, `#isi-sisa` (disabled saat sisa 0), `#pb-meter`, `[data-dock]` visibility.

- [ ] **Step 1: Tulis page test yang gagal**

```php
public function testAlokasiDanDockMemilikiLogikaSinkron(): void
{
    $html = $this->render();

    $this->assertStringContainsString('function updateAlokasi(', $html);
    $this->assertStringContainsString('function updateDockMode(', $html);
    $this->assertStringContainsString('id="uang-pas"', $html);
    $this->assertStringContainsString('id="isi-sisa"', $html);
    $this->assertStringContainsString('Uang pas', $html);
    $this->assertStringContainsString('Isi sisa', $html);
}

public function testValidasiPembayaranMemakaiPesanInline(): void
{
    $html = $this->render();

    $this->assertStringContainsString('function tampilkanErrorPembayaran(', $html);
    $this->assertStringContainsString('Pembayaran Lunas harus tepat', $html);
    $this->assertStringNotContainsString("alert('Pembayaran Lunas harus tepat", $html);
    $this->assertStringContainsString('ArrowRight', $html);
    $this->assertStringContainsString('ArrowLeft', $html);
    $this->assertStringContainsString('Home', $html);
    $this->assertStringContainsString('End', $html);
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run perintah Task 1 Step 2. Expected: FAIL pada `updateAlokasi`, `updateDockMode`, `tampilkanErrorPembayaran`, dan `Uang pas`.

- [ ] **Step 3: Implementasikan `updateAlokasi()` dan `updateDockMode()`**

```js
function updateAlokasi() {
    const total = totalPembelian();
    let dibayar = unformatRupiah((document.getElementById('bayar') || {}).value || 'Rp 0');
    document.querySelectorAll('.bank-amount').forEach(function (input) {
        dibayar += unformatRupiah(input.value || 'Rp 0');
    });

    const sisa = Math.max(total - dibayar, 0);
    const lebih = Math.max(dibayar - total, 0);
    const totalEl = document.getElementById('pb-alloc-total');
    const dibayarEl = document.getElementById('pb-alloc-dibayar');
    const sisaEl = document.getElementById('pb-alloc-sisa');
    if (totalEl) totalEl.textContent = 'Rp ' + total.toLocaleString('id-ID');
    if (dibayarEl) dibayarEl.textContent = 'Rp ' + dibayar.toLocaleString('id-ID');
    if (sisaEl) sisaEl.textContent = 'Rp ' + sisa.toLocaleString('id-ID');

    const meter = document.getElementById('pb-meter');
    if (meter) {
        const rasio = total > 0 ? Math.min(dibayar / total, 1) : 0;
        meter.style.width = (rasio * 100) + '%';
        meter.classList.toggle('is-pas', dibayar === total);
        meter.classList.toggle('is-lebih', lebih > 0);
    }
}

function updateDockMode() {
    const jenis = (document.getElementById('jenis_pembayaran') || {}).value || '';
    document.querySelectorAll('.pb-dock [data-dock]').forEach(function (el) {
        const mode = el.getAttribute('data-dock');
        el.hidden = !(jenis === 'lunas' && mode === 'lunas') &&
                    !(jenis === 'tempo' && mode === 'tempo');
    });
}
```

Ketentuan:
- `updateHutang()` tetap menghitung `hutang`/`kembalian` untuk kontrak form, lalu memanggil `updateAlokasi()` dan `updateDockMode()`.
- `updateStatusPembayaran()` mengisi `#pb-pay-status` dengan `Pas` / `Kurang Rp …` / `Lebih Rp … — Lunas tidak menerima kelebihan`, memakai kelas `is-pas`/`is-kurang`/`is-lebih`.
- Untuk Lunas, `#hutang` dan `#kembalian` tetap diisi tetapi wrapper `[data-dock]`-nya `hidden`.
- Untuk Tempo, tampilkan Hutang dan sembunyikan Dibayar/Sisa/Kembalian.

- [ ] **Step 4: Implementasikan aksi cepat dan state tombol**

```js
$('#uang-pas').on('click', function () {
    const total = totalPembelian();
    $('#bayar').val(formatToRupiah(String(total)));
    updateHutang();
});

$('#isi-sisa').on('click', function () {
    const sisa = Math.max(totalPembelian() - totalBankTerisi(), 0);
    $('#bayar').val(formatToRupiah(String(sisa)));
    updateHutang();
});
```

- Tampilkan `#uang-pas` hanya untuk metode `tunai`; tampilkan `#isi-sisa` hanya untuk `tunai_transfer`.
- `#isi-sisa` `disabled` bila sisa 0.
- Jangan menambahkan listener kedua untuk `#bayar`.

- [ ] **Step 5: Ganti alert pembayaran dengan pesan inline**

```js
function tampilkanErrorPembayaran(pesan, fokusEl) {
    const status = document.getElementById('pb-pay-status');
    if (status) {
        status.textContent = pesan;
        status.classList.remove('is-pas', 'is-lebih');
        status.classList.add('is-kurang');
    }
    if (fokusEl && typeof fokusEl.focus === 'function') fokusEl.focus();
}
```

Di submit guard:
- metode kosong → `tampilkanErrorPembayaran('Pilih metode pembayaran Lunas.', document.querySelector('#pb-method-tabs [data-metode]'))`;
- total tidak tepat → `tampilkanErrorPembayaran('Pembayaran Lunas harus tepat Rp ' + total.toLocaleString('id-ID') + '.', document.getElementById('bayar'))`;
- jangan memakai `alert()` untuk dua kasus ini. `alert()` untuk pelanggan/produk tetap boleh (di luar scope).

- [ ] **Step 6: Tambahkan keyboard tablist**

```js
$('#pb-method-tabs').on('keydown', '[data-metode]', function (e) {
    const tabs = Array.from(document.querySelectorAll('#pb-method-tabs [data-metode]:not(:disabled)'));
    const index = tabs.indexOf(this);
    if (index < 0) return;
    let next = null;
    if (e.key === 'ArrowRight') next = tabs[(index + 1) % tabs.length];
    if (e.key === 'ArrowLeft') next = tabs[(index - 1 + tabs.length) % tabs.length];
    if (e.key === 'Home') next = tabs[0];
    if (e.key === 'End') next = tabs[tabs.length - 1];
    if (!next) return;
    e.preventDefault();
    next.focus();
    setMetode(next.getAttribute('data-metode'));
});
```

- [ ] **Step 7: Tambahkan CSS status, meter, dan dock**

```css
.pb-alloc { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.pb-alloc__figures { display: flex; gap: 16px; flex-wrap: wrap; }
.pb-alloc b { color: var(--pb-strong); font-variant-numeric: tabular-nums; }
.pb-meter { display: block; width: 100%; height: 3px; border-radius: 999px; background: var(--pb-rule); overflow: hidden; }
.pb-meter > i { display: block; width: 0; height: 100%; background: var(--pb-danger); transition: width .2s ease; }
.pb-meter > i.is-pas { background: var(--pb-ok); }
.pb-meter > i.is-lebih { background: var(--pb-danger); }
.pb-pay-status.is-pas { color: var(--pb-ok); }
.pb-pay-status.is-kurang, .pb-pay-status.is-lebih { color: var(--pb-danger); }
@media (prefers-reduced-motion: reduce) { .pb-meter > i { transition: none; } }
```

- [ ] **Step 8: Jalankan page test sampai lulus**

Run perintah Task 1 Step 2. Expected: `OK`.

- [ ] **Step 9: Verifikasi perilaku manual**

Buka `/pembelian` di browser yang terhubung ke aplikasi, lalu cek:

- Lunas tunai: `Uang pas` mengisi total; status `Pas`; dock menampilkan Total/Dibayar/Sisa.
- Lunas transfer: satu baris otomatis; ubah total → nominal ikut hanya bila satu baris.
- Lunas tunai + transfer: isi bank, klik `Isi sisa`; status `Pas`; `Isi sisa` disabled saat sisa 0.
- Kurang/lebih: status danger; submit diblokir; tidak ada Kembalian untuk Lunas.
- Tempo: tab/zona hilang; Jatuh Tempo wajib; dock menampilkan Hutang.
- Tanpa rekening: tab transfer disabled; catatan muncul.
- Keyboard: Tab ke tablist, ArrowLeft/Right, Home/End, Enter/Space.

Jika browser tidak tersedia, catat langkah manual ini sebagai pending di laporan akhir; jangan klaim sudah diverifikasi visual.

- [ ] **Step 10: Checkpoint**

```bash
php -l app/Views/transaksi/pembelian.php
git diff --check
```

Jangan commit.

---

### Task 3: Dokumentasi, responsive/a11y, verifikasi akhir

**Files:**
- Modify: `.impeccable/surfaces/pembelian.md` (bagian Pembayaran + Unresolved decisions)
- Modify: `docs/superpowers/specs/2026-11-20-pembelian-split-payment-design.md:139-157` (cross-reference)
- Modify: `docs/superpowers/specs/2026-11-20-redesign-lunas-pembelian-design.md` (status sudah disetujui)
- No PRD changes.

**Interfaces:**
- Consumes: implementasi Task 1–2.
- Produces: rekaman desain yang sesuai dengan markup final; bukti test.

- [ ] **Step 1: Perbarui surface brief**

Di `.impeccable/surfaces/pembelian.md`:
- Ganti deskripsi "Payment method sits directly under Jenis Pembayaran…" dengan tab metode + panel split + ringkasan alokasi.
- Perbarui "Unresolved decisions" yang masih menyebut bank field satu kartu vertikal.
- Catat dock Lunas: Total/Dibayar/Sisa; Tempo: Total/Hutang.
- Catat fallback select native + `pb-js`.

- [ ] **Step 2: Perbarui cross-reference design split-payment**

Di `docs/superpowers/specs/2026-11-20-pembelian-split-payment-design.md` bagian 7.4, tambahkan satu paragraf:

```markdown
> UI final mengikuti `docs/superpowers/specs/2026-11-20-redesign-lunas-pembelian-design.md`
> (payment console: tab metode, panel split, ringkasan alokasi). Sketsa di atas
> hanya kontrak perilaku, bukan markup final.
```

- [ ] **Step 3: Jalankan seluruh test terkait**

```bash
for f in tests/PembelianPageTest.php tests/PembelianHppUnitTest.php tests/PembelianBankScopeTest.php tests/PembelianJatuhTempoTest.php tests/PembelianUnitInputTest.php tests/unit/PembelianPaymentSplitTest.php; do
  docker exec -w /app -e database.tests.hostname=erp-db -e database.tests.database=erp_pembelian_test -e database.tests.username=erp -e database.tests.password=erp -e database.tests.DBDriver=MySQLi -e database.tests.DBPrefix= erp-app vendor/bin/phpunit -c phpunit.xml.dist --no-coverage "$f" 2>&1 | tail -3
done
```

Expected: setiap file `OK`; tidak ada failure/error baru.

- [ ] **Step 4: Render halaman dan cek sintaks JS**

Buat test sementara (hapus setelah selesai) yang merender `transaksi/pembelian` ke `/tmp/opencode/pembelian-rendered.html`, lalu:

```bash
node - /tmp/opencode/pembelian-rendered.html <<'NODE'
const fs = require('fs');
const html = fs.readFileSync(process.argv[2], 'utf8');
const blocks = [...html.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g)];
blocks.forEach((m, i) => {
  if (m[1].trim()) fs.writeFileSync(`/tmp/opencode/pb-${i}.js`, m[1]);
});
NODE
for f in /tmp/opencode/pb-*.js; do node --check "$f" || exit 1; done
echo "JS OK"
```

Expected: `JS OK`, tidak ada syntax error. Hapus test sementara dan file render setelah selesai.

- [ ] **Step 5: Pemeriksaan manual responsive/a11y**

Di browser, cek 1440, 1024, 768, 390px; terang dan gelap:

- Tidak ada scroll horizontal.
- Tab tiga kolom rapi; label tidak terpotong.
- Panel split menumpuk di bawah 992px.
- Baris bank tidak meluber; tombol hapus ≥44px di mobile.
- Ringkasan alokasi dan dock tidak tumpang tindih.
- Fokus keyboard terlihat di tab, input, select2, tombol.
- Status `Pas/Kurang/Lebih` terbaca di kedua tema.
- `prefers-reduced-motion`: meter/tab tidak beranimasi.

Jika browser tidak tersedia, catat sebagai pending; jangan klaim visual sudah diverifikasi.

- [ ] **Step 6: Cek sintaks PHP dan diff**

Sebelum task ini, simpan diff Penjualan yang sudah ada (perubahan user) sebagai pembanding:

```bash
git diff -- app/Views/transaksi/_penjualan_theme.php tests/PenjualanPageTest.php > /tmp/opencode/penjualan-before.diff
```

Lalu:

```bash
php -l app/Views/transaksi/pembelian.php
php -l app/Views/transaksi/_pembelian_theme.php
php -l tests/PembelianPageTest.php
git diff --check
git diff --stat
git diff -- app/Views/transaksi/_penjualan_theme.php tests/PenjualanPageTest.php > /tmp/opencode/penjualan-after.diff
diff -u /tmp/opencode/penjualan-before.diff /tmp/opencode/penjualan-after.diff
```

Expected:
- `php -l` tanpa error.
- `git diff --check` tanpa output.
- `diff -u` Penjualan tanpa output (perubahan user tidak bertambah/berubah).

- [ ] **Step 7: Perbarui issue #227 ke Testing**

Karena belum ada commit, komentar menyebut "belum commit" dan hasil test aktual:

```bash
gh issue comment 227 --repo bwafi/ERP-iClear --body-file /tmp/opencode/issue-227-testing.md
gh project item-edit --project-id PVT_kwHOBrFnns4BmskD --id PVTI_lAHOBrFnns4BmskDzhKdrU0 --field-id PVTSSF_lAHOBrFnns4BmskDzhlUhds --single-select-option-id 7dc918ef
```

Isi komentar minimal: file yang diubah, hasil tiap test, hasil `php -l`/`git diff --check`/JS check, status manual visual (atau pending), dan catatan belum commit.

- [ ] **Step 8: Laporan akhir**

Laporkan:
- ringkasan perubahan;
- file yang diubah;
- PRD/business rules yang diperiksa (Modul 03 aturan 20, Modul 05 BR 27; tidak ada perubahan perilaku);
- perintah test dan hasil aktual;
- risiko/keterbatasan (select native + JS, manual visual pending bila tidak ada browser, mapping multi-rekening);
- status dokumentasi (surface brief + cross-reference; PRD tidak berubah).
