<?php
$bulan = [
    1 => "Jan",
    2 => "Feb",
    3 => "Mar",
    4 => "Apr",
    5 => "Mei",
    6 => "Jun",
    7 => "Jul",
    8 => "Agu",
    9 => "Sep",
    10 => "Okt",
    11 => "Nov",
    12 => "Des",
];
$hari = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
$ts = strtotime($hariIni);
$tanggal =
    $hari[(int) date("w", $ts)] .
    ", " .
    date("j", $ts) .
    " " .
    $bulan[(int) date("n", $ts)] .
    " " .
    date("Y", $ts);

$zonaPresensi = new \DateTimeZone(config('Presensi')->zonaWaktu);
$sekarang     = new \DateTime('now', $zonaPresensi);
$jamSekarang  = $sekarang->format('H:i:s');
$jamSapaan    = (int) $sekarang->format('G');
$sapaan       = $jamSapaan < 11
    ? 'Selamat pagi'
    : ($jamSapaan < 15 ? 'Selamat siang' : ($jamSapaan < 19 ? 'Selamat sore' : 'Selamat malam'));

$kataNama = preg_split('/\s+/', trim((string) $nama)) ?: [];
$inisial  = '';
foreach (array_slice($kataNama, 0, 2) as $kata) {
    if ($kata === '') {
        continue;
    }
    $inisial .= function_exists('mb_substr') ? mb_substr($kata, 0, 1) : substr($kata, 0, 1);
}
$inisial = strtoupper($inisial !== '' ? $inisial : '?');

// Peta titik absen hanya berguna bila unit punya titik dan radius.
$petaSiap =
    is_numeric($unit->LATITUDE ?? null) &&
    is_numeric($unit->LONGTITUDE ?? null) &&
    (int) ($unit->RADIUS ?? 0) > 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Absen</title>
    <?= view("inc/_presensi_mobile_theme") ?>
    <?= view("absen/_theme") ?>
    <?php if ($petaSiap): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .peta-overlay {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding: 16px;
            background: rgba(10, 18, 28, .55);
        }

        .peta-overlay[hidden] {
            display: none;
        }

        .peta-panel {
            display: grid;
            gap: 10px;
            width: 100%;
            max-width: 448px;
            padding: 14px;
            border-radius: 14px;
            background: var(--mob-surface);
            color: var(--mob-ink);
        }

        .peta-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .peta-title {
            margin: 0;
            font-size: .9375rem;
            font-weight: 650;
        }

        .peta-tutup {
            width: 40px;
            height: 40px;
            border: 1px solid var(--mob-rule-strong);
            border-radius: 10px;
            background: transparent;
            color: var(--mob-ink);
            font: inherit;
            font-size: 1.25rem;
            line-height: 1;
            cursor: pointer;
        }

        .peta-canvas {
            height: 260px;
            border-radius: 10px;
            overflow: hidden;
        }

        .peta-info {
            margin: 0;
            font-size: .8125rem;
            color: var(--mob-muted);
            font-variant-numeric: tabular-nums;
        }

        /* `.mob .link` memaksa display, jadi atribut hidden butuh aturan sendiri. */
        .mob .lihat-peta {
            border: 0;
            background: transparent;
            font-family: inherit;
            cursor: pointer;
        }

        .mob .lihat-peta[hidden] {
            display: none;
        }
    </style>
    <?php endif; ?>
</head>
<body>

<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true">
    <symbol id="i-pin" viewBox="0 0 24 24"><path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/></symbol>
    <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M8 3.5v3M16 3.5v3M3.5 9.5h17"/></symbol>
    <symbol id="i-camera" viewBox="0 0 24 24"><path d="M3 8.5A2.5 2.5 0 0 1 5.5 6h1.2a1 1 0 0 0 .8-.4l.9-1.2a1.5 1.5 0 0 1 1.2-.6h4.8a1.5 1.5 0 0 1 1.2.6l.9 1.2a1 1 0 0 0 .8.4h1.2A2.5 2.5 0 0 1 21 8.5v8A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5z"/><circle cx="12" cy="12.5" r="3.2"/></symbol>
    <symbol id="i-lock" viewBox="0 0 24 24"><rect x="4.5" y="10" width="15" height="10.5" rx="2.5"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12.5 4.2 4.2L19 7"/></symbol>
    <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
    <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7v5.2l3.2 2"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3.5 19 6v5.2c0 4.3-2.9 7.5-7 9.3-4.1-1.8-7-5-7-9.3V6z"/><path d="m9 12 2 2 4-4"/></symbol>
    <symbol id="i-pause" viewBox="0 0 24 24"><rect x="6.5" y="5.5" width="3.6" height="13" rx="1.2"/><rect x="13.9" y="5.5" width="3.6" height="13" rx="1.2"/></symbol>
