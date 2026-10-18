# Kasbon mengurangi kas laci (Kas & Bank, arus kas, Tutup Kasir)

Tanggal: 2026-10-18

## 1. Intent & kenapa

Kasbon pegawai di modul Hutang Piutang saat ini **tidak menyentuh kas
operasional**. Saat kasbon dibuat, sistem hanya memposting jurnal
(`hp_kasbon_input` = Dr Piutang Pegawai / Cr Kas Besar) ke tabel `jurnal`.
Tutup Kasir, arus kas harian, dan saldo Kas & Bank **tidak membaca tabel
`jurnal`** — ketiganya membaca tabel sumber operasional. Akibatnya uang yang
benar-benar keluar dari laci untuk kasbon tidak pernah mengurangi saldo laci:
saldo sistem lebih besar dari uang fisik.

Tujuan: kasbon yang dicairkan **mengurangi saldo laci** sehingga konsisten di
ketiga modul itu.

Keputusan pengguna (dikonfirmasi):

- Kasbon mengurangi kas laci di **Kas & Bank**, **arus kas**, dan **Tutup Kasir**.
- **Tidak ada pelunasan** kasbon. Diselesaikan lewat potong gaji (payroll).
- Kasbon bisa dicairkan **tunai maupun transfer**.
- Kasbon **boleh muncul sebagai biaya** di Laba Rugi (tidak perlu dikecualikan).
- Baris kas otomatis **boleh diedit/dihapus** (akses terbatas admin root), tetapi
  **wajib sinkron satu arah dari sisi kas**: kalau baris kas diubah/dihapus,
  data kasbon ikut berubah/terhapus.

## 2. Goals / Non-goals / Anti-goals

**Goals**

- Pencairan kasbon (tunai) mengurangi saldo KAS unit di ketiga modul.
- Pencairan kasbon (transfer) mengurangi saldo rekening BANK terkait.
- Satu kasbon = satu baris kas; tidak ada dobel (laci maupun jurnal).
- Edit/hapus baris kas menjaga kasbon tetap konsisten.

**Non-goals**

- Tidak men-backfill kasbon lama.
- Tidak mengubah cara payroll memotong kasbon.
- Tidak mengubah definisi Tutup Kasir / arus kas / Kas & Bank.
- Tidak memindahkan akuntansi kasbon ke jalur `kas_keluar`.

**Anti-goals** (gagal walau "jalan")

- Kasbon terhitung dua kali di laci, atau jurnal dobel.
- Kasbon ikut terhitung sebagai bonus/lembur di slip gaji.
- Kasbon transfer tercatat ke rekening yang tidak berhak dipakai unit itu.
- Baris kas diubah/dihapus tetapi kasbon tetap, sehingga laci ↔ kasbon beda.

Catatan: "kasbon muncul sebagai biaya di Laba Rugi" **bukan** anti-goal lagi
(keputusan pengguna).

## 3. Temuan yang membentuk desain (terverifikasi di kode)

1. `kas_keluar` adalah sumber laci untuk ketiganya:
   - Tutup Kasir: `TutupKasirSourceDefinition::kasKeluarCash()` (`idbank IS NULL`)
     & `kasKeluarTransfer()` (`idbank IS NOT NULL`), **tanpa filter kategori**.
   - Arus kas harian: `DailyCashFlowService::fetchKasKeluar()`.
   - Kas & Bank: `KasBankSourceMovement::movementKas()` / `movementBank()`.
2. Baris `kas_keluar` operasional **tidak** perlu ditulis ke `transaksi_kas_bank`
   (hanya baris ber-`transfer_ref` yang dipromosikan).
3. Membuat `kas_keluar` lewat controller **ikut memposting jurnal**. Maka baris
   otomatis kasbon **tidak** boleh posting jurnal, supaya tidak dobel dengan
   `hp_kasbon_input`.
4. Slip gaji menghitung "bon" lewat `kategori_idkategori = 10` **dan**
   `deskripsi LIKE '%bon%'`. "Ka**sbon**" mengandung "bon" → kategori 10 haram.
