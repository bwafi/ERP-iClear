<div class="mkt-rekap">
<div class="card dm-page-header shadow-none border-0 mb-4">
    <div class="card-body d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div class="dm-page-heading">
            <span class="dm-page-kicker">Digital Marketing</span>
            <h1 class="dm-page-title">Rekap Marketing Harian</h1>
            <p class="dm-page-description">Input harian CS per cabang: Non Iklan, Iklan, Prospek, Datang &amp; Rate. Sumber utama KPI Marketing — terpisah dari data Kommo.</p>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-decoration-none" href="<?= base_url('marketing') ?>">Marketing KPI</a></li>
                <li class="breadcrumb-item active" aria-current="page">Rekap Harian</li>
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
$periodeLabel  = date('F Y', mktime(0, 0, 0, $bulanR, 1, $tahunR));
$periodeShort  = date('M Y', mktime(0, 0, 0, $bulanR, 1, $tahunR));
$fmtNum        = fn($v) => number_format((float)$v, 0, ',', '.');
$fmtPct        = fn($v) => number_format((float)$v, 2, ',', '.') . '%';
$inputOpen     = !$rekap['header'] || (string)service('request')->getGet('edit') === '1';

// Agregasi dari daftar yang sudah difilter unit/bulan: tren harian + komposisi platform.
$daily = [];
foreach ($rekaps as $g) {
    $tgl = (string)$g['tanggal'];
    if (!isset($daily[$tgl])) {
        $daily[$tgl] = ['total' => 0, 'prospek' => 0, 'datang' => 0];
    }
    $daily[$tgl]['total']   += (int)$g['total'];
    $daily[$tgl]['prospek'] += (int)$g['prospek'];
    $daily[$tgl]['datang']  += (int)$g['datang'];
}
ksort($daily);

$dailyLabels  = [];
$dailyTotal   = [];
$dailyProspek = [];
$dailyDatang  = [];
foreach ($daily as $tgl => $row) {
    $dailyLabels[]  = date('d/m', strtotime($tgl));
    $dailyTotal[]   = $row['total'];
    $dailyProspek[] = $row['prospek'];
    $dailyDatang[]  = $row['datang'];
}
$hasDailyData = !empty($daily);

$platformTotals = [];
foreach ($rekaps as $g) {
    foreach ($g['platforms'] as $p) {
        $name = trim((string)$p['platform']);
        if ($name === '') {
            $name = 'Tanpa platform';
        }
        $platformTotals[$name] = ($platformTotals[$name] ?? 0) + (int)$p['total'];
    }
}
arsort($platformTotals);

$leadTotal    = (int)$summary['total'];
$nonIklan     = (int)$summary['non_iklan'];
$iklan        = (int)$summary['iklan'];
$nonPct       = $leadTotal > 0 ? ($nonIklan / $leadTotal) * 100 : 0;
$iklanPct     = $leadTotal > 0 ? ($iklan / $leadTotal) * 100 : 0;
$convProspek  = $leadTotal > 0 ? ((int)$summary['prospek'] / $leadTotal) * 100 : 0;
$convDatang   = (int)$summary['prospek'] > 0 ? ((int)$summary['datang'] / (int)$summary['prospek']) * 100 : 0;

$cmpRows = [
    ['Non Iklan', 'non_iklan', false],
    ['Iklan', 'iklan', false],
    ['Total Lead', 'total', false],
    ['Prospek', 'prospek', false],
    ['Datang', 'datang', false],
    ['Rate', 'rate', true],
    ['Total Lead WA/DM', 'total_lead_wa_dm', false],
];
$fmtCmp = function ($value, $isPct) {
    return $isPct
        ? number_format((float)$value, 2, ',', '.') . '%'
        : number_format((float)$value, 0, ',', '.');
};

$rangeLabel = function (string $start, string $end): string {
    return date('d M', strtotime($start)) . ' - ' . date('d M Y', strtotime($end));
};
$rangeInput = function (string $start, string $end): string {
    return date('d/m/Y', strtotime($start)) . ' - ' . date('d/m/Y', strtotime($end));
};
$cmpLabelA  = $rangeLabel($cmpAStart, $cmpAEnd);
$cmpLabelB  = $rangeLabel($cmpBStart, $cmpBEnd);
$cmpInputA  = $rangeInput($cmpAStart, $cmpAEnd);
$cmpInputB  = $rangeInput($cmpBStart, $cmpBEnd);

