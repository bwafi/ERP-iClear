<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Transfer &amp; Pembayaran Antar Unit</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('kas_bank') ?>">Kas &amp; Bank</a></li>
                <li class="breadcrumb-item active">Pembayaran Antar Unit</li>
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
$labelStatus = static fn($s) => ['belum_lunas' => 'Belum Lunas', 'sebagian' => 'Sebagian', 'lunas' => 'Lunas'][$s] ?? $s;
$badgeStatus = static fn($s) => ['belum_lunas' => 'bg-warning-subtle text-warning', 'sebagian' => 'bg-primary-subtle text-primary', 'lunas' => 'bg-success-subtle text-success'][$s] ?? 'bg-secondary-subtle text-secondary';
$hpOpen = array_filter($hp_hutang ?? [], static fn($h) => (int) $h->sisa > 0 && $h->status !== 'lunas');
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-none border">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">Form Pembayaran Antar Unit</h5>
            </div>
            <div class="card-body">
                <?php if (!$canInput) : ?>
                    <div class="alert alert-warning py-2 mb-3">Anda hanya dapat melihat data. Hubungi Admin Center / Direktur / Manager untuk input.</div>
                <?php endif; ?>
                <form method="post" action="<?= base_url('kas_bank/antar-unit/save') ?>" enctype="multipart/form-data">
                    <div class="mb-2">
                        <label class="form-label mb-1">Hutang Antar Unit</label>
                        <select name="hutang_piutang_id" id="hp_hutang" class="form-select form-select-sm" required <?= $canInput ? '' : 'disabled' ?>>
                            <option value="">Pilih Hutang (masih bersaldo)</option>
                            <?php foreach (($hp_hutang ?? []) as $h) : ?>
                                <option value="<?= (int) $h->id ?>"
                                    data-unit="<?= (int) $h->unit_id ?>"
                                    data-lawan="<?= (int) $h->lawan_unit_id ?>"
                                    data-sisa="<?= (int) $h->sisa ?>"
                                    <?= (int) $h->sisa <= 0 ? 'disabled' : '' ?>>
                                    [<?= esc($unitMap[(int) $h->unit_id] ?? 'Unit ' . $h->unit_id) ?>] <?= esc($h->nama_pihak) ?> — sisa <?= $rp($h->sisa) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Akun Pengirim (kas/bank)</label>
                        <select name="akun_pengirim_id" id="akun_pengirim" class="form-select form-select-sm" required <?= $canInput ? '' : 'disabled' ?>>
                            <option value="">Pilih Akun Pengirim</option>
                            <?php foreach (($akun_kas_bank ?? []) as $a) : ?>
                                <option value="<?= (int) $a->idakun_kas_bank ?>" data-unit="<?= (int) $a->unit_id ?>">
                                    [<?= esc($unitMap[(int) $a->unit_id] ?? 'U' . $a->unit_id) ?>] <?= esc($a->nama_akun) ?> (<?= esc($a->tipe) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Akun Penerima (kas/bank)</label>
                        <select name="akun_penerima_id" id="akun_penerima" class="form-select form-select-sm" required <?= $canInput ? '' : 'disabled' ?>>
                            <option value="">Pilih Akun Penerima</option>
                            <?php foreach (($akun_kas_bank ?? []) as $a) : ?>
                                <option value="<?= (int) $a->idakun_kas_bank ?>" data-unit="<?= (int) $a->unit_id ?>">
                                    [<?= esc($unitMap[(int) $a->unit_id] ?? 'U' . $a->unit_id) ?>] <?= esc($a->nama_akun) ?> (<?= esc($a->tipe) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Jumlah</label>
                        <input type="text" name="jumlah" id="jumlah_bayar" class="form-control form-control-sm rupiah" placeholder="cth: 5.000.000" required <?= $canInput ? '' : 'disabled' ?>>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" <?= $canInput ? '' : 'disabled' ?>>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Bukti Transfer (opsional)</label>
                        <input type="file" name="bukti" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf,.webp" <?= $canInput ? '' : 'disabled' ?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-1">Keterangan</label>
                        <input type="text" name="keterangan" class="form-control form-control-sm">
                    </div>
                    <button type="submit" class="btn btn-sm btn-success w-100" <?= $canInput ? '' : 'disabled' ?>>Simpan Pembayaran</button>
                </form>
                <small class="text-muted d-block mt-2">
                    Pembayaran dari akun KAS dicatat tunai; dari akun BANK dicatat bank (bank pengirim).
                    Sisa hutang <b>dan</b> pasangan piutangnya berkurang otomatis.
                </small>
            </div>
        </div>

        <div class="card shadow-none border">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">Piutang Antar Unit (unit lawan)</h5>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Unit</th><th class="text-end">Sisa</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach (($hp_piutang ?? []) as $p) : ?>
                            <tr>
                                <td><?= esc($unitMap[(int) $p->unit_id] ?? 'U' . $p->unit_id) ?><br><small class="text-muted"><?= esc($p->nama_pihak) ?></small></td>
                                <td class="text-end fw-semibold"><?= $rp($p->sisa) ?></td>
                                <td><span class="badge <?= $badgeStatus($p->status) ?>"><?= $labelStatus($p->status) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($hp_piutang)) : ?>
                            <tr><td colspan="3" class="text-center text-muted">Belum ada piutang antar unit.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-none border mb-3">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">Hutang Antar Unit (milik unit ini)</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Unit</th>
                            <th>Pasangan</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Dibayar</th>
                            <th class="text-end">Sisa</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($hp_hutang ?? []) as $h) : ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($h->kode) ?></td>
                                <td><?= esc($unitMap[(int) $h->unit_id] ?? 'U' . $h->unit_id) ?></td>
                                <td><?= esc($unitMap[(int) $h->lawan_unit_id] ?? 'U' . $h->lawan_unit_id) ?><br><small class="text-muted"><?= esc($h->nama_pihak) ?></small></td>
                                <td class="text-end"><?= $rp($h->total) ?></td>
                                <td class="text-end"><?= $rp($h->total_dibayar) ?></td>
                                <td class="text-end fw-semibold"><?= $rp($h->sisa) ?></td>
                                <td><span class="badge <?= $badgeStatus($h->status) ?>"><?= $labelStatus($h->status) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($hp_hutang)) : ?>
                            <tr><td colspan="7" class="text-center text-muted">Belum ada hutang antar unit. Hutang terbentuk otomatis dari mutasi stok antar unit.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card shadow-none border">
            <div class="card-header bg-transparent">
                <h5 class="mb-0">Riwayat Pembayaran Antar Unit</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Ref</th>
                            <th>Tanggal</th>
                            <th>Pengirim</th>
                            <th>Penerima</th>
                            <th class="text-end">Jumlah</th>
                            <th>Bukti</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pembayaran)) : ?>
                            <tr><td colspan="7" class="text-center text-muted">Belum ada pembayaran antar unit.</td></tr>
                        <?php endif; ?>
                        <?php foreach (($pembayaran ?? []) as $t) :
                            $asal = $akunMap[(int) $t->akun_kas_bank_id] ?? null;
                            $tujuan = $akunMap[(int) $t->akun_tujuan_id] ?? null; ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($t->transfer_ref) ?></td>
                                <td><?= esc($t->tanggal) ?></td>
                                <td><?= $asal ? esc(($unitMap[(int) $asal->unit_id] ?? '') . ' – ' . $asal->nama_akun) : '-' ?></td>
                                <td><?= $tujuan ? esc(($unitMap[(int) $tujuan->unit_id] ?? '') . ' – ' . $tujuan->nama_akun) : '-' ?></td>
                                <td class="text-end fw-semibold"><?= $rp($t->jumlah) ?></td>
                                <td>
                                    <?php if ($t->bukti) : ?>
                                        <a href="<?= base_url($t->bukti) ?>" target="_blank" class="btn btn-sm btn-light">Lihat</a>
                                    <?php else : ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($t->arah === 'KELUAR' && $canInput) : ?>
                                        <a class="btn btn-sm btn-danger-soft" href="<?= base_url('kas_bank/antar-unit/reversal/' . (int) $t->idtransaksi) ?>"
                                            onclick="return confirm('Batalkan pembayaran <?= esc($t->transfer_ref) ?> sebesar <?= $rp($t->jumlah) ?>? Sisa hutang/piutang pasangannya akan dikembalikan.')">Batalkan</a>
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

<script>
(function () {
    var canInput = <?= $canInput ? 'true' : 'false' ?>;
    var akunByUnit = {};
    document.querySelectorAll('#akun_pengirim option').forEach(function (o) {
        if (!o.value) return;
        (akunByUnit[o.dataset.unit] = akunByUnit[o.dataset.unit] || []).push(o);
    });
    document.getElementById('hp_hutang').addEventListener('change', function () {
        var opt = this.options[this.selectedIndex];
        if (!opt.value) return;
        var sisa = opt.dataset.sisa || '';
        var jumlah = document.getElementById('jumlah_bayar');
        if (sisa && !jumlah.value) { jumlah.value = sisa; }
        ['akun_pengirim', 'akun_penerima'].forEach(function (id) {
            var sel = document.getElementById(id);
            sel.value = '';
            sel.querySelectorAll('option').forEach(function (o) { o.style.display = ''; });
        });
    });
})();
</script>