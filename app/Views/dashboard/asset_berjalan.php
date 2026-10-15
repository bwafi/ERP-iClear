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
$bolehPilihUnit = (bool) ($boleh_pilih_unit ?? in_array($id_jabatan, [1, 0, 2, 34, 40], true));

// Kalkulasi nilai metrik
$tanggunganAsset = 0;
if ($selected_unit == 4) {
    $tanggunganAsset = (($omset_bulan - $pengeluaran - $totalGajiUnit) * 20 / 100);
} elseif (in_array($selected_unit, [1, 2])) {
    $tanggunganAsset = $hariDalamBulan * 355000;
}

$hakCabang = 0;
$tagihanCenter = 0;
if (in_array($selected_unit, [3, 4, 5])) {
    $profitNett = $omset_bulan - $pengeluaran - $totalGajiUnit;
    $hakCabang = 0.60 * $profitNett;
    $tagihanCenter = 0.40 * $profitNett;
}
?>

<style>
    .metric-figure {
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
    }

    .card-accent-primary {
        border-top: 3px solid var(--bs-primary) !important;
    }

    .card-accent-success {
        border-top: 3px solid var(--bs-success) !important;
    }

    .card-accent-warning {
        border-top: 3px solid var(--bs-warning) !important;
    }

    .card-accent-danger {
        border-top: 3px solid var(--bs-danger) !important;
    }

    .card-accent-info {
        border-top: 3px solid var(--bs-info) !important;
    }
</style>

