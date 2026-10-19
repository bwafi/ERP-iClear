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
        <p>Nilai dari riwayat mutasi: <?= $valuation['valid'] ? 'Rp ' . number_format((int) $valuation['value'], 0, ',', '.') : 'Belum dapat dihitung; riwayat memiliki HPP kosong atau saldo sementara negatif' ?>.</p>
        <?php if ($difference != 0): ?>
            <div class="alert alert-danger">Saldo mutasi tidak sama dengan stok tersedia; periksa sumber transaksi sebelum memakai angka persediaan.</div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead><tr><th>Tanggal/waktu</th><th>Sumber</th><th>Referensi</th><th>Petugas ID</th><th class="text-end">Masuk</th><th class="text-end">Keluar</th><th class="text-end">Saldo</th><th class="text-end">HPP/unit sumber</th><th class="text-end">HPP rata-rata</th><th class="text-end">Nilai stok</th></tr></thead>
                <tbody>
                <?php foreach ($movements as $movement): ?>
                    <tr>
                        <td><?= esc($movement['tanggal'] ?? '-') ?></td>
                        <td><?= esc($movement['sumber']) ?></td>
                        <td><a href="<?= base_url($sourcePaths[$movement['sumber']] ?? 'kartu_stok') ?>"><?= esc($movement['sumber']) ?> #<?= (int) $movement['referensi_id'] ?></a></td>
                        <td><?= $movement['petugas_id'] === null ? '-' : (int) $movement['petugas_id'] ?></td>
                        <td class="text-end"><?= number_format((float) $movement['masuk'], 0, ',', '.') ?></td>
                        <td class="text-end"><?= number_format((float) $movement['keluar'], 0, ',', '.') ?></td>
                        <td class="text-end"><?= number_format((float) $movement['saldo'], 0, ',', '.') ?></td>
                        <td class="text-end"><?= $movement['hpp_satuan'] === null ? '-' : 'Rp ' . number_format((float) $movement['hpp_satuan'], 0, ',', '.') ?></td>
                        <td class="text-end"><?= $movement['hpp_rata_rata'] === null ? '-' : 'Rp ' . number_format((int) $movement['hpp_rata_rata'], 0, ',', '.') ?></td>
                        <td class="text-end"><?= $movement['saldo_nilai'] === null ? '-' : 'Rp ' . number_format((int) $movement['saldo_nilai'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <h5 class="mt-4">Jejak ledger persediaan</h5>
        <p class="text-muted">Entri pembalikan tetap tercatat di sini; tabel mutasi sumber di atas hanya menampilkan transaksi yang masih aktif. Saldo ledger harus direkonsiliasi sebelum dipakai sebagai nilai persediaan.</p>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead><tr><th>Waktu</th><th>Peristiwa</th><th>Referensi sumber</th><th>Entri dibalik</th><th class="text-end">Perubahan jumlah</th><th class="text-end">Perubahan nilai</th><th class="text-end">Saldo jumlah</th><th class="text-end">Saldo nilai</th></tr></thead>
                <tbody>
                <?php foreach ($ledgerEntries as $entry): ?>
                    <tr>
                        <td><?= esc($entry['created_at']) ?></td>
                        <td><?= esc($entry['source_event']) ?></td>
                        <td><?= esc($entry['source_type']) ?> #<?= esc($entry['source_id']) ?></td>
                        <td><?= $entry['reverses_id'] === null ? '-' : (int) $entry['reverses_id'] ?></td>
                        <td class="text-end"><?= number_format((int) $entry['quantity'], 0, ',', '.') ?></td>
                        <td class="text-end">Rp <?= number_format((int) $entry['value'], 0, ',', '.') ?></td>
                        <td class="text-end"><?= number_format((int) $entry['balance_quantity'], 0, ',', '.') ?></td>
                        <td class="text-end">Rp <?= number_format((int) $entry['balance_value'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($ledgerEntries === []): ?>
                    <tr><td colspan="8">Belum ada entri ledger untuk barang dan unit ini.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
