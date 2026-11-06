<?php
/**
 * Tema halaman mobile presensi: /absen, /pengajuan_izin, dan /profil.
 *
 * Satu bahasa visual untuk dua halaman: kanvas tenang, sheet rata dengan
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
<meta name="theme-color" content="#0074ba" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0e1726" media="(prefers-color-scheme: dark)">
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
    }

    html,
    body {
        margin: 0;
        background: var(--mob-canvas);
    }

    body {
        color: var(--mob-ink);
        font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        -webkit-font-smoothing: antialiased;
        text-rendering: optimizeLegibility;
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
        border: 1px solid var(--mob-rule-strong);
        border-radius: 9px;
        background: var(--mob-surface);
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
        padding: 18px 16px;
        border: 1px solid var(--mob-rule);
        border-radius: 12px;
        background: var(--mob-surface);
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
        border-radius: 10px;
        background: transparent;
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
        border-radius: 10px;
        background: var(--mob-accent-solid);
        color: #fff;
        font: inherit;
        font-size: .9375rem;
        font-weight: 650;
        letter-spacing: .01em;
        cursor: pointer;
        transition: background-color .15s ease;
    }

    .mob .btn:hover {
        background: var(--mob-accent-hover);
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
        border: 1px solid var(--mob-rule-strong);
        border-radius: 9px;
        background: var(--mob-surface);
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
        border: 1px solid var(--mob-rule);
        border-radius: 10px;
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
        border: 1px solid var(--mob-rule);
        border-radius: 9px;
        background: color-mix(in srgb, var(--mob-ink) 3%, transparent);
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
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 4px;
        width: calc(100% - 24px);
        max-width: 456px;
        transform: translateX(-50%);
        padding: 6px;
        border: 1px solid rgba(255, 255, 255, .62);
        border-radius: 999px;
        background: rgba(255, 255, 255, .58);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .85),
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
        border-color: rgba(255, 255, 255, .12);
        background: rgba(20, 31, 46, .55);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .10),
            inset 0 -1px 0 rgba(0, 0, 0, .28),
            0 12px 32px -14px rgba(0, 0, 0, .7);
    }

    html[data-bs-theme="dark"] .mob-nav__item.is-active {
        background: linear-gradient(180deg, rgba(124, 196, 240, .16), rgba(124, 196, 240, .08));
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .14);
    }

    /* Tanpa backdrop-filter kaca jadi putih susu, tetap terbaca. */
    @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
        .mob-nav {
            background: rgba(255, 255, 255, .94);
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
        border: 1px solid var(--mob-rule);
        border-radius: 20px;
        background: var(--mob-accent-wash);
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
        border: 0;
        border-top: 1px solid var(--mob-rule);
        border-radius: 18px 18px 0 0;
        background: var(--mob-surface);
        color: var(--mob-ink);
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
        border-radius: 10px;
        background: var(--mob-accent-solid);
        color: #fff;
        font: inherit;
        font-size: .9375rem;
        font-weight: 650;
        cursor: pointer;
    }

    .pwa-install__actions .btn.is-outline {
        border-color: var(--mob-rule-strong);
        background: var(--mob-surface);
        color: var(--mob-ink);
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
