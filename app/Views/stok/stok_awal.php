<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Datamaster Stok Awal</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Datamaster</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Stok Awal</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom d-flex justify-content-end">
        <a href="<?php echo base_url('input_stokawal') ?>">
            <button type="button" class="btn btn-primary" style="display: inline-flex; align-items: center;">
                <iconify-icon icon="solar:password-minimalistic-input-broken" width="24" height="24"
                    style="margin-right: 8px;"></iconify-icon>
                Input Barang
            </button>
    </a>
</div>

    <!-- data-bs-toggle="modal" data-bs-target="#input-stokawal-modal" -->

    <br>
    <div class="mb-3 px-4">
        <label class="me-2">Filter Kategori:</label>
        <select id="kategoriFilter" class="form-select d-inline" style="width: auto;" onchange="filterKategori()">
            <option value="">Semua Kategori</option>
            <?php
            $kategoriList = [];
            foreach ($stok as $row) {
                if (!in_array($row->nama_kategori, $kategoriList)) {
                    $kategoriList[] = $row->nama_kategori;
                    echo '<option value="' . esc($row->nama_kategori) . '">' . esc($row->nama_kategori) . '</option>';
                }
            }
            ?>
        </select>

        <label class="me-2">Filter Unit:</label>
        <select id="unitFilter" class="form-select d-inline" style="width: auto; display: inline-block;"
            onchange="filterKategori()">
            <option value="">Semua Unit</option>

            <?php foreach ($unit as $u): ?>

            <?php if (session('ID_UNIT') == 1): ?>
            <!-- Admin: tampilkan semua -->
            <option value="<?= esc($u->NAMA_UNIT) ?>">
                <?= esc($u->NAMA_UNIT) ?>
            </option>

            <?php else: ?>
            <!-- User biasa: tampilkan hanya unit miliknya -->
            <?php if ($u->idunit == session('ID_UNIT')): ?>
            <option value="<?= esc($u->NAMA_UNIT) ?>" selected>
                <?= esc($u->NAMA_UNIT) ?>
            </option>
            <?php endif; ?>

            <?php endif; ?>

            <?php endforeach; ?>
        </select>



        <label class="me-2 ms-4">Filter PPN:</label>
        <select id="ppnFilter" class="form-select d-inline" style="width: auto;" onchange="filterKategori()">
            <option value="">Semua</option>
            <option value="PPN">PPN</option>
            <option value="Non PPN">Non PPN</option>
        </select>
        <?php if (session('ID_UNIT') == 1) : ?>
        <button onclick="resetKategoriFilter()" class="btn btn-sm btn-secondary ms-2">Reset</button>
        <?php endif; ?>
    </div>


    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
            <thead class="text-dark fs-4">
                <tr>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Tanggal</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Jumlah</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama Barang</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Imei</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama kategori</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Harga Beli</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Status PPN</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Satuan Terkecil</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">ID Unit</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">ID Suplier</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">ID Pelanggan</h6>
                    </th>
                </tr>
            </thead>
            <tbody id="produkTableBody">
                <?php if (!empty($stok)): ?>
                <?php foreach ($stok as $row): ?>
                <tr>
                    <td><?= esc(date('d-m-Y', strtotime($row->tanggal))) ?></td>

                    <td><?= esc($row->jumlah) ?></td>
                    <td><?= esc($row->nama_barang) ?></td>
                    <td><?= esc($row->imei ?? "Tidak ada imei") ?></td>
                    <td><?= esc($row->nama_kategori) ?></td>
                    <td>Rp <?= number_format($row->harga_beli, 0, ',', '.') ?></td>
                    <td><?= $row->status_ppn == 1 ? 'PPN' : 'Non PPN' ?></td>
                    <td><?= esc($row->satuan_terkecil) ?></td>
                    <td><?= esc($row->NAMA_UNIT) ?></td>
                    <td>
                        <?php if (!empty($row->nama_suplier)): ?>
                        <?= esc($row->nama_suplier) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($row->nama_pelanggan)): ?>
                        <?= esc($row->nama_pelanggan) ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center">Tidak ada data</td>
                </tr>
                <?php endif; ?>
            </tbody>

        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="input-stokawal-modal" tabindex="-1" aria-labelledby="inputStokAwalModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" style="max-width: 95%;" role="document">
        <div class="modal-content">
            <form action="<?= base_url('insert/stokawal') ?>" method="post">
                <div class="modal-header">
                    <h4 class="modal-title" id="inputStokAwalModalLabel">Input Data Stok Awal </h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <label for="global_unit" class="col-sm-2 col-form-label">Unit</label>
                        <div class="col-sm-10">
                            <select name="global_unit" id="global_unit" class="form-select" required
                                <?= session('ID_UNIT') == 1 ? '' : 'readonly' ?>>

                                <?php if (session('ID_UNIT') == 1): ?>
                                <!-- Admin / Superuser: tampilkan semua unit -->
                                <?php foreach ($unit as $u): ?>
                                <?php if ($u && isset($u->idunit)): ?>
                                <option value="<?= $u->idunit ?>">
                                    <?= $u->NAMA_UNIT ?>
                                </option>
                                <?php endif; ?>
                                <?php endforeach; ?>

                                <?php else: ?>
                                <!-- User biasa: hanya tampilkan unit sesuai session -->
                                <?php foreach ($unit as $u): ?>
                                <?php if ($u && isset($u->idunit) && $u->idunit == session('ID_UNIT')): ?>
                                <option value="<?= $u->idunit ?>" selected>
                                    <?= $u->NAMA_UNIT ?>
                                </option>
                                <?php endif; ?>
                                <?php endforeach; ?>
                                <?php endif; ?>

                            </select>

                        </div>

                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="table_barang">
                            <thead>
                                <tr>
                                    <th>Pilih</th>

                                    <th style="text-align: center;">Nama Barang</th>
                                    <th style="text-align: center;">Imei</th>
                                    <th>Jumlah</th>
                                    <!-- <th>Harga Beli</th> -->
                                    <th>Satuan Terkecil</th>
                                    <th>Sumber</th>
                                    <th>Suplier</th>
                                    <th>Pelanggan</th>
                                    <th hidden>Unit</th>
                                    <th hidden>Kode Barang</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($barang as $index => $b): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_products[]" value="<?= $b->kode_barang ?>"
                                            id="product_<?= $index ?>" onchange="toggleProductFields(<?= $index ?>)">
                                    </td>

                                    <td style="min-width: 140px; text-align: center;">
                                        <p style="font-weight: bold;"><?= esc($b->kode_barang) ?></p>
                                        <p style="font-style: italic;"><?= esc($b->nama_barang) ?></p>

                                    </td>
                                    <td>
                                        <p style="font-style: italic;"><?= esc($b->imei ?? "tidak ada imei") ?></p>
                                    </td>
                                    <td>
                                        <input type="number" name="jumlah[<?= $b->kode_barang ?>]" class="form-control"
                                            id="jumlah_<?= $index ?>" disabled style="min-width: 120px;">
                                    </td>

                                    <!-- <td>
                                            <input  type="number" name="harga_beli[<?= $b->kode_barang ?>]"
                                                class="form-control currency" id="harga_beli_<?= $index ?>" disabled
                                                style="min-width: 120px;">
                                        </td> -->
                                    <td>
                                        <select name="satuan_terkecil[<?= $b->kode_barang ?>]" class="form-select"
                                            id="satuan_terkecil_<?= $index ?>" disabled style="min-width: 190px;" >
                                            <option value="">-- Pilih Satuan --</option>
                                            <option value="pcs">pcs</option>
                                            <option value="pack">pack</option>
                                        </select>
                                    </td>

                                    
                                    <td>
                                        <select name="id_suplier_text[<?= $b->kode_barang ?>]" class="form-select"
                                            id="id_suplier_text_<?= $index ?>" disabled style="min-width: 190px;">
                                            <option value="">-- Pilih Suplier --</option>
                                            <?php foreach ($suplier as $s): ?>
                                            <option value="<?= $s->id_suplier ?>"><?= $s->nama_suplier ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="id_pelanggan_text[<?= $b->kode_barang ?>]" class="form-select"
                                            id="id_pelanggan_text_<?= $index ?>" disabled style="min-width: 190px;">
                                            <option value="">-- Pilih Pelanggan --</option>
                                            <?php foreach ($pelanggan as $p): ?>
                                            <option value="<?= $p->id_pelanggan ?>"><?= $p->nama ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="id_unit_text[<?= $b->kode_barang ?>]"
                                            id="id_unit_text_<?= $index ?>" hidden>
                                            <?php foreach ($unit as $u): ?>
                                            <?php if ($u && isset($u->idunit)): ?>
                                            <option value="<?= $u->idunit ?>"><?= $u->NAMA_UNIT ?></option>
                                            <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>

                                    </td>
                                    <td hidden><?= esc($b->kode_barang) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let table;

