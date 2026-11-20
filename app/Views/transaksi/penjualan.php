<?= $this->include('transaksi/_penjualan_theme') ?>
<?= $this->include('partials/sparepart_picker_style') ?>

<script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>

<?php
$tanggalHariIni = date('Y-m-d');
$namaUnit = session('NAMA_UNIT') ?: 'Unit aktif';
$bankOptions = [];

foreach (($bank ?? []) as $b) {
    $label = trim((string) ($b->nama_bank ?? ''));
    $atasNama = trim((string) ($b->atas_nama ?? ''));

    if ($atasNama !== '') {
        $label .= ' ' . $atasNama;
    }

    $label .= ' : ' . ($b->norek ?? '');

    $bankOptions[] = [
        'id' => (string) ($b->idbank ?? ''),
        'label' => $label,
    ];
}

$bankJson = json_encode(
    $bankOptions,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
);
?>

<div class="penjualan-page">
    <header class="pj-head">
        <ol class="pj-crumb">
            <li><a href="<?= base_url('/') ?>">Transaksi</a></li>
            <li aria-current="page">Penjualan</li>
        </ol>
        <div class="pj-head__row">
            <div>
                <h1>Penjualan</h1>
                <p class="pj-sub">Pilih pelanggan, masukkan produk, lalu bagi pembayaran tunai dan transfer.</p>
            </div>
            <span class="pj-unit">
                <iconify-icon icon="solar:shop-outline" width="15" height="15"></iconify-icon>
                <?= esc($namaUnit) ?>
            </span>
        </div>
    </header>

    <form action="<?= base_url('insert_penjualan') ?>" method="post" id="form_penjualan" class="pj-sheet"
        enctype="multipart/form-data" novalidate>

        <!-- ===== 1. Pihak & Dokumen ===== -->
        <section class="pj-section" aria-labelledby="pj-pihak-title">
            <div class="pj-section__head">
                <div>
                    <h2 class="pj-section__title" id="pj-pihak-title">Pihak &amp; Dokumen</h2>
                    <p class="pj-section__hint">Tentukan tanggal, kasir yang melayani, dan pelanggan yang menerima
                        nota.</p>
                </div>
            </div>

            <div class="pj-grid">
                <div class="pj-field">
                    <label for="tanggal_masuk">Tanggal</label>
                    <input type="date" class="pj-input" id="tanggal_masuk" name="tanggal_masuk"
                        value="<?= $tanggalHariIni ?>" required>
                    <p class="pj-hint">Semua unit boleh memilih tanggal, termasuk backdate.</p>
                </div>

                <div class="pj-field">
                    <label for="sales_by">Staff</label>
                    <select class="pj-select" id="sales_by" name="sales_by" required
                        aria-describedby="pj-staff-error">
                        <option value="">— Pilih staff —</option>
                        <?php foreach ($frontliner as $akun): ?>
                            <option value="<?= esc($akun->ID_AKUN) ?>"><?= esc($akun->NAMA_AKUN) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="pj-error" id="pj-staff-error" hidden>Pilih staff yang melayani.</p>
                </div>

                <div class="pj-field">
                    <label for="pelanggan">Pelanggan</label>
                    <div class="pj-customer">
                        <input type="text" class="pj-input" id="pelanggan" name="pelanggan"
                            placeholder="Belum dipilih" readonly required aria-describedby="pj-customer-error">
                        <button type="button" id="pelanggan-button" class="pj-btn pj-btn--outline"
                            data-bs-toggle="modal" data-bs-target="#pelangganModal">
                            <iconify-icon icon="solar:user-circle-outline" width="18" height="18"></iconify-icon>
                            Pilih
                        </button>
                    </div>
                    <span class="pj-customer__meta" id="pj-customer-meta">
                        <iconify-icon icon="solar:map-point-outline" width="14" height="14"></iconify-icon>
                        Alamat pelanggan akan tampil di sini.
                    </span>
                    <p class="pj-error" id="pj-customer-error" hidden>Pilih pelanggan terlebih dahulu.</p>
                </div>
            </div>
        </section>

        <!-- ===== 2. Produk ===== -->
        <section class="pj-section" aria-labelledby="pj-produk-title">
            <div class="pj-section__head">
                <div>
                    <h2 class="pj-section__title" id="pj-produk-title">Produk</h2>
                    <p class="pj-section__hint">Harga jual mengikuti unit aktif. Centang PPN 11% bila barang kena
                        pajak.</p>
                </div>
                <div class="pj-toolbar">
                    <span class="pj-count" id="pj-item-count">0 item</span>
                    <button type="button" class="pj-btn pj-btn--primary" id="pj-pilih-produk"
                        data-bs-toggle="modal" data-bs-target="#pilih-produk-modal">
                        <iconify-icon icon="solar:password-minimalistic-input-outline" width="18" height="18">
                        </iconify-icon>
                        Pilih Produk
                    </button>
                    <button type="button" class="pj-btn pj-btn--ghost" id="pj-clear-cart">Kosongkan</button>
                </div>
            </div>

            <p class="pj-form-error" id="pj-produk-error" hidden>Tambahkan minimal satu produk sebelum
                menyimpan.</p>

            <div class="pj-table-wrap">
                <table class="pj-table">
                    <colgroup>
                        <col>
                        <col style="width: 180px;">
                        <col style="width: 96px;">
                        <col style="width: 160px;">
                        <col style="width: 88px;">
                        <col style="width: 150px;">
                        <col style="width: 56px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th class="pj-num">Harga</th>
                            <th class="pj-num">Qty</th>
                            <th class="pj-num">Diskon</th>
                            <th class="text-center">PPN</th>
                            <th class="pj-num">Subtotal</th>
                            <th><span class="visually-hidden">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody id="selected-produk-table">
                        <tr class="pj-empty" id="pj-empty-row">
                            <td colspan="7">
                                <iconify-icon icon="solar:cart-large-2-outline" width="30" height="30"></iconify-icon>
                                <span class="pj-empty__title">Belum ada produk</span>
                                Pilih barang, bundle, atau jasa untuk memulai nota.
                                <div>
                                    <button type="button" class="pj-btn pj-btn--outline pj-btn--sm"
                                        data-bs-toggle="modal" data-bs-target="#pilih-produk-modal">
                                        <iconify-icon icon="solar:add-circle-outline" width="16" height="16">
                                        </iconify-icon>
                                        Pilih Produk
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ===== 3. Pembayaran ===== -->
        <section class="pj-section" aria-labelledby="pj-bayar-title">
            <div class="pj-section__head">
                <div>
                    <h2 class="pj-section__title" id="pj-bayar-title">Pembayaran</h2>
                    <p class="pj-section__hint">Pilih satu metode. Nominal yang tidak dipakai tidak akan
                        dikirim.</p>
                </div>
            </div>

            <div class="pj-methods" role="radiogroup" aria-label="Metode pembayaran">
                <label class="pj-method">
                    <input type="radio" name="metode_bayar" value="tunai" id="metode_tunai">
                    <span class="pj-method__box">
                        <span class="pj-method__icon">
                            <iconify-icon icon="solar:wad-of-money-outline" width="20" height="20"></iconify-icon>
                        </span>
                        <span>
                            <span class="pj-method__label">Tunai</span>
                            <span class="pj-method__hint">Uang tunai dari laci</span>
                        </span>
                    </span>
                </label>

                <label class="pj-method">
                    <input type="radio" name="metode_bayar" value="transfer" id="metode_transfer">
                    <span class="pj-method__box">
                        <span class="pj-method__icon">
                            <iconify-icon icon="solar:card-transfer-outline" width="20" height="20"></iconify-icon>
                        </span>
                        <span>
                            <span class="pj-method__label">Transfer</span>
                            <span class="pj-method__hint">Masuk ke rekening bank</span>
                        </span>
                    </span>
                </label>

                <label class="pj-method">
                    <input type="radio" name="metode_bayar" value="tunai_transfer" id="metode_tunai_transfer">
                    <span class="pj-method__box">
                        <span class="pj-method__icon">
                            <iconify-icon icon="solar:wallet-money-outline" width="20" height="20"></iconify-icon>
                        </span>
                        <span>
                            <span class="pj-method__label">Tunai + Transfer</span>
                            <span class="pj-method__hint">Gabungan dua cara bayar</span>
                        </span>
                    </span>
                </label>
            </div>

            <div class="pj-zone" id="pj-zone-tunai" hidden>
                <div class="pj-zone__head">
                    <h3 class="pj-zone__title">Uang tunai</h3>
                    <span class="pj-hint">Ketik nominal yang diterima dari pelanggan.</span>
                </div>
                <div class="pj-cash">
                    <input type="text" class="pj-input pj-num" id="bayar" name="bayar" value="Rp 0"
                        inputmode="numeric" autocomplete="off" aria-label="Nominal tunai">
                    <div class="pj-quick">
                        <button type="button" class="pj-btn pj-btn--outline" id="pj-uang-pas">Uang pas</button>
                    </div>
                </div>
            </div>

            <div class="pj-zone" id="pj-zone-transfer" hidden>
                <div class="pj-zone__head">
                    <h3 class="pj-zone__title">Transfer bank</h3>
                    <span class="pj-hint">Satu baris untuk satu rekening tujuan.</span>
                </div>
                <div class="pj-bank-list" id="bank-payment-container"></div>
                <p class="pj-bank-note" id="pj-bank-empty" <?= empty($bankOptions) ? '' : 'hidden' ?>>
                    <iconify-icon icon="solar:danger-triangle-outline" width="15" height="15"></iconify-icon>
                    Belum ada rekening aktif untuk unit ini.
                </p>
                <p class="pj-error" id="pj-bank-error" hidden>Pilih rekening untuk setiap nominal transfer.</p>
                <button type="button" class="pj-btn pj-btn--outline pj-btn--sm mt-2" id="tambah-bank"
                    <?= empty($bankOptions) ? 'disabled' : '' ?>>
                    <iconify-icon icon="solar:add-circle-outline" width="16" height="16"></iconify-icon>
                    Tambah Bank
                </button>
            </div>

            <div class="pj-pay-status is-empty" id="pj-pay-status" aria-live="polite">
                <iconify-icon id="pj-pay-icon" icon="solar:info-circle-outline" width="17" height="17"></iconify-icon>
                <span id="pj-pay-text">Pilih metode pembayaran untuk membuka kolom nominal.</span>
            </div>
        </section>

        <!--
            ID pelanggan dikirim lewat hidden input. Modal pelanggan berada di
            luar <form> supaya tidak terpotong overflow/transform lembar dan
            tidak membuat stacking context yang menutupi tombolnya sendiri.
        -->
        <input type="hidden" id="selectedidpelanggan" name="selectedidpelanggan" value="">

        <div class="pj-dock" role="group" aria-label="Ringkasan pembayaran">
            <div class="pj-dock__figures">
                <label class="pj-figure" for="total-diskon">
                    <span>Diskon</span>
                    <input type="text" id="total-diskon" name="total-diskon" class="pj-num" value="Rp 0" readonly>
                </label>
                <label class="pj-figure" for="total-ppn">
                    <span>PPN</span>
                    <input type="text" id="total-ppn" name="total-ppn" class="pj-num" value="Rp 0" readonly>
                </label>
                <label class="pj-figure pj-figure--total" for="total-harga">
                    <span>Total</span>
                    <input type="text" id="total-harga" name="total-harga" class="pj-num" value="Rp 0" readonly>
                </label>
                <div class="pj-figure">
                    <span>Dibayar</span>
                    <span class="pj-figure__value" id="pj-dibayar">Rp 0</span>
                </div>
                <div class="pj-figure pj-figure--debt">
                    <span>Sisa</span>
                    <span class="pj-figure__value" id="pj-sisa">Rp 0</span>
                </div>
                <label class="pj-figure" for="kembalian">
                    <span>Kembalian</span>
                    <input type="text" id="kembalian" name="kembalian" class="pj-num" value="Rp 0" readonly>
                </label>
            </div>

            <div class="pj-dock__actions">
                <div class="pj-dock__buttons">
                    <button type="submit" name="action" value="simpan" class="pj-btn pj-btn--primary"
                        id="pj-save">
                        <iconify-icon icon="solar:check-circle-outline" width="18" height="18"></iconify-icon>
                        Simpan
                    </button>
                    <button type="submit" name="action" value="simpan_thermal" class="pj-btn pj-btn--outline"
                        id="pj-save-thermal">
                        <iconify-icon icon="solar:printer-outline" width="18" height="18"></iconify-icon>
                        Simpan &amp; Cetak
                    </button>
                </div>
                <p class="pj-save-hint">Kedua tombol menyimpan lalu mencetak struk thermal.</p>
            </div>

            <input type="hidden" id="hutang" name="hutang" value="Rp 0" readonly>
        </div>
    </form>
