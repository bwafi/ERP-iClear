<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Transfer Internal</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('kas_bank') ?>">Kas &amp; Bank</a></li>
                <li class="breadcrumb-item active">Transfer Internal</li>
            </ol>
        </nav>
    </div>
</div>

<?php
$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$canInput = $bisa_pilih_unit ?? false;
$unitMap = [];
foreach (($unit ?? []) as $u) {
    $unitMap[(int) $u->idunit] = $u->NAMA_UNIT;
}
$akunMap = [];
foreach (($akun_kas_bank ?? []) as $a) {
    $akunMap[(int) $a->idakun_kas_bank] = $a;
}
$labelAkun = static function ($a) use ($unitMap) {
    return ($unitMap[(int) $a->unit_id] ?? 'Unit ' . $a->unit_id) . ' – ' . $a->nama_akun . ' (' . $a->tipe . ')';
};
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-none border">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">Form Transfer</h5>
            </div>
            <div class="card-body">
                <?php if (!$canInput) : ?>
                    <div class="alert alert-warning py-2 mb-3">Anda hanya dapat melihat data. Hubungi Admin Center / Direktur / Manager untuk input.</div>
                <?php endif; ?>
                <form method="post" action="<?= base_url('kas_bank/transfer/save') ?>">
                    <div class="mb-2">
                        <label class="form-label mb-1">Dari Akun</label>
                        <select name="akun_asal_id" class="form-select form-select-sm" required <?= $canInput ? '' : 'disabled' ?>>
                            <option value="">Pilih Akun Asal</option>
                            <?php foreach (($akun_kas_bank ?? []) as $a) : ?>
                                <option value="<?= (int) $a->idakun_kas_bank ?>"><?= esc($labelAkun($a)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Ke Akun</label>
                        <select name="akun_tujuan_id" class="form-select form-select-sm" required <?= $canInput ? '' : 'disabled' ?>>
                            <option value="">Pilih Akun Tujuan</option>
                            <?php foreach (($akun_kas_bank ?? []) as $a) : ?>
                                <option value="<?= (int) $a->idakun_kas_bank ?>"><?= esc($labelAkun($a)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Jumlah</label>
                        <input type="text" name="jumlah" class="form-control form-control-sm rupiah" placeholder="cth: 5.000.000" required <?= $canInput ? '' : 'disabled' ?>>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" <?= $canInput ? '' : 'disabled' ?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-1">Keterangan</label>
                        <input type="text" name="keterangan" class="form-control form-control-sm">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary w-100" <?= $canInput ? '' : 'disabled' ?>>Simpan Transfer</button>
                </form>
                <small class="text-muted d-block mt-2">Transfer internal tidak memengaruhi Net Cash Flow dan bukan pembayaran hutang/piutang.</small>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-none border">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">Riwayat Transfer Internal</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Ref</th>
                            <th>Tanggal</th>
                            <th>Dari</th>
                            <th>Ke</th>
                            <th class="text-end">Jumlah</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transaksi)) : ?>
                            <tr><td colspan="6" class="text-center text-muted">Belum ada transfer internal.</td></tr>
                        <?php endif; ?>
                        <?php foreach (($transaksi ?? []) as $t) :
                            $asal = $akunMap[(int) $t->akun_kas_bank_id] ?? null;
                            $tujuan = $akunMap[(int) $t->akun_tujuan_id] ?? null; ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($t->transfer_ref) ?></td>
                                <td><?= esc($t->tanggal) ?></td>
                                <td><?= $asal ? esc($labelAkun($asal)) : '.' ?></td>
                                <td><?= $tujuan ? esc($labelAkun($tujuan)) : '-' ?></td>
                                <td class="text-end fw-semibold"><?= $rp($t->jumlah) ?></td>
                                <td class="text-end">
                                    <?php if ($t->arah === 'KELUAR' && $canInput) : ?>
                                        <a class="btn btn-sm btn-danger-soft" href="<?= base_url('kas_bank/transfer/reversal/' . (int) $t->idtransaksi) ?>"
                                            onclick="return confirm('Batalkan transfer <?= esc($t->transfer_ref) ?> sebesar <?= $rp($t->jumlah) ?>?')">Batalkan</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>