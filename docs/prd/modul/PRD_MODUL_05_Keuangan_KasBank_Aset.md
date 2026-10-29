# PRD Modul 05 — Keuangan, Kas & Bank, Tutup Kasir, Aset

| | |
|---|---|
| Kode Modul | KEU-05 |
| Sumber analisis | baca penuh `app/Controllers/Kas_Keluar.php` & `app/Libraries/ModeKasBank.php`, `Routes.php`, service `Finance/*`, banyak test |
| Status verifikasi | ✅ fakta / 🔶 inferensi / ⚠️ ketidakpastian |

---

## 1. Tujuan
Mengelola pencatatan kas masuk/keluar, jurnal, laporan posisi keuangan & laba rugi, akun kas/bank (rekening, saldo awal, alokasi, opening kas, transfer internal/antar unit, setor/tarik tunai), tutup kasir harian, aset & penyusutan, dan dashboard/KPI finance.

## 2. Aktor
- Kasir/admin cabang (kas masuk/keluar, tutup kasir).
- Finance / ROOT / Direktur / Manager (role 0,1,2,34: bebas pilih unit & kelola akun kas/bank/HO).
- SPV (scope unit binaan via `spv_units`).

## 3. Preconditions
- Login; unit aktif.
- Untuk kas keluar: rekening valid & unit berhak (validasi `BankRekeningValidator`).
- Untuk posting ledger: akun kas/bank aktif terkonfigurasi (fallback Kas Besar COA 1010101000).

