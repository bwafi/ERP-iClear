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

Ledger-first with a centered modal (2026-10-27 revision; supersedes the two-deck console, then the side sheet). The list owns the page. Input and edit open in a Bootstrap modal capped at 640px, so the ledger stays visible behind the backdrop and never loses scroll position while one row is corrected. One card in the modal is one `kas_keluar` record — the shape the backend actually stores — instead of a wide table of account rows that had to be scrolled sideways on a phone.

Memorable moment: the summary strip reads back what the ledger is doing — `Total terfilter / Masuk 6% / Di luar 6%` — and the `Semua · 6% · Di luar 6%` control filters the list server-side. Type `1850` in search and the transaction surfaces as an exact-ID chip, unchanged from the previous build.

## Direction contract

THESIS: A cash-out page is a ledger you occasionally write into. The ledger is the page; writing and correcting happen in a compact centered dialog that covers as little of it as the form allows, so a 1,738-row list never gets pushed down or permanently taxed. Refuses the incumbent's empty header card, its twelve equal-weight columns, and the two-deck console's permanent composer strip — as well as a side sheet that shrinks the ledger to a sliver on a laptop.

OWN-WORLD: Committed Modernize admin tokens only, consumed as resolved CSS vars (`--bs-primary`, `--bs-success`, `--bs-warning`, `--bs-danger`, `--bs-border-color`, `--bs-body-bg`, `--bs-secondary-color`). Debet = primary-tinted chip, Kredit = warning-tinted chip, danger reserved for Hapus. Success tint is mixed into the ink for the 6% badge and summary so it stays readable light and dark. Iconify `solar:*` for chrome. Tabular-nums for every Rupiah figure and ID. `--kk-surface: var(--bs-body-bg)`, 1.125rem radius, the single existing elevation `0 2px 6px rgba(37,83,185,.1)`; no gradients, no new fonts, no new palette.

STORY: Finance opens the page, scans the six-column ledger, and reads the 6% split at a glance. To write, they hit `Input Kas Keluar`, fill tanggal/unit/deskripsi, add as many transaction cards as the entry needs, watch the draft total and its 6%/other split settle, and save without the list leaving the screen. To reconcile, they type in one field — a number resolves as an exact ID — or flip `Semua / 6% / Di luar 6%`. Active filters appear as removable chips.

FIRST VIEWPORT: Masthead with title, breadcrumb, scope readout, and a primary `Input Kas Keluar` button. Below it the ledger card: command bar (search 320px, Dari/Sampai, Unit, `Semua · 6% · Di luar 6%`, Export, Reset), a three-cell summary strip, filter chips, then the table — Tanggal (with `#ID · unit` beneath), Kategori (chip + green `6%` badge), Keterangan (deskripsi, then penerima/bank), No. Akun (with nama akun), Jumlah (right-aligned tabular, Debet/Kredit chip beneath), Aksi. Footer carries the filtered total across all pages.

FORM: Centered modal (`modal-dialog-centered modal-dialog-scrollable`, `max-width: 640px`); header names the mode (`Input Kas Keluar` / `Edit Kas Keluar #id`) with a live `n transaksi · total` sub-line; body is a two-column card grid that becomes one column under 576px; a sticky footer holds total, the 6%/other split, Batal, Simpan, so the save button stays reachable while the transaction cards scroll. Edit reuses the modal in single-position mode (the shape `update_kas_keluar` accepts).

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review and this brief.

## Unresolved decisions

- Column visibility toggle was dropped: it existed to hide Unit and No. Akun, and Unit is now folded into the Tanggal cell. Icons/columns can come back only if a real user asks.
- Faceted filters (kategori, jenis, bank) remain out of scope. The 6% mode is a compliance split, not a facet rail.
- Mobile: table rows become stacked cards under 768px via `data-label`; the summary strip becomes two columns with Total on its own row.

## Provenance & verification

- Code: `app/Views/jurnal/kas_keluar.php` (ledger-first rewrite), `app/Controllers/Kas_Keluar.php` (`columnMap`, `kategori_mode`, per-row `enam`, `kk_old` repopulation), `app/Models/ModelKasKeluar.php` (`applyKasKeluarFilter` + `summaryKasKeluarFiltered`), `app/Config/Finance.php` (single source for the 6% list). No route, schema, field-name, or role-gating change.
- `php -l` clean on the four PHP files; extracted view script `node --check` clean; detector `impeccable detect app/Views/jurnal/kas_keluar.php` → no findings.
- `vendor/bin/phpunit -c phpunit.xml tests/KasKeluarUnitFilterTest.php` → 11 tests / 79 assertions, including server-side `kategori_mode` and summary, modal markup, and rejected-input replay. `tests/AssetBerjalanFilterTest.php` → 15 / 52.
- Not re-verified in a browser this revision: no screenshot or geometry pass was run, so contrast, overflow at 360/390/768/1280/1440, and Select2 positioning inside the modal are unconfirmed. Rasters in `.impeccable/review/kas-keluar-*.png` predate this direction and must not be treated as current.
