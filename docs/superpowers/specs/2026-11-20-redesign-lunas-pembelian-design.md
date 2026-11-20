# Redesign UI Pembayaran Lunas — Tunai + Transfer (Pembelian)

**Tanggal:** 2026-11-20
**Status:** disetujui (2026-11-20)
**Modul:** Pembelian & Persediaan (PRD Modul 03), Kas & Bank (PRD Modul 05)
**Pendekatan terpilih:** **A — Payment console**

## 1. Intent

Blok **Pembayaran** di `/pembelian` saat ini bekerja, tetapi terasa padat dan
membingungkan saat Lunas dibayar tunai + transfer:

- semua kontrol dipaksa ke grid tiga kolom; kolom bank menjadi sempit dan baris
  rekening menumpuk di ruang kecil;
- metode masih berupa dropdown, sehingga pengguna tidak langsung melihat pilihan
  Tunai / Transfer / Tunai + Transfer;
- status `Pas / Kurang / Lebih` hanya satu baris teks kecil di bawah grid;
- dock ringkasan selalu menampilkan **Hutang** dan **Kembalian**, padahal Lunas
  tidak punya hutang dan menolak kelebihan bayar;
- tidak ada satu tempat yang menjawab "total, sudah dibayar, kurang berapa".

Tujuan redesign: menjadikan pembayaran Lunas terasa seperti **konsol kasir** —
satu band lebar, metode terlihat langsung, alokasi tunai/bank jelas, dan sisa
pembayaran selalu terbaca — tanpa mengubah server, nama field, route, atau
perilaku bisnis.

## 2. Goals

1. Memindahkan blok pembayaran dari grid sempit menjadi **band selebar lembar**
   dengan hierarki: Jenis → Metode → Zona nominal → Status.
2. Mengganti dropdown metode dengan **tab Tunai | Transfer | Tunai + Transfer**
   yang langsung memperlihatkan zona relevan.
3. Membuat alokasi tunai + bank terbaca sebagai **panel split**: tunai di kiri,
   daftar rekening di kanan, status sisa di bawahnya.
4. Menampilkan **ringkasan alokasi** (Total, Dibayar, Sisa) di dekat input dan
   menyederhanakan dock Lunas menjadi Total / Dibayar / Sisa.
5. Menghilangkan Hutang dan Kembalian dari tampilan Lunas; keduanya tetap ada
   di DOM untuk kontrak form dan tetap dipakai Tempo.
6. Mempertahankan semua `id`, `name`, hook JS, modal, route, dan alur POST lama.
7. Tampilan konsisten dengan dunia `pb-*`: hairline, satu aksen, angka tabular,
   tanpa bayangan/gradien, terang dan gelap.

## 3. Non-goals

- Tidak mengubah `Pembelian::insert`, `PembelianPaymentSplit`, `pembayaran_bank`,
  `kas_keluar`, jurnal, stok, HPP, PPN, atau harga jual.
- Tidak mengubah perilaku Tempo: tetap tanpa pembayaran di muka dan tetap wajib
  jatuh tempo.
- Tidak menambah metode bayar baru (debit/QRIS/e-wallet).
- Tidak menambah dependency, font, palet, atau ikon selain Iconify `solar:*`
  yang sudah dipakai.
- Tidak mengubah schema database, route, CSRF, atau scope rekening.
- Tidak mengubah halaman Penjualan, Service, Kas & Bank, atau Tutup Kasir.
- Tidak menjadikan dock sebagai floating/sticky bar; dock tetap bagian normal
  dari lembar.

## 4. Constraints

- `#jenis_pembayaran` **tetap `<select>`** dengan opsi `lunas` dan `tempo`
  (dikunci `tests/PembelianPageTest.php`).
- `#metode_bayar` **tetap `<select>`** dengan opsi `tunai`, `transfer`,
  `tunai_transfer`, dan tetap berada setelah judul Pembayaran serta sebelum
  `name="bayar"` (dikunci page test).
- `#bayar`, `#bank-payment-container`, `#tambah-bank`, `#isi-sisa`,
  `#pb-pay-status`, `#hutang`, `#kembalian`, `#pb-dibayar`, dan
  `name="bank[${index}][id|jumlah]"` tetap ada.
