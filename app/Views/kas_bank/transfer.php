<?= $this->include('kas_bank/_nav') ?>
<?= $this->include('kas_bank/_theme') ?>

<?php
$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$canInput = $can_transaksi ?? ($bisa_pilih_unit ?? false);
$canKelola = $bisa_pilih_unit ?? false;
$unitMap = [];
foreach (($unit ?? []) as $u) {
    $unitMap[(int) $u->idunit] = $u->NAMA_UNIT;
}
// Lookup label rekening untuk tabel riwayat. Controller mengirim GABUNGAN
// daftar source + destination (keduanya sudah BANK-only), jadi setiap baris
// Pindah Saldo yang sah punya nama rekening di kedua kolomnya. Kalau daftar
// ini tidak sinkron dengan rekening yang dipakai form, kolom render "-" untuk
// rekening yang sah. KAS tidak boleh masuk ke sini hanya untuk melengkapi
// label: Pindah Saldo wajib BANK -> BANK, jadi tidak ada baris KAS di tab ini.
$akunMap = [];
foreach (($akun_kas_bank ?? []) as $a) {
    $akunMap[(int) $a->idakun_kas_bank] = $a;
}
$labelAkun = static function ($a) use ($unitMap) {
    return ((isset($unitMap[(int) $a->unit_id])) ? $unitMap[(int) $a->unit_id] : 'Fisik') . ' – ' . $a->nama_akun . ' (' . $a->tipe . ')';
};
?>

<!-- Mode Switcher Perpindahan Uang -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-2 bg-light rounded-3">
        <div class="nav nav-pills nav-fill gap-2">
            <a href="<?= base_url('kas_bank/transfer') ?>" class="nav-link active bg-primary text-white fw-semibold py-2 d-flex align-items-center justify-content-center gap-2">
                <iconify-icon icon="solar:transfer-horizontal-bold-duotone" class="fs-5"></iconify-icon>
                <span>Pindah Saldo (Bank &rarr; Bank)</span>
            </a>
            <a href="<?= base_url('kas_bank/setor-tunai') ?>" class="nav-link bg-white text-dark border fw-semibold py-2 d-flex align-items-center justify-content-center gap-2">
                <iconify-icon icon="solar:cash-out-bold-duotone" class="fs-5 text-success"></iconify-icon>
                <span>Setor Tunai (Laci &rarr; Bank)</span>
            </a>
            <a href="<?= base_url('kas_bank/penarikan-tunai') ?>" class="nav-link bg-white text-dark border fw-semibold py-2 d-flex align-items-center justify-content-center gap-2">
                <iconify-icon icon="solar:hand-money-bold-duotone" class="fs-5 text-warning-emphasis"></iconify-icon>
                <span>Tarik Tunai (Bank &rarr; Laci)</span>
            </a>
        </div>
    </div>
</div>

<?php if (($bisa_pilih_unit ?? false)) : ?>
    <form class="kb-card mb-4 p-3" method="get" action="<?= base_url('kas_bank/transfer') ?>">
        <div class="row align-items-center g-2">
            <div class="col-12 col-md-4">
                <label class="kb-label mb-1">Unit Transaksi</label>
                <select name="unit_id" class="form-select form-select-sm kb-select" onchange="this.form.submit()">
                    <?php foreach (($unit ?? []) as $u) : ?>
                        <option value="<?= (int) $u->idunit ?>" <?= ($unit_terpilih ?? 0) == $u->idunit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-8 text-muted d-flex align-items-center gap-2">
                <iconify-icon icon="bi:info-circle-fill" class="text-info kb-ico flex-shrink-0"></iconify-icon>
                <span>Transfer internal mencatat perpindahan antar rekening fisik milik sendiri (tidak memengaruhi laba/rugi perusahaan).</span>
            </div>
        </div>
    </form>
<?php endif; ?>

