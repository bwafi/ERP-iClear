<!-- Breadcrumb -->
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Datamaster Pelanggan</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Datamaster</a>
                </li>
                <li class="breadcrumb-item" aria-current="page">Pelanggan</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Main Card -->
<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom"></div>

    <div class="px-4 py-3 border-bottom d-flex justify-content-between">
        <div class="d-flex gap-2">
            <form method="GET" action="<?= base_url('/pelanggan') ?>" class="d-flex gap-2">
                <input type="text" name="search" value="<?= esc($search ?? '') ?>" class="form-control" placeholder="Cari nama, NIK, no HP, alamat..." style="width: 280px;">
                <button type="submit" class="btn btn-secondary">
                    <iconify-icon icon="solar:magnifer-broken" width="20" height="20"></iconify-icon>
                </button>
                <?php if (!empty($search)): ?>
                    <a href="<?= base_url('/pelanggan') ?>" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('export/pelanggan') ?>" class="btn btn-danger"
                style="display: inline-flex; align-items: center;">
                <iconify-icon icon="solar:export-broken" width="24" height="24" style="margin-right: 8px;">
                </iconify-icon>
                Export
            </a>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#samedata-modal"
                style="display: inline-flex; align-items: center;">
                <iconify-icon icon="solar:import-broken" width="24" height="24" style="margin-right: 8px;">
                </iconify-icon>
                Import
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#input-pelanggan-modal"
                style="display: inline-flex; align-items: center;">
                <iconify-icon icon="solar:password-minimalistic-input-broken" width="24" height="24"
                    style="margin-right: 8px;"></iconify-icon>
                Input
            </button>
        </div>
    </div>

    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" id="pelanggan_table">
            <thead class="text-dark fs-4">
                <tr>
                    <th>Nama</th>
                    <th>Alamat</th>
                    <th>Kecamatan</th>
                    <th>Kabupaten</th>
                    <th>Provinsi</th>
                    <th>Nomer HP</th>
                    <th>Jenis Pelanggan</th>
                    <th>Mengetahui Dari</th>
                    <th>Riwayat Transaksi</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pelanggan)): ?>
                    <?php foreach ($pelanggan as $row): ?>
                        <tr>
                            <td><?= esc($row->nama) ?></td>
                            <td><?= esc($row->alamat) ?></td>
                            <td><?= esc($row->kecamatan) ?></td>
                            <td><?= esc($row->kabupaten) ?></td>
                            <td><?= esc($row->provinsi) ?></td>
                            <td><?= esc($row->no_hp) ?></td>
                            <td><?= $row->kategori == 0 ? 'Umum' : 'Toko' ?></td>
                            <td><?= esc($row->mengetahui_dari) ?></td>
                            <td><a href="<?= base_url('riwayat_transaksi_pelanggan/' . $row->id_pelanggan) ?>" class="btn btn-sm btn-success">Check</a></td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-warning edit-button" data-bs-toggle="modal"
                                        data-bs-target="#edit-pelanggan-modal" data-id_pelanggan="<?= esc($row->id_pelanggan) ?>"
                                        data-nama="<?= esc($row->nama) ?>" data-alamat="<?= esc($row->alamat) ?>"
                                        data-nik="<?= esc($row->nik) ?>" data-no_hp="<?= esc($row->no_hp) ?>"
                                        data-kategori="<?= esc($row->kategori) ?>">
                                        <iconify-icon icon="solar:clapperboard-edit-broken" width="20" height="20"></iconify-icon>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger delete-button" data-bs-toggle="modal"
                                        data-bs-target="#delete-pelanggan-modal" data-id_pelanggan="<?= esc($row->id_pelanggan) ?>">
                                        <iconify-icon icon="solar:trash-bin-minimalistic-broken" width="20" height="20"></iconify-icon>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center">Tidak ada data</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top">
            <span class="text-muted">
                Menampilkan <?= ($currentPage - 1) * $perPage + 1 ?> - <?= min($currentPage * $perPage, $total) ?> dari <?= $total ?> pelanggan
            </span>
            <nav aria-label="Page navigation">
                <ul class="pagination mb-0">
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('/pelanggan?page=' . ($currentPage - 1) . ($search ? '&search=' . urlencode($search) : '')) ?>" tabindex="-1">
                            <iconify-icon icon="solar:arrow-left-broken" width="18" height="18"></iconify-icon>
                        </a>
                    </li>
                    <?php
                    $window = 2;
                    $start = max(1, $currentPage - $window);
                    $end = min($totalPages, $currentPage + $window);
                    $showStart = $start > 1;
                    $showEnd = $end < $totalPages;
                    ?>
                    <?php if ($showStart): ?>
                        <li class="page-item"><a class="page-link" href="<?= base_url('/pelanggan?page=1' . ($search ? '&search=' . urlencode($search) : '')) ?>">1</a></li>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="<?= base_url('/pelanggan?page=' . $i . ($search ? '&search=' . urlencode($search) : '')) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($showEnd): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <li class="page-item"><a class="page-link" href="<?= base_url('/pelanggan?page=' . $totalPages . ($search ? '&search=' . urlencode($search) : '')) ?>"><?= $totalPages ?></a></li>
                    <?php endif; ?>
                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('/pelanggan?page=' . ($currentPage + 1) . ($search ? '&search=' . urlencode($search) : '')) ?>">
                            <iconify-icon icon="solar:arrow-right-broken" width="18" height="18"></iconify-icon>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Input Pelanggan -->
