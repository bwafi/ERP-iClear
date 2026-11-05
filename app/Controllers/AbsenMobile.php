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

            if ($roster && in_array($roster->shift, ['PAGI', 'SIANG', 'FULL', 'PS'], true)) {
                $rows   = (new ModelPresensi())->getHariIniByAkun($akunId, $hariIni);
                $bySesi = [];
                foreach ($rows as $row) {
                    $bySesi[$row->sesi ?: 'FULL'] = $row;
                }

                $jamSekarang = $sekarang->format('H:i:s');
                $sesi[] = $roster->shift === 'PS'
                    ? $this->alurPs($bySesi, $jamSekarang, $hariIni, $zona)
                    : $this->alurBiasa($roster->shift, $bySesi, $jamSekarang, $hariIni, $zona);
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

    public function istirahat()
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

        if (! $row || (int) $row->akun_idakun !== $akunId) {
            return $this->jawab(false, 'Data absen tidak ditemukan.', 403);
        }

        $lat    = (float) $this->request->getPost('latitude');
        $long   = (float) $this->request->getPost('longitude');
        $waktu  = $sekarang->format('Y-m-d H:i:s');
        $service = new AbsenPunchService();

        try {
            $service->validasiLokasi($unitId, $lat, $long);
            $service->istirahat($akunId, $id, $waktu);

            return $this->jawab(true, 'Absen break berhasil.');
        } catch (PresensiException $e) {
            return $this->jawab(false, $e->getMessage(), $e->status, ['kode' => $e->kode]);
        } catch (\Throwable $e) {
            log_message('error', 'Absen break gagal: ' . $e->getMessage());

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

    private function alurBiasa(string $shift, array $bySesi, string $jamSekarang, string $hariIni, \DateTimeZone $zona): array
    {
        $row       = $bySesi['FULL'] ?? null;
        $masuk     = AttendanceScoreCalculator::getScheduledTime($shift, 'FULL');
        $pulang    = $this->jamPulang($shift, 'FULL');
        $ringkasan = [];

        if ($row) {
            $ringkasan[] = 'Masuk ' . $this->jamRingkas($row->waktu_masuk)
                . ' (' . $this->labelKehadiran($row) . ')';
            if (! empty($row->waktu_pulang)) {
                $ringkasan[] = 'Pulang ' . $this->jamRingkas($row->waktu_pulang);
            }
        }

        $langkah = 'masuk';
        if ($row) {
            $langkah = empty($row->waktu_pulang) ? 'pulang' : 'selesai';
        }

        $rel = [
            ['kode' => 'masuk', 'label' => 'Masuk', 'jam' => $masuk],
            ['kode' => 'pulang', 'label' => 'Pulang', 'jam' => $pulang],
        ];
        $langkahIndex = ['masuk' => 0, 'pulang' => 1, 'selesai' => 2][$langkah] ?? 0;
        $timeline     = [];

        if ($row) {
            $timeline[] = [
                'kode'       => 'masuk',
                'label'      => 'Masuk',
                'waktu'      => $this->jamRingkas($row->waktu_masuk),
                'status'     => $this->labelKehadiran($row),
                'foto'       => ! empty($row->foto) ? 'absen/foto/' . (int) $row->idpresensi : null,
                'foto_label' => 'Foto masuk',
                'tanpa_foto' => empty($row->foto),
            ];

            if (! empty($row->waktu_pulang)) {
                $timeline[] = [
                    'kode'       => 'pulang',
                    'label'      => 'Pulang',
                    'waktu'      => $this->jamRingkas($row->waktu_pulang),
                    'status'     => null,
                    'foto'       => ! empty($row->foto_pulang)
                        ? 'absen/foto/' . (int) $row->idpresensi . '/pulang'
                        : null,
                    'foto_label' => 'Foto pulang',
                    'tanpa_foto' => empty($row->foto_pulang),
                ];
            }
        }

        return [
            'kode'          => 'FULL',
            'label'         => $this->labelSesi($shift, 'FULL'),
            'rentang'       => $masuk . '–' . $pulang,
            'pulang'        => $pulang,
            'buka'          => $this->bukaPada($hariIni, $pulang, $zona),
            'boleh_tutup'   => $jamSekarang >= $pulang . ':00',
            'langkah'       => $langkah,
            'langkah_index' => $langkahIndex,
            'rel'           => $rel,
            'timeline'      => $timeline,
            'presensi'      => $row,
            'ringkasan'     => implode(' · ', $ringkasan),
            'foto'          => $this->tautanFoto($row, null, 'Foto pulang'),
        ];
    }

    private function alurPs(array $bySesi, string $jamSekarang, string $hariIni, \DateTimeZone $zona): array
    {
        $pagi  = $bySesi['PAGI'] ?? null;
        $sore  = $bySesi['SORE'] ?? null;
        $jamPagi    = AttendanceScoreCalculator::getScheduledTime('PS', 'PAGI');
        $jamSore    = AttendanceScoreCalculator::getScheduledTime('PS', 'SORE');
        $pulangPagi = $this->jamPulang('PS', 'PAGI');
        $pulangSore = $this->jamPulang('PS', 'SORE');

        $ringkasan = [];
        if ($pagi) {
            $ringkasan[] = 'Masuk ' . $this->jamRingkas($pagi->waktu_masuk)
                . ' (' . $this->labelKehadiran($pagi) . ')';
            if (! empty($pagi->waktu_pulang)) {
                $ringkasan[] = 'Break ' . $this->jamRingkas($pagi->waktu_pulang);
            }
        }
        if ($sore) {
            $ringkasan[] = 'In Break ' . $this->jamRingkas($sore->waktu_masuk)
                . ' (' . $this->labelKehadiran($sore) . ')';
            if (! empty($sore->waktu_pulang)) {
                $ringkasan[] = 'Pulang ' . $this->jamRingkas($sore->waktu_pulang);
            }
        }

        $langkah = 'masuk';
        $row     = null;
        $kode    = 'PAGI';
        $pulang  = $pulangPagi;

        if ($sore) {
            $row     = $sore;
            $pulang  = $pulangSore;
            $langkah = empty($sore->waktu_pulang) ? 'pulang' : 'selesai';
        } elseif ($pagi) {
            $row = $pagi;
            if (empty($pagi->waktu_pulang)) {
                $langkah = 'break';
            } else {
                $langkah = 'masuk_lagi';
                $kode    = 'SORE';
                $pulang  = $pulangSore;
            }
        }

        $rel = [
            ['kode' => 'masuk', 'label' => 'Masuk', 'jam' => $jamPagi],
            ['kode' => 'break', 'label' => 'Break', 'jam' => $pulangPagi],
            ['kode' => 'masuk_lagi', 'label' => 'In Break', 'jam' => $jamSore],
            ['kode' => 'pulang', 'label' => 'Pulang', 'jam' => $pulangSore],
        ];
        $langkahIndex = [
            'masuk'      => 0,
            'break'      => 1,
            'masuk_lagi' => 2,
            'pulang'     => 3,
            'selesai'    => 4,
        ][$langkah] ?? 0;

        $timeline = [];
        if ($pagi) {
            $timeline[] = [
                'kode'       => 'masuk',
                'label'      => 'Masuk',
                'waktu'      => $this->jamRingkas($pagi->waktu_masuk),
                'status'     => $this->labelKehadiran($pagi),
                'foto'       => ! empty($pagi->foto) ? 'absen/foto/' . (int) $pagi->idpresensi : null,
                'foto_label' => 'Foto masuk',
                'tanpa_foto' => empty($pagi->foto),
            ];

            if (! empty($pagi->waktu_pulang)) {
                $timeline[] = [
                    'kode'       => 'break',
                    'label'      => 'Break',
                    'waktu'      => $this->jamRingkas($pagi->waktu_pulang),
                    'status'     => null,
                    'foto'       => null,
                    'foto_label' => null,
                    'tanpa_foto' => true,
                ];
            }
        }

        if ($sore) {
            $timeline[] = [
                'kode'       => 'masuk_lagi',
                'label'      => 'In Break',
                'waktu'      => $this->jamRingkas($sore->waktu_masuk),
                'status'     => $this->labelKehadiran($sore),
                'foto'       => ! empty($sore->foto) ? 'absen/foto/' . (int) $sore->idpresensi : null,
                'foto_label' => 'Foto in break',
                'tanpa_foto' => empty($sore->foto),
            ];

            if (! empty($sore->waktu_pulang)) {
                $timeline[] = [
                    'kode'       => 'pulang',
                    'label'      => 'Pulang',
                    'waktu'      => $this->jamRingkas($sore->waktu_pulang),
                    'status'     => null,
                    'foto'       => ! empty($sore->foto_pulang)
                        ? 'absen/foto/' . (int) $sore->idpresensi . '/pulang'
                        : null,
                    'foto_label' => 'Foto pulang',
                    'tanpa_foto' => empty($sore->foto_pulang),
                ];
            }
        }

        return [
            'kode'          => $kode,
            'label'         => 'Shift PS',
            'rentang'       => $jamPagi . '–' . $pulangPagi . ' · ' . $jamSore . '–' . $pulangSore,
            'pulang'        => $pulang,
            'buka'          => $this->bukaPada($hariIni, $pulang, $zona),
            'boleh_tutup'   => $jamSekarang >= $pulang . ':00',
            'langkah'       => $langkah,
            'langkah_index' => $langkahIndex,
            'rel'           => $rel,
            'timeline'      => $timeline,
            'presensi'      => $row,
            'ringkasan'     => implode(' · ', $ringkasan),
            'foto'          => $this->tautanFoto($pagi, $sore),
        ];
    }

    private function jamRingkas(?string $waktu): string
    {
        return substr((string) $waktu, 11, 5);
    }

    private function labelKehadiran(object $row): string
    {
        if ($row->status_kehadiran === null) {
            return 'Tercatat';
        }

        return [0 => 'Tepat', 1 => 'Toleransi', 2 => 'Telat'][(int) $row->status_kehadiran] ?? 'Tercatat';
    }

    private function bukaPada(string $hariIni, string $jam, \DateTimeZone $zona): string
    {
        return (new \DateTime($hariIni . ' ' . $jam . ':00', $zona))->format(\DateTimeInterface::ATOM);
    }

    /**
     * @return list<array{label:string,url:string}>
     */
    private function tautanFoto(?object $pagi, ?object $sore, string $labelPagiTutup = 'Foto break'): array
    {
        $foto = [];
        if ($pagi) {
            $foto[] = ['label' => 'Foto masuk', 'url' => 'absen/foto/' . (int) $pagi->idpresensi];
            if (! empty($pagi->foto_pulang)) {
                $foto[] = ['label' => $labelPagiTutup, 'url' => 'absen/foto/' . (int) $pagi->idpresensi . '/pulang'];
            }
        }
        if ($sore) {
            $foto[] = ['label' => 'Foto in break', 'url' => 'absen/foto/' . (int) $sore->idpresensi];
            if (! empty($sore->foto_pulang)) {
                $foto[] = ['label' => 'Foto pulang', 'url' => 'absen/foto/' . (int) $sore->idpresensi . '/pulang'];
            }
        }

        return $foto;
    }

    private function labelSesi(string $shift, string $sesi): string
    {
        if ($shift === 'FULL') {
            return 'Full';
        }

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
