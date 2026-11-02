<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Riwayat Presensi</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Riwayat </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Presensi</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom">
    </div>

    <div class="mb-3 px-4">
        <form action="<?php echo base_url('export/semua_presensi') ?>" method="post" enctype="multipart/form-data">
            <!-- <button type="submit" class="btn btn-danger" style="margin-left: 20px; display: inline-flex; align-items: center;">
                <iconify-icon icon="solar:export-broken" width="24" height="24" style="margin-right: 8px;"></iconify-icon>
                Export
            </button> -->
            <br><br>

            <label class="ms-3 me-2">Tanggal Awal:</label>
            <input name="tanggal_awal" type="date" id="startDate" class="form-control d-inline"
                style="width: auto; display: inline-block;" onchange="filterData()">

            <label class="ms-3 me-2">Tanggal Akhir:</label>
            <input name="tanggal_akhir" type="date" id="endDate" class="form-control d-inline"
                style="width: auto; display: inline-block;" onchange="filterData()">

            <button type="button" onclick="resetFilter()" class="btn btn-sm btn-secondary ms-3">Reset</button>
        </form>
    </div>




    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
            <thead class="text-dark fs-4">
                <tr>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Tanggal</h6>
                    </th>

                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Jam Jadwal Masuk</h6>
                    </th>

                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Toleransi</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Jam Masuk</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Jam Pulang</h6>
                    </th>

                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Status Kehadiran</h6>
                    </th>

                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Detail Presensi</h6>
                    </th>

                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Status Presensi</h6>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($presensi)): ?>
                    <?php foreach ($presensi as $row): ?>
                        <tr>
                            <td><?= esc($row->NAMA_AKUN) ?></td>
                            <td><?= esc(date('d-m-Y', strtotime($row->created_at))) ?></td>
                            <td><?= esc(date('H:i:s', strtotime($row->jmmasuk))) ?></td>
                            <td><?= esc(date('H:i:s', strtotime($row->toleransi))) ?></td>
                            <td><?= esc(date('H:i:s', strtotime($row->waktu_masuk))) ?></td>
                            <td><?= esc(date('H:i:s', strtotime($row->waktu_pulang))) ?></td>
                            <?php if ($row->status_kehadiran == 0) : ?>
                                <td><span class="btn btn-success btn-sm" style="width: 100px;">Tepat Waktu</span></td>
                            <?php elseif ($row->status_kehadiran == 1) : ?>
                                <td><span class="btn btn-warning btn-sm text-dark" style="width: 100px;">Toleransi</span></td>
                            <?php elseif ($row->status_kehadiran == 2) : ?>
                                <td><span class="btn btn-danger btn-sm" style="width: 100px;">Telat</span></td>
                            <?php endif ?>





                            <td>
                                <!-- <button type="button" class="btn btn-success"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modal-detail-absen"
                                    data-waktumasuk="<?= $row->waktu_masuk ?>"
                                    data-waktupulang="<?= $row->waktu_pulang ?>"
                                    data-ip="<?= $row->ip ?>"
                                    data-lat="<?= $row->lat ?>"
                                    data-long="<?= $row->long ?>"
                                    data-foto="<?= $row->foto ?>"
                                    data-jarak="<?= $row->jarak ?>">
                                    Detail
                                </button> -->

                                <button
                                    type="button"
                                    class="btn-detail-lokasi  btn btn-danger"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modal-peta-lokasi"
                                    data-lat="<?= $row->lat ?>"
                                    data-waktumasuk="<?= $row->waktu_masuk ?>"
                                    data-waktupulang="<?= $row->waktu_pulang ?>"
                                    data-jadwalmasuk="<?= $row->jam_jadwal_masuk ?>"
                                    data-jadwalpulang="<?= $row->jam_jadwal_pulang ?>"
                                    data-ip="<?= $row->ip ?>"
                                    data-nama="<?= $row->NAMA_AKUN ?>"
                                    data-idpresensi="<?= (int) $row->idpresensi ?>"
                                    data-sumber="<?= esc((string) $row->sumber) ?>"
                                    data-foto="<?= $row->foto ?>"
                                    data-long="<?= $row->long ?>"
                                    data-jarak="<?= $row->jarak ?>"
                                    data-fotopulang="<?= $row->foto_pulang ?>">
                                    Detail
                                </button>

                            </td>


                            <td>
                                <?php if ($row->status_absensi == 0): ?>
                                    <button class="btn btn-warning btn-sm"

                                        data-idpresensi="<?= $row->idpresensi ?>">
                                        Belum Dikonfirmasi
                                    </button>

                                <?php elseif ($row->status_absensi == 1): ?>
                                    <button class="btn btn-success btn-sm">Terkonfirmasi</button>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Tidak diketahui</span>
                                <?php endif; ?>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">Tidak ada data</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>



