<?php

namespace App\Services\Kpi;

use App\Models\ModelAuth;

/**
 * ManualGridService — sumber tunggal data grid input harian KPI manual
 * (non-absensi), dipakai bersama oleh PenilaianKPI::kpi_detail/penilaian
 * dan Penilaian::spv_kpi_index.
 *
 * Aturan komponen yang masuk grid:
 *   - type = 'manual' & is_active = 1
 *   - BUKAN komponen absensi harian (KEHADIRAN, KEBERSIHAN, SERAGAM, KEPATUHAN_SOP)
 *     karena itu diinput lewat halaman absensi.
 *   - BUKAN KONTROL_ASET (sudah dihitung otomatis dari data aset).
 *   - Punya bobot aktif (> 0) untuk jabatan target di kpi_weights.
 *   - Evaluator berwenang menilai komponen tsb (EvaluatorAuthorizationService).
 *
 * Rumus nilai bulanan = AVG seluruh skor harian (1-5) yang tersedia,
 * dikonversi ke skala 100 (avg / 5 * 100). Hari kosong di-skip.
 */
class ManualGridService
{
    private const ATTENDANCE_CODES = ['KEHADIRAN', 'KEBERSIHAN', 'SERAGAM', 'KEPATUHAN_SOP'];
    private const EXCLUDED_CODES = ['KONTROL_ASET'];

    /**
     * Komponen manual harian yang valid untuk target + evaluator pada periode.
     *
     * @return array list of:
     *   ['id','code','name','weight','scores' => [day=>raw], 'count','totalDays','avg','nilai']
     */
    public function components(int $targetId, int $evaluatorId, int $month, int $year): array
    {
        $target = (new ModelAuth())->where('ID_AKUN', $targetId)->first();
        if (!$target) {
            return [];
        }

        $positionId = (int)$target->ID_JABATAN;
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd   = date('Y-m-t', strtotime($monthStart));

        $rows = $this->db()
            ->table('kpi_components c')
            ->select('c.id, c.code, c.name, kw.weight')
            ->join('kpi_weights kw', 'kw.kpi_component_id = c.id', 'inner')
            ->where('c.type', 'manual')
            ->where('c.is_active', 1)
            ->whereNotIn('c.code', self::ATTENDANCE_CODES)
            ->whereNotIn('c.code', self::EXCLUDED_CODES)
            ->where('kw.position_id', $positionId)
            ->where('kw.weight >', 0)
            ->where('kw.effective_from <=', $monthEnd)
            ->groupStart()
                ->where('kw.effective_to', null)
                ->orWhere('kw.effective_to >=', $monthStart)
            ->groupEnd()
            ->orderBy('kw.weight', 'DESC')
            ->get()->getResult();

        $result = [];
        foreach ($rows as $r) {
            if (!EvaluatorAuthorizationService::canEvaluateComponent($evaluatorId, $targetId, (string)$r->code)) {
                continue;
            }

            $scores = $this->monthlyScores($targetId, (int)$r->id, $month, $year);
            $count     = count($scores);                              // jumlah hari terisi
            $sum       = array_sum($scores);
            // Denominator = jumlah hari KALENDER bulan (30/31): Kualitas dinilai
            // tiap hari tanpa OFF, hari kosong ikut sebagai 0.
            $totalDays = (int)date('t', strtotime($monthStart));
            $avg       = $totalDays > 0 ? round($sum / $totalDays, 3) : 0.0;
            $nilai     = $totalDays > 0 ? round(($sum / ($totalDays * 5)) * 100.0, 2) : 0.0;

            $result[] = [
                'id'        => (int)$r->id,
                'code'      => (string)$r->code,
                'name'      => (string)$r->name,
                'weight'    => (float)$r->weight,
                'scores'    => $scores,
                'count'     => $count,
                'totalDays' => $totalDays,
                'avg'       => $avg,
                'nilai'     => $nilai,
            ];
        }

        return $result;
    }

