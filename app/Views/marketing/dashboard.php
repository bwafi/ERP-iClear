<div class="card shadow-none position-relative overflow-hidden mb-4"
    style="background: linear-gradient(120deg, #0f2b46 0%, #1d4e89 60%, #2a6dbb 100%);">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div class="text-white">
            <h4 class="fw-semibold mb-1 text-white">Dashboard Digital Marketing</h4>
            <small class="text-white-50">Kepala Divisi — seluruh actual dihitung otomatis dari data operasional (lead, biaya iklan, transaksi customer hasil lead).</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active text-white">Dashboard Digital Marketing</li>
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
                <a href="<?= base_url('marketing/leads') ?>" class="btn btn-primary">
                    <iconify-icon icon="solar:users-group-rounded-bold" class="me-1"></iconify-icon>Lead
                </a>
                <a href="<?= base_url('marketing/ads_performa') ?>" class="btn btn-primary">
                    <iconify-icon icon="solar:chart-2-bold" class="me-1"></iconify-icon>Performa Ads
                </a>
                <a href="<?= base_url('konten/channel') ?>" class="btn btn-light">
                    <iconify-icon icon="solar:chart-2-bold" class="me-1"></iconify-icon>Performa Channel
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$statIcons = [
    'LEAD_MARKETING'       => ['solar:users-group-rounded-bold', 'primary'],
    'CUSTOMER_MARKETING'   => ['solar:user-id-bold', 'success'],
    'CONVERSION_MARKETING' => ['solar:tuning-3-bold', 'info'],
    'CPL'                  => ['solar:wallet-bold', 'warning'],
    'OMZET_MARKETING'      => ['solar:banknote-bold', 'secondary'],
    'ROAS_MARKETING'       => ['solar:chart-up-bold', 'danger'],
    'CHANNEL_GROWTH'       => ['solar:chart-2-bold', 'dark'],
];
?>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><iconify-icon icon="solar:pie-chart-2-bold" class="text-primary me-1"></iconify-icon>Komposisi Lead</h5>
                <small class="text-muted"><?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></small>
            </div>
            <div class="card-body">
                <div id="chartLeadsStatus"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><iconify-icon icon="solar:chart-bold" class="text-primary me-1"></iconify-icon>Tren Lead &amp; Closed</h5>
                <small class="text-muted">6 bulan terakhir — lead masuk vs customer hasil conversion.</small>
            </div>
            <div class="card-body">
                <div id="chartLeadTrend"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0"><iconify-icon icon="solar:wallet-bold" class="text-primary me-1"></iconify-icon>Omzet Marketing vs Biaya Iklan</h5>
                <small class="text-muted">6 bulan terakhir.</small>
            </div>
            <div class="card-body">
                <div id="chartOmzetAds"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><iconify-icon icon="solar:bolt-bold" class="text-primary me-1"></iconify-icon>ROAS</h5>
                <small class="text-muted">Omzet / Biaya Iklan — <?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></small>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <?php $roasVal = $summary['items'][5]['key_value'] ?? null; ?>
                <div class="display-5 fw-bold text-success">
                    <?= $roasVal === null ? 'N/A' : number_format($roasVal, 2, ',', '.') ?>×
                </div>
                <span class="badge rounded-pill text-bg-<?= ($roasVal ?? 0) >= 1.5 ? 'success' : 'danger' ?> mt-2">
                    target <?= number_format(1.5, 2, ',', '.') ?>×
                </span>
                <div class="mt-3 w-100">
                    <div id="chartRoasGauge"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php foreach ($summary['items'] as $it) :
        $ico = $statIcons[$it['key']] ?? ['solar:widget-3-bold', 'primary'];
    ?>
        <div class="col-md-4 col-xl-2 px-1">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center justify-content-center rounded-3 text-white text-bg-<?= $ico[1] ?>"
                            style="width:40px;height:40px;">
                            <iconify-icon icon="<?= $ico[0] ?>" width="22" height="22"></iconify-icon>
                        </div>
                        <span class="badge rounded-pill text-bg-<?= $it['achievement'] === null ? 'secondary' : ($it['achievement'] >= 100 ? 'success' : ($it['achievement'] >= 80 ? 'warning' : 'danger')) ?>">
                            <?= $it['achievement'] === null ? 'N/A' : number_format($it['achievement'], 0, ',', '.') . '%' ?>
                        </span>
                    </div>
                    <h4 class="mb-0 fw-semibold text-truncate" title="<?= esc($it['actual_label']) ?>">
                        <?= esc($it['actual_label']) ?>
                    </h4>
                    <small class="text-muted"><?= esc($it['name']) ?> · target <?= esc($it['target_label']) ?></small>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <h5 class="mb-0"><iconify-icon icon="solar:clipboard-list-bold" class="text-primary me-1"></iconify-icon>Rekap Marketing Harian (Format CS)</h5>
        <a href="<?= base_url('marketing/rekap') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square me-1"></i>Input Harian</a>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-end">
            <?php
            $rk = $rekap;
            $rkCards = [
                ['Non Iklan', (int)$rk['non_iklan'], 'solar:user-block-bold', 'secondary'],
                ['Iklan', (int)$rk['iklan'], 'solar:megaphone-bold', 'primary'],
                ['Total', (int)$rk['total'], 'solar:users-group-rounded-bold', 'info'],
                ['Prospek', (int)$rk['prospek'], 'solar:target-bold', 'warning'],
                ['Datang', (int)$rk['datang'], 'solar:login-2-bold', 'success'],
                ['Rate', number_format((float)$rk['rate'], 2, ',', '.') . '%', 'solar:chart-2-bold', 'danger'],
            ];
            foreach ($rkCards as $c) :
            ?>
                <div class="card border-0 shadow-sm flex-grow-1" style="min-width:130px;">
                    <div class="card-body p-3 py-2 d-flex align-items-center gap-2">
                        <div class="d-flex align-items-center justify-content-center rounded-3 text-white text-bg-<?= $c[3] ?>" style="width:34px;height:34px;">
                            <iconify-icon icon="<?= $c[2] ?>" width="18" height="18"></iconify-icon>
                        </div>
                        <div>
                            <div class="small text-muted"><?= $c[0] ?></div>
                            <div class="fw-semibold"><?= $c[1] ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="row g-2 mt-1 small text-muted">
            <div class="col-auto">
                <i class="bi bi-whatsapp me-1"></i>Total Lead WA/DM: <span class="fw-semibold text-dark"><?= (int)$rk['total_lead_wa_dm'] ?></span>
            </div>
            <div class="col-auto">
                <i class="bi bi-megaphone me-1"></i>Lead Total Iklan (Dashboard): <span class="fw-semibold text-dark"><?= (int)$rk['lead_total_iklan_dashboard'] ?></span>
            </div>
            <div class="col-auto">
                <i class="bi bi-calendar-check me-1"></i>Hari terisi: <span class="fw-semibold text-dark"><?= (int)$rk['total_days'] ?></span>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="mb-0">Ringkasan KPI Digital Marketing</h5>
                <small class="text-muted"><?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?> — bobot sesuai engine KPI existing (total <?= $summary['weightsum'] ?>%).</small>
            </div>
        </div>
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
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <iconify-icon icon="<?= $statIcons[$it['key']][0] ?? 'solar:widget-3-bold' ?>" class="text-primary"></iconify-icon>
                                    <span class="fw-semibold"><?= esc($it['name']) ?></span>
                                </div>
                            </td>
                            <td class="text-center"><?= esc($it['target_label']) ?></td>
                            <td class="text-center"><span class="badge rounded-pill bg-light text-dark border"><?= $it['bobot'] ?>%</span></td>
                            <td><?= esc($it['actual_label']) ?></td>
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
                        <th class="text-end fw-bold text-primary">
                            <?= number_format(array_sum(array_map(fn($i) => $i['achievement'] === null ? 0 : $i['achievement'] * $i['bobot'] / 100, $summary['items'])), 2, ',', '.') ?>
                        </th>
                    </tr>
                </tfoot>
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
            <h5 class="mb-0">Pertumbuhan Channel — <?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></h5>
            <small class="text-muted">metric dihitung per channel + metric (tidak dijumlahkan antar metric).</small>
        </div>
        <?php if (($summary['channel']['kpi_achievement'] ?? null) !== null) : ?>
            <span class="badge rounded-pill text-bg-primary fs-6 py-2">
                <iconify-icon icon="solar:chart-2-bold" class="me-1"></iconify-icon>
                Rata-rata Achievement KPI: <?= number_format($summary['channel']['kpi_achievement'], 2, ',', '.') ?>%
            </span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($summary['channel']['rows'])) : ?>
            <div class="text-center py-4">
                <iconify-icon icon="solar:chart-2-line-outline" class="text-muted fs-1"></iconify-icon>
                <p class="text-muted mt-2 mb-1">Belum ada data performa channel.</p>
                <a href="<?= base_url('konten/channel') ?>" class="btn btn-sm btn-primary">Input Performa Channel</a>
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
                        <?php foreach ($summary['channel']['rows'] as $c) : ?>
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

