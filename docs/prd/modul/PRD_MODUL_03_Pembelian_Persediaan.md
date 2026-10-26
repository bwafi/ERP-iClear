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
5. Sinkronisasi projection hutang supplier ke registry H/P (best-effort). — `Pembelian::insert` → `HutangPiutangService::syncFromPembelian`
6. Transaksi atomik untuk header+detail+jurnal+pembayaran. — `Pembelian::insert`
7. `Pembelian::insert_produk` (route `insert_produk` juga dipakai dari halaman pembelian) membuat barang baru dengan kode `kodeKategori+nomor(2)`. — `Pembelian::insert_produk`
8. [⚠️] Detail alur stok opname, mutasi, dan retur serta mekanisme nilai/penyesuaian stok belum diverifikasi penuh di dokumen ini (belum dibaca lengkap: `StokOpname.php`, `MutasiStok.php`, `ReturBarangRusak.php`); lihat gap di bawah.

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
- ⚠️ Belum diverifikasi penuh: alur detail Opname (mulai/simpan/finalisasi/reopen/batal), Mutasi (terima/batal terima), Retur barang rusak (ubah status), serta integrasinya dengan `StokBarang` (migration `StokBarangHanyaMutasiDiterima` mengindikasikan stok hanya bertambah dari mutasi yang diterima).
- Test yang ad: `StokOpnameBukanProdukTest`, `StokBarangHanyaMutasiDiterimaTest`, `MutasiNoNotaUniqueTest`, `MutasiStokPickerTest`, `ReturBarangRusakTest`, `StokBarangKurangiSparepartGaransiTest`.
