<!-- Header Card dengan Gradient Elegan -->
<div class="card shadow-none position-relative overflow-hidden mb-4 border-0"
    style="background: linear-gradient(120deg, #0f2b46 0%, #1d4e89 60%, #2a6dbb 100%);">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div class="text-white">
            <h4 class="fw-semibold mb-1 text-white">Performa Ads (Iklan)</h4>
            <p class="text-white-50 mb-0 fs-3">
                Sumber tunggal data iklan ke Laporan Digital Marketing: Daily Budget, Spending, PPN, Reach, Impression, Klik, hingga Hasil per campaign × cabang.
            </p>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('marketing') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">Performa Ads</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Flash Messages -->
<?php if (session()->getFlashdata('success')) : ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <iconify-icon icon="solar:check-circle-bold" class="fs-5 me-2"></iconify-icon>
            <div><?= session()->getFlashdata('success') ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')) : ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <iconify-icon icon="solar:danger-triangle-bold" class="fs-5 me-2"></iconify-icon>
            <div><?= session()->getFlashdata('error') ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter & Actions Card -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <form method="get" class="row g-2 align-items-end flex-grow-1">
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-semibold">Bulan</label>
                    <select name="bulan" class="form-select form-select-sm">
                        <?php for ($i = 1; $i <= 12; $i++) : ?>
                            <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-semibold">Tahun</label>
                    <select name="tahun" class="form-select form-select-sm">
                        <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++) : ?>
                            <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1 fw-semibold">Campaign</label>
                    <select name="campaign" class="form-select form-select-sm">
                        <option value="">Semua Campaign</option>
                        <?php foreach ($campaigns as $cn) : ?>
                            <option value="<?= esc($cn) ?>" <?= $kampanye === $cn ? 'selected' : '' ?>><?= esc($cn) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <iconify-icon icon="solar:filter-bold" class="me-1 align-text-bottom"></iconify-icon> Filter
                    </button>
                </div>
            </form>

            <div class="d-flex flex-wrap gap-2">
                <?php if ($canWrite) : ?>
                    <button type="button" class="btn btn-success btn-sm px-3" onclick="openPerformaModal()">
                        <iconify-icon icon="solar:add-circle-bold" class="me-1 align-text-bottom"></iconify-icon>Input Performa
                    </button>
                <?php endif; ?>
                <a href="<?= base_url('marketing/laporan') ?>" class="btn btn-outline-secondary btn-sm px-3">
                    <iconify-icon icon="solar:chart-2-bold" class="me-1 align-text-bottom"></iconify-icon>Laporan
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Main Table Card -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2 border-bottom">
        <div>
            <h5 class="mb-0 fw-semibold">Data Performa Ads</h5>
            <small class="text-muted">Periode: <?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></small>
        </div>
    </div>
    <div class="card-body p-0">
        <?php $channelName = []; ?>
        <?php foreach ($channels as $ch) : ?>
            <?php $channelName[(int)$ch->id] = $ch->name; ?>
        <?php endforeach; ?>
        <?php $unitName = []; ?>
        <?php foreach ($units as $u) : ?>
            <?php $unitName[(int)$u->idunit] = $u->NAMA_UNIT; ?>
        <?php endforeach; ?>

        <?php if (empty($rows)) : ?>
            <div class="text-center py-5">
                <iconify-icon icon="solar:chart-down-bold" class="text-muted fs-1 mb-2"></iconify-icon>
                <p class="text-muted mb-1">Belum ada data performa Ads untuk periode ini.</p>
                <?php if ($canWrite) : ?>
                    <button type="button" class="btn btn-sm btn-success mt-2 px-3" onclick="openPerformaModal()">
                        <iconify-icon icon="solar:add-circle-bold" class="me-1 align-text-bottom"></iconify-icon>Input Performa Ads
                    </button>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <!--
                Aturan alignment kolom (konsisten di header & body):
                - Teks bebas panjang (Periode, Campaign, Objective, Catatan) -> rata kiri
                - Badge pendek (Cabang, Channel)                             -> rata tengah
                - Angka/nominal (Budget, Spending, PPN, Reach, dst.)         -> rata kanan
                - Aksi                                                      -> rata tengah

                Kolom Channel: dibatasi lebar + satu baris (tidak wrap ke bawah).
                Kalau channel lebih dari 2, sisanya dirangkum jadi badge "+N"
                dengan tooltip berisi nama channel selebihnya saat di-hover.
            -->
            <style>
                .ads-table-responsive {
                    overflow-x: auto;
                    -webkit-overflow-scrolling: touch;
                }

                .ads-table th,
                .ads-table td {
                    white-space: nowrap;
                    font-size: 0.85rem;
                    padding: 0.75rem 0.6rem;
                }

                .ads-table th {
                    font-weight: 600;
                    background-color: #f8f9fa !important;
                    color: #2a3547;
                }

                /* Kolom channel: satu baris, tidak wrap ke bawah */
                .channel-cell {
                    max-width: 190px;
                    margin: 0 auto;
                }

                .channel-cell .badge-ch {
                    max-width: 85px;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .channel-cell .badge-more {
                    cursor: help;
                    flex-shrink: 0;
                }

                /* Catatan: dipotong 1 baris, isi lengkap muncul lewat tooltip */
                .catatan-cell {
                    max-width: 180px;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    display: inline-block;
                    vertical-align: middle;
                    cursor: <?= 'default' ?>;
                }
            </style>
            <div class="table-responsive ads-table-responsive">
                <table class="table ads-table table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3 text-start">Periode / Tanggal</th>
                            <th class="text-center">Cabang</th>
                            <th class="text-center">Channel</th>
                            <th class="text-start">Campaign</th>
                            <th class="text-end">Daily Budget</th>
                            <th class="text-end">Spending Ads</th>
                            <th class="text-end">PPN</th>
                            <th class="text-end">PPN Value</th>
                            <th class="text-start">Objective</th>
                            <th class="text-end">Reach</th>
                            <th class="text-end">Impression</th>
                            <th class="text-end">Klik</th>
                            <th class="text-end">Hasil</th>
                            <th class="text-start">Catatan</th>
                            <?php if ($canWrite) : ?>
                                <th class="text-center pe-3">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td class="ps-3 text-start">
                                    <?php if ($row->tanggal) : ?>
                                        <div class="fw-semibold text-dark"><?= date('d M Y', strtotime($row->tanggal)) ?></div>
                                    <?php else : ?>
                                        <span class="badge bg-light text-dark border"><?= date('Y-m', mktime(0, 0, 0, $row->period_month, 1, $row->period_year)) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (isset($unitName[(int)$row->unit_id])) : ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><?= esc($unitName[(int)$row->unit_id]) ?></span>
                                    <?php else : ?>
                                        <span class="badge bg-secondary-subtle text-secondary px-2 py-1">Umum</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $chList = [];
                                    if (!empty($row->channel_ids)) {
                                        $chIds = json_decode($row->channel_ids, true);
                                        if (is_array($chIds)) {
                                            foreach ($chIds as $cid) {
                                                if (isset($channelName[(int)$cid])) {
                                                    $chList[] = $channelName[(int)$cid];
                                                }
                                            }
                                        }
                                    }
                                    if (empty($chList) && isset($channelName[(int)$row->channel_id])) {
                                        $chList[] = $channelName[(int)$row->channel_id];
                                    }
                                    $chVisible = array_slice($chList, 0, 2);
                                    $chRestCount = count($chList) - count($chVisible);
                                    ?>
                                    <?php if (!empty($chList)) : ?>
                                        <div class="d-flex flex-nowrap align-items-center justify-content-center gap-1 channel-cell">
                                            <?php foreach ($chVisible as $cName) : ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 text-truncate badge-ch"
                                                    data-bs-toggle="tooltip" title="<?= esc($cName) ?>"><?= esc($cName) ?></span>
                                            <?php endforeach; ?>
                                            <?php if ($chRestCount > 0) : ?>
                                                <span class="badge bg-primary text-white px-2 badge-more"
                                                    data-bs-toggle="tooltip" title="<?= esc(implode(', ', array_slice($chList, 2))) ?>">+<?= $chRestCount ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else : ?>
                                        <span class="badge bg-secondary-subtle text-secondary px-2">Umum</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold text-dark text-start"><?= esc($row->campaign) ?></td>
                                <td class="text-end"><?= $row->daily_budget !== null ? 'Rp ' . number_format($row->daily_budget, 0, ',', '.') : '-' ?></td>
                                <td class="text-end fw-semibold text-primary"><?= $row->amount !== null ? 'Rp ' . number_format($row->amount, 0, ',', '.') : '-' ?></td>
                                <td class="text-end"><?= $row->ppn !== null ? number_format($row->ppn, 1, ',', '.') . '%' : '-' ?></td>
                                <td class="text-end"><?= $row->amount !== null && $row->ppn !== null ? 'Rp ' . number_format($row->amount + $row->amount * $row->ppn / 100, 0, ',', '.') : '-' ?></td>
                                <td class="text-start"><span class="text-muted"><?= esc($row->objective) ?: '-' ?></span></td>
                                <td class="text-end"><?= $row->reach !== null ? number_format($row->reach, 0, ',', '.') : '-' ?></td>
                                <td class="text-end"><?= $row->impression !== null ? number_format($row->impression, 0, ',', '.') : '-' ?></td>
                                <td class="text-end"><?= $row->klik !== null ? number_format($row->klik, 0, ',', '.') : '-' ?></td>
                                <td class="text-end fw-semibold text-success"><?= $row->hasil !== null ? number_format($row->hasil, 0, ',', '.') : '-' ?></td>
                                <td class="text-start">
                                    <?php if (!empty($row->note)) : ?>
                                        <span class="text-muted catatan-cell" data-bs-toggle="tooltip" title="<?= esc($row->note, 'attr') ?>"><?= esc($row->note) ?></span>
                                    <?php else : ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canWrite) : ?>
                                    <td class="text-center text-nowrap pe-3">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-edit me-1"
                                            data-id="<?= (int)$row->id ?>"
                                            data-tanggal="<?= esc($row->tanggal) ?>"
                                            data-unit="<?= (int)$row->unit_id ?>"
                                            data-channels="<?= esc($row->channel_ids ?: json_encode([$row->channel_id]), 'attr') ?>"
                                            data-campaign="<?= esc($row->campaign, 'attr') ?>"
                                            data-budget="<?= esc((string)($row->daily_budget ?? ''), 'attr') ?>"
                                            data-amount="<?= esc((string)($row->amount ?? ''), 'attr') ?>"
                                            data-ppn="<?= esc((string)($row->ppn ?? ''), 'attr') ?>"
                                            data-objective="<?= esc((string)$row->objective ?? '', 'attr') ?>"
                                            data-reach="<?= esc((string)($row->reach ?? ''), 'attr') ?>"
                                            data-impression="<?= esc((string)($row->impression ?? ''), 'attr') ?>"
                                            data-klik="<?= esc((string)($row->klik ?? ''), 'attr') ?>"
                                            data-hasil="<?= esc((string)($row->hasil ?? ''), 'attr') ?>"
                                            data-note="<?= esc((string)$row->note ?? '', 'attr') ?>"
                                            title="Edit"><i class="bi bi-pencil"></i></button>
                                        <form method="post" action="<?= base_url('marketing/ads_performa/hapus') ?>" class="d-inline"
                                            onsubmit="return confirm('Hapus performa Ads ini?');">
                                            <input type="hidden" name="id" value="<?= $row->id ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                <iconify-icon icon="solar:trash-bin-trash-bold" width="15" height="15"></iconify-icon>
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah / Edit Performa Ads -->
<?php if ($canWrite) : ?>
    <div class="modal fade" id="modalPerformaAds" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form method="post" action="<?= base_url('marketing/ads_performa/simpan') ?>" id="performaForm">
                <input type="hidden" name="id" id="pf_id" value="">
                <input type="hidden" name="period_month" id="pfPeriodMonth" value="<?= $bulan ?>">
                <input type="hidden" name="period_year" id="pfPeriodYear" value="<?= $tahun ?>">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-semibold">
                            <iconify-icon icon="solar:chart-2-bold" class="text-primary me-1 align-text-bottom"></iconify-icon>
                            <span id="pf_title">Input Performa Ads</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1 fw-semibold">Tanggal</label>
                                <input type="date" name="tanggal" id="pf_tanggal" class="form-control form-control-sm"
                                    value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1 fw-semibold">Cabang</label>
                                <select name="unit_id" id="pf_unit" class="form-select form-select-sm">
                                    <option value="">Semua / Umum</option>
                                    <?php foreach ($units as $u) : ?>
                                        <option value="<?= $u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1 fw-semibold">Channel</label>
                                <select name="channel_id[]" id="pf_channel" class="form-select form-select-sm" multiple size="3">
                                    <?php foreach ($channels as $ch) : ?>
                                        <option value="<?= $ch->id ?>"><?= esc($ch->name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted fs-1">Tahan Ctrl/Cmd untuk pilih banyak</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1 fw-semibold">Campaign</label>
                                <input type="text" name="campaign" id="pf_campaign" class="form-control form-control-sm"
                                    placeholder="mis. Promo Ramadhan" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1 fw-semibold">Daily Budget (Rp)</label>
                                <input type="text" name="daily_budget" id="pf_budget" class="form-control form-control-sm"
                                    placeholder="mis. 92.557">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1 fw-semibold">Spending Ads (Rp)</label>
                                <input type="text" name="amount" id="pf_amount" class="form-control form-control-sm"
                                    placeholder="mis. 87.679">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1 fw-semibold">PPN (%)</label>
                                <input type="text" name="ppn" id="pf_ppn" class="form-control form-control-sm"
                                    placeholder="11">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1 fw-semibold">Objective</label>
                                <input type="text" name="objective" id="pf_objective" class="form-control form-control-sm"
                                    placeholder="mis. Traffic">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1 fw-semibold">Reach</label>
                                <input type="text" name="reach" id="pf_reach" class="form-control form-control-sm angka-bulat"
                                    placeholder="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1 fw-semibold">Impression</label>
                                <input type="text" name="impression" id="pf_impression" class="form-control form-control-sm angka-bulat"
                                    placeholder="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1 fw-semibold">Klik</label>
                                <input type="text" name="klik" id="pf_klik" class="form-control form-control-sm angka-bulat"
                                    placeholder="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1 fw-semibold">Hasil</label>
                                <input type="text" name="hasil" id="pf_hasil" class="form-control form-control-sm angka-bulat"
                                    placeholder="0">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small text-muted mb-1 fw-semibold">Catatan</label>
                                <input type="text" name="note" id="pf_note" class="form-control form-control-sm" placeholder="Catatan opsional">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success btn-sm px-4">
                            <iconify-icon icon="solar:check-circle-bold" class="me-1 align-text-bottom"></iconify-icon>Simpan Data
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    // Format input: angka bulat (reach, imp, klik, hasil)
    var perfForm = document.getElementById('performaForm');
    if (perfForm) {
        perfForm.querySelectorAll('.angka-bulat').forEach(function(inp) {
            inp.addEventListener('blur', function() {
                var v = this.value.replace(/[^0-9]/g, '');
                this.value = v;
            });
        });
    }

    // Bulan/tahun otomatis mengikuti tanggal.
    var pfTgl = document.querySelector('#pf_tanggal');
    var syncPfPeriod = function() {
        var t = pfTgl.value;
        if (!t) return;
        var p = t.split('-');
        var mSel = document.getElementById('pfPeriodMonth');
        var yInp = document.getElementById('pfPeriodYear');
        if (mSel) mSel.value = parseInt(p[1], 10).toString();
        if (yInp) yInp.value = p[0];
    };
    if (pfTgl) {
        pfTgl.addEventListener('change', syncPfPeriod);
    }

    function openPerformaModal() {
        document.getElementById('performaForm').reset();
        document.getElementById('pf_id').value = '';
        document.getElementById('pf_tanggal').value = '<?= date('Y-m-d') ?>';
        document.getElementById('pf_title').textContent = 'Input Performa Ads';
        syncPfPeriod();
        var modal = new bootstrap.Modal(document.getElementById('modalPerformaAds'));
        modal.show();
    }

    document.querySelectorAll('.btn-edit').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('pf_title').textContent = 'Edit Performa Ads';
            document.getElementById('pf_id').value = this.dataset.id || '';
            document.getElementById('pf_tanggal').value = this.dataset.tanggal || '';
            document.getElementById('pf_unit').value = this.dataset.unit || '';

            // Multi-select channel.
            var sel = document.getElementById('pf_channel');
            for (var i = 0; i < sel.options.length; i++) sel.options[i].selected = false;
            try {
                var chIds = JSON.parse(this.dataset.channels || '[]');
                for (var j = 0; j < chIds.length; j++) {
                    for (var k = 0; k < sel.options.length; k++) {
                        if (parseInt(sel.options[k].value, 10) === chIds[j]) {
                            sel.options[k].selected = true;
                        }
                    }
                }
            } catch (e) {}

            document.getElementById('pf_campaign').value = this.dataset.campaign || '';
            document.getElementById('pf_budget').value = this.dataset.budget || '';
            document.getElementById('pf_amount').value = this.dataset.amount || '';
            document.getElementById('pf_ppn').value = this.dataset.ppn || '';
            document.getElementById('pf_objective').value = this.dataset.objective || '';
            document.getElementById('pf_reach').value = this.dataset.reach || '';
            document.getElementById('pf_impression').value = this.dataset.impression || '';
            document.getElementById('pf_klik').value = this.dataset.klik || '';
            document.getElementById('pf_hasil').value = this.dataset.hasil || '';
            document.getElementById('pf_note').value = this.dataset.note || '';
            syncPfPeriod();
            var modal = new bootstrap.Modal(document.getElementById('modalPerformaAds'));
            modal.show();
        });
    });

    // Tooltip untuk badge channel (+N) dan catatan yang dipotong.
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                html: false,
                trigger: 'hover'
            });
        });
    });
</script>
