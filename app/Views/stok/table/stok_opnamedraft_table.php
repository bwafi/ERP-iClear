<div class="mb-3" style="margin-left: 20px;">
    <label for="filterUnit"> Unit:</label>
    <select style="margin-right: 10px;" id="filterUnit" class="form-control d-inline-block w-auto">
        <option value="">Semua Unit</option>
        <?php foreach ($unit as $u): ?>
            <option value="<?= esc($u->nama_unit ?? $u->NAMA_UNIT) ?>">
                <?= esc($u->nama_unit ?? $u->NAMA_UNIT) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button id="resetFilter" class="btn btn-secondary">Reset</button>
</div>

<form action="<?= base_url("insert/stokopname") ?>" method="post">
    <div id="draftTableWrapper" class="mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle row-border hover order-column" id="stokOpnameDraftTable" style="width:100%">
            <thead class="text-dark fs-4">
                <tr>
                    <th><input type="checkbox" id="select_all"></th>
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
    <button type="submit" class="btn btn-primary mt-3">Simpan</button>
</form>

<style>
    /* Area tabel punya scroll sendiri; header sticky; pagination & info di luar */
    #draftTableWrapper .dataTables_wrapper .dataTables_scrollBody {
        max-height: 60vh;
    }

    #stokOpnameDraftTable thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background-color: #fff;
    }

    /* Info kiri, pagination kanan, keduanya di bawah area scroll */
    #draftTableWrapper .dataTables_wrapper .bottom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    @media (max-width: 768px) {
        #draftTableWrapper .dataTables_wrapper .bottom {
            justify-content: center;
        }
    }

    body {}
</style>

<script>
    $(document).ready(function() {
        var $table = $('#stokOpnameDraftTable');

        function reloadTable() {
            if ($.fn.dataTable.isDataTable($table)) {
                $table.DataTable().ajax.reload(null, false);
            }
        }

        if ($.fn.dataTable.isDataTable('#zero_config')) {
            $('#zero_config').DataTable().destroy();
        }

        $table.DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '<?= base_url("stokopname/loadtable?table=tabledaraft") ?>',
                data: function(d) {
                    d.unit = $('#filterUnit').val();
                }
            },
            scrollY: '60vh',
            scrollX: true,
            scrollCollapse: true,
            columns: [{
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function(data, type, row) {
                        var bid = row.barang_idbarang;
                        var uid = row.unit_idunit;
                        return '<input type="checkbox" class="row-check" name="data[' + bid + '][checked]" value="1">' +
                            '<input type="hidden" name="data[' + bid + '][barang_idbarang]" value="' + bid + '">' +
                            '<input type="hidden" name="data[' + bid + '][unit_idunit]" value="' + uid + '">';
                    }
                },
                {
                    data: 'kode_barang'
                },
                {
                    data: 'nama_barang'
                },
                {
                    data: 'NAMA_UNIT',
                    name: 'unit.NAMA_UNIT'
                },
                {
                    data: 'jumlah_komp',
                    render: function(data, type, row) {
                        return '<input class="form-control form-control-sm jumlah-komp" readonly name="data[' + row.barang_idbarang + '][jumlah_komp]" value="' + (data || 0) + '">';
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
                        return '<input readonly class="form-control form-control-sm jumlah_selisih" name="data[' + row.barang_idbarang + '][jumlah_selisih]" value="' + (data || 0) + '">';
                    }
                },
            ],
            columnDefs: [{
                targets: [0, 4, 5, 6],
                orderable: false
            }],
            pageLength: 25,
            lengthMenu: [
                [25, 50, 100],
                [25, 50, 100]
            ],
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
            // l=length, f=filter, t=tabel(scroll), i=info, p=pagination
            // i dan p otomatis DI LUAR area scroll karena scroll hanya membungkus t
            dom: "<'row mb-2'<'col-md-6'l><'col-md-6 d-flex justify-content-end'f>>" +
                "rt" +
                "<'row mt-2'<'col-md-6'i><'col-md-6 d-flex justify-content-end'p>>",
            order: [
                [2, 'asc']
            ],
            drawCallback: function() {
                // Pagination styling sudah di-apply oleh template.php (dataTables.bootstrap5.min.css)
                // Tidak perlu custom styling manual
            }
        });

        $('#select_all').on('change', function() {
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
            $tr.find('.jumlah_selisih').val(real - komp);
        });

        $(document).on('change', '.row-check', function() {
            var $tr = $(this).closest('tr');
            $tr.find('.jumlah-komp').prop('disabled', !$(this).is(':checked'));
            $tr.find('.jumlah-real').prop('disabled', !$(this).is(':checked'));
        });

        $('#filterUnit').on('change', function() {
            reloadTable();
        });

        $('#resetFilter').on('click', function(e) {
            e.preventDefault();
            $('#filterUnit').val('');
            reloadTable();
        });
    });
</script>
