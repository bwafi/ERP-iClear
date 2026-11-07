# DESIGN.md — Kas & Bank Visual System

## Visual Identity & Theme
- **Framework:** Bootstrap 5 (Modernize admin style) with dark/light mode compatibility.
- **Brand Context:** iClear ERP — Professional, financial clarity, operational-first density.
- **Design Language:** Two layers: the shell's card-based admin vocabulary for the module's operational pages, and the statement-sheet vocabulary (`.kbx-*`) for the Kas & Bank dashboard and account register — flat sheets, hairline rules, underline tabs, tabular figures.

## System Color Semantics
- **KAS (Cash):** Primary theme color — Tunai & physical cash drawer.
- **BANK (Bank Account):** Warning theme color — Bank accounts & transfer legs.
- **HUTANG (Payables):** Danger theme color — Inter-unit obligations & debt balances.
- **PIUTANG (Receivables):** Success theme color — Tagihan & receivables.
- **TRANSFER (Internal Move):** Info theme color — Internal non-operational transfers.
- **ATRIBUSI (Same Account Settlement):** Secondary theme color — Non-cash H/P clearing.

## Component & Layout System

### Navigation (`_nav.php`)
- **Scope:** shared header for `/kas_bank`, `/kas_bank/akun`, `/kas_bank/transfer`, `/kas_bank/antar-unit`.
- **Header:** flat on the page canvas — title `1.5rem`/600 with `-0.025em` tracking, one-line description, breadcrumb right-aligned on desktop; no card, no shadow.
- **Tabs:** underline tabs (2px active border in `--bs-primary`) over a 1px hairline; icon + label + optional count. Active = primary ink and primary underline, inactive = muted; no pill, no elevation.
- **Counts:** plain text (`12 rekening`, `6 bank`, `N terbuka`), not colored pills; hidden below `992px`. Labels are hidden below `768px` so the four tabs fit as icons, each still carrying `aria-label`.
- **Titles:** `$__titles` is keyed by `ringkasan|rekening|mutasi|antar`; the mutasi key must stay `mutasi` (older `pindah`/`tunai` keys never matched and showed the dashboard title).

### Metric Strip (`.kb-stat`) — legacy
- **Status:** no current page uses it. `/kas_bank/akun` and the dashboard now use the `.kbx-*` summary sheet. Do not revive this strip for new pages.
- **Structure (if ever reused):** label above value, values in `tabular-nums`; no icon tiles and no hover lift.
- **Note:** `.kb-metric-card` in `_theme.php` is legacy and unused; do not revive it for new pages.

### Data Cards (`.kb-data-card`)
- **Purpose:** Container for tables and data displays.
- **Structure:** Header with title/subtitle/legend, scrollable table wrapper, optional footer note.
- **Table Style:** Custom `.kb-table` with uppercase headers, hover states, tabular numbers.
- **Tags:** Color-coded `.kb-tag` system for status, type, and category labels.

### Riwayat Pindah Saldo (`.kbt-*`)
- **Scope:** `app/Views/kas_bank/transfer.php`; selector hanya di panel riwayat dan memakai token `--kb-*` dari `_theme.php`. Tidak ada palet baru.
- **Layout:** header kartu memuat judul, konteks unit ("Semua unit" saat konsolidasi), dan ringkasan "Total tampil" + jumlah transfer. Tabel enam kolom: Tanggal/Ref, Unit, Alur Dana, Nominal, Bukti, Aksi (aksi hanya bila `can_reversal`).
- **Unit:** kolom Unit menampilkan `transaksi_kas_bank.unit_id` (unit yang mencatat), bukan pemilik rekening. Pemilik rekening tampil sebagai meta di bawah nama akun: nama unit, "Rekening bersama", atau "Finance/HO".
- **Alur Dana:** pengirim → penerima dalam satu sel; nama akun tebal, pemilik di bawahnya. Nominal rata kanan, `tabular-nums`; referensi `kb-mono`.
- **Responsive:** di bawah `768px` header tabel disembunyikan secara visual (tetap di accessibility tree) dan tiap baris menjadi blok dengan label `data-label`.
- **Rules:** tidak mengubah query/filter/route; tetap satu aksen (primary) untuk titik unit dan fokus; tanpa gradien atau border kiri berwarna; motion hanya pada state kontrol.

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

