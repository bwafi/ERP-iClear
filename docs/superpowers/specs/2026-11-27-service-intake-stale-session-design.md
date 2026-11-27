# Desain: hentikan penyuntingan senyap tiket service lama

Tanggal: 2026-11-27
Modul: 04 Service & Garansi (halaman `/service` dan `/proses_service`)

## Konteks

Halaman `/service` adalah wizard 4 langkah (pelanggan → kerusakan → sparepart →
pembayaran). Action form pelanggan dipilih dari ada/tidaknya `session('idservice')`
(`app/Views/transaksi/table/pelanggan_table.php:138-142`). Session itu hanya
dihapus saat pembayaran berhasil (`Service::insert_pembayaran`), pembatalan, atau
logout. Akibatnya, bila sebuah tiket selesai tanpa melewati pembayaran di sesi
yang sama (mis. diambil/dibayar oleh akun kasir lain), sesi teknisi tetap
menunjuk tiket lama. Intake berikutnya berubah menjadi *edit* tiket lama tanpa
pemberitahuan, dan tombol "Simpan & Cetak Invoice" mencetak nota tiket lama —
sementara tidak ada baris `service` baru.

Bukti data produksi (dump 2026-11-25): 28 service memiliki baris
`service_kerusakan` yang dibuat setelah tiket selesai, tanpa baris service baru
pada jam tersebut (mis. svc 343 selesai 2026-06-01 09:25, kerusakan masuk
2026-06-02 09:23).

## Tujuan

- Nota hanya tercetak untuk tiket yang benar-benar baru dibuat, atau tiket yang
  sedang disunting dan masih boleh disunting dari halaman intake.
- Tidak ada lagi penulisan ke tiket lama tanpa persetujuan pengguna.
- Teknisi punya jalan eksplisit untuk memulai service baru: tombol **Service Baru**.

## Non-goals

- Tidak mengubah urutan/isi wizard 4 langkah.
- Tidak mengubah `Service::insert_pembayaran` (termasuk mode koreksi) dan
  endpoint halaman detail (`update_service/save*`).
- Tidak memperbaiki duplikat/gap `no_service` (isu terpisah).
- Tidak mengubah filter tanggal/unit `/proses_service`, `JOIN pelanggan`, atau
  skema database.

## Anti-goals

- Teknisi tidak boleh terhalang membuat tiket baru hanya karena tiket lama masih
  berstatus 1/2.
- Penyuntingan yang sah dari halaman detail tiket tidak boleh ikut terblokir.

## Batasan

- Status: `1` menunggu, `2` proses, `3` bisa diambil, `4` selesai, `90/91` batal.
- `update/pelanggan_service` dipakai dua halaman: intake (`return_to=service`)
  dan detail (tanpa `return_to`). Pembeda ini sudah ada di form.
- Endpoint intake dan detail untuk kerusakan/sparepart sudah terpisah
  (`service/save*` vs `update_service/save*`).
- Tidak ada audit log tiket; perbaikan tidak boleh menulis ulang data lama.

## Pendekatan

| Pendekatan | Isi | Trade-off |
|---|---|---|
| **A (dipilih)** | Validasi status di `index()` + tombol "Service Baru" + guard server di endpoint intake | Perubahan kecil, alur kerja tetap, kasus tertutup di UI dan server |
| B | Pisahkan jalur create vs edit; action form tidak lagi ditentukan session | Paling bersih, tetapi menyentuh banyak view/route/controller dan berisiko regresi |
| C | Hapus konsep `session('idservice')` | Perubahan produk: teknisi kehilangan alur 4 langkah untuk tiket yang ditinggal |

A dipilih karena menutup penyebab langsung tanpa memindahkan risiko ke alur yang
dipakai setiap hari. B dan C bisa ditinjau ulang bila wizard intake memang akan
dirombak.

## Desain

### 1. Guard baru

`app/Services/Service/ServiceIntakeGuard.php`

- `isIntakeEditable($status): bool` → hanya status `1` dan `2`.
- `allowedUnitIds(): array` → HO (`ID_JABATAN` 0/1/34) = semua unit; SPV (40) =
  `ServiceUnitScopeService::spvUnitIds()`; lainnya = `[session('ID_UNIT')]`.
- `canEdit($service): bool` → tiket ada, status dapat disunting, unit tiket masuk
  cakupan user.

