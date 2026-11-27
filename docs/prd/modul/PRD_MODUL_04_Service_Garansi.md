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
1. Saat `GET /service`, sesi `idservice` hanya dipertahankan bila tiket berstatus 1/2 dan unit tiket berada dalam cakupan user (`ServiceIntakeGuard`: HO role 0/1/34 = semua unit, SPV 40 via `spv_units`, role lain `ID_UNIT`). Status selain 1/2 (termasuk 3, 4, 90, 91) atau unit lain → sesi dihapus, muncul pesan, dan form mulai dari service baru. Tiket aktif menampilkan banner status + tombol **Service Baru** (`service/clear_session`). — ✅ `Service::index`; test `ServiceIntakeSessionTest`
2. Validasi: hanya satu transaksi aktif per user (guard `session('idservice')`); bila ada transaksi aktif → tolak dan tawarkan **Service Baru** (hapus sesi tanpa menghapus tiket lama).
3. Wajib pelanggan (`idpelanggan` valid).
4. DP opsional (`dp_bayar`): tolak negatif; metode `tunai|bank`; simpan ke ledger `service_dp` (melalui `ServiceDpService`) + jurnal DP; `kode_dp='DP{no_service}'`.
5. Set `session('idservice')`; seluruh operasi lanjutan memakai id service itu.
### 4.2 Kerusakan & Sparepart
- `insert_kerusakan`, `insert_sparepart` (potong stok sparepart; set `garansi_hari`).
  - Keduanya menolak tiket di luar status 1/2, unit di luar cakupan, atau `idservice` yang berbeda dari `session('idservice')`; kerusakan yang ditolak tidak mencetak nota. — ✅ `Service::insert_kerusakan/insert_sparepart`; test `ServiceIntakeSessionTest`
  - Penyuntingan pelanggan dari `/service` (`update/pelanggan_service` dengan `return_to=service`) ditolak bila pelanggan berbeda atau `idservice` tidak sama dengan `session('idservice')`; dari halaman detail, `selectedidpelanggan` hanya mengubah pelanggan service bila tombol **Pilih** mengirim penanda `pelanggan_changed=1` dan pelanggan tujuan ada serta tidak terhapus (`deleted=0`). — ✅ `Riwayat_Service::update_service_pelanggan`; test `ServiceIntakeSessionTest`
  - Domisili pelanggan hanya ditulis bila minimal satu field `domisili_*` dikirim tidak kosong, sehingga memilih pelanggan baru dari halaman detail tidak menimpa domisili dengan string kosong. — ✅ `Riwayat_Service::update_service_pelanggan`; test `ServiceIntakeSessionTest`
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
- **Pengambilan — `Riwayat_Service::update_sudah_diambil`**: bila `total_service <= 0` dan service punya baris `service_sparepart` dengan `sub_total`, total tagihan = `SUM(sub_total)` dan disimpan ke `total_service` + `harus_dibayar` dalam transaksi yang sama. Total yang sudah terisi tidak ditimpa. Bila total tetap 0 sementara saldo DP > 0, pengambilan ditolak (status tidak berubah, tanpa jurnal) agar DP tidak dianggap kas keluar saat service selesai. — `Riwayat_Service.php`; test `ServicePengambilanDpTest`
- Riwayat service: `Riwayat_Service::index/riwayat_service_ajax/detail_service/cetak_invoice/export/delete_service`.
### 4.5 Garansi
- `StatusGaransi::index/garansi_service_ajax` (garansi aktif), `update_service_pelanggan_garansi`, `insert_kerusakan_garansi`, `insert_sparepart_garansi`, `insert_pembayaran_garansi`.
- Klaim garansi: `claim_garansi`, `claim_detail`, `getRiwayatKlaim` → `GaransiClaimService`.
- Riwayat garansi: `Riwayat_Service::index2`, `riwayat_service_garansi/export2`; `Status_Service::index/{id}`.
### 4.6 DP & Refund
- `dp_service` + `dp_service_ajax`; `refund_dp_service` (`ServiceDpService`); `DP_SERVICE_MENU` migration.
- **Refund DP**: `Riwayat_Service::refund_dp_service` (POST `dp_service/refund`, filter `['auth','csrf']`) → `ServiceDpService::refund()`. Refund penuh membatalkan service dan mencatat `kas_keluar` kategori `REFUND` + jurnal pembalik `refund_dp_service_tunai`/`refund_dp_service_bank`; penerimaan DP asal tetap tersimpan sebagai histori.
  - Metode refund wajib dipilih di modal dan boleh berbeda dari metode DP asal (mis. DP tunai → refund transfer). Bila metode tidak dikirim, service default mengikuti metode DP asal (kompatibilitas pemanggil lama).
  - Refund `transfer` wajib rekening dalam scope unit service (divalidasi `BankRekeningValidator::validateUntukUnit()`) dan referensi transfer; refund `tunai` mengosongkan rekening/referensi.
  - `kas_keluar.idbank`, template jurnal, dan audit `refund_metode`/`refund_bank_idbank`/`refund_referensi` mengikuti metode refund yang dipilih, bukan metode DP asal.
  - Daftar rekening modal refund mengikuti hak refund: role 0/1 semua unit, role lain unit sesi. Tombol Refund untuk non-HO hanya tampil pada baris unit sendiri; server tetap menolak unit lain.
