# Group D — Service & Garansi

Laporan analisis **baca-saja** pada modul Service (jasa perbaikan HP), Garansi Service, Penerimaan/Refund DP, dan Sparepart Keluar. Semua bukti merujuk ke file sumber aktual. Tidak ada perubahan source code/DB/config yang dilakukan.

---

## 1. Ruang Lingkup

| Area | File utama |
|---|---|
| Intake & transaksi service (alur baru 4 langkah) | `app/Controllers/Service.php`, `app/Models/ModelService.php` |
| Riwayat & proses service (status, pengambilan, hapus) | `app/Controllers/Riwayat_Service.php` |
| Garansi service & klaim | `app/Controllers/StatusGaransi.php`, `app/Services/Service/GaransiClaimService.php` |
| Tag publik status service (tanpa auth filter) | `app/Controllers/Status_Service.php` |
| Kedaluwarsa service | `app/Controllers/Expired_service.php` |
| Laba & fee service | `app/Controllers/RiwayatLabaService.php`, `app/Controllers/Fee_Service.php` |
| Notifikasi pengambilan | `app/Controllers/NotifikasiService.php`, `app/Views/notifikasi/pengambilan_service.php` |
| Barang rusak (inventori) & klaim | `app/Controllers/BarangRusak.php`, `app/Models/ModelBarangRusak.php`, `ModelBarangRusakClaim.php` |
| Ledger DP + refund | `app/Services/Service/ServiceDpService.php`, `app/Models/ModelServiceDp.php` |
| Sinkronisasi sparepart → invoice penjualan | `app/Services/Service/ServiceSparepartPenjualanSyncService.php`, `SparepartKeluarHardDeleteService.php` |
| Migrasi terkait | `app/Database/Migrations/2026-10-12-080000_CreateServiceDpLedger.php`, `2026-10-14-000100_AddDpServiceMenu.php`, `2026-10-14-000200_AddServiceDpRefund.php`, `2026-10-14-000300_AddServiceLinkToPenjualan.php`, `2026-10-23-000300_AddCatatanToRiwayatClaimGaransi.php`, `2026-10-23-000400_CreateBarangRusakClaim.php`, `2026-10-23-000500_StokBarangKurangiSparepartGaransi.php`, `2026-10-24-000100_BackfillHppSparepartKlaim.php` |

Model relevan: `ModelService`, `ModelProsesService`, `ModelServiceKerusakan`, `ModelServiceSparepart`, `ModelServiceDp`, `ModelKerusakan`, `ModelRiwayatKlaimGaransi`, `ModelBarangRusakClaim`, `ModelBarangRusak`, `ModelStokBarang`, `ModelPelanggan`.

---

## 2. Endpoint / Routes

Sumber: `app/Config/Routes.php` (baris 149–234, 332–340). Semua bermfilter `auth` kecuali dinyatakan lain.

### 2.1 Alur intake / transaksi service (kontroller `Service`)
| Route | Handler | Catatan |
|---|---|---|
| `GET /service` | `Service::index` | form intake multi-tab |
| `GET /service_kerusakan` | `Service::kerusakan_table` | ⚠️ **method tidak ada** di `Service.php` |
| `GET /service_sparepart` | `Service::sparepart_table` | ⚠️ **method tidak ada** di `Service.php` |
| `POST /insert/pelanggan_service` | `Service::insert_service` | intake + DP via ledger |
| `POST /service/search_sparepart` | `Service::search_sparepart_ajax` | AJAX |
| `POST /service/search_pelanggan` | `Service::search_pelanggan_ajax` | AJAX |
| `GET /service/cancel/(:num)` | `Service::cancel_service` | batalkan ticket |
| `GET /service/clear_session` | `Service::clear_session` | |
| `POST /service/saveKerusakan` | `Service::insert_kerusakan` | |
| `POST /service/saveSparepart` | `Service::insert_sparepart` | sinkronisasi invoice |
| `POST /insert/service/savePembayaran` | `Service::insert_pembayaran` | **handler GROSS aktif** |

### 2.2 Proses, pengambilan, riwayat (kontroller `Riwayat_Service`)
| Route | Handler | Catatan |
|---|---|---|
| `GET /proses_service` | `proses_service` | |
| `POST /proses_service/ajax` | `proses_service_ajax` | |
| `GET /dp_service` / `POST /dp_service/ajax` | `dp_service` / `dp_service_ajax` | menu DP |
| `POST /dp_service/refund` | `refund_dp_service` | filter `['auth','csrf']` |
| `POST /service/toggle_prioritas` | `togglePrioritas` | AJAX |
| `POST /update_status_proses` | `update_status_proses` | status→2 |
| `POST /service/bisa_diambil` | `update_bisa_diambil` | status→3 |
| `POST /service/dibatalkan` | `update_dibatalkan` | status→90 |
| `GET /list/service/dibatalkan` | `service_dibatalkan` | |
| `GET /bisa_diambil` | `service_bisa_diambil` | |
| `POST /service/sudah_diambil` | `update_sudah_diambil` | **settlement + jurnal pengambilan** |
| `GET /sudah_diambil` / `POST /sudah_diambil/ajax` | `service_sudah_diambil` | |
| `POST /diambil/dibtalkan` | `dibatalkan_sudah_diambil` | status→91 |
| `POST /aktifkan/dibatalkan` | `dibatalkan_aktifkan_lagi` | status→2 |
| `GET /riwayat_service` / `POST /riwayat_service/ajax` | `index` | |
| `GET /detail/riwayat_service/(:num)` | `detail_service` | |
| `GET /cetak/invoice_service/(:num)` | `cetak_invoice` | PDF + QR |
| `POST /riwayat_service/export`, `/riwayat_service_garansi/export` | `export`, `export2` | XLSX |
| `POST /delete_service` | `delete_service` | hanya admin root (jabatan 1) |
| `GET /sparepart_keluar`, `POST /sparepart_keluar/ajax` | `sparepart`, `sparepart_keluar_ajax` | |
| `POST /sparepart_keluar/edit` | `edit_sparepart_keluar` | hanya admin root |
| `POST /sparepart_keluar/delete` | `delete_sparepart_keluar` | hanya admin root |
| `POST /update/pelanggan_service` | `update_service_pelanggan` | |
| `POST /update_service/saveKerusakan` | `insert_kerusakan` | |
| `POST /update_service/saveSparepart` | `insert_sparepart` | |

### 2.3 Garansi & klaim (kontroller `StatusGaransi`)
| Route | Handler | Catatan |
|---|---|---|
| `GET /garansi_service` | `index` | |
| `POST /garansi_service/ajax` | `garansi_service_ajax` | |
| `GET /riwayat_service_garansi` | `Riwayat_Service::index2` | |
| `GET /service_by_garansi/(:num)` | `service_by_garansi` | |
| `POST /update/pelanggan_service_garansi` | `update_service_pelanggan_garansi` | `['auth','csrf']` |
| `POST /update_garansi_service/saveKerusakan` | `insert_kerusakan_garansi` | `['auth','csrf']` |
| `POST /update_service_garansi/saveSparepart` | `insert_sparepart_garansi` | `['auth','csrf']` |
| `POST /update_service_garansi/savePembayaran` | `insert_pembayaran_garansi` | `['auth','csrf']` |
| `GET /claim_garansi/detail/(:num)` | `claim_detail` | |
| `POST /claim_garansi` | `claim_garansi` | `['auth','csrf']` |
| `GET /riwayat-klaim/(:num)` | `getRiwayatKlaim` | AJAX |

### 2.4 Routes yang DINONAKTIFKAN (komentar)
```php
// Routes.php baris 193 (DINONAKTIFKAN):
// $routes->post('update_insert/service/savePembayaran', 'Riwayat_Service::insert_pembayaran', ['filter' => 'auth']);

// Routes.php baris 220 (DINONAKTIFKAN):
// $routes->post('insert/sparepart_service', 'Service::insert_sparepart', ['filter' => 'auth']);
```
Alasan (komentar kode baris 186–192): `Riwayat_Service::insert_pembayaran` menulis `harus_dibayar` **NET** (`total - dp_bayar`), sedangkan alur UI aktif memakai handler **GROSS** `Service::insert_pembayaran` (route `insert/service/savePembayaran`). View pemicu jalur NET (`riwayat/table/pembayaran.php`) sudah yatim; jika tetap aktif, integrasi DP di TutupKasir (`dpServiceSelesai()`) akan menghitung DP dua kali (undercount sebesar DP). Terverifikasi oleh `tests/unit/ServicePembayaranRouteGuardTest.php`.

### 2.5 Route publik tanpa auth
- `GET /status_service/(:num)` → `Status_Service::index/$1` — **tidak ada filter `auth`** (Routes.php baris 218). Ini satu-satunya route service tanpa proteksi autentikasi; QR invoice mengarah ke sini (`Riwayat_Service::cetak_invoice` menyisipkan QR `base_url('status_service/'.$idservice)`).

---

## 3. Alur Pengguna Utama & Alternatif

### 3.1 Intake Service (alur baru 4 langkah)
1. **Pelanggan** — `Service::index` (tab pelanggan) → pilih/buat pelanggan. `insert_service` memvalidasi pelanggan harus ada (`ModelPelanggan::getById`), menolak `dp_bayar` negatif, dan mencegah dua ticket aktif per sesi (session `idservice` + status 1/2/3).
2. **Kerusakan** — `insert_kerusakan` (route `service/saveKerusakan`) upsert baris `service_kerusakan` per `fungsi`; yang tidak dipilih dihapus. Wajib ≥1 fungsi.
3. **Sparepart** — `insert_sparepart` (route `service/saveSparepart`) menulis ulang `service_sparepart`, menetapkan `garansi_hari`, lalu **menghapus invoice penjualan lama** dan **membuat invoice baru** via `buatPenjualanDariService()`.
4. **Pembayaran** — `insert_pembayaran` (route `insert/service/savePembayaran`) — **GROSS**; DP tambahan dicatat via `ServiceDpService::catatDp`. Setelah selesai, session `idservice` dibersihkan.

