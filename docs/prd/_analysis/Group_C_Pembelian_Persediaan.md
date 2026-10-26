# Group C — Pembelian & Persediaan/Stok (Analisis ERP CodeIgniter 4)

> Hanya baca. Tidak ada perubahan source/db/config. Semua bukti = `File.php::method()` atau migrasi/test.
> Tanggal analisis: 2026-10-26.

---

## 1. Ruang lingkup

Modul pembelian & persediaan:
- **Pembelian**: `Pembelian`, `Riwayat_pembelian`, `ModelPembelian`, `ModelDetailPembelian`, `ModelHppBarang`, `ModelBarang`, `ModelKategori`, `ModelSubKategori`.
- **Stok/barang**: `StokAwal`, `Kartu_Stok`, `StokHP`, `StokMinimum`, `ModelStokBarang`, `ModelKartuStok`, `ModelStokAwal`, `ModelStokHP`.
- **Mutasi stok**: `MutasiStok`, `Riwayat_MutasiStok`, `ModelMutasiStok`, `ModelDetailMutasi`, `ModeKasBank` (panggilan).
- **Stok opname v2**: `StokOpname`, `Riwayat_StokOpname`, `StokOpnameService`, `ModelStokOpname`, `ModelStokOpnamePeriode`, `ModelStokOpnameDraft`.
- **Retur**: `Retur_Suplier`, `ReturBarangRusak` (klaim garansi), `BarangRusak` (legacy), `Riwayat_ReturPembelian`, `Riwayat_ReturPenjualan`, `ModelReturSuplier`, `ModelReturBarangRusak`, `ModelBarangRusak`, `ModelBarangRusakClaim`.

Catatan penting: **`stok_barang` adalah VIEW (bukan tabel fisik)**, dihitung dari banyak tabel transaksi (lihat §6). `ModelKartuStok` dan `ModelStokBarang` menunjuk ke view ini.

---

## 2. Endpoint/Routes

(sumber: `app/Config/Routes.php`; semua GET/POST dengan filter `auth` kecuali disebut)

### Pembelian
| Method | Route | Controller::method |
|---|---|---|
| GET | `/pembelian` | `Pembelian::index` |
| POST | `insert_pembelian` | `Pembelian::insert` |
| POST | `insert_produk` | `Pembelian::insert_produk` (tanpa filter auth tertera di route) |
| GET | `riwayat_pembelian` | `Riwayat_pembelian::index` |
| POST | `riwayat_pembelian/export` | `Riwayat_pembelian::export` |

### Stok
| Method | Route | Controller::method |
|---|---|---|
| GET | `stok_awal` | `StokAwal::index` |
| GET | `input_stokawal` / `input_stokawal/(:any)` | `StokAwal::input_stokawal` |
| POST | `insert/stokawal` | `StokAwal::insert` |
| GET | `kartu_stok` | `Kartu_Stok::index` |
| GET | `kartu_stok/dt` | `Kartu_Stok::dt` |
| POST | `export/kartu_stock` | `Kartu_Stok::export` |
| GET | `stok_hp` | `StokHP::index` |
| GET | `stok_minimum` | `StokMinimum::index` |
| POST | `update_stokminimum` | `StokMinimum::update` |

### Mutasi stok
| Method | Route | Controller::method |
|---|---|---|
| GET | `mutasi_stok` | `MutasiStok::index` |
| GET | `mutasi_stok/produk` | `MutasiStok::produk` (picker DataTables server-side) |
| GET | `mutasi_stok/masuk` | `MutasiStok::masuk` |
| POST | `mutasi_stok/terima/(:num)` | `MutasiStok::terima` |
| POST | `mutasi_stok/batal-terima/(:num)` | `MutasiStok::batalTerima` |
| POST | `insert_mutasi` | `MutasiStok::insert` |
| GET | `riwayat_mutasi` | `Riwayat_MutasiStok::index` |
| POST | `riwayat_mutasi/export` | `Riwayat_MutasiStok::export` |
| GET | `cetak/invoice_mutasi/(:num)` | `MutasiStok::cetak_notamutasi` (PDF mpdf) |

### Stok opname
| Method | Route | Controller::method |
|---|---|---|
| GET | `stok_opname` | `StokOpname::index` |
| POST | `stok_opname/mulai` | `StokOpname::mulai` |
| POST | `stok_opname/simpan` | `StokOpname::simpan` |
| POST | `stok_opname/finalisasi` | `StokOpname::finalisasi` |
| POST | `stok_opname/reopen` | `StokOpname::reopen` |
| POST | `stok_opname/batal` | `StokOpname::batal` |
| POST | `insert/stokopname`, `insert/stokopnamefix` | `StokOpname::simpan`/`finalisasi` |
| GET | `riwayat_stok_opname` | `Riwayat_StokOpname::index` |
| POST | `riwayat_stok_opname/export` | `Riwayat_StokOpname::export` |

> Opname group memakai filter `['auth','csrf']` (CSRF diaktifkan khusus di route ini).

### Retur & barang rusak
| Method | Route | Controller::method |
|---|---|---|
| GET | `retur_suplier` | `Retur_Suplier::index` |
| POST | `insert_retur_suplier` | `Retur_Suplier::insert` |
| GET | `riwayat_retur_pembelian` | `Riwayat_ReturPembelian::index` |
| POST | `riwayat_retur_pembelian/export` | `Riwayat_ReturPembelian::export` |
| GET | `riwayat_retur_penjualan` | `Riwayat_ReturPenjualan::index` |
| POST | `riwayat_retur_penjualan/export` | `Riwayat_ReturPenjualan::export` |
| GET | `barang_rusak` | `BarangRusak::index` |
| GET | `input_barang_rusak` | `BarangRusak::input` |
| POST | `insert_barang_rusak` | `BarangRusak::insert_barang_rusak` |
| GET | `retur_barang_rusak` | `ReturBarangRusak::index` |
| POST | `retur_barang_rusak/simpan` | `ReturBarangRusak::simpan` (auth+csrf) |
| POST | `retur_barang_rusak/ubah-status/(:num)` | `ReturBarangRusak::ubahStatus` (auth+csrf) |
| POST | `retur_barang_rusak/batal/(:num)` | `ReturBarangRusak::batal` (auth+csrf) |

---

## 3. Alur Pengguna utama & alternatif

### 3.1 Pembelian
1. `Pembelian::index` render form (produk, kategori, suplier, bank, pelanggan, frontliner).
2. `Pembelian::insert` (POST `insert_pembelian`):
   - Ambil produk line dari POST, bersihkan rupiah (`cleanRupiah`).
   - Bank: simpan `pembayaran_bank` + susun jurnal `pembelian_lunas_bank_{id}`.
   - Tunai: jurnal `pembelian_lunas_tunai`.
   - No nota otomatis: `PCR{unit}{ymd}{urutan4}` — urutan dari nota terakhir hari itu per unit (`no_nota_supplier` DESC, ambil 4 digit terakhir; bila tidak ada → `0001`).
   - Status `Lunas` bila `sisa==0`, else `Belum Lunas`.
   - Upload foto nota ke `public/foto_nota`.
   - Bila `id_pelanggan` kosong, buat/cari pelanggan baru (`getByNomor`).
   - Insert header `pembelian` → ID → update `pembayaran_bank` (`updateByKodePembayaran`).
   - Per produk: hitung subtotal, diskon, PPN (`0.11` bila ppn ada), kategori HP/Second/Sparepart/Aksesoris; insert `detail_pembelian`.
   - Susun jurnal PPN & per-kategori (`pembelian_ppn_lunas`, `pembelian_aksesoris`, `pembelian_sparepart`, `pembelian_lunas_hp_baru_ppn/hp_baru/hp_second`).
   - `HutangPiutangService::syncFromPembelian` dalam try/catch (best-effort).
   - Insert jurnal via `ModelJurnal::insertJurnal`. Semua dalam satu transaksi DB (`transStart/transComplete/transRollback`).
3. Alternatif: `insert_produk` membuat barang baru (`kode_barang = kodeKategori + 2 digit urutan`).

### 3.2 Stok awal & kartu stok
1. `StokAwal::input_stokawal`: pilih unit + cari barang (kategori/teks), paginasi 25.
2. `StokAwal::insert`: simpan `stok_awal` (satuan_terkecil, tipe relasi suplier/pelanggan, harga_beli ambil dari `barang.harga_beli`).
3. `Kartu_Stok::index/dt`: baca view `stok_barang` (DataTables server-side).

