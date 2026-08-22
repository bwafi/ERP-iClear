<?php
$grouped_penjualan = [];
foreach ($detail_penjualan as $row) {
    $grouped_penjualan[$row->kode_invoice][] = $row;
}
?>

<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Riwayat Penjualan</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Riwayat</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Penjualan</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="card-body px-4 pt-4 pb-2 mb-1"></div>

    <form action="<?php echo base_url('riwayat_penjualan/export') ?>" method="post" enctype="multipart/form-data">
        <button type="submit" class="btn btn-danger"
            style="margin-left: 20px; display: inline-flex; align-items: center;">
            <iconify-icon icon="solar:export-broken" width="24" height="24" style="margin-right: 8px;"></iconify-icon>
            Export
        </button>

        <br>
        <br>

        <!-- Filter Tanggal -->
        <div class="mb-3 px-4">
            <label class="me-2">Nama Unit:</label>
            <select name="unit" id="unitFilter" class="form-select d-inline" style="width: auto; display: inline-block;"
                onchange="filterData()">
                <option value="">Semua Unit</option>
                <?php
                $unitList = [];
                foreach ($grouped_penjualan as $items) {
                    $unitName = $items[0]->NAMA_UNIT;
                    if (!in_array($unitName, $unitList)) {
                        $unitList[] = $unitName;
                        echo '<option value="' . esc($unitName) . '">' . esc($unitName) . '</option>';
                    }
                }
                ?>
            </select>

            <label class="ms-3 me-2">Tanggal Awal:</label>
            <input name="tanggal_awal" type="date" id="startDate" class="form-control d-inline"
                style="width: auto; display: inline-block;" onchange="filterData()">

            <label class="ms-3 me-2">Tanggal Akhir:</label>
            <input name="tanggal_akhir" type="date" id="endDate" class="form-control d-inline"
                style="width: auto; display: inline-block;" onchange="filterData()">

            <button type="button" id="resetBtn" class="btn btn-sm btn-secondary ms-3">Reset</button>

        </div>
    </form>

    <div class="row px-4 mb-3">
        <div class="table-responsive mb-4 px-4">
            <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
                <thead class="text-dark fs-4">
                    <tr>
                        <th>Invoice</th>
                        <th>Tanggal</th>
                        <th>Nama Barang</th>
                        <th>Nama Unit</th>
                        <th style="text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($grouped_penjualan)): ?>
                    <?php foreach ($grouped_penjualan as $kode_invoice => $items): ?>
                    <?php $first = $items[0]; ?>
                    <tr>
                        <td><?= esc($kode_invoice) ?></td>
                        <td><?= esc(date('d-m-Y', strtotime($first->tanggal))) ?></td>
                        <td><?= esc($first->nama_barang) ?>, ...</td>
                        <td><?= esc($first->NAMA_UNIT) ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                data-bs-target="#modalDetail<?= esc($kode_invoice) ?>"
                                style="display: inline-flex; align-items: center;">
                                <iconify-icon icon="solar:folder-favourite-bookmark-broken" width="24" height="24">
                                </iconify-icon>
                                Lihat Detail
                            </button>

                            <?php
                            $adaPelanggan = false;

                            foreach ($items as $item) {
                                if (!empty($item->id_pelanggan) && $item->id_pelanggan != 0) {
                                    $adaPelanggan = true;
                                    break;
                                }
                            }
                            ?>

                            <?php if ($adaPelanggan): ?>
                                <a href="<?= base_url('riwayat_penjualan/struk/' . $kode_invoice) ?>" target="_blank">
                                    <button type="button" class="btn btn-sm btn-danger"
                                        style="display: inline-flex; align-items: center;">
                                        <iconify-icon icon="solar:folder-favourite-bookmark-broken" width="24" height="24">
                                        </iconify-icon>
                                        Cetak Struk
                                    </button>
                                </a>

                                <a href="<?= base_url('riwayat_penjualan/struk/' . $kode_invoice . '?mode=thermal') ?>"
                                    target="_blank">
                                    <button type="button" class="btn btn-sm btn-danger"
                                        style="display: inline-flex; align-items: center;">
                                        <iconify-icon icon="solar:folder-favourite-bookmark-broken" width="24" height="24">
                                        </iconify-icon>
                                        Cetak Struk (Thermal)
                                    </button>
                                </a>
                            <?php endif; ?>
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
</div>

