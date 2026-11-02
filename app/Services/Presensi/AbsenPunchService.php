<?php

namespace App\Services\Presensi;

use App\Models\ModelPresensi;
use App\Models\ModelPresensiRoster;
use App\Models\ModelUnit;
use App\Services\Kpi\AttendanceScoreCalculator;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Punch masuk/pulang absen mobile. Roster adalah sumber jadwal; `presensi`
 * tetap kompatibel dengan pembaca lama (KPI/riwayat) lewat kolom yang sama.
 */
class AbsenPunchService
{
    private const SHIFT_ABSEN = ['PAGI', 'SIANG', 'PS'];

    protected $presensi;
    protected $roster;
    protected $foto;
    protected $unit;

    public function __construct(
        ?ModelPresensi $presensi = null,
        ?ModelPresensiRoster $roster = null,
        ?FotoPresensiService $foto = null,
        ?ModelUnit $unit = null
    ) {
        $this->presensi = $presensi ?? new ModelPresensi();
        $this->roster   = $roster ?? new ModelPresensiRoster();
        $this->foto     = $foto ?? new FotoPresensiService();
        $this->unit     = $unit ?? new ModelUnit();
    }

    public function validasiLokasi(int $unitId, float $lat, float $long): int
    {
        if (abs($lat) > 90 || abs($long) > 180) {
            throw new PresensiException('Lokasi tidak valid.');
        }

        $unit = $this->unit->getById($unitId);
        if (! $unit || ! is_numeric($unit->LATITUDE) || ! is_numeric($unit->LONGTITUDE) || (int) $unit->RADIUS <= 0) {
            throw new PresensiException('Titik dan radius absen unit belum diatur.');
        }

        $jarak = self::jarakMeter($lat, $long, (float) $unit->LATITUDE, (float) $unit->LONGTITUDE);
        if ($jarak > (int) $unit->RADIUS) {
            throw new PresensiException(
                'Lokasi Anda ' . number_format($jarak, 0, ',', '.')
                . ' m dari titik absen (maks ' . (int) $unit->RADIUS . ' m).',
                422,
                'jarak'
            );
        }

        return (int) round($jarak);
    }

    public function masuk(
        int $akunId,
        int $unitId,
        string $sesi,
        float $lat,
        float $long,
        int $jarak,
        UploadedFile $foto,
        string $waktu
    ): int {
        $tanggal = substr($waktu, 0, 10);
        $roster  = $this->roster->getByAkunTanggal($akunId, $tanggal);
        if (! $roster) {
            throw new PresensiException('Belum ada jadwal untuk hari ini. Hubungi admin cabang.');
        }

        $shift = strtoupper((string) $roster->shift);
        if (! in_array($shift, self::SHIFT_ABSEN, true)) {
            throw new PresensiException('Hari ini ' . $shift . ', tidak perlu absen.');
        }

        $sesi = strtoupper($sesi);
        if ($shift === 'PS') {
            if (! in_array($sesi, ['PAGI', 'SORE'], true)) {
                throw new PresensiException('Sesi shift PS tidak valid.');
            }
            if ($sesi === 'SORE' && ! $this->presensi->getByAkunTanggalSesi($akunId, $tanggal, 'PAGI')) {
                throw new PresensiException('Absen sore bisa dilakukan setelah absen pagi.');
            }
        } elseif ($sesi !== 'FULL') {
            throw new PresensiException('Sesi shift tidak valid.');
        }

        if ($this->presensi->getByAkunTanggalSesi($akunId, $tanggal, $sesi)) {
            throw new PresensiException('Anda sudah absen ' . strtolower($sesi) . ' hari ini.');
        }

        $jadwalMasuk = AttendanceScoreCalculator::getScheduledTime($shift, $sesi);
        $jadwalPulang = config('Presensi')->shiftPulang[$shift];
        if (is_array($jadwalPulang)) {
            $jadwalPulang = $jadwalPulang[$sesi];
        }
        $toleransi = date('H:i:s', strtotime($jadwalMasuk) + config('Presensi')->toleransiMenit * 60);

        $fotoPath = $this->foto->simpan($foto, 'masuk', $akunId);

        $data = [
            'waktu_masuk'       => $waktu,
            'jam_jadwal_masuk'  => $jadwalMasuk,
            'jam_jadwal_pulang' => $jadwalPulang,
            'jam_toleransi'     => $toleransi,
            // Absen mobile langsung final: approval hanya untuk izin/sakit/cuti.
            'status_absensi'    => 1,
            'lat'               => (string) $lat,
            'long'              => (string) $long,
            'ip'                => null,
            'foto'              => $fotoPath,
            'idjadwal_masuk'    => null,
            'akun_idakun'       => $akunId,
            'unit_idunit'       => $unitId,
            'created_at'        => $waktu,
            'jarak'             => $jarak,
            'status_kehadiran'  => self::statusKehadiran($waktu, $jadwalMasuk, $toleransi),
            'sesi'              => $sesi,
            'sumber'            => 'mobile',
        ];

        try {
            $id = $this->presensi->insert($data);
        } catch (\Throwable $e) {
            $this->foto->hapus($fotoPath);
            throw new PresensiException('Absen gagal disimpan.', 500);
        }

        if ($id === false) {
            $this->foto->hapus($fotoPath);
            throw new PresensiException('Absen gagal disimpan.', 500);
        }

        return (int) $id;
    }

    public function pulang(int $akunId, int $idPresensi, float $lat, float $long, UploadedFile $foto, string $waktu): void
    {
        $row = $this->presensi->find($idPresensi);
        if (! $row || (int) $row->akun_idakun !== $akunId) {
            throw new PresensiException('Data absen tidak ditemukan.', 403);
        }

        if (! empty($row->waktu_pulang)) {
            throw new PresensiException('Anda sudah absen pulang.');
        }

        if (substr((string) $row->waktu_masuk, 0, 10) !== substr($waktu, 0, 10)) {
            throw new PresensiException('Absen pulang hanya untuk hari ini.');
        }

        $fotoPath = $this->foto->simpan($foto, 'pulang', $akunId);

        try {
            $ok = $this->presensi->update($idPresensi, [
                'waktu_pulang' => $waktu,
                'foto_pulang'  => $fotoPath,
                'updated_at'   => $waktu,
            ]);
        } catch (\Throwable $e) {
            $this->foto->hapus($fotoPath);
            throw new PresensiException('Absen pulang gagal disimpan.', 500);
        }

        if (! $ok) {
            $this->foto->hapus($fotoPath);
            throw new PresensiException('Absen pulang gagal disimpan.', 500);
        }
    }

    public static function statusKehadiran(string $waktu, string $jadwalMasuk, string $jamToleransi): int
    {
        $selisih = strtotime($waktu) - strtotime(substr($waktu, 0, 10) . ' ' . $jadwalMasuk);
        if ($selisih <= 59) {
            return 0;
        }

        return strtotime($waktu) <= strtotime(substr($waktu, 0, 10) . ' ' . $jamToleransi) ? 1 : 2;
    }

    public static function jarakMeter(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r    = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
