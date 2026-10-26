# Group J — Integrasi & Hal Lintas-Modul

Analisis statis (read-only) ERP CodeIgniter 4. Ruang lingkup: webhook Kommo, bridge CRM (lead summary/list, price catalog, service summary), invoice progress, approval phone, dashboard (Home/DashboardService), filter auth global, dan model/migrasi terkait.

---

## 1. Ruang lingkup

Kelompok ini mencakup jalur yang menghubungkan ERP dengan sistem eksternal dan antar-modul internal:

- **Kommo CRM → ERP**: webhook `POST /api/kommo/webhook` (`KommoWebhook::handle`), diproses `KommoWebhookService`, data otoritatif ditarik dari Kommo API v4 (`KommoApiService`), ditulis ke tabel `marketing_lead`.
- **ERP → CRM (bridge baca-saja)**: `CrmLeadBridge` — lead summary, lead list, price catalog, service summary. Tidak memakai session, memakai signature HMAC.
- **ERP → Chat/Konsumen**: `CustomerChatInvoiceBridge::progress` — progres invoice servis berdasarkan nomor.
- **Approval phone**: `Approval` (approve/decline) — persetujuan HP baru yang masuk via `ModelPhone` (tabel `barang`, kategori 1).
- **Dashboard**: `Home::index` dan `DashboardService::index` (metrik lintas-modul: penjualan, servis, kas, pelanggan).
- **Konfigurasi & auth**: `Config/Kommo.php`, `Config/Finance.php`, `Config/Filters.php`, `Config/Database.php`, `Config/Routes.php` (seksi API), `Filters/AuthFilter.php`.
- **Model**: `Models/Core.php` (role/menu), `Models/ModelMarketingLead.php`, `Models/ModelPhone.php`, `Models/ModelMarketingSource.php`.
- **Migrasi** terkait Kommo/lead: `2026-09-11-000100_AddKommoLeadFields.php`, plus migrasi `marketing_lead` lain (service_id, tipe, unit_id, harga/cabang, rename CLOSING, closing-rate otomatis, rekap harian).

File yang **tidak** termasuk: modul Marketing (controller `Marketing.php`) — hanya dipakai untuk memverifikasi hubungan lintas-modul (status/omzet), bukan untuk dirubah.

---

## 2. Endpoint / Routes

Dari `Config/Routes.php`:

### 2.1 API tanpa session (tidak memakai filter `auth`)

| Method | Route | Controller::method | Auth | Dibelikan |
|---|---|---|---|---|
| POST | `api/kommo/webhook` | `KommoWebhook::handle` | Webhook secret opsional (`KOM_WEBHOOK_SECRET`) | `Routes.php:731` |
| GET | `api/crm/lead-summary` | `CrmLeadBridge::leadSummary` | HMAC (secret `CRM_BRIDGE_SECRET`) + timestamp | `Routes.php:735` |
| GET | `api/crm/lead-list` (terdaftar **2x**, baris 736 & 737) | `CrmLeadBridge::leadList` | HMAC | `Routes.php:736-737` |
| GET | `api/crm/price-catalog` | `CrmLeadBridge::priceCatalog` | HMAC | `Routes.php:738` |
| GET | `api/crm/service-summary` | `CrmLeadBridge::serviceSummary` | HMAC | `Routes.php:739` |
| GET | `api/customer-chat/invoice-progress` | `CustomerChatInvoiceBridge::progress` | HMAC | `Routes.php:740` |

Catatan:
- Semua route di atas **tidak** dilampiri `['filter' => 'auth']`. Proteksi mereka murni berbasis signature/secret di dalam controller.
- `api/crm/lead-list` terdaftar dua kali (duplikat tidak berbahaya, hanya redundan).
- `status_service/(:num)` (`Status_Service::index`) juga publik tanpa filter auth (`Routes.php:218`), tapi di luar scope grup ini.

### 2.2 Route dengan filter `auth` (session)

- `/` → `Home::index` (`Routes.php:16`)
- `dashboard/service` → `DashboardService::index` (`Routes.php:19`)
- `/approval`, `approve/phone/(:num)`, `decline/phone/(:num)` → `Approval::*` (`Routes.php:133-135`)
- Semua route modul lain (service, pelanggan, produk, dst.) memakai filter `auth`.

