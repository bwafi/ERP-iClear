# PRD Induk — Sistem ERP (CodeIgniter 4)

| | |
|---|---|
| Repository | `/home/syro/Projects/ERP` |
| Tanggal analisis | 2026-10-26 |
| Metode | Analisis kode baca-saja (read-only). Tidak ada perubahan source/DB/config. |
| Status verifikasi | ✅ Fakta terverifikasi dari kode · 🔶 Inferensi · ⚠️ Ketidakpastian/belum diverifikasi |

---

## 1. Ringkasan Sistem
Sistem ERP operasional toko/cabang berbasis **CodeIgniter 4** (PHP 8, MySQL produksi / SQLite3 test), Bootstrap 5, Vanilla JS, jQuery/DataTables. Cakupan: penjualan (POS), pembelian & persediaan, service & garansi, keuangan (kas/bank/jurnal/laporan), hutang piutang terpusat, HRD & payroll, aset, KPI & penilaian, marketing digital/konten/social media, dan integrasi Kommo/CRM.

Volume: 88 controller · 127 model · 86 service · 157 migrasi · 219 view · 88 file test.

## 2. Inventarisasi Modul (Induk)

| Kode | Modul | Controller utama | PRD |
|---|---|---|---|
| MD-01 | Master Data & Inti | Auth, Pegawai, Unit, Produk, Kategori, Phone, Pelanggan, Supplier, Bundle, NoAkun | `modul/PRD_MODUL_01_MasterData.md` |
| PJ-02 | Penjualan & Piutang | Penjualan, Riwayat_Penjualan, Retur_Customer, Piutang | `modul/PRD_MODUL_02_Penjualan_Piutang.md` |
| PB-03 | Pembelian & Persediaan | Pembelian, StokAwal, KartuStok, MutasiStok, StokOpname, ReturBarangRusak | `modul/PRD_MODUL_03_Pembelian_Persediaan.md` |
| SV-04 | Service & Garansi | Service, Riwayat_Service, StatusGaransi, Expired_service | `modul/PRD_MODUL_04_Service_Garansi.md` |
| KEU-05 | Keuangan, KasBank, Aset | KasMasuk/Keluar, Jurnal, SisiKeuangan, LabaRugi, KasBank, TutupKasir, Asset, DashboardFinance | `modul/PRD_MODUL_05_Keuangan_KasBank_Aset.md` |
| HP-06 | Hutang Piutang Terpusat | HutangPiutang, PembayaranHutang, Piutang | `modul/PRD_MODUL_06_HutangPiutang.md` |
| HR-07 | HRD & Payroll | Absensi, RiwayatPresensi, JadwalMasuk, Tugas, Payroll | `modul/PRD_MODUL_07_HRD_Payroll.md` |
| KPI-08 | KPI & Penilaian | PenilaianKPI, TemplateKPI, Penilaian, SummaryKPI, TargetKpi, CustomerSatisfaction | `modul/PRD_MODUL_08_KPI_Penilaian.md` |
| MK-09 | Marketing/Konten/Sosial | Konten, Sosial, Marketing | `modul/PRD_MODUL_09_Marketing_Konten_Sosial.md` |
| INT-10 | Integrasi & Cross | KommoWebhook, CrmLeadBridge, CustomerChatInvoiceBridge | `modul/PRD_MODUL_10_Integrasi_Cross.md` |

## 3. Aturan Lintas-Modul (Verified)
### 3.1 Autentikasi & Otorisasi
- `AuthFilter` hanya memeriksa `logged_in=true`; **tidak ada penegakan menu/role global**. Role dipakai untuk menampilkan sidebar (`Core::get_role/get_menu_show`) dan untuk guard di dalam controller (mis. `FinanceScopeService::inputRoles()`, `ContentScopeService::ROLES_*`). Konsekuensi: sebagian besar otorisasi bergantung pada disiplin per-controller.
- Login: `ModelAuth::cekUsername` (NOID|KTP|EMAIL) + `password_verify`; sesi berisi `ID_AKUN, ID_JABATAN, ID_UNIT, NAMA_UNIT, logged_in`.
### 3.2 Transaksi & Atomisitas
- Pola `transStart/transComplete/transRollback`; banyak modul keuangan memakai `PostingLedgerException` untuk membatalkan seluruh batch bila ledger gagal (anti partial-success).
- Idempotensi posting ledger dijaga unique `(sumber_tipe,sumber_id,akun,arah)`.
### 3.3 Scope Unit & Entitlement
- Role 0/1/2/34 bebas lintas unit; role lain dibatasi ke unit sendiri / `spv_units` (SPV).
- Rekening shared / Finance-HO (`is_finance_ho`) punya aturan source/destination.
- Cut-off `FinanceScopeService::cutoffDate()=2026-10-05`; transaksi legacy ber-scope `LEGACY`.
### 3.4 CSRF
- Global CSRF mati; diaktifkan per-route (`['auth','csrf']`) pada penulisan ledger/kas/tutup kasir/stok opname/retur.
### 3.5 Audit & Anti-Double-Counting
- Hutang/piutang terpusat menolak double counting; KPI memakai sumber achievement (rekap/lead/snapshot) tanpa menulis ulang.

