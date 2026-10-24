# Rancangan: Campaign dari Meta & Reporting Mingguan

- Tanggal: 2026-10-18
- Status: menunggu persetujuan pemilik produk
- Terkait: `docs/superpowers/plans/2026-10-17-meta-ads-otomatis.md` (tarikan Meta yang sudah ada)

## 1. Kenapa (latar)

Tarikan Meta sudah jalan secara teknis, tapi **menulis 0 baris** karena tidak ada satu pun
campaign ERP yang dipetakan ke campaign Meta (log `meta_pull_log` id 37: `dilewati 93`,
"Tanpa peta: …"). Akibatnya:

- Data Meta yang tersedia (92 baris, 1–17 Okt) tidak masuk.
- KPI Kepala Divisi kekecilan: `adsBudget`/`adsLeads` dihitung `SUM` seluruh baris periode,
  jadi **baris yang tak tertulis = uang & leads yang hilang dari KPI**.

Akar masalahnya bukan bug, tapi **dua pekerjaan manual**: membuat campaign ERP, lalu
mencocokkannya ke campaign Meta. Rancangan ini menghapus keduanya, dan menata ulang
komponen KPI `REPORTING` yang selama ini bergantung pada isian manual itu.

## 2. Tujuan

1. Daftar campaign datang dari Meta — tidak ada lagi input campaign ERP manual.
2. Unit pembebanan biaya tetap akurat per campaign, dengan sedikit mungkin isian manual.
3. `REPORTING` tetap ada dan tetap bernama "Reporting", tapi isinya laporan yang wajar
   dinilai: laporan **mingguan** ke Manager.
4. Tidak membongkar model data yang ada kalau tak perlu (FK `campaign_id`, join Laporan,
   mesin KPI).

## 3. Bukan tujuan

- Menghapus tabel `marketing_campaigns` atau membongkar FK ke `marketing_ads_performance`.
- Sistem notifikasi ke Manager (tidak ada prasarana notifikasi; laporan dilihat di halaman).
- Ekspor/cetak PDF laporan mingguan.
- Mengubah bobot KPI selain `REPORTING`.

## 4. Fakta lapangan (hasil penelusuran)

- `marketing_campaigns`: per periode, punya `status` (draft/active/done), `unit_id`,
  `meta_campaign_id` (UNIQUE), `nama`, `pic`.
- KPI `REPORTING` sekarang = `done / total campaign` periode; dulu ada kolom `report_url`,
  dihapus di migrasi `2026-09-25-000400` — jadi "laporan per campaign" sudah pernah dicoba
  dan ditinggalkan.
- Tabel KPI (`kpi_weights`, `kpi_targets`, `kpi_evaluations`, `incentive_rules`) menaut
  komponen lewat **`kpi_component_id` (angka)**, bukan kode → mengganti `code`/`name`
  tidak merusak riwayat penilaian.
- `MetaAdsClient::fetchCampaignOptions($since, $until)` **sudah ada** (dibangun sesi ini):
  mengembalikan campaign yang ada belanja periode itu + yang sedang ACTIVE, dengan nama,
  spend, dan penanda aktif. Ini persis bahan yang dibutuhkan untuk sync.
- Pemilih Meta di form Campaign **sudah ada** (endpoint `marketing/campaign/meta_options`).
- Jabatan: `43` = Kepala Divisi (pemilik KPI), `34` = Manager.
- Unit operasional: `1` Probolinggo, `2` Jember, `3` Banyuwangi, `4` Pandaan, `5` Genteng.
- Nama campaign Meta memuat cabang: "Prob"/"Probolinggo", "Jember", "Banyuwangi"/"Bwi",
  "Pandaan", "Genteng" — kecuali beberapa (`Loker Talent iClear`, `Raja Casing`).

**Data yang ada sekarang (dicek langsung di `erp_local`):**

- `marketing_campaigns`: 15 baris — 10 periode Sep (id 1–10) dan 5 periode Okt (id 11–15),
  **semua `meta_campaign_id` NULL**, `unit_id` NULL, `pic` 55.
- `marketing_ads_performance`: 70 baris manual (1 Sep – 12 Okt), semuanya sudah punya
  `campaign_id` dan `unit_id`; belum ada satu pun baris `sumber='meta'`.
