# Redesign Halaman Penjualan + Split Payment

**Tanggal:** 2026-11-20
**Status:** menunggu persetujuan
**Modul:** Penjualan & Piutang (PRD Modul 02)

## 1. Intent

Halaman `/penjualan` hari ini sudah punya opsi `Tunai / Transfer / Tunai + Transfer`
dan server sudah menyimpan tunai + tiap baris bank. Masalahnya ada di pengalaman:

- tampilan masih berupa dropdown + section tersembunyi, bukan alur bayar yang jelas;
- ada bug JS: `getElementById('bayar_bank')` tidak ada sehingga listener error dan
  validasi "pembayaran kurang" tidak pernah terpasang;
- belum ada ringkasan live yang menyatukan total, dibayar, kembalian, dan sisa;
- halaman padat, banyak kartu bersarang, dan tidak konsisten dengan halaman
  transaksi lain seperti `/pembelian`.

Tujuan: menjadikan `/penjualan` halaman kasir yang tenang, cepat dipindai, dan
jelas saat pembayaran dipecah ke tunai + beberapa rekening bank — tanpa mengubah
skema, route, atau alur jurnal yang sudah ada.

## 2. Goals

1. Redesign seluruh halaman `/penjualan` dengan satu lembar vertikal yang
   konsisten dengan `/pembelian`: kepala tipis → Pihak & Dokumen → Produk →
   Pembayaran → dock ringkasan.
2. Split payment yang benar-benar terasa: kartu metode (Tunai, Transfer,
   Tunai + Transfer), input tunai, banyak baris bank, ringkasan live.
3. Memperbaiki bug JS dan mengganti `alert()` dengan pesan inline.
4. Menjaga semua `id`, `name`, hook JS, modal, route, dan alur POST lama.
5. Tampilan modern-minimalis: hairline, satu aksen, angka tabular, tanpa
   bayangan/gradien, terang dan gelap.
6. UX kasir: minim scroll, target sentuh jelas, status pembayaran terbaca,
   validasi mencegah uang hilang diam-diam.

## 3. Non-goals

- Tidak mengubah skema database.
- Tidak mengubah invoice numbering, stok, HPP, jurnal, atau template jurnal.
- Tidak menambah metode bayar baru (debit/QRIS) — butuh skema dan aturan baru.
- Tidak menyentuh modul Service, Pembelian, Piutang, atau Tutup Kasir.
- Tidak memperbaiki bug `$nilaidiskon` pada struk (di luar scope; dicatat sebagai
  risiko).
- Tidak menambah dependency, font, atau palet baru.

## 4. Constraints

- Route aktif: `GET /penjualan` → `Penjualan::index`; `POST insert_penjualan` →
  `Penjualan::insert_penjualan` (`auth`, tanpa CSRF — mengikuti pola lama).
- Form wajib tetap `id="form_penjualan"`, `method="post"`,
  `action="<?= base_url('insert_penjualan') ?>"`, `enctype="multipart/form-data"`.
- Field yang harus tetap ada dan tetap terkirim:
  `tanggal_masuk`, `sales_by`, `metode_bayar`, `bayar`,
  `bank[i][id]`, `bank[i][jumlah]`,
  `produk[id][id|kode|nama|kategori|kondisi_barang|harga|jumlah|diskon|ppn]`,
  `total-diskon`, `total-ppn`, `total-harga`, `hutang`, `kembalian`,
  `selectedidpelanggan`, `pelanggan`, dan `action` (`simpan` /
  `simpan_thermal`).
- Hook JS yang harus dipertahankan: `.produk-checkbox` + `data-id/kode/nama/harga/
  kategori/input/kondisi_barang`, `#selected-produk-table`,
  `#confirm-produk-btn`, `#select-all-produk`, `#pilih-produk-modal`,
  `#produk-modal-table`, `#jasa-modal-table`, `#bundle-modal-table`,
  `#tambah-bank`, `#bank-payment-container`, `.bank-row`, `.bank-select`,
  `.bank-amount`, `.hapus-bank`, `.btn-check-bundle`, `#detailBundleModal`,
  `#bundle-detail-body`, `#pelangganModal`, `#pelanggan-select`,
  `#btnPilihPelanggan`, `#btnTambahPelanggan`, `#modalTambahPelanggan`,
  `#formTambahPelanggan`.
