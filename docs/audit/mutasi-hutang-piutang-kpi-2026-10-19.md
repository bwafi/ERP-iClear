# Audit Mutasi Stok → Hutang/Piutang → KPI Finance

Tanggal: 2026-10-19
Ruang lingkup: alur mutasi antar unit, pembentukan hutang/piutang, pelunasan, dan dampaknya ke KPI Finance Hutang & Piutang.
Metode: baca kode + verifikasi data di database lokal `erp_local`. Audit awal read-only; perbaikan dicatat pada bagian temuan.

---

## Jawaban singkat

1. **Mutasi jadi Hutang/Piutang hanya saat tombol “Terima”** — bukan saat mutasi dibuat. `MutasiStok::terima()` memanggil `ModeKasBank::buatHutangPiutangDariMutasi()`.
2. **Pelunasan bukan dari modul Hutang/Piutang.** Harus dari **Kas & Bank → Antar Unit** (`/kas_bank/antar-unit`). Satu pembayaran melunasi sisi hutang *dan* sisi piutang sekaligus, atomik, dan bisa di-reversal.
3. **KPI Finance “Hutang & Piutang” tidak terpengaruh mutasi.** KPI itu hanya membaca tabel `pembelian` dan `piutang`; baris `mutasi_unit` di registry `hutang_piutang` tidak dibaca sama sekali. Tapi dashboard keuangan lain menghitungnya — dan di situ ada inkonsistensi serius.

---

## Alur mutasi → H/P

| Langkah | Lokasi | Yang terjadi |
|---|---|---|
| Buat mutasi | `MutasiStok.php:420` | Header + detail, `status=0`. **Stok sudah bergerak** karena view `stok_barang` menghitung semua `detail_mutasi` tanpa melihat status. |
| Terima | `MutasiStok.php:229` | Cek unit tujuan, update `status=1` + `tanggal_terima`, lalu buat pasangan H/P. |
| Buat pasangan | `ModeKasBank.php:672` | Pengirim dapat **piutang**, penerima dapat **hutang**, saling `lawan_unit_id`, `sumber_tipe='mutasi_unit'`. |
| Nilai | `ModeKasBank.php:654` | `Σ jumlah_kirim × harga_mutasi`; fallback ke `hpp_barang`. |
| Tanggal & jatuh tempo | `ModeKasBank.php:727` | Tanggal = `tanggal_kirim`; jatuh tempo = `tanggal_terima + 3 hari`. |

Tidak ada jurnal otomatis untuk mutasi maupun pasangan H/P-nya.

## Cara melunasi

1. Login role yang boleh transaksi: jabatan `0,1,2,34,35,40,41,47` (`KasBank.php:82`).
2. Buka **Kas & Bank → Antar Unit** (`/kas_bank/antar-unit`).
3. Pilih unit yang punya hutang → pilih tagihan → pilih rekening “Bayar DARI” dan “Dibayar KE” → simpan.
4. Sistem menulis 2 leg `transaksi_kas_bank`, 2 baris `pembayaran_hutang_piutang`, lalu mengurangi `sisa` hutang **dan** piutang pasangannya secara atomik (`KasBank.php:2547`, `ModeKasBank.php:820`).
5. Rekening fisik sama → memakai leg `antar_unit_atribusi`; uang tidak berpindah, hanya hak saldo unit.
6. Pembatalan: `reversalAntarUnit()` (`KasBank.php:2907`) atau `reversalAtribusi()` (`KasBank.php:2845`).

Yang **tidak bisa**: bayar dari `/hutangpiutang/bayar`. Service menolak `mutasi_unit` karena bukan `AUTHORITATIVE_SUMBER` (`HutangPiutangService.php:640`), dan tombol bayar di detail H/P memang disembunyikan (`detail.php:97`). Tidak ada tautan dari detail H/P ke halaman Antar Unit.

## Dampak ke KPI

| KPI / dashboard | Sumber angka | Mutasi masuk? |
|---|---|---|
| `FIN_HUTANG_PIUTANG` (bobot 10) | `HutangTimelinessCalculator` → tabel `pembelian`; `PiutangTimelinessCalculator` → tabel `piutang` | **Tidak** |
| Dashboard Keuangan | `SUM(hutang_piutang.sisa)` semua `sumber_tipe`, `scope != legacy` | **Ya** |
| Dashboard Hutang Piutang — kartu total | Hanya `pembelian` + `AUTHORITATIVE_SUMBER` + `piutang_legacy` | **Tidak** |
| Dashboard Hutang Piutang — kartu status | Semua baris `hutang_piutang` | **Ya** |