Nomor service: `SRV` + unit + `Ymd` + urutan 3 digit (counter per unit per hari), `Service::insert_service` baris 193–207.

### 3.2 Proses Service (status 2 ⇄ 3)
- `update_status_proses` → `status_service=2`, insert `proses_service` (riwayat status).
- `update_bisa_diambil` → `status_service=3` + `tanggal_bisa_diambil`.
- Alternatif: `update_dibatalkan` → status 90; `dibatalkan_sudah_diambil` → status 91; `dibatalkan_aktifkan_lagi` → status 2 (reset `tanggal_bisa_diambil`).

### 3.3 Pengambilan / Settlement (status 3 → 4)
`Riwayat_Service::update_sudah_diambil` (route `service/sudah_diambil`):
1. Guard idempotensi: bila `status_service=4` → tolak.
2. Baca `total_service`, `bayar_tunai`, pembayaran bank (`pembayaran_bank` referensi `service_baru`).
3. Sumber saldo DP via `ServiceDpService::statusSumberDp()` (ledger / historis / none). DP terpakai = `min(saldo DP, sisa tagihan)`.
4. Jurnal pengambilan (tabel_referensi `service`): Dr Kas (± bayar tunai/bank), Dr PDM (`dpTerpakai`), Cr Pendapatan (`pendapatan`). Verify seimbang & nominal.
5. Idempotensi berbasis jurnal: bila jurnal `service` sudah ada (`ringkasByReferensi`), tidak diposting ulang (menahan jalur "Dibatalkan(90)→Aktifkan(2)→diambil ulang" agar tidak menggandakan pendapatan).

### 3.4 Garansi & Klaim
- **Status garansi**: `status_service=4` + `garansi_hari>0` → masa garansi = `tanggal_selesai + garansi_hari hari` (inklusif hari terakhir). `GaransiClaimService::warrantyEnd()`.
- **Klaim**: hanya jika `isClaimable` (status 4, belum diklaim, belum kadaluwarsa). `claim_garansi` (route `claim_garansi`):
  1. `FOR UPDATE` baris service; cek unit (non-HO wajib unit sesi).
  2. Menolak jika sudah pernah diklaim (`isClaimed` ATAU ada baris `riwayat_claim_garansi`).
  3. `simpanSparepartKlaim()` — menulis `jumlah_tambahan_garansi`, HPP garansi, `status_retur` (`bisa`/`tidak_bisa`), `hpp_omset_garansi`, `tanggal_hpp_klaim`. Cek stok cukup (`stok_akhir >= qty`).
  4. `simpanBarangRusakKlaim()` — insert `barang_rusak_claim`.
  5. Set `tanggal_claim_garansi` + insert `riwayat_claim_garansi` (catatan wajib).
  6. **Service asli tidak diubah** (status, tanggal selesai, total asli tetap).
- **Sparepart pengganti klaim** (`insert_sparepart_garansi`): memperbarui `jumlah_tambahan_garansi`, `harga_penjualan_garansi`, `sub_total_garansi`, `hpp_penjualan_garansi`, `diskon_penjualan_garansi`, `biaya_tambahan_garansi` di service. Baris normal service **tidak dihapus**; hanya "tambahan garansi"-nya yang dibersihkan bila dilepas.
- **Pembayaran garansi** (`insert_pembayaran_garansi`): bank dibatasi ke scope unit, transfer tidak boleh melebihi sisa tagihan, tunai dibatasi sisa (`min(cash, sisa - bank)`). Menulis `bayar_garansi`, `bayar_tunai_garansi`, `total_service_garansi`, dst.