<?php foreach ($grouped_penjualan as $kode_invoice => $items): ?>
<!-- Modal for each invoice -->
<div class="modal fade" id="modalDetail<?= esc($kode_invoice) ?>" tabindex="-1"
    aria-labelledby="modalLabel<?= esc($kode_invoice) ?>" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Penjualan - Invoice <?= esc($kode_invoice) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered detail-table" id="detailTable<?= esc($kode_invoice) ?>">
                        <thead>
                            <tr>
                                <th>Nama Barang</th>
                                <th>Jumlah</th>
                                <th>HPP</th>
                                <th>Harga Jual</th>
                                <th>Diskon</th>
                                <th>SubTotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <?= esc($item->nama_barang) ?><br>
                                    <small class="text-muted"><?= esc($item->imei) ?></small>
                                </td>

                                <td><?= esc($item->jumlah) ?></td>
                                <td><?= esc(number_format($item->hpp_penjualan, 0, ',', '.')) ?></td>
                                <td><?= esc(number_format($item->harga_penjualan, 0, ',', '.')) ?></td>
                                <td><?= esc(number_format($item->diskon_penjualan, 0, ',', '.')) ?></td>
                                <td><?= esc(number_format($item->sub_total, 0, ',', '.')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php 
                    $total_ppn = $items[0]->total_ppn;
                ?>
                <div class="text-end mt-3">
                    <strong>Total PPN:</strong>
                    <span class="badge bg-primary">
                        <?= number_format($total_ppn, 0, ',', '.') ?>
                    </span>
                </div>


            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
$(document).ready(function() {
    $('#zero_config').DataTable();
    $('#detailTable').DataTable({

    });

});
</script>

<!-- JavaScript untuk filter -->
<script>
$(document).ready(function() {
    var table = $('#zero_config').DataTable();

    // Custom filter untuk tanggal + unit
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        const start = $('#startDate').val();
        const end = $('#endDate').val();
        const selectedUnit = $('#unitFilter').val().toLowerCase();

        // Ambil kolom Tanggal (index 1) dan Unit (index 3)
        const dateText = data[1];
        const unitName = data[3].toLowerCase();

        // Parse tanggal (dd-mm-yyyy → yyyy-mm-dd)
        const parts = dateText.split('-');
        const rowDate = new Date(`${parts[2]}-${parts[1]}-${parts[0]}`);

        const startDate = start ? new Date(start) : null;
        const endDate = end ? new Date(end) : null;

        // Cek tanggal sesuai filter
        let dateMatch = true;
        if (startDate && rowDate < startDate) dateMatch = false;
        if (endDate && rowDate > endDate) dateMatch = false;

        // Cek unit sesuai filter
        let unitMatch = (selectedUnit === "" || unitName === selectedUnit);

        return dateMatch && unitMatch;
    });

    // Apply filter setiap kali input berubah
    $('#startDate, #endDate, #unitFilter').on('change', function() {
        table.draw();
    });

    // Reset filter
    $('#resetBtn').on('click', function() {
        $('#startDate').val('');
        $('#endDate').val('');
        $('#unitFilter').val('');
        table.draw();
    });

    // Set default value awal (15 hari terakhir)
    const today = new Date();
    const fifteenDaysAgo = new Date();
    fifteenDaysAgo.setDate(today.getDate() - 15);

    const toDateInputValue = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    $('#startDate').val(toDateInputValue(fifteenDaysAgo));
    $('#endDate').val(toDateInputValue(today));

    // Trigger filter pertama kali
    table.draw();
});
</script>