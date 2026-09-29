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

## Selected Direction

### Visual Authority
**Established world:** Bootstrap 5 (Modernize-style admin template), dark/light mode, iClear branding, per-unit logos in sidebar. **Do not replace the template.** Extend it with a dedicated Kas & Bank visual layer: tighter spacing, purposeful color coding (KAS=primary, BANK=warning, HUTANG=danger, PIUTANG=success, TRANSFER=info), tabular-nums everywhere, and a persistent "reading guide" pattern for complex tables.

### Structural Thesis
**"Two-layer truth"** — every screen simultaneously shows:
1. **Physical layer** (saldo nyata, rekening fisik, uang benar-benar ada)
2. **Entitlement layer** (hak unit, alokasi, H/P per unit)

The navigation and tables make this duality explicit, not implicit.

### Sequence & Focal Moments
| Page | Focal Moment |
|------|--------------|
| Dashboard | **Saldo cards** (Kas/Bank/Total/Net Cash Flow) + **Per-account table** with dual columns (Saldo Nyata | Hak Unit Ini) |
| Rekening | **3-step wizard cards** (Daftar Rekening → Saldo Awal → Alokasi Unit) + **Master table** with inline alokasi expansion |
| Transfer | **Split view**: Form (kiri) | History (kanan) — clear "bukan pembayaran hutang" badge |
| Bayar Antar Unit | **Three-panel layout**: Form (kiri) | Hutang Saya (tengah) | Piutang Lawan & History (kanan) — visual coding for "rekening sama = atribusi" |

### Interaction & Layout
- **Persistent sub-nav** (`_nav.php`) upgraded: pill tabs with icons, active state carries context badge (e.g., "4 rekening aktif").
- **Tables**: Sticky header, hover highlight, row expansion for alokasi/barang detail, tabular-nums, status badges with semantic colors.
- **Forms**: Inline validation, auto-populate jumlah from sisa hutang, disabled states for non-input roles, clear "read-only" banner.
- **Filters**: Collapsible filter bar above tables; unit selector prominent for lintas-unit roles.
- **Responsive**: Cards stack on mobile; tables get horizontal scroll with pinned first column (account name).
- **Empty states**: Illustrative + action button (e.g., "Tambah rekening pertama").

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

## Direction Contract

**THESIS:** Kas & Bank surfaces expose the duality of physical money vs. unit entitlement at every layer — navigation, cards, tables, forms — so a manager never asks "which number do I trust?"

**OWN-WORLD:** Bootstrap 5 semantic palette extended with purposeful role colors (KAS=primary, BANK=warning, HUTANG=danger, PIUTANG=success, TRANSFER=info, ATRIBUSI=secondary). Tabular-nums on all currency. Compact card density (p-3). Pill tabs for sub-nav. Inline row expansion for detail. Sticky table headers.

**STORY:** Visitor lands on Dashboard → sees 4 big numbers (Kas, Bank, Total, Net Cash Flow) → scans per-account table with dual columns → clicks "Rekening" to master accounts → sees 3-step wizard + master table with inline alokasi → clicks "Pindah Saldo" for internal moves → clicks "Bayar Antar Unit" to settle H/P with visual cue for same-account atribusi.

**FIRST VIEWPORT (Dashboard):** 4 metric cards (Kas | Bank | Total | Net Cash Flow) → Filter bar (unit | date range | account) → Dual-column table (Saldo Nyata | Hak Unit) → Arus kas breakdown card. All above fold at 1440px.

**FORM:** Code-led (no comp generation). Build directly in Bootstrap 5 vocabulary with the above system.

**FINISH:** Unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance.