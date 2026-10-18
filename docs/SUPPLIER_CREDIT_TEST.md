# Uji TEST: Retur, Kredit Supplier, dan Akuntansi

Jalankan hanya di ERP TEST dengan data simulasi. Branch PR tetap terpisah dari `main`.

## Urutan

1. Pasang branch PR dan jalankan migrasi `2026-10-18-000500`, `000600`, lalu `000700`.
2. Catat saldo awal Kartu Stok, utang supplier, piutang supplier, dan jurnal.
3. Buat pembelian simulasi supplier A: dua faktur tempo, Rp500.000 dan Rp300.000, satu batch barang yang dapat diretur.
4. Catat dua barang pada **Barang Rusak**. Stok jual turun dua unit.
5. Kirim satu dokumen **Retur Supplier** berisi satu barang rusak. Stok jual tidak turun lagi; kredit masih berstatus **menunggu** dan utang belum berubah.
6. Buka **Kredit Supplier** dari halaman retur. Masukkan nota kredit simulasi Rp200.000 dan pilih tiga akun sesuai kebijakan akuntansi iClear: Utang Supplier, Piutang Supplier, dan Pemulihan Barang Rusak.
7. Setujui. Total utang turun menjadi Rp600.000 tanpa mutasi kas/bank. Jurnal persetujuan dan pemakaian masing-masing seimbang.
8. Ulangi dengan kredit Rp700.000 ketika utang tinggal Rp600.000. Utang menjadi Rp0 dan sisa kredit Rp100.000 tampil sebagai piutang supplier.
9. Buat faktur tempo baru Rp150.000, lalu klik **Gunakan sisa kredit**. Utang faktur menjadi Rp50.000; sisa kredit menjadi Rp0; kas/bank tetap.
10. Coba setujui nota yang sama dua kali, jumlah retur di atas Barang Rusak, dan pembayaran yang melebihi sisa tagihan. Semua harus ditolak tanpa saldo/jurnal parsial.

## Pemeriksaan

- `php tests/smoke/linked_stock_view.php`
- `php tests/smoke/supplier_credit.php`
- `php tests/smoke/warranty_stock_view.php`
- `php tests/smoke/warranty_profit.php`
- `php tests/smoke/stock_valuation.php`
- `php tests/integration/WarrantyStockMySql.php` pada MySQL lokal yang boleh membuat database sintetis sementara.
- Jalankan PHPUnit pada runtime PHP yang cocok dengan dependensi proyek.
- Bandingkan `SUM(pembelian.sisa)` dengan total utang supplier pada dashboard dan posisi `hutang_piutang`.
- Bandingkan `SUM(kredit_retur_suplier.sisa_kredit)` berstatus disetujui dengan piutang supplier.
- Setiap referensi `kredit_retur_suplier` pada `jurnal` harus memiliki total debit sama dengan kredit.
- Jangan melakukan merge atau deployment produksi sebelum seluruh langkah di atas lulus pada commit TEST yang sama.

## Batasan historis

Retur lama tanpa hubungan ke Barang Rusak tidak direklasifikasi otomatis. Dokumen lama perlu rekonsiliasi manual agar stok historis tidak berubah diam-diam. Kredit lama yang pernah dicatat manual di Hutang Piutang juga perlu ditandai dan direkonsiliasi agar tidak diakui dua kali.

## Garansi pengganti

Migrasi `000700` menambahkan mutasi stok tersendiri untuk sparepart pengganti. Admin root wajib memilih akun beban dan persediaan pada menu `/akun_jurnal_garansi` sebelum penggantian baru. Mutasi stok dan jurnal debit beban/kredit persediaan disimpan dalam satu transaksi; bila akun atau HPP belum valid, keduanya ditolak. Simpan ulang formulir klaim yang sama tidak boleh mengurangi stok atau membuat jurnal kedua kali. Mutasi lama tanpa jurnal harus direkonsiliasi manual. Klaim kedua untuk **barang yang sama** belum mendukung lapisan HPP baru dalam baris service yang sama; jangan mengubah kuantitas lama atau menganggapnya sebagai penggantian baru tanpa desain histori per klaim. Stok TEST dan jurnal harus direkonsiliasi sebelum rilis.

## Batas laporan

Nilai Kartu Stok sekarang memakai sumber HPP yang sama dengan service, bukan harga beli master; baris tanpa HPP dan stok negatif ditandai sehingga total tidak dianggap final. Laba Rugi mode transaksi menghitung service awal saat klaim sedang diproses dan memisahkan HPP pengganti; mode jurnal baru mencakup pengganti yang sudah dijurnal setelah pemetaan akun diisi. Ringkasan rekonsiliasi Neraca pada Posisi Keuangan hanyalah indikator saldo jurnal; saldo awal, persediaan, dan eliminasi antarcabang belum dibuktikan. Jangan menyebut laporan ini final sebelum rekonsiliasi TEST lulus.
