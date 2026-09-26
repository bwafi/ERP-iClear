<link rel="stylesheet"
    href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">

        <h4 class="fw-semibold mb-0">
            Payroll
        </h4>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">

                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none"
                        href="<?= base_url('/') ?>">
                        Jurnal
                    </a>
                </li>

                <li class="breadcrumb-item active">
                    Payroll
                </li>

            </ol>
        </nav>

    </div>
</div>


<?php
$todayTs = strtotime(date('Y-m-d'));
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

<div class="card w-100 mb-4">
    <div class="card-body p-3">

        <form
            class="row g-2 align-items-end"
            method="get"
            action="<?= base_url('payroll2') ?>">

            <div class="col-auto">
                <label class="form-label mb-1">Bulan</label>
                <input
                    type="month"
                    class="form-control"
                    name="bulan"
                    value="<?= esc($bulan) ?>"
                    placeholder="Semua Bulan">
            </div>

            <div class="col-auto">
                <button
                    type="submit"
                    class="btn btn-primary">
                    Tampilkan
                </button>
            </div>

            <div class="col-auto">
                <button
                    type="submit"
                    name="bulan"
                    value=""
                    class="btn <?= $show_all ? 'btn-secondary' : 'btn-outline-secondary' ?>">
                    Semua Bulan
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
            <div class="card w-100">
                <div class="card-body text-center text-muted py-3">
                    Menampilkan semua bulan. Pilih bulan di atas untuk
                    melihat ringkasan KPI per unit.
                </div>
            </div>
        </div>

    <?php elseif (empty($payrollSummary)): ?>

        <div class="col-12">
            <div class="card w-100">
                <div class="card-body text-center text-muted py-4">
                    Belum ada data payroll untuk bulan
                    <?= esc($bulan) ?>.
                </div>
            </div>
        </div>

    <?php else: ?>

        <?php foreach ($payrollSummary as $ps): ?>

            <?php
            $score = (float) ($ps['score'] ?? 0);
            $badgeCls = $score >= 80
                ? 'bg-success'
                : ($score >= 60 ? 'bg-warning text-dark' : 'bg-danger');
            $statusLabel = ($ps['status'] ?? 'no_data') === 'no_data'
                ? 'Belum Ada Penilaian'
                : 'Sudah Dinilai';
            $badgeStatus = ($ps['status'] ?? 'no_data') === 'no_data'
                ? 'bg-secondary'
                : 'bg-primary';
            ?>

            <div class="col-xl-4 col-md-6 mb-3">

                <div class="card w-100 h-100">
                    <div class="card-body">

                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="mb-0 fw-semibold">
                                <?= esc($ps['nama_unit'] ?? '-') ?>
                            </h6>
                            <span class="badge <?= $badgeStatus ?>">
                                <?= $statusLabel ?>
                            </span>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <div>
                                <h3 class="mb-0">
                                    <?= number_format($score, 0, ',', '.') ?>
                                </h3>
                                <small class="text-muted">Skor</small>
                            </div>
                            <div class="ms-auto text-end">
                                <div>
                                    <span class="badge bg-success">Tepat
                                        <?= (int) ($ps['detail']['tepat'] ?? 0) ?></span>
                                    <span class="badge bg-danger">Terlambat
                                        <?= (int) ($ps['detail']['terlambat'] ?? 0) ?></span>
                                    <span class="badge bg-secondary">Menunggu
                                        <?= (int) ($ps['detail']['open'] ?? 0) ?></span>
                                </div>
                                <div class="mt-2 small text-muted">
                                    Total
                                    Rp <?= number_format((float) ($ps['detail']['total_payroll'] ?? 0), 0, ',', '.') ?>
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

