# PRD Induk — Sistem ERP (CodeIgniter 4)

| | |
|---|---|
| Repository | `/home/syro/Projects/ERP` |
| Tanggal analisis | 2026-10-26 |
| Metode | Analisis kode baca-saja (read-only). Tidak ada perubahan source/DB/config. |
| Status verifikasi | ✅ Fakta terverifikasi dari kode · 🔶 Inferensi · ⚠️ Ketidakpastian/belum diverifikasi |
| Laporan audit | [`audit/PRD_AUDIT_REPORT.md`](audit/PRD_AUDIT_REPORT.md) |

---

## 1. Ringkasan Sistem
Sistem ERP operasional toko/cabang berbasis **CodeIgniter 4** (PHP 8, MySQL/MariaDB; database test MySQLi `erp_test`), Bootstrap 5, Vanilla JS, jQuery/DataTables. Cakupan: penjualan (POS), pembelian & persediaan, service & garansi, keuangan (kas/bank/jurnal/laporan), hutang piutang terpusat, HRD & payroll, aset, KPI & penilaian, marketing digital/konten/social media, dan integrasi Kommo/CRM.

Baseline repository yang diverifikasi: **88 controller · 127 model · 86 service · 151 migration · 219 view · 87 file `*Test.php`**. Framework `codeigniter4/framework v4.4.8`; PHP audit `8.5.12`; PHPUnit `9.6.23`. Konfigurasi test aktif: `phpunit.xml` → MySQLi, database `erp_test`, prefix `db_`; **bukan SQLite3**.

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
- **Auth bukan authz.** Banyak route hanya memakai `['filter'=>'auth']`; sejumlah route tulis bahkan tidak memakai filter `auth` (lihat `audit/PRD_AUDIT_REPORT.md` X-05). CRUD master (`Pegawai`, `Unit`, `Produk`) juga tidak memiliki guard role.
- Login: `ModelAuth::cekUsername` (NOID|KTP|EMAIL) + `password_verify`; sesi berisi `ID_AKUN, ID_JABATAN, ID_UNIT, NAMA_UNIT, logged_in`.

### 3.2 Transaksi & Atomisitas
- Pola `transStart/transComplete/transRollback` dipakai pada beberapa alur, dan modul keuangan tertentu memakai `PostingLedgerException` untuk membatalkan batch bila ledger gagal.
- **Tidak semua operasi multi-tabel atomik.** Insert kas masuk/keluar dibungkus transaksi, tetapi update/delete tidak selalu menyinkronkan jurnal; retur supplier/customer dan insert aset tidak dibungkus transaksi. Lihat X-07, PI-04, FN-06.
- Idempotensi posting ledger dijaga unique `(sumber_tipe,sumber_id,akun,arah)` pada jalur yang memakai `ModeKasBank`; ini tidak otomatis menjamin jurnal sumber ikut sinkron.

### 3.3 Scope Unit & Entitlement
- Role 0/1/2/34 bebas lintas unit; role lain dibatasi ke unit sendiri / `spv_units` (SPV) pada service scope yang tersedia.
- Rekening shared / Finance-HO (`is_finance_ho`) punya aturan source/destination yang lebih ketat di `KasBankScopeService`.
- **Scope tulis belum konsisten.** `Kas_Masuk`, `Kas_Keluar`, `Asset`, dan retur mengambil unit dari `POST`; `FinanceScopeService` baru dipakai untuk filter/daftar. Lihat X-06.
- Cut-off `FinanceScopeService::cutoffDate()=2026-10-05`; transaksi legacy ber-scope `LEGACY`.

### 3.4 CSRF
- Global CSRF dimatikan (`Filters.php`).
- `csrf` hanya aktif pada route yang eksplisit memakainya: refund DP, sebagian route `StatusGaransi`, `StokOpname`, `ReturBarangRusak`, setor/tarik tunai `KasBank`, dan `tutupkasir/tutup`. Dari 254 route `POST`, mayoritas transaksi (kas masuk/keluar, pembelian, penjualan, aset, transfer internal) **tidak** memakai CSRF. Lihat X-04.

### 3.5 Audit & Anti-Double-Counting
- Hutang/piutang terpusat memiliki guard double counting pada jalur authoritative, tetapi projection dari pembelian memakai `total_bayar` (bukan `total_transaksi`) sehingga total/sisa dapat salah (PI-05/HP-01).
- Detail penjualan bundle menulis `sub_total` penuh untuk setiap komponen; laporan yang menjumlahkan `detail_penjualan.sub_total` dapat menggandakan omzet (SL-03).
- KPI memakai sumber achievement (rekap/lead/snapshot) pada engine aktif `KpiCalculationService`; sebagian istilah “omzet” sebenarnya berarti gross profit (KPI-06).

## 4. Matriks Cakupan Verifikasi

