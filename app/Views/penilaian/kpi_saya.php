<?php
$bulanNama = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];
$periodeLabel = ($bulanNama[(int) $bulan] ?? $bulan) . ' ' . $tahun;
?>
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
            <h4 class="fw-semibold mb-0">KPI Saya</h4>
            <small class="text-muted">Periode <?= esc($periodeLabel) ?></small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                </li>
                <li class="breadcrumb-item active">KPI Saya</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= esc(session()->getFlashdata('error')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <h5 class="fw-semibold mb-1"><?= esc($nama) ?></h5>
                <div class="text-muted small">Unit <?= esc(session()->get('NAMA_UNIT') ?: '-') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card bg-primary-subtle border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <h6 class="text-primary mb-1">Total Skor KPI</h6>
                <h1 class="fw-bold text-primary mb-0"><?= number_format((float) $skor_total, 2, ',', '.') ?></h1>
                <div class="text-muted small mt-1">Skala 0&ndash;100</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <form method="get" action="<?= base_url('kpi_saya') ?>" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label text-muted small fw-medium mb-1" for="f_bulan">Bulan</label>
                <select name="bulan" id="f_bulan" class="form-select bg-light border-0 py-2" onchange="this.form.submit()">
                    <?php for ($i = 1; $i <= 12; $i++) : ?>
                        <option value="<?= $i ?>" <?= (int) $bulan === $i ? 'selected' : '' ?>>
                            <?= $bulanNama[$i] ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-medium mb-1" for="f_tahun">Tahun</label>
                <select name="tahun" id="f_tahun" class="form-select bg-light border-0 py-2" onchange="this.form.submit()">
                    <?php for ($i = (int) date('Y'); $i >= 2023; $i--) : ?>
                        <option value="<?= $i ?>" <?= (int) $tahun === $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-header">
        <h5 class="mb-0">Rincian Komponen KPI</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Komponen</th>
                        <th class="text-center">Bobot</th>
                        <th>Target</th>
                        <th>Realisasi</th>
                        <th class="pe-4 text-center">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (! empty($detail_kpi)) : ?>
                        <?php foreach ($detail_kpi as $item) : ?>
                            <?php
                            $format     = $item['format'] ?? 'currency';
                            $isCurrency = $format === 'currency';
                            $isPercent  = $format === 'percent';
                            $angka      = static fn ($v) => number_format((float) $v, $isPercent ? 2 : 0, ',', '.');
                            $nilai      = $item['nilai'];
                            $badge      = $nilai === null ? 'secondary' : ($nilai >= 90 ? 'success' : ($nilai >= 75 ? 'warning text-dark' : 'danger'));
                            ?>
                            <tr>
                                <td class="ps-4 fw-semibold"><?= esc($item['nama']) ?></td>
                                <td class="text-center"><span class="badge bg-light text-secondary border px-2 py-1"><?= esc($item['bobot']) ?>%</span></td>
                                <td class="text-nowrap">
                                    <?php if ($item['target'] !== null) : ?>
                                        <?= $isCurrency ? 'Rp ' : '' ?><?= $angka($item['target']) ?><?= $isPercent ? '%' : '' ?>
                                    <?php elseif ($item['unit_count'] !== null) : ?>
                                        <?= (int) $item['unit_count'] ?> Cabang
                                    <?php else : ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ($item['actual'] !== null) : ?>
                                        <?= $isCurrency ? 'Rp ' : '' ?><?= $angka($item['actual']) ?><?= $isPercent ? '%' : '' ?>
                                    <?php elseif ($item['reached'] !== null) : ?>
                                        <?= (int) $item['reached'] ?> Cabang
                                    <?php else : ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4 text-center">
                                    <span class="badge bg-<?= $badge ?> px-3 py-2 fw-bold"><?= $nilai ?? 'N/A' ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                Data KPI periode ini belum tersedia.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Penilaian Absensi</h5>
        <span class="badge bg-light text-secondary border px-3 py-2">
            Skor Absensi: <?= number_format((float) $skor_absen, 2, ',', '.') ?>
        </span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" width="5%">No</th>
                        <th>Kriteria Kehadiran</th>
                        <th class="text-center" width="15%">Bobot</th>
                        <th class="pe-4 text-center" width="15%">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (! empty($detail_absen)) : ?>
                        <?php foreach ($detail_absen as $i => $absen) : ?>
                            <?php
                            $nilaiAbsen = (float) $absen['nilai'];
                            $badgeAbsen = $nilaiAbsen >= 90 ? 'success' : ($nilaiAbsen >= 75 ? 'warning text-dark' : 'danger');
                            ?>
                            <tr>
                                <td class="ps-4 text-muted fw-medium"><?= $i + 1 ?></td>
                                <td class="fw-semibold"><?= esc($absen['nama']) ?></td>
                                <td class="text-center"><span class="badge bg-light text-secondary border px-2 py-1"><?= esc($absen['bobot']) ?>%</span></td>
                                <td class="pe-4 text-center">
                                    <span class="badge bg-<?= $badgeAbsen ?> px-3 py-2 fw-bold"><?= esc($absen['nilai']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                Belum ada data absen untuk periode ini.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
