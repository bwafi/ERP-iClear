# Campaign dari Meta + Reporting Mingguan — Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Campaign ERP diisi otomatis dari Meta (unit ditebak dari nama, bisa dikoreksi), dan KPI `REPORTING` menjadi laporan mingguan 4×/bulan yang dinilai dari ketepatan waktu kirim.

**Architecture:** Sync memakai `MetaAdsClient::fetchCampaignOptions()` yang sudah ada, menulis baris `marketing_campaigns` lewat `meta_campaign_id` (UNIQUE). Data Oktober yang sudah ada diadopsi, bukan dihapus, sehingga `meta:pull` (yang sudah "Meta menang") menimpa baris manual tanpa menggandakan. Reporting mingguan memakai tabel baru + satu tombol di halaman Laporan; skornya dihitung di `DigitalMarketingKpiService` mulai periode 2026-11.

**Tech Stack:** PHP 7.4, CodeIgniter 4, MariaDB, tes berupa skrip `app/Scripts/*_test.php` (pola `ok()` + PASS/FAIL).

**Spec:** `docs/superpowers/specs/2026-10-18-campaign-dari-meta-dan-reporting-mingguan-design.md`

## Global Constraints

- PHP 7.4: tanpa `match`, tanpa named arguments, tanpa `str_contains`.
- Tanggal disimpan `Y-m-d`; perbandingan tanggal memakai string/`DateTime`, bukan zona waktu.
- Jangan mengubah data September 2026 (`marketing_campaigns` id 1–10, baris iklan 1–30 Sep).
- Jangan hapus baris iklan. Data manual Okt 1–12 boleh ditimpa oleh `meta:pull`.
- Kata kunci unit ada di SATU tempat (`UnitDetector::UNIT_KEYWORDS`).
- Semua perintah tes: `php74 app/Scripts/<file>.php` (fallback `php`).

---

### Task 1: Skema + adopsi 5 campaign Oktober

**Files:**
- Create: `app/Database/Migrations/2026-10-18-000100_AddUnitSumberAndLaporanMingguan.php`
- Test: `app/Scripts/marketing_weekly_schema_test.php`

**Interfaces:**
- Produces: kolom `marketing_campaigns.unit_sumber` ENUM(`auto`,`manual`) DEFAULT `auto`; tabel `marketing_laporan_mingguan` (`id`, `period_year`, `period_month`, `minggu`, `submitted_by`, `submitted_at`, UNIQUE `period_year+period_month+minggu`).

- [ ] **Step 1: Tulis tes skema (gagal dulu)**

`app/Scripts/marketing_weekly_schema_test.php` — pola bootstrap sama dengan `app/Scripts/meta_ads_command_test.php` baris 80–97, lalu:

```php
$db = \Config\Database::connect();

ok('kolom unit_sumber ada', $db->fieldExists('unit_sumber', 'marketing_campaigns'));
ok('tabel laporan mingguan ada', $db->tableExists('marketing_laporan_mingguan'));

$idx = $db->query(
    "SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marketing_laporan_mingguan' AND Non_unique = 0
     GROUP BY INDEX_NAME"
)->getResultArray();
$uniq = array_filter($idx, fn($r) => $r['cols'] === 'period_year,period_month,minggu');
ok('unik per (tahun, bulan, minggu) ada', count($uniq) === 1, json_encode($idx));

$adopsi = [
    '120251146211920189' => 'Prob 76',
    '120251147591300189' => 'Jember 64',
    '120249387385360189' => 'Banyuwangi 60',
    '120251197138500189' => 'Pandaan 09',
    '120251224385770189' => 'Genteng 04',
];
foreach ($adopsi as $metaId => $label) {
    $n = (int)$db->query(
        "SELECT COUNT(*) c FROM marketing_campaigns
         WHERE meta_campaign_id = ? AND period_month = 10 AND period_year = 2026",
        [$metaId]
    )->getRow()->c;
    ok("adopsi Okt: {$label} → {$metaId}", $n === 1, "count={$n}");
}
ok('September tidak tersentuh', (int)$db->query(
    "SELECT COUNT(*) c FROM marketing_campaigns WHERE period_year = 2026 AND period_month = 9 AND meta_campaign_id IS NOT NULL"
)->getRow()->c === 0);
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php74 app/Scripts/marketing_weekly_schema_test.php`
Expected: FAIL (`kolom unit_sumber ada` dan seterusnya).

