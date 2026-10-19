# Batch asal fisik dan penyesuaian stok — pekerjaan belum siap deploy

Batch (`detail_pembelian.iddetail_pembelian`) hanya menunjukkan asal fisik, supplier, dan nota. Nilai persediaan serta HPP keluar tetap berasal dari moving average per barang dan unit pada `inventory_ledger_*`. Pemilihan batch tidak mengganti `hpp_penjualan` atau HPP garansi dengan harga beli batch.

Saldo lama tanpa bukti asal dibuka sebagai batch `0` (belum diketahui). Pengeluaran tanpa pilihan batch menurunkan kepastian asal stok tersisa menjadi belum diketahui, bukan memilih supplier secara otomatis. Batch pembelian yang ditransfer tetap menunjuk nota pembelian asal meskipun berada di unit lain.

Migrasi baru dijalankan berurutan setelah ledger moving average: `2026-10-19-000400_CreateInventoryBatchLedger.php`, lalu `2026-10-19-000500_CreateInventoryStockAdjustments.php`. Migrasi kedua menambah sumber penyesuaian ke view `stok_barang` dan Kartu Stok. Keduanya menolak rollback setelah dipakai.

Uji terisolasi memakai database **kosong** bernama `erp_finance_test` dan socket MariaDB lokal tanpa jaringan:

```sh
ERP_SYNTHETIC_MARIADB_SOCKET=/path/to/isolated.sock \
ERP_SYNTHETIC_MARIADB_USER="$USER" \
php vendor/bin/phpunit tests/integration/InventoryBatchMariaDbTest.php --no-coverage
```

Test berhenti bila nama database berbeda, jaringan server aktif, atau database berisi tabel. Test membuat dan menghapus hanya fixture sintetis miliknya. Cakupan: migrasi, satu ID dari dua supplier dengan dua harga, HPP service lama tetap, rata-rata per unit, transfer batch, saldo batch dan view, penyesuaian, pembatalan idempoten, serta jasa ditolak.

**Belum boleh deploy:** penyesuaian belum memiliki pemetaan jurnal kerugian/kelebihan persediaan dan belum dihubungkan ke formulir stok opname. Retur supplier yang berasal dari klaim dengan asal lama tidak pasti masih memerlukan penanda bukti asal eksplisit. Uji controller/browser end-to-end dan rekonsiliasi jurnal terhadap Neraca juga belum lulus. Jangan memasang migrasi atau perubahan ini di TEST selama pengujian pengguna sedang berlangsung.
