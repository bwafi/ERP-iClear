# Audit Mutasi Stok → Hutang/Piutang → KPI Finance

Tanggal: 2026-10-19
Ruang lingkup: alur mutasi antar unit, pembentukan hutang/piutang, pelunasan, dan dampaknya ke KPI Finance Hutang & Piutang.
Metode: baca kode + verifikasi data di database lokal `erp_local`. Audit awal read-only; perbaikan dicatat pada bagian temuan.

---

## Jawaban singkat

1. **Mutasi jadi Hutang/Piutang hanya saat tombol “Terima”** — bukan saat mutasi dibuat. `MutasiStok::terima()` memanggil `ModeKasBank::buatHutangPiutangDariMutasi()`.
2. **Pelunasan bisa dimulai dari detail Hutang/Piutang, diposting tetap satu jalur.** Form “Catat Pelunasan Antar Unit” di `/hutangpiutang/detail/<id>` mengirim ke `KasBank::saveAntarUnit()` (`/kas_bank/antar-unit/save`). Satu pembayaran melunasi sisi hutang *dan* sisi piutang sekaligus, atomik, dan bisa di-reversal. Form lama di Kas & Bank tetap ada.
3. **KPI Finance “Hutang & Piutang” sekarang ikut menilai mutasi antar unit.** Sebelumnya KPI hanya membaca tabel `pembelian` dan `piutang`; sekarang `MutasiTimelinessCalculator` membaca sisi hutang `hutang_piutang` yang `sumber_tipe='mutasi_unit'`, `scope='active'`, dan `deleted=0`. Skor Hutang, Piutang, dan Mutasi digabung rata-rata; dashboard juga sudah memakai satu definisi total.

---

## Alur mutasi → H/P

| Langkah | Lokasi | Yang terjadi |
|---|---|---|
| Buat mutasi | `MutasiStok.php:420` | Header + detail, `status=0`. **Stok belum bergerak**; view `stok_barang` hanya menghitung mutasi `status=1`. |
| Terima | `MutasiStok.php:229` | Cek unit tujuan, update `status=1` + `tanggal_terima`, lalu buat pasangan H/P. **Stok baru berpindah di sini.** |
| Buat pasangan | `ModeKasBank.php:672` | Pengirim dapat **piutang**, penerima dapat **hutang**, saling `lawan_unit_id`, `sumber_tipe='mutasi_unit'`. |
| Nilai | `ModeKasBank.php:654` | `Σ jumlah_terima × harga_mutasi`; fallback ke `hpp_barang`. |
| Tanggal & jatuh tempo | `ModeKasBank.php:727` | Tanggal = `tanggal_kirim`; jatuh tempo = `tanggal_terima + 3 hari`. |

Tidak ada jurnal otomatis untuk mutasi maupun pasangan H/P-nya.

## Cara melunasi

1. Login role yang boleh transaksi: jabatan `0,1,2,34,35,40,41,47` (`KasBank.php:82`).
2. Buka **Kas & Bank → Antar Unit** (`/kas_bank/antar-unit`).
3. Pilih unit yang punya hutang → pilih tagihan → pilih rekening “Bayar DARI” dan “Dibayar KE” → simpan.
4. Sistem menulis 2 leg `transaksi_kas_bank`, 2 baris `pembayaran_hutang_piutang`, lalu mengurangi `sisa` hutang **dan** piutang pasangannya secara atomik (`KasBank.php:2547`, `ModeKasBank.php:820`).
5. Rekening fisik sama → memakai leg `antar_unit_atribusi`; uang tidak berpindah, hanya hak saldo unit.
6. Pembatalan: `reversalAntarUnit()` (`KasBank.php:2907`) atau `reversalAtribusi()` (`KasBank.php:2845`).

Yang **tidak bisa**: memakai form pembayaran biasa `/hutangpiutang/bayar`. Service menolak `mutasi_unit` karena bukan `AUTHORITATIVE_SUMBER` (`HutangPiutangService.php:640`). Pelunasan antar unit harus lewat form khusus di detail H/P atau menu Kas & Bank → Antar Unit.

## Dampak ke KPI

