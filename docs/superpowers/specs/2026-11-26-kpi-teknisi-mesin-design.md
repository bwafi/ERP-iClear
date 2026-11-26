# KPI & Komisi Teknisi Mesin — Desain (Opsi 1)

**Tanggal:** 2026-11-26
**Status:** menunggu persetujuan
**Terkait:** `docs/pedoman-kpi/kpi-teknisi-mesin.md`, PRD Modul 04, PRD Modul 07, PRD Modul 08, `docs/architecture/kpi-business-rules.md`, `docs/architecture/kpi-engine.md`

## Intent

Membuat penilaian KPI dan komisi teknisi mesin yang terpisah dari teknisi
interface. `service_by` tetap menjadi teknisi interface dan tidak diubah.
Pekerjaan mesin internal dicatat pada `teknisi_mesin_id` beserta riwayat
percobaan, alasan batal/rujuk, bukti, dan verifikasi SPV. Mesin yang sejak awal
dikirim ke teknisi eksternal dicatat dengan mode `EXTERNAL_ONLY` dan hanya
menyimpan nama teknisi eksternal.

Kebijakan sumber masih berstatus **rancangan**. Implementasi menunggu
persetujuan desain ini dan pengesahan manajemen.

## Ringkasan keputusan yang diusulkan

| No | Keputusan | Alasan |
|---|---|---|
| 1 | `service_by` tetap teknisi interface | Tidak mengubah KPI `OMSET_TEKNISI`/`CUSTOMER_COUNT` dan alur intake yang sudah berjalan |
| 2 | Tambah `service.teknisi_mesin_id` dan tabel riwayat `service_teknisi` | Satu service bisa berpindah teknisi; atribusi harus auditable |
| 3 | Satu form gabungan **Batalkan / Rujuk** | Opsi 1 yang dipilih user; tidak ada dua alur terpisah |
| 4 | Pembatalan memakai status `90/91`, bukan hard delete; rujukan tidak mengubah status service | Data cancel/rujuk harus tetap ada untuk evaluasi |
| 5 | Rujukan mendukung teknisi **internal** dan **eksternal**; data eksternal hanya nama | Kebutuhan operasional di luar iClear; data eksternal diminimalkan sesuai instruksi user |
| 6 | KPI teknisi mesin memakai grup bobot baru `teknisi_mesin` 40/20/20/20 | Tidak menimpa KPI jabatan 36 yang sekarang 70/15/15 |
| 7 | Komisi dihitung dari **jasa bersih**, bukan total tagihan | Sparepart bukan harga jual servis dan sudah punya HPP sendiri |
| 8 | Tarif komisi 35% disimpan sebagai konfigurasi | Kebijakan bisa berubah tanpa ubah kode |
| 9 | Verifikasi SPV dilakukan bulanan/bulk | Alur harian tidak tertahan |
| 10 | Insentif 1% `KEPALA_TOKO` dan `fee_teknisi` tidak diubah pada fase ini | Keduanya punya dasar dan tujuan berbeda; penggantian butuh keputusan terpisah |
| 11 | Tambah `service.mesin_mode` `INTERNAL`/`EXTERNAL_ONLY` | Mesin yang sejak awal dikirim ke teknisi eksternal tidak punya percobaan internal |

## Non-goals

- Tidak mengubah arti `service_by` atau KPI teknisi interface yang sudah ada.
- Tidak mengganti bobot KPI jabatan 36 (Omset Toko 70, Omset Teknisi 15,
  Customer 15) pada fase ini.
- Tidak mengubah skor absensi 80/5/10/5 yang sudah dipakai payroll.
- Tidak membuat master PL 1/2/3. Harga jual memakai baris jasa yang benar-benar
  ada di transaksi.
- Tidak mengimplementasikan Pasal 8 (pembelian bahan) dan Pasal 9 (insiden)
  pada fase ini.
- Tidak mengubah payroll historis atau jurnal yang sudah terposting.
- Tidak menjadikan verifikasi SPV sebagai penghalang harian. SPV memverifikasi
  secara bulanan/bulk.
- Tidak menghapus permanen service yang sudah punya jejak KPI.
- Tidak memberi KPI rujukan atau komisi teknisi mesin pada service
  `EXTERNAL_ONLY` atau rujukan eksternal. Rujukan eksternal hanya dapat
  memengaruhi KPI cancel bila SPV membuktikan kesalahan teknisi pertama.
- Tidak mengubah KPI interface (`service_by`) untuk service `EXTERNAL_ONLY`.
  Bila manajemen ingin mengecualikannya, itu keputusan terpisah.

## Kondisi aktual yang menjadi dasar

- `service_by` diisi saat intake dengan akun yang login
  (`app/Controllers/Service.php` sekitar baris 226). Alur pembayaran aktif
  (`Service::insert_pembayaran`) tidak menulis ulang `service_by`; penulisan
  lama hanya ada di route `Riwayat_Service::insert_pembayaran` yang sudah
  dinonaktifkan.
- Belum ada kolom teknisi mesin, riwayat percobaan, alasan batal/rujuk
  terstruktur, bukti, atau verifikasi SPV.
- Pembatalan dari halaman proses service memakai status `90`
  (`Riwayat_Service::update_dibatalkan`), tetapi masih ada jalur
  `Service::cancel_service` yang menghapus service, kerusakan, sparepart, dan
  ledger DP secara permanen.
