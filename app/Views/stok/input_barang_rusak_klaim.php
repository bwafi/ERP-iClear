<div class="card"><div class="card-body">
    <h4>Barang Rusak dari Klaim Customer</h4>
    <p>Service <?= esc($origin['no_service']) ?> · Klaim #<?= (int) $origin['claim_id'] ?></p>
    <p>Barang: <strong><?= esc($origin['name']) ?></strong><br>
        Supplier: <?= esc($origin['supplier']) ?><br>
        Nota: <?= esc($origin['invoice']) ?><br>
        Batch: <?= esc($origin['batch'] ?: 'Belum diketahui') ?></p>
    <div class="alert alert-info">Pencatatan ini tidak mengurangi stok jual lagi. Terima barang lama dari customer terlebih dahulu.</div>
    <?php if ($origin['origin_status'] === 'belum_diketahui'): ?>
        <div class="alert alert-warning">Asal belum diketahui. Barang dapat dicatat sebagai rusak, tetapi belum dapat diretur ke supplier sampai bukti asal diperiksa dan disetujui.</div>
    <?php endif ?>
    <p>Sisa yang dapat dicatat rusak: <?= (int) $origin['available'] ?> unit.</p>
    <?php if ($origin['available'] > 0): ?>
    <form method="post" action="<?= base_url('insert_barang_rusak') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="idbarang[]" value="<?= (int) $origin['item_id'] ?>">
        <input type="hidden" name="idpembelian[]" value="<?= (int) $origin['purchase_id'] ?>">
        <input type="hidden" name="no_batch[]" value="<?= esc($origin['batch'], 'attr') ?>">
        <input type="hidden" name="claim_id[]" value="<?= (int) $origin['claim_id'] ?>">
        <input type="hidden" name="asal_dokumen[]" value="<?= esc($origin['origin_status'], 'attr') ?>">
        <label class="form-label">Jumlah barang lama yang diterima</label>
        <input class="form-control mb-3" type="number" name="jumlah_rusak[]" min="1" max="<?= (int) $origin['available'] ?>" value="1" required>
        <label class="form-label">Tanggal diterima</label>
        <input class="form-control mb-3" type="date" name="tanggal_rusak[]" value="<?= date('Y-m-d') ?>" required>
        <label class="form-label">Kondisi barang</label>
        <textarea class="form-control mb-3" name="keterangan[]" required></textarea>
        <button class="btn btn-primary" type="submit">Simpan Barang Rusak dari Klaim</button>
    </form>
    <?php else: ?>
        <div class="alert alert-warning">Semua barang pada klaim ini sudah dicatat. Jangan mencatat ulang.</div>
    <?php endif ?>
</div></div>