| KPI / dashboard | Sumber angka | Mutasi masuk? |
|---|---|---|
| `FIN_HUTANG_PIUTANG` (bobot 10) | `HutangTimelinessCalculator` → `pembelian`; `PiutangTimelinessCalculator` → `piutang`; `MutasiTimelinessCalculator` → `hutang_piutang` sisi hutang `mutasi_unit` | **Ya** |
| Dashboard Keuangan | `getRingkasan()` — `SUMBER_DIHITUNG`, termasuk `mutasi_unit` aktif | **Ya** |
| Dashboard Hutang Piutang — kartu total | `getRingkasan()` — bucket `mutasi_unit` aktif | **Ya** |
| Dashboard Hutang Piutang — kartu status | `countJatuhTempo()` — `SUMBER_DIHITUNG`, scope sama dengan total | **Ya** |

Bukti dari DB lokal `erp_local`:

- 62 mutasi: 50 diterima, 12 belum.
- 48 pasang H/P = 96 baris; **44 pasang (88 baris) legacy** dan **4 pasang (8 baris) aktif** setelah backfill scope.
- **0 pembayaran** antar unit — belum ada satu pun yang dilunasi.
- 44 pasang pra-cut-off bertanggal **sebelum 2026-10-06** sudah menjadi `scope='legacy'`; 4 pasang sesudah cut-off tetap `active`.
- **Update 2026-10-20:** 10 mutasi September yang sudah diterima secara fisik di-backfill dari `status=0` ke `status=1`; 20 baris H/P legacy baru (10 pasang, Rp6.129.161 per sisi). Sisa 3 mutasi `status=0` adalah mutasi Oktober (83 qty). Backup: `mutasi_backup_20261020_060623` dan `hutang_piutang_backup_20261020_060623`. Skrip permanen: `php app/Scripts/backfill_mutasi_legacy.php --month=YYYY-MM [--execute]` (default dry-run).
- 11 grup nomor nota duplikat (30 baris); **19 baris sudah di-renumber** dan unique index `uniq_mutasi_no_nota` terpasang.
- 5 baris stok negatif.

---

## Temuan audit

### Harus diperbaiki

**1. Terima ulang setelah batal tidak membuat H/P lagi — dokumen hilang. — ✅ DIPERBAIKI**
`batalTerima()` meng-soft-delete H/P (`MutasiStok.php:368`), tetapi pengecekan “sudah ada” di `ModeKasBank.php:682` tidak memfilter `deleted`. Saat diterima ulang, fungsi mengembalikan `skipped`, dan `terima()` mengabaikan hasil itu (`MutasiStok.php:262-264`) lalu tetap menampilkan pesan sukses. Akibatnya mutasi berstatus diterima tanpa H/P aktif.
**Perbaikan:** `buatHutangPiutangDariMutasi()` sekarang membedakan baris aktif dan ter-soft-delete. Baris `deleted=1` **dihidupkan kembali** (`deleted=0`, reset `total/total_dibayar/sisa/status/tanggal/jatuh_tempo/keterangan`) memakai id lama, karena unique index `(sumber_tipe,sumber_id,jenis)` menolak insert baru. Hanya pasangan yang dua-duanya aktif yang menghasilkan `skipped`.
**Test:** `KasBankTest::testBuatHutangPiutangDariMutasiMenghidupkanDokumenYangDibatalkan`, `KasBankControllerTest::testTerimaUlangSetelahBatalMembuatHutangPiutangAktif`.

**2. `terima()` mengabaikan kegagalan pembuatan H/P. — ✅ DIPERBAIKI**
Jika total mutasi 0 (harga dan HPP kosong) atau insert gagal, `buatHutangPiutangDariMutasi()` mengembalikan `failed`, tetapi transaksi tetap commit dan mutasi tetap “diterima”. Ada 2 mutasi diterima tanpa detail dan tanpa H/P (id 36, 73).
**Perbaikan:** `terima()` melempar `RuntimeException` bila status bukan `inserted`/`skipped`, sehingga `catch` melakukan `transRollback()` dan mutasi tetap `status=0`. `insert()`/`update()` yang gagal juga sekarang dikembalikan sebagai `failed` (sebelumnya insert gagal tetap dianggap sukses).
**Test:** `KasBankControllerTest::testTerimaMutasiNilaiNolTidakMenandaiDiterima`.