- `data-harga` produk/jasa harus tetap rupiah bulat (`(int) round((float) …)`),
  dan fungsi `formatToRupiah()` tetap membuang non-digit. Test
  `tests/PenjualanPickerMoneyTest.php` adalah kontrak.
- Scope unit mengikuti sesi (`session('ID_UNIT')`), tidak ada pemilih unit baru.
- Daftar rekening memakai `ModelBank::getBankDalamScopeUnit($unitId)` (rekening
  unit + shared yang dialokasikan, tanpa Finance/HO), dan `bank[][id]`
  divalidasi ulang di server agar kasir tidak bisa mengarahkan pembayaran ke
  rekening unit lain.
- `metode_bayar` menjadi sumber kebenaran zona pembayaran: `tunai` mengabaikan
  baris bank, `transfer` mengabaikan tunai, `tunai_transfer` memakai keduanya.
  Nilai `metode_bayar` kosong memakai perilaku lama (jumlahkan yang ada).
- Light/dark memakai token tema aplikasi; tanpa palet baru.

## 5. Approaches considered

1. **Satu Lembar Penjualan (dipilih).** Satu alur vertikal seperti `/pembelian`:
   kepala tipis, lembar dengan tiga bagian berhairline, dock total + simpan di
   kaki lembar. Konsisten, risiko rendah, semua kontrol lama tetap di tempatnya.
2. **Kasir dua kolom.** Daftar produk di kiri, panel pembayaran menempel di
   kanan. Split selalu terlihat, tetapi menyimpang dari pola `/pembelian` dan
   menambah kerumitan responsif. Ditolak karena user memilih satu lembar.
3. **Polish struktur lama.** Hanya merapikan CSS dan memperbaiki split. Cepat,
   tetapi tidak menjawab permintaan redesign dan UX tetap padat.

## 6. Desain

### 6.1 Kepala halaman

Kartu putih ber-hairline (`.pj-head`) di atas kanvas:

- breadcrumb `Transaksi / Penjualan`;
- `h1` "Penjualan" `1.375rem`/600, tracking `-.03em`;
- satu baris deskripsi: "Pilih pelanggan, masukkan produk, lalu bagi pembayaran
  tunai dan transfer.";
- chip unit aktif dari `session('NAMA_UNIT')` (read-only, ikon toko). Tidak ada
  pemilih unit karena penjualan terikat unit sesi; unit ini juga menjadi scope
  daftar rekening split payment.

### 6.2 Lembar (`.pj-sheet`)

Satu `<form>` berisi tiga bagian berhairline, lalu dock. Tanpa kartu bersarang.

#### Bagian 1 — Pihak & Dokumen

Grid tiga kolom (`Tanggal`, `Staff`, `Pelanggan`), melipat 2 → 1 kolom.

- Tanggal: input `type="date"` `name="tanggal_masuk"`, default hari ini.
  Semua unit tetap bebas memilih tanggal (termasuk backdate), sesuai perilaku
  sekarang.
- Staff: select `name="sales_by"` dari `$frontliner`, wajib.
- Pelanggan: input readonly `#pelanggan` `name="pelanggan"` (kontrak lama) +
  tombol "Pilih Pelanggan" yang membuka `#pelangganModal`.
  Di bawahnya baris meta alamat pelanggan (provinsi · kabupaten · kecamatan)
  yang diisi dari `data-provinsi/kabupaten/kecamatan` opsi terpilih.
- Select provinsi/kabupaten/kecamatan yang lama di form utama **dihapus** dari
  alur utama karena nilainya tidak pernah dipakai server; kontrol yang sama
  tetap ada di modal "Tambah Pelanggan Baru" dan tetap memakai cascade region.
- `selectedidpelanggan` tetap dikirim lewat `#pelanggan-select` di modal.

#### Bagian 2 — Produk

Kepala bagian: judul "Produk", hint singkat, dan toolbar:

- tombol primary "Pilih Produk" (membuka `#pilih-produk-modal`);
- hitungan item `#pj-item-count` ("3 item");
- tombol ghost "Kosongkan" untuk mengosongkan keranjang (dengan konfirmasi
  inline, bukan `confirm()`).

Tabel ledger `#selected-produk-table`:

