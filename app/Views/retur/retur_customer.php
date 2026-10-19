<div class="card shadow-none mb-4"><div class="card-body">
    <h4 class="fw-semibold mb-3">Retur Penjualan</h4>
    <form method="get" action="<?= base_url('retur_customer') ?>" class="row g-2 align-items-end">
        <div class="col-md-5"><label for="invoiceFilter" class="form-label">Nomor invoice</label><input id="invoiceFilter" name="invoice" class="form-control" value="<?= esc($invoiceFilter) ?>" placeholder="Cari nomor invoice"></div>
        <div class="col-md-4"><label for="unitFilter" class="form-label">Unit</label><select id="unitFilter" name="unit" class="form-select"><option value="">Semua Unit</option>
            <?php foreach ($units as $unit): ?><option value="<?= (int) $unit['idunit'] ?>" <?= (int) $unitFilter === (int) $unit['idunit'] ? 'selected' : '' ?>><?= esc($unit['NAMA_UNIT']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-3"><button class="btn btn-primary" type="submit">Cari</button> <a class="btn btn-outline-secondary" href="<?= base_url('retur_customer') ?>">Reset</a></div>
    </form>
</div></div>

<div class="card shadow-none"><div class="card-body">
    <p class="text-muted">Maksimal 50 invoice per halaman. Detail barang dimuat saat dibuka.</p>
    <div class="table-responsive"><table class="table table-striped align-middle">
        <thead><tr><th>Invoice</th><th>Tanggal</th><th>Unit</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($invoices as $invoice): ?>
            <tr><td><?= esc($invoice['kode_invoice']) ?></td><td><?= esc(date('d-m-Y', strtotime($invoice['tanggal']))) ?></td><td><?= esc($invoice['NAMA_UNIT']) ?></td>
                <td><button type="button" class="btn btn-sm btn-primary buka-detail" data-id="<?= (int) $invoice['idpenjualan'] ?>">Lihat Detail</button></td></tr>
        <?php endforeach; ?>
        <?php if ($invoices === []): ?><tr><td colspan="4">Invoice tidak ditemukan.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php if ($hasMore): ?><a class="btn btn-outline-primary" href="<?= base_url('retur_customer') . '?' . http_build_query(['invoice' => $invoiceFilter, 'unit' => $unitFilter, 'before' => end($invoices)['idpenjualan']]) ?>">Invoice lebih lama</a><?php endif; ?>
</div></div>

<div class="modal fade" id="modalRetur" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="modalReturTitle">Detail Penjualan</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body"><div id="modalReturError" class="alert alert-danger d-none"></div>
        <form method="post" action="<?= base_url('insert_retur_customer') ?>">
            <div class="mb-3"><label for="tanggalRetur" class="form-label">Tanggal Pengembalian</label>
                <input id="tanggalRetur" name="tanggal_pengembalian" type="date" class="form-control" value="<?= (int) session('ID_UNIT') === 1 ? '' : date('Y-m-d') ?>" <?= (int) session('ID_UNIT') === 1 ? '' : 'readonly' ?> required></div>
            <div class="table-responsive"><table class="table table-bordered align-middle"><thead><tr><th>Pilih</th><th>Nama Barang</th><th>Jumlah</th><th>Harga Jual</th><th>Diskon</th><th>Subtotal</th><th>Jumlah Retur</th><th>Satuan</th></tr></thead><tbody id="modalReturRows"></tbody></table></div>
            <button id="simpanRetur" type="submit" class="btn btn-success" disabled>Simpan Retur</button>
        </form>
    </div>
</div></div></div>

<script>
document.querySelectorAll('.buka-detail').forEach(button => {
    button.addEventListener('click', async () => {
        const modal = new bootstrap.Modal(document.getElementById('modalRetur'));
        const rows = document.getElementById('modalReturRows');
        const error = document.getElementById('modalReturError');
        const submit = document.getElementById('simpanRetur');
        rows.replaceChildren();
        error.classList.add('d-none');
        submit.disabled = true;
        document.getElementById('modalReturTitle').textContent = 'Memuat detail invoice...';
        modal.show();
        try {
            const response = await fetch('<?= base_url('retur_customer/invoice') ?>/' + encodeURIComponent(button.dataset.id), {
                headers: { 'Accept': 'application/json' }, credentials: 'same-origin'
            });
            if (!response.ok) throw new Error('Detail invoice tidak dapat dimuat. Coba lagi.');
            const data = await response.json();
            document.getElementById('modalReturTitle').textContent = 'Detail Penjualan - Invoice ' + data.invoice.kode_invoice;
            data.details.forEach((item, index) => {
                const remaining = Number(item.jumlah) - Number(item.jumlah_diretur);
                if (remaining <= 0) return;
                const row = rows.insertRow();
                const choose = document.createElement('input');
                choose.type = 'checkbox';
                choose.className = 'form-check-input';
                choose.name = `items[${index}][selected]`;
                choose.value = '1';
                row.insertCell().append(choose);
                const id = document.createElement('input');
                id.type = 'hidden';
                id.name = `items[${index}][iddetail_penjualan]`;
                id.value = item.iddetail_penjualan;
                row.cells[0].append(id);
                [item.nama_barang, remaining, item.harga_penjualan, item.diskon_penjualan, item.sub_total].forEach(value => row.insertCell().textContent = value ?? '-');
                const quantity = document.createElement('input');
                quantity.type = 'number';
                quantity.className = 'form-control';
                quantity.name = `items[${index}][jumlah_retur]`;
                quantity.min = '1';
                quantity.max = String(remaining);
                quantity.disabled = true;
                row.insertCell().append(quantity);
                row.insertCell().textContent = item.satuan_jual ?? '-';
                choose.addEventListener('change', () => {
                    quantity.disabled = !choose.checked;
                    quantity.required = choose.checked;
                    if (!choose.checked) quantity.value = '';
                    submit.disabled = !rows.querySelector('input[type="checkbox"]:checked');
                });
            });
            if (rows.children.length === 0) {
                error.textContent = 'Semua barang pada invoice ini sudah diretur.';
                error.classList.remove('d-none');
            }
        } catch (failure) {
            error.textContent = failure.message;
            error.classList.remove('d-none');
        }
    });
});
</script>