Bukti dari DB lokal `erp_local`:

- 62 mutasi: 50 diterima, 12 belum.
- 48 pasang H/P = 96 baris, semuanya `belum_lunas`; masing-masing sisi **Rp 87.600.080**.
- **0 pembayaran** antar unit — belum ada satu pun yang dilunasi.
- 96 baris **terlambat**, tetapi dashboard H/P menampilkan total hutang **Rp 0** dan “Terlambat **96**”.
- 44 baris hutang (22 pasang) bertanggal **sebelum cut-off 2026-10-05** tetapi `scope='active'` — Rp **86.920.080** seharusnya `legacy` menurut kebijakan cut-off.
- 11 grup nomor nota duplikat, melibatkan **30 baris**.
- 8 baris stok negatif.

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
**Dampak data lokal:** migration akan mengubah **44 baris hutang + 44 baris piutang** (22 pasang, Rp86.920.080 per sisi) dari `active` → `legacy`. Jalankan `php spark migrate` saat deploy.

**4. Dua dashboard memakai definisi “Total Hutang/Piutang” yang berbeda. — ✅ DIPERBAIKI**
`HutangPiutangService::getRingkasan()` (`:998`) tidak memasukkan `mutasi_unit`, sedangkan `countJatuhTempo()` (`:1132`) menghitung semua baris; `DashboardKeuangan.php:94-122` menghitung semua. Hasilnya angka total dan angka status saling bertentangan.
**Perbaikan:**
- `getRingkasan()` sekarang punya bucket khusus `mutasi_unit` (`total_hutang_antar_unit`, `total_piutang_antar_unit`, beserta jumlah transaksi) dan memasukkannya ke `total_hutang`/`total_piutang`.
- `countJatuhTempo()` memakai `SUMBER_DIHITUNG` — daftar sumber yang sama dengan total — sehingga status tidak menghitung baris yang tidak ada di total.
- `DashboardKeuangan` tidak lagi menjumlah registry sendiri; ia memanggil `getRingkasan()` sehingga kedua dashboard memakai satu definisi.
- Dashboard Hutang Piutang menampilkan dua kartu baru: **Total Hutang Antar Unit** dan **Total Piutang Antar Unit**.
**Test:** `KasBankControllerTest::testRingkasanMemasukkanMutasiAntarUnitSesuaiStatus`, `KasBankControllerTest::testStatusRingkasanHanyaDariSumberYangDihitung`.

**5. Nomor nota mutasi duplikat dan riwayat menggabungkan transaksi berbeda. — ✅ DIPERBAIKI (kode; unique index ditunda)**
`MutasiStok.php:461` memakai `like('DATE(tanggal_kirim)', date('ymd'))` — `'2026-10-19'` tidak pernah cocok dengan `'261019'`, jadi urutan selalu `001`. Selain itu `$lastMutasi['no_nota_mutasi']` mengakses object sebagai array (fatal jika suatu saat query cocok), dan tidak ada unique index. `riwayat_mutasi.php:4` mengelompokkan berdasarkan nomor nota → 30 baris tergabung salah.
**Perbaikan:** `insert()` sekarang memakai `simpanMutasi()`. Header disimpan dengan placeholder unik, lalu nomor final dibentuk dari `insertID()` (`MTS{unit}{ymd}{id}`). Query `DATE LIKE` dan akses object-sebagai-array dihapus. Nota baru tidak lagi memakai urutan `001` yang selalu sama.
**Test:** `KasBankControllerTest::testSimpanMutasiMenyimpanHeaderDetailDanNomorUnik`, `testSimpanMutasiRollbackSaatDetailGagal`, `testInsertMutasiRedirectSetelahSimpan`.
**Batas:** 30 baris lama dalam 11 grup duplikat **belum diubah**; unique index belum dipasang karena akan gagal pada data lama. Perlu keputusan renumbering historis sebelum index ditambahkan.

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

