<?= view('riwayat/_proses_service_theme') ?>
<?php
$showUnitFilter = !empty($is_ho) && $is_ho;
$unitLabel = $showUnitFilter ? 'Semua unit' : (session('NAMA_UNIT') ?: 'Unit Anda');
?>

<div class="ps-page">
    <header class="ps-head">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="ps-crumb">
                    <li><a href="<?= base_url('/') ?>">Proses</a></li>
                    <li aria-hidden="true">/</li>
                    <li aria-current="page">Service</li>
                </ol>
            </nav>
            <h1 class="ps-title">Proses Service</h1>
            <p>Antrean service aktif. Urut dari service garansi, prioritas, lalu tanggal masuk terbaru.</p>
        </div>
        <div class="ps-head__meta">
            <span class="ps-chip ps-chip--accent">
                <iconify-icon icon="solar:clipboard-list-linear" width="16" height="16"></iconify-icon>
                <strong id="psCount">–</strong> service
            </span>
            <span class="ps-chip">
                <iconify-icon icon="<?= $showUnitFilter ? 'solar:map-point-linear' : 'solar:lock-keyhole-minimalistic-linear' ?>" width="16" height="16"></iconify-icon>
                <span id="psUnitLabel"><?= esc($unitLabel) ?></span>
            </span>
            <span class="ps-chip">
                <iconify-icon icon="solar:calendar-linear" width="16" height="16"></iconify-icon>
                <span id="psPeriodLabel">30 hari terakhir</span>
            </span>
        </div>
    </header>

    <section class="ps-panel" aria-labelledby="ps-panel-title">
        <div class="ps-panel__head">
            <div>
                <h2 id="ps-panel-title">Antrean service</h2>
                <p>Perbarui status, hubungi pelanggan, atau siapkan pengambilan.</p>
            </div>
            <div class="ps-search">
                <iconify-icon icon="solar:magnifer-linear" width="18" height="18"></iconify-icon>
                <input type="search" id="searchBox"
                    placeholder="Cari no service, nama, HP, tipe HP..."
                    aria-label="Cari service" autocomplete="off">
            </div>
        </div>

        <div class="ps-filter">
            <div class="ps-field">
                <span class="ps-field__label" id="psUnitFieldLabel">Unit</span>
                <?php if ($showUnitFilter): ?>
                    <select id="unitFilter" class="ps-input" aria-labelledby="psUnitFieldLabel">
                        <option value="">Semua unit</option>
                        <?php foreach (($unit ?? []) as $u): ?>
                            <option value="<?= (int) $u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <div class="ps-locked" title="Role Anda hanya dapat melihat unit sendiri" aria-label="Unit terkunci: <?= esc($unitLabel) ?>">
                        <iconify-icon icon="solar:lock-keyhole-minimalistic-linear" width="18" height="18"></iconify-icon>
                        <span><?= esc($unitLabel) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ps-field">
                <span class="ps-field__label" id="psDateFieldLabel">Tanggal masuk</span>
                <div class="ps-range">
                    <input type="date" id="startDate" class="ps-input" aria-label="Tanggal masuk dari">
                    <span aria-hidden="true">s.d.</span>
                    <input type="date" id="endDate" class="ps-input" aria-label="Tanggal masuk sampai">
                </div>
            </div>

            <div class="ps-filter__actions">
                <label class="visually-hidden" for="pageLength">Jumlah baris</label>
                <select id="pageLength" class="ps-input ps-pagesize" aria-label="Jumlah baris">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <button type="button" id="resetBtn" class="ps-btn ps-btn--ghost">
                    <iconify-icon icon="solar:refresh-linear" width="18" height="18"></iconify-icon>
                    Reset
                </button>
                <form action="<?= base_url('riwayat_service/export') ?>" method="post" id="exportForm" class="mb-0">
                    <input type="hidden" name="tanggal_awal" id="exportStartDate">
                    <input type="hidden" name="tanggal_akhir" id="exportEndDate">
                    <button type="submit" class="ps-btn ps-btn--export">
                        <iconify-icon icon="solar:export-broken" width="18" height="18"></iconify-icon>
                        Export
                    </button>
                </form>
            </div>
        </div>

        <table class="ps-table" id="psTable">
            <caption class="visually-hidden">Antrean service aktif</caption>
            <thead>
                <tr>
                    <th scope="col" style="display:none;">Rank</th>
                    <th scope="col">Prioritas</th>
                    <th scope="col">Service</th>
                    <th scope="col">Pelanggan</th>
                    <th scope="col">Lama</th>
                    <th scope="col">Jenis</th>
                    <th scope="col">Status proses</th>
                    <th scope="col">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </section>

    <!-- Modal Bisa Diambil -->
    <div class="modal fade ps-modal" id="bisaDiambilModal" tabindex="-1" aria-labelledby="bisaDiambilModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="ps-modal__title">
                        <span class="ps-modal__icon">
                            <iconify-icon icon="solar:clipboard-check-linear" width="22" height="22"></iconify-icon>
                        </span>
                        <div>
                            <h5 class="modal-title" id="bisaDiambilModalLabel">Konfirmasi Bisa Diambil</h5>
                            <p>Tandai service siap diambil pelanggan.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form action="<?= base_url('service/bisa_diambil') ?>" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="idservice" id="modal-bisa-diambil-idservice">
                        <div id="modal-bisa-diambil-alert"></div>
                        <p class="ps-modal__question">Apakah Anda yakin service ini <strong>bisa diambil</strong>?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="ps-btn ps-btn--ghost" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="ps-btn ps-btn--primary" id="modal-bisa-diambil-submit">Konfirmasi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Sudah Diambil -->
    <div class="modal fade ps-modal" id="sudahDiambilModal" tabindex="-1" aria-labelledby="sudahDiambilModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="ps-modal__title">
                        <span class="ps-modal__icon">
                            <iconify-icon icon="solar:check-circle-linear" width="22" height="22"></iconify-icon>
                        </span>
                        <div>
                            <h5 class="modal-title" id="sudahDiambilModalLabel">Konfirmasi Sudah Diambil</h5>
                            <p>Tutup service karena unit sudah diterima pelanggan.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form action="<?= base_url('service/sudah_diambil') ?>" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="idservice" id="modal-sudah-diambil-idservice">
                        <div id="modal-sudah-diambil-alert"></div>
                        <p class="ps-modal__question">Apakah Anda yakin service ini <strong>sudah diambil</strong>?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="ps-btn ps-btn--ghost" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="ps-btn ps-btn--primary" id="modal-sudah-diambil-submit">Konfirmasi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Dibatalkan -->
    <div class="modal fade ps-modal" id="DibatalkanModal" tabindex="-1" aria-labelledby="DibatalkanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="ps-modal__title ps-modal__title--danger">
                        <span class="ps-modal__icon">
                            <iconify-icon icon="solar:close-circle-linear" width="22" height="22"></iconify-icon>
                        </span>
                        <div>
                            <h5 class="modal-title" id="DibatalkanModalLabel">Konfirmasi Pembatalan</h5>
                            <p>Service yang dibatalkan keluar dari antrean aktif.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form action="<?= base_url('service/dibatalkan') ?>" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="idservice" id="modal-dibatalkan-idservice">
                        <div id="modal-dibatalkan-alert"></div>
                        <p class="ps-modal__question">Apakah Anda yakin ingin <strong>membatalkan</strong> service ini?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="ps-btn ps-btn--ghost" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="ps-btn ps-btn--danger" id="modal-dibatalkan-submit">Batalkan Service</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    const tableElement = document.getElementById('psTable');
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    const unitFilter = document.getElementById('unitFilter');
    const searchBox = document.getElementById('searchBox');
    const pageLength = document.getElementById('pageLength');
    const countLabel = document.getElementById('psCount');
    const unitLabel = document.getElementById('psUnitLabel');
    const periodLabel = document.getElementById('psPeriodLabel');
    const exportForm = document.getElementById('exportForm');

    const ajaxUrl = <?= json_encode(base_url('proses_service/ajax'), JSON_UNESCAPED_SLASHES) ?>;
    const prioritasUrl = <?= json_encode(base_url('service/toggle_prioritas'), JSON_UNESCAPED_SLASHES) ?>;
    const statusUrl = <?= json_encode(base_url('update_status_proses'), JSON_UNESCAPED_SLASHES) ?>;
    const detailUrl = <?= json_encode(base_url('detail/riwayat_service'), JSON_UNESCAPED_SLASHES) ?>;
    const garansiUrl = <?= json_encode(base_url('service_by_garansi'), JSON_UNESCAPED_SLASHES) ?>;
    const invoiceUrl = <?= json_encode(base_url('cetak/invoice_service'), JSON_UNESCAPED_SLASHES) ?>;

    const statusMap = {
        1: { text: 'Belum Dicek', variant: 'idle' },
        2: { text: 'Sedang Dicek', variant: 'info' },
        3: { text: 'Sedang Dikerjakan', variant: 'progress' },
        4: { text: 'Sedang Testing', variant: 'success' },
        5: { text: 'Menunggu Konfirmasi', variant: 'danger' },
        6: { text: 'Menunggu Sparepart', variant: 'warn' }
    };
    const bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const emptyState = '<div class="ps-empty"><span class="ps-empty__icon"><iconify-icon icon="solar:clipboard-list-linear" width="24" height="24"></iconify-icon></span><h3>Tidak ada service aktif</h3><p>Tidak ada service pada filter ini. Coba ubah tanggal, unit, atau kata kunci.</p></div>';

    const escapeHtml = (value) => $('<div>').text(value ?? '').html();
    const escapeAttr = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[ch]));

    function formatDate(value) {
        if (!value) return '-';
        const parts = String(value).slice(0, 10).split('-');
        if (parts.length !== 3 || parts[0] === '0000' || parts[1] === '00' || parts[2] === '00') return '-';
        return `${Number(parts[2])} ${bulan[Number(parts[1]) - 1] || parts[1]} ${parts[0]}`;
    }

    function formatTime(value) {
        return String(value || '').slice(11, 16);
    }

    function isWarranty(row) {
        return Boolean(row.tanggal_claim_garansi) && row.tanggal_claim_garansi > '1971-01-01';
    }

    function toDateInputValue(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function defaultRange() {
        const today = new Date();
        const start = new Date();
        start.setDate(today.getDate() - 30);
        return [toDateInputValue(start), toDateInputValue(today)];
    }

    function syncExport() {
        document.getElementById('exportStartDate').value = startDateInput.value;
        document.getElementById('exportEndDate').value = endDateInput.value;
    }

    function syncMeta() {
        syncExport();

        const awal = startDateInput.value ? formatDate(startDateInput.value) : '';
        const akhir = endDateInput.value ? formatDate(endDateInput.value) : '';
        periodLabel.textContent = awal && akhir
            ? `${awal} – ${akhir}`
            : (awal ? `Sejak ${awal}` : (akhir ? `Sampai ${akhir}` : 'Semua periode'));

        if (unitFilter) {
            const selected = unitFilter.options[unitFilter.selectedIndex];
            unitLabel.textContent = unitFilter.value ? selected.textContent : 'Semua unit';
        }
    }

    const [defaultStart, defaultEnd] = defaultRange();
    startDateInput.value = defaultStart;
    endDateInput.value = defaultEnd;

    const initialSearch = new URLSearchParams(window.location.search).get('search') || '';
    if (initialSearch) {
        startDateInput.value = '';
        endDateInput.value = '';
        searchBox.value = initialSearch;
    }

    let searchTimeout;
    const table = $(tableElement).DataTable({
        serverSide: true,
        processing: true,
        searching: false,
        lengthChange: false,
        pageLength: 10,
        autoWidth: false,
        order: [[0, 'asc']],
        dom: "<'ps-table-scroll'rt><'ps-dt-foot'ip>",
        language: {
            processing: '<span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span> Memuat data...',
            emptyTable: emptyState,
            zeroRecords: emptyState,
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ service',
            infoEmpty: 'Tidak ada service',
            infoFiltered: '(difilter dari _MAX_ total service)',
            paginate: {
                first: 'Pertama',
                last: 'Terakhir',
                next: 'Berikutnya',
                previous: 'Sebelumnya'
            }
        },
        ajax: {
            url: ajaxUrl,
            type: 'POST',
            data: function(d) {
                d.startDate = startDateInput.value;
                d.endDate = endDateInput.value;
                d.unitFilter = unitFilter ? unitFilter.value : '';
                d.search = { value: searchBox.value };
            }
        },
        drawCallback: function() {
            const info = this.api().page.info();
            countLabel.textContent = info.recordsDisplay;
            syncMeta();
        },
        columns: [
            {
                data: 'rank',
                visible: false
            },
            {
                data: 'prioritas',
                render: function(data, type, row) {
                    const on = Number(data) === 1;
                    return `<button type="button" class="ps-star toggle-prioritas-btn${on ? ' is-on' : ''}"
                        data-idservice="${Number(row.idservice)}" data-prioritas="${on ? 1 : 0}"
                        aria-pressed="${on}" title="${on ? 'Batalkan prioritas' : 'Prioritaskan service'}"
                        aria-label="${on ? 'Batalkan prioritas' : 'Prioritaskan service'}">
                        <iconify-icon icon="${on ? 'solar:star-bold' : 'solar:star-linear'}" width="18" height="18"></iconify-icon>
                    </button>`;
                }
            },
            {
                data: 'no_service',
                render: function(data, type, row) {
                    const masuk = formatDate(row.created_at);
                    const jam = formatTime(row.created_at);
                    const unit = row.nama_unit || ('Unit ' + (row.unit_idunit ?? '?'));
                    return `<span class="ps-code">${escapeHtml(data || '-')}</span>
                        <span class="ps-sub">Masuk ${masuk}${jam ? ' · ' + jam : ''}</span>
                        <span class="ps-unit"><iconify-icon icon="solar:map-point-linear" width="12" height="12"></iconify-icon><span>${escapeHtml(unit)}</span></span>`;
                }
            },
            {
                data: 'nama_pelanggan',
                render: function(data, type, row) {
                    const nama = data || '-';
                    const initial = String(nama).trim().charAt(0).toUpperCase() || '?';
                    return `<div class="ps-person">
                        <span class="ps-avatar" aria-hidden="true">${escapeHtml(initial)}</span>
                        <div class="ps-person__body">
                            <span class="ps-person__name">${escapeHtml(nama)}</span>
                            <span class="ps-sub">${escapeHtml(row.no_hp || '-')}</span>
                            <span class="ps-sub">${escapeHtml(row.tipe_hp || '-')}</span>
                        </div>
                    </div>`;
                }
            },
            {
                data: 'lama_service_days',
                render: function(data, type, row) {
                    if (data === null || data === undefined || data === '') return '<span class="ps-sub">-</span>';
                    const days = Number(data);
                    return `<span class="ps-age" title="${escapeAttr(row.lama_service || '')}"><span class="ps-age__value">${days}</span><span class="ps-age__unit">hari</span></span>`;
                }
            },
            {
                data: 'tanggal_claim_garansi',
                render: function(data, type, row) {
                    return isWarranty(row)
                        ? '<span class="ps-tag ps-tag--warranty"><iconify-icon icon="solar:shield-check-linear" width="14" height="14"></iconify-icon>Garansi</span>'
                        : '<span class="ps-tag ps-tag--new">Baru</span>';
                }
            },
            {
                data: 'status_proses',
                render: function(data, type, row) {
                    const status = statusMap[data] || { text: 'Status Tidak Diketahui', variant: 'idle' };
                    return `<button type="button" class="ps-status ps-status--${status.variant} btn-status-modal"
                        data-idservice="${Number(row.idservice)}" data-status="${Number(data) || 0}"
                        title="Ubah status proses" aria-label="Ubah status proses: ${escapeAttr(status.text)}">
                        <span>${status.text}</span>
                        <iconify-icon icon="solar:alt-arrow-down-linear" width="14" height="14"></iconify-icon>
                    </button>`;
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    const id = Number(row.idservice);
                    const warranty = isWarranty(row);
                    const incomplete = Number(row.jumlah_kerusakan) === 0 || Number(row.jumlah_sparepart) === 0;
                    const warnTitle = incomplete ? 'Data kerusakan atau sparepart belum lengkap' : '';

                    let editBtn;
                    if (warranty) {
                        editBtn = `<a href="${garansiUrl}/${id}" class="ps-btn ps-btn--sm ps-btn--icon ps-btn--soft" title="Edit service garansi" aria-label="Edit service garansi">
                            <iconify-icon icon="solar:clapperboard-edit-broken" width="18" height="18"></iconify-icon>
                        </a>`;
                    } else if (Number(row.status_service) === 4) {
                        editBtn = `<button type="button" class="ps-btn ps-btn--sm ps-btn--icon ps-btn--soft" disabled title="Service sudah selesai" aria-label="Edit service terkunci">
                            <iconify-icon icon="solar:clapperboard-edit-broken" width="18" height="18"></iconify-icon>
                        </button>`;
                    } else {
                        editBtn = `<a href="${detailUrl}/${id}?tab=sparepart" class="ps-btn ps-btn--sm ps-btn--icon ps-btn--soft" title="Edit kerusakan & sparepart" aria-label="Edit kerusakan dan sparepart">
                            <iconify-icon icon="solar:clapperboard-edit-broken" width="18" height="18"></iconify-icon>
                        </a>`;
                    }

                    const invoiceBtn = `<a href="${invoiceUrl}/${id}" target="_blank" rel="noopener noreferrer"
                        class="ps-btn ps-btn--sm ps-btn--soft js-service-print" data-print-mode="invoice"
                        title="Cetak nota (A4)" aria-label="Cetak nota">
                        <iconify-icon icon="solar:document-text-linear" width="16" height="16"></iconify-icon>Nota
                    </a>`;
                    const thermalBtn = `<a href="${invoiceUrl}/${id}?mode=thermal" target="_blank" rel="noopener noreferrer"
                        class="ps-btn ps-btn--sm ps-btn--soft js-service-print" data-print-mode="thermal"
                        title="Cetak struk thermal" aria-label="Cetak struk thermal">
                        <iconify-icon icon="solar:printer-minimalistic-broken" width="16" height="16"></iconify-icon>Struk
                    </a>`;

                    const waBtn = row.no_hp
                        ? `<button type="button" class="ps-btn ps-btn--sm ps-btn--icon ps-btn--soft btn-wa"
                            data-nohp="${escapeAttr(row.no_hp)}" data-nama="${escapeAttr(row.nama_pelanggan)}"
                            title="Hubungi via WhatsApp" aria-label="Hubungi ${escapeAttr(row.nama_pelanggan)} via WhatsApp">
                            <iconify-icon icon="solar:phone-bold" width="18" height="18"></iconify-icon>
                        </button>`
                        : `<button type="button" class="ps-btn ps-btn--sm ps-btn--icon ps-btn--soft" disabled title="Nomor HP tidak tersedia" aria-label="Nomor HP tidak tersedia">
                            <iconify-icon icon="solar:phone-bold" width="18" height="18"></iconify-icon>
                        </button>`;

                    return `<div class="ps-actions">
                        <div class="ps-actions__row">
                            <button type="button" class="ps-btn ps-btn--sm ps-btn--action btn-bisa-diambil"
                                data-idservice="${id}" data-jumlah_kerusakan="${Number(row.jumlah_kerusakan) || 0}" data-jumlah_sparepart="${Number(row.jumlah_sparepart) || 0}"
                                ${warnTitle ? `title="${warnTitle}"` : ''}>
                                ${incomplete ? '<span class="ps-alert-dot" aria-hidden="true"></span>' : ''}Bisa Diambil
                            </button>
                            <button type="button" class="ps-btn ps-btn--sm ps-btn--soft btn-sudah-diambil"
                                data-idservice="${id}" data-jumlah_kerusakan="${Number(row.jumlah_kerusakan) || 0}" data-jumlah_sparepart="${Number(row.jumlah_sparepart) || 0}"
                                ${warnTitle ? `title="${warnTitle}"` : ''}>Sudah Diambil</button>
                            <button type="button" class="ps-btn ps-btn--sm ps-btn--danger btn-dibatalkan"
                                data-idservice="${id}">Batalkan</button>
                        </div>
                        <div class="ps-actions__row">
                            ${editBtn}
                            ${invoiceBtn}
                            ${thermalBtn}
                            ${waBtn}
                        </div>
                    </div>`;
                }
            }
        ],
        columnDefs: [
            { targets: 0, visible: false },
            { targets: 1, createdCell: function(td) { td.setAttribute('data-label', 'Prioritas'); } },
            { targets: 2, createdCell: function(td) { td.setAttribute('data-label', 'Service'); } },
            { targets: 3, createdCell: function(td) { td.setAttribute('data-label', 'Pelanggan'); } },
            { targets: 4, createdCell: function(td) { td.setAttribute('data-label', 'Lama'); } },
            { targets: 5, createdCell: function(td) { td.setAttribute('data-label', 'Jenis'); } },
            { targets: 6, createdCell: function(td) { td.setAttribute('data-label', 'Status proses'); } },
            { targets: 7, createdCell: function(td) { td.setAttribute('data-label', 'Aksi'); } },
            { targets: 7, orderable: false, searchable: false }
        ]
    });

    syncMeta();

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
        syncMeta();
        table.ajax.reload();
    }

    startDateInput.addEventListener('change', reload);
    endDateInput.addEventListener('change', reload);
    if (unitFilter) {
        unitFilter.addEventListener('change', reload);
    }

    document.getElementById('resetBtn').addEventListener('click', function() {
        const [start, end] = defaultRange();
        startDateInput.value = start;
        endDateInput.value = end;
        searchBox.value = '';
        if (unitFilter) unitFilter.value = '';
        reload();
    });

    exportForm.addEventListener('submit', syncExport);

    // Cetak invoice/thermal dibuka di tab baru; kunci link yang diklik agar
    // satu render tabel hanya mengirim satu permintaan cetak per tombol.
    $(document).on('click', '.js-service-print', function(event) {
        if (this.dataset.printing === '1') {
            event.preventDefault();
            return;
        }

        this.dataset.printing = '1';
        this.classList.add('is-loading');
        this.setAttribute('aria-disabled', 'true');
        this.setAttribute('aria-busy', 'true');
        this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>'
            + (this.dataset.printMode === 'thermal' ? 'Struk' : 'Nota');
    });

    $(document).on('click', '.btn-wa', function() {
        if (!this.dataset.nohp) return;

        let nomor = this.dataset.nohp.trim();
        const nama = (this.dataset.nama ?? '').trim();

        if (nomor.startsWith('0')) {
            nomor = '62' + nomor.substring(1);
        } else if (nomor.startsWith('+62')) {
            nomor = nomor.substring(1);
        }

        const waUrl = 'https://wa.me/' + nomor + '?text=' + encodeURIComponent('Halo, Kami dari welldone group ingin melakukan konfirmasi untuk service handphone atas nama ' + nama);
        window.open(waUrl, '_blank');
    });

    $(document).on('click', '.toggle-prioritas-btn', function() {
        const button = this;
        const idservice = Number(this.dataset.idservice);
        const next = Number(this.dataset.prioritas) === 1 ? 0 : 1;

        button.disabled = true;
        fetch(prioritasUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `idservice=${idservice}&prioritas=${next}`
            })
            .then((response) => response.json())
            .then((data) => {
                if (data.status === 'success') {
                    table.ajax.reload(null, false);
                } else {
                    alert('Gagal mengubah prioritas');
                    button.disabled = false;
                }
            })
            .catch((err) => {
                console.error(err);
                button.disabled = false;
            });
    });

    $(document).on('click', '.btn-bisa-diambil', function() {
        const incomplete = Number(this.dataset.jumlah_kerusakan) === 0 || Number(this.dataset.jumlah_sparepart) === 0;
        $('#modal-bisa-diambil-idservice').val(this.dataset.idservice);
        $('#modal-bisa-diambil-alert').html(incomplete
            ? '<div class="alert">Peringatan: data kerusakan atau sparepart belum lengkap.</div>'
            : '');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('bisaDiambilModal')).show();
    });

    $(document).on('click', '.btn-sudah-diambil', function() {
        const incomplete = Number(this.dataset.jumlah_kerusakan) === 0 || Number(this.dataset.jumlah_sparepart) === 0;
        $('#modal-sudah-diambil-idservice').val(this.dataset.idservice);
        $('#modal-sudah-diambil-alert').html(incomplete
            ? '<div class="alert">Peringatan: data kerusakan atau sparepart belum lengkap.</div>'
            : '');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('sudahDiambilModal')).show();
    });

    $(document).on('click', '.btn-dibatalkan', function() {
        $('#modal-dibatalkan-idservice').val(this.dataset.idservice);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('DibatalkanModal')).show();
    });

    $(document).on('click', '.btn-status-modal', function() {
        const idservice = Number(this.dataset.idservice);
        const currentStatus = Number(this.dataset.status);
        const modalId = 'statusModal-' + idservice;

        document.getElementById(modalId)?.remove();

        const modalHtml = `
            <div class="modal fade ps-modal" id="${modalId}" tabindex="-1" aria-labelledby="${modalId}Label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div class="ps-modal__title">
                                <span class="ps-modal__icon">
                                    <iconify-icon icon="solar:refresh-circle-linear" width="22" height="22"></iconify-icon>
                                </span>
                                <div>
                                    <h5 class="modal-title" id="${modalId}Label">Ubah Status Proses</h5>
                                    <p>Pilih tahap pengerjaan service saat ini.</p>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <form action="${statusUrl}" method="post">
                            <div class="modal-body">
                                <input type="hidden" name="idservice" value="${idservice}">
                                <div class="ps-choice-grid" role="radiogroup" aria-label="Status proses">
                                    ${Object.entries(statusMap).map(([value, info]) => `
                                        <label class="ps-choice">
                                            <input type="radio" name="status_proses" value="${value}" ${Number(value) === currentStatus ? 'checked' : ''}>
                                            <span class="ps-choice__dot" style="color: var(--ps-${info.variant === 'idle' ? 'muted' : (info.variant === 'progress' ? 'accent-ink' : info.variant)})"></span>
                                            <span>${info.text}</span>
                                        </label>
                                    `).join('')}
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="ps-btn ps-btn--ghost" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="ps-btn ps-btn--primary">Simpan Status</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>`;

        $('body').append(modalHtml);
        const modalElement = document.getElementById(modalId);
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
        modalElement.addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    });
});
</script>
