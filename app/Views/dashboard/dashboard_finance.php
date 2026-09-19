<style>
    :root {
        --fn-border: #e5e7eb;
        --fn-bg-subtle: #f8f9fb;
        --fn-text-main: #1a1d23;
        --fn-text-muted: #6b7280;
    }

    .fn-hero {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        border-radius: 16px;
        padding: 28px 32px;
        color: #fff;
    }

    .fn-hero .score-value {
        font-size: 42px;
        font-weight: 800;
        line-height: 1;
    }

    .fn-scorecard {
        border: 1px solid var(--fn-border);
        border-radius: 12px;
        background: #fff;
        padding: 16px;
        height: 100%;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .fn-scorecard:hover {
        border-color: #3b82f6;
        box-shadow: 0 2px 10px rgba(59, 130, 246, 0.12);
        transform: translateY(-1px);
    }

    .fn-scorecard.has-score {
        border-left: 3px solid #3b82f6;
    }

    .fn-scorecard.no-score {
        border-left: 3px solid var(--fn-border);
        opacity: 0.75;
    }

    .fn-scorecard .score-num {
        font-size: 26px;
        font-weight: 700;
    }

    .fn-section-title {
        font-size: 14px;
        font-weight: 700;
        color: var(--fn-text-main);
    }

    .fn-nav-tabs .nav-link {
        border: none;
        border-bottom: 2px solid transparent;
        color: var(--fn-text-muted);
        font-size: 13.5px;
        font-weight: 600;
        padding: 10px 16px;
    }

    .fn-nav-tabs .nav-link.active {
        border-bottom: 2px solid #3b82f6;
        color: #1a1d23;
        background: none;
    }

    .fn-input-toggle {
        background: var(--fn-bg-subtle);
        border: 1px solid var(--fn-border);
        border-radius: 10px;
        padding: 12px 16px;
        cursor: pointer;
        user-select: none;
    }

    .fn-input-toggle:hover {
        background: #eef2f7;
    }

    .fn-metric-tile h4 {
        font-weight: 700;
    }

    .fn-cf-scroll {
        overflow-x: auto;
        padding-bottom: 4px;
        -webkit-overflow-scrolling: touch;
    }

    .fn-cf-scroll::-webkit-scrollbar {
        height: 6px;
    }

    .fn-cf-scroll::-webkit-scrollbar-thumb {
        background: var(--fn-border);
        border-radius: 3px;
    }

    .fn-cf-card {
        flex: 0 0 320px;
        min-width: 320px;
    }

    .fn-cf-card .card-body {
        padding: 14px 18px;
    }

    .fn-cf-card h6 {
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        margin-bottom: 6px !important;
    }

    .fn-cf-card h4 {
        font-size: 20px;
        margin-bottom: 2px !important;
    }

    .fn-cf-card small {
        font-size: 11px;
    }
</style>

<div class="card mb-4">
    <div class="card-body">
        <!-- Filter Section -->
        <form method="get" class="mb-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="month" class="form-label">Periode</label>
                    <input type="month" class="form-control" id="month" name="month" value="<?= esc($month ?? date('Y-m')) ?>">
                </div>
                <div class="col-md-3">
                    <label for="unit_id" class="form-label">Unit</label>
                    <select class="form-select" id="unit_id" name="unit_id">
                        <?php foreach ($units ?? [] as $unit): ?>
                            <option value="<?= $unit->idunit ?>" <?= ((int)($unit_id ?? 0) === (int) $unit->idunit ? 'selected' : '') ?>>
                                <?= esc($unit->NAMA_UNIT) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </div>
        </form>

        <?php if (session()->getFlashdata('sukses')): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= esc(session()->getFlashdata('sukses')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('gagal')): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= esc(session()->getFlashdata('gagal')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($unit_id)): ?>

            <!-- Hero: Total Score -->
            <div class="fn-hero d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <div class="text-white-50 small mb-1">Skor KPI Finance — <?= esc($unit_name) ?></div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="score-value"><?= number_format($total_score, 2, ',', '.') ?></span>
                        <span class="text-white-50">/ 100</span>
                    </div>
                    <div class="text-white-50 small mt-1"><?= esc($month) ?></div>
                </div>
                <div class="text-end">
                    <span class="badge bg-light text-dark">Berdasarkan <?= (int) $counted ?> dari <?= count($rows) ?> KPI</span>
                </div>
            </div>

            <!-- Scorecard Grid (clickable → jump to tab) -->
            <div class="row mb-4 g-3">
                <?php foreach ($rows as $row): ?>
                    <?php
                    $hasScore = $row['score'] !== null;
                    $badge = $row['mode'] === 'auto' ? 'bg-info' : 'bg-secondary';
                    $statusText = [
                        'ok' => 'Tersedia',
                        'data_kosong' => 'Data kosong',
                        'belum_dinilai' => 'Belum dinilai',
                        'terisi' => 'Terisi',
                        'gap_piutang' => 'Piutang GAP',
                    ][$row['status']] ?? $row['status'];

                    $tabTarget = [
                        'akurasi' => '#tab-akurasi',
                        'cash_flow' => '#tab-cashflow',
                        'hutang_piutang' => '#tab-hutang',
                        'payroll' => '#tab-payroll',
                    ][$row['code']] ?? '#tab-manual';
                    ?>
                    <div class="col-md-3">
                        <div class="fn-scorecard <?= $hasScore ? 'has-score' : 'no-score' ?>"
                            data-bs-toggle="tab" data-bs-target="<?= $tabTarget ?>"
                            onclick="document.querySelector('button[data-bs-target=\'<?= $tabTarget ?>\']')?.click(); document.getElementById('financeTabs').scrollIntoView({behavior:'smooth', block:'start'});">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="badge <?= $badge ?>"><?= $row['mode'] === 'auto' ? 'Auto' : 'Manual' ?></span>
                                <small class="text-muted">Bobot <?= (int) $row['weight'] ?>%</small>
                            </div>
                            <div class="text-muted small mb-1"><?= esc($labels[$row['code']] ?? $row['code']) ?></div>
                            <div class="score-num <?= $hasScore ? 'text-primary' : 'text-muted' ?>">
                                <?= $hasScore ? number_format($row['score'], 0, ',', '.') : '—' ?>
                            </div>
                            <small class="text-muted">
                                <?= $hasScore ? 'Kontribusi ' . number_format($row['contribution'], 2, ',', '.') : $statusText ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($can_input): ?>
                <!-- Collapsible Input Forms -->
                <div class="mb-4">
                    <div class="fn-input-toggle d-flex justify-content-between align-items-center"
                        data-bs-toggle="collapse" data-bs-target="#inputFormsCollapse">
                        <span class="fn-section-title mb-0"><i class="bi bi-pencil-square me-2"></i>Input Data KPI</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse mt-3" id="inputFormsCollapse">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header bg-white fw-semibold small">Input Omzet Sheet (Harian)</div>
                                    <div class="card-body">
                                        <form method="post" action="<?= base_url('finance/entry/omzet-sheet') ?>">
                                            <input type="hidden" name="unit_id" value="<?= (int) $unit_id ?>">
                                            <div class="mb-3">
                                                <label class="form-label small">Tanggal</label>
                                                <input type="date" class="form-control" name="tanggal" required max="<?= date('Y-m-d') ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small">Omzet Sheet (Rp)</label>
                                                <input type="text" class="form-control" name="omzet_sheet" required placeholder="0">
                                            </div>
                                            <button type="submit" class="btn btn-success btn-sm">Simpan</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header bg-white fw-semibold small">Penilaian Manual KPI</div>
                                    <div class="card-body">
                                        <form method="post" action="<?= base_url('finance/entry/manual') ?>">
                                            <input type="hidden" name="unit_id" value="<?= (int) $unit_id ?>">
                                            <input type="hidden" name="month" value="<?= esc($month) ?>">
                                            <div class="mb-3">
                                                <label class="form-label small">KPI</label>
                                                <select class="form-select" name="kpi_code" required>
                                                    <?php foreach ($labels as $code => $label): ?>
                                                        <?php if (in_array($code, $manual_options, true)): ?>
                                                            <option value="<?= esc($code) ?>"><?= esc($label) ?></option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small">Skor (0-100)</label>
                                                <input type="number" class="form-control" name="score" min="0" max="100" step="0.01" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small">Catatan</label>
                                                <textarea class="form-control" name="notes" rows="2"></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-success btn-sm">Simpan</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tabs Detail -->
            <ul class="nav fn-nav-tabs border-bottom mb-3" id="financeTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-akurasi" type="button">Akurasi Omzet</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-cashflow" type="button">Cash Flow</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-hutang" type="button">Hutang & Piutang</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-payroll" type="button">Payroll</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-manual" type="button">Riwayat Manual</button>
                </li>
            </ul>
            <div class="tab-content">
                <!-- Akurasi -->
                <div class="tab-pane fade show active" id="tab-akurasi">
                    <?php
                    $akurasiTotal = 0;
                    $akurasiCocok = 0;
                    foreach ($akurasi_detail ?? [] as $a) {
                        if ($a['omzet_sheet'] !== null) {
                            $akurasiTotal++;
                            if ($a['is_match']) {
                                $akurasiCocok++;
                            }
                        }
                    }
                    ?>
                    <?php if ($akurasiTotal > 0): ?>
                        <div class="mb-3">
                            <strong>Nilai Akurasi: <?= number_format(($akurasiCocok / $akurasiTotal) * 100, 2, ',', '.') ?>%</strong>
                            <small class="text-muted">(<?= $akurasiCocok ?> / <?= $akurasiTotal ?> hari sesuai)</small>
                        </div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th class="text-end">Omzet ERP (Rp)</th>
                                    <th class="text-end">Omzet Sheet (Rp)</th>
                                    <th class="text-end">Selisih (Rp)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($akurasi_detail ?? [])): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Belum ada input omzet sheet untuk periode ini.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($akurasi_detail ?? [] as $a): ?>
                                    <tr>
                                        <td><?= esc($a['tanggal']) ?></td>
                                        <td class="text-end"><?= number_format($a['omzet_erp'], 0, ',', '.') ?></td>
                                        <td class="text-end"><?= $a['omzet_sheet'] !== null ? number_format($a['omzet_sheet'], 0, ',', '.') : '—' ?></td>
                                        <td class="text-end"><?= $a['selisih'] !== null ? number_format($a['selisih'], 0, ',', '.') : '—' ?></td>
                                        <td>
                                            <?php if ($a['is_match'] === true): ?>
                                                <span class="badge bg-success">Sesuai</span>
                                            <?php elseif ($a['is_match'] === false): ?>
                                                <span class="badge bg-danger">Tidak sesuai</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Belum diinput</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Cash Flow -->
                <div class="tab-pane fade" id="tab-cashflow">
                    <?php $cf = $cashflow_monthly ?? []; ?>
                    <div class="d-flex flex-nowrap gap-3 mb-3 fn-cf-scroll">
                        <div class="fn-cf-card">
                            <div class="card text-bg-success fn-metric-tile h-100">
                                <div class="card-body">
                                    <h6 class="text-white-50 mb-2">Penerimaan (Penjualan + Service)</h6>
                                    <h4 class="text-white mb-0">Rp <?= number_format($cf['detail']['kas_masuk'] ?? 0, 0, ',', '.') ?></h4>
                                    <small class="text-white-50">
                                        Penjualan Rp <?= number_format($cf['detail']['penjualan'] ?? 0, 0, ',', '.') ?>
                                        · Service Rp <?= number_format($cf['detail']['service'] ?? 0, 0, ',', '.') ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="fn-cf-card">
                            <div class="card text-bg-danger fn-metric-tile h-100">
                                <div class="card-body">
                                    <h6 class="text-white-50 mb-2">Kas Keluar</h6>
                                    <h4 class="text-white mb-0">Rp <?= number_format($cf['detail']['kas_keluar'] ?? 0, 0, ',', '.') ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="fn-cf-card">
                            <div class="card text-bg-info fn-metric-tile h-100">
                                <div class="card-body">
                                    <h6 class="text-white-50 mb-2">Net Cash Flow</h6>
                                    <h4 class="text-white mb-0">Rp <?= number_format($cf['detail']['net'] ?? 0, 0, ',', '.') ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="fn-cf-card">
                            <div class="card text-bg-primary fn-metric-tile h-100">
                                <div class="card-body">
                                    <h6 class="text-white-50 mb-2">Score KPI</h6>
                                    <h4 class="text-white mb-0">
                                        <?= isset($cf['score']) && $cf['score'] !== null
                                            ? number_format($cf['score'], 0, ',', '.') . '%'
                                            : '—' ?>
                                    </h4>
                                    <small class="text-white-50">Target CF <?= number_format($cf['detail']['target_persen'] ?? 0, 0, ',', '.') ?>%</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th class="text-end">Masuk (Rp)</th>
                                    <th class="text-end">Keluar (Rp)</th>
                                    <th class="text-end">Net (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $cd = $cashflow_daily ?? []; ?>
                                <?php if (empty($cd['labels'] ?? [])): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Tidak ada transaksi kas pada periode ini.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($cd['labels'] ?? [] as $i => $tanggal): ?>
                                    <tr>
                                        <td><?= esc($tanggal) ?></td>
                                        <td class="text-end"><?= number_format($cd['masuk'][$i] ?? 0, 0, ',', '.') ?></td>
                                        <td class="text-end"><?= number_format($cd['keluar'][$i] ?? 0, 0, ',', '.') ?></td>
                                        <td class="text-end"><?= number_format($cd['net'][$i] ?? 0, 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Hutang & Piutang -->
                <div class="tab-pane fade" id="tab-hutang">
                    <?php $hd = $hutang_detail ?? []; ?>
                    <?php $pd = $piutang_detail ?? []; ?>

                    <?php if (($hd['status'] ?? '') === 'data_kosong' && ($pd['status'] ?? '') === 'gap_no_payment_date'): ?>
                        <div class="alert alert-info mb-0">
                            Tidak ada transaksi pembelian/piutang jatuh tempo pada periode ini.
                        </div>
                    <?php else: ?>
                        <div class="row mb-3 g-3">
                            <div class="col-md-3">
                                <div class="card text-bg-success h-100">
                                    <div class="card-body">
                                        <h6 class="text-white-50 mb-2">Hutang Tepat Waktu</h6>
                                        <h4 class="text-white mb-0"><?= (int) ($hd['detail']['tepat'] ?? 0) ?></h4>
                                        <small class="text-white-50">dari <?= (int) ($hd['detail']['dinilai'] ?? 0) ?> dinilai</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-bg-danger h-100">
                                    <div class="card-body">
                                        <h6 class="text-white-50 mb-2">Hutang Terlambat</h6>
                                        <h4 class="text-white mb-0"><?= (int) ($hd['detail']['terlambat'] ?? 0) ?></h4>
                                        <small class="text-white-50"><?= (int) ($hd['detail']['open'] ?? 0) ?> open</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-bg-primary h-100">
                                    <div class="card-body">
                                        <h6 class="text-white-50 mb-2">Skor Hutang</h6>
                                        <h4 class="text-white mb-0">
                                            <?= isset($hd['score']) && $hd['score'] !== null ? number_format($hd['score'], 2, ',', '.') . '%' : '—' ?>
                                        </h4>
                                        <small class="text-white-50">Tepat / (Tepat + Terlambat)</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-bg-secondary h-100">
                                    <div class="card-body">
                                        <h6 class="text-white-50 mb-2">Skor Piutang</h6>
                                        <h4 class="text-white mb-0">—</h4>
                                        <small class="text-white-50">
                                            <?= ($pd['status'] ?? '') === 'gap_no_payment_date' ? 'GAP tanggal bayar' : ($pd['detail']['overdue'] ?? 0) . ' overdue' ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-lg-6">
                                <div class="card h-100">
                                    <div class="card-header bg-white fw-semibold small">Rincian Hutang (jatuh tempo <?= esc($hd['detail']['start_date'] ?? '—') ?> s/d <?= esc($hd['detail']['end_date'] ?? '—') ?>)</div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>No. Nota</th>
                                                    <th>Supplier</th>
                                                    <th>Jatuh Tempo</th>
                                                    <th class="text-end">Total (Rp)</th>
                                                    <th class="text-end">Sisa (Rp)</th>
                                                    <th>Klasifikasi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($hd['detail']['items'] ?? [])): ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center text-muted">Tidak ada pembelian jatuh tempo pada periode ini.</td>
                                                    </tr>
                                                <?php endif; ?>
                                                <?php foreach ($hd['detail']['items'] ?? [] as $it): ?>
                                                    <tr>
                                                        <td><?= esc($it['no_nota']) ?></td>
                                                        <td><?= esc($it['supplier']) ?></td>
                                                        <td><?= esc($it['jatuh_tempo']) ?></td>
                                                        <td class="text-end"><?= number_format($it['total'], 0, ',', '.') ?></td>
                                                        <td class="text-end"><?= number_format($it['sisa'], 0, ',', '.') ?></td>
                                                        <td>
                                                            <?php
                                                            $cls = $it['klasifikasi'] === 'Tepat Waktu' ? 'bg-success' : 'bg-danger';
                                                            $fallback = (strpos($it['klasifikasi'], 'open') !== false) || (strpos($it['klasifikasi'], 'tanpa bukti') !== false) ? 'bg-warning text-dark' : $cls;
                                                            ?>
                                                            <span class="badge <?= $it['klasifikasi'] === 'Tepat Waktu' ? 'bg-success' : $fallback ?>"><?= esc($it['klasifikasi']) ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card h-100">
                                    <div class="card-header bg-white fw-semibold small">Rincian Piutang (jatuh tempo <?= esc($pd['detail']['start_date'] ?? '—') ?> s/d <?= esc($pd['detail']['end_date'] ?? '—') ?>)</div>
                                    <?php if (($pd['status'] ?? '') === 'gap_no_payment_date'): ?>
                                        <div class="alert alert-warning m-3 py-2 small mb-0">
                                            Ketepatan piutang belum dapat dinilai: tabel <code>pembayaran_piutang</code> tidak menyimpan tanggal bayar (GAP).
                                            Kategori Lunas/Overdue di bawah hanya menunjukkan status saat ini.
                                        </div>
                                    <?php endif; ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Kode</th>
                                                    <th>Pegawai</th>
                                                    <th>Jatuh Tempo</th>
                                                    <th class="text-end">Sisa (Rp)</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($pd['detail']['items'] ?? [])): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted">Tidak ada piutang jatuh tempo pada periode ini.</td>
                                                    </tr>
                                                <?php endif; ?>
                                                <?php foreach ($pd['detail']['items'] ?? [] as $it): ?>
                                                    <tr>
                                                        <td><?= esc($it['kode']) ?></td>
                                                        <td><?= esc($it['pegawai']) ?></td>
                                                        <td><?= esc($it['jatuh_tempo']) ?></td>
                                                        <td class="text-end"><?= number_format($it['sisa'], 0, ',', '.') ?></td>
                                                        <td>
                                                            <?php
                                                            $badgeCls = $it['klasifikasi'] === 'Lunas' ? 'bg-success' : ($it['klasifikasi'] === 'Overdue' ? 'bg-danger' : 'bg-warning text-dark');
                                                            ?>
                                                            <span class="badge <?= $badgeCls ?>"><?= esc($it['klasifikasi']) ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Payroll -->
                <div class="tab-pane fade" id="tab-payroll">
                    <?php $pr = $payroll_detail ?? []; ?>
                    <?php $prd = $pr['detail'] ?? []; ?>

                    <?php if ($can_input && !empty($unit_id)): ?>
                        <div class="card mb-3">
                            <div class="card-header bg-white fw-semibold small">Input Jadwal Gaji (periode <?= esc($month) ?>)</div>
                            <div class="card-body">
                                <form method="post" action="<?= base_url('finance/entry/payroll') ?>" class="row g-2 align-items-end">
                                    <input type="hidden" name="unit_id" value="<?= (int) $unit_id ?>">
                                    <div class="col-md-2">
                                        <label class="form-label small">Jatuh Tempo</label>
                                        <input type="date" class="form-control form-control-sm" name="due_date" required value="<?= esc($month) ?>-25">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Tanggal Bayar (opsional)</label>
                                        <input type="date" class="form-control form-control-sm" name="paid_date">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Total Gaji (Rp)</label>
                                        <input type="text" class="form-control form-control-sm" name="total" required placeholder="0">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small">Catatan</label>
                                        <input type="text" class="form-control form-control-sm" name="notes">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-success btn-sm w-100">Simpan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row mb-3 g-3">
                        <div class="col-md-3">
                            <div class="card text-bg-primary fn-metric-tile h-100">
                                <div class="card-body">
                                    <h6 class="text-white-50 mb-2">Skor Payroll</h6>
                                    <h4 class="text-white mb-0">
                                        <?= isset($pr['score']) && $pr['score'] !== null ? number_format($pr['score'], 2, ',', '.') . '%' : '—' ?>
                                    </h4>
                                    <small class="text-white-50">Tepat / (Tepat + Terlambat)</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-bg-success h-100">
                                <div class="card-body">
                                    <h6 class="text-white-50 mb-2">Tepat Waktu</h6>
                                    <h4 class="text-white mb-0"><?= (int) ($prd['tepat'] ?? 0) ?></h4>
                                    <small class="text-white-50">dari <?= (int) ($prd['dinilai'] ?? 0) ?> dinilai</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-bg-danger h-100">
                                <div class="card-body">
                                    <h6 class="text-white-50 mb-2">Terlambat</h6>
                                    <h4 class="text-white mb-0"><?= (int) ($prd['terlambat'] ?? 0) ?></h4>
                                    <small class="text-white-50"><?= (int) ($prd['open'] ?? 0) ?> open</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-bg-dark h-100">
                                <div class="card-body">
                                    <h6 class="text-white-50 mb-2">Total Payroll</h6>
                                    <h4 class="text-white mb-0">Rp <?= number_format((float) ($prd['total_payroll'] ?? 0), 0, ',', '.') ?></h4>
                                    <small class="text-white-50">periode berjalan</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Periode</th>
                                    <th>Jatuh Tempo</th>
                                    <th>Tanggal Bayar</th>
                                    <th class="text-end">Total (Rp)</th>
                                    <th>Status</th>
                                    <th>Klasifikasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($prd['items'] ?? [])): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            Belum ada jadwal payroll untuk periode ini. Isi form di atas (bagi yang berhak).
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($prd['items'] ?? [] as $it): ?>
                                    <tr>
                                        <td><?= esc($it['period']) ?></td>
                                        <td><?= esc($it['due_date']) ?></td>
                                        <td><?= $it['paid_date'] !== null ? esc($it['paid_date']) : '—' ?></td>
                                        <td class="text-end"><?= number_format($it['total'], 0, ',', '.') ?></td>
                                        <td>
                                            <span class="badge <?= $it['status'] === 'dibayar' ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $it['status'] === 'dibayar' ? 'Dibayar' : 'Rencana' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $plBadge = $it['klasifikasi'] === 'Tepat Waktu' ? 'bg-success'
                                                : (strpos($it['klasifikasi'], 'Terlambat') !== false ? 'bg-danger'
                                                    : 'bg-warning text-dark');
                                            ?>
                                            <span class="badge <?= $plBadge ?>"><?= esc($it['klasifikasi']) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($pr['status'] === 'data_kosong'): ?>
                        <div class="alert alert-info mt-3 mb-0">
                            Belum ada data payroll. Sebelum ada jadwal gaji (due_date) & tanggal bayar, KPI Payroll menunggu input.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Riwayat Manual -->
                <div class="tab-pane fade" id="tab-manual">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>KPI</th>
                                    <th class="text-end">Skor</th>
                                    <th class="text-end">Kontribusi</th>
                                    <th>Catatan</th>
                                    <th>Terakhir Dinilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($manual_records ?? [])): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Belum ada penilaian manual.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($manual_records ?? [] as $rec): ?>
                                    <tr>
                                        <td><?= esc($labels[$rec->kpi_code] ?? $rec->kpi_code) ?></td>
                                        <td class="text-end"><?= $rec->score !== null ? number_format($rec->score, 2, ',', '.') : '—' ?></td>
                                        <td class="text-end"><?= $rec->contribution !== null ? number_format($rec->contribution, 2, ',', '.') : '—' ?></td>
                                        <td><?= esc($rec->notes ?? '') ?></td>
                                        <td><?= $rec->evaluated_at ? date('d M Y H:i', strtotime($rec->evaluated_at)) : '—' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning mb-0">
                Anda belum memiliki unit yang dapat diakses pada Dashboard Finance.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Chevron icon flip on collapse toggle
    document.addEventListener('DOMContentLoaded', function() {
        var toggle = document.querySelector('.fn-input-toggle');
        var collapseEl = document.getElementById('inputFormsCollapse');
        if (toggle && collapseEl) {
            var icon = toggle.querySelector('.bi-chevron-down');
            collapseEl.addEventListener('show.bs.collapse', function() {
                icon.style.transform = 'rotate(180deg)';
            });
            collapseEl.addEventListener('hide.bs.collapse', function() {
                icon.style.transform = 'rotate(0deg)';
            });
        }
    });
</script>
