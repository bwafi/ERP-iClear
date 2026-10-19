# Permintaan data audit Neraca TEST

**Pesan siap kirim ke tim IT**

> Mas, mohon bantu jalankan audit read-only Neraca pada database TEST sesuai file `docs/NERACA_TEST_READONLY_AUDIT.sql` di branch PR #16. Jangan menjalankan `INSERT`, `UPDATE`, `DELETE`, migrasi, atau mengubah transaksi. Angka yang sedang dicocokkan adalah Neraca per **18 Oktober 2026**, **gabungan semua cabang/unit** (parameter `@as_of_date = '2026-10-18'`, `@unit_id = NULL`). Mohon pastikan schema yang dipakai memang TEST dan kirim versi server/schema tanpa kredensial.
>
> Jalankan query dalam urutan file, dari `SET` dan `START TRANSACTION READ ONLY` sampai `COMMIT`. Ekspor setiap result set sebagai CSV terpisah dengan nama `01_schema_version.csv` sampai `08_inventory_gl.csv` (jika result set terakhir kosong/terhalang, kirim pesan error saja). Untuk audit saldo historis, cukup result set 01–07; result set 08 hanya membandingkan ledger dan buku besar pada tanggal hari ini, sehingga **jangan** menggunakannya untuk cutoff 18 Oktober 2026.
>
> Hasil minimum yang dibutuhkan: 01 schema/version/read-only; 02 angka Aset, Kewajiban, Ekuitas, Laba Berjalan, selisih Neraca dan selisih total jurnal; 03 saldo debet/kredit per akun dan unit; 04 referensi jurnal yang tidak seimbang; 05 baris jurnal tanpa referensi lengkap; 06 akun kosong/tidak valid/tidak ada di master; 07 referensi saldo awal per unit. Sertakan jumlah baris dan total hasil ekspor. Jangan sertakan nama, telepon, alamat, IMEI, deskripsi transaksi, atau kredensial. Bila nama tabel/kolom tidak cocok atau query ditolak, jangan mengubah SQL di server; kirim nomor query dan pesan error persisnya agar bisa disesuaikan.
>
> Jangan deploy atau mengubah versi TEST saat tim sedang menguji. Kirim hasilnya lewat kanal internal yang disetujui.

## Urutan eksekusi

1. Buka sesi database **read-only** khusus TEST. Jangan memakai koneksi production.
2. Buka `NERACA_TEST_READONLY_AUDIT.sql` dan verifikasi parameter di bagian paling atas tetap tanggal `2026-10-18` dan `NULL` untuk seluruh unit.
3. Jalankan seluruh skrip berurutan. Skrip memulai transaksi `READ ONLY` dan tidak menampilkan deskripsi transaksi atau data pelanggan.
4. Ekspor hasil query satu per satu; beri nama berurutan seperti pada pesan di atas. Query 08 bersyarat tanggal hari ini dan bukan bukti historis.
5. Kirim file CSV hasil query, jumlah baris, total baris, schema/version, dan error apa pun. Jangan sertakan identitas pelanggan atau kredensial.

## Format ringkasan balasan minimum

```text
Target: TEST
Server: <versi DB>
Cutoff: 2026-10-18
Cakupan: semua unit/cabang
Read-only: ya/tidak
Q01: selesai/error; jumlah baris
Q02: aset=...; kewajiban=...; ekuitas=...; laba berjalan=...; selisih neraca=...; selisih jurnal=...
Q03: CSV terlampir; jumlah baris=...
Q04: CSV terlampir; jumlah baris=...
Q05: CSV terlampir; jumlah baris=...
Q06: CSV terlampir; jumlah baris=...
Q07: CSV terlampir; jumlah baris=...
Q08: dilewati/tidak applicable/error (bukan cutoff historis)
Error: <nomor query dan pesan, bila ada>
```

Jangan menambahkan jurnal penyeimbang atau mengoreksi data historis selama proses pengumpulan bukti.
