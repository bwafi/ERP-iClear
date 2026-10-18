<div class="card">
    <div class="card-body">
        <h4>Kredit Retur Supplier</h4>
        <p>Nominal kredit dicatat setelah nota retur disetujui supplier. Kredit mengurangi total utang supplier, bukan pembayaran kas atau faktur tertentu.</p>
        <?php if (session('gagal')): ?>
            <div class="alert alert-danger"><?= esc(session('gagal')) ?></div>
        <?php endif; ?>
        <?php if (session('sukses')): ?>
            <div class="alert alert-success"><?= esc(session('sukses')) ?></div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>No. Retur</th><th>Supplier</th><th>Status</th><th>Nominal</th><th>Sisa Kredit</th><th>Nota Kredit</th><th>Persetujuan</th></tr></thead>
                <tbody>
                <?php foreach ($credits as $credit): ?>
                    <tr>
                        <td><?= esc($credit->no_retur_suplier) ?></td>
                        <td><?= esc($credit->nama_suplier) ?></td>
                        <td><?= esc($credit->status) ?></td>
                        <td>Rp <?= number_format((int) $credit->nominal, 0, ',', '.') ?></td>
                        <td>Rp <?= number_format((int) $credit->sisa_kredit, 0, ',', '.') ?></td>
                        <td><?= esc($credit->nomor_nota_kredit ?? '-') ?></td>
                        <td>
                            <?php if ($canApprove && $credit->status === 'menunggu'): ?>
                                <form method="post" action="<?= base_url('kredit_retur_suplier/approve') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="credit_id" value="<?= (int) $credit->id ?>">
                                    <label>Nominal nota kredit
                                        <input class="form-control" type="number" min="1" step="1" name="nominal" required>
                                    </label>
                                    <label>Nomor nota kredit supplier
                                        <input class="form-control" type="text" maxlength="100" name="nomor_nota_kredit" required>
                                    </label>
                                    <label>Akun Utang Supplier (debit saat kredit dipakai)
                                        <select class="form-select" name="akun_debet" required>
                                            <option value="">Pilih akun</option>
                                            <?php foreach ($accounts as $account): ?>
                                                <option value="<?= esc($account->no_akun) ?>"><?= esc($account->no_akun . ' - ' . $account->nama_akun) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>Akun Piutang Supplier (debit saat disetujui)
                                        <select class="form-select" name="akun_piutang" required>
                                            <option value="">Pilih akun</option>
                                            <?php foreach ($accounts as $account): ?>
                                                <option value="<?= esc($account->no_akun) ?>"><?= esc($account->no_akun . ' - ' . $account->nama_akun) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>Akun Pemulihan Barang Rusak (kredit saat disetujui)
                                        <select class="form-select" name="akun_kredit" required>
                                            <option value="">Pilih akun</option>
                                            <?php foreach ($accounts as $account): ?>
                                                <option value="<?= esc($account->no_akun) ?>"><?= esc($account->no_akun . ' - ' . $account->nama_akun) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <button class="btn btn-primary mt-2" type="submit">Setujui dan catat jurnal</button>
                                </form>
                            <?php elseif ($canApprove && $credit->status === 'disetujui' && (int) $credit->sisa_kredit > 0): ?>
                                <form method="post" action="<?= base_url('kredit_retur_suplier/apply') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="credit_id" value="<?= (int) $credit->id ?>">
                                    <button class="btn btn-outline-primary" type="submit">Gunakan sisa kredit</button>
                                </form>
                            <?php else: ?>
                                <?= esc($credit->disetujui_pada ?? '-') ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($credits === []): ?>
                    <tr><td colspan="7">Belum ada dokumen retur supplier.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
