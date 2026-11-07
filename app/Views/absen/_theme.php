<?php
/**
 * Tampilan /absen: satu lembar, tiga blok — shift, aksi, hari ini.
 *
 * Token warna dan permukaan berasal dari `inc/_presensi_mobile_theme.php`.
 * Halaman ini hanya menambah susunan dan komponen yang khas absen.
 */
?>
<style>
    .absen {
        gap: 0;
        padding-top: calc(14px + env(safe-area-inset-top));
    }

    .absen .ic {
        width: 18px;
        height: 18px;
        flex: 0 0 auto;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    /* ===== Kepala ===== */
    .absen .head {
        gap: 10px;
        margin-bottom: 14px;
        padding: 0 2px;
    }

    .absen .avatar {
        display: grid;
        place-items: center;
        width: 38px;
        height: 38px;
        flex: 0 0 auto;
        border: 1px solid var(--mob-glass-border);
        border-radius: 13px;
        background: var(--mob-field);
        box-shadow: inset 0 1px 0 var(--mob-glass-edge);
        color: var(--mob-accent-ink);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .02em;
    }

    .absen .head-main {
        flex: 1 1 auto;
    }

    .absen .head h1 {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 650;
        letter-spacing: -.02em;
        line-height: 1.2;
    }

    .absen .head .meta {
        margin-top: 2px;
        color: var(--mob-muted);
        font-size: .6875rem;
        line-height: 1.35;
    }

    .absen .head .meta .visit {
        display: inline-block;
        margin-left: 5px;
        padding: 1px 6px;
        border-radius: 999px;
        background: var(--mob-accent-wash);
        color: var(--mob-accent-ink);
        font-size: .5625rem;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    /* ===== Lembar ===== */
    .absen .sheet {
        padding: 18px 16px;
    }

    /* ===== Shift ===== */
    .absen .plate-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .absen .plate-shift {
        min-width: 0;
    }

    .absen .plate-name {
        margin: 0;
        color: var(--mob-muted);
        font-size: .6875rem;
        font-weight: 750;
        letter-spacing: .08em;
        line-height: 1.2;
        text-transform: uppercase;
    }

    .absen .plate-state {
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 6px 0 0;
        color: var(--mob-ink);
        font-size: .8125rem;
        font-weight: 600;
        line-height: 1.2;
    }

    .absen .state-dot {
        width: 6px;
        height: 6px;
        flex: 0 0 auto;
        border-radius: 999px;
        background: var(--mob-accent-solid);
        box-shadow: 0 0 0 3px var(--mob-accent-wash);
    }

    .absen .plate-state.is-wait .state-dot {
        background: var(--mob-warn);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--mob-warn) 16%, transparent);
    }

    .absen .plate-state.is-done {
        color: var(--mob-muted);
    }

    .absen .plate-state.is-done .state-dot {
        background: var(--mob-muted);
        box-shadow: none;
    }

    .absen .clock-line {
        display: flex;
        align-items: baseline;
        gap: 3px;
        flex: 0 0 auto;
        margin-top: 1px;
    }

    .absen .clock {
        font-size: .8125rem;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
        letter-spacing: .01em;
        line-height: 1;
    }

    .absen .clock .sec {
        color: var(--mob-accent-ink);
        font-weight: 750;
    }

    .absen .tz {
        color: var(--mob-muted);
        font-size: .5625rem;
        font-weight: 700;
        letter-spacing: .08em;
    }

    .absen .plate-range {
        margin: 12px 0 0;
        font-size: 1.5rem;
        font-weight: 650;
        letter-spacing: -.03em;
        line-height: 1.15;
        font-variant-numeric: tabular-nums;
    }

    /* ===== Rel langkah ===== */
    .absen .rail {
        position: relative;
        display: grid;
        grid-template-columns: repeat(var(--rail-count, 2), minmax(0, 1fr));
        gap: 4px;
        margin: 16px 0 0;
        padding: 0;
        list-style: none;
    }

    .absen .rail::before {
        content: "";
        position: absolute;
        top: 4px;
        left: 4px;
        right: calc(100% / var(--rail-count, 2) - 4px);
        height: 1px;
        background: var(--mob-rule);
    }

    .absen .rail-step {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
    }

    .absen .rail-dot {
        width: 8px;
        height: 8px;
        border: 1.5px solid var(--mob-rule-strong);
        border-radius: 999px;
        background: var(--mob-canvas);
    }

    .absen .rail-step.done .rail-dot {
        border-color: var(--mob-accent-solid);
        background: var(--mob-accent-solid);
    }

    .absen .rail-step.now .rail-dot {
        border-color: var(--mob-accent-solid);
        background: var(--mob-accent-solid);
        box-shadow: 0 0 0 3px var(--mob-accent-wash);
    }

    .absen .rail-label {
        color: var(--mob-muted);
        font-size: .5625rem;
        font-weight: 750;
        letter-spacing: .05em;
        line-height: 1.2;
        text-transform: uppercase;
    }

    .absen .rail-step.done .rail-label,
    .absen .rail-step.now .rail-label {
        color: var(--mob-ink);
    }

    /* ===== Aksi ===== */
    .absen .action {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--mob-rule);
    }

    .absen .action-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .absen .action h2 {
        margin: 0;
        font-size: 1.0625rem;
        font-weight: 650;
        letter-spacing: -.02em;
        line-height: 1.25;
    }

    .absen .action-desc {
        margin: 4px 0 0;
        color: var(--mob-muted);
        font-size: .75rem;
        line-height: 1.45;
    }

    .absen .chip {
        flex: 0 0 auto;
        padding: 3px 8px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--mob-warn) 14%, transparent);
        color: var(--mob-warn);
        font-size: .625rem;
        font-weight: 750;
        font-variant-numeric: tabular-nums;
        letter-spacing: .01em;
        white-space: nowrap;
    }

    /* ===== Foto ===== */
    .absen .photo {
        position: relative;
    }

    .absen .photo input[type="file"] {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: -1px;
        padding: 0;
        overflow: hidden;
        clip: rect(0 0 0 0);
        border: 0;
        white-space: nowrap;
    }

    .absen .photo-slot {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 56px;
        margin-top: 14px;
        padding: 10px 12px;
        border: 1px solid var(--mob-field-border);
        border-radius: 14px;
        background: var(--mob-field);
        box-shadow: inset 0 1px 0 var(--mob-field-edge);
        cursor: pointer;
        transition: border-color .15s ease, background-color .15s ease;
    }

    .absen .photo-slot:hover {
        border-color: var(--mob-accent-solid);
        background: var(--mob-accent-wash);
    }

    .absen .photo input:focus-visible + .photo-slot {
        outline: 2px solid var(--mob-accent-solid);
        outline-offset: 2px;
    }

    .absen .cam,
    .absen .photo-preview {
        display: grid;
        place-items: center;
        width: 36px;
        height: 36px;
        flex: 0 0 auto;
        border-radius: 11px;
        background: var(--mob-accent-wash);
        color: var(--mob-accent-ink);
    }

    .absen .photo-preview {
        display: none;
        background-position: center;
        background-size: cover;
    }

    .absen .photo-slot.has-photo .cam {
        display: none;
    }

    .absen .photo-slot.has-photo .photo-preview {
        display: block;
    }

    .absen .photo-text {
        display: grid;
        gap: 1px;
        min-width: 0;
    }

    .absen .photo-title {
        font-size: .8125rem;
        font-weight: 650;
        line-height: 1.3;
    }

    .absen .photo-hint {
        color: var(--mob-muted);
        font-size: .6875rem;
        line-height: 1.3;
    }

    .absen .no-photo {
        display: flex;
        align-items: center;
        gap: 9px;
        margin: 14px 0 0;
        padding: 12px;
        border: 1px solid var(--mob-field-border);
        border-radius: 14px;
        background: var(--mob-field);
        color: var(--mob-muted);
        font-size: .75rem;
        line-height: 1.4;
    }

    /* ===== Tombol aksi ===== */
    .absen .punch-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        width: 100%;
        min-height: 52px;
        margin-top: 12px;
        padding: 0 16px;
        border: 0;
        border-radius: 14px;
        background: var(--mob-accent-solid);
        color: #fff;
        font: inherit;
        font-size: .9375rem;
        font-weight: 700;
        letter-spacing: -.01em;
        cursor: pointer;
        transition: background-color .15s ease, transform .1s ease;
    }

    html[data-bs-theme="dark"] .absen .punch-btn {
        color: var(--mob-cta-ink);
    }

    .absen .punch-btn:hover {
        background: var(--mob-accent-hover);
    }

    .absen .punch-btn:active {
        transform: scale(.99);
    }

    .absen .punch-btn[disabled] {
        border: 1px solid var(--mob-field-border);
        background: var(--mob-field);
        color: var(--mob-muted);
        cursor: not-allowed;
        transform: none;
    }

    .absen .btn-ic {
        display: grid;
        place-items: center;
    }

    .absen .btn-ic .ic {
        width: 19px;
        height: 19px;
    }

    .absen .msg {
        min-height: 0;
        margin: 0;
    }

    .absen .msg:not(:empty) {
        margin-top: 10px;
    }

    .absen .lihat-peta {
        margin-top: 4px;
        padding: 0;
        border: 0;
        background: transparent;
        font-family: inherit;
        font-size: .75rem;
        cursor: pointer;
    }

    .absen .lihat-peta[hidden] {
        display: none;
    }

    /* ===== Hari ini ===== */
    .absen .today {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--mob-rule);
    }

    .absen .today-head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 10px;
    }

    .absen .today h2 {
        margin: 0;
        font-size: .8125rem;
        font-weight: 700;
        letter-spacing: -.01em;
    }

    .absen .count {
        color: var(--mob-muted);
        font-size: .6875rem;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
    }

    .absen .today-line {
        margin: 6px 0 0;
        font-size: .75rem;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .absen .today-line.is-empty {
        color: var(--mob-muted);
    }

    .absen .thumbs {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
    }

    .absen .thumb {
        width: 38px;
        height: 38px;
        overflow: hidden;
        border: 1px solid var(--mob-field-border);
        border-radius: 10px;
        background: var(--mob-field);
    }

    .absen .thumb img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    @media (max-width: 380px) {
        .absen .plate-range {
            font-size: 1.3125rem;
        }

        .absen .rail-label {
            font-size: .5rem;
            letter-spacing: .03em;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .absen .photo-slot,
        .absen .punch-btn {
            transition: none;
        }
    }
</style>