### 3.3 Mutasi stok
1. `MutasiStok::index`: scope unit — user cabang hanya unit sesi; Admin Root (jabatan 1) bisa pilih unit.
2. `MutasiStok::produk`: picker server-side (10 baris), hanya produk `stok_akhir > 0` di unit pengirim; role cabang dipaksa ke unit sesi.
3. `MutasiStok::insert` → `validasiInsert` + `simpanMutasi`:
   - Header `mutasi` + detail `detail_mutasi` dalam satu transaksi; `no_nota_mutasi = MTS{kirim}{ymd}{idMutasi}` diisi setelah insertID.
   - Validasi: unit ada, harus beda, pengirim harus unit sendiri (kecuali root), qty > 0, harus ada stok_awal+satuan, stok pengirim cukup.
4. `MutasiStok::terima` (unit penerima): set `status=1`, `tanggal_terima`, buat H/P antar unit via `ModeKasBank::buatHutangPiutangDariMutasi` (jatuh tempo = tanggal terima + 3 hari).
5. `MutasiStok::batalTerima` (hanya Admin Root): batalkan, soft-delete dokumen H/P (`deleted=1`), tolak bila ada pembayaran (`total_dibayar>0` atau `sisa!=total`).

### 3.4 Stok opname v2
1. `StokOpname::mulai` → `StokOpnameService::createPeriode`: buat periode DRAFT, seed hanya barang berstok (`stok_akhir != 0`), exclude non-produk (PUP/JASA/MESIN).
   - Satu unit = satu DRAFT terbuka (ditolak bila sudah ada).
2. `simpan` → `saveDraft`: update `jumlah_real` per barang (boleh dicicil, boleh dikosongkan); nilai non-numerik/negatif ditolak.
3. `finalisasi` → `finalize`: wajib semua barang berstok (`jumlah_komp != 0`) terisi; salin draft → `stok_opname` (final), tandai `is_reverted=0`, status periode `FINAL`.
4. `reopen` (Admin Root saja, wajib alasan): baris final lama `is_reverted=1`, periode kembali `DRAFT`.
5. `batal` (Admin Root saja, wajib alasan): periode DRAFT → `BATAL`.
6. Audit trail di `stok_opname_audit` (mulai/simpananinalisasi/reopen/batal_draft). KPI bulanan target 4 (`KPI_TARGET`).

### 3.5 Retur suplier
1. `Retur_Suplier::insert`: per item terpilih, cek `jumlah_retur <= jumlah` (jumlah beli dari detail), insert `retur_suplier`; no nota `RTS{unit}{ymd}{seq3}`.

### 3.6 Retur barang rusak klaim garansi (v2)
1. `ReturBarangRusak::index`: daftar klaim belum retur (`getKlaimBelumRetur`) + riwayat; scope unit via `GaransiClaimService::canSeeAllUnits` (role 0,1,34,40 saw semua).
2. `simpan`: untuk tiap sparepart terpilih:
   - Lock `FOR UPDATE`; wajib `status_retur='bisa'`, belum pernah di-retur, satu unit per retur, `hpp_manual > 0`, `hasil ∈ {barang_pengganti, potong_tagihan, uang_kembali, ditolak}` (bila pengganti, barang+qty wajib).
   - `nilai = hpp_manual * jumlah_tambahan_garansi`.
   - Insert `retur_klaim` + `retur_klaim_item`; update `service_sparepart.status_retur` (`diterima`/`ditolak`) + `hpp_omset_garansi` + `tanggal_hpp_klaim`.
3. `ubahStatus`: toggle `bisa`/`tidak_bisa` (tidak_bisa → loss = `hpp_penjualan_garansi`).
4. `batal`: hapus fisik item+header, reset sparepart ke `bisa`/loss 0.

### 3.7 Barang rusak (legacy)
- `BarangRusak::insert_barang_rusak`: insert `barang_rusak` (kurangi stok via view).

---

## 4. Business Rules

### (a) Fakta terverifikasi