### 3.5 DP & Refund
- **Pencatatan DP**: `ServiceDpService::catatDp()` — insert `service_dp` (status `aktif`) + jurnal template `dp_service_tunai`/`dp_service_bank` + proyeksi `service.dp_bayar`. DP 0 → `skip` (tanpa ledger/jurnal). Negatif → ditolak. Idempotensi via `kode_dp` unik.
- **Refund penuh** (`refund_dp_service`, route `dp_service/refund`): hanya untuk service belum dikerjakan (status 1/2/90), DP status `aktif`, belum ada refund. Dalam satu transaksi: cek pembayaran lain (harus nihil), retur sparepart (hard delete invoice service), batalkan ticket (status→90), insert `kas_keluar` (kategori `REFUND`, akun PDM `2010302000`), jurnal pembalik `refund_dp_service_tunai/bank`, flag refund di ledger. **Penerimaan DP asal TIDAK dihapus** (tetap `aktif` sebagai histori kas masuk; `refund_idkas_keluar` jadi jejak).
- **Sumber saldo DP**: `statusSumberDp()` → `ledger` (ada baris `service_dp`) / `historis` (kolom `dp_bayar` lama) / `none`. Jika ledger ada tapi nol, tidak fallback ke kolom historis (dicegah).
- **Koreksi salah catat** (`koreksi_dp_service`, route `dp_service/koreksi` POST `['auth','csrf']` → `ServiceDpService::koreksiDp()`): hanya Admin Root (`ID_JABATAN = 1`). Koreksi `metode`/`bank_idbank`/`referensi`/`jumlah` tanpa membuat kas masuk/keluar baru. Dalam satu transaksi: `FOR UPDATE` DP + service, tolak DP non-`aktif`/sudah direfund, validasi rekening via `BankRekeningValidator::validateUntukUnit()`, tolak nominal yang melebihi `total_service` (DP lain + pembayaran non-DP ikut dihitung), update `service_dp`, tulis ulang dua baris jurnal DP (akun debit tunai/bank sesuai template, nominal debit/kredit = nominal baru, tanggal tetap), proyeksi ulang `service.dp_bayar`, lalu insert audit `service_dp_koreksi`. Nominal terkunci setelah `status_service = 4`; metode/rekening masih boleh. Tutup Kasir yang sudah tersimpan tidak dihitung ulang.

### 3.6 Pembatalan & Hapus
- `Service::cancel_service`: hanya jika `status_service < 4`; menolak service yang sudah refund DP; menghapus service + `service_kerusakan` + `service_sparepart` + ledger DP + jurnal DP dalam satu transaksi.
- `ModelService::hardDeleteService` (`delete_service`): menghapus service, detail/penjualan tertaut, `service_sparepart`, `service_kerusakan`, `pembayaran_bank` (referensi service_baru/garansi_0/garansi_1), jurnal `service`, `proses_service`, ledger DP + jurnal DP. **Ditolak bila service punya refund DP** (`hasRefundedDp`).

### 3.7 Sparepart Keluar
- `update_sudah_diambil` menyatukan baris penjualan (sumber `penjualan`) + sparepart klaim (sumber `klaim`, dari `jumlah_tambahan_garansi`).
- `edit_sparepart_keluar` / `delete_sparepart_keluar`: khusus admin root; memakai `SparepartKeluarHardDeleteService::hapusDetail()`.

---

## 4. Business Rules

### (a) Fakta terverifikasi (dengan bukti)
| Aturan | Bukti |
|---|---|
| Auto-no `SRV{unit}{Ymd}{3digit}` per unit per hari | `Service::insert_service` L193–207 |
| Ticket aktif per sesi: hanya satu service status 1/2/3 boleh berjalan | `Service::insert_service` L136–146 |
| DP negatif ditolak; DP 0 skip tanpa ledger/jurnal | `ServiceDpService::catatDp` L147–159; `tests/unit/ServiceDpLedgerTest` |
| `kode_dp` unik → idempotensi (status `duplikat`) | `ServiceDpService::catatDp` L164–171; `ModelServiceDp::kodeDpSudahAda` |
| DP transfer wajib bank terdaftar; metode hanya `tunai`/`transfer` | `ServiceDpService::validasi` L709–735 |
| Jurnal DP wajib seimbang & nominal sesuai (insertJurnal tak memberi sinyal) | `ServiceDpService::pastikanJurnalSeimbang` L752–777 |
| Ledger DP + jurnal dalam transaksi yang sama dgn service (rollback pemanggil membatalkan) | komentar `ServiceDpService` L29–34; `tests/unit/ServiceDpLedgerTest::testLedgerDanJurnalIkutTransaksiPemanggil` |
| Garansi berakhir = `tanggal_selesai + garansi_hari hari` (inklusif) | `GaransiClaimService::warrantyEnd` |
| Klaim hanya jika status=4, belum diklaim, dan hari ini <= akhir garansi | `GaransiClaimService::isClaimable` |
| Klaim menolak jika sudah ada riwayat klaim | `StatusGaransi::claim_garansi` L336–342 |
| Catatan klaim wajib (non-kosong) | `StatusGaransi::claim_garansi` L316–319; `GaransiClaimSaveTest::testCatatanKosongTidakMenyimpanKlaim` |
| Qty sparepart klaim tidak boleh melebihi stok | `StatusGaransi::simpanSparepartKlaim` L840–842; `GaransiClaimSaveTest::testQtyMelebihiStokTidakMenyimpanKlaim` |
| Klaim tidak mengubah service asli (status, tanggal selesai, total asli) | `StatusGaransi::claim_garansi` L353–356; `GaransiClaimSaveTest` |
| Sparepart klaim `status_retur=tidak_bisa` → simpan `hpp_omset_garansi` + `tanggal_hpp_klaim` | `StatusGaransi::simpanSparepartKlaim` L849–856; `GaransiClaimSaveTest::testKlaimTidakBisaRetur...` |
| Barang rusak klaim tidak mengurangi stok (tabel terpisah) | `2026-10-23-000400_CreateBarangRusakClaim.php` L8–9 (komentar) |
| Stok view dikurangi `jumlah_tambahan_garansi` untuk service terkiklaim | `2026-10-23-000500_StokBarangKurangiSparepartGaransi.php` (view `stok_barang`) |
| Sparepart service → dibuatkan invoice `penjualan` (tanpa pembayaran, keterangan "Belum Lunas") | `Service::buatPenjualanDariService` |
| Sebelum tulis ulang sparepart, invoice lama dihapus (atau adopted bila unik & cocok) | `ServiceSparepartPenjualanSyncService::hapusTransaksiLama`; `tests/unit/ServiceSparepartPenjualanSyncTest` |
| Hard delete detail sparepart: bila baris terakhir → hapus invoice + jurnal; else recalc total | `SparepartKeluarHardDeleteService::hapusDetail` |
| Refund DP: hanya status service 1/2/90; tolak bila ada pembayaran lain | `ServiceDpService::refund` L280–302; `tests/unit/ServiceDpRefundTest` |
| Refund menulis kas keluar (kategori REFUND, akun PDM 2010302000) + jurnal pembalik, service→90 | `ServiceDpService::refund` L429–461; `ServiceDpRefundTest` |
| Service yang sudah refund DP tidak bisa dihapus permanen | `ModelService::hasRefundedDp`; `hardDeleteService` L110–113; `ServiceDpRefundTest::testRefundPenuh...` |
| Koreksi DP: Admin Root saja, nominal > 0, audit wajib, jurnal & proyeksi ikut berubah, nominal terkunci setelah service diambil | `ServiceDpService::koreksiDp`; `tests/unit/ServiceDpKoreksiTest` (10 test) |
| Pengambilan menulis jurnal (service): Dr kas, Dr PDM, Cr pendapatan; idempotensi berbasis jurnal | `Riwayat_Service::update_sudah_diambil` L1268–1329; `tests/ServicePengambilanDpTest` |
| Settlement tidak menghitung ulang DP intake sebagai kas (hindari dobel) | `ServicePengambilanDpTest::testPengambilanDpLedgerTidakMenambahKasGanda` |
| `harus_dibayar` di alur aktif adalah GROSS; jalur NET dinonaktifkan | `Routes.php` L186–193; `ServicePembayaranRouteGuardTest` |
| Hapus service via `delete_service` khusus jabatan 1 | `Riwayat_Service::delete_service` L1397–1403 |
| Cross-unit view hanya role [0,1,34,40] | `GaransiClaimService::canSeeAllUnits` |
| Menu DP Service (id 10150) diberikan ke jabatan [0,1,2,34,35,38,40,41,47] | `2026-10-14-000100_AddDpServiceMenu.php` |
| `status_service` 4 = sudah diambil/selesai; 90/91 = dibatalkan | `ModelService::ProsesServiceDibatalkan` (status 90/91), `ServiceBisaDiambil` (3), `ServiceSudahDiambil` (4) |

### (b) Inferensi (disimpulkan dari struktur kode, bukan dinyatakan eksplisit di satu tempat)
| Inferensi | Dasar |
|---|---|
| Pemakaian sparepart **normal** service langsung memotong stok melalui invoice penjualan (insert detail) | `Service::buatPenjualanDariService` → `DetailPenjualanModel::insert_detail`; view `stok_barang` mengurangi `detail_penjualan.jumlah` |
| Pemakaian sparepart **garansi/klaim** TIDAK melalui invoice penjualan; stok dipotong hanya melalui view agregat | komentar `StatusGaransi::insert_sparepart_garansi` L639–641 ("shortcut: pemakaian sparepart garansi belum membuat mutasi stok keluar"); `StokBarangKurangiSparepartGaransi` |
| Ada dua "mesin" pembayaran service: GROSS (`Service::insert_pembayaran`, aktif) dan NET (`Riwayat_Service::insert_pembayaran`, nonaktif) | `Routes.php` L186–193 + kedua handler |
| DP bisa dicatat di dua tahap: saat intake (`insert_service`) dan saat tahap pembayaran (`insert_pembayaran`), keduanya lewat `catatDp` | `Service::insert_service` L265–276; `Service::insert_pembayaran` L681–704 |
| `service.dp_bayar` kini proyeksi (SUM DP aktif), bukan sumber otoritatif untuk service baru | `ModelServiceDp` L8–12; `ServiceDpService::proyeksiDpBayar` |
| Klaim pada dasarnya "gratis" (harga & sub_total garansi = 0 saat klaim murni) | `simpanSparepartKlaim` L861–864; `GaransiClaimSaveTest` |
| Jurnal pengambilan dan jurnal DP adalah dua set jurnal terpisah (referensi `service` vs `service_dp`) | `update_sudah_diambil` (service) vs `catatDp` (service_dp) |

