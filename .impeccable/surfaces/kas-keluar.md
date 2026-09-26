---
version: 1
slug: "kas-keluar"
primary_target: "kas_keluar"
related_targets:
  - "app/Controllers/Kas_Keluar.php"
  - "app/Models/ModelKasKeluar.php"
---

# Surface brief — Kas Keluar (/kas_keluar)

## Scope & mode

- Scope: `/kas_keluar` — route `Kas_Keluar::index` (view `app/Views/jurnal/kas_keluar.php`), the server-side `kas_keluar/datatables` endpoint, and `ModelKasKeluar`'s read/filter builder. Scope confirmed by the user 2026-09-26: **daftar + modal** — table, filters, search, summary, and the input/edit/delete surfaces.
- Visitor mode: **Operate** — finance/back-office at the counter or on the floor, writing cash-out entries and reconciling the ledger.
- Audience: finance, purchasing, admin center (`ID_JABATAN` 0/1/2/34 see all units; other roles are unit-locked). Indonesian, Rupiah, Modernize light/dark.
- Job (user-ranked, 2026-09-26): 1) input a new cash-out, 2) watch cash flow per unit, 3) reconcile — find one specific transaction fast. Audit/correction is *not* a named job, so edit is made correct and safe but is not the hero.
- Proof/content: the real ledger — 1,738 journal lines, `kas_keluar.*` joined to `kategori_kas`, `bank`, `unit`, `no_akun`. Each inserted row also writes one `jurnal` line (`tabel_referensi = 'kas_keluar'`).
- Constraints: no change to business logic, DB schema, routes, field names, or role gating. Unit locking (`[0,1,2,34]`), the multi-position insert loop, and the single-position update route are preserved exactly. Indonesian UI, Rupiah throughout.

## Chosen direction & memorable moment

Two-deck console: a persistent composer deck docked above the ledger deck. The entry form lives in the document, not in a modal, so writing a transaction and reading the ledger are the same screen. The Edit form reuses that same deck in single-position mode (the shape `update_kas_keluar` actually accepts) instead of shipping a second, near-duplicate modal.

Memorable moment: type `1850` in search and the transaction surfaces — a numeric query is detected as an ID, resolved by indexed exact match, and answered with a clickable `ID #1850 ditemukan` chip that selects the row. On a 1,738-row ledger where the incumbent search returns 499 rows for `3`, that is the product's own primary key finally being usable.

## Direction contract

THESIS: Writing a cash-out entry and reading the ledger are one task, so the page is two decks rather than a list plus a modal — a composer deck docked above a ledger deck, with the running Rupiah total anchored in the composer and never hidden behind a full-screen sheet. Refuses the incumbent's empty header card, its twelve equal-weight columns, and its modal-only entry path.

OWN-WORLD: Committed Modernize admin tokens only, consumed as resolved CSS vars (`--bs-primary`, `--bs-success`, `--bs-warning`, `--bs-danger`, `--bs-border-color`, `--bs-body-bg`, `--bs-secondary-color`) so Blue_Theme's `#0085db` primary and `#4bd08b` success and every dark-mode value come from the app itself. Debet = primary-tinted chip, Kredit = warning-tinted chip, danger reserved for Hapus alone. Iconify `solar:*` for chrome, Bootstrap Icons for row glyphs. Tabular-nums for every Rupiah figure and for IDs. Bordered 12px-radius containers, one elevation level, no gradients, no new fonts, no new palette.

STORY: Finance opens the page and the composer is already there, folded to a single line. They tap it, fill tanggal/unit/deskripsi, add as many posisi akun as the entry needs, watch the total settle as they type, and save — without the ledger ever leaving the screen. To reconcile, they type in one search field: a number resolves as an ID and answers with a clickable chip naming that exact transaction; anything else searches deskripsi, kategori, penerima, and no. akun. Filters show as removable chips so the current scope is never guesswork.

