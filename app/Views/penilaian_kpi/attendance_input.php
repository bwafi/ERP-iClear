<?php
/**
 * UI Input Absensi Modern: Jam Masuk → Auto-Scoring
 * 
 * Form input:
 * - Tanggal, Karyawan, Shift, Sesi (PS), Jenis Absensi, Jam Masuk
 * - Live calculate: keterlambatan & nilai otomatis
 * 
 * Daftar absensi bulan ini (untuk karyawan terpilih).
 */
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">
                <iconify-icon icon="solar:calendar-mark-bold-duotone" class="text-primary me-2"></iconify-icon>
                Input Absensi KPI
            </h3>
            <small class="text-muted">Input jam masuk → sistem hitung nilai otomatis</small>
        </div>
        <a href="<?= base_url('penilaian/absen') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-grid-3x3 me-1"></i>Grid Manual (Lama)
        </a>
    </div>

    <?php if (session()->getFlashdata('success')) : ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= session()->getFlashdata('success') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= session()->getFlashdata('error') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <!-- Form Input -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Input Jam Masuk</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= base_url('penilaian-kpi/attendance-save') ?>" id="attendanceForm">
                        <div class="mb-3">
                            <label class="form-label">Karyawan <span class="text-danger">*</span></label>
                            <select name="target_id" id="targetSelect" class="form-select select2" required>
                                <option value="">-- Pilih Karyawan --</option>
                                <?php foreach ($employees as $emp) : ?>
                                    <option value="<?= (int)$emp->ID_AKUN ?>" 
                                        data-jabatan="<?= esc($emp->NAMA_JABATAN) ?>"
                                        data-unit="<?= esc($emp->NAMA_UNIT) ?>"
                                        <?= (int)$emp->ID_AKUN === $targetId ? 'selected' : '' ?>>
                                        <?= esc($emp->NAMA_AKUN) ?> - <?= esc($emp->NAMA_JABATAN) ?> (<?= esc($emp->NAMA_UNIT) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="date" id="dateInput" class="form-control" 
                                value="<?= esc($date) ?>" required max="<?= date('Y-m-d') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Shift <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="shift" id="shiftPagi" value="PAGI" required checked>
                                    <label class="form-check-label" for="shiftPagi">Pagi (08:45)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="shift" id="shiftSiang" value="SIANG" required>
                                    <label class="form-check-label" for="shiftSiang">Siang (12:45)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="shift" id="shiftPs" value="PS" required>
                                    <label class="form-check-label" for="shiftPs">PS (Pagi+Sore)</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3" id="sessionGroup" style="display:none;">
                            <label class="form-label">Sesi (PS) <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="session" id="sessionPagi" value="PAGI">
                                    <label class="form-check-label" for="sessionPagi">Pagi (08:45)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="session" id="sessionSore" value="SORE">
                                    <label class="form-check-label" for="sessionSore">Sore (17:00)</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Jenis Absensi <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="attendance_type" id="typeNormal" value="NORMAL" required checked>
                                    <label class="form-check-label" for="typeNormal">Normal</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="attendance_type" id="typeIzin" value="IZIN_TELAT" required>
                                    <label class="form-check-label" for="typeIzin">Izin Telat</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Jam Masuk Aktual <span class="text-danger">*</span></label>
                            <input type="time" name="actual_time" id="actualTime" class="form-control" required>
                        </div>

                        <div class="alert alert-light border mb-3" id="calculationResult" style="display:none;">
                            <div class="row g-2">
                                <div class="col-6">
                                    <small class="text-muted d-block">Jam Mulai Shift</small>
                                    <strong id="scheduledTime">--:--</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Keterlambatan</small>
                                    <strong id="lateMinutes" class="text-danger">0 menit</strong>
                                </div>
                                <div class="col-12 mt-2">
                                    <small class="text-muted d-block">Nilai Otomatis</small>
                                    <h3 class="mb-0" id="autoScore">
                                        <span class="badge bg-success">5</span>
                                    </h3>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                <i class="bi bi-save me-2"></i>Simpan Absensi
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Info Box -->
            <div class="card shadow-sm border-0 mt-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-2"><i class="bi bi-info-circle text-info me-2"></i>Aturan Penilaian</h6>
                    <small class="text-muted">
                        <strong>Normal:</strong> ≤0 (5) | 1-3 (4) | 4-6 (3) | 7-10 (2) | 11-14 (1) | ≥15 (0)<br>
                        <strong>Izin Telat:</strong> 1-5 (5) | 6-15 (4) | 16-30 (3) | 31-40 (1) | &gt;40 (0)<br>
                        <strong>PS:</strong> Input 2x (pagi + sore), total keterlambatan dihitung gabungan.
                    </small>
                </div>
            </div>
        </div>

        <!-- List Absensi Bulan Ini -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Absensi Bulan Ini</h5>
                    <?php if ($target) : ?>
                        <span class="badge bg-primary"><?= esc($target->NAMA_AKUN) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($monthlyList)) : ?>
                        <div class="text-center py-5 text-muted">
                            <iconify-icon icon="solar:inbox-outline" width="48" height="48" class="mb-2"></iconify-icon>
                            <p class="mb-0">Belum ada data absensi bulan ini.</p>
                            <small>Pilih karyawan dan mulai input absensi.</small>
                        </div>
                    <?php else : ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Shift</th>
                                        <th>Jam Masuk</th>
                                        <th class="text-center">Telat</th>
                                        <th class="text-center">Nilai</th>
                                        <th class="text-center">Jenis</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthlyList as $item) : ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($item->evaluation_date)) ?></td>
                                            <td>
                                                <?php if ($item->shift) : ?>
                                                    <span class="badge bg-secondary"><?= esc($item->shift) ?></span>
                                                    <?php if ($item->shift === 'PS' && $item->session) : ?>
                                                        <small class="text-muted">(<?= esc($item->session) ?>)</small>
                                                    <?php endif; ?>
                                                <?php else : ?>
                                                    <span class="text-muted">Manual</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($item->actual_time) : ?>
                                                    <?= date('H:i', strtotime($item->actual_time)) ?>
                                                <?php else : ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($item->late_minutes !== null) : ?>
                                                    <span class="<?= (int)$item->late_minutes > 0 ? 'text-danger' : 'text-success' ?>">
                                                        <?= (int)$item->late_minutes ?> menit
                                                    </span>
                                                <?php else : ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php 
                                                $score = $item->auto_score ?? $item->raw_score;
                                                $badgeClass = $score >= 4 ? 'bg-success' : ($score >= 3 ? 'bg-warning' : ($score >= 1 ? 'bg-danger' : 'bg-secondary'));
                                                ?>
                                                <span class="badge <?= $badgeClass ?>"><?= number_format((float)$score, 1) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($item->attendance_type) : ?>
                                                    <small class="text-muted"><?= $item->attendance_type === 'IZIN_TELAT' ? 'Izin' : 'Normal' ?></small>
                                                <?php else : ?>
                                                    <small class="text-muted">Manual</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    const scheduledTimes = {
        'PAGI': '08:45',
        'SIANG': '12:45',
        'PS': {
            'PAGI': '08:45',
            'SORE': '17:00'
        }
    };

    const normalScoring = [
        {max: 0, score: 5},
        {max: 3, score: 4},
        {max: 6, score: 3},
        {max: 10, score: 2},
        {max: 14, score: 1},
        {max: 999, score: 0}
    ];

    const izinScoring = [
        {max: 5, score: 5},
        {max: 15, score: 4},
        {max: 30, score: 3},
        {max: 40, score: 1},
        {max: 999, score: 0}
    ];

    const form = document.getElementById('attendanceForm');
    const targetSelect = document.getElementById('targetSelect');
    const dateInput = document.getElementById('dateInput');
    const shiftInputs = document.querySelectorAll('input[name="shift"]');
    const sessionGroup = document.getElementById('sessionGroup');
    const sessionInputs = document.querySelectorAll('input[name="session"]');
    const typeInputs = document.querySelectorAll('input[name="attendance_type"]');
    const actualTimeInput = document.getElementById('actualTime');
    const calculationResult = document.getElementById('calculationResult');
    const scheduledTimeEl = document.getElementById('scheduledTime');
    const lateMinutesEl = document.getElementById('lateMinutes');
    const autoScoreEl = document.getElementById('autoScore');
    const submitBtn = document.getElementById('submitBtn');

    function getScheduledTime() {
        const shift = document.querySelector('input[name="shift"]:checked')?.value;
        if (!shift) return null;

        if (shift === 'PS') {
            const session = document.querySelector('input[name="session"]:checked')?.value;
            return session ? scheduledTimes.PS[session] : null;
        }

        return scheduledTimes[shift];
    }

    function minutesLate(scheduled, actual) {
        if (!scheduled || !actual) return 0;
        const schedTime = new Date('1970-01-01T' + scheduled);
        const actualTime = new Date('1970-01-01T' + actual);
        const diff = Math.ceil((actualTime - schedTime) / 60000);
        return Math.max(0, diff);
    }

    function calculateScore(minutes, isIzin) {
        const rules = isIzin ? izinScoring : normalScoring;
        for (let rule of rules) {
            if (minutes <= rule.max) {
                return rule.score;
            }
        }
        return 0;
    }

    function updateCalculation() {
        const scheduled = getScheduledTime();
        const actual = actualTimeInput.value;
        const isIzin = document.getElementById('typeIzin').checked;

        if (!scheduled || !actual) {
            calculationResult.style.display = 'none';
            submitBtn.disabled = true;
            return;
        }

        const late = minutesLate(scheduled, actual);
        const score = calculateScore(late, isIzin);

        scheduledTimeEl.textContent = scheduled;
        lateMinutesEl.textContent = late + ' menit';
        lateMinutesEl.className = late > 0 ? 'text-danger' : 'text-success';

        const badgeClass = score >= 4 ? 'bg-success' : (score >= 3 ? 'bg-warning' : (score >= 1 ? 'bg-danger' : 'bg-secondary'));
        autoScoreEl.innerHTML = '<span class="badge ' + badgeClass + '">' + score + '</span>';

        calculationResult.style.display = 'block';
        submitBtn.disabled = false;
    }

    shiftInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.value === 'PS') {
                sessionGroup.style.display = 'block';
                sessionInputs[0].required = true;
                if (!document.querySelector('input[name="session"]:checked')) {
                    sessionInputs[0].checked = true;
                }
            } else {
                sessionGroup.style.display = 'none';
                sessionInputs.forEach(s => {
                    s.required = false;
                    s.checked = false;
                });
            }
            updateCalculation();
        });
    });

    sessionInputs.forEach(input => {
        input.addEventListener('change', updateCalculation);
    });

    typeInputs.forEach(input => {
        input.addEventListener('change', updateCalculation);
    });

    actualTimeInput.addEventListener('input', updateCalculation);

    targetSelect.addEventListener('change', function() {
        if (this.value) {
            window.location.href = '<?= base_url('penilaian-kpi/attendance-input') ?>?target_id=' + this.value + '&date=' + dateInput.value;
        }
    });

    dateInput.addEventListener('change', function() {
        if (targetSelect.value) {
            window.location.href = '<?= base_url('penilaian-kpi/attendance-input') ?>?target_id=' + targetSelect.value + '&date=' + this.value;
        }
    });

    // Init select2
    if (typeof $.fn.select2 !== 'undefined') {
        $('#targetSelect').select2({
            placeholder: '-- Pilih Karyawan --',
            allowClear: true,
            width: '100%'
        });
    }
})();
</script>