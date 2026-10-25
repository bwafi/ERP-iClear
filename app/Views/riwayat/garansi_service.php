 <div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Garansi Service</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Garansi</a>
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
                <label class="form-label fw-semibold mb-2">Tanggal Selesai (dari)</label>
                <input type="date" id="startDate" class="form-control">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label fw-semibold mb-2">Tanggal Selesai (sampai)</label>
                <input type="date" id="endDate" class="form-control">
            </div>
            <?php if (!empty($can_see_all_units)): ?>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label fw-semibold mb-2">Unit</label>
                    <select id="unitFilter" class="form-select">
                        <option value="">Semua Unit</option>
                        <?php foreach (($unit ?? []) as $u): ?>
                            <option value="<?= (int) $u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-lg-3 col-md-6">
                <label class="form-label fw-semibold mb-2">Search</label>
                <input type="text" id="searchBox" class="form-control" placeholder="Cari no service, nama, HP, kecamatan/kabupaten...">
            </div>
            <div class="col-lg-1 col-md-3">
                <label class="form-label fw-semibold mb-2">Show</label>
                <select id="pageLength" class="form-select">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 d-flex align-items-end">
                <button type="button"
                    onclick="resetFilter()"
                    class="btn btn-outline-secondary w-100 d-flex justify-content-center align-items-center gap-1">
                    <iconify-icon icon="solar:refresh-linear" width="18" height="18"></iconify-icon>
                    Reset
                </button>
            </div>
        </div>

        <div class="d-flex justify-content-end mb-3">
            <form action="<?php echo base_url('riwayat_service_garansi/export') ?>" method="post" enctype="multipart/form-data" class="mb-0">
                <input type="hidden" name="sumber" value="garansi">
                <input type="hidden" name="tanggal_awal" id="exportStartDate">
                <input type="hidden" name="tanggal_akhir" id="exportEndDate">
                <input type="hidden" name="unit" id="exportUnit">
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
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Kecamatan / Kabupaten</h6>
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Unit</h6>
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Status garansi</h6>
                        </th>
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Riwayat garansi</h6>
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

