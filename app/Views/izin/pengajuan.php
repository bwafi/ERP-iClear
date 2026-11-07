<?php
$status = [
    0 => ['Menunggu', 'is-warn'],
    1 => ['Disetujui', 'is-ok'],
    2 => ['Ditolak', 'is-bad'],
];
$nama  = (string) (session('NAMA') ?: 'Pegawai');
$unit  = (string) (session('NAMA_UNIT') ?: 'Unit');
$total = count($pengajuan);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Pengajuan Izin / Cuti</title>
    <?= view('inc/_presensi_mobile_theme') ?>
</head>
<body>
<main class="mob">
    <header class="head">
        <div class="head-main">
            <p class="kicker">Pengajuan</p>
            <h1>Ajukan izin</h1>
            <p class="meta"><?= esc($nama) ?><span class="sep" aria-hidden="true">·</span><?= esc($unit) ?></p>
        </div>
    </header>

    <?php if (session()->getFlashdata('sukses')): ?>
        <div class="flash is-ok" role="status"><?= esc(session()->getFlashdata('sukses')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('gagal')): ?>
        <div class="flash is-err" role="alert"><?= esc(session()->getFlashdata('gagal')) ?></div>
    <?php endif; ?>

    <form class="sheet" method="post" action="<?= base_url('pengajuan_izin/simpan') ?>" enctype="multipart/form-data">
        <div class="field">
            <label class="label" for="jenis">Jenis</label>
            <select class="input" id="jenis" name="jenis" required>
                <option value="">Pilih jenis</option>
                <?php foreach ($jenis as $j): ?>
                    <option value="<?= (int) $j->idjenis_perizinan ?>" data-jenis="<?= esc(strtoupper(trim((string) $j->jenis_izin))) ?>"><?= esc($j->jenis_izin) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="tanggal-mulai">Tanggal mulai</label>
                <input class="input" id="tanggal-mulai" type="date" name="tanggal_mulai" required>
            </div>
            <div class="field">
                <label class="label" for="tanggal-akhir">Tanggal akhir <span class="opt">(opsional)</span></label>
                <input class="input" id="tanggal-akhir" type="date" name="tanggal_akhir">
            </div>
        </div>

        <div class="field">
            <label class="label" for="alasan">Alasan</label>
            <textarea class="input textarea" id="alasan" name="alasan" rows="3" required placeholder="Contoh: demam, perlu istirahat."></textarea>
        </div>

        <div class="field" id="surat-field" hidden>
            <label class="label" for="surat">Surat keterangan dokter <span class="opt">(opsional)</span></label>
            <input class="input" id="surat" type="file" name="surat" accept="image/jpeg,image/png">
            <p class="hint">JPG atau PNG, maksimal 2 MB.</p>
        </div>

        <button class="btn" type="submit">Kirim pengajuan</button>
        <p class="hint">Cuti diajukan minimal 7 hari sebelumnya. Sakit dan izin bisa kapan saja.</p>
    </form>

    <section class="sheet">
        <div class="section-head">
            <h2>Pengajuan saya</h2>
            <?php if ($total > 0): ?>
                <span class="count"><?= $total ?> pengajuan</span>
            <?php endif; ?>
        </div>

        <?php if ($total === 0): ?>
            <p class="empty">Belum ada pengajuan.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($pengajuan as $row): ?>
                    <?php $s = $status[(int) $row->status] ?? ['-', '']; ?>
                    <li class="item">
                        <div class="item-head">
                            <div>
                                <h3 class="item-title"><?= esc($row->jenis_izin ?? '-') ?></h3>
                                <p class="item-date">
                                    <?= esc(date('d/m/Y', strtotime((string) $row->tanggal_mulai))) ?>
                                    <?php if ($row->tanggal_akhir && $row->tanggal_akhir !== $row->tanggal_mulai): ?>
                                        &ndash; <?= esc(date('d/m/Y', strtotime((string) $row->tanggal_akhir))) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="status <?= esc($s[1]) ?>"><?= esc($s[0]) ?></span>
                        </div>

                        <p class="item-body"><?= esc((string) $row->alasan) ?></p>

                        <?php if (! empty($row->file)): ?>
                            <div class="links">
                                <a class="link" href="<?= base_url('pengajuan_izin/surat/' . (int) $row->idizin) ?>" target="_blank" rel="noopener">Lihat surat dokter</a>
                            </div>
                        <?php endif; ?>

                        <?php if ($row->balasan): ?>
                            <div class="balasan">
                                <p class="balasan-label">Balasan atasan</p>
                                <p><?= esc((string) $row->balasan) ?></p>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>

<?= view('inc/_pwa_install') ?>

<?= view('inc/_mobile_nav', ['active' => 'izin']) ?>

<script>
    (function () {
        var jenis = document.getElementById('jenis');
        var field = document.getElementById('surat-field');

        function sync() {
            var opsi = jenis.options[jenis.selectedIndex];
            field.hidden = !opsi || opsi.dataset.jenis !== 'SAKIT';
        }

        jenis.addEventListener('change', sync);
        sync();
    })();
</script>
</body>
</html>
