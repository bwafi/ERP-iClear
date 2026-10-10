# DESIGN — Surface design decisions

Repo: ERP (CodeIgniter 4 + Bootstrap 5 Modernize admin, light/dark). World established and committed (see PRODUCT.md); designs below use Modernize tokens only and compose within the committed world.

Token correction: the briefs' OWN-WORLD hex (#5D87FF primary, #13DEB9 success) are the template's default-light values; the app's active Blue_Theme overrides primary to `#0085db` and success to `#4bd08b` in `public/template/assets/css/styles.css`. All surfaces consume the resolved CSS vars (`--bs-primary`, `--bs-success`, `--bs-border-color`, …) so the shipped colors are the app's actual tokens.

---

## /service — Vertical journey timeline

Status: shipped 2026-09-25 · direction rank 5/7, seed `bb50b654`. Brief: `.impeccable/surfaces/service.md` (with verdict + provenance).

### Composition
- **Header card** unchanged (title + breadcrumb).
- **Two-column deck** `#service-deck`: left `#service-rail` (vertical milestone rail, 4 nodes, connector line) · right the active step's form card with consistent `.service-step-footer` (Sebelumnya outline-secondary / Selanjutnya primary, Submit success).
- **Running ticket summary** `.service-summary-strip` pins pelanggan · perangkat · kerusakan n · sparepart n · DP · total across the top once a ticket exists (`#service-summary-col` hidden when no ticket).
- Responsive: ≥1200 vertical rail; ≤1199 horizontal stepper (overflow-x auto); ≤575 2×2 grid wrap — every step visible without scroll.

### Rail states (applied by `refreshServiceState()` on input/change/tab and at boot)
- done = success check node + green connector; active = primary ring on visible step; locked = dashed tertiary node, `pointer-events:none`, opacity .6; not-started = numbered node, helper copy in `.service-rail-sub`.
- `is-locked` beats `is-done` (kasir jabatan-36 sparepart step stays locked even when ticket rows exist).
- Helper copy gates later steps when no ticket ("Lengkapi data pelanggan dulu.").

### Behavior contracts kept
- 4 exact steps/routes; each step posts its own form; `?tab=` deep links + localStorage `activeTab` restore + tab memory; `shown.bs.tab` drives rail active ring; role gating preserved (kasir sparepart lock via `show.bs.tab` preventDefault + inert link).

### Defects found & fixed during verification (see brief verdict)
1. `role="tablist"` missing → Bootstrap Tab `_parent=null` → `Illegal invocation`, tabs dead. Fixed.
2. Lock/done precedence bug stripped `is-locked`. Fixed (lock always wins).
3. Mobile rail overflow → 2×2 wrap. Fixed.

### Verification
- `php -l` clean on the 5 changed views.
- Playwright/Chromium suite 23/23 (layout, rail states, switching, summary mirrors, kasir lock, empty state, deep-link; desktop + mobile).
- Detector: only template-wide tokens (Modernize muted text ~4.1–4.4:1, breadcrumb ol, func-card chips) — pre-existing world conditions.
- Rasters: `.impeccable/review/service-desktop.png`, `.impeccable/review/service-mobile.png`.

---

## /kas_keluar — Two-deck cash-out console

Status: shipped 2026-09-26 · brief + verdict + provenance: `.impeccable/surfaces/kas-keluar.md`. Operate mode (finance writing and reconciling cash-out). Scope: `Kas_Keluar::index` view, `kas_keluar/datatables` endpoint, `ModelKasKeluar` read/filter builder.

### Composition
- **Composer deck** `#kkComposer` docked above the ledger deck, folded to a 56px bar carrying the live draft summary (tanggal · n pos · total). The entry form lives in the document — no modal — and Edit reuses the same deck in single-position mode, matching what `update_kas_keluar` actually accepts.
- **Ledger deck**: command bar (320px search as hero, date range, unit, removable filter chips, count, export, column toggle) over a 9+1 column ledger. Bank / nama bank / no. rekening are folded into the Penerima cell's second line instead of standing as three mostly-empty columns.
- Footer total sums the **whole filtered set**, not the visible page, so `Total` never disagrees with the active filter. Colspan is computed from `$canAct` (7 + nominal + 2-or-1) and verified to land under the Jumlah column.
- Mobile: ledger rows become stacked cards below 768px; the composer stays a real form (a 7-field multi-position entry is not a card).

