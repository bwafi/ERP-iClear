<div class="card border shadow-none">
    <div class="card-body">
        <a href="<?= base_url('produk') ?>" class="btn btn-sm btn-outline-secondary mb-3">Kembali ke master barang</a>
        <h4>Review Tipe Item</h4>
        <p>Usulan tidak otomatis mengubah master. Administrator dapat menetapkan satu item setelah memeriksa barang dan bukti transaksi. Tidak ada klasifikasi massal atau perubahan ID/HPP historis.</p>
        <?php if ($returnServiceId > 0): ?><a class="btn btn-outline-primary mb-3" href="<?= base_url('garansi_service/klaim/' . $returnServiceId) ?>">Kembali ke formulir klaim</a><?php endif; ?>
        <form method="get" class="d-flex flex-wrap gap-2 mb-3">
            <input type="number" class="form-control" style="max-width:240px" name="item_id" min="1" placeholder="Cari ID master" value="<?= $itemId ?: '' ?>">
            <input type="hidden" name="service_id" value="<?= $returnServiceId ?>">
            <button class="btn btn-outline-primary">Cari</button>
            <a class="btn btn-light" href="<?= base_url('produk/review-tipe') ?>">Semua item</a>
        </form>
        <p><?= number_format($total, 0, ',', '.') ?> item aktif · Halaman <?= $page ?> dari <?= max(1, (int) ceil($total / 100)) ?></p>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead><tr><th>ID</th><th>Nama</th><th>Kategori</th><th>Tipe saat ini</th><th class="text-end">Stok</th><th class="text-end">Pembelian</th><th class="text-end">Dipakai service</th><th class="text-end">Terjual</th><th>Usulan</th><th>Alasan / status</th><th>Persetujuan Administrator</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= (int) $item['idbarang'] ?><br><small><?= esc($item['kode_barang']) ?></small></td>
                        <td><?= esc($item['nama_barang']) ?></td>
                        <td><?= esc($item['nama_kategori'] ?? '-') ?></td>
                        <td><?= esc($item['jenis_item'] ?: 'Belum diisi') ?></td>
                        <td class="text-end"><?= number_format((float) $item['stok'], 0, ',', '.') ?></td>
                        <td class="text-end"><?= number_format((float) $item['dibeli'], 0, ',', '.') ?> (<?= (int) $item['nota_pembelian'] ?> nota)</td>
                        <td class="text-end"><?= number_format((float) $item['dipakai_service'], 0, ',', '.') ?></td>
                        <td class="text-end"><?= number_format((float) $item['terjual'], 0, ',', '.') ?></td>
                        <td><?= esc($item['usulan']) ?></td>
                        <td><?= $item['ambigu'] ? '<span class="badge bg-warning">Ambigu</span> ' : '' ?><?= esc($item['alasan']) ?></td>
                        <td style="min-width:260px;white-space:normal">
                            <?php if (! in_array($item['jenis_item'], ['fisik', 'jasa'], true)): ?>
                                <form method="post" action="<?= base_url('produk/review-tipe/simpan') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="item_id" value="<?= (int) $item['idbarang'] ?>">
                                    <input type="hidden" name="service_id" value="<?= $returnServiceId ?>">
                                    <select name="jenis_item" class="form-select mb-2" aria-label="Tipe yang disetujui" required><option value="">Pilih setelah pemeriksaan</option><option value="fisik">Barang Fisik</option><option value="jasa">Jasa</option></select>
                                    <textarea name="alasan" class="form-control mb-2" minlength="10" maxlength="500" placeholder="Bukti: barang fisik/nota yang sudah diperiksa" aria-label="Bukti pemeriksaan" required></textarea>
                                    <label class="d-block mb-2"><input type="checkbox" name="confirm_type" value="yes" required> Saya sudah memeriksa item ini, bukan hanya menebak dari nama.</label>
                                    <button class="btn btn-sm btn-primary">Simpan Tipe Item Ini</button>
                                </form>
                            <?php else: ?>Sudah ditetapkan<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex gap-2">
            <?php if ($page > 1): ?><a class="btn btn-outline-primary" href="<?= base_url('produk/review-tipe?page=' . ($page - 1) . '&item_id=' . $itemId . '&service_id=' . $returnServiceId) ?>">Sebelumnya</a><?php endif; ?>
            <?php if ($page * 100 < $total): ?><a class="btn btn-outline-primary" href="<?= base_url('produk/review-tipe?page=' . ($page + 1) . '&item_id=' . $itemId . '&service_id=' . $returnServiceId) ?>">Berikutnya</a><?php endif; ?>
        </div>
    </div>
</div>
