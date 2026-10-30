# Kolom `TERIMA_GAJI` pada akun — penentu per-akun "digaji atau tidak"

Tanggal: 2026-10-19

## 1. Intent & kenapa

"Apakah suatu akun menerima gaji" sekarang ditentukan oleh daftar **jabatan
yang di-hardcode**, dan aturannya **beda-beda di tiap halaman**:

| Tempat | Aturan sekarang |
|---|---|
| `TutupKasir::assetberjalan()` loop Total Gaji | **tidak membuang siapa pun** |
| `UnitSalaryCalculationService::calculateForUnit()` (kembaran replika) | tidak membuang siapa pun |
| `/gaji` → `PenilaianKPI::gaji()` daftar karyawan | buang jabatan **1** saja |
| `/penilaian_kinerja` → daftar karyawan | buang jabatan **1 & 2** |
| `/payroll` generate | konstanta `PayrollGenerator::JABATAN_TANPA_GAJI = [1, 2]` |
| Distribusi Gaji HO (`/asset_berjalan`) | tidak membuang siapa pun |

Akibat nyata (terukur, 2026-10-19): di `/asset_berjalan?unit=1` **Admin Root
(ID_AKUN 1) dan 5 ikut masuk Total Gaji** — Rp 3.350.000 dari total
Rp 7.977.742. Karena `/gaji` hanya membuang jabatan 1 sementara `/penilaian`
membuang 1 & 2, angka antar-halaman pun tidak konsisten.

Tujuan: **satu penanda per akun** supaya aturannya tunggal, bisa diatur sendiri,
dan dipakai seragam di semua perhitungan gaji.

## 2. Keputusan pengguna (dikonfirmasi)

- Penanda nempel **per akun** (bukan per jabatan) — satu orang bisa beda walau
  jabatannya sama.
- Berlaku di **semua** perhitungan gaji: `/asset_berjalan`, `/gaji`,
  `/penilaian_kinerja`, `/slip_gaji`, `/payroll`, distribusi HO.
- Diisi lewat UI halaman **`/pegawai`** (form tambah/edit pegawai).
- Backfill awal: hanya **Admin Root (jabatan 1)** dan **Direktur (jabatan 2)**
  yang "tidak digaji". Sisanya "digaji". Daftar ini diisi sendiri oleh user
  lewat UI nanti.

## 3. Goals / Non-goals / Anti-goals

**Goals**

- Kolom `TERIMA_GAJI` (TINYINT 1, default **1**) di tabel `akun`.
- Satu sumber kebenaran: semua tempat baca kolom ini, hardcode jabatan dihapus.
- Akun "tidak digaji" **hilang dari Total Gaji `/asset_berjalan`** dan dari
  daftar/hitungan gaji lain.
- Bisa di-toggle per akun di `/pegawai`.

**Non-goals**

- Tidak mengubah rumus gaji, tunjangan, insentif, atau KPI.
- Tidak menghapus/mengubah baris `finance_payroll` yang sudah ada. **Revisi 2026-10-30:** draft `rencana` milik akun `TERIMA_GAJI=0` dihapus saat Susun Otomatis; baris `dibayar` tetap tidak diubah.
- Tidak menambah halaman/route baru.
- Tidak memperbaiki filter lain yang sudah ada (mis. `STATUS_PEGAWAI`,
  `deleted`) — filter itu dibiarkan seperti sekarang di tiap tempat.
- Tidak menyentuh logika non-gaji. Halaman **input** KPI (`/penilaian`,
  `/penilaian/kpi`) tidak diubah — akun tanpa gaji tetap bisa dinilai KPI-nya.
  Yang berubah hanya **daftar di halaman gaji/scorecard** (`gaji()`,
  `penilaian_kinerja()`); kedua daftar itu **sudah** membuang jabatan 1 & 2
  hari ini, jadi tidak ada yang "baru hilang" setelah kolom ini dipasang.

**Anti-goals** (gagal walau "jalan")

- Total Gaji untuk akun yang tetap "digaji" berubah sedikit pun.
- Admin Root / Direktur masih muncul atau terhitung di salah satu tempat.
- Nilai kolom hilang saat simpan (mis. checkbox unchecked tersimpan sebagai
  `NULL` sehingga akun ikut terhitung gaji).

## 4. Perubahan teknis

1. **Migrasi** `2026-10-19-000100_AddTerimaGajiToAkun.php`
   - up: `ALTER TABLE akun ADD COLUMN TERIMA_GAJI TINYINT(1) NOT NULL DEFAULT 1`
     lalu `UPDATE akun SET TERIMA_GAJI = 0 WHERE ID_JABATAN IN (1, 2)`.
   - down: drop kolom.
   - Default 1 = perilaku lama dipertahankan untuk akun lain.

