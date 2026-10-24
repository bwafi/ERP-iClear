<?= $this->include('partials/sparepart_picker_style') ?>
<?php
$tglSelesai = !empty($service->tanggal_selesai) ? date('d-m-Y', strtotime((string) $service->tanggal_selesai)) : '-';
$tglGaransi = $warranty_end ? $warranty_end->format('d-m-Y') : '-';

// Sparepart yang sudah dipakai di service ini otomatis dicatat sebagai rusak.
$rusakAwal = [];
foreach (($service_sparepart ?? []) as $item) {
    $id = (int) ($item->barang_idbarang ?? 0);
    $qty = (int) ($item->jumlah ?? 0);
    if ($id <= 0 || $qty <= 0) {
        continue;
    }
    if (!isset($rusakAwal[$id])) {
        $rusakAwal[$id] = [
            'id' => $id,
            'nama' => $item->nama_barang ?? ('Barang #' . $id),
            'qty' => 0,
        ];
    }
    $rusakAwal[$id]['qty'] += $qty;
}
?>
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Klaim Garansi</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('garansi_service') ?>">Garansi Service</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Klaim</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h5 class="mb-1"><?= esc($service->no_service ?? '-') ?></h5>
                <span class="text-muted"><?= esc($status_garansi) ?></span>
            </div>
            <a href="<?= base_url('garansi_service') ?>" class="btn btn-light">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <small class="text-muted d-block">Pelanggan</small>
                <strong><?= esc($service->nama ?? '-') ?></strong>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">No. HP</small>
                <strong><?= esc($service->no_hp ?? '-') ?></strong>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Alamat</small>
                <strong><?= esc($service->alamat ?? '-') ?></strong>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Tipe HP</small>
                <strong><?= esc($service->tipe_hp ?? '-') ?></strong>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">IMEI</small>
                <strong><?= esc($service->imei ?? '-') ?></strong>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Tanggal Selesai</small>
                <strong><?= esc($tglSelesai) ?></strong>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Garansi Sampai</small>
                <strong><?= esc($tglGaransi) ?></strong>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Keluhan</small>
                <strong><?= esc($service->keluhan ?? '-') ?></strong>
            </div>
            <div class="col-md-4">
                <small class="text-muted d-block">Keterangan</small>
                <strong><?= esc($service->keterangan ?? '-') ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden mb-4">
    <div class="card-body">
        <h5 class="mb-3">Kerusakan Service</h5>
        <?php if (!empty($oldkerusakan)): ?>
            <ul class="mb-0">
                <?php foreach ($oldkerusakan as $kerusakan): ?>
                    <li>
                        <?= esc($fungsiMap[(int) ($kerusakan->fungsi_idfungsi ?? 0)] ?? 'Fungsi #' . ($kerusakan->fungsi_idfungsi ?? '-')) ?>
                        <?php if (!empty($kerusakan->keterangan)): ?>
                            <span class="text-muted">- <?= esc($kerusakan->keterangan) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-muted mb-0">Tidak ada data kerusakan.</p>
        <?php endif; ?>
    </div>
</div>