**3. Scope cut-off tidak diterapkan saat membuat H/P mutasi. — ✅ DIPERBAIKI**
`ModeKasBank.php:737-791` insert tanpa kolom `scope`, sehingga default DB `active` — termasuk untuk mutasi bertanggal sebelum cut-off. Migrasi `ApplyFinanceCutoffBackfill` hanya menandai data yang sudah ada saat itu; baris mutasi dibuat setelahnya. Dampak: Rp86,9 juta utang antar unit lama dihitung sebagai utang aktif/terlambat.
**Perbaikan:** jalur insert/revive sekarang memakai `FinanceScopeService::isLegacyTransaction()` — tanggal sebelum periode mulai (`< 2026-10-06`, termasuk 5 Okt) → `scope='legacy'` + meta `LEGACY_CUTOFF`; tanggal periode baru → `active`. Migration idempotent `2026-10-19-000200_BackfillMutasiUnitScope` menandai baris lama yang masih `active`.
**Test:** `KasBankTest::testBuatHutangPiutangMutasiMemakaiScopeSesuaiCutoff`, `KasBankTest::testBackfillScopeMutasiUnitMenandaiPreCutoffLegacy`.
**Dampak data lokal:** migration mengubah **44 baris hutang + 44 baris piutang** (44 pasang, Rp86.920.080 per sisi) dari `active` → `legacy`. Sudah dijalankan di `erp_local` pada batch 41.

**4. Dua dashboard memakai definisi “Total Hutang/Piutang” yang berbeda. — ✅ DIPERBAIKI**
`HutangPiutangService::getRingkasan()` (`:998`) tidak memasukkan `mutasi_unit`, sedangkan `countJatuhTempo()` (`:1132`) menghitung semua baris; `DashboardKeuangan.php:94-122` menghitung semua. Hasilnya angka total dan angka status saling bertentangan.
**Perbaikan:**
- `getRingkasan()` sekarang punya bucket khusus `mutasi_unit` (`total_hutang_antar_unit`, `total_piutang_antar_unit`, beserta jumlah transaksi) dan memasukkannya ke `total_hutang`/`total_piutang`.
- `countJatuhTempo()` memakai `SUMBER_DIHITUNG` — daftar sumber yang sama dengan total — sehingga status tidak menghitung baris yang tidak ada di total.
- `DashboardKeuangan` tidak lagi menjumlah registry sendiri; ia memanggil `getRingkasan()` sehingga kedua dashboard memakai satu definisi.
- Dashboard Hutang Piutang menampilkan dua kartu baru: **Total Hutang Antar Unit** dan **Total Piutang Antar Unit**.
**Test:** `KasBankControllerTest::testRingkasanMemasukkanMutasiAntarUnitSesuaiStatus`, `KasBankControllerTest::testStatusRingkasanHanyaDariSumberYangDihitung`.

**5. Nomor nota mutasi duplikat dan riwayat menggabungkan transaksi berbeda. — ✅ DIPERBAIKI**
`MutasiStok.php:461` memakai `like('DATE(tanggal_kirim)', date('ymd'))` — `'2026-10-19'` tidak pernah cocok dengan `'261019'`, jadi urutan selalu `001`. Selain itu `$lastMutasi['no_nota_mutasi']` mengakses object sebagai array (fatal jika suatu saat query cocok), dan tidak ada unique index. `riwayat_mutasi.php:4` mengelompokkan berdasarkan nomor nota → 30 baris tergabung salah.
**Perbaikan:** `insert()` sekarang memakai `simpanMutasi()`. Header disimpan dengan placeholder unik, lalu nomor final dibentuk dari `insertID()` (`MTS{unit}{ymd}{id}`). Query `DATE LIKE` dan akses object-sebagai-array dihapus. Nota baru tidak lagi memakai urutan `001` yang selalu sama.
**Test:** `KasBankControllerTest::testSimpanMutasiMenyimpanHeaderDetailDanNomorUnik`, `testSimpanMutasiRollbackSaatDetailGagal`, `testInsertMutasiRedirectSetelahSimpan`.
**Data lama:** migration `2026-10-20-000100_RenumberDanUnikkanNoNotaMutasi` mempertahankan nomor pertama tiap grup dan memberi suffix `-R2`, `-R3`, dst. pada 19 baris lainnya; 11 nomor asli tetap. Unique index `uniq_mutasi_no_nota` sudah terpasang setelah backup `mutasi_backup_20261020` (62 baris). Verifikasi: total 62, duplikat 0, suffix 19.
**Test migration:** `MutasiNoNotaUniqueTest::testRenumberDanPasangUniqueIndex`, `testNomorDuplikatDitolakSetelahIndex`, `testRenumberMenganggapBedaHurufDanSpasiSebagaiDuplikat`, `testSuffixBertabrakanDenganNomorYangSudahAda`, `testStringKosongMenjadiNull`, `testSuffixTerlaluPanjangDitolakTanpaMengubahData`. Preflight menolak suffix >45 karakter sebelum menulis, dan string kosong dinormalisasi menjadi NULL agar unique index tidak gagal.