2. **Model** `app/Models/ModelAuth.php`
   - tambah `'TERIMA_GAJI'` ke `$allowedFields` (wajib: `Pegawai::update()`
     memakai `ModelAuth->update()` yang menegakkan `allowedFields`, sehingga
     tanpa ini nilai tidak akan tersimpan).

3. **Enforcement** — ganti filter jabatan hardcode jadi `where('TERIMA_GAJI', 1)`:
   - `TutupKasir::assetberjalan()` query `$karyawanUnit` (~baris 805)
   - `UnitSalaryCalculationService::calculateForUnit()` query `$karyawanUnit` (~78)
   - `PenilaianKPI::gaji()` list (~2262): ganti `where('ID_JABATAN !=', 1)`
   - `PenilaianKPI::penilaian_kinerja()` list (~1533): ganti dua `where ID_JABATAN !=`
   - `PayrollGenerator`: **jangan** filter `daftarKaryawan()`. Sebagai gantinya
     tambah `TERIMA_GAJI` ke `select()`-nya, lalu ganti cek
     `JABATAN_TANPA_GAJI` (~137) jadi `(int) $karyawan['TERIMA_GAJI'] === 0`.
     Hapus konstanta `JABATAN_TANPA_GAJI`. Alasan: blok itu satu-satunya
     penghasil hitungan `dikecualikan` dan baris `status => 'excluded'` yang
     dibaca `/payroll` untuk memberi tahu Finance siapa yang dilewati — kalau
     difilter di query, laporan itu hilang. Sekalian ganti teks alasannya
     (~144) dan pesan `Payroll::pesanGenerate()` (~378) dari
     "karena jabatannya" jadi "karena tidak menerima gaji".
   - `HoSalaryDistributionService::calculateDistribution()` query `$hoEmployees` (~75)

4. **UI** `app/Views/pegawai/list_pegawai_new.php` + `app/Controllers/Pegawai.php`
   - checkbox "Terima Gaji" di modal tambah dan modal edit.
   - `Pegawai::insert()`: tambah `'TERIMA_GAJI' => post ? 1 : 0` ke array insert.
   - `Pegawai::update()`: idem ke array update.
   - baris tabel: teruskan nilai lewat `data-terima_gaji` + prefill di JS edit modal.
   - `ModelAuth::getAkunPegawai()` memakai `akun.*` sehingga kolom otomatis terbawa.

5. **Test** — tambah assertion kecil: akun ber-`TERIMA_GAJI = 0` tidak ikut
   dijumlah/daftar; akun ber-`TERIMA_GAJI = 1` tetap ikut. Ikuti pola script
   regresi yang sudah ada di `app/Scripts/` (`assetberjalan_regression.php`).

## 5. Panggilan yang aku ambil (review ini saat baca)

- Enforcement di **level query/loop** (siapa yang muncul & dijumlah), **bukan**
  di `SalaryCalculationService::calculateSalary()`. Service itu tetap kalkulator
  murni; ini juga pola yang sudah dipakai `PayrollGenerator` hari ini.
- **Batas lingkup: daftar & total, bukan halaman detail.** Halaman detail gaji
  (`/gaji` yang default memilih `session('ID_AKUN')`, `/penilaian_kinerja`
  dengan `?karyawan=<id>`, dan `/penilaian/slip_gaji/{id}`) **tidak** diblokir
  untuk akun "tidak digaji". Alasannya: yang dibuang flag ini adalah *siapa
  yang muncul di daftar dan dijumlah*, dan jalur uang sebenarnya adalah
  register `/payroll` (sudah ikut dibuang). Halaman detail hanya tampilan
  satu-akun dan tidak memindahkan uang. Kalau nanti kamu mau akun tanpa gaji
  benar-benar tidak bisa membuka slip sendiri, itu permintaan terpisah.
- Backfill terverifikasi di `erp_local`: jabatan 1 = ID_AKUN 1 & 5, jabatan 2 =
  ID_AKUN 46 & 70, jadi `WHERE ID_JABATAN IN (1,2)` memang menutup keempatnya.
- Nilai checkbox dinormalisasi ke `0/1` di controller (`? 1 : 0`), bukan
  mengandalkan default DB, supaya tidak pernah tersimpan `NULL`.
- `Pegawai::update()` baris 198 (`$THIS->AuthModel`) punya typo lama yang tidak
  aku sentuh — di luar lingkup.
