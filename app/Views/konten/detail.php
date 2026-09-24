<?php
$statusBadge = [
    'DRAFT' => 'secondary',
    'PRODUCTION' => 'info',
    'QC' => 'warning',
    'APPROVED' => 'primary',
    'PUBLISHED' => 'success',
    'COMPLETED' => 'success',
    'REVISION' => 'danger',
];
?>
<style>
    :root {
        --kn-border: #e5e7eb;
        --kn-bg-subtle: #f8f9fb;
        --kn-text-main: #1a1d23;
        --kn-text-muted: #6b7280;
        --kn-radius: 12px;
    }

    .kn-card {
        background: #fff;
        border: 1px solid var(--kn-border);
        border-radius: var(--kn-radius);
    }

    .kn-section-title {
        font-size: 14px;
        font-weight: 700;
        color: var(--kn-text-main);
        margin-bottom: 0;
    }

    .kn-label {
        font-size: 12px;
        color: var(--kn-text-muted);
        margin-bottom: 2px;
    }

    .kn-value {
        font-size: 14px;
        color: var(--kn-text-main);
        font-weight: 600;
    }

    .kn-divider {
        border-color: var(--kn-border);
        margin: 20px 0;
    }

    .kn-meta-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px 14px;
        font-size: 13px;
        color: var(--kn-text-muted);
    }

    .kn-meta-row .divider-dot {
        width: 3px;
        height: 3px;
        border-radius: 50%;
        background: var(--kn-border);
        display: inline-block;
    }

    .kn-table thead th {
        font-size: 12px;
        font-weight: 600;
        color: var(--kn-text-muted);
        border-bottom: 1px solid var(--kn-border);
        background: var(--kn-bg-subtle);
    }

    .kn-table td {
        font-size: 13.5px;
        vertical-align: middle;
    }

    .kn-empty {
        padding: 28px 16px;
        text-align: center;
        color: var(--kn-text-muted);
        font-size: 13px;
    }

    .kn-history-item {
        border-bottom: 1px solid var(--kn-border);
        padding: 10px 0;
    }

    .kn-history-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .kn-history-item:first-child {
        padding-top: 0;
    }

    .kn-form-title {
        font-size: 14px;
        font-weight: 700;
        color: var(--kn-text-main);
    }
</style>

