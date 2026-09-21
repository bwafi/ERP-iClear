<?php
$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$unitMap = [];
foreach (($unit ?? []) as $u) {
    $unitMap[(int) $u->idunit] = $u->NAMA_UNIT;
}
$isLintas = in_array((int) session('ID_JABATAN'), [0, 1, 2, 34], true);
?>

<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Konfirmasi Terima Mutasi</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('mutasi_stok') ?>">Mutasi Stok</a>
                </li>
                <li class="breadcrumb-item active">Konfirmasi Terima</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session('sukses')) : ?>
    <div class="alert alert-success py-2"><?= esc(session('sukses')) ?></div>
<?php endif; ?>
<?php if (session('gagal')) : ?>
    <div class="alert alert-danger py-2"><?= esc(session('gagal')) ?></div>
<?php endif; ?>

<div class="card shadow-none border">
    <div class="card-header bg-transparent">
        <h5 class="mb-0"><?= $isLintas ? 'Semua Mutasi Masuk' : 'Mutasi Masuk Untuk Unit Saya' ?></h5>
    </div>
    <div class="card-body table-responsive">
        <?php if (empty($items)) : ?>
            <p class="text-muted mb-0">Belum ada mutasi masuk.</p>
        <?php else : ?>
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>No. Nota</th>
                        <th>Tgl Kirim</th>
                        <th>Pengirim</th>
                        <th>Penerima</th>
                        <th class="text-end">Nilai</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $m) : ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($m->no_nota_mutasi) ?><br>
                                <small class="text-muted">#<?= $m->idmutasi ?></small>
                            </td>
                            <td><?= esc($m->tanggal_kirim) ?></td>
                            <td><?= esc($m->nama_unit_kirim) ?></td>
                            <td><?= esc($m->nama_unit_terima) ?></td>
                            <td class="text-end fw-semibold"><?= $rp($m->total) ?></td>
                            <td>
                                <?php if ($m->status === '1') : ?>
                                    <span class="badge bg-success-subtle text-success">Diterima</span>
                                    <small class="d-block text-muted"><?= esc($m->tanggal_terima ?: '-') ?></small>
                                <?php else : ?>
                                    <span class="badge bg-warning-subtle text-warning">Belum diterima</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($m->status !== '1') : ?>
                                    <form method="post" class="d-inline"
                                        action="<?= base_url('mutasi_stok/terima/' . $m->idmutasi) ?>"
                                        onsubmit="return confirm('Konfirmasi bahwa mutasi <?= esc($m->no_nota_mutasi) ?> dari <?= esc($m->nama_unit_kirim) ?> benar diterima? Hutang/Piutang antar unit akan dibuat otomatis (jatuh tempo +3 hari).')">
                                        <button type="submit" class="btn btn-sm btn-success">Terima</button>
                                    </form>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-light"
                                    data-bs-toggle="collapse" data-bs-target="#detail-<?= $m->idmutasi ?>">Detail</button>
                            </td>
                        </tr>
                        <tr class="collapse" id="detail-<?= $m->idmutasi ?>">
                            <td colspan="7" class="ps-4 py-1">
                                <table class="table table-sm table-striped align-middle mb-1 w-50">
                                    <thead>
                                        <tr><th>Barang</th><th class="text-end">Jml Kirim</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($m->detail as $d) : ?>
                                            <tr>
                                                <td><?= esc($d->nama_barang) ?></td>
                                                <td class="text-end"><?= (int) $d->jumlah_kirim ?></td>
                                                <td class="text-end"><?= $rp($d->harga_mutasi) ?></td>
                                                <td class="text-end"><?= $rp($d->nilai ?? 0) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <small class="text-muted">Total: <strong><?= $rp($m->total) ?></strong></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>