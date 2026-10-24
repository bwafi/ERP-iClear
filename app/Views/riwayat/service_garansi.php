<?php
$hariIni = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Jakarta'));
$awalBulan = $hariIni->modify('first day of this month')->format('Y-m-d');
$akhirBulan = $hariIni->modify('last day of this month')->format('Y-m-d');
?>
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Riwayat Service</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Riwayat</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Service</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom">
    </div>

    <form action="<?php echo base_url('riwayat_service_garansi/export') ?>" method="post" enctype="multipart/form-data">
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
                style="width: auto; display: inline-block;" value="<?= esc($awalBulan) ?>" onchange="filterData()">

            <label class="ms-3 me-2">Tanggal Akhir:</label>
            <input name="tanggal_akhir" type="date" id="endDate" class="form-control d-inline"
                style="width: auto; display: inline-block;" value="<?= esc($akhirBulan) ?>" onchange="filterData()">

            <button type="button" onclick="resetFilter()" class="btn btn-sm btn-secondary ms-3">Reset</button>
        </div>
    </form>

    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
            <thead class="text-dark fs-4">
                <tr>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">No Service</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Tanggal Service</h6>
                    </th>

                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Tanggal Claim Garansi</h6>
                    </th>

                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama Pelanggan</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nomor Handphone</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Alamat</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Status</h6>
                    </th>
                    <th>
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
                            <td data-order="<?= esc(date('Y-m-d H:i:s', strtotime($row->tanggal_claim_garansi))) ?>"><?= esc(date('d-m-Y', strtotime($row->tanggal_claim_garansi))) ?></td>
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
                                <button type="button" class="btn btn-sm btn-warning me-1 btn-riwayat"
                                    data-idservice="<?= (int) $row->idservice ?>"
                                    style="display: inline-flex; align-items: center;">
                                    <iconify-icon icon="solar:document-text-broken" width="24" height="24">
                                    </iconify-icon>
                                    Riwayat Klaim
                                </button>
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
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">Tidak ada data</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Riwayat Klaim -->
<div class="modal fade" id="modalRiwayatKlaim" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Riwayat Klaim</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="riwayat-klaim-content" class="text-center">
                    <span class="text-muted">Pilih data untuk melihat riwayat klaim...</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    let riwayatTable = null;

    window.onload = function() {
        const endDateInput = document.getElementById('endDate');
        const startDateInput = document.getElementById('startDate');

        riwayatTable = $('#zero_config').DataTable();
        riwayatTable.order([2, 'desc']).draw();

        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'zero_config') return true;

            const row = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
            const cell = row ? row.cells[2] : null;
            const rowDate = cell ? (cell.getAttribute('data-order') || '').slice(0, 10) : '';
            if (!rowDate) return true;

            const start = startDateInput.value;
            const end = endDateInput.value;

            return (!start || rowDate >= start) && (!end || rowDate <= end);
        });

        filterData();

        document.addEventListener('click', function(e) {
            const button = e.target.closest('.btn-riwayat');
            if (!button) return;

            const content = document.getElementById('riwayat-klaim-content');
            content.innerHTML = '<div class="text-center p-3">Loading...</div>';

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRiwayatKlaim')).show();

            fetch('<?= base_url('riwayat-klaim') ?>/' + button.dataset.idservice, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    content.innerHTML = data.html;
                })
                .catch(() => {
                    content.innerHTML = '<div class="alert alert-danger">Gagal memuat riwayat klaim.</div>';
                });
        });
    };

    function filterData() {
        if (riwayatTable) {
            riwayatTable.draw();
        }
    }

    function resetFilter() {
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';

        filterData();
    }
</script>