- `#hutang` dan `#kembalian` tetap dikirim; menyembunyikannya hanya perubahan
  tampilan. Server tetap sumber kebenaran.
- `#metode_bayar` tidak boleh dihapus atau diganti radio: test dan fallback
  tanpa JS bergantung pada select native.
- Tidak ada `alert()` baru; validasi pembayaran yang sekarang memakai `alert()`
  dipindah ke pesan inline di area pembayaran (teks kontrak tetap ada).
- Light/dark memakai token `--pb-*`; tanpa palet baru.
- Semua selector baru berada di bawah `.pembelian-page`; tidak boleh bocor.
- Tanpa `box-shadow`, gradien, border kiri berwarna, atau animasi halaman baru.

## 5. Approaches yang dipertimbangkan

### A. Payment console (dipilih)

Band pembayaran lebar: Jenis di atas, tab metode, panel split tunai/bank,
ringkasan alokasi, lalu dock yang disederhanakan.

- Kelebihan: menjawab ketiga keluhan utama (padat, alur membingungkan, kurang
  profesional) tanpa menyentuh server; perubahan terukur dan bisa diuji lewat
  markup.
- Kekurangan: perlu JS sinkronisasi tab → select dan penataan ulang markup.

### B. Allocation ledger (ditolak)

Satu tabel alokasi: baris Tunai + tiap rekening, footer total.

- Kelebihan: paling mudah dipindai untuk banyak rekening.
- Kekurangan: terasa administratif/spreadsheet, kurang cocok untuk kasir harian,
  dan "Tunai" sebagai baris tabel terasa janggal. Ditolak.

### C. Guided split (ditolak)

Pengguna mengisi satu sisi, sisi lain otomatis sisa; multi-rekening dibagi
berurutan.

- Kelebihan: paling mengurangi salah hitung.
- Kekurangan: menambah langkah dan JS; auto-fill bisa melawan pengguna yang
  ingin mengetik bebas. Manfaat utamanya sudah didapat lewat tombol
  **Isi sisa** di pendekatan A. Ditolak.

## 6. Desain

### 6.1 Struktur band

Blok `.pb-payment` berubah dari grid tiga kolom menjadi satu band vertikal
`.pb-pay-band`:

```
Pembayaran
Pilih Lunas atau Tempo. Lunas bisa tunai, transfer, atau keduanya.

[ Jenis Pembayaran v ]        [ Jatuh Tempo ]        (Jatuh Tempo hanya Tempo)
Lunas · tunai, transfer, atau gabungan.

[ Tunai ] [ Transfer ] [ Tunai + Transfer ]        (tab, hanya Lunas)

┌──────────────────────────────┬──────────────────────────────┐
│ Tunai                        │ Transfer                     │
│ Nominal tunai                │ Rekening 1                   │
│ Rp 300.000                   │ [BCA ...]  Rp 700.000   (x)  │
│ [Uang pas] [Isi sisa]        │ + Tambah rekening            │
└──────────────────────────────┴──────────────────────────────┘
Status: Pas · Total Rp 1.200.000 · Dibayar Rp 1.200.000 · Sisa Rp 0
```

- Band memakai satu permukaan, bukan kartu bertumpuk.
- Zona tunai dan bank dipisahkan hairline vertikal saat berdampingan.
- Ringkasan alokasi berada tepat di bawah zona, selalu terlihat saat mengisi.
- Dock di kaki lembar tetap menjadi ringkasan akhir dan tombol simpan.

### 6.2 Jenis pembayaran

- `#jenis_pembayaran` tetap select native dua opsi, diberi label mikro
  `JENIS PEMBAYARAN`.
- Saat `lunas`: tampilkan tab metode, zona pembayaran, dan ringkasan alokasi.
- Saat `tempo`: sembunyikan tab dan zona; tampilkan `#jatuh-tempo-field` dan
  catatan "Tempo menjadikan seluruh total sebagai hutang supplier." Dock
  menampilkan Total + Hutang.
- Saat kosong: hanya Jenis (dan Jatuh Tempo tersembunyi); tampil hint "Pilih
  Lunas untuk membuka metode pembayaran."

### 6.3 Tab metode

Tab visual `role="tablist"` berisi tiga tombol:

