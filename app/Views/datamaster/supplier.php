<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Datamaster Supplier</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Datamaster</a>
                </li>
                <li class="breadcrumb-item" aria-current="page">Supplier</li>
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

    <div hidden class="mb-3" style="margin-left: 20px;">
        <label for="filterUnit">Filter Unit:</label>
        <select id="filterUnit" class="form-control d-inline-block w-auto" style="margin-right: 10px;">
            <option value="">Semua Unit</option>
            <?php foreach ($unit as $u): ?>
            <option value="<?= esc($u->NAMA_UNIT) ?>" <?= ($u->idunit == session('ID_UNIT')) ? 'selected' : '' ?>>
                <?= esc($u->NAMA_UNIT) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button id="resetFilter" class="btn btn-secondary">Reset</button>
    </div>

    <div class="mb-3" style="margin-left: 20px;">
        <label for="filterUnitxx"> Unit:</label>
        <select disabled id="filterUnitxx" class="form-control d-inline-block w-auto" style="margin-right: 10px;">
            <option value="">Semua Unit</option>
            <?php foreach ($unit as $u): ?>
            <option value="<?= esc($u->NAMA_UNIT) ?>" <?= ($u->idunit == session('ID_UNIT')) ? 'selected' : '' ?>>
                <?= esc($u->NAMA_UNIT) ?>
            </option>
            <?php endforeach; ?>
        </select>

    </div>


    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
            <thead class="text-dark fs-4">
                <tr>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama Supplier</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Alamat</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nomer HP</h6>
                    </th>
                    <th>Unit</th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Action</h6>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($suplier)): ?>
                <?php foreach ($suplier as $row): ?>
                <tr>
                    <td><?= esc($row->nama_suplier) ?></td>
                    <td><?= esc($row->alamat) ?></td>
                    <td><?= esc($row->no_hp) ?></td>
                    <td><?= $row->nama_unit ?></td>
                    <td>
                        <button type="button" class="btn btn-warning edit-button" data-bs-toggle="modal"
                            data-bs-target="#edit-produk-modal" data-id_suplier="<?= esc($row->id_suplier) ?>"
                            data-nama_suplier="<?= esc($row->nama_suplier) ?>"
                            data-alamat="<?= esc($row->alamat, 'attr') ?>"
                            data-no-hp="<?= esc($row->no_hp, 'attr') ?>" aria-label="Edit supplier">
                            <iconify-icon icon="solar:clapperboard-edit-broken" width="24" height="24"></iconify-icon>
                        </button>
                        <button type="button" class="btn btn-danger delete-button" data-bs-toggle="modal"
                            data-bs-target="#delete-produk-modal" data-id_suplier="<?= esc($row->id_suplier) ?>" aria-label="Hapus supplier">
                            <iconify-icon icon="solar:trash-bin-minimalistic-broken" width="24" height="24">
                            </iconify-icon>
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
                    Edit Data Suplier
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('update_suplier') ?>" method="post">
                <div class="modal-body">
                    <input type="hidden" id="edit_id_suplier" name="id_suplier">
                    <div class="mb-3">
                        <label for="edit_nama_suplier" class="form-label">Nama Suplier</label>
                        <input type="text" class="form-control" id="edit_nama_suplier" name="nama_suplier" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_alamat_suplier" class="form-label">Alamat</label>
                        <input type="text" class="form-control" id="edit_alamat_suplier" name="alamat" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_hp_suplier" class="form-label">Nomer HP</label>
                        <input type="text" class="form-control" id="edit_hp_suplier" name="no_hp" required>
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
                    Input Data Suplier
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('insert_suplier') ?>" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_nama_suplier" class="form-label">Nama Suplier</label>
                        <input type="text" class="form-control" id="edit_nama_suplier" name="nama_suplier" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_alamat_suplier" class="form-label">Alamat</label>
                        <input type="text" class="form-control" id="edit_alamat_suplier" name="alamat" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_hp_suplier" class="form-label">Nomer HP</label>
                        <input type="text" class="form-control" id="edit_hp_suplier" name="no_hp" required>
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
                    Delete Data Suplier
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('delete_suplier') ?>" method="post">
                <div class="modal-body">
                    <input type="hidden" id="delete_id_suplier" name="id_suplier">
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
document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('#zero_config').addEventListener('click', function(e) {
        if (e.target.closest('.edit-button')) {
            const button = e.target.closest('.edit-button');
            const modal = document.getElementById('edit-produk-modal');
            modal.querySelector('#edit_id_suplier').value = button.getAttribute('data-id_suplier');
            modal.querySelector('[name="nama_suplier"]').value = button.getAttribute('data-nama_suplier');
            modal.querySelector('[name="alamat"]').value = button.getAttribute('data-alamat') || '';
            modal.querySelector('[name="no_hp"]').value = button.getAttribute('data-no-hp') || '';
        }
        if (e.target.closest('.delete-button')) {
            const button = e.target.closest('.delete-button');
            document.getElementById('delete_id_suplier').value = button.getAttribute('data-id_suplier');
        }
    });
});
</script>

<script>
let table;

document.addEventListener('DOMContentLoaded', function() {
    // Cegah reinitialisasi
    if (!$.fn.DataTable.isDataTable('#zero_config')) {
        table = $('#zero_config').DataTable({
            pageLength: 10,
        });
    } else {
        table = $('#zero_config').DataTable();
    }

    // Tambahkan custom filter berdasarkan nama_unit
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        const selectedUnit = $('#filterUnit').val().toLowerCase();
        const namaUnit = (data[3] || '').toLowerCase(); // kolom ke-4 (index 3)

        return selectedUnit === '' || namaUnit === selectedUnit;
    });

    // Trigger filter saat dropdown berubah
    $('#filterUnit').on('change', function() {
        table.draw();
    });

    // Tombol reset filter
    $('#resetFilter').on('click', function() {
        $('#filterUnit').val('');
        table.draw();
    });

    // Jalankan filter default sesuai session
    const defaultUnit = $('#filterUnit').val();
    if (defaultUnit) {
        table.draw();
    }
});
</script>
