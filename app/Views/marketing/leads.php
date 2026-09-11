<div class="card shadow-none position-relative overflow-hidden mb-4"
    style="background: linear-gradient(120deg, #0f2b46 0%, #1d4e89 60%, #2a6dbb 100%);">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div class="text-white">
            <h4 class="fw-semibold mb-1 text-white">Detail Prospek</h4>
            <small class="text-white-50">Prospek dihubungkan ke service — omset dihitung otomatis dari penjualan service. KPI Marketing tetap dari Rekap Harian CS.</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('marketing') ?>">Marketing KPI</a></li>
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('marketing/rekap') ?>">Rekap Harian</a></li>
                <li class="breadcrumb-item active text-white">Detail Prospek</li>
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

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <form method="get" action="<?= base_url('marketing/leads') ?>" class="row g-2 align-items-end">
        <div class="col-auto">
            <label class="form-label mb-1 small text-muted">Bulan</label>
            <select name="bulan" class="form-select">
                <?php for ($i = 1; $i <= 12; $i++) : ?>
                    <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label mb-1 small text-muted">Tahun</label>
            <select name="tahun" class="form-select">
                <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++) : ?>
                    <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label mb-1 small text-muted">Unit</label>
            <select name="unit_id" class="form-select">
                <option value="">Semua Unit</option>
                <?php foreach ($units as $u) : ?>
                    <option value="<?= (int)$u->idunit ?>" <?= $unitId === (int)$u->idunit ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label mb-1 small text-muted">Platform</label>
            <select name="platform" class="form-select">
                <option value="">Semua Platform</option>
                <?php foreach ($platforms as $pf) : ?>
                    <option value="<?= esc($pf->name) ?>" <?= $platform === $pf->name ? 'selected' : '' ?>><?= esc($pf->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label mb-1 small text-muted">Status</label>
            <select name="status" class="form-select">
                <option value="">Semua Status</option>
                <?php foreach ($statuses as $st) : ?>
                    <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Tampilkan</button>
        </div>
    </form>
    <div class="d-flex gap-2">
        <a href="<?= base_url('marketing/rekap') ?>" class="btn btn-light">
            <iconify-icon icon="solar:clipboard-list-bold" class="me-1"></iconify-icon>Rekap Harian
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="mb-0">Data Prospek — <?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></h5>
            <small class="text-muted"><?= $total ?> prospek manual (data dari Kommo tidak ditampilkan di sini).</small>
        </div>
        <?php if ($canWrite) : ?>
            <button type="button" class="btn btn-primary" onclick="openProspekModal()">
                <iconify-icon icon="solar:add-circle-bold" class="me-1"></iconify-icon>+ Tambah Prospek
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($rows)) : ?>
            <div class="text-center py-5">
                <iconify-icon icon="solar:inbox-line-bold" class="text-muted fs-1"></iconify-icon>
                <p class="text-muted mt-2 mb-0">Belum ada prospek untuk periode ini.</p>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table align-middle table-hover" id="prospekTable">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:50px;">No</th>
                            <th class="text-nowrap">Tanggal</th>
                            <th>Nama/Akun</th>
                            <th>Unit</th>
                            <th>Platform</th>
                            <th>No Telp (WA)</th>
                            <th>Keterangan Servis</th>
                            <th class="text-center">Status</th>
                            <th class="text-nowrap">Tgl Booking</th>
                            <th class="text-end">Omset</th>
                            <th>Catatan</th>
                            <?php if ($canWrite) : ?><th class="text-center">Aksi</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $nomorAwal = ($currentPage - 1) * $perPage;
                        $badgeCls = 'bg-light border text-dark';
                        foreach ($rows as $i => $lead) :
                            $nomor = $lead->nomor ?: ($nomorAwal + $i + 1);
                            $svcNo = $lead->service_id ? (isset($serviceMap[(int)$lead->service_id]) ? $serviceMap[(int)$lead->service_id] : '') : '';
                            $unitName = $lead->unit_id ? ($unitMap[(int)$lead->unit_id] ?? '-') : '-';
                        ?>
                            <tr>
                                <td class="text-center text-muted small"><?= (int)$nomor ?></td>
                                <td class="text-nowrap text-muted small"><?= date('d M Y', strtotime($lead->tanggal)) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= esc($lead->nama) ?></div>
                                </td>
                                <td class="small text-nowrap"><?= esc($unitName) ?></td>
                                <td>
                                    <?php if ($lead->platform) : ?>
                                        <span class="badge rounded-pill <?= $badgeCls ?>"><?= esc($lead->platform) ?></span>
                                    <?php else : ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap small"><?= esc($lead->no_telp_wa ?: '-') ?></td>
                                <td class="small"><?= esc($lead->keterangan ?: '-') ?></td>
                                <td class="text-center">
                                    <?php if ($canWrite) : ?>
                                        <button type="button" class="btn btn-sm p-0 border-0 bg-transparent st-change"
                                            data-id="<?= (int)$lead->id ?>"
                                            data-status="<?= esc($lead->status) ?>"
                                            data-service="<?= (int)$lead->service_id ?>"
                                            data-svc="<?= esc($svcNo, 'attr') ?>"
                                            data-unit="<?= (int)$lead->unit_id ?>"
                                            data-tanggal="<?= esc($lead->tanggal) ?>"
                                            data-bs-toggle="modal" data-bs-target="#modalStatusProspek"
                                            title="Ubah status">
                                            <span class="badge rounded-pill <?= $badgeCls ?>">
                                                <?= esc($lead->status) ?> <i class="bi bi-pencil-square ms-1" style="font-size:.6rem;"></i>
                                            </span>
                                        </button>
                                    <?php else : ?>
                                        <span class="badge rounded-pill <?= $badgeCls ?>"><?= esc($lead->status) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap small"><?= $lead->tanggal_booking ? date('d M Y', strtotime($lead->tanggal_booking)) : '-' ?></td>
                                <td class="text-end fw-semibold text-nowrap">
                                    <?= $lead->omset !== null && $lead->omset > 0 ? 'Rp ' . number_format((float)$lead->omset, 0, ',', '.') : '-' ?>
                                    <?php if ($svcNo !== '') : ?>
                                        <div class="small fw-normal text-muted"><?= esc($svcNo) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= esc($lead->catatan ?: '-') ?></td>
                                <?php if ($canWrite) : ?>
                                <td class="text-center text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit"
                                        data-id="<?= (int)$lead->id ?>"
                                        data-tanggal="<?= esc($lead->tanggal) ?>"
                                        data-nama="<?= esc($lead->nama, 'attr') ?>"
                                        data-unit="<?= (int)$lead->unit_id ?>"
                                        data-platform="<?= esc($lead->platform ?? '', 'attr') ?>"
                                        data-no_telp_wa="<?= esc($lead->no_telp_wa ?? '', 'attr') ?>"
                                        data-keterangan="<?= esc($lead->keterangan ?? '', 'attr') ?>"
                                        data-status="<?= esc($lead->status) ?>"
                                        data-tanggal_booking="<?= esc($lead->tanggal_booking ?? '', 'attr') ?>"
                                        data-omset="<?= esc((float)($lead->omset ?? 0), 'attr') ?>"
                                        data-service="<?= (int)$lead->service_id ?>"
                                        data-svc="<?= esc($svcNo, 'attr') ?>"
                                        data-catatan="<?= esc($lead->catatan ?? '', 'attr') ?>"
                                        title="Edit"><i class="bi bi-pencil"></i></button>
                                    <form method="post" action="<?= base_url('marketing/leads/hapus') ?>" class="d-inline"
                                        onsubmit="return confirm('Hapus prospek ini?');">
                                        <input type="hidden" name="id" value="<?= (int)$lead->id ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1) : ?>
                <nav class="mt-3">
                    <ul class="pagination pagination-sm justify-content-end mb-0">
                        <?php
                        $qs = 'bulan=' . $bulan . '&tahun=' . $tahun . '&status=' . urlencode($status) . '&platform=' . urlencode($platform) . '&unit_id=' . (int)$unitId;
                        for ($p = 1; $p <= $totalPages; $p++) : ?>
                            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= base_url('marketing/leads?' . $qs . '&page=' . $p) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($canWrite) : ?>
