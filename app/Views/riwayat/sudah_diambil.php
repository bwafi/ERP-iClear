<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Pengambilan Service</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Pengambilan</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Service</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom">
    </div>

    <form action="<?php echo base_url('riwayat_service/export') ?>" method="post" enctype="multipart/form-data">
        <button type="submit" class="btn btn-danger"
            style="margin-left: 20px; display: inline-flex; align-items: center;">
            <iconify-icon icon="solar:export-broken" width="24" height="24" style="margin-right: 8px;"></iconify-icon>
            Export
        </button>
        <br><br>


        <!-- Filter Tanggal & Unit -->
        <div class="mb-3 px-4">

            <label class="ms-3 me-2">Tanggal Awal:</label>
            <input name="tanggal_awal" type="date" id="startDate" class="form-control d-inline"
                style="width: auto; display: inline-block;" onchange="filterData()">

            <label class="ms-3 me-2">Tanggal Akhir:</label>
            <input name="tanggal_akhir" type="date" id="endDate" class="form-control d-inline"
                style="width: auto; display: inline-block;" onchange="filterData()">

            <button type="button" onclick="resetFilter()" class="btn btn-sm btn-secondary ms-3">Reset</button>
        </div>
    </form>





    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" <?= empty($service) ? '' : 'id="zero_config"' ?>>

            <thead class="text-dark fs-4">
                <tr>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">No Service</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Tanggal Service Masuk</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Tanggal Diambil</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama Pelanggan</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nomor Handphone</h6>
                    </th>

                    <th style="display: flex; justify-content: center;">
                        <h6 class="fs-4 fw-semibold mb-0">Unit</h6>
                    </th>

                    <th>
                        <h6 style="display: flex; justify-content: center;" class="fs-4 fw-semibold mb-0">Lama Waktu</h6>
                    </th>

                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Status garansi</h6>
                    </th>

                    <th style="display: flex; justify-content: center;">
                        <h6 class="fs-4 fw-semibold mb-0">Action</h6>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($service)): ?>
                    <?php foreach ($service as $row): ?>
                        <tr>
                            <td><?= esc($row->no_service) ?></td>
                            <td><?= esc(date('d-m-Y', strtotime($row->created_at))) ?></td>
                            <td><?= esc(date('d-m-Y', strtotime($row->tanggal_selesai))) ?></td>
                            <td><?= esc($row->nama_pelanggan) ?></td>
                            <td><?= esc($row->no_hp) ?></td>
                            <td>
                                <?= esc($row->unit_idunit == 1 ? 'Probolinggo' : ($row->unit_idunit == 2 ? 'Jember' : ($row->unit_idunit == 3 ? 'Banyuwangi': ($row->unit_idunit == 4 ? 'Pandaan' : $row->unit_idunit)))) ?>
                            </td>
                            <td><?= esc($row->lama_service) ?></td>


                            <td>
                                <?php if ($row->garansi_hari > 0 && !empty($row->tanggal_selesai)): ?>
                                    <?php
                                    $tanggal_selesai = new DateTime($row->tanggal_selesai);
                                    $tanggal_akhir = clone $tanggal_selesai;
                                    $tanggal_akhir->modify("+{$row->garansi_hari} days");

                                    $today = new DateTime();

                                    if ($today > $tanggal_akhir) {
                                        // Garansi sudah kadaluarsa
                                        echo "Kadaluarsa pada " . $tanggal_akhir->format('d-m-Y');
                                    } else {
                                        // Garansi masih aktif
                                        echo esc($row->garansi_hari) . " hari (aktif sampai " . $tanggal_akhir->format('d-m-Y') . ")";
                                    }
                                    ?>
                                <?php else: ?>
                                    Tidak ada garansi
                                <?php endif; ?>
                            </td>


                            <td>
                                <a href="<?php echo base_url('cetak/invoice_service/' . $row->idservice) ?>">
                                    <button type="button" class="btn btn-sm btn-danger"
                                        style="display: inline-flex; align-items: center;">
                                        <iconify-icon icon="solar:folder-favourite-bookmark-broken" width="24" height="24">
                                        </iconify-icon>
                                        Cetak Struk
                                    </button>
                                </a>
                                
                                <a href="<?= base_url('cetak/invoice_service/' . $row->idservice . '?mode=thermal') ?>">
                                    <button type="button" class="btn btn-sm btn-danger"
                                        style="display: inline-flex; align-items: center;">
                                        <iconify-icon icon="solar:folder-favourite-bookmark-broken" width="24" height="24">
                                        </iconify-icon>
                                        Cetak Struk (Thermal)
                                    </button>
                                </a>
                                
                                <button type="button" class="btn btn-wa"
                                    data-nohp="<?= esc($row->no_hp) ?>"
                                    data-nama="<?= esc($row->nama_pelanggan) ?>"
                                    style="width: 100px; height: 40px; background-color: greenyellow;">
                                    <iconify-icon icon="solar:phone-bold" width="24" height="24"></iconify-icon>
                                </button>
                            </td>
                        </tr>

                        <!-- modal bsa dimabil -->

                        <div class="modal fade" id="sudahDiambilModal-<?= esc($row->idservice) ?>" tabindex="-1" aria-labelledby="sudahDiambilModalLabel-<?= esc($row->idservice) ?>" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header d-flex align-items-center">
                                        <h4 class="modal-title" id="sudahDiambilModalLabel-<?= esc($row->idservice) ?>">Konfirmasi Pengambilan</h4>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                    </div>
                                    <form action="<?= base_url('service/sudah_diambil') ?>" method="post">
                                        <div class="modal-body">
                                            <input type="hidden" name="idservice" value="<?= esc($row->idservice) ?>">
                                            <p>Apakah Anda yakin service ini sudah diambil oleh pelanggan?</p>
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
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">Tidak ada data</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>






<script>
    let dataTable;

    window.onload = function() {
        // Inisialisasi DataTable (pastikan id tabel kamu benar)
        dataTable = $('#zero_config').DataTable();

        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');

        const today = new Date();
        const fifteenDaysAgo = new Date();
        fifteenDaysAgo.setDate(today.getDate() - 15);

        const toDateInputValue = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        // Set default 15 hari ke belakang
        startDateInput.value = toDateInputValue(fifteenDaysAgo);
        endDateInput.value = toDateInputValue(today);

        // Filter pertama kali
        filterData();

        // Jalankan filter setiap kali tanggal diubah
        startDateInput.addEventListener('change', filterData);
        endDateInput.addEventListener('change', filterData);
    };

    function filterData() {
        const start = document.getElementById('startDate').value;
        const end = document.getElementById('endDate').value;

        const startDate = start ? new Date(start) : null;
        const endDate = end ? new Date(end) : null;

        // Tambahkan custom filter DataTables
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            // Ambil tanggal dari kolom ke-2 (index 1) – ubah jika posisi kolom berbeda
            const dateText = data[1].trim();
            const parts = dateText.split('-'); // Asumsi format dd-mm-yyyy
            if (parts.length !== 3) return true; // kalau format salah, jangan disaring

            const rowDate = new Date(`${parts[2]}-${parts[1]}-${parts[0]}`);

            if (startDate && rowDate < startDate) return false;
            if (endDate && rowDate > endDate) return false;

            return true;
        });

        // Redraw tabel
        dataTable.draw();

        // Hapus filter supaya tidak menumpuk setiap kali filterData dipanggil
        $.fn.dataTable.ext.search.pop();
    }

    function resetFilter() {
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';

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