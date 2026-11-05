<?php

namespace App\Services\Presensi;

use App\Models\ModelPresensiRoster;

class RosterService
{
    private const SHIFT_POLA  = ['PAGI', 'SIANG', 'PS', 'OFF'];
    public const SHIFT_SEMUA = ['PAGI', 'SIANG', 'PS', 'OFF', 'IZIN', 'SAKIT', 'CUTI'];

    protected $model;

    public function __construct(?ModelPresensiRoster $model = null)
    {
        $this->model = $model ?? new ModelPresensiRoster();
    }

    /**
     * Isi tanggal yang belum punya baris dengan pola 7 hari (1=Senin..7=Minggu).
     * Baris yang sudah ada tidak ditimpa.
     */
    public function terapkanBulan(int $akunId, string $bulan, array $pola, int $oleh): int
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            throw new \InvalidArgumentException('Bulan roster tidak valid.');
        }

        for ($hari = 1; $hari <= 7; $hari++) {
            if (! isset($pola[$hari]) || ! in_array($pola[$hari], self::SHIFT_POLA, true)) {
                throw new \InvalidArgumentException('Pola roster tidak valid.');
            }
        }

        $jumlahHari = (int) date('t', strtotime($bulan . '-01'));
        $db         = db_connect();
        $db->transStart();

        $jumlah = 0;
        for ($tanggal = 1; $tanggal <= $jumlahHari; $tanggal++) {
            $iso = sprintf('%s-%02d', $bulan, $tanggal);
            if ($this->model->ada($akunId, $iso)) {
                continue;
            }

            $this->model->simpan(
                $akunId,
                $iso,
                $pola[(int) date('N', strtotime($iso))],
                null,
                $oleh
            );
            $jumlah++;
        }

        $db->transComplete();

        return $jumlah;
    }

    public function simpanTanggal(int $akunId, string $tanggal, string $shift, ?string $keterangan, int $oleh): bool
    {
        if (! in_array($shift, self::SHIFT_SEMUA, true)) {
            throw new \InvalidArgumentException('Shift roster tidak valid.');
        }

        return $this->model->simpan($akunId, $tanggal, $shift, $keterangan, $oleh);
    }
}
