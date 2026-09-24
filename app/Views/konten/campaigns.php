<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
            <h4 class="fw-semibold mb-0">Campaign Marketing</h4>
            <small class="text-muted">KPI Multimedia — Support Campaign 10% (konten campaign dikerjakan tepat waktu vs target campaign).</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('konten') ?>">Manajemen Konten</a></li>
                <li class="breadcrumb-item active">Campaign</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="get" action="<?= base_url('konten/campaigns') ?>" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small">Bulan</label>
                <select name="bulan" class="form-select">
                    <?php for ($i = 1; $i <= 12; $i++) : ?>
                        <option value="<?= $i ?>" <?= $i === (int)$bulan ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small">Tahun</label>
                <select name="tahun" class="form-select">
                    <?php for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++) : ?>
                        <option value="<?= $i ?>" <?= $i === (int)$tahun ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary">Tampilkan</button>
            </div>
        </form>
    </div>
</div>

<?php if ($canWrite) : ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="post" action="<?= base_url('konten/campaign/simpan') ?>">
                <input type="hidden" name="id" id="campId" value="0">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-semibold mb-0" id="campTitle">Tambah Campaign</h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="btnCancelCamp">Batal Edit</button>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">Nama Campaign *</label>
                        <input type="text" name="nama" id="campNama" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Bulan</label>
                        <select name="period_month" id="campMonth" class="form-select">
                            <?php for ($i = 1; $i <= 12; $i++) : ?>
                                <option value="<?= $i ?>" <?= $i === (int)$bulan ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Tahun</label>
                        <select name="period_year" id="campYear" class="form-select">
                            <?php for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++) : ?>
                                <option value="<?= $i ?>" <?= $i === (int)$tahun ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Status</label>
                        <select name="status" id="campStatus" class="form-select">
                            <?php foreach ($statuses as $st) : ?>
                                <option value="<?= $st ?>"><?= esc(ucfirst($st)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4 col-md-2">
                        <label class="form-label"></label>
                        <button class="btn btn-primary w-100" id="campSubmit">Simpan</button>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Target Jumlah Konten</label>
                        <input type="number" min="0" name="target_jumlah_konten" id="campTarget" class="form-control" placeholder="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Target Deadline</label>
                        <input type="date" name="target_deadline" id="campDeadline" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Deskripsi</label>
                        <input type="text" name="deskripsi" id="campDeskripsi" class="form-control">
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nama</th>
                    <th>Periode</th>
                    <th>Status</th>
                    <th class="text-end">Target Konten</th>
                    <th class="text-end">Konten Tersimpan</th>
                    <th>Target Deadline</th>
                    <?php if ($canWrite) : ?><th class="text-end">Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($campaigns)) : ?>
                    <tr><td colspan="<?= $canWrite ? 7 : 6 ?>" class="text-center text-muted py-4">Belum ada campaign pada periode ini.</td></tr>
                <?php else : foreach ($campaigns as $c) : ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($c->nama) ?><?php if ($c->deskripsi) : ?><div class="text-muted small fw-normal"><?= esc($c->deskripsi) ?></div><?php endif; ?></td>
                        <td><?= sprintf('%02d/%d', (int)$c->period_month, (int)$c->period_year) ?></td>
                        <td><span class="badge <?= $c->status === 'active' ? 'text-bg-success' : ($c->status === 'done' ? 'text-bg-secondary' : 'text-bg-warning') ?>"><?= esc(ucfirst($c->status)) ?></span></td>
                        <td class="text-end"><?= $c->target_jumlah_konten !== null ? (int)$c->target_jumlah_konten : '-' ?></td>
                        <td class="text-end"><span class="badge bg-primary-subtle text-primary"><?= (int)($contentCounts[$c->id] ?? 0) ?></span></td>
                        <td><?= $c->target_deadline ? esc(date('d/m/Y', strtotime($c->target_deadline))) : '-' ?></td>
                        <?php if ($canWrite) : ?>
                            <td class="text-end text-nowrap">
                                <button class="btn btn-sm btn-outline-secondary btn-edit-camp"
                                    data-id="<?= $c->id ?>"
                                    data-nama="<?= esc($c->nama, 'attr') ?>"
                                    data-deskripsi="<?= esc($c->deskripsi ?? '', 'attr') ?>"
                                    data-bulan="<?= (int)$c->period_month ?>"
                                    data-tahun="<?= (int)$c->period_year ?>"
                                    data-status="<?= esc($c->status, 'attr') ?>"
                                    data-target="<?= $c->target_jumlah_konten ?? '' ?>"
                                    data-deadline="<?= esc($c->target_deadline ?? '', 'attr') ?>">Edit</button>
                                <form method="post" action="<?= base_url('konten/campaign/hapus') ?>" class="d-inline" onsubmit="return confirm('Hapus campaign ini? Konten menjadi tanpa campaign.')">
                                    <input type="hidden" name="id" value="<?= $c->id ?>">
                                    <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    var campaigns = <?= json_encode($campaigns) ?>;
    $(function() {
        $('.btn-edit-camp').on('click', function() {
            var d = $(this).data();
            $('#campId').val(d.id);
            $('#campNama').val(d.nama);
            $('#campDeskripsi').val(d.deskripsi);
            $('#campMonth').val(d.bulan);
            $('#campYear').val(d.tahun);
            $('#campStatus').val(d.status);
            $('#campTarget').val(d.target);
            $('#campDeadline').val(d.deadline);
            $('#campTitle').text('Edit Campaign');
            $('#campSubmit').text('Update')
                .removeClass('btn-primary').addClass('btn-warning');
            $('#btnCancelCamp').removeClass('d-none');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        $('#btnCancelCamp').on('click', function() {
            $('#campId').val(0);
            $('#campTitle').text('Tambah Campaign');
            $('#campSubmit').text('Simpan')
                .removeClass('btn-warning').addClass('btn-primary');
            $(this).addClass('d-none');
        });
    });
</script>