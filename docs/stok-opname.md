# Stok Opname — Changelog & Tutorial

Modul **Stok Opname** di-refactor besar-besaran dari "input ke tabel langsung" menjadi
**alur kerja PERIODE (DRAFT → FINAL)** supaya dapat dicicil, terkunci dengan benar,
dan informatif. Berikut catatan perubahannya dan panduan penggunaannya.

---

## 1. Changelog

### v1.5 — Pengawas hanya melihat & finalisasi boleh parsial (dengan peringatan) (2026-09-07)

- **Role pengawas (Admin Center 0, Admin Root 1, Direktur 2, Manager 34, SPV 40)
  HANYA melihat**: tombol *Mulai/Simpan Draft/Finalisasi/Reopen* disembunyikan,
  input Jumlah Real readonly, dan endpoint-nya juga di-guard di server.
  Pengawasan/monitoring tetap lengkap (progress, riwayat, filter selisih, pencarian).
- **Finalisasi DIPERBOLEHKAN meski belum semua terisi** — muncul peringatan yang
  jelas saat konfirmasi (jumlah barang yang masih kosong), pengguna boleh melanjutkan.
  Barang yang tidak terisi menjadi `NULL` di data final. Flash juga menyertakan catatan
  jumlah yang belum terisi.

### v1.4 — Filter Selisih untuk role pengawas (2026-09-07)

- Dropdown **Selisih** di toolbar list-cerdas, khusus role **Admin Center (0), Admin
  Root (1), Direktur (2), Manager (34), dan SPV (40)**; operator lain tidak melihatnya.
- Opsinya: Semua / Ada Selisih (≠ 0) / Lebih (+) / Kurang (−) / Presisi (= 0).
- Bisa dipakai untuk review cepat barang yang selisihnya tidak wajar sebelum finalisasi.
- (Catatan investigasi) Total **Stok Komputer** yang tampil adalah jumlah `stok_akhir`
  semua barang unit tsb (mis. 25.733 utk 372 produk unit 1 — ada produk stok ribuan);
  bukan salah hitung/double count.

### v1.3 — Total Selisih kini terhitung di periode DRAFT (2026-09-07)

- **Sebelumnya**: kolom *Total Selisih* (dan *Stok Real*) di tabel `stok_opname_periode`
  hanya dihitung saat periode FINAL. Pada periode DRAFT nilainya NULL sehingga monitor
  atas & Riwayat Periode menampilkan "-".
- **Sekarang**: ringkasan periode (termasuk `jumlah_real` & `jumlah_selisih`) dihitung
  ulang setiap kali draft di-simpan — monitor & riwayat menampilkan total selisih
  berjalan (bisa berubah selama masih DRAFT, terkunci saat FINAL).
- Backfill data existing: semua periode DRAFT lama juga dihitung ulang
  (migrasi `2026-09-07-000002`).

### v1.2 — Perbaikan: Simpan Draft tidak lagi "kehilangan" data di halaman lain (2026-09-07)

- **Bug**: karena tabel menggunakan pagination, form hanya memuat baris yang sedang
  terlihat di halaman aktif DOM. Nilai yang diinput di **halaman lain** hanya ada di
  memori browser, sehingga saat klik *Simpan Draft* sebagian nilai tidak ikut terkirim
  (flash tetap "berhasil disimpan").
- **Perbaikan**: sebelum submit, *semua* barang di-sinkronkan ke form sebagai hidden
  input (`data-so-sync`), jadi seluruh nilai dari semua halaman ikut tersimpan.
- Pesan sukses kini menampilkan jumlahnya: mis. *"Draft stok opname berhasil disimpan
  (127 barang ter-update)."* — memudahkan memastikan data benar-benar masuk.

### v1.1 — Filter "Belum Disimpan" & Urutan "Terbaru Diinput" (2026-09-07)

- **Filter baru `Belum Disimpan`**: menampilkan barang yang sudah diinput di browser
  tetapi **belum disimpan ke server** (belum klik *Simpan Draft*). Ada counter jumlahnya.
  - Filternya berbasis perbandingan nilai input vs nilai terakhir tersimpan — jadi kalau
    sebuah baris diubah lalu dikembalikan lagi ke nilai semula, otomatis keluar dari daftar ini.
  - Setelah klik *Simpan Draft* (halaman reload), semua barang bersih dari daftar ini.
- **Urutan "Terbaru diinput"** (default): daftar di-sort sehingga barang yang paling
  baru diinput tampil paling atas; barang yang belum disentuh tetap urut kode di bawah.
  Bisa diganti ke urut **Kode A–Z** lewat dropdown *Urut*.
- Pencarian tetap menang atas semua filter (barang cocok selalu muncul apa pun statusnya).

### v1.0 — Refactor Stok Opname menjadi Periode DRAFT/FINAL (2026-09-07)

