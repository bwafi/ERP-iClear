<!-- Page Header & Breadcrumb -->
<div class="card shadow-none position-relative overflow-hidden mb-4 bg-light-subtle border">
    <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Omset Bulanan</h4>
            <p class="fs-3 text-muted mb-0">Laporan ringkasan penjualan, performa produk, dan tren pendapatan harian</p>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 bg-transparent p-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                </li>
                <li class="breadcrumb-item text-primary fw-medium" aria-current="page">Omset Bulanan</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Period Navigation Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3 px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <a href="<?= base_url('omset_bulanan?unit=' . urlencode($selected_unit) . '&bulan=' . $bulanSebelum . '&tahun=' . $tahunSebelum) ?>"
                class="btn btn-outline-light text-dark border d-inline-flex align-items-center gap-2 py-2 px-3 shadow-sm">
                <iconify-icon icon="solar:arrow-left-bold-duotone" class="text-primary fs-5"></iconify-icon>
                <span class="fw-medium d-none d-md-inline"><?= date('F Y', mktime(0, 0, 0, $bulanSebelum, 1, $tahunSebelum)) ?></span>
                <span class="fw-medium d-md-none">Sebelumnya</span>
            </a>

            <div class="d-flex align-items-center gap-2 px-3 py-2 border rounded-pill bg-primary-subtle bg-opacity-10 border-primary-subtle">
                <iconify-icon icon="solar:calendar-bold-duotone" class="text-primary fs-5"></iconify-icon>
                <span class="fs-4 fw-bold text-primary"><?= esc($periodeLabel) ?></span>
            </div>

            <?php if (!$isBulanBerjalan): ?>
                <?php $bulanSesudah = (int)date('m', mktime(0, 0, 0, $bulan + 1, 1, $tahun)); ?>
                <?php $tahunSesudah = (int)date('Y', mktime(0, 0, 0, $bulan + 1, 1, $tahun)); ?>
                <a href="<?= base_url('omset_bulanan?unit=' . urlencode($selected_unit) . '&bulan=' . $bulanSesudah . '&tahun=' . $tahunSesudah) ?>"
                    class="btn btn-outline-light text-dark border d-inline-flex align-items-center gap-2 py-2 px-3 shadow-sm">
                    <span class="fw-medium d-none d-md-inline"><?= date('F Y', mktime(0, 0, 0, $bulanSesudah, 1, $tahunSesudah)) ?></span>
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
<?php if (in_array($id_jabatan, [1, 0, 34, 40])): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row align-items-end g-3">
                <div class="col-md-5">
                    <label class="form-label fw-semibold fs-3 text-dark">Pilih Unit Cabang</label>
                    <select name="unit" class="form-select bg-white" onchange="this.form.submit()">
                        <?php foreach ($list_unit as $u): ?>
                            <option value="<?= $u['idunit'] ?>" <?= $selected_unit == $u['idunit'] ? 'selected' : '' ?>>
                                <?= $u['NAMA_UNIT'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold fs-3 text-dark">Bulan Pelaporan</label>
                    <select name="bulan" class="form-select bg-white" onchange="this.form.submit()">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold fs-3 text-dark">Tahun</label>
                    <select name="tahun" class="form-select bg-white" onchange="this.form.submit()">
                        <?php for ($i = date('Y'); $i >= date('Y') - 3; $i--): ?>
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
    <!-- Row 1: Key Performance Cards -->
    <div class="col-md-6 col-lg-3">
        <div class="card border shadow-none h-100 bg-success-subtle bg-opacity-10 border-success-subtle">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="text-uppercase fs-2 text-success fw-bold d-block mb-1">Omset Hari Ini</span>
                        <h3 class="fw-bold mb-0 text-success">Rp <?= number_format($omset_hari_ini ?? 0, 0, ',', '.') ?></h3>
                    </div>
                    <div class="p-3 bg-success-subtle rounded-3 text-success d-flex align-items-center justify-content-center">
                        <iconify-icon icon="solar:chart-bold-duotone" width="28" height="28"></iconify-icon>
                    </div>
                </div>
                <small class="text-muted">Akumulasi pendapatan hari ini</small>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card border shadow-none h-100 bg-primary-subtle bg-opacity-10 border-primary-subtle">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="text-uppercase fs-2 text-primary fw-bold d-block mb-1">Total Omset Bulan Ini</span>
                        <h3 class="fw-bold mb-0 text-primary">Rp <?= number_format($omset_bulan ?? 0, 0, ',', '.') ?></h3>
                    </div>
                    <div class="p-3 bg-primary-subtle rounded-3 text-primary d-flex align-items-center justify-content-center">
                        <iconify-icon icon="solar:wallet-money-bold-duotone" width="28" height="28"></iconify-icon>
                    </div>
                </div>
                <small class="text-muted">
                    Periode <?= esc($periodeLabel) ?> ·
                    <?php if ($pertumbuhan_omset > 0): ?>
                        <span class="text-success fw-semibold"><iconify-icon icon="solar:arrow-up-bold-duotone"></iconify-icon> +<?= number_format($pertumbuhan_omset, 1) ?>%</span>
                    <?php elseif ($pertumbuhan_omset < 0): ?>
                        <span class="text-danger fw-semibold"><iconify-icon icon="solar:arrow-down-bold-duotone"></iconify-icon> <?= number_format($pertumbuhan_omset, 1) ?>%</span>
                    <?php else: ?>
                        <span class="text-muted">±0%</span>
                    <?php endif; ?>
                </small>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card border shadow-none h-100">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="text-uppercase fs-2 text-muted fw-bold d-block mb-1">Bulan Sebelumnya</span>
                        <h4 class="fw-bold mb-0 text-dark">Rp <?= number_format($omset_bulan_lalu ?? 0, 0, ',', '.') ?></h4>
                    </div>
                    <div class="p-3 bg-secondary-subtle rounded-3 text-dark d-flex align-items-center justify-content-center">
                        <iconify-icon icon="solar:history-bold-duotone" width="28" height="28"></iconify-icon>
                    </div>
                </div>
                <small class="text-muted"><?= esc($periodeLaluLabel) ?> · Selisih <?= $selisih_omset >= 0 ? '+' : '' ?>Rp <?= number_format(abs($selisih_omset ?? 0), 0, ',', '.') ?></small>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card border shadow-none h-100">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="text-uppercase fs-2 text-muted fw-bold d-block mb-1">Rata-rata / Hari</span>
                        <h4 class="fw-bold mb-0 text-dark">Rp <?= number_format($omset_rata_rata ?? 0, 0, ',', '.') ?></h4>
                    </div>
                    <div class="p-3 bg-info-subtle rounded-3 text-info d-flex align-items-center justify-content-center">
                        <iconify-icon icon="solar:calculator-minimalistic-bold-duotone" width="28" height="28"></iconify-icon>
                    </div>
                </div>
                <small class="text-muted"><?php if ($isBulanBerjalan ?? false): ?>Rata-rata <?= (int) ($hari_rata_rata ?? 0) ?> hari berjalan (s/d <?= date('j F Y') ?>)<?php else: ?>Rata-rata <?= (int) ($hari_rata_rata ?? 0) ?> hari pada bulan ini<?php endif; ?></small>
            </div>
        </div>
    </div>

    <!-- Row 2: Secondary Highlights -->
    <div class="col-md-6 col-lg-3">
        <div class="card border shadow-none h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-primary-subtle text-primary mb-2 px-3 py-1 fw-semibold">Fast Moving Service</span>
                        <h5 class="fw-bold mb-1 text-dark text-truncate" style="max-width: 180px;"><?= esc($bestsellerproduct->tipe_hp_label ?? '-') ?></h5>
                        <span class="fs-2 text-muted">Selesai: <strong class="text-dark"><?= number_format($bestsellerproduct->total ?? 0, 0, ',', '.') ?></strong> service</span>
                    </div>
                    <div class="p-3 bg-primary-subtle rounded-3 text-primary d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:medal-ribbons-star-bold-duotone" width="30" height="30"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card border shadow-none h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-info-subtle text-info mb-2 px-3 py-1 fw-semibold">Sparepart Best Seller</span>
                        <h5 class="fw-bold mb-1 text-dark text-truncate" style="max-width: 180px;"><?= esc($bestseller->nama_barang ?? '-') ?></h5>
                        <span class="fs-2 text-muted">Total Terjual: <strong class="text-dark"><?= number_format($bestseller->total_penjualan ?? 0, 0, ',', '.') ?></strong></span>
                    </div>
                    <div class="p-3 bg-info-subtle rounded-3 text-info d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:box-minimalistic-bold-duotone" width="30" height="30"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card border shadow-none h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-danger-subtle text-danger mb-2 px-3 py-1 fw-semibold">Hari Omset Tertinggi</span>
                        <h5 class="fw-bold mb-1 text-dark text-truncate" style="max-width: 180px;"><?= $hariTerbaik ? date('d F Y', strtotime($hariTerbaik)) : '-' ?></h5>
                        <span class="fs-2 text-muted"><?= $hariTerbaik ? 'Rp ' . number_format($omsetTerbaik ?? 0, 0, ',', '.') : 'Belum ada data' ?></span>
                    </div>
                    <div class="p-3 bg-danger-subtle rounded-3 text-danger d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:medal-star-bold-duotone" width="30" height="30"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card border shadow-none h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="overflow-hidden">
                        <span class="badge bg-warning-subtle text-warning-emphasis mb-2 px-3 py-1 fw-semibold">Domisili Terbanyak</span>
                        <h5 class="fw-bold mb-1 text-dark text-truncate" style="max-width: 180px;" title="<?= esc($top_kecamatan->nama_kecamatan ?? '-') ?>">
                            <?= esc($top_kecamatan->nama_kecamatan ?? '-') ?>
                        </h5>
                        <div class="d-flex align-items-center gap-1">
                            <span class="fs-2 text-muted">
                                <strong class="text-dark"><?= number_format($top_kecamatan->total_pelanggan ?? 0, 0, ',', '.') ?></strong> pelanggan (<?= $top_kecamatan->persentase ?? 0 ?>%)
                            </span>
                        </div>
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none d-inline-flex align-items-center gap-1 mt-1 text-primary fs-2 fw-medium" data-bs-toggle="modal" data-bs-target="#modalKecamatan">
                            Lihat Sebaran (<?= count($list_kecamatan ?? []) ?>) <iconify-icon icon="solar:alt-arrow-right-line-duotone"></iconify-icon>
                        </button>
                    </div>
                    <div class="p-3 bg-warning-subtle rounded-3 text-warning-emphasis d-flex align-items-center justify-content-center flex-shrink-0">
                        <iconify-icon icon="solar:map-point-wave-bold-duotone" width="30" height="30"></iconify-icon>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 3: Operational Operational Stats -->
    <div class="col-md-3">
        <div class="card border shadow-none h-100">
            <div class="card-body p-3">
                <span class="text-muted fs-2 d-block mb-1">Total Pelanggan Masuk</span>
                <h5 class="fw-bold text-dark mb-0"><?= number_format($pelanggan_bulan ?? 0, 0, ',', '.') ?></h5>
                <small class="text-muted fs-2"><?= number_format($countService ?? 0) ?> service / <?= number_format($countSales ?? 0) ?> sales</small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border shadow-none h-100">
            <div class="card-body p-3">
                <span class="text-muted fs-2 d-block mb-1">Total Sparepart Keluar</span>
                <h5 class="fw-bold text-dark mb-0"><?= number_format($sparepart_keluar ?? 0, 0, ',', '.') ?> Unit</h5>
                <small class="text-muted fs-2">Periode <?= esc($periodeLabel) ?></small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border shadow-none h-100">
            <div class="card-body p-3">
                <span class="text-muted fs-2 d-block mb-1">Total HPP</span>
                <h5 class="fw-bold text-dark mb-0">Rp <?= number_format($hpp ?? 0, 0, ',', '.') ?></h5>
                <small class="text-muted fs-2">Periode <?= esc($periodeLabel) ?></small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border shadow-none h-100">
            <div class="card-body p-3">
                <span class="text-muted fs-2 d-block mb-1">HPP Value</span>
                <h5 class="fw-bold text-dark mb-0">Rp <?= number_format($hpp_global ?? 0, 0, ',', '.') ?></h5>
                <small class="text-muted fs-2"><?= $isBulanBerjalan ? 'Bulan berjalan' : 'Periode lampau' ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Date Range Filtering Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3 p-md-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-medium fs-3 text-dark">Dari Tanggal</label>
                <input type="date" id="startDate" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-medium fs-3 text-dark">Sampai Tanggal</label>
                <input type="date" id="endDate" class="form-control">
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2 py-2" onclick="filterData()">
                    <iconify-icon icon="solar:filter-bold-duotone" width="18" height="18"></iconify-icon>
                    Filter Grafik & Tabel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Chart Section -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="fw-bold mb-1">Grafik Omset Harian</h5>
                <small class="text-muted">Tren pendapatan per hari untuk bulan <?= esc($periodeLabel) ?></small>
            </div>
            <div class="p-2 bg-light rounded-2 text-primary">
                <iconify-icon icon="solar:graph-up-bold-duotone" width="24" height="24"></iconify-icon>
            </div>
        </div>
        <div style="position: relative; height: 320px; width: 100%;">
            <canvas id="chartOmset"></canvas>
        </div>
    </div>
</div>

<!-- Detailed Table Section -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="fw-bold mb-1">Detail Omset Harian</h5>
                <small class="text-muted">Rincian pendapatan per tanggal bulan <?= esc($periodeLabel) ?></small>
            </div>
            <div class="p-2 bg-light rounded-2 text-primary">
                <iconify-icon icon="solar:bill-list-bold-duotone" width="24" height="24"></iconify-icon>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-hover border mb-0" id="tableOmset">
                <thead class="table-light text-dark fs-3">
                    <tr>
                        <th width="6%" class="text-center">No</th>
                        <th>Tanggal</th>
                        <th class="text-end">Omset</th>
                        <th width="120px" class="text-center">Arus Kas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; ?>
                    <?php foreach ($listHari as $hari): ?>
                        <tr>
                            <td class="text-center text-muted fs-3"><?= $no++ ?></td>
                            <td class="fw-medium text-dark"><?= date('d F Y', strtotime($hari['tanggal'])) ?></td>
                            <td class="text-end fw-semibold">
                                <?php if ($hari['total'] != 0): ?>
                                    <span class="<?= $hari['total'] > 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $hari['total'] < 0 ? '-Rp ' . number_format(abs($hari['total']), 0, ',', '.') : 'Rp ' . number_format($hari['total'], 0, ',', '.') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Rp 0</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <button type="button"
                                    class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 btn-arus-kas"
                                    data-tanggal="<?= esc($hari['tanggal']) ?>"
                                    title="Lihat Arus Kas Harian">
                                    <iconify-icon icon="solar:wallet-money-bold-duotone" width="16" height="16"></iconify-icon>
                                    <span class="d-none d-xl-inline">Arus Kas</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="2" class="text-end fw-bold text-dark">Total Bulan Ini</th>
                        <th class="text-end text-primary fw-bold fs-4" id="totalOmset">
                            Rp <?= number_format($omset_bulan ?? 0, 0, ',', '.') ?>
                        </th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="alert alert-light border mt-3 mb-0 d-flex align-items-center gap-2 py-2 px-3 text-muted fs-2">
            <iconify-icon icon="solar:info-circle-bold-duotone" class="fs-5 text-primary flex-shrink-0"></iconify-icon>
            <span>Kolom <strong>Omset</strong> murni menghitung nilai penjualan/penyerahan barang/jasa. Tombol <strong>Arus Kas</strong> menampilkan mutasi fisik kas/bank masuk dan keluar pada tanggal tersebut.</span>
        </div>
    </div>
</div>

<!-- Modal Sebaran Domisili Kecamatan -->
<div class="modal fade" id="modalKecamatan" tabindex="-1" aria-labelledby="modalKecamatanLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom bg-light">
                <div>
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="modalKecamatanLabel">
                        <iconify-icon icon="solar:map-point-wave-bold-duotone" class="text-warning-emphasis fs-5"></iconify-icon>
                        Sebaran Domisili Pelanggan (Kecamatan)
                    </h5>
                    <small class="text-muted fs-2">Periode <?= esc($periodeLabel) ?></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded-3 border text-center">
                            <span class="text-muted fs-2 d-block mb-1">Total Pelanggan</span>
                            <h5 class="fw-bold text-dark mb-0"><?= number_format($total_kecamatan_pelanggan ?? 0, 0, ',', '.') ?></h5>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded-3 border text-center">
                            <span class="text-muted fs-2 d-block mb-1">Total Transaksi/Unit</span>
                            <h5 class="fw-bold text-dark mb-0"><?= number_format($total_kecamatan_transaksi ?? 0, 0, ',', '.') ?></h5>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded-3 border text-center">
                            <span class="text-muted fs-2 d-block mb-1">Kecamatan Terdata</span>
                            <h5 class="fw-bold text-primary mb-0"><?= count($list_kecamatan ?? []) ?></h5>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <iconify-icon icon="solar:magnifer-linear" class="text-muted"></iconify-icon>
                        </span>
                        <input type="text" id="searchKecamatan" class="form-control border-start-0" placeholder="Cari nama kecamatan..." onkeyup="filterKecamatanTable()">
                    </div>
                </div>

                <div class="table-responsive border rounded-3">
                    <table class="table align-middle table-hover mb-0" id="tableKecamatanList">
                        <thead class="table-light text-dark fs-2">
                            <tr>
                                <th width="8%" class="text-center">#</th>
                                <th width="35%">Kecamatan</th>
                                <th width="20%" class="text-center">Pelanggan</th>
                                <th width="17%" class="text-center">Transaksi</th>
                                <th width="20%">Porsi (%)</th>
                            </tr>
                        </thead>
                        <tbody class="fs-2" id="tbodyKecamatan">
                            <?php if (!empty($list_kecamatan)): ?>
                                <?php foreach ($list_kecamatan as $idx => $kec): ?>
                                    <tr>
                                        <td class="text-center fw-bold text-muted">
                                            <?php if ($idx === 0): ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis rounded-circle p-1">🥇</span>
                                            <?php elseif ($idx === 1): ?>
                                                <span class="badge bg-secondary-subtle text-secondary rounded-circle p-1">🥈</span>
                                            <?php elseif ($idx === 2): ?>
                                                <span class="badge bg-light-subtle text-dark rounded-circle p-1">🥉</span>
                                            <?php else: ?>
                                                <?= $idx + 1 ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark nama-kecamatan-text"><?= esc($kec->nama_kecamatan) ?></span>
                                            <?php if ($idx === 0): ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis ms-1 fs-1">Terbanyak</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center fw-medium"><?= number_format($kec->total_pelanggan, 0, ',', '.') ?> orang</td>
                                        <td class="text-center text-muted"><?= number_format($kec->total_transaksi, 0, ',', '.') ?> trx</td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar <?= $idx === 0 ? 'bg-warning' : 'bg-primary' ?>" role="progressbar" style="width: <?= min(100, $kec->persentase) ?>%;" aria-valuenow="<?= $kec->persentase ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <span class="text-muted fs-1 fw-semibold" style="width: 40px; text-align: right;"><?= $kec->persentase ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <iconify-icon icon="solar:info-circle-linear" class="fs-4 d-block mb-1"></iconify-icon>
                                        Belum ada data domisili kecamatan pelanggan untuk periode ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Arus Kas Harian (Drill-down Offcanvas) -->
<div class="offcanvas offcanvas-end" style="--bs-offcanvas-width: min(760px, 94vw);" tabindex="-1" id="offcanvasArusKas" aria-labelledby="offcanvasArusKasLabel">
    <div class="offcanvas-header border-bottom bg-light">
        <div>
            <h5 class="offcanvas-title fw-bold text-dark mb-0" id="offcanvasArusKasLabel">Arus Kas Harian</h5>
            <small class="text-muted fs-2" id="arusKasTanggal">-</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>
    <div class="offcanvas-body p-3 p-md-4" id="arusKasBody">
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Memuat...</span>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Script & Logic -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ARUS_KAS_URL = '<?= base_url('omset_bulanan/arus_kas') ?>';
    const ARUS_KAS_UNIT = <?= (int) $selected_unit ?>;

    const formatRupiah = (value) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.abs(value || 0));

    const rawData = [
        <?php foreach ($listHari as $item): ?> {
                tanggal: "<?= $item['tanggal'] ?>",
                label: "<?= date('d M', strtotime($item['tanggal'])) ?>",
                total: <?= $item['total'] ?? 0 ?>
            },
        <?php endforeach; ?>
    ];

    const ctx = document.getElementById('chartOmset').getContext('2d');
    let chartOmset = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Omset Harian',
                data: [],
                tension: 0.3,
                fill: true,
                backgroundColor: 'rgba(13, 110, 253, 0.05)',
                borderColor: '#0d6efd',
                borderWidth: 2.5,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let value = context.raw || 0;
                            return ' Omset: Rp ' + new Intl.NumberFormat('id-ID').format(value);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        callback: function(value) {
                            if (value >= 1000000) {
                                return 'Rp ' + (value / 1000000).toFixed(1) + ' Jt';
                            }
                            return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    renderChart(rawData);

    function renderChart(data) {
        chartOmset.data.labels = data.map(item => item.label);
        chartOmset.data.datasets[0].data = data.map(item => item.total);
        chartOmset.update();
    }

    function filterData() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        let filtered = rawData;

        if (startDate && endDate) {
            filtered = rawData.filter(item => {
                return item.tanggal >= startDate && item.tanggal <= endDate;
            });
        }

        renderChart(filtered);
        updateTable(filtered);
    }

    function updateTable(data) {
        let tbody = '';
        let totalBulan = 0;

        data.forEach((item, index) => {
            let val = parseInt(item.total) || 0;
            totalBulan += val;

            let formattedDate = new Date(item.tanggal).toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'long',
                year: 'numeric'
            });

            tbody += `
                <tr>
                    <td class="text-center text-muted fs-3">${index + 1}</td>
                    <td class="fw-medium text-dark">${formattedDate}</td>
                    <td class="text-end fw-semibold">
                        <span class="${val > 0 ? 'text-success' : (val < 0 ? 'text-danger' : 'text-muted')}">
                            ${val < 0 ? '-Rp ' : 'Rp '} ${new Intl.NumberFormat('id-ID').format(Math.abs(val))}
                        </span>
                    </td>
                    <td class="text-center">
                        <button type="button"
                                class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 btn-arus-kas"
                                data-tanggal="${item.tanggal}"
                                title="Lihat Arus Kas Harian">
                            <iconify-icon icon="solar:wallet-money-bold-duotone" width="16" height="16"></iconify-icon>
                            <span class="d-none d-xl-inline">Arus Kas</span>
                        </button>
                    </td>
                </tr>
            `;
        });

        document.querySelector('#tableOmset tbody').innerHTML = tbody || '<tr><td colspan="4" class="text-center text-muted py-4">Tidak ada data untuk rentang tanggal ini</td></tr>';
        document.getElementById('totalOmset').innerHTML = 'Rp ' + new Intl.NumberFormat('id-ID').format(totalBulan);
    }

    // ==========================================
    // ARUS KAS HARIAN DRILL-DOWN (FIXED LAYOUT)
    // ==========================================
    let arusKasInstance = null;

    document.addEventListener('click', function(event) {
        const button = event.target.closest('.btn-arus-kas');
        if (!button) return;
        event.preventDefault();
        bukaArusKas(button.dataset.tanggal);
    });

    function bukaArusKas(tanggal) {
        const offcanvasEl = document.getElementById('offcanvasArusKas');
        if (typeof bootstrap !== 'undefined' && bootstrap.Offcanvas) {
            arusKasInstance = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
            arusKasInstance.show();
        }

        document.getElementById('arusKasTanggal').textContent = formatTanggalPanjang(tanggal);
        document.getElementById('arusKasBody').innerHTML =
            '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Memuat...</span></div></div>';

        const url = ARUS_KAS_URL + '?unit=' + encodeURIComponent(ARUS_KAS_UNIT) + '&tanggal=' + encodeURIComponent(tanggal);

        fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then((response) => response.json())
            .then((payload) => {
                if (!payload || payload.success !== true) {
                    renderArusKasError((payload && payload.message) ? payload.message : 'Gagal memuat data arus kas');
                    return;
                }
                renderArusKas(payload.data);
            })
            .catch(() => renderArusKasError('Gagal memuat data arus kas'));
    }

    function renderArusKasError(pesan) {
        document.getElementById('arusKasBody').innerHTML =
            '<div class="alert alert-warning mb-0 border-0 shadow-sm fs-2">' + pesan + '</div>';
    }

    function formatTanggalPanjang(tanggal) {
        const parts = String(tanggal).split('-');
        if (parts.length !== 3) return tanggal;
        const bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return parts[2] + ' ' + (bulan[Number(parts[1]) - 1] || parts[1]) + ' ' + parts[0];
    }

    const escHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));

    function barisArusKas(rows, warnaNominal) {
        if (!rows || !rows.length) {
            return '<tr><td colspan="3" class="text-muted py-2 text-center fs-2">Tidak ada transaksi</td></tr>';
        }

        return rows.map((row) => `
            <tr>
                <td class="text-muted fs-2 text-nowrap pe-2" style="width: 78px;">${row.waktu ? escHtml(row.waktu) : '-'}</td>
                <td class="text-break fs-2">${escHtml(row.keterangan)}${row.transfer_internal ? ' <span class="badge bg-secondary-subtle text-dark border">Internal</span>' : ''}</td>
                <td class="text-end fw-semibold fs-2 text-nowrap ${warnaNominal}">${formatRupiah(row.nominal)}</td>
            </tr>
        `).join('');
    }

    function blokArusKas(judul, ikon, labelSubtotal, rowsTunai, rowsTransfer, subtotalTunai, subtotalTransfer, isPengeluaran = false) {
        const warnaSubtotal = isPengeluaran ? 'text-danger' : 'text-success';
        const warnaNominalBaris = isPengeluaran ? 'text-danger' : 'text-success';

        return `
            <div class="card border shadow-none mb-3 overflow-hidden">
                <div class="card-header bg-light border-bottom d-flex align-items-center gap-2 py-2 px-3">
                    <iconify-icon icon="${ikon}" width="18" height="18" class="${isPengeluaran ? 'text-danger' : 'text-success'}"></iconify-icon>
                    <span class="fw-bold text-dark fs-3">${judul}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr class="bg-light-subtle text-muted fs-2">
                                <th class="ps-3 border-bottom text-nowrap" style="width: 78px;">Waktu</th>
                                <th class="border-bottom">Keterangan</th>
                                <th class="text-end pe-3 border-bottom text-nowrap">Nominal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="3" class="fw-bold fs-2 text-dark bg-light-subtle py-1 ps-3">
                                    <span class="badge bg-secondary-subtle text-dark border">TUNAI</span>
                                </td>
                            </tr>
                            ${barisArusKas(rowsTunai, warnaNominalBaris)}
                            <tr class="table-light">
                                <td colspan="2" class="text-end fw-semibold fs-2 ps-3 border-top">Total ${labelSubtotal} Tunai</td>
                                <td class="text-end fw-bold fs-2 pe-3 border-top ${warnaSubtotal}">${formatRupiah(subtotalTunai)}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="fw-bold fs-2 text-dark bg-light-subtle py-1 ps-3 border-top">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">TRANSFER / BANK</span>
                                </td>
                            </tr>
                            ${barisArusKas(rowsTransfer, warnaNominalBaris)}
                            <tr class="table-light">
                                <td colspan="2" class="text-end fw-semibold fs-2 ps-3 border-top">Total ${labelSubtotal} Transfer</td>
                                <td class="text-end fw-bold fs-2 pe-3 border-top ${warnaSubtotal}">${formatRupiah(subtotalTransfer)}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    function renderArusKas(data) {
        const subtotal = data.subtotal || {};
        const internal = data.transfer_internal || [];
        const net = data.net || 0;

        let html = `
            <div class="alert alert-info border-0 shadow-sm d-flex gap-2 align-items-start mb-3 p-3">
                <iconify-icon icon="solar:info-circle-bold-duotone" width="20" height="20" class="flex-shrink-0 mt-1"></iconify-icon>
                <div class="fs-2">
                    Angka di bawah adalah <strong>mutasi fisikal kas/bank</strong> (bukan omset). Kas awal Tutup Kasir tidak dihitung penerimaan baru.
                </div>
            </div>
        `;

        // Kas Masuk (Penerimaan)
        html += blokArusKas(
            'Kas Masuk',
            'solar:arrow-down-bold-duotone',
            'Kas Masuk',
            data.masuk.tunai,
            data.masuk.transfer,
            subtotal.masuk_tunai,
            subtotal.masuk_transfer,
            false
        );

        // Kas Keluar (Pengeluaran - Fixed color to text-danger)
        html += blokArusKas(
            'Kas Keluar',
            'solar:arrow-up-bold-duotone',
            'Kas Keluar',
            data.keluar.tunai,
            data.keluar.transfer,
            subtotal.keluar_tunai,
            subtotal.keluar_transfer,
            true
        );

        // Transfer Internal
        if (internal && internal.length) {
            html += `
                <div class="card border shadow-none mb-3 overflow-hidden">
                    <div class="card-header bg-light border-bottom d-flex align-items-center gap-2 py-2 px-3">
                        <iconify-icon icon="solar:refresh-bold-duotone" width="18" height="18" class="text-dark"></iconify-icon>
                        <span class="fw-bold text-dark fs-3">Transfer Internal</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="bg-light-subtle border-bottom text-muted fs-2">
                                    <th style="width: 65px;" class="ps-3">Waktu</th>
                                    <th>Keterangan</th>
                                    <th class="text-end pe-3">Nominal</th>
                                </tr>
                            </thead>
                            <tbody>${barisArusKas(internal, 'text-muted')}</tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-light border-top py-2 px-3">
                        <small class="text-muted fs-2 d-block">
                            Perpindahan antar kas/rekening internal. Tidak mempengaruhi Net Cash Flow.
                        </small>
                    </div>
                </div>
            `;
        }

        // Net Cash Flow Summary
        html += `
            <div class="card border shadow-none bg-primary-subtle border-primary-subtle">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-uppercase fs-2 text-muted fw-bold">Net Cash Flow</span>
                        <span class="text-muted fs-2">Kas Masuk - Kas Keluar</span>
                    </div>
                    <h4 class="fw-bold mb-3 ${net >= 0 ? 'text-success' : 'text-danger'}">
                        ${net < 0 ? '- ' : ''}${formatRupiah(net)}
                    </h4>
                    <div class="row g-2 pt-2 border-top">
                        <div class="col-6">
                            <small class="text-muted d-block fs-2">Total Kas Masuk</small>
                            <span class="fw-semibold text-success fs-3">${formatRupiah(data.total_masuk)}</span>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block fs-2">Total Kas Keluar</small>
                            <span class="fw-semibold text-danger fs-3">${formatRupiah(data.total_keluar)}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('arusKasBody').innerHTML = html;
    }

    function filterKecamatanTable() {
        const input = document.getElementById('searchKecamatan');
        const filter = (input ? input.value : '').toLowerCase().trim();
        const tbody = document.getElementById('tbodyKecamatan');
        if (!tbody) return;
        const rows = tbody.getElementsByTagName('tr');
        for (let i = 0; i < rows.length; i++) {
            const nameEl = rows[i].querySelector('.nama-kecamatan-text');
            if (nameEl) {
                const text = nameEl.textContent || nameEl.innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }
    }
</script>
