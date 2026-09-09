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
                    <div class="kn-label">Target Performa</div>
                    <div class="kn-value">
                        <?= $content->performance_metric_name ? esc($content->performance_metric_name) . ' — ' : '' ?><?= $content->performance_target !== null ? number_format((float)$content->performance_target, 2, ',', '.') : '-' ?>
                    </div>
                </div>
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
                                            <form method="post" action="<?= base_url('konten/publication/delete') ?>" class="d-inline" onsubmit="return confirm('Hapus publikasi beserta performanya?')">
                                                <input type="hidden" name="id" value="<?= $pub->id ?>">
                                                <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                                <?php if (!empty($pub->performances)) : ?>
                                    <tr class="table-light">
                                        <td colspan="<?= $canWrite ? 6 : 5 ?>">
                                            <small><strong>Performa:</strong>
                                                <?php foreach ($pub->performances as $perf) : ?>
                                                    <span class="badge text-bg-light border me-2 fw-normal"><?= esc($perf->metric_name) ?> <?= date('m/Y', mktime(0, 0, 0, (int)$perf->period_month, 1, (int)$perf->period_year)) ?>: <?= number_format((float)$perf->actual, 0, ',', '.') ?>/<?= number_format((float)$perf->target, 0, ',', '.') ?> (<?= $perf->achievement !== null ? number_format((float)$perf->achievement, 1, ',', '.') . '%' : 'N/A' ?>)</span>
                                                <?php endforeach; ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endif; ?>
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

        <?php if ($canWrite) : ?>
            <!-- Performa Publikasi -->
            <div class="kn-card p-4 mt-3">
                <?php
                $perfRows = [];
                foreach ($publications as $pub) {
                    foreach (array_filter($pub->performances ?? []) as $perf) {
                        $perf->publication_id = (int)$pub->id;
                        $perf->publication_label = ($pub->NAMA_UNIT ?? '-') . ' — ' . ($pub->platform_name ?? '-');
                        $perfRows[] = $perf;
                    }
                }
                ?>
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="kn-section-title mb-0" id="perfFormTitle">Input Performa Publikasi</h6>
                    <span class="badge text-bg-warning d-none" id="perfEditBadge">Mode Edit</span>
                </div>

                <?php if (!empty($perfRows)) : ?>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm kn-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Publikasi</th>
                                    <th>Metric</th>
                                    <th>Periode</th>
                                    <th class="text-end">Target</th>
                                    <th class="text-end">Actual</th>
                                    <th class="text-end">Capaian</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($perfRows as $p) : ?>
                                    <tr>
                                        <td><small><?= esc($p->publication_label) ?></small></td>
                                        <td><small><?= esc($p->metric_name ?? '-') ?></small></td>
                                        <td><small><?= date('m/Y', mktime(0, 0, 0, (int)$p->period_month, 1, (int)$p->period_year)) ?></small></td>
                                        <td class="text-end"><small><?= number_format((float)$p->target, 0, ',', '.') ?></small></td>
                                        <td class="text-end"><small><?= number_format((float)$p->actual, 0, ',', '.') ?></small></td>
                                        <td class="text-end"><small><?= $p->achievement !== null ? number_format((float)$p->achievement, 1, ',', '.') . '%' : 'N/A' ?></small></td>
                                        <td class="text-end text-nowrap">
                                            <button class="btn btn-sm btn-outline-secondary btn-edit-perf" data-perf='<?= htmlspecialchars(json_encode([
                                                                                                                            'id' => (int)$p->id,
                                                                                                                            'publication_id' => (int)$p->publication_id,
                                                                                                                            'metric_id' => (int)$p->metric_id,
                                                                                                                            'period_month' => (int)$p->period_month,
                                                                                                                            'period_year' => (int)$p->period_year,
                                                                                                                            'target' => (float)$p->target,
                                                                                                                            'actual' => (float)$p->actual,
                                                                                                                        ]), ENT_QUOTES) ?>'>Edit</button>
                                            <form method="post" action="<?= base_url('konten/performance/delete') ?>" class="d-inline" onsubmit="return confirm('Hapus performa ini?')">
                                                <input type="hidden" name="id" value="<?= $p->id ?>">
                                                <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= base_url('konten/performance/save') ?>" class="row g-2" id="perfForm">
                    <input type="hidden" name="id" id="perfId" value="0">
                    <div class="col-12">
                        <label class="form-label small">Publikasi</label>
                        <select name="publication_id" id="perfPublication" class="form-select" required>
                            <option value="">— Pilih —</option>
                            <?php foreach ($publications as $pub) : ?>
                                <option value="<?= $pub->id ?>"><?= esc($pub->NAMA_UNIT ?? '-') ?> — <?= esc($pub->platform_name ?? '-') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($publications)) : ?><small class="text-danger d-block mt-1">Tambahkan publikasi terlebih dahulu.</small><?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Metric</label>
                        <select name="metric_id" id="perfMetric" class="form-select" required>
                            <option value="">— Pilih —</option>
                            <?php foreach ($metrics as $m) : ?>
                                <option value="<?= $m->id ?>"><?= esc($m->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Periode Bulan</label>
                        <select name="period_month" id="perfMonth" class="form-select">
                            <?php for ($i = 1; $i <= 12; $i++) : ?>
                                <option value="<?= $i ?>" <?= $i == date('n') ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Tahun</label>
                        <select name="period_year" id="perfYear" class="form-select">
                            <?php for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++) : ?>
                                <option value="<?= $i ?>" <?= $i == date('Y') ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Target</label>
                        <input type="number" step="0.01" min="0" name="target" id="perfTarget" class="form-control" value="0" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Actual</label>
                        <input type="number" step="0.01" min="0" name="actual" id="perfActual" class="form-control" value="0" required>
                    </div>
                    <div class="col-12 d-grid gap-2 mt-1">
                        <button class="btn btn-success w-100" id="perfSubmitBtn" <?= empty($publications) ? 'disabled' : '' ?>>Simpan Performa</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btnCancelPerf">Batal Edit</button>
                    </div>
                </form>
                <small class="text-muted d-block mt-2">Achievement = actual / target × 100. Input manual (belum ada integrasi API media sosial).</small>
            </div>
        <?php endif; ?>
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

    function perfResetForm() {
        $('#perfId').val(0);
        $('#perfForm')[0].reset();
        $('#perfFormTitle').text('Input Performa Publikasi');
        $('#perfSubmitBtn').text('Simpan Performa')
            .removeClass('btn-warning')
            .addClass('btn-success');
        $('#perfEditBadge').addClass('d-none');
        $('#btnCancelPerf').addClass('d-none');
    }

    $('.btn-edit-perf').on('click', function() {
        var d = $(this).data('perf');
        $('#perfId').val(d.id);
        $('#perfPublication').val(d.publication_id);
        $('#perfMetric').val(d.metric_id);
        $('#perfMonth').val(d.period_month);
        $('#perfYear').val(d.period_year);
        $('#perfTarget').val(d.target);
        $('#perfActual').val(d.actual);
        $('#perfFormTitle').text('Ubah Performa Publikasi');
        $('#perfSubmitBtn').text('Ubah')
            .removeClass('btn-success')
            .addClass('btn-warning');
        $('#perfEditBadge').removeClass('d-none');
        $('#btnCancelPerf').removeClass('d-none');
        $('#perfForm')[0].scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    });

    $('#btnCancelPerf').on('click', function() {
        perfResetForm();
    });
    $('#perfSubmitBtn').on('click', function(e) {
        if ($('#perfId').val() > 0 && !$('#perfPublication').val()) {
            e.preventDefault();
            alert('Pilih publikasi.');
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
