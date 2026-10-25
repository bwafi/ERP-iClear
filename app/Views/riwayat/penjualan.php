<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3 p-4">
        <div>
            <h4 class="fw-semibold mb-1">Riwayat Penjualan</h4>
            <p class="text-muted mb-0 fs-3">Daftar invoice penjualan, detail barang, dan cetak struk.</p>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Riwayat</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Penjualan</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="card-body p-4">
        <div class="row g-3 align-items-end mb-4">
            <div class="col-xl-2 col-lg-3 col-md-6">
                <label class="form-label fw-semibold mb-2" for="startDate">Tanggal Awal</label>
                <input type="date" id="startDate" class="form-control">
            </div>
            <div class="col-xl-2 col-lg-3 col-md-6">
                <label class="form-label fw-semibold mb-2" for="endDate">Tanggal Akhir</label>
                <input type="date" id="endDate" class="form-control">
            </div>
            <div class="col-xl-3 col-lg-3 col-md-6">
                <label class="form-label fw-semibold mb-2" for="unitFilter">Unit</label>
                <select id="unitFilter" class="form-select">
                    <option value="">Semua Unit</option>
                    <?php foreach (($unit ?? []) as $u): ?>
                        <option value="<?= esc($u->NAMA_UNIT) ?>"><?= esc($u->NAMA_UNIT) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-lg-3 col-md-6">
                <label class="form-label fw-semibold mb-2" for="searchBox">Cari</label>
                <input type="text" id="searchBox" class="form-control" placeholder="Invoice / barang / unit...">
            </div>
            <div class="col-xl-1 col-lg-2 col-md-4">
                <label class="form-label fw-semibold mb-2" for="pageLength">Baris</label>
                <select id="pageLength" class="form-select">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="col-xl-2 col-lg-4 col-md-8 d-flex gap-2">
                <button type="button" id="resetBtn" class="btn btn-outline-secondary flex-fill">
                    <iconify-icon icon="solar:refresh-linear" width="18" height="18" class="align-text-bottom me-1"></iconify-icon>
                    Reset
                </button>
                <form action="<?= base_url('riwayat_penjualan/export') ?>" method="post" class="flex-fill mb-0" id="exportForm">
                    <input type="hidden" name="tanggal_awal" id="exportStartDate">
                    <input type="hidden" name="tanggal_akhir" id="exportEndDate">
                    <input type="hidden" name="unit" id="exportUnit">
                    <button type="submit" class="btn btn-danger w-100">
                        <iconify-icon icon="solar:export-broken" width="18" height="18" class="align-text-bottom me-1"></iconify-icon>
                        Export
                    </button>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table border text-nowrap mb-0 align-middle" id="penjualanTable">
                <thead class="text-dark fs-4">
                    <tr>
                        <th>Invoice</th>
                        <th>Tanggal</th>
                        <th>Barang</th>
                        <th>Unit</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="detailModalLabel">Detail Penjualan</h5>
                    <span class="text-muted fs-3" id="detailInvoice">-</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="detailLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Memuat...</span>
                    </div>
                </div>

                <div id="detailError" class="alert alert-danger d-none mb-0"></div>

                <div id="detailContent" class="d-none">
                    <div class="row g-3 mb-4">
                        <div class="col-md-3 col-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="text-muted fs-2 mb-1">Tanggal</div>
                                <div class="fw-semibold" id="detailTanggal">-</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="text-muted fs-2 mb-1">Unit</div>
                                <div class="fw-semibold" id="detailUnit">-</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="text-muted fs-2 mb-1">Pelanggan</div>
                                <div class="fw-semibold" id="detailPelanggan">-</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="text-muted fs-2 mb-1">Kasir</div>
                                <div class="fw-semibold" id="detailKasir">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="fs-4">
                                <tr>
                                    <th>Nama Barang</th>
                                    <th class="text-center">Jumlah</th>
                                    <th class="text-end">HPP</th>
                                    <th class="text-end">Harga Jual</th>
                                    <th class="text-end">Diskon</th>
                                    <th class="text-end">Sub Total</th>
                                </tr>
                            </thead>
                            <tbody id="detailItems"></tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <div style="min-width: 280px;">
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted">Sub Total</span>
                                <span id="detailSubtotal">Rp 0</span>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted">PPN</span>
                                <span id="detailPpn">Rp 0</span>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-semibold">Total</span>
                                <span class="fw-bold fs-5" id="detailTotal">Rp 0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    const unitFilter = document.getElementById('unitFilter');
    const searchBox = document.getElementById('searchBox');
    const pageLength = document.getElementById('pageLength');
    const exportForm = document.getElementById('exportForm');

    const strukUrl = <?= json_encode(base_url('riwayat_penjualan/struk/'), JSON_UNESCAPED_SLASHES) ?>;
    const detailUrl = <?= json_encode(base_url('riwayat_penjualan/detail/'), JSON_UNESCAPED_SLASHES) ?>;

    const escapeHtml = (value) => $('<div>').text(value ?? '').html();
    const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(Number(value) || 0);
    const formatRupiah = (value) => 'Rp ' + formatNumber(value);
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    function formatDate(value) {
        if (!value) return '-';
        const parts = String(value).slice(0, 10).split('-');
        if (parts.length !== 3) return value;
        return `${Number(parts[2])} ${monthNames[Number(parts[1]) - 1] || parts[1]} ${parts[0]}`;
    }

    function formatTime(value) {
        const time = String(value || '').slice(11, 16);
        return time || '';
    }

    function syncExport() {
        document.getElementById('exportStartDate').value = startDateInput.value;
        document.getElementById('exportEndDate').value = endDateInput.value;
        document.getElementById('exportUnit').value = unitFilter.value;
    }

    // Default 15 hari terakhir, sama seperti perilaku halaman sebelumnya.
    const today = new Date();
    const start = new Date();
    start.setDate(today.getDate() - 15);
    const toInputValue = (date) => {
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${date.getFullYear()}-${month}-${day}`;
    };
    startDateInput.value = toInputValue(start);
    endDateInput.value = toInputValue(today);
    syncExport();

    const table = $('#penjualanTable').DataTable({
        serverSide: true,
        processing: true,
        searching: false,
        lengthChange: false,
        pageLength: 10,
        autoWidth: false,
        order: [[1, 'desc']],
        language: {
            processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Memuat...</span></div>',
            emptyTable: 'Tidak ada data penjualan',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ invoice',
            infoEmpty: 'Tidak ada invoice',
            infoFiltered: '(difilter dari _MAX_ total invoice)',
            zeroRecords: 'Tidak ada invoice yang cocok',
            paginate: {
                first: 'Pertama',
                last: 'Terakhir',
                next: 'Selanjutnya',
                previous: 'Sebelumnya'
            }
        },
        ajax: {
            url: <?= json_encode(base_url('riwayat_penjualan/ajax'), JSON_UNESCAPED_SLASHES) ?>,
            type: 'POST',
            data: function(d) {
                d.startDate = startDateInput.value;
                d.endDate = endDateInput.value;
                d.unitFilter = unitFilter.value;
                d.search = { value: searchBox.value };
            }
        },
        columns: [
            {
                data: 'kode_invoice',
                render: function(data) {
                    return `<span class="font-monospace fw-semibold">${escapeHtml(data)}</span>`;
                }
            },
            {
                data: 'tanggal',
                render: function(data) {
                    const time = formatTime(data);
                    return `<div>${escapeHtml(formatDate(data))}</div>${time ? `<small class="text-muted">${escapeHtml(time)}</small>` : ''}`;
                }
            },
            {
                data: 'nama_barang',
                render: function(data, type, row) {
                    const extra = Number(row.jumlah_item) > 1
                        ? ` <span class="badge bg-secondary-subtle text-secondary">+${Number(row.jumlah_item) - 1}</span>`
                        : '';
                    return escapeHtml(data) + extra;
                }
            },
            {
                data: 'NAMA_UNIT',
                render: function(data) {
                    return escapeHtml(data || '-');
                }
            },
            {
                data: 'total',
                className: 'text-end fw-semibold',
                render: function(data) {
                    return formatRupiah(data);
                }
            },
            {
                data: null,
                orderable: false,
                className: 'text-end',
                render: function(data, type, row) {
                    const invoice = encodeURIComponent(row.kode_invoice);
                    let buttons = `<button type="button" class="btn btn-sm btn-primary js-detail" data-invoice="${escapeHtml(row.kode_invoice)}">
                        <iconify-icon icon="solar:folder-favourite-bookmark-broken" width="18" height="18" class="align-text-bottom me-1"></iconify-icon>
                        Detail
                    </button>`;

                    if (Number(row.id_pelanggan) > 0) {
                        buttons += `<a href="${strukUrl}${invoice}" target="_blank" rel="noopener noreferrer"
                            class="btn btn-sm btn-outline-danger" title="Cetak struk A4">
                            <iconify-icon icon="solar:printer-minimalistic-broken" width="18" height="18"></iconify-icon>
                        </a>
                        <a href="${strukUrl}${invoice}?mode=thermal" target="_blank" rel="noopener noreferrer"
                            class="btn btn-sm btn-outline-danger" title="Cetak struk thermal">
                            <iconify-icon icon="solar:printer-broken" width="18" height="18"></iconify-icon>
                        </a>`;
                    }

                    return `<div class="d-flex justify-content-end gap-1">${buttons}</div>`;
                }
            }
        ]
    });

    let searchTimeout;
    searchBox.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            table.ajax.reload();
        }, 400);
    });

    pageLength.addEventListener('change', function() {
        table.page.len(Number(this.value)).draw();
    });

    function reload() {
        syncExport();
        table.ajax.reload();
    }

    startDateInput.addEventListener('change', reload);
    endDateInput.addEventListener('change', reload);
    unitFilter.addEventListener('change', reload);

    document.getElementById('resetBtn').addEventListener('click', function() {
        startDateInput.value = '';
        endDateInput.value = '';
        unitFilter.value = '';
        searchBox.value = '';
        reload();
    });

    // Pastikan filter tanggal/unit ikut terkirim saat export.
    exportForm.addEventListener('submit', syncExport);

    let detailRequest = 0;

    $(document).on('click', '.js-detail', function() {
        const invoice = this.dataset.invoice;
        const requestId = ++detailRequest;

        document.getElementById('detailInvoice').textContent = invoice;
        document.getElementById('detailLoading').classList.remove('d-none');
        document.getElementById('detailContent').classList.add('d-none');
        document.getElementById('detailError').classList.add('d-none');

        bootstrap.Modal.getOrCreateInstance(document.getElementById('detailModal')).show();

        fetch(detailUrl + encodeURIComponent(invoice), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Detail invoice gagal dimuat.');
                return response.json();
            })
            .then(function(data) {
                if (requestId !== detailRequest) return;

                document.getElementById('detailTanggal').textContent = formatDate(data.tanggal);
                document.getElementById('detailUnit').textContent = data.nama_unit || '-';
                document.getElementById('detailPelanggan').textContent = data.nama_pelanggan || '-';
                document.getElementById('detailKasir').textContent = data.kasir || '-';

                const items = Array.isArray(data.items) ? data.items : [];
                let subtotal = 0;
                document.getElementById('detailItems').innerHTML = items.map(function(item) {
                    subtotal += Number(item.sub_total) || 0;
                    return `<tr>
                        <td>
                            <div class="fw-semibold">${escapeHtml(item.nama_barang)}</div>
                            ${item.imei ? `<small class="text-muted">${escapeHtml(item.imei)}</small>` : ''}
                        </td>
                        <td class="text-center">${formatNumber(item.jumlah)}</td>
                        <td class="text-end">${formatRupiah(item.hpp)}</td>
                        <td class="text-end">${formatRupiah(item.harga)}</td>
                        <td class="text-end">${formatRupiah(item.diskon)}</td>
                        <td class="text-end">${formatRupiah(item.sub_total)}</td>
                    </tr>`;
                }).join('');

                document.getElementById('detailSubtotal').textContent = formatRupiah(subtotal);
                document.getElementById('detailPpn').textContent = formatRupiah(data.total_ppn);
                document.getElementById('detailTotal').textContent = formatRupiah(data.total);

                document.getElementById('detailLoading').classList.add('d-none');
                document.getElementById('detailContent').classList.remove('d-none');
            })
            .catch(function(error) {
                if (requestId !== detailRequest) return;
                const errorBox = document.getElementById('detailError');
                errorBox.textContent = error.message;
                errorBox.classList.remove('d-none');
                document.getElementById('detailLoading').classList.add('d-none');
            });
    });

    // Hindari form export ter-submit saat menekan Enter di kotak pencarian.
    searchBox.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') event.preventDefault();
    });
});
</script>
