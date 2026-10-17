# Performa Ads Otomatis dari Meta — Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengisi otomatis tabel `marketing_ads_performance` dari Meta Marketing API, menggantikan input manual harian kepala divisi.

**Architecture:** Command `meta:pull` menarik insight level-campaign (satu panggilan per rentang tanggal) lewat `MetaAdsClient`, memetakan kampanye Meta → campaign ERP lewat `marketing_campaigns.meta_campaign_id`, lalu upsert ke `marketing_ads_performance`. Pola mengikuti integrasi SocialMedia yang sudah ada (`app/Services/SocialMedia/`, `app/Commands/SocialMediaScrape.php`).

**Tech Stack:** PHP 7.4 (`php74`), CodeIgniter 4, Guzzle (sudah terpasang), MySQL.

**Spec:** `docs/architecture/meta-ads-performa-otomatis.md`

## Global Constraints

- PHP 7.4 — tanpa sintaks PHP 8 (tanpa named args, tanpa `match`, tanpa `enum`, tanpa constructor promotion).
- Token Meta **hanya** dari `env('META_ADS_TOKEN')`. Tidak pernah ditulis ke DB, log, atau halaman web. Pesan exception tidak boleh memuat token.
- Integrasi ke Meta **baca saja**. Tidak ada POST/PUT/DELETE ke Graph API.
- Migrasi non-destruktif: `down()` tidak menghapus tabel/kolom/data (pola repo).
- Nama tabel & kolom snake_case, tanpa prefix; komentar dan pesan UI dalam Bahasa Indonesia.
- Jangan tambah dependency baru. Guzzle sudah ada; pakai `GuzzleHttp\Client` yang disuntikkan agar bisa diuji.
- `META_PPN_PERSEN` (default `5`) dibaca dari `.env`, tidak ditanam di kode.
- Rentang tanggal default `meta:pull` = **7 hari terakhir** (angka `spend` Meta masih menyesuaikan).

## Review Focus

Kemungkinan yang paling mudah bikin rusak, dan sudah dipasangi uji di task pemilik kodenya:

1. **Tarikan ulang hari yang sama** — `spend` Meta berubah antar-tarikan; harus UPDATE baris yang ada, bukan menambah baris kedua. (Task 3)
2. **Kampanye Meta belum dipetakan** — harus dilewati dan dilaporkan, bukan menulis baris dengan `campaign_id` kosong atau menggagalkan seluruh tarikan. (Task 3)
3. **Kombinasi objective belum berlabel** — label jatuh ke kode mentah; kolom `objective` tidak boleh kosong dan tarikan tidak boleh gagal. (Task 3)
4. **Token kosong / Meta menolak (401)** — tidak ada baris yang ditulis, pesan jelas, token tidak bocor ke pesan. (Task 2)
5. **Kampanye pause di tengah periode** — tidak ada baris insight untuk tanggal itu; jangan menulis baris nol palsu dan jangan menghapus baris lama. (Task 3)

---

### Task 1: Skema basis data & model

**Files:**
- Create: `app/Database/Migrations/2026-10-17-000100_AddMetaMappingToMarketingCampaigns.php`
- Create: `app/Database/Migrations/2026-10-17-000200_CreateMetaAdsTables.php`
- Create: `app/Models/ModelMetaLabelObjektif.php`
- Create: `app/Models/ModelMetaPullLog.php`
- Modify: `app/Models/ModelMarketingCampaign.php:22-32`
- Modify: `app/Models/ModelMarketingAdsPerf.php:23-42`
- Create: `app/Scripts/meta_ads_schema_test.php`

**Interfaces:**
- Consumes: —
- Produces:
  - Kolom `marketing_campaigns.meta_campaign_id` (VARCHAR 32, NULL, UNIQUE), `marketing_campaigns.unit_id` (INT, NULL)
  - Kolom `marketing_ads_performance.sumber` (ENUM('manual','meta'), default 'manual'), `ditarik_pada` (DATETIME, NULL)
  - Tabel `meta_label_objektif` (`objective`, `optimization_goal`, `destination_type`, `label`; UNIQUE ketiga kolom kunci)
  - Tabel `meta_pull_log` (`tanggal_mulai`, `tanggal_sampai`, `jumlah_kampanye`, `jumlah_baris`, `baru`, `diperbarui`, `dilewati`, `call_count`, `total_time`, `total_cputime`, `app_id_util_pct`, `acc_id_util_pct`, `estimated_time_to_regain_access`, `tier`, `status`, `pesan`, `created_at`)
  - `App\Models\ModelMetaLabelObjektif::labelFor(?string $objective, ?string $goal, ?string $dest): string`
  - `App\Models\ModelMetaPullLog` (model biasa, `$allowedFields` = kolom di atas kecuali `id`/`created_at`)
  - `App\Models\ModelMarketingCampaign::metaMap(): array` → `meta_campaign_id => ['campaign_id' => int, 'unit_id' => int|null]`

