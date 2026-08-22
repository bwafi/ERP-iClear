<style>
.table-scroll {
    max-height: 400px; /* tinggi area scroll */
    overflow-y: auto;
}

.table-scroll thead th {
    position: sticky;
    top: 0;
    background: white;
    z-index: 2;
}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js"></script>

<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Kartu Stok</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Stok</a>
                </li>
                <li class="breadcrumb-item" aria-current="page">Kartu Stok</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom"></div>

    <form action="<?php echo base_url('export/kartu_stock') ?>" method="post" enctype="multipart/form-data">
        <div class="card-body px-4 pt-4 pb-2 d-flex justify-content-between align-items-start mb-1">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger" style="display: inline-flex; align-items: center;">
                    <iconify-icon icon="solar:export-broken" width="24" height="24" style="margin-right: 8px;">
                    </iconify-icon>
                    Export
                </button>
            </div>
        </div>
        <br>

        <div class="mb-3 px-4">
            <label class="me-2">Filter PPN:</label>
            <select name="status_ppn" id="ppnFilter" class="form-select d-inline"
                style="width: auto; display: inline-block;" onchange="filterKategori()">
                <option value="">Semua</option>
                <option value="PPN">PPN</option>
                <option value="Non PPN">Non PPN</option>
            </select>

            <label class="me-2 ms-4">Nama Unit:</label>
            <select name="unit" id="unitFilter" class="form-select d-inline" style="width: auto; display: inline-block;"
                onchange="filterKategori()">
                <option value="">Semua Unit</option>
                <?php
                $selectedUnit = session('ID_UNIT');
                foreach ($unit as $row) {
                    $selected = ($row->idunit == $selectedUnit) ? 'selected' : '';
                    echo '<option value="' . esc($row->idunit) . '" ' . $selected . '>' . esc($row->NAMA_UNIT) . '</option>';
                }
                ?>
            </select>

            <!-- <label class="me-2 ms-4">Tanggal Awal:</label>
            <input name="tanggal_awal" type="date" id="startDate" class="form-control d-inline"
                style="width: auto; display: inline-block;" onchange="filterKategori()">

            <br><br>

            <label class="me-2 ms-2">Tanggal Akhir:</label>
            <input name="tanggal_akhir" type="date" id="endDate" class="form-control d-inline"
                style="width: auto; display: inline-block;" onchange="filterKategori()">

            <button type="button" onclick="resetKategoriFilter()" class="btn btn-sm btn-secondary ms-2">Reset</button> -->
        </div>
    </form>

    <div class="table-responsive mb-4 px-4 table-scroll">
        <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
            <thead class="text-dark fs-4">
                <tr>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Kode Barang</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama Barang</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama Unit</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Warna</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Jenis</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">HPP</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Harga Jual</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama Kategori</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Status PPN</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Stok Awal</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Total Pembelian</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Total Penjualan</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Total Retur Pelanggan</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Total Retur Suplier</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Total Mutasi Masuk</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Total Mutasi Keluar</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Stok Akhir</h6>
                    </th>
                </tr>
            </thead>
            <tbody id="produkTableBody">
                <?php if (!empty($stok)): ?>
                <?php foreach ($stok as $row): ?>
                <tr data-idunit="<?= esc($row->id_unit) ?>">
                    <td><?= esc($row->kode_barang) ?></td>
                    <td>
                        <?= esc($row->nama_barang) ?><br>
                        <small><?= esc($row->imei) ?></small>
                    </td>
                    <td><?= esc($row->nama_unit) ?></td>
                    <td><?= esc($row->warna) ?></td>
                    <td><?= esc($row->jenis_hp) ?></td>
                    <td><?= esc($row->harga_beli) ?></td>
                    <td><?= esc($row->harga) ?></td>
                    <td><?= esc($row->nama_kategori) ?></td>
                    <td><?= $row->status_ppn == 1 ? 'PPN' : 'Non PPN' ?></td>
                    <td><?= esc($row->stok_awal) ?></td>
                    <td><?= esc($row->total_pembelian) ?></td>
                    <td><?= esc($row->total_penjualan) ?></td>
                    <td><?= esc($row->total_retur_pelanggan) ?></td>
                    <td><?= esc($row->total_retur_supplier) ?></td>
                    <td><?= esc($row->total_mutasi_masuk) ?></td>
                    <td><?= esc($row->total_mutasi_keluar) ?></td>
                    <td><b><?= esc($row->stok_akhir) ?></b></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ✅ WORKING FILTER SCRIPT -->
<script>
$(document).ready(function() {
    const table = $('#zero_config').DataTable();

    // Custom filter logic
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (settings.nTable.id !== 'zero_config') return true;

        const ppnFilter = ($('#ppnFilter').val() || '').toLowerCase();
        const unitFilter = ($('#unitFilter').val() || '').trim();
        const start = $('#startDate').val();
        const end = $('#endDate').val();

        const rowNode = table.row(dataIndex).node();
        const unitIdInTable = $(rowNode).attr('data-idunit') || '';
        const tanggalAttr = ($(rowNode).attr('data-tanggal') || '').trim();

        const ppn = (data[8] || '').toLowerCase(); // kolom Status PPN

        // Parse date from data-tanggal
        let rowDate = null;
        if (tanggalAttr) {
            if (tanggalAttr.match(/^\d{4}-\d{2}-\d{2}$/)) {
                rowDate = new Date(tanggalAttr);
            } else if (tanggalAttr.match(/^\d{2}-\d{2}-\d{4}$/)) {
                const parts = tanggalAttr.split('-');
                rowDate = new Date(`${parts[2]}-${parts[1]}-${parts[0]}`);
            }
        }

        const startDate = start ? new Date(start) : null;
        const endDate = end ? new Date(end) : null;

        const matchUnit = !unitFilter || unitIdInTable == unitFilter;
        const matchPPN = !ppnFilter || ppn === ppnFilter;

        let matchDate = true;
        if (rowDate instanceof Date && !isNaN(rowDate)) {
            if (startDate && rowDate < startDate) matchDate = false;
            if (endDate && rowDate > endDate) matchDate = false;
        }

        return matchUnit && matchPPN && matchDate;
    });

    // Filter apply and reset
    window.filterKategori = function() {
        table.draw();
    };

    window.resetKategoriFilter = function() {
        $('#ppnFilter').val('');
        $('#startDate').val('');
        $('#endDate').val('');
        $('#unitFilter').val('');
        table.search('').columns().search('').draw();
    };
});
</script>

<!-- ✅ CURRENCY FORMATTER -->
<script>
document.querySelectorAll('.currency').forEach(function(el) {
    new Cleave(el, {
        numeral: true,
        numeralThousandsGroupStyle: 'thousand'
    });
});
</script>

<script>
    const slider = document.querySelector('.table-scroll')

    let isDown = false
    let startX
    let scrollLeft

    slider.addEventListener('mousedown', (e) => {
        isDown = true
        slider.classList.add('active')
        startX = e.pageX - slider.offsetLeft
        scrollLeft = slider.scrollLeft
    })

    slider.addEventListener('mouseleave', () => {
        isDown = false
    })

    slider.addEventListener('mouseup', () => {
        isDown = false
    })

    slider.addEventListener('mousemove', (e) => {
        if (!isDown) return
        e.preventDefault()
        const x = e.pageX - slider.offsetLeft
        const walk = (x - startX) * 2
        slider.scrollLeft = scrollLeft - walk
    })
</script>