</div>

<!-- ===== Modal Pelanggan ===== -->
<div class="modal fade pj-modal" id="pelangganModal" tabindex="-1" aria-labelledby="pelangganModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pelangganModalLabel">Cari Data Pelanggan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="pj-modal-note">Cari berdasarkan nama atau nomor HP.</p>
                <select id="pelanggan-select" class="select2 form-control" style="width: 100%;">
                    <option value="" disabled selected>Pilih pelanggan</option>
                    <?php foreach ($pelanggan as $p): ?>
                        <option value="<?= htmlspecialchars($p->id_pelanggan) ?>"
                            data-provinsi="<?= htmlspecialchars($p->provinsi ?? '') ?>"
                            data-kabupaten="<?= htmlspecialchars($p->kabupaten ?? '') ?>"
                            data-kecamatan="<?= htmlspecialchars($p->kecamatan ?? '') ?>">
                            <?= htmlspecialchars($p->nama) ?> : <?= htmlspecialchars($p->no_hp) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <p class="pj-modal-error" id="pj-pelanggan-error" hidden>Silakan pilih pelanggan terlebih dahulu.</p>
                <p class="pj-modal-success" id="pj-pelanggan-success" hidden></p>

                <div class="pj-modal-actions">
                    <button id="btnTambahPelanggan" type="button" class="pj-btn pj-btn--outline">
                        <iconify-icon icon="solar:user-plus-outline" width="17" height="17"></iconify-icon>
                        Tambah Pelanggan
                    </button>
                    <button id="btnPilihPelanggan" type="button" class="pj-btn pj-btn--primary">Pilih</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== Modal Pilih Produk ===== -->
