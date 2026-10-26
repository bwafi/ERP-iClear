# Analisis Modul — Group F: Hutang Piutang Terpusat & Pembayaran

Tanggal analisis: 2026-10-26. Mode: baca-saja. Semua klaim diberi bukti `File::method()` / baris. Ketidakpastian ditandai eksplisit.

---

## 1. Ruang lingkup

Modul ini adalah **registry terpusat** posisi hutang & piutang (tabel `hutang_piutang`), tempat seluruh jenis saldo dibaca tanpa double counting.

**Dua kelas sumber (prinsip anti-duplikasi):**

| `sumber_tipe` | Authoritative? | Tabel pembayaran | Sumber saldo |
|---|---|---|---|
| `pembelian` | Tidak (projection) | `pembayaran_hutang` | `pembelian.sisa` |
| `piutang_legacy` | Tidak (projection) | `pembayaran_piutang` | `piutang.sisa_hutang` |
| `piutang_pelanggan` | Ya | `pembayaran_hutang_piutang` | tabel ini |
| `kasbon` | Ya | `pembayaran_hutang_piutang` | tabel ini |
| `manual` | Ya | `pembayaran_hutang_piutang` | tabel ini |
| `jasa_teknisi` | Ya | `pembayaran_hutang_piutang` | tabel ini |
| `kelebihan_transfer` | Ya | `pembayaran_hutang_piutang` | tabel ini |
| `retur_barang` | Ya | `pembayaran_hutang_piutang` | tabel ini |
| `mutasi_unit` | **Tidak menentu** | via KasBank `saveAntarUnit` | via KasBank |

Bukti: `HutangPiutangService::AUTHORITATIVE_SUMBER` (list tanpa `pembelian` & `piutang_legacy`), komentar kelas `HutangPiutang` (baris 14-16), `ModelHutangPiutang` komentar (baris 10-14), migration `2026-09-19-000100`.

Catatan: `mutasi_unit` TIDAK ada di `AUTHORITATIVE_SUMBER` maupun di `SUMBER_DIHITUNG`? — salah, ada di `SUMBER_DIHITUNG`. Namun posisi mutasi antar-unit (hutang + piutang) diciptakan oleh modul KasBank (`HutangPiutangService::SUMBER_MUTASI`, `getHutangPiutangUnit`, `getPasangan`) dan pelunasannya lewat `KasBank::saveAntarUnit`, bukan `bayar()`.

---

## 2. Endpoint/Routes

### Modul baru H/P (Routes baris 623-635)
| Method | Route | Controller::method |
|---|---|---|
| GET | `hutangpiutang/dashboard` | `HutangPiutang::dashboard` |
| GET | `hutangpiutang/piutang` | `piutang` |
| GET | `hutangpiutang/hutang` | `hutang` |
| GET | `hutangpiutang/riwayat` | `riwayat` |
| GET | `hutangpiutang/form` | `form` |
| POST | `hutangpiutang/store` | `store` |
| GET | `hutangpiutang/detail/(:num)` | `detail/$1` |
| POST | `hutangpiutang/bayar` | `bayar` |
| POST | `hutangpiutang/kompensasi` | `kompensasi` |
| POST | `hutangpiutang/tandai-lunas-legacy` | `tandaiLunasLegacy` |
| POST | `hutangpiutang/batalkan-lunas-legacy` | `batalkanLunasLegacy` |
| GET | `hutangpiutang/cetak/(:num)` | `cetak/$1` |

Semua berfilter `auth`. `store`/`bayar`/`kompensasi`/legacy di-guard `canInput()` di controller.

### Modul lama (routes 594-620) — masih ada, jalur untuk pembelian/legacy
- `riwayat_pembayaran_hutang`, `daftar_tagihan`, `umur_hutang`, `update_cicilan_hutang`, `export_umur_hutang`, `export_daftar_hutang` → `PembayaranHutang`
- `piutang`, `input_piutang`, `riwayat_pembayaran_piutang`, `daftar_piutang`, `update_cicilan_piutang`, `export_*`, `umur_piutang` → `Piutang`
- `kas_bank/antar-unit/save` → `KasBank::saveAntarUnit` (route 454) — dipakai form pelunasan antar-unit dari halaman detail H/P.

