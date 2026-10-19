# Kolom `akun.TERIMA_GAJI` Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tambah penanda per-akun `akun.TERIMA_GAJI` sehingga "siapa yang digaji" ditentukan satu sumber, bisa diatur di `/pegawai`, dan dipakai seragam di semua perhitungan gaji.

**Architecture:** Satu kolom TINYINT (default 1) di `akun`. Migrasi mem-backfill jabatan 1 & 2 jadi 0. Semua tempat yang sekarang memakai daftar jabatan hardcode diganti query kolom ini. Tidak ada service baru, tidak ada route baru.

**Tech Stack:** PHP 7.4, CodeIgniter 4, MySQLi. Tanpa dependency baru.

**Spec:** `docs/superpowers/specs/2026-10-19-akun-terima-gaji-design.md`

## Global Constraints

- **PHP 7.4**: jangan pakai `str_contains`, `match`, `?->`, named argument, atau sintaks 8.x lain (produksi = 7.4).
- Ikuti pola file yang sudah ada; jangan restrukturisasi file besar.
- Migrasi diasumsikan berjalan di DB produksi/lokal dengan `DBPrefix = ''` (tabel tanpa prefix), sama seperti migrasi lain.
- Jangan menyentuh typo lama `$THIS->AuthModel` di `app/Controllers/Pegawai.php` (baris ~198).
- Nilai checkbox wajib dinormalisasi ke `0/1` di controller, jangan mengandalkan default DB (anti-goal: tersimpan `NULL`).
- Jangan hapus/mengubah baris `finance_payroll` yang sudah ada.

## Review Focus

Kondisi yang harus tetap benar walau tidak semua punya test otomatis — cek saat review:

1. **Akun `TERIMA_GAJI = 0` yang sudah punya baris `finance_payroll` lama** — baris lama tetap tampil di register `/payroll` (tidak dihapus). Ini sengaja; flag hanya menghentikan pembuatan baris baru. (tidak ada test)
2. **Direktur hilang dari daftar `/gaji`** — perubahan perilaku yang disetujui. (Task 4, dicek manual)
3. **Akun `TERIMA_GAJI = 0` dibuka langsung** di `/gaji` (diri sendiri) atau `/penilaian/slip_gaji/{id}` — tetap menampilkan slip. Batas lingkup yang disetujui. (tidak ada test)
4. **Checkbox tidak dicentang saat simpan** — harus tersimpan `0`, bukan `NULL`. (Task 2)
5. **`asset_berjalan` tidak memfilter `STATUS_PEGAWAI`/`deleted`** — akun nonaktif tetap ikut. Perilaku lama yang tidak diubah (Non-goal). (tidak ada test)

---

### Task 1: Migrasi `AddTerimaGajiToAkun`

**Files:**
- Create: `app/Database/Migrations/2026-10-19-000100_AddTerimaGajiToAkun.php`

**Interfaces:**
- Produces: kolom `akun.TERIMA_GAJI TINYINT(1) NOT NULL DEFAULT 1`; setelah `up()`, `ID_JABATAN IN (1,2)` bernilai 0.

- [ ] **Step 1: Tulis migrasi**

Idempoten lewat `information_schema` (pola `2026-10-11-000000_AddIsOperationalToUnit.php`). Kelas `AddTerimaGajiToAkun` di `namespace App\Database\Migrations`.

```php
public function up()
{
    if (! $this->kolomAda('TERIMA_GAJI', 'akun')) {
        $this->forge->addColumn('akun', [
            'TERIMA_GAJI' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 1,
                'after'      => 'JENIS_PEGAWAI',
            ],
        ]);
    }
    $this->db->query('UPDATE akun SET TERIMA_GAJI = 0 WHERE ID_JABATAN IN (1, 2) AND TERIMA_GAJI <> 0');
}

public function down()
{
    if ($this->kolomAda('TERIMA_GAJI', 'akun')) {
        $this->forge->dropColumn('akun', 'TERIMA_GAJI');
    }
}
```

`kolomAda(string $kolom, string $tabel): bool` = salin persis dari `AddIsOperationalToUnit`.

- [ ] **Step 2: Jalankan migrasi di lokal**

Run: `php spark migrate`
Expected: `AddTerimaGajiToAkun` muncul sebagai "Migrated" (atau no-op kalau sudah jalan).

- [ ] **Step 3: Verifikasi kolom & backfill**

