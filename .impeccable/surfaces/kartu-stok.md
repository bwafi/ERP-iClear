---
version: 1
slug: "kartu-stok"
primary_target: "kartu_stok"
related_targets: ["app/Controllers/Kartu_Stok.php","app/Models/ModelKartuStok.php"]
---

# Surface brief — Kartu Stok (/kartu_stok)

## Scope & mode

- Scope: `/kartu_stok` — view `app/Views/stok/_kartu_theme.php` + `app/Views/stok/kartu_stok.php`. Route, controller, model, schema, business logic, permissions, and field names untouched. Only the DataTables column-index map in `Kartu_Stok::dt()` moved with the visible columns.
- Visitor mode: **Operate** — supervisor/Finance scanning stock per product per unit, and unit roles correcting HPP or deactivating a product-unit pair.
- Audience: roles 0/1/34/35/41/47 manage HPP and status; other authenticated roles read the same ledger.
- Job: 1) answer "berapa stok barang ini di unit ini, dan dari mana angkanya", 2) filter by unit and PPN, 3) export the filtered ledger, 4) correct HPP or hide a zero-stock product from its unit.
- Proof/content: `stok_barang` rows joined to barang/kategori/supplier, `hpp_unit`/`hpp_barang` HPP resolution, and movement totals (pembelian, penjualan, mutasi, retur).
- Constraints: DataTables server-side, unit + PPN filters, xlsx export, HPP modal, status toggle, role guards, CSRF, and every existing ID/JS hook preserved. Indonesian throughout. Light and dark from the app's own theme.

## Chosen direction & memorable moment

**Buku Besar** (ledger sheet): one quiet page with a thin header, a single summary strip of four figures separated by hairlines, one filter row, and one sheet of rows — no icon-tile stat cards, no boxed card around the table, no badge boxes. Supplier leaves the row and joins the expanded detail, so the main ledger shows only what the operator scans: kode, barang, unit, kategori, PPN, stok akhir, aksi.

Memorable moment: the row opens into a four-column ledger detail — identitas, harga & stok, stok masuk, stok keluar — where every movement carries its direction (`+` masuk, `−` keluar) instead of a flat list of totals.

## Direction contract

THESIS: A stock card is a ledger, not a dashboard, so the page is one sheet with one summary strip and one filter line; it refuses the incumbent's four icon-tile cards plus boxed toolbar plus nine-column table that hides the useful detail.

OWN-WORLD: Established stock-module world, inherited not invented — same resolved theme tokens (`--bs-card-bg`, `--bs-border-color`, `--bs-body-color`), 14px sheet radius, 9px controls, hairline rules instead of card borders, `tabular-nums` on every count, Iconify `solar:*` glyphs only. One accent (`#0077c4` / `#8ab4ff` dark) for focus and the HPP action; status reads as a 6px dot plus text; no gradients, no colored left borders, no new palette, no new fonts.

STORY: The operator lands, reads four figures that state the whole ledger, narrows to one unit or PPN status, scans the sheet, opens a row to see where the stock came from, corrects HPP if their role allows, and exports exactly what they filtered.

FIRST VIEWPORT: At 1440×900 the thin header (breadcrumb, title, one-line description), then the four-figure strip with hairline dividers, then the filter row (Unit, Status PPN, search, Export), then the sheet's sticky column headings and the first rows visible before the fold; the row actions sit at the right edge as a chevron plus a labelled switch.

FORM: Ledger sheet — extension of the existing stok-module surface world (inherits `.ms-*`/`.so-*` composition), not a replacement world; no new-world concept roll, no seed key.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance.

## Unresolved decisions

- Mobile keeps the sheet as a horizontally scrolling table (760px min width) instead of turning rows into cards like Mutasi Stok. If mobile use becomes a primary path, convert rows to the `.ms-table` stacked-card pattern.
- Supplier moved from the main row to the detail panel. If operators need supplier while scanning, add it back as an eighth column rather than a subtitle.
- The summary strip describes the unfiltered store-wide ledger; it does not follow the unit/PPN filter. Making it react to filters would require a new aggregate endpoint.
