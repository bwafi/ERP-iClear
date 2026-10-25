---
target: app/Views/jurnal/omset_bulanan.php
total_score: 16
max_score: 40
na_heuristics: 
p0_count: 2
p1_count: 2
target_identity: "file:/home/syro/Projects/ERP/app/Views/jurnal/omset_bulanan.php"
target_fingerprint: "sha256:97d7fada055e3818bb3b6e8b90cf13454c41d2ec733de5d7ed3e52beb36ba08e"
target_path: /home/syro/Projects/ERP/app/Views/jurnal/omset_bulanan.php
timestamp: 2026-10-16T12-09-13Z
slug: app-views-jurnal-omset-bulanan-php
---
# Critique — Omset Bulanan (`/omset_bulanan`)

Method: dual-agent (A: general · B: general)
Target: `app/Views/jurnal/omset_bulanan.php` (+ `TutupKasir::omsetbulanan()`)
Mode: Operate (data-heavy monitoring dashboard)

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 2 | Period nav is clear, but the date filter shows no "filtered" state/result count; filtered table still says "Total Bulan Ini". |
| 2 | Match System / Real World | 2 | Jargon (HPP, Value, Net Cash Flow, DP service) and "Hari Ini"/"Bulan Ini" labels that lie when another month is selected. |
| 3 | User Control and Freedom | 2 | Month prev/next is good; the date range has no reset/presets and can only be cleared by reloading. |
| 4 | Consistency and Standards | 1 | Ignores the project's own `kb-*`/`so-*` systems; mixed tinted/plain cards; English `date('F Y')` vs Indonesian JS dates; heading order h4→h3→h5. |
| 5 | Error Prevention | 1 | Filter silently no-ops unless both dates are set; no `start ≤ end` validation; inverted range = empty table. |
| 6 | Recognition Rather Than Recall | 2 | Unit/period live in a filter card hidden for some roles; must remember which period and that the filter is chart/table-only. |
| 7 | Flexibility and Efficiency | 2 | No date presets, no export, no comparison mode, no keyboard path, ~30 identical per-row buttons. |
| 8 | Aesthetic and Minimalist Design | 1 | 12 metric cards + two filter bars + modal + offcanvas; money not tabular; `bg-opacity-10` bolted onto `-subtle` (no-op). |
| 9 | Error Recovery | 2 | Offcanvas shows a warning on fetch failure; but broken KPIs render as silent `0` and empty states offer no next step. |
| 10 | Help and Documentation | 1 | Omset-vs-Arus-Kas footnote is useful but misleading; nothing explains HPP, Value, or Rata-rata/Hari. |
| **Total** | | **16/40** | **Poor — major UX overhaul required** |

## Design Specificity Verdict

**LLM assessment: category-interchangeable Bootstrap admin.** The page is the Modernize skeleton untouched: header card + breadcrumb, a 12-tile `card` grid with `bg-*-subtle`, a `form-select` filter card, a Chart.js line chart, a `table table-hover`, a modal, and an offcanvas. Swap the labels and it is an inventory or HR dashboard unchanged. Evidence: **zero `kb-*` classes** in the view despite `DESIGN.md` defining `.kb-metric-card`, `.kb-data-card`, `.kb-table`, `.kb-filter-bar`, `.kb-btn` and a KAS/BANK/HUTANG/PIUTANG color system that maps directly onto this page's Tunai / Transfer-Bank / DP split. The project also authored the bespoke `.so-*` board (one card, four hairline-separated `.so-cell` figures, `tabular-nums`, measured contrast) for Stok Opname — proving it knows how to build a product-authored board — yet this flagship revenue page uses a flat `row g-3` of twelve free-floating cards. The single genuinely authored artifact is the *content* of the Arus Kas offcanvas (physical cash ≠ omset, Tunai vs Transfer legs, internal-transfer badge, DP double-count rule), but its presentation is still generic Bootstrap.

**Deterministic scan:** the bundled detector ran once over the markup — **0 findings, exit code 0 (clean)**, engine v0.1.5. Bootstrap utility tokens, PHP/`<?=` tags, Iconify attributes, inline styles, and JS color literals were all examined and correctly not flagged. No false positives.

**Visual overlays:** browser inspection was skipped — no importable browser automation (Playwright/Puppeteer not in any `node_modules`), no harness browser layer exposed, and the target is a server-rendered PHP view with no live URL. All visual claims below come from source reading only.

## Overall Impression

A capable, genuine piece of financial reporting wrapped in a generic admin template — and undermined by broken numbers and a wrong definition of its own headline word. The biggest single opportunity is to stop treating this as a dashboard and design it as **one monitoring instrument**: a compact KPI board on a product-authored token system, with the daily ledger and cash-flow audit as first-class, correctly-scoped views.

## What's Working

1. **The Arus Kas offcanvas is the page's real voice.** It frames itself as physical cash mutation (not omset), splits every block into TUNAI vs TRANSFER/BANK, badges internal moves as non-impacting, and documents the DP double-count rule so the number reconciles with TutupKasir. Real iClear domain thinking.
2. **The month-over-month chart line is honest.** `totalLalu` is day-of-month-aligned and set to `null` when the previous month is shorter, with `spanGaps: false`, so the comparison line breaks instead of diving to zero.
3. **The Kecamatan modal is disciplined progressive disclosure** — live search, ranked rows with medals, progress bars, a porsi column, and a real empty state.

## Priority Issues

**[P0] A. Two KPIs are permanently zero (undefined view variables).** The controller's `return view(...)` never passes `omset_rata_rata`, `hari_rata_rata`, `countService`, `countSales`, yet the view reads all four — so "Rata-rata / Hari" renders Rp 0 and "Total Pelanggan Masuk" shows "0 service / 0 sales" under a non-zero headline. *Why it matters:* a monitoring page whose own KPIs silently read zero destroys trust. *Fix:* pass the four values (or delete the cards) and add a regression check that every view variable exists in the data array. *Suggested command:* `/impeccable harden`.

