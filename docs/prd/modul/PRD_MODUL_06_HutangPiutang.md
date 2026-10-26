# PRD Modul 06 — Hutang Piutang Terpusat & Pembayaran

| | |
|---|---|
| Kode Modul | HP-06 |
| Sumber analisis | `docs/prd/_analysis/Group_F_HutangPiutang.md` (lengkap) |
| Status verifikasi | ✅ fakta / 🔶 inferensi / ⚠️ ketidakpastian |

---

## 1. Tujuan
Menyediakan registry terpusat posisi hutang & piutang (tabel `hutang_piutang`), menghindari double counting, dan menangani pembayaran/cicilan, kompensasi, serta pelunasan legacy. Juga memelihara modul lama pembayaran hutang & piutang (legacy).

## 2. Aktor
- Finance/ROOT/Direktur/Manager (role 0,1,2,34): boleh mengisi (`financeInputRoles`).
- CS/frontliner: melihat.
- KasBank: menangani pelunasan antar-unit.

## 3. Preconditions
- Login; role dalam `financeInputRoles` untuk input.
- Cut-off config: `FinanceScopeService::cutoffDate()=2026-10-05`, `periodeMulaiDate=2026-10-06`.

## 4. Alur Utama
### 4.1 Buat posisi (authoritative)
1. `HutangPiutang::form` → pilih `sumber_tipe` dari `inputTypes()`.
2. `store` → validasi `canInput()` + `sumber_tipe` ∈ `inputTypes()` → `service->createPosition()`.
3. `createPosition` memaksa `pihak_tipe` & `jenis` sesuai sumber; tolak total ≤ 0 / negatif; transaksi DB.
4. Efek samping: kasbon → baris `kas_keluar` + jurnal; piutang pelanggan → jurnal; jenis manual/jasa/kelebihan/retur **tidak** diposting jurnal (COA belum disetujui).
### 4.2 Bayar cicilan — `bayar()`
1. Row lock `FOR UPDATE`; validasi sisa; insert `pembayaran_hutang_piutang`; update saldo; post jurnal per metode (tunai/bank).
### 4.3 Kompensasi — `kompensasi()`
1. Hanya bila ada lawan kompensasi (authoritative, non-projection, non-lunas, pihak sama).
2. Lock dua baris; min(sisa) sebagai nilai; insert `kompensasi_hutang_piutang` + 2 baris audit; tanpa perpindahan uang.
### 4.4 Tandai/batalkan lunas legacy
1. Hanya non-projection, outstanding, tanggal ≤ cut-off.
2. `tandaiLunasLegacy`: set sisa 0 + `sisa_legacy_sebelum` + `tanggal_settlement_legacy` + `cutoff_reason='legacy'`. **Tanpa jurnal/pembayaran/movement.**
3. `batalkanLunasLegacy`: kembalikan sisa, status dihitung ulang, cabut penanda.
### 4.5 Cetak & umur
`cetak/{id}` (mPDF); `umur_hutang/umur_piutang` + ekspor; `daftar_tagihan/daftar_piutang`.

