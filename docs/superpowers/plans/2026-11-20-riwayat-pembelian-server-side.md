# Plan: Riwayat Pembelian server-side (#228)

Design: `docs/superpowers/specs/2026-11-20-riwayat-pembelian-server-side-design.md`

## Task 1 — Query server-side di model

**File:** `app/Models/ModelPembelian.php`, `app/Models/ModelDetailPembelian.php`

- `getRiwayatServerSide($start, $length, $search, $orderColumn, $orderDir, $startDate, $endDate, $unitId, array $allowedUnitIds)`:
  - satu baris per `pembelian`, join `unit`, left join `suplier`;
  - kolom: `idpembelian`, `no_batch` (COALESCE), `tanggal_masuk`, `NAMA_UNIT`,
    `suplier_id_suplier`, `nama_suplier`, `total_transaksi`, `status`, `sisa`,
    `jumlah_item`, `nama_barang` (barang pertama);
  - filter scope `unit_idunit IN (...)`, unit terpilih, rentang tanggal, cari
    (batch/unit/barang/suplier) — semua lewat parameter binding;
  - urutan default `tanggal_masuk DESC, idpembelian DESC`, tie-break id;
  - `LIMIT/OFFSET` dari request.
- `countRiwayat(array $allowedUnitIds)` untuk `recordsTotal`.
- `ModelDetailPembelian::getByPembelianId($id)` untuk isi modal.

**Test:** `tests/RiwayatPembelianServerSideTest.php`
- urutan default terbaru;
- pagination;
- cari nama barang mengembalikan satu nota dengan `jumlah_item` benar;
- filter unit dan filter tanggal;
- scope role unit menang atas `unitFilter` dari request;
- detail hanya milik `idpembelian` tersebut walau `no_batch` duplikat.

## Task 2 — Controller + route

**File:** `app/Controllers/Riwayat_pembelian.php`, `app/Config/Routes.php`

- `index()`: kirim `unit` (scope), `boleh_semua_unit` (`isLintas`).
- `ajax()`: baca draw/start/length/search/order/tanggal/unit; validasi unit
  terhadap `allowedUnitIds()`; balas JSON DataTables.
- `detail($id)`: 404 bila nota tidak ada atau unit di luar scope; balas
  header + item.
- `export()`: validasi unit terhadap scope, teruskan `unitIds` ke model.
- Route: `POST riwayat_pembelian/ajax`,
  `GET riwayat_pembelian/detail/(:num)`.

**Test (feature):** sesi role unit meminta unit lain → data unit sendiri;
detail nota unit lain → 404.

## Task 3 — View

**File:** `app/Views/riwayat/pembelian.php` (tulis ulang)

- Filter: tanggal awal/akhir (default kosong), unit (Semua Unit hanya untuk
  role lintas), cari, baris, reset, export.
- Tabel `#pembelianTable` server-side; kolom sesuai desain; tombol Detail.
- Modal detail diisi via AJAX; ada state loading/error/kosong.
- Hapus seluruh filter `$.fn.dataTable.ext.search` dan modal per baris.

**Test:** `tests/RiwayatPembelianPageTest.php` — kontrak markup/JS:
`serverSide: true`, URL ajax/detail, tidak ada `ext.search`, opsi
"Semua Unit" hanya saat `boleh_semua_unit`, select terkunci bila satu unit.

## Verifikasi

- `php -l` file yang diubah, `git diff --check`.
- `node --check` pada blok `<script>` hasil render.
- Suite terarah lewat container `erp-app` (DB `erp_pembelian_test`).
- Cek manual HTTP: `/riwayat_pembelian` 200, ajax mengembalikan JSON,
  detail nota sendiri 200, nota unit lain 404.
