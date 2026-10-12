<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * KPI Finance (jabatan 0 — Ira Kurniawati) untuk engine KPI pegawai.
 *
 * Sebelumnya jabatan 0 TIDAK punya baris kpi_weights sama sekali, sehingga
 * halaman /penilaian_kinerja menampilkan "Belum ada data KPI" untuk Finance.
 *
 * Finance berada di Head Office dan mengelola SEMUA cabang, jadi nilai tiap
 * KPI dihitung rata-rata antar-cabang (lihat FinanceKpiService). Yang
 * dikonfigurasi di sini hanya komponen + bobotnya.
 *
 * Bobot = keputusan bisnis Dashboard Finance (Config\Finance::$kpiWeights):
 *   kesehatan_uang 30, akurasi 20, cash_flow 15, hutang_piutang 10,
 *   rekonsiliasi 10, payroll 5, compliance 5, improvement 5  → total 100.
 *
 * Kode komponen diberi prefix FIN_ agar tidak bentrok dengan komponen divisi
 * lain (mis. IMPROVEMENT dipakai jabatan 43/44).
 */
class AddFinanceKpiJabatan0 extends Migration
{
    /** ID_JABATAN Finance (dashboard Finance/HO, role lintas unit). */
    private const FINANCE_POSITION = 0;

    /** code => [name, weight] — urutan = urutan tampil. */
    private const COMPONENTS = [
        'FIN_KESEHATAN_UANG' => ['name' => 'Kesehatan Uang per Cabang', 'weight' => 30],
        'FIN_AKURASI'        => ['name' => 'Akurasi Laporan Keuangan',  'weight' => 20],
        'FIN_CASH_FLOW'      => ['name' => 'Cash Flow',                 'weight' => 15],
        'FIN_HUTANG_PIUTANG' => ['name' => 'Hutang & Piutang',          'weight' => 10],
        'FIN_REKONSILIASI'   => ['name' => 'Rekonsiliasi',              'weight' => 10],
        'FIN_PAYROLL'        => ['name' => 'Payroll',                   'weight' => 5],
        'FIN_COMPLIANCE'     => ['name' => 'Compliance',                'weight' => 5],
        'FIN_IMPROVEMENT'    => ['name' => 'Improvement Finance',       'weight' => 5],
    ];

    public function up()
    {
        $codeToId = [];

        foreach (self::COMPONENTS as $code => $meta) {
            $existing = $this->db->table('kpi_components')
                ->where('code', $code)
                ->get()->getRow();

            if ($existing) {
                $this->db->table('kpi_components')
                    ->where('id', $existing->id)
                    ->update([
                        'name'                 => $meta['name'],
                        'type'                 => 'automatic',
                        'calculation_strategy' => '',
                        'category'             => 'finance',
                        'is_active'            => 1,
                    ]);
                $codeToId[$code] = (int) $existing->id;
                continue;
            }

            $this->db->table('kpi_components')->insert([
                'code'                 => $code,
                'name'                 => $meta['name'],
                'type'                 => 'automatic',
                'calculation_strategy' => '',
                'category'             => 'finance',
                'is_active'            => 1,
            ]);
            $codeToId[$code] = (int) $this->db->insertID();
        }

        // Bersihkan bobot lama posisi 0 (group kpi) agar tidak menumpuk.
        $this->db->table('kpi_weights')
            ->where('position_id', self::FINANCE_POSITION)
            ->where('weight_group', 'kpi')
            ->delete();

        $rows = [];
        foreach (self::COMPONENTS as $code => $meta) {
            $rows[] = [
                'kpi_component_id' => $codeToId[$code],
                'position_id'      => self::FINANCE_POSITION,
                'weight'           => (float) $meta['weight'],
                'weight_group'     => 'kpi',
                'effective_from'   => '2024-01-01',
                'effective_to'     => null,
                'created_by'       => null,
            ];
        }

        if (!empty($rows)) {
            $this->db->table('kpi_weights')->insertBatch($rows);
        }
    }

    public function down()
    {
        $ids = array_map(
            static fn ($r) => (int) $r->id,
            $this->db->table('kpi_components')
                ->whereIn('code', array_keys(self::COMPONENTS))
                ->get()->getResult()
        );

        if (empty($ids)) {
            return;
        }

        $this->db->table('kpi_weights')
            ->where('position_id', self::FINANCE_POSITION)
            ->whereIn('kpi_component_id', $ids)
            ->delete();

        $this->db->table('kpi_components')->whereIn('id', $ids)->delete();
    }
}
