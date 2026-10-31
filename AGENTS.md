# AGENTS.md — ERP iClear

Instruksi ini berlaku untuk seluruh repository. Instruksi eksplisit dari user selalu menang atas dokumen ini. PRD adalah dokumentasi, bukan bukti bahwa implementasi sudah benar; kode, route, schema, dan test adalah bukti implementasi aktual.

## A. Project context

- **Framework:** CodeIgniter 4.4.8 (`composer.lock`). `composer.json` mendukung PHP `^7.4 || ^8.0`; environment audit memakai PHP 8.5.12.
- **Domain:** ERP operasional toko/cabang: penjualan/POS, pembelian dan persediaan, service dan garansi, keuangan/kas-bank/jurnal/aset, hutang-piutang, HR/payroll, KPI, marketing/konten/social media, serta integrasi Kommo/CRM.
- **Arsitektur aktual:** controller di `app/Controllers` (extends `App\Controllers\BaseController`); model di `app/Models` dengan prefix `Model*`; business logic domain di subfolder `app/Services` (mis. `Finance`, `Kpi`, `Payroll`); route di `app/Config/Routes.php`; view di `app/Views`; migrasi di `app/Database/Migrations`; konfigurasi di `app/Config`; test di `tests`.
- **Pola view:** mayoritas halaman merender `app/Views/template.php` dengan data `body => 'folder/view'`. Layout dan partial ada di `app/Views/inc` dan `app/Views/partials`.
- **Frontend:** Bootstrap 5, jQuery/DataTables server-side, Iconify, dan vanilla JS. CSS utama ada di `public/template/assets/css/styles.css`.
- **Database:** driver default MySQLi. Konfigurasi test yang aktif adalah `phpunit.xml`, yang mengarah ke database test MySQLi (`erp_test`, prefix `db_`). `app/Config/Database.php` memaksa group `tests` saat testing dan menolak database test yang sama dengan database produksi.
- **Dokumentasi:** `docs/prd/README.md`, `docs/prd/PRD_MASTER.md`, 10 PRD modul di `docs/prd/modul/`, dan analisis di `docs/prd/_analysis/`. Aturan isolasi test ada di `tests/README.md`.
- **Konteks produk/UI:** `PRODUCT.md` khusus alur Tutup Kasir; `DESIGN.md` adalah visual system Kas & Bank, bukan spesifikasi seluruh ERP. `README.md` masih boilerplate CodeIgniter dan bukan sumber aturan proyek.

### Peta modul dan PRD

| Area | Kode utama | Dokumentasi wajib dibaca untuk perubahan area ini |
|---|---|---|
| Master data & auth | `Pegawai`, `Unit`, `Produk`, `Phone`, `Pelanggan`, `Supplier`, `Bundle`, `NoAkun`, `Auth` | `docs/prd/modul/PRD_MODUL_01_MasterData.md` |
| Penjualan & piutang | `Penjualan`, `Riwayat_Penjualan`, `Retur_Customer`, `Piutang` | `docs/prd/modul/PRD_MODUL_02_Penjualan_Piutang.md` |
| Pembelian & persediaan | `Pembelian`, `StokAwal`, `Kartu_Stok`, `MutasiStok`, `StokOpname`, `ReturBarangRusak`, `Retur_Suplier` | `docs/prd/modul/PRD_MODUL_03_Pembelian_Persediaan.md` |
| Service & garansi | `Service`, `Riwayat_Service`, `StatusGaransi`, `Expired_service`, `Fee_Service` | `docs/prd/modul/PRD_MODUL_04_Service_Garansi.md` |
| Keuangan, kas-bank, aset | `Kas_Masuk`, `Kas_Keluar`, `Jurnal`, `SisiKeuangan`, `LabaRugi`, `KasBank`, `TutupKasir`, `Asset`, `DashboardFinance` | `docs/prd/modul/PRD_MODUL_05_Keuangan_KasBank_Aset.md` |
| Hutang-piutang terpusat | `HutangPiutang`, `PembayaranHutang`, `Piutang` | `docs/prd/modul/PRD_MODUL_06_HutangPiutang.md` |
| HRD & payroll | `Absensi`, `RiwayatPresensi`, `JadwalMasuk`, `Tugas`, `Payroll` | `docs/prd/modul/PRD_MODUL_07_HRD_Payroll.md` |
| KPI & penilaian | `PenilaianKPI`, `TemplateKPI`, `Penilaian`, `SummaryKPI`, `TargetKpi`, `CustomerSatisfaction` | `docs/prd/modul/PRD_MODUL_08_KPI_Penilaian.md`; untuk formula juga `docs/architecture/kpi-business-rules.md` dan `docs/architecture/kpi-engine.md` |
| Marketing, konten, sosial | `Konten`, `Sosial`, `Marketing` | `docs/prd/modul/PRD_MODUL_09_Marketing_Konten_Sosial.md` |
| Integrasi & lintas modul | `KommoWebhook`, `CrmLeadBridge`, `CustomerChatInvoiceBridge` | `docs/prd/modul/PRD_MODUL_10_Integrasi_Cross.md`; `docs/prd/_analysis/00_cross_cutting.md` |

