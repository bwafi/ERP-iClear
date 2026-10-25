# Retur Barang Rusak Klaim Garansi ke Supplier

Tanggal: 2026-10-24
Status: draft untuk persetujuan

## Tujuan

Klaim garansi mengganti sparepart rusak dengan sparepart pengganti dari stok.
HPP sparepart pengganti harus tetap terlihat di Sparepart Keluar. Sebagian
sparepart rusak bisa diretur ke supplier; sebagian tidak. HPP baru boleh
mengurangi omset/laba service hanya kalau sparepart tidak bisa diretur atau
supplier menolak retur.

Retur ke supplier bisa berisi satu atau banyak barang. Supplier dipilih manual
saat retur. Hasil retur bisa berupa barang pengganti baru, potong tagihan, uang
kembali, atau ditolak. Nilai retur memakai HPP manual yang diisi saat retur,
bukan HPP master, karena HPP master bisa sudah lama.

## Goals

- HPP sparepart pengganti selalu disimpan dan ditampilkan sebesar HPP asli.
- Status bisa/tidak bisa retur dipisah dari HPP.
- Omset dan laba service hanya dikurangi saat status `tidak_bisa` atau `ditolak`.
- Menyediakan menu retur barang rusak klaim yang mendukung satu atau banyak
  barang, supplier manual, HPP manual, dan empat hasil retur.
- Menyimpan riwayat retur dan nilai HPP manual yang dipakai saat retur.
- Menjaga data lama tetap seperti sekarang: klaim yang sudah ada tidak otomatis
  mengurangi omset.

## Non-goals

- Tidak mengubah menu `retur_suplier` yang sudah ada. Menu itu tetap untuk retur
  pembelian dan tetap terikat ke `detail_pembelian`.
- Tidak menambah stok otomatis untuk barang pengganti. Input stok tetap manual.
- Tidak otomatis memotong hutang supplier untuk hasil `potong_tagihan`.
- Tidak otomatis membuat kas masuk untuk hasil `uang_kembali`.
- Tidak menghitung selisih kalau nilai retur diterima lebih kecil dari HPP klaim.
  Hasil yang diterima dianggap menutup penuh; selisih tidak mengurangi omset.
- Tidak mendukung retur sebagian qty dari satu baris sparepart pengganti. Satu
  baris diretur penuh. Satu transaksi boleh berisi satu item atau banyak item;
  yang tidak didukung adalah memecah qty satu baris menjadi beberapa retur.
  Kalau nanti dibutuhkan, tambah kolom qty retur per baris.

## Istilah

- **Baris klaim**: baris `service_sparepart` dengan `jumlah_tambahan_garansi > 0`.
  Ini baris sparepart pengganti yang membawa HPP klaim.
- **Barang rusak klaim**: baris `barang_rusak_claim`, yaitu sparepart lama yang
  rusak dan diserahkan pelanggan. Tidak mengurangi stok.
- **HPP asli**: `service_sparepart.hpp_penjualan_garansi`, HPP sparepart pengganti
  saat klaim.
- **HPP omset**: `service_sparepart.hpp_omset_garansi`, HPP per unit yang dipakai
  untuk mengurangi omset saat `tidak_bisa` atau `ditolak`.

## Aturan Bisnis

### Saat klaim

Teknisi memilih status retur per baris sparepart pengganti:

- `bisa`: sparepart rusak diperkirakan bisa diretur. HPP asli tetap disimpan,
  tetapi omset belum dikurangi. Baris masuk daftar retur.
- `tidak_bisa`: sparepart rusak tidak bisa diretur. HPP asli disimpan dan
  `hpp_omset_garansi` diisi HPP asli. Omset service dikurangi di bulan klaim
  (`tanggal_claim_garansi`).

Pilihan HPP 0/HPP asli yang sekarang ada di form klaim diganti menjadi pilihan
status retur. HPP tidak lagi bisa dipilih 0; HPP selalu HPP asli.