Run: `mysql -uroot erp_local -e "SHOW COLUMNS FROM akun LIKE 'TERIMA_GAJI'; SELECT ID_AKUN, ID_JABATAN, TERIMA_GAJI FROM akun WHERE ID_JABATAN IN (1,2);"`
Expected: kolom `TERIMA_GAJI` ada (`tinyint(1)`, Default 1); ID_AKUN 1, 5, 46, 70 semua `TERIMA_GAJI = 0`.

- [ ] **Step 4: Verifikasi idempoten**

Run: `php spark migrate:rollback` lalu `php spark migrate`
Expected: tanpa error; hasil Step 3 sama.

- [ ] **Step 5: Commit**

```bash
git add app/Database/Migrations/2026-10-19-000100_AddTerimaGajiToAkun.php
git commit -m "feat(payroll): migrasi kolom akun.TERIMA_GAJI + backfill Admin Root/Direktur"
```

---

### Task 2: Model + UI `/pegawai`

**Files:**
- Modify: `app/Models/ModelAuth.php:12`
- Modify: `app/Controllers/Pegawai.php` (`insert()` ~127-142, `update()` ~179-192)
- Modify: `app/Views/pegawai/list_pegawai_new.php` (tombol edit ~157-174, modal edit ~260-268, modal input ~382-390, JS ~515-558)
- Create: `app/Scripts/terima_gaji_verify.php`

**Interfaces:**
- Produces: `ModelAuth` boleh menyimpan `TERIMA_GAJI`; `Pegawai::insert/update` menormalisasi checkbox ke 0/1.
- Produces: script `php app/Scripts/terima_gaji_verify.php` (exit 0 = semua PASS), yang akan diperluas di Task 3.

- [ ] **Step 1: Tulis check gagal di script verify**

Buat `app/Scripts/terima_gaji_verify.php` mengikuti bootstrap `app/Scripts/assetberjalan_debug.php` (require autoload + Paths + DotEnv). Isi awal: helper `check($nama, $kondisi)` yang mencetak `PASS`/`FAIL` dan melacak gagal; di akhir `exit($gagal === 0 ? 0 : 1)`. Lalu satu check:

```php
// B: Model menegakkan allowedFields -> update TERIMA_GAJI harus tersimpan.
$db = \Config\Database::connect();
$db->transBegin();
try {
    $id = 43;
    (new \App\Models\ModelAuth())->update($id, ['TERIMA_GAJI' => 0]);
    $val = (int) $db->table('akun')->where('ID_AKUN', $id)->get()->getRow()->TERIMA_GAJI;
    check('ModelAuth menyimpan TERIMA_GAJI=0', $val === 0);
} finally {
    $db->transRollback();
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php app/Scripts/terima_gaji_verify.php`
Expected: `FAIL ModelAuth menyimpan TERIMA_GAJI=0` (kolom belum di `allowedFields`, update diabaikan), exit 1.

- [ ] **Step 3: Tambah `TERIMA_GAJI` ke `allowedFields`**

Di `app/Models/ModelAuth.php:12`, tambahkan `'TERIMA_GAJI'` di akhir array `$allowedFields`.

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `php app/Scripts/terima_gaji_verify.php`
Expected: `PASS ModelAuth menyimpan TERIMA_GAJI=0`, exit 0.

- [ ] **Step 5: Normalisasi di controller**

Di `Pegawai::insert()` tambahkan ke array `$data`:
```php
'TERIMA_GAJI' => $this->request->getPost('terima_gaji') ? 1 : 0,
```
Di `Pegawai::update()` tambahkan baris yang sama ke array `$data` (baris ~192).

- [ ] **Step 6: Checkbox di view**

Kedua modal: tambah dropdown setelah field `jenis_pegawai` (nama `terima_gaji`, id unik):
```php
<div class="mb-3 col-md-6">
    <label for="terima_gaji" class="form-label">Terima Gaji</label>
    <select class="form-control" name="terima_gaji" id="terima_gaji">
        <option value="1">Ya</option>
        <option value="0">Tidak</option>
    </select>
</div>
```
Untuk modal **edit** pakai id `terima_gaji-update`. Untuk modal **input** pakai id `terima_gaji`. Tambahkan `data-terima_gaji="<?= $row->TERIMA_GAJI ?>"` di tombol `.edit-button` (~157-174). Di JS (~531 & ~544) tambahkan:
```js
const terima_gaji = button.data('terima_gaji');
$('#terima_gaji-update').val(terima_gaji);
```

- [ ] **Step 7: Smoke halaman**

