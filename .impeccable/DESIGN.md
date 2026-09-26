# DESIGN — Surface design decisions

Repo: ERP (CodeIgniter 4 + Bootstrap 5 Modernize admin, light/dark). World established and committed (see PRODUCT.md); designs below use Modernize tokens only and compose within the committed world.

Token correction: the briefs' OWN-WORLD hex (#5D87FF primary, #13DEB9 success) are the template's default-light values; the app's active Blue_Theme overrides primary to `#0085db` and success to `#4bd08b` in `public/template/assets/css/styles.css`. All surfaces consume the resolved CSS vars (`--bs-primary`, `--bs-success`, `--bs-border-color`, …) so the shipped colors are the app's actual tokens.

---

## /service — Vertical journey timeline

Status: shipped 2026-09-25 · direction rank 5/7, seed `bb50b654`. Brief: `.impeccable/surfaces/service.md` (with verdict + provenance).

### Composition
- **Header card** unchanged (title + breadcrumb).
- **Two-column deck** `#service-deck`: left `#service-rail` (vertical milestone rail, 4 nodes, connector line) · right the active step's form card with consistent `.service-step-footer` (Sebelumnya outline-secondary / Selanjutnya primary, Submit success).
- **Running ticket summary** `.service-summary-strip` pins pelanggan · perangkat · kerusakan n · sparepart n · DP · total across the top once a ticket exists (`#service-summary-col` hidden when no ticket).
- Responsive: ≥1200 vertical rail; ≤1199 horizontal stepper (overflow-x auto); ≤575 2×2 grid wrap — every step visible without scroll.

### Rail states (applied by `refreshServiceState()` on input/change/tab and at boot)
- done = success check node + green connector; active = primary ring on visible step; locked = dashed tertiary node, `pointer-events:none`, opacity .6; not-started = numbered node, helper copy in `.service-rail-sub`.
- `is-locked` beats `is-done` (kasir jabatan-36 sparepart step stays locked even when ticket rows exist).
- Helper copy gates later steps when no ticket ("Lengkapi data pelanggan dulu.").

### Behavior contracts kept
- 4 exact steps/routes; each step posts its own form; `?tab=` deep links + localStorage `activeTab` restore + tab memory; `shown.bs.tab` drives rail active ring; role gating preserved (kasir sparepart lock via `show.bs.tab` preventDefault + inert link).

### Defects found & fixed during verification (see brief verdict)
1. `role="tablist"` missing → Bootstrap Tab `_parent=null` → `Illegal invocation`, tabs dead. Fixed.
2. Lock/done precedence bug stripped `is-locked`. Fixed (lock always wins).
3. Mobile rail overflow → 2×2 wrap. Fixed.

### Verification
- `php -l` clean on the 5 changed views.
- Playwright/Chromium suite 23/23 (layout, rail states, switching, summary mirrors, kasir lock, empty state, deep-link; desktop + mobile).
- Detector: only template-wide tokens (Modernize muted text ~4.1–4.4:1, breadcrumb ol, func-card chips) — pre-existing world conditions.
- Rasters: `.impeccable/review/service-desktop.png`, `.impeccable/review/service-mobile.png`.

---

## /kas_keluar — Two-deck cash-out console

Status: shipped 2026-09-26 · brief + verdict + provenance: `.impeccable/surfaces/kas-keluar.md`. Operate mode (finance writing and reconciling cash-out). Scope: `Kas_Keluar::index` view, `kas_keluar/datatables` endpoint, `ModelKasKeluar` read/filter builder.

### Composition
- **Composer deck** `#kkComposer` docked above the ledger deck, folded to a 56px bar carrying the live draft summary (tanggal · n pos · total). The entry form lives in the document — no modal — and Edit reuses the same deck in single-position mode, matching what `update_kas_keluar` actually accepts.
- **Ledger deck**: command bar (320px search as hero, date range, unit, removable filter chips, count, export, column toggle) over a 9+1 column ledger. Bank / nama bank / no. rekening are folded into the Penerima cell's second line instead of standing as three mostly-empty columns.
- Footer total sums the **whole filtered set**, not the visible page, so `Total` never disagrees with the active filter. Colspan is computed from `$canAct` (7 + nominal + 2-or-1) and verified to land under the Jumlah column.
- Mobile: ledger rows become stacked cards below 768px; the composer stays a real form (a 7-field multi-position entry is not a card).

### Decisions worth keeping
- **Bare digits are an exact primary-key lookup.** `1850` → one row; `3` → nothing, where the incumbent's substring match returned 499 rows. `search_mode=text` is the documented escape hatch. Resolution is answered separately from the table (`idHit.inScope`) so "this ID exists but sits outside your date range" is *said* instead of rendered as an empty ledger.
- **The app force-flattens tables in dark mode.** `styles.css` has `[data-bs-theme="dark"] .table > :not(caption) > * > * { color: #7c8fac !important; }` — an app-wide `!important` that collapses every table cell to one blue-grey and, here, made sub-lines *brighter* than primary text. Answered with equal priority at higher specificity, scoped to `.kk-table` only (`[data-bs-theme="dark"] .kk-scope .kk-table tbody td { color: var(--kk-ink) !important; }`). Other tables in the app are untouched. This is the one place this surface argues with the template, and it argues narrowly.
- **Wide tables scroll inside their deck, never the page.** `.kk-tablewrap` and `.kk-composer-body` both carry `contain: paint`. Without it, `.table-responsive` clipped the position table correctly yet the overflow still propagated up and the whole document scrolled sideways on a 390px phone. Verified `docScrollWidth == clientWidth` at 1440/1280/768/390/360 with the composer both folded and open.
- **Folded composer leaves the flow entirely** (`display: none`, plus a one-shot open animation) rather than collapsing to `0fr`; a collapsed-but-present body kept widening the document.
- Ink hierarchy is a 3-step mix off the committed tokens (`--kk-ink` / `-2` / `-3` via `color-mix`), so hierarchy holds in both themes instead of being hand-picked per theme.
- **`--bs-card-bg` is not a global token.** It is declared only inside `.card` (`styles.css:5477`), so any custom surface that reaches for it outside a card resolves to nothing and silently paints the page canvas — which is exactly how these decks once rendered `#f0f5f9` / `#15263a` instead of card stock. Decks here take `var(--bs-body-bg)` at 1.125rem radius and the app's single elevation `0 2px 6px rgba(37,83,185,.1)`, with no hairline border, so masthead and decks match `/kas_masuk` pixel-for-pixel; `--kk-shadow` is the only new token and it carries an existing value.

### Preserved exactly
Route names, field names, multi-position insert, single-position update, unit locking (`[0,1,2,34]`), and jabatan gating. Date defaults to today as the incumbent did.

### Verification
`php -l` clean; `node --check` on the extracted view script; endpoint contracts exercised live (1.738 / `Rp 699.752.128` baseline, 145-row text search, exact-ID hit and miss, out-of-scope ID); browser pass over desktop/mobile/dark with no DataTables dialogs; computed contrast 4.95:1 light and 6.13:1 dark. Detector: `[]`. Rasters: `.impeccable/review/kas-keluar-*.png` (9 files) — geometry/colour verified programmatically; **eyeball sign-off still pending a vision-capable reviewer**, and Iconify glyphs unverified offline.

---

## /asset_berjalan — Month-ruler report deck

Status: shipped earlier (see `.impeccable/surfaces/dashboard-asset-berjalan.md`, seed `b777bde7`). 6 committed metrics preserved; month/unit ruler as hero element; formula notes kept. Rasters: `.impeccable/review/desktop.png`, `.impeccable/review/mobile.png`.