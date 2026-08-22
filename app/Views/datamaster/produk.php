<script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js"></script>

<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Datamaster Barang</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Datamaster</a>
                </li>
                <li class="breadcrumb-item" aria-current="page">Barang</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom">

    </div>

    <div class="card-body px-4 pt-4 pb-2 d-flex justify-content-between align-items-start mb-1">
        <div class="d-flex gap-2">
            <a href="<?= base_url('produk/export/produk') ?>" class="btn btn-danger"
                style="display: inline-flex; align-items: center;">
                <iconify-icon icon="solar:export-broken" width="24" height="24" style="margin-right: 8px;">
                </iconify-icon>Export
            </a>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#samedata-modal"
                style="display: inline-flex; align-items: center;">
                <iconify-icon icon="solar:import-broken" width="24" height="24" style="margin-right: 8px;">
                </iconify-icon>
                Import
            </button>
            <a href="<?php echo base_url('format_excell/format_product_terbaru.xlsx') ?>"><button type="button"
                    class="btn btn-success" style="display: inline-flex; align-items: center;">
                    <iconify-icon icon="solar:download-broken" width="24" height="24" style="margin-right: 8px;">
                    </iconify-icon>
                    Download Format Excell
                </button></a>
        </div>
    </div>

    <br>


    <div class="mb-3 px-4">
        <label class="me-2">Filter Kategori:</label>
        <select id="kategoriFilter" class="form-select d-inline" style="width: auto; display: inline-block;"
            onchange="filterSubKategori()">
            <option value="">Semua Kategori</option>
            <?php foreach ($kategori as $row): ?>
                <option data-idkategori="<?= $row->id ?>" value="<?= esc($row->nama_kategori) ?>">
                    <?= esc($row->nama_kategori) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label class="me-2 ms-4">Filter Sub Kategori:</label>
        <select id="subKategoriFilter" class="form-select d-inline" style="width: auto; display: inline-block;"
            onchange="filterKategori()">
            <option value="">Semua Sub Kategori</option>
            <?php foreach ($sub_kategori as $row): ?>
                <?php if (!empty($row->nama_sub_kategori)): ?>
                    <option data-idparent_kategori="<?= $row->id_kategori_parent ?>" value="<?= esc($row->nama_sub_kategori) ?>">
                        <?= esc($row->nama_sub_kategori) ?>
                    </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>




        <label class="me-2 ms-4">Filter PPN:</label>
        <select id="ppnFilter" class="form-select d-inline" style="width: auto; display: inline-block;"
            onchange="filterKategori()">
            <option value="">Semua</option>
            <option value="PPN">PPN</option>
            <option value="Non PPN">Non PPN</option>
        </select>
        <button onclick="resetKategoriFilter()" class="btn btn-sm btn-secondary ms-2">Reset</button>
    </div>





    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
            <thead class="text-dark fs-4">
                <tr>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Kode Barang</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama Barang</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Harga</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Harga Beli</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Warna</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Kategori</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Sub Kategori</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Stok Minim</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Status PPN</h6>
                    </th>

                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Input By</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Action</h6>
                    </th>
                </tr>
            </thead>
            <tbody id="produkTableBody">
                <?php if (!empty($produk)): ?>
                    <?php foreach ($produk as $row): ?>
                        <tr>
                            <td><?= esc($row->kode_barang) ?></td>
                            <td><?= esc($row->nama_barang) ?></td>
                            <td><?= 'Rp ' . number_format($row->harga, 0, ',', '.') ?></td>
                            <td><?= 'Rp ' . number_format($row->harga_beli, 0, ',', '.') ?></td>
                            <td><?= esc($row->warna) ?? 'tidak ada' ?></td>
                            <td><?= esc($row->nama_kategori) ?></td>
                            <td><?= esc($row->nama_sub_kategori) ?? '-' ?></td>
                            <td><?= esc($row->stok_minimum) ?></td>
                            <td><?= $row->status_ppn == 1 ? 'PPN' : 'Non PPN' ?></td>

                            <!-- for push ulang -->

                            <td><?= esc($row->input) ?></td>
                            <td><button type="button" class="btn btn-warning edit-button" data-bs-toggle="modal"
                                    data-bs-target="#edit-produk-modal" data-id_barang="<?= esc($row->idbarang) ?>"
                                    data-kode_barang="<?= esc($row->kode_barang) ?>"
                                    data-nama_barang="<?= esc($row->nama_barang) ?>" data-harga="<?= esc($row->harga) ?>"
                                    data-harga_beli="<?= esc($row->harga_beli) ?>"
                                    data-kategori="<?= esc($row->nama_kategori) ?>" data-sub-kategori="<?= esc($row->nama_sub_kategori) ?>" data-ppn="<?= esc($row->status_ppn) ?>"
                                    data-input_by="<?= esc($row->input) ?>"
                                    data-stok_minim="<?= esc($row->stok_minimum) ?>"
                                    data-warna="<?= esc($row->warna) ?>">
                                    <iconify-icon icon="solar:clapperboard-edit-broken" width="24" height="24"></iconify-icon>
                                </button>
                                <button type="button" class="btn btn-danger delete-button" data-bs-toggle="modal"
                                    data-bs-target="#delete-produk-modal" data-id_barang="<?= esc($row->idbarang) ?>">
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

<!-- //modal -->
<div class="modal fade" id="samedata-modal" tabindex="-1" aria-labelledby="exampleModalLabel1">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="exampleModalLabel1">
                    Import File Excell
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="<?php echo base_url('produk/import/produk') ?>" enctype="multipart/form-data"
                    method="post">
                    <div class="mb-3">
                        <label for="recipient-name" class="control-label">file:</label>
                        <input type="File" class="form-control" name="file" id="recipient-name1" />
                    </div>


            </div>
            <div class="modal-footer">
                <button type="button" class="btn bg-danger-subtle text-danger " data-bs-dismiss="modal">
                    Close
                </button>
                <button type="submit" class="btn btn-success">
                    Submit
                </button>
            </div>
            </form>
        </div>
    </div>
</div>
<!-- /.modal -->

<!-- //modal EditProduk -->
<div class="modal fade" id="edit-produk-modal" tabindex="-1" aria-labelledby="inputProdukModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="inputProdukModalLabel">Edit Data Barang</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('produk/update_produk') ?>" method="post">
                <div class="modal-body">
                    <input type="hidden" name="id_barang" id="id_barang">
                    <input type="hidden" class="form-control" id="kode_barang" name="kode_barang">

                    <div class="mb-3">
                        <label for="nama_barang" class="form-label">Nama Barang</label>
                        <input type="text" class="form-control" id="nama_barang" name="nama_barang" required>
                    </div>

                    <div class="mb-3">
                        <label for="id_kategori" class="form-label">Kategori</label>
                        <select class="form-control" id="id_kategori" name="kategori" required onchange="loadSubKategori(this.value)">
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategori as $k) : ?>
                                <option value="<?= $k->nama_kategori; ?>"><?= $k->nama_kategori; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_id_sub_kategori" class="form-label">Sub Kategori</label>
                        <select class="form-control id_sub_kategori" id="edit_id_sub_kategori" name="sub_kategori">
                            <option value="">-- Pilih Sub Kategori --</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit-harga" class="form-label">Harga PL</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" class="form-control currency" id="edit-harga" name="harga" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit-harga-beli" class="form-label">HPP</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" class="form-control currency" id="edit-harga-beli" name="harga_beli"
                                required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="warna" class="form-label">Warna</label>
                        <input type="text" class="form-control" id="edit-warna" name="warna" required>
                    </div>

                    <div class="mb-3">
                        <label for="stok_minimum" class="form-label">Stok Minim</label>
                        <input type="text" class="form-control" id="stok_minimum" name="stok_minimum" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit-ppn-status" class="form-label">Status PPN</label>
                        <select class="form-control" id="edit-ppn-status" name="status_ppn" required>
                            <option value="">-- Pilih Status PPN --</option>
                            <option value="1">PPN</option>
                            <option value="0">Non PPN</option>
                        </select>
                    </div>

                    <!-- <div class="mb-3">
                        <label for="input_by" class="form-label">Input By</label>
                        <input type="text" class="form-control" id="input_by" name="input_by" readonly>
                    </div> -->
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

