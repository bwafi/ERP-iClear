<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<!-- ====================================================== -->
<!-- HEADER & BREADCRUMB -->
<!-- ====================================================== -->
<div class="card bg-light-subtle shadow-none position-relative overflow-hidden mb-4 border-0">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
            <h4 class="fw-bold mb-1">Payroll & Penggajian</h4>
            <p class="text-muted mb-0 small">Kelola jadwal gaji, realisasi pembayaran, dan kas lembur/bonus karyawan.</p>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">
                        <i class="ti ti-home me-1"></i>Jurnal
                    </a>
                </li>
                <li class="breadcrumb-item active fw-semibold text-primary" aria-current="page">Payroll</li>
            </ol>
        </nav>
    </div>
</div>

<?php
$todayTs        = strtotime(date('Y-m-d'));
$payrollSummary = $payrollSummary ?? [];
$payrollItems   = $payrollItems ?? [];
$can_input      = $can_input ?? false;
$bulan          = $bulan ?? date('Y-m');
$show_all       = $show_all ?? ($bulan === '');
$unit           = $unit ?? [];
$akun           = $akun ?? [];
$bank           = $bank ?? [];
?>

<!-- ====================================================== -->
<!-- FILTER PERIODE -->
<!-- ====================================================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form class="row g-2 align-items-center" method="get" action="<?= base_url('payroll2') ?>">
            <div class="col-12 col-sm-auto d-flex align-items-center gap-2">
                <label class="form-label mb-0 fw-semibold text-nowrap"><i class="ti ti-calendar me-1"></i>Periode Bulan:</label>
                <input type="month" class="form-control form-control-sm" name="bulan" value="<?= esc($bulan) ?>" placeholder="Semua Bulan">
            </div>

            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm px-3">
                    <i class="ti ti-filter me-1"></i>Tampilkan
                </button>
            </div>

            <div class="col-auto">
                <button type="submit" name="bulan" value="" class="btn <?= $show_all ? 'btn-secondary' : 'btn-outline-secondary' ?> btn-sm px-3">
                    <i class="ti ti-list me-1"></i>Semua Bulan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ====================================================== -->
