<?= $this->include('penilaian/_penilaian_kinerja_theme') ?>

<?php
/**
 * Halaman Penilaian Kinerja & Penggajian (/penilaian_kinerja).
 *
 * Komposisi: kartu laporan (rapor) yang mengendap menjadi slip gaji.
 * Skor kinerja memimpin sebagai angka bercatatan mutu, kriteria KPI & absen
 * membaca sebagai "mata pelajaran" yang menyusunnya, dan halaman menutup
 * pada komposisi penghasilan serta Take Home Pay.
 */

$rp = static fn ($n): string => 'Rp ' . number_format((float) $n, 0, ',', '.');

$skor = (float) $skor_total;

if ($skor >= 90) {
    $bandClass = 'is-success';
    $bandLabel = 'Sangat Baik';
    $meterClass = 'is-success';
} elseif ($skor >= 75) {
    $bandClass = 'is-warning';
    $bandLabel = 'Baik';
    $meterClass = 'is-warning';
} elseif ($skor >= 60) {
    $bandClass = 'is-warning';
    $bandLabel = 'Cukup';
    $meterClass = 'is-warning';
} else {
    $bandClass = 'is-danger';
    $bandLabel = 'Kurang';
    $meterClass = 'is-danger';
}

$pct = max(0, min(100, $skor));

// Skor kehadiran tertimbang (tampilan saja; nilai resmi dihitung server).
$skorAbsen = 0.0;
foreach ($detail_absen as $a) {
    $skorAbsen += ((float) $a['nilai'] * (float) $a['bobot']) / 100;
}
$skorAbsen = min($skorAbsen, 100);

// Nama karyawan terpilih dari daftar yang diberikan controller.
$namaSelected = '';
foreach ($list_karyawan as $k) {
    if ((int) $k['ID_AKUN'] === (int) $selected_karyawan) {
        $namaSelected = (string) $k['NAMA_AKUN'];
        break;
    }
}
$namaBulan = date('F', mktime(0, 0, 0, (int) $bulan, 1));
?>