| Tab | Ikon | Label | Zona |
|---|---|---|---|
| Tunai | `solar:wad-of-money-outline` | Tunai | input tunai |
| Transfer | `solar:card-outline` | Transfer | daftar rekening |
| Tunai + Transfer | `solar:split-horizontal-outline` | Tunai + Transfer | keduanya |

- Tab memakai `role="tab"`, `aria-selected`, `aria-controls`, dan mendukung
  `ArrowLeft` / `ArrowRight` / `Home` / `End`.
- `#metode_bayar` tetap select native, tetapi secara visual menjadi kontrol
  cadangan:
  - tanpa JS, select terlihat dan bisa dipakai;
  - dengan JS, `<html>` atau wrapper mendapat kelas `pb-js`, lalu select
    disembunyikan secara visual (`sr-only`, tetap fokusabel) dan tab menjadi
    kontrol utama.
- Klik tab menulis nilai ke `#metode_bayar` lalu memicu `change`, sehingga
  `aturPembayaran()` yang sudah ada tetap menjadi satu-satunya orkestrator.
- Tab `Transfer` dan `Tunai + Transfer` dinonaktifkan bila `$bank` kosong;
  `title`/`aria-describedby` menjelaskan "Belum ada rekening aktif untuk unit
  ini."
- Tab aktif memakai aksen lembut + garis bawah aksen; tab nonaktif netral.
- Tidak ada tab terpilih saat metode kosong.

### 6.4 Zona tunai

- Panel `.pb-cash-panel` dengan label mikro `TUNAI` dan hint "Uang dari laci".
- `#bayar` menjadi input nominal besar (`1.35rem`, `font-weight: 650`,
  `tabular-nums`), tetap memformat `Rp` saat mengetik.
- Aksi cepat:
  - **Uang pas** — hanya untuk metode `tunai`; mengisi `#bayar` sebesar total.
  - **Isi sisa** — hanya untuk `tunai_transfer`; mengisi `#bayar` sebesar
    `total − total bank`, minimal 0. Tetap terlihat tetapi `disabled` bila sisa 0.
- Saat metode `transfer`, panel tunai tidak ditampilkan dan `#bayar` diisi
  `Rp 0` (perilaku lama).
- Saat `tunai_transfer`, panel tunai dan bank berdampingan di desktop dan
  menumpuk di bawah 992px.

### 6.5 Zona bank

- Panel `.pb-bank-panel` dengan label mikro `TRANSFER` dan hitungan rekening
  ("1 rekening").
- Daftar `.pb-bank-list` berisi baris `.pb-bank-row` yang tetap vertikal:
  - select rekening (`select2`, `.bank-select`) dengan label mikro `REKENING`;
  - input nominal (`.bank-amount`) dengan label mikro `NOMINAL`;
  - tombol hapus ikon di kanan atas, target sentuh minimal 32px desktop dan
    44px di mobile.
- Tombol **Tambah rekening** (`#tambah-bank`) menjadi tombol outline selebar
  panel dengan ikon plus; teks berubah dari "Tambah Bank" menjadi
  "Tambah rekening" (page test disesuaikan).
- Baris baru otomatis dibuat saat metode transfer / tunai + transfer dipilih
  dan belum ada baris (perilaku lama).
- Bila `$bank` kosong: tampilkan `pb-bank-note` yang sudah ada dan nonaktifkan
  tab transfer.
- Rekening yang sudah dipilih tetap boleh muncul lagi di dropdown; server
  menolak duplikat. Catatan ini dipertahankan sebagai risiko yang sudah ada.

### 6.6 Ringkasan alokasi

Baris `.pb-alloc` di bawah zona, dengan `aria-live="polite"`:

- tiga angka inline: **Total**, **Dibayar**, **Sisa**;
- status teks: `Pas`, `Kurang Rp …`, atau `Lebih Rp …`;
- meter tipis 3px: lebar = `min(dibayar / total, 1)`, warna `--pb-ok` saat pas,
  `--pb-danger` saat kurang/lebih;
- saat lebih, teks menegaskan "Lunas tidak menerima kelebihan";
- `#pb-pay-status` tetap ada dan menjadi tempat status (kontrak page test).

Ringkasan ini adalah konteks saat mengisi. Dock tetap menjadi ringkasan akhir.

### 6.7 Dock

Dock `.pb-dock` tetap di kaki lembar, bukan sticky.

