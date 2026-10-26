# Analisis Lintas-Modul (Cross-Cutting) — ERP

Status: disusun dari kode yang benar-benar dibaca. Metode: verifikasi langsung terhadap file sumber; inferensi diberi tanda [INFERENSI]; hal yang belum diverifikasi diberi tanda [BELUM TERVERIFIKASI].

## 1. Stack & Struktur
- Framework: CodeIgniter 4 (PHP 8.x), MySQL (produksi) / SQLite3 (test). Sumber: `composer.json`, `app/Config/Database.php`.
- Frontend: Bootstrap 5, Iconify Solar Icons, Vanilla JS, jQuery/DataTables (server-side) di banyak tabel. Sumber: `PRODUCT.md`, template.
- Pola arsitektur: Controller << Model + Library + Service. Business logic berat berpindah ke `app/Services/*` (Finance, Kpi, Payroll, Marketing, Konten, SocialMedia, Incentive, Kommo).
- Volume: 88 controller, 127 model, 86 service, 157 migrasi, 219 view, 88 file test.

## 2. Autentikasi & Otorisasi (VERIFIKASI)
- Filter global `AuthFilter` (`app/Filters/AuthFilter.php`): hanya memeriksa `session()->get('logged_in') === true`. Bila tidak, redirect ke `/login`. **Tidak ada cek menu/role global di filter.**
- Login (`app/Controllers/Auth.php::proses_login`): username dicocokkan ke kolom `NOID` / `KTP` / `EMAIL` (`ModelAuth::cekUsername`). Password dicek dengan `password_verify` terhadap `akun.PASSWORD` (hash bcrypt).
- Setelah login, session diisi: `ID_AKUN, NAMA, NOID, KTP, EMAIL, ID_JABATAN, NAMA_JABATAN, ID_UNIT, NAMA_UNIT, logged_in=true`. `ID_UNIT` di-decode JSON dan diambil elemen pertama bila array.
- Peran (role) disimpan di kolom `akun.ROLES` (JSON array idmenu) dan `jabatan.ROLES_JABATAN` (JSON). Menu yang boleh ditampilkan diambil lewat `Auth::getRoles` (query tabel `menu`).
- **Konsekuensi penting [VERIFIKASI]**: otorisasi berbasis hak akses menu/role TIDAK diterapkan secara global pada seluruh route. Banyak controller melakukan cek role/unit secara manual di dalam method (contoh: `Kas_Keluar::bebasPilihUnit`, `unitFilterTerpilih` memakai `ID_JABATAN` dan `FinanceScopeService`). Ini berarti security bergantung pada disiplin per-controller, bukan satu gerbang terpusat.
- Logout (`Auth::proses_logout`): `session()->destroy()`.

## 3. Konfigurasi Keamanan CSRF (VERIFIKASI)
- CSRF global dimatikan (`app/Config/Filters.php`). Token CSRF diaktifkan **per-route** dengan filter `['auth','csrf']` pada route yang menulis ledger/uang (contoh: `tutupkasir/tutup`, `kas_bank/*/save`, `stok_opname/*`, `retur_barang_rusak/*`). Disengaja; dokumentasi inline di `Routes.php`.
- Selain CSRF, beberapa form memakai `submit_token` (guard manual) di modul KasBank.

## 4. Transaksi Database & Atomisitas (VERIFIKASI)
- Pola standar: `$db->transStart(); try { ... $db->transComplete(); } catch (...) { $db->transRollback(); }`, lalu cek `transStatus()`. Contoh kuat: `Kas_Keluar::insert_kas_keluar`, `update_kas_keluar`, `delete_kas_keluar`.
- **Aturan anti partial-success [VERIFIKASI]**: bila posting ke ledger `transaksi_kas_bank` gagal (`PostingLedgerException`), seluruh batch dibatalkan (rollback). Ini sengaja untuk mencegah "sumber tersimpan tapi ledger kosong".
- Idempotensi posting ledger dijaga oleh guard `sumber_tipe + sumber_id + akun_kas_bank_id + arah` (`ModeKasBank::insertIdempotent`, `sudahTerposting`).
- Koneksi test: guard fail-closed di `Database::tolakTestYangTunjukProduksi()` menolak bila group `tests` menunjuk database produksi.

