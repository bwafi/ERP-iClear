<div class="card shadow-none position-relative overflow-hidden mb-4"
    style="background: linear-gradient(120deg, #0f2b46 0%, #1d4e89 60%, #2a6dbb 100%);">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div class="text-white">
            <h4 class="fw-semibold mb-1 text-white">Target Social Media</h4>
            <small class="text-white-50"> Target period-based (bulan) per platform × metric. Dikelola: Kepala Divisi (43), Admin (0), Root (1), Manager (34).</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('sosial/kpi') ?>">Social Media</a></li>
                <li class="breadcrumb-item active text-white">Target</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session()->getFlashdata('sukses')) : ?>
    <div class="alert alert-success alert-dismissible fade show"><?= esc(session()->getFlashdata('sukses')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= esc(session()->getFlashdata('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end justify-content-between">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-1 small text-muted">Bulan</label>
                <select name="bulan" class="form-select form-select-sm">
                    <?php for ($m = 1; $m <= 12; $m++) : ?>
                        <option value="<?= $m ?>" <?= $m == $bulan ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-1 small text-muted">Tahun</label>
                <select name="tahun" class="form-select form-select-sm">
                    <?php for ($t = date('Y') - 2; $t <= date('Y') + 1; $t++) : ?>
                        <option value="<?= $t ?>" <?= $t == $tahun ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-1 small text-muted">Unit (opsional)</label>
                <select name="unit_id" class="form-select form-select-sm">
                    <option value="0">Global (semua unit)</option>
                    <?php foreach ($units as $u) : ?>
                        <option value="<?= (int)$u->idunit ?>" <?= (int)$u->idunit == $unit_id ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-1 small text-muted">Platform</label>
                <select name="platform" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach ($platforms as $p) : ?>
                        <option value="<?= esc($p) ?>" <?= $platform === $p ? 'selected' : '' ?>><?= ucfirst(esc($p)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
            </div>
        </form>
        <button type="button" class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#targetModal">
            <i class="bi bi-plus-lg me-1"></i>Tambah Target
        </button>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th>Unit</th>
                    <th>Platform</th>
                    <th>Metric</th>
                    <th class="text-end">Target</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $unitMap = [];
                foreach ($units as $u) { $unitMap[(int)$u->idunit] = $u->NAMA_UNIT; }
                if (empty($targets)) : ?>
                    <tr><td colspan="6" class="text-center text-muted">Belum ada target untuk periode ini. Buat target baru di atas.</td></tr>
                <?php endif; ?>
                <?php foreach ($targets as $t) : ?>
                    <tr>
                        <td><?= esc($t->period_month) ?></td>
                        <td><?= $t->unit_id == 0 ? '<span class="badge bg-secondary">Global</span>' : esc($unitMap[(int)$t->unit_id] ?? (string)$t->unit_id) ?></td>
                        <td><?= ucfirst(esc($t->platform)) ?></td>
                        <td><?= esc($t->metric) ?></td>
                        <td class="text-end"><?= number_format((float)$t->target_value, 2, ',', '.') ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary edit-btn" data-bs-toggle="modal" data-bs-target="#targetModal"
                                data-unit="<?= (int)$t->unit_id ?>"
                                data-platform="<?= esc($t->platform, 'attr') ?>"
                                data-metric="<?= esc($t->metric, 'attr') ?>"
                                data-target="<?= (float)$t->target_value ?>">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <form action="<?= base_url('sosial/target/hapus') ?>" method="post" class="d-inline"
                                onsubmit="return confirm('Hapus target ini?')">
                                <input type="hidden" name="id" value="<?= (int)$t->id ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="targetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('sosial/target/simpan') ?>" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="targetModalTitle">Tambah Target</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Bulan</label>
                            <select name="bulan" id="f_bulan" class="form-select" required>
                                <?php for ($m = 1; $m <= 12; $m++) : ?>
                                    <option value="<?= $m ?>" <?= $m == $bulan ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tahun</label>
                            <select name="tahun" id="f_tahun" class="form-select" required>
                                <?php for ($t = date('Y') - 2; $t <= date('Y') + 1; $t++) : ?>
                                    <option value="<?= $t ?>" <?= $t == $tahun ? 'selected' : '' ?>><?= $t ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Unit</label>
                            <select name="unit_id" id="f_unit" class="form-select">
                                <option value="0">Global (semua unit)</option>
                                <?php foreach ($units as $u) : ?>
                                    <option value="<?= (int)$u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Platform</label>
                            <select name="platform" id="f_platform" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach ($platforms as $p) : ?>
                                    <option value="<?= esc($p) ?>"><?= ucfirst(esc($p)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Metric</label>
                            <select name="metric" id="f_metric" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach ($metrics as $m) : ?>
                                    <option value="<?= esc($m) ?>"><?= esc($m) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Target Value</label>
                            <input type="number" name="target_value" id="f_target" class="form-control" step="0.01" min="0" value="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const t = document.getElementById('targetModalTitle');
        document.querySelectorAll('.edit-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                t.textContent = 'Edit Target';
                document.getElementById('f_unit').value = btn.dataset.unit;
                document.getElementById('f_platform').value = btn.dataset.platform;
                document.getElementById('f_metric').value = btn.dataset.metric;
                document.getElementById('f_target').value = btn.dataset.target;
            });
        });
        document.querySelector('[data-bs-target="#targetModal"].btn-dark')?.addEventListener('click', function() {
            t.textContent = 'Tambah Target';
            document.getElementById('f_unit').value = '0';
            document.getElementById('f_platform').value = '';
            document.getElementById('f_metric').value = '';
            document.getElementById('f_target').value = '0';
        });
    });
</script>