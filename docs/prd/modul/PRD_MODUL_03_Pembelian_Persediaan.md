# PRD Modul 03 — Pembelian & Persediaan (Stok)

| | |
|---|---|
| Kode Modul | PB-03 |
| Sumber analisis | baca `app/Controllers/Pembelian.php` (full), `Routes.php`, model stok |
| Status verifikasi | ✅ fakta / 🔶 inferensi / ⚠️ ketidakpastian |

---

## 1. Tujuan
Mencatat pembelian dari supplier (dengan hutang/PPN), mengelola stok (awal, kartu stok, mutasi antar-unit, opname, stok minimum, stok HP), serta retur (supplier & barang rusak).

## 2. Aktor
- Pegawai/frontliner (input pembelian).
- Finance/manager & staff gudang (stok, opname, mutasi, retur).

## 3. Preconditions
- Login; unit aktif.
- Supplier & barang tersedia; data stok awal (untuk satuan) tersedia.

## 4. Alur Utama
### 4.1 Pembelian — `Pembelian::insert`
1. Ambil `produk[]`; bersihkan angka via `cleanRupiah`.
2. Generate nota `PCR{unit}{Ymd}{seq4}` (per unit per hari).
3. Upload foto nota (`public/foto_nota`).
4. Ciptakan pelanggan baru bila kosong & ada data nama/nomor.
5. Pembayaran tunai + bank (`pembayaran_bank`); kode `PBL{date}{unit}{rand4}`.
6. Insert header `pembelian` + detail `detail_pembelian` (HPP `hitung_hpp`, PPN 11% bila dicentang), lalu sinkronkan `hpp_unit` untuk barang+unit pembelian. Bila HPP tidak valid, seluruh transaksi di-rollback dan pembelian dibatalkan.
7. Susun jurnal template: `pembelian_lunas_tunai`, `pembelian_lunas_bank_{id}`, `pembelian_ppn_lunas`, `pembelian_aksesoris`, `pembelian_sparepart`, `pembelian_lunas_hp_baru_ppn`, `pembelian_lunas_hp_baru`, `pembelian_lunas_hp_second`.
8. `HutangPiutangService::syncFromPembelian(id)` disinkronkan (best-effort; gagal hanya di-log).
9. Satu transaksi `transStart/transComplete`.
10. Frontliner diisi dari akun yang login (`session('ID_AKUN')`), bukan pilihan form; form hanya menampilkan nilai read-only dan POST `frontliner` diabaikan.
### 4.2 Lainnya (routes)
- Stok awal `StokAwal::index/input_stokawal/insert/stok`.
- Kartu stok `Kartu_Stok::index/dt/export`; status aktif produk per unit `Kartu_Stok::updateStatus` (POST `kartu_stok/status`, filter auth+csrf).
- Mutasi stok `MutasiStok::index/produk/masuk/insert/terima/batalTerima`; riwayat & cetak nota mutasi.
- Stok opname `StokOpname::index/mulai/simpananinalisasi/reopen/batal` (filter CSRF).
- Stok HP `StokHP::index`, Stok minimum `StokMinimum::index/update`, Produk terlaris `Produk_Terlaris::index`.
- Retur supplier `Retur_Suplier::index/insert`; retur barang rusak `ReturBarangRusak::index/simpan/ubahStatus/batal`; barang rusak `BarangRusak::index/input/insert_barang_rusak`.