<!-- Modal 2 Kolom Lokasi Pegawai -->
<div class="modal fade" id="modal-peta-lokasi" tabindex="-1" aria-labelledby="modalPetaLokasiLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetailLokasiLabel">Detail Lokasi Pegawai :</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <!-- Kolom Map -->
                    <div class="col-md-8">
                        <div id="map" style="height: 500px; width: 100%; border-radius: 10px;"></div>

                        <!-- Baris 2 card di bawah map tambahan -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="card text-bg-success">
                                    <div class="card-body">
                                        <h6 class="card-title mb-1">Jadwal Masuk</h6>
                                        <p class="card-text" id="detail-jamjadwal-masuk">-</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card text-bg-danger">
                                    <div class="card-body">
                                        <h6 class="card-title mb-1">Jadwal Pulang</h6>
                                        <p class="card-text" id="detail-jamjadwal-pulang">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom Detail Pegawai -->
                    <!-- Kolom Detail Pegawai -->
                    <div class="col-md-4">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Informasi Pegawai</h5>
                                <p><strong>Nama:</strong> <span id="detail_nama_map">-</span></p>
                                <p><strong>Jarak:</strong> <span id="detail_jarak_map">-</span></p>
                            </div>
                        </div>

                        <div id="detail-absen-map">
                            <!-- Konten akan dirender dengan JS -->
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal untuk Menampilkan Gambar -->
<div class="modal fade" id="fotoModal" tabindex="-1" aria-labelledby="fotoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div id="zoom-container" style="overflow: hidden;">
                    <img id="zoom-image" src="" alt="Foto Zoom"
                        style="max-width: 100%; transition: transform 0.3s ease;">
                </div>
            </div>
            <div class="modal-footer justify-content-center gap-2 flex-wrap">

                <!-- Tombol Geser -->
                <div class="btn-group me-3" role="group">
                    <button class="btn btn-secondary" onclick="panImage(0, -20)">↑</button>
                    <div class="d-flex">
                        <button class="btn btn-secondary" onclick="panImage(-20, 0)">←</button>
                        <button class="btn btn-secondary" onclick="panImage(20, 0)">→</button>
                    </div>
                    <button class="btn btn-secondary" onclick="panImage(0, 20)">↓</button>
                </div>

                <!-- Tombol Zoom -->
                <div class="btn-group" role="group">
                    <button class="btn btn-secondary" onclick="zoomOut()">-</button>
                    <button class="btn btn-secondary" onclick="zoomIn()">+</button>
                </div>

                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>


