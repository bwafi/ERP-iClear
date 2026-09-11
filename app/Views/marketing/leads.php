<div class="card shadow-none position-relative overflow-hidden mb-4"
    style="background: linear-gradient(120deg, #0f2b46 0%, #1d4e89 60%, #2a6dbb 100%);">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div class="text-white">
            <h4 class="fw-semibold mb-1 text-white">Lead Marketing</h4>
            <small class="text-white-50">Lead masuk → Follow Up → Won (tertaut Customer) / Lost. Customer WON menjadi dasar KPI Customer, Conversion &amp; Omzet Marketing.</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('marketing') ?>">Marketing KPI</a></li>
                <li class="breadcrumb-item active text-white">Lead Marketing</li>
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

<div class="col-md-12 mb-3">
    <?php
    $counts = ['TOTAL' => count($rows), 'NEW' => 0, 'FOLLOW_UP' => 0, 'WON' => 0, 'LOST' => 0];
    foreach ($rows as $r) {
        $counts[$r->status] = ($counts[$r->status] ?? 0) + 1;
    }
    ?>
    <div class="row g-2">
        <div class="col">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="fs-4 fw-bold text-dark"><?= $counts['TOTAL'] ?></div>
                <div class="text-muted small">Total Lead</div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="fs-4 fw-bold text-secondary"><?= $counts['NEW'] ?></div>
                <div class="text-muted small">New</div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="fs-4 fw-bold text-warning"><?= $counts['FOLLOW_UP'] ?></div>
                <div class="text-muted small">Follow Up</div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="fs-4 fw-bold text-success"><?= $counts['WON'] ?></div>
                <div class="text-muted small">Won</div>
            </div>
        </div>
        <div class="col">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="fs-4 fw-bold text-danger"><?= $counts['LOST'] ?></div>
                <div class="text-muted small">Lost</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><iconify-icon icon="solar:pie-chart-2-bold" class="text-primary me-1"></iconify-icon>Komposisi Lead</h5>
                <small class="text-muted"><?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></small>
            </div>
            <div class="card-body">
                <div id="chartLeadsStatus"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><iconify-icon icon="solar:chart-bold" class="text-primary me-1"></iconify-icon>Komposisi per Source</h5>
                <small class="text-muted">Jumlah lead per channel/source period ini.</small>
            </div>
            <div class="card-body">
                <div id="chartLeadsSource"></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-end justify-content-between">
            <form method="get" class="row g-2 align-items-end">
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
                    <label class="form-label mb-1 small text-muted">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <?php foreach (['NEW', 'FOLLOW_UP', 'WON', 'LOST'] as $st) : ?>
                            <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Tampilkan</button>
                </div>
            </form>
            <div class="d-flex gap-2">
                <a href="<?= base_url('marketing') ?>" class="btn btn-light">
                    <iconify-icon icon="solar:chart-2-bold" class="me-1"></iconify-icon>Dashboard Digital Marketing
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($canWrite) : ?>
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h5 class="mb-0">
                <iconify-icon icon="solar:user-plus-bold" class="text-primary me-1"></iconify-icon> Tambah Lead
            </h5>
        </div>
        <form method="post" action="<?= base_url('marketing/leads/simpan') ?>" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label mb-1 small text-muted">Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small text-muted">Nama / Customer</label>
                <input type="text" name="nama" class="form-control" placeholder="Nama lead" required>
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1 small text-muted">No HP</label>
                <input type="text" name="no_hp" class="form-control" inputmode="numeric" placeholder="08xx">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small text-muted">Channel / Source</label>
                <select name="source_id" class="form-select">
                    <option value="">— Pilih source —</option>
                    <?php foreach ($sources as $s) : ?>
                        <option value="<?= $s->id ?>"><?= esc($s->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1 small text-muted">Tipe</label>
                <select name="ads_organic" class="form-select">
                    <option value="ORGANIC">Organic</option>
                    <option value="ADS">Ads</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1 small text-muted">CS</label>
                <select name="cs" class="form-select">
                    <option value="">— Pilih CS / Kepala —</option>
                    <?php foreach ($csPeoples as $cp) : ?>
                        <option value="<?= esc($cp->NAMA_AKUN) ?>"><?= esc($cp->NAMA_AKUN) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1 small text-muted">Status</label>
                <select name="status" class="form-select">
                    <option>NEW</option>
                    <option>FOLLOW_UP</option>
                    <option>WON</option>
                    <option>LOST</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-success w-100 h-100 d-inline-flex align-items-center justify-content-center gap-1">
                    <iconify-icon icon="solar:add-circle-bold"></iconify-icon> Simpan
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Data Lead</h5>
            <small class="text-muted"><?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?> — klik ⋮ pada baris untuk Follow Up / Won / Lost.</small>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($rows)) : ?>
            <div class="text-center py-5">
                <iconify-icon icon="solar:inbox-line-bold" class="text-muted fs-1"></iconify-icon>
                <p class="text-muted mt-2 mb-0">Belum ada lead untuk periode ini.</p>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table align-middle table-hover" id="leadsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Nama &amp; Kontak</th>
                            <th>Source</th>
                            <th class="text-center">Tipe</th>
                            <th>CS</th>
                            <th class="text-center">Status</th>
                            <th>Customer</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $lead) :
                            $src = isset($sourceMap[(int)$lead->source_id]) ? $sourceMap[(int)$lead->source_id] : null;
                            $cust = (isset($customerMap[(int)$lead->customer_id]) && $lead->customer_id) ? $customerMap[(int)$lead->customer_id] : null;
                        ?>
                            <tr>
                                <td class="text-nowrap text-muted small"><?= date('d M Y', strtotime($lead->tanggal)) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= esc($lead->nama) ?></div>
                                    <?php if ($lead->kommo_lead_id) : ?>
                                        <div class="small text-muted"><i class="bi bi-diagram-3 me-1"></i>Kommo #<?= (int)$lead->kommo_lead_id ?></div>
                                    <?php endif; ?>
                                    <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= esc($lead->no_hp ?: '-') ?></div>
                                </td>
                                <td>
                                    <?php if ($src) : ?>
                                        <span class="badge rounded-pill bg-light border text-dark">
                                            <i class="bi bi-globe2 me-1"></i><?= esc($src->name) ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($lead->ads_organic === 'ADS') : ?>
                                        <span class="badge rounded-pill text-bg-primary"><i class="bi bi-megaphone me-1"></i>ADS</span>
                                    <?php else : ?>
                                        <span class="badge rounded-pill text-bg-light text-dark border"><i class="bi bi-tree me-1"></i>ORGANIC</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-white text-dark border fw-normal"><?= esc($lead->cs ?: '-') ?></span></td>
                                <td class="text-center">
                                    <?php
                                    $st = $lead->status;
                                    $stBadge = $st === 'WON' ? 'text-bg-success'
                                        : ($st === 'LOST' ? 'text-bg-danger'
                                        : ($st === 'FOLLOW_UP' ? 'text-bg-warning' : 'text-bg-secondary'));
                                    $stIcon = $st === 'WON' ? 'check-circle' : ($st === 'LOST' ? 'x-circle' : ($st === 'FOLLOW_UP' ? 'arrow-repeat' : 'plus-circle'));
                                    ?>
                                    <span class="badge rounded-pill <?= $stBadge ?> text-nowrap">
                                        <i class="bi bi-<?= $stIcon ?> me-1"></i><?= $st ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($cust) : ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="avatar avatar-sm bg-success-subtle text-success rounded-circle d-inline-flex align-items-center justify-content-center fw-bold">
                                                <?= strtoupper(substr($cust->nama, 0, 1)) ?>
                                            </span>
                                            <div>
                                                <div class="fw-semibold small"><?= esc($cust->nama) ?></div>
                                                <div class="small text-muted">#<?= (int)$cust->id_pelanggan ?></div>
                                            </div>
                                        </div>
                                    <?php elseif ($lead->status === 'WON') : ?>
                                        <span class="text-danger small"><i class="bi bi-broken-link"></i> Customer dihapus</span>
                                    <?php else : ?>
                                        <span class="text-muted small">Belum Won</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($canWrite) : ?>
                                        <?php if (in_array($lead->status, ['NEW', 'FOLLOW_UP'], true)) : ?>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal" data-bs-target="#wonModal"
                                                    data-lead-id="<?= $lead->id ?>"
                                                    data-lead-nama="<?= esc($lead->nama) ?>"
                                                    data-lead-hp="<?= esc($lead->no_hp) ?>">
                                                    <i class="bi bi-check2-circle me-1"></i>Won
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
                                                    <span class="visually-hidden">Menu</span>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    <li>
                                                        <form method="post" action="<?= base_url('marketing/leads/status') ?>" class="m-0">
                                                            <input type="hidden" name="id" value="<?= $lead->id ?>">
                                                            <input type="hidden" name="status" value="FOLLOW_UP">
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="bi bi-arrow-repeat me-2"></i>Follow Up
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="post" action="<?= base_url('marketing/leads/status') ?>" class="m-0" onsubmit="return confirm('Tandai lead sebagai LOST?');">
                                                            <input type="hidden" name="id" value="<?= $lead->id ?>">
                                                            <input type="hidden" name="status" value="LOST">
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="bi bi-x-circle me-2"></i>Lost
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="post" action="<?= base_url('marketing/leads/hapus') ?>" class="m-0" onsubmit="return confirm('Hapus lead ini?');">
                                                            <input type="hidden" name="id" value="<?= $lead->id ?>">
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="bi bi-trash me-2"></i>Hapus
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        <?php else : ?>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    <?php if ($lead->status === 'LOST') : ?>
                                                        <li>
                                                            <form method="post" action="<?= base_url('marketing/leads/status') ?>" class="m-0">
                                                                <input type="hidden" name="id" value="<?= $lead->id ?>">
                                                                <input type="hidden" name="status" value="FOLLOW_UP">
                                                                <button type="submit" class="dropdown-item">
                                                                    <i class="bi bi-arrow-repeat me-2"></i>Buka Kembali (Follow Up)
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <form method="post" action="<?= base_url('marketing/leads/hapus') ?>" class="m-0" onsubmit="return confirm('Hapus lead ini?');">
                                                            <input type="hidden" name="id" value="<?= $lead->id ?>">
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="bi bi-trash me-2"></i>Hapus
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <?php
                $qs = 'bulan=' . $bulan . '&tahun=' . $tahun . ($status !== '' ? '&status=' . $status : '');
                $window = 2;
                $start = max(1, $currentPage - $window);
                $end = min($totalPages, $currentPage + $window);
                $showStart = $start > 1;
                $showEnd = $end < $totalPages;
                ?>
                <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top">
                    <span class="text-muted small">
                        Menampilkan <?= ($currentPage - 1) * $perPage + 1 ?> - <?= min($currentPage * $perPage, $total) ?> dari <?= $total ?> lead
                    </span>
                    <nav aria-label="Paginasi lead">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= base_url('marketing/leads?' . $qs . '&page=' . ($currentPage - 1)) ?>" tabindex="-1">
                                    <iconify-icon icon="solar:arrow-left-broken" width="18" height="18"></iconify-icon>
                                </a>
                            </li>
                            <?php if ($showStart): ?>
                                <li class="page-item"><a class="page-link" href="<?= base_url('marketing/leads?' . $qs . '&page=1') ?>">1</a></li>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= base_url('marketing/leads?' . $qs . '&page=' . $i) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($showEnd): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                <li class="page-item"><a class="page-link" href="<?= base_url('marketing/leads?' . $qs . '&page=' . $totalPages) ?>"><?= $totalPages ?></a></li>
                            <?php endif; ?>
                            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= base_url('marketing/leads?' . $qs . '&page=' . ($currentPage + 1)) ?>">
                                    <iconify-icon icon="solar:arrow-right-broken" width="18" height="18"></iconify-icon>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Mark Won -->