<form action="<?= base_url('claim_garansi') ?>" method="post" id="form-claim-garansi">
    <?= csrf_field() ?>
    <input type="hidden" name="idservice" value="<?= (int) $service->idservice ?>">

    <div class="card w-100 position-relative overflow-hidden mb-4">
        <div class="card-body">
            <label for="catatan" class="form-label fw-semibold">Catatan Klaim <span class="text-danger">*</span></label>
            <textarea name="catatan" id="catatan" class="form-control" rows="3" required
                placeholder="Contoh: LCD pecah karena terbentur"><?= esc(old('catatan')) ?></textarea>
            <small class="text-muted">Wajib diisi, jelaskan alasan klaim garansi.</small>
        </div>
    </div>

    <div class="card w-100 position-relative overflow-hidden mb-4">
        <div class="card-body">
            <div class="mb-3">
                <h5 class="mb-1">Sparepart Rusak</h5>
                <p class="text-muted mb-0">Pilih sparepart service yang diklaim; dicatat untuk laporan, tidak mengurangi stok.</p>
            </div>

            <div class="table-responsive">
                <table class="table border align-middle mb-0">
                    <thead class="text-dark fs-4">
                        <tr>
                            <th style="width: 70px;" class="text-center">Klaim</th>
                            <th>Nama Sparepart</th>
                            <th style="width: 120px;">Qty</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody id="damagedRows">
                        <?php $damagedIndex = 0; ?>
                        <?php foreach ($rusakAwal as $item): ?>
                            <tr id="damaged-row-<?= $item['id'] ?>">
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input damaged-select"
                                        name="barang_rusak[<?= $damagedIndex ?>][id]" value="<?= $item['id'] ?>">
                                </td>
                                <td><?= esc($item['nama']) ?></td>
                                <td>
                                    <?= $item['qty'] ?>
                                    <input type="hidden" name="barang_rusak[<?= $damagedIndex ?>][qty]" value="<?= $item['qty'] ?>">
                                </td>
                                <td>
                                    <textarea class="form-control form-control-sm"
                                        name="barang_rusak[<?= $damagedIndex ?>][keterangan]" rows="1"
                                        placeholder="Keterangan kerusakan"></textarea>
                                </td>
                            </tr>
                            <?php $damagedIndex++; ?>
                        <?php endforeach; ?>
                        <?php if (empty($rusakAwal)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">Tidak ada sparepart di service ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card w-100 position-relative overflow-hidden mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h5 class="mb-1">Sparepart Pengganti</h5>
                    <p class="text-muted mb-0">Stok berkurang; harga klaim Rp 0. Pilih HPP asli, atau HPP 0 jika sparepart bisa diretur ke supplier.</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                    data-bs-target="#replacementPartModal">Tambah Sparepart</button>
            </div>

            <div class="table-responsive">
                <table class="table border align-middle mb-0">
                    <thead class="text-dark fs-4">
                        <tr>
                            <th>Nama Sparepart</th>
                            <th>Stok</th>
                            <th>HPP</th>
                            <th>Harga</th>
                            <th style="width: 120px;">Qty</th>
                            <th style="width: 90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="replacementRows"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="<?= base_url('garansi_service') ?>" class="btn btn-light">Batal</a>
        <button type="submit" class="btn btn-success">Simpan Klaim</button>
    </div>
</form>

<div class="modal fade" id="replacementPartModal" tabindex="-1" aria-labelledby="replacementPartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <span class="bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center"
                        style="width: 36px; height: 36px;">
                        <i class="bi bi-box-seam fs-5"></i>
                    </span>
                    <div>
                        <h5 class="modal-title" id="replacementPartModalLabel">Pilih Sparepart Pengganti</h5>
                        <p class="text-muted fs-3 mb-0">Cari nama atau kode, lalu centang yang dipakai.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="service-sp-search flex-grow-1">
                        <i class="bi bi-search service-sp-search-icon"></i>
                        <input type="text" class="form-control" id="searchReplacementInput"
                            placeholder="Cari nama atau kode sparepart..." autocomplete="off">
                    </div>
                    <span class="service-sp-unit badge text-bg-light border d-none d-sm-inline-flex">
                        <i class="bi bi-shop me-1"></i>Unit <?= (int) $service->unit_idunit ?>
                    </span>
                </div>
                <div class="service-sp-list" id="replacementPartList" aria-live="polite">
                    <?php if (empty($sparepart)): ?>
                        <div class="service-sp-state">
                            <i class="bi bi-inbox"></i>
                            <p>Tidak ada sparepart dengan stok tersedia di unit ini.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($sparepart as $s): ?>
                            <label class="service-sp-item replacement-option">
                                <input type="checkbox" class="service-sp-item__input replacement-check"
                                    data-id="<?= (int) $s->idbarang ?>"
                                    data-nama="<?= esc($s->nama_barang, 'attr') ?>"
                                    data-stok="<?= (int) ($s->stok_akhir ?? 0) ?>"
                                    data-hpp="<?= (int) ($s->hpp ?? 0) ?>">
                                <span class="service-sp-item__box">
                                    <span class="service-sp-tick" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                                    <span class="service-sp-item__main">
                                        <span class="service-sp-name"><?= esc($s->nama_barang) ?></span>
                                        <span class="service-sp-meta">
                                            <span class="service-sp-code"><?= esc($s->kode_barang ?? '-') ?></span>
                                            <?php if (!empty($s->nama_suplier)): ?>
                                                <span class="service-sp-meta-item"><i class="bi bi-truck"></i><?= esc($s->nama_suplier) ?></span>
                                            <?php endif; ?>
                                            <span class="service-sp-meta-item">
                                                <i class="bi bi-tag"></i>HPP Rp <?= number_format((float) ($s->hpp ?? 0), 0, ',', '.') ?>
                                            </span>
                                            <span class="service-sp-meta-item"><i class="bi bi-gift"></i>Gratis</span>
                                        </span>
                                    </span>
                                    <span class="service-sp-side">
                                        <span class="service-sp-price">Rp <?= number_format((float) ($s->harga ?? 0), 0, ',', '.') ?></span>
                                        <span class="service-sp-stock<?= (int) ($s->stok_akhir ?? 0) <= 2 ? ' is-low' : '' ?>">
                                            Stok <?= (int) ($s->stok_akhir ?? 0) ?>
                                        </span>
                                    </span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="service-sp-count" id="replacement-selected-count" aria-live="polite">Belum ada yang dipilih</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" id="addSelectedReplacement"
                        class="btn btn-primary d-inline-flex align-items-center gap-2" data-bs-dismiss="modal" disabled>
                        <i class="bi bi-plus-lg"></i> Tambahkan
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        let replacementIndex = 0;
        const replacementRows = document.getElementById('replacementRows');
        const replacementCount = document.getElementById('replacement-selected-count');
        const addReplacementButton = document.getElementById('addSelectedReplacement');
        const replacementSearch = document.getElementById('searchReplacementInput');
        const replacementList = document.getElementById('replacementPartList');

        function escapeHtml(value) {
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

        function updateReplacementSelection() {
            const count = document.querySelectorAll('.replacement-check:checked').length;
            replacementCount.textContent = count > 0 ? count + ' sparepart dipilih' : 'Belum ada yang dipilih';
            addReplacementButton.disabled = count === 0;
        }

        replacementSearch.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            replacementList.querySelectorAll('.replacement-option').forEach(function(item) {
                item.classList.toggle('d-none', query !== '' && !item.textContent.toLowerCase().includes(query));
            });
        });

        document.addEventListener('change', function(event) {
            if (event.target.classList.contains('replacement-check')) {
                updateReplacementSelection();
            }
        });

        document.getElementById('replacementPartModal').addEventListener('shown.bs.modal', function() {
            replacementSearch.focus();
        });

        function addReplacementRow(id, nama, stok, hpp) {
            if (document.getElementById('replacement-row-' + id)) {
                return;
            }

            const index = replacementIndex++;
            const hppLabel = Number(hpp || 0).toLocaleString('id-ID');
            const row = document.createElement('tr');
            row.id = 'replacement-row-' + id;
            row.innerHTML = `
                <td>${escapeHtml(nama)}<input type="hidden" name="produk[${index}][id]" value="${id}"></td>
                <td>${stok}</td>
                <td>
                    <select class="form-select form-select-sm" name="produk[${index}][hpp_mode]">
                        <option value="asli">HPP asli (Rp ${hppLabel})</option>
                        <option value="nol">HPP 0</option>
                    </select>
                </td>
                <td>Rp 0</td>
                <td><input type="number" class="form-control form-control-sm replacement-qty" name="produk[${index}][qty]" value="1" min="1" max="${stok}" data-max="${stok}" required></td>
                <td><button type="button" class="btn btn-sm btn-danger remove-replacement">Hapus</button></td>
            `;
            replacementRows.appendChild(row);
        }

        document.getElementById('addSelectedReplacement').addEventListener('click', function() {
            document.querySelectorAll('.replacement-check:checked').forEach(function(checkbox) {
                addReplacementRow(
                    checkbox.dataset.id,
                    checkbox.dataset.nama,
                    checkbox.dataset.stok,
                    checkbox.dataset.hpp
                );
                checkbox.checked = false;
            });
            updateReplacementSelection();
        });

        document.addEventListener('click', function(event) {
            const replacementButton = event.target.closest('.remove-replacement');
            if (replacementButton) {
                replacementButton.closest('tr').remove();
            }
        });

        document.addEventListener('input', function(event) {
            if (!event.target.classList.contains('replacement-qty')) {
                return;
            }

            const max = parseInt(event.target.dataset.max || '0', 10);
            let value = parseInt(event.target.value || '1', 10);

            if (isNaN(value) || value < 1) {
                value = 1;
            }
            if (value > max) {
                value = max;
            }

            event.target.value = value;
        });

        updateReplacementSelection();
    })();
</script>
