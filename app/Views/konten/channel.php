<div class="card shadow-none position-relative overflow-hidden mb-4"
    style="background: linear-gradient(120deg, #0f2b46 0%, #1d4e89 60%, #2a6dbb 100%);">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div class="text-white">
            <h4 class="fw-semibold mb-1 text-white">Performa Channel</h4>
            <small class="text-white-50">Pertumbuhan performa social media (KPI 10%). Scope: <?= esc($scopeLabel) ?>.</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('konten/dashboard') ?>">Digital Marketing</a></li>
                <li class="breadcrumb-item active text-white">Performa Channel</li>
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

<?php if (empty($channels)) : ?>
    <div class="alert alert-warning">Belum ada channel terdaftar.</div>
<?php return;
endif; ?>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-end justify-content-between">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-1 small text-muted">Bulan</label>
                    <select name="bulan" class="form-select">
                        <?php for ($i = 1; $i <= 12; $i++) : ?>
                            <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-1 small text-muted">Tahun</label>
                    <select name="tahun" class="form-select">
                        <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++) : ?>
                            <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        <iconify-icon icon="solar:filter-bold"></iconify-icon> Tampilkan
                    </button>
                </div>
            </form>
            <div class="d-flex gap-2">
                <?php if ($kpiAchievement !== null) : ?>
                    <span class="badge rounded-pill text-bg-primary fs-6 py-2">
                        <iconify-icon icon="solar:chart-2-bold" class="me-1"></iconify-icon>
                        Rata-rata Achievement: <?= number_format($kpiAchievement, 2, ',', '.') ?>%
                    </span>
                <?php endif; ?>
                <a href="<?= base_url('marketing') ?>" class="btn btn-light">
                    <iconify-icon icon="solar:widget-3-bold" class="me-1"></iconify-icon>Marketing KPI
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($canWrite) : ?>
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <iconify-icon icon="solar:add-circle-bold" class="text-primary me-1"></iconify-icon> Tambah Performa
        </h5>
        <small class="text-muted">Cukup input Actual. Previous, Growth, dan Achievement dihitung sistem.</small>
    </div>
    <div class="card-body">
        <form method="post" action="<?= base_url('konten/channel/simpan') ?>" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label mb-1 small text-muted">Channel</label>
                <select name="channel_id" id="chChannel" class="form-select" required>
                    <?php foreach ($channels as $ch) : ?>
                        <option value="<?= $ch->id ?>"><?= esc($ch->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small text-muted">Metric</label>
                <select name="metric_id" id="chMetric" class="form-select" required></select>
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1 small text-muted">Bulan</label>
                <select name="period_month" class="form-select">
                    <?php for ($i = 1; $i <= 12; $i++) : ?>
                        <option value="<?= $i ?>" <?= $i === $bulan ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1 small text-muted">Tahun</label>
                <input type="number" name="period_year" class="form-control" value="<?= $tahun ?>" min="2000" max="2100" required>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small text-muted">Actual</label>
                <input type="text" name="actual" id="chActual" class="form-control angka-ribuan" placeholder="5.500" inputmode="numeric" required>
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1 small text-muted">Target Growth %</label>
                <input type="number" name="target_growth" id="chTarget" class="form-control" placeholder="8" min="0" step="0.01">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small text-muted">Catatan (opsional)</label>
                <input type="text" name="note" class="form-control" placeholder="catatan">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-success w-100 h-100 d-inline-flex align-items-center justify-content-center gap-1">
                    <iconify-icon icon="solar:add-circle-bold"></iconify-icon> Simpan
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($rows)) : ?>
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white">
        <h5 class="mb-0"><iconify-icon icon="solar:chart-bold" class="text-primary me-1"></iconify-icon>Growth &amp; Achievement per Channel</h5>
        <small class="text-muted"><?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></small>
    </div>
    <div class="card-body">
        <div id="chartChannelBar"></div>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="mb-0">Data Performa — <?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></h5>
            <small class="text-muted">Growth = (Actual − Previous) / Previous × 100. Achievement = Growth / Target × 100.</small>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($rows)) : ?>
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
                            <th class="text-center">KPI?</th>
                            <th class="text-end">Target</th>
                            <th class="text-end">Previous</th>
                            <th class="text-end">Actual</th>
                            <th class="text-center">Growth</th>
                            <th class="text-center">Achievement</th>
                            <th>Catatan</th>
                            <?php if ($canWrite) : ?>
                                <th class="text-end">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r) : ?>
                            <tr>
                                <td class="fw-semibold">
                                    <iconify-icon icon="solar:instagram-line-bold" class="text-muted me-1"></iconify-icon>
                                    <?= esc($r['channel_name']) ?>
                                </td>
                                <td><?= esc($r['metric_name']) ?></td>
                                <td class="text-center">
                                    <?php if ($r['is_kpi']) : ?>
                                        <span class="badge rounded-pill bg-success-subtle text-success">KPI</span>
                                    <?php else : ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= $r['target'] !== null ? number_format($r['target'], 2, ',', '.') . '%' : '-' ?></td>
                                <td class="text-end"><?= $r['previous'] !== null ? number_format($r['previous'], 0, ',', '.') : '<span class="text-muted">N/A</span>' ?></td>
                                <td class="text-end fw-semibold"><?= number_format($r['actual'], 0, ',', '.') ?></td>
                                <td class="text-center">
                                    <?php if ($r['growth'] === null) : ?>
                                        <span class="badge rounded-pill text-bg-secondary">New / N/A</span>
                                    <?php else : ?>
                                        <span class="badge rounded-pill <?= $r['growth'] >= 0 ? 'text-bg-success' : 'text-bg-danger' ?>">
                                            <?= $r['growth'] > 0 ? '+' : '' ?><?= number_format($r['growth'], 2, ',', '.') ?>%
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($r['achievement'] === null) : ?>
                                        <span class="text-muted">-</span>
                                    <?php else : ?>
                                        <span class="badge rounded-pill <?= $r['achievement'] >= 100 ? 'text-bg-success' : ($r['achievement'] >= 80 ? 'text-bg-warning' : 'text-bg-danger') ?>">
                                            <?= number_format($r['achievement'], 2, ',', '.') ?>%
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= esc($r['note']) ?></small></td>
                                <?php if ($canWrite) : ?>
                                    <td class="text-end">
                                        <form method="post" action="<?= base_url('konten/channel/hapus') ?>" class="m-0" onsubmit="return confirm('Hapus performa channel ini?');">
                                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i> Hapus
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