- Meta periode Okt mengembalikan **8 campaign**. Lima di antaranya cocok satu-satu dengan
  campaign manual Okt berdasarkan cabang + nomor:

  | Campaign manual | Campaign Meta |
  |---|---|
  | iClear Prob 76 | `120251146211920189` |
  | iClear Jember 64 | `120251147591300189` |
  | iClear Banyuwangi 60 | `120249387385360189` |
  | iClear Pandaan 09 | `120251197138500189` |
  | iClear Genteng 04 | `120251224385770189` |

  Tiga sisanya baru: `iClear Genteng 06`, `iClear Banyuwangi 61`, `Loker Talent iClear`.
- `MetaAdsPullService` mencari baris lama dengan kunci **(periode, campaign, tanggal)** dan
  **menimpa** baris manual ("Meta menang" — sudah jadi keputusan pemilik produk sebelumnya).
  Artinya: begitu kelima campaign di atas punya `meta_campaign_id`, tarikan Meta
  1–12 Okt **memperbarui** baris manual yang ada, **tidak menggandakan**.

## 5. Keputusan pemilik produk

| # | Keputusan |
|---|-----------|
| D1 | Campaign tidak diinput manual lagi; daftar datang dari Meta. |
| D2 | Campaign Meta di-**sync** otomatis jadi baris `marketing_campaigns` (tidak membongkar model). |
| D3 | Unit **tetap per campaign** (tidak dibagi rata); dibaca dari nama; default Probolinggo (unit 1); **boleh dikoreksi manual**. |
| D4 | `REPORTING` tetap bernama "Reporting" tapi jadi laporan **mingguan** (4×/bulan), dikirim ke Manager. |
| D5 | Skor `REPORTING` = **ketepatan waktu kirim**; tidak menunggu Manager memeriksa (pilihan a). |
| D6 | Pemilih Meta tetap dipakai sebagai jaring pengaman bila sync gagal. **Direvisi saat perancangan:** karena form tidak lagi membuat campaign manual, pemilih itu tidak punya pekerjaan; jaring pengamannya menjadi **menjalankan sync ulang** (tombol/command), dan `fetchCampaignOptions()` tetap dipakai sync. |

## 6. Rancangan

### 6.1 Sync campaign dari Meta

- **Sekali di awal (adopsi, bukan hapus):** isi `meta_campaign_id` untuk 5 campaign manual
  Okt (id 11–15) sesuai tabel §4. Sesudah itu `meta:pull` 1–12 Okt menimpa angka manual
  dengan angka Meta (tidak menggandakan, tidak menghapus baris). Ini bagian dari migrasi
  data, bukan langkah manual pemilik produk.
- Data Sep (id 1–10) **tidak disentuh** — riwayat, di luar lingkup.
- Command baru `meta:sync-campaigns {bulan} {tahun}` (pola mengikuti `meta:pull`), dan sebuah
  tombol "Tarik Campaign dari Meta" di halaman Campaign.
- **Periode sebelum 2026-10 ditolak** oleh command maupun tombol. Tanpa penjagaan ini, satu
  klik untuk September membuat 10 baris baru, lalu tarikan Sep menulis di bawah id baru
  sementara baris manual Sep tetap ada → belanja Sep dobel dan penyebut KPI lama naik dari
  10 ke 20.
- **Sync ikut dipanggil di awal `meta:pull`** (bukan hanya cron baru), supaya cron harian
  yang sudah terpasang (`0 3 * * * … spark meta:pull`) tetap menangkap campaign baru tanpa
  perubahan konfigurasi server. `--dry-run` tidak men-sync. Tombol/command terpisah tetap ada
  untuk menjalankan manual.
- Sumber daftar: `MetaAdsClient::fetchCampaignOptions()` untuk periode terpilih.
- Untuk tiap campaign Meta: upsert baris `marketing_campaigns` dikunci pada `meta_campaign_id`:
  - `nama` = nama Meta;
  - `period_month`/`period_year` = periode saat baris **dibuat** (tidak ditimpa saat update);
  - `status` = `active` bila Meta melaporkan ACTIVE, selain itu `draft` (menggantikan arti
    lama "selesai"; lihat §6.3);
  - `unit_id` = hasil deteksi nama (§6.2), **kecuali** yang sudah dikoreksi manual.
- **Campaign yang tidak lagi dilaporkan Meta diturunkan** ke `draft` di akhir sync (hanya
  baris ber-`meta_campaign_id` yang statusnya masih `active`). Tanpa ini, campaign yang
  di-pause tetap `active` selamanya dan menumpuk di daftar pilihan bulan-bulan berikutnya.
