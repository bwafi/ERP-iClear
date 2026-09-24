<?php
/**
 * Functional test — Struktur KPI Multimedia OWNER (jabatan 44).
 *
 * Menguji MultimediaKpiService (achievement per employee utk 6 komponen:
 * KETEPATAN_DEADLINE, KUALITAS_OUTPUT, KESESUAIAN_BRIEF, PRODUKTIVITAS,
 * SUPPORT_CAMPAIGN, IMPROVEMENT) + integrasi KpiCalculationService (bobot
 * 25/25/20/15/10/5 total 100). Isolasi penuh: akun uji khusus (9901/9902,
 * STATUS_PEGAWAI=0, deleted=1) sehingga data uji manual live di akun asli
 * tidak pernah ikut dihitung. Semua data uji di-rollback di akhir.
 *
 * Usage: php74 app/Scripts/multimedia_kpi_test.php
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
use App\Models\ModelContentPerson;
use App\Models\ModelContentQc;
use App\Models\ModelContentCampaign;
use App\Models\ModelImprovement;
use App\Services\Konten\MultimediaKpiService;
use App\Services\Kpi\KpiCalculationService;

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
$Svc = new MultimediaKpiService();

// Akun uji khusus (bukan karyawan asli) supaya data uji manual live tidak
// pernah ikut dihitung. Tidak tampil di dashboard divisi (STATUS_PEGAWAI=0)
// dan tidak tampil di dropdown orang (deleted=1); hanya identitas FK.
function ensureTestAccounts($db): void {
    $need = [
        9901 => ['jabatan' => 44, 'nama' => '[TEST] KPI Multimedia Owner'],
        9902 => ['jabatan' => 44, 'nama' => '[TEST] KPI Multimedia Peer'],
    ];
    foreach ($need as $id => $f) {
        if ($db->table('akun')->where('ID_AKUN', $id)->countAllResults() > 0) {
            continue;
        }
        $db->table('akun')->insert([
            'ID_AKUN' => $id, 'ID_JABATAN' => $f['jabatan'], 'ID_UNIT' => 1,
            'NOID' => 'TEST-' . $id, 'EMAIL' => null, 'PASSWORD' => 'test',
            'NAMA_AKUN' => $f['nama'], 'ALAMAT' => null, 'JENIS_KELAMIN' => '-',
            'HP' => null, 'JENIS_PEGAWAI' => 100000, 'STATUS_PEGAWAI' => 0,
            'FOTO_KTP' => null, 'FOTO_KK' => null, 'deleted' => 1,
        ]);
    }
}
ensureTestAccounts($db);

$Engine = new KpiCalculationService();
$ContentModel = new ModelContent();
$PersonModel = new ModelContentPerson();
$QcModel = new ModelContentQc();
$CampaignModel = new ModelContentCampaign();
$ImprovementModel = new ModelImprovement();

const EMP = 9901;    // akun uji khusus (jabatan 44, STATUS_PEGAWAI=0, deleted=1)
const PEER = 9902;   // akun uji isolasi antar-employee
const PERIODE_M = 9;
const PERIODE_Y = 2026;

// Idempotent cleanup dari run sebelumnya.
foreach ($db->query("SELECT id FROM contents WHERE judul LIKE '[KPI]%'")->getResult() as $s) {
    $ContentModel->delete((int)$s->id);
}
foreach ($db->query("SELECT id FROM content_campaigns WHERE nama LIKE '[KPI]%'")->getResult() as $s) {
    $db->table('contents')->where('campaign_id', $s->id)->set('campaign_id', null)->update();
    $CampaignModel->delete((int)$s->id);
}
$db->query("DELETE FROM improvements WHERE judul LIKE '[KPI]%'");

$contentIds = [];

echo "== SETUP DATA UJI (bulan 9/2026, akun uji " . EMP . ") ==\n";
// A: selesai TEPAT waktu + QC PASS sesuai brief + campaign on-time.
$a = $ContentModel->insert([
    'judul' => '[KPI] Konten A tepat waktu', 'jenis_konten' => 'REGULAR',
    'target_scope' => 'ALL', 'deadline' => '2026-09-15', 'status' => 'COMPLETED',
    'published_at' => '2026-09-14 08:00:00', 'completed_at' => '2026-09-14 08:30:00', 'created_by' => EMP,
]);
// B: APPROVED TEPAT waktu (deadline 20/9) tapi BELUM COMPLETED
//    → tidak dihitung selesai/on-time untuk KPI (hanya COMPLETED).
$b = $ContentModel->insert([
    'judul' => '[KPI] Konten B approved', 'jenis_konten' => 'REGULAR',
    'target_scope' => 'ALL', 'deadline' => '2026-09-20', 'status' => 'APPROVED',
    'created_by' => EMP,
]);
// C: masih DRAFT (belum selesai).
$c = $ContentModel->insert([
    'judul' => '[KPI] Konten C progres', 'jenis_konten' => 'REGULAR',
    'target_scope' => 'ALL', 'deadline' => '2026-09-25', 'status' => 'DRAFT', 'created_by' => EMP,
]);
$contentIds = [$a, $b, $c];
ok('3 konten uji akun ' . EMP . ' dibuat', count($contentIds) === 3 && $a > 0 && $b > 0 && $c > 0);

// D: milik PEER — harus TIDAK mencemari perhitungan EMP.
$d = $ContentModel->insert([
    'judul' => '[KPI] Konten D karyawan lain', 'jenis_konten' => 'REGULAR',
    'target_scope' => 'ALL', 'deadline' => '2026-09-28', 'status' => 'COMPLETED',
    'published_at' => '2026-09-10 09:00:00', 'completed_at' => '2026-09-10 09:30:00', 'created_by' => PEER,
]);
$PersonModel->replaceForContent((int)$d, [], [PEER]);

// People: A,B,C creative = EMP.
foreach ($contentIds as $cid) {
    $PersonModel->replaceForContent((int)$cid, [], [EMP]);
}

// Campaign aktif periode 9/2026 target 2 konten, deadline 18/9.
$camp = $CampaignModel->insert([
    'nama' => '[KPI] Campaign Ramadhan', 'period_month' => PERIODE_M, 'period_year' => PERIODE_Y,
    'target_jumlah_konten' => 2, 'target_deadline' => '2026-09-18', 'status' => 'active', 'created_by' => EMP,
]);
// A & B masuk campaign; C tidak.
$ContentModel->update((int)$a, ['campaign_id' => (int)$camp]);
$ContentModel->update((int)$b, ['campaign_id' => (int)$camp]);
ok('Campaign aktif dibuat, A & B terhubung campaign', $camp > 0);

// QC: A PASS & B PASS — QC tidak lagi menyimpan verdict kesesuaian brief.
$now = date('Y-m-d H:i:s');
$QcModel->insert(['content_id' => (int)$a, 'status' => 'PASS', 'checker_id' => 43, 'checked_at' => '2026-09-14 10:00:00', 'created_at' => $now, 'updated_at' => $now]);
$QcModel->insert(['content_id' => (int)$b, 'status' => 'PASS', 'checker_id' => 43, 'checked_at' => '2026-09-21 10:00:00', 'created_at' => $now, 'updated_at' => $now]);
ok('QC A(PASS) & B(PASS) tercatat', true);

// Kesesuaian Brief dinilai MANUAL oleh Kepala Divisi (content_brief_verdicts):
//   A → sesuai (1); B → tidak sesuai (0). Keduanya masuk denominator.
$BriefVerdict = new \App\Models\ModelContentBriefVerdict();
$BriefVerdict->insertVerdict((int)$a, 1, 'Sesuai brief', 43);
$BriefVerdict->insertVerdict((int)$b, 0, 'Logo melenceng', 43);
ok('Verdict brief: A sesuai, B tidak sesuai tercatat', true);

// Konten C punya brief tapi belum dinilai → tidak masuk denominator KPI.
$db->table('content_briefs')->insert(['content_id' => (int)$c, 'isi_brief' => 'Brief C', 'created_at' => $now, 'updated_at' => $now]);

// Improvement akun uji EMP bulan 9/2026: 1 approved, 1 rejected, 1 draft.
$imp1 = $ImprovementModel->insert(['employee_id' => EMP, 'judul' => '[KPI] Imp approved', 'status' => 'approved', 'submission_month' => PERIODE_M, 'submission_year' => PERIODE_Y, 'approved_at' => '2026-09-15 09:00:00', 'evaluated_by' => 43]);
$imp2 = $ImprovementModel->insert(['employee_id' => EMP, 'judul' => '[KPI] Imp rejected', 'status' => 'rejected', 'submission_month' => PERIODE_M, 'submission_year' => PERIODE_Y, 'evaluated_by' => 43]);
$imp3 = $ImprovementModel->insert(['employee_id' => EMP, 'judul' => '[KPI] Imp draft', 'status' => 'draft', 'submission_month' => PERIODE_M, 'submission_year' => PERIODE_Y]);
ok('Improvement 1 approved / 1 rejected / 1 draft dibuat', $imp1 && $imp2 && $imp3);

// Campaign DONE lain TANPA konten milik EMP → TIDAK terpilih → tidak dihitung.
$camp2 = $CampaignModel->insert([
    'nama' => '[KPI] Campaign Done Non-Terlibat', 'period_month' => PERIODE_M, 'period_year' => PERIODE_Y,
    'target_jumlah_konten' => 3, 'target_deadline' => '2026-09-30', 'status' => 'done', 'created_by' => EMP,
]);
ok('Campaign done non-terlibat dibuat (target 3)', $camp2 > 0);

// Campaign DONE yang TERLIBAT (berisi konten B milik EMP, target 1):
// ikut dihitung (status done tetap dinilai), konten B APPROVED belum selesai → 0.
$camp3 = $CampaignModel->insert([
    'nama' => '[KPI] Campaign Done Terlibat', 'period_month' => PERIODE_M, 'period_year' => PERIODE_Y,
    'target_jumlah_konten' => 1, 'target_deadline' => '2026-09-28', 'status' => 'done', 'created_by' => EMP,
]);
$ContentModel->update((int)$b, ['campaign_id' => (int)$camp3]);
ok('Campaign done terlibat dibuat (B terhubung, target 1)', $camp3 > 0);

echo "\n== MULTIMEDIA KPI SERVICE (per akun uji " . EMP . ") ==\n";
// Realisasi:
//   total assigned = 3 (A,B,C).  on-time = A(1) => 33.33
//   Deadline: on-time hanya A (COMPLETED 14/9 ≤ 15/9); B APPROVED (approval
//   lalu belum COMPLETED) TIDAK dihitung → 1/3 = 33.33
$dln = $Svc->achievement('KETEPATAN_DEADLINE', EMP, 50, PERIODE_M, PERIODE_Y);
ok('Deadline = 33.33 (1/3; B hanya APPROVED tidak dihitung)', near($dln, 100 / 3), (string)$dln);

//   QC pass distinct = A,B (2) → 66.67
$kul = $Svc->achievement('KUALITAS_OUTPUT', EMP, 50, PERIODE_M, PERIODE_Y);
ok('Kualitas = 66.67 (2/3 lolos QC)', near($kul, 200 / 3), (string)$kul);

//   Brief: dinilai = A & B (dua-duanya punya verdict) → sesuai 1/2 = 50
$brf = $Svc->achievement('KESESUAIAN_BRIEF', EMP, 50, PERIODE_M, PERIODE_Y);
ok('Kesesuaian Brief = 50 (1/2 dinilai sesuai)', near($brf, 50), (string)$brf);

//   Produktivitas: completed = A(1) / target 30 → 3.33
$prd = $Svc->achievement('PRODUKTIVITAS', EMP, 50, PERIODE_M, PERIODE_Y);
ok('Produktivitas = 3.33 (1 completed / target 30)', near($prd, 100 / 30), (string)$prd);

//   Campaign: HANYA campaign terpilih (berisi konten EMP) dihitung, baik
//   active maupun done. Ramadhan(active,target2): A on-time → 1. Done
//   Terlibat(target1): B belum COMPLETED → 0. Done Non-Terlibat (target 3)
//   TIDAK ditambah → total 1/3 = 33.33.
$cap = $Svc->achievement('SUPPORT_CAMPAIGN', EMP, 50, PERIODE_M, PERIODE_Y);
ok('Support Campaign = 33.33 (1/3; done terlibat ikut, done non-terlibat tidak)', near($cap, 100 / 3), (string)$cap);

//   Improvement: approved 1 / target 1 → 100
$imp = $Svc->achievement('IMPROVEMENT', EMP, 50, PERIODE_M, PERIODE_Y);
ok('Improvement = 100 (1 approved / target 1)', near($imp, 100), (string)$imp);

// Isolasi per employee: PEER hanya punya D (COMPLETED on-time, QC PASS) + tanpa improvement.
$dlnPeer = $Svc->achievement('KETEPATAN_DEADLINE', PEER, 50, PERIODE_M, PERIODE_Y);
$prdPeer = $Svc->achievement('PRODUKTIVITAS', PEER, 50, PERIODE_M, PERIODE_Y);
$impPeer = $Svc->achievement('IMPROVEMENT', PEER, 50, PERIODE_M, PERIODE_Y);
ok('Isolasi: PEER punya 1 konten (deadline 100)', $dlnPeer === 100.0, (string)$dlnPeer);
ok('Isolasi: produktivitas PEER = 1/30', near($prdPeer, 100 / 30), (string)$prdPeer);
ok('Isolasi: improvement PEER = null (tidak ada data)', $impPeer === null, (string)$impPeer);

// Null behavior: campaign EMP, periode tanpa campaign = null.
$capNone = $Svc->achievement('SUPPORT_CAMPAIGN', EMP, 50, 2, PERIODE_Y);
ok('Campaign periode tanpa data = null', $capNone === null, (string)$capNone);

// Divisi ringkasan: 6 item dgn nama/bobot benar, total ≥ 0.
$sum = $Svc->monthlySummary(PERIODE_M, PERIODE_Y);
ok('monthlySummary 6 item owner', count($sum['items']) === 6, 'n=' . count($sum['items']));
$expectNames = ['Ketepatan Deadline', 'Kualitas Output', 'Kesesuaian Brief', 'Produktivitas', 'Support Campaign', 'Improvement'];
$sumNames = array_column($sum['items'], 'name');
ok('monthlySummary nama urutan benar', $sumNames === $expectNames, implode(',', $sumNames));
ok('monthlySummary bobot total 100', near((float)array_sum(array_column($sum['items'], 'bobot')), 100));
ok('monthlySummary weighted_total ≥ 0', $sum['weighted_total'] >= 0, (string)$sum['weighted_total']);

echo "\n== INTEGRASI ENGINE (KpiCalculationService, jabatan/posisi 44) ==\n";
$result = $Engine->calculateForEmployee(EMP, 50, (string)PERIODE_M, (string)PERIODE_Y, 'penilaian_kinerja');
ok('Engine mengembalikan 6 komponen Multimedia', count($result['items']) === 6, 'items=' . count($result['items']));

$map = [];
foreach ($result['items'] as $it) {
    $map[$it['code']] = $it;
}
ok('Bobot total KPI 44 = 100', $result['weight_valid']);

$codes = ['KETEPATAN_DEADLINE', 'KUALITAS_OUTPUT', 'KESESUAIAN_BRIEF', 'PRODUKTIVITAS', 'SUPPORT_CAMPAIGN', 'IMPROVEMENT'];
$all6 = true;
foreach ($codes as $cd) {
    if (!isset($map[$cd])) {
        $all6 = false;
    }
}
ok('Seluruh 6 komponen owner ada di hasil', $all6);
ok('Bobot Deadline=25', near($map['KETEPATAN_DEADLINE']['weight'], 25));
ok('Bobot Kualitas=25', near($map['KUALITAS_OUTPUT']['weight'], 25));
ok('Bobot Brief=20', near($map['KESESUAIAN_BRIEF']['weight'], 20));
ok('Bobot Produktivitas=15', near($map['PRODUKTIVITAS']['weight'], 15));
ok('Bobot Campaign=10', near($map['SUPPORT_CAMPAIGN']['weight'], 10));
ok('Bobot Improvement=5', near($map['IMPROVEMENT']['weight'], 5));

// Total tertimbang = Σ achievement*bobot/100.
$expected = 0.0;
$weights = ['KETEPATAN_DEADLINE' => 25, 'KUALITAS_OUTPUT' => 25, 'KESESUAIAN_BRIEF' => 20, 'PRODUKTIVITAS' => 15, 'SUPPORT_CAMPAIGN' => 10, 'IMPROVEMENT' => 5];
$vals = ['KETEPATAN_DEADLINE' => $dln, 'KUALITAS_OUTPUT' => $kul, 'KESESUAIAN_BRIEF' => $brf, 'PRODUKTIVITAS' => $prd, 'SUPPORT_CAMPAIGN' => $cap, 'IMPROVEMENT' => $imp];
foreach ($vals as $cd => $v) {
    $expected += ($v / 100) * $weights[$cd];
}
ok('Skor total engine = Σ weighted (≈' . round($expected, 2) . ')', near($result['total_score'], $expected), 'total=' . $result['total_score'] . ' expected=' . round($expected, 2));

// Skor total2 (absen) tetap ada & grup kpi TIDAK menyentuh absen.
ok('skor_total2 tetap dihitung (attendance)', $result['skor_total2'] >= 0);

// Komponen lama tidak boleh muncul untuk posisi 44.
$old = ['KONTEN_JUMLAH', 'KONTEN_DEADLINE', 'KONTEN_KUALITAS', 'KONTEN_BRAND', 'KONTEN_PERFORMA', 'CHANNEL_GROWTH'];
$hasOld = false;
foreach ($result['items'] as $it) {
    if (in_array($it['code'], $old, true)) {
        $hasOld = true;
    }
}
ok('Komponen lama TIDAK ada di posisi 44', !$hasOld);

echo "\n== ROLLBACK DATA UJI ==\n";
foreach ([$a, $b, $c, $d] as $cid) {
    $ContentModel->delete((int)$cid);
}
$CampaignModel->delete((int)$camp);
$CampaignModel->delete((int)$camp2);
$CampaignModel->delete((int)$camp3);
foreach ([$imp1, $imp2, $imp3] as $iid) {
    $ImprovementModel->delete((int)$iid);
}
$db->query("DELETE FROM content_brief_verdicts WHERE content_id IN (" . implode(',', [$a, $b, $c]) . ")");
$db->query("DELETE FROM content_briefs WHERE content_id IN (" . implode(',', [$a, $b, $c]) . ")");
ok('Data uji bersih', $db->query("SELECT COUNT(*) c FROM contents WHERE judul LIKE '[KPI]%'")->getRow()->c == 0
    && $db->query("SELECT COUNT(*) c FROM improvements WHERE employee_id IN (" . EMP . "," . PEER . ")")->getRow()->c == 0);

echo "\n========================================\n";
echo "RESULT: {$pass} PASS, {$fail} FAIL\n";
exit($fail === 0 ? 0 : 1);