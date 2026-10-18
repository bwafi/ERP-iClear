# Deploy dan verifikasi ERP iClear di TEST

Target dokumen ini **hanya** `testerp.icleargroup.id`. Jangan merge ke `main`, menjalankan migrasi di produksi, atau menyalin `.env`/database produksi.

## Prasyarat

1. Tim IT mencatat commit branch `codex/hpp-garansi-audit-20261018` yang akan dipasang dan memastikan seluruh perubahan lokal sudah masuk commit tersebut. Jangan deploy dari branch yang tertinggal.
2. Pastikan checkout dan `app.baseURL` menunjuk ke TEST; koneksi database adalah database TEST terisolasi. Jangan tampilkan password atau isi `.env` pada tiket/chat.
3. Buat snapshot database dan file TEST sesuai prosedur tim IT. Siapkan cara memulihkan checkout serta database sebagai **satu pasangan**.
4. Gunakan PHP 8.2–8.4 dengan ekstensi yang disyaratkan lock file; gate lokal dijalankan pada PHP 8.4.26. Branch ini menyelaraskan entrypoint dengan CodeIgniter 4.7.5 pada lock file. Jangan menjalankan TEST dengan vendor framework versi lama atau PHP 8.5.

## Urutan

1. Di checkout TEST, ambil commit yang disetujui dari branch pengembangan. Verifikasi `git rev-parse HEAD` sama dengan hash yang diserahkan.
2. Jalankan `composer install` sesuai lock file, tanpa memperbarui dependency atau mengabaikan syarat versi PHP.
3. Pastikan koneksi database dan backup menunjuk TEST. Jalankan `php spark migrate` **hanya di TEST**. Jangan jalankan rollback otomatis; beberapa migrasi menolak rollback bila data sudah tercatat.
4. Pastikan `barang.jenis_item` tersedia; nilai kosong pada data lama sengaja memakai klasifikasi legacy sampai master diaudit manual. Jangan melakukan backfill otomatis yang dapat mengubah nilai stok historis.
5. Pastikan akun beban garansi dan akun persediaan dikonfigurasi oleh admin TEST sebelum penggantian sparepart. Tanpa pemetaan, alur garansi harus gagal aman, bukan membuat jurnal tidak seimbang.
6. Periksa halaman Kartu Stok, Stok Awal, Pembelian, Service, Garansi Service, Barang Rusak, Retur Supplier, Kredit Supplier, dan Posisi Keuangan memakai akun TEST. Catat status HTTP dan error aplikasi; jangan menguji dengan data pelanggan asli.
7. Gunakan satu rangkaian data sintetis: master sparepart fisik → pembelian/restock dengan ID master sama → service dan snapshot HPP/harga → klaim → sparepart pengganti → barang rusak terkait klaim → retur → nota kredit disetujui → jurnal dan laporan. Periksa jumlah stok hanya berkurang sekali pada setiap pengeluaran, serta jumlah debit sama dengan kredit.
8. Uji juga jasa/komisi tanpa stok, klaim berulang, kredit supplier parsial, barang tanpa HPP, dan input ganda. Catat expected/actual, commit, waktu, serta unit TEST; hentikan jika ada selisih persediaan atau jurnal.

## Gate sebelum produksi

- Tidak ada error migrasi, HTTP 404/500 pada menu baru, atau data TEST asli yang tidak sengaja berubah.
- Jumlah persediaan, nilai HPP, kredit supplier, utang, jurnal, Neraca, Laba Rugi, dan Arus Kas direkonsiliasi pada **commit dan database TEST yang sama**.
- Tim IT mereview diff, hak akses, keamanan input, backup/restore, dan memberi persetujuan eksplisit. Dokumen ini bukan otorisasi deploy produksi.

Uji lokal `tests/integration/WarrantyStockMySql.php` memakai database MariaDB sintetis yang dibuat dan dihapus sendiri untuk membuktikan stok, dua klaim, retur terkait, kredit supplier, jurnal, serta keseimbangan Neraca **pada fixture tersebut**. Itu bukan pengganti uji route/browser dan data TEST pada langkah 6–8.

Jika gagal, hentikan transaksi uji dan pulihkan **kode serta database TEST bersama-sama** dari snapshot. Simpan log error yang sudah disanitasi dan hasil expected/actual untuk perbaikan branch; jangan memperbaiki data TEST secara manual demi membuat laporan terlihat seimbang.
