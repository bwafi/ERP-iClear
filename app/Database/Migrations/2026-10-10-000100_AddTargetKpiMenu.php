<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Menu "Target KPI" (editor kpi_targets) — HANYA untuk Admin root (jabatan 1).
 *
 * Halaman ini dipakai untuk mengatur target omset / target KPI di tabel
 * kpi_targets. Karena mengubah nilai target berdampak langsung ke perhitungan
 * KPI, akses menu hanya diberikan ke jabatan Admin root (ID_JABATAN = 1).
 * Penegakan akses juga dilakukan di controller (TargetKpi).
 */
class AddTargetKpiMenu extends Migration
{
    private const MENU_ID = 10140;

    public function up()
    {
        $existing = $this->db->table('menu')->where('idmenu', self::MENU_ID)->get()->getRow();
        if (!$existing) {
            $this->db->table('menu')->insert([
                'idmenu'     => self::MENU_ID,
                'urutan'     => self::MENU_ID,
                'nama_menu'  => 'Target KPI',
                'roles'      => 'kpi_target_editor',
                'url'        => 'penilaian/kpi/target',
                'show_menu'  => 1,
                'sub'        => 0,
                'parent'     => 0,
                'utama'      => 1,
                'categories' => 1,
                'icon'       => '<iconify-icon icon="solar:target-bold" width="24" height="24"></iconify-icon>',
                'manualbook' => null,
            ]);
        }

        // Grant akses menu HANYA ke jabatan Admin root (ID_JABATAN = 1).
        $jabatan = $this->db->table('jabatan')
            ->where('ID_JABATAN', 1)
            ->get()
            ->getRow();

        if ($jabatan) {
            $roles = json_decode($jabatan->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                $roles = [];
            }

            if (!in_array(self::MENU_ID, $roles, true)) {
                $roles[] = self::MENU_ID;
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', 1)
                    ->update(['ROLES_JABATAN' => json_encode(array_values($roles))]);
            }
        }
    }

    public function down()
    {
        $jabatan = $this->db->table('jabatan')
            ->where('ID_JABATAN', 1)
            ->get()
            ->getRow();

        if ($jabatan) {
            $roles = json_decode($jabatan->ROLES_JABATAN ?? '', true);
            if (is_array($roles)) {
                $filtered = array_values(array_diff($roles, [self::MENU_ID]));
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', 1)
                    ->update(['ROLES_JABATAN' => json_encode($filtered)]);
            }
        }

        $this->db->table('menu')->where('idmenu', self::MENU_ID)->delete();
    }
}