<div class="card shadow-none position-relative overflow-hidden mb-4"
    style="background: linear-gradient(120deg, #0f2b46 0%, #1d4e89 60%, #2a6dbb 100%);">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div class="text-white">
            <h4 class="fw-semibold mb-1 text-white">Dashboard Multimedia &amp; Creative</h4>
            <small class="text-white-50">Data operasional marketing (KPI Multimedia/Creative). Scope: <?= esc($scopeLabel) ?>.</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active text-white">Digital Marketing</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-end justify-content-between">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-1 small text-muted">Bulan</label>
                    <select name="bulan" class="form-select" onchange="this.form.submit()">
                        <?php for ($i = 1; $i <= 12; $i++) : ?>
                            <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-1 small text-muted">Tahun</label>
                    <select name="tahun" class="form-select" onchange="this.form.submit()">
                        <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++) : ?>
                            <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= base_url('konten') ?>" class="btn btn-primary">
                    <iconify-icon icon="solar:gallery-bold" class="me-1"></iconify-icon>Manajemen Konten
                </a>
                <a href="<?= base_url('konten/channel') ?>" class="btn btn-light">
                    <iconify-icon icon="solar:chart-2-bold" class="me-1"></iconify-icon>Performa Channel
                </a>
                <a href="<?= base_url('marketing') ?>" class="btn btn-light">
                    <iconify-icon icon="solar:widget-3-bold" class="me-1"></iconify-icon>Dashboard Digital Marketing
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php
    $statCards = [
        ['label' => 'Total Content',   'value' => $stats['total'],      'icon' => 'solar:gallery-bold',            'color' => 'primary'],
        ['label' => 'Production',      'value' => $stats['production'], 'icon' => 'solar:pen-new-square-broken',   'color' => 'info'],
        ['label' => 'QC',              'value' => $stats['qc'],         'icon' => 'solar:list-check-bold',         'color' => 'warning'],
        ['label' => 'Published',       'value' => $stats['published'],  'icon' => 'solar:cloud-upload-bold',       'color' => 'success'],
        ['label' => 'Completed',       'value' => $stats['completed'],  'icon' => 'solar:check-read-bold',         'color' => 'success'],
        ['label' => 'Overdue',         'value' => $stats['overdue'],    'icon' => 'solar:calendar-mark-bold',      'color' => 'danger'],
    ];
    foreach ($statCards as $c) : ?>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center justify-content-center rounded-3 text-white text-bg-<?= $c['color'] ?>" style="width:50px;height:50px;">
                        <iconify-icon icon="<?= $c['icon'] ?>" width="26" height="26"></iconify-icon>
                    </div>
                    <div class="pt-1">
                        <h4 class="mb-0 fw-semibold"><?= number_format($c['value']) ?></h4>
                        <small class="text-muted"><?= $c['label'] ?></small>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><iconify-icon icon="solar:pie-chart-2-bold" class="text-primary me-1"></iconify-icon>Status Konten</h5>
                <small class="text-muted"><?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></small>
            </div>
            <div class="card-body">
                <div id="chartKontenStatus"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><iconify-icon icon="solar:chart-bold" class="text-primary me-1"></iconify-icon>Achievement KPI Creative</h5>
                <small class="text-muted">per item KPI — tercapai bila ≥ 100%.</small>
            </div>
            <div class="card-body">
                <div id="chartKontenKpi"></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white">
        <h5 class="mb-0"><iconify-icon icon="solar:chart-2-bold" class="text-primary me-1"></iconify-icon>Growth Channel</h5>
        <small class="text-muted">Growth % per channel + metric — <?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></small>
    </div>
    <div class="card-body">
        <div id="chartChannelGrowth"></div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="mb-0">Pertumbuhan Channel — <?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></h5>
            <small class="text-muted">Growth vs periode sebelumnya. Achievement = Growth / Target Growth × 100.</small>
        </div>
        <div class="d-flex gap-2">
            <?php if (($kpi['channel']['kpi_achievement'] ?? null) !== null) : ?>
                <span class="badge rounded-pill text-bg-primary fs-6 py-2">
                    <iconify-icon icon="solar:chart-2-bold" class="me-1"></iconify-icon>
                    Rata-rata Achievement KPI: <?= number_format($kpi['channel']['kpi_achievement'], 2, ',', '.') ?>%
                </span>
            <?php endif; ?>
            <a href="<?= base_url('konten/channel') ?>" class="btn btn-primary btn-sm">
                <iconify-icon icon="solar:add-circle-bold" class="me-1"></iconify-icon>Input Performa
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($kpi['channel']['rows'])) : ?>
            <div class="text-center py-4">
                <iconify-icon icon="solar:chart-2-line-outline" class="text-muted fs-1"></iconify-icon>
                <p class="text-muted mt-2 mb-0">Belum ada data performa channel untuk periode ini.</p>
            </div>
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
                                <td class="fw-semibold">
                                    <iconify-icon icon="solar:instagram-line-bold" class="text-muted me-1"></iconify-icon>
                                    <?= esc($c['channel_name']) ?>
                                </td>
                                <td><?= esc($c['metric_name']) ?>
                                    <?php if ($c['is_kpi']) : ?>
                                        <span class="badge rounded-pill bg-success-subtle text-success ms-1">KPI</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= $c['previous'] !== null ? number_format($c['previous'], 0, ',', '.') : '<span class="text-muted">N/A</span>' ?></td>
                                <td class="text-end fw-semibold"><?= number_format($c['actual'], 0, ',', '.') ?></td>
                                <td class="text-center">
                                    <?php if ($c['growth'] === null) : ?>
                                        <span class="badge rounded-pill text-bg-secondary">N/A</span>
                                    <?php else : ?>
                                        <span class="badge rounded-pill <?= $c['growth'] >= 0 ? 'text-bg-success' : 'text-bg-danger' ?>">
                                            <?= $c['growth'] > 0 ? '+' : '' ?><?= number_format($c['growth'], 2, ',', '.') ?>%
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= $c['target'] !== null ? number_format($c['target'], 2, ',', '.') . '%' : '-' ?></td>
                                <td class="text-center">
                                    <?php if ($c['achievement'] === null) : ?>
                                        <span class="text-muted">-</span>
                                    <?php else : ?>
                                        <span class="badge rounded-pill <?= $c['achievement'] >= 100 ? 'text-bg-success' : ($c['achievement'] >= 80 ? 'text-bg-warning' : 'text-bg-danger') ?>">
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

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <h5 class="mb-0">Ringkasan KPI Creative — <?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></h5>
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
                            <td class="fw-semibold"><?= esc($it['name']) ?></td>
                            <td class="text-center"><?= esc($it['target']) ?></td>
                            <td class="text-center"><span class="badge rounded-pill bg-light text-dark border"><?= $it['bobot'] ?>%</span></td>
                            <td><?= esc($it['realisasi']) ?></td>
                            <td class="text-center">
                                <?php if ($it['achievement'] === null) : ?>
                                    <span class="badge rounded-pill text-bg-secondary">N/A</span>
                                <?php else : ?>
                                    <span class="badge rounded-pill <?= $it['achievement'] >= 100 ? 'text-bg-success' : ($it['achievement'] >= 80 ? 'text-bg-warning' : 'text-bg-danger') ?>">
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
                        <th class="text-end fw-bold text-primary"><?= number_format($kpi['weighted_total'], 2, ',', '.') ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
    if (window.ApexCharts) {
        // Donut status konten
        var chartKontenStatusEl = document.querySelector('#chartKontenStatus');
        if (chartKontenStatusEl) {
            new ApexCharts(chartKontenStatusEl, {
                chart: { type: 'pie', fontFamily: 'inherit', toolbar: { show: false }, height: 260 },
                labels: ['Production', 'QC', 'Published', 'Completed', 'Overdue'],
                series: [
                    <?= (int)$stats['production'] ?>,
                    <?= (int)$stats['qc'] ?>,
                    <?= (int)$stats['published'] ?>,
                    <?= (int)$stats['completed'] ?>,
                    <?= (int)$stats['overdue'] ?>,
                ],
                colors: ['#0dcaf0', '#ffc107', '#0d6efd', '#198754', '#dc3545'],
                legend: { position: 'bottom' },
                stroke: { width: 0 },
                dataLabels: { enabled: true, formatter: function(v) { return Math.round(v) + '%'; } },
            }).render();
        }

        // Bar achievement KPI creative
        var chartKontenKpiEl = document.querySelector('#chartKontenKpi');
        if (chartKontenKpiEl) {
            new ApexCharts(chartKontenKpiEl, {
                chart: { type: 'bar', fontFamily: 'inherit', toolbar: { show: false }, height: 260 },
                series: [{ name: 'Achievement %', data: <?= json_encode(array_map(fn($i) => $i['achievement'] === null ? null : round($i['achievement'], 2), $kpi['items'])) ?> }],
                xaxis: { categories: <?= json_encode(array_map(fn($i) => $i['name'], $kpi['items'])) ?> },
                plotOptions: { bar: { columnWidth: '45%', borderRadius: 3 } },
                colors: ['#1d4e89'],
                dataLabels: { enabled: true, formatter: function(v) { return v === null ? 'N/A' : v + '%'; } },
                yaxis: { max: 120, labels: { formatter: function(v) { return v + '%'; } } },
            }).render();
        }

        // Bar growth channel
        var chartChannelGrowthEl = document.querySelector('#chartChannelGrowth');
        if (chartChannelGrowthEl) {
            var chRows = <?= json_encode(array_map(
                fn($c) => [
                    'name'   => $c['channel_name'] . ' · ' . $c['metric_name'],
                    'growth' => $c['growth'] === null ? null : round($c['growth'], 2),
                    'ach'    => $c['achievement'] === null ? null : round($c['achievement'], 2),
                ],
                $kpi['channel']['rows'] ?? []
            )) ?>;
            new ApexCharts(chartChannelGrowthEl, {
                chart: { type: 'bar', fontFamily: 'inherit', toolbar: { show: false }, height: 280 },
                series: [
                    { name: 'Growth %', data: chRows.map(function(r) { return r.growth; }) },
                    { name: 'Achievement %', data: chRows.map(function(r) { return r.ach; }) },
                ],
                xaxis: { categories: chRows.map(function(r) { return r.name; }) },
                plotOptions: { bar: { horizontal: true, columnWidth: '55%', borderRadius: 3 } },
                colors: ['#0d6efd', '#198754'],
                stroke: { width: 0 },
                legend: { position: 'top' },
                dataLabels: { enabled: false },
                yaxis: { labels: { formatter: function(v) { return v === null ? 'N/A' : v; } } },
            }).render();
        }
    }
</script>