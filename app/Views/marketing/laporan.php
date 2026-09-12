<div class="card shadow-none position-relative overflow-hidden mb-4"
    style="background: linear-gradient(120deg, #0f2b46 0%, #1d4e89 60%, #2a6dbb 100%);">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div class="text-white">
            <h4 class="fw-semibold mb-1 text-white">Laporan Digital Marketing</h4>
            <small class="text-white-50">Rangkuman &amp; evaluasi performa iklan — read only, bersumber dari data Ads yang tercatat di sistem.</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-white-50 text-decoration-none" href="<?= base_url('marketing') ?>">Dashboard Digital Marketing</a></li>
                <li class="breadcrumb-item active text-white">Laporan Digital Marketing</li>
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

<?php
$rupiah = fn($v) => $v === null ? '-' : 'Rp' . number_format((float)$v, 0, ',', '.');
$pct    = fn($v) => $v === null ? '-' : number_format((float)$v, 2, ',', '.') . '%';
$numfmt = fn($v) => $v === null ? '-' : number_format((float)$v, 0, ',', '.');
$tglKeys = array_keys($perTgl);
sort($tglKeys);
$hasDaily = !empty($tglKeys);
?>

<div class="alert alert-info d-flex align-items-start gap-2 mb-3 py-2 px-3 small">
    <iconify-icon icon="solar:info-circle-bold" class="fs-5 mt-1 flex-shrink-0"></iconify-icon>
    <div class="mt-1">
        Sumber data: <strong>menu Performa Ads</strong> (Daily Budget, Spending Ads, PPN, Objective, Reach, Impression, Klik &amp; Hasil per campaign × cabang × channel).
        Kolom tampil <strong>–</strong> bila datanya belum dicatat. Laporan ini murni Ads (tidak mencampur
        Kommo / Detail Prospek / Rekap Harian / Performa Channel).
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-end justify-content-between">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-1 small text-muted">Bulan</label>
                    <select name="bulan" class="form-select form-select-sm">
                        <?php for ($i = 1; $i <= 12; $i++) : ?>
                            <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-1 small text-muted">Tahun</label>
                    <select name="tahun" class="form-select form-select-sm">
                        <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++) : ?>
                            <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-1 small text-muted">Campaign</label>
                    <select name="campaign" class="form-select form-select-sm">
                        <option value="">Semua Campaign</option>
                        <?php foreach ($campaigns as $c) : ?>
                            <option value="<?= esc($c) ?>" <?= $kampanye === $c ? 'selected' : '' ?>><?= esc($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <iconify-icon icon="solar:filter-bold"></iconify-icon> Tampilkan
                    </button>
                </div>
            </form>
            <div class="d-flex gap-2">
                <a href="<?= base_url('marketing/ads_performa') ?>" class="btn btn-light btn-sm">
                    <iconify-icon icon="solar:chart-2-bold" class="me-1"></iconify-icon>Performa Ads
                </a>
                <a href="<?= base_url('marketing') ?>" class="btn btn-light btn-sm">
                    <iconify-icon icon="solar:chart-2-bold" class="me-1"></iconify-icon>Dashboard Digital Marketing
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (empty($daily)) : ?>
    <div class="card shadow-sm border-0">
        <div class="card-body text-center py-5">
            <iconify-icon icon="solar:chart-down-bold" width="48" height="48" class="text-muted mb-2"></iconify-icon>
            <h6 class="text-muted mb-0">Belum ada data Ads pada <?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?>.</h6>
            <small class="text-muted">Isi lewat menu Performa Ads, lalu kembali ke laporan ini.</small>
        </div>
    </div>
<?php else : ?>

<!-- ── Summary KPI ─────────────────────────────────────────────── -->
<div class="row g-2 mb-2">
    <?php
    $kpiCards = [
        ['Total Spending Ads', $rupiah($sum['spending']), 'solar:wallet-bold', 'primary'],
        ['Total PPN', $rupiah($sum['ppn']), 'solar:percent-bold', 'secondary'],
        ['Total Biaya (+ PPN)', $rupiah($sum['biaya']), 'solar:dollar-minimalistic-bold', 'info'],
        ['Total Reach', $numfmt($sum['reach']), 'solar:users-group-rounded-bold', 'primary'],
        ['Total Impression', $numfmt($sum['impression']), 'solar:eye-bold', 'info'],
        ['Total Klik', $numfmt($sum['klik']), 'solar:cursor-bold', 'warning'],
        ['CTR', $pct($sum['ctr']), 'solar:chart-bold', 'success'],
        ['CPC', $rupiah($sum['cpc']), 'solar:tag-price-bold', 'danger'],
        ['CPM', $rupiah($sum['cpm']), 'solar:banknote-bold', 'warning'],
        ['Frequency', $sum['freq'] === null ? '-' : number_format((float)$sum['freq'], 2, ',', '.') . 'x', 'solar:repeat-bold', 'secondary'],
        ['Total Hasil Meta', $numfmt($sum['hasil']), 'solar:check-circle-bold', 'success'],
        ['CPR Platform', $rupiah($sum['cpr']), 'solar:money-bag-bold', 'danger'],
    ];
    foreach ($kpiCards as $kc) :
        [$label, $value, $icon, $color] = $kc;
    ?>
        <div class="col-6 col-md-4 col-lg-3 col-xl-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-center rounded-3 text-white text-bg-<?= $color ?> mb-2"
                        style="width:34px;height:34px;">
                        <iconify-icon icon="<?= $icon ?>" width="18" height="18"></iconify-icon>
                    </div>
                    <h5 class="mb-0 fw-semibold text-truncate" title="<?= esc($value) ?>"><?= esc($value) ?></h5>
                    <small class="text-muted d-block text-truncate"><?= esc($label) ?></small>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ── Grafik ──────────────────────────────────────────────────── -->
<div class="row g-2 mb-3">
    <?php
    $chartSpendLabels = array_map(fn($d) => date('d/m', strtotime($d)), $tglKeys);
    $chartSpendValues = array_map(fn($d) => $perTgl[$d], $tglKeys);
    $chartDefs = [
        ['chartSpending', 'Spending Harian', 'Bar', $hasDaily],
        ['chartKlik', 'Klik Harian', 'Bar', false],
        ['chartCtr', 'CTR Harian', 'Line', false],
        ['chartHasil', 'Hasil Meta', 'Bar', false],
    ];
    foreach ($chartDefs as $cd) :
        [$id, $title, $type, $hasData] = $cd;
        $tip = ['chartSpending' => 'Tanggal → Spending Ads (dari Performa Ads)', 'chartKlik' => 'Tanggal → Klik', 'chartCtr' => 'Tanggal → CTR (%)', 'chartHasil' => 'Tanggal → Hasil Meta'][$id];
    ?>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 small fw-semibold">
                        <iconify-icon icon="solar:chart-2-bold" class="text-primary me-1"></iconify-icon><?= $title ?>
                    </h6>
                </div>
                <div class="card-body py-2">
                    <?php if ($hasData) : ?>
                        <div id="<?= $id ?>"></div>
                    <?php else : ?>
                        <div class="text-center text-muted py-4">
                            <iconify-icon icon="solar:chart-down-bold" width="28" height="28" class="mb-1 text-muted"></iconify-icon>
                            <div class="small">Data harian belum tersedia.<br><span class="text-muted-50"><?= esc($tip) ?></span></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- ── Performa Harian ─────────────────────────────────────────── -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white">
        <h5 class="mb-0"><iconify-icon icon="solar:calendar-bold" class="text-primary me-1"></iconify-icon>Performa Harian</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="small text-muted">
                    <th style="width:32px;"></th>
                    <th>Tanggal</th>
                    <th>Campaign</th>
                    <th class="text-end">Daily Budget</th>
                    <th class="text-end">Spending</th>
                    <th class="text-end">PPN Value</th>
                    <th class="text-end">Reach</th>
                    <th class="text-end">Impression</th>
                    <th class="text-end">Klik</th>
                    <th class="text-end">CTR</th>
                    <th class="text-end">CPC</th>
                    <th class="text-end">CPM</th>
                    <th class="text-end">Hasil</th>
                    <th class="text-end">CPR</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 0;
                foreach ($daily as $row) : $i++; ?>
                    <tr>
                        <td>
                            <button type="button" class="btn btn-sm btn-link p-0 text-primary detail-toggle"
                                data-target="daily-detail-<?= $i ?>" title="Detail">
                                <iconify-icon icon="solar:alt-arrow-down-bold" width="16" height="16"></iconify-icon>
                            </button>
                        </td>
                        <td class="small text-nowrap"><?= $row['tanggal'] !== '' ? date('d M Y', strtotime($row['tanggal'])) : '<span class="text-muted">Periode</span>' ?></td>
                        <td class="small"><?= esc($row['campaign']) ?: '<span class="text-muted">—</span>' ?></td>
                        <td class="small text-end text-muted"><?= $row['budget'] > 0 ? $rupiah($row['budget']) : '-' ?></td>
                        <td class="small text-end fw-semibold"><?= $rupiah($row['spending']) ?></td>
                        <td class="small text-end"><?= $row['spending'] > 0 && $row['ppn'] > 0 ? $rupiah($row['spending'] + $row['spending'] * $row['ppn'] / 100) : '-' ?></td>
                        <td class="small text-end text-muted"><?= $row['reach'] > 0 ? $numfmt($row['reach']) : '-' ?></td>
                        <td class="small text-end text-muted"><?= $row['impression'] > 0 ? $numfmt($row['impression']) : '-' ?></td>
                        <td class="small text-end text-muted"><?= $row['klik'] > 0 ? $numfmt($row['klik']) : '-' ?></td>
                        <td class="small text-end text-muted"><?= $row['ctr'] !== null ? $pct($row['ctr']) : '-' ?></td>
                        <td class="small text-end text-muted"><?= $row['cpc'] !== null ? $rupiah($row['cpc']) : '-' ?></td>
                        <td class="small text-end text-muted"><?= $row['cpm'] !== null ? $rupiah($row['cpm']) : '-' ?></td>
                        <td class="small text-end text-muted"><?= $row['hasil'] > 0 ? $numfmt($row['hasil']) : '-' ?></td>
                        <td class="small text-end text-muted"><?= $row['cpr'] !== null ? $rupiah($row['cpr']) : '-' ?></td>
                    </tr>
                    <tr class="detail-row d-none" id="daily-detail-<?= $i ?>">
                        <td colspan="14" class="ps-4 py-2">
                            <div class="row g-2 small">
                                <div class="col-md-3"><span class="text-muted">Channel:</span> <?= !empty($row['channels']) ? esc(implode(', ', $row['channels'])) : '-' ?></div>
                                <div class="col-md-3"><span class="text-muted">Cabang:</span> <?= !empty($row['units']) ? esc(implode(', ', $row['units'])) : '-' ?></div>
                                <div class="col-md-3"><span class="text-muted">Jumlah entri:</span> <?= (int)$row['entri'] ?></div>
                                <div class="col-md-3"><span class="text-muted">Objective:</span> <?= $row['objective'] !== '' ? esc($row['objective']) : '-' ?></div>
                                <div class="col-md-3"><span class="text-muted">PPN:</span> <?= $row['ppn'] > 0 ? number_format($row['ppn'], 1, ',', '.') . '%' : '-' ?></div>
                                <div class="col-md-12"><span class="text-muted">Keterangan:</span> <?= $row['keterangan'] !== '' ? esc($row['keterangan']) : '-' ?></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Insight & Evaluasi ──────────────────────────────────────── -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <h5 class="mb-0"><iconify-icon icon="solar:graph-new-bold" class="text-primary me-1"></iconify-icon>Insight &amp; Evaluasi</h5>
        <span class="badge text-bg-light border text-muted"><?= date('F', mktime(0, 0, 0, $bulan, 1)) ?> <?= $tahun ?></span>
    </div>
    <div class="card-body">
        <div class="row g-2 mb-2">
            <div class="col-md-6 col-xl-3">
                <div class="border rounded-3 p-3 h-100 bg-light">
                    <div class="small text-muted mb-1">Spending tertinggi (Kampanye)</div>
                    <?php if ($topCampaignSpend) : ?>
                        <div class="fw-semibold"><?= esc($topCampaignSpend['campaign']) ?></div>
                        <div class="small text-muted">Spending: <strong><?= $rupiah($topCampaignSpend['spending']) ?></strong></div>
                    <?php else : ?>
                        <div class="text-muted">-</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="border rounded-3 p-3 h-100 bg-light">
                    <div class="small text-muted mb-1">Kampanye klik terbanyak</div>
                    <?php
                    $topKlikCampaign = null;
                    foreach ($daily as $dRow) {
                        if ($dRow['klik'] <= 0 || $dRow['campaign'] === '') {
                            continue;
                        }
                        if ($topKlikCampaign === null || $dRow['klik'] > $topKlikCampaign['klik']) {
                            $topKlikCampaign = ['campaign' => $dRow['campaign'], 'klik' => $dRow['klik']];
                        }
                    }
                    ?>
                    <?php if ($topKlikCampaign) : ?>
                        <div class="fw-semibold"><?= esc($topKlikCampaign['campaign']) ?></div>
                        <div class="small text-muted">Klik: <strong><?= $numfmt($topKlikCampaign['klik']) ?></strong></div>
                    <?php else : ?>
                        <div class="text-muted">-</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="border rounded-3 p-3 h-100 bg-light">
                    <div class="small text-muted mb-1">CTR tertinggi</div>
                    <?php
                    $topCtrCampaign = null;
                    foreach ($daily as $dRow) {
                        if ($dRow['impression'] <= 0 || $dRow['campaign'] === '') {
                            continue;
                        }
                        $ctr = $dRow['klik'] / $dRow['impression'] * 100;
                        if ($topCtrCampaign === null || $ctr > $topCtrCampaign['ctr']) {
                            $topCtrCampaign = ['campaign' => $dRow['campaign'], 'ctr' => $ctr];
                        }
                    }
                    ?>
                    <?php if ($topCtrCampaign) : ?>
                        <div class="fw-semibold"><?= esc($topCtrCampaign['campaign']) ?></div>
                        <div class="small text-muted">CTR: <strong><?= number_format($topCtrCampaign['ctr'], 2, ',', '.') ?>%</strong></div>
                    <?php else : ?>
                        <div class="text-muted">-</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="border rounded-3 p-3 h-100 bg-light">
                    <div class="small text-muted mb-1">Hasil Meta tertinggi</div>
                    <?php
                    $topHasilCampaign = null;
                    foreach ($daily as $dRow) {
                        if ($dRow['hasil'] <= 0 || $dRow['campaign'] === '') {
                            continue;
                        }
                        if ($topHasilCampaign === null || $dRow['hasil'] > $topHasilCampaign['hasil']) {
                            $topHasilCampaign = ['campaign' => $dRow['campaign'], 'hasil' => $dRow['hasil']];
                        }
                    }
                    ?>
                    <?php if ($topHasilCampaign) : ?>
                        <div class="fw-semibold"><?= esc($topHasilCampaign['campaign']) ?></div>
                        <div class="small text-muted">Hasil: <strong><?= $numfmt($topHasilCampaign['hasil']) ?></strong></div>
                    <?php else : ?>
                        <div class="text-muted">-</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100">
                    <h6 class="small fw-semibold text-success mb-2">Performa terbaik</h6>
                    <?php if ($bestDay) : ?>
                        <div class="fw-semibold"><?= date('d F Y', strtotime($bestDay['tanggal'])) ?></div>
                        <div class="small text-muted">Spending: <?= $rupiah($bestDay['spending']) ?> · Klik: <?= $bestDay['klik'] > 0 ? $numfmt($bestDay['klik']) : '-' ?> · CTR: <?= $bestDay['ctr'] !== null ? $pct($bestDay['ctr']) : '-' ?> · CPC: <?= $bestDay['cpc'] !== null ? $rupiah($bestDay['cpc']) : '-' ?></div>
                        <div class="small text-muted-50 mt-1">Urutan berdasarkan spending pada periode terfilter.</div>
                    <?php else : ?>
                        <div class="text-muted small">Tidak ada hari dengan tanggal pada periode ini.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100">
                    <h6 class="small fw-semibold text-danger mb-2">Performa terendah</h6>
                    <?php if ($worstDay) : ?>
                        <div class="fw-semibold"><?= date('d F Y', strtotime($worstDay['tanggal'])) ?></div>
                        <div class="small text-muted">Spending: <?= $rupiah($worstDay['spending']) ?></div>
                    <?php else : ?>
                        <div class="text-muted small">Tidak ada hari dengan tanggal pada periode ini.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($metricsPerChannel !== null && $metricsPerChannel !== []) : ?>
            <div class="mt-3">
                <h6 class="small fw-semibold text-primary mb-2">Performa per Saluran (data Ads)</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-muted">
                                <th>Saluran</th>
                                <th class="text-end">Spending</th>
                                <th class="text-end">Reach</th>
                                <th class="text-end">Impression</th>
                                <th class="text-end">Klik</th>
                                <th class="text-end">CTR</th>
                                <th class="text-end">CPC</th>
                                <th class="text-end">CPM</th>
                                <th class="text-end">Freq</th>
                                <th class="text-end">Hasil</th>
                                <th class="text-end">CPR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($metricsPerChannel as $pc) : ?>
                                <tr>
                                    <td class="small fw-semibold"><?= esc($pc['nama_channel']) ?></td>
                                    <td class="small text-end"><?= $rupiah($pc['spending'] > 0 ? $pc['spending'] : null) ?></td>
                                    <td class="small text-end"><?= $numfmt($pc['reach'] > 0 ? $pc['reach'] : null) ?></td>
                                    <td class="small text-end"><?= $numfmt($pc['impression'] > 0 ? $pc['impression'] : null) ?></td>
                                    <td class="small text-end"><?= $numfmt($pc['klik'] > 0 ? $pc['klik'] : null) ?></td>
                                    <td class="small text-end"><?= $pct($pc['ctr']) ?></td>
                                    <td class="small text-end"><?= $rupiah($pc['cpc']) ?></td>
                                    <td class="small text-end"><?= $rupiah($pc['cpm']) ?></td>
                                    <td class="small text-end"><?= $pc['freq'] === null ? '-' : number_format((float)$pc['freq'], 2, ',', '.') . 'x' ?></td>
                                    <td class="small text-end"><?= $numfmt($pc['hasil'] > 0 ? $pc['hasil'] : null) ?></td>
                                    <td class="small text-end"><?= $rupiah($pc['cpr']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($metricsPerUnit !== null && $metricsPerUnit !== []) : ?>
            <div class="mt-3">
                <h6 class="small fw-semibold text-primary mb-2">Performa per Cabang (data Ads)</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-muted">
                                <th>Cabang</th>
                                <th class="text-end">Biaya Harian</th>
                                <th class="text-end">Daily Budget</th>
                                <th class="text-end">PPN</th>
                                <th class="text-end">Reach</th>
                                <th class="text-end">Impression</th>
                                <th class="text-end">Klik</th>
                                <th class="text-end">CTR</th>
                                <th class="text-end">CPC</th>
                                <th class="text-end">CPM</th>
                                <th class="text-end">Freq</th>
                                <th class="text-end">Hasil</th>
                                <th class="text-end">CPR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($metricsPerUnit as $pu) : ?>
                                <tr>
                                    <td class="small fw-semibold"><?= esc($pu['nama_unit']) ?></td>
                                    <td class="small text-end"><?= $rupiah($pu['spending'] > 0 ? $pu['spending'] : null) ?></td>
                                    <td class="small text-end"><?= $rupiah($pu['daily_budget'] > 0 ? $pu['daily_budget'] : null) ?></td>
                                    <td class="small text-end"><?= $pu['ppn'] > 0 ? number_format((float)$pu['ppn'], 1, ',', '.') . '%' : '-' ?></td>
                                    <td class="small text-end"><?= $numfmt($pu['reach'] > 0 ? $pu['reach'] : null) ?></td>
                                    <td class="small text-end"><?= $numfmt($pu['impression'] > 0 ? $pu['impression'] : null) ?></td>
                                    <td class="small text-end"><?= $numfmt($pu['klik'] > 0 ? $pu['klik'] : null) ?></td>
                                    <td class="small text-end"><?= $pct($pu['ctr']) ?></td>
                                    <td class="small text-end"><?= $rupiah($pu['cpc']) ?></td>
                                    <td class="small text-end"><?= $rupiah($pu['cpm']) ?></td>
                                    <td class="small text-end"><?= $pu['freq'] === null ? '-' : number_format((float)$pu['freq'], 2, ',', '.') . 'x' ?></td>
                                    <td class="small text-end"><?= $numfmt($pu['hasil'] > 0 ? $pu['hasil'] : null) ?></td>
                                    <td class="small text-end"><?= $rupiah($pu['cpr']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<script>
    document.querySelectorAll('.detail-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tr = document.getElementById(this.dataset.target);
            if (tr) { tr.classList.toggle('d-none'); }
            var ic = this.querySelector('iconify-icon');
            if (ic) {
                var down = ic.getAttribute('icon') === 'solar:alt-arrow-down-bold';
                ic.setAttribute('icon', down ? 'solar:alt-arrow-up-bold' : 'solar:alt-arrow-down-bold');
            }
        });
    });

    <?php if ($hasDaily) : ?>
    var chartCommon = {
        chart: { toolbar: { show: false }, fontFamily: 'inherit' },
        dataLabels: { enabled: false },
        grid: { strokeDashArray: 3 },
        tooltip: { theme: 'light' },
    };
    new ApexCharts(document.querySelector('#chartSpending'), Object.assign({}, chartCommon, {
        chart: Object.assign({}, chartCommon.chart, { type: 'bar', height: 200 }),
        series: [{ name: 'Spending', data: <?= json_encode($chartSpendValues) ?> }],
        xaxis: { categories: <?= json_encode($chartSpendLabels) ?>, labels: { rotate: -45, style: { fontSize: '10px' } } },
        colors: ['#1d4e89'],
        yaxis: { labels: { formatter: function(v) { return 'Rp' + Number(v).toLocaleString('id-ID'); } } },
    })).render();
    <?php endif; ?>
</script>