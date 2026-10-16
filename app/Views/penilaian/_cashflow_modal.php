<?php
/**
 * Modal rincian KPI "Net Cash Flow" — arus kas tiap cabang pada periode.
 *
 * Menampilkan penerimaan, kas keluar, Net Cash Flow, dan NCF% tiap cabang
 * berdampingan dengan target bisnis (default 20%), supaya terlihat cabang mana
 * yang menahan nilai. Memakai komponen visual `.ku` yang sama dengan modal
 * Kesehatan Uang/Akurasi agar konsisten dengan tabel di sekitarnya.
 *
 * Variabel:
 *   $cabang  array<int,array<string,mixed>>  hasil FinanceKpiService::cashflowBreakdown()
 *   $modalId string                           id modal (unik per baris tabel)
 *   $periode string                           label periode, mis. "Oktober 2026"
 */

$cabang  = $cabang ?? [];
$modalId = $modalId ?? 'cfModal';
$periode = $periode ?? '';

$scored     = array_values(array_filter($cabang, static fn ($c) => ($c['skor'] ?? null) !== null));
$avgSkor    = $scored ? array_sum(array_column($scored, 'skor')) / count($scored) : null;
$reached    = count(array_filter($cabang, static fn ($c) => !empty($c['reached'])));
$sumMasuk   = array_sum(array_column($cabang, 'penerimaan'));
$sumKeluar  = array_sum(array_column($cabang, 'kas_keluar'));
$sumNet     = array_sum(array_column($cabang, 'net'));
$target     = (float) ($cabang[0]['target_persen'] ?? 20);

$rp  = static fn ($n): string => 'Rp ' . number_format((float) $n, 0, ',', '.');
$pct = static fn ($persen, float $target): float => ($persen !== null && $target > 0)
    ? max(0, min(100, ((float) $persen / $target) * 100))
    : 0.0;
?>
<div class="modal fade ku-modal" id="<?= esc($modalId) ?>" tabindex="-1" aria-labelledby="<?= esc($modalId) ?>Label" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="<?= esc($modalId) ?>Label">Net Cash Flow per Cabang</h5>
                    <div class="ku-modal__sub">
                        <?php if ($periode !== ''): ?>Periode <strong><?= esc($periode) ?></strong> · <?php endif; ?>
                        <?= count($cabang) ?> cabang · <?= $reached ?> tercapai · target <strong><?= number_format($target, 0, ',', '.') ?>%</strong>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <div class="ku-stats">
                    <div class="ku-stat">
                        <div class="ku-stat__label">Penerimaan</div>
                        <div class="ku-stat__value"><?= $rp($sumMasuk) ?></div>
                    </div>
                    <div class="ku-stat">
                        <div class="ku-stat__label">Kas Keluar</div>
                        <div class="ku-stat__value"><?= $rp($sumKeluar) ?></div>
                    </div>
                    <div class="ku-stat">
                        <div class="ku-stat__label">Net Cash Flow</div>
                        <div class="ku-stat__value"><?= $rp($sumNet) ?></div>
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
                                <th class="text-end">Penerimaan</th>
                                <th class="text-end">Kas Keluar</th>
                                <th class="text-end">Net Cash Flow</th>
                                <th style="min-width:8.5rem;">NCF% / <?= number_format($target, 0, ',', '.') ?>%</th>
                                <th class="text-end">Kontribusi</th>
                                <th class="pe-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cabang as $cb): ?>
                                <?php
                                $status    = (string) ($cb['status'] ?? '');
                                $kosong    = $status === 'data_kosong' || $status === 'error';
                                $persen    = $cb['persen'] ?? null;
                                $skor      = $cb['skor'] ?? null;
                                $net       = (int) ($cb['net'] ?? 0);
                                $reachedCb = !empty($cb['reached']);

                                if ($kosong) {
                                    $tone = 'muted';
                                } elseif ($reachedCb) {
                                    $tone = 'ok';
                                } elseif ($persen !== null && $persen >= $target * 0.6) {
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
                                    <td class="text-end"><?= $rp($cb['penerimaan'] ?? 0) ?></td>
                                    <td class="text-end"><?= $rp($cb['kas_keluar'] ?? 0) ?></td>
                                    <td class="text-end <?= $net < 0 ? 'text-danger fw-semibold' : '' ?>"><?= $rp($net) ?></td>
                                    <td>
                                        <div class="ku-ratio">
                                            <span><?= $persen !== null ? number_format((float) $persen, 2, ',', '.') . '%' : '—' ?></span>
                                        </div>
                                        <div class="ku-mini"><span style="--w: <?= number_format($pct($persen, $target) / 100, 4, '.', '') ?>"></span></div>
                                    </td>
                                    <td class="text-end"><?= ($cb['kontribusi'] ?? null) !== null ? number_format((float) $cb['kontribusi'], 2, ',', '.') : '—' ?></td>
                                    <td class="pe-3 text-center">
                                        <?php if ($kosong): ?>
                                            <span class="badge rounded-pill bg-secondary-subtle text-secondary">Data kosong</span>
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
                    Net Cash Flow = Penerimaan − Kas Keluar; NCF% = Net Cash Flow ÷ Penerimaan × 100.
                    Skor kategori = min((NCF% ÷ <?= number_format($target, 0, ',', '.') ?>%) × 100, 100).
                    Penerimaan &amp; kas keluar mengikuti definisi Tutup Kasir; periode dihitung satu bulan penuh.
                </span>
            </div>
        </div>
    </div>
</div>
