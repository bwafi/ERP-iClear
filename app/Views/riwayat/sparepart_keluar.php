<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Sparepart Keluar</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Sparepart Keluar</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="card-body">
        <style>
            input[type="date"] {
                cursor: pointer;
                -webkit-appearance: none;
                appearance: none;
            }

            input[type="date"]::-webkit-calendar-picker-indicator {
                cursor: pointer;
            }
        </style>
        <div class="row g-3 mb-3">
            <div class="col-lg-3 col-md-6">
                <label class="form-label fw-semibold mb-2">Unit</label>
                <select id="unitFilter" class="form-select" onchange="reloadTable()">
                    <?php foreach ($list_unit as $u): ?>
                        <option value="<?= $u['idunit'] ?>" <?= $selected_unit == $u['idunit'] ? 'selected' : '' ?>>
                            <?= $u['NAMA_UNIT'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label fw-semibold mb-2">Tanggal</label>
                <input type="date" id="dayFilter" class="form-control" value="<?= $selected_day ?? '' ?>" onchange="reloadTable()">
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label fw-semibold mb-2">Search</label>
                <input type="text" id="searchBox" class="form-control" placeholder="Cari invoice, nama barang...">
            </div>
            <div class="col-lg-2 col-md-3">
                <label class="form-label fw-semibold mb-2">Show</label>
                <select id="pageLength" class="form-select">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="col-lg-1 col-md-3 d-flex align-items-end">
                <button type="button"
                    onclick="resetFilter()"
                    class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-1">
                    <iconify-icon icon="solar:refresh-linear" width="18" height="18"></iconify-icon>
                    <span>Reset</span>
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
                <thead class="text-dark fs-4">
                    <tr>
                        <th>Kode Invoice</th>
                        <th>Tanggal</th>
                        <th>Nama Sparepart</th>
                        <th>Unit</th>
                        <th>HPP</th>
                        <th>Sub Total</th>
                        <th width="180">Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div id="modalContainer"></div>

<script>
    var dt;
    var searchTimeout;
    var is_admin = <?= $is_admin ? 'true' : 'false' ?>;

    function rupiah(num) {
        return 'Rp ' + Number(num).toLocaleString('id-ID');
    }

    function fmtDate(data) {
        if (!data) return '';
        var d = new Date(data);
        return String(d.getDate()).padStart(2, '0') + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + d.getFullYear();
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (jQuery.fn.DataTable.isDataTable('#zero_config')) {
            jQuery('#zero_config').DataTable().destroy();
        }

        // Pastikan seluruh area input date bisa diklik dan membuka datepicker
        jQuery('#dayFilter').on('click', function() {
            this.showPicker && this.showPicker();
        });

        dt = jQuery('#zero_config').DataTable({
            serverSide: true,
            processing: true,
            scrollY: '50vh',
            scrollX: true,
            scrollCollapse: true,
            searching: false,
            lengthChange: false,
            pageLength: 10,
            language: {
                processing: '<div class="spinner-border text-primary" role="status"></div>',
                emptyTable: 'Tidak ada data',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
                infoEmpty: 'Menampilkan 0 sampai 0 dari 0 data',
                infoFiltered: '(difilter dari _MAX_ total data)',
                paginate: {
                    first: 'Pertama',
                    last: 'Terakhir',
                    next: 'Selanjutnya',
                    previous: 'Sebelumnya'
                }
            },
            ajax: {
                url: '<?= base_url('sparepart_keluar/ajax') ?>',
                type: 'POST',
                data: function(d) {
                    d.unit = jQuery('#unitFilter').val();
                    d.day = jQuery('#dayFilter').val();
                }
            },
            columns: [{
                    data: 'kode_invoice'
                },
                {
                    data: 'tanggal',
                    render: function(d) {
                        return fmtDate(d);
                    }
                },
                {
                    data: 'nama_barang'
                },
                {
                    data: 'NAMA_UNIT'
                },
                {
                    data: 'hpp_penjualan',
                    render: function(d) {
                        return rupiah(d);
                    }
                },
                {
                    data: 'sub_total',
                    render: function(d) {
                        return rupiah(d);
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: function(d, t, r, m) {
                        var b = '<div class="d-flex gap-1">';
                        b += '<button class="btn btn-primary btn-sm btn-d" data-i="' + m.row + '"><iconify-icon icon="solar:folder-favourite-bookmark-broken" width="20"></iconify-icon></button>';
                        if (is_admin) {
                            b += '<button class="btn btn-warning btn-sm btn-e" data-i="' + m.row + '"><iconify-icon icon="solar:pen-bold" width="20"></iconify-icon></button>';
                            b += '<button class="btn btn-danger btn-sm btn-x" data-i="' + m.row + '"><iconify-icon icon="solar:trash-bin-trash-broken" width="20"></iconify-icon></button>';
                        }
                        b += '</div>';
                        return b;
                    }
                }
            ],
            order: [
                [1, 'desc']
            ]
        });

        jQuery('#searchBox').on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                dt.ajax.reload();
            }, 500);
        });

        jQuery('#pageLength').on('change', function() {
            dt.page.len(this.value).draw();
        });

        jQuery(document).on('click', '.btn-d', function() {
            openModal('detail', jQuery(this).data('i'));
        });
        jQuery(document).on('click', '.btn-e', function() {
            openModal('edit', jQuery(this).data('i'));
        });
        jQuery(document).on('click', '.btn-x', function() {
            openModal('delete', jQuery(this).data('i'));
        });
    });

    function reloadTable() {
        dt.ajax.reload();
    }

    function resetFilter() {
        jQuery('#unitFilter').val('<?= $selected_unit ?>');
        jQuery('#dayFilter').val('');
        dt.ajax.reload();
    }

    function openModal(t, i) {
        var r = dt.row(i).data();
        if (!r) return;
        var h = '';

        if (t === 'detail') {
            h = '<div class="modal fade" id="tmpM" tabindex="-1"><div class="modal-dialog"><div class="modal-content">';
            h += '<div class="modal-header"><h5 class="modal-title">Detail Sparepart</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
            h += '<div class="modal-body">';
            h += '<p><strong>Tanggal:</strong> ' + fmtDate(r.tanggal) + '</p>';
            h += '<p><strong>Invoice:</strong> ' + r.kode_invoice + '</p>';
            h += '<p><strong>Id Detail:</strong> ' + r.iddetail_penjualan + '</p>';
            h += '<p><strong>Nama Sparepart:</strong> ' + r.nama_barang + '</p>';
            h += '<p><strong>Unit:</strong> ' + r.NAMA_UNIT + '</p>';
            h += '<p><strong>Jumlah:</strong> ' + r.jumlah + ' unit</p>';
            h += '<p><strong>HPP:</strong> ' + rupiah(r.hpp_penjualan) + '</p>';
            h += '<p><strong>Sub Total:</strong> ' + rupiah(r.sub_total) + '</p>';
            h += '</div></div></div></div>';
        } else if (t === 'edit') {
            h = '<div class="modal fade" id="tmpM" tabindex="-1"><div class="modal-dialog"><div class="modal-content">';
            h += '<form action="<?= base_url("sparepart_keluar/edit") ?>" method="post">';
            h += '<?= csrf_field() ?>';
            h += '<input type="hidden" name="iddetail_penjualan" value="' + r.iddetail_penjualan + '">';
            h += '<div class="modal-header"><h5 class="modal-title">Edit Sparepart</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
            h += '<div class="modal-body">';
            h += '<div class="mb-3"><label class="form-label">Nama Sparepart</label><input type="text" class="form-control" value="' + r.nama_barang + '" readonly></div>';
            h += '<div class="mb-3"><label class="form-label">Jumlah</label><input type="number" class="form-control" name="jumlah" value="' + r.jumlah + '" required min="1"></div>';
            h += '<div class="mb-3"><label class="form-label">Harga Jual</label><input type="text" class="form-control rupiah-input" name="harga_penjualan" value="' + rupiah(r.harga_penjualan) + '" required></div>';
            h += '<div class="mb-3"><label class="form-label">HPP</label><input type="text" class="form-control rupiah-input" name="hpp_penjualan" value="' + rupiah(r.hpp_penjualan) + '" required></div>';
            h += '<div class="mb-3"><label class="form-label">Diskon</label><input type="text" class="form-control rupiah-input" name="diskon_penjualan" value="' + rupiah(r.diskon_penjualan || 0) + '"></div>';
            h += '</div>';
            h += '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>';
            h += '</form></div></div></div>';
        } else if (t === 'delete') {
            h = '<div class="modal fade" id="tmpM" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">';
            h += '<form action="<?= base_url("sparepart_keluar/delete") ?>" method="post">';
            h += '<?= csrf_field() ?>';
            h += '<input type="hidden" name="iddetail_penjualan" value="' + r.iddetail_penjualan + '">';
            h += '<div class="modal-header bg-danger text-white"><h5 class="modal-title">Konfirmasi Hapus</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>';
            h += '<div class="modal-body text-center">';
            h += '<iconify-icon icon="solar:trash-bin-trash-bold-duotone" width="80" class="text-danger"></iconify-icon>';
            h += '<h5 class="mt-3">Yakin ingin menghapus?</h5>';
            h += '<p class="text-muted">' + r.kode_invoice + ' - ' + r.nama_barang + '</p>';
            h += '</div>';
            h += '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-danger">Hapus</button></div>';
            h += '</form></div></div></div>';
        }

        jQuery('#tmpM').modal('hide').remove();
        jQuery('#modalContainer').html(h);
        var m = new bootstrap.Modal(document.getElementById('tmpM'));
        m.show();

        if (t === 'edit') {
            jQuery('.rupiah-input').each(function() {
                var el = this;
                el.addEventListener('focus', function() {
                    if (this.value.replace(/[^0-9]/g, '') === '') {
                        this.value = 'Rp ';
                    }
                    this.setSelectionRange(this.value.length, this.value.length);
                });
                el.addEventListener('input', function() {
                    var digits = this.value.replace(/[^0-9]/g, '');
                    if (digits === '') {
                        this.value = 'Rp ';
                    } else {
                        this.value = 'Rp ' + Number(digits).toLocaleString('id-ID');
                    }
                });
                el.addEventListener('keydown', function(e) {
                    // Izinkan tombol navigasi & backspace
                    if ([8, 9, 37, 39, 46].indexOf(e.keyCode) !== -1) return;
                    // Blokir semua karakter non-angka
                    if (!/[0-9]/.test(e.key) && e.keyCode !== 13) {
                        e.preventDefault();
                    }
                });
            });
        }
    }
</script>
