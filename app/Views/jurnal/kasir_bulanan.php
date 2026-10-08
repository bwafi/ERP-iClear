<?= $this->include('jurnal/_tutup_kasir_theme') ?>

<?php
$fmt = fn($v) => number_format((int)($v ?? 0), 0, ',', '.');
$totalPengeluaran = ($pengeluarancash ?? 0) + ($pengeluarantf ?? 0);
?>

<div class="tutup-kasir kb-page">
    <header class="tk-header">
        <div class="container-fluid py-3">
            <nav aria-label="breadcrumb" class="d-flex align-items-center flex-wrap gap-3 mb-2">
                <ol class="breadcrumb mb-0 me-auto">
                    <li class="breadcrumb-item">
                        <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Rekap Kasir</li>
                </ol>
            </nav>
        </div>
    </header>

    <main class="container-fluid py-4">

        <section class="tk-section mb-4">
            <header class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-2">
                    <iconify-icon icon="solar:calendar-bold" width="22" class="text-primary"></iconify-icon>
                    <h5 class="mb-0 fw-semibold">Rekap Harian Kasir</h5>
                </div>

                <form method="get" class="d-flex gap-2 flex-wrap align-items-center">
                    <input type="date"
                        name="tanggal"
                        class="form-control form-control-sm"
                        value="<?= $tanggal ?>"
                        style="width: auto; border-radius: var(--tk-radius); font-variant-numeric: tabular-nums;">

                    <select name="unit"
                        class="form-select form-select-sm"
                        style="width: auto; border-radius: var(--tk-radius);">
                        <?php foreach ($list_unit as $u): ?>
                            <option value="<?= (int)$u['idunit'] ?>"
                                <?= (string)($selected_unit ?? '') === (string)$u['idunit'] ? 'selected' : '' ?>>
                                <?= $u['NAMA_UNIT'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit"
                        class="tk-btn tk-btn--primary"
                        style="padding: 0.4rem 1rem; font-size: 0.875rem;">
                        <iconify-icon icon="solar:calendar-search-bold" width="16"></iconify-icon>
                        Filter
                    </button>
                </form>
            </header>

            <?php if ($tutupkasir): ?>
                <div class="p-3 border-bottom" style="background: var(--bs-secondary-bg, #f8f9fa);">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="d-flex align-items-center gap-2">
                            <span style="
                                display: inline-flex;
                                align-items: center;
                                gap: 0.375rem;
                                padding: 0.25rem 0.75rem;
                                border-radius: 2rem;
                                font-size: 0.8125rem;
                                font-weight: 600;
                                background: var(--bs-success-bg-subtle, #dffff3);
                                color: var(--bs-success, #198754);
                            ">
                                <iconify-icon icon="solar:check-circle-bold" width="14"></iconify-icon>
                                Ditutup
                            </span>
                        </div>
                        <div style="font-size: 0.875rem; color: var(--tk-muted);">
                            <span style="font-variant-numeric: tabular-nums; font-weight: 600; color: var(--bs-body-color);"><?= date('d M Y', strtotime($tanggal)) ?></span>
                            &middot; Oleh <strong><?= $tutupkasir->NAMA_AKUN ?? '-' ?></strong>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!$tutupkasir): ?>

            <div class="tk-alert tk-alert--warning" role="alert">
                <iconify-icon icon="solar:info-circle-bold" width="20"></iconify-icon>
                <div>
                    <div class="tk-alert__title">Data tidak ditemukan</div>
                    <div class="tk-alert__desc">Tutup kasir untuk tanggal <strong><?= $tanggal ?></strong> belum tersedia.</div>
                </div>
            </div>

        <?php else: ?>

            <section class="tk-hero mb-4">
                <div class="tk-hero__balance tk-card">
                    <div class="d-flex align-items-center mb-3">
                        <iconify-icon icon="solar:wallet-bold" width="28" class="text-primary me-2"></iconify-icon>
                        <h5 class="mb-0 fw-semibold">Saldo Awal</h5>
                    </div>
                    <div class="tk-balance__amount">
                        Rp <?= $fmt(($kas_awalcash ?? 0) + ($kas_awaltf ?? 0)) ?>
                    </div>
                    <div class="tk-balance__breakdown">
                        Kas <strong class="tk-tabular">Rp <?= $fmt($kas_awalcash) ?></strong>
                        &middot;
                        Transfer <strong class="tk-tabular">Rp <?= $fmt($kas_awaltf) ?></strong>
                    </div>
                </div>

                <div class="tk-card" style="padding: 1.5rem;">
                    <div class="d-flex align-items-center mb-3">
                        <iconify-icon icon="solar:calculator-bold" width="28" class="text-success me-2"></iconify-icon>
                        <h5 class="mb-0 fw-semibold">Saldo Akhir</h5>
                    </div>
                    <div class="tk-balance__amount" style="color: var(--tk-success);">
                        Rp <?= $fmt(($kas_akhircash ?? 0) + ($kas_akhirtf ?? 0)) ?>
                    </div>
                    <div class="tk-balance__breakdown">
                        Kas <strong class="tk-tabular">Rp <?= $fmt($kas_akhircash) ?></strong>
                        &middot;
                        Transfer <strong class="tk-tabular">Rp <?= $fmt($kas_akhirtf) ?></strong>
                    </div>
                </div>
            </section>

            <section class="tk-section mb-4">
                <header class="p-3 border-bottom">
                    <h5 class="mb-0 fw-semibold d-flex align-items-center gap-2">
                        <iconify-icon icon="solar:document-text-bold" width="22"></iconify-icon>
                        Ringkasan Pendapatan & Pengeluaran
                    </h5>
                </header>
                <div class="p-3">
                    <div class="tk-summary">
                        <article class="tk-summary__item tk-summary__item--income">
                            <div class="tk-summary__header">
                                <span class="tk-summary__title">Pendapatan Cash</span>
                                <div class="tk-summary__icon"><iconify-icon icon="solar:money-bag-bold" width="28"></iconify-icon></div>
                            </div>
                            <div class="tk-summary__value tk-tabular">Rp <?= $fmt($cash) ?></div>
                        </article>

                        <article class="tk-summary__item tk-summary__item--income">
                            <div class="tk-summary__header">
                                <span class="tk-summary__title">Pendapatan Transfer</span>
                                <div class="tk-summary__icon"><iconify-icon icon="solar:card-transfer-bold" width="28"></iconify-icon></div>
                            </div>
                            <div class="tk-summary__value tk-tabular">Rp <?= $fmt($transfer) ?></div>
                        </article>

                        <article class="tk-summary__item tk-summary__item--income-total">
                            <div class="tk-summary__header">
                                <span class="tk-summary__title">Total Pendapatan</span>
                                <div class="tk-summary__icon"><iconify-icon icon="solar:wallet-money-bold" width="28"></iconify-icon></div>
                            </div>
                            <div class="tk-summary__value tk-tabular">Rp <?= $fmt($total_pendapatan) ?></div>
                        </article>

                        <article class="tk-summary__item tk-summary__item--expense">
                            <div class="tk-summary__header">
                                <span class="tk-summary__title">Pengeluaran Cash</span>
                                <div class="tk-summary__icon"><iconify-icon icon="solar:bill-list-bold" width="28"></iconify-icon></div>
                            </div>
                            <div class="tk-summary__value tk-tabular">Rp <?= $fmt($pengeluarancash) ?></div>
                        </article>

                        <article class="tk-summary__item tk-summary__item--expense">
                            <div class="tk-summary__header">
                                <span class="tk-summary__title">Pengeluaran Transfer</span>
                                <div class="tk-summary__icon"><iconify-icon icon="solar:bill-list-bold" width="28"></iconify-icon></div>
                            </div>
                            <div class="tk-summary__value tk-tabular">Rp <?= $fmt($pengeluarantf) ?></div>
                        </article>

                        <article class="tk-summary__item tk-summary__item--expense-total">
                            <div class="tk-summary__header">
                                <span class="tk-summary__title">Total Pengeluaran</span>
                                <div class="tk-summary__icon"><iconify-icon icon="solar:bill-list-bold" width="28"></iconify-icon></div>
                            </div>
                            <div class="tk-summary__value tk-tabular">Rp <?= $fmt($totalPengeluaran) ?></div>
                        </article>
                    </div>
                </div>
            </section>

            <?php
            $selisih = ($tutupkasir->cash_laci ?? 0) - ($tutupkasir->akhir_cash ?? 0);
            $selisihColor = $selisih == 0 ? 'var(--bs-info, #0dcaf0)' : ($selisih > 0 ? 'var(--tk-success)' : 'var(--tk-expense)');
            $selisihBg = $selisih == 0 ? 'var(--bs-info-bg-subtle, #e1f5fa)' : ($selisih > 0 ? 'var(--bs-success-bg-subtle, #dffff3)' : 'var(--bs-danger-bg-subtle, #ffede9)');
            $selisihIcon = $selisih == 0 ? 'solar:check-circle-bold' : ($selisih > 0 ? 'solar:arrow-up-bold' : 'solar:arrow-down-bold');
            $selisihLabel = $selisih == 0 ? 'Cocok' : ($selisih > 0 ? 'Lebih' : 'Kurang');
            ?>

            <details class="tk-details tk-card mb-4">
                <summary>
                    <iconify-icon icon="solar:safe-2-bold" width="22"></iconify-icon>
                    Rekonsiliasi Fisik
                </summary>
                <div class="tk-details__content">
                    <div class="tk-details__row">
                        <span class="tk-details__label">Saldo Akhir Kas (Sistem)</span>
                        <span class="tk-details__value tk-tabular">Rp <?= $fmt($kas_akhircash) ?></span>
                    </div>
                    <div class="tk-details__row">
                        <span class="tk-details__label">Uang Fisik di Laci</span>
                        <span class="tk-details__value tk-tabular" style="color: var(--bs-warning, #f59e0b);">Rp <?= $fmt($tutupkasir->cash_laci ?? 0) ?></span>
                    </div>
                    <div class="tk-details__row" style="padding-top: 0.75rem; padding-bottom: 0.75rem;">
                        <span class="tk-details__label"><strong>Selisih (Fisik - Sistem)</strong></span>
                        <span class="tk-details__value tk-tabular d-flex align-items-center gap-2" style="color: <?= $selisihColor ?>;">
                            <iconify-icon icon="<?= $selisihIcon ?>" width="16"></iconify-icon>
                            <strong>Rp <?= $fmt($selisih) ?></strong>
                            <span style="
                                font-size: 0.75rem;
                                font-weight: 600;
                                padding: 0.125rem 0.5rem;
                                border-radius: 2rem;
                                background: <?= $selisihBg ?>;
                            "><?= $selisihLabel ?></span>
                        </span>
                    </div>
                </div>
            </details>

            <input type="hidden" name="awal_cash" value="<?= $kas_awalcash ?? 0 ?>">
            <input type="hidden" name="awal_transfer" value="<?= $kas_awaltf ?? 0 ?>">
            <input type="hidden" name="akhir_cash" value="<?= $kas_akhircash ?? 0 ?>">
            <input type="hidden" name="akhir_transfer" value="<?= $kas_akhirtf ?? 0 ?>">
            <input type="hidden" name="pendapatan_cash" value="<?= $cash ?? 0 ?>">
            <input type="hidden" name="pendapatan_transfer" value="<?= $transfer ?? 0 ?>">
            <input type="hidden" name="pengeluaran_cash" value="<?= $pengeluarancash ?? 0 ?>">
            <input type="hidden" name="pengeluaran_transfer" value="<?= $pengeluarantf ?? 0 ?>">

        <?php endif; ?>

    </main>
</div>
