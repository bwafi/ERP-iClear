<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0"> Service Dibatalkan</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Service</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Dibatalkan</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative">
    <div class="px-4 py-3 border-bottom">
    </div>

    <form action="<?php echo base_url('riwayat_service/export') ?>" method="post" enctype="multipart/form-data">
        <!-- <button type="submit" class="btn btn-danger"
            style="margin-left: 20px; display: inline-flex; align-items: center;">
            <iconify-icon icon="solar:export-broken" width="24" height="24" style="margin-right: 8px;"></iconify-icon>
            Export
        </button> -->


        <!-- Filter Tanggal & Unit -->
        <div class="iclear-filter-toolbar">

            <label class="ms-3 me-2">Tanggal Awal:</label>
            <input name="tanggal_awal" type="date" id="startDate" class="form-control d-inline"
                style="width: auto; display: inline-block;" onchange="filterData()">

            <label class="ms-3 me-2">Tanggal Akhir:</label>
            <input name="tanggal_akhir" type="date" id="endDate" class="form-control d-inline"
                style="width: auto; display: inline-block;" onchange="filterData()">

            <label class="ms-3 me-2">Status:</label>
            <select id="statusFilter" class="form-select d-inline"
                style="width: auto; display: inline-block;" onchange="filterData()">
                <option value="">Semua</option>
                <option value="90">Belum Diambil</option>
                <option value="91">Sudah Diambil</option>
            </select>

            <button type="button" onclick="resetFilter()" class="btn btn-sm btn-secondary ms-3">Reset</button>
        </div>
    </form>





    <div class="iclear-table-host mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle iclear-data-table" data-compact-columns="2,4" id="zero_config">

            <thead class="text-dark fs-4">
                <tr>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">No Service</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Tanggal Service</h6>
                    </th>



                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama Pelanggan</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">No. HP</h6>
                    </th>

                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Alamat</h6>
                    </th>

                    <th>
                        <h6 style="display: flex; justify-content: center;" class="fs-4 fw-semibold mb-0">Lama</h6>
                    </th>

                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Jenis Service</h6>
                    </th>



                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Status Pengambilan</h6>
                    </th>

                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Aksi</h6>
                    </th>

                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Kontak</h6>
                    </th>


                </tr>
            </thead>
            <tbody>
                <?php if (!empty($service)): ?>
                    <?php foreach ($service as $row): ?>
                        <tr data-status="<?= esc($row->status_service) ?>">

                            <td><?= esc($row->no_service) ?></td>
                            <td><?= esc(date('d-m-Y', strtotime($row->created_at))) ?></td>

                            <td><?= esc($row->nama_pelanggan) ?></td>
                            <td><?= esc($row->no_hp) ?></td>
                            <td><?= esc($row->alamat) ?></td>
                            <td><?= esc($row->lama_service) ?></td>
                            <td>
                                <?php if (!empty($row->tanggal_claim_garansi) && $row->tanggal_claim_garansi > '1971-01-01'): ?>
                                    <span class="badge bg-warning text-dark">Service Garansi</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Service Baru</span>
                                <?php endif; ?>
                            </td>

                            <!-- Tombol Status -->
                            <td>
                                <?php
                                $status = $row->status_service;
                                $id = $row->idservice;

                                $statusMap = [
                                    90 => ['text' => 'Belum Diambil', 'class' => 'btn-light'],
                                    91 => ['text' => 'Sudah Diambil', 'class' => 'btn-primary'],

                                ];

                                $current = $statusMap[$status] ?? ['text' => 'Status Tidak Diketahui', 'class' => 'btn-outline-dark'];

                                echo '<button disabled type="button" class="btn ' . $current['class'] . '" data-bs-toggle="modal" data-bs-target="#statusModal-' . $id . '">' . $current['text'] . '</button>';
                                ?>
                            </td>


                            <td>
                                <button class="btn btn-success btn-bisa-diambil"
                                    data-bs-toggle="modal"
                                    data-bs-target="#bisaDiambilModal"
                                    data-idservice="<?= esc($row->idservice) ?>"
                                    data-jumlah_kerusakan="<?= $row->jumlah_kerusakan ?>"
                                    data-jumlah_sparepart="<?= $row->jumlah_sparepart ?>">
                                    Diambil
                                </button>

                                <button class="btn btn-success btn-Dibatalkan"
                                    data-bs-toggle="modal"
                                    data-bs-target="#DibatalkanModal"
                                    data-idservice="<?= esc($row->idservice) ?>"
                                    data-jumlah_kerusakan="<?= $row->jumlah_kerusakan ?>"
                                    data-jumlah_sparepart="<?= $row->jumlah_sparepart ?>">
                                    Aktifkan Kembali
                                </button>



                            </td>
                            <td>
                                <button type="button" class="btn btn-wa"
                                    data-nohp="<?= esc($row->no_hp) ?>"
                                    data-nama="<?= esc($row->nama_pelanggan) ?>"
                                    style="width: 100px; height: 40px; background-color: greenyellow;">
                                    <iconify-icon icon="solar:phone-bold" width="24" height="24"></iconify-icon>
                                </button>
                            </td>



                        </tr>

                        <!-- //modal detail status -->

                        <div class="modal fade" id="statusModal-<?= $id ?>" tabindex="-1" aria-labelledby="statusModalLabel-<?= $id ?>" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header d-flex align-items-center">
                                        <h4 class="modal-title" id="statusModalLabel-<?= $id ?>">Ubah Status Proses</h4>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>

                                    <form action="<?= base_url('update_status_proses') ?>" method="post" enctype="multipart/form-data">
                                        <div class="modal-body">
                                            <input type="hidden" name="idservice" value="<?= $id ?>">

                                            <div class="container-fluid">
                                                <div class="row">
                                                    <?php foreach ($statusMap as $value => $info): ?>
                                                        <div class="col-6 mb-2">
                                                            <input class="form-check-input" type="radio" name="status_proses" id="status<?= $value ?>-<?= $id ?>" value="<?= $value ?>" <?= $value == $status ? 'checked' : '' ?>>
                                                            <label style="width: 150px;" class="form-check-label btn <?= $info['class'] ?>  btn-sm ms-2" for="status<?= $value ?>-<?= $id ?>">
                                                                <?= $info['text'] ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>



                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>


                        <!-- modal bsa dimabil -->

                        <div class="modal fade" id="bisaDiambilModal" tabindex="-1" aria-labelledby="bisaDiambilModalLabel" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header d-flex align-items-center">
                                        <h4 class="modal-title" id="bisaDiambilModalLabel">Konfirmasi Pengambilan</h4>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                    </div>
                                    <form action="<?= base_url('diambil/dibtalkan') ?>" method="post">
                                        <div class="modal-body">
                                            <input type="hidden" name="idservice" id="modal-idservice">
                                            <p class="modal-text">

                                                Apakah Anda yakin ingin melanjutkan?
                                            </p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-success">Konfirmasi</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Dibatalkan modal -->
                        <div class="modal fade" id="DibatalkanModal" tabindex="-1" aria-labelledby="DibatalkanModalLabel" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header d-flex align-items-center">
                                        <h4 class="modal-title" id="DibatalkanModalLabel">Konfirmasi Pengambilan</h4>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                    </div>
                                    <form action="<?= base_url('aktifkan/dibatalkan') ?>" method="post">
                                        <div class="modal-body">
                                            <input type="hidden" name="idservice" id="modal-idservice">
                                            <p class="modal-text">
                                                Apakah Anda yakin ingin mengaktifkan kembali

                                            </p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-success">Konfirmasi</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>






                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modalElement = document.getElementById('bisaDiambilModal');

        // Gunakan event delegation pada document
        document.addEventListener('click', function(e) {
            if (e.target && e.target.closest('.btn-bisa-diambil')) {
                const button = e.target.closest('.btn-bisa-diambil');

                // Ambil data dari tombol
                const idservice = button.getAttribute('data-idservice');
                const jumlahKerusakan = button.getAttribute('data-jumlah_kerusakan');
                const jumlahSparepart = button.getAttribute('data-jumlah_sparepart');

                // Isi modal
                modalElement.querySelector('#modal-idservice').value = idservice;
                modalElement.querySelector('.kerusakan-count').textContent = jumlahKerusakan;
                modalElement.querySelector('.sparepart-count').textContent = jumlahSparepart;
            }
        });
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modalElement = document.getElementById('DibatalkanModal');

        // Gunakan event delegation pada document
        document.addEventListener('click', function(e) {
            if (e.target && e.target.closest('.btn-Dibatalkan')) {
                const button = e.target.closest('.btn-Dibatalkan');

                // Ambil data dari tombol
                const idservice = button.getAttribute('data-idservice');
                const jumlahKerusakan = button.getAttribute('data-jumlah_kerusakan');
                const jumlahSparepart = button.getAttribute('data-jumlah_sparepart');

                // Isi modal
                modalElement.querySelector('#modal-idservice').value = idservice;
                modalElement.querySelector('.kerusakan-count').textContent = jumlahKerusakan;
                modalElement.querySelector('.sparepart-count').textContent = jumlahSparepart;
            }
        });
    });
</script>







<script>
    window.onload = function() {
        filterData();
    };

    function filterData() {
        window.IclearTables.filter('zero_config', 1, 'statusFilter');
    }

    function resetFilter() {
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';
        document.getElementById('statusFilter').value = '';
        filterData();
    }
</script>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const buttons = document.querySelectorAll('.btn-wa');

        buttons.forEach(button => {
            button.addEventListener('click', function() {
                let nomor = this.dataset.nohp.trim();
                let nama = this.dataset.nama.trim();

                // Normalisasi nomor HP
                if (nomor.startsWith('0')) {
                    nomor = '62' + nomor.substring(1);
                } else if (nomor.startsWith('+62')) {
                    nomor = nomor.substring(1); // hapus +
                }

                const waUrl = 'https://wa.me/' + nomor + '?text=' + encodeURIComponent("Halo, Kami dari welldone group ingin melakukan konfirmasi untuk service handphone atas nama " + nama);

                // Buka WhatsApp
                window.open(waUrl, '_blank');
            });
        });
    });
</script>
