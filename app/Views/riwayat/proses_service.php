<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Proses Service</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Proses</a>
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
                <input type="text" id="searchBox" class="form-control" placeholder="Cari no service, nama, HP, tipe HP...">
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
                    class="btn btn-outline-secondary w-100 d-flex justify-content-center align-items-center gap-1">
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
                        <th style="display: none;">Rank</th>
                        <th>Prioritas</th>
                        <th>No Service</th>
                        <th>Tanggal Masuk</th>
                        <th>Nama Pelanggan</th>
                        <th>No HP</th>
                        <th>Tipe HP</th>
                        <th>Unit</th>
                        <th>Lama</th>
                        <th>Status Service</th>
                        <th>Detail</th>
                        <th>Ubah Status</th>
                        <th>Action</th>
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
            <form action="<?= base_url('service/dibatalkan') ?>" method="post">
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

<script>
    let dataTable;
    let searchTimeout;

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
                class: 'btn-light'
            },
            2: {
                text: 'Sedang Dicek',
                class: 'btn-primary'
            },
            3: {
                text: 'Sedang Dikerjakan',
                class: 'btn-success'
            },
            4: {
                text: 'Sedang Testing',
                class: 'btn-secondary'
            },
            5: {
                text: 'Menunggu Konfirmasi',
                class: 'btn-danger'
            },
            6: {
                text: 'Menunggu Sparepart',
                class: 'btn-warning'
            }
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
                url: '<?= base_url('proses_service/ajax') ?>',
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
                    data: 'rank',
                    visible: false
                },
                {
                    data: null,
                    orderable: false,
                    render: function(data, type, row) {
                        const isPrioritas = row.prioritas == 1;
                        const btnClass = isPrioritas ? 'btn-warning' : 'btn-outline-warning';
                        const btnText = isPrioritas ? '★ Prioritas' : '☆ Prioritaskan';
                        return `<button class="btn btn-sm toggle-prioritas-btn ${btnClass}" data-idservice="${row.idservice}" data-prioritas="${row.prioritas}">${btnText}</button>`;
                    }
                },
                {
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
                    data: 'no_hp'
                },
                {
                    data: 'tipe_hp'
                },
                {
                    data: 'unit_idunit',
                    render: function(data) {
                        const units = {
                            1: 'Probolinggo',
                            2: 'Jember',
                            3: 'Banyuwangi',
                            4: 'Pandaan'
                        };
                        return units[data] || 'Tidak diketahui';
                    }
                },
                {
                    data: 'lama_service'
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        if (row.tanggal_claim_garansi && row.tanggal_claim_garansi > '1971-01-01') {
                            return '<span class="badge bg-warning text-dark">Service Garansi</span>';
                        }
                        return '<span class="badge bg-success">Service Baru</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: function(data, type, row) {
                        const status = statusMap[row.status_proses] || {
                            text: 'Status Tidak Diketahui',
                            class: 'btn-outline-dark'
                        };
                        return `<button type="button" class="btn btn-sm ${status.class} btn-status-modal" data-idservice="${row.idservice}" data-status="${row.status_proses}">${status.text}</button>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                            <button class="btn btn-sm btn-success btn-bisa-diambil" data-idservice="${row.idservice}" data-jumlah_kerusakan="${row.jumlah_kerusakan}" data-jumlah_sparepart="${row.jumlah_sparepart}">Bisa Diambil</button>
                            <button class="btn btn-sm btn-success btn-sudah-diambil" data-idservice="${row.idservice}" data-jumlah_kerusakan="${row.jumlah_kerusakan}" data-jumlah_sparepart="${row.jumlah_sparepart}">Sudah Diambil</button>
                            <button class="btn btn-sm btn-danger btn-dibatalkan" data-idservice="${row.idservice}" data-jumlah_kerusakan="${row.jumlah_kerusakan}" data-jumlah_sparepart="${row.jumlah_sparepart}">Dibatalkan</button>
                        `;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: function(data, type, row) {
                        let editBtn = '';
                        if (row.status_service == 4) {
                            editBtn = '<button type="button" class="btn btn-sm btn-warning" disabled><iconify-icon icon="solar:clapperboard-edit-broken" width="20" height="20"></iconify-icon></button>';
                        } else if (row.tanggal_claim_garansi && row.tanggal_claim_garansi > '1971-01-01') {
                            editBtn = `<a href="<?= base_url('service_by_garansi/') ?>${row.idservice}"><button type="button" class="btn btn-sm btn-warning"><iconify-icon icon="solar:clapperboard-edit-broken" width="20" height="20"></iconify-icon></button></a>`;
                        } else {
                            editBtn = `<a href="<?= base_url('detail/riwayat_service/') ?>${row.idservice}"><button type="button" class="btn btn-sm btn-warning"><iconify-icon icon="solar:clapperboard-edit-broken" width="20" height="20"></iconify-icon></button></a>`;
                        }

                        return `
                            ${editBtn}
                            <a href="<?= base_url('cetak/invoice_service/') ?>${row.idservice}"><button type="button" class="btn btn-sm btn-danger"><iconify-icon icon="solar:folder-favourite-bookmark-broken" width="20" height="20"></iconify-icon> Cetak</button></a>
                            <a href="<?= base_url('cetak/invoice_service/') ?>${row.idservice}?mode=thermal"><button type="button" class="btn btn-sm btn-danger"><iconify-icon icon="solar:folder-favourite-bookmark-broken" width="20" height="20"></iconify-icon> Thermal</button></a>
                            <button type="button" class="btn btn-sm btn-wa" data-nohp="${row.no_hp}" data-nama="${row.nama_pelanggan}" style="background-color: greenyellow;"><iconify-icon icon="solar:phone-bold" width="20" height="20"></iconify-icon></button>
                        `;
                    }
                }
            ],
            order: [
                [0, 'asc'],
                [3, 'desc']
            ],
            columnDefs: [{
                targets: 0,
                visible: false
            }]
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

        $(document).on('click', '.toggle-prioritas-btn', function() {
            const idservice = $(this).data('idservice');
            const currentPrioritas = $(this).data('prioritas');
            const newPrioritas = currentPrioritas == 1 ? 0 : 1;
            const button = this;

            fetch('<?= base_url('toggle_prioritas') ?>', {
                    method: 'POST',
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                        "X-Requested-With": "XMLHttpRequest"
                    },
                    body: `idservice=${idservice}&prioritas=${newPrioritas}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success") {
                        dataTable.ajax.reload(null, false);
                    } else {
                        alert("Gagal mengubah prioritas");
                    }
                })
                .catch(err => console.error(err));
        });

        $(document).on('click', '.btn-bisa-diambil', function() {
            const idservice = $(this).data('idservice');
            const jumlahKerusakan = $(this).data('jumlah_kerusakan');
            const jumlahSparepart = $(this).data('jumlah_sparepart');

            $('#modal-bisa-diambil-idservice').val(idservice);

            if (jumlahKerusakan == 0 || jumlahSparepart == 0) {
                $('#modal-bisa-diambil-alert').html('<div class="alert alert-warning">Peringatan: Data kerusakan atau sparepart belum lengkap!</div>');
            } else {
                $('#modal-bisa-diambil-alert').html('');
            }

            $('#bisaDiambilModal').modal('show');
        });

        $(document).on('click', '.btn-sudah-diambil', function() {
            const idservice = $(this).data('idservice');
            const jumlahKerusakan = $(this).data('jumlah_kerusakan');
            const jumlahSparepart = $(this).data('jumlah_sparepart');

            $('#modal-sudah-diambil-idservice').val(idservice);

            if (jumlahKerusakan == 0 || jumlahSparepart == 0) {
                $('#modal-sudah-diambil-alert').html('<div class="alert alert-warning">Peringatan: Data kerusakan atau sparepart belum lengkap!</div>');
            } else {
                $('#modal-sudah-diambil-alert').html('');
            }

            $('#sudahDiambilModal').modal('show');
        });

        $(document).on('click', '.btn-dibatalkan', function() {
            const idservice = $(this).data('idservice');
            $('#modal-dibatalkan-idservice').val(idservice);
            $('#DibatalkanModal').modal('show');
        });

        $(document).on('click', '.btn-status-modal', function() {
            const idservice = $(this).data('idservice');
            const currentStatus = $(this).data('status');

            const modalHtml = `
                <div class="modal fade" id="statusModal-${idservice}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title">Ubah Status Proses</h4>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form action="<?= base_url('update_status_proses') ?>" method="post">
                                <div class="modal-body">
                                    <input type="hidden" name="idservice" value="${idservice}">
                                    <div class="row">
                                        ${Object.entries(statusMap).map(([value, info]) => `
                                            <div class="col-6 mb-2">
                                                <input class="form-check-input" type="radio" name="status_proses" id="status${value}-${idservice}" value="${value}" ${value == currentStatus ? 'checked' : ''}>
                                                <label class="form-check-label" for="status${value}-${idservice}">${info.text}</label>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary">Simpan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
            $(`#statusModal-${idservice}`).modal('show');
            $(`#statusModal-${idservice}`).on('hidden.bs.modal', function() {
                $(this).remove();
            });
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