<!-- RINGKASAN KPI PER UNIT -->
<!-- ====================================================== -->
<div class="row mb-4">
    <?php if ($show_all): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center text-muted py-4">
                    <i class="ti ti-info-circle fs-6 d-block mb-1 text-primary"></i>
                    Menampilkan semua bulan. Pilih periode bulan pada filter di atas untuk melihat ringkasan KPI per unit.
                </div>
            </div>
        </div>
    <?php elseif (empty($payrollSummary)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center text-muted py-4">
                    <i class="ti ti-file-x fs-6 d-block mb-1 text-warning"></i>
                    Belum ada data ringkasan payroll untuk bulan <strong><?= esc($bulan) ?></strong>.
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($payrollSummary as $ps): ?>
            <?php
            $score = (float) ($ps['score'] ?? 0);
            $badgeCls = $score >= 80 ? 'bg-success' : ($score >= 60 ? 'bg-warning text-dark' : 'bg-danger');
            $statusLabel = ($ps['status'] ?? 'no_data') === 'no_data' ? 'Belum Dinilai' : 'Sudah Dinilai';
            $badgeStatus = ($ps['status'] ?? 'no_data') === 'no_data' ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary';
            ?>
            <div class="col-xl-4 col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="mb-0 fw-bold text-dark">
                                <i class="ti ti-building me-1 text-primary"></i><?= esc($ps['nama_unit'] ?? '-') ?>
                            </h6>
                            <span class="badge <?= $badgeStatus ?> px-2 py-1 rounded-pill">
                                <?= $statusLabel ?>
                            </span>
                        </div>

                        <div class="row align-items-center g-0">
                            <div class="col-5 border-end pe-3">
                                <div class="text-muted small mb-1">Skor KPI</div>
                                <div class="d-flex align-items-baseline gap-1">
                                    <h2 class="mb-0 fw-bold"><?= number_format($score, 0, ',', '.') ?></h2>
                                    <small class="text-muted">/100</small>
                                </div>
                            </div>
                            <div class="col-7 ps-3">
                                <div class="d-flex justify-content-between mb-1 gap-1">
                                    <span class="badge bg-success-subtle text-success small">Tepat: <?= (int) ($ps['detail']['tepat'] ?? 0) ?></span>
                                    <span class="badge bg-danger-subtle text-danger small">Terlambat: <?= (int) ($ps['detail']['terlambat'] ?? 0) ?></span>
                                    <span class="badge bg-warning-subtle text-warning small">Menunggu: <?= (int) ($ps['detail']['open'] ?? 0) ?></span>
                                </div>
                                <div class="mt-2 text-end">
                                    <span class="text-muted small d-block">Total Nominal:</span>
                                    <span class="fw-bold text-dark">Rp <?= number_format((float) ($ps['detail']['total_payroll'] ?? 0), 0, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ====================================================== -->
<!-- JADWAL & REALISASI GAJI -->
<!-- ====================================================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent border-bottom py-3 px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="ti ti-calendar-event me-2 text-primary"></i>Jadwal & Realisasi Gaji
            </h5>
            <?php if ($can_input): ?>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#generate-payroll-modal">
                        <iconify-icon icon="solar:magic-stick-2-bold-duotone" width="18" height="18"></iconify-icon>
                        <span>Susun Otomatis</span>
                    </button>
                    <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#input-payroll-finance-modal">
                        <iconify-icon icon="solar:wallet-money-line-duotone" width="18" height="18"></iconify-icon>
                        <span>Input Payroll Gaji</span>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive p-4">
            <table class="table table-hover align-middle w-100" id="table-payroll-fp">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" width="50">No</th>
                        <th>Karyawan</th>
                        <th>Unit</th>
                        <th>Jatuh Tempo</th>
                        <th>Tanggal Bayar</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Klasifikasi</th>
                        <th class="text-end">Total</th>
                        <th>Catatan</th>
                        <th class="text-center" width="120">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($payrollItems)): ?>
                        <?php $no = 1; ?>
                        <?php foreach ($payrollItems as $row): ?>
                            <?php
                            $dueTs = strtotime($row->due_date);
                            $paidTs = !empty($row->paid_date) ? strtotime($row->paid_date) : null;

                            if ($row->status === 'dibayar' && $paidTs !== null) {
                                if ($paidTs <= $dueTs) {
                                    $cls = 'bg-success-subtle text-success';
                                    $label = 'Tepat Waktu';
                                } else {
                                    $cls = 'bg-danger-subtle text-danger';
                                    $label = 'Terlambat';
                                }
                            } elseif ($todayTs > $dueTs) {
                                $cls = 'bg-danger-subtle text-danger';
                                $label = 'Belum Bayar (Overdue)';
                            } else {
                                $cls = 'bg-warning-subtle text-warning';
                                $label = 'Belum Jatuh Tempo';
                            }
                            ?>
                            <tr>
                                <td class="text-center text-muted small"><?= $no++ ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-sm bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px;">
                                            <?= strtoupper(substr($row->NAMA_AKUN ?? 'K', 0, 1)) ?>
                                        </div>
                                        <span class="fw-semibold text-dark"><?= esc($row->NAMA_AKUN ?? '-') ?></span>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= esc($row->NAMA_UNIT ?? '-') ?></span></td>
                                <td class="small"><i class="ti ti-calendar me-1 text-muted"></i><?= esc(date('d-m-Y', $dueTs)) ?></td>
                                <td class="small">
                                    <?= $paidTs !== null
                                        ? '<i class="ti ti-circle-check text-success me-1"></i>' . esc(date('d-m-Y', $paidTs))
                                        : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill <?= $row->status === 'dibayar' ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $row->status === 'dibayar' ? 'Dibayar' : 'Rencana' ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $cls ?> px-2 py-1">
                                        <?= $label ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    Rp <?= number_format((float) $row->total, 0, ',', '.') ?>
                                </td>
                                <td class="small">
                                    <?php if (($row->sumber ?? 'manual') === 'auto'): ?>
                                        <span class="badge bg-info-subtle text-info me-1">Auto</span>
                                    <?php endif; ?>
                                    <span class="text-muted"><?= esc($row->notes ?? '-') ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($row->status !== 'dibayar' && $can_input): ?>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-warning edit-register-button" data-bs-toggle="modal" data-bs-target="#edit-register-modal" data-id="<?= esc($row->id) ?>" data-karyawan="<?= esc($row->NAMA_AKUN ?? '-') ?>" data-total="<?= esc($row->total) ?>" data-due="<?= esc($row->due_date) ?>" data-notes="<?= esc($row->notes ?? '') ?>" title="Koreksi Data">
                                                <i class="ti ti-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-success bayar-button" data-bs-toggle="modal" data-bs-target="#bayar-payroll-modal" data-id="<?= esc($row->id) ?>" data-karyawan="<?= esc($row->NAMA_AKUN ?? '-') ?>" data-total="<?= esc($row->total) ?>" title="Tandai Sudah Dibayar">
                                                <i class="ti ti-check"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ====================================================== -->
<!-- BONUS / LEMBUR (KAS KELUAR) -->
<!-- ====================================================== -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-bottom py-3 px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="ti ti-receipt-tax me-2 text-primary"></i>Bonus / Lembur (Kas Keluar)
            </h5>
            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#input-kas-modal">
                <iconify-icon icon="solar:wallet-money-line-duotone" width="18" height="18"></iconify-icon>
                <span>Input Bonus / Lembur</span>
            </button>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive p-4">
            <table class="table table-hover align-middle w-100" id="zero_config">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" width="50">No</th>
                        <th>Tanggal</th>
                        <th>Unit</th>
                        <th>Kategori</th>
                        <th>Deskripsi</th>
                        <th>Penerima</th>
                        <th>Bank / Rekening</th>
                        <th class="text-end">Jumlah</th>
                        <th class="text-center">Jenis</th>
                        <th class="text-center" width="120">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($kas_keluar)): ?>
                        <?php $no = 1; ?>
                        <?php foreach ($kas_keluar as $row): ?>
                            <tr>
                                <td class="text-center text-muted small"><?= $no++ ?></td>
                                <td class="small"><?= esc(date('d-m-Y', strtotime($row->tanggal))) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= esc($row->NAMA_UNIT ?? '-') ?></span></td>
                                <td><span class="badge bg-secondary-subtle text-secondary"><?= esc($row->kategori ?? '-') ?></span></td>
                                <td class="small"><?= esc($row->deskripsi) ?></td>
                                <td class="fw-semibold text-dark"><?= esc($row->NAMA_AKUN ?? '-') ?></td>
                                <td class="small">
                                    <div class="fw-bold text-dark"><?= esc($row->nama_bank ?? '-') ?></div>
                                    <div class="text-muted"><?= esc($row->atas_nama ?? '-') ?></div>
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    Rp <?= number_format($row->jumlah ?? 0, 0, ',', '.') ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= strtolower($row->jenis) === 'debet' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?>">
                                        <?= strtoupper(esc($row->jenis ?? '-')) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $locks = $payrollLocks ?? [];
                                    $isLocked = !empty($locks[(string) $row->idkas_keluar]);
                                    ?>
                                    <?php if (
                                        session()->get('ID_JABATAN') == 1 ||
                                        (session()->get('ID_JABATAN') == 35 && session()->get('ID_UNIT') == 1)
                                    ): ?>
                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                            <?php if (!$isLocked): ?>
                                                <button type="button" class="btn btn-outline-warning btn-sm edit-button" data-bs-toggle="modal" data-bs-target="#edit-kas-modal"
                                                    data-id="<?= esc($row->idkas_keluar) ?>"
                                                    data-tanggal="<?= esc($row->tanggal) ?>"
                                                    data-unit="<?= esc($row->idunit) ?>"
                                                    data-no-akun="<?= esc($row->no_akun) ?>"
                                                    data-kategori="<?= esc($row->kategori_idkategori) ?>"
                                                    data-deskripsi="<?= esc($row->deskripsi) ?>"
                                                    data-penerima="<?= esc($row->penerima) ?>"
                                                    data-no-rekening="<?= esc($row->no_rekening ?? '') ?>"
                                                    data-jumlah="<?= esc($row->jumlah) ?>"
                                                    data-jenis="<?= esc($row->jenis) ?>" title="Edit">
                                                    <i class="ti ti-edit"></i>
                                                </button>

                                                <button type="button" class="btn btn-outline-danger btn-sm delete-button" data-bs-toggle="modal" data-bs-target="#delete-kas-modal" data-id="<?= esc($row->idkas_keluar) ?>" title="Hapus">
                                                    <i class="ti ti-trash"></i>
                                                </button>

                                                <form action="<?= base_url('lock_payroll2') ?>" method="post" class="d-inline">
                                                    <input type="hidden" name="idkas_keluar" value="<?= esc($row->idkas_keluar) ?>">
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm" title="Lock Transaksi">
                                                        <i class="ti ti-lock"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form action="<?= base_url('unlock_payroll2') ?>" method="post" class="d-inline">
                                                    <input type="hidden" name="idkas_keluar" value="<?= esc($row->idkas_keluar) ?>">
                                                    <button type="submit" class="btn btn-success btn-sm" title="Unlock Transaksi">
                                                        <i class="ti ti-lock-open"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ====================================================== -->