5. Beberapa ringkasan beban memakai `kategori IN (1,2,3,4,5,11,18)`; Laba Rugi
   memakai **semua** `kas_keluar`. Kategori kasbon = kategori khusus.
6. Validasi rekening transfer: `BankRekeningValidator::validateUntukUnit()`.
7. Preseden kategori khusus: `ServiceDpService` mencari `kategori_kas` `REFUND`.
8. Preseden linkage: `service_dp.refund_idkas_keluar` menyimpan id kas_keluar
   yang dibuatkan, dan dihapus kembali saat refund dibatalkan.
9. `HutangPiutangService::createPosition()` sudah dalam transaksi.
10. Input kasbon saat ini tanpa field metode (selalu tunai).
11. `hutang_piutang` belum punya kolom metode/bank maupun id kas_keluar.

## 4. Desain (keputusan)

### 4.1 Mekanisme

Saat kasbon dicairkan (`createPosition`, `sumber_tipe = kasbon`), dalam
transaksi yang sama, insert **satu baris `kas_keluar`**:

| Kolom | Nilai |
|---|---|
| `tanggal` | tanggal kasbon |
| `idunit` | `unit_id` kasbon |
| `jumlah` | `total` kasbon |
| `idbank` | `NULL` (tunai) atau `idbank` rekening (transfer) |
| `kategori_idkategori` | kategori khusus **"Kasbon"** |
| `deskripsi` | `Kasbon <kode> - <nama pegawai>` |
| `penerima` | `ID_AKUN` pegawai (konvensi `/payroll`) |
| `no_akun` | `1020201000` (Piutang Pegawai), untuk tampilan saja |
| `jenis` | mengikuti konvensi baris kas keluar |
| `created_on` | sekarang |

Baris ini **tidak** memanggil `JurnalModel`.

### 4.2 Jurnal

`hp_kasbon_input` (tunai) tetap menjadi catatan akuntansi. Untuk **transfer**,
tambah template baru `hp_kasbon_input_bank` = Dr `1020201000` Piutang Pegawai /
Cr `1010102000` Kas di Bank (meniru `hp_kasbon_bayar_bank`). Service memilih
template berdasarkan metode. Tidak ada perubahan pada `hp_kasbon_input`.

### 4.3 Kategori kas "Kasbon"

Tambah satu baris `kategori_kas` (`kategori = 'Kasbon'`, `jenis_kas = 'kas_keluar'`).
Service mencarinya; bila tidak ada → error input (pola `REFUND`). Kasbon
muncul sebagai biaya di Laba Rugi (diterima).

### 4.4 Metode pencairan di form input kasbon

Tambah pilihan **Tunai / Transfer** (+ pilih rekening bank bila transfer) di
`_input_form.php`, muncul hanya untuk kategori kasbon. Default Tunai.
Transfer divalidasi `BankRekeningValidator::validateUntukUnit()`; gagal → tolak.

### 4.5 Potong gaji (tidak berubah — keputusan pengguna)

Tidak berubah sama sekali. Yang berlaku sekarang tetap:

- Pelunasan **manual**: Finance menyusun payroll lalu menekan "Sudah Dibayar";
  saat itulah `settleKasbonFromPayroll()` memotong kasbon. **Tidak** otomatis
  tanggal 1, dan **tidak** dibuat penjadwal/cron baru.
- Potongan **penuh**: seluruh sisa kasbon diambil sekali potong (bukan cicilan),
  meski hasilnya gaji bersih bisa negatif. Tidak ada batas potong baru.
- `jatuh_tempo` (tanggal 1 bulan depan) tetap hanya penanda, bukan pemicu.
- `settleKasbonFromPayroll()` tidak menyentuh kas. Kasbon tidak pernah menulis
  `kas_masuk`.

### 4.6 Tombol pelunasan manual

Aksi "Bayar" untuk baris kasbon disembunyikan di UI (tidak ada pelunasan).
Logika `bayar()` dibiarkan agar data lama tidak pecah.

