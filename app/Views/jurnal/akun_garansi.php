<div class="card">
    <div class="card-body">
        <h4>Akun Jurnal Pengganti Garansi</h4>
        <p>Admin memilih akun beban garansi dan persediaan. Setiap sparepart pengganti mendebit beban dan mengkredit persediaan sebesar HPP pengganti; tanpa pemetaan valid, transaksi ditolak.</p>
        <?php if (session('gagal')): ?>
            <div class="alert alert-danger"><?= esc(session('gagal')) ?></div>
        <?php endif; ?>
        <?php if (session('sukses')): ?>
            <div class="alert alert-success"><?= esc(session('sukses')) ?></div>
        <?php endif; ?>
        <?php $selected = [];
        foreach ($mapping as $row) {
            $selected[$row['debet_kredit']] = $row['no_akun'];
        } ?>
        <form method="post" action="<?= base_url('akun_jurnal_garansi/save') ?>">
            <?= csrf_field() ?>
            <label class="form-label" for="akun_beban">Akun beban garansi (debit)</label>
            <select class="form-select mb-3" id="akun_beban" name="akun_beban" required>
                <option value="">Pilih akun</option>
                <?php foreach ($accounts as $account): ?>
                    <?php if (preg_match('/^([56][0-9]{9}|702[0-9]{7})$/D', $account->no_akun)): ?>
                        <option value="<?= esc($account->no_akun) ?>" <?= ($selected['debet'] ?? '') === $account->no_akun ? 'selected' : '' ?>><?= esc($account->no_akun . ' - ' . $account->nama_akun) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <label class="form-label" for="akun_persediaan">Akun persediaan (kredit)</label>
            <select class="form-select mb-3" id="akun_persediaan" name="akun_persediaan" required>
                <option value="">Pilih akun</option>
                <?php foreach ($accounts as $account): ?>
                    <?php if (preg_match('/^1[0-9]{9}$/D', $account->no_akun) && preg_match('/persediaan|stok|inventory/i', $account->nama_akun)): ?>
                        <option value="<?= esc($account->no_akun) ?>" <?= ($selected['kredit'] ?? '') === $account->no_akun ? 'selected' : '' ?>><?= esc($account->no_akun . ' - ' . $account->nama_akun) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit">Simpan akun jurnal</button>
        </form>
    </div>
</div>
