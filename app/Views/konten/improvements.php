<div class="card dm-page-header shadow-none border-0 mb-4">
    <div class="card-body d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div class="dm-page-heading">
            <span class="dm-page-kicker">Multimedia</span>
            <h1 class="dm-page-title">Improvement</h1>
            <p class="dm-page-description">KPI Multimedia — Improvement 5% (ide perbaikan yang disetujui / bulan). Target: 1 per bulan.</p>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-decoration-none" href="<?= base_url('konten') ?>">Manajemen Konten</a></li>
                <li class="breadcrumb-item active" aria-current="page">Improvement</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card dm-filter-card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="get" action="<?= base_url('konten/improvements') ?>" class="row g-2 align-items-end">
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
            <form method="post" action="<?= base_url('konten/improvement/simpan') ?>">
                <input type="hidden" name="id" id="impId" value="0">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-semibold mb-0" id="impTitle">Ajukan Improvement</h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="btnCancelImp">Batal Edit</button>
                </div>
                <div class="row g-3">
                    <?php if ($canApprove) : ?>
                        <div class="col-md-3">
                            <label class="form-label small">Employee</label>
                            <select name="employee_id" id="impEmployee" class="form-select">
                                <?php foreach ($multimediaPeoples as $p) : ?>
                                    <option value="<?= $p->ID_AKUN ?>" <?= (int)$p->ID_AKUN === (int)session('ID_AKUN') ? 'selected' : '' ?>><?= esc($p->NAMA_AKUN) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="form-label small">Judul *</label>
                        <input type="text" name="judul" id="impJudul" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Bulan</label>
                        <select name="submission_month" id="impMonth" class="form-select">
                            <?php for ($i = 1; $i <= 12; $i++) : ?>
                                <option value="<?= $i ?>" <?= $i === (int)$bulan ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Tahun</label>
                        <select name="submission_year" id="impYear" class="form-select">
                            <?php for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++) : ?>
                                <option value="<?= $i ?>" <?= $i === (int)$tahun ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Deskripsi / Rencana</label>
                        <textarea name="deskripsi" id="impDeskripsi" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Evidence / Hasil</label>
                        <textarea name="evidence" id="impEvidence" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" id="impSubmit">Ajukan</button>
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
                    <th>Employee</th>
                    <th>Judul</th>
                    <th>Periode</th>
                    <th>Status</th>
                    <th>Disetujui</th>
                    <?php if ($canApprove) : ?><th class="text-end">Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)) : ?>
                    <tr><td colspan="<?= $canApprove ? 6 : 5 ?>" class="text-center text-muted py-4">Belum ada improvement pada periode ini.</td></tr>
                <?php else : foreach ($rows as $r) : ?>
                    <tr>
                        <td><?= esc($peopleById[(int)$r->employee_id]->NAMA_AKUN ?? '#' . $r->employee_id) ?></td>
                        <td>
                            <span class="fw-semibold"><?= esc($r->judul) ?></span>
                            <?php if ($r->deskripsi) : ?><div class="text-muted small"><?= esc($r->deskripsi) ?></div><?php endif; ?>
                            <?php if ($r->evidence) : ?><div class="text-muted small"><i class="bi bi-paperclip me-1"></i><?= esc($r->evidence) ?></div><?php endif; ?>
                        </td>
                        <td><?= sprintf('%02d/%d', (int)$r->submission_month, (int)$r->submission_year) ?></td>
                        <td>
                            <?php
                            $badge = [
                                'draft' => 'secondary',
                                'submitted' => 'warning',
                                'approved' => 'success',
                                'implemented' => 'primary',
                                'rejected' => 'danger',
                            ];
                            ?>
                            <span class="badge text-bg-<?= $badge[$r->status] ?? 'secondary' ?>"><?= esc(ucfirst($r->status)) ?></span>
                        </td>
                        <td><?= $r->approved_at ? date('d/m/Y H:i', strtotime($r->approved_at)) : '-' ?></td>
                        <?php if ($canApprove) : ?>
                            <td class="text-end text-nowrap">
                                <form method="post" action="<?= base_url('konten/improvement/status') ?>" class="d-inline">
                                    <input type="hidden" name="id" value="<?= $r->id ?>">
                                    <select name="status" class="form-select form-select-sm d-inline-block" style="width:auto" onchange="this.form.submit()">
                                        <?php foreach (['submitted', 'approved', 'implemented', 'rejected'] as $st) : ?>
                                            <option value="<?= $st ?>" <?= $r->status === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                                        <?php endforeach; ?>
                                    </select>
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
    $(function() {
        $('.btn-edit-imp').on('click', function() {
            var d = $(this).data();
            $('#impId').val(d.id);
            $('#impJudul').val(d.judul);
            $('#impDeskripsi').val(d.deskripsi || '');
            $('#impEvidence').val(d.evidence || '');
            $('#impMonth').val(d.month);
            $('#impYear').val(d.year);
            $('#impTitle').text('Ubah Improvement');
            $('#impSubmit').text('Update')
                .removeClass('btn-primary').addClass('btn-warning');
            $('#btnCancelImp').removeClass('d-none');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        $('#btnCancelImp').on('click', function() {
            $('#impId').val(0);
            $('#impTitle').text('Ajukan Improvement');
            $('#impSubmit').text('Ajukan')
                .removeClass('btn-warning').addClass('btn-primary');
            $(this).addClass('d-none');
        });
    });
</script>