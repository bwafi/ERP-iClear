<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Grant akses menu Digital Marketing (10040, 10045, 10047)
 * ke jabatan 40 (SPV) dan 41 (Kepala Toko) agar bisa melihat
 * Detail Prospek & Rekap Harian terkait data customer.
 */
class AddCsAccessDigitalMarketing extends Migration
{
    private array $menuIds = [10040, 10045, 10047];
    private array $targetJabatans = [40, 41];

    public function up()
    {
        foreach ($this->targetJabatans as $jabatanId) {
            $row = $this->db->table('jabatan')
                ->where('ID_JABATAN', $jabatanId)
                ->get()
                ->getRow();

            if (!$row) {
                continue;
            }

            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                $roles = [];
            }

            $merged = array_values(array_unique(array_merge($roles, $this->menuIds)));

            if ($merged !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jabatanId)
                    ->update(['ROLES_JABATAN' => json_encode($merged)]);
            }
        }
    }

    public function down()
    {
        foreach ($this->targetJabatans as $jabatanId) {
            $row = $this->db->table('jabatan')
                ->where('ID_JABATAN', $jabatanId)
                ->get()
                ->getRow();

            if (!$row) {
                continue;
            }

            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                continue;
            }

            $filtered = array_values(array_diff($roles, $this->menuIds));

            if ($filtered !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jabatanId)
                    ->update(['ROLES_JABATAN' => json_encode($filtered)]);
            }
        }
    }
}