| Kolom | Isi |
|---|---|
| Produk | nama tebal + baris meta kode · kategori · IMEI/warna; hidden input kontrak lama |
| Harga | input teks `produk[id][harga]`, format rupiah saat mengetik |
| Qty | input number `produk[id][jumlah]`, min 1 |
| Diskon | input teks `produk[id][diskon]` |
| PPN | checkbox `produk[id][ppn]` + label "11%" |
| Subtotal | teks terhitung, `tabular-nums` |
| Aksi | tombol hapus ikon (aria-label) |

- Baris kosong memakai empty state (ikon + "Belum ada produk" + tombol pilih),
  bukan `<tbody>` kosong.
- Kolom uang rata kanan dan `font-variant-numeric: tabular-nums`.
- Di bawah `768px` baris menjadi kartu bertumpuk dengan label `data-label`;
  tabel tidak memaksa scroll horizontal.
- Subtotal baris = `(harga × qty) − diskon`; jika PPN dicentang tambah 11%.
  Nilai ini hanya tampilan; `total-harga` tetap sumber yang dikirim.

#### Bagian 3 — Pembayaran

Kartu metode (radio asli, label visual) `role="radiogroup"`:

1. **Tunai** — "Uang tunai dari laci".
2. **Transfer** — "Masuk ke rekening bank".
3. **Tunai + Transfer** — "Gabungan dua cara bayar".

Perilaku:

- Tunai: tampilkan `#bayar` (`name="bayar"`), input besar `inputmode="numeric"`,
  dengan chip "Uang pas" yang mengisi sebesar total. Chip nominal cepat
  (`50.000`, `100.000`) opsional dan hanya menambah, bukan mengganti.
- Transfer: tampilkan `#bank-payment-container` + tombol "+ Tambah Bank".
  Baris bank: select rekening (`bank-select`), nominal (`bank-amount`), hapus.
- Tunai + Transfer: kedua zona tampil.
- Metode kosong: zona disembunyikan, tampil empty state "Pilih metode untuk
  membuka kolom nominal."
- Saat metode berubah, nilai zona yang tidak aktif dikosongkan (tunai → 0,
  bank → baris dihapus) supaya nominal lama tidak ikut terkirim; server tetap
  menormalkan berdasarkan `metode_bayar`.
- Baris bank baru otomatis dibuat saat metode transfer / tunai + transfer
  dipilih dan belum ada baris.
- Baris bank dengan nominal `0` diabaikan server; nominal `> 0` wajib punya
  rekening yang masuk scope unit.
- Ringkasan pembayaran live di bagian ini:
  - `Dibayar` (tunai + semua bank);
  - `Kembalian` bila lebih;
  - `Sisa` bila kurang, dengan kalimat "Akan tercatat belum lunas — catat
    piutang secara manual" (perilaku lama dipertahankan; POS tidak membuat
    catatan piutang otomatis).
- `aria-live="polite"` pada status pembayaran.

#### Dock (`.pj-dock`)

Bagian normal di kaki lembar (bukan floating bar), seperti `/pembelian`:

- angka: Diskon, PPN, **Total** (paling besar), Dibayar, Kembalian, Sisa/Hutang;
- input readonly yang tetap terkirim: `#total-diskon`, `#total-ppn`,
  `#total-harga`, `#hutang`, `#kembalian`;
- status pembayaran satu baris (Lunas / Kembalian / Kurang);
- aksi: "Simpan" primary (`action=simpan`) dan "Simpan & Cetak"
  (`action=simpan_thermal`), ikon berbeda;
- hint kecil di bawah aksi: "Kedua tombol menyimpan dan mencetak struk thermal"
  — perilaku server tidak diubah pada iterasi ini;
- tombol simpan nonaktif selama keranjang kosong; alasan tampil sebagai hint.

### 6.3 Modal Pilih Produk

Modal `modal-lg` dengan tema `.pj-modal`:

- header ikon + judul + hint;
- tabs Produk / Bundle / Jasa (Bootstrap tabs, kontrak lama);
- satu kolom pencarian yang memfilter baris di sisi klien (nama, kode, IMEI,
  kategori);
- daftar kartu/baris yang bisa di-scroll:
  - Produk: checkbox bulat, nama, kode, meta kategori · IMEI · warna · jenis,
    stok (chip; stok ≤ 0 / null disabled), harga jual;
  - Jasa: checkbox, nama, kode, harga;
  - Bundle: checkbox, nama, harga, status ketersediaan, tombol "Lihat isi"
    (membuka `#detailBundleModal`).
