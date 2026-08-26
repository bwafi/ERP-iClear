<div class="mb-3" style="margin-left: 20px;">
    <label for="filterUnit2"> Unit:</label>
    <select style="margin-right: 10px;" id="filterUnit2" class="form-control d-inline-block w-auto">
        <option value="">Semua Unit</option>
        <?php foreach ($unit as $u): ?>
        <option value="<?= esc($u->NAMA_UNIT) ?>">
            <?= esc($u->NAMA_UNIT) ?>
        </option>
        <?php endforeach; ?>
    </select>
    <button id="resetFilter2" class="btn btn-secondary">Reset</button>
</div>

<form action="<?= base_url('insert/stokopnamefix') ?>" method="post">
    <div id="fixTableWrapper" class="mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle row-border hover order-column" id="stokOpnameFixTable" style="width:100%">
            <thead class="text-dark fs-4">
                <tr>
                    <th><input type="checkbox" id="select_all2"></th>
                    <th>Kode Barang</th>
                    <th>Nama Barang</th>
                    <th>Nama Unit</th>
                    <th>Jumlah Komputer</th>
                    <th>Jumlah Real</th>
                    <th>Jumlah Selisih</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <button type="submit" class="btn btn-primary mt-3">Simpan Fix</button>
</form>

<style>
#fixTableWrapper .dataTables_wrapper .dataTables_scrollBody {
    max-height: 60vh;
}
#stokOpnameFixTable thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background-color: #fff;
}

/* DataTables pagination styling (tanpa bootstrap JS integration) */
#fixTableWrapper .dataTables_paginate {
    display: flex;
    justify-content: flex-end;  /* sebelah kanan */
    align-items: center;        /* vertikal center */
    gap: 4px;
}
#fixTableWrapper .dataTables_paginate .paginate_button {
    display: flex;              /* teks di dalam tombol ikut center */
    align-items: center;
    justify-content: center;
    min-width: 38px;            /* lebar seragam untuk nomor */
    height: 38px;
    padding: 0.375rem 0.75rem;
    box-sizing: border-box;
    text-align: center;
    font-size: 1rem;
    color: #0d6efd;
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
}
#fixTableWrapper .dataTables_paginate .paginate_button:hover {
    color: #0a58ca;
    background-color: #e9ecef;
    text-decoration: none;
}
#fixTableWrapper .dataTables_paginate .paginate_button.current,
#fixTableWrapper .dataTables_paginate .paginate_button.active {
    color: #fff;
    background-color: #0d6efd;
    border-color: #0d6efd;
}
#fixTableWrapper .dataTables_paginate .paginate_button.disabled {
    color: #6c757d;
    background-color: #fff;
    border-color: #dee2e6;
    pointer-events: none;
}
</style>

<script>
$(document).ready(function() {
    var $table = $('#stokOpnameFixTable');

    function reloadTable() {
        if ($.fn.dataTable.isDataTable($table)) {
            $table.DataTable().ajax.reload(null, false);
        }
    }

    if ($.fn.dataTable.isDataTable('#zero_config2')) {
        $('#zero_config2').DataTable().destroy();
    }

    $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '<?= base_url('stokopname/loadtable?table=tablefix') ?>',
            data: function(d) {
                d.unit = $('#filterUnit2').val();
            }
        },
        scrollY: '60vh',
        scrollX: true,
        scrollCollapse: true,
        columns: [
            {
                data: null,
                orderable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    var bid = row.barang_idbarang;
                    var uid = row.unit_idunit;
                    return '<input type="checkbox" class="row-check" name="data[' + bid + '][checked]" value="1">'
                         + '<input type="hidden" name="data[' + bid + '][barang_idbarang]" value="' + bid + '">'
                         + '<input type="hidden" name="data[' + bid + '][unit_idunit]" value="' + uid + '">';
                }
            },
            { data: 'kode_barang' },
            { data: 'nama_barang' },
            { data: 'NAMA_UNIT', name: 'unit.NAMA_UNIT' },
            {
                data: 'jumlah_komp',
                render: function(data, type, row) {
                    return '<input type="number" class="form-control form-control-sm jumlah-komp" name="data[' + row.barang_idbarang + '][jumlah_komp]" value="' + (data || 0) + '">';
                }
            },
            {
                data: 'jumlah_real',
                render: function(data, type, row) {
                    return '<input type="number" class="form-control form-control-sm jumlah-real" name="data[' + row.barang_idbarang + '][jumlah_real]" value="' + (data || '') + '">';
                }
            },
            {
                data: 'jumlah_selisih',
                render: function(data, type, row) {
                    return '<input readonly class="form-control form-control-sm jumlah-selisih" name="data[' + row.barang_idbarang + '][jumlah_selisih]" value="' + (data || 0) + '">';
                }
            },
        ],
        columnDefs: [{
            targets: [0, 4, 5, 6],
            orderable: false
        }],
        pageLength: 25,
        lengthMenu: [[25, 50, 100], [25, 50, 100]],
        language: {
            search: "Cari:",
            lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            emptyTable: "Tidak ada data",
            zeroRecords: "Tidak ditemukan",
            processing: "Memuat...",
            paginate: {
                first: "Pertama",
                last: "Terakhir",
                next: "Berikutnya",
                previous: "Sebelumnya"
            }
        },
        order: [[2, 'asc']]
    });

    $('#select_all2').on('change', function() {
        $('.row-check').prop('checked', this.checked);
    });

    $(document).on('input', '.jumlah-real, .jumlah-komp', function() {
        var $tr = $(this).closest('tr');
        var $checkbox = $tr.find('.row-check');
        if (!$checkbox.is(':checked')) {
            alert("Silakan centang baris terlebih dahulu sebelum mengisi jumlah real.");
            $(this).val('');
            return;
        }
        var komp = parseFloat($tr.find('.jumlah-komp').val()) || 0;
        var real = parseFloat($tr.find('.jumlah-real').val()) || 0;
        $tr.find('.jumlah-selisih').val(real - komp);
    });

    $(document).on('change', '.row-check', function() {
        var $tr = $(this).closest('tr');
        $tr.find('.jumlah-komp').prop('disabled', !$(this).is(':checked'));
        $tr.find('.jumlah-real').prop('disabled', !$(this).is(':checked'));
    });

    $('#filterUnit2').on('change', function() {
        reloadTable();
    });

    $('#resetFilter2').on('click', function(e) {
        e.preventDefault();
        $('#filterUnit2').val('');
        reloadTable();
    });
});
</script>
