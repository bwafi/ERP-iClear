<?php
use App\Services\Finance\RekonDailyCalculator;
use App\Models\ModelFinanceRekonDaily;

// Default defensif: view ini selalu dipanggil lewat DashboardFinance::rekonForm(),
// tapi variabel tetap dideklarasikan agar aman bila dipanggil tanpa data.
$tanggal = $tanggal ?? date('Y-m-d');
$unit_id = (int) ($unit_id ?? 0);
$unit_name = $unit_name ?? '—';
$can_input = $can_input ?? false;
$can_approve = $can_approve ?? false;
$can_submit = $can_submit ?? false;
$my_id = (int) ($my_id ?? 0);
$erp = $erp ?? ['cash_masuk' => 0, 'transfer_masuk' => 0, 'kas_keluar' => 0];

$ex = $existing ?? null;
$statusHarian = RekonDailyCalculator::statusHarian($ex);

$actualCash = $ex && $ex->actual_cash_masuk !== null ? (int) $ex->actual_cash_masuk : '';
$actualTransfer = $ex && $ex->actual_transfer_masuk !== null ? (int) $ex->actual_transfer_masuk : '';
$actualKeluar = $ex && $ex->actual_kas_keluar !== null ? (int) $ex->actual_kas_keluar : '';

$selisihCash = $ex ? (int) $ex->selisih_cash_masuk : null;
$selisihTransfer = $ex ? (int) $ex->selisih_transfer_masuk : null;
$selisihKeluar = $ex ? (int) $ex->selisih_kas_keluar : null;

$catatan = $ex ? $ex->catatan : '';
$catatanRevisi = $ex ? $ex->catatan_revisi : '';

$statusProses = RekonDailyCalculator::statusProses($ex);
$locked = RekonDailyCalculator::isLocked($ex);
$disabled = $locked ? ' disabled' : '';

$rekonUrl = 'finance/rekonsiliasi?unit_id=' . ($unit_id ?? '') . '&month=' . substr($tanggal, 0, 7);
$formUrl = 'finance/rekon/form?unit_id=' . ($unit_id ?? '') . '&tanggal=' . $tanggal;