<div class="modal fade" id="input-pelanggan-modal" tabindex="-1" aria-labelledby="inputPelangganModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="<?= base_url('insert_pelanggan') ?>" method="post">
                <input type="hidden" name="page" value="<?= esc($currentPage ?? 1) ?>">
                <input type="hidden" name="search" value="<?= esc($search ?? '') ?>">
                <div class="modal-header">
                    <h4 class="modal-title" id="inputPelangganModalLabel">
                        Input Data Pelanggan
                    </h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Nama</label>
                        <input type="text" class="form-control" name="nama" required>
                    </div>
                    <div class="mb-3">
                        <label>Alamat</label>
                        <input type="text" class="form-control" name="alamat" required>
                    </div>

                    <div class="mb-3">
                        <label>Provinsi</label>
                        <select class="form-control select2" name="provinsi" id="provinsi" required>
                            <option value="">-- Pilih Provinsi --</option>
                            <?php foreach ($provinsi as $p): ?>
                                <!-- VALUE = NAME -->
                                <option value="<?= esc($p->name) ?>">
                                    <?= esc($p->name) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Kabupaten</label>
                        <select class="form-control select2" name="kabupaten" id="kabupaten" required>
                            <option value="">-- Pilih Kabupaten --</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Kecamatan</label>
                        <select class="form-control select2" name="kecamatan" id="kecamatan" required>
                            <option value="">-- Pilih Kecamatan --</option>
                        </select>
                    </div>


                    <div class="mb-3">
                        <label>Nomor HP</label>
                        <input type="text" class="form-control" name="no_hp" required>
                    </div>
                    <div class="mb-3">
                        <label>Jenis Pelanggan</label>
                        <select class="form-control" name="kategori" required>
                            <option value="">-- Pilih Jenis Pelanggan --</option>
                            <option value="0">Umum</option>
                            <option value="1">Toko</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Mengetahui Dari</label>
                        <select class="form-control" name="mengetahui_dari" id="mengetahui_dari" required>
                            <option value="">-- Mengetahui Dari --</option>
                            <option value="Iklan di Facebook">Iklan di Facebook</option>
                            <option value="Iklan di Instagram">Iklan di Instagram</option>
                            <option value="Iklan di Tiktok">Iklan di Tiktok</option>
                            <option value="Iklan di Google">Iklan di Google</option>
                            <option value="Teman/Kerabat/Saudara">Teman/Kerabat/Saudara</option>
                            <option value="Store Offline">Store Offline</option>
                            <option value="Pelanggan Lama">Pelanggan lama</option>
                            <option value="Sosial Media Store">Sosial Media Store</option>
                            <option value="Informasi dari Store">Informasi dari Store Lain</option>
                            <option value="Informasi dari Store">Informasi dari Karyawan Store</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                        Close
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Submit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Pelanggan -->
<div class="modal fade" id="edit-pelanggan-modal" tabindex="-1" aria-labelledby="editPelangganModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="<?= base_url('update_pelanggan') ?>" method="post">
                <div class="modal-header">
                    <h4 class="modal-title" id="editPelangganModalLabel">Edit Data Pelanggan</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_pelanggan" id="edit-id_pelanggan">
                    <input type="hidden" name="page" value="<?= esc($currentPage ?? 1) ?>">
                    <input type="hidden" name="search" value="<?= esc($search ?? '') ?>">
                    <div class="mb-3"><label>Nama</label><input type="text" class="form-control" name="nama"
                            id="edit-nama" required></div>
                    <div class="mb-3"><label>Alamat</label><input type="text" class="form-control" name="alamat"
                            id="edit-alamat" required></div>
                    <div class="mb-3">
                        <label>Provinsi</label>
                        <select class="form-control select2" name="provinsi" id="edit-provinsi" required>
                            <option value="">-- Pilih Provinsi --</option>
                            <?php foreach ($provinsi as $p): ?>
                                <option value="<?= esc($p->name) ?>">
                                    <?= esc($p->name) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Kabupaten</label>
                        <select class="form-control select2" name="kabupaten" id="edit-kabupaten" required>
                            <option value="">-- Pilih Kabupaten --</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Kecamatan</label>
                        <select class="form-control select2" name="kecamatan" id="edit-kecamatan" required>
                            <option value="">-- Pilih Kecamatan --</option>
                        </select>
                    </div>

                    <div class="mb-3"><label>Nomor HP</label><input type="text" class="form-control" name="no_hp"
                            id="edit-no_hp" required></div>
                    <div class="mb-3">
                        <label>Jenis Pelanggan</label>
                        <select class="form-control" name="kategori" id="edit-kategori" required>
                            <option value="">-- Pilih Jenis Pelanggan --</option>
                            <option value="0">Umum</option>
                            <option value="1">Toko</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Delete Pelanggan -->
