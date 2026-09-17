<div class="card shadow-none position-relative overflow-hidden mb-4"
    style="background: linear-gradient(120deg, #0f2b46 0%, #1d4e89 60%, #2a6dbb 100%);">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div class="text-white">
            <h4 class="fw-semibold mb-1 text-white">KPI Social Media</h4>
            <small class="text-white-50">Sumber metric: social_media_posts + social_media_metric_snapshots (snapshot terbaru per post, tanpa double counting).</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active text-white">KPI Social Media</li>
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
    <div class="card-body d-flex flex-wrap align-items-end gap-2 justify-content-between">
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
                    <?php for ($t = date('Y') - 2; $t <= date('Y'); $t++) : ?>
                        <option value="<?= $t ?>" <?= $t == $tahun ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label mb-1 small text-muted">Unit</label>
                <select name="unit_id" class="form-select form-select-sm">
                    <option value="0">Semua Unit</option>
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
        <span class="text-muted small align-self-center">
            <i class="bi bi-cloud-arrow-down me-1"></i>Data di-refresh via CLI: <code>php74 spark social:pull</code>
        </span>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php
    $labels = ['views' => 'Views', 'plays' => 'Plays', 'likes' => 'Likes', 'comments' => 'Comments', 'shares' => 'Shares', 'saves' => 'Saves'];
    foreach ($metrics as $metric) : ?>
        <div class="col-6 col-md-2">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase"><?= $labels[$metric] ?></div>
                    <div class="fs-5 fw-semibold"><?= number_format((float)$totals[$metric], 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="col-6 col-md-2">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Jumlah Post</div>
                <div class="fs-5 fw-semibold"><?= number_format((int)$totals['posts'], 0, ',', '.') ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Unit</th>
                    <th>Platform</th>
                    <th>Post</th>
                    <?php foreach ($metrics as $metric) : ?>
                        <th class="text-end"><?= ucfirst($labels[$metric]) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($summary)) : ?>
                    <tr><td colspan="8" class="text-center text-muted">Belum ada data postingan pada periode ini. Jalankan scraping untuk mengisi data.</td></tr>
                <?php endif; ?>
                <?php foreach ($summary as $row) :
                    $unitName = '';
                    foreach ($units as $u) {
                        if ((int)$u->idunit === (int)$row['unit_id']) { $unitName = $u->NAMA_UNIT; break; }
                    }
                    $first = true;
                ?>
                    <tr>
                        <td><?= esc($unitName) ?></td>
                        <td><?= ucfirst(esc($row['platform'])) ?></td>
                        <td><?= number_format($row['posts'], 0, ',', '.') ?></td>
                        <?php foreach ($metrics as $metric) :
                            $m = $row['metrics'][$metric] ?? ['actual' => 0, 'target' => 0, 'achievement' => null];
                        ?>
                            <td class="text-end">
                                <div><?= number_format((float)$m['actual'], 0, ',', '.') ?></div>
                                <?php if ($m['achievement'] !== null && $m['target'] > 0) : ?>
                                    <small class="text-muted"><?= number_format((float)$m['achievement'], 0) ?>% dari <?= number_format((float)$m['target'], 0, ',', '.') ?></small>
                                <?php elseif ($m['target'] > 0) : ?>
                                    <small class="text-muted">target <?= number_format((float)$m['target'], 0, ',', '.') ?></small>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
