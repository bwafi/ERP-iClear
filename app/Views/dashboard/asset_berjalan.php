<?php
$namaBulan = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];
$bolehPilihUnit = in_array($id_jabatan, [1, 0, 34, 40]);
?>

<style>
    .metric-figure {
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.01em;
    }
</style>

<!-- Page Header & Breadcrumb -->
<div class="card shadow-none position-relative overflow-hidden mb-4 bg-light-subtle border">
    <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4">
        <div>
            <h4 class="fw-semibold mb-1 text-dark">Asset Berjalan</h4>
            <p class="fs-3 text-muted mb-0">Laporan asset berjalan dan pembagian hasil per cabang</p>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 bg-transparent p-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                </li>
                <li class="breadcrumb-item text-primary fw-medium" aria-current="page">Asset Berjalan</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Period Ruler Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3 px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">

            <div class="d-flex align-items-center gap-2">
                <a href="<?= base_url('asset_berjalan?unit=' . urlencode($selected_unit) . '&bulan=' . $bulanSebelum . '&tahun=' . $tahunSebelum) ?>"
                    class="btn btn-outline-light text-dark border d-inline-flex align-items-center gap-2 py-2 px-3 shadow-sm">
                    <iconify-icon icon="solar:arrow-left-bold-duotone" class="text-primary"></iconify-icon>
                    <span class="fw-medium d-none d-md-inline"><?= $namaBulan[$bulanSebelum] . ' ' . $tahunSebelum ?></span>
                    <span class="fw-medium d-md-none">Sebelumnya</span>
                </a>

                <div class="d-flex align-items-center gap-2 px-3 py-2 border rounded-pill bg-primary-subtle bg-opacity-10 border-primary-subtle">
                    <iconify-icon icon="solar:calendar-bold-duotone" class="text-primary fs-5"></iconify-icon>
                    <span class="fs-4 fw-bold text-primary"><?= $namaBulan[$bulan] . ' ' . $tahun ?></span>
                </div>

                <?php if (!$isBulanBerjalan): ?>
                    <a href="<?= base_url('asset_berjalan?unit=' . urlencode($selected_unit) . '&bulan=' . $bulanSesudah . '&tahun=' . $tahunSesudah) ?>"
                        class="btn btn-outline-light text-dark border d-inline-flex align-items-center gap-2 py-2 px-3 shadow-sm">
                        <span class="fw-medium d-none d-md-inline"><?= $namaBulan[$bulanSesudah] . ' ' . $tahunSesudah ?></span>
                        <span class="fw-medium d-md-none">Berikutnya</span>
                        <iconify-icon icon="solar:arrow-right-bold-duotone" class="text-primary"></iconify-icon>
                    </a>
                <?php else: ?>
                    <span class="btn btn-light text-muted border disabled d-inline-flex align-items-center gap-2 py-2 px-3 opacity-50" title="Bulan berikutnya belum tersedia">
                        <span class="fw-medium d-none d-md-inline">Berikutnya</span>
                        <iconify-icon icon="solar:arrow-right-bold-duotone"></iconify-icon>
                    </span>
                <?php endif; ?>
            </div>

            <form method="GET" class="d-flex align-items-end flex-wrap gap-2">
                <?php if ($bolehPilihUnit): ?>
                    <div>
                        <label class="form-label fw-semibold fs-3 text-dark mb-1">Unit</label>
                        <select name="unit" class="form-select form-select-sm bg-white" onchange="this.form.submit()">
                            <?php foreach ($list_unit as $u): ?>
                                <option value="<?= $u['idunit'] ?>" <?= $selected_unit == $u['idunit'] ? 'selected' : '' ?>>
                                    <?= $u['NAMA_UNIT'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div>
                    <label class="form-label fw-semibold fs-3 text-dark mb-1">Bulan</label>
                    <select name="bulan" class="form-select form-select-sm bg-white" onchange="this.form.submit()">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>><?= $namaBulan[$i] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label fw-semibold fs-3 text-dark mb-1">Tahun</label>
                    <select name="tahun" class="form-select form-select-sm bg-white" onchange="this.form.submit()">
                        <?php for ($i = (int) date('Y'); $i >= (int) date('Y') - 3; $i--): ?>
                            <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- Metric Report Grid (3 x 2) -->
<div class="row g-4 mb-4">

    <!-- Omset Bulan Ini -->
    <div class="col-md-6 col-xl-4">
        <div class="card border shadow-none h-100 mb-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 fw-semibold text-muted">Omset Bulan Ini</h6>
                        <h3 class="fw-bold mb-1 metric-figure text-dark">Rp <?= number_format($omset_bulan ?? 0, 0, ',', '.') ?></h3>
                        <small class="text-muted">Total omset <?= $namaBulan[$bulan] . ' ' . $tahun ?></small>
                    </div>
                    <div class="p-3 bg-success-subtle rounded-3 text-success d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:chart-bold" width="32" height="32"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pengeluaran Bulan Ini -->
    <div class="col-md-6 col-xl-4">
        <div class="card border shadow-none h-100 mb-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 fw-semibold text-muted">Pengeluaran Bulan Ini</h6>
                        <h3 class="fw-bold mb-1 metric-figure text-dark">Rp <?= number_format($pengeluaran ?? 0, 0, ',', '.') ?></h3>
                        <small class="text-muted">Total pengeluaran <?= $namaBulan[$bulan] . ' ' . $tahun ?></small>
                    </div>
                    <div class="p-3 bg-warning-subtle rounded-3 text-warning d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:cart-3-bold" width="32" height="32"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Tanggungan Asset -->
    <div class="col-md-6 col-xl-4">
        <div class="card border shadow-none h-100 mb-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 fw-semibold text-muted">Total Tanggungan Asset</h6>
                        <h3 class="fw-bold mb-1 metric-figure text-primary">
                            <?php
                            if ($selected_unit == 4) {
                                $total = (($omset_bulan - $pengeluaran - $totalGajiUnit) * 20 / 100);
                            } elseif ($selected_unit == 3) {
                                $total = 0;
                            } elseif (in_array($selected_unit, [1, 2])) {
                                $total = $hariDalamBulan * 355000;
                            }
                            ?>
                            Rp <?= number_format($total ?? 0, 0, ',', '.') ?>
                        </h3>
                        <small class="text-muted">Tanggungan asset berjalan <?= $namaBulan[$bulan] . ' ' . $tahun ?></small>
                    </div>
                    <div class="p-3 bg-primary-subtle rounded-3 text-primary d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:wallet-money-bold" width="32" height="32"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Gaji -->
    <div class="col-md-6 col-xl-4">
        <div class="card border shadow-none h-100 mb-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 fw-semibold text-muted">Total Gaji</h6>
                        <h3 class="fw-bold mb-1 metric-figure text-dark">Rp <?= number_format($totalGajiUnit ?? 0, 0, ',', '.') ?></h3>
                        <small class="text-muted">Total gaji <?= $namaBulan[$bulan] . ' ' . $tahun ?></small>
                    </div>
                    <div class="p-3 bg-danger-subtle rounded-3 text-danger d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:hand-money-bold" width="32" height="32"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hak Cabang -->
    <div class="col-md-6 col-xl-4">
        <div class="card border shadow-none h-100 mb-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 fw-semibold text-muted">Hak Cabang</h6>
                        <h3 class="fw-bold mb-1 metric-figure text-primary">
                            <?php
                            if (in_array($selected_unit, [3, 4])) {
                                $total = 60 * (($omset_bulan - $pengeluaran - $totalGajiUnit)) / 100;
                            } elseif (in_array($selected_unit, [1, 2])) {
                                $total = 0;
                            }
                            ?>
                            Rp <?= number_format($total ?? 0, 0, ',', '.') ?>
                        </h3>
                        <small class="text-muted">Total hak cabang <?= $namaBulan[$bulan] . ' ' . $tahun ?></small>
                    </div>
                    <div class="p-3 bg-primary-subtle rounded-3 text-primary d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:shop-bold" width="32" height="32"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tagihan Center -->
    <div class="col-md-6 col-xl-4">
        <div class="card border shadow-none h-100 mb-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-2 fw-semibold text-muted">Tagihan Center</h6>
                        <h3 class="fw-bold mb-1 metric-figure text-primary">
                            <?php
                            if (in_array($selected_unit, [3, 4])) {
                                $total = 40 * (($omset_bulan - $pengeluaran - $totalGajiUnit)) / 100;
                            } elseif (in_array($selected_unit, [1, 2])) {
                                $total = 0;
                            }
                            ?>
                            Rp <?= number_format($total ?? 0, 0, ',', '.') ?>
                        </h3>
                        <small class="text-muted">Total tagihan center <?= $namaBulan[$bulan] . ' ' . $tahun ?></small>
                    </div>
                    <div class="p-3 bg-primary-subtle rounded-3 text-primary d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:buildings-bold" width="32" height="32"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>