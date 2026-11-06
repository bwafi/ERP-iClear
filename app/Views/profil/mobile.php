<?php
/**
 * Profil pegawai di app mobile: identitas dari sesi + tombol keluar.
 * Edit profil belum termasuk.
 */
$kataNama = preg_split('/\s+/', trim($nama)) ?: [];
$inisial  = '';
foreach (array_slice($kataNama, 0, 2) as $kata) {
    if ($kata === '') {
        continue;
    }
    $inisial .= function_exists('mb_substr') ? mb_substr($kata, 0, 1) : substr($kata, 0, 1);
}
$inisial = strtoupper($inisial !== '' ? $inisial : '?');

$rincian = array_filter([
    'Jabatan' => $jabatan,
    'Unit'    => $unit,
    'NOID'    => $noid,
    'Email'   => $email,
], static fn ($nilai) => $nilai !== '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Profil</title>
    <?= view('inc/_presensi_mobile_theme') ?>
</head>
<body>
<main class="mob">
    <header class="head">
        <div class="head-main">
            <h1>Profil</h1>
            <p class="meta">Identitas akun pegawai</p>
        </div>
    </header>

    <section class="sheet prof-card">
        <span class="prof-avatar" aria-hidden="true"><?= esc($inisial) ?></span>
        <p class="prof-name"><?= esc($nama) ?></p>
        <?php if ($jabatan !== '' || $unit !== ''): ?>
            <p class="prof-meta">
                <?= esc($jabatan) ?><?= $jabatan !== '' && $unit !== '' ? ' · ' : '' ?><?= esc($unit) ?>
            </p>
        <?php endif; ?>

        <?php if ($rincian !== []): ?>
            <ul class="prof-list">
                <?php foreach ($rincian as $label => $nilai): ?>
                    <li class="prof-list__row">
                        <span class="prof-list__label"><?= esc($label) ?></span>
                        <span class="prof-list__value"><?= esc($nilai) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <a class="btn is-outline" href="<?= base_url('Logout') ?>">Keluar</a>
</main>

<?= view('inc/_pwa_install') ?>

<?= view('inc/_mobile_nav', ['active' => 'profil']) ?>
</body>
</html>
