<!-- Page Header -->
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Laporan Jurnal</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Laporan Jurnal</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Laporan Jurnal</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Server-side filters keep Neraca drill-downs on the selected account and cutoff. -->
<form action="<?= base_url('laporan_jurnal') ?>" method="get" class="card card-body mb-3">
    <div class="row g-3 align-items-end">
        <div class="col-sm-6 col-lg-2"><label for="startDate" class="form-label">Tanggal awal</label><input name="startDate" id="startDate" type="date" class="form-control" value="<?= esc($journal_filters['startDate']) ?>"></div>
        <div class="col-sm-6 col-lg-2"><label for="endDate" class="form-label">Tanggal akhir</label><input name="endDate" id="endDate" type="date" class="form-control" value="<?= esc($journal_filters['endDate']) ?>"></div>
        <div class="col-sm-6 col-lg-2"><label for="unitSelect" class="form-label">Unit</label><select name="filterUnit" id="unitSelect" class="form-select"><option value="">Semua unit</option><?php foreach ($data_unit as $unit): ?><option value="<?= esc($unit->idunit) ?>" <?= (string) $journal_filters['filterUnit'] === (string) $unit->idunit ? 'selected' : '' ?>><?= esc($unit->NAMA_UNIT) ?></option><?php endforeach; ?></select></div>
        <div class="col-sm-6 col-lg-2"><label for="filterAccount" class="form-label">Nomor akun</label><input name="no_akun" id="filterAccount" class="form-control" value="<?= esc($journal_filters['no_akun']) ?>"></div>
        <div class="col-sm-6 col-lg-2"><label for="filterReferenceTable" class="form-label">Tabel sumber</label><input name="tabel_referensi" id="filterReferenceTable" class="form-control" value="<?= esc($journal_filters['tabel_referensi']) ?>"></div>
        <div class="col-sm-6 col-lg-2"><label for="filterReferenceId" class="form-label">ID sumber</label><input name="id_referensi" id="filterReferenceId" class="form-control" value="<?= esc($journal_filters['id_referensi']) ?>"></div>
    </div>
    <div class="mt-3 d-flex gap-2"><button type="submit" class="btn btn-primary">Tampilkan</button><a href="<?= base_url('laporan_jurnal') ?>" class="btn btn-outline-secondary">Reset</a></div>
</form>

<div class="mb-3">
    <form action="<?= base_url('export_jurnal') ?>" method="post">
        <input type="hidden" name="tanggal_awal" value="<?= esc($journal_filters['startDate']) ?>">
        <input type="hidden" name="tanggal_akhir" value="<?= esc($journal_filters['endDate']) ?>">
        <input type="hidden" name="nama_unit" id="exportUnit" value="">
        <button type="submit" class="btn btn-danger">Export</button>
    </form>
</div>

<div class="card w-100 position-relative overflow-hidden">

    <!-- Summary Cards -->
    <div class="row mx-3 mb-3">
        <div class="col-lg-6 col-md-6">
            <div class="card text-bg-primary text-white">
                <div class="card-body d-flex no-block align-items-center">
                    <iconify-icon icon="solar:cart-2-linear" width="48" height="48" style="color: #fff"></iconify-icon>
                    <div class="ms-3 mt-2">
                        <h4 class="mb-0 text-white">Total Debet</h4>
                        <h5 class="text-white" id="totalDebet">Rp. 0</h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 col-md-6">
            <div class="card text-bg-danger text-white">
                <div class="card-body d-flex no-block align-items-center">
                    <iconify-icon icon="solar:cart-2-linear" width="48" height="48" style="color: #fff"></iconify-icon>
                    <div class="ms-3 mt-2">
                        <h4 class="mb-0 text-white">Total Kredit</h4>
                        <h5 class="text-white" id="totalKredit">Rp. 0</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" id="jurnal_table">
            <thead class="text-dark fs-4">
                <tr>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Tanggal</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nomor Akun</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Akun & Keterangan</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Debet</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Kredit</h6>
                    </th>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Unit</h6>
                    </th>
                    <th><h6 class="fs-4 fw-semibold mb-0">Tabel sumber</h6></th>
                    <th><h6 class="fs-4 fw-semibold mb-0">ID sumber</h6></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jurnal as $row): ?>
                <tr data-unit="<?= esc($row->id_unit) ?>">
                    <td data-raw="<?= esc($row->tanggal) ?>"><?= esc(date('d-m-Y', strtotime($row->tanggal))) ?></td>
                    <td><?= esc($row->no_akun) ?></td>
                    <td>
                        <i><?= esc($row->nama_akun) ?></i>
                        <br>
                        <b>
                            <?= esc($row->keterangan) ?>
                        </b>
                    </td>
                    <td class="debet-cell" data-value="<?= esc($row->debet) ?>"></td>
                    <td class="kredit-cell" data-value="<?= esc($row->kredit) ?>"></td>
                    <td><?= esc($row->NAMA_UNIT) ?></td>
                    <td><?= esc($row->tabel_referensi ?? '') ?></td>
                    <td><?= esc($row->id_referensi ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const today = new Date();
    const fifteenDaysAgo = new Date();
    fifteenDaysAgo.setDate(today.getDate() - 15);

    const toDateInputValue = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const hasSpecificFilter = ['filterAccount', 'filterReferenceTable', 'filterReferenceId']
        .some(id => document.getElementById(id).value.trim() !== '');
    if (!document.getElementById('startDate').value && !document.getElementById('endDate').value && !hasSpecificFilter) {
        document.getElementById('startDate').value = toDateInputValue(fifteenDaysAgo);
        document.getElementById('endDate').value = toDateInputValue(today);
    }

    sortTableByDateDesc();
    formatTableCurrency();
    filterData();
});

