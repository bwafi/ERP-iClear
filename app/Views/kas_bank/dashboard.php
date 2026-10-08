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

<div class="kb">
    <!-- Filter -->
    <form method="get" action="<?= base_url('kas_bank') ?>" class="kb-filter">
        <?php if (($bisa_pilih_unit ?? false)) : ?>
            <div class="kb-field">
                <label for="f-unit" class="kb-label">Filter Cabang</label>
                <select name="unit_id" id="f-unit" class="form-select kb-input kb-filter-select">
                    <option value="">Semua Cabang (Konsolidasi)</option>
                    <?php foreach (($unit ?? []) as $u) : ?>
                        <option value="<?= (int) $u->idunit ?>" <?= ($unit_terpilih ?? 0) == $u->idunit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="kb-field">
            <label for="f-dari" class="kb-label">Dari Tanggal</label>
            <input type="date" id="f-dari" name="tanggal_awal" class="form-control kb-input" value="<?= esc($f['tanggal_awal'] ?? '') ?>">
        </div>
        <div class="kb-field">
            <label for="f-sampai" class="kb-label">Sampai Tanggal</label>
            <input type="date" id="f-sampai" name="tanggal_akhir" class="form-control kb-input" value="<?= esc($f['tanggal_akhir'] ?? '') ?>">
        </div>
        <div class="kb-field">
            <label for="f-akun" class="kb-label">Rekening</label>
            <select name="akun_id" id="f-akun" class="form-select kb-input kb-filter-select">
                <option value="">Semua Rekening</option>
                <?php foreach (($akun_kas_bank ?? []) as $a) : ?>
                    <option value="<?= (int) $a->idakun_kas_bank ?>" <?= (int) ($f['akun_id'] ?? 0) === (int) $a->idakun_kas_bank ? 'selected' : '' ?>>
                        <?= esc($a->nama_akun) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="kb-field">
            <button class="btn btn-primary kb-btn" type="submit">
                <iconify-icon icon="bi:funnel-fill" class="kb-ico"></iconify-icon>
                Terapkan Filter
            </button>
        </div>
    </form>

    <!-- Panduan pembacaan saldo -->
    <div class="kb-banner">
        <div class="kb-banner-icon text-primary">
            <iconify-icon icon="bi:info-circle-fill"></iconify-icon>
        </div>
        <div class="kb-banner-content">
            <strong class="text-emphasis">Panduan Pembacaan Saldo:</strong>
            <div class="text-secondary mt-1">
                <strong>Saldo Nyata</strong> adalah nominal fisik di rekening/laci.
                <?php if ($unitDipilih) : ?>
                    Cabang aktif: <strong class="text-primary">Hak Unit Ini</strong> mencerminkan porsi kepemilikan unit dari total fisik.
                <?php else : ?>
                    Pilih cabang tertentu pada filter untuk meninjau <strong class="text-primary">Hak Pakai per Unit</strong>.
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Metrik -->
    <div class="kb-stats">
        <div class="kb-stat">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <span class="kb-stat-label">Kas Tunai<?= $unitDipilih ? ' (Unit)' : '' ?></span>
                <span class="kb-tile bg-primary-subtle text-primary">
                    <iconify-icon icon="bi:cash-stack"></iconify-icon>
                </span>
            </div>
            <span class="kb-stat-value kb-num"><?= $rp($total_kas ?? 0) ?></span>
            <span class="kb-stat-hint">
                <?= $unitDipilih ? 'Fisik semua unit: ' . $rp($total_fisik_kas ?? 0) : 'Total fisik laci & brankas' ?>
            </span>
        </div>

        <div class="kb-stat">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <span class="kb-stat-label">Saldo Bank<?= $unitDipilih ? ' (Unit)' : '' ?></span>
                <span class="kb-tile bg-warning-subtle text-warning-emphasis">
                    <iconify-icon icon="bi:bank2"></iconify-icon>
                </span>
            </div>
            <span class="kb-stat-value kb-num"><?= $rp($total_bank ?? 0) ?></span>
            <span class="kb-stat-hint">
                <?= $unitDipilih ? 'Fisik semua unit: ' . $rp($total_fisik_bank ?? 0) : 'Total saldo terdaftar di bank' ?>
            </span>
        </div>

        <div class="kb-stat">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <span class="kb-stat-label">Total Posisi<?= $unitDipilih ? ' (Unit)' : '' ?></span>
                <span class="kb-tile bg-info-subtle text-info">
                    <iconify-icon icon="bi:wallet-fill"></iconify-icon>
                </span>
            </div>
            <span class="kb-stat-value kb-num"><?= $rp($total_semua ?? 0) ?></span>
            <span class="kb-stat-hint">
                <?= $unitDipilih ? 'Fisik semua unit: ' . $rp($total_fisik_semua ?? 0) : 'Total konsolidasi Kas + Bank' ?>
            </span>
        </div>

        <div class="kb-stat">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <span class="kb-stat-label">Arus Kas Bersih</span>
                <span class="kb-tile <?= $netFlow < 0 ? 'bg-danger-subtle text-danger' : ($netFlow > 0 ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary') ?>">
                    <iconify-icon icon="<?= $netFlow < 0 ? 'bi:graph-down-arrow' : ($netFlow > 0 ? 'bi:graph-up-arrow' : 'bi:dash-lg') ?>"></iconify-icon>
                </span>
            </div>
            <span class="kb-stat-value kb-num <?= $netFlow < 0 ? 'text-danger' : ($netFlow > 0 ? 'text-success' : '') ?>"><?= $rp($netFlow) ?></span>
            <span class="kb-stat-hint">Pemasukan &minus; Pengeluaran Ops.</span>
        </div>
    </div>

    <!-- Peringatan alokasi melebihi saldo fisik -->
    <?php if ($hasWarning) : ?>
        <div class="kb-banner is-danger">
            <div class="kb-banner-icon text-danger">
                <iconify-icon icon="bi:exclamation-triangle-fill"></iconify-icon>
            </div>
            <div class="kb-banner-content">
                <strong>Alokasi Saldo Melebihi Saldo Fisik Rekening!</strong>
                <div class="mt-1">Beberapa akun bank memiliki total alokasi cabang yang melampaui saldo riil:</div>
                <div class="kb-alloc mt-2">
                    <?php foreach (($warning_alokasi ?? []) as $ka) : ?>
                        <span class="kb-badge kb-badge-red kb-mono"><?= esc($akunMap[$ka] ?? '#' . $ka) ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="mt-2">
                    Penyesuaian dapat dilakukan di menu
                    <a href="<?= base_url('kas_bank/akun') ?>" class="fw-semibold text-decoration-underline">Rekening &amp; Saldo</a> &rarr; Hak Unit.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Diagnosa konfigurasi rekening yang belum bisa dipakai transaksi -->
    <?= $this->include('kas_bank/_diagnostik') ?>

    <div class="kb-split">
        <!-- Saldo per rekening fisik -->
        <section class="kb-card">
            <div class="kb-card-header">
                <div>
                    <div class="kb-card-title">Saldo per Rekening Fisik</div>
                    <div class="kb-card-sub"><?= $unitDipilih ? 'Rincian kepemilikan unit terhadap saldo riil.' : 'Rincian saldo seluruh kas &amp; bank terdaftar.' ?></div>
                </div>
                <div class="kb-alloc">
                    <span class="kb-badge kb-badge-blue">KAS</span>
                    <span class="kb-badge kb-badge-amber">BANK</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table kb-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="min-width: 160px;">Nama Rekening</th>
                            <th>Tipe</th>
                            <th>Status</th>
                            <th class="text-end" style="min-width: 120px;">Saldo Nyata</th>
                            <?php if ($unitDipilih) : ?>
                                <th class="text-end" style="min-width: 120px;">Hak Unit Ini</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($akun_kas_bank)) : ?>
                            <tr>
                                <td colspan="<?= $unitDipilih ? 5 : 4 ?>" class="kb-empty">
                                    <iconify-icon icon="bi:inbox" class="kb-ico-lg d-block mx-auto mb-1"></iconify-icon>
                                    Belum ada rekening terdaftar.
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach (($akun_kas_bank ?? []) as $a) : ?>
                                <?php
                                $akunIdRow   = (int) $a->idakun_kas_bank;
                                $shared      = (int) ($a->is_shared ?? 0) === 1;
                                // Account scope: unit yang benar-benar punya hak
                                // atas rekening ini (alokasi), bukan "semua unit".
                                $entitled    = $akun_scope[$akunIdRow] ?? [];
                                $entitledNama = implode(', ', array_map(
                                    static fn ($uid) => (string) ($unitMap[(int) $uid] ?? ('Unit ' . $uid)),
                                    $entitled
                                ));
                                $fisik = $saldo_fisik_per_akun[$akunIdRow] ?? 0;
                                $unitSaldo = $saldo_unit_per_akun[$akunIdRow] ?? 0;
                                ?>
                                <tr class="kb-row-main">
                                    <td>
                                        <div class="kb-name">
                                            <?= esc($a->nama_akun) ?>
                                            <?php if ($shared) : ?>
                                                <span class="kb-badge kb-badge-muted"><?= $entitledNama !== '' ? 'Shared · ' . esc($entitledNama) : 'Shared · belum dialokasikan' ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="kb-sub kb-meta">
                                            <?php if ($shared) : ?>
                                                Dialokasikan ke: <?= $entitledNama !== '' ? esc($entitledNama) : 'belum ada unit' ?>
                                            <?php else : ?>
                                                <?= esc($unitMap[(int) $a->unit_id] ?? 'Tanpa unit') ?>
                                            <?php endif; ?>
                                            <?= $a->bank_idbank ? ' · ' . esc($a->bank_idbank) : '' ?>
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
                                                <span class="kb-badge kb-badge-red">Minus</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Arus uang per jenis -->
        <section class="kb-card">
            <div class="kb-card-header">
                <div>
                    <div class="kb-card-title">Arus Uang per Jenis</div>
                    <div class="kb-card-sub">Akumulasi transaksi pada periode terpilih.</div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table kb-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Jenis Pergerakan</th>
                            <th class="text-end" style="min-width: 120px;">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $jenisLabels = [
                            'PEMASUKAN'             => ['Pemasukan Operasional', 'bi:arrow-down-left-circle-fill', 'kb-badge-green', 'bg-success-subtle', 'text-success'],
                            'PENGELUARAN'           => ['Pengeluaran Operasional', 'bi:arrow-up-right-circle-fill', 'kb-badge-red', 'bg-danger-subtle', 'text-danger'],
                            'TRANSFER_INTERNAL'     => ['Transfer Internal', 'bi:arrow-left-right', 'kb-badge-blue', 'bg-info-subtle', 'text-info'],
                            'PEMBAYARAN_ANTAR_UNIT' => ['Pembayaran Antar Cabang', 'bi:building-fill-check', 'kb-badge-amber', 'bg-warning-subtle', 'text-warning-emphasis'],
                        ];
                        ?>
                        <?php foreach ($jenisLabels as $k => [$label, $icon, $badge, $bg, $color]) : ?>
                            <?php $val = (int) ($ringkasan[$k] ?? 0); ?>
                            <tr class="kb-row-main">
                                <td>
                                    <div class="kb-name">
                                        <span class="kb-tile-sm <?= $bg ?> <?= $color ?>">
                                            <iconify-icon icon="<?= $icon ?>"></iconify-icon>
                                        </span>
                                        <span><?= $label ?></span>
                                    </div>
                                </td>
                                <td class="text-end kb-amount <?= $k === 'PENGELUARAN' ? 'text-danger' : '' ?>"><?= $rp($val) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="kb-card-footer">
                <iconify-icon icon="bi:info-circle-fill" class="text-info kb-ico"></iconify-icon>
                <div>
                    <strong>Cut-Off Finance:</strong> Dihitung dari transaksi operasional sejak
                    <strong><?= esc(\App\Services\Finance\FinanceScopeService::kasBankCutoffDate()) ?></strong>.
                </div>
            </div>
        </section>
    </div>
</div>