<div class="modal fade" id="delete-pelanggan-modal" tabindex="-1" aria-labelledby="deletePelangganModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="<?= base_url('delete_pelanggan') ?>" method="post">
                <div class="modal-header">
                    <h4 class="modal-title" id="deletePelangganModalLabel">Delete Data Pelanggan</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input id="delete-id_pelanggan" hidden name="id_pelanggan">
                    <input type="hidden" name="page" value="<?= esc($currentPage ?? 1) ?>">
                    <input type="hidden" name="search" value="<?= esc($search ?? '') ?>">
                    <p style="font-style: italic;">Apa anda yakin ingin menghapus data ini?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Import -->
<div class="modal fade" id="samedata-modal" tabindex="-1" aria-labelledby="exampleModalLabel1">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="exampleModalLabel1">Import File Excell</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('import/pelanggan') ?>" enctype="multipart/form-data" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="recipient-name" class="control-label">File:</label>
                        <input type="file" class="form-control" name="file" id="recipient-name1" />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for Edit/Delete Modal -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('#pelanggan_table').addEventListener('click', function(e) {
            if (e.target.closest('.edit-button')) {
                const button = e.target.closest('.edit-button');
                document.getElementById('edit-id_pelanggan').value = button.getAttribute(
                    'data-id_pelanggan');
                document.getElementById('edit-nama').value = button.getAttribute('data-nama');
                document.getElementById('edit-alamat').value = button.getAttribute('data-alamat');
                document.getElementById('edit-nik').value = button.getAttribute('data-nik');
                document.getElementById('edit-no_hp').value = button.getAttribute('data-no_hp');
                document.getElementById('edit-kategori').value = button.getAttribute('data-kategori');
            }
            if (e.target.closest('.delete-button')) {
                const button = e.target.closest('.delete-button');
                document.getElementById('delete-id_pelanggan').value = button.getAttribute(
                    'data-id_pelanggan');
            }
        });
    });
</script>

<script>
    $(document).ready(function() {

        $('#input-pelanggan-modal').on('shown.bs.modal', function() {

            $(this).find('.select2').select2({
                width: '100%',
                dropdownParent: $('#input-pelanggan-modal')
            });

        });

    });
</script>

