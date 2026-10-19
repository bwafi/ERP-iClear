# Deploy dan verifikasi ERP iClear di TEST

Target dokumen ini **hanya** `testerp.icleargroup.id`. Jangan merge ke `main`, menjalankan migrasi di produksi, atau menyalin `.env`/database produksi.

## Paket rilis dan jalur resmi

- Sumber rilis: PR #16, branch `codex/hpp-garansi-audit-20261018`. Tim IT harus menerima SHA commit penuh dari PR; jangan memakai nama branch saja sebagai bukti versi terpasang. Contoh SHA pada persiapan dokumen ini: `8a4839894bed1197f42a52854a125de267a67d3d`; jika PR bertambah, gunakan SHA terbaru yang disetujui.
- Paket kode adalah **checkout Git dari SHA tersebut, termasuk `composer.lock` yang dilacak Git**. Jangan mengemas `.env`, `writable/`, dump database, atau `vendor/` dari mesin pengembang. Dependency dipasang di TEST dengan `composer install` dari lock file. Hentikan deploy bila `composer validate` melaporkan lock tidak sinkron.
- Di repository ini tidak ada `.github/workflows`, GitLab CI, Jenkinsfile, atau skrip deploy TEST yang terdaftar. Karena itu **push PR bukan deploy**. Tim IT harus memakai jalur deploy TEST miliknya dan mengirim SHA aktual dari checkout yang dilayani web; URL login/HTTP 200 saja bukan bukti SHA.
- Inventaris perubahan untuk review: `git diff --stat main...<SHA>` dan `git diff --name-only main...<SHA>`. Jangan checkout, merge, atau menjalankan migrasi di `main`/produksi.

## Pemeriksaan sebelum deploy (tim IT di host TEST)

1. Verifikasi host dan document root TEST secara terpisah dari production; pastikan `hostname`, `pwd`, dan target web yang dipetakan memang `testerp.icleargroup.id`. Jangan kirim isi `.env` atau kredensial.
2. Catat `git rev-parse HEAD` (versi lama), versi PHP (`php -v`), versi framework dari `composer.lock`, dan `composer validate --no-check-publish`. Jalankan `composer check-platform-reqs` setelah dependency dari lock dipasang. Hentikan bila PHP di luar 8.2–8.4 atau ekstensi tidak cocok.
3. Pastikan file checkout tidak memiliki perubahan lokal yang akan tertimpa. Catat daftar perubahan tanpa menyalin rahasia: `git status --short` (sensor nama file sensitif sebelum dibagikan).
4. Pastikan database TEST terisolasi. Ambil backup konsisten database serta file upload TEST dan catat lokasi/ID snapshot secara internal. Verifikasi backup bisa dibaca; jangan kirim dump melalui chat.
5. Jalankan `php spark migrate:status` untuk daftar migrasi tertunda. Review migrasi PR yang menyentuh HPP, klaim, barang rusak, retur, kredit, jurnal, dan `barang.jenis_item`; pastikan tabel/kolom target tidak sudah dibuat manual dengan skema berbeda.
6. Tetapkan jendela uji agar tidak ada transaksi paralel selama backup, migrasi, dan smoke test. Jika satu gate gagal, hentikan sebelum melanjutkan.

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

## Bukti browser dan rekonsiliasi (commit dan database TEST yang sama)

