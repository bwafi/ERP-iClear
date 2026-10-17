# Performa Ads Otomatis dari Meta — Desain

Status: **usulan, belum ada kode.** Dokumen ini untuk direview dulu.
Tanggal: 2026-10-17

---

## 1. Temuan yang mengubah rencana

ERP sudah punya modul **Performa Ads**:

- Tabel `marketing_ads_performance`
- Halaman `marketing/ads_performa` (controller `app/Controllers/Marketing.php`, view `app/Views/marketing/ads_performa.php`)
- Model `App\Models\ModelMarketingAdsPerf`

Kolomnya **sama dengan sheet harian kepala divisi**:

| Sheet kepala div | Kolom ERP sekarang |
|---|---|
| Nama Kampanye | `campaign_id` → `marketing_campaigns.nama` |
| Tanggal | `tanggal` |
| Objektif | `objective` |
| Daily Budget | `daily_budget` |
| Spending Ads | `amount` |
| PPN % + Value | `ppn` (% saja; Value **sudah dihitung di view**, `amount + amount×ppn/100`) |
| Reach | `reach` |
| Impression | `impression` |
| Klik | `klik` |
| Hasil Meta | `hasil` |
| Ket. | `note` |
| — | `qualified` (kualitas lead, khusus ERP, tidak ada di Meta) |

**Konsekuensi:** pekerjaan ini **bukan** membuat laporan/tabel baru. Pekerjaan ini
**mengisi otomatis** baris `marketing_ads_performance` yang selama ini diketik manual.
Satu tabel, satu halaman, tanpa duplikasi.

### Premis lama yang gugur

- ~~"Bikin halaman laporan baru seperti sheet"~~ → halamannya sudah ada.
- ~~"Bikin tabel insight baru"~~ → tabelnya sudah ada.
- Frek/CTR/CPC/CPM/CPR **bukan** kolom di ERP dan tidak perlu ditambah: semuanya turunan,
  dihitung di view (pola yang sudah dipakai untuk kolom PPN Value).

---

## 2. Ruang lingkup

**Termasuk**

1. Command `php spark meta:pull` — tarik insight harian per kampanye, upsert ke
   `marketing_ads_performance`.
2. Pemetaan **kampanye Meta → campaign ERP** (manual, diisi sekali, dirawat).
3. Tabel label Objektif (15 kombinasi, lihat §7).
4. Tombol "Tarik dari Meta" di halaman Performa Ads + penanda asal baris.
5. Log tarikan + nilai rate-limit (untuk pertanyaan "sisa kuota berapa").

**Tidak termasuk**

- **Transfer antar unit** — pekerjaan terpisah (Tahap 2), menyentuh uang, dibahas sendiri.
- Kolom `qualified` — tetap manual (tidak ada padanannya di Meta).
- Menulis/mengubah apa pun ke Meta. Integrasi ini **baca saja** (read-only).
- Level `adset` / `ad`. Cukup `campaign` sesuai kebutuhan laporan.

---

## 3. Sumber data

Endpoint yang sudah terbukti jalan:

```
GET https://graph.facebook.com/v25.0/{ad_account_id}/insights
      ?level=campaign
      &time_increment=1
      &time_range={"since":"YYYY-MM-DD","until":"YYYY-MM-DD"}
      &fields=campaign_name,spend,reach,frequency,impressions,actions
```

Kredensial dari `.env` (sudah ada, tidak di-commit):

```
META_ADS_TOKEN=...
META_AD_ACCOUNT_ID=act_1134662688368218
```

Tarif pajak **per akun iklan**, bukan di kode: `META_PPN_PERSEN=5` (default 5).

Satu panggilan mengembalikan **semua kampanye sekaligus** untuk rentang tanggal —
bukan satu panggilan per kampanye.

---

## 4. Perubahan basis data

Empat migrasi kecil. Semua non-destruktif (`down()` tidak menghapus data).

**4.1 `marketing_campaigns`** — tambah:
- `meta_campaign_id` VARCHAR(32) NULL, UNIQUE (kampanye Meta yang dipetakan)
- `unit_id` INT NULL (cabang default untuk baris hasil tarikan)

**4.2 `marketing_ads_performance`** — tambah:
- `sumber` ENUM('manual','meta') NOT NULL DEFAULT 'manual'
- `ditarik_pada` DATETIME NULL (kapan angka ini diambil dari Meta)

**4.3 Tabel baru `meta_label_objektif`**

