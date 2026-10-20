<?php
$grouped_pembelian = [];
foreach ($detail_pembelian as $row) {
    $grouped_pembelian[$row->no_batch][] = $row;
}
?>

<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Retur Pembelian</h4>
        <a class="btn btn-outline-primary" href="<?= base_url('kredit_retur_suplier') ?>">Kredit Supplier</a>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Retur</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Pembelian</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="alert alert-info m-4 mb-0">
        Catat barang di <a href="<?= base_url('barang_rusak') ?>">Barang Rusak</a> terlebih dahulu dengan supplier dan nota asal yang sesuai.
        Jumlah siap retur adalah sisa Barang Rusak yang belum pernah diretur. Pengiriman retur tidak mengurangi stok jual lagi.
    </div>
    <div class="px-4 py-3 border-bottom"></div>

    <div class="card-body px-4 pt-4 pb-2 d-flex justify-content-between align-items-start mb-1">
        <div class="d-flex gap-2"></div>
    </div>

    <!-- Filter Tanggal -->
    <div class="mb-3 px-4">
        <label class="me-2">Nama Unit:</label>
        <select id="unitFilter" class="form-select d-inline" style="width: auto; display: inline-block;" onchange="filterData()">
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
        <input type="date" id="startDate" class="form-control d-inline" style="width: auto; display: inline-block;" onchange="filterData()">

        <label class="ms-3 me-2">Tanggal Akhir:</label>
        <input type="date" id="endDate" class="form-control d-inline" style="width: auto; display: inline-block;" onchange="filterData()">

        <button onclick="resetFilter()" class="btn btn-sm btn-secondary ms-3">Reset</button>
    </div>

    <div class="row px-4 mb-3">
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
                        <th>Supplier</th>
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
                                <td><?= esc($row->nama_barang) ?>, ...</td>
                                <td><?= esc($row->NAMA_UNIT) ?></td>
                                <td><?= esc($row->nama_suplier ?: 'Supplier belum tercatat') ?></td>
                                <td><?= esc(number_format($row->total_harga, 0, ',', '.')) ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#modalDetail<?= esc($no_batch) ?>">
                                        <iconify-icon icon="solar:folder-favourite-bookmark-broken" width="24" height="24">
                                        </iconify-icon>
                                        Lihat Detail
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php foreach ($grouped_pembelian as $no_batch => $items): ?>
        <div class="modal fade" id="modalDetail<?= esc($no_batch) ?>" tabindex="-1"
            aria-labelledby="modalLabel<?= esc($no_batch) ?>" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <form action="<?= base_url('insert_retur_suplier') ?>" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="no_batch" value="<?= esc($no_batch) ?>">

                        <div class="modal-header">
                            <h5 class="modal-title">Detail pembelian - No. Batch <?= esc($no_batch) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Supplier:</strong> <?= esc($items[0]->nama_suplier ?: 'Supplier belum tercatat') ?></p>
                            <p class="text-muted">Pilih hanya barang yang siap diretur. Retur lama pada batch yang sama perlu direkonsiliasi terlebih dahulu.</p>
                            <?php
                            $idUnit = session('ID_UNIT');
                            $tanggalSekarang = date('Y-m-d');
                            ?>



                            <div style="display: flex; align-items: center; gap: 10px;">
                                <label style="font-weight: bold;" for="tanggal_pengembalian">
                                    Tanggal Pengembalian :
                                </label>

                                <input
                                    id="tanggal_pengembalian"
                                    style="width: 80%;"
                                    type="date"
                                    name="tanggal_pengembalian"
                                    class="form-control"
                                    value="<?= $idUnit == 1 ? '' : $tanggalSekarang ?>"
                                    <?= $idUnit == 1 ? '' : 'readonly' ?>>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered detail-table" id="detailTable<?= esc($no_batch) ?>">
                                    <thead>
                                        <tr>
                                            <th>Pilih</th>
                                            <th>Nama Barang</th>
                                            <th>Jumlah</th>
                                            <th>Siap Retur</th>
                                            <th>Harga Beli</th>
                                            <th>Diskon</th>
                                            <th>SubTotal</th>
                                            <th>Jumlah Retur</th>
                                            <th>Satuan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $index => $item): ?>
                                            <?php $capacity = $return_availability[(int) $item->iddetail_pembelian] ?? ['returnable' => 0, 'reason' => 'Retur hanya dapat dicatat dari unit akun yang sama dengan pembelian.']; ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox"
                                                        class="form-check-input pilih-retur"
                                                        name="items[<?= $index ?>][selected]"
                                                        <?= $capacity['returnable'] > 0 ? '' : 'disabled' ?>
                                                        value="1">

                                                    <input type="hidden" name="items[<?= $index ?>][iddetail_pembelian]"
                                                        value="<?= esc($item->iddetail_pembelian) ?>">
                                                </td>
                                                <td><?= esc($item->nama_barang) ?></td>
                                                <td><?= esc($item->jumlah) ?></td>
                                                <td>
                                                    <strong><?= (int) $capacity['returnable'] ?> unit</strong>
                                                    <?php if ($capacity['reason'] !== ''): ?><div class="small text-muted text-wrap" style="min-width:200px"><?= esc($capacity['reason']) ?></div><?php endif; ?>
                                                </td>
                                                <td><?= esc(number_format($item->hrg_beli, 0, ',', '.')) ?></td>
                                                <td><?= esc(number_format($item->diskon, 0, ',', '.')) ?></td>
                                                <td><?= esc(number_format($item->total_harga, 0, ',', '.')) ?></td>
                                                <td>
                                                    <input type="number"
                                                        class="form-control jumlah-retur"
                                                        name="items[<?= $index ?>][jumlah_retur]"
                                                        min="1"
                                                        max="<?= (int) $capacity['returnable'] ?>"
                                                        disabled>
                                                </td>

                                                <td>
                                                    <input readonly type="text" name="items[<?= $index ?>][satuan]" value="<?= esc($item->satuan_beli) ?>" class="form-control">
                                                    <input hidden type="text" name="items[<?= $index ?>][jumlah]" value="<?= esc($item->jumlah) ?>" class="form-control">
                                                    <input hidden type="text" name="items[<?= $index ?>][barang_idbarang]" value="<?= esc($item->barang_idbarang) ?>" class="form-control">
                                                    <input hidden type="text" name="items[<?= $index ?>][iddetail_pembelian]" value="<?= esc($item->iddetail_pembelian) ?>" class="form-control">
                                                    <input hidden type="text" name="items[<?= $index ?>][unit_idunit]" value="<?= esc($item->unit_idunit) ?>" class="form-control">

                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <?php $hasReturnable = count(array_filter($items, static fn ($detail) => ($return_availability[(int) $detail->iddetail_pembelian]['returnable'] ?? 0) > 0)) > 0; ?>
                            <button type="submit" class="btn btn-success" <?= $hasReturnable ? '' : 'disabled' ?>><?= $hasReturnable ? 'Simpan Retur' : 'Belum Ada Barang Siap Retur' ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
        $(document).ready(function() {

            $('.detail-table').each(function() {
                $(this).DataTable();
            });
        });
    </script>
