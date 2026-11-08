<?= $this->include('stok/_kartu_theme') ?>

<?php
$unitAktif = session('ID_UNIT');
$ringkasan = [
    [
        'label' => 'Total Barang',
        'value' => number_format((float) $summary->total_barang, 0, ',', '.'),
        'note'  => 'Baris produk per unit',
    ],
    [
        'label' => 'Total Unit',
        'value' => number_format((float) $summary->total_unit, 0, ',', '.'),
        'note'  => 'Unit dengan stok tercatat',
    ],
    [
        'label' => 'Stok Akhir',
        'value' => number_format((float) $summary->total_stok, 0, ',', '.'),
        'note'  => 'Jumlah unit barang',
    ],
    [
        'label' => 'Nilai Stok (HPP)',
        'value' => 'Rp ' . number_format((float) $summary->nilai_stok, 0, ',', '.'),
        'note'  => 'Stok akhir × HPP per unit',
    ],
];
?>

<div class="kartu-stok">

    <header class="ks-head">
        <nav class="ks-crumb" aria-label="breadcrumb">
            <a href="<?= base_url('/') ?>">Stok</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Kartu Stok</span>
        </nav>
        <h1>Kartu Stok</h1>
        <p>Rekap stok per barang dan unit — pembelian, penjualan, retur, dan mutasi dalam satu baris.</p>
    </header>

    <section class="ks-stats" aria-label="Ringkasan kartu stok">
        <?php foreach ($ringkasan as $item) : ?>
            <div class="ks-stat">
                <span class="ks-stat__label"><?= esc($item['label']) ?></span>
                <span class="ks-stat__value"><?= $item['value'] ?></span>
                <span class="ks-stat__note"><?= esc($item['note']) ?></span>
            </div>
        <?php endforeach; ?>
    </section>

    <form id="exportForm" method="post" action="<?= base_url('export/kartu_stock') ?>">
        <input type="hidden" name="unit" id="expUnit" value="">
        <input type="hidden" name="status_ppn" id="expPpn" value="">
    </form>

    <div class="ks-toolbar">
        <div class="ks-filters">
            <label class="ks-field" for="unitFilter">
                <span>Unit</span>
                <select class="form-select ks-select" id="unitFilter" name="unit">
                    <option value="">Semua unit</option>
                    <?php foreach ($unit as $row) : ?>
                        <option value="<?= esc($row->idunit) ?>" <?= (string) $row->idunit === (string) $unitAktif ? 'selected' : '' ?>>
                            <?= esc($row->NAMA_UNIT) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="ks-field" for="ppnFilter">
                <span>Status PPN</span>
                <select class="form-select ks-select" id="ppnFilter" name="status_ppn">
                    <option value="">Semua</option>
                    <option value="PPN">PPN</option>
                    <option value="Non PPN">Non PPN</option>
                </select>
            </label>
        </div>

        <div class="ks-tools">
            <div class="ks-search">
                <iconify-icon icon="solar:magnifer-line-duotone" aria-hidden="true"></iconify-icon>
                <label class="visually-hidden" for="ksSearch">Cari barang</label>
                <input type="search" id="ksSearch" placeholder="Cari kode, nama, unit…" autocomplete="off">
            </div>

            <button type="button" id="exportBtn" class="ks-btn">
                <iconify-icon icon="solar:download-minimalistic-line-duotone" aria-hidden="true"></iconify-icon>
                <span>Export</span>
            </button>
        </div>
    </div>

    <section class="ks-sheet" aria-label="Daftar kartu stok">
        <div class="ks-table-wrap">
            <table class="ks-table" id="ks-datatable">
                <thead>
                    <tr>
                        <th scope="col">Kode</th>
                        <th scope="col">Barang</th>
                        <th scope="col">Unit</th>
                        <th scope="col">Kategori</th>
                        <th scope="col" class="ks-center">PPN</th>
                        <th scope="col" class="ks-num">Stok akhir</th>
                        <th scope="col" class="ks-num">Aksi</th>
                    </tr>
                </thead>
            </table>
        </div>
    </section>

