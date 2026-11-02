<?php
/**
 * Tema halaman mobile presensi: /absen dan /pengajuan_izin.
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
        padding: calc(20px + env(safe-area-inset-top)) max(16px, env(safe-area-inset-right)) calc(28px + env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
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

    @media (prefers-reduced-motion: reduce) {
        .mob *,
        .mob *::before,
        .mob *::after {
            transition: none !important;
            animation: none !important;
        }
    }
</style>
