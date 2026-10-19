<div class="card border shadow-none">
    <div class="card-body">
        <a href="<?= base_url('produk') ?>" class="btn btn-sm btn-outline-secondary mb-3">Kembali ke master barang</a>
        <h4>Review Tipe Item</h4>
        <p>Daftar baca-saja. Usulan tidak mengubah master. Cocokkan item ambigu dengan bukti fisik dan keputusan tim sebelum mengisi tipe di master.</p>
        <p><?= number_format($total, 0, ',', '.') ?> item aktif · Halaman <?= $page ?> dari <?= max(1, (int) ceil($total / 100)) ?></p>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead><tr><th>ID</th><th>Nama</th><th>Kategori</th><th>Tipe saat ini</th><th class="text-end">Stok</th><th class="text-end">Pembelian</th><th class="text-end">Dipakai service</th><th class="text-end">Terjual</th><th>Usulan</th><th>Alasan / status</th></tr></thead>
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
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex gap-2">
            <?php if ($page > 1): ?><a class="btn btn-outline-primary" href="<?= base_url('produk/review-tipe?page=' . ($page - 1)) ?>">Sebelumnya</a><?php endif; ?>
            <?php if ($page * 100 < $total): ?><a class="btn btn-outline-primary" href="<?= base_url('produk/review-tipe?page=' . ($page + 1)) ?>">Berikutnya</a><?php endif; ?>
        </div>
    </div>
</div>
