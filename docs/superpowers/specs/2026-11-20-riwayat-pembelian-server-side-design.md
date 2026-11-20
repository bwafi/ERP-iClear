# Riwayat Pembelian: server-side, scope unit, dan perbaikan detail

Status: **disetujui (2026-11-20)** — user menyetujui arahan di percakapan
("supaya tidak lelet kalo datanya banyak, lanjut"). Issue: #228.

## Intent

Halaman `/riwayat_pembelian` dipakai untuk menelusuri nota pembelian dan
rincian barangnya. Saat ini halaman menarik **seluruh** `detail_pembelian`
ke browser, tidak punya scope unit, dan modal detailnya kosong karena filter
DataTables global menelan semua baris tabel detail.

## Goals

- Modal detail menampilkan seluruh barang milik nota yang diklik.
- Halaman tetap responsif saat data besar: pagination, pencarian, filter
  tanggal, dan filter unit diproses di server.
- Default urutan tanggal terbaru.
- Role lintas unit (Finance 0, Admin root 1, Direktur 2, Manager 34) dapat
  memilih **Semua Unit**; role lain terkunci ke unit dalam scope-nya.
- Export mengikuti scope yang sama.

## Non-goals

- Tidak menambah rincian pembayaran (tunai/transfer/rekening) di modal.
- Tidak mengubah schema, jurnal, stok, HPP, PPN, atau alur input pembelian.
- Tidak membersihkan 21 baris `detail_pembelian` yatim (butuh DML terpisah).

## Constraints

- Pola yang sudah ada: `Riwayat_Penjualan::ajax` +
  `ModelPenjualan::getPenjualanServerSide` + view `riwayat/penjualan.php`.
- Scope wajib memakai `App\Services\Finance\FinanceScopeService`
  (`resolveAllowedUnits()`, `allowedUnitIds()`, `scopeInfo()['isLintas']`).
- `no_batch` **tidak unik**; identitas nota adalah `pembelian.idpembelian`.
- Unit dari request tidak boleh dipercaya; validasi terhadap scope di server.
- Tidak ada perubahan route yang sudah dipakai (`export` tetap).

## Approaches

1. **Perbaiki filter saja (client-side).** Cepat, tapi tetap menarik semua
   baris dan tidak menyelesaikan kelambatan maupun scope. Ditolak.
2. **Server-side penuh, mengikuti pola Riwayat Penjualan.** Satu baris per
   nota, detail via AJAX, scope di server. Dipilih: menyelesaikan akar
   masalah kelambatan, modal kosong, dan kebocoran unit sekaligus, dengan pola
   yang sudah terbukti di repo.
3. **Server-side tanpa scope role.** Lebih kecil, tetapi role unit tetap bisa
   melihat unit lain. Ditolak karena melanggar `unit-scope.md`.

## Keputusan desain

- Daftar: satu baris per `pembelian`, kolom No. Batch
  (`COALESCE(no_nota_supplier, no_batch detail pertama)`), Tanggal
  (`tanggal_masuk`), Barang (barang pertama + jumlah item), Unit, Sumber,
  Total (`total_transaksi`), Aksi.
- Urutan default `tanggal_masuk DESC, idpembelian DESC`.
- Filter unit memakai `idunit`, bukan nama, agar tidak ambigu.
- `recordsTotal` = jumlah nota dalam scope; `recordsFiltered` = setelah
  filter tanggal/unit/cari.
- Detail endpoint `GET riwayat_pembelian/detail/{idpembelian}` memvalidasi
  unit nota terhadap scope, lalu mengembalikan header + item.
- Role non-lintas: pilihan unit hanya unit dalam scope (select dinonaktifkan
  bila satu unit); server tetap memaksa `unit_idunit IN (scope)`.
- Export: `unit` divalidasi terhadap scope; bila kosong, export seluruh unit
  dalam scope.
- Default tanggal di view: kosong (semua tanggal), karena pagination sudah
  di server dan data lama tidak boleh tersembunyi tanpa disadari.
- Tidak ada `$.fn.dataTable.ext.search` global di halaman ini.

## Yang diserahkan ke pelaksana

- Nama method model, bentuk respons JSON, dan detail markup.
- Susunan test dan data seed.