- `data-harga` tetap rupiah bulat; `.produk-checkbox` dan atribut lama tetap.
- footer: jumlah dipilih + "Tambahkan" (nonaktif bila kosong) + "Batal".
- DataTables untuk tiga tabel picker dihapus; pencarian dan sortir ringan
  dilakukan manual. Tidak ada query baru.

### 6.4 Modal Pelanggan

- `#pelangganModal`: select2 pencarian (`#pelanggan-select`), tombol
  "Tambah Pelanggan Baru", "Pilih", dan pesan inline bila belum memilih.
- `#modalTambahPelanggan`: form lama dipertahankan (nama, no HP, provinsi,
  kabupaten, kecamatan) + cascade region; setelah sukses, opsi baru ditambahkan
  dan modal pelanggan dibuka kembali.
- `alert()` diganti pesan inline di dalam modal.

## 7. Visual system (`.pj-*`)

- Scope: `app/Views/transaksi/_penjualan_theme.php`, semua selector di bawah
  `.penjualan-page` dan `.pj-modal`; token `--pj-*` diturunkan dari tema
  aplikasi (surface, rule, ink, strong, muted, accent, danger, ok, radius).
- Aksen: `#0077c4` terang / `#8ab4ff` gelap (sama dengan `/pembelian`); satu
  aksen untuk fokus dan aksi utama.
- Semantik status: sukses untuk lunas/kembalian, danger untuk sisa, warning
  untuk "belum lunas"; selalu teks + ikon, bukan warna saja.
- Tipografi: label mikro `0.625rem` uppercase `0.09em`; judul bagian
  `0.95rem`/600; angka uang `tabular-nums`; nominal `Rp 1.234.567`.
- Kontrol: tinggi 40px, radius 10px, focus ring 2px aksen.
- Tanpa bayangan, gradien, border kiri berwarna, atau palet baru.
- Motion: `pj-settle` (fade + 4px) saat lembar muncul, tint baris saat produk
  ditambahkan, transisi state kontrol. Semua dimatikan under
  `prefers-reduced-motion`.

## 8. Responsif

- `≤1199px`: grid pihak 2 kolom; dock membungkus 3×2.
- `≤991px`: grid pihak 1 kolom; kartu metode tetap 3 kolom; baris bank
  select penuh + nominal/hapus di baris kedua.
- `≤767px`: tabel produk menjadi kartu berlabel `data-label`; dock menjadi grid
  2 kolom dengan tombol simpan selebar dock; kepala halaman menumpuk.
- `≤575px`: kartu metode 3 kolom kompak (ikon + label), hint disembunyikan;
  tombol "Simpan & Cetak" selebar dock.

## 9. Perilaku & validasi

### Klien

- Satu listener submit; tidak ada listener ganda.
- Validasi sebelum submit:
  1. pelanggan sudah dipilih;
  2. minimal satu produk;
  3. staff sudah dipilih;
  4. nominal tunai/bank valid (angka ≥ 0);
  5. baris bank dengan nominal > 0 sudah punya rekening.
- Jika gagal: fokus ke field pertama yang salah + pesan inline di dekatnya,
  tanpa `alert()`.
- Jika kurang bayar: tetap boleh submit, tetapi dock menampilkan "Kurang Rp X —
  transaksi akan tercatat belum lunas". Tidak ada catatan piutang otomatis;
  penagihan tetap lewat modul Piutang.
- Jika lebih bayar: tampilkan "Kembalian Rp X".
- `hutang` dihitung ulang di klien untuk ditampilkan; server tidak lagi
  mempercayainya (lihat 9.2).
- Semua angka diformat saat mengetik dan di-unformat sebelum dikirim.

### Server (`Penjualan::insert_penjualan`)

- Validasi keranjang tidak kosong → flash gagal + redirect, bukan fatal error.
- Validasi `total-harga` > 0.
- Normalisasi `bayar` dan `bank[*][jumlah]`; tolak nominal negatif.
- Normalisasi mengikuti `metode_bayar`: `tunai` hanya memakai tunai,
  `transfer` hanya memakai bank, `tunai_transfer` memakai keduanya; nilai kosong
  memakai perilaku lama (jumlahkan yang ada).