Run: buka `/pegawai`, klik edit akun "Admin Root" → dropdown "Terima Gaji" harus menampilkan "Tidak"; ubah jadi "Ya", submit, buka lagi → "Ya". Lalu kembalikan ke "Tidak".
Expected: nilai bertahan setelah simpan.

- [ ] **Step 8: Commit**

```bash
git add app/Models/ModelAuth.php app/Controllers/Pegawai.php app/Views/pegawai/list_pegawai_new.php app/Scripts/terima_gaji_verify.php
git commit -m "feat(payroll): kelola TERIMA_GAJI per akun di /pegawai"
```

---

### Task 3: Enforcement di `asset_berjalan` (loop Total Gaji)

**Files:**
- Modify: `app/Controllers/TutupKasir.php:805-808`
- Modify: `app/Services/Payroll/UnitSalaryCalculationService.php:78-81`
- Modify: `app/Scripts/terima_gaji_verify.php`

**Interfaces:**
- Consumes: kolom `TERIMA_GAJI` (Task 1).
- Produces: `TutupKasir::assetberjalan()` & `UnitSalaryCalculationService::calculateForUnit()` tidak menghitung akun `TERIMA_GAJI = 0`.

- [ ] **Step 1: Tulis check gagal**

Tambahkan ke `app/Scripts/terima_gaji_verify.php`:

```php
// C: akun TERIMA_GAJI=0 tidak ikut di totalGajiUnit; TERIMA_GAJI=1 ikut.
$db = \Config\Database::connect();
$db->transBegin();
try {
    foreach ([9901 => 0, 9902 => 1] as $idAkun => $flag) {
        $db->table('akun')->insert([
            'ID_AKUN' => $idAkun, 'ID_JABATAN' => 36, 'ID_UNIT' => 2, 'NOID' => "TG$idAkun",
            'PASSWORD' => 'x', 'NAMA_AKUN' => "TEMP TG $idAkun", 'JENIS_KELAMIN' => 'Laki-Laki',
            'HP' => '0', 'STATUS_PEGAWAI' => 1, 'TERIMA_GAJI' => $flag,
        ]);
    }
    $svc = new \App\Services\Payroll\UnitSalaryCalculationService();
    $hasil = $svc->calculateForUnit(1, 2, date('m'), date('Y'));
    $idAda = array_keys($hasil['employeeData']);
    check('akun TERIMA_GAJI=0 tidak ikut Total Gaji', ! in_array(9901, $idAda, true));
    check('akun TERIMA_GAJI=1 tetap ikut Total Gaji', in_array(9902, $idAda, true));
} finally {
    $db->transRollback();
}
```

Catat: `calculateForUnit` menerima `$bulan`/`$tahun` sebagai string `'mm'`/`'yyyy'`; `date('m')`/`date('Y')` sudah cocok.

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php app/Scripts/terima_gaji_verify.php`
Expected: `FAIL akun TERIMA_GAJI=0 tidak ikut Total Gaji` (saat ini loop tidak memfilter), exit 1.

- [ ] **Step 3: Filter di service**

Di `app/Services/Payroll/UnitSalaryCalculationService.php:78-81`, tambahkan filter:
```php
$karyawanUnit = $this->db->table('akun')
    ->where('ID_UNIT', $unit)
    ->where('TERIMA_GAJI', 1)
    ->get()->getResultArray();