$(document).ready(function() {
    table = $('#zero_config').DataTable();

    // Tambahkan filter custom
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {

        const kategoriFilter = $('#kategoriFilter').val().toLowerCase();
        const ppnFilter = $('#ppnFilter').val().toLowerCase();
        const unitFilter = $('#unitFilter').val().toLowerCase(); // ⬅️ NEW

        const kategori = data[4].toLowerCase(); // kolom kategori
        const ppn = data[6].toLowerCase(); // kolom PPN
        const unit = data[8].toLowerCase(); // kolom Unit (sesuai tabel Anda)

        const matchKategori = !kategoriFilter || kategori === kategoriFilter;
        const matchPPN = !ppnFilter || ppn === ppnFilter;
        const matchUnit = !unitFilter || unit === unitFilter; // ⬅️ NEW

        return matchKategori && matchPPN && matchUnit;
    });

    // Auto-select default bila ada

    if ($('#kategoriFilter option').length > 1) {
        $('#kategoriFilter')[0].selectedIndex = 1;
    }
    if ($('#unitFilter option').length > 1) {
        $('#unitFilter')[0].selectedIndex = 1;
    }
    if ($('#ppnFilter option').length > 1) {
        $('#ppnFilter')[0].selectedIndex = 1;
    }


    table.draw();
});

