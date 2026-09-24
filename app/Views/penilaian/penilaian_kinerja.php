<!-- HEADER SECTION -->
<div class="card border-0 bg-white shadow-sm mb-4 rounded-4">
    <div class="card-body d-flex align-items-center p-4">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary-subtle text-primary p-3 rounded-4 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                <i class="fa fa-chart-line fa-lg"></i>
            </div>
            <div>
                <h4 class="fw-bold text-dark mb-1">Penilaian Kinerja & Penggajian</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 small">
                        <li class="breadcrumb-item">
                            <a class="text-decoration-none text-muted" href="<?= base_url('/') ?>">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active text-primary fw-medium" aria-current="page">Penilaian Kinerja</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- FILTER SECTION -->
<div class="card border-0 shadow-sm mb-4 rounded-4">
    <div class="card-body p-4">
        <div class="d-flex align-items-center gap-2 mb-3 text-muted">
            <i class="fa fa-filter text-primary small"></i>
            <span class="small fw-bold text-uppercase tracking-wider">Filter Periode & Karyawan</span>
        </div>
        <form method="get">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-medium">Bulan</label>
                    <select name="bulan" class="form-select bg-light border-0 py-2" onchange="this.form.submit()">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-medium">Tahun</label>
                    <select name="tahun" class="form-select bg-light border-0 py-2" onchange="this.form.submit()">
                        <?php for ($i = date('Y'); $i >= 2023; $i--): ?>
                            <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>>
                                <?= $i ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted small fw-medium">Pilih Karyawan</label>
                    <select name="karyawan" class="form-select bg-light border-0 py-2" onchange="this.form.submit()">
                        <?php foreach ($list_karyawan as $karyawan): ?>
                            <option value="<?= $karyawan['ID_AKUN'] ?>" <?= $selected_karyawan == $karyawan['ID_AKUN'] ? 'selected' : '' ?>>
                                <?= $karyawan['NAMA_AKUN'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- OMSET OVERVIEW SECTION -->
<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100 rounded-4 text-white p-2" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);">
            <div class="card-body d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-white bg-opacity-25 text-white px-3 py-1 rounded-pill small">Global Metric</span>
                        <i class="fa fa-chart-pie opacity-50 fa-lg"></i>
                    </div>
                    <h6 class="text-white-50 fw-medium mb-1">Omset Global</h6>
                    <h2 class="fw-bold mb-2 text-white">
                        Rp <?= number_format($aktual_omset_unit[1] + $aktual_omset_unit[2] + $aktual_omset_unit[3] + $aktual_omset_unit[4], 0, ',', '.') ?>
                    </h2>
                </div>
                <div class="mt-3 pt-3 border-top border-white border-opacity-10 d-flex align-items-center gap-2 small text-white-50">
                    <i class="fa fa-info-circle"></i> Bobot maksimal kontribusi 100 poin
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100 rounded-4">
            <div class="card-body p-4">
                <h6 class="text-dark fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="fa fa-store text-primary"></i> Rincian Omset Cabang
                </h6>
                <div class="row g-3">
                    <?php
                    $cabang_list = [
                        1 => ['nama' => 'Probolinggo', 'val' => $aktual_omset_unit[1]],
                        2 => ['nama' => 'Jember', 'val' => $aktual_omset_unit[2]],
                        3 => ['nama' => 'Banyuwangi', 'val' => $aktual_omset_unit[3]],
                        4 => ['nama' => 'Pandaan', 'val' => $aktual_omset_unit[4]],
                    ];
                    foreach ($cabang_list as $cb):
                    ?>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center">
                                <span class="text-secondary small fw-medium"><?= $cb['nama'] ?></span>
                                <span class="fw-bold text-dark small">Rp <?= number_format($cb['val'], 0, ',', '.') ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FINANCIAL SUMMARY SECTION -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <h6 class="fw-bold text-dark mb-0">Ringkasan Penghasilan & Skor</h6>
</div>
<div class="row g-3 mb-4">
    <!-- Skor Kinerja -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100 rounded-4 bg-primary text-white">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small text-white-50">Skor Kinerja</span>
                    <i class="fa fa-star text-warning"></i>
                </div>
                <h3 class="fw-bold mb-0 text-white"><?= $skor_total ?></h3>
            </div>
        </div>
    </div>
    <!-- Gaji Pokok -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100 rounded-4 bg-white">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <span class="small text-muted mb-2">Gaji Pokok</span>
                <h6 class="fw-bold text-dark mb-0">Rp <?= number_format($gaji_pokok, 0, ',', '.') ?></h6>
            </div>
        </div>
    </div>
    <!-- Tunjangan Kinerja -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100 rounded-4 bg-white">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <span class="small text-muted mb-2">Tunj. Kinerja</span>
                <h6 class="fw-bold text-dark mb-0">Rp <?= number_format($tunjangan_kinerja, 0, ',', '.') ?></h6>
            </div>
        </div>
    </div>
    <!-- Tunjangan Absen -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100 rounded-4 bg-white">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <span class="small text-muted mb-2">Tunj. Absen</span>
                <h6 class="fw-bold text-dark mb-0">Rp <?= number_format($tunjangan_absen, 0, ',', '.') ?></h6>
            </div>
        </div>
    </div>
    <!-- Tunjangan Penempatan -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100 rounded-4 bg-white">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <span class="small text-muted mb-2">Tunj. Penempatan</span>
                <h6 class="fw-bold text-dark mb-0">Rp <?= number_format($tunjangan_penempatan->tunjangan_penempatan, 0, ',', '.') ?></h6>
            </div>
        </div>
    </div>
    <!-- Insentif -->
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="card border-0 shadow-sm h-100 rounded-4 bg-white">
            <div class="card-body p-3 d-flex flex-column justify-content-between">
                <span class="small text-muted mb-2">Insentif</span>
                <h6 class="fw-bold text-dark mb-0">Rp <?= number_format($insentif, 0, ',', '.') ?></h6>
            </div>
        </div>
    </div>
</div>

<!-- TOTAL & ACTIONS HERO CARD (Satu-satunya tombol cetak slip gaji yang strategis) -->
<div class="card border-0 shadow-sm rounded-4 text-white mb-4" style="background: linear-gradient(135deg, #198754 100%, #157347 0%);">
    <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <span class="badge bg-white bg-opacity-25 px-3 py-1 rounded-pill small mb-2 d-inline-block">Take Home Pay (Estimasi)</span>
            <h1 class="fw-bold text-white mb-1 display-6">Rp <?= number_format($gaji, 0, ',', '.') ?></h1>
            <p class="text-white-50 small mb-0"><i class="fa fa-info-circle me-1"></i> Belum termasuk komponen komisi & insentif tambahan</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= base_url('penilaian/slip_gaji/' . $selected_karyawan . '?bulan=' . $bulan . '&tahun=' . $tahun) ?>"
                class="btn btn-light px-4 py-3 fw-bold text-success shadow-sm rounded-3 d-flex align-items-center gap-2" target="_blank">
                <i class="fa fa-print"></i> Cetak Slip Gaji Resmi
            </a>
        </div>
    </div>
</div>

<!-- KPI DETAILS TABLE -->
<div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden">
    <div class="card-header bg-white p-4 border-bottom d-flex align-items-center justify-content-between">
        <h5 class="fw-bold text-dark mb-0">Detail Penilaian Kinerja (KPI)</h5>
        <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill small">Evaluasi Bulanan</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4 py-3">No</th>
                        <th class="py-3">Kriteria</th>
                        <th class="text-center py-3">Bobot</th>
                        <th class="py-3">Target</th>
                        <th class="py-3">Realisasi</th>
                        <th class="py-3">Status / Kekurangan</th>
                        <th class="pe-4 text-center py-3">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($detail_kpi)): ?>
                        <?php $no = 1;
                        foreach ($detail_kpi as $kpi): ?>
                            <tr>
                                <td class="ps-4 text-muted fw-medium"><?= $no++ ?></td>
                                <td class="py-3">
                                    <span class="fw-bold text-dark d-block mb-1"><?= $kpi['nama'] ?></span>
                                    <?php $isCurrency = ($kpi['format'] ?? 'currency') === 'currency'; ?>
                                    <?php if (!empty($kpi['cabang'])): ?>
                                        <div class="small mt-2 p-3 bg-light rounded-3 border">
                                            <?php foreach ($kpi['cabang'] as $cb): ?>
                                                <div class="<?= !empty($cb['reached']) ? 'text-success' : 'text-danger' ?> mb-1 d-flex align-items-center justify-content-between">
                                                    <div>
                                                        <i class="<?= !empty($cb['reached']) ? 'fa fa-check-circle' : 'fa fa-times-circle' ?> me-1"></i>
                                                        <strong>Cab. <?= $cb['unit'] ?>:</strong>
                                                        <span>Rp <?= number_format((float)$cb['target_ho'], 0, ',', '.') ?> &rarr; Rp <?= number_format((float)$cb['actual'], 0, ',', '.') ?></span>
                                                    </div>
                                                    <div>
                                                        <?php if (!empty($cb['reached'])): ?>
                                                            <span class="badge bg-success-subtle text-success">Tercapai</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger-subtle text-danger">-Rp <?= number_format((float)$cb['shortfall_ho'], 0, ',', '.') ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><span class="badge bg-light text-secondary border px-2 py-1"><?= $kpi['bobot'] ?>%</span></td>
                                <td class="text-nowrap">
                                    <?php if ($kpi['target'] !== null): ?>
                                        <span class="fw-medium text-dark"><?= $isCurrency ? 'Rp ' : '' ?><?= number_format((float)$kpi['target'], 0, ',', '.') ?></span>
                                        <?php if ($isCurrency): ?>
                                            <?php if (!empty($kpi['ho'])): ?>
                                                <span class="badge bg-info-subtle text-info ms-1">HO</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary ms-1">Non HO</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php elseif ($kpi['unit_count'] !== null): ?>
                                        <span class="fw-medium text-dark"><?= $kpi['unit_count'] ?> Cabang</span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ($kpi['actual'] !== null): ?>
                                        <span class="fw-medium text-dark"><?= $isCurrency ? 'Rp ' : '' ?><?= number_format((float)$kpi['actual'], 0, ',', '.') ?></span>
                                    <?php elseif ($kpi['reached'] !== null): ?>
                                        <span class="fw-medium text-dark"><?= $kpi['reached'] ?> Cabang</span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ($kpi['unit_count'] !== null): ?>
                                        <?php $kurangCabang = (int)$kpi['shortfall']; ?>
                                        <?php if ($kurangCabang > 0): ?>
                                            <span class="text-danger fw-medium small"><i class="fa fa-arrow-down me-1"></i> Kurang <?= $kurangCabang ?> cabang</span>
                                        <?php else: ?>
                                            <span class="text-success fw-medium small"><i class="fa fa-check me-1"></i> Semua tercapai</span>
                                        <?php endif; ?>
                                    <?php elseif ($kpi['shortfall'] !== null && $kpi['shortfall'] > 0): ?>
                                        <span class="text-danger fw-medium small"><i class="fa fa-arrow-down me-1"></i> <?= $isCurrency ? 'Rp ' : '' ?><?= number_format((float)$kpi['shortfall'], 0, ',', '.') ?></span>
                                    <?php elseif ($kpi['shortfall'] !== null): ?>
                                        <span class="text-success fw-medium small"><i class="fa fa-check me-1"></i> Target tercapai</span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-center">
                                    <?php
                                    $badge = 'secondary';
                                    if ($kpi['nilai'] !== null) {
                                        if ($kpi['nilai'] >= 90) $badge = 'success';
                                        elseif ($kpi['nilai'] >= 75) $badge = 'warning text-dark';
                                        else $badge = 'danger';
                                    }
                                    ?>
                                    <span class="badge bg-<?= $badge ?> px-3 py-2 fw-bold">
                                        <?= $kpi['nilai'] ?? 'N/A' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <div class="py-4">
                                    <i class="fa fa-folder-open fa-3x mb-3 text-secondary opacity-50"></i>
                                    <p class="mb-0">Belum ada data KPI untuk periode ini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ATTENDANCE DETAILS TABLE -->
