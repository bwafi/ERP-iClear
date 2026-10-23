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
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h5 class="mb-1">Sparepart Pengganti</h5>
                    <p class="text-muted mb-0">Gratis (Rp 0), stok berkurang, HPP tetap tercatat.</p>
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
                            <th style="width: 120px;">Qty</th>
                            <th style="width: 90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="replacementRows"></tbody>
                </table>
            </div>
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
                                    <input type="number" class="form-control form-control-sm damaged-qty"
                                        name="barang_rusak[<?= $damagedIndex ?>][qty]" value="<?= $item['qty'] ?>" min="1">
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

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="<?= base_url('garansi_service') ?>" class="btn btn-light">Batal</a>
        <button type="submit" class="btn btn-success">Simpan Klaim</button>
    </div>
</form>

<div class="modal fade" id="replacementPartModal" tabindex="-1" aria-labelledby="replacementPartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="replacementPartModalLabel">Pilih Sparepart Pengganti</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="replacementPartTable">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Nama Sparepart</th>
                                <th>Stok</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sparepart as $s): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="replacement-check"
                                            data-id="<?= (int) $s->idbarang ?>"
                                            data-nama="<?= esc($s->nama_barang) ?>"
                                            data-stok="<?= (int) ($s->stok_akhir ?? 0) ?>">
                                    </td>
                                    <td><?= esc($s->nama_barang) ?></td>
                                    <td><?= (int) ($s->stok_akhir ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" id="addSelectedReplacement">Tambahkan</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        let replacementIndex = 0;
        const replacementRows = document.getElementById('replacementRows');

        function addReplacementRow(id, nama, stok) {
            if (document.getElementById('replacement-row-' + id)) {
                return;
            }

            const index = replacementIndex++;
            const row = document.createElement('tr');
            row.id = 'replacement-row-' + id;
            row.innerHTML = `
                <td>${nama}<input type="hidden" name="produk[${index}][id]" value="${id}"></td>
                <td>${stok}</td>
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
                    checkbox.dataset.stok
                );
                checkbox.checked = false;
            });
        });

        document.querySelectorAll('.damaged-select').forEach(function(checkbox) {
            const qty = checkbox.closest('tr').querySelector('.damaged-qty');
            const sync = function() {
                qty.required = checkbox.checked;
            };

            checkbox.addEventListener('change', sync);
            sync();
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

        $(document).ready(function() {
            $('#replacementPartTable').DataTable({
                pageLength: 5,
                lengthChange: false
            });
        });
    })();
</script>
