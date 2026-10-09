<?php
/**
 * Navigasi bersama Kas & Bank — Operational UI v4.
 * Dipakai 4 halaman: dashboard, akun, transfer, antar_unit.
 */
$__uri = service('uri');
$__seg2 = $__uri->getSegment(2) ?? '';
$__active = $__seg2 === '' ? 'ringkasan'
    : ($__seg2 === 'akun' ? 'rekening'
    : (in_array($__seg2, ['transfer', 'setor-tunai', 'penarikan-tunai'], true) ? 'mutasi'
    : (in_array($__seg2, ['antar-unit', 'antar_unit'], true) ? 'antar' : 'ringkasan')));

$__ctxBadges = [
    'ringkasan' => '',
    'rekening'  => '',
    'pindah'    => '',
    'antar'     => '',
    'tunai'     => '',
];
if (isset($akun_kas_bank) && is_array($akun_kas_bank)) {
    $aktifCount = array_reduce($akun_kas_bank, fn($c, $a) => $c + (($a->status ?? '') === 'aktif' ? 1 : 0), 0);
    $bankCount  = array_reduce($akun_kas_bank, fn($c, $a) => $c + (($a->tipe ?? '') === 'BANK' ? 1 : 0), 0);
    $__ctxBadges['rekening'] = $aktifCount > 0 ? "<span class='kb-pill kb-pill-primary'>{$aktifCount} Rekening</span>" : '';
    // Pindah Saldo menerima HANYA rekening BANK, jadi dataset halaman ini
    // tidak pernah punya baris KAS. Badge kategori Kas/Bank lama selalu
    // merender "0 Kas · N Bank" — menyesatkan karena tak ada pilihan KAS
    // sama sekali. Badge Bank saja; KAS dihitung untuk halaman lain yang
    // datanya memang bercampur.
    $__ctxBadges['pindah']   = $bankCount > 0 ? "<span class='kb-pill kb-pill-info'>{$bankCount} Rekening Bank</span>" : '';
}
if (isset($hp_hutang) && is_array($hp_hutang)) {
    $openHutang = array_reduce($hp_hutang, fn($c, $h) => $c + (((int)($h->sisa ?? 0) > 0 && ($h->status ?? '') !== 'lunas') ? 1 : 0), 0);
    $__ctxBadges['antar'] = $openHutang > 0 ? "<span class='kb-pill kb-pill-danger'>{$openHutang} Hutang Open</span>" : '';
}

$__tabs = [
    'ringkasan' => [
        'url'   => base_url('kas_bank'),
        'icon'  => 'solar:chart-square-bold-duotone',
        'label' => 'Ringkasan & Saldo',
        'badge' => $__ctxBadges['ringkasan'],
    ],
    'rekening' => [
        'url'   => base_url('kas_bank/akun'),
        'icon'  => 'solar:card-2-bold-duotone',
        'label' => 'Rekening & Saldo Awal',
        'badge' => $__ctxBadges['rekening'],
    ],
    'mutasi' => [
        'url'   => base_url('kas_bank/transfer'),
        'icon'  => 'solar:refresh-horizontal-bold-duotone',
        'label' => 'Mutasi Dana (Internal)',
        'badge' => $__ctxBadges['pindah'],
    ],
    'antar' => [
        'url'   => base_url('kas_bank/antar-unit'),
        'icon'  => 'solar:buildings-bold-duotone',
        'label' => 'Talangan & Antar Cabang',
        'badge' => $__ctxBadges['antar'],
    ],
];

$__titles = [
    'ringkasan' => ['Ringkasan Kas & Bank', 'Pantau saldo fisik kas laci, buku rekening bank, dan jatah saldo operasional per cabang.'],
    'rekening'  => ['Rekening & Saldo Awal', 'Kelola daftar rekening bank/kas, saldo awal fisik, dan pembagian jatah saldo per cabang.'],
    'mutasi'    => ['Mutasi Dana', 'Pindahkan dana antar rekening bank atau setor/tarik tunai ke laci kas.'],
    'antar'     => ['Talangan & Antar Cabang', 'Penyelesaian hutang piutang transfer fisik antar cabang atau talangan biaya bersama.'],
];

$__titles = [
    'ringkasan' => ['Ringkasan Kas & Bank', 'Pantau saldo fisik kas laci, buku rekening bank, dan jatah saldo operasional per cabang.'],
    'rekening'  => ['Rekening & Saldo Awal', 'Kelola daftar rekening bank/kas, saldo awal fisik, dan pembagian jatah saldo per cabang.'],
    'pindah'    => ['Pindah Saldo (Bank ke Bank)', 'Pindahkan uang antar rekening bank milik sendiri tanpa mengubah laporan laba rugi.'],
    'tunai'     => ['Setor & Tarik Tunai', 'Pindahkan uang fisik antara laci kas cabang dan rekening bank operasional.'],
    'antar'     => ['Talangan & Antar Cabang', 'Penyelesaian hutang piutang transfer fisik antar cabang atau talangan biaya bersama.'],
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
   Komponen (kb-num, kb-pill, dll) didefinisikan di _theme.php.

   .kb-header-card hanya memuat padding di sini: latar, border, radius, dan
   bayangannya sudah satu aturan di _theme.php "1b. Elevasi panel" supaya
   kartu ini tidak berbeda dari kartu modul lain. */

.kb-header-card {
    padding: 1.25rem 1.5rem;
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
    background: var(--kb-soft);
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
    background: var(--kb-surface);
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