// Gabungkan platform dari kedua periode; platform yang hanya ada di salah
// satu periode tetap tampil dengan nilai 0 di periode lainnya.
$platformCompare = [];
foreach ([['a', $cmpSummaryA], ['b', $cmpSummaryB]] as [$side, $cmpSummary]) {
    foreach (($cmpSummary['platforms'] ?? []) as $key => $p) {
        if (!isset($platformCompare[$key])) {
            $platformCompare[$key] = [
                'platform' => (string)($p['platform'] ?? $key),
                'non_iklan_a' => 0, 'iklan_a' => 0, 'total_a' => 0, 'prospek_a' => 0, 'datang_a' => 0,
                'non_iklan_b' => 0, 'iklan_b' => 0, 'total_b' => 0, 'prospek_b' => 0, 'datang_b' => 0,
            ];
        }
        foreach (['non_iklan', 'iklan', 'total', 'prospek', 'datang'] as $metric) {
            $platformCompare[$key][$metric . '_' . $side] = (int)($p[$metric] ?? 0);
        }
    }
}
usort($platformCompare, fn($x, $y) => ($y['total_a'] + $y['total_b']) <=> ($x['total_a'] + $x['total_b']));
$cmpChartLabels = array_column($platformCompare, 'platform');
$cmpChartA      = array_column($platformCompare, 'total_a');
$cmpChartB      = array_column($platformCompare, 'total_b');
$hasPlatformCompare = !empty($platformCompare);

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<link rel="stylesheet" href="<?= base_url('template/assets/libs/daterangepicker/daterangepicker.css') ?>">
<style>
    .mkt-rekap { --mkt-organic: #20c997; --mkt-paid: #0d6efd; --mkt-accent: #fd7e14; }
    .mkt-rekap .mkt-scope { border: 1px solid var(--bs-border-color); border-radius: .9rem; }
    .mkt-rekap .mkt-scope .card-body { padding: .9rem 1rem; }
    .mkt-rekap .mkt-scope__form { display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-end; }
    .mkt-rekap .mkt-field { display: flex; flex-direction: column; gap: .3rem; min-width: 140px; }
    .mkt-rekap .mkt-field > label { font-size: .68rem; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--bs-secondary-color); }
    .mkt-rekap .mkt-scorecard { display: flex; flex-wrap: wrap; overflow: hidden; border: 1px solid var(--bs-border-color); border-radius: .9rem; background: var(--bs-body-bg); }
    .mkt-rekap .mkt-scorecard__lead { flex: 1 1 290px; padding: 1.3rem 1.4rem; background: var(--bs-tertiary-bg); }
    .mkt-rekap .mkt-eyebrow { display: block; font-size: .68rem; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--bs-secondary-color); }
    .mkt-rekap .mkt-scorecard__total { margin-top: .35rem; font-size: clamp(2.6rem, 5vw, 3.5rem); font-weight: 700; line-height: 1; letter-spacing: -.045em; color: var(--bs-heading-color); font-variant-numeric: tabular-nums; }
    .mkt-rekap .mkt-scorecard__caption { margin-top: .4rem; font-size: .82rem; color: var(--bs-secondary-color); }
    .mkt-rekap .mkt-split { margin-top: 1rem; }
    .mkt-rekap .mkt-split__track { display: flex; height: 8px; overflow: hidden; border-radius: 999px; background: var(--bs-secondary-bg); }
    .mkt-rekap .mkt-split__track > span { height: 100%; }
    .mkt-rekap .mkt-split__track .is-organic { background: var(--mkt-organic); }
    .mkt-rekap .mkt-split__track .is-paid { background: var(--mkt-paid); }
    .mkt-rekap .mkt-split__legend { display: flex; flex-wrap: wrap; gap: .25rem 1rem; margin-top: .5rem; font-size: .75rem; color: var(--bs-secondary-color); }
    .mkt-rekap .mkt-split__legend span { display: inline-flex; align-items: center; gap: .35rem; }
    .mkt-rekap .mkt-split__legend i { width: 8px; height: 8px; border-radius: 50%; }
    .mkt-rekap .mkt-split__legend .is-organic { background: var(--mkt-organic); }
    .mkt-rekap .mkt-split__legend .is-paid { background: var(--mkt-paid); }
    .mkt-rekap .mkt-scorecard__cells { flex: 2 1 560px; display: grid; grid-template-columns: repeat(auto-fit, minmax(112px, 1fr)); gap: 1px; background: var(--bs-border-color); }
    .mkt-rekap .mkt-cell { display: flex; flex-direction: column; justify-content: center; padding: 1rem 1.1rem; background: var(--bs-body-bg); }
    .mkt-rekap .mkt-cell > span { font-size: .68rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--bs-secondary-color); }
    .mkt-rekap .mkt-cell > strong { margin-top: .3rem; font-size: 1.35rem; font-weight: 650; line-height: 1.1; color: var(--bs-heading-color); font-variant-numeric: tabular-nums; }
    .mkt-rekap .mkt-cell > small { margin-top: .2rem; font-size: .72rem; color: var(--bs-secondary-color); }
    .mkt-rekap .mkt-panel { height: 100%; border: 1px solid var(--bs-border-color); border-radius: .9rem; }
    .mkt-rekap .mkt-panel .card-body { padding: 1.1rem 1.15rem; }
    .mkt-rekap .mkt-panel__head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .5rem 1rem; margin-bottom: .9rem; }
    .mkt-rekap .mkt-panel__head h5 { margin: 0; font-size: .95rem; font-weight: 650; }
    .mkt-rekap .mkt-panel__head p { margin: .15rem 0 0; font-size: .76rem; color: var(--bs-secondary-color); }
    .mkt-rekap .mkt-range-form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .5rem; }
    .mkt-rekap .mkt-range { display: flex; flex-direction: column; gap: .25rem; }
    .mkt-rekap .mkt-range__label { font-size: .68rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--bs-secondary-color); }
    .mkt-rekap .mkt-range .mkt-range__input { width: 210px; cursor: pointer; }
    @media (max-width: 767.98px) {
        .mkt-rekap .mkt-range .mkt-range__input { width: 100%; }
        .daterangepicker { max-width: calc(100vw - 24px); overflow-x: auto; }
    }
    .mkt-rekap .mkt-empty-chart { display: grid; place-items: center; min-height: 220px; padding: 1rem; text-align: center; border: 1px dashed var(--bs-border-color); border-radius: .75rem; color: var(--bs-secondary-color); }
    .mkt-rekap .mkt-funnel-meta { display: grid; gap: .5rem; margin-top: .75rem; padding-top: .75rem; border-top: 1px solid var(--bs-border-color); }
    .mkt-rekap .mkt-funnel-meta > div { display: flex; justify-content: space-between; gap: 1rem; font-size: .78rem; }
    .mkt-rekap .mkt-funnel-meta span { color: var(--bs-secondary-color); }
    .mkt-rekap .mkt-funnel-meta strong { font-variant-numeric: tabular-nums; }
    .mkt-rekap .mkt-compare__table { font-size: .8rem; }
    .mkt-rekap .mkt-compare__table th { font-size: .68rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--bs-secondary-color); }
    .mkt-rekap .mkt-input-card { scroll-margin-top: 5rem; border: 1px solid var(--bs-border-color); border-radius: .9rem; }
    .mkt-rekap .mkt-input-card .card-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; background: var(--bs-body-bg); border-bottom: 1px solid var(--bs-border-color); }
    .mkt-rekap .mkt-input-inline { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .5rem; }
    .mkt-rekap .mkt-list-card { border: 1px solid var(--bs-border-color); border-radius: .9rem; }
    .mkt-rekap .mkt-list-card .card-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; background: var(--bs-body-bg); border-bottom: 1px solid var(--bs-border-color); }
    .mkt-rekap .table > :not(caption) > * > * { vertical-align: middle; }