**6. Siapa pun yang login bisa membuat mutasi dari unit mana pun. — ✅ DIPERBAIKI**
`MutasiStok::insert()` tidak mengecek unit pengirim terhadap scope user; `AuthFilter` hanya memeriksa `logged_in`. Daftar produk juga memakai stok unit sesi, bukan unit pengirim yang dipilih. Tidak ada validasi stok → 8 baris stok negatif.
**Perbaikan:**
- `insert()` sekarang memanggil `validasiInsert()` sebelum header disimpan.
- Unit pengirim dan penerima harus ada dan berbeda.
- Role non-lintas hanya boleh mengirim dari unit sendiri; role lintas `0,1,2,34` tetap boleh dari unit mana pun, sama seperti `terima()`.
- `jumlah_kirim` wajib > 0; stok dicek dari `stok_barang` **unit pengirim**, diakumulasi per barang.
- Daftar produk di form masih memuat semua barang, tetapi tidak lagi menentukan stok/otorisasi — yang menentukan adalah validasi saat submit.
**Test:** `KasBankControllerTest::testInsertMutasiMenolakPengirimDiLuarUnitPengguna`, `testInsertMutasiLintasBolehMengirimDariUnitLain`, `testInsertMutasiMenolakStokPengirimKurang`, `testInsertMutasiMengizinkanStokPengirimCukup`.
**Batas:** cek stok dilakukan sebelum insert, bukan lock; dua request bersamaan masih bisa sama-sama lolos.

**7. Batal terima menghapus utang, tetapi stok tetap di penerima. — ✅ DIPERBAIKI**
View `stok_barang` (`2026-10-07-000001`, baris 71-77) menghitung semua `detail_mutasi` tanpa melihat `mutasi.status`. Jadi mutasi yang belum diterima sudah memindahkan stok; saat penerimaan dibatalkan, stok tetap pindah sementara H/P di-soft-delete.
**Perbaikan:** migration `2026-10-19-000400_StokBarangHanyaMutasiDiterima.php` membuat ulang view dengan `JOIN mutasi` dan `WHERE mutasi.status = 1`. Stok kini bergerak saat terima, dan otomatis kembali saat `batalTerima()` mengubah status ke 0. Pada 20 Okt 2026, 10 mutasi September yang sudah diterima secara fisik di-backfill ke `status=1`; sisa 3 mutasi Oktober masih `status=0` dan stoknya belum dihitung.
**Test:** `StokBarangHanyaMutasiDiterimaTest::testStokHanyaDihitungSetelahDiterima` dan `testBatalTerimaMengembalikanStok` — sebelum filter unit 1=85/unit 2=15, sesudah filter 95/5, lalu saat status diubah 1 → 85/15 dan saat dibatalkan 0 → 95/5. Migration sudah dijalankan di `erp_local` pada batch 41; definisi view terverifikasi memakai `mutasi.status = 1`.

**8. Nilai tagihan memakai jumlah kirim, stok masuk memakai jumlah terima. — ✅ DIPERBAIKI**
`nilaiDetailMutasi()` memakai `jumlah_kirim`; view stok penerima memakai `jumlah_terima`. Form mengizinkan keduanya berbeda, jadi penerima bisa ditagih barang yang tidak diterima. Di data sekarang semuanya sama, tapi risikonya nyata.
**Perbaikan:** `nilaiDetailMutasi()` sekarang memakai `jumlah_terima` (0 bila tidak ada), dengan harga `harga_mutasi` dan fallback `hpp_barang`. Item bukti cetak, rincian di Kas & Bank, modal terima, dan total form mutasi juga memakai `jumlah_terima` agar subtotal cocok dengan tagihan. Data lokal: 220 detail, 0 `jumlah_terima` null, 0 selisih dengan `jumlah_kirim` — jadi tidak ada nilai historis yang berubah.
**Test:** `KasBankTest::testNilaiDetailMutasiMemakaiJumlahTerima`, `testNilaiDetailMutasiTanpaJumlahTerimaBernilaiNol`; `KasBankControllerTest::testCetakBuktiHutangPiutangMutasiMemuatListBarang` sekarang memakai kirim 5/terima 2 dan memastikan total serta item cetak tetap 2. Fixture `BuatHutangPiutangDariMutasi` di `KasBankTest` dan `KasBankControllerTest` ikut diisi `jumlah_terima`.

