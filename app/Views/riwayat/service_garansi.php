<?= view('riwayat/_service_garansi_theme') ?>
<?php
$showUnitColumn = !empty($can_see_all_units) && (int) ($selected_unit ?? 0) === 0;
$actionColumnIndex = $showUnitColumn ? 5 : 4;
$colspan = $showUnitColumn ? 6 : 5;

$bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$formatTanggal = static function ($value) use ($bulan): ?string {
    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return null;
    }

    return date('d', $timestamp) . ' ' . $bulan[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
};

$awalLabel = $formatTanggal($start_date ?? '');
$akhirLabel = $formatTanggal($end_date ?? '');
$periodeLabel = $awalLabel && $akhirLabel ? $awalLabel . ' – ' . $akhirLabel : 'Semua periode';
$unitLabel = ($selected_unit_name ?? '') ?: (!empty($can_see_all_units) ? 'Semua unit' : 'Unit Anda');

$statusMap = [
    1 => ['label' => 'Menunggu Service', 'class' => 'rsg-status--wait'],
    2 => ['label' => 'Dalam Pengerjaan', 'class' => 'rsg-status--progress'],
    3 => ['label' => 'Menunggu Pengambilan', 'class' => 'rsg-status--ready'],
    4 => ['label' => 'Sudah Diambil', 'class' => 'rsg-status--done'],
    9 => ['label' => 'Dibatalkan', 'class' => 'rsg-status--cancel'],
];
?>
<div class="rsg-page">
    <header class="rsg-head">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="rsg-crumb">
                    <li><a href="<?= base_url('/') ?>">Riwayat</a></li>
                    <li aria-hidden="true">/</li>
                    <li aria-current="page">Service Garansi</li>
                </ol>
            </nav>
            <h1 class="rsg-title">Riwayat Service Garansi</h1>
            <p>Daftar service yang sudah diklaim garansi. Urut dari klaim terbaru; <?= !empty($can_see_all_units) ? 'pilih unit dan periode untuk mempersempit data.' : 'gunakan periode untuk mempersempit data.' ?></p>
        </div>
        <div class="rsg-head__meta">
            <span class="rsg-chip rsg-chip--accent">
                <iconify-icon icon="solar:shield-check-linear" width="16" height="16"></iconify-icon>
                <strong><?= count($service ?? []) ?></strong> klaim
            </span>
            <span class="rsg-chip">
                <iconify-icon icon="<?= !empty($can_see_all_units) ? 'solar:map-point-linear' : 'solar:lock-keyhole-minimalistic-linear' ?>" width="16" height="16"></iconify-icon>
                <?= esc($unitLabel) ?>
            </span>
            <span class="rsg-chip">
                <iconify-icon icon="solar:calendar-linear" width="16" height="16"></iconify-icon>
                <?= esc($periodeLabel) ?>
            </span>
        </div>
    </header>

    <section class="rsg-panel<?= empty($service) ? ' is-empty' : '' ?>" aria-labelledby="rsg-panel-title">
        <div class="rsg-panel__head">
            <div>
                <h2 id="rsg-panel-title">Klaim garansi</h2>
                <p>Buka riwayat klaim untuk melihat catatan dan sparepart, atau cetak struk service.</p>
            </div>
            <div class="rsg-search">
                <iconify-icon icon="solar:magnifer-linear" width="18" height="18"></iconify-icon>
                <input type="search" id="rsgSearch"
                    placeholder="<?= empty($service) ? 'Tidak ada klaim pada periode ini' : 'Cari no service, pelanggan, unit...' ?>"
                    aria-label="Cari klaim garansi" autocomplete="off" <?= empty($service) ? 'disabled' : '' ?>>
            </div>
        </div>

        <form class="rsg-filter" method="get" action="<?= base_url('riwayat_service_garansi') ?>">
            <div class="rsg-field">
                <span class="rsg-field__label" id="rsgUnitLabel">Unit</span>
                <?php if (!empty($can_see_all_units)): ?>
                    <select name="unit" id="rsgUnit" class="rsg-input" aria-labelledby="rsgUnitLabel">
                        <option value="">Semua unit</option>
                        <?php foreach (($unit ?? []) as $u): ?>
                            <option value="<?= (int) $u->idunit ?>" <?= (int) $u->idunit === (int) ($selected_unit ?? 0) ? 'selected' : '' ?>>
                                <?= esc($u->NAMA_UNIT) ?>
                            </option>
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
                <label for="rsgStartDate">Periode klaim</label>
                <div class="rsg-range">
                    <input type="date" name="tanggal_awal" id="rsgStartDate" class="rsg-input"
                        value="<?= esc($start_date ?? '') ?>" aria-label="Tanggal awal klaim">
                    <span aria-hidden="true">s.d.</span>
                    <input type="date" name="tanggal_akhir" id="rsgEndDate" class="rsg-input"
                        value="<?= esc($end_date ?? '') ?>" aria-label="Tanggal akhir klaim">
                </div>
            </div>

            <div class="rsg-filter__actions">
                <button type="submit" class="rsg-btn rsg-btn--primary">
                    <iconify-icon icon="solar:filter-linear" width="18" height="18"></iconify-icon>
                    Terapkan
                </button>
                <a href="<?= base_url('riwayat_service_garansi') ?>" class="rsg-btn rsg-btn--ghost">
                    <iconify-icon icon="solar:refresh-linear" width="18" height="18"></iconify-icon>
                    Reset
                </a>
                <button type="submit" formaction="<?= base_url('riwayat_service_garansi/export') ?>" formmethod="post" class="rsg-btn rsg-btn--export">
                    <iconify-icon icon="solar:export-broken" width="18" height="18"></iconify-icon>
                    Export
                </button>
            </div>
        </form>

        <table class="rsg-table" id="rsgTable">
            <caption class="visually-hidden">Daftar klaim garansi service</caption>
            <thead>
                <tr>
                    <th scope="col">No. Service</th>
                    <th scope="col">Pelanggan</th>
                    <th scope="col">Tanggal Klaim</th>
                    <?php if ($showUnitColumn): ?>
                        <th scope="col">Unit</th>
                    <?php endif; ?>
                    <th scope="col">Status</th>
                    <th scope="col">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($service)): ?>
                    <?php foreach ($service as $row): ?>
                        <?php
                        $claimTs = strtotime((string) $row->tanggal_claim_garansi);
                        $createdTs = strtotime((string) $row->created_at);
                        $status = $statusMap[(int) $row->status_service] ?? ['label' => 'Data Tidak Valid', 'class' => 'rsg-status--cancel'];
                        $inisial = mb_substr(trim((string) ($row->nama_pelanggan ?? '')), 0, 1);
                        $inisial = $inisial !== '' ? mb_strtoupper($inisial) : '?';
                        ?>
                        <tr>
                            <td data-label="No. Service">
                                <span class="rsg-code"><?= esc($row->no_service) ?></span>
                                <span class="rsg-sub">Masuk <?= $createdTs ? esc(date('d-m-Y', $createdTs)) : '-' ?></span>
                            </td>
                            <td data-label="Pelanggan">
                                <div class="rsg-person">
                                    <span class="rsg-avatar" aria-hidden="true"><?= esc($inisial) ?></span>
                                    <div class="rsg-person__body">
                                        <span class="rsg-person__name"><?= esc($row->nama_pelanggan ?: '-') ?></span>
                                        <span class="rsg-sub"><?= esc($row->no_hp ?: '-') ?></span>
                                        <?php if (!empty($row->alamat)): ?>
                                            <span class="rsg-sub rsg-address" title="<?= esc($row->alamat) ?>"><?= esc($row->alamat) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Tanggal Klaim" data-order="<?= $claimTs ? esc(date('Y-m-d H:i:s', $claimTs)) : '' ?>">
                                <span class="rsg-date"><?= $claimTs ? esc(date('d-m-Y', $claimTs)) : '-' ?></span>
                                <span class="rsg-sub"><?= $claimTs ? esc(date('H:i', $claimTs)) . ' WIB' : '-' ?></span>
                            </td>
                            <?php if ($showUnitColumn): ?>
                                <td data-label="Unit">
                                    <span class="rsg-unit">
                                        <iconify-icon icon="solar:map-point-linear" width="14" height="14"></iconify-icon>
                                        <span><?= esc($row->nama_unit ?: '-') ?></span>
                                    </span>
                                </td>
                            <?php endif; ?>
                            <td data-label="Status">
                                <span class="rsg-status <?= esc($status['class']) ?>"><?= esc($status['label']) ?></span>
                            </td>
                            <td data-label="Aksi">
                                <div class="rsg-actions">
                                    <button type="button" class="rsg-btn rsg-btn--claim btn-riwayat"
                                        data-idservice="<?= (int) $row->idservice ?>">
                                        <iconify-icon icon="solar:document-text-linear" width="16" height="16"></iconify-icon>
                                        Riwayat Klaim
                                    </button>
                                    <a href="<?= base_url('cetak/invoice_service/' . $row->idservice) ?>"
                                        class="rsg-btn rsg-btn--print" title="Cetak struk service" aria-label="Cetak struk service <?= esc($row->no_service) ?>">
                                        <iconify-icon icon="solar:printer-linear" width="16" height="16"></iconify-icon>
                                        Cetak
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="rsg-empty-row">
                        <td colspan="<?= $colspan ?>">
                            <div class="rsg-empty">
                                <span class="rsg-empty__icon">
                                    <iconify-icon icon="solar:document-text-linear" width="24" height="24"></iconify-icon>
                                </span>
                                <h3>Belum ada klaim garansi</h3>
                                <p>Tidak ada klaim pada unit dan periode ini. Coba ubah rentang tanggal<?= !empty($can_see_all_units) ? ' atau pilih unit lain' : '' ?>.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
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
        const tableElement = document.getElementById('rsgTable');
        const searchInput = document.getElementById('rsgSearch');

        if (tableElement && $.fn.DataTable) {
            const panel = tableElement.closest('.rsg-panel');

            if (panel && !panel.classList.contains('is-empty')) {
                const table = $(tableElement).DataTable({
                    order: [
                        [2, 'desc']
                    ],
                    pageLength: 10,
                    lengthChange: false,
                    searching: false,
                    autoWidth: false,
                    dom: "<'rsg-table-scroll't><'rsg-dt-foot'ip>",
                    columnDefs: [{
                        targets: <?= (int) $actionColumnIndex ?>,
                        orderable: false,
                        searchable: false
                    }],
                    language: {
                        info: 'Menampilkan _START_–_END_ dari _TOTAL_ klaim',
                        infoEmpty: 'Tidak ada klaim',
                        infoFiltered: '(difilter dari _MAX_ total klaim)',
                        zeroRecords: 'Tidak ada klaim yang cocok dengan pencarian.',
                        emptyTable: 'Tidak ada klaim garansi pada periode ini.',
                        paginate: {
                            first: 'Pertama',
                            last: 'Terakhir',
                            next: 'Berikutnya',
                            previous: 'Sebelumnya'
                        }
                    }
                });

                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        table.search(this.value).draw();
                    });
                }
            }
        }

        document.addEventListener('click', function(e) {
            const button = e.target.closest('.btn-riwayat');
            if (!button) return;

            const content = document.getElementById('riwayat-klaim-content');
            content.innerHTML = '<div class="rsg-modal__loading"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Memuat riwayat klaim...</div>';

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRiwayatKlaim')).show();

            fetch('<?= base_url('riwayat-klaim') ?>/' + button.dataset.idservice, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    content.innerHTML = data.html;
                })
                .catch(() => {
                    content.innerHTML = '<div class="alert alert-danger mb-0">Gagal memuat riwayat klaim.</div>';
                });
        });
    });
</script>
