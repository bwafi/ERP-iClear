<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
            <h4 class="fw-semibold mb-0">Dashboard Digital Marketing</h4>
            <small class="text-muted">Data operasional marketing (KPI Multimedia/Creative). Scope: <?= esc($scopeLabel) ?>.</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                </li>
                <li class="breadcrumb-item active">Digital Marketing</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Bulan</label>
                <select name="bulan" class="form-select" onchange="this.form.submit()">
                    <?php for ($i = 1; $i <= 12; $i++) : ?>
                        <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tahun</label>
                <select name="tahun" class="form-select" onchange="this.form.submit()">
                    <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++) : ?>
                        <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <a href="<?= base_url('konten') ?>" class="btn btn-light w-100">Manajemen Konten</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <?php
    $statCards = [
        ['label' => 'Total Content',   'value' => $stats['total'],     'icon' => 'solar:gallery-bold',  'color' => 'primary'],
        ['label' => 'Production',      'value' => $stats['production'],'icon' => 'solar:pen-new-square-broken', 'color' => 'info'],
        ['label' => 'QC',              'value' => $stats['qc'],        'icon' => 'solar:list-check-bold', 'color' => 'warning'],
        ['label' => 'Published',       'value' => $stats['published'], 'icon' => 'solar:cloud-upload-bold', 'color' => 'success'],
        ['label' => 'Completed',       'value' => $stats['completed'], 'icon' => 'solar:check-read-bold', 'color' => 'success'],
        ['label' => 'Overdue',         'value' => $stats['overdue'],   'icon' => 'solar:calendar-mark-bold', 'color' => 'danger'],
    ];
    foreach ($statCards as $c) : ?>
        <div class="col-md-4 col-xl-2">
            <div class="card shadow-sm border-0">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="me-3 d-flex align-items-center justify-content-center rounded-3 text-bg-<?= $c['color'] ?>" style="width:54px;height:54px;">
                        <iconify-icon icon="<?= $c['icon'] ?>" width="28" height="28"></iconify-icon>
                    </div>
                    <div>
                        <h3 class="mb-0 fw-semibold"><?= number_format($c['value']) ?></h3>
                        <small class="text-muted"><?= $c['label'] ?></small>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm border-0 mt-3">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="mb-0">Pertumbuhan Channel (<?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?>)</h5>
            <small class="text-muted">Growth vs periode sebelumnya. Achievement = Growth / Target Growth × 100.</small>
        </div>
        <div class="d-flex gap-2">
            <?php if (($kpi['channel']['kpi_achievement'] ?? null) !== null) : ?>
                <span class="badge text-bg-primary fs-6 py-2">
                    Rata-rata Achievement KPI: <?= number_format($kpi['channel']['kpi_achievement'], 2, ',', '.') ?>%
                </span>
            <?php endif; ?>
            <a href="<?= base_url('konten/channel') ?>" class="btn btn-light btn-sm">Input Performa Channel</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($kpi['channel']['rows'])) : ?>
            <div class="text-muted small">Belum ada data performa channel untuk periode ini.</div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Channel</th>
                            <th>Metric</th>
                            <th class="text-end">Previous</th>
                            <th class="text-end">Actual</th>
                            <th class="text-center">Growth</th>
                            <th class="text-center">Target</th>
                            <th class="text-center">Achievement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kpi['channel']['rows'] as $c) : ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($c['channel_name']) ?></td>
                                <td><?= esc($c['metric_name']) ?>
                                    <?php if ($c['is_kpi']) : ?>
                                        <span class="badge bg-success-subtle text-success ms-1">KPI</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= $c['previous'] !== null ? number_format($c['previous'], 0, ',', '.') : '<span class="text-muted">N/A</span>' ?></td>
                                <td class="text-end fw-semibold"><?= number_format($c['actual'], 0, ',', '.') ?></td>
                                <td class="text-center">
                                    <?php if ($c['growth'] === null) : ?>
                                        <span class="badge text-bg-secondary">N/A</span>
                                    <?php else : ?>
                                        <span class="badge <?= $c['growth'] >= 0 ? 'text-bg-success' : 'text-bg-danger' ?>">
                                            <?= $c['growth'] > 0 ? '+' : '' ?><?= number_format($c['growth'], 2, ',', '.') ?>%
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= $c['target'] !== null ? number_format($c['target'], 2, ',', '.') . '%' : '-' ?></td>
                                <td class="text-center">
                                    <?php if ($c['achievement'] === null) : ?>
                                        <span class="text-muted">-</span>
                                    <?php else : ?>
                                        <span class="badge <?= $c['achievement'] >= 100 ? 'text-bg-success' : ($c['achievement'] >= 80 ? 'text-bg-warning' : 'text-bg-danger') ?>">
                                            <?= number_format($c['achievement'], 2, ',', '.') ?>%
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm border-0 mt-3">
    <div class="card-header bg-white">
        <h5 class="mb-0">Ringkasan KPI Creative (<?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?>)</h5>
        <small class="text-muted">Achievement &amp; bobot sesuai engine KPI existing. Satu content dihitung satu kali.</small>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>KPI</th>
                        <th class="text-center">Target</th>
                        <th class="text-center">Bobot</th>
                        <th>Realisasi</th>
                        <th class="text-center">Achievement</th>
                        <th class="text-end">Nilai Tertimbang</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kpi['items'] as $it) : ?>
                        <tr>
                            <td><?= esc($it['name']) ?></td>
                            <td class="text-center"><?= esc($it['target']) ?></td>
                            <td class="text-center"><?= $it['bobot'] ?>%</td>
                            <td><?= esc($it['realisasi']) ?></td>
                            <td class="text-center">
                                <?php if ($it['achievement'] === null) : ?>
                                    <span class="badge text-bg-secondary">N/A</span>
                                <?php else : ?>
                                    <span class="badge <?= $it['achievement'] >= 100 ? 'text-bg-success' : ($it['achievement'] >= 80 ? 'text-bg-warning' : 'text-bg-danger') ?>">
                                        <?= number_format($it['achievement'], 2, ',', '.') ?>%
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-semibold">
                                <?= $it['achievement'] === null ? '-' : number_format($it['achievement'] * $it['bobot'] / 100, 2, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <th colspan="5" class="text-end">Total Skor Digital Marketing</th>
                        <th class="text-end fw-bold"><?= number_format($kpi['weighted_total'], 2, ',', '.') ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>