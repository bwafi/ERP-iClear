<?= view('riwayat/_riwayat_pembelian_theme') ?>

<?php
$bolehSemuaUnit = ! empty($boleh_semua_unit);
$daftarUnit     = $unit ?? [];
$unitTerkunci   = ! $bolehSemuaUnit && count($daftarUnit) <= 1;
$labelUnit      = $bolehSemuaUnit
    ? 'Semua unit'
    : (string) ($daftarUnit[0]->NAMA_UNIT ?? 'Unit Anda');
?>

<div class="rb-page">
    <header class="rb-hero">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="rb-crumb">
                    <li><a href="<?= base_url('/') ?>">Riwayat</a></li>
                    <li aria-hidden="true">/</li>
                    <li><span aria-current="page">Pembelian</span></li>
                </ol>
            </nav>
            <h1>Riwayat Pembelian</h1>
            <p>Daftar nota pembelian per unit beserta rincian barang yang dibeli. Buka Detail untuk melihat harga beli, diskon, PPN, dan sisa hutang nota.</p>
        </div>
        <div class="rb-chips">
            <span class="rb-chip rb-chip--accent">
                <iconify-icon icon="solar:document-text-linear" width="15" height="15" aria-hidden="true"></iconify-icon>
                <strong id="rbCountValue">0</strong> nota
            </span>
            <span class="rb-chip">
                <iconify-icon icon="solar:buildings-2-linear" width="15" height="15" aria-hidden="true"></iconify-icon>
                <span id="rbUnitChip"><?= esc($labelUnit) ?></span>
            </span>
            <span class="rb-chip">
                <iconify-icon icon="solar:calendar-linear" width="15" height="15" aria-hidden="true"></iconify-icon>
                <span id="rbRangeChip">Semua tanggal</span>
            </span>
        </div>
    </header>

    <section class="rb-toolbar" aria-label="Filter riwayat pembelian">
        <div class="rb-search">
            <iconify-icon icon="solar:magnifer-linear" width="18" height="18" aria-hidden="true"></iconify-icon>
            <input type="search" id="rbSearch" aria-label="Cari nota pembelian"
                placeholder="Cari no. nota, barang, atau supplier…" autocomplete="off">
        </div>

        <div class="rb-range">
            <div class="rb-field">
                <label for="startDate">Tanggal awal</label>
                <input type="date" id="startDate" class="rb-control">
            </div>
            <span class="rb-range__dash" aria-hidden="true">–</span>
            <div class="rb-field">
                <label for="endDate">Tanggal akhir</label>
                <input type="date" id="endDate" class="rb-control">
            </div>
        </div>

        <div class="rb-field">
            <label for="unitFilter">Unit</label>
            <select id="unitFilter" class="rb-control" <?= $unitTerkunci ? 'disabled' : '' ?>>
                <?php if ($bolehSemuaUnit): ?>
                    <option value="">Semua Unit</option>
                <?php endif; ?>
                <?php foreach ($daftarUnit as $u): ?>
                    <option value="<?= (int) $u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="rb-field">
            <label for="pageLength">Baris</label>
            <select id="pageLength" class="rb-control">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>

        <div class="rb-toolbar__actions">
            <button type="button" id="resetBtn" class="rb-btn">
                <iconify-icon icon="solar:refresh-linear" width="17" height="17" aria-hidden="true"></iconify-icon>
                Reset
            </button>
            <form action="<?= base_url('riwayat_pembelian/export') ?>" method="post" class="mb-0" id="exportForm">
                <input type="hidden" name="tanggal_awal" id="exportStartDate">
                <input type="hidden" name="tanggal_akhir" id="exportEndDate">
                <input type="hidden" name="unit" id="exportUnit">
                <button type="submit" class="rb-btn rb-btn--accent">
                    <iconify-icon icon="solar:download-minimalistic-linear" width="17" height="17" aria-hidden="true"></iconify-icon>
                    Export XLSX
                </button>
            </form>
        </div>

        <?php if ($unitTerkunci): ?>
            <p class="rb-scope-note">
                <iconify-icon icon="solar:lock-keyhole-minimalistic-linear" width="15" height="15" aria-hidden="true"></iconify-icon>
                Akun Anda hanya dapat melihat unit ini.
            </p>
        <?php endif; ?>
    </section>

    <section class="rb-card" aria-labelledby="rb-table-title">
        <div class="rb-card__head">
            <div>
                <h2 id="rb-table-title">Nota pembelian</h2>
                <p>Klik Detail untuk rincian barang. Sisa hutang tampil di bawah total bila nota belum lunas.</p>
            </div>
        </div>

        <table class="rb-table" id="pembelianTable">
            <caption class="visually-hidden">Daftar nota pembelian beserta total dan sisa hutang</caption>
            <thead>
                <tr>
                    <th scope="col">No. Nota</th>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Barang</th>
                    <th scope="col">Unit</th>
                    <th scope="col">Sumber</th>
                    <th scope="col" class="is-num">Total</th>
                    <th scope="col" class="is-action">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </section>
