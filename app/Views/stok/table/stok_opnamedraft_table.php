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

<form action="<?= base_url('insert/stokopname') ?>" method="post">
    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
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
            <tbody>
                <?php foreach ($stok as $index => $row): ?>
                <tr>
                    <td>
                        <input type="checkbox" class="row-check" name="data[<?= $index ?>][checked]" value="1">
                    </td>
                    <td>
                        <?= esc($row->kode_barang) ?>
                        <input type="hidden" name="data[<?= $index ?>][barang_idbarang]" value="<?= $row->idbarang ?>">
                        <input type="hidden" name="data[<?= $index ?>][unit_idunit]" value="<?= $row->id_unit ?>">
                    </td>
                    <td><?= esc($row->nama_barang) ?></td>
                    <td><?= esc($row->nama_unit ?? $row->NAMA_UNIT ?? 'N/A') ?></td>
                    <td><input class="form-control jumlah-komp" name="data[<?= $index ?>][jumlah_komp]"
                            value="<?= $row->stok_akhir ?>" readonly></td>
                    <td><input type="number" class="form-control jumlah-real" name="data[<?= $index ?>][jumlah_real]">
                    </td>
                    <td><input readonly class="form-control jumlah_selisih" name="data[<?= $index ?>][jumlah_selisih]">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button type="submit" class="btn btn-primary mt-3">Simpan</button>
</form>

<script>
// Solusi 1: Gunakan $.fn.dataTable.isDataTable() untuk cek apakah sudah diinisialisasi
$(document).ready(function() {
    // 1. Fungsi untuk checkbox select all
    $('#select_all').on('change', function() {
        $('.row-check').prop('checked', this.checked);
    });

    // 2. Fungsi untuk menghitung selisih
    $(document).on('input', '.jumlah-real', function() {
        const $tr = $(this).closest('tr');
        const $checkbox = $tr.find('.row-check');
        const komp = parseFloat($tr.find('input[name$="[jumlah_komp]"]').val()) || 0;
        const real = parseFloat($(this).val()) || 0;

        if ($checkbox.is(':checked')) {
            $tr.find('input[name$="[jumlah_selisih]"]').val(real - komp);
        } else {
            alert("Silakan centang kotak ceklis terlebih dahulu sebelum mengisi jumlah real.");
            $(this).val('').focus();
        }
    });

    // 3. Inisialisasi DataTable HANYA JIKA BELUM DIINISIALISASI
    var table;

    if ($.fn.dataTable.isDataTable('#zero_config')) {
        // Jika sudah diinisialisasi, gunakan instance yang ada
        table = $('#zero_config').DataTable();
        console.log('Menggunakan DataTable yang sudah ada');
    } else {
        // Jika belum diinisialisasi, buat baru
        table = $('#zero_config').DataTable({
            "columnDefs": [{
                "orderable": false,
                "targets": [0, 4, 5, 6]
            }],
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
            },
            "pageLength": 25
        });
        console.log('DataTable diinisialisasi baru');
    }

    // 4. Fungsi untuk filter
    function applyFilter() {
        var selectedValue = $('#filterUnit').val();

        // Reset semua filter
        $.fn.dataTable.ext.search = [];

        if (selectedValue && selectedValue.trim() !== '') {
            // Tambah filter baru
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                var unitName = data[3]; // Kolom Nama Unit
                return unitName === selectedValue;
            });
        }

        table.draw();
    }

    // 5. Event untuk filter dropdown
    $('#filterUnit').on('change', function() {
        applyFilter();
    });

    // 6. Event untuk reset filter
    $('#resetFilter').on('click', function(e) {
        e.preventDefault();
        $('#filterUnit').val('');
        applyFilter();
    });
});
</script>