<!-- Top Toolbar: Header & Control Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <!-- Title -->
            <div>
                <h4 class="fw-bold mb-1 text-dark">Asset Berjalan</h4>
                <p class="fs-3 text-muted mb-0">Ringkasan performa finansial dan distribusi hasil operasional cabang</p>
            </div>

            <!-- Global Controls -->
            <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- Navigation Month -->
                <div class="btn-group shadow-sm">
                    <a href="<?= base_url('asset_berjalan?unit=' . urlencode($selected_unit) . '&bulan=' . $bulanSebelum . '&tahun=' . $tahunSebelum) ?>"
                        class="btn btn-white border text-dark py-2 px-3" title="Bulan Sebelumnya">
                        <iconify-icon icon="solar:alt-arrow-left-bold" class="align-middle fs-5"></iconify-icon>
                    </a>

                    <div class="btn btn-white border px-3 py-2 fw-bold text-primary bg-light-subtle d-flex align-items-center gap-2 pointer-events-none">
                        <iconify-icon icon="solar:calendar-mark-bold-duotone" class="fs-5"></iconify-icon>
                        <span><?= $namaBulan[$bulan] . ' ' . $tahun ?></span>
                    </div>

                    <?php if (!$isBulanBerjalan): ?>
                        <?php
                        $bulanSesudah = (int)date('m', mktime(0, 0, 0, $bulan + 1, 1, $tahun));
                        $tahunSesudah = (int)date('Y', mktime(0, 0, 0, $bulan + 1, 1, $tahun));
                        ?>
                        <a href="<?= base_url('asset_berjalan?unit=' . urlencode($selected_unit) . '&bulan=' . $bulanSesudah . '&tahun=' . $tahunSesudah) ?>"
                            class="btn btn-white border text-dark py-2 px-3" title="Bulan Berikutnya">
                            <iconify-icon icon="solar:alt-arrow-right-bold" class="align-middle fs-5"></iconify-icon>
                        </a>
                    <?php else: ?>
                        <button class="btn btn-white border text-muted py-2 px-3 opacity-50" disabled>
                            <iconify-icon icon="solar:alt-arrow-right-bold" class="align-middle fs-5"></iconify-icon>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Form Filter -->
                <form method="GET" class="d-flex align-items-center gap-2 ms-lg-2">
                    <?php if ($bolehPilihUnit): ?>
                        <select name="unit" class="form-select border-secondary-subtle fw-medium" onchange="this.form.submit()">
                            <?php foreach ($list_unit as $u): ?>
                                <option value="<?= $u['idunit'] ?>" <?= $selected_unit == $u['idunit'] ? 'selected' : '' ?>>
                                    <?= $u['NAMA_UNIT'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>

                    <select name="bulan" class="form-select border-secondary-subtle fw-medium" onchange="this.form.submit()">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>><?= $namaBulan[$i] ?></option>
                        <?php endfor; ?>
                    </select>

                    <select name="tahun" class="form-select border-secondary-subtle fw-medium" onchange="this.form.submit()">
                        <?php for ($i = (int) date('Y'); $i >= (int) date('Y') - 3; $i--): ?>
                            <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Financial Metrics Grid -->
<div class="row g-3 mb-4">
    <!-- Omset Bulan Ini -->
    <div class="col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100 card-accent-success">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-semibold fs-3">Omset Bulan Ini</span>
                    <span class="badge bg-success-subtle text-success p-2 rounded-circle">
                        <iconify-icon icon="solar:graph-up-bold" class="fs-5 d-block"></iconify-icon>
                    </span>
                </div>
                <h3 class="fw-bold text-dark metric-figure mb-1">Rp <?= number_format($omset_bulan ?? 0, 0, ',', '.') ?></h3>
                <span class="fs-2 text-muted">Total bruto <?= $namaBulan[$bulan] ?></span>
            </div>
        </div>
    </div>

    <!-- Pengeluaran Bulan Ini -->
    <div class="col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100 card-accent-warning">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-semibold fs-3">Pengeluaran Operasional</span>
                    <span class="badge bg-warning-subtle text-warning p-2 rounded-circle">
                        <iconify-icon icon="solar:card-send-bold" class="fs-5 d-block"></iconify-icon>
                    </span>
                </div>
                <h3 class="fw-bold text-dark metric-figure mb-1">Rp <?= number_format($pengeluaran ?? 0, 0, ',', '.') ?></h3>
                <span class="fs-2 text-muted">Biaya operasional unit</span>
            </div>
        </div>
    </div>

    <!-- Total Gaji Unit -->
    <div class="col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100 card-accent-danger">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-semibold fs-3">Total Gaji Unit</span>
                    <span class="badge bg-danger-subtle text-danger p-2 rounded-circle">
                        <iconify-icon icon="solar:users-group-two-rounded-bold" class="fs-5 d-block"></iconify-icon>
                    </span>
                </div>
                <h3 class="fw-bold text-dark metric-figure mb-1">Rp <?= number_format($totalGajiUnit ?? 0, 0, ',', '.') ?></h3>
                <span class="fs-2 text-muted">Beban gaji cabang</span>
            </div>
        </div>
    </div>

    <!-- Tanggungan Asset -->
    <div class="col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100 card-accent-primary">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-semibold fs-3">Tanggungan Asset</span>
                    <span class="badge bg-primary-subtle text-primary p-2 rounded-circle">
                        <iconify-icon icon="solar:vault-bold" class="fs-5 d-block"></iconify-icon>
                    </span>
                </div>
                <h3 class="fw-bold text-primary metric-figure mb-1">Rp <?= number_format($tanggunganAsset, 0, ',', '.') ?></h3>
                <span class="fs-2 text-muted">Alokasi tanggungan berjalan</span>
            </div>
        </div>
    </div>

    <!-- Hak Cabang -->
    <div class="col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100 card-accent-info">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-semibold fs-3">Hak Cabang (60%)</span>
                    <span class="badge bg-info-subtle text-info p-2 rounded-circle">
                        <iconify-icon icon="solar:shop-2-bold" class="fs-5 d-block"></iconify-icon>
                    </span>
                </div>
                <h3 class="fw-bold text-dark metric-figure mb-1">Rp <?= number_format($hakCabang, 0, ',', '.') ?></h3>
                <span class="fs-2 text-muted">Bagian untuk pengelola cabang</span>
            </div>
        </div>
    </div>

    <!-- Tagihan Center -->
    <div class="col-sm-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100 card-accent-primary">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fw-semibold fs-3">Tagihan Center (40%)</span>
                    <span class="badge bg-primary-subtle text-primary p-2 rounded-circle">
                        <iconify-icon icon="solar:buildings-3-bold" class="fs-5 d-block"></iconify-icon>
                    </span>
                </div>
                <h3 class="fw-bold text-primary metric-figure mb-1">Rp <?= number_format($tagihanCenter, 0, ',', '.') ?></h3>
                <span class="fs-2 text-muted">Kewajiban penyetoran HO</span>
            </div>
        </div>
    </div>
</div>

<!-- Head Office (HO) Salary Distribution Panel -->
<?php if (!empty($ho_distribution) && $ho_distribution['divisor'] > 0): ?>
    <div class="card border-0 shadow-sm mb-4 overflow-hidden">
        <!-- Panel Header -->
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">Pembagian Gaji Head Office (HO)</h5>
                <span class="fs-2 text-muted">
                    Aturan Pembagi: <strong><?= $ho_distribution['divisor'] ?> bagian</strong>
                    (Pribadi: <?= $ho_distribution['pribadi_count'] ?>, Franchise: <?= $ho_distribution['franchise_count'] ?>)
                </span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark border px-3 py-2 fw-medium">
                    Perkiraan: <strong class="text-primary ms-1">Rp <?= number_format($ho_distribution['total_perkiraan'] ?? 0, 0, ',', '.') ?></strong>
                </span>
                <span class="badge bg-light text-dark border px-3 py-2 fw-medium">
                    Dipotong: <strong class="text-success ms-1">Rp <?= number_format($ho_distribution['total_dipotong'] ?? 0, 0, ',', '.') ?></strong>
                </span>
            </div>
        </div>

        <div class="card-body p-4">
            <!-- Metric Summary Cards -->
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3 bg-light-subtle border">
                        <span class="fs-2 text-muted fw-semibold d-block mb-1">Per Unit (Perkiraan)</span>
                        <h5 class="fw-bold text-primary metric-figure mb-0">Rp <?= number_format($ho_distribution['per_employee_share'] ?? 0, 0, ',', '.') ?></h5>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3 bg-light-subtle border">
                        <span class="fs-2 text-muted fw-semibold d-block mb-1">Per Unit (Dipotong)</span>
                        <h5 class="fw-bold text-success metric-figure mb-0">Rp <?= number_format($ho_distribution['per_employee_actual'] ?? 0, 0, ',', '.') ?></h5>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <?php $selisihTotal = ($ho_distribution['per_employee_share'] ?? 0) - ($ho_distribution['per_employee_actual'] ?? 0); ?>
                    <div class="p-3 rounded-3 bg-light-subtle border">
                        <span class="fs-2 text-muted fw-semibold d-block mb-1">Selisih Alokasi</span>
                        <h5 class="fw-bold text-<?= $selisihTotal >= 0 ? 'warning' : 'danger' ?> metric-figure mb-0">
                            Rp <?= number_format(abs($selisihTotal), 0, ',', '.') ?>
                        </h5>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3 bg-light-subtle border">
                        <span class="fs-2 text-muted fw-semibold d-block mb-1">Total Karyawan HO</span>
                        <h5 class="fw-bold text-dark metric-figure mb-0"><?= count($ho_distribution['employees'] ?? []) ?> <span class="fs-2 font-normal text-muted">Orang</span></h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
