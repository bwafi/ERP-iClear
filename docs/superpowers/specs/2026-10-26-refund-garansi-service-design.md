# Refund Garansi Service

Tanggal: 2026-10-26
Status: disetujui 2026-10-26 (diimplementasikan)

## Tujuan

Pelanggan yang sudah membayar klaim garansi kadang meminta uangnya kembali.
Saat refund, sparepart pengganti yang terpasang harus diputuskan: kembali ke
stok kalau masih baik, atau dicatat rusak kalau tidak layak pakai. Fitur ini
membuat refund dari halaman `/garansi_service` tanpa mengubah data klaim lama.

## Goals

- Refund tunai atau bank untuk pembayaran klaim garansi (`bayar_garansi`).
- Nominal refund otomatis sebesar total yang sudah dibayar; tidak bisa melebihi.
- Pilih kondisi tiap sparepart pengganti: `baik` (kembali ke stok akhir) atau
  `rusak` (masuk riwayat barang rusak klaim).
- Sparepart rusak mengurangi omset pada tanggal refund lewat mekanisme
  `hpp_omset_garansi` + `tanggal_hpp_klaim` yang sudah ada, termasuk
  `status_retur = tidak_bisa` supaya `ModelHppKlaimLoss` membacanya.
- Refund tunai/bank tercatat sebagai kas keluar + jurnal seimbang.
- Refund mengurangi KPI Omset Toko, Omset Cabang, dan Omset Teknisi sebesar
  nominal refund pada bulan refund.
- Refund hanya sekali per service dan aman dari request bersamaan.
- Setelah refund, data klaim dan retur sparepart service itu dikunci.

## Non-goals

- Tidak refund sebagian, tidak refund berulang, tidak refund qty sebagian per
  baris sparepart. Satu baris mengikuti satu kondisi.
- Tidak me-refund pembayaran service normal di luar pembayaran klaim garansi.
- Tidak mengubah/membalik omset yang sudah tercatat saat klaim.
- Tidak menyambungkan ke retur supplier atau `retur_klaim`.
- Tidak membuat jurnal untuk pembayaran klaim asal yang belum pernah dijurnal.
- Tidak mengubah status klaim (`tanggal_claim_garansi` tetap).
- Tidak mengubah laporan Laba Rugi/Dashboard pada iterasi ini; hanya KPI dan
  omset harian ERP yang ikut dikurangi.

## Aturan Bisnis

### Kapan tombol refund muncul

Tombol **Refund** tampil di daftar garansi bila:

- service sudah diklaim (`tanggal_claim_garansi` valid),
- `bayar_garansi > 0`,
- belum pernah direfund (`refund_garansi_at` kosong),
- user berhak atas unit service (aturan `GaransiClaimService::canSeeAllUnits`).

Service yang belum diklaim atau tanpa pembayaran tidak bisa direfund.

### Nominal dan metode

- Nominal selalu `service.bayar_garansi`, dihitung ulang di server. Nominal dari
  form tidak dipercaya.
- Metode: `tunai` atau `bank`.
- Metode `bank` wajib memilih rekening yang valid untuk unit service
  (`BankRekeningValidator::validateUntukUnit`) dan wajib mengisi referensi.
- Alasan refund wajib diisi, maksimal 255 karakter.

### Sparepart

Baris yang diputuskan adalah `service_sparepart` dengan
`jumlah_tambahan_garansi > 0` milik service tersebut.

- `baik`: `jumlah_refund_garansi = jumlah_tambahan_garansi`. View stok berhenti
  mengurangi qty itu, sehingga stok akhir bertambah kembali.
- `rusak`: `jumlah_refund_garansi = 0`; stok tetap seperti setelah klaim. Satu
  baris `barang_rusak_claim` dibuat dengan keterangan berawalan `Refund:`.
  `status_retur` diubah ke `tidak_bisa` bila belum, `hpp_omset_garansi` diisi
  `hpp_penjualan_garansi` bila masih 0, dan `tanggal_hpp_klaim = tanggal refund`.
  Kalau kerugian sudah tercatat saat klaim, nilai lama dibiarkan (tidak dobel,
  tidak menulis ulang bulan lama).

Semua baris sparepart pengganti wajib punya pilihan kondisi. Baris yang tidak
dikirim ditolak, supaya stok tidak berubah karena form setengah terisi.

### Baris yang tidak boleh direfund

