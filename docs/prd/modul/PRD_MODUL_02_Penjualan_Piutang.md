# PRD Modul 02 — Penjualan & Piutang

| | |
|---|---|
| Kode Modul | PJ-02 |
| Sumber analisis | baca `app/Controllers/Penjualan.php` (full), `Routes.php`, model `ModelPenjualan/ModelDetailPenjualan/ModelPiutang/ModelPembayaranPiutang` |
| Status verifikasi | ✅ fakta / 🔶 inferensi / ⚠️ ketidakpastian |

---

## 1. Tujuan
Mencatat transaksi penjualan (POS), menghasilkan invoice & struk, mencatat pembayaran tunai/bank, menghitung HPP & jurnal, memelihara piutang pelanggan (legacy) dan riwayat/daftar umur piutang.

## 2. Aktor
- Kasir / frontliner (input penjualan).
- Pelanggan (data master; bisa dibuat saat transaksi).
- Finance/manager (lihat riwayat, piutang, ekspor).

## 3. Preconditions
- User login; unit aktif (`session('ID_UNIT')`).
- Barang/HP tersedia di master; HPP barang tersedia (`ModelHppBarang`).
- Pelanggan terpilih (wajib untuk beberapa alur).

## 4. Alur Utama
### 4.1 Input Penjualan — `Penjualan::insert_penjualan`
1. Ambil `produk[]` dari form; sanitasi angka (`sanitizeCurrency`: buang `Rp`, `.`, spasi).
2. Generate kode invoice: `SLL{idunit}{Ymd}{seq4}` (per unit per hari; last invoice hari itu).
3. Normalisasi pembayaran: `PenjualanPaymentSplit::normalize()` membaca `metode_bayar`, `bayar`, dan `bank[]`; rekening divalidasi terhadap `ModelBank::getBankDalamScopeUnit(session('ID_UNIT'))`. Setelah itu `PenjualanSaleValidator::periksa()` menghitung nilai kotor + total diskon dari `produk[]` dan memvalidasi total/pembayaran sebelum transaksi dimulai. — `insert_penjualan`
4. Susun jurnal: tunai (`penjualan_lunas_tunai`), bank (`penjualan_lunas_bank_{bankid}`), PPN, diskon, aksesoris/sparepart (`penjualan_acc_cash`), HP baru PPN/non-PPN, HP second.
5. Insert header `penjualan` + detail `detail_penjualan` (HPP per item dari `hpp_unit` unit transaksi, fallback `hpp_barang` lalu `barang.harga_beli`; bundle dipecah per barang penyusun).
6. Eksekusi dalam satu transaksi `transStart/transComplete`; bila gagal `transRollback` + flashdata.
7. Cetak struk/invoice mPDF (thermal/default) ke `uploads/Struk-{invoice}.pdf` + output ke browser.
### 4.2 Riwayat & Cetak
`Riwayat_Penjualan::index/ajax/detail/export/cetak_struk`.
### 4.3 Piutang legacy
`Piutang::index/insert` (input piutang), `riwayat_pembayaran_piutang`, `daftar_piutang`, `bayar_piutang` (cicilan), `umur_piutang`, ekspor aging.

