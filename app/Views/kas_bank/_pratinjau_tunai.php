<?php

/**
 * Pratinjau setor tunai & penarikan tunai.
 *
 * Dipakai kedua halaman. Pratinjau ini read-only: tidak ada efek ke saldo.
 * Semua angkanya dikirim controller dari KasBankCutoffService supaya yang
 * tampil di layar sama dengan angka yang dipakai service saat menyimpan.
 *
 * Dua hal yang dijaga di sini:
 *
 *   1. "Shared account" ditulis terbuka. Rekening bersama tidak milik satu
 *      unit, jadi tidak boleh ditampilkan seolah-olah milik unit pemohon.
 *
 *   2. Saldo yang belum terverifikasi ditampilkan sebagai "belum tersedia",
 *      bukan Rp 0. Angka 0 yang belum diverifikasi artinya "belum tahu",
 *      bukan "tidak ada uang". Menampilkan 0 membuat user salah mengambil
 *      keputusan.
 *
 * Layout sengaja tidak memakai kb-tile-sm / kb-pane-head / kb-empty. Semua
 * gaya ada di blok <style> di bawah (awalan "pv-") supaya tampilan tidak
 * bergantung pada _theme.
 *
 * @var array<string, mixed>|null $preview
 * @var array<string, mixed>      $cutoff_info
 */
$rp         = static fn($n) => 'Rp ' . number_format((float) ($n ?? 0), 0, ',', '.');
$isSetor    = ($preview['arah'] ?? '') === 'setor';
$arahLabel  = $isSetor ? 'Setor tunai' : 'Penarikan tunai';
$unitNama   = $preview['unit_nama'] ?? null;
$bank       = $preview['bank'] ?? [];
$kas        = $preview['kas'] ?? [];
$nominal    = (int) ($preview['nominal'] ?? 0);
$blokir     = $preview['blokir'] ?? [];
?>