### (c) Ketidakpastian / yang perlu verifikasi lanjut
| Topik | Alasan |
|---|---|
| Manual "shortcut" klaim: sparepart garansi **belum** membuat mutasi stok keluar (belum ada baris `detail_mutasi`) — hanya view yang mengurangi `stok_akhir`. Berdampak pada `total_mutasi_keluar` & kartu stok. | komentar `StatusGaransi::insert_sparepart_garansi` L639–641 |
| `search_sparepart_ajax` memfilter kode `sprt`/`acc` dan `idkategori=3` (`getSparepart`); definisi "sparepart" ganda antara kontroller vs model | `Service::search_sparepart_ajax` vs `ModelStokBarang::getSparepart` |
| Alur legacy `update_service_pelanggan`/`detail_service` masih men-`update` `dp_bayar` secara langsung? Tidak — di `StatusGaransi::update_service_pelanggan_garansi` kolom DP dikomentari; di `update_service_pelanggan` tidak dikirim DP. Potensi ketidakselarasan dengan ledger. | `StatusGaransi.php` L429 & L440 (komentar); `Riwayat_Service::update_service_pelanggan` |
| `hardDeleteService` menghapus `detail_penjualan`/`penjualan` **tanpa** lewat `SparepartKeluarHardDeleteService` dan **tanpa** jurnal penjualan? (menghapus jurnal `service` saja). Verifikasi apakah invoice penjualan service punya jurnal sendiri yang ikut terhapus. | `ModelService::hardDeleteService` L157–181 |
| Route `status_service/(:num)` tanpa auth — apakah ini intentional public check-status? | `Routes.php` L218 |
| Migrasi `BackfillHppSparepartKlaim` mengisi HPP 0 → HPP master; HPP 0 setelahnya dianggap "pilihan sengaja" | `2026-10-24-000100_BackfillHppSparepartKlaim.php` |

---

## 5. Validasi & Permission/Role

### Validasi
| Konteks | Validasi | Bukti |
|---|---|---|
| Intake | pelanggan wajib ada; DP non-negatif; unit valid bila DP>0 | `Service::insert_service` L173–184 |
| Kerusakan | minimal 1 fungsi dipilih | `Service::insert_kerusakan` |
| Sparepart | id>0; item diduplikasi dicek; sparepart klaim yang sudah diretur (`retur_klaim_item`) ditolak | `Service::insert_sparepart`; `returLockedSparepartIds` |
| Pembayaran | service harus ada; cek lunas (`sisaTagihan===0`); tolak kelebihan tanpa `koreksi_pembayaran=1`; batasi tunai/bank sesuai `metode_bayar` | `Service::insert_pembayaran` L600–677 |
| Klaim | catatan wajib; belum diklaim; claimable; unit sesuai; stok cukup | `StatusGaransi::claim_garansi`, `simpanSparepartKlaim` |
| Refund | alasan wajib ≤120 char; referensi ≤100; DP valid; status service 1/2/90; tanpa pembayaran lain; bank asal valid untuk transfer | `ServiceDpService::refund` L243–357 |
| DP transfer | bank wajib & terdaftar | `ServiceDpService::validasi` |

### Permission / Role
| Aksi | Role | Bukti |
|---|---|---|
| Semua halaman service/garansi/dp | login (filter `auth`) | `Routes.php` |
| `delete_service`, edit/hapus sparepart keluar | Admin Root (jabatan 1) | `Riwayat_Service::delete_service`, `edit_sparepart_keluar`, `delete_sparepart_keluar` |
| Lihat seluruh unit (filter lintas unit) | jabatan [0,1,34,40] (Admin Root, Manager, Finance, SPV) | `GaransiClaimService::canSeeAllUnits` |
| Navigasi unit lintas unit | hanya `is_operational=1` | `Riwayat_Service::sparepartUnitScope`, `StatusGaransi::index`, `proses_service` |
| Non-HO selalu terkunci ke unit sesi | `ID_UNIT` dipaksa | `riwayat_service_ajax`, `proses_service_ajax`, `dp_service_ajax`, `garansi_service_ajax`, `claim_garansi` |
| Menu DP Service | jabatan [0,1,2,34,35,38,40,41,47] | `AddDpServiceMenu` |
| Ruang cross-unit (H0) di `Riwayat_Service::index2` | `isHO = jabatan 1/0/42` | `index2` L149 |

