<?= $this->include('transaksi/table_garansi/_claim_theme') ?>
<?= $this->include('partials/sparepart_picker_style') ?>
<?php
$tglSelesai = !empty($service->tanggal_selesai) ? date('d-m-Y', strtotime((string) $service->tanggal_selesai)) : '-';
$tglGaransi = $warranty_end ? $warranty_end->format('d-m-Y') : '-';

$namaPelanggan = trim((string) ($service->nama ?? '')) ?: '-';
$noHp = trim((string) ($service->no_hp ?? '')) ?: '-';
$lokasi = implode(', ', array_filter([
    trim((string) ($service->kecamatan ?? '')),
    trim((string) ($service->kabupaten ?? '')),
]));
$unitLabel = trim((string) ($unit_label ?? '')) ?: ('Unit ' . (int) ($service->unit_idunit ?? 0));
$inisial = mb_strtoupper(mb_substr($namaPelanggan, 0, 1));
$jumlahKerusakan = count($oldkerusakan ?? []);

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
<div class="cg-page">
    <header class="cg-hero">
        <div class="cg-hero__top">
            <nav aria-label="breadcrumb">
                <ol class="cg-crumb">
                    <li><a href="<?= base_url('garansi_service') ?>">Garansi Service</a></li>
                    <li aria-hidden="true">/</li>
                    <li aria-current="page">Klaim Garansi</li>
                </ol>
            </nav>
            <a href="<?= base_url('garansi_service') ?>" class="cg-back">
                <iconify-icon icon="solar:arrow-left-linear" width="16" height="16"></iconify-icon>
                Kembali
            </a>
        </div>

        <span class="cg-eyebrow">Garansi Service</span>
        <h1 class="cg-title">Klaim Garansi</h1>
        <div class="cg-chips">
            <span class="cg-chip cg-chip--code">
                <iconify-icon icon="solar:hashtag-linear" width="15" height="15"></iconify-icon>
                <?= esc($service->no_service ?? '-') ?>
            </span>
            <span class="cg-status" title="<?= esc($status_garansi) ?>">Garansi aktif</span>
            <span class="cg-chip">
                <iconify-icon icon="solar:calendar-mark-linear" width="15" height="15"></iconify-icon>
                Garansi s.d. <?= esc($tglGaransi) ?>
            </span>
            <span class="cg-chip">
                <iconify-icon icon="solar:shop-2-linear" width="15" height="15"></iconify-icon>
                <?= esc($unitLabel) ?>
            </span>
        </div>
    </header>

    <section class="cg-card" aria-labelledby="cg-service-title">
        <div class="cg-card__head">
            <div>
                <h2 class="cg-card__title" id="cg-service-title">Data Service</h2>
                <p class="cg-card__sub">Periksa perangkat dan pelanggan sebelum mencatat klaim.</p>
            </div>
        </div>

        <div class="cg-person">
            <span class="cg-avatar" aria-hidden="true"><?= esc($inisial) ?></span>
            <div>
                <span class="cg-person__name"><?= esc($namaPelanggan) ?></span>
                <span class="cg-person__sub">
                    <span>
                        <iconify-icon icon="solar:phone-linear" width="14" height="14"></iconify-icon>
                        <?= esc($noHp) ?>
                    </span>
                </span>
            </div>
            <span class="cg-person__loc">
                <iconify-icon icon="solar:map-point-linear" width="14" height="14"></iconify-icon>
                <?= esc($lokasi !== '' ? $lokasi : '-') ?>
            </span>
        </div>

        <dl class="cg-grid">
            <div>
                <dt>Tipe HP</dt>
                <dd><?= esc($service->tipe_hp ?? '-') ?></dd>
            </div>
            <div>
                <dt>IMEI</dt>
                <dd><?= esc($service->imei ?? '-') ?></dd>
            </div>
            <div>
                <dt>Tanggal Selesai</dt>
                <dd><?= esc($tglSelesai) ?></dd>
            </div>
            <div>
                <dt>Garansi Sampai</dt>
                <dd><?= esc($tglGaransi) ?></dd>
            </div>
        </dl>

        <div class="cg-notes">
            <div class="cg-note">
                <span>Keluhan</span>
                <p><?= esc($service->keluhan ?? '-') ?></p>
            </div>
            <div class="cg-note">
                <span>Keterangan</span>
                <p><?= esc($service->keterangan ?? '-') ?></p>
            </div>
        </div>
    </section>

    <section class="cg-card" aria-labelledby="cg-damage-title">
        <div class="cg-card__head">
            <div>
                <h2 class="cg-card__title" id="cg-damage-title">Kerusakan Service</h2>
                <p class="cg-card__sub">Temuan awal dari teknisi saat service diterima.</p>
            </div>
            <span class="cg-count"><?= $jumlahKerusakan ?> temuan</span>
        </div>

        <?php if (!empty($oldkerusakan)): ?>
            <ul class="cg-damage-list">
                <?php foreach ($oldkerusakan as $kerusakan): ?>
                    <li class="cg-damage-item">
                        <iconify-icon icon="solar:danger-triangle-line-duotone" width="17" height="17"></iconify-icon>
                        <div>
                            <strong><?= esc($fungsiMap[(int) ($kerusakan->fungsi_idfungsi ?? 0)] ?? 'Fungsi #' . ($kerusakan->fungsi_idfungsi ?? '-')) ?></strong>
                            <?php if (!empty($kerusakan->keterangan)): ?>
                                <span><?= esc($kerusakan->keterangan) ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="cg-empty mb-0">Tidak ada data kerusakan.</p>
        <?php endif; ?>
    </section>

    <form action="<?= base_url('claim_garansi') ?>" method="post" id="form-claim-garansi">
        <?= csrf_field() ?>
        <input type="hidden" name="idservice" value="<?= (int) $service->idservice ?>">

        <section class="cg-card" aria-labelledby="cg-note-title">
            <div class="cg-card__head">
                <div class="cg-step__main">
                    <span class="cg-step__num" aria-hidden="true">1</span>
                    <div class="cg-step__body">
                        <h2 class="cg-card__title" id="cg-note-title">Catatan Klaim</h2>
                        <p class="cg-card__sub">Jelaskan alasan klaim supaya riwayat garansi mudah ditelusuri.</p>
                    </div>
                </div>
                <span class="cg-req">Wajib</span>
            </div>
            <label for="catatan" class="visually-hidden">Catatan klaim</label>
            <textarea name="catatan" id="catatan" class="form-control" rows="3" required
                placeholder="Contoh: LCD pecah karena terbentur"><?= esc(old('catatan')) ?></textarea>
            <p class="cg-hint">
                <iconify-icon icon="solar:info-circle-linear" width="15" height="15"></iconify-icon>
                Wajib diisi, jelaskan alasan klaim garansi.
            </p>
        </section>

        <section class="cg-card" aria-labelledby="cg-damaged-title">
            <div class="cg-card__head">
                <div class="cg-step__main">
                    <span class="cg-step__num" aria-hidden="true">2</span>
                    <div class="cg-step__body">
                        <h2 class="cg-card__title" id="cg-damaged-title">Sparepart Rusak</h2>
                        <p class="cg-card__sub">Pilih sparepart service yang diklaim; dicatat untuk laporan, tidak mengurangi stok.</p>
                    </div>
                </div>
                <span class="cg-count" id="damagedSelectedCount">0 dipilih</span>
            </div>

            <div class="table-responsive">
                <table class="cg-table cg-table--damaged">
                    <thead>
                        <tr>
                            <th style="width: 70px;" class="text-center">Klaim</th>
                            <th>Nama Sparepart</th>
                            <th style="width: 110px;">Qty</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody id="damagedRows">
                        <?php $damagedIndex = 0; ?>
                        <?php foreach ($rusakAwal as $item): ?>
                            <tr id="damaged-row-<?= $item['id'] ?>">
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input damaged-select"
                                        name="barang_rusak[<?= $damagedIndex ?>][id]" value="<?= $item['id'] ?>"
                                        aria-label="Klaim <?= esc($item['nama'], 'attr') ?>">
                                </td>
                                <td><?= esc($item['nama']) ?></td>
                                <td>
                                    <span class="cg-qty"><?= $item['qty'] ?></span>
                                    <input type="hidden" name="barang_rusak[<?= $damagedIndex ?>][qty]" value="<?= $item['qty'] ?>">
                                </td>
                                <td>
                                    <textarea class="form-control form-control-sm"
                                        name="barang_rusak[<?= $damagedIndex ?>][keterangan]" rows="1"
                                        placeholder="Keterangan kerusakan"
                                        aria-label="Keterangan kerusakan <?= esc($item['nama'], 'attr') ?>"></textarea>
                                </td>
                            </tr>
                            <?php $damagedIndex++; ?>
                        <?php endforeach; ?>
                        <?php if (empty($rusakAwal)): ?>
                            <tr class="cg-empty-row">
                                <td colspan="4" class="cg-empty">Tidak ada sparepart di service ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="cg-card" aria-labelledby="cg-replacement-title">
            <div class="cg-card__head">
                <div class="cg-step__main">
                    <span class="cg-step__num" aria-hidden="true">3</span>
                    <div class="cg-step__body">
                        <h2 class="cg-card__title" id="cg-replacement-title">Sparepart Pengganti</h2>
                        <p class="cg-card__sub">Stok berkurang; harga klaim Rp 0. HPP asli tetap tampil. Pilih status retur: omset hanya berkurang kalau tidak bisa retur atau ditolak supplier.</p>
                    </div>
                </div>
                <button type="button" class="cg-btn cg-btn--primary" data-bs-toggle="modal"
                    data-bs-target="#replacementPartModal">
                    <iconify-icon icon="solar:add-circle-linear" width="17" height="17"></iconify-icon>
                    Tambah Sparepart
                </button>
            </div>

            <div class="table-responsive">
                <table class="cg-table cg-table--replacement">
                    <thead>
                        <tr>
                            <th>Nama Sparepart</th>
                            <th>Stok</th>
                            <th>HPP</th>
                            <th>Status Retur</th>
                            <th>Harga</th>
                            <th style="width: 110px;">Qty</th>
                            <th style="width: 90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="replacementRows">
                        <tr id="replacementEmpty">
                            <td colspan="7" class="cg-empty">Belum ada sparepart pengganti dipilih.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="cg-scroll-hint">Geser tabel ke samping untuk melihat semua kolom.</p>
        </section>

        <div class="cg-actionbar">
            <div class="cg-actionbar__note">
                <iconify-icon icon="solar:shield-check-linear" width="17" height="17"></iconify-icon>
                <span>Catatan klaim wajib diisi. Periksa sparepart rusak dan pengganti sebelum menyimpan.</span>
            </div>
            <div class="cg-actions">
                <a href="<?= base_url('garansi_service') ?>" class="cg-btn cg-btn--ghost">Batal</a>
                <button type="submit" class="cg-btn cg-btn--save">
                    <iconify-icon icon="solar:check-circle-linear" width="17" height="17"></iconify-icon>
                    Simpan Klaim
                </button>
            </div>
        </div>
    </form>
