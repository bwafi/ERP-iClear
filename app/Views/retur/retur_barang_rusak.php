<?= view('retur/_retur_theme') ?>
<?php
$unitLabel = $can_see_all_units ? 'Semua unit' : (session('NAMA_UNIT') ?: 'Unit Anda');
foreach ($units as $unit) {
    if ((int) $selected_unit === (int) $unit->idunit) {
        $unitLabel = (string) $unit->NAMA_UNIT;
        break;
    }
}

$hasilLabel = [
    'potong_tagihan' => 'Potong tagihan',
    'uang_kembali' => 'Uang kembali',
    'barang_pengganti' => 'Barang pengganti',
    'ditolak' => 'Ditolak supplier',
];
$jumlahKlaim = count($klaim);
$jumlahRiwayat = count($riwayat);

$filterParams = [];
if ($can_see_all_units && (int) $selected_unit > 0) {
    $filterParams['unit'] = (int) $selected_unit;
}
$filterParams['tanggal_awal'] = $tanggal_awal;
$filterParams['tanggal_akhir'] = $tanggal_akhir;
$filterQuery = http_build_query($filterParams);
?>
<div class="rt-page">
    <header class="rt-head">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="rt-crumb">
                    <li><a href="<?= base_url('/') ?>">Service</a></li>
                    <li aria-hidden="true">/</li>
                    <li aria-current="page">Retur Barang Rusak</li>
                </ol>
            </nav>
            <h1 class="rt-title">Retur Barang Rusak</h1>
            <p>Catat sparepart klaim yang dikembalikan ke supplier. Satu transaksi retur hanya boleh berisi barang dari satu unit dan satu supplier.</p>
        </div>
        <div class="rt-head__meta">
            <span class="rt-chip rt-chip--accent">
                <iconify-icon icon="solar:clipboard-list-linear" width="16" height="16"></iconify-icon>
                <strong><?= $jumlahKlaim ?></strong> klaim belum diretur
            </span>
            <span class="rt-chip">
                <iconify-icon icon="solar:arrow-left-linear" width="16" height="16"></iconify-icon>
                <strong><?= $jumlahRiwayat ?></strong> retur periode ini
            </span>
            <span class="rt-chip">
                <iconify-icon icon="solar:calendar-linear" width="16" height="16"></iconify-icon>
                <?= esc(date('d-m-Y', strtotime((string) $tanggal_awal))) ?> &ndash; <?= esc(date('d-m-Y', strtotime((string) $tanggal_akhir))) ?>
            </span>
            <span class="rt-chip">
                <iconify-icon icon="<?= $can_see_all_units ? 'solar:map-point-linear' : 'solar:lock-keyhole-minimalistic-linear' ?>" width="16" height="16"></iconify-icon>
                <?= esc($unitLabel) ?>
            </span>
        </div>
    </header>

    <section class="rt-filter" aria-label="Filter periode klaim">
        <form action="<?= base_url('retur_barang_rusak') ?>" method="get" class="rt-filter__form">
            <?php if ($can_see_all_units): ?>
            <div class="rt-field">
                <label for="rtUnit">Unit</label>
                <select id="rtUnit" name="unit" class="rt-input">
                    <option value="0">Semua unit</option>
                    <?php foreach ($units as $unit): ?>
                    <option value="<?= (int) $unit->idunit ?>" <?= (int) $selected_unit === (int) $unit->idunit ? 'selected' : '' ?>>
                        <?= esc($unit->NAMA_UNIT) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="rt-field">
                <label for="rtTanggalAwal">Tanggal klaim awal</label>
                <input type="date" id="rtTanggalAwal" name="tanggal_awal" class="rt-input" value="<?= esc($tanggal_awal, 'attr') ?>">
            </div>
            <div class="rt-field">
                <label for="rtTanggalAkhir">Tanggal klaim akhir</label>
                <input type="date" id="rtTanggalAkhir" name="tanggal_akhir" class="rt-input" value="<?= esc($tanggal_akhir, 'attr') ?>">
            </div>
            <div class="rt-filter__actions">
                <button type="submit" class="rt-btn rt-btn--primary">
                    <iconify-icon icon="solar:filter-linear" width="17" height="17"></iconify-icon>
                    Filter
                </button>
                <a href="<?= base_url('retur_barang_rusak') ?>" class="rt-btn rt-btn--ghost">
                    <iconify-icon icon="solar:refresh-linear" width="17" height="17"></iconify-icon>
                    Reset
                </a>
            </div>
        </form>
    </section>

    <?php if (session()->getFlashdata('gagal')): ?>
    <div class="rt-flash rt-flash--danger" role="alert">
        <iconify-icon icon="solar:danger-triangle-linear" width="18" height="18"></iconify-icon>
        <span><?= esc(session()->getFlashdata('gagal')) ?></span>
    </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('sukses')): ?>
    <div class="rt-flash rt-flash--success" role="status">
        <iconify-icon icon="solar:check-circle-linear" width="18" height="18"></iconify-icon>
        <span><?= esc(session()->getFlashdata('sukses')) ?></span>
    </div>
    <?php endif; ?>

    <section class="rt-panel" aria-labelledby="rtKlaimTitle">
        <div class="rt-panel__head">
            <div>
                <h2 id="rtKlaimTitle">Klaim belum diretur</h2>
                <p>Centang barang, isi HPP manual dan hasil retur. HPP manual dipakai sebagai nilai retur ke supplier.</p>
            </div>
            <span class="rt-panel__count"><strong class="js-rt-count">0</strong> dipilih</span>
        </div>

        <?php if (empty($suppliers)): ?>
        <div class="rt-notice" role="alert">
            <iconify-icon icon="solar:danger-triangle-linear" width="18" height="18"></iconify-icon>
            <span>Belum ada supplier aktif. Tambahkan supplier terlebih dahulu sebelum menyimpan retur.</span>
        </div>
        <?php endif; ?>

        <?php if (empty($klaim)): ?>
        <div class="rt-empty">
            <span class="rt-empty__icon">
                <iconify-icon icon="solar:clipboard-check-linear" width="24" height="24"></iconify-icon>
            </span>
            <h3>Tidak ada klaim menunggu retur</h3>
            <p>Semua klaim pada periode ini sudah diretur, atau belum ada klaim yang ditandai bisa retur. Ubah rentang tanggal untuk melihat periode lain.</p>
            <a href="<?= base_url('retur_barang_rusak') ?>" class="rt-btn rt-btn--ghost">Tampilkan bulan ini</a>
        </div>
        <?php else: ?>
        <form action="<?= base_url('retur_barang_rusak/simpan') ?>" method="post" id="formRetur">
            <?= csrf_field() ?>

            <div class="rt-docband">
                <div class="rt-field">
                    <label for="rtSupplier">Supplier</label>
                    <select id="rtSupplier" name="suplier_id" class="rt-input" required>
                        <option value="">Pilih supplier</option>
                        <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= (int) $supplier->id_suplier ?>"><?= esc($supplier->nama_suplier) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="rt-field">
                    <label for="rtTanggal">Tanggal retur</label>
                    <input type="date" id="rtTanggal" name="tanggal" class="rt-input" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="rt-field rt-field--grow">
                    <label for="rtCatatan">Catatan <span class="rt-sub">(opsional)</span></label>
                    <input type="text" id="rtCatatan" name="catatan" class="rt-input" placeholder="Misal: nomor retur supplier">
                </div>
            </div>

            <div class="rt-table-scroll">
                <table class="rt-table rt-table--klaim">
                    <caption class="visually-hidden">Klaim sparepart yang menunggu retur ke supplier</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="rt-col-check"><span class="visually-hidden">Pilih</span></th>
                            <th scope="col">Dokumen</th>
                            <th scope="col">Barang klaim</th>
                            <th scope="col" class="rt-num">HPP asli</th>
                            <th scope="col" class="rt-col-status">Status retur</th>
                            <th scope="col" class="rt-col-hpp">HPP manual</th>
                            <th scope="col" class="rt-col-optional">Hasil retur</th>
                            <th scope="col" class="rt-col-optional">Pengganti</th>
                            <th scope="col" class="rt-col-optional">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($klaim as $i => $row): ?>
                        <?php $bisa = $row->status_retur === 'bisa'; ?>
                        <tr class="rt-row" data-qty="<?= (float) $row->jumlah_tambahan_garansi ?>">
                            <td class="rt-pick" data-label="Pilih">
                                <input type="checkbox" class="rt-check retur-check"
                                    name="items[<?= $i ?>][selected]" value="1" <?= $bisa ? '' : 'disabled' ?>
                                    aria-label="Pilih <?= esc($row->no_service, 'attr') ?> untuk diretur">
                                <input type="hidden" name="items[<?= $i ?>][id]" value="<?= (int) $row->idservice_sparepart ?>">
                            </td>
                            <td data-label="Dokumen">
                                <div class="rt-cell">
                                    <span class="rt-code"><?= esc($row->no_service) ?></span>
                                    <span class="rt-sub"><?= date('d-m-Y', strtotime((string) $row->tanggal_claim_garansi)) ?></span>
                                    <span class="rt-unit">
                                        <iconify-icon icon="solar:map-point-linear" width="12" height="12"></iconify-icon>
                                        <span><?= esc($row->NAMA_UNIT ?? '-') ?></span>
                                    </span>
                                </div>
                            </td>
                            <td data-label="Barang klaim">
                                <div class="rt-cell">
                                    <span class="rt-strong"><?= esc($row->nama_barang ?? '-') ?></span>
                                    <span class="rt-sub">Rusak: <?= esc($row->nama_barang_rusak ?? '-') ?></span>
                                    <span class="rt-sub">Qty <?= (float) $row->jumlah_tambahan_garansi ?></span>
                                </div>
                            </td>
                            <td class="rt-num" data-label="HPP asli">
                                <div class="rt-cell">
                                    <span class="rt-money">Rp <?= number_format((float) $row->hpp_penjualan_garansi, 0, ',', '.') ?></span>
                                </div>
                            </td>
                            <td class="rt-col-status" data-label="Status retur">
                                <div class="rt-cell">
                                    <span class="rt-badge <?= $bisa ? 'rt-badge--success' : 'rt-badge--muted' ?>">
                                        <?= $bisa ? 'Bisa retur' : 'Tidak bisa retur' ?>
                                    </span>
                                    <div class="rt-statusform">
                                        <select class="rt-input rt-input--sm" name="status" form="status-form-<?= $i ?>" aria-label="Ubah status retur <?= esc($row->no_service, 'attr') ?>">
                                            <option value="tidak_bisa" <?= $bisa ? '' : 'selected' ?>>Tidak bisa retur</option>
                                            <option value="bisa" <?= $bisa ? 'selected' : '' ?>>Bisa retur</option>
                                        </select>
                                        <button type="submit" class="rt-mini" form="status-form-<?= $i ?>">Ubah</button>
                                    </div>
                                </div>
                            </td>
                            <td data-label="HPP manual">
                                <input type="number" min="0" step="1" inputmode="numeric" class="rt-input rt-input--sm retur-input"
                                    name="items[<?= $i ?>][hpp_manual]" aria-label="HPP manual <?= esc($row->no_service, 'attr') ?>">
                            </td>
                            <td data-label="Hasil retur">
                                <select class="rt-input rt-input--sm retur-input hasil-select" name="items[<?= $i ?>][hasil]" aria-label="Hasil retur <?= esc($row->no_service, 'attr') ?>">
                                    <option value="potong_tagihan">Potong tagihan</option>
                                    <option value="uang_kembali">Uang kembali</option>
                                    <option value="barang_pengganti">Barang pengganti</option>
                                    <option value="ditolak">Ditolak supplier</option>
                                </select>
                            </td>
                            <td data-label="Pengganti">
                                <div class="rt-replacement is-off">
                                    <select class="rt-input rt-input--sm retur-input pengganti-field"
                                        name="items[<?= $i ?>][barang_pengganti_id]" aria-label="Barang pengganti <?= esc($row->no_service, 'attr') ?>">
                                        <option value="">Pilih barang</option>
                                        <?php foreach ($barang as $b): ?>
                                        <option value="<?= (int) $b->idbarang ?>">
                                            <?= esc(trim(($b->kode_barang ?? '') . ' - ' . ($b->nama_barang ?? ''), ' -')) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="number" min="0" step="0.01" inputmode="decimal" class="rt-input rt-input--sm retur-input pengganti-field"
                                        name="items[<?= $i ?>][jumlah_pengganti]" placeholder="Qty pengganti" aria-label="Qty pengganti <?= esc($row->no_service, 'attr') ?>">
                                </div>
                            </td>
                            <td data-label="Keterangan">
                                <input type="text" class="rt-input rt-input--sm retur-input"
                                    name="items[<?= $i ?>][keterangan]" placeholder="Opsional" aria-label="Keterangan <?= esc($row->no_service, 'attr') ?>">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="rt-actionbar">
                <div class="rt-summary">
                    <span><strong class="js-rt-count">0</strong> barang dipilih</span>
                    <span class="rt-summary__value">Total HPP <strong class="js-rt-total">Rp 0</strong></span>
                </div>
                <button type="submit" class="rt-btn rt-btn--primary" <?= empty($suppliers) ? 'disabled' : '' ?>>
                    <iconify-icon icon="solar:inbox-in-linear" width="18" height="18"></iconify-icon>
                    Simpan retur
                </button>
            </div>
        </form>
        <?php endif; ?>
    </section>

    <section class="rt-panel" aria-labelledby="rtRiwayatTitle">
        <div class="rt-panel__head">
            <div>
                <h2 id="rtRiwayatTitle">Riwayat retur</h2>
                <p>Retur yang sudah tersimpan pada periode ini. Batalkan retur untuk mengembalikan status barang menjadi bisa retur.</p>
            </div>
            <span class="rt-panel__count"><strong><?= $jumlahRiwayat ?></strong> transaksi</span>
        </div>

        <?php if (empty($riwayat)): ?>
        <div class="rt-empty">
            <span class="rt-empty__icon">
                <iconify-icon icon="solar:history-linear" width="24" height="24"></iconify-icon>
            </span>
            <h3>Belum ada retur tersimpan</h3>
            <p>Retur yang disimpan pada rentang tanggal ini akan muncul di sini beserta rincian barangnya.</p>
        </div>
        <?php else: ?>
        <div class="rt-table-scroll">
            <table class="rt-table rt-table--riwayat">
                <caption class="visually-hidden">Riwayat retur barang rusak</caption>
                <thead>
                    <tr>
                        <th scope="col">No. retur</th>
                        <th scope="col">Supplier</th>
                        <th scope="col">Unit</th>
                        <th scope="col" class="rt-num">Item</th>
                        <th scope="col" class="rt-num">Total nilai</th>
                        <th scope="col" class="rt-num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riwayat as $header): ?>
                    <tr>
                        <td data-label="No. retur">
                            <div class="rt-cell">
                                <span class="rt-code"><?= esc($header->no_retur) ?></span>
                                <span class="rt-sub"><?= date('d-m-Y', strtotime((string) $header->tanggal)) ?></span>
                            </div>
                        </td>
                        <td data-label="Supplier">
                            <div class="rt-cell">
                                <span class="rt-strong"><?= esc($header->nama_suplier ?? '-') ?></span>
                            </div>
                        </td>
                        <td data-label="Unit">
                            <div class="rt-cell">
                                <span class="rt-unit">
                                    <iconify-icon icon="solar:map-point-linear" width="12" height="12"></iconify-icon>
                                    <span><?= esc($header->NAMA_UNIT ?? '-') ?></span>
                                </span>
                            </div>
                        </td>
                        <td class="rt-num" data-label="Item">
                            <div class="rt-cell">
                                <span class="rt-strong"><?= (int) $header->jumlah_item ?></span>
                            </div>
                        </td>
                        <td class="rt-num" data-label="Total nilai">
                            <div class="rt-cell">
                                <span class="rt-money">Rp <?= number_format((float) $header->total_nilai, 0, ',', '.') ?></span>
                            </div>
                        </td>
                        <td class="rt-num" data-label="Aksi">
                            <div class="rt-actions">
                                <button type="button" class="rt-mini" data-bs-toggle="modal"
                                    data-bs-target="#detailRetur<?= (int) $header->idretur_klaim ?>">Detail</button>
                                <form action="<?= base_url('retur_barang_rusak/batal/' . (int) $header->idretur_klaim) ?>"
                                    method="post" onsubmit="return confirm('Batalkan retur ini? Status barang kembali bisa retur.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="kembali" value="<?= esc($filterQuery, 'attr') ?>">
                                    <button type="submit" class="rt-mini rt-btn--danger-ghost">Batal</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>