## 5. Scope Unit & Entitlement (VERIFIKASI)
- Ada layanan scope: `FinanceScopeService`, `KasBankScopeService`, `DirekturUnitScopeService`, `ContentScopeService`, `SocialMediaScopeService`.
- Peran yang bebas memilih unit (`FinanceScopeService::inputRoles()`) = ROOT/Finance (ID_JABATAN tertentu; lihat implementasi). Role lain dibatasi ke unit sendiri / unit binaan (SPV via tabel `spv_units`).
- Konsep "rekening shared" dan "Finance/HO account" (`is_finance_ho`): rekening Finance/HO boleh dipakai unit mana pun sebagai destination (ARAH_MASUK), tapi hanya boleh ditarik oleh ROOT/Finance (ARAH_KELUAR). Bukti: `ModeKasBank::resolveAkunDetail`, `KasBankScopeService::canUseAsDestination/canUseAsSource`.
- Konsep cut-off & scope legacy: transaksi sebelum tanggal cut-off diberi `scope=LEGACY` + `cutoff_closed_*`; transaksi baru `scope=ACTIVE`. Tanggal cut-off dari `FinanceScopeService::cutoffDate()`.

## 6. Database (VERIFIKASI parsial)
- Migrasi tersebar mulai `2025-08-26` s/d `2026-10-24`; nama eksplisit menggambarkan keputusan bisnis (mis. `AddFinanceHoFlagToAkunKasBank`, `StokBarangHanyaMutasiDiterima`, `FixMutasiStatusComment`).
- Tabel inti yang terverifikasi: `akun`, `jabatan`, `unit`, `menu`, `gedung`, `pangkat`, `kas_masuk`, `kas_keluar`, `kategori_kas`, `no_akun`, `jurnal`, `transaksi_kas_bank`, `akun_kas_bank`, `bank`, `hutang_piutang`, `pembayaran_hutang`, `pembayaran_piutang`, `mutasi_stok`, `detail_mutasi`, `stok_barang`, `stok_opname*`, `penjualan`, `detail_penjualan`, `pembelian`, `detail_pembelian`, `service`, `proses_service`, `barang_rusak`, `asset`, `payroll`, `penilaian*`, `kpi*`, `content*`, `marketing*`, `social_media*`.
- Detail skema lengkap per tabel tidak diinventarisasi menyeluruh di dokumen ini (lihat migrasi & model per modul).

## 7. Pengujian (VERIFIKASI)
- 88 file test (PHPUnit). Konfigurasi: `phpunit.xml`, `phpunit.erp_finance.xml`, `phpunit.hard.xml`, `phpunit.tk.xml`.
- Area dengan cakupan test paling padat: KasBank, Tutup Kasir, Hutang/Piutang, KPI/Finance, Service & DP, StokOpname, Mutasi, Garansi, SocialMedia.
- **Keterbatasan [VERIFIKASI]**: test yang lulus membuktikan perilaku spesifik yang diuji (banyak test fokus pada khusus/rendering/plugin), BUKAN membuktikan seluruh business rules benar. Banyak modul master data (produk, pelanggan, pegawai, dsb.) tidak memiliki test otomatis.

## 8. Notifikasi Global (VERIFIKASI)
- `BaseController::initController` menghitung untuk layout: stok minimum, service yang sedang diproses, service yang bisa diambil, service expired (via `ModelService::getExpiredService` dsb.) dan `akun_service` (akun login + jabatan). Semua dipakai untuk topbar/notifikasi.

## 9. Integrasi Eksternal (VERIFIKASI)
- Kommo CRM: webhook (`KommoWebhook::handle`) dan API read-only dengan HMAC signature (`CrmLeadBridge`, `CustomerChatInvoiceBridge`). Tidak memakai session ERP.
- Social Media: scraper eksternal (BrightData) via `app/Services/SocialMedia/*`.
- Export: PhpSpreadsheet (XLSX), mPDF (PDF).

## 10. Konflik / Gap Lintas-Modul yang Teridentifikasi
1. [VERIFIKASI] Otorisasi berbasis role tidak terpusat: AuthFilter hanya cek login. Konsekuensi: beberapa endpoint (mis. route tanpa `['filter'=>'auth']`, seperti `insert_produk`, `template_tugas`, `status_service/(:num)`, `penyusutan_cronjob`, `api/*`) bisa diakses tanpa autentikasi. Perlu inventarisasi route tanpa filter.
2. [INFERENSI] `Auth::getRoles` hanya dipakai untuk mengisi menu/tampilan; tidak menjamin blokade akses backend.
3. [VERIFIKASI] Ada route duplikat/ambigu: `penjualan/search_by_hp` vs `pelanggan/search`; `api/crm/lead-list` didefinisikan dua kali (identik).
4. [VERIFIKASI] Route `insert_produk` menunjuk ke `Pembelian::insert_produk` (bukan `Produk::insert_produk`) — potensi konflik naming (lihat modul).
5. [BELUM TERVERIFIKASI] Konsistensi penggunaan `session('ID_UNIT')` vs `ID_GEDUNG` lintas controller.