</div>

<!-- JavaScript untuk filter -->
<script>
    let table;

    window.onload = function() {
        table = $('#zero_config').DataTable();

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
    };

    function filterData() {
        const start = document.getElementById('startDate').value;
        const end = document.getElementById('endDate').value;
        const selectedUnit = document.getElementById('unitFilter').value.toLowerCase();

        $.fn.dataTable.ext.search = []; // reset filter lama

        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'zero_config') return true;
            // Ambil data dari kolom (sesuaikan index kolommu!)
            const dateText = data[1]; // kolom tanggal
            const unitName = data[3].toLowerCase(); // kolom unit

            // Parse tanggal (karena di view kamu format d-m-Y)
            const parts = dateText.split('-');
            const rowDate = new Date(`${parts[2]}-${parts[1]}-${parts[0]}`);

            const startDate = start ? new Date(start) : null;
            const endDate = end ? new Date(end) : null;

            // Filter unit
            const unitMatch = selectedUnit === "" || unitName === selectedUnit;

            // Filter tanggal
            let dateMatch = true;
            if (startDate && rowDate < startDate) dateMatch = false;
            if (endDate && rowDate > endDate) dateMatch = false;

            return unitMatch && dateMatch;
        });

        table.draw(); // refresh datatable
    }

    function resetFilter() {
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';
        document.getElementById('unitFilter').value = '';
        filterData();
    }
</script>

<script>
    $(document).on('change', '.pilih-retur', function() {
        const row = $(this).closest('tr');
        const inputJumlah = row.find('.jumlah-retur');

        if ($(this).is(':checked')) {
            inputJumlah.prop('disabled', false);
            inputJumlah.prop('required', true);
            inputJumlah.focus();
        } else {
            inputJumlah.prop('disabled', true);
            inputJumlah.prop('required', false);
            inputJumlah.val('');
        }
    });
</script>
