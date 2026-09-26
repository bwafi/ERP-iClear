<?php
use App\Services\Finance\RekonDailyCalculator;
use App\Models\ModelFinanceRekonDaily;

$units = $units ?? [];
$unit_id = (int) ($unit_id ?? 0);
$unit_name = $unit_name ?? '—';
$month = $month ?? date('Y-m');
$monthLabel = date('F Y', strtotime($month . '-01'));
$list = $list ?? [];
$rekon_score = $rekon_score ?? null;
$can_input = $can_input ?? false;
$can_approve = $can_approve ?? false;
$status_proses = $status_proses ?? '';
$akun_names = $akun_names ?? [];

$baseParams = ['unit_id' => $unit_id, 'month' => $month];
?>
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Rekonsiliasi Harian</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('dashboard/finance') ?>">Dashboard Finance</a></li>
                <li class="breadcrumb-item active" aria-current="page">Rekonsiliasi</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">

        <?php if (session()->getFlashdata('sukses')): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= esc(session()->getFlashdata('sukses')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('gagal')): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= esc(session()->getFlashdata('gagal')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('finance/rekonsiliasi') ?>" method="get" class="row g-2 align-items-end mb-4">
            <div class="col-sm-6 col-lg-3">
                <label class="form-label mb-1">Unit</label>
                <select name="unit_id" class="form-select form-select-sm" required>
                    <?php foreach ($units as $u): ?>
                        <option value="<?= (int) ($u->idunit ?? 0) ?>" <?= (int) ($u->idunit ?? 0) === $unit_id ? 'selected' : '' ?>>
                            <?= esc($u->NAMA_UNIT ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label mb-1">Bulan</label>
                <input type="month" name="month" class="form-control form-control-sm" value="<?= esc($month) ?>">
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label mb-1">Status Proses</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach ([
                        ModelFinanceRekonDaily::STATUS_DRAFT        => 'Draft',
                        ModelFinanceRekonDaily::STATUS_SUBMITTED    => 'Submitted',
                        ModelFinanceRekonDaily::STATUS_VERIFIED     => 'Verified',
                        ModelFinanceRekonDaily::STATUS_NEED_REVISION => 'Perlu Revisi',
                    ] as $val => $label): ?>
                        <option value="<?= esc($val) ?>" <?= $status_proses === $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-3">
                <button type="submit" class="btn btn-primary btn-sm">Terapkan</button>
            </div>
        </form>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small mb-1">Unit</div>
                    <div class="fw-semibold"><?= esc($unit_name) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small mb-1">Periode</div>
                    <div class="fw-semibold"><?= esc($monthLabel) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small mb-1">Skor KPI Rekonsiliasi</div>
                    <div class="fw-semibold">
                        <?php if (! empty($rekon_score['score']) || (isset($rekon_score['score']) && $rekon_score['score'] === 0.0)): ?>
                            <?= number_format((float) $rekon_score['score'], 2) ?>%
                        <?php else: ?>
                            <span class="text-muted">Belum tersedia</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small mt-1">
                        <?php if (! empty($rekon_score['detail']['hari_kerja'])): ?>
                            <?= (int) $rekon_score['detail']['hari_lengkap_verified'] ?>
                            / <?= (int) $rekon_score['detail']['hari_kerja'] ?>
                            hari lengkap &amp; verified
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($list)): ?>
            <div class="alert alert-info mb-0">Belum ada data rekonsiliasi untuk periode ini.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:110px">Tanggal</th>
                            <th class="text-center" style="width:130px">Status Hasil</th>
                            <th class="text-center" style="width:130px">Status Proses</th>
                            <th class="text-end">Cash (ERP / Aktual / Selisih)</th>
                            <th class="text-end">Transfer (ERP / Aktual / Selisih)</th>
                            <th class="text-end">Kas Keluar (ERP / Aktual / Selisih)</th>
                            <th style="width:150px">Oleh</th>
                            <th class="text-center" style="width:120px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($list as $item):
                        $row = $item['row'];
                        $tgl = (string) $item['tanggal'];
                        $sHarian = RekonDailyCalculator::statusHarian($row);
                        $sProses = RekonDailyCalculator::statusProses($row);
                        $formUrl = base_url('finance/rekon/form?unit_id=' . $unit_id . '&tanggal=' . $tgl);
                        // actual_* NULL = belum diisi; angka 0 tetap nilai sah.
                        $fmt = static function ($v) {
                            return $v === null ? '<span class="text-muted">&mdash;</span>'
                                : number_format((int) $v, 0, ',', '.');
                        };
                    ?>
                        <tr>
                            <td><?= esc(date('d', strtotime($tgl))) ?> <?= esc(date('M Y', strtotime($tgl))) ?></td>
                            <td class="text-center">
                                <span class="badge <?= RekonDailyCalculator::badgeStatus($sHarian) ?>">
                                    <?= esc(RekonDailyCalculator::labelStatus($sHarian)) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= RekonDailyCalculator::badgeProses($sProses) ?>">
                                    <?= esc(RekonDailyCalculator::labelProses($sProses)) ?>
                                </span>
                                <?php if ($row && $row->catatan_revisi): ?>
                                    <div class="text-danger small mt-1" title="<?= esc((string) $row->catatan_revisi) ?>">
                                        <i class="fas fa-exclamation-triangle"></i> ada catatan revisi
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end small">
                                <?php if ($row): ?>
                                    <?= number_format((int) $row->erp_cash_masuk, 0, ',', '.') ?>
                                    /
                                    <span class="fw-semibold"><?= $fmt($row->actual_cash_masuk) ?></span>
                                    /
                                    <?php if ($row->selisih_cash_masuk === null): ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php else: ?>
                                        <span class="<?= (int) $row->selisih_cash_masuk === 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= number_format((int) $row->selisih_cash_masuk, 0, ',', '.') ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end small">
                                <?php if ($row): ?>
                                    <?= number_format((int) $row->erp_transfer_masuk, 0, ',', '.') ?>
                                    /
                                    <span class="fw-semibold"><?= $fmt($row->actual_transfer_masuk) ?></span>
                                    /
                                    <?php if ($row->selisih_transfer_masuk === null): ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php else: ?>
                                        <span class="<?= (int) $row->selisih_transfer_masuk === 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= number_format((int) $row->selisih_transfer_masuk, 0, ',', '.') ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end small">
                                <?php if ($row): ?>
                                    <?= number_format((int) $row->erp_kas_keluar, 0, ',', '.') ?>
                                    /
                                    <span class="fw-semibold"><?= $fmt($row->actual_kas_keluar) ?></span>
                                    /
                                    <?php if ($row->selisih_kas_keluar === null): ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php else: ?>
                                        <span class="<?= (int) $row->selisih_kas_keluar === 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= number_format((int) $row->selisih_kas_keluar, 0, ',', '.') ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if ($row): ?>
                                    <div><span class="text-muted">Input:</span>
                                        <?= esc($akun_names[(int) $row->input_by] ?? '—') ?>
                                    </div>
                                    <?php if ($row->submitted_by): ?>
                                        <div><span class="text-muted">Submit:</span>
                                            <?= esc($akun_names[(int) $row->submitted_by] ?? '—') ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($row->verified_by): ?>
                                        <div><span class="text-muted">Verify:</span>
                                            <?= esc($akun_names[(int) $row->verified_by] ?? '—') ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($can_input): ?>
                                    <a href="<?= $formUrl ?>" class="btn btn-sm btn-outline-primary">
                                        <?= $row ? 'Detail' : 'Isi' ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="text-muted small mb-0 mt-3">
                Kolom nominal ditampilkan sebagai <strong>ERP / Aktual / Selisih</strong>.
                Selisih = Aktual − ERP; nilai negatif berarti aktual lebih kecil dari ERP.
            </p>
        <?php endif; ?>
    </div>
</div>
