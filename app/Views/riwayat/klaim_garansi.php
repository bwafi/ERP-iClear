<div class="card"><div class="card-body">
    <h4>Klaim Garansi — <?= esc($claimService->no_service) ?></h4>
    <p>Pilih barang yang diklaim, periksa asalnya, lalu pilih sparepart pengganti. Membuka halaman ini belum mengubah stok atau status service.</p>
    <div class="alert alert-warning">Asal barang lama hanya mengikuti batch yang tercatat saat pemasangan. “Belum diketahui” tidak boleh diganti dengan perkiraan supplier. Harga batch tidak mengganti HPP moving average.</div>
    <form action="<?= base_url('claim_garansi') ?>" method="post" id="claim-intake-form">
        <?= csrf_field() ?>
        <input type="hidden" name="idservice" value="<?= (int) $claimService->idservice ?>">
        <h5>1. Sparepart/jasa yang diklaim</h5>
        <?php foreach ($claimOriginals as $original): ?>
            <label class="d-block border rounded p-3 mb-2" style="overflow-wrap:anywhere">
                <input type="radio" name="original_part_id" value="<?= (int) $original['idservice_sparepart'] ?>"
                    data-kind="<?= esc($original['jenis_item'] ?? '', 'attr') ?>" data-quantity="<?= (int) $original['jumlah'] ?>" required <?= ! empty($original['ambiguous_replacement']) ? 'disabled' : '' ?>>
                <strong><?= esc($original['kode_barang'] . ' — ' . $original['nama_barang']) ?></strong>
                <span class="d-block">Jumlah pada invoice: <?= (int) $original['jumlah'] ?> · Tipe: <?= esc($original['jenis_item'] ?: 'Perlu klasifikasi') ?></span>
                <span class="d-block">Supplier asal: <?= esc($original['nama_suplier'] ?: 'Belum diketahui') ?></span>
                <span class="d-block">Nota/batch asal: <?= esc($original['no_nota_supplier'] ?: ($original['no_batch'] ?: 'Belum diketahui')) ?></span>
                <span class="d-block">ID batch pembelian: <?= $original['source_purchase_detail_id'] ? (int) $original['source_purchase_detail_id'] : 'Belum diketahui' ?></span>
                <?php if (! empty($original['ambiguous_replacement'])): ?><span class="text-danger d-block">Histori pengganti memiliki beberapa barang. Perlu pemeriksaan relasi asal sebelum klaim ulang; jangan menebak.</span><?php endif; ?>
            </label>
        <?php endforeach; ?>
        <?php if (! $claimOriginals): ?>
            <div class="alert alert-warning">Invoice ini belum memiliki baris sparepart/jasa asal yang dapat ditautkan. Jangan membuat relasi asal secara perkiraan.</div>
        <?php endif; ?>
        <label for="claim-quantity" class="form-label">Jumlah yang diklaim</label>
        <input class="form-control mb-3" type="number" min="1" step="1" value="1" id="claim-quantity" name="claim_quantity" required>
        <label for="claim-reason" class="form-label">Keluhan/alasan klaim</label>
        <textarea class="form-control mb-3" id="claim-reason" name="claim_reason" maxlength="1000" required></textarea>
        <div id="replacement-fields">
            <h5>2. Sparepart pengganti</h5>
            <p>Hanya barang bertipe fisik dengan stok tersedia. Jika tidak ditemukan, Administrator perlu meninjau tipe master; jangan membuat ID duplikat.</p>
            <?php if (! $claimReplacements): ?>
                <div class="alert alert-warning">Belum ada barang bertipe Fisik dengan stok tersedia di cabang akun ini.
                    <?php if ($pendingReplacementTypes): ?>Ada <?= count($pendingReplacementTypes) ?> ID dengan stok, tetapi tipenya belum ditetapkan. Minta Administrator memeriksa ID di bawah; jangan menambah ID baru atau stok palsu.<?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($pendingReplacementTypes): ?>
                <details class="border rounded p-3 mb-3"><summary>Barang belum muncul? Lihat <?= count($pendingReplacementTypes) ?> item yang perlu review tipe</summary>
                    <ul class="mt-2 mb-0" style="max-height:260px;overflow:auto">
                        <?php foreach ($pendingReplacementTypes as $pending): ?>
                            <li class="mb-2"><?= esc($pending['kode_barang'] . ' — ' . $pending['nama_barang']) ?> · ID <?= (int) $pending['idbarang'] ?> · Stok <?= (int) $pending['stok_akhir'] ?>
                                <?php if ((int) session('ID_JABATAN') === 1): ?><a href="<?= base_url('produk/review-tipe?item_id=' . (int) $pending['idbarang'] . '&service_id=' . (int) $claimService->idservice) ?>">Periksa dan tetapkan tipe</a><?php else: ?><span>— perlu persetujuan Administrator</span><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
            <label for="replacement-item" class="form-label">Barang pengganti</label>
            <select class="form-select mb-3" name="replacement_id" id="replacement-item" required>
                <option value="">Pilih barang yang diambil</option>
                <?php foreach ($claimReplacements as $replacement): ?>
                    <option value="<?= (int) $replacement['idbarang'] ?>"><?= esc($replacement['kode_barang'] . ' — ' . $replacement['nama_barang']) ?> · Stok <?= (int) $replacement['stok_akhir'] ?></option>
                <?php endforeach; ?>
            </select>
            <label for="replacement-batch" class="form-label">Supplier / nota asal barang pengganti</label>
            <select class="form-select mb-3" name="replacement_batch_id" id="replacement-batch" required><option value="">Pilih barang dahulu</option></select>
            <p class="text-warning" id="replacement-batch-help" aria-live="polite"></p>
            <label for="replacement-quantity" class="form-label">Jumlah pengganti</label>
            <input class="form-control mb-3" type="number" min="1" step="1" value="1" id="replacement-quantity" name="replacement_quantity" required>
            <p>Stok pengganti berkurang sekali saat disimpan. Barang lama tidak dikembalikan ke stok jual. Pencatatan Barang Rusak/retur tetap mengikuti klaim dan asal yang terbukti.</p>
        </div>
        <div id="labor-note" class="alert alert-info" hidden>Klaim jasa dicatat untuk pengerjaan ulang tanpa mengeluarkan stok sparepart.</div>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <a href="<?= base_url('garansi_service') ?>" class="btn btn-light">Batal</a>
            <button type="submit" class="btn btn-primary" <?= ! $claimOriginals ? 'disabled' : '' ?>>Simpan Klaim &amp; Pengganti</button>
        </div>
    </form>
