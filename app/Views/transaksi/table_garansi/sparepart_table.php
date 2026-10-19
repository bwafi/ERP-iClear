<form action="<?php echo base_url('update_service_garansi/saveSparepart') ?>" enctype="multipart/form-data" method="post">
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
                    <table class="table table-bordered" id="sparepartDataTable">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Nama Sparepart</th>
                                <th>Harga</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sparepart as $s) : ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="sparepart-check" data-id="<?= esc($s->idbarang) ?>"
                                            data-nama="<?= esc($s->nama_barang) ?>" data-harga="<?= esc($s->harga) ?>">
                                    </td>
                                    <td><?= esc($s->nama_barang) ?></td>
                                    <td><?= 'Rp ' . number_format($s->harga, 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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

                <th>Qty Tambahan</th>
                <th>Diskon</th>
                <th>Total</th>
                <th hidden>Qty</th>

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
        <label class="form-label">Total Diskon</label>
        <input type="text" class="form-control" name="diskon" id="diskon_sparepart" value="Rp 0" readonly>
    </div>
    <div class="mb-3">
        <label class="form-label">Biaya Tambahan Garansi</label>
        <input type="text" class="form-control" id="biaya_garansi" name="biaya_garansi" value="Rp <?php echo @$old_service_pelanggan->biaya_tambahan_garansi ?>">
    </div>



    <div class="mb-4">
        <label class="form-label">Harga Akhir</label>
        <input type="text" class="form-control" id="harga_akhir_sparepart" name="harga_akhir" value="Rp 0" readonly>
    </div>

    <div style="display: flex; justify-content: space-between;">
        <div>
            <input hidden type="text" name="idservice_s" value="<?php echo @$idservice ?>">
            <button type="button" class="btn btn-light" id="btn-previous-to-kerusakan">Sebelumnya</button>
            <button type="submit" class="btn btn-success">Selanjutnya</button>
        </div>
    </div>
</form>

<script>
    $(document).ready(function() {
        $('#sparepartDataTable').DataTable();
    });

    const oldSpareparts = <?= json_encode($oldsparepart) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        const tbody = document.getElementById('sparepart-table-body');

        oldSpareparts.forEach((sp) => {
            addSparepartRow(sp.barang_idbarang, sp.nama_barang, parseFloat(sp.harga_penjualan),
                parseInt(sp.jumlah), parseFloat(sp.diskon_penjualan), parseFloat(sp.diskon_penjualan_garansi), parseInt(sp.jumlah_tambahan_garansi), parseInt(sp.sub_total), parseInt(sp.sub_total_garansi), parseInt(sp.harga_penjualan_garansi),
                sp.source_nama_suplier, sp.source_nota_supplier);

            console.log({
                id: sp.idservice_sparepart,
                barang_id: sp.barang_idbarang,
                sub_total: sp.sub_total,
                sub_total_garansi: sp.sub_total_garansi
            });


            // Centang checkbox lama
            const checkbox = document.querySelector(
                `.sparepart-check[data-id="${sp.barang_idbarang}"]`);
            if (checkbox) checkbox.checked = true;
        });

        updateTotals();
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

    function addSparepartRow(
        id,
        nama,
        harga,
        jumlah = 1,
        diskon = 0,
        diskon_penjualan_garansi = 0,
        jumlah_tambahan_garansi = 0,
        sub_total = 0,
        sub_total_garansi = 0,
        harga_penjualan_garansi = 0,
        sourceSupplier = null,
        sourceNota = null,
    ) {

        const tbody = document.getElementById('sparepart-table-body');
        const rows = tbody.querySelectorAll('tr');
        const nextIndex = rows.length;
        const jumlahAcuan = Math.max(0, jumlah - jumlah_tambahan_garansi);

        const subTotalAcuan = sub_total - sub_total_garansi;
        const DiskonAcuan = Math.max(0, diskon - diskon_penjualan_garansi);





        const row = document.createElement('tr');
        row.id = `row-${id}`;
        row.innerHTML = `
        <td>
            ${nama}
            <div class="small text-muted source-batch-label"></div>
            <input type="hidden" name="produk[${nextIndex}][id]" value="${id}">
            <input hidden type="number" class="form-control jumlah_acuan" name="produk[${nextIndex}][jumlah_acuan]" value="${jumlahAcuan}" readonly>
        <input hidden type="number" class="form-control sub_total_acuan" name="produk[${nextIndex}][sub_total_acuan]" value="${subTotalAcuan}" readonly>
        <input hidden type="number" class="form-control diskon_acuan" name="produk[${nextIndex}][diskon_acuan]" value="${DiskonAcuan}" readonly>
        </td>

        <td>
  <input type="text" class="form-control harga" 
         name="produk[${nextIndex}][harga]" 
         value="Rp ${formatNumber(jumlah_tambahan_garansi > 0 ? harga_penjualan_garansi : harga)}">
</td>

        <td hidden>
            <input type="number" class="form-control qty" hidden name="produk[${nextIndex}][jumlah]" value="${jumlahAcuan}" readonly>
        </td>
        <td>
    <input type="number" class="form-control qty-tambahan mb-1" name="produk[${nextIndex}][qty_tambahan]" value="${jumlah_tambahan_garansi}" min="0" placeholder="Qty Tambahan">
    <select class="form-select replacement-batch" name="produk[${nextIndex}][purchase_detail_id]"><option value="">Asal pengganti belum diketahui</option></select>
    <div class="form-check mt-1">
        <input class="form-check-input gratiskan-checkbox" type="checkbox" id="gratiskan-${id}" data-row-id="${id}">
        <label class="form-check-label" for="gratiskan-${id}">Gratiskan</label>
    </div>
</td>

        <td><input type="text" class="form-control diskon-item" name="produk[${nextIndex}][diskon_penjualan_garansi]" value="Rp ${formatNumber(diskon_penjualan_garansi)}" min="0"></td>
        <td><input type="text" class="form-control total" name="produk[${nextIndex}][total]" readonly></td>
    `;
        tbody.appendChild(row);
        fetch('<?= base_url('service/purchase-batches') ?>/' + encodeURIComponent(id))
            .then(response => response.ok ? response.json() : [])
            .then(batches => {
                const selector = row.querySelector('.replacement-batch');
                batches.forEach(batch => {
                    const option = document.createElement('option');
                    option.value = batch.iddetail_pembelian;
                    option.textContent = (batch.nama_suplier || 'Supplier tidak tercatat') + ' — ' +
                        (batch.no_nota_supplier || 'Nota tanpa nomor') + ' (sisa ' + batch.available_quantity + ')';
                    selector.appendChild(option);
                });
            });
        row.querySelector('.source-batch-label').textContent = sourceSupplier
            ? 'Batch dipilih saat service: ' + sourceSupplier + (sourceNota ? ' — ' + sourceNota : '')
            : 'Asal supplier service belum diketahui';
    }


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

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('gratiskan-checkbox')) {
            const checkbox = e.target;
            const rowId = checkbox.getAttribute('data-row-id');
            const row = document.getElementById(`row-${rowId}`);

            const hargaInput = row.querySelector('.harga');

            if (checkbox.checked) {
                hargaInput.setAttribute('data-old-harga', hargaInput.value); // Simpan harga lama
                hargaInput.value = 'Rp 0';
                // hargaInput.setAttribute('readonly', true);
            } else {
                const oldHarga = hargaInput.getAttribute('data-old-harga') || 'Rp 0';
                hargaInput.value = oldHarga;
                hargaInput.removeAttribute('readonly');
            }

            updateTotals();
        }
    });



    document.addEventListener('input', function(e) {
        if (
            e.target.classList.contains('harga') ||
            e.target.classList.contains('diskon-item') ||
            e.target.classList.contains('qty-tambahan')
        ) {
            if (e.target.classList.contains('harga') || e.target.classList.contains('diskon-item')) {
                formatInputRupiah(e.target);
            }
            updateTotals();
        }
    });


    // Format input biaya garansi saat diketik
    document.getElementById('biaya_garansi').addEventListener('input', function(e) {
        formatInputRupiah(e.target);
        updateTotals();
    });




    // Update total & diskon
    function updateTotals() {
        let totalHarga = 0;
        let totalDiskon = 0;

        document.querySelectorAll('#sparepart-table-body tr').forEach(row => {
            const harga = parseRupiah(row.querySelector('.harga')?.value) || 0;
            const qtyTambahan = parseInt(row.querySelector('.qty-tambahan')?.value) || 0;
            const diskon = parseRupiah(row.querySelector('.diskon-item')?.value) || 0;

            const subtotal = harga * qtyTambahan;
            const total = subtotal - diskon;

            row.querySelector('.total').value = 'Rp ' + formatNumber(total);

            totalHarga += subtotal;
            totalDiskon += diskon;
        });

        const biayaGaransi = parseRupiah(document.getElementById('biaya_garansi')?.value) || 0;

        document.querySelector('[name="total_harga"]').value = 'Rp ' + formatNumber(totalHarga);
        document.getElementById('diskon_sparepart').value = 'Rp ' + formatNumber(totalDiskon);
        document.getElementById('harga_akhir_sparepart').value = 'Rp ' + formatNumber(totalHarga - totalDiskon + biayaGaransi);

        updateTotalHarga(); // jika ada
    }



    // Optional: update total ke input pembayaran (jika ada tab selanjutnya)
    function updateTotalHarga() {
        const harga = document.getElementById('harga_akhir_sparepart').value;
        const diskon_akhir = document.getElementById('diskon_sparepart').value;
        const biaya_tambahan = document.getElementById('biaya_garansi').value;

        const harga_akhir = parseRupiah(harga);

        const biaya_tambahanint = parseRupiah(biaya_tambahan);
        const harga_mula = harga_akhir - biaya_tambahanint;



        // Assuming you have inputs with these IDs elsewhere in your form/page:
        if (document.getElementById('total_harga_pembayaran')) {
            document.getElementById('total_harga_pembayaran').value = harga_mula;
        }

        if (document.getElementById('diskon_pembayaran')) {
            document.getElementById('diskon_pembayaran').value = diskon_akhir;
        }
        if (document.getElementById('total_harga_pembayaran_akhir')) {
            document.getElementById('total_harga_pembayaran_akhir').value = harga_akhir;
        }
        if (document.getElementById('biaya_garansi')) {
            document.getElementById('biaya_garansi_pembayaran').value = biaya_tambahanint;
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