function filterKategori() {
    table.draw();
}

function resetKategoriFilter() {
    $('#kategoriFilter').val('');
    $('#ppnFilter').val('');
    $('#unitFilter').val(''); // ⬅️ RESET filter unit
    table.draw();
}
</script>

<script>
let tablecc;

$(document).ready(function() {
    tablecc = $('#table_barang').DataTable();
});
const suplierList = <?= json_encode($suplier) ?>;
const pelangganList = <?= json_encode($pelanggan) ?>;
const allBarang = <?= json_encode($barang) ?>;
const stok = <?= json_encode($stok) ?>;

// Toggle enable/disable fields when checkbox is clicked
function toggleProductFields(kodeBarang) {
    const isChecked = document.getElementById('product_' + kodeBarang)?.checked;

    const fields = [
        'jumlah_',
        'harga_beli_',
        'satuan_terkecil_',
        'tipe_relasi_',
        'id_suplier_text_',
        'id_pelanggan_text_'
    ];

    fields.forEach(id => {
        const el = document.getElementById(id + kodeBarang);
        if (el) el.disabled = !isChecked;
    });
}

// Toggle supplier/pelanggan fields based on selected relation type
function toggleRelasiFields(kodeBarang) {
    const tipeRelasi = document.getElementById('tipe_relasi_' + kodeBarang)?.value;
    const suplierSelect = document.getElementById('id_suplier_text_' + kodeBarang);
    const pelangganSelect = document.getElementById('id_pelanggan_text_' + kodeBarang);

    if (!suplierSelect || !pelangganSelect) return;

    if (tipeRelasi === 'suplier') {
        suplierSelect.disabled = false;
        pelangganSelect.disabled = true;
        pelangganSelect.value = '';
    } else if (tipeRelasi === 'pelanggan') {
        pelangganSelect.disabled = false;
        suplierSelect.disabled = true;
        suplierSelect.value = '';
    } else {
        suplierSelect.disabled = true;
        pelangganSelect.disabled = true;
        suplierSelect.value = '';
        pelangganSelect.value = '';
    }
}

