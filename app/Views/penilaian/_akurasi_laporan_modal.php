<?php
/**
 * Modal rincian KPI "Akurasi Laporan Keuangan" — hari rekonsiliasi
 * terverifikasi per cabang (terverifikasi, cocok, selisih, nilai).
 *
 * Sumber: halaman /finance/rekonsiliasi (finance_rekon_daily), approval
 * `verified`. Memakai komponen visual `.ku` yang sama dengan modal Kesehatan
 * Uang agar konsisten dengan tabel di sekitarnya.
 *
 * Variabel:
 *   $cabang  array<int,array<string,mixed>>  hasil FinanceKpiService::akurasiBreakdown()
 *   $modalId string                           id modal (unik per baris tabel)
 *   $periode string                           label periode, mis. "Oktober 2026"
 */

$cabang  = $cabang ?? [];
$modalId = $modalId ?? 'akModal';
$periode = $periode ?? '';

$scored       = array_values(array_filter($cabang, static fn ($c) => ($c['skor'] ?? null) !== null));
$avgSkor      = $scored ? array_sum(array_column($scored, 'skor')) / count($scored) : null;
$sumTerekon   = array_sum(array_column($cabang, 'hari_terekon'));
$sumCocok     = array_sum(array_column($cabang, 'hari_cocok'));
$sumSelisih   = array_sum(array_column($cabang, 'hari_selisih'));

$pct = static fn ($skor): float => $skor === null ? 0.0 : max(0, min(100, (float) $skor));
?>
<div class="modal fade ku-modal" id="<?= esc($modalId) ?>" tabindex="-1" aria-labelledby="<?= esc($modalId) ?>Label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="<?= esc($modalId) ?>Label">Akurasi Laporan Keuangan per Cabang</h5>
                    <div class="ku-modal__sub">
                        <?php if ($periode !== ''): ?>Periode <strong><?= esc($periode) ?></strong> · <?php endif; ?>
                        hari rekonsiliasi terverifikasi (tidak ada selisih)
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <div class="ku-stats">
                    <div class="ku-stat">
                        <div class="ku-stat__label">Hari Terverifikasi</div>
                        <div class="ku-stat__value"><?= (int) $sumTerekon ?></div>
                    </div>
                    <div class="ku-stat">
                        <div class="ku-stat__label">Hari Cocok</div>
                        <div class="ku-stat__value"><?= (int) $sumCocok ?></div>
                    </div>
                    <div class="ku-stat">
                        <div class="ku-stat__label">Hari Selisih</div>
                        <div class="ku-stat__value"><?= (int) $sumSelisih ?></div>
                    </div>
                    <div class="ku-stat">
                        <div class="ku-stat__label">Rata-rata Nilai</div>
                        <div class="ku-stat__value"><?= $avgSkor !== null ? number_format($avgSkor, 2, ',', '.') : '—' ?></div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle ku-table mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Cabang</th>
                                <th class="text-end">Terverifikasi</th>
                                <th class="text-end">Cocok</th>
                                <th class="text-end">Selisih</th>
                                <th style="min-width:8.5rem;">Akurasi</th>
                                <th class="text-end">Kontribusi</th>
                                <th class="pe-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cabang as $cb): ?>
                                <?php
                                $status    = (string) ($cb['status'] ?? '');
                                $belum     = $status === 'belum_verifikasi' || $status === 'error';
                                $skor      = $cb['skor'] ?? null;
                                $akurasi   = $cb['akurasi'] ?? null;
                                $selisih   = (int) ($cb['hari_selisih'] ?? 0);
                                $reachedCb = !empty($cb['reached']);

                                if ($belum) {
                                    $tone = 'muted';
                                } elseif ($reachedCb) {
                                    $tone = 'ok';
                                } elseif ($selisih > 0) {
                                    $tone = $akurasi !== null && $akurasi >= 80 ? 'warn' : 'bad';
                                } else {
                                    $tone = 'ok';
                                }
                                ?>
                                <tr class="ku-trow ku-trow--<?= $tone ?>">
                                    <td class="ps-3">
                                        <div class="ku-tname">
                                            <span class="ku__dot" aria-hidden="true"></span>
                                            <b>Cab. <?= $cb['unit'] ?></b>
                                            <?php if (!empty($cb['nama'])): ?>
                                                <span class="ku__unit">· <?= esc($cb['nama']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-end"><?= (int) ($cb['hari_terekon'] ?? 0) ?></td>
                                    <td class="text-end"><?= (int) ($cb['hari_cocok'] ?? 0) ?></td>
                                    <td class="text-end">
                                        <?php if ($selisih > 0): ?>
                                            <span class="text-danger fw-semibold"><?= $selisih ?></span>
                                        <?php else: ?>
                                            <?= $selisih ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="ku-ratio">
                                            <span><?= $akurasi !== null ? number_format((float) $akurasi, 2, ',', '.') . '%' : '—' ?></span>
                                        </div>
                                        <div class="ku-mini"><span style="--w: <?= number_format($pct($skor) / 100, 4, '.', '') ?>"></span></div>
                                    </td>
                                    <td class="text-end"><?= ($cb['kontribusi'] ?? null) !== null ? number_format((float) $cb['kontribusi'], 2, ',', '.') : '—' ?></td>
                                    <td class="pe-3 text-center">
                                        <?php if ($belum): ?>
                                            <span class="badge rounded-pill bg-secondary-subtle text-secondary">Belum terverifikasi</span>
                                        <?php elseif ($reachedCb): ?>
                                            <span class="badge rounded-pill bg-success-subtle text-success">Akurat</span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill bg-danger-subtle text-danger">Kurang <?= number_format((float) ($cb['shortfall'] ?? 0), 2, ',', '.') ?> poin</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer ku-modal__foot">
                <span>
                    Nilai = (hari terverifikasi <b>tanpa selisih</b> ÷ hari terverifikasi) × 100, dari
                    <a href="<?= base_url('finance/rekonsiliasi') ?>">Rekonsiliasi Harian</a>.
                    Satu hari berselisih menurunkan nilai; hari yang belum diverifikasi Manager tidak dihitung.
                </span>
            </div>
        </div>
    </div>
</div>
