<?= $this->include('kas_bank/_nav') ?>
<?= $this->include('kas_bank/_theme') ?>

<?php
$rp = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$f = $filter ?? [];
$unitMap = [];
foreach (($unit ?? []) as $u) {
    $unitMap[(int) $u->idunit] = $u->NAMA_UNIT;
}
$akunMap = [];
foreach (($akun_kas_bank ?? []) as $a) {
    $akunMap[(int) $a->idakun_kas_bank] = $a->nama_akun;
}
$unitDipilih = (int) ($unit_terpilih ?? 0) > 0;
$hasWarning = !empty($warning_alokasi);
$netFlow = (int) ($net_cash_flow ?? 0);
?>

<!-- Filter -->
<div class="kb-filter">
    <form method="get" action="<?= base_url('kas_bank') ?>" class="d-flex align-items-end flex-wrap gap-2 w-100">
        <?php if (($bisa_pilih_unit ?? false)) : ?>
            <div class="kb-field">
                <label class="kb-label" for="f-unit">Filter Cabang</label>
                <select name="unit_id" id="f-unit" class="form-select form-select-sm kb-select kb-filter-select">
                    <option value="">Semua Cabang (Konsolidasi)</option>
                    <?php foreach (($unit ?? []) as $u) : ?>
                        <option value="<?= (int) $u->idunit ?>" <?= ($unit_terpilih ?? 0) == $u->idunit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="kb-field">
            <label class="kb-label" for="f-dari">Dari Tanggal</label>
            <input type="date" id="f-dari" name="tanggal_awal" class="form-control form-control-sm kb-input" value="<?= esc($f['tanggal_awal'] ?? '') ?>">
        </div>

        <div class="kb-field">
            <label class="kb-label" for="f-sampai">Sampai Tanggal</label>
            <input type="date" id="f-sampai" name="tanggal_akhir" class="form-control form-control-sm kb-input" value="<?= esc($f['tanggal_akhir'] ?? '') ?>">
        </div>

        <div class="kb-field">
            <label class="kb-label" for="f-akun">Filter Rekening</label>
            <select name="akun_id" id="f-akun" class="form-select form-select-sm kb-select kb-filter-select">
                <option value="">Semua Rekening</option>
                <?php foreach (($akun_kas_bank ?? []) as $a) : ?>
                    <option value="<?= (int) $a->idakun_kas_bank ?>" <?= ($f['akun_id'] ?? 0) == $a->idakun_kas_bank ? 'selected' : '' ?>>
                        <?= esc((isset($unitMap[(int) $a->unit_id]) ? $unitMap[(int) $a->unit_id] . ' – ' : '') . $a->nama_akun . ' (' . $a->tipe . ')') ?>
                        <?= $a->tipe === 'BANK' && empty($a->unit_id) ? ' — Shared' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="kb-field">
            <button class="btn btn-primary btn-sm fw-semibold d-inline-flex align-items-center justify-content-center gap-1" type="submit">
                <iconify-icon icon="bi:funnel-fill"></iconify-icon>
                Apply Filter
            </button>
        </div>
    </form>
</div>

<!-- Banner konteks -->
<div class="kb-banner">
    <div class="kb-banner-icon text-primary">
        <iconify-icon icon="bi:info-circle-fill"></iconify-icon>
    </div>
    <div class="kb-banner-content">
        <strong>Panduan Pembacaan Saldo:</strong>
        <strong>Saldo Nyata</strong> adalah nominal fisik di rekening/laci.
        <?php if ($unitDipilih) : ?>
            Cabang aktif: <strong class="text-primary">Hak Unit Ini</strong> mencerminkan porsi kepemilikan unit dari total fisik.
        <?php else : ?>
            Pilih cabang tertentu pada filter untuk meninjau <strong class="text-primary">Hak Pakai per Unit</strong>.
        <?php endif; ?>
    </div>
</div>