## B. Documentation-first workflow

Sebelum mengubah kode:

1. Baca `docs/prd/README.md` bila tersedia. Jika dokumen yang dirujuk tidak ada, katakan demikian; jangan mengarang isinya.
2. Pilih dan baca PRD yang relevan dari tabel di atas. **Jangan membaca seluruh PRD untuk setiap tugas**; pilih berdasarkan dampak perubahan.
3. Jika perubahan menyentuh lebih dari satu modul, baca juga aturan lintas-modul di `docs/prd/PRD_MASTER.md` dan `docs/prd/_analysis/00_cross_cutting.md`. Untuk KPI, baca juga dokumen arsitektur KPI.
4. Periksa implementasi aktual: route, controller, service/model, query, migrasi/schema tabel terkait, view, dan test yang relevan. Jangan hanya membaca PRD.
5. Jika dokumentasi tidak tersedia, tidak lengkap, atau bertentangan dengan kode, nyatakan ketidakpastian dan investigasi kode/test/schema sebelum memutuskan. Tandai aturan yang belum terverifikasi sebagai `⚠️` atau pertanyaan terbuka; inferensi ditandai `🔶`.
6. Jika kebutuhan bisnis ambigu atau menyentuh alur berisiko, tanyakan kepada user sebelum mengubah perilaku.

## B.1 Issue-first setelah approval (wajib, lintas session)

Jangan buat issue untuk setiap permintaan. Issue hanya dibuat **setelah user menyetujui pekerjaan**, yaitu saat user menyatakan secara eksplisit untuk dikerjakan (mis. "setuju", "kerjakan", "lanjut", "gas", "implementasikan") atau menyetujui rencana yang diajukan. Saat user masih bertanya, minta pendapat, atau menjelaskan kebutuhan, jangan buat issue dan jangan edit file; ajukan rencana singkat lalu tunggu persetujuan.

Setelah approval, urutannya:

1. Buat issue lebih dulu:
   ```sh
   gh issue create --repo bwafi/ERP-iClear --title "[Change] <ringkas>" --body-file /tmp/opencode/issue.md --label enhancement
   ```
   Body minimal: Sumber, Latar belakang, Perubahan, Dampak, Acceptance criteria.
2. Masukkan ke board dan set status **Todo**:
   ```sh
   gh project item-add 1 --owner bwafi --url <issue-url> --format json
   gh project item-edit --project-id PVT_kwHOBrFnns4BmskD --id <item-id> --field-id PVTSSF_lAHOBrFnns4BmskDzhlUhds --single-select-option-id f75ad846
   ```
3. Setelah implementasi dan test, pindahkan ke **Testing** (`7dc918ef`) dan komentari hash commit + hasil test. Setelah verifikasi manual, pindahkan ke **Done** (`98236657`).
4. Sebut nomor issue di pesan commit: `... (refs #<nomor>)`.

## C. Change safety

- Pahami alur data, efek samping, caller, route, laporan, job, dan test sebelum mengubah implementasi. Perubahan harus berada dalam scope tugas.
- Jangan mengubah business logic yang sudah ada tanpa instruksi eksplisit dari user.
- Jangan mengubah schema database, data historis, jurnal yang sudah terposting, permission, atau alur keuangan tanpa otorisasi eksplisit.
- Untuk perubahan keuangan, persediaan, refund/DP, hutang-piutang, payroll, atau permission: telusuri seluruh dependensi dan tambahkan regression test yang relevan. Jangan hanya menguji happy path.
- Jangan menganggap route, tombol, menu, atau view yang ada berarti fitur sudah berfungsi. Buktikan lewat controller/service dan test.
- Jangan mengarang hasil test, hasil query, isi database, atau fakta repository.
- Jangan menjalankan DDL/DML destruktif pada database nyata. Perubahan schema harus lewat migrasi yang disetujui.

### Guardrail terverifikasi yang harus dipertahankan