</svg>

<main class="mob absen"<?php if ($petaSiap): ?> data-titik-lat="<?= esc(
     $unit->LATITUDE,
 ) ?>" data-titik-lng="<?= esc(
    $unit->LONGTITUDE,
) ?>" data-titik-radius="<?= (int) $unit->RADIUS ?>"<?php endif; ?>>
    <header class="head">
        <span class="avatar" aria-hidden="true"><?= esc($inisial) ?></span>
        <div class="head-main">
            <p class="hello"><?= esc($sapaan) ?>,</p>
            <h1><?= esc($nama) ?></h1>
            <p class="meta"><?= esc(
                $unit->NAMA_UNIT ?? "Unit tidak dikenal",
            ) ?><span class="sep" aria-hidden="true">·</span><?= esc(
    $tanggal,
) ?></p>
        </div>
        <a class="ghost" href="<?= base_url("pengajuan_izin") ?>"><svg class="ic"><use href="#i-calendar"/></svg> Ajukan izin</a>
    </header>

    <?php if ($hariIni < $goLive): ?>
        <section class="sheet">
            <h2 class="state-title">Belum bisa absen</h2>
            <p class="state-text">Absen mobile aktif mulai <strong><?= esc(
                $goLive,
            ) ?></strong>.</p>
        </section>
    <?php elseif ($roster === null): ?>
        <section class="sheet">
            <h2 class="state-title">Belum ada jadwal</h2>
            <p class="state-text">Hubungi admin cabang untuk jadwal shift hari ini.</p>
        </section>
    <?php elseif ($roster->shift === "OFF"): ?>
        <section class="sheet">
            <h2 class="state-title">OFF</h2>
            <p class="state-text">Hari ini libur. Tidak perlu absen.</p>
        </section>
    <?php elseif (in_array($roster->shift, ["IZIN", "SAKIT", "CUTI"], true)): ?>
        <section class="sheet">
            <h2 class="state-title"><?= esc($roster->shift) ?></h2>
            <p class="state-text"><?= esc(
                trim((string) ($roster->keterangan ?? "")) ?: "Tidak perlu absen.",
            ) ?></p>
        </section>
    <?php else: ?>
        <?php foreach ($sesi as $s): ?>
            <?php
            $langkah     = (string) $s['langkah'];
            $rel         = $s['rel'];
            $jmlRel      = count($rel);
            $langkahIndex = (int) $s['langkah_index'];
            $terkunci    = in_array($langkah, ['break', 'pulang'], true) && ! $s['boleh_tutup'];
            $railInset   = rtrim(rtrim(number_format(50 / max(1, $jmlRel), 2, '.', ''), '0'), '.');
            $jamInBreak  = $rel[2]['jam'] ?? '';

            if ($langkah === 'selesai') {
                $plateState = 'Shift selesai';
                $plateClass = 'is-done';
            } elseif ($langkah === 'masuk') {
                $plateState = 'Belum masuk';
                $plateClass = '';
            } elseif ($langkah === 'break') {
                $plateState = $s['boleh_tutup'] ? 'Waktunya break' : 'Menunggu break';
                $plateClass = $s['boleh_tutup'] ? '' : 'is-wait';
            } elseif ($langkah === 'masuk_lagi') {
                $plateState = 'Siap in break';
                $plateClass = '';
            } else {
                $plateState = $s['boleh_tutup'] ? 'Waktunya pulang' : 'Menunggu pulang';
                $plateClass = $s['boleh_tutup'] ? '' : 'is-wait';
            }

            $judul = [
                'masuk'      => 'Mulai shift',
                'break'      => 'Break siang',
                'masuk_lagi' => 'In break',
                'pulang'     => 'Pulang',
            ][$langkah] ?? 'Absen';

            $deskripsi = [
                'masuk'      => 'Pastikan seragam dan ID card terlihat jelas, serta kamu berada di titik toko.',
                'break'      => 'Tutup sesi pagi untuk mulai break. Break tanpa foto, tapi lokasi tetap dicek.',
                'masuk_lagi' => 'Masuk kembali setelah break. Foto wajib; pulang baru bisa pukul ' . $s['pulang'] . '.',
                'pulang'     => 'Absen pulang dengan foto. Tombol aktif tepat pukul ' . $s['pulang'] . '.',
            ][$langkah] ?? '';

            $aksi = $langkah === 'break'
                ? 'absen/break'
                : ($langkah === 'pulang' ? 'absen/pulang' : 'absen/masuk');
            $butuhFoto = $langkah !== 'break';
            $labelTombol = [
                'masuk'      => 'Absen Masuk',
                'break'      => 'Absen Break',
                'masuk_lagi' => 'Absen In Break',
                'pulang'     => 'Absen Pulang',
            ][$langkah] ?? 'Absen';
            $ikonTombol = $langkah === 'break' ? 'i-pause' : 'i-camera';
            $idFoto = 'foto-' . strtolower((string) $s['kode']) . '-' . (int) ($s['presensi']->idpresensi ?? 0);
            $events = $s['timeline'];
            $jmlSelesai = count($events);
            $jamMasuk = $events[0]['waktu'] ?? '--:--';
            $jamPulang = $events !== [] ? $events[array_key_last($events)]['waktu'] : '--:--';
            ?>
            <article class="shift-card" data-langkah="<?= esc($langkah, 'attr') ?>">
                <section class="clock-card" aria-label="Jam dan progres shift">
                    <svg class="ic plate-mark"><use href="#i-clock"/></svg>
                    <div class="plate-top">
                        <span class="shift-badge"><span class="pulse"></span><?= esc(
                            $s['label'],
                        ) ?> <span class="range"><?= esc(
    $s['rentang'],
) ?></span></span>
                    </div>
                    <div class="clock-line">
                        <div class="clock" data-base="<?= esc($jamSekarang, 'attr') ?>"><span class="hm"><?= esc(
                            substr($jamSekarang, 0, 5),
                        ) ?></span><span class="sec">:<?= esc(
    substr($jamSekarang, 6, 2),
) ?></span></div>
                        <span class="tz">WIB</span>
                    </div>
                    <p class="plate-state <?= esc($plateClass, 'attr') ?>"><span class="state-dot"></span> <?= esc(
                        $plateState,
                    ) ?></p>

                    <div class="rail" style="--rail-count: <?= (int) $jmlRel ?>; --rail-inset: <?= esc(
                        $railInset,
                        'attr',
                    ) ?>%;" aria-label="Progres shift">
                        <?php foreach ($rel as $i => $step): ?>
                            <?php
                            $status = $i < $langkahIndex ? 'done' : ($i === $langkahIndex ? 'now' : 'todo');
                            ?>
                            <div class="rail-step <?= $status ?>"<?= $status === 'now' ? ' aria-current="step"' : '' ?>>
                                <span class="rail-dot"><?php if ($status === 'done'): ?><svg class="ic"><use href="#i-check"/></svg><?php elseif ($status === 'now'): ?><?php else: ?><?= $i + 1 ?><?php endif; ?></span>
                                <span class="rail-label"><?= esc($step['label']) ?></span>
                                <span class="rail-time"><?= esc($step['jam']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <?php if ($langkah === 'selesai'): ?>
                    <section class="done-card" aria-label="Ringkasan shift selesai">
                        <div class="done-head">
                            <span class="done-ic"><svg class="ic"><use href="#i-check"/></svg></span>
                            <div class="done-copy">
                                <h3>Shift selesai</h3>
                                <p><?= esc($s['ringkasan']) ?></p>
                            </div>
                            <span class="chip is-open">Selesai</span>
                        </div>
                        <div class="done-grid">
                            <div class="metric"><b><?= esc($jamMasuk) ?></b><span>Masuk</span></div>
                            <div class="metric"><b><?= esc($jamPulang) ?></b><span>Pulang</span></div>
                            <div class="metric"><b><?= esc($jmlSelesai) ?>/<?= esc($jmlRel) ?></b><span>Langkah</span></div>
                        </div>
                        <?php if ($s['foto'] !== []): ?>
                            <div class="photo-links">
                                <?php foreach ($s['foto'] as $f): ?>
                                    <a class="photo-link" href="<?= base_url($f['url']) ?>"><?= esc(
                                        $f['label'],
                                    ) ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="done-foot">
                            <span><svg class="ic"><use href="#i-check"/></svg> Semua langkah lengkap</span>
                            <span><?= count($s['foto']) ?> foto</span>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="action-card" aria-label="Aksi berikutnya">
                        <div class="action-kicker">
                            <span class="eyebrow">Langkah berikutnya</span>
                            <?php if ($terkunci): ?>
                                <span class="chip is-wait" data-hitung="<?= esc(
                                    $s['buka'],
                                    'attr',
                                ) ?>">Buka <?= esc($s['pulang']) ?></span>
                            <?php else: ?>
                                <span class="chip is-open">Bisa sekarang</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="action-title"><?= esc($judul) ?></h3>
                        <p class="action-desc"><?= esc($deskripsi) ?></p>

                        <div class="history-strip">
                            <span class="hs-label">Sudah</span>
                            <span class="hs-text"><?= $s['ringkasan'] !== ''
                                ? esc($s['ringkasan'])
                                : 'Belum ada absen hari ini' ?></span>
                        </div>

                        <form class="form-absen" method="post" action="<?= base_url(
                            $aksi,
                        ) ?>"<?= $butuhFoto ? ' enctype="multipart/form-data"' : '' ?>>
                            <?= csrf_field() ?>
                            <?php if ($langkah === 'masuk' || $langkah === 'masuk_lagi'): ?>
                                <input type="hidden" name="sesi" value="<?= esc(
                                    $s['kode'],
                                ) ?>">
                            <?php else: ?>
                                <input type="hidden" name="idpresensi" value="<?= (int) $s[
                                    'presensi'
                                ]->idpresensi ?>">
                            <?php endif; ?>
                            <input type="hidden" name="latitude">
                            <input type="hidden" name="longitude">

                            <?php if ($langkah === 'break'): ?>
                                <div class="no-photo">
                                    <span class="shield"><svg class="ic"><use href="#i-shield"/></svg></span>
                                    <span class="copy">
                                        <strong>Tanpa foto</strong>
                                        <span>GPS tetap dicek · in break berikutnya <?= esc(
                                            $jamInBreak,
                                        ) ?></span>
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="photo">
                                    <input id="<?= esc($idFoto, 'attr') ?>" type="file" name="foto" accept="image/*" capture="environment">
                                    <label class="photo-label photo-slot" for="<?= esc(
                                        $idFoto,
                                        'attr',
                                    ) ?>">
                                        <span class="cam"><svg class="ic"><use href="#i-camera"/></svg></span>
                                        <span class="photo-preview" role="img" aria-label="Pratinjau foto"></span>
                                        <span class="photo-text">
                                            <span class="photo-title"><?= $langkah === 'masuk'
                                                ? 'Ambil foto masuk'
                                                : ($langkah === 'masuk_lagi'
                                                    ? 'Ambil foto in break'
                                                    : 'Ambil foto pulang') ?></span>
                                            <span class="photo-hint">Kamera belakang &middot; wajib</span>
                                        </span>
                                        <span class="chev"><svg class="ic"><use href="#i-chevron"/></svg></span>
                                    </label>
                                </div>
                            <?php endif; ?>

                            <button class="punch-btn" type="submit"<?= $terkunci
                                ? ' data-buka="' . esc($s['buka'], 'attr') . '" disabled'
                                : '' ?>>
                                <span class="btn-ic"><svg class="ic"><use href="#<?= $terkunci
                                    ? 'i-lock'
                                    : $ikonTombol ?>"/></svg></span>
                                <span class="btn-label"><?= esc($labelTombol) ?></span>
                                <span class="btn-arrow"><svg class="ic"><use href="#<?= $terkunci
                                    ? 'i-lock'
                                    : 'i-arrow' ?>"/></svg></span>
                            </button>

                            <?php if ($terkunci && $langkah === 'break'): ?>
                                <p class="punch-hint"><svg class="ic"><use href="#i-clock"/></svg> Break tanpa foto, bisa mulai <?= esc(
                                    $s['pulang'],
                                ) ?>.</p>
                            <?php elseif ($terkunci && $langkah === 'pulang'): ?>
                                <p class="punch-hint"><svg class="ic"><use href="#i-clock"/></svg> Pulang bisa mulai <?= esc(
                                    $s['pulang'],
                                ) ?>.</p>
                            <?php elseif ($langkah === 'masuk_lagi'): ?>
                                <p class="punch-hint"><svg class="ic"><use href="#i-lock"/></svg> Pulang baru aktif pukul <?= esc(
                                    $s['pulang'],
                                ) ?>.</p>
                            <?php endif; ?>

                            <p class="msg" role="status" aria-live="polite"></p>
                            <?php if ($petaSiap): ?>
                                <button type="button" class="link lihat-peta" hidden>Lihat titik absen</button>
                            <?php endif; ?>
                        </form>

                        <div class="meta-row">
                            <span class="meta-item"><span class="live-dot"></span> GPS titik toko</span>
                            <?php if ($langkah === 'break'): ?>
                                <span class="meta-item"><svg class="ic"><use href="#i-shield"/></svg> Tanpa foto</span>
                                <span class="meta-item"><svg class="ic"><use href="#i-clock"/></svg> In break <?= esc(
                                    $jamInBreak,
                                ) ?></span>
                            <?php elseif ($langkah === 'masuk'): ?>
                                <span class="meta-item"><svg class="ic"><use href="#i-camera"/></svg> Foto seragam</span>
                                <span class="meta-item"><svg class="ic"><use href="#i-clock"/></svg> Boleh kapan saja</span>
                            <?php elseif ($langkah === 'masuk_lagi'): ?>
                                <span class="meta-item"><svg class="ic"><use href="#i-camera"/></svg> Foto wajib</span>
                                <span class="meta-item"><svg class="ic"><use href="#i-clock"/></svg> Pulang <?= esc(
                                    $s['pulang'],
                                ) ?></span>
                            <?php else: ?>
                                <span class="meta-item"><svg class="ic"><use href="#i-camera"/></svg> Foto wajib</span>
                                <span class="meta-item"><svg class="ic"><use href="#i-clock"/></svg> Buka <?= esc(
                                    $s['pulang'],
                                ) ?></span>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="timeline-card" aria-label="Riwayat absen hari ini">
                    <div class="sec-head">
                        <h3>Absen hari ini</h3>
                        <span class="count"><?= $jmlSelesai ?> dari <?= $jmlRel ?></span>
                    </div>
                    <?php if ($events === []): ?>
                        <div class="empty">
                            <span class="empty-ic"><svg class="ic"><use href="#i-clock"/></svg></span>
                            <strong>Belum ada absen</strong>
                            <p>Mulai dengan Absen Masuk. Setiap langkah akan muncul di sini.</p>
                        </div>
                    <?php else: ?>
                        <ol class="timeline">
                            <?php foreach ($events as $ev): ?>
                                <li>
                                    <span class="node is-done"><svg class="ic"><use href="#i-check"/></svg></span>
                                    <div class="event">
                                        <div class="event-top"><strong><?= esc(
                                            $ev['label'],
                                        ) ?></strong><time><?= esc(
    $ev['waktu'],
) ?></time></div>
                                        <p><?php if ($ev['tanpa_foto']): ?>Tanpa foto<?= $ev['kode'] === 'break'
    ? ' · lokasi dicek'
    : '' ?><?php else: ?>Foto tersimpan<?= $ev['status']
    ? ' · ' . esc($ev['status'])
    : '' ?><?php endif; ?></p>
                                    </div>
                                    <?php if ($ev['foto']): ?>
                                        <a class="thumb" href="<?= base_url(
                                            $ev['foto'],
                                        ) ?>" aria-label="<?= esc(
    $ev['foto_label'] ?? 'Foto absen',
    'attr',
) ?>">
                                            <img src="<?= base_url(
                                                $ev['foto'],
                                            ) ?>" alt="" loading="lazy">
                                        </a>
                                    <?php else: ?>
                                        <span class="thumb no-photo" aria-hidden="true"><svg class="ic"><use href="#i-pause"/></svg></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>

                <footer class="app-foot">
                    <span><svg class="ic"><use href="#i-shield"/></svg> Lokasi dicek saat absen</span>
                    <span class="foot-note">Koreksi lewat admin</span>
                </footer>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($petaSiap): ?>
    <div class="peta-overlay" id="peta-lokasi" hidden>
        <div class="peta-panel" role="dialog" aria-modal="true" aria-labelledby="peta-judul">
            <div class="peta-head">
                <p class="peta-title" id="peta-judul">Titik absen</p>
                <button type="button" class="peta-tutup" aria-label="Tutup peta">&times;</button>
            </div>
            <div class="peta-canvas" id="peta-canvas"></div>
            <p class="peta-info" id="peta-info"></p>
            <a class="link" id="peta-gmaps" href="#" target="_blank" rel="noopener">Buka di Google Maps</a>
        </div>
    </div>
    <?php endif; ?>
