# DESIGN.md — Kas & Bank Visual System

## Visual Identity & Theme
- **Framework:** Bootstrap 5 (Modernize admin style) with dark/light mode compatibility.
- **Brand Context:** iClear ERP — Professional, financial clarity, operational-first density.
- **Design Language:** Distinctive, non-generic component system with custom cards, metric displays, segmented navigation, and refined data tables.

## System Color Semantics
- **KAS (Cash):** Primary theme color — Tunai & physical cash drawer.
- **BANK (Bank Account):** Warning theme color — Bank accounts & transfer legs.
- **HUTANG (Payables):** Danger theme color — Inter-unit obligations & debt balances.
- **PIUTANG (Receivables):** Success theme color — Tagihan & receivables.
- **TRANSFER (Internal Move):** Info theme color — Internal non-operational transfers.
- **ATRIBUSI (Same Account Settlement):** Secondary theme color — Non-cash H/P clearing.

## Component & Layout System

### Navigation (`_nav.php`)
- **Segmented Control:** Pill-style tab bar with icons, labels, and dynamic context badges.
- **Active State:** Elevated card-style active tab with shadow and border.
- **Context Badges:** Color-coded pills showing counts (aktif, kas/bank, hutang terbuka).

### Metric Cards (`.kb-metric-card`)
- **Purpose:** Dashboard summary cards for key financial metrics.
- **Structure:** Header with label + icon, large value, supporting subtitle.
- **Variants:** Primary (Kas), Success (Bank), Info (Total), Dynamic (Arus Kas).
- **Interaction:** Subtle hover lift with shadow.

### Data Cards (`.kb-data-card`)
- **Purpose:** Container for tables and data displays.
- **Structure:** Header with title/subtitle/legend, scrollable table wrapper, optional footer note.
- **Table Style:** Custom `.kb-table` with uppercase headers, hover states, tabular numbers.
- **Tags:** Color-coded `.kb-tag` system for status, type, and category labels.

### Step Cards (`.kb-step-card`)
- **Purpose:** Numbered form sections for multi-step workflows.
- **Structure:** Header with numbered badge + title, body with form elements.
- **Variants:** Primary (Step 1), Success (Step 2), Info (Step 3).

### Filter Bar (`.kb-filter-bar`)
- **Purpose:** Consistent filter interface across pages.
- **Structure:** Horizontal group with labeled fields and submit button.
- **Style:** Card background with rounded corners, compact spacing.

### Form Elements
- **Inputs:** `.kb-form-input`, `.kb-form-select` — Compact, rounded, focus rings.
- **Buttons:** `.kb-btn` with variants (primary, success, info, danger, outline).
- **Notes:** `.kb-form-note` — Highlighted info boxes with icons.

### Context Alerts
- **Purpose:** Explanatory banners for complex workflows.
- **Style:** Tertiary background with icon, bold lead text, muted description.

### Warning Cards
- **Purpose:** Alert for allocation exceeding physical balance.
- **Style:** Amber background with icon, badge list, action guidance.

### Stock Opname Control Board (`.so-*`)
- **Purpose:** Period overview and counting workspace for `/stok_opname`; every rule below lives in `app/Views/stok/_opname_theme.php`, scoped under `.stok-opname` so it cannot leak to other modules.
- **Board (`.so-board__grid`):** one card holding four hairline-separated figures (`.so-cell`), not four metric cards — label `0.75rem`/600 muted, value `clamp(1.5rem, 1.9vw, 1.75rem)`/650 with `tabular-nums`, note `0.75rem`; collapses to 2 columns at `992px` and stacks under `576px`.
- **Freeze chip (`.so-freeze`):** one line stating when the computer-stock snapshot was taken and by whom (`Stok komputer dibekukan <tanggal> oleh user #N`), rendered only from real `stok_opname_periode` data — the `oleh` clause is dropped when `mulai_by` is null, never invented.
- **Status row (`.so-status`):** period badge · unit · tanggal · freeze stamp · the state's own action buttons, optionally followed by the DRAFT-continuation banner and the progress meter (`.so-meter`, 6px pill track, `--bs-primary` fill animated by `scaleX`, JS writes the `--so-prog` custom property rather than `width`).
- **Ledger (`.so-ledger`):** command row (`.so-command` — filter chips, search, selisih/urut/tampil selects), sticky-header table with `tabular-nums` on every count, thin custom scrollbars (`.so-scroll`), live selisih deltas colored by `.is-pos` / `.is-neg`.
- **History panels (`.so-trail`, `.so-history`):** side by side in `col-lg-6` when both exist; when only one exists it takes the full row (`col-12`).
- **Color semantics:** `--so-muted` `#5b6672` light / `#8fa0b8` dark for labels and notes, `--so-ink` `#626d7b` light / body color dark for values, delta negative `#c2353f` light / `#fb977d` dark, delta positive `#0f7a46` light / `#4bd08b` dark — every one measured ≥4.5:1 on its own surface in both themes.
- **Rules:** no new palette and no gradients; light and dark both derive from the app's own theme values; motion is limited to the meter fill and control state transitions, all disabled under `prefers-reduced-motion`.

