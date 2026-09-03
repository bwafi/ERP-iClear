<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Riwayat Service</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Riwayat</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Service</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-lg-2 col-md-6">
                <label class="form-label fw-semibold mb-2">Tanggal Awal</label>
                <input type="date" id="startDate" class="form-control">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label fw-semibold mb-2">Tanggal Akhir</label>
                <input type="date" id="endDate" class="form-control">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label fw-semibold mb-2">Unit</label>
                <select id="unitFilter" class="form-select">
                    <option value="">Semua Unit</option>
                    <option value="Probolinggo">Probolinggo</option>
                    <option value="Jember">Jember</option>
                    <option value="Banyuwangi">Banyuwangi</option>
                    <option value="Pandaan">Pandaan</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label fw-semibold mb-2">Search</label>
                <input type="text" id="searchBox" class="form-control" placeholder="Cari no service, nama, HP...">
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

        <div class="d-flex justify-content-end mb-3">
            <form action="<?php echo base_url('riwayat_service/export') ?>" method="post" enctype="multipart/form-data" class="mb-0">
                <input type="hidden" name="tanggal_awal" id="exportStartDate">
                <input type="hidden" name="tanggal_akhir" id="exportEndDate">
                <button type="submit" class="btn btn-danger">
                    <iconify-icon icon="solar:export-broken" width="18" height="18" class="me-1"></iconify-icon>
                    Export
                </button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
                <thead class="text-dark fs-4">
                    <tr>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">No Service</h6>
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Tanggal Service</h6>
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Nama Pelanggan</h6>
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Tipe HP</h6>
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Keterangan</h6>
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Nomor Handphone</h6>
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Unit</h6>
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Status</h6>
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Action</h6>
                        </th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Bisa Diambil -->
<div class="modal fade" id="bisaDiambilModal" tabindex="-1" aria-labelledby="bisaDiambilModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="bisaDiambilModalLabel">Konfirmasi Bisa Diambil</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('service/bisa_diambil') ?>" method="post">
                <div class="modal-body">
                    <input type="hidden" name="idservice" id="modal-bisa-diambil-idservice">
                    <div id="modal-bisa-diambil-alert"></div>
                    <p>Apakah Anda yakin service ini bisa diambil?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="modal-bisa-diambil-submit">Konfirmasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Sudah Diambil -->
<div class="modal fade" id="sudahDiambilModal" tabindex="-1" aria-labelledby="sudahDiambilModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="sudahDiambilModalLabel">Konfirmasi Sudah Diambil</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('service/sudah_diambil') ?>" method="post">
                <div class="modal-body">
                    <input type="hidden" name="idservice" id="modal-sudah-diambil-idservice">
                    <div id="modal-sudah-diambil-alert"></div>
                    <p>Apakah Anda yakin service ini sudah diambil?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="modal-sudah-diambil-submit">Konfirmasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Dibatalkan -->
<div class="modal fade" id="DibatalkanModal" tabindex="-1" aria-labelledby="DibatalkanModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="DibatalkanModalLabel">Konfirmasi Pembatalan</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('dibatalkan/service') ?>" method="post">
                <div class="modal-body">
                    <input type="hidden" name="idservice" id="modal-dibatalkan-idservice">
                    <div id="modal-dibatalkan-alert"></div>
                    <p>Apakah Anda yakin ingin membatalkan service ini?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger" id="modal-dibatalkan-submit">Konfirmasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus Service (Admin Root Only) -->