<script>
    let table;

    window.onload = function() {
        const endDateInput = document.getElementById('endDate');
        const startDateInput = document.getElementById('startDate');

        const today = new Date();
        const fifteenDaysAgo = new Date();
        fifteenDaysAgo.setDate(today.getDate() - 15);

        const toDateInputValue = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        startDateInput.value = toDateInputValue(fifteenDaysAgo);
        endDateInput.value = toDateInputValue(today);

        // ✅ Inisialisasi DataTable
        table = $('#zero_config').DataTable();

        // ✅ Tambahkan custom filter untuk date range
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            const start = startDateInput.value ? new Date(startDateInput.value) : null;
            const end = endDateInput.value ? new Date(endDateInput.value) : null;

            // Ambil tanggal dari kolom ke-2 (index 1)
            const dateParts = data[1].split('-'); // format dd-mm-yyyy
            const rowDate = new Date(`${dateParts[2]}-${dateParts[1]}-${dateParts[0]}`);

            if (start && rowDate < start) return false;
            if (end && rowDate > end) return false;

            return true;
        });

        // ✅ Jalankan filter pertama kali
        table.draw();
    };

    function filterData() {
        table.draw(); // ✅ Refresh DataTable setelah ganti filter
    }

    function resetFilter() {
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';
        table.draw(); // ✅ Refresh table setelah reset
    }
</script>