### Periode pengurangan omset

Pengurangan omset masuk ke bulan `tanggal_hpp_klaim`, bukan bulan service:

- Klaim yang sudah pasti tidak bisa retur: bulan klaim.
- Klaim yang bisa retur tetapi ditolak supplier: bulan retur.
- Klaim yang diterima supplier: tidak ada pengurangan.
- Retur dibatalkan: pengurangan dibatalkan.

Jadi service September yang baru ditolak supplier Oktober akan mengurangi omset
Oktober. Laporan September tidak berubah.

### Edit kalau salah pilih status

Di menu Retur Barang Rusak, baris yang belum punya item retur punya tombol
**Ubah Status**. Modal berisi pilihan `bisa` / `tidak_bisa`.

- Ubah ke `tidak_bisa`: `hpp_omset_garansi = hpp_penjualan_garansi`,
  `tanggal_hpp_klaim = DATE(service.tanggal_claim_garansi)`.
- Ubah ke `bisa`: `hpp_omset_garansi = 0`, `tanggal_hpp_klaim = NULL`.
- Kalau baris sudah punya item retur, tombol disabled. Batalkan retur dulu.

### Saat retur

Menu retur menampilkan baris klaim dengan status `bisa`.

Saat retur disimpan:

- `barang_pengganti`: status menjadi `diterima`; `hpp_omset_garansi = 0`; omset
  tidak dikurangi. Barang pengganti dicatat (barang dan qty), stok tetap input
  manual.
- `potong_tagihan`: status menjadi `diterima`; `hpp_omset_garansi = 0`; omset
  tidak dikurangi. Nilai retur = HPP manual x qty.
- `uang_kembali`: status menjadi `diterima`; `hpp_omset_garansi = 0`; omset tidak
  dikurangi. Nilai retur = HPP manual x qty.
- `ditolak`: status menjadi `ditolak`; `hpp_omset_garansi = HPP manual`;
  `tanggal_hpp_klaim = retur_klaim.tanggal`; omset dikurangi HPP manual x qty
  di bulan retur.

Kalau retur dibatalkan, status kembali `bisa`, `hpp_omset_garansi = 0`,
`tanggal_hpp_klaim = NULL`, dan omset kembali seperti sebelum retur.

### HPP manual

HPP manual diisi per unit saat retur. Field kosong dan wajib diisi. Tidak
di-prefill dari master. Nilai retur per baris = HPP manual x qty.

### Supplier

Supplier dipilih manual dari master `suplier` yang aktif (`deleted = '0'`).
Satu header retur hanya untuk satu supplier. Kalau barang yang mau diretur
berbeda supplier, buat retur terpisah.

### Unit

Satu header retur hanya untuk satu unit. User cabang hanya melihat dan membuat
retur untuk unitnya. User yang lolos `GaransiClaimService::canSeeAllUnits()`
boleh melihat semua unit dan memilih unit lewat filter. Item lintas unit dalam
satu retur ditolak; buat retur terpisah per unit.

## Perubahan Skema

### `service_sparepart`

Tambah kolom:

```sql
status_retur VARCHAR(20) NULL DEFAULT NULL
hpp_omset_garansi INT NOT NULL DEFAULT 0
tanggal_hpp_klaim DATE NULL DEFAULT NULL
```

Nilai `status_retur`: `bisa`, `tidak_bisa`, `diterima`, `ditolak`.

`tanggal_hpp_klaim` adalah tanggal pengurangan omset masuk. Ini yang membuat
pengurangan ikut bulan retur, bukan bulan service.

- `tidak_bisa` saat klaim: `tanggal_hpp_klaim = DATE(service.tanggal_claim_garansi)`.
- `ditolak` saat retur: `tanggal_hpp_klaim = retur_klaim.tanggal`.
- `bisa`, `diterima`, atau retur dibatalkan: `tanggal_hpp_klaim = NULL`.

Backfill data lama:

