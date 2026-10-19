<style>
    /* Pemilih sparepart — daftar pilih, bukan tabel padat */
    .service-sp-search {
        position: relative;
    }

    .service-sp-search-icon {
        position: absolute;
        top: 50%;
        left: 14px;
        transform: translateY(-50%);
        color: var(--bs-secondary-color);
        pointer-events: none;
    }

    .service-sp-search .form-control {
        height: 44px;
        padding-left: 42px;
    }

    .service-sp-unit {
        flex-shrink: 0;
        font-size: .75rem;
        font-weight: 600;
        color: var(--bs-secondary-color);
    }

    .service-sp-list {
        display: flex;
        flex-direction: column;
        gap: .5rem;
        min-height: 220px;
        max-height: min(58vh, 460px);
        overflow-y: auto;
        padding: 4px;
        scrollbar-width: thin;
        scrollbar-color: var(--bs-border-color) transparent;
    }

    .service-sp-item {
        position: relative;
        margin: 0;
        cursor: pointer;
    }

    .service-sp-item__input {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }

    .service-sp-item__box {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: .7rem .875rem;
        border: 1px solid var(--bs-border-color);
        border-radius: 12px;
        background: var(--bs-body-bg);
        transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
    }

    .service-sp-item:hover .service-sp-item__box {
        border-color: var(--bs-primary-border-subtle);
        background: var(--bs-primary-bg-subtle);
    }

    .service-sp-item__input:focus-visible+.service-sp-item__box {
        outline: 2px solid var(--bs-primary);
        outline-offset: 2px;
    }

    .service-sp-item__input:checked+.service-sp-item__box {
        border-color: var(--bs-primary);
        background: var(--bs-primary-bg-subtle);
    }

    .service-sp-tick {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border: 1.5px solid var(--bs-border-color);
        border-radius: 50%;
        color: transparent;
        transition: border-color .15s ease, background-color .15s ease, color .15s ease;
    }

    .service-sp-item__input:checked+.service-sp-item__box .service-sp-tick {
        border-color: var(--bs-primary);
        background: var(--bs-primary);
        color: #fff;
    }

    .service-sp-item__main {
        flex: 1 1 auto;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: .2rem;
    }

    .service-sp-name {
        font-weight: 600;
        color: var(--bs-heading-color);
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .service-sp-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .1rem .6rem;
        font-size: .78rem;
        color: var(--bs-secondary-color);
    }

    .service-sp-meta-item {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        min-width: 0;
    }

    .service-sp-meta-item i {
        font-size: .85rem;
        flex: 0 0 auto;
    }

    .service-sp-code {
        font-weight: 600;
        color: var(--bs-body-color);
        font-variant-numeric: tabular-nums;
    }

    .service-sp-side {
        flex: 0 0 auto;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: .3rem;
        text-align: right;
    }

    .service-sp-price {
        font-weight: 700;
        color: var(--bs-heading-color);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .service-sp-stock {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        padding: .1rem .5rem;
        border: 1px solid var(--bs-border-color);
        border-radius: 999px;
        background: var(--bs-tertiary-bg);
        color: var(--bs-secondary-color);
        font-size: .72rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    .service-sp-stock.is-low {
        border-color: var(--bs-warning-border-subtle);
        background: var(--bs-warning-bg-subtle);
        color: var(--bs-warning-text-emphasis);
    }

    .service-sp-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: .5rem;
        min-height: 220px;
        padding: 2rem 1rem;
        text-align: center;
    }

    .service-sp-state i {
        font-size: 1.4rem;
        color: var(--bs-secondary-color);
    }

    .service-sp-state p {
        margin: 0;
        font-size: .82rem;
        color: var(--bs-secondary-color);
    }

    .service-sp-state.is-error i {
        color: var(--bs-danger);
    }

    .service-sp-count {
        font-size: .82rem;
        color: var(--bs-secondary-color);
        font-variant-numeric: tabular-nums;
    }

    @media (max-width: 575.98px) {
        .service-sp-item__box {
            flex-wrap: wrap;
        }

        .service-sp-side {
            flex-basis: 100%;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            padding-left: calc(22px + .75rem);
        }
    }
</style>

<form id="service-sparepart-form" action="<?php echo base_url('update_service/saveSparepart') ?>" enctype="multipart/form-data" method="post">
    <div class="mt-3">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sparepartModal">
            Tambah Sparepart
        </button>
    </div>
    <br>

    <div class="modal fade" id="sparepartModal" tabindex="-1" aria-labelledby="sparepartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="bi bi-box-seam fs-5"></i>
                        </span>
                        <div>
                            <h5 class="modal-title" id="sparepartModalLabel">Pilih Sparepart</h5>
                            <p class="text-muted fs-3 mb-0">Cari nama atau kode, lalu centang yang dipakai.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="service-sp-search flex-grow-1">
                            <i class="bi bi-search service-sp-search-icon"></i>
                            <input type="text" class="form-control" id="searchSparepartInput" placeholder="Cari nama, kode, atau warna sparepart..." autocomplete="off">
                        </div>
                        <span class="service-sp-unit badge text-bg-light border d-none d-sm-inline-flex">
                            <i class="bi bi-shop me-1"></i><?= session('NAMA_UNIT') ?>
                        </span>
                    </div>
                    <div class="service-sp-list" id="sparepart-modal-body" aria-live="polite">
                        <div class="service-sp-state">
                            <i class="bi bi-search"></i>
                            <p>Ketik minimal 2 karakter untuk mencari sparepart.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <span class="service-sp-count" id="sparepart-selected-count" aria-live="polite">Belum ada yang dipilih</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="button" id="add-selected-sparepart" class="btn btn-primary d-inline-flex align-items-center gap-2" data-bs-dismiss="modal">
                            <i class="bi bi-plus-lg"></i> Tambahkan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Nama Sparepart</th>
                <th>Harga</th>
                <th>Stok Akhir</th>
                <th>Qty</th>
                <th>Batch asal</th>
                <th>Diskon</th>
                <th>Total</th>
                <th>Aksi</th>
            </tr>
        </thead>

        <tbody id="sparepart-table-body">

        </tbody>
    </table>

    <div class="mb-3">
        <label class="form-label">Total Harga</label>
        <input type="text" class="form-control" name="total_harga" readonly>
    </div>

    <input type="text" hidden name="idservice_s" value="<?php echo @$idservice ?>" id="">

    <div class="mb-3">
        <label class="form-label">Garansi</label>
        <select class="form-select" name="garansi" id="garansiSelect" onchange="cekGaransi(this)">
            <option selected disabled>---Pilih Garansi---</option>
            <option value="0">Tidak Ada</option>
            <option value="7">1 Minggu</option>
            <option value="30">1 Bulan</option>
            <option value="60">2 Bulan</option>
            <option value="180">6 Bulan</option>
            <option value="360">1 Tahun</option>
            <option value="720">2 Tahun</option>
            <option value="999">Selamanya</option>
        </select>

        <!-- Input manual akan muncul kalau pilih 'manual' -->
        <input type="text" class="form-control mt-2 d-none" name="garansi_manual" id="garansiManual" placeholder="Masukkan garansi dalam hari (contoh: 45 )">
    </div>

    <div class="mb-3">
        <label class="form-label">Total Diskon</label>
        <input type="text" class="form-control" name="diskon" id="diskon_sparepart" value="Rp 0" readonly>
    </div>

    <div class="mb-4">
        <label class="form-label">Harga Akhir</label>
        <input type="text" class="form-control" id="harga_akhir_sparepart" name="harga_akhir" value="Rp 0" readonly>
    </div>

    <div class="service-step-footer">
        <div>
            <input hidden type="text" name="idservice_s" value="<?php echo @$idservice ?>">
            <button type="button" class="btn btn-outline-secondary" id="btn-previous-to-kerusakan">
                <i class="bi bi-arrow-left me-1"></i> Sebelumnya
            </button>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted fs-3 d-none d-sm-inline">Total dihitung otomatis ke tahap Pembayaran.</span>
            <button type="submit" id="selanjutnyabtnnya" class="btn btn-primary" aria-live="polite">
                Selanjutnya <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>
    <input type="text" hidden value="<?php echo @$idservice ?>" name="" id="idpelabel">
</form>

<script>
    let searchTimeout;
    const oldSpareparts = <?= json_encode($oldsparepart) ?>;
    const sparepartFormatter = new Intl.NumberFormat('id-ID');

    function escapeSparepart(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function(ch) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [ch];
        });
    }

    function sparepartState(icon, message, isError) {
        return '<div class="service-sp-state' + (isError ? ' is-error' : '') + '">' +
            '<i class="' + icon + '" aria-hidden="true"></i>' +
            '<p>' + escapeSparepart(message) + '</p>' +
            '</div>';
    }

    function sparepartOption(item) {
        const id = item.idbarang;
        const kode = item.kode_barang || '';
        const nama = item.nama_barang || 'Tanpa nama';
        const warna = item.warna || '';
        const unit = item.NAMA_UNIT || item.nama_unit || '';
        const suplier = item.nama_suplier || '';
        const harga = Number(item.harga) || 0;
        const hpp = Number(item.harga_beli) || 0;
        const stok = Number(item.stok_akhir) || 0;
        const isChecked = document.getElementById('row-' + id) ? ' checked' : '';

        let meta = '<span class="service-sp-code">' + escapeSparepart(kode || '-') + '</span>';
        if (warna) {
            meta += '<span class="service-sp-meta-item"><i class="bi bi-droplet-half"></i>' + escapeSparepart(warna) + '</span>';
        }
        if (unit) {
            meta += '<span class="service-sp-meta-item"><i class="bi bi-phone"></i>' + escapeSparepart(unit) + '</span>';
        }
        if (suplier) {
            meta += '<span class="service-sp-meta-item"><i class="bi bi-truck"></i>' + escapeSparepart(suplier) + '</span>';
        }
        if (hpp > 0) {
            meta += '<span class="service-sp-meta-item"><i class="bi bi-tag"></i>HPP Rp ' + sparepartFormatter.format(hpp) + '</span>';
        }

        return '<label class="service-sp-item">' +
            '<input type="checkbox" class="service-sp-item__input sparepart-check"' +
            ' data-id="' + escapeSparepart(id) + '"' +
            ' data-kode="' + escapeSparepart(kode) + '"' +
            ' data-nama="' + escapeSparepart(nama) + '"' +
            ' data-harga="' + harga + '"' +
            ' data-stok="' + stok + '"' + isChecked + '>' +
            '<span class="service-sp-item__box">' +
            '<span class="service-sp-tick" aria-hidden="true"><i class="bi bi-check-lg"></i></span>' +
            '<span class="service-sp-item__main">' +
            '<span class="service-sp-name">' + escapeSparepart(nama) + '</span>' +
            '<span class="service-sp-meta">' + meta + '</span>' +
            '</span>' +
            '<span class="service-sp-side">' +
            '<span class="service-sp-price">Rp ' + sparepartFormatter.format(harga) + '</span>' +
            '<span class="service-sp-stock' + (stok <= 2 ? ' is-low' : '') + '">Stok ' + sparepartFormatter.format(stok) + '</span>' +
            '</span>' +
            '</span>' +
            '</label>';
    }

    function updateSparepartSelection() {
        const count = document.querySelectorAll('#sparepart-modal-body .sparepart-check:checked').length;
        const label = document.getElementById('sparepart-selected-count');
        const button = document.getElementById('add-selected-sparepart');
        if (label) {
            label.textContent = count > 0 ? count + ' sparepart dipilih' : 'Belum ada yang dipilih';
        }
        if (button) {
            button.disabled = count === 0;
        }
    }

    document.addEventListener('change', function(event) {
        if (event.target && event.target.classList && event.target.classList.contains('sparepart-check')) {
            updateSparepartSelection();
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        const sparepartForm = document.getElementById('service-sparepart-form');
        const nextButton = document.getElementById('selanjutnyabtnnya');
        let sparepartSubmitting = false;

        if (sparepartForm && nextButton) {
            sparepartForm.addEventListener('submit', (event) => {
                if (sparepartSubmitting) {
                    event.preventDefault();
                    return;
                }

                sparepartSubmitting = true;
                nextButton.disabled = true;
                nextButton.setAttribute('aria-busy', 'true');
                nextButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Menyimpan sparepart...';
            });
        }

        const tbody = document.getElementById('sparepart-table-body');
        const searchInput = document.getElementById('searchSparepartInput');
        const modalBody = document.getElementById('sparepart-modal-body');

        // Load old spareparts if any
        if (oldSpareparts && oldSpareparts.length > 0) {
            oldSpareparts.forEach((sp) => {
                addSparepartRow(sp.barang_idbarang, sp.nama_barang, parseFloat(sp.harga_penjualan),
                    parseInt(sp.jumlah), parseFloat(sp.diskon_penjualan), parseFloat(sp.stok_akhir || 0),
                    sp.source_purchase_detail_id);
                const checkbox = document.querySelector(`.sparepart-check[data-id="${sp.barang_idbarang}"]`);
                if (checkbox) checkbox.checked = true;
            });
            updateTotals();
        }

        // AJAX search sparepart
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();

            if (query.length < 2) {
                modalBody.innerHTML = sparepartState('bi-search', 'Ketik minimal 2 karakter untuk mencari sparepart.');
                return;
            }

            modalBody.innerHTML = sparepartState('spinner-border spinner-border-sm text-primary', 'Mencari sparepart...');

            searchTimeout = setTimeout(function() {
                fetch('<?= base_url('service/search_sparepart') ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'search=' + encodeURIComponent(query)
                })
                .then(response => response.json())
                .then(data => {
                    if (!Array.isArray(data) || data.length === 0) {
                        modalBody.innerHTML = sparepartState('bi-inbox', 'Tidak ada sparepart yang cocok dengan "' + query + '".');
                        return;
                    }

                    modalBody.innerHTML = data.map(sparepartOption).join('');
                    updateSparepartSelection();
                })
                .catch(err => {
                    console.error(err);
                    modalBody.innerHTML = sparepartState('bi-exclamation-triangle', 'Gagal memuat data sparepart. Coba lagi.', true);
                });
            }, 300);
        });

        // Load initial data when modal opens
        $('#sparepartModal').on('shown.bs.modal', function () {
            searchInput.focus();
            if (!searchInput.value) {
                searchInput.dispatchEvent(new Event('input'));
            }
        });

        updateSparepartSelection();
    });

    // Tambah sparepart dari modal
    document.getElementById('add-selected-sparepart').addEventListener('click', () => {
        document.querySelectorAll('.sparepart-check:checked').forEach((checkbox) => {
            const id = checkbox.getAttribute('data-id');
            const nama = checkbox.getAttribute('data-nama');
            const harga = parseFloat(checkbox.getAttribute('data-harga'));
            const stokAkhir = parseFloat(checkbox.getAttribute('data-stok')) || 0;

            // Cek apakah sparepart sudah ada di tabel
            if (!document.getElementById(`row-${id}`)) {
                addSparepartRow(id, nama, harga, 1, 0, stokAkhir);
            }
        });

        updateTotals();
    });

    // Tambahkan baris sparepart
    function addSparepartRow(id, nama, harga, jumlah = 1, diskon = 0, stokAkhir = 0, originId = null) {
        const tbody = document.getElementById('sparepart-table-body');

        // Tentukan index terakhir yang belum terpakai
        const rows = tbody.querySelectorAll('tr');
        const nextIndex = rows.length;

        const row = document.createElement('tr');
        row.id = `row-${id}`;
        row.innerHTML = `
            <td>
                ${nama}
                <input type="hidden" name="produk[${nextIndex}][id]" value="${id}">
            </td>
            <td><input type="text" class="form-control harga" name="produk[${nextIndex}][harga]" value="Rp ${formatNumber(harga)}"></td>
            <td><input type="text" class="form-control" value="${formatNumber(stokAkhir)}" readonly></td>
            <td><input type="number" class="form-control qty" name="produk[${nextIndex}][jumlah]" value="${jumlah}" min="1"></td>
            <td><select class="form-select origin-batch" name="produk[${nextIndex}][purchase_detail_id]"><option value="">Asal belum diketahui</option></select><small class="text-muted">Pilih hanya jika batch fisik dapat dipastikan.</small></td>
            <td><input type="text" class="form-control diskon-item" name="produk[${nextIndex}][diskon]" value="Rp ${formatNumber(diskon)}" min="0"></td>
            <td><input type="text" class="form-control total" name="produk[${nextIndex}][total]" readonly></td>
            <td><button type="button" class="btn btn-danger btn-sm remove-row">Hapus</button></td>
        `;
        tbody.appendChild(row);
        loadPurchaseBatches(row, id, originId);
    }

    async function loadPurchaseBatches(row, itemId, originId) {
        const select = row.querySelector('.origin-batch');
        try {
            const response = await fetch('<?= base_url('service/purchase-batches') ?>/' + encodeURIComponent(itemId));
            if (!response.ok) throw new Error('Batch gagal dimuat');
            const batches = await response.json();
            batches.forEach(batch => {
                const option = document.createElement('option');
                option.value = batch.iddetail_pembelian;
                option.textContent = (batch.nama_suplier || 'Supplier tidak tercatat') + ' — ' +
                    (batch.no_nota_supplier || 'Nota tanpa nomor') + ' — ' + batch.tanggal + ' (masuk ' + batch.jumlah + ')';
                select.appendChild(option);
            });
            if (originId && !Array.from(select.options).some(option => option.value === String(originId))) {
                const option = document.createElement('option');
                option.value = originId;
                option.textContent = 'Batch tercatat #' + originId + ' (di luar 100 terbaru)';
                select.appendChild(option);
            }
            select.value = originId || '';
        } catch (error) {
            select.innerHTML = '<option value="">Asal belum diketahui (batch gagal dimuat)</option>';
            if (originId) {
                const option = document.createElement('option');
                option.value = originId;
                option.textContent = 'Pertahankan batch tercatat #' + originId;
                select.appendChild(option);
                select.value = originId;
            }
        }
    }

    // Event: hapus baris
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-row')) {
            const tr = e.target.closest('tr');
            if (tr) tr.remove();
            updateTotals();
        }
    });

    // Event: hitung ulang saat input berubah
    document.addEventListener('input', function(e) {
        if (
            e.target.classList.contains('qty') ||
            e.target.classList.contains('harga') ||
            e.target.classList.contains('diskon-item')
        ) {
            if (e.target.classList.contains('harga') || e.target.classList.contains('diskon-item')) {
                formatInputRupiah(e.target);
            }
            updateTotals();
        }
    });

    // Format number with thousand separators (e.g. 10000 -> 10.000)
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    // Format input field with Rupiah currency style on typing
    function formatInputRupiah(input) {
        let cursorPos = input.selectionStart;
        let originalLength = input.value.length;

        // Remove all non-digit characters
        let numbersOnly = input.value.replace(/[^0-9]/g, '');

        if (numbersOnly === '') {
            input.value = '';
            return;
        }

        let number = parseInt(numbersOnly, 10);

        if (isNaN(number)) {
            input.value = '';
            return;
        }

        // Format number to Rupiah string
        let formatted = 'Rp ' + number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        input.value = formatted;

        // Adjust cursor position after formatting
        let newLength = formatted.length;
        cursorPos = cursorPos + (newLength - originalLength);
        input.setSelectionRange(cursorPos, cursorPos);
    }

    // Parse formatted Rupiah string to number
    function parseRupiah(rupiahString) {
        if (!rupiahString) return 0;
        // Remove Rp and dots
        let numberString = rupiahString.replace(/[^0-9]/g, '');
        return parseInt(numberString, 10) || 0;
    }

    // Update total & diskon
    function updateTotals() {
        let totalHarga = 0;
        let totalDiskon = 0;

        document.querySelectorAll('#sparepart-table-body tr').forEach(row => {
            const harga = parseRupiah(row.querySelector('.harga')?.value) || 0;
            const qty = parseInt(row.querySelector('.qty')?.value) || 0;
            const diskon = parseRupiah(row.querySelector('.diskon-item')?.value) || 0;

            const subtotal = harga * qty;
            const total = subtotal - diskon;

            row.querySelector('.total').value = 'Rp ' + formatNumber(total);

            totalHarga += subtotal;
            totalDiskon += diskon;
        });

        document.querySelector('[name="total_harga"]').value = 'Rp ' + formatNumber(totalHarga);
        document.getElementById('diskon_sparepart').value = 'Rp ' + formatNumber(totalDiskon);
        document.getElementById('harga_akhir_sparepart').value = 'Rp ' + formatNumber(totalHarga - totalDiskon);

        updateTotalHarga();
    }

    // Optional: update total ke input pembayaran (jika ada tab selanjutnya)
    function updateTotalHarga() {
        const harga = document.getElementById('harga_akhir_sparepart').value;
        const diskon_akhir = document.getElementById('diskon_sparepart').value;
        const dpEl = document.getElementById('dp_bayar') || document.querySelector('input[name="dp_bayar"]');
        const dp = dpEl ? parseRupiah(dpEl.value) : 0;
        const harga_akhir = parseRupiah(harga) - dp;


        // Assuming you have inputs with these IDs elsewhere in your form/page:
        if (document.getElementById('total_harga_pembayaran')) {
            document.getElementById('total_harga_pembayaran').value = harga;
        }

        if (document.getElementById('diskon_pembayaran')) {
            document.getElementById('diskon_pembayaran').value = diskon_akhir;
        }
        if (document.getElementById('total_harga_pembayaran_akhir')) {
            document.getElementById('total_harga_pembayaran_akhir').value = harga_akhir;
        }
    }

    // Jika ada perubahan manual di diskon akhir
    document.getElementById('diskon_sparepart').addEventListener('input', updateTotals);
</script>

<script>
    document.getElementById('btn-previous-to-kerusakan').addEventListener('click', function() {
        var tabTrigger = new bootstrap.Tab(document.querySelector('#kerusakan-tab'));
        tabTrigger.show();
    });
</script>

<script>
    function cekGaransi(select) {
        const manualInput = document.getElementById('garansiManual');
        if (select.value === 'manual') {
            manualInput.classList.remove('d-none');
            manualInput.setAttribute("required", "required");
        } else {
            manualInput.classList.add('d-none');
            manualInput.removeAttribute("required");
        }
    }
</script>