---

## 6. Perubahan Database / Transaksi / Efek Samping

### Skema baru (migrasi)
| Migrasi | Tabel/kolom |
|---|---|
| `CreateServiceDpLedger` | `service_dp` (unik `kode_dp`, index service/status/unit/tanggal) |
| `AddServiceDpRefund` | kolom refund di `service_dp`; template jurnal `refund_dp_service_tunai/bank` |
| `AddServiceLinkToPenjualan` | `penjualan.service_idservice` + index |
| `AddCatatanToRiwayatClaimGaransi` | `riwayat_claim_garansi.catatan` |
| `CreateBarangRusakClaim` | `barang_rusak_claim` |
| `StokBarangKurangiSparepartGaransi` | view `stok_barang` (mengurangi `jumlah_tambahan_garansi`) |
| `BackfillHppSparepartKlaim` | backfill `hpp_penjualan_garansi` |

### Transaksi
| Alur | Ruang transaksi | Bukti |
|---|---|---|
| Intake + DP | `Service::insert_service` `transStart/transComplete`; `catatDp` ikut transaksi yang sama | L239–294 |
| Sparepart + sinkronisasi invoice | `Service::insert_sparepart` `transBegin/transCommit` | L369–465 |
| Pembayaran | `Service::insert_pembayaran` `transStart/transComplete` + `FOR UPDATE` baris service | L597–771 |
| Klaim | `StatusGaransi::claim_garansi` `transBegin/transCommit` + `FOR UPDATE` | L321–377 |
| Pembayaran garansi | `insert_pembayaran_garansi` `transBegin` + `FOR UPDATE` | L680–807 |
| Refund | `ServiceDpService::refund` `transBegin/transCommit` (intern) | L260–510 |
| Cancel service | `Service::cancel_service` `transBegin` | L884–935 |
| Hard delete service | `ModelService::hardDeleteService` `transBegin` | L123–204 |
| Sparepart hard delete | `delete_sparepart_keluar` `transBegin` | L1706–1734 |

### Efek samping
- **Ledger DP**: insert `service_dp` + jurnal `service_dp` + proyeksi `service.dp_bayar`.
- **Sinkronisasi ke penjualan**: setiap simpan sparepart service → hapus invoice lama (via `ServiceSparepartPenjualanSyncService`) → buat invoice baru (`penjualan` + `detail_penjualan`), `bayar=0`, keterangan "Belum Lunas".
- **Potong stok sparepart normal**: melalui `detail_penjualan` (view `stok_barang`).
- **Potong stok sparepart garansi**: tidak via invoice; hanya via agregat view `jumlah_tambahan_garansi`.
- **Hard delete**: `SparepartKeluarHardDeleteService::hapusDetail` → hapus detail, jurnal, invoice bila baris terakhir, atau recalc total invoice.
- **Refund**: `kas_keluar` + jurnal pembalik + retur seluruh sparepart service (hard delete invoice) + batalkan ticket (status 90).
- **Pengambilan**: jurnal `service` (kas/PDM/pendapatan); idempotensi berbasis jurnal.

---

## 7. Relasi Antar-Modul

| Modul | Relasi | Bukti |
|---|---|---|
| **Penjualan** | Sparepart service membuat/menghapus `penjualan` + `detail_penjualan` (via `..._idservice`); laporan Sparepart Keluar menggabung `sumber=penjualan` & `sumber=klaim` | `buatPenjualanDariService`, `ServiceSparepartPenjualanSyncService`, `sparepartKeluarServerSide` |
| **Stok** | `stok_barang` adalah VIEW yang agregat dari pembelian/penjualan/mutasi/retur/rusak + `..._tambahan_garansi` | `StokBarangKurangiSparepartGaransi` |
| **Kas/piutang** | DP → kas masuk (jurnal `service_dp`); refund → `kas_keluar`; settlement → jurnal `service`. `harus_dibayar` GROSS konsumsi TutupKasir (`dpServiceSelesai`) | `ServiceDpService`, `update_sudah_diambil`, `ServicePembayaranRouteGuardTest` |
| **Jurnal** | template `dp_service_tunai/bank`, `refund_dp_service_tunai/bank`, `pembayaran_service_tunai/bank` | `ServiceDpService::TEMPLATE/TEMPLATE_REFUND`, `AddServiceDpRefund` |
| **Pelanggan** | `service.pelanggan_id_pelanggan`; update domisili ikut service | `insert_service`, `update_service_pelanggan` |
| **Barang rusak** | `barang_rusak` (inventori) vs `barang_rusak_claim` (klaim, tidak mengurangi stok) | `BarangRusak.php`, `ModelBarangRusakClaim` |
| **Laba** | `getServiceWithLaba` memakai `service_sparepart.hpp/harga/diskon` (normal & garansi); HPP klaim loss | `ModelService::getServiceWithLaba`, `RiwayatLabaService` |

