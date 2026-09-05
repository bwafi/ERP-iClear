<!-- Breadcrumb -->
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Datamaster Asset</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Datamaster</a>
                </li>
                <li class="breadcrumb-item" aria-current="page">Asset</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Main Card -->
<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom"></div>

    <div class="px-4 py-3 border-bottom d-flex justify-content-between">
        <div class="d-flex gap-2">
            <a href="<?= base_url('export/asset') ?>" class="btn btn-danger"
                style="display: inline-flex; align-items: center;">
                <iconify-icon icon="solar:export-broken" width="24" height="24" style="margin-right: 8px;">
                </iconify-icon>
                Export
            </a>

            <button class="btn btn-warning" style="color: white;" data-bs-toggle="modal" data-bs-target="#asset-terdahulu"> Asset Terhapus </button>

        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#input-asset-modal"
            style="display: inline-flex; align-items: center;">
            <iconify-icon icon="solar:password-minimalistic-input-broken" width="24" height="24"
                style="margin-right: 8px;"></iconify-icon>
            Input
        </button>
    </div>

    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
            <thead class="text-dark fs-4">
                <tr>
                    <th>Asset Code</th>
                    <th>Nama Asset</th>
                    <th>Kategori Asset</th>
                    <th>Tanggal Perolehan</th>
                    <th>Nilai Perolehan</th>

                    <th>Penyusutan Bulanan</th>
                    <th>Jangka Waktu Penyusutan</th>
                    <th>Nilai Sekarang</th>
                    <th>Kondisi</th>
                    <th>Keterangan</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($asset)): ?>
                    <?php foreach ($asset as $row): ?>
                        <tr>
                            <td><?= esc($row->asset_code) ?></td>
                            <td><?= esc($row->asset) ?></td>
                            <td><?= esc($row->nama_kategori_asset) ?></td>
                            <td><?= esc(date('d-m-Y', strtotime($row->tanggal_perolehan))) ?></td>
                            <td> <?= 'Rp ' . number_format($row->nilai_perolehan, 0, ',', '.') ?></td>
                            <td> <?= 'Rp ' . number_format($row->penyusutan_bulanan, 0, ',', '.') ?></td>
                            <td><?= esc(date('d-m-Y', strtotime($row->jangka_waktu))) ?></td>
                            <td> <?= 'Rp ' . number_format($row->nilai_sekarang, 0, ',', '.') ?></td>
                            <td><?= esc($row->kondisi) ?></td>
                            <td><?= esc($row->keterangan) ?></td>

                            <td>
                                <button type="button" class="btn btn-warning edit-button" data-bs-toggle="modal"
                                    data-bs-target="#edit-asset-modal"
                                    data-id_asset="<?= esc($row->idasset) ?>"
                                    data-asset_code="<?= esc($row->asset_code) ?>"
                                    data-asset="<?= esc($row->asset) ?>"
                                    data-tanggal_perolehan="<?= esc($row->tanggal_perolehan) ?>"
                                    data-nilai_perolehan="<?= esc($row->nilai_perolehan) ?>"
                                    data-penyusutan_bulanan="<?= esc($row->penyusutan_bulanan) ?>"
                                    data-nilai_sekarang="<?= esc($row->nilai_sekarang) ?>"
                                    data-kondisi="<?= esc($row->kondisi) ?>"
                                    data-nama_kategori_asset="<?= esc($row->nama_kategori_asset) ?>"
                                    data-jangkawaktu="<?= esc(date('Y-m-d', strtotime($row->jangka_waktu))) ?>"
                                    data-keterangan="<?= esc($row->keterangan) ?>">
                                    <iconify-icon icon="solar:clapperboard-edit-broken" width="24" height="24"></iconify-icon>
                                </button>

                                <button type="button" class="btn btn-danger delete-button" data-bs-toggle="modal"
                                    data-bs-target="#delete-asset-modal" data-id_asset="<?= esc($row->idasset) ?>">
                                    <iconify-icon icon="solar:trash-bin-minimalistic-broken" width="24" height="24">
                                    </iconify-icon>
                                </button>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">Tidak ada data</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Input asset -->
