<?php

namespace App\Services\Presensi;

use App\Models\ModelPresensiRoster;
use InvalidArgumentException;

/**
 * Terapkan keputusan izin ke roster absensi.
 *
 * Approve menulis rentang tanggal sebagai shift sesuai jenis izin
 * (IZIN/SAKIT/CUTI). Jenis cuti/sakit tidak lagi disimpan di keterangan.
 */
class IzinApprovalService
{
    private $db;
    private $roster;

    public function __construct(?ModelPresensiRoster $roster = null)
    {
        $this->db     = \Config\Database::connect();
        $this->roster = $roster ?? new ModelPresensiRoster();
    }

    /**
     * @return list<string> Bulan (YYYY-MM) yang perlu disinkronkan ke KPI.
     */
    public function setujui(int $izinId, int $oleh): array
    {
        $izin  = $this->izin($izinId);
        $jenis = strtoupper(trim((string) ($izin->jenis_izin ?? ''))) ?: 'IZIN';

        $service = new RosterService($this->roster);
        foreach ($this->tanggal((string) $izin->tanggal_mulai, (string) $izin->tanggal_akhir) as $tanggal) {
            $service->simpanTanggal((int) $izin->akun_idakun, $tanggal, $jenis, null, $oleh);
        }

        return $this->bulan((string) $izin->tanggal_mulai, (string) $izin->tanggal_akhir);
    }

    /**
     * @return list<string> Bulan (YYYY-MM) yang perlu disinkronkan ke KPI.
     */
    public function batalkan(int $izinId, bool $pernahDisetujui = true): array
    {
        $izin  = $this->izin($izinId);
        $jenis = strtoupper(trim((string) ($izin->jenis_izin ?? ''))) ?: 'IZIN';

        // shortcut: tanpa kolom sumber, semua baris shift jenis ini pada rentang
        // pengajuan dianggap milik approval. Upgrade bila perlu membedakan
        // pengajuan yang rentangnya tumpang tindih.
        if ($pernahDisetujui) {
            $this->db->table('presensi_roster')
                ->where('akun_idakun', (int) $izin->akun_idakun)
                ->where('tanggal >=', (string) $izin->tanggal_mulai)
                ->where('tanggal <=', (string) $izin->tanggal_akhir)
                ->where('shift', $jenis)
                ->delete();
        }

        // Hari yang tadinya dikecualikan harus masuk lagi ke denominator KPI.
        // Hanya lakukan bila pengajuan memang pernah disetujui; penolakan
        // pengajuan baru tidak boleh menghapus marker OFF yang sah.
        if ($pernahDisetujui && $this->db->tableExists('kpi_attendance_off')) {
            $this->db->table('kpi_attendance_off')
                ->where('employee_id', (int) $izin->akun_idakun)
                ->where('evaluation_date >=', (string) $izin->tanggal_mulai)
                ->where('evaluation_date <=', (string) $izin->tanggal_akhir)
                ->delete();
        }

        return $this->bulan((string) $izin->tanggal_mulai, (string) $izin->tanggal_akhir);
    }

    private function izin(int $izinId): object
    {
        $row = $this->db->table('izin')
            ->select('izin.idizin, izin.akun_idakun, izin.tanggal_mulai, izin.tanggal_akhir, jenis_perizinan.jenis_izin')
            ->join('jenis_perizinan', 'jenis_perizinan.idjenis_perizinan = izin.jenis_perizinan_idjenis_perizinan', 'left')
            ->where('izin.idizin', $izinId)
            ->get()
            ->getRow();

        if (! $row) {
            throw new InvalidArgumentException('Pengajuan izin tidak ditemukan.');
        }

        return $row;
    }

    /**
     * @return list<string>
     */
    private function tanggal(string $mulai, string $akhir): array
    {
        $dari   = new \DateTimeImmutable($mulai);
        $sampai = new \DateTimeImmutable($akhir);
        if ($sampai < $dari) {
            throw new InvalidArgumentException('Rentang izin tidak valid.');
        }

        $hasil = [];
        for ($hari = $dari; $hari <= $sampai; $hari = $hari->modify('+1 day')) {
            $hasil[] = $hari->format('Y-m-d');
        }

        return $hasil;
    }

    /**
     * @return list<string>
     */
    private function bulan(string $mulai, string $akhir): array
    {
        $bulan = [];
        foreach ($this->tanggal($mulai, $akhir) as $tanggal) {
            $bulan[substr($tanggal, 0, 7)] = true;
        }

        return array_keys($bulan);
    }
}