<!-- Modal Tambah / Edit Prospek -->
<div class="modal fade" id="modalProspek" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="post" action="<?= base_url('marketing/leads/simpan') ?>" id="prospekForm">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="pp_id" value="">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <iconify-icon icon="solar:user-plus-bold" class="text-primary me-1"></iconify-icon>
                        <span id="pp_title">Tambah Prospek</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Tanggal</label>
                            <input type="date" name="tanggal" id="pp_tanggal" class="form-control form-control-sm"
                                value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Platform</label>
                            <select name="platform" id="pp_platform" class="form-select form-select-sm" required>
                                <option value="">— Pilih —</option>
                                <?php foreach ($platforms as $pf) : ?>
                                    <option value="<?= esc($pf->name) ?>"><?= esc($pf->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3" id="pp_unit_col">
                            <label class="form-label small text-muted mb-1">Unit</label>
                            <select name="unit_id" id="pp_unit" class="form-select form-select-sm" required>
                                <option value="">— Pilih Unit —</option>
                                <?php foreach ($units as $u) : ?>
                                    <option value="<?= (int)$u->idunit ?>"><?= esc($u->NAMA_UNIT) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Status</label>
                            <select name="status" id="pp_status" class="form-select form-select-sm">
                                <?php foreach ($statuses as $st) : ?>
                                    <option value="<?= $st ?>"><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Area Service (CLOSED wajib / terpilih) -->
                    <div id="pp_svc_area">
                        <div class="mt-3 position-relative">
                            <label class="form-label small text-muted mb-1">
                                Service <span class="text-danger" id="pp_svc_req">*</span>
                                <span class="text-muted" id="pp_svc_hint_ctx"></span>
                            </label>
                            <input type="text" id="pp_service_search" class="form-control form-control-sm"
                                placeholder="Cari service: ketik ID / no service / nama pelanggan..." autocomplete="off">
                            <input type="hidden" name="service_id" id="pp_service_id" value="">
                            <div class="list-group position-absolute w-100 shadow d-none serv-results"
                                style="z-index:1060;max-height:240px;overflow:auto;" id="pp_service_results"></div>
                        </div>

                        <!-- Preview service (read only) -->
                        <div id="pp_preview" class="d-none mt-2">
                            <div class="border rounded-3 p-3">
                                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                    <strong>Detail Service</strong>
                                    <span class="badge rounded-pill bg-light border text-dark" id="pp_svc_no">-</span>
                                    <span class="badge rounded-pill bg-light border text-dark" id="pp_svc_status">-</span>
                                    <button type="button" class="btn btn-sm btn-link p-0 ms-auto text-muted"
                                        onclick="clearServiceSelection()" title="Hapus pilihan service">
                                        <i class="bi bi-x-circle me-1"></i>Hapus pilihan
                                    </button>
                                </div>
                                <div class="row g-2 small">
                                    <div class="col-md-4"><span class="text-muted">Nama Customer:</span><div><strong id="pp_svc_nama">-</strong></div></div>
                                    <div class="col-md-3"><span class="text-muted">No HP:</span><div><strong id="pp_svc_hp">-</strong></div></div>
                                    <div class="col-md-2"><span class="text-muted">Unit:</span><div><strong id="pp_svc_unit">-</strong></div></div>
                                    <div class="col-md-3"><span class="text-muted">Tgl Service:</span><div><strong id="pp_svc_tgl">-</strong></div></div>
                                    <div class="col-12"><span class="text-muted">Keterangan Service:</span> <span id="pp_svc_ket">-</span></div>
                                    <div class="col-md-4 d-none" id="pp_omset_row">
                                        <span class="text-muted">Omset:</span><div class="fw-semibold" id="pp_svc_omset">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Area data manual (selain CLOSED & belum terhubung service) -->
                    <div id="pp_manual_area" class="d-none">
                        <hr>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-1">Nama / Akun <span class="text-danger">*</span></label>
                                <input type="text" name="nama" id="pp_nama" class="form-control form-control-sm"
                                    placeholder="Nama customer / akun">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-1">No Telp (WA)</label>
                                <input type="text" name="no_telp_wa" id="pp_no_telp" class="form-control form-control-sm" placeholder="08xx">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-1">Keterangan Servis</label>
                                <input type="text" name="keterangan" id="pp_keterangan" class="form-control form-control-sm"
                                    placeholder="Perbaikan yang dibutuhkan">
                            </div>
                        </div>
                    </div>

                    <hr>
                    <div class="row g-2">
                        <div class="col-md-4" id="pp_tgl_booking_col">
                            <label class="form-label small text-muted mb-1">Tanggal Booking</label>
                            <input type="date" name="tanggal_booking" id="pp_tgl_booking" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small text-muted mb-1">Catatan</label>
                            <input type="text" name="catatan" id="pp_catatan" class="form-control form-control-sm">
                        </div>
                    </div>
                    <p class="form-text text-muted mt-2 mb-0" id="pp_hint"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="ppSubmit"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ubah Status Cepat -->
<div class="modal fade" id="modalStatusProspek" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= base_url('marketing/leads/status') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="ms_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <iconify-icon icon="solar:refresh-circle-bold" class="text-primary me-1"></iconify-icon>
                        Ubah Status Prospek
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Status saat ini: <span class="fw-semibold" id="ms_current">-</span>
                    </p>
                    <div class="d-flex flex-column gap-2">
                        <?php
                        $stDesc = [
                            'PROSPEK' => 'Prospek baru, belum ada janji.',
                            'BOOKING' => 'Sudah ada janji booking.',
                            'DATANG'  => 'Prospek datang (sudah jadi service).',
                            'CLOSED'  => 'Selesai — wajib pilih service selesai, omset otomatis (sum sub total).',
                            'BATAL'   => 'Prospek dibatalkan.',
                        ];
                        foreach ($statuses as $st) :
                            $desc = $stDesc[$st] ?? '';
                        ?>
                            <label class="form-check p-3 border rounded-3 d-flex gap-3 align-items-start mb-0 st-option">
                                <input class="form-check-input mt-1" type="radio" name="status" value="<?= $st ?>">
                                <span class="d-block">
                                    <span class="fw-semibold"><?= $st ?></span>
                                    <span class="d-block small text-muted mt-1"><?= $desc ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3 d-none" id="msServiceWrap">
                        <label class="form-label small text-muted mb-1">Service Selesai <span class="text-danger">*</span> <span class="text-muted">(per tanggal prospek, semua cabang)</span></label>
                        <div class="position-relative">
                            <input type="text" id="ms_service_search" class="form-control form-control-sm"
                                placeholder="Ketik ID / no service / nama..." autocomplete="off">
                            <input type="hidden" name="service_id" id="ms_service_id" value="">
                            <div class="list-group position-absolute w-100 shadow d-none serv-results"
                                style="z-index:1060;max-height:200px;overflow:auto;" id="ms_service_results"></div>
                        </div>
                        <p class="small text-muted mt-1 mb-0" id="ms_omset_info"></p>
                    </div>
                    <p class="small text-muted mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Untuk <strong>CLOSED</strong>, service selesai wajib dipilih agar omset terhitung otomatis (sum sub total); tanggal_won otomatis hari ini bila belum terisi.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan Status</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    var PROSPEK_STATUSES = <?= json_encode(array_values($statuses)) ?>;
    var UNITS_MAP = {};
    <?php foreach ($units as $u) : ?>
        UNITS_MAP[<?= (int)$u->idunit ?>] = <?= json_encode($u->NAMA_UNIT) ?>;
    <?php endforeach; ?>

    // ── Service search AJAX ──────────────────────────────────────────
    function initServiceSearch(opts) {
        var input = opts.input, hidden = opts.hidden, resultsEl = opts.results;
        var timer = null;

        function close() { resultsEl.classList.add('d-none'); resultsEl.innerHTML = ''; }

        function render(items) {
            resultsEl.innerHTML = '';
            if (!items.length) {
                resultsEl.innerHTML = '<div class="list-group-item text-muted small">Tidak ditemukan.</div>';
                resultsEl.classList.remove('d-none');
                return;
            }
            items.forEach(function(it) {
                var a = document.createElement('button');
                a.type = 'button';
                a.className = 'list-group-item list-group-item-action text-start py-2';
                a.innerHTML =
                    '<div class="fw-semibold small"><span class="badge rounded-pill bg-light border text-dark me-1">' + it.status_label + '</span>' + it.text +
                    (it.unit_name ? ' <span class="text-muted fw-normal">· ' + it.unit_name + '</span>' : '') + '</div>' +
                    '<div class="small text-muted">Selesai: ' + it.service_date + (it.status_label === 'SELESAI' && it.omset > 0 ? ' · Omset: <strong>Rp ' + Number(it.omset).toLocaleString('id-ID') + '</strong>' : '') + '</div>';
                a.addEventListener('click', function() {
                    hidden.value = it.id;
                    input.value = it.text;
                    if (opts.onSelect) { opts.onSelect(it); }
                    close();
                });
                resultsEl.appendChild(a);
            });
            resultsEl.classList.remove('d-none');
        }

        input.addEventListener('input', function() {
            clearTimeout(timer);
            close();
            var q = input.value.trim();
            if (q.length < 1) { hidden.value = ''; if (opts.onClear) { opts.onClear(); } return; }
            timer = setTimeout(function() {
                var p = opts.params ? opts.params() : {};
                var qs = new URLSearchParams({ q: q });
                if (p.s) qs.set('s', p.s);
                if (p.unit_id) qs.set('unit_id', p.unit_id);
                fetch('<?= base_url('marketing/search_service') ?>?' + qs.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function(r) { return r.json(); })
                    .then(function(d) { render(d.results || []); })
                    .catch(function() { close(); });
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if (e.target !== input && !resultsEl.contains(e.target)) { close(); }
        });
        input.addEventListener('keydown', function(e) { if (e.key === 'Escape') { close(); } });

        return { clear: close };
    }

    // ── Modal Tambah / Edit ──────────────────────────────────────────
    var unitName = function(id) { return UNITS_MAP[id] || '-'; };
    var ppClosed = function() { return document.getElementById('pp_status').value === 'CLOSED'; };
    var ppHasService = function() { return !!document.getElementById('pp_service_id').value; };
    var ppSvcData = null;

    function setText(id, v) { document.getElementById(id).textContent = v === null || v === undefined || v === '' ? '-' : v; }

    function renderPreview(it) {
        ppSvcData = it;
        setText('pp_svc_no', it.text.split('\u00B7')[0].trim());
        setText('pp_svc_status', it.status_label);
        setText('pp_svc_nama', it.nama);
        setText('pp_svc_hp', it.no_hp);
        setText('pp_svc_unit', unitName(it.unit_id));
        setText('pp_svc_tgl', it.service_date);
        setText('pp_svc_ket', it.keterangan);
        if (it.status_label === 'SELESAI') {
            document.getElementById('pp_omset_row').classList.remove('d-none');
            document.getElementById('pp_svc_omset').textContent = 'Rp ' + Number(it.omset || 0).toLocaleString('id-ID');
        } else {
            document.getElementById('pp_omset_row').classList.add('d-none');
        }
    }

    function clearServiceSelection() {
        ppSvcData = null;
        document.getElementById('pp_service_id').value = '';
        document.getElementById('pp_service_search').value = '';
        syncProspekMode();
    }

    function syncProspekMode() {
        var closed = ppClosed();
        var hasSvc = ppHasService();
        var manualArea = document.getElementById('pp_manual_area');
        var preview = document.getElementById('pp_preview');
        var submit = document.getElementById('ppSubmit');
        var hint = document.getElementById('pp_hint');

        // Unit hanya dipakai utk non-CLOSED; CLOSED diambil dari service.
        document.getElementById('pp_unit_col').classList.toggle('d-none', closed);
        document.getElementById('pp_unit').required = !closed;

        // Tanggal booking tidak relevan saat CLOSED.
        document.getElementById('pp_tgl_booking_col').classList.toggle('d-none', closed);
        if (closed) { document.getElementById('pp_tgl_booking').value = ''; }

        // Manual: hanya saat bukan CLOSED dan belum ada service terpilih.
        manualArea.classList.toggle('d-none', closed || hasSvc);
        preview.classList.toggle('d-none', !hasSvc);

        if (hasSvc)      { hint.innerHTML = 'Detail prospek diambil dari service. Anda dapat menyesuaikan Status & Catatan.'; }
        else if (closed) { hint.innerHTML = 'Status <strong>CLOSED</strong> wajib memilih <strong>Service</strong> <strong>SELESAI</strong> per tanggal prospek (semua cabang). Omset otomatis = sum sub total penjualan service.'; }
        else             { hint.innerHTML = 'Belum ada service? Isi <strong>Nama/Akun</strong> secara manual. Service boleh dipilih bila sudah tersedia (opsional).'; }

        submit.disabled = closed && !hasSvc;
        submit.title = (closed && !hasSvc) ? 'Pilih service selesai dahulu' : '';
        document.getElementById('pp_svc_hint_ctx').textContent = closed ? '(wajib, service selesai)' : '(opsional)';
    }

    function fillManualFromService(it) {
        document.getElementById('pp_unit').value = it.unit_id ? String(it.unit_id) : '';
        if (!document.getElementById('pp_tanggal').value && it.service_date) {
            document.getElementById('pp_tanggal').value = it.service_date;
        }
    }

    initServiceSearch({
        input: document.getElementById('pp_service_search'),
        hidden: document.getElementById('pp_service_id'),
        results: document.getElementById('pp_service_results'),
        params: function() {
            // CLOSED → cari per tanggal prospek, SEMUA unit (cabang tampil di hasil).
            if (ppClosed()) {
                return { s: 1, sdate: document.getElementById('pp_tanggal').value || '' };
            }
            return { s: 0, unit_id: document.getElementById('pp_unit').value || 0 };
        },
        onSelect: function(it) {
            renderPreview(it);
            fillManualFromService(it);
            syncProspekMode();
        },
        onClear: function() { syncProspekMode(); }
    });

    document.getElementById('pp_status').addEventListener('change', function() {
        // Status berpindah: jika CLOSED tanpa service → submit terkunci; jika keluar CLOSED → omset tak relevan.
        syncProspekMode();
    });
    document.getElementById('pp_tanggal').addEventListener('change', function() {
        // Ganti tanggal → pilihan service (per tanggal) tidak relevan lagi.
        if (ppClosed()) { clearServiceSelection(); }
    });
    document.getElementById('pp_unit').addEventListener('change', function() {
        clearServiceSelection();
    });

    function openProspekModal() {
        document.getElementById('pp_id').value = '';
        document.getElementById('prospekForm').reset();
        document.getElementById('pp_tanggal').value = '<?= date('Y-m-d') ?>';
        document.getElementById('pp_title').textContent = 'Tambah Prospek';
        document.getElementById('pp_service_search').value = '';
        document.getElementById('pp_service_id').value = '';
        ppSvcData = null;
        syncProspekMode();
        var modal = new bootstrap.Modal(document.getElementById('modalProspek'));
        modal.show();
    }

    document.querySelectorAll('.btn-edit').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('pp_title').textContent = 'Edit Prospek';
            document.getElementById('pp_id').value = this.dataset.id;
            document.getElementById('pp_tanggal').value = this.dataset.tanggal;
            document.getElementById('pp_platform').value = this.dataset.platform;
            document.getElementById('pp_unit').value = this.dataset.unit;
            document.getElementById('pp_status').value = this.dataset.status;
            document.getElementById('pp_tgl_booking').value = this.dataset.tanggal_booking;
            document.getElementById('pp_catatan').value = this.dataset.catatan;
            document.getElementById('pp_nama').value = this.dataset.nama;
            document.getElementById('pp_no_telp').value = this.dataset.no_telp_wa;
            document.getElementById('pp_keterangan').value = this.dataset.keterangan;

            var svcId = this.dataset.service ? parseInt(this.dataset.service, 10) : 0;
            document.getElementById('pp_service_id').value = svcId > 0 ? String(svcId) : '';
            document.getElementById('pp_service_search').value = this.dataset.svc || '';
            if (svcId > 0) {
                renderPreview({
                    text: this.dataset.svc,
                    status_label: this.dataset.status === 'CLOSED' ? 'SELESAI' : 'PROSES',
                    nama: this.dataset.nama,
                    no_hp: this.dataset.no_telp_wa,
                    keterangan: this.dataset.keterangan,
                    unit_id: parseInt(this.dataset.unit || '0', 10),
                    service_date: this.dataset.tanggal,
                    omset: parseFloat(this.dataset.omset || '0')
                });
            } else {
                ppSvcData = null;
            }
            syncProspekMode();
            var modal = new bootstrap.Modal(document.getElementById('modalProspek'));
            modal.show();
        });
    });

    // ── Modal Ubah Status Cepat ──────────────────────────────────────
    var msDate = '';
    document.querySelectorAll('.st-change').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('ms_id').value = this.dataset.id;
            var cur = this.dataset.status;
            msDate = this.dataset.tanggal || '';
            document.querySelectorAll('#modalStatusProspek input[name="status"]').forEach(function(r) {
                r.checked = (r.value === cur);
                r.closest('label').classList.toggle('border-primary', r.value === cur);
                r.closest('label').classList.toggle('shadow-sm', r.value === cur);
            });
            document.getElementById('ms_current').textContent = cur;
            document.getElementById('ms_service_id').value = this.dataset.service || '';
            document.getElementById('ms_service_search').value = this.dataset.svc || '';
            toggleMsService(cur);
        });
    });

    function toggleMsService(status) {
        var wrap = document.getElementById('msServiceWrap');
        var isClosed = (status === 'CLOSED');
        wrap.classList.toggle('d-none', !isClosed);
        document.getElementById('ms_omset_info').textContent = isClosed
            ? ('Cari service SELESAI tanggal ' + (msDate || '-') + ' (semua cabang).' +
               (document.getElementById('ms_service_id').value ? ' Service terpilih: ganti hanya bila perlu.' : ''))
            : '';
    }

    initServiceSearch({
        input: document.getElementById('ms_service_search'),
        hidden: document.getElementById('ms_service_id'),
        results: document.getElementById('ms_service_results'),
        params: function() { return { s: 1, sdate: msDate }; },
        onSelect: function(it) {
            document.getElementById('ms_omset_info').textContent = 'Omset otomatis: Rp ' + Number(it.omset).toLocaleString('id-ID') + (it.unit_name ? ' · Cabang: ' + it.unit_name : '');
        }
    });

    document.querySelectorAll('#modalStatusProspek input[name="status"]').forEach(function(r) {
        r.addEventListener('change', function() {
            document.querySelectorAll('#modalStatusProspek input[name="status"]').forEach(function(x) {
                x.closest('label').classList.toggle('border-primary', x.checked);
                x.closest('label').classList.toggle('shadow-sm', x.checked);
            });
            toggleMsService(this.value);
        });
    });
</script>