- **Lunas:** tampilkan Total (paling besar), Dibayar, Sisa; sembunyikan
  Hutang dan Kembalian (tetap ada di DOM, `hidden`, nilai `Rp 0`).
- **Tempo:** tampilkan Total dan Hutang; sembunyikan Dibayar, Sisa, dan
  Kembalian.
- **Jenis kosong:** tampilkan Total saja; figur pembayaran disembunyikan.
- `#hutang`, `#kembalian`, `#total-diskon`, `#total-ppn`, `#total-harga`, dan
  `#pb-dibayar` tetap ada dan tetap dikirim.
- Tombol **Simpan Pembelian** tetap satu, primary, dengan ikon cek; status
  disabled + `aria-busy` saat submit (perilaku lama).
- Label Sisa memakai warna danger; Total memakai skala terbesar; angka
  `tabular-nums`.

### 6.8 Copy

- Judul: "Pembayaran".
- Hint: "Lunas bisa tunai, transfer, atau keduanya. Total harus tepat sama."
- Tab: "Tunai", "Transfer", "Tunai + Transfer".
- Zona tunai: label "Tunai", hint "Uang dari laci".
- Zona bank: label "Transfer", hint "Masuk ke rekening bank".
- Tombol: "Uang pas", "Isi sisa", "Tambah rekening".
- Status: "Pas", "Kurang Rp …", "Lebih Rp … — Lunas tidak menerima kelebihan".
- Catatan bawah: "Lunas dicatat sebagai Kas Keluar. Tempo menjadi hutang
  supplier."
- Pesan validasi inline: "Pembayaran Lunas harus tepat Rp …" (teks kontrak
  tetap ada), "Pilih metode pembayaran Lunas.", "Rekening bank wajib dipilih
  untuk nominal transfer."

### 6.9 State

| State | Tampilan |
|---|---|
| Jenis kosong | Hanya Jenis + hint; zona, tab, dan figur pembayaran disembunyikan |
| Tempo | Jatuh Tempo wajib; catatan hutang; tanpa tab/zona |
| Lunas, metode kosong | Tab terlihat; empty state "Pilih metode untuk membuka kolom nominal." |
| Lunas tunai | Panel tunai; status + alokasi |
| Lunas transfer | Panel bank; status + alokasi; auto satu baris |
| Lunas tunai + transfer | Dua panel; tombol Isi sisa |
| Pas | Status hijau, meter penuh, simpan diizinkan (bila produk ada) |
| Kurang | Status danger, meter sebagian, submit diblokir |
| Lebih | Status danger, meter penuh merah, submit diblokir |
| Tanpa rekening | Tab transfer disabled + catatan |
| Submit | Tombol disabled + `aria-busy`; tidak ada double submit |

### 6.10 Responsif

- **≥1200px:** Jenis dan Jatuh Tempo sebaris; tab metode sebaris; panel tunai
  dan bank berdampingan pada `tunai_transfer`; ringkasan alokasi tiga kolom.
- **992–1199px:** panel `tunai_transfer` tetap berdampingan bila muat, jika
  tidak menumpuk; tab tetap tiga kolom.
- **≤991px:** panel `tunai_transfer` menumpuk; bank row tetap vertikal; tombol
  tambah rekening selebar panel.
- **≤767px:** tab menjadi tiga kolom kompak (ikon di atas label); ringkasan
  alokasi menjadi tiga kolom kecil; dock menjadi grid 2 kolom dengan tombol
  simpan selebar dock; target sentuh ≥44px.
- **≤575px:** label tab boleh membungkus dua baris; hint metode disembunyikan;
  input nominal tetap full width; tidak ada scroll horizontal.

### 6.11 Aksesibilitas

- Tablist punya `aria-label="Metode pembayaran"`; tab aktif `aria-selected="true"`.
- Panel zona memakai `role="tabpanel"` dan `aria-labelledby` ke tab terkait.
- Status memakai `aria-live="polite"`; error submit memindahkan fokus ke field
  pertama yang salah.
- Semua tombol ikon punya `aria-label`; tombol hapus rekening menyebut
  rekening/nominal bila memungkinkan.
- Fokus terlihat (outline 2px aksen) di tab, input, select2, dan tombol.
- `prefers-reduced-motion`: transisi tab/meter dimatikan.