<!-- Metrik -->
<div class="kb-stats">
    <div class="kb-stat">
        <div class="d-flex align-items-center justify-content-between gap-2">
            <span class="kb-stat-label">Kas Tunai<?= $unitDipilih ? ' (Unit)' : '' ?></span>
            <iconify-icon icon="bi:cash-stack" class="text-primary"></iconify-icon>
        </div>
        <div class="kb-stat-value kb-num"><?= $rp($total_kas ?? 0) ?></div>
        <div class="kb-stat-hint"><?= $unitDipilih ? 'Fisik semua unit: ' . $rp($total_fisik_kas ?? 0) : 'Total fisik laci &amp; brankas' ?></div>
    </div>

    <div class="kb-stat">
        <div class="d-flex align-items-center justify-content-between gap-2">
            <span class="kb-stat-label">Saldo Bank<?= $unitDipilih ? ' (Unit)' : '' ?></span>
            <iconify-icon icon="bi:bank2" class="text-warning-emphasis"></iconify-icon>
        </div>
        <div class="kb-stat-value kb-num"><?= $rp($total_bank ?? 0) ?></div>
        <div class="kb-stat-hint"><?= $unitDipilih ? 'Fisik semua unit: ' . $rp($total_fisik_bank ?? 0) : 'Total saldo terdaftar di bank' ?></div>
    </div>

    <div class="kb-stat">
        <div class="d-flex align-items-center justify-content-between gap-2">
            <span class="kb-stat-label">Total Posisi<?= $unitDipilih ? ' (Unit)' : '' ?></span>
            <iconify-icon icon="bi:wallet-fill" class="text-info"></iconify-icon>
        </div>
        <div class="kb-stat-value kb-num"><?= $rp($total_semua ?? 0) ?></div>
        <div class="kb-stat-hint"><?= $unitDipilih ? 'Fisik semua unit: ' . $rp($total_fisik_semua ?? 0) : 'Total konsolidasi Kas + Bank' ?></div>
    </div>

    <div class="kb-stat">
        <div class="d-flex align-items-center justify-content-between gap-2">
            <span class="kb-stat-label">Arus Kas Bersih</span>
            <iconify-icon
                icon="<?= $netFlow < 0 ? 'bi:graph-down-arrow' : ($netFlow > 0 ? 'bi:graph-up-arrow' : 'bi:dash-lg') ?>"
                class="<?= $netFlow < 0 ? 'text-danger' : ($netFlow > 0 ? 'text-success' : 'text-secondary') ?>"></iconify-icon>
        </div>
        <div class="kb-stat-value kb-num <?= $netFlow < 0 ? 'text-danger' : ($netFlow > 0 ? 'text-success' : '') ?>"><?= $rp($netFlow) ?></div>
        <div class="kb-stat-hint">Pemasukan − Pengeluaran Ops.</div>
    </div>
</div>

