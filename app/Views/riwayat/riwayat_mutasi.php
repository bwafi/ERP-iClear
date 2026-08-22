<?php
$grouped_mutasi = [];
foreach ($detail_mutasi as $row) {
    $grouped_mutasi[$row->no_nota_mutasi][] = $row;
}
?>

<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Riwayat Mutasi Stok</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none"
                        href="<?= base_url('/') ?>">Riwayat</a></li>
                <li class="breadcrumb-item active" aria-current="page">Mutasi Stok</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="card-body px-4 pt-4 pb-2 mb-1"></div>

    <form action="<?php echo base_url('riwayat_mutasi/export') ?>" method="post" enctype="multipart/form-data">
        <button type="submit" class="btn btn-danger" style="margin-left: 20px; display: inline-flex; align-items: center;">
            <iconify-icon icon="solar:export-broken" width="24" height="24" style="margin-right: 8px;"></iconify-icon>
            Export
        </button>
        <br><br>


        <!-- Filter Tanggal & Unit -->
        <div class="mb-3 px-4">
            <label class="me-2">Unit Asal:</label>
            <select name="unit" id="unitFilter" class="form-select d-inline" style="width: auto; display: inline-block;"
                onchange="filterData()">
                <option value="">Semua Unit</option>
                <?php
                $unitList = [];
                foreach ($grouped_mutasi as $items) {
                    $unitName = $items[0]->nama_unit_kirim;
                    if (!in_array($unitName, $unitList)) {
                        $unitList[] = $unitName;
                        echo '<option value="' . esc($unitName) . '">' . esc($unitName) . '</option>';
                    }
                }
                ?>
            </select>

            <label class="ms-3 me-2">Tanggal Awal:</label>
            <input name="tanggal_awal" type="date" id="startDate" class="form-control d-inline" style="width: auto; display: inline-block;"
                onchange="filterData()">

            <label class="ms-3 me-2">Tanggal Akhir:</label>
            <input name="tanggal_akhir" type="date" id="endDate" class="form-control d-inline" style="width: auto; display: inline-block;"
                onchange="filterData()">

            <button type="button" onclick="resetFilter()" class="btn btn-sm btn-secondary ms-3">Reset</button>
        </div>
    </form>

    <div class="row px-4 mb-3">
        <div class="table-responsive mb-4 px-4">
            <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
                <thead class="text-dark fs-4">
                    <tr>
                        <th>No. Mutasi</th>
                        <th>Tanggal Kirim</th>
                        <th>Unit Asal</th>
                        <th>Unit Tujuan</th>
                        <th>Barang</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($grouped_mutasi)): ?>
                        <?php foreach ($grouped_mutasi as $no_nota => $items): ?>
                            <?php $first = $items[0]; ?>
                            <tr>
                                <td><?= esc($no_nota) ?></td>
                                <td><?= esc(date('d-m-Y', strtotime($first->mutasi_tanggal_kirim))) ?></td>
                                <td><?= esc($first->nama_unit_kirim) ?></td>
                                <td><?= esc($first->nama_unit_terima) ?></td>
                                <td><?= esc($first->nama_barang) ?>, ...</td>
                                <td>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#modalDetail<?= esc($no_nota) ?>">
                                        <iconify-icon icon="solar:folder-favourite-bookmark-broken" width="24" height="24">
                                        </iconify-icon>
                                        Lihat Detail
                                    </button>
                                    <a href="<?php echo base_url('cetak/invoice_mutasi/' . $first->mutasi_idmutasi) ?>">
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
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">Tidak ada data</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Detail per Mutasi -->
<?php foreach ($grouped_mutasi as $no_nota => $items): ?>
    <div class="modal fade" id="modalDetail<?= esc($no_nota) ?>" tabindex="-1"
        aria-labelledby="modalLabel<?= esc($no_nota) ?>" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Mutasi - No. <?= esc($no_nota) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Nama Barang</th>
                                <th>Jumlah Kirim</th>
                                <th>Jumlah Terima</th>
                                <th>Satuan</th>
                                <th>HPP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= esc($item->nama_barang) ?></td>
                                    <td><?= esc($item->jumlah_kirim) ?></td>
                                    <td><?= esc($item->jumlah_terima) ?></td>
                                    <td><?= esc($item->satuan) ?></td>
                                    <td><?= esc(number_format($item->hpp_barang, 0, ',', '.')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- DataTables -->
<script>
    $(document).ready(function() {
        $('#zero_config').DataTable();
    });
</script>

<!-- Filter Script -->
<script>
    let dataTable; // simpan instance DataTables global

    window.onload = function() {
        // Inisialisasi DataTables
        dataTable = $('#zero_config').DataTable();

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
            // Kolom ke-1 = tanggal, Kolom ke-2 = unit (index mulai dari 0)
            const dateText = data[1].trim();
            const parts = dateText.split('-');
            const rowDate = new Date(`${parts[2]}-${parts[1]}-${parts[0]}`);

            const startDate = start ? new Date(start) : null;
            const endDate = end ? new Date(end) : null;

            const unitName = data[2].trim().toLowerCase(); // ambil unit dari kolom tabel
            const unitMatch = selectedUnit === "" || unitName === selectedUnit;

            let dateMatch = true;
            if (startDate && rowDate < startDate) dateMatch = false;
            if (endDate && rowDate > endDate) dateMatch = false;

            return (unitMatch && dateMatch);
        });

        dataTable.draw(); // refresh DataTable
    }

    function resetFilter() {
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';
        document.getElementById('unitFilter').value = '';

        $.fn.dataTable.ext.search = []; // hapus semua filter
        dataTable.draw();
    }
</script>