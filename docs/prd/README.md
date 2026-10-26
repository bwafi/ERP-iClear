# Dokumentasi PRD — ERP

Folder ini berisi **Product Requirements Document berdasarkan implementasi aktual** (baca-saja; tidak ada perubahan kode/DB/config).

## Struktur
- `PRD_MASTER.md` — PRD induk: ringkasan sistem, inventarisasi 10 modul, aturan lintas-modul, matriks cakupan verifikasi, temuan gap/konflik, rekomendasi prioritas.
- `modul/` — PRD per modul (10 file), masing-masing memuat: Tujuan, Aktor, Preconditions, Alur Utama/Alternatif, Business Rules (dengan bukti `File.php::method()`), Acceptance Criteria, Dependensi, Status verifikasi.
- `_analysis/` — hasil analisis mentah per kelompok modul (bukti & temuan detail):
  - `00_cross_cutting.md` — analisis lintas-modul
  - `Group_A_Inti_MasterData.md`
  - `Group_F_HutangPiutang.md`
  - `Group_I_Marketing_Konten_Sosial.md`
  - `Group_J_Integrasi_Cross.md`

## Legenda Status Verifikasi
- ✅ Fakta terverifikasi dari kode
- 🔶 Inferensi (disimpulkan dari kode)
- ⚠️ Ketidakpastian / belum diverifikasi penuh

## Catatan Methodologi
- Semua business rule ditulis hanya bila ada bukti kode; jika tidak, ditandai ⚠️/🔶.
- Keberadaan route/tombol tidak dianggap bukti fitur berfungsi.
- Test yang lulus tidak membuktikan seluruh business rules benar.
