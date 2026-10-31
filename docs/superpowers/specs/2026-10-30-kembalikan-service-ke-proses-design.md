# Kembalikan Service ke Proses — Design

Tanggal: 2026-10-30
Status: menunggu persetujuan

## 1. Intent dan alasan

Admin/kasir kadang menyimpan pembayaran service dengan nominal atau metode yang salah, lalu sudah menekan **Sudah Diambil**. Saat itu status service menjadi 4 dan jurnal pendapatan pengambilan sudah terposting. UI pembayaran hanya bisa menambah (cicilan) atau mengoreksi saat kelebihan bayar, sehingga pembayaran yang kurang/ salah metode tidak bisa ditimpa.

Solusi yang diminta: Admin Root bisa **mengembalikan service yang sudah diambil ke Proses Service**, sehingga pembayaran yang salah dihapus, jurnal pengambilan dibatalkan, dan admin dapat mengisi ulang pembayaran yang benar sebelum menandai diambil lagi.

## 2. Goals

- Admin Root (`ID_JABATAN = 1`) dapat mengembalikan service berstatus **4 (sudah diambil)** ke status **2 (proses)**.
- Pembayaran non-DP yang tercatat dihapus: baris `pembayaran_bank` (`service_baru`) dan kolom `service.bayar`, `bayar_tunai`, `bayar_bank`.
- Jurnal pengambilan (`jurnal` dengan `tabel_referensi = 'service'`) dihapus, supaya saat diambil ulang jurnal baru terbentuk dari nominal yang benar.
- DP tetap utuh: `service.dp_bayar`, ledger `service_dp`, dan jurnal DP tidak disentuh.
- Service kembali muncul di **Proses Service**, dan admin dapat membuka detail → tab Pembayaran untuk input ulang.
- Bila Tutup Kasir untuk unit + tanggal service sudah tersimpan, aksi tetap boleh setelah konfirmasi eksplisit; angka tutup kasir lama tidak dihitung ulang.

## 3. Non-goals

- Tidak mengubah total tagihan (`service.total_service`, `harus_dibayar`), sparepart, stok, invoice sparepart, kerusakan, atau data pelanggan.
- Tidak mengoreksi DP (sudah ada fitur Koreksi DP).
- Tidak mengoreksi atau membuka ulang Tutup Kasir.
- Tidak mengembalikan service berstatus 91 (dibatalkan setelah diambil).
- Tidak menambah tabel audit baru. Jejak audit memakai `log_message` berisi snapshot sebelum/sesudah.

## 4. Anti-goals

- Jangan sampai service yang masih proses atau belum diambil ikut berubah.
- Jangan sampai jurnal pengambilan lama tertinggal (nanti jurnal baru tidak terbentuk karena guard idempotensi).
- Jangan sampai DP hilang atau dihitung dua kali saat pengambilan ulang.
- Jangan sampai role selain Admin Root bisa memanggil endpoint.

## 5. Constraint dan fakta kode

- Status: 1/2 = proses, 3 = bisa diambil, 4 = sudah diambil (`ModelService::ProsesServiceAktif*`, `ServiceBisaDiambil`, `ServiceSudahDiambil`).
- Pengambilan: `Riwayat_Service::update_sudah_diambil` (`app/Controllers/Riwayat_Service.php:1297-1420`) menulis status 4, `tanggal_selesai`, lalu memposting jurnal `tabel_referensi='service'` dengan nominal `bayar_tunai + total pembayaran_bank + DP terpakai`. Guard idempotensi: kalau sudah ada baris jurnal `service`, tidak posting lagi.
- Pembayaran non-DP tidak membuat jurnal; hanya DP yang membuat jurnal (`service_dp`).
- `pembayaran_bank` untuk pembayaran service memakai `tabel_referensi='service_baru'` dan `id_referensi=idservice`.
- Rekon/tutup kasir membaca `service.status_service = 4`, `service.tanggal_selesai`, `service.bayar_tunai`, dan `service.harus_dibayar - service.bayar_tunai`.
- Tutup kasir tersimpan di tabel `tutup_kasir` dengan kolom `unit` dan `tanggal`; tidak ada fitur buka ulang.
- Admin Root = `(int) session('ID_JABATAN') === 1` (pola `TutupKasir::isAdminRoot`, `Riwayat_Service` koreksi DP).
- CSRF global dimatikan, tetapi endpoint keuangan berisiko memakai filter `['auth','csrf']` (pola `dp_service/koreksi`).