| kolom | keterangan |
|---|---|
| `objective` | mis. OUTCOME_ENGAGEMENT |
| `optimization_goal` | mis. CONVERSATIONS |
| `destination_type` | mis. MESSAGING_INSTAGRAM_DIRECT_WHATSAPP |
| `label` | mis. "Interaksi, CTWA DM+WA" |

UNIQUE(objective, optimization_goal, destination_type). Diisi manusia (§7).

**4.4 Tabel baru `meta_pull_log`** — catatan tiap tarikan:
`tanggal_mulai, tanggal_sampai, jumlah_kampanye, jumlah_baris, call_count,
total_time, total_cputime, app_id_util_pct, acc_id_util_pct,
estimated_time_to_regain_access, status, pesan, created_at`.

Ini yang menjawab "sisa kuota/limit berapa" tanpa harus buka App Dashboard.

---

## 5. Alur `meta:pull`

```
php spark meta:pull                       # default: 7 hari terakhir
php spark meta:pull --dari=2026-10-01 --sampai=2026-10-17
php spark meta:pull --dry-run             # tampilkan, tidak menyimpan
```

1. Baca peta `meta_campaign_id → campaign_id` dari `marketing_campaigns`.
2. Satu panggilan insights untuk rentang tanggal.
3. Untuk tiap baris kampanye×tanggal:
   - `spend` → `amount`; `reach`, `frequency`, `impressions`, `actions` → kolomnya;
   - `ppn` = `META_PPN_PERSEN`;
   - `objective` = label dari `meta_label_objektif` (cadangan: kode mentah
     `objective/optimization_goal/destination_type` bila belum dipetakan);
   - `daily_budget` = dari endpoint campaigns;
   - `klik` = **link_click**, `hasil` = `messaging_conversation_started_7d`;
   - `sumber` = 'meta', `ditarik_pada` = sekarang.
4. Upsert berdasarkan **(tanggal, campaign_id)**:
   - tidak ada baris → INSERT;
   - ada baris `sumber='meta'` → UPDATE;
   - ada baris `sumber='manual'` → **SKIP**, dilaporkan sebagai konflik
     (jangan pernah menimpa ketikan manusia).
5. Simpan hasil + header rate-limit ke `meta_pull_log`.
6. Cetak ringkasan: berapa baris baru, diperbarui, dilewati, gagal.

**Default 7 hari terakhir**, bukan 1 hari: angka `spend` Meta masih menyesuaikan
beberapa hari setelahnya (sudah terbukti: 70.836 vs 70.898 untuk hari yang sama).
Tarik bergulir supaya angka ikut terbarui.

### Cron

```
0 3 * * * cd /path/erp && php74 spark meta:pull >> writable/logs/meta_pull.log 2>&1
```

Pola sama dengan `social:pull` yang sudah ada (`app/Commands/SocialMediaScrape.php`).

---

## 6. Susunan kode (mengikuti pola SocialMedia yang sudah ada)

```
app/Services/Meta/MetaAdsClient.php      # HTTP + parsing + baca header (tanpa logika bisnis)
app/Services/Meta/MetaAdsPullService.php # pemetaan + upsert
app/Commands/MetaPull.php                # php spark meta:pull
app/Models/ModelMetaLabelObjektif.php
app/Models/ModelMetaPullLog.php
```

`MetaAdsClient` meniru `BrightDataClient`: token hanya dari `env()`, **token tidak
pernah muncul di pesan exception/log**, error transient dibedakan dari error tetap.

---

## 7. Label Objektif — 15 kombinasi yang ada di akun

Sudah ditarik dari akun (266 kampanye, 311 ad set). Kunci: `objective + optimization_goal + destination_type`.

