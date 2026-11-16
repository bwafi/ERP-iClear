<?php

namespace App\Libraries;

/**
 * Helper manual book per menu.
 *
 * Kolom `menu.manualbook` boleh berisi satu nama file atau beberapa nama file
 * yang dipisah `|`. Pemisah ini dipakai agar panduan baru bisa ditambahkan
 * tanpa menghapus manual lama pada halaman yang sama.
 */
class ManualBook
{
    /** Pemisah antar nama file di kolom `menu.manualbook`. */
    public const PEMISAH = '|';

    /**
     * Ubah isi kolom `menu.manualbook` menjadi daftar nama file yang bersih.
     *
     * - `null`/string kosong menghasilkan daftar kosong.
     * - Pemisah boleh `|` atau baris baru (untuk data lama yang tidak konsisten).
     * - Spasi di tepi dibuang, duplikat dihapus, dan path diambil nama filenya
     *   saja agar tidak bisa keluar dari folder `public/manualbook`.
     *
     * @return list<string>
     */
    public static function daftarBerkas(?string $nilai): array
    {
        if ($nilai === null) {
            return [];
        }

        $bagian = preg_split('/[|' . "\r\n" . ']+/', $nilai);
        if ($bagian === false) {
            return [];
        }

        $hasil = [];
        foreach ($bagian as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }

            $item = basename(str_replace('\\', '/', $item));
            if ($item === '' || $item === '.' || $item === '..') {
                continue;
            }

            if (! in_array($item, $hasil, true)) {
                $hasil[] = $item;
            }
        }

        return $hasil;
    }

    /** Judul yang ditampilkan di pemilih manual book (nama file tanpa `.pdf`). */
    public static function judul(string $berkas): string
    {
        return pathinfo($berkas, PATHINFO_FILENAME);
    }
}
