# Analisis Kelompok Modul — Marketing Digital / Konten (Content Management) / Social Media

> Dokumen analisis **hanya-baca** (read-only) dari kode sumber ERP CodeIgniter 4.
> Tidak ada perubahan kode/DB/config. Bukti dirujuk sebagai `File.php::method()` atau baris.
> Tandai: ✅ Fakta terverifikasi (terbaca di kode), 🔶 Inferensi (disimpulkan dari kode), ⚠️ Ketidakpastian (belum terkonfirmasi).

---

## 1. Ruang Lingkup

Kelompok ini mencakup tiga modul yang saling terkait lewat KPI:

| Modul | Controller | Services | Menu/Rute |
|---|---|---|---|
| Content Management (KPI Multimedia/Creative) | `Konten.php` | `Konten/ContentWorkflowService`, `ContentKpiService`, `MultimediaKpiService`, `ContentScopeService` | `konten/*` |
| Social Media KPI | `Sosial.php` | `SocialMedia/SocialMediaKpiService`, `SocialMediaScopeService`, `SocialMediaScraperService`, `BrightDataClient`, `BrightDataSocialMediaProvider` | `sosial/*` |
| Marketing Digital (KPI Kepala Divisi) | `Marketing.php` | `Marketing/MarketingKpiService`, `DigitalMarketingKpiService`, `MarketingRekapService` | `marketing/*` |

**Engine KPI**: ketiga modul *tidak* menghitung KPI final; mereka "mengulangi bukan engine KPI" (`ContentKpiService`, `MarketingKpiService`, `DigitalMarketingKpiService`) dan menyerahkan score/achievement ke engine existing (`KpiCalculationService`) via kode komponen (`kpi_components`, `kpi_weights`, `kpi_targets`).

**Model tercakup** (dibaca): `ModelContent`, `ModelContentType`, `ModelContentUnit`, `ModelContentPerson`, `ModelContentCampaign`, `ModelContentChecklist`, `ModelContentQc`, `ModelChannel`, `ModelChannelMetric`, `ModelChannelPerformance`, `ModelContentBriefVerdict`, `ModelContentPerformanceTarget`, `ModelImprovement`, `ModelSocialMediaAccount/Post/Snapshot/Target/ScrapeJob`, `ModelMarketingLead`, `ModelMarketingCampaign`, `ModelMarketingPlatform`, `ModelMarketingSource`, `ModelMarketingAdsPerf`, `ModelMarketingRekapHarian(+Detail)`, `ModelPublication`, `ModelPublicationPerformance`, `ModelPerformanceMetric`, `ModelPlatform`, `ModelUnit`.

---

## 2. Endpoint / Routes

Semua rute di bawah filter `auth` (`app/Config/Routes.php`).

### `konten/*` (baris 676–698)
| Method | Route | Controller::method |
|---|---|---|
| GET | `/konten/dashboard` | `Konten::dashboard` |
| GET | `/konten` | `Konten::index` |
| GET | `/konten/dt` | `Konten::dt` (DataTables JSON) |
| GET | `/konten/tambah` | `Konten::form` |
| GET | `/konten/edit/(:num)` | `Konten::form/$1` |
| GET | `/konten/detail/(:num)` | `Konten::detail/$1` |
| GET | `/konten/delete/(:num)` | `Konten::hapus/$1` |
| POST | `/konten/simpan` | `Konten::simpan` |
| POST | `/konten/status` | `Konten::setStatus` |
| POST | `/konten/qc` | `Konten::qc` |
| POST | `/konten/brief/verdict` | `Konten::brief_verdict` |
| GET | `/konten/channel` | `Konten::channel` |
| POST | `/konten/channel/simpan` · `/channel/hapus` | `channel_simpan` · `channel_hapus` |
| GET | `/konten/campaigns` | `Konten::campaigns` |
| POST | `/konten/campaign/simpan` · `/campaign/hapus` | `campaign_simpan` · `campaign_hapus` |
| GET | `/konten/improvements` | `Konten::improvements` |
| POST | `/konten/improvement/simpan` · `/status` · `/hapus` | `improvement_simpan` · `improvement_status` · `improvement_hapus` |

### `sosial/*` (baris 701–710)
| Method | Route | Controller::method |
|---|---|---|
| GET | `/sosial/kpi` | `Sosial::kpi` |
| GET | `/sosial/account` | `Sosial::account` |
| POST | `/sosial/account/simpan` · `/toggle` · `/hapus` | `account_simpan` · `account_toggle` · `account_hapus` |
| GET | `/sosial/target` | `Sosial::target` |
| POST | `/sosial/target/simpan` · `/hapus` | `target_simpan` · `target_hapus` |

