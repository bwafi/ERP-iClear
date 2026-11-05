<?php
/**
 * Tampilan khusus /absen. Sengaja tidak mengubah tema bersama
 * `inc/_presensi_mobile_theme.php` karena tema itu juga dipakai
 * /pengajuan_izin.
 */
?>
<style>
    .absen {
        --absen-soft: #f5f8fc;
        --absen-soft-2: #eef3f9;
        --absen-plate: #082a47;
        --absen-plate-ink: #f6fbff;
        --absen-plate-muted: #a9c4da;
        --absen-sky: #6cc6f5;
        --absen-shadow: 0 1px 2px rgba(13, 26, 41, .05), 0 16px 36px -26px rgba(13, 26, 41, .45);
        gap: 10px;
        padding-top: calc(18px + env(safe-area-inset-top));
        padding-bottom: calc(24px + env(safe-area-inset-bottom));
    }

    html[data-bs-theme="dark"] .absen {
        --absen-soft: #111c2a;
        --absen-soft-2: #0b1522;
        --absen-plate: #071c30;
        --absen-plate-muted: #9fbdd5;
        --absen-sky: #7cc4f0;
        --absen-shadow: 0 1px 2px rgba(0, 0, 0, .4), 0 20px 50px -30px rgba(0, 0, 0, .9);
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

    .absen .shift-card {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    /* ===== Kepala ===== */
    .absen .head {
        align-items: center;
        gap: 10px;
        margin-bottom: 2px;
    }

    .absen .head-main {
        flex: 1 1 auto;
    }

    .absen .avatar {
        display: grid;
        place-items: center;
        width: 38px;
        height: 38px;
        flex: 0 0 auto;
        border: 1px solid var(--mob-rule);
        border-radius: 12px;
        background: var(--mob-accent-wash);
        color: var(--mob-accent-ink);
        font-size: 12px;
        font-weight: 750;
        letter-spacing: .02em;
    }

    .absen .hello {
        margin: 0;
        color: var(--mob-muted);
        font-size: 10.5px;
        font-weight: 550;
        line-height: 1.2;
    }

    .absen .head h1 {
        margin: 1px 0 0;
        font-size: 15px;
        font-weight: 720;
        letter-spacing: -.015em;
        line-height: 1.2;
    }

    .absen .head .meta {
        margin: 1px 0 0;
        font-size: 10.5px;
    }

    .absen .head .meta .visit {
        margin-left: 5px;
        padding: 1px 5px;
        border-radius: 4px;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #8a5300;
        background: #ffe8b8;
    }

    .absen .head .ghost {
        min-height: 34px;
        padding: 0 10px;
        border-radius: 9px;
        font-size: 10.5px;
    }

    .absen .head .ghost .ic {
        width: 14px;
        height: 14px;
    }

    .absen .unit {
        display: flex;
        align-items: center;
        gap: 5px;
        margin: -2px 0 1px 2px;
        color: var(--mob-muted);
        font-size: 11px;
        font-weight: 550;
    }

    .absen .unit .ic {
        width: 13px;
        height: 13px;
        color: var(--mob-accent-ink);
    }

    .absen .unit .sep {
        padding: 0 1px;
    }

    .absen .sheet {
        padding: 16px;
        border-radius: 18px;
        background: var(--mob-surface);
        box-shadow: var(--absen-shadow);
    }

    /* ===== Pelat jam ===== */
    .absen .clock-card {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        padding: 15px 16px 14px;
        border: 1px solid rgba(255, 255, 255, .08);
        border-radius: 20px;
        background: var(--absen-plate);
        color: var(--absen-plate-ink);
        box-shadow: 0 18px 34px -28px rgba(4, 19, 34, .9);
    }

    .absen .plate-mark {
        position: absolute;
        z-index: -1;
        top: 20px;
        right: -20px;
        width: 132px;
        height: 132px;
        color: #fff;
        opacity: .055;
        stroke-width: 1.1;
    }

    .absen .plate-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .absen .shift-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 8px;
        border: 1px solid rgba(255, 255, 255, .15);
        border-radius: 999px;
        background: rgba(255, 255, 255, .09);
        color: #fff;
        font-size: 9.5px;
        font-weight: 750;
        letter-spacing: .075em;
        line-height: 1;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .absen .shift-badge .range {
        color: var(--absen-plate-muted);
        font-weight: 600;
        letter-spacing: .03em;
    }

    .absen .pulse {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: var(--absen-sky);
        box-shadow: 0 0 0 3px rgba(108, 198, 245, .16);
        animation: absen-pulse 2.4s ease-in-out infinite;
    }

    @keyframes absen-pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: .45; transform: scale(.8); }
    }

    .absen .clock-line {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        margin-top: 7px;
        line-height: 1;
    }

    .absen .clock {
        display: flex;
        align-items: baseline;
        color: #fff;
        font-family: "SFMono-Regular", ui-monospace, "Cascadia Mono", Menlo, Consolas, monospace;
        font-size: 56px;
        font-weight: 700;
        letter-spacing: -.06em;
        font-variant-numeric: tabular-nums;
    }

    .absen .clock .sec {
        margin-left: 2px;
        color: var(--absen-sky);
        font-size: 19px;
        font-weight: 600;
        letter-spacing: -.03em;
        opacity: .9;
    }

    .absen .tz {
        margin-bottom: 8px;
        color: var(--absen-plate-muted);
        font-size: 9px;
        font-weight: 700;
        letter-spacing: .12em;
    }

    .absen .plate-state {
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 3px 0 0;
        color: var(--absen-plate-muted);
        font-size: 11px;
        font-weight: 600;
    }

    .absen .state-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--absen-sky);
        box-shadow: 0 0 0 3px rgba(108, 198, 245, .16);
    }

    .absen .plate-state.is-wait .state-dot {
        background: #f0b45a;
        box-shadow: 0 0 0 3px rgba(240, 180, 90, .16);
    }

    .absen .plate-state.is-done {
        color: #a9e8c5;
    }

    .absen .plate-state.is-done .state-dot {
        background: #6fdd9d;
        box-shadow: 0 0 0 3px rgba(111, 221, 157, .16);
    }

    /* ===== Rel langkah ===== */
    .absen .rail {
        --rail-count: 4;
        --rail-inset: 12.5%;
        position: relative;
        display: grid;
        grid-template-columns: repeat(var(--rail-count), minmax(0, 1fr));
        gap: 4px;
        margin-top: 13px;
        padding-top: 14px;
        border-top: 1px solid rgba(255, 255, 255, .13);
    }

    .absen .rail::before {
        content: "";
        position: absolute;
        top: 23px;
        left: var(--rail-inset);
        right: var(--rail-inset);
        height: 1px;
        background: rgba(255, 255, 255, .17);
    }

    .absen .rail-step {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
        min-width: 0;
        text-align: center;
    }

    .absen .rail-dot {
        display: grid;
        place-items: center;
        width: 18px;
        height: 18px;
        border: 1.5px solid rgba(255, 255, 255, .30);
        border-radius: 50%;
        background: var(--absen-plate);
        color: rgba(255, 255, 255, .55);
        font-size: 8.5px;
        font-weight: 750;
        line-height: 1;
    }

    .absen .rail-dot .ic {
        width: 10px;
        height: 10px;
        stroke-width: 2.6;
    }

    .absen .rail-step.done .rail-dot {
        border-color: var(--absen-sky);
        background: var(--absen-sky);
        color: #06233b;
    }

    .absen .rail-step.now .rail-dot {
        border-color: var(--absen-sky);
        background: #fff;
        color: var(--absen-plate);
        box-shadow: 0 0 0 4px rgba(108, 198, 245, .18);
    }

    .absen .rail-step.now .rail-dot::after {
        content: "";
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: var(--mob-accent-solid);
    }

    .absen .rail-label {
        overflow: hidden;
        max-width: 100%;
        color: rgba(255, 255, 255, .52);
        font-size: 10.5px;
        font-weight: 650;
        line-height: 1.2;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .absen .rail-time {
        color: rgba(255, 255, 255, .56);
        font-size: 9.5px;
        font-weight: 550;
        font-variant-numeric: tabular-nums;
        line-height: 1;
    }

    .absen .rail-step.done .rail-label {
        color: rgba(255, 255, 255, .82);
    }

    .absen .rail-step.done .rail-time {
        color: rgba(255, 255, 255, .58);
    }

    .absen .rail-step.now .rail-label {
        color: #fff;
    }

    .absen .rail-step.now .rail-time {
        color: var(--absen-sky);
    }

    /* ===== Kartu aksi ===== */
    .absen .action-card,
    .absen .timeline-card,
    .absen .done-card {
        padding: 15px 16px;
        border: 1px solid var(--mob-rule);
        border-radius: 18px;
        background: var(--mob-surface);
        box-shadow: var(--absen-shadow);
    }

    .absen .action-kicker {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 7px;
    }

    .absen .eyebrow {
        color: var(--mob-muted);
        font-size: 10px;
        font-weight: 750;
        letter-spacing: .085em;
        text-transform: uppercase;
    }

    .absen .chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 7px;
        border: 1px solid var(--mob-rule);
        border-radius: 999px;
        background: var(--absen-soft);
        color: var(--mob-muted);
        font-size: 10px;
        font-weight: 700;
        line-height: 1;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .absen .chip.is-open {
        border-color: transparent;
        background: var(--mob-ok);
        color: var(--mob-surface);
    }

    html[data-bs-theme="dark"] .absen .chip.is-open {
        background: rgba(111, 221, 157, .16);
        color: var(--mob-ok);
    }

    .absen .chip.is-wait {
        border-color: transparent;
        background: var(--mob-warn);
        color: var(--mob-surface);
    }

    html[data-bs-theme="dark"] .absen .chip.is-wait {
        background: rgba(240, 180, 90, .16);
        color: var(--mob-warn);
    }

    .absen .action-title {
        margin: 0 0 3px;
        font-size: 20px;
        font-weight: 750;
        letter-spacing: -.025em;
        line-height: 1.15;
    }

    .absen .action-desc {
        margin: 0 0 10px;
        color: var(--mob-muted);
        font-size: 12px;
        line-height: 1.5;
    }

    .absen .history-strip {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 4px 8px;
        margin: 0 0 10px;
        padding: 7px 9px;
        border: 1px solid var(--mob-rule);
        border-radius: 9px;
        background: var(--absen-soft);
        color: var(--mob-muted);
        font-size: 11px;
        line-height: 1.4;
    }

    .absen .history-strip .hs-label {
        color: var(--mob-muted);
        font-size: 9px;
        font-weight: 750;
        letter-spacing: .07em;
        text-transform: uppercase;
    }

    .absen .history-strip .hs-text {
        color: var(--mob-ink);
        font-weight: 650;
        font-variant-numeric: tabular-nums;
    }

    /* ===== Foto ===== */
    .absen .photo {
        margin-top: 0;
    }

    .absen .photo-label.photo-slot {
        min-height: 0;
        gap: 9px;
        margin: 0 0 10px;
        padding: 9px 10px;
        border: 1px dashed var(--mob-rule-strong);
        border-radius: 11px;
        background: var(--absen-soft);
    }

    .absen .photo-label.photo-slot .cam {
        display: grid;
        place-items: center;
        width: 32px;
        height: 32px;
        flex: 0 0 auto;
        border-radius: 9px;
        background: var(--mob-accent-wash);
        color: var(--mob-accent-ink);
    }

    .absen .photo-label.photo-slot .cam .ic {
        width: 17px;
        height: 17px;
    }

    .absen .photo-label.photo-slot .photo-preview {
        width: 32px;
        height: 32px;
        border-radius: 9px;
    }

    .absen .photo-label.photo-slot .photo-title {
        font-size: 12px;
        font-weight: 700;
        line-height: 1.25;
    }

    .absen .photo-label.photo-slot .photo-hint {
        font-size: 10.5px;
        line-height: 1.25;
    }

    .absen .photo-label.has-photo .cam {
        display: none;
    }

    .absen .photo-label.photo-slot .chev {
        color: var(--mob-muted);
    }

    .absen .photo-label.photo-slot .chev .ic {
        width: 15px;
        height: 15px;
    }

    .absen .no-photo {
        display: flex;
        align-items: center;
        gap: 9px;
        margin: 0 0 10px;
        padding: 8px 10px;
        border: 1px solid var(--mob-rule);
        border-radius: 11px;
        background: var(--absen-soft);
    }

    .absen .no-photo .shield {
        display: grid;
        place-items: center;
        width: 32px;
        height: 32px;
        flex: 0 0 auto;
        border: 1px solid var(--mob-rule);
        border-radius: 9px;
        background: var(--mob-surface);
        color: var(--mob-accent-ink);
    }

    .absen .no-photo .shield .ic {
        width: 16px;
        height: 16px;
    }

    .absen .no-photo strong {
        display: block;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.25;
    }

    .absen .no-photo span {
        display: block;
        margin-top: 1px;
        color: var(--mob-muted);
        font-size: 10.5px;
        line-height: 1.25;
    }

    /* ===== Tombol aksi ===== */
    .absen .punch-btn {
        display: grid;
        grid-template-columns: 24px 1fr 24px;
        align-items: center;
        width: 100%;
        min-height: 58px;
        padding: 0 15px;
        border: 0;
        border-radius: 14px;
        background: var(--mob-accent-solid);
        color: #fff;
        font: inherit;
        font-size: 15.5px;
        font-weight: 750;
        letter-spacing: -.01em;
        cursor: pointer;
        box-shadow: 0 10px 22px -12px rgba(0, 116, 186, .72);
        transition: background-color .15s ease, transform .1s ease;
    }

    .absen .punch-btn:hover {
        background: var(--mob-accent-hover);
    }

    .absen .punch-btn:active {
        transform: translateY(1px);
    }

    .absen .punch-btn .btn-ic {
        display: grid;
        place-items: center;
    }

    .absen .punch-btn .btn-ic .ic {
        width: 19px;
        height: 19px;
    }

    .absen .punch-btn .btn-arrow {
        display: grid;
        place-items: center;
        justify-self: end;
        opacity: .9;
    }

    .absen .punch-btn .btn-arrow .ic {
        width: 17px;
        height: 17px;
    }

    .absen .punch-btn[disabled] {
        border: 1px solid var(--mob-rule);
        background: var(--absen-soft-2);
        color: var(--mob-muted);
        box-shadow: none;
        cursor: not-allowed;
    }

    .absen .punch-btn[disabled] .btn-ic .ic {
        color: var(--mob-muted);
    }

    .absen .punch-hint {
        display: flex;
        align-items: flex-start;
        gap: 5px;
        margin: 8px 1px 0;
        color: var(--mob-muted);
        font-size: 10.5px;
        line-height: 1.4;
    }

    .absen .punch-hint .ic {
        width: 12px;
        height: 12px;
        margin-top: 1px;
    }

    .absen .meta-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 5px 10px;
        margin-top: 10px;
        color: var(--mob-muted);
        font-size: 10.5px;
        font-weight: 550;
    }

    .absen .meta-item {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .absen .meta-item .ic {
        width: 12px;
        height: 12px;
    }

    .absen .meta-item .live-dot {
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: var(--mob-ok);
    }

    .absen .form-absen .lihat-peta {
        min-height: 34px;
        margin-top: 2px;
        padding: 0;
        font-size: 10.5px;
    }

    .absen .form-absen .msg {
        min-height: 1.1rem;
        margin-top: 7px;
        font-size: 11px;
    }

    /* ===== Timeline ===== */
    .absen .timeline-card {
        padding-bottom: 13px;
    }

    .absen .sec-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 11px;
    }

    .absen .sec-head h3 {
        margin: 0;
        color: var(--mob-muted);
        font-size: 10px;
        font-weight: 750;
        letter-spacing: .085em;
        text-transform: uppercase;
    }

    .absen .sec-head .count {
        color: var(--mob-muted);
        font-size: 10.5px;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    .absen .timeline {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .absen .timeline li {
        position: relative;
        display: grid;
        grid-template-columns: 25px minmax(0, 1fr) auto;
        align-items: start;
        gap: 9px;
        padding-bottom: 13px;
    }

    .absen .timeline li:last-child {
        padding-bottom: 0;
    }

    .absen .timeline li:not(:last-child)::before {
        content: "";
        position: absolute;
        top: 23px;
        bottom: 0;
        left: 12px;
        width: 1px;
        background: var(--mob-rule);
    }

    .absen .node {
        display: grid;
        place-items: center;
        width: 25px;
        height: 25px;
        border: 1px solid transparent;
        border-radius: 50%;
        background: var(--mob-accent-wash);
        color: var(--mob-accent-ink);
    }

    .absen .node .ic {
        width: 12px;
        height: 12px;
        stroke-width: 2.4;
    }

    .absen .node.is-done {
        background: var(--mob-ok);
        color: var(--mob-surface);
    }

    html[data-bs-theme="dark"] .absen .node.is-done {
        background: rgba(111, 221, 157, .16);
        color: var(--mob-ok);
    }

    .absen .event {
        min-width: 0;
        padding-top: 1px;
    }

    .absen .event-top {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 8px;
    }

    .absen .event-top strong {
        overflow: hidden;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: -.01em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .absen .event-top time {
        flex: 0 0 auto;
        color: var(--mob-ink);
        font-size: 11.5px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .absen .event p {
        margin: 1px 0 0;
        color: var(--mob-muted);
        font-size: 10.5px;
        line-height: 1.4;
    }

    .absen .thumb {
        display: grid;
        place-items: center;
        width: 36px;
        height: 36px;
        overflow: hidden;
        border: 1px solid var(--mob-rule);
        border-radius: 9px;
        background: var(--absen-soft);
        color: var(--mob-muted);
        text-decoration: none;
    }

    .absen .thumb img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .absen .thumb.no-photo .ic {
        width: 15px;
        height: 15px;
    }

    .absen .empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
        padding: 12px 8px 8px;
        text-align: center;
    }

    .absen .empty .empty-ic {
        display: grid;
        place-items: center;
        width: 38px;
        height: 38px;
        margin-bottom: 2px;
        border: 1px dashed var(--mob-rule-strong);
        border-radius: 12px;
        background: var(--absen-soft);
        color: var(--mob-muted);
    }

    .absen .empty .empty-ic .ic {
        width: 18px;
        height: 18px;
    }

    .absen .empty strong {
        color: var(--mob-ink);
        font-size: 11.5px;
        font-weight: 700;
    }

    .absen .empty p {
        margin: 0;
        color: var(--mob-muted);
        font-size: 10.5px;
        line-height: 1.4;
    }

    /* ===== Selesai ===== */
    .absen .done-card {
        border-top: 3px solid var(--mob-ok);
    }

    .absen .done-head {
        display: flex;
        align-items: center;
        gap: 9px;
    }

    .absen .done-ic {
        display: grid;
        place-items: center;
        width: 36px;
        height: 36px;
        flex: 0 0 auto;
        border-radius: 11px;
        background: var(--mob-ok);
        color: var(--mob-surface);
    }

    html[data-bs-theme="dark"] .absen .done-ic {
        background: rgba(111, 221, 157, .16);
        color: var(--mob-ok);
    }

    .absen .done-ic .ic {
        width: 19px;
        height: 19px;
        stroke-width: 2.4;
    }

    .absen .done-copy {
        min-width: 0;
        flex: 1 1 auto;
    }

    .absen .done-copy h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 750;
        letter-spacing: -.02em;
        line-height: 1.2;
    }

    .absen .done-copy p {
        margin: 2px 0 0;
        color: var(--mob-muted);
        font-size: 10.5px;
        font-variant-numeric: tabular-nums;
        line-height: 1.35;
    }

    .absen .done-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 7px;
        margin-top: 11px;
    }

    .absen .metric {
        padding: 7px 8px;
        border: 1px solid var(--mob-rule);
        border-radius: 9px;
        background: var(--absen-soft);
    }

    .absen .metric b {
        display: block;
        font-size: 13px;
        font-weight: 750;
        letter-spacing: -.02em;
        font-variant-numeric: tabular-nums;
        line-height: 1.2;
    }

    .absen .metric span {
        display: block;
        margin-top: 1px;
        color: var(--mob-muted);
        font-size: 9px;
        font-weight: 600;
        letter-spacing: .03em;
        text-transform: uppercase;
    }

    .absen .done-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-top: 10px;
        padding-top: 9px;
        border-top: 1px solid var(--mob-rule);
        color: var(--mob-muted);
        font-size: 10.5px;
    }

    .absen .done-foot span {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .absen .done-foot span .ic {
        width: 12px;
        height: 12px;
        color: var(--mob-ok);
    }

    .absen .photo-links {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 6px;
        margin-top: 10px;
    }

    .absen .photo-link {
        display: inline-flex;
        align-items: center;
        min-height: 30px;
        padding: 0 9px;
        border: 1px solid var(--mob-rule);
        border-radius: 8px;
        background: var(--absen-soft);
        color: var(--mob-accent-ink);
        font-size: 10.5px;
        font-weight: 650;
        text-decoration: none;
    }

    .absen .photo-link:hover {
        border-color: var(--mob-accent-solid);
        background: var(--mob-accent-wash);
    }

    .absen .app-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 0 2px;
        color: var(--mob-muted);
        font-size: 10px;
    }

    .absen .app-foot span {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .absen .app-foot .ic {
        width: 12px;
        height: 12px;
    }

    .absen .app-foot .foot-note {
        font-weight: 600;
    }

    @media (max-width: 380px) {
        .absen .clock {
            font-size: 50px;
        }

        .absen .clock .sec {
            font-size: 17px;
        }

        .absen .clock-card {
            padding-left: 13px;
            padding-right: 13px;
        }

        .absen .action-title {
            font-size: 19px;
        }

        .absen .rail-label {
            font-size: 10px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .absen .pulse {
            animation: none;
        }
    }
</style>