<!-- MODAL SUSUN PAYROLL OTOMATIS -->
<!-- ====================================================== -->
<div class="modal fade" id="generate-payroll-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= base_url('payroll2/generate') ?>" method="post">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Susun Payroll Otomatis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 bg-info-subtle text-info-emphasis mb-3">
                        <div class="d-flex gap-2">
                            <i class="ti ti-info-circle fs-5 flex-shrink-0"></i>
                            <div class="small">
                                Nominal dihitung berdasarkan <code>salary_structures</code> (Gaji Pokok + Tunjangan KPI) + Lembur Kas Keluar. Hasil generate berstatus <strong>Rencana</strong>.
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bulan Payroll</label>
                        <input type="month" class="form-control" name="bulan" value="<?= esc($bulan !== '' ? $bulan : date('Y-m')) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Jatuh Tempo</label>
                        <input type="date" class="form-control" name="due_date" value="<?= esc(date('Y-m-t', strtotime(($bulan !== '' ? $bulan : date('Y-m')) . '-01'))) ?>">
                        <div class="form-text">Kosongkan untuk menyetel otomatis ke tanggal akhir bulan.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Batasi Unit</label>
                        <select class="form-select select" name="unit_ids[]" multiple size="4">
                            <option value="" selected>Semua Unit</option>
                            <?php foreach ($unit as $u): ?>
                                <option value="<?= esc($u->idunit) ?>"><?= esc($u->NAMA_UNIT) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light-subtle">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">Proses Susun</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====================================================== -->