- `status_service = 4` dapat tercapai walau pembayaran belum lunas
  (`Riwayat_Service::update_sudah_diambil`), sehingga status selesai saja tidak
  cukup untuk menentukan komisi.
- KPI jabatan 36 saat ini Omset Toko 70, Omset Teknisi 15, Customer 15
  (`app/Database/Seeds/KPIConfigurationSeeder.php`). Absensi adalah grup
  terpisah 80/5/10/5 (`AttendanceAggregationService`), bukan rumus 40 poin
  kebijakan.
- Belum ada tabel komisi, ledger komisi, komponen payroll komisi, atau laporan
  KPI teknisi mesin.
- `fee_teknisi` di master tarif jasa adalah HPP jasa
  (`barang.harga_beli`/`hpp_barang`/`hpp_unit`), bukan komisi yang dibayarkan
  ke teknisi.
- Tidak ada tier PL 1/2/3 di kode; harga jasa disimpan per baris `barang`
  berjenis `jasa` dan dijual melalui `service_sparepart`.

## Model data

Semua perubahan schema lewat migrasi baru. Tidak ada DDL/DML destruktif pada
database nyata.

### 1. `service`

Tambahan kolom:

| Kolom | Tipe | Keterangan |
|---|---|---|
| `mesin_mode` | ENUM(`INTERNAL`,`EXTERNAL_ONLY`) NOT NULL DEFAULT `INTERNAL` | Mode penanganan mesin sejak intake |
| `teknisi_mesin_id` | INT NULL, index | Penunjuk teknisi mesin internal aktif; `NULL` = belum ditugaskan atau ditangani eksternal |
| `teknisi_mesin_eksternal_nama` | VARCHAR(150) NULL | Penunjuk nama teknisi eksternal aktif; hanya nama, tanpa kontak/invoice |
| `teknisi_mesin_assigned_at` | DATETIME NULL | Kapan penugasan aktif dibuat (internal maupun eksternal) |
| `teknisi_mesin_finished_at` | DATETIME NULL | Kapan pekerjaan mesin dinyatakan selesai dan siap diverifikasi |

Aturan:

- `INTERNAL`: pekerjaan ditangani teknisi internal. `teknisi_mesin_id` wajib
  sebelum pengerjaan; `teknisi_mesin_eksternal_nama` `NULL` selama belum
  dirujuk eksternal. Riwayat percobaan ada di `service_teknisi`.
- `EXTERNAL_ONLY`: mesin langsung dikirim ke teknisi eksternal sejak intake.
  `teknisi_mesin_id` wajib `NULL`, `teknisi_mesin_eksternal_nama` wajib, dan
  tidak ada baris `service_teknisi`. Service ini tidak masuk KPI/komisi teknisi
  mesin internal.
- `mesin_mode` tidak diubah saat rujukan internal→eksternal. Service tetap
  `INTERNAL` karena sudah ada percobaan internal; `teknisi_mesin_id`
  dikosongkan dan `teknisi_mesin_eksternal_nama` diisi.
- `teknisi_mesin_id` dan `teknisi_mesin_eksternal_nama` adalah penunjuk cepat
  untuk daftar dan laporan. Sumber kebenaran riwayat tetap `service_teknisi`
  dan `service_teknisi_event`. Penulisan dilakukan dalam satu transaksi.

### 2. `service_teknisi` — riwayat percobaan

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT PK | |
| `service_idservice` | INT, index | Relasi ke `service.idservice` |
| `teknisi_id` | INT, index | Akun teknisi mesin internal |
| `attempt_no` | INT | Urutan percobaan, mulai 1 |
| `role` | ENUM(`PRIMARY`,`SECOND`) | Percobaan pertama atau lanjutan |
| `started_at` | DATETIME NULL | Mulai pengerjaan efektif |
| `finished_at` | DATETIME NULL | Selesai dan siap diverifikasi |
| `outcome` | ENUM(`IN_PROGRESS`,`SUCCESS`,`CANCELED`,`REFERRED`,`REASSIGNED`,`FAILED`) | Hasil percobaan |
| `event_id` | BIGINT NULL | Relasi ke event batal/rujuk bila ada |
| `created_by` | INT | Pencatat |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

Aturan:

- Satu service hanya boleh punya satu baris `IN_PROGRESS` pada satu waktu.
- Reassign menutup baris lama sebagai `REASSIGNED`, lalu membuat baris baru.
- Rujukan internal menutup baris pertama sebagai `REFERRED`, lalu membuat baris
  `SECOND` untuk teknisi penerima.
- Keberhasilan teknisi kedua menutup baris kedua sebagai `SUCCESS`.

