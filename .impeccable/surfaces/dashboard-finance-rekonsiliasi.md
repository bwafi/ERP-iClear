---
version: 1
slug: "finance-rekonsiliasi"
primary_target: "dashboard/finance_rekonsiliasi"
related_targets:
  - "app/Controllers/DashboardFinance.php"
  - "app/Views/dashboard/finance_rekon_form.php"
---

# Surface brief — Rekonsiliasi Harian (/finance/rekonsiliasi)

> **Superseded in part (2026-09-27).** The Sunday handling in this brief
> ("Minggu bukan hari kerja", muted index rows, Monday–Saturday KPI
> denominator) turned out to contradict the business rule — reconciliation is
> required for **every calendar day** — and it shipped a KPI bug with it:
> a Mon–Sat denominator against an all-days numerator let filling Sundays
> produce a capped 100% score over unfilled weekdays. All of it has been
> changed. See `.impeccable/DESIGN.md` → "Aturan rekonsiliasi harian" and
> `.impeccable/surfaces/dashboard-finance-rekon-form.md`. The rest of this
> brief is kept as the record of that session.

> **Panels are now editable (2026-09-27).** The per-day panel is an input
> form, not a summary: it POSTs to the same `finance/rekon/save` using the
> same shared input partial as the form page, with one **Simpan & Kirim**
> button per day. A modal was considered and rejected — the URL stops being
> shareable — and the dedicated form page stays because approval, the
> verified lock, and the durable deep-link still live there. This honours
> the "no modal" constraint below rather than bending it. See
> `.impeccable/DESIGN.md` → "Panel daftar yang bisa diisi". What this brief
> describes as read-only `.rk-comps` cards and the `Isi Rekonsiliasi` link
> now applies to roles **without** input rights only.

## Scope & mode

