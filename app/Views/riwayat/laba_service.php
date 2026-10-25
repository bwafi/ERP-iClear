<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Riwayat Laba Service</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Riwayat</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Laba Service</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom">
    </div>

    <div class="card-body px-4 pt-4 pb-2 d-flex justify-content-between align-items-start mb-1">
        <div class="d-flex gap-2">
        </div>

    </div>



    <form action="<?php echo base_url('laba_service/export') ?>" method="post" enctype="multipart/form-data">
        <button type="submit" class="btn btn-danger"
            style="margin-left: 24px; display: inline-flex; align-items: center;">
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

    <div class="row mx-3">
        <div class="col-lg-3 col-md-6">
            <div class="card text-bg-dark text-white">
                <div class="card-body">
                    <div class="d-flex no-block align-items-center">
                        <a href="JavaScript: void(0);">
                            <iconify-icon icon="solar:cart-2-linear" width="48" height="48" style="color: #fff">
                            </iconify-icon>
                        </a>
                        <div class="ms-3 mt-2">
                            <h4 class=" mb-0 text-white">
                                Total HPP
                            </h4>
                            <h5 class="text-white" id="totalHPP">Rp. 0</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card text-bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex no-block align-items-center">
                        <a href="JavaScript: void(0);">
                            <iconify-icon icon="solar:cart-2-linear" width="48" height="48" style="color: #fff">
                            </iconify-icon>
                        </a>
                        <div class="ms-3 mt-2">
                            <h4 class=" mb-0 text-white">
                                Total Servive
                            </h4>
                            <h5 class="text-white" id="totalService">Rp. 0</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card text-bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex no-block align-items-center">
                        <a href="JavaScript: void(0);">
                            <iconify-icon icon="solar:cart-2-linear" width="48" height="48" style="color: #fff">
                            </iconify-icon>
                        </a>
                        <div class="ms-3 mt-2">
                            <h4 class=" mb-0 text-white">
                                Total Diskon
                            </h4>
                            <h5 class="text-white" id="totalDiskon">Rp. 0</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card text-bg-info text-white">
                <div class="card-body">
                    <div class="d-flex no-block align-items-center">
                        <a href="JavaScript: void(0);">
                            <iconify-icon icon="solar:cart-2-linear" width="48" height="48" style="color: #fff">
                            </iconify-icon>
                        </a>
                        <div class="ms-3 mt-2">
                            <h4 class=" mb-0 text-white">
                                Total Laba
                            </h4>
                            <h5 class="text-white" id="totalLaba">Rp. 0</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card text-bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex no-block align-items-center">
                        <a href="JavaScript: void(0);">
                            <iconify-icon icon="solar:cart-2-linear" width="48" height="48" style="color: #fff">
                            </iconify-icon>
                        </a>
                        <div class="ms-3 mt-2">
                            <h4 class=" mb-0 text-white">
                                HPP Klaim
                            </h4>
                            <h5 class="text-white" id="totalHppKlaim">Rp. 0</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
            <thead class="text-dark fs-4">
                <tr>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">No Service</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Tanggal Masuk</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama Teknisi</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Total HPP</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Total Service</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Total Diskon</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nilai Laba</h6>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($service)): ?>
                <?php foreach ($service as $row): ?>
                <tr>
                    <td><?= esc($row->no_service) ?></td>
                    <td><?= esc(date('d-m-Y', strtotime($row->created_at))) ?></td>
                    <td><?= esc($row->nama_teknisi) ?></td>
                    <td>Rp. <?= number_format($row->total_hpp_penjualan, 0, ',', '.') ?></td>
                    <td>Rp. <?= number_format($row->total_service, 0, ',', '.') ?></td>
                    <td>Rp. <?= number_format($row->total_diskon, 0, ',', '.') ?></td>
                    <td>Rp. <?= number_format($row->laba_service, 0, ',', '.') ?></td>
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

