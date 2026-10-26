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
5. Update/delete: hapus posting lama, posting ulang, sinkron kasbon (`HutangPiutangService::guardKasbonKasKeluar/syncKasbonDariKasKeluar/hapusKasbonDariKasKeluar`).
### 4.2 Kas Masuk — `Kas_Masuk::insert_kas_Masuk` (pola serupa, `postingKasMasuk`).
### 4.3 Jurnal & Laporan
- `Jurnal::index/export_jurnal`; `SisiKeuangan::index/export_pdf/export_excel`; `LabaRugi::index/laporan_unit/laporan_perbandingan_periode/cetak_*`.
### 4.4 KasBank (modul terpusat)
- Akun (`saveAkun`), saldo awal (`saveSaldoAwal`), alokasi saldo (`saveAlokasiSaldo`), opening kas (`saveOpeningKas`).
- Transfer internal (`saveTransfer`), antar unit (`saveAntarUnit`), setor tunai (`saveSetorTunai`), penarikan tunai (`savePenarikanTunai`), reversal.
- Posting ke `transaksi_kas_bank` via `ModeKasBank` (resolve akun, scope source/destination, idempotent guard).
- CSRF aktif per-route menulis ledger.
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
5. **Unit filter**: role 0/1/2/34 bebas pilih unit; lainnya dibatasi (unit sendiri / `spv_units`). Request unit di luar scope jatuh ke unit pertama diizinkan. — `Kas_Keluar::bebasPilihUnit/unitFilterTerpilih`
6. **Transaksi kas keluar** → posting ledger + jurnal, dipisah `debet/kredit` berdasar `posisi_drk`. — `Kas_Keluar::insert_kas_keluar`
7. **Kasbon**: baris `kas_keluar` dari payroll menyimpan `penerima=ID_AKUN`; guard mencegah ubah/hapus kasbon yang sudah lunas. — `HutangPiutangService::guardKasbonKasKeluar`
8. **Tutup kasir**: perhitungan saldo server-side; browser hanya kirim `cash_laci`; one-time per hari per unit. — `PRODUCT.md`; `TutupKasirClosing::hitung`
9. **CSRF per-route** untuk semua penulisan ledger kas-bank & tutup kasir. — `Routes.php`
10. **Reversal** menghapus posting lalu post ulang (idempotent). — `ModeKasBank::hapusPosting`

## 6. Acceptance Criteria
- [ ] Insert/update/delete kas keluar & masuk atomik & konsisten dengan ledger & jurnal.
- [ ] Role terbatas tidak dapat melihat/memfilter data unit di luar scope.
- [ ] Rekening di luar entitlement ditolak (bukan di-skip diam-diam).
- [ ] Tutup kasir per hari per unit hanya satu kali; PDF bukti tersedia.
- [ ] Transfer internal & antar unit serta setor/tarik tunai tercatat di ledger dan dapat di-reversal.
- [ ] Laporan posisi keuangan & laba rugi dapat dicetak (PDF/Excel).

## 7. Dependensi
- `ModelKasMasuk/ModelKasKeluar/ModelJurnal/ModelNoAkun/ModelKategoriKas/ModelAkunKasBank/ModelTransaksiKasBank/ModelSaldoAwalKasBank/ModelAlokasiSaldoKasBank/ModelOpeningKas/ModelTutupKasir/ModelAsset`.
- Service `Finance/*` (Scope, Entitlement, Cutoff, BankRekeningValidator, TutupKasir*, dll.), `ModeKasBank`.
- Modul: Hutang Piutang (kasbon), Payroll (kas_keluar), Penjualan/Pembelian (jurnal).

## 8. Status & Tandai
- ✅ Terverifikasi penuh: kas keluar, ledger idempotent, scope unit/account, transaksi atomik, CSRF, kasbon guard.
- ⚠️ Belum diverifikasi penuh: body penuh `KasBank.php` (2989 baris), `TutupKasir.php`, `DashboardFinance.php`, `LabaRugi.php`, `SisiKeuangan.php`, `Asset.php` (metode & transaksi detail). Rujuk `tests/KasBank*`, `tests/TutupKasir*`, `tests/AssetBerjalan*`, `tests/unit/FinanceKpiTest`, dst.
