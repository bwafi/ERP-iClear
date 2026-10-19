# Usulan COA selisih stok opname — perlu persetujuan Finance

Pada master akun ERP TEST tanggal 19 Oktober 2026, pencarian `selisih` tidak menemukan akun. Akun `1030100000 — Persediaan Barang Dagang` sudah ada dan sebelumnya disetujui sebagai akun persediaan. Di rumpun `702 — Beban Lain-lain` ada `7020200000 — Kerugian Penurunan Nilai Persediaan`; penurunan nilai bukan otomatis selisih jumlah fisik. Di rumpun `701 — Pendapatan Lain-lain` ada `7010400000 — Keuntungan Penjualan Aset Tetap` dan `7019900000 — Pendapatan Lain-lain`.

| Fungsi | Kandidat kode baru | Nama usulan | Kelompok di master | Status TEST |
|---|---|---|---|---|
| Stok fisik kurang | `7020201000` | Beban Selisih Persediaan | Beban Lain-lain (`702`) | Belum digunakan pada pencarian master 317 akun |
| Stok fisik lebih | `7010500000` | Pendapatan Selisih Persediaan | Pendapatan Lain-lain (`701`) | Belum digunakan pada pencarian master 317 akun |

Keduanya **hanya kandidat**, bukan akun yang sudah dibuat atau disetujui. Finance perlu mengonfirmasi apakah selisih kurang/lebih memang nonoperasional (`702`/`701`), atau seharusnya masuk Beban Administrasi & Umum (`602`) dan pendapatan operasional (`4`). Finance juga perlu memutuskan apakah stok sparepart memakai akun persediaan umum `1030100000` atau akun rinci `1030105000 — Sparepart Service Handphone`. Validasi sistem menerima kelompok beban `5`, `6`, atau `702` serta pendapatan `4` atau `701`; akun persediaan harus kelompok `1`. Kode wajib sudah ada di `no_akun` dan dipetakan per unit dalam `inventory_adjustment_accounts`; tanpa itu posting gagal atomik dengan pesan jelas.

Contoh jika HPP moving average barang Rp100.000/unit:

| Kejadian | Debit | Kredit |
|---|---:|---:|
| Selisih kurang 1 unit | `7020201000` Rp100.000 | `1030100000` Rp100.000 |
| Selisih lebih 1 unit | `1030100000` Rp100.000 | `7010500000` Rp100.000 |

Pembalikan jurnal memakai akun dan nominal transaksi asal dengan sisi debit/kredit terbalik. Jurnal mengikuti **nilai ledger**, bukan harga beli batch atau HPP master. Contoh ini tidak mengesahkan perlakuan pajak atau klasifikasi laba-rugi; keputusan akhir tetap Finance. Akun fixture bernama sintetis dalam tes MariaDB tidak membuat akun di TEST.
