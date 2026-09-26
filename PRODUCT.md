# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Two primary audiences, both internal and Indonesian-speaking, gated by the same role-based menu system (`jabatan` roles):

- **Branch staff** — frontliner, customer service (CS), SPV, service technicians working POS sales, service intake/completion, and stock at a single unit/cabang.
- **Management & back office** — branch/division managers (Kadiv, Manager), finance, purchasing, HRD, and admin center. Their job is monitoring and control across units: cash, hutang/piutang, KPI, incentives, payroll, and marketing/CRM.

## Product Purpose

An in-house ERP that runs a multi-branch mobile/gadget retail chain end-to-end: POS sales, phone-repair service, inventory, purchasing, cash, finance, marketing/CRM, HRD attendance, and payroll/KPI — all in one system, so daily operations and management decisions share a single source of truth.

## Positioning

An integrated retail operations platform where POS, service, inventory, finance, CRM, and a role-based KPI/incentive engine are connected rather than separate modules: every transaction feeds branch-level financial and performance calculations, so sales, service results, attendance, KPI, incentives, payroll, and cash movements compose each other. This connected-engine property, tailored to this business's own workflows (including a KPI/insentif payroll engine and a CRM lead bridge), is what a generic off-the-shelf ERP would not truthfully replicate.

## Operating Context

- Daily branch operations: POS sales, service intake/completion, stock and stok opname, cash in/out, and purchases/payments happen in the working day, on the shop floor.
- Management work spans the chain: per-cabang dashboards and reports with branch filters and month-by-month comparison.
- UI is in Indonesian and amounts are Rupiah.
- Access and menus are role-shaped by `jabatan` (frontliner, CS, SPV, Kadiv, HRD, Admin, etc.).
- Rendered in a Bootstrap 5 admin template (Modernize-style) with dark/light mode and per-unit logos.

## Capabilities and Constraints

Confirmed capabilities include: POS sales (Penjualan), phone-repair service (Service), stock and stock card (Kartu Stok), stock opname, purchasing (Pembelian), cash in/out (Kas Masuk/Keluar), hutang/piutang and retur, finance (Laba Rugi, Jurnal, asset/depreciation), payroll with a KPI/insentif engine per branch, marketing and promotions (Konten, Promosi WhatsApp), a CRM lead bridge (Kommo webhook, read-only lead summary with HMAC signature), and HRD attendance/tasks (Presensi, Tugas). Terminologi: `unit`/`cabang` = branch, `jabatan` = position/role.

Binding constraints (confirmed by the owner):

- Do not alter business logic, database schema, permission/role access, or existing workflows for the sake of a frontend redesign.
- Frontend must follow the existing, already-running ERP structure and stay responsive for operational use.
- Indonesian UI and Rupiah figures throughout.

## Brand Commitments

- iClear branding (default logo `logo_iclear.png`), with per-unit logos shown in the sidebar.
- "urban" corporate domain (`urban.motpolije.com`).
- The incumbent Bootstrap 5 admin template structure (Modernize-style, dark/light) is committed; the user requires keeping this admin-template structure.

## Evidence on Hand

- Running production deployment: `urban.motpolije.com` (CI4 app; `.env` points to `erp_local` for local dev).
- Source: `app/Controllers` (82 controllers), `app/Views`, `app/Models`, plus `public/template/assets`.
- Docs: `docs/architecture/*`, `docs/analisis/*`, `KPI_SERVICE_DOCUMENTATION.md`, `REFACTOR_KPI_ABSENSI.md`, and git history describe workflows, KPI business rules, and the KPI engine.
- No marketing copy, testimonials, or customer lists exist in-repo and must not be fabricated.

## Product Principles

1. **One source of truth** — every transaction (sales, service, cash, attendance) flows into branch-level financial and performance calculations; no module is a silo.
2. **Role-shaped access** — menus and dashboards follow `jabatan`/roles; permission boundaries are never bypassed or loosened.
3. **Operational-first** — the UI follows the running ERP structure and supports daily branch work; design must not impede or rewrite documented workflows, business logic, or schema.
4. **Branch-aware everywhere** — unit/cabang filtering and period comparison are core to dashboards and reports, not an afterthought.
5. **Quietly robust** — long data tables, heavy filters, and report workflows must stay fast, scannable, and usable in the working session.