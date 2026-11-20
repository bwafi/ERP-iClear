# Redesign Riwayat Pembelian — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyelaraskan halaman `/riwayat_pembelian` dengan bahasa visual halaman riwayat lain (hero + chip, toolbar ringkas, tabel hairline, pagination kustom, modal senada) tanpa mengubah route, controller, model, filter, atau export.

**Architecture:** Satu tema halaman baru `app/Views/riwayat/_riwayat_pembelian_theme.php` (semua selector di bawah `.rb-page` / `.rb-modal`) di-include di baris pertama view; `app/Views/riwayat/pembelian.php` ditulis ulang menjadi hero + toolbar + panel tabel + modal, dengan DataTables server-side, filter, dan detail AJAX yang sama.

**Tech Stack:** PHP CodeIgniter 4.4, Bootstrap 5, jQuery 3.7, DataTables 1.13 server-side, Iconify Solar, CSS token aplikasi.

**Spec:** `docs/superpowers/specs/2026-11-20-redesign-riwayat-pembelian-design.md`

## Global Constraints

- Tanpa perubahan `app/Controllers/Riwayat_pembelian.php`, model, route, schema, filter, atau export.
- `tests/RiwayatPembelianPageTest.php` lulus tanpa diubah: id `pembelianTable`, `unitFilter`, `detailModal`, `detailBatch`, `detailItems`, `detailLoading`, `detailError`; class `js-detail`; string `<option value="">Semua Unit</option>` dan `<option value="2">Unit Dua</option>`; teks `Akun Anda hanya dapat melihat unit ini.`; variabel JS `startDateInput`, `endDateInput`, `unitFilter`, `searchBox`, `pageLength`, `exportForm`; `serverSide: true`, `order: [[1, 'desc']]`.
- Tanpa dependency baru, tanpa `alert()`, tanpa `!important` sebagai jalan pintas konflik.
- Semua selector tema di bawah `.rb-page` / `.rb-modal`; satu aksen `#0b5f8a` (gelap `#7cc4f0`).

## Review Focus

- Nota tanpa rincian barang (`nama_barang` null) → tampil "Tanpa rincian barang", bukan sel kosong atau `undefined`.
- `sisa` = 0 / null → tidak ada baris "Sisa Rp 0" palsu.
- Select unit `disabled` (role satu unit) → chip unit menampilkan nama unitnya, bukan "Semua unit".
- Dua klik Detail cepat berturut-turut → modal menampilkan nota terakhir, bukan respons pertama.
- Nama barang panjang dan nomor nota panjang → tidak merusak lebar tabel; kolom tetap terbaca di 1440px dan 1024px.

---

### Task 1: Kontrak tampilan baru (test gagal dulu)

**Files:**
- Create: `tests/RiwayatPembelianRedesignTest.php`

**Interfaces:**
- Consumes: `view('riwayat/pembelian', ['boleh_semua_unit' => bool, 'unit' => object[]])`.
- Produces: nama class/ID yang dipakai Task 2 dan 3: `.rb-page`, `.rb-hero`, `.rb-toolbar`, `.rb-card`, `.rb-table`, `#rbCountValue`, `#rbUnitChip`, `#rbRangeChip`, `#rbSearch`.

- [ ] **Step 1: Tulis test kontrak redesign**

