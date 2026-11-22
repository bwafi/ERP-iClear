# Redesign Kas Masuk dengan DataTables Server-side — Design

**Tanggal:** 2026-11-22
**Status:** diimplementasikan (menunggu verifikasi manual + commit)
**Issue:** [#235](https://github.com/bwafi/ERP-iClear/issues/235)

## 1. Intent

Halaman `/kas_masuk` menyimpan uang masuk, tetapi tampilannya belum layak dipakai
harian: daftar 10 kolom tanpa pencarian dan total, filter hanya menyaring baris
yang sudah dirender, modal Edit/Hapus tidak pernah bisa dibuka, dan role
terbatas dapat melihat transaksi unit lain.

Tujuan: menjadikan `/kas_masuk` halaman ledger-first yang setara `/kas_keluar`,
dengan daftar **DataTables server-side** dan filter yang benar-benar bekerja di
server, tanpa mengubah kontrak penyimpanan yang sudah ada.

## 2. Goals

- Daftar kas masuk memakai DataTables server-side: `draw`, `start`, `length`,
  `search`, dan `order` diproses server.
- Filter server-side: rentang tanggal, unit (sesuai scope role), dan kategori.
- Strip ringkasan dari **seluruh hasil filter**: total masuk, jumlah transaksi,
  total tunai, total transfer.
- Tombol Edit dan Hapus di tabel benar-benar berfungsi, dengan modal yang sudah
  ada dan integritas jurnal yang dijaga.
- Tampilan scoped `.km-*` yang konsisten dengan `DESIGN.md` dan `/kas_keluar`.
- Tabel menjadi kartu berlabel `data-label` di bawah `768px`.
- Export mengikuti tanggal dan unit yang sedang aktif.

## 3. Non-goals

- Tidak mengubah schema database.
- Tidak mengubah route POST, nama field, validasi COA (`bolehUntukKasMasuk`),
  validasi rekening (`BankRekeningValidator`), transaksi, posting ledger, atau
  aturan inflow-only dari #234.
- Tidak menambah validasi scope unit di **write path**. Scope di issue ini hanya
  untuk baca: daftar, ringkasan, dan endpoint datatable.
- Tidak mengelompokkan beberapa baris `kas_masuk` menjadi satu dokumen batch.
  Setiap baris tetap satu transaksi, seperti bentuk yang disimpan backend.
- Tidak mempertahankan isian modal input setelah simpan gagal. Pesan gagal tetap
  ditampilkan; repopulasi draf multi-baris adalah pekerjaan terpisah.
- Tidak mengubah pemilih unit operasional. Input tetap memakai `ModelUnit::getUnit()`
  seperti `/kas_keluar`; pembersihan HO/non-operasional adalah issue terpisah.
- Tidak mengubah jurnal historis yang sudah terlanjur yatim.

## 4. Approaches yang dipertimbangkan

1. **Visual saja.** Aman dan cepat, tetapi tombol Edit/Hapus tetap mati, filter
   tetap palsu, dan role terbatas tetap bisa membaca unit lain. Tidak memenuhi
   kebutuhan.
2. **Ledger-first setara `/kas_keluar` + DataTables server-side (dipilih).**
   Menyelesaikan masalah nyata tanpa mengubah jalur tulis. Kontrak route/field
   dipertahankan; jurnal disinkronkan hanya untuk baris `kas_masuk`.
3. **Ledger-first + perbaikan tulis penuh.** Termasuk scope unit di write path
   dan pengelompokan batch. Risiko keuangan paling besar dan tidak diminta.

**Keputusan:** opsi 2, ditambah permintaan user agar daftar memakai DataTables
server-side.

## 5. Kontrak data dan endpoint

### 5.1 Route baru

```php
$routes->get('/kas_masuk', 'Kas_Masuk::index', ['filter' => 'auth']);
$routes->get('kas_masuk/datatables', 'Kas_Masuk::datatable', ['filter' => 'auth']);
```

`GET /kas_masuk` sebelumnya tanpa filter. Menambahkan `auth` menyamakannya
dengan `/kas_keluar` dan mencegah endpoint datatable menjadi satu-satunya pintu
berautentikasi ke data yang sama.

### 5.2 Parameter datatable (GET)

| Parameter | Arti |
|---|---|
| `draw` | DataTables counter, dikembalikan apa adanya. |
| `start`, `length` | Offset dan jumlah baris. `length < 1` diperlakukan sebagai 25. |
| `search[value]` | Pencarian teks. |
| `order[0][column]`, `order[0][dir]` | Urutan; dipetakan ke kolom aman. Default `tanggal DESC`. |
| `tanggal_awal`, `tanggal_akhir` | Rentang `kas_masuk.tanggal`, inklusif. |
| `unit_id` | Filter unit; divalidasi terhadap scope role. |
| `kategori_id` | Filter `kategori_idkategori`; `0`/kosong = semua. |

### 5.3 Payload respons

```json
{
  "draw": 1,
  "recordsTotal": 120,
  "recordsFiltered": 18,
  "sumTotal": 1250000,
  "sumTunai": 750000,
  "sumTransfer": 500000,
  "countFiltered": 18,
  "data": [
    {
      "id": 637,
      "tanggal": "2026-11-20",
      "unit": "Probolinggo",
      "no_akun": "4000000000",
      "nama_akun": "Pendapatan Lain",
      "kategori": "Pendapatan",
      "kategori_id": 3,
      "deskripsi": "Setoran modal",
      "idbank": "BNI-001",
      "nama_bank": "BNI",
      "norek": "123456789",
      "penerima": "PT Contoh",
      "jenis": "kredit",
      "jumlah": 500000,
      "aksi": null
    }
  ]
}
```

- `idbank` selalu string; jangan `(int)` karena nilainya bisa `BNI-001`.
- `recordsTotal` adalah jumlah baris **yang boleh dilihat user**, bukan seluruh
  perusahaan, supaya role terbatas tidak membaca hitungan unit lain.
- `sumTunai` memakai `idbank IS NULL OR idbank = ''`; `sumTransfer` sebaliknya.
- Seluruh nilai uang dikirim sebagai angka; format Rupiah dilakukan di view.

### 5.4 Pencarian

Satu kotak pencarian mencocokkan substring pada:

- `kas_masuk.deskripsi`
- `kas_masuk.no_akun`, `no_akun.nama_akun` (left join)
- `kategori_kas.kategori`
- `bank.nama_bank`, `bank.norek` (left join)
- `unit.NAMA_UNIT` (left join)
- `kas_masuk.penerima`
- `kas_masuk.idkas_masuk` (angka)

Pencarian tidak membedakan mode ID/teks. Angka tetap dicocokkan sebagai
substring ke semua kolom di atas; hasilnya deterministik dan tidak perlu UI
tambahan.

### 5.5 Scope unit

Mengikuti pola `/kas_keluar`:

- `FinanceScopeService::inputRoles()` (0, 1, 2, 34) = lintas unit.
- Role lain memakai `resolveAllowedUnits()`; SPV memakai `spv_units`.
- `unit_id` dari request hanya dipercaya bila berada di `allowedUnitIds()`.
  Nilai di luar scope jatuh ke unit pertama yang diizinkan.
- Unit filter yang dirender hanya unit dalam scope. Role dengan satu unit
  melihat unit terkunci (select `disabled`).
- `recordsTotal` untuk role terbatas memakai hitungan yang sudah difilter unit,
  bukan `countAll`.

## 6. Perubahan per file

### 6.1 `app/Models/ModelKasMasuk.php`

Method baru (pola `ModelKasKeluar`, tanpa union):

- `baseKasMasukQuery()` — `kas_masuk` left join `kategori_kas`, `bank`, `unit`,
  `no_akun`.
- `applyKasMasukFilter($builder, $search, $startDate, $endDate, $unitId, $kategoriId)`
  — mengembalikan `[whereString, binds]` atau memakai builder; pencarian memakai
  `LIKE` dengan escape.
- `countAllKasMasuk(): int`
- `countKasMasukFiltered(...): int`
- `sumKasMasukFiltered(...): array` — `total`, `tunai`, `transfer`.
- `getKasMasukDataTable($length, $start, $search, $orderCol, $orderDir, ...): array`
  — whitelist kolom urutan, default `kas_masuk.tanggal DESC, idkas_masuk DESC`.

`getKasMasuk()` dan `getKasMasukFiltered()` tetap ada karena dipakai export dan
pemanggil lain. `getKasMasuk()` tidak lagi dipanggil `index()`.

### 6.2 `app/Controllers/Kas_Masuk.php`

- Tambah properti `FinanceScopeService` dan helper:
  - `bebasPilihUnit()`
  - `unitFilterList()`
  - `unitFilterTerpilih($requested)`
- `index()`:
  - berhenti memuat `getKasMasuk()`;
  - kirim `unit_filter`, `can_filter_unit`, `show_all_units`,
    `default_filter_unit`, `kategori_kas`, `bank_per_unit`, dan `can_act`;
  - `can_act = (int) ($akun->ID_JABATAN ?? 0) === 1`, sama seperti
    `/kas_keluar`; server **tidak** menambah guard baru pada route update/delete
    (authz lama tidak berubah).
- `datatable()` baru: baca parameter, resolve scope, ambil count/sum/rows,
  bentuk payload JSON. Tidak ada HTML di respons.
- `update_kas_masuk()`:
  - normalisasi `jumlah` menjadi integer rupiah;
  - di dalam transaksi yang sama, setelah update sumber, sinkronkan jurnal
    `tabel_referensi = 'kas_masuk'` dan `id_referensi = $id`:
    `tanggal`, `debet = 0`, `kredit = jumlah`, `keterangan = deskripsi`;
  - `jenis` tetap `kredit`.
- `delete_kas_masuk()`:
  - di dalam transaksi yang sama, panggil
    `JurnalModel::hapusByReferensi('kas_masuk', (int) $id)` sebelum/sesudah
    `hapusPosting`, lalu hapus sumber;
  - kegagalan dibungkus try/catch dan di-rollback seperti pola update.

Jurnal modul lain tidak disentuh karena filter `tabel_referensi` eksplisit.

### 6.3 `app/Views/jurnal/_kas_masuk_theme.php` (baru)

Semua selector di bawah `.km-page` / `.km-modal`, token `--km-*` diturunkan dari
tema aplikasi. Tanpa palet, font, atau dependency baru. Isi utama:

- masthead tipis: breadcrumb, judul, deskripsi satu baris, chip scope;
- bilah perintah: pencarian, tanggal awal/akhir, unit, kategori, reset, export;
- strip ringkasan 4 sel berhairline: Total masuk, Transaksi, Tunai, Transfer;
- `.km-table`: header uppercase, angka `tabular-nums`, aksi ghost;
- `.km-empty`: ikon, judul, teks, tombol Input/Bersihkan filter;
- `.km-pager`: tombol pager 32px windowed seperti `/kas_keluar`;
- modal input/edit: `max-width: 880px`, footer sticky;
- di bawah `768px`: baris tabel menjadi kartu dengan `data-label`, filter
  menumpuk, modal full-width;
- `prefers-reduced-motion` mematikan transisi.

### 6.4 `app/Views/jurnal/kas_masuk.php`

Struktur baru:

```
masthead
├─ breadcrumb + judul + scope readout
└─ tombol Input Kas Masuk
deck
├─ cmdbar: search · tanggal · unit · kategori · reset · export
├─ summary: Total masuk · Transaksi · Tunai · Transfer
├─ table#kmTable
├─ empty state
└─ pager + info
modal input (form insert_kas_masuk)
├─ tanggal · unit · deskripsi
├─ daftar baris akun (template + tombol Tambah Akun)
└─ footer sticky: total draf · Batal · Simpan
modal Cari Buku (daftar akun getAkunMasuk)
modal edit (form update_kas_masuk)
modal hapus (form delete_kas_masuk, ringkasan baris)
```

Yang dipertahankan:

- `id` dan `name` field: `tanggal`, `deskripsi`, `unit_idunit`,
  `akun[i][no_akun]`, `akun[i][kategori_idkategori]`,
  `akun[i][jenis_transaksi]`, `akun[i][no_rekening]`, `akun[i][penerima]`,
  `akun[i][jumlah]`; edit: `idkas_masuk`, `tanggal`, `kategori_idkategori`,
  `deskripsi`, `jumlah`, `penerima`, `idunit`; hapus: `idkas_masuk`.
- `#table_kasmasuk` sebagai id daftar akun Cari Buku.
- Route form: `insert_kas_masuk`, `update_kas_masuk`, `delete_kas_masuk`,
  `export_kas_masuk`.

Yang berubah:

- DataTables `#kmTable` memakai `serverSide: true` dan endpoint
  `kas_masuk/datatables`; pager bawaan disembunyikan (`dom: 'rt'`) dan pager
  dirender sendiri.
- Baris akun dibuat dari `<template>` dan `window.KM_BANKS` /
  `window.KM_KATEGORI` yang di-`json_encode` dengan flag HEX; tidak ada PHP di
  dalam string JS.
- Rekening per unit: mengganti unit mengganti opsi rekening, sehingga rekening
  unit lain tidak pernah ditawarkan.
- Kolom "Jenis" historis diganti label **Tunai/Bank** yang diturunkan dari
  `idbank` (lebih bermakna daripada `debet/kredit` yang kini selalu `kredit`).
- Tombol Edit/Hapus dirender hanya bila `can_act`.

### 6.5 `app/Controllers/Kas_Masuk.php::export()`

Tidak berubah kontrak. Form export mengirim `tanggal_awal`, `tanggal_akhir`,
dan `nama_unit` (nama unit terpilih; kosong = semua unit dalam scope). Endpoint
tetap memakai `getKasMasukFiltered()`. Pencarian dan kategori tidak diterapkan
ke export pada issue ini; disebutkan di UI sebagai "Export mengikuti tanggal dan
unit".

## 7. Alur yang dijaga

### 7.1 Insert

Tidak berubah: validasi seluruh batch COA sebelum transaksi, validasi rekening
per unit, simpan `kas_masuk`, posting ledger idempotent, tulis jurnal, satu
transaksi. Redesign hanya mengubah cara baris dikumpulkan di UI.

### 7.2 Update

```
transStart
  update kas_masuk (tanggal, kategori, deskripsi, jumlah, jenis=kredit,
                    penerima, idbank, updated_on)
  sinkronkan jurnal kas_masuk (tanggal, debet=0, kredit=jumlah, keterangan)
  hapusPosting('kas_masuk', id)
  postingKasMasuk(id) + wajibBerhasil
transComplete
```

Bila salah satu gagal, seluruh perubahan dibatalkan, termasuk sinkronisasi
jurnal.

### 7.3 Delete

```
transStart
  hapus jurnal referensi kas_masuk
  hapusPosting('kas_masuk', id)
  delete kas_masuk
transComplete
```

Bila gagal, rollback. Tidak ada jurnal yatim baru.

## 8. Edge cases

| Kasus | Perilaku |
|---|---|
| Role terbatas mengirim `unit_id` unit lain | Jatuh ke unit pertama yang diizinkan; tidak membocorkan data. |
| Role hanya punya satu unit | Select unit disabled; nilai tetap dikirim/diabaikan server. |
| SPV punya beberapa unit | Memilih satu unit; default unit pertama. Tidak ada mode "semua unit" untuk SPV, sama seperti `/kas_keluar`. |
| `length` tidak valid | Dipaksa 25; `start` negatif dipaksa 0. |
| Kolom urutan tidak dikenal | Jatuh ke `tanggal DESC`. |
| `kategori_id` tidak ada | Hasil kosong; bukan error. |
| Rekening di luar unit | Tidak ditawarkan; server tetap menolak lewat validator yang ada. |
| `idbank` kosong string di data lama | Dihitung sebagai tunai. |
| Baris legacy tanpa `kategori_kas` | Left join; tetap tampil, kategori kosong. |
| Jurnal legacy tidak ada saat update | Update 0 baris; sumber tetap tersimpan, tidak membuat jurnal baru. |
| Edit/hapus oleh role selain 1 lewat POST langsung | Perilaku authz lama tidak berubah; tombol hanya disembunyikan. |
| Export saat filter kategori aktif | Export tetap tanggal + unit; kategori tidak ikut. |

## 9. Test plan

Test yang ada di `tests/KasBankControllerTest.php` harus tetap lulus. Tambahan:

1. **Model datatable**
   - filter tanggal, unit, kategori, dan pencarian menghasilkan `recordsFiltered`
     yang benar;
   - `sumTunai`/`sumTransfer` menjumlah seluruh hasil filter, bukan satu halaman;
   - urutan `jumlah` dan `tanggal` benar; kolom urutan liar jatuh ke default.
2. **Endpoint datatable**
   - respons JSON berisi `draw`, `recordsTotal`, `recordsFiltered`, `data`;
   - `recordsTotal` role terbatas tidak memuat unit lain;
   - `unit_id` di luar scope diabaikan;
   - route memakai filter `auth` (request tanpa sesi ditolak/redirect).
3. **Update**
   - mengubah jumlah ikut mengubah `debet=0`, `kredit`, `tanggal`, dan
     `keterangan` pada jurnal referensi;
   - jurnal modul lain tidak berubah;
   - posting gagal → sumber dan jurnal di-rollback.
4. **Delete**
   - baris `kas_masuk` dan jurnal referensinya hilang bersamaan;
   - jurnal modul lain tetap ada.
5. **View statis**
   - tidak ada lagi `jenis_transaksi` yang membawa `posisi_drk`;
   - tidak ada `getKasMasuk()` di `index()`;
   - form memuat field kontrak di §6.4.
6. **Regresi #234**
   - `ModelNoAkunKasMasukTest`, `KasBankControllerTest`, `KasBankTest`, dan test
     inflow-only tetap lulus.

Perintah:

```sh
vendor/bin/phpunit -c phpunit.xml tests/KasBankControllerTest.php
vendor/bin/phpunit -c phpunit.xml tests/KasBankTest.php
vendor/bin/phpunit -c phpunit.xml tests/unit/ModelNoAkunKasMasukTest.php
php -l app/Controllers/Kas_Masuk.php
php -l app/Models/ModelKasMasuk.php
php -l app/Views/jurnal/kas_masuk.php
php -l app/Views/jurnal/_kas_masuk_theme.php
```

Bila environment PHPUnit tidak mendukung (ekstensi `mysqli`/`intl`), catat error
aktual dan jangan klaim lulus.

## 10. Dokumentasi

- `docs/prd/modul/PRD_MODUL_05_Keuangan_KasBank_Aset.md`:
  - 4.2: daftar kas masuk server-side, filter/scope baca, ringkasan, dan aksi
    Edit/Hapus;
  - temuan #13 (`GET /kas_masuk` tanpa auth) ditandai selesai;
  - keterbatasan update/delete jurnal diperbarui: sekarang disinkronkan untuk
    referensi `kas_masuk`.
- `DESIGN.md`: bagian baru `Kas Masuk (`.km-*`)` mengikuti pola `Kas Keluar`.
- `PRD_MASTER.md` hanya jika ringkasan lintas modul berubah (kemungkinan tidak).

## 11. Risiko

- **Perubahan perilaku auth baca:** `GET /kas_masuk` kini butuh login. Ini
  disengaja dan setara halaman kas lain, tetapi perlu disebut di rilis.
- **Sinkronisasi jurnal:** update menulis ulang baris jurnal referensi. Baris
  legacy yang sudah tidak konsisten akan ikut “diperbaiki” saat diedit; tidak
  ada backfill massal.
- **Aksi hanya untuk role 1:** mengikuti `/kas_keluar`. Bila Finance (0) juga
  perlu mengedit, itu keputusan terpisah.
- **Export tidak memuat pencarian/kategori:** konsisten dengan kontrak lama,
  tetapi bisa mengejutkan; disebutkan di UI.
