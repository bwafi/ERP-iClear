<?php
/**
 * Functional test KPI Digital Marketing / Kepala Divisi (jabatan 43).
 *
 * Flow: Lead → Won (link Customer) → Transaction (Service & Penjualan) → Omzet Marketing.
 * Plus: Conversion, CPL, ROAS, dan integrasi 7 KPI ke engine KPI existing
 * (KpiCalculationService jabatan 43). Rollback semua data uji di akhir.
 *
 * Periode uji DEDIKASI (08/2026, dengan previous 07/2026) supaya tidak
 * terganggu data produksi periode berjalan. Data uji ditandai '[TEST]'.
 *
 * Usage: php74 app/Scripts/marketing_test.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/Config/Paths.php';
use Config\Paths;
$paths = new Paths();
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
define('APPPATH', realpath(rtrim($paths->appDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('WRITEPATH', realpath(rtrim($paths->writableDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
require_once SYSTEMPATH . 'bootstrap.php';
$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

use App\Models\ModelMarketingLead;
use App\Models\ModelMarketingAdsCost;
use App\Models\ModelMarketingSource;
use App\Models\ModelPelanggan;
use App\Models\ModelChannel;
use App\Models\ModelChannelMetric;
use App\Models\ModelChannelPerformance;
use App\Services\Marketing\MarketingKpiService;
use App\Services\Kpi\KpiCalculationService;

$fail = 0;
$pass = 0;

function ok($label, $cond, $detail = '')
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  PASS  {$label}\n";
    } else {
        $fail++;
        echo "  FAIL  {$label}  " . ($detail !== '' ? "→ {$detail}" : '') . "\n";
    }
}

function near($a, $b, $tol = 0.01)
{
    return abs((float)$a - (float)$b) < $tol;
}

$db = \Config\Database::connect();
$Y = 2026;
$M = 10;      // periode uji (Oktober 2026, setelah bobot efektif 2026-09-01)
$P = $M - 1;  // previous untuk Pertumbuhan Channel (September 2026)

// ── Bersihkan sisa uji ──────────────────────────────────────────
$db->query("DELETE FROM marketing_ads_cost WHERE note='[TEST]'");
$db->query("DELETE FROM marketing_lead WHERE nama LIKE '[TEST]%'");
$db->query("DELETE FROM detail_penjualan WHERE penjualan_idpenjualan IN (SELECT idpenjualan FROM penjualan WHERE keterangan='[TEST]')");
$db->query("DELETE FROM penjualan WHERE keterangan='[TEST]'");
$db->query("DELETE FROM service WHERE keterangan='[TEST]'");
$db->query("UPDATE marketing_lead SET customer_id=NULL WHERE nama LIKE '[TEST]%'");
$db->query("DELETE FROM pelanggan WHERE nama LIKE '[TEST]%'");

$Lead  = new ModelMarketingLead();
$Ads   = new ModelMarketingAdsCost();
$Mkt   = new MarketingKpiService();
$KpiSvc = new KpiCalculationService();
$Channel = new ModelChannel();

echo "== PERSIAPAN MASTER ==\n";
$srcAds = (clone $db)->table('marketing_source')->where('code', 'INSTAGRAM')->get()->getRow();
$srcOrg = (clone $db)->table('marketing_source')->where('code', 'ORGANIC')->get()->getRow();
ok('Master source marketing ada', $srcAds && $srcOrg);

$channelIg = $Channel->where('code', 'IG')->get()->getRow();
$metricIg  = (new ModelChannelMetric())->where('channel_id', $channelIg->id)->where('code', 'IG_FOLLOWERS')->get()->getRow();
ok('Channel IG + metric Followers tersedia (untuk Pertumbuhan Channel)', $channelIg && $metricIg);

echo "\n== FLOW: Lead → Won → Customer ==\n";
$lead1 = $Lead->insert([
    'tanggal'      => '2026-10-03',
    'nama'         => '[TEST] Budi',
    'no_hp'        => '0812TEST1',
    'source_id'    => (int)$srcAds->id,
    'ads_organic'  => 'ADS',
    'cs'           => 'CS A',
    'status'       => 'NEW',
    'created_by'   => 55,
]);
$lead2 = $Lead->insert([
    'tanggal'      => '2026-10-05',
    'nama'         => '[TEST] Siti',
    'no_hp'        => '0812TEST2',
    'source_id'    => (int)$srcAds->id,
    'ads_organic'  => 'ADS',
    'cs'           => 'CS B',
    'status'       => 'NEW',
    'created_by'   => 55,
]);
$lead3 = $Lead->insert([
    'tanggal'      => '2026-10-10',
    'nama'         => '[TEST] Rudi',
    'no_hp'        => '0812TEST3',
    'source_id'    => (int)$srcOrg->id,
    'ads_organic'  => 'ORGANIC',
    'cs'           => 'CS A',
    'status'       => 'NEW',
    'created_by'   => 55,
]);
$lead4 = $Lead->insert([
    'tanggal'      => '2026-10-15',
    'nama'         => '[TEST] Dewi',
    'no_hp'        => '0812TEST4',
    'source_id'    => (int)$srcAds->id,
    'ads_organic'  => 'ADS',
    'cs'           => 'CS B',
    'status'       => 'FOLLOW_UP',
    'created_by'   => 55,
]);
ok('Lead 10/2026 = 4 (Budi, Siti, Rudi, Dewi)', $lead1 && $lead2 && $lead3 && $lead4);

// KPI Lead/Iklan kini dari REKAP MANUAL HARIAN (source of truth), bukan
// marketing_lead (yang bisa tercampur dengan baris sinkronisasi Kommo).
// 4 lead di atas tetap ada untuk CRM: Won → Customer, Omzet, leadsByStatus.
$RekapSvc = new \App\Services\Marketing\MarketingRekapService();
$rekapSave = $RekapSvc->save(1, '2026-10-20', [
    ['platform' => 'WhatsApp',  'non_iklan' => 1, 'iklan' => 2, 'prospek' => 1, 'datang' => 1],
    ['platform' => 'Instagram', 'non_iklan' => 0, 'iklan' => 1, 'prospek' => 1, 'datang' => 1],
], 5, 55);
ok('Rekap harian tersimpan (total_lead_wa_dm = 4)', $rekapSave['total_lead_wa_dm'] === 4, json_encode($rekapSave));
$rkDet = $RekapSvc->getByDate(1, '2026-10-20');
ok('Rate WhatsApp = 33.33% (1/3 datang)', $rkDet && count($rkDet['details']) === 2 && near((float)$rkDet['details'][0]->rate, 33.33), (string)($rkDet['details'][0]->rate ?? '-'));
ok('Rate Instagram = 100% (1/1 datang)', $rkDet && near((float)$rkDet['details'][1]->rate, 100), (string)($rkDet['details'][1]->rate ?? '-'));
$totalAll = $RekapSvc->monthlyLeadTotal($M, $Y);
$paidAll  = $RekapSvc->monthlyPaidTotal($M, $Y);
ok("Rekap monthlyLeadTotal = 4 (WhatsApp 3 + Instagram 1)", $totalAll === 4, (string)$totalAll);
ok("Rekap monthlyPaidTotal = 3 (iklan)", $paidAll === 3, (string)$paidAll);

ok("countLeads({$M},{$Y}) = 4 (dari rekap)", $Mkt->countLeads($M, $Y) === 4, $Mkt->countLeads($M, $Y));
ok("Paid lead (Iklan) = 3 (dari rekap)", $Mkt->paidLeads($M, $Y) === 3, $Mkt->paidLeads($M, $Y));

// WON → link customer hasil conversion.
$custBudi = (new ModelPelanggan())->insert([
    'nama' => '[TEST] Customer Budi', 'no_hp' => '0812TEST1',
    'alamat' => 'Test', 'kategori' => 1, 'deleted' => null,
    'create_on' => date('Y-m-d H:i:s'),
]);
$custSiti = (new ModelPelanggan())->insert([
    'nama' => '[TEST] Customer Siti', 'no_hp' => '0812TEST2',
    'alamat' => 'Test', 'kategori' => 1, 'deleted' => null,
    'create_on' => date('Y-m-d H:i:s'),
]);
ok('Customer dibuat (Budi, Siti) — bukan seluruh customer ERP, hanya hasil lead', $custBudi && $custSiti);

$Lead->update((int)$lead1, ['status' => 'WON', 'customer_id' => (int)$custBudi, 'tanggal_won' => '2026-10-12']);
$Lead->update((int)$lead2, ['status' => 'WON', 'customer_id' => (int)$custSiti, 'tanggal_won' => '2026-10-20']);
$Lead->update((int)$lead4, ['status' => 'LOST']);
$rowBudi = $Lead->find((int)$lead1);
ok('Lead WON tertaut customer (Won → Customer)', $rowBudi->status === 'WON' && (int)$rowBudi->customer_id === (int)$custBudi);

echo "\n== CUSTOMER & CONVERSION ==\n";
$customers = $Mkt->countCustomers($M, $Y);
ok("Customer Marketing = 2 (hanya dari lead WON + tertaut)", $customers === 2, "cust={$customers}");
$conv = $Mkt->conversionPct($M, $Y);
ok("Conversion = 2/4 × 100 = 50%", near($conv, 50), "conv={$conv}");
ok('Lead yang LOST tidak jadi customer (masih 2)', $customers === 2);

echo "\n== TRANSACTION / SERVICE → OMZET MARKETING ==\n";
// Transaksi Service: customer Budi (MKT) & Siti (MKT) vs customer lain (bukan marketing).
$svcModel = new \App\Models\ModelService();
$svcBudi = $svcModel->insert([
    'pelanggan_id_pelanggan' => (int)$custBudi, 'no_hp' => '0812TEST1',
    'unit_idunit' => 1, 'service_by' => 55, 'input_by' => 55,
    'status_service' => 8, 'status_proses' => 4,
    'total_service' => 1000000, 'total_diskon' => 100000,
    'harus_dibayar' => 900000, 'keterangan' => '[TEST]',
    'created_at' => '2026-10-14 10:00:00', 'updated_at' => '2026-10-14 10:00:00',
]);
$svcSiti = $svcModel->insert([
    'pelanggan_id_pelanggan' => (int)$custSiti, 'no_hp' => '0812TEST2',
    'unit_idunit' => 2, 'service_by' => 55, 'input_by' => 55,
    'status_service' => 8, 'status_proses' => 4,
    'total_service' => 500000, 'total_diskon' => 0,
    'harus_dibayar' => 500000, 'keterangan' => '[TEST]',
    'created_at' => '2026-10-22 11:00:00', 'updated_at' => '2026-10-22 11:00:00',
]);
$salesModel = new \App\Models\ModelPenjualan();
$penjBudi = $salesModel->insert([
    'kode_invoice' => '[TEST]-PJ-1', 'tanggal' => '2026-10-15 09:00:00',
    'total_penjualan' => 2000000, 'diskon' => 200000, 'total_ppn' => 0,
    'harus_dibayar' => '1800000', 'id_pelanggan' => (int)$custBudi,
    'unit_idunit' => 1, 'input_by' => 55, 'sales_by' => 55, 'keterangan' => '[TEST]',
    'created_on' => date('Y-m-d H:i:s'),
]);
$penjLain = $salesModel->insert([
    'kode_invoice' => '[TEST]-PJ-2', 'tanggal' => '2026-10-25 09:00:00',
    'total_penjualan' => 50000000, 'diskon' => 0, 'total_ppn' => 0,
    'harus_dibayar' => '50000000', 'id_pelanggan' => (int)$custBudi,
    'unit_idunit' => 1, 'input_by' => 55, 'sales_by' => 55, 'keterangan' => '[TEST]',
    'created_on' => date('Y-m-d H:i:s'),
]);

$revenue = $Mkt->marketingRevenue($M, $Y);
$expectedRevenue = (1000000 - 100000) + (500000 - 0) + (2000000 - 200000) + 50000000;
ok('Omzet Marketing otomatis = service Budi + service Siti + penjualan Budi (bukan total omzet perusahaan)',
    near($revenue, $expectedRevenue), "rev={$revenue} vs {$expectedRevenue}");

// Uji omzet periode lain TIDAK ikut (penjualan Sept).
$penjLainBulan = $salesModel->insert([
    'kode_invoice' => '[TEST]-PJ-3', 'tanggal' => '2026-11-05 09:00:00',
    'total_penjualan' => 99999999, 'diskon' => 0, 'total_ppn' => 0,
    'harus_dibayar' => '99999999', 'id_pelanggan' => (int)$custBudi,
    'unit_idunit' => 1, 'input_by' => 55, 'sales_by' => 55, 'keterangan' => '[TEST]',
    'created_on' => date('Y-m-d H:i:s'),
]);
$revM2 = $Mkt->marketingRevenue($M, $Y);
ok('Transaksi bulan lain tidak mencemari omzet periode berjalan', near($revM2, $revenue), $revM2);
$revLain = $Mkt->marketingRevenue(11, $Y);
ok('Omzet bulan lain berisi transaksi bulan itu saja (customer hasil lead)', near($revLain, 99999999), $revLain);

echo "\n== ADS COST → CPL & ROAS ==\n";
$adsModel = new ModelMarketingAdsCost();
$ads1 = $adsModel->insert([
    'period_month' => $M, 'period_year' => $Y,
    'channel_id' => (int)$channelIg->id, 'campaign' => 'Campaign 1',
    'amount' => 300000, 'note' => '[TEST]', 'created_by' => 55,
]);
$ads2 = $adsModel->insert([
    'period_month' => $M, 'period_year' => $Y,
    'channel_id' => null, 'campaign' => 'Campaign 2',
    'amount' => 450000, 'note' => '[TEST]', 'created_by' => 55,
]);
ok('Ads cost tersimpan (2 baris)', $ads1 && $ads2);
$cost = $Mkt->adsCost($M, $Y);
ok("Total ads cost {$M}/{$Y} = 750.000", near($cost, 750000), $cost);

$cpl = $Mkt->cpl($M, $Y);
ok("CPL = 750.000 / 3 paid lead = 250.000", near($cpl, 250000), "cpl={$cpl}");

$roas = $Mkt->roas($M, $Y);
ok('ROAS = 53.200.000 / 750.000 = 70,93×', near($roas, round($revenue / 750000, 2)), "roas={$roas}");

// Ads cost Sept (prod data sdh ada tapi bukan [TEST]) → lho, hanya uji bulan tanpa cost:
$roasTanpaCost = $Mkt->roas(12, $Y);
ok('ROAS periode tanpa ads cost = null (N/A)', $roasTanpaCost === null);

echo "\n== ACHIEVEMENT via scoreByCode (engine existing) ==\n";
$achLead = $Mkt->scoreByCode('LEAD_MARKETING', $M, $Y);
ok('Lead 4/60 = 6.67%', near($achLead, 4 / 60 * 100), $achLead);
$achCust = $Mkt->scoreByCode('CUSTOMER_MARKETING', $M, $Y);
ok('Customer 2/20 = 10%', near($achCust, 2 / 20 * 100), $achCust);
$achConv = $Mkt->scoreByCode('CONVERSION_MARKETING', $M, $Y);
ok('Conversion 50% / target 30% = 100% (cap)', near($achConv, min(100, 50 / 30 * 100)), $achConv);
$achCpl = $Mkt->scoreByCode('CPL', $M, $Y);
ok('CPL achievement = target 250k / actual 250k ×100 = 100%', near($achCpl, 100), $achCpl);
$achOmzet = $Mkt->scoreByCode('OMZET_MARKETING', $M, $Y);
ok('Omzet 53,2jt / 300jt = 17,73%', near($achOmzet, round(min(100, $revenue / 300000000 * 100), 2)), $achOmzet);
$achRoas = $Mkt->scoreByCode('ROAS_MARKETING', $M, $Y);
ok('ROAS 70,93 / target 1,5 = 100% (cap)', near($achRoas, 100), $achRoas);

echo "\n== PERTUMBUHAN CHANNEL (reuse, tidak dibuat ulang) ==\n";
$cpModel = new ModelChannelPerformance();
$cpSave = function (int $chId, int $metricId, int $m, int $y, float $actual) use ($cpModel) {
    $existing = $cpModel->getByUnique($chId, $metricId, $m, $y);
    $data = ['channel_id' => $chId, 'metric_id' => $metricId, 'period_month' => $m, 'period_year' => $y, 'actual' => $actual, 'target_growth' => 8, 'note' => '[TEST]', 'created_by' => 999];
    if ($existing) { $cpModel->update($existing->id, $data); } else { $cpModel->insert($data); }
};
$cpSave((int)$channelIg->id, (int)$metricIg->id, $P, $Y, 5000);
$cpSave((int)$channelIg->id, (int)$metricIg->id, $M, $Y, 5500);
$growth = $Mkt->scoreByCode('CHANNEL_GROWTH', $M, $Y);
ok('CHANNEL_GROWTH integrasi: (5500-5000)/5000=10% growth → achievement 125%', near($growth, 125), "growth={$growth}");
ok('Metric dihitung per channel+metric dan tidak dijumlahkan antar metric (tetap 125%)', near($Mkt->scoreByCode('CHANNEL_GROWTH', $M, $Y), $growth));

echo "\n== ENGINE KPI Total (jabatan 43 via KpiCalculationService) ==\n";
$engine = $KpiSvc->calculateForEmployee(55, 1, sprintf('%02d', $M), (string)$Y, 'penilaian_kinerja');
ok('Engine KPI pos 43: 7 komponen terhitung', count($engine['items']) === 7, count($engine['items']));
$codes = array_column($engine['items'], 'code');
$hasAll = in_array('LEAD_MARKETING', $codes) && in_array('CUSTOMER_MARKETING', $codes)
    && in_array('CONVERSION_MARKETING', $codes) && in_array('CPL', $codes)
    && in_array('OMZET_MARKETING', $codes) && in_array('ROAS_MARKETING', $codes)
    && in_array('CHANNEL_GROWTH', $codes);
ok('Engine memuat 7 KPI Digital Marketing', $hasAll);
$total = array_sum(array_map(fn($i) => $i['weight'], $engine['items']));
ok("Bobot pos 43 total = 100 (15/15/15/10/20/15/10)", near($total, 100), "total={$total}");
$leadItem = array_values(array_filter($engine['items'], fn($i) => $i['code'] === 'LEAD_MARKETING'))[0];
ok('Engine achievement LEAD = 6.67 dengan bobot 15', near($leadItem['achievement'], 6.6667, 0.01) && near($leadItem['weight'], 15));
$cplItem = array_values(array_filter($engine['items'], fn($i) => $i['code'] === 'CPL'))[0];
ok('Engine achievement CPL = 100 (bobot 10, weighted 10)', near($cplItem['achievement'], 100) && near($cplItem['weighted_score'], 10));
$roasItem = array_values(array_filter($engine['items'], fn($i) => $i['code'] === 'ROAS_MARKETING'))[0];
ok('Engine achievement ROAS = 100 (bobot 15)', near($roasItem['achievement'], 100));

echo "\n== MONTHLY SUMMARY (dashboard) ==\n";
$sum = $Mkt->monthlySummary($M, $Y);
ok('Summary 7 item & bobot total 100', count($sum['items']) === 7 && $sum['weightsum'] === 100);
$leadSum = $sum['items'][0];
ok('Summary Lead menampilkan actual otomatis (4 lead)', strpos($leadSum['actual_label'], '4') !== false);
$cplSum = $sum['items'][3];
ok('Summary CPL actual Rp 250.000', strpos($cplSum['actual_label'], '250') !== false);

echo "\n== DATA CHART (ApexCharts) ==\n";
$lb = $Mkt->leadsByStatus($M, $Y);
ok('leadsByStatus total 4 (NEW/WON/LOST)', array_sum($lb) === 4 && $lb['NEW'] === 1 && $lb['FOLLOW_UP'] === 0 && $lb['WON'] === 2 && $lb['LOST'] === 1);
$adsByCh = $Mkt->adsCostByChannel($M, $Y);
ok('adsCostByChannel total 750.000', near(array_sum(array_map(fn($c) => $c['amount'], $adsByCh)), 750000));
$trend = $Mkt->trendSeries($M, $Y, 6);
ok('trendSeries 6 label & bulan terakhir = periode uji', count($trend['labels']) === 6 && end($trend['leads']) === 4 && abs(end($trend['ads']) - 750000) < 1);
$trend3 = $Mkt->trendSeries($M, $Y, 3);
ok('trendSeries 3 min bulan', count($trend3['labels']) === 3);

echo "\n== REKAP UNIQUE (1 cabang 1 rekap per tanggal) ==\n";
$hdrBefore = (int)$db->query("SELECT COUNT(*) c FROM marketing_rekap_harian WHERE unit_id=1 AND tanggal='2026-10-20'")->getRow()->c;
$RekapSvc->save(1, '2026-10-20', [['platform' => 'WhatsApp', 'non_iklan' => 2, 'iklan' => 3, 'prospek' => 2, 'datang' => 2]], 7, 55);
$hdrAfter = (int)$db->query("SELECT COUNT(*) c FROM marketing_rekap_harian WHERE unit_id=1 AND tanggal='2026-10-20'")->getRow()->c;
ok('Upsert tidak menambah baris header (unique unit+tanggal)', $hdrBefore === 1 && $hdrAfter === 1, "before=$hdrBefore after=$hdrAfter");
$upd = $RekapSvc->getByDate(1, '2026-10-20');
ok('Rekap ter-update (total_lead_wa_dm=5, iklan_dash=7)', $upd && (int)$upd['header']->total_lead_wa_dm === 5 && (int)$upd['header']->lead_total_iklan_dashboard === 7);

echo "\n== ROLLBACK DATA UJI ==\n";
$db->query("DELETE FROM marketing_rekap_harian WHERE created_by=55 AND unit_id=1 AND tanggal='2026-10-20'");
$db->query("DELETE FROM marketing_ads_cost WHERE note='[TEST]'");
$db->query("DELETE FROM channel_performance WHERE note='[TEST]'");
$prevIds = array_map('intval', array_column($db->query("SELECT idpenjualan FROM penjualan WHERE keterangan='[TEST]'")->getResult(), 'idpenjualan'));
if (!empty($prevIds)) {
    $idsStr = implode(',', $prevIds);
    $db->query("DELETE FROM detail_penjualan WHERE penjualan_idpenjualan IN ({$idsStr})");
    $db->query("DELETE FROM penjualan WHERE idpenjualan IN ({$idsStr})");
}
$svcIds = array_map('intval', array_column($db->query("SELECT idservice FROM service WHERE keterangan='[TEST]'")->getResult(), 'idservice'));
if (!empty($svcIds)) {
    $idsStr = implode(',', $svcIds);
    $db->query("DELETE FROM service_sparepart WHERE service_idservice IN ({$idsStr})");
    $db->query("DELETE FROM service_kerusakan WHERE service_idservice IN ({$idsStr})");
    $db->query("DELETE FROM service WHERE idservice IN ({$idsStr})");
}
$db->query("UPDATE marketing_lead SET customer_id=NULL WHERE nama LIKE '[TEST]%'");
$db->query("DELETE FROM marketing_lead WHERE nama LIKE '[TEST]%'");
$db->query("DELETE FROM pelanggan WHERE nama LIKE '[TEST]%'");
$leftLeads = (int)$db->query("SELECT COUNT(*) c FROM marketing_lead WHERE nama LIKE '[TEST]%'")->getRow()->c;
$leftRevenue = (int)$db->query("SELECT COUNT(*) c FROM penjualan WHERE keterangan='[TEST]'")->getRow()->c
    + (int)$db->query("SELECT COUNT(*) c FROM service WHERE keterangan='[TEST]'")->getRow()->c
    + (int)$db->query("SELECT COUNT(*) c FROM pelanggan WHERE nama LIKE '[TEST]%'")->getRow()->c
    + (int)$db->query("SELECT COUNT(*) c FROM marketing_ads_cost WHERE note='[TEST]'")->getRow()->c
    + (int)$db->query("SELECT COUNT(*) c FROM channel_performance WHERE note='[TEST]'")->getRow()->c;
ok('Semua data uji dibersihkan', $leftLeads === 0 && $leftRevenue === 0, "left={$leftLeads}/{$leftRevenue}");

echo "\n============================================================\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
echo "============================================================\n";
exit($fail > 0 ? 1 : 0);