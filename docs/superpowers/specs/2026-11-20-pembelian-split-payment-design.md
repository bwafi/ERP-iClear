# Design — Split Payment untuk Pembelian Lunas

Tanggal: 2026-11-20
Status: menunggu persetujuan
Pendekatan terpilih: **A — service split khusus pembelian + tabel yang sudah ada**

## 1. Intent dan alasan

Pembelian **Lunas** saat ini hanya menerima satu metode: tunai penuh atau tepat
satu rekening bank. Di lapangan supplier kadang dibayar sebagian tunai dan
sebagian transfer. Fitur ini membuat Lunas bisa dibayar dengan kombinasi
tunai + transfer (dan lebih dari satu rekening), sementara **Lunas tetap wajib
pas**: total semua komponen harus sama dengan total pembelian.

## 2. Goals

- `metode_bayar` menerima `tunai`, `transfer`, dan `tunai_transfer`.
- Tunai dan transfer boleh digabung; transfer boleh memakai lebih dari satu
  rekening.
- Server menghitung ulang total pembayaran dari komponen dan menolak bila
  **kurang atau lebih** dari total pembelian.
- Setiap komponen tercatat benar di `pembayaran_bank`, `kas_keluar`, dan jurnal.
- Tempo tetap tanpa pembayaran di muka.
- UI tetap satu lembar dan konsisten dengan halaman Penjualan.

## 3. Non-goals

- Tidak mengubah alur Tempo, DP, atau pembayaran hutang setelah transaksi.
- Tidak mengubah alur Penjualan (pendekatan B ditolak).
- Tidak menambah tabel atau kolom baru (pendekatan C ditolak).
- Tidak mengubah perhitungan harga, diskon, PPN, stok, HPP, atau harga jual.
- Tidak menulis ulang data historis.

## 4. Anti-goals

- Header Lunas dengan `sisa > 0` atau `status != 'Lunas'`.
- Kas/bank tercatat lebih besar/kecil dari yang benar-benar dibayar.
- Jurnal per grup referensi tidak seimbang.
- Pembelian tersimpan sebagian (header/detail/kas/jurnal) saat salah satu
  komponen gagal.

## 5. Constraints

- `pembelian.bank_idbank` hanya satu kolom `varchar(11)`; tidak bisa menampung
  banyak rekening.
- `pembayaran_bank` sudah mendukung banyak baris per `kode_pembayaran` dan
  `id_referensi`.
- `kas_keluar` menyimpan satu `idbank` per baris; kas tunai = `idbank NULL`.
- Jurnal pembelian memakai template dua sisi yang seimbang per grup.
- Nominal uang integer rupiah.
- Route `insert_pembelian` tetap `auth` + `csrf`.

## 6. Approaches yang dipertimbangkan

### A. Service split khusus pembelian + tabel yang sudah ada (dipilih)

`PembelianPaymentSplit` baru menormalkan `metode_bayar`, tunai, dan baris bank.
Controller menulis banyak baris `pembayaran_bank`, banyak baris `kas_keluar`,
dan satu jurnal pembayaran per komponen. Tanpa migrasi schema.

- Kelebihan: risiko paling kecil, tidak menyentuh Penjualan, memakai struktur
  yang sudah ada.
- Kekurangan: logika parsing split mirip `PenjualanPaymentSplit`.

### B. Satukan service split Penjualan dan Pembelian

Generalisasi `PenjualanPaymentSplit` menjadi service bersama.

- Kelebihan: satu sumber aturan split.
- Kekurangan: menyentuh alur Penjualan dan test-nya; risiko regresi lebih besar
  untuk kebutuhan ini. Ditolak.

### C. Tabel baru `pembelian_pembayaran`

Menyimpan setiap baris split secara eksplisit.

- Kelebihan: paling mudah diaudit per baris.
- Kekurangan: migrasi baru, menduplikasi `pembayaran_bank`/`kas_keluar`, belum
  ada konsumen. Ditolak.

## 7. Keputusan desain

### 7.1 Normalisasi pembayaran

Service baru `App\Services\Pembelian\PembelianPaymentSplit`:

- Memakai `PembelianCalculator::parseRupiah()` untuk membaca nominal.
- Input: `metode_bayar`, `bayar`, `bank[]`.
- Output: `cash`, `banks[]` (`id`, `jumlah`), `total`, `errors[]`.

Aturan per metode:

| `metode_bayar` | Tunai | Bank |
|---|---|---|
| `tunai` | dipakai | diabaikan |
| `transfer` | diabaikan | dipakai |
| `tunai_transfer` | dipakai | dipakai |
| kosong/lain | ditolak untuk Lunas | — |

- Tunai tidak boleh negatif.
- Baris bank dengan nominal `0` dilewati; nominal negatif ditolak.
- Setiap baris bank wajib punya rekening dan lolos
  `BankRekeningValidator::validateUntukUnit($id, $unit, $role)`.
- Minimal satu komponen > 0.
- **Total komponen harus tepat sama dengan total pembelian.** Kurang atau lebih
  ditolak sebelum transaksi dibuka.

