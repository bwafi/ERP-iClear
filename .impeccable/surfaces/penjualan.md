---
version: 1
slug: "penjualan"
primary_target: "penjualan"
related_targets: ["app/Views/transaksi/penjualan.php","app/Views/transaksi/_penjualan_theme.php","app/Controllers/Penjualan.php","app/Services/Sales/PenjualanPaymentSplit.php"]
---

# Surface brief — Penjualan (/penjualan)

## Scope & mode

- Scope: `/penjualan` — view `app/Views/transaksi/penjualan.php`, tema `app/Views/transaksi/_penjualan_theme.php`, controller `Penjualan::index/insert_penjualan`, dan service `PenjualanPaymentSplit`. Route, nama field, id, modal, dan alur POST lama dipertahankan.
- Visitor mode: **Operate** — kasir memilih pelanggan, memasukkan produk/bundle/jasa, membagi pembayaran tunai dan transfer, lalu menyimpan nota.
- Audience: kasir unit dan supervisor yang melayani penjualan harian; Finance membaca hasilnya lewat kas, bank, jurnal, dan laporan.
- Job: 1) tetapkan tanggal, staff, dan pelanggan, 2) pilih produk/bundle/jasa beserta harga, qty, diskon, dan PPN, 3) bagi pembayaran tunai/transfer dan baca sisa atau kembalian, 4) simpan sekali dengan atau tanpa cetak thermal.
- Proof/content: produk dan jasa per unit (`harga_jual_unit` fallback master), bundle beserta isinya, pelanggan, staff frontliner, dan rekening unit.
- Constraints: `form_penjualan`, `bayar`, `bank[]`, `hutang`, `kembalian`, `selectedidpelanggan`, `simpan`/`simpan_thermal`, picker `.produk-checkbox` + `data-harga`, dan seluruh modal lama tetap ada; jQuery/Select2/Bootstrap/Iconify tetap dipakai; light dan dark dari token aplikasi.

## Chosen direction & memorable moment

**Satu Lembar** (one sales sheet): satu alur vertikal tanpa kolom kedua. Header tipis, lalu lembar yang mengalir dari **Pihak & Dokumen → Produk → Pembayaran → Dock**. Padat tetapi tenang: satu aksen, hairline sebagai pemisah, dan angka uang selalu tabular.

Memorable moment: kartu metode pembayaran yang langsung membuka zona nominal yang relevan, dengan baris status live di bawahnya — kasir melihat `Dibayar`, `Sisa`, dan `Kembalian` berubah saat mengetik, tanpa meninggalkan lembar.

## Direction contract

THESIS: Penjualan adalah satu lembar berurutan, bukan dashboard dan bukan dua kolom; pihak, produk, pembayaran, dan jawaban uang terakhir mengalir dalam satu bacaan vertikal.

OWN-WORLD: Dunia modul yang sudah ada (`.pb-*`, `.tj-*`, `.ks-*`): token tema (`--bs-card-bg`, `--bs-border-color`, `--bs-body-color`), radius lembar 16px, kontrol 9–10px, hairline sebagai pemisah, `tabular-nums` pada setiap angka uang, ikon Iconify `solar:*`. Satu aksen (`#0077c4` / `#8ab4ff` dark) untuk fokus dan aksi utama; status dibaca sebagai teks, bukan badge. Tanpa gradien, tanpa shadow, tanpa palet/font baru.

STORY: Operator mengisi pihak dan dokumen dalam satu baris, memilih produk ke ledger tempat identitas (nama, kode, kategori, IMEI) menjadi satu sel, memilih metode bayar, membagi tunai/transfer, memeriksa dock, lalu menyimpan.

FIRST VIEWPORT: Pada 1440×900 header tipis (breadcrumb, judul, deskripsi, chip unit) berada di atas lembar; Pihak & Dokumen muat satu baris, toolbar Produk dan beberapa baris pertama terlihat sebelum fold, dan dock hanya satu scroll pendek.

PEMBAYARAN: Tiga kartu metode (Tunai, Transfer, Tunai + Transfer) menjadi radio asli yang tersembunyi secara visual; kartu menampilkan ikon, label, dan hint. Zona tunai memakai input besar + `Uang pas`; zona transfer memakai baris `select rekening + nominal + hapus` yang bisa ditambah. Zona yang tidak aktif benar-benar `hidden` dan nominalnya dinolkan. Baris status di bawah zona memakai warna ok/warn/danger yang lembut, dengan ikon dan teks — bukan hanya warna.

