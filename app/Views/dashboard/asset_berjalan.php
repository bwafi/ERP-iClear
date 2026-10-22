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

// Ditentukan controller (resolveUnitScope) agar konsisten dengan otorisasi
// ?unit= di TutupKasir — termasuk Direktur (2) dengan batasan unit per akun.
$bolehPilihUnit = (bool) ($boleh_pilih_unit ?? in_array($id_jabatan, [1, 0, 2, 34, 40], true));

$bulan = (int) ($bulan ?? date('m'));
$tahun = (int) ($tahun ?? date('Y'));
$periodeLabelId = ($namaBulan[$bulan] ?? '-') . ' ' . $tahun;
$labelSebelum   = ($namaBulan[$bulanSebelum] ?? '-') . ' ' . $tahunSebelum;

// Nilai opsional dari controller (dijaga null agar view tetap aman diuji
// maupun dirender tanpa parameter tren).
$pertumbuhanOmset = $pertumbuhan_omset ?? null;

// ---------------------------------------------------------------
// Kalkulasi nilai metrik (formula bisnis — jangan diubah).
// ---------------------------------------------------------------
$tanggunganAsset = 0;
if ($selected_unit == 4) {
    $tanggunganAsset = (($omset_bulan - $pengeluaran - $totalGajiUnit) * 20 / 100);
} elseif (in_array($selected_unit, [1, 2])) {
    $tanggunganAsset = $hariDalamBulan * 355000;
}

$profitNett = $omset_bulan - $pengeluaran - $totalGajiUnit;

$hakCabang = 0;
$tagihanCenter = 0;
if (in_array($selected_unit, [3, 4, 5])) {
    $hakCabang = 0.60 * $profitNett;
    $tagihanCenter = 0.40 * $profitNett;
}
?>

<style>
    .metric-figure {
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
    }
</style>

<!-- Page Header & Breadcrumb -->
<div class="card shadow-none position-relative overflow-hidden mb-4 bg-light-subtle border">
    <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Asset Berjalan</h4>
            <p class="fs-3 text-muted mb-0">Laporan asset berjalan dan pembagian hasil operasional per cabang</p>
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

<!-- Period Navigation Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3 px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <a href="<?= base_url('asset_berjalan?unit=' . urlencode($selected_unit) . '&bulan=' . $bulanSebelum . '&tahun=' . $tahunSebelum) ?>"
                class="btn btn-outline-light text-dark border d-inline-flex align-items-center gap-2 py-2 px-3 shadow-sm">
                <iconify-icon icon="solar:arrow-left-bold-duotone" class="text-primary fs-5"></iconify-icon>
                <span class="fw-medium d-none d-md-inline"><?= esc($labelSebelum) ?></span>
                <span class="fw-medium d-md-none">Sebelumnya</span>
            </a>

            <div class="d-flex align-items-center gap-2 px-3 py-2 border rounded-pill bg-primary-subtle bg-opacity-10 border-primary-subtle">
                <iconify-icon icon="solar:calendar-bold-duotone" class="text-primary fs-5"></iconify-icon>
                <span class="fs-4 fw-bold text-primary"><?= esc($periodeLabelId) ?></span>
            </div>

            <?php if (!$isBulanBerjalan): ?>
                <?php $bulanSesudah = (int) date('m', mktime(0, 0, 0, $bulan + 1, 1, $tahun)); ?>
                <?php $tahunSesudah = (int) date('Y', mktime(0, 0, 0, $bulan + 1, 1, $tahun)); ?>
                <a href="<?= base_url('asset_berjalan?unit=' . urlencode($selected_unit) . '&bulan=' . $bulanSesudah . '&tahun=' . $tahunSesudah) ?>"
                    class="btn btn-outline-light text-dark border d-inline-flex align-items-center gap-2 py-2 px-3 shadow-sm">
                    <span class="fw-medium d-none d-md-inline"><?= esc(($namaBulan[$bulanSesudah] ?? '-') . ' ' . $tahunSesudah) ?></span>
                    <span class="fw-medium d-md-none">Berikutnya</span>
                    <iconify-icon icon="solar:arrow-right-bold-duotone" class="text-primary fs-5"></iconify-icon>
                </a>
            <?php else: ?>
                <span class="btn btn-light text-muted border disabled d-inline-flex align-items-center gap-2 py-2 px-3 opacity-50" title="Bulan berikutnya belum tersedia">
                    <span class="fw-medium d-none d-md-inline">Berikutnya</span>
                    <iconify-icon icon="solar:arrow-right-bold-duotone" class="fs-5"></iconify-icon>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filter Toolbar (For Authorized Roles) -->