### 3. `service_teknisi_event` — batal/rujuk gabungan

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT PK | |
| `service_idservice` | INT, index | |
| `from_teknisi_id` | INT NULL, index | Teknisi yang dilewati/dibatalkan; `NULL` hanya untuk cancel service `EXTERNAL_ONLY` |
| `event_type` | ENUM(`CANCEL`,`REFERRAL`) | Satu event bisa memengaruhi dua KPI |
| `reason_code` | VARCHAR(40) | Alasan terstruktur |
| `reason_note` | TEXT | Penjelasan wajib |
| `diagnosis_note` | TEXT | Diagnosis teknisi interface/pertama |
| `evidence_json` | JSON/TEXT NULL | Daftar path/foto/dokumen; wajib untuk cancel/rujukan internal, `NULL` untuk eksternal |
| `second_type` | ENUM(`NONE`,`INTERNAL`,`EXTERNAL`) | Jenis teknisi kedua |
| `second_teknisi_id` | INT NULL | Wajib bila internal |
| `second_name` | VARCHAR(150) NULL | Wajib bila eksternal; hanya nama |
| `second_result` | ENUM(`PENDING`,`SUCCESS`,`FAILED`,`UNKNOWN`) NULL | Hasil teknisi kedua; hanya diisi untuk internal, `NULL` untuk eksternal |
| `same_damage` | TINYINT(1) NULL | Checklist Pasal 6.1 |
| `comparable_condition` | TINYINT(1) NULL | Checklist Pasal 6.2 |
| `no_material_change` | TINYINT(1) NULL | Checklist Pasal 6.3 |
| `first_tech_facility_ok` | TINYINT(1) NULL | Checklist Pasal 6.4 |
| `second_result_verified` | TINYINT(1) NULL | Checklist Pasal 6.5 |
| `affects_cancel` | TINYINT(1) NULL | Hasil verifikasi SPV |
| `affects_referral` | TINYINT(1) NULL | Hasil verifikasi SPV |
| `verification_status` | ENUM(`PENDING`,`APPROVED`,`REJECTED`) | Default `PENDING` |
| `verified_by` | INT NULL | SPV/Manager |
| `verified_at` | DATETIME NULL | |
| `verification_note` | TEXT NULL | Alasan approve/reject |
| `created_by` | INT | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

Untuk `second_type = EXTERNAL`, kelompok data teknisi kedua hanya mengisi
`second_name`. Tidak ada kontak, nomor invoice, hasil, atau bukti. Rujukan
eksternal tidak dapat dinilai sebagai KPI rujukan karena syarat Pasal 6.5
(hasil teknisi kedua terbukti) tidak dapat diverifikasi. Checklist Pasal 6
hanya wajib untuk rujukan internal.

`reason_code` yang diusulkan:

- `CUSTOMER_CANCEL` — customer membatalkan sendiri.
- `CUSTOMER_REJECT_COST` — customer tidak setuju biaya.
- `UNIT_UNREPAIRABLE` — unit dinyatakan tidak layak diperbaiki.
- `OUT_OF_SCOPE` — di luar kemampuan/fasilitas yang disepakati.
- `TECHNICAL_FAILURE` — kesalahan teknis.
- `INCOMPETENCE` — tidak mampu mengerjakan yang seharusnya mampu.
- `NEGLIGENCE` — kelalaian pengerjaan.
- `OTHER` — wajib menjelaskan di `reason_note`.

`affects_cancel` dan `affects_referral` dipisah karena satu pekerjaan dapat
memengaruhi kedua KPI (Pasal 6.4 butir 5). Nilai default diusulkan dari
`reason_code`, tetapi SPV dapat mengubahnya dengan catatan. Untuk rujukan
eksternal, `affects_referral` selalu `0`; `affects_cancel` hanya dapat `1` bila
SPV membuktikan kesalahan teknisi pertama dari diagnosis/alasan, bukan dari
hasil teknisi eksternal yang tidak dicatat.

### 4. `service_teknisi_wait` — waktu tunggu hardware

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT PK | |
| `service_teknisi_id` | BIGINT, index | Percobaan terkait |
| `hardware_name` | VARCHAR(150) | Hardware yang ditunggu |
| `started_at` | DATETIME | Mulai menunggu |
| `ended_at` | DATETIME NULL | Hardware tersedia |
| `status` | ENUM(`PENDING`,`APPROVED`,`REJECTED`) | Verifikasi SPV |
| `verified_by` | INT NULL | |
| `verified_at` | DATETIME NULL | |
| `note` | TEXT NULL | Bukti/alasan |
| `created_at` | DATETIME | |

Hanya durasi `APPROVED` yang dikurangkan dari waktu pengerjaan. Menunggu bahan
habis pakai, antrean, atau keterlambatan lain tidak otomatis dikecualikan
(Pasal 7.1 butir 7).

### 5. Konfigurasi KPI

Memakai tabel yang sudah ada:

- `kpi_components`: tambah komponen `TEKNISI_MESIN_ABSENSI`,
  `TEKNISI_MESIN_CANCEL`, `TEKNISI_MESIN_REFERRAL`,
  `TEKNISI_MESIN_SPEED`.
- `kpi_weights`: tambah baris untuk `position_id = 36`,
  `weight_group = 'teknisi_mesin'`, bobot 40/20/20/20, dengan
  `effective_from` tanggal berlaku.
- Grup `kpi` yang lama (70/15/15) tidak diubah.

Dengan pemisahan grup, KPI payroll/tunjangan kinerja yang lama tetap berjalan,
sedangkan komisi memakai grup baru.