FIRST VIEWPORT: A single full-width header line — title, breadcrumb, and the period/unit scope in one row, no separate header card. Below it the composer deck, folded: one 56px bar reading `Input Kas Keluar` with the live draft summary (tanggal · n pos · total) right-aligned and a primary unfold control. Below that the ledger deck's command bar: the search field at 320px as the deck's hero, date range and unit beside it, active-filter chips, result count, export, and a column toggle. Then the ledger, nine weighted columns plus Aksi — ID mono-muted, Tanggal, Kategori as a chip, No Akun mono with nama_akun beneath, Deskripsi taking the surplus width, Penerima with rekening beneath, Jumlah right-aligned in tabular numerals at the largest size on the row, Posisi as a semantic chip — with a true filtered total in the footer. Bank, nama bank, and no. rekening are deliberately folded into the Penerima cell as its second line rather than kept as columns of their own: at 1.738 rows they were three mostly-empty columns buying nothing.

FORM: The dealt surface structure, rank 5 of 7 on the grounded list, seed key f2a11c7c (THE ROLL). Composition: two-deck console — composer deck over ledger deck.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Unresolved decisions

- Column visibility toggle (show/hide Unit and No. Akun) shipped and was kept: it is the only way to reclaim the surplus width on a 1280 laptop, and it is scoped to the two genuinely secondary columns rather than offered for all nine. Had it read as clutter it was the first thing to cut.
- Faceted filters (kategori, jenis, bank) were ranked candidates 1 and were **not** picked with the structure; the user asked for search, not a facet rail. Left out deliberately — revisit only on request.
- Mobile stacked-card ledger: `text-nowrap` is dropped below 768px and each row becomes a card, since a 12-field record cannot be a table on a phone.

## Verdict

Shipped, with the two-deck console intact and every contract preserved. The memorable moment is real and measured, not asserted: `1850` resolves to one row in 0.14s, and `3` — which the incumbent answered with 499 substring matches — returns nothing, because a bare number is now an exact primary-key lookup with `search_mode=text` as the escape hatch.

The strongest thing here is not visual. It is that the surface stopped lying about scale. The old footer totalled only the visible page, so `Total` disagreed with the filter the user had just applied; it now sums the whole filtered set and holds steady across all 70 pages. The old search answered a different question than the one being asked. The old `!important`-forced dark theme flattened every table to one blue-grey, which this surface answers at its own scope only.

Kept, deliberately: the column toggle, the empty-position submit guard, and a date default of today (the incumbent had it; losing it would have been a silent regression in the entry path).

## Provenance & verification

- Code: `app/Views/jurnal/kas_keluar.php` (rewritten), `app/Controllers/Kas_Keluar.php` (`datatable` payload + `columnMap`), `app/Models/ModelKasKeluar.php` (`applyKasKeluarFilter` + `sum`/`find`/`inScope`). No route, schema, field-name, or role-gating change.
- `php -l` clean on all three; view script extracted and `node --check` clean; no CJK/garbled text in the view.
- Endpoint, live against `erp_local`: baseline 1.738 / `Rp 699.752.128` · `1850` → 1 row, `Rp 220.000`, `inScope=true` · `PARKIR&search_mode=text` → 145 / `Rp 1.844.800` · `3` → 0 · `9999` → 0 · `1850` inside a 2020 date range → 0 rows **with** `idHit.inScope=false`, so "exists but filtered out" is stated rather than shown as an empty table.
- Browser (Chromium 153, CDP, desktop + mobile + dark): 25 rows / 10 columns, no DataTables dialogs, composer open with live total, edit prefill (`#1850`, kategori 7, `debet`, `220000`), delete modal, column toggle 10→9, pager 1→3→5→next→1 with the footer total unchanged, and header sorting verified per column.
- Geometry: `docScrollWidth == clientWidth` at 1440 / 1280 / 768 / 390 / 360 in **both** composer states; no sub-32px tap targets on mobile.
- Contrast (computed, not eyeballed): light secondary `#707070` ≈ 4.95:1 on white; dark secondary `rgb(150,155,163)` ≈ 6.13:1 on `#111c2d`; dark primary `#fff` ≈ 17:1.
- Detector: `.opencode/skills/impeccable/scripts/impeccable detect --json` on all three files → `[]` (no findings).
- Rasters: `.impeccable/review/kas-keluar-*.png` (9 files, 1440 desktop / 390 mobile, light + dark).
- **Pending human/visual sign-off:** the reviewing model has no image input, so the rasters were verified by computed geometry, colour, and DOM measurement rather than by eye. Iconify also could not load (no network), so glyph rendering is unverified. A vision-capable reviewer should eyeball the nine rasters before this is called finished.