Alasan dipisah: logika ini dipakai `Service::index`, `update_service_pelanggan`,
`insert_kerusakan`, dan `insert_sparepart`, dan bisa diuji tanpa HTTP/session.

### 2. `Service::index()`

- Muat tiket dari `session('idservice')`.
- Bila tiket tidak ada, status bukan 1/2, atau unit di luar cakupan:
  hapus session, set flash info ("Tiket sebelumnya sudah selesai/di luar unit;
  form dimulai dari service baru."), render form kosong.
- Bila valid: kirim data ringkas tiket ke view (`intake_ticket`).

### 3. View `app/Views/transaksi/service.php`

- Saat mode lanjut: banner "Sedang menyunting tiket `<no_service>` (status …)".
- Tombol **Service Baru** di header, hanya tampil saat mode lanjut, dengan
  konfirmasi JS, menuju route yang sudah ada `service/clear_session`
  (`app/Config/Routes.php:164`). Tidak ada penulisan data bisnis.

### 4. `Riwayat_Service::update_service_pelanggan()`

- Bila `return_to === 'service'` (jalur intake):
  - tolak bila tiket tidak ada / status bukan 1/2 / unit di luar cakupan;
  - tolak bila `selectedidpelanggan` berbeda dari pelanggan tiket, dengan pesan
    yang mengarahkan ke tombol **Service Baru**;
  - saat ditolak: tidak ada penulisan apa pun.
- Bila bukan jalur intake (halaman detail): perilaku tetap, ditambah menghormati
  `selectedidpelanggan` setelah validasi pelanggan ada dan tidak dihapus
  (keputusan terbuka #1).

### 5. `Service::insert_kerusakan()`

- Validasi: `idservice_k` ada, sama dengan `session('idservice')`, status 1/2,
  unit dalam cakupan.
- Gagal → redirect kembali ke `/service?tab=kerusakan` dengan flash gagal,
  **tanpa** mencetak nota.
- Sukses → tetap redirect ke `cetak/invoice_service/{id}`.

### 6. `Service::insert_sparepart()`

- Tambah validasi status 1/2 + cakupan unit sebelum transaksi sparepart.

### 7. `Service::insert_service()`

- Pesan guard diubah: tawarkan **Service Baru** (hapus session), bukan
  `service/cancel` (yang menghapus tiket permanen).

## Keputusan yang diambil di desain ini

- Status `3` (bisa diambil) dianggap **selesai untuk intake**; penyuntingan
  lanjutan dilakukan dari halaman detail.
- **Service Baru** boleh dipakai walau masih ada tiket status 1/2; tiket lama
  tetap muncul di Proses Service dan tidak dihapus.
- Tombol memakai route GET `service/clear_session` yang sudah ada.

## Keputusan terbuka

1. Halaman detail saat ini menampilkan dropdown pelanggan yang bisa diubah, tetapi
   `selectedidpelanggan` diabaikan. Usulan: **hormati** perubahan pelanggan dari
   halaman detail (validasi pelanggan ada & tidak dihapus), sementara jalur intake
   tetap menolak. Perlu persetujuan karena mengubah data pelanggan tiket lama.

## Test

- `tests/unit/ServiceIntakeGuardTest.php` — status 1/2 vs 3/4/90/91, cakupan unit
  HO/SPV/unit sendiri.
- `tests/ServiceIntakeSessionTest.php` (feature, pola `tests/ServiceCancelDpTest.php`):
  - sesi menunjuk tiket status 4 → `GET /service` menghapus sesi dan form memakai
    `insert/pelanggan_service`;
  - sesi menunjuk tiket status 2 → form memakai `update/pelanggan_service`,
    banner tampil, tombol "Service Baru" tampil;
  - `POST update/pelanggan_service` dengan status 4 + `return_to=service` → data
    tidak berubah, flash gagal;
  - `POST service/saveKerusakan` dengan status 4 → tidak ada baris kerusakan baru
    dan tidak redirect ke cetak;
  - `POST service/saveKerusakan` dengan `idservice_k` ≠ session → ditolak.

Perintah: `vendor/bin/phpunit -c phpunit.xml tests/ServiceIntakeSessionTest.php tests/unit/ServiceIntakeGuardTest.php`

## Dokumentasi

Perbarui `docs/prd/modul/PRD_MODUL_04_Service_Garansi.md`: aturan sesi intake,
status yang boleh disunting dari `/service`, dan arti tombol **Service Baru**.

## Rollback

Revert commit. Tidak ada migrasi atau perubahan data.