```sql
UPDATE service_sparepart
SET status_retur = 'bisa',
    hpp_omset_garansi = 0,
    tanggal_hpp_klaim = NULL
WHERE jumlah_tambahan_garansi > 0
  AND (status_retur IS NULL OR status_retur = '');
```

Backfill ini menjaga klaim lama, termasuk `SRV420261001011`, tetap tidak
mengurangi omset. HPP asli klaim lama sudah diisi oleh migration
`2026-10-24-000100_BackfillHppSparepartKlaim`.

### `retur_klaim`

Header retur barang rusak klaim.

```sql
idretur_klaim INT AUTO_INCREMENT PRIMARY KEY
no_retur VARCHAR(45) NOT NULL
tanggal DATE NOT NULL
suplier_id_suplier INT NOT NULL
unit_idunit INT NOT NULL
input_by INT NULL
catatan TEXT NULL
created_at DATETIME NULL
UNIQUE KEY uq_retur_klaim_no (no_retur)
KEY idx_retur_klaim_tanggal (tanggal)
KEY idx_retur_klaim_supplier (suplier_id_suplier)
KEY idx_retur_klaim_unit (unit_idunit)
```

Format nomor: `RTB` + `unit_idunit` + `Ymd` + 3 digit urut, mengikuti pola
`RTS` di `Retur_Suplier`.

### `retur_klaim_item`

```sql
idretur_klaim_item INT AUTO_INCREMENT PRIMARY KEY
retur_klaim_idretur_klaim INT NOT NULL
service_sparepart_idservice_sparepart INT NOT NULL
barang_rusak_claim_idbarang_rusak_claim INT NULL
barang_idbarang INT NOT NULL
jumlah DECIMAL(15,2) NOT NULL
hpp_manual INT NOT NULL
hasil VARCHAR(30) NOT NULL
barang_pengganti_idbarang INT NULL
jumlah_pengganti DECIMAL(15,2) NULL
nilai INT NOT NULL DEFAULT 0
keterangan TEXT NULL
created_at DATETIME NULL
KEY idx_retur_klaim_item_header (retur_klaim_idretur_klaim)
UNIQUE KEY uq_retur_klaim_item_sparepart (service_sparepart_idservice_sparepart)
```

Unique key mencegah satu baris klaim masuk dua retur. Saat menyimpan retur,
baca baris klaim dengan `SELECT ... FOR UPDATE` di dalam transaksi.

Nilai `hasil`: `barang_pengganti`, `potong_tagihan`, `uang_kembali`, `ditolak`.

`barang_idbarang` adalah barang sparepart pengganti klaim. `barang_rusak_claim_id...`
diisi kalau item terkait bisa dipetakan ke satu barang rusak; kalau tidak, NULL.
`nilai = hpp_manual * jumlah` disimpan sebagai snapshot.

### Menu

Tambah baris `menu`:

- `nama_menu`: `Retur Barang Rusak`
- `url`: `retur_barang_rusak`
- `roles`: `retur_barang_rusak`
- `urutan`: setelah `Retur Supplier` (23), mis. 25
- `sub`: 0
- `parent`: 0
- `utama`: 1
- `categories`: 1
- `icon`: ikon retur/kirim

Grant menu ke jabatan yang sama dengan menu `Retur Supplier` (roles
`retur_suplier`). Kalau daftar jabatan tidak memakai roles itu, samakan dengan
jabatan yang sudah bisa membuka `garansi_service`.

## Perubahan Form Klaim

File: `app/Views/transaksi/table_garansi/claim_detail.php`

- Hapus select HPP 0/HPP asli.
- Tambah select `produk[i][status_retur]` dengan pilihan:
  - `bisa` — Bisa retur (default)
  - `tidak_bisa` — Tidak bisa retur
- HPP ditampilkan sebagai teks HPP asli, bukan input.
- Teks bantuan: HPP asli tetap tampil; omset hanya berkurang kalau tidak bisa
  retur atau ditolak supplier.

File: `app/Controllers/StatusGaransi.php`