<!-- Modal Global Riwayat Klaim -->
<div class="modal fade" id="modalRiwayatKlaim" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Riwayat Klaim</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="riwayat-klaim-content" class="text-center">
                    <span class="text-muted">Pilih data untuk melihat riwayat klaim...</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    let dataTable;
    let searchTimeout;

    const formatDate = (date) => {
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        return `${day}-${month}-${year}`;
    };

    window.onload = function() {
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');
        const searchBox = document.getElementById('searchBox');
        const pageLength = document.getElementById('pageLength');
        const unitFilter = document.getElementById('unitFilter');

        document.getElementById('exportStartDate').value = '';
        document.getElementById('exportEndDate').value = '';

        if ($.fn.DataTable.isDataTable('#zero_config')) {
            $('#zero_config').DataTable().destroy();
        }

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
                url: '<?= base_url('garansi_service/ajax') ?>',
                type: 'POST',
                data: function(d) {
                    d.startDate = startDateInput.value;
                    d.endDate = endDateInput.value;
                    d.unitFilter = unitFilter ? unitFilter.value : '';
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
                        return formatDate(new Date(data));
                    }
                },
                {
                    data: 'tanggal_selesai',
                    render: function(data) {
                        if (!data) return '';
                        return formatDate(new Date(data));
                    }
                },
                {
                    data: 'nama_pelanggan'
                },
                {
                    data: 'no_hp'
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        const kecamatan = (row.kecamatan ?? '').trim();
                        const kabupaten = (row.kabupaten ?? '').trim();
                        if (!kecamatan && !kabupaten) {
                            return '-';
                        }
                        const baris = [];
                        if (kecamatan) {
                            baris.push(`<span>${kecamatan}</span>`);
                        }
                        if (kabupaten) {
                            baris.push(`<span class="text-muted fs-2">${kabupaten}</span>`);
                        }
                        return `<div class="d-flex flex-column lh-sm">${baris.join('')}</div>`;
                    }
                },
                {
                    data: 'nama_unit'
                },
                {
                    data: 'status_garansi',
                    orderable: false
                },
                {
                    data: 'riwayat_klaim',
                    orderable: false
                },
                {
                    data: null,
                    orderable: false,
                    render: function(data, type, row) {
                        let buttons = '';

                        if (row.claimable) {
                            buttons += `<a href="<?= base_url('claim_garansi/detail') ?>/${row.idservice}" class="btn btn-sm btn-danger me-1" style="display: inline-flex; align-items: center;">
                                <iconify-icon icon="solar:folder-favourite-bookmark-broken" width="24" height="24"></iconify-icon>
                                Claim Garansi
                            </a>`;
                        } else {
                            buttons += `<button type="button" class="btn btn-sm btn-danger me-1" style="display: inline-flex; align-items: center;" disabled>
                                <iconify-icon icon="solar:folder-favourite-bookmark-broken" width="24" height="24"></iconify-icon>
                                Claim Garansi
                            </button>`;
                        }

                        // Tombol WhatsApp
                        buttons += `<button type="button" class="btn btn-wa me-1"
                            data-nohp="${row.no_hp ?? ''}"
                            data-nama="${row.nama_pelanggan ?? ''}"
                            style="width: 100px; height: 40px; background-color: greenyellow;">
                            <iconify-icon icon="solar:phone-bold" width="24" height="24"></iconify-icon>
                        </button>`;

                        // Tombol Riwayat Klaim
                        if (row.claimed) {
                            buttons += `<button type="button" class="btn btn-wa btn-riwayat"
                                data-idservice="${row.idservice}"
                                style="height: 40px; background-color: antiquewhite;">
                                Riwayat Klaim
                            </button>`;
                        }

                        return buttons;
                    }
                }
            ],
            order: [
                [2, 'desc']
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
        if (unitFilter) {
            unitFilter.addEventListener('change', filterData);
        }

        // WhatsApp button (delegated agar tetap jalan untuk baris dari server)
        $(document).on('click', '.btn-wa', function() {
            if (!this.dataset.nohp) {
                return;
            }

            let nomor = this.dataset.nohp.trim();
            let nama = (this.dataset.nama ?? '').trim();

            // Normalisasi nomor HP
            if (nomor.startsWith('0')) {
                nomor = '62' + nomor.substring(1);
            } else if (nomor.startsWith('+62')) {
                nomor = nomor.substring(1); // hapus +
            }

            const waUrl = 'https://wa.me/' + nomor + '?text=' + encodeURIComponent("Halo, Kami dari welldone group ingin melakukan konfirmasi untuk service handphone atas nama " + nama);
            window.open(waUrl, '_blank');
        });

        // Riwayat klaim (delegated)
        $(document).on('click', '.btn-riwayat', function() {
            const idservice = $(this).data('idservice');

            // tampilkan loading
            $('#riwayat-klaim-content').html('<div class="text-center p-3">Loading...</div>');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRiwayatKlaim')).show();

            // request data via AJAX
            $.get('<?= base_url('riwayat-klaim') ?>/' + idservice, function(response) {
                $('#riwayat-klaim-content').html(response.html);
            }).fail(function() {
                $('#riwayat-klaim-content').html('<div class="alert alert-danger">Gagal memuat riwayat klaim.</div>');
            });
        });
    };

    function filterData() {
        dataTable.ajax.reload();
        document.getElementById('exportStartDate').value = document.getElementById('startDate').value;
        document.getElementById('exportEndDate').value = document.getElementById('endDate').value;
        const unitFilter = document.getElementById('unitFilter');
        document.getElementById('exportUnit').value = unitFilter ? unitFilter.value : '';
    }

    function resetFilter() {
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';
        document.getElementById('searchBox').value = '';
        const unitFilter = document.getElementById('unitFilter');
        if (unitFilter) {
            unitFilter.value = '';
        }
        document.getElementById('exportStartDate').value = '';
        document.getElementById('exportEndDate').value = '';
        document.getElementById('exportUnit').value = '';
        dataTable.ajax.reload();
    }
</script>
