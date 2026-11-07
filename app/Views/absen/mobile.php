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
    <symbol id="i-camera" viewBox="0 0 24 24"><path d="M3 8.5A2.5 2.5 0 0 1 5.5 6h1.2a1 1 0 0 0 .8-.4l.9-1.2a1.5 1.5 0 0 1 1.2-.6h4.8a1.5 1.5 0 0 1 1.2.6l.9 1.2a1 1 0 0 0 .8.4h1.2A2.5 2.5 0 0 1 21 8.5v8A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5z"/><circle cx="12" cy="12.5" r="3.2"/></symbol>
    <symbol id="i-lock" viewBox="0 0 24 24"><rect x="4.5" y="10" width="15" height="10.5" rx="2.5"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7v5.2l3.2 2"/></symbol>
    <symbol id="i-pause" viewBox="0 0 24 24"><rect x="6.5" y="5.5" width="3.6" height="13" rx="1.2"/><rect x="13.9" y="5.5" width="3.6" height="13" rx="1.2"/></symbol>
</svg>

<main class="mob absen"<?php if ($petaSiap): ?> data-titik-lat="<?= esc($unit->LATITUDE) ?>" data-titik-lng="<?= esc($unit->LONGTITUDE) ?>" data-titik-radius="<?= (int) $unit->RADIUS ?>"<?php endif; ?>>
    <header class="head">
        <span class="avatar" aria-hidden="true"><?= esc($inisial) ?></span>
        <div class="head-main">
            <h1><?= esc($nama) ?></h1>
            <p class="meta"><?= esc(
                $unit->NAMA_UNIT ?? "Unit tidak dikenal",
            ) ?><?php if ($kunjungan ?? false): ?><span class="visit">Kunjungan</span><?php endif; ?><span class="sep" aria-hidden="true">·</span><?= esc(
    $tanggal,
) ?></p>
        </div>
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
            $langkah      = (string) $s['langkah'];
            $rel          = $s['rel'];
            $jmlRel       = count($rel);
            $langkahIndex = (int) $s['langkah_index'];
            $bolehInBreak = (bool) ($s['boleh_in_break'] ?? true);
            $terkunci     = (in_array($langkah, ['break', 'pulang'], true) && ! $s['boleh_tutup'])
                || ($langkah === 'masuk_lagi' && ! $bolehInBreak);
            $bukaTerkunci = $langkah === 'masuk_lagi' ? ($s['buka_in_break'] ?? $s['buka']) : $s['buka'];
            $labelBuka    = $langkah === 'masuk_lagi' ? ($s['in_break'] ?? $s['pulang']) : $s['pulang'];
            $jamInBreak   = $rel[2]['jam'] ?? '';

            if ($langkah === 'selesai') {
                $plateState = 'Selesai';
                $plateClass = 'is-done';
            } elseif ($langkah === 'masuk') {
                $plateState = 'Belum masuk';
                $plateClass = '';
            } elseif ($langkah === 'break') {
                $plateState = $s['boleh_tutup'] ? 'Waktunya break' : 'Menunggu break';
                $plateClass = $s['boleh_tutup'] ? '' : 'is-wait';
            } elseif ($langkah === 'masuk_lagi') {
                $plateState = $bolehInBreak ? 'Siap in break' : 'Menunggu in break';
                $plateClass = $bolehInBreak ? '' : 'is-wait';
            } else {
                $plateState = $s['boleh_tutup'] ? 'Waktunya pulang' : 'Menunggu pulang';
                $plateClass = $s['boleh_tutup'] ? '' : 'is-wait';
            }

            $judul = [
                'masuk'      => 'Mulai shift',
                'break'      => 'Break siang',
                'masuk_lagi' => 'Masuk lagi',
                'pulang'     => 'Pulang',
            ][$langkah] ?? 'Absen';

            $deskripsi = [
                'masuk'      => 'Seragam & ID card terlihat · foto wajib.',
                'break'      => 'Tanpa foto · lokasi tetap dicek.',
                'masuk_lagi' => 'Foto wajib · pulang baru pukul ' . $s['pulang'] . '.',
                'pulang'     => 'Foto wajib · tombol aktif pukul ' . $s['pulang'] . '.',
            ][$langkah] ?? '';

            $aksi      = $langkah === 'break' ? 'absen/break' : ($langkah === 'pulang' ? 'absen/pulang' : 'absen/masuk');
            $butuhFoto = $langkah !== 'break';
            $labelTombol = [
                'masuk'      => 'Absen Masuk',
                'break'      => 'Absen Break',
                'masuk_lagi' => 'Absen In Break',
                'pulang'     => 'Absen Pulang',
            ][$langkah] ?? 'Absen';
            $ikonTombol = $langkah === 'break' ? 'i-pause' : 'i-camera';
            $idFoto     = 'foto-' . strtolower((string) $s['kode']) . '-' . (int) ($s['presensi']->idpresensi ?? 0);
            $events     = $s['timeline'];
            $jmlSelesai = count($events);
            $adaFoto    = array_filter($events, static fn ($ev) => $ev['foto'] !== null);
            ?>
            <article class="sheet shift" data-langkah="<?= esc($langkah, 'attr') ?>">
                <div class="plate-top">
                    <div class="plate-shift">
                        <p class="plate-name"><?= esc($s['label']) ?></p>
                        <p class="plate-state <?= esc($plateClass, 'attr') ?>"><span class="state-dot" aria-hidden="true"></span><?= esc(
                            $plateState,
                        ) ?></p>
                    </div>
                    <div class="clock-line">
                        <div class="clock" data-base="<?= esc($jamSekarang, 'attr') ?>"><span class="hm"><?= esc(
                            substr($jamSekarang, 0, 5),
                        ) ?></span><span class="sec">:<?= esc(substr($jamSekarang, 6, 2)) ?></span></div>
                        <span class="tz">WIB</span>
                    </div>
                </div>

                <p class="plate-range"><?= esc($s['rentang']) ?></p>

                <ol class="rail" style="--rail-count: <?= (int) $jmlRel ?>;" aria-label="Progres shift">
                    <?php foreach ($rel as $i => $step): ?>
                        <?php $status = $i < $langkahIndex ? 'done' : ($i === $langkahIndex ? 'now' : 'todo'); ?>
                        <li class="rail-step <?= $status ?>"<?= $status === 'now' ? ' aria-current="step"' : '' ?>>
                            <span class="rail-dot" aria-hidden="true"></span>
                            <span class="rail-label"><?= esc($step['label']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>

                <?php if ($langkah === 'selesai'): ?>
                    <section class="action is-done" aria-label="Ringkasan shift">
                        <div class="action-head">
                            <h2>Ringkasan shift</h2>
                        </div>
                        <p class="action-desc">Semua langkah lengkap · <?= count($adaFoto) ?> foto.</p>
                    </section>
                <?php else: ?>
                    <section class="action" aria-label="Aksi berikutnya">
                        <div class="action-head">
                            <h2><?= esc($judul) ?></h2>
                            <?php if ($terkunci): ?>
                                <span class="chip is-wait" data-hitung="<?= esc($bukaTerkunci, 'attr') ?>">Buka <?= esc(
                                    $labelBuka,
                                ) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="action-desc"><?= esc($deskripsi) ?></p>

                        <form class="form-absen" method="post" action="<?= base_url(
                            $aksi,
                        ) ?>"<?= $butuhFoto ? ' enctype="multipart/form-data"' : '' ?>>
                            <?= csrf_field() ?>
                            <?php if ($langkah === 'masuk' || $langkah === 'masuk_lagi'): ?>
                                <input type="hidden" name="sesi" value="<?= esc($s['kode']) ?>">
                            <?php else: ?>
                                <input type="hidden" name="idpresensi" value="<?= (int) $s[
                                    'presensi'
                                ]->idpresensi ?>">
                            <?php endif; ?>
                            <input type="hidden" name="latitude">
                            <input type="hidden" name="longitude">

                            <?php if ($langkah === 'break'): ?>
                                <p class="no-photo"><svg class="ic"><use href="#i-clock"/></svg> In break berikutnya <?= esc(
                                    $jamInBreak,
                                ) ?></p>
                            <?php else: ?>
                                <div class="photo">
                                    <input id="<?= esc($idFoto, 'attr') ?>" type="file" name="foto" accept="image/*" capture="environment">
                                    <label class="photo-label photo-slot" for="<?= esc($idFoto, 'attr') ?>">
                                        <span class="cam"><svg class="ic"><use href="#i-camera"/></svg></span>
                                        <span class="photo-preview" role="img" aria-label="Pratinjau foto"></span>
                                        <span class="photo-text">
                                            <span class="photo-title">Ambil foto</span>
                                            <span class="photo-hint">Kamera belakang</span>
                                        </span>
                                    </label>
                                </div>
                            <?php endif; ?>

                            <button class="punch-btn" type="submit"<?= $terkunci
                                ? ' data-buka="' . esc($bukaTerkunci, 'attr') . '" disabled'
                                : '' ?>>
                                <span class="btn-ic"><svg class="ic"><use href="#<?= $terkunci
                                    ? 'i-lock'
                                    : $ikonTombol ?>"/></svg></span>
                                <span class="btn-label"><?= esc($labelTombol) ?></span>
                            </button>

                            <p class="msg" role="status" aria-live="polite"></p>
                            <?php if ($petaSiap): ?>
                                <button type="button" class="link lihat-peta" hidden>Lihat titik absen</button>
                            <?php endif; ?>
                        </form>
                    </section>
                <?php endif; ?>

                <section class="today" aria-label="Absen hari ini">
                    <div class="today-head">
                        <h2>Hari ini</h2>
                        <span class="count"><?= $jmlSelesai ?> dari <?= $jmlRel ?></span>
                    </div>
                    <?php if ($s['ringkasan'] !== ''): ?>
                        <p class="today-line"><?= esc($s['ringkasan']) ?></p>
                    <?php else: ?>
                        <p class="today-line is-empty">Belum ada absen hari ini.</p>
                    <?php endif; ?>
                    <?php if ($adaFoto !== []): ?>
                        <div class="thumbs">
                            <?php foreach ($adaFoto as $ev): ?>
                                <a class="thumb" href="<?= base_url(
                                    $ev['foto'],
                                ) ?>" aria-label="<?= esc(
    $ev['foto_label'] ?? 'Foto absen',
    'attr',
) ?>">
                                    <img src="<?= base_url($ev['foto']) ?>" alt="" loading="lazy">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
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

<?= view('inc/_pwa_install') ?>

<?= view('inc/_mobile_nav', ['active' => 'absen']) ?>

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
            var jam = Math.floor(menit / 60);
            el.textContent = 'Buka dalam ' + (jam > 0 ? jam + ':' + dua(menit % 60) : menit) + ':' + dua(detik);
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
