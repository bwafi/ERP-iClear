<?php

namespace App\Controllers;

use App\Models\ModelIzin;
use App\Models\ModelJenisPerizinan;
use App\Services\Kpi\PresensiAttendanceSyncService;
use App\Services\Presensi\IzinApprovalService;
use App\Services\Presensi\PresensiException;
use App\Services\Presensi\PresensiScopeService;
use App\Services\Presensi\SuratIzinService;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Pengajuan dan approval izin/sakit/cuti.
 *
 * Absensi tidak lewat sini: punch mobile langsung final (`status_absensi = 1`).
 */
class Izin extends BaseController
{
    public function index()
    {
        return view('izin/pengajuan', [
            'jenis'     => (new ModelJenisPerizinan())->orderBy('idjenis_perizinan', 'ASC')->findAll(),
            'pengajuan' => (new ModelIzin())->getByAkun((int) session('ID_AKUN')),
        ]);
    }

    public function simpan()
    {
        if (! $this->request->is('post')) {
            return redirect()->to(base_url('pengajuan_izin'));
        }

        $jenis  = (int) $this->request->getPost('jenis');
        $mulai  = (string) $this->request->getPost('tanggal_mulai');
        $akhir  = (string) ($this->request->getPost('tanggal_akhir') ?: $mulai);
        $alasan = trim((string) $this->request->getPost('alasan'));

        $jenisRow  = $jenis > 0 ? (new ModelJenisPerizinan())->getById($jenis) : null;
        $namaJenis = $jenisRow ? strtoupper(trim((string) $jenisRow->jenis_izin)) : '';

        $error = '';
        if (! $jenisRow) {
            $error = 'Jenis perizinan tidak valid.';
        } elseif (! $this->tanggalValid($mulai) || ! $this->tanggalValid($akhir)) {
            $error = 'Tanggal tidak valid.';
        } elseif ($akhir < $mulai) {
            $error = 'Tanggal akhir tidak boleh sebelum tanggal mulai.';
        } elseif ($alasan === '') {
            $error = 'Alasan wajib diisi.';
        } elseif ($namaJenis === 'CUTI' && $mulai < $this->tanggalMinimalCuti()) {
            $error = 'Pengajuan cuti minimal 7 hari sebelum tanggal mulai.';
        }

        $file    = $this->request->getFile('surat');
        $adaFile = $file instanceof UploadedFile && $file->getError() !== UPLOAD_ERR_NO_FILE;
        if ($error === '' && $adaFile && $namaJenis !== 'SAKIT') {
            $error = 'Surat keterangan dokter hanya untuk pengajuan sakit.';
        }

        if ($error !== '') {
            return redirect()->to(base_url('pengajuan_izin'))->with('gagal', $error);
        }

        $surat = null;
        if ($adaFile) {
            try {
                $surat = (new SuratIzinService())->simpan($file, (int) session('ID_AKUN'));
            } catch (PresensiException $e) {
                return redirect()->to(base_url('pengajuan_izin'))->with('gagal', $e->getMessage());
            }
        }

        $tersimpan = (new ModelIzin())->insert([
            'akun_idakun'                        => (int) session('ID_AKUN'),
            'jenis_perizinan_idjenis_perizinan'  => $jenis,
            'tanggal_mulai'                      => $mulai,
            'tanggal_akhir'                      => $akhir,
            'alasan'                             => $alasan,
            'status'                             => 0,
            'file'                               => $surat,
        ]);

        if (! $tersimpan) {
            (new SuratIzinService())->hapus($surat);

            return redirect()->to(base_url('pengajuan_izin'))->with('gagal', 'Pengajuan gagal disimpan.');
        }

        return redirect()->to(base_url('pengajuan_izin'))->with('sukses', 'Pengajuan terkirim.');
    }