- **Filter unit Service**: Admin Root/Finance (`0/1`) dan **Manager (34)** boleh memilih unit di `proses_service`/`proses_service_ajax`; `riwayat_service`/`riwayat_service_ajax` dan `dp_service`/`dp_service_ajax` juga mengizinkan **Customer Service (42)**. **SPV (40)** hanya boleh memilih cabang binaannya dari tabel `spv_units` (fallback `ID_UNIT` bila mapping kosong), dan "Semua Unit" berarti seluruh cabang binaan; role lain terkunci ke `ID_UNIT` sesi. Filter SPV divalidasi di server (`ServiceUnitScopeService::resolveSpvFilter`) dan query memakai daftar unit (`ModelService::resolveUnitFilterIds`, `ModelServiceDp::datatable`), sehingga unit di luar binaan tidak bisa dibuka lewat request manual. Refund DP tetap dibatasi unit sendiri untuk non-HO (34/40/42 tidak termasuk), jadi perluasan ini hanya untuk baca. ⚠️ `proses_service` belum memuat role 42 sebagai role lintas unit meski role itu lintas unit di `riwayat_service`/`dp_service`. — `Riwayat_Service::index/riwayat_service_ajax/proses_service/proses_service_ajax/dp_service/dp_service_ajax`; test `DpServiceUnitFilterTest`, `ModelServiceUnitFilterListTest`
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
- Omset bulanan `/omset_bulanan` (kartu omset, pertumbuhan vs bulan sebelumnya, grafik, dan Detail Omset Harian) juga dikurangi porsi gross profit refund pada tanggal refund, sama seperti `omset_hari_ini`. Kartu **Value** dan mode **Value** harian memakai pengurang yang sama (ditambah HPP Klaim), hanya HPP penjualan yang tidak dikurangkan. Arus kas tetap memakai `kas_keluar` refund, jadi jangan menambah pengurang kedua di kartu kas.
- Kondisi tiap baris sparepart service wajib dipilih: `baik` mengembalikan qty ke stok, `rusak` mencatat barang rusak tanpa mengembalikan stok. Untuk sparepart pengganti, `baik` memakai `service_sparepart.jumlah_refund_garansi` dan `rusak` mencatat `barang_rusak_claim` + kerugian HPP pada `hpp_omset_garansi`/`tanggal_hpp_klaim` tanggal refund. Untuk sparepart normal (yang keluar lewat `detail_penjualan`), `baik` dicatat sebagai `retur_pelanggan` tanpa mengubah detail penjualan asal, dan `rusak` hanya mencatat `barang_rusak_claim` (stok sudah keluar lewat penjualan, jadi tidak dikurangi dua kali).
- Refund hanya sekali per service (`service.refund_garansi_at`) dan mengunci perubahan klaim setelahnya.
- Sparepart yang sudah diretur ke supplier tidak boleh direfund; baris `baik` ditolak bila kerugian HPP sudah tercatat.
- 🔶 Porsi DP sudah dijurnal ke `2010302000`; pembayaran non-DP belum dijurnal (#37), sehingga seluruh refund saat ini mendebit akun diterima di muka. Pemisahan akun menunggu posting pendapatan service.

## 5. Business Rules (Bukti)
1. Satu transaksi service aktif per user (session `idservice`); sesi hanya dipertahankan untuk tiket status 1/2 dalam cakupan unit, dan pengguna bisa memilih **Service Baru** (hapus sesi) tanpa menghapus tiket lama. — `Service::index`; `ServiceIntakeGuard`; test `ServiceIntakeSessionTest`
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
15. Pengambilan service dengan total 0 mengambil total dari `SUM(service_sparepart.sub_total)`; total yang sudah terisi tidak ditimpa, dan DP>0 tanpa total ditolak. — `Riwayat_Service::update_sudah_diambil`; test `ServicePengambilanDpTest`
16. HPP sparepart service (`service_sparepart.hpp_penjualan`), HPP invoice sparepart, dan HPP klaim garansi (`hpp_penjualan_garansi`/`hpp_omset_garansi`) memakai `hpp_unit` produk+unit service; fallback `hpp_barang` lalu `barang.harga_beli`. Prefill HPP di halaman klaim memakai resolver yang sama. — `ModelHppUnit::resolve/resolveMany`; `Service/Riwayat_Service::insert_sparepart`; `StatusGaransi`
17. Picker service (`service/search_sparepart`, dipakai `/service` dan `/detail/riwayat_service/{id}?tab=sparepart`) punya **dua tombol/mode**: "Pilih Sparepart" menampilkan produk sparepart/aksesoris berstok dari view `stok_barang` (kode sprt/acc, stok 0 tersaring), dan "Pilih Jasa" menampilkan master `barang.jenis_barang='jasa'` tanpa syarat stok. Mode dipilih lewat parameter `jenis` (`sparepart` default, `jasa`); kedua mode menambahkan baris ke `service_sparepart` seperti biasa. Harga jual di modal untuk kedua mode mengikuti unit sesi: `COALESCE(harga_jual_unit.harga_jual, barang.harga)`, tanpa baris override berarti ikut master. Nilai yang dipilih menjadi snapshot `service_sparepart.harga_penjualan`; baris lama tidak di-backfill dan tidak berubah saat override/master diubah. HPP yang tampil di modal memakai resolver per unit yang sama dengan jalur simpan (`hpp_unit` barang+unit login, fallback `hpp_barang`, lalu `barang.harga_beli`), sehingga angka di picker sama dengan yang disimpan. — `Service::search_sparepart_ajax`; `ModelHargaJualUnit::resolve`; `Views/riwayat/table/sparepart_table.php`; `Views/transaksi/table/sparepart_table.php`; test `ServiceSparepartJasaTest`, `ServiceSparepartSearchDedupTest`
18. Tiket service hanya boleh disunting dari intake bila berstatus 1/2 dan unitnya dalam cakupan user; status lain atau unit lain mereset sesi, bukan menyunting tiket lama. — ✅ `Service::index`; `ServiceIntakeGuard`; test `ServiceIntakeSessionTest`
19. `service/saveKerusakan` dan `service/saveSparepart` menolak tiket yang sudah selesai/unit lain atau `idservice` yang tidak sama dengan sesi; penolakan tidak menulis data dan tidak membuka halaman cetak nota. — ✅ `Service::insert_kerusakan/insert_sparepart`; test `ServiceIntakeSessionTest`

## 6. Acceptance Criteria
- [ ] Intake service menolak transaksi aktif kedua; memaksa pelanggan.
- [ ] DP tersimpan ke ledger `service_dp` + jurnal; refund DP berfungsi.
- [ ] Admin Root bisa mengoreksi metode/rekening/nominal DP salah catat; role lain ditolak; jurnal, proyeksi `dp_bayar`, dan audit ikut konsisten.
- [ ] Sparepart terpotong dari stok dan mencatat garansi.
- [ ] Alur proses→bisa diambil→diambil→dibatalkan berjalan dengan status yang konsisten.
- [ ] Klaim garansi tercatat & bisa dilihat riwayatnya.
- [ ] Picker sparepart/jasa memakai harga jual unit service dan transaksi service lama tidak berubah.
- [ ] Invoice & ekspor riwayat service tersedia.
- [ ] Sesi intake menunjuk tiket status 3/4/90/91 atau unit lain → form mulai dari service baru tanpa menyunting tiket lama.
- [ ] Simpan kerusakan/sparepart dari tiket selesai atau `idservice` yang tidak sama dengan sesi ditolak dan tidak mencetak nota.

## 7. Dependensi
- `ModelService/ModelProsesService/ModelServiceKerusakan/ModelServiceSparepart/ModelServiceDp`, `ModelRiwayatKlaimGaransi`, `ModelBarangRusakClaim`, `ModelStokBarang`, `ModelPelanggan`.
- Service: `ServiceDpService`, `GaransiClaimService`, `ServiceSparepartPenjualanSyncService`, `SparepartKeluarHardDeleteService`.
- Modul: Penjualan (sinkronisasi sparepart → penjualan), Stok, Keuangan (jurnal/DP), Hutang Piutang (jasa teknisi).

## 8. Status & Tandai
- ✅ Terverifikasi: guard transaksi aktif, DP ledger, garansi sparepart, route NET dinonaktifkan, klaim garansi.
- ✅ Terverifikasi: HPP sparepart service dan klaim garansi baru memakai `hpp_unit` per unit service (fallback `hpp_barang`/`barang.harga_beli`); snapshot lama tidak diubah. — `HppPerUnitTest`, `GaransiClaimSaveTest`
- ✅ Terverifikasi: picker service memisahkan "Pilih Sparepart" (produk berstok, view `stok_barang`) dan "Pilih Jasa" (master jasa tanpa stok); mode default tetap sparepart. Harga jual mengikuti `harga_jual_unit` unit sesi (fallback `barang.harga`), HPP di modal memakai HPP per unit yang sama dengan jalur simpan, dan snapshot `service_sparepart` lama tidak berubah. — `ServiceSparepartJasaTest`, `ServiceSparepartSearchDedupTest`
- ✅ Refund klaim garansi: tunai/bank, keputusan kondisi sparepart per baris, kunci sekali refund, dan guard retur supplier. — `GaransiRefundService`; test `GaransiRefundServiceTest`, `GaransiServiceViewTest`, `ReturBarangRusakTest`.
- ✅ Koreksi DP salah catat: Admin Root saja, jurnal DP diperbaiki in-place, audit `service_dp_koreksi`, nominal terkunci setelah service diambil. — `ServiceDpService::koreksiDp`; test `ServiceDpKoreksiTest`.
- ✅ Refund DP memilih metode/rekening sendiri yang boleh berbeda dari DP asal; transfer wajib rekening scope unit + referensi, tunai mengosongkan rekening/referensi. — `ServiceDpService::refund`; test `tests/unit/ServiceDpRefundTest.php`.
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
