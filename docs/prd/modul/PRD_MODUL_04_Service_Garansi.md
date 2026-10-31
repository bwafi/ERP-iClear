# PRD Modul 04 — Service & Garansi

| | |
|---|---|
| Kode Modul | SV-04 |
| Sumber analisis | baca parsial `app/Controllers/Service.php`, `Routes.php`, service `ServiceDpService/GaransiClaimService/ServiceSparepartPenjualanSyncService`, banyak test |
| Status verifikasi | ✅ fakta / 🔶 inferensi / ⚠️ ketidakpastian |

---

## 1. Tujuan
Mengelola siklus service perangkat (intake, diagnosis, sparepart, pengerjaan, pembayaran & DP, pengambilan), garansi & klaim, serta barang rusak dan fee service.

## 2. Aktor
- Teknisi/petugas service (intake, proses, sparepart).
- Kasir (pembayaran/DP).
- CS/frontliner (pengambilan, konfirmasi).
- Supervisor/garansi (klaim garansi).

## 3. Preconditions
- Login; unit aktif.
- Pelanggan terpilih (wajib).
- Stok sparepart tersedia.

## 4. Alur Utama
### 4.1 Intake Service — `Service::insert_service`
1. Validasi: hanya satu transaksi aktif per user (guard `session('idservice')`); bila ada transaksi aktif → tolak sampai diselesaikan/dibatalkan.
2. Wajib pelanggan (`idpelanggan` valid).
3. DP opsional (`dp_bayar`): tolak negatif; metode `tunai|bank`; simpan ke ledger `service_dp` (melalui `ServiceDpService`) + jurnal DP; `kode_dp='DP{no_service}'`.
4. Set `session('idservice')`; seluruh operasi lanjutan memakai id service itu.
### 4.2 Kerusakan & Sparepart
- `insert_kerusakan`, `insert_sparepart` (potong stok sparepart; set `garansi_hari`).
- `search_sparepart_ajax`, `search_pelanggan_ajax`.
### 4.3 Pembayaran
- `Service::insert_pembayaran` (GROSS) — menulis `harus_dibayar` bruto.
- [⚠️] Route lama `update_insert/service/savePembayaran` (`Riwayat_Service::insert_pembayaran`, NET) **disonaktifkan** karena menghitung DP dua kali; view pemicunya sudah yatim. — `Routes.php:186-193`
### 4.4 Proses & Status
- `Riwayat_Service::proses_service` + `proses_service_ajax`; `update_status_proses`; `update_bisa_diambil`; `update_dibatalkan`; `update_sudah_diambil`; `dibatalkan_sudah_diambil`; `dibatalkan_aktifkan_lagi`; `togglePrioritas`.
- **Kembalikan ke Proses Service** (Admin Root, `ID_JABATAN = 1`): `Riwayat_Service::kembalikanKeProses` (POST `service/kembalikan_ke_proses`, filter `['auth','csrf']`).
  - Syarat: `status_service = 4`, tidak sedang diklaim garansi (`GaransiClaimService::isClaimed`), tidak direfund.
  - Dalam satu transaksi: hapus jurnal `tabel_referensi='service'`, hapus `pembayaran_bank` (`service_baru`), set `status_service=2`, `tanggal_selesai=NULL`, `tanggal_bisa_diambil=NULL`, `bayar=0`, `bayar_tunai=0`, `bayar_bank=NULL`.
  - DP (`dp_bayar`, ledger `service_dp`, jurnal DP) tidak berubah. `total_service`/`harus_dibayar` tidak berubah.
  - Bila Tutup Kasir unit+tanggal `tanggal_selesai` sudah ada, wajib `konfirmasi_tutup_kasir=1`. Angka tutup kasir tersimpan/struk tidak diubah, tetapi halaman live dan omset/KPI teknisi bisa berubah karena dihitung ulang dari `service`.
  - Alasan wajib (maks 120 karakter) dicatat di log aplikasi.