### Hutang / Piutang List (`.hp-list`)
- **Scope:** `app/Views/hutangpiutang/_list_theme.php`; all selectors live under `.hp-list` and use `--hpl-*` tokens derived from the app theme.
- **Accent:** hutang `--hpl-accent-*` (danger family), piutang (success family). Status uses separate neutral/warning/success soft backgrounds.
- **Layout:** hero (title + actions) → filter panel (search, unit, month, status chips, source/scope) → four-cell summary board (hairline grid, not four cards) → one table card.
- **Table:** six columns (Dokumen, Pihak, Jatuh tempo, Nilai, Status, Aksi); money and dates use `tabular-nums`; at `767.98px` the table becomes stacked cards with `data-label` captions.
- **Rules:** no gradients, no colored left borders, no page-level font changes; muted text stays ≥4.5:1 in both themes; hover motion is limited to background/button state and disabled under `prefers-reduced-motion`.

### Mutasi Stok (`.ms-*`)
- **Scope:** `app/Views/stok/_mutasi_theme.php`; every selector lives under `.mutasi-stok` and derives from `--ms-*` tokens tied to the app theme.
- **Layout:** page head (breadcrumb, title, active-unit chip) → route panel (sender → receiver, send date) → items panel (empty state → line table → summary action). The receive date is not entered here; it is recorded when the receiving unit confirms on `/mutasi_stok/masuk`.
- **Product picker:** modal with custom search, stock chip (≤5 warning, else neutral), supplier, last buy price, and category. Hanya produk dengan stok > 0 untuk unit pengirim yang ditampilkan; stok dan supplier mengikuti unit pengirim, dan pilihan lintas halaman tetap terbawa saat dikonfirmasi. Maps dibatasi ke unit pengguna kecuali peran lintas unit.
- **Line table:** product with code/category/IMEI, buy and sell price, mutation price, send and receive quantity; becomes stacked cards at `767.98px` with `data-label` captions. Money and counts use `tabular-nums`.
- **Rules:** one accent for primary action and focus, no gradients, no colored left borders; dates are locked for non-admin; the only motion is the new-row tint and control state transitions, disabled under `prefers-reduced-motion`.

### Mutasi Masuk / Konfirmasi Terima (`.mutasi-masuk`)
- **Scope:** `app/Views/stok/_mutasi_masuk_theme.php`; reuses the `.ms-*` tokens and components of `app/Views/stok/_mutasi_theme.php` under the same `.mutasi-stok` wrapper, and adds only queue-specific parts (`.mm-*`).
- **Layout:** page head (breadcrumb, title, active-unit chip) → filter panel (search, unit for cross-unit roles, send-date range, apply/reset; status chips auto-submit) → results panel (count pill → antrean table → range/footer pagination). Flash success/failure renders as slim `.mm-flash` banners.
- **Queue table:** nota + id, local date, route (`dari → ke` with visually hidden words), value, status pill with the received date, actions (Terima / Batal terima for Admin Root / Detail). Detail opens as a sub-row with a line table; table becomes stacked cards at `767.98px` and its line table drops the unit-price column below `576px` (the Terima modal still shows it).
- **Modals:** Terima (receipt block, line table, total, H/P due-date notice, `Terima & buat H/P`) and Batal (stacked receipt, danger callout, required reason). `asal` keeps filter+page on the redirect; the JS escapes product names before injecting detail rows.
- **Rules:** same accent/no-gradient rules, no color-only states, focus visible, motion limited to control transitions and the detail caret.


