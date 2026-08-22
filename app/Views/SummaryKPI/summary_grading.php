<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Summary Performance Grading</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Home</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Summary Performance Grading</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="card-body px-4 pt-4 pb-2 mb-1">
        <!-- Filter Form -->
        <form method="get" action="<?= base_url('SummaryPerformance/summary_grading') ?>" class="mb-4">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="start_month" class="form-label">Bulan Awal:</label>
                    <input type="month"
                        name="start_month"
                        id="start_month"
                        class="form-control"
                        value="<?= esc($start_month) ?>"
                        required>
                </div>
                <div class="col-md-3">
                    <label for="end_month" class="form-label">Bulan Akhir:</label>
                    <input type="month"
                        name="end_month"
                        id="end_month"
                        class="form-control"
                        value="<?= esc($end_month) ?>"
                        required>
                </div>
                <div class="col-md-3">
                    <label for="id_unit" class="form-label">Unit:</label>
                    <select name="id_unit" id="id_unit" class="form-control">
                        <option value="">Semua Unit</option>
                        <?php foreach ($unit as $row): ?>
                            <option value="<?= $row->idunit ?>"><?= $row->NAMA_UNIT ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <iconify-icon icon="solar:filter-bold" width="20" height="20"></iconify-icon>
                        Filter
                    </button>
                    <a href="<?= base_url('SummaryPerformance/summary_grading') ?>" class="btn btn-secondary">
                        Reset
                    </a>
                </div>
            </div>
        </form>

        <!-- Table -->
        <div class="table-responsive">
            <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
                <thead class="text-dark fs-4">
                    <tr>
                        <th>No</th>
                        <th>Unit</th>
                        <th>Nama Pegawai</th>
                        <?php foreach ($months as $month): ?>
                            <th style="text-align: center;">
                                <?= esc($monthLabels[$month]) ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($pivotedData)): ?>
                        <?php $no = 1; ?>
                        <?php foreach ($pivotedData as $row): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <small><?= esc($row['nama_unit']) ?></small><br>
                                    <?= esc($row['nama_jabatan']) ?>
                                </td>
                                <td><?= esc($row['nama_pegawai']) ?></td>
                                <?php foreach ($months as $month): ?>
                                    <td style="text-align: center;">
                                        <?php
                                        $value = $row['months'][$month] ?? null;
                                        echo $value !== null ? number_format($value, 2) . "%" : '-';
                                        ?>
                                        <a href="javascript:void(0)" onclick="detail_grading('<?= $row['pegawai_id'] ?>', '<?= $month ?>')">
                                            <iconify-icon icon="solar:eye-bold" width="20" height="20"></iconify-icon>
                                        </a>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="detailGradingModal" tabindex="-1" aria-labelledby="detailGradingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailGradingModalLabel">Detail Grading</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="detailGradingContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {
        // Initialize DataTable
        // $('#zero_config').DataTable({
        //     "pageLength": 25,
        //     "order": [
        //         [1, "asc"]
        //     ],
        //     "scrollX": true
        // });
    });

    function detail_grading(id_akun, month) {
        $.ajax({
            url: `/SummaryPerformance/summary_detail`,
            type: 'POST',
            data: {
                id_akun: id_akun,
                month: month,
            },
            success: function(response) {
                $('#detailGradingContent').html(response);
                $('#detailGradingModal').modal('show');
            },
            error: function(xhr, status, error) {
                console.log(xhr.responseText);
            }
        });
    }
</script>