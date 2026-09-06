<?php
/**
 * Partial: grid input harian + riwayat KPI manual (non-absensi).
 *
 * Variabel yang diharapkan:
 *   $pegawai        object (ID_AKUN, NAMA_AKUN)
 *   $jabatanRingkas string  (opsional)
 *   $manualGrid     array   komponen manual: id, code, name, weight, scores, count, avg, nilai
 *   $bulan, $tahun  int
 *   $jumlahHari     int     (opsional - dihitung otomatis bila kosong)
 *   $saveUrl        string  action form (default penilaian/kpi/save_daily)
 *   $editable       bool
 */
$jumlahHari = (int)($jumlahHari ?? date('t', strtotime(sprintf('%04d-%02d-01', $tahun, $bulan))));
$todayDay   = (int)date('j');
$selDay     = ((int)date('n') === (int)$bulan && $todayDay >= 1 && $todayDay <= $jumlahHari) ? $todayDay : 1;
$saveUrl    = $saveUrl ?? 'penilaian/kpi/save_daily';

$skala = [5 => 'Sangat Baik', 4 => 'Baik', 3 => 'Cukup', 2 => 'Kurang', 1 => 'Sangat Kurang'];
?>
<style>
    .riwayat-kpi { table-layout: fixed; width: 100%; }
    .riwayat-kpi thead th,
    .riwayat-kpi tbody td { vertical-align: middle; }
    .riwayat-kpi th.riwayat-komponen,
    .riwayat-kpi td.riwayat-komponen { width: 210px; text-align: left; }
    .riwayat-kpi th.riwayat-date,
    .riwayat-kpi td.riwayat-date {
        width: 34px;
        text-align: center;
        overflow: hidden;
        padding-left: 0;
        padding-right: 0;
    }
    .kpi-skor {
        border-radius: .25rem;
        font-weight: 600;
        font-size: .7rem;
        line-height: 1.5;
        padding: .1rem .15rem;
        display: inline-block;
        width: 1.85rem;
        text-align: center;
        box-sizing: border-box;
    }
    .kpi-skor.skor-low { background: var(--bs-danger-bg-subtle); color: var(--bs-danger); }
    .kpi-skor.skor-mid { background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis); }
    .kpi-skor.skor-good { background: var(--bs-success-bg-subtle); color: var(--bs-success); }
</style>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0"><?= esc($pegawai->NAMA_AKUN) ?></h5>
        <span class="text-muted small"><?= esc($jabatanRingkas ?? '') ?><?= isset($manualGrid[0]) ? ' • Nilai = total skor ÷ (jumlah hari bulan × 5) × 100' : '' ?></span>
    </div>
    <div class="card-body">
        <?php if ($editable && !empty($manualGrid)) : ?>
            <form method="post" action="<?= base_url($saveUrl) ?>" class="row g-2 align-items-end mb-3">
                <input type="hidden" name="employee_id" value="<?= (int)$pegawai->ID_AKUN ?>">
                <input type="hidden" name="bulan" value="<?= (int)$bulan ?>">
                <input type="hidden" name="tahun" value="<?= (int)$tahun ?>">
                <div class="col-auto">
                    <label class="form-label mb-1">Tanggal</label>
                    <select name="tanggal" class="form-select form-select-sm" style="min-width:80px;">
                        <?php for ($d = 1; $d <= $jumlahHari; $d++) : ?>
                            <option value="<?= $d ?>" <?= $d === $selDay ? 'selected' : '' ?>><?= $d ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <?php foreach ($manualGrid as $kit) : ?>
                    <?php $already = $kit['scores'][$selDay] ?? null; ?>
                    <div class="col-auto">
                        <label class="form-label mb-1">
                            <?= esc($kit['name']) ?> <span class="text-muted">(<?= (int)$kit['weight'] ?>%)</span>
                            <?php if ($already !== null) : ?>
                                <span class="badge bg-primary-subtle text-primary ms-1">terisi: <?= (int)$already ?></span>
                            <?php endif; ?>
                        </label>
                        <select name="komponen[<?= (int)$kit['id'] ?>]" class="form-select form-select-sm" style="min-width:180px;">
                            <option value="">- (tidak diubah)</option>
                            <?php foreach ($skala as $v => $label) : ?>
                                <option value="<?= $v ?>"><?= $v ?> — <?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
                <div class="col-auto">
                    <label class="form-label d-block mb-1 invisible">.</label>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <iconify-icon icon="solar:diskette-bold" class="me-1"></iconify-icon>Simpan
                    </button>
                </div>
            </form>
        <?php elseif (!$editable && !empty($manualGrid)) : ?>
            <div class="alert alert-light border small">Mode baca saja — Anda tidak berwenang mengisi komponen manual pegawai ini.</div>
        <?php endif; ?>

        <?php if (empty($manualGrid)) : ?>
            <div class="alert alert-light border small mb-0">Tidak ada komponen KPI manual yang dapat dinilai untuk pegawai ini.</div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle text-center riwayat-kpi">
                    <thead class="table-light">
                        <tr>
                            <th class="riwayat-komponen">Komponen</th>
                            <?php for ($d = 1; $d <= $jumlahHari; $d++) : ?>
                                <th class="riwayat-date"><?= $d ?></th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($manualGrid as $kit) : ?>
                            <tr>
                                <td class="fw-semibold riwayat-komponen">
                                    <?= esc($kit['name']) ?>
                                    <span class="text-muted small">(<?= (int)$kit['weight'] ?>%)</span>
                                </td>
                                <?php for ($d = 1; $d <= $jumlahHari; $d++) : ?>
                                    <td class="riwayat-date">
                                        <?php if (isset($kit['scores'][$d])) : ?>
                                            <?php $sv = (int)$kit['scores'][$d]; ?>
                                            <?php if ($sv <= 2) : ?>
                                                <span class="kpi-skor skor-low"><?= $sv ?></span>
                                            <?php elseif ($sv === 3) : ?>
                                                <span class="kpi-skor skor-mid"><?= $sv ?></span>
                                            <?php else : ?>
                                                <span class="kpi-skor skor-good"><?= $sv ?></span>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex flex-wrap gap-3 text-muted small mt-2">
                <?php foreach ($manualGrid as $kit) : ?>
                    <div>
                        <strong><?= esc($kit['name']) ?>:</strong>
                        <?= $kit['count'] ?> hari dinilai dari <?= (int)($kit['totalDays'] ?? $jumlahHari) ?> hari •
                        Rata-rata <?= number_format($kit['avg'], 2, ',', '.') ?> •
                        Nilai <strong><?= number_format($kit['nilai'], 2, ',', '.') ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-muted small mt-1">
                Legenda: <span class="kpi-skor skor-low">1–2</span> rendah,
                <span class="kpi-skor skor-mid">3</span> sedang,
                <span class="kpi-skor skor-good">4–5</span> baik.
                Kualitas dinilai setiap hari (tanpa OFF) — hari yang belum dinilai dihitung 0
                terhadap <?= (int)($manualGrid[0]['totalDays'] ?? $jumlahHari) ?> hari pada bulan ini.
            </div>
        <?php endif; ?>
    </div>
</div>