<?php date_default_timezone_set('Asia/Jakarta'); ?>


<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Presensi</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Presensi</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Presensi</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="card-body">
        <div class="modal fade" id="loading-lokasi-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
            data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content text-center p-4">
                    <center>
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                    </center>
                    <h5>Mendapatkan lokasi...</h5>
                </div>
            </div>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <h5 style="display: flex; padding-left: 20px; padding-top: 20px; padding-bottom: 20px;">Absensi Masuk
                Tanggal
                : <?= date('d-m-Y') ?></h2>
                <div class="d-flex flex-wrap justify-content-center gap-2 gap-md-3">
                    <button type="button" class="btn btn-sm btn-danger"
                        style="display: inline-flex; align-items: center; justify-content: center; width: 140px; height: 35px;"
                        data-bs-toggle="modal" data-bs-target="#modal-lokasi-saya">
                        Cek Lokasi Saya
                    </button>

                    <button type="button" class="btn btn-sm btn-primary" id="btn-absen-masuk"
                        style="display: inline-flex; align-items: center; justify-content: center; width: 140px; height: 35px;">
                        Absen
                    </button>
                </div>

        </div>
        <div class="table-responsive mb-4 px-4">
            <table class="table border text-nowrap mb-0 align-middle">
                <thead class="text-dark fs-4">
                    <tr>
                        <th>Nama Pegawai</th>
                        <td>Tanggal</td>
                        <th>Jenis Absensi</th>
                        <th>Jenis Shift</th>
                        <th>Status Kehadiran</th>
                        <th style="display: flex; justify-content: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($presensiHariIni as $row) : ?>
                        <tr>
                            <td><?= session('NAMA') ?></td>
                            <td><?= date('d-m-Y') ?></td>
                            <td>Absen Masuk</td>
                            <td><?= $row->nama_jadwal ?></td>
                            <?php if ($row->status_kehadiran == 0) : ?>
                                <td><span class="btn btn-success btn-sm" style="width: 100px;">Tepat Waktu</span></td>
                            <?php elseif ($row->status_kehadiran == 1) : ?>
                                <td><span class="btn btn-warning btn-sm text-dark" style="width: 100px;">Toleransi</span></td>
                            <?php elseif ($row->status_kehadiran == 2) : ?>
                                <td><span class="btn btn-danger btn-sm" style="width: 100px;">Telat</span></td>
                            <?php endif ?>
                            <td>


                                <button type="button" class="btn-detail-lokasi  btn btn-success" data-bs-toggle="modal"
                                    data-bs-target="#modal-peta-lokasi" data-lat="<?= $row->lat ?>"
                                    data-waktumasuk="<?= $row->waktu_masuk ?>" data-waktupulang="<?= $row->waktu_pulang ?>"
                                    data-jadwalmasuk="<?= $row->jam_jadwal_masuk ?>"
                                    data-jadwalpulang="<?= $row->jam_jadwal_pulang ?>" data-ip="<?= $row->ip ?>"
                                    data-nama="<?= $row->NAMA_AKUN ?>" data-foto="<?= $row->foto ?>"
                                    data-idpresensi="<?= (int) $row->idpresensi ?>"
                                    data-sumber="<?= esc((string) $row->sumber) ?>"
                                    data-long="<?= $row->long ?>" data-jarak="<?= $row->jarak ?>">
                                    Detail
                                </button>




                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<div class="card w-100 position-relative overflow-hidden">
    <div class="card-body">


        <h5 style="display: flex; padding-left: 20px; padding-top: 20px; padding-bottom: 20px;">Absensi Pulang Tanggal
            : <?= date('d-m-Y') ?></h2>



            <div class="table-responsive mb-4 px-4">
                <table class="table border text-nowrap mb-0 align-middle">
                    <thead class="text-dark fs-4">
                        <tr>
                            <th>Nama Pegawai</th>
                            <th>Tanggal</th>
                            <th>Jenis Absensi</th>
                            <th>Jenis Shift</th>
                            <th style="display: flex; justify-content: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($presensiHariIni as $row) : ?>
                            <tr>
                                <td><?= session('NAMA') ?></td>
                                <td><?= date('d-m-Y') ?></td>
                                <td>Absen Pulang</td>
                                <td><?= $row->nama_jadwal ?></td>
                                <td style="display: flex; justify-content: center;">
                                    <?php if (empty($row->waktu_pulang) || $row->waktu_pulang === '00:00:00'): ?>

                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                            data-bs-target="#modal-konfirmasi-absen-pulang"
                                            data-idabsenpulang="<?= $row->idpresensi ?>">
                                            Submit
                                        </button>

                                    <?php else: ?>
                                        <button type="button" class="btn btn-success" data-bs-toggle="modal"
                                            data-bs-target="#modal-detail-absen-pulang" data-jammasuk="<?= $row->waktu_masuk ?>"
                                            data-jampulang="<?= $row->waktu_pulang ?>"
                                            data-detailabsenpulang="<?= $row->idpresensi ?>" data-jarak="<?= $row->jarak ?>"
                                            data-sumber="<?= esc((string) $row->sumber) ?>"
                                            data-fotopulang="<?= $row->foto_pulang ?>">
                                            Detail
                                        </button>

                                    <?php endif; ?>

                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>

            </div>

    </div>
