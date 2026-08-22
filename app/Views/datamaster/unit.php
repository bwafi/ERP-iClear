<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Datamaster Unit</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Datamaster</a>
                </li>
                <li class="breadcrumb-item" aria-current="page">Unit</li>
            </ol>
        </nav>
    </div>
</div>




<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom"></div>

    <div class="card-body px-4 pt-4 pb-2 d-flex justify-content-between align-items-start mb-1">
        <div class="d-flex gap-2"></div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#input-produk-modal"
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
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">ID Unit</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama Unit</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Alamat</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nomer HP</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">IG</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Jenis Kepemilikan</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Tanggungan</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Action</h6>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($data)): ?>
                <?php foreach($data as $row): ?>
                <tr>
                    <td><?= esc($row->idunit) ?></td>
                    <td><?= esc($row->NAMA_UNIT) ?></td>
                    <td><?= esc($row->JALAN_UNIT) ?></td>
                    <td><?= esc($row->NOTELP) ?></td>
                    <td><?= esc($row->KELURAHAN_UNIT) ?></td>
                    <td><?= esc($row->jenis) ?></td>
                    <td><?= esc($row->tanggungan) ?></td>

                    <td>
                        <button class="btn btn-warning edit-button"
                            data-bs-toggle="modal"
                            data-bs-target="#edit-produk-modal"

                            data-id="<?= $row->idunit ?>"
                            data-nama="<?= $row->NAMA_UNIT ?>"
                            data-jalan="<?= $row->JALAN_UNIT ?>"
                            data-telp="<?= $row->NOTELP ?>"
                            data-kelurahan="<?= $row->KELURAHAN_UNIT ?>"
                            data-tanggungan="<?= $row->tanggungan ?>"
                            data-jenis="<?= $row->jenis ?>">
                            Edit
                        </button>

                        <button class="btn btn-danger delete-button"
                            data-bs-toggle="modal"
                            data-bs-target="#delete-produk-modal"
                            data-id="<?= $row->idunit ?>">
                            Hapus
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="4" class="text-center">Tidak ada data</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal EditProduk -->
<div class="modal fade" id="edit-produk-modal" tabindex="-1" aria-labelledby="editProdukModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="editProdukModalLabel">
                    Edit Data Unit
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('update_unit') ?>" method="post">
                <div class="modal-body">
                    <input type="hidden" class="form-control" id="edit_id_unit" name="id_unit">
                    <div class="mb-3">
                        <label for="edit_nama_suplier" class="form-label">Nama Unit</label>
                        <input type="text" class="form-control" id="edit_nama_unit" name="nama_unit">
                    </div>
                    <div class="mb-3">
                        <label for="edit_nama_suplier" class="form-label">Alamat Unit</label>
                        <input type="text" class="form-control" id="edit_jalan_unit" name="alamat_unit">
                    </div>
                    <div class="mb-3">
                        <label for="edit_hp_suplier" class="form-label">Nomer HP</label>
                        <input type="text" class="form-control" id="edit_notelp" name="notelp">
                    </div>
                    <div class="mb-3">
                        <label for="edit_hp_suplier" class="form-label">IG</label>
                        <input type="text" class="form-control" id="edit_kelurahan_unit" name="kelurahan_unit">
                    </div>
                    <div class="mb-3">
                        <label for="edit_hp_suplier" class="form-label">Jenis Kepemlikan</label>
                        <select name="kepemilikan" class="form-select" id="edit_kepemilikan">
                            <option value="pribadi">Pribadi</option>
                            <option value="franchise">Franchise</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_hp_suplier" class="form-label">Tanggungan</label>
                        <input type="number" class="form-control" id="edit_tanggungan" name="tanggungan">
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


<!-- Modal Input Produk -->
<div class="modal fade" id="input-produk-modal" tabindex="-1" aria-labelledby="inputProdukModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="inputProdukModalLabel">
                    Input Data Unit
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('insert_unit') ?>" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_nama_suplier" class="form-label">Nama Unit</label>
                        <input type="text" class="form-control" id="edit_nama_unit" name="nama_unit">
                    </div>
                    <div class="mb-3">
                        <label for="edit_nama_suplier" class="form-label">Alamat Unit</label>
                        <input type="text" class="form-control" id="edit_jalan_unit" name="alamat_unit">
                    </div>    
                    <div class="mb-3">
                        <label for="edit_hp_suplier" class="form-label">Nomer HP</label>
                        <input type="text" class="form-control" id="edit_notelp" name="notelp">
                    </div>
                    <div class="mb-3">
                        <label for="edit_hp_suplier" class="form-label">IG</label>
                        <input type="text" class="form-control" id="edit_kelurahan_unit" name="kelurahan_unit">
                    </div>
                    <div class="mb-3">
                        <label for="edit_hp_suplier" class="form-label">Jenis Kepemlikan</label>
                        <select name="kepemilikan" class="form-select" id="edit_kepemilikan">
                            <option value="pribadi">Pribadi</option>
                            <option value="franchise">Franchise</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_hp_suplier" class="form-label">Tanggungan</label>
                        <input type="number" class="form-control" id="edit_tanggungan" name="tanggungan">
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




<!-- /.modal -->

<!-- Modal Delete Produk -->
<div class="modal fade" id="delete-produk-modal" tabindex="-1" aria-labelledby="deleteProdukModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="deleteProdukModalLabel">
                    Delete Data Unit
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('delete_unit') ?>" method="post">
                <div class="modal-body">
                    <input type="hidden" id="delete_id_unit" name="id_unit">
                    <p style="font-style: italic;">Apa anda yakin ingin menghapus data ini?</p>
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

<!-- Script for handling theme -->
<script>
function handleColorTheme(e) {
    $("html").attr("data-color-theme", e);
    $(e).prop("checked", true);
}
</script>

<!-- Script for handling modal data -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    document.querySelector('#zero_config').addEventListener('click', function(e){

        if(e.target.closest('.edit-button')){

            let b = e.target.closest('.edit-button');

            document.getElementById('edit_id_unit').value = b.dataset.id;
            document.getElementById('edit_nama_unit').value = b.dataset.nama;
            document.getElementById('edit_jalan_unit').value = b.dataset.jalan;
            document.getElementById('edit_notelp').value = b.dataset.telp;
            document.getElementById('edit_kelurahan_unit').value = b.dataset.kelurahan;
            document.getElementById('edit_kepemilikan').value = b.dataset.jenis;
            document.getElementById('edit_tanggungan').value = b.dataset.tanggungan;
        }

        if(e.target.closest('.delete-button')){

            let b = e.target.closest('.delete-button');

            document.getElementById('delete_id_unit').value = b.dataset.id;
        }

    });

});
</script>