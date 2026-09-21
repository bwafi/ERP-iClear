<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sidebar "Konfirmasi Terima Mutasi" + grant akses.
 *
 * Setelah admin penerima mengonfirmasi penerimaan mutasi, H/P antar unit
 * dibuat (jatuh tempo = tanggal terima + 3 hari). Menu 26 adalah anak dari
 * "Mutasi Stok" (25). Id 26 diklaim gratis (di range 24..60 mulai 27 dipakai;
 * 26 kosong).
 */
class AddKonfirmasiTerimaMutasiMenu extends Migration
{
    public function up()
    {
        $ada = $this->db->table('menu')->where('idmenu', 26)->get()->getRow();
        if (!$ada) {
            $this->db->table('menu')->insert([
                'idmenu'     => 26,
                'urutan'     => 2,
                'nama_menu'  => 'Konfirmasi Terima Mutasi',
                'roles'      => 'mutasi_stok',
                'url'        => 'mutasi_stok/masuk',
                'show_menu'  => 1,
                'sub'        => 0,
                'parent'     => 25,
                'utama'      => 0,
                'categories' => 0,
                'icon'       => null,
                'manualbook' => null,
            ]);
        }

        // Grant ke jabatan yang sudah punya akses "Mutasi Stok" (25).
        $jabatan = $this->db->table('jabatan')->select('ID_JABATAN, ROLES_JABATAN')->get()->getResult();
        foreach ($jabatan as $j) {
            $roles = json_decode($j->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                continue;
            }
            if (!in_array('25', $roles, true)) {
                continue;
            }
            if (in_array('26', $roles, true)) {
                continue;
            }
            $roles[] = '26';
            $this->db->table('jabatan')
                ->where('ID_JABATAN', $j->ID_JABATAN)
                ->update(['ROLES_JABATAN' => json_encode(array_values($roles))]);
        }
    }

    public function down()
    {
        $this->db->table('menu')->where('idmenu', 26)->delete();

        $jabatan = $this->db->table('jabatan')->select('ID_JABATAN, ROLES_JABATAN')->get()->getResult();
        foreach ($jabatan as $j) {
            $roles = json_decode($j->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                continue;
            }
            $roles = array_values(array_diff($roles, ['26']));
            $this->db->table('jabatan')
                ->where('ID_JABATAN', $j->ID_JABATAN)
                ->update(['ROLES_JABATAN' => json_encode($roles)]);
        }
    }
}