### Proses Service (`.ps-*`)
- **Scope:** `app/Views/riwayat/proses_service.php` + `app/Views/riwayat/_proses_service_theme.php`; every selector lives under `.ps-page` / `.ps-modal` and derives from `--ps-*` tokens tied to the app theme. Modal status dirender di body, jadi token juga dipasang di `.ps-modal`. No new palette, font, or dependency.
- **Layout:** full-width (`max-width: none`) page head (breadcrumb, title, count/unit/period chips) → data panel (search → filter band: unit, tanggal masuk, page size, reset, export → table → DataTables footer). Default period is 30 days; `?search=` dari halaman lain mengosongkan tanggal agar hasil lintas periode.
- **Unit filter:** hanya role 0 (Finance) dan 1 (Admin Root) yang boleh memilih unit; role lain melihat unit terkunci dan tidak mengirim nilai unit. Endpoint AJAX tetap memaksa unit sesi untuk role terbatas.
- **Table:** seven working columns — Prioritas, Service (no. service + tanggal masuk + unit), Pelanggan (avatar + nama + HP + tipe), Lama (hari), Jenis (Garansi/Baru), Status proses, Aksi. Default order `rank` (garansi → prioritas → tanggal masuk terbaru) dan mapping kolom server-side mengikuti urutan ini.
- **Priority & status:** prioritas adalah tombol bintang dengan state terisi; status proses adalah badge yang bisa diklik untuk membuka modal radio 6 tahap (Belum Dicek, Sedang Dicek, Sedang Dikerjakan, Sedang Testing, Menunggu Konfirmasi, Menunggu Sparepart).
- **Actions:** dua baris — lifecycle (Bisa Diambil / Sudah Diambil / Batalkan) dan tools (edit, Nota, Struk, WhatsApp). Dua tombol cetak diberi label teks dan ikon berbeda (dokumen vs printer thermal) supaya tidak tertukar. Data kerusakan/sparepart yang belum lengkap menandai tombol dengan titik amber dan peringatan di modal; semua nilai dinamis di-escape sebelum masuk atribut HTML.
- **Responsive:** di bawah `1200px` tabel menjadi stacked cards dengan `data-label` (tabel tujuh kolom + aksi berlabel butuh ~1174px); filter menjadi dua kolom di bawah `991.98px` dan satu kolom di bawah `575.98px`.
- **Rules:** route, field POST, query server-side, dan perilaku bisnis tidak berubah; satu aksen untuk aksi utama/fokus, tanpa gradien atau border kiri berwarna; muted text ≥4.5:1 di kedua tema; motion terbatas pada transisi kontrol dan dimatikan under `prefers-reduced-motion`.

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