### Decisions worth keeping
- **Bare digits are an exact primary-key lookup.** `1850` → one row; `3` → nothing, where the incumbent's substring match returned 499 rows. `search_mode=text` is the documented escape hatch. Resolution is answered separately from the table (`idHit.inScope`) so "this ID exists but sits outside your date range" is *said* instead of rendered as an empty ledger.
- **The app force-flattens tables in dark mode.** `styles.css` has `[data-bs-theme="dark"] .table > :not(caption) > * > * { color: #7c8fac !important; }` — an app-wide `!important` that collapses every table cell to one blue-grey and, here, made sub-lines *brighter* than primary text. Answered with equal priority at higher specificity, scoped to `.kk-table` only (`[data-bs-theme="dark"] .kk-scope .kk-table tbody td { color: var(--kk-ink) !important; }`). Other tables in the app are untouched. This is the one place this surface argues with the template, and it argues narrowly.
- **Wide tables scroll inside their deck, never the page.** `.kk-tablewrap` and `.kk-composer-body` both carry `contain: paint`. Without it, `.table-responsive` clipped the position table correctly yet the overflow still propagated up and the whole document scrolled sideways on a 390px phone. Verified `docScrollWidth == clientWidth` at 1440/1280/768/390/360 with the composer both folded and open.
- **Folded composer leaves the flow entirely** (`display: none`, plus a one-shot open animation) rather than collapsing to `0fr`; a collapsed-but-present body kept widening the document.
- Ink hierarchy is a 3-step mix off the committed tokens (`--kk-ink` / `-2` / `-3` via `color-mix`), so hierarchy holds in both themes instead of being hand-picked per theme.
- **`--bs-card-bg` is not a global token.** It is declared only inside `.card` (`styles.css:5477`), so any custom surface that reaches for it outside a card resolves to nothing and silently paints the page canvas — which is exactly how these decks once rendered `#f0f5f9` / `#15263a` instead of card stock. Decks here take `var(--bs-body-bg)` at 1.125rem radius and the app's single elevation `0 2px 6px rgba(37,83,185,.1)`, with no hairline border, so masthead and decks match `/kas_masuk` pixel-for-pixel; `--kk-shadow` is the only new token and it carries an existing value.

### Preserved exactly
Route names, field names, multi-position insert, single-position update, unit locking (`[0,1,2,34]`), and jabatan gating. Date defaults to today as the incumbent did.

### Verification
`php -l` clean; `node --check` on the extracted view script; endpoint contracts exercised live (1.738 / `Rp 699.752.128` baseline, 145-row text search, exact-ID hit and miss, out-of-scope ID); browser pass over desktop/mobile/dark with no DataTables dialogs; computed contrast 4.95:1 light and 6.13:1 dark. Detector: `[]`. Rasters: `.impeccable/review/kas-keluar-*.png` (9 files) — geometry/colour verified programmatically; **eyeball sign-off still pending a vision-capable reviewer**, and Iconify glyphs unverified offline.

---

## /asset_berjalan — Month-ruler report deck

Status: shipped earlier (see `.impeccable/surfaces/dashboard-asset-berjalan.md`, seed `b777bde7`). 6 committed metrics preserved; month/unit ruler as hero element; formula notes kept. Rasters: `.impeccable/review/desktop.png`, `.impeccable/review/mobile.png`.
---

## /finance/rekonsiliasi — Day-index + day-detail console

Status: shipped 2026-09-27 · brief + verdict + provenance: `.impeccable/surfaces/dashboard-finance-rekonsiliasi.md`. Operate mode (finance closing out month-ends). Scope: `app/Views/dashboard/finance_rekonsiliasi.php` only — no controller, route, schema, or service change.

