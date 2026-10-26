# Laporan Audit PRD vs Implementasi — ERP iClear

| | |
|---|---|
| Tanggal | 2026-10-26 |
| Ruang lingkup | `docs/prd/PRD_MASTER.md`, 10 PRD modul, seluruh `docs/prd/_analysis/*`, route/controller/model/service/view/migration/test yang relevan |
| Metode | Audit kode baca-saja (read-only), tanpa perubahan source/DB/config dan tanpa menjalankan test |
| Sumber fakta | Kode, route, migration, dan test di repository. PRD diperlakukan sebagai dokumen yang diuji, bukan bukti |
| Baseline | CodeIgniter `4.4.8`; PHP `8.5.12`; PHPUnit `9.6.23`; 88 controller; 127 model; 86 service; 151 migration; 219 view; 87 file `*Test.php` |
| Database test aktif | MySQLi `erp_test`, prefix `db_` (`phpunit.xml`) |

## 1. Ringkasan Eksekutif

Audit menemukan **61 temuan**: 7 `STALE`, 21 `CONTRADICTION`, 20 `MISSING`, 9 `UNIMPLEMENTED`, 3 `UNCERTAIN`, dan 1 `TEST_GAP` utama. Temuan paling berisiko:

1. **Otorisasi tidak terpusat dan beberapa endpoint sensitif terbuka.** `AuthFilter` hanya memeriksa `logged_in`; sejumlah route tulis tidak memakai filter `auth`, dan banyak controller tidak memeriksa role/unit.
2. **CSRF tidak seluas yang diklaim PRD.** Dari 254 route `POST`, hanya kelompok route tertentu yang memakai `csrf`; kas masuk/keluar, pembelian, penjualan, aset, dan transfer internal KasBank tidak memakainya.
3. **Jurnal dan ledger dapat berbeda.** Update `kas_masuk`/`kas_keluar` memposting ulang ledger tanpa memperbarui jurnal; delete `kas_masuk` tidak menghapus jurnal.
4. **Pembelian membentuk projection hutang dengan total yang salah.** `syncFromPembelian()` memakai `pembelian.total_bayar` (jumlah yang sudah dibayar), bukan `total_transaksi`.
5. **Payroll dan KPI membocorkan data gaji/absensi.** Halaman payroll menampilkan semua unit; `slip_gaji`, `gaji`, dan `attendance_save` tidak memeriksa hak pengguna/evaluator.
6. **Penjualan dan service dapat meninggalkan data keuangan/stok yang tidak konsisten.** Penjualan kredit tidak membentuk piutang pelanggan; bundle menggandakan `sub_total`; pembatalan service tidak menghapus invoice sparepart.
7. **Beberapa dokumentasi PRD sudah usang secara faktual**, terutama jumlah migration/test, konfigurasi SQLite, dan mesin KPI yang aktif.

Temuan yang tidak dapat dipastikan dari repository (status database runtime, isi data produksi, hasil test) ditandai `UNCERTAIN` dan tidak dijadikan klaim final.

## 2. Metode, Batasan, dan Legenda

### 2.1 Yang diperiksa

- Route dan filter: `app/Config/Routes.php`, `app/Config/Filters.php`, `app/Filters/AuthFilter.php`.
- Implementasi domain: controller, model, service, view, dan JS yang memanggil route terkait.
- Schema dan aturan data: migration, terutama view `stok_barang`, audit stok opname, hutang-piutang, payroll, KPI.
- Test statis: nama file, isi test yang relevan, dan kelas yang diuji.
- Dokumen: `PRD_MASTER.md`, 10 PRD modul, `_analysis/*`, serta `docs/architecture/kpi-engine.md` karena bertentangan langsung dengan PRD 08.

### 2.2 Batasan

- **Tidak ada test yang dijalankan.** Perintah test berpotensi menyentuh database; hasil runtime tidak dapat diklaim dari audit ini.
- Tidak dilakukan query ke database produksi/`erp_local`.
- Perilaku database yang bergantung pada schema aktual (mis. view non-updatable, constraint yang tidak ada di migration) ditandai `UNCERTAIN` bila tidak dapat dibuktikan dari file.
- Konflik aturan bisnis tidak otomatis “dimenangkan” oleh kode. Kode adalah bukti perilaku saat ini; keputusan bisnis tetap memerlukan konfirmasi user.

### 2.3 Legenda status

| Status | Arti |
|---|---|
| `SESUAI` | Dokumen sesuai dengan kode |
| `STALE` | Fakta dokumen sudah tidak benar (angka, versi, path, konfigurasi) |
| `CONTRADICTION` | Dokumen menyatakan perilaku yang bertentangan dengan kode |
| `MISSING` | Perilaku penting ada di kode tetapi tidak didokumentasikan |
| `UNIMPLEMENTED` | Dokumen mengklaim fitur yang tidak ada/tidak berfungsi |
| `UNCERTAIN` | Perlu data runtime atau keputusan bisnis |
| `TEST_GAP` | Tidak ada test yang mengunci perilaku berisiko |

---

## 3. Temuan Lintas-Modul

### X-01 — Baseline migration/test dan konfigurasi database test salah

- **Status:** `STALE`
- **Lokasi PRD:** `PRD_MASTER.md` §1; `_analysis/00_cross_cutting.md` §1 dan §7.
- **Bukti:** Repository berisi **151** migration (`find app/Database/Migrations`), **87** file `*Test.php`, bukan 157/88. `phpunit.xml` memakai `database.tests.DBDriver=MySQLi`, database `erp_test`, prefix `db_`. Tidak ada konfigurasi SQLite3 aktif.
- **Dampak:** Pembaca salah memahami cakupan test dan berisiko menjalankan asumsi database yang keliru.
- **Rekomendasi:** Perbarui angka dan hapus klaim SQLite3. Sebutkan lokasi konfigurasi aktif.
- **Risiko:** Angka dapat berubah bila file ditambah; cantumkan tanggal baseline.

### X-02 — Indeks analisis tidak lengkap

- **Status:** `STALE`
- **Lokasi PRD:** `docs/prd/README.md` §Struktur.
- **Bukti:** `_analysis/` berisi `Group_C_Pembelian_Persediaan.md` dan `Group_D_Service_Garansi.md`, tetapi keduanya tidak tercantum. Laporan audit ini juga belum terindeks.
- **Dampak:** Pembaca melewatkan analisis inventory/service.
- **Rekomendasi:** Tambahkan kedua file dan tautan `audit/PRD_AUDIT_REPORT.md`.
- **Risiko:** Rendah.

