<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Riwayat Garansi</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Riwayat</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Riwayat Garansi</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative">
    <div class="px-4 py-3 border-bottom">
    </div>

    <form action="<?php echo base_url('riwayat_service_garansi/export') ?>" method="post" enctype="multipart/form-data">
        <button type="submit" class="btn btn-danger"
            style="margin-left: 20px; display: inline-flex; align-items: center;">
            <iconify-icon icon="solar:export-broken" width="24" height="24" style="margin-right: 8px;"></iconify-icon>
            Ekspor
        </button>


        <!-- Filter Tanggal & Unit -->
        <div class="iclear-filter-toolbar">

            <label class="ms-3 me-2">Tanggal Awal:</label>
            <input name="tanggal_awal" type="date" id="startDate" class="form-control d-inline"
                style="width: auto; display: inline-block;" onchange="filterData()">

            <label class="ms-3 me-2">Tanggal Akhir:</label>
            <input name="tanggal_akhir" type="date" id="endDate" class="form-control d-inline"
                style="width: auto; display: inline-block;" onchange="filterData()">

            <button type="button" onclick="resetFilter()" class="btn btn-sm btn-secondary ms-3">Reset</button>
        </div>
    </form>

    <div class="iclear-table-host mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle iclear-data-table" data-compact-columns="3,5" id="zero_config">
            <thead class="text-dark fs-4">
                <tr>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">No Service</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Tanggal Service</h6>
                    </th>

                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Tanggal Klaim</h6>
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
                        <h6 class="fs-4 fw-semibold mb-0">Status</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Aksi</h6>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($service)): ?>
                    <?php foreach ($service as $row): ?>
                        <tr>
                            <td><?= esc($row->no_service) ?></td>
                            <td><?= esc(date('d-m-Y', strtotime($row->created_at))) ?></td>
                            <td><?= esc(date('d-m-Y', strtotime($row->tanggal_claim_garansi))) ?></td>
                            <td><?= esc($row->nama_pelanggan) ?></td>
                            <td><?= esc($row->no_hp) ?></td>
                            <td><?= esc($row->alamat) ?></td>
                            <td>
                                <?php if ($row->status_service == 1) : ?>
                                    Menunggu Service
                                <?php elseif ($row->status_service == 2) : ?>
                                    Dalam Pengerjaan
                                <?php elseif ($row->status_service == 3) : ?>
                                    Menunggu Pengambilan
                                <?php elseif ($row->status_service == 4) : ?>
                                    Sudah Diambil
                                <?php elseif ($row->status_service == 9) : ?>
                                    Dibatalkan
                                <?php else : ?>
                                    Data Tidak Valid
                                <?php endif ?>
                            </td>
                            <td>
                                <!-- <?php if ($row->status_service == 4): ?>
                                    <button type="button" class="btn btn-warning edit-button" disabled>
                                        <iconify-icon icon="solar:clapperboard-edit-broken" width="24" height="24"></iconify-icon>
                                    </button>
                                <?php else: ?>
                                    <a href="<?php echo base_url('detail/riwayat_service/' . $row->idservice) ?>">
                                        <button type="button" class="btn btn-warning edit-button">
                                            <iconify-icon icon="solar:clapperboard-edit-broken" width="24" height="24">
                                            </iconify-icon>
                                        </button>
                                    <?php endif; ?>
                                    </a> -->
                                <a href="<?php echo base_url('cetak/invoice_service/' . $row->idservice) ?>">
                                    <button type="button" class="btn btn-sm btn-danger"
                                        style="display: inline-flex; align-items: center;">
                                        <iconify-icon icon="solar:folder-favourite-bookmark-broken" width="24" height="24">
                                        </iconify-icon>
                                        Cetak Struk
                                    </button>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
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



        filterData();
    };

    function filterData() {
        window.IclearTables.filter('zero_config', 2);
    }

    function resetFilter() {
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';

        filterData();
    }
</script>
