<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * KPI Digital Marketing jabatan 43 (Kepala Divisi Digital Marketing).
 *
 * Komponen baru (total bobot 100):
 *   OMZET_GLOBAL        50  (Global omzet perusahaan; floor = batas_awal OMSET_TOKO)
 *   LEADS_QUALITY       15  (Leads + Kualitas dari Performa Ads; target 3.000)
 *   CONVERSION          10  (Closing CS / Lead CS)
 *   CPL                 10  (Budget Ads / Datang & Closing CS)
 *   CAMPAIGN_PERFORMANCE 5  (Performa Ads ber-campaign valid)
 *   REPORTING            5  (Campaign selesai yang memiliki report)
 *   IMPROVEMENT          5  (mekanisme Improvement Multimedia, reuse)
 *
 * Komponen lama jabatan 43 (LEAD/CUSTOMER/CONVERSION_MARKETING, OMZET_MARKETING,
 * ROAS_MARKETING, CHANNEL_GROWTH) nonaktifkan (is_active = 0) — komponen ini
 * hanya dipakai posisi 43 sehingga aman dinonaktifkan. CPL & IMPROVEMENT
 * tetap aktif (CPL dipakai ulang, IMPROVEMENT dipakai jabatan 44 juga).
 *
 * Target tidak di-hardcode di service: ditulis ke kpi_targets
 * (LEADS_QUALITY = 3.000, CONVERSION = 30%, CPL = Rp250.000).
 */
class UpdateKpiJabatan43DigitalMarketing extends Migration
{
    private const NEW_COMPONENTS = [
        ['code' => 'OMZET_GLOBAL',         'name' => 'Omzet Global Perusahaan'],
        ['code' => 'LEADS_QUALITY',        'name' => 'Leads & Kualitas Leads'],
        ['code' => 'CONVERSION',           'name' => 'Conversion'],
        ['code' => 'CAMPAIGN_PERFORMANCE', 'name' => 'Campaign Performance'],
        ['code' => 'REPORTING',            'name' => 'Reporting'],
    ];

    private const WEIGHTS_43 = [
        'OMZET_GLOBAL'        => 50,
        'LEADS_QUALITY'       => 15,
        'CONVERSION'          => 10,
        'CPL'                 => 10,
        'CAMPAIGN_PERFORMANCE' => 5,
        'REPORTING'           => 5,
        'IMPROVEMENT'         => 5,
    ];

    private const TARGETS = [
        ['code' => 'LEADS_QUALITY', 'value' => 3000],
        ['code' => 'CONVERSION',    'value' => 30],
        ['code' => 'CPL',           'value' => 250000],
    ];

    private const DEACTIVATE = [
        'LEAD_MARKETING',
        'CUSTOMER_MARKETING',
        'CONVERSION_MARKETING',
        'OMZET_MARKETING',
        'ROAS_MARKETING',
        'CHANNEL_GROWTH',
    ];