**7. Batal terima menghapus utang, tetapi stok tetap di penerima.**
View `stok_barang` (`2026-10-07-000001`, baris 71-77) menghitung semua `detail_mutasi` tanpa melihat `mutasi.status`. Jadi 12 mutasi yang belum diterima sudah memindahkan 55 qty; saat penerimaan dibatalkan, stok tetap pindah sementara H/P di-soft-delete. Penerima bisa memegang barang tanpa kewajiban.
**Fix:** putuskan aturannya — kalau batal adalah pembatalan nyata, stok harus ikut dikembalikan; kalau barang memang sudah dikirim, H/P tidak boleh dihapus. Saat ini keduanya tidak konsisten.

**8. Nilai tagihan memakai jumlah kirim, stok masuk memakai jumlah terima.**
`nilaiDetailMutasi()` memakai `jumlah_kirim`; view stok penerima memakai `jumlah_terima`. Form mengizinkan keduanya berbeda, jadi penerima bisa ditagih barang yang tidak diterima. Di data sekarang semuanya sama, tapi risikonya nyata.
**Fix:** tagih `jumlah_terima`, atau secara eksplisit dokumentasikan bahwa selisih menjadi beban pengirim.

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

**12. Sisi piutang tidak bisa memulai pelunasan, dan tidak ada tautan ke halaman Antar Unit dari detail H/P. — ✅ DIPERBAIKI (tautan)**
Hanya unit yang berhutang (atau role lintas) yang bisa membayar. Unit pengirim hanya bisa menunggu.
**Perbaikan:** detail H/P `mutasi_unit` sisi hutang menampilkan tombol “Lunasi di Kas & Bank → Antar Unit” yang membawa `unit_id` unit penghutang. Sisi piutang menampilkan keterangan bahwa pelunasan dilakukan unit yang berhutang. Otorisasi `saveAntarUnit()` tidak diubah: hanya unit penghutang/role lintas yang bisa membayar.
**Test:** `HutangPiutangDetailLinkTest`.

**13. `getPasangan()` tidak memfilter `deleted`. — ✅ DIPERBAIKI**
`ModelHutangPiutang.php:106`. Jika satu sisi ter-soft-delete, pembayaran bisa menempel ke baris mati.
**Perbaikan:** `getPasangan()` sekarang menambahkan `where('deleted', 0)`. Pasangan yang sudah dibatalkan menghasilkan `null`, sehingga alur bayar berhenti dengan pesan “Pasangan piutang tidak ditemukan” alih-alih menyentuh baris mati.
**Test:** `KasBankTest::testGetPasanganMengabaikanDokumenTerhapus`.

### Nice to have

- Komentar kolom `mutasi.status` di DB (`0=menunggu,1=kirim,2=terima`) tidak sesuai kode (`1=diterima`).
- `hitungStatus()` di `ModeKasBank` menduplikasi `statusFromSisa()` di service.
- Tidak ada fitur void untuk mutasi `status=0`; 12 mutasi menggantung dan stoknya sudah bergerak.

---

## Verdict

Jalur happy-path **mutasi → terima → H/P → bayar antar unit → reversal** sudah rapi dan atomik. Masalah utamanya bukan di pembuatan pasangan, melainkan di **pembatalan/terima ulang**, **cut-off**, **konsistensi laporan**, dan **otorisasi pembuatan mutasi**.

KPI `FIN_HUTANG_PIUTANG` memang **tidak terpengaruh** — tetapi itu bukan kabar baik: KPI tersebut buta terhadap utang antar unit yang nilainya Rp87,6 juta. Kalau utang antar unit memang harus dinilai, KPI-nya yang kurang cakupan; kalau tidak, dashboard lain yang salah menghitungnya.

---

## Yang tidak diperiksa

- Perbaikan #1–#6 dan #9–#13 sudah diuji terarah. Full suite: 708 test; di luar `CustomerSatisfactionTest` (4 error karena tabel `db_service`/`db_stok_barang` tidak dibuat test itu sendiri), daftar error/failure identik dengan baseline #5+#10.
- Data produksi tidak diperiksa; angka diambil dari database lokal `erp_local` per 2026-10-19.
- Migration backfill #3 belum dijalankan di `erp_local`; baru diverifikasi lewat test.
- Cetak PDF, tampilan mobile, dan seluruh matriks role di luar route yang disebut belum diperiksa.
- Temuan #7–#8 belum diperbaiki karena menunggu keputusan bisnis. #5 sudah diperbaiki di kode, tetapi renumbering data lama dan unique index masih ditunda.