## 5. Business Rules (Bukti)
1. Kode invoice `SLL{unit}{Ymd}{seq4}` per unit per hari. — `Penjualan::insert_penjualan`
2. Total & diskon disanitasi (`Rp/.`/spasi dihilangkan). — `Penjualan::sanitizeCurrency`
3. Pembayaran terpecah tunai + bank; `bank_idbank` header menyimpan `kode_pembayaran` (bukan id bank asli). — `insert_penjualan`
4. Status keterangan = `Lunas` bila `hutang==0`, else `Belum Lunas`. — `insert_penjualan`
5. HPP per item diambil dari `hpp_unit` produk+unit transaksi; fallback `hpp_barang` (global) lalu `barang.harga_beli`. Baris `hpp_unit` unit itu diperbarui pembelian terbaru dengan harga beli terakhir (lihat PRD Modul 03 rule 15). Bundle dipecah (jumlah × qty bundle) dan tiap komponen memakai HPP unit transaksi. — `ModelHppUnit::resolve`; `Penjualan::insert_penjualan`
6. Kategori akun jurnal dibedakan: HP baru PPN/non-PPN/second, sparepart, aksesoris, PPN, diskon. — `insert_penjualan`
7. Penjualan berjalan dalam SATU transaksi (header+detail+jurnal+pembayaran bank). — `insert_penjualan`
8. Kode pembayaran bank = `PJL{date}{unit}{rand4}`; di-refer ke `id_referensi` penjualan setelah insert. — `insert_penjualan`
9. Potongan stok penjualan **tidak** dilakukan oleh `Penjualan::insert_penjualan`; stok dihitung oleh VIEW `stok_barang` yang mengurangi `total_penjualan` dari `SUM(detail_penjualan.jumlah)`. — `2026-10-23-000500_StokBarangKurangiSparepartGaransi.php:103-105` (SL-01)
10. Tidak ada validasi ketersediaan stok sebelum penjualan; pengecekan stok terkait dikomentari. — `Penjualan.php:278-279` (SL-05)
11. Penjualan kredit hanya mengisi `keterangan='Belum Lunas'`; tidak membentuk piutang pelanggan dan tidak memanggil `HutangPiutangService`. — `Penjualan::insert_penjualan` (SL-02)
12. Setiap komponen bundle ditulis dengan `sub_total` penuh, sehingga laporan yang menjumlahkan `detail_penjualan.sub_total` dapat menggandakan omzet/laba. — `Penjualan.php:327-334`; `TutupKasir.php:360,610`; `ModelPenjualan.php:132` (SL-03)
13. `kode_invoice` tidak memiliki unique constraint; dua transaksi bersamaan dapat memperoleh kode sama. Kode invoice service juga memakai `SRV{date('YmdHis')}` tanpa random. — migration `penjualan`; `Service.php:496` (SL-04)
14. Picker kasir punya tab Produk/Bundle/Jasa. Tab Jasa memakai `ModelStokBarang::getJasa()` (`barang.jenis_barang='jasa'`) sehingga jasa bisa dijual tanpa stok awal; baris keranjang memakai kontrak `produk-checkbox` yang sama (harga, qty, diskon, PPN). Penjualan jasa tetap dicatat di `detail_penjualan` (omset/refund/DP/KPI tidak berubah) tetapi tidak menyentuh persediaan karena view `stok_barang` hanya memuat produk. HPP jasa mengikuti resolver yang sama (`hpp_unit` → `hpp_barang` → `barang.harga_beli`). HPP jasa (bagian teknisi) disimpan lewat field **HPP** di `/tarif_jasa`: nilai nominal masuk ke `barang.harga_beli` dan dicerminkan ke `hpp_barang.hpp` serta semua baris `hpp_unit` jasa itu (`sumber='manual'`), dengan validasi `0 ≤ HPP ≤ harga`. Field HPP juga menerima persen dari harga jual (mis. 10% × 1.000.000 = 100.000); persen hanya alat bantu input dan server yang menghitung nominalnya — yang disimpan tetap nominal, jadi perubahan harga jual tidak otomatis mengubah HPP sampai disimpan ulang. Kolom HPP tampil sebelum Harga Jual. Karena omzet = `sub_total − hpp_penjualan`, bagian teknisi tidak dihitung sebagai omzet pada transaksi baru; data historis tidak dihitung ulang. — `Penjualan::index`; `ModelStokBarang::getJasa`; `TarifJasa`; `ModelHppUnit::resolve`; `Views/transaksi/penjualan.php`; `Views/jasa/tarif.php`
15. Harga jual picker POS mengikuti unit transaksi: `COALESCE(harga_jual_unit.harga_jual, barang.harga)`. Picker produk memakai `ModelStokBarang::getAllBarang2()` dan picker jasa `getJasa()`; keduanya mengekspos `harga_jual` (efektif) dan tetap menyimpan `harga` master. Tanpa baris `harga_jual_unit` = ikut master. Nilai yang dipilih masuk keranjang sebagai `detail_penjualan.harga_penjualan` (snapshot); transaksi lama tidak di-backfill dan tidak berubah saat override/master diubah. Bundle tetap memakai `bundle.harga_jual`. — `ModelHargaJualUnit::resolve`; `ModelStokBarang::getAllBarang2/getJasa`; `Views/transaksi/penjualan.php`; `HargaJualUnitTest`; `HargaJualPickerTest`

