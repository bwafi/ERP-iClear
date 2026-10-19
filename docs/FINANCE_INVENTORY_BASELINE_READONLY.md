# Baseline persediaan dan Neraca TEST

Jalankan hanya pada checkout dan database ERP TEST yang telah diverifikasi:

`php spark finance:inventory-baseline <akun-persediaan-10-digit> [unit-id]`

Perintah menghasilkan JSON ke stdout; tidak menulis database atau file. Akun persediaan harus dipilih dan disetujui finance berdasarkan bagan akun. Jalankan saat transaksi TEST dihentikan sementara agar query stok dan jurnal mewakili satu keadaan. Catat SHA checkout, waktu, unit, dan keluaran di luar database ERP.

`stock_value_at_current_master_cost` adalah stok saat ini dikali HPP master, bukan valuasi historis atau nilai buku yang otomatis sah. `stock_vs_journal_difference` hanya kandidat selisih. `balance_sheet.selisih_jurnal` dan `balance_sheet.akun_tidak_terklasifikasi` menunjukkan masalah jurnal terpisah. Angka per unit tidak boleh dijumlahkan tanpa memeriksa jurnal antarunit dan akun bersama. Daftar `missing_cost` dan `negative_stock` harus ditelusuri sebelum nilai stok disahkan.

Cutover: bekukan cutoff dan snapshot; cocokkan kuantitas per barang/unit dengan mutasi sumber; telusuri jurnal per referensi dan template yang hilang; tentukan nilai biaya menurut kebijakan finance; kelompokkan selisih lama berdasarkan penyebab; minta persetujuan atas setiap koreksi yang diusulkan; uji posting masa depan pada fixture terisolasi. Perintah ini tidak membuat jurnal penyeimbang, mengubah HPP, atau memigrasikan transaksi lama.