</div>

<div class="modal fade cg-modal" id="replacementPartModal" tabindex="-1" aria-labelledby="replacementPartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header align-items-start">
                <div class="d-flex align-items-center gap-2">
                    <span class="cg-modal__icon">
                        <iconify-icon icon="solar:box-minimalistic-line-duotone" width="20" height="20"></iconify-icon>
                    </span>
                    <div>
                        <h5 class="modal-title" id="replacementPartModalLabel">Pilih Sparepart Pengganti</h5>
                        <p class="mb-0">Cari nama atau kode, lalu centang yang dipakai.</p>
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
                        <i class="bi bi-shop me-1"></i><?= esc($unitLabel) ?>
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
                    <button type="button" class="cg-btn cg-btn--ghost" data-bs-dismiss="modal">Batal</button>
                    <button type="button" id="addSelectedReplacement"
                        class="cg-btn cg-btn--primary" data-bs-dismiss="modal" disabled>
                        <iconify-icon icon="solar:add-circle-linear" width="17" height="17"></iconify-icon>
                        Tambahkan
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
        const replacementEmpty = document.getElementById('replacementEmpty');
        const damagedSelectedCount = document.getElementById('damagedSelectedCount');
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

        function updateDamagedSelection() {
            const checked = document.querySelectorAll('.damaged-select:checked');
            damagedSelectedCount.textContent = checked.length + ' dipilih';
            document.querySelectorAll('.damaged-select').forEach(function(checkbox) {
                checkbox.closest('tr').classList.toggle('is-selected', checkbox.checked);
            });
        }

        function updateReplacementSelection() {
            const count = document.querySelectorAll('.replacement-check:checked').length;
            replacementCount.textContent = count > 0 ? count + ' sparepart dipilih' : 'Belum ada yang dipilih';
            addReplacementButton.disabled = count === 0;
        }

        function syncReplacementEmpty() {
            const hasRows = replacementRows.querySelector('tr:not(#replacementEmpty)') !== null;
            replacementEmpty.classList.toggle('d-none', hasRows);
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
            if (event.target.classList.contains('damaged-select')) {
                updateDamagedSelection();
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
                <td>Rp ${hppLabel}</td>
                <td>
                    <select class="form-select form-select-sm" name="produk[${index}][status_retur]">
                        <option value="bisa">Bisa retur</option>
                        <option value="tidak_bisa">Tidak bisa retur</option>
                    </select>
                </td>
                <td>Rp 0</td>
                <td><input type="number" class="form-control form-control-sm replacement-qty" name="produk[${index}][qty]" value="1" min="1" max="${stok}" data-max="${stok}" required></td>
                <td><button type="button" class="cg-btn cg-btn--ghost remove-replacement">Hapus</button></td>
            `;
            replacementRows.appendChild(row);
            syncReplacementEmpty();
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
                syncReplacementEmpty();
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

        updateDamagedSelection();
        updateReplacementSelection();
        syncReplacementEmpty();
    })();
</script>
