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

    /** Jam pulang per shift; sesi PS punya dua nilai. */
    public array $shiftPulang = [
        'PAGI'  => '17:00',
        'SIANG' => '21:00',
        'PS'    => ['PAGI' => '13:00', 'SORE' => '21:00'],
    ];

    public array $rosterSemuaUnit = [0, 1, 34];
    public array $rosterUnitSendiri = [35, 47];
    public int $koreksiLampau = 1;
}
