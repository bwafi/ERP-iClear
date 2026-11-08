<!-- Page Breadcrumb & Header -->
<div class="card shadow-none position-relative overflow-hidden mb-4 bg-light-subtle border">
    <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4">
        <div>
            <h4 class="fw-semibold mb-1 text-dark">Kartu Stok</h4>
            <p class="fs-3 text-muted mb-0">Rekapitulasi stok per barang & unit (pembelian, penjualan, retur, mutasi)</p>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 bg-transparent p-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Stok</a>
                </li>
                <li class="breadcrumb-item text-primary fw-medium" aria-current="page">Kartu Stok</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Summary Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-none border mb-0 h-100 hover-shadow transition">
            <div class="card-body d-flex align-items-center gap-3 p-4">
                <span class="btn p-3 bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0">
                    <iconify-icon icon="solar:box-minimalistic-line-duotone" class="fs-7"></iconify-icon>
                </span>
                <div>
                    <h5 class="fs-5 fw-bold mb-1 text-dark"><?= number_format((float)$summary->total_barang, 0, ',', '.') ?></h5>
                    <span class="fs-2 text-muted fw-medium d-block">Total Barang</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-none border mb-0 h-100 hover-shadow transition">
            <div class="card-body d-flex align-items-center gap-3 p-4">
                <span class="btn p-3 bg-info-subtle text-info rounded-3 d-flex align-items-center justify-content-center flex-shrink-0">
                    <iconify-icon icon="solar:buildings-2-line-duotone" class="fs-7"></iconify-icon>
                </span>
                <div>
                    <h5 class="fs-5 fw-bold mb-1 text-dark"><?= number_format((float)$summary->total_unit, 0, ',', '.') ?></h5>
                    <span class="fs-2 text-muted fw-medium d-block">Total Unit</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-none border mb-0 h-100 hover-shadow transition">
            <div class="card-body d-flex align-items-center gap-3 p-4">
                <span class="btn p-3 bg-warning-subtle text-warning rounded-3 d-flex align-items-center justify-content-center flex-shrink-0">
                    <iconify-icon icon="solar:layers-minimalistic-line-duotone" class="fs-7"></iconify-icon>
                </span>
                <div>
                    <h5 class="fs-5 fw-bold mb-1 text-dark"><?= number_format((float)$summary->total_stok, 0, ',', '.') ?></h5>
                    <span class="fs-2 text-muted fw-medium d-block">Total Stok Akhir</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card shadow-none border mb-0 h-100 hover-shadow transition">
            <div class="card-body d-flex align-items-center gap-3 p-4">
                <span class="btn p-3 bg-success-subtle text-success rounded-3 d-flex align-items-center justify-content-center flex-shrink-0">
                    <iconify-icon icon="solar:wallet-money-line-duotone" class="fs-7"></iconify-icon>
                </span>
                <div>
                    <h5 class="fs-5 fw-bold mb-1 text-dark"><?= 'Rp ' . number_format((float)$summary->nilai_stok, 0, ',', '.') ?></h5>
                    <span class="fs-2 text-muted fw-medium d-block">Nilai Stok (HPP)</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Export Form -->
<form id="exportForm" method="post" action="<?= base_url('export/kartu_stock') ?>">
    <input type="hidden" name="unit" id="expUnit" value="">
    <input type="hidden" name="status_ppn" id="expPpn" value="">
</form>

<!-- Main Table Card -->
<div class="card w-100 shadow-none border position-relative overflow-hidden">
    <div class="card-body p-4">
        <!-- Filter Controls Toolbar -->
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 p-3 bg-body-tertiary rounded-3 border">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <label class="fw-semibold fs-3 text-dark text-nowrap" for="unitFilter">Unit:</label>
                    <select name="unit" id="unitFilter" class="form-select form-select-sm bg-white" style="width: 160px;">
                        <option value="">Semua Unit</option>
                        <?php
                        $selectedUnit = session('ID_UNIT');
                        foreach ($unit as $row) {
                            $selected = ($row->idunit == $selectedUnit) ? 'selected' : '';
                            echo '<option value="' . esc($row->idunit) . '" ' . $selected . '>' . esc($row->NAMA_UNIT) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="fw-semibold fs-3 text-dark text-nowrap" for="ppnFilter">Status PPN:</label>
                    <select name="status_ppn" id="ppnFilter" class="form-select form-select-sm bg-white" style="width: 140px;">
                        <option value="">Semua</option>
                        <option value="PPN">PPN</option>
                        <option value="Non PPN">Non PPN</option>
                    </select>
                </div>
            </div>
            <button type="button" id="exportBtn" class="btn btn-danger btn-sm d-inline-flex align-items-center gap-2 px-3 py-2 shadow-sm">
                <iconify-icon icon="solar:export-broken" width="18" height="18"></iconify-icon>
                <span>Export Data</span>
            </button>
        </div>

        <!-- DataTable Container -->
        <div class="table-responsive">
            <table class="table border text-nowrap mb-0 align-middle w-100" id="ks-datatable">
                <thead class="table-light text-dark fs-4">
                    <tr>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Unit</th>
                        <th>Kategori</th>
                        <th>Supplier</th>
                        <th>Status PPN</th>
                        <th class="text-end">Stok Akhir</th>
                        <th class="text-center">Detail</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($can_manage_produk_unit)): ?>