### Riwayat Absen (`.ra-*`)
- **Scope:** `app/Views/riwayat/semua_presensi.php`; every selector lives under `.ra-page` (the photo viewer lives inside it too) and derives from `--ra-*` tokens tied to the app theme. No new palette, font, or dependency; the map and DataTables are the template's own libraries.
- **Layout:** page head berupa kartu putih ber-hairline (breadcrumb, title, Export Excel) → five-cell summary board (Total, Tepat waktu, Toleransi, Telat, Belum konfirmasi) → filter band (date range + reset + hint) → log panel (length/search toolbar → table → info/pagination). Board figures come from the rows DataTables currently shows, so they follow the date filter and search.
- **Table:** Pegawai (name + unit), Tanggal, Jadwal (jam + toleransi), Masuk, Pulang, Kehadiran, Status, Detail. Dates carry ISO `data-order` so sorting is chronological; the date filter reads that ISO attribute, not the `dd-mm-yyyy` display text. Below `767.98px` rows become stacked cards with `data-label` captions.
- **Detail modal:** employee header (identity, unit · date, status tags) → facts list as hairline rows (jam masuk/pulang, jadwal, toleransi, jarak, IP, koordinat) → "Penilaian seragam" checklist (Seragam +3, Sepatu +1, ID Card +1, live 0–5 score, one primary save) → Leaflet map + masuk/pulang photos in the media column. Values saved from an older manual input show the old score plus the "Nilai ulang dari foto" action instead of the checklist. Below `992px` the facts and checklist come first, media after. Clicking a photo opens the dark viewer with pan/zoom; the viewer reopens the detail modal when closed so the two modals never stack.
- **Rules:** mobile (sumber `mobile`) photos come from `/absen/foto/{id}[/pulang]`, legacy photos from `/foto_presensi/{file}`; the template customizer is hidden because it covers the action column. Routes, export dates, and the 15-day default window are unchanged. One accent for primary action/focus, no gradients or colored left borders; muted text stays ≥4.5:1 in both themes; motion is limited to control transitions and disabled under `prefers-reduced-motion`. Pagination uses the 32px windowed box buttons of `/kas_keluar` (`1 … n-1 n n+1 … last`); DataTables core renders `a.paginate_button`, not Bootstrap 5's `.page-link`, so the theme styles that markup. The unit select appears only for roles `0/1/2/34` (all units) and SPV `40` (their `spv_units`); SPV rows are already limited server-side, and the export posts the same unit scope.

### Riwayat Presensi Pegawai (`.rp-*`)
- **Scope:** `app/Views/riwayat/presensi.php` + `app/Views/riwayat/_riwayat_presensi_theme.php`; every selector lives under `.rp-page` / `.rp-modal` and derives from `--rp-*` tokens tied to the app theme. The accent is the same blue as `.ra-*` so the two riwayat pages read as siblings; no new palette, font, or dependency.
- **Layout:** page head (breadcrumb, title, unit chip + row count) → five-cell summary board (Total, Tepat waktu, Toleransi, Telat, Belum konfirmasi) → filter band (date range + reset + hint) → log panel (DataTables toolbar → table → footer). Board figures come from the rows DataTables currently shows, so they follow the date filter and search.
- **Table:** eight columns — Tanggal, Jadwal masuk, Toleransi, Jam masuk, Jam pulang, Kehadiran, Status presensi, Detail. The Nama column is omitted because this page only lists the signed-in employee's own records; the name lives in the detail modal header. Dates carry ISO `data-order` (read by the date filter, not the `dd-mm-yyyy` display text) plus the Indonesian weekday as a note. Rows become stacked cards at `991.98px` with `data-label` captions (the eight columns need ~925px, so tablets get cards instead of horizontal scroll).
- **Detail modal:** identity header (avatar, name, unit · date, status tags) → media column (Leaflet map + masuk/pulang photos) and a facts panel with hairline rows (jam masuk/pulang, jadwal, toleransi, jarak, IP, koordinat). Photo clicks open the dark viewer with pan/zoom; the viewer reopens the detail modal when closed so the two modals never stack.
- **Rules:** mobile (sumber `mobile`) photos come from `/absen/foto/{id}[/pulang]`, legacy photos from `/foto_presensi/{file}`; the "Belum dikonfirmasi" state is a label because it offers no action. Routes, query, and the 15-day default window are unchanged; the template customizer is hidden because it covers the action column. One accent, no gradients or colored left borders; muted text stays ≥4.5:1 in both themes; motion is limited to control transitions and disabled under `prefers-reduced-motion`. Pagination matches `/kas_keluar`: 32px windowed box buttons (`1 … n-1 n n+1 … last`) styled on DataTables' `a.paginate_button` markup.

