# Stok Opname — Changelog & Tutorial

Modul **Stok Opname** di-refactor besar-besaran dari "input ke tabel langsung" menjadi
**alur kerja PERIODE (DRAFT → FINAL)** supaya dapat dicicil, terkunci dengan benar,
dan informatif. Berikut catatan perubahannya dan panduan penggunaannya.

---

## 1. Changelog

### v2.1 — Admin Root boleh menjalankan stok opname (2026-09-28)

- **Admin Root (ID_JABATAN 1) tidak lagi mode lihat.** Ia sudah bisa memilih unit,
  dan sekarang juga bisa menjalankan *Mulai / Simpan Draft / Finalisasi / Reopen*
  pada unit yang dipilih. Berguna untuk menutup opname unit yang operatornya tidak
  bisa melakukan hitung fisik.
- Konsep peran dipisah agar tidak ada yang ikut berubah:
  - `VIEW_ONLY_ROLES` (0, 2, 34, 40) — tidak boleh mutasi.
  - `CROSS_UNIT_ROLES` (0, 1, 2, 34) — boleh memilih unit.
  - `MONITOR_ROLES` (0, 1, 2, 34, 40) — boleh memakai filter selisih.
  Sebelumnya ketiganya diturunkan dari satu daftar `SUPERVISOR_ROLES`, sehingga
  ketika Admin Root dilepas dari daftar read-only, filter selisih miliknya ikut
  hilang. Sekarang ketiganya berdiri sendiri.
- **Nilai unit dari POST divalidasi.** Begitu `unit` menjadi input yang bisa
  dipilih, nilainya harus dicek terhadap daftar unit yang ada — `0`, `-1`, dan
  `999999` ditolak. Operator biasa tetap dikunci ke `session('ID_UNIT')`.
- Nilai yang diisi Admin Root tercatat atas `actor_id` dia sendiri di
  `stok_opname_audit`, sehingga terlihat jelas pada riwayat siapa yang menutup
  opname unit tersebut.

### v2.0 — Finalisasi wajib 100%, draft bisa dilanjutkan lintas hari, daftar hanya barang berstok (2026-09-28)

- **Finalisasi HANYA boleh bila semua barang berstok terisi.** v1.5 masih mengizinkan
  finalisasi parsial dengan peringatan; aturan itu dicabut. Tombol *Finalisasi* nonaktif
  selama masih ada barang kosong, dan server menolak dengan pesan berisi sisa barang.
- **Satu periode/unit = satu daftar barang berstok.** Saat *Mulai Opname*, yang masuk
  daftar hanya barang dengan `stok_akhir <> 0`. Barang stok 0 tidak perlu dihitung fisik;
  barang stok **negatif** tetap masuk justru karena itu kondisi bermasalah yang harus
  ditemukan. One unit hanya boleh punya satu DRAFT terbuka; untuk membuat periode baru,
  periode yang sedang menggantung harus diselesaikan (lihat banner *Lanjutkan draft ini*).
- **Draft bisa dilanjutkan di hari lain.** v1.x memaksa `tanggal = date('Y-m-d')`, jadi
  opname yang tidak selesai hari itu menggantung selamanya dan tidak pernah masuk hitungan
  KPI. v2 membuka DRAFT yang masih ada saat operator masuk, lintas hari. Audinya: sebelum
  September 2026 ada 44 DRAFT menggantung di semua unit.
- **Operator memfinalisasi sendiri**, tanpa approval, tapi wajib menyertakan **alasan**
  saat *Reopen*.
- **Reopen tidak menghapus data.** Nilai final lama ditandai `is_reverted = 1` dan tetap
  tersimpan. View `stok_barang` hanya menjumlahkan baris aktif (`is_reverted = 0`), jadi
  koreksi tetap mengubah `stok_akhir` seperti sebelumnya tetapi jejaknya bisa ditelusuri.
  Setiap aksi (`mulai`, `simpan`, `finalisasi`, `reopen`) dicatat di `stok_opname_audit`
  lengkap dengan aktor dan jumlah barang terisi.
- **Nilai kosong berarti dikosongkan**, bukan error — jadi salah input bisa dikoreksi tanpa
  jalur terpisah. Nilai negatif ditolak dan dilaporkan (hasil hitung fisik tidak mungkin negatif).
- **KPI hanya menghitung periode FINAL yang terisi penuh** (`terisi_barang = total_barang`).
  Semua pembaca KPI diseragamkan ke aturan ini; sebelumnya sebagian menghitung
  `stok_opname_draft` sehingga angkanya beda dari KPI resmi.
- **Keamanan**: route `stok_opname/loadtable` yang terbuka publik (tanpa login) sudah
  dihapus; route mutasi memakai filter `auth` + `csrf`; `AuthFilter` tidak lagi meloloskan
  sesi yang punya `ID_UNIT` tapi belum terautentikasi.
