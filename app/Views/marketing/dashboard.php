<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
            <h4 class="fw-semibold mb-0">KPI Digital Marketing</h4>
            <small class="text-muted">Kepala Divisi — seluruh actual dihitung otomatis dari data operasional (lead, biaya iklan, transaksi customer hasil lead).</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                </li>
                <li class="breadcrumb-item active">Marketing KPI</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session()->getFlashdata('success')) : ?>
    <div class="alert alert-success alert-dismissible fade show"><?= session()->getFlashdata('success') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= session()->getFlashdata('error') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

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
                <a href="<?= base_url('marketing/leads') ?>" class="btn btn-light w-100">Lead Marketing</a>
            </div>
            <div class="col-md-2">
                <a href="<?= base_url('marketing/ads') ?>" class="btn btn-light w-100">Biaya Iklan</a>
            </div>
            <div class="col-md-2">
                <a href="<?= base_url('konten/channel') ?>" class="btn btn-light w-100">Performa Channel</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 mt-3">
    <div class="card-header bg-white">
        <h5 class="mb-0">Ringkasan KPI Digital Marketing (<?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?>)</h5>
        <small class="text-muted">Bobot sesuai engine KPI existing (total <?= $summary['weightsum'] ?>%).</small>
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
                    <?php foreach ($summary['items'] as $it) : ?>
                        <tr>
                            <td><?= esc($it['name']) ?></td>
                            <td class="text-center"><?= esc($it['target_label']) ?></td>
                            <td class="text-center"><?= $it['bobot'] ?>%</td>
                            <td><?= $it['actual_label'] ?></td>
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
            </table>
        </div>
        <div class="small text-muted mt-2">
            Rumus: Conversion = Customer / Lead × 100 · CPL = Biaya Iklan / Paid Lead ·
            Omzet Marketing = penjualan + service dari customer hasil lead · ROAS = Omzet / Biaya Iklan ·
            Pertumbuhan = (actual − previous) / previous × 100 per channel+metric.
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mt-3">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="mb-0">Pertumbuhan Channel (<?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?>)</h5>
            <small class="text-muted">metric dihitung per channel + metric (tidak dijumlahkan antar metric).</small>
        </div>
        <?php if (($summary['channel']['kpi_achievement'] ?? null) !== null) : ?>
            <span class="badge text-bg-primary fs-6 py-2">
                Rata-rata Achievement KPI: <?= number_format($summary['channel']['kpi_achievement'], 2, ',', '.') ?>%
            </span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($summary['channel']['rows'])) : ?>
            <div class="text-muted small">Belum ada data performa channel. <a href="<?= base_url('konten/channel') ?>">Input Performa Channel</a>.</div>
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
                        <?php foreach ($summary['channel']['rows'] as $c) : ?>
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