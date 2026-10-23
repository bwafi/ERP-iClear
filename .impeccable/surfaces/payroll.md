---
version: 1
slug: "payroll"
primary_target: "payroll"
related_targets: ["app/Views/jurnal/payroll.php","app/Controllers/Payroll.php"]
---

# Surface brief — Payroll (/payroll)

## Scope & mode

- Scope: `/payroll` — route `Payroll::index` and the view `app/Views/jurnal/payroll.php`, plus the monthly summary data it needs from `app/Controllers/Payroll.php`. The redesign covers the page header, period control, phase board, unit KPI strip, payroll ledger, bonus/lembur ledger, empty states, and the six existing modals (layout and copy only).
- Visitor mode: **Operate** — Finance/back-office running a monthly payroll cycle at a desk, with occasional tablet use.
- Audience: Finance, Manager, Admin Center (roles that can input); other roles read the same page without the action buttons. Indonesian, Rupiah, Modernize/Bootstrap light and dark.
- Job: understand where the month stands, scan who is paid or still due, correct a row, mark it paid, and record bonus/lembur — without leaving the page.
- Proof/content: real `finance_payroll` rows joined to `akun` and `unit`; `kas_keluar` rows for bonus/lembur; `payrollSummary` per unit from `PayrollTimelinessCalculator`; generator results from the existing flash message.
- Constraints: routes, field names, database schema, role gating, and business logic stay untouched. Susun Otomatis still overwrites `rencana` rows and skips paid rows. The six existing modals keep their fields and submit targets. Indonesian throughout.

## Chosen direction & memorable moment

**Lembar Proses Payroll** — one period read as a run in progress. The top of the page is a single sheet: title, period control, and one phase board whose four hairline cells count the month's real states — rows still auto, rows already corrected, rows paid, and bonus/lembur — with count and Rupiah in each. The ledger below is where those rows get acted on; the unit KPI strip sits between the board and the ledger, not as a row of equal consumer cards.

Memorable moment: after Susun Otomatis, the phase board and the flash message answer the same question in the same words: how many rows are still untouched, how many were refreshed, and how many are already paid. The board is the month's status line, not decoration.

## Direction contract

THESIS: One payroll period is a run in progress, not a flat register. The page opens with one phase board that counts the month's actual states — auto rows waiting for review, corrected rows ready to pay, paid rows, and bonus/lembur — then the ledger where those rows are acted on. It refuses the incumbent's stack of generic cards, its equal-weight ten-column table, and its empty header card.

OWN-WORLD: Committed Modernize tokens only, consumed as resolved CSS vars (`--bs-primary` #0085db, `--bs-success` #4bd08b, `--bs-warning` #f8c076, `--bs-danger` #fb977d, `--bs-body-bg`, `--bs-border-color`, `--bs-emphasis-color`, `--bs-secondary-color`) so dark mode comes from the app. One card stock at 1.125rem radius with the app's `0 2px 6px rgba(37,83,185,.1)` elevation, hairline-separated cells instead of nested cards, no gradients, no new fonts. Plus Jakarta Sans throughout; `tabular-nums` on every Rupiah, date, and count. Iconify `solar:*` for chrome. Semantic colours mark state only.

STORY: Finance opens the month and the phase board says where the run stands: how many rows are still auto, how many were corrected, how many are paid, and what bonus/lembur is waiting. They scan the ledger by employee, read the amount as the row's largest figure, and act inline. A late row is stated in words, never by colour alone. The unit strip keeps each branch's KPI score and total in view while the ledger stays the working surface.

FIRST VIEWPORT: At 1440×900: one header line with the title, breadcrumb, period control, and the two actions (Susun Otomatis, Input Payroll Gaji); below it one phase sheet with four hairline cells (Susun · Koreksi · Bayar · Bonus/Lembur), each carrying a count and Rupiah; a compact unit KPI strip; then the ledger's command row (search, status chips, result count) with the first payroll rows visible. No separate empty header card and no equal four-card metric row.

FORM: The dealt surface structure, rank 4 of 7 on the grounded list, seed key 6b372622. Composition: payroll run sheet — phase board over ledger.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Decisions locked in the build

- Phase buckets use real data, not invented workflow states: **Susun** = unpaid rows with `sumber='auto'`; **Koreksi** = unpaid rows with `sumber='manual'` (or null, the legacy default); **Bayar** = status `dibayar`; **Bonus/Lembur** = `kas_keluar` rows for the selected period. A paid auto row counts only under Bayar, so the three payroll buckets do not overlap.
- The bonus/lembur query becomes period-scoped when a month is selected, matching the phase board and the rest of the page. "Semua Bulan" restores the unfiltered list. This is a deliberate scope correction, not a business-logic change.
- Ledger search and status chips replace the DataTables chrome on these two tables. Server-side ordering stays; client-side filtering is enough at payroll scale. Shortcut comment in the view names the limit; reintroduce paging if the all-months view exceeds roughly 300 rows.
- The KPI strip shows only units that have payroll rows in the period, because that is what `payrollSummary` computes. An empty strip says so instead of inventing a score.
- Responsive: the payroll and bonus tables become stacked cards at `899.98px` — the six-column table has a ~824px minimum and clipped below that; the phase board is 2×2 at `991.98px` and stays 2×2 on phones; the unit strip is 2 columns below `899.98px`.
- “Semua bulan” is a link to `?bulan=`; it clears the month input, changes the badge to “Semua bulan”, hides the phase board/KPI, and shows the all-months notice. The month form itself only has the “Tampilkan” submit.
- The template’s floating theme customizer is hidden on `/payroll` because it covers the right-aligned amount/action column on mobile; the topbar still exposes light/dark mode. Outline-primary labels use `--pay-primary-ink` for contrast.