- **Data September 2026 dimigrasikan**: 43 DRAFT legacy lengkap difinalisasi lewat
  `php spark stokopname:cutover`, sehingga 48 periode menjadi FINAL. Periode legacy yang
  ter-finalize tanpa isian lengkap (1 periode) sengaja tidak dihitung KPI.

### v1.5 — Pengawas hanya melihat & finalisasi boleh parsial (dengan peringatan) (2026-09-07) — DIBATALKAN oleh v2.0

- **Role pengawas (Admin Center 0, Admin Root 1, Direktur 2, Manager 34, SPV 40)
  HANYA melihat** *(hanya berlaku di v1.5; lifting untuk Admin Root ada di v2.1)*: tombol *Mulai/Simpan Draft/Finalisasi/Reopen* disembunyikan,
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
  - *Reopen* → membuka kembali periode FINAL untuk koreksi. **v1.x menghapus** hasil final
    periode tersebut lalu menghitung ulang setelah finalisasi baru; **v2.0 menggantinya**
    dengan penandaan `is_reverted` supaya nilai lamanya tetap bisa ditelusuri.
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
- **Pembatasan role**: pemilih **Unit & Tanggal** hanya untuk Admin Root / Manager (ID_JABATAN `0,1,2,34`). Operator/inputer memakai unit miliknya (`ID_UNIT`) dan **v2 tidak lagi mengunci tanggal ke hari ini** — DRAFT bisa dilanjutkan lintas hari.
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
                    reopen_by, tanggal_reopen, alasan_reopen,
                    catatan_finalisasi,
                    created_at, updated_at)

stok_opname_draft (…, periode_id)              -- working set, UNIQUE (periode_id, barang_idbarang)
stok_opname       (…, periode_id, is_reverted,
                      reverted_by, reverted_at, revert_alasan)

stok_opname_audit(id, periode_id, unit_idunit, tanggal,
                  aksi 'mulai|simpan|finalisasi|reopen',
                  actor_id, jumlah_barang, jumlah_terisi, catatan, created_at)
```

> `stok_opname` tidak lagi punya UNIQUE `(periode_id, barang_idbarang)` karena satu
> periode bisa difinalisasi ulang setelah reopen — tiap finalisasi menulis revisi
> barisnya sendiri. View `stok_barang` hanya menjumlahkan baris dengan `is_reverted = 0`.

---

## 2. Tutorial

### 2.1 Siapa boleh apa

| Role | Bisa pilih Unit & Tanggal | Bisa ubah/simpan/finalisasi | Tampilan |
|---|---|---|---|
| **Admin Root (1)** | ✅ Ya | ✅ **Ya, unit mana pun** | Bisa initiation, mengisi, finalisasi, dan reopen unit yang dipilih |
| Admin Center (0), Direktur (2), Manager (34) | ✅ Ya | ❌ Hanya melihat | Bisa lihat semua unit & tanggal kapan saja |
| SPV (40) | ❌ (unit sendiri) | ❌ Hanya melihat | Lihat + filter selisih |
| Operator / inputer (kasir, teknisi, dll) | ❌ Tidak | ✅ Ya (unit sendiri, finalize tanpa approval) | Unit miliknya; DRAFT yang menggantung dibuka otomatis |

> **Admin Root bisa menjalankan opname, tapi pilih unit dulu.** Ia tetap bukan operator
> unit tersebut, jadi nilai yang ia isi tercatat atas namanya di `stok_opname_audit`
> (`actor_id`), bukan atas nama operator unit itu. Pakai ini terutama untuk menutup
> opname unit yang operatornya tidak bisa melakukan hitung fisik — jangan dipakai
> menggantikan hitung fisik operator.
>
> Catatan keamanan: `unit` milik Admin Root datang dari request, jadi **divalidasi**
> terhadap daftar unit yang ada. Nilai seperti `0`, `-1`, atau `999999` akan ditolak.
> Untuk operator biasa, `unit` dari POST diabaikan dan dipaksa memakai unit sesinya,
> jadi tidak ada jalur mengopname unit lain.

### 2.2 Alur Kerja

```text
Buka menu Stok Opname
        │
        ▼
┌── SUDAH ADA DRAFT MENGGANTUNG? ──┐
│  Muncul banner "Lanjutkan draft"  │→  buka periode itu (bisa beda tanggal/hari)
└───────────────────────────────────┘
        │ tidak ada
        ▼
┌── BELUM DIMULAI ──┐
│  Klik [Mulai Opname]│→  periode DRAFT dibuat, daftar = barang berstok (stok ≠ 0)
└────────────────────┘
        │
        ▼
┌── DRAFT ──────────────────────────────────────────────┐
│  Isi kolom "Jumlah Real" sesuai hitung fisik          │
│  • Tekan Enter → pindah ke barang berikutnya          │
│  • Baris yang terisi otomatis hilang dari "Belum"     │
│  • Simpan kapan saja dengan [Simpan Draft] (dicicil)  │
│  • Boleh tutup browser & lanjutkan besok              │
│  • Kosongkan kolom = membatalkan isian                │
└───────────────────────────────────────────────────────┘
        │ 100% terisi (tombol Finalisasi aktif)
        ▼