// Status komponen 100% otomatis dari perbandingan ERP vs Aktual.
// Tidak ada input manual: tidak ada lagi checkbox "Sudah Diperiksa".
$rekonStatusLabel = static function (?object $row, string $suffix) {
    $status = RekonDailyCalculator::statusKomponen($row, $suffix);

    return '<span class="badge ' . RekonDailyCalculator::badgeKomponen($status) . '">'
        . RekonDailyCalculator::labelKomponen($status) . '</span>';
};
?>
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Rekonsiliasi — <?= esc($tanggal) ?></h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('dashboard/finance') ?>">Dashboard Finance</a></li>
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url($rekonUrl) ?>">Rekonsiliasi</a></li>
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
            <div class="col-md-3">
                <strong>Unit:</strong> <?= esc($unit_name) ?>
            </div>
            <div class="col-md-3">
                <strong>Tanggal:</strong> <?= esc($tanggal) ?>
            </div>
            <div class="col-md-3">
                <strong>Status Hasil:</strong>
                <span class="badge <?= RekonDailyCalculator::badgeStatus($statusHarian) ?>">
                    <?= esc(RekonDailyCalculator::labelStatus($statusHarian)) ?>
                </span>
            </div>
            <div class="col-md-3">
                <strong>Status Proses:</strong>
                <span class="badge <?= RekonDailyCalculator::badgeProses($statusProses) ?>">
                    <?= esc(RekonDailyCalculator::labelProses($statusProses)) ?>
                </span>
            </div>
        </div>

        <?php if ($locked && $ex): ?>
            <div class="alert alert-success py-2 small">
                Data sudah <strong>VERIFIED</strong> oleh manager
                <?= $ex->verified_by ? ' (#' . (int) $ex->verified_by . ')' : '' ?>
                pada <?= esc((string) $ex->verified_at) ?> dan tidak dapat diubah.
            </div>
        <?php endif; ?>

        <?php if ($statusProses === ModelFinanceRekonDaily::STATUS_NEED_REVISION && $catatanRevisi): ?>
            <div class="alert alert-warning py-2 small">
                <strong>Catatan manager (perlu revisi):</strong> <?= esc((string) $catatanRevisi) ?>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('finance/rekon/save') ?>" method="post">
            <input type="hidden" name="unit_id" value="<?= (int) ($unit_id ?? 0) ?>">
            <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">

            <div class="mb-2 small text-muted" id="rekon-format-hint"
                 data-default="Ketik angka saja, titik ribuan otomatis. Kosong berarti Rp 0.">
                Ketik angka saja, titik ribuan otomatis. Kosong berarti Rp 0.
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:24%">Kelompok</th>
                            <th class="text-end" style="width:18%">ERP</th>
                            <th class="text-end" style="width:20%">Aktual</th>
                            <th class="text-end" style="width:16%">Selisih</th>
                            <th class="text-center" style="width:16%">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-semibold">Cash Masuk</td>
                            <td class="text-end">Rp <?= number_format($erp['cash_masuk'] ?? 0, 0, ',', '.') ?></td>
                            <td>
                                <input type="text" class="form-control text-end rupiah-rekon<?= $disabled ?>"
                                       name="actual_cash_masuk" inputmode="numeric" autocomplete="off" spellcheck="false"
                                       value="<?= $actualCash !== '' ? number_format($actualCash, 0, ',', '.') : '' ?>"
                                       data-group="cash_masuk"
                                       data-erp="<?= (int) ($erp['cash_masuk'] ?? 0) ?>"
                                       data-selishtext="selisih_cash_masuk"
                                       data-status="status_cash_masuk"
                                       placeholder="0">
                            </td>
                            <td class="text-end" id="selisih_cash_masuk">
                                <?= $selisihCash !== null ? 'Rp ' . number_format($selisihCash, 0, ',', '.') : '—' ?>
                            </td>
                            <td class="text-center" id="status_cash_masuk">
                                <?= $rekonStatusLabel($ex, 'cash_masuk') ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Transfer Masuk</td>
                            <td class="text-end">Rp <?= number_format($erp['transfer_masuk'] ?? 0, 0, ',', '.') ?></td>
                            <td>
                                <input type="text" class="form-control text-end rupiah-rekon<?= $disabled ?>"
                                       name="actual_transfer_masuk" inputmode="numeric" autocomplete="off" spellcheck="false"
                                       value="<?= $actualTransfer !== '' ? number_format($actualTransfer, 0, ',', '.') : '' ?>"
                                       data-group="transfer_masuk"
                                       data-erp="<?= (int) ($erp['transfer_masuk'] ?? 0) ?>"
                                       data-selishtext="selisih_transfer_masuk"
                                       data-status="status_transfer_masuk"
                                       placeholder="0">
                            </td>
                            <td class="text-end" id="selisih_transfer_masuk">
                                <?= $selisihTransfer !== null ? 'Rp ' . number_format($selisihTransfer, 0, ',', '.') : '—' ?>
                            </td>
                            <td class="text-center" id="status_transfer_masuk">
                                <?= $rekonStatusLabel($ex, 'transfer_masuk') ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold">Kas Keluar</td>
                            <td class="text-end">Rp <?= number_format($erp['kas_keluar'] ?? 0, 0, ',', '.') ?></td>
                            <td>
                                <input type="text" class="form-control text-end rupiah-rekon<?= $disabled ?>"
                                       name="actual_kas_keluar" inputmode="numeric"
                                       value="<?= $actualKeluar !== '' ? number_format($actualKeluar, 0, ',', '.') : '' ?>"
                                       data-group="kas_keluar"
                                       data-erp="<?= (int) ($erp['kas_keluar'] ?? 0) ?>"
                                       data-selishtext="selisih_kas_keluar"
                                       data-status="status_kas_keluar"
                                       placeholder="0">
                            </td>
                            <td class="text-end" id="selisih_kas_keluar">
                                <?= $selisihKeluar !== null ? 'Rp ' . number_format($selisihKeluar, 0, ',', '.') : '—' ?>
                            </td>
                            <td class="text-center" id="status_kas_keluar">
                                <?= $rekonStatusLabel($ex, 'kas_keluar') ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mb-3">
                <label for="catatan" class="form-label">Catatan (opsional)</label>
                <textarea class="form-control" id="catatan" name="catatan" rows="2"<?= $disabled ?>><?= esc((string) $catatan) ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <?php if (! $locked): ?>
                    <button type="submit" class="btn btn-primary">Simpan Draft</button>
                <?php endif; ?>
                <a href="<?= base_url($rekonUrl) ?>" class="btn btn-outline-secondary">Kembali</a>
            </div>
        </form>

        <hr class="my-4">

        <h6 class="fw-semibold mb-3">Workflow Approval</h6>

        <?php if (! empty($can_submit) && ! $locked): ?>
            <form action="<?= base_url('finance/rekon/submit') ?>" method="post" class="mb-2">
                <input type="hidden" name="unit_id" value="<?= (int) ($unit_id ?? 0) ?>">
                <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">
                <button type="submit" class="btn btn-info">Kirim untuk Verifikasi (Submit)</button>
            </form>
        <?php elseif ($statusProses === ModelFinanceRekonDaily::STATUS_SUBMITTED && empty($can_approve)): ?>
            <div class="alert alert-secondary py-2 small mb-2">
                Menunggu verifikasi Manager / Admin Root. Anda tidak berwenang memverifikasi data ini, termasuk data yang Anda kirim sendiri.
            </div>
        <?php elseif (! $locked): ?>
            <div class="alert alert-secondary py-2 small mb-2">
                Ketiga kelompok harus diperiksa sebelum dapat dikirim untuk verifikasi.
            </div>
        <?php endif; ?>

        <?php if (! empty($can_approve) && $statusProses === ModelFinanceRekonDaily::STATUS_SUBMITTED): ?>
            <form action="<?= base_url('finance/rekon/approve') ?>" method="post" class="row g-2 align-items-end">
                <input type="hidden" name="unit_id" value="<?= (int) ($unit_id ?? 0) ?>">
                <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">
                <div class="col-md-6">
                    <label for="catatan_revisi" class="form-label">Catatan revisi (wajib bila Need Revision)</label>
                    <input type="text" class="form-control" id="catatan_revisi" name="catatan_revisi"
                           value="<?= esc((string) $catatanRevisi) ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" name="action" value="verify" class="btn btn-success w-100">
                        Verify
                    </button>
                </div>
                <div class="col-md-3">
                    <button type="submit" name="action" value="need_revision" class="btn btn-warning w-100">
                        Need Revision
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // -----------------------------------------------------------------
    // Format nominal: user mengetik ANGKA SAJA, titik ribuan dipasang
    // otomatis oleh JS.
    //
    // Kenapa parse ketat (parseNominal) TIDAK dipakai saat mengetik:
    // begitu titik terpasang ("1.000"), ketikan berikutnya menghasilkan
    // "1.0005" yang gagal aturan "3 digit per kelompok" -> field tersangkut
    // dan tidak bisa diketik lagi. Itu justru penyebab input merepotkan.
    //
    // parseNominal yang ketat tetap dipakai untuk NILAI AWAL dari server
    // dan untuk PASTE (menolak negatif / desimal). Server tetap menjadi
    // sumber kebenaran (parseNominalRekon di DashboardFinance).
    // -----------------------------------------------------------------

    /** Buang semua pemisah; sisa harus digit saja. */
    function readDigits(value) {
        return String(value).replace(/[^0-9]/g, '');
    }

    /** Pasang pemisah ribuan setiap 3 digit dari kanan. */
    function groupThousands(digits) {
        return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    /** Parse ketat untuk nilai server & paste (tolak negatif/desimal/sampah). */
    function parseNominal(str) {
        var raw = String(str).replace(/\s|Rp\.?/gi, '');
        if (raw === '') { return 0; }
        // Guard desimal: digit setelah pemisah TERAKHIR harus tepat 3 (p ribuan).
        // "1.000.000" -> "000" (ok). "1.500,25" -> "25" (desimal, tolak).
        // Mengikuti parseNominalRekon() di DashboardFinance (parity).
        var last = Math.max(raw.lastIndexOf('.'), raw.lastIndexOf(','));
        if (last >= 0) {
            if (raw.slice(last + 1).length !== 3) { return NaN; }
        }
        var digits = raw.replace(/[.,]/g, '');
        if (!/^-?\d+$/.test(digits)) { return NaN; }
        var n = parseInt(digits, 10);
        return (isNaN(n) || n < 0) ? NaN : n;
    }

    function formatRupiah(num) {
        return 'Rp ' + num.toLocaleString('id-ID');
    }

    function setInvalid(input, message) {
        input.classList.add('is-invalid');
        input.setAttribute('title', message || '');
        var hint = document.getElementById('rekon-format-hint');
        if (hint) {
            hint.classList.add('text-danger');
            hint.textContent = message;
        }
    }

    function clearInvalid(input) {
        input.classList.remove('is-invalid');
        input.removeAttribute('title');
        var hint = document.getElementById('rekon-format-hint');
        if (hint && hint.classList.contains('text-danger')) {
            hint.classList.remove('text-danger');
            hint.textContent = hint.dataset.default;
        }
    }

    var inputs = {};
    document.querySelectorAll('.rupiah-rekon').forEach(function (input) {
        inputs[input.dataset.group] = input;
    });

    /**
     * Status komponen 100% otomatis dari perbandingan ERP vs Aktual.
     * Tidak ada checkbox / input manual penentu status.
     *
     *   aktual kosong                 -> Belum diperiksa
     *   aktual terisi & selisih = 0   -> Cocok
     *   aktual terisi & selisih != 0  -> Selisih
     *
     * Mengikuti server: RekonDailyCalculator::statusKomponen() memakai NULL
     * sebagai penanda "belum diisi", jadi angka 0 dihitung sah.
     */
    function refreshStatus(group) {
        var input = inputs[group];
        if (!input) { return; }
        var statusEl = document.getElementById(input.dataset.status);
        var selisihEl = document.getElementById(input.dataset.selishtext);
        if (!statusEl) { return; }

        var erp = parseInt(input.dataset.erp || '0', 10);
        var digits = readDigits(input.value);
        var terisi = digits !== '';
        var actual = terisi ? parseInt(digits, 10) : null;
        var valid = terisi && !isNaN(actual) && !input.classList.contains('is-invalid');
        var selisih = valid ? actual - erp : null;

        if (selisihEl) {
            selisihEl.textContent = selisih === null ? '\u2014' : formatRupiah(selisih);
        }

        if (!valid) {
            statusEl.innerHTML = '<span class="badge bg-secondary">Belum diperiksa</span>';
        } else if (selisih === 0) {
            statusEl.innerHTML = '<span class="badge bg-success">Cocok</span>';
        } else {
            statusEl.innerHTML = '<span class="badge bg-info">Selisih</span>';
        }
    }

    document.querySelectorAll('.rupiah-rekon').forEach(function (input) {
        var group = input.dataset.group;

        // --- ketik: hanya digit diterima, titik ribuan dipasang otomatis ---
        input.addEventListener('input', function () {
            var raw = this.value;

            // Minus, huruf, atau karakter lain: tolak, kembalikan nilai terakhir.
            if (/[^0-9.,\s]/.test(raw)) {
                setInvalid(this, 'Nominal harus angka bulat, tidak boleh negatif.');
                this.value = this.dataset.lastValid || '';
                refreshStatus(group);
                return;
            }

            clearInvalid(this);

            var digits = readDigits(raw);
            this.value = digits === '' ? '' : groupThousands(digits);
            this.dataset.lastValid = this.value;

            var end = this.value.length;
            try { this.setSelectionRange(end, end); } catch (err) { /* ignore */ }

            refreshStatus(group);
        });

        // --- paste: parse ketat, tolak negatif / desimal ---
        input.addEventListener('paste', function (e) {
            var text = '';
            if (e.clipboardData) {
                text = e.clipboardData.getData('text');
            } else if (window.clipboardData) {
                text = window.clipboardData.getData('Text');
            }
            if (text === '') { return; }

            var parsed = parseNominal(text);
            if (isNaN(parsed)) {
                e.preventDefault();
                setInvalid(this, 'Nilai yang ditempel harus angka bulat (bukan negatif / desimal).');
                refreshStatus(group);
                return;
            }

            e.preventDefault();
            clearInvalid(this);
            var digits = String(parsed);
            this.value = digits === '0' ? '' : groupThousands(digits);
            this.dataset.lastValid = this.value;
            var end = this.value.length;
            try { this.setSelectionRange(end, end); } catch (err) { /* ignore */ }
            refreshStatus(group);
        });

        // --- blur: normalisasi akhir ---
        input.addEventListener('blur', function () {
            if (this.classList.contains('is-invalid')) { return; }
            var digits = readDigits(this.value);
            this.value = digits === '' ? '' : groupThousands(digits);
            this.dataset.lastValid = this.value;
            refreshStatus(group);
        });
    });

    // Normalisasi nilai awal dari server ("1.000.000" tetap utuh, "0" jadi kosong).
    document.querySelectorAll('.rupiah-rekon').forEach(function (input) {
        var parsed = parseNominal(input.value);
        if (!isNaN(parsed)) {
            var digits = String(parsed);
            input.value = digits === '0' ? '' : groupThousands(digits);
        }
        input.dataset.lastValid = input.value;
    });

    // Samakan badge & selisih dengan nilai server saat halaman dibuka.
    Object.keys(inputs).forEach(refreshStatus);
});
</script>
