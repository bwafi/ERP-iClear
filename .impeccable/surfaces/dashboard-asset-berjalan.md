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
- Job: read the 6 committed metrics (Omset, Pengeluaran, Total Tanggungan Asset, Total Gaji, Hak Cabang, Tagihan Center) for one month; switch month and unit.
- Proof/content: the 6 metrics, computed server-side by the existing business formulas. Month filter uses GET bulan+tahun (pattern of `jurnal/omset_bulanan`); for units 1&2 tanggungan uses days-in-that-month x 355000 (user decision).
- Constraints: keep exact metrics & formulas; role gating unchanged; Bootstrap 5 Modernize admin world (light/dark) committed; keep formula note for unit-4 20% slice.

## Direction contract

THESIS: The month ruler is the spine: navigating bulan/tahun is the primary act, not a buried toolbar; the six metrics sit as a scannable report band. Refuses the incumbent equal-stat-card grid that treats month-switching as an afterthought.

OWN-WORLD: Committed Bootstrap Modernize admin tokens only — primary blue #5D87FF for obligations, success green #13DEB9 for income, warm amber #FFAE1F, danger red #FA896B; bordered/shadow cards, Iconify solar icons, digit-spaced tabular numbers. No new palette, no new fonts, no gradients, no hard shadows.

STORY: Management opens the page, reads the current month labeled once, and one glance moves across six figures: money in (Omset), money out (Pengeluaran, Gaji), then the three obligation slices (Tanggungan, Hak Cabang, Tagihan Center). Prev/next month is one click each way, always visible.

FIRST VIEWPORT: A period ruler card across the top: month-previous / current month pill / month-next plus unit & tahun controls fused into one bar. Beneath it, six equal compact metric tiles in a 3x2 grid (6 metrics, one per tile), big Rupiah figures with icon, label, and one-line note. The ruler is the hero element, the grid is the report.

FORM: The dealt surface structure, rank 7 of 7 on the grounded list, seed key b777bde7 (THE ROLL). Composition: month-ruler report deck.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
