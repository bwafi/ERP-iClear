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


## Responsive Behavior
- **Mobile (<768px):** Stacked layouts, icon-only navigation, full-width filters.
- **Tablet (768-991px):** Adjusted spacing, wrapped filter groups.
- **Desktop (≥992px):** Full multi-column layouts, side-by-side forms and tables.

## Accessibility
- **Focus States:** Visible focus rings on all interactive elements.
- **Reduced Motion:** All transitions disabled under `prefers-reduced-motion`.
- **High Contrast:** Forced colors mode support with CanvasText borders.
- **Semantic HTML:** Proper heading hierarchy, ARIA labels, table structure.
