# Cutover penilaian persediaan TEST

Dokumen ini hanya untuk `testerp.icleargroup.id`. Jangan jalankan pada `main` atau produksi.

## Kebijakan biaya

- Biaya perolehan unit adalah harga beli setelah diskon, dibagi jumlah dengan pembulatan setengah naik ke rupiah. PPN dan biaya tambahan tidak masuk HPP; PPN sudah dijurnal terpisah. Keputusan ini dikonfirmasi untuk TEST pada 19 Oktober 2026.
- Penerimaan menambah kuantitas dan nilai biaya pada cabang penerima. Pengeluaran memakai rata-rata nilai/kuantitas tepat sebelum pengeluaran. Jika seluruh stok keluar, sisa pembulatan nilai dilepas bersama stok terakhir.
- Snapshot `hpp_penjualan` service/penjualan dan `hpp_satuan` garansi yang sudah tercatat tidak diubah ketika pembelian berikutnya masuk. Pricelist/harga jual tidak menjadi sumber HPP.

## Batas data lama dan pemeriksaan pra-cutover

1. Bekukan satu SHA TEST dan ambil backup TEST. Ekstrak daftar baca-saja per `(barang_id, unit_id)`: kuantitas `stok_barang`, mutasi Kartu Stok, HPP snapshot transaksi, nilai dari mutasi, dan saldo akun persediaan. Jangan membuat baris stok awal hanya agar kartu muncul.
2. Kelompokkan item dengan transaksi bertanggal mundur, stok sementara negatif, HPP sumber kosong, mutasi yang tidak cocok dengan `stok_barang`, atau klasifikasi master belum pasti. Mesin biaya tidak boleh diam-diam mengganti HPP atau jurnal historis kelompok ini; jalur lama dipertahankan sementara dan selisih ditampilkan untuk review.
3. Untuk item fisik yang riwayatnya valid, uji pembelian pertama, service, restock dengan ID sama dan biaya berbeda, service kedua, pengganti garansi, barang rusak terkait klaim, retur supplier, serta transfer antar-cabang. Cocokkan kuantitas dan nilai per cabang setelah tiap langkah.
4. Rekonsiliasi nilai persediaan terhadap jurnal menurut tanggal cutoff dan unit. Pisahkan selisih pembukuan lama, biaya perolehan, HPP keluar, klasifikasi item, dan akun tanpa kode. Jangan memposting jurnal penyeimbang umum atau menghitung ulang transaksi lama.
5. Tinjau daftar `produk/review-tipe` untuk item lama yang `jenis_item` kosong. Terapkan klasifikasi hanya setelah bukti fisik/riwayat disetujui; jangan gabungkan ID otomatis.

## Gate deploy

- `git rev-parse HEAD` di TEST harus sama dengan SHA yang disepakati dan tidak berubah sepanjang uji browser. Checkout TEST saat audit adalah shared checkout; hentikan jika pihak lain mengganti branch/SHA.
- Untuk fixture sintetis, `stok akhir = saldo Kartu Stok`, nilai akhir = nilai mutasi, HPP service pertama tetap setelah restock, jurnal tiap referensi seimbang, dan transaksi nonkas tidak mengubah Arus Kas.
- Neraca historis yang sudah berselisih dilaporkan terpisah dari dampak fixture. Tidak ada klaim lulus sampai seluruh selisih baru nol dan laporan browser pada SHA yang sama diverifikasi.

Proyeksi biaya saat ini menggunakan sumber transaksi yang sudah ada; ini belum menjadi ledger posting immutable untuk pembatalan/koreksi. Jangan menyebutnya cutover final sebelum pembalik transaksi, validasi konkurensi, dan akuntansi nilai lulus pengujian integrasi.