**Fitur baru**

- **Alur periode DRAFT → FINAL** (mirip Kontrol Aset):
  - *Mulai Opname* → membuat periode DRAFT dan menyiapkan daftar barang otomatis dari stok kartu (`stok_barang`).
  - *Simpan Draft* → menyimpan jumlah real secara bertahap/berulang (bisa dicicil, aman).
  - *Finalisasi* → hanya bisa jika **semua barang sudah terisi**; data disalin ke tabel final `stok_opname`, periode terkunci FINAL, dan terhitung di riwayat & KPI.
  - *Reopen* → membuka kembali periode FINAL untuk koreksi (hasil final unit/tanggal tsb dihapus lalu dihitung ulang setelah finalisasi baru).
- **Indikator & informasi lengkap**:
  - Badge status `BELUM DIMULAI / DRAFT / FINAL`.
  - Progress bar + "x dari y barang terisi (n%)" + peringatan sisa barang kosong.
  - Statistik Total Barang, Stok Komputer, Total Selisih.
  - Info siapa & kapan periode difinalisasi.
  - Riwayat periode per unit (badge status tiap tanggal).
  - Indikator per baris (Terisi/Belum) + baris kuning bila belum diisi.
- **List Cerdas + Pagination**:
  - Filter segmented `Belum Terisi` (default) / `Semua` / `Sudah Terisi` + counter.
  - Pencarian realtime kode/nama — **saat mencari, filter diabaikan** sehingga barang terisi maupun belum yang cocok tetap tampil.
  - Tampil per halaman 50/100/200/500 + pagination.
  - Enter pada input = pindah ke baris berikutnya (tidak submit), baris yang baru terisi hilang dari tab "Belum Terisi" & fokus berpindah otomatis.
  - Progress bar & counter diperbarui realtime saat mengetik.
  - Sticky header tabel.
- **Pembatasan role**: pemilih **Unit & Tanggal** hanya untuk Admin Root / Manager (ID_JABATAN `0,1,2,34`). Operator/inputer otomatis memakai unit miliknya (`ID_UNIT`) dan tanggal hari ini — tidak bisa pindah unit/tanggal lain.
- **KPI Stok Opname** kini hanya menghitung **periode FINAL** (sebelumnya semua baris draft ikut terhitung).

- **Filter baru `Belum Disimpan`**: barang yang sudah diinput di browser tapi belum disimpan server (belum klik *Simpan Draft*), lengkap dengan counter.
- **Urut "Terbaru diinput"** (default) atau "Kode A–Z" via dropdown *Urut*.
- **Pencarian tetap menang** atas semua filter: barang cocok (terisi/belum, tersimpan/belum) selalu muncul.
- **Simpan DRAFT & FINAL tetap manual** — Enter hanya memindah fokus, tidak ada request ke server; data hanya terkirim saat klik *Simpan Draft* / *Finalisasi*.
- **Semua halaman ikut tersimpan** — saat *Simpan Draft*, seluruh barang (termasuk yang diketik di halaman lain) dikirim; tidak ada yang hilang.

**Perbaikan (dari masukan user)**

- Tidak lagi banyak alert "nilai tidak valid" saat submit: input kosong dilewati, nilai tidak valid digabung menjadi 1 pesan ringkas.
- Track progress bar tidak polos putih (gradient + striped + warna hangat).

**Teknis**

- Tabel baru: `stok_opname_periode`
  (unit, tanggal, status `DRAFT|FINAL`, ringkasan `jumlah_komp/jumlah_real/jumlah_selisih`, `total_barang`, `terisi_barang`, `mulai_by`, `finalisasi_by`, `tanggal_finalisasi`, timestamp; UNIQUE `(unit_idunit, tanggal)`).
- Kolom `periode_id` ditambahkan di `stok_opname_draft` & `stok_opname` + index.
- **Backfill** data lama: seluruh data draft & final yang sudah ada dikelompokkan per `(unit, tanggal)` menjadi periode (data tidak hilang).
- File baru/berubah:
  - `app/Database/Migrations/2026-09-07-000001_RefactorStokOpnamePeriode.php`
  - `app/Models/ModelStokOpnamePeriode.php`
  - `app/Services/StokOpnameService.php`
  - `app/Controllers/StokOpname.php`
  - `app/Views/stok/stok_opname.php`
  - `app/Services/Kpi/StokOpnameCalculator.php`
  - `app/Config/Routes.php`
- Route baru: `stok_opname/mulai`, `stok_opname/simpan`, `stok_opname/finalisasi`, `stok_opname/reopen`.

**Skema database**

```text
stok_opname_periode(id, unit_idunit, tanggal, status 'DRAFT|FINAL',
                    jumlah_komp, jumlah_real, jumlah_selisih,
                    total_barang, terisi_barang,
                    mulai_by, finalisasi_by, tanggal_finalisasi,
                    created_at, updated_at)

stok_opname_draft (…, periode_id)
stok_opname       (…, periode_id)
```