### Composition
- **Masthead** (unit · periode) → **status band** (periode + lima count status + Skor KPI + meter) → **console**.
- **Console** is a 42/58 grid at ≥992px: a day index on the left, one detail panel on the right. The index row is a real GET link (`?unit_id&month&status&hari`), so the whole page works without JS; JS only swaps the panel in place and `replaceState`s the URL.
- **Mobile (<992px)**: the single-column grid, the aside hides, and the selected panel is moved into the list right under its own row. Returning to desktop re-parents it to the aside and clears the state.
- Panel per day: state sentence ("di tangan siapa" or why it's blocked) → three component blocks (Cash Masuk / Transfer Masuk / Kas Keluar, each ERP · Aktual · Selisih) → audit trail → input form and, when the viewer may approve, the verify form. Both inline; the day page is gone (2026-09-27).

### Decisions worth keeping
- **No `.table`, same reason as `/kas_keluar`** — the app's `[data-bs-theme="dark"] .table … { color:#7c8fac !important }` flattens every cell. A day index is not a spreadsheet, so it is a list of anchors.
- **The theme's semantic hues are unusable as text.** Blue_Theme resolves `--bs-danger` to `#fb977d` and `--bs-success` to `#4bd08b` — 2.37:1 and 2.14:1 on the white card, and `.rk-day-flag` failed at 12px too. So text tints mix the hue toward `--bs-emphasis-color` (`--rk-bad-ink` / `--rk-good-ink`, `color-mix` 58/42) while **badges keep `bg-danger` etc. untouched** so the status vocabulary does not drift. All 17 probes now pass in both themes.
- **`--bs-card-bg` is card-scoped** (precedent above), so the sticky index head takes `var(--bs-card-bg, var(--bs-body-bg))` — inside the card it resolves, and the fallback still matches the card stock exactly (white / `#111c2d`).
- **Sticky offsets sit at `4.375rem`**, the measured topbar height, so nothing slides under it. Below 992px the topbar wraps (140px at 768 and 360), so the index head goes `static` and the aside is already `static` — sticky head + one long column buys nothing there.
- **Every calendar day is reconciled — see *Semua hari kalender* below for the rule change (2026-09-27).** This section originally muted Sundays and labelled them "Bukan hari kerja". That was wrong twice over: it told finance a day they are required to fill could be skipped, and it shipped alongside a real KPI bug. `countHariKerja()` was Mon–Sat while the numerator counted every day, so filling Sundays could push the score to a capped 100% with weekdays still unfilled (Sept 2026: 19 of 23 weekdays + 4 Sundays = 23/23 = 100%). The calculator now counts all elapsed days (`countHariDilalui()`, detail key `hari_dilalui`), the index gives Sundays no special treatment, and the "di luar KPI" / "Bukan hari kerja" copy is gone.
- **The band can only show the five counts honestly when unfiltered** — the controller filters `$list` before the view ever sees it. With an active status filter the band switches to "Menampilkan *Perlu Revisi* · 2 dari 27 hari · Tampilkan semua", taking the denominator from `rekon_score.detail.hari_dilalui` (unfiltered by construction) instead of counting a filtered list twice.
- **Panel copy describes, it does not authorize.** "Menunggu verifikasi Anda" is display only; `DashboardFinance::canApproveRekon` still decides. The form page was not touched.

### Preserved exactly
GET `unit_id` / `month` / `status` (plus the optional `hari`), `RekonDailyCalculator` labels and badge classes, `Skor KPI Rekonsiliasi`, `bg-danger` on returned drafts, thousands-separated Rupiah (`700.000`), revision notes, and the Sunday-inclusive day list.

### Verification
`php -l` clean; `app/Scripts/recon_http_smoke.php` 64 PASS / FAIL 0, including all nine list-contract assertions and the "every calendar day" assertions. `rekon_daily_test.php` 230 PASS / FAIL 1 (A5, pre-existing data seed). Browser pass at 1440 / 1280 / 768 / 390 / 360 in light plus 1440 dark: `docScrollWidth == clientWidth` everywhere, no element-level overflow, no sub-32px row targets, panel swap keeps exactly one visible panel and sets `aria-current`, the panel moves under its row on mobile and returns on resize, and all contrast probes pass. Renders of the `status`-filtered and empty-month variants are also checked (7 and 28 days respectively, one panel open each). Detector: 2 findings, both the `border-left: 3px` accent pattern on the dashboard score cards — the colour is now a token and the state is also carried by `opacity` and the card's own value, so it is not colour-only. Rasters: `.impeccable/review/rekonsiliasi-*.png` (9 after + 3 before) — geometry and colour verified programmatically; **eyeball sign-off still pending a vision-capable reviewer**, and the template's own offline JS keeps throwing `Element not found` (pre-existing, also present in the before captures).

## /finance/rekon/form — Day-entry deck (act on one day) — REMOVED 2026-09-27

> **Dihapus 2026-09-27.** Permintaan: input dan verifikasi jangan pindah
> halaman; `finance/rekonsiliasi` saja yang tersisa. Yang dihapus:
> route `finance/rekon/form`, method `rekonForm()` + `rekonFormData()`, view
> `app/Views/dashboard/finance_rekon_form.php`, dan surface brief
> `.impeccable/surfaces/dashboard-finance-rekon-form.md`. Semua keputusan
> di bawah tetap berlaku kecuali yang ditandai usang — terutama partial
> input bersama, token bersama, keputusan `disabled`, dan guard
> Need Revision, semuanya sekarang dipakai dari daftar.
>
> Sisa seksi ini dipertahankan sebagai catatan keputusan, bukan deskripsi
> halaman yang masih ada.

Status: shipped 2026-09-27 · brief + verdict: `.impeccable/surfaces/dashboard-finance-rekon-form.md` (keduanya sudah dihapus; lihat catatan di atas). Operate mode (finance entering one day's actuals). Scope: `app/Views/dashboard/finance_rekon_form.php` rewritten + new shared partial `app/Views/inc/rek_tokens.php` — no controller, route, schema, or approval-logic change.

### Composition
- ~~**Masthead** (breadcrumb · unit · hari ini) → **day navigator** (large date + month label, `Sebelumnya` / `Berikutnya`, GET date-jump with `max` = today — no working-day label, see below) → **status rail** (process badge + note line) → **deck** (5-column entry table) → **manager panel** (only when a revision note exists) → **sticky action bar**.
- ~~**The navigator is the redesign's real answer to the split.**~~ USANG: tidak ada lagi halaman terpisah yang kehilangan konteks. Navigasi hari sekarang inherent di daftar — daftar hari itu sendiri, dan `hari` di query string memilih panel yang terbuka.
- ~~**Mobile (<768px)**: the table becomes three stacked cards…~~ USANG: deck 5 kolom sudah tidak ada; daftar memakai `.rk-comp` stacked cards dan tidak pernah memakai `.table` (lihat trap dark-mode di bawah).
- ~~**One action bar, one endpoint.**~~ USANG (bar sudah tidak ada; deck-nya inline). Catatan originally: the bar now carries `Simpan & Kirim` (POST `finance/rekon/save`) and `Kembali ke daftar`. The second click was removed, so there is no longer a `form="rk-submit"` sibling; see *Simpan & Kirim — satu klik* below.

### Decisions worth keeping
- **PHP 7.4 is the real target, not an aspiration.** `composer.json` allows `^7.4 || ^8.0`, so the list view's `match` became a `$kunciStatus` lookup array. The form uses no `match`, no named arguments, no `str_contains`.
- **The input deck itself became a shared partial** (`app/Views/inc/rek_input_fields.php` + `app/Views/inc/rek_input_js.php`) when the list gained editable panels. The selisih figure is the number finance treats as true; two copies of that arithmetic would drift, and the drifted one would be the one nobody owns. Field *names* stay unprefixed on purpose — `actual_cash_masuk` is the server contract.
- **Shared tokens went into `app/Views/inc/rek_tokens.php`**, not into the list view. Two pages now ship one `--rk-*` scale, the masthead, the status chips, the note line, and the breadcrumb padding; editing the palette in one place moves both. The list view regressed zero on the browser pass after the extraction.
- **The theme's dark mode flattens every table cell** (`[data-bs-theme="dark"] .table … { color:#7c8fac !important }`, 4.33:1 — the same trap `/kas_keluar` and the list both sidestepped by not using `.table`). This form genuinely *is* a form grid, so instead of abandoning it, the dark rules are re-asserted at higher specificity (`.rk-scope .rk-money td`, `.rk-money thead th`) without `!important` — 6.85:1, and `thead` rows take the `--rk-…-tint` so the head still reads as a head.
- **`btn-primary` fails AA in this template**: white on `#0085db` is 3.9:1 at 15px/500. The blue is darkened 12% toward black → 4.9:1, and hover/active follow. It must be `color-mix(…, #000)` and **not** `--bs-emphasis-color`, which inverts between themes and would darken the dark-theme button into illegibility. The selector is `[data-bs-theme] .rk-scope .rk-actions .btn.btn-primary` (0,5,0) to outrank the template's `[data-bs-theme][data-color-theme]:root .btn-primary` (0,4,0) while naming no theme, so Aqua/Purple/Green/Orange all keep the fix. Same reason the warn chip and the input text needed their own tints: Blue_Theme's semantic hues are unusable as text (2.1–2.4:1).
- **An explicit `0` is data, not absence.** `parseNominalRekon()` maps `""` → `null` (belum diisi) and `"0"` → `0` (sah). The boot normaliser used to collapse `"0"` to `""`, which made a genuinely complete day render as "Belum diperiksa" and, on re-save, rewrite a good figure as `NULL`. Now `"0"` survives boot, typing, and paste; `null` already arrives as `value=""` and needs no compensation. Regression-tested.
- **`disabled` is an attribute, not a class name.** A verified day used to render `class="… text-end disabled"` — a class that changes nothing, so the field stayed typable while looking locked (the textarea beside it was correctly disabled, which is what kept this hidden). The attribute is now real, asserted by `H13g`.
- **No `readonly` anywhere.** The smoke test forbids it, and it is the right call: a disabled field with no focus is invisible to keyboard and screen-reader users, so the locked state is stated in prose in the status rail instead.
- ~~**"Berikutnya" is inert on the last day, not a dead link.**~~ USANG: `rekonFormData()` sudah tidak ada. Kunci tanggal masa depan tetap ditegakkan di `rekonSave()`.
- **Need Revision is guarded client-side, not just server-side.** Sending without a reason is intercepted with an inline error, because the manager otherwise receives an empty instruction and the day stalls.

### Preserved exactly
Endpoint `finance/rekon/save` · `finance/rekon/submit` · `finance/rekon/approve`; hidden `unit_id` + `tanggal` on every form; `RekonDailyCalculator` status vocabulary and badge classes; the smoke test's literal strings (`Catatan manager (perlu revisi)`, `tidak dapat diubah`) and its forbidden substrings; thousands-separated Rupiah; future-date clamping. **Berubah 2026-09-27:** `finance/rekon/form?unit_id=…` contract ikut hilang, begitu `Simpan Draft` dan `rupiah-rekon` (deck 5 kolom sudah tidak ada), dan 5 `<th>` + 5 `<td>` per row.

### Verification
`php74 -l` clean on all three views. `recon_http_smoke.php` 60 PASS / FAIL 0, `recon_flow_smoke.php` 29 PASS / FAIL 0. Browser pass at 1440 / 1280 / 768 / 390 / 360 in light plus 1440 dark across 7 states (draft · need_revision · submitted · verified · approver · hari-ini · aktual-0): **209 PASS / FAIL 0** — no overflow, no sub-32px targets, all contrast probes pass in both themes, date nav links point at the form, the last day's "Berikutnya" is inert, every breadcrumb label sits on one baseline per wrapped line, and `0` survives boot/typing/paste. Detector: `[]`. Rasters: `.impeccable/review/rekon-form-*.png` (8) — **dihapus bersama permukaan ini**, tidak lagi relevan; geometry and colour verified programmatically; **eyeball sign-off still pending a vision-capable reviewer**, and the template's own offline JS keeps throwing `Element not found` (pre-existing).

## Kunci angka saat sudah dikirim (2026-09-27)

Laporan: begitu satu hari sudah di-input, manager masih melihat tombol
**Simpan & Kirim**. Kalaumanager bisa mengubah, berarti manager bukan hanya
menyetujui — dia bisa menulis ulang angka yang diminta dia setujui,
termasuk setelah membuka layar verifikasi. Itu membatalkan nilai persetujuan.

Penyebabnya bukan UI. `isLocked()` hanya `true` untuk `VERIFIED`, jadi
`rekonSave()` tidak pernah menolak apa pun selama belum verified, dan
`financeInputRoles = [0, 1, 2, 34]` memuat jabatan 34 & 1 yang juga ada di
`financeApproveRoles`. Satu orang bisa jadi pengirim sekaligus pemverifikasi,
jadi aturan **peran** tidak bisa membedakan kasus itu — harus aturan *state*.

Aturannya (`RekonDailyCalculator::bolehUbahAngka()`):

| status | boleh diubah? |
| --- | --- |
| belum ada record | ya, pengisian pertama |
| `DRAFT` | ya, memang belum jadi pernyataan |
| `NEED_REVISION` | ya, catatan manager justru meminta ini |
| `SUBMITTED` | **hanya `submitted_by`**, dan hanya sebelum ada yang memverifikasi |
| `VERIFIED` | tidak, untuk siapa pun |

Kasus "sudah terkirim tapi finance menemukan salah ketik sebelum dicek"
diselesaikan lewat jalur resmi: `NEED_REVISION` membuka kembali hak ubah.
Tidak ada edit liar di atas angka yang sedang dinilai.

Ditegakkan di **dua** tempat, karena mematikan tombol tidak menutup route:

- `DashboardFinance::rekonSave()` menolak POST dengan flash
  `kunciAlasan()` — ini yang benar-benar menutup lubang.
- Kedua tampilan (daftar + form) memakai hasil kalkulator yang sama untuk
  mematikan input dan menyembunyikan tombol, lalu menampilkan alasannya.

Tidak ada peran baru dan tidak ada kolom baru: aturan ini sepenuhnya
turunan dari `status_proses` + `submitted_by` yang sudah ada.

Regresi: `recon_flow_smoke` 10c–10f (tabel nilai, POST manager ditolak
serta angkanya tidak berubah, pengirim tetap boleh, `NEED_REVISION` membuka
kembali) dan `recon_http_smoke` (tombol hilang di panel terkunci untuk akun
kedua, `boleh_ubah` benar di kedua sisi, panel ter-disable).

## Simpan & Kirim — satu klik (2026-09-27)

Alur lama butuh dua klik: "Simpan Draft" (DRAFT), lalu "Kirim untuk
Verifikasi" (SUBMITTED). Untuk operator yang mengisi satu bulan itu
puluhan menit, klik kedua itu friksi tanpa nilai — itu satu pekerjaan.

Sekarang `DashboardFinance::rekonSave()` menulis **satu** status:

- ketiga `actual_*` terisi → `SUBMITTED` + `submitted_by` + `submitted_at`
  langsung terisi pada write yang sama. Manager / Admin Root (jabatan 34 & 1)
  bisa langsung approve atau minta revisi.
- belum lengkap → tetap `DRAFT`, dengan flash message yang menjelaskan apa
  yang perlu dilengkapi. Pekerjaan setengah jadi tidak terkirim, dan tidak
  hilang.

Kelengkapan memakai `RekonDailyCalculator::isLengkap()` — definisi yang sama
dengan numerator KPI, dirakit dari nilai yang sudah diparse (tidak perlu
baca ulang dari DB), jadi tidak ada aturan "lengkap" yang dobel.

Tombol "Kirim untuk Verifikasi" dan `<form id="rk-submit">` dihapus dari
form; endpoint `finance/rekon/submit` **tidak** dihapus dan masih dijaga
`siapSubmit()` — jalur itu tetap ada untuk pemanggilan di luar UI, dan
regresinya diuji (`recon_flow_smoke` 10b: submit eksplisit ditolak saat data
tidak lengkap).

"Terkirim" di sini berarti **masuk antrean approval**, bukan push notifikasi.
Aplikasi ini belum punya infrastruktur notifikasi (tanpa tabel notifikasi,
tanpa mailer untuk modul ini), jadi tidak ada email/badge yang diklaim.

## Aturan rekonsiliasi harian — semua hari kalender (perubahan aturan 2026-09-27)

Status: ratified by the user 2026-09-27 · supersedes the Sunday exclusions recorded in both earlier sections.

**The rule:** finance wajib mengisi rekonsiliasi **setiap hari kalender** — Minggu dan hari libur termasuk — dan isian yang tertunda boleh disusulkan di hari-hari berikutnya. The list already supported catch-up (every past day is listed and links to its form); what was missing was the *encouragement*, and the KPI was actively hiding the backlog.

### What the old rule got wrong
`RekonDailyCalculator::countHariKerja()` counted Monday–Saturday for the denominator while `countLengkapVerifiedInRange()` counted *every* complete+verified day for the numerator. With `min(…, 100)` on the result, filling Sundays could manufacture a perfect score over unfilled weekdays:

| Sept 2026 (27 days elapsed: 23 Mon–Sat + 4 Sundays) | numerator | score |
|---|---|---|
| 23 weekdays verified, 0 Sundays | 23 | 100% |
| **19 weekdays + 4 Sundays verified** | 23 | **100%** |
| 23 weekdays + 4 Sundays verified | 27 | 100% (capped) |

So the UI copy "Minggu bukan hari kerja … isian hari ini tidak menambah atau mengurangi skor" was false on both counts: a Sunday *does* move the score, and it moves it by masking a hole.

### The change
- `countHariKerja()` → **`countHariDilalui()`** (RekonDailyCalculator.php:489), counting every elapsed calendar day. Detail key `hari_kerja` → **`hari_dilalui`**; consumers updated (`finance_rekonsiliasi.php`, `dashboard_finance.php`, `rekon_daily_test.php`).
- Numerator and denominator now cover the same range, so the score cannot be inflated. `min(…, 100)` stays as a safety net only — the `uq_rekon_daily_unit_tanggal` unique key makes numerator > denominator impossible.
- **Copy removed, not reworded.** The "Bukan hari kerja" day-index label, the "Minggu · di luar KPI" flag, the panel paragraph, and the `is-nonaktif` muting are all gone from `finance_rekonsiliasi.php`; the `.rk-daynav-sub` label on the form is now the month (`September 2026`) — orientation, never permission to skip. The legend states the opposite of what it used to.
- `recon_daily_test.php` F1/F5 were reworded to the new rule, and **F8b–F8e are new regressions**: Sunday days are in the denominator, filling a Sunday raises the score, an unfilled Sunday drops it below 100, and the numerator can never exceed the denominator.

## Panel daftar yang bisa diisi (2026-09-27)

Panel per-hari di `/finance/rekonsiliasi` tidak lagi cuma read-only. Bagi
operator yang sedang mengejar hari yang tertinggal, satu bulan berarti buka
form, isi, submit, kembali, buka lagi — dan form itu hanya menampilkan satu
hari, jadi konteks bulan hilang setiap kali berpindah hari. Modal
digunakan sebagai gantinya, lalu ditolak: URL-nya menjadi dalam dan tidak
bisa dibagikan. Mengisi inline di panel yang sama menjawabnya tanpa
mengorbankan deep-link.

### Yang berubah
- `finance_rekonsiliasi.php`: tiap `<article class="rk-panel">` memuat
  `<form>` ke `finance/rekon/save` berisi partial input yang sama dengan
  panelnya sendiri, plus satu tombol **Simpan & Kirim**. Prefix id per tanggal
  (`p20260904-`) menjaga 27 panel tidak saling menabrak `msg_` /
  `selisih_` / `status_`. Role tanpa izin input tetap dapat ringkasan
  baca-saja `.rk-comps` — 90 input yang mati bukan cara menyampaikan
  read-only.
- `DashboardFinance::rekonsiliasiData()` menempelkan angka ERP ke tiap item
  dari `RekonDailyCalculator::erpValuesRange()` (3 query aggregate sebulan).
  `erpValues()` per hari akan jadi ~6 query × 27 hari. Angka yang
  ditampilkan adalah angka terkini, bukan snapshot `erp_*` di record —
  sama seperti form, dan sama seperti yang jadi dasar hitungan selisih saat
  server menyimpan.
- `rekonSave()` redirect ke `?hari=<besok>` bila besok masih di bulan yang
  sama dan sudah lewat; kalau tidak (akhir bulan, atau tanggal terakhir),
  tetap di tanggal ini supaya hasil simpan langsung terlihat.
- Aksi lama `Isi Rekonsiliasi` jadi `Form lengkap`; `Verifikasi` hanya
  muncul saat status SUBMITTED. Persetujuan juga inline di panel yang sama.

### Keputusan yang perlu dijaga
- **Satu implementasi, dua permukaan.** Markup + JS input dipindah ke
  `app/Views/inc/rek_input_fields.php` dan `inc/rek_input_js.php`.
  `rekon_js_test.js` mengekstrak script dari partial itu, jadi yang diuji
  tetap kode yang benar-benar dirender.
- **State per unit, bukan global.** `inputs` / `groups` / `prefix` berada
  di dalam `initRekonInput(root, prefix)`. Kalau dibiarkan di scope modul,
  unit terakhir yang di-init yang menang dan setiap ketikan berikutnya
  menulis ke panel tanggal yang salah. `rekon_js_test.js` punya 9 assertion
  dua unit berdampingan untuk menjaganya.
- **Field POST tidak pernah diawakan.** `actual_cash_masuk` adalah kontrak
  `parseNominalRekon()`; hanya id DOM yang dapat prefix.
- **Partial `.php` yang hanya berisi `<script>` tetap butuh `<?php`.**
  Tanpa itu docblock bocor jadi teks halaman dan script-nya tidak jalan —
  sempat lolos karena harness membaca file render yang basi.

### Verification
`rekon_daily_test.php` 230 PASS / FAIL 1 (A5) · `recon_flow_smoke.php`
36 / 0 · `recon_http_smoke.php` 64 / 0 · `rekon_js_test.js` 53 / 0 · browser
form 212 / 0. Di render 27 hari: 332 id dengan 331 unik (duplikat tunggal
`sidebarnav` milik dua sidebar template), 81 input, 27 tombol, 27 prefix
unik, tanpa page error. Ketik `1.234.567` pada hari dengan ERP `800.000`
memunculkan `Rp 434.567` di panel itu saja; panel lain tidak bergerak.

---

## /penilaian_kinerja — Report card → payslip

Status: shipped 2026-10-10 · brief + direction contract: `.impeccable/surfaces/app-views-penilaian-penilaian-kinerja-php.md`, seed `614d898d`. Operate mode (supervisor/HR/finance reading one employee's month). Scope: `app/Views/penilaian/penilaian_kinerja.php` + new scoped `app/Views/penilaian/_penilaian_kinerja_theme.php` — no controller, service, route, field, role, or calculation change.

### Composition
- Page header → filter (bulan · tahun · karyawan via Select2, GET auto-submit) → **report-card hero** (`.pk-report`, a 50/50 grid at ≥992px: left Skor Kinerja + quality band + weighted-contribution meter, right Take Home Pay + Cetak Slip Gaji) → Omset cabang strip → KPI ledger → attendance ledger → income composition resolving to Take Home Pay.
- Mobile (<992px) stacks the hero halves; the omset grid steps 4 → 2 → 1 columns; both ledgers scroll inside a capped `.pk-scroll` with a sticky `thead`.

### Decisions worth keeping
- **The template's `--bs-*-text-emphasis` tokens are unusable in light mode — they ship raw Sass (`shade-color(#fb977d, 60%)`).** `var(--bs-danger-text-emphasis, <fallback>)` then invalidates at computed-value time and *inherits* the ink colour, so every status word rendered grey in light theme (dark resolves real hex, which is why it looked fine there). The surface defines resolved status tints itself (`--pk-ok #146c43`, `--pk-warn #7a5c04`, `--pk-bad #b02a37`), mirroring the precedent recorded for `/finance/rekonsiliasi` above. Light warning needed `#7a5c04` (5.84:1 on the tint); `#997404` is only 4.04:1.
- **`--pk-ink` is set darker than `--pk-muted`** (#55606d vs #5b6672) so the hierarchy reads correctly and the filter selects clear AA — `--bs-body-color` (#707a82) is 4.42:1 on the filter ground `#e7ecf0`. Filter controls take `--pk-ink-strong` (#4d5866 light / `#cfd8e3` dark) for the same reason.
- **Bands and badges keep the template's semantic `--bs-success/-warning/-danger` fills untouched** (badge fills pass at 8.15 / 8.37 / 6.9:1) so the status vocabulary does not drift.
- **No eyebrow.** A "Periode & Karyawan" kicker over the filter was deleted to the craft floor's ban; the three field labels carry the card.
- **Omset rows are separated with `nth-child`, not a border on the first cell** — `:first-child` left a stray hairline on the second row's first column, and the header already draws the top rule, so the grid adds no `border-top` (it was doubling the header hairline).

### Preserved exactly
All data and logic: bulan/tahun/karyawan GET + Select2, `detail_kpi` (weight, target → realisasi, HO/non-HO, per-cabang sub-ledger with reached/shortfall, score badge), `detail_absen`, omset per cabang + global, salary composition (Gaji Pokok, Tunj. Kinerja, Tunj. Absen, Penempatan, Insentif) → Take Home Pay, the "not including commission/incentive" note, the official slip link, and both empty states. Server-side scores untouched; the attendance figure is display-only weighted.

### Verification
Detector `[]` (clean). Programmatic render audit at 1440 / 1280 / 768 / 390 light + 1440 dark: `documentElement.scrollWidth == innerWidth` everywhere, no element-level overflow, hero halves side-by-side ≥992px and stacked below, meter `scaleX = skor/100`, 92/92 icons resolved, `tabular-nums` on figures, and every sampled contrast probe ≥4.5:1 (filter selects 6.08 light / 9.91 dark after the fix; status text 5.74–8.57). Print target `/penilaian/slip_gaji/49?bulan=10&tahun=2026` → HTTP 200. Pre-existing, not repaired: the template's offline JS throws `Element not found` ×4 (apexcharts, reproduced identically on untouched `/stok_opname`); the surface brief carries a duplicated YAML frontmatter block. Rasters: `.impeccable/review/desktop.png`, `mobile.png`, `desktop-dark.png` — geometry and colour verified programmatically; **eyeball sign-off still pending a vision-capable reviewer**.
