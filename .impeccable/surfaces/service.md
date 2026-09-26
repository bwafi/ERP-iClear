---
version: 1
slug: "service"
primary_target: "service"
related_targets: []
---

# Surface brief — Service (/service)

## Scope & mode

- Scope: `/service` (route -> `Service::index`, view `app/Views/transaksi/service.php` + 4 partials under `app/Views/transaksi/table/`).
- Visitor mode: Operate — branch frontliner/CS service intake at the shop floor.
- Audience: frontliner & CS (jabatan 36 kasir on Sparepart locked), Indonesian, Rupiah, light/dark Modernize admin shell.
- Job: intake a phone-repair in 4 committed steps — Pelanggan -> Kerusakan -> Sparepart -> Pembayaran (each posts to its own route; redirects carry `?tab=` and localStorage keeps the last tab).
- Proof/content: the 4 step forms, computed server-side by existing business formulas (DP jurnal, sparepart -> penjualan bridge, garansi, bank rows).
- Constraints: keep the exact 4 steps, routes, business logic, role gating (jabatan 36 sparepart lock), `?tab=` deep links + localStorage tab memory; Bootstrap 5 Modernize admin world (light/dark) committed; Indonesian UI + Rupiah throughout.

## Direction contract

THESIS: The four intake steps are one vertical journey, not interchangeable tabs: a milestone rail on the left states exactly where the ticket stands and what is next, and every completed milestone stays open for one-click correction with its filled state intact. Refuses the incumbent flat nav-pills wizard that hides progress, drops context across tab switches, and buries the running total.

OWN-WORLD: Committed Bootstrap Modernize admin tokens only — primary blue #5D87FF rail + active states, success green #13DEB9 completed checks, warm amber #FFAE1F for DP/garansi notes, danger red #FA896B for removal only; bordered/shadow cards, Iconify solar icons, digit-spaced tabular Rupiah. No new palette, no new fonts, no gradients, no hard shadows.

STORY: The CS drops a device: fills Pelanggan once at the top, and every step below is reached on the rail — completed steps show a green check and a compact summary, so correcting earlier data is a click away and never loses what was entered. A pinned intake console keeps device, DP, kerusakan count, and the running Rupiah total visible while filling Sparepart and Pembayaran.

FIRST VIEWPORT: Page header card (title + breadcrumb) as today. Below it a two-column deck: left column the 4-node vertical milestone rail (numbered circles, connector line; done = success check + min summary, active = primary ring, locked = stepped-down); right column the active step's focused form card with a consistent footer (Sebelumnya / Selanjutnya primary). A compact summary bar (pelanggan · perangkat · kerusakan n · total Rp) tops the deck once a ticket exists.

FORM: The dealt surface structure, rank 5 of 7 on the grounded list, seed key bb50b654 (THE ROLL). Composition: vertical journey timeline.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Verdict — 2026-09-25

**Status: shipped · direction honored (rank 5/7, seed bb50b654 — vertical journey timeline).**

Review performed in-thread (no subagent tool available in this session; declared substitution for the finish-review subagent step). Evidence: `php -l` clean on all 5 changed views; Playwright/Chromium 153 headless suite, 23/23 checks green (desktop 1440 + mobile 390: no horizontal overflow; rail done/active/locked states; rail tab switching; running-summary mirrors for nama/perangkat/kerusakan/sparepart; uncheck kerusakan clears chip + count; kasir jabatan-36 sparepart lock inert; empty state hides summary bar + gates helper copy; `?tab=` deep-link restore). Final detector run against served render (stylesheet resolved): only template-wide tokens remain (Modernize muted `#707a82` 4.1–4.4:1, breadcrumb ol, func-card bordered chips) — pre-existing world conditions, kept for consistency.

**Defects found & fixed during verification:**
1. Rail never switched steps — Bootstrap `Tab` needs a `.nav` / `[role="tablist"]` ancestor; rail was a plain `<div>`, so `_parent` was `null` → `Element.prototype.querySelectorAll.call(null)` threw `Illegal invocation` (`TypeError`) and the whole page's tab activation was dead. Fix: `role="tablist"` on `#service-rail` (service.php).
2. `refreshServiceState` silently stripped `is-locked` from a kasir's sparepart step when ticket data existed (`toggle('is-locked', locked && !done)` → dropped with `done=true`). Fix: lock always wins — `toggle('is-locked', locked)` (service.php:430).
3. Mobile rail overflowed at ≤575px (4 pills at `min-width:76px` in a ~262px container) → forced a 54px horizontal scroll, hiding the Pembayaran step. Fix: ≤575.98px wraps the rail to a 2×2 grid, no scroll (service.php).

**Provenance — shipping rasters** (captured 2026-09-25, post-fix stub render `SV_WITH_TICKET=1`, Chromium 153, full-page):
- `.impeccable/review/service-desktop.png` — /service, ticket loaded, 1440×1000 viewport.
- `.impeccable/review/service-mobile.png` — /service, ticket loaded, 390×900 viewport (rail 2×2 wrap).
- Source HTML stubs: `/tmp/opencode/site/service_{empty,ticket,kasir}.html`; renderer `/tmp/opencode/render_service.php`.

## Extension — Pelanggan selection flow (2026-09-25)

Scoped redesign of `app/Views/transaksi/table/pelanggan_table.php` — the Pelanggan step within the shipped vertical rail. Scope (user-confirmed): only the customer-selection flow — the "Pilih pelanggan" CTA, the cari/tambah modals, and the auto-filled readonly fields. All field names, routes (`insert/pelanggan_service`, `service/search_pelanggan`, `simpan/pelanggan`, `region/{kabupaten,kecamatan}`), JS ids, and the master-data cascade stay untouched.

- Replaced the flat amber header row with a committed-world status panel: empty state (avatar + "Pilih pelanggan untuk memulai") and a selected state (initial avatar, nama, No HP + domisili deps; success badge "Terhubung ke service" when `idservice` exists, muted "Belum disimpan" otherwise). Driven by `window.updateCustomerPanel()`, wired into `setSelectedCustomer` and the domisili `change` events.
- Modal "Cari Data Pelanggan": `modal-dialog-centered`, icon tile header + helper copy, footer buttons (`Tambah Baru` outline / `Pilih` primary) — ids unchanged.
- Modal "Tambah Pelanggan Baru": grouped Kontak / Domisili sections with `service-modal-section` labels, two-col grid, `modal-dialog-centered`, split footer (Batal / Simpan Pelanggan) — field ids/names/required unchanged.
- Auto-filled readonly field group (Nama, No HP, Domisili) tinted with `--bs-success-bg-subtle` (`service-auto` / `service-select-auto` + a `bi-magic` hint) to signal data comes from the picked customer.

Tokens: same vertical-rail world only (bi + solar icons, `--bs-*` accents, bordered 12px rounded containers, tabular numerals), light/dark safe. Detector clean (`[]`). Verified in stub render both viewports (1440/390): empty↔selected toggle, initial/name/hp/domisili mirroring, draft vs ticket badge, no horizontal overflow.