- `simpanSparepartKlaim()`:
  - Selalu set `hpp_penjualan_garansi = HPP master`.
  - Baca `status_retur`; nilai selain `tidak_bisa` dianggap `bisa`.
  - Set `hpp_omset_garansi = status_retur === 'tidak_bisa' ? HPP master : 0`.
  - Set `tanggal_hpp_klaim = status_retur === 'tidak_bisa' ? $wibTime->format('Y-m-d') : NULL`.
    Pakai `$wibTime` yang sama dengan tanggal klaim; jangan baca kolom
    `service.tanggal_claim_garansi` di sini karena baru di-update setelah
    sparepart disimpan. Tambahkan parameter tanggal klaim ke
    `simpanSparepartKlaim()`.
- Hapus pembacaan `hpp_mode`.

File: `app/Models/ModelServiceSparepart.php`

- Tambah `status_retur`, `hpp_omset_garansi`, dan `tanggal_hpp_klaim` ke
  `$allowedFields`.

## Perhitungan Omset dan Laba

Karena pengurangan ikut bulan `tanggal_hpp_klaim`, jangan mengurangi HPP klaim
dari baris service yang tanggalnya berbeda. Buat satu sumber data loss yang
dipakai semua laporan.

### `ModelHppKlaimLoss`

Method:

- `getLoss($start, $end, $unitId = null, $teknisiId = null)`: ambil baris
  `service_sparepart` join `service`, `barang`, `unit` dengan
  `status_retur IN ('tidak_bisa', 'ditolak')` dan
  `tanggal_hpp_klaim BETWEEN $start AND $end`.
- `getTotalLoss(...)`: jumlahkan `hpp_omset_garansi * jumlah_tambahan_garansi`.

Field hasil: tanggal klaim loss, no service, teknisi, unit, nama barang, HPP
per unit, qty, total.

Semua laporan memakai `tanggal_hpp_klaim` sebagai periode loss.

### Omset Service dan Laba Service

`ModelService::getServiceWithLaba()` tetap per service dan tetap memfilter
`service.tanggal_selesai`. Jangan kurangi HPP klaim dari baris per service,
karena bulan service dan bulan loss bisa berbeda.

- `Fee_Service` (Omset Service) dan `RiwayatLabaService` (Laba Service) memanggil
  `ModelHppKlaimLoss->getLoss()` untuk periode laporan.
- Tampilkan section/tabel terpisah **HPP Klaim** di bawah tabel utama.
- Total kartu Omset Service dan Laba Service dikurangi total HPP Klaim.
- Baris per service tetap seperti sekarang; aritmetika Total Service - Total
  HPP - Diskon tetap valid untuk periode service.
- Kalau service dan retur terjadi di bulan yang sama, loss tetap muncul di
  section HPP Klaim, bukan di baris service.
- Sparepart Keluar tetap menampilkan HPP asli dan tidak berubah.

### Export Laba Service

`ModelService::filterexportlaba()` tetap mengeluarkan baris service berdasarkan
`service.created_at`. Tambah sheet/section **HPP Klaim** dari
`ModelHppKlaimLoss->getLoss($awal, $akhir)`, dengan kolom tanggal, no service,
teknisi, barang, HPP, qty, total. Total laba di export dikurangi total HPP Klaim.

### Laba Rugi dan Dashboard

Tambahkan biaya **HPP Klaim** dari `ModelHppKlaimLoss->getTotalLoss()` ke:

- `LabaRugi::getLabaRugiFromTransaksi()`
- `DashboardKeuangan::getLabaRugiFromTransaksi()`
- `DashboardLabaRugi::getLabaRugiFromTransaksi()`

Biaya memakai periode laporan dan `tanggal_hpp_klaim`. Tambah baris
**HPP Klaim** di view masing-masing. Total laba rugi dikurangi biaya ini.

### Omset Teknisi / KPI