---

## 2. Tutorial

### 2.1 Siapa boleh apa

| Role | Bisa pilih Unit & Tanggal | Bisa ubah/simpan/finalisasi | Tampilan |
|---|---|---|---|
| Admin Center (0), Admin Root (1), Direktur (2), Manager (34) | ✅ Ya | ❌ Hanya melihat | Bisa lihat semua unit & tanggal kapan saja |
| SPV (40) | ❌ (unit sendiri) | ❌ Hanya melihat | Lihat + filter selisih |
| Operator / inputer (kasir, teknisi, dll) | ❌ Tidak | ✅ Ya | Otomatis unit miliknya + tanggal hari ini |

### 2.2 Alur Kerja

```text
Buka menu Stok Opname
        │
        ▼
┌── BELUM DIMULAI ──┐   (unit & tanggal sudah terpilih otomatis)
│  Klik [Mulai Opname]│→  periode DRAFT dibuat + daftar barang dari stok kartu
└────────────────────┘
        │
        ▼
┌── DRAFT ─────────────────────────────────────────────┐
│  Isi kolom "Jumlah Real" sesuai hitung fisik         │
│  • Tekan Enter → pindah ke barang berikutnya         │
│  • Baris yang terisi otomatis hilang dari "Belum"    │
│  • Simpan kapan saja dengan [Simpan Draft] (dicicil) │
│  • Bisa cari barang dulu (search) lalu isi           │
└──────────────────────────────────────────────────────┘
        │  semua terisi
        ▼
┌── FINAL ───────────────────────────────┐
│  Data terkunci, tampil read-only      │
│  Terhitung di riwayat & KPI           │
│  Mau koreksi? [Reopen / Koreksi]      │
└────────────────────────────────────────┘
```

### 2.3 Langkah detail untuk Operator

1. Buka menu **Stok → Stok Opname**. (Unit & tanggal otomatis: unit Anda, tanggal hari ini.)
2. Jika status **BELUM DIMULAI**, klik **Mulai Opname** dan konfirmasi. Daftar barang muncul.
3. Di tab **Belum Terisi**, isi **Jumlah Real** (hasil hitung fisik) satu per satu:
   - Ketik angka lalu tekan **Enter** → fokus berpindah ke barang berikutnya.
   - Barang yang sudah terisi otomatis tak muncul lagi di tab Belum Terisi.
   - Selisih & progress dihitung otomatis di layar.
   - Gunakan tab **Belum Disimpan** untuk melihat barang yang sudah diinput di browser
     tapi belum disimpan server (daftar otomatis urut dari yang terbaru diinput).
4. Untuk mencari barang (misal hanya menginput sebagian), ketik di kotak **"Cari kode / nama barang"** — hasil pencarian menampilkan barang terisi maupun belum, siap diisi/dikoreksi.
5. Setiap saat bisa klik **Simpan Draft** — aman, data tersimpan walau belum lengkap.
6. Kapan saja siap, klik **Finalisasi** dan konfirmasi. Kalau masih ada barang kosong, muncul **peringatan** berisi jumlahnya — boleh lanjut (barang kosong tercatat `-`) atau batal dulu untuk melengkapi.
7. Jika salah setelah final, klik **Reopen / Koreksi**, perbaiki, lalu **Finalisasi** ulang.

### 2.4 Langkah untuk Admin / Manager

1. Buka **Stok → Stok Opname**.
2. Pilih **Unit** dan **Tanggal** di form filter (khusus role ini), klik **Tampilkan**.
3. Ikuti alur yang sama seperti di atas (Mulai → isi → Finalisasi / Reopen).
4. Riwayat periode unit yang dipilih tampil di bawah tabel (badge DRAFT/FINAL).

### 2.5 Tips

- **Mengisi banyak barang berurutan**: pastikan tab *Belum Terisi* aktif, cukup tekan **Enter** setelah tiap angka — tidak perlu klik Simpan setiap kali (Simpan hanya saat halaman ditutup/di-refresh).
- **Progress bar** menunjukkan posisi sekarang jelas; jangan sampai menutup halaman sebelum **Simpan Draft**.
- **Finalisasi boleh tidak lengkap**: muncul peringatan jumlah barang yang masih kosong saat konfirmasi — lanjutkan atau isi dulu, terserah Anda. Barang yang kosong tercatat `-` pada data final.
- **Role pengawas (Admin/Manager/SPV/Direktur) hanya dapat melihat** progres, riwayat, dan memakai filter selisih; tidak bisa menyimpan/memfinalisasi.
- Pencarian bisa digunakan untuk mengecek satu barang: ketik sebagian kode → muncul semua baris yang cocok apa pun statusnya.