<?php foreach ($presensi as $p): ?>
    <script>
        const latitudeKantor = <?= json_encode($p->LATITUDE ?? null) ?>;
        const longitudeKantor = <?= json_encode($p->LONGTITUDE ?? null) ?>;


        let jarakPegawaiKantor = null;
        let map = null;

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-detail-lokasi')) {
                latitudePegawai = parseFloat(e.target.getAttribute('data-lat'));
                longitudePegawai = parseFloat(e.target.getAttribute('data-long'));
                const waktuMasuk = e.target.getAttribute('data-waktumasuk');
                const waktuPulang = e.target.getAttribute('data-waktupulang');
                const ip = e.target.getAttribute('data-ip') || '-';
                const lat = e.target.getAttribute('data-lat') || '-';
                const long = e.target.getAttribute('data-long') || '-';
                const foto = e.target.getAttribute('data-foto');
                const jarak = e.target.getAttribute('data-jarak') || '-';
                const nama = e.target.getAttribute('data-nama') || '-';
                const jadwalmasuk = e.target.getAttribute('data-jadwalmasuk') || '-';
                const jadwalpulang = e.target.getAttribute('data-jadwalpulang') || '-';

                const waktuMasukDate = waktuMasuk ? new Date(waktuMasuk) : null;
                const waktuPulangDate = waktuPulang ? new Date(waktuPulang) : null;
                const fotopulang = e.target.getAttribute('data-fotopulang');
                const idpresensi = e.target.getAttribute('data-idpresensi');
                const sumber = e.target.getAttribute('data-sumber');
                const urlFoto = (path, pulang) => sumber === 'mobile'
                    ? "<?= base_url('absen/foto/') ?>" + idpresensi + (pulang ? '/pulang' : '')
                    : "<?= base_url('foto_presensi/') ?>" + path;

                document.getElementById('detail_nama_map').textContent = nama;
                document.getElementById('detail_jarak_map').textContent = jarak + ' meter';
                document.getElementById('detail-jamjadwal-pulang').textContent = jadwalpulang;
                document.getElementById('detail-jamjadwal-masuk').textContent = jadwalmasuk;

                let konten = '';

                if (waktuMasukDate) {
                    konten += `
                <div class="card">
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><strong>Tanggal:</strong> ${waktuMasukDate.toLocaleDateString('id-ID')}</li>
                            <li class="list-group-item"><strong>Jam Masuk:</strong> ${waktuMasukDate.toLocaleTimeString('id-ID')}</li>
                            <li class="list-group-item"><strong>Jam Pulang:</strong> ${waktuPulangDate?.toLocaleTimeString('id-ID') ?? '-'}</li>
                            <li class="list-group-item"><strong>IP:</strong> ${ip}</li>
                            <li class="list-group-item"><strong>Lokasi:</strong> ${lat}, ${long}</li>
                            <li class="list-group-item"><strong>Foto Kehadiran:</strong><br>`;

                    if (foto) {
                        const imgSrc = urlFoto(foto);
                        konten += `
                    <center>
                    
                        <img src="${imgSrc}" alt="Foto Absen"
                             style="max-width: 200px; max-height: 150px; cursor: pointer;"
                             onclick="tampilkanModalGambar('${imgSrc}')">
                    </center>`;
                    } else {
                        konten += `<em>Tidak ada foto</em>`;
                    }
                    konten += `</li>

                    <li class="list-group-item"><strong>Foto Kepulangan:</strong><br>`;
                    if (fotopulang) {
                        const imgSrc = urlFoto(fotopulang, true);
                        konten += `
                    <center>
                        <img src="${imgSrc}" alt="Foto Absen"
                             style="max-width: 200px; max-height: 150px; cursor: pointer;"
                             onclick="tampilkanModalGambar('${imgSrc}')">
                    </center>`;
                    } else {
                        konten += `<em>Tidak ada foto</em>`;
                    }
                    konten += `</li>
                    </ul></div></div>`;
                } else {
                    konten = `<p class="text-danger">Data presensi tidak ditemukan.</p>`;
                }

                document.getElementById('detail-absen-map').innerHTML = konten;
            }
        });


        function hitungJarak(lat1, lon1, lat2, lon2) {
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat / 2) ** 2 +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon / 2) ** 2;
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        document.getElementById('modal-peta-lokasi').addEventListener('shown.bs.modal', function() {

            if (map) {
                map.remove();
                map = null;
            }


            map = L.map('map').setView([latitudeKantor, longitudeKantor], 15);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }).addTo(map);


            L.marker([latitudeKantor, longitudeKantor])
                .addTo(map)
                .bindPopup('Lokasi Kantor')
                .openPopup();


            L.marker([latitudePegawai, longitudePegawai])
                .addTo(map)
                .bindPopup('Lokasi Pegawai');


            const latlngs = [
                [latitudeKantor, longitudeKantor],
                [latitudePegawai, longitudePegawai]
            ];

            const polyline = L.polyline(latlngs, {
                color: 'blue'
            }).addTo(map);

            // Tooltip Jarak
            const jarak = hitungJarak(latitudeKantor, longitudeKantor, latitudePegawai, longitudePegawai);
            const midLat = (latitudeKantor + latitudePegawai) / 2;
            const midLng = (longitudeKantor + longitudePegawai) / 2;

            L.tooltip({
                    permanent: true,
                    direction: 'top',
                    className: 'leaflet-distance-label'
                })
                .setContent(`${jarak.toFixed(2)} km`)
                .setLatLng([midLat, midLng])
                .addTo(map);
        });
    </script>
<?php endforeach; ?>



<style>
    .leaflet-distance-label {
        background-color: white;
        border: 1px solid #999;
        border-radius: 4px;
        padding: 4px 8px;
        font-size: 13px;
        font-weight: bold;
        color: #333;
        box-shadow: 0 0 3px rgba(0, 0, 0, 0.3);
    }
</style>


<script>
    let zoomLevel = 1;
    let translateX = 0;
    let translateY = 0;
    const zoomImage = document.getElementById('zoom-image');

    function updateTransform() {
        zoomImage.style.transform = `scale(${zoomLevel}) translate(${translateX}px, ${translateY}px)`;
    }

    function tampilkanModalGambar(src) {
        zoomLevel = 1;
        translateX = 0;
        translateY = 0;
        zoomImage.src = src;
        updateTransform();
        const modal = new bootstrap.Modal(document.getElementById('fotoModal'));
        modal.show();
    }

    function zoomIn() {
        zoomLevel += 0.1;
        updateTransform();
    }

    function zoomOut() {
        zoomLevel = Math.max(0.1, zoomLevel - 0.1);
        updateTransform();
    }

    function panImage(x, y) {
        translateX += x;
        translateY += y;
        updateTransform();
    }
</script>