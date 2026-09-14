<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ganti KPI lama jabatan 43 (Kepala Divisi) & 44 (Multimedia) dengan KPI baru.
 *
 * - Kepala Divisi : KPI Marketing (Lead, Customer, Konversi, CPL, Omzet, ROAS,
 *                   Pertumbuhan Channel).
 * - Multimedia    : KPI Konten (Jumlah, Deadline, Kualitas, Brand, Performa,
 *                   Pertumbuhan Channel).
 *
 * Komponen lama (OMSET_TOKO/BUDGETING/ROAS utk 43; OMSET_TOKO, FEED MINGGUAN /
 * FEED PL / VIDEO / STORY / TESTIMONI utk 44) dilepas dari bobot posisi tsb.
 * Bobot baru total 100.
 * Non-destruktif untuk data lain: komponen lama tetap ada di kpi_components
 * dan masih dipakai posisi lain.
 */
class AddNewKpiJabatanKadivMultimedia extends Migration
{
    private const WEIGHTS_43 = [
        'LEAD_MARKETING'     => 15,
        'CUSTOMER_MARKETING' => 15,
        'CONVERSION_MARKETING' => 15,
        'CPL'                => 10,
        'OMZET_MARKETING'    => 20,
        'ROAS_MARKETING'     => 15,
        'CHANNEL_GROWTH'     => 10,
    ];

    private const WEIGHTS_44 = [
        'KONTEN_JUMLAH'    => 15,
        'KONTEN_DEADLINE'  => 15,
        'KONTEN_KUALITAS'  => 25,
        'KONTEN_BRAND'     => 15,
        'KONTEN_PERFORMA'  => 20,
        'CHANNEL_GROWTH'   => 10,
    ];

    public function up()
    {
        // Komponen baru (marketing + konten). CHANNEL_GROWTH dipakai bersama.
        $components = [
            ['code' => 'LEAD_MARKETING',     'name' => 'Lead Marketing'],
            ['code' => 'CUSTOMER_MARKETING', 'name' => 'Customer Marketing'],
            ['code' => 'CONVERSION_MARKETING','name' => 'Konversi Marketing'],
            ['code' => 'CPL',                'name' => 'CPL (Cost Per Lead)'],
            ['code' => 'OMZET_MARKETING',    'name' => 'Omzet Marketing'],
            ['code' => 'ROAS_MARKETING',     'name' => 'ROAS Marketing'],
            ['code' => 'KONTEN_JUMLAH',      'name' => 'Jumlah Konten'],
            ['code' => 'KONTEN_DEADLINE',    'name' => 'Deadline Konten'],
            ['code' => 'KONTEN_KUALITAS',    'name' => 'Kualitas Konten'],
            ['code' => 'KONTEN_BRAND',       'name' => 'Konsistensi Brand'],
            ['code' => 'KONTEN_PERFORMA',    'name' => 'Performa Konten'],
            ['code' => 'CHANNEL_GROWTH',     'name' => 'Pertumbuhan Channel'],
        ];

        $codeToId = [];
        foreach ($components as $c) {
            $existing = $this->db->table('kpi_components')
                ->where('code', $c['code'])
                ->get()->getRow();
            if ($existing) {
                $codeToId[$c['code']] = (int)$existing->id;
                continue;
            }
            $this->db->table('kpi_components')->insert([
                'code'                => $c['code'],
                'name'                => $c['name'],
                'type'                => 'automatic',
                'calculation_strategy'=> '',
                'is_active'           => 1,
            ]);
            $codeToId[$c['code']] = (int)$this->db->insertID();
        }

        // Komponen lama yang dilepas utk posisi 43 & 44.
        $oldFor43 = $this->componentIds(['OMSET_TOKO', 'BUDGETING', 'ROAS']);
        $oldFor44 = $this->componentIds(['OMSET_TOKO', 'FEED_MINGGUAN', 'FEED_PL', 'VIDEO', 'STORY', 'TESTIMONI']);

        // Bersihkan bobot lama + (bila migration dijalankan ulang) bobot baru, lalu isi ulang.
        $this->db->table('kpi_weights')
            ->where('position_id', 43)
            ->where('weight_group', 'kpi')
            ->whereIn('kpi_component_id', array_merge($oldFor43, array_values($codeToId)))
            ->delete();
        $this->db->table('kpi_weights')
            ->where('position_id', 44)
            ->where('weight_group', 'kpi')
            ->whereIn('kpi_component_id', array_merge($oldFor44, array_values($codeToId)))
            ->delete();

        $this->insertWeights(43, self::WEIGHTS_43, $codeToId);
        $this->insertWeights(44, self::WEIGHTS_44, $codeToId);
    }

    public function down()
    {
        $codeToIdNew = $this->componentIds([
            'LEAD_MARKETING', 'CUSTOMER_MARKETING', 'CONVERSION_MARKETING', 'CPL',
            'OMZET_MARKETING', 'ROAS_MARKETING', 'KONTEN_JUMLAH', 'KONTEN_DEADLINE',
            'KONTEN_KUALITAS', 'KONTEN_BRAND', 'KONTEN_PERFORMA', 'CHANNEL_GROWTH',
        ]);

        // Hapus bobot baru.
        $this->db->table('kpi_weights')
            ->whereIn('position_id', [43, 44])
            ->where('weight_group', 'kpi')
            ->whereIn('kpi_component_id', $codeToIdNew)
            ->delete();

        // Pulihkan bobot lama.
        $old43 = $this->componentIds(['OMSET_TOKO', 'BUDGETING', 'ROAS']);
        $weights43 = ['OMSET_TOKO' => 70, 'BUDGETING' => 15, 'ROAS' => 15];
        $this->insertWeightsByIds(43, $old43, $weights43);

        $old44 = $this->componentIds(['OMSET_TOKO', 'FEED_MINGGUAN', 'FEED_PL', 'VIDEO', 'STORY', 'TESTIMONI']);
        $weights44 = ['OMSET_TOKO' => 30, 'FEED_MINGGUAN' => 15, 'FEED_PL' => 15, 'VIDEO' => 20, 'STORY' => 10, 'TESTIMONI' => 10];
        $this->insertWeightsByIds(44, $old44, $weights44);
    }

    private function componentIds(array $codes): array
    {
        $rows = $this->db->table('kpi_components')->whereIn('code', $codes)->get()->getResult();
        return array_map(static fn($r) => (int)$r->id, $rows);
    }

    private function insertWeights(int $positionId, array $codeWeights, array $codeToId): void
    {
        $rows = [];
        foreach ($codeWeights as $code => $weight) {
            $rows[] = [
                'kpi_component_id' => $codeToId[$code],
                'position_id'      => $positionId,
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
    }

    private function insertWeightsByIds(int $positionId, array $ids, array $codeWeights): void
    {
        $codeToId = [];
        $codes = array_keys($codeWeights);
        foreach ($ids as $i => $id) {
            $codeToId[$codes[$i]] = $id;
        }
        $this->insertWeights($positionId, $codeWeights, $codeToId);
    }
}