| # | objective | optimization_goal | destination_type | adset | label (diisi Anda) |
|---|---|---|---|---|---|
| 1 | OUTCOME_ENGAGEMENT | CONVERSATIONS | MESSAGING_INSTAGRAM_DIRECT_WHATSAPP | 64 | |
| 2 | OUTCOME_TRAFFIC | CONVERSATIONS | MESSAGING_INSTAGRAM_DIRECT_WHATSAPP | 57 | |
| 3 | OUTCOME_ENGAGEMENT | CONVERSATIONS | WHATSAPP | 39 | |
| 4 | OUTCOME_TRAFFIC | PROFILE_VISIT | INSTAGRAM_PROFILE | 37 | |
| 5 | OUTCOME_TRAFFIC | PROFILE_VISIT | INSTAGRAM_PROFILE_AND_FACEBOOK_PAGE | 22 | |
| 6 | OUTCOME_TRAFFIC | CONVERSATIONS | INSTAGRAM_DIRECT | 22 | |
| 7 | OUTCOME_TRAFFIC | LINK_CLICKS | INSTAGRAM_DIRECT | 22 | |
| 8 | OUTCOME_ENGAGEMENT | CONVERSATIONS | INSTAGRAM_DIRECT | 11 | |
| 9 | OUTCOME_TRAFFIC | LINK_CLICKS | MESSAGING_INSTAGRAM_DIRECT_WHATSAPP | 10 | |
| 10 | OUTCOME_AWARENESS | REACH | UNDEFINED | 6 | |
| 11 | OUTCOME_ENGAGEMENT | LINK_CLICKS | INSTAGRAM_DIRECT | 6 | |
| 12 | OUTCOME_AWARENESS | AD_RECALL_LIFT | UNDEFINED | 6 | |
| 13 | OUTCOME_SALES | CONVERSATIONS | MESSAGING_INSTAGRAM_DIRECT_WHATSAPP | 4 | |
| 14 | OUTCOME_ENGAGEMENT | LINK_CLICKS | MESSAGING_INSTAGRAM_DIRECT_WHATSAPP | 4 | |
| 15 | OUTCOME_ENGAGEMENT | CONVERSATIONS | MESSAGING_INSTAGRAM_DIRECT_MESSENGER_WHATSAPP | 1 | |

Bila kombinasi baru muncul, label = kode mentahnya (laporan tidak pernah kosong).

---

## 8. Rumus kolom turunan (dihitung di view, tidak disimpan)

| Kolom | Rumus |
|---|---|
| Frek | `impression ÷ reach` |
| CTR | `klik(link_click) ÷ impression` |
| CPC | `amount ÷ klik` |
| CPM | `amount ÷ impression × 1000` |
| CPR Platform | `amount ÷ hasil` |
| PPN Value | `amount + amount × ppn ÷ 100` (sudah ada di view) |

Catatan penting: CTR/CPC di sheet kepala div berbasis **link click**, sedangkan field
`ctr`/`cpc` bawaan Meta berbasis **semua klik**. Karena itu `klik` disimpan sebagai
`link_click` dan CTR/CPC dihitung dari situ — kalau tidak, angkanya beda dari sheet.

---

## 9. Rate limit & error

Sudah terverifikasi: tier `development_access`, kuota besar (4 dari 668.200 panggilan),
batas berbentuk **bergulir** (bukan reset harian/bulanan). Blokir dev tier = 300 detik.

- Setiap tarikan menyimpan header `x-business-use-case-usage` &
  `x-fb-ads-insights-throttle` ke `meta_pull_log`.
- Tangani `#17`, `#613`, `#80004`, `#4`: baca `estimated_time_to_regain_access`,
  mundur (backoff) lalu ulangi. Jangan agresif.

---

## 10. Keamanan

- Token hanya di `.env` (sudah gitignored). Tidak pernah ditulis ke DB, log, atau
  halaman web.
- Token yang pernah tersalin ke chat sebaiknya **di-rotate** setelah selesai.
- Baris respons mentah **tidak** dicatat ke log (URL paging Meta memuat token).

---

## 11. Yang perlu keputusan Anda

1. Isi kolom label pada tabel §7 (15 baris).
2. Konfirmasi tarif pajak: 5% untuk akun ini, dan apakah sama untuk akun iklan lain.
3. Pemetaan unit & channel: apakah cukup 1 unit per campaign (`marketing_campaigns.unit_id`),
   atau ada campaign yang biayanya dibagi ke beberapa unit?
4. Boleh tidak `meta:pull` **memperbarui** baris yang tadinya manual? Desain ini
   memilih **tidak** (manual selalu menang) — konfirmasi.

---

## 12. Pengujian

- `app/Scripts/` self-check (pola `marketing_test.php`): panggil service dengan respons
  Meta palsu, pastikan rumus, pemetaan label, dan aturan konflik manual benar.
- Satu tarikan `--dry-run` dibandingkan dengan sheet kepala divisi untuk tanggal yang sama.

---

## 13. Yang sengaja tidak dikerjakan (YAGNI)

- Async/insight job Meta — tidak perlu, volume kita kecil.
- Batch request, level adset/ad, backfill tahunan — tidak diminta.
- Tabel insight baru — tabelnya sudah ada.
- Pelacakan hutang/piutang antar unit — Anda bilang tidak perlu.
