<?php

namespace App\Services\Kpi;

use App\Models\ModelKpiComponent;

/**
 * Nilai SERAGAM dari foto absen mobile.
 *
 * Evaluator melihat foto punch lalu mencentang tiga komponen:
 * seragam (2), sepatu (1), ID card (2) — total 0–5 per hari. Satu baris
 * `kpi_evaluations` per pegawai + tanggal; simpan ulang mengganti baris itu
 * supaya tidak ada rata-rata ganda. Rincian centang disimpan di `notes`
 * karena tidak ada kolom khusus (tanpa perubahan schema).
 */
class PresensiSeragamService
{
    public const NOTE_PREFIX = 'SERAGAM_ABSEN';

    /** Bobot tiap centang; total maksimal 5. */
    private const BOBOT = ['seragam' => 2, 'sepatu' => 1, 'idcard' => 2];

    private const JABATAN_MANAGER = 34;
    private const JABATAN_SPV     = 40;
    private const UNIT_HO         = 50;

    private $db;
    private $component;
    private $tickerId;
    private $ticker;
    private $spvUnits;

    public function __construct(?int $tickerId = null)
    {
        $this->db       = \Config\Database::connect();
        $this->component = (new ModelKpiComponent())->where('code', 'SERAGAM')->first();
        $this->tickerId = $tickerId ?? (int) session('ID_AKUN');
    }

    /**
     * Apakah evaluator aktif boleh menilai pegawai dengan unit/jabatan ini.
     */
    public function bolehMenilai(int $employeeUnit, int $employeeJabatan): bool
    {
        $ticker = $this->ticker();
        if ($ticker === null || $employeeJabatan === self::JABATAN_MANAGER) {
            return false;
        }

        $role = (int) ($ticker->ID_JABATAN ?? 0);

        if (in_array($role, [0, 1], true)) {
            return true;
        }

        if ($role === self::JABATAN_MANAGER) {
            return $employeeUnit === self::UNIT_HO;
        }

        if ($role === self::JABATAN_SPV) {
            return in_array($employeeUnit, $this->spvUnits(), true);
        }

        return false;
    }

