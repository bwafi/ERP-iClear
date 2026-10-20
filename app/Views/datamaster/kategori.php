<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Datamaster Kategori</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Datamaster</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Kategori</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom">
    </div>

    <!-- <div class="card-body px-4 pt-4 pb-2 d-flex justify-content-between align-items-start mb-1">
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#input-kategori-modal"
                style="display: inline-flex; align-items: center;">
                <iconify-icon icon="solar:password-minimalistic-input-broken" width="24" height="24"
                    style="margin-right: 8px;"></iconify-icon>Tambah Kategori
            </button>
        </div>
    </div> -->

    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" id="ketegori_induk_table">
            <thead class="text-dark fs-4">
                <tr>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">ID Kategori</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama Kategori</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Sub Kategori</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Action</h6>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($kategori)): ?>
                    <?php foreach ($kategori as $row): ?>
                        <!-- Main Category Row -->
                        <tr class="category-row" data-category-id="<?= esc($row->id) ?>">
                            <td><?= esc($row->idkategori) ?></td>
                            <td>
                                <strong><?= esc($row->nama_kategori) ?></strong>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary toggle-subcategories"
                                    data-category-id="<?= esc($row->id) ?>">
                                    <iconify-icon icon="solar:alt-arrow-down-linear" width="16" height="16"></iconify-icon>
                                    Lihat Sub Kategori (<?= count($row->sub_kategori) ?>)
                                </button>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-success add-sub-kategori" data-bs-toggle="modal"
                                    data-bs-target="#input-sub-kategori-modal" data-category-id="<?= esc($row->id) ?>"
                                    data-category-name="<?= esc($row->nama_kategori) ?>">
                                    <iconify-icon icon="solar:add-circle-broken" width="16" height="16"></iconify-icon>
                                </button>
                                <!-- <button type="button" class="btn btn-sm btn-warning edit-button" data-bs-toggle="modal"
                                    data-bs-target="#edit-kategori-modal" data-id="<?= esc($row->id) ?>"
                                    data-id_kategori="<?= esc($row->idkategori) ?>"
                                    data-nama_kategori="<?= esc($row->nama_kategori) ?>">
                                    <iconify-icon icon="solar:clapperboard-edit-broken" width="16" height="16"></iconify-icon>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger delete-button" data-bs-toggle="modal"
                                    data-bs-target="#delete-kategori-modal" data-id="<?= esc($row->id) ?>">
                                    <iconify-icon icon="solar:trash-bin-minimalistic-broken" width="16" height="16"></iconify-icon>
                                </button> -->
                            </td>
                        </tr>

                        <!-- Sub Categories Accordion Row -->
                        <tr class="sub-category-accordion" data-category-id="<?= esc($row->id) ?>" style="display: none;">
                            <td colspan="4">
                                <div class="accordion-content" style="padding: 15px; background-color: #f8f9fa; border-left: 4px solid #007bff;">
                                    <h6 class="mb-3">
                                        <iconify-icon icon="solar:folder-open-broken" width="20" height="20"></iconify-icon>
                                        Sub Kategori untuk "<?= esc($row->nama_kategori) ?>"
                                    </h6>

                                    <?php if (!empty($row->sub_kategori)): ?>
                                        <div class="row">
                                            <?php foreach ($row->sub_kategori as $sub): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="card border-0 shadow-sm">
                                                        <div class="card-body p-3">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <h6 class="mb-1">
                                                                        <iconify-icon icon="solar:arrow-right-linear" width="16" height="16"></iconify-icon>
                                                                        <?= esc($sub->nama_sub_kategori) ?>
                                                                    </h6>
                                                                    <small class="text-muted">ID: <?= esc($sub->id) ?></small>
                                                                </div>
                                                                <div class="btn-group btn-group-sm">
                                                                    <button type="button" class="btn btn-warning edit-sub-button" data-bs-toggle="modal"
                                                                        data-bs-target="#edit-sub-kategori-modal" data-id="<?= esc($sub->id) ?>"
                                                                        data-id_sub_kategori="<?= esc($sub->id) ?>"
                                                                        data-nama_sub_kategori="<?= esc($sub->nama_sub_kategori) ?>">
                                                                        <iconify-icon icon="solar:clapperboard-edit-broken" width="14" height="14"></iconify-icon>
                                                                    </button>
                                                                    <button type="button" class="btn btn-danger delete-sub-button" data-bs-toggle="modal"
                                                                        data-bs-target="#delete-sub-kategori-modal" data-id="<?= esc($sub->id) ?>">
                                                                        <iconify-icon icon="solar:trash-bin-minimalistic-broken" width="14" height="14"></iconify-icon>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <iconify-icon icon="solar:folder-open-broken" width="48" height="48" class="text-muted"></iconify-icon>
                                            <p class="text-muted mt-2">Belum ada sub kategori</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
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

