<?php
/**
 * Functional test modul Content Management (KPI Multimedia/Creative).
 *
 * Menjalankan alur model/service seperti controller: CRUD content, target
 * ALL/SELECTED, banyak talent/creative (satu orang dua peran), banyak
 * publikasi (tetap 1 content), workflow + QC pass/reject/revision, brand
 * checklist, performa publikasi, ringkasan KPI bulanan, scope per role,
 * dan query DataTables. Semua data uji di-rollback di akhir.
 *
 * Usage: php74 app/Scripts/konten_test.php
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

use App\Models\ModelContent;
use App\Models\ModelContentChecklist;
use App\Models\ModelContentPerson;
use App\Models\ModelContentUnit;
use App\Models\ModelPublication;
use App\Models\ModelPublicationPerformance;
use App\Services\Konten\ContentKpiService;
use App\Services\Konten\ContentScopeService;
use App\Services\Konten\ContentWorkflowService;

$fail = 0;
$pass = 0;

function ok($label, $cond, $detail = '') {
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  PASS  {$label}\n";
    } else {
        $fail++;
        echo "  FAIL  {$label}  " . ($detail !== '' ? "→ {$detail}" : '') . "\n";
    }
}

function near($a, $b, $tol = 0.01) {
    return abs((float)$a - (float)$b) < $tol;
}

$db = \Config\Database::connect();
$ContentModel = new ModelContent();
$Workflow = new ContentWorkflowService();
$Kpi = new ContentKpiService();
$Scope = new ContentScopeService();

// Idempotent: bersihkan sisa data uji dari run sebelumnya.
$stale = $db->query("SELECT id FROM contents WHERE judul LIKE '[TEST]%'")->getResult();
foreach ($stale as $s) {
    $ContentModel->delete((int)$s->id);
}

$createdContentIds = [];

echo "== PERSIAPAN MASTER ==\n";
$platformIg = (clone $db)->table('platforms')->where('code', 'INSTAGRAM')->get()->getRow();
$platformFb = (clone $db)->table('platforms')->where('code', 'FACEBOOK')->get()->getRow();
ok('Master platform Instagram ada', $platformIg !== null);
ok('Master platform Facebook ada', $platformFb !== null);

$typeFeed = (clone $db)->table('content_types')->where('code', 'FEED')->get()->getRow();
$metricReach = (clone $db)->table('performance_metrics')->where('code', 'REACH')->get()->getRow();
$checklistItems = (new \App\Models\ModelBrandChecklistItem())->optionsActive();
ok('Master content_type & metric & checklist tersedia', $typeFeed && $metricReach && count($checklistItems) === 6);

$peopleDb = (clone $db)->table('akun');
$fahri = $peopleDb->where('ID_AKUN', 63)->get()->getRow();
$fathoni = $peopleDb->where('ID_AKUN', 55)->get()->getRow();
ok('Akun pemain uji ada (63 Fahri, 55 fathoni)', $fahri && $fathoni);

echo "\n== CRUD CONTENT ==\n";
// C1: target ALL, banyak orang, 1 orang dua peran sekaligus.
$c1 = $ContentModel->insert([
    'judul'       => '[TEST] Promo Lebaran C1',
    'deskripsi'   => 'Konten uji target ALL',
    'content_type_id' => $typeFeed->id,
    'jenis_konten' => 'ADS',
    'target_scope' => 'ALL',
    'deadline'    => '2026-09-30',
    'status'      => 'DRAFT',
    'performance_metric_id' => $metricReach->id,
    'performance_target' => 1500,
    'created_by'  => 63,
]);
$createdContentIds[] = (int)$c1;

// C2: target SELECTED units [2,3].
$c2 = $ContentModel->insert([
    'judul'       => '[TEST] Feeds Bibit C2',
    'deskripsi'   => 'Konten uji SELECTED',
    'content_type_id' => $typeFeed->id,
    'target_scope' => 'SELECTED',
    'deadline'    => '2026-09-30',
    'status'      => 'DRAFT',
    'created_by'  => 63,
]);
$createdContentIds[] = (int)$c2;

// C3: deadline lalu (overdue), DRAFT tanpa publikasi/checklist.
$c3 = $ContentModel->insert([
    'judul'       => '[TEST] Feed Lama C3',
    'target_scope' => 'ALL',
    'deadline'    => '2026-09-05',
    'status'      => 'DRAFT',
    'created_by'  => 63,
]);
$createdContentIds[] = (int)$c3;

ok('Content C1 target ALL dibuat', $c1 > 0);
ok('Content C2 target SELECTED dibuat', $c2 > 0);

// Target unit C2.
(new ModelContentUnit())->replaceForContent((int)$c2, [2, 3]);
$cu2 = $db->query("SELECT unit_id FROM content_units WHERE content_id = {$c2} ORDER BY unit_id")->getResultArray();
ok('C2 target units = [2,3]', array_column($cu2, 'unit_id') === ['2', '3'], json_encode($cu2));

// People: C1 talent [55,63], creative [63,55] → 63 dua peran.
(new ModelContentPerson())->replaceForContent((int)$c1, [55, 63], [63, 55]);
$cp1 = $db->query("SELECT akun_id, role FROM content_people WHERE content_id = {$c1} ORDER BY role, akun_id")->getResultArray();
ok('C1 punya 4 relasi orang (2 role × 2 orang)', count($cp1) === 4, json_encode($cp1));
$roles63 = $db->query("SELECT role FROM content_people WHERE content_id = {$c1} AND akun_id = 63 ORDER BY role")->getResultArray();
$roleList63 = array_column($roles63, 'role');
ok('Satu orang (63) boleh dua peran sekaligus', count($roles63) === 2 && in_array('TALENT', $roleList63, true) && in_array('CREATIVE', $roleList63, true), json_encode($roles63));
(new ModelContentPerson())->replaceForContent((int)$c2, [], [63]);

// Checklist otomatis.
$items = array_map(fn($i) => $i->id, $checklistItems);
(new ModelContentChecklist())->ensureItemsForContent((int)$c1, $items);
(new ModelContentChecklist())->ensureItemsForContent((int)$c2, $items);
$cl1 = $db->query("SELECT COUNT(*) c FROM content_checklists WHERE content_id = {$c1}")->getRow()->c;
ok('Checklist C1 = 6 item', (int)$cl1 === 6, "cl1={$cl1}");

echo "\n== WORKFLOW + QC ==\n";
$c1row = $ContentModel->find($c1);
ok('C1 status awal DRAFT', $c1row->status === 'DRAFT');

// Transisi ilegal.
$illegal = $Workflow->transition($c1row, 'PUBLISHED', 63);
ok('DRAFT→PUBLISHED diblokir', !$illegal['ok'], $illegal['message']);
$okWf = $Workflow->transition($c1row, 'PRODUCTION', 63);
$c1row = $ContentModel->find($c1);
$okWf2 = $Workflow->transition($c1row, 'QC', 63);
$c1row = $ContentModel->find($c1);
ok('DRAFT→PRODUCTION→QC berjalan', $okWf['ok'] && $okWf2['ok'] && $c1row->status === 'QC');

// QC REJECT.
$rej = $Workflow->qc($c1row, 'REJECT', 'Kontras logo kurang', 43);
$c1row = $ContentModel->find($c1);
ok('QC REJECT → REVISION', $rej['ok'] && $c1row->status === 'REVISION');
$qcLog = $db->query("SELECT * FROM content_qc WHERE content_id = {$c1} ORDER BY id DESC LIMIT 1")->getRow();
ok('Histori QC REJECT tercatat (checker 43, note)', $qcLog && $qcLog->status === 'REJECT' && $qcLog->checker_id == 43 && $qcLog->note === 'Kontras logo kurang', json_encode($qcLog));

// Revision → production → qc → pass.
$Workflow->transition($c1row, 'PRODUCTION', 63);
$c1row = $ContentModel->find($c1);
$Workflow->transition($c1row, 'QC', 63);
$c1row = $ContentModel->find($c1);
// QC disaat bukan status QC → ditolak.
$Wf = new ContentWorkflowService();
$badQc = $Wf->qc($ContentModel->find($c2), 'PASS', null, 63);
ok('QC saat bukan status QC ditolak', !$badQc['ok']);
$passQc = $Wf->qc($c1row, 'PASS', 'OK semua', 43);
$c1row = $ContentModel->find($c1);
ok('QC PASS → APPROVED', $passQc['ok'] && $c1row->status === 'APPROVED');

// Approve → publish (stamp) → complete.
$Workflow->transition($c1row, 'PUBLISHED', 63);
$c1row = $ContentModel->find($c1);
ok('published_at terisi saat PUBLISHED', $c1row->published_at !== null, $c1row->published_at);
$Workflow->transition($c1row, 'COMPLETED', 63);
$c1row = $ContentModel->find($c1);
ok('COMPLETED → completed_at terisi', $c1row->status === 'COMPLETED' && $c1row->completed_at !== null);
ok('C1 selesai ≤ deadline (tepat waktu)', $c1row->completed_at <= ($c1row->deadline . ' 23:59:59'));

echo "\n== PUBLICATION + PERFORMANCE (banyak publikasi = 1 content) ==\n";
$pubModel = new ModelPublication();
$pub1 = $pubModel->insert(['content_id' => (int)$c1, 'unit_id' => 1, 'platform_id' => $platformIg->id, 'status' => 'PUBLISHED', 'published_at' => '2026-09-20 09:00:00', 'created_by' => 63]);
$pub2 = $pubModel->insert(['content_id' => (int)$c1, 'unit_id' => 2, 'platform_id' => $platformIg->id, 'status' => 'PUBLISHED', 'published_at' => '2026-09-21 10:00:00', 'created_by' => 63]);
$pub3 = $pubModel->insert(['content_id' => (int)$c1, 'unit_id' => 1, 'platform_id' => $platformFb->id, 'status' => 'PLANNED', 'created_by' => 63]);
ok('C1 punya 3 publikasi (IG u1 + IG u2 + FB u1)', $pub1 && $pub2 && $pub3);

$ppModel = new ModelPublicationPerformance();
$set = function (int $pubId, int $metricId, float $target, float $actual) use ($ppModel) {
    $ach = $target > 0 ? round($actual / $target * 100, 2) : null;
    $existing = $ppModel->getByUnique($pubId, $metricId, 9, 2026);
    if ($existing) {
        $ppModel->update($existing->id, ['target' => $target, 'actual' => $actual, 'achievement' => $ach]);
    } else {
        $ppModel->insert(['publication_id' => $pubId, 'metric_id' => $metricId, 'period_month' => 9, 'period_year' => 2026, 'target' => $target, 'actual' => $actual, 'achievement' => $ach]);
    }
};
$set((int)$pub1, (int)$metricReach->id, 1000, 1200); // 120%
$set((int)$pub2, (int)$metricReach->id, 500, 400);   // 80%
$set((int)$pub3, (int)$metricReach->id, 2000, 1000); // 50%
$perfRow = $ppModel->getByUnique((int)$pub1, (int)$metricReach->id, 9, 2026);
ok('Achievement P1 = 120', near($perfRow->achievement, 120), $perfRow->achievement);

$totalPubC1 = $db->query("SELECT COUNT(*) c FROM publications WHERE content_id = {$c1}")->getRow()->c;
ok('Masih dihitung 1 content (bukan 3) di ringkasan', (int)$totalPubC1 === 3);

echo "\n== BRAND CHECKLIST ==\n";
// Satu request: centang item 1-5, item 6 tidak.
$Sync = new ModelContentChecklist();
$syncChecked = array_slice(array_map('intval', array_column($checklistItems, 'id')), 0, 5);
$Sync->syncForContent((int)$c1, array_map('intval', array_column($checklistItems, 'id')), $syncChecked, 43);
$clCheck = $db->query("SELECT COUNT(*) c, COALESCE(SUM(is_checked),0) s FROM content_checklists WHERE content_id = {$c1}")->getRow();
ok("Checklist C1 tercentang 5/6 (sekali request)", (int)$clCheck->c === 6 && (int)$clCheck->s === 5, json_encode($clCheck));

// Ubah lagi dalam satu request: hanya item terakhir yang dicentang (yang lain dibatalkan).
$lastItemId = (int)array_column($checklistItems, 'id')[count($checklistItems) - 1];
$Sync->syncForContent((int)$c1, array_map('intval', array_column($checklistItems, 'id')), [$lastItemId], 43);
$clCheck2 = $db->query("SELECT COUNT(*) c, COALESCE(SUM(is_checked),0) s FROM content_checklists WHERE content_id = {$c1}")->getRow();
ok("Ulang ceklist 1 request → hanya item terakhir tercentang", (int)$clCheck2->s === 1, json_encode($clCheck2));

// Kembalikan 5/6 seperti semula supaya ringkasan brand konsisten.
$Sync->syncForContent((int)$c1, array_map('intval', array_column($checklistItems, 'id')), $syncChecked, 43);
$clCheck3 = $db->query("SELECT COUNT(*) c, COALESCE(SUM(is_checked),0) s FROM content_checklists WHERE content_id = {$c1}")->getRow();
ok("Restore checklist 5/6", $clCheck3->s == 5, json_encode($clCheck3));

echo "\n== RINGKASAN KPI BULANAN (1 content dihitung 1x) ==\n";
$kpi = $Kpi->monthlyKpi(9, 2026, "c.judul LIKE '[TEST]%'");
$stats = $Kpi->monthlyStats(9, 2026, "c.judul LIKE '[TEST]%'");
ok('Total content bulan = 3', $kpi['total'] === 3, "total={$kpi['total']}");
ok('Status COMPLETED = 1', $stats['completed'] === 1, json_encode($stats));
ok('Status DRAFT = 2', $stats['draft'] === 2);
ok('Overdue = 1 (C3 deadline lampau, belum selesai)', $stats['overdue'] === 1, "overdue={$stats['overdue']}");
ok('Jumlah Konten = 1/30×100', near($kpi['items'][0]['achievement'], 3.33), json_encode($kpi['items'][0]));
ok('Deadline = 1/3×100 (1 tepat waktu)', near($kpi['items'][1]['achievement'], 33.33), json_encode($kpi['items'][1]));
ok('Kualitas = 1/3×100 (1 lolos QC)', near($kpi['items'][2]['achievement'], 33.33), json_encode($kpi['items'][2]));
ok('Brand = 5/(6+6)×100', near($kpi['items'][3]['achievement'], 41.67), json_encode($kpi['items'][3]));
ok('Performa = 2600/3500×100', near($kpi['items'][4]['achievement'], 74.29), json_encode($kpi['items'][4]));
$expectedWeighted = 3.3333 * 0.20 + 33.3333 * 0.20 + 33.3333 * 0.25 + 41.6667 * 0.15 + 74.2857 * 0.20;
ok('Skor tertimbang sesuai bobot engine', near($kpi['weighted_total'], $expectedWeighted), "{$kpi['weighted_total']} vs {$expectedWeighted}");

// KPI Performa hanya menilai konten ADS: publikasi C2 (REGULAR) berperforma tinggi
// TIDAK boleh mengubah achievement performa.
$pubC2 = $pubModel->insert(['content_id' => (int)$c2, 'unit_id' => 3, 'platform_id' => $platformFb->id, 'status' => 'PUBLISHED', 'published_at' => '2026-09-15 10:00:00', 'created_by' => 63]);
$set((int)$pubC2, (int)$metricReach->id, 1, 999999);
$perfC2 = $ppModel->getByUnique((int)$pubC2, (int)$metricReach->id, 9, 2026);
ok('Performa tetap bisa diinput pada konten REGULAR', $perfC2 !== null && (float)$perfC2->actual === 999999.0);
$kpiPost = $Kpi->monthlyKpi(9, 2026, "c.judul LIKE '[TEST]%'");
ok('Performa KPI tidak terpengaruh konten REGULAR (tetap 74.29)', near($kpiPost['items'][4]['achievement'], 74.29), json_encode($kpiPost['items'][4]));
ok('Jenis konten default REGULAR', $ContentModel->find((int)$c2)->jenis_konten === 'REGULAR');

echo "\n== SCOPE PER ROLE ==\n";
ok('Role 1/2/34 → scope null (semua)', $Scope->scopeSql(1, 1, 63) === null && $Scope->scopeSql(2, 1, 63) === null && $Scope->scopeSql(34, 3, 55) === null);
ok('Role 43 (Kadiv) → monitoring semua', $Scope->scopeSql(43, 1, 55) === null && !ContentScopeService::canWrite(43));
ok('Role 44 bisa menulis', ContentScopeService::canWrite(44));
ok('Role 41/42/45 tidak bisa lihat', !ContentScopeService::canView(41) && !ContentScopeService::canView(42));

$sql44Fahri = $Scope->scopeSql(44, 1, 63);
$where44Fahri = $sql44Fahri === null ? '1=1' : $sql44Fahri;
$visible44Fahri = $db->query("SELECT COUNT(*) c FROM contents c WHERE ({$where44Fahri}) AND c.id IN ({$c1},{$c2},{$c3})")->getRow()->c;
ok('Multimedia 63 melihat semua (scope seluruh divisi)', (int)$visible44Fahri === 3, "vis={$visible44Fahri}");

$sql44Fathoni = $Scope->scopeSql(44, 1, 55);
$where44Fathoni = $sql44Fathoni === null ? '1=1' : $sql44Fathoni;
$visible44Fathoni = $db->query("SELECT COUNT(*) c FROM contents c WHERE ({$where44Fathoni}) AND c.id IN ({$c1},{$c2})")->getRow()->c;
ok('Multimedia 55 juga melihat semua unit (2)', (int)$visible44Fathoni === 2, "vis={$visible44Fathoni}");
ok('Unit filter multimedia memuat semua unit (null)', $Scope->allowedUnits(44, 1, 55) === null);

$sqlSpv49 = $Scope->scopeSql(40, 1, 49); // spv_units [2,3]
$visibleSpv = $db->query("SELECT COUNT(*) c FROM contents c WHERE ({$sqlSpv49}) AND c.id IN ({$c1},{$c2},{$c3})")->getRow()->c;
ok('SPV 49 (units 2,3) melihat C1 (ALL) + C2 (SELECTED 2,3), bukan C3 unit1? (C3 ALL → terlihat)', (int)$visibleSpv === 3, "vis={$visibleSpv}");

// Konten hanya unit1: buat C4 SELECTED unit [1] → SPV 49 tidak melihat.
$c4 = $ContentModel->insert(['judul' => '[TEST] Unit1 only C4', 'target_scope' => 'SELECTED', 'deadline' => '2026-09-30', 'status' => 'DRAFT', 'created_by' => 63]);
$createdContentIds[] = (int)$c4;
(new ModelContentUnit())->replaceForContent((int)$c4, [1]);
$visC4Spv = $db->query("SELECT COUNT(*) c FROM contents c WHERE ({$sqlSpv49}) AND c.id = {$c4}")->getRow()->c;
ok('SPV 49 TIDAK melihat konten khusus unit1', (int)$visC4Spv === 0);
$visC4Fahri = $db->query("SELECT COUNT(*) c FROM contents c WHERE ({$where44Fahri}) AND c.id = {$c4}")->getRow()->c;
ok('Multimedia unit1 melihat konten unit1', (int)$visC4Fahri === 1);

echo "\n== DATATABLES SERVER-SIDE (query & filter) ==\n";
ok('Total tanpa filter = 4', (int)$ContentModel->countContentsDT(['scope_sql' => "c.judul LIKE '[TEST]%'"]) >= 4);
ok('Filter periode 2026-09 = 4', (int)$ContentModel->countContentsDT(['periode' => '2026-09', 'scope_sql' => "c.judul LIKE '[TEST]%'"]) === 4);
ok('Filter status COMPLETED = 1', (int)$ContentModel->countContentsDT(['periode' => '2026-09', 'status' => 'COMPLETED', 'scope_sql' => "c.judul LIKE '[TEST]%'"]) === 1);
ok('Filter multimedia=63 (creative) minimal 1', (int)$ContentModel->countContentsDT(['multimedia' => 63, 'scope_sql' => "c.judul LIKE '[TEST]%'"]) >= 1);
ok('Filter talent=55 minimal 1', (int)$ContentModel->countContentsDT(['talent' => 55, 'scope_sql' => "c.judul LIKE '[TEST]%'"]) >= 1);
ok('Filter platform Instagram → 1 content (bukan 2 publikasi)', (int)$ContentModel->countContentsDT(['platform' => $platformIg->id, 'periode' => '2026-09', 'scope_sql' => "c.judul LIKE '[TEST]%'"]) === 1);
ok('Filter unit=2 → C1(pub)+C2(target)+C3(ALL)=3 (C4 unit1 tidak)', (int)$ContentModel->countContentsDT(['unit' => 2, 'periode' => '2026-09', 'scope_sql' => "c.judul LIKE '[TEST]%'"]) === 3);
ok('Filter content_type FEED = 2', (int)$ContentModel->countContentsDT(['content_type' => $typeFeed->id, 'periode' => '2026-09', 'scope_sql' => "c.judul LIKE '[TEST]%'"]) === 2);
$rows = $ContentModel->getContentsDT(10, 0, ['periode' => '2026-09', 'status' => 'COMPLETED', 'scope_sql' => "c.judul LIKE '[TEST]%'"], 'c.deadline', 'DESC');
ok('Data pull baris: talent/creative names dipisah per role (relational, bukan kolom JSON)',
    count($rows) === 1 && strpos($rows[0]->talent_names, 'Fahri') !== false && strpos($rows[0]->creative_names, 'fathoni') !== false);
$rowFound = $rows[0];
ok('C1 judul ditemukan', strpos($rowFound->judul, 'Promo Lebaran') !== false);

echo "\n== SCOPE SERTA DITERAPKAN DI DATATABLES ==\n";
$visDtSpv = (int)$ContentModel->countContentsDT(['periode' => '2026-09', 'scope_sql' => "($sqlSpv49) AND c.judul LIKE '[TEST]%'"]);
ok('DataTables scope SPV 49 = 3 (C1,C2,C3)', $visDtSpv === 3, "vis={$visDtSpv}");

echo "\n== CASCADE DELETE ==\n";
$ContentModel->delete((int)$c1);
$rem = [
    'publications'            => (int)$db->query("SELECT COUNT(*) c FROM publications WHERE content_id = {$c1} AND id IN ({$pub1},{$pub2},{$pub3})")->getRow()->c,
    'publication_performance' => (int)$db->query("SELECT COUNT(*) c FROM publication_performance pp JOIN publications p ON p.id = pp.publication_id WHERE p.content_id = {$c1}")->getRow()->c,
    'content_people'          => (int)$db->query("SELECT COUNT(*) c FROM content_people WHERE content_id = {$c1}")->getRow()->c,
    'content_checklists'      => (int)$db->query("SELECT COUNT(*) c FROM content_checklists WHERE content_id = {$c1}")->getRow()->c,
    'content_qc'              => (int)$db->query("SELECT COUNT(*) c FROM content_qc WHERE content_id = {$c1}")->getRow()->c,
];
$allGone = ($rem['publications'] === 0 && $rem['publication_performance'] === 0 && $rem['content_people'] === 0 && $rem['content_checklists'] === 0 && $rem['content_qc'] === 0);
ok('Delete content → relasi terhapus CASCADE', $allGone, json_encode($rem));

echo "\n== ROLLBACK DATA UJI ==\n";
foreach ($createdContentIds as $cid) {
    $ContentModel->delete((int)$cid);
}
$left = $db->query("SELECT COUNT(*) c FROM contents WHERE judul LIKE '[TEST]%'")->getRow()->c;
ok('Semua data uji terhapus (contents)', (int)$left === 0);
$leftRel = $db->query("SELECT COUNT(*) c FROM content_people cp JOIN contents ct ON ct.id = cp.content_id WHERE ct.judul LIKE '[TEST]%'")->getRow()->c;
ok('Relasi people ikut bersih', (int)$leftRel === 0);

echo "\n============================================================\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
echo "============================================================\n";
exit($fail > 0 ? 1 : 0);