UANG PAS: Pada mode `tunai_transfer`, `Uang pas` mengisi tunai sebesar `total − transfer`, bukan total penuh. Ini mencegah split payment otomatis kelebihan bayar ketika kasir sudah mengisi nominal bank.

REKENING: Daftar rekening mengikuti `ModelBank::getBankDalamScopeUnit(session('ID_UNIT'))` — unit sendiri + shared yang dialokasikan, tanpa unit lain dan tanpa Finance/HO. Server memvalidasi ulang daftar yang sama; nominal transfer tanpa rekening atau dengan rekening di luar scope ditolak.

STAFF: Dropdown Staff hanya memuat akun unit sesi. Finance (0), Admin root (1), dan Manager (34) melihat staff semua unit. Server memvalidasi `sales_by` terhadap daftar yang sama sebelum menyimpan.

HUTANG: `hutang` dan `keterangan` dihitung server dari `total − (cash + bank)`. Hidden input `hutang` tidak dibaca. Kurang bayar tetap `Belum Lunas` tanpa membentuk piutang pelanggan.

PICKER: Modal `modal-lg` dengan header ikon + judul + hint, pencarian tunggal, badge unit, dan tab Produk/Bundle/Jasa. Daftar dan kartu memakai komponen picker Sparepart yang sama persis dengan halaman terima service (`partials/sparepart_picker_style`), termasuk tinggi pencarian 44px, tick lingkaran, chip meta berikon, dan pill stok. Checkbox tetap memakai `.produk-checkbox` + `data-harga` rupiah bulat. Tidak ada kontrol "Pilih semua" — setiap baris dicentang manual dan seluruh kartu bisa diklik. Baris produk menampilkan kode, warna, jenis HP, suplier unit, dan HPP efektif; bundle dan jasa tidak. Stok ≤ 2 memakai pill peringatan. `Lihat isi` bundle menutup picker, menampilkan modal detail, lalu mengembalikan picker dengan centang utuh.

MODAL PELANGGAN: Modal berada di luar `<form>` supaya tidak terpotong `overflow: clip`/transform lembar dan tidak menjadi stacking context yang menutupi tombolnya sendiri. ID pelanggan dikirim lewat hidden input `selectedidpelanggan`. `Tambah Pelanggan` membuka modal kedua; menutupnya mengembalikan modal pelanggan.

FORM: Satu lembar (`.pj-sheet`). Urutan: `Pihak & Dokumen` (tanggal, staff, pelanggan), `Produk` (toolbar + ledger tujuh kolom), `Pembayaran` (kartu metode + zona + status), lalu dock `.pj-dock` (Diskon, PPN, Total, Dibayar, Sisa, Kembalian, Simpan, Simpan & Cetak). Grid melipat ke 2/1 kolom pada 1200/992px.

FINISH: build ini ditutup dengan screenshot desktop, dark, dan mobile; validasi inline; QA Playwright 14/14; `php -l`; test PHPUnit; dan detector Impeccable tanpa temuan.

## Unresolved decisions

- Tabel produk tetap tujuh kolom dengan `colgroup` tetap di desktop; di bawah `768px` baris berubah menjadi kartu berlabel, bukan tabel yang digeser horizontal.
- Dock bukan sticky bar mengambang; ia bagian normal dari lembar dan menjadi grid 2 kolom + tombol selebar layar di ponsel.
- `Diskon` dan `PPN` tetap input ringkasan `readonly` di dock; sumber edit per baris tetap Diskon dan checkbox PPN 11%.
- Modal detail bundle tidak ditumpuk di atas picker; picker ditutup sementara dan dibuka kembali setelah detail ditutup agar hanya satu dialog yang terlihat.
- Nominal tunai/bank yang tidak dipakai metode aktif dinolkan di klien dan diabaikan service di server. Perilaku metode kosong tetap kompatibel dengan payload lama (jumlahkan yang ada).
- Label staff memakai `Staff` (bukan `Frontliner`) agar konsisten dengan istilah halaman; nilai dan field `sales_by` tidak berubah.