### Approval Izin (`.ap-*`)
- **Scope:** `app/Views/admin/approval_izin.php` + `_approval_izin_theme.php`; semua selector di bawah `.ap-page` dan memakai token `--ap-*` dari tema aplikasi.
- **Layout:** page head berupa kartu putih ber-hairline (breadcrumb, title, tombol Buat Pengajuan) → papan ringkasan empat sel (total, menunggu, disetujui, ditolak) → kartu filter status + unit → kartu tabel dengan form keputusan inline.
- **Rules:** route, query, field keputusan (`idizin`, `status`, `balasan`), dan aturan approval tidak berubah. Filter unit mengikuti scope jabatan yang sama dengan `/semua_riwayat_presensi`: role `0/1/2/34` melihat semua unit, SPV `40` hanya `spv_units`, dan baris SPV sudah dibatasi server. Keputusan approval SPV juga divalidasi server ke `spv_units`, bukan hanya disembunyikan di tampilan. Satu aksen primary; muted text ≥4.5:1 di kedua tema; tabel menjadi stacked cards dengan `data-label` di bawah `768px`.

### Roster Absensi (`/jadwal_masuk`, `.jad-*`)
- **Scope:** `app/Views/absensi/jadwal_masuk.php`; semua selector di bawah `.jad` dan memakai token `--jad-*` yang diturunkan dari tema aplikasi. Tanpa palet, font, atau dependency baru.
- **Layout:** kartu head (judul + deskripsi + tombol "Buka Absen Mobile" + toolbar bulan/unit/pegawai) → flash → kartu pola mingguan yang selalu tampil → lembar roster bulanan. Head dan toolbar berbagi satu permukaan putih ber-hairline (`.jad-top`); toolbar bukan lagi strip di kanvas dan tidak punya garis bawah sendiri.
- **Pola mingguan:** tujuh select Sen–Min selalu terlihat; tombol outline "Terapkan pola" hanya mengisi tanggal yang belum punya baris roster. Aksi utama tetap "Simpan Roster" (primary).
- **Sel tanggal:** tanggal + select shift; catatan per tanggal memakai tombol `+ Catatan` yang membuka input dan otomatis terbuka untuk shift `IZIN` atau tanggal yang sudah punya catatan. Tanggal lampau tampil sebagai chip terkunci.
- **Responsive:** di bawah `992px` grid pola menjadi 4 kolom, di bawah `768px` menjadi 2 kolom dan grid kalender berubah menjadi daftar baris (tanggal kiri, kontrol kanan); tombol pola dan simpan menjadi full width.
- **Rules:** route, field POST (`pola[]`, `shift[]`, `keterangan[]`), CSRF, dan aturan scope/koreksi tanggal lampau tidak berubah. Satu aksen primary, tanpa gradien atau bayangan kartu; muted text ≥4.5:1 di kedua tema; motion hanya pada state kontrol dan dimatikan under `prefers-reduced-motion`.

### Penilaian Harian (`.pa-*`)
- **Scope:** `app/Views/penilaian/penilaian_absen.php` + `_penilaian_absen_theme.php`; every selector lives under `.pa-page` / `.pa-modal` and derives from `--pa-*` tokens tied to the app theme. No new palette, font, or dependency.
- **Layout:** page head on the canvas (breadcrumb, title, period) → filter toolbar with a bottom hairline (month, year, typed employee Select2, reset) → monthly board (identity + score out of 100, four hairline-separated components) → input sheet (date + Seragam note, Kebersihan/Kepatuhan selects) → history ledger. Kehadiran and Seragam are scored from mobile attendance, so the sheet takes no attendance status, shift, or punch times.
- **Matrix:** the desktop table has a sticky component column and one column per day inside a horizontal scroll; below `768px` it becomes one card per component with a 7-column wrapped day grid, so the month does not require horizontal scrolling. OFF/IZIN/SAKIT/CUTI cells are neutral; scores 1–2 danger, 3 warning, 4–5 success; Kehadiran tooltips carry shift/jam/telat details.
- **Select2:** the employee filter inherits `form-select-sm` height, padding, radius, and focus ring; the global `40px` height in `styles.css` is overridden, and the arrow is a chevron matching the native selects.
- **Rules:** routes, CSRF, evaluator authorization, and the overwrite-confirmation modal are unchanged; the sheet only posts `tanggal` and `skor_*` (plus hidden employee/period), and the attendance/decor rows stay read-only. One accent for primary action/focus; no gradients or colored left borders; muted text ≥4.5:1 in both themes; motion is limited to control state and disabled under `prefers-reduced-motion`.