<div class="modal fade" id="hapusServiceModal" tabindex="-1" aria-labelledby="hapusServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="hapusServiceModalLabel">Konfirmasi Hapus Service</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= base_url('delete_service') ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <input type="hidden" name="idservice" id="modal-hapus-service-idservice">
                    <div class="alert alert-danger">
                        <i class="ti ti-alert-triangle me-2"></i>
                        <strong>Peringatan!</strong> Data service akan dihapus permanen. Stok sparepart akan dikembalikan.
                    </div>
                    <p>Apakah Anda yakin ingin menghapus service <strong id="modal-hapus-service-no"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus Permanen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let dataTable;
    let searchTimeout;
    const is_admin = <?= $is_admin ? 'true' : 'false' ?>;

    window.onload = function() {
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');
        const searchBox = document.getElementById('searchBox');
        const pageLength = document.getElementById('pageLength');
        const unitFilter = document.getElementById('unitFilter');

        const today = new Date();
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(today.getDate() - 30);

        const toDateInputValue = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        startDateInput.value = toDateInputValue(thirtyDaysAgo);
        endDateInput.value = toDateInputValue(today);

        if ($.fn.DataTable.isDataTable('#zero_config')) {
            $('#zero_config').DataTable().destroy();
        }

        const statusMap = {
            1: {
                text: 'Belum Dicek',
                class: 'badge bg-info'
            },
            2: {
                text: 'Sedang Dicek',
                class: 'badge bg-warning'
            },
            3: {
                text: 'Sedang Dikerjakan',
                class: 'badge bg-primary'
            },
            4: {
                text: 'Selesai',
                class: 'badge bg-success'
            },
            5: {
                text: 'Dibatalkan',
                class: 'badge bg-danger'
            },
            6: {
                text: 'Bisa Diambil',
                class: 'badge bg-success'
            },
            7: {
                text: 'Sudah Diambil',
                class: 'badge bg-dark'
            }
        };

        const unitMap = {
            1: 'Probolinggo',
            2: 'Jember',
            3: 'Banyuwangi',
            4: 'Pandaan'
        };

        dataTable = $('#zero_config').DataTable({
            serverSide: true,
            processing: true,
            scrollY: '50vh',
            scrollX: true,
            scrollCollapse: true,
            searching: false,
            lengthChange: false,
            pageLength: 10,
            language: {
                processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>',
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
                url: '<?= base_url('riwayat_service/ajax') ?>',
                type: 'POST',
                data: function(d) {
                    d.startDate = startDateInput.value;
                    d.endDate = endDateInput.value;
                    d.unitFilter = unitFilter.value;
                    d.search = {
                        value: searchBox.value
                    };
                }
            },
            columns: [{
                    data: 'no_service'
                },
                {
                    data: 'created_at',
                    render: function(data) {
                        if (!data) return '';
                        const date = new Date(data);
                        const day = String(date.getDate()).padStart(2, '0');
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const year = date.getFullYear();
                        return `${day}-${month}-${year}`;
                    }
                },
                {
                    data: 'nama_pelanggan'
                },
                {
                    data: 'tipe_hp'
                },
                {
                    data: 'keterangan'
                },
                {
                    data: 'no_hp'
                },
                {
                    data: 'unit_idunit',
                    render: function(data) {
                        return unitMap[data] || 'Tidak diketahui';
                    }
                },
                {
                    data: 'status_service',
                    render: function(data) {
                        const status = statusMap[data] || {
                            text: 'Tidak diketahui',
                            class: 'badge bg-secondary'
                        };
                        return `<span class="${status.class}">${status.text}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: function(data, type, row) {
                        let buttons = '';

                        // Tombol Edit
                        if (row.status_service == 4) {
                            buttons += '<button type="button" class="btn btn-sm btn-warning me-1" disabled><iconify-icon icon="solar:clapperboard-edit-broken" width="20" height="20"></iconify-icon></button>';
                        } else if (row.tanggal_claim_garansi && row.tanggal_claim_garansi > '1971-01-01') {
                            buttons += `<a href="<?= base_url('service_by_garansi/') ?>${row.idservice}" class="me-1"><button type="button" class="btn btn-sm btn-warning"><iconify-icon icon="solar:clapperboard-edit-broken" width="20" height="20"></iconify-icon></button></a>`;
                        } else {
                            buttons += `<a href="<?= base_url('detail/riwayat_service/') ?>${row.idservice}" class="me-1"><button type="button" class="btn btn-sm btn-warning"><iconify-icon icon="solar:clapperboard-edit-broken" width="20" height="20"></iconify-icon></button></a>`;
                        }

                        // Tombol Cetak
                        buttons += `<a href="<?= base_url('cetak/invoice_service/') ?>${row.idservice}" class="me-1"><button type="button" class="btn btn-sm btn-danger"><iconify-icon icon="solar:folder-favourite-bookmark-broken" width="20" height="20"></iconify-icon> Cetak</button></a>`;
                        buttons += `<a href="<?= base_url('cetak/invoice_service/') ?>${row.idservice}?mode=thermal" class="me-1"><button type="button" class="btn btn-sm btn-danger"><iconify-icon icon="solar:folder-favourite-bookmark-broken" width="20" height="20"></iconify-icon> Thermal</button></a>`;

                        // Tombol Hapus (Admin Root Only)
                        if (is_admin) {
                            buttons += `<button type="button" class="btn btn-sm btn-dark btn-hapus-service" data-idservice="${row.idservice}" data-no_service="${row.no_service}"><iconify-icon icon="solar:trash-bin-trash-broken" width="20" height="20"></iconify-icon> Hapus</button>`;
                        }

                        return buttons;
                    }
                }
            ],
            order: [
                [1, 'desc']
            ]
        });

        searchBox.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                dataTable.ajax.reload();
            }, 500);
        });

        pageLength.addEventListener('change', function() {
            dataTable.page.len(this.value).draw();
        });

        startDateInput.addEventListener('change', filterData);
        endDateInput.addEventListener('change', filterData);
        unitFilter.addEventListener('change', filterData);

        // WhatsApp button
        $(document).on('click', '.btn-wa', function() {
            let nomor = $(this).data('nohp').toString().trim();
            let nama = $(this).data('nama').toString().trim();

            if (nomor.startsWith('0')) {
                nomor = '62' + nomor.substring(1);
            } else if (nomor.startsWith('+62')) {
                nomor = nomor.substring(1);
            }

            const waUrl = 'https://wa.me/' + nomor + '?text=' + encodeURIComponent("Halo, Kami dari welldone group ingin melakukan konfirmasi untuk service handphone atas nama " + nama);
            window.open(waUrl, '_blank');
        });

        // Hapus service button
        $(document).on('click', '.btn-hapus-service', function() {
            const idservice = $(this).data('idservice');
            const noService = $(this).data('no_service');

            $('#modal-hapus-service-idservice').val(idservice);
            $('#modal-hapus-service-no').text(noService);
            $('#hapusServiceModal').modal('show');
        });
    };

    function filterData() {
        dataTable.ajax.reload();
        document.getElementById('exportStartDate').value = document.getElementById('startDate').value;
        document.getElementById('exportEndDate').value = document.getElementById('endDate').value;
    }

    function resetFilter() {
        const today = new Date();
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(today.getDate() - 30);

        const toDateInputValue = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        document.getElementById('startDate').value = toDateInputValue(thirtyDaysAgo);
        document.getElementById('endDate').value = toDateInputValue(today);
        document.getElementById('unitFilter').value = '';
        document.getElementById('searchBox').value = '';
        document.getElementById('exportStartDate').value = toDateInputValue(thirtyDaysAgo);
        document.getElementById('exportEndDate').value = toDateInputValue(today);
        dataTable.ajax.reload();
    }
</script>