<style>
    .pv {
        display: block;
        width: 100%;
        flex: 1 1 100%;
        min-width: 0;
        box-sizing: border-box;
    }

    .pv-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .25rem 1.5rem;
        padding: .85rem 0;
        border-top: 1px solid var(--bs-border-color, rgba(128, 128, 128, .25));
    }

    .pv-row:first-of-type {
        border-top: 0;
        padding-top: 0;
    }

    .pv-name {
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    .pv-sub {
        margin-top: .15rem;
        font-size: .82rem;
        color: var(--bs-secondary-color, #6c757d);
    }

    .pv-side {
        text-align: right;
    }

    .pv-amount {
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .pv-amount.is-in {
        color: var(--bs-success, #198754);
    }

    .pv-amount.is-out {
        color: var(--bs-danger, #dc3545);
    }

    .pv-full {
        grid-column: 1 / -1;
    }

    .pv-status {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .5rem;
    }

    .pv-unit {
        display: flex;
        justify-content: flex-end;
        margin-bottom: .75rem;
    }

    .pv-empty {
        display: block;
        width: 100%;
        box-sizing: border-box;
        padding: 1.75rem 1rem;
        text-align: center;
        color: var(--bs-secondary-color, #6c757d);
        border: 1px dashed var(--bs-border-color, rgba(128, 128, 128, .35));
        border-radius: .5rem;
    }

    .pv-empty iconify-icon {
        display: block;
        margin: 0 auto .5rem;
        font-size: 1.75rem;
    }

    .pv-empty p {
        max-width: 36rem;
        margin: 0 auto;
        overflow-wrap: break-word;
    }

    @media (max-width: 575.98px) {
        .pv-row {
            grid-template-columns: 1fr;
        }

        .pv-side {
            text-align: left;
        }
    }
</style>

<div class="pv">
    <?php if ($preview !== null) : ?>

        <?php if (! empty($blokir)) : ?>
            <div class="alert alert-danger py-2 px-3 mb-3" role="alert">
                <div class="d-flex align-items-start gap-2">
                    <iconify-icon icon="bi:shield-exclamation" class="kb-ico flex-shrink-0 mt-1"></iconify-icon>
                    <div>
                        <strong><?= esc($arahLabel) ?> belum bisa diproses</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            <?php foreach ($blokir as $alasan) : ?>
                                <li><?= esc($alasan) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($unitNama !== null) : ?>
            <div class="pv-unit">
                <span class="kb-badge kb-badge-muted">Unit transaksi: <?= esc($unitNama) ?></span>
            </div>
        <?php endif; ?>

        <?php /* ---------- Kaki kas ---------- */ ?>
        <div class="pv-row">
            <div>
                <div class="pv-name">
                    Kas <?= esc($unitNama ?? '—') ?>
                    <?php if (($kas['nama'] ?? null) !== null) : ?>
                        <span class="kb-badge kb-badge-muted ms-1"><?= esc($kas['nama']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="pv-sub">Rekening laci kas unit</div>
            </div>
            <div class="pv-side">
                <div class="pv-amount <?= $isSetor ? 'is-out' : 'is-in' ?>">
                    <?= $isSetor ? '−' : '+' ?> <?= $rp($nominal) ?>
                </div>
                <div class="pv-sub">
                    <?= ($kas['saldo'] ?? null) === null ? 'Belum dapat dihitung' : 'Saldo ' . $rp($kas['saldo']) ?>
                </div>
            </div>
        </div>

        <?php /* ---------- Kaki bank ---------- */ ?>
        <div class="pv-row">
            <div>
                <div class="pv-name">
                    <?= esc($bank['nama'] ?? '—') ?>
                    <?php if (! empty($bank['norek'])) : ?>
                        <span class="kb-mono ms-1"><?= esc($bank['norek']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="pv-sub">
                    <?php if (! empty($bank['is_shared'])) : ?>
                        <span class="kb-badge kb-badge-blue">Shared account</span>
                    <?php else : ?>
                        Rekening bank
                    <?php endif; ?>
                </div>
            </div>
            <div class="pv-side">
                <div class="pv-amount <?= $isSetor ? 'is-in' : 'is-out' ?>">
                    <?= $isSetor ? '+' : '−' ?> <?= $rp($nominal) ?>
                </div>
                <div class="pv-sub">
                    <?= ($bank['saldo'] ?? null) === null ? 'Belum tersedia' : 'Saldo fisik ' . $rp($bank['saldo']) ?>
                </div>
            </div>
        </div>

        <?php /* ---------- Status statement ---------- */ ?>
        <?php if (! empty($bank['akun_id'])) : ?>
            <div class="pv-row">
                <div class="pv-full">
                    <div class="pv-status">
                        <span class="pv-name">Saldo statement cut-off</span>
                        <?php if (! empty($bank['terverifikasi'])) : ?>
                            <span class="kb-badge kb-badge-green">Terverifikasi</span>
                        <?php else : ?>
                            <span class="kb-badge kb-badge-amber">Belum diverifikasi</span>
                        <?php endif; ?>
                    </div>
                    <div class="pv-sub">
                        Statement <?= esc($cutoff_info['tanggal_cutoff'] ?? '') ?>
                        <?php if (empty($bank['terverifikasi'])) : ?>
                            — koran bank belum diverifikasi finance, jadi saldo fisik di atas
                            <strong>belum tersedia</strong>. Nilai placeholder
                            <span class="kb-mono">0</span> tidak ditampilkan sebagai saldo faktual.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php /* ---------- Penempatan unit ---------- */ ?>
        <div class="pv-row">
            <?php if (! empty($bank['is_shared'])) : ?>
                <div>
                    <div class="pv-name">Alokasi unit — <?= esc($unitNama ?? '—') ?></div>
                    <div class="pv-sub">
                        Rekening bersama: dana ini menjadi hak unit tersebut, bukan saldo
                        rekening milik satu unit.
                    </div>
                </div>
                <div class="pv-side">
                    <?php if ($isSetor) : ?>
                        <div class="pv-amount is-in">+ <?= $rp($nominal) ?></div>
                        <div class="pv-sub">
                            <?= $rp($preview['posisi_unit_sebelum'] ?? 0) ?> &rarr;
                            <?= $rp($preview['posisi_unit_setelah'] ?? 0) ?>
                        </div>
                    <?php else : ?>
                        <div class="pv-amount is-out">− <?= $rp($nominal) ?></div>
                        <div class="pv-sub">
                            <?= $rp($preview['entitlement_sebelum'] ?? 0) ?> &rarr;
                            <?= $rp($preview['entitlement_setelah'] ?? 0) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="pv-full pv-sub">
                    Rekening ini bukan shared account, jadi dana tidak dibagi ke unit lain.
                    Seluruhnya untuk unit <?= esc($unitNama ?? '—') ?>.
                </div>
            <?php endif; ?>
        </div>

    <?php else : ?>
        <div class="pv-empty">
            <iconify-icon icon="bi:calculator"></iconify-icon>
            <p>
                Isi tanggal, rekening, dan nominal, lalu tekan <strong>Lihat pratinjau</strong>
                untuk melihat dampak saldo sebelum disimpan.
            </p>
        </div>
    <?php endif; ?>
</div>