- Idempoten: sync berulang tidak menggandakan baris.
- Setelah baris campaign ada, `metaMap()` di `meta:pull` otomatis terisi → tarikan Meta
  berikutnya menulis baris.
- Halaman Campaign: tombol sync; form manual hanya menyunting **unit** — tidak ada lagi
  pembuatan campaign manual, dan `status` hanya ditampilkan (ditulis oleh sync). Pemilih Meta
  yang dibangun sebelumnya **dicabut** (endpoint + blok di view + assertion smoke-nya):
  tanpa form pembuatan, pemilih itu tidak punya pekerjaan. Fungsi
  `MetaAdsClient::fetchCampaignOptions()` tetap dipakai sync, jadi uji-nya tetap berlaku.

**Akibat satu baris per campaign Meta** (`meta_campaign_id` UNIQUE):

- Halaman Campaign menampilkan **semua** baris (tanpa saringan periode). Jumlahnya kecil
  (hanya campaign yang berbelanja atau ACTIVE yang pernah di-sync), dan ini satu-satunya
  tempat mengoreksi unit — jadi baris lama harus tetap terjangkau.
- Pemilih campaign di halaman Laporan **dan** di form Performa Ads memakai satu query
  bersama: *punya baris iklan di periode itu, atau `period_month`/`period_year` = periode
  itu (akses data lama), atau `status = 'active'`*. `ModelMarketingCampaign::options()` ikut
  diubah ke query ini — kalau tidak, campaign lintas bulan tidak bisa dipilih di form
  Performa Ads dan jaring pengaman manual (D6) mati.
- Tidak ada perubahan skema. Campaign yang berjalan lintas bulan tetap satu baris, dan tetap
  muncul di bulan mana pun ia berbelanja.

**Batasan yang disadari** (ditandai di kode): `metaMap()` mengembalikan satu entri per
`meta_campaign_id`, jadi satu campaign Meta = satu baris untuk selamanya. Cukup untuk pola
sekarang (campaign baru tiap bulan), tidak cukup bila kelak ada kebutuhan riwayat per bulan
untuk campaign yang sama.

### 6.2 Unit per campaign

- Deteksi dari nama, peta kata kunci → unit, **di satu tempat** yang gampang diubah:

  | Kata kunci nama | Unit |
  |---|---|
  | `probolinggo`, `prob` | 1 Probolinggo |
  | `jember` | 2 Jember |
  | `banyuwangi`, `bwi` | 3 Banyuwangi |
  | `pandaan` | 4 Pandaan |
  | `genteng` | 5 Genteng |
  | (tidak ada yang cocok) | 1 Probolinggo (default) |

- Koreksi manual: kolom unit di halaman Campaign. Butuh penanda supaya sync tidak menimpa
  koreksi → tambah kolom `unit_sumber` ENUM(`auto`,`manual`) DEFAULT `auto`; deteksi hanya
  menulis bila `unit_sumber = 'auto'`.
- Contoh: `Loker Talent iClear` → Probolinggo (default). Nanti bila dinamai
  `Loker Talent iClear Banyuwangi` → Banyuwangi (kata kunci cocok).
- Baris iklan mewarisi `unit_id` campaign — `meta:pull` sudah bekerja begitu.
- Kelima campaign adopsi (§6.1) menghasilkan unit 1–5, **sama** dengan `unit_id` yang sudah
  ada di baris manualnya — jadi tidak ada angka yang bergeser.
- **Koreksi unit ikut memperbaiki baris lama:** saat unit sebuah campaign dikoreksi,
  perbarui juga `marketing_ads_performance.unit_id` untuk `campaign_id` itu (semua periode).
  Tanpa ini, koreksi hanya berlaku untuk tarikan berikutnya dan baris lama tetap di cabang
  yang salah.

### 6.3 Reporting mingguan

- **Artefak baru**: tabel `marketing_laporan_mingguan`:
  `id`, `period_year`, `period_month`, `minggu` (1–4), `submitted_by`, `submitted_at`,
  UNIQUE(`period_year`,`period_month`,`minggu`).
- **UI**: di halaman Laporan (`/marketing/laporan`), tambah pemilih **Minggu** dan tombol
  "Laporkan minggu ini" (hanya peran yang berhak tulis). Setelah dikirim, tampil badge
  "Dilaporkan <tanggal> oleh <nama>".
