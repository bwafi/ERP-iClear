<?php
/**
 * Tema halaman mobile presensi: /absen, /jadwal, /pengajuan_izin, dan /profil.
 *
 * Satu bahasa visual untuk empat halaman: kanvas tenang, sheet rata dengan
 * garis rambut, satu aksen (primary #0085db), angka tabular, tanpa gradient
 * dan tanpa shadow bertumpuk. Tema mengikuti `localStorage['app-theme']`
 * yang dipakai shell ERP, dengan fallback preferensi sistem.
 *
 * Kontras teks kecil dijaga >= 4.5:1 di terang maupun gelap:
 *   --mob-muted #5b6672 di putih (5.85:1), #93a3b8 di #16212f (6.4:1)
 *   tombol primary #0074ba dengan teks putih (4.99:1)
 */
?>
<link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">
<meta name="theme-color" content="#eef5fb" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#070d18" media="(prefers-color-scheme: dark)">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Absen iClear">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="apple-touch-icon" href="<?= base_url('template/assets/pwa/apple-touch-icon.png') ?>">
<script>
(function () {
    var saved = null;
    try { saved = localStorage.getItem('app-theme'); } catch (e) {}
    if (saved !== 'dark' && saved !== 'light') {
        saved = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    document.documentElement.setAttribute('data-bs-theme', saved);
})();
</script>
<style>
    :root {
        color-scheme: light;
        --mob-canvas: #eef2f7;
        --mob-surface: #ffffff;
        --mob-ink: #16202c;
        --mob-muted: #5b6672;
        --mob-rule: #e1e7ef;
        --mob-rule-strong: #cbd6e2;
        --mob-accent-ink: #0069a8;
        --mob-accent-solid: #0074ba;
        --mob-accent-hover: #005f99;
        --mob-accent-wash: rgba(0, 116, 186, .07);
        --mob-ok: #0f7a46;
        --mob-warn: #8a5300;
        --mob-bad: #b42318;

        /* Kaca: permukaan tembus cahaya di atas ambient kanvas. */
        --mob-glass: rgba(255, 255, 255, .52);
        --mob-glass-strong: rgba(255, 255, 255, .74);
        --mob-glass-border: rgba(255, 255, 255, .62);
        --mob-glass-edge: rgba(255, 255, 255, .88);
        --mob-glass-shadow: 0 22px 44px -30px rgba(15, 35, 60, .55);
        --mob-field: rgba(255, 255, 255, .58);
        --mob-field-border: rgba(255, 255, 255, .68);
        --mob-field-edge: rgba(255, 255, 255, .7);
        --mob-glow-a: rgba(0, 133, 219, .17);
        --mob-glow-b: rgba(20, 184, 166, .12);
    }

    html[data-bs-theme="dark"] {
        color-scheme: dark;
        --mob-canvas: #0e1726;
        --mob-surface: #16212f;
        --mob-ink: #e9eef5;
        --mob-muted: #93a3b8;
        --mob-rule: rgba(255, 255, 255, .10);
        --mob-rule-strong: rgba(255, 255, 255, .20);
        --mob-accent-ink: #7cc4f0;
        --mob-accent-solid: #0074ba;
        --mob-accent-hover: #0085db;
        --mob-accent-wash: rgba(124, 196, 240, .10);
        --mob-ok: #6fdd9d;
        --mob-warn: #f0b45a;
        --mob-bad: #ff9aa2;

        --mob-glass: rgba(24, 36, 52, .55);
        --mob-glass-strong: rgba(24, 36, 52, .74);
        --mob-glass-border: rgba(255, 255, 255, .11);
        --mob-glass-edge: rgba(255, 255, 255, .14);
        --mob-glass-shadow: 0 22px 44px -30px rgba(0, 0, 0, .85);
        --mob-field: rgba(255, 255, 255, .055);
        --mob-field-border: rgba(255, 255, 255, .13);
        --mob-field-edge: rgba(255, 255, 255, .10);
        --mob-glow-a: rgba(56, 148, 220, .18);
        --mob-glow-b: rgba(45, 212, 191, .09);
    }

    html {
        margin: 0;
        background: var(--mob-canvas);
    }

    body {
        margin: 0;
        background: transparent;
        color: var(--mob-ink);
        font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        -webkit-font-smoothing: antialiased;
        text-rendering: optimizeLegibility;
    }

    /* Ambient tetap: cahaya yang dibiaskan permukaan kaca. */
    body::before {
        content: "";
        position: fixed;
        inset: 0;
        z-index: -1;
        pointer-events: none;
        background:
            radial-gradient(40rem 30rem at 12% -10%, var(--mob-glow-a), transparent 70%),
            radial-gradient(36rem 28rem at 105% 6%, var(--mob-glow-b), transparent 72%),
            radial-gradient(50rem 34rem at 50% 115%, var(--mob-glow-a), transparent 72%);
    }

    ::selection {
        background: var(--mob-accent-solid);
        color: #fff;
    }

    :focus-visible {
        outline: 2px solid var(--mob-accent-solid);
        outline-offset: 2px;
    }

    .mob {
        box-sizing: border-box;
        min-height: 100vh;
        min-height: 100dvh;
        max-width: 480px;
        margin: 0 auto;
        padding: calc(20px + env(safe-area-inset-top)) max(16px, env(safe-area-inset-right)) calc(96px + env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .mob *,
    .mob *::before,
    .mob *::after {
        box-sizing: border-box;
    }

    /* ===== Kepala halaman ===== */
    .mob .head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 4px;
    }

    .mob .head-main {
        min-width: 0;
    }

    .mob h1 {
        margin: 0;
        font-size: 1.0625rem;
        font-weight: 650;
        letter-spacing: -.01em;
        overflow-wrap: anywhere;
    }

    .mob .meta {
        margin: 3px 0 0;
        font-size: .8125rem;
        color: var(--mob-muted);
        overflow-wrap: anywhere;
    }

    .mob .meta .sep {
        padding: 0 2px;
    }

    .mob .ghost {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 40px;
        padding: 0 12px;
        border: 1px solid var(--mob-glass-border);
        border-radius: 12px;
        background: var(--mob-glass-strong);
        box-shadow: inset 0 1px 0 var(--mob-glass-edge);
        color: var(--mob-accent-ink);
        font-size: .8125rem;
        font-weight: 600;
        text-decoration: none;
        transition: border-color .15s ease, background-color .15s ease;
    }

    .mob .ghost:hover {
        border-color: var(--mob-accent-solid);
        background: var(--mob-accent-wash);
    }

    .mob .ghost svg {
        width: 16px;
        height: 16px;
        flex: 0 0 auto;
    }

    /* ===== Sheet ===== */
    .mob .sheet {
        position: relative;
        padding: 18px 16px;
        border: 1px solid var(--mob-glass-border);
        border-radius: 20px;
        background: var(--mob-glass);
        box-shadow: inset 0 1px 0 var(--mob-glass-edge), var(--mob-glass-shadow);
        -webkit-backdrop-filter: blur(20px) saturate(170%);
        backdrop-filter: blur(20px) saturate(170%);
    }

    .mob .state-title {
        margin: 0;
        font-size: 1.375rem;
        font-weight: 650;
        letter-spacing: -.02em;
    }

    .mob .state-text {
        margin: 6px 0 0;
        color: var(--mob-muted);
        font-size: .875rem;
    }

    .mob .state-text strong {
        color: var(--mob-ink);
        font-variant-numeric: tabular-nums;
    }

    /* ===== Blok shift ===== */
    .mob .shift + .shift {
        margin-top: 18px;
        padding-top: 18px;
        border-top: 1px solid var(--mob-rule);
    }

    .mob .shift-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .mob .shift-name {
        margin: 0;
        font-size: .9375rem;
        font-weight: 650;
    }

    .mob .shift-time {
        margin: 2px 0 0;
        font-size: 1.5rem;
        font-weight: 600;
        letter-spacing: -.02em;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .mob .shift-time .dash {
        padding: 0 4px;
        color: var(--mob-muted);
        font-weight: 400;
    }

    .mob .shift-state {
        flex: 0 0 auto;
        margin: 3px 0 0;
        font-size: .75rem;
        font-weight: 600;
        text-align: right;
        color: var(--mob-muted);
        font-variant-numeric: tabular-nums;
    }

    .mob .shift-state.is-ok {
        color: var(--mob-ok);
    }

    .mob .shift-state.is-warn {
        color: var(--mob-warn);
    }

    .mob .shift-state.is-bad {
        color: var(--mob-bad);
    }

    /* ===== Slot foto ===== */
    .mob .photo {
        margin-top: 14px;
    }

    .mob .photo input {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
        white-space: nowrap;
        border: 0;
    }

    .mob .photo-label {
        display: flex;
        align-items: center;
        gap: 12px;
        min-height: 64px;
        padding: 12px 14px;
        border: 1px dashed var(--mob-rule-strong);
        border-radius: 16px;
        background: var(--mob-field);
        cursor: pointer;
        transition: border-color .15s ease, background-color .15s ease;
    }

    .mob .photo-label:hover {
        border-color: var(--mob-accent-solid);
        background: var(--mob-accent-wash);
    }

    .mob .photo input:focus-visible + .photo-label {
        outline: 2px solid var(--mob-accent-solid);
        outline-offset: 2px;
    }

    .mob .photo-icon {
        width: 22px;
        height: 22px;
        flex: 0 0 auto;
        color: var(--mob-accent-ink);
    }

    .mob .photo-preview {
        display: none;
        width: 44px;
        height: 44px;
        flex: 0 0 auto;
        border: 1px solid var(--mob-rule);
        border-radius: 8px;
        background-color: var(--mob-accent-wash);
        background-position: center;
        background-size: cover;
    }

    .mob .photo-text {
        display: grid;
        gap: 1px;
        min-width: 0;
    }

    .mob .photo-title {
        font-size: .875rem;
        font-weight: 600;
    }

    .mob .photo-hint {
        font-size: .75rem;
        color: var(--mob-muted);
    }

    .mob .photo-label.has-photo {
        border-style: solid;
        border-color: var(--mob-accent-solid);
        background: var(--mob-accent-wash);
    }

    .mob .photo-label.has-photo .photo-icon {
        display: none;
    }

    .mob .photo-label.has-photo .photo-preview {
        display: block;
    }

    .mob .photo-label.has-photo .photo-hint {
        color: var(--mob-accent-ink);
    }

    /* ===== Aksi ===== */
    .mob .btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        min-height: 48px;
        margin-top: 12px;
        padding: 0 18px;
        border: 0;
        border-radius: 14px;
        background: linear-gradient(180deg, #0a86d2 0%, #0074ba 100%);
        color: #fff;
        font: inherit;
        font-size: .9375rem;
        font-weight: 650;
        letter-spacing: .01em;
        cursor: pointer;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .3), 0 12px 24px -16px rgba(0, 95, 153, .85);
        transition: background-color .15s ease;
    }

    .mob .btn:hover {
        background: linear-gradient(180deg, #0f93e3 0%, #0080cb 100%);
    }

    .mob .btn:active {
        transform: translateY(1px);
    }

    .mob .btn:disabled {
        opacity: .55;
        cursor: progress;
    }

    .mob .msg {
        min-height: 1.25rem;
        margin: 8px 0 0;
        font-size: .8125rem;
        color: var(--mob-muted);
    }

    .mob .msg.is-err {
        color: var(--mob-bad);
    }

    .mob .msg.is-ok {
        color: var(--mob-ok);
    }

    .mob .links {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-top: 12px;
    }

    .mob .link {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        padding: 0 10px;
        border-radius: 8px;
        color: var(--mob-accent-ink);
        font-size: .8125rem;
        font-weight: 600;
        text-decoration: underline;
        text-decoration-color: transparent;
        text-underline-offset: 3px;
        transition: background-color .15s ease, text-decoration-color .15s ease;
    }

    .mob .link:hover {
        background: var(--mob-accent-wash);
        text-decoration-color: currentColor;
    }

    /* ===== Form ===== */
    .mob .field {
        margin-top: 16px;
    }

    .mob .field:first-child {
        margin-top: 0;
    }

    .mob .label {
        display: block;
        margin-bottom: 6px;
        font-size: .8125rem;
        font-weight: 600;
    }

    .mob .label .opt {
        color: var(--mob-muted);
        font-weight: 400;
    }

    .mob .input {
        width: 100%;
        min-height: 46px;
        padding: 11px 12px;
        border: 1px solid var(--mob-field-border);
        border-radius: 14px;
        background: var(--mob-field);
        box-shadow: inset 0 1px 0 var(--mob-field-edge);
        color: var(--mob-ink);
        font: inherit;
        font-size: 16px;
        line-height: 1.4;
    }

    .mob .input::placeholder {
        color: var(--mob-muted);
        opacity: 1;
    }

    .mob .input:focus {
        border-color: var(--mob-accent-solid);
        outline: 2px solid var(--mob-accent-wash);
        outline-offset: 0;
    }

    .mob .textarea {
        min-height: 104px;
        resize: vertical;
    }

    .mob .input[type="file"] {
        padding: 9px 10px;
        color: var(--mob-muted);
        font-size: .875rem;
    }

    .mob .input[type="file"]::file-selector-button {
        margin-right: 12px;
        padding: 7px 12px;
        border: 1px solid var(--mob-rule-strong);
        border-radius: 7px;
        background: transparent;
        color: var(--mob-ink);
        font: inherit;
        font-size: .8125rem;
        font-weight: 600;
        cursor: pointer;
    }

    .mob .input[type="file"]::file-selector-button:hover {
        border-color: var(--mob-accent-solid);
        background: var(--mob-accent-wash);
    }

    .mob .grid-2 {
        display: grid;
        gap: 16px 12px;
        margin-top: 16px;
    }

    .mob .grid-2 .field {
        margin-top: 0;
    }

    @media (min-width: 400px) {
        .mob .grid-2 {
            grid-template-columns: 1fr 1fr;
        }
    }

    .mob .hint {
        margin: 8px 0 0;
        font-size: .75rem;
        color: var(--mob-muted);
    }

    /* ===== Flash ===== */
    .mob .flash {
        padding: 11px 14px;
        border: 1px solid var(--mob-glass-border);
        border-radius: 14px;
        background: var(--mob-glass-strong);
        box-shadow: inset 0 1px 0 var(--mob-glass-edge);
        font-size: .875rem;
    }

    .mob .flash.is-ok {
        border-color: color-mix(in srgb, var(--mob-ok) 32%, transparent);
        background: color-mix(in srgb, var(--mob-ok) 9%, transparent);
        color: var(--mob-ok);
    }

    .mob .flash.is-err {
        border-color: color-mix(in srgb, var(--mob-bad) 32%, transparent);
        background: color-mix(in srgb, var(--mob-bad) 8%, transparent);
        color: var(--mob-bad);
    }

    /* ===== Riwayat ===== */
    .mob .section-head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 12px;
    }

    .mob .section-head h2 {
        margin: 0;
        font-size: 1rem;
        font-weight: 650;
    }

    .mob .count {
        color: var(--mob-muted);
        font-size: .8125rem;
        font-variant-numeric: tabular-nums;
    }

    .mob .empty {
        margin: 8px 0 0;
        color: var(--mob-muted);
        font-size: .875rem;
    }

    .mob .list {
        margin: 4px 0 0;
        padding: 0;
        list-style: none;
    }

    .mob .item {
        padding: 16px 0;
        border-top: 1px solid var(--mob-rule);
    }

    .mob .item:first-child {
        margin-top: 12px;
        border-top: 0;
        padding-top: 0;
    }

    .mob .item-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .mob .item-title {
        margin: 0;
        font-size: .9375rem;
        font-weight: 650;
    }

    .mob .item-date {
        margin: 2px 0 0;
        color: var(--mob-muted);
        font-size: .8125rem;
        font-variant-numeric: tabular-nums;
    }

    .mob .status {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 2px;
        color: var(--mob-muted);
        font-size: .75rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .mob .status::before {
        content: "";
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    .mob .status.is-ok {
        color: var(--mob-ok);
    }

    .mob .status.is-warn {
        color: var(--mob-warn);
    }

    .mob .status.is-bad {
        color: var(--mob-bad);
    }

    .mob .item-body {
        margin: 9px 0 0;
        color: var(--mob-ink);
        font-size: .875rem;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    .mob .balasan {
        margin-top: 12px;
        padding: 11px 12px;
        border: 1px solid var(--mob-glass-border);
        border-radius: 14px;
        background: color-mix(in srgb, var(--mob-ink) 4%, transparent);
        box-shadow: inset 0 1px 0 var(--mob-glass-edge);
    }

    .mob .balasan-label {
        margin: 0 0 3px;
        color: var(--mob-muted);
        font-size: .6875rem;
        font-weight: 600;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .mob .balasan p:last-child {
        margin: 0;
        font-size: .875rem;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    /* ===== Navigasi bawah app: kapsul kaca melayang ===== */
    .mob-nav {
        position: fixed;
        left: 50%;
        bottom: calc(10px + env(safe-area-inset-bottom));
        z-index: 40;
        box-sizing: border-box;
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 4px;
        width: calc(100% - 24px);
        max-width: 456px;
        transform: translateX(-50%);
        padding: 6px;
        border: 1px solid var(--mob-glass-border);
        border-radius: 999px;
        background: var(--mob-glass);
        box-shadow:
            inset 0 1px 0 var(--mob-glass-edge),
            inset 0 -1px 0 rgba(255, 255, 255, .22),
            0 12px 32px -14px rgba(15, 35, 60, .45);
        -webkit-backdrop-filter: blur(24px) saturate(180%);
        backdrop-filter: blur(24px) saturate(180%);
    }

    .mob-nav__item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 3px;
        padding: 9px 4px 7px;
        border-radius: 999px;
        color: var(--mob-muted);
        font-size: .6875rem;
        font-weight: 600;
        line-height: 1.2;
        text-align: center;
        text-decoration: none;
        -webkit-tap-highlight-color: transparent;
        transition: background-color .18s ease, color .18s ease;
    }

    .mob-nav__item svg {
        width: 22px;
        height: 22px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .mob-nav__item.is-active {
        color: var(--mob-accent-ink);
        background: linear-gradient(180deg, rgba(0, 116, 186, .18), rgba(0, 116, 186, .09));
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .7);
    }

    .mob-nav__item:active {
        transform: translateY(1px);
    }

    html[data-bs-theme="dark"] .mob-nav {
        box-shadow:
            inset 0 1px 0 var(--mob-glass-edge),
            inset 0 -1px 0 rgba(0, 0, 0, .28),
            0 12px 32px -14px rgba(0, 0, 0, .7);
    }

    html[data-bs-theme="dark"] .mob-nav__item.is-active {
        background: linear-gradient(180deg, rgba(124, 196, 240, .16), rgba(124, 196, 240, .08));
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .14);
    }

    /* Tanpa backdrop-filter kaca jadi putih susu, tetap terbaca. */
    @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
        .mob .sheet,
        .mob .flash,
        dialog.pwa-install {
            background: rgba(255, 255, 255, .95);
        }

        .mob-nav {
            background: rgba(255, 255, 255, .94);
        }

        html[data-bs-theme="dark"] .mob .sheet,
        html[data-bs-theme="dark"] .mob .flash,
        html[data-bs-theme="dark"] dialog.pwa-install {
            background: rgba(20, 31, 46, .96);
        }

        html[data-bs-theme="dark"] .mob-nav {
            background: rgba(20, 31, 46, .94);
        }
    }

    /* ===== Profil ===== */
    .prof-card {
        display: grid;
        justify-items: center;
        gap: 4px;
        padding: 22px 16px 18px;
        text-align: center;
    }

    .prof-avatar {
        display: grid;
        place-items: center;
        width: 64px;
        height: 64px;
        border: 1px solid var(--mob-glass-border);
        border-radius: 20px;
        background: linear-gradient(160deg, var(--mob-accent-wash), transparent 70%);
        box-shadow: inset 0 1px 0 var(--mob-glass-edge), 0 12px 24px -20px rgba(15, 35, 60, .55);
        color: var(--mob-accent-ink);
        font-size: 20px;
        font-weight: 750;
        letter-spacing: .02em;
    }

    .prof-name {
        margin: 8px 0 0;
        font-size: 1.05rem;
        font-weight: 700;
        letter-spacing: -.01em;
    }

    .prof-meta {
        margin: 0;
        color: var(--mob-muted);
        font-size: .8125rem;
    }

    .prof-list {
        width: 100%;
        margin: 12px 0 0;
        padding: 0;
        list-style: none;
    }

    .prof-list__row {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        padding: 11px 0;
        border-top: 1px solid var(--mob-rule);
        font-size: .875rem;
    }

    .prof-list__label {
        color: var(--mob-muted);
    }

    .prof-list__value {
        text-align: right;
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    .mob .btn.is-outline {
        border: 1px solid var(--mob-rule-strong);
        background: var(--mob-surface);
        color: var(--mob-ink);
    }

    .mob .btn.is-outline:hover {
        background: var(--mob-accent-wash);
    }

    /* ===== Ajakan install PWA ===== */
    dialog.pwa-install {
        box-sizing: border-box;
        width: 100%;
        max-width: 480px;
        margin: auto auto 0;
        padding: 0;
        border: 1px solid var(--mob-glass-border);
        border-radius: 26px 26px 0 0;
        background: var(--mob-glass-strong);
        color: var(--mob-ink);
        box-shadow: inset 0 1px 0 var(--mob-glass-edge), 0 -16px 40px -24px rgba(15, 35, 60, .55);
        -webkit-backdrop-filter: blur(28px) saturate(180%);
        backdrop-filter: blur(28px) saturate(180%);
    }

    dialog.pwa-install::backdrop {
        background: rgba(14, 23, 38, .45);
    }

    .pwa-install__body {
        padding: 20px 18px calc(18px + env(safe-area-inset-bottom));
    }

    .pwa-install__icon {
        display: block;
        width: 44px;
        height: 44px;
        border-radius: 12px;
    }

    .pwa-install__title {
        margin: 12px 0 0;
        font-size: 1rem;
        font-weight: 700;
        letter-spacing: -.01em;
    }

    .pwa-install__copy {
        margin: 6px 0 0;
        color: var(--mob-muted);
        font-size: .8125rem;
    }

    .pwa-install__actions {
        display: grid;
        gap: 8px;
        margin-top: 16px;
    }

    .pwa-install__actions .btn {
        min-height: 48px;
        padding: 0 18px;
        border: 1px solid transparent;
        border-radius: 14px;
        background: linear-gradient(180deg, #0a86d2 0%, #0074ba 100%);
        color: #fff;
        font: inherit;
        font-size: .9375rem;
        font-weight: 650;
        cursor: pointer;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .3), 0 12px 24px -16px rgba(0, 95, 153, .85);
    }

    .pwa-install__actions .btn.is-outline {
        border-color: var(--mob-glass-border);
        background: var(--mob-glass);
        color: var(--mob-ink);
        box-shadow: inset 0 1px 0 var(--mob-glass-edge);
    }

    @media (prefers-reduced-motion: reduce) {
        .mob *,
        .mob *::before,
        .mob *::after,
        .mob-nav__item {
            transition: none !important;
            animation: none !important;
        }
    }

    /* ===== Aurora ===== */
    :root {
        --mob-canvas: #eef5fb;
        --mob-surface: #ffffff;
        --mob-ink: #0b2239;
        --mob-muted: #4f657a;
        --mob-rule: rgba(9, 45, 74, .10);
        --mob-rule-strong: rgba(9, 45, 74, .20);
        --mob-accent-ink: #0f766e;
        --mob-accent-solid: #0f766e;
        --mob-accent-hover: #0b5d56;
        --mob-accent-wash: rgba(15, 118, 110, .10);
        --mob-ok: #0f766e;
        --mob-warn: #8a5300;
        --mob-bad: #be123c;
        --mob-glass: rgba(255, 255, 255, .62);
        --mob-glass-strong: rgba(255, 255, 255, .88);
        --mob-glass-border: rgba(255, 255, 255, .92);
        --mob-glass-edge: #ffffff;
        --mob-glass-shadow: 0 26px 46px -32px rgba(8, 35, 58, .30);
        --mob-field: rgba(255, 255, 255, .66);
        --mob-field-border: rgba(9, 45, 74, .12);
        --mob-field-edge: rgba(255, 255, 255, .92);
        --mob-glow-a: rgba(56, 189, 248, .26);
        --mob-glow-b: rgba(129, 140, 248, .20);
        --mob-glow-c: rgba(45, 212, 191, .26);
        --mob-nav: rgba(255, 255, 255, .80);
        --mob-cta-ink: #04121f;
    }

    html[data-bs-theme="dark"] {
        --mob-canvas: #070d18;
        --mob-surface: #0c1626;
        --mob-ink: #eaf2ff;
        --mob-muted: #8fa6c4;
        --mob-rule: rgba(255, 255, 255, .10);
        --mob-rule-strong: rgba(255, 255, 255, .18);
        --mob-accent-ink: #5eead4;
        --mob-accent-solid: #2dd4bf;
        --mob-accent-hover: #5eead4;
        --mob-accent-wash: rgba(94, 234, 212, .12);
        --mob-ok: #5eead4;
        --mob-warn: #fbbf24;
        --mob-bad: #fb7185;
        --mob-glass: rgba(255, 255, 255, .065);
        --mob-glass-strong: rgba(12, 22, 38, .88);
        --mob-glass-border: rgba(255, 255, 255, .14);
        --mob-glass-edge: rgba(255, 255, 255, .20);
        --mob-glass-shadow: 0 28px 50px -34px rgba(0, 0, 0, .85);
        --mob-field: rgba(255, 255, 255, .05);
        --mob-field-border: rgba(255, 255, 255, .10);
        --mob-field-edge: rgba(255, 255, 255, .12);
        --mob-glow-a: rgba(45, 212, 191, .34);
        --mob-glow-b: rgba(129, 140, 248, .30);
        --mob-glow-c: rgba(56, 189, 248, .22);
        --mob-nav: rgba(12, 22, 38, .80);
    }

    body::before {
        background:
            radial-gradient(38rem 30rem at 6% -8%, var(--mob-glow-a), transparent 70%),
            radial-gradient(34rem 28rem at 106% 10%, var(--mob-glow-b), transparent 72%),
            radial-gradient(46rem 34rem at 50% 112%, var(--mob-glow-c), transparent 74%);
    }

    .mob {
        gap: 0;
        padding: calc(16px + env(safe-area-inset-top)) max(14px, env(safe-area-inset-right)) calc(96px + env(safe-area-inset-bottom)) max(14px, env(safe-area-inset-left));
    }

    .mob .head {
        align-items: center;
        gap: 10px;
        margin: 0 0 13px;
        padding: 0 2px;
    }

    .mob .kicker {
        margin: 0 0 2px;
        color: var(--mob-muted);
        font-size: .6875rem;
        font-weight: 800;
        letter-spacing: .16em;
        text-transform: uppercase;
    }

    .mob h1 {
        font-size: 1.3125rem;
        font-weight: 650;
        letter-spacing: -.03em;
        line-height: 1.15;
    }

    .mob .meta {
        margin-top: 3px;
        color: var(--mob-muted);
        font-size: .75rem;
    }

    .mob .sheet {
        padding: 14px 16px;
        border-radius: 22px;
        background: var(--mob-glass);
        border: 1px solid var(--mob-glass-border);
        box-shadow: inset 0 1px 0 var(--mob-glass-edge), var(--mob-glass-shadow);
        -webkit-backdrop-filter: blur(18px) saturate(160%);
        backdrop-filter: blur(18px) saturate(160%);
    }

    .mob .sheet + .sheet {
        margin-top: 12px;
    }

    .mob .state-title {
        font-size: 1.25rem;
        letter-spacing: -.025em;
    }

    .mob .state-text {
        margin-top: 4px;
        font-size: .8125rem;
        line-height: 1.45;
    }

    .mob .field {
        margin-top: 12px;
    }

    .mob .label {
        margin-bottom: 5px;
        color: var(--mob-muted);
        font-size: .6875rem;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
    }

    .mob .label .opt {
        font-weight: 400;
        letter-spacing: 0;
        text-transform: none;
    }

    .mob .input {
        min-height: 46px;
        padding: 11px 12px;
        border-radius: 13px;
        border: 1px solid var(--mob-field-border);
        background: var(--mob-field);
        box-shadow: inset 0 1px 0 var(--mob-field-edge);
        color: var(--mob-ink);
        font-size: 16px;
    }

    .mob .input::placeholder {
        color: var(--mob-muted);
    }

    .mob .input:focus {
        border-color: var(--mob-accent-solid);
        outline: 2px solid var(--mob-accent-wash);
    }

    .mob .textarea {
        min-height: 92px;
    }

    .mob .hint {
        margin-top: 8px;
        color: var(--mob-muted);
        font-size: .6875rem;
        line-height: 1.45;
    }

    .mob .btn {
        min-height: 52px;
        margin-top: 12px;
        border-radius: 16px;
        background: linear-gradient(135deg, #5eead4, #38bdf8);
        color: var(--mob-cta-ink);
        font-size: .8125rem;
        font-weight: 800;
        letter-spacing: 0;
        text-decoration: none;
        box-shadow: 0 16px 30px -16px rgba(56, 189, 248, .7);
    }

    .mob .btn:hover {
        background: linear-gradient(135deg, #7cf0dd, #5cc9fa);
    }

    .mob .btn.is-outline {
        border: 1px solid var(--mob-glass-border);
        background: var(--mob-glass);
        color: var(--mob-ink);
        box-shadow: inset 0 1px 0 var(--mob-glass-edge);
    }

    .mob .btn.is-outline:hover {
        background: var(--mob-accent-wash);
    }

    .mob .flash {
        margin-bottom: 12px;
        border-radius: 14px;
        background: var(--mob-glass-strong);
        font-size: .8125rem;
    }

    .mob .section-head {
        align-items: center;
    }

    .mob .section-head h2 {
        color: var(--mob-muted);
        font-size: .6875rem;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .mob .count {
        font-size: .6875rem;
        font-weight: 600;
    }

    .mob .empty {
        margin-top: 8px;
        font-size: .8125rem;
    }

    .mob .item {
        padding: 11px 0;
        border-top: 1px solid var(--mob-rule);
    }

    .mob .item:first-child {
        margin-top: 8px;
        padding-top: 10px;
    }

    .mob .item-title {
        font-size: .875rem;
        font-weight: 700;
    }

    .mob .item-date {
        font-size: .6875rem;
        color: var(--mob-muted);
    }

    .mob .status {
        margin-top: 1px;
        padding: 3px 7px;
        border-radius: 999px;
        background: var(--mob-field);
        font-size: .625rem;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .mob .status::before {
        width: 5px;
        height: 5px;
    }

    .mob .status.is-ok {
        background: var(--mob-accent-wash);
        color: var(--mob-accent-ink);
    }

    .mob .status.is-warn {
        background: color-mix(in srgb, var(--mob-warn) 14%, transparent);
        color: var(--mob-warn);
    }

    .mob .status.is-bad {
        background: color-mix(in srgb, var(--mob-bad) 12%, transparent);
        color: var(--mob-bad);
    }

    .mob .item-body {
        margin-top: 6px;
        font-size: .8125rem;
        line-height: 1.45;
    }

    .mob .balasan {
        margin-top: 9px;
        padding: 8px 10px;
        border-radius: 12px;
        border: 1px solid var(--mob-field-border);
        background: var(--mob-field);
        box-shadow: inset 0 1px 0 var(--mob-field-edge);
    }

    .mob .balasan-label {
        color: var(--mob-muted);
        font-size: .625rem;
        font-weight: 800;
        letter-spacing: .1em;
    }

    .mob .balasan p:last-child {
        font-size: .8125rem;
        line-height: 1.45;
    }

    .mob .link {
        color: var(--mob-accent-ink);
        font-size: .75rem;
    }

    /* Navigasi: dock kaca melayang */
    .mob-nav {
        bottom: calc(12px + env(safe-area-inset-bottom));
        gap: 2px;
        padding: 6px;
        border: 1px solid var(--mob-nav-border, var(--mob-glass-border));
        background: var(--mob-nav);
        box-shadow: inset 0 1px 0 var(--mob-glass-edge), 0 18px 34px -20px rgba(4, 18, 31, .55);
    }

    html[data-bs-theme="dark"] .mob-nav {
        box-shadow: inset 0 1px 0 var(--mob-glass-edge), 0 18px 34px -20px rgba(0, 0, 0, .9);
    }

    .mob-nav__item {
        gap: 3px;
        padding: 8px 4px 7px;
        font-size: .6875rem;
        font-weight: 700;
        letter-spacing: .01em;
    }

    .mob-nav__item svg {
        width: 20px;
        height: 20px;
    }

    .mob-nav__item.is-active {
        background: var(--mob-accent-wash);
        color: var(--mob-accent-ink);
        box-shadow: none;
    }

    html[data-bs-theme="dark"] .mob-nav__item.is-active {
        background: var(--mob-accent-wash);
    }

    /* Profil */
    .prof-card {
        gap: 2px;
        padding: 20px 16px 16px;
    }

    .prof-avatar {
        width: 64px;
        height: 64px;
        border-radius: 22px;
        background: linear-gradient(160deg, var(--mob-accent-wash), transparent 70%);
        border: 1px solid var(--mob-glass-border);
        color: var(--mob-accent-ink);
        font-size: 20px;
        font-weight: 800;
    }

    .prof-name {
        margin-top: 10px;
        font-size: 1.1875rem;
        font-weight: 650;
        letter-spacing: -.02em;
    }

    .prof-meta {
        margin-top: 2px;
        font-size: .75rem;
    }

    .prof-list {
        margin-top: 12px;
    }

    .prof-list__row {
        padding: 9px 0;
        border-top: 1px solid var(--mob-rule);
        font-size: .8125rem;
    }

    .prof-list__label {
        color: var(--mob-muted);
    }

    .prof-list__value {
        font-weight: 700;
    }

    /* PWA */
    dialog.pwa-install {
        border-radius: 26px 26px 0 0;
        background: var(--mob-glass-strong);
        color: var(--mob-ink);
        border: 1px solid var(--mob-glass-border);
    }

    .pwa-install__title {
        font-size: 1.0625rem;
        font-weight: 700;
        letter-spacing: -.02em;
    }

    .pwa-install__copy {
        font-size: .8125rem;
        line-height: 1.45;
    }

    .pwa-install__actions .btn {
        min-height: 48px;
        border-radius: 14px;
        background: linear-gradient(135deg, #5eead4, #38bdf8);
        color: var(--mob-cta-ink);
        font-size: .8125rem;
        font-weight: 800;
        box-shadow: 0 14px 26px -16px rgba(56, 189, 248, .7);
    }

    .pwa-install__actions .btn.is-outline {
        border: 1px solid var(--mob-glass-border);
        background: var(--mob-glass);
        color: var(--mob-ink);
        box-shadow: inset 0 1px 0 var(--mob-glass-edge);
    }

    @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
        .mob .sheet,
        .mob .flash,
        dialog.pwa-install {
            background: rgba(255, 255, 255, .96);
        }

        .mob-nav {
            background: rgba(255, 255, 255, .96);
        }

        html[data-bs-theme="dark"] .mob .sheet,
        html[data-bs-theme="dark"] .mob .flash,
        html[data-bs-theme="dark"] dialog.pwa-install {
            background: rgba(10, 19, 32, .97);
        }

        html[data-bs-theme="dark"] .mob-nav {
            background: rgba(10, 19, 32, .97);
        }
    }
</style>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('<?= base_url('sw.js') ?>').catch(function () {});
    });
}
</script>
<script>
(function () {
    var KEY = 'pwa-install-seen';
    var ULANG_MS = 7 * 24 * 60 * 60 * 1000;
    var sheet = null;
    var event = null;

    function sudahTerpasang() {
        return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
    }

    function tandai() {
        try { localStorage.setItem(KEY, String(Date.now())); } catch (e) {}
    }

    function bolehTampil() {
        try {
            var at = Number(localStorage.getItem(KEY) || 0);
            return !at || Date.now() - at > ULANG_MS;
        } catch (e) {
            return true;
        }
    }

    function tampilkan() {
        if (!sheet || sheet.open || sudahTerpasang() || !bolehTampil()) {
            return;
        }
        tandai();
        sheet.showModal();
    }

    // Chrome/Edge: dialog install asli dipicu dari tombol kita.
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        event = e;
        tampilkan();
    });

    function init() {
        sheet = document.getElementById('pwa-install');
        if (!sheet || typeof sheet.showModal !== 'function') {
            sheet = null;
            return;
        }

        var tombol = sheet.querySelector('[data-pwa-install]');
        var salinan = sheet.querySelector('[data-pwa-copy]');
        var nanti = sheet.querySelector('[data-pwa-dismiss]');

        tombol.addEventListener('click', function () {
            if (!event) {
                return;
            }
            var prompt = event;
            event = null;
            sheet.close();
            prompt.prompt();
            prompt.userChoice.catch(function () {}).then(tandai);
        });

        nanti.addEventListener('click', function () {
            sheet.close();
        });

        window.addEventListener('appinstalled', function () {
            tandai();
            if (sheet.open) {
                sheet.close();
            }
        });

        // Safari iOS tidak punya event install: tampilkan instruksi manual saja.
        var ios = /iPad|iPhone|iPod/.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        if (ios) {
            salinan.textContent = 'Ketuk tombol Bagikan di Safari, lalu pilih "Tambahkan ke Layar Utama".';
            tombol.hidden = true;
            nanti.textContent = 'Mengerti';
            nanti.classList.remove('is-outline');
            window.addEventListener('load', function () {
                if (!event) {
                    tampilkan();
                }
            });
        }

        if (event) {
            tampilkan();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
