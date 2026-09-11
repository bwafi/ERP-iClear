<!-- Page Header -->
<div class="card bg-light-info shadow-none position-relative overflow-hidden mb-4 border-0">
    <div class="card-body px-4 py-3">
        <div class="row align-items-center">
            <div class="col-9">
                <h4 class="fw-semibold mb-2">Penilaian Absensi Karyawan</h4>
                <p class="text-muted mb-0 fs-3">
                    Input skor harian (0 = OFF, 1-5) per komponen. Rumus Nilai = $\frac{\text{SUM}}{\text{Hari Efektif} \times 5} \times 100$
                </p>
            </div>
            <div class="col-3 text-end">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 justify-content-end bg-transparent p-0">
                        <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a></li>
                        <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('/penilaian/kpi') ?>">Penilaian</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Absensi</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Flash Messages -->
<?php if (session()->getFlashdata('success')) : ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <iconify-icon icon="solar:check-circle-bold" class="fs-5 me-2"></iconify-icon>
            <div><?= session()->getFlashdata('success') ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <iconify-icon icon="solar:danger-triangle-bold" class="fs-5 me-2"></iconify-icon>
            <div><?= session()->getFlashdata('error') ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter & Selector Section -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold fs-3">Bulan</label>
                <select name="bulan" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php for ($i = 1; $i <= 12; $i++) : ?>
                        <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold fs-3">Tahun</label>
                <select name="tahun" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++) : ?>
                        <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold fs-3">Pilih Karyawan</label>
                <select name="karyawan" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($list_karyawan as $k) : ?>
                        <option value="<?= $k['ID_AKUN'] ?>" <?= $selected_karyawan == $k['ID_AKUN'] ? 'selected' : '' ?>>
                            <?= esc($k['NAMA_AKUN']) ?> — <?= esc($k['NAMA_JABATAN'] ?? 'Tanpa Jabatan') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <a href="<?= base_url('/penilaian/absen') ?>" class="btn btn-outline-secondary btn-sm">
                    <iconify-icon icon="solar:restart-bold" class="me-1"></iconify-icon> Reset Filter
                </a>
            </div>
        </form>
    </div>
</div>