```

- [ ] **Step 4: Filter di controller**

Di `app/Controllers/TutupKasir.php:805-808`, tambahkan `->where('TERIMA_GAJI', 1)` ke query `$karyawanUnit`.

- [ ] **Step 5: Jalankan, pastikan lulus**

Run: `php app/Scripts/terima_gaji_verify.php`
Expected: kedua check PASS, exit 0.

- [ ] **Step 6: Konfirmasi angka unit 1 turun**

Run: `php app/Scripts/assetberjalan_debug.php 1 10 2026`
Expected: `employees` tidak lagi memuat `id=1` / `id=5`; `totalGaji` turun ~Rp 3.350.000 dari 7.977.742.

- [ ] **Step 7: Commit**

```bash
git add app/Controllers/TutupKasir.php app/Services/Payroll/UnitSalaryCalculationService.php app/Scripts/terima_gaji_verify.php
git commit -m "fix(payroll): asset_berjalan kecualikan akun TERIMA_GAJI=0 dari Total Gaji"
```

---

### Task 4: Enforcement di daftar karyawan `/gaji` & `/penilaian_kinerja`

**Files:**
- Modify: `app/Controllers/PenilaianKPI.php:2262-2266` (`gaji()`)
- Modify: `app/Controllers/PenilaianKPI.php:1533-1536` (`penilaian_kinerja()`)

**Interfaces:**
- Consumes: kolom `TERIMA_GAJI`.
- Produces: kedua daftar hanya memuat akun `TERIMA_GAJI = 1`.

- [ ] **Step 1: Ganti filter di `gaji()`**

Hapus `->where('ID_JABATAN !=', 1)` (baris 2264), ganti jadi `->where('TERIMA_GAJI', 1)`. Sisa (`STATUS_PEGAWAI`, urutan) tidak berubah.

- [ ] **Step 2: Ganti filter di `penilaian_kinerja()`**

Ganti kedua baris `->where('ID_JABATAN !=', 1)` dan `->where('ID_JABATAN !=', 2)` (1535-1536) jadi satu `->where('TERIMA_GAJI', 1)`. Filter `$areaUnits` untuk Direktur tetap.

- [ ] **Step 3: Pastikan tidak ada filter jabatan tersisa di dua method itu**

Run: `grep -n "ID_JABATAN !=" app/Controllers/PenilaianKPI.php`
Expected: tidak ada hasil.

- [ ] **Step 4: Smoke halaman**

Run: buka `/gaji` dan `/penilaian_kinerja`.
Expected: "Admin Root" dan "Direktur" tidak muncul di daftar karyawan; karyawan lain tetap muncul dan angkanya tidak berubah.

- [ ] **Step 5: Commit**

```bash
git add app/Controllers/PenilaianKPI.php
git commit -m "feat(payroll): /gaji & /penilaian_kinerja pakai TERIMA_GAJI, bukan filter jabatan"
```

---

### Task 5: Enforcement di generate `/payroll`

**Files:**
- Modify: `app/Services/Payroll/PayrollGenerator.php` (hapus `JABATAN_TANPA_GAJI` ~59-70, blok skip ~133-147, `daftarKaryawan()` ~329-335, docblock ~40-41)
- Modify: `app/Controllers/Payroll.php:377-382` (teks pesan)
- Modify: `app/Scripts/terima_gaji_verify.php`

**Interfaces:**
- Consumes: kolom `TERIMA_GAJI`.
- Produces: `daftarKaryawan()` ikut menyeleksi `TERIMA_GAJI`; karyawan `TERIMA_GAJI = 0` dilaporkan `status => 'excluded'` dengan alasan "tidak menerima gaji".

- [ ] **Step 1: Tulis check gagal**

Tambahkan ke script (memakai `generate()` di dalam transaksi, satu unit):

```php
// E: PayrollGenerator melewati akun TERIMA_GAJI=0 (bukan hanya jabatan) & melaporkannya.
$db = \Config\Database::connect();
$db->transBegin();
try {
    // Akun temp jabatan 36 (BUKAN jabatan 1/2) supaya check ini benar-benar
    // gagal sebelum sumbernya dipindah dari jabatan ke kolom.
    $db->table('akun')->insert([
        'ID_AKUN' => 9903, 'ID_JABATAN' => 36, 'ID_UNIT' => 1, 'NOID' => 'TG9903',
        'PASSWORD' => 'x', 'NAMA_AKUN' => 'TEMP TG 9903', 'JENIS_KELAMIN' => 'Laki-Laki',
        'HP' => '0', 'STATUS_PEGAWAI' => 1, 'TERIMA_GAJI' => 0,
    ]);
    $hasil = (new \App\Services\Payroll\PayrollGenerator())->generate(date('Y-m'), ['unit_ids' => [1]]);
    $excluded = array_filter($hasil['rows'], fn($r) => $r['status'] === 'excluded');
    $nama = array_column($excluded, 'nama');
    check('akun TERIMA_GAJI=0 (jabatan biasa) dilaporkan excluded', in_array('TEMP TG 9903', $nama, true));
} finally {
    $db->transRollback();
}
```

Catat: `generate()` menulis baris `finance_payroll`; transaksi + rollback membatalkannya. Kalau `session('ID_AKUN')` kosong di CLI, `created_by` jadi 0 dan tidak masalah.

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php app/Scripts/terima_gaji_verify.php`
Expected: `FAIL akun TERIMA_GAJI=0 (jabatan biasa) dilaporkan excluded` (konstanta lama hanya mengenal jabatan 1 & 2), exit 1.

- [ ] **Step 3: Pindahkan sumber dari jabatan ke kolom**