<script>
    var CHANNEL_METRICS = <?= json_encode($metrics, JSON_UNESCAPED_UNICODE) ?>;

    function fmtRibuan(v) {
        while (/(\d+)(\d{3})/.test(v)) v = v.replace(/(\d+)(\d{3})/, '$1.$2');
        return v;
    }

    function isiMetric(channelId) {
        var sel = document.getElementById('chMetric');
        sel.innerHTML = '';
        (CHANNEL_METRICS[channelId] || []).forEach(function(m) {
            var o = document.createElement('option');
            o.value = m.id;
            o.textContent = m.name + (m.is_kpi ? ' (KPI)' : '');
            sel.appendChild(o);
        });
        prefillTarget();
    }

    function prefillTarget() {
        var ch = parseInt(document.getElementById('chChannel').value, 10);
        var mt = parseInt(document.getElementById('chMetric').value, 10);
        var found = (CHANNEL_METRICS[ch] || []).find(function(m) { return m.id === mt; });
        var t = document.getElementById('chTarget');
        if (found && found.target_growth !== null) t.value = found.target_growth;
    }

    document.getElementById('chChannel').addEventListener('change', isiMetric);
    document.getElementById('chMetric').addEventListener('change', prefillTarget);
    document.getElementById('chActual').addEventListener('input', function() {
        var raw = this.value.replace(/\D/g, '');
        this.value = raw !== '' ? fmtRibuan(raw) : '';
    });
    var f = document.querySelector('form[action="<?= base_url('konten/channel/simpan') ?>"]');
    f.addEventListener('submit', function() {
        document.getElementById('chActual').value = document.getElementById('chActual').value.replace(/\./g, '');
    });
    (function() {
        var ch = document.getElementById('chChannel');
        if (ch.options.length) isiMetric(ch.value);
    })();
</script>

<script>
    if (window.ApexCharts) {
        var chartChannelBarEl = document.querySelector('#chartChannelBar');
        if (chartChannelBarEl) {
            var chRows = <?= json_encode(array_map(
                fn($r) => [
                    'name'   => $r['channel_name'] . ' · ' . $r['metric_name'],
                    'growth' => $r['growth'] === null ? null : round($r['growth'], 2),
                    'ach'    => $r['achievement'] === null ? null : round($r['achievement'], 2),
                ],
                $rows
            )) ?>;
            new ApexCharts(chartChannelBarEl, {
                chart: { type: 'bar', fontFamily: 'inherit', toolbar: { show: false }, height: 300 },
                series: [
                    { name: 'Growth %', data: chRows.map(function(r) { return r.growth; }) },
                    { name: 'Achievement %', data: chRows.map(function(r) { return r.ach; }) },
                ],
                xaxis: { categories: chRows.map(function(r) { return r.name; }) },
                plotOptions: { bar: { horizontal: true, columnWidth: '55%', borderRadius: 3 } },
                colors: ['#0d6efd', '#198754'],
                stroke: { width: 0 },
                legend: { position: 'top' },
            }).render();
        }
    }
</script>