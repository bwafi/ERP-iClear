<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Data Pegawai</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Riwayat</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Pegawai</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom"></div>

    <div class="card-body px-4 pt-4 pb-2 d-flex justify-content-between align-items-start mb-1">
        <!-- Filter Tanggal -->
        <!-- Filter Unit -->
        <div class="mb-3" style="display: flex; gap: 10px;">
            <label for="filter-unit" class="form-label" style="width: 200px; display: flex; align-items: center;">Filter Unit</label>
            <select id="filter-unit" class="form-control">
                <option value="">-- Semua Unit --</option>
                <?php
                $unitList = [];
                foreach ($akun as $row) {
                    if (!in_array($row->NAMA_UNIT, $unitList)) {
                        $unitList[] = $row->NAMA_UNIT;
                        echo '<option value="' . esc($row->NAMA_UNIT) . '">' . esc($row->NAMA_UNIT) . '</option>';
                    }
                }
                ?>
            </select>

            <label for="filter-status" class="form-label" style="width: 150px; display: flex; align-items: center;">Filter Status</label>
            <select id="filter-status" class="form-control" style="width: 200px;">
                <option value="">-- Semua Status --</option>
                <option value="Aktif">Aktif</option>
                <option value="Tidak Aktif">Tidak Aktif</option>
            </select>


            <button type="button" id="reset-filter" class="btn btn-secondary">Reset</button>
        </div>

        <div class="d-flex gap-2">
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#input-pegawai-modal"
            style="display: inline-flex; align-items: center;">
            <iconify-icon icon="solar:password-minimalistic-input-broken" width="24" height="24"
                style="margin-right: 8px;"></iconify-icon>Input
        </button>
    </div>


    <div class="row px-4 mb-3">
        <div class="table-responsive mb-4 px-4">
            <div id="loadingSpinner" style="display: none;" class="text-center my-3">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
                <thead class="text-dark fs-4">
                    <tr>
                        <th>
                            Status Pegawai
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">No. ID</h6>
                        </th>

                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">No KTP</h6>
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Email</h6>
                        </th>

                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Nama Pegawai</h6>
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Jabatan</h6>
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Unit</h6>
                        </th>
                        <th>
                            Jenis Pegawai
                        </th>
                        <th>
                            Foto KTP
                        </th>
                        <th>
                            Foto KK
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Action</h6>
                        </th>

                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($akun as $row) : ?>
                        <tr>
                            <?php if ($row->STATUS_PEGAWAI == 1): ?>
                                <td>
                                    <button class="btn btn-success" type="button">Aktif</button>
                                </td>
                            <?php else: ?>
                                <td>
                                    <button class="btn btn-success" type="button">Tidak Aktif</button>
                                </td>
                            <?php endif ?>
                            <td><?= esc($row->NOID) ?></td>
                            <td><?= esc($row->KTP) ?></td>
                            <td><?= esc($row->EMAIL) ?></td>
                            <td><?= esc($row->NAMA_AKUN) ?></td>
                            <td><?= esc($row->NAMA_JABATAN) ?></td>
                            <td><?= esc($row->NAMA_UNIT) ?></td>
                            <?php if ($row->JENIS_PEGAWAI === '0' || $row->JENIS_PEGAWAI === 0): ?>
                                <td>Pegawai Tetap</td>
                            <?php elseif ($row->JENIS_PEGAWAI === '1' || $row->JENIS_PEGAWAI === 1): ?>
                                <td>Pegawai Kontrak</td>
                            <?php elseif ($row->JENIS_PEGAWAI === '2' || $row->JENIS_PEGAWAI === 2): ?>
                                <td>Pegawai Probation</td>
                            <?php else: ?>
                                <td>Belum Ditetapkan</td>
                            <?php endif; ?>


                            <td>
                                <?php if (!empty($row->FOTO_KTP)) : ?>
                                    <button type="button" class="btn btn-sm btn-primary preview-btn"
                                        data-img="<?php echo base_url('foto_ktp/' . $row->FOTO_KTP) ?>">
                                        Preview KTP
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-500">Tidak ada</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($row->FOTO_KK)) : ?>
                                    <button type="button" class="btn btn-sm btn-primary preview-btn"
                                        data-img="<?php echo base_url('foto_kk/' . $row->FOTO_KK) ?>">
                                        Preview KK
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-500">Tidak ada</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <button type="button" class="btn btn-warning edit-button"
                                    data-id="<?= $row->ID_AKUN ?>"
                                    data-noid="<?= $row->NOID ?>"
                                    data-ktp="<?= $row->KTP ?>"
                                    data-email="<?= $row->EMAIL ?>"
                                    data-nama="<?= $row->NAMA_AKUN ?>"
                                    data-unit="<?= $row->ID_UNIT ?>"
                                    data-jabatan="<?= $row->ID_JABATAN ?>"
                                    data-hp="<?= $row->HP ?>"
                                    data-jenis_pegawai="<?= $row->JENIS_PEGAWAI ?>"
                                    data-terima_gaji="<?= $row->TERIMA_GAJI ?>"
                                    data-jenis="<?= $row->JENIS_KELAMIN ?>"
                                    data-alamat="<?= $row->ALAMAT ?>"
                                    data-roles="<?= $row->ROLES ?>"
                                    data-foto_ktp_lama="<?= $row->FOTO_KTP ?>"
                                    data-foto_kk_lama="<?= $row->FOTO_KK ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#edit-pegawai-modal">
                                    <iconify-icon icon="solar:clapperboard-edit-broken" width="24" height="24"></iconify-icon>
                                </button>

                                <button type="button" class="btn btn-primary reset-button"
                                    data-id="<?= $row->ID_AKUN ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#reset-pegawai-modal">
                                    <i class="fas fa-lock"></i>
                                </button>


                                <button type="button" class="btn btn-danger delete-button"
                                    data-id="<?= $row->ID_AKUN ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#delete-pegawai-modal">
                                    <i class="fas fa-trash-alt"></i>
                                </button>

                            </td>
                        </tr>


                        <!-- Modal Edit Pegawai -->

        </div>

    <?php endforeach ?>

    </tbody>
    </table>
    </div>