### 6. `teknisi_mesin_kpi_periods` — snapshot bulanan

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT PK | |
| `employee_id` | INT, index | |
| `unit_id` | INT, index | Unit saat periode |
| `period` | CHAR(7) | `YYYY-MM` |
| `hari_wajib` | INT | Hari kerja wajib |
| `hari_patuh` | INT | Hari patuh |
| `attendance_score` | DECIMAL(6,4) | 0–40 |
| `eligible_work_count` | INT | Penyebut cancel/referral |
| `cancel_count` | INT | Cancel akibat teknisi yang disetujui |
| `cancel_score` | DECIMAL(6,4) | 0–20 |
| `referral_count` | INT | Rujukan terbukti berhasil |
| `referral_score` | DECIMAL(6,4) | 0–20 |
| `completed_unit_count` | INT | Unit selesai untuk kecepatan |
| `effective_days_total` | DECIMAL(10,4) | Total hari efektif |
| `average_days` | DECIMAL(10,4) NULL | Rata-rata hari |
| `speed_score` | DECIMAL(6,4) | 0–20 |
| `total_score` | DECIMAL(6,4) | 0–100 |
| `status` | ENUM(`DRAFT`,`PENDING_VERIFICATION`,`VERIFIED`,`LOCKED`) | |
| `verified_by` | INT NULL | |
| `verified_at` | DATETIME NULL | |
| `note` | TEXT NULL | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

Unique key: (`employee_id`, `period`). Snapshot disimpan agar angka yang sudah
dibayar tidak berubah karena data harian berubah.

### 7. `teknisi_mesin_commission_ledger` — ledger komisi

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT PK | |
| `employee_id` | INT, index | |
| `period` | CHAR(7) | |
| `kpi_period_id` | BIGINT | Snapshot KPI yang dipakai |
| `base_jasa` | BIGINT | Jasa bersih |
| `rate_percent` | DECIMAL(6,4) | Default 35 |
| `kpi_score` | DECIMAL(6,4) | 0–100 |
| `gross_commission` | BIGINT | `base_jasa × rate` |
| `net_commission` | BIGINT | `gross_commission × kpi_score / 100` |
| `adjustment_of_id` | BIGINT NULL | Untuk koreksi periode berikutnya |
| `status` | ENUM(`DRAFT`,`APPROVED`,`PAID`,`VOID`) | |
| `payroll_reference` | VARCHAR(100) NULL | Referensi payroll/slip |
| `approved_by` | INT NULL | |
| `approved_at` | DATETIME NULL | |
| `paid_at` | DATETIME NULL | |
| `note` | TEXT NULL | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

Unique key untuk baris normal: (`employee_id`, `period`,
`adjustment_of_id`). Baris `PAID`/`LOCKED` tidak boleh diubah; koreksi membuat
baris adjustment baru.

## Alur operasional

### 1. Mode mesin dan penugasan teknisi

1. Intake tetap membuat service dengan `service_by` = akun interface yang
   login dan memilih `mesin_mode`.
2. `INTERNAL`: admin/SPV menugaskan `teknisi_mesin_id` dari halaman detail
   atau proses service. Server memvalidasi akun aktif, jabatan teknisi, dan
   unit.
3. Penugasan internal membuat baris `service_teknisi` `PRIMARY` dengan
   `started_at`. `service.teknisi_mesin_id` diisi dalam transaksi yang sama.
4. `EXTERNAL_ONLY`: admin/SPV mengisi `teknisi_mesin_eksternal_nama` dan
   `teknisi_mesin_assigned_at`. Tidak ada baris `service_teknisi`, tidak ada
   KPI/komisi teknisi mesin internal, dan `teknisi_mesin_id` tetap `NULL`.
5. Saat pekerjaan selesai dan siap diverifikasi, baris internal ditutup
   `SUCCESS` dan `finished_at` diisi. Untuk `EXTERNAL_ONLY`,
   `teknisi_mesin_finished_at` diisi tanpa baris percobaan. Status service
   tetap mengikuti alur yang ada (`3` bisa diambil, `4` sudah diambil).
6. Reassign internal menutup baris lama `REASSIGNED` dan membuat baris baru.
   Reassign eksternal hanya mengganti `teknisi_mesin_eksternal_nama` dengan
   audit log. Riwayat tetap terlihat.

### 2. Mesin langsung ke teknisi eksternal (`EXTERNAL_ONLY`)

1. Digunakan bila unit memang dikirim ke teknisi di luar iClear sejak awal,
   tanpa percobaan teknisi mesin internal.
2. Intake/penugasan hanya mencatat `teknisi_mesin_eksternal_nama`. Tidak ada
   kontak, nomor invoice, hasil, bukti, atau checklist Pasal 6.
3. Tidak ada baris `service_teknisi`, tidak ada event rujukan, dan
   `teknisi_mesin_id` tetap `NULL`.
4. Service tetap dapat ditagih dan diproses lewat alur service yang ada.
   Keputusan siapa yang menagih/membayar teknisi eksternal tetap mengikuti
   pertanyaan terbuka nomor 4.
5. Service `EXTERNAL_ONLY` tidak masuk KPI cancel, rujukan, kecepatan, maupun
   komisi teknisi mesin internal. KPI interface (`service_by`) tidak diubah
   pada fase ini.
6. Bila service `EXTERNAL_ONLY` dibatalkan, event `CANCEL` boleh dicatat
   dengan `from_teknisi_id = NULL` untuk audit operasional, tetapi tidak
   memengaruhi KPI teknisi mesin internal.

### 3. Form gabungan Batalkan / Rujuk

Satu tombol di `/proses_service` membuka form dengan pilihan:

- **Batalkan**: pekerjaan tidak dilanjutkan.
- **Rujuk**: pekerjaan dilanjutkan ke teknisi lain.

Field wajib:

- Alasan terstruktur dan catatan.
- Diagnosis/kerusakan awal.
- Bukti (foto/dokumen): wajib untuk cancel/rujukan internal; tidak diminta
  untuk eksternal.
- Jenis teknisi kedua: tidak ada, internal, atau eksternal.
- Untuk internal: pilih akun teknisi penerima, checklist Pasal 6, dan hasil
  teknisi kedua (pending/success/failed) bila sudah diketahui.
- Untuk eksternal: cukup nama teknisi. Tidak ada kontak, nomor invoice,
  hasil, atau bukti.

Aksi:

- **Batalkan**: service diubah ke status `90` (dibatalkan) atau `91`
  (dibatalkan sudah diambil) secara soft-cancel. Tidak ada hard delete.
- **Rujuk**: status service tidak diubah; percobaan pertama ditutup
  `REFERRED`, lalu pekerjaan dilanjutkan sesuai jenis teknisi kedua.
- Event disimpan dengan `verification_status = PENDING`.
- Rujukan internal membuat baris `SECOND` dan memindahkan
  `service.teknisi_mesin_id` ke teknisi penerima.
- Rujukan eksternal mengosongkan `service.teknisi_mesin_id`, mengisi
  `service.teknisi_mesin_eksternal_nama` dan
  `service.teknisi_mesin_assigned_at`, tidak membuat baris `service_teknisi`,
  dan tidak memberi KPI rujukan/komisi internal. Event hanya menyimpan nama
  teknisi eksternal.
- Rujukan eksternal tidak dapat menjadi KPI rujukan (`affects_referral = 0`);
  `affects_cancel` mengikuti verifikasi SPV.
- Jika teknisi kedua internal berhasil, baris kedua ditutup `SUCCESS`; event
  tetap `PENDING` sampai SPV memverifikasi.
- Jika teknisi kedua internal gagal, event kedua dicatat sebagai cancel untuk
  teknisi kedua. Teknisi pertama tetap dapat terkena KPI rujukan bila syarat
  Pasal 6 terpenuhi.

### 4. Waktu tunggu hardware

1. Teknisi menekan "Mulai menunggu hardware" dan mengisi nama hardware.
2. Saat hardware tersedia, teknisi menekan "Hardware tersedia".
3. SPV memverifikasi. Hanya durasi `APPROVED` yang dikurangkan.
4. Beberapa periode tunggu dijumlahkan.

### 5. Verifikasi SPV

- SPV melihat daftar event `PENDING` per unit/periode.
- SPV dapat approve/reject satu per satu atau bulk.
- `affects_cancel` dan `affects_referral` diisi saat approve.
- Event `PENDING` tidak dihitung di KPI final. Event `REJECTED` tidak dihitung.
- Verifikasi tidak memblokir alur harian.
- Jika event disetujui setelah payroll terkunci, koreksi masuk periode
  berikutnya sebagai adjustment.

## Rumus KPI

Semua nilai dibatasi 0 sampai bobot masing-masing. Total memakai presisi
penuh; tampilan dua angka desimal.

### 1. Absensi — 40 poin

```
Nilai Absensi = 40 × (hari_patuh ÷ hari_wajib)
```

- `hari_wajib` = tanggal dalam bulan dengan roster shift kerja
  (`PAGI`/`SIANG`/`FULL`/`PS`), setelah mengecualikan `OFF`, `IZIN`, `SAKIT`,
  dan `CUTI`.
- `hari_patuh` = hari wajib dengan check-in dan check-out valid, tidak
  terlambat tanpa izin, dan tidak pulang lebih awal tanpa izin.
- Sumber: `presensi_roster`, `presensi`, dan aturan
  `AttendanceScoreCalculator`; bukan skor 80/5/10/5.
- Ambang "patuh" yang diusulkan: skor harian minimal 4 (toleransi terlambat
  ≤3 menit) dan check-out tercatat. Ambang ini perlu konfirmasi.
- Bila tidak ada hari wajib karena cuti/penugasan resmi sepanjang periode,
  komponen ditandai `N/A` dan ditinjau SPV (Pasal 4.4).

### 2. Cancel — 20 poin

```
Nilai Cancel = 20 × (1 − cancel_teknisi ÷ total_pekerjaan_layak)
```

- `cancel_teknisi` = event `CANCEL`/`REFERRAL` dengan `affects_cancel = 1`
  setelah verifikasi SPV.
- `total_pekerjaan_layak` = pekerjaan selesai + cancel akibat teknisi yang
  sudah final. Cancel yang dikecualikan tidak masuk pembilang maupun penyebut.
- Service `EXTERNAL_ONLY` tidak masuk pembilang maupun penyebut KPI teknisi
  mesin internal.
- Rujukan eksternal tidak masuk `rujukan_terbukti`. Untuk KPI cancel, event
  hanya masuk bila SPV menetapkan `affects_cancel = 1`.
- Cancel `PENDING` belum masuk perhitungan final.
- Nilai minimum 0.

### 3. Cancel tetapi berhasil di teknisi lain — 20 poin

```
Nilai Rujukan = 20 × (1 − rujukan_terbukti ÷ total_pekerjaan_layak)
```

- `rujukan_terbukti` = event `REFERRAL` dengan `affects_referral = 1` setelah
  SPV memverifikasi seluruh syarat Pasal 6:
  1. kerusakan sama;
  2. kondisi unit masih dapat dibandingkan;
  3. tidak ada perubahan komponen/kondisi material;
  4. teknisi pertama punya fasilitas dan kesempatan layak;
  5. hasil teknisi kedua terbukti berhasil;
  6. SPV memverifikasi seluruh bukti.