</main>

<script>
(function () {
    var main    = document.querySelector('main.mob');
    var overlay = document.getElementById('peta-lokasi');
    var peta    = null;
    var TITIK   = main && main.dataset.titikLat ? {
        lat: parseFloat(main.dataset.titikLat),
        lng: parseFloat(main.dataset.titikLng),
        radius: parseFloat(main.dataset.titikRadius)
    } : null;

    function tampilkanPeta(form) {
        if (!TITIK || !overlay || !window.L) return;

        var latInput = form.querySelector('input[name=latitude]');
        var lngInput = form.querySelector('input[name=longitude]');
        var uLat = parseFloat(latInput.value);
        var uLng = parseFloat(lngInput.value);
        if (isNaN(uLat) || isNaN(uLng)) return;

        overlay.hidden = false;
        if (peta) {
            peta.remove();
            peta = null;
        }

        peta = L.map('peta-canvas').setView([TITIK.lat, TITIK.lng], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(peta);

        L.circle([TITIK.lat, TITIK.lng], {
            radius: TITIK.radius, color: '#0074ba', weight: 1, fillOpacity: .08
        }).addTo(peta);
        L.marker([TITIK.lat, TITIK.lng]).addTo(peta).bindPopup('Titik absen').openPopup();
        L.marker([uLat, uLng]).addTo(peta).bindPopup('Posisi Anda');
        L.polyline([[TITIK.lat, TITIK.lng], [uLat, uLng]], {
            color: '#0074ba', dashArray: '4'
        }).addTo(peta);

        peta.fitBounds(
            L.latLng(TITIK.lat, TITIK.lng).toBounds(TITIK.radius * 2).extend([uLat, uLng]),
            { padding: [24, 24] }
        );

        document.getElementById('peta-info').textContent =
            'Jarak Anda ' + Math.round(L.latLng(TITIK.lat, TITIK.lng).distanceTo([uLat, uLng]))
            + ' m dari titik absen (maks ' + Math.round(TITIK.radius) + ' m).';
        document.getElementById('peta-gmaps').href =
            'https://www.google.com/maps?q=' + TITIK.lat + ',' + TITIK.lng;

        overlay.querySelector('.peta-tutup').focus();
    }

    if (overlay) {
        var tutupPeta = function () { overlay.hidden = true; };
        overlay.querySelector('.peta-tutup').addEventListener('click', tutupPeta);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) tutupPeta();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !overlay.hidden) tutupPeta();
        });
    }

    function ambilLokasi() {
        return new Promise(function (resolve, reject) {
            if (!navigator.geolocation) {
                reject(new Error('Perangkat tidak mendukung GPS.'));
                return;
            }
            navigator.geolocation.getCurrentPosition(
                function (pos) { resolve(pos.coords); },
                function () { reject(new Error('Izin lokasi ditolak atau GPS tidak tersedia.')); },
                { enableHighAccuracy: true, timeout: 15000 }
            );
        });
    }

    function kompres(file) {
        return createImageBitmap(file).then(function (bmp) {
            var skala = Math.min(1, 1280 / Math.max(bmp.width, bmp.height));
            var canvas = document.createElement('canvas');
            canvas.width = Math.round(bmp.width * skala);
            canvas.height = Math.round(bmp.height * skala);
            canvas.getContext('2d').drawImage(bmp, 0, 0, canvas.width, canvas.height);

            return new Promise(function (resolve, reject) {
                canvas.toBlob(function (blob) {
                    blob ? resolve(blob) : reject(new Error('Foto gagal diproses.'));
                }, 'image/jpeg', 0.8);
            });
        }).catch(function () { throw new Error('Foto tidak dapat dibaca. Coba ambil ulang.'); });
    }

    document.querySelectorAll('form.form-absen').forEach(function (form) {
        var input   = form.querySelector('input[type=file]');
        var label   = form.querySelector('.photo-label');
        var preview = form.querySelector('.photo-preview');
        var hint    = form.querySelector('.photo-hint');
        var petaBtn = form.querySelector('.lihat-peta');

        if (petaBtn) {
            petaBtn.addEventListener('click', function () { tampilkanPeta(form); });
        }

        if (input) {
            input.addEventListener('change', function () {
                var file = input.files && input.files[0];
                if (!file) return;

                if (preview.dataset.url) URL.revokeObjectURL(preview.dataset.url);
                preview.dataset.url = URL.createObjectURL(file);
                preview.style.backgroundImage = 'url("' + preview.dataset.url + '")';
                label.classList.add('has-photo');
                hint.textContent = 'Foto siap dikirim';
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (form.dataset.sibuk === '1') return;

            var pesan = form.querySelector('.msg');
            var tombol = form.querySelector('button[type=submit]');
            pesan.className = 'msg';
            pesan.textContent = 'Mengambil lokasi...';
            if (petaBtn) petaBtn.hidden = true;

            if (input && !input.files[0]) {
                pesan.className = 'msg is-err';
                pesan.textContent = 'Foto wajib diambil.';
                return;
            }

            form.dataset.sibuk = '1';
            tombol.disabled = true;

            ambilLokasi().then(function (coords) {
                form.querySelector('input[name=latitude]').value = coords.latitude;
                form.querySelector('input[name=longitude]').value = coords.longitude;

                return input ? kompres(input.files[0]) : null;
            }).then(function (blob) {
                var data = new FormData(form);
                if (blob) data.set('foto', blob, 'absen.jpg');
                pesan.textContent = 'Mengirim absen...';

                return fetch(form.action, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
            }).then(function (res) {
                return res.json().catch(function () { return { ok: false, message: 'Respons server tidak dikenali.' }; });
            }).then(function (json) {
                if (json.ok) {
                    pesan.className = 'msg is-ok';
                    pesan.textContent = json.message || 'Berhasil.';
                    setTimeout(function () { window.location.reload(); }, 600);
                    return;
                }
                if (json.kode === 'jarak' && petaBtn) petaBtn.hidden = false;
                throw new Error(json.message || 'Absen gagal.');
            }).catch(function (err) {
                pesan.className = 'msg is-err';
                pesan.textContent = err.message;
                form.dataset.sibuk = '0';
                tombol.disabled = false;
            });
        });
    });

    setInterval(function () {
        document.querySelectorAll('button[data-buka]').forEach(function (btn) {
            if (new Date() < new Date(btn.dataset.buka)) return;
            btn.disabled = false;
            btn.removeAttribute('data-buka');
        });
    }, 15000);

    var jamEls = document.querySelectorAll('.clock[data-base]');
    var mulai  = Date.now();

    function dua(n) {
        return String(n).padStart(2, '0');
    }

    function perbaruiJam() {
        var lewat = Math.floor((Date.now() - mulai) / 1000);
        jamEls.forEach(function (el) {
            var p = el.dataset.base.split(':').map(Number);
            var total = (p[0] * 3600 + p[1] * 60 + p[2] + lewat) % 86400;
            el.querySelector('.hm').textContent =
                dua(Math.floor(total / 3600)) + ':' + dua(Math.floor(total / 60) % 60);
            el.querySelector('.sec').textContent = ':' + dua(total % 60);
        });
    }

    function perbaruiHitung() {
        document.querySelectorAll('[data-hitung]').forEach(function (el) {
            var selisih = new Date(el.dataset.hitung).getTime() - Date.now();
            if (selisih <= 0) {
                el.textContent = 'Bisa sekarang';
                el.classList.remove('is-wait');
                el.classList.add('is-open');
                return;
            }
            var menit = Math.floor(selisih / 60000);
            var detik = Math.floor(selisih / 1000) % 60;
            el.textContent = 'Buka dalam ' + menit + ':' + dua(detik);
        });
    }

    perbaruiJam();
    perbaruiHitung();
    setInterval(function () {
        perbaruiJam();
        perbaruiHitung();
    }, 1000);
})();
</script>
</body>
</html>