| # | Rule | Bukti |
|---|---|---|
| B1 | Status pembelian = `Lunas` bila `sisa==0`, else `Belum Lunas`. | `Pembelian.php::insert` (L180-185) |
| B2 | No nota pembelian: `PCR{unit}{ymd}{urutan4}`; urutan dari nota terakhir hari ini per unit. | `Pembelian.php::insert` (L159-175) |
| B3 | PPN per line = `subtotalSetelahDiskon * 0.11` bila ppn diisi. | `Pembelian.php::insert` (L277) |
| B4 | Mutasi: stok hanya boleh bergerak SETELAH mutasi diterima (`status=1`). | Migrasi `2026-10-19-000400_StokBarangHanyaMutasiDiterima.php` (+ test `StokBarangHanyaMutasiDiterimaTest`) |
| B5 | `no_nota_mutasi` unik (index `uniq_mutasi_no_nota`); duplikat lama di-renumber `-R2/-R3`. | Migrasi `2026-10-20-000100_RenumberDanUnikkanNoNotaMutasi.php` (+ `MutasiNoNotaUniqueTest`) |
| B6 | Penerimaan mutasi selalu penuh: `jumlah_terima = jumlah_kirim` (form tidak minta jumlah terima). | `MutasiStok.php::simpanMutasi` (L617) |
| B7 | Terima mutasi membuat Hutang/Piutang antar unit, jatuh tempo +3 hari. | `MutasiStok.php::terima` (L330,349) |
| B8 | Batal terima hanya Admin Root (jabatan 1); tolak bila H/P sudah ada pembayaran. | `MutasiStok.php::bolehBatalTerima`, `batalTerima` (L416-429) |
| B9 | Validasi mutasi: unit pengirim & penerima beda, pengirim = unit sesi (kecuali root), qty>0, perlu stok_awal+satuan, stok pengirim cukup. Pengecekan stok **tanpa lock** (shortcut). | `MutasiStok.php::validasiInsert` (L500-553) |
| B10 | Opname seed hanya barang `stok_akhir != 0`; exclude jasa PUP/JASA/MESIN (awalan). | `StokOpnameService::createPeriode`, `kecualikanBukanProduk` (+ `StokOpnameBukanProdukTest`) |
| B11 | Satu unit hanya boleh punya satu DRAFT opname terbuka. | `StokOpnameService::createPeriode` (L144-152) |
| B12 | Finalisasi opname menolak bila masih ada barang berstok belum terisi (`jumlah_komp != 0` & `jumlah_real IS NULL`). | `StokOpnameService::finalize` (L472-489) |
| B13 | Reopen (koreksi) hanya Admin Root, wajib alasan; baris final lama ditandai `is_reverted=1` (tidak dihapus). | `StokOpnameService::reopen`, `StokOpname.php::REOPEN_ROLES = [1]` |
| B14 | Batal draft hanya Admin Root; periode DRAFT → BATAL. | `StokOpnameService::batalDraft`, `StokOpname.php::batal` |
| B15 | Role read-only opname (tak boleh mutasi): `[0,2,34,40]`; role lintas-unit: `[0,1,2,34]`; role 35/41 tidak melihat kolom "Stok Komputer". | `StokOpname.php::VIEW_ONLY_ROLES/CROSS_UNIT_ROLES/HIDE_KOMP_ROLES` |
| B16 | Retur suplier: `jumlah_retur <= jumlah` beli. | `Retur_Suplier.php::insert` (L83-86) |
| B17 | Retur barang rusak klaim: hanya `status_retur='bisa'`, belum pernah di-retur, satu unit per retur, `hpp_manual>0`, `hasil` whitelist. | `ReturBarangRusak.php::simpan` (L125-195) |
| B18 | Sparepart pengganti garansi mengurangi stok via view (`service_sparepart.jumlah_tambahan_garansi`, service sudah klaim); **barang rusak klaim TIDAK mengurangi stok** (`barang_rusak_claim` terpisah). | Migrasi `2026-10-23-000500_StokBarangKurangiSparepartGaransi.php` (+ `StokBarangKurangiSparepartGaransiTest`) |
| B19 | KPI opname target 4/bulan; kondisi periode FINAL, semua terisi, total>0. | `StokOpname.php::kpiBulan` (L396-416) |
| B20 | `stok_barang` adalah VIEW; `stok_opname` berkontribusi 0 ke `stok_akhir` (selisih opname TIDAK lagi menyesuaikan stok fisik). | Migrasi `2026-10-07-000001_RemoveStokOpnameSelisihFromStokAkhir.php` |