### `marketing/*` (baris 713–729)
| Method | Route | Controller::method |
|---|---|---|
| GET | `/marketing` | `Marketing::index` (dashboard KPI) |
| GET/POST | `/marketing/rekap` · `/rekap/simpan` | `Marketing::rekap` · `rekap_simpan` |
| GET/POST | `/marketing/leads` · `/leads/simpan` · `/leads/status` · `/leads/hapus` | `Marketing::leads` · `leads_simpan` · `leads_status` · `leads_hapus` |
| GET | `/marketing/search_service` | `Marketing::search_service` (AJAX service) |
| GET/POST | `/marketing/ads_performa` · `/ads_performa/simpan` · `/ads_performa/hapus` | `ads_performa` · `ads_performa_simpan` · `ads_performa_hapus` |
| GET | `/marketing/laporan` | `Marketing::laporan` (read-only) |
| GET/POST | `/marketing/campaign` · `/campaign/simpan` · `/campaign/hapus` | `campaign` · `campaign_simpan` · `campaign_hapus` |

Rute non-modul relevan: `POST /api/kommo/webhook` → `KommoWebhook::handle` (baris 731); `GET /api/crm/lead-summary|lead-list|price-catalog|service-summary` → `CrmLeadBridge` (baris 735–739).

---

## 3. Alur Pengguna Utama & Alternatif

### 3.1 Membuat & mengelola konten (`Konten::simpan`)
1. **Buat** `POST /konten/simpan` tanpa `id` → butuh `canCreate` (multimedia 44 boleh). Status awal selalu `DRAFT`, `created_by = ID_AKUN`. ✅ `Konten::simpan`
2. **Edit** dengan `id>0` → butuh `canWrite` (hanya root/direktur/manager/kadiv). ✅ `Konten::simpan`
3. Validasi: `judul` wajib; `deadline` format `YYYY-MM-DD`; jika `target_scope=SELECTED` wajib ≥1 unit target; `campaign_id` harus ada di `content_campaigns`; `jenis_konten` ∈ `REGULAR|ADS` (default REGULAR). ✅ `Konten::simpan`
4. Simpan relasi: `ModelContentUnit::replaceForContent` (target unit), `ModelContentPerson::replaceForContent` (talent + creative). ✅ `Konten::simpan`
5. Brief **tidak lagi** diisi di form — dikomentari: "kolom brief di form sudah dihapus", penilaian kesesuaian brief pindah ke verdict manual. ✅ `Konten::simpan` (komentar baris 422–423)

### 3.2 Workflow status (`ContentWorkflowService`)
- Status: `DRAFT → PRODUCTION → QC → (PASS) APPROVED → COMPLETED`, atau `QC → (REJECT) REVISION → PRODUCTION → QC`. ✅ `ContentWorkflowService::TRANSITIONS`
- **Tidak ada** status `PUBLISHED`; setelah APPROVED langsung bisa COMPLETED. ✅ `ContentWorkflowService::TRANSITIONS` & komentar class.
- `setStatus` (POST `status`) memakai `transition()`, butuh `canChangeStatus`. Transisi ilegal ditolak (mis. DRAFT→QC langsung). ✅ `Konten::setStatus` + `transition()`
- `COMPLETED` mengisi `published_at` (bila kosong) dan `completed_at = now`. ✅ `transition()`

### 3.3 Workflow QC (`ContentWorkflowService::qc`)
- Butuh `canQc` (role 1/34/43) dan status konten **harus `QC`**. ✅ `Konten::qc` + `qc()`
- `PASS` → `APPROVED`; `REJECT` → `REVISION`. Riwayat dicatat di tabel `content_qc` (checker_id, checked_at, note). ✅ `qc()`

### 3.4 Brief verdict (kesesuaian brief) — manual
- Butuh `canAssessBrief` (role 1/34/43 → "Hanya Kepala Divisi"). ✅ `Konten::brief_verdict`
- `POST brief/verdict`: `sesuai ∈ {0,1}`; tulis baris baru di `content_brief_verdicts` (`penilai_id = ID_AKUN`). Verdict terbaru = `MAX(id)`. ✅ `Konten::brief_verdict` + `ModelContentBriefVerdict::insertVerdict/latestForContent`