- [x] **Step 1: Tulis migrasi 1 — kolom pemetaan**

`app/Database/Migrations/2026-10-17-000100_AddMetaMappingToMarketingCampaigns.php`. Ikuti pola `AddUnitAmountToMarketingAdsPerformance`: bungkus tiap `addColumn` dengan `$db->fieldExists(...)`.

- `meta_campaign_id`: VARCHAR 32, null, after `pic`
- `unit_id`: INT 11, null, after `meta_campaign_id`
- `addKey('meta_campaign_id', false, true)` untuk UNIQUE

- [x] **Step 2: Tulis migrasi 2 — kolom sumber & dua tabel baru**

`app/Database/Migrations/2026-10-17-000200_CreateMetaAdsTables.php`:

- `marketing_ads_performance`: `sumber` ENUM('manual','meta') NOT NULL DEFAULT 'manual'; `ditarik_pada` DATETIME NULL
- `meta_label_objektif`: `id` AI, `objective` VARCHAR 64, `optimization_goal` VARCHAR 64, `destination_type` VARCHAR 64, `label` VARCHAR 150, `created_at`/`updated_at` DATETIME NULL; UNIQUE(`objective`,`optimization_goal`,`destination_type`)
- `meta_pull_log`: sesuai daftar di Interfaces; `status` ENUM('ok','gagal') DEFAULT 'ok'

- [x] **Step 3: Tulis model baru**

`ModelMetaLabelObjektif` — `labelFor()` mengembalikan `label` yang cocok bila ada; bila tidak ada kembalikan `"{objective} / {goal} / {dest}"` (bagian null jadi `UNDEFINED`) sehingga tidak pernah kosong.

`ModelMetaPullLog` — model biasa, `useTimestamps = true`, `createdField = 'created_at'`, `updatedField = 'updated_at'`.

`ModelMarketingCampaign::metaMap(): array` — `WHERE meta_campaign_id IS NOT NULL`, kembalikan `meta_campaign_id => ['campaign_id' => (int)$id, 'unit_id' => $unitId ?: null]`.

- [x] **Step 4: Daftarkan kolom baru di `$allowedFields`**

- `ModelMarketingCampaign`: tambah `'meta_campaign_id'`, `'unit_id'`
- `ModelMarketingAdsPerf`: tambah `'sumber'`, `'ditarik_pada'`

- [x] **Step 5: Tulis uji skema (gagal dulu)**

`app/Scripts/meta_ads_schema_test.php` dengan header bootstrap yang sama seperti `app/Scripts/marketing_test.php:1-27`.

Assert dengan helper `ok(...)` yang sudah dipakai repo:

```php
ok('kolom marketing_campaigns.meta_campaign_id ada', $db->fieldExists('meta_campaign_id', 'marketing_campaigns'));
ok('kolom marketing_campaigns.unit_id ada', $db->fieldExists('unit_id', 'marketing_campaigns'));
ok('kolom marketing_ads_performance.sumber ada', $db->fieldExists('sumber', 'marketing_ads_performance'));
ok('kolom marketing_ads_performance.ditarik_pada ada', $db->fieldExists('ditarik_pada', 'marketing_ads_performance'));
ok('tabel meta_label_objektif ada', $db->tableExists('meta_label_objektif'));
ok('tabel meta_pull_log ada', $db->tableExists('meta_pull_log'));
$L = new \App\Models\ModelMetaLabelObjektif();
ok('labelFor fallback tidak kosong', $L->labelFor('OUTCOME_SALES', 'CONVERSATIONS', 'WHATSAPP') === 'OUTCOME_SALES / CONVERSATIONS / WHATSAPP');
```