    public function surat($id)
    {
        $row = (new ModelIzin())->getById((int) $id);
        if (! $row) {
            return $this->response->setStatusCode(404);
        }

        $akunPemilik = (int) $row->akun_idakun;
        $akunUnit    = db_connect()->table('akun')->select('ID_UNIT')->where('ID_AKUN', $akunPemilik)->get()->getRow();
        $unitPemilik = (int) ($akunUnit->ID_UNIT ?? 0);

        // Surat dokter bersifat privat; pakai aturan scope yang sama dengan foto absen.
        if (! (new PresensiScopeService())->bolehLihatFoto(
            (int) session('ID_JABATAN'),
            (int) session('ID_UNIT'),
            (int) session('ID_AKUN'),
            $akunPemilik,
            $unitPemilik
        )) {
            return $this->response->setStatusCode(403);
        }

        $relatif = (string) ($row->file ?? '');
        if ($relatif === '' || strpos($relatif, '/') === false) {
            return $this->response->setStatusCode(404);
        }

        $base = realpath(config('Presensi')->suratDir);
        if ($base === false) {
            return $this->response->setStatusCode(404);
        }

        $path = realpath($base . '/' . $relatif);
        if ($path === false || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || ! is_file($path)) {
            return $this->response->setStatusCode(404);
        }

        $mime = [
            'jpg'  => 'image/jpeg',
            'png'  => 'image/png',
            'pdf'  => 'application/pdf',
        ][strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'inline')
            ->setBody((string) file_get_contents($path));
    }

    public function approval()
    {
        return view('template', [
            'body'      => 'admin/approval_izin',
            'pengajuan' => (new ModelIzin())->getWithAkun(),
        ]);
    }

    public function putuskan()
    {
        if (! $this->request->is('post')) {
            return redirect()->to(base_url('approval_presensi'));
        }

        $id      = (int) $this->request->getPost('idizin');
        $status  = (int) $this->request->getPost('status');
        $balasan = trim((string) $this->request->getPost('balasan'));

        if ($id <= 0 || ! in_array($status, [1, 2], true)) {
            return redirect()->to(base_url('approval_presensi'))->with('gagal', 'Data keputusan tidak valid.');
        }

        $model = new ModelIzin();
        $izin  = $model->getById($id);
        if (! $izin) {
            return redirect()->to(base_url('approval_presensi'))->with('gagal', 'Pengajuan tidak ditemukan.');
        }

        $oleh = (int) session('ID_AKUN');
        $db   = db_connect();
        $db->transStart();

        try {
            $model->update($id, ['status' => $status, 'balasan' => $balasan]);
            $bulan = $status === 1
                ? (new IzinApprovalService())->setujui($id, $oleh)
                : (new IzinApprovalService())->batalkan($id, (int) $izin->status === 1);
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Keputusan izin gagal diterapkan: ' . $e->getMessage());

            return redirect()->to(base_url('approval_presensi'))->with('gagal', 'Keputusan gagal disimpan.');
        }

        if (! $db->transStatus()) {
            return redirect()->to(base_url('approval_presensi'))->with('gagal', 'Keputusan gagal disimpan.');
        }

        $this->syncKpi((int) $izin->akun_idakun, $bulan);

        return redirect()->to(base_url('approval_presensi'))
            ->with('sukses', $status === 1 ? 'Pengajuan disetujui.' : 'Pengajuan ditolak.');
    }

    /**
     * Sinkronkan KPI setelah roster izin berubah; kegagalan sync tidak
     * membatalkan keputusan approval.
     *
     * @param list<string> $bulan
     */
    private function syncKpi(int $akunId, array $bulan): void
    {
        try {
            $sync = new PresensiAttendanceSyncService();
        } catch (\Throwable $e) {
            log_message('error', 'Sync KPI izin tidak tersedia: ' . $e->getMessage());

            return;
        }

        foreach ($bulan as $periode) {
            try {
                $sync->syncMonth($akunId, $periode);
            } catch (\Throwable $e) {
                log_message('error', 'Sync KPI izin gagal untuk ' . $periode . ': ' . $e->getMessage());
            }
        }
    }

    private function tanggalValid(string $tanggal): bool
    {
        $date = \DateTime::createFromFormat('Y-m-d', $tanggal);

        return $date !== false && $date->format('Y-m-d') === $tanggal;
    }

    private function tanggalMinimalCuti(): string
    {
        $zona = new \DateTimeZone(config('Presensi')->zonaWaktu);

        return (new \DateTimeImmutable('today', $zona))->modify('+7 days')->format('Y-m-d');
    }
}