<input type="hidden" id="hpp-csrf" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
<div class="modal fade" id="modal-hpp" tabindex="-1" aria-labelledby="modal-hpp-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="form-hpp" method="post" action="<?= base_url('kartu_stok/hpp') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="idbarang" id="hpp-idbarang">
            <input type="hidden" name="id_unit" id="hpp-idunit">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-hpp-label">Ubah HPP</h5>
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
<?php endif; ?>

<!-- Script Configurations -->
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

        function childHtml(d) {
            const rows = [
                ['IMEI', d.imei ? esc(d.imei) : 'Tidak ada IMEI'],
                ['Jenis / Warna', esc(d.jenis_hp || '-') + ' / ' + esc(d.warna || '-')],
                ['HPP (Harga Beli)', money(d.harga_beli) + (canManageUnit ? ' <button type="button" class="btn btn-sm btn-light-primary text-primary ms-2 py-0 px-2 js-edit-hpp" data-idbarang="' + attr(d.idbarang) + '" data-idunit="' + attr(d.id_unit) + '" data-hpp="' + attr(d.harga_beli) + '" data-nama="' + attr(d.nama_barang) + '" data-unit="' + attr(d.nama_unit) + '">Ubah</button>' : '')],
                ['Harga Jual', money(d.harga)],
                ['Stok Awal', fmt(d.stok_awal)],
                ['Stok Akhir', '<b>' + fmt(d.stok_akhir) + '</b>'],
                ['Total Pembelian', fmt(d.total_pembelian)],
                ['Total Penjualan', fmt(d.total_penjualan)],
                ['Total Mutasi Masuk', fmt(d.total_mutasi_masuk)],
                ['Total Mutasi Keluar', fmt(d.total_mutasi_keluar)],
                ['Total Retur Pelanggan', fmt(d.total_retur_pelanggan)],
                ['Total Retur Supplier', fmt(d.total_retur_supplier)],
            ];
            let html = '<div class="p-3 bg-white border rounded-2 my-2"><table class="table table-sm table-borderless mb-0"><tbody>';
            rows.forEach(function(r) {
                html += '<tr><td class="text-muted fw-medium py-1 px-2" style="width: 200px;">' + r[0] +
                    '</td><td class="py-1 px-2 text-dark fw-normal">' + r[1] + '</td></tr>';
            });
            html += '</tbody></table></div>';
            return html;
        }

        const table = $('#ks-datatable').DataTable({
            serverSide: true,
            processing: true,
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
            searchDelay: 500,
            scrollX: true,
            columns: [{
                    data: 'kode_barang'
                },
                {
                    data: 'nama_barang',
                    render: function(data, type, row) {
                        let html = '<span class="fw-medium text-dark">' + esc(data) + '</span>';
                        if (row.imei) {
                            html += '<br><small class="text-muted font-monospace">' + esc(row.imei) + '</small>';
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
                    data: 'nama_supplier',
                    orderable: false
                },
                {
                    data: 'status_ppn',
                    className: 'text-center',
                    render: function(data) {
                        return data == 1 ?
                            '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">PPN</span>' :
                            '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">Non PPN</span>';
                    }
                },
                {
                    data: 'stok_akhir',
                    className: 'text-end fw-semibold text-dark',
                    render: function(data) {
                        return fmt(data);
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    searchable: false,
                    defaultContent: '<button class="js-detail btn btn-sm btn-light-primary text-primary d-inline-flex align-items-center justify-content-center p-2 rounded-circle transition"><iconify-icon icon="solar:alt-arrow-down-line-duotone" class="fs-6"></iconify-icon></button>'
                },
                {
                    data: 'aktif',
                    className: 'text-center',
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        if (!canManageUnit) {
                            return '<span class="text-muted">-</span>';
                        }
                        const aktif = Number(data) === 1;
                        const badge = aktif ?
                            '<span class="badge bg-success-subtle text-success border border-success-subtle me-1">Aktif</span>' :
                            '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle me-1">Nonaktif</span>';
                        const tombol = '<button type="button" class="js-toggle-status btn btn-sm py-1 px-2 ' +
                            (aktif ? 'btn-light-danger text-danger' : 'btn-light-success text-success') +
                            '" data-aktif="' + (aktif ? 1 : 0) + '">' + (aktif ? 'Nonaktifkan' : 'Aktifkan') + '</button>';
                        return badge + tombol;
                    }
                }
            ],
            createdRow: function(row, data, dataIndex) {
                $(row).attr('data-idbarang', data.idbarang);
            }
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
                    alert('Produk masih punya stok ' + fmt(row.stok_akhir) + ' di unit ini. '
                        + 'Sesuaikan stok lewat stok opname sebelum menonaktifkan.');
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