### X-03 — `AuthFilter` adalah autentikasi, bukan otorisasi

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MASTER.md` §3.1; beberapa PRD modul yang menyebut “aktor” seolah-olah role ditegakkan.
- **Bukti:** `app/Filters/AuthFilter.php::before()` hanya memeriksa `session()->get('logged_in') === true`. Pemeriksaan role/menu yang lama dikomentari. Banyak controller tidak memanggil guard role.
- **Dampak:** User login dengan jabatan apa pun dapat mengakses URL yang tidak dimaksudkan bila controller tidak menjaga sendiri.
- **Rekomendasi:** Pertahankan pernyataan bahwa otorisasi bersifat per-controller; tambahkan filter role/menu terpusat atau audit endpoint.
- **Risiko:** Tinggi; perubahan filter global dapat memutus alur yang ada.

### X-04 — Cakupan CSRF jauh lebih sempit daripada klaim PRD

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MASTER.md` §3.4; `PRD_MODUL_05` §4.4, §5.9, §8.
- **Bukti:** `app/Config/Filters.php` mematikan CSRF global (`// 'csrf'`). `Routes.php` memiliki 254 route `POST`; hanya kelompok tertentu yang eksplisit memakai `csrf`: refund DP, beberapa route `StatusGaransi`, `StokOpname`, `ReturBarangRusak`, setor/tarik `KasBank`, dan `tutupkasir/tutup`. `insert_kas_masuk`, `insert_kas_keluar`, `insert_pembelian`, `insert_penjualan`, `insert_asset`, transfer internal/antar-unit, dan `insert_retur_suplier/customer` tidak memakai CSRF.
- **Dampak:** Klaim “CSRF aktif untuk semua penulisan ledger/kas” tidak benar; endpoint berisiko rentan CSRF.
- **Rekomendasi:** Koreksi PRD; buat daftar route ber-CSRF yang dapat diaudit. Jangan mengaktifkan global CSRF tanpa menyiapkan token semua form.
- **Risiko:** Tinggi bila dokumentasi dipakai sebagai dasar penilaian keamanan.

