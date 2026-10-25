<?= view('riwayat/_sparepart_keluar_theme') ?>
<?php
$bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$tanggalLabel = 'Semua tanggal';
if (!empty($selected_day)) {
    $ts = strtotime((string) $selected_day);
    if ($ts !== false) {
        $tanggalLabel = date('d', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    }
}

$unitLabel = 'Unit Anda';
if ((int) $selected_unit === 0) {
    $unitLabel = 'Semua unit';
}
foreach ($list_unit as $u) {
    if ((int) $u['idunit'] === (int) $selected_unit) {
        $unitLabel = (string) $u['NAMA_UNIT'];
        break;
    }
}
?>
<div class="sk-page">
    <header class="sk-head">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="sk-crumb">
                    <li><a href="<?= base_url('/') ?>">Dashboard</a></li>
                    <li aria-hidden="true">/</li>
                    <li aria-current="page">Sparepart Keluar</li>
                </ol>
            </nav>
            <h1 class="sk-title">Sparepart Keluar</h1>
            <p><?= $can_see_all_units
                    ? 'Sparepart yang keluar dari stok lewat penjualan dan klaim garansi. Semua unit ditampilkan secara default; pilih unit dan tanggal untuk mempersempit data.'
                    : 'Sparepart yang keluar dari stok lewat penjualan dan klaim garansi pada unit Anda.' ?></p>
        </div>
        <div class="sk-head__meta">
            <span class="sk-chip sk-chip--accent">
                <iconify-icon icon="solar:box-minimalistic-linear" width="16" height="16"></iconify-icon>
                <strong id="skCountValue">0</strong> baris
            </span>
            <span class="sk-chip">
                <iconify-icon icon="<?= $can_see_all_units ? 'solar:map-point-linear' : 'solar:lock-keyhole-minimalistic-linear' ?>" width="16" height="16"></iconify-icon>
                <?= esc($unitLabel) ?>
            </span>
            <span class="sk-chip">
                <iconify-icon icon="solar:calendar-linear" width="16" height="16"></iconify-icon>
                <?= esc($tanggalLabel) ?>
            </span>
        </div>
    </header>

    <section class="sk-panel" aria-labelledby="sk-panel-title">
        <div class="sk-panel__head">
            <div>
                <h2 id="sk-panel-title">Daftar sparepart keluar</h2>
                <p>Klik Detail untuk melihat rincian. Edit dan Hapus hanya tersedia untuk Admin root.</p>
            </div>
            <div class="sk-search">
                <iconify-icon icon="solar:magnifer-linear" width="18" height="18"></iconify-icon>
                <input type="search" id="searchBox" placeholder="Cari invoice/no. service, nama barang..."
                    aria-label="Cari sparepart keluar" autocomplete="off">
            </div>
        </div>

        <div class="sk-filter" role="group" aria-label="Filter sparepart keluar">
            <div class="sk-field">
                <label for="unitFilter">Unit</label>
                <select id="unitFilter" class="sk-input" onchange="reloadTable()" <?= $can_see_all_units ? '' : 'disabled' ?>>
                    <?php if ($can_see_all_units): ?>
                        <option value=""<?= (int) $selected_unit === 0 ? ' selected' : '' ?>>Semua Unit</option>
                    <?php endif; ?>
                    <?php foreach ($list_unit as $u): ?>
                        <option value="<?= $u['idunit'] ?>"<?= $selected_unit == $u['idunit'] ? ' selected' : '' ?>>
                            <?= esc($u['NAMA_UNIT']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sk-field">
                <label for="dayFilter">Tanggal</label>
                <input type="date" id="dayFilter" class="sk-input" value="<?= esc($selected_day ?? '') ?>" onchange="reloadTable()">
            </div>
            <div class="sk-field">
                <label for="pageLength">Tampilkan</label>
                <select id="pageLength" class="sk-input">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="sk-filter__actions">
                <button type="button" onclick="resetFilter()" class="sk-btn sk-btn--ghost">
                    <iconify-icon icon="solar:refresh-linear" width="18" height="18"></iconify-icon>
                    Reset
                </button>
            </div>
        </div>

        <div class="sk-table-scroll">
            <table class="sk-table" id="zero_config">
                <caption class="visually-hidden">Daftar sparepart yang keluar dari stok</caption>
                <thead>
                    <tr>
                        <th scope="col">Kode Invoice / Service</th>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Nama Sparepart</th>
                        <th scope="col">Unit</th>
                        <th scope="col" class="sk-num">HPP</th>
                        <th scope="col" class="sk-num">Sub Total</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </section>
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
        if (isNaN(d.getTime())) return '';
        return String(d.getDate()).padStart(2, '0') + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + d.getFullYear();
    }

    function esc(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function(c) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[c];
        });
    }

    function cellLabel(label) {
        return function(td) {
            td.setAttribute('data-label', label);
        };
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
            searching: false,
            lengthChange: false,
            pageLength: 10,
            autoWidth: false,
            dom: "t<'sk-dt-foot'ip>",
            language: {
                processing: '<div class="sk-loading"><span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span> Memuat data...</div>',
                emptyTable: '<div class="sk-empty">' +
                    '<span class="sk-empty__icon"><iconify-icon icon="solar:box-minimalistic-linear" width="24" height="24"></iconify-icon></span>' +
                    '<h3>Belum ada sparepart keluar</h3>' +
                    '<p>Tidak ada data pada unit dan tanggal ini. Coba ubah tanggal atau pilih unit lain.</p>' +
                    '</div>',
                zeroRecords: '<div class="sk-empty">' +
                    '<span class="sk-empty__icon"><iconify-icon icon="solar:magnifer-linear" width="24" height="24"></iconify-icon></span>' +
                    '<h3>Tidak ada hasil</h3>' +
                    '<p>Tidak ada sparepart yang cocok dengan pencarian Anda.</p>' +
                    '</div>',
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris',
                infoEmpty: 'Menampilkan 0 dari 0 baris',
                infoFiltered: '(difilter dari _MAX_ total baris)',
                paginate: {
                    first: 'Pertama',
                    last: 'Terakhir',
                    next: 'Berikutnya',
                    previous: 'Sebelumnya'
                }
            },
            ajax: {
                url: '<?= base_url('sparepart_keluar/ajax') ?>',
                type: 'POST',
                data: function(d) {
                    d.unit = jQuery('#unitFilter').val();
                    d.day = jQuery('#dayFilter').val();
                    d.search = { value: jQuery('#searchBox').val() || '' };
                }
            },
            columns: [{
                    data: 'kode_invoice',
                    createdCell: cellLabel('Dokumen'),
                    render: function(d, t, r) {
                        var tag = r.sumber === 'klaim' ?
                            '<span class="sk-tag sk-tag--claim">Klaim Garansi</span>' :
                            '<span class="sk-tag sk-tag--sale">Penjualan</span>';
                        return '<div class="sk-doc"><span class="sk-code">' + esc(d) + '</span>' + tag + '</div>';
                    }
                },
                {
                    data: 'tanggal',
                    createdCell: cellLabel('Tanggal'),
                    render: function(d) {
                        var date = new Date(d);
                        if (isNaN(date.getTime())) return '-';
                        var time = String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
                        return '<span class="sk-date">' + fmtDate(d) + '</span><span class="sk-sub">' + time + ' WIB</span>';
                    }
                },
                {
                    data: 'nama_barang',
                    createdCell: cellLabel('Sparepart'),
                    render: function(d, t, r) {
                        return '<span class="sk-strong">' + esc(d) + '</span><span class="sk-sub">' + esc(r.jumlah) + ' unit</span>';
                    }
                },
                {
                    data: 'NAMA_UNIT',
                    createdCell: cellLabel('Unit'),
                    render: function(d) {
                        return '<span class="sk-unit"><iconify-icon icon="solar:map-point-linear" width="14" height="14"></iconify-icon><span>' + esc(d) + '</span></span>';
                    }
                },
                {
                    data: 'hpp_penjualan',
                    className: 'sk-num',
                    createdCell: cellLabel('HPP'),
                    render: function(d) {
                        return '<span class="sk-money">' + rupiah(d) + '</span>';
                    }
                },
                {
                    data: 'sub_total',
                    className: 'sk-num',
                    createdCell: cellLabel('Sub Total'),
                    render: function(d) {
                        return '<span class="sk-money">' + rupiah(d) + '</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    createdCell: cellLabel('Aksi'),
                    render: function(d, t, r, m) {
                        var b = '<div class="sk-actions">';
                        b += '<button type="button" class="sk-btn sk-btn--icon btn-d" data-i="' + m.row + '" title="Detail" aria-label="Detail"><iconify-icon icon="solar:folder-favourite-bookmark-broken" width="18" height="18"></iconify-icon></button>';
                        if (is_admin && r.sumber !== 'klaim') {
                            b += '<button type="button" class="sk-btn sk-btn--icon sk-btn--edit btn-e" data-i="' + m.row + '" title="Edit" aria-label="Edit"><iconify-icon icon="solar:pen-bold" width="18" height="18"></iconify-icon></button>';
                            b += '<button type="button" class="sk-btn sk-btn--icon sk-btn--delete btn-x" data-i="' + m.row + '" title="Hapus" aria-label="Hapus"><iconify-icon icon="solar:trash-bin-trash-broken" width="18" height="18"></iconify-icon></button>';
                        }
                        b += '</div>';
                        return b;
                    }
                }
            ],
            order: [
                [1, 'desc']
            ],
            drawCallback: function() {
                var info = this.api().page.info();
                var count = document.getElementById('skCountValue');
                if (count) count.textContent = info.recordsDisplay;
            }
        });

        jQuery('#searchBox').on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                // Pencarian berlaku lintas unit: filter otomatis pindah ke Semua Unit.
                if (!jQuery('#unitFilter').prop('disabled')) {
                    jQuery('#unitFilter').val('');
                }
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
        jQuery('#unitFilter').val('<?= (int) $selected_unit ?: '' ?>');
        jQuery('#dayFilter').val('');
        jQuery('#searchBox').val('');
        dt.ajax.reload();
    }

    function openModal(t, i) {
        var r = dt.row(i).data();
        if (!r) return;
        var h = '';

        if (t === 'detail') {
            h = '<div class="modal fade sk-modal" id="tmpM" tabindex="-1" aria-labelledby="skModalLabel">';
            h += '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">';
            h += '<div class="modal-header"><div class="sk-modal__title"><span class="sk-modal__icon"><iconify-icon icon="solar:box-minimalistic-linear" width="22" height="22"></iconify-icon></span><div><h5 class="modal-title" id="skModalLabel">Detail Sparepart</h5><p>' + esc(r.kode_invoice) + '</p></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>';
            h += '<div class="modal-body"><dl class="sk-detail">';
            h += '<div class="sk-detail__row"><dt>Tanggal</dt><dd>' + fmtDate(r.tanggal) + '</dd></div>';
            h += '<div class="sk-detail__row"><dt>Invoice / Service</dt><dd>' + esc(r.kode_invoice) + '</dd></div>';
            h += '<div class="sk-detail__row"><dt>Sumber</dt><dd>' + (r.sumber === 'klaim' ? 'Klaim Garansi' : 'Penjualan') + '</dd></div>';
            if (r.sumber !== 'klaim') {
                h += '<div class="sk-detail__row"><dt>Id Detail</dt><dd>' + esc(r.iddetail_penjualan) + '</dd></div>';
            }
            h += '<div class="sk-detail__row"><dt>Nama Sparepart</dt><dd>' + esc(r.nama_barang) + '</dd></div>';
            h += '<div class="sk-detail__row"><dt>Unit</dt><dd>' + esc(r.NAMA_UNIT) + '</dd></div>';
            h += '<div class="sk-detail__row"><dt>Jumlah</dt><dd>' + esc(r.jumlah) + ' unit</dd></div>';
            h += '<div class="sk-detail__row"><dt>HPP</dt><dd>' + rupiah(r.hpp_penjualan) + '</dd></div>';
            h += '<div class="sk-detail__row"><dt>Sub Total</dt><dd>' + rupiah(r.sub_total) + '</dd></div>';
            h += '</dl></div>';
            h += '<div class="modal-footer"><button type="button" class="sk-btn sk-btn--ghost" data-bs-dismiss="modal">Tutup</button></div>';
            h += '</div></div></div>';
        } else if (t === 'edit') {
            h = '<div class="modal fade sk-modal" id="tmpM" tabindex="-1" aria-labelledby="skModalLabel">';
            h += '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">';
            h += '<form action="<?= base_url("sparepart_keluar/edit") ?>" method="post">';
            h += '<?= csrf_field() ?>';
            h += '<input type="hidden" name="iddetail_penjualan" value="' + esc(r.iddetail_penjualan) + '">';
            h += '<div class="modal-header"><div class="sk-modal__title"><span class="sk-modal__icon"><iconify-icon icon="solar:pen-bold" width="22" height="22"></iconify-icon></span><div><h5 class="modal-title" id="skModalLabel">Edit Sparepart</h5><p>' + esc(r.kode_invoice) + '</p></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>';
            h += '<div class="modal-body sk-form">';
            h += '<div class="mb-3"><label class="form-label">Nama Sparepart</label><input type="text" class="form-control" value="' + esc(r.nama_barang) + '" readonly></div>';
            h += '<div class="mb-3"><label class="form-label">Jumlah</label><input type="number" class="form-control" name="jumlah" value="' + esc(r.jumlah) + '" required min="1"></div>';
            h += '<div class="mb-3"><label class="form-label">Harga Jual</label><input type="text" class="form-control rupiah-input" name="harga_penjualan" value="' + rupiah(r.harga_penjualan) + '" required></div>';
            h += '<div class="mb-3"><label class="form-label">HPP</label><input type="text" class="form-control rupiah-input" name="hpp_penjualan" value="' + rupiah(r.hpp_penjualan) + '" required></div>';
            h += '<div class="mb-3"><label class="form-label">Diskon</label><input type="text" class="form-control rupiah-input" name="diskon_penjualan" value="' + rupiah(r.diskon_penjualan || 0) + '"></div>';
            h += '</div>';
            h += '<div class="modal-footer"><button type="button" class="sk-btn sk-btn--ghost" data-bs-dismiss="modal">Batal</button><button type="submit" class="sk-btn sk-btn--primary">Simpan</button></div>';
            h += '</form></div></div></div>';
        } else if (t === 'delete') {
            h = '<div class="modal fade sk-modal sk-modal--danger" id="tmpM" tabindex="-1" aria-labelledby="skModalLabel">';
            h += '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">';
            h += '<form action="<?= base_url("sparepart_keluar/delete") ?>" method="post">';
            h += '<?= csrf_field() ?>';
            h += '<input type="hidden" name="iddetail_penjualan" value="' + esc(r.iddetail_penjualan) + '">';
            h += '<div class="modal-header"><div class="sk-modal__title"><span class="sk-modal__icon"><iconify-icon icon="solar:trash-bin-trash-broken" width="22" height="22"></iconify-icon></span><div><h5 class="modal-title" id="skModalLabel">Konfirmasi Hapus</h5><p>' + esc(r.kode_invoice) + '</p></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>';
            h += '<div class="modal-body text-center">';
            h += '<h5 class="mt-2 mb-2">Yakin ingin menghapus?</h5>';
            h += '<p class="text-muted mb-0">' + esc(r.kode_invoice) + ' — ' + esc(r.nama_barang) + '</p>';
            h += '<p class="text-muted mt-2 mb-0">Stok dan total penjualan akan otomatis disesuaikan.</p>';
            h += '</div>';
            h += '<div class="modal-footer"><button type="button" class="sk-btn sk-btn--ghost" data-bs-dismiss="modal">Batal</button><button type="submit" class="sk-btn sk-btn--danger">Hapus</button></div>';
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