</div>
</div>


<form action="<?= base_url('pegawai/update') ?>" method="post" enctype="multipart/form-data">
    <div class="modal fade" id="edit-pegawai-modal" tabindex="-1" aria-labelledby="inputPeagawaiModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center">
                    <h4 class="modal-title" id="inputPegawaiModalLabel">Update Data Pegawai</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <input type="hidden" name="ID_AKUN" id="id">

                <div class="modal-body row">
                    <div class="mb-3 col-md-6">
                        <label for="noid" class="form-label">No ID Pegawai</label> <span
                            class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="noid" name="noid" required>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="no_ktp" class="form-label">No KTP</label> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="no_ktp" name="no_ktp">
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="email" class="form-label">Email</label> <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="nama" class="form-label">Nama Pegawai</label> <span
                            class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama" name="nama" required>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="unit" class="form-label">Unit<span class="text-danger">*</span></label>
                        <select class="select-unit form-control" name="unit" id="unit-update">
                            <option value="">-- Pilih Unit --</option>
                            <?php foreach ($unit as $val) : ?>
                                <option value="<?= $val->idunit; ?>"><?= $val->NAMA_UNIT; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="jabatan" class="form-label">Jabatan<span class="text-danger">*</span> </label>
                        <select class="select-jabatan form-control" name="jabatan" id="jabatan-update">
                            <option value="">-- Pilih Jabatan --</option>
                            <?php foreach ($jabatan as $val) : ?>
                                <option value="<?= $val->ID_JABATAN; ?>"><?= $val->NAMA_JABATAN; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>


                    <div class="mb-3 col-md-6">
                        <label for="jenis_pegawai" class="form-label">jenis Pegawai<span class="text-danger">*</span> </label>
                        <select class="select-jenis_pegawai form-control" name="jenis_pegawai" id="jenis_pegawai">
                            <option value="0">-- Pegawai Tetap --</option>
                            <option value="1">-- Pegawai Kontrak --</option>
                            <option value="2">-- Pegawai Probation --</option>
                            <option value="100">-- Resign --</option>
                        </select>
                    </div>

                    <div class="mb-3 col-md-6">
                        <label for="terima_gaji" class="form-label">Terima Gaji</label>
                        <select class="form-control" name="terima_gaji" id="terima_gaji-update">
                            <option value="1">Ya</option>
                            <option value="0">Tidak</option>
                        </select>
                    </div>


                    <div class="mb-3 col-md-6">
                        <label for="hp" class="form-label">No.HP</label></label>
                        <input type="number" class="form-control" id="hp" name="hp">
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="hp" class="form-label">Jenis Kelamin</label></label>
                        <div class="custom-control py-2 custom-radio">
                            <input type="radio" id="laki-update" name="jenis_kelamin" value="Laki-Laki"
                                class="form-check-input" />
                            <label class="form-check-label" for="laki-update">Laki - Laki</label>
                        </div>
                        <div class="custom-control py-2 custom-radio">
                            <input type="radio" id="perempuan-update" name="jenis_kelamin" value="Perempuan"
                                class="form-check-input" />
                            <label class="form-check-label" for="perempuan-update">Perempuan</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="alamat" class="form-label">Alamat</label></label>
                        <input type="text" class="form-control" id="alamat" name="alamat">
                    </div>
                    <div class="mb-3">
                        <label for="roles-select" class="form-label">Roles Tambahan (optional)</label>
                        <select class="select2 form-control" multiple="multiple" name="roles[]" id="roles-update">
                            <?php foreach ($roles as $r) : ?>
                                <option value="<?= $r->idmenu; ?>"><?= $r->nama_menu; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>

                <div class="mb-3">
                    <label for="foto_ktp" class="form-label">Foto KTP</label></label>
                    <input type="file" class="form-control" id="foto_ktp" name="foto_ktp">
                </div>
                <div class="mb-3">
                    <label for="foto_kk" class="form-label">Foto KK</label></label>
                    <input type="file" class="form-control" id="foto_kk" name="foto_kk">
                </div>

                <div class="mb-3">
                    <label hidden for="foto_ktp_lama" class="form-label">Foto KTP</label></label>
                    <input hidden type="text" class="form-control" id="foto_ktp_lama" name="foto_ktp_lama">
                </div>
                <div class="mb-3">
                    <label hidden for="foto_kk_lama" class="form-label">Foto KK</label></label>
                    <input hidden type="text" class="form-control" id="foto_kk_lama" name="foto_kk_lama">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>

            </div>


        </div>
    </div>