### (b) Inferensi (berdasarkan bukti, bukan observasi langsung runtime)

| # | Inferensi | Dasar |
|---|---|---|
| I1 | Perubahan stok antar modul terjadi "langsung" lewat view `stok_barang` — insert `detail_pembelian`/`retur_suplier`/`detail_mutasi`/`detail_penjualan` otomatis menambah/mengurangi `stok_akhir` tanpa record ledger stok terpisah. | Migrasi view + `ModelKartuStok` (table = `stok_barang`) |
| I2 | Karena `mutasi` dihitung hanya saat `status=1`, nota mutasi yang belum diterima tidak memengaruhi stok; pembatalan terima mengembalikan stok otomatis (kembali ke kondisi sebelum). | `StokBarangHanyaMutasiDiterimaTest::testBatalTerimaMengembalikanStok` |
| I3 | HPP pembelian berasal dari `hpp_barang.hpp` (diambil saat insert detail: `hitung_hpp`), tapi jalur **update** `hpp_barang` tidak ada di scope modul ini — kemungkinan dikelola modul/fitur lain. | `Pembelian.php::insert` (L282-303), `ModelHppBarang` (hanya read) |
| I4 | Alur retur penjualan (pelanggan) dipakai bersama view via `retur_pelanggan`; `Riwayat_ReturPenjualan` membaca `ModelReturCustomer` (luar scope). | `Riwayat_ReturPenjualan.php`, migrasi view |
| I5 | `stok_hp` (tabel) tampaknya snapshot/duplikat ringkasan, bukan sumber truth; `StokHP` hanya menampilkan `getStokHP()`. | `ModelStokHP` (table `stok_hp`, `findAll`) |
| I6 | Opname saat ini bersifat "pencatatan selisih" (audit) bukan "koreksi stok" — selisih tidak masuk `stok_akhir`. | B20, `ModelStokOpname` (filter `is_reverted`) |

### (c) Ketidakpastian

| # | Hal yang tidak pasti | Alasan |
|---|---|---|
| U1 | Apakah `hpp_barang` diperbarui saat pembelian? Jalur update tidak ditemukan di scope. | hanya `ModelHppBarang::getById` dipanggil |
| U2 | `bank_idbank` di header `pembelian` diisi string `kodePembayaran` (bukan id bank) — kemungkinan anomaly/legacy dan tidak dipakai untuk relasi. | `Pembelian.php::insert` (L242) |
| U3 | Total jurnal PPN vs `total_ppn` dari form: jurnal memakai `total_ppn` POST, sementara total per-kategori dihitung ulang; potensi selisih bila form & hitung beda. Belum terverifikasi konsistensinya. | `Pembelian.php::insert` (L316-375) |
| U4 | `Riwayat_ReturPenjualan::export` kolom `no_retur_pelanggan` — struktur tabel `retur_pelanggan` tidak diverifikasi. | hanya query export |
| U5 | Race condition pembuatan `no_nota_mutasi` dan `no_nota_supplier` (membaca max urutan tanpa lock) — kemungkinan bentrok pada konkurensi tinggi, unik index hanya menjaga sisi DB. | `MutasiStok.php::simpanMutasi`, `Pembelian.php::insert` |
| U6 | Scope role pada controller lain (pembelian, retur suplier, barang rusak) tidak memakai konstanta role terpusat seperti opname; otorisasi umumnya inline/bergantung filter `auth` + menu role. Belum diverifikasi penuh per-route. | pola tidak konsisten antar controller |

---

## 5. Validasi & Permission/Role

- **Filter global**: route memakai filter `auth`; route tulis sensitif (opname, retur barang rusak klaim) memakai `['auth','csrf']`. Group `stok_opname` mengaktifkan CSRF khusus.
- **Role (opname v2)** terdefinisi eksplisit:
  - `VIEW_ONLY_ROLES = [0,2,34,40]` → read-only (tak boleh `mulai/simpan/finalisasi/reopen/batal`).
  - `CROSS_UNIT_ROLES = [0,1,2,34]` → boleh pilih unit di layar.
  - `REOPEN_ROLES = [1]` → Admin Root.
  - `HIDE_KOMP_ROLES = [35,41]` → tidak lihat kolom stok komputer (anti bias hitung).