<div class="card shadow-sm border-0 mb-5 rounded-4 overflow-hidden">
    <div class="card-header bg-white p-4 border-bottom d-flex align-items-center justify-content-between">
        <h5 class="fw-bold text-dark mb-0">Detail Penilaian Absen</h5>
        <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill small">Kehadiran</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4 py-3" width="5%">No</th>
                        <th class="py-3">Kriteria Kehadiran</th>
                        <th class="text-center py-3" width="15%">Bobot</th>
                        <th class="pe-4 text-center py-3" width="15%">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($detail_absen)): ?>
                        <?php $no = 1;
                        foreach ($detail_absen as $absen): ?>
                            <tr>
                                <td class="ps-4 text-muted fw-medium"><?= $no++ ?></td>
                                <td class="fw-bold text-dark py-3"><?= $absen['nama'] ?></td>
                                <td class="text-center"><span class="badge bg-light text-secondary border px-2 py-1"><?= $absen['bobot'] ?>%</span></td>
                                <td class="pe-4 text-center">
                                    <?php
                                    if ($absen['nilai'] >= 90) $badge = 'success';
                                    elseif ($absen['nilai'] >= 75) $badge = 'warning text-dark';
                                    else $badge = 'danger';
                                    ?>
                                    <span class="badge bg-<?= $badge ?> px-3 py-2 fw-bold">
                                        <?= $absen['nilai'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <div class="py-4">
                                    <i class="fa fa-calendar-times fa-3x mb-3 text-secondary opacity-50"></i>
                                    <p class="mb-0">Belum ada data Absen untuk periode ini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
