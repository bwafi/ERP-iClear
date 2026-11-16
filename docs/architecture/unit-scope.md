# Unit Operasional vs Non-Operasional

**Berlaku sejak:** 11 Oktober 2026 (`unit.is_operational`)
**Diperbarui:** 16 November 2026 — konsolidasi aturan lintas modul untuk agent.
**Status bukti:** `✅` terverifikasi di kode/migration · `🔶` inferensi · `⚠️` belum terverifikasi.

Dokumen ini adalah sumber detail untuk guardrail **Unit operasional vs Head Office** di `AGENTS.md`. PRD modul tetap menjadi sumber aturan bisnis per modul; dokumen ini menjelaskan cara flag `unit.is_operational` dipakai di seluruh aplikasi.

---

## 1. Definisi

- `unit.is_operational` adalah penanda di master `unit`: `1` = unit operasional (toko/cabang), `0` = unit non-operasional. ✅
- Kolom ditambahkan oleh `app/Database/Migrations/2026-10-11-000000_AddIsOperationalToUnit.php` dengan default `1`, lalu migration yang sama menandai `idunit = 50` sebagai non-operasional. ✅
- Default `1` dipilih agar unit lama tidak otomatis dibebaskan dari kewajiban operasional; hanya unit yang memang terverifikasi non-operasional yang ditandai `0`. ✅
- Head Office menggunakan `idunit = 50` (hasil `2026-09-14-001000_RenumberHOUnitTo50`) dan ber-flag `0`. ✅
- Jangan menganggap "non-operasional" berarti "tidak boleh punya data". Unit non-operasional tetap bisa punya akun, karyawan, rekening, dan laporan; yang dibatasi adalah **pemakaiannya sebagai unit transaksi operasional**.

## 2. Helper yang tersedia

| Helper | Perilaku | Catatan |
|---|---|---|
| `ModelUnit::getUnitOperasional()` | `where('is_operational', 1)->orderBy('idunit')` | ✅ Dipakai `/pembelian`. |
| `ModelUnit::isOperational(int $idunit)` | Baca flag satu unit; unit tidak ditemukan → `true` | ✅ Fallback `true` sengaja agar unit tak dikenal tidak dianggap non-operasional; pemanggil tetap harus memvalidasi ID. |

Jangan menambah daftar unit hardcoded atau helper baru sebelum memeriksa kedua method di atas.

## 3. Pemakaian di kode

| Area | Perilaku | Bukti |
|---|---|---|
| Pembelian | Role lintas unit (0, 1, 34) memilih dari unit operasional. GET/POST unit non-operasional ditolak; akun lintas unit yang bernaung di unit non-operasional default ke unit operasional pertama. Tim toko selalu memakai unit akun. | ✅ `Pembelian::resolveUnitPembelian`, `Pembelian::daftarIdUnitOperasional`, `ModelUnit::getUnitOperasional`; PRD Modul 03 rule 18 |
| Service — proses service | Filter unit untuk role lintas unit hanya berisi unit operasional. | ✅ `Riwayat_Service::proses_service` |
| Service — sparepart/garansi | `GaransiClaimService::canSeeAllUnits` → filter `is_operasional = 1`; role lain dikunci ke unit akun. | ✅ `Riwayat_Service::sparepartUnitScope` |
| Status garansi | Sama: role lintas unit hanya melihat unit operasional. | ✅ `StatusGaransi::index` |
| KasBank — kewajiban opening KAS | Hanya laci KAS unit operasional yang wajib punya baseline opening. Rekening Finance/HO diatur terpisah oleh `is_finance_ho`. | ✅ `KasBank::index`, `KasBankCutoffService::unitOperasional` |
| Payroll — distribusi gaji HO | Unit 50 dikecualikan dari daftar unit pembagi; unit non-operasional dan trial juga tidak dihitung. Karyawan HO (unit 50) tetap diproses sebagai penerima. | ✅ `HoSalaryDistributionService` |

### Bukan pemakaian flag ini

- **KasBank — hak rekening operasional:** `KasBankScopeService::unitPunyaRekeningOperasional()` mendefinisikan "operasional" dari ada/tidaknya rekening **BANK aktif non-Finance/HO**, bukan dari `unit.is_operational`. Jangan mengganti logikanya dengan flag tanpa analisis. ⚠️
- **Scope laporan keuangan:** `FinanceScopeService` mengembalikan seluruh unit (termasuk HO) untuk role lintas unit karena laporan bersifat konsolidasi. ✅

## 4. Pengecualian yang sah

Unit non-operasional (Head Office) tetap dipakai untuk:

- Payroll karyawan HO dan distribusi gaji HO. ✅
- KPI/insentif HO: pool unit 50 dan target global memakai unit non-trial sesuai `docs/architecture/kpi-business-rules.md`. ✅
- Laporan konsolidasi dan dashboard Finance yang mencakup seluruh unit. ✅
- Rekening Finance/HO (`is_finance_ho = 1`) dan rekening shared. ✅

Jangan menyaring unit non-operasional di area tersebut tanpa instruksi eksplisit dan regression test.

## 5. Aturan untuk agent

1. **Gunakan flag, bukan ID.** Jangan hardcode `50` atau daftar unit di kode baru; baca dari master. Pengecualian: migration yang memang menetapkan nilai awal HO.
2. **Pemilih unit operasional** (transaksi, filter lintas unit, laporan operasional) harus memakai `getUnitOperasional()` atau filter `is_operational = 1`.
3. **Validasi di server.** Unit dari GET/POST tidak boleh dipercaya. Unit non-operasional atau tidak valid harus ditolak dan jatuh ke aturan yang jelas (unit akun untuk role terkunci, unit operasional pertama untuk role lintas unit), bukan diteruskan.
4. **Bedakan unit transaksi dari unit organisasi.** Unit akun boleh non-operasional (mis. Finance di HO); yang dibatasi adalah unit yang dipakai untuk transaksi operasional.
5. **Perubahan semantik** `is_operational` harus memperbarui dokumen ini, PRD modul terkait, dan menambah regression test.
6. **Jangan menyamakan "operasional" KasBank** (rekening bank aktif) dengan flag `is_operational` (unit toko). Keduanya konsep berbeda.

## 6. Verifikasi

- ✅ Kolom, default, dan penetapan HO: `2026-10-11-000000_AddIsOperationalToUnit.php`.
- ✅ Daftar unit operasional dev saat ini: 1 Probolinggo, 2 Jember, 3 Banyuwangi, 4 Pandaan, 5 Genteng; unit 50 Head Office `is_operational = 0` (dicek lewat `ModelUnit::getUnitOperasional()` di container app, 16 Nov 2026).
- ✅ Test: `PembelianUnitInputTest`, `PembelianPageTest`, `PembelianHppUnitTest` (unit pembelian & fallback unit operasional).
- ⚠️ Service/garansi/KasBank/payroll belum memiliki test khusus yang mengunci aturan `is_operational`; perilaku di tabel §3 diverifikasi lewat pembacaan kode, bukan test regresi.

## 7. Referensi

- `AGENTS.md` §C — guardrail ringkas.
- `docs/prd/modul/PRD_MODUL_03_Pembelian_Persediaan.md` rule 18 — aturan unit pembelian.
- `docs/prd/_analysis/Group_D_Service_Garansi.md` — ringkasan scope unit Service/Garansi.
- `docs/architecture/kpi-business-rules.md` — pemakaian unit 50 untuk insentif HO.