### Presensi Mobile (`/absen`, `/jadwal`, `/pengajuan_izin`, `/profil`) (`.mob`)
- **Scope:** `app/Views/absen/mobile.php`, `app/Views/jadwal/mobile.php`, `app/Views/izin/pengajuan.php`, `app/Views/profil/mobile.php`, `app/Views/inc/_mobile_nav.php`, dan tema bersama `app/Views/inc/_presensi_mobile_theme.php`; semua selector berada di bawah `.mob`/`.mob-nav` dan memakai token `--mob-*`. Halaman standalone mobile-first (tanpa layout `template`), maksimal `480px`.
- **Thesis:** presensi adalah ritual harian personal, bukan form ERP. Kanvas Aurora (navy `#070d18` gelap / `#eef5fb` terang) diterangi wash ambient teal–indigo–sky; satu lembar kaca per halaman membuat jam dan aksi berikutnya menjadi pusat, sisanya turun menjadi rail dan daftar berhairline. Menolak tumpukan kartu admin dan chrome biru korporat.
- **`/absen`:** kepala halaman (avatar inisial `38px`, nama, unit · tanggal — tanpa sapaan) → satu lembar kaca dengan blok berhairline: **shift** (label shift, status kata + titik, jam berjalan dengan detik aksen di kanan atas, rentang jam besar sebagai hero), **rel** (garis progres tipis dengan titik done/now/todo dan label langkah; jam per langkah tidak diulang karena rentang sudah memuatnya), **aksi** (judul langkah, satu baris petunjuk, slot foto, satu CTA), dan **hari ini** (ringkasan `Masuk 08:50 (Tepat) · Break 13:00`, jumlah langkah, thumbnail foto). Tanpa kicker, chip "Bisa sekarang", strip "Sudah", baris meta, footer, dan empty-state besar. State go-live, tanpa roster, `OFF`, `IZIN` memakai lembar kaca yang sama. In break PS sebelum `15:00` tampil sebagai state terkunci: tombol `disabled`, chip hitung mundur `Buka dalam H:MM:SS`/`m:ss`, jadwal sesi tetap `16:55`.
- **Slot foto:** input file disembunyikan di dalam slot setinggi `56px` berisian translusen; ikon kamera berganti pratinjau `36px` setelah foto dipilih. Satu CTA aksen solid per keadaan (`Absen Masuk`/`Absen Pulang`) — teks putih di terang, `--mob-cta-ink` di gelap; tanpa gradien.
- **`/jadwal`:** kepala halaman (avatar inisial, kicker Jadwal, nama · unit) → satu lembar kaca berisi navigasi bulan (panah kiri/kanan, label bulan, jumlah hari kerja), strip tiga metrik (Kerja · Libur · Izin), kalender tujuh kolom dengan label shift per tanggal, penanda hari ini berupa ring aksen, dan catatan tanggal di lembar terpisah. Read-only: tanpa form, POST, atau tombol simpan; warna shift memakai token `--cal-*` yang aman di terang dan gelap.
- **`/pengajuan_izin`:** kepala halaman berkicker → flash ramping → sheet form (jenis, pasangan tanggal, alasan, surat dokter hanya untuk `SAKIT`) dengan CTA gradien di dalam sheet → sheet riwayat berhairline. Status memakai pil (Menunggu kuning, Disetujui mint, Ditolak rose); balasan atasan berupa blok inset.
- **`/profil`:** sheet identitas (avatar gradien, nama, jabatan · unit, rincian NOID/email berhairline) + tombol Keluar kaca. Read-only dari sesi; edit profil belum ada.
- **Permukaan kaca:** sheet, lembar shift, slot foto, chip, thumb, avatar, flash, dan dialog install memakai `--mob-glass*`/`--mob-field*`: `backdrop-filter: blur(18px) saturate(160%)` + `-webkit-` prefix, border putih tipis, inner highlight atas, drop shadow ber-offset, radius `13–26px`. Blur hanya pada permukaan besar; kontrol kecil memakai isian translusen + inner highlight. Tanpa `backdrop-filter`, permukaan menjadi putih susu `.96` (gelap `rgba(10,19,32,.97)`).
- **Navbar bawah (dock Aurora):** empat tab — Absen · Jadwal · Izin · Profil — sebagai dock kaca melayang `12px` dari tepi (maksimal `456px`, `border-radius: 999px`), bukan bar penuh. Tab aktif adalah pill wash aksen dengan `aria-current="page"`; pill bergeser antar tab lewat cross-document view transition (`view-transition-name: mob-nav-pill`), dan dock tetap diam (`mob-nav`) saat konten berganti. Konten `.mob` diberi ruang bawah agar tidak tertutup.
- **PWA:** satu app "Absen iClear" (`start_url=/absen`, `scope=/` agar `/login` tidak keluar jendela app), manifest dari `Pwa::manifest`, service worker `public/sw.js` hanya meng-cache ikon PWA; halaman dan API tetap online. Manifest/theme-color/apple-touch-icon dan registrasi SW dimuat dari tema bersama.
- **Ajakan install:** dialog `<dialog>` (`inc/_pwa_install.php`) muncul sekali per 7 hari di `/absen`, `/jadwal`, `/pengajuan_izin`, `/profil`; tombol Install memanggil `prompt()` dari `beforeinstallprompt`, tombol Nanti menutup. Tidak muncul saat sudah `standalone` atau `appinstalled`. Safari iOS tidak punya API install, jadi dialog berubah menjadi instruksi "Bagikan → Tambahkan ke Layar Utama" dengan satu tombol Mengerti.
- **Tema:** mengikuti `localStorage['app-theme']` yang dipakai shell ERP (fallback preferensi sistem), bukan hanya `prefers-color-scheme`. Aurora memakai aksen `#0f766e` terang / `#5eead4` gelap, muted `#4f657a` (5.49:1) / `#8fa6c4` (7.8:1), kanvas `#eef5fb` / `#070d18`, kaca `rgba(255,255,255,.62)` / `rgba(255,255,255,.065)`. CTA gradien `#5eead4→#38bdf8` dengan teks `#04121f` (≥8.8:1).
- **Rules:** route, nama field, CSRF, alur GPS/kompresi foto, dan aturan bisnis tidak berubah. Target sentuh ≥ `44px`, fokus terlihat, teks kecil ≥4.5:1 di kedua tema; motion hanya pada reveal pratinjau, state kontrol, transisi halaman (fade + geser `10px`, `260ms`), dan geser pill nav (`300ms`), dimatikan under `prefers-reduced-motion`. Fallback tanpa View Transitions tetap menampilkan halaman dan pill secara instan.