<div class="penilaian-kinerja">

    <!-- Header halaman -->
    <header class="card pk-head mb-3">
        <div class="card-body d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
                <h4 class="fw-semibold mb-1">Penilaian Kinerja</h4>
                <p class="text-muted mb-0">
                    Laporan bulanan
                    <strong><?= esc($namaSelected !== '' ? $namaSelected : 'Karyawan') ?></strong>
                    &middot; <?= esc($namaBulan) ?> <?= esc((string) $tahun) ?> &mdash;
                    skor kinerja, rincian kriteria, dan estimasi take home pay.
                </p>
            </div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Penilaian Kinerja</li>
                </ol>
            </nav>
        </div>
    </header>

    <?php if (session()->getFlashdata('error')) : ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div class="flex-grow-1"><?= session()->getFlashdata('error') ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>

    <!-- Filter periode & karyawan -->
    <section class="card pk-filter mb-3" aria-label="Filter periode dan karyawan">
        <div class="card-body">
            <form method="get" action="<?= base_url('penilaian_kinerja') ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label" for="f_bulan">Bulan</label>
                        <select name="bulan" id="f_bulan" class="form-select bg-light border-0" onchange="this.form.submit()">
                            <?php for ($i = 1; $i <= 12; $i++) : ?>
                                <option value="<?= $i ?>" <?= (int) $bulan === $i ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label" for="f_tahun">Tahun</label>
                        <select name="tahun" id="f_tahun" class="form-select bg-light border-0" onchange="this.form.submit()">
                            <?php for ($i = (int) date('Y'); $i >= 2023; $i--) : ?>
                                <option value="<?= $i ?>" <?= (int) $tahun === $i ? 'selected' : '' ?>>
                                    <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="f_karyawan">Pilih Karyawan</label>
                        <select name="karyawan" id="f_karyawan" class="form-select bg-light border-0 select2" onchange="this.form.submit()">
                            <?php foreach ($list_karyawan as $karyawanItem) : ?>
                                <option value="<?= (int) $karyawanItem['ID_AKUN'] ?>" <?= (int) $selected_karyawan === (int) $karyawanItem['ID_AKUN'] ? 'selected' : '' ?>>
                                    <?= esc($karyawanItem['NAMA_AKUN']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Kartu laporan: skor kinerja ↔ take home pay -->
    <section class="card pk-report mb-3" aria-label="Ringkasan penilaian dan penghasilan">
        <div class="card-body">
            <div class="pk-report__grid">

                <div class="pk-half">
                    <span class="pk-half__label">Skor Kinerja</span>
                    <div class="pk-score">
                        <span class="pk-score__num"><?= esc((string) $skor_total) ?></span>
                        <span class="pk-score__den">/ 100</span>
                    </div>
                    <span class="pk-band <?= $bandClass ?>"><?= esc($bandLabel) ?></span>
                    <div class="pk-meter" role="img" aria-label="Skor kinerja <?= esc((string) $skor_total) ?> dari 100">
                        <div class="pk-meter__bar <?= $meterClass ?>" style="--pk-prog: <?= number_format($pct / 100, 4, '.', '') ?>"></div>
                    </div>
                    <p class="pk-note">
                        <iconify-icon icon="solar:info-circle-linear"></iconify-icon>
                        Dihitung dari bobot tiap kriteria KPI pada tabel di bawah.
                    </p>
                </div>

                <div class="pk-half">
                    <span class="pk-half__label">Estimasi Take Home Pay</span>
                    <div class="pk-pay__num"><?= $rp($gaji) ?></div>
                    <p class="pk-note mb-3">
                        <iconify-icon icon="solar:info-circle-linear"></iconify-icon>
                        Belum termasuk komponen komisi &amp; insentif tambahan.
                    </p>
                    <a href="<?= base_url('penilaian/slip_gaji/' . (int) $selected_karyawan . '?bulan=' . (int) $bulan . '&tahun=' . (int) $tahun) ?>"
                        class="btn btn-primary d-inline-flex align-items-center gap-2" target="_blank" rel="noopener">
                        <iconify-icon icon="solar:printer-bold"></iconify-icon>
                        Cetak Slip Gaji Resmi
                    </a>
                </div>

            </div>
        </div>
    </section>

    <!-- Rincian omset cabang (penunjang kriteria KPI) -->
    <section class="card pk-omset pk-card mb-3" aria-label="Rincian omset cabang">
        <div class="card-header">
            <h6>
                <iconify-icon icon="solar:shop-bold-duotone"></iconify-icon>
                Rincian Omset Cabang
            </h6>
        </div>
        <div class="card-body">
            <?php if (!empty($omset_cabang)) : ?>
                <div class="pk-omset__grid">
                    <?php foreach ($omset_cabang as $cb) : ?>
                        <div class="pk-omset__cell">
                            <span class="pk-omset__label"><?= esc($cb['nama']) ?></span>
                            <span class="pk-omset__value"><?= $rp($cb['omset']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="pk-omset__cell is-total">
                        <span class="pk-omset__label">Omset Global</span>
                        <span class="pk-omset__value"><?= $rp($omset_global) ?></span>
                    </div>
                </div>
            <?php else : ?>
                <div class="pk-empty py-4">
                    <p class="mb-0">Belum ada data omset cabang untuk periode ini.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Ledger KPI -->
    <section class="card pk-ledger pk-card mb-3" aria-label="Detail penilaian KPI">
        <div class="card-header">
            <h5>
                <iconify-icon icon="solar:clipboard-list-bold-duotone"></iconify-icon>
                Detail Penilaian Kinerja (KPI)
            </h5>
            <span class="pk-chip">Evaluasi Bulanan</span>
        </div>
        <div class="card-body">
            <div class="pk-scroll">
                <table class="pk-table">
                    <thead>
                        <tr>
                            <th class="text-end">No</th>
                            <th>Kriteria</th>
                            <th class="text-center">Bobot</th>
                            <th>Target</th>
                            <th>Realisasi</th>
                            <th>Status / Kekurangan</th>
                            <th class="text-center">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($detail_kpi)) : ?>
                            <?php $no = 1; ?>
                            <?php foreach ($detail_kpi as $kpi) : ?>
                                <?php
                                $isCurrency = ($kpi['format'] ?? 'currency') === 'currency';
                                $badge = 'is-neutral';
                                if ($kpi['nilai'] !== null) {
                                    if ($kpi['nilai'] >= 90) {
                                        $badge = 'is-success';
                                    } elseif ($kpi['nilai'] >= 75) {
                                        $badge = 'is-warning';
                                    } else {
                                        $badge = 'is-danger';
                                    }
                                }
                                ?>
                                <tr>
                                    <td class="pk-idx"><?= $no++ ?></td>
                                    <td>
                                        <span class="pk-kriteria"><?= esc($kpi['nama']) ?></span>
                                        <?php if (!empty($kpi['cabang'])) : ?>
                                            <div class="pk-branch">
                                                <?php foreach ($kpi['cabang'] as $cb) : ?>
                                                    <?php $reached = !empty($cb['reached']); ?>
                                                    <div class="pk-branch__row">
                                                        <span>
                                                            <i class="<?= $reached ? 'bi bi-check-circle-fill pk-pos' : 'bi bi-x-circle-fill pk-neg' ?> me-1"></i>
                                                            <span class="pk-branch__name">Cab. <?= esc($cb['unit']) ?>:</span>
                                                            <span class="pk-branch__flow"><?= $rp($cb['target_ho']) ?> &rarr; <?= $rp($cb['actual']) ?></span>
                                                        </span>
                                                        <span>
                                                            <?php if ($reached) : ?>
                                                                <span class="pk-chip pk-pos">Tercapai</span>
                                                            <?php else : ?>
                                                                <span class="pk-chip pk-neg">&minus;<?= $rp($cb['shortfall_ho']) ?></span>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><span class="pk-chip"><?= esc((string) $kpi['bobot']) ?>%</span></td>
                                    <td class="pk-num">
                                        <?php if ($kpi['target'] !== null) : ?>
                                            <span class="fw-medium"><?= $isCurrency ? 'Rp ' : '' ?><?= number_format((float) $kpi['target'], 0, ',', '.') ?></span>
                                            <?php if ($isCurrency) : ?>
                                                <?php if (!empty($kpi['ho'])) : ?>
                                                    <span class="pk-chip pk-chip--ho ms-1">HO</span>
                                                <?php else : ?>
                                                    <span class="pk-chip ms-1">Non HO</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php elseif ($kpi['unit_count'] !== null) : ?>
                                            <span class="fw-medium"><?= (int) $kpi['unit_count'] ?> Cabang</span>
                                        <?php else : ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pk-num">
                                        <?php if ($kpi['actual'] !== null) : ?>
                                            <span class="fw-medium"><?= $isCurrency ? 'Rp ' : '' ?><?= number_format((float) $kpi['actual'], 0, ',', '.') ?></span>
                                        <?php elseif ($kpi['reached'] !== null) : ?>
                                            <span class="fw-medium"><?= (int) $kpi['reached'] ?> Cabang</span>
                                        <?php else : ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pk-num">
                                        <?php if ($kpi['unit_count'] !== null) : ?>
                                            <?php $kurangCabang = (int) $kpi['shortfall']; ?>
                                            <?php if ($kurangCabang > 0) : ?>
                                                <span class="pk-neg small"><i class="bi bi-arrow-down me-1"></i>Kurang <?= $kurangCabang ?> cabang</span>
                                            <?php else : ?>
                                                <span class="pk-pos small"><i class="bi bi-check me-1"></i>Semua tercapai</span>
                                            <?php endif; ?>
                                        <?php elseif ($kpi['shortfall'] !== null && $kpi['shortfall'] > 0) : ?>
                                            <span class="pk-neg small"><i class="bi bi-arrow-down me-1"></i><?= $isCurrency ? 'Rp ' : '' ?><?= number_format((float) $kpi['shortfall'], 0, ',', '.') ?></span>
                                        <?php elseif ($kpi['shortfall'] !== null) : ?>
                                            <span class="pk-pos small"><i class="bi bi-check me-1"></i>Target tercapai</span>
                                        <?php else : ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="pk-badge <?= $badge ?>"><?= $kpi['nilai'] ?? 'N/A' ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="7">
                                    <div class="pk-empty">
                                        <span class="pk-empty__icon"><iconify-icon icon="solar:folder-error-bold-duotone"></iconify-icon></span>
                                        <div class="pk-empty__title">Belum ada data KPI</div>
                                        <p>Belum ada kriteria KPI yang tercatat untuk periode ini. Coba pilih periode atau karyawan lain.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Ledger kehadiran -->
    <section class="card pk-ledger pk-card mb-3" aria-label="Detail penilaian kehadiran">
        <div class="card-header">
            <h5>
                <iconify-icon icon="solar:calendar-mark-bold-duotone"></iconify-icon>
                Detail Penilaian Absen
            </h5>
            <span class="pk-chip">Skor Kehadiran <?= number_format($skorAbsen, 2) ?>%</span>
        </div>
        <div class="card-body">
            <div class="pk-scroll">
                <table class="pk-table">
                    <thead>
                        <tr>
                            <th class="text-end">No</th>
                            <th>Kriteria Kehadiran</th>
                            <th class="text-center">Bobot</th>
                            <th class="text-center">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($detail_absen)) : ?>
                            <?php $no = 1; ?>
                            <?php foreach ($detail_absen as $absen) : ?>
                                <?php
                                if ($absen['nilai'] >= 90) {
                                    $badge = 'is-success';
                                } elseif ($absen['nilai'] >= 75) {
                                    $badge = 'is-warning';
                                } else {
                                    $badge = 'is-danger';
                                }
                                ?>
                                <tr>
                                    <td class="pk-idx"><?= $no++ ?></td>
                                    <td class="pk-kriteria"><?= esc($absen['nama']) ?></td>
                                    <td class="text-center"><span class="pk-chip"><?= esc((string) $absen['bobot']) ?>%</span></td>
                                    <td class="text-center"><span class="pk-badge <?= $badge ?>"><?= esc((string) $absen['nilai']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="4">
                                    <div class="pk-empty">
                                        <span class="pk-empty__icon"><iconify-icon icon="solar:calendar-date-bold-duotone"></iconify-icon></span>
                                        <div class="pk-empty__title">Belum ada data kehadiran</div>
                                        <p>Belum ada kriteria kehadiran yang tercatat untuk periode ini.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Komposisi penghasilan (slip gaji) -->
    <section class="card pk-pay pk-card mb-4" aria-label="Komposisi penghasilan">
        <div class="card-header">
            <h5>
                <iconify-icon icon="solar:wallet-money-bold-duotone"></iconify-icon>
                Komposisi Penghasilan
            </h5>
            <span class="pk-chip">Estimasi</span>
        </div>
        <div class="card-body">
            <div class="pk-pay__row">
                <span class="pk-pay__label">Gaji Pokok</span>
                <span class="pk-pay__amount"><?= $rp($gaji_pokok) ?></span>
            </div>
            <div class="pk-pay__row">
                <span class="pk-pay__label">
                    Tunjangan Kinerja
                    <span class="pk-pay__sub">Skor KPI <?= esc((string) $skor_total) ?>% &times; tarif jabatan</span>
                </span>
                <span class="pk-pay__amount"><?= $rp($tunjangan_kinerja) ?></span>
            </div>
            <div class="pk-pay__row">
                <span class="pk-pay__label">
                    Tunjangan Absen
                    <span class="pk-pay__sub">Skor kehadiran <?= number_format($skorAbsen, 2) ?>% &times; Rp 250.000</span>
                </span>
                <span class="pk-pay__amount"><?= $rp($tunjangan_absen) ?></span>
            </div>
            <div class="pk-pay__row">
                <span class="pk-pay__label">
                    Tunjangan Penempatan
                    <span class="pk-pay__sub">Sesuai status penempatan</span>
                </span>
                <span class="pk-pay__amount"><?= $rp($tunjangan_penempatan->tunjangan_penempatan) ?></span>
            </div>
            <div class="pk-pay__row">
                <span class="pk-pay__label">
                    Insentif
                    <span class="pk-pay__sub">Insentif periode</span>
                </span>
                <span class="pk-pay__amount"><?= $rp($insentif) ?></span>
            </div>
            <div class="pk-pay__row is-total">
                <span class="pk-pay__label">Take Home Pay</span>
                <span class="pk-pay__amount"><?= $rp($gaji) ?></span>
            </div>
        </div>
    </section>

</div>
