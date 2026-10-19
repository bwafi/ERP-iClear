<?php

namespace App\Libraries;

use CodeIgniter\Events\Events;

/**
 * Versi data untuk cache baca per-request.
 *
 * Cache baca hanya sah selama tidak ada query tulis ke data sumber di request
 * yang sama. Setiap query tulis menaikkan versi, sehingga kunci cache lama
 * otomatis tidak terpakai lagi. Ini yang membuat pola "simpan lalu hitung ulang
 * dalam satu request" (dipakai beberapa script uji dan alur simpan/verifikasi)
 * tetap benar, tanpa perlu membersihkan cache satu per satu.
 *
 * Cara pakai: bungkus kunci cache dengan kunci().
 *   $key = ReadCache::kunci('hpp', $unitId, $dari, $sampai);
 */
class ReadCache
{
    /**
     * Tabel HASIL hitungan sendiri, ditulis di tengah perhitungan KPI.
     * Menulis ke tabel ini tidak boleh membatalkan cache baca data sumber —
     * kalau tidak, cache yang baru diisi akan langsung batal tiap kali
     * perhitungan menyimpan hasilnya.
     */
    private const TABEL_HASIL = ['finance_kpi_records'];

    private static $versi = 1;

    /** Kunci cache yang ikut berubah begitu ada query tulis ke data sumber. */
    public static function kunci(...$bagian): string
    {
        self::pantauTulis();

        return self::$versi . '|' . implode('|', $bagian);
    }

    /** Listener DBQuery: query tulis ke data sumber membatalkan cache baca. */
    public static function tandaiTulis($event = null): void
    {
        if (! is_object($event) || ! method_exists($event, 'isWriteType')) {
            self::$versi++;

            return;
        }

        if (! $event->isWriteType()) {
            return;
        }

        $tabel = self::tabelTulisan((string) $event->getQuery());
        foreach (self::TABEL_HASIL as $hasil) {
            if ($tabel !== '' && substr($tabel, -strlen($hasil)) === $hasil) {
                return;
            }
        }

        self::$versi++;
    }

    /** Nama tabel tujuan sebuah query tulis (tanpa prefix), '' kalau tak terbaca. */
    private static function tabelTulisan(string $sql): string
    {
        if (preg_match('/^\s*(?:INSERT\s+INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM)\s+[`"]?([A-Za-z0-9_]+)/i', $sql, $m)) {
            return strtolower($m[1]);
        }

        return '';
    }

    /**
     * Dipasang sekali pada pemakaian pertama. Dibuat malas (bukan di
     * Config\Events) supaya tetap aktif di script CLI yang tidak memuat
     * konfigurasi event aplikasi.
     */
    private static function pantauTulis(): void
    {
        $pemantau = [self::class, 'tandaiTulis'];
        if (in_array($pemantau, Events::listeners('DBQuery'), true)) {
            return;
        }

        Events::on('DBQuery', $pemantau);
    }
}