## 5. Business Rules (Bukti)
1. Hanya sumber authoritative yang bisa dibayar/dikompensasi/ditandai lunas. — `HutangPiutangService::AUTHORITATIVE_SUMBER`, `bayar`, `kompensasi`, `tandaiLunasLegacy`
2. Status = fungsi sisa & total_dibayar (`statusFromSisa`). — `HutangPiutangService::statusFromSisa`
3. Pembayaran tidak boleh melebihi sisa (row lock `FOR UPDATE`). — `bayar`
4. Kompensasi hanya pihak sama, jenis berlawanan, non-lunas, dibatasi min sisa. — `kompensasi`
5. Kasbon: jatuh tempo tanggal 1 bulan berikutnya; pihak dipaksa pegawai; jenis piutang; pencairan membuat `kas_keluar` (kategori Kasbon, no_akun 1020201000). — `createPosition`
6. Settlement kasbon dari payroll idempotent. — `settleKasbonFromPayroll`
7. Jurnal H/P via `template_jurnal`; bila template absent → hanya log, transaksi tetap tersimpan. — `postJurnal`
8. Settlement legacy bukan pembayaran (tidak ubah `total_dibayar`, tidak sentuh saldo/ledger). — `tandaiLunasLegacy`
9. Projection disinkron dari sumber (`syncFromPembelian`, `syncFromPiutangLegacy`); lunas-legacy projection ditimpa sync → ditolak. — `refreshProjections`. **`syncFromPembelian` mengisi `total` dari `pembelian.total_bayar` (nilai yang sudah dibayar), bukan `total_transaksi`; untuk pembelian sebagian total/sisa/status salah.** — `HutangPiutangService::syncFromPembelian` (PI-05/HP-01)
10. Kode normalisasi `<SEG>-Ymd<unit><seq4>`. — `generateKode`
11. `Piutang::bayar_piutang` (legacy) mengambil `sisa` dari POST, menyimpan `pembayaran_piutang`, memperbarui `piutang.sisa_hutang` dengan nilai itu, memakai `if ($sisa >= 0) 0 else 1` sehingga status Lunas tidak pernah tercapai, dan tidak membuat jurnal/ledger kas. — `Piutang.php:153-190` (HP-02/SL-06)
12. `syncFromPiutangLegacy` hanya menyinkronkan projection; tidak memperbaiki pembayaran legacy di atas. — `HutangPiutangService`

## 6. Acceptance Criteria
- [ ] Buat hutang/piutang authoritative tersimpan; jenis manual divalidasi.
- [ ] Bayar cicilan tidak bisa melebihi sisa; transaksi diserialisasi.
- [ ] Kompensasi hanya untuk pihak yang sama; tercatat + audit 2 baris.
- [ ] Lunas legacy konsisten (tanpa jurnal/movement) & dapat dibatalkan.
- [ ] Dashboard/ringkasan tidak double counting; legacy dikecualikan.
- [ ] Cetak bukti & ekspor umur hutang/piutang berfungsi.

## 7. Dependensi
- `ModelHutangPiutang`, `ModelPembayaranHutangPiutang`, `ModelKompensasiHutangPiutang` (migration 000400), `ModelJurnal`.
- Service: `HutangPiutangService`, `FinanceScopeService`, `KasBankScopeService`, `HutangTimelinessCalculator`, `PiutangTimelinessCalculator`.
- Modul: KasBank (antar-unit), Pembelian (projection), Payroll (kasbon), Kas keluar, KPI Finance.

## 8. Status & Tandai
- ✅ Terverifikasi: aturan di atas (dengan bukti `HutangPiutangService::method`).
- ⚠️ Gap terkonfirmasi:
  1. **Projection pembelian memakai `total_bayar`**, bukan `total_transaksi`; total/sisa hutang supplier bisa salah. — HP-01/PI-05
  2. **Pembayaran piutang legacy** memakai `sisa` dari POST, tidak memposting kas/jurnal, dan status Lunas tidak pernah tercapai. — HP-02/SL-06
  3. **KPI Piutang selalu null** karena `pembayaran_piutang` tidak punya `tanggal_bayar` (status `gap_no_payment_date`). — `PiutangTimelinessCalculator` (HP-03)
- ⚠️ Inferensi/risiko: pembayaran tanpa rincian metode dipaksa tunai; jurnal manual tidak terposting; dua mekanisme guard saldo paralel; enum `sumber` untuk `antar_unit/kas_bank/legacy` belum terkonfirmasi.
- Test: `HutangPiutangDetailLinkTest`, `HutangPiutangListRedesignTest`, `ScopeConsolidasiTest`, `ScopeFinanceRoleTest`, `FinanceKpiTest`. Tidak ada test untuk projection `total_bayar`, pembayaran piutang legacy, dan KPI timeliness.