</style>

<section class="card mkt-scope mb-3">
    <div class="card-body">
        <form method="get" class="mkt-scope__form">
            <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">
            <input type="hidden" name="unit_id" value="<?= (int)$unitId ?>">
            <input type="hidden" name="cmp_a_start" value="<?= esc($cmpAStart) ?>">
            <input type="hidden" name="cmp_a_end" value="<?= esc($cmpAEnd) ?>">
            <input type="hidden" name="cmp_b_start" value="<?= esc($cmpBStart) ?>">
            <input type="hidden" name="cmp_b_end" value="<?= esc($cmpBEnd) ?>">
            <div class="mkt-field">
                <label for="mktFilterUnit">Cabang laporan</label>
                <select name="f_unit_id" id="mktFilterUnit" class="form-select form-select-sm">
                    <option value="0">Semua Cabang</option>
                    <?php foreach ($units as $u) : ?>
                        <option value="<?= (int)$u->idunit ?>" <?= (int)$u->idunit === $filterUnitId ? 'selected' : '' ?>><?= esc($u->NAMA_UNIT) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mkt-field">
                <label for="mktFilterBulan">Bulan</label>
                <select name="bulan" id="mktFilterBulan" class="form-select form-select-sm">
                    <?php foreach (range(1, 12) as $b) : ?>
                        <option value="<?= $b ?>" <?= $b === $bulanR ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $b, 1)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mkt-field">
                <label for="mktFilterTahun">Tahun</label>
                <select name="tahun" id="mktFilterTahun" class="form-select form-select-sm">
                    <?php foreach (range((int)date('Y'), (int)date('Y') - 3) as $t) : ?>
                        <option value="<?= $t ?>" <?= $t === $tahunR ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Terapkan</button>
            <a href="#inputHarian" class="btn btn-sm btn-outline-secondary ms-sm-auto"><i class="bi bi-pencil-square me-1"></i>Input harian</a>
        </form>
    </div>
