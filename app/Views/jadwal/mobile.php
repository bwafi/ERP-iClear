<?php
/**
 * Jadwal shift pegawai di app mobile: kalender bulanan read-only.
 *
 * Data hanya roster milik akun yang sedang login; tidak ada form atau POST.
 */
$namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$bulanLabel = ($namaBulan[(int) substr($bulan, 5, 2)] ?? '') . ' ' . substr($bulan, 0, 4);

$hariPendek = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
$hariPanjang = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
$labelShift  = ['PAGI' => 'Pagi', 'SIANG' => 'Siang', 'FULL' => 'Full', 'PS' => 'PS', 'OFF' => 'Off', 'IZIN' => 'Izin', 'SAKIT' => 'Sakit', 'CUTI' => 'Cuti'];
$slugShift   = ['PAGI' => 'pagi', 'SIANG' => 'siang', 'FULL' => 'full', 'PS' => 'ps', 'OFF' => 'off', 'IZIN' => 'izin', 'SAKIT' => 'sakit', 'CUTI' => 'cuti'];

$jumlahHari = (int) date('t', strtotime($bulan . '-01'));
$offsetAwal = (int) date('N', strtotime($bulan . '-01')) - 1;
$hariIni    = date('Y-m-d');
$bulanIni   = date('Y-m');
$prev       = date('Y-m', strtotime($bulan . '-01 -1 month'));
$next       = date('Y-m', strtotime($bulan . '-01 +1 month'));

ksort($roster);

$kerja = 0;
$libur = 0;
$izin  = 0;
$catatan = [];
foreach ($roster as $tanggal => $baris) {
    if (in_array($baris->shift, ['PAGI', 'SIANG', 'FULL', 'PS'], true)) {
        $kerja++;
    } elseif ($baris->shift === 'OFF') {
        $libur++;
    } elseif (in_array($baris->shift, ['IZIN', 'SAKIT', 'CUTI'], true)) {
        $izin++;
    }

    $ket = trim((string) ($baris->keterangan ?? ''));
    $unitKerja = (int) ($baris->unit_idunit ?? 0);
    if ($ket !== '' || ($unitKerja > 0 && $unitKerja !== $unitId)) {
        $catatan[$tanggal] = [
            'ket'  => $ket,
            'unit' => $unitKerja > 0 && $unitKerja !== $unitId ? ($unitMap[$unitKerja] ?? 'Unit lain') : '',
        ];
    }
}
$jumlahCatatan = count($catatan);
$kosong = max(0, $jumlahHari - count($roster));

$kataNama = preg_split('/\s+/', trim($nama)) ?: [];
$inisial  = '';
foreach (array_slice($kataNama, 0, 2) as $kata) {
    if ($kata === '') {
        continue;
    }
    $inisial .= function_exists('mb_substr') ? mb_substr($kata, 0, 1) : substr($kata, 0, 1);
}
$inisial = strtoupper($inisial !== '' ? $inisial : '?');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Jadwal</title>
    <?= view('inc/_presensi_mobile_theme') ?>
    <?= view('jadwal/_theme') ?>
