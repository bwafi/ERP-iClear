<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Presensi extends BaseConfig
{
    public string $goLiveDate = '2026-11-01';
    public string $zonaWaktu = 'Asia/Jakarta';
    public string $fotoDir = WRITEPATH . 'uploads/presensi';
    public int $maxFotoBytes = 2097152;
    public string $suratDir = WRITEPATH . 'uploads/izin';
    public int $maxSuratBytes = 2097152;
    public int $toleransiMenit = 14;

    /** In break PS SORE dikunci sampai jam ini; jadwal SORE tetap 16:55. */
    public string $inBreakMulai = '15:00';

    /** Jam pulang per shift; sesi PS punya dua nilai. */
    public array $shiftPulang = [
        'PAGI'  => '17:00',
        'SIANG' => '21:00',
        'FULL'  => '21:00',
        'PS'    => ['PAGI' => '13:00', 'SORE' => '21:00'],
    ];

    public array $rosterSemuaUnit = [0, 1, 34];
    public array $rosterUnitSendiri = [35, 47];
    public int $koreksiLampau = 1;

    /** SPV hanya melihat foto/surat pegawai di unit binaan `spv_units`. */
    public int $jabatanSpv = 40;

    /** Riwayat absen: role yang boleh melihat dan memfilter semua unit. */
    public array $riwayatSemuaUnit = [0, 1, 2, 34];
}