function sortTableByDateDesc() {
    const table = document.getElementById("jurnal_table");
    const tbody = table.querySelector("tbody");
    const rows = Array.from(tbody.querySelectorAll("tr"));

    rows.sort((a, b) => {
        const dateA = new Date(a.cells[0].getAttribute("data-raw"));
        const dateB = new Date(b.cells[0].getAttribute("data-raw"));
        return dateB - dateA;
    });

    rows.forEach(row => tbody.appendChild(row));
}

function formatRupiah(angka) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(angka);
}

function formatTableCurrency() {
    document.querySelectorAll('.debet-cell').forEach(cell => {
        const val = parseFloat(cell.getAttribute('data-value')) || 0;
        cell.textContent = formatRupiah(val);
    });

    document.querySelectorAll('.kredit-cell').forEach(cell => {
        const val = parseFloat(cell.getAttribute('data-value')) || 0;
        cell.textContent = formatRupiah(val);
    });
}

function filterData() {
    const start = document.getElementById('startDate').value;
    const end = document.getElementById('endDate').value;
    const selectedUnit = document.getElementById('unitSelect').value.toLowerCase();
    const rows = document.querySelectorAll('#jurnal_table tbody tr');

    let totalDebet = 0;
    let totalKredit = 0;

    rows.forEach(row => {
        const dateCell = row.children[0];
        const debetCell = row.children[3];
        const kreditCell = row.children[4];
        const unitCell = row.children[5];

        if (!dateCell || !unitCell) return;

        const dateText = dateCell.getAttribute('data-raw');
        const unitId = row.getAttribute('data-unit') || '';

        const rowDate = new Date(dateText);
        const startDate = start ? new Date(start) : null;
        const endDate = end ? new Date(end) : null;

        let visible = true;
        if (startDate && rowDate < startDate) visible = false;
        if (endDate && rowDate > endDate) visible = false;
        if (selectedUnit && unitId !== selectedUnit) visible = false;

        row.style.display = visible ? '' : 'none';

        const debet = parseFloat(debetCell.getAttribute('data-value')) || 0;
        const kredit = parseFloat(kreditCell.getAttribute('data-value')) || 0;

        if (visible) {
            totalDebet += debet;
            totalKredit += kredit;
        }
    });

    document.getElementById('totalDebet').textContent = formatRupiah(totalDebet);
    document.getElementById('totalKredit').textContent = formatRupiah(totalKredit);
    const unitSelect = document.getElementById('unitSelect');
    document.getElementById('exportUnit').value = unitSelect.selectedOptions[0]?.textContent === 'Semua unit'
        ? ''
        : unitSelect.selectedOptions[0]?.textContent || '';
}

function resetFilter() {
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    document.getElementById('unitSelect').value = '';
    formatTableCurrency();
    filterData();
}
</script>

<!-- DataTable Script (NEW) -->
<script>
document.addEventListener("DOMContentLoaded", function() {

    // Custom date sorting using data-raw
    $.fn.dataTable.ext.order['tanggal-sort'] = function(settings, colIndex) {
        return this.api()
            .column(colIndex, {
                order: 'index'
            })
            .nodes()
            .map(function(td) {
                return new Date(td.getAttribute('data-raw')).getTime();
            });
    };

    // Initialize DataTable
    $('#jurnal_table').DataTable({
        paging: true,
        searching: true,
        info: true,
        order: [
            [0, 'desc']
        ],
        columnDefs: [{
            targets: 0,
            orderDataType: "tanggal-sort"
        }]
    });

});
</script>
