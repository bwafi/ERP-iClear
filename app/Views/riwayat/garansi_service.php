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
                <label class="form-label fw-semibold mb-2">Tanggal Awal</label>
                <input type="date" id="startDate" class="form-control">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label fw-semibold mb-2">Tanggal Akhir</label>
                <input type="date" id="endDate" class="form-control">
            </div>
            <div class="col-lg-4 col-md-6">
                <label class="form-label fw-semibold mb-2">Search</label>
                <input type="text" id="searchBox" class="form-control" placeholder="Cari no service, nama, HP, alamat...">
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
                <button type="button"
                    onclick="resetFilter()"
                    class="btn btn-outline-secondary w-100 d-flex justify-content-center align-items-center gap-1">
                    <iconify-icon icon="solar:refresh-linear" width="18" height="18"></iconify-icon>
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
                        <th>
                            <h6 class="fs-4 fw-semibold mb-0">Alamat</h6>
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

<!-- Modal Konfirmasi Claim Garansi (dipakai bersama semua baris) -->
<div class="modal fade" id="ClaimGaransiModal" tabindex="-1" aria-labelledby="ClaimGaransiModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="ClaimGaransiModalLabel">Konfirmasi Klaim Garansi</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <form action="<?= base_url('claim_garansi') ?>" method="post">
                <div class="modal-body">
                    <input type="hidden" name="idservice" id="modal-claim-idservice">
                    <p>Apakah Anda yakin service Akan klaim garansi?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Konfirmasi</button>
                </div>
            </form>
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

        const today = new Date();
        const fifteenDaysAgo = new Date();
        fifteenDaysAgo.setDate(today.getDate() - 15);

        const toDateInputValue = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        // Set default 15 hari ke belakang
        startDateInput.value = toDateInputValue(fifteenDaysAgo);
        endDateInput.value = toDateInputValue(today);
        document.getElementById('exportStartDate').value = startDateInput.value;
        document.getElementById('exportEndDate').value = endDateInput.value;

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
                    data: 'alamat'
                },
                {
                    data: 'garansi_hari',
                    render: function(data, type, row) {
                        if (row.garansi_hari > 0 && row.tanggal_selesai) {
                            const tanggalSelesai = new Date(row.tanggal_selesai);
                            const tanggalAkhir = new Date(tanggalSelesai);
                            tanggalAkhir.setDate(tanggalAkhir.getDate() + parseInt(row.garansi_hari));
                            const today = new Date();

                            const claimDate = row.tanggal_claim_garansi;
                            const isClaimed = claimDate && claimDate > '1971-01-01 00:00:00';

                            if (isClaimed) {
                                return 'Di klaim pada ' + formatDate(new Date(claimDate));
                            }

                            if (today > tanggalAkhir) {
                                return 'Tidak Claim (kadaluarsa pada ' + formatDate(tanggalAkhir) + ')';
                            }

                            return row.garansi_hari + ' hari (aktif sampai ' + formatDate(tanggalAkhir) + ')';
                        }

                        return 'Tidak ada garansi';
                    }
                },
                {
                    data: 'tanggal_claim_garansi',
                    render: function(data) {
                        if (data && data > '1971-01-01') {
                            return 'Klaim pada tanggal ' + formatDate(new Date(data));
                        }
                        return 'Tidak ada riwayat';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: function(data, type, row) {
                        const adaGaransi = parseInt(row.garansi_hari) > 0 && !!row.tanggal_selesai;
                        let masihDalamGaransi = false;

                        if (adaGaransi) {
                            const tanggalAkhir = new Date(row.tanggal_selesai);
                            tanggalAkhir.setDate(tanggalAkhir.getDate() + parseInt(row.garansi_hari));
                            tanggalAkhir.setHours(0, 0, 0, 0);

                            const hariIni = new Date();
                            hariIni.setHours(0, 0, 0, 0);

                            masihDalamGaransi = hariIni <= tanggalAkhir;
                        }

                        let buttons = '';

                        // Tombol Claim Garansi
                        if (adaGaransi && masihDalamGaransi) {
                            buttons += `<button type="button" class="btn btn-sm btn-danger btn-claim-garansi me-1" style="display: inline-flex; align-items: center;" data-idservice="${row.idservice}">
                                <iconify-icon icon="solar:folder-favourite-bookmark-broken" width="24" height="24"></iconify-icon>
                                Claim Garansi
                            </button>`;
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
                        buttons += `<button type="button" class="btn btn-wa btn-riwayat"
                            data-idservice="${row.idservice}"
                            style="height: 40px; background-color: antiquewhite;">
                            Riwayat Klaim
                        </button>`;

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

        // Buka modal konfirmasi claim untuk baris yang dipilih
        $(document).on('click', '.btn-claim-garansi', function() {
            $('#modal-claim-idservice').val($(this).data('idservice'));
            $('#ClaimGaransiModal').modal('show');
        });

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
            $('#modalRiwayatKlaim').modal('show');

            // request data via AJAX
            $.get('<?= base_url('riwayat-klaim') ?>/' + idservice, function(response) {
                $('#riwayat-klaim-content').html(response.html);
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
        document.getElementById('exportStartDate').value = document.getElementById('startDate').value;
        document.getElementById('exportEndDate').value = document.getElementById('endDate').value;
        dataTable.ajax.reload();
    }
</script>