<div class="modal fade pj-picker-modal" id="pilih-produk-modal" tabindex="-1" aria-labelledby="produkModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <span class="bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center"
                        style="width: 36px; height: 36px;">
                        <i class="bi bi-box-seam fs-5"></i>
                    </span>
                    <div>
                        <h5 class="modal-title" id="produkModalLabel">Pilih Produk</h5>
                        <p class="text-muted fs-3 mb-0">Cari nama atau kode, lalu centang yang dipakai.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body pt-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="service-sp-search flex-grow-1">
                        <i class="bi bi-search service-sp-search-icon"></i>
                        <input type="text" class="form-control" id="pj-picker-search"
                            placeholder="Cari nama, kode, atau warna produk..." autocomplete="off">
                    </div>
                    <span class="service-sp-unit badge text-bg-light border d-none d-sm-inline-flex">
                        <i class="bi bi-shop me-1"></i><?= esc($namaUnit) ?>
                    </span>
                </div>

                <ul class="nav pj-picker-tabs" id="produkTab" role="tablist">
                    <li role="presentation">
                        <button class="nav-link active" id="produk-tab" data-bs-toggle="tab"
                            data-bs-target="#produk-content" type="button" role="tab" aria-controls="produk-content"
                            aria-selected="true">Produk</button>
                    </li>
                    <li role="presentation">
                        <button class="nav-link" id="bundle-tab" data-bs-toggle="tab" data-bs-target="#bundle-content"
                            type="button" role="tab" aria-controls="bundle-content" aria-selected="false">Bundle</button>
                    </li>
                    <li role="presentation">
                        <button class="nav-link" id="jasa-tab" data-bs-toggle="tab" data-bs-target="#jasa-content"
                            type="button" role="tab" aria-controls="jasa-content" aria-selected="false">Jasa</button>
                    </li>
                </ul>

                <div class="tab-content" id="produkTabContent">
                    <!-- Produk -->
                    <div class="tab-pane fade show active" id="produk-content" role="tabpanel"
                        aria-labelledby="produk-tab">
                        <div class="service-sp-list" id="produk-modal-table">
                            <?php foreach ($produk as $p): ?>
                                <?php
                                $stok = $p->stok_akhir;
                                $stokHabis = $stok === null || (int) $stok <= 0;
                                $stokRendah = !$stokHabis && (int) $stok <= 2;
                                $cariProduk = implode(' ', [
                                    $p->kode_barang ?? '',
                                    $p->nama_barang ?? '',
                                    $p->nama_kategori ?? '',
                                    $p->imei ?? '',
                                    $p->warna ?? '',
                                    $p->jenis_hp ?? '',
                                    $p->nama_suplier ?? '',
                                ]);
                                $hppUnit = (float) ($p->hpp_unit ?? $p->harga_beli ?? 0);
                                $namaSuplier = trim((string) ($p->nama_suplier ?? ''));
                                $warna = trim((string) ($p->warna ?? ''));
                                $jenisHp = trim((string) ($p->jenis_hp ?? ''));
                                ?>
                                <label
                                    class="service-sp-item <?= $stokHabis ? 'is-disabled' : '' ?>"
                                    data-search="<?= esc(strtolower($cariProduk)) ?>">
                                    <input type="checkbox" class="produk-checkbox service-sp-item__input"
                                        id="pick-produk-<?= $p->idbarang ?>"
                                        data-id="<?= $p->idbarang ?>"
                                        data-kode="<?= esc($p->kode_barang) ?>"
                                        data-nama="<?= esc($p->nama_barang) ?>"
                                        data-harga="<?= (int) round((float) $p->harga_jual) ?>"
                                        data-kategori="<?= esc($p->nama_kategori) ?>"
                                        data-input="<?= esc($p->input ?? '') ?>"
                                        data-kondisi_barang="<?= esc($p->status_barang) ?>"
                                        <?= $stokHabis ? 'disabled' : '' ?>>
                                    <span class="service-sp-item__box">
                                        <span class="service-sp-tick" aria-hidden="true">
                                            <i class="bi bi-check-lg"></i>
                                        </span>
                                        <span class="service-sp-item__main">
                                            <span class="service-sp-name"><?= esc($p->nama_barang) ?></span>
                                            <span class="service-sp-meta">
                                                <span class="service-sp-code"><?= esc($p->kode_barang ?: '-') ?></span>
                                                <?php if ($warna !== '' && $warna !== 'Tidak Ada'): ?>
                                                    <span class="service-sp-meta-item">
                                                        <i class="bi bi-droplet-half"></i><?= esc($warna) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($jenisHp !== '' && $jenisHp !== 'Tidak Ada'): ?>
                                                    <span class="service-sp-meta-item">
                                                        <i class="bi bi-phone"></i><?= esc($jenisHp) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($namaSuplier !== ''): ?>
                                                    <span class="service-sp-meta-item">
                                                        <i class="bi bi-truck"></i><?= esc($namaSuplier) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($hppUnit > 0): ?>
                                                    <span class="service-sp-meta-item">
                                                        <i class="bi bi-tag"></i>HPP <?= 'Rp ' . number_format($hppUnit, 0, ',', '.') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                        <span class="service-sp-side">
                                            <span class="service-sp-price"><?= 'Rp ' . number_format((float) $p->harga_jual, 0, ',', '.') ?></span>
                                            <span class="service-sp-stock <?= ($stokHabis || $stokRendah) ? 'is-low' : '' ?>">
                                                Stok <?= (int) $stok ?>
                                            </span>
                                        </span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="service-sp-state" id="produk-empty-state" hidden>
                            <i class="bi bi-inbox" aria-hidden="true"></i>
                            <p>Tidak ada produk yang cocok dengan pencarian.</p>
                        </div>
                    </div>

                    <!-- Bundle -->
                    <div class="tab-pane fade" id="bundle-content" role="tabpanel" aria-labelledby="bundle-tab">
                        <div class="service-sp-list" id="bundle-modal-table">
                            <?php foreach ($bundle as $p): ?>
                                <?php $bundleTersedia = ($p->status_stok ?? '') === 'Tersedia'; ?>
                                <label
                                    class="service-sp-item <?= $bundleTersedia ? '' : 'is-disabled' ?>"
                                    data-search="<?= esc(strtolower(($p->nama_bundle ?? '') . ' bundle ' . ($p->status_stok ?? ''))) ?>">
                                    <input type="checkbox" class="produk-checkbox service-sp-item__input"
                                        id="pick-bundle-<?= $p->idbundle ?>"
                                        data-id="bundle<?= $p->idbundle ?>" data-kode="Bundle"
                                        data-nama="<?= esc($p->nama_bundle) ?>"
                                        data-harga="<?= $p->harga_jual ?>"
                                        data-kategori="Bundle" data-input="Bundle"
                                        <?= $bundleTersedia ? '' : 'disabled' ?>>
                                    <span class="service-sp-item__box">
                                        <span class="service-sp-tick" aria-hidden="true">
                                            <i class="bi bi-check-lg"></i>
                                        </span>
                                        <span class="service-sp-item__main">
                                            <span class="service-sp-name"><?= esc($p->nama_bundle) ?></span>
                                            <span class="service-sp-meta">
                                                <span class="service-sp-code">Bundle</span>
                                                <span class="service-sp-meta-item">
                                                    <i class="bi bi-box2-heart"></i>Paket beberapa barang dalam satu harga
                                                </span>
                                            </span>
                                        </span>
                                        <span class="service-sp-side">
                                            <span class="service-sp-price"><?= 'Rp ' . number_format((float) $p->harga_jual, 0, ',', '.') ?></span>
                                            <span class="service-sp-stock">
                                                <?= esc($p->status_stok) ?>
                                            </span>
                                            <button type="button" class="btn btn-outline-secondary btn-sm btn-check-bundle"
                                                data-id="<?= $p->idbundle ?>" data-nama="<?= esc($p->nama_bundle) ?>"
                                                data-detail="<?= htmlspecialchars(json_encode($p->detail ?: []), ENT_QUOTES, 'UTF-8') ?>">
                                                Lihat isi
                                            </button>
                                        </span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="service-sp-state" id="bundle-empty-state" hidden>
                            <i class="bi bi-inbox" aria-hidden="true"></i>
                            <p>Tidak ada bundle yang cocok dengan pencarian.</p>
                        </div>
                    </div>

                    <!-- Jasa -->
                    <div class="tab-pane fade" id="jasa-content" role="tabpanel" aria-labelledby="jasa-tab">
                        <div class="service-sp-list" id="jasa-modal-table">
                            <?php foreach ($jasa as $j): ?>
                                <label class="service-sp-item"
                                    data-search="<?= esc(strtolower(($j->kode_barang ?? '') . ' ' . ($j->nama_barang ?? '') . ' jasa')) ?>">
                                    <input type="checkbox" class="produk-checkbox service-sp-item__input"
                                        id="pick-jasa-<?= $j->idbarang ?>"
                                        data-id="<?= (int) $j->idbarang ?>"
                                        data-kode="<?= esc($j->kode_barang) ?>"
                                        data-nama="<?= esc($j->nama_barang) ?>"
                                        data-harga="<?= (int) round((float) $j->harga_jual) ?>"
                                        data-kategori="Jasa" data-kondisi_barang="0">
                                    <span class="service-sp-item__box">
                                        <span class="service-sp-tick" aria-hidden="true">
                                            <i class="bi bi-check-lg"></i>
                                        </span>
                                        <span class="service-sp-item__main">
                                            <span class="service-sp-name"><?= esc($j->nama_barang) ?></span>
                                            <span class="service-sp-meta">
                                                <span class="service-sp-code"><?= esc($j->kode_barang ?: '-') ?></span>
                                                <span class="service-sp-meta-item">
                                                    <i class="bi bi-tools"></i>Jasa
                                                </span>
                                            </span>
                                        </span>
                                        <span class="service-sp-side">
                                            <span class="service-sp-price"><?= 'Rp ' . number_format((float) $j->harga_jual, 0, ',', '.') ?></span>
                                            <span class="service-sp-stock">Jasa</span>
                                        </span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="service-sp-state" id="jasa-empty-state" hidden>
                            <i class="bi bi-inbox" aria-hidden="true"></i>
                            <p>Tidak ada jasa yang cocok dengan pencarian.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer justify-content-between">
                <span class="service-sp-count" id="pj-pick-count" aria-live="polite">Belum ada yang dipilih</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" id="confirm-produk-btn" class="btn btn-primary d-inline-flex align-items-center gap-2"
                        disabled>
                        <i class="bi bi-plus-lg"></i> Tambahkan
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== Modal Detail Bundle ===== -->
<div class="modal fade pj-modal" id="detailBundleModal" tabindex="-1" aria-labelledby="detailBundleLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailBundleLabel">Detail Bundle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <table class="pj-bundle-detail">
                    <thead>
                        <tr>
                            <th>Nama Barang</th>
                            <th class="pj-num">Jumlah</th>
                            <th class="pj-num">Stok Akhir</th>
                        </tr>
                    </thead>
                    <tbody id="bundle-detail-body"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="pj-btn pj-btn--outline" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== Modal Tambah Pelanggan (di luar form utama) ===== -->