<!-- Peringatan alokasi -->
<?php if ($hasWarning) : ?>
    <div class="alert alert-warning d-flex align-items-start gap-2 mb-3 rounded-3 py-2 px-3"
         style="font-size: var(--kb-fs-body); background: var(--bs-warning-bg-subtle); border-color: var(--bs-warning-border-subtle); color: var(--bs-warning-text-emphasis);">
        <iconify-icon icon="bi:exclamation-triangle-fill" class="flex-shrink-0 mt-1"></iconify-icon>
        <div>
            <strong class="d-block">Alokasi Saldo Melebihi Saldo Fisik Rekening!</strong>
            <div class="kb-meta">Beberapa akun bank memiliki total alokasi cabang yang melampaui saldo riil:</div>
            <div class="kb-alloc my-2">
                <?php foreach (($warning_alokasi ?? []) as $ka) : ?>
                    <span class="kb-badge kb-badge-red kb-num"><?= esc($akunMap[$ka] ?? '#' . $ka) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="kb-meta">
                Penyesuaian dapat dilakukan di menu
                <a href="<?= base_url('kas_bank/akun') ?>" class="fw-semibold text-decoration-underline">Rekening &amp; Saldo</a> &rarr; Hak Unit.
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Tabel utama -->
<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="kb-card h-100">
            <div class="kb-card-header">
                <div>
                    <h5 class="kb-card-title mb-0">Saldo per Rekening Fisik</h5>
                    <span class="kb-card-sub"><?= $unitDipilih ? 'Rincian kepemilikan unit terhadap saldo riil.' : 'Rincian saldo seluruh kas &amp; bank terdaftar.' ?></span>
                </div>
                <div class="d-flex gap-1">
                    <span class="kb-badge kb-badge-blue">KAS</span>
                    <span class="kb-badge kb-badge-amber">BANK</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table kb-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nama Rekening</th>
                            <th>Tipe</th>
                            <th>Status</th>
                            <th class="text-end">Saldo Nyata</th>
                            <?php if ($unitDipilih) : ?>
                                <th class="text-end">Hak Unit Ini</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($akun_kas_bank)) : ?>
                            <tr>
                                <td colspan="<?= $unitDipilih ? 5 : 4 ?>" class="kb-empty">
                                    <iconify-icon icon="bi:inbox" class="kb-ico-lg d-block mx-auto mb-2"></iconify-icon>
                                    Belum ada rekening terdaftar.
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach (($akun_kas_bank ?? []) as $a) : ?>
                                <?php
                                $shared = (int) ($a->is_shared ?? 0) === 1 || ($a->tipe === 'BANK' && empty($a->unit_id));
                                $fisik = $saldo_fisik_per_akun[(int) $a->idakun_kas_bank] ?? 0;
                                $unitSaldo = $saldo_unit_per_akun[(int) $a->idakun_kas_bank] ?? 0;
                                ?>
                                <tr>
                                    <td>
                                        <div class="kb-name"><?= esc($a->nama_akun) ?></div>
                                        <div class="kb-meta">
                                            <?= esc($unitMap[(int) $a->unit_id] ?? 'Lintas Unit') ?>
                                            <?= $a->bank_idbank ? ' · ' . esc($a->bank_idbank) : '' ?>
                                            <?php if ($shared) : ?>
                                                <span class="kb-badge kb-badge-muted">Shared</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($a->tipe === 'KAS') : ?>
                                            <span class="kb-badge kb-badge-blue">Kas</span>
                                        <?php else : ?>
                                            <span class="kb-badge kb-badge-amber">Bank</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($a->status === 'aktif') : ?>
                                            <span class="kb-badge kb-badge-green">Aktif</span>
                                        <?php else : ?>
                                            <span class="kb-badge kb-badge-red">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end kb-amount"><?= $rp($fisik) ?></td>
                                    <?php if ($unitDipilih) : ?>
                                        <td class="text-end kb-amount <?= ($unitSaldo < 0) ? 'text-danger' : 'text-primary' ?>">
                                            <?= $rp($unitSaldo) ?>
                                            <?php if ($unitSaldo < 0) : ?>
                                                <span class="kb-badge kb-badge-red ms-1">Minus</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="kb-card h-100 d-flex flex-column">
            <div class="kb-card-header">
                <div>
                    <h5 class="kb-card-title mb-0">Arus Uang per Jenis</h5>
                    <span class="kb-card-sub">Akumulasi transaksi pada periode terpilih.</span>
                </div>
            </div>
            <div class="table-responsive flex-grow-1">
                <table class="table kb-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Jenis Pergerakan</th>
                            <th class="text-end">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $jenisLabels = [
                            'PEMASUKAN'            => ['Pemasukan Operasional', 'bi:arrow-down-left-circle-fill', 'text-success', 'bg-success-subtle'],
                            'PENGELUARAN'          => ['Pengeluaran Operasional', 'bi:arrow-up-right-circle-fill', 'text-danger', 'bg-danger-subtle'],
                            'TRANSFER_INTERNAL'    => ['Transfer Internal', 'bi:arrow-left-right', 'text-info', 'bg-info-subtle'],
                            'PEMBAYARAN_ANTAR_UNIT' => ['Pembayaran Antar Cabang', 'bi:building-fill-check', 'text-warning-emphasis', 'bg-warning-subtle'],
                        ];
                        ?>
                        <?php foreach ($jenisLabels as $k => [$label, $icon, $color, $bg]) : ?>
                            <?php $val = (int) ($ringkasan[$k] ?? 0); ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="p-1 rounded-1 d-flex align-items-center justify-content-center <?= $bg ?> <?= $color ?>">
                                            <iconify-icon icon="<?= $icon ?>" class="kb-ico"></iconify-icon>
                                        </span>
                                        <span class="fw-medium"><?= $label ?></span>
                                    </div>
                                </td>
                                <td class="text-end kb-amount <?= $k === 'PENGELUARAN' ? 'text-danger' : '' ?>"><?= $rp($val) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-3 py-2 border-top kb-meta d-flex align-items-start gap-2">
                <iconify-icon icon="bi:info-circle-fill" class="text-info flex-shrink-0 mt-1"></iconify-icon>
                <div>
                    <strong>Cut-Off Finance:</strong> Dihitung dari transaksi operasional sejak
                    <strong><?= esc(\App\Services\Finance\FinanceScopeService::cutoffDate()) ?></strong>.
                </div>
            </div>
        </div>
    </div>
</div>
