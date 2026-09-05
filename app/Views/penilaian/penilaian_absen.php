<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
            <h4 class="fw-semibold mb-0">Penilaian Absensi</h4>
            <small class="text-muted">Input skor harian (1-5) per komponen absensi. Skor = SUM / (26 x 5) x 100.</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                </li>
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/penilaian/kpi') ?>">Penilaian</a>
                </li>
                <li class="breadcrumb-item active">Penilaian Absensi</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session()->getFlashdata('success')) : ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= session()->getFlashdata('success') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= session()->getFlashdata('error') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Bulan</label>
                <select name="bulan" class="form-select" onchange="this.form.submit()">
                    <?php for ($i = 1; $i <= 12; $i++) : ?>
                        <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tahun</label>
                <select name="tahun" class="form-select" onchange="this.form.submit()">
                    <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++) : ?>
                        <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Karyawan</label>
                <select name="karyawan" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($list_karyawan as $k) : ?>
                        <option value="<?= $k['ID_AKUN'] ?>" <?= $selected_karyawan == $k['ID_AKUN'] ? 'selected' : '' ?>>
                            <?= esc($k['NAMA_AKUN']) ?> — <?= esc($k['NAMA_JABATAN'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($target) : ?>
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-semibold mb-0"><?= esc($target->NAMA_AKUN) ?></h5>
                        <span class="badge bg-primary-subtle text-primary mt-1">
                            <?= esc($targetUnitName) ?>
                        </span>
                    </div>
                    <div class="text-end">
                        <h6 class="text-muted mb-0">Skor Absensi</h6>
                        <h2 class="fw-bold text-success mb-0"><?= number_format($skor_total2, 2, ',', '.') ?></h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="mb-2">Rincian Komponen</h6>
                    <div class="row text-center">
                        <?php foreach ($detail_absen as $a) : ?>
                            <div class="col-3">
                                <small class="text-muted d-block"><?= esc($a['nama']) ?></small>
                                <strong><?= $a['nilai'] ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php $allowedCount = is_array($allowedComponentCodes ?? null) ? count($allowedComponentCodes) : 0; ?>
    <?php if ($target && $allowedCount > 0) : ?>
        <form method="post" action="<?= base_url('/penilaian/absen/save') ?>" class="card shadow-sm border-0 mb-3">
            <input type="hidden" name="employee_id" value="<?= $target->ID_AKUN ?>">
            <input type="hidden" name="bulan" value="<?= $bulan ?>">
            <input type="hidden" name="tahun" value="<?= $tahun ?>">

            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Input Absensi Harian — <?= $allowedCount ?> Aspek</h5>
                <button type="submit" class="btn btn-primary btn-sm">
                    <iconify-icon icon="solar:diskette-bold" class="me-1"></iconify-icon>Simpan Semua
                </button>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-control" name="tanggal"
                            value="<?= sprintf('%04d-%02d-%02d', $tahun, $bulan, 1) ?>" required>
                    </div>
                    <div class="col-md-9">
                        <div class="row g-3">
                            <?php foreach ($attendanceComponents as $c) : ?>
                                <?php if (!in_array($c->code, $allowedComponentCodes, true)) continue; ?>
                                <div class="col-md-3">
                                    <label class="form-label"><?= esc($c->name) ?></label>
                                    <select class="form-select" name="skor_<?= strtolower($c->code) ?>">
                                        <option value="">-</option>
                                        <?php foreach ([5 => 'SB', 4 => 'Baik', 3 => 'Cukup', 2 => 'Kurang', 1 => 'Sangat Kurang'] as $v => $label) : ?>
                                            <option value="<?= $v ?>"
                                                <?= isset($existing[$c->id][(int)date('j', strtotime(sprintf('%04d-%02d-%02d', $tahun, $bulan, 1)))]) && $existing[$c->id][(int)date('j', strtotime(sprintf('%04d-%02d-%02d', $tahun, $bulan, 1)))] == $v ? 'selected' : '' ?>>
                                                <?= $v ?> (<?= $label ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="text-muted small mt-2">
                    Pilih tanggal, isi skor 1-5 untuk aspek yang ditampilkan, lalu klik <strong>Simpan Semua</strong>. Aspek yang dikosongkan tidak diubah. Skor bulanan otomatis = SUM / (26 x 5) x 100.
                </div>
            </div>
        </form>

        <div class="card shadow-sm border-0">
            <div class="card-header">
                <h5 class="mb-0">Riwayat Nilai Bulanan</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle text-center">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start">Komponen</th>
                                <?php $jumlahHari = (int)date('t', strtotime("$tahun-$bulan-01")); ?>
                                <?php for ($d = 1; $d <= $jumlahHari; $d++) : ?>
                                    <th style="min-width:15px;"><?= $d ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendanceComponents as $c) : ?>
                                <tr>
                                    <td class="text-start fw-semibold"><?= esc($c->name) ?></td>
                                    <?php for ($d = 1; $d <= $jumlahHari; $d++) : ?>
                                        <td>
                                            <?php if (isset($existing[$c->id][$d])) : ?>
                                                <span class="badge bg-primary-subtle text-primary"><?= $existing[$c->id][$d] ?></span>
                                            <?php else : ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-muted small mt-2">
                    Angka = skor harian (1-5) yang sudah tersimpan. Kosong (-) = belum dinilai.
                </div>
            </div>
        </div>
    <?php else : ?>
        <div class="alert alert-light border mt-3">
            Anda tidak berwenang menginput absensi pegawai ini. (Mode baca saja.)
        </div>
    <?php endif; ?>
<?php else : ?>
    <div class="alert alert-warning mt-3">Pilih karyawan untuk melihat / menginput absensi.</div>
<?php endif; ?>

<?php if (session()->getFlashdata('require_confirmation')) : ?>
    <?php $confirmData = session()->getFlashdata('require_confirmation'); ?>
    <!-- Modal Konfirmasi Timpa Data -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning-subtle">
                    <h5 class="modal-title" id="confirmModalLabel">
                        <iconify-icon icon="solar:danger-triangle-bold" class="text-warning me-2"></iconify-icon>
                        Konfirmasi Timpa Data
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        <strong>Tanggal <?= date('d F Y', strtotime($confirmData['tanggal'])) ?></strong> sudah memiliki data penilaian.
                    </p>
                    <p class="mb-2">Perubahan yang akan dilakukan:</p>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Komponen</th>
                                <th class="text-center">Nilai Lama</th>
                                <th class="text-center">Nilai Baru</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($confirmData['comparisons'] as $comp) : ?>
                                <tr>
                                    <td><?= esc($comp['name']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?= $comp['old'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?= $comp['new'] ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="alert alert-warning mb-0 mt-3">
                        <small>Data lama akan <strong>ditimpa</strong> dan tidak dapat dikembalikan.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="post" action="<?= base_url('/penilaian/absen/save') ?>" style="display:inline;">
                        <input type="hidden" name="confirm" value="1">
                        <?php foreach ($confirmData['post_data'] as $key => $value) : ?>
                            <?php if (is_scalar($value)) : ?>
                                <input type="hidden" name="<?= esc($key) ?>" value="<?= esc($value) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-warning">
                            <iconify-icon icon="solar:diskette-bold" class="me-1"></iconify-icon>Ya, Timpa Data
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-show modal on page load
        document.addEventListener('DOMContentLoaded', function() {
            var confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            confirmModal.show();
        });
    </script>
<?php endif; ?>
