<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Retur Barang Rusak</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Service</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Retur Barang Rusak</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden mb-4">
    <div class="card-body">
        <form action="<?= base_url('retur_barang_rusak') ?>" method="get" class="row g-2 align-items-end">
            <?php if ($can_see_all_units): ?>
            <div class="col-md-3">
                <label class="form-label">Unit</label>
                <select name="unit" class="form-select">
                    <option value="0">Semua unit</option>
                    <?php foreach ($units as $unit): ?>
                    <option value="<?= (int) $unit->idunit ?>" <?= (int) $selected_unit === (int) $unit->idunit ? 'selected' : '' ?>>
                        <?= esc($unit->NAMA_UNIT) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-3">
                <label class="form-label">Tanggal Klaim Awal</label>
                <input type="date" name="tanggal_awal" class="form-control" value="<?= esc($tanggal_awal, 'attr') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tanggal Klaim Akhir</label>
                <input type="date" name="tanggal_akhir" class="form-control" value="<?= esc($tanggal_akhir, 'attr') ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="<?= base_url('retur_barang_rusak') ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<?php if (session()->getFlashdata('gagal')): ?>
<div class="alert alert-danger mx-4"><?= esc(session()->getFlashdata('gagal')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('sukses')): ?>
<div class="alert alert-success mx-4"><?= esc(session()->getFlashdata('sukses')) ?></div>
<?php endif; ?>

<div class="card w-100 position-relative overflow-hidden mb-4">
    <div class="card-body">
        <h5 class="fw-semibold mb-1">Klaim Belum Diretur</h5>
        <p class="text-muted mb-3">Centang barang yang diretur. Satu transaksi hanya boleh berisi barang dari satu unit dan satu supplier.</p>

        <?php if (empty($suppliers)): ?>
        <div class="alert alert-warning">Belum ada supplier aktif. Tambahkan supplier terlebih dahulu.</div>
        <?php endif; ?>

        <form action="<?= base_url('retur_barang_rusak/simpan') ?>" method="post" id="formRetur">
            <?= csrf_field() ?>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Supplier</label>
                    <select name="suplier_id" class="form-select" required>
                        <option value="">Pilih supplier</option>
                        <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= (int) $supplier->id_suplier ?>"><?= esc($supplier->nama_suplier) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal Retur</label>
                    <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="catatan" class="form-control" placeholder="Opsional">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table border align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 36px;"></th>
                            <th>No Service</th>
                            <th>Tanggal Klaim</th>
                            <th>Unit</th>
                            <th>Barang Pengganti</th>
                            <th>Barang Rusak</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">HPP Asli</th>
                            <th>Status</th>
                            <th style="min-width: 130px;">HPP Manual</th>
                            <th style="min-width: 160px;">Hasil Retur</th>
                            <th style="min-width: 180px;">Barang Pengganti Baru</th>
                            <th style="min-width: 100px;">Qty Pengganti</th>
                            <th style="min-width: 160px;">Keterangan</th>
                            <th style="min-width: 150px;">Ubah Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($klaim as $i => $row): ?>
                        <?php $bisa = $row->status_retur === 'bisa'; ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input retur-check"
                                    name="items[<?= $i ?>][selected]" value="1" <?= $bisa ? '' : 'disabled' ?>>
                                <input type="hidden" name="items[<?= $i ?>][id]" value="<?= (int) $row->idservice_sparepart ?>">
                            </td>
                            <td><?= esc($row->no_service) ?></td>
                            <td><?= date('d-m-Y', strtotime((string) $row->tanggal_claim_garansi)) ?></td>
                            <td><?= esc($row->NAMA_UNIT ?? '-') ?></td>
                            <td><?= esc($row->nama_barang ?? '-') ?></td>
                            <td><?= esc($row->nama_barang_rusak ?? '-') ?></td>
                            <td class="text-end"><?= (float) $row->jumlah_tambahan_garansi ?></td>
                            <td class="text-end">Rp <?= number_format((float) $row->hpp_penjualan_garansi, 0, ',', '.') ?></td>
                            <td>
                                <span class="badge <?= $bisa ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                    <?= $bisa ? 'Bisa retur' : 'Tidak bisa' ?>
                                </span>
                            </td>
                            <td>
                                <input type="number" min="0" step="1" class="form-control form-control-sm retur-input"
                                    name="items[<?= $i ?>][hpp_manual]" placeholder="HPP manual">
                            </td>
                            <td>
                                <select class="form-select form-select-sm retur-input hasil-select" name="items[<?= $i ?>][hasil]">
                                    <option value="potong_tagihan">Potong tagihan</option>
                                    <option value="uang_kembali">Uang kembali</option>
                                    <option value="barang_pengganti">Barang pengganti</option>
                                    <option value="ditolak">Ditolak supplier</option>
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm retur-input pengganti-field"
                                    name="items[<?= $i ?>][barang_pengganti_id]">
                                    <option value="">Pilih barang</option>
                                    <?php foreach ($barang as $b): ?>
                                    <option value="<?= (int) $b->idbarang ?>">
                                        <?= esc(trim(($b->kode_barang ?? '') . ' - ' . ($b->nama_barang ?? ''), ' -')) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" min="0" step="0.01" class="form-control form-control-sm retur-input pengganti-field"
                                    name="items[<?= $i ?>][jumlah_pengganti]" placeholder="Qty">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm retur-input"
                                    name="items[<?= $i ?>][keterangan]" placeholder="Opsional">
                            </td>
                            <td>
                                <select class="form-select form-select-sm" name="status" form="status-form-<?= $i ?>">
                                    <option value="tidak_bisa" <?= $bisa ? '' : 'selected' ?>>Tidak bisa retur</option>
                                    <option value="bisa" <?= $bisa ? 'selected' : '' ?>>Bisa retur</option>
                                </select>
                                <button type="submit" class="btn btn-outline-secondary btn-sm mt-1" form="status-form-<?= $i ?>">Ubah</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($klaim)): ?>
                        <tr>
                            <td colspan="15" class="text-center text-muted">Tidak ada klaim yang menunggu retur pada periode ini.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary" <?= empty($klaim) || empty($suppliers) ? 'disabled' : '' ?>>
                    Simpan Retur
                </button>
            </div>
        </form>

        <?php foreach ($klaim as $i => $row): ?>
        <form id="status-form-<?= $i ?>" action="<?= base_url('retur_barang_rusak/ubah-status/' . (int) $row->idservice_sparepart) ?>"
            method="post" class="d-none">
            <?= csrf_field() ?>
        </form>
        <?php endforeach; ?>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden mb-4">
    <div class="card-body">
        <h5 class="fw-semibold mb-3">Riwayat Retur</h5>
        <div class="table-responsive">
            <table class="table border align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No Retur</th>
                        <th>Tanggal</th>
                        <th>Supplier</th>
                        <th>Unit</th>
                        <th class="text-end">Item</th>
                        <th class="text-end">Total Nilai</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riwayat as $header): ?>
                    <tr>
                        <td><?= esc($header->no_retur) ?></td>
                        <td><?= date('d-m-Y', strtotime((string) $header->tanggal)) ?></td>
                        <td><?= esc($header->nama_suplier ?? '-') ?></td>
                        <td><?= esc($header->NAMA_UNIT ?? '-') ?></td>
                        <td class="text-end"><?= (int) $header->jumlah_item ?></td>
                        <td class="text-end">Rp <?= number_format((float) $header->total_nilai, 0, ',', '.') ?></td>
                        <td>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
                                data-bs-target="#detailRetur<?= (int) $header->idretur_klaim ?>">Detail</button>
                            <form action="<?= base_url('retur_barang_rusak/batal/' . (int) $header->idretur_klaim) ?>"
                                method="post" class="d-inline" onsubmit="return confirm('Batalkan retur ini? Status barang kembali bisa retur.')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline-danger btn-sm">Batal</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($riwayat)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">Belum ada retur pada periode ini.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($riwayat as $header): ?>