- Scope: route `finance/rekonsiliasi` (`DashboardFinance::rekonsiliasi`), view `app/Views/dashboard/finance_rekonsiliasi.php`. Redesign is view-only: no controller, route, model, schema, or approval-logic change. The day form (`finance/rekon/form`) is a separate surface and stays as it is; this page routes to it, never re-implements it.
- Visitor mode: **Operate** — finance/admin closing the day's books at the end of the shift, and the manager clearing the verification queue.
- Audience: `ID_JABATAN` 0/1/2/34 (see all units), others unit-locked. Indonesian, Rupiah, Modernize light/dark.
- Job (user-ranked 2026-09-27): **close out the days that are not finished yet** — "tutup hari yang belum beres". Reading the month is secondary; the page must make "what is left" the first thing it says.
- Proof/content: one row per calendar day of the selected month up to today, from `RekonDailyCalculator::monthlyList` — `erp_*` (from penjualan/service/kas_keluar), `actual_*` (typed by finance), `selisih_*` (server-computed), `catatan`, `catatan_revisi`, `status_proses`, and the input/submit/verify trail. 27–31 days, most of them empty records.
- Constraints: GET filter contract `unit_id` + `month` + `status` unchanged (user-confirmed 2026-09-27); no invented metrics, charts, or derived money figures; no modal; no change to status vocabulary (`RekonDailyCalculator::labelStatus` / `labelProses` / `badgeProses` are the app's contract, and `app/Scripts/recon_http_smoke.php` asserts on their output).
- Feel bar (user-confirmed 2026-09-27): **professional**. A finance back-office page has to look like it can be trusted with a number.

## Chosen direction & memorable moment

Master–detail day console: a dense day index on the left, the selected day opened on the right. The index is a *date navigator*, not a spreadsheet — the money lives in the panel, where each of the three components gets a full block instead of one cramped `a / b / c` cell.

Memorable moment: the month answers "how am I doing" in one line. The first viewport states the situation as a sentence with clickable counts — `18 hari belum diisi · 2 perlu revisi · 3 menunggu verify · 4 selesai` — and the KPI score sits at the right end of the same line. The first day you are told to act on is already open in the panel when the page loads, so the page starts on the work, not on a blank selection.

## Direction contract

THESIS: A reconciliation month is a queue of unfinished days, not a spreadsheet, so the page is a day index plus one open day. Refuses the incumbent's three equal stat cards, its eight-column table, and its three run-on `ERP / Aktual / Selisih` cells that force a sideways glance to read one number.

OWN-WORLD: Committed Modernize tokens only, as resolved CSS vars (`--bs-body-bg`, `--bs-emphasis-color`, `--bs-secondary-color`, `--bs-tertiary-bg`, `--bs-border-color`, `--bs-primary`, `--bs-success`, `--bs-danger`, `--bs-warning` and their `-bg-subtle` pairs) so Blue_Theme's `#0085db` primary and `#4bd08b` success and every dark value come from the app. Card stock is `--bs-body-bg` at 1.125rem with the app's single elevation `0 2px 6px rgba(37,83,185,.1)`, no hairline border, no new palette, no new font, no gradient. Every Rupiah figure and date in `font-variant-numeric: tabular-nums`. No `.table` markup on this surface: the template's `[data-bs-theme="dark"] .table … { color:#7c8fac !important }` flattens every cell in dark mode, and a day index does not need a table. Text-only controls; no icon dependency (Bootstrap Icons is CDN-loaded and unverified offline).

STORY: Finance opens the page and the month speaks first: how many days are untouched, how many bounced back, how many are waiting on a manager, and what the KPI score is. The day most in need of them is already open in the panel with its three components side by side — ERP above Aktual, Selisih as the only colored number — plus the revision note when a manager sent it back. They read, fix what is wrong, and leave through the day's own form. A manager clears the queue the same way, from the same panel, and `Verified` days read as locked rather than as done.

FIRST VIEWPORT: One header line (title, breadcrumb, unit + period) on the app's card stock, no empty card under it. Then a single status band, not a row of stat cards: scope text and period on the left, the five counts (Belum diisi · Draft · Perlu revisi · Menunggu verify · Selesai) as inline click-through GET filters in the middle, and the KPI score with its meter on the right; with a status filter active the band cannot show those counts (the controller filters the list before the view sees it), so it becomes `Menampilkan *Perlu Revisi* · 2 dari 23 hari kerja · Tampilkan semua`, taking the denominator from the unfiltered KPI detail. Below, the two-column console: 42% day index (sticky month label, then one row per day — `25 Sep · Jum`, result line, a `catatan revisi` marker) and 58% panel, sticky, showing the selected day. The panel's own grid: day header with weekday and both status badges, a state sentence saying whose move it is or why it is blocked, then one block per component, each a three-column readout (ERP / Aktual / Selisih); then catatan and catatan revisi; then the input/submit/verify trail; then the action to that day's form. Under 992px the console is one column, the index head stops being sticky (the topbar wraps to 140px there), and the open day sits directly under its row in the list.

FORM: The dealt surface structure, the locked card at index 6 of 7 on the grounded list, seed key `75c520e9` (THE ROLL round). Composition: day index + selected-day panel.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Unresolved decisions

- Selection rides an optional `hari=YYYY-MM-DD` GET param, so a manager can be sent straight to a day and the no-JS path is a plain link. `unit_id`, `month`, and `status` are untouched and still carry the filter; the three existing params keep working with no `hari` at all.
- The count for days with no record at all cannot be a link: `status` has no value for "no row", and the filter is server-side. It is rendered as plain text inside an otherwise clickable band, rather than faked with a status value that would lie.
- Quick-entry inline in the panel was rejected: `finance/rekon/save` carries the lock rule (`VERIFIED` is immutable) and the submit/verify separation of duties. The panel routes to the day form instead of duplicating those rules.
- The three component values per day are shown in the panel only. The index deliberately carries no money column, because a synthetic per-day total would be a derived figure the product does not compute.
- Sundays are kept in the index and labelled, not filtered out. `countHariKerja()` scores Monday–Saturday while `monthlyList()` returns every calendar day, so the page shows 27 listed against 23 scored; hiding Sundays in a view would have been a silent behaviour change, and a Sunday can legitimately hold work. The mismatch is stated once in the legend and per-row as "Bukan hari kerja".
- Semantic text colours are tinted toward the theme's ink (`--rk-bad-ink` / `--rk-good-ink` via `color-mix`) because Blue_Theme's `--bs-danger` `#fb977d` and `--bs-success` `#4bd08b` are only 2.37:1 and 2.14:1 on the white card as text. Badges keep `bg-danger` / `bg-success` unchanged so the status vocabulary stays the app's.

## Verdict

**Shipped, with one honest gap.** The surface now opens on the work: the most urgent day (`need_revision` → incomplete draft → complete draft → no record → submitted → verified, newest first) is already in the panel at load, the month states its queue in one line, and every path out of the page is a plain GET link, so the whole thing still works with JS off. Nothing in the brief's promises is missing, and the GET filter contract, status vocabulary, and smoke assertions are intact.

The gap: **eyeball sign-off was not performed in this session.** Geometry, hierarchy, contrast, and behaviour were verified programmatically (computed styles, hit rectangles, DOM interaction, and the rasters below), but I have no vision here, so no human looked at a single pixel of the result. Treat the screenshots as evidence to review, not as a sign-off. Two smaller caveats ride along: the template's own JS throws `Element not found` offline (pre-existing — it is in the before captures too, from missing CDN assets), and the day form (`finance/rekon/form`) is a separate surface that was deliberately left alone, so the click-through target still looks like the old design.

## Provenance & verification

- **Code**: `app/Views/dashboard/finance_rekonsiliasi.php` is the only file changed (`git status`: one modified file). `DashboardFinance`, `RekonDailyCalculator`, and `finance_rekon_form.php` were read, not touched.
- **Syntax**: `php -l` clean.
- **Contract**: `php74 app/Scripts/recon_http_smoke.php` → 83 PASS / FAIL 0, including all nine list assertions (`Lengkap - Selisih`, no legacy label, no `Sudah Diperiksa` column, `Perlu Revisi`, thousands separator, revision note, `badgeProses` on need_revision, and the KPI score). The `finance/rekon/form?unit_id=` link is gone with the page it pointed at; every former form assertion was repointed at the per-day panel.
- **Data**: real DB was read first (`finance_rekon_daily` holds 2 submitted rows for September 2026 — not enough to judge a month), then rendered against synthetic rows through `/tmp/opencode/render_rekon.php`, which wraps everything in a transaction and rolls back. Renders covered the unfiltered month, an active `status` filter, an explicit `hari=`, and a future month (`hari_dilist=0`) for the empty state.
- **Browser** (Playwright Chromium 1148, `http://localhost:8080`): 1440 / 1280 / 768 / 390 / 360 in light plus 1440 dark. `docScrollWidth == clientWidth` at every width, no element-level overflow, no row target under 32px (44px on the panel action at ≤575px), one visible panel after a swap, `aria-current` moves, the panel re-parents under its row on mobile and back to the aside on resize, and the initial `hari` selection is honoured. 17 contrast probes × both themes pass.
- **Detector**: `./.opencode/skills/impeccable/scripts/impeccable detect --json` → `[]`.
- **One surface (2026-09-27)**: the day page is deleted. This route is now the only reconciliation surface. Both the input form and the manager's verify form live in the same per-day panel, so filling and approving a day never navigates. The verify form is a *sibling* of the save form, not nested — HTML forbids a form inside a form, and the smoke test asserts the `<form>`/`</form>` counts balance. Approval is per day (`can_approve` per item), because `canApproveRekon()` refuses the submitter of that specific day. Post-action redirects return here with `unit_id` + `month` + `hari`, so the panel you acted on is still the panel you land on. `panelHari()` in the smoke test bounds an assertion to one `<article class="rk-panel">`: a whole-page presence check would pass off some other day's button.
- **Locked day panel** (2026-09-27): a `SUBMITTED` day is read-only for anyone except the submitter. The panel for that date shows no **Simpan & Kirim** button, its inputs are `disabled`, and it says why. The check is per-date panel, not whole page — other days in the same view legitimately keep their button.
- **Rasters**: `.impeccable/review/rekonsiliasi-{desktop,desktop-dark,laptop1280,tablet768,mobile,mobile360,filter-status,bulan-kosong}.png` (after) and `rekonsiliasi-before-{desktop,desktop-dark,mobile}.png` (before). Measured, not viewed.
- **Design system**: `.impeccable/DESIGN.md` gained a `/finance/rekonsiliasi` section carrying the composition, the five decisions worth keeping (no `.table`, the pale-semantic-hue tint, card-scoped `--bs-card-bg`, the 4.375rem sticky offset, the Sunday/`hari_kerja` mismatch, the filter-aware band), what was preserved exactly, and this verification list.
- **Cleanup**: the four `public/__preview_rekon*.html` harness outputs are removed; the harness and capture scripts live in `/tmp/opencode` and never enter the repo.
