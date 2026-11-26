<?php

namespace App\Services\Payroll;

use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

/**
 * Lembur harian untuk Teknisi (36) dan Kepala Toko (41).
 *
 * Aturan:
 * - hanya jabatan 36/41, unit operasional (bukan HO/50), akun aktif & menerima gaji
 * - hari dihitung dari roster shift `FULL`; `presensi.sesi = 'FULL'` bukan
 *   penanda lembur karena semua shift non-PS memakai sesi itu
 * - hanya tanggal yang sudah lewat (`< hari ini`); tanggal berjalan/belum
 *   lewat tidak dihitung
 * - satu tanggal dihitung sekali, tarif Rp35.000/hari
 * - periode sebelum 2026-11-01 tetap memakai kas_keluar (bukan service ini)
 */
class AttendanceOvertimeService
{
    public const TARIF_HARIAN = 35000;

    public const JABATAN_PENERIMA = [36, 41];

    public const MULAI_SUMBER_PRESENSI = '2026-11-01';

    public const SHIFT_LEMBUR = 'FULL';

    /** @var BaseConnection */
    protected $db;

    private string $hariIni;

    public function __construct(?BaseConnection $db = null, ?string $hariIni = null)
    {
        $this->db = $db ?? \Config\Database::connect();
        $this->hariIni = $hariIni ?? date('Y-m-d');
    }

    public function sumberPeriode(string $bulan): string
    {
        $this->pastikanBulan($bulan);

        return ($bulan . '-01') >= self::MULAI_SUMBER_PRESENSI ? 'presensi' : 'kas_keluar';
    }

    /**
     * @return array<int, array{hari:int, tanggal:list<string>, nominal:int}>
     */
    public function ringkasanPeriode(string $bulan): array
    {
        $this->pastikanBulan($bulan);

        if ($this->sumberPeriode($bulan) !== 'presensi') {
            return [];
        }

        $pegawai = $this->pegawaiLayak();
        if ($pegawai === []) {
            return [];
        }

        $mulai = $bulan . '-01';
        $akhir = date('Y-m-t', strtotime($mulai));

        $rows = $this->db->table('presensi_roster')
            ->select('akun_idakun AS pegawai_id, tanggal')
            ->where('shift', self::SHIFT_LEMBUR)
            ->where('tanggal >=', $mulai)
            ->where('tanggal <=', $akhir)
            ->where('tanggal <', $this->hariIni)
            ->whereIn('akun_idakun', $pegawai)
            ->groupBy('akun_idakun')
            ->groupBy('tanggal')
            ->orderBy('akun_idakun', 'ASC')
            ->orderBy('tanggal', 'ASC')
            ->get()
            ->getResultArray();

        $hasil = [];
        foreach ($rows as $row) {
            $id = (int) $row['pegawai_id'];
            if (! isset($hasil[$id])) {
                $hasil[$id] = ['hari' => 0, 'tanggal' => [], 'nominal' => 0];
            }

            $hasil[$id]['hari']++;
            $hasil[$id]['tanggal'][] = (string) $row['tanggal'];
            $hasil[$id]['nominal'] += self::TARIF_HARIAN;
        }

        foreach ($hasil as $id => $ringkasan) {
            sort($hasil[$id]['tanggal']);
        }
        ksort($hasil);

        return $hasil;
    }

    /**
     * @return array{hari:int, tanggal:list<string>, nominal:int}
     */
    public function untukPegawai(int $employeeId, string $bulan): array
    {
        $ringkasan = $this->ringkasanPeriode($bulan);

        return $ringkasan[$employeeId] ?? ['hari' => 0, 'tanggal' => [], 'nominal' => 0];
    }

    /**
     * @return list<array{pegawai_id:int, nama:string, jabatan_id:int, hari:int, tanggal:list<string>, nominal:int}>
     */
    public function laporan(string $bulan, ?int $pegawaiId = null): array
    {
        $ringkasan = $this->ringkasanPeriode($bulan);
        if ($ringkasan === []) {
            return [];
        }

        $ids = array_keys($ringkasan);
        if ($pegawaiId !== null && $pegawaiId > 0) {
            $ids = array_values(array_intersect($ids, [$pegawaiId]));
        }
        if ($ids === []) {
            return [];
        }

        $akun = $this->db->table('akun')
            ->select('ID_AKUN, NAMA_AKUN, ID_JABATAN')
            ->whereIn('ID_AKUN', $ids)
            ->get()
            ->getResultArray();

        $hasil = [];
        foreach ($akun as $row) {
            $id = (int) $row['ID_AKUN'];
            $hasil[] = [
                'pegawai_id' => $id,
                'nama'       => (string) $row['NAMA_AKUN'],
                'jabatan_id' => (int) $row['ID_JABATAN'],
                'hari'       => $ringkasan[$id]['hari'],
                'tanggal'    => $ringkasan[$id]['tanggal'],
                'nominal'    => $ringkasan[$id]['nominal'],
            ];
        }

        usort($hasil, static function (array $a, array $b): int {
            return [$a['jabatan_id'], $a['nama']] <=> [$b['jabatan_id'], $b['nama']];
        });

        return $hasil;
    }

    /**
     * Apakah masih ada lembur manual di kas_keluar untuk pegawai yang layak?
     * Hanya berlaku pada periode presensi; periode legacy tidak memicu peringatan.
     */
    public function adaLemburManual(string $bulan): bool
    {
        $this->pastikanBulan($bulan);

        if ($this->sumberPeriode($bulan) !== 'presensi') {
            return false;
        }

        $layak = array_flip($this->pegawaiLayak());
        if ($layak === []) {
            return false;
        }

        $rows = $this->db->table('kas_keluar')
            ->select('penerima')
            ->where('kategori_idkategori', 10)
            ->where('tanggal >=', $bulan . '-01')
            ->where('tanggal <=', date('Y-m-t', strtotime($bulan . '-01')))
            ->like('deskripsi', 'lembur')
            ->groupBy('penerima')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $penerima = trim((string) ($row['penerima'] ?? ''));

            if ($penerima !== '' && ctype_digit($penerima) && isset($layak[(int) $penerima])) {
                return true;
            }
        }

        return false;
    }

    /** @return list<int> */
    protected function pegawaiLayak(): array
    {
        $rows = $this->db->table('akun a')
            ->select('a.ID_AKUN')
            ->join('unit u', 'u.idunit = a.ID_UNIT')
            ->whereIn('a.ID_JABATAN', self::JABATAN_PENERIMA)
            ->where('a.STATUS_PEGAWAI', 1)
            ->groupStart()
            ->where('a.deleted', null)
            ->orWhere('a.deleted', '')
            ->groupEnd()
            ->where('a.TERIMA_GAJI', 1)
            ->where('u.is_operational', 1)
            ->where('a.ID_UNIT !=', 50)
            ->get()
            ->getResultArray();

        return array_map(static fn ($row) => (int) $row['ID_AKUN'], $rows);
    }

    private function pastikanBulan(string $bulan): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            throw new InvalidArgumentException('Format bulan harus YYYY-MM.');
        }
    }
}