- **Isi laporan** = ringkasan performa iklan minggu itu, memakai perhitungan yang sudah ada
  di halaman Laporan (spending+PPN, hasil, CTR/CPC/CPM/CPR, campaign terbaik/terendah,
  banding minggu sebelumnya), dipotong ke rentang satu minggu.
- **Definisi minggu** (pilihan saya): selalu **4 minggu per bulan** — 1–7, 8–14, 15–21,
  22–akhir bulan. Ini yang menjamin "4 kali sebulan".
- **Skor KPI**: `achievement = (jumlah minggu yang submitted_at ≤ deadline) / (jumlah minggu
  yang deadlinenya sudah lewat) × 100`. Penyebut memakai **deadline**, bukan akhir minggu:
  kalau memakai akhir minggu, skor sempat tampak 0% selama masa tenggang 2 hari (minggu 1
  berakhir tgl 7, batas tgl 9), dan KPI November bisa terbaca 75% saat gaji diproses 1–2
  Desember padahal laporannya tepat waktu. Perbandingan per tanggal (batas = akhir hari).
  Bulan tanpa deadline lewat → `null` (dikecualikan, pola KPI yang ada).
- **Kapan tombol boleh ditekan**: hanya untuk minggu yang **sudah berakhir**; server menolak
  minggu yang belum berakhir (kalau tidak, keempat minggu bisa dikirim tanggal 1 dan skornya
  100% tanpa laporan). Minggu yang telat tetap boleh dikirim — tercatat, dan dihitung tidak
  tepat waktu karena `submitted_at` melewati deadline.
- **Tanggal acuan** (pilihan saya): kalender (`date('Y-m-d')`), bukan parameter `$date` KPI.
  `componentData()` menerima `$date` yang di pemanggil KPI selalu diisi **tanggal 15** bulan
  yang dinilai — kalau dipakai, tiap bulan hanya terhitung 2 deadline. Jadi: bulan yang sudah
  lewat → keempat deadline lewat; bulan berjalan → deadline yang sudah lewat sampai hari ini;
  bulan mendatang → `null`.
- **Berlaku mulai kapan** (pilihan saya): formula mingguan hanya untuk periode **≥ 2026-11**;
  periode sebelumnya tetap memakai `done / total` yang lama. Tanpa batas ini, seluruh riwayat
  KPI turun 5 poin karena laporan mingguan tidak pernah ada di masa lalu. Oktober 2026 tidak
  dihukum atas minggu 1–2 yang sudah lewat sebelum fitur ini ada.
- **Deadline** (pilihan saya): 2 hari kalender setelah minggu berakhir, jadi minggu 1 → tgl 9,
  minggu 2 → tgl 16, minggu 3 → tgl 23, minggu 4 → tgl 2 bulan berikutnya.
- **Sekali kirim, tidak bisa diubah** (pilihan saya): baris disisipkan sekali; tombol hilang
  setelah terkirim dan `submitted_at` tidak pernah diperbarui. Kalau boleh di-upsert, laporan
  telat akan berubah jadi "tepat waktu" dan skornya kehilangan arti.
- Manager (jabatan 34) menerima; **tidak menggerbang skor** (D5) dan **tidak ada kolom
  penerima** — penyampaian ke Manager terjadi di luar sistem; halaman ini jadi rujukannya.

### 6.4 `CAMPAIGN_PERFORMANCE`

- **Pilihan saya: biarkan apa adanya.** Setelah sync, hampir semua baris iklan tertaut
  campaign sehingga nilainya ~100% — tapi ia tetap berfungsi sebagai pemeriksaan integritas
  (menangkap baris yang gagal tertaut). Tidak diubah tanpa keputusan pemilik produk.

## 7. Pilihan yang saya ambil (silakan dikoreksi)

1. `status` ditulis sync dari status Meta, hanya ditampilkan (tidak bisa disunting); campaign
   yang tidak lagi dilaporkan Meta diturunkan ke `draft`.
2. Halaman Campaign menampilkan semua baris; pemilih di Laporan & Performa Ads memakai query
   bersama "punya baris iklan di periode itu, atau periode = periode itu, atau `active`"
   (tanpa perubahan skema).
3. Definisi minggu 1–7/8–14/15–21/22–akhir (bukan pekan ISO).
4. Deadline 2 hari kalender setelah minggu berakhir; penyebut skor = minggu yang deadlinenya
   sudah lewat.