## 5. Business Rules (Bukti)
1. Nota pembelian `PCR{unit}{date}{seq4}` per unit per hari. — `Pembelian::insert`
2. PPN per item = 11% dari subtotal setelah diskon bila item dicentang ppn. — `Pembelian::insert`
3. HPP disimpan per detail (`hitung_hpp`) dari `ModelHppBarang`. — `Pembelian::insert`
4. Status = `Lunas` bila `sisa==0`, else `Belum Lunas`. — `Pembelian::insert`
5. Sinkronisasi projection hutang supplier ke registry H/P (best-effort; gagal hanya di-log). **Total projection memakai `pembelian.total_bayar`, bukan `total_transaksi`, sehingga untuk pembelian sebagian total tidak sama dengan sisa + dibayar.** — `Pembelian::insert` → `HutangPiutangService::syncFromPembelian` (PI-05/HP-01)
6. Transaksi atomik untuk header+detail+jurnal+pembayaran. — `Pembelian::insert`
7. `Pembelian::insert_produk` (route `insert_produk` juga dipakai dari halaman pembelian) membuat barang baru dengan kode `kodeKategori+nomor(2)`. **Route ini tidak memakai filter `auth`.** — `Routes.php:140`; `Pembelian.php:412` (PI-06)
8. `StokMinimum::update` menulis ke VIEW `stok_barang`, yang bukan view updatable; `stok_minimum` berada di tabel `barang`. — `ModelStokBarang::updateStokMinimum` (PI-01)
9. Finalisasi stok opname tidak lagi mengoreksi `stok_akhir`; migration 2026-10-07 menghapus komponen opname dari view, dan view final menulis `0 AS stok_opname` untuk cabang opname. — `2026-10-07-000001_RemoveStokOpnameSelisihFromStokAkhir.php`; `2026-10-23-000500...:80` (PI-02)
10. `StokOpnameService::batalDraft()` menulis aksi `batal_draft`, tetapi ENUM audit hanya `mulai|simpan|finalisasi|reopen`. — `StokOpnameService.php:710`; `2026-09-28-000001...:127` (PI-03)
11. Retur supplier/customer tidak memakai transaksi, mengambil unit per item dari POST, dan route-nya tanpa CSRF. — `Retur_Suplier.php:80,96`; `Retur_Customer.php`; `Routes.php:313,321` (PI-04)
12. HPP Kartu Stok dibaca per produk+unit dari `hpp_unit`, fallback `hpp_barang` (global) lalu `barang.harga_beli`. Seed diambil dari baris `stok_awal.harga_beli` terakhir yang > 0. Koreksi HPP satu baris/unit boleh dilakukan Finance (0), Admin root (1), Manager (34), ADMIN/KASIR (35 & 47), dan Kepala Toko (41); nilai tersimpan sebagai `manual` dan tidak ditimpa sync stok awal berikutnya (tetapi tetap ditimpa pembelian terbaru, rule 15). Role lain ditolak server (403). Resolver yang sama dipakai penjualan dan service. Transaksi lama tetap memakai snapshot `detail_penjualan.hpp_penjualan`/`service_sparepart.hpp_penjualan`. — `2026-11-07-000100_CreateHppUnit.php`; `ModelHppUnit`; `Kartu_Stok::bolehKelolaProdukUnit`/`updateHpp`; `ModelKartuStok::kartuStokBuilder`
13. Produk dapat dinonaktifkan per unit lewat tabel sparse `barang_unit` (`barang_idbarang`, `unit_idunit`, `aktif`); tanpa baris berarti aktif. View `stok_barang` mengekspos `COALESCE(MAX(barang_unit.aktif), 1) AS aktif_unit`. Produk nonaktif tetap tampil di Kartu Stok (badge "Nonaktif") tetapi disaring dari stok opname (`StokOpnameService::createPeriode`), picker penjualan (`ModelStokBarang::getAllBarang2`/`getSparepart`), pembelian/stok awal (`ModelBarang::getAllBarang`), picker mutasi (`ModelBarang::getProdukPicker`), dan sparepart service (`Service::search_sparepart_ajax`). Nonaktif ditolak bila `stok_akhir != 0` (termasuk negatif); aktivasi selalu boleh. Role sama dengan koreksi HPP; role lain 403. Soft delete master (`barang.deleted`) tetap terpisah dan berlaku global. Data historis/stok/laporan tidak berubah. Produk nonaktif boleh dipakai lagi lewat input stok awal: `StokAwal::insert` (`insert/stokawal`, filter `auth`) menyimpan stok lalu memanggil `ModelBarangUnit::aktifkanJikaNonaktif`, sehingga produk otomatis aktif kembali di unit itu saja (baris `barang_unit.aktif` diubah ke 1; produk yang sudah aktif tidak dibuatkan baris baru). Ini menjaga aturan nonaktif-hanya-saat-stok-0 tanpa langkah aktivasi manual. — `2026-11-07-000200_CreateBarangUnit.php`; `ModelBarangUnit`; `Kartu_Stok::bolehKelolaProdukUnit`/`updateStatus`; `StokAwal::insert`
14. Jasa (PUP LCD, jasa mesin, cleaning, dll.) bukan persediaan. Satu-satunya penanda adalah `barang.jenis_barang` ENUM `produk|jasa` (default `produk`); view `stok_barang` hanya memuat `jenis_barang='produk'` sehingga jasa otomatis keluar dari Kartu Stok, nilai stok, stok opname, picker mutasi/pembelian/stok awal, dan sparepart service tanpa daftar awalan nama. Jasa tetap baris `barang` agar `detail_penjualan`, retur, DP, tutup kasir, dan KPI tidak berubah query; qty penjualan jasa hanya angka transaksi dan tidak pernah mengurangi persediaan (tidak ada baris jasa di view). Stok awal jasa lama tidak dimigrasi ulang dan berhenti dihitung sejak view diperbarui; data historis tidak ditulis ulang. Jasa dikelola di menu Master Jasa → Tarif Jasa (`TarifJasa`, filter `auth`+`csrf`), tanpa field stok, dengan field HPP untuk bagian teknisi (lihat PRD Modul 02 rule 14). Grup menu Master Jasa berada di section DATA MASTER tepat setelah grup Produk (`menu.urutan=1007`). — `2026-11-08-000100_AddJenisBarangToBarang.php`; `2026-11-08-000200_AddTarifJasaMenu.php`; `2026-11-09-000100_MoveMasterJasaMenu.php`; `ModelStokBarang::getJasa`; `TarifJasa`; `JasaBukanProdukTest`; `AddTarifJasaMenuTest`; `MoveMasterJasaMenuTest`
15. Pembelian adalah sumber HPP per unit. Setiap detail pembelian menulis `hpp_unit` untuk `barang_idbarang` + `unit_idunit` pembelian dengan `sumber='pembelian'`, menimpa nilai `manual` sekalipun. Nilai = `(harga_beli × jumlah − diskon + biaya_tambahan) / jumlah`; PPN tidak masuk HPP. Harga beli terakhir menang, bukan rata-rata. Hanya unit pembelian yang berubah; `barang.harga_beli` master dan `hpp_barang` tidak diubah. Produk tanpa baris `hpp_unit` dibuatkan baris baru. Bila qty/harga tidak valid atau HPP ≤ 0, pembelian dibatalkan seluruhnya. Stok Awal tetap untuk saldo awal/migrasi, bukan pembelian harian. Picker pembelian menampilkan HPP per unit (fallback `harga_beli`). — `Pembelian::insert`; `ModelHppUnit::syncFromPembelian`; `Views/transaksi/pembelian.php`; `HppPerUnitTest`; `PembelianHppUnitTest`
16. Frontliner pembelian selalu akun yang login (`session('ID_AKUN')`), sama dengan `input_by`. Form menampilkan nama user read-only tanpa input pilihan; nilai POST `frontliner` tidak dipakai. — `Pembelian::index/insert`; `Views/transaksi/pembelian.php`; `PembelianHppUnitTest`

