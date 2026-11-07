---
version: 1
slug: "absen"
primary_target: "absen"
related_targets: ["pengajuan_izin","profil"]
---

# Surface brief — Presensi Mobile (/absen, /pengajuan_izin, /profil)

## Scope & mode

- Scope: the three standalone mobile pages `/absen` (`absen/mobile.php`), `/pengajuan_izin` (`izin/pengajuan.php`), `/profil` (`profil/mobile.php`), the shared theme `inc/_presensi_mobile_theme.php`, the per-page `/absen` theme `absen/_theme.php`, the bottom dock `inc/_mobile_nav.php`, and the install dialog `inc/_pwa_install.php`.
- Visitor mode: Operate — a store employee punches in/out and files leave from a phone, often standing, sometimes outdoors.
- Audience: kasir, SPV, and branch staff on Android/iOS; Indonesian; one-handed use; light and dark.
- Job: see the current shift and the next punch, punch with photo/GPS, file or review leave, read identity.
- Proof/content: real shift/roster, punch history, radius/photo requirements, leave status and supervisor reply, employee identity.
- Constraints: routes, field names, CSRF, server-side roster/radius/photo validation, online-only flow, PWA install, and unit scope stay untouched. No new dependency or web font. Mobile-first at 390px, safe-area aware, ≥44px targets, text ≥4.5:1, works without `backdrop-filter`.

## Direction contract

THESIS: Presensi is a personal daily ritual, not an ERP form: one luminous glass sheet makes the active shift and the next punch the unmistakable center, and the rest of the shift recedes into a quiet rail. Refuses the incumbent white admin card stack and corporate-blue chrome.

OWN-WORLD: Aurora — deep navy `#070d18` canvas lit by teal/indigo/sky ambient washes; one translucent glass surface per page with a white rim, inner highlight, deep soft shadow, and 22–24px radius; mint `#5eead4` → sky `#38bdf8` gradient CTA with near-black ink; system sans, compact live clock, uppercase micro-labels; floating pill dock. Light mirrors on `#eef5fb` with white glass and `#0f766e` / `#0369a1` accents.

STORY: The employee opens the app and instantly knows which shift is active, its hours, what time it is, which step comes next, and that location/photo are checked on the server. One tap punches; the history below confirms what was recorded. Izin and Profil keep the same one-sheet rhythm, so the app feels like one product rather than three forms.

FIRST VIEWPORT: Header with a 38px avatar tile, greeting, name, and unit. One glass card: shift name with a large time range as the focus, a compact live clock with mint seconds and WIB at the top right, status line, four-step rail (done mint, now sky, todo muted), and a location inset. A full-width gradient CTA “Absen istirahat” sits below, with a micro-hint and a compact history list. The floating dock holds Absen · Izin · Profil.

FORM: User-pinned Aurora, chosen by the user from three presented directions (A Aurora / B Kertas / C Signal) in the brainstorming round; no concept roll — the user's pick outranks it. Seed: user-pinned-aurora.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