</section>

<section class="mkt-scorecard mb-3">
    <div class="mkt-scorecard__lead">
        <span class="mkt-eyebrow">Ringkasan <?= esc($periodeLabel) ?></span>
        <div class="mkt-scorecard__total"><?= $fmtNum($leadTotal) ?></div>
        <div class="mkt-scorecard__caption">Total lead · <?= esc($filterUnitName) ?></div>
        <div class="mkt-split">
            <div class="mkt-split__track" role="img" aria-label="Komposisi non iklan <?= $fmtPct($nonPct) ?> dan iklan <?= $fmtPct($iklanPct) ?>">
                <span class="is-organic" style="width:<?= $leadTotal > 0 ? number_format($nonPct, 2, '.', '') : 0 ?>%"></span>
                <span class="is-paid" style="width:<?= $leadTotal > 0 ? number_format($iklanPct, 2, '.', '') : 0 ?>%"></span>
            </div>
            <div class="mkt-split__legend">
                <span><i class="is-organic"></i>Non Iklan <?= $fmtNum($nonIklan) ?></span>
                <span><i class="is-paid"></i>Iklan <?= $fmtNum($iklan) ?></span>
            </div>
        </div>
    </div>
    <div class="mkt-scorecard__cells">
        <div class="mkt-cell">
            <span>Rate</span>
            <strong><?= $fmtPct($summary['rate']) ?></strong>
            <small>datang / lead</small>
        </div>
        <div class="mkt-cell">
            <span>Prospek</span>
            <strong><?= $fmtNum($summary['prospek']) ?></strong>
            <small><?= $fmtPct($convProspek) ?> dari lead</small>
        </div>
        <div class="mkt-cell">
            <span>Datang</span>
            <strong><?= $fmtNum($summary['datang']) ?></strong>
            <small><?= $fmtPct($convDatang) ?> dari prospek</small>
        </div>
        <div class="mkt-cell">
            <span>Lead WA/DM</span>
            <strong><?= $fmtNum($summary['total_lead_wa_dm']) ?></strong>
            <small>WhatsApp &amp; DM</small>
        </div>
        <div class="mkt-cell">
            <span>Rekap harian</span>
            <strong><?= $fmtNum($summary['total_days']) ?></strong>
            <small>hari terisi</small>
        </div>
    </div>