## 6. Acceptance Criteria
- [ ] Input pembelian tersimpan atomik beserta jurnal & pembayaran.
- [ ] Pembelian memperbarui HPP unit pembelian dari harga beli terakhir tanpa mengubah unit lain atau master `barang.harga_beli`.
- [ ] Frontliner pembelian tidak dapat diganti dan selalu mengikuti akun yang login.
- [ ] Nota unik per unit per hari.
- [ ] Foto nota dapat diunggah.
- [ ] Projection hutang supplier muncul di modul Hutang Piutang.
- [ ] Kartu stok & riwayat mutasi dapat dibaca dan diekspor.
- [ ] Jasa tidak muncul di Kartu Stok, nilai stok, stok opname, pembelian, stok awal, mutasi, atau sparepart service walau namanya tidak berawalan khusus.

## 7. Dependensi
- `ModelPembelian/ModelDetailPembelian`, `ModelStokAwal`, `ModelStokBarang`, `ModelKartuStok`, `ModelMutasiStok/ModelDetailMutasi`, `ModelStokOpname*`, `ModelReturSuplier/ModelReturBarangRusak/ModelBarangRusak`, `ModelHppBarang`, `ModelHppUnit`, `ModelJurnal`, `ModelPembayaranBank`, `HutangPiutangService`.
- Modul: Master Data (barang/supplier/kategori), Keuangan (jurnal/kas), Hutang Piutang.