<div class="card w-100">

    <div class="card-body px-4 pt-4 pb-2">

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">

            <h5 class="fw-semibold mb-0">
                Jadwal & Realisasi Gaji
            </h5>

            <?php if ($can_input): ?>

                <button
                    type="button"
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#input-payroll-finance-modal">

                    <iconify-icon
                        icon="solar:wallet-money-line-duotone"
                        width="24"
                        height="24">
                    </iconify-icon>

                    Input Payroll Gaji

                </button>

            <?php endif; ?>

        </div>

    </div>

    <div class="table-responsive px-4 pb-4">

        <table
            class="table table-bordered table-striped align-middle"
            id="table-payroll-fp">

            <thead>
                <tr>
                    <th>No</th>
                    <th>Karyawan</th>
                    <th>Unit</th>
                    <th>Jatuh Tempo</th>
                    <th>Tanggal Bayar</th>
                    <th>Status</th>
                    <th>Klasifikasi</th>
                    <th>Total</th>
                    <th>Catatan</th>
                    <th width="120">Action</th>
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
                                $cls = 'bg-success';
                                $label = 'Tepat Waktu';
                            } else {
                                $cls = 'bg-danger';
                                $label = 'Terlambat';
                            }
                        } elseif ($todayTs > $dueTs) {
                            $cls = 'bg-danger';
                            $label = 'Belum Bayar (Overdue)';
                        } else {
                            $cls = 'bg-warning text-dark';
                            $label = 'Belum Jatuh Tempo';
                        }
                        ?>

                        <tr>

                            <td><?= $no++ ?></td>

                            <td><?= esc($row->NAMA_AKUN ?? '-') ?></td>

                            <td><?= esc($row->NAMA_UNIT ?? '-') ?></td>

                            <td><?= esc(date('d-m-Y', $dueTs)) ?></td>

                            <td><?= $paidTs !== null ? esc(date('d-m-Y', $paidTs)) : '<span class="text-muted">Belum</span>' ?></td>

                            <td>
                                <span class="badge <?= $row->status === 'dibayar' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <?= $row->status === 'dibayar' ? 'Dibayar' : 'Rencana' ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge <?= $cls ?>">
                                    <?= $label ?>
                                </span>
                            </td>

                            <td>
                                Rp <?= number_format((float) $row->total, 0, ',', '.') ?>
                            </td>

                            <td><?= esc($row->notes ?? '-') ?></td>

                            <td>
                                <?php if ($row->status !== 'dibayar' && $can_input): ?>

                                    <button
                                        type="button"
                                        class="btn btn-success btn-sm bayar-button"
                                        data-bs-toggle="modal"
                                        data-bs-target="#bayar-payroll-modal"
                                        data-id="<?= esc($row->id) ?>"
                                        data-karyawan="<?= esc($row->NAMA_AKUN ?? '-') ?>"
                                        data-total="<?= esc($row->total) ?>">

                                        <i class="ti ti-check"></i>
                                        Sudah Dibayar

                                    </button>

                                <?php else: ?>

                                    <span class="text-muted">-</span>

                                <?php endif; ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- ====================================================== -->
<!-- BONUS / LEMBUR (KAS KELUAR) -->
<!-- ====================================================== -->