---

## 3. Alur Pengguna utama & alternatif

**A. Buat piutang / hutang baru (authoritative)**
1. `HutangPiutang::form`/`index` (modal) → pilih `sumber_tipe` dari `inputTypes()`.
2. `store` → validasi `canInput()`, `sumber_tipe` ada di `inputTypes()`, panggil `service->createPosition()`.
3. `createPosition` (HutangPiutangService.php:461): paksa `pihak_tipe` & `jenis` sesuai sumber (forced map baris 490-496), reject total ≤ 0 / negatif, keterangan wajib utk manual/jasa/kelebihan/retur. Transaksi DB.
4. Efek samping (lihat §6): kasbon → baris `kas_keluar` + jurnal; piutang pelanggan → jurnal `hp_piutang_pelanggan_input`. Jenis manual **tidak** diposting jurnal (COA belum disetujui).
5. Redirect ke `detail/{id}`.

**B. Bayar cicilan / lunas (authoritative)**
1. Dari list (`index`) tombol "Bayar" (hanya non-kasbon) atau detail.
2. `bayar` (controller) → `service->bayar()` (Service:629): row lock `FOR UPDATE`, validasi sisa, insert `pembayaran_hutang_piutang`, update saldo, post jurnal tunai &/ bank.

**C. Kompensasi (piutang ⇄ hutang satu pihak)**
1. Detail → tombol "Kompensasi" hanya jika ada `getLawanKompensasi()` (lawan authoritative, non-projection, non-lunas, pihak sama).
2. `kompensasi` (controller) → `service->kompensasi()` (Service:2165): lock dua baris, validasi jenis berlawanan + pihak sama + sisa cukup, insert `kompensasi_hutang_piutang` + 2 baris audit `pembayaran_hutang_piutang` (`sumber='kompensasi'`). Tidak ada uang fisik bergerak.

**D. Tandai lunas LEGACY (settle tanpa pembayaran)**
1. Hanya baris non-projection, outstanding, bertanggal ≤ Finance cut-off (`FinanceScopeService::cutoffDate()` = 2026-10-05).
2. `tandaiLunasLegacy` (Service:801): set status lunas, sisa 0, simpan `sisa_legacy_sebelum`, `tanggal_settlement_legacy`, `cutoff_reason='legacy'`, `cutoff_closed_at/by`. **Tidak ada jurnal, pembayaran, maupun movement kas/bank.**

**E. Batalkan lunas legacy**
1. `batalkanLunasLegacy` (Service:912): butuh `settlementLegacyAktif()`. Mengembalikan sisa `sisa_legacy_sebelum`, status dihitung ulang via `statusFromSisa()`, cabut penanda aktif, catat `legacy_dibatalkan_*`. Jejak settlement asli tidak dihapus.

**F. Cetak bukti**
1. `cetak/{id}` (controller:374): `view('cetak/bukti_hutang_piutang')` → mPDF A4, `Output('bukti-kode.pdf','I')`.

**G. Umur hutang/piutang (legacy & KPI)**
- Legacy: `umur_hutang` → `ModelPembelian::getAgingHutang()`; `umur_piutang` → `ModelPiutang::getAgingPiutang()`. Ada export XLSX.
- KPI Din: `HutangTimelinessCalculator` (score tepat waktu) & `PiutangTimelinessCalculator` (score **null** → gap `no_payment_date`).

**H. Daftar tagihan**
- Hutang: `daftar_tagihan` → `ModelPembelian::getBelumLunas()`; bayar via `insert_cicilan` (PembayaranHutang, transaksi atomik + PostingLedgerException + `syncFromPembelian`).
- Piutang: `daftar_piutang` → `ModelPiutang::getPiutangStatus0()`; bayar via `bayar_piutang` + `syncFromPiutangLegacy`.

---

## 4. Business Rules

