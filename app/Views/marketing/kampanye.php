<!-- Header Card -->
<div class="card shadow-none position-relative overflow-hidden mb-4 border-0"
    style="background: linear-gradient(120deg, #0f2b46 0%, #1d4e89 60%, #2a6dbb 100%);">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div class="text-white">
            <h4 class="fw-semibold mb-1 text-white">Campaign Digital Marketing</h4>
            <p class="text-white-50 mb-0 fs-3">
                Master campaign per periode. Campaign Selesai (Done) yang diisi link Laporan
                memenuhi KPI Reporting; pemakaian campaign wajib di Performa Ads.
            </p>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('marketing') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">Campaign</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Flash Messages -->
<?php if (session()->getFlashdata('success')) : ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <iconify-icon icon="solar:check-circle-bold" class="fs-5 me-2"></iconify-icon>
            <div><?= session()->getFlashdata('success') ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <iconify-icon icon="solar:danger-triangle-bold" class="fs-5 me-2"></iconify-icon>
            <div><?= session()->getFlashdata('error') ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter & Actions -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <form method="get" class="row g-2 align-items-end flex-grow-1">
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-semibold">Bulan</label>
                    <select name="bulan" class="form-select form-select-sm">
                        <?php for ($i = 1; $i <= 12; $i++) : ?>
                            <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-semibold">Tahun</label>
                    <select name="tahun" class="form-select form-select-sm">
                        <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++) : ?>
                            <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <iconify-icon icon="solar:filter-bold" class="me-1 align-text-bottom"></iconify-icon> Filter
                    </button>
                </div>
            </form>

            <?php if ($canWrite) : ?>
                <button type="button" class="btn btn-success btn-sm px-3" onclick="openCampaignModal()">
                    <iconify-icon icon="solar:add-circle-bold" class="me-1 align-text-bottom"></iconify-icon>Input Campaign
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2 border-bottom">
        <div>
            <h5 class="mb-0 fw-semibold">Daftar Campaign Digital Marketing</h5>
            <small class="text-muted">Periode: <?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></small>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($rows)) : ?>
            <div class="text-center py-5">
                <iconify-icon icon="solar:megaphone-bold" class="text-muted fs-1 mb-2"></iconify-icon>
                <p class="text-muted mb-1">Belum ada campaign untuk periode ini.</p>
                <?php if ($canWrite) : ?>
                    <button type="button" class="btn btn-sm btn-success mt-2 px-3" onclick="openCampaignModal()">
                        <iconify-icon icon="solar:add-circle-bold" class="me-1 align-text-bottom"></iconify-icon>Input Campaign
                    </button>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0" style="font-size: 0.85rem;">
                    <thead>
                        <tr>
                            <th class="ps-3 text-start">Nama Campaign</th>
                            <th class="text-center">Status</th>
                            <th class="text-start">Periode Tanggal</th>
                            <th class="text-start">Deskripsi</th>
                            <th class="text-start">Link Laporan Reporting</th>
                            <?php if ($canWrite) : ?>
                                <th class="text-center pe-3">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <?php
                            $statusClass = [
                                'draft'  => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                                'active' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                'done'   => 'bg-success-subtle text-success border border-success-subtle',
                            ];
                            $sc = $statusClass[$row->status] ?? $statusClass['draft'];
                            $hasReport = trim((string)$row->report_url) !== '';
                            ?>
                            <tr>
                                <td class="ps-3 fw-semibold text-dark"><?= esc($row->nama) ?></td>
                                <td class="text-center">
                                    <span class="badge px-2 py-1 <?= $sc ?>">
                                        <?= esc($statusLabels[$row->status] ?? $row->status) ?>
                                    </span>
                                </td>
                                <td class="text-start">
                                    <?php if ($row->tanggal_mulai) : ?>
                                        <?= date('d M Y', strtotime($row->tanggal_mulai)) ?>
                                        <?= $row->tanggal_selesai ? ' s.d. ' . date('d M Y', strtotime($row->tanggal_selesai)) : '' ?>
                                    <?php else : ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-start text-muted"><?= esc($row->deskripsi) ?: '-' ?></td>
                                <td class="text-start">
                                    <?php if ($hasReport) : ?>
                                        <a href="<?= esc($row->report_url) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                                            <iconify-icon icon="solar:link-circle-bold" class="me-1 align-text-bottom"></iconify-icon> Reporting
                                        </a>
                                    <?php else : ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canWrite) : ?>
                                    <td class="text-center text-nowrap pe-3">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-edit me-1"
                                            data-id="<?= (int)$row->id ?>"
                                            data-nama="<?= esc((string)$row->nama, 'attr') ?>"
                                            data-deskripsi="<?= esc((string)$row->deskripsi, 'attr') ?>"
                                            data-tanggal-mulai="<?= esc((string)$row->tanggal_mulai, 'attr') ?>"
                                            data-tanggal-selesai="<?= esc((string)$row->tanggal_selesai, 'attr') ?>"
                                            data-status="<?= esc((string)$row->status, 'attr') ?>"
                                            data-report="<?= esc((string)$row->report_url, 'attr') ?>"
                                            title="Edit"><i class="bi bi-pencil"></i></button>
                                        <form method="post" action="<?= base_url('marketing/campaign/hapus') ?>" class="d-inline"
                                            onsubmit="return confirm('Hapus campaign ini?');">
                                            <input type="hidden" name="id" value="<?= $row->id ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                <iconify-icon icon="solar:trash-bin-trash-bold" width="15" height="15"></iconify-icon>
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah / Edit Campaign -->
<?php if ($canWrite) : ?>
    <div class="modal fade" id="modalCampaign" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form method="post" action="<?= base_url('marketing/campaign/simpan') ?>" id="campaignForm">
                <input type="hidden" name="id" id="cp_id" value="">
                <input type="hidden" name="period_month" id="cpPeriodMonth" value="<?= $bulan ?>">
                <input type="hidden" name="period_year" id="cpPeriodYear" value="<?= $tahun ?>">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-semibold">
                            <iconify-icon icon="solar:megaphone-bold" class="text-primary me-1 align-text-bottom"></iconify-icon>
                            <span id="cp_title">Input Campaign</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label small text-muted mb-1 fw-semibold">Nama Campaign</label>
                                <input type="text" name="nama" id="cp_nama" class="form-control form-control-sm"
                                    placeholder="mis. Promo Ramadhan 2026" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-1 fw-semibold">Status</label>
                                <select name="status" id="cp_status" class="form-select form-select-sm">
                                    <option value="draft">Draft</option>
                                    <option value="active">Aktif</option>
                                    <option value="done">Selesai</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1 fw-semibold">Tanggal Mulai</label>
                                <input type="date" name="tanggal_mulai" id="cp_mulai" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1 fw-semibold">Tanggal Selesai</label>
                                <input type="date" name="tanggal_selesai" id="cp_selesai" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small text-muted mb-1 fw-semibold">Link Laporan Reporting</label>
                                <input type="url" name="report_url" id="cp_report" class="form-control form-control-sm"
                                    placeholder="https://drive.google.com/... (wajib jika status Selesai)">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small text-muted mb-1 fw-semibold">Deskripsi</label>
                                <textarea name="deskripsi" id="cp_deskripsi" class="form-control form-control-sm" rows="2"
                                    placeholder="Tujuan, segmentasi, dsb."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success btn-sm px-4">
                            <iconify-icon icon="solar:check-circle-bold" class="me-1 align-text-bottom"></iconify-icon>Simpan Data
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    function openCampaignModal() {
        document.getElementById('campaignForm').reset();
        document.getElementById('cp_id').value = '';
        document.getElementById('cp_title').textContent = 'Input Campaign';
        var modal = new bootstrap.Modal(document.getElementById('modalCampaign'));
        modal.show();
    }

    document.querySelectorAll('.btn-edit').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('cp_title').textContent = 'Edit Campaign';
            document.getElementById('cp_id').value = this.dataset.id || '';
            document.getElementById('cp_nama').value = this.dataset.nama || '';
            document.getElementById('cp_deskripsi').value = this.dataset.deskripsi || '';
            document.getElementById('cp_mulai').value = this.dataset.tanggalMulai || '';
            document.getElementById('cp_selesai').value = this.dataset.tanggalSelesai || '';
            document.getElementById('cp_status').value = this.dataset.status || 'draft';
            document.getElementById('cp_report').value = this.dataset.report || '';
            var modal = new bootstrap.Modal(document.getElementById('modalCampaign'));
            modal.show();
        });
    });
</script>