<!-- MODAL KOREKSI PAYROLL -->
<!-- ====================================================== -->
<div class="modal fade" id="edit-register-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= base_url('payroll2/update-register') ?>" method="post">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Koreksi Payroll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="edit_register_id">
                    <p class="mb-3">Koreksi item gajian untuk karyawan: <strong id="edit_register_karyawan" class="text-primary">-</strong></p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Total Gaji</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" class="form-control currency-input" name="total" id="edit_register_total" inputmode="numeric" autocomplete="off" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Jatuh Tempo</label>
                        <input type="date" class="form-control" name="due_date" id="edit_register_due" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Catatan</label>
                        <textarea class="form-control" name="notes" id="edit_register_notes" rows="3" placeholder="Alasan koreksi atau catatan tambahan"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light-subtle">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====================================================== -->
<!-- MODAL INPUT PAYROLL GAJI (finance_payroll) -->
<!-- ====================================================== -->
<div class="modal fade" id="input-payroll-finance-modal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= base_url('finance/entry/payroll') ?>" method="post">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Input Payroll Gaji Manual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Karyawan</label>
                            <select class="form-select select" name="pegawai_id" id="input_pegawai" required>
                                <option value="">-- Pilih Karyawan --</option>
                                <?php foreach ($akun as $a): ?>
                                    <option value="<?= esc($a->ID_AKUN) ?>" data-unit="<?= esc($a->ID_UNIT ?? '') ?>"><?= esc($a->NAMA_AKUN) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Unit</label>
                            <select class="form-select" name="unit_id" id="input_unit" required>
                                <option value="">-- Pilih Unit --</option>
                                <?php foreach ($unit as $u): ?>
                                    <option value="<?= esc($u->idunit) ?>"><?= esc($u->NAMA_UNIT) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jatuh Tempo (Jadwal Gajian)</label>
                            <input type="date" class="form-control" name="due_date" value="<?= esc(($show_all ? date('Y-m') : $bulan)) ?>-25" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal Bayar <small class="text-muted fw-normal">(Opsional)</small></label>
                            <input type="date" class="form-control" name="paid_date">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Total Gaji</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" name="total" min="0" placeholder="0" required>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Catatan Tambahan"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light-subtle">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">Simpan Payroll</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====================================================== -->
<!-- MODAL SUDAH DIBAYAR -->
<!-- ====================================================== -->
<div class="modal fade" id="bayar-payroll-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= base_url('payroll2/bayar') ?>" method="post">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Konfirmasi Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <input type="hidden" name="id" id="bayar_id">
                    <div class="avatar-lg bg-success-subtle text-success rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:60px; height:60px;">
                        <i class="ti ti-cash fs-2"></i>
                    </div>
                    <p class="mb-3 fs-6">Tandai penggajian <strong id="bayar_karyawan" class="text-dark">-</strong> sebagai telah dibayar?</p>

                    <div class="text-start">
                        <label class="form-label fw-semibold">Tanggal Pelunasan / Bayar</label>
                        <input type="date" class="form-control" name="paid_date" id="bayar_tanggal" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light-subtle justify-content-center">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success px-4">Konfirmasi Bayar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====================================================== -->