<!-- //modal Input Produk -->
<div class="modal fade" id="input-produk-modal" tabindex="-1" aria-labelledby="inputProdukModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="inputProdukModalLabel">Input Data Barang</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('produk/insert_produk') ?>" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nama_barang" class="form-label">Nama Barang</label>
                        <input type="text" class="form-control" id="nama_barang" name="nama_barang" required>
                    </div>

                    <div class="mb-3">
                        <label for="id_kategori" class="form-label">Kategori</label>
                        <select class="form-control" id="id_kategori" name="kategori" required onchange="loadSubKategori(this.value)">
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategori as $k) : ?>
                                <option value="<?= $k->nama_kategori; ?>"><?= $k->nama_kategori; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="id_sub_kategori" class="form-label">Sub Kategori</label>
                        <select class="form-control id_sub_kategori" id="id_sub_kategori" name="sub_kategori">
                            <option value="">-- Pilih Sub Kategori --</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="harga" class="form-label">Harga</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" class="form-control currency" id="harga" name="harga" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="harga_beli" class="form-label">Harga Beli</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" class="form-control currency" id="harga_beli" name="harga_beli" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="warna" class="form-label">Warna</label>
                        <input type="text" class="form-control" id="warna" name="warna" required>
                    </div>

                    <div class="mb-3">
                        <label for="stok_minimum" class="form-label">Stok Minim</label>
                        <input type="text" class="form-control" id="stok_minim" name="stok_minimum" required>
                    </div>

                    <div class="mb-3">
                        <label for="ppn_status" class="form-label">Status PPN</label>
                        <select class="form-control" id="ppn_status" name="status_ppn" required>
                            <option value="">-- Pilih Status PPN --</option>
                            <option value="1">PPN</option>
                            <option value="0">Non PPN</option>
                        </select>
                    </div>


                    <div class="mb-3">
                        <label for="input_by" class="form-label">Input By</label>
                        <input type="text" class="form-control" value="<?= @$akun->NAMA_AKUN ?>" id="input_by"
                            name="input_by" required>
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

<!-- //modal Delete Produk -->
<div class="modal fade" id="delete-produk-modal" tabindex="-1" aria-labelledby="inputProdukModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="inputProdukModalLabel">
                    Delete Data Produk
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo base_url('produk/delete_produk') ?>" method="post">
                <div class="modal-body">

                    <div class="mb-3">
                        <input type="text" id="id_barang_delete" hidden name="id_barang">
                        <p style="font-style: italic;">Apa anda yakin ingin menghapus data ini?</p>
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