- [x] **Step 6: Jalankan uji, pastikan gagal**

Run: `php74 app/Scripts/meta_ads_schema_test.php`
Expected: FAIL pada assertion pertama (kolom belum ada).

- [x] **Step 7: Jalankan migrasi**

Run: `php74 spark migrate`
Expected: kedua migrasi terdaftar dan sukses.

- [x] **Step 8: Jalankan uji, pastikan lulus**

Run: `php74 app/Scripts/meta_ads_schema_test.php`
Expected: semua assertion lulus, exit code 0.

- [x] **Step 9: Commit**

```bash
git add app/Database/Migrations app/Models app/Scripts/meta_ads_schema_test.php
git commit -m "feat(marketing): skema pemetaan kampanye Meta & log tarikan"
```

---

### Task 2: Klien Meta Ads

**Files:**
- Create: `app/Services/Meta/MetaAdsClient.php`
- Create: `app/Services/Meta/MetaAdsException.php`
- Create: `app/Scripts/meta_ads_client_test.php`

**Interfaces:**
- Consumes: `env('META_ADS_TOKEN')`, `env('META_AD_ACCOUNT_ID')`
- Produces:
  - `MetaAdsClient::__construct(?\GuzzleHttp\Client $http = null)`
  - `MetaAdsClient::fetchCampaignInsights(string $since, string $until): array` → `['rows' => array<int, array{campaign_id:string, campaign_name:string, date:string, spend:float, reach:int, frequency:float, impressions:int, link_clicks:int, conversations:int}>, 'rate' => array<string, mixed>]`
  - `MetaAdsClient::fetchCampaigns(): array` → `campaign_id => array{daily_budget: float|null, objective: string|null, optimization_goal: string|null, destination_type: string|null}` (sumber label Objektif)
  - `MetaAdsException extends \RuntimeException`

- [x] **Step 1: Tulis uji klien (gagal dulu)**

`app/Scripts/meta_ads_client_test.php`, memakai `GuzzleHttp\Handler\MockHandler` + `GuzzleHttp\HandlerStack` yang disuntikkan ke constructor. Siapkan satu respons sukses JSON (`data` berisi dua baris insight, satu dengan `actions` berisi `link_click` dan `onsite_conversion.messaging_conversation_started_7d`) dengan header respons `x-business-use-case-usage` dan `x-fb-ads-insights-throttle`.

Assert:

```php
ok('spend terparsing', $res['rows'][0]['spend'] === 70836.0);
ok('link_clicks diambil dari actions link_click', $res['rows'][0]['link_clicks'] === 67);
ok('conversations diambil dari actions messaging_conversation_started_7d', $res['rows'][0]['conversations'] === 12);
ok('rate limit terbaca', ($res['rate']['acc_id_util_pct'] ?? null) === 0);
ok('tier terbaca', ($res['rate']['tier'] ?? null) === 'development_access');
ok('tanggal terparsing', $res['rows'][0]['date'] === '2026-10-01');
```

Uji token: dengan `META_ADS_TOKEN` kosong, `fetchCampaignInsights` melempar `MetaAdsException` dan **pesan tidak memuat nilai token**. Uji HTTP 401: MockHandler mengembalikan 401 dengan body error Graph → `MetaAdsException` dilempar, pesan memuat `error.code`/`error.message` dari Meta.

- [x] **Step 2: Jalankan uji, pastikan gagal**

Run: `php74 app/Scripts/meta_ads_client_test.php`
Expected: FAIL karena `App\Services\Meta\MetaAdsClient` belum ada.

- [x] **Step 3: Implementasi `MetaAdsClient`**

Konstanta `ENDPOINT = 'https://graph.facebook.com/v25.0/%s/insights'`. Konfigurasi Guzzle sama seperti `BrightDataClient` (`timeout` 30, `connect_timeout` 10) dan pola suntikan client yang sama.

`fetchCampaignInsights()`:
- bangun query: `level=campaign`, `time_increment=1`, `time_range={"since":...,"until":...}`, `fields=campaign_id,campaign_name,spend,reach,frequency,impressions,actions`, `limit=500`, `access_token`;
- ikuti `paging.next` sampai habis;
- normalisasi tiap baris ke bentuk di Interfaces (agregasi `actions` menjadi `link_clicks`/`conversations`);
- baca header `x-business-use-case-usage` dan `x-fb-ads-insights-throttle` → `rate`.