## 7. Perilaku & kontrak JS

- `aturPembayaran()` tetap satu-satunya orkestrator:
  - toggle `#jatuh-tempo-field`, `required`, `.lunas-section`;
  - toggle zona tunai/bank;
  - kosongkan zona yang tidak aktif;
  - panggil `updateHutang()`.
- Fungsi baru:
  - `setMetode(value)`: set `#metode_bayar`, trigger `change`;
  - `syncTabMetode()`: sinkronkan `aria-selected`/`tabindex` dari nilai select;
  - `updateAlokasi()`: isi Total/Dibayar/Sisa, status, dan meter;
  - `updateDockMode()`: tampilkan/sembunyikan figur dock sesuai jenis.
- `sinkronPembayaranOtomatis()` dipertahankan; untuk `transfer` hanya mengisi
  otomatis bila tepat satu baris bank (perbaikan review sebelumnya).
- `updateHutang()` tetap menghitung `bayar + bankTotal`; `hutang`/`kembalian`
  tetap diisi untuk kontrak form meski disembunyikan.
- Submit guard tetap:
  1. metode wajib untuk Lunas;
  2. total komponen harus tepat sama;
  3. minimal satu produk;
  4. cegah double submit.
- Validasi pembayaran yang sekarang `alert()` diganti pesan inline di
  `#pb-pay-status` (danger) + fokus ke field terkait. Teks "Pembayaran Lunas
  harus tepat" dipertahankan untuk page test.
- Tidak ada listener ganda pada `#bayar` atau `#metode_bayar`.

## 8. Visual system (`.pb-*`)

- Selector baru: `.pb-pay-band`, `.pb-pay-head`, `.pb-method-tabs`,
  `.pb-method-tab`, `.pb-pay-zone`, `.pb-pay-split`, `.pb-cash-panel`,
  `.pb-bank-panel`, `.pb-bank-head`, `.pb-bank-list`, `.pb-alloc`,
  `.pb-alloc__figures`, `.pb-meter`, `.pb-pay-empty`.
- Token yang sudah ada: `--pb-surface`, `--pb-subtle`, `--pb-rule`,
  `--pb-rule-strong`, `--pb-ink`, `--pb-strong`, `--pb-muted`, `--pb-accent`,
  `--pb-accent-soft`, `--pb-danger`, `--pb-danger-soft`, `--pb-ok`,
  `--pb-ok-soft`, `--pb-radius-sm`.
- Satu aksen hanya untuk tab aktif, fokus, dan aksi utama. Status memakai teks
  + ikon/dot, bukan badge penuh warna.
- Tipografi: label mikro `0.65rem` uppercase `0.04em`; tab `0.8rem`/600;
  nominal tunai `1.35rem`/650; total dock `1.3rem`/650.
- Angka uang selalu `font-variant-numeric: tabular-nums`.
- Tanpa bayangan, gradien, border kiri berwarna, atau palet baru.
- Motion: transisi tab 150ms, meter 200ms, `pb-settle` yang sudah ada; semua
  dimatikan under `prefers-reduced-motion`.

## 9. Test yang direncanakan

### Page test (`tests/PembelianPageTest.php`)

- Hook lama tetap ada: `#jenis_pembayaran`, `#metode_bayar`, `#bayar`,
  `#bank-payment-container`, `#tambah-bank`, `#pb-pay-status`, `#hutang`,
  `#kembalian`, `#pb-dibayar`.
- `#metode_bayar` tetap select dengan opsi `tunai_transfer`.
- `#jenis_pembayaran` tetap hanya `lunas`/`tempo`.
- Tablist baru: `role="tablist"`, tiga `[data-metode]`, `aria-selected`.
- Panel baru: `.pb-pay-split`, `.pb-cash-panel`, `.pb-bank-panel`,
  `.pb-alloc`, `#pb-alloc-total`, `#pb-alloc-dibayar`, `#pb-alloc-sisa`,
  `#pb-meter`.
- Baris bank tetap memakai `.pb-bank-row`, `.pb-subfield`,
  `.pb-subfield__label`, `bank-rekening-${index}`, `bank-nominal-${index}`.
