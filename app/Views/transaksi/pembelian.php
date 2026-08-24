<script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>

<!-- Page Header -->
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Pembelian</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Transaksi</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Pembelian</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Form & Main Content -->
<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom"></div>

    <div class="card-body px-4 pt-4 pb-2">
        <form action="<?= base_url('insert_pembelian') ?>" method="post" id="form_pembelian"
            enctype="multipart/form-data">
            <div class="row g-3">
                <!-- <div class="col-md-6">
                    <label for="no_nota" class="form-label">No Nota Supplier</label>
                    <input type="text" class="form-control" id="no_nota" name="no_nota" placeholder="xxxxxxxxx"
                        required>
                </div> -->

                <div class="col-md-6">
                    <label for="nama_sumber" class="form-label">Sumber</label>
                    <select id="tipe_pihak" class="form-select" required>
                        <option value="">Pilih Tipe</option>
                        <option value="suplier">Suplier</option>
                        <option value="pelanggan">Pelanggan</option>
                    </select>


                </div>



                <div class="col-md-6">
                    <label for="nama_suplier" class="form-label" id="suplier-label">Nama Suplier</label>
                    <select disabled class="form-select" id="suplier" name="suplier" required
                        onchange="tampilkanIdSuplier()">
                        <option value="" disabled selected>Pilih Unit</option>
                        <?php foreach ($suplier as $b): ?>
                        <option value="<?= $b->id_suplier; ?>"><?= $b->nama_suplier; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input name="id_suplier_text" type="hidden" id="id_suplier_text"
                        class="text-muted d-block mt-2"></input>

                    <div id="pelanggan-container" style="display: none;">
                        <label for="pelanggan" class="form-label">Pelanggan</label>
                        <input type="text" class="form-control" id="pelanggan" name="pelanggan" readonly>
                    </div>
                    <div id="pelanggan-section" class="mt-3" style="display: none;">
                        <button type="button" class="btn btn-warning" data-bs-toggle="modal"
                            data-bs-target="#pelangganModal"
                            style="display: inline-flex; align-items: center; margin-bottom: 4px;">
                            <iconify-icon icon="mdi:account" width="20" height="20" style="margin-right: 8px;">
                            </iconify-icon>
                            Input Data Pelanggan
                        </button>
                    </div>
                </div>


                <div class="col-md-6">
                    <label class="form-label">Upload Gambar Nota : Max 10Mb</label>
                    <div class="border border-2 border-dashed rounded p-3 text-center position-relative">
                        <i class="bi bi-cloud-arrow-up fs-1 text-secondary"></i>
                        <p class="mb-0 text-muted">Drag and drop a file here or click</p>
                        <input type="file" name="nota_file" id="notaFileInput"
                            class="form-control position-absolute top-0 start-0 w-100 h-100 opacity-0" accept="image/*">
                    </div>

                    <!-- Preview Image -->
                    <div class="mt-2 text-center">
                        <img id="previewNota" src="#" alt="Preview Gambar Nota"
                            style="max-width: 100%; max-height: 250px; display: none;" />
                    </div>
                </div>
                <?php
                $idUnit = session('ID_UNIT');
                $tanggalHariIni = date('Y-m-d');
                ?>

                <div class="col-md-3">
                    <label for="tanggal_masuk" class="form-label">Tanggal Masuk</label>

                    <?php if ($idUnit == 1): ?>
                    <!-- UNIT 1: bebas pilih tanggal -->
                    <input type="date" class="form-control" id="tanggal_masuk" name="tanggal_masuk"
                        value="<?= $tanggalHariIni ?>" required>
                    <?php else: ?>
                    <!-- UNIT selain 1: tanggal hari ini saja -->
                    <input type="date" class="form-control" id="tanggal_masuk" name="tanggal_masuk"
                        value="<?= $tanggalHariIni ?>" min="<?= $tanggalHariIni ?>" max="<?= $tanggalHariIni ?>"
                        readonly required>
                    <?php endif; ?>
                </div>

                <div class="col-md-3">
                    <label for="jatuh_tempo" class="form-label">Jatuh Tempo</label>
                    <input type="date" class="form-control" id="jatuh_tempo" name="jatuh_tempo"
                        value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="metode_bayar" class="form-label">Metode Pembayaran</label>
                    <select id="metode_bayar" name="metode_bayar" class="form-control">
                        <option disabled selected>Pilih Metode</option>
                        <option value="tunai">Tunai</option>
                        <option value="transfer">Transfer</option>
                        <option value="tunai_transfer">Tunai + Transfer</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="frontliner" class="form-label">Frontliner</label>
                    <select class="form-control" id="frontliner" name="frontliner" required>
                        <option value="">-- Pilih Frontliner --</option>
                        <?php foreach ($frontliner as $akun): ?>
                        <option value="<?= esc($akun->ID_AKUN) ?>"><?= esc($akun->NAMA_AKUN) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>



            </div>

            <!-- Button Trigger Modal -->
            <div class="mt-4">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                    data-bs-target="#pilih-produk-modal" style="display: inline-flex; align-items: center;">
                    <iconify-icon icon="solar:password-minimalistic-input-broken" width="24" height="24"
                        style="margin-right: 8px;"></iconify-icon>
                    Pilih Produk
                </button>

                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                    data-bs-target="#input-produk-modal">
                    Tambah Produk
                </button>
            </div>

            <!-- Modal Pilih Produk -->
            <div class="modal fade" id="pilih-produk-modal" tabindex="-1" aria-labelledby="produkModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Pilih Produk</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive mb-4 px-4">
                                <table class="table border text-nowrap mb-0 align-middle" id="produk-modal-table">
                                    <thead class="text-dark fs-4">
                                        <tr>
                                            <th><input type="checkbox" id="select-all-produk"></th>
                                            <th>
                                                <h6 class="fs-4 fw-semibold mb-0">Kode Barang</h6>
                                            </th>
                                            <th>
                                                <h6 class="fs-4 fw-semibold mb-0">Nama Barang</h6>
                                            </th>
                                            <th>
                                                <h6 class="fs-4 fw-semibold mb-0">Harga</h6>
                                            </th>
                                            <th>
                                                <h6 class="fs-4 fw-semibold mb-0">HPP</h6>
                                            </th>
                                            <th>
                                                <h6 class="fs-4 fw-semibold mb-0">Kategori</h6>
                                            </th>
                                            <th>
                                                <h6 class="fs-4 fw-semibold mb-0">IMEI</h6>
                                            </th>
                                            <th>
                                                <h6 class="fs-4 fw-semibold mb-0">Penyimpanan</h6>
                                            </th>
                                            <th>
                                                <h6 class="fs-4 fw-semibold mb-0">Warna</h6>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($produk as $p): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="produk-checkbox"
                                                    data-id="<?= $p->idbarang ?>" data-kode="<?= $p->kode_barang ?>"
                                                    data-nama="<?= $p->nama_barang ?>" data-harga="<?= $p->harga ?>"
                                                    data-harga_beli="<?= $p->harga_beli ?>"
                                                    data-kategori="<?= $p->nama_kategori ?>"
                                                    data-input="<?= $p->input ?>" data-imei="<?= $p->imei ?>">
                                            </td>
                                            <td><?= $p->kode_barang ?></td>
                                            <td><?= $p->nama_barang ?></td>
                                            <td><?= 'Rp ' . number_format($p->harga, 0, ',', '.') ?></td>
                                            <td><?= 'Rp ' . number_format($p->harga_beli, 0, ',', '.') ?></td>
                                            <td><?= $p->nama_kategori ?></td>
                                            <td><?= $p->imei ? $p->imei : 'Tidak Ada' ?></td>
                                            <td><?= $p->internal ? $p->internal : 'Tidak Ada' ?></td>
                                            <td><?= $p->warna ? $p->warna : 'Tidak Ada' ?></td>

                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" id="confirm-produk-btn" class="btn btn-primary">Pilih</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Selected Produk Table -->
            <div class="table-responsive mt-3 mb-4">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th>IMEI</th>
                            <th>Hrg. Jual</th>
                            <th>Hrg. Beli</th>
                            <th>Nominal</th>
                            <th>Biaya tambahan</th>
                            <th>Keterangan</th>
                            <th>Diskon (Rp)</th>
                            <th>
                                <input type="checkbox" id="select-all-ppn"> PPN 11%
                            </th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="selected-produk-table">
                        <!-- Rows will be dynamically added -->
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3"><strong>Total Diskon (Rp.)</strong></td>
                            <td colspan="7">
                                <input type="text" id="total-diskon" name="total-diskon" class="form-control" readonly>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3"><strong>Total PPN</strong></td>
                            <td colspan="7">
                                <input type="text" id="total-ppn" name="total-ppn" class="form-control" readonly>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3"><strong>Total Keseluruhan</strong></td>
                            <td colspan="7">
                                <input type="text" id="total-harga" name="total-harga" class="form-control" readonly>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <!-- , -->

                <!-- Bayar and Hutang Form -->
                <div class="row mb-3 tunai-section">
                    <div class="col-md-6">
                        <label for="bayar" class="form-label">Bayar Tunai</label>
                        <input type="text" class="form-control" id="bayar" name="bayar" value="Rp 0">
                    </div>

                </div>

                <div class="row mb-3 transfer-section">


                    <div class="row mb-3 transfer-section">
                        <div class="col-12">
                            <label class="form-label">Pembayaran via Bank</label>
                            <div id="bank-payment-container">
                                <!-- Baris bank akan ditambahkan secara dinamis -->
                            </div>
                            <button type="button" class="btn btn-sm btn-primary mt-2" id="tambah-bank">
                                + Tambah Bank
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="hutang" class="form-label">Hutang</label>
                        <input type="text" class="form-control" id="hutang" name="hutang" readonly>
                    </div>
                    <div class="col-md-6">
                        <label for="kembalian" class="form-label">Kembalian</label>
                        <input type="text" class="form-control" id="kembalian" name="kembalian" value="Rp 0">
                    </div>
                </div>

                <button style="height: fit-content;" class="btn btn-success mt-3" type="submit">Simpan</button>

                <!-- Pelanggan Button -->
                <!-- Pelanggan Button -->


                <div class="modal fade" id="pelangganModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Cari Data Pelanggan</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <select id="pelanggan-select" name="selectedidpelanggan" class="select2 form-control"
                                    style="width: 100%;">
                                    <option disabled selected>Select</option>
                                    <?php foreach ($pelanggan as $p): ?>
                                    <option value="<?= htmlspecialchars($p->id_pelanggan) ?>">
                                        <?= htmlspecialchars($p->nama) ?> : <?= htmlspecialchars($p->no_hp) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>

                                <!-- Tombol di bawah dropdown -->
                                <div style="display: flex; justify-content: right; gap: 10px; margin-top: 20px;">
                                    <button id="btnPilihPelanggan" type="button" class="btn btn-primary">Pilih</button>
                                    <button id="btnTambahPelanggan" type="button"
                                        class="btn btn-success">Tambah</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- //modal Input Produk -->


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

            document.addEventListener('DOMContentLoaded', function() {
                const selectedTable = document.getElementById('selected-produk-table');
                const confirmBtn = document.getElementById('confirm-produk-btn');
                const selectAll = document.getElementById('select-all-produk');
                const modalEl = document.getElementById('pilih-produk-modal');
                const modalInstance = new bootstrap.Modal(modalEl);
                const bayarInput = document.getElementById('bayar');
                const bayarBank = document.getElementById('bayar_bank');

                selectAll.addEventListener('change', function() {
                    document.querySelectorAll('.produk-checkbox').forEach(cb => cb.checked = this
                        .checked);
                });

                confirmBtn.addEventListener('click', function() {
                    document.querySelectorAll('.produk-checkbox:checked').forEach(cb => {
                        const id = cb.getAttribute('data-id');
                        const kode = cb.getAttribute('data-kode');
                        const nama = cb.getAttribute('data-nama');
                        const harga = cb.getAttribute('data-harga');
                        const harga_beli = cb.getAttribute('data-harga_beli');
                        const kategori = cb.getAttribute('data-kategori');

                        const imeiAttr = cb.getAttribute('data-imei');
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
                            Rp ${parseInt(harga).toLocaleString('id-ID')}
                            <input type="hidden" name="produk[${id}][harga]" value="${harga}">
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
                            <button type="button" class="btn btn-sm btn-danger" onclick="hapusProduk('${id}')">
                                <iconify-icon icon="solar:trash-bin-minimalistic-broken" width="20" height="20"></iconify-icon>
                            </button>
                        </td>
                    `;

                            selectedTable.appendChild(row);

                            const jumlahInput = row.querySelector('.jumlah-input');
                            jumlahInput.value = 1;
                            if (hasImei) {
                                jumlahInput.setAttribute('readonly', true);
                            }

                            row.querySelector('.jumlah-input').addEventListener('input',
                                updateTotals);
                            row.querySelector('.ppn-checkbox').addEventListener('change',
                                updateTotals);

                            const hargaBeliInput = row.querySelector('.harga-beli-input');
                            hargaBeliInput.addEventListener('input', function() {
                                const id = this.getAttribute('data-id');
                                const numeric = this.value.replace(/[^\d]/g, '');
                                this.value = formatToRupiah(numeric);
                                document.getElementById(`harga-beli-hidden-${id}`)
                                    .value = numeric;
                                updateTotals();
                            });

                            // 🔹 Event listener untuk biaya tambahan
                            const biayaTambahanInput = row.querySelector(
                                '.biaya-tambahan-input');
                            biayaTambahanInput.addEventListener('input', function() {
                                const id = this.getAttribute('data-id');
                                const numeric = this.value.replace(/[^\d]/g, '');
                                this.value = formatToRupiah(numeric);
                                document.getElementById(`biaya-tambahan-hidden-${id}`)
                                    .value = numeric;
                                updateTotals();
                            });


                            const diskonInput = row.querySelector('.diskon-input');
                            diskonInput.addEventListener('input', function() {
                                const id = this.getAttribute('data-id');
                                const numeric = this.value.replace(/[^\d]/g, '');
                                this.value = formatToRupiah(numeric);
                                document.getElementById(`diskon-hidden-${id}`).value =
                                    numeric;
                                updateTotals();
                            });
                        }
                    });

                    modalInstance.hide();
                    setTimeout(() => {
                        document.body.classList.remove('modal-open');
                        document.body.style.removeProperty('padding-right');
                        document.body.style.removeProperty('overflow');
                        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
                    }, 500);

                    document.querySelectorAll('.produk-checkbox').forEach(cb => cb.checked = false);
                    selectAll.checked = false;

                    updateTotals();
                });

                bayarInput.addEventListener('input', function() {
                    const numeric = this.value.replace(/[^\d]/g, '');
                    this.value = formatToRupiah(numeric);
                    updateHutang();
                });

                bayarBank.addEventListener('input', function() {
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
                updateTotals();
            }

            function updateTotals() {
                let total = 0;
                let totalDiskon = 0;
                let totalPPN = 0;

                document.querySelectorAll('#selected-produk-table tr').forEach(row => {
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


                const totalHargaFinal = total;

                document.getElementById('total-ppn').value = 'Rp ' + totalPPN.toLocaleString('id-ID');
                document.getElementById('total-harga').value = 'Rp ' + totalHargaFinal.toLocaleString('id-ID');

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

                // **🔑 Ambil semua pembayaran bank**
                let bankTotal = 0;
                document.querySelectorAll('.bank-amount').forEach(input => {
                    bankTotal += unformatRupiah(input.value || 'Rp 0');
                });

                const selisih = (bayar + bankTotal) - total;

                const hutang = Math.max(total - (bayar + bankTotal), 0);
                const kembalian = selisih > 0 ? selisih : 0;

                hutangInput.value = 'Rp ' + hutang.toLocaleString('id-ID');
                kembalianInput.value = 'Rp ' + kembalian.toLocaleString('id-ID');
            }
            </script>


        </form>

        <div class="modal fade" id="modalTambahPelanggan" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="formTambahPelanggan">
                        <div class="modal-header">
                            <h5 class="modal-title">Tambah Pelanggan Baru</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="nik" class="form-label">NIK</label>
                                <input type="text" id="nik" name="nik" class="form-control" required />
                            </div>
                            <div class="mb-3">
                                <label for="nama" class="form-label">Nama</label>
                                <input type="text" id="nama" name="nama" class="form-control" required />
                            </div>
                            <div class="mb-3">
                                <label for="no_hp" class="form-label">No HP</label>
                                <input type="text" id="no_hp" name="no_hp" class="form-control" required />
                            </div>
                            <div class="mb-3">
                                <label for="alamat" class="form-label">Alamat</label>
                                <textarea id="alamat" name="alamat" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Simpan Pelanggan</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>



        <script>
        $(document).ready(function() {
            var table = $('#produk-modal-table').DataTable();


        });
        </script>
        <script>
        function tampilkanIdSuplier() {
            const select = document.getElementById('suplier');
            const idSuplierInput = document.getElementById('id_suplier_text');
            const selectedValue = select.value;
            idSuplierInput.value = selectedValue;
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

            /* ===============================
               FIX SELECT2 + MODAL SCROLL
               =============================== */
            let pelangganSelect2Inited = false;

            $('#pelangganModal').on('shown.bs.modal', function() {

                // Init select2 only once
                if (!pelangganSelect2Inited) {
                    $('#pelanggan-select').select2({
                        dropdownParent: $('#pelangganModal .modal-content')
                    });
                    pelangganSelect2Inited = true;
                }

                // Fix scroll stuck (clean both html & body)
                document.body.classList.add('modal-open');
                document.documentElement.classList.add('modal-open');
            });

            $('#pelangganModal').on('hidden.bs.modal', function() {
                // remove leftover backdrop
                $('.modal-backdrop').remove();

                // cleanup scroll lock
                document.body.classList.remove('modal-open');
                document.documentElement.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.documentElement.style.removeProperty('overflow');
            });

            /* ===============================
               FUNCTION checkForHandphone()
               =============================== */
            function checkForHandphone() {
                const rows = document.querySelectorAll('#selected-produk-table tr');
                let show = false;

                rows.forEach(row => {
                    const kategoriInput = row.querySelector('input[name$="[kategori]"]');
                    if (tipePihak.value === 'pelanggan') {
                        show = true;
                    }
                });

                document.getElementById('pelanggan-button')?.remove();

                if (show) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.id = 'pelanggan-button';

                    btn.style =
                        'display:none;margin:0;padding:0;width:0;height:0;';

                    // FIX: always use same instance
                    btn.onclick = () => pelangganModal.show();

                    const container = document.querySelector('.table-responsive.mt-3.mb-4');
                    if (container) container.appendChild(btn);
                }
            }

            const confirmBtn = document.getElementById('confirm-produk-btn');
            if (confirmBtn) {
                confirmBtn.addEventListener('click', () => {
                    setTimeout(checkForHandphone, 500);
                });
            }


            /* ===============================
               Tipe pihak change
               =============================== */
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

            /* ===============================
               Tambah pelanggan
               =============================== */
            document.getElementById('btnTambahPelanggan').addEventListener('click', function() {
                modalTambah.show();
            });

            /* ===============================
               Pilih pelanggan
               =============================== */
            document.getElementById('btnPilihPelanggan').addEventListener('click', function() {
                const select = document.getElementById('pelanggan-select');
                const selectedOption = select.options[select.selectedIndex];

                if (!selectedOption || selectedOption.disabled) {
                    alert('Silakan pilih pelanggan terlebih dahulu.');
                    return;
                }

                document.getElementById('pelanggan-container').style.display = 'block';
                document.getElementById('pelanggan').value = selectedOption.text;

                pelangganModal.hide(); // use same modal instance

                // Cleanup leftover backdrop
                setTimeout(() => {
                    $('.modal-backdrop').remove();
                    document.body.classList.remove('modal-open');
                    document.documentElement.classList.remove('modal-open');
                    document.body.style.removeProperty('overflow');
                    document.documentElement.style.removeProperty('overflow');
                }, 300);
            });

            /* ===============================
               AJAX tambah pelanggan
               =============================== */
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
                const checkboxes = document.querySelectorAll('.ppn-checkbox');
                checkboxes.forEach(cb => cb.checked = selectAllPPN.checked);
                updateTotals();
            });
        });
        </script>


        <script>
        $(document).ready(function() {
            // Sembunyikan section awal
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

            // Function untuk buat baris bank
            function tambahBarisBank() {
                const container = $('#bank-payment-container');
                const index = container.children('.bank-row').length;

                const newRow = $(`
            <div class="row bank-row mb-2">
                <div class="col-md-6">
                    <select name="bank[${index}][id]" class="select2 form-control bank-select" style="width: 100%;" required>
                        <option disabled selected>Pilih Bank</option>
                        <?php foreach ($bank as $p): ?>
                            <option value="<?= htmlspecialchars($p->idbank) ?>">
                                <?= htmlspecialchars($p->nama_bank) . ' ' . ($p->atas_nama)?> : <?= htmlspecialchars($p->norek) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control bank-amount" name="bank[${index}][jumlah]" value="Rp 0">
                </div>
                <div class="col-md-2 text-end">
                    <button type="button" class="btn btn-danger btn-sm hapus-bank">&times;</button>
                </div>
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

            // Klik tombol tambah bank
            $('#tambah-bank').on('click', tambahBarisBank);

            // Panggil minimal satu kali jika metode transfer dipilih
            $('#metode_bayar').on('change', function() {
                if ($(this).val() === 'transfer' || $(this).val() === 'tunai_transfer') {
                    if ($('#bank-payment-container .bank-row').length === 0) {
                        tambahBarisBank();
                    }
                }
            });

            $('#bank_idbank').select2({
                dropdownParent: $('body')
            });
        });
        </script>


        <script>
        document.getElementById('form-pembelian').addEventListener('submit', function(e) {
            const requiredInputs = this.querySelectorAll('[required]');
            let isValid = true;
            let firstEmpty = null;
            let emptyFields = [];

            requiredInputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    emptyFields.push(input.id || input.name);
                    if (!firstEmpty) firstEmpty = input;
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Field berikut belum diisi:\n\n' + emptyFields.join(', '));
                if (firstEmpty) firstEmpty.focus();
            }
        });
        </script>


        <div class="modal fade" id="input-produk-modal" tabindex="-1" aria-labelledby="inputProdukModalLabel"
            aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header d-flex align-items-center">
                        <h4 class="modal-title" id="inputProdukModalLabel">Input Data Barang</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="<?= base_url('insert_produk') ?>" enctype="multipart/form-data" method="post">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="nama_barang" class="form-label">Nama Barang</label>
                                <input type="text" class="form-control" id="nama_barang" name="nama_barang">
                            </div>
                            <!-- <div class="mb-3">
                                <label for="">Nama Handphone</label>
                                <select id="namahandphone-select" name="nama_hp" class="select2 form-control"
                                    style="width: 100%;">
                                    <option disabled selected>Select</option>
                                    <?php foreach ($nama_handphone as $p): ?>
                                    <option data-type="<?= $p->type ?>" data-size="<?= $p->size ?>"
                                        value="<?= htmlspecialchars($p->id) ?>">
                                        <?= htmlspecialchars($p->nama)  ?> : <?= htmlspecialchars($p->type)  ?> :
                                        <?= htmlspecialchars($p->size)  ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>

                            </div> -->

                            <div class="mb-3">
                                <label for="id_kategori" class="form-label">Kategori</label>
                                <select class="form-control" id="id_kategori" name="kategori" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($kategori as $k) : ?>
                                    <option data-idkategori="<?= $k->id ?>" value="<?= $k->nama_kategori; ?>">
                                        <?= $k->nama_kategori; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>


                            <div class="mb-3">
                                <label for="harga" class="form-label">Harga PL</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" class="form-control currency" id="harga" name="harga" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="harga_beli" class="form-label">HPP</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" class="form-control currency" id="harga_beli" name="harga_beli"
                                        required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="imei" class="form-label">Imei</label>
                                <input type="text" class="form-control" id="imei" name="imei">
                            </div>

                            <div class="mb-3">
                                <label for="type" class="form-label">Tipe</label>
                                <input type="text" class="form-control" id="type" name="type">
                            </div>

                            <div class="mb-3">
                                <label for="kondisi" class="form-label">Kondisi</label>
                                <select id="kondisi" name="kondisi" class="form-control"
                                    style="width: 100%;" required>
                                    <option value=0>Baru</option>
                                    <option value=1>Second</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="size" class="form-label">Spesifikasi</label>
                                <input type="text" class="form-control" id="size" name="size">
                            </div>


                            <div class="mb-3">
                                <label for="warna" class="form-label">Warna</label>
                                <input type="text" class="form-control" id="warna" name="warna" required>
                            </div>

                            <div class="mb-3">
                                <label for="stok_minimum" class="form-label">Stok Minim</label>
                                <input type="text" class="form-control" id="stok_minim" value="0" name="stok_minimum"
                                    required>
                            </div>

                            <div class="mb-3">
                                <label for="ppn_status" class="form-label">Status PPN</label>
                                <select class="form-control" id="ppn_status" name="status_ppn">
                                    <option value="">-- Pilih Status PPN --</option>
                                    <option value="1">PPN</option>
                                    <option value="0">Non PPN</option>
                                </select>
                            </div>


                            <div class="mb-3">
                                <label for="input_by" class="form-label">Input By</label>
                                <input type="text" class="form-control" value="<?= session()->get('NAMA') ?? '' ?>"
                                    id="input_by" name="input_by" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn bg-danger-subtle text-danger"
                                data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        document.getElementById('form_pembelian').addEventListener('submit', function(e) {

            const tipe = document.getElementById('tipe_pihak').value;
            const pelanggan = document.getElementById('pelanggan').value.trim();

            // ❗ ONLY validate pelanggan if tipe pihak = pelanggan
            if (tipe === "pelanggan" && pelanggan === "") {
                e.preventDefault();
                alert("Pelanggan belum dipilih!");
                return false;
            }

            // otherwise allow submit normally
        });
        </script>


        <script>
document.addEventListener("DOMContentLoaded", function() {

    const kategori = document.getElementById("id_kategori");
    const namaBarangInput = document.getElementById("nama_barang");

    const typeInput = document.getElementById("type");
    const sizeInput = document.getElementById("size");
    const imeiInput = document.getElementById("imei");

    const typeWrapper = typeInput.parentElement;
    const sizeWrapper = sizeInput.parentElement;
    const imeiWrapper = imeiInput.parentElement;

    // default hide field tambahan
    imeiWrapper.style.display = "none";
    typeWrapper.style.display = "none";
    sizeWrapper.style.display = "none";

    kategori.addEventListener("change", function() {

        if (this.value === "Handphone") {

            // ✅ tampilkan input manual nama barang
            namaBarangInput.parentElement.style.display = "block";
            namaBarangInput.setAttribute("required", "required");
            namaBarangInput.placeholder = "Masukkan nama handphone";

            // ✅ tampilkan field tambahan
            imeiWrapper.style.display = "block";
            typeWrapper.style.display = "block";
            sizeWrapper.style.display = "block";

        } else {

            // tetap pakai input biasa
            namaBarangInput.parentElement.style.display = "block";
            namaBarangInput.setAttribute("required", "required");
            namaBarangInput.placeholder = "Masukkan nama barang";

            // sembunyikan field tambahan
            imeiWrapper.style.display = "none";
            typeWrapper.style.display = "none";
            sizeWrapper.style.display = "none";

            // reset value
            typeInput.value = "";
            sizeInput.value = "";
            imeiInput.value = "";
        }
    });

});
</script>


        <script>
        document.addEventListener("DOMContentLoaded", function() {

            const kategoriSelect = document.getElementById("id_kategori");
            const subKategoriSelect = document.getElementById("subKategori");

            // Simpan semua sub kategori dulu
            let allSubkategoriOptions = Array.from(subKategoriSelect.querySelectorAll("option"));

            kategoriSelect.addEventListener("change", function() {
                const selectedIdKategori = this.options[this.selectedIndex].getAttribute(
                    "data-idkategori");

                // Reset isi subkategori
                subKategoriSelect.innerHTML = "";

                // Tambahkan opsi default
                let defaultOption = document.createElement("option");
                defaultOption.value = "";
                defaultOption.textContent = "Semua Sub Kategori";
                subKategoriSelect.appendChild(defaultOption);

                if (!selectedIdKategori) {
                    // Jika kategori kosong, tampilkan semua sub kategori
                    allSubkategoriOptions.forEach(opt => {
                        if (opt.value !== "") subKategoriSelect.appendChild(opt.cloneNode(
                            true));
                    });
                    return;
                }

                // Filter sub kategori berdasarkan parent
                allSubkategoriOptions.forEach(opt => {
                    let parentId = opt.getAttribute("data-idparent_kategori");

                    if (parentId === selectedIdKategori) {
                        subKategoriSelect.appendChild(opt.cloneNode(true));
                    }
                });
            });

        });
        </script>