</div>

<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content rb-modal">
            <div class="modal-header">
                <div class="rb-modal__title">
                    <span class="rb-modal__icon">
                        <iconify-icon icon="solar:document-text-linear" width="20" height="20" aria-hidden="true"></iconify-icon>
                    </span>
                    <div>
                        <h5 class="modal-title" id="detailModalLabel">Detail Pembelian</h5>
                        <p class="rb-modal__batch" id="detailBatch">-</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="detailLoading" class="rb-loading-block">
                    <span class="rb-spinner" aria-hidden="true"></span>
                    <span>Memuat rincian nota…</span>
                </div>

                <div id="detailError" class="rb-alert d-none" role="alert"></div>

                <div id="detailContent" class="d-none">
                    <dl class="rb-meta">
                        <div class="rb-meta__cell">
                            <dt>Tanggal</dt>
                            <dd id="detailTanggal">-</dd>
                        </div>
                        <div class="rb-meta__cell">
                            <dt>Unit</dt>
                            <dd id="detailUnit">-</dd>
                        </div>
                        <div class="rb-meta__cell">
                            <dt>Sumber</dt>
                            <dd id="detailSumber">-</dd>
                        </div>
                        <div class="rb-meta__cell">
                            <dt>Status</dt>
                            <dd id="detailStatus">-</dd>
                        </div>
                    </dl>

                    <div class="rb-table-wrap">
                        <table class="rb-table">
                            <caption class="visually-hidden">Rincian barang pada nota pembelian</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Barang</th>
                                    <th scope="col" class="is-center">Jumlah</th>
                                    <th scope="col" class="is-num">Harga Beli</th>
                                    <th scope="col" class="is-num">Diskon</th>
                                    <th scope="col" class="is-num">PPN</th>
                                    <th scope="col" class="is-num">Sub Total</th>
                                </tr>
                            </thead>
                            <tbody id="detailItems"></tbody>
                        </table>
                    </div>

                    <div class="rb-summary">
                        <div class="rb-totals">
                            <div class="rb-totals__row">
                                <span>Sub Total</span>
                                <span id="detailSubtotal">Rp 0</span>
                            </div>
                            <div class="rb-totals__row" id="detailSisaRow">
                                <span>Sisa Hutang</span>
                                <span id="detailSisa">Rp 0</span>
                            </div>
                            <div class="rb-totals__row rb-totals__row--grand">
                                <span>Total Nota</span>
                                <span id="detailTotal">Rp 0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="rb-btn" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    const unitFilter = document.getElementById('unitFilter');
    const searchBox = document.getElementById('rbSearch');
    const pageLength = document.getElementById('pageLength');
    const exportForm = document.getElementById('exportForm');
    const countChip = document.getElementById('rbCountValue');
    const unitChip = document.getElementById('rbUnitChip');
    const rangeChip = document.getElementById('rbRangeChip');

    const detailUrl = <?= json_encode(base_url('riwayat_pembelian/detail/'), JSON_UNESCAPED_SLASHES) ?>;

    const escapeHtml = (value) => $('<div>').text(value ?? '').html();
    const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(Number(value) || 0);
    const formatRupiah = (value) => 'Rp ' + formatNumber(value);
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    function parseDate(value) {
        if (!value) return null;
        const parts = String(value).slice(0, 10).split('-').map(Number);
        if (parts.length !== 3 || !parts[0] || !parts[1] || !parts[2]) return null;
        const date = new Date(parts[0], parts[1] - 1, parts[2]);
        return Number.isNaN(date.getTime()) ? null : date;
    }

    function formatDate(value) {
        const date = parseDate(value);
        if (!date) return value ? String(value) : '-';
        return `${date.getDate()} ${monthNames[date.getMonth()]} ${date.getFullYear()}`;
    }

    function formatDay(value) {
        const date = parseDate(value);
        return date ? dayNames[date.getDay()] : '';
    }

    function syncExport() {
        document.getElementById('exportStartDate').value = startDateInput.value;
        document.getElementById('exportEndDate').value = endDateInput.value;
        document.getElementById('exportUnit').value = unitFilter.value;
    }

    function refreshRangeChip() {
        const from = startDateInput.value;
        const to = endDateInput.value;
        let label = 'Semua tanggal';
        if (from && to) {
            label = `${formatDate(from)} – ${formatDate(to)}`;
        } else if (from) {
            label = `Sejak ${formatDate(from)}`;
        } else if (to) {
            label = `Sampai ${formatDate(to)}`;
        }
        rangeChip.textContent = label;
    }

    function refreshUnitChip() {
        if (!unitFilter) return;
        if (unitFilter.value === '') {
            unitChip.textContent = 'Semua unit';
            return;
        }
        const option = unitFilter.options[unitFilter.selectedIndex];
        unitChip.textContent = option ? option.textContent.trim() : 'Unit Anda';
    }

    refreshRangeChip();
    refreshUnitChip();
    syncExport();

    const table = $('#pembelianTable').DataTable({
        serverSide: true,
        processing: true,
        searching: false,
        lengthChange: false,
        pageLength: 10,
        autoWidth: false,
        order: [[1, 'desc']],
        pagingType: 'simple_numbers',
        dom: '<"rb-table-wrap"t><"rb-dt-foot"<"rb-dt-info"i><"rb-dt-pager"p>>',
        language: {
            processing: '<span class="rb-spinner" aria-hidden="true"></span> Memuat nota…',
            emptyTable: '<span class="rb-empty">' +
                '<span class="rb-empty__icon"><iconify-icon icon="solar:box-minimalistic-linear" width="22" height="22" aria-hidden="true"></iconify-icon></span>' +
                '<span class="rb-empty__title">Tidak ada nota pada filter ini</span>' +
                '<span class="rb-empty__hint">Coba lebarkan rentang tanggal atau kosongkan pencarian.</span>' +
                '</span>',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ nota',
            infoEmpty: 'Tidak ada nota',
            infoFiltered: '(difilter dari _MAX_ total nota)',
            zeroRecords: 'Tidak ada nota yang cocok',
            paginate: {
                first: 'Pertama',
                last: 'Terakhir',
                next: 'Selanjutnya',
                previous: 'Sebelumnya'
            }
        },
        ajax: {
            url: <?= json_encode(base_url('riwayat_pembelian/ajax'), JSON_UNESCAPED_SLASHES) ?>,
            type: 'POST',
            data: function(d) {
                d.startDate = startDateInput.value;
                d.endDate = endDateInput.value;
                d.unitFilter = unitFilter.value;
                d.search = { value: searchBox.value };
            }
        },
        drawCallback: function(settings) {
            const json = settings.json || {};
            const total = typeof json.recordsFiltered === 'number'
                ? json.recordsFiltered
                : (settings._iRecordsDisplay || 0);
            countChip.textContent = formatNumber(total);
        },
        columns: [
            {
                data: 'no_batch',
                render: function(data) {
                    return `<span class="rb-note">${escapeHtml(data || '-')}</span>`;
                }
            },
            {
                data: 'tanggal_masuk',
                render: function(data) {
                    const hari = formatDay(data);
                    return `<span class="rb-strong rb-nowrap">${escapeHtml(formatDate(data))}</span>` +
                        (hari ? `<span class="rb-sub">${escapeHtml(hari)}</span>` : '');
                }
            },
            {
                data: 'nama_barang',
                render: function(data, type, row) {
                    if (!data) {
                        return '<span class="rb-sub">Tanpa rincian barang</span>';
                    }
                    const jumlah = Number(row.jumlah_item) || 0;
                    const sisaItem = jumlah > 1
                        ? `<span class="rb-sub">+${formatNumber(jumlah - 1)} barang lain</span>`
                        : '';
                    return `<span class="rb-strong">${escapeHtml(data)}</span>` + sisaItem;
                }
            },
            {
                data: 'NAMA_UNIT',
                render: function(data) {
                    return `<span class="rb-unit"><iconify-icon icon="solar:shop-2-linear" width="13" height="13" aria-hidden="true"></iconify-icon>${escapeHtml(data || '-')}</span>`;
                }
            },
            {
                data: 'suplier_id_suplier',
                render: function(data, type, row) {
                    const sumber = Number(data) > 0 ? 'Suplier' : 'Pelanggan';
                    const nama = row.nama_suplier
                        ? `<span class="rb-sub">${escapeHtml(row.nama_suplier)}</span>`
                        : '';
                    return `<span class="rb-strong">${sumber}</span>` + nama;
                }
            },
            {
                data: 'total_transaksi',
                className: 'is-num',
                render: function(data, type, row) {
                    const sisa = Number(row.sisa) || 0;
                    const sisaInfo = sisa > 0
                        ? `<span class="rb-sub rb-sub--warn">Sisa ${formatRupiah(sisa)}</span>`
                        : '';
                    return `<span class="rb-money">${formatRupiah(data)}</span>` + sisaInfo;
                }
            },
            {
                data: null,
                orderable: false,
                className: 'is-action',
                render: function(data, type, row) {
                    return `<button type="button" class="rb-btn-detail js-detail" data-id="${Number(row.idpembelian)}">
                        <iconify-icon icon="solar:document-add-linear" width="15" height="15" aria-hidden="true"></iconify-icon>
                        Detail
                    </button>`;
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
        refreshRangeChip();
        refreshUnitChip();
        syncExport();
        table.ajax.reload();
    }

    startDateInput.addEventListener('change', reload);
    endDateInput.addEventListener('change', reload);
    unitFilter.addEventListener('change', reload);

    document.getElementById('resetBtn').addEventListener('click', function() {
        startDateInput.value = '';
        endDateInput.value = '';
        searchBox.value = '';
        reload();
    });

    // Pastikan filter tanggal/unit ikut terkirim saat export.
    exportForm.addEventListener('submit', syncExport);

    let detailRequest = 0;

    $(document).on('click', '.js-detail', function() {
        const id = this.dataset.id;
        const requestId = ++detailRequest;

        document.getElementById('detailBatch').textContent = 'Memuat…';
        document.getElementById('detailLoading').classList.remove('d-none');
        document.getElementById('detailContent').classList.add('d-none');
        document.getElementById('detailError').classList.add('d-none');

        bootstrap.Modal.getOrCreateInstance(document.getElementById('detailModal')).show();

        fetch(detailUrl + encodeURIComponent(id), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Detail nota gagal dimuat. Coba muat ulang halaman.');
                return response.json();
            })
            .then(function(data) {
                if (requestId !== detailRequest) return;

                document.getElementById('detailBatch').textContent = data.no_batch || '-';
                document.getElementById('detailTanggal').textContent = formatDate(data.tanggal);
                document.getElementById('detailUnit').textContent = data.nama_unit || '-';
                document.getElementById('detailSumber').textContent = data.sumber || '-';
                document.getElementById('detailStatus').textContent = data.status || '-';

                const items = Array.isArray(data.items) ? data.items : [];
                const sisa = Number(data.sisa) || 0;
                let subtotal = 0;

                document.getElementById('detailItems').innerHTML = items.map(function(item) {
                    subtotal += Number(item.total_harga) || 0;
                    const kode = [item.kode_barang, item.imei].filter(Boolean).join(' · ');
                    const satuan = item.satuan_beli
                        ? `<span class="rb-sub-inline">${escapeHtml(item.satuan_beli)}</span>`
                        : '';
                    const ppn = Number(item.ppn) || 0;
                    return `<tr>
                        <td>
                            <span class="rb-strong">${escapeHtml(item.nama_barang || '-')}</span>
                            ${kode ? `<span class="rb-sub">${escapeHtml(kode)}</span>` : ''}
                        </td>
                        <td class="is-center">${formatNumber(item.jumlah)}${satuan}</td>
                        <td class="is-num">${formatRupiah(item.hrg_beli)}</td>
                        <td class="is-num">${formatRupiah(item.diskon)}</td>
                        <td class="is-num">${ppn > 0 ? formatRupiah(ppn) : '—'}</td>
                        <td class="is-num"><span class="rb-money">${formatRupiah(item.total_harga)}</span></td>
                    </tr>`;
                }).join('') || '<tr><td colspan="6"><span class="rb-empty"><span class="rb-empty__title">Tidak ada rincian barang.</span></span></td></tr>';

                document.getElementById('detailSubtotal').textContent = formatRupiah(subtotal);
                document.getElementById('detailSisa').textContent = formatRupiah(sisa);
                document.getElementById('detailSisaRow').classList.toggle('rb-totals__row--warn', sisa > 0);
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