## 6. Perilaku yang diminta

### 6.1 Endpoint

`POST service/kembalikan_ke_proses` → `Riwayat_Service::kembalikanKeProses`, filter `['auth','csrf']`.

Input:

- `idservice` (wajib, int > 0)
- `alasan` (wajib, 1-120 karakter)
- `konfirmasi_tutup_kasir` = `1` bila Tutup Kasir sudah ada (dikirim setelah dialog konfirmasi)

### 6.2 Validasi server (urutan)

1. `ID_JABATAN === 1`; jika bukan → flash `gagal`, redirect kembali.
2. `idservice` valid dan baris `service` ditemukan.
3. `status_service === 4`; jika bukan → tolak.
4. `refund_garansi_at` kosong dan `GaransiClaimService::isClaimed($service)` false; jika ada klaim/refund → tolak dengan pesan bahwa service sudah masuk garansi/refund. (`isClaimed` sudah menangani sentinel `0000-00-00`/`1970-01-01` sebagai "tidak diklaim".)
5. Bila ada baris `tutup_kasir` untuk `unit = service.unit_idunit` dan `DATE(tanggal) = DATE(service.tanggal_selesai)`, wajib `konfirmasi_tutup_kasir === '1'`; jika tidak → tolak dengan pesan agar mengulang dengan konfirmasi.

### 6.3 Transaksi (semua atau tidak sama sekali)

1. Baca service `FOR UPDATE` (MySQLi/Postgre, pola `Service::insert_pembayaran`).
2. `DELETE FROM jurnal WHERE tabel_referensi='service' AND id_referensi=idservice` lewat `ModelJurnal::hapusByReferensi`.
3. `DELETE FROM pembayaran_bank WHERE tabel_referensi='service_baru' AND id_referensi=idservice`.
4. `UPDATE service SET status_service=2, tanggal_selesai=NULL, tanggal_bisa_diambil=NULL, bayar=0, bayar_tunai=0, bayar_bank=NULL WHERE idservice=idservice`.
5. Commit. Jika ada langkah gagal → rollback, flash `gagal`, tidak ada perubahan.

`dp_bayar`, `total_service`, `harus_dibayar`, dan seluruh data sparepart/penjualan tidak diubah.

### 6.4 Audit

`log_message('info', ...)` berisi: akun, jabatan, idservice, no_service, alasan, snapshot sebelum (status, tanggal_selesai, bayar, bayar_tunai, dp_bayar, jumlah baris bank, jumlah baris jurnal), dan hasil sesudah.

### 6.5 UI

Di halaman **Pengambilan Service** (`/sudah_diambil`), kolom Action tiap baris mendapat tombol **Kembalikan ke Proses** hanya bila `is_admin_root` true. Form POST berisi `csrf_field()`, `idservice`, `alasan` (dialog prompt sederhana), dan `konfirmasi_tutup_kasir=1`. Sebelum submit, `confirm()` menjelaskan bahwa pembayaran dihapus, jurnal pengambilan dibatalkan, dan bila Tutup Kasir tanggal itu sudah tersimpan, angka live Tutup Kasir serta omset/KPI teknisi pada tanggal/bulan tersebut ikut berubah (angka tutup kasir yang sudah tersimpan dan struknya tidak berubah).

Setelah sukses, redirect ke `detail/riwayat_service/{idservice}?tab=pembayaran` dengan flash sukses agar admin langsung mengisi pembayaran yang benar.