<div class="modal fade pj-modal" id="modalTambahPelanggan" tabindex="-1" aria-labelledby="modalTambahLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formTambahPelanggan">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTambahLabel">Tambah Pelanggan Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="modal_nama">Nama</label>
                        <input type="text" class="form-control" id="modal_nama" name="nama" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="modal_no_hp">Nomor HP</label>
                        <input type="text" class="form-control" id="modal_no_hp" name="no_hp" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="modal_provinsi">Provinsi</label>
                        <select name="provinsi" id="modal_provinsi"
                            class="js-domisili form-control form-select" style="width: 100%;" required>
                            <option value="">— Pilih / cari provinsi —</option>
                            <?php foreach ($provinsi as $prov): ?>
                                <option value="<?= esc($prov->name) ?>"><?= esc($prov->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="modal_kabupaten">Kabupaten</label>
                        <select name="kabupaten" id="modal_kabupaten"
                            class="js-domisili form-control form-select" style="width: 100%;" required>
                            <option value="">— Pilih / cari kabupaten —</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="modal_kecamatan">Kecamatan</label>
                        <select name="kecamatan" id="modal_kecamatan"
                            class="js-domisili form-control form-select" style="width: 100%;" required>
                            <option value="">— Pilih / cari kecamatan —</option>
                        </select>
                    </div>

                    <p class="pj-modal-error" id="pj-add-error" hidden></p>
                </div>
                <div class="modal-footer">
                    <button type="button" id="btnbataltambah" class="pj-btn pj-btn--outline"
                        data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="pj-btn pj-btn--primary">Simpan Pelanggan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function formatToRupiah(angka) {
        angka = String(angka);
        const cleaned = angka.replace(/[^\d]/g, '');
        const number = parseInt(cleaned) || 0;
        return 'Rp ' + number.toLocaleString('id-ID');
    }

    function unformatRupiah(rupiah) {
        return parseInt(String(rupiah).replace(/[^\d]/g, '')) || 0;
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('form_penjualan');
        const selectedTable = document.getElementById('selected-produk-table');
        const emptyRow = document.getElementById('pj-empty-row');
        const itemCount = document.getElementById('pj-item-count');
        const produkError = document.getElementById('pj-produk-error');
        const customerError = document.getElementById('pj-customer-error');
        const staffError = document.getElementById('pj-staff-error');
        const bankError = document.getElementById('pj-bank-error');
        const pelangganInput = document.getElementById('pelanggan');
        const pelangganMeta = document.getElementById('pj-customer-meta');
        const salesBy = document.getElementById('sales_by');
        const saveButtons = [document.getElementById('pj-save'), document.getElementById('pj-save-thermal')];
        const clearBtn = document.getElementById('pj-clear-cart');
        const clearLabel = clearBtn.innerHTML;

        const totalDiskonInput = document.getElementById('total-diskon');
        const totalPPNInput = document.getElementById('total-ppn');
        const totalHargaInput = document.getElementById('total-harga');
        const bayarInput = document.getElementById('bayar');
        const hutangInput = document.getElementById('hutang');
        const kembalianInput = document.getElementById('kembalian');
        const dibayarEl = document.getElementById('pj-dibayar');
        const sisaEl = document.getElementById('pj-sisa');

        const zoneTunai = document.getElementById('pj-zone-tunai');
        const zoneTransfer = document.getElementById('pj-zone-transfer');
        const bankContainer = document.getElementById('bank-payment-container');
        const tambahBankBtn = document.getElementById('tambah-bank');
        const payStatus = document.getElementById('pj-pay-status');
        const payIcon = document.getElementById('pj-pay-icon');
        const payText = document.getElementById('pj-pay-text');
        const uangPasBtn = document.getElementById('pj-uang-pas');

        const banks = <?= $bankJson ?: '[]' ?>;
        let bankIndex = 0;

        function metodeTerpilih() {
            const checked = document.querySelector('input[name="metode_bayar"]:checked');
            return checked ? checked.value : '';
        }

        function productRows() {
            return Array.from(selectedTable.querySelectorAll('tr[id^="produk-row-"]'));
        }

        function totalHarga() {
            return unformatRupiah(totalHargaInput.value);
        }

        function updateItemCount() {
            const jumlah = productRows().length;
            itemCount.textContent = jumlah + ' item';
            emptyRow.hidden = jumlah > 0;
            saveButtons.forEach(function(btn) {
                if (btn) {
                    btn.disabled = jumlah === 0;
                }
            });
        }

        function setPayStatus(kelas, ikon, teks) {
            payStatus.classList.remove('is-ok', 'is-over', 'is-due', 'is-empty');
            payStatus.classList.add(kelas);
            payIcon.setAttribute('icon', ikon);
            payText.textContent = teks;
        }

        function hitungPembayaran() {
            const metode = metodeTerpilih();
            let cash = 0;
            let bank = 0;

            if (metode !== 'transfer') {
                cash = unformatRupiah(bayarInput.value);
            }

            if (metode !== 'tunai') {
                document.querySelectorAll('#bank-payment-container .bank-amount').forEach(function(input) {
                    bank += unformatRupiah(input.value);
                });
            }

            return {
                cash: cash,
                bank: bank,
                total: cash + bank,
                metode: metode
            };
        }

        function updatePayment() {
            const total = totalHarga();
            const pembayaran = hitungPembayaran();
            const dibayar = pembayaran.total;
            const sisa = Math.max(total - dibayar, 0);
            const kembalian = Math.max(dibayar - total, 0);

            hutangInput.value = formatToRupiah(sisa);
            kembalianInput.value = formatToRupiah(kembalian);
            dibayarEl.textContent = formatToRupiah(dibayar);
            sisaEl.textContent = formatToRupiah(sisa);
            sisaEl.classList.toggle('is-due', sisa > 0);
            sisaEl.classList.toggle('is-ok', sisa === 0 && total > 0);

            if (total <= 0) {
                setPayStatus('is-empty', 'solar:info-circle-outline',
                    'Tambahkan produk untuk melihat ringkasan pembayaran.');
                return;
            }

            if (pembayaran.metode === '') {
                setPayStatus('is-empty', 'solar:info-circle-outline',
                    'Pilih metode pembayaran untuk membuka kolom nominal.');
                return;
            }

            if (dibayar <= 0) {
                setPayStatus('is-empty', 'solar:info-circle-outline',
                    'Masukkan nominal tunai atau transfer yang diterima.');
                return;
            }

            if (sisa > 0) {
                setPayStatus('is-due', 'solar:danger-triangle-outline',
                    'Kurang ' + formatToRupiah(sisa) +
                    ' — transaksi akan tercatat belum lunas.');
                return;
            }

            if (kembalian > 0) {
                setPayStatus('is-over', 'solar:check-circle-outline',
                    'Kembalian ' + formatToRupiah(kembalian) + '.');
                return;
            }

            setPayStatus('is-ok', 'solar:check-circle-outline',
                'Pembayaran pas — transaksi lunas.');
        }

        function updateTotals() {
            let total = 0;
            let totalDiskon = 0;
            let totalPPN = 0;

            productRows().forEach(function(row) {
                const hargaInput = row.querySelector('.harga-input');
                const jumlahInput = row.querySelector('.jumlah-input');
                const diskonInput = row.querySelector('.diskon-input');
                const ppnCheckbox = row.querySelector('.ppn-checkbox');

                const harga = unformatRupiah(hargaInput ? hargaInput.value : 'Rp 0');
                const jumlah = parseInt(jumlahInput ? jumlahInput.value : '0', 10) || 0;
                const diskon = unformatRupiah(diskonInput ? diskonInput.value : 'Rp 0');
                const kenaPpn = !!(ppnCheckbox && ppnCheckbox.checked);

                const setelahDiskon = (harga * jumlah) - diskon;
                const ppnAmount = kenaPpn ? setelahDiskon * 0.11 : 0;

                totalDiskon += diskon;
                totalPPN += ppnAmount;
                total += setelahDiskon + ppnAmount;

                const subtotalEl = row.querySelector('.pj-subtotal');

                if (subtotalEl) {
                    subtotalEl.textContent = formatToRupiah(setelahDiskon + ppnAmount);
                }
            });

            totalDiskonInput.value = formatToRupiah(totalDiskon);
            totalPPNInput.value = formatToRupiah(totalPPN);
            totalHargaInput.value = formatToRupiah(total);

            updateItemCount();
            updatePayment();
        }

        function hapusProduk(id) {
            const row = document.getElementById('produk-row-' + id);

            if (row) {
                row.remove();
            }

            updateTotals();
        }

        window.hapusProduk = hapusProduk;

        function addProduct(cb) {
            const id = cb.getAttribute('data-id');

            if (document.getElementById('produk-row-' + id)) {
                return;
            }

            const kode = cb.getAttribute('data-kode') || '';
            const nama = cb.getAttribute('data-nama') || '';
            const harga = cb.getAttribute('data-harga') || '0';
            const kategori = cb.getAttribute('data-kategori') || '';
            const input = cb.getAttribute('data-input') || '';
            const kondisi = cb.getAttribute('data-kondisi_barang') || '0';
            const meta = [kode, kategori, input].filter(function(nilai) {
                return nilai && nilai !== 'Tidak Ada' && nilai !== 'Bundle';
            }).join(' · ');

            const row = document.createElement('tr');
            row.id = 'produk-row-' + id;
            row.innerHTML = `
                <td data-label="Produk">
                    <span class="pj-row__name">${escapeHtml(nama)}</span>
                    <span class="pj-row__meta">${escapeHtml(meta || kategori || 'Produk')}</span>
                    <input type="hidden" name="produk[${escapeHtml(id)}][id]" value="${escapeHtml(id)}">
                    <input type="hidden" name="produk[${escapeHtml(id)}][kode]" value="${escapeHtml(kode)}">
                    <input type="hidden" name="produk[${escapeHtml(id)}][nama]" value="${escapeHtml(nama)}">
                    <input type="hidden" name="produk[${escapeHtml(id)}][kategori]" value="${escapeHtml(kategori)}">
                    <input type="hidden" name="produk[${escapeHtml(id)}][kondisi_barang]" value="${escapeHtml(kondisi)}">
                </td>
                <td data-label="Harga">
                    <input type="text" name="produk[${escapeHtml(id)}][harga]"
                        class="pj-input harga-input pj-num" value="${formatToRupiah(harga)}"
                        inputmode="numeric" autocomplete="off" aria-label="Harga ${escapeHtml(nama)}">
                </td>
                <td data-label="Qty">
                    <input type="number" name="produk[${escapeHtml(id)}][jumlah]"
                        class="pj-input jumlah-input pj-num" value="1" min="1" step="1"
                        aria-label="Jumlah ${escapeHtml(nama)}">
                </td>
                <td data-label="Diskon">
                    <input type="text" name="produk[${escapeHtml(id)}][diskon]"
                        class="pj-input diskon-input pj-num" value="Rp 0"
                        inputmode="numeric" autocomplete="off" aria-label="Diskon ${escapeHtml(nama)}">
                </td>
                <td data-label="PPN">
                    <label class="pj-check">
                        <input type="checkbox" name="produk[${escapeHtml(id)}][ppn]" value="1"
                            class="ppn-checkbox">
                        <span>11%</span>
                    </label>
                </td>
                <td data-label="Subtotal">
                    <span class="pj-subtotal">${formatToRupiah(harga)}</span>
                </td>
                <td data-label="">
                    <button type="button" class="pj-row-del" onclick="hapusProduk('${escapeHtml(id)}')"
                        aria-label="Hapus ${escapeHtml(nama)}">
                        <iconify-icon icon="solar:trash-bin-minimalistic-outline" width="17" height="17">
                        </iconify-icon>
                    </button>
                </td>
            `;

            selectedTable.appendChild(row);

            row.querySelector('.harga-input').addEventListener('input', function() {
                this.value = formatToRupiah(this.value);
                updateTotals();
            });
            row.querySelector('.diskon-input').addEventListener('input', function() {
                this.value = formatToRupiah(this.value);
                updateTotals();
            });
            row.querySelector('.jumlah-input').addEventListener('input', updateTotals);
            row.querySelector('.ppn-checkbox').addEventListener('change', updateTotals);
        }

        /* ===== Metode & zona pembayaran ===== */
        function applyMethod() {
            const metode = metodeTerpilih();
            const pakaiTunai = metode === 'tunai' || metode === 'tunai_transfer';
            const pakaiBank = metode === 'transfer' || metode === 'tunai_transfer';

            zoneTunai.hidden = !pakaiTunai;
            zoneTransfer.hidden = !pakaiBank;

            if (!pakaiTunai) {
                bayarInput.value = 'Rp 0';
            }

            if (!pakaiBank) {
                bankContainer.querySelectorAll('.bank-row').forEach(function(row) {
                    row.remove();
                });
                bankIndex = 0;
            } else if (banks.length > 0 && bankContainer.querySelectorAll('.bank-row').length === 0) {
                addBankRow();
            }

            updatePayment();
        }

        document.querySelectorAll('input[name="metode_bayar"]').forEach(function(radio) {
            radio.addEventListener('change', applyMethod);
        });

        /* ===== Baris bank ===== */
        function addBankRow() {
            if (banks.length === 0) {
                return;
            }

            const index = bankIndex++;
            const row = document.createElement('div');
            row.className = 'pj-bank-row bank-row';
            row.innerHTML = `
                <select class="pj-select bank-select" name="bank[${index}][id]" aria-label="Rekening bank">
                    <option value="" disabled selected>Pilih rekening</option>
                    ${banks.map(function(bank) {
                        return '<option value="' + escapeHtml(bank.id) + '">' +
                            escapeHtml(bank.label) + '</option>';
                    }).join('')}
                </select>
                <input type="text" class="pj-input bank-amount pj-num" name="bank[${index}][jumlah]"
                    value="Rp 0" inputmode="numeric" autocomplete="off" aria-label="Nominal transfer">
                <button type="button" class="pj-row-del hapus-bank" aria-label="Hapus rekening transfer">
                    <iconify-icon icon="solar:close-circle-outline" width="18" height="18"></iconify-icon>
                </button>
            `;

            bankContainer.appendChild(row);

            row.querySelector('.bank-amount').addEventListener('input', function() {
                this.value = formatToRupiah(this.value);
                updatePayment();
            });

            row.querySelector('.bank-select').addEventListener('change', function() {
                this.classList.remove('is-invalid');

                if (!bankContainer.querySelector('.bank-select.is-invalid')) {
                    bankError.hidden = true;
                }
            });

            row.querySelector('.hapus-bank').addEventListener('click', function() {
                row.remove();
                updatePayment();
            });
        }

        tambahBankBtn.addEventListener('click', addBankRow);

        bayarInput.addEventListener('input', function() {
            this.value = formatToRupiah(this.value);
            updatePayment();
        });

        uangPasBtn.addEventListener('click', function() {
            const total = totalHarga();

            if (metodeTerpilih() === 'tunai_transfer') {
                // Uang pas harus menutup sisa setelah transfer, bukan total
                // penuh; kalau tidak, split payment otomatis kelebihan bayar.
                const bank = hitungPembayaran().bank;
                bayarInput.value = formatToRupiah(Math.max(total - bank, 0));
            } else {
                bayarInput.value = formatToRupiah(total);
            }

            updatePayment();
        });

        /* ===== Picker produk ===== */
        const pickerModalEl = document.getElementById('pilih-produk-modal');
        const pickerModal = new bootstrap.Modal(pickerModalEl);
        const detailModalEl = document.getElementById('detailBundleModal');
        const detailModal = new bootstrap.Modal(detailModalEl);
        const pickerSearch = document.getElementById('pj-picker-search');
        const pickCount = document.getElementById('pj-pick-count');
        const confirmBtn = document.getElementById('confirm-produk-btn');
        let detailIntent = false;

        function activePane() {
            return document.querySelector('#produkTabContent .tab-pane.active');
        }

        function refreshPicker() {
            const pane = activePane();
            const dipilih = pane ? pane.querySelectorAll('.produk-checkbox:checked:not(:disabled)').length : 0;
            pickCount.textContent = dipilih > 0 ? dipilih + ' dipilih' : 'Belum ada yang dipilih';
            confirmBtn.disabled = dipilih === 0;
        }

        function filterPicker(keyword) {
            const term = String(keyword || '').trim().toLowerCase();

            document.querySelectorAll('#produkTabContent .tab-pane').forEach(function(pane) {
                let terlihat = 0;

                pane.querySelectorAll('.service-sp-item').forEach(function(row) {
                    const haystack = (row.getAttribute('data-search') || row.textContent || '').toLowerCase();
                    const cocok = term === '' || haystack.indexOf(term) !== -1;
                    row.classList.toggle('is-hidden', !cocok);

                    if (cocok) {
                        terlihat++;
                    }
                });

                const state = pane.querySelector('.service-sp-state');

                if (state) {
                    state.hidden = terlihat > 0;
                }
            });
        }

        pickerSearch.addEventListener('input', function() {
            filterPicker(this.value);
        });

        pickerModalEl.addEventListener('shown.bs.modal', function() {
            pickerSearch.value = '';
            filterPicker('');
            refreshPicker();
            pickerSearch.focus();
        });

        pickerModalEl.addEventListener('hidden.bs.modal', function() {
            // "Lihat isi" menutup picker sebentar; jangan buang centang yang
            // sedang dipilih. Picker dibuka lagi setelah detail ditutup.
            if (detailIntent) {
                detailIntent = false;
                detailModal.show();
                return;
            }

            document.querySelectorAll('.produk-checkbox').forEach(function(cb) {
                cb.checked = false;
            });

            refreshPicker();
        });

        detailModalEl.addEventListener('hidden.bs.modal', function() {
            pickerModal.show();
        });

        pickerModalEl.addEventListener('change', function(event) {
            if (event.target.classList && event.target.classList.contains('produk-checkbox')) {
                refreshPicker();
            }
        });

        pickerModalEl.querySelectorAll('[data-bs-toggle="tab"]').forEach(function(tab) {
            tab.addEventListener('shown.bs.tab', function() {
                filterPicker(pickerSearch.value);
                refreshPicker();
            });
        });

        confirmBtn.addEventListener('click', function() {
            const pane = activePane();

            if (pane) {
                pane.querySelectorAll('.produk-checkbox:checked:not(:disabled)').forEach(addProduct);
            }

            pickerModal.hide();
            produkError.hidden = true;
            updateTotals();
        });

        produkError.hidden = true;

        /* ===== Kosongkan keranjang ===== */
        let clearArmed = false;
        let clearTimer = null;

        function resetClearButton() {
            clearArmed = false;
            clearBtn.innerHTML = clearLabel;

            if (clearTimer) {
                clearTimeout(clearTimer);
                clearTimer = null;
            }
        }

        clearBtn.addEventListener('click', function() {
            if (!clearArmed) {
                clearArmed = true;
                clearBtn.textContent = 'Yakin?';

                clearTimer = setTimeout(resetClearButton, 2500);
                return;
            }

            resetClearButton();
            productRows().forEach(function(row) {
                row.remove();
            });
            updateTotals();
        });

        /* ===== Detail bundle ===== */
        document.querySelectorAll('.btn-check-bundle').forEach(function(btn) {
            btn.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();

                const detail = JSON.parse(this.getAttribute('data-detail') || '[]');
                const tbody = document.getElementById('bundle-detail-body');
                tbody.innerHTML = '';

                detail.forEach(function(item) {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${escapeHtml(item.nama_barang || '-')}</td>
                        <td class="pj-num">${escapeHtml(item.jumlah || 0)}</td>
                        <td class="pj-num">${escapeHtml(item.stok_akhir === null || item.stok_akhir ===
                            undefined ? '-' : item.stok_akhir)}</td>
                    `;
                    tbody.appendChild(row);
                });

                document.getElementById('detailBundleLabel').textContent =
                    'Detail Bundle: ' + (this.getAttribute('data-nama') || '');
                detailIntent = true;
                pickerModal.hide();
            });
        });

        /* ===== Pelanggan ===== */
        const pelangganModalEl = document.getElementById('pelangganModal');
        const pelangganModal = new bootstrap.Modal(pelangganModalEl);
        const tambahModalEl = document.getElementById('modalTambahPelanggan');
        const tambahModal = new bootstrap.Modal(tambahModalEl);
        const pelangganSelect = document.getElementById('pelanggan-select');
        const pelangganError = document.getElementById('pj-pelanggan-error');
        const pelangganSuccess = document.getElementById('pj-pelanggan-success');
        const addError = document.getElementById('pj-add-error');

        if (window.jQuery && jQuery.fn.select2) {
            jQuery('#pelanggan-select').select2({
                dropdownParent: jQuery('#pelangganModal'),
                width: '100%'
            });

            jQuery('#modalTambahPelanggan').on('shown.bs.modal', function() {
                jQuery('#modalTambahPelanggan .js-domisili').each(function() {
                    if (!jQuery(this).data('select2')) {
                        jQuery(this).select2({
                            dropdownParent: jQuery('#modalTambahPelanggan'),
                            width: '100%'
                        });
                    }
                });
            });

            jQuery('#modalTambahPelanggan').on('hidden.bs.modal', function() {
                jQuery('#modalTambahPelanggan .js-domisili').each(function() {
                    if (jQuery(this).data('select2')) {
                        jQuery(this).select2('destroy');
                    }
                });
                jQuery('#modalTambahPelanggan select').val('').trigger('change');
            });
        }

        function tampilkanAlamat(option) {
            const bagian = [
                option.getAttribute('data-provinsi'),
                option.getAttribute('data-kabupaten'),
                option.getAttribute('data-kecamatan')
            ].filter(function(nilai) {
                return nilai && nilai.trim() !== '';
            });

            pelangganMeta.innerHTML =
                '<iconify-icon icon="solar:map-point-outline" width="14" height="14"></iconify-icon> ' +
                (bagian.length ? escapeHtml(bagian.join(' · ')) : 'Alamat pelanggan belum diisi.');
        }

        const selectedPelangganInput = document.getElementById('selectedidpelanggan');

        function pakaiPelanggan(option) {
            pelangganInput.value = option.text.trim();
            selectedPelangganInput.value = option.value;
            pelangganInput.classList.remove('is-invalid');
            customerError.hidden = true;
            tampilkanAlamat(option);
        }

        document.getElementById('btnPilihPelanggan').addEventListener('click', function() {
            const option = pelangganSelect.options[pelangganSelect.selectedIndex];

            if (!option || option.disabled || option.value === '') {
                pelangganError.hidden = false;
                return;
            }

            pelangganError.hidden = true;
            pelangganSuccess.hidden = true;
            pakaiPelanggan(option);
            pelangganModal.hide();
        });

        let kembaliKePelanggan = false;

        document.getElementById('btnTambahPelanggan').addEventListener('click', function() {
            pelangganError.hidden = true;
            pelangganSuccess.hidden = true;
            kembaliKePelanggan = true;
            pelangganModal.hide();
        });

        pelangganModalEl.addEventListener('hidden.bs.modal', function() {
            if (!kembaliKePelanggan) {
                return;
            }

            kembaliKePelanggan = false;
            tambahModal.show();
        });

        tambahModalEl.addEventListener('hidden.bs.modal', function() {
            pelangganModal.show();
        });

        /* ===== Cascade wilayah khusus modal tambah pelanggan ===== */
        function pushOption($select, value) {
            if (!value) {
                return;
            }

            const escaped = String(value).replace(/"/g, '&quot;');

            if (!$select.find('option[value="' + escaped + '"]').length) {
                $select.append(jQuery('<option>', {
                    value: value,
                    text: value
                }));
            }
        }

        if (window.jQuery) {
            const $provinsi = jQuery('#modal_provinsi');
            const $kabupaten = jQuery('#modal_kabupaten');
            const $kecamatan = jQuery('#modal_kecamatan');

            $provinsi.on('change', function() {
                const provinsi = jQuery(this).val();

                $kabupaten.html('<option value="">Memuat...</option>').trigger('change');
                $kecamatan.html('<option value="">— Pilih kecamatan —</option>').trigger('change');

                if (provinsi === '') {
                    return;
                }

                jQuery.getJSON("<?= base_url('region/kabupaten') ?>/" + encodeURIComponent(provinsi),
                    function(data) {
                        let opt = '<option value="">— Pilih kabupaten —</option>';
                        jQuery.each(data, function(i, item) {
                            opt += '<option value="' + escapeHtml(item.name) + '">' + escapeHtml(
                                item.name) + '</option>';
                        });
                        $kabupaten.html(opt).trigger('change');
                    });
            });

            $kabupaten.on('change', function() {
                const kabupaten = jQuery(this).val();

                $kecamatan.html('<option value="">Memuat...</option>').trigger('change');

                if (kabupaten === '') {
                    return;
                }

                jQuery.getJSON("<?= base_url('region/kecamatan') ?>/" + encodeURIComponent(kabupaten),
                    function(data) {
                        let opt = '<option value="">— Pilih kecamatan —</option>';
                        jQuery.each(data, function(i, item) {
                            opt += '<option value="' + escapeHtml(item.name) + '">' + escapeHtml(
                                item.name) + '</option>';
                        });
                        $kecamatan.html(opt).trigger('change');
                    });
            });
        }

        /* ===== Simpan pelanggan baru ===== */
        jQuery('#formTambahPelanggan').on('submit', function(event) {
            event.preventDefault();

            addError.hidden = true;

            jQuery.ajax({
                url: '<?= base_url('simpan/pelanggan') ?>',
                method: 'POST',
                data: jQuery(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (!response.success) {
                        addError.textContent = response.message || 'Pelanggan gagal disimpan.';
                        addError.hidden = false;
                        return;
                    }

                    jQuery('#formTambahPelanggan')[0].reset();

                    const option = new Option(
                        response.data.nama + ' : ' + response.data.no_hp,
                        response.data.id_pelanggan,
                        true,
                        true
                    );
                    option.setAttribute('data-provinsi', response.data.provinsi || '');
                    option.setAttribute('data-kabupaten', response.data.kabupaten || '');
                    option.setAttribute('data-kecamatan', response.data.kecamatan || '');

                    jQuery('#pelanggan-select').append(option).trigger('change');
                    pakaiPelanggan(option);
                    pelangganSuccess.textContent = 'Pelanggan berhasil ditambahkan.';
                    pelangganSuccess.hidden = false;

                    tambahModal.hide();
                },
                error: function() {
                    addError.textContent = 'Terjadi kesalahan saat menyimpan data.';
                    addError.hidden = false;
                }
            });
        });

        /* ===== Validasi submit ===== */
        salesBy.addEventListener('change', function() {
            this.classList.remove('is-invalid');
            staffError.hidden = true;
        });

        form.addEventListener('submit', function(event) {
            let fokusPertama = null;

            customerError.hidden = true;
            staffError.hidden = true;
            produkError.hidden = true;
            bankError.hidden = true;
            pelangganInput.classList.remove('is-invalid');
            salesBy.classList.remove('is-invalid');

            if (pelangganInput.value.trim() === '') {
                customerError.hidden = false;
                pelangganInput.classList.add('is-invalid');
                fokusPertama = fokusPertama || pelangganInput;
            } else if (!selectedPelangganInput.value) {
                customerError.hidden = false;
                pelangganInput.classList.add('is-invalid');
                fokusPertama = fokusPertama || pelangganInput;
            }

            if (productRows().length === 0) {
                produkError.hidden = false;
                fokusPertama = fokusPertama || document.getElementById('pj-pilih-produk');
            }

            if (!salesBy.value) {
                staffError.hidden = false;
                salesBy.classList.add('is-invalid');
                fokusPertama = fokusPertama || salesBy;
            }

            if (metodeTerpilih() !== 'tunai') {
                let bankTidakValid = false;

                bankContainer.querySelectorAll('.bank-row').forEach(function(row) {
                    const select = row.querySelector('.bank-select');
                    const amount = unformatRupiah(row.querySelector('.bank-amount').value);

                    select.classList.remove('is-invalid');

                    if (amount > 0 && !select.value) {
                        select.classList.add('is-invalid');
                        bankTidakValid = true;
                        fokusPertama = fokusPertama || select;
                    }
                });

                if (bankTidakValid) {
                    bankError.hidden = false;
                }
            }

            if (fokusPertama) {
                event.preventDefault();
                fokusPertama.focus();
            }
        });

        /* ===== Kondisi awal ===== */
        updateItemCount();
        applyMethod();
        updateTotals();
    });
</script>