16. Harga di markup picker wajib berupa rupiah bulat tanpa pemisah ribuan dan tanpa desimal. `harga_jual_unit.harga_jual` bertipe `decimal(15,2)`, sehingga MySQL mengembalikan string `"55000.00"`; `formatToRupiah()`/`unformatRupiah()` di halaman membuang semua karakter non-digit, jadi nilai desimal mentah terbaca ×100 (`"55000.00"` → `5.500.000`). Picker produk mengirim `(int) round((float) $p->harga_jual)` — pembulatan yang sama dengan `number_format($p->harga_jual, 0)` pada kolom harga di tabel itu; picker jasa memakai `(float)`. Bundle tetap memakai `bundle.harga_jual` (`varchar`) apa adanya. Normalisasi dilakukan saat render, bukan dengan mengubah regex JS, karena regex yang sama juga membersihkan titik ribuan pada input kasir. — `Views/transaksi/penjualan.php`; `PenjualanPickerMoneyTest`
17. `metode_bayar` adalah sumber kebenaran zona pembayaran: `tunai` hanya memakai nominal tunai dan mengabaikan baris bank; `transfer` hanya memakai baris bank dan mengabaikan tunai; `tunai_transfer` memakai keduanya; metode kosong/nilai lain memakai perilaku lama (jumlahkan yang ada). Baris bank bernominal `0` atau tanpa nominal diabaikan; nominal negatif, nominal tanpa rekening, dan rekening di luar scope unit ditolak sebelum menyentuh `pembayaran_bank`/jurnal. — `App\Services\Sales\PenjualanPaymentSplit`; `Penjualan::insert_penjualan`; `PenjualanPaymentSplitTest`
18. Rekening yang boleh dipakai transaksi penjualan sama dengan yang tampil di halaman: rekening unit sesi + rekening shared yang dialokasikan, tanpa rekening unit lain dan tanpa rekening Finance/HO (`ModelBank::getBankDalamScopeUnit`). Validasi diulang di server; `id` bank dari request tidak dipercaya. — `Penjualan::index`; `Penjualan::insert_penjualan`; `ModelBank::getBankDalamScopeUnit`
19. `hutang` dan `keterangan` dihitung server dari nominal yang benar-benar diterima: `hutang = max(0, total_penjualan − (cash + bank))`, `keterangan = 'Lunas'` bila `hutang == 0`, selain itu `'Belum Lunas'`. Hidden input `hutang` dari klien tidak dibaca. Kurang bayar tetap tidak membentuk piutang pelanggan (SL-02) dan tetap tercatat `Belum Lunas`. — `Penjualan::insert_penjualan`; `PenjualanPaymentSplitTest::testControllerMemakaiServiceDanScopeBankUnit`
20. Halaman `/penjualan` mengirim ID pelanggan lewat hidden input `selectedidpelanggan` di dalam form; modal pelanggan berada di luar `<form>` supaya tidak terpotong `overflow/transform` lembar dan tidak menutupi tombolnya sendiri. Tombol **Uang pas** mengisi tunai sebesar sisa setelah transfer pada mode `tunai_transfer`, bukan total penuh, agar split payment tidak otomatis kelebihan bayar. — `Views/transaksi/penjualan.php`; `PenjualanPageTest`; QA browser (lihat `.impeccable/surfaces/penjualan.md`)
21. Dropdown **Staff** (`sales_by`) hanya memuat akun dari unit sesi. Pengecualian: Finance (`ID_JABATAN` 0), Admin root (1), dan Manager (34) melihat staff semua unit. Server memvalidasi ulang `sales_by` terhadap daftar yang sama sebelum menyimpan; staff di luar scope ditolak dengan pesan `Staff yang dipilih tidak tersedia untuk unit ini.` — `Penjualan::resolveStaffUnit`; `Penjualan::index`; `Penjualan::insert_penjualan`; `ModelAuth::getdataakun`; `PenjualanStaffScopeTest`
22. Picker tidak menyediakan kontrol "Pilih semua"; setiap produk/bundle/jasa dicentang manual. Baris tab Produk menampilkan HPP efektif (`hpp_unit` → `hpp_barang` → `barang.harga_beli`, resolver yang sama dengan `detail_penjualan.hpp_penjualan`) dan suplier dari baris `stok_awal` terbaru unit (`ModelBarang::getSuplierPerUnit`). Bundle dan jasa tidak menampilkan HPP/suplier karena tidak punya pasangan biaya/suplier tunggal. Suplier ikut menjadi kata kunci pencarian. — `Penjualan::index`; `Views/transaksi/penjualan.php`; `PenjualanPageTest`; `PenjualanPickerMoneyTest`
23. Diskon 100% (total `Rp 0`) sah dan bisa disimpan. Server menghitung nilai kotor dan total diskon dari rincian `produk[]`, lalu menolak: nilai kotor ≤ 0, diskon negatif atau melebihi nilai kotor, total negatif, total 0 tanpa diskon yang menutup seluruh nilai kotor, total diskon yang tidak sama dengan rincian, dan pembayaran tunai/bank pada transaksi total 0. Header `penjualan` memakai total bersih dan diskon hasil validasi server. Struk menampilkan Subtotal = nilai kotor, Total Diskon, PPN, Total = nilai bersih (sama dengan reprint riwayat). — `App\Services\Sales\PenjualanSaleValidator`; `Penjualan::insert_penjualan`; `Views/cetak/cetak_penjualan_thermal.php`; `PenjualanSaleValidatorTest`; `PenjualanReceiptDiscountTest`