- Di `daftarKaryawan()`, tambah `TERIMA_GAJI` ke `select()`.
- Ganti cek di baris ~133-147: hapus `self::JABATAN_TANPA_GAJI`, ganti jadi `if ((int) ($karyawan['TERIMA_GAJI'] ?? 1) === 0)`, dan alasannya `'tidak menerima gaji'`.
- Hapus konstanta `JABATAN_TANPA_GAJI`.
- Perbarui docblock (~40-41): sebut `TERIMA_GAJI` sebagai sumber.

- [ ] **Step 4: Perbarui teks pesan Finance**

Di `app/Controllers/Payroll.php:378`, ganti `'%d tidak digaji karena jabatannya (%s%s)'` jadi `'%d tidak digaji karena tidak menerima gaji (%s%s)'`.

- [ ] **Step 5: Jalankan, pastikan lulus**

Run: `php app/Scripts/terima_gaji_verify.php`
Expected: semua PASS, exit 0.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Payroll/PayrollGenerator.php app/Controllers/Payroll.php app/Scripts/terima_gaji_verify.php
git commit -m "feat(payroll): generate payroll pakai TERIMA_GAJI, bukan jabatan"
```

---

### Task 6: Enforcement di Distribusi Gaji HO

**Files:**
- Modify: `app/Services/Payroll/HoSalaryDistributionService.php:75-84`

**Interfaces:**
- Consumes: kolom `TERIMA_GAJI`.
- Produces: `calculateDistribution()->employees` tidak memuat akun HO `TERIMA_GAJI = 0`.

- [ ] **Step 1: Tambah filter**

Di query `$hoEmployees` (baris 75-84), tambah `->where('TERIMA_GAJI', 1)` (biarkan `ID_UNIT = 50`, `STATUS_PEGAWAI`, `deleted` apa adanya).

- [ ] **Step 2: Smoke di `/asset_berjalan`**

Run: buka `/asset_berjalan?unit=1`, lihat kartu "Distribusi Gaji HO".
Expected: halaman render tanpa error; daftar HO tidak memuat akun `TERIMA_GAJI = 0` (kalau ada).

- [ ] **Step 3: Commit**

```bash
git add app/Services/Payroll/HoSalaryDistributionService.php
git commit -m "feat(payroll): distribusi gaji HO hormati TERIMA_GAJI"
```

---

### Task 7: Verifikasi akhir

**Files:**
- Modify: (tidak ada — hanya menjalankan)

- [ ] **Step 1: Jalankan seluruh self-check**

Run: `php app/Scripts/terima_gaji_verify.php`
Expected: semua `PASS`, exit 0.

- [ ] **Step 2: Pastikan tidak ada sisa filter jabatan untuk gaji**

Run: `grep -rn "JABATAN_TANPA_GAJI" app/`
Expected: tidak ada hasil.
Run: `grep -rn "ID_JABATAN !=" app/Controllers app/Services`
Expected: tidak ada sisanya yang berkaitan dengan penentuan gaji.

- [ ] **Step 3: Smoke lintas halaman**

Run: buka `/asset_berjalan?unit=1`, `/gaji`, `/penilaian_kinerja`, `/payroll`, `/pegawai`.
Expected: semua render tanpa error PHP; Admin Root & Direktur absen dari daftar/total gaji; `/pegawai` bisa toggle "Terima Gaji".

- [ ] **Step 4: Update status di dokumen desain bila ada panggilan yang berubah**

Bila ada keputusan yang bergeser saat implementasi, perbarui `docs/superpowers/specs/2026-10-19-akun-terima-gaji-design.md`.

- [ ] **Step 5: Commit (bila ada perubahan)**

```bash
git add -A
git commit -m "docs(payroll): catat hasil implementasi TERIMA_GAJI"
```

---

## Self-Review

- **Spec coverage:** kolom (T1), model (T2), UI (T2), asset_berjalan 2 tempat (T3), `/gaji`+`/penilaian_kinerja` (T4), PayrollGenerator+teks Finance (T5), HO (T6), test (T2/T3/T5 + T7). Semua 6 titik enforcement spec tercakup.
- **Type/penamaan:** `TERIMA_GAJI` konsisten di semua task; `dikecualikan`/`status => 'excluded'` mengikuti kontrak `PayrollGenerator` yang ada.
- **Review Focus:** #4 punya test (T2 check B); #1, #2, #3, #5 didokumentasikan sebagai batas/risk di Review Focus dan dicek manual di T7.
- **Proportion:** plan ~sepanjang spec; mayoritas step satu aksi dengan hasil terperiksa.
