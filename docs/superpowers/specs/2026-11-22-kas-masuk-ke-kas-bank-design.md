# Kas Masuk Benar-Benar Menambah Saldo Kas & Bank — Design

**Tanggal:** 2026-11-22
**Status:** revisi 3 — disetujui, siap implementasi
**Issue:** [#234](https://github.com/bwafi/ERP-iClear/issues/234)

## 0. Perubahan pada revisi 2 dan 3

Revisi 1 memakai asumsi `posisi_drk = debet` berarti uang masuk. Asumsi itu
**salah**. Bukti kode dan data nyata ada di §5.5. Revisi ini:

- menetapkan `kas_masuk.jenis` sebagai posisi COA, bukan arah kas;
- mengusulkan aturan **Kas Masuk = inflow-only** (semua baris non-`kas awal`
  menambah saldo), bukan `debet` menambah / `kredit` mengurangi;
- memasukkan Kas Masuk tunai ke dalam `cashMasuk()` privat supaya
  `movementKas()` dan `rincianMovement()` memakai angka yang sama;
- menambahkan aturan unit/entitlement untuk BANK agar invariant
  `saldo_fisik == LEGACY + SUM(posisiUnit)` tetap berlaku;
- mencatat laporan mentah lain yang juga membaca `kas_masuk`;
- membatasi form Kas Masuk (hapus `posisi_drk`, paksa `jenis = 'kredit'`,
  saring COA) supaya input baru konsisten inflow-only. Keputusan ini disetujui
  user pada revisi 2.

## 1. Intent

Halaman `/kas_masuk` menyimpan baris ke tabel `kas_masuk`, tetapi baris itu
tidak pernah menambah saldo Kas & Bank. Akibatnya uang yang diinput lewat Kas
Masuk tidak muncul di kartu saldo, Tutup Kasir, Rekonsiliasi Harian, maupun
Detail Omset Harian.

Tujuan: **input Kas Masuk menjadi sumber pergerakan uang yang sah**, baik tunai
(KAS unit) maupun transfer (rekening bank yang dipilih), dan ikut dihitung di
semua laporan uang yang memakai definisi Tutup Kasir.

## 2. Goals

- Baris `kas_masuk` dengan `idbank IS NULL` menambah saldo rekening **KAS** unit
  pada `idunit`.
- Baris `kas_masuk` dengan `idbank` terisi menambah saldo rekening **BANK**
  yang `bank_idbank`-nya sama dengan `kas_masuk.idbank`.
- Baris `deskripsi = 'kas awal'` **tidak** dihitung sebagai penerimaan baru.
- Kas Masuk ikut di:
  - saldo dan kartu Pemasukan/Pengeluaran halaman `/kas_bank`;
  - Tutup Kasir (`TutupKasirClosing::hitung`) dan carry-forward
    `akhir_cash`/`akhir_transfer`;
  - Rekonsiliasi Harian (`RekonDailyCalculator`);
  - drill-down Detail Omset Harian (`DailyCashFlowService`).
- Form Kas Masuk dibatasi agar konsisten inflow-only: `posisi_drk` dihapus,
  `jenis` selalu `kredit`, dan akun beban/HPP/akumulasi penyusutan/kas-bank
  tidak bisa dipilih.
- Tidak ada perubahan schema.

## 3. Non-goals

- **Tidak** menghidupkan kembali posting `kas_masuk` ke `transaksi_kas_bank`.
  `postingKasMasuk` tetap `skipped` karena `kas_masuk` tidak punya
  `transfer_ref`.
- **Tidak** memperbaiki jurnal double-entry Kas Masuk. Saat ini jurnal hanya
  menulis satu baris untuk akun yang dipilih; leg kas/bank tidak ditulis.
- **Tidak** mengubah KPI Cash Flow (`CashFlowCalculator::calculate`) yang
  berbasis penjualan + service. `harianRange()` tetap sales-only supaya grafik
  KPI tidak berbeda dari skor KPI-nya.
- **Tidak** mengubah KPI Kesehatan Uang secara langsung; KPI itu berubah
  otomatis karena membaca saldo dari `KasBankCutoffService::posisiUnit`.
- **Tidak** mengubah `kas_keluar`, penjualan, service, DP, Setor/Tarik, atau
  alur reversal yang sudah ada.
- **Tidak** mengubah form Kas Keluar, master `no_akun`, atau master
  `kategori_kas`. Pembatasan hanya di form Kas Masuk.
- **Tidak** memperbaiki baris jurnal lama yang terlanjur memakai COA beban.
  Baris itu dianggap dummy dan akan dihapus user; form restriction hanya
  berlaku untuk input baru dan edit berikutnya.
- **Tidak** menambah validasi unit di write path. Temuan bahwa
  `Kas_Masuk::index` memakai `ModelUnit::getUnit()` mentah dan server tidak
  memvalidasi ulang scope unit adalah temuan terpisah.
- **Tidak** memperbaiki laporan mentah `LabaRugi`/`DashboardKeuangan`/
  `DashboardLabaRugi` di revisi ini; lihat §7.6.

## 4. Keputusan

1. **Pendekatan A — source movement.** Saldo Kas & Bank dihitung dari tabel
   sumber (`KasBankSourceMovement`), bukan dari ledger. Karena itu `kas_masuk`
   harus dibaca sebagai tabel sumber; menulis `transaksi_kas_bank` saja tidak
   akan mengubah saldo.
2. **Tutup Kasir ikut menghitung.** `akhir_cash`/`akhir_transfer` menjadi saldo
   awal hari berikutnya. Kalau Kas Masuk tidak ikut Tutup Kasir, saldo akan
   surplus/selisih setiap hari.
3. **Rekon Harian dan Detail Omset Harian ikut menghitung** supaya angka ERP
   sama dengan Tutup Kasir.
4. **Hanya input baru yang dihitung.** Data lama dianggap dummy dan akan
   dihapus user. Tidak ada backfill atau migrasi data.
5. **`deskripsi = 'kas awal'` dikecualikan** karena itu opening, bukan
   penerimaan baru.
6. **Kas Masuk adalah inflow-only.** Setiap baris non-`kas awal` menambah saldo
   sebesar `jumlah`. `jenis` **tidak** dipakai untuk menentukan arah kas;
   nilainya adalah posisi COA untuk baris jurnal. Lihat §5.5 dan §6.1.
7. **Form Kas Masuk dibatasi di issue ini** (disetujui user):
   - `posisi_drk` dihapus dari form input dan modal edit;
   - controller memaksa `jenis = 'kredit'`, karena COA yang dipilih adalah
     akun lawan dan sisi kas/bank didebit secara implisit;
   - COA yang bisa dipilih disaring: tanpa `jenis_akun`, `Kas & Bank`,
     `Beban%`, `Harga Pokok Penjualan`, dan `Akumulasi Penyusutan Aset`
     dikecualikan;
   - validasi akun diulang di server, bukan hanya di UI.
8. **Tidak ada perubahan schema.**

## 5. Fakta kode dan akar masalah

### 5.1 Saldo Kas & Bank tidak membaca `kas_masuk`

Rantai saldo:

```
KasBank::index
  -> ModelTransaksiKasBank::getSaldoFisikAkun
  -> KasBankCutoffService::saldoFisik
  -> opening + KasBankSourceMovement::netMovement
```

`KasBankSourceMovement::movementKas()` (`:454-482`) hanya membaca:

- `penjualan.bayar_tunai` (non-`srv%`),
- `service.bayar_tunai` status 4,
- DP tunai (`service_dp`),
- `kas_keluar` tunai.

`movementBank()` (`:488-540`) hanya membaca:

- `kas_keluar.idbank`,
- transfer penjualan + residual service ke rekening default unit,
- DP transfer ke `service_dp.bank_idbank`,
- ledger internal (`transfer_ref IS NOT NULL`).

Tidak ada satu pun yang membaca `kas_masuk`. Itulah sebabnya input Kas Masuk
tidak mengubah saldo.

### 5.2 Posting ledger `kas_masuk` selalu `skipped`

`ModeKasBank::postingKasMasuk()` (`:424-427`) berhenti di guard:

```php
$transferRef = $row->transfer_ref ?? null;
if (empty($transferRef)) {
    return ['status' => 'skipped', ...];
}
```

`kas_masuk` tidak punya kolom `transfer_ref`, jadi guard selalu benar.
`PostingLedgerException::wajibBerhasil()` hanya melempar untuk status `failed`,
sehingga `skipped` dianggap sukses. UI menampilkan "berhasil" dengan ledger
kosong. **Tidak diperbaiki di desain ini** karena saldo memang tidak membaca
ledger.

### 5.3 Tutup Kasir memakai definisi yang sama

`TutupKasirClosing::hitung()` (`:158-168`) memakai:

```php
$kasMasuk      = $srcDef->kasMasukDp($unitId, $tanggal);
$transferMasuk = $srcDef->transferMasukDp($unitId, $tanggal);
$akhirCash     = $awalCashNum + $kasMasuk - $kasKeluar - $setor + $tarik;
$akhirTransfer = $awalTfNum + $transferMasuk - $transferKeluar;
```

`kasMasukDp()` = `cashMasuk() + dpMasukTunai()`.
`transferMasukDp()` = `transferMasuk() + dpMasukTransfer() - dpServiceSelesai()`.
Tidak ada `kas_masuk` di keduanya. `TutupKasirSaldoAwal` membaca
`tutup_kasir.akhir_cash`/`akhir_transfer` sebagai carry-forward, jadi perubahan
definisi otomatis terbawa ke hari berikutnya.

### 5.4 Kolom yang tersedia

`kas_masuk` (dari `ModelKasMasuk::$allowedFields` dan test schema):

`idkas_masuk`, `tanggal`, `kategori_idkategori`, `deskripsi`, `jumlah`,
`penerima`, `idunit`, `idbank`, `created_on`, `updated_on`, `jenis`, `no_akun`.

- `idbank` adalah VARCHAR yang merujuk `bank.idbank` (bukan
  `akun_kas_bank.idakun_kas_bank`).
- `idbank IS NULL` = tunai; `idbank` terisi = transfer.

### 5.5 `jenis` adalah posisi COA, BUKAN arah kas — bukti

Ini koreksi utama terhadap revisi 1.

**Kode:**

- `Kas_Masuk::insert_kas_masuk()` `:93` membaca `$jenis = $data['posisi_drk']`,
  `:132` menyimpannya ke `kas_masuk.jenis`, lalu `:158-159` memakai nilai itu
  untuk menulis `debet`/`kredit` pada `no_akun` di jurnal.
- `Kas_Masuk::update_kas_masuk()` `:241` melakukan hal yang sama.
- PRD 05 §4.1 dan BR 6 menyatakan `posisi_drk` menentukan `debet/kredit` baris
  jurnal, sama seperti `Kas_Keluar` (`Kas_Keluar.php:499,534-536`).
- View `app/Views/jurnal/kas_masuk.php:397-401` memberi label kolom itu
  "Jenis" dengan pilihan Debet/Kredit untuk baris akun yang dipilih.

**Data nyata** (`backups/erp_local_2026-11-12_195540.sql.gz`):

- 932 baris `kas_masuk`; 926 di antaranya `deskripsi = 'kas awal'`.
- 6 baris non-`kas awal` (`idkas_masuk` 608-612 dan 637) **semuanya**
  `jenis = 'debet'` dan `idbank = '2'` (transfer), dengan
  `no_akun = '6000000000'` (BEBAN OPERASIONAL) atau `'1000000000'` (header
  ASET), dan deskripsi `BEBAN`, `TISU`, `BATERAI ...`. Ini **pengeluaran**,
  bukan penerimaan, meskipun tersimpan di tabel `kas_masuk`.
- `kategori_kas.jenis_kas` untuk 19 kategori seluruhnya `kas_keluar`; form Kas
  Masuk memakai daftar kategori pengeluaran yang sama.
- `jurnal` untuk baris-baris itu (`idjurnal` 7899-7903 dan 8220) mencatat
  `no_akun = 6000000000`/`1000000000`, `debet = jumlah`, `kredit = 0`.
  Pasangan lama `kas_masuk` 302-305 sudah dihapus, tetapi jurnalnya
  (`idjurnal` 4488-4491) masih ada — bukti tambahan bahwa `jenis = debet`
  pada akun beban berarti uang **keluar**.

**Kesimpulan:** `jenis = debet` tidak berarti uang masuk. Nilai itu hanya
menentukan sisi COA di jurnal. Karena itu aturan "debet menambah, kredit
mengurangi" tidak boleh dipakai untuk saldo kas.

## 6. Perilaku yang diusulkan

### 6.1 Definisi nominal — inflow-only

Tambahkan definisi di `TutupKasirSourceDefinition` agar semua laporan memakai
angka yang sama:

```
kasMasukManualCash(unit, tanggal)     = SUM(jumlah) WHERE idunit = unit
                                          AND idbank IS NULL
                                          AND NOT (deskripsi = 'kas awal')
kasMasukManualTransfer(unit, tanggal) = SUM(jumlah) WHERE idunit = unit
                                          AND idbank IS NOT NULL
                                          AND NOT (deskripsi = 'kas awal')
```

`jenis` **tidak** muncul di formula. Alasannya ada di §5.5: nilainya adalah
posisi COA, bukan arah kas. Halaman Kas Masuk adalah kontrak inflow; untuk uang
keluar sudah ada halaman Kas Keluar.

Pengecualian `kas awal` harus NULL-safe:

```sql
(deskripsi IS NULL OR LOWER(TRIM(deskripsi)) <> 'kas awal')
```

Jika nanti arah berbasis `jenis` dibutuhkan, itu perubahan terpisah; keputusan
saat ini ada di §12.1 dan formula tinggal mengganti `SUM(jumlah)` menjadi
`SUM(CASE WHEN ... THEN -jumlah ELSE jumlah END)`.

### 6.2 Formula baru

```
kasMasukDp        = cashMasuk + dpMasukTunai + kasMasukManualCash
transferMasukDp   = transferMasuk + dpMasukTransfer - dpServiceSelesai
                    + kasMasukManualTransfer

akhir_cash        = awal_cash + kasMasukDp - kasKeluarCash - setor + tarik
akhir_transfer    = awal_transfer + transferMasukDp - kasKeluarTransfer
```

Saldo fisik:

```
netMovement(KAS)  += kasMasukManualCashRange(unit akun, dari, sampai)
netMovement(BANK) += kasMasukManualTransferBankRange(bank_idbank, unit, dari, sampai)
```

`kasMasukManualTransferRange(unit, ...)` di §6.1 adalah total seluruh bank
untuk satu unit (dipakai Tutup Kasir/Rekon). Untuk saldo rekening, BANK butuh
varian per rekening `kasMasukManualTransferBankRange()` yang memfilter
`idbank = bank_idbank`.

Untuk rekening BANK:

- `$unitId !== null` → filter `idunit = $unitId` dan `idbank = bank_idbank`,
  dan hanya dihitung bila `unitBerhakAtasRekening($akun, $unitId)`.
- `$unitId === null` (saldo fisik) → filter `idbank = bank_idbank` saja, sama
  seperti `kas_keluar` saat konsolidasi.
- Baris baru divalidasi `BankRekeningValidator::validateUntukUnit()` di write
  path, jadi `idunit` baris baru selalu berhak atas `idbank`. Dengan begitu
  `netMovement(null) == SUM(netMovement(unit))` untuk baris baru dan invariant
  `saldo_fisik == LEGACY + SUM(posisiUnit)` tetap berlaku.
- Baris legacy lintas unit (437 baris menurut `BankRekeningValidator`) dapat
  merusak invariant, persis seperti `kas_keluar` legacy. Ini bukan regresi baru;
  user berencana menghapus data dummy.
- Rekening Finance/HO tetap dihitung secara fisik; `posisiUnit` tetap
  mengembalikan 0 untuk unit yang tidak entitled (`entitled()` `:694-696`).

### 6.3 Klasifikasi di drill-down

`DailyCashFlowService` menambahkan sumber `kas_masuk`:

- `idbank IS NULL` → `masuk.tunai`.
- `idbank` terisi → `masuk.transfer`.
- `jumlah` selalu positif (inflow-only). Baris `kredit` tidak dipindah ke bucket
  keluar.
- Keterangan memakai `deskripsi`; `sumber` = `Kas Masuk`.
- Waktu memakai `created_on`, fallback `updated_on`.
- Baris `deskripsi = 'kas awal'` tidak diambil.

### 6.4 Yang tidak berubah

- `TutupKasirSourceDefinition::harianRange()` **tidak** diubah. Method ini
  hanya dipakai `CashFlowCalculator::daily()` (grafik KPI Cash Flow). Kalau
  Kas Masuk ditambahkan di sini tanpa mengubah skor KPI, grafik dan skor akan
  berbeda. Konsekuensinya, total `ringkasanRange()` di `/kas_bank` dan grafik
  KPI harian memang berbeda; itu disengaja karena keduanya fitur berbeda.
- `CashFlowCalculator::calculate()` tidak berubah.
- `ModeKasBank::postingKasMasuk()` tidak berubah.
- `Kas_Masuk` controller **berubah hanya** untuk memaksa `jenis = 'kredit'`
  dan memvalidasi COA di server; lihat §7.7. Alur transaksi, validasi
  rekening, posting ledger, dan jurnal tidak berubah.
- Tidak ada penulisan ke `transaksi_kas_bank`, `jurnal`, atau tabel lain.

## 7. File dan perubahan

### 7.1 `app/Services/Finance/TutupKasirSourceDefinition.php`

Tambahkan:

- `kasMasukManualCash(int $unitId, string $tanggal): int`
- `kasMasukManualTransfer(int $unitId, string $tanggal): int`
- `kasMasukManualCashRange(int $unitId, string $dari, string $sampai): int`
- `kasMasukManualTransferRange(int $unitId, string $dari, string $sampai): int`
- `kasMasukManualTransferBankRange(string $bankId, ?int $unitId, string $dari, ?string $sampai = null): int`
  — pola yang sama dengan `dpMasukTransferBankRange()`; dipakai
  `KasBankSourceMovement::movementBank()`.
- `kasMasukManualHarianRange(int $unitId, string $dari, string $sampai): array`
  dengan bentuk `['cash' => ['YYYY-MM-DD' => int], 'transfer' => [...]]`
  untuk `RekonDailyCalculator`.
- helper private untuk query dasar + pengecualian `kas awal` + `memo()`.

Ubah:

- `kasMasukDp()` → tambah `kasMasukManualCash()`.
- `transferMasukDp()` → tambah `kasMasukManualTransfer()`.
- `ringkasan()` → `cash`/`transfer` ikut manual (method ini hanya dipakai test,
  tetapi dijaga konsisten).
- `ringkasanRange()` → `cash`/`transfer` ikut manual; tambahkan key
  `kas_masuk_tunai`/`kas_masuk_transfer` untuk transparansi.
- Docblock atas (`:27-37`, `:87-90`) tetap benar untuk `kas awal`; tambahkan
  catatan bahwa baris non-`kas awal` sekarang menjadi sumber movement dan
  `jenis` bukan arah kas.

Komentar yang harus dikoreksi karena menyatakan `kas_masuk` hanya berisi
`kas awal`:

- `app/Services/Finance/CashFlowCalculator.php:28-29`.
- `app/Services/Finance/KasBankCutoffService.php:24-27`.

### 7.2 `app/Services/Finance/KasBankSourceMovement.php`

Ubah:

- **`cashMasuk()` privat (`:555-580`)** → tambah
  `kasMasukManualCashRange($unit, $dari, $sampai)`. Ini satu titik yang otomatis
  membuat `movementKas()` **dan** `rincianMovement()` (KAS) konsisten, sehingga
  `alurKasSampai()`/`saldo_buku` tidak berbeda dari `saldoFisik()`.
- **`movementBank()` (`:488-540`)** → tambah
  `kasMasukManualTransferBankRange($bankId, $unitId, $dari, $sampai)`, dengan
  filter unit seperti `kas_keluar`:
  - `$unitId !== null`: helper memfilter `idunit = $unitId`; tambahan guard
    `unitBerhakAtasRekening()` di `movementBank()`.
  - `$unitId === null`: helper memfilter `idbank = $bankId` saja.
- `rincianMovement()` untuk BANK **tidak** diubah: method itu memang hanya
  membaca ledger internal (`movementInternalUnionRincian`), bukan
  `movementBank()`. `alurKasSampai()` tidak punya caller produksi
  (`grep` hanya menemukan definisinya), jadi tidak ada angka UI yang
  bergantung pada rincian BANK.

Tidak mengubah `movementInternalUnion()`: baris mirror `kas_masuk` tidak punya
`transfer_ref`, jadi tidak dobel.

### 7.3 `app/Services/Finance/RekonDailyCalculator.php`

Ubah `erpValuesRange()`:

- Tambah satu query GROUP BY `kas_masuk` per tanggal (memakai
  `kasMasukManualHarianRange()`).
- Split `idbank IS NULL` → `cash_masuk`, `idbank IS NOT NULL` →
  `transfer_masuk`.
- Pengecualian `kas awal`.

`erpValues()` tidak perlu query baru karena sudah memakai `kasMasukDp()` /
`transferMasukDp()`.

### 7.4 `app/Services/Finance/DailyCashFlowService.php`

- Tambah `fetchKasMasuk(int $unitId, string $tanggal): array`.
- Kirim `'kas_masuk' => $this->fetchKasMasuk(...)` dari `getForDate()`.
- Konsumsi `$sources['kas_masuk']` di `assemble()` dan **saring juga**
  `deskripsi = 'kas awal'` di sana, supaya `assemble()` tetap benar bila
  dipanggil langsung dengan row mentah (dan bisa diuji tanpa database).
- Perbarui docblock `:26-31` dan `:322-329`: `kas_masuk` sekarang dibaca,
  kecuali `kas awal`.

### 7.5 Test schema

Karena §6.1 tidak memakai `jenis`, tiga schema yang sudah punya `kas_masuk`
tetapi belum punya kolom `jenis` **tidak perlu diubah** untuk query baru:

- `tests/TutupKasirSharedAllocationTest.php`
- `tests/TutupKasirClosingHardeningTest.php`
- `tests/TutupKasirSaldoAwalTest.php`

Yang perlu dipastikan hanya `deskripsi`, `jumlah`, `idunit`, `idbank`,
`tanggal` — semuanya sudah ada. `DailyCashFlowServiceTest` memakai object tanpa
`jenis`; `assemble()` tidak boleh membutuhkannya.

Sebaliknya, empat test berikut memanggil service yang akan query `kas_masuk`
tetapi **belum membuat tabelnya**. Tanpa penambahan schema, test itu akan gagal
dengan "table not found":

- `tests/unit/TutupKasirDpTest.php` — memanggil `kasMasukDp()` /
  `transferMasukDp()`.
- `tests/KasBankAlurSampaiTanggalTest.php` — memanggil `ringkasanRange()`.
- `tests/unit/RekonAntarUnitTest.php` — memanggil `erpValuesRange()`.
- `tests/unit/RekonDpTest.php` — memanggil `erpValuesRange()`.

`tests/RekonKpiTest.php` hanya memanggil `calculate()` (tidak membaca
`kas_masuk`), jadi tidak perlu diubah.

Selain itu, audit seed `kas_masuk` yang sudah ada di `tests/KasBankTest.php`
(`:234`, `:277`, `:1141`) dan `tests/KasBankControllerTest.php` (`:690`).
Baris non-`kas awal` yang dulu inert sekarang menambah movement; assertion
saldo/ringkasan yang terpengaruh harus disesuaikan atau barisnya diubah menjadi
`kas awal`.

`tests/KasBankControllerTest.php` juga perlu dua penyesuaian schema/seed:

- `CREATE TABLE db_no_akun` (`:199`) harus menambah `jenis_akun TEXT NULL`,
  karena `getAkunMasuk()` dan `bolehUntukKasMasuk()` membaca kolom itu;
- `seedNoAkun()` (`:2925`) harus mengisi `jenis_akun` yang valid (mis.
  `Pendapatan`) untuk test happy path `testA1KasMasukValidTetapCommitDanIdempoten`
  dan test rollback `testA1CreateKasMasukRollbackSa...`. Tanpa itu, akun
  `5-1010` ber-`jenis_akun` NULL akan ditolak sebelum validasi rekening, dan
  assertion `flashGagal()` berisi `BCA-X` tidak lagi terpenuhi.

`tests/KasBankTest.php` tidak memakai `insert_kas_masuk`, jadi tidak butuh
`jenis_akun`; cukup audit baris `kas_masuk` yang di-seed terhadap assertion
saldo.

Jika §12.1 memilih aturan berbasis `jenis`, semua schema `kas_masuk` di atas
harus ditambah `jenis TEXT NULL`.

### 7.6 Laporan mentah lain yang membaca `kas_masuk`

Tiga laporan berikut menjumlahkan **seluruh** `kas_masuk` (termasuk
`kas awal`) sebagai pendapatan, tanpa filter `deskripsi`:

- `app/Controllers/LabaRugi.php:148-155` (total) dan `:157-167` (detail).
- `app/Controllers/DashboardKeuangan.php:292-302` (total) dan `:155-161`
  (per kategori).
- `app/Controllers/DashboardLabaRugi.php:104-114`.

Ini sudah tidak konsisten dengan Tutup Kasir sebelum perubahan ini: `kas awal`
(opening) ikut dihitung sebagai pendapatan. Desain ini **tidak** mengubah
mereka karena scope-nya saldo Kas & Bank, Tutup Kasir, Rekon, dan Detail Omset.
Risikonya dicatat di §12.4; jika user ingin "tidak ada surplus" juga di laporan
laba-rugi, laporan itu perlu perubahan terpisah (minimal mengecualikan
`kas awal`).

### 7.7 Form Kas Masuk — pembatasan COA dan `posisi_drk`

Tujuan: input baru tidak bisa lagi mencatat pengeluaran lewat halaman Kas
Masuk. Karena saldo memakai aturan inflow-only (§6.1), form harus menjamin
COA yang dipilih adalah **akun lawan yang wajar untuk uang masuk**, dan baris
jurnal selalu berada di sisi kredit.

**Aturan COA.** `ModelNoAkun` mendapat dua method:

- `getAkunMasuk(): array` — daftar akun untuk modal "Cari Buku".
- `bolehUntukKasMasuk(object $akun): bool` — predikat yang sama, dipakai
  controller untuk validasi server.

Akun **ditolak** bila salah satu benar:

| Kondisi | Alasan |
|---|---|
| `jenis_akun` NULL/kosong | Tidak bisa diklasifikasikan; gagal tertutup. |
| `jenis_akun` = `Kas & Bank` | Sisi kas/bank implisit; memilihnya akan mengkredit kas. |
| `jenis_akun` berawalan `Beban` | Akun beban adalah pengeluaran, bukan sumber uang masuk. |
| `jenis_akun` = `Harga Pokok Penjualan` | HPP bukan akun lawan kas masuk. |
| `jenis_akun` = `Akumulasi Penyusutan Aset` | Akun kontra-aset untuk penyusutan, bukan penerimaan. |

Akun lain (Pendapatan, Piutang, Liabilitas, Ekuitas, Aset, Persediaan, dan
sebagainya) tetap boleh. Ini **blacklist**, bukan whitelist: kategori baru
otomatis boleh sampai ada alasan untuk menolaknya. Trade-off ini disengaja
agar master akun tidak perlu diubah setiap kali ada kategori baru; risiko
kategori salah tetap ditutup oleh validasi `jenis_akun` di atas.

Perbandingan dilakukan case-insensitive dan trim, baik di query maupun di
predikat PHP, supaya perilaku MySQL dan SQLite sama. `LIKE 'Beban%'` aman
untuk `Pendapatan & Beban Pajak Penghasilan` karena tidak berawalan `Beban`.

Sketsa query yang portabel (MySQL & SQLite):

```sql
WHERE jenis_akun IS NOT NULL
  AND LOWER(TRIM(jenis_akun)) NOT IN
      ('kas & bank', 'harga pokok penjualan', 'akumulasi penyusutan aset')
  AND LOWER(TRIM(jenis_akun)) NOT LIKE 'beban%'
```

Predikat PHP memakai aturan yang sama. Karena `composer.json` masih mendukung
PHP 7.4, jangan memakai `str_starts_with()`; pakai
`strpos($jenis, 'beban') === 0`.

**Perubahan per file.**

- `app/Models/ModelNoAkun.php`
  - tambah `getAkunMasuk()` dan `bolehUntukKasMasuk()`;
  - `getAkun()` dan `getByNoAkun()` tidak berubah, supaya halaman lain
    (Kas Keluar, jurnal, laporan) tetap memakai daftar penuh.
- `app/Controllers/Kas_Masuk.php`
  - `index()`: `'no_akun' => $this->NoAkunModel->getAkunMasuk()`.
  - `insert_kas_masuk()`:
    - hapus pembacaan `posisi_drk`; set `$jenis = 'kredit'`;
    - tolak request tanpa baris `akun[]` sebelum transaksi;
    - validasi **semua** baris `akun[]` sebelum `transStart`:
      `no_akun` wajib ada di `no_akun` dan `bolehUntukKasMasuk()` harus
      `true`. Bila gagal, flash `gagal` berisi nomor akun + alasan lalu
      redirect, tanpa menulis apa pun;
    - validasi rekening dan alur posting/jurnal yang ada tidak berubah.
  - `update_kas_masuk()`:
    - hapus pembacaan `posisi_drk`; set `'jenis' => 'kredit'`;
    - `no_akun` tidak diedit di modal, jadi tidak ada validasi ulang akun.
      Baris legacy ber-COA beban tetap bisa diedit nominalnya; `jenis`-nya
      menjadi `kredit` tetapi jurnal lama tidak ditulis ulang (di luar scope,
      §3).
- `app/Views/jurnal/kas_masuk.php`
  - hapus `<th>Jenis</th>` pada tabel baris akun (`:168`) dan select
    `akun[i][posisi_drk]` (`:396-402`);
  - hapus select `edit_posisi_drk` (`:280-287`) dan baris JS yang mengisinya
    (`:333`);
  - modal "Cari Buku" otomatis hanya menampilkan akun dari
    `getAkunMasuk()`;
  - kolom "Jenis" di tabel daftar (`:84`, `:101`) **tetap** untuk melihat
    nilai historis; baris baru akan menampilkan `kredit`.

**Yang tidak berubah.** Field `jenis_transaksi` (Kas/Bank) dan `no_rekening`
tetap dipakai untuk menentukan `idbank`; tidak ada perubahan pada validasi
rekening, transaksi database, posting ledger, atau penulisan jurnal selain
nilai `jenis` yang kini konstan `kredit`.

## 8. Edge cases

| Kasus | Perilaku |
|---|---|
| `deskripsi = 'kas awal'` | Dikecualikan dari movement, Tutup Kasir, Rekon, dan drill-down. Case-insensitive + trim. |
| `deskripsi` variasi kapitalisasi (`Kas Awal`) | Dikecualikan dari manual movement, tetapi accessor legacy `kasAwalCash` tetap exact-match sehingga tidak masuk opening. Efeknya baris itu tidak muncul di mana pun. Ini disengaja agar opening tidak bocor sebagai pendapatan; data nyata memakai huruf kecil semua. |
| `deskripsi` NULL | Tetap dihitung (bukan `kas awal`). |
| `jenis` apa pun (`debet`, `kredit`, NULL) | Tidak memengaruhi arah kas; baris tetap inflow. `jenis` hanya untuk jurnal. |
| `idbank IS NULL` | Tunai, masuk ke rekening KAS unit `idunit`. |
| `idbank` terisi | Transfer, masuk ke rekening BANK dengan `bank_idbank` yang sama. |
| `idbank` tidak cocok rekening mana pun | Tidak masuk saldo rekening mana pun; tetap terlihat di halaman Kas Masuk. Data invalid; validator menolak input baru. |
| Rekening BANK shared | Dihitung per `idbank`; unit-scoped memakai `idunit` baris + cek entitlement. |
| Rekening Finance/HO | Saldo fisik bertambah; `posisiUnit` tetap 0 untuk unit (tidak entitled). |
| Unit HO / non-operasional (`is_operational = 0`) | KAS HO (unit 50) adalah rekening fisik; tetap dihitung. Tidak ada filter `is_operational` di jalur saldo. |
| Tanggal sebelum `kasBankPeriodeMulaiDate()` | Tidak memengaruhi saldo Kas & Bank (movement dibatasi periode), tetapi tetap dihitung Tutup Kasir untuk tanggal itu. Sama seperti sumber lain. |
| Baris legacy lintas unit | Bisa ikut dihitung bila cocok `idbank`/`idunit`; dapat merusak invariant seperti `kas_keluar` legacy. User berencana menghapus data dummy. |
| Double count dengan ledger | Tidak terjadi selama `postingKasMasuk` tetap `skipped` (tidak ada `transfer_ref`). |
| Closing historis | Closing yang sudah tersimpan **tidak** berubah retroaktif; carry-forward hari berikutnya baru memakai angka baru setelah closing ulang. |
| Snapshot Rekon Harian | Baris `finance_rekon_daily` yang sudah disimpan adalah snapshot; angka ERP tersimpan tidak berubah retroaktif. Hari yang belum disimpan memakai angka baru. |
| Baris dummy non-`kas awal` (BEBAN/TISU/BATERAI, `idkas_masuk` 608-612/637) | Akan menambah saldo rekening BANK `idbank='2'` setelah deploy. **Harus dihapus sebelum deploy.** |

## 9. Test plan

### 9.1 Unit tanpa database

`tests/unit/DailyCashFlowServiceTest.php`:

- `testKasMasukDanKasKeluarDibedakanIdbank` sudah ada dan akan lulus setelah
  `assemble()` mengonsumsi `kas_masuk`.
- `testBarisTanpaJamDiurutkanTerakhir` sudah ada dan akan lulus.
- Tambah: baris `deskripsi = 'kas awal'` tidak muncul.
- Tambah: object tanpa `jenis` tetap dianggap inflow.

### 9.2 Database test

Test baru (pola `TutupKasirClosingHardeningTest` / `KasBankTest`):

1. `TutupKasirSourceDefinition::kasMasukDp()`:
   - tunai menambah `cash`;
   - transfer menambah `transfer`;
   - `kas awal` tidak ikut;
   - `jenis = kredit` tetap menambah (inflow-only). Test baru ini membuat
     schema sendiri dan menyertakan kolom `jenis` hanya untuk membuktikan
     nilainya diabaikan; schema test lama tidak perlu diubah.
2. `KasBankSourceMovement::netMovement()`:
   - KAS: baris tunai menambah saldo rekening KAS unit;
   - BANK: baris dengan `idbank` menambah saldo rekening yang cocok;
   - BANK lain tidak ikut;
   - `kas awal` tidak ikut;
   - unit tidak entitled tidak ikut (unit-scoped).
3. `TutupKasirClosing::hitung()`:
   - `akhir_cash`/`akhir_transfer` berubah sesuai Kas Masuk;
   - carry-forward hari berikutnya memakai angka baru.
4. `RekonDailyCalculator::erpValuesRange()` sama dengan `erpValues()` per
   tanggal setelah Kas Masuk ditambahkan.
5. `DailyCashFlowService::getForDate()` memuat baris Kas Masuk dan
   mengklasifikasikan tunai/transfer.
6. `ringkasanRange()` memuat Kas Masuk di `cash`/`transfer`.
7. Invariant: `cekInvariant()` tetap nol selisih untuk baris baru yang lolos
   `BankRekeningValidator`.
8. Regresi: `kas_keluar`, penjualan, service, dan DP tidak berubah.

### 9.3 Test lama yang perlu diperhatikan

Tiga test berikut sudah tidak sesuai dengan guard `transfer_ref` dan **bukan
bagian dari perubahan ini**:

- `tests/KasBankTest.php::testPostingKasMasukIdempotent` (`:232`)
  mengharapkan `inserted`.
- `tests/KasBankTest.php::testSaldoModel` (`:935`) memanggil test di atas,
  jadi ikut gagal.
- `tests/KasBankControllerTest.php::testA1UpdateKasMasukMemulihkanPostingLamaSaatGagal`
  (`:3052`) mengharapkan 1 baris ledger.

Ketiganya akan tetap gagal sampai ada keputusan terpisah tentang posting
`kas_masuk`. Desain ini tidak mengubah perilaku tersebut.

### 9.4 Form restriction

Test baru:

1. `ModelNoAkun::getAkunMasuk()`:
   - mengecualikan `jenis_akun` NULL, `Kas & Bank`, `Beban%`, HPP, dan
     akumulasi penyusutan;
   - tetap memuat Pendapatan, Piutang, Liabilitas, Ekuitas, dan Aset Tetap;
   - `Pendapatan & Beban Pajak Penghasilan` tetap boleh.
2. `Kas_Masuk::insert_kas_masuk()`:
   - baris dengan COA beban ditolak dan **tidak** menulis `kas_masuk` atau
     `jurnal`;
   - baris dengan COA pendapatan tersimpan dengan `jenis = 'kredit'`
     walaupun request tidak mengirim `posisi_drk`;
   - campuran baris valid + tidak valid membatalkan seluruh batch.
3. `Kas_Masuk::update_kas_masuk()`:
   - `jenis` menjadi `kredit` tanpa `posisi_drk` di request.
4. View/regresi:
   - tidak ada lagi input `posisi_drk` di form input maupun modal edit.

## 10. Deployment dan rollback

- **Tanpa migrasi.** Tidak ada perubahan schema.
- **Sebelum deploy:** hapus baris `kas_masuk` non-`kas awal` yang dummy
  (`idkas_masuk` 608-612 dan 637: BEBAN/TISU/BATERAI). Jika tidak, baris itu
  akan menambah saldo rekening BANK `idbank='2'`.
- **Form restriction** hanya berlaku untuk input baru dan edit berikutnya.
  Baris legacy ber-COA beban tidak diubah otomatis; hapus manual bersama data
  dummy.
- **Efek langsung:** setelah deploy, semua baris `kas_masuk` (kecuali
  `kas awal`) yang tanggalnya dalam periode aktif langsung menambah saldo.
- **Closing historis** tidak berubah retroaktif. Jika perlu koreksi, closing
  harus dihitung ulang lewat alur yang ada.
- **Rollback:** hapus pemanggilan method baru di `KasBankSourceMovement`,
  `TutupKasirSourceDefinition`, `RekonDailyCalculator`, dan
  `DailyCashFlowService`. Tidak ada data yang perlu dikembalikan.
- **Tidak ada perubahan pada ledger/jurnal**, jadi rollback tidak perlu
  membersihkan posting.

## 11. Dokumentasi

Setelah disetujui dan diimplementasikan:

- `docs/prd/modul/PRD_MODUL_05_Keuangan_KasBank_Aset.md`
  - bagian 4.2: jelaskan bahwa `kas_masuk` adalah sumber movement, bukan hanya
    baris ledger; `jenis` adalah posisi COA, bukan arah kas; form dibatasi
    (tanpa `posisi_drk`, `jenis` selalu `kredit`, COA disaring);
  - business rules: tambahkan aturan inflow-only, `idbank` tunai/transfer,
    pengecualian `kas awal`, cakupan laporan, dan daftar COA yang ditolak.
- `docs/prd/PRD_MASTER.md` hanya jika ringkasan lintas modul berubah.
- Dokumen desain ini.

## 12. Keputusan yang sudah dikonfirmasi

### 12.1 Arah kas — **disetujui: inflow-only (A)**

Semua baris non-`kas awal` menambah `jumlah`; `jenis` diabaikan untuk saldo.
Pilihan B/C ditolak karena bertentangan dengan bukti §5.5 dan membuat halaman
Kas Masuk bisa mencatat pengeluaran.

### 12.2 KPI Cash Flow — **tetap sales-only**

`CashFlowCalculator::calculate()` dan `harianRange()` tidak diubah. Grafik
harian KPI memang tidak memuat Kas Masuk; itu disengaja agar grafik dan skor
KPI memakai definisi yang sama.

### 12.3 `kas awal` — **hanya penanda itu**

Pengecualian memakai `LOWER(TRIM(deskripsi)) = 'kas awal'`. Data nyata hanya
memakai penanda ini. Bila nanti muncul penanda opening lain (mis.
`saldo awal`), itu perubahan terpisah; jangan menebak sekarang.

### 12.4 Laporan mentah lain — **cukup dicatat**

`LabaRugi`, `DashboardKeuangan`, dan `DashboardLabaRugi` tetap menghitung
seluruh `kas_masuk` (termasuk `kas awal`) sebagai pendapatan. Ini keterbatasan
yang didokumentasikan di §7.6, bukan bagian dari issue ini. Perbaikan minimal
(mengecualikan `kas awal`) dapat dibuat sebagai issue terpisah bila diperlukan.

### 12.5 Rekening tanpa `bank_idbank` — **ditolak di write path**

`BankRekeningValidator::validate()` sudah menolak `idbank` yang tidak
terpetakan ke `akun_kas_bank` (`:97-103`). Baris legacy yang tidak cocok tidak
menambah saldo rekening mana pun dan tetap terlihat di halaman Kas Masuk.
Tidak ada perubahan tambahan.

### 12.6 Form Kas Masuk — **dibatasi di issue ini**

`posisi_drk` dihapus, `jenis` dipaksa `kredit`, dan COA beban/HPP/akumulasi
penyusutan/kas-bank disaring di UI dan server. Detail di §7.7 dan test di §9.4.

### 12.7 Sisa asumsi yang perlu diketahui user

- **Blacklist COA fail-open.** Kategori `jenis_akun` baru yang tidak cocok
  dengan daftar tolak otomatis boleh dipilih. Ini disengaja agar master akun
  tidak perlu diubah setiap ada kategori baru; risiko kategori salah ditutup
  oleh lima pola tolak di §7.7.
- **Kolom "Jenis" di tabel daftar tetap ada.** Baris baru selalu `kredit`;
  kolom itu dipertahankan untuk melihat nilai historis. Bila user ingin kolom
  itu dihapus juga, itu perubahan UI terpisah.
- **Baris legacy ber-COA beban** tidak diperbaiki otomatis. Baris itu harus
  dihapus bersama data dummy sebelum deploy.

