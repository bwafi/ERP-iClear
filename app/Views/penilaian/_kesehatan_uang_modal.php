<?php
/**
 * Modal rincian KPI "Kesehatan Uang per Cabang" — versi lengkap (nominal
 * saldo kas & bank, biaya, rasio, skor, kontribusi, status tiap cabang).
 *
 * Variabel:
 *   $cabang  array<int,array<string,mixed>>  hasil FinanceKpiService::kesehatanUangBreakdown()
 *   $modalId string                           id modal (unik per baris tabel)
 *   $periode string                           label periode, mis. "Oktober 2026"
 */

$cabang  = $cabang ?? [];
$modalId = $modalId ?? 'kuModal';
$periode = $periode ?? '';

$scored    = array_values(array_filter($cabang, static fn ($c) => ($c['skor'] ?? null) !== null));
$avgSkor   = $scored ? array_sum(array_column($scored, 'skor')) / count($scored) : null;
$reached   = count(array_filter($cabang, static fn ($c) => !empty($c['reached'])));
$sumSaldo  = array_sum(array_column($cabang, 'saldo'));
$sumKeluar = array_sum(array_column($cabang, 'keluar'));
$ratioAgg  = $sumKeluar > 0 ? $sumSaldo / $sumKeluar * 100 : null;
$target    = (float) ($cabang[0]['target_rasio'] ?? 120);

$rp = static fn ($n): string => 'Rp ' . number_format((float) $n, 0, ',', '.');
$pct = static fn ($rasio, float $target): float => ($rasio !== null && $target > 0)
    ? max(0, min(100, ((float) $rasio / $target) * 100))
    : 0.0;
?>
<div class="modal fade ku-modal" id="<?= esc($modalId) ?>" tabindex="-1" aria-labelledby="<?= esc($modalId) ?>Label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="<?= esc($modalId) ?>Label">Kesehatan Uang per Cabang</h5>
                    <div class="ku-modal__sub">
                        <?php if ($periode !== ''): ?>Periode <strong><?= esc($periode) ?></strong> · <?php endif; ?>
                        <?= count($cabang) ?> cabang · <?= $reached ?> tercapai
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <div class="ku-stats">
                    <div class="ku-stat">
                        <div class="ku-stat__label">Saldo Kas &amp; Bank</div>
                        <div class="ku-stat__value"><?= $rp($sumSaldo) ?></div>
                    </div>
                    <div class="ku-stat">
                        <div class="ku-stat__label">Uang Keluar</div>
                        <div class="ku-stat__value"><?= $rp($sumKeluar) ?></div>
                    </div>
                    <div class="ku-stat">
                        <div class="ku-stat__label">Rasio Gabungan</div>
                        <div class="ku-stat__value"><?= $ratioAgg !== null ? number_format($ratioAgg, 2, ',', '.') . '%' : '—' ?></div>
                    </div>
                    <div class="ku-stat">
                        <div class="ku-stat__label">Rata-rata Skor</div>
                        <div class="ku-stat__value"><?= $avgSkor !== null ? number_format($avgSkor, 2, ',', '.') : '—' ?></div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle ku-table mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Cabang</th>
                                <th class="text-end">Saldo Kas &amp; Bank</th>
                                <th class="text-end">Uang Keluar</th>
                                <th style="min-width:8.5rem;">Rasio / <?= number_format($target, 0, ',', '.') ?>%</th>
                                <th class="text-end">Skor</th>
                                <th class="text-end">Kontribusi</th>
                                <th class="pe-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cabang as $cb): ?>
                                <?php
                                $status  = (string) ($cb['status'] ?? '');
                                $tanpaSkor = in_array($status, ['kebutuhan_nol', 'saldo_belum_tersedia'], true);
                                $rasio   = $cb['rasio'] ?? null;
                                $skor    = $cb['skor'] ?? null;
                                $reachedCb = !empty($cb['reached']);

                                if ($tanpaSkor) {
                                    $tone = 'muted';
                                } elseif ($reachedCb) {
                                    $tone = 'ok';
                                } elseif ($rasio !== null && $rasio >= $target * 0.6) {
                                    $tone = 'warn';
                                } else {
                                    $tone = 'bad';
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
                                    <td class="text-end"><?= $rp($cb['saldo'] ?? 0) ?></td>
                                    <td class="text-end"><?= $rp($cb['keluar'] ?? 0) ?></td>
                                    <td>
                                        <div class="ku-ratio">
                                            <span><?= $rasio !== null ? number_format((float) $rasio, 2, ',', '.') . '%' : '—' ?></span>
                                        </div>
                                        <div class="ku-mini"><span style="--w: <?= number_format($pct($rasio, $target) / 100, 4, '.', '') ?>"></span></div>
                                    </td>
                                    <td class="text-end fw-bold"><?= $skor !== null ? number_format((float) $skor, 2, ',', '.') : '—' ?></td>
                                    <td class="text-end"><?= ($cb['kontribusi'] ?? null) !== null ? number_format((float) $cb['kontribusi'], 2, ',', '.') : '—' ?></td>
                                    <td class="pe-3 text-center">
                                        <?php if ($status === 'kebutuhan_nol'): ?>
                                            <span class="badge rounded-pill bg-secondary-subtle text-secondary">Kebutuhan nol</span>
                                        <?php elseif ($status === 'saldo_belum_tersedia'): ?>
                                            <span class="badge rounded-pill bg-secondary-subtle text-secondary">Saldo belum tersedia</span>
                                        <?php elseif ($reachedCb): ?>
                                            <span class="badge rounded-pill bg-success-subtle text-success">Tercapai</span>
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
                    Rasio = (saldo kas + bank per akhir bulan) ÷ (kas keluar operasional + payroll) × 100.
                    Saldo mengikuti posisi Kas &amp; Bank per unit; biaya dihitung satu bulan penuh; HPP tidak dihitung.
                </span>
            </div>
        </div>
    </div>
</div>
