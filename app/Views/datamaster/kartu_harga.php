<style>
    .price-card-list { display: grid; gap: .75rem; }
    .price-card { border: 1px solid #e5e7eb; border-radius: .75rem; padding: 1rem; background: #fff; }
    .price-card__title { font-weight: 650; overflow-wrap: anywhere; }
    .price-card__meta { color: #6b7280; font-size: .9rem; }
    .price-card__fields { display: grid; grid-template-columns: minmax(110px, 1fr) minmax(110px, 1fr) auto; gap: .6rem; align-items: end; margin-top: .75rem; }
    .price-card__fields label { display: block; font-size: .8rem; color: #4b5563; margin-bottom: .25rem; }
    @media (max-width: 767.98px) {
        .price-table-wrap { display: none; }
        .price-card__fields { grid-template-columns: 1fr 1fr; }
        .price-card__fields button { grid-column: 1 / -1; width: 100%; }
        .price-history-wrap { overflow-x: auto; }
    }
    @media (min-width: 768px) { .price-card-list { display: none; } }
</style>

<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h4 class="mb-1">Kartu Harga</h4>
            <div class="text-muted">Harga jual bersama untuk transaksi baru. Harga beli dan HPP moving average tidak diubah.</div>
        </div>
        <a class="btn btn-outline-secondary" href="<?= base_url('produk') ?>">Master Barang/Jasa</a>
    </div>

    <?php foreach (['sukses' => 'success', 'gagal' => 'danger', 'info' => 'info'] as $flash => $type): ?>
        <?php if (session()->getFlashdata($flash)): ?><div class="alert alert-<?= $type ?>" role="status"><?= esc(session()->getFlashdata($flash)) ?></div><?php endif; ?>
    <?php endforeach; ?>

    <form class="row g-2 mb-3" method="get" action="<?= base_url('produk/kartu-harga') ?>">
        <div class="col-12 col-md-6"><label class="visually-hidden" for="price-search">Cari ID atau nama</label><input class="form-control" id="price-search" name="q" value="<?= esc($search) ?>" placeholder="Cari ID atau nama barang/jasa"></div>
        <div class="col-auto"><button class="btn btn-primary" type="submit">Cari</button></div>
        <div class="col-auto align-self-center text-muted"><?= number_format(count($items), 0, ',', '.') ?> item pada halaman ini</div>
    </form>

    <div class="alert alert-light border small">
        Harga yang tersimpan tetap menjadi snapshot transaksi lama. Perubahan berlaku mulai waktu simpan untuk transaksi baru. Garansi kosong berarti <strong>Belum diatur</strong>; angka 0 berarti tanpa garansi. Garansi transaksi dipilih eksplisit saat proses service.
    </div>

    <div class="price-table-wrap table-responsive">
        <table class="table table-striped align-middle">
            <thead><tr><th>ID / Kode</th><th>Nama</th><th>Tipe master</th><th>Stok cabang</th><th>Harga jual</th><th>Garansi default</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= (int) $item->idbarang ?> · <?= esc($item->kode_barang) ?></td>
                    <td><?= esc($item->nama_barang) ?></td>
                    <td><?= esc($item->jenis_item ?: 'Perlu klasifikasi') ?></td>
                    <td><?= $item->jenis_item === 'fisik' ? number_format((float) $item->stok_akhir, 0, ',', '.') : '—' ?></td>
                    <td>
                        <?php $formId = 'price-form-' . (int) $item->idbarang; ?>
                        <form id="<?= esc($formId) ?>" method="post" action="<?= base_url('produk/kartu-harga/simpan') ?>">
                            <?= csrf_field() ?><input type="hidden" name="idbarang" value="<?= (int) $item->idbarang ?>">
                            <label class="visually-hidden" for="price-<?= (int) $item->idbarang ?>">Harga jual</label>
                            <input class="form-control" style="min-width:150px" id="price-<?= (int) $item->idbarang ?>" name="harga" inputmode="numeric" pattern="[0-9., ]+" value="<?= (int) $item->harga ?>" aria-label="Harga jual <?= esc($item->kode_barang) ?>" required>
                        </form>
                    </td>
                    <td>
                        <label class="visually-hidden" for="warranty-<?= (int) $item->idbarang ?>">Garansi default dalam hari</label>
                        <input class="form-control" form="<?= esc($formId) ?>" style="min-width:130px" id="warranty-<?= (int) $item->idbarang ?>" name="garansi_hari_default" type="number" min="0" max="9999" value="<?= $item->garansi_hari_default === null ? '' : (int) $item->garansi_hari_default ?>" placeholder="Belum diatur" aria-label="Garansi default dalam hari <?= esc($item->kode_barang) ?>">
                    </td>
                    <td><button form="<?= esc($formId) ?>" class="btn btn-outline-primary" type="submit">Simpan</button></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($items === []): ?><tr><td colspan="7" class="text-center text-muted py-4">Tidak ada barang/jasa yang cocok.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="price-card-list">
        <?php foreach ($items as $item): ?>
            <article class="price-card">
                <div class="price-card__title"><?= esc($item->kode_barang) ?> · <?= esc($item->nama_barang) ?></div>
                <div class="price-card__meta">ID <?= (int) $item->idbarang ?> · <?= esc($item->jenis_item ?: 'Perlu klasifikasi') ?> · Stok <?= $item->jenis_item === 'fisik' ? number_format((float) $item->stok_akhir, 0, ',', '.') : '—' ?></div>
                <form class="price-card__fields" method="post" action="<?= base_url('produk/kartu-harga/simpan') ?>">
                    <?= csrf_field() ?><input type="hidden" name="idbarang" value="<?= (int) $item->idbarang ?>">
                    <div><label for="mobile-price-<?= (int) $item->idbarang ?>">Harga jual (Rp)</label><input class="form-control" id="mobile-price-<?= (int) $item->idbarang ?>" name="harga" inputmode="numeric" pattern="[0-9., ]+" value="<?= (int) $item->harga ?>" required></div>
                    <div><label for="mobile-warranty-<?= (int) $item->idbarang ?>">Garansi default (hari)</label><input class="form-control" id="mobile-warranty-<?= (int) $item->idbarang ?>" name="garansi_hari_default" type="number" min="0" max="9999" value="<?= $item->garansi_hari_default === null ? '' : (int) $item->garansi_hari_default ?>" placeholder="Belum diatur"></div>
                    <button class="btn btn-outline-primary" type="submit">Simpan perubahan</button>
                </form>
            </article>
        <?php endforeach; ?>
        <?php if ($items === []): ?><div class="text-center text-muted py-4">Tidak ada barang/jasa yang cocok.</div><?php endif; ?>
    </div>

    <nav class="d-flex justify-content-between align-items-center my-3" aria-label="Navigasi halaman">
        <a class="btn btn-sm btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= base_url('produk/kartu-harga?q=' . rawurlencode($search) . '&page=' . max(1, $page - 1)) ?>">Sebelumnya</a>
        <span class="small text-muted">Halaman <?= (int) $page ?> / <?= (int) $totalPages ?></span>
        <a class="btn btn-sm btn-outline-secondary <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= base_url('produk/kartu-harga?q=' . rawurlencode($search) . '&page=' . min($totalPages, $page + 1)) ?>">Berikutnya</a>
    </nav>

    <section class="mt-4">
        <h5>Histori perubahan terbaru</h5>
        <div class="price-history-wrap table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead><tr><th>Waktu</th><th>ID / Nama</th><th>Harga sebelumnya → baru</th><th>Garansi sebelumnya → baru</th><th>Petugas</th></tr></thead>
                <tbody>
                <?php foreach ($history as $entry): ?>
                    <tr><td><?= esc($entry->created_at) ?></td><td><?= esc($entry->kode_barang . ' · ' . $entry->nama_barang) ?></td><td>Rp <?= number_format((int) $entry->harga_sebelum, 0, ',', '.') ?> → Rp <?= number_format((int) $entry->harga_sesudah, 0, ',', '.') ?></td><td><?= $entry->garansi_sebelum === null ? 'Belum diatur' : (int) $entry->garansi_sebelum . ' hari' ?> → <?= $entry->garansi_sesudah === null ? 'Belum diatur' : (int) $entry->garansi_sesudah . ' hari' ?></td><td><?= esc($entry->NAMA_AKUN ?? 'Akun tidak diketahui') ?></td></tr>
                <?php endforeach; ?>
                <?php if ($history === []): ?><tr><td colspan="5" class="text-center text-muted">Belum ada perubahan harga/garansi.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