</section>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="card mkt-panel">
            <div class="card-body">
                <div class="mkt-panel__head">
                    <div>
                        <h5>Tren Harian</h5>
                        <p><?= esc($periodeShort) ?> · <?= esc($filterUnitName) ?></p>
                    </div>
                    <span class="badge text-bg-light border align-self-start"><?= $fmtNum($leadTotal) ?> lead</span>
                </div>
                <?php if ($hasDailyData) : ?>
                    <div id="mktTrendChart" role="img" aria-label="Grafik tren harian total lead, prospek, dan datang"></div>
                <?php else : ?>
                    <div class="mkt-empty-chart">
                        <div>
                            <iconify-icon icon="solar:chart-2-outline" width="34" height="34"></iconify-icon>
                            <div class="mt-2 small">Belum ada data harian pada periode ini.</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card mkt-panel">
            <div class="card-body">
                <div class="mkt-panel__head">
                    <div>
                        <h5>Komposisi Platform</h5>
                        <p>Total lead per platform</p>
                    </div>
                </div>
                <?php if (!empty($platformTotals)) : ?>
                    <div id="mktPlatformChart" role="img" aria-label="Grafik komposisi total lead per platform"></div>
                <?php else : ?>
                    <div class="mkt-empty-chart">
                        <div>
                            <iconify-icon icon="solar:pie-chart-2-outline" width="34" height="34"></iconify-icon>
                            <div class="mt-2 small">Belum ada detail platform.</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-4">
        <div class="card mkt-panel">
            <div class="card-body">
                <div class="mkt-panel__head">
                    <div>
                        <h5>Corong Konversi</h5>
                        <p>Lead → prospek → datang</p>
                    </div>
                </div>
                <?php if ($hasDailyData) : ?>
                    <div id="mktFunnelChart" role="img" aria-label="Grafik corong konversi dari lead ke prospek lalu datang"></div>
                <?php else : ?>
                    <div class="mkt-empty-chart" style="min-height:190px;">
                        <div class="small">Belum ada data untuk corong konversi.</div>
                    </div>
                <?php endif; ?>
                <div class="mkt-funnel-meta">
                    <div><span>Lead → prospek</span><strong><?= $fmtPct($convProspek) ?></strong></div>
                    <div><span>Prospek → datang</span><strong><?= $fmtPct($convDatang) ?></strong></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card mkt-panel">
            <div class="card-body">
                <div class="mkt-panel__head">
                    <div>
                        <h5>Bandingkan Periode</h5>
                        <p>Pilih rentang tanggal bebas. Selisih = periode A dikurangi periode B.</p>
                    </div>
                    <form method="get" class="mkt-range-form m-0">
                        <input type="hidden" name="tanggal" value="<?= esc($tanggal) ?>">
                        <input type="hidden" name="unit_id" value="<?= (int)$unitId ?>">
                        <input type="hidden" name="bulan" value="<?= (int)$bulanR ?>">
                        <input type="hidden" name="tahun" value="<?= (int)$tahunR ?>">
                        <input type="hidden" name="f_unit_id" value="<?= (int)$filterUnitId ?>">
                        <div class="mkt-range">
                            <span class="mkt-range__label">Periode A</span>
                            <input type="text" id="cmpA" class="form-control form-control-sm mkt-range__input" value="<?= esc($cmpInputA) ?>" aria-label="Rentang tanggal periode A" readonly>
                            <input type="hidden" name="cmp_a_start" id="cmpAStart" value="<?= esc($cmpAStart) ?>">
                            <input type="hidden" name="cmp_a_end" id="cmpAEnd" value="<?= esc($cmpAEnd) ?>">
                        </div>
                        <div class="mkt-range">
                            <span class="mkt-range__label">Periode B</span>
                            <input type="text" id="cmpB" class="form-control form-control-sm mkt-range__input" value="<?= esc($cmpInputB) ?>" aria-label="Rentang tanggal periode B" readonly>
                            <input type="hidden" name="cmp_b_start" id="cmpBStart" value="<?= esc($cmpBStart) ?>">
                            <input type="hidden" name="cmp_b_end" id="cmpBEnd" value="<?= esc($cmpBEnd) ?>">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary align-self-end"><i class="bi bi-arrow-left-right me-1"></i>Bandingkan</button>
                    </form>
                </div>
                <div class="row g-3">
                    <div class="col-lg-7">
                        <?php if ($hasPlatformCompare) : ?>
                            <div id="mktCompareChart" role="img" aria-label="Grafik perbandingan total lead per platform"></div>
                        <?php else : ?>
                            <div class="mkt-empty-chart" style="min-height:220px;">
                                <div class="small">Belum ada data platform pada periode ini.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-lg-5">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 mkt-compare__table">
                                <thead>
                                    <tr>
                                        <th>Metrik</th>
                                        <th class="text-end"><?= esc($cmpLabelA) ?></th>
                                        <th class="text-end"><?= esc($cmpLabelB) ?></th>
                                        <th class="text-end">Selisih</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cmpRows as $row) : ?>
                                        <?php
                                        $key = $row[1];
                                        $isPct = $row[2];
                                        $a = (float)($cmpSummaryA[$key] ?? 0);
                                        $b = (float)($cmpSummaryB[$key] ?? 0);
                                        $d = $a - $b;
                                        $delta = ($d > 0 ? '+' : ($d < 0 ? '-' : '')) . $fmtCmp(abs($d), $isPct) . ($isPct ? ' pp' : '');
                                        $deltaClass = $d > 0 ? 'text-success' : ($d < 0 ? 'text-danger' : 'text-muted');
                                        ?>
                                        <tr>
                                            <td><?= esc($row[0]) ?></td>
                                            <td class="text-end"><?= $fmtCmp($a, $isPct) ?></td>
                                            <td class="text-end"><?= $fmtCmp($b, $isPct) ?></td>
                                            <td class="text-end fw-semibold <?= $deltaClass ?>"><?= $delta ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="small text-muted mb-0 mt-2">Untuk <?= esc($filterUnitName) ?>.</p>
                    </div>
                </div>

                <div class="mt-3 pt-3 border-top">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <div>
                            <h6 class="mb-0">Rincian per Platform</h6>
                            <div class="small text-muted">Total lead, prospek, dan datang tiap platform pada kedua periode.</div>
                        </div>
                        <span class="badge text-bg-light border"><?= count($platformCompare) ?> platform</span>
                    </div>
                    <?php if ($hasPlatformCompare) : ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 mkt-compare__table">
                                <thead>
                                    <tr>
                                        <th>Platform</th>
                                        <th class="text-end">Lead A</th>
                                        <th class="text-end">Lead B</th>
                                        <th class="text-end">Δ Lead</th>
                                        <th class="text-end">Prospek A</th>
                                        <th class="text-end">Prospek B</th>
                                        <th class="text-end">Datang A</th>
                                        <th class="text-end">Datang B</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($platformCompare as $p) :
                                        $deltaLead = (int)$p['total_a'] - (int)$p['total_b'];
                                        $deltaLeadClass = $deltaLead > 0 ? 'text-success' : ($deltaLead < 0 ? 'text-danger' : 'text-muted');
                                    ?>
                                        <tr>
                                            <td class="fw-semibold"><?= esc($p['platform']) ?></td>
                                            <td class="text-end"><?= $fmtNum($p['total_a']) ?></td>
                                            <td class="text-end"><?= $fmtNum($p['total_b']) ?></td>
                                            <td class="text-end fw-semibold <?= $deltaLeadClass ?>">
                                                <?= ($deltaLead > 0 ? '+' : ($deltaLead < 0 ? '-' : '')) . $fmtNum(abs($deltaLead)) ?>
                                            </td>
                                            <td class="text-end"><?= $fmtNum($p['prospek_a']) ?></td>
                                            <td class="text-end"><?= $fmtNum($p['prospek_b']) ?></td>
                                            <td class="text-end"><?= $fmtNum($p['datang_a']) ?></td>
                                            <td class="text-end"><?= $fmtNum($p['datang_b']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <div class="small text-muted">Belum ada data platform pada kedua periode.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mkt-input-card mb-3" id="inputHarian">
    <div class="card-header py-3">
        <div>
            <h5 class="mb-0"><iconify-icon icon="solar:clipboard-list-bold" class="text-primary me-1"></iconify-icon>Input Angka Harian</h5>
            <div class="small text-muted mt-1">
                <?= $rekap['header'] ? 'Rekap tanggal ' . esc($tanggal) . ' sudah ada — ubah bila perlu.' : 'Belum ada rekap untuk tanggal ini.' ?>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <form method="get" class="mkt-input-inline m-0">
                <input type="hidden" name="bulan" value="<?= (int)$bulanR ?>">
                <input type="hidden" name="tahun" value="<?= (int)$tahunR ?>">
                <input type="hidden" name="f_unit_id" value="<?= (int)$filterUnitId ?>">
                <input type="hidden" name="cmp_a_start" value="<?= esc($cmpAStart) ?>">
                <input type="hidden" name="cmp_a_end" value="<?= esc($cmpAEnd) ?>">
                <input type="hidden" name="cmp_b_start" value="<?= esc($cmpBStart) ?>">
                <input type="hidden" name="cmp_b_end" value="<?= esc($cmpBEnd) ?>">
                <input type="hidden" name="edit" value="1">
                <div>
                    <label class="form-label mb-1 small text-muted" for="mktInputTanggal">Tanggal</label>
                    <input type="date" name="tanggal" id="mktInputTanggal" class="form-control form-control-sm" value="<?= esc($tanggal) ?>" required onchange="this.form.submit()">
                </div>
                <div>
                    <label class="form-label mb-1 small text-muted" for="mktInputUnit">Cabang input</label>
                    <select name="unit_id" id="mktInputUnit" class="form-select form-select-sm" required onchange="this.form.submit()">
                        <option value="">Pilih Cabang</option>
                        <?php foreach ($units as $unit) : ?>
                            <option value="<?= (int)$unit->idunit ?>" <?= (int)$unit->idunit === $unitId ? 'selected' : '' ?>><?= esc($unit->NAMA_UNIT) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#rekapInputPanel" aria-expanded="<?= $inputOpen ? 'true' : 'false' ?>" aria-controls="rekapInputPanel">
                <i class="bi bi-chevron-expand me-1"></i>Form
            </button>
        </div>
    </div>
    <div class="collapse <?= $inputOpen ? 'show' : '' ?>" id="rekapInputPanel">
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
</div>

<div class="card mkt-list-card">
    <div class="card-header py-3">
        <div>
            <h5 class="mb-0"><iconify-icon icon="solar:calendar-mark-bold" class="text-primary me-1"></iconify-icon>Daftar Rekap Marketing</h5>
            <div class="small text-muted mt-1"><?= esc($filterUnitName) ?> · <?= esc($periodeLabel) ?></div>
        </div>
        <span class="badge text-bg-light border"><?= count($rekaps) ?> hari</span>
    </div>
    <div class="card-body">
        <?php if (empty($rekaps)) : ?>
            <div class="alert alert-light border mb-0 text-center py-4">
                <iconify-icon icon="solar:file-corrupted-outline" class="text-muted" width="32" height="32"></iconify-icon>
                <div class="text-muted mt-1">Belum ada rekap untuk <?= esc($filterUnitName) ?> pada <?= esc($periodeLabel) ?>. Pilih tanggal &amp; cabang di form input, lalu Simpan.</div>
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
                            $url = base_url('marketing/rekap') . '?tanggal=' . $g['tanggal'] . '&unit_id=' . (int)$g['unit_id'] . '&edit=1#inputHarian';
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

<?php if ($hasDailyData) : ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    if (typeof ApexCharts === 'undefined') { return; }

    var base = {
        chart: { toolbar: { show: false }, fontFamily: 'inherit', foreColor: 'inherit' },
        dataLabels: { enabled: false },
        grid: { strokeDashArray: 3, borderColor: 'rgba(128,128,128,.18)' },
        tooltip: { theme: 'light' }
    };

    new ApexCharts(document.querySelector('#mktTrendChart'), Object.assign({}, base, {
        chart: Object.assign({}, base.chart, { type: 'line', height: 268 }),
        series: [
            { name: 'Total Lead', type: 'area', data: <?= json_encode($dailyTotal, $jsonFlags) ?> },
            { name: 'Prospek', type: 'line', data: <?= json_encode($dailyProspek, $jsonFlags) ?> },
            { name: 'Datang', type: 'line', data: <?= json_encode($dailyDatang, $jsonFlags) ?> }
        ],
        xaxis: { categories: <?= json_encode($dailyLabels, $jsonFlags) ?>, tickAmount: Math.min(10, <?= count($dailyLabels) ?>) },
        colors: ['#0d6efd', '#fd7e14', '#198754'],
        stroke: { curve: 'smooth', width: [2, 2, 2] },
        fill: { type: ['gradient', 'solid', 'solid'], opacity: [0.22, 0, 0] },
        legend: { position: 'top', horizontalAlign: 'left', markers: { radius: 3 } },
        yaxis: { labels: { formatter: function (v) { return Math.round(v); } } }
    })).render();

    var platformLabels = <?= json_encode(array_keys($platformTotals), $jsonFlags) ?>;
    var platformSeries = <?= json_encode(array_values($platformTotals), $jsonFlags) ?>;
    if (platformLabels.length) {
        new ApexCharts(document.querySelector('#mktPlatformChart'), Object.assign({}, base, {
            chart: Object.assign({}, base.chart, { type: 'donut', height: 268 }),
            labels: platformLabels,
            series: platformSeries,
            colors: ['#0d6efd', '#20c997', '#fd7e14', '#6f42c1', '#dc3545', '#0dcaf0', '#adb5bd'],
            legend: { position: 'bottom', fontSize: '12px' },
            stroke: { width: 2 },
            plotOptions: { pie: { donut: { size: '62%', labels: { show: true, total: { show: true, label: 'Total', formatter: function (w) { return w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0); } } } } } },
            dataLabels: { enabled: true, formatter: function (v) { return Math.round(v) + '%'; } }
        })).render();
    }

    new ApexCharts(document.querySelector('#mktFunnelChart'), Object.assign({}, base, {
        chart: Object.assign({}, base.chart, { type: 'bar', height: 190 }),
        series: [{ name: 'Jumlah', data: [<?= (int)$leadTotal ?>, <?= (int)$summary['prospek'] ?>, <?= (int)$summary['datang'] ?>] }],
        xaxis: { categories: ['Total Lead', 'Prospek', 'Datang'] },
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '55%', distributed: true } },
        colors: ['#0d6efd', '#fd7e14', '#198754'],
        legend: { show: false },
        dataLabels: { enabled: true, formatter: function (v) { return Math.round(v); } }
    })).render();
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    if (typeof ApexCharts === 'undefined') { return; }

    var compareEl = document.querySelector('#mktCompareChart');
    if (!compareEl) { return; }

    new ApexCharts(compareEl, {
        chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'inherit' },
        dataLabels: { enabled: false },
        grid: { strokeDashArray: 3, borderColor: 'rgba(128,128,128,.18)' },
        tooltip: { theme: 'light' },
        series: [
            { name: <?= json_encode($cmpLabelA, $jsonFlags) ?>, data: <?= json_encode($cmpChartA, $jsonFlags) ?> },
            { name: <?= json_encode($cmpLabelB, $jsonFlags) ?>, data: <?= json_encode($cmpChartB, $jsonFlags) ?> }
        ],
        xaxis: { categories: <?= json_encode($cmpChartLabels, $jsonFlags) ?> },
        plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } },
        colors: ['#0d6efd', '#adb5bd'],
        legend: { position: 'top', horizontalAlign: 'left' },
        yaxis: { labels: { formatter: function (v) { return Math.round(v); } } }
    }).render();
});
</script>

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

    var inputLink = document.querySelector('a[href="#inputHarian"]');
    var inputPanel = document.getElementById('rekapInputPanel');
    if (inputLink && inputPanel && window.bootstrap) {
        inputLink.addEventListener('click', function () {
            window.bootstrap.Collapse.getOrCreateInstance(inputPanel, { toggle: false }).show();
        });
    }

    recalcAll();
})();
</script>