`fetchCampaigns()`: `GET /{act}/campaigns?fields=id,daily_budget,objective&limit=500`, lalu `GET /{act}/adsets?fields=campaign_id,optimization_goal,destination_type&limit=500`; ikuti paging dan gabungkan jadi satu peta per `campaign_id`.

Token dibaca lewat helper privat `token()`; bila kosong lempar `MetaAdsException('META_ADS_TOKEN belum dikonfigurasi di .env.')`.

- [x] **Step 4: Jalankan uji, pastikan lulus**

Run: `php74 app/Scripts/meta_ads_client_test.php`
Expected: semua assertion lulus, exit code 0.

- [x] **Step 5: Commit**

```bash
git add app/Services/Meta app/Scripts/meta_ads_client_test.php
git commit -m "feat(meta): klien Graph API insights (baca saja, token dari env)"
```

---

### Task 3: Layanan tarik & upsert

**Files:**
- Create: `app/Services/Meta/MetaAdsPullService.php`
- Create: `app/Scripts/meta_ads_pull_test.php`

**Interfaces:**
- Consumes: `MetaAdsClient::fetchCampaignInsights()`, `MetaAdsClient::fetchCampaigns()`, `ModelMarketingCampaign::metaMap()`, `ModelMetaLabelObjektif::labelFor()`, `ModelMarketingAdsPerf`
- Produces:
  - `MetaAdsPullService::__construct(?MetaAdsClient $client = null)`
  - `MetaAdsPullService::pull(string $since, string $until, bool $dryRun = false): array` → `['baru' => int, 'diperbarui' => int, 'dilewati' => int, 'tanpa_peta' => array<int,string>, 'log_id' => int|null]`

- [x] **Step 1: Tulis uji layanan (gagal dulu)**

`app/Scripts/meta_ads_pull_test.php`, memakai kelas fake yang mengimplementasikan `MetaAdsClient` (constructor-nya sama, tapi `fetchCampaignInsights`/`fetchCampaigns` mengembalikan data tetap). Pakai periode uji dedikasi (`period_year = 2099`) dan tandai nama campaign `[TEST]`, rollback di akhir seperti `marketing_test.php`.

Assert:

```php
// 1. Upsert: tarikan dua kali tidak menggandakan baris.
ok('tarikan dua kali tetap satu baris', $count === 1);
ok('angka terbarui pada tarikan kedua', $amount === 200.0);

// 2. Menimpa baris manual (keputusan: Meta menang).
ok('baris manual ditimpa angka Meta', $sumber === 'meta' && $amount === 150.0);

// 3. Kampanye Meta belum dipetakan.
ok('kampanye tanpa peta dilewati', in_array('999_UNMAPPED', $hasil['tanpa_peta'], true));
ok('tidak ada baris dibuat untuk kampanye tanpa peta', $barisKosong === 0);

// 4. Label objective belum dipetakan.
ok('objective jatuh ke kode mentah, tidak kosong', $objective === 'OUTCOME_SALES / CONVERSATIONS / WHATSAPP');

// 5. Kampanye pause (tidak ada baris insight tanggal itu).
ok('tidak menulis baris nol palsu', $barisNol === 0);
ok('baris lama tidak dihapus', $barisLama === 1);
ok('tanggal di luar insight terhitung dilewati', $hasil['dilewati'] >= 1);
```

- [x] **Step 2: Jalankan uji, pastikan gagal**

Run: `php74 app/Scripts/meta_ads_pull_test.php`
Expected: FAIL karena `App\Services\Meta\MetaAdsPullService` belum ada.

- [x] **Step 3: Implementasi `MetaAdsPullService::pull()`**