</head>
<body>
<main class="mob jadwal">
    <header class="head">
        <span class="avatar" aria-hidden="true"><?= esc($inisial) ?></span>
        <div class="head-main">
            <p class="kicker">Jadwal</p>
            <h1>Jadwal shift</h1>
            <p class="meta"><?= esc($nama) ?><span class="sep" aria-hidden="true">·</span><?= esc($unit) ?></p>
        </div>
    </header>

    <section class="sheet jad-sheet" aria-labelledby="jad-bulan">
        <div class="jad-month">
            <a class="jad-nav" href="<?= base_url('jadwal?bulan=' . $prev) ?>" aria-label="Bulan sebelumnya">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 6 9 12l5.5 6"/></svg>
            </a>
            <div class="jad-month-main">
                <h2 id="jad-bulan"><?= esc($bulanLabel) ?></h2>
                <p><?= $kerja ?> hari kerja · <?= count($roster) ?> terjadwal</p>
            </div>
            <a class="jad-nav" href="<?= base_url('jadwal?bulan=' . $next) ?>" aria-label="Bulan berikutnya">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.5 6 15 12l-5.5 6"/></svg>
            </a>
        </div>

        <?php if ($bulan !== $bulanIni): ?>
            <a class="jad-today" href="<?= base_url('jadwal') ?>">Kembali ke bulan ini</a>
        <?php endif; ?>

        <div class="jad-stats">
            <div class="jad-stat"><b><?= $kerja ?></b><span>Kerja</span></div>
            <div class="jad-stat"><b><?= $libur ?></b><span>Libur</span></div>
            <div class="jad-stat"><b><?= $izin ?></b><span>Izin</span></div>
        </div>

        <div class="jad-grid" role="list" aria-label="Jadwal <?= esc($bulanLabel, 'attr') ?>">
            <?php foreach ($hariPendek as $label): ?>
                <span class="jad-dow" aria-hidden="true"><?= esc($label) ?></span>
            <?php endforeach; ?>

            <?php for ($i = 0; $i < $offsetAwal; $i++): ?>
                <span class="jad-blank" aria-hidden="true"></span>
            <?php endfor; ?>

            <?php for ($tgl = 1; $tgl <= $jumlahHari; $tgl++): ?>
                <?php
                $tanggal = sprintf('%s-%02d', $bulan, $tgl);
                $baris   = $roster[$tanggal] ?? null;
                $shift   = $baris->shift ?? '';
                $label   = $labelShift[$shift] ?? '';
                $nomor   = (int) date('N', strtotime($tanggal));
                $akhir   = $nomor >= 6;
                $kelas   = 'jad-day'
                    . ($shift !== '' ? ' is-set' : '')
                    . ($akhir ? ' is-weekend' : '')
                    . ($tanggal === $hariIni ? ' is-today' : '');
                $ket     = trim((string) ($baris->keterangan ?? ''));
                $unitKerja = (int) ($baris->unit_idunit ?? 0);
                $unitLain  = $unitKerja > 0 && $unitKerja !== $unitId;
                $keterangan = [];
                if ($label !== '') {
                    $keterangan[] = $label;
                }
                if ($unitLain) {
                    $keterangan[] = 'di ' . ($unitMap[$unitKerja] ?? 'unit lain');
                }
                if ($ket !== '') {
                    $keterangan[] = $ket;
                }
                $aria = $hariPanjang[$nomor] . ', ' . $tgl . ' ' . $bulanLabel
                    . ($keterangan !== [] ? ': ' . implode(', ', $keterangan) : ': belum dijadwalkan');
                ?>
                <div class="<?= $kelas ?>"<?= isset($slugShift[$shift]) ? ' data-shift="' . $slugShift[$shift] . '"' : '' ?>
                    role="listitem" aria-label="<?= esc($aria, 'attr') ?>"
                    <?= $tanggal === $hariIni ? 'aria-current="date"' : '' ?>>
                    <span class="jad-num"><?= $tgl ?></span>
                    <?php if ($label !== ''): ?>
                        <span class="jad-label"><?= esc($label) ?></span>
                    <?php endif; ?>
                    <?php if ($ket !== '' || $unitLain): ?>
                        <span class="jad-mark" aria-hidden="true"></span>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>

        <?php if ($kosong > 0): ?>
            <p class="jad-hint">Tanggal tanpa label berarti jadwal belum diisi admin.</p>
        <?php endif; ?>
    </section>

    <?php if ($jumlahCatatan > 0): ?>
        <section class="sheet" aria-labelledby="jad-catatan">
            <div class="jad-sec-head">
                <h2 id="jad-catatan">Catatan</h2>
                <span class="jad-count"><?= $jumlahCatatan ?> tanggal</span>
            </div>
            <ul class="jad-notes">
                <?php foreach ($catatan as $tanggal => $c): ?>
                    <li class="jad-note">
                        <span class="jad-note-date"><?= esc(date('d/m', strtotime($tanggal))) ?></span>
                        <span class="jad-note-body"><?= esc($c['ket'] !== '' ? $c['ket'] : 'Jadwal di ' . $c['unit']) ?><?php if ($c['ket'] !== '' && $c['unit'] !== ''): ?><em> · di <?= esc($c['unit']) ?></em><?php endif; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <p class="jad-foot">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2.5"/><path d="M8.5 10V7.5a3.5 3.5 0 0 1 7 0V10"/></svg>
        Hanya lihat. Perubahan jadwal lewat admin cabang.
    </p>
</main>

<?= view('inc/_pwa_install') ?>

<?= view('inc/_mobile_nav', ['active' => 'jadwal']) ?>
</body>
</html>