### Lembar Posisi Kas (`.kbx-*`)
- **Scope:** `app/Views/kas_bank/dashboard.php`, `app/Views/kas_bank/akun.php`, `_posisi_theme.php`, and `_akun_theme.php`; every selector lives under `.kbx` and derives from `--kbx-*` tokens tied to the app theme. The older `.kb-*` components in `_theme.php` stay for `transfer`/`antar_unit`.
- **Thesis:** the dashboard is one treasury position sheet, not a widget grid. The physical-vs-entitlement duality is a single statement: hero total, Kas/Bank as ruled columns, one thin composition bar, and an identity footer (`Fisik = Dialokasikan + Belum dialokasikan`, or `Fisik semua unit · Hak unit ini` when a unit is selected).
- **Layout:** page head → slim filter toolbar on the canvas (bottom hairline, not a card) → sheet (meta line → hero + cash-flow column → composition bar + reading footnote → identity footer) → alert strips → full-width ledger with `tfoot` total. Cash flow is a ruled list inside the sheet, so no empty column is left under a four-row table.
- **Register (`akun`):** the same sheet used as a working register — meta line, 2rem physical total, 2×2 ruled facts, and the worded identity footer; one working card with four underline tabs (Rekening, Saldo awal, Jatah cabang, Opening kas) whose labels carry the live count/date/total; then the full-width ledger. The first tab is the account form (Kas/Bank segmented radio pair, not a select); the other tabs are single compact forms with the cut-off date rendered as locked text, never an editable date input. Forms cap at `48rem`; the opening table and ledger stack with `data-label` captions below `768px`; the shared-account allocation strip stays attached to its account row. Each ledger row carries a contextual action (Isi saldo for BANK, Isi opening for KAS) that opens the matching tab with that account preselected; the active tab is mirrored in the URL hash (`#rekening`, `#saldo-awal`, `#jatah`, `#opening-kas`) and survives the save redirect. Page selectors are `.kba-*` scoped under `.kbx`.
- **Ledger:** Rekening (name + unit/shared meta), Jenis (Kas/Bank mark), Saldo fisik, and Dialokasikan/Hak unit ini with a 72×3px share bar plus `% dari fisik`, `Belum Rp …`, `Penuh`, `Melebihi fisik`, or `Saldo minus`. The hero shows physical total when consolidated and unit entitlement when a unit is selected; the composition bar follows the same numbers.
- **KAS ownership:** a KAS account always belongs to exactly one unit and never has rows in `alokasi_saldo_kas_bank`; in consolidated mode it is shown as 100% dialokasikan with the note `Milik unit`, and only BANK accounts feed `Belum dialokasikan`. The footer identity `Fisik = Dialokasikan + Belum` therefore counts unit-owned cash as allocated. Never print `Dialokasikan Rp 0` on a KAS account.
- **Numbers:** `font-variant-numeric: tabular-nums` everywhere; Indonesian formatting `Rp 269.776.654`; minus sign `−`; totals separated by a 3px double rule (`--kbx-line-strong`), never by weight alone.
- **Color:** ink `--bs-emphasis-color` for the main figure and KAS amounts, muted `#5b6672` light / `#8fa0b8` dark, hairlines `--bs-border-color`, control borders `#7d8a99` light / `#8fa0b8` dark (≥3:1). `--bs-primary` fills KAS marks, bars and the active tab underline; KAS text uses the readable ink `#0069a8` light / `#66b6e9` dark. The primary action uses `#0074ba` (white text 4.99:1). BANK uses amber `#b45309` light / `#fbbf24` dark only as a mark and bar segment. Flow signs use measured tokens `#0f7a46`/`#4bd08b` (positive) and `#c2353f`/`#fb977d` (negative); zero values are muted. No gradients, no shadows inside the sheet, square-ish `6px` radius.
- **Responsive:** below `992px` the hero stacks and cash flow moves under the hero with a top hairline; below `768px` the unit and account filters go full width while the two date fields stay paired at 45%, the ledger becomes labelled blocks with `data-label` captions, and the footer total becomes a two-column label/value grid.
- **Motion:** only the composition bar grows once (`scaleX`, `0.45s`), disabled under `prefers-reduced-motion`; hover is limited to ledger row background and control state.
- **Cash-flow label:** the double-rule total is `Arus kas operasional` (pemasukan − pengeluaran) and carries the note that internal transfers and inter-branch settlements are excluded; never label it as the sum of every row above it.
- **Rules:** routes, controller data, filters, role/unit scope, and money semantics are unchanged. Do not add a second accent, colored left borders, or a card shadow inside `.kbx`.