<?php if ($target) : ?>
    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted d-block fs-2 text-uppercase fw-semibold">Pegawai Dinilai</span>
                        <h4 class="fw-bold mb-1 mt-1"><?= esc($target->NAMA_AKUN) ?></h4>
                        <span class="badge bg-primary-subtle text-primary px-2 py-1">
                            <?= esc($targetUnitName) ?>
                        </span>
                    </div>
                    <div class="text-end bg-light-success rounded-3 p-3 px-4">
                        <span class="text-success d-block fs-2 fw-semibold text-uppercase">Skor Absensi</span>
                        <h2 class="fw-bolder text-success mb-0"><?= number_format($skor_total2, 2, ',', '.') ?></h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="text-muted d-block fs-2 text-uppercase fw-semibold mb-2">Rincian Komponen Bulanan</span>
                    <div class="row text-center g-2">
                        <?php if (!empty($detail_absen)) : ?>
                            <?php foreach ($detail_absen as $a) : ?>
                                <div class="col bg-light rounded p-2 mx-1 border">
                                    <small class="text-muted d-block fs-1 text-truncate"><?= esc($a['nama']) ?></small>
                                    <strong class="fs-4 text-dark"><?= $a['nilai'] ?></strong>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <?php foreach ($attendanceComponents as $c) : ?>
                                <div class="col bg-light rounded p-2 mx-1 border">
                                    <small class="text-muted d-block fs-1 text-truncate"><?= esc($c->name) ?></small>
                                    <strong class="fs-4 text-muted">-</strong>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php $allowedCount = is_array($allowedComponentCodes ?? null) ? count($allowedComponentCodes) : 0; ?>
    <?php if ($target && $allowedCount > 0) : ?>
        <!-- Form Input Harian -->
        <form method="post" action="<?= base_url('/penilaian/absen/save') ?>" class="card shadow-sm border-0 mb-4">
            <input type="hidden" name="employee_id" value="<?= $target->ID_AKUN ?>">
            <input type="hidden" name="bulan" value="<?= $bulan ?>">
            <input type="hidden" name="tahun" value="<?= $tahun ?>">

            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center">
                    <div class="bg-primary-subtle text-primary rounded p-2 me-2 d-flex align-items-center justify-content-center">
                        <iconify-icon icon="solar:calendar-add-bold" class="fs-5"></iconify-icon>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-semibold">Input Absensi Harian</h5>
                        <small class="text-muted">Form pencatatan aspek kehadiran dan komponen harian</small>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm px-4">
                    <iconify-icon icon="solar:diskette-bold" class="me-1 fs-4 align-text-bottom"></iconify-icon>Simpan Data
                </button>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Pilih Tanggal</label>
                        <?php
                        $defaultTanggal = (date('Y') == $tahun && (int)date('m') == $bulan)
                            ? date('Y-m-d')
                            : sprintf('%04d-%02d-%02d', $tahun, $bulan, 1);
                        ?>
                        <input type="date" class="form-control" name="tanggal"
                            value="<?= $defaultTanggal ?>" required onchange="resetSkorInput()">
                        <small class="text-muted fs-1 mt-1 d-block">Tanggal yang akan diinput/diubah skornya.</small>
                    </div>
                    <div class="col-md-9">
                        <?php if (in_array('KEHADIRAN', (array)$allowedComponentCodes, true)) : ?>
                            <!-- KEHADIRAN: input jam masuk auto-scoring -->
                            <div class="bg-light border rounded-3 p-3 mb-3">
                                <div class="mb-2">
                                    <span class="fw-semibold text-dark"><i class="bi bi-clock-history text-primary me-1"></i>Kehadiran (Auto-Scoring Sistem)</span>
                                </div>

                                <div class="row g-2 align-items-end" id="atsInputsRow">
                                    <div class="col-md-2" id="atsStatusWrap">
                                        <label class="form-label small text-muted mb-1">Status</label>
                                        <select class="form-select form-select-sm" name="kehadiran_status" id="atsStatus">
                                            <option value="HADIR">Hadir</option>
                                            <option value="OFF">OFF (Libur)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2" id="atsShiftWrap">
                                        <label class="form-label small text-muted mb-1">Shift</label>
                                        <select class="form-select form-select-sm" name="shift" id="atsShift">
                                            <option value="">- Pilih Shift -</option>
                                            <option value="PAGI">Pagi (08:45)</option>
                                            <option value="SIANG">Siang (12:45)</option>
                                            <option value="PS">PS (Pagi + Sore)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2" id="atsTypeWrap">
                                        <label class="form-label small text-muted mb-1">Jenis Absensi</label>
                                        <select class="form-select form-select-sm" name="attendance_type" id="atsType">
                                            <option value="NORMAL">Normal</option>
                                            <option value="IZIN_TELAT">Izin Telat</option>
                                        </select>
                                    </div>
                                    <div id="atsJamWrap" class="col-md-3 d-none">
                                        <label class="form-label small text-muted mb-1" id="atsJamLabel">Jam Masuk</label>
                                        <input type="time" name="jam_masuk" id="atsJam" class="form-control form-control-sm">
                                    </div>
                                    <div id="atsJamPagiWrap" class="col-md-3 d-none">
                                        <label class="form-label small text-muted mb-1">Jam Pagi (08:45)</label>
                                        <input type="time" name="jam_masuk_pagi" id="atsJamPagi" class="form-control form-control-sm">
                                    </div>
                                    <div id="atsJamSoreWrap" class="col-md-3 d-none">
                                        <label class="form-label small text-muted mb-1">Jam Sore (17:00)</label>
                                        <input type="time" name="jam_masuk_sore" id="atsJamSore" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md" id="atsPreviewBlockCol">
                                        <div id="atsPreviewBlock">
                                            <div id="atsPreview"></div>
                                            <div id="atsOffNote" class="d-none"><span class="badge bg-secondary">OFF — hari ini tidak dihitung</span></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-text mt-2 text-muted fs-1" id="atsHint">
                                    Aturan: Normal (≤0m=5, 1-3m=4, 4-6m=3, 7-10m=2, 11-14m=1, ≥15m=0) · Izin Telat memiliki toleransi lebih longgar.
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="row g-3">
                            <?php foreach ($attendanceComponents as $c) : ?>
                                <?php if ($c->code === 'KEHADIRAN') continue; ?>
                                <?php if (!in_array($c->code, $allowedComponentCodes, true)) continue; ?>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold fs-3"><?= esc($c->name) ?></label>
                                    <select class="form-select form-select-sm" name="skor_<?= strtolower($c->code) ?>">
                                        <option value="">- Tidak Diubah -</option>
                                        <option value="0">0 (OFF / Libur)</option>
                                        <?php foreach ([5 => 'Sangat Baik (5)', 4 => 'Baik (4)', 3 => 'Cukup (3)', 2 => 'Kurang (2)', 1 => 'Sangat Kurang (1)'] as $v => $label) : ?>
                                            <option value="<?= $v ?>"><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-light py-2 text-muted fs-2">
                <iconify-icon icon="solar:info-circle-bold" class="me-1 align-text-bottom text-info"></iconify-icon>
                Aspek yang dikosongkan (-) nilainya tidak akan diubah pada tanggal tersebut. Masukkan skor 0 untuk mencatat status OFF/Libur.
            </div>
        </form>

        <!-- Riwayat Nilai Bulanan Matrix -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-semibold d-flex align-items-center">
                    <iconify-icon icon="solar:calendar-mark-bold" class="text-primary fs-5 me-2"></iconify-icon>
                    Riwayat Nilai & Matriks Bulanan
                </h5>
                <?php if ($target && !empty($attendanceDetails)) : ?>
                    <?php
                    $totalLateMonth = 0;
                    foreach ($attendanceDetails as $dayDetails) {
                        foreach ($dayDetails as $det) {
                            $totalLateMonth += (int)($det->late_minutes ?? 0);
                        }
                    }
                    ?>
                    <span class="badge bg-danger-subtle text-danger px-3 py-2">
                        <iconify-icon icon="solar:clock-circle-bold" class="me-1"></iconify-icon> Total Akumulasi Telat: <strong><?= $totalLateMonth ?> menit</strong>
                    </span>
                <?php endif; ?>
            </div>

            <div class="card-body">
                <style>
                    .riwayat-absen {
                        table-layout: fixed;
                        width: 100%;
                    }

                    .riwayat-absen thead th,
                    .riwayat-absen tbody td {
                        vertical-align: middle;
                    }

                    .riwayat-absen th.riwayat-komponen,
                    .riwayat-absen td.riwayat-komponen {
                        width: 220px;
                    }

                    .riwayat-absen th.riwayat-date,
                    .riwayat-absen td.riwayat-date {
                        width: 38px;
                        text-align: center;
                        overflow: hidden;
                        padding-left: 0;
                        padding-right: 0;
                    }

                    .skor-cell {
                        border-radius: .25rem;
                        font-weight: 600;
                        font-size: .72rem;
                        line-height: 1.5;
                        padding: .1rem .15rem;
                        display: inline-block;
                        width: 2rem;
                        text-align: center;
                        box-sizing: border-box;
                    }

                    .skor-cell.skor-off {
                        background: var(--bs-secondary-bg-subtle);
                        color: var(--bs-secondary-color);
                    }

                    .skor-cell.skor-low {
                        background: var(--bs-danger-bg-subtle);
                        color: var(--bs-danger);
                    }

                    .skor-cell.skor-mid {
                        background: var(--bs-warning-bg-subtle);
                        color: var(--bs-warning-text-emphasis);
                    }

                    .skor-cell.skor-good {
                        background: var(--bs-success-bg-subtle);
                        color: var(--bs-success);
                    }

                    .skor-cell.skor-empty {
                        color: var(--bs-secondary-color);
                    }
                </style>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle text-center riwayat-absen">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start riwayat-komponen ps-3">Komponen Penilaian</th>
                                <?php $jumlahHari = (int)date('t', strtotime("$tahun-$bulan-01")); ?>
                                <?php for ($d = 1; $d <= $jumlahHari; $d++) : ?>
                                    <th class="riwayat-date fs-2"><?= $d ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendanceComponents as $c) : ?>
                                <tr>
                                    <td class="text-start fw-semibold riwayat-komponen ps-3"><?= esc($c->name) ?></td>
                                    <?php for ($d = 1; $d <= $jumlahHari; $d++) : ?>
                                        <td class="riwayat-date">
                                            <?php if (isset($existing[$c->id][$d])) : ?>
                                                <?php
                                                $skorValue = (int)$existing[$c->id][$d];
                                                $tooltipContent = '';

                                                if ($c->code === 'KEHADIRAN' && isset($attendanceDetails[$d])) {
                                                    $details = $attendanceDetails[$d];
                                                    $tooltipParts = [];
                                                    foreach ($details as $det) {
                                                        $parts = [];
                                                        if ($det->shift) {
                                                            $parts[] = 'Shift: ' . $det->shift;
                                                            if ($det->shift === 'PS' && $det->session) {
                                                                $parts[] = '(' . $det->session . ')';
                                                            }
                                                        }
                                                        if ($det->actual_time) {
                                                            $parts[] = 'Jam: ' . date('H:i', strtotime($det->actual_time));
                                                        }
                                                        if ($det->late_minutes !== null) {
                                                            $parts[] = 'Telat: ' . (int)$det->late_minutes . ' menit';
                                                        }
                                                        if ($det->attendance_type === 'IZIN_TELAT') {
                                                            $parts[] = '(Izin)';
                                                        }
                                                        if (!empty($parts)) {
                                                            $tooltipParts[] = implode(', ', $parts);
                                                        }
                                                    }
                                                    if (!empty($tooltipParts)) {
                                                        $tooltipContent = implode(' | ', $tooltipParts);
                                                    }
                                                }
                                                ?>
                                                <?php if ($skorValue === 0) : ?>
                                                    <span class="skor-cell skor-off" <?= $tooltipContent ? 'data-bs-toggle="tooltip" data-bs-placement="top" title="' . esc($tooltipContent) . '"' : '' ?>>OFF</span>
                                                <?php elseif ($skorValue <= 2) : ?>
                                                    <span class="skor-cell skor-low" <?= $tooltipContent ? 'data-bs-toggle="tooltip" data-bs-placement="top" title="' . esc($tooltipContent) . '"' : '' ?>><?= $skorValue ?></span>
                                                <?php elseif ($skorValue === 3) : ?>
                                                    <span class="skor-cell skor-mid" <?= $tooltipContent ? 'data-bs-toggle="tooltip" data-bs-placement="top" title="' . esc($tooltipContent) . '"' : '' ?>><?= $skorValue ?></span>
                                                <?php else : ?>
                                                    <span class="skor-cell skor-good" <?= $tooltipContent ? 'data-bs-toggle="tooltip" data-bs-placement="top" title="' . esc($tooltipContent) . '"' : '' ?>><?= $skorValue ?></span>
                                                <?php endif; ?>
                                            <?php else : ?>
                                                <span class="text-muted opacity-25">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Legend & Keterangan -->
                <div class="d-flex flex-wrap align-items-center justify-content-between mt-3 pt-2 border-top">
                    <div class="d-flex flex-wrap align-items-center gap-3 text-muted fs-2">
                        <span class="fw-semibold text-dark">Legenda:</span>
                        <span><span class="skor-cell skor-off">OFF</span> Hari Libur/OFF</span>
                        <span><span class="skor-cell skor-low">1–2</span> Kurang</span>
                        <span><span class="skor-cell skor-mid">3</span> Cukup</span>
                        <span><span class="skor-cell skor-good">4–5</span> Baik / Sangat Baik</span>
                    </div>
                    <div class="text-muted fs-2">
                        Arahkan kursor ke angka matriks tanggal untuk melihat detail jam masuk.
                    </div>
                </div>
            </div>
        </div>
    <?php else : ?>
        <div class="alert alert-light border shadow-sm text-center py-4">
            <iconify-icon icon="solar:shield-keyhole-bold-duotone" class="fs-1 text-warning mb-2"></iconify-icon>
            <h6 class="fw-semibold">Akses Terbatas</h6>
            <p class="text-muted mb-0">Anda tidak berwenang menginput absensi pegawai ini (Mode baca saja).</p>
        </div>
    <?php endif; ?>