Controller `service_sudah_diambil` mengirim `is_admin_root` ke view. AJAX list sudah mengirim `idservice` per baris.

Catatan: `konfirmasi_tutup_kasir` adalah pertahanan server-side; UI selalu mengirim `1` setelah dialog konfirmasi. Peringatan spesifik "tutup kasir sudah ada" tidak ditampilkan per baris (tidak ada flag per baris di AJAX) — dialog generik sudah menyebut konsekuensinya.

## 7. Edge cases

- **Tutup kasir sudah tersimpan**: boleh setelah konfirmasi; baris `tutup_kasir` dan struk lama tidak berubah, tetapi halaman Tutup Kasir live dan omset/KPI teknisi bisa berubah karena dihitung ulang dari data service. Ini keputusan user.
- **Service punya DP**: DP tetap; saat diambil ulang, `dpTerpakai` dihitung lagi dari saldo DP. Jurnal pengambilan lama sudah dihapus sehingga tidak dobel. Efek samping: karena status kembali 2, `ServiceDpService::koreksiDp` kembali mengizinkan perubahan nominal DP — di luar scope fitur ini.
- **Service punya baris bank garansi (`service_garansi_0/1`)**: tidak disentuh.
- **Service dengan klaim garansi atau refund**: ditolak.
- **Service status 4 legacy tanpa jurnal pengambilan**: revert tetap boleh; hapus jurnal tidak melakukan apa-apa, dan pengambilan ulang akan memposting jurnal pertama kali dari nominal yang benar. Dicatat di log.
- **Cross-unit**: Admin Root boleh mengembalikan service unit mana pun yang tampil di daftar (sesuai peran lintas unit yang ada). Jurnal saat pengambilan ulang tetap memakai `session('ID_UNIT')` seperti perilaku lama; tidak diubah di fitur ini.
- **Double submit**: baris dikunci `FOR UPDATE`; submit kedua melihat status sudah 2 → ditolak.
- **Service lunas tetapi status 4**: tetap bisa dikembalikan; pembayaran dikosongkan.
- **Service belum lunas tetapi status 4**: tetap bisa dikembalikan; sisa pembayaran dikosongkan lalu diisi ulang.

## 8. Test

Feature test baru `tests/unit/ServiceKembalikanKeProsesTest.php` (pola `ServicePembayaranRouteGuardTest` + `ServicePengambilanDpTest`, database `erp_test`, prefix `db_`):

1. Admin Root berhasil: status 4 → 2, `tanggal_selesai`/`tanggal_bisa_diambil` NULL, `bayar`/`bayar_tunai` 0, baris bank `service_baru` hilang, jurnal `service` hilang, DP tetap.
2. Role selain Admin Root ditolak; data tidak berubah.
3. Service bukan status 4 ditolak.
4. Service dengan klaim garansi/refund ditolak.
5. Tutup kasir sudah ada tanpa `konfirmasi_tutup_kasir` ditolak; dengan konfirmasi berhasil.
6. Setelah revert, pengambilan ulang (`service/sudah_diambil`) membentuk jurnal baru dengan nominal pembayaran yang benar (tidak dobel).

## 9. Dokumentasi

Update `docs/prd/modul/PRD_MODUL_04_Service_Garansi.md` bagian 4.4 (Proses & Status) dan bagian verifikasi: tambah alur "Kembalikan ke Proses", guard Admin Root, perilaku jurnal/pembayaran/DP, dan batasan Tutup Kasir.

## 10. Builder calls

- Tidak menambah tabel audit; cukup log aplikasi. Alasan: fitur ini operasional dan frekuensinya rendah; upgrade ke tabel audit bila finance butuh queryable trail.
- Tombol hanya di halaman Pengambilan Service, bukan di detail service, agar satu tempat dan tidak menambah noise di halaman detail.
- `alasan` wajib tetapi disimpan di log, bukan kolom baru.