`OmsetTeknisiCalculator` mengurangi total HPP Klaim per teknisi pada bulan
`tanggal_hpp_klaim`. Loss diatribusikan ke `service.service_by`. Ini ikut
mempengaruhi KPI/insentif teknisi.

### Laporan yang Tidak Diubah

- `DashboardService`, `Home`, dan `getTotalPendapatanService` menampilkan
  pendapatan service bruto, bukan omset net. Tidak dikurangi HPP klaim.
- `TutupKasir` menghitung omset penjualan, bukan service.
- `Marketing` menghitung omset lead, bukan omset service.
- Laporan laba rugi berbasis jurnal (`ModelJurnal`) tidak diubah karena belum ada
  jurnal HPP klaim. Kalau nanti dibutuhkan, buat jurnal terpisah.

## Menu Retur Barang Rusak

### Routes

```php
$routes->get('retur_barang_rusak', 'ReturBarangRusak::index', ['filter' => 'auth']);
$routes->post('retur_barang_rusak/simpan', 'ReturBarangRusak::simpan', ['filter' => 'auth']);
$routes->post('retur_barang_rusak/ubah-status/(:num)', 'ReturBarangRusak::ubahStatus/$1', ['filter' => 'auth']);
$routes->post('retur_barang_rusak/batal/(:num)', 'ReturBarangRusak::batal/$1', ['filter' => 'auth']);
```

### Controller `ReturBarangRusak`

- `index()`:
  - Ambil daftar baris klaim status `bisa` atau `tidak_bisa` yang belum punya
    item retur.
  - Join `service`, `barang`, `unit`, dan `barang_rusak_claim` (agregat nama
    barang rusak per service).
  - Filter unit/tanggal.
  - Ambil supplier aktif.
  - Ambil riwayat retur.
  - Scope unit memakai `GaransiClaimService::canSeeAllUnits()` yang sudah dipakai
    alur garansi, bukan cek `ID_JABATAN = 1` sendiri.
  - Tanggal retur read-only untuk user yang tidak bisa melihat semua unit.
- `simpan()`:
  - Validasi CSRF, supplier, tanggal, minimal satu item.
  - Semua item harus satu unit dan status `bisa`.
  - HPP manual wajib >= 0.
  - `barang_pengganti` wajib punya barang pengganti dan qty > 0.
  - Transaksi:
    - Buat header dan item.
    - `ditolak`: update `status_retur='ditolak'`, `hpp_omset_garansi=hpp_manual`,
      `tanggal_hpp_klaim=retur_klaim.tanggal`.
    - Selain `ditolak`: update `status_retur='diterima'`, `hpp_omset_garansi=0`,
      `tanggal_hpp_klaim=NULL`.
  - Redirect dengan flash sukses/gagal.
- `ubahStatus($id)`:
  - Hanya untuk baris tanpa `retur_klaim_item`.
  - `tidak_bisa`: set `hpp_omset_garansi=hpp_penjualan_garansi`,
    `tanggal_hpp_klaim=DATE(service.tanggal_claim_garansi)`.
  - `bisa`: set `hpp_omset_garansi=0`, `tanggal_hpp_klaim=NULL`.
- `batal($id)`:
  - Dalam transaksi, kembalikan setiap baris ke `status_retur='bisa'`,
    `hpp_omset_garansi=0`, `tanggal_hpp_klaim=NULL`.
  - Hapus item dan header.
  - Ini mengembalikan omset ke posisi sebelum retur.

### Model `ModelReturBarangRusak`

- `getKlaimBelumRetur($unitId, $tanggalAwal, $tanggalAkhir)`:
  - `service_sparepart.jumlah_tambahan_garansi > 0`
  - `status_retur IN ('bisa', 'tidak_bisa')`
  - Tidak ada baris `retur_klaim_item` untuk `idservice_sparepart` itu.
  - Urut tanggal klaim terbaru.
  - Baris `tidak_bisa` tetap tampil supaya statusnya bisa diedit kembali.
