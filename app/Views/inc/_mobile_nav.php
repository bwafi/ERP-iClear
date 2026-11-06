<?php
/**
 * Navigasi bawah app mobile presensi.
 *
 * @var string $active absen|izin|profil
 */
$active = $active ?? '';
$tabs   = [
    'absen' => [
        'label' => 'Absen',
        'href'  => 'absen',
        'icon'  => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5.2l3.2 2"/>',
    ],
    'izin' => [
        'label' => 'Pengajuan Izin',
        'href'  => 'pengajuan_izin',
        'icon'  => '<rect x="4" y="5.5" width="16" height="15" rx="2.5"/><path d="M4 10h16M8 3.5v4M16 3.5v4"/>',
    ],
    'profil' => [
        'label' => 'Profil',
        'href'  => 'profil',
        'icon'  => '<circle cx="12" cy="8" r="3.6"/><path d="M5 20c.7-3.5 3.5-5.5 7-5.5s6.3 2 7 5.5"/>',
    ],
];
?>
<nav class="mob-nav" aria-label="Navigasi utama">
    <?php foreach ($tabs as $key => $tab): ?>
        <a class="mob-nav__item<?= $active === $key ? ' is-active' : '' ?>"
            href="<?= base_url($tab['href']) ?>"
            <?= $active === $key ? 'aria-current="page"' : '' ?>>
            <svg viewBox="0 0 24 24" aria-hidden="true"><?= $tab['icon'] ?></svg>
            <span><?= esc($tab['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
