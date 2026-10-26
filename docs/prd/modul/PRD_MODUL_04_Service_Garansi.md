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
- Riwayat service: `Riwayat_Service::index/riwayat_service_ajax/detail_service/cetak_invoice/export/delete_service`.
### 4.5 Garansi
- `StatusGaransi::index/garansi_service_ajax` (garansi aktif), `update_service_pelanggan_garansi`, `insert_kerusakan_garansi`, `insert_sparepart_garansi`, `insert_pembayaran_garansi`.
- Klaim garansi: `claim_garansi`, `claim_detail`, `getRiwayatKlaim` → `GaransiClaimService`.
- Riwayat garansi: `Riwayat_Service::index2`, `riwayat_service_garansi/export2`; `Status_Service::index/{id}`.
### 4.6 DP & Refund
- `dp_service` + `dp_service_ajax`; `refund_dp_service` (`ServiceDpService`); `DP_SERVICE_MENU` migration.
### 4.7 Lainnya
- `Expired_service::index/export/expired_proses`, `Fee_Service::index`, `NotifikasiService::index`, `RiwayatLabaService::index/export`.
### 4.8 Refund Klaim Garansi
- `StatusGaransi::refund_detail` (GET) dan `StatusGaransi::refund` (POST `['auth','csrf']`); form dimuat dari tombol `Refund` di `/garansi_service`.
- Nominal refund otomatis `service.bayar_garansi` (tidak boleh melebihi); metode `tunai` atau `bank` (bank wajib rekening unit yang sah + referensi transfer).
- Kondisi tiap baris sparepart pengganti wajib dipilih: `baik` mengembalikan qty ke stok lewat `service_sparepart.jumlah_refund_garansi`; `rusak` mencatat `barang_rusak_claim` dan kerugian HPP pada `hpp_omset_garansi`/`tanggal_hpp_klaim` tanggal refund.
- Refund hanya sekali per service (`service.refund_garansi_at`) dan mengunci perubahan klaim setelahnya.
- Sparepart yang sudah diretur ke supplier tidak boleh direfund; baris `baik` ditolak bila kerugian HPP sudah tercatat.

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
- ⚠️ Gap tinggi:
  1. `cancel_service` tidak menghapus invoice/detail/pembayaran sparepart → invoice yatim & stok tetap terpotong. — SV-01
  2. Route `service_kerusakan`/`service_sparepart` menunjuk method yang tidak ada. — SV-02
  3. DP kedua gagal karena `kode_dp` sama; perlu keputusan aturan DP. — SV-03
  4. `status_service/{id}` publik, PII tanpa escape. — SV-04
  5. Cancel service via GET tanpa CSRF. — SV-05
- ⚠️ Belum diverifikasi penuh: isi lengkap `Riwayat_Service` (proses/pengambilan cronology per baris), `StatusGaransi` (semua method), dan integrasi `TutupKasir::dpServiceSelesai` (dibaca hanya bukti komentar).
- Test padat: `ServiceIntakeDpTest`, `ServicePengambilanDpTest`, `ServiceCancelDpTest`, `GaransiClaimSaveTest`, `StokBarangKurangiSparepartGaransiTest`, dst. Tidak ditemukan test untuk cancel invoice/detail, DP kedua, route mati, dan akses publik `status_service`.
