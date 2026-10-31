<?= $this->include('kas_bank/_nav') ?>
<?= $this->include('kas_bank/_theme') ?>

<?php
$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$canInput = $can_transaksi ?? ($bisa_pilih_unit ?? false);
// Pembatalan transfer hanya untuk Finance / Admin root. Kalau bukan, kolom
// "Aksi" TIDAK dirender sama sekali (bukan hanya tombolnya yang disembunyikan).
$canReversal = $can_reversal ?? false;
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

// Riwayat menampilkan dua hal berbeda: unit TRANSAKSI (siapa yang mencatat)
// dan pemilik REKENING (bisa shared, tanpa unit). Jangan campur keduanya.
$unitNama = static function ($id) use ($unitMap) {
    $id = (int) $id;
    if ($id <= 0) {
        return 'Tanpa unit';
    }

    return $unitMap[$id] ?? 'Unit #' . $id;
};
$akunNama = static fn($a) => $a ? (string) $a->nama_akun : '-';
$akunPemilik = static function ($a) use ($unitMap) {
    if (! $a) {
        return '';
    }
    if ((int) ($a->is_shared ?? 0) === 1) {
        return 'Rekening bersama';
    }
    if ((int) ($a->is_finance_ho ?? 0) === 1) {
        return 'Finance/HO';
    }

    $unitId = (int) ($a->unit_id ?? 0);

    return $unitId > 0 ? ($unitMap[$unitId] ?? 'Unit #' . $unitId) : 'Fisik';
};

$riwayat      = $transaksi ?? [];
$totalTampil  = 0;
foreach ($riwayat as $t) {
    $totalTampil += (int) ($t->jumlah ?? 0);
}
$unitTerpilihId = (int) ($unit_terpilih ?? 0);
$unitRingkas    = $unitTerpilihId > 0 ? $unitNama($unitTerpilihId) : 'Semua unit';
?>

