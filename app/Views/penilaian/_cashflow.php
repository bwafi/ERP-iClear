<?php
/**
 * Ringkasan KPI "Net Cash Flow" untuk tabel Penilaian Kinerja.
 *
 * Menampilkan ringkasan (jumlah cabang, tercapai, target, rata-rata skor) dan
 * tombol pembuka modal; rincian per cabang ada di `_cashflow_modal`.
 *
 * Memakai komponen visual `.ku` yang sama dengan KPI Kesehatan Uang/Akurasi
 * agar tampilan tabel konsisten.
 *
 * Variabel:
 *   $cabang  array<int,array<string,mixed>>  hasil FinanceKpiService::cashflowBreakdown()
 *   $modalId string                           id modal rincian (unik per baris)
 */

$cabang  = $cabang ?? [];
$modalId = $modalId ?? 'cfModal';

$scored  = array_values(array_filter($cabang, static fn ($c) => ($c['skor'] ?? null) !== null));
$avgSkor = $scored ? array_sum(array_column($scored, 'skor')) / count($scored) : null;
$reached = count(array_filter($cabang, static fn ($c) => !empty($c['reached'])));
$target  = (float) ($cabang[0]['target_persen'] ?? 20);
?>
<div class="ku">
    <div>
        <span class="ku__eyebrow">Rincian per cabang</span>
        <span class="ku__meta">
            <?= count($cabang) ?> cabang
            · <b><?= $reached ?></b> tercapai
            · target <b><?= number_format($target, 0, ',', '.') ?>%</b>
            <?php if ($avgSkor !== null): ?>
                · rata-rata skor <b><?= number_format($avgSkor, 2, ',', '.') ?></b>
            <?php endif; ?>
        </span>
    </div>
    <button type="button" class="ku__btn" data-bs-toggle="modal" data-bs-target="#<?= esc($modalId) ?>">
        <i class="fa fa-arrows-alt" aria-hidden="true"></i> Rincian lengkap
    </button>
</div>