### Payroll Run Sheet (`.pay-*`)
- **Scope:** `app/Views/jurnal/payroll.php` + `app/Views/jurnal/_payroll_theme.php`; every selector lives under `.payroll-page` and derives from `--pay-*` tokens tied to the app theme. No new palette, font, or dependency.
- **Layout:** page head (breadcrumb, title, period badge, month control, Susun/Input actions) → run board (status line + four hairline-separated phases: Susun = unpaid auto, Koreksi = unpaid manual/null, Bayar = paid, Bonus/Lembur = period-scoped kas keluar) → KPI unit strip (score/100, total, meter, tepat/terlambat/menunggu counts) → payroll ledger → bonus/lembur ledger. “Semua bulan” hides the board and KPI, empties the month input, and shows a notice.
- **Ledger:** custom search + status chips (Semua/Rencana/Dibayar/Telat) replace DataTables; `data-status` and `data-late` drive filtering, with Telat counting unpaid overdue rows to match the board alert. Rows become stacked cards at `899.98px` (the six-column table needs ~824px); captions come from `data-label`, amounts align right with `tabular-nums`. Pagination is deliberately omitted at payroll scale (23 rows/month; the all-months bonus list can exceed 100).
- **Bonus/lembur:** period-scoped to the selected month; search covers description, penerima, and unit; lock/unlock and the existing edit/delete modals are preserved; rows use the same stacked-card breakpoint.
- **Color/motion:** phase dots use the app’s primary/warning/success/info semantics; secondary copy uses the derived `--pay-muted` token (≥4.5:1 in both themes); outline-primary labels use `--pay-primary-ink`. Motion is limited to hover/focus/meter and disabled under `prefers-reduced-motion`.
- **Rules:** routes, field names, role gates, modals, overwrite semantics, and DB schema are unchanged. The template’s floating theme customizer is hidden on this page because it covers the right-aligned amount/action column on mobile.

### Riwayat Service Garansi (`.rsg-*`)
- **Scope:** `app/Views/riwayat/service_garansi.php` + `app/Views/riwayat/garansi_service.php` + `app/Views/riwayat/_service_garansi_theme.php`; every selector lives under `.rsg-page` / `.rsg-modal` and derives from `--rsg-*` tokens tied to the app theme. No new palette, font, or dependency.
- **Layout:** page head (breadcrumb, title, count/unit/period chips) → data panel (search → filter band: unit, claim period, actions → table → DataTables footer). The unit select is rendered only for roles 0/1/34/40; other roles see a locked unit display and no posted unit value. The default period is the current month in WIB; explicit empty dates mean all periods.
- **Klaim service (`/garansi_service`):** the same theme hosts the server-side claim list. Filters are unit and `tanggal_selesai` range (default all periods), table columns are No. Service + customer, tanggal selesai + akhir garansi, optional unit, status + riwayat klaim, and actions Klaim/WhatsApp/Riwayat; export keeps the existing `/riwayat_service_garansi/export` route with `sumber=garansi`.
- **Lebar (`/garansi_service`):** `.rsg-page` di halaman ini memakai `max-width: none` untuk menimpa `max-width: 1280px` bawaan tema, sehingga halaman mengisi penuh `.container-fluid mw-100`. Jangan menambahkan `max-width` atau margin horizontal pada wrapper; tidak boleh ada ruang kosong kiri/kanan selain padding container aplikasi.
- **Table:** No. Service + customer, claim date, optional unit column, status, actions; newest claim first via ISO `data-order`; becomes stacked cards at `767.98px` with `data-label` captions. Actions are Riwayat Klaim (modal) and Cetak (existing print route).
- **Modal:** claim note, damaged parts, and replacement parts (HPP, price 0) from the shared `/riwayat-klaim/{id}` endpoint; opened with Bootstrap 5 `getOrCreateInstance`.
- **Rules:** one accent for primary action/focus, no gradients or colored left borders; muted text stays ≥4.5:1 in both themes; motion is limited to control transitions/hover and disabled under `prefers-reduced-motion`; the empty state disables search and skips DataTables init.

