<?php
/**
 * Ringkasan KPI "Akurasi Laporan Keuangan" untuk tabel Penilaian Kinerja.
 *
 * Menampilkan ringkasan (jumlah cabang, total hari selisih, rata-rata nilai)
 * dan tombol pembuka modal; rincian per cabang ada di `_akurasi_laporan_modal`.
 *
 * Memakai komponen visual `.ku` (ringkasan + tombol) yang sama dengan KPI
 * Kesehatan Uang agar tampilan tabel konsisten.
 *
 * Variabel:
 *   $cabang  array<int,array<string,mixed>>  hasil FinanceKpiService::akurasiBreakdown()
 *   $modalId string                           id modal rincian (unik per baris)
 */

$cabang  = $cabang ?? [];
$modalId = $modalId ?? 'akModal';

$scored       = array_values(array_filter($cabang, static fn ($c) => ($c['skor'] ?? null) !== null));
$avgAkurasi   = $scored ? array_sum(array_column($scored, 'skor')) / count($scored) : null;
$totalSelisih = array_sum(array_column($cabang, 'hari_selisih'));
?>
<div class="ku">
    <div>
        <span class="ku__eyebrow">Rincian per cabang</span>
        <span class="ku__meta">
            <?= count($cabang) ?> cabang
            · <b><?= (int) $totalSelisih ?></b> hari selisih
            <?php if ($avgAkurasi !== null): ?>
                · rata-rata <b><?= number_format($avgAkurasi, 2, ',', '.') ?></b>
            <?php endif; ?>
        </span>
    </div>
    <button type="button" class="ku__btn" data-bs-toggle="modal" data-bs-target="#<?= esc($modalId) ?>">
        <i class="fa fa-arrows-alt" aria-hidden="true"></i> Rincian lengkap
    </button>
</div>