    public function up()
    {
        $codeToId = [];
        foreach (self::NEW_COMPONENTS as $c) {
            $existing = $this->db->table('kpi_components')
                ->where('code', $c['code'])
                ->get()->getRow();
            if ($existing) {
                $this->db->table('kpi_components')
                    ->where('id', $existing->id)
                    ->update(['name' => $c['name'], 'type' => 'automatic', 'calculation_strategy' => '', 'is_active' => 1]);
                $codeToId[$c['code']] = (int)$existing->id;
                continue;
            }
            $this->db->table('kpi_components')->insert([
                'code'                 => $c['code'],
                'name'                 => $c['name'],
                'type'                 => 'automatic',
                'calculation_strategy' => '',
                'is_active'            => 1,
            ]);
            $codeToId[$c['code']] = (int)$this->db->insertID();
        }

        // CPL & IMPROVEMENT sudah ada — pastikan aktif & di-ambil id-nya.
        foreach (['CPL', 'IMPROVEMENT'] as $shared) {
            $row = $this->db->table('kpi_components')->where('code', $shared)->get()->getRow();
            if ($row) {
                $codeToId[$shared] = (int)$row->id;
            }
        }

        // Bersihkan bobot lama posisi 43 (group kpi) — prevent double scoring.
        $this->db->table('kpi_weights')
            ->where('position_id', 43)
            ->where('weight_group', 'kpi')
            ->delete();

        $rows = [];
        foreach (self::WEIGHTS_43 as $code => $weight) {
            if (!isset($codeToId[$code])) {
                continue;
            }
            $rows[] = [
                'kpi_component_id' => $codeToId[$code],
                'position_id'      => 43,
                'weight'           => (float)$weight,
                'weight_group'     => 'kpi',
                'effective_from'   => '2024-01-01',
                'effective_to'     => null,
                'created_by'       => null,
            ];
        }
        if (!empty($rows)) {
            $this->db->table('kpi_weights')->insertBatch($rows);
        }

        // Nonaktifkan komponen lama (hanya dipakai posisi 43).
        $this->db->table('kpi_components')
            ->whereIn('code', self::DEACTIVATE)
            ->update(['is_active' => 0]);

        // Target DB utk komponen divisi (unit 50 = Head Office).
        foreach (self::TARGETS as $t) {
            foreach ([50, null] as $unitId) {
                $exists = $this->db->table('kpi_targets')
                    ->where('kpi_component_id', $codeToId[$t['code']])
                    ->where('unit_id', $unitId)
                    ->where('context', 'default')
                    ->where('effective_from', '2024-01-01')
                    ->get()->getRow();
                if ($exists) {
                    continue;
                }
                $this->db->table('kpi_targets')->insert([
                    'kpi_component_id' => $codeToId[$t['code']],
                    'unit_id'          => $unitId,
                    'position_id'      => 43,
                    'target_value'     => (float)$t['value'],
                    'batas_awal'       => null,
                    'context'          => 'default',
                    'period_type'      => 'monthly',
                    'period_month'     => null,
                    'effective_from'   => '2024-01-01',
                    'effective_to'     => null,
                    'created_by'       => null,
                ]);
            }
        }
    }

    public function down()
    {
        $newIds = $this->componentIds(array_keys(self::WEIGHTS_43));
        $this->db->table('kpi_weights')
            ->where('position_id', 43)
            ->where('weight_group', 'kpi')
            ->whereIn('kpi_component_id', $newIds)
            ->delete();

        $old = $this->componentIds(self::DEACTIVATE);
        $weights = ['LEAD_MARKETING' => 15, 'CUSTOMER_MARKETING' => 15, 'CONVERSION_MARKETING' => 15, 'CPL' => 10, 'OMZET_MARKETING' => 20, 'ROAS_MARKETING' => 15, 'CHANNEL_GROWTH' => 10];
        $rows = [];
        foreach ($weights as $code => $weight) {
            $id = (int)$this->db->table('kpi_components')->where('code', $code)->get()->getRow()->id;
            $rows[] = [
                'kpi_component_id' => $id,
                'position_id'      => 43,
                'weight'           => (float)$weight,
                'weight_group'     => 'kpi',
                'effective_from'   => '2024-01-01',
                'effective_to'     => null,
                'created_by'       => null,
            ];
        }
        if (!empty($rows)) {
            $this->db->table('kpi_weights')->insertBatch($rows);
        }

        $this->db->table('kpi_components')->whereIn('code', self::DEACTIVATE)->update(['is_active' => 1]);

        $targetCodes = array_column(self::TARGETS, 'code');
        $targetIds = $this->componentIds($targetCodes);
        $this->db->table('kpi_targets')
            ->whereIn('kpi_component_id', $targetIds)
            ->where('period_type', 'monthly')
            ->where('context', 'default')
            ->groupStart()
                ->where('unit_id', 50)
                ->orWhere('unit_id IS NULL')
            ->groupEnd()
            ->delete();

        unset($old);
    }

    private function componentIds(array $codes): array
    {
        $rows = $this->db->table('kpi_components')->whereIn('code', $codes)->get()->getResult();
        return array_map(static fn($r) => (int)$r->id, $rows);
    }
}