- [ ] **Step 3: Tulis migrasi**

`up()`: (a) tambah `unit_sumber` setelah `unit_id`; (b) buat tabel `marketing_laporan_mingguan` dengan `id INT UNSIGNED AUTO_INCREMENT`, `period_year SMALLINT`, `period_month TINYINT`, `minggu TINYINT`, `submitted_by INT NULL`, `submitted_at DATETIME NULL`, PRIMARY KEY `id`, UNIQUE KEY `uniq_laporan_minggu` (`period_year`,`period_month`,`minggu`); (c) adopsi:

```php
$maps = [
    'iClear Prob 76'       => '120251146211920189',
    'iClear Jember 64'     => '120251147591300189',
    'iClear Banyuwangi 60' => '120249387385360189',
    'iClear Pandaan 09'    => '120251197138500189',
    'iClear Genteng 04'    => '120251224385770189',
];
foreach ($maps as $nama => $metaId) {
    $this->db->table('marketing_campaigns')
        ->where('period_month', 10)->where('period_year', 2026)
        ->where('meta_campaign_id', null)
        ->like('nama', $nama)
        ->update(['meta_campaign_id' => $metaId, 'unit_sumber' => 'auto']);
}
```

`down()`: hapus tabel + kolom (data pemetaan dibiarkan; pola migrasi sebelumnya non-destruktif).

- [ ] **Step 4: Jalankan migrasi + tes**

Run: `php74 spark migrate && php74 app/Scripts/marketing_weekly_schema_test.php`
Expected: semua PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Database/Migrations/2026-10-18-000100_AddUnitSumberAndLaporanMingguan.php app/Scripts/marketing_weekly_schema_test.php
git commit -m "feat(marketing): skema unit_sumber + laporan mingguan, adopsi campaign Okt"
```

---

### Task 2: Penebak unit dari nama

**Files:**
- Create: `app/Services/Marketing/UnitDetector.php`
- Test: `app/Scripts/unit_detector_test.php`

**Interfaces:**
- Produces: `UnitDetector::UNIT_KEYWORDS` (array `string => int`) dan `UnitDetector::detect(string $nama): int` — selalu mengembalikan 1–5, default 1.

- [ ] **Step 1: Tulis tes**

```php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\Marketing\UnitDetector;

ok('Prob → 1', UnitDetector::detect('iClear Prob 76 - CTWA') === 1);
ok('Probolinggo → 1', UnitDetector::detect('ICLEAR PROBOLINGGO 01') === 1);
ok('Jember → 2', UnitDetector::detect('iClear Jember 64 - CTWA') === 2);
ok('Banyuwangi → 3', UnitDetector::detect('iclear banyuwangi 60') === 3);
ok('Bwi → 3', UnitDetector::detect('iClear Bwi 60') === 3);
ok('Pandaan → 4', UnitDetector::detect('iClear Pandaan 09') === 4);
ok('Genteng → 5', UnitDetector::detect('iClear Genteng 04') === 5);
ok('tanpa cabang → Probolinggo', UnitDetector::detect('Loker Talent iClear') === 1);
ok('Raja Casing → Probolinggo', UnitDetector::detect('Raja Casing') === 1);
ok('nama kosong → Probolinggo', UnitDetector::detect('') === 1);
ok('Banyuwangi menang atas Prob', UnitDetector::detect('Loker Talent iClear Banyuwangi') === 3);
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php74 app/Scripts/unit_detector_test.php` → FAIL (class tidak ada).

- [ ] **Step 3: Implementasikan**

```php
namespace App\Services\Marketing;

class UnitDetector
{
    /** Kata kunci nama campaign → idunit. Satu tempat untuk mengubah aturan. */
    public const UNIT_KEYWORDS = [
        'probolinggo' => 1, 'prob' => 1,
        'jember'      => 2,
        'banyuwangi'  => 3, 'bwi' => 3,
        'pandaan'     => 4,
        'genteng'     => 5,
    ];

    public const DEFAULT_UNIT = 1;