<?php else : ?>
    <div class="card border-0 shadow-sm text-center py-5">
        <div class="card-body">
            <iconify-icon icon="solar:user-search-bold-duotone" class="fs-1 text-muted mb-3"></iconify-icon>
            <h5 class="fw-semibold">Belum Ada Karyawan Dipilih</h5>
            <p class="text-muted mb-0">Silakan pilih salah satu karyawan melalui filter di atas untuk melihat atau menginput data absensi.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Modal Konfirmasi Timpa Data -->
<?php if (session()->getFlashdata('require_confirmation')) : ?>
    <?php $confirmData = session()->getFlashdata('require_confirmation'); ?>
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-warning-subtle text-warning-emphasis">
                    <h5 class="modal-title fw-semibold" id="confirmModalLabel">
                        <iconify-icon icon="solar:danger-triangle-bold" class="fs-5 me-1 align-text-bottom"></iconify-icon>
                        Konfirmasi Timpa Data Absensi
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3 text-dark">
                        Tanggal <strong><?= date('d F Y', strtotime($confirmData['tanggal'])) ?></strong> sudah memiliki data penilaian sebelumnya. Apakah Anda ingin menimpanya dengan data baru?
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0 align-middle text-center">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-start ps-2">Komponen</th>
                                    <th>Nilai Lama</th>
                                    <th>Nilai Baru</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($confirmData['comparisons'] as $comp) : ?>
                                    <tr>
                                        <td class="text-start ps-2"><?= esc($comp['name']) ?></td>
                                        <td><span class="badge bg-secondary"><?= $comp['old'] ?></span></td>
                                        <td><span class="badge bg-primary"><?= $comp['new'] ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-warning mb-0 mt-3 fs-2 border-0">
                        <iconify-icon icon="solar:info-circle-bold" class="me-1"></iconify-icon> Data yang lama akan ditimpa secara permanen.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <form method="post" action="<?= base_url('/penilaian/absen/save') ?>" class="d-inline">
                        <input type="hidden" name="confirm" value="1">
                        <?php foreach ($confirmData['post_data'] as $key => $value) : ?>
                            <?php if (is_scalar($value)) : ?>
                                <input type="hidden" name="<?= esc($key) ?>" value="<?= esc($value) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-warning btn-sm px-3">
                            <iconify-icon icon="solar:diskette-bold" class="me-1"></iconify-icon>Ya, Timpa Data
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            confirmModal.show();
        });
    </script>
