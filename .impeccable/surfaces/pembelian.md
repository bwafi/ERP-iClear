---
version: 1
slug: "pembelian"
primary_target: "pembelian"
related_targets: ["app/Views/transaksi/pembelian.php","app/Views/transaksi/_pembelian_theme.php"]
---

# Surface brief — Pembelian (/pembelian)

## Scope & mode

- Scope: `/pembelian` — view `app/Views/transaksi/pembelian.php` + `app/Views/transaksi/_pembelian_theme.php`. Route, controller, model, schema, business logic, permissions, and field names untouched. Only markup, styles, and dead JS references change.
- Visitor mode: **Operate** — kasir/frontliner mencatat barang masuk, memilih supplier atau pelanggan, mengisi harga beli, dan menentukan pembayaran.
- Audience: kasir unit dan supervisor yang menginput pembelian harian; Finance membaca hasilnya lewat HPP, stok, dan hutang.
- Job: 1) pilih pihak dan dokumen, 2) pilih/tambah produk beserta harga beli, diskon, biaya, dan PPN, 3) tetapkan pembayaran tunai/transfer dan lihat sisa atau hutang, 4) simpan sekali tanpa kehilangan angka akhir.
- Proof/content: data produk per unit dari controller (`hpp_unit` fallback `harga_beli`), supplier, bank, pelanggan, dan akun login untuk frontliner.
- Constraints: semua `id`, `name`, hook JS, modal, dan alur POST dipertahankan; jQuery/DataTables/Select2/Bootstrap tetap dipakai; light dan dark dari token aplikasi.

## Chosen direction & memorable moment

**Satu Lembar** (one purchase sheet): satu alur vertikal tanpa kolom kedua. Header tipis, lalu lembar yang mengalir dari **Pihak & Dokumen → Produk → Pembayaran → Ringkasan/Simpan**. Padat tetapi tenang: satu aksen, hairline sebagai pemisah, dan angka uang selalu tabular.

Memorable moment: footer ringkasan di kaki lembar. Total tampil paling besar. Untuk **Lunas**, footer menampilkan Total, Dibayar, dan Sisa (Hutang/Kembalian disembunyikan); untuk **Tempo**, Total dan Hutang. Simpan Pembelian di ujung kanan—semua keputusan akhir terbaca sebelum submit tanpa memecah halaman.

## Direction contract

THESIS: A purchase entry is one continuous sheet, not a dashboard and not a split workspace; party, products, payment, and the final money answer follow in a single vertical read, refusing the incumbent's nested cards and the two-column rail.

OWN-WORLD: Established module world inherited from `.tj-*`/`.ks-*`: resolved theme tokens (`--bs-card-bg`, `--bs-border-color`, `--bs-body-color`), 16px sheet radius, 9–10px controls, hairline rules instead of card borders, `tabular-nums` on every money figure, Iconify `solar:*` glyphs only. One accent (`#0077c4` / `#8ab4ff` dark) for focus and the primary action; status reads as text, not badges. No gradients, no colored left borders, no new palette, no new fonts.

STORY: The operator lands, fills party and document in one compact row, picks products into a ledger where identity (name, code, category, IMEI) is one cell, sets Lunas/Tempo, checks the footer totals, and saves once.

FIRST VIEWPORT: At 1440×900 the thin header (breadcrumb, title, one-line description, unit scope) sits above the sheet; Pihak & Dokumen is one compact row, the Produk toolbar and first rows are visible before the fold, and the footer totals are one short scroll away.

