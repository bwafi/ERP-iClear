<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4">
        <div>
            <h4 class="fw-semibold mb-1">DP Service</h4>
            <p class="text-muted mb-0">Daftar penerimaan uang muka untuk setiap service.</p>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('proses_service') ?>">Service</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">DP Service</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (empty($dp_ledger_ada)): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
        <iconify-icon icon="solar:database-bold" width="22" class="mt-1 flex-shrink-0"></iconify-icon>
        <div>
            <strong>Ledger DP belum tersedia.</strong>
            <div>Jalankan migrasi tabel <code>service_dp</code> sebelum menggunakan menu ini.</div>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-lg-4">
            <?php $isHO = !empty($is_ho) && $is_ho; ?>
            <div class="row g-3 align-items-end">
                <div class="col-sm-6 col-lg-2">
                    <label for="dpStartDate" class="form-label fw-semibold mb-2">Tanggal Awal</label>
                    <input type="date" id="dpStartDate" class="form-control">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="dpEndDate" class="form-label fw-semibold mb-2">Tanggal Akhir</label>
                    <input type="date" id="dpEndDate" class="form-control">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="dpUnitFilter" class="form-label fw-semibold mb-2">Unit</label>
                    <select id="dpUnitFilter" class="form-select" <?= $isHO ? '' : 'disabled' ?>>
                        <?php if ($isHO): ?>
                            <option value="">Semua Unit</option>
                        <?php endif; ?>
                        <?php foreach (($unit ?? []) as $u): ?>
                            <option value="<?= (int) $u->idunit ?>" <?= (!$isHO && (int) $u->idunit === (int) ($user_unit ?? 0)) ? 'selected' : '' ?>>
                                <?= esc($u->NAMA_UNIT) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="dpSearch" class="form-label fw-semibold mb-2">Cari DP</label>
                    <input type="search" id="dpSearch" class="form-control" placeholder="Kode DP, service, pelanggan..." autocomplete="off">
                </div>
                <div class="col-sm-4 col-lg-1">
                    <label for="dpPageLength" class="form-label fw-semibold mb-2">Baris</label>
                    <select id="dpPageLength" class="form-select">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="col-sm-4 col-lg-2">
                    <button type="button" id="dpResetFilter" class="btn btn-outline-secondary w-100 d-inline-flex align-items-center justify-content-center gap-2">
                        <iconify-icon icon="solar:refresh-linear" width="18" height="18" aria-hidden="true"></iconify-icon>
                        Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card w-100 position-relative overflow-hidden">
        <div class="card-body p-3 p-lg-4">
            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
                <div>
                    <h5 class="fw-semibold mb-1">Riwayat Penerimaan DP</h5>
                    <small class="text-muted">Refund DP penuh sekaligus membatalkan service; penerimaan DP tetap tersimpan di histori.</small>
                </div>
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2">
                    <iconify-icon icon="solar:hand-money-bold" class="me-1" aria-hidden="true"></iconify-icon>
                    Uang Muka Service
                </span>
            </div>

            <?php if (empty($dp_refund_siap)): ?>
                <div class="alert alert-warning d-flex align-items-start gap-2 py-2 px-3" role="status">
                    <iconify-icon icon="solar:info-circle-bold" width="20" class="mt-1 flex-shrink-0" aria-hidden="true"></iconify-icon>
                    <span>Aksi refund belum siap. Jalankan migrasi refund DP agar kas keluar dan jurnal pembalik dapat dicatat.</span>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table border text-nowrap mb-0 align-middle" id="dpServiceTable" style="width:100%">
                    <thead class="text-dark fs-4">
                        <tr>
                            <th>Tanggal Terima</th>
                            <th>Kode DP</th>
                            <th>No. Service</th>
                            <th>Nama Pelanggan</th>
                            <th>Perangkat</th>
                            <th>Metode</th>
                            <th>Unit</th>
                            <th class="text-end">Jumlah DP</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="refundDpModal" tabindex="-1" aria-labelledby="refundDpModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= base_url('dp_service/refund') ?>" method="post" id="refundDpForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="dp_id" id="refundDpId">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title fw-semibold" id="refundDpModalTitle">Refund DP Service</h5>
                            <small class="text-muted" id="refundDpReference">-</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning py-2 px-3 mb-3" role="note">
                            Refund membatalkan service dan mengembalikan DP lewat metode/rekening asal. Jika ada sparepart, detail keluar, invoice kosong, dan jurnal terkait otomatis dihapus seperti aksi hard delete di Sparepart Keluar.
                        </div>
                        <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                            <span class="text-muted">Nominal refund</span>
                            <strong class="text-dark text-nowrap" id="refundDpAmount">Rp 0</strong>
                        </div>
                        <div class="d-flex justify-content-between gap-3 py-2 mb-3">
                            <span class="text-muted">Dikembalikan via</span>
                            <strong class="text-dark text-end" id="refundDpMethod">-</strong>
                        </div>
                        <div class="mb-3 d-none" id="refundDpReferenceWrap">
                            <label for="refundDpBankReference" class="form-label fw-semibold">Referensi transfer refund</label>
                            <input type="text" class="form-control" name="refund_referensi" id="refundDpBankReference" maxlength="100" placeholder="Nomor referensi transfer" autocomplete="off">
                            <small class="form-text text-muted">Wajib diisi setelah transfer dikirim.</small>
                        </div>
                        <div>
                            <label for="refundDpReason" class="form-label fw-semibold">Alasan refund</label>
                            <textarea class="form-control" name="refund_alasan" id="refundDpReason" rows="3" maxlength="120" required placeholder="Contoh: pelanggan membatalkan service"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kembali</button>
                        <button type="submit" class="btn btn-danger" id="refundDpSubmit">
                            <iconify-icon icon="solar:undo-left-bold" class="me-1" aria-hidden="true"></iconify-icon>
                            Konfirmasi Refund Penuh
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const startDate = document.getElementById('dpStartDate');
            const endDate = document.getElementById('dpEndDate');
            const unitFilter = document.getElementById('dpUnitFilter');
            const searchInput = document.getElementById('dpSearch');
            const pageLength = document.getElementById('dpPageLength');
            const resetButton = document.getElementById('dpResetFilter');
            const refundForm = document.getElementById('refundDpForm');
            const refundSubmit = document.getElementById('refundDpSubmit');
            const tableSelector = '#dpServiceTable';

            const toDateInputValue = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            const hariIni = new Date();
            const sebulanLalu = new Date();
            sebulanLalu.setDate(hariIni.getDate() - 30);
            startDate.value = toDateInputValue(sebulanLalu);
            endDate.value = toDateInputValue(hariIni);

            if ($.fn.DataTable.isDataTable(tableSelector)) {
                $(tableSelector).DataTable().destroy();
            }

            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char]));

            const formatTanggal = (value) => {
                const parts = String(value ?? '').slice(0, 10).split('-');
                return parts.length === 3 ? `${parts[2]}-${parts[1]}-${parts[0]}` : '';
            };

            const datatable = $(tableSelector).DataTable({
                serverSide: true,
                processing: true,
                scrollX: true,
                scrollCollapse: true,
                searching: false,
                lengthChange: false,
                pageLength: 10,
                order: [[0, 'desc']],
                language: {
                    processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Memuat...</span></div>',
                    emptyTable: 'Belum ada penerimaan DP pada rentang tanggal ini.',
                    info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ DP',
                    infoEmpty: 'Menampilkan 0 sampai 0 dari 0 DP',
                    infoFiltered: '(difilter dari _MAX_ total DP)',
                    paginate: { first: 'Pertama', last: 'Terakhir', next: 'Berikutnya', previous: 'Sebelumnya' }
                },
                ajax: {
                    url: '<?= base_url('dp_service/ajax') ?>',
                    type: 'POST',
                    data: function (data) {
                        data.startDate = startDate.value;
                        data.endDate = endDate.value;
                        data.unitFilter = unitFilter ? unitFilter.value : '';
                        data.search = { value: searchInput.value };
                    }
                },
                columns: [
                    { data: 'tanggal', render: (data) => escapeHtml(formatTanggal(data)) },
                    { data: 'kode_dp', render: (data) => `<span class="fw-semibold text-dark">${escapeHtml(data || '-')}</span>` },
                    { data: 'no_service', render: (data) => escapeHtml(data || '-') },
                    { data: 'nama_pelanggan', render: (data) => escapeHtml(data || '-') },
                    { data: 'tipe_hp', render: (data) => escapeHtml(data || '-') },
                    {
                        data: 'metode',
                        render: function (data, type, row) {
                            const transfer = String(data).toLowerCase() === 'transfer';
                            const label = transfer ? 'Transfer' : 'Tunai';
                            const bank = transfer ? (row.nama_bank || row.bank_idbank || '') : '';
                            return `<span class="badge ${transfer ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success'}">${label}</span>${bank ? `<small class="text-muted d-block mt-1">${escapeHtml(bank)}</small>` : ''}`;
                        }
                    },
                    { data: 'nama_unit', render: (data) => escapeHtml(data || '-') },
                    {
                        data: 'jumlah',
                        className: 'text-end fw-semibold',
                        render: (data) => `Rp ${new Intl.NumberFormat('id-ID').format(Number(data) || 0)}`
                    },
                    {
                        data: 'status',
                        render: function (data, type, row) {
                            if (Number(row.refund_idkas_keluar)) {
                                return '<span class="badge bg-success-subtle text-success">Direfund</span>';
                            }
                            const dibatalkan = String(data).toLowerCase() === 'dibatalkan';
                            return `<span class="badge ${dibatalkan ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning-emphasis'}">${dibatalkan ? 'Dibatalkan' : 'Aktif'}</span>`;
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function (data, type, row) {
                            const serviceAktif = [1, 2].includes(Number(row.status_service));
                            const prosesButton = serviceAktif
                                ? `<a class="btn btn-sm btn-outline-primary" href="<?= base_url('proses_service') ?>?search=${encodeURIComponent(row.no_service || '')}" title="Buka Proses Service untuk ${escapeHtml(row.no_service || '')}">
                                    <iconify-icon icon="solar:arrow-right-up-linear" class="me-1" aria-hidden="true"></iconify-icon>Proses Service
                                </a>`
                                : '';

                            if (Number(row.refund_idkas_keluar)) {
                                return `<div class="d-inline-flex flex-nowrap align-items-center gap-2">${prosesButton}<span class="text-success small">Kas keluar #${escapeHtml(row.refund_idkas_keluar)}</span></div>`;
                            }
                            if (String(row.status).toLowerCase() !== 'aktif') {
                                return prosesButton || '<span class="text-muted small">-</span>';
                            }
                            if (!<?= ! empty($dp_refund_siap) ? 'true' : 'false' ?>) {
                                return `<div class="d-inline-flex flex-nowrap align-items-center gap-2">${prosesButton}<span class="text-muted small">Refund belum siap</span></div>`;
                            }
                            if (![1, 2, 90].includes(Number(row.status_service))) {
                                return '<span class="text-muted small">Service siap diambil/selesai</span>';
                            }
                            const sparepartBelumDiretur = Number(row.sparepart_belum_diretur) || 0;
                            if (sparepartBelumDiretur > 0 && Number(row.sparepart_retur_siap) !== 1) {
                                return `<div class="d-inline-flex flex-nowrap align-items-center gap-2">${prosesButton}<span class="text-warning-emphasis small" title="Jalankan migrasi penghubung invoice service sebelum refund sparepart.">Skema retur perlu diperbarui</span></div>`;
                            }
                            return `<div class="d-inline-flex flex-nowrap align-items-center gap-2">${prosesButton}<button type="button" class="btn btn-sm btn-outline-danger btn-refund-dp"
                                data-dp-id="${Number(row.id)}"
                                data-kode="${escapeHtml(row.kode_dp)}"
                                data-service="${escapeHtml(row.no_service)}"
                                data-jumlah="${Number(row.jumlah) || 0}"
                                data-sparepart-pending="${sparepartBelumDiretur}"
                                data-metode="${escapeHtml(row.metode)}"
                                data-bank="${escapeHtml(row.nama_bank || row.bank_idbank || '')}"
                                data-bs-toggle="modal" data-bs-target="#refundDpModal">
                                <iconify-icon icon="solar:undo-left-bold" class="me-1" aria-hidden="true"></iconify-icon>${sparepartBelumDiretur > 0 ? 'Refund + Retur' : 'Refund'}
                            </button></div>`;
                        }
                    }
                ]
            });

            const reload = () => datatable.ajax.reload();
            [startDate, endDate, unitFilter].filter(Boolean).forEach((element) => {
                element.addEventListener('change', reload);
            });
            pageLength.addEventListener('change', function () {
                datatable.page.len(Number(this.value) || 10).draw();
            });

            let searchTimeout;
            searchInput.addEventListener('input', function () {
                window.clearTimeout(searchTimeout);
                searchTimeout = window.setTimeout(reload, 250);
            });

            resetButton.addEventListener('click', function () {
                startDate.value = toDateInputValue(sebulanLalu);
                endDate.value = toDateInputValue(hariIni);
                if (unitFilter && <?= $isHO ? 'true' : 'false' ?>) unitFilter.value = '';
                searchInput.value = '';
                pageLength.value = '10';
                datatable.page.len(10).draw();
            });

            $(document).on('click', '.btn-refund-dp', function () {
                const button = this;
                const metode = String(button.dataset.metode || '').toLowerCase();
                const bank = button.dataset.bank || '';
                document.getElementById('refundDpId').value = button.dataset.dpId || '';
                document.getElementById('refundDpReference').textContent = `${button.dataset.service || '-'} · ${button.dataset.kode || '-'}`;
                document.getElementById('refundDpAmount').textContent = `Rp ${new Intl.NumberFormat('id-ID').format(Number(button.dataset.jumlah) || 0)}`;
                document.getElementById('refundDpMethod').textContent = metode === 'transfer'
                    ? `Transfer${bank ? ` · ${bank}` : ''}`
                    : 'Tunai';

                const sparepartPending = Number(button.dataset.sparepartPending) || 0;
                refundSubmit.innerHTML = sparepartPending > 0
                    ? '<iconify-icon icon="solar:undo-left-bold" class="me-1" aria-hidden="true"></iconify-icon>Konfirmasi Refund + Retur Sparepart'
                    : '<iconify-icon icon="solar:undo-left-bold" class="me-1" aria-hidden="true"></iconify-icon>Konfirmasi Refund Penuh';

                const referenceWrap = document.getElementById('refundDpReferenceWrap');
                const referenceInput = document.getElementById('refundDpBankReference');
                const perluReferensi = metode === 'transfer';
                referenceWrap.classList.toggle('d-none', !perluReferensi);
                referenceInput.required = perluReferensi;
                referenceInput.value = '';
                document.getElementById('refundDpReason').value = '';
            });

            refundForm.addEventListener('submit', function () {
                refundSubmit.disabled = true;
                refundSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Memproses refund...';
            });
        });
    </script>
<?php endif; ?>