<!-- MODAL INPUT BONUS / LEMBUR -->
<!-- ====================================================== -->
<div class="modal fade" id="input-kas-modal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= base_url('insert_payroll2') ?>" method="post">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Input Bonus / Lembur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal</label>
                            <input type="date" class="form-control" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Unit</label>
                            <select class="form-select" name="idunit" required>
                                <option value="">-- Pilih Unit --</option>
                                <?php foreach ($unit as $u): ?>
                                    <option value="<?= esc($u->idunit) ?>"><?= esc($u->NAMA_UNIT) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Deskripsi</label>
                            <input type="text" class="form-control" name="deskripsi" placeholder="Misal: Uang Lembur Project X" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Penerima</label>
                            <select class="form-select select" name="penerima" id="penerima" required>
                                <option value="">-- Pilih Penerima --</option>
                                <?php foreach ($akun as $a): ?>
                                    <option value="<?= esc($a->ID_AKUN) ?>"><?= esc($a->NAMA_AKUN) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Rekening Bank</label>
                            <select class="form-select select" name="no_rekening">
                                <option value="">-- Pilih Bank --</option>
                                <?php foreach ($bank as $b): ?>
                                    <option value="<?= esc($b->idbank) ?>"><?= esc($b->atas_nama) ?> : <?= esc($b->norek) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jumlah</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" name="jumlah" min="0" placeholder="0" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jenis Mutasi</label>
                            <select class="form-select" name="jenis" required>
                                <option value="">-- Pilih Jenis --</option>
                                <option value="debet">Debet</option>
                                <option value="kredit">Kredit</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light-subtle">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">Simpan Mutasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====================================================== -->
<!-- MODAL EDIT BONUS / LEMBUR -->
<!-- ====================================================== -->
<div class="modal fade" id="edit-kas-modal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= base_url('update_payroll2') ?>" method="post">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Edit Mutasi Bonus / Lembur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="idkas_keluar" id="edit_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal</label>
                            <input type="date" class="form-control" name="tanggal" id="edit_tanggal" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Unit</label>
                            <select class="form-select" name="idunit" id="edit_unit" required>
                                <option value="">-- Pilih Unit --</option>
                                <?php foreach ($unit as $u): ?>
                                    <option value="<?= esc($u->idunit) ?>"><?= esc($u->NAMA_UNIT) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Deskripsi</label>
                            <input type="text" class="form-control" name="deskripsi" id="edit_deskripsi" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Penerima</label>
                            <select class="form-select select" name="penerima" id="edit_penerima" required>
                                <option value="">-- Pilih Penerima --</option>
                                <?php foreach ($akun as $a): ?>
                                    <option value="<?= esc($a->ID_AKUN) ?>"><?= esc($a->NAMA_AKUN) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Rekening Bank</label>
                            <select class="form-select select" name="no_rekening" id="edit_no_rekening">
                                <option value="">-- Pilih Bank --</option>
                                <?php foreach ($bank as $b): ?>
                                    <option value="<?= esc($b->idbank) ?>"><?= esc($b->atas_nama) ?> : <?= esc($b->norek) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jumlah</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" name="jumlah" id="edit_jumlah" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jenis</label>
                            <select class="form-select" name="jenis" id="edit_jenis" required>
                                <option value="">-- Pilih Jenis --</option>
                                <option value="debet">Debet</option>
                                <option value="kredit">Kredit</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light-subtle">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">Update Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ====================================================== -->
