<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Riwayat Stok Opname</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Riwayat</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Stok Opname</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom">
    </div>

    <div class="card-body px-4 pt-4 pb-2 d-flex justify-content-between align-items-start mb-1">
        <div class="d-flex gap-2">
        </div>
    </div>

    <form action="<?php echo base_url('riwayat_stok_opname/export') ?>" method="post" enctype="multipart/form-data"
        id="exportForm">
        <button type="submit" class="btn btn-danger"
            style="margin-left: 20px; display: inline-flex; align-items: center;">
            <iconify-icon icon="solar:export-broken" width="24" height="24" style="margin-right: 8px;"></iconify-icon>
            Export
        </button>
        <br><br>

        <div style="display: flex; justify-content: left; margin-left: 20px; gap: 20px;">
            <!-- Filter Tanggal & Unit -->
            <div class="mb-4">
                <label for="unitFilter">Filter Unit:</label>
                <select style="width: 200px;" name="unit" id="unitFilter" class="form-control">
                    <option value="">Semua Unit</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="startDate">Tanggal Awal:</label>
                <input style="width: 200px;" name="tanggal_awal" type="date" id="startDate" class="form-control">
            </div>

            <div class="mb-4">
                <label for="endDate">Tanggal Akhir:</label>
                <input style="width: 200px;" name="tanggal_akhir" type="date" id="endDate" class="form-control">
            </div>
            <div class="mb-4">
                <label for="blabla" style="color: transparent; text-decoration: none;">a</label>
                <br>
                <button type="button" id="resetBtn" class="btn btn-sm btn-secondary ms-3">Reset</button>
            </div>
        </div>
    </form>

    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
            <thead class="text-dark fs-4">
                <tr>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Tanggal</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama Barang</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama Unit</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Jumlah Real</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Jumlah Komputer</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Jumlah Selisih</h6>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($stokopname)): ?>
                    <?php foreach ($stokopname as $row): ?>
                        <tr>
                            <td data-sort="<?= date('Y-m-d', strtotime($row->tanggal)) ?>"><?= esc($row->tanggal) ?></td>
                            <td><?= esc($row->nama_barang) ?></td>
                            <td><?= esc($row->NAMA_UNIT) ?></td>
                            <td><?= esc($row->jumlah_real) ?></td>
                            <td><?= esc($row->jumlah_komp) ?></td>
                            <td><?= esc($row->jumlah_selisih) ?></td>
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

