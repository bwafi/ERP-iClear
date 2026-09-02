<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Pengambilan Service</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Pengambilan</a>
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
            <div class="col-lg-4 col-md-6">
                <label class="form-label fw-semibold mb-2">Search</label>
                <input type="text" id="searchBox" class="form-control" placeholder="Cari no service, nama, atau no HP...">
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
            <div class="col-lg-2 col-md-3 d-flex align-items-end">
                <button type="button" onclick="resetFilter()" class="btn btn-outline-secondary w-100">
                    <iconify-icon icon="solar:refresh-linear" width="18" height="18" class="me-1"></iconify-icon>
                    Reset
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
                        <h6 class="fs-4 fw-semibold mb-0">Tanggal Service Masuk</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Tanggal Diambil</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nama Pelanggan</h6>
                    </th>
                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Nomor Handphone</h6>
                    </th>

                    <th style="display: flex; justify-content: center;">
                        <h6 class="fs-4 fw-semibold mb-0">Unit</h6>
                    </th>

                    <th>
                        <h6 style="display: flex; justify-content: center;" class="fs-4 fw-semibold mb-0">Lama Waktu</h6>
                    </th>

                    <th>
                        <h6 class="fs-4 fw-semibold mb-0">Status garansi</h6>
                    </th>

                    <th style="display: flex; justify-content: center;">
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


<script>
    let dataTable;
    let searchTimeout;

    window.onload = function() {
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');
        const searchBox = document.getElementById('searchBox');
        const pageLength = document.getElementById('pageLength');

        const today = new Date();
        const fifteenDaysAgo = new Date();
        fifteenDaysAgo.setDate(today.getDate() - 15);

        const toDateInputValue = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        startDateInput.value = toDateInputValue(fifteenDaysAgo);
        endDateInput.value = toDateInputValue(today);

        if ($.fn.DataTable.isDataTable('#zero_config')) {
            $('#zero_config').DataTable().destroy();
        }

        dataTable = $('#zero_config').DataTable({
            serverSide: true,
            processing: true,
            scrollY: '50vh',
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
                url: '<?= base_url('sudah_diambil/ajax') ?>',
                type: 'POST',
                data: function(d) {
                    d.startDate = startDateInput.value;
                    d.endDate = endDateInput.value;
                    d.search = {value: searchBox.value};
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
                    data: 'tanggal_selesai',
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
                    data: 'unit_idunit',
                    render: function(data) {
                        const units = {
                            1: 'Probolinggo',
                            2: 'Jember',
                            3: 'Banyuwangi',
                            4: 'Pandaan'
                        };
                        return units[data] || data;
                    }
                },
                {
                    data: 'lama_service'
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        if (row.garansi_hari > 0 && row.tanggal_selesai) {
                            const tanggalSelesai = new Date(row.tanggal_selesai);
                            const tanggalAkhir = new Date(tanggalSelesai);
                            tanggalAkhir.setDate(tanggalAkhir.getDate() + parseInt(row.garansi_hari));
                            const today = new Date();

                            const formatDate = (date) => {
                                const day = String(date.getDate()).padStart(2, '0');
                                const month = String(date.getMonth() + 1).padStart(2, '0');
                                const year = date.getFullYear();
                                return `${day}-${month}-${year}`;
                            };

                            if (today > tanggalAkhir) {
                                return 'Kadaluarsa pada ' + formatDate(tanggalAkhir);
                            } else {
                                return row.garansi_hari + ' hari (aktif sampai ' + formatDate(tanggalAkhir) + ')';
                            }
                        }
                        return 'Tidak ada garansi';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                            <a href="<?= base_url('cetak/invoice_service/') ?>${row.idservice}">
                                <button type="button" class="btn btn-sm btn-danger" style="display: inline-flex; align-items: center;">
                                    <iconify-icon icon="solar:folder-favourite-bookmark-broken" width="24" height="24"></iconify-icon>
                                    Cetak Struk
                                </button>
                            </a>
                            <a href="<?= base_url('cetak/invoice_service/') ?>${row.idservice}?mode=thermal">
                                <button type="button" class="btn btn-sm btn-danger" style="display: inline-flex; align-items: center;">
                                    <iconify-icon icon="solar:folder-favourite-bookmark-broken" width="24" height="24"></iconify-icon>
                                    Cetak Struk (Thermal)
                                </button>
                            </a>
                            <button type="button" class="btn btn-wa" data-nohp="${row.no_hp}" data-nama="${row.nama_pelanggan}" style="width: 100px; height: 40px; background-color: greenyellow;">
                                <iconify-icon icon="solar:phone-bold" width="24" height="24"></iconify-icon>
                            </button>
                        `;
                    }
                }
            ],
            order: [[2, 'desc']]
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
    };

    function filterData() {
        dataTable.ajax.reload();
        document.getElementById('exportStartDate').value = document.getElementById('startDate').value;
        document.getElementById('exportEndDate').value = document.getElementById('endDate').value;
    }

    function resetFilter() {
        const today = new Date();
        const fifteenDaysAgo = new Date();
        fifteenDaysAgo.setDate(today.getDate() - 15);

        const toDateInputValue = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        document.getElementById('startDate').value = toDateInputValue(fifteenDaysAgo);
        document.getElementById('endDate').value = toDateInputValue(today);
        document.getElementById('searchBox').value = '';
        document.getElementById('exportStartDate').value = toDateInputValue(fifteenDaysAgo);
        document.getElementById('exportEndDate').value = toDateInputValue(today);
        dataTable.ajax.reload();
    }
</script>