- Riwayat service: `Riwayat_Service::index/riwayat_service_ajax/detail_service/cetak_invoice/export/delete_service`.
### 4.5 Garansi
- `StatusGaransi::index/garansi_service_ajax` (garansi aktif), `update_service_pelanggan_garansi`, `insert_kerusakan_garansi`, `insert_sparepart_garansi`, `insert_pembayaran_garansi`.
- Klaim garansi: `claim_garansi`, `claim_detail`, `getRiwayatKlaim` → `GaransiClaimService`.
- Riwayat garansi: `Riwayat_Service::index2`, `riwayat_service_garansi/export2`; `Status_Service::index/{id}`.
### 4.6 DP & Refund
- `dp_service` + `dp_service_ajax`; `refund_dp_service` (`ServiceDpService`); `DP_SERVICE_MENU` migration.
- **Koreksi DP salah catat**: `Riwayat_Service::koreksi_dp_service` (POST `dp_service/koreksi`, filter `['auth','csrf']`) → `ServiceDpService::koreksiDp()`. Hanya **Admin Root** (`ID_JABATAN = 1`); role lain tidak melihat tombol "Koreksi" dan ditolak server.
  - Yang bisa dikoreksi: `metode` (`tunai`/`transfer`), `bank_idbank` + `referensi` (wajib saat transfer, dikosongkan saat tunai), dan `jumlah` (> 0). Tanggal DP tidak diubah.
  - Bukan kas masuk/keluar baru. Dalam satu transaksi: update `service_dp`, perbaiki dua baris jurnal DP yang ada (akun debit Kas Besar `1010101000` ↔ Kas di Bank `1010102000`, nominal debit/kredit disesuaikan; tanggal jurnal tetap), lalu hitung ulang proyeksi `service.dp_bayar` lewat `proyeksiDpBayar()`.
  - Jejak audit wajib: tabel `service_dp_koreksi` menyimpan `data_sebelum`, `data_sesudah` (JSON), `alasan` (wajib, maks 120 karakter), `oleh`, `created_at`.
  - Ditolak bila DP tidak `aktif`, sudah direfund (`refund_idkas_keluar`/`refund_at` terisi), rekening di luar scope unit, atau jurnal DP lama tidak sesuai nominal DP. Nominal tidak boleh diubah setelah service diambil (`status_service = 4`); metode/rekening masih boleh. Nominal juga tidak boleh membuat total DP + pembayaran non-DP melebihi `service.total_service`.
  - Koreksi tetap boleh walau Tutup Kasir tanggal DP sudah tersimpan; angka closing lama **tidak** dihitung ulang (koreksi Tutup Kasir adalah fitur terpisah). Untuk menghapus DP sepenuhnya, gunakan Refund, bukan nominal 0.
