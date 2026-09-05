<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Penilaian KPI Kepala Toko (SPV)</h4>
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
                        <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $bulan == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
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
    <div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger"><?= session()->getFlashdata('error') ?></div>
<?php endif; ?>

<div class="card w-100 position-relative overflow-hidden">
    <div class="card-body">
        <h5 class="mb-3 fw-semibold">Kepala Toko Unit <?= $myUnit ?> — Periode <?= date('F Y', mktime(0,0,0,$bulan,1,$tahun)) ?></h5>

        <?php if (empty($kepalaTokos)) : ?>
            <div class="alert alert-warning">Tidak ada Kepala Toko aktif pada unit ini.</div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="tableSpvKpi">
                    <thead>
                        <tr>
                            <th>Nama Kepala Toko</th>
                            <th class="text-center" width="280">
                                Kualitas Pelayanan (15%)<br>
                                <small class="text-muted fw-normal">Skor 1 - 5</small>
                            </th>
                            <th class="text-center" width="200">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kepalaTokos as $kt) : ?>
                            <tr>
                                <form method="post" action="<?= base_url('penilaian/save_spv_kpi') ?>">
                                    <input type="hidden" name="employee_id" value="<?= $kt->ID_AKUN ?>">
                                    <input type="hidden" name="bulan" value="<?= $bulan ?>">
                                    <input type="hidden" name="tahun" value="<?= $tahun ?>">
                                    <td class="fw-semibold">
                                        <?= esc($kt->NAMA_AKUN) ?>
                                        <div class="text-muted small">Kepala Toko</div>
                                    </td>
                                    <td class="text-center">
                                        <select class="form-select form-select-sm text-center fw-semibold" name="kualitas_pelayanan" required>
                                            <option value="">-- Pilih Nilai --</option>
                                            <option value="5" <?= (isset($kualitas[$kt->ID_AKUN]) && $kualitas[$kt->ID_AKUN] == 5) ? 'selected' : '' ?>>5 (Sangat Baik - 100%)</option>
                                            <option value="4" <?= (isset($kualitas[$kt->ID_AKUN]) && $kualitas[$kt->ID_AKUN] == 4) ? 'selected' : '' ?>>4 (Baik - 80%)</option>
                                            <option value="3" <?= (isset($kualitas[$kt->ID_AKUN]) && $kualitas[$kt->ID_AKUN] == 3) ? 'selected' : '' ?>>3 (Cukup - 60%)</option>
                                            <option value="2" <?= (isset($kualitas[$kt->ID_AKUN]) && $kualitas[$kt->ID_AKUN] == 2) ? 'selected' : '' ?>>2 (Kurang - 40%)</option>
                                            <option value="1" <?= (isset($kualitas[$kt->ID_AKUN]) && $kualitas[$kt->ID_AKUN] == 1) ? 'selected' : '' ?>>1 (Sangat Kurang - 20%)</option>
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        <button type="submit" class="btn btn-primary btn-sm px-3">
                                            <iconify-icon icon="solar:diskette-bold" class="me-1"></iconify-icon> Simpan
                                        </button>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <small class="text-muted">Hanya Kepala Toko pada unit Anda (<?= $myUnit ?>) yang dapat dinilai.</small>
        <?php endif; ?>
    </div>
</div>
