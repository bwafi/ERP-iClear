<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pastikan menu sidebar "Penilaian" (10025/10026/10027) selalu ada,
 * termasuk hak aksesnya di jabatan.ROLES_JABATAN.
 *
 * LATAR BELAKANG: tabel menu tidak dikelola oleh seeder/migration utama sehingga
 * saat DB di-restore ke snapshot lama, menu Penilaian (id > 10024) bisa hilang
 * bersama id-nya di ROLES_JABATAN. Migration ini idempotent — aman dijalankan
 * berulang pada environment mana pun (mengecek keberadaan sebelum insert/update).
 */
class EnsurePenilaianSidebarMenus extends Migration
{
    private array $menuDefs = [
        [
            'idmenu'       => 10025,
            'urutan'       => 106,
            'nama_menu'    => 'Penilaian',
            'roles'        => 'penilaian',
            'url'          => '',
            'show_menu'    => 1,
            'sub'          => 0,
            'parent'       => 0,
            'utama'        => 1,
            'categories'   => 1,
            'icon'         => '<iconify-icon icon="solar:user-check-bold" width="24" height="24"></iconify-icon>',
        ],
        [
            'idmenu'       => 10026,
            'urutan'       => 107,
            'nama_menu'    => 'Penilaian KPI',
            'roles'        => 'penilaian_kpi',
            'url'          => 'penilaian/kpi',
            'show_menu'    => 1,
            'sub'          => 0,
            'parent'       => 10025,
            'utama'        => 1,
            'categories'   => 0,
            'icon'         => null,
        ],
        [
            'idmenu'       => 10027,
            'urutan'       => 108,
            'nama_menu'    => 'Penilaian Absensi',
            'roles'        => 'penilaian_absen',
            'url'          => 'penilaian/absen',
            'show_menu'    => 1,
            'sub'          => 0,
            'parent'       => 10025,
            'utama'        => 1,
            'categories'   => 0,
            'icon'         => null,
        ],
    ];

    // Role yang berhak melihat menu Penilaian (evaluator).
    private array $targetJabatans = [0, 1, 2, 34, 35, 40, 41, 43, 45];

    public function up()
    {
        foreach ($this->menuDefs as $def) {
            $exists = $this->db->table('menu')
                ->where('idmenu', $def['idmenu'])
                ->countAllResults();

            if ($exists === 0) {
                $this->db->table('menu')->insert($def);
            }
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

            $merged = array_values(array_unique(array_merge(
                $roles,
                [10025, 10026, 10027]
            )));

            if ($merged !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jabatanId)
                    ->update(['ROLES_JABATAN' => json_encode($merged)]);
            }
        }
    }

    public function down()
    {
        // Tidak ada rollback — menu ini bagian dari aplikasi.
    }
}