<!-- HPP Klaim: loss mengikuti tanggal_hpp_klaim, bukan tanggal service. -->
<div class="card w-100 position-relative overflow-hidden mb-4">
    <div class="card-body">
        <h5 class="fw-semibold mb-3">HPP Klaim</h5>
        <div class="table-responsive">
            <table class="table border text-nowrap mb-0 align-middle" id="hpp_klaim_table">
                <thead class="text-dark fs-4">
                    <tr>
                        <th>Tanggal Klaim</th>
                        <th>No Service</th>
                        <th>Nama Teknisi</th>
                        <th>Barang</th>
                        <th class="text-end">HPP</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($hpp_klaim ?? []) as $loss): ?>
                    <tr data-tanggal="<?= esc((string) $loss->tanggal_hpp_klaim, 'attr') ?>">
                        <td><?= date('d-m-Y', strtotime((string) $loss->tanggal_hpp_klaim)) ?></td>
                        <td><?= esc($loss->no_service) ?></td>
                        <td><?= esc($loss->nama_teknisi ?? '-') ?></td>
                        <td><?= esc($loss->nama_barang ?? '-') ?></td>
                        <td class="text-end">Rp. <?= number_format((float) $loss->hpp, 0, ',', '.') ?></td>
                        <td class="text-end"><?= (int) $loss->qty ?></td>
                        <td class="text-end hpp-klaim-total">Rp. <?= number_format((float) $loss->total, 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($hpp_klaim)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">Tidak ada HPP klaim pada periode ini.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="6" class="text-end">Total HPP Klaim:</th>
                        <th class="text-end" id="hppKlaimSectionTotal">Rp. <?= number_format((float) ($total_hpp_klaim ?? 0), 0, ',', '.') ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
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
    const start = document.getElementById('startDate').value;
    const end = document.getElementById('endDate').value;


    const rows = document.querySelectorAll('#zero_config tbody tr');
    rows.forEach(row => {
        const dateCell = row.children[1];
        if (!dateCell) return;

        const dateText = dateCell.textContent.trim();
        const parts = dateText.split('-');
        const rowDate = new Date(`${parts[2]}-${parts[1]}-${parts[0]}`);

        const startDate = start ? new Date(start) : null;
        const endDate = end ? new Date(end) : null;

        let dateMatch = true;
        if (startDate && rowDate < startDate) dateMatch = false;
        if (endDate && rowDate > endDate) dateMatch = false;

        row.style.display = (dateMatch) ? '' : 'none';
    });
}

function resetFilter() {
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';

    filterData();
}
</script>

<script>
function formatRupiah(number) {
    return 'Rp. ' + number.toLocaleString('id-ID');
}

function updateCardTotals() {
    let totalHPP = 0;
    let totalService = 0;
    let totalDiskon = 0;
    let totalLaba = 0;
    let totalHppKlaim = 0;

    const rows = document.querySelectorAll('#zero_config tbody tr');

    rows.forEach(row => {
        if (row.style.display === 'none') return;

        const hpp = parseInt(row.children[3].textContent.replace(/[^\d]/g, '')) || 0;
        const service = parseInt(row.children[4].textContent.replace(/[^\d]/g, '')) || 0;
        const diskon = parseInt(row.children[5].textContent.replace(/[^\d]/g, '')) || 0;
        const laba = parseInt(row.children[6].textContent.replace(/[^\d]/g, '')) || 0;

        totalHPP += hpp;
        totalService += service;
        totalDiskon += diskon;
        totalLaba += laba;
    });

    document.querySelectorAll('#hpp_klaim_table tbody tr[data-tanggal]').forEach(row => {
        if (row.style.display === 'none') return;
        totalHppKlaim += parseInt(row.querySelector('.hpp-klaim-total').textContent.replace(/[^\d]/g, '')) || 0;
    });

    document.getElementById('totalHPP').textContent = formatRupiah(totalHPP);
    document.getElementById('totalService').textContent = formatRupiah(totalService);
    document.getElementById('totalDiskon').textContent = formatRupiah(totalDiskon);
    document.getElementById('totalHppKlaim').textContent = formatRupiah(totalHppKlaim);
    document.getElementById('hppKlaimSectionTotal').textContent = formatRupiah(totalHppKlaim);
    document.getElementById('totalLaba').textContent = formatRupiah(totalLaba - totalHppKlaim);
}

function filterData() {
    const start = document.getElementById('startDate').value;
    const end = document.getElementById('endDate').value;
    const startDate = start ? new Date(start) : null;
    const endDate = end ? new Date(end) : null;

    const rows = document.querySelectorAll('#zero_config tbody tr');
    rows.forEach(row => {
        const dateCell = row.children[1];
        if (!dateCell) return;

        const dateText = dateCell.textContent.trim();
        const parts = dateText.split('-');
        const rowDate = new Date(`${parts[2]}-${parts[1]}-${parts[0]}`);

        let dateMatch = true;
        if (startDate && rowDate < startDate) dateMatch = false;
        if (endDate && rowDate > endDate) dateMatch = false;

        row.style.display = (dateMatch) ? '' : 'none';
    });

    document.querySelectorAll('#hpp_klaim_table tbody tr[data-tanggal]').forEach(row => {
        const rowDate = new Date(row.getAttribute('data-tanggal') + 'T00:00:00');
        let dateMatch = true;
        if (startDate && rowDate < startDate) dateMatch = false;
        if (endDate && rowDate > endDate) dateMatch = false;
        row.style.display = dateMatch ? '' : 'none';
    });

    updateCardTotals();
}

function resetFilter() {
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    filterData();
}

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
</script>