### 3.5 Channel & pertumbuhan (`konten/channel`)
- `channel` GET menampilkan kanal aktif + metrik (`channel_metric`, `is_kpi`, `target_growth`). ✅ `Konten::channel`
- `channel_simpan` POST: validasi periode, kanal;, metrik must belong to kanal; `actual` ≥0; simpan/upsert di `channel_performance` (uniqueness channel+metric+periode). ✅ `Konten::channel_simpan`
- KPI pertumbuhan dihitung `ContentKpiService::channelGrowthSummary` (growth vs previous period, achievement vs target). ✅ `ContentKpiService::channelGrowthSummary`

### 3.6 Campaign konten (`konten/campaigns`)
- CRUD campaign (`content_campaigns`) butuh `canManageKpi` (manager/kadiv + multimedia operasional). ✅ `Konten::campaign_simpan`
- Hapus campaign: konten dilepas `SET NULL` (`campaign_id = null`) sebelum campaign dihapus. ✅ `Konten::campaign_hapus`

### 3.7 Improvement (`konten/improvements`)
- Submit/update butuh `canManageKpi`. ✅ `improvement_simpan`
- Hapus: hanya pemilik improvement atau `canManageKpi`. ✅ `improvement_hapus`
- Status: `submitted|approved|implemented|rejected`; `approved_at` diisi untuk approved/implemented. ✅ `improvement_status`

### 3.8 Akun social media & target (`sosial`)
- `account_simpan`: wajib unit+platform+profile_url; platform ∈ `PLATFORMS` (facebook/tiktok); upsert by identity `(unit_id, platform, profile_url)`. ✅ `Sosial::account_simpan` + `ModelSocialMediaAccount::findByIdentity`
- `account_toggle`: balik `is_active`. `account_hapus`: delete. Keduanya butuh `canManage`. ✅ `Sosial::account_toggle/account_hapus`
- `target_simpan`: butuh `canManage`; periode YYYY-MM + platform + metric valid + target ≥0; `upsertTarget`. ✅ `Sosial::target_simpan`

### 3.9 Scrape job (BrightData async) & KPI social media
- **Trigger** (`social:pull`): `SocialMediaScraperService::triggerAll()` → untuk tiap akun aktif provider → `provider->trigger()` → `snapshot_id` → `upsertTriggeredJob` (idempoten by `snapshot_id`). ✅ `ScraperService` + `ModelSocialMediaScrapeJob::upsertTriggeredJob`
- **Process** (`social:process`): `processPendingBatch()` → cek `fetchProgress`; bila ready → download+normalisasi → `storePosts` (upsert post + insert snapshot) → `markCompleted`; bila running/starting → `markStatus(processing)`; bila failed/exception → `markFailed` (error disanitasi token). ✅ `ScraperService::processPendingBatch/storePosts`
- **KPI**: `SocialMediaKpiService::summary/totals/metricAchievement` memakai snapshot **terbaru per post** (no double-count) + posts periode, filter unit/platform. Target unit-spesifik diutamakan, fallback global (`unit_id=0`). ✅ `SocialMediaKpiService` + `ModelSocialMediaSnapshot::latestSnapshotTotals`

### 3.10 Leads marketing (`marketing/leads`)
- **Manual detail prospek** = baris `kommo_lead_id IS NULL`. CRUD butuh `canWrite`. ✅ `Marketing::leads*` + `ModelMarketingLead::findManualById`
- Status detail prospek: `PROSPEK|DATANG|CLOSING|BATAL` (`PROSPEK_STATUSES`). ✅ `ModelMarketingLead`
- **CLOSING** (menutup jadi customer): wajib memilih service **SELESAI** (`status_service=4`); semua field (nama/noHP/keterangan/unit/tanggal/omset) diambil **dari service** sebagai source of truth; `tanggal_won` diisi. ✅ `Marketing::leads_simpan/leads_status` + `closedFieldsFromService`
- Non-CLOSING: service hanya tautan opsional; data manual dipakai. ✅ `Marketing::leads_simpan`

### 3.11 Rekap marketing harian (`marketing/rekap`) — source of truth KPI lead
- CS mengisi angka harian per cabang per platform (non-iklan, iklan, prospek, datang). `MarketingRekapService::save()` upsert per (unit,tanggal) + replace detail, dalam **transaksi**. ✅ `MarketingRekapService::save`
- `rate = datang / total * 100`; `Total Lead WA/DM = Σ total platform WhatsApp+Instagram` (case-insensitive). ✅ `MarketingRekapService::save/monthlySummary`

