<?php
$bulan = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
$hari  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$ts    = strtotime($hariIni);
$tanggal = $hari[(int) date('w', $ts)] . ', ' . date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);

// Peta titik absen hanya berguna bila unit punya titik dan radius.
$petaSiap = is_numeric($unit->LATITUDE ?? null)
    && is_numeric($unit->LONGTITUDE ?? null)
    && (int) ($unit->RADIUS ?? 0) > 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Absen</title>
    <?= view('inc/_presensi_mobile_theme') ?>
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
<main class="mob"<?php if ($petaSiap): ?> data-titik-lat="<?= esc($unit->LATITUDE) ?>" data-titik-lng="<?= esc($unit->LONGTITUDE) ?>" data-titik-radius="<?= (int) $unit->RADIUS ?>"<?php endif; ?>>
    <header class="head">
        <div class="head-main">
            <h1><?= esc($nama) ?></h1>
            <p class="meta"><?= esc($unit->NAMA_UNIT ?? 'Unit tidak dikenal') ?><span class="sep" aria-hidden="true">·</span><?= esc($tanggal) ?></p>
        </div>
        <a class="ghost" href="<?= base_url('pengajuan_izin') ?>">Ajukan izin</a>
    </header>

    <?php if ($hariIni < $goLive): ?>
        <section class="sheet">
            <h2 class="state-title">Belum bisa absen</h2>
            <p class="state-text">Absen mobile aktif mulai <strong><?= esc($goLive) ?></strong>.</p>
        </section>
    <?php elseif ($roster === null): ?>
        <section class="sheet">
            <h2 class="state-title">Belum ada jadwal</h2>
            <p class="state-text">Hubungi admin cabang untuk jadwal shift hari ini.</p>
        </section>
    <?php elseif ($roster->shift === 'OFF'): ?>
        <section class="sheet">
            <h2 class="state-title">OFF</h2>
            <p class="state-text">Hari ini libur. Tidak perlu absen.</p>
        </section>
    <?php elseif ($roster->shift === 'IZIN'): ?>
        <section class="sheet">
            <h2 class="state-title">IZIN</h2>
            <p class="state-text"><?= esc($roster->keterangan ?: 'Izin disetujui.') ?></p>
        </section>
    <?php else: ?>
        <section class="sheet">
            <?php foreach ($sesi as $s): ?>
                <article class="shift">
                    <div class="shift-head">
                        <div>
                            <h2 class="shift-name"><?= esc($s['label']) ?></h2>
                            <p class="shift-time"><time datetime="<?= esc($s['masuk']) ?>"><?= esc($s['masuk']) ?></time><span class="dash" aria-hidden="true">&ndash;</span><time datetime="<?= esc($s['pulang']) ?>"><?= esc($s['pulang']) ?></time></p>
                        </div>
                        <?php if ($s['presensi'] === null): ?>
                            <p class="shift-state">Belum absen</p>
                        <?php elseif (empty($s['presensi']->waktu_pulang)): ?>
                            <?php
                            $kehadiran = (int) $s['presensi']->status_kehadiran;
                            $kelas     = [0 => 'is-ok', 1 => 'is-warn'][$kehadiran] ?? 'is-bad';
                            $label     = ['Tepat', 'Toleransi', 'Telat'][$kehadiran] ?? 'Tercatat';
                            ?>
                            <p class="shift-state <?= $kelas ?>">Masuk <?= esc(substr((string) $s['presensi']->waktu_masuk, 11, 5)) ?> &middot; <?= esc($label) ?></p>
                        <?php else: ?>
                            <p class="shift-state is-ok">Selesai <?= esc(substr((string) $s['presensi']->waktu_pulang, 11, 5)) ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if ($s['presensi'] === null): ?>
                        <form class="form-absen" method="post" action="<?= base_url('absen/masuk') ?>" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="sesi" value="<?= esc($s['kode']) ?>">
                            <input type="hidden" name="latitude">
                            <input type="hidden" name="longitude">
                            <div class="photo">
                                <input id="foto-<?= esc($s['kode']) ?>" type="file" name="foto" accept="image/*" capture="environment">
                                <label class="photo-label" for="foto-<?= esc($s['kode']) ?>">
                                    <svg class="photo-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8.5A2.5 2.5 0 0 1 5.5 6h1.2a1 1 0 0 0 .8-.4l.9-1.2a1.5 1.5 0 0 1 1.2-.6h4.8a1.5 1.5 0 0 1 1.2.6l.9 1.2a1 1 0 0 0 .8.4h1.2A2.5 2.5 0 0 1 21 8.5v8A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5z"/><circle cx="12" cy="12.5" r="3.2"/></svg>
                                    <span class="photo-preview" role="img" aria-label="Pratinjau foto"></span>
                                    <span class="photo-text">
                                        <span class="photo-title">Ambil foto wajah</span>
                                        <span class="photo-hint">Kamera belakang &middot; wajib</span>
                                    </span>
                                </label>
                            </div>
                            <button class="btn" type="submit">Absen Masuk</button>
                            <p class="msg" role="status" aria-live="polite"></p>
                            <?php if ($petaSiap): ?>
                            <button type="button" class="link lihat-peta" hidden>Lihat titik absen</button>
                            <?php endif; ?>
                        </form>
                    <?php elseif (empty($s['presensi']->waktu_pulang)): ?>
                        <form class="form-absen" method="post" action="<?= base_url('absen/pulang') ?>" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="idpresensi" value="<?= (int) $s['presensi']->idpresensi ?>">
                            <input type="hidden" name="latitude">
                            <input type="hidden" name="longitude">
                            <div class="photo">
                                <input id="pulang-<?= (int) $s['presensi']->idpresensi ?>" type="file" name="foto" accept="image/*" capture="environment">
                                <label class="photo-label" for="pulang-<?= (int) $s['presensi']->idpresensi ?>">
                                    <svg class="photo-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8.5A2.5 2.5 0 0 1 5.5 6h1.2a1 1 0 0 0 .8-.4l.9-1.2a1.5 1.5 0 0 1 1.2-.6h4.8a1.5 1.5 0 0 1 1.2.6l.9 1.2a1 1 0 0 0 .8.4h1.2A2.5 2.5 0 0 1 21 8.5v8A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5z"/><circle cx="12" cy="12.5" r="3.2"/></svg>
                                    <span class="photo-preview" role="img" aria-label="Pratinjau foto"></span>
                                    <span class="photo-text">
                                        <span class="photo-title">Ambil foto pulang</span>
                                        <span class="photo-hint">Kamera belakang &middot; wajib</span>
                                    </span>
                                </label>
                            </div>
                            <button class="btn" type="submit">Absen Pulang</button>
                            <p class="msg" role="status" aria-live="polite"></p>
                            <?php if ($petaSiap): ?>
                            <button type="button" class="link lihat-peta" hidden>Lihat titik absen</button>
                            <?php endif; ?>
                        </form>
                    <?php else: ?>
                        <div class="links">
                            <a class="link" href="<?= base_url('absen/foto/' . (int) $s['presensi']->idpresensi) ?>">Foto masuk</a>
                            <a class="link" href="<?= base_url('absen/foto/' . (int) $s['presensi']->idpresensi . '/pulang') ?>">Foto pulang</a>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
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

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) return;

            if (preview.dataset.url) URL.revokeObjectURL(preview.dataset.url);
            preview.dataset.url = URL.createObjectURL(file);
            preview.style.backgroundImage = 'url("' + preview.dataset.url + '")';
            label.classList.add('has-photo');
            hint.textContent = 'Foto siap dikirim';
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (form.dataset.sibuk === '1') return;

            var pesan = form.querySelector('.msg');
            var tombol = form.querySelector('button');
            pesan.className = 'msg';
            pesan.textContent = 'Mengambil lokasi...';
            if (petaBtn) petaBtn.hidden = true;

            if (!input.files[0]) {
                pesan.className = 'msg is-err';
                pesan.textContent = 'Foto wajib diambil.';
                return;
            }

            form.dataset.sibuk = '1';
            tombol.disabled = true;

            ambilLokasi().then(function (coords) {
                form.querySelector('input[name=latitude]').value = coords.latitude;
                form.querySelector('input[name=longitude]').value = coords.longitude;

                return kompres(input.files[0]);
            }).then(function (blob) {
                var data = new FormData(form);
                data.set('foto', blob, 'absen.jpg');
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
})();
</script>
</body>
</html>
