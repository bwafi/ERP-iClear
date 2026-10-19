# Batch asal fisik dan penyesuaian stok — pekerjaan belum siap deploy

Batch (`detail_pembelian.iddetail_pembelian`) hanya menunjukkan asal fisik, supplier, dan nota. Nilai persediaan serta HPP keluar tetap berasal dari moving average per barang dan unit pada `inventory_ledger_*`. Pemilihan batch tidak mengganti `hpp_penjualan` atau HPP garansi dengan harga beli batch.

Saldo lama tanpa bukti asal dibuka sebagai batch `0` (belum diketahui). Pengeluaran tanpa pilihan batch menurunkan kepastian asal stok tersisa menjadi belum diketahui, bukan memilih supplier secara otomatis. Batch pembelian yang ditransfer tetap menunjuk nota pembelian asal meskipun berada di unit lain.

Migrasi baru dijalankan berurutan setelah ledger moving average: `2026-10-19-000400_CreateInventoryBatchLedger.php`, `2026-10-19-000500_CreateInventoryStockAdjustments.php`, `2026-10-19-000600_CreateInventoryAdjustmentAccounts.php`, lalu `2026-10-19-000700_AllowUnknownDamagedStockOrigin.php`. Migrasi kedua menambah sumber penyesuaian ke view `stok_barang` dan Kartu Stok. Migrasi ketiga menyediakan pemetaan akun per unit tanpa menebak akun lawan persediaan. Migrasi keempat mengizinkan asal pembelian barang rusak yang benar-benar tidak diketahui tetap NULL. Penyesuaian dan pembalikan menulis dua baris jurnal seimbang dari nilai ledger; operasi gagal atomik bila akun belum dipetakan. Finalisasi/reopen opname mengaitkan dan membalik penyesuaian tanpa menulis ulang HPP historis.

Uji terisolasi memakai database **kosong** bernama `erp_finance_test` dan socket MariaDB lokal tanpa jaringan:

```sh
ERP_SYNTHETIC_MARIADB_SOCKET=/path/to/isolated.sock \
ERP_SYNTHETIC_MARIADB_USER="$USER" \
php vendor/bin/phpunit tests/integration/InventoryBatchMariaDbTest.php --no-coverage
```

Test berhenti bila nama database berbeda, jaringan server aktif, atau database berisi tabel. Test membuat dan menghapus hanya fixture sintetis miliknya. Cakupan: migrasi, satu ID dari dua supplier dengan dua harga, HPP service lama tetap, rata-rata per unit, transfer batch, saldo batch dan view, penyesuaian dan jurnal seimbang, finalisasi/reopen opname berulang, pembatalan idempoten, akun belum dipetakan ditolak atomik, asal lama NULL, serta jasa ditolak.

**Belum boleh deploy:** finance belum memiliki kode akun beban selisih kurang dan pendapatan selisih lebih; `1030100000` hanya akun persediaan. Barang rusak dari klaim lama tanpa bukti asal dapat dicatat sebagai “belum diketahui” dan tidak otomatis masuk retur supplier. Pemilihan item masih dimulai dari daftar pembelian sebagai referensi nama barang, bukan bukti asal; perlu uji browser dan perbaikan UX. Uji controller/browser end-to-end dan rekonsiliasi Neraca belum lulus. Jangan memasang migrasi atau perubahan ini di TEST sebelum gerbang itu lulus.