- `getRiwayat($unitId, $tanggalAwal, $tanggalAkhir)`:
  - Header + item + supplier + unit + nama barang.
- `getById($id)` dan `getItems($id)`.

### View `app/Views/retur/retur_barang_rusak.php`

- Judul: Retur Barang Rusak.
- Filter unit (HO), tanggal awal/akhir, tombol reset.
- Tabel barang belum retur:
  - No service, tanggal klaim, barang rusak, sparepart pengganti, qty, HPP asli,
    unit, status.
  - Tombol **Ubah Status** untuk baris yang belum punya item retur.
  - Tombol `Buat Retur` hanya untuk baris `bisa`; baris `tidak_bisa` hanya bisa
    diedit statusnya.
- Tombol `Buat Retur` membuka modal:
  - Supplier (select manual).
  - Tanggal retur (default hari ini; user cabang read-only seperti pola retur
    supplier).
  - Tabel item dengan checkbox.
  - Per item: HPP manual (input angka), hasil (select), barang pengganti + qty
    (muncul hanya kalau hasil `barang_pengganti`), keterangan.
  - Tombol simpan.
- Tabel riwayat retur:
  - No retur, tanggal, supplier, unit, jumlah item, total nilai, aksi detail.
  - Modal detail menampilkan item, HPP manual, hasil, nilai, dan status.
- Tombol `Batalkan Retur` di detail dengan konfirmasi.

### Validasi dan Pengamanan

- Hanya user login.
- Unit scope: user cabang tidak bisa melihat/mengubah unit lain.
- Supplier harus aktif.
- Baris klaim harus milik unit yang sama dengan header.
- Baris yang sudah punya item retur tidak bisa diretur lagi.
- Baris yang statusnya bukan `bisa` tidak bisa diretur.
- Kalau baris klaim dihapus/diubah dari form klaim setelah ada retur, tolak
  perubahan. Form klaim hanya boleh mengubah status kalau belum ada item retur.
- Semua update status dan insert item dalam satu transaksi.

### Pengamanan Jalur Edit Sparepart Lain

Selain `claim_detail.php`, baris klaim juga bisa dibuat/diubah/dihapus lewat:

- `StatusGaransi::insert_sparepart_garansi()` (workbench sparepart garansi)
- `Service::insert_sparepart()`
- `Riwayat_Service::insert_sparepart()`

Aturan:

- Baris klaim baru dari jalur mana pun harus diberi `status_retur='bisa'` dan
  `hpp_omset_garansi=0`, atau jalur itu harus menolak pembuatan baris klaim.
- Baris klaim yang sudah punya `retur_klaim_item` tidak boleh dihapus, diubah
  menjadi qty 0, atau diubah `jumlah_tambahan_garansi`-nya.
- Kalau jalur itu tidak bisa diedit dengan aman, jadikan baris klaim read-only
  di jalur tersebut.
- Tambah tes regresi untuk workbench sparepart garansi dan edit sparepart
  service supaya retur tidak bisa menggantung.

## Dampak ke Data dan Halaman Lain

- `SRV420261001011`: status hasil backfill `bisa`, `hpp_omset_garansi = 0`,
  `tanggal_hpp_klaim = NULL`. HPP di Sparepart Keluar tetap Rp 247.680; omset
  service tetap Rp 994.000.
- Halaman Omset Service dan Laba Service menampilkan section HPP Klaim hanya
  untuk baris `tidak_bisa`/`ditolak` pada periode loss.
- `barang_rusak.php` tidak wajib berubah. Kalau nanti perlu, status retur bisa
  ditambahkan di tabel Barang Rusak Klaim Garansi.
- `retur_suplier` dan stok tidak berubah.

## Tes

### Klaim

- Simpan klaim dengan `status_retur='bisa'`: HPP asli tersimpan,
  `hpp_omset_garansi=0`, `tanggal_hpp_klaim=NULL`.
