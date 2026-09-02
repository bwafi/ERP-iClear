<form action="<?php echo base_url('service/saveSparepart') ?>" enctype="multipart/form-data" method="post">
    <div class="mt-3">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sparepartModal">
            Tambah Sparepart
        </button>
    </div>
    <br>

    <div class="modal fade" id="sparepartModal" tabindex="-1" aria-labelledby="sparepartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pilih Sparepart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Unit:</label>
                            <input type="text" class="form-control" id="unit" value="<?= session('NAMA_UNIT') ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cari Sparepart:</label>
                            <input type="text" class="form-control" id="searchSparepartInput" placeholder="Ketik nama, kode, atau warna...">
                        </div>
                    </div>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-bordered table-hover" id="sparepartDataTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th width="40"></th>
                                    <th>Nama Sparepart</th>
                                    <th>Warna</th>
                                    <th>Nama Unit</th>
                                    <th>HPP</th>
                                    <th>Harga</th>
                                </tr>
                            </thead>
                            <tbody id="sparepart-modal-body">
                                <tr>
                                    <td colspan="6" class="text-center py-3">Ketik untuk mencari sparepart...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="add-selected-sparepart" class="btn btn-success"
                        data-bs-dismiss="modal">Tambahkan</button>
                </div>
            </div>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Nama Sparepart</th>
                <th>Harga</th>
                <th>Qty</th>
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

    <div style="display: flex; justify-content: space-between;">
        <div>
            <input hidden type="text" name="idservice_s" value="<?php echo @$idservice ?>">
            <button type="button" class="btn btn-light" id="btn-previous-to-kerusakan">Sebelumnya</button>
            <button type="submit" id="selanjutnyabtnnya" class="btn btn-success">Selanjutnya</button>
        </div>
    </div>
    <input type="text" hidden value="<?php echo @$idservice ?>" name="" id="idpelabel">
</form>

<script>
    let searchTimeout;
    const oldSpareparts = <?= json_encode($oldsparepart) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        const tbody = document.getElementById('sparepart-table-body');
        const searchInput = document.getElementById('searchSparepartInput');
        const modalBody = document.getElementById('sparepart-modal-body');

        // Load old spareparts if any
        if (oldSpareparts && oldSpareparts.length > 0) {
            oldSpareparts.forEach((sp) => {
                addSparepartRow(sp.barang_idbarang, sp.nama_barang, parseFloat(sp.harga_penjualan),
                    parseInt(sp.jumlah), parseFloat(sp.diskon_penjualan));
            });
            updateTotals();
        }

        // AJAX search sparepart
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();

            if (query.length < 2) {
                modalBody.innerHTML = '<tr><td colspan="6" class="text-center py-3">Ketik minimal 2 karakter untuk mencari...</td></tr>';
                return;
            }

            modalBody.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Mencari...</td></tr>';

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
                    if (data.length === 0) {
                        modalBody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">Tidak ada sparepart ditemukan</td></tr>';
                        return;
                    }

                    let html = '';
                    data.forEach(item => {
                        const isChecked = document.getElementById(`row-${item.idbarang}`) ? 'checked' : '';
                        html += `
                            <tr>
                                <td>
                                    <input type="checkbox" class="sparepart-check" 
                                        data-id="${item.idbarang}" 
                                        data-nama="${item.nama_barang}" 
                                        data-harga="${item.harga}"
                                        ${isChecked}>
                                </td>
                                <td>${item.nama_barang}</td>
                                <td>${item.warna || '-'}</td>
                                <td>${item.nama_unit || '-'}</td>
                                <td>Rp ${new Intl.NumberFormat('id-ID').format(item.harga_beli)}</td>
                                <td>Rp ${new Intl.NumberFormat('id-ID').format(item.harga)}</td>
                            </tr>
                        `;
                    });
                    modalBody.innerHTML = html;
                })
                .catch(err => {
                    console.error(err);
                    modalBody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-danger">Gagal memuat data</td></tr>';
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
    });

    // Tambah sparepart dari modal
    document.getElementById('add-selected-sparepart').addEventListener('click', () => {
        document.querySelectorAll('.sparepart-check:checked').forEach((checkbox) => {
            const id = checkbox.getAttribute('data-id');
            const nama = checkbox.getAttribute('data-nama');
            const harga = parseFloat(checkbox.getAttribute('data-harga'));

            // Cek apakah sparepart sudah ada di tabel
            if (!document.getElementById(`row-${id}`)) {
                addSparepartRow(id, nama, harga, 1, 0);
            }
        });

        updateTotals();
    });

    // Tambahkan baris sparepart
    function addSparepartRow(id, nama, harga, jumlah = 1, diskon = 0) {
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
            <td><input type="number" class="form-control qty" name="produk[${nextIndex}][jumlah]" value="${jumlah}" min="1"></td>
            <td><input type="text" class="form-control diskon-item" name="produk[${nextIndex}][diskon]" value="Rp ${formatNumber(diskon)}" min="0"></td>
            <td><input type="text" class="form-control total" name="produk[${nextIndex}][total]" readonly></td>
            <td><button type="button" class="btn btn-danger btn-sm remove-row">Hapus</button></td>
        `;
        tbody.appendChild(row);
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
        const dp = parseRupiah(document.getElementById('dp_bayar').value);
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