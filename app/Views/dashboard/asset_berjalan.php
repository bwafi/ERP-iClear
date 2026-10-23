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

$namaUnitAktif = '';
foreach (($list_unit ?? []) as $u) {
    if ((int) ($u['idunit'] ?? 0) === (int) $selected_unit) {
        $namaUnitAktif = (string) ($u['NAMA_UNIT'] ?? '');
        break;
    }
}

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

// Rasio realisasi biaya terkontrol terhadap batas 6% omset. Batas 0 (belum
// ada omset) dianggap 100% kalau sudah ada biaya, supaya statusnya jujur.
$batasBiayaTerkontrol = 0.06 * ($omset_bulan ?? 0);
$biayaTerkontrol = $pengeluaran ?? 0;
$rasioBiayaTerkontrol = $batasBiayaTerkontrol > 0
    ? ($biayaTerkontrol / $batasBiayaTerkontrol) * 100
    : ($biayaTerkontrol > 0 ? 100 : 0);
$statusBiayaTerkontrol = $rasioBiayaTerkontrol > 100
    ? 'Melebihi batas 6%'
    : ($rasioBiayaTerkontrol >= 80 ? 'Hampir mencapai batas' : 'Aman');
$warnaBiayaTerkontrol = $rasioBiayaTerkontrol > 100
    ? 'danger'
    : ($rasioBiayaTerkontrol >= 80 ? 'warning' : 'success');
?>

<style>
    .metric-figure {
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.02em;
    }

    /* Detail pengeluaran: hero, ringkasan, dan ledger yang lebih rapi. */
    #modalDetailPengeluaran .detail-hero {
        background: var(--bs-warning-bg-subtle);
        border-bottom: 1px solid var(--bs-warning-border-subtle);
        padding: 1rem 1.25rem;
    }
    #modalDetailPengeluaran[data-warna="secondary"] .detail-hero {
        background: var(--bs-secondary-bg-subtle);
        border-bottom-color: var(--bs-secondary-border-subtle);
    }
    #modalDetailPengeluaran .detail-hero-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: var(--bs-warning);
        color: #fff;
    }
    #modalDetailPengeluaran[data-warna="secondary"] .detail-hero-icon {
        background: var(--bs-secondary);
    }
    #modalDetailPengeluaran .detail-summary {
        display: grid;
        grid-template-columns: 1.2fr 1fr 1fr;
        border-bottom: 1px solid var(--bs-border-color);
    }
    #modalDetailPengeluaran .detail-summary > div {
        padding: .85rem 1rem;
        min-width: 0;
    }
    #modalDetailPengeluaran .detail-summary > div + div {
        border-left: 1px solid var(--bs-border-color);
    }
    #modalDetailPengeluaran .modal-body {
        padding-top: 0;
    }
    #modalDetailPengeluaran .detail-summary-label,
    #modalDetailPengeluaran .detail-section-label {
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--bs-secondary-color);
    }
    #modalDetailPengeluaran .detail-summary-value {
        font-size: clamp(.95rem, 2.2vw, 1.25rem);
        font-weight: 700;
        line-height: 1.25;
        white-space: nowrap;
    }
    #modalDetailPengeluaran .detail-chip {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .32rem .65rem;
        border: 1px solid var(--bs-border-color);
        border-radius: 999px;
        background: var(--bs-body-bg);
        font-size: .78rem;
        color: var(--bs-body-color);
    }
    #modalDetailPengeluaran .detail-chip strong {
        font-variant-numeric: tabular-nums;
        font-weight: 700;
    }
    #modalDetailPengeluaran .detail-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--bs-secondary-color);
        background: var(--bs-tertiary-bg);
        border-bottom: 1px solid var(--bs-border-color);
        padding-top: .6rem;
        padding-bottom: .6rem;
    }
    #modalDetailPengeluaran .detail-table tbody td {
        border-color: var(--bs-border-color-translucent);
        vertical-align: middle;
    }
    @media (max-width: 767.98px) {
        /* Di layar sempit, kategori sudah terwakili chip; penerima bisa dibuka di desktop. */
        #modalDetailPengeluaran .detail-table th:nth-child(2),
        #modalDetailPengeluaran .detail-table td:nth-child(2),
        #modalDetailPengeluaran .detail-table th:nth-child(4),
        #modalDetailPengeluaran .detail-table td:nth-child(4) {
            display: none;
        }
        #modalDetailPengeluaran .detail-table th,
        #modalDetailPengeluaran .detail-table td {
            padding-left: .4rem;
            padding-right: .4rem;
        }
        #modalDetailPengeluaran .detail-table {
            table-layout: fixed;
        }
        #modalDetailPengeluaran .detail-table th:first-child {
            width: 96px;
        }
        #modalDetailPengeluaran .detail-table th:last-child {
            width: 116px;
        }
        #modalDetailPengeluaran .detail-table td {
            word-break: break-word;
        }
    }
    @media (max-width: 575.98px) {
        #modalDetailPengeluaran .detail-summary {
            grid-template-columns: 1fr 1fr;
        }
        #modalDetailPengeluaran .detail-summary > div:first-child {
            grid-column: 1 / -1;
            border-bottom: 1px solid var(--bs-border-color);
        }
        #modalDetailPengeluaran .detail-summary > div:nth-child(2) {
            border-left: 0;
        }
    }