<!-- Header -->
<div class="kn-card p-4 mb-3">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                <h4 class="fw-bold mb-0"><?= esc($content->judul) ?></h4>
                <span class="badge text-bg-<?= $statusBadge[$content->status] ?? 'secondary' ?>"><?= esc($content->status) ?></span>
                <?php if ($overdue) : ?>
                    <span class="badge text-bg-danger">Terlambat</span>
                <?php endif; ?>
            </div>
            <div class="kn-meta-row">
                <span><?= esc($content->content_type_name ?? '-') ?></span>
                <span class="divider-dot"></span>
                <?php if (($content->jenis_konten ?? 'REGULAR') === 'ADS') : ?>
                    <span class="badge text-bg-warning">Iklan</span>
                <?php else : ?>
                    <span class="badge text-bg-light border">Regular</span>
                <?php endif; ?>
                <span class="divider-dot"></span>
                <span>Deadline <?= esc($content->deadline) ?></span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 text-nowrap">
            <a href="<?= base_url('konten') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Manajemen Konten
            </a>
            <?php if ($canWrite) : ?>
                <a href="<?= base_url('konten/edit/' . $content->id) ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">

        <!-- Deskripsi & Info -->
        <div class="kn-card p-4">
            <h6 class="kn-section-title mb-3">Deskripsi</h6>
            <p class="text-muted mb-0"><?= esc(nl2br($content->deskripsi ?? '-')) ?></p>

            <hr class="kn-divider">

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="kn-label">Target Scope</div>
                    <div class="kn-value"><?= esc($content->target_scope) ?></div>
                    <?php if ($content->target_scope === 'SELECTED') : ?>
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            <?php foreach ($targetUnits as $tu) : ?>
                                <span class="badge text-bg-light border fw-normal"><?= esc($tu->nama_unit) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div class="text-muted small mt-1">Semua unit</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <div class="kn-label">Talent</div>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        <?php foreach (array_filter($peoples, fn($p) => $p->role === 'TALENT') as $p) : ?>
                            <span class="badge bg-info-subtle text-info border fw-normal"><?= esc($p->NAMA_AKUN) ?></span>
                        <?php endforeach; ?>
                        <?php if (!array_filter($peoples, fn($p) => $p->role === 'TALENT')) : ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="kn-label">Multimedia / Creative</div>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        <?php foreach (array_filter($peoples, fn($p) => $p->role === 'CREATIVE') as $p) : ?>
                            <span class="badge bg-primary-subtle text-primary border fw-normal"><?= esc($p->NAMA_AKUN) ?></span>
                        <?php endforeach; ?>
                        <?php if (!array_filter($peoples, fn($p) => $p->role === 'CREATIVE')) : ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="kn-label">Campaign</div>
                    <div class="kn-value"><?= isset($campaign) && $campaign ? esc($campaign->nama) : '-' ?></div>
                    <?php if (isset($campaign) && $campaign) : ?>
                        <div class="text-muted small mt-1"><?= $campaign->period_month ?>/<?= $campaign->period_year ?></> · <?= esc(ucfirst($campaign->status ?? '')) ?></div>
                    <?php endif; ?>
                </div>

                <?php if (isset($brief) && $brief && ($brief->isi_brief || $brief->requirement)) : ?>
                    <div class="col-12">
                        <div class="kn-label">Brief (Kesesuaian Brief)</div>
                        <?php if ($brief->isi_brief) : ?><div class="small text-muted mb-1"><strong>Isi:</strong> <?= esc(nl2br($brief->isi_brief)) ?></div><?php endif; ?>
                        <?php if ($brief->requirement) : ?><div class="small text-muted"><strong>Requirement:</strong> <?= esc(nl2br($brief->requirement)) ?></div><?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="col-md-4">
                    <div class="kn-label">Published</div>
                    <div class="kn-value"><?= $content->published_at ? date('d/m/Y H:i', strtotime($content->published_at)) : '-' ?></div>
                </div>
                <div class="col-md-4">
                    <div class="kn-label">Completed</div>
                    <div class="kn-value"><?= $content->completed_at ? date('d/m/Y H:i', strtotime($content->completed_at)) : '-' ?></div>
                </div>
            </div>

            <?php if ($canWrite && !empty($nextStatuses)) : ?>
                <hr class="kn-divider">
                <h6 class="kn-section-title mb-2">Ubah Status</h6>
                <form method="post" action="<?= base_url('konten/status') ?>" class="d-flex gap-2 flex-wrap">
                    <input type="hidden" name="id" value="<?= $content->id ?>">
                    <?php foreach ($nextStatuses as $ns) : ?>
                        <button type="submit" name="status" value="<?= $ns ?>" class="btn btn-sm btn-outline-primary"
                            onclick="return confirm('Ubah status ke <?= $ns ?>?')">→ <?= esc($ns) ?></button>
                    <?php endforeach; ?>
                </form>
            <?php endif; ?>

            <?php if ($canQc && $content->status === 'QC') : ?>
                <hr class="kn-divider">
                <h6 class="kn-section-title mb-2">Tindakan QC</h6>
                <form method="post" action="<?= base_url('konten/qc') ?>" class="row g-2 align-items-start">
                    <input type="hidden" name="id" value="<?= $content->id ?>">
                    <div class="col-md-7">
                        <textarea name="qc_note" class="form-control" rows="2" placeholder="Catatan QC (opsional)"></textarea>
                        <?php if ($brief) : ?>
                            <div class="form-check form-check-inline mt-2">
                                <input class="form-check-input" type="radio" name="sesuai_brief" value="1" checked>
                                <label class="form-check-label small">Sesuai Brief</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="sesuai_brief" value="0">
                                <label class="form-check-label small">Tidak Sesuai Brief</label>
                            </div>
                            <small class="text-muted d-block">Verdict dipakai KPI Kesesuaian Brief.</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-5 d-flex gap-2 pt-1">
                        <button type="submit" name="qc_result" value="PASS" class="btn btn-sm btn-success">PASS → APPROVED</button>
                        <button type="submit" name="qc_result" value="REJECT" class="btn btn-sm btn-danger" onclick="return confirm('Reject? Status menjadi REVISION.')">REJECT → REVISION</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- Brand Checklist -->
        <div class="kn-card p-4 mt-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="kn-section-title mb-0">Brand Checklist</h6>
                <span class="badge bg-primary-subtle text-primary" id="checkCount">0/<?= count($checklistItems) ?> tercentang</span>
            </div>
            <?php
            $checkedMap = [];
            foreach ($checklist as $cl) {
                $checkedMap[(int)$cl->item_id] = (int)$cl->is_checked;
            }
            ?>
            <?php if ($canQc) : ?>
                <form method="post" action="<?= base_url('konten/checklist') ?>" id="checklistForm">
                    <input type="hidden" name="content_id" value="<?= $content->id ?>">
                <?php endif; ?>
                <table class="table table-sm kn-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Status</th><?php if ($canQc) : ?><th class="text-end">Centang</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checklistItems as $item) : $isChecked = (bool)($checkedMap[(int)$item->id] ?? false); ?>
                            <tr>
                                <td><?= esc($item->name) ?></td>
                                <td>
                                    <?php if ($isChecked) : ?>
                                        <span class="badge text-bg-success">Terpenuhi</span>
                                    <?php else : ?>
                                        <span class="badge text-bg-light border">Belum</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canQc) : ?>
                                    <td class="text-end">
                                        <input class="form-check-input check-item" type="checkbox" name="checks[]"
                                            value="<?= $item->id ?>" <?= $isChecked ? 'checked' : '' ?>>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($canQc) : ?>
                    <div class="d-flex justify-content-end align-items-center gap-2 mt-3">
                        <small class="text-muted me-auto">Centang item yang terpenuhi, lalu simpan sekaligus.</small>
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Checklist</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- Publikasi -->
        <div class="kn-card p-4 mt-3">
            <h6 class="kn-section-title mb-3">Publikasi</h6>
            <div class="table-responsive">
                <table class="table table-sm kn-table align-middle">
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th>Platform</th>
                            <th>Link</th>
                            <th>Status</th>
                            <th>Published At</th>
                            <?php if ($canWrite) : ?><th class="text-end">Aksi</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($publications)) : ?>
                            <tr>
                                <td colspan="<?= $canWrite ? 6 : 5 ?>" class="kn-empty">Belum ada publikasi. Content dengan banyak publikasi tetap dihitung satu content.</td>
                            </tr>
                            <?php else : foreach ($publications as $pub) : ?>
                                <tr>
                                    <td><?= esc($pub->NAMA_UNIT ?? '-') ?></td>
                                    <td><?= esc($pub->platform_name ?? '-') ?></td>
                                    <td class="text-truncate" style="max-width:220px"><?= $pub->link ? '<a href="' . esc($pub->link) . '" target="_blank">' . esc($pub->link) . '</a>' : '-' ?></td>
                                    <td><span class="badge <?= $pub->status === 'PUBLISHED' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= esc($pub->status) ?></span></td>
                                    <td><?= $pub->published_at ? date('d/m/Y H:i', strtotime($pub->published_at)) : '-' ?></td>
                                    <?php if ($canWrite) : ?>
                                        <td class="text-end text-nowrap">
                                            <button class="btn btn-sm btn-outline-secondary btn-edit-pub" data-pub='<?= htmlspecialchars(json_encode([
                                                                                                                        'id' => (int)$pub->id,
                                                                                                                        'unit_id' => (int)$pub->unit_id,
                                                                                                                        'platform_id' => (int)$pub->platform_id,
                                                                                                                        'link' => $pub->link,
                                                                                                                        'status' => $pub->status,
                                                                                                                        'published_at' => $pub->published_at,
                                                                                                                    ]), ENT_QUOTES) ?>'>Edit</button>
                                            <form method="post" action="<?= base_url('konten/publication/delete') ?>" class="d-inline" onsubmit="return confirm('Hapus publikasi ini?')">
                                                <input type="hidden" name="id" value="<?= $pub->id ?>">
                                                <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($canWrite) : ?>
                <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
                    <h6 class="kn-form-title mb-0" id="pubFormTitle">Tambah Publikasi</h6>
                    <span class="badge text-bg-warning d-none" id="pubEditBadge">Mode Edit</span>
                </div>
                <form method="post" action="<?= base_url('konten/publication/save') ?>" id="pubForm" class="row g-2">
                    <input type="hidden" name="content_id" value="<?= $content->id ?>">
                    <input type="hidden" name="id" id="pubId" value="0">
                    <div class="col-md-2">
                        <label class="form-label small">Unit</label>
                        <select name="unit_id" id="pubUnit" class="form-select" required>
                            <option value="">—</option>
                            <?php foreach ($units as $u) : if (!in_array((int)$u->idunit, array_map('intval', $allowedUnits), true)) continue; ?>
                                <option value="<?= $u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Platform</label>
                        <select name="platform_id" id="pubPlatform" class="form-select" required>
                            <option value="">—</option>
                            <?php foreach ($platforms as $pf) : ?>
                                <option value="<?= $pf->id ?>"><?= esc($pf->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Link</label>
                        <input type="text" name="link" id="pubLink" class="form-control" placeholder="https://...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Status</label>
                        <select name="status" id="pubStatus" class="form-select">
                            <option value="PLANNED">PLANNED</option>
                            <option value="PUBLISHED">PUBLISHED</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Published At</label>
                        <input type="datetime-local" name="published_at" id="pubDate" class="form-control">
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-1">
                        <button class="btn btn-primary w-100" id="pubSubmitBtn">Tambah Publikasi</button>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-secondary w-100 d-none" id="btnCancelPub">Batal</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">

        <!-- Histori QC -->
        <div class="kn-card p-4">
            <h6 class="kn-section-title mb-3">Histori QC</h6>
            <?php if (empty($qcHistory)) : ?>
                <p class="text-muted small mb-0">Belum ada QC untuk content ini.</p>
            <?php else : ?>
                <div>
                    <?php foreach ($qcHistory as $q) : ?>
                        <div class="kn-history-item">
                            <span class="badge <?= $q->status === 'PASS' ? 'text-bg-success' : 'text-bg-danger' ?>"><?= esc($q->status) ?></span>
                            <div class="text-muted small mt-1"><?= date('d/m/Y H:i', strtotime($q->checked_at)) ?> · checker: <?= esc($q->checker_name ?? '-') ?></div>
                            <?php if ($q->note) : ?><div class="small mt-1"><?= esc($q->note) ?></div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function pubResetForm() {
        $('#pubId').val(0);
        $('#pubForm')[0].reset();
        $('#pubFormTitle').text('Tambah Publikasi');
        $('#pubSubmitBtn').text('Tambah Publikasi')
            .removeClass('btn-warning')
            .addClass('btn-primary');
        $('#pubEditBadge').addClass('d-none');
        $('#btnCancelPub').addClass('d-none');
    }

    $('.btn-edit-pub').on('click', function() {
        var d = $(this).data('pub');
        $('#pubId').val(d.id);
        $('#pubUnit').val(d.unit_id);
        $('#pubPlatform').val(d.platform_id);
        $('#pubLink').val(d.link || '');
        $('#pubStatus').val(d.status);
        $('#pubDate').val(d.published_at ? d.published_at.replace(' ', 'T') : '');
        $('#pubFormTitle').text('Edit Publikasi');
        $('#pubSubmitBtn').text('Update Publikasi')
            .removeClass('btn-primary')
            .addClass('btn-warning');
        $('#pubEditBadge').removeClass('d-none');
        $('#btnCancelPub').removeClass('d-none');
        $('#pubForm')[0].scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    });

    $('#btnCancelPub').on('click', function() {
        pubResetForm();
    });
    $('#pubSubmitBtn').on('click', function(e) {
        if ($('#pubId').val() > 0 && !$('#pubUnit').val()) {
            e.preventDefault();
            alert('Pilih unit publikasi.');
        }
    });

    function updateCheckCount() {
        var total = $('.check-item').length;
        var checked = $('.check-item:checked').length;
        $('#checkCount').text(checked + '/' + total + ' tercentang');
    }
    $('.check-item').on('change', updateCheckCount);
    updateCheckCount();
</script>