<div class="card w-100 mt-4">

    <div class="card-body px-4 pt-4 pb-2">

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">

            <h5 class="fw-semibold mb-0">
                Bonus / Lembur (Kas Keluar)
            </h5>

            <button
                type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#input-kas-modal">

                <iconify-icon
                    icon="solar:wallet-money-line-duotone"
                    width="24"
                    height="24">
                </iconify-icon>

                Input Bonus / Lembur

            </button>

        </div>

    </div>

    <div class="table-responsive px-4 pb-4">

        <table
            class="table table-bordered table-striped align-middle"
            id="zero_config">

            <thead>

                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Unit</th>
                    <th>Kategori</th>
                    <th>Deskripsi</th>
                    <th>Penerima</th>
                    <th>Bank</th>
                    <th>Jumlah</th>
                    <th>Jenis</th>
                    <th width="120">Action</th>
                </tr>

            </thead>

            <tbody>

                <?php if (!empty($kas_keluar)): ?>

                    <?php $no = 1; ?>

                    <?php foreach ($kas_keluar as $row): ?>

                        <tr>

                            <td>
                                <?= $no++ ?>
                            </td>

                            <td>
                                <?= esc(
                                    date(
                                        'd-m-Y',
                                        strtotime($row->tanggal)
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= esc($row->NAMA_UNIT ?? '-') ?>
                            </td>

                            <td>
                                <?= esc($row->kategori ?? '-') ?>
                            </td>

                            <td>
                                <?= esc($row->deskripsi) ?>
                            </td>

                            <td>
                                <?= esc($row->NAMA_AKUN ?? '-') ?>
                            </td>

                            <td>
                                <?= esc($row->nama_bank ?? '-')?> <br>
                                <?= esc($row->atas_nama ?? '-') ?>
                            </td>

                            <td>
                                Rp <?= number_format(
                                    $row->jumlah ?? 0,
                                    0,
                                    ',',
                                    '.'
                                ) ?>
                            </td>

                            <td>
                                <?= esc($row->jenis ?? '-') ?>
                            </td>

                            <td>
                                <?php
                                $locks = $payrollLocks ?? [];
                                $isLocked = !empty($locks[(string) $row->idkas_keluar]);
                                ?>

                                <?php if (
                                    session()->get('ID_JABATAN') == 1 ||
                                    (session()->get('ID_JABATAN') == 35 && session()->get('ID_UNIT') == 1)
                                ): ?>

                                    <?php if (!$isLocked): ?>

                                        <!-- EDIT -->
                                        <button
                                            type="button"
                                            class="btn btn-warning btn-sm edit-button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#edit-kas-modal"

                                            data-id="<?= esc($row->idkas_keluar) ?>"
                                            data-tanggal="<?= esc($row->tanggal) ?>"
                                            data-unit="<?= esc($row->idunit) ?>"
                                            data-no-akun="<?= esc($row->no_akun) ?>"
                                            data-kategori="<?= esc($row->kategori_idkategori) ?>"
                                            data-deskripsi="<?= esc($row->deskripsi) ?>"
                                            data-penerima="<?= esc($row->penerima) ?>"
                                            data-no-rekening="<?= esc($row->no_rekening ?? '') ?>"
                                            data-jumlah="<?= esc($row->jumlah) ?>"
                                            data-jenis="<?= esc($row->jenis) ?>">

                                            <i class="ti ti-edit"></i>
                                        </button>

                                        <!-- DELETE -->
                                        <button
                                            type="button"
                                            class="btn btn-danger btn-sm delete-button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#delete-kas-modal"
                                            data-id="<?= esc($row->idkas_keluar) ?>">

                                            <i class="ti ti-trash"></i>
                                        </button>

                                    <?php endif; ?>


                                    <!-- LOCK -->
                                    <?php if (!$isLocked): ?>

                                        <form
                                            action="<?= base_url('lock_payroll2') ?>"
                                            method="post"
                                            style="display:inline;">

                                            <input
                                                type="hidden"
                                                name="idkas_keluar"
                                                value="<?= esc($row->idkas_keluar) ?>">

                                            <button
                                                type="submit"
                                                class="btn btn-warning btn-sm"
                                                title="Lock">

                                                <i class="bi bi-lock"></i>
                                            </button>
                                        </form>

                                    <?php else: ?>

                                        <!-- UNLOCK -->
                                        <form
                                            action="<?= base_url('unlock_payroll2') ?>"
                                            method="post"
                                            style="display:inline;">

                                            <input
                                                type="hidden"
                                                name="idkas_keluar"
                                                value="<?= esc($row->idkas_keluar) ?>">

                                            <button
                                                type="submit"
                                                class="btn btn-success btn-sm"
                                                title="Unlock">

                                                <i class="bi bi-unlock"></i>
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                <?php endif; ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- ====================================================== -->
<!-- MODAL INPUT PAYROLL GAJI (finance_payroll) -->
<!-- ====================================================== -->

<div class="modal fade"
    id="input-payroll-finance-modal"
    tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form
                action="<?= base_url('finance/entry/payroll') ?>"
                method="post">

                <div class="modal-header">

                    <h5 class="modal-title">
                        Input Payroll Gaji
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <!-- KARYAWAN -->
                    <div class="mb-3">

                        <label class="form-label">
                            Karyawan
                        </label>

                        <select
                            class="form-control select"
                            name="pegawai_id"
                            id="input_pegawai">

                            <option value="">
                                -- Pilih Karyawan --
                            </option>

                            <?php foreach ($akun as $a): ?>

                                <option
                                    value="<?= esc($a->ID_AKUN) ?>"
                                    data-unit="<?= esc($a->ID_UNIT ?? '') ?>">

                                    <?= esc($a->NAMA_AKUN) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- UNIT -->
                    <div class="mb-3">

                        <label class="form-label">
                            Unit
                        </label>

                        <select
                            class="form-control"
                            name="unit_id"
                            id="input_unit"
                            required>

                            <option value="">
                                -- Pilih Unit --
                            </option>

                            <?php foreach ($unit as $u): ?>

                                <option
                                    value="<?= esc($u->idunit) ?>">

                                    <?= esc($u->NAMA_UNIT) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- TANGGAL JATUH TEMPO -->
                    <div class="mb-3">

                        <label class="form-label">
                            Tanggal Jatuh Tempo (Jadwal Gajian)
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            name="due_date"
                            value="<?= esc(($show_all ? date('Y-m') : $bulan)) ?>-25"
                            required>

                    </div>

                    <!-- TANGGAL BAYAR -->
                    <div class="mb-3">

                        <label class="form-label">
                            Tanggal Bayar
                            <small class="text-muted">(kosongkan bila belum dibayar)</small>
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            name="paid_date">

                    </div>

                    <!-- JUMLAH -->
                    <div class="mb-3">

                        <label class="form-label">
                            Total Gaji
                        </label>

                        <input
                            type="number"
                            class="form-control"
                            name="total"
                            min="0"
                            placeholder="Masukkan total gaji"
                            required>

                    </div>

                    <!-- CATATAN -->
                    <div class="mb-3">

                        <label class="form-label">
                            Catatan
                        </label>

                        <textarea
                            class="form-control"
                            name="notes"
                            rows="2"
                            placeholder="Catatan penilaian / info tambahan"></textarea>

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                        Batal

                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary">

                        Simpan

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ====================================================== -->
<!-- MODAL SUDAH DIBAYAR -->
<!-- ====================================================== -->

<div class="modal fade"
    id="bayar-payroll-modal"
    tabindex="-1">

    <div class="modal-dialog">

        <div class="modal-content">

            <form
                action="<?= base_url('payroll2/bayar') ?>"
                method="post">

                <div class="modal-header">

                    <h5 class="modal-title">
                        Tandai Sudah Dibayar
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <input
                        type="hidden"
                        name="id"
                        id="bayar_id">

                    <p class="mb-3">
                        Tandai gaji karyawan
                        <strong id="bayar_karyawan">-</strong>
                        sebagai sudah dibayar?
                    </p>

                    <div class="mb-3">

                        <label class="form-label">
                            Tanggal Bayar
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            name="paid_date"
                            id="bayar_tanggal"
                            value="<?= date('Y-m-d') ?>"
                            required>

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                        Batal

                    </button>

                    <button
                        type="submit"
                        class="btn btn-success">

                        Sudah Dibayar

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ====================================================== -->
<!-- MODAL INPUT BONUS / LEMBUR -->
<!-- ====================================================== -->

<div class="modal fade"
    id="input-kas-modal"
    tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form
                action="<?= base_url('insert_payroll2') ?>"
                method="post">

                <div class="modal-header">

                    <h5 class="modal-title">
                        Input Bonus / Lembur
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>


                <div class="modal-body">

                    <!-- TANGGAL -->
                    <div class="mb-3">

                        <label class="form-label">
                            Tanggal
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            name="tanggal"
                            value="<?= date('Y-m-d') ?>"
                            required>

                    </div>


                    <!-- UNIT -->
                    <div class="mb-3">

                        <label class="form-label">
                            Unit
                        </label>

                        <select
                            class="form-control"
                            name="idunit"
                            required>

                            <option value="">
                                -- Pilih Unit --
                            </option>

                            <?php foreach ($unit as $u): ?>

                                <option
                                    value="<?= esc($u->idunit) ?>">

                                    <?= esc($u->NAMA_UNIT) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>



                    <!-- DESKRIPSI -->
                    <div class="mb-3">

                        <label class="form-label">
                            Deskripsi
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="deskripsi"
                            placeholder="Tuliskan deskripsi"
                            required>

                    </div>


                    <!-- PENERIMA -->
                    <div class="mb-3">

                        <label class="form-label">
                            Penerima
                        </label>

                        <select
                            class="form-control select"
                            name="penerima"
                            id="penerima"
                            required>

                            <option value="">
                                -- Pilih Penerima --
                            </option>

                            <?php foreach ($akun as $a): ?>

                                <option
                                    value="<?= esc($a->ID_AKUN) ?>">

                                    <?= esc($a->NAMA_AKUN) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Bank
                        </label>

                        <select class="form-control select" name="no_rekening">
                            <option value="">
                                -- Pilih Bank --
                            </option>

                            <?php foreach ($bank as $b): ?>
                                <option value="<?= esc($b->idbank) ?>"><?= esc($b->atas_nama) ?> : <?= esc($b->norek) ?></option>
                            <?php endforeach; ?>
                        </select>

                    </div>


                    <!-- JUMLAH -->
                    <div class="mb-3">

                        <label class="form-label">
                            Jumlah
                        </label>

                        <input
                            type="number"
                            class="form-control"
                            name="jumlah"
                            min="0"
                            placeholder="Masukkan jumlah"
                            required>

                    </div>


                    <!-- JENIS -->
                    <div class="mb-3">

                        <label class="form-label">
                            Jenis
                        </label>

                        <select
                            class="form-control"
                            name="jenis"
                            required>

                            <option value="">
                                -- Pilih Jenis --
                            </option>

                            <option value="debet">
                                Debet
                            </option>

                            <option value="kredit">
                                Kredit
                            </option>

                        </select>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                        Batal

                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary">

                        Simpan

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ====================================================== -->
<!-- MODAL EDIT BONUS / LEMBUR -->
<!-- ====================================================== -->

<div class="modal fade"
    id="edit-kas-modal"
    tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <form
                action="<?= base_url('update_payroll2') ?>"
                method="post">

                <div class="modal-header">

                    <h5 class="modal-title">
                        Edit Bonus / Lembur
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>


                <div class="modal-body">

                    <input
                        type="hidden"
                        name="idkas_keluar"
                        id="edit_id">


                    <!-- TANGGAL -->
                    <div class="mb-3">

                        <label class="form-label">
                            Tanggal
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            name="tanggal"
                            id="edit_tanggal"
                            required>

                    </div>


                    <!-- UNIT -->
                    <div class="mb-3">

                        <label class="form-label">
                            Unit
                        </label>

                        <select
                            class="form-control"
                            name="idunit"
                            id="edit_unit"
                            required>

                            <option value="">
                                -- Pilih Unit --
                            </option>

                            <?php foreach ($unit as $u): ?>

                                <option
                                    value="<?= esc($u->idunit) ?>">

                                    <?= esc($u->NAMA_UNIT) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- DESKRIPSI -->
                    <div class="mb-3">

                        <label class="form-label">
                            Deskripsi
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="deskripsi"
                            id="edit_deskripsi"
                            placeholder="Tuliskan deskripsi"
                            required>

                    </div>


                    <!-- PENERIMA -->
                    <div class="mb-3">

                        <label class="form-label">
                            Penerima
                        </label>

                        <select
                            class="form-control select"
                            name="penerima"
                            id="edit_penerima"
                            required>

                            <option value="">
                                -- Pilih Penerima --
                            </option>

                            <?php foreach ($akun as $a): ?>

                                <option
                                    value="<?= esc($a->ID_AKUN) ?>">

                                    <?= esc($a->NAMA_AKUN) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Bank
                        </label>

                        <select class="form-control select" name="no_rekening" id="edit_no_rekening">
                            <option value="">
                                -- Pilih Bank --
                            </option>

                            <?php foreach ($bank as $b): ?>
                                <option value="<?= esc($b->idbank) ?>"><?= esc($b->atas_nama) ?> : <?= esc($b->norek) ?></option>
                            <?php endforeach; ?>
                        </select>

                    </div>

                    <!-- JUMLAH -->
                    <div class="mb-3">

                        <label class="form-label">
                            Jumlah
                        </label>

                        <input
                            type="number"
                            class="form-control"
                            name="jumlah"
                            id="edit_jumlah"
                            required>

                    </div>


                    <!-- JENIS -->
                    <div class="mb-3">

                        <label class="form-label">
                            Jenis
                        </label>

                        <select
                            class="form-control"
                            name="jenis"
                            id="edit_jenis"
                            required>

                            <option value="">
                                -- Pilih Jenis --
                            </option>

                            <option value="debet">
                                Debet
                            </option>

                            <option value="kredit">
                                Kredit
                            </option>

                        </select>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                        Batal

                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary">

                        Update

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ====================================================== -->
<!-- MODAL DELETE BONUS / LEMBUR -->
<!-- ====================================================== -->

<div class="modal fade"
    id="delete-kas-modal"
    tabindex="-1">

    <div class="modal-dialog">

        <div class="modal-content">

            <form
                action="<?= base_url('delete_kas_keluar') ?>"
                method="post">

                <div class="modal-header">

                    <h5 class="modal-title">
                        Hapus Bonus / Lembur
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>


                <div class="modal-body">

                    <input
                        type="hidden"
                        name="idkas_keluar"
                        id="delete_id">

                    <p>
                        Apakah Anda yakin ingin menghapus
                        data Bonus / Lembur ini?
                    </p>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                        Batal

                    </button>

                    <button
                        type="submit"
                        class="btn btn-danger">

                        Hapus

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script>

$(document).ready(function() {

    // DataTable
    if ($('#table-payroll-fp').length) {
        $('#table-payroll-fp').DataTable();
    }
    $('#zero_config').DataTable();


    // Select2 penerima
    $('#penerima').select2({
        dropdownParent: $('#input-kas-modal'),
        width: '100%'
    });


    $('#edit_penerima').select2({
        dropdownParent: $('#edit-kas-modal'),
        width: '100%'
    });


    $('#input_pegawai').select2({
        dropdownParent: $('#input-payroll-finance-modal'),
        width: '100%'
    });


    // Karyawan dipilih → unit otomatis terisi
    $('#input_pegawai').on('change', function() {

        const unitId = $(this).find('option:selected').data('unit');

        if (unitId) {
            $('#input_unit').val(String(unitId));
        }

    });


    // ============================
    // TANDAI SUDAH DIBAYAR
    // ============================

    $('#table-payroll-fp').on(
        'click',
        '.bayar-button',
        function() {

            const btn = $(this);

            $('#bayar_id').val(btn.data('id'));
            $('#bayar_karyawan').text(btn.data('karyawan'));
            $('#bayar_tanggal').val('<?= date('Y-m-d') ?>');

        }
    );


    // ============================
    // EDIT
    // ============================

    $('#zero_config').on(
        'click',
        '.edit-button',
        function() {

            const btn = $(this);

            $('#edit_id').val(
                btn.data('id')
            );

            $('#edit_tanggal').val(
                btn.data('tanggal')
            );

            $('#edit_unit').val(
                btn.data('unit')
            );

            $('#edit_deskripsi').val(
                btn.data('deskripsi')
            );

            $('#edit_penerima')
                .val(btn.data('penerima'))
                .trigger('change');
            
            $('#edit_no_rekening')
                .val(btn.data('no_rekening'))
                .trigger('change');
            
            $('#edit_jumlah').val(
                btn.data('jumlah')
            );

            $('#edit_jenis').val(
                btn.data('jenis')
            );

        }
    );


    // ============================
    // DELETE
    // ============================

    $('#zero_config').on(
        'click',
        '.delete-button',
        function() {

            const id = $(this).data('id');

            $('#delete_id').val(id);

        }
    );

});

</script>