- Penyebut sama dengan KPI cancel agar konsisten (Pasal 6.2).
- Rujukan eksternal tidak pernah masuk `rujukan_terbukti` karena hasil dan
  bukti teknisi kedua tidak dicatat.
- Satu pekerjaan dapat masuk cancel dan rujukan sekaligus bila memenuhi kedua
  syarat (Pasal 6.4 butir 5).

### 4. Kecepatan — 20 poin

```
Rata-rata Hari = total_hari_efektif ÷ jumlah_unit_dinilai

Jika rata-rata ≤ 3 hari:  Nilai Kecepatan = 20
Jika rata-rata > 3 hari:  Nilai Kecepatan = 20 × (3 ÷ rata-rata)
```

- `hari_efektif = (finished_at − started_at) − durasi tunggu hardware yang
  disetujui`.
- Hanya unit selesai dan dibayar yang masuk `jumlah_unit_dinilai`.
- Pekerjaan yang melewati akhir bulan dicatat outstanding dan dinilai saat
  selesai (Pasal 7.7); tidak dianggap tepat waktu.
- Unit yang dibatalkan/dirujuk tidak masuk kecepatan, tetapi masuk cancel/
  rujukan.
- Hanya percobaan teknisi internal; service `EXTERNAL_ONLY` dan rujukan
  eksternal tidak masuk `jumlah_unit_dinilai`.
- Nilai maksimal 20, minimum 0.

### 5. Total KPI

```
Total KPI = Nilai Absensi + Nilai Cancel + Nilai Rujukan + Nilai Kecepatan
```

Bila salah satu komponen `N/A`, total dinormalisasi dari bobot yang tersedia:

```
Total KPI = 100 × Σ(skor komponen tersedia) ÷ Σ(bobot komponen tersedia)
```

Bila semua komponen tersedia, hasilnya sama dengan penjumlahan Pasal 10.
Normalisasi hanya untuk data yang benar-benar tidak ada, bukan untuk
menyembunyikan nilai 0.

Contoh kebijakan:

| Indikator | Data | Nilai |
|---|---|---:|
| Absensi | 23 dari 26 hari | 35,38 |
| Cancel | 3 dari 30 pekerjaan | 18,00 |
| Rujukan | 2 dari 30 pekerjaan | 18,67 |
| Kecepatan | rata-rata 4 hari | 15,00 |
| **Total** | | **87,05** |

## Komisi

### Dasar komisi

Komisi memakai **jasa bersih**:

```
base_jasa = Σ service_sparepart.sub_total
            untuk baris yang barang.jenis_barang = 'jasa'
```

- `sub_total` sudah memperhitungkan diskon per baris.
- Sparepart tidak masuk base.
- `total_service`/`harus_dibayar` tidak dipakai langsung karena mengandung
  sparepart.
- Service garansi, service batal, service `EXTERNAL_ONLY`, service rujukan
  eksternal, dan service yang belum dibayar tidak masuk base.
- Tidak ada konversi PL karena PL 1/2/3 belum ada di master. Harga jasa yang
  dipakai adalah harga final transaksi.

Atribusi komisi: hanya service dengan percobaan `service_teknisi` internal
terakhir berstatus `SUCCESS` yang masuk base. Komisi diatribusikan ke
`teknisi_id` percobaan itu. Aturan ini otomatis mengecualikan `EXTERNAL_ONLY`
dan rujukan eksternal, serta memastikan komisi rujukan internal jatuh ke
teknisi penerima yang berhasil.

### Rumus

```
Komisi Dasar   = base_jasa × 35%
Komisi Dicairkan = Komisi Dasar × (Total KPI ÷ 100)
```

Tarif 35% disimpan di konfigurasi, bukan hardcode. Pembulatan akhir ke rupiah
dilakukan setelah perkalian KPI; perhitungan memakai presisi penuh.

Contoh: base Rp20.000.000, KPI 87,05 → komisi dasar Rp7.000.000 → komisi cair
Rp6.093.500.

### Syarat dibayar

Service dianggap layak komisi bila:

1. `status_service = 4` (sudah diambil/selesai);
2. `total_service > 0`;
3. `dp_bayar + bayar ≥ total_service` (lunas);
4. bukan service garansi tanpa nilai jual;
5. tidak berstatus batal (`90`/`91`);
6. tidak ada refund yang belum diselesaikan;
7. memiliki percobaan `service_teknisi` internal terakhir dengan
   `outcome = SUCCESS`; komisi diatribusikan ke `teknisi_id` percobaan itu.

Status 4 saja tidak cukup karena status itu bisa tercapai walau masih ada sisa
tagihan.

### Refund, garansi, dan koreksi

- Refund sebelum periode dikunci: kurangi `base_jasa` pada periode yang sama.
- Refund setelah periode dikunci/dibayar: buat baris adjustment negatif pada
  bulan refund, jangan mengubah snapshot lama.
- Klaim garansi yang tidak menghasilkan pendapatan baru tidak menambah komisi.
- Refund garansi yang mengurangi pendapatan mengurangi komisi pada bulan
  refund, mengikuti pola `ModelRefundGaransiLoss`.