UNIT SCOPE: Unit pembelian milik role lintas unit (Finance/Root/Manager) adalah konteks halaman, bukan field nota. Kontrolnya tinggal di kolom kanan header — label mikro `Unit pembelian`, satu trigger berikon toko + nama unit + chevron, dan hint `Stok & HPP mengikuti unit ini.` Trigger membuka menu Bootstrap (`.pb-scope__menu`): header `Pilih unit` + jumlah unit, lalu daftar unit operasional (`unit.is_operational = 1`; Head Office tidak tampil) sebagai tautan `?unit=` dengan tanda centang pada unit aktif. Menu memakai permukaan `--pb-surface` dan teks `--pb-strong` sendiri supaya tetap terbaca di tema gelap, tanpa shadow. Role tim toko melihat bentuk statis (`pb-scope--static`). Nilai unit dikirim lewat hidden input `unit_idunit` di dalam form. Daftar rekening pada **Pembayaran via Bank** mengikuti unit ini: hanya rekening milik unit dan rekening shared yang dialokasikan ke unit (`ModelBank::getBankDalamScopeUnit`), tanpa rekening unit lain, Finance/HO, atau rekening nonaktif.

FORM: Satu lembar (`.pb-sheet`). Urutan: `Pihak & Dokumen` (grid lima kolom: sumber, supplier/pelanggan, tanggal masuk, frontliner, nota), `Produk` (toolbar + ledger sembilan kolom), `Pembayaran` (band lebar: jenis + jatuh tempo, tab metode, panel split tunai/transfer, ringkasan alokasi), lalu footer `.pb-dock` (Diskon, PPN, Total, lalu figur kondisional: Dibayar/Sisa untuk Lunas atau Hutang untuk Tempo; Simpan). Grid pihak/produk melipat ke 3/2/1 kolom pada 1200/992/576px; panel split menumpuk di bawah 992px.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance.

## Unresolved decisions

- The product table keeps its nine-column compact shape; at narrow widths it stays horizontally scrollable rather than converting rows to stacked cards.
- The footer `.pb-dock` is a normal part of the sheet, not a floating sticky bar; it wraps at tablet widths and becomes a two-column figure grid with a full-width save action on phones.
- Diskon and PPN stay computed summary inputs in the footer (same `readonly` contract as before); per-row Diskon remains the editable source.
- The product picker mirrors the Terima Service sparepart picker: icon-plus-heading header, one search field with a unit badge, and a scrollable card list (round tick, name, bold code, icon metadata, HPP as the strong figure with the sale price beneath). The footer pairs a count and Total HPP with `Batal` / `Tambahkan`, disabled until something is picked. Filtering is client-side and selection lives in a `Map`, so picks survive a search. On small screens the unit badge hides and the price row drops below the card text.
- Payment is a full-width band, not a grid cell. Jenis Pembayaran and Jatuh Tempo share `.pb-pay-head`; Jatuh Tempo only shows for Tempo. For Lunas, `#metode_bayar` stays a native select but is visually replaced by a `role="tablist"` of three tabs (Tunai / Transfer / Tunai + Transfer) that write to the select and trigger `aturPembayaran()`. `pb-js` on `<html>` hides the native select visually (clipped, removed from tab order, still the value source) and shows the tabs; without JS the select remains visible and usable. Transfer tabs are disabled when the unit has no active bank account.
- Lunas zones live in `.pb-pay-split`: a cash panel on the left (large nominal, `Uang pas`, `Isi sisa`) and a bank panel on the right (vertical `.pb-bank-row` cards with `Rekening Bank` above `Nominal`, plus `Tambah rekening`). Tunai-only and Transfer-only collapse to one column; Tunai + Transfer uses two columns until 992px. `.pb-alloc` below the zones shows Total / Dibayar / Sisa, a status line (`Pas`, `Kurang`, `Lebih — Lunas tidak menerima kelebihan`), and a 3px meter; it is hidden outside Lunas.
- `#hutang` and `#kembalian` stay in the DOM and are still submitted, but their dock figures are hidden for Lunas (Kembalian always hidden). Tempo shows Total + Hutang. Payment validation uses inline `#pb-pay-status` messages instead of `alert()`; the keyboard tablist supports ArrowLeft/ArrowRight/Home/End.
