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
6. Insert header `pembelian` + detail `detail_pembelian` (HPP `hitung_hpp`, PPN 11% bila dicentang).
7. Susun jurnal template: `pembelian_lunas_tunai`, `pembelian_lunas_bank_{id}`, `pembelian_ppn_lunas`, `pembelian_aksesoris`, `pembelian_sparepart`, `pembelian_lunas_hp_baru_ppn`, `pembelian_lunas_hp_baru`, `pembelian_lunas_hp_second`.
8. `HutangPiutangService::syncFromPembelian(id)` disinkronkan (best-effort; gagal hanya di-log).
9. Satu transaksi `transStart/transComplete`.
### 4.2 Lainnya (routes)
- Stok awal `StokAwal::index/input_stokawal/insert/stok`.
- Kartu stok `Kartu_Stok::index/dt/export`.
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

## 6. Acceptance Criteria
- [ ] Input pembelian tersimpan atomik beserta jurnal & pembayaran.
- [ ] Nota unik per unit per hari.
- [ ] Foto nota dapat diunggah.
- [ ] Projection hutang supplier muncul di modul Hutang Piutang.
- [ ] Kartu stok & riwayat mutasi dapat dibaca dan diekspor.

## 7. Dependensi
- `ModelPembelian/ModelDetailPembelian`, `ModelStokAwal`, `ModelStokBarang`, `ModelKartuStok`, `ModelMutasiStok/ModelDetailMutasi`, `ModelStokOpname*`, `ModelReturSuplier/ModelReturBarangRusak/ModelBarangRusak`, `ModelHppBarang`, `ModelJurnal`, `ModelPembayaranBank`, `HutangPiutangService`.
- Modul: Master Data (barang/supplier/kategori), Keuangan (jurnal/kas), Hutang Piutang.

## 8. Status & Tandai
- ✅ Terverifikasi: alur pembelian (nota, PPN, HPP, jurnal, sync H/P, atomik).
- ✅ Terverifikasi: VIEW `stok_barang` mengurangi penjualan, menambah pembelian/mutasi diterima/retur, dan mengabaikan opname pada `stok_akhir`; retur customer/supplier serta pemakaian garansi ikut dihitung.
- ⚠️ Gap tinggi:
  1. Projection hutang memakai `total_bayar` → total dan rekonsiliasi salah untuk pembelian tidak lunas. — PI-05/HP-01
  2. `StokMinimum::update` menulis ke VIEW `stok_barang`; fitur stok minimum tidak berfungsi. — PI-01
  3. `batal_draft` tidak ada di ENUM audit stok opname. — PI-03
  4. Retur supplier/customer tidak atomik, unit item dari POST, tanpa CSRF. — PI-04
  5. Route `insert_produk` dari halaman pembelian tanpa auth. — PI-06
- 🔶 Inferensi/keputusan bisnis: apakah opname seharusnya mengoreksi `stok_akhir` (kode saat ini tidak) perlu konfirmasi user. — PI-02
- Test yang ada: `StokOpnameBukanProdukTest`, `StokBarangHanyaMutasiDiterimaTest`, `MutasiNoNotaUniqueTest`, `MutasiStokPickerTest`, `ReturBarangRusakTest`, `StokBarangKurangiSparepartGaransiTest`. Tidak ada test untuk projection `total_bayar`, update stok minimum, `batal_draft`, retur atomik/scope, dan route `insert_produk`.