<?php if ($bolehPilihUnit): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row align-items-end g-3">
                <div class="col-md-5">
                    <label class="form-label fw-semibold fs-3 text-dark">Pilih Unit Cabang</label>
                    <select name="unit" class="form-select bg-white" onchange="this.form.submit()">
                        <?php foreach ($list_unit as $u): ?>
                            <option value="<?= $u['idunit'] ?>" <?= $selected_unit == $u['idunit'] ? 'selected' : '' ?>>
                                <?= esc($u['NAMA_UNIT']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-3 text-dark">Bulan Pelaporan</label>
                    <select name="bulan" class="form-select bg-white" onchange="this.form.submit()">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>><?= esc($namaBulan[$i]) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-3 text-dark">Tahun</label>
                    <select name="tahun" class="form-select bg-white" onchange="this.form.submit()">
                        <?php for ($i = (int) date('Y'); $i >= (int) date('Y') - 3; $i--): ?>
                            <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Main Metrics Grid -->
<div class="row g-3 mb-4">
    <!-- Omset Bulan Ini -->
    <div class="col-md-6 col-lg-4">
        <div class="card border shadow-none h-100 bg-success-subtle bg-opacity-10 border-success-subtle">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="text-uppercase fs-2 text-success fw-bold d-block mb-1">Omset Bulan Ini</h6>
                        <h3 class="fw-bold mb-0 text-success metric-figure">Rp <?= number_format($omset_bulan ?? 0, 0, ',', '.') ?></h3>
                    </div>
                    <div class="p-3 bg-success-subtle rounded-3 text-success d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:chart-bold-duotone" width="28" height="28"></iconify-icon>
                    </div>
                </div>
                <small class="text-muted">
                    Periode <?= esc($periodeLabelId) ?>
                    <?php if ($pertumbuhanOmset !== null): ?>
                        &middot;
                        <?php if ($pertumbuhanOmset > 0): ?>
                            <span class="text-success fw-semibold"><iconify-icon icon="solar:arrow-up-bold-duotone" class="align-middle"></iconify-icon> +<?= number_format((float) $pertumbuhanOmset, 1, ',', '.') ?>%</span>
                        <?php elseif ($pertumbuhanOmset < 0): ?>
                            <span class="text-danger fw-semibold"><iconify-icon icon="solar:arrow-down-bold-duotone" class="align-middle"></iconify-icon> <?= number_format((float) $pertumbuhanOmset, 1, ',', '.') ?>%</span>
                        <?php else: ?>
                            <span class="text-muted">&plusmn;0%</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </small>
            </div>
        </div>
    </div>

    <!-- Pengeluaran Bulan Ini -->
    <div class="col-md-6 col-lg-4">
        <div class="card border shadow-none h-100 bg-warning-subtle bg-opacity-10 border-warning-subtle">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="text-uppercase fs-2 text-warning-emphasis fw-bold d-block mb-1">Pengeluaran Bulan Ini</h6>
                        <h3 class="fw-bold mb-0 text-warning-emphasis metric-figure">Rp <?= number_format($pengeluaran ?? 0, 0, ',', '.') ?></h3>
                    </div>
                    <div class="p-3 bg-warning-subtle rounded-3 text-warning-emphasis d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:card-send-bold-duotone" width="28" height="28"></iconify-icon>
                    </div>
                </div>
                <small class="text-muted">Biaya operasional terkontrol unit periode ini</small>
                <?php if (($pengeluaran ?? 0) > 0.06 * ($omset_bulan ?? 0)): ?>
                    <small class="text-danger fw-semibold d-block mt-1">
                        <iconify-icon icon="solar:danger-triangle-bold-duotone" class="align-middle"></iconify-icon>
                        Melebihi batas 6% (Rp <?= number_format(0.06 * ($omset_bulan ?? 0), 0, ',', '.') ?>)
                    </small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pengeluaran Full 1 Bulan -->
    <div class="col-md-6 col-lg-4">
        <div class="card border shadow-none h-100 bg-secondary-subtle bg-opacity-10 border-secondary-subtle">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="text-uppercase fs-2 text-secondary-emphasis fw-bold d-block mb-1">Pengeluaran Full 1 Bulan</h6>
                        <h3 class="fw-bold mb-0 text-secondary-emphasis metric-figure">Rp <?= number_format($pengeluaran_full ?? 0, 0, ',', '.') ?></h3>
                    </div>
                    <div class="p-3 bg-secondary-subtle rounded-3 text-secondary-emphasis d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:wallet-money-bold-duotone" width="28" height="28"></iconify-icon>
                    </div>
                </div>
                <small class="text-muted">Seluruh kas keluar unit periode ini (termasuk HPP)</small>
            </div>
        </div>
    </div>

    <!-- Total Gaji -->
    <div class="col-md-6 col-lg-4">
        <div class="card border shadow-none h-100 bg-danger-subtle bg-opacity-10 border-danger-subtle">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="text-uppercase fs-2 text-danger fw-bold d-block mb-1">Total Gaji</h6>
                        <h3 class="fw-bold mb-0 text-danger metric-figure">Rp <?= number_format($totalGajiUnit ?? 0, 0, ',', '.') ?></h3>
                    </div>
                    <div class="p-3 bg-danger-subtle rounded-3 text-danger d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:hand-money-bold-duotone" width="28" height="28"></iconify-icon>
                    </div>
                </div>
                <small class="text-muted">Beban gaji karyawan cabang</small>
                <?php if (isset($gaji_detail)): ?>
                    <button type="button" class="btn btn-sm bg-danger-subtle text-danger fw-semibold mt-2 align-self-start"
                            data-bs-toggle="modal" data-bs-target="#modalRincianGaji">
                        <iconify-icon icon="solar:list-bold-duotone" class="align-middle"></iconify-icon>
                        Lihat rincian
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Total Tanggungan Asset -->
    <div class="col-md-6 col-lg-4">
        <div class="card border shadow-none h-100 bg-primary-subtle bg-opacity-10 border-primary-subtle">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="text-uppercase fs-2 text-primary fw-bold d-block mb-1">Total Tanggungan Asset</h6>
                        <h3 class="fw-bold mb-0 text-primary metric-figure">Rp <?= number_format($tanggunganAsset, 0, ',', '.') ?></h3>
                    </div>
                    <div class="p-3 bg-primary-subtle rounded-3 text-primary d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:vault-bold-duotone" width="28" height="28"></iconify-icon>
                    </div>
                </div>
                <small class="text-muted">Alokasi tanggungan asset berjalan</small>
            </div>
        </div>
    </div>

    <!-- Hak Cabang -->
    <div class="col-md-6 col-lg-4">
        <div class="card border shadow-none h-100 bg-info-subtle bg-opacity-10 border-info-subtle">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="text-uppercase fs-2 text-info-emphasis fw-bold d-block mb-1">Hak Cabang</h6>
                        <h3 class="fw-bold mb-0 text-info-emphasis metric-figure">Rp <?= number_format($hakCabang, 0, ',', '.') ?></h3>
                    </div>
                    <div class="p-3 bg-info-subtle rounded-3 text-info-emphasis d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:shop-2-bold-duotone" width="28" height="28"></iconify-icon>
                    </div>
                </div>
                <small class="text-muted">Bagian 60% untuk pengelola cabang</small>
            </div>
        </div>
    </div>

    <!-- Tagihan Center -->
    <div class="col-md-6 col-lg-4">
        <div class="card border shadow-none h-100 bg-primary-subtle bg-opacity-10 border-primary-subtle">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="text-uppercase fs-2 text-primary fw-bold d-block mb-1">Tagihan Center</h6>
                        <h3 class="fw-bold mb-0 text-primary metric-figure">Rp <?= number_format($tagihanCenter, 0, ',', '.') ?></h3>
                    </div>
                    <div class="p-3 bg-primary-subtle rounded-3 text-primary d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:buildings-3-bold-duotone" width="28" height="28"></iconify-icon>
                    </div>
                </div>
                <small class="text-muted">Kewajiban penyetoran 40% ke Head Office</small>
            </div>
        </div>
    </div>
</div>

<!-- Formula & Context Note -->
<div class="alert alert-light border mb-4 d-flex align-items-start gap-2 py-3 px-3 text-muted fs-2">
    <iconify-icon icon="solar:info-circle-bold-duotone" class="fs-5 text-primary flex-shrink-0"></iconify-icon>
    <span>
        <strong class="text-dark">Total Tanggungan Asset</strong> — unit 4 = 20% laba; unit 1 &amp; 2 = jumlah hari bulan ini &times; Rp 355.000; unit franchise (3 &amp; 5) = Rp 0.
        <strong class="text-dark">Hak Cabang (60%)</strong> dan <strong class="text-dark">Tagihan Center (40%)</strong> dihitung dari laba bersih (Omset &minus; Pengeluaran &minus; Total Gaji) unit 3, 4, &amp; 5.
        <br>
        <strong class="text-dark">Batas 6%</strong> hanya peringatan — transaksi tetap tercatat dan tidak dibatalkan.
    </span>
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
            <div class="d-flex align-items-center gap-2 flex-wrap">
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
                <div class="col-6 col-md-4">
                    <div class="p-3 rounded-3 bg-light-subtle border">
                        <span class="fs-2 text-muted fw-semibold d-block mb-1">Per Unit (Perkiraan)</span>
                        <h5 class="fw-bold text-primary metric-figure mb-0">Rp <?= number_format($ho_distribution['per_employee_share'] ?? 0, 0, ',', '.') ?></h5>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="p-3 rounded-3 bg-light-subtle border">
                        <span class="fs-2 text-muted fw-semibold d-block mb-1">Per Unit (Dipotong)</span>
                        <h5 class="fw-bold text-success metric-figure mb-0">Rp <?= number_format($ho_distribution['per_employee_actual'] ?? 0, 0, ',', '.') ?></h5>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="p-3 rounded-3 bg-light-subtle border">
                        <span class="fs-2 text-muted fw-semibold d-block mb-1">Total Karyawan HO</span>
                        <h5 class="fw-bold text-dark metric-figure mb-0"><?= count($ho_distribution['employees'] ?? []) ?> <span class="fs-2 fw-normal text-muted">Orang</span></h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($gaji_detail)): ?>
