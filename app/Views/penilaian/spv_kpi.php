<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Penilaian Harian Kualitas Pelayanan (SPV)</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">SPV KPI</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden mb-3">
    <div class="card-body">
        <form method="get" action="<?= base_url('penilaian/spv_kpi') ?>" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Bulan</label>
                <select name="bulan" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++) : ?>
                        <option value="<?= str_pad((string)$m, 2, '0', STR_PAD_LEFT) ?>" <?= $bulan == str_pad((string)$m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Tahun</label>
                <input type="number" name="tahun" class="form-control" value="<?= $tahun ?>" min="2020" max="2035">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Tampilkan</button>
            </div>
        </form>
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

<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="fw-semibold mb-0">Kepala Toko Unit <?= $myUnit ?> — Periode <?= date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun)) ?></h5>
    <span class="text-muted small">Diisi setiap hari (tanpa OFF). Nilai bulanan = total skor ÷ (jumlah hari bulan × 5) × 100.</span>
</div>

<?php if (empty($kepalaTokos)) : ?>
    <div class="alert alert-warning">Tidak ada Kepala Toko aktif pada unit ini.</div>
<?php else : ?>
    <?php foreach ($kepalaTokos as $kt) : ?>
        <?= view('penilaian/_manual_grid', [
            'pegawai'        => $kt,
            'jabatanRingkas' => 'Kepala Toko',
            'manualGrid'     => $grids[$kt->ID_AKUN] ?? [],
            'bulan'          => (int)$bulan,
            'tahun'          => (int)$tahun,
            'jumlahHari'     => (int)date('t', strtotime("$tahun-$bulan-01")),
            'saveUrl'        => 'penilaian/kpi/save_daily',
            'editable'       => true,
        ]) ?>
    <?php endforeach; ?>
<?php endif; ?>