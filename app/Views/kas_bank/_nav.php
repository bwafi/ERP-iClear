<?php
/**
 * Navigasi bersama Kas & Bank.
 * Dipakai 4 halaman: dashboard, akun, transfer, antar_unit.
 *
 * Header memakai bahasa visual "Lembar Posisi Kas": judul di kanvas, garis
 * rambut, dan tab bergaris bawah. Kartu header lama sengaja dilepas supaya
 * dashboard dan tiga halaman lain tidak terlihat seperti dua aplikasi.
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
    'mutasi'    => '',
    'antar'     => '',
];
if (isset($akun_kas_bank) && is_array($akun_kas_bank)) {
    $aktifCount = array_reduce($akun_kas_bank, fn($c, $a) => $c + (($a->status ?? '') === 'aktif' ? 1 : 0), 0);
    $bankCount  = array_reduce($akun_kas_bank, fn($c, $a) => $c + (($a->tipe ?? '') === 'BANK' ? 1 : 0), 0);
    $__ctxBadges['rekening'] = $aktifCount > 0 ? $aktifCount . ' rekening' : '';
    // Pindah Saldo hanya menerima rekening BANK, jadi badge cukup menghitung
    // rekening bank; "0 Kas" akan menyesatkan karena tak ada pilihan KAS.
    $__ctxBadges['mutasi']   = $bankCount > 0 ? $bankCount . ' bank' : '';
}
if (isset($hp_hutang) && is_array($hp_hutang)) {
    $openHutang = array_reduce($hp_hutang, fn($c, $h) => $c + (((int)($h->sisa ?? 0) > 0 && ($h->status ?? '') !== 'lunas') ? 1 : 0), 0);
    $__ctxBadges['antar'] = $openHutang > 0 ? $openHutang . ' terbuka' : '';
}

$__tabs = [
    'ringkasan' => [
        'url'   => base_url('kas_bank'),
        'icon'  => 'bi:bar-chart-line-fill',
        'label' => 'Ringkasan & Saldo',
        'badge' => $__ctxBadges['ringkasan'],
    ],
    'rekening' => [
        'url'   => base_url('kas_bank/akun'),
        'icon'  => 'bi:credit-card-2-front-fill',
        'label' => 'Rekening & Saldo Awal',
        'badge' => $__ctxBadges['rekening'],
    ],
    'mutasi' => [
        'url'   => base_url('kas_bank/transfer'),
        'icon'  => 'bi:arrow-left-right',
        'label' => 'Mutasi Dana (Internal)',
        'badge' => $__ctxBadges['mutasi'],
    ],
    'antar' => [
        'url'   => base_url('kas_bank/antar-unit'),
        'icon'  => 'bi:buildings-fill',
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
$__t = $__titles[$__active] ?? $__titles['ringkasan'];
?>

<header class="kb-head">
    <div class="kb-head-row">
        <div class="kb-head-copy">
            <h1 class="kb-head-title"><?= esc($__t[0]) ?></h1>
            <p class="kb-head-desc"><?= esc($__t[1]) ?></p>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 kb-breadcrumb">
                <li class="breadcrumb-item"><a href="<?= base_url('kas_bank') ?>">Kas &amp; Bank</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= esc($__t[0]) ?></li>
            </ol>
        </nav>
    </div>

    <nav class="kb-nav-tabs" aria-label="Menu Kas dan Bank">
        <?php foreach ($__tabs as $__key => $__tab) : ?>
            <?php $__isActive = $__key === $__active; ?>
            <a href="<?= $__tab['url'] ?>"
               class="kb-nav-tab <?= $__isActive ? 'active' : '' ?>"
               aria-label="<?= esc($__tab['label']) ?>"
               <?= $__isActive ? 'aria-current="page"' : '' ?>>
                <iconify-icon icon="<?= $__tab['icon'] ?>" class="kb-tab-icon" aria-hidden="true"></iconify-icon>
                <span class="kb-tab-label"><?= esc($__tab['label']) ?></span>
                <?php if ($__tab['badge'] !== '') : ?>
                    <span class="kb-count"><?= esc($__tab['badge']) ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
</header>

<style>
    .kb-head {
        --kb-head-muted: #5b6672;
        margin-bottom: 1.25rem;
    }

    [data-bs-theme="dark"] .kb-head {
        --kb-head-muted: #8fa0b8;
    }

    .kb-head-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem 1.5rem;
        flex-wrap: wrap;
    }

    .kb-head-title {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
        line-height: 1.25;
        letter-spacing: -0.025em;
        color: var(--bs-emphasis-color, #111c2d);
    }

    .kb-head-desc {
        margin: 0.25rem 0 0;
        max-width: 72ch;
        font-size: 0.875rem;
        line-height: 1.5;
        color: var(--kb-head-muted);
    }

    .kb-breadcrumb {
        font-size: 0.75rem;
        font-weight: 500;
        margin-bottom: 0;
    }
    .kb-breadcrumb a {
        color: var(--kb-head-muted);
        text-decoration: none;
    }
    .kb-breadcrumb a:hover { color: #0069a8; }
    [data-bs-theme="dark"] .kb-breadcrumb a:hover { color: #66b6e9; }
    .kb-breadcrumb .active { color: var(--bs-emphasis-color, #111c2d); }

    .kb-nav-tabs {
        display: flex;
        gap: 1.5rem;
        margin-top: 1rem;
        border-bottom: 1px solid var(--bs-border-color, #e6ecf1);
        overflow-x: auto;
        scrollbar-width: none;
    }
    .kb-nav-tabs::-webkit-scrollbar { display: none; }

    .kb-nav-tab {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: -1px;
        padding: 0.625rem 0 0.75rem;
        border-bottom: 2px solid transparent;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--kb-head-muted);
        text-decoration: none;
        white-space: nowrap;
    }

    .kb-nav-tab:hover {
        color: var(--bs-emphasis-color, #111c2d);
        text-decoration: none;
    }

    .kb-nav-tab.active {
        color: #0069a8;
        border-bottom-color: var(--bs-primary, #0d6efd);
        font-weight: 600;
    }

    [data-bs-theme="dark"] .kb-nav-tab.active { color: #66b6e9; }

    .kb-nav-tab:focus-visible {
        outline: 2px solid var(--bs-primary, #0d6efd);
        outline-offset: 2px;
    }

    .kb-tab-icon {
        display: inline-block;
        width: 1.05rem;
        height: 1.05rem;
        font-size: 1.05rem;
        flex-shrink: 0;
    }

    .kb-count {
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--kb-head-muted);
    }
    .kb-nav-tab.active .kb-count { color: inherit; }

    @media (max-width: 991.98px) {
        .kb-nav-tabs { gap: 1rem; }
        .kb-count { display: none; }
    }

    @media (max-width: 767.98px) {
        .kb-head-title { font-size: 1.25rem; }
        .kb-nav-tabs { gap: 1.25rem; }
        .kb-nav-tab { flex: 1 1 0; padding: 0.5rem 0 0.625rem; min-width: 2.75rem; min-height: 2.75rem; justify-content: center; }
        .kb-tab-label { display: none; }
    }
</style>