<!-- Rincian Total Gaji: hanya dikirim controller untuk Admin root (1), Finance (0), Manager (34) -->
<div class="modal fade" id="modalRincianGaji" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rincian Total Gaji</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <?php if ($gaji_detail === []): ?>
                    <p class="text-muted mb-0">Tidak ada data.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama</th>
                                    <th>Jabatan</th>
                                    <th class="text-end">Gaji Pokok</th>
                                    <th class="text-end">T. Kinerja</th>
                                    <th class="text-end">T. Absen</th>
                                    <th class="text-end">T. Penempatan</th>
                                    <th class="text-end">Insentif</th>
                                    <th class="text-end">Lembur</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gaji_detail as $d): ?>
                                    <tr>
                                        <td><?= esc($d['nama']) ?></td>
                                        <td><?= esc($d['jabatan']) ?></td>
                                        <td class="text-end"><?= number_format($d['gaji_pokok'], 0, ',', '.') ?></td>
                                        <td class="text-end"><?= number_format($d['tunjangan_kinerja'], 0, ',', '.') ?></td>
                                        <td class="text-end"><?= number_format($d['tunjangan_absen'], 0, ',', '.') ?></td>
                                        <td class="text-end"><?= number_format($d['tunjangan_penempatan'], 0, ',', '.') ?></td>
                                        <td class="text-end"><?= number_format($d['insentif'], 0, ',', '.') ?></td>
                                        <td class="text-end"><?= number_format($d['lembur'] ?? 0, 0, ',', '.') ?></td>
                                        <td class="text-end fw-semibold"><?= number_format($d['total'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold table-light">
                                    <td colspan="8" class="text-end">Total</td>
                                    <td class="text-end rincian-gaji-total">Rp <?= number_format($totalGajiUnit ?? 0, 0, ',', '.') ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
