<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
            <h4 class="fw-semibold mb-0">Detail KPI — <?= esc($target->NAMA_AKUN) ?></h4>
            <small class="text-muted">Periode <?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                </li>
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/penilaian/kpi') ?>">Penilaian KPI</a>
                </li>
                <li class="breadcrumb-item active"><?= esc($target->NAMA_AKUN) ?></li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session()->getFlashdata('success')) : ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= session()->getFlashdata('success') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= session()->getFlashdata('error') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <h5 class="fw-semibold mb-0"><?= esc($target->NAMA_AKUN) ?></h5>
                <span class="badge bg-primary-subtle text-primary mt-2">
                    <?= esc($namaJabatan) ?>
                </span>
                <div class="text-muted small mt-1">
                    Unit <?= $target->ID_UNIT ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-primary-subtle border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-primary">Total Skor KPI</h6>
                <h1 class="fw-bold text-primary mb-0">
                    <?= number_format($kpi['skor_total'], 2, ',', '.') ?>
                </h1>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success-subtle border-0 shadow-sm">
            <div class="card-body text-center">
                <h6 class="text-success">Skor Absensi</h6>
                <h1 class="fw-bold text-success mb-0">
                    <?= number_format($kpi['skor_total2'], 2, ',', '.') ?>
                </h1>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header">
        <h5 class="mb-0">Komponen KPI</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Komponen</th>
                        <th class="text-center">Bobot</th>
                        <th class="text-center">Jenis</th>
                        <th class="text-center">Nilai</th>
                        <th class="text-center">Input Manual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($kpi['detail_kpi'] ?? []) as $item) : ?>
                        <?php
                        $isManual  = isset($manualNameSet[$item['nama']]);
                        $badge     = $item['nilai'] >= 90 ? 'success' : ($item['nilai'] >= 75 ? 'warning' : 'danger');
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($item['nama']) ?></td>
                            <td class="text-center"><?= $item['bobot'] ?>%</td>
                            <td class="text-center">
                                <?php if ($isManual) : ?>
                                    <span class="badge bg-info-subtle text-info">Manual</span>
                                <?php else : ?>
                                    <span class="badge bg-secondary-subtle text-secondary">Otomatis</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= $badge ?>"><?= $item['nilai'] ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($isManual && $canEvaluate) : ?>
                                    <a href="#manualGridSection" class="btn btn-sm btn-outline-warning">
                                        Input Harian
                                    </a>
                                <?php elseif ($isManual) : ?>
                                    <span class="text-muted small">Tidak berwenang</span>
                                <?php else : ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="manualGridSection" class="mt-4">
            <?= view('penilaian/_manual_grid', [
                'pegawai'        => $target,
                'jabatanRingkas' => $namaJabatan,
                'manualGrid'     => $manualGrid ?? [],
                'bulan'          => $bulan,
                'tahun'          => $tahun,
                'jumlahHari'     => (int)date('t', strtotime("$tahun-$bulan-01")),
                'saveUrl'        => 'penilaian/kpi/save_daily',
                'editable'       => $canEvaluate,
            ]) ?>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mt-3">
    <div class="card-header">
        <h5 class="mb-0">Detail Absensi</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Kriteria</th>
                        <th class="text-center">Bobot</th>
                        <th class="text-center">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($kpi['detail_absen'] ?? []) as $a) : ?>
                        <tr>
                            <td><?= esc($a['nama']) ?></td>
                            <td class="text-center"><?= $a['bobot'] ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $a['nilai'] >= 90 ? 'success' : ($a['nilai'] >= 75 ? 'warning' : 'danger') ?>">
                                    <?= $a['nilai'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