<script src="<?= base_url('template/assets/js/extra-libs/moment/moment.min.js') ?>"></script>
<script src="<?= base_url('template/assets/libs/daterangepicker/daterangepicker.js') ?>"></script>
<script>
// vendor.min.js memuat jQuery lain dan menimpa window.jQuery setelah view ini,
// jadi simpan instance yang sudah memasang plugin daterangepicker.
window.__mktRangeJQuery = window.jQuery;
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    var $range = window.__mktRangeJQuery;
    if (!$range || !$range.fn || !$range.fn.daterangepicker || typeof moment === 'undefined') {
        return;
    }

    function initRange(inputSelector, startSelector, endSelector) {
        var $input = $range(inputSelector);
        var $start = $range(startSelector);
        var $end = $range(endSelector);
        if (!$input.length) { return; }

        $input.daterangepicker({
            autoApply: true,
            showDropdowns: true,
            linkedCalendars: false,
            locale: {
                format: 'DD/MM/YYYY',
                separator: ' - ',
                applyLabel: 'Terapkan',
                cancelLabel: 'Batal',
                customRangeLabel: 'Pilih sendiri',
                weekLabel: 'M',
                daysOfWeek: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
                monthNames: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
                firstDay: 1
            },
            startDate: moment($start.val(), 'YYYY-MM-DD').format('DD/MM/YYYY'),
            endDate: moment($end.val(), 'YYYY-MM-DD').format('DD/MM/YYYY')
        }, function (start, end) {
            $start.val(start.format('YYYY-MM-DD'));
            $end.val(end.format('YYYY-MM-DD'));
            $input.val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
        });
    }

    initRange('#cmpA', '#cmpAStart', '#cmpAEnd');
    initRange('#cmpB', '#cmpBStart', '#cmpBEnd');
});
</script>
</div>