</div></div>
<script>
(() => {
    const replacements = <?= json_encode($claimReplacements, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const item = document.getElementById('replacement-item');
    const batch = document.getElementById('replacement-batch');
    item.addEventListener('change', () => {
        batch.replaceChildren(new Option('Pilih supplier / nota sesuai barang fisik', ''));
        const selected = replacements.find(product => String(product.idbarang) === item.value);
        document.getElementById('replacement-batch-help').textContent = selected && !selected.batches.length
            ? 'Stok ada, tetapi belum ada batch pembelian yang dapat ditelusuri. Jangan pilih supplier perkiraan. Periksa nota asal atau gunakan barang lain dengan batch yang terbukti.' : '';
        for (const source of selected?.batches || []) {
            const remaining = Number(source.available_quantity ?? 0);
            const label = `${source.nama_suplier || 'Supplier tidak tercatat'} / ${source.no_nota_supplier || source.no_batch || '-'} · Batch #${source.iddetail_pembelian} · Sisa ${remaining}`;
            batch.add(new Option(label, source.iddetail_pembelian));
        }
    });
    document.querySelectorAll('[name="original_part_id"]').forEach(input => input.addEventListener('change', () => {
        const labor = input.dataset.kind === 'jasa';
        document.getElementById('claim-quantity').max = input.dataset.quantity;
        document.getElementById('replacement-fields').hidden = labor;
        document.getElementById('labor-note').hidden = !labor;
        document.querySelectorAll('#replacement-fields select, #replacement-fields input').forEach(field => {
            field.disabled = labor;
            field.required = !labor;
        });
    }));
    document.getElementById('claim-intake-form').addEventListener('submit', event => {
        const button = event.currentTarget.querySelector('[type="submit"]');
        button.disabled = true;
        button.textContent = 'Menyimpan klaim dan pengganti…';
    });
})();
</script>
