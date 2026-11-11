<?= $this->include('transaksi/_pembelian_theme') ?>

<div class="pembelian-page">
    <header class="pb-head">
        <ol class="pb-crumb">
            <li><a href="<?= base_url('/') ?>">Transaksi</a></li>
            <li aria-current="page">Pembelian</li>
        </ol>
        <div class="pb-head__row">
            <div>
                <h1>Pembelian</h1>
                <p class="pb-sub">Catat barang masuk, atur harga beli per unit, lalu tentukan pembayaran supplier.</p>
            </div>
            <?php $unitSesi = session('ID_UNIT'); ?>
            <?php if ($unitSesi !== null): ?>
            <span class="pb-head__unit">Unit <?= esc((string) $unitSesi) ?></span>
            <?php endif; ?>
        </div>
    </header>

    <form action="<?= base_url('insert_pembelian') ?>" method="post" id="form_pembelian" class="pb-sheet"
        enctype="multipart/form-data">
        <section class="pb-section" aria-labelledby="pb-pihak-title">
            <div class="pb-section__head">
                <div>
                    <h2 id="pb-pihak-title">Pihak &amp; Dokumen</h2>
                    <p class="pb-section__hint">Tentukan asal barang dan bukti nota sebelum memilih produk.</p>
                </div>
            </div>

            <div class="pb-grid">
                <div class="pb-field">
                    <label for="tipe_pihak">Sumber</label>
                    <select id="tipe_pihak" class="form-select" required>
                        <option value="">Pilih Tipe</option>
                        <option value="suplier">Suplier</option>
                        <option value="pelanggan">Pelanggan</option>
                    </select>
                </div>

                <div class="pb-field">
                    <label for="suplier" id="suplier-label">Nama Suplier</label>
                    <select disabled class="form-select" id="suplier" name="suplier" required
                        onchange="tampilkanIdSuplier()">
                        <option value="" disabled selected>Pilih Suplier</option>
                        <?php foreach ($suplier as $b): ?>
                        <option value="<?= $b->id_suplier; ?>"><?= $b->nama_suplier; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input name="id_suplier_text" type="hidden" id="id_suplier_text">

                    <div id="pelanggan-container" style="display: none;" class="mt-2">
                        <label for="pelanggan">Pelanggan</label>
                        <input type="text" class="form-control" id="pelanggan" name="pelanggan" readonly>
                    </div>
                    <div id="pelanggan-section" style="display: none;" class="mt-2">
                        <button type="button" class="pb-btn pb-btn--outline" data-bs-toggle="modal"
                            data-bs-target="#pelangganModal">
                            <iconify-icon icon="solar:user-rounded-outline" width="18" height="18"></iconify-icon>
                            Pilih Pelanggan
                        </button>
                    </div>
                </div>

                <div class="pb-field pb-field--span2">
                    <label for="notaFileInput">Upload Gambar Nota</label>
                    <div class="pb-file">
                        <input type="file" name="nota_file" id="notaFileInput" class="pb-file__input"
                            accept="image/*" aria-label="Upload gambar nota, maksimal 10 MB">
                        <span class="pb-file__icon">
                            <iconify-icon icon="solar:cloud-upload-outline" width="20" height="20"></iconify-icon>
                        </span>
                        <div>
                            <p class="pb-file__title">Letakkan file di sini atau klik untuk memilih</p>
                            <p class="pb-file__hint">JPG atau PNG, maksimal 10 MB.</p>
                        </div>
                        <img id="previewNota" class="pb-file__preview" src="#" alt="Preview gambar nota">
                    </div>
                </div>

                <?php $idUnit = session('ID_UNIT'); $tanggalHariIni = date('Y-m-d'); ?>
                <div class="pb-field">
                    <label for="tanggal_masuk">Tanggal Masuk</label>
                    <?php if ($idUnit == 1): ?>
                    <input type="date" class="form-control" id="tanggal_masuk" name="tanggal_masuk"
                        value="<?= $tanggalHariIni ?>" required>
                    <?php else: ?>
                    <input type="date" class="form-control" id="tanggal_masuk" name="tanggal_masuk"
                        value="<?= $tanggalHariIni ?>" min="<?= $tanggalHariIni ?>" max="<?= $tanggalHariIni ?>"
                        readonly required>
                    <?php endif; ?>
                </div>

                <div class="pb-field">
                    <label for="jatuh_tempo">Jatuh Tempo</label>
                    <input type="date" class="form-control" id="jatuh_tempo" name="jatuh_tempo"
                        value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="pb-field pb-field--span2">
                    <label for="frontliner">Frontliner</label>
                    <input type="text" class="form-control" id="frontliner"
                        value="<?= esc($akun->NAMA_AKUN ?? session('NAMA')) ?>" readonly>
                </div>
            </div>
        </section>

        <section class="pb-section" aria-labelledby="pb-produk-title">
            <div class="pb-section__head">
                <div>
                    <h2 id="pb-produk-title">Produk</h2>
                    <p class="pb-section__hint">HPP mengikuti harga beli terakhir untuk unit ini, bukan rata-rata.<?= !empty($can_manage_harga_jual) ? ' Harga jual yang diubah berlaku untuk unit ini.' : '' ?></p>
                </div>
                <div class="pb-toolbar">
                    <button type="button" class="pb-btn pb-btn--primary" data-bs-toggle="modal"
                        data-bs-target="#pilih-produk-modal">
                        <iconify-icon icon="solar:password-minimalistic-input-outline" width="18" height="18">
                        </iconify-icon>
                        Pilih Produk
                    </button>
                    <button type="button" class="pb-btn pb-btn--outline" data-bs-toggle="modal"
                        data-bs-target="#input-produk-modal">
                        <iconify-icon icon="solar:add-circle-outline" width="18" height="18"></iconify-icon>
                        Tambah Produk
                    </button>
                </div>
            </div>

            <div class="pb-table-wrap table-responsive" id="produk-table-wrap">
                <table class="pb-table">
                    <thead>
                        <tr>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th>IMEI</th>
                            <th>Hrg. Jual</th>
                            <th>Hrg. Beli</th>
                            <th>Nominal</th>
                            <th>Biaya Tambahan</th>
                            <th>Keterangan</th>
                            <th>Diskon (Rp)</th>
                            <th>
                                <input type="checkbox" id="select-all-ppn" class="form-check-input"> PPN 11%
                            </th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="selected-produk-table">
                        <tr class="pb-empty" id="produk-empty">
                            <td colspan="12">
                                <span class="pb-empty__title">Belum ada produk dipilih</span>
                                Gunakan Pilih Produk untuk menambahkan barang dari master.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="pb-section" aria-labelledby="pb-bayar-title">
            <div class="pb-section__head">
                <div>
                    <h2 id="pb-bayar-title">Pembayaran</h2>
                    <p class="pb-section__hint">Isi tunai dan/atau bank. Selisih terhadap total otomatis menjadi hutang.</p>
                </div>
            </div>

            <div class="pb-pay">
                <div class="pb-field">
                    <label for="metode_bayar">Metode Pembayaran</label>
                    <select id="metode_bayar" name="metode_bayar" class="form-select">
                        <option disabled selected>Pilih Metode</option>
                        <option value="tunai">Tunai</option>
                        <option value="transfer">Transfer</option>
                        <option value="tunai_transfer">Tunai + Transfer</option>
                    </select>
                </div>

                <div class="pb-field tunai-section">
                    <label for="bayar">Bayar Tunai</label>
                    <input type="text" class="form-control pb-num" id="bayar" name="bayar" value="Rp 0"
                        inputmode="numeric">
                </div>

                <div class="pb-field pb-field--span2 transfer-section">
                    <label for="tambah-bank">Pembayaran via Bank</label>
                    <div id="bank-payment-container"></div>
                    <button type="button" class="pb-btn pb-btn--outline" id="tambah-bank">
                        <iconify-icon icon="solar:add-circle-outline" width="18" height="18"></iconify-icon>
                        Tambah Bank
                    </button>
                </div>

                <p class="pb-pay__note">Hutang muncul di menu Hutang Piutang sebagai hutang supplier dan dapat
                    dicicil lewat Daftar Tagihan sampai jatuh tempo.</p>
            </div>
        </section>

        <div class="pb-dock" role="group" aria-label="Ringkasan pembayaran">
            <div class="pb-dock__figures">
                <label class="pb-figure" for="total-diskon">
                    <span>Diskon</span>
                    <input type="text" id="total-diskon" name="total-diskon" class="pb-num" value="Rp 0" readonly>
                </label>
                <label class="pb-figure" for="total-ppn">
                    <span>PPN</span>
                    <input type="text" id="total-ppn" name="total-ppn" class="pb-num" value="Rp 0" readonly>
                </label>
                <label class="pb-figure pb-figure--total" for="total-harga">
                    <span>Total</span>
                    <input type="text" id="total-harga" name="total-harga" class="pb-num" value="Rp 0" readonly>
                </label>
                <div class="pb-figure">
                    <span>Dibayar</span>
                    <span class="pb-figure__value pb-num" id="pb-dibayar">Rp 0</span>
                </div>
                <label class="pb-figure pb-figure--debt" for="hutang">
                    <span>Hutang</span>
                    <input type="text" id="hutang" name="hutang" class="pb-num" value="Rp 0" readonly>
                </label>
                <label class="pb-figure" for="kembalian">
                    <span>Kembalian</span>
                    <input type="text" id="kembalian" name="kembalian" class="pb-num" value="Rp 0" readonly>
                </label>
            </div>
            <button type="submit" class="pb-btn pb-btn--primary pb-dock__save">
                <iconify-icon icon="solar:check-circle-outline" width="18" height="18"></iconify-icon>
                Simpan Pembelian
            </button>
        </div>
    </form>

    <!-- Modal Pilih Produk -->
    <div class="modal fade pb-modal" id="pilih-produk-modal" tabindex="-1" aria-labelledby="produkModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content pb-picker">
                <div class="modal-header">
                    <div class="pb-picker__head">
                        <span class="pb-picker__icon" aria-hidden="true">
                            <iconify-icon icon="solar:box-bold" width="20" height="20"></iconify-icon>
                        </span>
                        <div>
                            <h5 class="modal-title" id="produkModalLabel">Pilih Produk</h5>
                            <p class="pb-picker__hint mb-0">Cari nama atau kode, lalu centang
                                yang masuk nota.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="pb-picker__toolbar">
                        <label class="pb-search" for="pb-produk-search">
                            <iconify-icon icon="solar:magnifer-linear" width="18" height="18"></iconify-icon>
                            <input type="search" id="pb-produk-search"
                                placeholder="Cari nama, kode, IMEI, atau kategori..." autocomplete="off">
                        </label>
                        <?php if (session('NAMA_UNIT')): ?>
                        <span class="pb-picker__unit d-none d-sm-inline-flex">
                            <iconify-icon icon="solar:shop-outline" width="15" height="15"></iconify-icon>
                            <?= esc(session('NAMA_UNIT')) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="pb-produk-list" id="pb-produk-list" aria-live="polite">
                        <?php foreach ($produk as $p): ?>
                        <?php
                        $hpp = (int) ($p->hpp_unit ?? $p->harga_beli);
                        $meta = trim(implode(' ', [
                            $p->nama_barang,
                            $p->kode_barang,
                            $p->nama_kategori ?? '',
                            $p->imei ?? '',
                            $p->internal ?? '',
                            $p->warna ?? '',
                            $p->input ?? '',
                        ]));
                        ?>
                        <label class="pb-produk-card"
                            data-search="<?= esc(strtolower($meta), 'attr') ?>">
                            <input type="checkbox" class="pb-produk-card__input produk-checkbox"
                                aria-label="Pilih <?= esc($p->nama_barang) ?>"
                                data-id="<?= $p->idbarang ?>" data-kode="<?= esc($p->kode_barang) ?>"
                                data-nama="<?= esc($p->nama_barang) ?>" data-harga="<?= $p->harga_jual ?>"
                                data-harga_beli="<?= $hpp ?>"
                                data-kategori="<?= esc($p->nama_kategori) ?>" data-input="<?= esc($p->input) ?>"
                                data-imei="<?= esc($p->imei) ?>">
                            <span class="pb-produk-card__box">
                                <span class="pb-produk-card__tick" aria-hidden="true">
                                    <iconify-icon icon="solar:check-read-bold" width="13" height="13"></iconify-icon>
                                </span>
                                <span class="pb-produk-card__main">
                                    <span class="pb-produk-card__name"><?= esc($p->nama_barang) ?></span>
                                    <span class="pb-produk-card__meta">
                                        <span class="pb-produk-card__code"><?= esc($p->kode_barang) ?></span>
                                        <?php if (!empty($p->nama_kategori)): ?>
                                        <span class="pb-produk-card__meta-item">
                                            <iconify-icon icon="solar:tag-outline" width="13" height="13"></iconify-icon>
                                            <?= esc($p->nama_kategori) ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (!empty($p->imei)): ?>
                                        <span class="pb-produk-card__meta-item">
                                            <iconify-icon icon="solar:smartphone-outline" width="13" height="13"></iconify-icon>
                                            <?= esc($p->imei) ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (!empty($p->internal)): ?>
                                        <span class="pb-produk-card__meta-item">
                                            <iconify-icon icon="solar:card-2-outline" width="13" height="13"></iconify-icon>
                                            <?= esc($p->internal) ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (!empty($p->warna)): ?>
                                        <span class="pb-produk-card__meta-item">
                                            <iconify-icon icon="solar:palette-outline" width="13" height="13"></iconify-icon>
                                            <?= esc($p->warna) ?>
                                        </span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                                <span class="pb-produk-card__side">
                                    <span class="pb-produk-card__price">Rp
                                        <?= number_format($hpp, 0, ',', '.') ?></span>
                                    <span class="pb-produk-card__sub">HPP · jual Rp
                                        <?= number_format($p->harga_jual, 0, ',', '.') ?></span>
                                </span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                        <div class="pb-picker__state" id="pb-produk-empty" hidden>
                            <iconify-icon icon="solar:magnifer-zoom-out-outline" width="26" height="26"></iconify-icon>
                            <p>Tidak ada produk yang cocok. Coba kata kunci lain.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer pb-picker__foot">
                    <div class="pb-picker__status">
                        <span id="pb-produk-summary">Belum ada yang dipilih</span>
                        <span id="pb-produk-total-hpp">Total HPP Rp 0</span>
                    </div>
                    <div class="pb-picker__actions">
                        <button type="button" class="pb-btn pb-btn--outline" data-bs-dismiss="modal">Batal</button>
                        <button type="button" id="confirm-produk-btn" class="pb-btn pb-btn--primary" disabled>
                            <iconify-icon icon="solar:add-circle-outline" width="18" height="18"></iconify-icon>
                            Tambahkan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Input Produk -->
    <div class="modal fade pb-modal" id="input-produk-modal" tabindex="-1" aria-labelledby="inputProdukModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="inputProdukModalLabel">Tambah Produk Baru</h5>
                        <p class="pb-section__hint mb-0">Produk baru langsung masuk ke master barang.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form action="<?= base_url('insert_produk') ?>" enctype="multipart/form-data" method="post">
                    <div class="modal-body">
                        <div class="pb-grid pb-grid--2">
                            <div class="pb-field pb-field--span2">
                                <label for="nama_barang">Nama Barang</label>
                                <input type="text" class="form-control" id="nama_barang" name="nama_barang">
                            </div>

                            <div class="pb-field">
                                <label for="id_kategori">Kategori</label>
                                <select class="form-select" id="id_kategori" name="kategori" required>
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($kategori as $k): ?>
                                    <option data-idkategori="<?= $k->id ?>" value="<?= $k->nama_kategori; ?>">
                                        <?= $k->nama_kategori; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="pb-field">
                                <label for="kondisi">Kondisi</label>
                                <select id="kondisi" name="kondisi" class="form-select" required>
                                    <option value="0">Baru</option>
                                    <option value="1">Second</option>
                                </select>
                            </div>

                            <div class="pb-field">
                                <label for="harga">Harga PL</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" class="form-control currency" id="harga" name="harga" required>
                                </div>
                            </div>

                            <div class="pb-field">
                                <label for="harga_beli">HPP</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" class="form-control currency" id="harga_beli" name="harga_beli"
                                        required>
                                </div>
                            </div>

                            <div class="pb-field">
                                <label for="imei">IMEI</label>
                                <input type="text" class="form-control" id="imei" name="imei">
                            </div>

                            <div class="pb-field">
                                <label for="type">Tipe</label>
                                <input type="text" class="form-control" id="type" name="type">
                            </div>

                            <div class="pb-field">
                                <label for="size">Spesifikasi</label>
                                <input type="text" class="form-control" id="size" name="size">
                            </div>

                            <div class="pb-field">
                                <label for="warna">Warna</label>
                                <input type="text" class="form-control" id="warna" name="warna" required>
                            </div>

                            <div class="pb-field">
                                <label for="stok_minim">Stok Minimum</label>
                                <input type="text" class="form-control" id="stok_minim" value="0" name="stok_minimum"
                                    required>
                            </div>

                            <div class="pb-field">
                                <label for="ppn_status">Status PPN</label>
                                <select class="form-select" id="ppn_status" name="status_ppn">
                                    <option value="">Pilih Status PPN</option>
                                    <option value="1">PPN</option>
                                    <option value="0">Non PPN</option>
                                </select>
                            </div>

                            <div class="pb-field">
                                <label for="input_by">Input Oleh</label>
                                <input type="text" class="form-control"
                                    value="<?= session()->get('NAMA') ?? '' ?>" id="input_by" name="input_by" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="pb-btn pb-btn--outline" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" class="pb-btn pb-btn--primary">Simpan Produk</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Pilih Pelanggan -->
    <div class="modal fade pb-modal" id="pelangganModal" tabindex="-1" aria-labelledby="pelangganModalLabel">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="pelangganModalLabel">Cari Data Pelanggan</h5>
                        <p class="pb-section__hint mb-0">Pilih pelanggan yang sudah terdaftar atau tambahkan baru.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="pb-field">
                        <label for="pelanggan-select">Pelanggan</label>
                        <select id="pelanggan-select" name="selectedidpelanggan" class="select2 form-select"
                            style="width: 100%;">
                            <option disabled selected>Pilih pelanggan</option>
                            <?php foreach ($pelanggan as $p): ?>
                            <option value="<?= htmlspecialchars($p->id_pelanggan) ?>">
                                <?= htmlspecialchars($p->nama) ?> : <?= htmlspecialchars($p->no_hp) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button id="btnTambahPelanggan" type="button" class="pb-btn pb-btn--outline">Tambah
                        Pelanggan</button>
                    <button id="btnPilihPelanggan" type="button" class="pb-btn pb-btn--primary">Pilih</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Pelanggan -->
    <div class="modal fade pb-modal" id="modalTambahPelanggan" tabindex="-1"
        aria-labelledby="modalTambahPelangganLabel">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <form id="formTambahPelanggan">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTambahPelangganLabel">Tambah Pelanggan Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="pb-grid pb-grid--2">
                            <div class="pb-field">
                                <label for="nik">NIK</label>
                                <input type="text" id="nik" name="nik" class="form-control" required>
                            </div>
                            <div class="pb-field">
                                <label for="nama">Nama</label>
                                <input type="text" id="nama" name="nama" class="form-control" required>
                            </div>
                            <div class="pb-field pb-field--span2">
                                <label for="no_hp">No HP</label>
                                <input type="text" id="no_hp" name="no_hp" class="form-control" required>
                            </div>
                            <div class="pb-field pb-field--span2">
                                <label for="alamat">Alamat</label>
                                <textarea id="alamat" name="alamat" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="pb-btn pb-btn--outline" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="pb-btn pb-btn--primary">Simpan Pelanggan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function formatToRupiah(angka) {
        const cleaned = angka.replace(/[^\d]/g, '');
        const number = parseInt(cleaned) || 0;
        return 'Rp ' + number.toLocaleString('id-ID');
    }

    function unformatRupiah(rupiah) {
        return parseInt(rupiah.replace(/[^\d]/g, '')) || 0;
    }

    function updateEmptyState() {
        const empty = document.getElementById('produk-empty');
        if (!empty) return;
        empty.hidden = document.querySelectorAll('#selected-produk-table tr:not(#produk-empty)').length > 0;
    }

    const pickerSelected = new Map();
    const canEditHargaJual = <?= !empty($can_manage_harga_jual) ? 'true' : 'false' ?>;

    function pickerData(cb) {
        return {
            id: cb.getAttribute('data-id'),
            kode: cb.getAttribute('data-kode'),
            nama: cb.getAttribute('data-nama'),
            harga: parseInt(cb.getAttribute('data-harga')) || 0,
            harga_beli: parseInt(cb.getAttribute('data-harga_beli')) || 0,
            kategori: cb.getAttribute('data-kategori'),
            imei: cb.getAttribute('data-imei'),
        };
    }

    function rememberPick(cb) {
        const data = pickerData(cb);
        if (cb.checked) {
            pickerSelected.set(data.id, data);
        } else {
            pickerSelected.delete(data.id);
        }
    }

    function updatePickerCount() {
        const picks = Array.from(pickerSelected.values());
        const count = picks.length;
        const total = picks.reduce((sum, p) => sum + (parseInt(p.harga_beli, 10) || 0), 0);

        const summaryEl = document.getElementById('pb-produk-summary');
        if (summaryEl) {
            summaryEl.textContent = count === 0 ? 'Belum ada yang dipilih' : count + ' produk dipilih';
        }

        const totalEl = document.getElementById('pb-produk-total-hpp');
        if (totalEl) totalEl.textContent = 'Total HPP Rp ' + total.toLocaleString('id-ID');

        const confirmBtn = document.getElementById('confirm-produk-btn');
        if (confirmBtn) confirmBtn.disabled = count === 0;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const selectedTable = document.getElementById('selected-produk-table');
        const confirmBtn = document.getElementById('confirm-produk-btn');
        const modalEl = document.getElementById('pilih-produk-modal');
        const modalInstance = new bootstrap.Modal(modalEl);
        const bayarInput = document.getElementById('bayar');
        const pickerSearch = document.getElementById('pb-produk-search');
        const pickerList = document.getElementById('pb-produk-list');
        const pickerEmpty = document.getElementById('pb-produk-empty');

        function filterPicker() {
            const query = pickerSearch.value.trim().toLowerCase();
            let visible = 0;

            pickerList.querySelectorAll('.pb-produk-card').forEach(card => {
                const match = !query || (card.getAttribute('data-search') || '').includes(query);
                card.hidden = !match;
                if (match) visible++;
            });

            pickerEmpty.hidden = visible > 0;
        }

        pickerSearch.addEventListener('input', filterPicker);
        modalEl.addEventListener('shown.bs.modal', function() {
            pickerSearch.value = '';
            filterPicker();
            pickerSearch.focus();
        });

        modalEl.addEventListener('change', function(e) {
            if (e.target.classList.contains('produk-checkbox')) {
                rememberPick(e.target);
                updatePickerCount();
            }
        });

        confirmBtn.addEventListener('click', function() {
            pickerSelected.forEach(pick => {
                const id = pick.id;
                const kode = pick.kode;
                const nama = pick.nama;
                const harga = pick.harga;
                const harga_beli = pick.harga_beli;
                const kategori = pick.kategori;

                const imeiAttr = pick.imei;
                const hasImei = imeiAttr !== null && imeiAttr.trim() !== '';
                const imei = hasImei ? imeiAttr : 'tidak ada imei';

                if (!document.getElementById('produk-row-' + id)) {
                    const row = document.createElement('tr');
                    row.id = 'produk-row-' + id;
                    row.innerHTML = `
                        <td>
                            ${kode}
                            <input type="hidden" name="produk[${id}][id]" value="${id}">
                            <input type="hidden" name="produk[${id}][kode]" value="${kode}">
                        </td>
                        <td>
                            ${nama}
                            <input type="hidden" name="produk[${id}][nama]" value="${nama}">
                        </td>
                        <td>
                            ${kategori}
                            <input type="hidden" name="produk[${id}][kategori]" value="${kategori}">
                        </td>
                        <td>
                            ${imei}
                            <input type="hidden" name="produk[${id}][imei]" value="${imei}">
                        </td>
                        <td>
                            ${canEditHargaJual
                                ? `<input class="form-control harga-jual-input" data-id="${id}" value="Rp ${parseInt(harga).toLocaleString('id-ID')}">
                                   <input type="hidden" name="produk[${id}][harga]" id="harga-jual-hidden-${id}" value="${harga}">`
                                : `Rp ${parseInt(harga).toLocaleString('id-ID')}
                                   <input type="hidden" name="produk[${id}][harga]" value="${harga}">`}
                        </td>
                        <td>
                            <input class="form-control harga-beli-input" data-id="${id}" value="Rp ${parseInt(harga_beli).toLocaleString('id-ID')}">
                            <input type="hidden" name="produk[${id}][harga_beli]" id="harga-beli-hidden-${id}" value="${parseInt(harga_beli)}">
                        </td>
                        <td>
                            <input type="number" name="produk[${id}][jumlah]" class="form-control jumlah-input" data-id="${id}" value="1" min="1">
                        </td>
                        <td>
                            <input class="form-control biaya-tambahan-input" data-id="${id}" value="Rp 0">
                            <input type="hidden" name="produk[${id}][biaya_tambahan]" id="biaya-tambahan-hidden-${id}" value="0">
                        </td>
                        <td>
                            <input type="text" name="produk[${id}][keterangan]" class="form-control keterangan-input" data-id="${id}" placeholder="">
                        </td>
                        <td>
                            <input class="form-control diskon-input" data-id="${id}" value="Rp 0">
                            <input type="hidden" name="produk[${id}][diskon]" id="diskon-hidden-${id}" value="0">
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="produk[${id}][ppn]" class="form-check-input ppn-checkbox" data-id="${id}">
                        </td>
                        <td>
                            <button type="button" class="pb-btn pb-btn--danger pb-btn--icon" onclick="hapusProduk('${id}')" aria-label="Hapus ${nama}">
                                <iconify-icon icon="solar:trash-bin-minimalistic-outline" width="18" height="18"></iconify-icon>
                            </button>
                        </td>
                    `;

                    selectedTable.appendChild(row);

                    const jumlahInput = row.querySelector('.jumlah-input');
                    jumlahInput.value = 1;
                    if (hasImei) {
                        jumlahInput.setAttribute('readonly', true);
                    }

                    row.querySelector('.jumlah-input').addEventListener('input', updateTotals);
                    row.querySelector('.ppn-checkbox').addEventListener('change', updateTotals);

                    const hargaBeliInput = row.querySelector('.harga-beli-input');
                    hargaBeliInput.addEventListener('input', function() {
                        const id = this.getAttribute('data-id');
                        const numeric = this.value.replace(/[^\d]/g, '');
                        this.value = formatToRupiah(numeric);
                        document.getElementById(`harga-beli-hidden-${id}`).value = numeric;
                        updateTotals();
                    });

                    const hargaJualInput = row.querySelector('.harga-jual-input');
                    if (hargaJualInput) {
                        hargaJualInput.addEventListener('input', function() {
                            const id = this.getAttribute('data-id');
                            const numeric = this.value.replace(/[^\d]/g, '');
                            this.value = formatToRupiah(numeric);
                            document.getElementById(`harga-jual-hidden-${id}`).value = numeric;
                        });
                    }

                    const biayaTambahanInput = row.querySelector('.biaya-tambahan-input');
                    biayaTambahanInput.addEventListener('input', function() {
                        const id = this.getAttribute('data-id');
                        const numeric = this.value.replace(/[^\d]/g, '');
                        this.value = formatToRupiah(numeric);
                        document.getElementById(`biaya-tambahan-hidden-${id}`).value = numeric;
                        updateTotals();
                    });

                    const diskonInput = row.querySelector('.diskon-input');
                    diskonInput.addEventListener('input', function() {
                        const id = this.getAttribute('data-id');
                        const numeric = this.value.replace(/[^\d]/g, '');
                        this.value = formatToRupiah(numeric);
                        document.getElementById(`diskon-hidden-${id}`).value = numeric;
                        updateTotals();
                    });
                }
            });

            updateEmptyState();
            modalInstance.hide();
            setTimeout(() => {
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
                document.body.style.removeProperty('overflow');
                document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
            }, 500);

            pickerSelected.clear();
            document.querySelectorAll('.produk-checkbox').forEach(cb => cb.checked = false);
            updatePickerCount();

            updateTotals();
        });

        bayarInput.addEventListener('input', function() {
            const numeric = this.value.replace(/[^\d]/g, '');
            this.value = formatToRupiah(numeric);
            updateHutang();
        });

        const totalDiskonInput = document.getElementById('total-diskon');
        totalDiskonInput.addEventListener('input', function() {
            const numeric = this.value.replace(/[^\d]/g, '');
            this.value = formatToRupiah(numeric);
            updateTotals();
        });
    });

    function hapusProduk(id) {
        const row = document.getElementById('produk-row-' + id);
        if (row) row.remove();
        updateEmptyState();
        updateTotals();
    }

    function updateTotals() {
        let total = 0;
        let totalDiskon = 0;
        let totalPPN = 0;

        document.querySelectorAll('#selected-produk-table tr:not(#produk-empty)').forEach(row => {
            const hargaBeliHiddenInput = row.querySelector('input[id^="harga-beli-hidden-"]');
            const jumlahInput = row.querySelector('.jumlah-input');
            const diskonHiddenInput = row.querySelector('input[id^="diskon-hidden-"]');
            const biayaTambahanHiddenInput = row.querySelector('input[id^="biaya-tambahan-hidden-"]');
            const ppnCheckbox = row.querySelector('.ppn-checkbox');

            if (hargaBeliHiddenInput && jumlahInput && diskonHiddenInput) {
                const hargaBeli = parseInt(hargaBeliHiddenInput.value) || 0;
                const jumlah = parseInt(jumlahInput.value) || 0;
                const diskon = parseInt(diskonHiddenInput.value) || 0;
                const biayaTambahan = parseInt(biayaTambahanHiddenInput?.value) || 0;
                const isPpn = ppnCheckbox?.checked;

                let subtotal = hargaBeli * jumlah;
                let setelahDiskon = subtotal - diskon + biayaTambahan;
                let ppnAmount = isPpn ? setelahDiskon * 0.11 : 0;

                totalDiskon += diskon;
                totalPPN += ppnAmount;
                total += setelahDiskon + ppnAmount;
            }
        });

        const totalDiskonInput = document.getElementById('total-diskon');
        totalDiskonInput.min = totalDiskon;

        let manualDiskon = unformatRupiah(totalDiskonInput.value);

        if (manualDiskon !== totalDiskon) {
            manualDiskon = totalDiskon;
        }

        totalDiskonInput.value = formatToRupiah(totalDiskon.toString());

        document.getElementById('total-ppn').value = 'Rp ' + totalPPN.toLocaleString('id-ID');
        document.getElementById('total-harga').value = 'Rp ' + total.toLocaleString('id-ID');

        updateHutang();
    }

    function updateHutang() {
        const totalInput = document.getElementById('total-harga');
        const bayarEl = document.getElementById('bayar');
        const hutangInput = document.getElementById('hutang');
        const kembalianInput = document.getElementById('kembalian');

        if (!totalInput || !bayarEl || !hutangInput || !kembalianInput) return;

        const total = unformatRupiah(totalInput.value || 'Rp 0');
        const bayar = unformatRupiah(bayarEl.value || 'Rp 0');

        let bankTotal = 0;
        document.querySelectorAll('.bank-amount').forEach(input => {
            bankTotal += unformatRupiah(input.value || 'Rp 0');
        });

        const selisih = (bayar + bankTotal) - total;
        const hutang = Math.max(total - (bayar + bankTotal), 0);
        const kembalian = selisih > 0 ? selisih : 0;

        hutangInput.value = 'Rp ' + hutang.toLocaleString('id-ID');
        kembalianInput.value = 'Rp ' + kembalian.toLocaleString('id-ID');

        const dibayarEl = document.getElementById('pb-dibayar');
        if (dibayarEl) {
            dibayarEl.textContent = 'Rp ' + (bayar + bankTotal).toLocaleString('id-ID');
        }
    }
    </script>

    <script>
    function tampilkanIdSuplier() {
        const select = document.getElementById('suplier');
        const idSuplierInput = document.getElementById('id_suplier_text');
        idSuplierInput.value = select.value;
    }
    </script>

    <script>
    document.getElementById('notaFileInput').addEventListener('change', function(event) {
        const file = event.target.files[0];
        const preview = document.getElementById('previewNota');

        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            preview.src = '#';
            preview.style.display = 'none';
        }
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const pelangganModalEl = document.getElementById('pelangganModal');
        const pelangganModal = bootstrap.Modal.getOrCreateInstance(pelangganModalEl);
        const modalTambah = new bootstrap.Modal(document.getElementById('modalTambahPelanggan'));
        const tipePihak = document.getElementById('tipe_pihak');

        let pelangganSelect2Inited = false;

        $('#pelangganModal').on('shown.bs.modal', function() {
            if (!pelangganSelect2Inited) {
                $('#pelanggan-select').select2({
                    dropdownParent: $('#pelangganModal .modal-content')
                });
                pelangganSelect2Inited = true;
            }

            document.body.classList.add('modal-open');
            document.documentElement.classList.add('modal-open');
        });

        $('#pelangganModal').on('hidden.bs.modal', function() {
            $('.modal-backdrop').remove();
            document.body.classList.remove('modal-open');
            document.documentElement.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.documentElement.style.removeProperty('overflow');
        });

        function checkForHandphone() {
            const rows = document.querySelectorAll('#selected-produk-table tr:not(#produk-empty)');
            let show = false;

            rows.forEach(row => {
                if (tipePihak.value === 'pelanggan') {
                    show = true;
                }
            });

            document.getElementById('pelanggan-button')?.remove();

            if (show) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.id = 'pelanggan-button';
                btn.style = 'display:none;margin:0;padding:0;width:0;height:0;';
                btn.onclick = () => pelangganModal.show();

                const container = document.getElementById('produk-table-wrap');
                if (container) container.appendChild(btn);
            }
        }

        const confirmBtn = document.getElementById('confirm-produk-btn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
                setTimeout(checkForHandphone, 500);
            });
        }

        tipePihak.addEventListener('change', function() {
            checkForHandphone();

            const suplierSelect = document.getElementById('suplier');

            if (this.value === 'pelanggan') {
                suplierSelect.disabled = true;
                suplierSelect.hidden = true;
                suplierSelect.value = '';
                document.getElementById('suplier-label').style.display = 'none';
                document.getElementById('id_suplier_text').value = '';
                document.getElementById('pelanggan-container').style.display = 'block';
                document.getElementById('pelanggan-section').style.display = 'block';
            } else if (this.value === 'suplier') {
                suplierSelect.disabled = false;
                suplierSelect.hidden = false;
                document.getElementById('suplier-label').style.display = 'block';
                document.getElementById('pelanggan-container').style.display = 'none';
                document.getElementById('pelanggan-section').style.display = 'none';
            }
        });

        document.getElementById('btnTambahPelanggan').addEventListener('click', function() {
            modalTambah.show();
        });

        document.getElementById('btnPilihPelanggan').addEventListener('click', function() {
            const select = document.getElementById('pelanggan-select');
            const selectedOption = select.options[select.selectedIndex];

            if (!selectedOption || selectedOption.disabled) {
                alert('Silakan pilih pelanggan terlebih dahulu.');
                return;
            }

            document.getElementById('pelanggan-container').style.display = 'block';
            document.getElementById('pelanggan').value = selectedOption.text;

            pelangganModal.hide();

            setTimeout(() => {
                $('.modal-backdrop').remove();
                document.body.classList.remove('modal-open');
                document.documentElement.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.documentElement.style.removeProperty('overflow');
            }, 300);
        });

        $('#formTambahPelanggan').on('submit', function(e) {
            e.preventDefault();

            const formData = $(this).serialize();

            $.ajax({
                url: '<?= base_url('simpan/pelanggan') ?>',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        modalTambah.hide();
                        $('#formTambahPelanggan')[0].reset();

                        const newOption = new Option(
                            response.data.nama + ' : ' + response.data.no_hp,
                            response.data.id_pelanggan,
                            true,
                            true
                        );

                        $('#pelanggan-select').append(newOption).trigger('change');

                        alert('Pelanggan berhasil ditambahkan');
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Terjadi kesalahan saat menyimpan data.');
                }
            });
        });
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllPPN = document.getElementById('select-all-ppn');

        selectAllPPN.addEventListener('change', function() {
            document.querySelectorAll('.ppn-checkbox').forEach(cb => cb.checked = selectAllPPN.checked);
            updateTotals();
        });
    });
    </script>

    <script>
    $(document).ready(function() {
        $('.tunai-section, .transfer-section').hide();

        $('#metode_bayar').on('change', function() {
            var metode = $(this).val();

            if (metode === 'tunai') {
                $('.tunai-section').show();
                $('.transfer-section').hide();
            } else if (metode === 'transfer') {
                $('.tunai-section').hide();
                $('.transfer-section').show();
            } else if (metode === 'tunai_transfer') {
                $('.tunai-section').show();
                $('.transfer-section').show();
            } else {
                $('.tunai-section, .transfer-section').hide();
            }
        });

        function tambahBarisBank() {
            const container = $('#bank-payment-container');
            const index = container.children('.bank-row').length;

            const newRow = $(`
            <div class="pb-bank-row bank-row">
                <select name="bank[${index}][id]" class="select2 form-select bank-select" style="width: 100%;" required>
                    <option disabled selected>Pilih Bank</option>
                    <?php foreach ($bank as $p): ?>
                        <option value="<?= htmlspecialchars($p->idbank) ?>">
                            <?= htmlspecialchars($p->nama_bank) . ' ' . ($p->atas_nama)?> : <?= htmlspecialchars($p->norek) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" class="form-control bank-amount" name="bank[${index}][jumlah]" value="Rp 0">
                <button type="button" class="pb-btn pb-btn--danger pb-btn--icon hapus-bank" aria-label="Hapus bank">&times;</button>
            </div>
        `);

            container.append(newRow);

            newRow.find('.bank-amount').on('input', function() {
                const numeric = this.value.replace(/[^\d]/g, '');
                this.value = formatToRupiah(numeric);
                updateHutang();
            });

            newRow.find('.hapus-bank').on('click', function() {
                $(this).closest('.bank-row').remove();
                updateHutang();
            });

            newRow.find('.bank-select').select2({
                dropdownParent: $('body')
            });
        }

        $('#tambah-bank').on('click', tambahBarisBank);

        $('#metode_bayar').on('change', function() {
            if ($(this).val() === 'transfer' || $(this).val() === 'tunai_transfer') {
                if ($('#bank-payment-container .bank-row').length === 0) {
                    tambahBarisBank();
                }
            }
        });
    });
    </script>

    <script>
    let pembelianTersimpan = false;

    document.getElementById('form_pembelian').addEventListener('submit', function(e) {
        const tipe = document.getElementById('tipe_pihak').value;
        const pelanggan = document.getElementById('pelanggan').value.trim();

        if (tipe === 'pelanggan' && pelanggan === '') {
            e.preventDefault();
            alert('Pelanggan belum dipilih!');
            return false;
        }

        // Cegah double submit: submit kedua diabaikan dan tombol dikunci.
        if (pembelianTersimpan) {
            e.preventDefault();
            return false;
        }
        pembelianTersimpan = true;

        const tombol = this.querySelector('button[type="submit"]');
        if (tombol) {
            tombol.disabled = true;
            tombol.setAttribute('aria-busy', 'true');
        }
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const kategori = document.getElementById('id_kategori');
        const namaBarangInput = document.getElementById('nama_barang');

        const typeInput = document.getElementById('type');
        const sizeInput = document.getElementById('size');
        const imeiInput = document.getElementById('imei');

        const typeWrapper = typeInput.parentElement;
        const sizeWrapper = sizeInput.parentElement;
        const imeiWrapper = imeiInput.parentElement;

        imeiWrapper.style.display = 'none';
        typeWrapper.style.display = 'none';
        sizeWrapper.style.display = 'none';

        // Harga PL & HPP di modal: tampilkan pemisah ribuan titik. Tanpa "Rp "
        // di dalam value karena server hanya membuang titik (Pembelian::insert_produk).
        document.querySelectorAll('#input-produk-modal .currency').forEach(function (el) {
            el.addEventListener('input', function () {
                const numeric = this.value.replace(/[^\d]/g, '');
                this.value = numeric ? Number(numeric).toLocaleString('id-ID') : '';
            });
        });

        kategori.addEventListener('change', function() {
            if (this.value === 'Handphone') {
                namaBarangInput.parentElement.style.display = 'block';
                namaBarangInput.setAttribute('required', 'required');
                namaBarangInput.placeholder = 'Masukkan nama handphone';

                imeiWrapper.style.display = 'block';
                typeWrapper.style.display = 'block';
                sizeWrapper.style.display = 'block';
            } else {
                namaBarangInput.parentElement.style.display = 'block';
                namaBarangInput.setAttribute('required', 'required');
                namaBarangInput.placeholder = 'Masukkan nama barang';

                imeiWrapper.style.display = 'none';
                typeWrapper.style.display = 'none';
                sizeWrapper.style.display = 'none';

                typeInput.value = '';
                sizeInput.value = '';
                imeiInput.value = '';
            }
        });
    });
    </script>
</div>