### Invoice Internal (cetak/PDF)
- **Scope:** `app/Views/cetak/invoice_internal.php`, dirender mPDF untuk `asset_berjalan?...&invoice=1`. Dokumen cetak, bukan halaman web.
- **Layout:** masthead (wordmark serif + judul Invoice + nomor) → hairline navy → ditagihkan kepada + metadata → ledger rincian tanpa kotak → total di atas rule penuh → pembayaran → tanda tangan. Tidak ada header tabel gelap, border luar tabel, atau blok abu-abu.
- **Type:** DejaVu Serif untuk wordmark/judul, DejaVu Sans Condensed untuk body dan angka; nominal rata kanan, tebal, dan memakai `Rp` bertitik.
- **Color:** navy `#0f2a43` untuk ink utama, `#5b6672` untuk label, hairline `#cfd8e3`/`#e3e8ee`. Tanpa gradien atau isian latar dekoratif.
- **Royalti:** ditampilkan langsung sebagai nominal, tanpa rincian turunan omset/biaya.
- **Gaji Cabang:** halaman 1 hanya menampilkan total + catatan rincian ada di halaman berikutnya; matriks komponennya ada di halaman 2 sebagai section pertama (Gaji Pokok, Tunj. Kinerja, Tunj. Absensi, Tunj. Penempatan, Insentif, Lembur, Penyesuaian, Jumlah).
- **Gaji HO:** halaman 1 hanya menampilkan total Gaji HO + catatan rincian ada di halaman berikutnya. Halaman 2 (hanya bila ada rincian gaji) adalah lampiran matriks dengan urutan section: gaji karyawan cabang lalu gaji HO: baris = karyawan, kolom = komponen, sel kosong "–", dan baris Jumlah = total. Tunj. Penempatan HO hanya muncul di unit pusat (Probolinggo), tidak di cabang. Karyawan yang sudah dibayar memakai nominal aktual (termasuk bonus/potongan) dan selisihnya masuk "Penyesuaian"; karyawan lain tetap perkiraan (aktual menggantikan, bukan ditambahkan).
- **Tanda tangan:** label Finance/Manager, garis tanda tangan, lalu nama penanda tangan di bawah garis. Nama diambil dari akun aktif jabatan Finance (0) dan Manager (34), bukan hardcode.
- **mPDF constraints:** hanya tabel/block; hindari flex/grid. Garis tanda tangan memakai sel tabel dengan `height` + `border-bottom`, bukan div kosong. Untuk blok tanda tangan, pakai selector kelas tunggal (descendant selector pada blok itu tidak diterapkan mPDF).

