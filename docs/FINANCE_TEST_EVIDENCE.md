# Bukti uji paket Keuangan

## MariaDB sintetis

Pada 19 Oktober 2026, pengguna melaporkan runner `scripts/run-isolated-mariadb-finance-test.sh` lulus dengan PHPUnit 9.6.38: `OK (1 test, 56 assertions)`. Ini adalah hasil satu skenario MariaDB sintetis `InventoryBatchMariaDbTest`; SHA checkout yang diuji tidak disertakan dalam laporan tersebut. Hasil ini membuktikan hanya fixture yang dijalankan, bukan data atau rekonsiliasi TEST.

Runner telah diperluas agar eksekusi berikutnya juga menjalankan suite unit finance dan skenario MariaDB sintetis garansi/kredit terpisah. Hasil perlu dicatat ulang setelah versi runner terbaru dijalankan.

## Unit non-DB lokal

Pada checkout kerja, 13 kelompok unit terkait Neraca, arus kas, stok, garansi, supplier credit, menu, dan filter lulus: 50 tes, 143 asersi (PHPUnit 9.6.38). Ini tidak menggantikan pengujian MariaDB.

## Belum terbukti

- Integrasi browser dan role pada commit terbaru yang terpasang di TEST.
- Rekonsiliasi saldo Neraca TEST per 18 Oktober 2026 untuk gabungan seluruh cabang; hasil SQL read-only dari IT belum diterima.
- Kelengkapan posting jurnal terhadap semua transaksi sumber, eliminasi antarcabang, serta kecocokan laporan keuangan aktual.
