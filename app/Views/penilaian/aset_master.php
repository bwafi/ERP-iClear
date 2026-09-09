<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
            <h4 class="fw-semibold mb-0">Asset Master — Baseline Aset</h4>
            <span class="text-muted small">Kelola data master aset untuk baseline KPI KONTROL_ASET</span>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('penilaian/kpi/aset_master') ?>">Master Aset</a></li>
                <li class="breadcrumb-item active" aria-current="page">Asset Master</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (session()->getFlashdata('success')) : ?>
    <div class="alert alert-success alert-dismissible fade show"><?= session()->getFlashdata('success') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= session()->getFlashdata('error') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (empty($unitList)) : ?>
    <div class="alert alert-warning">Tidak ada unit yang dapat Anda kelola pada menu ini.</div>
<?php return;
endif; ?>

<div class="card w-100 position-relative overflow-hidden mb-3">
    <div class="card-body">
        <form method="get" action="<?= base_url('penilaian/kpi/aset_master') ?>" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Lokasi — Di Mana</label>
                <select name="unit" class="form-select">
                    <option value="0" <?= $unitId === 0 ? 'selected' : '' ?>>Semua Lokasi</option>
                    <?php foreach ($unitList as $uid => $nama) : ?>
                        <option value="<?= $uid ?>" <?= $uid === $unitId ? 'selected' : '' ?>><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Asal — Dari Mana</label>
                <select name="dari" class="form-select">
                    <option value="0" <?= $dariId === 0 ? 'selected' : '' ?>>Semua Asal</option>
                    <?php foreach ($unitList as $uid => $nama) : ?>
                        <option value="<?= $uid ?>" <?= $uid === $dariId ? 'selected' : '' ?>><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Tampilkan</button>
            </div>
        </form>
        <hr>
        <div class="text-muted small">
            <strong>Asset Master</strong> adalah data baseline aset untuk KPI KONTROL_ASET.
            Quantity master <strong>TIDAK pernah berubah otomatis</strong> berdasarkan hasil audit SPV.
            Status aktif/nonaktif digunakan untuk menonaktifkan aset yang tidak lagi dipakai tanpa menghapus histori audit.
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0">Tambah Aset Baru</h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?= base_url('penilaian/kpi/aset_master/insert') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="kode_aset" value="">
            <div class="col-md-3">
                <label class="form-label mb-1">Nama Aset</label>
                <input type="text" name="asset" class="form-control form-control-sm" placeholder="contoh: Laptop Admin" required>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Asal — Dari Mana</label>
                <select name="dari" class="form-select form-select-sm" id="addDari">
                    <?php foreach ($unitList as $uid => $nama) : ?>
                        <option value="<?= $uid ?>" <?= ($uid === 5 && isset($unitList[5])) || $uid === $unitId ? 'selected' : '' ?>><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Lokasi — Ada di Mana <span class="text-danger">*</span></label>
                <select name="unit" class="form-select form-select-sm" required>
                    <option value="" selected disabled>— Pilih Lokasi —</option>
                    <?php foreach ($unitList as $uid => $nama) : ?>
                        <option value="<?= $uid ?>"><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Quantity Baseline</label>
                <input type="number" name="quantity" class="form-control form-control-sm" value="1" min="1" required>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Harga <span class="text-muted">(opsional)</span></label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Rp</span>
                    <input type="text"
                        name="harga"
                        class="form-control harga-rupiah"
                        placeholder="0"
                        inputmode="numeric"
                        autocomplete="off">
                </div>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-success btn-sm w-100">
                    <iconify-icon icon="solar:add-circle-bold" class="me-1"></iconify-icon>Tambah
                </button>
            </div>
            <div class="col-md-10">
                <label class="form-label mb-1">Keterangan <span class="text-muted">(opsional)</span></label>
                <input type="text" name="keterangan" class="form-control form-control-sm" placeholder="catatan aset">
            </div>
            <div class="col-12 text-muted small">
                Kode otomatis mengikuti asal: <span class="fw-semibold" id="previewKodeAdd">AST-<?= esc($unitCodes[5] ?? 'HO') ?>-4digit</span> (mis. barang dari HO dan ada di Probolinggo → <span class="fw-semibold">AST-HO-8124</span>). Lokasi wajib dipilih manual — tidak ikut filter unit di atas.
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header">
        <h5 class="mb-0">
            Daftar Aset Master —
            <?= $unitId === 0 ? 'Semua Lokasi' : esc($unitList[$unitId] ?? ('Unit ' . $unitId)) ?>
            ·
            <?= $dariId === 0 ? 'Semua Asal' : 'Dari ' . esc($unitList[$dariId] ?? ('Unit ' . $dariId)) ?>
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($assets)) : ?>
            <div class="text-muted small">Belum ada aset master terdaftar.</div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle table-hover">
                    <thead class="table-light">
                        <tr>
                            <th rowspan="2" class="align-middle">Kode</th>
                            <th rowspan="2" class="align-middle">Nama Aset</th>
                            <th rowspan="2" class="align-middle">Asal (Dari)</th>
                            <th rowspan="2" class="align-middle">Lokasi (Di)</th>
                            <th colspan="2" class="text-center">Master</th>
                            <th colspan="6" class="text-center bg-info-subtle">Audit Terakhir</th>
                            <th rowspan="2" class="text-center align-middle">Status</th>
                            <th rowspan="2" class="text-center align-middle" width="200">Aksi</th>
                        </tr>
                        <tr>
                            <th class="text-center">Qty</th>
                            <th class="text-center">Harga</th>
                            <th class="text-center bg-info-subtle">Periode</th>
                            <th class="text-center bg-info-subtle">Ditemukan</th>
                            <th class="text-center bg-info-subtle">Hilang</th>
                            <th class="text-center bg-info-subtle">Kondisi</th>
                            <th class="text-center bg-info-subtle">Perawatan</th>
                            <th class="text-center bg-info-subtle">Status Audit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assets as $a) : ?>
                            <?php
                            $lastAudit = $a['last_audit'] ?? null;
                            $rowClass = !$a['is_active'] ? 'text-muted' : '';
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td class="fw-semibold"><?= esc($a['kode_aset']) ?></td>
                                <td>
                                    <?= esc($a['asset']) ?>
                                    <?php if ($a['keterangan']) : ?>
                                        <br><small class="text-muted"><?= esc($a['keterangan']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= esc($a['asal_name']) ?> <span class="badge bg-light border"><?= esc($a['asal_code']) ?></span></small></td>
                                <td><small><?= esc($a['unit_name']) ?></small></td>
                                <td class="text-center fw-bold"><?= $a['quantity'] ?></td>
                                <td class="text-end">
                                    <?php if ($a['harga'] !== null) : ?>
                                        <span class="text-muted">Rp</span> <?= number_format($a['harga'], 0, ',', '.') ?>
                                    <?php else : ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>

                                <?php if ($lastAudit) : ?>
                                    <td class="text-center bg-info-subtle">
                                        <small><?= str_pad((string)$lastAudit['bulan'], 2, '0', STR_PAD_LEFT) ?>/<?= $lastAudit['tahun'] ?></small>
                                    </td>
                                    <td class="text-center bg-info-subtle fw-bold">
                                        <?= $lastAudit['quantity_ditemukan'] ?>
                                    </td>
                                    <td class="text-center bg-info-subtle <?= $lastAudit['hilang'] > 0 ? 'text-danger fw-bold' : '' ?>">
                                        <?= $lastAudit['hilang'] ?>
                                    </td>
                                    <td class="text-center bg-info-subtle">
                                        <small><?= esc($lastAudit['kondisi']) ?></small>
                                    </td>
                                    <td class="text-center bg-info-subtle">
                                        <?php if ($lastAudit['perawatan'] === 'TERAWAT') : ?>
                                            <span class="badge bg-success-subtle text-success">TERAWAT</span>
                                        <?php elseif ($lastAudit['perawatan'] === 'TIDAK_TERAWAT') : ?>
                                            <span class="badge bg-warning-subtle text-warning">TIDAK TERAWAT</span>
                                        <?php else : ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center bg-info-subtle">
                                        <span class="badge bg-success-subtle text-success">FINAL</span>
                                    </td>
                                <?php else : ?>
                                    <td colspan="6" class="text-center bg-light text-muted">
                                        <small>Belum pernah diaudit</small>
                                    </td>
                                <?php endif; ?>

                                <td class="text-center">
                                    <span class="badge bg-<?= $a['is_active'] ? 'success' : 'secondary' ?>-subtle text-<?= $a['is_active'] ? 'success' : 'secondary' ?>">
                                        <?= $a['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex align-items-center justify-content-center gap-1 flex-nowrap">

                                        <button type="button"
                                            class="btn btn-sm btn-outline-primary btn-edit-master"
                                            data-aset='<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>'>
                                            Edit
                                        </button>

                                        <form method="post"
                                            action="<?= base_url('penilaian/kpi/aset_master/toggle') ?>"
                                            class="m-0">

                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <input type="hidden" name="unit" value="<?= $a['unit'] ?>">
                                            <input type="hidden" name="active" value="<?= $a['is_active'] ? '0' : '1' ?>">

                                            <button type="submit"
                                                class="btn btn-sm btn-outline-<?= $a['is_active'] ? 'warning' : 'info' ?>">
                                                <?= $a['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                            </button>
                                        </form>

                                        <?php if ($canDelete) : ?>
                                            <form method="post"
                                                action="<?= base_url('penilaian/kpi/aset_master/delete') ?>"
                                                class="m-0"
                                                onsubmit="return confirm('Hapus aset master <?= esc($a['kode_aset'], 'js') ?>? (ditolak jika sudah diaudit)');">

                                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                                <input type="hidden" name="unit" value="<?= $a['unit'] ?>">

                                                <button type="submit"
                                                    class="btn btn-sm btn-outline-danger">
                                                    Hapus
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Edit Master -->
<div class="modal fade" id="modalEditMaster" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="<?= base_url('penilaian/kpi/aset_master/update') ?>">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="unit" id="editUnit">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Aset Master</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Nama Aset</label>
                        <input type="text" name="asset" id="editAsset" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Asal — Dari Mana</label>
                            <select name="dari" id="editDari" class="form-select">
                                <?php foreach ($unitList as $uid => $nama) : ?>
                                    <option value="<?= $uid ?>"><?= esc($nama) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Lokasi — Ada di Mana</label>
                            <select name="unit" id="editUnit" class="form-select">
                                <?php foreach ($unitList as $uid => $nama) : ?>
                                    <option value="<?= $uid ?>"><?= esc($nama) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Kode Aset <small class="text-muted">(ikuti asal)</small></label>
                            <input type="text" id="editKodePreview" class="form-control" readonly>
                            <input type="hidden" name="kode_aset" id="editKode">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Quantity Baseline</label>
                            <input type="number" name="quantity" id="editQty" class="form-control" min="1" value="1" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label">Harga <small class="text-muted">(opsional)</small></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text"
                                    name="harga"
                                    id="editHarga"
                                    class="form-control harga-rupiah"
                                    placeholder="0"
                                    inputmode="numeric"
                                    autocomplete="off">
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" id="editKeterangan" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    var ASSET_UNIT_CODES = <?= json_encode($unitCodes, JSON_UNESCAPED_UNICODE) ?>;

    function asetKodePreview(dariId, suffix) {
        var code = ASSET_UNIT_CODES[dariId] || 'XXX';
        return 'AST-' + code + (suffix ? '-' + suffix : '-XXXX');
    }

    function formatRupiah(value) {
        value = value.replace(/\D/g, '');

        if (!value) {
            return '';
        }

        return value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    document.querySelectorAll('.harga-rupiah').forEach(function(input) {
        input.addEventListener('input', function() {
            this.value = formatRupiah(this.value);
        });
    });

    // Sebelum submit, ubah 1.500.000 menjadi 1500000
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            form.querySelectorAll('.harga-rupiah').forEach(function(input) {
                input.value = input.value.replace(/\./g, '');
            });
        });
    });
    document.querySelectorAll('.btn-edit-master').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var d = JSON.parse(btn.getAttribute('data-aset'));
            document.getElementById('editId').value = d.id;
            document.getElementById('editUnit').value = d.unit;
            document.getElementById('editDari').value = d.dari_unit;
            document.getElementById('editAsset').value = d.asset;
            document.getElementById('editKode').value = d.kode_aset;
            var suffix = String(d.kode_aset).replace(/^AST-[A-Z0-9]+-/, '');
            var preview = document.getElementById('editKodePreview');
            preview.value = asetKodePreview(d.dari_unit, suffix);
            preview.classList.remove('text-warning');
            document.getElementById('editQty').value = d.quantity;
            document.getElementById('editHarga').value =
                d.harga !== null && d.harga !== '' ?
                formatRupiah(String(Math.round(Number(d.harga)))) :
                '';
            document.getElementById('editKeterangan').value = d.keterangan || '';
            new bootstrap.Modal(document.getElementById('modalEditMaster')).show();
        });
    });

    // Ubah asal → kode otomatis digenerate ulang dengan prefix baru.
    document.getElementById('editDari').addEventListener('change', function() {
        document.getElementById('editKode').value = '';
        var preview = document.getElementById('editKodePreview');
        preview.value = asetKodePreview(this.value);
        preview.classList.add('text-warning');
    });
    var addDari = document.getElementById('addDari');
    if (addDari) {
        addDari.addEventListener('change', function() {
            document.getElementById('previewKodeAdd').textContent = asetKodePreview(this.value);
        });
    }
</script>