- Tolak baris bank dengan nominal > 0 tetapi `id` rekening kosong atau di luar
  scope unit (`ModelBank::getBankDalamScopeUnit($unitId)`).
- `keterangan` Lunas/Belum Lunas dihitung server dari
  `total_bayar >= total_penjualan`, bukan dari hidden `hutang`. Kurang bayar
  tetap diizinkan dan tidak membuat piutang otomatis.
- Jurnal, stok, invoice, dan alur transaksi tidak berubah.
- Logika normalisasi/validasi diekstrak ke service kecil
  `app/Services/Sales/PenjualanPaymentSplit.php` agar bisa diuji unit; service
  menerima daftar rekening yang diizinkan dari controller.

## 10. Testing

- `tests/PenjualanPickerMoneyTest.php` harus tetap lulus (kontrak `data-harga`
  dan `formatToRupiah`).
- Test view baru `tests/PenjualanPageTest.php`:
  - wrapper `penjualan-page`, `pj-sheet`, tiga bagian, dock;
  - kartu metode + `#bayar` + `#bank-payment-container` + `#tambah-bank`;
  - field dan `name` kontrak lama;
  - tidak ada referensi `bayar_bank`;
  - empty state, tombol simpan ganda, modal pelanggan/picker.
- Test unit baru `tests/unit/PenjualanPaymentSplitTest.php`:
  - tunai + beberapa bank dijumlahkan;
  - baris bank nominal 0 diabaikan;
  - nominal > 0 tanpa rekening ditolak;
  - rekening di luar scope unit ditolak;
  - metode `tunai` mengabaikan bank dan `transfer` mengabaikan tunai;
  - nominal negatif ditolak;
  - kembalian dan sisa dihitung benar.
- Jalankan `vendor/bin/phpunit -c phpunit.xml tests/PenjualanPickerMoneyTest.php
  tests/PenjualanPageTest.php tests/unit/PenjualanPaymentSplitTest.php`.
- `php -l` pada file PHP yang diubah dan `git diff --check`.

## 11. Dokumentasi

- Perbarui `DESIGN.md` dengan bagian `Penjualan (.pj-*)`.
- Perbarui `docs/prd/modul/PRD_MODUL_02_Penjualan_Piutang.md`:
  split payment UI, validasi server, dan status belum lunas.
- Buat surface brief `.impeccable/surfaces/penjualan.md` saat implementasi.

## 12. Risiko / catatan

1. **Kurang bayar.** Keputusan: perilaku server sekarang dipertahankan —
   penjualan kurang bayar tetap bisa disimpan sebagai "Belum Lunas", dengan
   peringatan jelas, dan tanpa catatan piutang otomatis. Jika bisnis ingin POS
   wajib lunas, aturan itu perlu ditambahkan terpisah (klien + server + test).
2. **Alamat pelanggan.** Keputusan: tiga select domisili di form utama dihapus
   karena nilainya tidak dipakai server; alamat tampil sebagai teks dari data
   pelanggan, dan tetap bisa diisi di modal tambah pelanggan.
3. **DataTables picker.** Penggantian ke daftar kartu menghilangkan sortir
   kolom; pencarian tetap ada. Untuk jumlah produk besar, daftar di-scroll di
   dalam modal (pola yang sudah dipakai `/pembelian`).
4. **Tombol simpan.** Keputusan: perilaku server tidak diubah — "Simpan" dan
   "Simpan & Cetak" sama-sama menyimpan lalu mencetak struk thermal. UI memberi
   hint agar label tidak menyesatkan; pemisahan alur simpan/cetak adalah
   perubahan terpisah.
5. **Unit multi-cabang.** Halaman memakai `session('ID_UNIT')` untuk produk dan
   scope rekening, sedangkan `insert_penjualan` memakai `akun.ID_UNIT` mentah.
   Untuk akun dengan `ID_UNIT` berupa array JSON, keduanya bisa berbeda; ini
   bug lama di luar scope redesign dan dicatat agar tidak menyesatkan.
6. **Struk.** `$nilaidiskon` pada `insert_penjualan` masih mengambil diskon baris
   terakhir untuk `sub_total_cetak`/`diskon` struk. Di luar scope; tidak diubah
   agar fokus redesign tidak melebar ke perhitungan struk.