### Sparepart Keluar (`.sk-*`)
- **Scope:** `app/Views/riwayat/sparepart_keluar.php` + `app/Views/riwayat/_sparepart_keluar_theme.php`; every selector lives under `.sk-page` / `.sk-modal` and derives from `--sk-*` tokens tied to the app theme. No new palette, font, or dependency.
- **Layout:** full-width di layar lebar — `.sk-page` tidak dibatasi `max-width` 1280px sehingga mengikuti lebar penuh container (`mw-100`), menyisakan hanya padding container; filter tetap ringkas dan rata kiri (maks 280px/220px untuk unit/tanggal) agar kontrol tidak ikut melebar; page head (breadcrumb, title, count/unit/date chips) → data panel (search → filter band: unit, date, page size, reset → table → DataTables footer).
- **Unit filter:** only operational units (`unit.is_operational = 1`) for roles 0/1/34/40; other roles are locked to their session unit — the select is disabled, the head shows a lock chip, and the ajax endpoint enforces the same rule server-side. Head Office and other non-operational units never appear.
- **Table:** Dokumen (invoice/service + source tag), Tanggal (date + WIB time), Sparepart (name + qty), Unit chip, right-aligned HPP and Sub Total with `tabular-nums`, and icon actions (Detail; Edit/Hapus only for Admin Root and non-claim rows). Becomes stacked cards at `767.98px` with `data-label` captions.
- **Modals:** detail uses a definition list; edit/delete keep the existing fields, routes, and role gates. Dynamic row values are escaped before injection.
- **Rules:** layout full-width hanya untuk halaman ini (halaman lain tetap 1280px); one accent for primary action/focus, no gradients or colored left borders; muted text stays ≥4.5:1 in both themes; motion is limited to control transitions/hover and disabled under `prefers-reduced-motion`.

### Detail Klaim Garansi (`.cg-*`)
- **Scope:** `app/Views/transaksi/table_garansi/claim_detail.php` + `_claim_theme.php`; every selector lives under `.cg-page` / `.cg-modal` and derives from `--cg-*` tokens tied to the app theme. No new palette, font, or dependency.
- **Layout:** hero (breadcrumb, back, service code, active-warranty/unit chips) → data service card (customer row, four-field grid, keluhan/keterangan notes) → damage card → numbered claim form (catatan → sparepart rusak → sparepart pengganti) → action bar. The action bar is sticky on desktop and static under `768px`.
- **Tables:** Damaged parts become stacked cards at `767.98px` (checkbox, name, qty, note) so mobile users do not need horizontal scroll; the replacement table keeps horizontal scroll with a visible hint. Selected damaged rows tint with the single accent color.
- **Rules:** location text uses `pelanggan.kecamatan` + `kabupaten`, not the long street address; one accent for primary action/focus; no gradients or colored left borders; muted text stays ≥4.5:1 in both themes; motion is limited to control transitions and disabled under `prefers-reduced-motion`.

### Retur Barang Rusak (`.rt-*`)
- **Scope:** `app/Views/retur/retur_barang_rusak.php` + `app/Views/retur/_retur_theme.php`; every selector lives under `.rt-page` / `.rt-modal` and derives from `--rt-*` tokens tied to the app theme. No new palette, font, or dependency.
- **Layout:** page head (breadcrumb, title, count/period/unit chips) → filter band (unit, claim-date range, reset) → claim panel (document band: supplier, return date, note → claim table → action bar with selected count and HPP total) → return-history panel. The claim table is the primary surface; history stays visible below it.
- **Claim table:** nine working columns — pick, document (service + claim date + unit chip), claimed item (sparepart + damaged part + qty), original HPP, return status with inline status switcher, manual HPP, return result, replacement (part + qty), note. Selecting a row enables its inputs and tints the row with the single accent color; the action bar totals manual HPP × qty in `tabular-nums`.
- **Responsive:** below `991.98px` the claim and history tables become stacked cards with `data-label` captions; below `575.98px` the document band and filters stack full width and the save action spans the panel. The detail modal keeps horizontal scroll for its line table.
- **Rules:** keep the return-status switcher next to the status badge, never as a far-right column; supplier/date gates, field names, routes, and existing save/cancel semantics are unchanged. The status switch posts its own form, warns before discarding unsaved row input, and carries the active unit/date filter back through the `kembali` field. One accent for primary action/focus, no gradients or colored left borders; muted text stays ≥4.5:1 in both themes; motion is limited to control transitions and disabled under `prefers-reduced-motion`.

## Responsive Behavior
- **Mobile (<768px):** Stacked layouts, icon-only navigation, full-width filters.
- **Tablet (768-991px):** Adjusted spacing, wrapped filter groups.
- **Desktop (≥992px):** Full multi-column layouts, side-by-side forms and tables.

## Accessibility
- **Focus States:** Visible focus rings on all interactive elements.
- **Reduced Motion:** All transitions disabled under `prefers-reduced-motion`.
- **High Contrast:** Forced colors mode support with CanvasText borders.
- **Semantic HTML:** Proper heading hierarchy, ARIA labels, table structure.