### Bukti Tutup Kasir (cetak/PDF)
- **Scope:** `app/Views/cetak/cetak_tutupkasir.php`, dirender mPDF A4 oleh `TutupKasir::cetak_tutup_kasir`. Dokumen cetak, bukan halaman web.
- **Layout:** masthead (wordmark + unit + alamat di kiri, judul `Tutup Kasir #<id>` + tanggal/waktu/status di kanan) → rule 2pt → strip identitas (kasir, jabatan, unit) → pita rekonsiliasi (kas sistem, kas fisik, cap selisih) → buku kas → rekap transaksi + rincian pengeluaran → catatan rumus → pengesahan → footer.
- **Rekonsiliasi:** dua angka utama memakai `Rp` dan 18pt; cap selisih di kanan berisi label, verdict, dan nominal. `Cocok` hijau `#0f7a46`; `Lebih`/`Kurang` merah `#b42318`. Angka nol di ledger ditulis `–`; pengeluaran dalam tanda kurung.
- **Type & color:** DejaVu Sans Condensed; nominal `tabular-nums`; navy `#0f2a43`, label `#5b6672`, hairline `#cfd8e3`/`#e3e8ee`. Tanpa gradien, kartu, atau isian latar dekoratif.
- **Rules:** seluruh angka uang dari baris `tutup_kasir`, bukan dihitung ulang; struktur tabel mPDF (tanpa flex/grid); warna verdict memakai inline style karena descendant selector di dalam tabel bersarang tidak diterapkan mPDF. Penanda tangan "Mengetahui" adalah SPV area unit dari `spv_units` (akun aktif), bukan Manager global; unit tanpa SPV mencetak garis kosong berlabel SPV.

## Responsive Behavior
- **Mobile (<768px):** Stacked layouts, icon-only navigation, full-width filters.
- **Tablet (768-991px):** Adjusted spacing, wrapped filter groups.
- **Desktop (≥992px):** Full multi-column layouts, side-by-side forms and tables.

## Accessibility
- **Focus States:** Visible focus rings on all interactive elements.
- **Reduced Motion:** All transitions disabled under `prefers-reduced-motion`.
- **High Contrast:** Forced colors mode support with CanvasText borders.
- **Semantic HTML:** Proper heading hierarchy, ARIA labels, table structure.