### (a) Fakta terverifikasi (dengan bukti)
1. **Hanya sumber authoritative yang bisa dibayar/dikompensasi/ditandai lunas di modul ini.** `bayar()` menolak bila `!in_array(sumber_tipe, AUTHORITATIVE_SUMBER) || is_projection==1`. — `HutangPiutangService::bayar` (657). `kompensasi` sama (2186). `tandaiLunasLegacy` menolak projection (825).
2. **Status = fungsi dari sisa & total_dibayar.** `statusFromSisa(total,sisa,dibayar)`: sisa≤0→lunas; dibayar>0→sebagian; else belum_lunas. — Service:109.
3. **Pembayaran tidak bisa melebihi sisa** (guard `jumlah > sisa` → tolak; row lock `FOR UPDATE`). — Service:665.
4. **Dua pembayaran paralel tidak bisa memakai sisa yang sama** (row lock `FOR UPDATE` di `bayar`, `kompensasi`, `settleKasbonFromPayroll`). `ModelHutangPiutang::kurangiSisaJikaCukup` juga menyediakan guard WHERE `sisa >= ?` (Model:126) — tapi di service utama tidak dipakai (service pakai transaksi sendiri).
5. **Kompensasi hanya untuk pihak yang sama** (`pihak_tipe` & `pihak_id` identik), hanya hutang-piutang, hanya authoritative non-lunas. — Service:2181,2195.
6. **Kompensasi dibatasi sisa terkecil** (`min(sisa_h,sisa_p)`). — Service:2200.
7. **Kasbon: jatuh tempo wajib tanggal 1 bulan berikutnya**; pihak dipaksa pegawai; jenis dipaksa piutang. — Service:477,491.
8. **Kasbon pencairan membuat baris `kas_keluar`** (kategori "Kasbon", bukan kategori 10; `no_akun=1020201000`) + jurnal `hp_kasbon_input(_bank)`. — Service:275,607, `KATEGORI_KASBON`, `NO_AKUN_PIUTANG_PEGAWAI`.
9. **Settlement kasbon dari payroll idempotent** (`existsByReferensi('finance_payroll',$payrollId)` guard + UNIQUE). — Service:1503, `ModelPembayaranHutangPiutang::existsByReferensi`.
10. **Jurnal H/P diposting via `template_jurnal`**; bila template tidak ada, hanya log error dan transaksi TETAP tersimpan (tidak gagal). — `postJurnal` (182-217), komentar (176-181).
11. **Settlement legacy bukan pembayaran**: tidak insert pembayaran, tidak jurnal, tidak `transaksi_kas_bank`, tidak sentuh `saldo_awal_kas_bank`, tidak ubah histori sumber, dan `total_dibayar` TIDAK diubah. — komentar Service:725-756, `tandaiLunasLegacy`.
12. **Settlement legacy hanya untuk hutang bertanggal ≤ cut-off** ("Aturan 6") & tanggal settlement ≤ cut-off. — Service:806,841.
13. **Pembatalan legacy wajib tercatat** di `legacy_dibatalkan_*`; `cutoff_reason` sengaja bukan penanda tunggal. — Service:773 `settlementLegacyAktif`, migration 2026-10-04-000700.
14. **Projection disinkron dari sumber kapan saja** sebelum list (`refreshProjections`), dan saat update pembelian/piutang legacy (`syncFromPembelian`, `syncFromPiutangLegacy`). Penandaan lunas legacy pada projection akan ditimpa sync → ditolak. — Service:1297,1602,1649,825.
15. **Kode normalisasi**: `<SEG>-Ymd<unit><seq4>`, seg KSB/PLG/PGW/JST/KLB/RTB/MNU/HUT/PUT; prefix "HP-" dihapus di migration 000500. — Service:138, migration 2026-09-19-000500.
16. **Registri menjaga UNIQUE** `kode`, `(sumber_tipe,sumber_id)`, dan pembayaran `(sumber,referensi_tipe,referensi_id,hutang_piutang_id)` (diperbaiki 2026-10-20-000200). — migration 000100, 2026-10-20-000200.
17. **Dashboard & ringkasan anti double counting**: tiap sumber dibaca sekali, scope legacy dikecualikan, filter outstanding diterapkan. — `getRingkasan` (1015+), `countJatuhTempo`.