- **Mutasi**: pembuatan hanya dari unit sendiri (kecuali Admin Root jabatan 1); `terima` untuk unit tujuan (atau lintas); `batalTerima` hanya Admin Root.
- **Retur barang rusak klaim**: scope unit via `GaransiClaimService::canSeeAllUnits` (role `[0,1,34,40]`).
- **Kontrol input**:
  - Opname: `cleanDate` (regex tanggal), `mutationUnit`/`requestedUnit` tervalidasi vs daftar unit; `jumlah_real` harus numerik ≥ 0.
  - Mutasi: `filterMasuk` menyaring status (hanya 0/1) & tanggal valid (`checkdate`); whitelist sort picker.
  - Retur klaim: whitelist `hasil`; `rupiahToInt`; `FOR UPDATE` lock; cek supplier milik unit.
  - Belum terlihat validasi CSRF/otorisasi mendalam untuk `Pembelian::insert` dan `Retur_Suplier::insert` selain filter `auth`.

---

## 6. Perubahan Database / Transaksi / Efek Samping

### Sumber kebenaran stok: VIEW `stok_barang`
(versi terkini dari `2026-10-23-000500_StokBarangKurangiSparepartGaransi.php`)
```
stok_akhir = stok_awal
            + total_pembelian (detail_pembelian.jumlah)
            + total_mutasi_masuk (detail_mutasi.jumlah_terima, mutasi.status=1)
            + total_retur_pelanggan (retur_pelanggan.jumlah)
            - total_penjualan (detail_penjualan.jumlah)
            - total_mutasi_keluar (detail_mutasi.jumlah_kirim, mutasi.status=1)
            - total_retur_supplier (retur_suplier.jumlah)
            - total_kerusakan (barang_rusak.jumlah)
            - total_pemakaian_garansi (service_sparepart.jumlah_tambahan_garansi, service sudah klaim)
```
- `stok_opname` ikut UNION tapi bernilai 0 (tidak mengubah stok) — B20.
- `barang_rusak_claim` TIDAK ada di view (tidak mengurangi stok) — B18.

### Transaksi
| Alur | Transaksi | Catatan |
|---|---|---|
| Pembelian | satu transaksi (`transStart/transComplete`) covering bank, header, detail, H/P sync, jurnal | rollback bila `transStatus()===FALSE` |
| Mutasi insert | `transBegin/transCommit/transRollback` (header+detail+no nota) | |
| Terima mutasi | satu transaksi: update status + buat H/P | gagal → rollback |
| Batal terima | satu transaksi: soft-delete H/P + reset status/batal_oleh/batal_at/batal_alasan | |
| Opname mulai/simpan/finalisasi/reopen/batal | `transBegin/transCommit` di service | |
| Retur klaim simpan/ubahStatus/batal | `transBegin/...` + `FOR UPDATE` | |

### Efek samping utama
- Insert pembelian → menambah `stok_akhir` di semua unit sesuai `unit_idunit` (via view).
- Insert retur suplier → mengurangi `stok_akhir`.
- Terima mutasi → `stok_akhir` pengirim turun (keluar), penerima naik (masuk), + H/P antar unit.
- Batal terima → stok kembali (view mengikuti status), H/P soft-delete.
- Klaim garansi → mengurangi stok sparepart pengganti; retur klaim tidak menyentuh stok (hanya status + loss).
- Opname → mencatat selisih, **tidak** mengubah `stok_akhir`.

### Tabel/migrasi terkait
`stok_opname_periode`, `stok_opname_draft`, `stok_opname`, `stok_opname_audit` (`HardenStokOpnameV2`, `StokOpnameReopenAudit`, `AddStatusBatalToStokOpnamePeriode`, `RemoveStokOpnameSelisihFromStokAkhir`); `mutasi` (+ kolom `batal_oleh/batal_at/batal_alasan` dari `AddBatalTerimaToMutasi`, unique index dari `RenumberDanUnikkanNoNotaMutasi`); `service_sparepart` (+`status_retur/hpp_omset_garansi/tanggal_hpp_klaim`), `barang_rusak_claim`, `retur_klaim`, `retur_klaim_item` (`CreateBarangRusakClaim`, `ReturBarangRusakKlaim`); `BackfillHppSparepartKlaim`.

