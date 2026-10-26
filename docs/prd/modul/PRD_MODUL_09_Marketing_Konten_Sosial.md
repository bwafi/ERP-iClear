# PRD Modul 09 — Marketing Digital / Konten / Social Media

| | |
|---|---|
| Kode Modul | MK-09 |
| Sumber analisis | `docs/prd/_analysis/Group_I_Marketing_Konten_Sosial.md` (lengkap) |
| Status verifikasi | ✅ fakta / 🔶 inferensi / ⚠️ ketidakpastian |

---

## 1. Tujuan
Mengelola KPI divisi pemasaran & kreatif: (1) Content Management / KPI Multimedia (jabatan 44), (2) Social Media KPI (scraper eksternal BrightData), (3) Marketing Digital / KPI Kepala Divisi (43). Ketiganya menyuplai achievement ke engine `KpiCalculationService`.

## 2. Aktor
- Role 0/1/2/34: view/write/manage penuh (kecuali QC/brief khusus 1/34/43).
- Role 43 (Kepala Divisi): view/QC/manage/assess-brief; tidak edit konten existing.
- Role 44 (Multimedia): create/status saja (tidak edit/hapus existing).
- Role 40/41/42: view marketing saja.
- Role 48 (Talent): view konten.

## 3. Preconditions
- Login; role sesuai (`ContentScopeService::ROLES_*`, `Marketing::canView/canWrite`).
- Untuk sosial: akun aktif & provider BrightData terkonfigurasi (token).

## 4. Alur Utama
### 4.1 Konten — `Konten::simpan`
1. Buat → `canCreate` (44 boleh); status awal selalu `DRAFT`; `created_by=ID_AKUN`.
2. Edit → `canWrite` (0/1/2/34); validasi: `judul` wajib, `deadline` `YYYY-MM-DD`, `target_scope=SELECTED` → ≥1 unit, `campaign_id` eksis, `jenis_konten ∈ REGULAR|ADS`.
3. Simpan relasi target unit & person (talent/creative).
### 4.2 Workflow status & QC
- `DRAFT → PRODUCTION → QC → APPROVED → COMPLETED`; `QC → REVISION → PRODUCTION → QC`. Tiada status `PUBLISHED`.
- `setStatus` via `transition()`; transisi ilegal ditolak; `COMPLETED` mengisi `published_at/completed_at`.
- `qc()` butuh `canQc` (1/34/43) & status `QC`; PASS→APPROVED, REJECT→REVISION; catat `content_qc`.
### 4.3 Brief verdict (manual)
- `brief_verdict` butuh `canAssessBrief` (1/34/43); `sesuai ∈ {0,1}`; tulis baris baru; verdict terbaru = `MAX(id)`.
### 4.4 Channel, Campaign, Improvement
- `channel_simpan`: validasi periode/kanal/metrik; `actual ≥0`; upsert `channel_performance`. Growth → `ContentKpiService::channelGrowthSummary`.
- `campaign_simpan` butuh `canManageKpi`; hapus campaign → `contents.campaign_id=null` dulu.
- `improvement_*` status `submitted|approved|implemented|rejected`.
### 4.5 Social Media
- `account_simpan` (upsert by `(unit,platform,profile_url)`; platform `facebook|tiktok`); `account_toggle/hapus` butuh `canManage`.
- `target_simpan` upsert target periode/platform/metric.
- Scrape: `social:pull` trigger (idempoten by `snapshot_id`); `social:process` processPendingBatch (fetchProgress → download → normalisasi → storePosts → completed/failed; token disanitasi).
### 4.6 Marketing
- Leads manual (detail prospek): status `PROSPEK|DATANG|CLOSING|BATAL`; CLOSING wajib service SELESAI (`status_service=4`) & seluruh field diambil dari service; `tanggal_won` diisi.
- Rekap harian (`MarketingRekapService::save`) = source of truth KPI lead (upsert per unit+tanggal, replace detail, transaksi).
- Ads performa: campaign wajib; `amount`/`hasil`; channel ganda JSON.
- Laporan read-only: spending/PPN/CTR/CPC/CPM/freq/CPR.
- Campaign marketing: tolak hapus bila masih dipakai `marketing_ads_performance`.

## 5. Business Rules (Bukti)
1. Rincian permission per role — `ContentScopeService` (konstanta `ROLES_*`), `Marketing::canView/canWrite`.
2. Konten DRAFT → workflow; tiada PUBLISHED; QC hanya 1/34/43. — `ContentWorkflowService::TRANSITIONS/qc`
3. Kualitas = distinct konten QC PASS/total; Brand = `Σ is_checked/Σ checklist`; Deadline = COMPLETED & `completed_at ≤ deadline 23:59:59`. — `ContentKpiService`
4. KPI owner 44 (per employee, cap 100): Deadline 25, Kualitas 25, Brief 20, Produktivitas 15, Campaign 10, Improvement 5. — `MultimediaKpiService`
5. Social media: sumber metric = posting + snapshot terbaru per post (no double count); platform hanya facebook/tiktok. — `SocialMediaKpiService` + `ModelSocialMediaSnapshot::latestSnapshotTotals`
6. Rekap harian = source of truth lead; CLOSING dari service; omzet KPI = Σ `omset` detail prospek manual CLOSING by `tanggal_won`. — `MarketingRekapService`, `MarketingKpiService::countCustomers/marketingRevenue`
7. KPI Digital Marketing (43) 7 komponen bobot Σ=100 (Omzet Global 50, Leads Quality 15, Conversion 10, CPL 10, Campaign 5, Reporting 5, Improvement 5). — `DigitalMarketingKpiService::BOBOT`
8. Data kosong per komponen → null (dikecualikan) kecuali leads bernilai 0 → 0. — `DigitalMarketingKpiService`

## 6. Acceptance Criteria
- [ ] Konten dapat dibuat (DRAFT), diedit (role write), status transisi legal, QC, brief verdict.
- [ ] Akun & target sosial media dikelola; job scrape idempoten; snapshot tidak overwrite.
- [ ] Leads manual CRUD; CLOSING menyinkronkan data service.
- [ ] Rekap harian tersimpan atomik dan menjadi sumber KPI lead.
- [ ] Performa ads & laporan terhitung; hapus campaign dicegah bila terpakai.
- [ ] Dashboard KPI konten/marketing/sosial menampilkan achievement.

## 7. Dependensi
- Model `ModelContent*`, `ModelChannel*`, `ModelSocialMedia*`, `ModelMarketing*`, `ModelPublication*`.
- Service `Konten/*`, `Marketing/*`, `SocialMedia/*`; engine `KpiCalculationService`.
- Modul: KPI, Unit, Service (CLOSING/omset), Kommo/CRM (lead).

## 8. Status & Tandai
- ✅ Terverifikasi: permission, workflow, KPI konten/multimedia/sosial/marketing, scrape, rekap, ads.
- ⚠️ Gap utama: (1) dua "wajah" KPI Marketing (legacy vs baru); (2) `performance_metrics`/`publication_performance` sisa legacy ter-join tapi tidak dipakai; (3) `SALES_UNITS=[1..5]` hardcode; (4) CLOSING menimpa data manual lama tanpa jejak; (5) jadwal cron `social:pull/process` tidak terverifikasi.
- Test: `tests/SocialMedia/*` (padat), `tests/unit/DigitalMarketingKpiTest`, `ManagerKpiTest`.