### (b) Inferensi (berasal dari kode, belum diverifikasi data)
1. **`getLawanKompensasi` hanya menampilkan lawan authoritative dengan pihak sama** — disimpulkan dari query (2123-2143); perilaku UI (tombol muncul) mengikutinya.
2. **`mutasi_unit` antar-unit membentuk pasangan hutang-piutang** yang dilunasi lewat KasBank, bukan `bayar()`. Disimpulkan dari `getPasangan`, `antarUnitPaymentData` (controller:202) dan view form `kas_bank/antar-unit/save`.
3. **Ringkasan "terlambat" dihitung dari `jatuh_tempo` vs hari ini tanpa memperhitungkan jam** — `tallyStatus` (1213), view `dueMeta`.
4. **Jurnal `hp_manual_*_bayar_*` kemungkinan besar belum ada** sehingga pembayaran manual tidak terposting (hanya log), sesuai komentar `bayarTemplate`. — Service:229-233.

### (c) Ketidakpastian / risk
1. **`PiutangTimelinessCalculator` tidak bisa menghitung "Tepat Waktu"** karena `pembayaran_piutang` tidak punya kolom tanggal bayar → score `null`, status `gap_no_payment_date`. — PiutangTimelinessCalculator.php:89, komentar 9-19. (GAP terkonfirmasi.)
2. **Saat `bayar()` dengan `bayar_tunai`+`bayar_bank` = 0**, seluruh jumlah dianggap tunai (`$tunai=$jumlah`). Ini bisa menciptakan jurnal tunai untuk pembayaran yang sebenarnya bank bila form tidak mengirim rincian. — Service:641-644.
3. **Input manual / jasa teknisi / kelebihan / retur tidak diposting jurnal** (COA belum disetujui) → saldo registry bisa berbeda dengan ledger. — Service:586-610.
4. **`createPosition` memakai `pihak_id` dari master saat `nama_pihak` kosong; kegagalan resolve → tolak.** Perilaku saat pihak sudah dihapus (deleted) tidak diuji di sini. — Service:520-527.
5. **ISO `syncFromPembelian` / `syncFromPiutangLegacy` dipanggil dalam try di controller lama**; kegagalannya hanya di-log, tidak membatalkan pembayaran (sengaja). — PembayaranHutang:240-246, Piutang:122-126.
6. **`kurangiSisaJikaCukup` tidak dipakai oleh HutangPiutangService** (service memakai transaksi + row lock). Ada dua mekanisme guard yang tampak paralel — perlu konfirmasi apakah Model ini masih dipakai jalur lain.

---

## 5. Validasi & Permission/Role (termasuk scope unit/cut-off)

**Role (Config\Finance):**
- Input/isi (`financeInputRoles`) = **0,1,2,34** (Finance, Admin root, Direktur, Manager). `->ScopeService::canInput()` = role ∈ list ini. — FinanceScopeService:172, Config\Finance:148.
- View (`financeViewRoles`) = **kosong** → kontrol akses penuh lewat inputRoles. — Config\Finance:154.
- Finance/HO source (`financeHoSourceRoles`) = **0,1** (hanya untuk menarik dana Direksi). — Config\Finance:188.
- Reversal transfer (`kasBankTransferReversalRoles`) = **0,1**. — Config\Finance:202.

**Scope unit (FinanceScopeService):**
- Role lintas unit (0,1,2,34) → semua unit. — `resolveAllowedUnits` (70-100).
- Role lain → unit sendiri (`ID_UNIT`), atau unit dari tabel `spv_units` (SPV). 
- `resolveUnitId` (controller): tanpa param & satu unit → unit itu; multi-unit → null (semua diizinkan). — controller:37-48.
- `resolveSelectedUnitIdAtauKosolidasi` mengembalikan null untuk konsolidasi jujur (tapi `resolveSelectedUnitId` TIDAK pernah null utk multi-unit). — FinanceScopeService:111-154.

**Account scope (KasBankScopeService)** — untuk pelunasan antar-unit & pemilihan rekening:
- `FINANCE_HO` (is_finance_ho=1): source hanya role 0/1 + unit valid di user scope; destination hanya unit yang punya rekening operasional. — `canUseAsSource`/`canUseAsDestination` (332-395).
- `SHARED`: unit yang punya baris `alokasi_saldo_kas_bank`. — `accountAllowedUnits` (196-199).
- `UNIT`: hanya unit pemilik (`akun.unit_id`). — (202-204).

