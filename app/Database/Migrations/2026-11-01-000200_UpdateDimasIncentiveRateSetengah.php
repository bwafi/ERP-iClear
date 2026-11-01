<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rate insentif Dimas (67) unit 5 periode Oktober 2026: 1,5% -> 0,75%.
 *
 * Permintaan user: insentif Dimas menjadi SETENGAH dari 1,5%, yaitu 0,75%
 * dari gross profit unit 5. Hanya baris periode 2026-10 yang disentuh;
 * karyawan lain dan periode lain tidak diubah. IDEMPOTEN.
 */
class UpdateDimasIncentiveRateSetengah extends Migration
{
    public function up()
    {
        $this->db->table('incentive_overrides')
            ->where('employee_id', 67)
            ->where('unit_id', 5)
            ->where('period', '2026-10')
            ->update(['rate' => 0.75, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function down()
    {
        $this->db->table('incentive_overrides')
            ->where('employee_id', 67)
            ->where('unit_id', 5)
            ->where('period', '2026-10')
            ->update(['rate' => 1.50, 'updated_at' => date('Y-m-d H:i:s')]);
    }
}