<div class="modal fade" id="input-asset-modal" tabindex="-1" aria-labelledby="inputassetModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="<?= base_url('insert_asset') ?>" method="post">
                <div class="modal-header">
                    <h4 class="modal-title" id="inputassetModalLabel">Input Data Asset</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label>Kode Asset</label>
                        <input type="text" class="form-control" name="asset_code" required>
                    </div>

                    <div class="mb-3">
                        <label>Unit / Cabang Toko</label>
                        <select class="form-control select2" name="unit" required>
                            <option value="">Pilih Unit</option>
                            <?php if (!empty($unit)): foreach ($unit as $u): ?>
                                <option value="<?= $u->idunit ?>"><?= $u->JALAN_UNIT ?? ('Unit ' . $u->idunit) ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Nama Asset</label>
                        <input type="text" class="form-control" name="asset" required>
                    </div>

                    <div class="mb-3">
                        <label>Tanggal Perolehan</label>
                        <input type="date" class="form-control" name="tanggal_perolehan" required>
                    </div>

                    <div class="mb-3">
                        <label for="nilai-perolehan" class="form-label">Nilai Perolehan</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" class="form-control currency" id="nilai-perolehan" name="nilai_perolehan" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="kategori_asset">Kategori</label>
                        <select class="form-control select2" name="kategori_asset" id="kategori_asset" required>
                            <option value="">Pilih Kategori</option>
                            <?php foreach ($kategori_asset as $b): ?>
                                <option value="<?= esc($b->idkategori_asset) ?>">
                                    <?= esc($b->kategori_asset) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    </div>

                    <div class="mb-3">
                        <label>Jangka Waktu</label>
                        <input type="date" class="form-control" name="jangka_waktu" required>
                    </div>


                    <div class="mb-3">
                        <label for="penyusutan-bulanan" class="form-label">Penyusutan Bulanan</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" class="form-control currency" id="penyusutan-bulanan" name="penyusutan_bulanan" required>
                        </div>
                    </div>

                    <!-- <div class="mb-3">
                        <label for="nilai-sekarang" class="form-label">Nilai Sekarang</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" class="form-control currency" id="nilai-sekarang" name="nilai_sekarang" required>
                        </div>
                    </div> -->


                    <div class="mb-3">
                        <label>Kondisi</label>
                        <input type="text" class="form-control" name="kondisi" required>
                    </div>

                    <div class="mb-3">
                        <label>Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="3"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Modal Edit asset -->
<div class="modal fade" id="edit-asset-modal" tabindex="-1" aria-labelledby="editassetModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="<?= base_url('update_asset') ?>" method="post">
                <div class="modal-header">
                    <h4 class="modal-title" id="editassetModalLabel">Edit Data Asset</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <input hidden name="id_asset" id="edit-id_asset">

                    <div class="mb-3">
                        <label>Kode Asset</label>
                        <input readonly type="text" class="form-control" name="asset_code" id="edit-asset_code" required>
                    </div>

                    <div class="mb-3">
                        <label>Nama Asset</label>
                        <input readonly type="text" class="form-control" name="asset" id="edit-asset" required>
                    </div>

                    <div class="mb-3">
                        <label>Nama Kategori</label>
                        <input readonly type="text" class="form-control" name="nama_kategori_asset" id="edit-nama_kategori_asset" required>
                    </div>

                    <div class="mb-3">
                        <label>Tanggal Perolehan</label>
                        <input readonly type="date" class="form-control" name="tanggal_perolehan" id="edit-tanggal_perolehan" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit-nilai_perolehan" class="form-label">Nilai Perolehan</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input readonly type="text" class="form-control currency" id="edit-nilai_perolehan" name="nilai_perolehan" required>
                        </div>
                    </div>



                    <div class="mb-3">
                        <label for="edit-nilai_sekarang" class="form-label">Nilai Sekarang</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input readonly type="text" class="form-control currency" id="edit-nilai_sekarang" name="nilai_sekarang" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit-penyusutan_bulanan" class="form-label">Penyusutan Bulanan</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" class="form-control currency" id="edit-penyusutan_bulanan" name="penyusutan_bulanan" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Jangka Waktu</label>
                        <input type="date" class="form-control" name="jangka_waktu" id="edit-jangka_waktu" required>
                    </div>


                    <div class="mb-3">
                        <label>Kondisi</label>
                        <input type="text" class="form-control" name="kondisi" id="edit-kondisi" required>
                    </div>

                    <div class="mb-3">
                        <label>Keterangan</label>
                        <textarea class="form-control" name="keterangan" id="edit-keterangan" rows="3"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Modal Delete asset -->
