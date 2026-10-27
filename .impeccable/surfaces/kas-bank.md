# Surface Brief: Kas & Bank Module

## Primary Target
`kas_bank` (route prefix: `/kas_bank`)

## Related Targets
- `kas_bank/dashboard` — Ringkasan (Dashboard)
- `kas_bank/akun` — Rekening & Saldo Awal
- `kas_bank/transfer` — Pindah Saldo (Transfer Internal)
- `kas_bank/antar_unit` — Bayar Antar Unit

## Job & Audience
**Primary audience:** Management & back office (Kadiv, Manager, Finance, Admin Center) — role `jabatan` 0,1,2,34 (lintas unit) and 35,40,41,47 (admin cabang).
**Secondary audience:** Branch staff (SPV, CS) — view-only for own unit.
**Context:** Reviewing daily/weekly cash position, inter-branch receivables/payables, net cash flow, and account master data. Used at desk (desktop) and occasionally on tablet on shop floor.
**Visitor mode:** **Read** (monitoring/oversight) with **Operate** moments for authorized roles (input transfer, payment, account setup).

## Outcome & Proof
**Primary task:** At a glance, understand the company's cash position — per physical account (saldo nyata) and per unit entitlement (hak unit) — plus operational cash flow since finance cut-off.
**Success metrics:**
- Time to locate a specific account's saldo fisik vs hak unit < 3 seconds
- Zero confusion between "transfer internal" (own accounts) and "bayar antar unit" (inter-branch H/P)
- Clear visual distinction: KAS (unit-owned) vs BANK (shared physical accounts)
- Role-appropriate data density: lintas-unit sees everything; admin cabang sees only their scope
- All four sub-pages share consistent IA, visual language, and interaction patterns

**Product-specific truth:**
- Physical accounts = real bank accounts/cash drawers. One physical account = one row in `akun_kas_bank`.
- KAS accounts are **always** owned by exactly one unit. BANK accounts can be **shared** (is_shared=1 or no unit_id).
- Saldo awal is **per physical account** (one row per account). Alokasi splits that saldo to units — total alokasi ≤ saldo fisik.
- Transfer internal = moving money between own physical accounts (no net cash flow impact).
- Bayar antar unit = settling inter-branch H/P from stock mutations. Two scenarios: different physical accounts (creates kas/bank legs) OR same physical account (atribusi — only H/P ledger changes, no cash movement).
- Finance cut-off date gates what counts as "operasional" for Net Cash Flow.

## Selected Direction — Dashboard `/kas_bank`

**Mode:** Read, with Operate moments (filter, drill to accounts).
**World:** "Lembar Posisi Kas" — the dashboard is one treasury position sheet, not a widget grid. Ink on a flat sheet: hairline rules, double-rule totals, uppercase micro-labels, tabular figures, no card shadows inside the sheet. KAS carries the single accent (primary); BANK carries a warm amber used only as a mark and bar segment. The app shell (sidebar/topbar), Bootstrap 5, and dark/light themes stay.

**Structural thesis:** the physical-vs-entitlement duality is one statement, not four cards. Total position leads; Kas and Bank are ruled columns; a thin composition bar shows the split. Cash-flow for the effective cut-off period is a ruled list in the same sheet (no orphan column). The ledger below is full width and every account row shows `Saldo fisik` next to `Dialokasikan`/`Hak unit ini`, with a share bar and the remaining/unallocated amount. The sheet footer states the identity in words and figures: `Fisik = Dialokasikan + Belum dialokasikan` (consolidated) or `Fisik semua unit · Hak unit ini` (unit scope).

**Other pages:** `akun`, `transfer`, `antar_unit` keep their bodies and `.kb-*` components. Only the shared header/tabs in `_nav.php` move to the flat statement header (title, breadcrumb, underline tabs) so the module still reads as one place.

**Reading guide:** the long banner is replaced by one muted footnote under the hero: saldo fisik = real money; hak unit = the branch's spendable share; the gap is unallocated.

**States:** over-allocation and configuration diagnostics stay visible as compact alert strips above the ledger, never as full cards. Empty ledger teaches the next action (add an account). Mobile stacks the hero and turns ledger rows into labelled blocks.

## Direction Contract

**THESIS:** One treasury position sheet answers "which number do I trust?" by putting physical money and unit entitlement in the same ruled line; it refuses the four-metric-card dashboard.

**OWN-WORLD:** Flat sheet on the app canvas, 1px hairlines, 3px double rule for totals, uppercase 11px labels with .06em tracking, tabular figures, square-ish 6px radius, no inner shadows. Primary ink for KAS and the main figure; one amber mark for BANK; semantic success/danger only for flow sign and over-allocation. System UI sans, no display face.

**STORY:** Visitor reads the total and its Kas/Bank split, sees how much of the physical money is already entitled to units, scans cash flow for the cut-off period, then finds one account's fisik vs hak in the full-width ledger.

**FIRST VIEWPORT:** Page head (title + breadcrumb + underline tabs) → slim filter toolbar on canvas → one sheet: meta line, 2.25rem total, Kas/Bank ruled columns, 6px composition bar with legend, footnote, cash-flow list at right (5 ruled rows, net on a double rule), identity footer → ledger header and first rows below the fold on 900px.

**FORM:** Code-led (no image generation). Installed Impeccable build exposes no `concept-seed`/decision-page scripts, so the direction was chosen through the harness structured question; no seed key exists.

**FINISH:** unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance.

## Scope & Boundaries
**In scope:** All four view files (`dashboard.php`, `akun.php`, `transfer.php`, `antar_unit.php`), shared `_nav.php`, any inline JS/CSS. Controller logic **unchanged** — only view layer.
**Out of scope:** Backend logic, database, permissions, routes, other modules.
**Anti-goals:** No dashboard-style widgets that hide data; no modal-heavy flows; no custom chart libs (keep it table-first); no breaking Bootstrap template structure.

## States & Ranges
| Data | Min | Typical | Max |
|------|-----|---------|-----|
| Akun kas/bank per unit | 1 | 5–15 | 50+ |
| Units in filter | 1 | 5–20 | 100+ |
| Transfer history rows | 0 | 50 | 200 (paginated) |
| H/P antar unit rows | 0 | 10–30 | 100+ |
| Alokasi per bank account | 0 | 2–8 | 20 units |

## Constraints & Open Decisions
- **Framework:** CodeIgniter 4 + Bootstrap 5.3 + jQuery (existing). No React/Vue.
- **Icons:** Bootstrap Icons (already loaded) + iconify (sidebar).
- **Currency:** Rupiah, `Rp 1.234.567` format, tabular-nums.
- **Language:** Indonesian UI copy throughout.
- **Dark mode:** All new colors must work in both themes (use Bootstrap semantic colors: `primary`, `warning`, `success`, `danger`, `info`, `secondary` with `-subtle` backgrounds).
- **Accessibility:** WCAG 2.1 AA — contrast, focus states, ARIA labels on icon-only buttons, table headers.
- **Performance:** No new JS libraries; vanilla JS only for progressive enhancement.
