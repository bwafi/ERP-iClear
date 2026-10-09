<?= $this->include('kas_bank/_nav') ?>
<?= $this->include('kas_bank/_theme') ?>

<?php
$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$canTransaksi = $can_transaksi ?? false;
$canKelola = $bisa_pilih_unit ?? false;
$unitMap = [];
foreach (($unit ?? []) as $u) {
    $unitMap[(int) $u->idunit] = $u->NAMA_UNIT;
}
$akunMap = [];
foreach (($akun_kas_bank ?? []) as $a) {
    $akunMap[(int) $a->idakun_kas_bank] = $a;
}
$kelAkun = static function ($a) use ($unitMap) {
    return ((isset($unitMap[(int) $a->unit_id])) ? $unitMap[(int) $a->unit_id] : 'Fisik') . ' – ' . $a->nama_akun . ' (' . $a->tipe . ')';
};
$labelStatus = static fn($s) => ['belum_lunas' => 'Belum Lunas', 'sebagian' => 'Sebagian', 'lunas' => 'Lunas'][$s] ?? $s;
$badgeStatus = static fn($s) => ['belum_lunas' => 'kb-badge-amber', 'sebagian' => 'kb-badge-blue', 'lunas' => 'kb-badge-green'][$s] ?? 'kb-badge-muted';
$hpOpen = array_filter($hp_hutang ?? [], static fn($h) => (int) $h->sisa > 0 && $h->status !== 'lunas');
?>

