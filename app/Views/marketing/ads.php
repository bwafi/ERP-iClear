<div class="card shadow-none position-relative overflow-hidden mb-4"
    style="background: linear-gradient(120deg, #0f2b46 0%, #1d4e89 60%, #2a6dbb 100%);">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div class="text-white">
            <h4 class="fw-semibold mb-1 text-white">Biaya Iklan (Ads Cost)</h4>
            <small class="text-white-50">Biaya iklan per periode + channel/campaign. Dipakai menghitung CPL &amp; ROAS — hanya lead berbayar (ADS) yang dimasukkan ke CPL.</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('marketing') ?>">Marketing KPI</a></li>
                <li class="breadcrumb-item active text-white">Biaya Iklan</li>
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
                <a href="<?= base_url('marketing') ?>" class="btn btn-light">
                    <iconify-icon icon="solar:chart-2-bold" class="me-1"></iconify-icon>Dashboard Digital Marketing
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($canWrite) : ?>
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <iconify-icon icon="solar:wallet-bold" class="text-primary me-1"></iconify-icon> Tambah Biaya Iklan
        </h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?= base_url('marketing/ads/simpan') ?>" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label mb-1 small text-muted">Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1 small text-muted">Bulan</label>
                <select name="period_month" class="form-select" id="adsPeriodMonth">
                    <?php for ($i = 1; $i <= 12; $i++) : ?>
                        <option value="<?= $i ?>" <?= $i === $bulan ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1 small text-muted">Tahun</label>
                <input type="number" name="period_year" id="adsPeriodYear" class="form-control" value="<?= $tahun ?>" min="2000" max="2100" required>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small text-muted">Channel</label>
                <select name="channel_id" class="form-select">
                    <option value="">Semua / Umum</option>
                    <?php foreach ($channels as $ch) : ?>
                        <option value="<?= $ch->id ?>"><?= esc($ch->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small text-muted">Campaign</label>
                <input type="text" name="campaign" class="form-control" placeholder="mis. Promo Ramadhan">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small text-muted">Biaya (Rp)</label>
                <input type="text" name="amount" class="form-control angka-ribuan" inputmode="numeric" placeholder="500.000" required>
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1 small text-muted">Catatan</label>
                <input type="text" name="note" class="form-control" placeholder="opsional">
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

<?php if (!empty($adsByChannel)) : ?>
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white">
        <h5 class="mb-0"><iconify-icon icon="solar:chart-bold" class="text-primary me-1"></iconify-icon>Biaya Iklan per Channel</h5>
        <small class="text-muted"><?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></small>
    </div>
    <div class="card-body">
        <div id="chartAdsByChannel"></div>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Data Biaya Iklan</h5>
            <small class="text-muted"><?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></small>
        </div>
    </div>
    <div class="card-body">
        <?php $channelName = []; ?>
        <?php foreach ($channels as $ch) : ?>
            <?php $channelName[(int)$ch->id] = $ch->name; ?>
        <?php endforeach; ?>
        <?php if (empty($rows)) : ?>
            <div class="text-center py-5">
                <iconify-icon icon="solar:wallet-line-outline" class="text-muted fs-1"></iconify-icon>
                <p class="text-muted mt-2 mb-0">Belum ada biaya iklan untuk periode ini.</p>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Periode</th>
                            <th>Channel</th>
                            <th>Campaign</th>
                            <th class="text-end">Biaya</th>
                            <th>Catatan</th>
                            <?php if ($canWrite) : ?>
                                <th class="text-end">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $total = 0; ?>
                        <?php foreach ($rows as $row) : $total += (float)$row->amount; ?>
                            <tr>
                                <td class="text-nowrap">
                                    <?php if ($row->tanggal) : ?>
                                        <span class="badge rounded-pill bg-light text-dark border"><?= date('d M Y', strtotime($row->tanggal)) ?></span>
                                        <div class="small text-muted mt-1"><?= str_pad($row->period_month, 2, '0', STR_PAD_LEFT) ?>/<?= $row->period_year ?></div>
                                    <?php else : ?>
                                        <span class="badge rounded-pill bg-light text-dark border"><?= date('Y-m', mktime(0, 0, 0, $row->period_month, 1, $row->period_year)) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($channelName[(int)$row->channel_id])) : ?>
                                        <span class="badge rounded-pill text-bg-primary">
                                            <iconify-icon icon="solar:instagram-line-bold" class="me-1"></iconify-icon><?= esc($channelName[(int)$row->channel_id]) ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="badge rounded-pill text-bg-secondary">Umum</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold"><?= esc($row->campaign) ?></td>
                                <td class="text-end fw-semibold">Rp <?= number_format($row->amount, 0, ',', '.') ?></td>
                                <td><small class="text-muted"><?= esc($row->note) ?></small></td>
                                <?php if ($canWrite) : ?>
                                    <td class="text-end">
                                        <form method="post" action="<?= base_url('marketing/ads/hapus') ?>" class="m-0" onsubmit="return confirm('Hapus biaya iklan ini?');">
                                            <input type="hidden" name="id" value="<?= $row->id ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i> Hapus
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <th colspan="3" class="text-end">Total Biaya Iklan</th>
                            <th class="text-end fw-bold text-primary">Rp <?= number_format($total, 0, ',', '.') ?></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function fmtRibuan(v) {
        while (/(\d+)(\d{3})/.test(v)) v = v.replace(/(\d+)(\d{3})/, '$1.$2');
        return v;
    }
    var amt = document.querySelector('input[name="amount"]');
    if (amt) {
        amt.addEventListener('input', function() {
            var raw = this.value.replace(/\D/g, '');
            this.value = raw !== '' ? fmtRibuan(raw) : '';
        });
        amt.form.addEventListener('submit', function() {
            amt.value = amt.value.replace(/\./g, '');
        });
    }

    // Saat tanggal dipilih, bulan & tahun mengikuti tanggal.
    var tglInput = document.querySelector('input[name="tanggal"]');
    if (tglInput) {
        var setPeriod = function() {
            var t = tglInput.value;
            if (!t) return;
            var p = t.split('-');
            var mSel = document.getElementById('adsPeriodMonth');
            var yInp = document.getElementById('adsPeriodYear');
            if (mSel) mSel.value = parseInt(p[1], 10).toString();
            if (yInp) yInp.value = p[0];
        };
        tglInput.addEventListener('change', setPeriod);
    }

    // Bar chart biaya iklan per channel
    var adsByChannelEl = document.querySelector('#chartAdsByChannel');
    if (adsByChannelEl && window.ApexCharts) {
        new ApexCharts(adsByChannelEl, {
            chart: { type: 'bar', height: 280, fontFamily: 'inherit', toolbar: { show: false } },
            series: [{ name: 'Biaya (Rp)', data: <?= json_encode(array_map(fn($c) => (float)$c['amount'], $adsByChannel ?? [])) ?> }],
            xaxis: { categories: <?= json_encode(array_map(fn($c) => $c['channel'], $adsByChannel ?? [])) ?> },
            plotOptions: { bar: { columnWidth: '50%', borderRadius: 3 } },
            colors: ['#1d4e89'],
            dataLabels: { enabled: true, formatter: function(v) { return v >= 1000000 ? (v / 1000000).toFixed(1) + ' jt' : v >= 1000 ? (v / 1000).toFixed(0) + ' rb' : v; } },
            legend: { show: true },
            yaxis: { labels: { formatter: function(v) { return v >= 1000000 ? (v / 1000000).toFixed(1) + ' jt' : v >= 1000 ? (v / 1000).toFixed(0) + ' rb' : v; } } },
        }).render();
    }
</script>