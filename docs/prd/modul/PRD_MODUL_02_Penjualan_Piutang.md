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
3. Potong pembayaran: `bayar_tunai` (POST `bayar`) dan `bank[]` (per baris → `pembayaran_bank`).
4. Susun jurnal: tunai (`penjualan_lunas_tunai`), bank (`penjualan_lunas_bank_{bankid}`), PPN, diskon, aksesoris/sparepart (`penjualan_acc_cash`), HP baru PPN/non-PPN, HP second.
5. Insert header `penjualan` + detail `detail_penjualan` (HPP per item dari `ModelHppBarang`; bundle dipecah per barang penyusun).
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
5. HPP per item diambil dari `ModelHppBarang`; bundle dipecah (jumlah × qty bundle). — `insert_penjualan`
6. Kategori akun jurnal dibedakan: HP baru PPN/non-PPN/second, sparepart, aksesoris, PPN, diskon. — `insert_penjualan`
7. Penjualan berjalan dalam SATU transaksi (header+detail+jurnal+pembayaran bank). — `insert_penjualan`
8. Kode pembayaran bank = `PJL{date}{unit}{rand4}`; di-refer ke `id_referensi` penjualan setelah insert. — `insert_penjualan`
9. [⚠️] Potongan stok saat penjualan TIDAK terlihat eksplisit pada `Penjualan::insert_penjualan` maupun `ModelPenjualan/ModelDetailPenjualan` (tidak ada panggilan kurangi stok). Perlu verifikasi mekanisme stok (kartu stok) untuk memastikan stok benar-benar berkurang.

## 6. Acceptance Criteria
- [ ] Input penjualan menyimpan header+detail+pembayaran bank+jurnal dalam satu transaksi.
- [ ] Kode invoice unik per unit per hari.
- [ ] Struk PDF dibuat & dapat diunduh.
- [ ] Pembayaran tunai dan bank tercatat; total bayar = tunai + bank.
- [ ] Jadwayat penjualan dapat dicari, dilihat detailnya, diekspor.

## 7. Dependensi
- `ModelPenjualan`, `ModelDetailPenjualan`, `ModelPembayaranBank`, `ModelHppBarang`, `ModelJurnal`, `ModelBundle/ModelDetailBundle`, `ModelPelanggan`, `ModelUnit`, `ModelAuth`.
- Modul: Master Data (produk/pelanggan/bank), Hutang Piutang (piutang legacy), Jurnal, KPI (omzet).

## 8. Status & Tandai
- ✅ Terverifikasi: alur input, invoice, pembayaran, jurnal, transaksi atomik.
- ⚠️ Belum terverifikasi: mekanisme pengurangan stok pada penjualan; test otomatis end-to-end controller `Penjualan` (hanya ada `tests/RiwayatPenjualanViewTest.php` untuk view riwayat).
- 🔶 Inferensi: piutang pelanggan pada alur penjualan "Belum Lunas" belum terbukti menulis `piutang_pelanggan`/registry H/P secara otomatis (perlu verifikasi integrasi Hutang Piutang).
