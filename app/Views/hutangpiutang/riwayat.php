<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Riwayat Pembayaran</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('hutangpiutang/dashboard') ?>">Hutang Piutang</a></li>
                <li class="breadcrumb-item active">Riwayat</li>
            </ol>
        </nav>
    </div>
</div>

<?php
$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$f = $filters ?? [];
$labelSumber = [
    'pembelian' => 'Hutang Supplier',
    'piutang_pelanggan' => 'Piutang Pelanggan',
    'kasbon' => 'Kasbon Pegawai',
    'piutang_legacy' => 'Piutang Pegawai',
];
?>

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get" action="<?= base_url('hutangpiutang/riwayat') ?>">
            <div class="col-md-3">
                <label class="form-label mb-1">Bulan</label>
                <input type="month" name="bulan" class="form-control form-control-sm" value="<?= esc($f['bulan'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Jenis</label>
                <select name="jenis" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="hutang" <?= ($f['jenis'] ?? '') === 'hutang' ? 'selected' : '' ?>>Hutang</option>
                    <option value="piutang" <?= ($f['jenis'] ?? '') === 'piutang' ? 'selected' : '' ?>>Piutang</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Unit</label>
                <select name="unit_id" class="form-select form-select-sm">
                    <option value="">Semua Unit</option>
                    <?php foreach (($units ?? []) as $u) : ?>
                        <option value="<?= (int) $u->idunit ?>" <?= ($unit_id ?? null) == $u->idunit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kode</th>
                        <th>Pihak</th>
                        <th>Jenis</th>
                        <th>Sumber</th>
                        <th class="text-end">Jumlah Bayar</th>
                        <th class="text-end">Sisa</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)) : ?>
                        <tr><td colspan="8" class="text-center text-muted">Belum ada pembayaran.</td></tr>
                    <?php else : foreach ($rows as $row) : ?>
                        <tr>
                            <td><?= $row['tanggal'] ? date('d-m-Y', strtotime($row['tanggal'])) : '-' ?></td>
                            <td class="fw-semibold"><?= esc($row['kode'] ?? '-') ?></td>
                            <td><?= esc($row['nama_pihak'] ?? '-') ?></td>
                            <td><span class="badge bg-<?= ($row['jenis'] ?? '') === 'hutang' ? 'danger' : 'success' ?>"><?= esc($row['jenis'] ?? '-') ?></span></td>
                            <td><?= esc($labelSumber[$row['sumber_tipe']] ?? $row['sumber_tipe'] ?? '-') ?></td>
                            <td class="text-end fw-semibold"><?= $rp($row['jumlah_bayar']) ?></td>
                            <td class="text-end"><?= $row['sisa'] === null ? '-' : $rp($row['sisa']) ?></td>
                            <td><?= esc($row['keterangan'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