**[P0] B. "Omset" is actually gross profit; "Value" is gross revenue; the footnote says the opposite.** Every Omset figure computes `SUM(sub_total − hpp_penjualan)`, while a separate card shows `SUM(sub_total)` as "Value." In Indonesian retail, *omset* = gross revenue, so users will misread their branch's turnover. *Fix:* pick one definition of Omset as the page's spine, rename the margin card "Laba Kotor (Omset − HPP)" and gross "Penjualan Bruto", and correct the footnote. *Suggested command:* `/impeccable clarify`.

**[P1] C. "Hari Ini" / "Bulan Ini" labels are wrong in historical view.** "Omset Hari Ini" has no month/year filter, and `date('j F Y')` is hard-coded to the present, so viewing January 2025 still shows live "today" KPIs mislabeled as this period. *Fix:* show "Omset Hari Ini" only when `$isBulanBerjalan`, otherwise show the selected period's last day or hide it; name cards to the selected period and add an "as of" timestamp. *Suggested command:* `/impeccable clarify`.

**[P1] D. The date-range filter is client-side-only, diverges from the KPIs, and has no reset.** `filterData()` rewrites only the chart and the table footer (which keeps the stale label "Total Bulan Ini") and never touches the 12 KPI cards; it no-ops unless both dates are set and doesn't validate start ≤ end. *Fix:* dynamic footer label, a Reset button + date presets, range validation, and either update or explicitly annotate the KPIs while filtering. *Suggested command:* `/impeccable harden`.

**[P2] E. The visual language abandons the documented design system.** No `kb-*`/`so-*`, no `tabular-nums`, generic `-subtle` tiles, broken heading order, mixed numeral/month-language conventions. Users moving from `/kas_bank` to `/omset_bulanan` experience two different products. *Fix:* re-express the KPI strip as a `kb` board / `kb-metric-card` set, adopt `kb-table`/`kb-tag`, and apply `tabular-nums` to every rupiah cell. *Suggested command:* `/impeccable layout`.

**[P2] F. Overload: 12 KPIs + two filter bars + a button on every row.** One `row` of twelve cards prevents a single focus, and ~28–31 identical "Arus Kas" buttons make targeting slow. *Fix:* promote 2–4 primary KPIs, demote the rest to a compact strip or secondary tab, and replace per-row buttons with row-click or one control. *Suggested command:* `/impeccable distill`.

**[P3] G. Accessibility gaps.** Filters have `.form-label` with no `for`/`id`; the "Arus Kas" label is hidden below `xl` leaving an icon-only button; the chart `<canvas>` has no text alternative; decorative icons and 🥇🥈🥉 emoji lack accessible names; heading order breaks the document outline. *Suggested command:* `/impeccable audit`.

## Persona Red Flags

**Alex (Power User):** no export/CSV/print; no date presets, so dates are retyped each visit; client-side filtering means he cannot query a range the server didn't already send; ~30 identical "Arus Kas" buttons with no "jump to highest/lowest day"; no comparison mode despite the page's premise.

**Sam (Accessibility):** no label/control association on any of the five filters; the active-period pill on `bg-light-subtle` is a light-mode contrast risk with no dark tuning (unlike `kb`/`so`); chart data reachable only via the separate table; icon-only Arus Kas button below `xl`; focus not explicitly managed into the offcanvas.

**"Kasir lelah di akhir shift" (project persona — closing 20:45–23:00):** the page opens on an unlabeled "today" KPI; closing-critical data sits at the bottom behind ~10 analytics cards; jargon (HPP, Value, Net Cash Flow, Fast Moving Service, Domisili, DP service) is unexplained; broken "Rata-rata 0 hari"/"0 service / 0 sales" reads as "the system is wrong"; English "October 2026" and "1.5 Jt" in an otherwise Indonesian terminal; no "as of" time or unit name to confirm it's her branch.

## Minor Observations

- English `date('F Y')` in PHP vs Indonesian `toLocaleDateString('id-ID')` in JS — the table changes language after filtering, and the nav bar ("October 2026") disagrees with the offcanvas ("16 Oktober 2026").
- `bg-*-subtle bg-opacity-10` is a no-op pair repeated ~8 times.
- Chart.js loaded from an **unpinned CDN URL** while the template vendor-pins the rest.
- `parseInt(item.total)` in `updateTable()` despite numeric data.
- x-axis "Rp 1.5 Jt" (dot decimal) next to id-ID tooltips ("Rp 1.500.000").
- `arsort($dataHarian)` mutates the array later used to build `$listHari` (latent footgun).
- Kecamatan "Total Transaksi/Unit" conflates two units of measure.
- No `tabular-nums`, so rupiah columns don't align.
- `id="totalOmset"` is on a `<th>`; the surrounding "Total Bulan Ini" header never updates with it.

## Questions to Consider

1. If "Rata-rata / Hari" and "Total Pelanggan Masuk" have been decorative rather than correct, would the page be better with half the cards deleted?
2. A kasir needs "today's take" and "is my cash right" at 22:50 — why are those buried under ten discretionary cards?
3. When the client-side filtered total diverges from the server-side closing report, which is the source of truth?
4. Is this page for **monitoring omset** or **auditing cash**? It claims both.
5. Why does an Indonesian terminal UI say "October 2026", "Fast Moving Service", and "Value"?
6. What if the page were designed as *one* instrument (a single KPI board + a tabbed Daily/Arus-Kas ledger) instead of twelve detached tiles?