    /**
     * Simpan skor harian 1-5 utk satu pegawai pada satu tanggal.
     * Nilai kosong / '-' di-skip (tidak diubah). Raw 0 TIDAK diterima
     * karena Kualitas Pelayanan tidak punya konsep OFF (1-5 saja).
     *
     * @return array ['saved' => int, 'errors' => string[]]
     */
    public function saveDailyRatings(int $evaluatorId, int $targetId, int $month, int $year, int $day, array $scoresById): array
    {
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $jumlahHari = (int)date('t', strtotime($monthStart));

        if ($day < 1 || $day > $jumlahHari) {
            return ['saved' => 0, 'errors' => ['Tanggal yang dipilih tidak valid untuk bulan ini.']];
        }

        $target = (new ModelAuth())->where('ID_AKUN', $targetId)->first();
        if (!$target || (int)$target->STATUS_PEGAWAI !== 1) {
            return ['saved' => 0, 'errors' => ['Pegawai tidak ditemukan.']];
        }

        // Validasi komponen yang dikirim (harus masuk kriteria grid).
        $valid = $this->validComponentsById(array_keys($scoresById), (int)$target->ID_JABATAN, $evaluatorId, $targetId, $month, $year);

        $evaluationDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $svc            = new KpiEvaluationService();
        $saved          = 0;
        $errors         = [];

        foreach ($scoresById as $componentId => $value) {
            $value = trim((string)$value);
            if ($value === '') {
                continue; // '-' => jangan diubah
            }

            if (!isset($valid[(int)$componentId])) {
                $errors[] = 'Komponen KPI tidak valid untuk pegawai ini.';
                continue;
            }

            if (!ctype_digit($value) || (int)$value < 1 || (int)$value > 5) {
                $errors[] = 'Skor ' . $valid[(int)$componentId]['name'] . ' harus antara 1 sampai 5.';
                continue;
            }

            $result = $svc->recordEvaluation([
                'employee_id'      => $targetId,
                'kpi_component_id' => (int)$componentId,
                'evaluator_id'     => $evaluatorId,
                'evaluation_date'  => $evaluationDate,
                'raw_score'        => (int)$value,
                'max_score'        => 5,
                'notes'            => $valid[(int)$componentId]['name'] . ' (Skor: ' . (int)$value . '/5)',
            ]);

            if ($result['success']) {
                $saved++;
            } else {
                $errors = array_merge($errors, $result['errors']);
            }
        }

        return ['saved' => $saved, 'errors' => $errors];
    }

    /**
     * Peta skor harian [day => raw] utk satu pegawai + komponen + periode.
     * Hanya skor >= 1 (0/OFF/legacy tidak dimasukkan). Bila ada beberapa
     * evaluator pada tanggal yang sama, nilai harian = rata-rata.
     */
    protected function monthlyScores(int $employeeId, int $componentId, int $month, int $year): array
    {
        $rows = $this->db()
            ->table('kpi_evaluations')
            ->select('evaluation_date, raw_score')
            ->where('employee_id', $employeeId)
            ->where('kpi_component_id', $componentId)
            ->where('period_year', $year)
            ->where('period_month', str_pad((string)$month, 2, '0', STR_PAD_LEFT))
            ->get()->getResult();

        $perDay = [];
        foreach ($rows as $r) {
            $raw = (float)$r->raw_score;
            if ($raw < 1) {
                continue;
            }
            $day = (int)date('j', strtotime($r->evaluation_date));
            $perDay[$day][] = $raw;
        }

        $scores = [];
        foreach ($perDay as $day => $values) {
            $scores[$day] = (int)round(array_sum($values) / count($values));
        }
        ksort($scores);

        return $scores;
    }

    /**
     * Komponen valid (kriteria grid) utk daftar id.
     *
     * @return array [id => ['id','code','name','weight']]
     */
    protected function validComponentsById(array $ids, int $positionId, int $evaluatorId, int $targetId, int $month, int $year): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return [];
        }

        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd   = date('Y-m-t', strtotime($monthStart));

        $rows = $this->db()
            ->table('kpi_components c')
            ->select('c.id, c.code, c.name')
            ->join('kpi_weights kw', 'kw.kpi_component_id = c.id', 'inner')
            ->whereIn('c.id', $ids)
            ->where('c.type', 'manual')
            ->where('c.is_active', 1)
            ->whereNotIn('c.code', self::ATTENDANCE_CODES)
            ->whereNotIn('c.code', self::EXCLUDED_CODES)
            ->where('kw.position_id', $positionId)
            ->where('kw.weight >', 0)
            ->where('kw.effective_from <=', $monthEnd)
            ->groupStart()
                ->where('kw.effective_to', null)
                ->orWhere('kw.effective_to >=', $monthStart)
            ->groupEnd()
            ->get()->getResult();

        $valid = [];
        foreach ($rows as $r) {
            if (!EvaluatorAuthorizationService::canEvaluateComponent($evaluatorId, $targetId, (string)$r->code)) {
                continue;
            }
            $valid[(int)$r->id] = [
                'id'   => (int)$r->id,
                'code' => (string)$r->code,
                'name' => (string)$r->name,
            ];
        }

        return $valid;
    }

    protected function db(): \CodeIgniter\Database\BaseConnection
    {
        return \Config\Database::connect();
    }
}