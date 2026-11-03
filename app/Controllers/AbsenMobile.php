<?php

namespace App\Controllers;

use App\Models\ModelPresensi;
use App\Models\ModelPresensiRoster;
use App\Models\ModelUnit;
use App\Services\Kpi\AttendanceScoreCalculator;
use App\Services\Kpi\PresensiAttendanceSyncService;
use App\Services\Presensi\AbsenPunchService;
use App\Services\Presensi\PresensiException;
use App\Services\Presensi\PresensiScopeService;
use CodeIgniter\Controller;

class AbsenMobile extends Controller
{
    protected $helpers = ['url', 'form'];

    public function index()
    {
        // Jangan ubah timezone global proses; cukup hitung waktu Jakarta.
        $zona     = new \DateTimeZone(config('Presensi')->zonaWaktu);
        $sekarang = new \DateTime('now', $zona);
        $hariIni  = $sekarang->format('Y-m-d');
        $akunId   = (int) session('ID_AKUN');
        $unitId   = (int) session('ID_UNIT');
        $goLive   = config('Presensi')->goLiveDate;

        $roster = null;
        $sesi   = [];
        if ($hariIni >= $goLive) {
            $roster = (new ModelPresensiRoster())->getByAkunTanggal($akunId, $hariIni);

            if ($roster && in_array($roster->shift, ['PAGI', 'SIANG', 'PS'], true)) {
                $rows   = (new ModelPresensi())->getHariIniByAkun($akunId, $hariIni);
                $bySesi = [];
                foreach ($rows as $row) {
                    $bySesi[$row->sesi ?: 'FULL'] = $row;
                }

                foreach ($roster->shift === 'PS' ? ['PAGI', 'SORE'] : ['FULL'] as $kode) {
                    $sesi[] = [
                        'kode'     => $kode,
                        'label'    => $this->labelSesi($roster->shift, $kode),
                        'masuk'    => AttendanceScoreCalculator::getScheduledTime($roster->shift, $kode),
                        'pulang'   => $this->jamPulang($roster->shift, $kode),
                        'presensi' => $bySesi[$kode] ?? null,
                    ];
                }
            }
        }

        return view('absen/mobile', [
            'hariIni' => $hariIni,
            'goLive'  => $goLive,
            'roster'  => $roster,
            'sesi'    => $sesi,
            'unit'    => (new ModelUnit())->getById($unitId),
            'nama'    => (string) session('NAMA'),
        ]);
    }

    public function masuk()
    {
        if (! $this->request->is('post')) {
            return $this->jawab(false, 'Metode tidak diizinkan.', 405);
        }

        $sekarang = new \DateTime('now', new \DateTimeZone(config('Presensi')->zonaWaktu));
        if ($sekarang->format('Y-m-d') < config('Presensi')->goLiveDate) {
            return $this->jawab(false, 'Absen mobile aktif mulai ' . config('Presensi')->goLiveDate . '.');
        }

        $akunId = (int) session('ID_AKUN');
        $unitId = (int) session('ID_UNIT');
        $lat    = (float) $this->request->getPost('latitude');
        $long   = (float) $this->request->getPost('longitude');
        $file   = $this->request->getFile('foto');
        $waktu  = $sekarang->format('Y-m-d H:i:s');
        $service = new AbsenPunchService();

        try {
            // Lokasi dulu supaya request di luar radius tidak perlu upload.
            $jarak = $service->validasiLokasi($unitId, $lat, $long);

            if (! $file) {
                throw new PresensiException('Foto wajib diambil dan berhasil diunggah.');
            }

            $service->masuk(
                $akunId,
                $unitId,
                (string) $this->request->getPost('sesi'),
                $lat,
                $long,
                $jarak,
                $file,
                $waktu
            );

            $this->syncKpi($akunId, substr($waktu, 0, 10));

            return $this->jawab(true, 'Absen masuk berhasil.');
        } catch (PresensiException $e) {
            return $this->jawab(false, $e->getMessage(), $e->status, ['kode' => $e->kode]);
        } catch (\Throwable $e) {
            log_message('error', 'Absen masuk gagal: ' . $e->getMessage());

            return $this->jawab(false, 'Absen gagal disimpan.', 500);
        }
    }