Alur:
1. `$map = (new ModelMarketingCampaign())->metaMap();` — kunci `meta_campaign_id`.
2. Ambil insight + peta kampanye (`fetchCampaigns()`) dari klien.
3. Untuk tiap baris insight: cari `campaign_id` di `$map`; bila tidak ada, tambahkan `campaign_id` ke `tanpa_peta`, lanjut.
4. Bangun data baris: `period_month`/`period_year` dari `date('n', strtotime($tanggal))` / `date('Y', ...)`; `amount` = spend; `reach`; `impression`; `klik` = link_clicks; `hasil` = conversations; `ppn` = `(float)(env('META_PPN_PERSEN') ?: 5)`; `daily_budget` dari `fetchCampaigns()`; `objective` = `labelFor($objective, $goal, $dest)` memakai nilai dari `fetchCampaigns()` (fallback kode mentah bila kombinasi belum berlabel); `unit_id` = `$map[...]['unit_id']`; `sumber` = `'meta'`; `ditarik_pada` = `date('Y-m-d H:i:s')`.
5. Cari baris eksis dengan `ModelMarketingAdsPerf::getByUnique($bulan, $tahun, $campaignId, $tanggal, $channelId, $unitId)`. Bila tidak ada dengan kunci lengkap, cari ulang dengan `channel_id` dan `unit_id` diabaikan (baris manual lama sering tidak punya keduanya) — bila ketemu, UPDATE; bila tidak, INSERT.
6. Bila `$dryRun` true: jangan tulis apa pun, jangan tulis log.
7. Tulis satu baris `meta_pull_log` berisi ringkasan + `rate`.

- [x] **Step 4: Jalankan uji, pastikan lulus**

Run: `php74 app/Scripts/meta_ads_pull_test.php`
Expected: semua assertion lulus, exit code 0.

- [x] **Step 5: Commit**

```bash
git add app/Services/Meta/MetaAdsPullService.php app/Scripts/meta_ads_pull_test.php
git commit -m "feat(meta): layanan tarik insight & upsert ke Performa Ads"
```

---

### Task 4: Command `meta:pull`

**Files:**
- Create: `app/Commands/MetaPull.php`

**Interfaces:**
- Consumes: `MetaAdsPullService::pull()`
- Produces: `php spark meta:pull [--dari=YYYY-MM-DD] [--sampai=YYYY-MM-DD] [--dry-run]`

- [x] **Step 1: Implementasi command**

Ikuti `app/Commands/SocialMediaScrape.php`: properti `$group = 'Meta'`, `$name = 'meta:pull'`, `$options` berisi `-dari`, `-sampai`, `-dry-run`. Default `-sampai` = hari ini, `-dari` = hari ini − 6. Validasi format tanggal `YYYY-MM-DD` dan `dari <= sampai`; bila salah cetak error dan kembalikan `EXIT_ERROR`. Cetak ringkasan (`baru`, `diperbarui`, `dilewati`, daftar `tanpa_peta`).

- [x] **Step 2: Uji jalannya command**

Run: `php74 spark meta:pull --dari=2026-10-01 --sampai=2026-10-02 --dry-run`
Expected: berjalan tanpa exception, mencetak ringkasan, **tidak ada baris baru** di `marketing_ads_performance` (verifikasi lewat `SELECT COUNT(*)` sebelum/sesudah).

- [x] **Step 3: Tarikan nyata (7 hari)**

Run: `php74 spark meta:pull`
Expected: ringkasan `baru`/`diperbarui` > 0; `SELECT` baris `sumber='meta'` bertambah; `meta_pull_log` berisi satu baris baru.

- [x] **Step 4: Bandingkan dengan sheet kepala divisi**

Ambil satu tanggal yang sudah ada di sheet, bandingkan `amount`, `reach`, `impression`, `klik`, `hasil`. Beda `amount` wajar (angka Meta menyesuaikan); beda besar = bug pemetaan.

- [x] **Step 5: Commit**

```bash
git add app/Commands/MetaPull.php
git commit -m "feat(meta): command meta:pull untuk tarikan harian"
```

---

### Task 5: Pemetaan kampanye di form Campaign

**Files:**
- Modify: `app/Views/marketing/kampanye.php` (tambah input `meta_campaign_id` + `unit_id`)
- Modify: `app/Controllers/Marketing.php:1285-1400` (`campaign()` kirim daftar unit; `campaign_simpan()` baca & validasi kedua field)

**Interfaces:**
- Consumes: kolom `marketing_campaigns.meta_campaign_id`, `unit_id`
- Produces: pemetaan yang dipakai `ModelMarketingCampaign::metaMap()`

- [x] **Step 1: Tambah input di form**

Dua input di form campaign: `meta_campaign_id` (teks, angka saja) dan `unit_id` (select dari `ModelUnit`). Tampilkan `meta_campaign_id` di daftar baris agar mudah diaudit.