<div class="modal fade" id="delete-asset-modal" tabindex="-1" aria-labelledby="deleteassetModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="<?= base_url('delete_asset') ?>" method="post">
                <div class="modal-header">
                    <h4 class="modal-title" id="deleteassetModalLabel">Delete Data Asset</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="delete-id_asset" name="id_asset">
                    <p style="font-style: italic;">Apa Anda yakin ingin menghapus data ini?</p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">Close</button>
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
            <form action="<?= base_url('import/asset') ?>" enctype="multipart/form-data" method="post">
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




<!-- Modal -->
<div class="modal fade" id="asset-terdahulu" tabindex="-1" aria-labelledby="assetTerdahuluLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg"> <!-- modal-lg biar lebar -->
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="assetTerdahuluLabel">Daftar Asset Terhapus</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($asset_lama)) : ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="tabel_terhapus">
                            <thead>
                                <tr>
                                    <th>Kode Asset</th>
                                    <th>Nama Asset</th>
                                    <th>Kategori Asset</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($asset_lama as $row): ?>
                                    <tr>
                                        <td><?= esc($row->asset_code) ?></td>
                                        <td><?= esc($row->asset) ?></td>
                                        <td><?= esc($row->nama_kategori_asset) ?></td>
                                        <td><a href="<?php echo base_url('pulihkan_asset/' . $row->idasset) ?>" class="btn btn-sm btn-success">Pulihkan</
                                                    <button class="btn btn-success">Pulihkan</button>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <p class="text-center text-muted">Tidak ada asset terhapus.</p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>


<!-- JavaScript for Edit/Delete Modal -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('#zero_config').addEventListener('click', function(e) {
            if (e.target.closest('.edit-button')) {
                const button = e.target.closest('.edit-button');
                document.getElementById('edit-id_asset').value = button.getAttribute('data-id_asset');
                document.getElementById('edit-asset_code').value = button.getAttribute('data-asset_code');
                document.getElementById('edit-asset').value = button.getAttribute('data-asset');
                document.getElementById('edit-nama_kategori_asset').value = button.getAttribute('data-nama_kategori_asset');
                document.getElementById('edit-tanggal_perolehan').value = button.getAttribute('data-tanggal_perolehan');
                document.getElementById('edit-nilai_perolehan').value = button.getAttribute('data-nilai_perolehan');
                document.getElementById('edit-penyusutan_bulanan').value = button.getAttribute('data-penyusutan_bulanan');
                document.getElementById('edit-nilai_sekarang').value = button.getAttribute('data-nilai_sekarang');
                document.getElementById('edit-jangka_waktu').value = button.getAttribute('data-jangkawaktu');
                document.getElementById('edit-kondisi').value = button.getAttribute('data-kondisi');
                document.getElementById('edit-keterangan').value = button.getAttribute('data-keterangan');

            }
            if (e.target.closest('.delete-button')) {
                const button = e.target.closest('.delete-button');
                document.getElementById('delete-id_asset').value = button.getAttribute(
                    'data-id_asset');
            }



        });
        // Currency formatting using Cleave.js
        document.querySelectorAll('.currency').forEach(function(el) {
            new Cleave(el, {
                numeral: true,
                numeralThousandsGroupStyle: 'thousand',
                delimiter: '.',
                numeralDecimalMark: ','
            });
        });

    });
</script>

<script>
    $(document).ready(function() {
        $('#kategori_asset').select2({
            dropdownParent: $('#input-asset-modal')
        });

        table = $('#tabel_terhapus').DataTable();

    });
</script>