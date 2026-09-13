<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Grant akses menu Customer Satisfaction + Digital Marketing (Detail Prospek & Rekap Harian)
 * ke jabatan 42 (Customer Service) agar Indah cs. bisa mengisi CS dan melihat data prospek.
 */
class AddCsAccessForJabatan42 extends Migration
{
    private array $menuIds = [
        10025, // Penilaian (parent)
        10049, // Customer Satisfaction
        10040, // Digital Marketing (parent)
        10045, // Detail Prospek
        10047, // Rekap Harian
    ];
    private int $targetJabatan = 42;

    public function up()
    {
        $row = $this->db->table('jabatan')
            ->where('ID_JABATAN', $this->targetJabatan)
            ->get()
            ->getRow();

        if (!$row) {
            return;
        }

        $roles = json_decode($row->ROLES_JABATAN ?? '', true);
        if (!is_array($roles)) {
            $roles = [];
        }

        $merged = array_values(array_unique(array_merge($roles, $this->menuIds)));

        if ($merged !== $roles) {
            $this->db->table('jabatan')
                ->where('ID_JABATAN', $this->targetJabatan)
                ->update(['ROLES_JABATAN' => json_encode($merged)]);
        }
    }

    public function down()
    {
        $row = $this->db->table('jabatan')
            ->where('ID_JABATAN', $this->targetJabatan)
            ->get()
            ->getRow();

        if (!$row) {
            return;
        }

        $roles = json_decode($row->ROLES_JABATAN ?? '', true);
        if (!is_array($roles)) {
            return;
        }

        $filtered = array_values(array_diff($roles, $this->menuIds));

        if ($filtered !== $roles) {
            $this->db->table('jabatan')
                ->where('ID_JABATAN', $this->targetJabatan)
                ->update(['ROLES_JABATAN' => json_encode($filtered)]);
        }
    }
}