┌── FINAL ───────────────────────────────┐
│  Data terkunci, tampil read-only      │
│  Terhitung di riwayat & KPI           │
│  Koreksi? [Reopen / Koreksi] + alasan │
└────────────────────────────────────────┘
```

### 2.3 Langkah detail untuk Operator

1. Buka menu **Stok → Stok Opname**. Unit otomatis unit Anda. Kalau ada DRAFT dari hari
   sebelumnya, halaman langsung membuka DRAFT itu dan muncul banner pengingat.
2. Jika status **BELUM DIMULAI**, klik **Mulai Opname** dan konfirmasi. Hanya barang
   berstok (`stok ≠ 0`) yang masuk daftar. Kalau sudah ada DRAFT yang menggantung di unit
   ini, tombol **Mulai Opname** ditolak — selesaikan atau lanjutkan yang lama.
3. Di tab **Belum Terisi**, isi **Jumlah Real** (hasil hitung fisik) satu per satu:
   - Ketik angka lalu tekan **Enter** → fokus berpindah ke barang berikutnya.
   - Barang yang sudah terisi otomatis tak muncul lagi di tab Belum Terisi.
   - Selisih & progress dihitung otomatis di layar.
   - Gunakan tab **Belum Disimpan** untuk melihat barang yang sudah diinput di browser
     tapi belum disimpan server (daftar otomatis urut dari yang terbaru diinput).
4. Untuk mencari barang (misal hanya menginput sebagian), ketik di kotak **"Cari kode / nama barang"** — hasil pencarian menampilkan barang terisi maupun belum, siap diisi/dikoreksi.
5. Setiap saat bisa klik **Simpan Draft** — aman, data tersimpan walau belum lengkap dan
   bisa dilanjutkan di hari lain.
6. Tombol **Finalisasi** hanya aktif setelah **seluruh** barang berstok terisi. Kalau masih
   ada barang kosong, tombolnya nonaktif; kalau tetap dipaksa, server menolak dengan
   pesan berisi sisa barang yang belum diisi.
7. Jika salah setelah final, klik **Reopen / Koreksi**, isi **alasan** (wajib), perbaiki,
   lalu **Finalisasi** ulang. Nilai final sebelumnya tersimpan sebagai riwayat (tidak
   dihapus) dan tidak ikut dihitung lagi.

### 2.4 Langkah untuk Admin / Manager

1. Buka **Stok → Stok Opname**.
2. Pilih **Unit** dan **Tanggal** di form filter (khusus role ini), klik **Tampilkan**.
3. Ikuti alur yang sama seperti di atas (Mulai → isi → Finalisasi / Reopen).
4. Riwayat periode unit yang dipilih tampil di bawah tabel (badge DRAFT/FINAL).

### 2.5 Tips

- **Mengisi banyak barang berurutan**: pastikan tab *Belum Terisi* aktif, cukup tekan **Enter** setelah tiap angka — tidak perlu klik Simpan setiap kali (Simpan hanya saat halaman ditutup/di-refresh).
- **Progress bar** menunjukkan posisi sekarang jelas; jangan sampai menutup halaman sebelum **Simpan Draft**.
- **Finalisasi WAJIB lengkap**: tombolnya tidak aktif selama masih ada barang kosong.
  Ini bukan sekadar tampilan — server juga menolak, jadi tidak bisa difinalisasi sebagian.
- **Satu unit hanya boleh punya satu DRAFT terbuka.** Kalau daftar lama masih menggantung,
  selesaikan atau lanjutkan dulu sebelum membuat periode baru; dengan begitu tidak ada dua
  daftar yang sama-sama menggantung dan ambigu.
- **Draft aman lintas hari**: tutup browser di tengah hitung pun tidak hilang, asal sudah
  di-*Simpan Draft*. Kapan pun kembali, halaman membuka periode yang sama.
- **Reopen wajib beralasan** dan nilainya tidak hilang — revise lama ditandai tidak aktif,
  bukan dihapus, dan tampil di riwayat aktivitas.
- **KPI 4 periode FINAL per bulan**: satu unit harus menyelesaikan 4 opname penuh dalam
  sebulan. Kartu KPI di atas halaman menampilkan progres bulan berjalan.
- **Cek cepat kondisi data**: `php spark opname:verify` (read-only) menampilkan status
  skema v2, jumlah periode FINAL/DRAFT, baris yatim, dan progres KPI per unit. Jalankan
  setelah deploy atau migrasi.
- **Role mode-lihat (Admin Center 0, Direktur 2, Manager 34, SPV 40) hanya dapat
  melihat** progres, riwayat, dan memakai filter selisih; tidak bisa menyimpan/memfinalisasi.
  **Admin Root (1) berbeda**: ia boleh menjalankan opname (lihat tabel 2.1).
- Pencarian bisa digunakan untuk mengecek satu barang: ketik sebagian kode → muncul semua baris yang cocok apa pun statusnya.