<!-- Modal Edit Kategori -->
<div class="modal fade" id="edit-kategori-modal" tabindex="-1" aria-labelledby="editKategoriModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="editKategoriModalLabel">Edit Data Kategori</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('update_kategori') ?>" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <input hidden type="text" class="form-control" id="edit_id" name="idnya" required>
                        <label for="edit_nama_kategori" class="form-label">Nama Kategori</label>
                        <input type="text" class="form-control" id="edit_nama_kategori" name="nama_kategori" required>
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

<!-- Modal Input Kategori -->
<div class="modal fade" id="input-kategori-modal" tabindex="-1" aria-labelledby="inputKategoriModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="inputKategoriModalLabel">Input Data Kategori</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('insert_kategori') ?>" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="idkategori" class="form-label">ID Kategori</label>
                        <input type="text" class="form-control" id="idkategori" name="idkategori" required>
                    </div>
                    <div class="mb-3">
                        <label for="nama_kategori" class="form-label">Nama Kategori</label>
                        <input type="text" class="form-control" id="nama_kategori" name="nama_kategori" required>
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

<!-- Modal Delete Kategori -->
<div class="modal fade" id="delete-kategori-modal" tabindex="-1" aria-labelledby="deleteKategoriModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="deleteKategoriModalLabel">Delete Data Kategori</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('delete_kategori') ?>" method="post">
                <div class="modal-body">
                    <input hidden id="delete_id" name="idnya">
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

<!-- Modal Input Sub Kategori -->
<div class="modal fade" id="input-sub-kategori-modal" tabindex="-1" aria-labelledby="inputSubKategoriModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="inputSubKategoriModalLabel">Input Sub Kategori</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('kategori/insert_sub_kategori') ?>" method="post">
                <div class="modal-body">
                    <input hidden type="text" id="sub_kategori_parent_id" name="id_kategori_parent">
                    <div class="mb-3">
                        <label for="nama_sub_kategori" class="form-label">Nama Sub Kategori</label>
                        <input type="text" class="form-control" id="nama_sub_kategori" name="nama_sub_kategori" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori Induk</label>
                        <input type="text" class="form-control" id="parent_category_name" readonly>
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

<!-- Modal Edit Sub Kategori -->
<div class="modal fade" id="edit-sub-kategori-modal" tabindex="-1" aria-labelledby="editSubKategoriModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="editSubKategoriModalLabel">Edit Sub Kategori</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('kategori/update_sub_kategori') ?>" enctype="multipart/form-data" method="post">
                <div class="modal-body">
                    <input hidden type="text" class="form-control" id="edit_sub_id" name="idnya" required>
                    <div class="mb-3">
                        <label for="edit_nama_sub_kategori" class="form-label">Nama Sub Kategori</label>
                        <input type="text" class="form-control" id="edit_nama_sub_kategori" name="nama_sub_kategori" required>
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