## 4. Alur Utama
### 4.1 Kas Keluar — `Kas_Keluar::insert_kas_keluar`
1. Validasi unit (fallback `session('ID_UNIT')`); minimal satu baris akun.
2. Per baris: validasi rekening via `BankRekeningValidator::validateUntukUnit` (gagal → `PostingLedgerException`).
3. Insert `kas_keluar` → `postingKasKeluar` (ledger `transaksi_kas_bank`, idempotent) → insert jurnal (posisi `debet/kredit` dari `posisi_drk`).
4. Seluruh batch dalam SATU transaksi `transStart`; kegagalan posting → `transRollback` (anti partial-success).
5. Update/delete: hapus posting lama, posting ulang, sinkron kasbon (`HutangPiutangService::guardKasbonKasKeluar/syncKasbonDariKasKeluar/hapusKasbonDariKasKeluar`). **Update tidak menghapus/menulis ulang jurnal; delete menghapus jurnal.**
### 4.2 Kas Masuk — `Kas_Masuk::insert_kas_Masuk` (pola serupa, `postingKasMasuk`). **Update/delete kas masuk tidak menyinkronkan/menghapus jurnal.**
### 4.3 Jurnal & Laporan
- `Jurnal::index/export_jurnal`; `SisiKeuangan::index/export_pdf/export_excel`; `LabaRugi::index/laporan_unit/laporan_perbandingan_periode/cetak_*`.
- Refund uang service garansi (`GaransiRefundService`, nominal `dp_bayar + bayar`) dicatat sebagai `kas_keluar` kategori `REFUND` (akun `2010302000`) dengan jurnal seimbang memakai template `refund_garansi_tunai` (kredit `1010101000`) atau `refund_garansi_bank` (kredit `1010102000`). 🔶 Pemisahan akun untuk porsi non-DP menunggu posting pembayaran service (#37).
### 4.4 KasBank (modul terpusat)
- Akun (`saveAkun`), saldo awal (`saveSaldoAwal`), alokasi saldo (`saveAlokasiSaldo`), opening kas (`saveOpeningKas`).
- Transfer internal (`saveTransfer`), antar unit (`saveAntarUnit`), setor tunai (`saveSetorTunai`), penarikan tunai (`savePenarikanTunai`), reversal.
- Posting ke `transaksi_kas_bank` via `ModeKasBank` (resolve akun, scope source/destination, idempotent guard).
- **CSRF hanya pada setor/tarik tunai (`['auth','csrf']`); transfer internal/antar-unit hanya `auth`.**
### 4.5 Tutup Kasir — `TutupKasir::tutup`
- One-time closing harian per unit; saldo awal laci; `cash_laci` dari browser; perhitungan server-side (`TutupKasirClosing::hitung`); selisih kas fisik vs sistem; PDF bukti.
- `kasirbulanan`, `omsetbulanan`, `arusKasHarian`, `assetberjalan`, `detailPengeluaran`.
### 4.6 Aset & Penyusutan
- `Asset::insert/update/delete_asset/export_asset/pulihkan_asset/prosesPenyusutan` (cron), `KategoriAsset`, `TemplateJurnalAsset`, `RiwayatPenyusutanAsset`.
### 4.7 Dashboard Finance
- `DashboardFinance::index/entryOmzetSheet/entryManual/entryPayroll/rekonsiliasi/rekonSave/rekonSubmit/rekonApprove`.

## 5. Business Rules (Bukti)
1. **Ledger idempotent** dengan guard `sumber_tipe+sumber_id+akun+arah`. — `ModeKasBank::insertIdempotent/sudahTerposting`
2. **Anti partial-success**: bila posting ledger gagal, seluruh batch dibatalkan. — `Kas_Keluar::insert_kas_keluar` + `PostingLedgerException::wajibBerhasil`
3. **Resolve akun**: bila `bankId` diberikan, wajib rekening BANK aktif milik unit; tidak boleh fallback ke KAS. Bila `bankId` kosong → akun KAS aktif pertama (prioritas Kas Besar COA 1010101000 > tanpa COA > Kas Kecil). — `ModeKasBank::resolveAkunDetail`
4. **Scope account berarah**: Finance/HO (`is_finance_ho`) hanya boleh jadi source oleh role 0/1; sebagai destination diterima dari unit mana pun; shared hanya unit dengan baris alokasi; unit hanya pemilik. — `KasBankScopeService::canUseAsSource/canUseAsDestination`
5. **Unit filter baca**: role 0/1/2/34 bebas pilih unit; lainnya dibatasi (unit sendiri / `spv_units`). Request unit di luar scope jatuh ke unit pertama diizinkan. — `Kas_Keluar::bebasPilihUnit/unitFilterTerpilih`. **Scope ini tidak memvalidasi unit tulis:** `insert_kas_keluar`/`insert_kas_Masuk` memakai `unit_idunit` dari POST; `Asset` memakai `unit` dari POST. — X-06/FN-05
6. **Transaksi kas keluar** → posting ledger + jurnal, dipisah `debet/kredit` berdasar `posisi_drk`. — `Kas_Keluar::insert_kas_keluar`
7. **Kasbon**: baris `kas_keluar` dari payroll menyimpan `penerima=ID_AKUN`; guard mencegah ubah/hapus kasbon yang sudah lunas. — `HutangPiutangService::guardKasbonKasKeluar`
8. **Tutup kasir**: perhitungan saldo server-side; browser hanya kirim `cash_laci`; one-time per hari per unit. — `PRODUCT.md`; `TutupKasirClosing::hitung`
9. **CSRF per-route hanya pada sebagian route.** Setor/tarik tunai, refund DP, sebagian `StatusGaransi`, `StokOpname`, `ReturBarangRusak`, dan `tutupkasir/tutup` memakai `csrf`; **kas masuk/keluar, aset, transfer internal/antar-unit, pembelian, penjualan, dan retur supplier/customer tidak**. — `Routes.php`; X-04/FN-02/FN-07
10. **Reversal** menghapus posting lalu post ulang (idempotent). — `ModeKasBank::hapusPosting`
11. **Edit/hapus kas tidak selalu sinkron jurnal.** `Kas_Masuk::update_kas_Masuk` memperbarui sumber + ledger tetapi tidak jurnal; `delete_kas_Masuk` tidak menghapus jurnal. `Kas_Keluar::update_kas_keluar` hanya posting ulang ledger; delete menghapus jurnal. — X-07/FN-03/FN-04
12. **Aset tidak memakai role/scope**, unit dari POST, insert aset + riwayat penyusutan tidak atomik, `update_asset` mengabaikan `asset_code`/`nilai_perolehan`/`nilai_sekarang`, `GET penyusutan_cronjob` tanpa auth, dan `pulihkan_asset/{id}` hanya `auth` + GET (tanpa CSRF/role). — `Asset.php:54,89,140,172,190,267`; `Routes.php:484-488,580` (FN-06)
13. **`GET /kas_masuk` dan `GET /kategori_kas` tanpa filter auth.** — `Routes.php:416,431` (FN-01)
14. **`laba_service`/`fee_service` memakai typo `['fileter'=>'auth']`**, bukan `filter`; proteksi auth tidak berlaku/route dapat error. — `Routes.php:336-340` (FN-08)
15. ✅ **Klasifikasi 6% punya satu sumber.** `Config\Finance::$kategoriBiayaTerkontrol` (`[1,2,3,4,5,18]`) dipakai `/kas_keluar` dan `/asset_berjalan`. Endpoint `kas_keluar/datatables` memfilter `kategori_mode=enam|luar` di server (`whereIn`/`whereNotIn`), dan ringkasan `Total / Masuk 6% / Di luar 6%` mengikuti filter tanggal, unit, dan pencarian — bukan mode kategori yang sedang dipilih. — `Config/Finance.php`; `ModelKasKeluar::applyKasKeluarFilter/summaryKasKeluarFiltered`; `tests/KasKeluarUnitFilterTest.php`
16. ✅ **Pembagian gaji HO di `/asset_berjalan`.** Gaji HO non-SPV dibagi global: semua unit `pribadi` = 1 bagian, tiap `franchise` = 1 bagian; unit trial/non-operasional tidak menanggung. Gaji SPV (jabatan 40) dipisah dari pool global dan hanya dibebankan ke unit wilayahnya (`spv_units`) yang non-trial; bila SPV tidak punya wilayah valid, gajinya jatuh ke pool global agar tidak hilang. Rincian nominal per unit dikirim tanpa batasan role tambahan; visibilitas panel tetap mengikuti role lintas cabang. Di halaman ini SPV (40) hanya dapat membuka unit pada `spv_units` (fallback unit sendiri); permintaan unit lain jatuh ke wilayah pertama. — `HoSalaryDistributionService::alokasiKeUnit`; `TutupKasir::resolveUnitScope`; `tests/HoSalaryDistributionSpvTest.php`
17. ✅ **Laba bersih `/asset_berjalan` dikurangi gaji HO.** Rumus: `Omset − Biaya Terkontrol − Total Gaji − Gaji HO`. Gaji HO = nominal yang sudah dipotong payroll unit itu (`total_dipotong`); selama belum ada potongan, dipakai perkiraan bagian unit (`total_perkiraan`) dan selnya diberi keterangan "Perkiraan — belum dipotong payroll". Keduanya tidak dijumlah. Tanggungan unit 4 (20%), Hak Cabang (60%), dan Tagihan Center (40%) memakai laba bersih yang sama. `totalGajiUnit` tetap gaji karyawan cabang saja, jadi rincian gaji tidak memuat HO. Bagian unit dikirim ke semua role (`ho_unit_share`), sedangkan agregat HO (`ho_distribution`) tetap hanya untuk role lintas cabang, supaya laba bersih tidak berbeda menurut siapa yang melihat. — `app/Views/dashboard/asset_berjalan.php`; `TutupKasir::assetberjalan`; `tests/AssetBerjalanCardTest.php`
18. ✅ **Pelunasan antar unit berarah ke rekening unit lawan.** "Dibayar KE" berisi rekening yang menjadi hak unit BERPIUTANG (lawan hutang), bukan rekening unit yang sedang dipilih; pilihan disaring per hutang di view. Admin cabang boleh melunasi hutang unitnya ke rekening unit lawan walau unit lawan di luar user scope-nya — guard hanya menuntut unit PEMBAYAR dalam scope, rekening sumber sah untuk unit itu, dan rekening tujuan menjadi hak unit berpiutang. — `KasBank::antar_unit/saveAntarUnit`; `KasBankScopeService::canReceiveAsCreditor`; `tests/KasBankControllerTest::testAntarUnitDibayarKeMenampilkanRekeningUnitLawan`, `testAdminCabangBisaMelunasiKeRekeningUnitLawanDiLuarScope`, `testAdminCabangTidakBolehMelunasiKeRekeningSembarang`
19. ✅ **Pelunasan antar unit tampil read-only di `/kas_keluar`, tanpa disalin ke `kas_keluar`.** Satu sumber kebenaran uang adalah `transaksi_kas_bank`. Daftar kas keluar membaca UNION `kas_keluar` + leg KELUAR `PEMBAYARAN_ANTAR_UNIT`, sehingga total/ringkasan di halaman ikut memuat pelunasan antar unit dan cocok dengan saldo rekening. Baris itu tidak punya `idkas_keluar`, ditandai `readonly`, dirujuk lewat `transfer_ref`, dan tidak menampilkan tombol edit/hapus — kalau disalin ke `kas_keluar`, posting keluar terjadi dua kali. Leg MASUK tidak ditampilkan, dan leg `sumber_tipe='antar_unit_atribusi'` (rekening fisik sama — hanya mutasi hak) dikecualikan karena tidak ada uang keluar. Pencarian ID angka hanya mencocokkan `idkas_keluar`, bukan `idtransaksi`. Deskripsi barisnya selalu memuat arah `Pelunasan antar unit <unit asal> → <unit tujuan>` (unit tujuan diambil dari leg MASUK ber-`transfer_ref` sama), diikuti keterangan user bila ada. — `ModelKasKeluar::unionSumber/unionWhere`; `Kas_Keluar::datatable`; `app/Views/jurnal/kas_keluar.php`; `tests/KasKeluarUnitFilterTest::testPelunasanAntarUnitTampilReadOnlyDiKasKeluar`, `testBarisAntarUnitTanpaTombolEditHapus`

**Catatan UI `/kas_keluar`:** input dan edit dibuka di modal di atas daftar; satu kartu transaksi = satu baris `kas_keluar`. Simpan yang ditolak mengembalikan isian lewat flashdata `kk_old`, dan hanya badge "6%" yang diwarnai — bukan seluruh nama kategori.

## 6. Acceptance Criteria
- [ ] Insert/update/delete kas keluar & masuk atomik & konsisten dengan ledger & jurnal.
- [ ] Role terbatas tidak dapat melihat/memfilter data unit di luar scope.
- [ ] Rekening di luar entitlement ditolak (bukan di-skip diam-diam).
- [ ] Tutup kasir per hari per unit hanya satu kali; PDF bukti tersedia.
- [ ] Transfer internal & antar unit serta setor/tarik tunai tercatat di ledger dan dapat di-reversal.
- [ ] Pelunasan antar unit menawarkan rekening unit berpiutang; admin cabang dapat melunasi hutang unitnya walau unit lawan di luar scope (BR 18).
- [ ] Pelunasan antar unit muncul sebagai baris read-only di `/kas_keluar` dan ikut total, tanpa membuat baris `kas_keluar` baru (BR 19).
- [ ] Laporan posisi keuangan & laba rugi dapat dicetak (PDF/Excel).

## 7. Dependensi
- `ModelKasMasuk/ModelKasKeluar/ModelJurnal/ModelNoAkun/ModelKategoriKas/ModelAkunKasBank/ModelTransaksiKasBank/ModelSaldoAwalKasBank/ModelAlokasiSaldoKasBank/ModelOpeningKas/ModelTutupKasir/ModelAsset`.
- Service `Finance/*` (Scope, Entitlement, Cutoff, BankRekeningValidator, TutupKasir*, dll.), `ModeKasBank`.
- Modul: Hutang Piutang (kasbon), Payroll (kas_keluar), Penjualan/Pembelian (jurnal).

## 8. Status & Tandai
- ✅ Terverifikasi: insert kas keluar/masuk, ledger idempotent (`ModeKasBank`), scope account berarah, atomisitas insert, kasbon guard.
- ⚠️ Gap tinggi:
  1. Update kas keluar dan update/delete kas masuk tidak menyinkronkan jurnal. — FN-03/FN-04/X-07
  2. Scope unit tidak divalidasi pada operasi tulis (unit dari POST). — FN-05/X-06
  3. Aset tanpa role/scope, tidak atomik, field update tidak lengkap, cron publik. — FN-06
  4. Hanya sebagian route mutasi memakai CSRF. — FN-02/FN-07
  5. `GET /kas_masuk`, `GET /kategori_kas` tanpa auth; typo `fileter`. — FN-01/FN-08
- ⚠️ Belum diverifikasi penuh: body penuh `KasBank.php`, `TutupKasir.php`, `DashboardFinance.php`, `LabaRugi.php`, `SisiKeuangan.php`, `Asset.php` (metode & transaksi detail). Rujuk `tests/KasBank*`, `tests/TutupKasir*`, `tests/AssetBerjalan*`, `tests/unit/FinanceKpiTest`, dst.
- Test gap: tidak ada test untuk konsistensi jurnal pada edit/hapus kas masuk/keluar, scope tulis, dan route tanpa auth. Lihat X-08.