<style>
    /* Riwayat Pindah Saldo — scoped ke panel ini, token dari _theme.php. */
    .kbt-head { flex-wrap: wrap; row-gap: .5rem; }
    .kbt-summary { text-align: right; line-height: 1.25; }
    .kbt-summary-label { display: block; font-size: var(--kb-fs-micro); font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--kb-muted); }
    .kbt-summary-value { display: block; font-size: var(--kb-fs-title); font-weight: 700; font-variant-numeric: tabular-nums; color: var(--bs-emphasis-color, #111c2d); }
    .kbt-count { font-size: var(--kb-fs-meta); color: var(--kb-muted); }

    .kbt-date { font-weight: 500; white-space: nowrap; }
    .kbt-ref { color: var(--kb-muted); font-size: .7rem; letter-spacing: .01em; white-space: nowrap; }
    .kbt-unit { display: inline-flex; align-items: center; gap: .4rem; font-weight: 600; white-space: nowrap; }
    .kbt-unit::before { content: ""; width: .4rem; height: .4rem; border-radius: 50%; background: var(--bs-primary, #0d6efd); flex-shrink: 0; }

    .kbt-flow { display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); align-items: center; gap: .5rem; }
    .kbt-leg { min-width: 0; }
    .kbt-leg-in { text-align: right; }
    .kbt-acc { display: block; font-weight: 600; color: var(--bs-emphasis-color, #111c2d); line-height: 1.3; overflow-wrap: anywhere; }
    .kbt-acc-meta { display: block; font-size: .7rem; color: var(--kb-muted); line-height: 1.3; }
    .kbt-arrow { color: var(--kb-muted); font-size: 1rem; }
    .kbt-amount { font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }

    @media (max-width: 767.98px) {
        /* Thead tetap di accessibility tree; hanya disembunyikan secara visual. */
        .kbt-table thead { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
        .kbt-table, .kbt-table tbody, .kbt-table tr, .kbt-table td { display: block; width: 100%; }
        .kbt-table tbody tr { padding: .75rem 1rem; border-bottom: 1px solid var(--kb-border); }
        .kbt-table tbody tr:last-child { border-bottom: 0; }
        .kbt-table tbody td { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; padding: .2rem 0; border: 0; text-align: right; }
        .kbt-table tbody td::before { content: attr(data-label); flex-shrink: 0; font-size: .72rem; font-weight: 600; color: var(--kb-muted); text-align: left; }
        .kbt-table tbody td.kbt-cell-flow { display: block; text-align: left; }
        .kbt-table tbody td.kbt-cell-flow::before { display: block; margin-bottom: .25rem; }
        .kbt-flow { grid-template-columns: 1fr; gap: .25rem; }
        .kbt-leg-in { text-align: left; }
        .kbt-arrow { transform: rotate(90deg); justify-self: start; }
        .kbt-summary { text-align: left; }
        .kbt-empty-row td { display: block; text-align: center; }
        .kbt-empty-row td::before { content: none; }
    }
</style>

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
            <div class="kb-card-header kbt-head">
                <div>
                    <h5 class="kb-card-title mb-0">Riwayat Transfer Internal</h5>
                    <span class="kb-card-sub text-muted">Perpindahan saldo antar rekening fisik &middot; <?= esc($unitRingkas) ?></span>
                </div>
                <?php if ($riwayat !== []) : ?>
                    <div class="kbt-summary">
                        <span class="kbt-summary-label">Total tampil</span>
                        <span class="kbt-summary-value"><?= $rp($totalTampil) ?></span>
                        <span class="kbt-count"><?= count($riwayat) ?> transfer</span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table kb-table align-middle mb-0 kbt-table">
                    <thead>
                        <tr>
                            <th>Tanggal / Ref</th>
                            <th>Unit</th>
                            <th>Alur Dana</th>
                            <th class="text-end">Nominal</th>
                            <th class="text-center">Bukti</th>
                            <?php if ($canReversal) : ?>
                                <th class="text-end">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($riwayat)) : ?>
                            <tr class="kbt-empty-row">
                                <td colspan="<?= $canReversal ? 6 : 5 ?>" class="text-center py-4 text-muted">
                                    <iconify-icon icon="bi:inbox" class="kb-ico-lg d-block mx-auto mb-2"></iconify-icon>
                                    Belum ada transfer untuk <?= esc($unitRingkas === 'Semua unit' ? 'unit mana pun' : $unitRingkas) ?>.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($riwayat as $t) :
                            $asal   = $akunMap[(int) $t->akun_kas_bank_id] ?? null;
                            $tujuan = $akunMap[(int) $t->akun_tujuan_id] ?? null;
                            $tgl    = strtotime((string) $t->tanggal); ?>
                            <tr>
                                <td data-label="Tanggal / Ref">
                                    <div class="kbt-date"><?= esc($tgl ? date('d/m/Y', $tgl) : (string) $t->tanggal) ?></div>
                                    <div class="kbt-ref kb-mono"><?= esc($t->transfer_ref) ?></div>
                                </td>
                                <td data-label="Unit">
                                    <span class="kbt-unit"><?= esc($unitNama($t->unit_id ?? 0)) ?></span>
                                </td>
                                <td data-label="Alur Dana" class="kbt-cell-flow">
                                    <div class="kbt-flow">
                                        <div class="kbt-leg">
                                            <span class="kbt-acc"><?= esc($akunNama($asal)) ?></span>
                                            <?php if ($asal) : ?>
                                                <small class="kbt-acc-meta"><?= esc($akunPemilik($asal)) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <iconify-icon icon="bi:arrow-right" class="kbt-arrow" aria-hidden="true"></iconify-icon>
                                        <div class="kbt-leg kbt-leg-in">
                                            <span class="kbt-acc"><?= esc($akunNama($tujuan)) ?></span>
                                            <?php if ($tujuan) : ?>
                                                <small class="kbt-acc-meta"><?= esc($akunPemilik($tujuan)) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Nominal" class="text-end">
                                    <div class="kbt-amount"><?= $rp($t->jumlah) ?></div>
                                </td>
                                <td data-label="Bukti" class="text-center">
                                    <?php if ($t->bukti) : ?>
                                        <a href="<?= base_url($t->bukti) ?>" target="_blank" rel="noopener" class="btn btn-xs btn-outline-secondary d-inline-flex align-items-center gap-1">
                                            <iconify-icon icon="bi:file-earmark-image"></iconify-icon>
                                            Lihat
                                        </a>
                                    <?php else : ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canReversal) : ?>
                                    <td data-label="Aksi" class="text-end">
                                        <?php if ($t->arah === 'KELUAR') : ?>
                                            <form method="post" class="d-inline" action="<?= base_url('kas_bank/transfer/reversal/' . (int) $t->idtransaksi) ?>"
                                                onsubmit="return confirm('Batalkan transfer <?= esc($t->transfer_ref) ?> sebesar <?= $rp($t->jumlah) ?>?')">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2 d-inline-flex align-items-center gap-1">
                                                    <iconify-icon icon="bi:x-circle-fill"></iconify-icon>
                                                    Batalkan
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