<script>
    var chartCommon = {
        chart: { toolbar: { show: false }, fontFamily: 'inherit' },
        dataLabels: { enabled: false },
    };

    // Donut komposisi detail prospek (manual, non-Kommo)
    var lbStatus = <?= json_encode(array_values($leadsByStatus)) ?>;
    new ApexCharts(document.querySelector('#chartLeadsStatus'), {
        chart: { type: 'pie', fontFamily: 'inherit', toolbar: { show: false }, width: '100%', height: 300 },
        labels: ['PROSPEK', 'BOOKING', 'DATANG', 'CLOSED', 'BATAL'],
        series: lbStatus,
        colors: ['#0d6efd', '#6f42c1', '#20c997', '#198754', '#dc3545'],
        legend: { position: 'bottom' },
        stroke: { width: 0 },
        dataLabels: { enabled: true, formatter: function(v) { return Math.round(v) + '%'; } },
    }).render();

    // Column tren lead & closed
    new ApexCharts(document.querySelector('#chartLeadTrend'), Object.assign({}, chartCommon, {
        chart: Object.assign({}, chartCommon.chart, { type: 'bar', height: 300 }),
        series: [
            { name: 'Lead', data: <?= json_encode($trend['leads']) ?> },
            { name: 'Closed', data: <?= json_encode($trend['won']) ?> },
        ],
        xaxis: { categories: <?= json_encode($trend['labels']) ?> },
        plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } },
        colors: ['#0d6efd', '#198754'],
        legend: { position: 'top' },
        yaxis: { labels: { formatter: function(v) { return Math.round(v); } } },
    })).render();

    // Column omzet vs biaya iklan
    new ApexCharts(document.querySelector('#chartOmzetAds'), Object.assign({}, chartCommon, {
        chart: Object.assign({}, chartCommon.chart, { type: 'bar', height: 260 }),
        series: [
            { name: 'Omzet Marketing', type: 'bar', data: <?= json_encode($trend['omzet']) ?> },
            { name: 'Biaya Iklan', type: 'bar', data: <?= json_encode($trend['ads']) ?> },
        ],
        xaxis: { categories: <?= json_encode($trend['labels']) ?> },
        plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } },
        colors: ['#198754', '#fd7e14'],
        stroke: { width: 0 },
        legend: { position: 'top' },
        yaxis: { labels: { formatter: function(v) { return v >= 1000000 ? (v / 1000000).toFixed(1) + ' jt' : v >= 1000 ? (v / 1000).toFixed(0) + ' rb' : v; } } },
    })).render();

    // Gauge ROAS
    var roasVal = <?= json_encode((($rv = $summary['items'][5]['key_value'] ?? null) !== null) ? round((float)$rv, 2) : null) ?>;
    new ApexCharts(document.querySelector('#chartRoasGauge'), {
        chart: { type: 'radialBar', fontFamily: 'inherit', height: 170, toolbar: { show: false } },
        series: [roasVal === null ? 0 : Math.min(100, Math.round(roasVal / 3 * 100))],
        plotOptions: {
            radialBar: {
                hollow: { size: '60%' },
                dataLabels: {
                    name: { show: false },
                    value: { fontSize: '20px', formatter: function() { return roasVal === null ? 'N/A' : roasVal.toFixed(1) + '×'; } },
                },
            },
        },
        colors: [roasVal >= 1.5 ? '#198754' : '#dc3545'],
        labels: ['ROAS'],
    }).render();
</script>