- Tombol tambah berlabel "Tambah rekening" dan tetap `#tambah-bank`.
- Dock punya penanda mode (`data-dock="lunas"` / `data-dock="tempo"`) dan
  `#hutang`/`#kembalian` tetap ada.
- JS memuat `setMetode`, `syncTabMetode`, `updateAlokasi`, `updateDockMode`,
  dan teks guard "Pembayaran Lunas harus tepat".
- Test lama yang menyebut "Tambah Bank" dan susunan vertikal bank disesuaikan
  dengan copy/struktur baru; assertion kontrak field tidak dihapus.

### Test lain

- `tests/unit/PembelianPaymentSplitTest.php` tidak berubah (logika server).
- `tests/PembelianHppUnitTest.php` tidak berubah (perilaku simpan).
- `tests/PembelianBankScopeTest.php`, `PembelianJatuhTempoTest.php`,
  `PembelianUnitInputTest.php` tetap lulus.
- Render halaman lalu `node --check` tiap blok JS (seperti iterasi sebelumnya).
- `php -l` pada file yang diubah dan `git diff --check`.

### Pemeriksaan manual

- 1440, 1024, 768, 390px; terang dan gelap.
- Tunai saja, transfer saja, tunai + transfer, tanpa rekening, pas/kurang/lebih.
- Keyboard: Tab ke tablist, ArrowLeft/Right, Enter/Space, fokus terlihat.
- Tidak ada scroll horizontal; target sentuh ≥44px di mobile.
- Submit kurang/lebih diblokir; pesan inline terbaca; tidak ada double submit.

## 10. Dokumentasi

- Perbarui `.impeccable/surfaces/pembelian.md` bagian Pembayaran dan
  "Unresolved decisions": tab metode, panel split, ringkasan alokasi, dock
  Lunas.
- Perbarui `docs/superpowers/specs/2026-11-20-pembelian-split-payment-design.md`
  bagian 7.4 agar menunjuk ke desain UI ini (sketsa lama tidak lagi menjadi
  acuan).
- **Tidak** mengubah `DESIGN.md`: file itu visual system Kas & Bank, bukan
  Pembelian (lihat `AGENTS.md`). Surface brief Pembelian adalah rekaman desain
  untuk halaman ini.
- PRD Modul 03/05 tidak perlu diubah karena tidak ada perubahan perilaku
  bisnis; bila ada penyesuaian copy aturan UI, cukup tambahkan catatan di
  aturan 20.

## 11. Risiko / catatan

1. **Select native + tab.** `#metode_bayar` tetap select agar test dan fallback
   tanpa JS aman. Dengan JS, select disembunyikan dan tab menjadi kontrol
   utama. Jika JS gagal, select tetap terlihat dan form tetap bisa dikirim.
2. **Duplikasi ringkasan.** Ringkasan alokasi di band dan dock sengaja
   duplikat: band untuk konteks saat mengisi, dock untuk konfirmasi akhir.
   Angka tetap dihitung dari satu fungsi (`updateHutang`/`updateAlokasi`).
3. **Hutang/Kembalian disembunyikan.** Keduanya tetap di DOM dan tetap dikirim;
   server tidak mempercayai nilainya untuk Lunas. Tidak ada perubahan bisnis.
4. **Test lama berubah.** Test yang mengunci copy "Tambah Bank" dan susunan
   bank vertikal perlu disesuaikan; assertion field/name tidak dihapus.
5. **Rekening ganda.** Dropdown masih menawarkan rekening yang sudah dipilih;
   server tetap menolak duplikat. Ini risiko lama, bukan regresi redesign.
6. **Multi-rekening produksi.** Mapping `Config\Finance::$rekeningResmiByBank`
   masih membatasi satu rekening operasional per unit; UI siap, tetapi rekening
   kedua perlu dipetakan Finance lebih dulu (sama seperti catatan fitur split).
7. **Tanpa browser automation.** Verifikasi visual dilakukan manual di browser;
   test otomatis hanya mengunci struktur markup dan JS.

## 12. Yang diserahkan ke builder

- Nama kelas CSS final dan nilai spasi/radius.
- Pilihan ikon `solar:*` yang paling tepat.
- Durasi transisi tab/meter.
- Susunan fixture dan penyesuaian page test.
- Teks mikro di dalam panel (selama tidak mengubah kontrak field/test).