</style>

<!-- Page Toolbar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 fs-2">
                        <li class="breadcrumb-item">
                            <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item text-primary fw-medium active" aria-current="page">Asset Berjalan</li>
                    </ol>
                </nav>
                <h4 class="fw-bold mb-0 text-dark">Asset Berjalan</h4>
                <small class="text-muted">Ringkasan omset, biaya, dan pembagian hasil per cabang</small>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="<?= base_url('asset_berjalan?unit=' . urlencode($selected_unit) . '&bulan=' . $bulanSebelum . '&tahun=' . $tahunSebelum) ?>"
                    class="btn btn-light border btn-sm d-inline-flex align-items-center gap-1 py-2 px-3"
                    title="<?= esc($labelSebelum) ?>">
                    <iconify-icon icon="solar:arrow-left-bold-duotone" class="text-primary"></iconify-icon>
                    <span class="d-none d-lg-inline"><?= esc($labelSebelum) ?></span>
                    <span class="d-lg-none">Sebelumnya</span>
                </a>

                <span class="badge bg-primary-subtle text-primary-emphasis fs-3 fw-bold px-3 py-2 rounded-pill">
                    <?= esc($periodeLabelId) ?>
                </span>

                <?php if (!$isBulanBerjalan): ?>
                    <?php $bulanSesudah = (int) date('m', mktime(0, 0, 0, $bulan + 1, 1, $tahun)); ?>
                    <?php $tahunSesudah = (int) date('Y', mktime(0, 0, 0, $bulan + 1, 1, $tahun)); ?>
                    <a href="<?= base_url('asset_berjalan?unit=' . urlencode($selected_unit) . '&bulan=' . $bulanSesudah . '&tahun=' . $tahunSesudah) ?>"
                        class="btn btn-light border btn-sm d-inline-flex align-items-center gap-1 py-2 px-3"
                        title="<?= esc(($namaBulan[$bulanSesudah] ?? '-') . ' ' . $tahunSesudah) ?>">
                        <span class="d-none d-lg-inline"><?= esc(($namaBulan[$bulanSesudah] ?? '-') . ' ' . $tahunSesudah) ?></span>
                        <span class="d-lg-none">Berikutnya</span>
                        <iconify-icon icon="solar:arrow-right-bold-duotone" class="text-primary"></iconify-icon>
                    </a>
                <?php else: ?>
                    <span class="btn btn-light border btn-sm d-inline-flex align-items-center gap-1 py-2 px-3 text-muted opacity-50 disabled" title="Bulan berikutnya belum tersedia">
                        <span>Berikutnya</span>
                        <iconify-icon icon="solar:arrow-right-bold-duotone"></iconify-icon>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($bolehPilihUnit): ?>
            <form method="GET" class="row g-2 align-items-end mt-3 pt-3 border-top">
                <div class="col-12 col-md-5">
                    <label for="filterUnit" class="form-label fs-2 fw-semibold text-muted mb-1">Unit cabang</label>
                    <select id="filterUnit" name="unit" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($list_unit as $u): ?>
                            <option value="<?= $u['idunit'] ?>" <?= $selected_unit == $u['idunit'] ? 'selected' : '' ?>>
                                <?= esc($u['NAMA_UNIT']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label for="filterBulan" class="form-label fs-2 fw-semibold text-muted mb-1">Bulan</label>
                    <select id="filterBulan" name="bulan" class="form-select" onchange="this.form.submit()">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>><?= esc($namaBulan[$i]) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="filterTahun" class="form-label fs-2 fw-semibold text-muted mb-1">Tahun</label>
                    <select id="filterTahun" name="tahun" class="form-select" onchange="this.form.submit()">
                        <?php for ($i = (int) date('Y'); $i >= (int) date('Y') - 3; $i--): ?>
                            <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-1">
                        <iconify-icon icon="solar:filter-bold-duotone" class="align-middle"></iconify-icon>
                        Tampilkan
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex align-items-end justify-content-between mb-2">
    <div>
        <h5 class="fw-bold text-dark mb-0">Ringkasan</h5>
        <small class="text-muted">Periode <?= esc($periodeLabelId) ?></small>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Omset Bulan Ini -->
    <div class="col-md-6 col-xl-3">
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

    <!-- Biaya Terkontrol (Maks 6%) -->
    <div class="col-md-6 col-xl-3">
        <div class="card border shadow-none h-100 bg-warning-subtle bg-opacity-10 border-warning-subtle">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="text-uppercase fs-2 text-warning-emphasis fw-bold d-block mb-1">Biaya Terkontrol (Maks 6%)</h6>
                        <h3 class="fw-bold mb-0 text-warning-emphasis metric-figure">Rp <?= number_format($pengeluaran ?? 0, 0, ',', '.') ?></h3>
                    </div>
                    <div class="p-3 bg-warning-subtle rounded-3 text-warning-emphasis d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:card-send-bold-duotone" width="28" height="28"></iconify-icon>
                    </div>
                </div>
                <small class="text-muted">Realisasi biaya operasional kecil unit periode ini</small>
                <div class="progress mt-2" style="height: 6px;" role="progressbar"
                    aria-label="Rasio biaya terkontrol terhadap batas 6%"
                    aria-valuenow="<?= (int) round(min(100, $rasioBiayaTerkontrol)) ?>"
                    aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-<?= $warnaBiayaTerkontrol ?>" style="width: <?= number_format(min(100, $rasioBiayaTerkontrol), 1, '.', '') ?>%"></div>
                </div>
                <small class="text-<?= $warnaBiayaTerkontrol ?> fw-semibold d-block mt-1">
                    <?= number_format($rasioBiayaTerkontrol, 1, ',', '.') ?>% dari batas — <?= $statusBiayaTerkontrol ?>
                </small>
                <button type="button"
                    class="btn btn-sm btn-outline-warning fw-semibold mt-3 align-self-start d-inline-flex align-items-center gap-1"
                    data-bs-toggle="modal" data-bs-target="#modalDetailPengeluaran"
                    data-kelompok="terkontrol" data-judul="Biaya Terkontrol (Maks 6%)"
                    data-warna="warning" data-icon="solar:card-send-bold-duotone">
                    <iconify-icon icon="solar:list-bold-duotone" class="align-middle"></iconify-icon>
                    Detail
                </button>
            </div>
        </div>
    </div>

    <!-- Biaya Kebutuhan (Di Luar 6%) -->
    <div class="col-md-6 col-xl-3">
        <div class="card border shadow-none h-100 bg-secondary-subtle bg-opacity-10 border-secondary-subtle">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="text-uppercase fs-2 text-secondary-emphasis fw-bold d-block mb-1">Biaya Kebutuhan (Di Luar 6%)</h6>
                        <h3 class="fw-bold mb-0 text-secondary-emphasis metric-figure">Rp <?= number_format($pengeluaran_full ?? 0, 0, ',', '.') ?></h3>
                    </div>
                    <div class="p-3 bg-secondary-subtle rounded-3 text-secondary-emphasis d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:wallet-money-bold-duotone" width="28" height="28"></iconify-icon>
                    </div>
                </div>
                <small class="text-muted">Seluruh kas keluar di luar biaya terkontrol (termasuk HPP, SDM, sewa, cicilan)</small>
                <button type="button"
                    class="btn btn-sm btn-outline-secondary fw-semibold mt-3 align-self-start d-inline-flex align-items-center gap-1"
                    data-bs-toggle="modal" data-bs-target="#modalDetailPengeluaran"
                    data-kelompok="kebutuhan" data-judul="Biaya Kebutuhan (Di Luar 6%)"
                    data-warna="secondary" data-icon="solar:wallet-money-bold-duotone">
                    <iconify-icon icon="solar:list-bold-duotone" class="align-middle"></iconify-icon>
                    Detail
                </button>
            </div>
        </div>
    </div>

    <!-- Total Gaji -->
    <div class="col-md-6 col-xl-3">
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
</div>

<div class="d-flex align-items-end justify-content-between mb-2">
    <div>
        <h5 class="fw-bold text-dark mb-0">Pembagian Hasil</h5>
        <small class="text-muted">Dihitung dari laba bersih periode ini</small>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Total Tanggungan Asset -->
    <div class="col-md-4">
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
    <div class="col-md-4">
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
    <div class="col-md-4">
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

<!-- Detail Pengeluaran: data baru diambil saat tombol Detail diklik -->
<div class="modal fade" id="modalDetailPengeluaran" tabindex="-1" aria-labelledby="detailPengeluaranTitle" aria-hidden="true"
    data-url="<?= base_url('asset_berjalan/detail-pengeluaran') ?>"
    data-unit="<?= (int) $selected_unit ?>"
    data-bulan="<?= (int) $bulan ?>"
    data-tahun="<?= (int) $tahun ?>">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg overflow-hidden">
            <div class="modal-header detail-hero align-items-start gap-3">
                <div class="detail-hero-icon">
                    <iconify-icon id="detailPengeluaranIcon" icon="solar:card-send-bold-duotone" width="24" height="24"></iconify-icon>
                </div>
                <div class="flex-grow-1">
                    <h5 class="modal-title mb-1" id="detailPengeluaranTitle">Detail Pengeluaran</h5>
                    <small class="text-muted">
                        <?= esc($namaUnitAktif ?: ('Unit ' . $selected_unit)) ?> &middot; <?= esc($periodeLabelId) ?>
                    </small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="detail-summary" id="detailPengeluaranSummary">
                <div>
                    <div class="detail-summary-label">Total pengeluaran</div>
                    <div class="detail-summary-value metric-figure" id="detailPengeluaranTotal">Rp 0</div>
                </div>
                <div>
                    <div class="detail-summary-label">Transaksi</div>
                    <div class="detail-summary-value metric-figure" id="detailPengeluaranCount">0</div>
                </div>
                <div>
                    <div class="detail-summary-label">Kategori</div>
                    <div class="detail-summary-value metric-figure" id="detailPengeluaranKategoriCount">0</div>
                </div>
            </div>

            <div class="modal-body">
                <div id="detailPengeluaranError" class="alert alert-danger d-none mb-0 mt-3" role="alert">
                    <span id="detailPengeluaranErrorText">Gagal memuat detail.</span>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="detailPengeluaranRetry">Coba lagi</button>
                </div>
                <div id="detailPengeluaranTableWrap" class="d-none">
                    <div class="detail-section-label mb-2 mt-3">Rincian kategori</div>
                    <div id="detailPengeluaranKategori" class="d-flex flex-wrap gap-2 mb-3"></div>
                    <input type="search" id="detailPengeluaranSearch" class="form-control form-control-sm mb-2"
                        placeholder="Cari deskripsi, penerima, atau kategori…" aria-label="Cari transaksi">
                    <div>
                        <table class="table table-hover align-middle mb-0 detail-table" id="detailPengeluaranTable">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Kategori</th>
                                    <th>Deskripsi</th>
                                    <th>Penerima</th>
                                    <th class="text-end">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody id="detailPengeluaranRows"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer justify-content-between">
                <span class="text-muted fs-2">Sumber: Kas Keluar</span>
                <button type="button" class="btn btn-light border btn-sm px-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('modalDetailPengeluaran');
    if (!modal) return;

    const url = modal.dataset.url;
    const el = (id) => document.getElementById(id);
    const errorBox = el('detailPengeluaranError');
    const errorText = el('detailPengeluaranErrorText');
    const wrap = el('detailPengeluaranTableWrap');
    const searchInput = el('detailPengeluaranSearch');
    const kategoriWrap = el('detailPengeluaranKategori');
    const totalEl = el('detailPengeluaranTotal');
    const countEl = el('detailPengeluaranCount');
    const kategoriCountEl = el('detailPengeluaranKategoriCount');
    const rupiah = new Intl.NumberFormat('id-ID');

    let table = null;
    let aktif = null;

    const tanggal = (v) => {
        const s = String(v || '').slice(0, 10).split('-');
        return s.length === 3 ? s[2] + '/' + s[1] + '/' + s[0] : (v || '-');
    };
    const escHtml = (v) => String(v == null ? '' : v)
        .replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));

    const tampilError = (teks) => {
        errorBox.classList.toggle('d-none', !teks);
        if (teks) errorText.textContent = teks;
    };

    const resetRingkasan = (teks) => {
        totalEl.textContent = teks;
        countEl.textContent = teks;
        kategoriCountEl.textContent = teks;
        kategoriWrap.textContent = '';
    };

    // Ringkasan dihitung server dari seluruh hasil filter, bukan dari baris
    // halaman ini saja — jadi angkanya tidak berubah saat pindah halaman.
    const isiRingkasan = (json) => {
        totalEl.textContent = 'Rp ' + rupiah.format(json.total || 0);
        countEl.textContent = json.recordsFiltered || 0;

        const kategori = json.kategori || [];
        kategoriCountEl.textContent = kategori.length;
        kategoriWrap.textContent = '';
        kategori.forEach((k) => {
            const chip = document.createElement('span');
            chip.className = 'detail-chip';
            const label = document.createElement('span');
            label.textContent = k.nama;
            const value = document.createElement('strong');
            value.textContent = 'Rp ' + rupiah.format(k.jumlah || 0);
            chip.append(label, value);
            kategoriWrap.appendChild(chip);
        });
    };

    // DataTables server-side: modal hanya memuat satu halaman baris, bukan
    // seluruh pengeluaran, sehingga tetap ringan walau transaksinya banyak.
    const buatTabel = () => {
        table = $('#detailPengeluaranTable').DataTable({
            serverSide: true,
            processing: true,
            // Modal masih tersembunyi saat init; biarkan CSS .detail-table yang
            // mengatur lebar, bukan autoWidth yang menghitung dari container 0.
            autoWidth: false,
            pageLength: 10,
            lengthChange: false,
            order: [[0, 'asc']],
            columnDefs: [{ orderable: false, targets: [2] }],
            dom: 'rt<"detail-dt-foot d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3"ip>',
            ajax: {
                url: url,
                data: (d) => {
                    d.unit = modal.dataset.unit;
                    d.bulan = modal.dataset.bulan;
                    d.tahun = modal.dataset.tahun;
                    d.kelompok = aktif;
                },
                dataSrc: (json) => {
                    tampilError('');
                    isiRingkasan(json);
                    return json.data;
                },
                error: (xhr) => {
                    resetRingkasan('—');
                    tampilError(xhr && xhr.status === 403
                        ? 'Anda tidak berhak melihat unit ini.'
                        : (xhr && xhr.status === 401
                            ? 'Sesi berakhir. Muat ulang halaman.'
                            : 'Gagal memuat detail. Periksa koneksi lalu coba lagi.'));
                }
            },
            columns: [
                { data: 'tanggal', render: (d) => tanggal(d), className: 'text-nowrap text-secondary' },
                {
                    data: 'nama_kategori',
                    render: (d) => '<span class="badge rounded-pill bg-light text-dark border fw-medium">'
                        + escHtml(d || 'Tanpa kategori') + '</span>'
                },
                { data: 'deskripsi', render: (d) => escHtml(d || '-') },
                { data: 'penerima', render: (d) => escHtml(d || '-'), className: 'text-muted' },
                { data: 'jumlah', render: (d) => 'Rp ' + rupiah.format(d || 0), className: 'text-end fw-semibold metric-figure text-nowrap' }
            ],
            language: {
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ transaksi',
                infoEmpty: 'Tidak ada transaksi',
                infoFiltered: '',
                zeroRecords: 'Tidak ada pengeluaran.',
                emptyTable: 'Tidak ada pengeluaran.',
                processing: 'Memuat…',
                paginate: { previous: '‹', next: '›' }
            }
        });
        return table;
    };

    modal.addEventListener('show.bs.modal', (event) => {
        const btn = event.relatedTarget;
        if (!btn || !btn.dataset.kelompok) return;

        el('detailPengeluaranTitle').textContent = btn.dataset.judul || 'Detail Pengeluaran';
        modal.dataset.warna = btn.dataset.warna || 'warning';
        el('detailPengeluaranIcon').setAttribute('icon', btn.dataset.icon || 'solar:list-bold-duotone');

        wrap.classList.remove('d-none');
        tampilError('');
        aktif = btn.dataset.kelompok;
        if (searchInput) searchInput.value = '';

        if (!table) {
            resetRingkasan('…');
            buatTabel();
        } else {
            resetRingkasan('…');
            table.search('').ajax.reload();
        }
    });

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            if (table) table.search(searchInput.value).draw();
        });
    }

    el('detailPengeluaranRetry').addEventListener('click', () => {
        tampilError('');
        if (table) table.ajax.reload();
    });
})();
</script>

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
