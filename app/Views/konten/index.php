<style>
    #kontenTable_wrapper .dt-buttons { margin-bottom: 8px; }
</style>
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
            <h4 class="fw-semibold mb-0">Manajemen Konten</h4>
            <small class="text-muted">Daftar content KPI Multimedia/Creative.</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('konten/dashboard') ?>">Digital Marketing</a></li>
                <li class="breadcrumb-item active">Manajemen Konten</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <?php if ($canWrite) : ?>
            <div class="mb-3">
                <a href="<?= base_url('konten/tambah') ?>" class="btn btn-primary">+ Tambah Konten</a>
            </div>
        <?php endif; ?>

        <form class="mb-3" id="filterForm">
            <div class="row g-2 align-items-end">
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1">Tanggal</label>
                    <input type="date" class="form-control form-control-sm" name="periode" id="filterPeriode">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1">Unit</label>
                    <select name="unit" id="filterUnit" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($units as $u) : if (!in_array((int)$u->idunit, array_map('intval', $allowedUnits), true)) continue; ?>
                            <option value="<?= $u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1">Multimedia</label>
                    <select name="multimedia" id="filterMultimedia" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($multimediaPeoples as $p) : ?>
                            <option value="<?= $p->ID_AKUN ?>"><?= esc($p->NAMA_AKUN) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1">Talent</label>
                    <select name="talent" id="filterTalent" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($allPeoples as $p) : ?>
                            <option value="<?= $p->ID_AKUN ?>"><?= esc($p->NAMA_AKUN) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" id="filterStatus" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($statuses as $s) : ?>
                            <option value="<?= $s ?>"><?= esc($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1">Platform</label>
                    <select name="platform" id="filterPlatform" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($platforms as $pf) : ?>
                            <option value="<?= $pf->id ?>"><?= esc($pf->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1">Content Type</label>
                    <select name="content_type" id="filterContentType" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($contentTypes as $ct) : ?>
                            <option value="<?= $ct->id ?>"><?= esc($ct->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 col-12">
                    <label class="form-label small mb-1">Cari</label>
                    <input type="text" class="form-control form-control-sm" name="search" id="filterSearch" placeholder="Cari judul konten…">
                </div>
                <div class="col-auto d-flex gap-1">
                    <button type="button" class="btn btn-primary btn-sm" id="btnFilter">Terapkan</button>
                    <button type="button" class="btn btn-light btn-sm" id="btnResetFilter">Reset</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table id="kontenTable" class="table table-bordered table-hover table-striped" style="width:100%">
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
                { data: 'judul' },
                {
                    data: null,
                    orderable: false,
                    render: function(data) {
                        var badge = data.jenis_konten === 'ADS'
                            ? '<span class="badge text-bg-warning">Iklan</span>'
                            : '<span class="badge text-bg-light border">Regular</span>';
                        return (data.content_type_name || '-') + ' ' + badge;
                    }
                },
                { data: 'target_scope' },
                { data: 'deadline' },
                {
                    data: 'created_at',
                    orderable: true,
                    render: function(data) {
                        if (!data) return '-';
                        var p = data.split(' ');
                        return p[0] + '<br><small class="text-muted">' + (p[1] || '') + '</small>';
                    }
                },
                {
                    data: 'status',
                    render: function(data) {
                        var badge = <?= json_encode($badgeMap) ?>[data] || 'secondary';
                        return '<span class="badge text-bg-' + badge + '">' + data + '</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: function(data) {
                        var s = '';
                        if (data.creative_names) s += '<small class="d-block text-muted">Multimedia: ' + data.creative_names + '</small>';
                        if (data.talent_names) s += '<small class="d-block">Talent: ' + data.talent_names + '</small>';
                        return s || '-';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-end',
                    render: function(data) {
                        var base = '<?= base_url('konten') ?>';
                        return '<a href="' + base + '/detail/' + data.id + '" class="btn btn-sm btn-outline-primary">Detail</a> ' +
                            '<?php if ($canWrite) : ?>' +
                            '<a href="' + base + '/edit/' + data.id + '" class="btn btn-sm btn-outline-secondary">Edit</a> ' +
                            '<a href="#" onclick="event.preventDefault(); if(confirm(\'Hapus konten ini?\')) location.href=\'' + base + '/delete/' + data.id + '\';" class="btn btn-sm btn-outline-danger">Hapus</a>' +
                            '<?php endif; ?>';
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