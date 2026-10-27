---
version: 1
slug: "dashboard-asset-berjalan"
primary_target: "dashboard/asset_berjalan"
related_targets: []
---

# Surface brief — Asset Berjalan (/asset_berjalan)

## Scope & mode

- Scope: `/asset_berjalan` (route -> `TutupKasir::assetberjalan`, view `app/Views/dashboard/asset_berjalan.php`).
- Visitor mode: Operate — management monthly review of branch asset obligations.
- Audience: management/back office (jabatan 1, 0, 34, 40 pick unit; branch staff see own unit), Indonesian, Rupiah.
- Job: answer "how did this unit do this month" in one pass: omset, biaya terkontrol, gaji unit, gaji HO, laba bersih, then the three obligations (Tanggungan, Hak Cabang, Tagihan Center). Beban Head Office is secondary and expandable.
- Proof/content: the committed metrics, computed server-side by the existing business formulas. Month filter uses GET bulan+tahun; for units 1&2 tanggungan uses days-in-that-month x 355000 (user decision). Laba Bersih subtracts the unit's HO share — the payroll-deducted amount when it exists, otherwise the estimate, labelled as such (user decision 2026-10-27) — and the three obligations follow that base.
- Constraints: role gating unchanged except HO per-unit detail is no longer restricted by role and SPV (40) page access stays limited to `spv_units`; HO share reaches every role so Laba Bersih does not change with the viewer; Bootstrap 5 Modernize admin world (light/dark) committed; keep formula note for unit-4 20% slice.

## Direction contract

THESIS: One unit sheet, not a deck of stat cards. Unit + month is stated once at the top; a single ruled equation (Omset − Biaya Terkontrol − Total Gaji − Gaji HO = Laba Bersih) carries the month's condition, with the result as its own closing bar; one obligation strip carries the consequences. Beban Head Office is a footnote-level section that expands on demand, not a competing dashboard.

OWN-WORLD: Committed Bootstrap Modernize admin tokens only — primary blue #5D87FF, success green #13DEB9, warm amber #FFAE1F, danger red #FA896B; light borders, soft shadow cards, Iconify solar icons, tabular figures. No new palette, no new fonts, no gradients.

STORY: Finance opens the page, confirms unit and month once, reads the equation left to right — including the estimated Gaji HO share — and lands on Laba Bersih as the closing bar, then scans the three obligation slices computed from it. If they need to know how that HO number is made, they open "Beban Head Office": the selected unit's share is its own block with the estimated amount and Non-SPV/SPV split, then the full table marks that unit as "Unit ini". "Lihat rincian" on Total Gaji opens a payroll register: component summary first, then one row per karyawan with a total footer.

FIRST VIEWPORT: Unit + period header card (unit name, jenis/trial badge, prev/next month, inline unit/month/year filter). Directly below, the equation card: four deduction cells then a full-width Laba Bersih bar, tinted as the result. Below that, a single three-cell obligation strip. HO sits under the obligations, its per-unit table collapsed by default.

FORM: Composition A — Lembar Posisi Unit; dealt structure lead from seed key d08adaa1 (THE ROLL). Replaces the incumbent equal-card grid that gave the HO panel the same visual weight as the unit's own result.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance.