### 4.7 Lainnya
- `Expired_service::index/export/expired_proses`, `Fee_Service::index`, `NotifikasiService::index`, `RiwayatLabaService::index/export`.
### 4.8 Refund Uang Service Garansi
- `StatusGaransi::refund_detail` (GET) dan `StatusGaransi::refund` (POST `['auth','csrf']`); form dimuat dari tombol `Refund` di `/garansi_service`.
- Syarat: garansi masih aktif (`status_service = 4`, `garansi_hari > 0`, hari ini ≤ `tanggal_selesai + garansi_hari`) dan sudah ada pembayaran. Klaim tidak menghalangi; service yang sudah diklaim tetap bisa direfund.
- Nominal refund otomatis `service.dp_bayar + service.bayar` (uang yang sudah diterima), bukan `bayar_garansi` dan bukan input form; metode `tunai` atau `bank` (bank wajib rekening unit yang sah + referensi transfer).
- Refund dicatat sebagai `kas_keluar` kategori `REFUND` + jurnal `refund_garansi_tunai`/`refund_garansi_bank` sebesar nominal penuh `refund_garansi_jumlah`. Nominal penuh ini hanya mengurangi kas/bank, bukan omset.
- Omset Toko/Cabang/Teknisi dan omset harian dikurangi hanya sebesar `service.refund_garansi_omset`, yaitu porsi gross profit yang dulu masuk omset: `sub_total - (hpp_penjualan × qty)` untuk barang `baik` yang kembali ke stok, atau `sub_total` untuk barang `rusak` yang HPP-nya tetap menjadi beban. Pengurangan terjadi pada tanggal refund, bukan tanggal service/klaim.
- Omset bulanan `/omset_bulanan` (kartu omset, pertumbuhan vs bulan sebelumnya, grafik, dan Detail Omset Harian) juga dikurangi porsi gross profit refund pada tanggal refund, sama seperti `omset_hari_ini`. Arus kas tetap memakai `kas_keluar` refund, jadi jangan menambah pengurang kedua di kartu kas.
- Kondisi tiap baris sparepart service wajib dipilih: `baik` mengembalikan qty ke stok, `rusak` mencatat barang rusak tanpa mengembalikan stok. Untuk sparepart pengganti, `baik` memakai `service_sparepart.jumlah_refund_garansi` dan `rusak` mencatat `barang_rusak_claim` + kerugian HPP pada `hpp_omset_garansi`/`tanggal_hpp_klaim` tanggal refund. Untuk sparepart normal (yang keluar lewat `detail_penjualan`), `baik` dicatat sebagai `retur_pelanggan` tanpa mengubah detail penjualan asal, dan `rusak` hanya mencatat `barang_rusak_claim` (stok sudah keluar lewat penjualan, jadi tidak dikurangi dua kali).
- Refund hanya sekali per service (`service.refund_garansi_at`) dan mengunci perubahan klaim setelahnya.
- Sparepart yang sudah diretur ke supplier tidak boleh direfund; baris `baik` ditolak bila kerugian HPP sudah tercatat.
- 🔶 Porsi DP sudah dijurnal ke `2010302000`; pembayaran non-DP belum dijurnal (#37), sehingga seluruh refund saat ini mendebit akun diterima di muka. Pemisahan akun menunggu posting pendapatan service.

## 5. Business Rules (Bukti)
1. Satu transaksi service aktif per user (session `idservice`). — `Service::insert_service`
2. Pelanggan wajib di pilih sebelum simpan service. — `Service::insert_service`
3. DP tidak boleh negatif; bila DP>0 → ledger `service_dp` + jurnal DP; DP 0 dilewati (tanpa baris/jurnal). — `Service::insert_service`
4. `kode_dp` stabil `DP{no_service}`. — `Service::insert_service`
5. Sparepart tersimpan dengan `garansi_hari` (manual atau dari master). — `Service::insert_sparepart`
6. Service terhapus/dibatalkan dapat dibatalkan atau diaktifkan kembali. — `Riwayat_Service::dibatalkan_sudah_diambil` / `dibatalkan_aktifkan_lagi`
7. **Route pembayaran NET disonaktifkan**; pembayaran DP dipakai alur GROSS di `TutupKasir::dpServiceSelesai`. — `Routes.php:186-193`
8. Klaim garansi tercatat di `riwayat_claim_garansi`; terkait `barang_rusak_claim` (migration 2026-10-23/24). — test `GaransiClaimServiceTest`
9. HPP sparepart klaim di-backfill (migration 2026-10-24-000100). — migrasi
10. `cancel_service` menghapus service/kerusakan/sparepart dan DP/jurnal DP, tetapi **tidak** menghapus invoice `penjualan`/`detail_penjualan`/pembayaran yang dibuat `buatPenjualanDariService()`; invoice yatim tetap ada dan stok tetap terpotong. — `Service.php:484-547,886-932` (SV-01)
11. Route `service_kerusakan` dan `service_sparepart` menunjuk `Service::kerusakan_table`/`sparepart_table` yang tidak ada. — `Routes.php:151-152` (SV-02)
12. DP tambahan setelah DP awal memakai `kode_dp` yang sama (`DP{no_service}`), sehingga `ServiceDpService::catatDp()` mengembalikan `duplikat` dan pembayaran DP kedua gagal. — `Service::insert_service/insert_pembayaran`; `ServiceDpService` (SV-03)
13. Halaman `GET status_service/{id}` publik, tanpa auth, dan view mencetak nama/keluhan pelanggan tanpa `esc()` → PII/XSS. — `Routes.php:218`; `Views/transaksi/status_service.php:83-88` (SV-04)
14. Pembatalan service memakai `GET service/cancel/{id}` tanpa CSRF. — `Routes.php:158`; `Service::cancel_service` (SV-05)

## 6. Acceptance Criteria
- [ ] Intake service menolak transaksi aktif kedua; memaksa pelanggan.
- [ ] DP tersimpan ke ledger `service_dp` + jurnal; refund DP berfungsi.
- [ ] Admin Root bisa mengoreksi metode/rekening/nominal DP salah catat; role lain ditolak; jurnal, proyeksi `dp_bayar`, dan audit ikut konsisten.
- [ ] Sparepart terpotong dari stok dan mencatat garansi.
- [ ] Alur proses→bisa diambil→diambil→dibatalkan berjalan dengan status yang konsisten.
- [ ] Klaim garansi tercatat & bisa dilihat riwayatnya.
- [ ] Invoice & ekspor riwayat service tersedia.

## 7. Dependensi
- `ModelService/ModelProsesService/ModelServiceKerusakan/ModelServiceSparepart/ModelServiceDp`, `ModelRiwayatKlaimGaransi`, `ModelBarangRusakClaim`, `ModelStokBarang`, `ModelPelanggan`.
- Service: `ServiceDpService`, `GaransiClaimService`, `ServiceSparepartPenjualanSyncService`, `SparepartKeluarHardDeleteService`.
- Modul: Penjualan (sinkronisasi sparepart → penjualan), Stok, Keuangan (jurnal/DP), Hutang Piutang (jasa teknisi).

## 8. Status & Tandai
- ✅ Terverifikasi: guard transaksi aktif, DP ledger, garansi sparepart, route NET dinonaktifkan, klaim garansi.
- ✅ Refund klaim garansi: tunai/bank, keputusan kondisi sparepart per baris, kunci sekali refund, dan guard retur supplier. — `GaransiRefundService`; test `GaransiRefundServiceTest`, `GaransiServiceViewTest`, `ReturBarangRusakTest`.
- ✅ Koreksi DP salah catat: Admin Root saja, jurnal DP diperbaiki in-place, audit `service_dp_koreksi`, nominal terkunci setelah service diambil. — `ServiceDpService::koreksiDp`; test `ServiceDpKoreksiTest`.
- ✅ Kembalikan service sudah diambil ke Proses Service: Admin Root saja, jurnal pengambilan dihapus, pembayaran lama dihapus, DP tetap, guard tutup kasir. Test `tests/unit/ServiceKembalikanKeProsesTest.php`.
- ⚠️ Koreksi DP tidak menghitung ulang Tutup Kasir yang sudah tersimpan; koreksi Tutup Kasir belum tersedia.
- ⚠️ Gap tinggi:
  1. `cancel_service` tidak menghapus invoice/detail/pembayaran sparepart → invoice yatim & stok tetap terpotong. — SV-01
  2. Route `service_kerusakan`/`service_sparepart` menunjuk method yang tidak ada. — SV-02
  3. DP kedua gagal karena `kode_dp` sama; perlu keputusan aturan DP. — SV-03
  4. `status_service/{id}` publik, PII tanpa escape. — SV-04
  5. Cancel service via GET tanpa CSRF. — SV-05
- ⚠️ Belum diverifikasi penuh: isi lengkap `Riwayat_Service` (proses/pengambilan cronology per baris), `StatusGaransi` (semua method), dan integrasi `TutupKasir::dpServiceSelesai` (dibaca hanya bukti komentar).
- Test padat: `ServiceIntakeDpTest`, `ServicePengambilanDpTest`, `ServiceCancelDpTest`, `GaransiClaimSaveTest`, `StokBarangKurangiSparepartGaransiTest`, dst. Tidak ditemukan test untuk cancel invoice/detail, DP kedua, route mati, dan akses publik `status_service`.