### X-05 — Banyak route sensitif tidak memakai filter `auth`

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MASTER.md` §5.1 (masih menyebut beberapa contoh tanpa daftar lengkap).
- **Bukti:** `Routes.php`: `insert_produk` (root, untuk halaman pembelian), `region/*`, `status_service/{id}`, `template_tugas`, `jadwalmasuk`, `tugas/updateStatus`, `penyusutan_cronjob`, `kategori_kas` (GET), `kas_masuk` (GET), `penilaian/get_template_by_jabatan`; `laba_service`/`fee_service` memakai typo `fileter`.
- **Dampak:** Data pelanggan/service, template, jadwal, dan aksi tulis tertentu dapat diakses tanpa login atau dengan filter yang tidak sesuai.
- **Rekomendasi:** Inventarisasi seluruh route tanpa `auth`; bedakan endpoint publik yang memang disengaja (login, webhook) dari yang tidak.
- **Risiko:** `status_service` memuat PII; `penyusutan_cronjob` menulis aset; `tugas/updateStatus` menulis data.

### X-06 — Scope unit pada operasi tulis masih mempercayai `POST`

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_05` §5.5, §6.
- **Bukti:** `Kas_Masuk::insert_kas_Masuk()` memakai `$this->request->getPost('unit_idunit')` untuk `kas_masuk.idunit`; `Kas_Keluar::insert_kas_keluar()` memakai `unit_idunit` dari POST; `Asset::insert_asset()`/`update_asset()` memakai `unit` dari POST; `Retur_Suplier::insert()`/`Retur_Customer::insert()` memakai `unit_idunit` per item. `FinanceScopeService::unitFilterTerpilih()` hanya dipakai untuk filter baca/export.
- **Dampak:** Role terbatas dapat menulis transaksi ke unit lain bila UI dimanipulasi.
- **Rekomendasi:** Validasi unit tulis di server dengan `FinanceScopeService`/`KasBankScopeService`; jangan gunakan unit POST untuk role terbatas.
- **Risiko:** Tinggi untuk integritas laporan unit dan ledger.

### X-07 — Jurnal tidak selalu disinkronkan saat sumber diubah/dihapus

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_05` §4.1 butir 5, §5.6, §6; `PRD_MODUL_06` §5.7.
- **Bukti:** `Kas_Masuk::update_kas_Masuk()` memperbarui sumber dan ledger, tetapi tidak menghapus/menulis ulang `jurnal`; `delete_kas_Masuk()` menghapus ledger dan sumber tanpa menghapus jurnal. `Kas_Keluar::update_kas_keluar()` juga hanya memposting ulang ledger, tidak jurnal; `delete_kas_keluar()` baru menghapus jurnal.
- **Dampak:** Laporan berbasis jurnal dapat berbeda dengan sumber/ledger setelah edit/hapus.
- **Rekomendasi:** Dalam transaksi yang sama, hapus jurnal lama (`hapusByReferensi`) lalu posting ulang; untuk delete, hapus jurnal sebelum/bersama sumber.
- **Risiko:** Tinggi; jurnal adalah dasar laba-rugi dan neraca.

### X-08 — Gap test pada alur berisiko

- **Status:** `TEST_GAP`
- **Lokasi PRD:** `PRD_MASTER.md` §4; seluruh PRD modul bagian test.
- **Bukti:** Ada 87 file test, tetapi tidak ditemukan test untuk: CSRF/route tanpa auth, CRUD kategori/produk master, projection hutang dari pembelian, bundle revenue, cancel service yang menghapus invoice, scope tulis Kas_Masuk/Keluar, edit/hapus jurnal kas, Payroll view/`bayar` ulang, `slip_gaji`/`attendance_save`, target sosial fallback, dan `CrmLeadBridge` filter `kommo_deleted_at`.
- **Dampak:** Regresi pada alur di atas tidak tertangkap.
- **Rekomendasi:** Prioritaskan test regresi untuk temuan `Tinggi` di laporan ini.
- **Risiko:** Tinggi; tanpa test, perbaikan berikutnya rawan.

### X-09 — Dokumen arsitektur KPI menyebut engine aktif yang salah

- **Status:** `STALE`
- **Lokasi PRD:** `docs/architecture/kpi-engine.md` (dirujuk `PRD_MODUL_08` §5.7).
- **Bukti:** Dokumen menyatakan `KpiCalculationService` “belum dipakai periode berjalan”, sedangkan kode memakai `new KpiCalculationService()` di `PenilaianKPI` (beberapa method), `PayrollGenerator`, `HoSalaryDistributionService`, `UnitSalaryCalculationService`, dan `ManagerKpiService`. `LegacyKpiCalculationService` tidak dipanggil dari controller aktif.
- **Dampak:** Dokumen pendukung menyesatkan pembaca PRD 08.
- **Rekomendasi:** Tandai bagian historis dan tambahkan status implementasi aktual.
- **Risiko:** Sedang; menyangkut pemahaman formula gaji/KPI.

### X-10 — Webhook Kommo masih bergantung pada secret opsional

- **Status:** `UNCERTAIN` (keamanan)
- **Lokasi PRD:** `PRD_MODUL_10` §5.4–5.5.
- **Bukti:** `KommoWebhook::authorized()` mengembalikan `true` bila `KOM_WEBHOOK_SECRET` kosong; tidak memverifikasi `X-Hub-Signature` HMAC. Ini sudah dicatat PRD 10, tetapi belum ada bukti konfigurasi produksi.
- **Dampak:** Bila secret kosong, siapa pun dapat mengirim payload webhook.
- **Rekomendasi:** Wajibkan secret/HMAC di produksi dan tambahkan test signature.
- **Risiko:** Tinggi; status konfigurasi nyata tidak diketahui dari repository.

---

## 4. Temuan per Modul

### 4.1 PRD Modul 01 — Master Data

#### MD-01 — Form kategori mengarah ke controller `Kategori_Kas`, field tidak cocok

- **Status:** `UNIMPLEMENTED`
- **Lokasi PRD:** `PRD_MODUL_01` §4.3, §5.5, §6.
- **Bukti:** `app/Views/datamaster/kategori.php` mengirim form ke `update_kategori`, `insert_kategori`, `delete_kategori` dengan field `idnya`, `nama_kategori`, `idkategori`. Route `Routes.php:416-419` mengarah ke `Kategori_Kas::insert_kategori/update_kategori/delete_kategori`, yang membaca `idkategori_kas`, `kategori`, `kode_template_jurnal`, `jenis_kas`. `Kategori` sendiri tidak memiliki route CRUD yang aktif.
- **Dampak:** CRUD kategori master tidak menyimpan data yang benar; dapat menulis ke tabel kategori kas atau gagal.
- **Rekomendasi:** Arahkan form ke `Kategori` dan samakan field, atau perbaiki route/controller yang dituju.
- **Risiko:** Tinggi untuk data master; perubahan route harus menelusuri pemanggil.

#### MD-02 — `Produk::insert_produk` dan `update_produk` memanggil method dengan argumen kurang

- **Status:** `UNIMPLEMENTED`
- **Lokasi PRD:** `PRD_MODUL_01` §4.3, §5.5.
- **Bukti:** `ModelBarang::getLastBarangByKategori($idkategori, $kode_kategori)` membutuhkan dua argumen (`ModelBarang.php:210`). `Produk::insert_produk()` memanggilnya dengan satu argumen (`Produk.php:71`); `Produk::update_produk()` juga satu argumen saat kategori berubah (`Produk.php:138`); `Produk::import_produk()` sama (`Produk.php:276`). PHP 8 akan melempar `ArgumentCountError`.
- **Dampak:** Pembuatan produk dan perubahan kategori produk gagal.
- **Rekomendasi:** Kirim `$kode_kategori` sebagai argumen kedua; tambahkan test pembuatan kode barang.
- **Risiko:** Tinggi; alur master produk tidak berfungsi.

#### MD-03 — `Pegawai::update` memakai variabel `$THIS`

- **Status:** `UNIMPLEMENTED`
- **Lokasi PRD:** `PRD_MODUL_01` §4.2.
- **Bukti:** `app/Controllers/Pegawai.php:205` menulis `$THIS->AuthModel->update(...)` di cabang `jenis_pegawai == 100`; variabel `$THIS` tidak didefinisikan (PHP case-sensitive).
- **Dampak:** Update pegawai yang menonaktifkan status dapat berhenti dengan fatal error setelah update utama.
- **Rekomendasi:** Ganti dengan `$this`; tambahkan test update status pegawai.
- **Risiko:** Sedang.

#### MD-04 — Route `delete_jabatan` menunjuk method yang tidak ada

- **Status:** `UNIMPLEMENTED`
- **Lokasi PRD:** `PRD_MODUL_01` §4.2.
- **Bukti:** `Routes.php:49` mengarah ke `Pegawai::delete_jabatan`; `Pegawai.php` hanya memiliki `insert_jabatan` dan `update_jabatan`, tidak `delete_jabatan`.
- **Dampak:** Hapus jabatan menghasilkan error/404.
- **Rekomendasi:** Implementasikan method atau hapus route/form.
- **Risiko:** Rendah–sedang.

#### MD-05 — CRUD master tidak memeriksa role

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_01` §2 (aktor) dan §6.
- **Bukti:** Route grup `pegawai`, `unit`, `produk` hanya memakai `auth`. `Pegawai::insert/update/delete/reset_password`, `Unit`, dan `Produk` tidak memeriksa `ID_JABATAN`/role. `Pegawai::update` dapat mengubah `ROLES`, `ID_JABATAN`, dan `ID_UNIT`.
- **Dampak:** Setiap user login berpotensi mengubah pegawai/role/unit/produk.
- **Rekomendasi:** Tambahkan guard role eksplisit; jangan hanya mengandalkan menu.
- **Risiko:** Kritis.

### 4.2 PRD Modul 02 — Penjualan & Piutang

#### SL-01 — Klaim “potongan stok tidak terlihat” sudah usang

- **Status:** `STALE`
- **Lokasi PRD:** `PRD_MODUL_02` §5.9.
- **Bukti:** `stok_barang` adalah view yang mengurangi `total_penjualan` dari `SUM(detail_penjualan.jumlah)` (`2026-10-23-000500_StokBarangKurangiSparepartGaransi.php:103-105`). Tidak perlu pemanggilan kurangi stok eksplisit di `Penjualan`.
- **Dampak:** Pembaca mengira stok penjualan belum tentu berkurang.
- **Rekomendasi:** Ganti dengan fakta view dan sebutkan mekanismenya.
- **Risiko:** Rendah.

#### SL-02 — Penjualan kredit tidak membentuk piutang pelanggan

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_02` §3, §4.3, §6.
- **Bukti:** `Penjualan::insert_penjualan()` hanya mengisi `keterangan='Belum Lunas'` bila `hutang != 0`; tidak memanggil `HutangPiutangService` dan tidak menulis baris piutang pelanggan. `Piutang` legacy adalah piutang pegawai (`piutang.pegawai_idpegawai`).
- **Dampak:** Penjualan kredit tidak memiliki registry umur piutang, pembayaran, atau settlement.
- **Rekomendasi:** Tegaskan apakah penjualan kredit masih didukung. Bila ya, hubungkan ke modul H/P; bila tidak, tolak di UI/server.
- **Risiko:** Tinggi; keputusan bisnis perlu konfirmasi.

#### SL-03 — Bundle menggandakan revenue di `detail_penjualan`

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_02` §5.5.
- **Bukti:** `Penjualan::insert_penjualan()` menulis setiap komponen bundle dengan `sub_total` penuh bundle dan `harga_penjualan` penuh (`Penjualan.php:327-334`). `TutupKasir` dan `ModelPenjualan` menjumlahkan `SUM(detail_penjualan.sub_total)` / `sub_total - hpp_penjualan` (`TutupKasir.php:360,610`; `ModelPenjualan.php:132`).
- **Dampak:** Omzet dan laba kotor dari laporan berbasis detail dapat berlipat sesuai jumlah komponen bundle.
- **Rekomendasi:** Alokasikan harga bundle ke komponen atau simpan nilai bundle satu kali; sesuaikan query laporan.
- **Risiko:** Tinggi; angka omzet/KPI/pajak dapat salah.

#### SL-04 — Uniqueness kode invoice tidak dijamin database

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_02` §5.1, §6.
- **Bukti:** Kode dibuat dari `SLL{unit}{Ymd}{seq4}` berdasarkan invoice terakhir hari itu; tidak ditemukan unique constraint `kode_invoice` di migration. Pembuatan invoice service juga memakai `SRV{date('YmdHis')}` tanpa random (`Service.php:496`).
- **Dampak:** Dua transaksi bersamaan dapat memperoleh kode sama.
- **Rekomendasi:** Tambahkan unique index dan tangani retry/duplicate; jangan hanya mengandalkan urutan aplikasi.
- **Risiko:** Sedang–tinggi.

#### SL-05 — Tidak ada validasi stok sebelum penjualan

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_02` §4.1.
- **Bukti:** `Penjualan::insert_penjualan()` mengambil `stok_awal` tetapi pengecekan stok terkait dikomentari (`Penjualan.php:278-279`); tidak ada penolakan bila `jumlah` melebihi `stok_akhir`.
- **Dampak:** Stok dapat menjadi negatif dan laporan persediaan tidak wajar.
- **Rekomendasi:** Validasi stok di server dalam transaksi yang sama atau dokumentasikan kebijakan stok negatif.
- **Risiko:** Sedang; perlu keputusan bisnis.

#### SL-06 — `Piutang::bayar_piutang` memakai `sisa` dari POST dan tidak memposting kas

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_02` §4.3; `PRD_MODUL_06` §5.3.
- **Bukti:** `Piutang::bayar_piutang()` mengambil `$sisa = POST['sisa']`, menyimpan `pembayaran_piutang`, lalu meng-update `piutang.sisa_hutang` dengan nilai itu; status memakai `if ($sisa >= 0) 0 else 1`, sehingga status Lunas tidak pernah tercapai untuk input non-negatif. Tidak ada jurnal/ledger kas. `syncFromPiutangLegacy()` hanya menyinkronkan projection.
- **Dampak:** Sisa piutang dapat dimanipulasi, status tidak konsisten, dan pembayaran tidak masuk kas/bank.
- **Rekomendasi:** Hitung sisa di server (`sisa - jumlah_bayar`), tolak negatif/berlebih, dan posting jurnal sesuai metode.
- **Risiko:** Tinggi; integritas keuangan.

### 4.3 PRD Modul 03 — Pembelian & Persediaan

#### PI-01 — `StokMinimum::update` menulis ke VIEW `stok_barang`

- **Status:** `UNIMPLEMENTED`
- **Lokasi PRD:** `PRD_MODUL_03` §4.2.
- **Bukti:** `ModelStokBarang` memakai `protected $table = 'stok_barang'`; `updateStokMinimum()` menjalankan `update` pada view tersebut. View `stok_barang` memakai derived table/UNION/GROUP BY (lihat `2026-10-23-000500...`), yang bukan view updatable di MySQL. `stok_minimum` sebenarnya berada di tabel `barang`.
- **Dampak:** Update stok minimum gagal atau tidak berpengaruh.
- **Rekomendasi:** Update tabel `barang` dan/atau view yang memang sederhana; tambahkan test.
- **Risiko:** Sedang; fitur peringatan stok minimum tidak berfungsi.

#### PI-02 — Finalisasi stok opname tidak lagi mengoreksi `stok_akhir`

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_03` §4.2; docstring `StokOpnameService`.
- **Bukti:** Migration `2026-10-07-000001_RemoveStokOpnameSelisihFromStokAkhir.php` menghapus komponen opname dari `stok_akhir`; view final `2026-10-23-000500...` juga menulis `0 AS stok_opname` pada cabang `stok_opname`. `StokOpnameService` masih menyatakan reopen “mengubah stok_akhir”.
- **Dampak:** Opname hanya menjadi catatan/audit; stok sistem tidak dikoreksi. Ini bisa disengaja, tetapi bertentangan dengan PRD dan komentar service.
- **Rekomendasi:** Konfirmasi aturan bisnis. Bila opname memang tidak mengoreksi stok, perbarui PRD/komentar; bila harus mengoreksi, perbaiki view/query.
- **Risiko:** Tinggi; perlu keputusan user.

#### PI-03 — `batalDraft` menulis aksi yang tidak ada di ENUM audit

- **Status:** `UNIMPLEMENTED`
- **Lokasi PRD:** `PRD_MODUL_03` §4.2.
- **Bukti:** `StokOpnameService::batalDraft()` memanggil `audit(..., 'batal_draft', ...)` (`StokOpnameService.php:710`), sedangkan `stok_opname_audit.aksi` adalah `ENUM('mulai','simpan','finalisasi','reopen')` (`2026-09-28-000001...:127`).
- **Dampak:** Pembatalan draft gagal pada insert audit (tergantung SQL mode) atau tidak tercatat.
- **Rekomendasi:** Tambahkan nilai `batal_draft` lewat migration atau gunakan aksi yang diizinkan.
- **Risiko:** Sedang.

#### PI-04 — Retur supplier/customer tidak atomik, scope item dari POST, tanpa CSRF

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_03` §4.2, §5.8.
- **Bukti:** `Retur_Suplier::insert()` dan `Retur_Customer::insert()` tidak memakai `transStart`; `unit_idunit` diambil dari item POST (`:80,96`); route hanya `auth` (`Routes.php:313,321`).
- **Dampak:** Gagal di tengah meninggalkan data parsial; user dapat mengirim unit lain; CSRF tidak dicegah.
- **Rekomendasi:** Bungkus transaksi, validasi unit server-side, tambahkan CSRF.
- **Risiko:** Tinggi untuk persediaan/keuangan.

#### PI-05 — Projection hutang pembelian memakai `total_bayar`

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_03` §5.5; `PRD_MODUL_06` §5.9.
- **Bukti:** `HutangPiutangService::syncFromPembelian()` mengisi `total` dengan `(int) $p->total_bayar`, sedangkan `total_bayar` di `Pembelian::insert()` adalah jumlah yang dibayar (`$bayar_tunai + $totalBayarBank`); total transaksi ada di `$total_harga`/`pembelian.total_transaksi`.
- **Dampak:** Untuk pembelian sebagian, `total` projection bisa lebih kecil dari `sisa`; status, aging, dan laporan hutang salah.
- **Rekomendasi:** Gunakan `total_transaksi` untuk `total`.
- **Risiko:** Tinggi; angka hutang supplier.

#### PI-06 — Route `insert_produk` dari halaman pembelian tanpa auth

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_03` §5.7.
- **Bukti:** `Routes.php:140` mendefinisikan `POST insert_produk` tanpa filter; form `app/Views/transaksi/pembelian.php:1011` mengarah ke route ini. `Pembelian::insert_produk()` membuat barang baru.
- **Dampak:** Barang dapat dibuat tanpa login.
- **Rekomendasi:** Tambahkan `auth` (dan validasi role/unit).
- **Risiko:** Sedang–tinggi.

### 4.4 PRD Modul 04 — Service & Garansi

#### SV-01 — `cancel_service` tidak menghapus invoice/detail sparepart

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_04` §4.4, §5.6, §6.
- **Bukti:** `Service::insert_sparepart()` memanggil `buatPenjualanDariService()` yang membuat `penjualan` + `detail_penjualan` bertaut `service_idservice` (`Service.php:484-547`). `cancel_service()` hanya menghapus `service`, `service_kerusakan`, `service_sparepart`, dan DP/jurnal DP (`Service.php:886-932`); tidak memanggil `ServiceSparepartPenjualanSyncService` atau menghapus penjualan/detail/pembayaran.
- **Dampak:** Invoice yatim tetap ada; stok tetap berkurang melalui `detail_penjualan`; laporan penjualan tidak sinkron.
- **Rekomendasi:** Panggil penghapus transaksi service yang sudah ada sebelum menghapus service, dalam transaksi yang sama.
- **Risiko:** Tinggi.

#### SV-02 — Route `service_kerusakan`/`service_sparepart` menunjuk method yang tidak ada

- **Status:** `UNIMPLEMENTED`
- **Lokasi PRD:** `PRD_MODUL_04` §4.2.
- **Bukti:** `Routes.php:151-152` mengarah ke `Service::kerusakan_table` dan `Service::sparepart_table`; `Service.php` tidak memiliki kedua method.
- **Dampak:** Halaman/route tersebut error.
- **Rekomendasi:** Hapus route atau implementasikan method.
- **Risiko:** Rendah–sedang.

#### SV-03 — DP kedua pada pembayaran memakai `kode_dp` yang sama

- **Status:** `UNCERTAIN`
- **Lokasi PRD:** `PRD_MODUL_04` §4.1, §4.3, §4.6.
- **Bukti:** `Service::insert_service()` dan `Service::insert_pembayaran()` sama-sama memakai `kode_dp='DP{no_service}'`. `ServiceDpService::catatDp()` mengembalikan status `duplikat`; `insert_pembayaran()` memperlakukan status selain OK sebagai error. Dengan demikian DP tambahan setelah DP awal tidak dapat dicatat.
- **Dampak:** Pembayaran dengan DP kedua gagal, meskipun UI menyediakan field DP pada halaman pembayaran.
- **Rekomendasi:** Gunakan `kode_dp` unik per penerimaan atau hapus field DP kedua dari UI.
- **Risiko:** Sedang; perlu konfirmasi aturan DP.

#### SV-04 — Halaman status service publik membocorkan PII dan merender HTML mentah

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_04` §4.5; `PRD_MODUL_10` tidak membahas halaman ini.
- **Bukti:** `Routes.php:218` `GET status_service/{id}` tanpa filter. View `app/Views/transaksi/status_service.php:83-88` mencetak `no_service`, `nama`, `keluhan` dengan `<?= ... ?>` tanpa `esc()`.
- **Dampak:** Nama/keluhan pelanggan dapat diakses tanpa login dan menjadi celah XSS.
- **Rekomendasi:** Tambahkan auth/ID acak, `esc()`, dan batasi data.
- **Risiko:** Tinggi (privasi/XSS).

#### SV-05 — Pembatalan service memakai GET tanpa CSRF

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_04` §4.4.
- **Bukti:** `Routes.php:158` `GET service/cancel/{id}`; `cancel_service()` menghapus data. Tidak ada `csrf`.
- **Dampak:** Tautan/jebakan CSRF dapat membatalkan service.
- **Rekomendasi:** Ubah ke POST + CSRF + konfirmasi.
- **Risiko:** Tinggi.

### 4.5 PRD Modul 05 — Keuangan, KasBank, Aset

#### FN-01 — `kas_masuk` dan `kategori_kas` dapat diakses tanpa auth

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_05` §6.
- **Bukti:** `Routes.php:431` `GET /kas_masuk` tanpa filter; `Routes.php:416` `GET /kategori_kas` tanpa filter.
- **Dampak:** Data kas masuk dan kategori kas terekspos tanpa login.
- **Rekomendasi:** Tambahkan `auth`.
- **Risiko:** Tinggi.

#### FN-02 — Klaim CSRF pada seluruh penulisan ledger tidak benar

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_05` §4.4, §5.9, §8.
- **Bukti:** Lihat X-04. `insert_kas_masuk`, `update_kas_masuk`, `delete_kas_masuk`, `insert_kas_keluar`, `update_kas_keluar`, `delete_kas_keluar`, `insert_asset`, `update_asset`, `delete_asset`, transfer internal/antar-unit, `insert_retur_suplier/customer` hanya `auth` atau tanpa filter.
- **Dampak:** PRD memberi rasa aman yang tidak ada.
- **Rekomendasi:** Koreksi status dan daftar route.
- **Risiko:** Tinggi.

#### FN-03 — Edit/hapus kas masuk tidak menyinkronkan jurnal

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_05` §4.1 butir 5, §5.6.
- **Bukti:** Lihat X-07; `Kas_Masuk.php:212-300` hanya update source + hapus/posting ledger, tanpa `JurnalModel->hapusByReferensi`/insert ulang; `delete_kas_Masuk()` tidak menghapus jurnal.
- **Dampak:** Jurnal tidak mencerminkan kas masuk setelah edit/hapus.
- **Rekomendasi:** Sinkronkan jurnal dalam transaksi yang sama.
- **Risiko:** Tinggi.

#### FN-04 — Edit kas keluar tidak menyinkronkan jurnal

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_05` §4.1 butir 5, §5.6.
- **Bukti:** `Kas_Keluar::update_kas_keluar()` (`:482-590`) hanya `update` sumber, `hapusPosting`, `postingKasKeluar`, dan sync kasbon; tidak menghapus/menulis ulang `jurnal`. Delete (`:593-625`) menghapus jurnal.
- **Dampak:** Jurnal lama tetap dengan nilai/tanggal/akun lama.
- **Rekomendasi:** Sinkronkan jurnal saat update.
- **Risiko:** Tinggi.

#### FN-05 — Scope unit tulis hanya di filter baca

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_05` §5.5, §6.
- **Bukti:** Lihat X-06. `unitFilterTerpilih()`/`bebasPilihUnit()` dipakai untuk daftar/filter/export, bukan untuk memvalidasi `unit_idunit` pada insert/update. `BankRekeningValidator` memastikan rekening teralokasi ke unit yang dikirim, bukan bahwa user berhak atas unit itu.
- **Dampak:** Cross-unit write.
- **Rekomendasi:** Validasi unit tulis terhadap scope user.
- **Risiko:** Tinggi.

#### FN-06 — Aset: tanpa role/scope, unit dari POST, tidak atomik, dan cron publik

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_05` §4.6, §5.
- **Bukti:** `Asset::insert_asset()`/`update_asset()`/`delete_asset()` tidak memeriksa `ID_JABATAN`; `unit` diambil dari POST (`Asset.php:89,172`); insert asset + riwayat penyusutan tidak dibungkus transaksi; `update_asset()` mengabaikan `asset_code`, `nilai_perolehan`, `nilai_sekarang`; route `GET penyusutan_cronjob` tanpa auth (`Routes.php:580`); `pulihkan_asset/{id}` juga GET.
- **Dampak:** Aset/penyusutan dapat diubah lintas unit tanpa hak; data nilai dapat tidak tersimpan; cron dapat dipicu publik.
- **Rekomendasi:** Guard role/unit, POST+CSRF untuk aksi mutasi, transaksi, perbaiki field update.
- **Risiko:** Tinggi.

#### FN-07 — Transfer internal/antar-unit tidak memakai CSRF

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_05` §4.4, §5.9.
- **Bukti:** `Routes.php:450-456` hanya `auth`; hanya setor/tarik tunai (`:475-480`) yang `['auth','csrf']`.
- **Dampak:** Transfer/post-reversal dapat dipicu lintas situs.
- **Rekomendasi:** Tambahkan CSRF pada semua route mutasi.
- **Risiko:** Tinggi.

#### FN-08 — Route `laba_service`/`fee_service` memakai typo `fileter`

- **Status:** `CONTRADICTION`/`UNCERTAIN`
- **Lokasi PRD:** `PRD_MODUL_04`/`05` yang menganggap route berfilter auth.
- **Bukti:** `Routes.php:336-340` memakai `['fileter' => 'auth']`, bukan `filter`. Filter `fileter` tidak terdaftar. Tergantung resolver CI, route dapat error atau berjalan tanpa auth yang dimaksud.
- **Dampak:** Proteksi auth tidak berlaku; route bisa 500.
- **Rekomendasi:** Perbaiki menjadi `filter` dan uji route.
- **Risiko:** Sedang.

### 4.6 PRD Modul 06 — Hutang Piutang

#### HP-01 — Projection pembelian memakai total yang salah

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_06` §5.9.
- **Bukti:** Lihat PI-05 (`HutangPiutangService.php` `syncFromPembelian`).
- **Dampak:** Saldo/status projection hutang supplier salah untuk pembelian tidak lunas.
- **Rekomendasi:** Gunakan `total_transaksi`.
- **Risiko:** Tinggi.

#### HP-02 — Pembayaran piutang legacy tidak masuk kas dan sisa dari POST

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_06` §5.3; `PRD_MODUL_02` §4.3.
- **Bukti:** Lihat SL-06.
- **Dampak:** Kas/bank tidak bertambah; sisa dapat dimanipulasi; status tidak pernah Lunas.
- **Rekomendasi:** Hitung sisa server-side, posting jurnal, perbaiki status.
- **Risiko:** Tinggi.

#### HP-03 — KPI ketepatan piutang selalu `null`

- **Status:** `UNIMPLEMENTED` (gap diketahui)
- **Lokasi PRD:** `PRD_MODUL_08` §7; `PRD_MODUL_06` §5/§6 bila dianggap tersedia.
- **Bukti:** `PiutangTimelinessCalculator::calculate()` selalu mengembalikan `score => null`, `status => 'gap_no_payment_date'` karena `pembayaran_piutang` tidak punya kolom tanggal bayar.
- **Dampak:** Komponen timeliness piutang tidak dapat dinilai.
- **Rekomendasi:** Tambahkan kolom tanggal bayar + isi data, atau dokumentasikan sebagai gap.
- **Risiko:** Tinggi bila komponen KPI memiliki bobot.

### 4.7 PRD Modul 07 — HRD & Payroll

#### PR-01 — Halaman payroll menampilkan semua unit tanpa scope

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_07` §4.4, §6.
- **Bukti:** `Payroll::index()` membangun query `finance_payroll` + `kas_keluar` tanpa filter unit; `$scope` hanya dipakai untuk `can_input` di view (`Payroll.php:85-220`).
- **Dampak:** Setiap user login dapat melihat payroll seluruh unit.
- **Rekomendasi:** Filter query berdasarkan `FinanceScopeService`; guard view.
- **Risiko:** Kritis (privasi gaji).

#### PR-02 — CRUD payroll legacy tanpa role/scope

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_07` §4.4, §5.3.
- **Bukti:** `Payroll::insert()/update()/delete()` hanya memeriksa lock file untuk update/delete; tidak ada `canInput`/scope. `idunit` diambil dari POST.
- **Dampak:** User login dapat menambah/mengubah/menghapus baris `kas_keluar` payroll lintas unit.
- **Rekomendasi:** Tambahkan guard role/scope dan validasi unit.
- **Risiko:** Kritis.

#### PR-03 — `bayar` ulang menghapus `potongan_kasbon`

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_07` §5.5.
- **Bukti:** `Payroll::bayar()` tidak menolak status `dibayar`. Settlement idempotent mengembalikan `potongan=0` pada panggilan kedua, lalu `potongan_kasbon` ditimpa 0 dan `total_bersih = total`.
- **Dampak:** Riwayat potongan kasbon hilang; payroll dapat dibayar dua kali.
- **Rekomendasi:** Tolak bila sudah `dibayar`; jangan menimpa nilai historis.
- **Risiko:** Tinggi.

#### PR-04 — `updateRegister` tidak memeriksa lock file

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_07` §5.3.
- **Bukti:** `Payroll::updateRegister()` hanya memeriksa `canInput`, unit, dan status `dibayar`; tidak membaca `getPayrollLocks()`.
- **Dampak:** Baris yang “terkunci” masih dapat diubah melalui register aktif.
- **Rekomendasi:** Tambahkan pemeriksaan lock yang sama.
- **Risiko:** Sedang–tinggi.

#### PR-05 — Dokumen mencampur `kas_keluar` dan `finance_payroll`

- **Status:** `STALE`
- **Lokasi PRD:** `PRD_MODUL_07` §4.4, §5.2, §5.3.
- **Bukti:** `Payroll::generateRegister()` memakai `PayrollGenerator` yang menulis `finance_payroll`; `index` menampilkan `finance_payroll` dan `kas_keluar`. CRUD legacy `insert/update/delete` menulis `kas_keluar`. PRD menyebut “baris payroll disimpan sebagai `kas_keluar`” untuk seluruh alur.
- **Dampak:** Pembaca salah memahami sumber data dan tabel yang perlu diuji.
- **Rekomendasi:** Pisahkan alur register aktif (`finance_payroll`) dan legacy (`kas_keluar`).
- **Risiko:** Sedang.

#### PR-06 — `slip_gaji` dan `gaji` tidak membatasi akses

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_07` §4.4; `PRD_MODUL_08` §4.
- **Bukti:** `PenilaianKPI::slip_gaji($idakun)` tidak memeriksa siapa yang meminta; `gaji()` menampilkan daftar semua karyawan dengan `TERIMA_GAJI=1`. Route hanya `auth`.
- **Dampak:** Setiap user login dapat melihat slip/gaji karyawan lain.
- **Rekomendasi:** Batasi ke diri sendiri/Finance/role berwenang dan validasi unit.
- **Risiko:** Kritis.

#### PR-07 — `attendance_save` tidak memeriksa evaluator/target

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_07`/`08` yang menyebut guard absensi.
- **Bukti:** `PenilaianKPI::attendance_save()` mengambil `target_id` dari POST dan langsung memanggil `AttendanceInputService::saveAttendance()` tanpa `canEvaluateComponent`/scope. `attendance_input` hanya memfilter daftar target.
- **Dampak:** User login dapat menyimpan absensi/nilai untuk karyawan mana pun.
- **Rekomendasi:** Gunakan `EvaluatorAuthorizationService` sebelum menyimpan.
- **Risiko:** Kritis.

#### PR-08 — `lockPayroll`/`unlockPayroll` tanpa role

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_07` §4.4.
- **Bukti:** `Payroll::lockPayroll()/unlockPayroll()` langsung membaca/menulis `writable/locks/payroll.json` tanpa `canInput`/role.
- **Dampak:** Siapa pun dapat mengunci/membuka payroll.
- **Rekomendasi:** Guard role/scope.
- **Risiko:** Sedang.

### 4.8 PRD Modul 08 — KPI & Penilaian

#### KPI-01 — Target KPI bukan “Admin Root saja”

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_08` §4.4, §5.5, §6.
- **Bukti:** `TargetKpi::ADA_YANG_BOLEH = [1, 34]`; role 0 (Admin/Finance) tidak termasuk, role 34 (Manager) termasuk. Guard ada di controller.
- **Dampak:** Dokumen salah soal aktor; perubahan target oleh Manager tidak terdokumentasi.
- **Rekomendasi:** Perbarui aturan/aktor atau ubah guard sesuai keputusan bisnis.
- **Risiko:** Sedang.

#### KPI-02 — Hapus aset master bukan hanya Finance 0/1

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_08` §5.3, §6.
- **Bukti:** `PenilaianKPI::aset_master_delete()` mengizinkan role `[0,1,2]`; pesan “Hanya Finance” menyesatkan. `isAssetMasterManager()` mengizinkan `[0,1,2,34]`.
- **Dampak:** Direktur (2) dapat menghapus aset master.
- **Rekomendasi:** Selaraskan guard dan pesan; perbarui PRD.
- **Risiko:** Sedang.

#### KPI-03 — Range input manual tidak seragam 0–100

- **Status:** `CONTRADICTION`
- **Lokasi PRD:** `PRD_MODUL_08` §5.6, §6.
- **Bukti:** `save_kualitas()` menolak skor di luar 1–5 (`max_score=5`); `save_manual_daily` memakai `ManualGridService`; `ManualKpiScorer` menggunakan skala 0–100 untuk beberapa komponen. Tidak semua input manual 0–100.
- **Dampak:** Aturan validasi yang ditulis tidak sesuai UI/komponen.
- **Rekomendasi:** Dokumentasikan range per komponen, bukan satu range global.
- **Risiko:** Sedang.

#### KPI-04 — `attendance_save` tidak memakai otorisasi evaluator

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_07` §4/§5; `PRD_MODUL_08` §4.2.
- **Bukti:** `PenilaianKPI::attendance_save()` mengambil `target_id` dari POST lalu memanggil `AttendanceInputService::saveAttendance()`. Controller tidak memanggil `EvaluatorAuthorizationService`/`isInScope`, dan `AttendanceInputService::saveDailyAttendance()` hanya memvalidasi shift/sesi/jam, bukan hak evaluator. `attendance_input` hanya memfilter daftar target.
- **Dampak:** User login dapat menyimpan absensi/nilai kehadiran untuk karyawan mana pun.
- **Rekomendasi:** Panggil `EvaluatorAuthorizationService` sebelum menyimpan, sama seperti `save_manual_daily`/`save_kualitas`.
- **Risiko:** Kritis.

#### KPI-05 — Engine KPI aktif adalah `KpiCalculationService`

- **Status:** `STALE`
- **Lokasi PRD:** `PRD_MODUL_08` §5.1, §8; `docs/architecture/kpi-engine.md`.
- **Bukti:** Pemanggilan aktif di `PenilaianKPI`, `PayrollGenerator`, `HoSalaryDistributionService`, `UnitSalaryCalculationService`, `ManagerKpiService`; `LegacyKpiCalculationService` tidak dipanggil controller aktif.
- **Dampak:** Dokumen arsitektur/PRD dapat menyesatkan tentang formula yang dipakai.
- **Rekomendasi:** Perbarui status dan rujukan.
- **Risiko:** Sedang.

#### KPI-06 — “Omzet” KPI dihitung dari gross profit

- **Status:** `UNCERTAIN`
- **Lokasi PRD:** `PRD_MODUL_08` §5/§8; `PRD_MODUL_09` §5.7.
- **Bukti:** `OmsetTokoCalculator`/`OmsetCabangCalculator` berkomentar “Omset = Gross Profit = SUM(sub_total - hpp_penjualan)”; komponen `OMZET_GLOBAL`/`OMZET_WILAYAH` memakai calculator tersebut. PRD 09 menyebut omzet marketing dari `omset` lead/service.
- **Dampak:** Istilah “omzet” dapat berarti laba kotor; target/achievement KPI tidak sebanding dengan omzet penjualan.
- **Rekomendasi:** Konfirmasi definisi bisnis; ganti label atau query agar konsisten.
- **Risiko:** Tinggi bila KPI dipakai untuk insentif.

### 4.9 PRD Modul 09 — Marketing, Konten, Sosial

#### MK-01 — Fallback target sosial global tidak berfungsi

- **Status:** `UNIMPLEMENTED`
- **Lokasi PRD:** `PRD_MODUL_09` §4.5/§5 (target global seluruh unit).
- **Bukti:** `ModelSocialMediaTarget::findTarget()` membangun query dengan `where('unit_id', $unitId)` lalu fallback menambahkan `groupStart()->where('unit_id', 0)->groupEnd()` pada builder yang sama. Query akhirnya meminta `unit_id = $unitId AND unit_id = 0`; target global tidak pernah ditemukan.
- **Dampak:** Unit tanpa target spesifik tidak memakai target global.
- **Rekomendasi:** Buat query fallback terpisah tanpa filter unit spesifik; tambahkan test.
- **Risiko:** Sedang.

#### MK-02 — Route marketing/konten/sosial tidak memakai CSRF

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_09` §6.
- **Bukti:** `Routes.php:677-728` hanya `auth`; semua `POST` (`simpan`, `status`, `qc`, `channel`, `campaign`, `leads`, `ads`, `target`) tanpa `csrf`.
- **Dampak:** Aksi tulis dapat dipicu lintas situs.
- **Rekomendasi:** Tambahkan CSRF atau dokumentasikan risiko.
- **Risiko:** Sedang.

### 4.10 PRD Modul 10 — Integrasi & Cross

#### IN-01 — `CrmLeadBridge` tidak memfilter `kommo_deleted_at`

- **Status:** `MISSING`
- **Lokasi PRD:** `PRD_MODUL_10` §4.2, §5.
- **Bukti:** `leadSummary()`/`leadList()` memfilter tanggal/nomor/status, tetapi tidak `ml.kommo_deleted_at IS NULL`; kolom ada di migration `2026-09-11-000100_AddKommoLeadFields.php`.
- **Dampak:** Lead yang sudah dihapus di Kommo masih tampil di CRM.
- **Rekomendasi:** Tambahkan filter soft-delete.
- **Risiko:** Sedang.

#### IN-02 — Route `api/crm/lead-list` duplikat

- **Status:** `STALE` (sudah dicatat, tetap ada)
- **Lokasi PRD:** `PRD_MODUL_10` §5.7.
- **Bukti:** `Routes.php:736-737` identik.
- **Dampak:** Tidak mengubah perilaku saat ini, tetapi membingungkan dan dapat menyebabkan salah edit.
- **Rekomendasi:** Hapus satu.
- **Risiko:** Rendah.

---

## 5. Konflik Antar-PRD

| ID | Konflik | Bukti | Dampak |
|---|---|---|---|
| K-01 | PRD 05 menyatakan CSRF seluruh ledger; PRD 10/README mengakui CSRF global mati | `Routes.php`; `Filters.php` | Status keamanan tidak konsisten |
| K-02 | PRD 02 menyebut piutang pelanggan; PRD 06 menyebut piutang legacy pegawai | `Penjualan.php`; `Piutang.php` | Tidak jelas apakah penjualan kredit didukung |
| K-03 | PRD 03/`StokOpnameService` menyatakan opname mengoreksi stok; migration 2026-10-07 menghapusnya | `RemoveStokOpnameSelisihFromStokAkhir.php` | Aturan inventory tidak konsisten |
| K-04 | PRD 07 menyebut payroll `kas_keluar`; implementasi aktif memakai `finance_payroll` | `PayrollGenerator.php` | Tabel/sumber data salah |
| K-05 | PRD 08/09 memakai istilah “omzet”; calculator KPI memakai gross profit | `OmsetTokoCalculator.php` | Definisi KPI/insentif ambigu |
| K-06 | PRD 08 menyebut target KPI root-only dan aset Finance-only; kode mengizinkan role lain | `TargetKpi.php`; `PenilaianKPI.php` | Aktor tidak sesuai |

---

## 6. Gap Test yang Diprioritaskan

| Prioritas | Test yang perlu ada | Alur yang dilindungi |
|---|---|---|
| P0 | Route auth/CSRF matrix test | Semua route tulis |
| P0 | Kas masuk/keluar edit/delete journal consistency | X-07/FN-03/FN-04 |
| P0 | Hutang projection dari pembelian parsial | PI-05/HP-01 |
| P0 | Payroll `bayar` idempotent + lock | PR-03/PR-04 |
| P0 | `slip_gaji`/`gaji`/attendance authorization | PR-06/PR-07 |
| P1 | Bundle revenue & stock view | SL-03 |
| P1 | Cancel service removes invoice/detail | SV-01 |
| P1 | Stok opname final/reopen/batal | PI-02/PI-03 |
| P1 | Social target global fallback | MK-01 |
| P2 | Master category/product CRUD | MD-01/MD-02 |

---

## 7. Urutan Perbaikan Berbasis Risiko

1. **P0 — Keamanan akses:** tutup route tanpa auth; tambahkan guard role/unit pada master, payroll, KPI, dan service; ubah aksi destruktif GET menjadi POST+CSRF.
2. **P0 — Integritas keuangan:** perbaiki projection hutang, sinkronisasi jurnal kas, `Piutang::bayar`, dan CSRF transaksi.
3. **P0 — Privasi gaji/KPI:** scope payroll dan otorisasi slip/attendance.
4. **P1 — Persediaan:** perbaiki update stok minimum, keputusan opname, enum audit, retur atomik, dan bundle revenue.
5. **P1 — Service:** hapus invoice yatim saat cancel; perbaiki route mati dan DP kedua.
6. **P2 — Master data:** perbaiki route/form kategori, argumen `getLastBarangByKategori`, `$THIS`, dan `delete_jabatan`.
7. **P2 — Integrasi/marketing:** filter soft-delete CRM, CSRF marketing, fallback target sosial, dan dokumentasi webhook.

---

## 8. Status Dokumentasi

Dokumen yang diperbarui setelah laporan ini:

- `docs/prd/PRD_MASTER.md`
- `docs/prd/README.md`
- `docs/prd/modul/PRD_MODUL_01_MasterData.md` s.d. `PRD_MODUL_10_Integrasi_Cross.md` (koreksi terarah + rujukan temuan)
- `docs/prd/_analysis/00_cross_cutting.md`
- `docs/architecture/kpi-engine.md` (catatan status engine aktif)

Temuan `UNCERTAIN` tidak diubah menjadi aturan bisnis final. Keputusan atas opname, piutang penjualan kredit, DP kedua, dan definisi “omzet” memerlukan konfirmasi user.
