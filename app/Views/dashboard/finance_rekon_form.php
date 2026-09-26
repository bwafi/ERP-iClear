<?php
use App\Services\Finance\RekonDailyCalculator;

$ex = $existing ?? null;
$statusHarian = RekonDailyCalculator::statusHarian($ex);

$checkedCash = $ex ? (int)$ex->checked_cash_masuk : 0;
$checkedTransfer = $ex ? (int)$ex->checked_transfer_masuk : 0;
$checkedKeluar = $ex ? (int)$ex->checked_kas_keluar : 0;

$actualCash = $ex ? (int)$ex->actual_cash_masuk : '';
$actualTransfer = $ex ? (int)$ex->actual_transfer_masuk : '';
$actualKeluar = $ex ? (int)$ex->actual_kas_keluar : '';

$selisihCash = $ex ? (int)$ex->selisih_cash_masuk : null;
$selisihTransfer = $ex ? (int)$ex->selisih_transfer_masuk : null;
$selisihKeluar = $ex ? (int)$ex->selisih_kas_keluar : null;

$catatan = $ex ? $ex->catatan : '';

function rekonStatusLabel($checked, $selisih) {
    if (!$checked) return '<span class="badge bg-secondary">Belum diperiksa</span>';
    if ($selisih === null) return '<span class="badge bg-secondary">Belum diperiksa</span>';
    if ((int)$selisih === 0) return '<span class="badge bg-success">Cocok</span>';
    return '<span class="badge bg-info">Selisih</span>';
}
?>
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Rekonsiliasi — <?= esc($tanggal) ?></h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('dashboard/finance') ?>">Dashboard Finance</a></li>
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('finance/rekonsiliasi?unit_id=' . ($unit_id ?? '') . '&month=' . substr($tanggal, 0, 7)) ?>">Rekonsiliasi</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= esc($tanggal) ?></li>
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

        <div class="row mb-3">
            <div class="col-md-4">
                <strong>Unit:</strong> <?= esc($unit_name) ?>
            </div>
            <div class="col-md-4">
                <strong>Tanggal:</strong> <?= esc($tanggal) ?>
            </div>
            <div class="col-md-4">
                <strong>Status:</strong>
                <span class="badge <?= RekonDailyCalculator::badgeStatus($statusHarian) ?>">
                    <?= esc(RekonDailyCalculator::labelStatus($statusHarian)) ?>
                </span>
            </div>
        </div>

        <form action="<?= base_url('finance/rekon/save') ?>" method="post">
            <input type="hidden" name="unit_id" value="<?= $unit_id ?>">
            <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:25%">Kelompok</th>
                            <th class="text-end" style="width:18%">ERP</th>
                            <th class="text-end" style="width:18%">Aktual</th>
                            <th class="text-end" style="width:15%">Selisih</th>
                            <th class="text-center" style="width:12%">Status</th>
                            <th class="text-center" style="width:12%">Sudah Diperiksa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-semibold">Cash Masuk</td>
                            <td class="text-end">Rp <?= number_format($erp['cash_masuk'] ?? 0, 0, ',', '.') ?></td>
                            <td>
                                <input type="text" class="form-control text-end rupiah-rekon"
                                       name="actual_cash_masuk"
                                       value="<?= $actualCash !== '' ? number_format($actualCash, 0, ',', '.') : '' ?>"
                                       data-erp="<?= (int)($erp['cash_masuk'] ?? 0) ?>"
                                       data-selisih="selisih_cash_masuk"
                                       data-status="status_cash_masuk"
                                       placeholder="0">
                            </td>
                            <td class="text-end" id="selisih_cash_masuk">
                                <?= $selisihCash !== null ? 'Rp ' . number_format($selisihCash, 0, ',', '.') : '—' ?>
                            </td>
                            <td class="text-center" id="status_cash_masuk">
                                <?= rekonStatusLabel($checkedCash, $selisihCash) ?>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input" name="checked_cash_masuk" value="1"
                                    <?= $checkedCash ? 'checked' : '' ?>>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Transfer Masuk</td>
                            <td class="text-end">Rp <?= number_format($erp['transfer_masuk'] ?? 0, 0, ',', '.') ?></td>
                            <td>
                                <input type="text" class="form-control text-end rupiah-rekon"
                                       name="actual_transfer_masuk"
                                       value="<?= $actualTransfer !== '' ? number_format($actualTransfer, 0, ',', '.') : '' ?>"
                                       data-erp="<?= (int)($erp['transfer_masuk'] ?? 0) ?>"
                                       data-selisih="selisih_transfer_masuk"
                                       data-status="status_transfer_masuk"
                                       placeholder="0">
                            </td>
                            <td class="text-end" id="selisih_transfer_masuk">
                                <?= $selisihTransfer !== null ? 'Rp ' . number_format($selisihTransfer, 0, ',', '.') : '—' ?>
                            </td>
                            <td class="text-center" id="status_transfer_masuk">
                                <?= rekonStatusLabel($checkedTransfer, $selisihTransfer) ?>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input" name="checked_transfer_masuk" value="1"
                                    <?= $checkedTransfer ? 'checked' : '' ?>>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Kas Keluar</td>
                            <td class="text-end">Rp <?= number_format($erp['kas_keluar'] ?? 0, 0, ',', '.') ?></td>
                            <td>
                                <input type="text" class="form-control text-end rupiah-rekon"
                                       name="actual_kas_keluar"
                                       value="<?= $actualKeluar !== '' ? number_format($actualKeluar, 0, ',', '.') : '' ?>"
                                       data-erp="<?= (int)($erp['kas_keluar'] ?? 0) ?>"
                                       data-selisih="selisih_kas_keluar"
                                       data-status="status_kas_keluar"
                                       placeholder="0">
                            </td>
                            <td class="text-end" id="selisih_kas_keluar">
                                <?= $selisihKeluar !== null ? 'Rp ' . number_format($selisihKeluar, 0, ',', '.') : '—' ?>
                            </td>
                            <td class="text-center" id="status_kas_keluar">
                                <?= rekonStatusLabel($checkedKeluar, $selisihKeluar) ?>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input" name="checked_kas_keluar" value="1"
                                    <?= $checkedKeluar ? 'checked' : '' ?>>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mb-3">
                <label for="catatan" class="form-label">Catatan (opsional)</label>
                <textarea class="form-control" id="catatan" name="catatan" rows="2"><?= esc($catatan) ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Simpan Rekonsiliasi</button>
                <a href="<?= base_url('finance/rekonsiliasi?unit_id=' . ($unit_id ?? '') . '&month=' . substr($tanggal, 0, 7)) ?>"
                   class="btn btn-outline-secondary">Kembali</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function parseRupiah(str) {
        return parseInt(String(str).replace(/[^0-9]/g, '') || '0', 10);
    }
    function formatRupiah(num) {
        return 'Rp ' + num.toLocaleString('id-ID');
    }

    document.querySelectorAll('.rupiah-rekon').forEach(function(input) {
        input.addEventListener('input', function() {
            var raw = parseRupiah(this.value);
            var erp = parseInt(this.dataset.erp || '0', 10);
            var selisih = raw - erp;
            var selisihEl = document.getElementById(this.dataset.selisih);
            var statusEl = document.getElementById(this.dataset.status);

            if (selisihEl) selisihEl.textContent = formatRupiah(selisih);
            if (statusEl) {
                if (selisih === 0) {
                    statusEl.innerHTML = '<span class="badge bg-success">Cocok</span>';
                } else {
                    statusEl.innerHTML = '<span class="badge bg-info">Selisih</span>';
                }
            }

            var cursorPos = this.selectionStart;
            var oldLen = this.value.length;
            this.value = raw > 0 ? raw.toLocaleString('id-ID') : '';
            var newLen = this.value.length;
            this.setSelectionRange(cursorPos + (newLen - oldLen), cursorPos + (newLen - oldLen));
        });
    });
});
</script>