```php
<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

final class RiwayatPembelianRedesignTest extends CIUnitTestCase
{
    private function render(bool $semua = true): string
    {
        return view('riwayat/pembelian', [
            'boleh_semua_unit' => $semua,
            'unit' => [(object) ['idunit' => 2, 'NAMA_UNIT' => 'Unit Dua']],
        ]);
    }

    public function testMemakaiTemaRbSendiri(): void
    {
        $html = $this->render();
        $this->assertStringContainsString("view('riwayat/_riwayat_pembelian_theme')", $html);
        $this->assertStringContainsString('class="rb-page"', $html);
        $this->assertStringContainsString('rb-hero', $html);
        $this->assertStringContainsString('rb-toolbar', $html);
        $this->assertStringContainsString('rb-table', $html);
    }

    public function testChipKonteksTersedia(): void
    {
        $html = $this->render();
        $this->assertStringContainsString('id="rbCountValue"', $html);
        $this->assertStringContainsString('id="rbUnitChip"', $html);
        $this->assertStringContainsString('id="rbRangeChip"', $html);
    }

    public function testHeaderTabelMemakaiIstilahNota(): void
    {
        $html = $this->render();
        $this->assertStringContainsString('>No. Nota<', $html);
        $this->assertStringContainsString('<caption class="visually-hidden">', $html);
        $this->assertStringContainsString('scope="col"', $html);
    }

    public function testSisaHutangDanPpnDirender(): void
    {
        $html = $this->render();
        $this->assertStringContainsString('rb-sub--warn', $html);
        $this->assertStringContainsString('Sisa ', $html);
        $this->assertStringContainsString('PPN', $html);
    }

    public function testExportDanPencarianTetapAda(): void
    {
        $html = $this->render();
        $this->assertStringContainsString('riwayat_pembelian/export', $html);
        $this->assertStringContainsString('id="rbSearch"', $html);
        $this->assertStringContainsString('aria-label="Cari nota pembelian"', $html);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `vendor/bin/phpunit -c phpunit.xml tests/RiwayatPembelianRedesignTest.php`
Expected: FAIL — `rb-page` / tema belum ada.

- [ ] **Step 3: Commit test**

```bash
git add tests/RiwayatPembelianRedesignTest.php docs/superpowers/specs/2026-11-20-redesign-riwayat-pembelian-design.md docs/superpowers/plans/2026-11-20-redesign-riwayat-pembelian.md
git commit -m "test(riwayat_pembelian): kontrak tampilan redesign (refs #229)"
```

### Task 2: Tema `.rb-*`

**Files:**
- Create: `app/Views/riwayat/_riwayat_pembelian_theme.php`

**Interfaces:**
- Produces: token dan class yang dipakai Task 3 — `.rb-hero`, `.rb-crumb`, `.rb-chip`, `.rb-toolbar`, `.rb-search`, `.rb-field`, `.rb-control`, `.rb-range`, `.rb-actions`, `.rb-btn`, `.rb-btn--ghost`, `.rb-scope-note`, `.rb-card`, `.rb-card__head`, `.rb-table-wrap`, `.rb-table`, `.rb-note`, `.rb-strong`, `.rb-sub`, `.rb-sub--warn`, `.rb-money`, `.rb-btn-detail`, `.rb-totals`, `.rb-meta`, `.rb-alert`, `.rb-empty`, `.rb-modal`, `.rb-modal__head`, `.rb-modal__title`, `.rb-modal__icon`, `.rb-modal__foot`, `.rb-loading-block`.

- [ ] **Step 1: Tulis file tema**

Token turunan variabel tema (`--bs-card-bg`, `--bs-border-color`, `--bs-body-color`) + override `html[data-bs-theme="dark"]`. Struktur blok: token → page → hero/crumb/chip → toolbar/field/control/button → card/table/cell → DataTables (`.dataTables_paginate .page-link`, `.dataTables_info`, `.dataTables_processing`, `.dataTables_empty`) → modal (meta strip, totals, loading, alert) → responsive (`@media (max-width: 991.98px)`, `(max-width: 575.98px)`) → `prefers-reduced-motion`.

- [ ] **Step 2: Periksa sintaks**

Run: `php -l app/Views/riwayat/_riwayat_pembelian_theme.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Views/riwayat/_riwayat_pembelian_theme.php
git commit -m "feat(riwayat_pembelian): tema .rb-* untuk halaman riwayat pembelian (refs #229)"
```

### Task 3: View hero + toolbar + tabel + modal

**Files:**
- Modify: `app/Views/riwayat/pembelian.php` (tulis ulang, JS tetap server-side)

**Interfaces:**
- Consumes: tema Task 2; data `$boleh_semua_unit`, `$unit`; route `riwayat_pembelian/ajax`, `riwayat_pembelian/detail/`, `riwayat_pembelian/export`.
- Produces: markup final; `window.rbReload` tidak dipakai (fungsi lokal `reload`).

- [ ] **Step 1: Ganti markup + renderer**

Hero (crumb `Riwayat / Pembelian`, h1, deskripsi, chip `#rbCountValue` / `#rbUnitChip` / `#rbRangeChip`), toolbar (search `#rbSearch`, `#startDate`–`#endDate`, `#unitFilter`, `#pageLength`, Reset, form export), panel tabel `#pembelianTable` dengan `<caption>` dan `scope="col"`, modal `#detailModal` (meta strip 4 sel, `#detailItems`, totals, `#detailLoading`, `#detailError`). Pertahankan semua id/nama variabel yang dikunci test dan string `<option>` persis.

- [ ] **Step 2: Renderer kolom**

- No. Nota: `.rb-note`
- Tanggal: tanggal + nama hari (`.rb-sub`)
- Barang: nama + `+N barang lain` atau "Tanpa rincian barang"
- Sumber: "Suplier"/"Pelanggan" + `nama_suplier` sebagai `.rb-sub`
- Total: `.rb-money` + `Sisa …` sebagai `.rb-sub.rb-sub--warn` hanya bila `sisa > 0`
- Aksi: `.rb-btn-detail.js-detail` dengan `data-id`

- [ ] **Step 3: Chip hidup + state**

`drawCallback` memperbarui `#rbCountValue` dari `settings.json.recordsFiltered`, `#rbRangeChip` dari input tanggal (`Semua tanggal` / `Sejak …` / `Sampai …` / rentang), `#rbUnitChip` dari opsi terpilih atau nama unit saat select `disabled`. `emptyTable` memakai `.rb-empty`; `processing` memakai `.rb-loading-block`; error detail memakai `.rb-alert`.

- [ ] **Step 4: Jalankan test halaman**

Run: `vendor/bin/phpunit -c phpunit.xml tests/RiwayatPembelianPageTest.php tests/RiwayatPembelianRedesignTest.php`
Expected: PASS semua.

- [ ] **Step 5: Commit**

```bash
git add app/Views/riwayat/pembelian.php tests/RiwayatPembelianRedesignTest.php
git commit -m "feat(riwayat_pembelian): redesign halaman riwayat pembelian (refs #229)"
```

### Task 4: Verifikasi akhir

- [ ] **Step 1: Sintaks + seluruh test terkait**

Run: `php -l app/Views/riwayat/pembelian.php && vendor/bin/phpunit -c phpunit.xml tests/RiwayatPembelianPageTest.php tests/RiwayatPembelianRedesignTest.php tests/RiwayatPembelianServerSideTest.php`
Expected: semua PASS.

- [ ] **Step 2: Detektor Impeccable**

Run: `.opencode/skills/impeccable/scripts/impeccable detect --json app/Views/riwayat/pembelian.php app/Views/riwayat/_riwayat_pembelian_theme.php`
Expected: tanpa temuan yang belum disengaja; perbaiki temuan valid dalam satu batch.

- [ ] **Step 3: Diff bersih**

Run: `git diff --check && git status --short`
Expected: tidak ada whitespace error; hanya file yang direncanakan.