---

## 8. Bukti Test

| Test | Apa yang dijamin |
|---|---|
| `tests/unit/ServiceDpLedgerTest.php` | ledger DP, jurnal seimbang, proyeksi dp_bayar, idempotensi kode, DP 0/negatif, atomik dgn transaksi pemanggil, tolak bank/metode/unit/service invalid |
| `tests/unit/ServiceDpRefundTest.php` | refund penuh, kas keluar + jurnal, service→90, tolak refund ganda, tolak bila ada pembayaran lain, retur sparepart otomatis, rollback kegagalan |
| `tests/unit/ServicePembayaranRouteGuardTest.php` | route NET nonaktif (404), GROSS aktif, lunas menolak bayar ulang, cicilan menambah, koreksi kelebihan, metode transfer mengabaikan tunai sisa |
| `tests/ServicePengambilanDpTest.php` | jurnal pengambilan seimbang, DP ledger vs historis, tidak dobel, idempotensi (status 4 / jalur 90→2→4) |
| `tests/GaransiClaimSaveTest.php` | simpan klaim, HPP asli, status_retur tidak_bisa, tolak klaim kedua/katatan kosong/stok kurang, baris normal tak hilang, tampil di sparepart keluar |
| `tests/GaransiClaimDetailPrefillTest.php` | prefill sparepart service ke tabel "Sparepart Rusak", modal pengganti, HPP/harga |
| `tests/GaransiServiceViewTest.php`, `tests/ProsesServiceViewTest.php`, `tests/RenderProsesServiceHtmlTest.php`, `tests/RiwayatServiceGaransiViewTest.php` | render view terkait |
| `tests/ServiceCancelDpTest.php`, `tests/ServiceIntakeDpTest.php`, `tests/ServiceDpBankDropdownRenderTest.php`, `tests/ServiceDpBankScopeTest.php` | cancel/intake dgn DP, scope bank |
| `tests/ServiceSparepartColumnRenderTest.php`, `tests/ServiceSparepartSearchDedupTest.php` | kolom & dedup pencarian sparepart |
| `tests/unit/ModelServiceDpListingTest.php`, `tests/unit/ModelServiceGaransiListingTest.php` | listing DP & garansi |
| `tests/unit/ServiceHardDeleteDpTest.php`, `tests/unit/ServiceSparepartPenjualanSyncTest.php`, `tests/unit/GaransiClaimServiceTest.php` | hard delete dgn DP, sinkronisasi invoice, aturan klaim |
| `tests/MigrationServiceDpTest.php`, `tests/StokBarangKurangiSparepartGaransiTest.php` | migrasi & view stok |

---

## 9. Gap / Konflik

1. **Route mengarah ke method yang tidak ada**: `GET /service_kerusakan` → `Service::kerusakan_table` dan `GET /service_sparepart` → `Service::sparepart_table` terdaftar di `Routes.php` L151–152 tetapi kedua method **tidak ada** di `app/Controllers/Service.php`. Akan menghasilkan error (atau dihandle autoRoute=off → 404). Perlu verifikasi apakah view masih memanggilnya.
2. **Dua handler pembayaran**: `Riwayat_Service::insert_pembayaran` (NET) masih ada sebagai kode tapi route-nya dinonaktifkan. Kode mati/duplikat; jika suatu saat diaktifkan, akan memicu undercount DP di TutupKasir (lihat komentar route).
3. **Klaim sparepart belum menulis mutasi stok** (shortcut L639–641): stok hanya dikurangi lewat view, sehingga `total_mutasi_keluar` dan kartu stok tidak mencatat pengeluaran stok klaim. Bila akuntansi stok garansi butuh jejak mutasi, ini celah.
4. **Potensi duplikasi definisi sparepart**: `search_sparepart_ajax` (kode `sprt`/`acc`) vs `ModelStokBarang::getSparepart` (`idkategori=3`). Bisa tidak konsisten.
5. **`hardDeleteService` menghapus invoice penjualan secara manual** tanpa memakai jalur `SparepartKeluarHardDeleteService` (tidak recalc/hapus jurnal penjualan). Verifikasi apakah jurnal penjualan ikut terhapus.
6. **Route publik `status_service/(:num)` tanpa auth**: paparan data status service kepada publik; pastikan memang disengaja (dipakai QR invoice).
7. **Ketidakselarasan legacy vs ledger DP**: `service.dp_bayar` dulunya ditulis langsung; sekarang proyeksi. Service lama tanpa baris ledger memakai `historis`. Perlu kesepakatan migrasi data lama.
8. **`update_status_proses`** memaksa `status_service=2` tanpa cek status asli; berpotensi menurunkan status 3→2.

---

*Dokumen ini murni hasil analisis baca-saja; tidak ada file source/DB/config yang diubah.*
