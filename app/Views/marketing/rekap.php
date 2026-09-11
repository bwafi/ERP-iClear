<div class="card shadow-none position-relative overflow-hidden mb-4"
    style="background: linear-gradient(120deg, #0f2b46 0%, #1d4e89 60%, #2a6dbb 100%);">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div class="text-white">
            <h4 class="fw-semibold mb-1 text-white">Rekap Marketing Harian</h4>
            <small class="text-white-50">Input harian CS per cabang: Non Iklan, Iklan, Prospek, Datang &amp; Rate. Sumber utama KPI Marketing — terpisah dari data Kommo.</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('marketing') ?>">Marketing KPI</a></li>
                <li class="breadcrumb-item active text-white">Rekap Harian</li>
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

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-1 small text-muted">Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?= esc($tanggal) ?>" required>
            </div>
            <div class="col-auto">
                <label class="form-label mb-1 small text-muted">Cabang</label>
                <select name="unit_id" class="form-select" required>
                    <option value="">Pilih Cabang</option>
                    <?php foreach ($units as $unit) : ?>
                        <option value="<?= (int)$unit->idunit ?>" <?= (int)$unit->idunit === $unitId ? 'selected' : '' ?>><?= esc($unit->NAMA_UNIT) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Tampilkan</button>
            </div>
            <?php if ($rekap['header']) : ?>
                <div class="col-auto text-muted small align-self-center">
                    <i class="bi bi-check-circle text-success me-1"></i>Rekap <?= esc($tanggal) ?> sudah ada.
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0"><iconify-icon icon="solar:clipboard-list-bold" class="text-primary me-1"></iconify-icon>Input Angka Harian</h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?= base_url('marketing/rekap/simpan') ?>" id="rekapForm">
            <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">
            <input type="hidden" name="unit_id" value="<?= (int)$unitId ?>">

            <?php
            $platformNames = [];
            foreach ($platforms as $pf) {
                $platformNames[] = $pf->name;
            }
            ?>
            <select class="d-none" id="platformTemplate" aria-hidden="true">
                <option value="">— Pilih Platform —</option>
                <?php foreach ($platformNames as $pn) : ?>
                    <option value="<?= esc($pn) ?>"><?= esc($pn) ?></option>
                <?php endforeach; ?>
            </select>

            <?php
            $details = $rekap['details'] ?: [];
            $rowCount = max(3, count($details));
            ?>

            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0" id="rekapTable">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:210px;">Platform</th>
                            <th class="text-center" style="width:105px;">Non Iklan</th>
                            <th class="text-center" style="width:105px;">Iklan</th>
                            <th class="text-center" style="width:105px;">Total</th>
                            <th class="text-center" style="width:105px;">Prospek</th>
                            <th class="text-center" style="width:105px;">Datang</th>
                            <th class="text-center" style="width:110px;">Rate</th>
                            <?php if ($canWrite) : ?><th class="text-center" style="width:50px;"></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < $rowCount; $i++) : ?>
                            <?php
                            $d = $details[$i] ?? null;
                            $rowPlatforms = $platformNames;
                            if ($d && trim((string)$d->platform) !== '' && !in_array($d->platform, $rowPlatforms, true)) {
                                $rowPlatforms[] = $d->platform;
                            }
                            ?>
                            <tr class="rekap-row">
                                <td>
                                    <select name="platform[]" class="form-select form-select-sm rp-platform" <?= $canWrite ? '' : 'disabled' ?>>
                                        <option value="">— Pilih Platform —</option>
                                        <?php foreach ($rowPlatforms as $pn) : ?>
                                            <option value="<?= esc($pn) ?>" <?= $d && $d->platform === $pn ? 'selected' : (!$d && $pn === $platformNames[0] ? 'selected' : '') ?>><?= esc($pn) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" min="0" name="non_iklan[]" class="form-control form-control-sm text-end rp-non" value="<?= (int)($d->non_iklan ?? 0) ?>" <?= $canWrite ? '' : 'readonly' ?>></td>
                                <td><input type="number" min="0" name="iklan[]" class="form-control form-control-sm text-end rp-iklan" value="<?= (int)($d->iklan ?? 0) ?>" <?= $canWrite ? '' : 'readonly' ?>></td>
                                <td class="text-center fw-semibold rp-total align-middle"><?= (int)($d->total ?? 0) ?></td>
                                <td><input type="number" min="0" name="prospek[]" class="form-control form-control-sm text-end rp-prospek" value="<?= (int)($d->prospek ?? 0) ?>" <?= $canWrite ? '' : 'readonly' ?>></td>
                                <td><input type="number" min="0" name="datang[]" class="form-control form-control-sm text-end rp-datang" value="<?= (int)($d->datang ?? 0) ?>" <?= $canWrite ? '' : 'readonly' ?>></td>
                                <td class="text-center rp-rate text-muted small"><?= $d && (int)$d->total > 0 ? number_format((float)$d->rate, 2, ',', '.') . '%' : '0%' ?></td>
                                <?php if ($canWrite) : ?>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger rp-del" title="Hapus baris"><i class="bi bi-x"></i></button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($canWrite) : ?>
                <div class="d-flex justify-content-end mt-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="rpAddRow"><i class="bi bi-plus-lg me-1"></i>Tambah Platform</button>
                </div>
            <?php endif; ?>

            <div class="row g-3 mt-1">
                <div class="col-md-12">
                    <div class="rounded-3 border p-3">
                        <label class="form-label small text-muted mb-2">Total Semua Platform</label>
                        <div class="row g-2">
                            <div class="col-6 col-lg-3">
                                <div class="text-muted small">Total Lead</div>
                                <div class="fw-semibold fs-5" id="totalLead">0</div>
                            </div>
                            <div class="col-6 col-lg-3">
                                <div class="text-muted small">Total Prospek</div>
                                <div class="fw-semibold fs-5" id="totalProspek">0</div>
                            </div>
                            <div class="col-6 col-lg-3">
                                <div class="text-muted small">Total Datang</div>
                                <div class="fw-semibold fs-5" id="totalDatang">0</div>
                            </div>
                            <div class="col-6 col-lg-3">
                                <div class="text-muted small">Rate</div>
                                <div class="fw-semibold fs-5" id="rateAll">0%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($canWrite) : ?>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="<?= base_url('marketing/rekap') ?>" class="btn btn-light">Batal</a>
                    <button type="submit" class="btn btn-primary" <?= $unitId > 0 ? '' : 'disabled' ?>>
                        <i class="bi bi-save me-1"></i>Simpan Rekap
                    </button>
                </div>
            <?php else : ?>
                <div class="alert alert-light border mt-3 mb-0 small text-muted">
                    Anda hanya dapat melihat rekap. Hubungi admin untuk mengubah data.
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 mt-3">
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h5 class="mb-0"><iconify-icon icon="solar:calendar-mark-bold" class="text-primary me-1"></iconify-icon>Daftar Rekap Marketing</h5>
        <form method="get" class="d-flex align-items-center gap-2 m-0" id="rekapListFilter">
            <select name="bulan" class="form-select form-select-sm select2" id="rekapBulan">
                <?php foreach (range(1, 12) as $b) : ?>
                    <option value="<?= $b ?>" <?= $b === $bulanR ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $b, 1)) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="tahun" class="form-select form-select-sm select2" style="width:100px;" id="rekapTahun">
                <?php foreach (range((int)date('Y'), (int)date('Y') - 3) as $t) : ?>
                    <option value="<?= $t ?>" <?= $t === $tahunR ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i></button>
        </form>
    </div>
    <div class="card-body">
        <?php if (empty($rekaps)) : ?>
            <div class="alert alert-light border mb-0 text-center py-4">
                <iconify-icon icon="solar:file-corrupted-outline" class="text-muted" width="32" height="32"></iconify-icon>
                <div class="text-muted mt-1">Belum ada rekap pada bulan ini. Pilih tanggal &amp; cabang di atas, lalu Simpan.</div>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Cabang</th>
                            <th>Platform</th>
                            <th class="text-center">Non Iklan</th>
                            <th class="text-center">Iklan</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Prospek</th>
                            <th class="text-center">Datang</th>
                            <th class="text-center">Rate</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rekaps as $g) :
                            $url = base_url('marketing/rekap') . '?tanggal=' . $g['tanggal'] . '&unit_id=' . (int)$g['unit_id'];
                            $platCount = count($g['platforms']);
                        ?>
                            <?php foreach ($g['platforms'] as $pidx => $p) : ?>
                                <tr>
                                    <?php if ($pidx === 0) : ?>
                                        <td class="fw-semibold align-middle" rowspan="<?= $platCount ?>">
                                            <?= date('d/m/Y', strtotime($g['tanggal'])) ?>
                                        </td>
                                        <td class="align-middle" rowspan="<?= $platCount ?>">
                                            <span class="fw-semibold"><?= esc($g['unit_name']) ?></span>
                                            <div class="small text-muted">Total <?= (int)$g['total'] ?> · Iklan <?= (int)$g['iklan'] ?> · WA/DM <?= (int)$g['wa_dm'] ?></div>
                                        </td>
                                    <?php endif; ?>
                                    <td><?= esc($p['platform']) ?></td>
                                    <td class="text-center"><?= (int)$p['non_iklan'] ?></td>
                                    <td class="text-center"><?= (int)$p['iklan'] ?></td>
                                    <td class="text-center fw-semibold"><?= (int)$p['total'] ?></td>
                                    <td class="text-center"><?= (int)$p['prospek'] ?></td>
                                    <td class="text-center"><?= (int)$p['datang'] ?></td>
                                    <td class="text-center text-muted"><?= number_format((float)$p['rate'], 1, ',', '.') ?>%</td>
                                    <?php if ($pidx === 0) : ?>
                                        <td class="text-center align-middle" rowspan="<?= $platCount ?>">
                                            <a class="btn btn-sm btn-outline-primary" href="<?= $url ?>"><i class="bi bi-pencil me-1"></i>Ubah</a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    'use strict';

    var table = document.getElementById('rekapTable');
    var totalLeadEl = document.getElementById('totalLead');
    var totalProspekEl = document.getElementById('totalProspek');
    var totalDatangEl = document.getElementById('totalDatang');
    var rateAllEl = document.getElementById('rateAll');
    var platformOptions = Array.prototype.slice.call(
        document.getElementById('platformTemplate').options
    ).filter(function (o) { return o.value !== ''; }).map(function (o) { return o.value; });

    function escHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function num(input) {
        return Math.max(0, parseInt(input.value, 10) || 0);
    }
    function recalcRow(tr) {
        var non = num(tr.querySelector('.rp-non'));
        var iklan = num(tr.querySelector('.rp-iklan'));
        var datang = num(tr.querySelector('.rp-datang'));
        var total = non + iklan;
        tr.querySelector('.rp-total').textContent = total;
        var rate = total > 0 ? (datang / total * 100).toFixed(2) : '0.00';
        tr.querySelector('.rp-rate').textContent = rate.replace('.', ',') + '%';
        return { total: total, datang: datang, prospek: num(tr.querySelector('.rp-prospek')) };
    }
    function recalcAll() {
        var rows = table.querySelectorAll('tbody .rekap-row');
        var lead = 0, datang = 0, prospek = 0;
        rows.forEach(function (tr) {
            var r = recalcRow(tr);
            lead += r.total;
            datang += r.datang;
            prospek += r.prospek;
        });
        totalLeadEl.textContent = lead;
        totalProspekEl.textContent = prospek;
        totalDatangEl.textContent = datang;
        rateAllEl.textContent = lead > 0 ? (datang / lead * 100).toFixed(2).replace('.', ',') + '%' : '0%';
    }

    function platformSelectHtml() {
        var html = '<select name="platform[]" class="form-select form-select-sm rp-platform">' +
            '<option value="">— Pilih Platform —</option>';
        platformOptions.forEach(function (p) {
            html += '<option value="' + escHtml(p) + '">' + escHtml(p) + '</option>';
        });
        return html + '</select>';
    }

    function addRow() {
        var tbody = table.querySelector('tbody');
        var row = document.createElement('tr');
        row.className = 'rekap-row';
        row.innerHTML =
            '<td>' + platformSelectHtml() + '</td>' +
            '<td><input type="number" min="0" name="non_iklan[]" class="form-control form-control-sm text-end rp-non" value="0"></td>' +
            '<td><input type="number" min="0" name="iklan[]" class="form-control form-control-sm text-end rp-iklan" value="0"></td>' +
            '<td class="text-center fw-semibold rp-total align-middle">0</td>' +
            '<td><input type="number" min="0" name="prospek[]" class="form-control form-control-sm text-end rp-prospek" value="0"></td>' +
            '<td><input type="number" min="0" name="datang[]" class="form-control form-control-sm text-end rp-datang" value="0"></td>' +
            '<td class="text-center rp-rate text-muted small">0%</td>' +
            '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger rp-del" title="Hapus baris"><i class="bi bi-x"></i></button></td>';
        tbody.appendChild(row);
        bindRow(row);
        recalcAll();
    }

    function bindRow(tr) {
        ['rp-non', 'rp-iklan', 'rp-datang', 'rp-prospek'].forEach(function (cls) {
            var el = tr.querySelector('.' + cls);
            if (el) el.addEventListener('input', recalcAll);
        });
        var plat = tr.querySelector('.rp-platform');
        if (plat) plat.addEventListener('change', recalcAll);
        var del = tr.querySelector('.rp-del');
        if (del) del.addEventListener('click', function () {
            tr.remove();
            recalcAll();
        });
    }

    table.querySelectorAll('tbody .rekap-row').forEach(bindRow);
    var addRowBtn = document.getElementById('rpAddRow');
    if (addRowBtn) addRowBtn.addEventListener('click', addRow);
    recalcAll();
})();
</script>