<div class="row g-4">
    <!-- Form Transfer Column -->
    <div class="col-12 col-lg-5 col-xl-4">
        <div class="kb-card h-100">
            <div class="kb-card-header bg-tertiary">
                <div class="d-flex align-items-center gap-2">
                    <div class="kb-step-badge bg-primary">
                        <iconify-icon icon="bi:arrow-left-right"></iconify-icon>
                    </div>
                    <div>
                        <h6 class="kb-card-title mb-0">Form Transfer Saldo</h6>
                        <span class="kb-card-sub text-muted">Perpindahan antar akun sendiri</span>
                    </div>
                </div>
            </div>
            <div class="p-3">
                <?php if (!$canInput) : ?>
                    <div class="alert alert-warning py-2 px-3 d-flex align-items-center gap-2 mb-3">
                        <iconify-icon icon="bi:shield-lock-fill" class="kb-ico"></iconify-icon>
                        <span>Mode Lihat Saja. Anda tidak memiliki akses untuk menambah transaksi.</span>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= base_url('kas_bank/transfer/save') ?>" enctype="multipart/form-data">
                    <input type="hidden" name="submit_token" value="<?= esc($submit_token ?? '') ?>">
                    <input type="hidden" name="unit_id" value="<?= (int) ($unit_terpilih ?? 0) ?>">

                    <?php if (($bisa_pilih_unit ?? false)) : ?>
                        <div class="mb-3">
                            <label class="kb-label mb-1">Unit Cabang Transaksi</label>
                            <input type="text" class="form-control form-control-sm kb-input bg-tertiary" value="<?= esc($unitMap[(int) ($unit_terpilih ?? 0)] ?? '-') ?>" readonly>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Dari Akun (Pengirim)</label>
                        <select name="akun_asal_id" class="form-select form-select-sm kb-select" required <?= $canInput ? '' : 'disabled' ?>>
                            <option value="">Pilih Rekening Asal</option>
                            <?php foreach (($akun_sumber ?? []) as $a) : ?>
                                <option value="<?= (int) $a->idakun_kas_bank ?>"><?= esc($labelAkun($a)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Ke Akun (Penerima)</label>
                        <select name="akun_tujuan_id" class="form-select form-select-sm kb-select" required <?= $canInput ? '' : 'disabled' ?>>
                            <option value="">— Pilih rekening tujuan —</option>
                            <?php foreach (($akun_tujuan ?? []) as $a) : ?>
                                <option value="<?= (int) $a->idakun_kas_bank ?>"><?= esc($labelAkun($a)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Nominal Transfer (Rp)</label>
                        <input type="text" name="jumlah" class="form-control form-control-sm kb-input rupiah" placeholder="cth: 5.000.000" required <?= $canInput ? '' : 'disabled' ?>>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Tanggal Transaksi</label>
                        <input type="date" name="tanggal" class="form-control form-control-sm kb-input" value="<?= date('Y-m-d') ?>" <?= $canInput ? '' : 'disabled' ?>>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Keterangan / Catatan</label>
                        <input type="text" name="keterangan" class="form-control form-control-sm kb-input" placeholder="Peruntukan transfer..." <?= $canInput ? '' : 'disabled' ?>>
                    </div>

                    <div class="mb-3">
                        <label class="kb-label mb-1">Bukti Transfer <small class="text-muted">(Opsional)</small></label>
                        <input type="file" name="bukti" class="form-control form-control-sm kb-input" accept=".jpg,.jpeg,.png,.pdf,.webp" <?= $canInput ? '' : 'disabled' ?>>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm text-white w-100 fw-semibold d-flex align-items-center justify-content-center gap-1" <?= $canInput ? '' : 'disabled' ?>>
                        <iconify-icon icon="bi:arrow-left-right"></iconify-icon>
                        Proses Transfer Saldo
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Riwayat Table Column -->
    <div class="col-12 col-lg-7 col-xl-8">
        <div class="kb-card h-100">
            <div class="kb-card-header">
                <div>
                    <h5 class="kb-card-title mb-0">Riwayat Transfer Internal</h5>
                    <span class="kb-card-sub text-muted">Daftar transaksi perpindahan saldo antar rekening fisik.</span>
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
                            <th class="text-end">Jumlah Nominal</th>
                            <th class="text-center">Bukti</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transaksi)) : ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <iconify-icon icon="bi:inbox" class="kb-ico-lg d-block mx-auto mb-2"></iconify-icon>
                                    Belum ada data riwayat transfer internal.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach (($transaksi ?? []) as $t) :
                            $asal = $akunMap[(int) $t->akun_kas_bank_id] ?? null;
                            $tujuan = $akunMap[(int) $t->akun_tujuan_id] ?? null; ?>
                            <tr>
                                <td>
                                    <span class="kb-badge kb-badge-blue fw-semibold"><?= esc($t->transfer_ref) ?></span>
                                </td>
                                <td class="text-secondary fw-medium"><?= esc($t->tanggal) ?></td>
                                <td>
                                    <div class="fw-semibold text-emphasis"><?= $asal ? esc($labelAkun($asal)) : '-' ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-emphasis"><?= $tujuan ? esc($labelAkun($tujuan)) : '-' ?></div>
                                </td>
                                <td class="text-end kb-num fw-bold text-emphasis"><?= $rp($t->jumlah) ?></td>
                                <td class="text-center">
                                    <?php if ($t->bukti) : ?>
                                        <a href="<?= base_url($t->bukti) ?>" target="_blank" class="btn btn-xs btn-outline-secondary d-inline-flex align-items-center gap-1">
                                            <iconify-icon icon="bi:file-earmark-image"></iconify-icon>
                                            Lihat
                                        </a>
                                    <?php else : ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($t->arah === 'KELUAR' && $canKelola) : ?>
                                        <form method="post" class="d-inline" action="<?= base_url('kas_bank/transfer/reversal/' . (int) $t->idtransaksi) ?>"
                                            onsubmit="return confirm('Batalkan transfer <?= esc($t->transfer_ref) ?> sebesar <?= $rp($t->jumlah) ?>?')">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2 d-inline-flex align-items-center gap-1">
                                                <iconify-icon icon="bi:x-circle-fill"></iconify-icon>
                                                Batalkan
                                            </button>
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