## 6. Acceptance Criteria
- [ ] Input penjualan menyimpan header+detail+pembayaran bank+jurnal dalam satu transaksi.
- [ ] Kode invoice unik per unit per hari.
- [ ] Struk PDF dibuat & dapat diunduh.
- [ ] Pembayaran tunai dan bank tercatat; total bayar = tunai + bank.
- [ ] Split payment mengikuti `metode_bayar`: tunai mengabaikan bank, transfer mengabaikan tunai, dan `tunai_transfer` menjumlahkan keduanya.
- [ ] Rekening bank di luar scope unit ditolak server, dan transaksi tanpa rekening untuk nominal transfer tidak tersimpan.
- [ ] Status `Lunas`/`Belum Lunas` dihitung server dari total diterima; hidden `hutang` tidak memengaruhi hasil.
- [ ] Kurang bayar tetap tersimpan sebagai `Belum Lunas` tanpa membuat piutang pelanggan.
- [ ] Dropdown Staff hanya memuat unit sendiri; Finance/Admin root/Manager melihat semua unit, dan `sales_by` di luar scope ditolak server.
- [ ] Picker produk menampilkan HPP dan suplier per baris, tanpa kontrol "Pilih semua".
- [ ] Harga jual picker POS mengikuti harga unit transaksi dan transaksi lama tidak berubah saat override diubah.
- [ ] Harga produk/jasa yang tersimpan sebagai `decimal(15,2)` tampil dan masuk keranjang sebagai rupiah bulat (tidak dikali 100).
- [ ] Diskon 100% dapat disimpan dengan Total `Rp 0` dan struk menampilkan Total `Rp 0`; diskon berlebih, total 0 tanpa diskon penuh, dan pembayaran pada total 0 ditolak.
- [ ] Jadwayat penjualan dapat dicari, dilihat detailnya, diekspor.

## 7. Dependensi
- `ModelPenjualan`, `ModelDetailPenjualan`, `ModelPembayaranBank`, `ModelHppUnit`/`ModelHppBarang`, `ModelJurnal`, `ModelBundle/ModelDetailBundle`, `ModelPelanggan`, `ModelUnit`, `ModelAuth`.
- Modul: Master Data (produk/pelanggan/bank), Hutang Piutang (piutang legacy), Jurnal, KPI (omzet).