- Komisi yang sudah `PAID` tidak diedit; koreksi selalu lewat adjustment.

### Hubungan dengan insentif yang sudah ada

- Insentif 1% `KEPALA_TOKO` tetap berjalan pada fase ini. Komisi 35% adalah
  komponen baru dengan dasar jasa, bukan pengganti otomatis.
- `fee_teknisi` tetap menjadi HPP jasa, bukan payout. Tidak dipakai sebagai
  base komisi.
- Bila manajemen ingin menghentikan 1% untuk teknisi mesin, itu perubahan
  kebijakan terpisah dengan regression test payroll.

## Integrasi payroll

- Tambah komponen `KOMISI_TEKNISI_MESIN` di `salary_components`.
- `SalaryCalculationService` menerima nominal komisi sebagai komponen
  `manual_amount`/`external_amount` (nama tipe final diserahkan ke
  implementer).
- `PayrollGenerator` memanggil `TeknisiMesinCommissionService` untuk periode
  payroll, lalu meneruskan nominal komisi ke perhitungan gaji.
- Komisi tampil sebagai baris terpisah di slip, bukan dicampur ke `insentif`.
- Hanya KPI `VERIFIED`/`LOCKED` yang dibayar. KPI `PENDING` ditahan sampai
  verifikasi; bila cutoff payroll tiba, komisi masuk periode berikutnya.
- Ledger komisi idempoten per (`employee_id`, `period`). Payroll ulang tidak
  menggandakan komisi.

## UI dan route yang diusulkan

### `/proses_service`

- Kolom mode mesin (`INTERNAL`/`EXTERNAL_ONLY`), teknisi mesin internal atau
  nama teknisi eksternal, dan status penugasan.
- Aksi assign/reassign teknisi internal dan input/ganti nama teknisi eksternal.
- Aksi mulai/selesai pengerjaan.
- Aksi mulai/selesai tunggu hardware.
- Tombol **Batalkan / Rujuk** dengan form gabungan.
- Form rujukan eksternal hanya meminta nama teknisi; tidak ada kontak, nomor
  invoice, hasil, atau bukti.

### `/detail/riwayat_service/{id}?tab=teknisi_mesin`

- Riwayat percobaan.
- Mode mesin dan nama teknisi eksternal bila ada.
- Event batal/rujuk dan bukti internal.
- Waktu tunggu hardware.
- Dampak KPI dan status verifikasi.

### `/kpi_teknisi_mesin`

- Rekap bulanan per teknisi/unit.
- Drill-down ke service.
- Daftar event `PENDING`.
- Aksi approve/reject bulk.
- Status snapshot: draft, pending, verified, locked.
- Ekspor.

### Payroll/slip

- Baris `Komisi Teknisi Mesin`.
- Dasar jasa, KPI, tarif, nominal.

### Route dan keamanan

- Route tulis baru memakai filter `['auth', 'csrf']` dan token form.
- Scope unit divalidasi di server memakai `ServiceUnitScopeService`/
  `spv_units`; `unit_id` dari request tidak dipercaya.
- Teknisi hanya dapat mengubah percobaan miliknya.
- Nama teknisi eksternal hanya dapat diubah admin/SPV.
- Hanya SPV/Manager/HO yang dapat memverifikasi.
- Setiap approve/reject menulis audit log.

## Service/controller/model baru

Diusulkan:

- `App\Services\Service\ServiceTeknisiService` — assign, reassign, mulai,
  selesai, tutup percobaan.
- `App\Services\Service\ServiceTeknisiEventService` — form gabungan, validasi
  bukti, soft cancel, rujukan internal/eksternal.
- `App\Services\Kpi\TeknisiMesinKpiService` — hitung snapshot bulanan.
- `App\Services\Kpi\TeknisiMesinAttendanceService` — rumus 40 poin dari
  roster/presensi.
- `App\Services\Kpi\TeknisiMesinSpeedService` — waktu efektif dan pengecualian
  hardware.
- `App\Services\Payroll\TeknisiMesinCommissionService` — base jasa, tarif,
  ledger, adjustment.
- `App\Models\ModelServiceTeknisi`, `ModelServiceTeknisiEvent`,
  `ModelServiceTeknisiWait`, `ModelTeknisiMesinKpiPeriod`,
  `ModelTeknisiMesinCommission`.
- `App\Controllers\TeknisiMesin.php` — halaman KPI/verifikasi.
- Perluasan `Riwayat_Service`/`Service` untuk penugasan dan form gabungan.
- View baru di `app/Views/teknisi_mesin/` dan partial di
  `app/Views/riwayat/`.
- Migrasi baru di `app/Database/Migrations/`.
- Test di `tests/unit/` dan `tests/feature/` bila ada pola feature test.

## Acceptance criteria

1. `service_by` tidak berubah arti dan tidak ditimpa oleh penugasan teknisi
   mesin.
2. Setiap service dapat punya paling banyak satu teknisi mesin aktif (internal
   atau eksternal) dan riwayat percobaan internal yang utuh.
3. Form gabungan dapat mencatat cancel, rujukan internal dengan alasan, bukti,
   dan checklist Pasal 6, serta rujukan eksternal dengan nama saja.
4. Cancel/rujuk tidak menghapus data KPI; batalkan memakai soft-cancel.
5. Rujukan internal memindahkan penugasan ke teknisi kedua dan mencatat hasil
   keduanya.
