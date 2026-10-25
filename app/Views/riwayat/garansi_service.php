<?= view('riwayat/_service_garansi_theme') ?>
<?php
$showUnitColumn = !empty($can_see_all_units);
$unitLabel = $showUnitColumn ? 'Semua unit' : (session('NAMA_UNIT') ?: 'Unit Anda');
?>
<style>
    /* Penyesuaian kecil khusus daftar klaim; tetap di bawah .rsg-page. */
    .rsg-page {
        max-width: none;
    }

    .rsg-page .gs-pagesize {
        flex: 0 0 88px;
        width: 88px;
    }

    .rsg-page .dataTables_processing {
        padding: 12px 24px;
        border-bottom: 1px solid var(--rsg-rule);
        background: var(--rsg-surface);
        color: var(--rsg-muted);
        font-size: .8125rem;
        text-align: center;
    }

    .rsg-page .rsg-table td.dataTables_empty {
        display: block;
        padding: 0;
        border: 0;
    }

    .rsg-page .rsg-table td.dataTables_empty::before {
        display: none;
    }

    .rsg-page .rsg-filter__actions form {
        display: flex;
    }

    .rsg-page .rsg-filter__actions form .rsg-btn {
        flex: 1;
    }
</style>

<div class="rsg-page">
    <header class="rsg-head">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="rsg-crumb">
                    <li><a href="<?= base_url('/') ?>">Garansi</a></li>
                    <li aria-hidden="true">/</li>
                    <li aria-current="page">Service</li>
                </ol>
            </nav>
            <h1 class="rsg-title">Garansi Service</h1>
            <p>Daftar service bergaransi untuk klaim. Urut dari tanggal selesai terbaru; <?= $showUnitColumn ? 'pilih unit dan periode untuk mempersempit data.' : 'gunakan periode untuk mempersempit data.' ?></p>
        </div>
        <div class="rsg-head__meta">
            <span class="rsg-chip rsg-chip--accent">
                <iconify-icon icon="solar:shield-check-linear" width="16" height="16"></iconify-icon>
                <strong id="gsCount">–</strong> garansi
            </span>
            <span class="rsg-chip">
                <iconify-icon icon="<?= $showUnitColumn ? 'solar:map-point-linear' : 'solar:lock-keyhole-minimalistic-linear' ?>" width="16" height="16"></iconify-icon>
                <span id="gsUnitLabel"><?= esc($unitLabel) ?></span>
            </span>
            <span class="rsg-chip">
                <iconify-icon icon="solar:calendar-linear" width="16" height="16"></iconify-icon>
                <span id="gsPeriodLabel">Semua periode</span>
            </span>
        </div>
    </header>

    <section class="rsg-panel" aria-labelledby="gs-panel-title">
        <div class="rsg-panel__head">
            <div>
                <h2 id="gs-panel-title">Daftar garansi</h2>
                <p>Kelola klaim garansi, hubungi pelanggan, atau lihat riwayat klaim.</p>
            </div>
            <div class="rsg-search">
                <iconify-icon icon="solar:magnifer-linear" width="18" height="18"></iconify-icon>
                <input type="search" id="searchBox"
                    placeholder="Cari no service, pelanggan, HP, wilayah..."
                    aria-label="Cari garansi service" autocomplete="off">
            </div>
        </div>

        <div class="rsg-filter">
            <div class="rsg-field">
                <span class="rsg-field__label" id="gsUnitFieldLabel">Unit</span>
                <?php if ($showUnitColumn): ?>
                    <select id="unitFilter" class="rsg-input" aria-labelledby="gsUnitFieldLabel">
                        <option value="">Semua unit</option>
                        <?php foreach (($unit ?? []) as $u): ?>
                            <option value="<?= (int) $u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <div class="rsg-locked" title="Role Anda hanya dapat melihat unit sendiri" aria-label="Unit terkunci: <?= esc($unitLabel) ?>">
                        <iconify-icon icon="solar:lock-keyhole-minimalistic-linear" width="18" height="18"></iconify-icon>
                        <span><?= esc($unitLabel) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="rsg-field">
                <label for="startDate">Tanggal selesai</label>
                <div class="rsg-range">
                    <input type="date" id="startDate" class="rsg-input" aria-label="Tanggal selesai dari">
                    <span aria-hidden="true">s.d.</span>
                    <input type="date" id="endDate" class="rsg-input" aria-label="Tanggal selesai sampai">
                </div>
            </div>

            <div class="rsg-filter__actions">
                <label class="visually-hidden" for="pageLength">Jumlah baris</label>
                <select id="pageLength" class="rsg-input gs-pagesize" aria-label="Jumlah baris">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <button type="button" id="resetBtn" class="rsg-btn rsg-btn--ghost">
                    <iconify-icon icon="solar:refresh-linear" width="18" height="18"></iconify-icon>
                    Reset
                </button>
                <form action="<?= base_url('riwayat_service_garansi/export') ?>" method="post" class="mb-0" id="exportForm">
                    <input type="hidden" name="sumber" value="garansi">
                    <input type="hidden" name="tanggal_awal" id="exportStartDate">
                    <input type="hidden" name="tanggal_akhir" id="exportEndDate">
                    <input type="hidden" name="unit" id="exportUnit">
                    <button type="submit" class="rsg-btn rsg-btn--export">
                        <iconify-icon icon="solar:export-broken" width="18" height="18"></iconify-icon>
                        Export
                    </button>
                </form>
            </div>
        </div>

        <table class="rsg-table" id="gsTable">
            <caption class="visually-hidden">Daftar garansi service untuk klaim</caption>
            <thead>
                <tr>
                    <th scope="col">No. Service</th>
                    <th scope="col">Pelanggan</th>
                    <th scope="col">Tanggal Selesai</th>
                    <th scope="col"<?= $showUnitColumn ? '' : ' style="display:none"' ?>>Unit</th>
                    <th scope="col">Status Garansi</th>
                    <th scope="col">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </section>

    <div class="modal fade rsg-modal" id="modalRiwayatKlaim" tabindex="-1" aria-labelledby="modalRiwayatKlaimLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="rsg-modal__title">
                        <span class="rsg-modal__icon">
                            <iconify-icon icon="solar:document-text-linear" width="22" height="22"></iconify-icon>
                        </span>
                        <div>
                            <h5 class="modal-title" id="modalRiwayatKlaimLabel">Riwayat Klaim</h5>
                            <p>Catatan klaim, sparepart rusak, dan sparepart pengganti.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div id="riwayat-klaim-content">
                        <p class="text-center text-muted py-4 mb-0">Pilih data untuk melihat riwayat klaim...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="rsg-btn rsg-btn--ghost" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    const tableElement = document.getElementById('gsTable');
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    const unitFilter = document.getElementById('unitFilter');
    const searchBox = document.getElementById('searchBox');
    const pageLength = document.getElementById('pageLength');
    const countLabel = document.getElementById('gsCount');
    const unitLabel = document.getElementById('gsUnitLabel');
    const periodLabel = document.getElementById('gsPeriodLabel');
    const exportForm = document.getElementById('exportForm');

    const claimUrl = <?= json_encode(base_url('claim_garansi/detail'), JSON_UNESCAPED_SLASHES) ?>;
    const riwayatUrl = <?= json_encode(base_url('riwayat-klaim'), JSON_UNESCAPED_SLASHES) ?>;
    const bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const emptyState = '<div class="rsg-empty"><span class="rsg-empty__icon"><iconify-icon icon="solar:shield-check-linear" width="24" height="24"></iconify-icon></span><h3>Belum ada data garansi</h3><p>Tidak ada service bergaransi pada filter ini. Coba ubah periode atau unit.</p></div>';

    const escapeHtml = (value) => $('<div>').text(value ?? '').html();

    function formatDate(value) {
        if (!value) return '-';
        const parts = String(value).slice(0, 10).split('-');
        if (parts.length !== 3 || parts[0] === '0000' || parts[1] === '00' || parts[2] === '00') return '-';
        return `${Number(parts[2])} ${bulan[Number(parts[1]) - 1] || parts[1]} ${parts[0]}`;
    }

    function formatTime(value) {
        return String(value || '').slice(11, 16);
    }

    function statusClass(row) {
        const text = String(row.status_garansi || '').toLowerCase();
        if (row.claimed) return 'rsg-status--done';
        if (text.includes('kadaluarsa') || text.includes('tidak ada garansi')) return 'rsg-status--cancel';
        if (text.includes('aktif')) return 'rsg-status--done';
        if (text.includes('belum selesai')) return 'rsg-status--progress';
        return 'rsg-status--wait';
    }

    function syncExport() {
        document.getElementById('exportStartDate').value = startDateInput.value;
        document.getElementById('exportEndDate').value = endDateInput.value;
        document.getElementById('exportUnit').value = unitFilter ? unitFilter.value : '';
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

    let searchTimeout;
    const table = $(tableElement).DataTable({
        serverSide: true,
        processing: true,
        searching: false,
        lengthChange: false,
        pageLength: 10,
        autoWidth: false,
        order: [[2, 'desc']],
        dom: "<'rsg-table-scroll'rt><'rsg-dt-foot'ip>",
        language: {
            processing: '<div class="d-flex align-items-center justify-content-center gap-2 py-2"><span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span><span>Memuat data...</span></div>',
            emptyTable: emptyState,
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ garansi',
            infoEmpty: 'Tidak ada garansi',
            infoFiltered: '(difilter dari _MAX_ total garansi)',
            zeroRecords: emptyState,
            paginate: {
                first: 'Pertama',
                last: 'Terakhir',
                next: 'Berikutnya',
                previous: 'Sebelumnya'
            }
        },
        ajax: {
            url: <?= json_encode(base_url('garansi_service/ajax'), JSON_UNESCAPED_SLASHES) ?>,
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
                data: 'no_service',
                render: function(data, type, row) {
                    const masuk = formatDate(row.created_at);
                    return `<span class="rsg-code">${data}</span><span class="rsg-sub">Masuk ${masuk}</span>`;
                }
            },
            {
                data: 'nama_pelanggan',
                render: function(data, type, row) {
                    const nama = data || '-';
                    const initial = String(nama).trim().charAt(0).toUpperCase() || '?';
                    const lokasi = [row.kecamatan, row.kabupaten].filter(Boolean).join(', ');
                    return `<div class="rsg-person">
                        <span class="rsg-avatar" aria-hidden="true">${escapeHtml(initial)}</span>
                        <div class="rsg-person__body">
                            <span class="rsg-person__name">${nama}</span>
                            <span class="rsg-sub">${row.no_hp || '-'}</span>
                            ${lokasi ? `<span class="rsg-sub rsg-address" title="${lokasi}">${lokasi}</span>` : ''}
                        </div>
                    </div>`;
                }
            },
            {
                data: 'tanggal_selesai',
                render: function(data, type, row) {
                    const jam = formatTime(data);
                    const akhirGaransi = row.warranty_end ? formatDate(row.warranty_end) : '';
                    return `<span class="rsg-date">${formatDate(data)}</span>
                        ${jam ? `<span class="rsg-sub">${jam} WIB</span>` : ''}
                        ${akhirGaransi ? `<span class="rsg-sub">Garansi s/d ${akhirGaransi}</span>` : ''}`;
                }
            },
            {
                data: 'nama_unit',
                <?php if (!$showUnitColumn): ?>visible: false, orderable: false, searchable: false,<?php endif; ?>
                render: function(data) {
                    return data
                        ? `<span class="rsg-unit"><iconify-icon icon="solar:map-point-linear" width="14" height="14"></iconify-icon><span>${data}</span></span>`
                        : '-';
                }
            },
            {
                data: 'status_garansi',
                orderable: false,
                render: function(data, type, row) {
                    const riwayat = row.claimed ? row.riwayat_klaim : 'Belum ada klaim';
                    return `<span class="rsg-status ${statusClass(row)}">${escapeHtml(data || '-')}</span>
                        <span class="rsg-sub">${escapeHtml(riwayat || '-')}</span>`;
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    const klaim = row.claimed
                        ? `<button type="button" class="rsg-btn rsg-btn--claim" disabled title="Garansi sudah pernah diklaim">
                            <iconify-icon icon="solar:check-circle-linear" width="16" height="16"></iconify-icon>
                            Sudah Diklaim
                        </button>`
                        : row.claimable
                            ? `<a href="${claimUrl}/${Number(row.idservice)}" class="rsg-btn rsg-btn--claim">
                                <iconify-icon icon="solar:shield-check-linear" width="16" height="16"></iconify-icon>
                                Klaim
                            </a>`
                            : `<button type="button" class="rsg-btn rsg-btn--claim" disabled title="Garansi belum aktif atau sudah kadaluarsa">
                                <iconify-icon icon="solar:shield-check-linear" width="16" height="16"></iconify-icon>
                                Klaim
                            </button>`;

                    const whatsapp = row.no_hp
                        ? `<button type="button" class="rsg-btn rsg-btn--print btn-wa"
                                data-nohp="${row.no_hp}" data-nama="${row.nama_pelanggan}"
                                title="Hubungi pelanggan via WhatsApp" aria-label="Hubungi ${row.nama_pelanggan} via WhatsApp">
                            <iconify-icon icon="solar:phone-bold" width="16" height="16"></iconify-icon>
                        </button>`
                        : `<button type="button" class="rsg-btn rsg-btn--print" disabled title="Nomor HP tidak tersedia">
                            <iconify-icon icon="solar:phone-bold" width="16" height="16"></iconify-icon>
                        </button>`;

                    const riwayat = row.claimed
                        ? `<button type="button" class="rsg-btn rsg-btn--print btn-riwayat" data-idservice="${Number(row.idservice)}">
                            <iconify-icon icon="solar:document-text-linear" width="16" height="16"></iconify-icon>
                            Riwayat
                        </button>`
                        : '';

                    return `<div class="rsg-actions">${klaim}${whatsapp}${riwayat}</div>`;
                }
            }
        ],
        columnDefs: [
            { targets: 0, createdCell: function(td) { td.setAttribute('data-label', 'No. Service'); } },
            { targets: 1, createdCell: function(td) { td.setAttribute('data-label', 'Pelanggan'); } },
            { targets: 2, createdCell: function(td) { td.setAttribute('data-label', 'Tanggal Selesai'); } },
            { targets: 3, createdCell: function(td) { td.setAttribute('data-label', 'Unit'); } },
            { targets: 4, createdCell: function(td) { td.setAttribute('data-label', 'Status Garansi'); } },
            { targets: 5, createdCell: function(td) { td.setAttribute('data-label', 'Aksi'); } },
            { targets: [4, 5], orderable: false, searchable: false }
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
        startDateInput.value = '';
        endDateInput.value = '';
        searchBox.value = '';
        if (unitFilter) unitFilter.value = '';
        reload();
    });

    exportForm.addEventListener('submit', syncExport);
    searchBox.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') event.preventDefault();
    });

    // WhatsApp
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

    // Riwayat klaim
    $(document).on('click', '.btn-riwayat', function() {
        const idservice = Number(this.dataset.idservice);
        const content = document.getElementById('riwayat-klaim-content');

        content.innerHTML = '<div class="rsg-modal__loading"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Memuat riwayat klaim...</div>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRiwayatKlaim')).show();

        fetch(riwayatUrl + '/' + idservice, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) { content.innerHTML = data.html; })
            .catch(function() {
                content.innerHTML = '<div class="alert alert-danger mb-0">Gagal memuat riwayat klaim.</div>';
            });
    });
});
</script>