---

## 3. Alur Pengguna utama & alternatif

### 3.1 Webhook Kommo → `marketing_lead`
1. Kommo memanggil `POST /api/kommo/webhook` (`KommoWebhook::handle`).
2. `KommoConfig` dicek: bila `KOM_ENABLED=false` → `503` (`KommoWebhook::handle` baris 33-35).
3. Bukan POST → `405`; signature tidak lolos → `401`.
4. Payload dikumpulkan (`collectPayload`): body JSON baru atau `$_POST` form lama; keduanya kosong → `400`.
5. `KommoWebhookService::processWebhook` menormalkan event (`normalizeEvents`) — format JSON (`payload.event='leads.*'`) dan format lama (`payload.leads.add/update/status/...`).
6. Per event:
   - `delete` → soft-delete (`markDeleted` set `kommo_deleted_at`).
   - `add/update/status/restore` → `processEvent`: `api->getLead($leadId)` (detail otoritatif), skip bila `is_unsorted`, `mapLead` → `upsertLead`.
   - `restore` → bersihkan `kommo_deleted_at`.
7. Respon: `200` `{ok:true}`; `502` bila ada `kommo_api_error`/`lead_tidak_ditemukan`; selain itu `200` dengan `errors`.

Alternatif:
- Lead `unsorted` dilewati (skip).
- `GET_LOCK` advisory lock per lead mencegah duplikat saat webhook bersamaan.
- `syncLead($leadId)` publik bisa dipakai backfill/cron (jalur sama persis dengan webhook).

### 3.2 Lead summary (`CrmLeadBridge::leadSummary`)
- HMAC + `month` + `phone` → ambil ≤10 lead `marketing_lead` pada bulan tsb. yang cocok dengan 10 digit terakhir `no_telp_wa`/`no_hp`.

### 3.3 Lead list (`CrmLeadBridge::leadList`)
- Paginasi (50 baris/halaman, maks 1000), filter `status` (`ALL/PROSPEK/CLOSING/BATAL`), pencarian `q`.
- Ringkasan: total lead, closing count, omzet (dari kolom `omset` saat status `CLOSING`), per cabang.

### 3.4 Price catalog (`CrmLeadBridge::priceCatalog`)
- Baca `barang` + `stok_barang` + `kategori`/`sub_kategori`; pilih harga cabang bila kolom ada (`harga_penjualan/harga_jual/harga_retail/harga/price`), fallback `b.harga`; **read-only** (`read_only:true`).
- Kategorisasi servis via heuristik teks (`offerFor`): LCD, Baterai, Backglass, Housing, Jasa pasang, Service mesin, Sparepart.
- Promo:
  - tanggal **21**: diskon `hsiDiscount` (%).
  - tanggal **1–10**: harga awal bulan `earlyMonthPrice` (iPhone X…14 Pro Max; hanya LCD Grade A, Baterai Grade Ori, Backglass) — hide warranty.
- Item non-servis (tidak terdeteksi service) **disaring keluar**.

### 3.5 Service summary (`CrmLeadBridge::serviceSummary`)
- Cari `service` berdasarkan 10 digit terakhir `no_hp`; padukan nama unit; label status; hitung sisa bayar (`payment_remaining = max(0, harus_dibayar - bayar)`), label Lunas/Menunggu.

### 3.6 Invoice progress (`CustomerChatInvoiceBridge::progress`)
- HMAC v1 + nomor `^SRV[0-9]{6,30}$/D`.
- Cari `service` by `no_service`; >1 hasil → `409`; 0 → `found:false`; cocok → label status + 5 kolom.

### 3.7 Approval phone (`Approval`)
- `index` → daftar HP (tabel `barang` kategori 1, belum dihapus).
- `approve($id_phone)` → set `status='1'` (`ModelPhone::update`).
- `decline($id_phone)` → `ModelPhone::delete` (soft? dihapus via model default).

### 3.8 Dashboard (`Home::index`, `DashboardService::index`)
- `Home`: pendapatan POS (tabel penjualan) + servis (status_service=4) per bulan; barang terlaris; hutang; kas keluar; count pelanggan.
- `DashboardService`: total pendapatan servis, servis selesai/proses/bisa diambil, rata-rata, chart harian, status service, per teknisi.