## 8. Status & Tandai
- ✅ Terverifikasi: alur input, invoice, pembayaran, jurnal, transaksi atomik.
- ✅ Terverifikasi: stok penjualan dihitung oleh VIEW `stok_barang` dari `detail_penjualan` (bukan update stok eksplisit).
- ✅ Terverifikasi: HPP detail penjualan baru memakai `hpp_unit` produk+unit; produk tanpa baris per unit tetap memakai `hpp_barang`/`barang.harga_beli`. — `HppPerUnitTest`
- ✅ Terverifikasi: jasa dijual lewat tab Jasa tanpa stok; tercatat di `detail_penjualan` untuk omset tetapi tidak masuk persediaan. — `JasaBukanProdukTest`
- ✅ Terverifikasi: HPP jasa (bagian teknisi) disimpan sebagai `barang.harga_beli` dan dicerminkan ke `hpp_barang`/`hpp_unit`, sehingga `hpp_penjualan` transaksi baru memuat HPP dan omzet = harga jual − HPP. — `TarifJasaPageTest`
- ✅ Terverifikasi: picker POS produk dan jasa memakai harga jual efektif unit (`harga_jual_unit` fallback `barang.harga`); transaksi lama tetap snapshot. — `HargaJualUnitTest`, `HargaJualPickerTest`
- ✅ Terverifikasi: harga picker POS dikirim ke markup sebagai rupiah bulat, sehingga `decimal(15,2)` `"55000.00"` tidak berubah menjadi `5.500.000` di keranjang. — `PenjualanPickerMoneyTest`
- ✅ Terverifikasi: split payment tunai/transfer/tunai+transfer dinormalisasi service, rekening divalidasi terhadap scope unit, dan `hutang`/`keterangan` dihitung server. — `PenjualanPaymentSplitTest`
- ✅ Terverifikasi: halaman `/penjualan` satu lembar, mempertahankan field/modal/hook, mengirim `selectedidpelanggan` lewat hidden input, dan tombol Uang pas menutup sisa setelah transfer. — `PenjualanPageTest`; QA Playwright 14/14 pada desktop, dark, dan mobile
- ✅ Terverifikasi: dropdown Staff dibatasi ke unit sesi, dengan pengecualian Finance/Admin root/Manager, dan server menolak `sales_by` di luar scope. — `PenjualanStaffScopeTest`
- ✅ Terverifikasi: picker produk menampilkan HPP efektif dan suplier unit, tanpa kontrol "Pilih semua". — `PenjualanPageTest`; `PenjualanPickerMoneyTest`
- ✅ Terverifikasi: total 0 karena diskon 100% diterima validator, sedangkan diskon berlebih/total 0 tanpa diskon penuh/pembayaran pada total 0 ditolak; struk memakai Subtotal kotor dan Total bersih. — `PenjualanSaleValidatorTest`; `PenjualanReceiptDiscountTest`
- ⚠️ Gap tinggi:
  1. Penjualan kredit tidak membentuk piutang pelanggan/registry H/P; `Piutang` legacy adalah piutang pegawai. — SL-02
  2. `detail_penjualan.sub_total` bundle digandakan per komponen; laporan omzet/laba dapat salah. — SL-03
  3. `Piutang::bayar_piutang` mengambil `sisa` dari POST, tidak memposting kas/jurnal, dan status Lunas tidak pernah tercapai. — SL-06/HP-02
  4. `kode_invoice` tidak dijamin unik oleh database. — SL-04
  5. Tidak ada validasi stok sebelum penjualan. — SL-05
- 🔶 Inferensi: aturan bisnis penjualan kredit dan definisi omzet bundle memerlukan konfirmasi user.
- Test: hanya `tests/RiwayatPenjualanViewTest.php` untuk view riwayat; tidak ada test end-to-end `Penjualan`/piutang legacy. Lihat `docs/prd/audit/PRD_AUDIT_REPORT.md` X-08.