| Modul | Cakupan analisis | Test ada | Status |
|---|---|---|---|
| MD-01 | Lengkap (agent A + audit ini) | hampir tidak ada | ✅ + banyak gap |
| PJ-02 | Sebagian (baca Penjualan full) | view test saja | ✅/⚠️ |
| PB-03 | Sebagian (baca Pembelian full) | opname/mutasi/retur | ✅/⚠️ |
| SV-04 | Sebagian (grep Service) | padat | ✅/⚠️ |
| KEU-05 | Sebagian (Kas_Keluar & ModeKasBank full) | padat | ✅/⚠️ |
| HP-06 | Lengkap (agent F) | ada | ✅ |
| HR-07 | Sebagian (grep Payroll) | ada | ✅/⚠️ |
| KPI-08 | Sebagian (grep PenilaianKPI) | ada | ✅/⚠️ |
| MK-09 | Lengkap (agent I) | padat | ✅ |
| INT-10 | Lengkap (agent J) | tidak ada | ✅/⚠️ |

Status ✅ di tabel ini berarti “dokumen sudah diperiksa”, **bukan** “fitur aman/berfungsi”.

## 5. Temuan Lintas-Modul (Gap & Konflik Utama)
1. **Otorisasi tidak terpusat** — `AuthFilter` hanya cek login; endpoint tanpa filter `auth` dan controller tanpa guard role adalah risiko utama (X-03, X-05, MD-05).
2. **CSRF tidak seluas klaim** — hanya sebagian route tulis yang memakai `csrf` (X-04, FN-02).
3. **Jurnal/ledger dapat divergen** — update/hapus kas tidak selalu menghapus/menulis ulang jurnal (X-07, FN-03, FN-04).
4. **Projection hutang supplier salah total** — `syncFromPembelian` memakai `total_bayar` (PI-05, HP-01).
5. **KPI Piutang selalu null** — `pembayaran_piutang` tanpa `tanggal_bayar` (HP-03).
6. **Payroll/KPI membocorkan data** — view payroll lintas unit; `slip_gaji`, `gaji`, dan `attendance_save` kurang guard (PR-01, PR-06, PR-07, KPI-04).
7. **Penjualan kredit tidak membentuk piutang pelanggan** — hanya `keterangan='Belum Lunas'`; `Piutang` legacy adalah piutang pegawai (SL-02).
8. **Bundle menggandakan revenue** — `detail_penjualan.sub_total` penuh per komponen (SL-03).
9. **Stok opname tidak mengoreksi stok akhir** — migration 2026-10-07 menghapus komponen opname; PRD/komentar masih menyatakan sebaliknya (PI-02).
10. **Beberapa route/fitur mati atau salah target** — `service_kerusakan`/`service_sparepart` tanpa method; `delete_jabatan` tanpa method; kategori master diarahkan ke `Kategori_Kas`; `getLastBarangByKategori` kurang argumen (MD-01, MD-02, MD-04, SV-02).
11. **Route duplikat/typo** — `api/crm/lead-list` dua kali; `antar-unit`/`antar_unit`; filter `fileter` pada `laba_service`/`fee_service` (IN-02, FN-08).
12. **Webhook Kommo** — secret opsional; belum verifikasi `X-Hub-Signature`; status Kommo vs UI tidak selaras (X-10, PRD Modul 10).
13. **Tidak ada test untuk master data, auth/CSRF, Kommo/bridge, dan alur kritis lain** (X-08).

## 6. Rekomendasi Prioritas
1. (Kritis) Tutup route tanpa auth dan tambahkan guard role/unit pada master, payroll, KPI, service, dan aset.
2. (Kritis) Perbaiki integritas keuangan: projection hutang, sinkronisasi jurnal kas, `Piutang::bayar_piutang`, dan CSRF transaksi.
3. (Kritis) Batasi akses payroll/slip gaji/absensi dan hentikan `bayar` ulang yang menimpa potongan kasbon.
4. (Tinggi) Perbaiki persediaan: stok minimum, keputusan opname, enum `batal_draft`, retur atomik, bundle revenue.
5. (Tinggi) Perbaiki service: hapus invoice yatim saat cancel, route mati, DP kedua.
6. (Sedang) Perbaiki master data: kategori, produk, `$THIS`, `delete_jabatan`.
7. (Sedang) Integrasi/marketing: filter soft-delete CRM, CSRF, fallback target sosial, dokumentasi webhook.
8. (Rendah) Hapus sisa legacy/route duplikat dan tambahkan unique constraint invoice/kode barang.

## 7. Provenansi & Keterbatasan
- Laporan rinci: [`audit/PRD_AUDIT_REPORT.md`](audit/PRD_AUDIT_REPORT.md).
- Analisis per modul: `docs/prd/_analysis/`. PRD per modul: `docs/prd/modul/`.
- Dokumen ini adalah **PRD induk berdasarkan implementasi aktual** — bukan spesifikasi target.
- Keterbatasan: kode dibaca sebagian untuk modul yang ditandai ⚠️; belum diverifikasi terhadap data runtime; **tidak ada test yang dijalankan** pada audit ini. Test yang lulus tidak membuktikan seluruh business rules benar.
