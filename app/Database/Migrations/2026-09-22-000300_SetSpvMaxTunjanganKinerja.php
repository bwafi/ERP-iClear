<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Maksimum tunjangan kinerja SPV (jabatan 40) naik ke Rp1.500.000.
 *
 * Sesuai pedoman KPI SPV §XVIII. Dataset-driven, BUKAN destruktif:
 * hanya meng-update nilai baris salary_structures TUNJANGAN_KINERJA (id 2)
 * untuk jabatan 40 — baris & tabel lain tidak tersentuh.
 *
 * Catatan: KPIConfigurationSeeder adalah source-of-truth untuk instalasi
 * baru (run()-nya me-truncate tabel config KPI). Untuk perubahan nilai pada
 * data existing, pakai migration ini — jangan re-run seeder di production.
 */
class SetSpvMaxTunjanganKinerja extends Migration
{
    public function up()
    {
        $this->db->table('salary_structures')
            ->where('position_id', 40)
            ->where('salary_component_id', 2) // TUNJANGAN_KINERJA
            ->update(['base_value' => 1500000.00]);
    }

    public function down()
    {
        $this->db->table('salary_structures')
            ->where('position_id', 40)
            ->where('salary_component_id', 2) // TUNJANGAN_KINERJA
            ->update(['base_value' => 1250000.00]);
    }
}