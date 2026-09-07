<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
            <h4 class="fw-semibold mb-0">Kontrol Aset — Audit Bulanan</h4>
            <span class="text-muted small">Audit keberadaan & perawatan aset per unit per bulan (wajib lengkap & FINAL)</span>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('penilaian/kpi/kontrol_aset') ?>">Master Aset</a></li>
                <li class="breadcrumb-item active" aria-current="page">Kontrol Aset</li>
            </ol>
        </nav>
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

<?php if (empty($unitList)) : ?>
    <div class="alert alert-warning">Tidak ada unit yang dapat Anda kelola pada menu ini.</div>
<?php return;
endif; ?>

<div class="card w-100 position-relative overflow-hidden mb-3">
    <div class="card-body">
        <form method="get" action="<?= base_url('penilaian/kpi/kontrol_aset') ?>" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Unit</label>
                <select name="unit" class="form-select">
                    <?php foreach ($unitList as $uid => $nama) : ?>
                        <option value="<?= $uid ?>" <?= $uid === $unitId ? 'selected' : '' ?>><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Bulan</label>
                <select name="bulan" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++) : ?>
                        <option value="<?= str_pad((string)$m, 2, '0', STR_PAD_LEFT) ?>" <?= $bulan == str_pad((string)$m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Tahun</label>
                <input type="number" name="tahun" class="form-control" value="<?= $tahun ?>" min="2020" max="2035">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Tampilkan</button>
            </div>
        </form>
        <hr>
        <div class="text-muted small">
            <strong>Kondisi</strong> hanya untuk monitoring (tidak masuk KPI). <strong>Keberadaan (70%) + Perawatan (30%)</strong> yang masuk KPI.
            Audit harus lengkap & di-FINAL agar KPI KONTROL_ASET terhitung. Tanpa fallback audit bulan lalu.
        </div>
    </div>
</div>

<?php
$s = $summary ?? ['totalAset' => 0, 'totalTerdaftar' => 0, 'totalDitemukan' => 0, 'totalHilang' => 0, 'auditedCount' => 0, 'progress' => 0, 'existence' => null, 'maintenance' => null, 'final' => null, 'complete' => false, 'status' => 'BELUM_DIMULAI'];
$isDraft = ($periode ?? null) && $periode->status === 'DRAFT';
$isFinal = ($periode ?? null) && $periode->status === 'FINAL';
?>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card bg-primary-subtle border-0">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Total Aset Aktif</h6>
                <h3 class="fw-bold"><?= $s['totalAset'] ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-<?= $s['progress'] == 100 ? 'success' : 'warning' ?>-subtle border-0">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Progress Audit</h6>
                <h3 class="fw-bold"><?= $s['auditedCount'] ?> / <?= $s['totalAset'] ?> <small class="fs-6">(<?= $s['progress'] ?>%)</small></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info-subtle border-0">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Existence (70%)</h6>
                <h3 class="fw-bold"><?= $s['existence'] !== null ? number_format($s['existence'], 2) : '-' ?></h3>
                <small class="text-muted"><?= $s['totalDitemukan'] ?> / <?= $s['totalTerdaftar'] ?> unit</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success-subtle border-0">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">KPI FINAL</h6>
                <h3 class="fw-bold <?= $s['final'] !== null ? ($s['final'] >= 90 ? 'text-success' : ($s['final'] >= 75 ? 'text-warning' : 'text-danger')) : '' ?>">
                    <?= $s['final'] !== null ? number_format($s['final'], 2) : ($isFinal ? 'Belum Diaudit' : 'DRAFT') ?>
                </h3>
                <small class="text-muted">Existence×70% + Maintenance×30%</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0">Audit Aset — <?= date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun)) ?></h5>
        <div>
            <?php if ($isDraft && $s['complete']) : ?>
                <form method="post" action="<?= base_url('penilaian/kpi/kontrol_aset/finalize') ?>" class="d-inline" onsubmit="return confirm('Finalisasi audit? Setelah FINAL, KPI akan dihitung & tidak bisa edit lagi kecuali reopen.');">
                    <input type="hidden" name="unit" value="<?= $unitId ?>">
                    <input type="hidden" name="bulan" value="<?= (int)$bulan ?>">
                    <input type="hidden" name="tahun" value="<?= (int)$tahun ?>">
                    <input type="hidden" name="tanggal_audit" value="<?= sprintf('%04d-%02d-%02d', $tahun, $bulan, min((int) date('j'), (int) date('t', strtotime(sprintf('%04d-%02d-01', $tahun, $bulan))))) ?>">
                    <button type="submit" class="btn btn-success btn-sm">
                        <iconify-icon icon="solar:check-circle-bold" class="me-1"></iconify-icon>Finalisasi Audit (FINAL)
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($isFinal) : ?>
                <form method="post" action="<?= base_url('penilaian/kpi/kontrol_aset/reopen') ?>" class="d-inline" onsubmit="return confirm('Buka kembali audit FINAL? KPI akan hilang sampai difinalisasi ulang.');">
                    <input type="hidden" name="unit" value="<?= $unitId ?>">
                    <input type="hidden" name="bulan" value="<?= (int)$bulan ?>">
                    <input type="hidden" name="tahun" value="<?= (int)$tahun ?>">
                    <button type="submit" class="btn btn-warning btn-sm">
                        <iconify-icon icon="solar:restart-bold" class="me-1"></iconify-icon>Reopen (DRAFT)
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if ($s['totalAset'] === 0) : ?>
            <div class="alert alert-light border mb-0">Belum ada aset master aktif untuk unit ini. Hubungi Admin Center untuk menambah aset master.</div>
        <?php else : ?>
            <form method="post" action="<?= base_url('penilaian/kpi/kontrol_aset/save') ?>" id="formAudit">
                <input type="hidden" name="unit" value="<?= $unitId ?>">
                <input type="hidden" name="bulan" value="<?= (int)$bulan ?>">
                <input type="hidden" name="tahun" value="<?= (int)$tahun ?>">
                <input type="hidden" name="tanggal_audit" value="<?= sprintf('%04d-%02d-%02d', $tahun, $bulan, min((int) date('j'), (int) date('t', strtotime(sprintf('%04d-%02d-01', $tahun, $bulan))))) ?>">
                <?php if (!$isFinal) : ?>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <iconify-icon icon="solar:diskette-bold" class="me-1"></iconify-icon>Simpan Draft
                        </button>
                        <span class="text-muted small ms-2">Simpan progress audit (bisa edit lagi sebelum FINAL).</span>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-sm align-middle table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th width="120">Kode</th>
                                <th>Aset</th>
                                <th class="text-center" width="80">Qty<br>Terdaftar</th>
                                <th class="text-center" width="80">Qty<br>Ditemukan</th>
                                <th class="text-center" width="60">Hilang</th>
                                <th width="150">Kondisi<br><small class="fw-normal">(info saja)</small></th>
                                <th width="120">Perawatan<br><small class="fw-normal">(KPI 30%)</small></th>
                                <th width="180">Keterangan</th>
                                <th class="text-center" width="90">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assets as $a) : ?>
                                <?php
                                $audited = $a['audited'];
                                $hilang = $a['hilang'];
                                $disabled = $isFinal ? ' disabled' : '';
                                ?>
                                <tr>
                                    <td class="fw-semibold"><?= esc($a['kode_aset']) ?></td>
                                    <td><?= esc($a['asset']) ?></td>
                                    <td class="text-center fw-bold"><?= $a['quantity'] ?></td>
                                    <td class="text-center">
                                        <input type="number" min="0" class="form-control form-control-sm text-center audit-ditemukan" style="width:60px; display:inline-block;"
                                            name="aset[<?= $a['id'] ?>][ditemukan]"
                                            value="<?= $audited && $a['ditemukan'] !== null ? $a['ditemukan'] : '' ?>"
                                            data-qty="<?= $a['quantity'] ?>"
                                            data-id="<?= $a['id'] ?>"
                                            <?= $disabled ?>>
                                    </td>
                                    <td class="text-center hilang-<?= $a['id'] ?> <?= $hilang > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                                        <?= $audited && $a['ditemukan'] !== null ? $hilang : '-' ?>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="aset[<?= $a['id'] ?>][kondisi]" <?= $disabled ?>>
                                            <option value="">- pilih -</option>
                                            <?php foreach ($kondisiList as $k) : ?>
                                                <option value="<?= $k ?>" <?= $a['kondisi'] === $k ? 'selected' : '' ?>><?= $k ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="aset[<?= $a['id'] ?>][perawatan]" <?= $disabled ?>>
                                            <option value="">- pilih -</option>
                                            <?php foreach ($perawatanList as $p) : ?>
                                                <option value="<?= $p ?>" <?= $a['perawatan'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" name="aset[<?= $a['id'] ?>][keterangan]"
                                            value="<?= esc($a['keterangan']) ?>" placeholder="catatan audit" <?= $disabled ?>>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($audited && $a['ditemukan'] !== null && $a['perawatan'] !== '') : ?>
                                            <span class="badge bg-success-subtle text-success">Lengkap</span>
                                        <?php elseif ($audited && $a['ditemukan'] !== null) : ?>
                                            <span class="badge bg-warning-subtle text-warning">Qty saja</span>
                                        <?php else : ?>
                                            <span class="badge bg-light text-muted">Belum</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    // Hitung hilang otomatis saat Qty Ditemukan berubah.
    document.querySelectorAll('.audit-ditemukan').forEach(function(inp) {
        inp.addEventListener('input', function() {
            var terdaftar = parseInt(inp.getAttribute('data-qty')) || 0;
            var ditemukan = parseInt(inp.value) || 0;
            var hilang = terdaftar - ditemukan;
            var id = inp.getAttribute('data-id');
            var td = document.querySelector('.hilang-' + id);
            if (td) {
                td.textContent = inp.value === '' ? '-' : hilang;
                if (hilang > 0) {
                    td.classList.add('text-danger', 'fw-bold');
                    td.classList.remove('text-muted');
                } else {
                    td.classList.remove('text-danger', 'fw-bold');
                    td.classList.add('text-muted');
                }
            }
        });
    });
</script>