---

## 4. Business Rules

### (a) Fakta terverifikasi (dengan bukti)

- **Webhook hanya trigger; data otoritatif dari API.** `KommoWebhookService::processEvent`: `$lead = $this->api->getLead($leadId);` baris 188. Tidak ada INSERT dari payload tanpa detail API.
- **Idempotent via unique index + upsert.** `2026-09-11-000100_AddKommoLeadFields::up`: `ALTER TABLE marketing_lead ADD UNIQUE INDEX uniq_kommo_lead (kommo_lead_id)`. `upsertLead`: cari `kommo_lead_id` lalu insert/update.
- **Advisory lock GET_LOCK 5 detik** per lead sebelum upsert, dilepas di `finally`: `upsertLead` baris 306, 331-333.
- **UPDATE tidak menimpa null.** `upsertLead`: `$patch = array_filter($mapped, fn($v) => $v !== null); // baris 326.
- **Delete = soft-delete.** `markDeleted` set `kommo_deleted_at`; baris tetap ada.
- **Default saat INSERT**: `status='NEW'`, `ads_organic='ORGANIC'`, `tanggal=hari ini` (`upsertLead` baris 312-314).
- **Status mapping**: `wonStatusIds=[142]`, `lostStatusIds=[143]` (`Config/Kommo.php` baris 107,110); heuristik nama status non-editable; open stage → `FOLLOW_UP`/`NEW` (`statusIdToInternal`).
- **Kommo hanya menulis kolom tertentu**: `nama`, `no_hp`, `cs`, `price`, `cabang`, `ads_organic`, `source_id`, `tanggal`, `status`, + kolom `kommo_*` (`mapLead` baris 262-290). **Tidak menulis `omset`.**
- **Skip unsorted**: `processEvent` baris 190-193.
- **Bridge semua read-only**: `priceCatalog` baris 114 `read_only:true`; `serviceSummary` baris 140; tidak ada INSERT/UPDATE di `CrmLeadBridge`.
- **Harga jual hanya yang boleh dibuka**: `priceCatalog` komentar baris 78-79, `$stockPrice` hanya dari kandidat harga jual.
- **Signature HMAC-SHA256** memakai `CRM_BRIDGE_SECRET`, timestamp `X-CRM-Time` dalam ±300 detik, `hash_equals` (`CrmLeadBridge::authorize` baris 143-181; `CustomerChatInvoiceBridge::progress` baris 12-22).
- **AuthFilter hanya cek `logged_in`**: `AuthFilter::before` baris 20-24; cek menu/role di-*comment out*.
- **Filter global kosong**: `Config/Filters.php` `$globals['before']=[]`, `$filters=[]` — tidak ada CSRF/invalidchars/honeypot global.
- **Webhook secret opsional**: kosong → `authorized()` selalu true (`KommoWebhook::authorized` baris 81-83). Hanya header `X-Kommo-Webhook-Secret` / query `secret`.
- **No. telepon dinormalkan 10 digit terakhir** di bridge via `RIGHT(REPLACE(...),10)` (`leadSummary`/`serviceSummary`).
- **Kommo template tanggal dari `created_at`** (`mapLead` baris 278-280) → dipakai filter bulan KPI.
- **Approval phone** memakai `ModelPhone` yang `$table='barang'`, `idkategori=1` (`ModelPhone::getPhone`).

### (b) Inferensi (pola konsisten, tidak diverifikasi eksekusi)

- **Mekanisme anti-duplikat berlapis**: unique index + GET_LOCK (+ array_filter non-null) — disimpulkan saling melengkapi untuk webhook bersamaan.
- **Kolom `marketing_lead.omset` vs `price`**: Lang— menulis `price`, sedangkan ringkasan omzet di `leadList` menggunakan `omset` saat `status='CLOSING'`. Kecil kemungkinan lead Kommo (status `WON/LOST/NEW`) berkontribusi ke omzet bridge kecuali ada promosi `price→omset` di tempat lain (tidak ditemukan di scope ini).
- **Status tidak selaras**: `leadList` hanya menerima filter `PROSPEK/CLOSING/BATAL`, sementara Kommo menulis `NEW/FOLLOW_UP/WON/LOST`; sehingga lead Kommo tidak muncul saat pengguna memfilter status manual tersebut.
- **`customer-chat/invoice-progress` memakai format `SRV`**: regex `^SRV[0-9]{6,30}$` mengisyaratkan prefix `SRV`; ketidakcocokan format `no_service` riil akan menolak permintaan (inferensi).
- **Kommo menulis `status` yang tidak ada di `PROSPEK_STATUSES`** (`ModelMarketingLead` baris 30-35): lead hasil sinkronisasi tidak muncul di view Detail Prospek (yang memfilter `kommo_lead_id IS NULL`, `findDetailProspek` baris 78).

### (c) Ketidakpastian

- Nilai `.env` (`KOM_*`, `CRM_BRIDGE_SECRET`) tidak ada di repo (`.gitignore` baris 2); status "aktif" produksi tidak dapat diverifikasi.
- Format header signature Kommo resmi (`X-Hub-Signature` HMAC-SHA1) belum dipakai — di-*TODO* di `KommoWebhook::authorized` (baris 75-77). Ketidakpastian apakah verifikasi  sudah berfungsi.
- `KommoApiService` tidak mengimplementasikan `setConfigured()` di interface (hanya di class konkret) — minor.
- Pemetaan pipeline→stage → `FOLLOW_UP` untuk semua pipeline non-bawaan di-*TODO* (`statusIdToInternal` baris 491). Ketidakpastian untuk akun dengan pipeline kustom.
- Persistensi token refresh: `refreshAccessToken` hanya berlaku untuk proses; update `.env` di-*TODO* (baris 392-393) — token lama bisa kedaluwarsa lintas restart.
- Format `no_service` riil (prefix `SRV`) tidak diverifikasi dari data.

---

## 5. Validasi & Permission

### Auth global
- `AuthFilter` hanya memeriksa `session('logged_in') === true`; **tidak ada** pengecekan menu/role secara global (semua kode role di-*comment out*).
- `BaseController::initController` memuat stok minimum/proses service/bisa diambil/expired + `akun_service` untuk semua halaman (berjalan untuk **semua** controller termasuk bridge yang mewarisi `BaseController`, tetapi bridge tidak menggunakannya untuk otorisasi).

### Endpoint API tanpa session
- `KommoWebhook`: hanya secret string (opsional). Bila `KOM_WEBHOOK_SECRET` kosong → **terbuka tanpa otentikasi** (hanya terhalang method/format).
- `CrmLeadBridge` & `CustomerChatInvoiceBridge`: wajib `CRM_BRIDGE_SECRET` non-kosong + HMAC + timestamp `X-CRM-Time` (±300 dtk) + `hash_equals`. Secret kosong → ditolak (`authorize` baris 149).
- Per-route validasi tambahan:
  - `leadSummary`/`serviceSummary`: nomor `^[1-9][0-9]{6,19}$`.
  - `leadList`: status ∈ whitelist; page 1–1000; q ≤ 100 char.
  - `priceCatalog`: q ≤ 100; unit/item 0–999999999.
  - `invoice-progress`: regex `^SRV[0-9]{6,30}$/D`, time ±300.

### Filter global vs per-route
- `Config/Filters.php`: `globals.before=[]`, `globals.after=['toolbar']`, `methods=[]`, `filters=[]`. → tidak ada CSRF/invalidchars global; toolbar hanya after.
- Filter `auth` diterapkan per-route (hampir semua route WEB), **kecuali** seksi API di atas dan `status_service/(:num)` + `template`.
- Catatan: karena tidak ada CSRF global, POST `api/kommo/webhook` tidak terkena CSRF (diinginkan untuk webhook server-to-server).

---

## 6. Perubahan Database / Transaksi / Efek Samping

### Migrasi (non-destructive)
- `2026-09-11-000100_AddKommoLeadFields.php`: tambah `kommo_lead_id` (INT unsigned null), `kommo_account_id`, `kommo_pipeline_id`, `kommo_status_id`, `kommo_updated_at`, `kommo_deleted_at`; unique index `uniq_kommo_lead`. `down()` drop index + kolom.
- Migrasi `marketing_lead` lain (skema berbagi): `AddMarketingLeadServiceId`, `AddMarketingLeadTipe`, `AddMarketingLeadUnitId`, `AddMarketingLeadPriceCabang`, `RenameMarketingLeadStatusClosing`, `ClosingRateJadiAutomatic`, `CreateMarketingRekapHarian`, `ReworkMarketingLeadDetailProspek`.

### Transaksi / efek samping
- `upsertLead` membungkus dengan `GET_LOCK` (bukan transaksi DB penuh); insert/update individual via `ModelMarketingLead`.
- Webhook tidak pernah membuat Customer/Conversion/Omzet fiktif (komentar kelas `KommoWebhookService` baris 17-19).
- `markDeleted` melakukan UPDATE; tanpa transaksi — rawan intermediate bila gagal di tengah banyak event (tiap event di-catch terpisah, `processWebhook` baris 63-85).
- Bridge (lead summary/list/catalog/service/invoice) **murni SELECT** — tanpa efek samping tulis.

---

## 7. Relasi antar-modul

- `marketing_lead` dipakai bersama **Marketing** (manual prospek: `kommo_lead_id IS NULL`) dan **Kommo sync** (`kommo_lead_id IS NOT NULL`). Diferensiasi via model (`findDetailProspek`, `findByKommoId`).
- **CRM bridge** membaca `marketing_lead`, `service`, `unit`, `barang`, `stok_barang`, `kategori`, `sub_kategori` → menjembatani data ke CRM/konsumen.
- **Service**: `serviceSummary`/`invoice-progress` memakai tabel `service` (status_service, bayar, harus_dibayar, unit). `DashboardService` juga memakai `service`.
- **Approval phone** → tabel `barang` (kategori 1) via `ModelPhone`; terkait modul Produk/HP.
- **Finance**: `Config/Finance.php` mendefinisikan KPI/cutoff (tidak terhubung langsung ke bridge, tapi `leadList` omzet dan dashboard terkait pendapatan).
- **Core.php**: `get_role`/`get_menu`/`get_menu_show` untuk izin menu (tidak dipakai API).

---

## 8. Bukti Test

- **Tidak ditemukan** file test untuk Kommo/webhook/lead/bridge/price-catalog/invoice-progress di `tests/` (grep `kommo|webhook|CrmLeadBridge|leadSummary|...` → kosong).
- Satu **script manual**: `app/Scripts/kommo_test.php` (memanggil `api/kommo/webhook`), bukan test otomatis.
- Gun test lain (mixed—, dll.) di luar scope grup ini.
- Interface `KommoApiInterface` dan `KommoApiException` menyediakan kontrak agar bisa di-fake di test masa depan (komentar interface baris 8-9).

---

## 9. Gap / Konflik

1. **Asimetri keamanan webhook**: `KOM_WEBHOOK_SECRET` kosong → endpoint webhook terbuka; sebaliknya bridge **menolak** saat secret kosong. Risiko pemakaian webhook tanpa secret.
2. **Tidak ada verifikasi HMAC-SHA1 `X-Hub-Signature`** (TODO di `KommoWebhook::authorized`) — keaslian payload webhook Kommo belum dijaga bila memakai jalur resmi.
3. **Ketidakselarasan status**: Kommo menulis `NEW/FOLLOW_UP/WON/LOST`, sementara UI/ringkasan marketing & bridge memakai `PROSPEK/CLOSING/BATAL`. Lead Kommo tidak muncul di filter status manual, dan omzet bridge (`omset` saat CLOSING) tidak terisi untuk lead Kommo (yang menulis `price`).
4. **Duplikat route** `api/crm/lead-list` (baris 736-737) — minor.
5. **Token refresh tidak persisten** — risk expired lintas restart (TODO).
6. **Format `no_service` vs regex `SRV...`** belum diverifikasi terhadap data riil.
7. **`status_service/(:num)` dan `template` publik tanpa filter auth** (di luar scope, tapi satu pola akses publik).
8. **Advisory lock short window (5 dtk)**; bila pemrosesan per event >5 dtk, lock bisa lepas sebelum selesai (risiko race teoretis).
9. **`CrmLeadBridge` mewarisi `BaseController`** yang menjalankan query notifikasi di tiap request API (stok minimum, dll.) — overhead + ketergantungan pada tabel service untuk endpoint API.
