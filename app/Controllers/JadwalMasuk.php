<?php

namespace App\Controllers;

use App\Models\ModelAuth;
use App\Models\ModelPresensiRoster;
use App\Models\ModelUnit;
use App\Services\Kpi\PresensiAttendanceSyncService;
use App\Services\Presensi\PresensiScopeService;
use App\Services\Presensi\RosterService;

class JadwalMasuk extends BaseController
{
    protected $AuthModel;
    protected $UnitModel;

    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->UnitModel = new ModelUnit();
    }

    public function index()
    {
        $jabatan    = (int) session('ID_JABATAN');
        $unitSesi   = (int) session('ID_UNIT');
        $scope      = new PresensiScopeService();
        $bolehSemua = in_array($jabatan, config('Presensi')->rosterSemuaUnit, true);

        $bulan = (string) ($this->request->getGet('bulan') ?: date('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            $bulan = date('Y-m');
        }

        $unitFilter = $bolehSemua
            ? (int) ($this->request->getGet('unit') ?: 0)
            : $unitSesi;

        $pegawai = $unitFilter === 0
            ? $this->AuthModel->getAkun()
            : $this->AuthModel->getAkun($unitFilter);

        $akunId = (int) ($this->request->getGet('akun') ?: 0);
        $valid  = array_map(static fn ($row) => (int) $row->ID_AKUN, $pegawai);
        if ($akunId === 0 || ! in_array($akunId, $valid, true)) {
            $akunId = $valid[0] ?? 0;
        }

        $roster = $akunId === 0 ? [] : (new ModelPresensiRoster())
            ->getRentang($akunId, $bulan . '-01', date('Y-m-t', strtotime($bulan . '-01')));

        return view('template', [
            'body'               => 'absensi/jadwal_masuk',
            'bulan'              => $bulan,
            'unit'               => $this->UnitModel->getUnit(),
            'pegawai'            => $pegawai,
            'akunId'             => $akunId,
            'roster'             => $roster,
            'bolehSemuaUnit'     => $bolehSemua,
            'bolehKoreksiLampau' => $scope->bolehKoreksiLampau($jabatan),
        ]);
    }

    public function terapkan()
    {
        $akun = $this->AuthModel->getById((int) $this->request->getPost('akun_idakun'));
        if (! $akun) {
            return redirect()->back()->with('gagal', 'Pegawai tidak ditemukan.');
        }

        $jabatan  = (int) session('ID_JABATAN');
        $unitSesi = (int) session('ID_UNIT');
        $scope    = new PresensiScopeService();

        if (! $scope->bolehKelolaUnit($jabatan, $unitSesi, (int) $akun->ID_UNIT)) {
            return redirect()->back()->with('gagal', 'Anda tidak berhak mengelola roster unit ini.');
        }

        $bulan = (string) $this->request->getPost('bulan');
        if (! $scope->bolehKoreksiLampau($jabatan) && $bulan < date('Y-m')) {
            return redirect()->back()->with('gagal', 'Roster bulan lampau hanya bisa diubah Admin Root.');
        }

        try {
            $jumlah = (new RosterService())->terapkanBulan(
                (int) $akun->ID_AKUN,
                $bulan,
                (array) $this->request->getPost('pola'),
                (int) session('ID_AKUN')
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('gagal', $e->getMessage());
        }

        $this->syncKpi((int) $akun->ID_AKUN, $bulan);

        return redirect()->back()->with('sukses', $jumlah . ' tanggal roster ditambahkan.');
    }

    public function simpan()
    {
        $akun = $this->AuthModel->getById((int) $this->request->getPost('akun_idakun'));
        if (! $akun) {
            return redirect()->back()->with('gagal', 'Pegawai tidak ditemukan.');
        }

        $jabatan  = (int) session('ID_JABATAN');
        $unitSesi = (int) session('ID_UNIT');
        $scope    = new PresensiScopeService();

        if (! $scope->bolehKelolaUnit($jabatan, $unitSesi, (int) $akun->ID_UNIT)) {
            return redirect()->back()->with('gagal', 'Anda tidak berhak mengelola roster unit ini.');
        }

        $shift      = (array) $this->request->getPost('shift');
        $keterangan = (array) $this->request->getPost('keterangan');
        $hariIni    = date('Y-m-d');

        // Validasi semua baris dulu supaya tidak ada simpan setengah jalan.
        foreach ($shift as $tanggal => $nilai) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $tanggal)) {
                return redirect()->back()->with('gagal', 'Tanggal roster tidak valid.');
            }
            if ($tanggal < $hariIni && ! $scope->bolehKoreksiLampau($jabatan)) {
                return redirect()->back()->with('gagal', 'Roster tanggal lampau hanya bisa diubah Admin Root.');
            }
            if (! in_array((string) $nilai, RosterService::SHIFT_SEMUA, true)) {
                return redirect()->back()->with('gagal', 'Shift roster tidak valid.');
            }
        }

        $service = new RosterService();
        $db      = db_connect();
        $db->transStart();
        foreach ($shift as $tanggal => $nilai) {
            $service->simpanTanggal(
                (int) $akun->ID_AKUN,
                $tanggal,
                (string) $nilai,
                (string) ($keterangan[$tanggal] ?? ''),
                (int) session('ID_AKUN')
            );
        }
        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->back()->with('gagal', 'Roster gagal disimpan.');
        }

        $this->syncKpi((int) $akun->ID_AKUN, (string) $this->request->getPost('bulan'));

        return redirect()->back()->with('sukses', 'Roster berhasil disimpan.');
    }

    /**
     * Sinkronkan OFF/IZIN/SAKIT/CUTI dan punch roster ke KPI tanpa menggagalkan simpan roster.
     */
    private function syncKpi(int $akunId, string $bulan): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            return;
        }

        try {
            (new PresensiAttendanceSyncService())->syncMonth($akunId, $bulan);
        } catch (\Throwable $e) {
            log_message('error', 'Sync KPI roster gagal: ' . $e->getMessage());
        }
    }
}