// Global unit change handler
document.getElementById('global_unit').addEventListener('change', function() {
    const selectedUnitId = this.value;

    // Update hidden unit fields (if used)
    allBarang.forEach(barang => {
        const el = document.getElementById('id_unit_text_' + barang.kode_barang);
        if (el) el.value = selectedUnitId;
    });

    // Filter and update barang table
    filterBarangByUnit(selectedUnitId);
});

function filterBarangByUnit(unitId) {
    const filteredBarang = allBarang.filter(barang => {
        return !stok.some(s => s.unit_idunit == unitId && s.barang_idbarang == barang.idbarang);
    });
    updateBarangTable(filteredBarang);
}

function updateBarangTable(filteredBarang) {
    const tableBody = document.querySelector('#table_barang tbody');
    tableBody.innerHTML = '';

    filteredBarang.forEach((barang) => {
        const kodeBarang = barang.kode_barang;

        const suplierOptions = suplierList.map(s =>
            `<option value="${s.id_suplier}">${s.nama_suplier}</option>`
        ).join('');

        const pelangganOptions = pelangganList.map(p =>
            `<option value="${p.id_pelanggan}">${p.nama}</option>`
        ).join('');

        tableBody.innerHTML += `
            <tr>
                <td>
                    <input type="checkbox" name="selected_products[]" value="${kodeBarang}" id="product_${kodeBarang}">
                </td>
                <td style="min-width: 140px; text-align: center;">
                    <p style="font-weight: bold;">${kodeBarang}</p>
                    <p style="font-style: italic;">${barang.nama_barang}</p>
                </td>
                <td><input type="number" name="jumlah[${kodeBarang}]" class="form-control" id="jumlah_${kodeBarang}" disabled style="min-width: 120px;"></td>
                <td>
                    <select name="satuan_terkecil[${kodeBarang}]" class="form-select" id="satuan_terkecil_${kodeBarang}" disabled style="min-width: 190px;">
                        <option value="">-- Pilih Satuan --</option>
                        <option value="pcs">pcs</option>
                        <option value="pack">pack</option>
                    </select>
                </td>
                <td>
                    <select name="tipe_relasi[${kodeBarang}]" class="form-select" id="tipe_relasi_${kodeBarang}" disabled>
                        <option value="">-- Pilih Tipe --</option>
                        <option value="suplier">Suplier</option>
                        <option value="pelanggan">Pelanggan</option>
                    </select>
                </td>
                <td>
                    <select name="id_suplier_text[${kodeBarang}]" class="form-select" id="id_suplier_text_${kodeBarang}" disabled style="min-width: 190px;">
                        <option value="">-- Pilih Suplier --</option>
                        ${suplierOptions}
                    </select>
                </td>
                <td>
                    <select name="id_pelanggan_text[${kodeBarang}]" class="form-select" id="id_pelanggan_text_${kodeBarang}" disabled style="min-width: 190px;">
                        <option value="">-- Pilih Pelanggan --</option>
                        ${pelangganOptions}
                    </select>
                </td>
            </tr>
        `;
    });

    // Rebind event listeners after rendering
    filteredBarang.forEach((barang) => {
        const kodeBarang = barang.kode_barang;
        const checkbox = document.getElementById('product_' + kodeBarang);
        const tipeRelasi = document.getElementById('tipe_relasi_' + kodeBarang);

        if (checkbox) {
            checkbox.addEventListener('change', function() {
                toggleProductFields(kodeBarang);
            });
        }

        if (tipeRelasi) {
            tipeRelasi.addEventListener('change', function() {
                toggleRelasiFields(kodeBarang);
            });
        }
    });
}

// Optional: Initialize DataTable
</script>