<?php foreach ($klaim as $i => $row): ?>
<form id="status-form-<?= $i ?>" action="<?= base_url('retur_barang_rusak/ubah-status/' . (int) $row->idservice_sparepart) ?>"
    method="post" class="d-none">
    <?= csrf_field() ?>
    <input type="hidden" name="kembali" value="<?= esc($filterQuery, 'attr') ?>">
</form>
<?php endforeach; ?>

<?php foreach ($riwayat as $header): ?>
<div class="modal fade rt-modal" id="detailRetur<?= (int) $header->idretur_klaim ?>" tabindex="-1" aria-labelledby="detailReturTitle<?= (int) $header->idretur_klaim ?>" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="rt-modal__title">
                    <span class="rt-modal__icon">
                        <iconify-icon icon="solar:inbox-in-linear" width="20" height="20"></iconify-icon>
                    </span>
                    <div>
                        <h5 class="modal-title" id="detailReturTitle<?= (int) $header->idretur_klaim ?>">Detail retur <?= esc($header->no_retur) ?></h5>
                        <p><?= esc($header->nama_suplier ?? '-') ?> &middot; <?= date('d-m-Y', strtotime((string) $header->tanggal)) ?></p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <dl class="rt-modal__meta">
                    <div>
                        <dt>Supplier</dt>
                        <dd><?= esc($header->nama_suplier ?? '-') ?></dd>
                    </div>
                    <div>
                        <dt>Tanggal retur</dt>
                        <dd><?= date('d-m-Y', strtotime((string) $header->tanggal)) ?></dd>
                    </div>
                    <div>
                        <dt>Unit</dt>
                        <dd><?= esc($header->NAMA_UNIT ?? '-') ?></dd>
                    </div>
                    <div>
                        <dt>Total item</dt>
                        <dd><?= (int) $header->jumlah_item ?></dd>
                    </div>
                    <div>
                        <dt>Total nilai</dt>
                        <dd>Rp <?= number_format((float) $header->total_nilai, 0, ',', '.') ?></dd>
                    </div>
                </dl>

                <div class="rt-table-scroll">
                    <p class="rt-scroll-hint">Geser tabel untuk melihat kolom lainnya.</p>
                    <table class="rt-table">
                        <caption class="visually-hidden">Rincian barang pada retur <?= esc($header->no_retur) ?></caption>
                        <thead>
                            <tr>
                                <th scope="col">Barang</th>
                                <th scope="col" class="rt-num">Qty</th>
                                <th scope="col" class="rt-num">HPP manual</th>
                                <th scope="col">Hasil</th>
                                <th scope="col">Barang pengganti</th>
                                <th scope="col" class="rt-num">Qty pengganti</th>
                                <th scope="col" class="rt-num">Nilai</th>
                                <th scope="col">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($items_by_retur[(int) $header->idretur_klaim] ?? []) as $item): ?>
                            <tr>
                                <td data-label="Barang"><div class="rt-cell"><span class="rt-strong"><?= esc($item->nama_barang ?? '-') ?></span></div></td>
                                <td class="rt-num" data-label="Qty"><div class="rt-cell"><span><?= (float) $item->jumlah ?></span></div></td>
                                <td class="rt-num" data-label="HPP manual"><div class="rt-cell"><span class="rt-money">Rp <?= number_format((float) $item->hpp_manual, 0, ',', '.') ?></span></div></td>
                                <td data-label="Hasil"><div class="rt-cell"><span class="rt-badge <?= ($item->hasil ?? '') === 'ditolak' ? 'rt-badge--warn' : 'rt-badge--success' ?>"><?= esc($hasilLabel[$item->hasil] ?? $item->hasil) ?></span></div></td>
                                <td data-label="Barang pengganti"><div class="rt-cell"><span><?= esc($item->nama_barang_pengganti ?? '-') ?></span></div></td>
                                <td class="rt-num" data-label="Qty pengganti"><div class="rt-cell"><span><?= $item->jumlah_pengganti !== null ? (float) $item->jumlah_pengganti : '-' ?></span></div></td>
                                <td class="rt-num" data-label="Nilai"><div class="rt-cell"><span class="rt-money">Rp <?= number_format((float) $item->nilai, 0, ',', '.') ?></span></div></td>
                                <td data-label="Keterangan"><div class="rt-cell"><span><?= esc($item->keterangan ?? '-') ?></span></div></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($items_by_retur[(int) $header->idretur_klaim])): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">Tidak ada rincian barang.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="rt-btn rt-btn--ghost" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
    (function() {
        const form = document.getElementById('formRetur');
        if (!form) {
            return;
        }

        const rows = Array.from(form.querySelectorAll('.rt-row'));

        function rupiah(value) {
            return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
        }

        function syncRow(row) {
            const checkbox = row.querySelector('.retur-check');
            const hasil = row.querySelector('.hasil-select')?.value;
            const replacement = row.querySelector('.rt-replacement');

            row.classList.toggle('is-selected', checkbox.checked);
            if (replacement) {
                replacement.classList.toggle('is-off', hasil !== 'barang_pengganti');
            }

            row.querySelectorAll('.retur-input').forEach(function(el) {
                let disabled = !checkbox.checked;
                if (el.classList.contains('pengganti-field') && hasil !== 'barang_pengganti') {
                    disabled = true;
                }
                el.disabled = disabled;
            });
        }

        function syncSummary() {
            let count = 0;
            let total = 0;

            rows.forEach(function(row) {
                const checkbox = row.querySelector('.retur-check');
                syncRow(row);
                if (!checkbox.checked) {
                    return;
                }

                count += 1;
                const qty = parseFloat(row.dataset.qty || '0');
                const hpp = parseFloat(row.querySelector('[name$="[hpp_manual]"]')?.value || '0');
                if (qty > 0 && hpp > 0) {
                    total += qty * hpp;
                }
            });

            form.querySelectorAll('.js-rt-count').forEach(function(el) {
                el.textContent = count;
            });
            form.querySelectorAll('.js-rt-total').forEach(function(el) {
                el.textContent = rupiah(total);
            });

            const panelCount = document.querySelector('.rt-panel .rt-panel__count .js-rt-count');
            if (panelCount) {
                panelCount.textContent = count;
            }
        }

        form.addEventListener('change', function(event) {
            if (event.target.classList.contains('retur-check') || event.target.classList.contains('hasil-select')) {
                syncSummary();
            }
        });

        form.addEventListener('input', function(event) {
            if (event.target.name && event.target.name.endsWith('[hpp_manual]')) {
                syncSummary();
            }
        });

        rows.forEach(function(row) {
            const pick = row.querySelector('.rt-pick');
            const checkbox = row.querySelector('.retur-check');
            if (!pick || !checkbox) {
                return;
            }

            pick.addEventListener('click', function(event) {
                if (event.target.closest('input, label, button, select, a') || checkbox.disabled) {
                    return;
                }
                checkbox.checked = !checkbox.checked;
                syncSummary();
            });
        });

        document.querySelectorAll('form[id^="status-form-"]').forEach(function(statusForm) {
            const select = form.querySelector('select[form="' + statusForm.id + '"]');
            const initial = select ? select.value : null;

            statusForm.addEventListener('submit', function(event) {
                const catatan = form.querySelector('#rtCatatan');
                const supplier = form.querySelector('#rtSupplier');
                const dirty = form.querySelector('.retur-check:checked')
                    || (catatan && catatan.value.trim() !== '')
                    || (supplier && supplier.value !== '');

                if (!dirty) {
                    return;
                }
                if (!window.confirm('Ubah status akan memuat ulang halaman dan menghapus isian retur yang belum disimpan. Lanjutkan?')) {
                    event.preventDefault();
                    if (select && initial !== null) {
                        select.value = initial;
                    }
                }
            });
        });

        syncSummary();
    })();
</script>