### Sebaiknya diperbaiki

**9. Backfill membuat H/P untuk mutasi yang belum diterima. — ✅ DIPERBAIKI**
`BackfillKasBank.php:154` memilih semua `mutasi` tanpa filter `status=1`. Kalau command dijalankan, 12 mutasi yang belum diterima akan langsung punya utang.
**Perbaikan:** `backfillMutasi()` sekarang hanya memilih `status='1'`.
**Test:** `KasBankTest::testBackfillMutasiHanyaMemprosesStatusDiterima`.

**10. `insert()` tidak atomik dan pengecekan hasilnya salah. — ✅ DIPERBAIKI**
Header dan detail tidak dibungkus transaksi; `if ($result & $result2)` memakai bitwise AND dan hanya membaca hasil detail terakhir; PDF `exit` membuat flash sukses tak pernah tercapai. Ada 2 header tanpa detail.
**Perbaikan:** header + detail sekarang disimpan lewat `simpanMutasi()` dalam `transBegin/transCommit/transRollback`, dan setiap insert diperiksa. `insert()` redirect ke daftar mutasi setelah simpan; PDF `exit` dihapus. Nota tetap bisa dicetak lewat `cetak/invoice_mutasi/{id}`.
**Test:** `KasBankControllerTest::testSimpanMutasiRollbackSaatDetailGagal`, `testInsertMutasiRedirectSetelahSimpan`.

**11. Pembayaran antar unit rawan overpay saat concurrent. — ✅ DIPERBAIKI**
`saveAntarUnit()` membaca `sisa` tanpa `FOR UPDATE`, dan `terapkanPembayaranHP()` juga tidak mengunci baris. Dua user membayar bersamaan bisa sama-sama lolos cek.
**Perbaikan:** `terapkanPembayaranHP()` tidak lagi baca-lalu-tulis. `ModelHutangPiutang::kurangiSisaJikaCukup()` menjalankan `UPDATE ... WHERE sisa >= ? AND deleted = 0`; kalau baris sudah berubah, update 0 baris dan pembayaran gagal. `saveAntarUnit()` kemudian rollback seluruh leg.
**Test:** `KasBankTest::testTerapkanPembayaranHPMenolakSisaBasi`, `testTerapkanDanRestorePembayaranHP`.

**12. Sisi piutang tidak bisa memulai pelunasan, dan pembayaran hanya bisa dari modul Kas & Bank. — ✅ DIPERBAIKI (form langsung di detail H/P)**
Hanya unit yang berhutang (atau role lintas) yang bisa membayar. Unit pengirim hanya bisa menunggu, dan user harus pindah ke Kas & Bank → Antar Unit.
**Perbaikan:** detail H/P `mutasi_unit` (sisi hutang maupun piutang) menampilkan modal “Catat Pelunasan Antar Unit” berisi rekening pengirim/penerima, nominal, bukti, dan token. Form POST ke `KasBank::saveAntarUnit()` yang sudah ada, jadi ledger, guard saldo, atribusi hak unit, dan pengurangan sisa H/P tetap satu jalur — halaman ini tidak menduplikasi logika. `kembali_ke` hanya menerima path `hutangpiutang/detail/<id>` supaya tidak jadi open redirect. Otorisasi `saveAntarUnit()` tidak diubah: hanya unit penghutang/role lintas yang bisa membayar.
**Test:** `HutangPiutangDetailLinkTest`, `KasBankControllerTest::testAntarUnitSaveDariHalamanHpKembaliKeDetail`, `testAntarUnitKembaliKeLiarTidakJadiOpenRedirect`.