<div class="modal fade" id="detailRetur<?= (int) $header->idretur_klaim ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Retur <?= esc($header->no_retur) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Barang</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">HPP Manual</th>
                                <th>Hasil</th>
                                <th>Barang Pengganti</th>
                                <th class="text-end">Qty Pengganti</th>
                                <th class="text-end">Nilai</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($items_by_retur[(int) $header->idretur_klaim] ?? []) as $item): ?>
                            <tr>
                                <td><?= esc($item->nama_barang ?? '-') ?></td>
                                <td class="text-end"><?= (float) $item->jumlah ?></td>
                                <td class="text-end">Rp <?= number_format((float) $item->hpp_manual, 0, ',', '.') ?></td>
                                <td><?= esc($item->hasil) ?></td>
                                <td><?= esc($item->nama_barang_pengganti ?? '-') ?></td>
                                <td class="text-end"><?= $item->jumlah_pengganti !== null ? (float) $item->jumlah_pengganti : '-' ?></td>
                                <td class="text-end">Rp <?= number_format((float) $item->nilai, 0, ',', '.') ?></td>
                                <td><?= esc($item->keterangan ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
document.querySelectorAll('.retur-check, .hasil-select').forEach(el => {
    el.addEventListener('change', () => syncReturRow(el.closest('tr')));
});
document.querySelectorAll('.retur-check').forEach(cb => syncReturRow(cb.closest('tr')));

function syncReturRow(row) {
    const checkbox = row.querySelector('.retur-check');
    const hasil = row.querySelector('.hasil-select')?.value;

    row.querySelectorAll('.retur-input').forEach(el => {
        let disabled = !checkbox.checked;
        if (el.classList.contains('pengganti-field') && hasil !== 'barang_pengganti') {
            disabled = true;
        }
        el.disabled = disabled;
    });
}
</script>
