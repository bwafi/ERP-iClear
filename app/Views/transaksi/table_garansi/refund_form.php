<?php
$refunded = $refunded ?? false;
$bank = $bank ?? [];
$sparepart = $sparepart ?? [];
$totalDibayar = (int) ($service->dp_bayar ?? 0) + (int) ($service->bayar ?? 0);
$rupiah = static fn ($nilai) => 'Rp ' . number_format((float) $nilai, 0, ',', '.');
?>
<?php if ($refunded): ?>
    <div class="alert alert-info mb-0">
        Refund untuk service <strong><?= esc($service->no_service ?? '-') ?></strong> sudah dicatat
        pada <?= esc($service->refund_garansi_tanggal ? date('d-m-Y', strtotime((string) $service->refund_garansi_tanggal)) : '-') ?>.
    </div>
<?php elseif ($totalDibayar <= 0): ?>
    <div class="alert alert-info mb-0">Service ini tidak punya pembayaran yang bisa direfund.</div>
<?php else: ?>
    <form id="refundGaransiForm" method="post" action="<?= base_url('garansi_service/refund') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="idservice" value="<?= (int) $service->idservice ?>">

        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <div class="text-muted small">No. Service</div>
                <div class="fw-semibold"><?= esc($service->no_service ?? '-') ?></div>
            </div>
            <div class="col-sm-6">
                <div class="text-muted small">Pelanggan</div>
                <div class="fw-semibold"><?= esc($service->nama ?? $service->nama_pelanggan ?? '-') ?></div>
            </div>
            <div class="col-sm-6">
                <div class="text-muted small">Nominal refund (DP + pembayaran)</div>
                <div class="fw-semibold"><?= esc($rupiah($totalDibayar)) ?></div>
            </div>
        </div>

        <h6>Metode Refund</h6>
        <div class="d-flex gap-3 mb-3">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="metode" id="refundMetodeTunai" value="tunai" checked>
                <label class="form-check-label" for="refundMetodeTunai">Tunai</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="metode" id="refundMetodeBank" value="bank" <?= $bank === [] ? 'disabled' : '' ?>>
                <label class="form-check-label" for="refundMetodeBank">Transfer bank</label>
            </div>
        </div>

        <div id="refundBankFields" class="row g-3 mb-3 d-none">
            <div class="col-sm-6">
                <label class="form-label" for="refundIdbank">Rekening</label>
                <select class="form-select" name="idbank" id="refundIdbank">
                    <?php foreach ($bank as $rekening): ?>
                        <option value="<?= esc((string) $rekening->idbank) ?>">
                            <?= esc(trim(($rekening->nama_bank ?? '') . ' - ' . ($rekening->norek ?? '') . ' (' . ($rekening->atas_nama ?? '') . ')')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="refundReferensi">Referensi transfer</label>
                <input class="form-control" type="text" name="referensi" id="refundReferensi" maxlength="100">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="refundAlasan">Alasan refund</label>
            <textarea class="form-control" name="alasan" id="refundAlasan" rows="2" maxlength="255" required></textarea>
        </div>

        <?php if ($sparepart !== []): ?>
            <h6>Kondisi Sparepart Pengganti</h6>
            <div class="table-responsive mb-3">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Qty</th>
                            <th>Baik (kembali ke stok)</th>
                            <th>Rusak</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sparepart as $item): ?>
                            <?php $id = (int) $item->idservice_sparepart; ?>
                            <tr>
                                <td><?= esc(($item->kode_barang ?? '') . ' - ' . ($item->nama_barang ?? '')) ?></td>
                                <td><?= (int) $item->jumlah_tambahan_garansi ?></td>
                                <td>
                                    <input class="form-check-input" type="radio" name="kondisi[<?= $id ?>]" value="baik" required>
                                </td>
                                <td>
                                    <input class="form-check-input" type="radio" name="kondisi[<?= $id ?>]" value="rusak" required>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="rsg-btn rsg-btn--ghost" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="rsg-btn rsg-btn--claim">Catat Refund</button>
        </div>
    </form>
<?php endif; ?>
