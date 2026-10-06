---
version: 1
slug: "stok-opname"
primary_target: "stok_opname"
related_targets: ["app/Controllers/StokOpname.php","app/Services/StokOpnameService.php"]
---

# Surface brief — Stok Opname (/stok_opname)

## Scope & mode

- Scope: `/stok_opname` — view `app/Views/stok/stok_opname.php` (route `StokOpname::index`, `mulai`, `simpan`, `finalisasi`, `reopen`). No controller, service, model, route, field, or role-gating change.
- Visitor mode: **Operate** — branch operator counting stock on the floor; supervisor/admin monitoring the same period.
- Audience: unit operators (mutate) + lintas-unit roles 0/1/2/34 and view-only roles (0,2,34,40). Indonesian UI, Rupiah-free counts (units, not currency), light/dark Modernize shell.
- Job: 1) start a period and count item by item until every stocked item is filled, 2) finalise, 3) monitor where a unit stands (progress, selisih, KPI 4×/bulan).
- Proof/content: the real period — `stok_opname_periode` status + `stok_opname_draft` rows, `stok_barang.stok_akhir` snapshot, audit trail (`stok_opname_audit`), riwayat periode, KPI (target 4 FINAL/bulan).
- Constraints: business logic, routes, permissions, field names untouched; every incumbent function preserved (unit/date picker, filter selisih, sort, page size, pagination, mode lihat, reopen with alasan, banner lanjut DRAFT, KPI card, audit, riwayat). Indonesian throughout.

## Chosen direction & memorable moment

**Papan Kendali** (control board): four period figures own the top of the page — Total Barang, Stok Komputer, Total Selisih, and KPI bulan ini — with the **freeze chip** stating exactly when the stock snapshot was taken and by whom. Under it the status row (periode badge · unit · tanggal · actions), then the dense counting ledger as the working surface, with audit trail and riwayat periode side by side beneath.

Memorable moment: the freeze chip — `Stok komputer dibekukan 06/10/2026 14:32 oleh user #12` — answers the operator's real question ("kenapa angkanya tidak ikut berubah?") before it is asked, and it is also spelled out in the Mulai Opname confirmation.

## Direction contract

THESIS: A stok opname period is a frozen snapshot measured against a live count, so the page is a control board — four period figures across the top with the freeze stamp stating when the snapshot was taken, over a dense counting ledger — and refuses the incumbent's stack of same-height cards that pushes the table below the fold. OWN-WORLD: committed Modernize tokens only, consumed as resolved CSS vars (`--bs-primary`, `--bs-success`, `--bs-warning`, `--bs-danger`, `--bs-border-color`, `--bs-body-bg`, `--bs-card-bg`, `--bs-secondary-color`), one elevation `0 2px 6px rgba(37,83,185,.1)`, the template's committed radii (18px cards, 8px inputs, pill filter chips), Iconify `solar:*` for chrome and Bootstrap Icons for row glyphs, `tabular-nums` on every count, no new palette, no gradients, no new fonts, light and dark both from the app's own theme values. STORY: the operator opens the board, reads where the period stands in one glance, sees the freeze stamp, presses the one action the state allows, and counts down the ledger — search and filter chips keep the unfinished set in front of them, selisih resolves live as they type, and the progress meter closes as they go; the supervisor reads the same board with the selisih filter open. FIRST VIEWPORT: at 1440×900 the page header line (title + breadcrumb + scope), then the four-figure board row with the freeze chip, then the status row carrying the period badge, unit · tanggal, freeze stamp and the state's action button group, then the ledger's command row (filter chips · search · selisih · urut · tampil) with the first table rows visible before the fold; KPI moves into the board row rather than owning a card of its own, and audit + riwayat sit side by side under the ledger. FORM: Papan Kendali, rank 7 of 7 on the grounded list, seed key 4528c861. FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Unresolved decisions

- Audit trail and riwayat periode ship side by side under the ledger (incumbent stacked them full-width). If either reads as noise on a small screen they collapse to one tabbed panel.
- The KPI card loses its own card and becomes the fourth board figure; if the KPI sentence ("hanya periode FINAL…") needs room it moves to a tooltip/secondary line rather than a new card.
- Freeze chip is shown whenever a periode exists (DRAFT and FINAL); before `mulai` the same fact is stated in the empty state and in the confirmation modal copy.