<?php if (!empty($can_manage_produk_unit)) : ?>
    <input type="hidden" id="hpp-csrf" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
    <div class="modal fade" id="modal-hpp" tabindex="-1" aria-labelledby="modal-hpp-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="form-hpp" method="post" action="<?= base_url('kartu_stok/hpp') ?>" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="idbarang" id="hpp-idbarang">
                <input type="hidden" name="id_unit" id="hpp-idunit">
                <div class="modal-header">
                    <h2 class="modal-title" id="modal-hpp-label">Ubah HPP</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3" id="hpp-target"></p>
                    <label for="hpp-value" class="form-label">HPP</label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" class="form-control" id="hpp-value" name="hpp" min="1" step="1" required>
                    </div>
                    <div class="form-text">Hanya berlaku untuk unit ini. Riwayat transaksi lama tidak berubah.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modal-stok-warning" tabindex="-1" aria-labelledby="modal-stok-warning-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="modal-stok-warning-label">Belum bisa dinonaktifkan</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="ks-warn">
                        <iconify-icon icon="solar:danger-triangle-line-duotone" aria-hidden="true"></iconify-icon>
                        <div>
                            <p class="ks-warn__target" id="warn-target"></p>
                            <p class="ks-warn__text">Produk masih punya stok
                                <strong class="ks-warn__figure" id="warn-stok"></strong> di unit ini.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<script>
    $(document).ready(function() {
        const csrfName = '<?= csrf_token() ?>';

        function esc(s) {
            return $('<div>').text(s == null ? '' : s).html();
        }

        function attr(s) {
            return esc(s).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        const canManageUnit = <?= !empty($can_manage_produk_unit) ? 'true' : 'false' ?>;

        function fmt(n) {
            return new Intl.NumberFormat('id-ID').format(n || 0);
        }

        function money(n) {
            return 'Rp ' + fmt(n);
        }

        function detailRow(label, value, cls) {
            return '<div class="ks-detail__row"><dt>' + label + '</dt><dd' +
                (cls ? ' class="' + cls + '"' : '') + '>' + value + '</dd></div>';
        }

        function movementRow(label, n, sign) {
            const value = Number(n || 0);
            if (value === 0) {
                return detailRow(label, '<span class="ks-none">0</span>');
            }
            return detailRow(label, sign + ' ' + fmt(Math.abs(value)), sign === '+' ? 'is-pos' : 'is-neg');
        }

        function detailGroup(title, rows) {
            return '<section class="ks-detail__group"><h2>' + title + '</h2><dl class="ks-detail__list">' +
                rows + '</dl></section>';
        }

        function childHtml(d) {
            const identitas = detailRow('IMEI', d.imei ?
                    '<span class="ks-mono">' + esc(d.imei) + '</span>' :
                    '<span class="ks-none">Tidak ada IMEI</span>') +
                detailRow('Jenis / warna', esc(d.jenis_hp || '-') + ' / ' + esc(d.warna || '-')) +
                detailRow('Supplier', esc(d.nama_supplier || '-'));

            const nilai = detailRow('HPP (harga beli)', money(d.harga_beli) +
                    (canManageUnit ?
                        ' <button type="button" class="ks-edit-hpp js-edit-hpp"' +
                        ' data-idbarang="' + attr(d.idbarang) + '"' +
                        ' data-idunit="' + attr(d.id_unit) + '"' +
                        ' data-hpp="' + attr(d.harga_beli) + '"' +
                        ' data-nama="' + attr(d.nama_barang) + '"' +
                        ' data-unit="' + attr(d.nama_unit) + '">Ubah</button>' : '')) +
                detailRow('Harga jual', money(d.harga)) +
                detailRow('Stok awal', fmt(d.stok_awal)) +
                detailRow('Stok akhir', fmt(d.stok_akhir), 'is-strong');

            const masuk = movementRow('Pembelian', d.total_pembelian, '+') +
                movementRow('Mutasi masuk', d.total_mutasi_masuk, '+') +
                movementRow('Retur pelanggan', d.total_retur_pelanggan, '+');

            const keluar = movementRow('Penjualan', d.total_penjualan, '−') +
                movementRow('Mutasi keluar', d.total_mutasi_keluar, '−') +
                movementRow('Retur supplier', d.total_retur_supplier, '−');

            return '<div class="ks-detail"><div class="ks-detail__grid">' +
                detailGroup('Identitas', identitas) +
                detailGroup('Harga &amp; stok', nilai) +
                detailGroup('Stok masuk', masuk) +
                detailGroup('Stok keluar', keluar) +
                '</div></div>';
        }

        const table = $('#ks-datatable').DataTable({
            serverSide: true,
            processing: true,
            autoWidth: false,
            ajax: {
                url: '<?= base_url('kartu_stok/dt') ?>',
                type: 'GET',
                data: function(d) {
                    d.unit = $('#unitFilter').val();
                    d.status_ppn = $('#ppnFilter').val();
                    d[csrfName] = document.querySelector('input[name="' + csrfName + '"]') ?
                        document.querySelector('input[name="' + csrfName + '"]').value : '';
                }
            },
            order: [
                [0, 'asc']
            ],
            pageLength: 25,
            lengthMenu: [
                [10, 25, 50, 100],
                [10, 25, 50, 100]
            ],
            dom: 'rt<"ks-dt-foot"lip>',
            language: {
                processing: 'Memuat…',
                lengthMenu: 'Tampil _MENU_ baris',
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris',
                infoEmpty: 'Tidak ada baris',
                infoFiltered: '(dari _MAX_ baris)',
                zeroRecords: 'Tidak ada barang yang cocok dengan filter ini.',
                emptyTable: 'Belum ada data kartu stok.',
                paginate: {
                    previous: '‹',
                    next: '›'
                }
            },
            columns: [{
                    data: 'kode_barang',
                    render: function(data) {
                        return '<span class="ks-code">' + esc(data) + '</span>';
                    }
                },
                {
                    data: 'nama_barang',
                    render: function(data, type, row) {
                        let html = '<span class="ks-name">' + esc(data) + '</span>';
                        if (row.imei) {
                            html += '<span class="ks-sub ks-sub--mono">' + esc(row.imei) + '</span>';
                        }
                        return html;
                    }
                },
                {
                    data: 'nama_unit'
                },
                {
                    data: 'nama_kategori'
                },
                {
                    data: 'status_ppn',
                    className: 'ks-center',
                    render: function(data) {
                        return Number(data) === 1 ?
                            '<span class="ks-tag ks-tag--on">PPN</span>' :
                            '<span class="ks-tag ks-tag--off">Non PPN</span>';
                    }
                },
                {
                    data: 'stok_akhir',
                    className: 'ks-num',
                    render: function(data) {
                        const value = Number(data || 0);
                        const cls = value < 0 ? ' is-neg' : (value === 0 ? ' is-zero' : '');
                        return '<span class="ks-stock' + cls + '">' + fmt(value) + '</span>';
                    }
                },
                {
                    data: 'aktif',
                    className: 'ks-num',
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        const detail = '<button type="button" class="js-detail ks-detail-btn"' +
                            ' aria-label="Lihat detail ' + attr(row.nama_barang) + '">' +
                            '<iconify-icon icon="solar:alt-arrow-down-line-duotone" aria-hidden="true"></iconify-icon>' +
                            '</button>';

                        if (!canManageUnit) {
                            return '<div class="ks-actions">' + detail + '</div>';
                        }

                        const aktif = Number(data) === 1;
                        const status = '<button type="button" role="switch" aria-checked="' + (aktif ? 'true' : 'false') +
                            '" class="js-toggle-status ks-switch" data-aktif="' + (aktif ? 1 : 0) +
                            '" title="' + (aktif ? 'Nonaktifkan produk di unit ini' : 'Aktifkan produk di unit ini') + '">' +
                            '<span class="ks-switch__track"><span class="ks-switch__thumb"></span></span>' +
                            '<span class="ks-switch__label">' + (aktif ? 'Aktif' : 'Nonaktif') + '</span></button>';

                        return '<div class="ks-actions">' + detail + status + '</div>';
                    }
                }
            ],
            createdRow: function(row, data) {
                $(row).attr('data-idbarang', data.idbarang);
            }
        });

        let searchTimer = null;
        $('#ksSearch').on('input', function() {
            const value = this.value;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() {
                table.search(value).draw();
            }, 350);
        });

        $('#ks-datatable tbody').on('click', '.js-detail', function() {
            const tr = $(this).closest('tr');
            const row = table.row(tr);
            if (row.child.isShown()) {
                row.child.hide();
                tr.removeClass('shown');
            } else {
                row.child(childHtml(row.data())).show();
                tr.addClass('shown');
            }
        });

        $('#unitFilter, #ppnFilter').on('change', function() {
            table.draw();
        });

        $('#exportBtn').on('click', function() {
            $('#expUnit').val($('#unitFilter').val());
            $('#expPpn').val($('#ppnFilter').val());
            $('#exportForm').submit();
        });

        if (canManageUnit) {
            const modalHppEl = document.getElementById('modal-hpp');
            const modalHpp = new bootstrap.Modal(modalHppEl);
            const formHpp = document.getElementById('form-hpp');
            const modalWarn = new bootstrap.Modal(document.getElementById('modal-stok-warning'));

            $('#ks-datatable').on('click', '.js-edit-hpp', function() {
                const btn = $(this);
                $('#hpp-idbarang').val(btn.data('idbarang'));
                $('#hpp-idunit').val(btn.data('idunit'));
                $('#hpp-value').val(btn.data('hpp'));
                $('#hpp-target').text(btn.data('nama') + ' - ' + btn.data('unit'));
                modalHpp.show();
            });

            formHpp.addEventListener('submit', function(e) {
                e.preventDefault();

                fetch(formHpp.action, {
                        method: 'POST',
                        body: new FormData(formHpp),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(res) {
                        if (res.status !== 'success') {
                            alert(res.message || 'Gagal menyimpan HPP.');
                            return;
                        }

                        if (res.csrf) {
                            formHpp.querySelector('input[name="<?= csrf_token() ?>"]').value = res.csrf;
                        }

                        modalHpp.hide();
                        table.ajax.reload(null, false);
                    })
                    .catch(function() {
                        alert('Gagal menyimpan HPP.');
                    });
            });

            $('#ks-datatable').on('click', '.js-toggle-status', function() {
                const btn = $(this);
                const row = table.row(btn.closest('tr')).data();
                const aktifBaru = Number(btn.data('aktif')) === 1 ? 0 : 1;

                if (aktifBaru === 0 && Number(row.stok_akhir) !== 0) {
                    $('#warn-target').text(row.nama_barang + ' · ' + row.nama_unit);
                    $('#warn-stok').text(fmt(row.stok_akhir));
                    modalWarn.show();
                    return;
                }

                const body = new URLSearchParams();
                body.set('idbarang', row.idbarang);
                body.set('id_unit', row.id_unit);
                body.set('aktif', aktifBaru);
                body.set(csrfName, $('#hpp-csrf').val());

                fetch('<?= base_url('kartu_stok/status') ?>', {
                        method: 'POST',
                        body: body,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(res) {
                        if (res.status !== 'success') {
                            alert(res.message || 'Gagal mengubah status produk.');
                            return;
                        }

                        if (res.csrf) {
                            $('#hpp-csrf').val(res.csrf);
                        }

                        table.ajax.reload(null, false);
                    })
                    .catch(function() {
                        alert('Gagal mengubah status produk.');
                    });
            });
        }
    });
</script>
