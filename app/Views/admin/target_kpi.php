<div class="card shadow-none position-relative overflow-hidden mb-4"
    style="background: linear-gradient(120deg, #0f2b46 0%, #1d4e89 60%, #2a6dbb 100%);">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div class="text-white">
            <h4 class="fw-semibold mb-1 text-white">Target KPI</h4>
            <small class="text-white-50">Edit target omset / target KPI (tabel kpi_targets). Khusus Admin Root & Manager.</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active text-white">Target KPI</li>
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
                <label class="form-label mb-1 small text-muted">Komponen KPI</label>
                <select name="kpi_component_id" class="form-select form-select-sm">
                    <option value="0">Semua</option>
                    <?php foreach ($components as $c) : ?>
                        <option value="<?= (int)$c->id ?>" <?= (int)$c->id == ($filter['kpi_component_id'] ?? 0) ? 'selected' : '' ?>>
<?= esc($c->name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-1 small text-muted">Unit</label>
                <select name="unit_id" class="form-select form-select-sm">
                    <option value="0">Global & Semua</option>
                    <?php foreach ($units as $u) : ?>
                        <option value="<?= (int)$u->idunit ?>" <?= (int)$u->idunit == ($filter['unit_id'] ?? 0) ? 'selected' : '' ?>> <?= esc($u->NAMA_UNIT) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-1 small text-muted">Jabatan</label>
                <select name="position_id" class="form-select form-select-sm">
                    <option value="0">Semua</option>
                    <?php foreach ($positions as $p) : ?>
                        <option value="<?= (int)$p->ID_JABATAN ?>" <?= (int)$p->ID_JABATAN == ($filter['position_id'] ?? 0) ? 'selected' : '' ?>> <?= esc($p->NAMA_JABATAN) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
            </div>
        </form>
        <button type="button" class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#targetKpiModal">
            <i class="bi bi-plus-lg me-1"></i>Tambah Target
        </button>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle table-sm">
            <thead>
                <tr>
                    <th>Komponen</th>
                    <th>Unit</th>
                    <th>Jabatan</th>
                    <th class="text-end">Target</th>
                    <th class="text-center">Periode</th>
                    <th class="text-center">Efektif</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($targets)) : ?>
                    <tr><td colspan="7" class="text-center text-muted">Belum ada target sesuai filter.</td></tr>
                <?php endif; ?>
                <?php
                $unitMap = [];
                foreach ($units as $u) { $unitMap[(int)$u->idunit] = $u->NAMA_UNIT; }
                $positionMap = [];
                foreach ($positions as $p) { $positionMap[(int)$p->ID_JABATAN] = $p->NAMA_JABATAN; }
                ?>
                <?php foreach ($targets as $t) : ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= esc($t->name) ?></div>
                        </td>
                        <td><?= $t->unit_id === null ? '<span class="badge bg-secondary">Global</span>' : esc($unitMap[(int)$t->unit_id] ?? (string)$t->unit_id) ?></td>
                        <td><?= $t->position_id ? esc($positionMap[(int)$t->position_id] ?? (string)$t->position_id) : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-end fw-semibold"><?= $t->target_value !== null ? number_format((float)$t->target_value, 2, ',', '.') : '-' ?></td>
                        <td class="text-center small">
                            <?= $t->period_type ?>(<?= $t->period_month ?: '-' ?>)
                        </td>
                        <td class="text-center small">
                            <?= esc($t->effective_from) ?>
                            <?php if ($t->effective_to) : ?><br /><span class="text-muted">s/d <?= esc($t->effective_to) ?></span><?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary edit-btn" data-bs-toggle="modal" data-bs-target="#targetKpiModal"
                                data-id="<?= (int)$t->id ?>"
                                data-component="<?= (int)$t->kpi_component_id ?>"
                                data-unit="<?= $t->unit_id === null ? '0' : (int)$t->unit_id ?>"
                                data-position="<?= $t->position_id ? (int)$t->position_id : '0' ?>"
                                data-target="<?= $t->target_value ?? '' ?>"
                                data-period_type="<?= esc($t->period_type, 'attr') ?>"
                                data-period_month="<?= $t->period_month ? (int)$t->period_month : '0' ?>"
                                data-effective_from="<?= esc($t->effective_from, 'attr') ?>"
                                data-effective_to="<?= esc($t->effective_to ?? '', 'attr') ?>">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <form action="<?= base_url('penilaian/kpi/target/hapus') ?>" method="post" class="d-inline"
                                onsubmit="return confirm('Hapus target ini? Perlu diingat perubahan berpengaruh ke perhitungan KPI.')">
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

<div class="modal fade" id="targetKpiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('penilaian/kpi/target/simpan') ?>" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="targetKpiModalTitle">Tambah Target</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="f_id" value="0">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Komponen KPI</label>
                            <select name="kpi_component_id" id="f_component" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach ($components as $c) : ?>
                                    <option value="<?= (int)$c->id ?>"><?= esc($c->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unit</label>
                            <select name="unit_id" id="f_unit" class="form-select">
                                <option value="0">Global (semua unit)</option>
                                <?php foreach ($units as $u) : ?>
                                    <option value="<?= (int)$u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Jabatan (opsional)</label>
                            <select name="position_id" id="f_position" class="form-select">
                                <option value="0">Tanpa jabatan</option>
                                <?php foreach ($positions as $p) : ?>
                                    <option value="<?= (int)$p->ID_JABATAN ?>"><?= esc($p->NAMA_JABATAN) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipe Periode</label>
                            <select name="period_type" id="f_period_type" class="form-select">
                                <?php foreach ($periodTypes as $pt) : ?>
                                    <option value="<?= esc($pt) ?>"><?= esc($pt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Target Value</label>
                            <input type="number" name="target_value" id="f_target" class="form-control" step="0.01" min="0" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Bulan (monthly)</label>
                            <input type="number" name="period_month" id="f_period_month" class="form-control" min="0" max="12" value="0">
                            <small class="text-muted">0 = semua bulan</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tanggal Mulai Efektif</label>
                            <input type="date" name="effective_from" id="f_effective_from" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Akhir Efektif (opsional)</label>
                            <input type="date" name="effective_to" id="f_effective_to" class="form-control">
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
        const title = document.getElementById('targetKpiModalTitle');

        function setVal(id, value) {
            const el = document.getElementById(id);
            if (el) { el.value = value ?? ''; }
        }

        document.querySelectorAll('.edit-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                title.textContent = 'Edit Target';
                setVal('f_id', btn.dataset.id);
                setVal('f_component', btn.dataset.component);
                setVal('f_unit', btn.dataset.unit || '0');
                setVal('f_position', btn.dataset.position || '0');
                setVal('f_target', btn.dataset.target);
                setVal('f_period_type', btn.dataset.period_type || 'monthly');
                setVal('f_period_month', btn.dataset.period_month || '0');
                setVal('f_effective_from', btn.dataset.effective_from);
                setVal('f_effective_to', btn.dataset.effective_to);
            });
        });

        document.querySelector('[data-bs-target="#targetKpiModal"].btn-dark')?.addEventListener('click', function() {
            title.textContent = 'Tambah Target';
            ['f_id', 'f_effective_to'].forEach(function(id) {
                setVal(id, '');
            });
            setVal('f_component', '');
            setVal('f_unit', '0');
            setVal('f_position', '0');
            setVal('f_target', '0');
            setVal('f_period_type', 'monthly');
            setVal('f_period_month', '0');
            setVal('f_effective_from', '<?= date('Y-m-d') ?>');
        });
    });
</script>