# PRD Modul 10 — Integrasi Eksternal & Cross-Cutting

| | |
|---|---|
| Kode Modul | INT-10 |
| Sumber analisis | `docs/prd/_analysis/Group_J_Integrasi_Cross.md`, `00_cross_cutting.md` |
| Status verifikasi | ✅ fakta / 🔶 inferensi / ⚠️ ketidakpastian |

---

## 1. Tujuan
Menjembatani ERP dengan sistem eksternal: Kommo CRM (webhook & API read-only), customer chat (progress invoice), dan menyediakan endpoint cross-cutting (approval, dashboard Home/Service).

## 2. Aktor
- Sistem eksternal (Kommo, CRM, customer chat) — tanpa session ERP; memakai secret/HMAC.
- Pengguna ERP (home dashboard, approval).

## 3. Preconditions
- API: secret `CRM_BRIDGE_SECRET` + HMAC-SHA256 + timestamp ±300 detik (bridge).
- Webhook Kommo: untuk opsional (kosong = terbuka — gap keamanan).

## 4. Alur Utama
### 4.1 Webhook Kommo — `KommoWebhook::handle`
- `POST api/kommo/webhook`; trigger saja; data otoritatif dari Kommo API.
- Idempotent (unique `uniq_kommo_lead` + `GET_LOCK`); soft-delete; skip unsorted; UPDATE tidak menimpa null.
- Menulis `nama,no_hp,cs,price,cabang,ads_organic,source_id,tanggal,status` + kolom `kommo_*`. **Tidak menulis `omset`.**
### 4.2 CRM Bridge (read-only)
- `GET api/crm/lead-summary|lead-list|price-catalog|service-summary`, `GET api/customer-chat/invoice-progress`.
- Wajib signature HMAC; tanpa session ERP.
- Price catalog: harga jual cabang → master; kategori servis via heuristik teks; promo tanggal 21 & 1–10.
### 4.3 Approval Phone — `Approval::approve/decline/{id}` (approval phone).
### 4.4 Dashboards — `Home::index`, `DashboardService::index`.

## 5. Business Rules (Bukti)
1. Bridge API read-only, tanpa session; wajib HMAC + timestamp window. — `CrmLeadBridge` (Routes `api/crm/*`)
2. Webhook trigger-only; idempotent; tidak menulis `omset`. — `KommoWebhook::handle`, migration `uniq_kommo_lead`
3. Status Kommo (`NEW/FOLLOW_UP/WON/LOST`) tidak selaras dengan status UI (`PROSPEK/CLOSING/BATAL`). — gap `KommoWebhook` vs `ModelMarketingLead::PROSPEK_STATUSES`
4. [⚠️] Tidak ada verifikasi `X-Hub-Signature` HMAC-SHA1 (TODO). — `KommoWebhook`
5. [⚠️] Secret webhook kosong = terbuka (belum dikonfigurasi). — `Config/Kommo`
6. Token refresh Kommo tidak persisten. — `KommoApiService`
7. Route duplikat `api/crm/lead-list` (dua baris identik). — `Routes.php:736-737` (IN-02)
8. **`CrmLeadBridge::leadSummary/leadList` tidak memfilter `ml.kommo_deleted_at IS NULL`**, sehingga lead yang dihapus di Kommo tetap muncul di CRM. — `CrmLeadBridge.php`; kolom di `2026-09-11-000100_AddKommoLeadFields.php` (IN-01)

## 6. Acceptance Criteria
- [ ] Webhook Kommo menyimpan/update lead idempotent; tidak dobel.
- [ ] API bridge menolak request tanpa signature/timestamp valid.
- [ ] Price catalog & service summary dapat diakses bridge.
- [ ] Invoice progress tersedia untuk customer chat.

## 7. Dependensi
- `ModelMarketingLead`, `ModelService`, `ModelPelanggan`, `ModelUnit`, `ModelProduk/ModelBarang`.
- Service `Kommo/*`; `ModelAuth`/session (khusus bagian web).

## 8. Status & Tandai
- ✅ Terverifikasi: HMAC bridge, webhook idempotent, tidak menulis omset, gap status.
- ⚠️ Gap:
  1. `CrmLeadBridge` tidak memfilter `kommo_deleted_at`. — IN-01
  2. Route `api/crm/lead-list` duplikat. — IN-02
  3. Webhook Kommo belum memverifikasi `X-Hub-Signature`; secret kosong = terbuka (status konfigurasi produksi tidak diketahui). — X-10
- ⚠️ Risiko: ketidakselarasan status Kommo vs manual → lead sinkronisasi bisa hilang dari pelaporan KPI.
- Test: **tidak ada test otomatis** untuk Kommo/bridge; hanya script manual `app/Scripts/kommo_test.php`.