Refund ditolak bila baris sparepart sudah terikat retur supplier
(`retur_klaim_item` ada atau `status_retur = diterima`). Barang sudah keluar ke
supplier; mengembalikan stok dari pelanggan akan menghitung stok dua kali.

- `baik` hanya boleh bila belum ada kerugian tercatat (`hpp_omset_garansi = 0`
  dan `status_retur` bukan `tidak_bisa`/`ditolak`). Kalau kerugian sudah
  tercatat, user harus mengubah status lewat menu Retur Barang Rusak dulu.
- `rusak` boleh bila `status_retur` masih `bisa`/`tidak_bisa`; kalau sudah
  `tidak_bisa`/`ditolak`, kerugian tidak ditambah lagi.

### Dampak KPI

Refund adalah pengembalian uang, jadi KPI omset harus ikut turun pada bulan
refund, bukan bulan service selesai.

- Sumber angka: `service.refund_garansi_tanggal` dan
  `service.refund_garansi_jumlah`, difilter `unit_idunit` dan/atau `service_by`.
- Model baru `ModelRefundGaransiLoss::getTotal($start, $end, $unitId, $teknisiId)`
  menjumlahkan refund pada rentang tanggal refund.
- Pengurang diterapkan di:
  - `OmsetTokoCalculator` (KPI Omset Toko),
  - `OmsetCabangCalculator` (KPI Omset Cabang/SPV/Manager),
  - `OmsetTeknisiCalculator` (KPI Omset Teknisi, difilter `service_by`),
  - `OmsetDailyCalculator` (Finance KPI dan omset harian ERP),
  - query `omset_hari_ini` di `TutupKasir`.
- Sparepart `baik`: pengurang hanya nominal refund.
- Sparepart `rusak`: nominal refund **ditambah** HPP loss yang sudah ada
  (`hpp_omset_garansi`), karena barang tidak kembali ke stok. HPP klaim tetap
  hanya mengurangi Omset Teknisi (perilaku `ModelHppKlaimLoss` yang sudah ada);
  nominal refund yang mengurangi Toko/Cabang/Teknisi.
- `/omset_bulanan` (arus kas harian) tidak diubah: `kas_keluar` refund sudah
  mengurangi net kas pada tanggal refund. Jangan menambah pengurang kedua di
  jalur kas.
- Laba Rugi tidak diubah pada iterasi ini. Dashboard Finance memakai
  `OmsetDailyCalculator` yang sama, jadi kartu omzet hariannya ikut berkurang
  pada tanggal refund; jangan menambah pengurang refund kedua di Dashboard.

### Sekali refund

Refund dikunci dengan `service.refund_garansi_at` dan
`service.refund_garansi_idkas_keluar`. Update dilakukan bersyarat di dalam
transaksi; kalau sudah terisi oleh request lain, seluruh transaksi dibatalkan.

## Perubahan Skema

### `service`

```sql
refund_garansi_idkas_keluar INT NULL
refund_garansi_tanggal DATE NULL
refund_garansi_metode VARCHAR(10) NULL
refund_garansi_idbank VARCHAR(20) NULL
refund_garansi_referensi VARCHAR(100) NULL
refund_garansi_alasan VARCHAR(255) NULL
refund_garansi_jumlah INT NOT NULL DEFAULT 0
refund_garansi_by INT NULL
refund_garansi_at DATETIME NULL
```

`kas_keluar` tidak punya kolom referensi, jadi metode/rekening/referensi/alasan
disimpan di `service`. Baris `kas_keluar` tetap menjadi sumber arus kas dan
jurnal; `idkas_keluar` menjadi tautan audit.

### `service_sparepart`

```sql
jumlah_refund_garansi INT NOT NULL DEFAULT 0
```

### View `stok_barang`

Union `service_sparepart` pada view diubah dari:

```sql
SUM(jumlah_tambahan_garansi)
```

menjadi:

```sql
SUM(jumlah_tambahan_garansi - jumlah_refund_garansi)
```

Tidak ada perubahan pada `barang_rusak_claim`: tabel itu memang tidak
mengurangi stok, sehingga sparepart rusak tidak terhitung dua kali.

### Template jurnal

- `refund_garansi_tunai`: debet `2010302000` Pendapatan Diterima di Muka (Jasa
  Service), kredit `1010101000` Kas Besar.
- `refund_garansi_bank`: debet `2010302000`, kredit `1010102000` Kas di Bank.

