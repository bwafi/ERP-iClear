<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambah menu sidebar "Customer Satisfaction" (10049) di bawah menu Penilaian
 * (10025), beserta hak akses di jabatan.ROLES_JABATAN.
 *
 * Idempotent: mengecek keberadaan sebelum insert/merge — aman dijalankan
 * berulang pada environment mana pun.
 */
class AddCustomerSatisfactionMenu extends Migration
{
    private array $menuDef = [
        'idmenu'     => 10049,
        'urutan'     => 109,
        'nama_menu'  => 'Customer Satisfaction',
        'roles'      => 'customer_satisfaction',
        'url'        => 'penilaian/customer_satisfaction',
        'show_menu'  => 1,
        'sub'        => 0,
        'parent'     => 10025,
        'utama'      => 1,
        'categories' => 0,
        'icon'       => null,
    ];

    // Role yang berhak melihat menu (Kepala Toko input, SPV lihat area, dst).
    private array $targetJabatans = [0, 1, 2, 34, 35, 40, 41, 43, 45];

    public function up()
    {
        $exists = $this->db->table('menu')
            ->where('idmenu', $this->menuDef['idmenu'])
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

            $merged = array_values(array_unique(array_merge($roles, [$this->menuDef['idmenu']])));

            if ($merged !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jabatanId)
                    ->update(['ROLES_JABATAN' => json_encode($merged)]);
            }
        }
    }

    public function down()
    {
        $this->db->table('menu')->where('idmenu', $this->menuDef['idmenu'])->delete();
    }
}