    /**
     * Simpan nilai SERAGAM satu hari. Aman dipanggil ulang (mengganti baris).
     *
     * @return array{score:float, seragam:bool, sepatu:bool, idcard:bool}
     */
    public function simpan(int $employeeId, string $date, bool $seragam, bool $sepatu, bool $idcard, bool $timpaManual = false): array
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \InvalidArgumentException('Tanggal absen tidak valid.');
        }
        if ($this->component === null) {
            throw new \RuntimeException('Komponen KPI SERAGAM tidak ditemukan.');
        }

        $employee = $this->db->table('akun')
            ->select('ID_UNIT, ID_JABATAN')
            ->where('ID_AKUN', $employeeId)
            ->get()
            ->getRow();

        if ($employee === null) {
            throw new \RuntimeException('Pegawai tidak ditemukan.');
        }

        $employeeUnit    = (int) $employee->ID_UNIT;
        $employeeJabatan = (int) $employee->ID_JABATAN;

        if ($employeeJabatan === self::JABATAN_MANAGER) {
            throw new \RuntimeException('SERAGAM Manager dihitung otomatis dari rata-rata tim.');
        }
        if (! $this->bolehMenilai($employeeUnit, $employeeJabatan)) {
            throw new \RuntimeException('Anda tidak berwenang menilai SERAGAM pegawai ini.');
        }

        $adaPresensi = $this->db->table('presensi')
            ->where('akun_idakun', $employeeId)
            ->where('DATE(waktu_masuk)', $date)
            ->countAllResults();
        if ($adaPresensi === 0) {
            throw new \RuntimeException('Tidak ada absen pada tanggal tersebut.');
        }

        // Nilai manual lama (sebelum ada checklist foto) tidak boleh tertimpa
        // tanpa konfirmasi eksplisit dari tombol "Nilai ulang dari foto".
        if (! $timpaManual) {
            $lama = $this->db->table('kpi_evaluations')
                ->select('notes')
                ->where('employee_id', $employeeId)
                ->where('kpi_component_id', (int) $this->component->id)
                ->where('evaluation_date', $date)
                ->get()
                ->getResult();
            foreach ($lama as $row) {
                if (strpos((string) $row->notes, self::NOTE_PREFIX) !== 0) {
                    throw new \RuntimeException('Nilai SERAGAM manual lama belum ditimpa. Gunakan "Nilai ulang dari foto".');
                }
            }
        }

        $score = ($seragam ? self::BOBOT['seragam'] : 0)
            + ($sepatu ? self::BOBOT['sepatu'] : 0)
            + ($idcard ? self::BOBOT['idcard'] : 0);

        $now    = date('Y-m-d H:i:s');
        $period = explode('-', $date);
        $notes  = sprintf(
            '%s;S=%d;P=%d;I=%d;BY=%d;AT=%s',
            self::NOTE_PREFIX,
            $seragam ? 1 : 0,
            $sepatu ? 1 : 0,
            $idcard ? 1 : 0,
            $this->tickerId,
            $now
        );

        $this->db->transStart();
        $this->db->table('kpi_evaluations')
            ->where('employee_id', $employeeId)
            ->where('kpi_component_id', (int) $this->component->id)
            ->where('evaluation_date', $date)
            ->delete();
        $this->db->table('kpi_evaluations')->insert([
            'employee_id'      => $employeeId,
            'kpi_component_id' => (int) $this->component->id,
            'evaluator_id'     => $this->tickerId,
            'evaluation_date'  => $date,
            'raw_score'        => $score,
            'max_score'        => 5,
            'normalized_score' => $score / 5 * 100,
            'weighted_score'   => 0,
            'notes'            => $notes,
            'period_year'      => (int) $period[0],
            'period_month'     => (int) $period[1],
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new \RuntimeException('Gagal menyimpan nilai SERAGAM.');
        }

        return ['score' => (float) $score, 'seragam' => $seragam, 'sepatu' => $sepatu, 'idcard' => $idcard];
    }

    /**
     * Peta nilai tersimpan untuk baris riwayat absen.
     *
     * @param array<int,mixed> $rows baris dengan akun_idakun + tanggal/iso
     * @return array<string,array{score:float,seragam:bool,sepatu:bool,idcard:bool}>
     */
    public function petaNilai(array $rows): array
    {
        if ($this->component === null) {
            return [];
        }

        $pasangan = [];
        foreach ($rows as $row) {
            $row      = (array) $row;
            $akunId   = (int) ($row['akun_idakun'] ?? 0);
            $tanggal  = substr((string) ($row['tanggal'] ?? ($row['evaluation_date'] ?? '')), 0, 10);
            if ($akunId > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
                $pasangan[$akunId . '|' . $tanggal] = true;
            }
        }
        if ($pasangan === []) {
            return [];
        }

        $akunIds  = [];
        $tanggal  = [];
        foreach (array_keys($pasangan) as $key) {
            [$id, $tgl] = explode('|', $key);
            $akunIds[]  = (int) $id;
            $tanggal[]  = $tgl;
        }

        $evals = $this->db->table('kpi_evaluations')
            ->select('employee_id, evaluation_date, raw_score, notes')
            ->where('kpi_component_id', (int) $this->component->id)
            ->whereIn('employee_id', array_values(array_unique($akunIds)))
            ->whereIn('evaluation_date', array_values(array_unique($tanggal)))
            ->get()
            ->getResult();

        $peta = [];
        foreach ($evals as $eval) {
            $key = (int) $eval->employee_id . '|' . $eval->evaluation_date;
            if (! isset($pasangan[$key])) {
                continue;
            }

            $flags = $this->uraiNotes((string) $eval->notes);
            $manual = $flags === null;
            $peta[$key] = [
                'score'   => (float) $eval->raw_score,
                'seragam' => $flags === null ? false : $flags['seragam'],
                'sepatu'  => $flags === null ? false : $flags['sepatu'],
                'idcard'  => $flags === null ? false : $flags['idcard'],
                'manual'  => $manual || ! empty($peta[$key]['manual']),
            ];
        }

        return $peta;
    }

    /**
     * @return array{seragam:bool,sepatu:bool,idcard:bool}|null
     */
    private function uraiNotes(string $notes): ?array
    {
        if (strpos($notes, self::NOTE_PREFIX) !== 0) {
            return null;
        }

        return [
            'seragam' => (bool) preg_match('/;S=1(;|$)/', $notes),
            'sepatu'  => (bool) preg_match('/;P=1(;|$)/', $notes),
            'idcard'  => (bool) preg_match('/;I=1(;|$)/', $notes),
        ];
    }

    private function ticker(): ?object
    {
        if ($this->ticker !== null) {
            return $this->ticker;
        }
        if ($this->tickerId <= 0) {
            return null;
        }

        $this->ticker = $this->db->table('akun')
            ->select('ID_AKUN, ID_UNIT, ID_JABATAN')
            ->where('ID_AKUN', $this->tickerId)
            ->get()
            ->getRow();

        return $this->ticker;
    }

    /**
     * @return array<int,int>
     */
    private function spvUnits(): array
    {
        if ($this->spvUnits !== null) {
            return $this->spvUnits;
        }

        $rows = $this->db->table('spv_units')
            ->select('unit_id')
            ->where('spv_id', $this->tickerId)
            ->get()
            ->getResult();

        return $this->spvUnits = array_map(static fn ($r) => (int) $r->unit_id, $rows);
    }
}
