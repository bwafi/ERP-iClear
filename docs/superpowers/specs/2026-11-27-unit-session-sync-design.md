# Sinkronisasi Unit Sesi dengan Master Data

**Tanggal:** 27 November 2026
**Issue:** #252
**Status:** disetujui untuk dikerjakan (user: "sinkronisasi unit sekarang kerjakan")

## Masalah

`session('ID_UNIT')` hanya ditulis saat login (`app/Controllers/Auth.php:86-101`).
Setelah itu sesi menyimpan salinan beku dari `akun.ID_UNIT`. Mengubah unit akun di
master data tidak mengubah sesi yang sedang berjalan, dan refresh tetap memakai unit
lama. Semua modul transaksi membaca `session('ID_UNIT')` (service, penjualan, kas,
tutup kasir), sehingga transaksi bisa tercatat di unit yang sudah tidak sesuai.

`AuthFilter` hanya memeriksa `logged_in`. `BaseController` membaca ulang baris akun
tiap request tetapi hanya untuk tampilan (`baseController.php:109-110`).

## Keputusan

1. **Sinkron pada GET non-AJAX saja.** POST/PUT/DELETE tidak menyinkronkan karena
   form yang sedang disubmit harus tetap memakai unit saat form dirender. AJAX GET
   dikecualikan karena lookup latar belakang tidak boleh memindahkan unit di tengah
   pengisian form.
2. **Tempat:** `AuthFilter::before`, setelah cek `logged_in`, lewat service baru
   `App\Services\Auth\SessionUnitSyncService` yang bisa diuji tanpa HTTP.
3. **Yang diubah saat unit berbeda:** `ID_UNIT`, `NAMA_UNIT`, hapus `idservice`,
   flashdata `sukses` berisi notifikasi. Tidak ada perubahan schema/route.
4. **Unit 0 tetap nilai valid** dan disinkronkan apa adanya (tidak dipaksa ke unit
   lain). `NAMA_UNIT` fallback `Unit <id>` bila baris `unit` tidak ada.
5. **Akun tidak ditemukan / `ID_AKUN` kosong / `ID_UNIT` akun NULL:** sesi dibiarkan.
6. **DB error saat membaca `akun`:** sinkron dilewati dan dicatat di log. Fitur ini
   best-effort; kegagalan sinkron tidak boleh menjatuhkan request. Ini juga menjaga
   suite test yang tidak membuat tabel `akun`.
7. **Tidak ada logout paksa** untuk akun `deleted`/nonaktif. Itu perubahan
   otorisasi terpisah di luar scope; dicatat sebagai follow-up.

## Alur

```
GET non-AJAX + logged_in
        |
        v
baca akun.ID_UNIT (PK ID_AKUN)
        |
   unit sama? --ya--> selesai (tidak ada perubahan)
        |
       tidak
        |
        v
baca unit.NAMA_UNIT (fallback "Unit <id>")
set ID_UNIT, NAMA_UNIT
hapus idservice
set flashdata sukses
```

Pesan: `Unit Anda berubah dari <lama> ke <baru>. Transaksi baru memakai unit <baru>.`
Bila tiket service sedang aktif: tambahkan `Tiket service yang sedang dikerjakan
direset; buka dari halaman Proses Service untuk melanjutkan.`

## Edge case

| Kasus | Perilaku |
|---|---|
| `akun.ID_UNIT` berupa JSON array (warisan login) | Ambil elemen pertama, sama seperti `Auth.php:86-92`. |
| `akun.ID_UNIT` NULL | Tidak menyinkronkan. |
| `akun` tidak ditemukan | Tidak menyinkronkan. |
| `unit` tidak ditemukan | `NAMA_UNIT = 'Unit <id>'`. |
| Tabel `akun` tidak ada (lingkungan test) | Dilewati + log warning. |
| Unit berubah saat tiket service status 1/2 | `idservice` dihapus; tiket tetap di DB dan bisa dibuka dari Proses Service. |
| Unit berubah di tengah form penjualan | POST tidak menyinkronkan; penjualan tetap masuk unit saat form dirender. |

## Test

- Unit: `tests/unit/SessionUnitSyncServiceTest.php` — GET/POST/AJAX, unit sama,
  akun tidak ada, ID_AKUN kosong, nama fallback, unit 0, tabel `akun` tidak ada.
- Feature: `tests/ServiceIntakeSessionTest.php` — GET menyinkronkan unit + mereset
  tiket + flashdata; POST dan AJAX GET tidak menyinkronkan.

## Di luar scope

- Logout paksa untuk akun nonaktif/terhapus.
- Sinkronisasi pada request POST/AJAX.
- Perubahan schema database.