    public static function detect(string $nama): int
    {
        $n = strtolower($nama);
        foreach (self::UNIT_KEYWORDS as $kata => $unit) {
            if (strpos($n, $kata) !== false) {
                return $unit;
            }
        }
        return self::DEFAULT_UNIT;
    }
}
```

Catatan: urutan array menentukan pemenang bila dua kata cocok. `banyuwangi` diperiksa sebelum `prob`, dan `bwi` sebelum `prob` juga? Tidak — `bwi` tidak mengandung `prob`, jadi aman. Jangan menaruh `prob` sebelum `probolinggo`; keduanya ke unit 1 sehingga tidak berpengaruh.

- [ ] **Step 4: Jalankan tes** → semua PASS.

- [ ] **Step 5: Commit**

---

### Task 3: Sync service + command + hook ke `meta:pull`

**Files:**
- Create: `app/Services/Meta/MetaCampaignSyncService.php`
- Create: `app/Commands/MetaSyncCampaigns.php`
- Modify: `app/Commands/MetaPull.php:35-64`
- Test: `app/Scripts/meta_campaign_sync_test.php`

**Interfaces:**
- `MetaCampaignSyncService::__construct(?MetaAdsClient $client = null)`
- `plan(array $opsi, array $existing): array` — murni, tanpa DB/jaringan. `$existing` = `[meta_id => ['id'=>int, 'unit_id'=>?int, 'unit_sumber'=>'auto'|'manual', 'status'=>string]]`. Hasil: `['upsert' => [['meta_id','id','nama','unit_id','status'], ...], 'demote' => [int, ...]]`.
- `sync(int $month, int $year): array` — `['dibuat'=>int,'diperbarui'=>int,'diturunkan'=>int]`; melempar `RuntimeException` bila periode < 2026-10.
- `MetaSyncCampaigns` name `meta:sync-campaigns`, usage `meta:sync-campaigns [bulan] [tahun]`.

- [ ] **Step 1: Tulis tes `plan()` + guard**

```php
use App\Services\Meta\MetaCampaignSyncService;

$svc = new MetaCampaignSyncService();

$opsi = [
    ['id' => '111', 'nama' => 'iClear Jember 64', 'spend' => 100.0, 'aktif' => true],
    ['id' => '222', 'nama' => 'Loker Talent iClear', 'spend' => 50.0, 'aktif' => false],
];
$existing = [
    '111' => ['id' => 7, 'unit_id' => 4, 'unit_sumber' => 'manual', 'status' => 'active'],
    '999' => ['id' => 8, 'unit_id' => 1, 'unit_sumber' => 'auto', 'status' => 'active'],
];
$p = $svc->plan($opsi, $existing);

ok('campaign lama → update, bukan create', $p['upsert'][0]['id'] === 7);
ok('unit manual tidak ditimpa', $p['upsert'][0]['unit_id'] === 4);
ok('campaign baru → id null', $p['upsert'][1]['id'] === null);
ok('unit baru ditebak dari nama', $p['upsert'][1]['unit_id'] === 1); // Loker → default
ok('status dari Meta (aktif)', $p['upsert'][0]['status'] === 'active');
ok('status dari Meta (tidak aktif)', $p['upsert'][1]['status'] === 'draft');
ok('campaign tak dilaporkan diturunkan', $p['demote'] === [8]);

$manual = $svc->plan(
    [['id' => '111', 'nama' => 'iClear Jember 64', 'spend' => 1, 'aktif' => false]],
    ['111' => ['id' => 7, 'unit_id' => 4, 'unit_sumber' => 'manual', 'status' => 'active']]
);
ok('unit manual tetap walau status turun', $manual['upsert'][0]['unit_id'] === 4);
ok('status jadi draft', $manual['upsert'][0]['status'] === 'draft');

