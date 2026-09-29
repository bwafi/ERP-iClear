<?php
/**
 * Navigasi bersama Kas & Bank — Operational UI v4.
 * Dipakai 4 halaman: dashboard, akun, transfer, antar_unit.
 */
$__uri = service('uri');
$__seg2 = $__uri->getSegment(2) ?? '';
$__active = $__seg2 === '' ? 'ringkasan'
    : ($__seg2 === 'akun' ? 'rekening'
    : ($__seg2 === 'transfer' ? 'pindah'
    : (in_array($__seg2, ['antar-unit', 'antar_unit'], true) ? 'antar' : 'ringkasan')));

$__ctxBadges = [
    'ringkasan' => '',
    'rekening'  => '',
    'pindah'    => '',
    'antar'     => '',
];
if (isset($akun_kas_bank) && is_array($akun_kas_bank)) {
    $aktifCount = array_reduce($akun_kas_bank, fn($c, $a) => $c + (($a->status ?? '') === 'aktif' ? 1 : 0), 0);
    $kasCount   = array_reduce($akun_kas_bank, fn($c, $a) => $c + (($a->tipe ?? '') === 'KAS' ? 1 : 0), 0);
    $bankCount  = array_reduce($akun_kas_bank, fn($c, $a) => $c + (($a->tipe ?? '') === 'BANK' ? 1 : 0), 0);
    $__ctxBadges['rekening'] = $aktifCount > 0 ? "<span class='kb-pill kb-pill-primary'>{$aktifCount} Rekening</span>" : '';
    $__ctxBadges['pindah']   = ($kasCount + $bankCount) > 1 ? "<span class='kb-pill kb-pill-info'>{$kasCount} Kas · {$bankCount} Bank</span>" : '';
}
if (isset($hp_hutang) && is_array($hp_hutang)) {
    $openHutang = array_reduce($hp_hutang, fn($c, $h) => $c + (((int)($h->sisa ?? 0) > 0 && ($h->status ?? '') !== 'lunas') ? 1 : 0), 0);
    $__ctxBadges['antar'] = $openHutang > 0 ? "<span class='kb-pill kb-pill-danger'>{$openHutang} Hutang Open</span>" : '';
}

$__tabs = [
    'ringkasan' => [
        'url'   => base_url('kas_bank'),
        'icon'  => 'bi:speedometer2',
        'label' => 'Ringkasan',
        'badge' => $__ctxBadges['ringkasan'],
    ],
    'rekening' => [
        'url'   => base_url('kas_bank/akun'),
        'icon'  => 'bi:bank2',
        'label' => 'Rekening & Saldo',
        'badge' => $__ctxBadges['rekening'],
    ],
    'pindah' => [
        'url'   => base_url('kas_bank/transfer'),
        'icon'  => 'bi:arrow-left-right',
        'label' => 'Pindah Saldo',
        'badge' => $__ctxBadges['pindah'],
    ],
    'antar' => [
        'url'   => base_url('kas_bank/antar-unit'),
        'icon'  => 'bi:building-check',
        'label' => 'Bayar Antar Unit',
        'badge' => $__ctxBadges['antar'],
    ],
];

$__titles = [
    'ringkasan' => ['Ringkasan Kas & Bank', 'Oversight posisi uang tunai, saldo rekening bank, dan arus kas bersih operasional.'],
    'rekening'  => ['Rekening & Saldo Awal', 'Kelola master rekening fisik, saldo awal, dan pembagian hak alokasi per cabang.'],
    'pindah'    => ['Pindah Saldo (Transfer Internal)', 'Transfer antar rekening milik sendiri tanpa memengaruhi laporan laba rugi.'],
    'antar'     => ['Pembayaran Antar Unit (H/P)', 'Penyelesaian hutang/piutang mutasi stok antar cabang (real transfer vs atribusi).'],
];
$__t = $__titles[$__active] ?? $__titles['ringkasan'];
?>

<div class="kb-header-card mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
        <div class="d-flex align-items-center gap-3">
            <div class="kb-header-icon">
                <iconify-icon icon="bi:wallet2"></iconify-icon>
            </div>
            <div>
                <h4 class="kb-header-title mb-1"><?= esc($__t[0]) ?></h4>
                <p class="kb-header-desc mb-0"><?= esc($__t[1]) ?></p>
            </div>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 kb-breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('kas_bank') ?>">Kas &amp; Bank</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= esc($__t[0]) ?></li>
            </ol>
        </nav>
    </div>

    <div class="kb-nav-tabs" role="tablist" aria-label="Menu Kas dan Bank">
        <?php foreach ($__tabs as $__key => $__tab) : ?>
            <?php $__isActive = $__key === $__active; ?>
            <a href="<?= $__tab['url'] ?>"
               class="kb-nav-tab <?= $__isActive ? 'active' : '' ?>"
               role="tab"
               aria-label="<?= esc($__tab['label']) ?>"
               <?= $__isActive ? 'aria-current="page" aria-selected="true"' : 'aria-selected="false"' ?>>
                <iconify-icon icon="<?= $__tab['icon'] ?>" class="kb-tab-icon" aria-hidden="true"></iconify-icon>
                <span class="kb-tab-label"><?= esc($__tab['label']) ?></span>
                <?= $__tab['badge'] ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<style>
/* ===== Navigasi Kas & Bank =====
   Ukuran font memakai token dari kas_bank/_theme.php agar konsisten
   dengan keempat halaman modul ini.
   Komponen (kb-num, kb-pill, dll) didefinisikan di _theme.php. */

.kb-header-card {
    background: var(--bs-card-bg);
    border: 1px solid var(--bs-border-color-translucent);
    border-radius: 0.875rem;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
}

.kb-header-icon {
    width: 44px;
    height: 44px;
    border-radius: 0.75rem;
    background: rgba(var(--bs-primary-rgb), 0.1);
    color: var(--bs-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}

.kb-header-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--bs-emphasis-color);
    letter-spacing: -0.02em;
    line-height: 1.3;
}

.kb-header-desc {
    font-size: var(--kb-fs-meta);
    color: var(--bs-secondary-color);
}

.kb-breadcrumb {
    font-size: var(--kb-fs-meta);
    font-weight: 500;
    margin-bottom: 0;
}
.kb-breadcrumb a {
    color: var(--bs-secondary-color);
    text-decoration: none;
}
.kb-breadcrumb a:hover {
    color: var(--bs-primary);
}

.kb-nav-tabs {
    display: flex;
    gap: 0.375rem;
    background: var(--bs-tertiary-bg);
    border-radius: 0.75rem;
    padding: 0.3125rem;
    overflow-x: auto;
}

.kb-nav-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: var(--kb-fs-body);
    font-weight: 500;
    color: var(--bs-secondary-color);
    text-decoration: none;
    transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    flex: 1;
    justify-content: center;
}

.kb-nav-tab:hover:not(.active) {
    color: var(--bs-emphasis-color);
    background: rgba(var(--bs-body-color-rgb), 0.04);
    text-decoration: none;
}

.kb-nav-tab.active {
    background: var(--bs-card-bg);
    color: var(--bs-primary);
    font-weight: 600;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06), 0 0 0 1px rgba(0, 0, 0, 0.04);
}

.kb-tab-icon {
    font-size: 1.1rem;
    flex-shrink: 0;
}

.kb-tab-label {
    white-space: nowrap;
}

@media (max-width: 767.98px) {
    .kb-header-card {
        padding: 1rem;
    }
    .kb-nav-tab {
        padding: 0.5rem 0.75rem;
    }
    .kb-tab-label {
        display: none;
    }
    .kb-pill {
        display: none;
    }
}
</style>