6. Rujukan eksternal hanya mencatat nama teknisi, tidak membuat percobaan
   internal, dan tidak memberi KPI rujukan/komisi internal; KPI cancel hanya
   bila SPV membuktikan kesalahan teknisi pertama.
7. Service `EXTERNAL_ONLY` dapat dibuat tanpa teknisi internal, tidak membuat
   `service_teknisi`, dan tidak masuk KPI/komisi teknisi mesin internal.
8. Event `PENDING` tidak dihitung sampai SPV approve; event `REJECTED` tidak
   dihitung.
9. Rumus absensi 40, cancel 20, rujukan 20, dan kecepatan 20 sesuai kebijakan;
   contoh 23/26, 3/30, 2/30, dan rata-rata 4 hari menghasilkan 87,05.
10. Komisi memakai jasa bersih, bukan total tagihan/sparepart.
11. Hanya service selesai, lunas, dan memiliki percobaan internal `SUCCESS`
    yang masuk komisi; komisi diatribusikan ke teknisi percobaan itu.
12. Refund/garansi setelah periode terkunci menghasilkan adjustment, bukan
    perubahan snapshot lama.
13. Komisi muncul sebagai komponen payroll terpisah dan idempoten.
14. KPI lama jabatan 36 (70/15/15), absensi 80/5/10/5, insentif 1%, dan
    `fee_teknisi` tidak berubah tanpa keputusan terpisah.
15. Ada test untuk: atribusi `service_by` vs `teknisi_mesin_id`, reassign,
    rujukan internal/eksternal, `EXTERNAL_ONLY`, cancel exempt, pending vs
    approved, paid vs unpaid, hardware wait, refund adjustment, dan
    perhitungan payroll.

## Rollout

1. Migrasi schema dan seed komponen/weight grup `teknisi_mesin`.
2. Feature flag `teknisi_mesin.enabled` default nonaktif.
3. Pilot satu unit operasional; `service_by` tetap seperti sekarang.
4. Aktifkan penugasan dan form gabungan.
5. Aktifkan perhitungan KPI draft dan verifikasi SPV.
6. Aktifkan komisi payroll setelah satu periode percobaan dan persetujuan
   manajemen.
7. Tidak ada backfill komisi untuk service sebelum tanggal berlaku. Service
   lama tanpa `teknisi_mesin_id` tidak masuk KPI/komisi.

## Keputusan yang masih perlu konfirmasi

| No | Pertanyaan | Default yang diusulkan | Dampak bila berbeda |
|---|---|---|---|
| 1 | Base komisi: jasa bersih atau total tagihan? | Jasa bersih | Total tagihan akan membayar komisi atas sparepart dan menekan margin |
| 2 | Insentif 1% `KEPALA_TOKO` untuk teknisi mesin: tetap atau diganti? | Tetap; komisi 35% terpisah | Bila diganti, payroll dan `IncentiveGroupSeeder` berubah |
| 3 | Ambang hari patuh: skor harian 5 atau minimal 4? | Minimal 4 + check-out + toleransi pulang 15 menit | Ambang 5 lebih ketat dan menurunkan nilai absensi |
| 4 | Rujukan eksternal dan `EXTERNAL_ONLY` yang tetap ditagih iClear: siapa yang menagih/membayar teknisi eksternal dan apakah ada komisi internal? | Tidak ada KPI rujukan/komisi teknisi mesin internal; KPI cancel hanya bila kesalahan teknisi pertama terbukti; alur tagihan service tidak diubah | Perlu aturan penagihan/pembayaran eksternal dan pembagian komisi khusus |
| 5 | Event `PENDING` saat cutoff payroll: tahan atau exclude? | Exclude; adjustment periode berikutnya | Menahan payroll menambah kompleksitas operasional |
| 6 | Tanggal berlaku dan unit pilot | Menunggu keputusan manajemen | Menentukan cut-off data dan komisi pertama |
| 7 | Apakah KPI 40/20/20/20 juga menggantikan tunjangan kinerja 36? | Tidak; hanya untuk komisi | Bila ya, bobot KPI 36 dan payroll berubah |
| 8 | Rujukan eksternal tidak dapat dinilai KPI rujukan karena hanya nama yang dicatat. Apakah ini diterima? | Ya; rujukan eksternal tidak masuk KPI rujukan dan `affects_referral = 0` | Teknisi dapat menghindari KPI rujukan dengan merujuk eksternal; perlu bukti/hasil minimal bila ingin ditutup |
| 9 | Apakah service `EXTERNAL_ONLY` dikecualikan dari KPI interface (`service_by`)? | Tidak; KPI interface tidak diubah pada fase ini | Bila ya, `OmsetTeknisiCalculator`/`CustomerCalculator` berubah |

## Dokumentasi yang perlu diperbarui setelah implementasi

- PRD Modul 04: mode `INTERNAL`/`EXTERNAL_ONLY`, penugasan teknisi mesin,
  cancel/rujuk, bukti internal, nama teknisi eksternal, soft-cancel.
- PRD Modul 07: komponen payroll komisi.
- PRD Modul 08: KPI teknisi mesin, pengecualian eksternal, dan verifikasi SPV.
- `docs/architecture/kpi-business-rules.md`: aturan baru dan pemisahan grup.
- `docs/architecture/kpi-engine.md`: service dan tabel baru.
- `docs/architecture/unit-scope.md` bila ada aturan scope baru.