## 4. Matriks Cakupan Verifikasi

| Modul | Cakupan analisis | Test ada | Status |
|---|---|---|---|
| MD-01 | Lengkap (agent A) | hampir tidak ada | ✅ + banyak gap |
| PJ-02 | Sebagian (baca Penjualan full) | view test saja | ✅/⚠️ |
| PB-03 | Sebagian (baca Pembelian full) | opname/mutasi/retur | ✅/⚠️ |
| SV-04 | Sebagian (grep Service) | padat | ✅/⚠️ |
| KEU-05 | Sebagian (Kas_Keluar & ModeKasBank full) | padat | ✅/⚠️ |
| HP-06 | Lengkap (agent F) | ada | ✅ |
| HR-07 | Sebagian (grep Payroll) | ada | ✅/⚠️ |
| KPI-08 | Sebagian (grep PenilaianKPI) | ada | ✅/⚠️ |
| MK-09 | Lengkap (agent I) | padat | ✅ |
| INT-10 | Lengkap (agent J) | tidak ada | ✅/⚠️ |

## 5. Temuan Lintas-Modul (Gap & Konflik Utama)
1. **Otorisasi tidak terpusat** — AuthFilter hanya cek login; endpoint tanpa filter `auth` dapat diakses tanpa login (mis. `insert_produk`, `template_tugas`, `status_service/{id}`, `region/*`, `penyusutan_cronjob`, `api/*`).
2. **CRUD kategori (master data) putus** — route dikomentari (`Routes.php:89-91`).
3. **KPI Piutang selalu null** — `pembayaran_piutang` tanpa `tanggal_bayar` → score `gap_no_payment_date`.
4. **Dua mekanisme guard saldo H/P paralel** — `kurangiSisaJikaCukup` (Model) vs transaksi+`FOR UPDATE` (Service).
5. **Dua wajah KPI Marketing (43)** — `MarketingKpiService` (legacy) vs `DigitalMarketingKpiService` (baru).
6. **Status Kommo vs UI tidak selaras** (`NEW/FOLLOW_UP/WON/LOST` vs `PROSPEK/CLOSING/BATAL`); webhook tanpa verifikasi signature; secret webhook kosong.
7. **Stok saat penjualan**: potongan stok tidak terlihat eksplisit pada `Penjualan::insert_penjualan`.
8. **Route duplikat/ambigu**: `api/crm/lead-list` dua kali; `insert_produk` → `Pembelian::insert_produk`.
9. **Tidak ada test untuk master data, auth, Kommo/bridge**.
10. **Import Excel memakai SQL mentah + `addslashes`** pada master data.

## 6. Rekomendasi Prioritas
1. (Tinggi) Terapkan gerbang otorisasi terpusat (filter role/menu) atau audit endpoint tanpa auth.
2. (Tinggi) Perbaiki KPI Piutang (tambah `tanggal_bayar`).
3. (Sedang) Konsolidasi guard saldo H/P; pertegas makna "lead" multi-sumber.
4. (Sedang) Amankan webhook Kommo (signature + secret wajib) & selaraskan status.
5. (Sedang) Selesaikan CRUD kategori & argumen `getLastBarangByKategori`; tambahkan unique constraint kode barang.
6. (Rendah) Hapus sisa legacy (`performance_metrics`, route duplikat, hard-delete nama handphone inkonsisten).

## 7. Provenansi & Keterbatasan
- Analisis per modul: `docs/prd/_analysis/`. PRD per modul: `docs/prd/modul/`.
- Dokumen ini adalah **PRD induk berdasarkan implementasi aktual** — bukan spesifikasi target.
- Keterbatasan: kode dibaca sebagian untuk modul yang ditandai ⚠️; belum diverifikasi terhadap data runtime. Test yang lulus tidak membuktikan seluruh business rules benar.