---

## 7. Relasi antar-modul

- **Pembelian → Stok**: `detail_pembelian` feed view `stok_barang` (total_pembelian). HPP dibaca dari `hpp_barang`.
- **Pembelian → Keuangan**: `HutangPiutangService::syncFromPembelian` (best-effort) + `ModelJurnal::insertJurnal` + `ModelPembayaranBank`.
- **Mutasi → Stok & Keuangan**: memindahkan stok antar unit + membuat H/P antar unit (`ModeKasBank::buatHutangPiutangDariMutasi`).
- **Opname ↔ Stok**: membawa satuan dari `stok_awal`, HPP dari `hpp_barang`, daftar barang dari view `stok_barang`; keluaran `stok_opname` untuk riwayat/KPI (bukan untuk koreksi stok).
- **Retur klaim ↔ Service/Garansi**: `service_sparepart` (status_retur, hpp_omset_garansi), `barang_rusak_claim`, `garansi` (tanggal_claim_garansi); `GaransiClaimService::canSeeAllUnits`.
- **Retur suplier ↔ Pembelian**: `detail_pembelian_iddetail_pembelian`; mengurangi stok via `retur_suplier`.
- **Barang rusak (legacy)**: kurangi stok via `barang_rusak`; **klaim** (baru) terpisah & tidak kurangi stok.

---

## 8. Bukti Test

- `StokBarangHanyaMutasiDiterimaTest` — stok bergerak hanya setelah mutasi diterima; pembatalan terima mengembalikan stok.
- `MutasiNoNotaUniqueTest` — renumber & unique index (duplikat ditolak; string kosong→null; suffix `-R*`).
- `MutasiStokPickerTest` — picker server-side; hanya stok>0 di unit pengirim; scope cabang terkunci ke unit sesi; sort/search.
- `StokOpnameBukanProdukTest` — jasa PUP/JASA/MESIN tidak di-seed; pembersihan draft lama; periode FINAL tidak disentuh.
- `StokBarangKurangiSparepartGaransiTest` — sparepart pengganti garansi kurangi stok; `barang_rusak_claim` tidak; `barang_rusak` legacy tetap kurangi.
- `ReturBarangRusakTest` — simpan (loss ditolak, nilai, pengganti tanpa mutasi stok, lintas unit ditolak), batal (reset status), ubah status (loss), scope, filter.
- `ReturBarangRusakMigrationTest` — idempoten, tidak mengubah klaim lama, menu/role 10160.

---

## 9. Gap/Konflik

1. **HPP tidak termutasi** di modul ini: `detail_pembelian.hitung_hpp` diambil dari `hpp_barang.hpp` saat insert, tapi tidak ada jalur update. Bila modul lain yang update, perlu sinkronisasi; bila tidak, HPP bisa basi. (U1)
2. **Opname tidak mengoreksi stok**: selisih opname hanya dicatat (B20), sehingga jika hasil opname dipakai untuk "benahi" stok, belum ada mekanisme penyesuaian — perlu konfirmasi niat bisnis. (U-implisit, I6)
3. **Data `stok_hp`** tampaknya snapshot terpisah tanpa alur pengisian di scope; berisiko tidak sinkron dengan `stok_barang`. (I5)
4. **`bank_idbank` diisi kode pemayaran** (bukan id bank) di `pembelian` — anomaly skema yang bisa membingungkan relasi. (U2)
5. **Race condition nomor urut** (`no_nota_supplier`, `no_nota_mutasi`) tanpa lock: unik index hanya melindungi `mutasi`, tidak `pembelian`. (U5)
6. **Otorisasi tidak konsisten**: opname/mutasi/retur klaim punya kontrol role eksplisit; pembelian & retur suplier hanya mengandalkan filter `auth` + menu role. (U6)
7. **`Retur_Suplier`** tidak dalam satu transaksi (loop insert per item), dan `getReturPembelian` memakai inner join (retur yang detailnya terhapus/tak ketemu jadi tak tampil). 
8. **Retur suplier no nota** memakai `LIKE 'DATE(tanggal)'` — pola string `DATE(...)` di `where`/`like` rawan tidak cocok (bandingkan perbaikan no nota mutasi yang sudah dibuat). Perlu verifikasi kebenaran query-nya.
