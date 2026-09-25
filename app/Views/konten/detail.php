<?php
$statusBadge = [
    'DRAFT' => 'secondary',
    'PRODUCTION' => 'info',
    'QC' => 'warning',
    'APPROVED' => 'primary',
    'COMPLETED' => 'success',
    'REVISION' => 'danger',
];
?>
<style>
    .dm-surface {
        --kn-border: var(--bs-border-color);
        --kn-bg-subtle: var(--bs-secondary-bg-subtle);
        --kn-text-main: var(--bs-heading-color);
        --kn-text-muted: var(--bs-body-color);
        --kn-radius: 12px;
    }

    .kn-card {
        background: var(--bs-body-bg);
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

<?php if ($content->status === 'QC') : ?>
    <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-hourglass-split"></i>
        <div>
            <strong>Sedang QC</strong> — konten ini menunggu keputusan dari
            <strong>Admin Root / Manager / Kepala Divisi</strong>:
            disetujui (APPROVED) langsung selesai, atau dikembalikan untuk revisi (REVISION).
        </div>
    </div>
<?php endif; ?>

<div class="kn-card dm-detail-header p-4 mb-3">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div class="dm-page-heading">
            <span class="dm-page-kicker">Detail Konten</span>
            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                <h1 class="dm-page-title"><?= esc($content->judul) ?></h1>
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

                <div class="col-md-4">
                    <div class="kn-label">Published</div>
                    <div class="kn-value"><?= $content->published_at ? date('d/m/Y H:i', strtotime($content->published_at)) : '-' ?></div>
                </div>
                <div class="col-md-4">
                    <div class="kn-label">Completed</div>
                    <div class="kn-value"><?= $content->completed_at ? date('d/m/Y H:i', strtotime($content->completed_at)) : '-' ?></div>
                </div>
            </div>

            <?php if ($canChangeStatus && !empty($nextStatuses)) : ?>
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

        <!-- Kesesuaian Brief (dinilai manual oleh Kepala Divisi) -->
        <div class="kn-card p-4 mt-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <h6 class="kn-section-title mb-0">Kesesuaian Brief</h6>
                <?php if (isset($briefVerdict) && $briefVerdict) : ?>
                    <?php if ((int)$briefVerdict->sesuai === 1) : ?>
                        <span class="badge text-bg-success">Sesuai Brief</span>
                    <?php else : ?>
                        <span class="badge text-bg-danger">Tidak Sesuai Brief</span>
                    <?php endif; ?>
                <?php else : ?>
                    <span class="badge text-bg-light border">Belum dinilai</span>
                <?php endif; ?>
            </div>

            <?php if (isset($briefVerdict) && $briefVerdict) : ?>
                <div class="text-muted small mb-3">
                    Dinilai oleh <?= esc($briefVerdict->penilai_nama ?? '-') ?> ·
                    <?= date('d/m/Y H:i', strtotime($briefVerdict->created_at)) ?>
                    <?php if ($briefVerdict->catatan) : ?><span class="d-block mt-1"><?= esc($briefVerdict->catatan) ?></span><?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($canAssessBrief) : ?>
                <form method="post" action="<?= base_url('konten/brief/verdict') ?>" class="row g-2 align-items-end">
                    <input type="hidden" name="content_id" value="<?= $content->id ?>">
                    <div class="col-md-auto">
                        <div class="d-flex gap-3 pt-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="sesuai" value="1" id="briefSesuai1"
                                    <?= isset($briefVerdict) && $briefVerdict && (int)$briefVerdict->sesuai === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="briefSesuai1">Sesuai Brief</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="sesuai" value="0" id="briefSesuai0"
                                    <?= isset($briefVerdict) && $briefVerdict && (int)$briefVerdict->sesuai === 0 ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="briefSesuai0">Tidak Sesuai Brief</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md">
                        <input type="text" name="catatan" class="form-control form-control-sm" placeholder="Catatan (opsional)" maxlength="255">
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Simpan Penilaian</button>
                    </div>
                </form>
                <small class="text-muted d-block mt-2">Penilaian ini dipakai KPI Kesesuaian Brief (kepala divisi).</small>
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
