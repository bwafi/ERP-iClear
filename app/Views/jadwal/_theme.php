<?php
/**
 * Tema halaman /jadwal mobile. Semua selector di-scope .jadwal supaya tidak
 * bocor ke halaman presensi lain. Warna shift memakai token yang sama di
 * terang dan gelap; hari ini ditandai ring aksen.
 */
?>
<style>
    .jadwal {
        --cal-pagi: #0f766e;
        --cal-pagi-wash: rgba(15, 118, 110, .10);
        --cal-siang: #0369a1;
        --cal-siang-wash: rgba(3, 105, 161, .10);
        --cal-full: #4338ca;
        --cal-full-wash: rgba(67, 56, 202, .10);
        --cal-ps: #a16207;
        --cal-ps-wash: rgba(161, 98, 7, .12);
        --cal-off: #475569;
        --cal-off-wash: rgba(71, 85, 105, .09);
        --cal-izin: #b42318;
        --cal-izin-wash: rgba(180, 35, 24, .09);
        --cal-sakit: #b45309;
        --cal-sakit-wash: rgba(180, 83, 9, .10);
        --cal-cuti: #7c3aed;
        --cal-cuti-wash: rgba(124, 58, 237, .09);
    }

    html[data-bs-theme="dark"] .jadwal {
        --cal-pagi: #5eead4;
        --cal-pagi-wash: rgba(94, 234, 212, .10);
        --cal-siang: #7dd3fc;
        --cal-siang-wash: rgba(125, 211, 252, .10);
        --cal-full: #a5b4fc;
        --cal-full-wash: rgba(165, 180, 252, .10);
        --cal-ps: #fbbf24;
        --cal-ps-wash: rgba(251, 191, 36, .12);
        --cal-off: #94a3b8;
        --cal-off-wash: rgba(148, 163, 184, .10);
        --cal-izin: #fda4af;
        --cal-izin-wash: rgba(253, 164, 175, .10);
        --cal-sakit: #fdba74;
        --cal-sakit-wash: rgba(253, 186, 116, .10);
        --cal-cuti: #c4b5fd;
        --cal-cuti-wash: rgba(196, 181, 253, .10);
    }

    .jadwal .head {
        align-items: center;
        gap: 10px;
        margin-bottom: 2px;
        padding: 0 2px;
    }

    .jadwal .head-main {
        flex: 1 1 auto;
        min-width: 0;
    }

    .jadwal .avatar {
        display: grid;
        place-items: center;
        width: 38px;
        height: 38px;
        flex: 0 0 auto;
        border: 1px solid var(--mob-glass-border);
        border-radius: 12px;
        background: var(--mob-accent-wash);
        color: var(--mob-accent-ink);
        font-size: 12px;
        font-weight: 750;
        letter-spacing: .02em;
    }

    .jadwal .kicker {
        margin: 0 0 2px;
        color: var(--mob-muted);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .16em;
        line-height: 1.2;
        text-transform: uppercase;
    }

    .jadwal .head h1 {
        margin: 0;
        font-size: 15px;
        font-weight: 720;
        letter-spacing: -.015em;
        line-height: 1.2;
    }

    .jadwal .head .meta {
        margin: 1px 0 0;
        font-size: 10.5px;
        overflow-wrap: anywhere;
    }

    /* ===== Lembar kalender ===== */
    .jadwal .jad-sheet {
        padding: 14px 12px 12px;
    }

    .jadwal .jad-month {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .jadwal .jad-month-main {
        min-width: 0;
        text-align: center;
    }

    .jadwal .jad-month h2 {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        letter-spacing: -.02em;
        line-height: 1.2;
    }

    .jadwal .jad-month p {
        margin: 2px 0 0;
        color: var(--mob-muted);
        font-size: 10.5px;
        font-variant-numeric: tabular-nums;
    }

    .jadwal .jad-nav {
        display: grid;
        place-items: center;
        width: 34px;
        height: 34px;
        flex: 0 0 auto;
        border: 1px solid var(--mob-glass-border);
        border-radius: 50%;
        background: var(--mob-field);
        color: var(--mob-ink);
        -webkit-tap-highlight-color: transparent;
        transition: background-color .18s ease;
    }

    .jadwal .jad-nav:active {
        background: var(--mob-accent-wash);
    }

    .jadwal .jad-nav svg {
        width: 17px;
        height: 17px;
        fill: none;
        stroke: currentColor;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .jadwal .jad-today {
        display: block;
        width: fit-content;
        margin: 9px auto 0;
        padding: 4px 10px;
        border-radius: 999px;
        background: var(--mob-accent-wash);
        color: var(--mob-accent-ink);
        font-size: 10.5px;
        font-weight: 650;
        text-decoration: none;
    }

    .jadwal .jad-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 6px;
        margin: 12px 0;
        padding: 9px 0;
        border-top: 1px solid var(--mob-rule);
        border-bottom: 1px solid var(--mob-rule);
    }

    .jadwal .jad-stat {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1px;
        min-width: 0;
    }

    .jadwal .jad-stat b {
        font-size: 16px;
        font-weight: 700;
        letter-spacing: -.02em;
        line-height: 1.1;
        font-variant-numeric: tabular-nums;
    }

    .jadwal .jad-stat span {
        color: var(--mob-muted);
        font-size: 8.5px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .jadwal .jad-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 3px;
    }

    .jadwal .jad-dow {
        padding-bottom: 3px;
        color: var(--mob-muted);
        font-size: 8px;
        font-weight: 800;
        letter-spacing: .08em;
        text-align: center;
        text-transform: uppercase;
    }

    .jadwal .jad-blank {
        min-height: 40px;
    }

    .jadwal .jad-day {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 1px;
        min-width: 0;
        min-height: 40px;
        padding: 4px 3px;
        border: 1px solid transparent;
        border-radius: 8px;
    }

    .jadwal .jad-day.is-set {
        background: var(--cal-wash, transparent);
    }

    .jadwal .jad-day.is-weekend .jad-num {
        color: var(--mob-muted);
    }

    .jadwal .jad-day.is-today {
        border-color: var(--mob-accent-solid);
        background: var(--mob-accent-wash);
    }

    .jadwal .jad-num {
        font-size: 11px;
        font-weight: 650;
        line-height: 1.15;
        font-variant-numeric: tabular-nums;
    }

    .jadwal .jad-day.is-today .jad-num {
        color: var(--mob-accent-ink);
        font-weight: 800;
    }

    .jadwal .jad-label {
        max-width: 100%;
        overflow: hidden;
        color: var(--cal-ink, var(--mob-muted));
        font-size: 8px;
        font-weight: 800;
        letter-spacing: .03em;
        line-height: 1.2;
        text-overflow: ellipsis;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .jadwal .jad-mark {
        position: absolute;
        top: 5px;
        right: 4px;
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: currentColor;
        color: var(--cal-ink, var(--mob-muted));
        opacity: .8;
    }

    .jadwal .jad-day[data-shift="pagi"] { --cal-ink: var(--cal-pagi); --cal-wash: var(--cal-pagi-wash); }
    .jadwal .jad-day[data-shift="siang"] { --cal-ink: var(--cal-siang); --cal-wash: var(--cal-siang-wash); }
    .jadwal .jad-day[data-shift="full"] { --cal-ink: var(--cal-full); --cal-wash: var(--cal-full-wash); }
    .jadwal .jad-day[data-shift="ps"] { --cal-ink: var(--cal-ps); --cal-wash: var(--cal-ps-wash); }
    .jadwal .jad-day[data-shift="off"] { --cal-ink: var(--cal-off); --cal-wash: var(--cal-off-wash); }
    .jadwal .jad-day[data-shift="izin"] { --cal-ink: var(--cal-izin); --cal-wash: var(--cal-izin-wash); }
    .jadwal .jad-day[data-shift="sakit"] { --cal-ink: var(--cal-sakit); --cal-wash: var(--cal-sakit-wash); }
    .jadwal .jad-day[data-shift="cuti"] { --cal-ink: var(--cal-cuti); --cal-wash: var(--cal-cuti-wash); }

    .jadwal .jad-hint {
        margin: 9px 2px 0;
        color: var(--mob-muted);
        font-size: 10px;
        line-height: 1.4;
    }

    /* ===== Catatan ===== */
    .jadwal .jad-sec-head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 8px;
    }

    .jadwal .jad-sec-head h2 {
        margin: 0;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: -.01em;
    }

    .jadwal .jad-count {
        color: var(--mob-muted);
        font-size: 10px;
        font-weight: 650;
        font-variant-numeric: tabular-nums;
    }

    .jadwal .jad-notes {
        display: grid;
        gap: 6px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .jadwal .jad-note {
        display: flex;
        align-items: baseline;
        gap: 8px;
        padding-top: 6px;
        border-top: 1px solid var(--mob-rule);
    }

    .jadwal .jad-note:first-child {
        padding-top: 0;
        border-top: 0;
    }

    .jadwal .jad-note-date {
        flex: 0 0 auto;
        color: var(--mob-accent-ink);
        font-size: 10.5px;
        font-weight: 750;
        font-variant-numeric: tabular-nums;
    }

    .jadwal .jad-note-body {
        min-width: 0;
        font-size: 11.5px;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    .jadwal .jad-note-body em {
        color: var(--mob-muted);
        font-style: normal;
    }

    /* ===== Footer read-only ===== */
    .jadwal .jad-foot {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        margin: 2px 0 0;
        padding: 0 4px;
        color: var(--mob-muted);
        font-size: 10px;
        line-height: 1.4;
        text-align: center;
    }

    .jadwal .jad-foot svg {
        width: 12px;
        height: 12px;
        flex: 0 0 auto;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    @media (min-width: 380px) {
        .jadwal .jad-sheet {
            padding: 16px 14px 14px;
        }

        .jadwal .jad-grid {
            gap: 4px;
        }

        .jadwal .jad-blank,
        .jadwal .jad-day {
            min-height: 44px;
        }

        .jadwal .jad-num {
            font-size: 12px;
        }

        .jadwal .jad-label {
            font-size: 8.5px;
        }
    }
</style>