</div>

<!-- modal absen masuk -->
<div class="modal fade" id="modal-konfirmasi-absen-masuk" tabindex="-1" aria-labelledby="konfirmasiAbsenMasukLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="konfirmasiAbsenMasukLabel">Konfirmasi Absen Masuk</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p style="font-style: italic;">Pilih shift terlebih dahulu</p>

                <form action="kirim/lokasi_masuk" enctype="multipart/form-data" method="post">

                    <div class="mb-3">
                        <label for="jadwal-select" class="form-label">Pilih Jadwal Masuk</label>
                        <select id="jadwal-select" class="form-select">
                            <?php if (!empty($jadwalmasuk)): ?>
                                <option value="" disabled selected>-- Pilih Jadwal --</option>
                                <?php foreach ($jadwalmasuk as $jadwal): ?>
                                    <option value="<?= esc($jadwal->idjadwal_masuk) ?>"
                                        data-jammasuk="<?= esc($jadwal->jam_masuk) ?>"
                                        data-jampulang="<?= esc($jadwal->jam_pulang) ?>"
                                        data-jamtoleransi="<?= esc($jadwal->toleransi) ?>">
                                        <?= esc($jadwal->nama_jadwal) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled selected>Tidak ada jadwal</option>
                            <?php endif; ?>
                        </select>
                    </div>


                    <div class="mb-3">
                        <label for="foto" class="form-label">Ambil Foto Kehadiran</label>
                        <input type="file" name="foto_kehadiran" id="foto" class="form-control" accept="image/*"
                            capture="environment" required>
                    </div>


                    <input hidden id="latitude" name="latitude">
                    <input hidden id="longitude" name="longitude">

                    <div class="mb-2">
                        <label hidden>ID Jadwal Masuk</label>
                        <input hidden type="text" name="idjadwal_masuk" id="idjadwal_masuk" class="form-control"
                            readonly>
                    </div>

                    <div class="mb-2">
                        <label>Jam Masuk</label>
                        <input type="text" id="jam_masuk" name="jam_masuk" class="form-control" readonly>
                    </div>

                    <div class="mb-2">
                        <label>Jam Pulang</label>
                        <input type="text" id="jam_pulang" name="jam_pulang" class="form-control" readonly>
                    </div>

                    <div class="mb-2">
                        <label>Jam toleransi</label>
                        <input type="text" id="jam_toleransi" name="jam_toleransi" class="form-control" readonly>
                    </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary" id="konfirmasi-absen-masuk">Ya, Absen Sekarang</button>
            </div>
            </form>

        </div>
    </div>
</div>


<!-- Modal Konfirmasi Absen Pulang -->
<div class="modal fade" id="modal-konfirmasi-absen-pulang" tabindex="-1" aria-labelledby="konfirmasiAbsenPulangLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="konfirmasiAbsenPulangLabel">Konfirmasi Absen Pulang</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p style="font-style: italic;">Apakah Anda yakin ingin melakukan absen pulang sekarang?</p>
                <form action="kirim/lokasi_pulang" enctype="multipart/form-data" method="post">
                    <input type="hidden" name="idpresensi" id="input-idpresensi-pulang">
                    <div class="mb-3">
                        <label for="foto" class="form-label">Ambil Foto Kepulangan</label>
                        <input type="file" name="foto_kehadiran" id="foto" class="form-control" accept="image/*"
                            capture="environment" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn bg-danger-subtle text-danger"
                            data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Ya, Absen Pulang</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- testing bug foto -->

