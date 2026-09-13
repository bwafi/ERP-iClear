<!-- Page Header -->
<div class="card bg-light-info shadow-none position-relative overflow-hidden mb-4 border-0">
    <div class="card-body px-4 py-3">
        <div class="row align-items-center">
            <div class="col-9">
                <h4 class="fw-semibold mb-2">Customer Satisfaction KPI</h4>
                <p class="text-muted mb-0 fs-3">
                    Kepala Toko menginput jumlah review Google Maps per hari. Total customer dihitung otomatis dari transaksi (penjualan SLL + service). Nilai = <span class="fw-semibold">Jumlah Review &divide; Total Customer &times; 100</span>
                </p>
            </div>
            <div class="col-3 text-end">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 justify-content-end bg-transparent p-0">
                        <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a></li>
                        <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('/penilaian/kpi') ?>">Penilaian</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Customer Satisfaction</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Flash Messages -->
<?php if (session()->getFlashdata('message')) : ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <iconify-icon icon="solar:check-circle-bold" class="fs-5 me-2"></iconify-icon>
            <div><?= session()->getFlashdata('message') ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <iconify-icon icon="solar:close-circle-bold" class="fs-5 me-2"></iconify-icon>
            <div><?= session()->getFlashdata('error') ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="<?= base_url('penilaian/customer_satisfaction') ?>" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Bulan</label>
                <select name="bulan" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php for ($m = 1; $m <= 12; $m++) : ?>
                        <option value="<?= $m ?>" <?= (int)$bulan === $m ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Tahun</label>
                <select name="tahun" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++) : ?>
                        <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <?php if (count($units) <= 1) : ?>
                    <label class="form-label fw-semibold">Unit</label>
                    <input type="text" class="form-control form-control-sm" value="<?= esc($unitNames[$selectedUnit] ?? 'Unit ' . $selectedUnit) ?>" readonly>
                <?php else : ?>
                    <label class="form-label fw-semibold">Unit</label>
                    <select name="id_unit" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($units as $uid) : ?>
                            <option value="<?= (int)$uid ?>" <?= $selectedUnit == $uid ? 'selected' : '' ?>>
                                <?= esc($unitNames[$uid] ?? 'Unit ' . $uid) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted d-block fs-2 text-uppercase fw-semibold">Customer Satisfaction</span>
                    <?php
                        $namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                        ?>
                    <small class="text-muted d-block"><?= esc($namaBulan[(int)$bulan] ?? $bulan) . ' ' . $tahun ?> · <?= esc($unitNames[$selectedUnit] ?? 'Unit ' . $selectedUnit) ?></small>
                    <h2 class="fw-bolder text-success mb-0"><?= number_format((float)$monthly['persen'], 2, ',', '.') ?>%</h2>
                </div>
                <iconify-icon icon="solar:star-bold" class="fs-1 text-success"></iconify-icon>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted d-block fs-2 text-uppercase fw-semibold">Review Google Maps</span>
                    <small class="text-muted d-block">Bulanan · <?= esc($namaBulan[(int)$bulan] ?? $bulan) . ' ' . $tahun ?></small>
                    <h2 class="fw-bolder text-warning mb-0"><?= (int)$monthly['review'] ?></h2>
                </div>
                <iconify-icon icon="solar:star-bold" class="fs-1 text-warning"></iconify-icon>
            </div>
        </div>
    </div>
</div>

<!-- Input Form -->
<?php if ($canInput) : ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="fw-semibold mb-3">Input Review Google Maps</h5>
            <form method="post" action="<?= base_url('penilaian/customer_satisfaction/save') ?>" id="formInputCS" class="row g-3 align-items-end">
                <input type="hidden" name="id_unit" value="<?= (int)$selectedUnit ?>">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Tanggal Review</label>
                    <input type="date" id="tanggalReview" name="tanggal" value="<?= esc($tanggalInput) ?>" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Jumlah Review Google Maps</label>
                    <input type="number" name="jumlah_review" class="form-control form-control-sm" min="0" value="<?= (int)$review ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold d-block">Simpan</label>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Daftar Input Harian -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h5 class="fw-semibold mb-3">Daftar Input Harian (<?= esc($unitNames[$selectedUnit] ?? 'Unit ' . $selectedUnit) ?>)</h5>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th class="text-end">Review</th>
                        <th class="text-end">Customer Satisfaction</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)) : ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">Belum ada input pada bulan ini.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($records as $rec) : ?>
                            <tr>
                                <td><?= date('d-m-Y', strtotime($rec['tanggal'])) ?></td>
                                <td class="text-end"><?= $rec['review'] ?></td>
                                <td class="text-end fw-semibold"><?= number_format($rec['persen'], 2, ',', '.') ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Klik area input tanggal langsung membuka datepicker (pola sparepart_keluar).
        var t = document.getElementById('tanggalReview');
        if (t) {
            t.addEventListener('click', function () {
                this.showPicker && this.showPicker();
            });
        }
    });
</script>