### 3.12 Ads performa (`marketing/ads_performa`) & Laporan Digital Marketing
- `ads_performa_simpan`: campaign **wajib** (harus ada di `marketing_campaigns`); periode valid; normalisasi angka `$toNum`/`$toCount` (ID & US format); channel ganda disimpan sebagai JSON (`channel_ids`). ✅ `Marketing::ads_performa_simpan`
- Increment-add upsert `getByUnique(bulan,tahun,campaign,tanggal,channel,unit)`. ✅ `Marketing::ads_performa_simpan`
- `laporan` (read-only) menghitung spending, PPN, biaya total, CTR/CPC/CPM/freq/CPR, best/worst day, per-channel/per-unit. Sumber tunggal = `marketing_ads_performance` (tidak campur Kommo/rekap/kanal). ✅ `Marketing::laporan`

### 3.13 Campaign Digital Marketing (`marketing/campaign`)
- `campaign_simpan`: nama wajib, periode valid, tanggal (mulai ≤ selesai), status ∈ labels. ✅ `Marketing::campaign_simpan`
- `campaign_hapus`: **ditolak** bila masih dipakai Performa Ads (`marketing_ads_performance`). ✅ `Marketing::campaign_hapus`

### 3.14 KPI (dashboards)
- `konten/dashboard`: KPI konten (5 komponen divisi) + KPI owner multimedia (6 komponen, per-employee). ✅ `Konten::dashboard` → `ContentKpiService::monthlyKpi`, `MultimediaKpiService::monthlySummary`
- `marketing`: 7 KPI Digital Marketing (jabatan 43). ✅ `Marketing::index` → `DigitalMarketingKpiService::monthlySummary`
- `sosial/kpi`: KPI social media per (unit × platform × metric). ✅ `Sosial::kpi`

---

## 4. Business Rules

### (a) Fakta terverifikasi (✅)

**Content / KPI Multimedia**
- `ContentScopeService::ROLES_VIEW = [0,1,2,34,43,44,48]`; `ROLES_WRITE=[0,1,2,34]`; `ROLES_CREATE=[0,1,2,34,44]`; `ROLES_STATUS=[0,1,2,34,44]`; `ROLES_QC=[1,34,43]`; `ROLES_MANAGE=[0,1,2,34,43]`; `ROLES_ASSESS_BRIEF=[1,34,43]`. ✅ `ContentScopeService` (konstanta)
- `canManageKpi = canManage || canChangeStatus` → multimedia 44 ikut mengelola campaign/improvement. ✅ `ContentScopeService::canManageKpi`
- KPI konten 5 komponen (bobot): Jumlah Konten 15%·30/bulan, Deadline 15%·≥95%, Kualitas 25%·≥90%, Brand 15%·≥95%, Pertumbuhan Channel 10%. **Performa Konten dihapus** dari struktur 6 KPI owner. ✅ `ContentKpiService::KPI_DEFS`
- Achievement non-pertumbuhan **di-cap 100**; `PERTUMBUHAN_CHANNEL` sengaja tidak di-cap (reward pertumbuhan > target). ✅ `ContentKpiService::monthlyKpi`
- Deadline on-time hanya status `COMPLETED` dan `completed_at ≤ deadline 23:59:59`. ✅ `ContentKpiService::monthlyKpi`
- Kualitas = distinct konten pernah QC PASS / total. ✅ `ContentKpiService::countQcPass`
- Brand = Σ `is_checked` / Σ item `content_checklists`. ✅ `ContentKpiService::brandSummary`
- KPI **owner Multimedia (jabatan 44)** 6 komponen, dihitung **per employee** via `content_people` (attribution), cap 100: Ketepatan Deadline 25%, Kualitas Output 25%, Kesesuaian Brief 20%, Produktivitas 15% (target 30 konten), Support Campaign 10%, Improvement 5%. ✅ `MultimediaKpiService::CODES/rawAggregates`
- Kesesuaian Brief: denominator = konten yang sudah dinilai (punya verdict); numerator = verdict terbaru `sesuai=1`. ✅ `MultimediaKpiService::briefAchievement`
- Support Campaign: hanya campaign `active|done` yang **berisi konten milik employee**; target = Σ `target_jumlah_konten` (fallback: campaign done dihitung jumlah kontennya; active tanpa target → 0 → null menunggu target). ✅ `MultimediaKpiService::campaignAchievement/campaignTargetTotal`
- Improvement: approved/implemented / target (1 per bulan), reuse `improvementResult`. ✅ `MultimediaKpiService::improvementResult`