<?php endif; ?>

<!-- Script Utama (Auto-scoring & Tooltips) -->
<script>
    function resetSkorInput() {
        var selects = document.querySelectorAll('select[name^="skor_"]');
        selects.forEach(function(el) {
            el.value = '';
        });
        if (typeof window.atsResetAbsensi === 'function') {
            window.atsResetAbsensi();
        }
    }

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

        const normalScoring = [{
                max: 0,
                score: 5
            },
            {
                max: 3,
                score: 4
            },
            {
                max: 6,
                score: 3
            },
            {
                max: 10,
                score: 2
            },
            {
                max: 14,
                score: 1
            },
            {
                max: 999,
                score: 0
            }
        ];

        const izinScoring = [{
                max: 5,
                score: 5
            },
            {
                max: 15,
                score: 4
            },
            {
                max: 30,
                score: 3
            },
            {
                max: 40,
                score: 1
            },
            {
                max: 999,
                score: 0
            }
        ];

        const shiftEl = document.getElementById('atsShift');
        const typeEl = document.getElementById('atsType');
        const statusEl = document.getElementById('atsStatus');
        const shiftWrapEl = document.getElementById('atsShiftWrap');
        const typeWrapEl = document.getElementById('atsTypeWrap');
        const previewBlockColEl = document.getElementById('atsPreviewBlockCol');
        const offNoteEl = document.getElementById('atsOffNote');
        const jamWrap = document.getElementById('atsJamWrap');
        const jamLabel = document.getElementById('atsJamLabel');
        const jamEl = document.getElementById('atsJam');
        const jamPagiWrap = document.getElementById('atsJamPagiWrap');
        const jamPagiEl = document.getElementById('atsJamPagi');
        const jamSoreWrap = document.getElementById('atsJamSoreWrap');
        const jamSoreEl = document.getElementById('atsJamSore');
        const previewEl = document.getElementById('atsPreview');
        const hintEl = document.getElementById('atsHint');

        if (!shiftEl) return;

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

        function scoreBadge(score) {
            const cls = score >= 4 ? 'bg-success' : (score >= 3 ? 'bg-warning' : (score >= 1 ? 'bg-danger' : 'bg-secondary'));
            return '<span class="badge ' + cls + '">' + score + '</span>';
        }

        function renderStatusMode() {
            const off = statusEl && statusEl.value === 'OFF';

            if (shiftWrapEl) shiftWrapEl.classList.toggle('d-none', off);
            if (typeWrapEl) typeWrapEl.classList.toggle('d-none', off);
            jamWrap.classList.add('d-none');
            jamPagiWrap.classList.add('d-none');
            jamSoreWrap.classList.add('d-none');
            if (previewBlockColEl) previewBlockColEl.classList.toggle('d-none', off);
            if (offNoteEl) offNoteEl.classList.toggle('d-none', !off);

            if (off) {
                previewEl.innerHTML = '';
                hintEl.textContent = 'Status OFF: hari libur/istirahat — tidak dihitung dalam nilai absensi.';
            } else {
                renderShiftMode();
                updatePreview();
            }
        }

        function renderShiftMode() {
            const off = statusEl && statusEl.value === 'OFF';
            const shift = shiftEl.value;

            jamWrap.classList.add('d-none');
            jamPagiWrap.classList.add('d-none');
            jamSoreWrap.classList.add('d-none');

            if (off) {
                hintEl.textContent = 'Status OFF: hari libur/istirahat — tidak dihitung dalam nilai absensi.';
                return;
            }

            if (shift === 'PS') {
                jamPagiWrap.classList.remove('d-none');
                jamSoreWrap.classList.remove('d-none');
                hintEl.textContent = 'Shift PS: Masukkan jam masuk sesi pagi dan sesi sore.';
            } else if (shift === '') {
                hintEl.textContent = 'Pilih shift terlebih dahulu untuk mengaktifkan input jam masuk.';
            } else {
                jamWrap.classList.remove('d-none');
                jamLabel.textContent = shift === 'SIANG' ? 'Jam Masuk (12:45)' : 'Jam Masuk (08:45)';
                hintEl.textContent = 'Masukkan jam kedatangan untuk menghitung skor keterlambatan otomatis.';
            }
        }

        function updatePreview() {
            const shift = shiftEl.value;
            const isIzin = typeEl.value === 'IZIN_TELAT';

            if (!shift) {
                previewEl.innerHTML = '';
                return;
            }

            let sessions = [];
            if (shift === 'PS') {
                if (jamPagiEl.value) sessions.push({
                    sched: '08:45',
                    actual: jamPagiEl.value
                });
                if (jamSoreEl.value) sessions.push({
                    sched: '17:00',
                    actual: jamSoreEl.value
                });
            } else {
                const sched = scheduledTimes[shift];
                if (jamEl.value) sessions.push({
                    sched: sched,
                    actual: jamEl.value
                });
            }

            if (sessions.length === 0) {
                previewEl.innerHTML = '';
                return;
            }

            let totalLate = 0;
            let rows = [];

            sessions.forEach(function(s, i) {
                const late = minutesLate(s.sched, s.actual);
                const score = calculateScore(late, isIzin);
                totalLate += late;
                const label = shift === 'PS' ? (i === 0 ? 'Pagi' : 'Sore') : (shift === 'SIANG' ? 'Siang' : 'Pagi');
                rows.push(label + ': ' + late + 'm (' + score + ')');
            });

            const dailyScore = calculateScore(totalLate, isIzin);

            previewEl.innerHTML =
                '<div class="alert alert-info py-2 px-3 small mb-0 d-flex align-items-center justify-content-between">' +
                '<span>Detail: ' + rows.join(' | ') + ' &bull; <strong>Total Telat: ' + totalLate + 'm</strong></span>' +
                '<span class="ms-2">Skor: ' + scoreBadge(dailyScore) + '</span>' +
                '</div>';
        }

        window.atsResetAbsensi = function() {
            if (statusEl) statusEl.value = 'HADIR';
            if (shiftEl) shiftEl.value = '';
            if (jamEl) jamEl.value = '';
            if (jamPagiEl) jamPagiEl.value = '';
            if (jamSoreEl) jamSoreEl.value = '';
            renderStatusMode();
            renderShiftMode();
            updatePreview();
        };

        renderStatusMode();
        renderShiftMode();
        updatePreview();

        if (statusEl) {
            statusEl.addEventListener('change', function() {
                renderStatusMode();
            });
        }
        shiftEl.addEventListener('change', function() {
            renderShiftMode();
            updatePreview();
        });
        typeEl.addEventListener('change', updatePreview);
        jamEl.addEventListener('input', updatePreview);
        jamPagiEl.addEventListener('input', updatePreview);
        jamSoreEl.addEventListener('input', updatePreview);
    })();

    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                html: true,
                trigger: 'hover'
            });
        });
    });
</script>
