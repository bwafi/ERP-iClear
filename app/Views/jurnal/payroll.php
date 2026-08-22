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


<div class="card w-100">

    <!-- HEADER -->
    <div class="card-body px-4 pt-4 pb-2">

        <button type="button"
            class="btn btn-primary"
            data-bs-toggle="modal"
            data-bs-target="#input-kas-modal">

            <iconify-icon
                icon="solar:wallet-money-line-duotone"
                width="24"
                height="24">
            </iconify-icon>

            Input Payroll

        </button>

    </div>


    <!-- TABLE -->
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
<!-- MODAL INPUT -->
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
                        Input Payroll
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
<!-- MODAL EDIT -->
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
                        Edit Payroll
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
<!-- MODAL DELETE -->
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
                        Hapus Payroll
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
                        data Payroll ini?
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