1. Dari akun berizin, buka `/akun_jurnal_garansi`, `/kartu_stok`, `/garansi_service`, `/retur_suplier`, `/kredit_retur_suplier`, `/laporan_jurnal`, dan `/sisi_keuangan` (Posisi Keuangan). Catat URL, waktu, HTTP 200/redirect login/404/500, dan SHA aplikasi yang disampaikan IT. Redirect login tidak membuktikan halaman setelah login berhasil.
2. Ambil saldo awal sintetis untuk sparepart, utang supplier, piutang supplier, dan jurnal. Catat ID transaksi uji internal saja; sensor nama/telepon pelanggan dan secret pada bukti bersama.
3. Pada satu rangkaian ID yang sama, cocokkan HPP per item faktur service dengan HPP historis yang muncul ketika klaim; harga/HPP pengganti boleh berbeda. Uji juga baris jasa tanpa mutasi stok/HPP persediaan.
4. Hitung stok akhir = stok awal + penerimaan fisik - service awal - pengganti garansi - pengeluaran lain yang benar-benar terjadi. Barang Rusak terkait klaim dan pengiriman retur **tidak** mengurangi stok kedua kali. Cocokkan Kartu Stok, stok master, dan mutasi; selisih harus nol untuk fixture ini.
5. Sebelum persetujuan nota supplier, utang/kredit tidak berubah. Setelah disetujui, total utang supplier turun sebesar alokasi yang sah; sisa kredit menjadi piutang supplier, tanpa mutasi kas. Cocokkan daftar faktur terbuka, alokasi kredit, Hutang Piutang, dan jurnal; tidak boleh ada kredit dipakai dua kali.
6. Cocokkan tiap referensi garansi dan kredit: total debit = total kredit; beban garansi masuk Laba Rugi, persediaan turun sesuai HPP pengganti, utang/piutang berubah sesuai kredit, dan Arus Kas tidak berubah oleh transaksi nonkas. Neraca baru boleh disebut seimbang bila saldo awal, seluruh jurnal, dan akun silang ikut direkonsiliasi—indikator ringkasan saja tidak cukup.
7. Simpan matriks `ID sintetis | saldo awal | mutasi ekspektasi | mutasi aktual | selisih | halaman/jurnal | hasil` di lokasi internal TEST. Semua selisih wajib nol atau dijelaskan sebagai gap sebelum status siap rilis diberikan.

## Rollback TEST

Jika ada migrasi gagal, 404/500 setelah login, stok/jurnal tidak seimbang, atau rekonsiliasi gagal: hentikan transaksi uji, nonaktifkan akses tulis TEST sesuai prosedur IT, simpan log tersanitasi, lalu **pulihkan database + upload TEST dari snapshot pra-deploy dan checkout kode SHA lama sebagai satu unit**. Jangan menjalankan `php spark migrate:rollback` secara otomatis: migrasi/data baru tidak selalu reversibel. Pasang dependency sesuai `composer.lock` milik SHA lama, cek `php spark migrate:status`, buka halaman login dan satu menu read-only, lalu cocokkan checksum/saldo snapshot awal. Setelah pulih, baru buka akses TEST dan catat SHA lama serta waktu pemulihan. Jangan memakai backup production untuk rollback TEST.

## Gate sebelum produksi

- Tidak ada error migrasi, HTTP 404/500 pada menu baru, atau data TEST asli yang tidak sengaja berubah.
- Jumlah persediaan, nilai HPP, kredit supplier, utang, jurnal, Neraca, Laba Rugi, dan Arus Kas direkonsiliasi pada **commit dan database TEST yang sama**.
- Tim IT mereview diff, hak akses, keamanan input, backup/restore, dan memberi persetujuan eksplisit. Dokumen ini bukan otorisasi deploy produksi.

Uji lokal `tests/integration/WarrantyStockMySql.php` memakai database MariaDB sintetis yang dibuat dan dihapus sendiri untuk membuktikan stok, dua klaim, retur terkait, kredit supplier, jurnal, serta keseimbangan Neraca **pada fixture tersebut**. Itu bukan pengganti uji route/browser dan data TEST pada langkah 6–8.

Jika gagal, hentikan transaksi uji dan pulihkan **kode serta database TEST bersama-sama** dari snapshot. Simpan log error yang sudah disanitasi dan hasil expected/actual untuk perbaikan branch; jangan memperbaiki data TEST secara manual demi membuat laporan terlihat seimbang.