### 4.7 Link & sinkronisasi kas_keluar ↔ kasbon

- Tambah kolom `kas_keluar_id`, `metode`, `bank_idbank` pada `hutang_piutang`.
- Saat membuat kasbon, id baris `kas_keluar` yang baru disimpan ke
  `hutang_piutang.kas_keluar_id` (dalam transaksi yang sama).
- `update_kas_keluar()`: bila baris terhubung ke kasbon, ikut memperbarui
  kasbon — `jumlah` → `total` (+ hitung ulang `sisa`/`status`),
  `idbank`/tanggal → `metode`/`bank_idbank`/`tanggal`.
- `delete_kas_keluar()`: bila terhubung, ikut menghapus kasbon
  (soft delete `deleted = 1`), termasuk menolak bila kasbon sudah punya
  pembayaran potong-gaji.
- Akses edit/hapus tetap seperti sekarang (admin root).

**Keputusan saya (mohon dicek):** pengubahan/penghapusan baris kas untuk kasbon
yang **sudah lunas dipotong gaji** ditolak dengan pesan jelas, karena fakta
kasnya sudah terkunci ke payroll. Kunci hanya dibuka lewat koreksi manual yang
disengaja. Kasbon yang belum lunas bebas diedit/dihapus.

## 5. Perubahan per file (perkiraan)

- `HutangPiutangService::createPosition()` — insert `kas_keluar` + simpan link.
- Migration baru — tambah kolom `kas_keluar_id`, `metode`, `bank_idbank`
  ke `hutang_piutang`; seed kategori "Kasbon".
- Migration — template jurnal `hp_kasbon_input_bank`.
- `HutangPiutang` controller `store()` — teruskan metode & bank.
- `Kas_Keluar::update_kas_keluar()` / `delete_kas_keluar()` — sinkron ke kasbon.
- `Views/hutangpiutang/_input_form.php` — field Tunai/Transfer + bank.
- `Views/hutangpiutang/detail.php` / `index.php` — sembunyikan Bayar untuk kasbon.
- Helper bersama untuk resolve kategori + validasi + sync.

## 6. Kasus tepi yang wajib ditangani

- Kategori "Kasbon" tidak ada → kasbon gagal dengan pesan jelas.
- Transfer tanpa rekening / rekening tidak berhak untuk unit → ditolak.
- Gagal insert `kas_keluar` → seluruh transaksi kasbon rollback.
- Unit tanpa akun KAS/BANK aktif → gagal dengan pesan (bukan silent).
- Idempotensi: satu kasbon hanya menghasilkan satu baris `kas_keluar`.
- Edit baris kas pada kasbon yang sudah lunas → ditolak (§4.7).
- Hapus baris kas pada kasbon yang sudah punya pembayaran potong-gaji → ditolak.
- Edit `jumlah` menurunkan `total` di bawah `total_dibayar` → ditolak
  (mencegah sisa negatif).

## 7. Diserahkan ke builder

- Nama/ID kategori "Kasbon" (seed) dan nilai kolom `jenis` baris kas keluar.
- Isi `penerima` (ID_AKUN vs nama) mengikuti konvensi `/payroll`.
- Bentuk widget metode (radio/select) dan penempatan di form.
- Nama & lokasi helper sync.
- Detail query pencarian kasbon dari `kas_keluar_id`.
- Test.

## 8. Verifikasi

- Buat kasbon tunai → `kas_keluar` +1, saldo KAS turun.
- Tutup Kasir hari itu: `pengeluarancash` naik sebesar kasbon.
- Arus kas harian: baris "Kasbon ..." muncul sebagai keluar.
- Jurnal: tepat satu entri (tunai) / template bank untuk transfer; tidak ada
  jurnal `kas_keluar` dari baris otomatis.
- Slip gaji: nilai "Bon" tidak berubah.
- Edit baris kas → kasbon ikut berubah; hapus → kasbon ikut terhapus.
- Edit/hapus pada kasbon yang sudah lunas → ditolak dengan pesan.
