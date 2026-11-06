<?= view('riwayat/_riwayat_presensi_theme') ?>

<?php
$rpBarisPertama = $presensi[0] ?? null;
$rpUnit         = $rpBarisPertama->NAMA_UNIT ?? session('NAMA_UNIT') ?? 'Unit';
$rpTotal        = count($presensi);
$rpJam          = static function ($value): string {
    if (empty($value)) {
        return '—';
    }
    $ts = strtotime((string) $value);

    return $ts ? date('H:i', $ts) : '—';
};
$rpHari = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu',
];
?>

<div class="rp-page">
    <header class="rp-hero">
        <div>
            <nav class="rp-crumb" aria-label="Breadcrumb">
                <ol>
                    <li><a href="<?= base_url('/') ?>">Riwayat</a></li>
                    <li><span aria-current="page">Presensi</span></li>
                </ol>
            </nav>
            <h1>Riwayat Presensi</h1>
            <p>Catatan kehadiran Anda dari absen mobile dan presensi lama. Buka detail untuk melihat jam, jarak, foto, dan titik absen.</p>
        </div>
        <div class="rp-hero__aside">
            <span class="rp-chip"><iconify-icon icon="solar:buildings-2-linear" width="15" height="15" aria-hidden="true"></iconify-icon><?= esc($rpUnit) ?></span>
            <span class="rp-chip"><strong><?= (int) $rpTotal ?></strong> baris tercatat</span>
        </div>
    </header>

    <section class="rp-board" aria-label="Ringkasan kehadiran">
        <div class="rp-board__cell">
            <div class="rp-board__label">Total baris</div>
            <div class="rp-board__value" data-rp-count="total">—</div>
        </div>
        <div class="rp-board__cell">
            <div class="rp-board__label">Tepat waktu</div>
            <div class="rp-board__value is-ok" data-rp-count="tepat">—</div>
        </div>
        <div class="rp-board__cell">
            <div class="rp-board__label">Toleransi</div>
            <div class="rp-board__value is-warn" data-rp-count="toleransi">—</div>
        </div>
        <div class="rp-board__cell">
            <div class="rp-board__label">Telat</div>
            <div class="rp-board__value is-bad" data-rp-count="telat">—</div>
        </div>
        <div class="rp-board__cell">
            <div class="rp-board__label">Belum konfirmasi</div>
            <div class="rp-board__value" data-rp-count="belum">—</div>
        </div>
    </section>

    <section class="rp-card rp-filter" aria-label="Filter tanggal">
        <div class="rp-filter__fields">
            <div class="rp-field">
                <label for="startDate">Dari tanggal</label>
                <input name="tanggal_awal" type="date" id="startDate" class="rp-control">
            </div>
            <div class="rp-field">
                <label for="endDate">Sampai tanggal</label>
                <input name="tanggal_akhir" type="date" id="endDate" class="rp-control">
            </div>
            <button type="button" id="rp-reset" class="rp-btn rp-btn--ghost">Reset</button>
        </div>
        <p class="rp-filter__hint">
            <iconify-icon icon="solar:info-circle-linear" width="16" height="16" aria-hidden="true"></iconify-icon>
            Filter bawaan 15 hari terakhir. Reset menampilkan seluruh riwayat.
        </p>
    </section>

    <section class="rp-card rp-table-card">
        <div class="rp-table-card__head">
            <h2>Log presensi</h2>
            <p>Status kehadiran dihitung saat absen; status presensi menandai absen yang sudah final.</p>
        </div>

        <div class="rp-table-wrap">
            <table class="rp-table" id="riwayat-presensi-table">
                <thead>
                    <tr>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Jadwal masuk</th>
                        <th scope="col">Toleransi</th>
                        <th scope="col">Jam masuk</th>
                        <th scope="col">Jam pulang</th>
                        <th scope="col">Kehadiran</th>
                        <th scope="col">Status presensi</th>
                        <th scope="col"><span class="visually-hidden">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($presensi as $row): ?>
                        <?php
                        $kehadiran = $row->status_kehadiran;
                        $status    = $row->status_absensi;
                        $tanggal   = strtotime((string) $row->created_at);
                        ?>
                        <tr data-kehadiran="<?= $kehadiran === null ? '' : (int) $kehadiran ?>"
                            data-status="<?= $status === null ? '' : (int) $status ?>">
                            <td data-label="Tanggal" data-order="<?= $tanggal ? esc(date('Y-m-d H:i:s', $tanggal)) : '' ?>">
                                <span class="rp-day">
                                    <span class="rp-day__date"><?= $tanggal ? esc(date('d-m-Y', $tanggal)) : '—' ?></span>
                                    <span class="rp-day__note"><?= $tanggal ? esc($rpHari[date('l', $tanggal)] ?? '') : '' ?></span>
                                </span>
                            </td>
                            <td data-label="Jadwal masuk"><span class="rp-time"><?= esc($rpJam(($row->jam_jadwal_masuk ?? null) ?: ($row->jmmasuk ?? null))) ?></span></td>
                            <td data-label="Toleransi"><span class="rp-time"><?= esc($rpJam(($row->jam_toleransi ?? null) ?: ($row->toleransi ?? null))) ?></span></td>
                            <td data-label="Jam masuk"><span class="rp-time <?= empty($row->waktu_masuk) ? 'is-empty' : '' ?>"><?= esc($rpJam($row->waktu_masuk)) ?></span></td>
                            <td data-label="Jam pulang"><span class="rp-time <?= empty($row->waktu_pulang) ? 'is-empty' : '' ?>"><?= esc($rpJam($row->waktu_pulang)) ?></span></td>
                            <td data-label="Kehadiran">
                                <?php if ((string) $kehadiran === '0'): ?>
                                    <span class="rp-tag rp-tag--ok">Tepat waktu</span>
                                <?php elseif ((string) $kehadiran === '1'): ?>
                                    <span class="rp-tag rp-tag--warn">Toleransi</span>
                                <?php elseif ((string) $kehadiran === '2'): ?>
                                    <span class="rp-tag rp-tag--bad">Telat</span>
                                <?php else: ?>
                                    <span class="rp-tag rp-tag--muted">Tercatat</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status presensi">
                                <?php if ((string) $status === '1'): ?>
                                    <span class="rp-tag rp-tag--accent">Terkonfirmasi</span>
                                <?php elseif ((string) $status === '0'): ?>
                                    <span class="rp-tag rp-tag--warn">Belum dikonfirmasi</span>
                                <?php else: ?>
                                    <span class="rp-tag rp-tag--muted">Tidak diketahui</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Detail">
                                <button type="button"
                                    class="rp-action js-rp-detail"
                                    data-bs-toggle="modal"
                                    data-bs-target="#rp-modal-detail"
                                    data-nama="<?= esc((string) $row->NAMA_AKUN) ?>"
                                    data-unit="<?= esc((string) ($row->NAMA_UNIT ?? '')) ?>"
                                    data-tanggal="<?= $tanggal ? esc(date('d-m-Y', $tanggal)) : '—' ?>"
                                    data-waktumasuk="<?= esc((string) $row->waktu_masuk) ?>"
                                    data-waktupulang="<?= esc((string) $row->waktu_pulang) ?>"
                                    data-jadwalmasuk="<?= esc($rpJam(($row->jam_jadwal_masuk ?? null) ?: ($row->jmmasuk ?? null))) ?>"
                                    data-jadwalpulang="<?= esc($rpJam($row->jam_jadwal_pulang)) ?>"
                                    data-toleransi="<?= esc($rpJam(($row->jam_toleransi ?? null) ?: ($row->toleransi ?? null))) ?>"
                                    data-ip="<?= esc((string) $row->ip) ?>"
                                    data-lat="<?= esc((string) $row->lat) ?>"
                                    data-long="<?= esc((string) $row->long) ?>"
                                    data-latkantor="<?= esc((string) ($row->LATITUDE ?? '')) ?>"
                                    data-longkantor="<?= esc((string) ($row->LONGTITUDE ?? '')) ?>"
                                    data-jarak="<?= esc((string) $row->jarak) ?>"
                                    data-idpresensi="<?= (int) $row->idpresensi ?>"
                                    data-sumber="<?= esc((string) $row->sumber) ?>"
                                    data-foto="<?= esc((string) $row->foto) ?>"
                                    data-fotopulang="<?= esc((string) $row->foto_pulang) ?>"
                                    data-kehadiran="<?= $kehadiran === null ? '' : (int) $kehadiran ?>"
                                    data-status="<?= $status === null ? '' : (int) $status ?>">
                                    <iconify-icon icon="solar:map-point-wave-linear" width="15" height="15" aria-hidden="true"></iconify-icon>
                                    Detail
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Detail presensi -->
    <div class="modal fade rp-modal" id="rp-modal-detail" tabindex="-1" aria-labelledby="rp-detail-title" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header rp-head">
                    <div class="rp-identity">
                        <span class="rp-avatar" aria-hidden="true"><iconify-icon icon="solar:user-rounded-linear" width="22" height="22"></iconify-icon></span>
                        <div>
                            <h5 class="modal-title" id="rp-detail-title"><span id="rp-detail-nama">Detail presensi</span></h5>
                            <p><span id="rp-detail-unit">—</span> · <span id="rp-detail-tanggal">—</span></p>
                        </div>
                    </div>
                    <div class="rp-detail__tags" id="rp-detail-tags"></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="rp-detail">
                        <div class="rp-media">
                            <div class="rp-map" id="rp-map"></div>
                            <div class="rp-photos" id="rp-detail-photos"></div>
                        </div>
                        <div>
                            <section class="rp-panel" aria-labelledby="rp-meta-title">
                                <h6 class="rp-panel__title" id="rp-meta-title">Jejak absen</h6>
                                <dl class="rp-meta" id="rp-detail-meta"></dl>
                            </section>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="rp-btn rp-btn--ghost" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Penampil foto -->
    <div class="modal fade rp-modal rp-viewer" id="rp-modal-foto" tabindex="-1" aria-labelledby="rp-foto-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rp-foto-title">Foto absensi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="rp-zoom">
                        <img id="rp-zoom-image" src="" alt="Foto absensi">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="rp-viewer__btn" data-rp-pan="0,-20" aria-label="Geser ke atas">↑</button>
                    <button type="button" class="rp-viewer__btn" data-rp-pan="-20,0" aria-label="Geser ke kiri">←</button>
                    <button type="button" class="rp-viewer__btn" data-rp-pan="20,0" aria-label="Geser ke kanan">→</button>
                    <button type="button" class="rp-viewer__btn" data-rp-pan="0,20" aria-label="Geser ke bawah">↓</button>
                    <button type="button" class="rp-viewer__btn" data-rp-zoom="-0.1" aria-label="Perkecil">−</button>
                    <button type="button" class="rp-viewer__btn" data-rp-zoom="0.1" aria-label="Perbesar">+</button>
                    <button type="button" class="rp-btn rp-btn--ghost" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const baseFotoLegacy = "<?= base_url('foto_presensi/') ?>";
        const baseFotoMobile = "<?= base_url('absen/foto/') ?>";
        const labelKehadiran = { '0': 'Tepat waktu', '1': 'Toleransi', '2': 'Telat' };
        const labelStatus = { '0': 'Belum dikonfirmasi', '1': 'Terkonfirmasi' };

        const startInput = document.getElementById('startDate');
        const endInput = document.getElementById('endDate');
        let table = null;

        const esc = function (value) {
            return String(value === null || value === undefined || value === '' ? '—' : value).replace(/[&<>"']/g, function (ch) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
            });
        };

        const tanggalInput = function (date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        };

        const formatJam = function (value) {
            if (!value) {
                return '—';
            }
            const date = new Date(value);
            if (isNaN(date.getTime())) {
                return '—';
            }
            return String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
        };

        $(function () {
            const today = new Date();
            const fifteenDaysAgo = new Date();
            fifteenDaysAgo.setDate(today.getDate() - 15);
            startInput.value = tanggalInput(fifteenDaysAgo);
            endInput.value = tanggalInput(today);

            // Tanggal dibaca dari data-order ISO di sel, bukan teks dd-mm-yyyy.
            $.fn.dataTable.ext.search.push(function (settings, data, index) {
                const tr = settings.aoData[index] && settings.aoData[index].nTr;
                const iso = tr && tr.cells[0] ? tr.cells[0].getAttribute('data-order') : '';
                if (!iso) {
                    return true;
                }
                const rowDate = new Date(iso.slice(0, 10));
                const start = startInput.value ? new Date(startInput.value) : null;
                const end = endInput.value ? new Date(endInput.value) : null;
                if (start && rowDate < start) {
                    return false;
                }
                if (end && rowDate > end) {
                    return false;
                }
                return true;
            });

            table = $('#riwayat-presensi-table').DataTable({
                order: [[0, 'desc']],
                pageLength: 15,
                lengthMenu: [[10, 15, 25, 50, -1], [10, 15, 25, 50, 'Semua']],
                columnDefs: [{ targets: 7, orderable: false, searchable: false }],
                dom: "<'rp-dt-toolbar'<'rp-dt-len'l><'rp-dt-search'f>>t<'rp-dt-foot'<'rp-dt-info'i><'rp-dt-page'p>>",
                language: {
                    search: '',
                    searchPlaceholder: 'Cari tanggal atau status…',
                    lengthMenu: 'Tampil _MENU_ baris',
                    info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris',
                    infoEmpty: 'Tidak ada baris',
                    infoFiltered: '(dari _MAX_ total baris)',
                    zeroRecords: 'Tidak ada presensi yang cocok dengan filter.',
                    emptyTable: 'Belum ada data presensi.',
                    paginate: { previous: '‹', next: '›' }
                }
            });

            startInput.addEventListener('change', function () { table.draw(); });
            endInput.addEventListener('change', function () { table.draw(); });

            table.table().container().querySelector('.dataTables_filter input')
                .setAttribute('aria-label', 'Cari presensi');
            table.table().container().querySelector('.dataTables_length select')
                .setAttribute('aria-label', 'Jumlah baris per halaman');

            document.getElementById('rp-reset').addEventListener('click', function () {
                startInput.value = '';
                endInput.value = '';
                table.search('').draw();
            });

            perbaruiRingkasan();
            table.on('draw', perbaruiRingkasan);
        });

        function perbaruiRingkasan() {
            if (!table) {
                return;
            }
            const nodes = table.rows({ search: 'applied' }).nodes().toArray();
            const hitung = { total: nodes.length, tepat: 0, toleransi: 0, telat: 0, belum: 0 };

            nodes.forEach(function (node) {
                const kehadiran = node.getAttribute('data-kehadiran');
                const status = node.getAttribute('data-status');
                if (kehadiran === '0') { hitung.tepat++; }
                else if (kehadiran === '1') { hitung.toleransi++; }
                else if (kehadiran === '2') { hitung.telat++; }
                if (status === '0') { hitung.belum++; }
            });

            Object.keys(hitung).forEach(function (key) {
                const el = document.querySelector('[data-rp-count="' + key + '"]');
                if (el) {
                    el.textContent = hitung[key];
                }
            });
        }

        function urlFoto(data, pulang) {
            const path = pulang ? data.fotoPulang : data.foto;
            if (!path) {
                return '';
            }
            if (data.sumber === 'mobile') {
                return baseFotoMobile + data.idpresensi + (pulang ? '/pulang' : '');
            }
            return baseFotoLegacy + path;
        }

        function metaItem(label, value) {
            return '<div class="rp-meta__item"><dt>' + esc(label) + '</dt><dd>' + esc(value) + '</dd></div>';
        }

        function fotoItem(label, src) {
            if (!src) {
                return '<figure class="rp-photo"><figcaption>' + esc(label) + '</figcaption><div class="rp-photo__empty">Tidak ada foto</div></figure>';
            }
            return '<figure class="rp-photo"><figcaption>' + esc(label) + '</figcaption>' +
                '<img class="rp-photo__img" src="' + esc(src) + '" alt="' + esc(label) + '" loading="lazy"></figure>';
        }

        let detailAktif = null;

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.js-rp-detail');
            if (!btn) {
                return;
            }

            const data = {
                nama: btn.getAttribute('data-nama'),
                unit: btn.getAttribute('data-unit'),
                tanggal: btn.getAttribute('data-tanggal'),
                waktuMasuk: btn.getAttribute('data-waktumasuk'),
                waktuPulang: btn.getAttribute('data-waktupulang'),
                jadwalMasuk: btn.getAttribute('data-jadwalmasuk'),
                jadwalPulang: btn.getAttribute('data-jadwalpulang'),
                toleransi: btn.getAttribute('data-toleransi'),
                ip: btn.getAttribute('data-ip'),
                lat: btn.getAttribute('data-lat'),
                long: btn.getAttribute('data-long'),
                latKantor: btn.getAttribute('data-latkantor'),
                longKantor: btn.getAttribute('data-longkantor'),
                jarak: btn.getAttribute('data-jarak'),
                idpresensi: btn.getAttribute('data-idpresensi'),
                sumber: btn.getAttribute('data-sumber'),
                foto: btn.getAttribute('data-foto'),
                fotoPulang: btn.getAttribute('data-fotopulang'),
                kehadiran: btn.getAttribute('data-kehadiran'),
                status: btn.getAttribute('data-status')
            };

            document.getElementById('rp-detail-nama').textContent = data.nama || 'Tanpa nama';
            document.getElementById('rp-detail-unit').textContent = data.unit || 'Tanpa unit';
            document.getElementById('rp-detail-tanggal').textContent = data.tanggal || '—';

            let tags = '';
            if (labelKehadiran[data.kehadiran]) {
                const warna = data.kehadiran === '0' ? 'ok' : (data.kehadiran === '1' ? 'warn' : 'bad');
                tags += '<span class="rp-tag rp-tag--' + warna + '">' + esc(labelKehadiran[data.kehadiran]) + '</span>';
            }
            if (labelStatus[data.status]) {
                tags += '<span class="rp-tag ' + (data.status === '1' ? 'rp-tag--accent' : 'rp-tag--warn') + '">' + esc(labelStatus[data.status]) + '</span>';
            }
            document.getElementById('rp-detail-tags').innerHTML = tags;

            const koordinat = (data.lat && data.long) ? data.lat + ', ' + data.long : '—';
            document.getElementById('rp-detail-meta').innerHTML = [
                metaItem('Jam masuk', formatJam(data.waktuMasuk)),
                metaItem('Jam pulang', formatJam(data.waktuPulang)),
                metaItem('Jadwal masuk', data.jadwalMasuk),
                metaItem('Toleransi', data.toleransi),
                metaItem('Jadwal pulang', data.jadwalPulang),
                metaItem('Jarak', data.jarak === '—' ? '—' : data.jarak + ' m'),
                metaItem('IP', data.ip || '—'),
                metaItem('Koordinat', koordinat)
            ].join('');

            document.getElementById('rp-detail-photos').innerHTML = [
                fotoItem('Foto masuk', urlFoto(data, false)),
                fotoItem('Foto pulang', urlFoto(data, true))
            ].join('');

            document.querySelectorAll('#rp-detail-photos .rp-photo__img').forEach(function (img) {
                img.addEventListener('click', function () {
                    tampilkanModalGambar(img.getAttribute('src'));
                });
            });

            detailAktif = data;
        });

        let peta = null;

        document.getElementById('rp-modal-detail').addEventListener('shown.bs.modal', function () {
            if (!detailAktif) {
                return;
            }
            const mapEl = document.getElementById('rp-map');
            const latKantor = parseFloat(detailAktif.latKantor);
            const longKantor = parseFloat(detailAktif.longKantor);
            const latPegawai = parseFloat(detailAktif.lat);
            const longPegawai = parseFloat(detailAktif.long);

            if (peta) {
                peta.remove();
                peta = null;
            }

            mapEl.innerHTML = '';
            if (!isFinite(latKantor) || !isFinite(longKantor) || !isFinite(latPegawai) || !isFinite(longPegawai)) {
                mapEl.innerHTML = '<p class="rp-map__empty">Koordinat unit atau pegawai belum diatur, peta tidak bisa ditampilkan.</p>';
                return;
            }

            peta = L.map(mapEl).setView([latKantor, longKantor], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }).addTo(peta);

            L.marker([latKantor, longKantor]).addTo(peta).bindPopup('Lokasi kantor').openPopup();
            L.marker([latPegawai, longPegawai]).addTo(peta).bindPopup('Lokasi pegawai');

            const garis = L.polyline([[latKantor, longKantor], [latPegawai, longPegawai]], { color: '#0b5f8a' }).addTo(peta);
            peta.fitBounds(garis.getBounds(), { padding: [30, 30] });

            const jarak = hitungJarak(latKantor, longKantor, latPegawai, longPegawai);
            L.tooltip({ permanent: true, direction: 'top', className: 'rp-map__label' })
                .setContent(jarak.toFixed(2) + ' km')
                .setLatLng([(latKantor + latPegawai) / 2, (longKantor + longPegawai) / 2])
                .addTo(peta);
        });

        function hitungJarak(lat1, lon1, lat2, lon2) {
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat / 2) ** 2 +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon / 2) ** 2;
            return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        }

        let zoomLevel = 1;
        let geserX = 0;
        let geserY = 0;
        let fotoTertunda = false;

        const elDetail = document.getElementById('rp-modal-detail');
        const elFoto = document.getElementById('rp-modal-foto');
        const elZoom = document.getElementById('rp-zoom-image');

        function terapkanTransformasi() {
            elZoom.style.transform = 'scale(' + zoomLevel + ') translate(' + geserX + 'px, ' + geserY + 'px)';
        }

        function tampilkanModalGambar(src) {
            zoomLevel = 1;
            geserX = 0;
            geserY = 0;
            elZoom.src = src;
            terapkanTransformasi();

            fotoTertunda = true;
            const inst = elDetail.classList.contains('show') ? bootstrap.Modal.getInstance(elDetail) : null;
            if (inst) {
                inst.hide();
            } else {
                bukaFoto();
            }
        }

        function bukaFoto() {
            fotoTertunda = false;
            bootstrap.Modal.getOrCreateInstance(elFoto).show();
        }

        // Viewer dibuka setelah detail tertutup agar dua modal tidak bertumpuk.
        elDetail.addEventListener('hidden.bs.modal', function () {
            if (fotoTertunda) {
                bukaFoto();
            }
        });

        elFoto.addEventListener('hidden.bs.modal', function () {
            if (detailAktif) {
                bootstrap.Modal.getOrCreateInstance(elDetail).show();
            }
        });

        document.querySelectorAll('[data-rp-zoom]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                zoomLevel = Math.max(0.4, zoomLevel + parseFloat(btn.getAttribute('data-rp-zoom')));
                terapkanTransformasi();
            });
        });

        document.querySelectorAll('[data-rp-pan]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const delta = btn.getAttribute('data-rp-pan').split(',');
                geserX += parseFloat(delta[0]);
                geserY += parseFloat(delta[1]);
                terapkanTransformasi();
            });
        });
    })();
</script>