<script>
    $(document).ready(function() {

        /* ===============================
         * SELECT2 INIT (MODAL SAFE)
         * =============================== */
        $('#input-pelanggan-modal .select2').select2({
            dropdownParent: $('#input-pelanggan-modal'),
            width: '100%'
        });

        $('#edit-pelanggan-modal .select2').select2({
            dropdownParent: $('#edit-pelanggan-modal'),
            width: '100%'
        });

        /* ===============================
         * ADD MODAL CASCADE
         * =============================== */
        $('#provinsi').on('change', function() {
            let provinsi = $(this).val();

            $('#kabupaten').html('<option value="">Loading...</option>').trigger('change');
            $('#kecamatan').html('<option value="">-- Pilih Kecamatan --</option>').trigger('change');

            if (provinsi !== '') {
                $.getJSON("<?= base_url('region/kabupaten') ?>/" + encodeURIComponent(provinsi), function(
                    data) {
                    let opt = '<option value="">-- Pilih Kabupaten --</option>';
                    $.each(data, function(i, v) {
                        opt += `<option value="${v.name}">${v.name}</option>`;
                    });
                    $('#kabupaten').html(opt);
                });
            }
        });

        $('#kabupaten').on('change', function() {
            let kabupaten = $(this).val();

            $('#kecamatan').html('<option value="">Loading...</option>').trigger('change');

            if (kabupaten !== '') {
                $.getJSON("<?= base_url('region/kecamatan') ?>/" + encodeURIComponent(kabupaten), function(
                    data) {
                    let opt = '<option value="">-- Pilih Kecamatan --</option>';
                    $.each(data, function(i, v) {
                        opt += `<option value="${v.name}">${v.name}</option>`;
                    });
                    $('#kecamatan').html(opt);
                });
            }
        });

        /* ===============================
         * EDIT MODAL CASCADE
         * =============================== */
        $('#edit-provinsi').on('change', function() {
            let provinsi = $(this).val();

            $('#edit-kabupaten').html('<option value="">Loading...</option>').trigger('change');
            $('#edit-kecamatan').html('<option value="">-- Pilih Kecamatan --</option>').trigger('change');

            if (provinsi !== '') {
                $.getJSON("<?= base_url('region/kabupaten') ?>/" + encodeURIComponent(provinsi), function(
                    data) {
                    let opt = '<option value="">-- Pilih Kabupaten --</option>';
                    $.each(data, function(i, v) {
                        opt += `<option value="${v.name}">${v.name}</option>`;
                    });
                    $('#edit-kabupaten').html(opt);
                });
            }
        });

        $('#edit-kabupaten').on('change', function() {
            let kabupaten = $(this).val();

            $('#edit-kecamatan').html('<option value="">Loading...</option>').trigger('change');

            if (kabupaten !== '') {
                $.getJSON("<?= base_url('region/kecamatan') ?>/" + encodeURIComponent(kabupaten), function(
                    data) {
                    let opt = '<option value="">-- Pilih Kecamatan --</option>';
                    $.each(data, function(i, v) {
                        opt += `<option value="${v.name}">${v.name}</option>`;
                    });
                    $('#edit-kecamatan').html(opt);
                });
            }
        });

        /* ===============================
         * RESET MODALS
         * =============================== */
        $('#input-pelanggan-modal, #edit-pelanggan-modal').on('hidden.bs.modal', function() {
            $(this).find('select').val('').trigger('change');
        });

    });

    /* ===============================
     * OPEN EDIT MODAL (CALL THIS)
     * =============================== */
    function openEditModal(row) {

        $('#edit-id_pelanggan').val(row.id_pelanggan);
        $('#edit-nama').val(row.nama);
        $('#edit-alamat').val(row.alamat);
        $('#edit-no_hp').val(row.no_hp);
        $('#edit-kategori').val(row.kategori);

        // set provinsi
        $('#edit-provinsi').val(row.provinsi).trigger('change');

        // load kabupaten
        $.getJSON("<?= base_url('region/kabupaten') ?>/" + encodeURIComponent(row.provinsi), function(kab) {

            let opt = '<option value="">-- Pilih Kabupaten --</option>';
            $.each(kab, function(i, v) {
                opt += `<option value="${v.name}">${v.name}</option>`;
            });

            $('#edit-kabupaten').html(opt);
            $('#edit-kabupaten').val(row.kabupaten).trigger('change');

            // load kecamatan
            $.getJSON("<?= base_url('region/kecamatan') ?>/" + encodeURIComponent(row.kabupaten), function(kec) {

                let opt2 = '<option value="">-- Pilih Kecamatan --</option>';
                $.each(kec, function(i, v) {
                    opt2 += `<option value="${v.name}">${v.name}</option>`;
                });

                $('#edit-kecamatan').html(opt2);
                $('#edit-kecamatan').val(row.kecamatan);
            });
        });

        $('#edit-pelanggan-modal').modal('show');
    }
</script>