- **Auth bukan authz:** `AuthFilter` hanya memeriksa `logged_in`. Filter `auth` tidak membuktikan user berhak atas role/menu/unit tertentu. Guard role/unit banyak dilakukan manual di controller/service; jangan menghapusnya dan jangan menambah endpoint tulis tanpa autentikasi kecuali memang disengaja dan diuji.
- **CSRF:** CSRF global dimatikan. Route penulisan kas/ledger dan transaksi berisiko yang sudah memakai `['auth', 'csrf']` harus tetap memakai CSRF dan token form. Ikuti pola yang ada untuk endpoint baru yang setara.
- **Transaksi & ledger:** operasi multi-tabel harus atomik. Posting kas-bank memakai `ModeKasBank`/guard idempotensi dan `PostingLedgerException`; kegagalan posting harus membatalkan seluruh batch, bukan meninggalkan partial success.
- **Scope unit:** role lintas unit, unit sendiri, SPV via `spv_units`, rekening shared, dan rekening Finance/HO memiliki aturan berbeda. Validasi scope di server; jangan percaya `unit_id` dari request. Gunakan service scope yang sudah ada, misalnya `FinanceScopeService`/`KasBankScopeService`.
- **Data historis & cut-off:** transaksi legacy, `scope`, dan cut-off keuangan tidak boleh ditulis ulang sembarangan. Gunakan alur reversal/backfill yang sudah ada dan minta otorisasi bila perlu.
- **Service DP:** route pembayaran NET lama sengaja dinonaktifkan; alur aktif memakai pembayaran GROSS (`Service::insert_pembayaran`). Jangan mengaktifkan kembali route lama tanpa instruksi eksplisit dan regression test.
- **Gap bukan status selesai:** temuan gap di PRD (misalnya otorisasi, stok penjualan, Kommo, atau KPI piutang) tidak boleh dianggap sudah diperbaiki hanya karena ada route/UI.

## D. Coding and testing

- Ikuti pola implementasi di file sekitarnya sebelum memperkenalkan abstraksi, helper, service, atau dependency baru. Jangan menambah dependency tanpa alasan yang jelas.
- Pertahankan struktur yang ada: controller dan method mengikuti gaya file setempat, model berprefix `Model*`, service ditempatkan di domain yang sesuai, view memakai layout `template` bila halaman memakai layout tersebut.
- Jangan mengganti nama file, route, field, tabel, atau kontrak API yang sudah dipakai tanpa menelusuri semua pemanggil.
- Validasi input di trust boundary, pertahankan error handling dan transaksi yang sudah ada, dan jangan mengorbankan security/accessibility demi penyederhanaan.
- Logika non-trivial baru (branch, loop, parser, perhitungan uang/stok, atau alur baru) harus memiliki test kecil atau regression test. Perubahan dokumentasi saja tidak memerlukan test aplikasi.
- Test harus memakai database test yang terisolasi. Konfigurasi aktif `phpunit.xml` memakai `erp_test`; jangan pernah mengarahkan test ke `erp_local` atau database produksi. Untuk database test tetap, gunakan nama yang jelas berakhiran `_test`. Jangan mengandalkan ringkasan PRD soal database test; baca `phpunit.xml` yang aktif.
- Perintah yang dapat dipakai:
  - Test terarah: `vendor/bin/phpunit -c phpunit.xml tests/NamaTest.php`
  - Seluruh suite: `vendor/bin/phpunit -c phpunit.xml` (atau `composer test`)
  - File konfigurasi khusus (`phpunit.hard.xml`, `phpunit.erp_finance.xml`, `phpunit.tk.xml`) hanya jika tugas memang terkait; periksa dulu database targetnya.
- Jika test tidak dapat dijalankan, jelaskan perintah dan error aktualnya. Jangan mengklaim test lulus.
- Tidak ada script lint/format khusus di `composer.json`; minimal jalankan `php -l` pada file PHP yang diubah bila tidak ada pemeriksaan lain.
- Sebelum selesai, periksa `git diff --check` dan `git diff` untuk memastikan tidak ada perubahan yang tidak terkait.
- **Jangan melakukan `git commit` sebelum user menginstruksikannya secara eksplisit.**

## E. PRD maintenance

- Jika perubahan mengubah business behavior, acceptance criteria, alur, schema bisnis, atau aturan lintas-modul, perbarui PRD terkait. Jika perubahan menyentuh lebih dari satu modul, perbarui juga `docs/prd/PRD_MASTER.md` bila ringkasannya terpengaruh.
- Refactor internal tanpa perubahan perilaku tidak perlu mengubah PRD.
- Jangan menyalin seluruh isi PRD ke `AGENTS.md`; gunakan referensi ke dokumen yang relevan.
- Jangan menaikkan status verifikasi tanpa bukti kode/test. Gunakan legenda yang ada: `✅` fakta terverifikasi, `🔶` inferensi, `⚠️` belum diverifikasi/ketidakpastian.
- Jika kode dan PRD bertentangan, laporkan konflik dan jelaskan mana yang menjadi bukti; jangan diam-diam memilih salah satu.

## F. Completion report

Akhiri setiap tugas dengan laporan singkat berisi:

- **Ringkasan perubahan:** apa yang diubah dan mengapa.
- **File yang diubah:** path lengkap.
- **PRD dan business rules yang diperiksa:** dokumen dan aturan spesifik.
- **Test:** perintah yang dijalankan, hasil aktual, dan alasan bila tidak dijalankan.
- **Risiko/keterbatasan:** hal yang belum terverifikasi, asumsi, atau dampak yang perlu dicek user.
- **Status dokumentasi:** PRD yang diperbarui, atau alasan tidak ada perubahan dokumentasi.

Jangan menyatakan pekerjaan selesai, aman, atau lulus test tanpa bukti dari output yang benar-benar dijalankan.