**Cut-off / scope data:**
- `cutoffDate = 2026-10-05`, `periodeMulaiDate = 2026-10-06` (H/P & KPI memakai ini).
- `kasBankCutoffDate = 2026-10-09`, `kasBankPeriodeMulaiDate = 2026-10-10` (khusus KasBank).
- Scope kolom `hutang_piutang.scope`: `active|opening|legacy`. Legacy dikecualikan dari saldo/aging/tempo aktif. List default = non-legacy; filter "semua"/"legacy"/"opening" tersedia. — view index, `listPositions` (1252-1259).

**Validasi controller:** semua POST H/P di-guard `canInput()` di awal (form, store, bayar, kompensasi, tandai/batalkan lunas). `store` juga memvalidasi `sumber_tipe` ada di `inputTypes()`. — controller:117,137,253,278,314,339.

---

## 6. Perubahan Database / Transaksi / Efek Samping

**Tabel inti (migration 2026-09-19-000100):**
- `hutang_piutang` (registry; kode UNIQUE; `sumber_tipe` ENUM; `scope` aktif/opening/legacy ditambah 000700; kolom settlement/audit ditambah 2026-10-04-000600/000700).
- `pembayaran_hutang_piutang` (authoritative payments; UNIQUE `(sumber,referensi_tipe,referensi_id,hutang_piutang_id)` — diperbaiki 2026-10-20-000200).
- `kompensasi_hutang_piutang` (migration 000400).
- `finance_payroll.potongan_kasbon` + `total_bersih`, komponen gaji `KASBON` (migration 000100).

**Transaksi (semua memakai `transBegin/transCommit/transRollback`):**
- `createPosition` (568): cas keluar (only kasbon) + jurnal + update `sumber_id` utk manual.
- `bayar` (646): insert payment + update posisi + jurnal (per metode).
- `kompensasi` (2172): insert kompensasi + 2 audit payment + update dua posisi.
- `tandaiLunasLegacy` (810) & `batalkanLunasLegacy` (914): hanya update baris registry.
- `settleKasbonFromPayroll` (1524): loop kasbon, insert payment payroll + update + jurnal `hp_kasbon_potong_payroll`.
- `syncKasbonDariKasKeluar` / `hapusKasbonDariKasKeluar`: sync/hapus kasbon saat baris `kas_keluar` diubah/dihapus.

**Efek samping / integration:**
- **Jurnal** via `template_jurnal` (tidak menggagalkan bila template absent). Tabel `jurnal`, `id_referensi` = id payment/posisi, `tabel_referensi` = `pembayaran_hutang_piutang` / `hutang_piutang`.
- **Movement kas-bank**: hanya melalui (1) kasbon → `kas_keluar`; (2) pembayaran hutang supplier → `ModeKasBank::postingCicilanHutang` (di `insert_cicilan`); (3) pelunasan antar-unit → `KasBank::saveAntarUnit` (transaksi_kas_bank). Modul H/P sendiri tidak menulis `transaksi_kas_bank`.
- **Kode normalisasi**: `generateKode`, projection `HUT-PB<id>` / `PUT-PG<id>`.
- **Kompensasi manual**: tidak ada perpindahan uang; hanya alokasi saldo antar dua posisi + 2 baris audit.

---

## 7. Relasi antar-modul

