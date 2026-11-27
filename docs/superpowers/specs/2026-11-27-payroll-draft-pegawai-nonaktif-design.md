# Bersihkan Draft Payroll Pegawai Nonaktif/Resign — Desain (Opsi B)

**Tanggal:** 2026-11-27
**Status:** disetujui 2026-11-27 (via chat, Opsi B)
**Terkait:** `docs/prd/modul/PRD_MODUL_07_HRD_Payroll.md` (butir 4.4, 5.16),
`AGENTS.md` bagian C dan D.

## Intent

Pegawai yang sudah resign tidak boleh lagi muncul di sistem gaji: tidak
mendapat gaji pokok, tunjangan, insentif, atau draft payroll baru. Riwayat
KPI dan payroll yang sudah dibayar tetap disimpan karena masih dipakai
laporan historis.

Kasus pemicu: akun `Indah Dewi Puspitasari` (`ID_AKUN = 54`) sudah berstatus
`STATUS_PEGAWAI = 0` dan `JENIS_PEGAWAI = 100` (Resign), tetapi masih punya
dua draft `finance_payroll` berstatus `rencana` untuk 30 November 2026 dan
namanya masih muncul di dropdown input payroll manual.

## Keputusan yang disepakati

| No | Keputusan | Catatan |
|---|---|---|
| 1 | Akun **tidak dihapus permanen** | 319 `penilaian`, 315 `kpi_evaluations`, 8 `kpi_attendance_off`, dan 1 payroll `dibayar` menunjuk ID 54 |
| 2 | Generate payroll mengabaikan pegawai nonaktif | Sudah berlaku (`STATUS_PEGAWAI = 1`) |
| 3 | Draft `rencana` milik pegawai nonaktif dibersihkan saat overwrite | Baris `dibayar` tidak pernah disentuh |
| 4 | Ada aksi eksplisit **Batalkan draft** per baris | Hanya status `rencana`; guard `canInput()`, scope unit, dan CSRF |
| 5 | Ada command production `payroll:draft-nonaktif` | Dry-run default; `--apply` untuk menghapus |
| 6 | Dropdown karyawan di input payroll manual hanya pegawai aktif | Dropdown penerima bonus/lembur tidak diubah |
| 7 | Tidak ada perubahan schema | Cukup query dan route baru |

## Perilaku

### Generate register (`PayrollGenerator::generate`)

Saat `overwrite = true`, sebelum/sesudah menyusun baris aktif, generator
menghapus `finance_payroll` berstatus `rencana` pada rentang periode yang
disusun untuk pegawai yang tidak lagi aktif:

```
NOT (akun.STATUS_PEGAWAI = 1 AND (akun.deleted IS NULL OR akun.deleted = ''))
```

Filter `unit_ids` tetap dihormati. Baris `dibayar` tidak pernah dihapus.
Jumlah yang dihapus masuk ke `hasil['dihapus']` dan pesan hasil.

Konsekuensi yang disadari: pegawai yang resign di tengah bulan dan masih
berhak gaji terakhir harus dibayar sebelum statusnya dinonaktifkan, atau
ditambahkan manual setelah generate terakhir. Ini sama dengan perlakuan
`TERIMA_GAJI = 0` yang sudah ada.

### Batalkan draft (`POST payroll/batalkan-draft`)

- Filter route: `['auth', 'csrf']`.
- Guard `FinanceScopeService::canInput()`.
- Hanya baris `status = 'rencana'`; baris `dibayar` ditolak.
- Unit baris harus ada di `resolveAllowedUnits()`.
- Sukses/gagal lewat flash message; tombol ada di kolom Aksi baris `rencana`.

### Command production

```
php spark payroll:draft-nonaktif
php spark payroll:draft-nonaktif --pegawai=54 --bulan=2026-11
php spark payroll:draft-nonaktif --pegawai=54 --bulan=2026-11 --apply
```

- Default dry-run: hanya menampilkan id, nama, unit, jatuh tempo, nominal.
- `--apply` menghapus seluruh baris yang tampil dalam satu transaksi.
- Tidak menyentuh baris `dibayar` dan tidak menghapus akun.

### Dropdown input payroll manual

`Payroll::index` mengirim `$akunAktif` (STATUS_PEGAWAI = 1, deleted kosong)
khusus untuk modal **Input Payroll Gaji Manual**. `$akun` lama tetap dipakai
dropdown penerima Bonus/Lembur agar pembayaran terakhir yang sah tetap bisa
dicatat.

## Non-goals

- Hard delete akun atau data KPI.
- Alur resign penuh (tanggal resign, halaman arsip, status khusus).
- Mengubah data `penilaian`/`kpi_evaluations` historis.
- Memblokir server-side input manual untuk pegawai nonaktif (hanya UI picker).

## Bukti yang diharapkan

- `tests/PayrollGeneratorUnitTest.php` — draft `rencana` pegawai nonaktif
  dihapus, baris `dibayar` tetap.
- `tests/unit/PayrollDraftServiceTest.php` — filter draft nonaktif dan
  penolakan baris `dibayar`.
- `php spark payroll:draft-nonaktif --pegawai=54 --bulan=2026-11` (dry-run)
  menampilkan dua draft Indah; `--apply` menghapusnya.
- PRD Modul 07 diperbarui (butir 4.4 dan business rule baru).
