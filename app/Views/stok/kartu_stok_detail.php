<?php
$stock = (float) $item->stok_akhir;
$difference = $calculated - $stock;
$sourcePaths = [
    'Stok Awal' => 'stok_awal',
    'Pembelian' => 'riwayat_pembelian',
    'Mutasi Masuk' => 'riwayat_mutasi',
    'Retur Pelanggan' => 'riwayat_retur_penjualan',
    'Penjualan/Service' => 'riwayat_penjualan',
    'Mutasi Keluar' => 'riwayat_mutasi',
    'Barang Rusak' => 'barang_rusak',
    'Barang Rusak Klaim (tanpa mutasi)' => 'barang_rusak',
    'Retur Supplier' => 'riwayat_retur_pembelian',
    'Retur Barang Rusak (tanpa mutasi)' => 'riwayat_retur_pembelian',
    'Pengganti Garansi' => 'garansi_service',
];
?>
<div class="card border shadow-none">
    <div class="card-body">
        <a href="<?= base_url('kartu_stok') ?>" class="btn btn-sm btn-outline-secondary mb-3">Kembali ke Kartu Stok</a>
        <h4>Kartu Stok: <?= esc($item->kode_barang) ?> — <?= esc($item->nama_barang) ?></h4>
        <p>Unit: <?= esc($item->nama_unit) ?> · Tipe master: <?= esc($item->jenis_item ?: 'Perlu klasifikasi') ?></p>
        <p>Saldo dari mutasi: <strong><?= number_format($calculated, 0, ',', '.') ?></strong> · Stok tersedia: <strong><?= number_format($stock, 0, ',', '.') ?></strong> · Selisih: <strong><?= number_format($difference, 0, ',', '.') ?></strong></p>
        <p>Nilai stok indikatif (saldo × HPP master): <?= $item->hpp === null ? 'HPP belum tersedia' : 'Rp ' . number_format($stock * (float) $item->hpp, 0, ',', '.') ?>. Nilai ini bukan pengganti rekonsiliasi jurnal persediaan.</p>
        <?php if ($difference != 0): ?>
            <div class="alert alert-danger">Saldo mutasi tidak sama dengan stok tersedia; periksa sumber transaksi sebelum memakai angka persediaan.</div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead><tr><th>Tanggal</th><th>Sumber</th><th>Referensi</th><th class="text-end">Masuk</th><th class="text-end">Keluar</th><th class="text-end">Saldo</th><th class="text-end">HPP/unit</th></tr></thead>
                <tbody>
                <?php foreach ($movements as $movement): ?>
                    <tr>
                        <td><?= esc($movement['tanggal'] ?? '-') ?></td>
                        <td><?= esc($movement['sumber']) ?></td>
                        <td><a href="<?= base_url($sourcePaths[$movement['sumber']] ?? 'kartu_stok') ?>"><?= esc($movement['sumber']) ?> #<?= (int) $movement['referensi_id'] ?></a></td>
                        <td class="text-end"><?= number_format((float) $movement['masuk'], 0, ',', '.') ?></td>
                        <td class="text-end"><?= number_format((float) $movement['keluar'], 0, ',', '.') ?></td>
                        <td class="text-end"><?= number_format((float) $movement['saldo'], 0, ',', '.') ?></td>
                        <td class="text-end"><?= $movement['hpp_satuan'] === null ? '-' : 'Rp ' . number_format((float) $movement['hpp_satuan'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