$threw = false;
try { $svc->sync(9, 2026); } catch (\RuntimeException $e) { $threw = true; }
ok('periode sebelum 2026-10 ditolak', $threw);
```

- [ ] **Step 2: Jalankan** → FAIL.

- [ ] **Step 3: Implementasikan service**

`plan()`:
- untuk tiap `$opsi`: `unit = existing.unit_sumber === 'manual' && existing.unit_id ? existing.unit_id : UnitDetector::detect(nama)`; `status = aktif ? 'active' : 'draft'`; `id = existing.id ?? null`; masuk `upsert`.
- setelah itu: untuk tiap `$existing` yang `status === 'active'` dan `meta_id` tidak ada di `$opsi` → masuk `demote` (id-nya).

`sync()`:
- `if ($year * 100 + $month < 202610) throw new \RuntimeException('Sync hanya untuk periode 2026-10 ke atas.');`
- `$dari = sprintf('%04d-%02d-01', $year, $month); $sampai = date('Y-m-t', strtotime($dari));`
- `$opsi = $this->client->fetchCampaignOptions($dari, $sampai);`
- muat existing: `WHERE meta_campaign_id IS NOT NULL`
- `$p = $this->plan($opsi, $existing);`
- tulis: update by id / insert (`nama`, `meta_campaign_id`, `period_month`, `period_year`, `status`, `unit_id`, `unit_sumber` = 'auto', `pic` tidak diubah); demote `status='draft'`.
- hitung dan kembalikan jumlah.

Catatan insert: kolom `created_by`/`pic` boleh NULL (sudah nullable).

Command `MetaSyncCampaigns`: argumen `bulan`/`tahun` (default bulan/tahun berjalan), panggil `sync`, cetak hasil, `EXIT_ERROR` bila RuntimeException.

Hook `MetaPull::run()`: sebelum `pull`, bila `!$dryRun`, panggil sync untuk bulan dari `$sampai`; kegagalan sync **tidak** menggagalkan pull (tulis `CLI::write(..., 'yellow')`).

- [ ] **Step 4: Jalankan tes** → PASS. Lalu `php74 spark list | grep meta:sync` → terdaftar.

- [ ] **Step 5: Commit**

---

### Task 4: Halaman Campaign — daftar dari Meta, sunting unit saja

**Files:**
- Modify: `app/Controllers/Marketing.php` (`campaign()`, `campaign_simpan()`, hapus `campaign_meta_options()`)
- Modify: `app/Models/ModelMarketingCampaign.php` (`options()`)
- Modify: `app/Views/marketing/kampanye.php`
- Modify: `app/Config/Routes.php` (hapus `campaign/meta_options`)
- Modify: `app/Scripts/marketing_smoke.php` (ganti assertion pemilih)

**Interfaces:**
- `campaign()` mengirim `rows` = SEMUA campaign (`ORDER BY nama`), `unitList`, `canWrite`, `statusLabels`.
- `campaign_simpan()` hanya membaca `id` + `unit_id`; menulis `unit_id` dan `unit_sumber='manual'`; **tidak** menyentuh `nama`/`status`/`meta_campaign_id`/periode; lalu `UPDATE marketing_ads_performance SET unit_id=? WHERE campaign_id=?`.
- `ModelMarketingCampaign::options(int $month, int $year): array` — campaign yang punya baris iklan di periode itu, **atau** `period_month`/`period_year` = periode itu, **atau** `status = 'active'`; urut `nama`. Dipakai juga oleh halaman Laporan.

- [ ] **Step 1: Ubah tes smoke lebih dulu**

Di `app/Scripts/marketing_smoke.php`: hapus assertion `meta_options`/pemilih; tambah:
- halaman `marketing/campaign` memuat `name="unit_id"` (form koreksi unit),
- halaman **tidak** memuat `cp_meta_pick`,
- POST `marketing/campaign/simpan` dengan `id` + `unit_id` mengubah `unit_id` dan `unit_sumber='manual'`,
- baris iklan campaign itu ikut berubah `unit_id`.

Run: `php74 app/Scripts/marketing_smoke.php` → FAIL.

- [ ] **Step 2: Ubah controller**

`campaign()`: buang `bulan`/`tahun` dari query; `rows` = semua; hapus filter validPeriod. Tetap kirim `bulan`/`tahun` (view masih memakainya untuk judul? — hapus pemakaiannya di view).

`campaign_simpan()`: validasi `id > 0` dan campaign ada; `unit_id` valid atau 0→NULL; update; cascade ke baris iklan; redirect `marketing/campaign`.

`campaign_meta_options()`: hapus method + route.

`ModelMarketingCampaign::options()`: ganti saringan periode dengan
`(period_month=? AND period_year=?) OR status='active' OR id IN (SELECT DISTINCT campaign_id FROM marketing_ads_performance WHERE period_month=? AND period_year=? AND campaign_id IS NOT NULL)`.

- [ ] **Step 3: Ubah view**

- Hapus kartu filter bulan/tahun dan tombol "Input Campaign".
- Hapus modal + seluruh `<script>` pemilih Meta.
- Tabel: kolom `Nama Campaign`, `Status`, `Unit`, `Campaign Meta`, `Aksi`.
- Kolom Unit = `<form method="post" action="marketing/campaign/simpan">` berisi `id`, `<select name="unit_id">` (opsi dari `$unitList`, terpilih `$row->unit_id`), tombol simpan kecil.
- Kolom Aksi = tombol hapus (tetap).
- Header: ganti deskripsi jadi "Campaign ditarik dari Meta. Yang bisa diubah hanya unit pembebanan biaya."

- [ ] **Step 4: Jalankan smoke** → PASS (68+ assertion).

- [ ] **Step 5: Commit**

---

### Task 5: Laporan mingguan — rentang, deadline, kirim

**Files:**
- Create: `app/Services/Marketing/WeeklyReport.php`
- Create: `app/Models/ModelMarketingLaporanMingguan.php`
- Modify: `app/Controllers/Marketing.php` (`laporan()`, tambah `laporan_minggu_simpan()`)
- Modify: `app/Config/Routes.php` (`POST marketing/laporan/minggu`)
- Modify: `app/Views/marketing/laporan.php`
- Test: `app/Scripts/marketing_weekly_report_test.php`

**Interfaces:**
- `WeeklyReport::range(int $month, int $year, int $minggu): array` → `['mulai'=>'Y-m-d','selesai'=>'Y-m-d']`; minggu 1: 1–7, 2: 8–14, 3: 15–21, 4: 22–akhir.
- `WeeklyReport::deadline(int $month, int $year, int $minggu): string` → `selesai + 2 hari`.
- `WeeklyReport::score(int $month, int $year, array $terkirim, string $today): ?float` — `$terkirim` = `[minggu => 'Y-m-d H:i:s']`; penyebut = minggu yang `today > deadline`; pembilang = `substr(submitted_at,0,10) <= deadline`.
- `WeeklyReport::sudahBerakhir(...)`: `today > selesai`.

- [ ] **Step 1: Tulis tes**

```php
use App\Services\Marketing\WeeklyReport;

