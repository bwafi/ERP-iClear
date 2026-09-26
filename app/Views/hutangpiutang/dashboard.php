<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Dashboard Hutang Piutang</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Hutang Piutang</a></li>
                <li class="breadcrumb-item active">Dashboard</li>
            </ol>
        </nav>
    </div>
</div>

<?php
$r = $ringkasan ?? [];
$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
?>

<div class="row">
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-1">Total Hutang (Supplier)</p>
                <h4 class="fw-semibold mb-0"><?= $rp($r['total_hutang_supplier'] ?? 0) ?></h4>
                <small class="text-muted"><?= (int) ($r['jml_hutang'] ?? 0) ?> transaksi</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-1">Total Piutang Pelanggan</p>
                <h4 class="fw-semibold mb-0"><?= $rp($r['total_piutang_pelanggan'] ?? 0) ?></h4>
                <small class="text-muted"><?= (int) ($r['jml_piutang_pelanggan'] ?? 0) ?> transaksi</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-1">Total Kasbon Pegawai</p>
                <h4 class="fw-semibold mb-0"><?= $rp($r['total_kasbon'] ?? 0) ?></h4>
                <small class="text-muted"><?= (int) ($r['jml_kasbon'] ?? 0) ?> transaksi</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-1">Total Piutang Pegawai (Legacy)</p>
                <h4 class="fw-semibold mb-0"><?= $rp($r['total_piutang_legacy'] ?? 0) ?></h4>
                <small class="text-muted"><?= (int) ($r['jml_piutang_legacy'] ?? 0) ?> transaksi</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-1">Total Hutang Lainnya (Teknisi/Manual)</p>
                <h4 class="fw-semibold mb-0"><?= $rp($r['total_hutang_lain'] ?? 0) ?></h4>
                <small class="text-muted"><?= (int) ($r['jml_hutang_lain'] ?? 0) ?> transaksi</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-1">Total Piutang Supplier (Kelebihan/Retur)</p>
                <h4 class="fw-semibold mb-0"><?= $rp($r['total_piutang_supplier'] ?? 0) ?></h4>
                <small class="text-muted"><?= (int) ($r['jml_piutang_supplier'] ?? 0) ?> transaksi</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-1">Total Piutang Lainnya</p>
                <h4 class="fw-semibold mb-0"><?= $rp($r['total_piutang_lain'] ?? 0) ?></h4>
                <small class="text-muted"><?= (int) ($r['jml_piutang_lain'] ?? 0) ?> transaksi</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-semibold mb-3">Ringkasan Status</h5>
                <table class="table table-sm mb-0">
                    <tr><td>Belum Lunas</td><td class="text-end fw-semibold"><?= (int) ($r['belum_lunas'] ?? 0) ?></td></tr>
                    <tr><td>Sebagian</td><td class="text-end fw-semibold"><?= (int) ($r['sebagian'] ?? 0) ?></td></tr>
                    <tr><td>Lunas</td><td class="text-end fw-semibold"><?= (int) ($r['lunas'] ?? 0) ?></td></tr>
                    <tr><td>Jatuh Tempo Hari Ini</td><td class="text-end fw-semibold text-warning"><?= (int) ($r['jatuh_tempo'] ?? 0) ?></td></tr>
                    <tr><td>Terlambat</td><td class="text-end fw-semibold text-danger"><?= (int) ($r['terlambat'] ?? 0) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-semibold mb-0">Transaksi Bulan <?= esc($bulan ?? '') ?></h5>
                    <form class="d-flex gap-2" method="get" action="<?= base_url('hutangpiutang/dashboard') ?>">
                        <input type="month" name="bulan" class="form-control form-control-sm" value="<?= esc($bulan ?? '') ?>">
                        <select name="unit_id" class="form-select form-select-sm">
                            <option value="">Semua Unit</option>
                            <?php foreach (($units ?? []) as $u) : ?>
                                <option value="<?= (int) $u->idunit ?>" <?= ($unit_id ?? null) == $u->idunit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-primary">Filter</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Kode</th><th>Pihak</th><th>Jenis</th><th class="text-end">Sisa</th><th>Status</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent)) : ?>
                                <tr><td colspan="6" class="text-center text-muted">Tidak ada data pada periode ini.</td></tr>
                            <?php else : foreach ($recent as $row) : ?>
                                <tr>
                                    <td><?= esc($row['kode']) ?></td>
                                    <td><?= esc($row['nama_pihak']) ?></td>
                                    <td><span class="badge bg-<?= $row['jenis'] === 'hutang' ? 'danger' : 'success' ?>"><?= esc($row['jenis']) ?></span></td>
                                    <td class="text-end"><?= $rp($row['sisa']) ?></td>
                                    <td><?= esc(\App\Services\Finance\HutangPiutangService::labelStatus($row['status'])) ?></td>
                                    <td class="text-end"><a href="<?= base_url('hutangpiutang/detail/' . $row['id']) ?>" class="btn btn-sm btn-light">Detail</a></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