<!-- Context Banner -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4 bg-primary-subtle text-primary-emphasis rounded-3">
        <h5 class="fw-bold mb-2">Pilih Metode Penyelesaian:</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="p-3 bg-white rounded-3 shadow-sm border">
                    <strong class="d-block text-dark mb-1"><iconify-icon icon="solar:transfer-horizontal-bold-duotone" class="text-primary fs-5"></iconify-icon> Transfer Fisik</strong>
                    <small class="text-muted">Pilih rekening pengirim & penerima yang <strong>berbeda</strong>. Uang tunai benar-benar berpindah antar rekening.</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 bg-white rounded-3 shadow-sm border">
                    <strong class="d-block text-dark mb-1"><iconify-icon icon="solar:calculator-minimalistic-bold-duotone" class="text-warning fs-5"></iconify-icon> Atribusi (Talangan)</strong>
                    <small class="text-muted">Pilih rekening pengirim & penerima yang <strong>sama</strong>. Hanya pindah beban/hutang, tidak ada uang tunai yang bergerak.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Form & Piutang -->
    <div class="col-12 col-lg-5 col-xl-4">
        <!-- Form Pembayaran Card -->
        <div class="kb-card mb-4">
            <div class="kb-card-header bg-tertiary">
                <div class="d-flex align-items-center gap-2">
                    <div class="kb-step-badge bg-success">
                        <iconify-icon icon="bi:send-fill"></iconify-icon>
                    </div>
                    <div>
                        <h6 class="kb-card-title mb-0">Form Pelunasan Antar Unit</h6>
                        <span class="kb-card-sub text-muted">Bayar hutang mutasi stok</span>
                    </div>
                </div>
            </div>
            <div class="p-3">
                <?php if (!$canTransaksi) : ?>
                    <div class="alert alert-warning py-2 px-3 d-flex align-items-center gap-2 mb-3">
                        <iconify-icon icon="bi:shield-lock-fill" class="kb-ico"></iconify-icon>
                        <span>Mode Lihat Saja. Hubungi Finance / Manager untuk input.</span>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= base_url('kas_bank/antar-unit/save') ?>" enctype="multipart/form-data">
                    <input type="hidden" name="submit_token" value="<?= esc($submit_token ?? '') ?>">

                    <div class="mb-3">
                        <label class="kb-label mb-1">Tagihan yang akan dibayar</label>
                        <select name="hutang_piutang_id" id="hp_hutang" class="form-select form-select-sm kb-select" required <?= $canTransaksi ? '' : 'disabled' ?>>
                            <option value="">Pilih tagihan</option>
                            <?php foreach (($hp_hutang ?? []) as $h) : ?>
                                <option value="<?= (int) $h->id ?>"
                                    data-unit="<?= (int) $h->unit_id ?>"
                                    data-lawan="<?= (int) $h->lawan_unit_id ?>"
                                    data-sisa="<?= (int) $h->sisa ?>"
                                    <?= (int) $h->sisa <= 0 ? 'disabled' : '' ?>>
                                    <?= esc($h->kode) ?> — ke <?= esc($unitMap[(int) $h->lawan_unit_id] ?? 'U' . $h->lawan_unit_id) ?> — sisa <?= $rp($h->sisa) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Daftar difilter otomatis sesuai cabang yang sedang difilter di atas.</small>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Akun Pengirim (Kas/Bank)</label>
                        <select name="akun_pengirim_id" id="akun_pengirim" class="form-select form-select-sm kb-select" required <?= $canTransaksi ? '' : 'disabled' ?>>
                            <option value="">Pilih Rekening Pengirim</option>
                            <?php foreach (($akun_pengirim ?? []) as $a) : ?>
                                <option value="<?= (int) $a->idakun_kas_bank ?>" data-unit="<?= (int) $a->unit_id ?>">
                                    [<?= esc($unitMap[(int) $a->unit_id] ?? 'U' . $a->unit_id) ?>] <?= esc($a->nama_akun) ?> (<?= esc($a->tipe) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Rekening Sumber Dana</label>
                        <select name="akun_sumber_id" id="akun_sumber" class="form-select form-select-sm kb-select" required <?= $canTransaksi ? '' : 'disabled' ?>>
                            <option value="">Pilih rekening pengirim</option>
                            <?php foreach (($akun_kas_bank ?? []) as $a) : ?>
                                <option value="<?= (int) $a->idakun_kas_bank ?>">
                                    <?= esc($kelAkun($a)) ?><?= (int) ($a->is_shared ?? 0) === 1 ? ' (Bersama)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Rekening Penerima</label>
                        <select name="akun_penerima_id" id="akun_penerima" class="form-select form-select-sm kb-select" required <?= $canTransaksi ? '' : 'disabled' ?>>
                            <option value="">Pilih Rekening Penerima</option>
                            <?php foreach (($akun_penerima ?? []) as $a) : ?>
                                <option value="<?= (int) $a->idakun_kas_bank ?>">
                                    <?= esc($kelAkun($a)) ?> <?= (int) ($a->is_shared ?? 0) === 1 ? '(Bersama)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Nominal Pembayaran (Rp)</label>
                        <input type="text" name="jumlah" id="jumlah_bayar" class="form-control form-control-sm kb-input rupiah" placeholder="cth: 5.000.000" required <?= $canTransaksi ? '' : 'disabled' ?>>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Tanggal Transaksi</label>
                        <input type="date" name="tanggal" class="form-control form-control-sm kb-input" value="<?= date('Y-m-d') ?>" <?= $canTransaksi ? '' : 'disabled' ?>>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Bukti Transfer <small class="text-muted">(Opsional)</small></label>
                        <input type="file" name="bukti" class="form-control form-control-sm kb-input" accept=".jpg,.jpeg,.png,.pdf,.webp" <?= $canTransaksi ? '' : 'disabled' ?>>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Keterangan Catatan</label>
                        <input type="text" name="keterangan" class="form-control form-control-sm kb-input" placeholder="Catatan pembayaran...">
                    </div>

                    <button type="submit" class="btn btn-success btn-sm w-100 fw-semibold d-flex align-items-center justify-content-center gap-1" <?= $canTransaksi ? '' : 'disabled' ?>>
                        <iconify-icon icon="bi:check2-circle"></iconify-icon>
                        Simpan Pembayaran
                    </button>
                </form>
            </div>
        </div>

        <!-- Table Piutang Card -->
        <div class="kb-card mb-4">
            <div class="kb-card-header">
                <div>
                    <h5 class="kb-card-title mb-0">Piutang Antar Unit</h5>
                    <span class="kb-card-sub text-muted">Hak tagih unit ke unit lawan</span>
                </div>
                <span class="kb-badge kb-badge-green">Piutang</span>
            </div>
            <div class="table-responsive">
                <table class="table kb-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Unit Lawan</th>
                            <th class="text-end">Sisa Tagihan</th>
                            <th>Status</th>
                            <th class="text-end">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($hp_piutang ?? []) as $p) :
                            $bukti = $detail_mutasi_map[(int) $p->sumber_id] ?? null; ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-emphasis"><?= esc($unitMap[(int) $p->unit_id] ?? 'U' . $p->unit_id) ?></div>
                                    <div class="text-muted"><?= esc($p->nama_pihak) ?></div>
                                </td>
                                <td class="text-end kb-num fw-bold text-success"><?= $rp($p->sisa) ?></td>
                                <td><span class="kb-badge <?= $badgeStatus($p->status) ?>"><?= $labelStatus($p->status) ?></span></td>
                                <td class="text-end">
                                    <?php if ($bukti) : ?>
                                        <button type="button" class="btn btn-xs btn-outline-secondary"
                                            data-bs-toggle="collapse" data-bs-target="#p-barang-<?= (int) $p->id ?>">
                                            Items
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($bukti) : ?>
                                <tr class="bg-tertiary collapse" id="p-barang-<?= (int) $p->id ?>">
                                    <td colspan="4" class="p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="fw-bold text-emphasis"><?= esc($bukti['no_nota']) ?> · <?= esc(date('Y-m-d', strtotime((string) $bukti['tanggal']))) ?></span>
                                            <span class="fw-bold text-primary">Total: <?= $rp($bukti['total']) ?></span>
                                        </div>
                                        <table class="table table-sm table-bordered mb-0 bg-white">
                                            <thead class="table-light">
                                                <tr><th>Barang</th><th class="text-end">Qty</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th></tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bukti['items'] as $d) : ?>
                                                    <tr>
                                                        <td><?= esc($d->nama_barang) ?></td>
                                                        <td class="text-end kb-num"><?= (float) $d->jumlah_kirim ?></td>
                                                        <td class="text-end kb-num"><?= $rp($d->harga_mutasi) ?></td>
                                                        <td class="text-end kb-num fw-semibold"><?= $rp($d->nilai ?? 0) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (empty($hp_piutang)) : ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    Belum ada catatan piutang antar unit.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Hutang, Atribusi & Riwayat -->
    <div class="col-12 col-lg-7 col-xl-8">
        <!-- Table Hutang Card -->
        <div class="kb-card mb-4">
            <div class="kb-card-header">
                <div>
                    <h5 class="kb-card-title mb-0">Hutang Antar Unit</h5>
                    <span class="kb-card-sub text-muted">Kewajiban cabang ini ke cabang lain</span>
                </div>
                <span class="kb-badge kb-badge-red">Hutang</span>
            </div>
            <div class="table-responsive">
                <table class="table kb-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Unit Asal</th>
                            <th>Unit Lawan</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Dibayar</th>
                            <th class="text-end">Sisa</th>
                            <th>Status</th>
                            <th class="text-end">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($hp_hutang ?? []) as $h) :
                            $bukti = $detail_mutasi_map[(int) $h->sumber_id] ?? null; ?>
                            <tr>
                                <td><span class="kb-badge kb-badge-muted"><?= esc($h->kode) ?></span></td>
                                <td class="text-muted"><?= esc($unitMap[(int) $h->unit_id] ?? 'U' . $h->unit_id) ?></td>
                                <td>
                                    <div class="fw-semibold text-emphasis"><?= esc($unitMap[(int) $h->lawan_unit_id] ?? 'U' . $h->lawan_unit_id) ?></div>
                                    <div class="text-muted"><?= esc($h->nama_pihak) ?></div>
                                </td>
                                <td class="text-end kb-num"><?= $rp($h->total) ?></td>
                                <td class="text-end kb-num text-success"><?= $rp($h->total_dibayar) ?></td>
                                <td class="text-end kb-num text-danger fw-bold"><?= $rp($h->sisa) ?></td>
                                <td><span class="kb-badge <?= $badgeStatus($h->status) ?>"><?= $labelStatus($h->status) ?></span></td>
                                <td class="text-end">
                                    <?php if ($bukti) : ?>
                                        <button type="button" class="btn btn-xs btn-outline-secondary"
                                            data-bs-toggle="collapse" data-bs-target="#hp-barang-<?= (int) $h->id ?>">
                                            Items
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($bukti) : ?>
                                <tr class="bg-tertiary collapse" id="hp-barang-<?= (int) $h->id ?>">
                                    <td colspan="8" class="p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="fw-bold text-emphasis"><?= esc($bukti['no_nota']) ?> · Tgl: <?= esc(date('Y-m-d', strtotime((string) $bukti['tanggal']))) ?> · Jt Tempo: <?= esc($h->jatuh_tempo) ?></span>
                                            <span class="fw-bold text-primary">Total: <?= $rp($bukti['total']) ?></span>
                                        </div>
                                        <table class="table table-sm table-bordered mb-0 bg-white">
                                            <thead class="table-light">
                                                <tr><th>Barang</th><th class="text-end">Qty</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th></tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bukti['items'] as $d) : ?>
                                                    <tr>
                                                        <td><?= esc($d->nama_barang) ?></td>
                                                        <td class="text-end kb-num"><?= (float) $d->jumlah_kirim ?></td>
                                                        <td class="text-end kb-num"><?= $rp($d->harga_mutasi) ?></td>
                                                        <td class="text-end kb-num fw-semibold"><?= $rp($d->nilai ?? 0) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (empty($hp_hutang)) : ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    Belum ada hutang antar unit terdaftar.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Table Atribusi Card -->
        <div class="kb-card mb-4">
            <div class="kb-card-header">
                <div>
                    <h5 class="kb-card-title mb-0">Penyelesaian H/P Rekening Sama (Atribusi)</h5>
                    <span class="kb-card-sub text-muted">Pelunasan tanpa pergerakan saldo kas/bank fisik</span>
                </div>
                <span class="kb-badge kb-badge-muted">Atribusi</span>
            </div>
            <div class="table-responsive">
                <table class="table kb-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Ref Hutang</th>
                            <th>Keterangan</th>
                            <th class="text-end">Jumlah</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($histori_atribusi)) : ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    Belum ada riwayat penyelesaian H/P rekening sama.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach (($histori_atribusi ?? []) as $p) : ?>
                            <tr>
                                <td class="text-muted"><?= esc($p->tanggal_bayar) ?></td>
                                <td><span class="kb-badge kb-badge-muted">#<?= (int) $p->referensi_id ?></span></td>
                                <td class="text-secondary"><?= esc($p->keterangan) ?></td>
                                <td class="text-end kb-num fw-bold text-emphasis"><?= $rp($p->jumlah_bayar) ?></td>
                                <td class="text-end">
                                    <?php if ($canKelola) : ?>
                                        <form method="post" class="d-inline" action="<?= base_url('kas_bank/antar-unit/reversal-atribusi/' . (int) $p->referensi_id) ?>"
                                            onsubmit="return confirm('Batalkan penyelesaian H/P ini? Sisa hutang/piutang pasangannya akan dikembalikan. Saldo kas tidak berubah.')">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2">Batalkan</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Riwayat Pembayaran Card -->
        <div class="kb-card mb-4">
            <div class="kb-card-header">
                <div>
                    <h5 class="kb-card-title mb-0">Riwayat Pembayaran Real Kas/Bank</h5>
                    <span class="kb-card-sub text-muted">Daftar transfer pembayaran antar unit yang memengaruhi kas/bank</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table kb-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Kode Ref</th>
                            <th>Tanggal</th>
                            <th>Pengirim</th>
                            <th>Penerima</th>
                            <th class="text-end">Jumlah</th>
                            <th class="text-center">Bukti</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pembayaran)) : ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    Belum ada riwayat pembayaran kas/bank antar unit.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach (($pembayaran ?? []) as $t) :
                            $asal = $akunMap[(int) $t->akun_kas_bank_id] ?? null;
                            $tujuan = $akunMap[(int) $t->akun_tujuan_id] ?? null; ?>
                            <tr>
                                <td><span class="kb-badge kb-badge-amber fw-semibold"><?= esc($t->transfer_ref) ?></span></td>
                                <td class="text-muted"><?= esc($t->tanggal) ?></td>
                                <td class="text-emphasis"><?= $asal ? esc(($unitMap[(int) $asal->unit_id] ?? '') . ' – ' . $asal->nama_akun) : '-' ?></td>
                                <td class="text-emphasis"><?= $tujuan ? esc(($unitMap[(int) $tujuan->unit_id] ?? '') . ' – ' . $tujuan->nama_akun) : '-' ?></td>
                                <td class="text-end kb-num fw-bold text-emphasis"><?= $rp($t->jumlah) ?></td>
                                <td class="text-center">
                                    <?php if ($t->bukti) : ?>
                                        <a href="<?= base_url($t->bukti) ?>" target="_blank" class="btn btn-xs btn-outline-secondary">Lihat</a>
                                    <?php else : ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($t->arah === 'KELUAR' && $canKelola) : ?>
                                        <form method="post" class="d-inline" action="<?= base_url('kas_bank/antar-unit/reversal/' . (int) $t->idtransaksi) ?>"
                                            onsubmit="return confirm('Batalkan pembayaran <?= esc($t->transfer_ref) ?> sebesar <?= $rp($t->jumlah) ?>? Sisa hutang/piutang pasangannya akan dikembalikan.')">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2">Batalkan</button>
                                        </form>
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
    var canInput = <?= $canKelola ? 'true' : 'false' ?>;
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