ok('minggu 1 = 1–7', WeeklyReport::range(11, 2026, 1) === ['mulai' => '2026-11-01', 'selesai' => '2026-11-07']);
ok('minggu 4 = 22–30', WeeklyReport::range(11, 2026, 4) === ['mulai' => '2026-11-22', 'selesai' => '2026-11-30']);
ok('Feb 2026 minggu 4 = 22–28', WeeklyReport::range(2, 2026, 4)['selesai'] === '2026-02-28');
ok('deadline minggu 1 = tgl 9', WeeklyReport::deadline(11, 2026, 1) === '2026-11-09');
ok('deadline minggu 4 = tgl 2 bulan berikut', WeeklyReport::deadline(11, 2026, 4) === '2026-12-02');

ok('masa tenggang: tgl 8 belum dihitung', WeeklyReport::score(11, 2026, [], '2026-11-08') === null);
ok('telat: skor 0 dari 1', WeeklyReport::score(11, 2026, [], '2026-11-10') == 0.0);
ok('tepat: skor 100', WeeklyReport::score(11, 2026, [1 => '2026-11-08 09:00:00'], '2026-11-10') == 100.0);
ok('telat kirim tetap 0', WeeklyReport::score(11, 2026, [1 => '2026-11-10 09:00:00'], '2026-11-10') == 0.0);
ok('2 dari 4 = 50', WeeklyReport::score(11, 2026, [1 => '2026-11-08 09:00:00', 2 => '2026-11-16 09:00:00'], '2026-12-03') == 50.0);
ok('bulan mendatang null', WeeklyReport::score(12, 2026, [], '2026-11-10') === null);
```

- [ ] **Step 2: Jalankan** → FAIL.

- [ ] **Step 3: Implementasikan `WeeklyReport` + model**

Model: `$table='marketing_laporan_mingguan'`, `$allowedFields=['period_year','period_month','minggu','submitted_by','submitted_at']`.

- [ ] **Step 4: Jalankan tes** → PASS.

- [ ] **Step 5: Sambungkan controller + view**

`laporan()`: baca `minggu` (0–4, default 0 = sebulan). Bila `>0`, ambil `range()`, tambahkan `AND a.tanggal BETWEEN ? AND ?` ke `$where`/`$perfParams` (dan `p.tanggal` untuk `$perfWhere`). Daftar campaign (`$allCampaigns`) diambil dari `(new ModelMarketingCampaign())->options($bulan, $tahun)` — bukan query periode sendiri. Kirim ke view: `minggu`, `mingguList` (1–4 + label tanggal), `laporanMinggu` (baris tersimpan untuk bulan itu, key = minggu), `bisaLapor` (`canWrite`).

`laporan_minggu_simpan()` (POST): `writeOrRedirect()`, validasi periode + `minggu` 1–4, tolak bila `WeeklyReport::sudahBerakhir()` false, tolak bila sudah ada baris (UNIQUE), insert `submitted_by`/`submitted_at`.

View: tambah `<select name="minggu">` di form filter (0 = Sebulan, 1–4 dengan rentang tanggal), dan di kanan filter sebuah tombol "Laporkan minggu ini" (POST) **hanya bila** `minggu > 0`, `canWrite`, minggu sudah berakhir, dan belum terkirim; bila sudah, tampilkan badge "Dilaporkan <tgl> oleh <nama>". Kirim `nama` pengirim: join `akun` di controller (`ID_AKUN` → `NAMA`).

- [ ] **Step 6: Smoke** — tambahkan assertion halaman laporan memuat `name="minggu"`; kirim minggu berjalan (belum berakhir) ditolak.

- [ ] **Step 7: Commit**

---

### Task 6: Skor KPI REPORTING mingguan

**Files:**
- Modify: `app/Services/Marketing/DigitalMarketingKpiService.php` (`reportingCoverage` tetap, tambah `reportingWeekly`, ubah cabang `REPORTING`)
- Test: `app/Scripts/marketing_weekly_report_test.php` (tambahan) atau `app/Scripts/digital_marketing_kpi_weekly_test.php`

**Interfaces:**
- `reportingWeekly(int $month, int $year): array{terkirim: array<int,string>, skor: ?float, total_deadline: int, tepat: int}`
- Cabang `REPORTING`: periode `>= 202611` → `reportingWeekly`; selain itu perilaku lama.

- [ ] **Step 1: Tulis tes**

Skor murni (`WeeklyReport::score`) sudah diuji di Task 5. Tambahan di sini: cabang periode.

```php
$svc = new \App\Services\Marketing\DigitalMarketingKpiService();
$nov = $svc->componentData('REPORTING', 55, 1, 11, 2026, 'default');
$okt = $svc->componentData('REPORTING', 55, 1, 10, 2026, 'default');
ok('Nov 2026 pakai sumber laporan mingguan', $nov['source'] === 'Laporan mingguan tepat waktu');
ok('Okt 2026 tetap rumus lama', $okt['source'] === 'Campaign selesai / total campaign');
```

(Catatan: nilai `achievement` bergantung data; jangan di-assert angkanya.)

- [ ] **Step 2: Jalankan** → FAIL.

- [ ] **Step 3: Implementasikan**

```php
if ((int)$year * 100 + (int)$month >= 202611) {
    $w = $this->reportingWeekly($month, $year);
    return [
        'achievement' => $w['skor'],
        'target'      => 100,
        'actual'      => $w['skor'],
        'shortfall'   => $w['skor'] === null ? null : round(max(100 - $w['skor'], 0), 2),
        'source'      => 'Laporan mingguan tepat waktu',
    ];
}
```

`reportingWeekly()`: ambil semua baris tabel untuk periode, bentuk `[minggu => submitted_at]`, panggil `WeeklyReport::score($month, $year, $terkirim, date('Y-m-d'))`.

- [ ] **Step 4: Jalankan tes** → PASS.

- [ ] **Step 5: Regresi penuh**

Run: `php74 app/Scripts/marketing_smoke.php`, `php74 app/Scripts/meta_ads_options_test.php`, `php74 app/Scripts/unit_detector_test.php`, `php74 app/Scripts/marketing_weekly_report_test.php`, `php74 app/Scripts/marketing_weekly_schema_test.php`, `php74 app/Scripts/meta_campaign_sync_test.php`
Expected: semua PASS. PHPUnit tetap di baseline (47 error / 173 failure).

- [ ] **Step 6: Commit**

---

## Review Focus

- Mengirim laporan untuk minggu yang belum berakhir → ditolak server (bukan hanya tombol disembunyikan).
- Mengirim dua kali → baris kedua tidak tercipta (UNIQUE + cek eksplisit).
- Sync bulan lalu (September) → ditolak, tidak membuat baris baru.
- Unit yang dikoreksi manual → tidak tertimpa sync berikutnya, dan baris iklan lama ikut berubah.
- Baris manual 1–12 Okt → berubah jadi `sumber='meta'` setelah pull, jumlah baris tetap 60.