**Social Media**
- Platform hanya `facebook`, `tiktok`; metric `views|plays|likes|comments|shares|saves`. ✅ `SocialMediaKpiService::PLATFORMS/METRICS`
- Sumber metric KPI = `social_media_posts` (published_at) + snapshot **terbaru per post** (bukan input manual, bukan Σ seluruh snapshot). ✅ `SocialMediaKpiService` + `ModelSocialMediaSnapshot::latestSnapshotTotals`
- Snapshot **tidak pernah di-overwrite**; identity `(post_id, captured_at)` → retry tidak ganda. ✅ `ModelSocialMediaSnapshot::insertSnapshot`
- Post identity = `(account_id, external_post_id)`; fallback hash URL bila tanpa id. ✅ `ModelSocialMediaPost::upsert` + `BrightDataSocialMediaProvider::fallbackPostId`
- Target: unit-spesifik diutamakan, fallback global `unit_id=0`. ✅ `ModelSocialMediaTarget::findTarget`
- Akun aktif saja yang discrape (`is_active=1`, provider `bright_data`). ✅ `SocialMediaScraperService::triggerAll` + `ModelSocialMediaAccount::activeByProvider`
- Job idempoten by uniqunapshot_id`; status job: pending/processing/running/ready/completed/failed. ✅ `ModelSocialMediaScrapeJob`

**Marketing**
- `Marketing::canView=[0,1,2,34,40,41,42,43]`; `canWrite=[0,1,2,42,43,44]`. ✅ `Marketing::canView/canWrite`
- Rekap harian = **source of truth** KPI lead (Kommo hanya CRM, tidak masuk angka KPI). ✅ `MarketingRekapService` + migrasi `2026-09-11-000300`
- Lead iklan/lead CS/datang CS KPI dihitung dari `marketing_rekap_harian(+detail)`. ✅ `MarketingRekapService::monthlyLeadTotal/PaidTotal/DatangTotal`
- Customer closing KPI = detail prospek manual `CLOSING` (`kommo_lead_id IS NULL`) by `tanggal_won`. ✅ `MarketingKpiService::countCustomers`
- Omzet marketing KPI = Σ `omset` detail prospek manual CLOSING by `tanggal_won` (tidak pakai transaksi customer/omzet global). ✅ `MarketingKpiService::marketingRevenue`
- Ads cost KPI = Σ `amount` `marketing_ads_performance` (period). ✅ `MarketingKpiService::adsCost`
- CPL = ads cost / paid lead; ROAS = revenue / ads cost; Conversion = customer/lead. ✅ `MarketingKpiService`
- KPI Digital Marketing (43) 7 komponen bobot total 100: OMZET_GLOBAL 50, LEADS_QUALITY 15, CONVERSION 10, CPL 10, CAMPAIGN_PERFORMANCE 5, REPORTING 5, IMPROVEMENT 5. ✅ `DigitalMarketingKpiService::BOBOT` (diverifikasi `testWeightsSumExactlyHundred`)
- Omzet Global: skor proporsional `actual / Target HO × 100` (cap 100); capai Target HO = 100. ✅ `DigitalMarketingKpiService::scoreOmzetGlobal/omzetTarget`
- LEADS_QUALITY = avg(achievement volume, achievement kualitas), cap 100; data absent leads → 0 (bukan 100). ✅ `DigitalMarketingKpiService::leadsScore`
- Data kosong per komponen → null (dikecualikan dari total), kecuali LEADS dengan data nyata 0 → 0. ✅ `DigitalMarketingKpiService` (komentar class)
- Target default: lead 60/bulan, customer 20, conversion 30%, CPL 250.000, omzet 300.000.000, ROAS 1.5 (legacy `MarketingKpiService`); target komponen divisi diambil dari `kpi_targets` (fallback unit 50, lalu unit NULL default). ✅ `MarketingKpiService::TARGET_*`, `DigitalMarketingKpiService::divTarget`

### (b) Inferensi (🔶)
- 🔶 `MarketingKpiService::monthlySummary` (dipakai `Marketing::index` legacy) kini hanyalah delegasi ke `DigitalMarketingKpiService::monthlySummary` (7 komponen baru); konstanta target `TARGET_*` masih ada tapi tidak lagi dipakai di dashboard. Kode dashboard memanggil `monthlySummary` → `DigitalMarketingKpiService`. Waspada dua "wajah" KPI marketing (legacy 7-komponen lama vs 7-komponen baru) — keduanya bernama sama 7.
- 🔶 Skor "Kualitas" konten (`ContentKpiService`) memakai kualitas QC PASS; KPI owner multimedia memakai `KUALITAS_OUTPUT` serupa tetapi per-employee.
- 🔶 Mapping kode komponen `COMPONENT_CODES` (KONTEN_JUMLAH, dst.) adalah jembatan ke engine `KpiCalculationService`; durasi/perhitungan akhir dilakukan engine (tidak terbaca di kelompok ini).
- 🔶 `ModelContent::getById` melakukan join `performance_metrics` (kolom `performance_metric_id`) meski tak lagi dipakai KPI (Performa Konten dihapus) — sisa legacy.
- 🔶 View `sosial` menampilkan `platforms=['facebook','tiktok']` statis di `Sosial::account` (tidak dari DB).

### (c) Ketidakpastian (⚠️)
- ⚠️ Bobot aktual per posisi (43/44) dijamin dari DB (`kpi_weights`); konstanta `BOBOT` di service adalah default/label, bisa berbeda dengan DB. Verifikasi lanjut perlu baca data DB (`kpi_weights` position 43/44).
- ⚠️ `SALES_UNITS=[1,2,3,4,5]` di `DigitalMarketingKpiService` hardcode daftar cabang operasional (HO dikecualikan); kebenarannya bergantung pada data unit aktual.
- ⚠️ Kolom `marketing_lead.no_hp` (legacy) vs `no_telp_wa` (detail prospek) keduanya ada; KPI memakai `omset`/`tanggal_won`/`customer_id`, bukan `no_hp`.
- ⚠️ Penjadwalan eksekusi `social:pull`/`social:process` (cron/command) tidak terbaca di kode — hanya service/command callback. Command `social:pull`/`social:process` disebut di komentar namun definisi CLI-nya tidak ditelusuri.
- ⚠️ `MediaKpiService::monthlySummary` mengiterasi `akun` jabatan 44 aktif (`STATUS_PEGAWAI=1`); atribusi berbasis `content_people` — tidak ada pengecekan bahwa orang tsb benar-benar "bertugas" pada unit tertentu.

---

## 5. Validasi & Permission/Role (per jabatan)

Permission dibangun **di atas session existing** (`ID_JABATAN/ID_UNIT/ID_AKUN`), bukan tabel permission baru. ✅ komentar `ContentScopeService`.

| Role ID | Nama (inferensi) | Konten | Sosial | Marketing |
|---|---|---|---|---|
| 0 | Finance/Admin | view/write/create/status/QC/manage/assess-brief | view/manage | view/write |
| 1 | Root/Admin root | view/write/create/status/QC/manage/assess-brief | view/manage | view/write |
| 2 | Direktur | view/write/create/status/manage (bukan QC) | view/manage | view/write |
| 34 | Manager | view/write/create/status/QC/manage/assess-brief | view/manage | view/write |
| 40 | Area SPV | — (tidak di `ROLES_VIEW`) | — | view saja |
| 41/42 | (opsional) | — | — | 41 view; 42 view/write |
| 43 | Kepala Divisi Digital Marketing | view/QC/manage/assess-brief (read-only scope seluruh divisi; **tidak** write content existing) | view/manage | view/write |
| 44 | Multimedia/Creative | view/create/status (boleh buat DRAFT & gerakkan status; **tidak** edit/hapus existing) | — | write |
| 48 | Talent | view only | — | — |

Catatan:
- **QC & brief verdict hanya role 1/34/43.** ✅ `ROLES_QC/ROLES_ASSESS_BRIEF`
- **Edit/hapus konten existing hanya role 0/1/2/34.** ✅ `ROLES_WRITE`
- **Multimedia (44) tidak bisa edit/hapus konten existing** (hanya detail), tapi bisa create & ubah status. ✅ komentar `ContentScopeService`
- Scope data konten: role 0/1/2/34/43/44 → semua unit (`scopeSql=null`); role 40 → unit dari `spv_units`; lainnya → unit sendiri + konten yang melibatkan user (target ALL/unit/publication/content_people). ✅ `ContentScopeService::scopeSql/allowedUnits`
- Validasi server-side dominan di controller (bukan model): format tanggal, enum, non-negatif, eksistensi relasi (campaign/channel/service/unit).

---

## 6. Perubahan Database / Transaksi / Efek Samping

**Transaksi eksplisit**
- `MarketingRekapService::save()`: `transBegin/transCommit` + rollback pada error (upsert header + replace detail). ✅ `MarketingRekapService::save`

**Efek samping / sinkronisasi**
- Konten `COMPLETED` → tulis `published_at`+`completed_at`. ✅ `ContentWorkflowService::transition`
- QC → insert `content_qc` + update status konten. ✅ `ContentWorkflowService::qc`
- Brief verdict → insert baris baru (historis). ✅ `ModelContentBriefVerdict::insertVerdict`
- Hapus campaign konten → `contents.campaign_id=null` (SET NULL manual) sebelum delete. ✅ `Konten::campaign_hapus`
- Hapus campaign marketing **diblokir** bila masih dipakai `marketing_ads_performance`. ✅ `Marketing::campaign_hapus`
- CLOSING lead → sinkronkan field dari service (nama/noHP/keterangan/unit/tanggal/omset), `tanggal_won` diisi; keluar CLOSING → `omset=null`, `tanggal_won=null`. ✅ `Marketing::leads_status`

**Scrape eksternal BrightData (async)**
- `social:pull` (trigger) → GET tidak menunggu ready; simpan job `snapshot_id` idempoten. ✅ `BrightDataClient` + `ScraperService::triggerAll`
- `social:process` → cek progress, download, normalisasi, simpan post+snapshot, tandai completed/failed. ✅ `ScraperService::processPendingBatch`
- Error handling API: timeout/koneksi/429/5xx → `SocialMediaTransientException`; 401/404/4xx lain/invalid JSON/empty → `BrightDataApiException`; token **tidak bocor** (disanitasi `[REDACTED]`). ✅ `BrightDataClient::requestJson` + `ScraperService::sanitizeMessage`
- Filter jendela: post hanya disimpan dalam rentang bulan (override `BRIGHT_DATA_START_DATE/END_DATE`). ✅ `ScraperService::isPostInWindow`

**Webhook / Bridge CRM (terkait)**
- `POST /api/kommo/webhook` → `KommoWebhook::handle`; `GET /api/crm/*` → `CrmLeadBridge` (signature HMAC, tanpa session ERP). Baris Kommo terpisah lewat `kommo_lead_id IS NULL` di KPI marketing. ✅ Routes baris 731–739 + `ModelMarketingLead`

**KPI (dampak komputasi)**
- KPI tidak menulis tabel baru; memakai data operasional langsung (read-only). ✅ komentar ketiga service KPI.

---

## 7. Relasi Antar-Modul

- **Konten → KPI Marketing/Manager**: `ContentKpiService::channelGrowthSummary` dipakai ulang oleh `MarketingKpiService::scoreByCode(CODE_GROWTH)` (komponen `CHANNEL_GROWTH` di `DigitalMarketingKpiService::componentData`). ✅
- **Konten → Marketing (Improvement)**: `DigitalMarketingKpiService` (komponen IMPROVEMENT) me-reuse `MultimediaKpiService::improvementResult`. ✅ (`testImprovementReusesMultimediaMechanism`)
- **Marketing → Konten (Channel)**: `marketing_ads_performance.channel_id` → `channel` (kanal yang sama dipakai `konten/channel`). ✅ `ModelChannel`, `Marketing::laporan`, `MarketingKpiService::adsCostByChannel`
- **Marketing → CRM/service**: `marketing_lead.service_id` → `service` (source of truth CLOSING/omset), via `service_sparepart.sub_total`; `customer_id` menghubungkan ke `pelanggan`. ✅ `Marketing::serviceOmset/serviceDetail/closedFieldsFromService`
- **Marketing → Kommo (CRM eksternal)**: `marketing_lead.kommo_lead_id` memisahkan baris Kommo vs manual; KPI hanya baris manual. ✅ `ModelMarketingLead`
- **Konten → publications/platform/unit**: `contents` → `content_units`, `content_people`, `publications` (platform/unit), `content_checklists`, `content_qc`, `content_brief_verdicts`, `content_campaigns`. ✅ `ModelContent` + `ContentScopeService::scopeSql`
- **Sosial → unit**: `social_media_accounts.unit_id` → `unit`; posting/snapshot mengikuti unit akun. ✅ `CreateSocialMediaSchema` + `ModelSocialMediaAccount::withUnit`
- **KPI cross-modul**: seluruh dashboard bergantung pada `kpi_components/kpi_weights/kpi_targets` (engine existing) — modul hanya sumber achievement.

---

## 8. Bukti Test

**`tests/SocialMedia/`**
- `KpiServiceTest.php`: filter unit/periode/platform; snapshot terbaru **tanpa double counting** (150 bukan 250); target achievement; fallback target global. ✅
- `ScraperServiceTest.php`: akun nonaktif tidak di-trigger; trigger simpan job; snapshot_id dinamis; idempoten (snapshot_id sama → 1 job); satu akun gagal tidak menghentikan batch; API error ditangani; post tidak duplicate; snapshot tidak overwrite; retry tidak ganda; identity fallback URL; views/plays terpisah. ✅
- `ProcessJobsTest.php`: running → tidak download (status processing); ready → download+persist+completed; failed → markFailed; completed dilewati; error → failed; batch multi status; idempoten (2x run); limit batch. ✅
- `BrightDataClientTest.php`: trigger Facebook (num_of_posts) vs TikTok (tanpa num_posts); tepat 1 HTTP request tanpa polling; 400/401/404/429/500/connect-timeout/invalid JSON/empty/BOM; token belum dikonfigurasi → exception. ✅
- (Ada juga `ProviderBatchTest.php`, `NormalizeTest.php`, `UnitAndSeedTest.php` — tidak dibaca detail, tercantum di direktori.)

**`tests/unit/`**
- `DigitalMarketingKpiTest.php`: pure static score (Omzet Global boundary 0/linear/100; leads absent=0; conversion null-if-no-lead; CPL lower-better & average; campaign/reporting ratio cap; bobot Σ=100; 7 codes; Improvement reuse). ✅
- `ManagerKpiTest.php`: agregasi manager (avg members missing=0; cap 100; avg per-role; saveManual reject out-of-range 0–100). ✅

**Cakupan**: Social Media service+client teruji baik; formula KPI marketing (pure) teruji; **tidak** terlihat test otomatis untuk controller Konten/Marketing end-to-end (workflow, rekap save, CLOSING, permission) dalam file yang dipindai.

---

## 9. Gap / Konflik

1. **Dua wajah KPI Marketing (43)**: `MarketingKpiService` (7 komponen lama: Lead/Customer/Conversion/CPL/Omzet/ROAS/Growth) vs `DigitalMarketingKpiService` (7 komponen baru: Omzet Global/Leads Quality/Conversion/CPL/Campaign Performance/Reporting/Improvement). `Marketing::index` memakai yang baru; `MarketingKpiService::scoreByCode` lama tetap ada (dipakai engine legacy?). Risiko ambiguitas bobot/komponen jika keduanya aktif.
2. **`ContentKpiService` vs `MultimediaKpiService`**: dua engine KPI konten berbeda (divisi vs per-employee owner 44) dengan bobot berbeda; keduanya tampil di dashboard yang sama (`monthlyKpi` + `monthlySummary`) → potensi kebingungan pembaca KPI.
3. **`performance_metrics` / `publication_performance` / `ModelContentPerformanceTarget`** masih ada (dan di-join) meski KPI "Performa Konten" dihapus → sisa legacy yang tidak dipakai komponen aktif. (`ContentKpiService::performanceSummary` & `ModelContent::publicationPerformance` ada tapi tidak dipanggil dari alur KPI utama.)
4. **Data leads multi-sumber**: lead KPI dari rekap harian (CS) vs leads dari detail prospek (`countCustomers` dari CLOSING) vs leads dari Performa Ads (`adsLeads` = `hasil`). Ketiganya memberi makna "lead" berbeda (kuantitas harian vs closing vs hasil iklan) — perlu kejelasan def.
5. **`SALES_UNITS=[1..5]` hardcode** untuk omzet global; perubahan struktur cabang memerlukan revisi kode (bukan konfigurasi).
6. **Target default ganda**: hardcode default di service (`TARGET_*`, `?? 30`, `?? 250000`, `?? 3000`) vs DB `kpi_targets`; perilaku saat target DB kosong bergantung pada default kode.
7. **Permission Marketing vs Sosial tidak seragam**: marketing `canView` memuat 40/41/42 sedangkan sosial hanya 0/1/2/34/43 → role 40–42 dapat melihat marketing tetapi tidak sosial/konten.
8. **`Sosial::account` & target** meng-hardcode `['facebook','tiktok']` (dan `SocialMediaKpiService::PLATFORMS`) — penambahan platform perlu ubah kode.
9. **CLOSING menghapus data manual**: saat lead diubah jadi CLOSING, nama/noHP/keterangan/tanggal booking sebelumnya ditimpa dari service; tidak ada jejak nilai lama (data lama hilang).
10. **Cron/CLI `social:pull`/`social:process` tidak terverifikasi** terdaftar; keandalannya KPI sosial bergantung pada penjadwalan eksternal.
11. **Kualitas QC tanpa kualifikasi checker**: `content_qc` mencatat `checker_id` tapi tidak ada validasi bahwa checker bernilai (mis. di masa lalu) — sekarang hanya role 1/34/43 yang bisa memicu.
12. **Konten status `REVISION`** dapat muncul di daftar tetapi `monthlyStats` menghitungnya; tidak ada batasan bahwa `REVISION` wajib kembali `PRODUCTION` dalam bulan yang sama.
