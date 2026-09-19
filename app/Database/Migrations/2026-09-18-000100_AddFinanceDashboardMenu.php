<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambah menu sidebar "Dashboard Finance" (10104) di area KEPEGAWAIAN.
 *
 * Bukan anak kategori DASHBOARD — ditempatkan sebagai item flat dengan
 * urutan 124 (sesudah Social Media 120, sebelum divider KEUANGAN 150),
 * sehingga tampil di dalam blok menu KEPEGAWAIAN.
 *
 * Hak akses hanya untuk:
 *  - 0  ADMIN CENTER
 *  - 1  Admin root
 *  - 2  Direktur
 *  - 34 Manager
 * (ROLES_JABATAN milik jabatan lain tidak disentuh).
 *
 * Idempotent: mengecek keberadaan sebelum insert/merge — aman dijalankan ulang.
 */
class AddFinanceDashboardMenu extends Migration
{
    private const MENU_ID = 10104;

    private array $menuDef = [
        'idmenu'     => self::MENU_ID,
        'urutan'     => 124,
        'nama_menu'  => 'Dashboard Finance',
        'roles'      => 'dashboard_finance', // segmen URL untuk penanda menu aktif
        'url'        => 'dashboard/finance',
        'show_menu'  => 1,
        'sub'        => 0,
        'parent'     => 0, // item flat di blok KEPEGAWAIAN
        'utama'      => 1,
        'categories' => 1,
        'icon'       => '<iconify-icon icon="solar:wallet-money-bold" width="22" height="22"></iconify-icon>',
    ];

    // Jabatan yang berhak melihat Dashboard Finance.
    private array $targetJabatans = [0, 1, 2, 34];

    public function up()
    {
        $exists = $this->db->table('menu')
            ->where('idmenu', self::MENU_ID)
            ->countAllResults();

        if ($exists === 0) {
            $this->db->table('menu')->insert($this->menuDef);
        }

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

            $merged = array_values(array_unique(array_merge($roles, [self::MENU_ID])));

            if ($merged !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jabatanId)
                    ->update(['ROLES_JABATAN' => json_encode($merged)]);
            }
        }
    }

    public function down()
    {
        $this->db->table('menu')->where('idmenu', self::MENU_ID)->delete();

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

            $roles = array_values(array_filter(
                $roles,
                static fn($id) => (int) $id !== self::MENU_ID
            ));

            $this->db->table('jabatan')
                ->where('ID_JABATAN', $jabatanId)
                ->update(['ROLES_JABATAN' => json_encode($roles)]);
        }
    }
}