## 8. Status & Tandai
- ✅ Terverifikasi: alur pembelian (nota, PPN, HPP, jurnal, sync H/P, atomik).
- ✅ Terverifikasi: VIEW `stok_barang` mengurangi penjualan, menambah pembelian/mutasi diterima/retur, dan mengabaikan opname pada `stok_akhir`; retur customer/supplier serta pemakaian garansi ikut dihitung.
- ✅ Terverifikasi: HPP Kartu Stok dan Nilai Stok memakai `hpp_unit` per produk+unit dengan fallback `hpp_barang`/`barang.harga_beli`; pembelian menimpa HPP unit itu dengan harga beli terakhir (bukan rata-rata, nilai manual ikut ditimpa) tanpa mengubah unit lain atau master. Override manual dan alur pembelian diuji di `HppPerUnitTest` dan `PembelianHppUnitTest`.
- ✅ Terverifikasi: nonaktif produk per unit memakai `barang_unit`; produk nonaktif tersaring dari stok opname dan picker transaksi unit itu, tetap tampil di Kartu Stok, dan ditolak bila stok unit != 0. Input stok awal untuk produk nonaktif mengaktifkannya kembali di unit itu tanpa menyentuh unit lain. Diuji di `BarangNonaktifUnitTest`, `BarangUnitMigrationTest`, `StokAwalReaktivasiTest`, `StokOpnameBukanProdukTest`, `MutasiStokPickerTest`.
- ✅ Terverifikasi: `barang.jenis_barang` memisahkan jasa dari produk; view `stok_barang` hanya produk sehingga nilai HPP persediaan tidak lagi memuat jasa, dan jasa tidak ikut stok opname/pembelian/stok awal/mutasi. Backfill menandai nama berawalan PUP/JASA/MESIN/RESTORE/FLASH/CLEANING. Diuji di `JasaBukanProdukTest` (kolom, view, seed opname, picker, `down()`).
- ⚠️ Gap tinggi:
  1. Projection hutang memakai `total_bayar` → total dan rekonsiliasi salah untuk pembelian tidak lunas. — PI-05/HP-01
  2. `StokMinimum::update` menulis ke VIEW `stok_barang`; fitur stok minimum tidak berfungsi. — PI-01
  3. `batal_draft` tidak ada di ENUM audit stok opname. — PI-03
  4. Retur supplier/customer tidak atomik, unit item dari POST, tanpa CSRF. — PI-04
  5. Route `insert_produk` dari halaman pembelian tanpa auth. — PI-06
- 🔶 Inferensi/keputusan bisnis: apakah opname seharusnya mengoreksi `stok_akhir` (kode saat ini tidak) perlu konfirmasi user. — PI-02
- ⚠️ Batas iterasi HPP per unit: input HPP manual di Stok Awal masih dinonaktifkan sehingga `stok_awal.harga_beli` disalin dari master. HPP harian kini berasal dari pembelian; koreksi manual tetap lewat modal Kartu Stok (Finance, Admin root, Manager, ADMIN/KASIR, Kepala Toko) dan akan ditimpa pembelian berikutnya.
- Test yang ada: `BarangNonaktifUnitTest`, `BarangUnitMigrationTest`, `StokAwalReaktivasiTest`, `HppPerUnitTest`, `PembelianHppUnitTest`, `StokOpnameBukanProdukTest`, `StokBarangHanyaMutasiDiterimaTest`, `MutasiNoNotaUniqueTest`, `MutasiStokPickerTest`, `ReturBarangRusakTest`, `StokBarangKurangiSparepartGaransiTest`. Tidak ada test untuk projection `total_bayar`, update stok minimum, `batal_draft`, retur atomik/scope, dan route `insert_produk`.