</form>


<!-- Modal Input Pegawai -->
<div class="modal fade" id="input-pegawai-modal" tabindex="-1" aria-labelledby="inputPeagawaiModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="inputPegawaiModalLabel">Input Data Pegawai</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('pegawai/insert') ?>" method="post" enctype="multipart/form-data">
                <div class="modal-body row">
                    <div class="mb-3 col-md-6">
                        <label for="noid" class="form-label">No ID Pegawai</label> <span
                            class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="noid" name="noid" required>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="no_ktp" class="form-label">No KTP</label> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="no_ktp" name="no_ktp" required>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="email" class="form-label">Email</label> <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="nama" class="form-label">Nama Pegawai</label> <span
                            class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama" name="nama" required>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="unit" class="form-label">Unit<span class="text-danger">*</span></label>
                        <select class="select-unit form-control" name="unit" id="unit">
                            <option value="">-- Pilih Unit --</option>
                            <?php foreach ($unit as $val) : ?>
                                <option value="<?= $val->idunit; ?>"><?= $val->NAMA_UNIT; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="jabatan" class="form-label">Jabatan<span class="text-danger">*</span> </label>
                        <select class="select-jabatan form-control" name="jabatan" id="jabatan">
                            <option value="">-- Pilih Jabatan --</option>
                            <?php foreach ($jabatan as $val) : ?>
                                <option value="<?= $val->ID_JABATAN; ?>"><?= $val->NAMA_JABATAN; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 col-md-6">
                        <label for="jenis_pegawai" class="form-label">jenis Pegawai<span class="text-danger">*</span> </label>
                        <select class="select-jenis_pegawai form-control" name="jenis_pegawai" id="jenis_pegawai">
                            <option value="0">-- Pegawai Tetap --</option>
                            <option value="1">-- Pegawai Kontrak --</option>
                            <option value="2">-- Pegawai Probation --</option>

                        </select>
                    </div>

                    <div class="mb-3 col-md-6">
                        <label for="terima_gaji" class="form-label">Terima Gaji</label>
                        <select class="form-control" name="terima_gaji" id="terima_gaji">
                            <option value="1">Ya</option>
                            <option value="0">Tidak</option>
                        </select>
                    </div>

                    <div class="mb-3 col-md-6">
                        <label for="hp" class="form-label">No.HP</label></label>
                        <input type="number" class="form-control" id="hp" name="hp">
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="hp" class="form-label">Jenis Kelamin</label></label>
                        <div class="custom-control py-2 custom-radio">
                            <input type="radio" id="laki-input" name="jenis_kelamin" value="Laki-Laki"
                                class="form-check-input" />
                            <label class="form-check-label" for="laki-input">Laki - Laki</label>
                        </div>
                        <div class="custom-control py-2 custom-radio">
                            <input type="radio" id="perempuan-input" name="jenis_kelamin" value="Perempuan"
                                class="form-check-input" />
                            <label class="form-check-label" for="perempuan-input">Perempuan</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="alamat" class="form-label">Alamat</label></label>
                        <input type="text" class="form-control" id="alamat" name="alamat">
                    </div>
                    <div class="mb-3">
                        <label for="roles-select" class="form-label">Roles Tambahan (optional)</label>
                        <select class="select2 form-control" multiple="multiple" name="roles[]" id="roles-select">
                            <?php foreach ($roles as $r) : ?>
                                <option value="<?= $r->idmenu; ?>"><?= $r->nama_menu; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="foto_ktp" class="form-label">Foto KTP</label></label>
                        <input type="file" class="form-control" id="foto_ktp" name="foto_ktp">
                    </div>
                    <div class="mb-3">
                        <label for="foto_kk" class="form-label">Foto KK</label></label>
                        <input type="file" class="form-control" id="foto_kk" name="foto_kk">
                    </div>


                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- modal delete pegawai -->
