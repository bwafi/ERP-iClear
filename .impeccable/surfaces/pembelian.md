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
- Job: 1) pilih pihak dan dokumen, 2) pilih/tambah produk beserta harga beli, diskon, biaya, dan PPN, 3) tetapkan pembayaran tunai/bank dan lihat hutang, 4) simpan sekali tanpa kehilangan angka akhir.
- Proof/content: data produk per unit dari controller (`hpp_unit` fallback `harga_beli`), supplier, bank, pelanggan, dan akun login untuk frontliner.
- Constraints: semua `id`, `name`, hook JS, modal, dan alur POST dipertahankan; jQuery/DataTables/Select2/Bootstrap tetap dipakai; light dan dark dari token aplikasi.

## Chosen direction & memorable moment

**Lembar Nota** (purchase sheet): satu lembar tenang dengan header tipis, tiga bagian bernapas — Pihak & Dokumen, Produk, Pembayaran — dan satu ringkasan pembayaran yang menempel di bawah. Tidak ada tumpukan card, tidak ada header kosong, tidak ada tabel penuh border.

Memorable moment: dock pembayaran yang selalu terlihat — Total, Dibayar, Hutang, Kembalian, dan Simpan dalam satu baris, sehingga angka akhir tidak pernah hilang saat menggulir daftar produk.

## Direction contract

THESIS: A purchase entry is a nota, not a dashboard; the page is one sheet with three quiet sections and a payment dock that never leaves the viewport, refusing the incumbent's nested cards, empty header strip, and scattered totals.

OWN-WORLD: Established module world inherited from `.tj-*`/`.ks-*`: resolved theme tokens (`--bs-card-bg`, `--bs-border-color`, `--bs-body-color`), 16px sheet radius, 9–10px controls, hairline rules instead of card borders, `tabular-nums` on every money figure, Iconify `solar:*` glyphs only. One accent (`#0077c4` / `#8ab4ff` dark) for focus and the primary action; status reads as text, not badges. No gradients, no colored left borders, no new palette, no new fonts.

STORY: The operator lands, sees where they are, fills the party and document fields, picks products into a ledger table, sets cash/bank, watches the dock settle the totals, and saves once with the balance visible.

FIRST VIEWPORT: At 1440×900 the thin header (breadcrumb, title, one-line description) sits above the sheet; Pihak & Dokumen is already filled out in one compact grid; the Produk toolbar and the first rows are visible before the fold; the payment dock is pinned at the bottom edge with the primary Simpan action at its right.

FORM: Lembar nota — page inside the established purchase/stok module world (inherits `.pb-*` composition), not a new-world concept roll; no seed key.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance.

## Unresolved decisions

- Mobile keeps the product table horizontally scrollable with a 1080px min width; converting each row to a stacked card is left for a later pass if mobile becomes a primary input path.
- The dock shows Kembalian inline; if overpay becomes rare in practice it can collapse to a secondary line without changing IDs.
- The product picker mirrors the Terima Service sparepart picker: icon-plus-heading header, one search field with a unit badge, and a scrollable card list (round tick, name, bold code, icon metadata, HPP as the strong figure with the sale price beneath). The footer pairs a count and Total HPP with `Batal` / `Tambahkan`, disabled until something is picked. Filtering is client-side and selection lives in a `Map`, so picks survive a search. On small screens the unit badge hides and the price row drops below the card text.
- Payment method sits at the top of the Pembayaran section, next to Bayar Tunai, so the cash/bank fields it controls appear directly below it.