**13. `getPasangan()` tidak memfilter `deleted`. — ✅ DIPERBAIKI**
`ModelHutangPiutang.php:106`. Jika satu sisi ter-soft-delete, pembayaran bisa menempel ke baris mati.
**Perbaikan:** `getPasangan()` sekarang menambahkan `where('deleted', 0)`. Pasangan yang sudah dibatalkan menghasilkan `null`, sehingga alur bayar berhenti dengan pesan “Pasangan piutang tidak ditemukan” alih-alih menyentuh baris mati.
**Test:** `KasBankTest::testGetPasanganMengabaikanDokumenTerhapus`.

### Nice to have

- Komentar kolom `mutasi.status` di DB (`0=menunggu,1=kirim,2=terima`) tidak sesuai kode (`1=diterima`).
  **Perbaikan:** migration `2026-10-19-000300_FixMutasiStatusComment.php` mengubah komentar jadi `0 = menunggu, 1 = diterima` (idempoten; `down()` mengembalikan komentar lama). Sudah dijalankan di `erp_local` pada batch 41.
- `hitungStatus()` di `ModeKasBank` menduplikasi `statusFromSisa()` di service.
  **Perbaikan:** `statusFromSisa()` dijadikan `static`, `ModeKasBank::hitungStatus()` mendelegasikan ke sana. Perilaku restore pembayaran terverifikasi `KasBankTest::testTerapkanDanRestorePembayaranHP`.
- `CustomerSatisfactionTest` tidak mandiri; error `db_service`/`db_stok_barang`/`db_penjualan` hanya muncul bila tabel sisa test lain ada.
  **Perbaikan:** `setUp()` sekarang membangun schema sendiri (tanpa men-drop tabel bersama) dan `CustomerSatisfactionService` memakai `prefixTable()` pada SQL mentahnya. Dua failure yang tersisa sudah ada sejak sebelum audit: role SPV (40) memang tidak masuk `viewRoles`, dan teks “Rekap Bulanan” tidak ada di view.
- Tidak ada fitur void untuk mutasi `status=0`; 3 mutasi Oktober (83 qty kirim) masih menggantung, tetapi stoknya tidak lagi dihitung setelah migration view. 10 mutasi September sudah di-backfill ke `status=1` pada 20 Okt 2026.

---

## Verdict

Jalur happy-path **mutasi → terima → H/P → bayar antar unit → reversal** sudah rapi dan atomik. Masalah utamanya bukan di pembuatan pasangan, melainkan di **pembatalan/terima ulang**, **cut-off**, **konsistensi laporan**, dan **otorisasi pembuatan mutasi**.

KPI `FIN_HUTANG_PIUTANG` sekarang **mencakup utang antar unit**. Skor akhirnya adalah rata-rata dari tiga komponen yang tersedia: Hutang supplier, Piutang pelanggan, dan Mutasi Antar Unit. Sisi piutang mutasi tidak dihitung terpisah karena merupakan cermin transaksi yang sama; hanya sisi hutang (unit yang berutang) yang dinilai. Bobot antar tiga komponen masih sama (belum ada pembobotan bisnis terpisah). Tab **Hutang & Piutang** di Dashboard Finance juga menampilkan kartu dan rincian Mutasi Antar Unit agar angkanya tidak tersembunyi di balik skor gabungan.

---

## Yang tidak diperiksa

- Perbaikan #1–#6, #8–#13, dan tiga item "Nice to have" sudah diuji terarah. Full suite belum dijalankan ulang setelah perubahan ini; baseline failure lama (`s.idservice`, data test persisten, dan dua ekspektasi `CustomerSatisfactionTest`) masih ada dan tidak berasal dari perubahan ini.
- Data produksi tidak diperiksa; angka diambil dari database lokal `erp_local` per 2026-10-19.
- Migration backfill #3 dan view #7 sudah dijalankan di `erp_local` batch 41; saat audit hasilnya diverifikasi lewat query data (88 baris legacy, 8 baris aktif), lalu 10 pasang mutasi September di-backfill pada 20 Okt 2026.
- Cetak PDF, tampilan mobile, dan seluruh matriks role di luar route yang disebut belum diperiksa.
- Temuan #8 sudah diperbaiki memakai `jumlah_terima`. #5 sudah lengkap: kode, renumbering 19 baris, dan unique index. #7 sudah diperbaiki lewat migration view.