<script>
    $(document).ready(function() {
        // ========== 1. CEK DAN INISIALISASI DATATABLE ==========
        var table;

        // Cek apakah DataTable sudah diinisialisasi
        if ($.fn.dataTable.isDataTable('#zero_config')) {
            console.log('DataTable sudah ada, menggunakan instance yang ada');
            table = $('#zero_config').DataTable();
        } else {
            console.log('Inisialisasi DataTable baru');
            table = $('#zero_config').DataTable({
                "order": [
                    [0, 'desc']
                ], // Urutkan tanggal descending
                "pageLength": 25,
                "language": {
                    "search": "Cari:",
                    "lengthMenu": "Tampilkan _MENU_ data",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Berikutnya",
                        "previous": "Sebelumnya"
                    }
                }
            });
        }

        console.log('DataTable initialized with', table.rows().count(), 'rows');

        // ========== 2. POPULATE UNIT FILTER ==========
        function populateUnitFilter() {
            var unitSet = new Set();

            // Ambil semua unit unik dari tabel
            table.rows().every(function() {
                var unit = this.data()[2]; // Kolom ke-3 (Nama Unit)
                if (unit && unit.trim() !== '') {
                    unitSet.add(unit.trim());
                }
            });

            // Urutkan dan tambahkan ke dropdown
            var unitFilter = $('#unitFilter');
            unitFilter.empty().append('<option value="">Semua Unit</option>');

            Array.from(unitSet).sort().forEach(function(unit) {
                unitFilter.append('<option value="' + unit + '">' + unit + '</option>');
            });

            console.log('Unit filter populated with', unitSet.size, 'units');
        }

        // Panggil setelah tabel diinisialisasi
        setTimeout(populateUnitFilter, 100);

        // ========== 3. FUNGSI FILTER TANGGAL ==========
        var currentFilter = null;

        function applyFilter() {
            console.log('Applying filter...');

            // Hapus filter sebelumnya jika ada
            if (currentFilter !== null) {
                var index = $.fn.dataTable.ext.search.indexOf(currentFilter);
                if (index !== -1) {
                    $.fn.dataTable.ext.search.splice(index, 1);
                }
                currentFilter = null;
            }

            var startDate = $('#startDate').val();
            var endDate = $('#endDate').val();
            var selectedUnit = $('#unitFilter').val();

            console.log('Filter parameters:', {
                startDate: startDate,
                endDate: endDate,
                unit: selectedUnit
            });

            // Buat filter baru jika ada parameter
            if (startDate || endDate || selectedUnit) {
                currentFilter = function(settings, data, dataIndex) {
                    // Kolom data: 0=Tanggal, 1=Nama Barang, 2=Unit, 3=Real, 4=Komp, 5=Selisih
                    var rowDateStr = data[0]; // Format tanggal dari tabel
                    var rowUnit = data[2]; // Nama Unit

                    // ===== FILTER TANGGAL =====
                    var dateMatch = true;

                    if (startDate || endDate) {
                        // Coba parsing tanggal dari berbagai format
                        var rowDate = null;

                        // Format 1: YYYY-MM-DD
                        if (rowDateStr.match(/^\d{4}-\d{2}-\d{2}$/)) {
                            rowDate = new Date(rowDateStr);
                        }
                        // Format 2: DD-MM-YYYY
                        else if (rowDateStr.match(/^\d{2}-\d{2}-\d{4}$/)) {
                            var parts = rowDateStr.split('-');
                            rowDate = new Date(parts[2] + '-' + parts[1] + '-' + parts[0]);
                        }
                        // Format 3: Ambil dari data-sort attribute
                        else {
                            var rowNode = table.row(dataIndex).node();
                            var sortValue = $(rowNode).find('td:eq(0)').data('sort');
                            if (sortValue) {
                                rowDate = new Date(sortValue);
                            } else {
                                rowDate = new Date(rowDateStr);
                            }
                        }

                        if (rowDate && !isNaN(rowDate.getTime())) {
                            rowDate.setHours(0, 0, 0, 0);

                            if (startDate) {
                                var start = new Date(startDate);
                                start.setHours(0, 0, 0, 0);
                                if (rowDate < start) dateMatch = false;
                            }

                            if (endDate) {
                                var end = new Date(endDate);
                                end.setHours(23, 59, 59, 999);
                                if (rowDate > end) dateMatch = false;
                            }
                        } else {
                            dateMatch = false;
                        }
                    }

                    // ===== FILTER UNIT =====
                    var unitMatch = true;
                    if (selectedUnit) {
                        unitMatch = rowUnit === selectedUnit;
                    }

                    var result = dateMatch && unitMatch;
                    return result;
                };

                $.fn.dataTable.ext.search.push(currentFilter);
            }

            // Redraw tabel dengan filter baru
            table.draw();

            console.log('Filter applied. Visible rows:', table.rows({
                filter: 'applied'
            }).count());
        }

        // ========== 4. SET DEFAULT DATE RANGE ==========
        function setDefaultDateRange() {
            var today = new Date();
            var fifteenDaysAgo = new Date();
            fifteenDaysAgo.setDate(today.getDate() - 15);

            // Format ke YYYY-MM-DD
            function formatDate(date) {
                var year = date.getFullYear();
                var month = String(date.getMonth() + 1).padStart(2, '0');
                var day = String(date.getDate()).padStart(2, '0');
                return year + '-' + month + '-' + day;
            }

            $('#startDate').val(formatDate(fifteenDaysAgo));
            $('#endDate').val(formatDate(today));

            console.log('Default date range set:', $('#startDate').val(), 'to', $('#endDate').val());

            // Apply filter setelah set default
            setTimeout(applyFilter, 300);
        }

        // ========== 5. EVENT HANDLERS ==========

        // Event untuk filter perubahan
        $('#startDate, #endDate, #unitFilter').on('change', function() {
            console.log('Filter changed by:', this.id);
            applyFilter();
        });

        // Event untuk reset
        $('#resetBtn').on('click', function() {
            console.log('Resetting filters...');
            $('#startDate').val('');
            $('#endDate').val('');
            $('#unitFilter').val('');
            applyFilter();
        });

        // Event untuk export form
        $('#exportForm').on('submit', function() {
            // Pastikan nilai filter tersimpan dalam form
            $(this).find('[name="tanggal_awal"]').val($('#startDate').val());
            $(this).find('[name="tanggal_akhir"]').val($('#endDate').val());
            $(this).find('[name="unit"]').val($('#unitFilter').val());
            console.log('Exporting with filters:', {
                start: $('#startDate').val(),
                end: $('#endDate').val(),
                unit: $('#unitFilter').val()
            });
            return true;
        });

        // ========== 6. INISIALISASI AWAL ==========

        // Set default date range hanya jika belum diisi
        if (!$('#startDate').val() && !$('#endDate').val()) {
            setDefaultDateRange();
        } else {
            // Jika sudah ada nilai, apply filter
            setTimeout(applyFilter, 500);
        }
    });
</script>
