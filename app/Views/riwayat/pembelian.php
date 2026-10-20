<?php
$grouped_pembelian = [];
foreach ($detail_pembelian as $row) {
    $grouped_pembelian[$row->no_batch][] = $row;
}
?>


<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Riwayat Pembelian</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Riwayat</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Pembelian</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom"></div>

    <div class="card-body px-4 pt-4 pb-2 d-flex justify-content-between align-items-start mb-1">
        <div class="d-flex gap-2"></div>
    </div>

    <div class="row px-4 mb-3">

        <!-- <form method="get" action="<?= base_url('riwayat_pembelian') ?>">
            <div class="row px-4 mb-3">
                <div class="col-md-3">
                    <label for="tanggal_awal" class="form-label">Tanggal Awal</label>
                    <input type="date" id="tanggal_awal" name="tanggal_awal" class="form-control" value="<?= esc($_GET['tanggal_awal'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                    <input type="date" id="tanggal_akhir" name="tanggal_akhir" class="form-control" value="<?= esc($_GET['tanggal_akhir'] ?? '') ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </div>
        </form> -->

        <!-- for export -->
        <form action="<?php echo base_url('riwayat_pembelian/export') ?>" method="post" enctype="multipart/form-data">
            <button type="submit" class="btn btn-danger" style="display: inline-flex; align-items: center;">
                <iconify-icon icon="solar:export-broken" width="24" height="24" style="margin-right: 8px;"></iconify-icon>
                Export
            </button>

            <br>
            <br>

            <!-- Filter Tanggal -->
            <div class="mb-3 px-4">
                <label class="me-2">Nama Unit:</label>
                <select id="unitFilter" class="form-select d-inline" style="width: auto; display: inline-block;" name="unit" onchange="filterData()">
                    <option value="">Semua Unit</option>
                    <?php
                    $unitList = [];
                    foreach ($grouped_pembelian as $items) {
                        $unitName = $items[0]->NAMA_UNIT;
                        if (!in_array($unitName, $unitList)) {
                            $unitList[] = $unitName;
                            echo '<option value="' . esc($unitName) . '">' . esc($unitName) . '</option>';
                        }
                    }
                    ?>
                </select>

                <label class="ms-3 me-2">Tanggal Awal:</label>
                <input type="date" id="startDate" class="form-control d-inline" name="tanggal_awal" style="width: auto; display: inline-block;" onchange="filterData()">

                <label class="ms-3 me-2">Tanggal Akhir:</label>
                <input type="date" id="endDate" class="form-control d-inline" name="tanggal_akhir" style="width: auto; display: inline-block;" onchange="filterData()">

                <button onclick="resetFilter()" type="button" class="btn btn-sm btn-secondary ms-3">Reset</button>
            </div>

        </form>


        <div class="table-responsive mb-4 px-4">
            <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
                <thead class="text-dark fs-4">
                    <tr>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">No. Batch</h6>
                        </th>

                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Tanggal</h6>
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Nama Barang</h6>
                        </th>

                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Nama Unit</h6>
                        </th>

                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Sumber</h6>
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Nama Supplier/Pelanggan</h6>
                        </th>

                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Total Harga</h6>
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Action</h6>
                        </th>

                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($grouped_pembelian)): ?>
                        <?php foreach ($grouped_pembelian as $no_batch => $items): ?>
                            <?php $row = $items[0]; ?>
                            <tr>
                                <td><?= esc($row->no_batch) ?></td>

                                <td><?= esc(date('d-m-Y', strtotime($row->tanggal))) ?></td>
                                <td><?= esc($row->nama_barang) ?></td>
                                <td><?= esc($row->NAMA_UNIT) ?></td>
                                <td><?= empty($row->suplier_id_suplier) ? 'Pelanggan' : 'Supplier' ?></td>
                                <td><?= esc(empty($row->suplier_id_suplier) ? ($row->nama_pelanggan ?? 'Tidak tercatat') : ($row->nama_suplier ?? 'Tidak tercatat')) ?></td>
                                <td><?= esc(number_format($row->total_harga, 0, ',', '.')) ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#modalDetail<?= esc($no_batch) ?>"
                                        style="display: inline-flex; align-items: center;">
                                        <iconify-icon icon="solar:folder-favourite-bookmark-broken" width="24" height="24">
                                        </iconify-icon>
                                        Lihat Detail
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Tidak ada data</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>


    <?php foreach ($grouped_pembelian as $no_batch => $items): ?>
        <!-- Modal for each invoice -->
        <div class="modal fade" id="modalDetail<?= esc($no_batch) ?>" tabindex="-1"
            aria-labelledby="modalLabel<?= esc($no_batch) ?>" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Detail pembelian - No. Batch <?= esc($no_batch) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-bordered detail-table" id="detailTable<?= esc($no_batch) ?>">
                                <thead>
                                    <tr>
                                        <th>Nama Barang</th>
                                        <th>Jumlah</th>
                                        <th>Harga Beli</th>
                                        <th>Diskon</th>
                                        <th>SubTotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?= esc($item->nama_barang) ?></td>
                                            <td><?= esc($item->jumlah) ?></td>
                                            <td><?= esc(number_format($item->hrg_beli, 0, ',', '.')) ?></td>
                                            <td><?= esc(number_format($item->diskon, 0, ',', '.')) ?></td>
                                            <td><?= esc(number_format($item->total_harga, 0, ',', '.')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>






    <script>
        let table;
        const purchaseHistoryFilter = function(settings, data) {
            if (settings.nTable.id !== 'zero_config') return true;

            const dateText = data[1] || '';
            const unitText = (data[3] || '').toLowerCase();
            const [day, month, year] = dateText.split('-').map(Number);
            const rowDate = new Date(year, month - 1, day);
            const start = document.getElementById('startDate').value;
            const end = document.getElementById('endDate').value;
            const selectedUnit = document.getElementById('unitFilter').value.toLowerCase();
            const startDate = start ? new Date(`${start}T00:00:00`) : null;
            const endDate = end ? new Date(`${end}T00:00:00`) : null;

            if (!Number.isFinite(rowDate.getTime())) return false;
            if (selectedUnit && unitText !== selectedUnit) return false;
            if (startDate && rowDate < startDate) return false;
            if (endDate && rowDate > endDate) return false;
            return true;
        };

        $(function() {
            table = $('#zero_config').DataTable();
            $('.detail-table').each(function() {
                if (!$.fn.dataTable.isDataTable(this)) $(this).DataTable();
            });

            if (!$.fn.dataTable.ext.search.includes(purchaseHistoryFilter)) {
                $.fn.dataTable.ext.search.push(purchaseHistoryFilter);
            }

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
            const unitSelect = document.getElementById('unitFilter');
            if (unitSelect.options.length > 1) {
                unitSelect.selectedIndex = 1;
            }
            filterData();
        });

        function filterData() {
            if (table) table.draw();
        }

        function resetFilter() {
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
            document.getElementById('unitFilter').value = '';
            filterData();
        }
    </script>
