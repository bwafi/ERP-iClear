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
    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" id="zero_config2">
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
            <tbody>
                <tr>
                    <td colspan="7" class="text-center">Memuat data...</td>
                </tr>
            </tbody>
        </table>
    </div>
    <button type="submit" class="btn btn-primary mt-3">Simpan Fix</button>
</form>

<script>
$(document).ready(function() {
    var $table = $('#zero_config2');

    function reloadTable2() {
        if ($.fn.dataTable.isDataTable($table)) {
            $table.DataTable().ajax.reload(null, false);
        }
    }

    if ($.fn.dataTable.isDataTable($table)) {
        $table = $table.DataTable();
    } else {
        $table = $table.DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '<?= base_url('stokopname/loadtable?table=tablefix') ?>',
                data: function(d) {
                    d.unit = $('#filterUnit2').val();
                }
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function(data, type, row) {
                        var bid = row.barang_idbarang;
                        var uid = row.unit_idunit;
                        return ''
                            + '<input type="checkbox" class="row-check" name="data[' + bid + '][checked]" value="1">'
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
                        var val = data || 0;
                        return '<input type="number" class="form-control form-control-sm jumlah-komp" name="data[' + row.barang_idbarang + '][jumlah_komp]" value="' + val + '">';
                    }
                },
                {
                    data: 'jumlah_real',
                    render: function(data, type, row) {
                        var val = data || '';
                        return '<input type="number" class="form-control form-control-sm jumlah-real" name="data[' + row.barang_idbarang + '][jumlah_real]" value="' + val + '">';
                    }
                },
                {
                    data: 'jumlah_selisih',
                    render: function(data, type, row) {
                        var val = data || 0;
                        return '<input readonly class="form-control form-control-sm jumlah-selisih" name="data[' + row.barang_idbarang + '][jumlah_selisih]" value="' + val + '">';
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
                paginate: {
                    first: "Pertama",
                    last: "Terakhir",
                    next: "Berikutnya",
                    previous: "Sebelumnya"
                }
            },
            order: [[2, 'asc']]
        });
    }

    // 1. Select all checkbox
    $('#select_all2').on('change', function() {
        $('.row-check').prop('checked', this.checked);
    });

    // 2. Hitung selisih (jumlah_real - jumlah_komp) — per row, client-side
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
        var selisih = real - komp;

        $tr.find('.jumlah-selisih').val(selisih);
    });

    // 3. Individual checkbox enables/disables inputs
    $(document).on('change', '.row-check', function() {
        var $tr = $(this).closest('tr');
        var $kompInput = $tr.find('.jumlah-komp');
        var $realInput = $tr.find('.jumlah-real');

        if ($(this).is(':checked')) {
            $kompInput.prop('disabled', false);
            $realInput.prop('disabled', false);
        } else {
            $kompInput.prop('disabled', true);
            $realInput.prop('disabled', true);
        }
    });

    // 4. Filter unit → reload server-side
    $('#filterUnit2').on('change', function() {
        reloadTable2();
    });

    $('#resetFilter2').on('click', function(e) {
        e.preventDefault();
        $('#filterUnit2').val('');
        reloadTable2();
    });
});
</script>