- [x] **Step 2: Simpan & validasi di `campaign_simpan()`**

- `meta_campaign_id` disanitasi jadi digit saja; kosong → `null`.
- Tolak bila `meta_campaign_id` sudah dipakai campaign lain (UNIQUE) dengan pesan error, bukan error DB mentah.
- `unit_id` divalidasi ada di `ModelUnit`, kalau tidak → `null`.

- [x] **Step 3: Uji simpan**

Uji asap mengikuti pola `app/Scripts/marketing_smoke.php`: POST form dengan `meta_campaign_id` duplikat → halaman kembali dengan pesan error; POST valid → tersimpan.

Run: `php74 app/Scripts/marketing_smoke.php` (setelah menambah kasus)
Expected: lulus.

- [x] **Step 4: Commit**

```bash
git add app/Views/marketing/kampanye.php app/Controllers/Marketing.php app/Scripts/marketing_smoke.php
git commit -m "feat(marketing): petakan campaign ERP ke campaign Meta & unit"
```

---

### Task 6: Tombol "Tarik dari Meta" & kolom turunan

**Files:**
- Modify: `app/Config/Routes.php:713-715` (tambah route POST)
- Modify: `app/Controllers/Marketing.php` (`ads_performa()` kirim log terakhir; tambah `ads_performa_tarik()`)
- Modify: `app/Views/marketing/ads_performa.php` (tombol, badge sumber, kolom Frek/CTR/CPC/CPM/CPR, panel info limit)
- Modify: `app/Scripts/marketing_smoke.php`

**Interfaces:**
- Consumes: `MetaAdsPullService::pull()`
- Produces: route `POST marketing/ads_performa/tarik`

- [x] **Step 1: Tambah route**

`$routes->post('ads_performa/tarik', 'Marketing::ads_performa_tarik');` di dalam grup `marketing`.

- [x] **Step 2: Tambah aksi controller**

`ads_performa_tarik()`: `writeOrRedirect()` dulu, wajib POST, ambil `bulan`/`tahun`, hitung rentang tanggal pertama–terakhir bulan itu, panggil `pull()`, redirect balik dengan pesan ringkasan (`baru`, `diperbarui`, `dilewati`).

- [x] **Step 3: Tombol & badge di view**

- Tombol "Tarik dari Meta" di header halaman (hanya bila `canWrite`).
- Badge per baris: `meta` / `manual`, plus `ditarik_pada` sebagai tooltip.

- [x] **Step 4: Kolom turunan**

Tambahkan kolom `Frek`, `CTR`, `CPC`, `CPM`, `CPR` di tabel, dihitung dari kolom yang ada (jangan simpan ke DB), persis seperti rumus di spec §8. Bila pembagi nol, tampilkan `-`.

- [x] **Step 5: Panel info limit**

Tampilkan data `meta_pull_log` terakhir: waktu tarikan, `call_count`, `total_time`, `acc_id_util_pct`, `tier`. Ini yang menjawab "sisa kuota berapa" tanpa buka App Dashboard.

- [x] **Step 6: Uji render**

Tambah kasus di `app/Scripts/marketing_smoke.php`: halaman render memuat teks "Tarik dari Meta" dan header kolom "CPR Platform"; baris dengan `impression = 0` menampilkan `-` pada kolom turunan, bukan error.

Run: `php74 app/Scripts/marketing_smoke.php`
Expected: lulus.

- [x] **Step 7: Commit**

```bash
git add app/Config/Routes.php app/Controllers/Marketing.php app/Views/marketing/ads_performa.php app/Scripts/marketing_smoke.php
git commit -m "feat(marketing): tombol tarik Meta, badge sumber, kolom turunan & info limit"
```

---

## Setelah semua task

- Jalankan seluruh uji: `php74 app/Scripts/meta_ads_schema_test.php && php74 app/Scripts/meta_ads_client_test.php && php74 app/Scripts/meta_ads_pull_test.php && php74 app/Scripts/marketing_smoke.php && php74 app/Scripts/marketing_test.php`
- Rotasi `META_ADS_TOKEN` (token pernah tersalin ke chat).
- Isi tabel `meta_label_objektif` dengan 15 label (spec §7).