<!-- Modal Detail Absen Pulang -->
<div class="modal fade" id="modal-detail-absen-pulang" tabindex="-1" aria-labelledby="modalDetailAbsenPulangLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetailAbsenPulangLabel">Detail Absen Pulang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" id="body-detail-absen-pulang">
                <!-- Konten akan diisi via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail Absen -->
<div class="modal fade" id="modal-detail-absen" tabindex="-1" aria-labelledby="modalDetailAbsenLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetailAbsenLabel">Detail Absen Masuk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" id="body-detail-absen-masuk">
                <!-- Isi akan di-render dengan JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>


<<div class="modal fade" id="modal-peta-lokasi" tabindex="-1" aria-labelledby="modalPetaLokasiLabel" aria-hidden="true">
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

                        <!-- Baris 2 card di bawah map -->
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

    <!-- modal lokasi -->
    <div class="modal fade" id="modal-lokasi-saya" tabindex="-1" aria-labelledby="modalLokasiSayaLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lokasi Saya & Unit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="map2" style="height: 500px; width: 100%;"></div>

                    <!-- Tombol ambil lokasi -->
                    <div class="text-center my-3">
                        <button id="btn-ambil-lokasi" class="btn btn-primary">
                            Ambil Lokasi Saya
                        </button>
                    </div>

                    <!-- Status lokasi -->
                    <div id="status-lokasi" class="text-center text-muted"></div>
                </div>
            </div>
        </div>
    </div>



    <script>
        let loadingModal;
        let konfirmasiModal;

        document.addEventListener('DOMContentLoaded', function() {
            const loadingModalEl = document.getElementById('loading-lokasi-modal');
            loadingModal = bootstrap.Modal.getOrCreateInstance(loadingModalEl);

            const konfirmasiModalEl = document.getElementById('modal-konfirmasi-absen-masuk');
            konfirmasiModal = bootstrap.Modal.getOrCreateInstance(konfirmasiModalEl);

            const btnAbsenMasuk = document.getElementById('btn-absen-masuk');

            btnAbsenMasuk.addEventListener('click', function() {
                // Tampilkan loading dulu
                loadingModal.show();

                // Ambil lokasi
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            // Isi input lat/long di modal konfirmasi
                            document.getElementById('latitude').value = position.coords.latitude;
                            document.getElementById('longitude').value = position.coords.longitude;

                            // Setelah loading, tampilkan modal konfirmasi
                            setTimeout(() => {
                                loadingModal.hide();
                                konfirmasiModal.show();
                            }, 800);
                        },
                        function(error) {
                            setTimeout(() => {
                                loadingModal.hide();
                                alert('Gagal mendapatkan lokasi: ' + error.message);
                            }, 500);
                        }
                    );
                } else {
                    loadingModal.hide();
                    alert('Geolocation tidak didukung oleh browser ini');
                }
            });


            document.getElementById('konfirmasi-absen-masuk').addEventListener('click', function() {
                const form = document.getElementById('form-masuk');


                const idjadwal = document.getElementById('idjadwal_masuk').value;
                if (!idjadwal) {
                    alert('Silakan pilih jadwal masuk terlebih dahulu');
                    return;
                }

                // Submit form
                form.submit();
            });
        });
    </script>

    <script>
        document.getElementById('jadwal-select').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];

            const id = selectedOption.value;
            const jamMasuk = selectedOption.getAttribute('data-jammasuk');
            const jamPulang = selectedOption.getAttribute('data-jampulang');
            const jamToleransi = selectedOption.getAttribute('data-jamtoleransi');

            document.getElementById('idjadwal_masuk').value = id;
            document.getElementById('jam_masuk').value = jamMasuk;
            document.getElementById('jam_pulang').value = jamPulang;
            document.getElementById('jam_toleransi').value = jamToleransi;
        });

        document.getElementById('konfirmasi-absen-masuk').addEventListener('click', function() {
            l
            const jadwalId = document.getElementById('idjadwal_masuk').value;
            if (!jadwalId) {
                alert('Silakan pilih jadwal terlebih dahulu.');
                return;
            }

            // Submit form
            document.getElementById('form-masuk').submit();
        });
    </script>

    <script>
        const modalAbsenPulang = document.getElementById('modal-konfirmasi-absen-pulang');
        modalAbsenPulang.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const idpresensi = button.getAttribute('data-idabsenpulang');
            const input = modalAbsenPulang.querySelector('#input-idpresensi-pulang');
            input.value = idpresensi;
        });
    </script>

    <script>
        const modalDetailAbsenPulang = document.getElementById('modal-detail-absen-pulang');
        modalDetailAbsenPulang.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;

            const waktuMasuk = button.getAttribute('data-jammasuk');
            const waktuPulang = button.getAttribute('data-jampulang');
            const nama = "<?= session('NAMA') ?>";

            const jamMasuk = new Date(waktuMasuk);
            const jamPulang = waktuPulang ? new Date(waktuPulang) : null;

            const fotopulang = button.getAttribute('data-fotopulang');
            const idpresensi = button.getAttribute('data-detailabsenpulang');
            const sumber = button.getAttribute('data-sumber');
            const urlFotoPulang = sumber === 'mobile'
                ? "<?= base_url('absen/foto/') ?>" + idpresensi + '/pulang'
                : "<?= base_url('foto_presensi/') ?>" + fotopulang;

            let konten = '';

            if (jamPulang) {
                const durasiMs = jamPulang - jamMasuk;
                const durasiJam = Math.floor(durasiMs / 1000 / 60 / 60);
                const durasiMenit = Math.floor((durasiMs / 1000 / 60) % 60);

                konten = `
                <ul class="list-group">
                    <li class="list-group-item"><strong>Nama:</strong> ${nama}</li>
                    <li class="list-group-item"><strong>Tanggal:</strong> ${jamMasuk.toLocaleDateString('id-ID')}</li>
                    <li class="list-group-item"><strong>Jam Masuk:</strong> ${jamMasuk.toLocaleTimeString('id-ID')}</li>
                    <li class="list-group-item"><strong>Jam Pulang:</strong> ${jamPulang.toLocaleTimeString('id-ID')}</li>
                    <li class="list-group-item"><strong>Durasi Kerja:</strong> ${durasiJam} jam ${durasiMenit} menit</li>
                    <li class="list-group-item"><strong>Foto:</strong><br>`;
                if (fotopulang) {
                    const imgSrc = urlFotoPulang;
                    konten += `
                    <center>
                        <img src="${imgSrc}" alt="Foto Absen"
                            style="max-width: 200px; max-height: 150px; cursor: pointer;"
                            onclick="tampilkanModalGambar('${imgSrc}')">
                    </center>`;
                } else {
                    konten += `<em>Tidak ada foto</em>`;
                }
                konten += `</li></ul>`;
            } else {
                konten = `<p class="text-danger">Data presensi tidak ditemukan.</p>`;
            }

            modalDetailAbsenPulang.querySelector('#body-detail-absen-pulang').innerHTML = konten;
        });
    </script>

    <?php foreach ($presensiHariIni as $p): ?>
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
                    const idpresensi = e.target.getAttribute('data-idpresensi');
                    const sumber = e.target.getAttribute('data-sumber');
                    const urlFoto = sumber === 'mobile'
                        ? "<?= base_url('absen/foto/') ?>" + idpresensi
                        : "<?= base_url('foto_presensi/') ?>" + foto;

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
                            <li class="list-group-item"><strong>Foto:</strong><br>`;
                        if (foto) {
                            const imgSrc = urlFoto;
                            konten += `
                    <center>
                        <img src="${imgSrc}" alt="Foto Absen"
                             style="max-width: 200px; max-height: 150px; cursor: pointer;"
                             onclick="tampilkanModalGambar('${imgSrc}')">
                    </center>`;
                        } else {
                            konten += `<em>Tidak ada foto</em>`;
                        }
                        konten += `</li></ul></div></div>`;
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

    <!-- Tambahkan CDN SweetAlert2 di head -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>



    <script>
        const latitudeUnit = parseFloat(<?= json_encode($data_latlong->LATITUDE ?? 0) ?>);
        const longitudeUnit = parseFloat(<?= json_encode($data_latlong->LONGTITUDE ?? 0) ?>);

        console.log("Lat Unit:", latitudeUnit, "Long Unit:", longitudeUnit);

        let map112 = null;
        let latitudeUser = null;
        let longitudeUser = null;

        function hitungJarak(lat1, lon1, lat2, lon2) {
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat / 2) ** 2 +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon / 2) ** 2;
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c; // KM
        }

        // Reset status setiap kali modal dibuka
        document.getElementById('modal-lokasi-saya').addEventListener('shown.bs.modal', function() {
            document.getElementById('status-lokasi').textContent = "Klik tombol untuk mengambil lokasi.";
        });

        document.getElementById('btn-ambil-lokasi').addEventListener('click', function() {
            if (!navigator.geolocation) {
                Swal.fire("Error", "Browser kamu tidak mendukung Geolocation.", "error");
                return;
            }

            // Jika browser support API permissions, cek status izin
            if (navigator.permissions) {
                navigator.permissions.query({
                    name: 'geolocation'
                }).then(function(result) {
                    console.log("Status izin lokasi:", result.state);
                    if (result.state === "denied") {
                        Swal.fire("Izin Lokasi Ditolak",
                            "Silakan aktifkan izin lokasi untuk browser ini di pengaturan HP Anda.",
                            "warning"
                        );
                        return;
                    }
                    ambilLokasiUser();
                }).catch(() => {
                    ambilLokasiUser(); // fallback jika tidak bisa cek izin
                });
            } else {
                ambilLokasiUser();
            }
        });

        function ambilLokasiUser() {
            document.getElementById('status-lokasi').textContent = "Mengambil lokasi...";
            navigator.geolocation.getCurrentPosition(function(pos) {
                latitudeUser = pos.coords.latitude;
                longitudeUser = pos.coords.longitude;
                console.log("Lokasi User:", latitudeUser, longitudeUser);
                document.getElementById('status-lokasi').textContent = "Lokasi berhasil diambil ✅";
                tampilkanMap();
            }, function(err) {
                console.error("Geolocation Error:", err);
                switch (err.code) {
                    case err.PERMISSION_DENIED:
                        Swal.fire("Izin Lokasi Ditolak", "Silakan izinkan akses lokasi di browser.", "error");
                        break;
                    case err.POSITION_UNAVAILABLE:
                        Swal.fire("Lokasi Tidak Tersedia", "Pastikan GPS/Location Service aktif.", "warning");
                        break;
                    case err.TIMEOUT:
                        Swal.fire("Timeout", "Gagal mendapatkan lokasi. Coba lagi.", "error");
                        break;
                    default:
                        Swal.fire("Error", "Terjadi kesalahan saat mengambil lokasi.", "error");
                }
            }, {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            });
        }

        function tampilkanMap() {
            if (map112) {
                map112.remove();
                map112 = null;
            }

            map112 = L.map('map2').setView([latitudeUnit, longitudeUnit], 15);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }).addTo(map112);

            // Marker Unit
            L.marker([latitudeUnit, longitudeUnit])
                .addTo(map112)
                .bindPopup('Lokasi Unit')
                .openPopup();

            // Marker User
            L.marker([latitudeUser, longitudeUser])
                .addTo(map112)
                .bindPopup('Lokasi Anda');

            // Polyline
            const latlngs = [
                [latitudeUnit, longitudeUnit],
                [latitudeUser, longitudeUser]
            ];
            const polyline = L.polyline(latlngs, {
                color: 'blue'
            }).addTo(map112);

            // Hitung jarak
            const jarakKM = hitungJarak(latitudeUnit, longitudeUnit, latitudeUser, longitudeUser);
            const jarakMeter = jarakKM * 1000;

            // Mid point untuk tooltip
            const midLat = (latitudeUnit + latitudeUser) / 2;
            const midLng = (longitudeUnit + longitudeUser) / 2;

            L.tooltip({
                    permanent: true,
                    direction: 'top',
                    className: 'leaflet-distance-label'
                })
                .setContent(`${jarakMeter.toFixed(0)} meter`)
                .setLatLng([midLat, midLng])
                .addTo(map112);

            // Hapus pesan lama jika ada
            const modalBody = document.querySelector('#modal-lokasi-saya .modal-body');
            const oldInfoDiv = modalBody.querySelector('.info-jarak');
            if (oldInfoDiv) oldInfoDiv.remove();

            // Tambahkan pesan jarak
            const infoDiv = document.createElement('div');
            infoDiv.classList.add('info-jarak');
            infoDiv.style.padding = '10px';
            infoDiv.style.textAlign = 'center';
            infoDiv.style.fontWeight = 'bold';

            if (jarakMeter > 200) {
                infoDiv.style.color = 'red';
                infoDiv.textContent = "Anda harus berada kurang dari 200 meter dari jarak ke unit";
            } else {
                infoDiv.style.color = 'green';
                infoDiv.textContent = "✅ Selamat anda bisa absen disini";
            }

            modalBody.appendChild(infoDiv);

            // Auto fit view
            map112.fitBounds(latlngs);
        }
    </script>