- **KasBank**: pelunasan &-unit (`mutasi_unit`) via `kas_bank/antar-unit/save`. `HutangPiutang::antarUnitPaymentData` menyiapkan rekening/token; logic ledger/saldo tetap di KasBank (anti-duplikasi). — controller:202-249, detail.php form.
- **Pembelian**: `syncFromPembelian` (upsert projection); pembayaran hutang supplier via `PembayaranHutang` (daftar_tagihan) + `postingCicilanHutang`.
- **Piutang legacy**: `syncFromPiutangLegacy`; pembayaran via `Piutang::bayar_piutang`.
- **Payroll**: `settleKasbonFromPayroll` memotong kasbon pegawai saat gaji dibayar (komponen KASBON, `potongan_kasbon`).
- **Kas keluar**: kasbon membuat `kas_keluar`; guard `guardKasbonKasKeluar` & `syncKasbonDariKasKeluar` menjaga sinkron.
- **Tutup Kasir / arus kas**: membaca `kas_keluar` kasbon; jurnal kasbon menghindari dobel posting.
- **KPI Finance**: `HutangTimelinessCalculator` & `PiutangTimelinessCalculator` dipakai `FinanceKpiService` (gabung skor hutang+piutang+mutasi; gap piutang ditandai). — FinanceKpiTest.
- **Autentikasi/Unit**: `ModelAuth`, `ModelUnit`, `FinanceScopeService`, `KasBankScopeService`.

---

## 8. Bukti Test

- `tests/HutangPiutangDetailLinkTest.php` — form pelunasan antar-unit di detail (asset `kas_bank/antar-unit/save`, `akun_pengirim_id`, `akun_penerima_id`, token, `kembali_ke`), detail tidak menampilkan form tanpa data, mujejak settlement legacy.
- `tests/HutangPiutangListRedesignTest.php` — ringkasan list, filter status, empty state, baris mutasi antar-unit menyediakan aksi "Lunasi".
- `tests/unit/FinanceKpiTest.php` — config code, bobot, average antar-cabang, compliance, gabung skor H/P + gap piutang.
- `tests/ScopeConsolidasiTest.php` — visibilitas rekening per unit & konsolidasi (user scope tidak bocor).
- `tests/ScopeFinanceRoleTest.php` — rekening Finance/HO hanya source utk role 0/1; destination hanya unit yang punya rekening operasional.
- `tests/ScopeNominalZeroTest.php`, `tests/DirekturUnitScopeTest.php`, `tests/ServiceDpBankScopeTest.php` — scope & nominal (dibaca seperlunya).

---

## 9. Gap/Konflik

1. **KPI Piutang tidak lengkap**: `pembayaran_piutang` tidak punya `tanggal_bayar` → score piutang selalu `null` (status `gap_no_payment_date`). Konflik dengan KPI "Hutang & Piutang" yang menggabungkan skor piutang. (GAP terkonfirmasi di kode; solusi Fase 3 = tambah kolom tanggal bayar.)
2. **`mutasi_unit` di luar `AUTHORITATIVE_SUMBER`** tapi masuk `SUMBER_DIHITUNG`: konsisten untuk ringkasan, namun pelunasannya bergantung penuh pada KasBank. Bila `saveAntarUnit` tidak menurunkan sisa H/P dengan benar, dashboard bisa tidak sinkron (belum diuji di sini).
3. **Pembayaran manual tanpa rincian metode** dipaksa jadi 100% tunai; berisiko salah post tunai vs bank.
4. **Jurnal jenis manual (jasa/kelebihan/retur/manual) tidak terposting** sampai mapping COA disetujui → beda registry vs ledger.
5. **Dua mekanisme guard sisa paralel**: `kurangiSisaJikaCukup` (Model) vs transaksi+`FOR UPDATE` (Service). Perlu konfirmasi mana yang benar-benar dipakai di jalur produksi.
6. **Enum `sumber` di `pembayaran_hutang_piutang`** asalnya `manual|payroll`, diperluas `kompensasi` (000400). View `labelPembayaran` memetakan `antar_unit`/`kas_bank`/`legacy` juga, tapi nilai enum untuk sumber tsb tidak terlihat di migration → kemungkinan disimpan sebagai nilai non-enum atau dari KasBank (perlu verifikasi tambahan). **Ketidakpastian.**
7. **Legacy settlement hanya mendukung yangang** (`tandaiLunasLegacy` menolak non-projection hutang? — tidak, hanya menolak projection/lunas; tapi pesan & alur difokuskan ke hutang). Untuk piutang legacy, lunas via `syncFromPiutangLegacy` dari tabel `piutang`. Perlu konfirmasi apakah piutang bisa ditandai lunas legacy juga. **Ketidakpastian.**