    public function pulang()
    {
        if (! $this->request->is('post')) {
            return $this->jawab(false, 'Metode tidak diizinkan.', 405);
        }

        $sekarang = new \DateTime('now', new \DateTimeZone(config('Presensi')->zonaWaktu));
        if ($sekarang->format('Y-m-d') < config('Presensi')->goLiveDate) {
            return $this->jawab(false, 'Absen mobile aktif mulai ' . config('Presensi')->goLiveDate . '.');
        }

        $akunId = (int) session('ID_AKUN');
        $unitId = (int) session('ID_UNIT');
        $id     = (int) $this->request->getPost('idpresensi');
        $row    = (new ModelPresensi())->find($id);

        // Kepemilikan dicek sebelum lokasi/foto: 403 tanpa perlu GPS.
        if (! $row || (int) $row->akun_idakun !== $akunId) {
            return $this->jawab(false, 'Data absen tidak ditemukan.', 403);
        }

        $lat    = (float) $this->request->getPost('latitude');
        $long   = (float) $this->request->getPost('longitude');
        $file   = $this->request->getFile('foto');
        $waktu  = $sekarang->format('Y-m-d H:i:s');
        $service = new AbsenPunchService();

        try {
            $service->validasiLokasi($unitId, $lat, $long);

            if (! $file) {
                throw new PresensiException('Foto wajib diambil dan berhasil diunggah.');
            }

            $service->pulang($akunId, $id, $lat, $long, $file, $waktu);

            return $this->jawab(true, 'Absen pulang berhasil.');
        } catch (PresensiException $e) {
            return $this->jawab(false, $e->getMessage(), $e->status, ['kode' => $e->kode]);
        } catch (\Throwable $e) {
            log_message('error', 'Absen pulang gagal: ' . $e->getMessage());

            return $this->jawab(false, 'Absen gagal disimpan.', 500);
        }
    }

    public function foto($id)
    {
        return $this->sajikanFoto((int) $id, 'foto');
    }

    public function fotoPulang($id)
    {
        return $this->sajikanFoto((int) $id, 'foto_pulang');
    }

    private function sajikanFoto(int $id, string $kolom)
    {
        $row = (new ModelPresensi())->find($id);
        if (! $row) {
            return $this->response->setStatusCode(404);
        }

        if (! (new PresensiScopeService())->bolehLihatFoto(
            (int) session('ID_JABATAN'),
            (int) session('ID_UNIT'),
            (int) session('ID_AKUN'),
            (int) $row->akun_idakun,
            (int) $row->unit_idunit
        )) {
            return $this->response->setStatusCode(403);
        }

        $relatif = (string) ($row->{$kolom} ?? '');
        if ($relatif === '' || strpos($relatif, '/') === false) {
            return $this->response->setStatusCode(404); // foto legacy ada di public/, bukan route ini
        }

        $base = realpath(config('Presensi')->fotoDir);
        if ($base === false) {
            return $this->response->setStatusCode(404);
        }

        $path = realpath($base . '/' . $relatif);
        if ($path === false || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || ! is_file($path)) {
            return $this->response->setStatusCode(404);
        }

        // `finfo` tidak tersedia di build PHP 7.4 yang dipakai web; getimagesize() cukup.
        $info = @getimagesize($path);
        $mime = is_array($info) && ! empty($info['mime']) ? $info['mime'] : 'application/octet-stream';

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'inline')
            ->setBody((string) file_get_contents($path));
    }

    private function jawab(bool $ok, string $message, int $status = 200, array $data = [])
    {
        return $this->response->setStatusCode($status)->setJSON(['ok' => $ok, 'message' => $message] + $data);
    }

    /**
     * Punch tetap sukses walau sinkronisasi KPI gagal; kegagalan dicatat
     * dan bisa diperbaiki lewat `spark presensi:sync-kpi`.
     */
    private function syncKpi(int $akunId, string $tanggal): void
    {
        try {
            (new PresensiAttendanceSyncService())->syncDay($akunId, $tanggal);
        } catch (\Throwable $e) {
            log_message('error', 'Sync KPI absen gagal: ' . $e->getMessage());
        }
    }

    private function labelSesi(string $shift, string $sesi): string
    {
        if ($shift !== 'PS') {
            return $shift === 'PAGI' ? 'Pagi' : 'Siang';
        }

        return $sesi === 'PAGI' ? 'PS Pagi' : 'PS Sore';
    }

    private function jamPulang(string $shift, string $sesi): string
    {
        $pulang = config('Presensi')->shiftPulang[$shift];

        return is_array($pulang) ? $pulang[$sesi] : $pulang;
    }
}
