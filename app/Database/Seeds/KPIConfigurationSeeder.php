<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class KPIConfigurationSeeder extends Seeder
{
    public function run()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->table('salary_structures')->truncate();
        $this->db->table('salary_components')->truncate();
        $this->db->table('kpi_targets')->truncate();
        $this->db->table('kpi_weights')->truncate();
        $this->db->table('kpi_components')->truncate();
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        $this->seedKPIComponents();
        $this->seedKPIWeights();
        $this->seedKPITargets();
        $this->seedSalaryComponents();
        $this->seedSalaryStructures();
    }

    private function seedKPIComponents()
    {
        $data = [
            [
                'code' => 'OMSET_TOKO',
                'name' => 'Omset Toko',
                'description' => 'Total revenue from store sales',
                'type' => 'automatic',
                'category' => 'sales',
                'unit_of_measure' => 'IDR',
                'calculation_strategy' => 'omset_toko',
                'is_active' => 1,
            ],
            [
                'code' => 'OMSET_TEKNISI',
                'name' => 'Omset Teknisi',
                'description' => 'Revenue from service/technical work',
                'type' => 'automatic',
                'category' => 'sales',
                'unit_of_measure' => 'IDR',
                'calculation_strategy' => 'omset_teknisi',
                'is_active' => 1,
            ],
            [
                'code' => 'CUSTOMER_COUNT',
                'name' => 'Total Pelanggan',
                'description' => 'Count of unique customers served',
                'type' => 'automatic',
                'category' => 'sales',
                'unit_of_measure' => 'count',
                'calculation_strategy' => 'customer_count',
                'is_active' => 1,
            ],
            [
                'code' => 'CLOSING_RATE',
                'name' => 'Closing Rate',
                'description' => 'Conversion rate from quote to sale',
                'type' => 'manual',
                'category' => 'sales',
                'unit_of_measure' => 'percent',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'UPSELLING',
                'name' => 'Upselling',
                'description' => 'Additional sales to existing customers',
                'type' => 'manual',
                'category' => 'sales',
                'unit_of_measure' => 'count',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'FOLLOWUP',
                'name' => 'Follow Up',
                'description' => 'Customer follow-up activities',
                'type' => 'manual',
                'category' => 'sales',
                'unit_of_measure' => 'count',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'TUTUP_KASIR',
                'name' => 'Tutup Kasir',
                'description' => 'Cash register closing accuracy',
                'type' => 'automatic',
                'category' => 'operational',
                'unit_of_measure' => 'count',
                'calculation_strategy' => 'tutup_kasir',
                'is_active' => 1,
            ],
            [
                'code' => 'STOK_OPNAME',
                'name' => 'Stok Opname',
                'description' => 'Inventory stock count accuracy',
                'type' => 'automatic',
                'category' => 'operational',
                'unit_of_measure' => 'count',
                'calculation_strategy' => 'stok_opname',
                'is_active' => 1,
            ],
            [
                'code' => 'KEHADIRAN',
                'name' => 'Kehadiran',
                'description' => 'Employee attendance score',
                'type' => 'manual',
                'category' => 'behavior',
                'unit_of_measure' => 'percent',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'KEBERSIHAN',
                'name' => 'Kebersihan',
                'description' => 'Workplace cleanliness score',
                'type' => 'manual',
                'category' => 'behavior',
                'unit_of_measure' => 'percent',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'SERAGAM',
                'name' => 'Seragam',
                'description' => 'Uniform compliance score',
                'type' => 'manual',
                'category' => 'behavior',
                'unit_of_measure' => 'percent',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'KEPATUHAN_SOP',
                'name' => 'Kepatuhan SOP',
                'description' => 'Standard Operating Procedure compliance',
                'type' => 'manual',
                'category' => 'behavior',
                'unit_of_measure' => 'percent',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'OPERASIONAL',
                'name' => 'Operasional',
                'description' => 'Operational excellence score',
                'type' => 'manual',
                'category' => 'operational',
                'unit_of_measure' => 'percent',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'DIVISI',
                'name' => 'Divisi',
                'description' => 'Divisional/team performance',
                'type' => 'manual',
                'category' => 'operational',
                'unit_of_measure' => 'percent',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'BUDGETING',
                'name' => 'Budgeting',
                'description' => 'Marketing budget management',
                'type' => 'manual',
                'category' => 'financial',
                'unit_of_measure' => 'percent',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'ROAS',
                'name' => 'ROAS',
                'description' => 'Return on Ad Spend',
                'type' => 'manual',
                'category' => 'financial',
                'unit_of_measure' => 'ratio',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'FEED_MINGGUAN',
                'name' => 'Feed Mingguan',
                'description' => 'Weekly feed content',
                'type' => 'manual',
                'category' => 'marketing',
                'unit_of_measure' => 'count',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'FEED_PL',
                'name' => 'Feed PL',
                'description' => 'Product list feed content',
                'type' => 'manual',
                'category' => 'marketing',
                'unit_of_measure' => 'count',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'VIDEO',
                'name' => 'Video',
                'description' => 'Video content creation',
                'type' => 'manual',
                'category' => 'marketing',
                'unit_of_measure' => 'count',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'STORY',
                'name' => 'Story',
                'description' => 'Story/social media posts',
                'type' => 'manual',
                'category' => 'marketing',
                'unit_of_measure' => 'count',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'TESTIMONI',
                'name' => 'Testimoni',
                'description' => 'Customer testimonials collected',
                'type' => 'manual',
                'category' => 'marketing',
                'unit_of_measure' => 'count',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'BUG_MINOR',
                'name' => 'Bug Minor',
                'description' => 'Minor bug fixes',
                'type' => 'manual',
                'category' => 'technical',
                'unit_of_measure' => 'count',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'BUG_OPERASIONAL',
                'name' => 'Bug Operasional',
                'description' => 'Operational bug fixes',
                'type' => 'manual',
                'category' => 'technical',
                'unit_of_measure' => 'count',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'ECOMMERCE',
                'name' => 'Ecommerce',
                'description' => 'E-commerce platform development',
                'type' => 'manual',
                'category' => 'technical',
                'unit_of_measure' => 'count',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'FITUR',
                'name' => 'Fitur',
                'description' => 'New feature development',
                'type' => 'manual',
                'category' => 'technical',
                'unit_of_measure' => 'count',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'OMSET_CABANG',
                'name' => 'Omzet Cabang',
                'description' => 'Gross profit cabang/toko (legacy omset cabang)',
                'type' => 'automatic',
                'category' => 'sales',
                'unit_of_measure' => 'IDR',
                'calculation_strategy' => 'omset_cabang',
                'is_active' => 1,
            ],
            [
                'code' => 'PRODUKTIVITAS_TEAM',
                'name' => 'Produktivitas Team',
                'description' => 'Rata-rata skor KPI Teknisi & Admin pada unit yang sama',
                'type' => 'automatic',
                'category' => 'team',
                'unit_of_measure' => 'percent',
                'calculation_strategy' => 'produktivitas_team',
                'is_active' => 1,
            ],
            [
                'code' => 'KUALITAS_PELAYANAN',
                'name' => 'Kualitas Pelayanan',
                'description' => 'Penilaian manual oleh SPV Area',
                'type' => 'manual',
                'category' => 'service',
                'unit_of_measure' => 'percent',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
            [
                'code' => 'KONTROL_ASET',
                'name' => 'Kontrol Aset',
                'description' => 'Aset sesuai / total aset wajib x 100, diaudit oleh SPV',
                'type' => 'manual',
                'category' => 'operational',
                'unit_of_measure' => 'percent',
                'calculation_strategy' => NULL,
                'is_active' => 1,
            ],
        ];

        $this->db->table('kpi_components')->insertBatch($data);
    }

    private function seedKPIWeights()
    {
        $components = $this->db->table('kpi_components')->select('id, code')->get()->getResultArray();
        $componentMap = array_column($components, 'id', 'code');

        // KPI detail weights (weight_group = 'kpi') — sum = 100 per position
        $data = [
            // Admin (35)
            ['kpi_component_id' => $componentMap['OMSET_TOKO'], 'position_id' => 35, 'weight' => 70, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['TUTUP_KASIR'], 'position_id' => 35, 'weight' => 10, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['STOK_OPNAME'], 'position_id' => 35, 'weight' => 10, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['KEHADIRAN'], 'position_id' => 35, 'weight' => 10, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],

            // Teknisi (36)
            ['kpi_component_id' => $componentMap['OMSET_TOKO'], 'position_id' => 36, 'weight' => 70, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['OMSET_TEKNISI'], 'position_id' => 36, 'weight' => 15, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['CUSTOMER_COUNT'], 'position_id' => 36, 'weight' => 15, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],

            // Kepala Toko (41)
            ['kpi_component_id' => $componentMap['OMSET_CABANG'], 'position_id' => 41, 'weight' => 45, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['CUSTOMER_COUNT'], 'position_id' => 41, 'weight' => 10, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['PRODUKTIVITAS_TEAM'], 'position_id' => 41, 'weight' => 15, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['KUALITAS_PELAYANAN'], 'position_id' => 41, 'weight' => 15, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['KONTROL_ASET'], 'position_id' => 41, 'weight' => 5, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['STOK_OPNAME'], 'position_id' => 41, 'weight' => 10, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],

            // SPV (40)
            ['kpi_component_id' => $componentMap['OMSET_TOKO'], 'position_id' => 40, 'weight' => 70, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['CUSTOMER_COUNT'], 'position_id' => 40, 'weight' => 10, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['OPERASIONAL'], 'position_id' => 40, 'weight' => 10, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['DIVISI'], 'position_id' => 40, 'weight' => 10, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],

            // Customer Service (42)
            ['kpi_component_id' => $componentMap['OMSET_TOKO'], 'position_id' => 42, 'weight' => 70, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['CLOSING_RATE'], 'position_id' => 42, 'weight' => 10, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['UPSELLING'], 'position_id' => 42, 'weight' => 10, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['FOLLOWUP'], 'position_id' => 42, 'weight' => 10, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],

            // Pengiklan (43)
            ['kpi_component_id' => $componentMap['BUDGETING'], 'position_id' => 43, 'weight' => 15, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['ROAS'], 'position_id' => 43, 'weight' => 15, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['OMSET_TOKO'], 'position_id' => 43, 'weight' => 70, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],

            // Multimedia (44)
            ['kpi_component_id' => $componentMap['OMSET_TOKO'], 'position_id' => 44, 'weight' => 30, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['FEED_PL'], 'position_id' => 44, 'weight' => 15, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['FEED_MINGGUAN'], 'position_id' => 44, 'weight' => 15, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['VIDEO'], 'position_id' => 44, 'weight' => 20, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['STORY'], 'position_id' => 44, 'weight' => 10, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['TESTIMONI'], 'position_id' => 44, 'weight' => 10, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],

            // IT (45)
            ['kpi_component_id' => $componentMap['OMSET_TOKO'], 'position_id' => 45, 'weight' => 30, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['BUG_MINOR'], 'position_id' => 45, 'weight' => 10, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['BUG_OPERASIONAL'], 'position_id' => 45, 'weight' => 25, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['ECOMMERCE'], 'position_id' => 45, 'weight' => 15, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
            ['kpi_component_id' => $componentMap['FITUR'], 'position_id' => 45, 'weight' => 20, 'weight_group' => 'kpi', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL],
        ];

        // Absen detail weights (weight_group = 'absen') — sum = 100 per position
        $absenData = [];
        foreach ([35, 36, 41, 42, 43, 44, 45] as $positionId) {
            $absenData[] = ['kpi_component_id' => $componentMap['KEHADIRAN'], 'position_id' => $positionId, 'weight' => 40, 'weight_group' => 'absen', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL];
            $absenData[] = ['kpi_component_id' => $componentMap['KEBERSIHAN'], 'position_id' => $positionId, 'weight' => 20, 'weight_group' => 'absen', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL];
            $absenData[] = ['kpi_component_id' => $componentMap['SERAGAM'], 'position_id' => $positionId, 'weight' => 20, 'weight_group' => 'absen', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL];
            $absenData[] = ['kpi_component_id' => $componentMap['KEPATUHAN_SOP'], 'position_id' => $positionId, 'weight' => 20, 'weight_group' => 'absen', 'effective_from' => '2024-01-01', 'effective_to' => NULL, 'created_by' => NULL];
        }

        $data = array_merge($data, $absenData);

        $this->db->table('kpi_weights')->insertBatch($data);
    }

    private function seedKPITargets()
    {
        $components = $this->db->table('kpi_components')->select('id, code')->get()->getResultArray();
        $componentMap = array_column($components, 'id', 'code');
        $units = $this->db->table('unit')->select('idunit')->get()->getResultArray();

        // Threshold OMSET_TOKO per unit + context.
        // Context 'gaji' vs 'penilaian_kinerja'/'slip_gaji' punya nilai berbeda — dipertahankan.
        $omsetThreshold = [
            1 => [ // Unit 1
                'gaji'       => ['batas_awal' => 30000000, 'batas_kedua' => 35000000, 'batas_ketiga' => 40000000, 'batas_keempat' => 45000000, 'target' => 50000000],
                'penilaian'  => ['batas_awal' => 35000000, 'batas_kedua' => 40000000, 'batas_ketiga' => 45000000, 'batas_keempat' => 50000000, 'target' => 55000000],
            ],
            2 => [ // Unit 2
                'gaji'       => ['batas_awal' => 18000000, 'batas_kedua' => 22000000, 'batas_ketiga' => 26000000, 'batas_keempat' => 30000000, 'target' => 35000000],
                'penilaian'  => ['batas_awal' => 18000000, 'batas_kedua' => 22000000, 'batas_ketiga' => 26000000, 'batas_keempat' => 30000000, 'target' => 35000000],
            ],
            3 => [ // Unit 3
                'gaji'       => ['batas_awal' => 40000000, 'batas_kedua' => 45000000, 'batas_ketiga' => 50000000, 'batas_keempat' => 55000000, 'target' => 60000000],
                'penilaian'  => ['batas_awal' => 40000000, 'batas_kedua' => 45000000, 'batas_ketiga' => 50000000, 'batas_keempat' => 55000000, 'target' => 60000000],
            ],
            4 => [ // Unit 4
                'gaji'       => ['batas_awal' => 18000000, 'batas_kedua' => 22000000, 'batas_ketiga' => 26000000, 'batas_keempat' => 30000000, 'target' => 35000000],
                'penilaian'  => ['batas_awal' => 35000000, 'batas_kedua' => 40000000, 'batas_ketiga' => 45000000, 'batas_keempat' => 50000000, 'target' => 55000000],
            ],
        ];

        // Target per KPI per unit (customer/closing/upselling/followup/roas/tutup_kasir/stok_opname)
        $kpiTarget = [
            'CUSTOMER_COUNT' => [1 => 130, 2 => 118, 3 => 210, 4 => 118],
            'CLOSING_RATE'   => [1 => 111, 2 => 96,  3 => 188, 4 => 96],
            'UPSELLING'      => [1 => 14,  2 => 14,  3 => 27,  4 => 14],
            'FOLLOWUP'       => [1 => 100, 2 => 80,  3 => 60,  4 => 80],
            'ROAS'           => [1 => 5,   2 => 4,   3 => 3,   4 => 5],
            'TUTUP_KASIR'    => [1 => 30,  2 => 30,  3 => 30,  4 => 30],
            'STOK_OPNAME'    => [1 => 4,   2 => 4,   3 => 4,   4 => 4],
            'PRODUKTIVITAS_TEAM' => [1 => 100, 2 => 100, 3 => 100, 4 => 100],
        ];

        $data = [];
        foreach ($units as $unit) {
            $uid = $unit['idunit'];
            $t = $omsetThreshold[$uid] ?? $omsetThreshold[1];

            // OMSET_TOKO context gaji
            $data[] = [
                'kpi_component_id' => $componentMap['OMSET_TOKO'],
                'unit_id' => $uid,
                'position_id' => NULL,
                'context' => 'gaji',
                'target_value' => $t['gaji']['target'],
                'batas_awal' => $t['gaji']['batas_awal'],
                'batas_kedua' => $t['gaji']['batas_kedua'],
                'batas_ketiga' => $t['gaji']['batas_ketiga'],
                'batas_keempat' => $t['gaji']['batas_keempat'],
                'period_type' => 'monthly',
                'period_month' => NULL,
                'effective_from' => '2024-01-01',
                'effective_to' => NULL,
                'created_by' => NULL,
            ];

            // OMSET_TOKO context penilaian_kinerja/slip_gaji
            $data[] = [
                'kpi_component_id' => $componentMap['OMSET_TOKO'],
                'unit_id' => $uid,
                'position_id' => NULL,
                'context' => 'penilaian_kinerja',
                'target_value' => $t['penilaian']['target'],
                'batas_awal' => $t['penilaian']['batas_awal'],
                'batas_kedua' => $t['penilaian']['batas_kedua'],
                'batas_ketiga' => $t['penilaian']['batas_ketiga'],
                'batas_keempat' => $t['penilaian']['batas_keempat'],
                'period_type' => 'monthly',
                'period_month' => NULL,
                'effective_from' => '2024-01-01',
                'effective_to' => NULL,
                'created_by' => NULL,
            ];

            // OMSET_CABANG: sama dgn OMSET_TOKO (omzet cabang/toko sendiri)
            $data[] = [
                'kpi_component_id' => $componentMap['OMSET_CABANG'],
                'unit_id' => $uid,
                'position_id' => NULL,
                'context' => 'gaji',
                'target_value' => $t['gaji']['target'],
                'batas_awal' => $t['gaji']['batas_awal'],
                'batas_kedua' => $t['gaji']['batas_kedua'],
                'batas_ketiga' => $t['gaji']['batas_ketiga'],
                'batas_keempat' => $t['gaji']['batas_keempat'],
                'period_type' => 'monthly',
                'period_month' => NULL,
                'effective_from' => '2024-01-01',
                'effective_to' => NULL,
                'created_by' => NULL,
            ];
            $data[] = [
                'kpi_component_id' => $componentMap['OMSET_CABANG'],
                'unit_id' => $uid,
                'position_id' => NULL,
                'context' => 'penilaian_kinerja',
                'target_value' => $t['penilaian']['target'],
                'batas_awal' => $t['penilaian']['batas_awal'],
                'batas_kedua' => $t['penilaian']['batas_kedua'],
                'batas_ketiga' => $t['penilaian']['batas_ketiga'],
                'batas_keempat' => $t['penilaian']['batas_keempat'],
                'period_type' => 'monthly',
                'period_month' => NULL,
                'effective_from' => '2024-01-01',
                'effective_to' => NULL,
                'created_by' => NULL,
            ];

            // OMSET_TEKNISI: target per teknisi = target omset cabang / 2
            // (jumlah teknisi per cabang diasumsikan = 2 konstanta)
            if (isset($componentMap['OMSET_TEKNISI'])) {
                $data[] = [
                    'kpi_component_id' => $componentMap['OMSET_TEKNISI'],
                    'unit_id' => $uid,
                    'position_id' => NULL,
                    'context' => 'gaji',
                    'target_value' => $t['gaji']['target'] / 2,
                    'batas_awal' => NULL,
                    'batas_kedua' => NULL,
                    'batas_ketiga' => NULL,
                    'batas_keempat' => NULL,
                    'period_type' => 'monthly',
                    'period_month' => NULL,
                    'effective_from' => '2024-01-01',
                    'effective_to' => NULL,
                    'created_by' => NULL,
                ];
                $data[] = [
                    'kpi_component_id' => $componentMap['OMSET_TEKNISI'],
                    'unit_id' => $uid,
                    'position_id' => NULL,
                    'context' => 'penilaian_kinerja',
                    'target_value' => $t['penilaian']['target'] / 2,
                    'batas_awal' => NULL,
                    'batas_kedua' => NULL,
                    'batas_ketiga' => NULL,
                    'batas_keempat' => NULL,
                    'period_type' => 'monthly',
                    'period_month' => NULL,
                    'effective_from' => '2024-01-01',
                    'effective_to' => NULL,
                    'created_by' => NULL,
                ];
            }

            // Target KPI lain
            foreach ($kpiTarget as $code => $perUnit) {
                if (!isset($componentMap[$code])) {
                    continue;
                }
                $data[] = [
                    'kpi_component_id' => $componentMap[$code],
                    'unit_id' => $uid,
                    'position_id' => NULL,
                    'context' => 'default',
                    'target_value' => $perUnit[$uid] ?? $perUnit[1],
                    'batas_awal' => NULL,
                    'batas_kedua' => NULL,
                    'batas_ketiga' => NULL,
                    'batas_keempat' => NULL,
                    'period_type' => 'monthly',
                    'period_month' => NULL,
                    'effective_from' => '2024-01-01',
                    'effective_to' => NULL,
                    'created_by' => NULL,
                ];
            }
        }

        $this->db->table('kpi_targets')->insertBatch($data);
    }

    private function seedSalaryComponents()
    {
        $data = [
            ['code' => 'GAJI_POKOK', 'name' => 'Gaji Pokok', 'type' => 'base', 'description' => 'Base monthly salary', 'default_value' => 1500000, 'is_active' => 1, 'is_configurable' => 1],
            ['code' => 'TUNJANGAN_KINERJA', 'name' => 'Tunjangan Kinerja', 'type' => 'allowance', 'description' => 'Performance allowance', 'default_value' => 250000, 'is_active' => 1, 'is_configurable' => 1],
            ['code' => 'TUNJANGAN_ABSEN', 'name' => 'Tunjangan Absensi', 'type' => 'allowance', 'description' => 'Attendance allowance', 'default_value' => 250000, 'is_active' => 1, 'is_configurable' => 1],
            ['code' => 'TUNJANGAN_PENEMPATAN', 'name' => 'Tunjangan Penempatan', 'type' => 'allowance', 'description' => 'Placement/location allowance', 'default_value' => 0, 'is_active' => 1, 'is_configurable' => 1],
            ['code' => 'INSENTIF', 'name' => 'Insentif', 'type' => 'incentive', 'description' => 'Performance incentive', 'default_value' => 0, 'is_active' => 1, 'is_configurable' => 0],
            ['code' => 'BON', 'name' => 'Bon/Advance', 'type' => 'deduction', 'description' => 'Employee advance/bon', 'default_value' => 0, 'is_active' => 1, 'is_configurable' => 1],
            ['code' => 'LEMBUR', 'name' => 'Overtime', 'type' => 'incentive', 'description' => 'Overtime payment', 'default_value' => 0, 'is_active' => 1, 'is_configurable' => 0],
        ];

        $this->db->table('salary_components')->insertBatch($data);
    }

    private function seedSalaryStructures()
    {
        $salaryComps = $this->db->table('salary_components')->select('id, code')->get()->getResultArray();
        $salaryMap = array_column($salaryComps, 'id', 'code');

        // Helper untuk membuat struktur
        $mk = function ($pos, $comp, $base, $type = 'fixed', $unit = null, $context = 'default') use ($salaryMap) {
            return [
                'position_id'         => $pos,
                'salary_component_id' => $salaryMap[$comp],
                'unit_id'             => $unit,
                'context'             => $context,
                'base_value'          => $base,
                'calculation_type'    => $type,
                'multiplier'          => ($type === 'fixed') ? NULL : 1.00,
                'effective_from'      => '2024-01-01',
                'effective_to'        => NULL,
                'created_by'          => NULL,
            ];
        };

        $data = [];

        // ── Gaji pokok (semua jabatan) ──────────────────────────────
        // termasuk 1 (root) & 2 (Direktur): old logic tetap menghitung gaji
        // dgn branch default (tunjangan kinerja 250k×skor→0, absen 0, penempatan, insentif)
        foreach ([1, 2, 35, 36, 40, 41, 42, 43, 44, 45, 46] as $pid) {
            $data[] = $mk($pid, 'GAJI_POKOK', 1500000, 'fixed');
        }

        // ── Tunjangan kinerja ───────────────────────────────────────
        // Root (1) & Direktur (2): fallback 250k (old else-branch), skor 0 → 0 rupiah
        $data[] = $mk(1, 'TUNJANGAN_KINERJA', 250000, 'percent_of_kpi');
        $data[] = $mk(2, 'TUNJANGAN_KINERJA', 250000, 'percent_of_kpi');
        // Admin (35): unit 1 = 850k, unit lain (default) = 250k
        $data[] = $mk(35, 'TUNJANGAN_KINERJA', 850000, 'percent_of_kpi', 1);
        $data[] = $mk(35, 'TUNJANGAN_KINERJA', 250000, 'percent_of_kpi', null);
        // Teknisi (36): 250k
        $data[] = $mk(36, 'TUNJANGAN_KINERJA', 250000, 'percent_of_kpi');
        // SPV (40): 1.25jt
        $data[] = $mk(40, 'TUNJANGAN_KINERJA', 1250000, 'percent_of_kpi');
        // Kepala Toko (41): 850k
        $data[] = $mk(41, 'TUNJANGAN_KINERJA', 850000, 'percent_of_kpi');
        // CS (42): 250k
        $data[] = $mk(42, 'TUNJANGAN_KINERJA', 250000, 'percent_of_kpi');
        // Kepala Divisi/Pengiklan (43): 1jt
        $data[] = $mk(43, 'TUNJANGAN_KINERJA', 1000000, 'percent_of_kpi');
        // Multimedia (44): 250k
        $data[] = $mk(44, 'TUNJANGAN_KINERJA', 250000, 'percent_of_kpi');
        // IT (45): 250k
        $data[] = $mk(45, 'TUNJANGAN_KINERJA', 250000, 'percent_of_kpi');
        // PIC (46): 850k di context non-gaji (penilaian_kinerja/slip_gaji), 250k di gaji
        $data[] = $mk(46, 'TUNJANGAN_KINERJA', 850000, 'percent_of_kpi', null, 'penilaian_kinerja');
        $data[] = $mk(46, 'TUNJANGAN_KINERJA', 850000, 'percent_of_kpi', null, 'slip_gaji');
        $data[] = $mk(46, 'TUNJANGAN_KINERJA', 250000, 'percent_of_kpi', null, 'gaji');

        // ── Tunjangan absen (250k, pakai score absen) ───────────────
        // Old logic: tunjangan_absen = (skor_total2/100) * 250000 untuk SEMUA jabatan
        // Hint: komponen ini dipakai bila skor absen tersedia → percent_of_kpi dengan skor absen.
        // Untuk regression diset 250k base. KPI achievement yang masuk dihitung dari score2.
        foreach ([1, 2, 35, 36, 40, 41, 42, 43, 44, 45, 46] as $pid) {
            $data[] = $mk($pid, 'TUNJANGAN_ABSEN', 250000, 'percent_of_kpi');
        }

        $this->db->table('salary_structures')->insertBatch($data);
    }
}