<div class="modal fade" id="wonModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= base_url('marketing/leads/status') ?>" class="modal-content shadow-lg">
            <input type="hidden" name="id" id="wonLeadId" value="">
            <input type="hidden" name="status" value="WON">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-trophy text-warning me-2"></i>Tandai Lead Menjadi WON</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="rounded-3 border mb-3 py-3 px-3" style="background:#f1f6fc;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-sm bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center fw-bold">
                            <i class="bi bi-person-fill"></i>
                        </span>
                        <div>
                            <div class="fw-semibold text-dark" id="wonLeadNama" style="color:#111c2d !important;">-</div>
                            <div class="small" id="wonLeadHp" style="color:#7c8fac !important;">-</div>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Customer Hasil Conversion <span class="text-danger">*</span></label>
                    <select name="customer_id" id="wonCustomerSelect" class="form-select select2" required>
                        <option value="">— Pilih customer hasil conversion —</option>
                        <?php foreach ($customers as $cust) : ?>
                            <option value="<?= $cust->id_pelanggan ?>"><?= esc($cust->nama) ?> (<?= esc($cust->no_hp) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Customer harus sudah terdaftar di master pelanggan (Won → link customer).</div>
                </div>
                <div class="mb-1">
                    <label class="form-label fw-semibold">Tanggal Won</label>
                    <input type="date" name="tanggal_won" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-trophy me-1"></i>Konfirmasi Won</button>
            </div>
        </form>
    </div>
</div>

<style>
    .modal {
        z-index: 1056 !important;
    }
    .modal-backdrop {
        z-index: 1055 !important;
    }
    .modal .select2-container {
        z-index: 1075 !important;
    }
    .select2-dropdown,
    .select2-container--open .select2-dropdown {
        z-index: 1075 !important;
    }
</style>

<script>
    var wonModal = document.getElementById('wonModal');
    if (wonModal) {
        wonModal.addEventListener('show.bs.modal', function(event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            document.getElementById('wonLeadId').value = btn.getAttribute('data-lead-id');
            document.getElementById('wonLeadNama').textContent = btn.getAttribute('data-lead-nama') || '-';
            document.getElementById('wonLeadHp').textContent = '+' + (btn.getAttribute('data-lead-hp') || '-');
        });
    }

    // Donut komposisi lead
    if (window.ApexCharts && document.querySelector('#chartLeadsStatus')) {
        var lb = <?= json_encode($leadsByStatus) ?>;
        new ApexCharts(document.querySelector('#chartLeadsStatus'), {
            chart: { type: 'pie', fontFamily: 'inherit', toolbar: { show: false }, height: 260 },
            labels: ['NEW', 'FOLLOW_UP', 'WON', 'LOST'],
            series: [lb.NEW, lb.FOLLOW_UP, lb.WON, lb.LOST],
            colors: ['#adb5bd', '#ffc107', '#198754', '#dc3545'],
            legend: { position: 'bottom' },
            stroke: { width: 0 },
            dataLabels: { enabled: true, formatter: function(v) { return Math.round(v) + '%'; } },
        }).render();
    }

    // Bar komposisi per source
    if (window.ApexCharts && document.querySelector('#chartLeadsSource')) {
        <?php $srcCount = [];
        foreach ($rows as $rl) {
            $sn = isset($sourceMap[(int)$rl->source_id]) ? $sourceMap[(int)$rl->source_id]->name : 'Tanpa Source';
            $srcCount[$sn] = ($srcCount[$sn] ?? 0) + 1;
        } ?>
        var srcLabels = <?= json_encode(array_keys($srcCount)) ?>;
        var srcData = <?= json_encode(array_values($srcCount)) ?>;
        new ApexCharts(document.querySelector('#chartLeadsSource'), {
            chart: { type: 'bar', fontFamily: 'inherit', toolbar: { show: false }, height: 260 },
            series: [{ name: 'Lead', data: srcData }],
            xaxis: { categories: srcLabels },
            plotOptions: { bar: { columnWidth: '50%', borderRadius: 3 } },
            colors: ['#1d4e89'],
            dataLabels: { enabled: true },
            legend: { show: false },
        }).render();
    }
</script>