<!-- Modal Delete Sub Kategori -->
<div class="modal fade" id="delete-sub-kategori-modal" tabindex="-1" aria-labelledby="deleteSubKategoriModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="deleteSubKategoriModalLabel">Delete Sub Kategori</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('kategori/delete_sub_kategori') ?>" method="post">
                <div class="modal-body">
                    <input hidden id="delete_sub_id" name="idnya">
                    <p style="font-style: italic;">Apa anda yakin ingin menghapus sub kategori ini?</p>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle sub-categories accordion visibility
        document.querySelectorAll('.toggle-subcategories').forEach(button => {
            button.addEventListener('click', function() {
                const categoryId = this.getAttribute('data-category-id');
                const accordionRow = document.querySelector(`.sub-category-accordion[data-category-id="${categoryId}"]`);
                const icon = this.querySelector('iconify-icon');

                if (accordionRow.style.display === 'none') {
                    accordionRow.style.display = '';
                    icon.setAttribute('icon', 'solar:alt-arrow-up-linear');
                    this.innerHTML = '<iconify-icon icon="solar:alt-arrow-up-linear" width="16" height="16"></iconify-icon> Sembunyikan Sub Kategori';
                } else {
                    accordionRow.style.display = 'none';
                    icon.setAttribute('icon', 'solar:alt-arrow-down-linear');
                    this.innerHTML = '<iconify-icon icon="solar:alt-arrow-down-linear" width="16" height="16"></iconify-icon> Lihat Sub Kategori';
                }
            });
        });

        // Handle main category edit
        document.querySelector('#ketegori_induk_table').addEventListener('click', function(e) {
            if (e.target.closest('.edit-button')) {
                const button = e.target.closest('.edit-button');
                const id = button.getAttribute('data-id');
                const nama_kategori = button.getAttribute('data-nama_kategori');

                document.getElementById('edit_id').value = id;
                document.getElementById('edit_nama_kategori').value = nama_kategori;
            }

            if (e.target.closest('.delete-button')) {
                const button = e.target.closest('.delete-button');
                const id = button.getAttribute('data-id');
                document.getElementById('delete_id').value = id;
            }

            // Handle add sub-category
            if (e.target.closest('.add-sub-kategori')) {
                const button = e.target.closest('.add-sub-kategori');
                const categoryId = button.getAttribute('data-category-id');
                const categoryName = button.getAttribute('data-category-name');

                document.getElementById('sub_kategori_parent_id').value = categoryId;
                document.getElementById('parent_category_name').value = categoryName;
            }

            // Handle edit sub-category
            if (e.target.closest('.edit-sub-button')) {
                const button = e.target.closest('.edit-sub-button');
                const id = button.getAttribute('data-id');
                const nama_sub_kategori = button.getAttribute('data-nama_sub_kategori');

                document.getElementById('edit_sub_id').value = id;
                document.getElementById('edit_nama_sub_kategori').value = nama_sub_kategori;
            }

            // Handle delete sub-category
            if (e.target.closest('.delete-sub-button')) {
                const button = e.target.closest('.delete-sub-button');
                const id = button.getAttribute('data-id');
                document.getElementById('delete_sub_id').value = id;
            }
        });
    });
</script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Event listener untuk semua tombol dengan class .add-sub-kategori
        const buttons = document.querySelectorAll('.add-sub-kategori');

        buttons.forEach(button => {
            button.addEventListener('click', function() {
                // Ambil data dari atribut data-*
                const categoryId = this.getAttribute('data-category-id');
                const categoryName = this.getAttribute('data-category-name');

                // Masukkan data ke input dalam modal
                document.getElementById('sub_kategori_parent_id').value = categoryId;
                document.getElementById('parent_category_name').value = categoryName;
            });
        });
    });
</script>

<script>
    $(document).ready(function() {
        // Event ketika tombol edit diklik
        $(document).on('click', '.edit-sub-button', function() {
            // Ambil data dari atribut data-*
            const id = $(this).data('id');
            const namaSub = $(this).data('nama_sub_kategori');

            // Masukkan ke field di dalam modal
            $('#edit_sub_id').val(id);
            $('#edit_nama_sub_kategori').val(namaSub);
        });
    });
</script>

<script>
    $(document).ready(function() {
        $(document).on('click', '.delete-sub-button', function() {
            const id = $(this).data('id');
            $('#delete_sub_id').val(id);
        });
    });
</script>
