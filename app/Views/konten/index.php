<style>
    #kontenTable_wrapper .dt-buttons { margin-bottom: 8px; }
    #kontenTable_filter { margin-bottom: 8px; }
</style>
<div class="card shadow-none position-relative overflow-hidden mb-4"
    style="background: linear-gradient(120deg, #0f2b46 0%, #1d4e89 60%, #2a6dbb 100%);">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div class="text-white">
            <h4 class="fw-semibold mb-1 text-white">Manajemen Konten</h4>
            <small class="text-white-50">Daftar content KPI Multimedia/Creative.</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('konten/dashboard') ?>">Digital Marketing</a></li>
                <li class="breadcrumb-item active text-white">Manajemen Konten</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h5 class="mb-0">
                <iconify-icon icon="solar:filter-bold" class="text-primary me-1"></iconify-icon> Filter &amp; Pencarian
            </h5>
            <?php if ($canWrite) : ?>
                <a href="<?= base_url('konten/tambah') ?>" class="btn btn-success">
                    <iconify-icon icon="solar:add-circle-bold" class="me-1"></iconify-icon>Tambah Konten
                </a>
            <?php endif; ?>
        </div>

        <form class="mb-0" id="filterForm">
            <div class="row g-2 align-items-end">
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1 text-muted">Tanggal</label>
                    <input type="date" class="form-control form-control-sm" name="periode" id="filterPeriode">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1 text-muted">Unit</label>
                    <select name="unit" id="filterUnit" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($units as $u) : if (!in_array((int)$u->idunit, array_map('intval', $allowedUnits), true)) continue; ?>
                            <option value="<?= $u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1 text-muted">Multimedia</label>
                    <select name="multimedia" id="filterMultimedia" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($multimediaPeoples as $p) : ?>
                            <option value="<?= $p->ID_AKUN ?>"><?= esc($p->NAMA_AKUN) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1 text-muted">Talent</label>
                    <select name="talent" id="filterTalent" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($allPeoples as $p) : ?>
                            <option value="<?= $p->ID_AKUN ?>"><?= esc($p->NAMA_AKUN) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1 text-muted">Status</label>
                    <select name="status" id="filterStatus" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($statuses as $s) : ?>
                            <option value="<?= $s ?>"><?= esc($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1 text-muted">Platform</label>
                    <select name="platform" id="filterPlatform" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($platforms as $pf) : ?>
                            <option value="<?= $pf->id ?>"><?= esc($pf->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1 text-muted">Content Type</label>
                    <select name="content_type" id="filterContentType" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($contentTypes as $ct) : ?>
                            <option value="<?= $ct->id ?>"><?= esc($ct->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 col-12">
                    <label class="form-label small mb-1 text-muted">Cari Judul</label>
                    <input type="text" class="form-control form-control-sm" name="search" id="filterSearch" placeholder="Ketik judul konten…">
                </div>
                <div class="col-auto d-flex gap-1">
                    <button type="button" class="btn btn-primary btn-sm" id="btnFilter">
                        <iconify-icon icon="solar:filter-bold" class="me-1"></iconify-icon>Terapkan
                    </button>
                    <button type="button" class="btn btn-light btn-sm" id="btnResetFilter">Reset</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <h5 class="mb-0">Daftar Konten</h5>
        <small class="text-muted">Terapkan filter untuk memperbarui daftar.</small>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="kontenTable" class="table table-hover align-middle" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th>Judul</th>
                        <th>Content Type</th>
                        <th>Target</th>
                        <th>Deadline</th>
                        <th>Dibuat</th>
                        <th>Status</th>
                        <th>Kreator &amp; Talent</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php
$badgeMap = [
    'DRAFT' => 'secondary', 'PRODUCTION' => 'info', 'QC' => 'warning',
    'APPROVED' => 'primary', 'PUBLISHED' => 'success', 'COMPLETED' => 'success', 'REVISION' => 'danger',
];
$badgeIcon = [
    'DRAFT' => 'bi-pencil-square', 'PRODUCTION' => 'bi-gear', 'QC' => 'bi-clipboard-check',
    'APPROVED' => 'bi-check2-circle', 'PUBLISHED' => 'bi-cloud-upload', 'COMPLETED' => 'bi-check2-all', 'REVISION' => 'bi-arrow-counterclockwise',
];
?>

<script>
    $(document).ready(function() {
        var table = $('#kontenTable').DataTable({
            processing: true,
            serverSide: true,
            order: [
                [3, 'desc']
            ],
            searching: false,
            ajax: {
                url: '<?= base_url('konten/dt') ?>',
                data: function(d) {
                    d.periode = $('#filterPeriode').val();
                    d.unit = $('#filterUnit').val();
                    d.multimedia = $('#filterMultimedia').val();
                    d.talent = $('#filterTalent').val();
                    d.status = $('#filterStatus').val();
                    d.platform = $('#filterPlatform').val();
                    d.content_type = $('#filterContentType').val();
                    d.search = $('#filterSearch').val();
                }
            },
            lengthMenu: [
                [10, 25, 50, 100],
                [10, 25, 50, 100]
            ],
            columns: [
                {
                    data: 'judul',
                    render: function(data, type, row) {
                        if (type !== 'display') return data;
                        return '<div class="fw-semibold">' + data + '</div>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: function(data) {
                        var badge = data.jenis_konten === 'ADS'
                            ? '<span class="badge rounded-pill text-bg-warning">Iklan</span>'
                            : '<span class="badge rounded-pill bg-light text-dark border">Regular</span>';
                        return '<div class="text-nowrap"><span class="badge rounded-pill bg-white text-dark border">' + (data.content_type_name || '-') + '</span><br>' + badge + '</div>';
                    }
                },
                { data: 'target_scope' },
                {
                    data: 'deadline',
                    render: function(data) {
                        if (!data) return '-';
                        return '<div class="text-nowrap">' + data + '</div>';
                    }
                },
                {
                    data: 'created_at',
                    orderable: true,
                    render: function(data) {
                        if (!data) return '-';
                        var p = data.split(' ');
                        return '<div class="text-nowrap">' + p[0] + '<br><small class="text-muted">' + (p[1] || '') + '</small></div>';
                    }
                },
                {
                    data: 'status',
                    render: function(data) {
                        var badge = <?= json_encode($badgeMap) ?>[data] || 'secondary';
                        var icon = <?= json_encode($badgeIcon) ?>[data] || 'bi-tag';
                        return '<span class="badge rounded-pill text-bg-' + badge + ' text-nowrap"><i class="bi ' + icon + ' me-1"></i>' + data + '</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: function(data) {
                        var s = '';
                        if (data.creative_names) s += '<small class="d-block text-muted"><i class="bi bi-people me-1"></i>' + data.creative_names + '</small>';
                        if (data.talent_names) s += '<small class="d-block"><i class="bi bi-person-video3 me-1"></i>' + data.talent_names + '</small>';
                        return s || '<span class="text-muted small">-</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-end text-nowrap',
                    render: function(data) {
                        var base = '<?= base_url('konten') ?>';
                        var s = '<a href="' + base + '/detail/' + data.id + '" class="btn btn-sm btn-outline-primary" title="Detail"><i class="bi bi-eye"></i> Detail</a> ';
                        <?php if ($canWrite) : ?>
                            s += '<a href="' + base + '/edit/' + data.id + '" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i> Edit</a> ';
                            s += '<a href="#" onclick="event.preventDefault(); if(confirm(\'Hapus konten ini?\')) location.href=\'' + base + '/delete/' + data.id + '\';" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i> Hapus</a>';
                        <?php endif; ?>
                        return s;
                    }
                }
            ],
            language: {
                search: 'Cari judul: ',
                lengthMenu: 'Tampil _MENU_ baris',
                info: 'Menampilkan _START_–_END_ dari _TOTAL_',
                infoEmpty: 'Tidak ada data',
                zeroRecords: 'Tidak ditemukan',
                processing: 'Memuat…',
                paginate: { first: 'Awal', last: 'Akhir', next: '»', previous: '«' }
            }
        });

        $('#btnFilter').on('click', function() {
            table.ajax.reload(null, false);
        });
        $('#filterSearch').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                table.ajax.reload(null, false);
            }
        });
        $('#btnResetFilter').on('click', function() {
            $('#filterForm')[0].reset();
            table.ajax.reload();
        });
    });
</script>