<div class="modal fade" id="delete-pegawai-modal" tabindex="-1" aria-labelledby="deletepegawaiModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="<?= base_url('pegawai/delete') ?>" method="post">
                <div class="modal-header">
                    <h4 class="modal-title" id="deletepegawaiModalLabel">Delete Data pegawai</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input hidden id="delete-id_pegawai" name="ID_AKUN">
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


<!-- modal reset pegawai -->
<div class="modal fade" id="reset-pegawai-modal" tabindex="-1" aria-labelledby="resetpegawaiModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="<?= base_url('pegawai/reset') ?>" method="post">
                <div class="modal-header">
                    <h4 class="modal-title" id="resetpegawaiModalLabel">Reset Password pegawai</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input hidden id="reset-id_pegawai" name="ID_AKUN">
                    <p style="font-style: italic;">Apa anda yakin ingin Reset password pegawai ini?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Modal Preview -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalLabel">Preview Gambar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="previewImage" src="" alt="Preview" class="img-fluid rounded shadow">
            </div>
        </div>
    </div>
</div>







<script>
    $(document).on('click', '.edit-button', function() {
        const button = $(this);

        const id = button.data('id');
        const noid = button.data('noid');
        const ktp = button.data('ktp');
        const email = button.data('email');
        const nama = button.data('nama');
        const unit = button.data('unit');
        const jabatan = button.data('jabatan');
        const hp = button.data('hp');
        const jenis = button.data('jenis');
        const alamat = button.data('alamat');
        const roles = button.data('roles');
        const foto_ktp_lama = button.data('foto_ktp_lama');
        const foto_kk_lama = button.data('foto_kk_lama');
        const jenis_pegawai = button.data('jenis_pegawai');
        const terima_gaji = button.data('terima_gaji');

        $('#id').val(id);
        $('#noid').val(noid);
        $('#no_ktp').val(ktp);
        $('#email').val(email);
        $('#nama').val(nama);
        $('#unit-update').val(unit);
        $('#jabatan-update').val(jabatan);
        $('#hp').val(hp);
        $('#alamat').val(alamat);
        $('#foto_kk_lama').val(foto_kk_lama);
        $('#foto_ktp_lama').val(foto_ktp_lama);
        $('#jenis_pegawai').val(jenis_pegawai).trigger('change');
        $('#terima_gaji-update').val(terima_gaji);


        if (jenis === 'Laki-Laki') {
            $('#laki-update').prop('checked', true);
        } else if (jenis === 'Perempuan') {
            $('#perempuan-update').prop('checked', true);
        }

        // Roles (jika multiple, pisahkan dengan koma)
        if (roles) {
            const roleArray = roles.toString().split(',');
            $('#roles-update').val(roleArray).trigger('change');
        }
    });

    $(document).on('click', '.delete-button', function() {
        const id = $(this).data('id');
        $('#delete-id_pegawai').val(id);
    });

    $(document).on('click', '.reset-button', function() {
        const id = $(this).data('id');
        $('#reset-id_pegawai').val(id);
    });
</script>


<script>
    $(document).ready(function() {
        var table = $('#zero_config').DataTable();

        function escapeRegex(text) {
            return text.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
        }

        function applyFilters() {
            var selectedUnit = $('#filter-unit').val();
            var selectedStatus = $('#filter-status').val();

            // Filter kolom Unit (kolom ke-6)
            if (selectedUnit) {
                var escapedUnit = '^' + escapeRegex(selectedUnit) + '$';
                table.column(6).search(escapedUnit, true, false);
            } else {
                table.column(6).search('');
            }

            // Filter kolom Status (kolom ke-0)
            if (selectedStatus) {
                var escapedStatus = '^' + escapeRegex(selectedStatus) + '$';
                table.column(0).search(escapedStatus, true, false);
            } else {
                table.column(0).search('');
            }

            table.draw();
        }

        $('#filter-unit').on('change', applyFilters);
        $('#filter-status').on('change', applyFilters);

        $('#reset-filter').on('click', function() {
            $('#filter-unit').val('');
            $('#filter-status').val('');
            table.columns().search('').draw();
        });
    });
</script>


<script>
    $(document).ready(function() {
        // Event delegation untuk tombol preview
        $('#zero_config').on('click', '.preview-btn', function() {
            const imgUrl = $(this).data('img');
            $('#previewImage').attr('src', imgUrl);
            $('#previewModal').modal('show');
        });
    });
</script>