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
            <h6>Kondisi Sparepart <span class="text-danger">*</span></h6>
            <small class="text-muted d-block mb-2">Wajib dipilih untuk setiap baris: baik dikembalikan ke stok, rusak dicatat sebagai barang rusak.</small>
            <div class="table-responsive mb-3">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Jenis</th>
                            <th>Qty</th>
                            <th>HPP</th>
                            <th>Harga Jual</th>
                            <th>Baik (kembali ke stok)</th>
                            <th>Rusak</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sparepart as $item): ?>
                            <?php
                            $id = (int) $item->idservice_sparepart;
                            $qtyNormal = max(0, (int) ($item->jumlah ?? 0));
                            $qtyKlaim = max(0, (int) ($item->jumlah_tambahan_garansi ?? 0));
                            $jenis = [];
                            if ($qtyNormal > 0) {
                                $jenis[] = 'Normal';
                            }
                            if ($qtyKlaim > 0) {
                                $jenis[] = 'Pengganti garansi';
                            }
                            $hpp = $qtyNormal * (int) ($item->hpp_penjualan ?? 0)
                                + $qtyKlaim * (int) ($item->hpp_penjualan_garansi ?? 0);
                            $harga = $qtyNormal * (int) ($item->harga_penjualan ?? 0)
                                + $qtyKlaim * (int) ($item->harga_penjualan_garansi ?? 0);
                            ?>
                            <tr>
                                <td><?= esc(($item->kode_barang ?? '') . ' - ' . ($item->nama_barang ?? '')) ?></td>
                                <td><?= esc(implode(' + ', $jenis)) ?></td>
                                <td><?= $qtyNormal + $qtyKlaim ?></td>
                                <td><?= esc($rupiah($hpp)) ?></td>
                                <td><?= esc($rupiah($harga)) ?></td>
                                <td>
                                    <input class="form-check-input" type="radio" name="kondisi[<?= $id ?>]" value="baik" aria-required="true" required>
                                </td>
                                <td>
                                    <input class="form-check-input" type="radio" name="kondisi[<?= $id ?>]" value="rusak" aria-required="true" required>
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
