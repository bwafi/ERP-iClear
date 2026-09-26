<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sidebar menu "Rekonsiliasi" (10130) untuk modul Rekonsiliasi Harian Finance.
 *
 * Item flat di blok yang sama dengan "Dashboard Finance" (10104), urutan 1241
 * sehingga tampil tepat di bawah Dashboard Finance dan sebelum Hutang Piutang (125).
 *
 * Mengikuti Core::get_menu_show(): categories=1 + parent=0 + show_menu=1 tanpa
 * anak -> dirender sebagai link langsung di sidebar.
 *
 * Grant ke jabatan yang sama dengan Dashboard Finance:
 *  0 ADMIN CENTER, 1 Admin root, 2 Direktur, 34 Manager
 *
 * Catatan: jabatan 2 (Direktur) sudah pernah ada di financeInputRoles,
 * sehingga Direktur bisa mengakses Rekonsiliasi (konsisten dengan Dashboard Finance).
 * Jika tidak ingin menyertakan Direktur, hapus '2' dari targetJabatans.
 */
class AddFinanceRekonsiliasiMenu extends Migration
{
    private const MENU_ID = 10130;

    private array $menuDef = [
        'idmenu'     => self::MENU_ID,
        'urutan'     => 1241,
        'nama_menu'  => 'Rekonsiliasi',
        'roles'      => 'rekonsiliasi', // segmen URL untuk penanda menu aktif
        'url'        => 'finance/rekonsiliasi',
        'show_menu'  => 1,
        'sub'        => 0,
        'parent'     => 0, // item flat di blok KEPEGAWAIAN/ sidebar-utama
        'utama'      => 1,
        'categories' => 1,
        'icon'       => '<iconify-icon icon="solar:clipboard-check-bold" width="22" height="22"></iconify-icon>',
    ];

    // Jabatan yang berhak mengakses menu ini.
    private array $targetJabatans = [0, 1, 2, 34];

    public function up()
    {
        // Insert menu jika belum ada
        $exists = $this->db->table('menu')
            ->where('idmenu', self::MENU_ID)
            ->countAllResults();

        if ($exists === 0) {
            $this->db->table('menu')->insert($this->menuDef);
        }

        // Grant ke jabatan target
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

            $filtered = array_values(array_filter(
                $roles,
                static fn($id) => (int) $id !== self::MENU_ID
            ));

            if ($filtered !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jabatanId)
                    ->update(['ROLES_JABATAN' => json_encode($filtered)]);
            }
        }
    }
}