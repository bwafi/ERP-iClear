<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Beri akses menu "Target KPI" (idmenu 10140) ke jabatan Manager (34).
 *
 * Sebelumnya menu hanya untuk Admin root (jabatan 1). Manager kini boleh
 * mengatur target KPI; guard di controller (TargetKpi) ikut diperluas.
 */
class GrantTargetKpiMenuToManager extends Migration
{
    private const MENU_ID  = 10140;
    private const MANAGER   = 34;

    public function up()
    {
        foreach ([self::MANAGER] as $jabatanId) {
            $jabatan = $this->db->table('jabatan')
                ->where('ID_JABATAN', $jabatanId)
                ->get()
                ->getRow();

            if (!$jabatan) {
                continue;
            }

            $roles = json_decode($jabatan->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                $roles = [];
            }

            if (!in_array(self::MENU_ID, $roles, true)) {
                $roles[] = self::MENU_ID;
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jabatanId)
                    ->update(['ROLES_JABATAN' => json_encode(array_values($roles))]);
            }
        }
    }

    public function down()
    {
        $jabatan = $this->db->table('jabatan')
            ->where('ID_JABATAN', self::MANAGER)
            ->get()
            ->getRow();

        if ($jabatan) {
            $roles = json_decode($jabatan->ROLES_JABATAN ?? '', true);
            if (is_array($roles)) {
                $filtered = array_values(array_diff($roles, [self::MENU_ID]));
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', self::MANAGER)
                    ->update(['ROLES_JABATAN' => json_encode($filtered)]);
            }
        }
    }
}