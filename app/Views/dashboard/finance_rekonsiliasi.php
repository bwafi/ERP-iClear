<?php
use App\Services\Finance\RekonDailyCalculator;
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
        <form method="get" class="mb-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="month" class="form-label">Periode</label>
                    <input type="month" class="form-control" id="month" name="month" value="<?= esc($month ?? date('Y-m')) ?>">
                </div>
                <div class="col-md-3">
                    <label for="unit_id" class="form-label">Unit</label>
                    <select class="form-select" id="unit_id" name="unit_id">
                        <?php foreach ($units ?? [] as $unit): ?>
                            <option value="<?= $unit->idunit ?>" <?= ((int)($unit_id ?? 0) === (int) $unit->idunit ? 'selected' : '') ?>>
                                <?= esc($unit->NAMA_UNIT) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </div>
        </form>

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

        <?php if (!empty($unit_id)): ?>

            <?php if (!empty($rekon_score)): ?>
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 bg-primary-subtle">
                            <div class="card-body text-center py-3">
                                <div class="text-muted small">Skor Rekonsiliasi</div>
                                <div class="fs-2 fw-bold"><?= $rekon_score['score'] !== null ? number_format($rekon_score['score'], 1) : '—' ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 bg-success-subtle">
                            <div class="card-body text-center py-3">
                                <div class="text-muted small">Hari Lengkap</div>
                                <div class="fs-2 fw-bold"><?= $rekon_score['detail']['hari_lengkap'] ?? 0 ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 bg-warning-subtle">
                            <div class="card-body text-center py-3">
                                <div class="text-muted small">Hari Kerja</div>
                                <div class="fs-2 fw-bold"><?= $rekon_score['detail']['hari_kerja'] ?? 0 ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th class="text-end">Cash Masuk (ERP)</th>
                            <th class="text-end">Transfer Masuk (ERP)</th>
                            <th class="text-end">Kas Keluar (ERP)</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data periode ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($list as $item):
                                $row = $item['row'];
                                $status = $item['status'];
                                $badge = RekonDailyCalculator::badgeStatus($status);
                                $label = RekonDailyCalculator::labelStatus($status);
                            ?>
                            <tr>
                                <td><?= esc($item['tanggal']) ?></td>
                                <td class="text-end"><?= $row ? 'Rp ' . number_format($row->erp_cash_masuk, 0, ',', '.') : '—' ?></td>
                                <td class="text-end"><?= $row ? 'Rp ' . number_format($row->erp_transfer_masuk, 0, ',', '.') : '—' ?></td>
                                <td class="text-end"><?= $row ? 'Rp ' . number_format($row->erp_kas_keluar, 0, ',', '.') : '—' ?></td>
                                <td class="text-center"><span class="badge <?= $badge ?>"><?= esc($label) ?></span></td>
                                <td class="text-center">
                                    <a href="<?= base_url('finance/rekon/form?unit_id=' . $unit_id . '&tanggal=' . $item['tanggal']) ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <?= $row ? 'Edit' : 'Input' ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <div class="text-center text-muted py-5">Pilih unit untuk melihat rekonsiliasi.</div>
        <?php endif; ?>
    </div>
</div>