Kategori kas keluar `REFUND` dipakai ulang.

## Alur

1. User klik **Refund** di `/garansi_service`.
2. Modal memuat ringkasan service, rekening unit, dan daftar sparepart pengganti
   lewat `GET garansi_service/refund/detail/{idservice}`.
3. User pilih metode, rekening (jika bank), referensi, alasan, dan kondisi tiap
   sparepart.
4. `POST garansi_service/refund` (filter `auth` + `csrf`) memproses dalam satu
   transaksi:
   - lock service `FOR UPDATE`,
   - validasi klaim, unit, nominal, metode, rekening, dan kondisi sparepart,
   - insert `kas_keluar` + jurnal, cek jurnal seimbang,
   - update `service_sparepart` dan `barang_rusak_claim`,
   - update `service` dengan syarat belum pernah refund,
   - commit.
5. Redirect ke `/garansi_service` dengan flash sukses/gagal.

## Penjagaan

Setelah `refund_garansi_at` terisi, service dianggap terkunci:

- `StatusGaransi::insert_sparepart_garansi` dan `insert_pembayaran_garansi`
  menolak service yang sudah direfund.
- `StatusGaransi::insert_sparepart_garansi` juga menolak mengubah/menghapus
  baris yang sudah punya `jumlah_refund_garansi > 0` walau guard service
  terlewat.
- `ReturBarangRusak::simpan`, `ubahStatus`, dan `batal` menolak baris milik
  service yang sudah direfund, supaya kerugian refund tidak dibatalkan diam-diam.
- `ModelService::hardDeleteService` menolak service yang punya refund garansi,
  sama seperti refund DP.
- Semua validasi unit dilakukan di server; `unit_id` dari request tidak dipakai.

## Test

- `tests/unit/GaransiRefundServiceTest.php`:
  - refund tunai: kas keluar, jurnal 2 baris seimbang, field refund service,
    sparepart baik menambah `jumlah_refund_garansi`, sparepart rusak membuat
    `barang_rusak_claim` + `status_retur = tidak_bisa` +
    `hpp_omset_garansi`/`tanggal_hpp_klaim`.
  - refund bank: rekening wajib valid, referensi wajib.
  - refund kedua ditolak.
  - service belum diklaim / tanpa pembayaran ditolak.
  - unit di luar wewenang ditolak.
  - baris yang sudah diretur supplier, atau sudah punya kerugian dengan pilihan
    `baik`, ditolak.
  - setelah refund, endpoint klaim dan retur barang rusak menolak perubahan.
- `tests/RefundGaransiMigrationTest.php`: kolom baru ada, template jurnal ada,
  dan view mengurangi stok hanya sebesar qty yang belum direfund.
- `tests/unit/ModelRefundGaransiLossTest.php`: total refund difilter tanggal
  refund, unit, dan teknisi; refund bulan lain tidak ikut.
- `tests/unit/GaransiRefundKpiTest.php`: `OmsetTokoCalculator`,
  `OmsetCabangCalculator`, dan `OmsetTeknisiCalculator` berkurang sebesar
  refund pada bulan refund, sedangkan bulan service selesai tidak berubah.

## Risiko

- Akun debet refund memakai `2010302000` mengikuti pola refund DP. Pembayaran
  klaim asal belum dijurnal, jadi Finance perlu mengonfirmasi akun ini.
- Perubahan view `stok_barang` memengaruhi semua laporan stok. Test view wajib
  memastikan qty refund mengurangi pemakaian garansi, bukan menambah stok dua
  kali.
- Keputusan kondisi per baris, bukan per qty. Kalau nanti butuh sebagian qty
  baik dan sebagian rusak, perlu kolom qty refund terpisah per kondisi.
- `OmsetTokoCalculator`/`OmsetCabangCalculator` menghitung gross profit dari
  tabel penjualan, sedangkan pembayaran klaim garansi tidak pernah masuk tabel
  penjualan. Mengurangi KPI sebesar nominal refund adalah penyesuaian
  manajemen yang diminta; angkanya tidak otomatis sama dengan laba akuntansi.
- `ModelHppKlaimLoss` hanya membaca `status_retur` `tidak_bisa`/`ditolak`.
  Refund rusak harus mengubah status itu, dan baris yang sudah `diterima`
  tidak boleh direfund agar riwayat retur supplier tidak ditimpa.
