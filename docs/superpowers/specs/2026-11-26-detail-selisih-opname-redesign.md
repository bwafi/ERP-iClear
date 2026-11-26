# Redesign Detail Selisih Stok Opname

Tanggal: 2026-11-26
Halaman: `/stok_opname` — section Riwayat Periode Opname
Status: disetujui lewat instruksi "lanjut" (mode Operate)

## Tujuan

Modal Detail Selisih saat ini hanya tabel polos. Angka per barang bisa jauh
lebih besar dari total periode karena plus dan minus saling menutup, sehingga
user mengira ada salah hitung. Redesign harus membuat rekonsiliasi itu terbaca
dalam sekali lihat.

## Arah

Tetap memakai modal (bukan drawer/halaman baru), karena aksi ini adalah
pemeriksaan cepat dari tabel riwayat. Bahasa visual mengikuti token halaman
(`_opname_theme.php`), bukan sistem visual baru.

### Struktur

1. **Header** — eyebrow "Riwayat Opname", judul, meta tanggal + chip status.
2. **Papan ringkasan** tiga sel: Total selisih periode (angka besar), Selisih
   plus (jumlah barang), Selisih minus (jumlah barang). Peringatan muncul bila
   jumlah baris tidak sama dengan ringkasan periode.
3. **Toolbar** — saringan Semua / Plus / Minus dengan jumlah baris.
4. **Tabel** — header sticky, kolom Barang (kode + nama + jenis/warna),
   Stok Komputer, Jumlah Real, Selisih. Selisih memakai angka tabular dan
   batang divergen dari garis tengah agar besar/kecilnya terbaca.
5. **Footer** — total periode dan catatan bahwa nilai per barang bisa lebih
   besar dari total.

### Perilaku

- Saringan merender ulang baris dan menskala ulang batang terhadap nilai
  absolut terbesar pada hasil saringan.
- Kolom Stok Komputer disembunyikan di layar < 576px agar nama barang tetap
  terbaca.
- Angka diformat `id-ID`, nama di-escape, dan status memakai chip yang sama
  dengan riwayat.

## Di luar scope

- Tidak mengubah query, payload, atau aturan bisnis.
- Tidak mengubah tombol Detail di tabel riwayat.