<!-- MODAL DELETE BONUS / LEMBUR -->
<!-- ====================================================== -->
<div class="modal fade" id="delete-kas-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= base_url('delete_kas_keluar') ?>" method="post">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <input type="hidden" name="idkas_keluar" id="delete_id">
                    <div class="avatar-lg bg-danger-subtle text-danger rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:60px; height:60px;">
                        <i class="ti ti-trash fs-2"></i>
                    </div>
                    <p class="mb-0 fs-6">Apakah Anda yakin ingin menghapus data <strong>Bonus / Lembur</strong> ini? Tindakan ini tidak dapat dibatalkan.</p>
                </div>
                <div class="modal-footer border-top bg-light-subtle justify-content-center">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger px-4">Hapus Permanen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Tabel diinisialisasi di luar document.ready supaya menang dari init
    // global di datatable-basic.init.js yang dimuat di footer. Kalau footer
    // yang lebih dulu, konfigurasi searchPlaceholder di bawah hilang dan
    // muncul peringatan "Cannot reinitialise DataTable". Pengecekan
    // isDataTable menjaga halaman ini tetap aman bila urutannya berubah.
    if ($('#table-payroll-fp').length && !$.fn.dataTable.isDataTable('#table-payroll-fp')) {
        $('#table-payroll-fp').DataTable({
            responsive: true,
            language: {
                search: "",
                searchPlaceholder: "Cari data payroll..."
            }
        });
    }

    if ($('#zero_config').length && !$.fn.dataTable.isDataTable('#zero_config')) {
        $('#zero_config').DataTable({
            responsive: true,
            language: {
                search: "",
                searchPlaceholder: "Cari data lembur/bonus..."
            }
        });
    }

    $(document).ready(function() {
        // Select2 Integration
        const selectOptions = {
            width: '100%'
        };
        $('#penerima').select2({
            ...selectOptions,
            dropdownParent: $('#input-kas-modal')
        });
        $('#edit_penerima').select2({
            ...selectOptions,
            dropdownParent: $('#edit-kas-modal')
        });
        $('#input_pegawai').select2({
            ...selectOptions,
            dropdownParent: $('#input-payroll-finance-modal')
        });

        // Auto select unit ketika pegawai dipilih
        $('#input_pegawai').on('change', function() {
            const unitId = $(this).find('option:selected').data('unit');
            if (unitId) {
                $('#input_unit').val(String(unitId));
            }
        });

        // Tandai Sudah Dibayar
        $('#table-payroll-fp').on('click', '.bayar-button', function() {
            const btn = $(this);
            $('#bayar_id').val(btn.data('id'));
            $('#bayar_karyawan').text(btn.data('karyawan'));
            $('#bayar_tanggal').val('<?= date('Y-m-d') ?>');
        });

        // Koreksi Payroll Register
        $('#table-payroll-fp').on('click', '.edit-register-button', function() {
            const btn = $(this);
            $('#edit_register_id').val(btn.data('id'));
            $('#edit_register_karyawan').text(btn.data('karyawan'));
            const totalVal = parseInt(btn.data('total'), 10) || 0;
            $('#edit_register_total').val(totalVal === 0 ? '' : formatRupiah(totalVal, ''));
            $('#edit_register_due').val(btn.data('due'));
            $('#edit_register_notes').val(btn.data('notes') || '');
        });

        // Edit Kas Keluar (Bonus/Lembur)
        $('#zero_config').on('click', '.edit-button', function() {
            const btn = $(this);
            $('#edit_id').val(btn.data('id'));
            $('#edit_tanggal').val(btn.data('tanggal'));
            $('#edit_unit').val(btn.data('unit'));
            $('#edit_deskripsi').val(btn.data('deskripsi'));
            $('#edit_penerima').val(btn.data('penerima')).trigger('change');
            $('#edit_no_rekening').val(btn.data('no_rekening')).trigger('change');
            $('#edit_jumlah').val(btn.data('jumlah'));
            $('#edit_jenis').val(btn.data('jenis'));
        });

        // Format Rupiah pada input currency
        function formatRupiah(angka, prefix = 'Rp ') {
            const numberString = angka.toString().replace(/[^\d]/g, '');
            const split = numberString.split(',');
            const sisa = split[0].length % 3;
            let rupiah = split[0].substr(0, sisa);
            const ribuan = split[0].substr(sisa).match(/\d{3}/g);

            if (ribuan) {
                const separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }

            rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
            return prefix + rupiah;
        }

        function parseRupiah(value) {
            return parseInt(value.toString().replace(/[^\d]/g, ''), 10) || 0;
        }

        $(document).on('input', '.currency-input', function(e) {
            const cursorPos = e.target.selectionStart;
            const oldLength = e.target.value.length;
            const raw = parseRupiah(e.target.value);
            e.target.value = raw === 0 ? '' : formatRupiah(raw, '');
            const newLength = e.target.value.length;
            e.target.selectionStart = e.target.selectionEnd = cursorPos + (newLength - oldLength);
        });

        // Hapus Kas Keluar
        $('#zero_config').on('click', '.delete-button', function() {
            $('#delete_id').val($(this).data('id'));
        });
    });
</script>