5. Formula mingguan mulai periode 2026-11; periode lama tetap `done / total`.
6. Laporan mingguan sekali kirim (tidak bisa diubah/dikirim ulang); hanya minggu yang sudah
   berakhir yang bisa dikirim.
7. Tidak ada kolom penerima; Manager melihat halaman yang sama.
8. Koreksi unit ikut memperbarui baris iklan lama campaign itu.
9. `CAMPAIGN_PERFORMANCE` tidak diubah.
10. Form campaign hanya menyunting unit; tidak ada pembuatan manual; pemilih Meta dicabut
    (fungsinya sudah digantikan sync, `fetchCampaignOptions()` tetap dipakai).
11. Tidak ada notifikasi ke Manager.
12. Campaign Meta yang hilang/dihapus di Meta: barisnya **dibiarkan** (riwayat baris iklan
    tetap punya induk), statusnya diturunkan bila tidak lagi dilaporkan.
13. Sync menolak periode sebelum 2026-10, dan ikut dijalankan di awal `meta:pull` supaya cron
    harian yang sudah ada tetap bekerja.

## 8. Yang masih terbuka

Tidak ada yang menghambat. Dua hal yang sengaja tidak dikerjakan, supaya tidak melebar:

1. Backfill data Sep 2026 dari Meta (riwayat dibiarkan apa adanya).
2. Satu campaign Meta yang berjalan lintas bulan tetap satu baris (lihat batasan §6.1).

## 9. Risiko

- **Deteksi unit dari nama bisa salah** kalau penamaan tidak lazim. Mitigasi: koreksi manual
  ada (dan ikut memperbaiki baris lama), unit yang terdeteksi ditampilkan agar kelihatan.
  Salah unit = biaya masuk cabang keliru (tidak mengubah total bulan, tapi salah di laporan
  per cabang).
- **Skor 5 poin bergantung pada satu aksi per minggu** yang mudah terlupa → KPI bisa jatuh
  karena lupa mengirim, bukan karena kinerja.
- **Angka manual Okt 1–12 akan tertimpa angka Meta** saat tarikan pertama. Ini memang tujuan
  ("Meta menang"), tapi bila angka manual itu pernah dipakai untuk laporan lain, selisihnya
  akan terlihat.
- **`status` lama "done" hilang** pada campaign yang di-sync (Okt ke atas): nilainya menjadi
  `active`/`draft`. Aman karena KPI memakai `done/total` hanya untuk periode < 2026-11, dan
  skor Okt sudah 0% sebelum perubahan ini. Data Sep tidak disentuh.
- **Sync menambah 2 panggilan Meta per hari** karena ikut berjalan di awal `meta:pull`.
  Pastikan idempoten dan tidak dobel bila tombol di halaman ditekan bersamaan dengan cron.

## 10. Rencana uji (tes dulu, sebelum kode)

- Deteksi nama → unit: tiap cabang, default Probolinggo, contoh `Loker Talent iClear
  Banyuwangi`.
- Skor REPORTING mingguan: tepat waktu vs telat; **masa tenggang** (tgl 8, minggu 1 belum
  masuk skor karena deadline tgl 9); minggu yang deadlinenya belum lewat tidak masuk penyebut;
  bulan tanpa deadline lewat → `null`; periode < 2026-11 tetap `done / total`.
- Laporan mingguan: kirim sekali tercatat; kirim kedua ditolak; **minggu yang belum berakhir
  ditolak**; kirim telat tetap tercatat dan dihitung telat; badge menampilkan pengirim.
- Command sync: idempoten (tidak menggandakan baris), tidak menimpa unit yang dikoreksi
  manual, menulis `status` dari status Meta, menurunkan campaign yang tak lagi dilaporkan,
  dan **menolak periode sebelum 2026-10**.
- `meta:pull` menjalankan sync lebih dulu; `--dry-run` tidak men-sync.
- Adopsi 5 campaign Okt: `meta:pull` 1–12 Okt **memperbarui** baris manual (bukan menambah),
  jumlah baris tidak bertambah.
- Koreksi unit: baris iklan lama campaign itu ikut berubah `unit_id`-nya.
- Pemilih campaign (`ModelMarketingCampaign::options()`) tetap memuat campaign yang
  berbelanja di periode itu walau `period_month`-nya bulan lain.
- Smoke: halaman Campaign menampilkan unit terdeteksi + kontrol koreksi; halaman Laporan
  punya pemilih minggu + tombol lapor.