<script>
    let table;

    window.onload = function() {
        table = $('#zero_config').DataTable();


        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            const kategoriFilter = $('#kategoriFilter').val().toLowerCase();
            const ppnFilter = $('#ppnFilter').val().toLowerCase();
            const subKategoriFilter = $('#subKategoriFilter').val().toLowerCase();

            const kategori = data[5].toLowerCase();
            const subKategori = data[6].toLowerCase();
            const ppn = data[8].toLowerCase();

            const matchKategori = !kategoriFilter || kategori === kategoriFilter;
            const matchSubKategori = !subKategoriFilter || subKategori === subKategoriFilter;
            const matchPPN = !ppnFilter || ppn === ppnFilter;

            return matchKategori && matchSubKategori && matchPPN;
        });


        // const kategoriSelect = document.getElementById('kategoriFilter');
        // const ppnSelect = document.getElementById('ppnFilter');

        // if (kategoriSelect && kategoriSelect.options.length > 1) {
        //     kategoriSelect.selectedIndex = 1;
        // }
        // if (ppnSelect && ppnSelect.options.length > 1) {
        //     ppnSelect.selectedIndex = 1;
        // }


        table.draw();
    };

    function filterKategori() {
        table.draw();
    }

    function resetKategoriFilter() {
        $('#kategoriFilter').val('');
        $('#ppnFilter').val('');
        $('#subKategoriFilter').val('');
        table.draw();
    }

    // Function to load sub-categories based on selected category
    function loadSubKategori(kategoriNama) {
        const subKategoriSelect = $(".id_sub_kategori");
        if (!subKategoriSelect.length) return; // Check if element exists

        // Clear existing options using jQuery
        subKategoriSelect.html('<option value="">-- Pilih Sub Kategori --</option>');

        if (!kategoriNama) return;

        // Get sub-categories for the selected category
        fetch('<?= base_url('produk/get_sub_kategori') ?>/' + encodeURIComponent(kategoriNama))
            .then(response => response.json())
            .then(data => {
                data.forEach(subKategori => {
                    // Create option using jQuery
                    const option = $('<option>', {
                        value: subKategori.id,
                        text: subKategori.nama_sub_kategori
                    });
                    subKategoriSelect.append(option);
                });
            })
            .catch(error => console.error('Error loading sub-categories:', error));
    }
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('#zero_config').addEventListener('click', function(e) {
            if (e.target.closest('.edit-button')) {
                const button = e.target.closest('.edit-button');

                const id_barang = button.getAttribute('data-id_barang');
                const kode_barang = button.getAttribute('data-kode_barang');
                const nama_barang = button.getAttribute('data-nama_barang');
                const harga = button.getAttribute('data-harga');
                const harga_beli = button.getAttribute('data-harga_beli');
                const warna = button.getAttribute('data-warna');
                const kategori = button.getAttribute('data-kategori');
                const subKategori = button.getAttribute('data-sub-kategori');
                const stok_minimum = button.getAttribute('data-stok_minim');
                const ppn = button.getAttribute('data-ppn');

                const input_by = button.getAttribute('data-input_by');
                const stok_minim = button.getAttribute('data-stok_minim');

                document.getElementById('id_barang').value = id_barang;
                document.getElementById('kode_barang').value = kode_barang;
                document.getElementById('nama_barang').value = nama_barang;
                document.getElementById('edit-harga').value = parseInt(harga.replace(/[^\d]/g, ''));
                document.getElementById('edit-harga-beli').value = parseInt(harga_beli.replace(/[^\d]/g, ''));
                document.getElementById('edit-warna').value = warna;
                document.getElementById('id_kategori').value = kategori;

                // Load sub-categories for the selected category
                loadSubKategori(kategori);

                // Set the sub-category after a short delay to ensure options are loaded
                setTimeout(() => {
                    if (subKategori && subKategori !== '-') {
                        const subKategoriSelect = document.getElementById('edit_id_sub_kategori');
                        if (subKategoriSelect) {
                            // Find the option with matching text
                            for (let option of subKategoriSelect.options) {
                                if (option.textContent === subKategori) {
                                    option.selected = true;
                                    break;
                                }
                            }
                        }
                    }
                }, 500);

                document.getElementById('stok_minimum').value = stok_minimum;
                document.getElementById('edit-ppn-status').value = ppn;
                document.getElementById('stok_minim');
                document.getElementById('input_by').value = input_by;
            }

            if (e.target.closest('.delete-button')) {
                const button = e.target.closest('.delete-button');
                const id_barang = button.getAttribute('data-id_barang');
                document.getElementById('id_barang_delete').value = id_barang;
            }
        });

        // Currency formatting using Cleave.js
        document.querySelectorAll('.currency').forEach(function(el) {
            new Cleave(el, {
                numeral: true,
                numeralThousandsGroupStyle: 'thousand'
            });
        });
    });
</script>

<script>
    function filterSubKategori() {

    const kategoriSelect = document.getElementById('kategoriFilter');
    const subKategoriSelect = document.getElementById('subKategoriFilter');
    const selectedKategori = kategoriSelect.options[kategoriSelect.selectedIndex];
    const idKategori = selectedKategori.getAttribute('data-idkategori');

    for (let option of subKategoriSelect.options) {
        if (option.value === '') continue;
        option.style.display = '';
    }

    if (idKategori) {
        for (let option of subKategoriSelect.options) {
            const idParent = option.getAttribute('data-idparent_kategori');

            if (idParent !== idKategori) {
                option.style.display = 'none';
            }
        }
    }

    subKategoriSelect.value = '';

    table.draw(); // ← ini yang kurang
}
</script>