### 7.2 Data yang disimpan

- `pembelian.bayar_tunai` = total tunai.
- `pembelian.bayar_bank` = total semua baris bank.
- `pembelian.total_bayar` = `bayar_tunai + bayar_bank` = `total_transaksi`.
- `pembelian.sisa` = 0, `status` = `Lunas`.
- `pembelian.bank_idbank`:
  - tepat satu baris bank → id rekening itu;
  - nol atau lebih dari satu baris bank → `NULL` (detail ada di
    `pembayaran_bank`).
- `pembayaran_bank`: satu baris per rekening, semua memakai `kode_pembayaran`
  yang sama (unik per transaksi), `tabel_referensi = 'pembelian'`,
  `id_referensi = idpembelian`, `jumlah` sesuai komponen.
- `kas_keluar`: satu baris per komponen > 0.
  - tunai: `idbank = NULL`;
  - bank: `idbank = id rekening`;
  - semua: kategori HPP, `no_akun = 1030100000`, `jenis = 'debet'`,
    `idunit` unit pembelian, tanggal dan penerima sama seperti sekarang.

### 7.3 Jurnal

- Jurnal persediaan/PPN tidak berubah: mendebit persediaan/PPN dan mengkredit
  `2010100000 Utang Usaha`.
- Jurnal pembayaran dibuat per komponen:
  - tunai → `pembelian_lunas_tunai`;
  - bank → `pembelian_lunas_bank_{id}`, fallback `pembelian_lunas_bank`.
- Semua jurnal pembayaran memakai referensi `('pembayaran_bank', kode)`.
- Setiap template divalidasi ada dan seimbang sebelum transaksi; setelah insert,
  grup `pembelian` dan `pembayaran_bank` diverifikasi seimbang.
- Tempo tetap hanya menulis jurnal persediaan/PPN dan menyisakan utang.

### 7.4 UI

Select `metode_bayar` mendapat opsi **Tunai + Transfer**. Sketsa:

```
Metode Pembayaran : [ Tunai | Transfer | Tunai + Transfer ]
Bayar Tunai       : Rp 300.000          (bisa diketik)
Pembayaran via Bank
  [BCA Unit Satu]  Rp 700.000           (+ Tambah Bank)
  [Mandiri Unit]   Rp 200.000           (x)
Status            : Pas Rp 1.200.000
```

- Tunai bisa diketik saat `tunai`/`tunai_transfer`; `0` saat `transfer`.
- Baris bank bisa ditambah/dihapus dan nominalnya diketik.
- Status menampilkan **Pas / Kurang / Lebih**.
- Tombol simpan diblokir bila total komponen belum tepat sama dengan total.
- Tombol **Isi sisa** mengisi tunai = total − total bank.
- Tempo: zona pembayaran disembunyikan seperti sekarang.

> UI final mengikuti `docs/superpowers/specs/2026-11-20-redesign-lunas-pembelian-design.md`
> (payment console: tab metode, panel split, ringkasan alokasi). Sketsa di atas
> hanya kontrak perilaku, bukan markup final.

### 7.5 Validasi server

- `metode_bayar` wajib untuk Lunas.
- Rekening divalidasi ulang di server, bukan hanya dari dropdown.
- Total komponen dihitung server; nilai POST tidak dipercaya.
- Kurang/lebih, nominal negatif, rekening di luar scope, dan tidak ada komponen
  semuanya ditolak dengan flashdata `gagal`, tanpa menyisakan header/detail.

## 8. Dampak file

- `app/Services/Pembelian/PembelianPaymentSplit.php` (baru)
- `app/Controllers/Pembelian.php`
- `app/Views/transaksi/pembelian.php`
- `tests/unit/PembelianPaymentSplitTest.php` (baru)
- `tests/PembelianHppUnitTest.php`
- `tests/PembelianPageTest.php`
- `docs/prd/modul/PRD_MODUL_03_Pembelian_Persediaan.md`
- `docs/prd/modul/PRD_MODUL_05_Keuangan_KasBank_Aset.md` (bila aturan kas/jurnal
  perlu diperjelas)

Tidak ada migrasi schema.

## 9. Test yang direncanakan

- Unit: normalisasi tunai, transfer, tunai+transfer, nominal negatif, rekening
  kosong/di luar scope, total kurang/lebih.
- Feature:
  - split tunai + satu bank tersimpan benar;
  - split tunai + dua bank: `bayar_tunai`, `bayar_bank`, `pembayaran_bank`,
    `kas_keluar`, jurnal seimbang;
  - `bank_idbank` = id saat satu bank, `NULL` saat banyak bank;
  - kurang dan lebih ditolak tanpa menyisakan data;
  - Tempo tidak berubah;
  - kode pembayaran tetap unik.
- Page: opsi `tunai_transfer` ada di `metode_bayar`, input tunai/bank bisa
  diedit, status pas/kurang/lebih, guard submit.

## 10. Yang diserahkan ke builder

- Penamaan kelas CSS dan teks bantuan.
- Susunan fixture test.
- Detail kecil formatting rupiah di UI.