- Simpan klaim dengan `status_retur='tidak_bisa'`: HPP asli tersimpan,
  `hpp_omset_garansi=HPP asli`, `tanggal_hpp_klaim=tanggal klaim`.
- Sparepart Keluar tetap menampilkan HPP asli untuk kedua status.
- Klaim lama setelah migration tetap `bisa` dan omset tidak berubah.

### Omset/Laba

- `bisa`: tidak ada baris HPP Klaim.
- `tidak_bisa`: baris HPP Klaim muncul di bulan klaim sebesar HPP asli x qty.
- `ditolak`: baris HPP Klaim muncul di bulan retur sebesar HPP manual x qty.
- `diterima` (semua hasil selain ditolak): tidak ada baris HPP Klaim.
- Batal retur: baris HPP Klaim hilang dan total kembali.
- Service September, retur ditolak Oktober: September tidak berubah, Oktober
  berkurang.
- Ubah status `bisa` -> `tidak_bisa`: loss muncul di bulan klaim.
- Ubah status `tidak_bisa` -> `bisa`: loss hilang.
- Omset Service, Laba Service, export Laba Service, LabaRugi,
  DashboardKeuangan, DashboardLabaRugi, dan OmsetTeknisiCalculator memakai
  total HPP Klaim yang sama untuk periode yang sama.
- Dashboard pendapatan bruto (DashboardService, Home) tidak berubah.

### Retur

- Simpan retur satu item.
- Simpan retur banyak item, satu supplier, satu unit.
- Supplier manual tersimpan.
- HPP manual tersimpan dan dipakai untuk nilai retur.
- Hasil `barang_pengganti` menyimpan barang pengganti dan qty, tanpa mutasi stok.
- Hasil `potong_tagihan` dan `uang_kembali` menyimpan nilai.
- Hasil `ditolak` mengubah status, HPP omset, dan `tanggal_hpp_klaim`.
- Hasil diterima mengosongkan `tanggal_hpp_klaim`.
- Batal retur mengosongkan `tanggal_hpp_klaim`.
- Item lintas unit ditolak.
- Item yang sudah diretur tidak muncul lagi.
- Tombol Ubah Status hanya muncul/aktif untuk baris tanpa item retur.
- User cabang tidak bisa mengakses unit lain.

## Keputusan yang Diambil Builder

- Nama tabel `retur_klaim` dan `retur_klaim_item`.
- Format nomor `RTB`.
- HPP manual kosong dan wajib diisi, tanpa prefill.
- Satu retur = satu supplier = satu unit.
- Retur penuh per baris, bukan partial qty.
- Potong tagihan/uang kembali hanya dicatat, tidak otomatis ke hutang/kas.
- Barang pengganti dicatat, stok tetap manual.
- Batal retur menghapus header/item dan mengembalikan status.
- Scope unit dan tanggal read-only memakai `GaransiClaimService::canSeeAllUnits()`.
- Menu diberikan ke jabatan yang sama dengan `Retur Supplier`.
- HPP Klaim ditampilkan sebagai section terpisah di Omset Service dan Laba
  Service, bukan dikurangkan ke baris service.
- Loss period memakai `tanggal_hpp_klaim`; `tidak_bisa` memakai tanggal klaim,
  `ditolak` memakai tanggal retur.
- Model baru `ModelHppKlaimLoss` menjadi sumber angka untuk semua laporan.
- Export Laba Service memakai sheet/section HPP Klaim terpisah.
- LabaRugi, DashboardKeuangan, DashboardLabaRugi, dan OmsetTeknisiCalculator
  ikut memakai `ModelHppKlaimLoss`.
- Dashboard pendapatan bruto tidak diubah.
- Semua jalur edit sparepart yang bisa membuat/menghapus baris klaim ikut
  dijaga; baris yang sudah punya retur tidak boleh dihapus atau diubah qty-nya.
- Unique key pada `retur_klaim_item.service_sparepart_idservice_sparepart`
  plus `SELECT ... FOR UPDATE` saat menyimpan retur.
